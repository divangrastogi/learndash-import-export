<?php
/**
 * The importer functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/includes
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class LD_Importer
 */
class LD_Importer {

    /**
     * ID mapping for remapping old IDs to new ones.
     *
     * @var array
     */
    private $id_mapping = array();

    /**
     * Import data.
     *
     * @param array $data Import data.
     * @param array $args Import arguments.
     * @return array Result.
     */
    public function import( $data, $args = array() ) {
        do_action( 'ld_before_import_start', $data );

        $defaults = array(
            'duplicate_handling' => 'create_new', // create_new, skip, overwrite
            'batch_size' => 100,
        );

        $args = wp_parse_args( $args, $defaults );

        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        // Log import start.
        ld_log( 'import', 'Starting import process with ' . count( $data ) . ' data sections' );

        // Import groups first.
        if ( isset( $data['groups'] ) && is_array( $data['groups'] ) ) {
            ld_log( 'import', 'Importing ' . count( $data['groups'] ) . ' groups' );
            foreach ( $data['groups'] as $group_data ) {
                try {
                    $res = $this->import_group( $group_data, $args );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                } catch ( Exception $e ) {
                    $result['errors'][] = 'Group import error: ' . $e->getMessage();
                    ld_log( 'import', 'Group import error: ' . $e->getMessage(), 'error' );
                }
            }
        }

        // Import courses.
        if ( isset( $data['courses'] ) && is_array( $data['courses'] ) ) {
            ld_log( 'import', 'Importing ' . count( $data['courses'] ) . ' courses' );
            foreach ( $data['courses'] as $course_data ) {
                try {
                    $res = $this->import_course( $course_data, $args );
                    ld_log( 'import', 'Course import result: ' . json_encode( $res ), 'debug' );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                } catch ( Exception $e ) {
                    $result['errors'][] = 'Course import error: ' . $e->getMessage();
                    ld_log( 'import', 'Course import error: ' . $e->getMessage(), 'error' );
                }
            }
        }

        // Log final results.
        ld_log( 'import', sprintf( 'Import completed. Imported: %d, Skipped: %d, Errors: %d', 
            $result['imported'], 
            $result['skipped'], 
            count( $result['errors'] ) 
        ) );

        do_action( 'ld_after_import_complete', $result );

        return $result;
    }

    /**
     * Import a course.
     *
     * @param array $course_data Course data.
     * @param array $args        Import args.
     * @return array Result.
     */
    public function import_course( $course_data, $args ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        $post_id = $this->import_post( $course_data, 'sfwd-courses', $args );

        if ( $post_id ) {
            $this->id_mapping[ $course_data['ID'] ?? $course_data['post_data']['ID'] ?? 0 ] = $post_id;
            $result['imported']++;

            // Handle different export formats for lessons
            $lessons_data = array();
            if ( isset( $course_data['lessons'] ) && is_array( $course_data['lessons'] ) ) {
                // New comprehensive export format
                $lessons_data = $course_data['lessons'];
                ld_log( 'import', "Found " . count( $lessons_data ) . " lessons in course data", 'info' );
            } elseif ( isset( $course_data['post_data'] ) && isset( $course_data['lessons'] ) ) {
                // New format with post_data wrapper
                $lessons_data = $course_data['lessons'];
                ld_log( 'import', "Found " . count( $lessons_data ) . " lessons in course data (post_data format)", 'info' );
            } else {
                ld_log( 'import', "No lessons found in course data. Keys present: " . implode( ', ', array_keys( $course_data ) ), 'warning' );
            }

            // Track imported lesson IDs for course builder
            $imported_lesson_ids = array();
            $imported_topic_ids = array();
            $imported_quiz_ids = array();

            // Import lessons.
            if ( ! empty( $lessons_data ) ) {
                foreach ( $lessons_data as $lesson_data ) {
                    $res = $this->import_lesson( $lesson_data, $post_id, $args );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                    
                    // Track the imported lesson ID
                    if ( isset( $res['lesson_id'] ) && $res['lesson_id'] ) {
                        $imported_lesson_ids[] = $res['lesson_id'];
                        if ( isset( $res['topic_ids'] ) ) {
                            $imported_topic_ids = array_merge( $imported_topic_ids, $res['topic_ids'] );
                        }
                        if ( isset( $res['quiz_ids'] ) ) {
                            $imported_quiz_ids = array_merge( $imported_quiz_ids, $res['quiz_ids'] );
                        }
                    }
                }
            }

            // Rebuild LearnDash course structure (CRITICAL for course display)
            $this->rebuild_course_structure( $post_id, $imported_lesson_ids, $imported_topic_ids, $imported_quiz_ids );
            ld_log( 'import', "Rebuilt course structure for course {$post_id} with " . count( $imported_lesson_ids ) . " lessons", 'info' );
            
            // Clear caches AFTER rebuilding (only once, lightweight)
            $this->clear_learndash_caches( $post_id );

            // Handle different export formats for certificates
            $certificate_data = null;
            if ( isset( $course_data['certificates'] ) && is_array( $course_data['certificates'] ) && ! empty( $course_data['certificates'] ) ) {
                // New comprehensive export format
                $certificate_data = $course_data['certificates'][0];
            } elseif ( isset( $course_data['certificate'] ) ) {
                // Old format
                $certificate_data = $course_data['certificate'];
            }

            // Import certificate.
            if ( $certificate_data ) {
                $cert_id = $this->import_certificate( $certificate_data, $args );
                if ( $cert_id ) {
                    update_post_meta( $post_id, '_ld_certificate', $cert_id );
                    $result['imported']++;
                }
            }
        } else {
            $result['skipped']++;
        }

        return $result;
    }

