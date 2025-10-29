<?php
/**
 * Enhanced importer functionality with Elementor support and comprehensive validation.
 *
 * @since      2.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/includes
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class LD_Enhanced_Importer
 * 
 * Handles comprehensive import operations with validation, conflict resolution, and Elementor support.
 */
class LD_Enhanced_Importer {

    /**
     * Validate import file with detailed logging.
     * 
     * @param string $file_path Path to import file.
     * @return array|WP_Error Validation result or error.
     * @since 2.0.0
     */
    public function ld_import_validate_file( $file_path ) {
        ld_log( 'validation', "Starting file validation - File: " . basename( $file_path ), 'info' );
        
        $validation_result = array(
            'valid' => false,
            'errors' => array(),
            'warnings' => array(),
            'data_summary' => array(),
            'compatibility' => array()
        );
        
        try {
            // File existence check
            if ( ! file_exists( $file_path ) ) {
                $validation_result['errors'][] = "File not found: {$file_path}";
                ld_log( 'validation', "File not found: {$file_path}", 'error' );
                return $validation_result;
            }
            
            // File size check
            $file_size = filesize( $file_path );
            $max_size = wp_max_upload_size();
            
            if ( $file_size > $max_size ) {
                $validation_result['errors'][] = "File too large: " . size_format( $file_size ) . " (max: " . size_format( $max_size ) . ")";
                ld_log( 'validation', "File too large - Size: " . size_format( $file_size ), 'error' );
                return $validation_result;
            }
            
            ld_log( 'validation', "File size: " . size_format( $file_size ), 'debug' );
            
            // JSON validation
            $content = file_get_contents( $file_path );
            if ( ! $content ) {
                $validation_result['errors'][] = "Could not read file content";
                ld_log( 'validation', "Could not read file content", 'error' );
                return $validation_result;
            }
            
            $data = json_decode( $content, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                $validation_result['errors'][] = 'JSON parsing failed: ' . json_last_error_msg();
                ld_log( 'validation', 'JSON parsing failed: ' . json_last_error_msg(), 'error' );
                return $validation_result;
            }
            
            ld_log( 'validation', "JSON parsed successfully", 'info' );
            
            // Schema validation
            $schema_validation = $this->validate_import_schema( $data );
            if ( ! $schema_validation['valid'] ) {
                $validation_result['errors'] = array_merge( $validation_result['errors'], $schema_validation['errors'] );
                return $validation_result;
            }
            
            // Version compatibility check
            $compatibility_check = $this->check_version_compatibility( $data );
            $validation_result['compatibility'] = $compatibility_check;
            
            if ( ! $compatibility_check['compatible'] ) {
                $validation_result['warnings'][] = $compatibility_check['message'];
                ld_log( 'validation', $compatibility_check['message'], 'warning' );
            }
            
            // Content analysis
            $content_analysis = $this->analyze_import_content( $data );
            $validation_result['data_summary'] = $content_analysis;
            
            // Dependency checks
            $dependency_check = $this->check_dependencies( $data );
            if ( ! empty( $dependency_check['missing'] ) ) {
                foreach ( $dependency_check['missing'] as $missing ) {
                    $validation_result['warnings'][] = "Missing dependency: {$missing}";
                }
            }
            
            // Conflict detection
            $conflicts = $this->detect_potential_conflicts( $data );
            if ( ! empty( $conflicts ) ) {
                $validation_result['warnings'][] = "Found " . count( $conflicts ) . " potential conflicts";
                $validation_result['conflicts'] = $conflicts;
            }
            
            $validation_result['valid'] = true;
            
            ld_log( 'validation', "Validation passed - Courses: {$content_analysis['courses']}, Warnings: " . count( $validation_result['warnings'] ), 'info' );
            
        } catch ( Exception $e ) {
            $validation_result['errors'][] = "Validation error: " . $e->getMessage();
            ld_log( 'validation', "Validation error: " . $e->getMessage(), 'error' );
        }
        
        return $validation_result;
    }
    
