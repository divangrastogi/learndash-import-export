<?php
/**
 * Bulk export functionality for the plugin.
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
 * Class LD_Bulk_Exporter
 * 
 * Handles bulk export operations with chunking, progress tracking, and comprehensive logging.
 */
class LD_Bulk_Exporter {

    /**
     * AJAX handler for bulk course export.
     * 
     * Processes courses in chunks with progress tracking and memory management.
     * 
     * @since 2.0.0
     */
    public function ld_ajax_bulk_export_courses() {
        // Verify nonce and permissions
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ld_bulk_export' ) || ! current_user_can( 'manage_options' ) ) {
            ld_log( 'bulk_export', 'Unauthorized bulk export attempt', 'error' );
            wp_die( 'Unauthorized' );
        }
        
        $session_id = sanitize_text_field( $_POST['session_id'] );
        $chunk_index = intval( $_POST['chunk_index'] );
        
        ld_log( 'bulk_export', "Starting bulk export chunk {$chunk_index} for session {$session_id}", 'info' );
        
        try {
            // Get session data
            $session_data = get_transient( "ld_export_session_{$session_id}" );
            if ( ! $session_data ) {
                throw new Exception( 'Export session not found or expired' );
            }
            
            // Get chunk data
            $chunk_data = get_transient( "ld_export_chunk_{$session_id}_{$chunk_index}" );
            if ( ! $chunk_data ) {
                throw new Exception( "Chunk {$chunk_index} data not found" );
            }
            
            // Process chunk
            $start_time = microtime( true );
            $result = $this->process_export_chunk( $chunk_data, $session_data );
            $duration = microtime( true ) - $start_time;
            
            ld_log( 'bulk_export', "Chunk {$chunk_index} completed in {$duration}s - Items: {$result['items_processed']}", 'info' );
            
            // Update session progress
            $this->update_session_progress( $session_id, $chunk_index, $result );
            
            // Memory cleanup
            $this->cleanup_chunk_memory();
            
            wp_send_json_success( array(
                'items_processed' => $result['items_processed'],
                'current_item' => $result['current_item'],
                'warnings' => $result['warnings'],
                'chunk_duration' => $duration,
                'memory_usage' => round( memory_get_usage() / 1024 / 1024, 2 ),
                'export_data' => $result['export_data']
            ) );
            
        } catch ( Exception $e ) {
            ld_log( 'bulk_export', "Chunk {$chunk_index} failed: " . $e->getMessage(), 'error' );
            
            wp_send_json_error( array(
                'message' => $e->getMessage(),
                'chunk_index' => $chunk_index
            ) );
        }
    }
    
    /**
     * Initialize bulk export session.
     * 
     * @param array $course_ids Array of course IDs to export.
     * @param array $options    Export options.
     * @return string Session ID.
     */
    public function initialize_bulk_export( $course_ids, $options = array() ) {
        $session_id = 'ld_export_' . wp_generate_uuid4();
        
        // ld_log( 'bulk_export', "Initializing bulk export session {$session_id} - " . count( $course_ids ) . " courses", 'info' );
        
        // Default options
        $defaults = array(
            'include_elementor' => true,
            'include_certificates' => true,
            'include_quiz_questions' => true,
            'preserve_serialized' => true,
            'include_taxonomies' => true,
            'chunk_size' => $this->calculate_chunk_size( $course_ids, $options )
        );
        $options = wp_parse_args( $options, $defaults );
        
        // Analyze content for optimal processing
        $content_analysis = $this->analyze_export_content( $course_ids );
        
        // Create chunks
        $chunks = $this->create_export_chunks( $course_ids, $options['chunk_size'] );
        
        // Session data
        $session_data = array(
            'session_id' => $session_id,
            'course_ids' => $course_ids,
            'options' => $options,
            'total_courses' => count( $course_ids ),
            'total_chunks' => count( $chunks ),
            'content_analysis' => $content_analysis,
            'start_time' => time(),
            'status' => 'initialized',
            'processed_courses' => 0,
            'export_data' => array(
                'export_meta' => array(
                    'version' => '2.0',
                    'plugin_version' => LEARNDASH_EXPORT_IMPORT_VERSION,
                    'timestamp' => current_time( 'mysql' ),
                    'site_url' => get_site_url(),
                    'site_name' => get_bloginfo( 'name' ),
                    'wordpress_version' => get_bloginfo( 'version' ),
                    'learndash_version' => defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : 'unknown',
                    'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'not_installed',
                    'export_options' => $options,
                    'total_courses' => count( $course_ids )
                ),
                'courses' => array(),
                'global_quizzes' => array(),
                'certificates' => array(),
                'groups' => array(),
                'taxonomies' => array(),
                'settings' => array(),
                'assets' => array()
            )
        );
        
        // Save session data
        set_transient( "ld_export_session_{$session_id}", $session_data, HOUR_IN_SECONDS );
        
        // Verify session data was saved
        $verify_data = get_transient( "ld_export_session_{$session_id}" );
        if ( ! $verify_data ) {
            ld_log( 'bulk_export', "Failed to save session data for session {$session_id}", 'error' );
        } else {
            ld_log( 'bulk_export', "Session data saved successfully for session {$session_id}", 'info' );
        }
        
        // Save chunks
        foreach ( $chunks as $index => $chunk ) {
            set_transient( "ld_export_chunk_{$session_id}_{$index}", $chunk, HOUR_IN_SECONDS );
        }
        
        return $session_id;
    }
    
    /**
     * Process a single export chunk.
     * 
     * @param array $chunk_data   Chunk data containing course IDs.
     * @param array $session_data Session configuration.
     * @return array Processing result.
     */
    private function process_export_chunk( $chunk_data, $session_data ) {
        $exporter = new LD_Exporter();
        $options = $session_data['options'];
        
        $result = array(
            'items_processed' => 0,
            'current_item' => '',
            'warnings' => array(),
            'export_data' => array()
        );
        
        foreach ( $chunk_data['course_ids'] as $course_id ) {
            try {
                $course = get_post( $course_id );
                if ( ! $course ) {
                    $result['warnings'][] = "Course not found: ID {$course_id}";
                    continue;
                }
                
                $result['current_item'] = $course->post_title;
                
                // ld_log( 'bulk_export', "Processing course: {$course->post_title} (ID: {$course_id})", 'info' );
                
                // Export comprehensive course data
                $course_data = $exporter->ld_export_get_all_course_data( $course_id, $options );
                $result['export_data'][] = $course_data;
                
                $result['items_processed']++;
                
                // Memory check
                $memory_usage = memory_get_usage();
                $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
                
                if ( $memory_usage > ( $memory_limit * 0.8 ) ) {
                    // ld_log( 'bulk_export', "High memory usage detected: " . round( $memory_usage / 1024 / 1024, 2 ) . "MB", 'warning' );
                    
                    // Force cleanup
                    $this->cleanup_chunk_memory();
                }
                
            } catch ( Exception $e ) {
                $error_msg = "Course export failed - ID: {$course_id}, Error: " . $e->getMessage();
                // ld_log( 'bulk_export', $error_msg, 'error' );
                $result['warnings'][] = $error_msg;
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate optimal chunk size based on content analysis.
     * 
     * @param array $course_ids Course IDs to analyze.
     * @param array $options    Export options.
     * @return int Optimal chunk size.
     */
    private function calculate_chunk_size( $course_ids, $options ) {
        $base_chunk_size = 5; // Conservative default for comprehensive export
        
        // Analyze a sample of courses
        $sample_size = min( 3, count( $course_ids ) );
        $sample_courses = array_slice( $course_ids, 0, $sample_size );
        
        $has_elementor = false;
        $avg_lessons = 0;
        $total_lessons = 0;
        
        foreach ( $sample_courses as $course_id ) {
            // Check for Elementor
            if ( get_post_meta( $course_id, '_elementor_edit_mode', true ) === 'builder' ) {
                $has_elementor = true;
            }
            
            // Count lessons
            $lessons = get_posts( array(
                'post_type' => 'sfwd-lessons',
                'meta_query' => array(
                    array(
                        'key' => 'course_id',
                        'value' => $course_id,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => -1,
                'fields' => 'ids'
            ) );
            
            $total_lessons += count( $lessons );
        }
        
        if ( $sample_size > 0 ) {
            $avg_lessons = $total_lessons / $sample_size;
        }
        
        // Adjust chunk size based on complexity
        if ( $has_elementor ) {
            $base_chunk_size = 2; // Smaller chunks for Elementor content
        }
        
        if ( $avg_lessons > 20 ) {
            $base_chunk_size = 1; // Very small chunks for complex courses
        } elseif ( $avg_lessons > 10 ) {
            $base_chunk_size = max( 1, $base_chunk_size - 2 );
        }
        
        // Memory considerations
        $memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
        if ( $memory_limit < 256 * 1024 * 1024 ) { // Less than 256MB
            $base_chunk_size = max( 1, $base_chunk_size - 1 );
        }
        
        // ld_log( 'bulk_export', "Calculated chunk size: {$base_chunk_size} (Elementor: " . ( $has_elementor ? 'yes' : 'no' ) . ", Avg lessons: {$avg_lessons})", 'info' );
        
        return $base_chunk_size;
    }
    
    /**
     * Analyze export content for optimization.
     * 
     * @param array $course_ids Course IDs to analyze.
     * @return array Content analysis.
     */
    private function analyze_export_content( $course_ids ) {
        $analysis = array(
            'total_courses' => count( $course_ids ),
            'has_elementor_content' => false,
            'total_lessons' => 0,
            'total_topics' => 0,
            'total_quizzes' => 0,
            'estimated_size_mb' => 0
        );
        
        // Sample analysis (first 5 courses for performance)
        $sample_courses = array_slice( $course_ids, 0, min( 5, count( $course_ids ) ) );
        
        foreach ( $sample_courses as $course_id ) {
            // Check Elementor
            if ( get_post_meta( $course_id, '_elementor_edit_mode', true ) === 'builder' ) {
                $analysis['has_elementor_content'] = true;
            }
            
            // Count content
            $lessons = get_posts( array(
                'post_type' => 'sfwd-lessons',
                'meta_query' => array( array( 'key' => 'course_id', 'value' => $course_id ) ),
                'posts_per_page' => -1,
                'fields' => 'ids'
            ) );
            $analysis['total_lessons'] += count( $lessons );
            
            foreach ( $lessons as $lesson_id ) {
                $topics = get_posts( array(
                    'post_type' => 'sfwd-topic',
                    'meta_query' => array( array( 'key' => 'lesson_id', 'value' => $lesson_id ) ),
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                ) );
                $analysis['total_topics'] += count( $topics );
            }
            
            $quizzes = get_posts( array(
                'post_type' => 'sfwd-quiz',
                'meta_query' => array( array( 'key' => 'course_id', 'value' => $course_id ) ),
                'posts_per_page' => -1,
                'fields' => 'ids'
            ) );
            $analysis['total_quizzes'] += count( $quizzes );
        }
        
        // Extrapolate for all courses
        if ( count( $sample_courses ) > 0 ) {
            $multiplier = count( $course_ids ) / count( $sample_courses );
            $analysis['total_lessons'] = round( $analysis['total_lessons'] * $multiplier );
            $analysis['total_topics'] = round( $analysis['total_topics'] * $multiplier );
            $analysis['total_quizzes'] = round( $analysis['total_quizzes'] * $multiplier );
        }
        
        // Estimate size (rough calculation)
        $base_size_per_course = 0.5; // MB
        if ( $analysis['has_elementor_content'] ) {
            $base_size_per_course = 2.0; // MB with Elementor
        }
        
        $analysis['estimated_size_mb'] = round( $analysis['total_courses'] * $base_size_per_course, 2 );
        
        // ld_log( 'bulk_export', 'Content analysis: ' . json_encode( $analysis ), 'info' );
        
        return $analysis;
    }
    
    /**
     * Create export chunks from course IDs.
     * 
     * @param array $course_ids Course IDs.
     * @param int   $chunk_size Chunk size.
     * @return array Chunks.
     */
    private function create_export_chunks( $course_ids, $chunk_size ) {
        // Force single chunk for now to isolate issues
        $chunks = array();
        $total_courses = count( $course_ids );
        
        $chunks[] = array(
            'chunk_index' => 0,
            'course_ids' => $course_ids, // All courses in one chunk
            'start_index' => 0,
            'end_index' => $total_courses - 1
        );
        
        return $chunks;
    }
    
    /**
     * Update session progress.
     * 
     * @param string $session_id   Session ID.
     * @param int    $chunk_index  Completed chunk index.
     * @param array  $chunk_result Chunk processing result.
     */
    private function update_session_progress( $session_id, $chunk_index, $chunk_result ) {
        $session_data = get_transient( "ld_export_session_{$session_id}" );
        if ( ! $session_data ) {
            return;
        }
        
        // Update progress
        $session_data['processed_courses'] += $chunk_result['items_processed'];
        $session_data['last_chunk_completed'] = $chunk_index;
        $session_data['last_update'] = time();
        
        // Merge export data
        if ( ! empty( $chunk_result['export_data'] ) ) {
            $session_data['export_data']['courses'] = array_merge(
                $session_data['export_data']['courses'],
                $chunk_result['export_data']
            );
        }
        
        // Calculate progress percentage
        $progress_percentage = ( $session_data['processed_courses'] / $session_data['total_courses'] ) * 100;
        
        // Save updated session
        set_transient( "ld_export_session_{$session_id}", $session_data, HOUR_IN_SECONDS );
        
        // Save progress for UI
        $progress_data = array(
            'session_id' => $session_id,
            'processed_courses' => $session_data['processed_courses'],
            'total_courses' => $session_data['total_courses'],
            'progress_percentage' => round( $progress_percentage, 2 ),
            'current_chunk' => $chunk_index + 1,
            'total_chunks' => $session_data['total_chunks'],
            'last_update' => $session_data['last_update']
        );
        
        set_transient( "ld_export_progress_{$session_id}", $progress_data, HOUR_IN_SECONDS );
        
        // ld_log( 'bulk_export', "Progress updated - {$session_data['processed_courses']}/{$session_data['total_courses']} courses ({$progress_percentage}%)", 'info' );
    }
    
    /**
     * Generate downloadable export file.
     * 
     * @param string $session_id Session ID.
     * @return array File information.
     */
    public function generate_export_file( $session_id ) {
        ld_log( 'bulk_export', "Generating export file for session {$session_id}", 'info' );
        
        $session_data = get_transient( "ld_export_session_{$session_id}" );
        if ( ! $session_data ) {
            ld_log( 'bulk_export', "Session data not found for session {$session_id}", 'error' );
            throw new Exception( 'Export session not found' );
        }
        
        ld_log( 'bulk_export', "Session data found for session {$session_id}, courses to export: " . count( $session_data['export_data']['courses'] ), 'info' );
        
        // Finalize export data
        $export_data = $session_data['export_data'];
        $export_data['export_meta']['exported_at'] = current_time( 'mysql' );
        $export_data['export_meta']['total_exported'] = count( $export_data['courses'] );
        
        // Create export JSON
        $json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/learndash-exports/';
        
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }
        
        $filename = 'learndash-export-' . date( 'Y-m-d-H-i-s' ) . '.json';
        $file_path = $temp_dir . $filename;
        
        $bytes_written = file_put_contents( $file_path, $json_data );
        
        if ( $bytes_written === false ) {
            throw new Exception( 'Failed to create export file' );
        }
        
        $file_size_mb = round( $bytes_written / 1024 / 1024, 2 );
        
        ld_log( 'bulk_export', "Export file created - {$filename} ({$file_size_mb}MB)", 'info' );
        
        // Don't cleanup session data immediately - wait for successful download
        // $this->cleanup_export_session( $session_id );
        
        return array(
            'filename' => $filename,
            'file_path' => $file_path,
            'file_url' => $upload_dir['baseurl'] . '/learndash-exports/' . $filename,
            'file_size_mb' => $file_size_mb,
            'courses_exported' => count( $export_data['courses'] )
        );
    }
    
    /**
     * Clean up memory after chunk processing.
     */
    private function cleanup_chunk_memory() {
        wp_cache_flush();
        wp_suspend_cache_addition( false );
        
        if ( function_exists( 'gc_collect_cycles' ) ) {
            $collected = gc_collect_cycles();
            // ld_log( 'memory', "Garbage collection freed {$collected} cycles", 'debug' );
        }
        
        $memory_mb = round( memory_get_usage() / 1024 / 1024, 2 );
        // ld_log( 'memory', "Memory usage after cleanup: {$memory_mb}MB", 'debug' );
    }
    
    /**
     * Clean up export session data.
     * 
     * @param string $session_id Session ID.
     */
    private function cleanup_export_session( $session_id ) {
        $session_data = get_transient( "ld_export_session_{$session_id}" );
        
        if ( $session_data ) {
            // Clean up chunks
            for ( $i = 0; $i < $session_data['total_chunks']; $i++ ) {
                delete_transient( "ld_export_chunk_{$session_id}_{$i}" );
            }
        }
        
        // Clean up session and progress
        delete_transient( "ld_export_session_{$session_id}" );
        delete_transient( "ld_export_progress_{$session_id}" );
        
        // ld_log( 'bulk_export', "Session cleanup completed for {$session_id}", 'info' );
    }
    
    /**
     * Get export progress for UI.
     * 
     * @param string $session_id Session ID.
     * @return array|false Progress data or false if not found.
     */
    public function get_export_progress( $session_id ) {
        return get_transient( "ld_export_progress_{$session_id}" );
    }
}