    /**
     * Import a lesson.
     *
     * @param array $lesson_data Lesson data.
     * @param int   $course_id   Course ID.
     * @param array $args        Import args.
     * @return array Result.
     */
    public function import_lesson( $lesson_data, $course_id, $args ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
            'lesson_id' => null,
            'topic_ids' => array(),
            'quiz_ids' => array(),
        );

        // Set course relationship meta based on export format
        if ( isset( $lesson_data['post_meta'] ) ) {
            // New format
            $lesson_data['post_meta']['course_id'] = array( $course_id );
            $lesson_data['post_meta']['ld_course_' . $course_id] = array( $course_id );
        } else {
            // Old format
            if ( ! isset( $lesson_data['meta'] ) ) {
                $lesson_data['meta'] = array();
            }
            $lesson_data['meta']['course_id'] = array( $course_id );
            $lesson_data['meta']['ld_course_' . $course_id] = array( $course_id );
        }

        $post_id = $this->import_post( $lesson_data, 'sfwd-lessons', $args );

        if ( $post_id ) {
            $this->id_mapping[ $lesson_data['ID'] ?? $lesson_data['post_data']['ID'] ?? 0 ] = $post_id;
            $result['imported']++;
            $result['lesson_id'] = $post_id;

            // Handle different export formats for topics
            $topics_data = array();
            if ( isset( $lesson_data['topics'] ) && is_array( $lesson_data['topics'] ) ) {
                $topics_data = $lesson_data['topics'];
            }

            // Import topics.
            if ( ! empty( $topics_data ) ) {
                foreach ( $topics_data as $topic_data ) {
                    $res = $this->import_topic( $topic_data, $course_id, $post_id, $args );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                    if ( isset( $res['topic_id'] ) && $res['topic_id'] ) {
                        $result['topic_ids'][] = $res['topic_id'];
                    }
                }
            }

            // Handle different export formats for lesson quizzes
            $lesson_quizzes_data = array();
            if ( isset( $lesson_data['lesson_quizzes'] ) && is_array( $lesson_data['lesson_quizzes'] ) ) {
                // New comprehensive export format
                $lesson_quizzes_data = $lesson_data['lesson_quizzes'];
            } elseif ( isset( $lesson_data['quizzes'] ) && is_array( $lesson_data['quizzes'] ) ) {
                // Old format
                $lesson_quizzes_data = $lesson_data['quizzes'];
            }

            // Import quizzes.
            if ( ! empty( $lesson_quizzes_data ) ) {
                foreach ( $lesson_quizzes_data as $quiz_data ) {
                    $res = $this->import_quiz( $quiz_data, $course_id, $post_id, null, $args );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                    if ( isset( $res['quiz_id'] ) && $res['quiz_id'] ) {
                        $result['quiz_ids'][] = $res['quiz_id'];
                    }
                }
            }
        } else {
            $result['skipped']++;
        }