    /**
     * Check for conflicts with existing content.
     * 
     * @param array $import_data Import data to check.
     * @return array Conflict analysis.
     * @since 2.0.0
     */
    public function ld_import_check_conflicts( $import_data ) {
        ld_log( 'conflict_check', "Starting conflict detection", 'info' );
        
        $conflicts = array(
            'courses' => array(),
            'lessons' => array(),
            'topics' => array(),
            'quizzes' => array(),
            'questions' => array(),
            'certificates' => array(),
            'taxonomies' => array()
        );
        
        $conflict_count = 0;
        
        // Check course conflicts
        if ( isset( $import_data['courses'] ) ) {
            foreach ( $import_data['courses'] as $course_data ) {
                $course_title = $course_data['post_data']['post_title'];
                $course_slug = $course_data['post_data']['post_name'];
                
                // Check by title
                $existing_by_title = get_posts( array(
                    'post_type' => 'sfwd-courses',
                    'title' => $course_title,
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ) );
                
                // Check by slug
                $existing_by_slug = get_posts( array(
                    'post_type' => 'sfwd-courses',
                    'name' => $course_slug,
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ) );
                
                if ( ! empty( $existing_by_title ) || ! empty( $existing_by_slug ) ) {
                    $conflicts['courses'][] = array(
                        'import_title' => $course_title,
                        'import_slug' => $course_slug,
                        'existing_id' => ! empty( $existing_by_title ) ? $existing_by_title[0] : $existing_by_slug[0],
                        'conflict_type' => ! empty( $existing_by_title ) ? 'title' : 'slug'
                    );
                    $conflict_count++;
                }
                
                // Check lesson conflicts within course
                if ( isset( $course_data['lessons'] ) ) {
                    foreach ( $course_data['lessons'] as $lesson_data ) {
                        $lesson_title = $lesson_data['post_data']['post_title'];
                        
                        $existing_lesson = get_posts( array(
                            'post_type' => 'sfwd-lessons',
                            'title' => $lesson_title,
                            'posts_per_page' => 1,
                            'fields' => 'ids'
                        ) );
                        
                        if ( ! empty( $existing_lesson ) ) {
                            $conflicts['lessons'][] = array(
                                'import_title' => $lesson_title,
                                'existing_id' => $existing_lesson[0],
                                'course_title' => $course_title
                            );
                            $conflict_count++;
                        }
                    }
                }
            }
        }
        
        // Check taxonomy conflicts
        if ( isset( $import_data['taxonomies'] ) ) {
            foreach ( $import_data['taxonomies'] as $taxonomy => $terms ) {
                if ( ! taxonomy_exists( $taxonomy ) ) {
                    $conflicts['taxonomies'][] = array(
                        'taxonomy' => $taxonomy,
                        'issue' => 'taxonomy_not_exists'
                    );
                    continue;
                }
                
                foreach ( $terms as $term_data ) {
                    $existing_term = get_term_by( 'slug', $term_data['slug'], $taxonomy );
                    if ( $existing_term ) {
                        $conflicts['taxonomies'][] = array(
                            'taxonomy' => $taxonomy,
                            'import_term' => $term_data['name'],
                            'existing_id' => $existing_term->term_id,
                            'conflict_type' => 'slug'
                        );
                        $conflict_count++;
                    }
                }
            }
        }
        
        ld_log( 'conflict_check', "Conflict detection completed - Found {$conflict_count} conflicts", 'info' );
        
        return array(
            'total_conflicts' => $conflict_count,
            'conflicts' => $conflicts,
            'has_conflicts' => $conflict_count > 0
        );
    }
    