        return $result;
    }

    /**
     * Import a topic.
     *
     * @param array $topic_data Topic data.
     * @param int   $course_id  Course ID.
     * @param int   $lesson_id  Lesson ID.
     * @param array $args       Import args.
     * @return array Result.
     */
    public function import_topic( $topic_data, $course_id, $lesson_id, $args ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
            'topic_id' => null,
        );

        // Set course and lesson relationship meta based on export format
        if ( isset( $topic_data['post_meta'] ) ) {
            // New format
            $topic_data['post_meta']['course_id'] = array( $course_id );
            $topic_data['post_meta']['lesson_id'] = array( $lesson_id );
            $topic_data['post_meta']['ld_course_' . $course_id] = array( $course_id );
        } else {
            // Old format
            if ( ! isset( $topic_data['meta'] ) ) {
                $topic_data['meta'] = array();
            }
            $topic_data['meta']['course_id'] = array( $course_id );
            $topic_data['meta']['lesson_id'] = array( $lesson_id );
            $topic_data['meta']['ld_course_' . $course_id] = array( $course_id );
        }

        $post_id = $this->import_post( $topic_data, 'sfwd-topic', $args );

        if ( $post_id ) {
            $old_id = $topic_data['ID'] ?? $topic_data['post_data']['ID'] ?? 0;
            if ( $old_id ) {
                $this->id_mapping[ $old_id ] = $post_id;
            }
            $result['imported']++;
            $result['topic_id'] = $post_id;

            // Import quizzes.
            if ( isset( $topic_data['quizzes'] ) ) {
                foreach ( $topic_data['quizzes'] as $quiz_data ) {
                    $res = $this->import_quiz( $quiz_data, $course_id, $lesson_id, $post_id, $args );
                    $result['imported'] += $res['imported'];
                    $result['skipped'] += $res['skipped'];
                    $result['errors'] = array_merge( $result['errors'], $res['errors'] );
                }
            }
        } else {
            $result['skipped']++;
        }

        return $result;
    }

    /**
     * Import a quiz.
     *
     * @param array $quiz_data Quiz data.
     * @param int   $course_id Course ID.
     * @param int   $lesson_id Lesson ID.
     * @param int   $topic_id  Topic ID.
     * @param array $args      Import args.
     * @return array Result.
     */
    public function import_quiz( $quiz_data, $course_id, $lesson_id, $topic_id, $args ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
            'quiz_id' => null,
        );

        // Set course, lesson, and topic relationship meta based on export format
        if ( isset( $quiz_data['post_meta'] ) ) {
            // New format
            $quiz_data['post_meta']['course_id'] = array( $course_id );
            $quiz_data['post_meta']['lesson_id'] = array( $lesson_id );
            $quiz_data['post_meta']['ld_course_' . $course_id] = array( $course_id );
            if ( $topic_id ) {
                $quiz_data['post_meta']['topic_id'] = array( $topic_id );
            }
        } else {
            // Old format
            if ( ! isset( $quiz_data['meta'] ) ) {
                $quiz_data['meta'] = array();
            }
            $quiz_data['meta']['course_id'] = array( $course_id );
            $quiz_data['meta']['lesson_id'] = array( $lesson_id );
            $quiz_data['meta']['ld_course_' . $course_id] = array( $course_id );
            if ( $topic_id ) {
                $quiz_data['meta']['topic_id'] = array( $topic_id );
            }
        }

        $post_id = $this->import_post( $quiz_data, 'sfwd-quiz', $args );

        if ( $post_id ) {
            $old_id = $quiz_data['ID'] ?? $quiz_data['post_data']['ID'] ?? 0;
            if ( $old_id ) {
                $this->id_mapping[ $old_id ] = $post_id;
            }
            $result['imported']++;
            $result['quiz_id'] = $post_id;

            // Import questions.
            if ( isset( $quiz_data['questions'] ) ) {
                $question_ids = array();
                foreach ( $quiz_data['questions'] as $question_data ) {
                    $q_id = $this->import_question( $question_data, $args );
                    if ( $q_id ) {
                        $question_ids[] = $q_id;
                        $result['imported']++;
                    }
                }
                update_post_meta( $post_id, 'ld_quiz_questions', $question_ids );
            }
        } else {
            $result['skipped']++;
        }

        return $result;
    }

    /**
     * Import a question.
     *
     * @param array $question_data Question data.
     * @param array $args          Import args.
     * @return int|false Post ID.
     */
    public function import_question( $question_data, $args ) {
        return $this->import_post( $question_data, 'sfwd-question', $args );
    }

    /**
     * Import a certificate.
     *
     * @param array $cert_data Certificate data.
     * @param array $args      Import args.
     * @return int|false Post ID.
     */
    private function import_certificate( $cert_data, $args ) {
        return $this->import_post( $cert_data, 'sfwd-certificates', $args );
    }

    /**
     * Import a group.
     *
     * @param array $group_data Group data.
     * @param array $args       Import args.
     * @return array Result.
     */
    public function import_group( $group_data, $args ) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
        );

        $post_id = $this->import_post( $group_data, 'groups', $args );

        if ( $post_id ) {
            $this->id_mapping[ $group_data['ID'] ] = $post_id;
            $result['imported']++;
        } else {
            $result['skipped']++;
        }

        return $result;
    }

    /**
     * Import a post.
     *
     * @param array  $post_data Post data.
     * @param string $post_type Post type.
     * @param array  $args      Import args.
     * @return int|false Post ID.
     */
    private function import_post( $post_data, $post_type, $args ) {
        // Handle both old and new export formats
        $title = '';
        $content = '';
        $excerpt = '';
        $status = 'draft';
        $meta = array();
        
        if ( isset( $post_data['post_data'] ) && is_array( $post_data['post_data'] ) ) {
            // New comprehensive export format
            $post_info = $post_data['post_data'];
            $title = $post_info['post_title'] ?? '';
            $content = $post_info['post_content'] ?? '';
            $excerpt = $post_info['post_excerpt'] ?? '';
            $status = $post_info['post_status'] ?? 'draft';
            $meta = $post_data['post_meta'] ?? array();
        } else {
            // Old export format (legacy support)
            $title = $post_data['title'] ?? '';
            $content = $post_data['content'] ?? '';
            $excerpt = $post_data['excerpt'] ?? '';
            $status = $post_data['status'] ?? 'draft';
            $meta = $post_data['meta'] ?? array();
        }
        
        // Validate required fields.
        if ( empty( $title ) ) {
            ld_log( 'import', "Skipping post with empty title for post type: $post_type", 'warning' );
            return false;
        }

        $query = new WP_Query( array(
            'post_type'      => $post_type,
            'title'          => $title,
            'posts_per_page' => 1,
        ) );
        $existing_post = $query->have_posts() ? $query->posts[0] : null;

        if ( $existing_post && 'skip' === $args['duplicate_handling'] ) {
            ld_log( 'import', "Skipping existing post: {$title} (ID: {$existing_post->ID})" );
            return false;
        }

        $post_args = array(
            'post_title'   => sanitize_text_field( $title ),
            'post_content' => wp_kses_post( $content ),
            'post_excerpt' => sanitize_textarea_field( $excerpt ),
            'post_status'  => in_array( $status, array( 'publish', 'draft', 'private', 'pending' ) ) ? $status : 'draft',
            'post_type'    => $post_type,
        );

        if ( $existing_post && 'overwrite' === $args['duplicate_handling'] ) {
            $post_args['ID'] = $existing_post->ID;
            $post_id = wp_update_post( $post_args );
            ld_log( 'import', "Updated existing post: {$title} (ID: $post_id)" );
        } else {
            $post_id = wp_insert_post( $post_args );
            if ( $post_id && ! is_wp_error( $post_id ) ) {
                ld_log( 'import', "Created new post: {$title} (ID: $post_id)" );
            }
        }

        // Check for errors.
        if ( is_wp_error( $post_id ) ) {
            ld_log( 'import', "Failed to import post '{$title}': " . $post_id->get_error_message(), 'error' );
            return false;
        }

        if ( ! $post_id ) {
            ld_log( 'import', "Failed to import post '{$title}': Unknown error", 'error' );
            return false;
        }

        // Import meta data.
        if ( $post_id && ! empty( $meta ) && is_array( $meta ) ) {
            foreach ( $meta as $key => $values ) {
                if ( is_array( $values ) ) {
                    if ( count( $values ) === 1 ) {
                        update_post_meta( $post_id, $key, $this->normalize_meta_value( $values[0] ) );
                    } else {
                        delete_post_meta( $post_id, $key );
                        foreach ( $values as $value ) {
                            add_post_meta( $post_id, $key, $this->normalize_meta_value( $value ) );
                        }
                    }
                } else {
                    update_post_meta( $post_id, $key, $this->normalize_meta_value( $values ) );
                }
            }
        }

        return $post_id;
    }

    /**
     * Normalize meta value by attempting to decode JSON or unserialize if it's a string representation.
     *
     * @param mixed $value Meta value.
     * @return mixed Normalized value.
     */
    private function normalize_meta_value( $value ) {
        if ( is_string( $value ) ) {
            // Check if this is our serialized format from export
            if ( strpos( $value, 'serialized:' ) === 0 ) {
                $serialized_data = substr( $value, 11 ); // Remove 'serialized:' prefix
                $unserialized = @unserialize( $serialized_data );
                if ( $unserialized !== false || $serialized_data === 'b:0;' ) {
                    ld_log( 'import', "Unserialized meta data", 'debug' );
                    return $unserialized;
                } else {
                    ld_log( 'import', "Failed to unserialize meta data: " . $serialized_data, 'error' );
                    return $value; // Return original if unserialization fails
                }
            }
            
            // Try to decode JSON.
            $json_decoded = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $json_decoded;
            }

            // Try to unserialize regular serialized data.
            $unserialized = @unserialize( $value );
            if ( $unserialized !== false || $value === 'b:0;' ) {
                return $unserialized;
            }
        }

        return $value;
    }

    /**
     * Rebuild LearnDash course structure (ld_course_steps) for proper display.
     * This is CRITICAL - without this meta, courses won't display lessons/topics properly.
     *
     * @param int   $course_id Course ID.
     * @param array $lesson_ids Array of lesson IDs.
     * @param array $topic_ids Array of topic IDs.
     * @param array $quiz_ids Array of quiz IDs.
     * @return void
     */
    private function rebuild_course_structure( $course_id, $lesson_ids, $topic_ids, $quiz_ids ) {
        if ( empty( $lesson_ids ) ) {
            ld_log( 'import', "No lessons to add to course structure for course {$course_id}", 'warning' );
            return;
        }

        // Build the course steps structure - LearnDash expects specific format
        $course_steps = array(
            'h' => array(
                'sfwd-lessons' => array(),
                'sfwd-topic' => array(),
                'sfwd-quiz' => array(),
            ),
            'steps' => array(),
        );

        // Add lessons with proper structure
        foreach ( $lesson_ids as $lesson_id ) {
            $lesson_id = absint( $lesson_id );
            if ( ! $lesson_id ) {
                continue;
            }
            
            // Add lesson ID to hierarchy
            $course_steps['h']['sfwd-lessons'][] = $lesson_id;
            
            // Get topics for this lesson
            $lesson_topics = array();
            $lesson_quizzes = array();
            
            foreach ( $topic_ids as $topic_id ) {
                $topic_id = absint( $topic_id );
                if ( ! $topic_id ) {
                    continue;
                }
                $topic_lesson_id = absint( get_post_meta( $topic_id, 'lesson_id', true ) );
                if ( $topic_lesson_id == $lesson_id ) {
                    $lesson_topics[] = $topic_id;
                    $course_steps['h']['sfwd-topic'][] = $topic_id;
                }
            }
            
            // Get quizzes for this lesson
            foreach ( $quiz_ids as $quiz_id ) {
                $quiz_id = absint( $quiz_id );
                if ( ! $quiz_id ) {
                    continue;
                }
                $quiz_lesson_id = absint( get_post_meta( $quiz_id, 'lesson_id', true ) );
                $quiz_topic_id = absint( get_post_meta( $quiz_id, 'topic_id', true ) );
                
                // Add to lesson if associated and not with a topic
                if ( $quiz_lesson_id == $lesson_id && empty( $quiz_topic_id ) ) {
                    $lesson_quizzes[] = $quiz_id;
                }
            }
            
            // Store lesson structure
            $lesson_steps = array();
            if ( ! empty( $lesson_topics ) ) {
                $lesson_steps['sfwd-topic'] = $lesson_topics;
            }
            if ( ! empty( $lesson_quizzes ) ) {
                $lesson_steps['sfwd-quiz'] = $lesson_quizzes;
            }
            
            if ( ! empty( $lesson_steps ) ) {
                $course_steps['steps'][ 'sfwd-lessons_' . $lesson_id ] = $lesson_steps;
            }
        }

        // Add global quizzes (quizzes not associated with lessons/topics)
        foreach ( $quiz_ids as $quiz_id ) {
            $quiz_id = absint( $quiz_id );
            if ( ! $quiz_id ) {
                continue;
            }
            $quiz_lesson_id = absint( get_post_meta( $quiz_id, 'lesson_id', true ) );
            $quiz_topic_id = absint( get_post_meta( $quiz_id, 'topic_id', true ) );
            
            // Only add to global if not associated with lesson or topic
            if ( empty( $quiz_lesson_id ) && empty( $quiz_topic_id ) ) {
                $course_steps['h']['sfwd-quiz'][] = $quiz_id;
            }
        }

        // Update the course steps meta
        update_post_meta( $course_id, 'ld_course_steps', $course_steps );
        
        // Log the structure for debugging
        ld_log( 'import', "Course steps structure: " . print_r( $course_steps, true ), 'debug' );
        
        // Update LearnDash course builder data
        if ( function_exists( 'learndash_course_builder_update_course' ) ) {
            learndash_course_builder_update_course( $course_id );
        }
        
        // Force refresh of LearnDash course steps
        if ( function_exists( 'learndash_course_steps' ) ) {
            learndash_course_steps( $course_id, true ); // Force refresh
        }
        
        ld_log( 'import', "Course structure rebuilt for course {$course_id}: " . count( $lesson_ids ) . " lessons, " . count( $topic_ids ) . " topics, " . count( $quiz_ids ) . " quizzes", 'info' );
    }
    
    /**
     * Clear all LearnDash caches for a specific course.
     * This prevents stale cached data from causing type mismatches.
     *
     * @param int $course_id Course ID.
     * @return void
     */
    private function clear_learndash_caches( $course_id ) {
        // Only clear once per course to prevent memory issues
        static $cleared = array();
        if ( isset( $cleared[ $course_id ] ) ) {
            return;
        }
        $cleared[ $course_id ] = true;
        
        ld_log( 'import', "Clearing caches for course {$course_id}", 'debug' );
        
        // Clear WordPress object cache (lightweight)
        wp_cache_delete( $course_id, 'posts' );
        wp_cache_delete( $course_id, 'post_meta' );
        wp_cache_delete( 'ld_course_steps_' . $course_id, 'learndash' );
        wp_cache_delete( 'course_lessons_' . $course_id, 'learndash' );
        wp_cache_delete( 'course_steps_' . $course_id, 'learndash' );
        wp_cache_delete( 'learndash_course_' . $course_id, 'learndash' );
        
        // Clear LearnDash transients (lightweight)
        delete_transient( 'learndash_course_steps_' . $course_id );
        delete_transient( 'ld_course_' . $course_id );
        delete_transient( 'learndash_course_' . $course_id );
        
        ld_log( 'import', "Caches cleared for course {$course_id}", 'debug' );
    }
}