    /**
     * Import course with comprehensive data handling.
     * 
     * @param array $course_data Course data to import.
     * @param array $options     Import options.
     * @param array $id_map      ID mapping for references.
     * @return array Import result.
     * @since 2.0.0
     */
    public function ld_import_course( $course_data, $options = array(), &$id_map = array() ) {
        $course_title = $course_data['post_data']['post_title'];
        
        ld_log( 'import', "=== Starting course import - Title: '{$course_title}' ===", 'info' );
        
        $import_result = array(
            'success' => false,
            'course_id' => null,
            'warnings' => array(),
            'imported_items' => array(
                'lessons' => 0,
                'topics' => 0,
                'quizzes' => 0,
                'questions' => 0
            )
        );
        
        try {
            // Handle duplicate course based on options
            $existing_course = $this->find_existing_course( $course_data );
            
            if ( $existing_course && $options['duplicate_handling'] === 'skip' ) {
                ld_log( 'import', "Course skipped - already exists: '{$course_title}'", 'info' );
                $import_result['course_id'] = $existing_course;
                $import_result['success'] = true;
                return $import_result;
            }
            
            // Prepare post data
            $post_data = $course_data['post_data'];
            $original_id = $post_data['ID'];
            unset( $post_data['ID'] ); // Remove old ID
            
            // Handle duplicate naming
            if ( $existing_course && $options['duplicate_handling'] === 'rename' ) {
                $post_data['post_title'] = $this->generate_unique_title( $post_data['post_title'], 'sfwd-courses' );
                $post_data['post_name'] = sanitize_title( $post_data['post_title'] );
            }
            
            // Create course post
            $new_course_id = wp_insert_post( $post_data );
            if ( is_wp_error( $new_course_id ) ) {
                throw new Exception( $new_course_id->get_error_message() );
            }
            
            ld_log( 'import', "Course post created - New ID: {$new_course_id}", 'info' );
            
            // Update ID mapping
            $id_map['courses'][ $original_id ] = $new_course_id;
            $import_result['course_id'] = $new_course_id;
            
            // Import post meta with serialization handling
            $this->import_post_meta_preserved( $new_course_id, $course_data['post_meta'], $id_map );
            
            // Import Elementor data if present
            if ( ! empty( $course_data['elementor_data']['has_elementor'] ) && $options['include_elementor'] ) {
                $elementor_result = $this->import_elementor_data( $new_course_id, $course_data['elementor_data'], $id_map );
                if ( ! $elementor_result ) {
                    $import_result['warnings'][] = "Elementor data import failed for course: {$course_title}";
                }
            }
            
            // Import lessons
            if ( ! empty( $course_data['lessons'] ) ) {
                foreach ( $course_data['lessons'] as $lesson_data ) {
                    $lesson_result = $this->import_lesson( $lesson_data, $new_course_id, $options, $id_map );
                    if ( $lesson_result['success'] ) {
                        $import_result['imported_items']['lessons']++;
                        $import_result['imported_items']['topics'] += $lesson_result['imported_items']['topics'];
                        $import_result['imported_items']['quizzes'] += $lesson_result['imported_items']['quizzes'];
                    } else {
                        $import_result['warnings'] = array_merge( $import_result['warnings'], $lesson_result['warnings'] );
                    }
                }
            }
            
            // Import global course quizzes
            if ( ! empty( $course_data['global_course_quizzes'] ) ) {
                foreach ( $course_data['global_course_quizzes'] as $quiz_data ) {
                    $quiz_result = $this->import_quiz( $quiz_data, $new_course_id, null, $options, $id_map );
                    if ( $quiz_result['success'] ) {
                        $import_result['imported_items']['quizzes']++;
                        $import_result['imported_items']['questions'] += $quiz_result['imported_items']['questions'];
                    }
                }
            }
            
            // Import certificates
            if ( ! empty( $course_data['certificates'] ) && $options['include_certificates'] ) {
                foreach ( $course_data['certificates'] as $certificate_data ) {
                    $certificate_id = $this->import_certificate( $certificate_data, $options, $id_map );
                    if ( $certificate_id ) {
                        update_post_meta( $new_course_id, '_ld_certificate', $certificate_id );
                        ld_log( 'import', "Certificate linked to course - Certificate ID: {$certificate_id}", 'info' );
                    }
                }
            }
            
            // Import taxonomies
            if ( ! empty( $course_data['taxonomies'] ) && $options['include_taxonomies'] ) {
                $this->import_post_taxonomies( $new_course_id, $course_data['taxonomies'], $id_map );
            }
            
            $import_result['success'] = true;
            
            $duration = microtime( true ) - $start_time ?? microtime( true );
            ld_log( 'import', "=== Course import completed - New ID: {$new_course_id}, Items: " . json_encode( $import_result['imported_items'] ) . " ===", 'info' );
            
        } catch ( Exception $e ) {
            ld_log( 'import', "Course import failed - Title: '{$course_title}', Error: " . $e->getMessage(), 'error' );
            $import_result['warnings'][] = "Course import failed: " . $e->getMessage();
        }
        
        return $import_result;
    }
    
    /**
     * Import Elementor data with reference updating.
     * 
     * @param int   $post_id      Post ID to import Elementor data to.
     * @param array $elementor_data Elementor data structure.
     * @param array $id_map       ID mapping for reference updates.
     * @return bool Success status.
     * @since 2.0.0
     */
    private function import_elementor_data( $post_id, $elementor_data, $id_map ) {
        ld_log( 'elementor', "Starting Elementor import - Post ID: {$post_id}", 'info' );
        
        if ( ! $elementor_data['has_elementor'] || empty( $elementor_data['elementor_data'] ) ) {
            ld_log( 'elementor', "No Elementor data to import - Post ID: {$post_id}", 'debug' );
            return true;
        }
        
        try {
            // Process Elementor data
            $processed_data = $this->process_elementor_references( $elementor_data['elementor_data'], $id_map );
            
            // Save Elementor data
            update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $processed_data ) ) );
            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
            
            // Import page settings
            if ( ! empty( $elementor_data['page_settings'] ) ) {
                update_post_meta( $post_id, '_elementor_page_settings', $elementor_data['page_settings'] );
                ld_log( 'elementor', "Page settings imported - Post ID: {$post_id}", 'debug' );
            }
            
            // Import CSS data
            if ( ! empty( $elementor_data['css_data'] ) ) {
                update_post_meta( $post_id, '_elementor_css', $elementor_data['css_data'] );
                ld_log( 'elementor', "CSS data imported - Post ID: {$post_id}", 'debug' );
            }
            
            ld_log( 'elementor', "Elementor data imported successfully - Post ID: {$post_id}", 'info' );
            
            return true;
            
        } catch ( Exception $e ) {
            ld_log( 'elementor', "Elementor import failed - Post ID: {$post_id}, Error: " . $e->getMessage(), 'error' );
            return false;
        }
    }
    
    /**
     * Import post meta with serialization preservation.
     * 
     * @param int   $post_id   Post ID.
     * @param array $meta_data Meta data to import.
     * @param array $id_map    ID mapping for reference updates.
     */
    private function import_post_meta_preserved( $post_id, $meta_data, $id_map ) {
        $serialized_count = 0;
        $updated_refs = 0;
        
        foreach ( $meta_data as $key => $value ) {
            // Handle serialized data
            if ( is_string( $value ) && strpos( $value, 'serialized:' ) === 0 ) {
                $value = substr( $value, 11 ); // Remove 'serialized:' prefix
                $serialized_count++;
                ld_log( 'serialization', "Importing serialized meta - Key: {$key}", 'debug' );
            }
            
            // Update references in meta values
            $updated_value = $this->update_meta_references( $value, $id_map, $updated_refs );
            
            update_post_meta( $post_id, $key, $updated_value );
        }
        
        if ( $serialized_count > 0 ) {
            ld_log( 'import', "Imported {$serialized_count} serialized meta fields for post {$post_id}", 'info' );
        }
        
        if ( $updated_refs > 0 ) {
            ld_log( 'import', "Updated {$updated_refs} references in meta data for post {$post_id}", 'info' );
        }
    }
    
    /**
     * Validate import schema structure.
     * 
     * @param array $data Import data.
     * @return array Validation result.
     */
    private function validate_import_schema( $data ) {
        $result = array( 'valid' => true, 'errors' => array() );
        
        // Required top-level fields
        $required_fields = array( 'export_meta', 'courses' );
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                $result['errors'][] = "Missing required field: {$field}";
                $result['valid'] = false;
            }
        }
        
        // Validate export meta
        if ( isset( $data['export_meta'] ) ) {
            $required_meta = array( 'version', 'timestamp' );
            foreach ( $required_meta as $meta_field ) {
                if ( ! isset( $data['export_meta'][ $meta_field ] ) ) {
                    $result['errors'][] = "Missing export meta field: {$meta_field}";
                    $result['valid'] = false;
                }
            }
        }
        
        // Validate courses structure
        if ( isset( $data['courses'] ) && is_array( $data['courses'] ) ) {
            foreach ( $data['courses'] as $index => $course ) {
                if ( ! isset( $course['post_data'] ) || ! isset( $course['post_meta'] ) ) {
                    $result['errors'][] = "Invalid course structure at index {$index}";
                    $result['valid'] = false;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check version compatibility.
     * 
     * @param array $data Import data.
     * @return array Compatibility result.
     */
    private function check_version_compatibility( $data ) {
        $export_version = $data['export_meta']['version'] ?? 'unknown';
        $current_version = '2.0';
        
        $compatible = version_compare( $export_version, '1.0', '>=' );
        
        return array(
            'compatible' => $compatible,
            'export_version' => $export_version,
            'current_version' => $current_version,
            'message' => $compatible ? 
                "Compatible version: {$export_version}" : 
                "Incompatible version: {$export_version} (requires 1.0+)"
        );
    }
    
    /**
     * Analyze import content for summary.
     * 
     * @param array $data Import data.
     * @return array Content analysis.
     */
    private function analyze_import_content( $data ) {
        $analysis = array(
            'courses' => 0,
            'lessons' => 0,
            'topics' => 0,
            'quizzes' => 0,
            'questions' => 0,
            'certificates' => 0,
            'has_elementor' => false,
            'has_serialized' => false,
            'taxonomies' => 0
        );
        
        if ( isset( $data['courses'] ) ) {
            $analysis['courses'] = count( $data['courses'] );
            
            foreach ( $data['courses'] as $course ) {
                // Count lessons and topics
                if ( isset( $course['lessons'] ) ) {
                    $analysis['lessons'] += count( $course['lessons'] );
                    
                    foreach ( $course['lessons'] as $lesson ) {
                        if ( isset( $lesson['topics'] ) ) {
                            $analysis['topics'] += count( $lesson['topics'] );
                        }
                        if ( isset( $lesson['lesson_quizzes'] ) ) {
                            $analysis['quizzes'] += count( $lesson['lesson_quizzes'] );
                        }
                    }
                }
                
                // Check for Elementor content
                if ( isset( $course['elementor_data']['has_elementor'] ) && $course['elementor_data']['has_elementor'] ) {
                    $analysis['has_elementor'] = true;
                }
                
                // Check for serialized data
                if ( isset( $course['post_meta'] ) ) {
                    foreach ( $course['post_meta'] as $value ) {
                        if ( is_string( $value ) && strpos( $value, 'serialized:' ) === 0 ) {
                            $analysis['has_serialized'] = true;
                            break;
                        }
                    }
                }
                
                // Count certificates
                if ( isset( $course['certificates'] ) ) {
                    $analysis['certificates'] += count( $course['certificates'] );
                }
                
                // Count taxonomies
                if ( isset( $course['taxonomies'] ) ) {
                    foreach ( $course['taxonomies'] as $taxonomy => $terms ) {
                        $analysis['taxonomies'] += count( $terms );
                    }
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * Additional helper methods would continue here...
     * (Truncated for brevity - would include methods for:
     * - detect_potential_conflicts()
     * - check_dependencies() 
     * - find_existing_course()
     * - generate_unique_title()
     * - import_lesson()
     * - import_quiz()
     * - import_certificate()
     * - import_post_taxonomies()
     * - process_elementor_references()
     * - update_meta_references()
     * etc.)
     */
}
