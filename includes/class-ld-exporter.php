<?php
/**
 * The exporter functionality of the plugin.
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
 * Class LD_Exporter
 */
class LD_Exporter {

    /**
     * Post types to export.
     *
     * @var array
     */
    private $post_types = array(
        'sfwd-courses',
        'sfwd-lessons',
        'sfwd-topic',
        'sfwd-quiz',
        'sfwd-question',
        'sfwd-certificates',
        'groups',
    );

    /**
     * Export data in batches.
     *
     * @param array $args Export arguments.
     * @return array Exported data.
     */
    public function export( $args = array() ) {
        do_action( 'ld_before_export_start', $args );

        $defaults = array(
            'batch_size' => 100,
            'offset'     => 0,
            'include_progress' => false,
            'include_attempts' => false,
        );

        $args = wp_parse_args( $args, $defaults );

        $data = array(
            'courses' => array(),
            'groups'  => array(),
            'version' => LEARNDASH_EXPORT_IMPORT_VERSION,
            'exported_at' => current_time( 'mysql' ),
        );

        // Export courses with hierarchy.
        $courses = $this->get_posts_batch( 'sfwd-courses', $args['batch_size'], $args['offset'] );

        ld_log( 'export', 'Found ' . count( $courses ) . ' courses to export.' );

        if ( is_array( $courses ) ) {
            foreach ( $courses as $course ) {
                $course_data = $this->export_course( $course->ID, $args );
                $data['courses'][] = $course_data;
            }
        }

        // Export groups.
        $groups = $this->get_posts_batch( 'groups', $args['batch_size'], $args['offset'] );

        if ( is_array( $groups ) ) {
            foreach ( $groups as $group ) {
                $group_data = $this->export_group( $group->ID, $args );
                $data['groups'][] = $group_data;
            }
        }

        $data = apply_filters( 'ld_export_data', $data );

        do_action( 'ld_after_export_complete', $data );

        return $data;
    }

    /**
     * Get comprehensive course data including Elementor content and serialized data preservation.
     *
     * @param int   $course_id Course ID to export.
     * @param array $options   Export options.
     * @return array Complete course data structure.
     * @since 2.0.0
     */
    public function ld_export_get_all_course_data( $course_id, $options = array() ) {
        ld_log( 'export', "=== Starting comprehensive course export - ID: {$course_id} ===", 'info' );
        
        $start_time = microtime( true );
        
        try {
            // Get course post
            $course = get_post( $course_id );
            if ( ! $course || $course->post_type !== 'sfwd-courses' ) {
                throw new Exception( "Course not found or invalid type - ID: {$course_id}" );
            }
            
            ld_log( 'export', "Course found - Title: '{$course->post_title}'", 'info' );
            
            // Default options
            $defaults = array(
                'include_elementor' => true,
                'include_certificates' => true,
                'include_quiz_questions' => true,
                'preserve_serialized' => true,
                'include_taxonomies' => true
            );
            $options = wp_parse_args( $options, $defaults );
            
            // Build comprehensive course data structure
            $course_data = array(
                'post_data' => $this->export_post_data( $course ),
                'post_meta' => $this->export_post_meta_preserved( $course_id ),
                'elementor_data' => array(),
                'lessons' => array(),
                'global_course_quizzes' => array(),
                'certificates' => array(),
                'taxonomies' => array()
            );
            
            // Export Elementor data if requested
            if ( $options['include_elementor'] ) {
                $course_data['elementor_data'] = $this->ld_export_get_elementor_data( $course_id );
            }
            
            // Export lessons with full hierarchy
            $lessons = $this->get_course_lessons( $course_id );
            ld_log( 'export', "Found {" . count( $lessons ) . "} lessons for course {$course_id}", 'info' );
            
            foreach ( $lessons as $lesson_id ) {
                $lesson_data = $this->export_lesson_comprehensive( $lesson_id, $options );
                $course_data['lessons'][] = $lesson_data;
            }
            
            // Export global course quizzes
            $global_quizzes = $this->get_course_global_quizzes( $course_id );
            foreach ( $global_quizzes as $quiz_id ) {
                $quiz_data = $this->export_quiz_comprehensive( $quiz_id, $options );
                $course_data['global_course_quizzes'][] = $quiz_data;
            }
            
            // Export certificates if requested
            if ( $options['include_certificates'] ) {
                $certificate_id = get_post_meta( $course_id, '_ld_certificate', true );
                if ( $certificate_id ) {
                    ld_log( 'export', "Exporting certificate - ID: {$certificate_id}", 'info' );
                    $course_data['certificates'][] = $this->export_certificate_comprehensive( $certificate_id, $options );
                }
            }
            
            // Export taxonomies if requested
            if ( $options['include_taxonomies'] ) {
                $course_data['taxonomies'] = $this->export_post_taxonomies( $course_id );
            }
            
            $duration = round( microtime( true ) - $start_time, 2 );
            ld_log( 'export', "=== Course export completed - ID: {$course_id}, Duration: {$duration}s ===", 'info' );
            
            return $course_data;
            
        } catch ( Exception $e ) {
            ld_log( 'export', "Course export failed - ID: {$course_id}, Error: " . $e->getMessage(), 'error' );
            throw $e;
        }
    }
    
    /**
     * Export a single course with its hierarchy (legacy method - enhanced).
     *
     * @param int   $course_id Course ID.
     * @param array $args      Export args.
     * @return array Course data.
     */
    private function export_course( $course_id, $args ) {
        $course = get_post( $course_id );

        $course_data = array(
            'ID' => $course->ID,
            'title' => $course->post_title,
            'content' => $course->post_content,
            'excerpt' => $course->post_excerpt,
            'status' => $course->post_status,
            'meta' => get_post_meta( $course_id ),
            'lessons' => array(),
            'certificate' => null,
        );

        // Get lessons.
        if ( function_exists( 'learndash_get_course_lessons_list' ) ) {
            $lessons = learndash_get_course_lessons_list( $course_id );
            if ( is_array( $lessons ) ) {
                ld_log( 'export', 'Course ' . $course_id . ' has ' . count( $lessons ) . ' lessons via LD function.' );
                foreach ( $lessons as $lesson ) {
                    if ( isset( $lesson['post'] ) ) {
                        $lesson_data = $this->export_lesson( $lesson['post']->ID, $args );
                        $course_data['lessons'][] = $lesson_data;
                    }
                }
            }
        } else {
            $lesson_posts = get_posts( array(
                'post_type' => 'sfwd-lessons',
                'meta_query' => array(
                    array(
                        'key' => 'course_id',
                        'value' => $course_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
            ) );
            ld_log( 'export', 'Course ' . $course_id . ' has ' . count( $lesson_posts ) . ' lessons via get_posts.' );
            foreach ( $lesson_posts as $lesson_post ) {
                $lesson_data = $this->export_lesson( $lesson_post->ID, $args );
                $course_data['lessons'][] = $lesson_data;
            }
        }

        // Get certificate.
        $certificate_id = get_post_meta( $course_id, '_ld_certificate', true );
        if ( $certificate_id ) {
            $course_data['certificate'] = $this->export_certificate( $certificate_id );
        }

        return $course_data;
    }

    /**
     * Export a lesson with topics and quizzes.
     *
     * @param int   $lesson_id Lesson ID.
     * @param array $args      Export args.
     * @return array Lesson data.
     */
    private function export_lesson( $lesson_id, $args ) {
        $lesson = get_post( $lesson_id );

        $lesson_data = array(
            'ID' => $lesson->ID,
            'title' => $lesson->post_title,
            'content' => $lesson->post_content,
            'excerpt' => $lesson->post_excerpt,
            'status' => $lesson->post_status,
            'meta' => get_post_meta( $lesson_id ),
            'topics' => array(),
            'quizzes' => array(),
        );

        // Get topics.
        if ( function_exists( 'learndash_get_lesson_topics_list' ) ) {
            $topics = learndash_get_lesson_topics_list( $lesson_id );
            foreach ( $topics as $topic ) {
                if ( isset( $topic['post'] ) ) {
                    $topic_data = $this->export_topic( $topic['post']->ID, $args );
                    $lesson_data['topics'][] = $topic_data;
                }
            }
        } else {
            $topic_posts = get_posts( array(
                'post_type' => 'sfwd-topic',
                'meta_query' => array(
                    array(
                        'key' => 'lesson_id',
                        'value' => $lesson_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
            ) );
            foreach ( $topic_posts as $topic_post ) {
                $topic_data = $this->export_topic( $topic_post->ID, $args );
                $lesson_data['topics'][] = $topic_data;
            }
        }

        // Get quizzes.
        if ( function_exists( 'learndash_get_lesson_quiz_list' ) ) {
            $quizzes = learndash_get_lesson_quiz_list( $lesson_id );
            foreach ( $quizzes as $quiz ) {
                if ( isset( $quiz['post'] ) ) {
                    $quiz_data = $this->export_quiz( $quiz['post']->ID, $args );
                    $lesson_data['quizzes'][] = $quiz_data;
                }
            }
        } else {
            $quiz_posts = get_posts( array(
                'post_type' => 'sfwd-quiz',
                'meta_query' => array(
                    array(
                        'key' => 'lesson_id',
                        'value' => $lesson_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
            ) );
            foreach ( $quiz_posts as $quiz_post ) {
                $quiz_data = $this->export_quiz( $quiz_post->ID, $args );
                $lesson_data['quizzes'][] = $quiz_data;
            }
        }

        return $lesson_data;
    }

    /**
     * Export a topic with quizzes.
     *
     * @param int   $topic_id Topic ID.
     * @param array $args     Export args.
     * @return array Topic data.
     */
    private function export_topic( $topic_id, $args ) {
        $topic = get_post( $topic_id );

        $topic_data = array(
            'ID' => $topic->ID,
            'title' => $topic->post_title,
            'content' => $topic->post_content,
            'excerpt' => $topic->post_excerpt,
            'status' => $topic->post_status,
            'meta' => get_post_meta( $topic_id ),
            'quizzes' => array(),
        );

        // Get quizzes.
        if ( function_exists( 'learndash_get_topic_quiz_list' ) ) {
            $quizzes = learndash_get_topic_quiz_list( $topic_id );
            foreach ( $quizzes as $quiz ) {
                if ( isset( $quiz['post'] ) ) {
                    $quiz_data = $this->export_quiz( $quiz['post']->ID, $args );
                    $topic_data['quizzes'][] = $quiz_data;
                }
            }
        } else {
            $quiz_posts = get_posts( array(
                'post_type' => 'sfwd-quiz',
                'meta_query' => array(
                    array(
                        'key' => 'topic_id',
                        'value' => $topic_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
            ) );
            foreach ( $quiz_posts as $quiz_post ) {
                $quiz_data = $this->export_quiz( $quiz_post->ID, $args );
                $topic_data['quizzes'][] = $quiz_data;
            }
        }

        return $topic_data;
    }

    /**
     * Export a quiz with questions.
     *
     * @param int   $quiz_id Quiz ID.
     * @param array $args    Export args.
     * @return array Quiz data.
     */
    private function export_quiz( $quiz_id, $args ) {
        $quiz = get_post( $quiz_id );

        $quiz_data = array(
            'ID' => $quiz->ID,
            'title' => $quiz->post_title,
            'content' => $quiz->post_content,
            'excerpt' => $quiz->post_excerpt,
            'status' => $quiz->post_status,
            'meta' => get_post_meta( $quiz_id ),
            'questions' => array(),
        );

        // Get questions.
        $question_ids = get_post_meta( $quiz_id, 'ld_quiz_questions', true );

        if ( is_array( $question_ids ) ) {
            foreach ( $question_ids as $question_id ) {
                $question_data = $this->export_question( $question_id );
                $quiz_data['questions'][] = $question_data;
            }
        }

        return $quiz_data;
    }

    /**
     * Export a question.
     *
     * @param int $question_id Question ID.
     * @return array Question data.
     */
    private function export_question( $question_id ) {
        $question = get_post( $question_id );

        return array(
            'ID' => $question->ID,
            'title' => $question->post_title,
            'content' => $question->post_content,
            'excerpt' => $question->post_excerpt,
            'status' => $question->post_status,
            'meta' => get_post_meta( $question_id ),
        );
    }

    /**
     * Export a certificate.
     *
     * @param int $certificate_id Certificate ID.
     * @return array Certificate data.
     */
    private function export_certificate( $certificate_id ) {
        $certificate = get_post( $certificate_id );

        return array(
            'ID' => $certificate->ID,
            'title' => $certificate->post_title,
            'content' => $certificate->post_content,
            'excerpt' => $certificate->post_excerpt,
            'status' => $certificate->post_status,
            'meta' => get_post_meta( $certificate_id ),
        );
    }

    /**
     * Export a group.
     *
     * @param int   $group_id Group ID.
     * @param array $args     Export args.
     * @return array Group data.
     */
    private function export_group( $group_id, $args ) {
        $group = get_post( $group_id );

        return array(
            'ID' => $group->ID,
            'title' => $group->post_title,
            'content' => $group->post_content,
            'excerpt' => $group->post_excerpt,
            'status' => $group->post_status,
            'meta' => get_post_meta( $group_id ),
        );
    }

    /**
     * Get posts in batch.
     *
     * @param string $post_type Post type.
     * @param int    $limit     Limit.
     * @param int    $offset    Offset.
     * @return array Posts.
     */
    private function get_posts_batch( $post_type, $limit, $offset ) {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'offset'         => $offset,
            'post_status'    => 'any',
        );

        return get_posts( $args );
    }
    
    /**
     * Export Elementor data with JSON validation and reference tracking.
     *
     * @param int $post_id Post ID to export Elementor data from.
     * @return array Elementor data structure.
     * @since 2.0.0
     */
    public function ld_export_get_elementor_data( $post_id ) {
        ld_log( 'elementor', "Starting Elementor data export - Post ID: {$post_id}", 'info' );
        
        $elementor_data = array(
            'has_elementor' => false,
            'elementor_data' => array(),
            'page_settings' => array(),
            'css_data' => '',
            'assets_used' => array(),
            'widgets_used' => array()
        );
        
        // Check if post has Elementor data
        $edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
        if ( $edit_mode !== 'builder' ) {
            ld_log( 'elementor', "No Elementor data found - Post ID: {$post_id}", 'debug' );
            return $elementor_data;
        }
        
        $elementor_data['has_elementor'] = true;
        
        // Get main Elementor data
        $raw_elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! empty( $raw_elementor_data ) ) {
            // Validate JSON
            if ( is_string( $raw_elementor_data ) ) {
                $parsed_data = json_decode( $raw_elementor_data, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $elementor_data['elementor_data'] = $parsed_data;
                    $data_size = round( strlen( $raw_elementor_data ) / 1024, 2 );
                    ld_log( 'elementor', "Elementor JSON parsed - Size: {$data_size}KB", 'info' );
                    
                    // Extract widgets and assets
                    $this->extract_elementor_assets( $parsed_data, $elementor_data );
                } else {
                    ld_log( 'elementor', "Elementor JSON parsing failed - Post: {$post_id}, Error: " . json_last_error_msg(), 'error' );
                }
            } else {
                $elementor_data['elementor_data'] = $raw_elementor_data;
                ld_log( 'elementor', "Elementor data is not JSON string - Post: {$post_id}", 'debug' );
            }
        }
        
        // Get page settings
        $page_settings = get_post_meta( $post_id, '_elementor_page_settings', true );
        if ( ! empty( $page_settings ) ) {
            $elementor_data['page_settings'] = $page_settings;
            ld_log( 'elementor', "Page settings exported - Post: {$post_id}", 'debug' );
        }
        
        // Get CSS data
        $css_data = get_post_meta( $post_id, '_elementor_css', true );
        if ( ! empty( $css_data ) ) {
            $elementor_data['css_data'] = $css_data;
            ld_log( 'elementor', "CSS data exported - Post: {$post_id}", 'debug' );
        }
        
        ld_log( 'elementor', "Elementor export completed - Post: {$post_id}, Widgets: " . count( $elementor_data['widgets_used'] ), 'info' );
        
        return $elementor_data;
    }
    
    /**
     * Preserve serialized data with proper detection and flagging.
     *
     * @param mixed $meta_value Meta value to check and preserve.
     * @return mixed Preserved meta value with serialization flag if needed.
     * @since 2.0.0
     */
    public function ld_export_preserve_serialized( $meta_value ) {
        if ( is_serialized( $meta_value ) ) {
            ld_log( 'serialization', "Serialized data detected and preserved", 'debug' );
            return 'serialized:' . $meta_value;
        }
        
        return $meta_value;
    }
    
    /**
     * Export post data in standardized format.
     *
     * @param WP_Post $post Post object.
     * @return array Post data.
     */
    private function export_post_data( $post ) {
        return array(
            'ID' => $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post->post_status,
            'post_type' => $post->post_type,
            'post_author' => $post->post_author,
            'post_date' => $post->post_date,
            'post_date_gmt' => $post->post_date_gmt,
            'post_modified' => $post->post_modified,
            'post_modified_gmt' => $post->post_modified_gmt,
            'post_name' => $post->post_name,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status
        );
    }
    
    /**
     * Export post meta with serialization preservation.
     *
     * @param int $post_id Post ID.
     * @return array Post meta with preserved serialization.
     */
    private function export_post_meta_preserved( $post_id ) {
        $meta_data = get_post_meta( $post_id );
        $preserved_meta = array();
        
        $serialized_count = 0;
        foreach ( $meta_data as $key => $values ) {
            // WordPress stores meta as arrays, get first value
            $value = is_array( $values ) && count( $values ) > 0 ? $values[0] : $values;
            
            // Preserve serialization
            $preserved_value = $this->ld_export_preserve_serialized( $value );
            $preserved_meta[ $key ] = $preserved_value;
            
            if ( is_serialized( $value ) ) {
                $serialized_count++;
                ld_log( 'serialization', "Serialized meta preserved - Key: {$key}", 'debug' );
            }
        }
        
        if ( $serialized_count > 0 ) {
            ld_log( 'export', "Preserved {$serialized_count} serialized meta fields for post {$post_id}", 'info' );
        }
        
        return $preserved_meta;
    }
    
    /**
     * Get course lessons in proper order.
     *
     * @param int $course_id Course ID.
     * @return array Lesson IDs.
     */
    private function get_course_lessons( $course_id ) {
        if ( function_exists( 'learndash_get_course_lessons_list' ) ) {
            $lessons = learndash_get_course_lessons_list( $course_id );
            $lesson_ids = array();
            
            if ( is_array( $lessons ) ) {
                foreach ( $lessons as $lesson ) {
                    if ( isset( $lesson['post'] ) && isset( $lesson['post']->ID ) ) {
                        $lesson_ids[] = $lesson['post']->ID;
                    }
                }
            }
            
            return $lesson_ids;
        }
        
        // Fallback method
        $lesson_posts = get_posts( array(
            'post_type' => 'sfwd-lessons',
            'meta_query' => array(
                array(
                    'key' => 'course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ) );
        
        return wp_list_pluck( $lesson_posts, 'ID' );
    }
    
    /**
     * Extract assets and widgets from Elementor data.
     *
     * @param array $elementor_data Parsed Elementor data.
     * @param array &$export_data   Export data to populate.
     */
    private function extract_elementor_assets( $elementor_data, &$export_data ) {
        if ( ! is_array( $elementor_data ) ) {
            return;
        }
        
        $this->traverse_elementor_elements( $elementor_data, $export_data );
    }
    
    /**
     * Recursively traverse Elementor elements to extract assets and widgets.
     *
     * @param array $elements     Elementor elements.
     * @param array &$export_data Export data to populate.
     */
    private function traverse_elementor_elements( $elements, &$export_data ) {
        foreach ( $elements as $element ) {
            // Track widget types
            if ( isset( $element['widgetType'] ) ) {
                if ( ! in_array( $element['widgetType'], $export_data['widgets_used'] ) ) {
                    $export_data['widgets_used'][] = $element['widgetType'];
                }
            }
            
            // Extract image assets from settings
            if ( isset( $element['settings'] ) ) {
                $this->extract_image_assets( $element['settings'], $export_data );
            }
            
            // Recursively process nested elements
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $this->traverse_elementor_elements( $element['elements'], $export_data );
            }
        }
    }
    
    /**
     * Extract image assets from element settings.
     *
     * @param array $settings     Element settings.
     * @param array &$export_data Export data to populate.
     */
    private function extract_image_assets( $settings, &$export_data ) {
        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) && isset( $value['id'] ) && isset( $value['url'] ) ) {
                // This looks like an image object
                $asset = array(
                    'type' => 'image',
                    'id' => $value['id'],
                    'url' => $value['url'],
                    'alt' => isset( $value['alt'] ) ? $value['alt'] : '',
                    'setting_key' => $key
                );
                
                $export_data['assets_used'][] = $asset;
            }
        }
    }
    
    /**
     * Get global course quizzes (not associated with lessons/topics).
     *
     * @param int $course_id Course ID.
     * @return array Quiz IDs.
     */
    private function get_course_global_quizzes( $course_id ) {
        $quiz_posts = get_posts( array(
            'post_type' => 'sfwd-quiz',
            'meta_query' => array(
                array(
                    'key' => 'course_id',
                    'value' => $course_id,
                    'compare' => '=',
                ),
                array(
                    'key' => 'lesson_id',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'posts_per_page' => -1,
        ) );
        
        return wp_list_pluck( $quiz_posts, 'ID' );
    }
    
    /**
     * Export lesson with comprehensive data.
     *
     * @param int   $lesson_id Lesson ID.
     * @param array $options   Export options.
     * @return array Lesson data.
     */
    private function export_lesson_comprehensive( $lesson_id, $options ) {
        $lesson = get_post( $lesson_id );
        
        $lesson_data = array(
            'post_data' => $this->export_post_data( $lesson ),
            'post_meta' => $this->export_post_meta_preserved( $lesson_id ),
            'elementor_data' => array(),
            'topics' => array(),
            'lesson_quizzes' => array(),
            'taxonomies' => array()
        );
        
        // Export Elementor data if requested
        if ( $options['include_elementor'] ) {
            $lesson_data['elementor_data'] = $this->ld_export_get_elementor_data( $lesson_id );
        }
        
        // Export topics
        $topics = $this->get_lesson_topics( $lesson_id );
        foreach ( $topics as $topic_id ) {
            $topic_data = $this->export_topic_comprehensive( $topic_id, $options );
            $lesson_data['topics'][] = $topic_data;
        }
        
        // Export lesson quizzes
        $lesson_quizzes = $this->get_lesson_quizzes( $lesson_id );
        foreach ( $lesson_quizzes as $quiz_id ) {
            $quiz_data = $this->export_quiz_comprehensive( $quiz_id, $options );
            $lesson_data['lesson_quizzes'][] = $quiz_data;
        }
        
        // Export taxonomies if requested
        if ( $options['include_taxonomies'] ) {
            $lesson_data['taxonomies'] = $this->export_post_taxonomies( $lesson_id );
        }
        
        return $lesson_data;
    }
    
    /**
     * Get lesson topics.
     *
     * @param int $lesson_id Lesson ID.
     * @return array Topic IDs.
     */
    private function get_lesson_topics( $lesson_id ) {
        if ( function_exists( 'learndash_get_lesson_topics_list' ) ) {
            $topics = learndash_get_lesson_topics_list( $lesson_id );
            $topic_ids = array();
            
            if ( is_array( $topics ) ) {
                foreach ( $topics as $topic ) {
                    if ( isset( $topic['post'] ) && isset( $topic['post']->ID ) ) {
                        $topic_ids[] = $topic['post']->ID;
                    }
                }
            }
            
            return $topic_ids;
        }
        
        // Fallback method
        $topic_posts = get_posts( array(
            'post_type' => 'sfwd-topic',
            'meta_query' => array(
                array(
                    'key' => 'lesson_id',
                    'value' => $lesson_id,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ) );
        
        return wp_list_pluck( $topic_posts, 'ID' );
    }
    
    /**
     * Get lesson quizzes.
     *
     * @param int $lesson_id Lesson ID.
     * @return array Quiz IDs.
     */
    private function get_lesson_quizzes( $lesson_id ) {
        if ( function_exists( 'learndash_get_lesson_quiz_list' ) ) {
            $quizzes = learndash_get_lesson_quiz_list( $lesson_id );
            $quiz_ids = array();
            
            if ( is_array( $quizzes ) ) {
                foreach ( $quizzes as $quiz ) {
                    if ( isset( $quiz['post'] ) && isset( $quiz['post']->ID ) ) {
                        $quiz_ids[] = $quiz['post']->ID;
                    }
                }
            }
            
            return $quiz_ids;
        }
        
        // Fallback method
        $quiz_posts = get_posts( array(
            'post_type' => 'sfwd-quiz',
            'meta_query' => array(
                array(
                    'key' => 'lesson_id',
                    'value' => $lesson_id,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
        ) );
        
        return wp_list_pluck( $quiz_posts, 'ID' );
    }
    
    /**
     * Export topic with comprehensive data.
     *
     * @param int   $topic_id Topic ID.
     * @param array $options  Export options.
     * @return array Topic data.
     */
    private function export_topic_comprehensive( $topic_id, $options ) {
        $topic = get_post( $topic_id );
        
        $topic_data = array(
            'post_data' => $this->export_post_data( $topic ),
            'post_meta' => $this->export_post_meta_preserved( $topic_id ),
            'elementor_data' => array(),
            'topic_quizzes' => array(),
            'taxonomies' => array()
        );
        
        // Export Elementor data if requested
        if ( $options['include_elementor'] ) {
            $topic_data['elementor_data'] = $this->ld_export_get_elementor_data( $topic_id );
        }
        
        // Export topic quizzes
        $topic_quizzes = $this->get_topic_quizzes( $topic_id );
        foreach ( $topic_quizzes as $quiz_id ) {
            $quiz_data = $this->export_quiz_comprehensive( $quiz_id, $options );
            $topic_data['topic_quizzes'][] = $quiz_data;
        }
        
        // Export taxonomies if requested
        if ( $options['include_taxonomies'] ) {
            $topic_data['taxonomies'] = $this->export_post_taxonomies( $topic_id );
        }
        
        return $topic_data;
    }
    
    /**
     * Get topic quizzes.
     *
     * @param int $topic_id Topic ID.
     * @return array Quiz IDs.
     */
    private function get_topic_quizzes( $topic_id ) {
        if ( function_exists( 'learndash_get_topic_quiz_list' ) ) {
            $quizzes = learndash_get_topic_quiz_list( $topic_id );
            $quiz_ids = array();
            
            if ( is_array( $quizzes ) ) {
                foreach ( $quizzes as $quiz ) {
                    if ( isset( $quiz['post'] ) && isset( $quiz['post']->ID ) ) {
                        $quiz_ids[] = $quiz['post']->ID;
                    }
                }
            }
            
            return $quiz_ids;
        }
        
        // Fallback method
        $quiz_posts = get_posts( array(
            'post_type' => 'sfwd-quiz',
            'meta_query' => array(
                array(
                    'key' => 'topic_id',
                    'value' => $topic_id,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
        ) );
        
        return wp_list_pluck( $quiz_posts, 'ID' );
    }
    
    /**
     * Export quiz with comprehensive data.
     *
     * @param int   $quiz_id Quiz ID.
     * @param array $options Export options.
     * @return array Quiz data.
     */
    private function export_quiz_comprehensive( $quiz_id, $options ) {
        $quiz = get_post( $quiz_id );
        
        $quiz_data = array(
            'post_data' => $this->export_post_data( $quiz ),
            'post_meta' => $this->export_post_meta_preserved( $quiz_id ),
            'elementor_data' => array(),
            'questions' => array(),
            'pro_quiz_data' => array(),
            'taxonomies' => array()
        );
        
        // Export Elementor data if requested
        if ( $options['include_elementor'] ) {
            $quiz_data['elementor_data'] = $this->ld_export_get_elementor_data( $quiz_id );
        }
        
        // Export questions if requested
        if ( $options['include_quiz_questions'] ) {
            $question_ids = get_post_meta( $quiz_id, 'ld_quiz_questions', true );
            
            if ( is_array( $question_ids ) ) {
                foreach ( $question_ids as $question_id ) {
                    $question_data = $this->export_question_comprehensive( $question_id, $options );
                    $quiz_data['questions'][] = $question_data;
                }
            }
        }
        
        // Export ProQuiz data
        $pro_quiz_id = get_post_meta( $quiz_id, 'quiz_pro', true );
        if ( $pro_quiz_id ) {
            $quiz_data['pro_quiz_data'] = array(
                'quiz_id' => $pro_quiz_id,
                'quiz_settings' => $this->export_pro_quiz_settings( $pro_quiz_id )
            );
        }
        
        // Export taxonomies if requested
        if ( $options['include_taxonomies'] ) {
            $quiz_data['taxonomies'] = $this->export_post_taxonomies( $quiz_id );
        }
        
        return $quiz_data;
    }
    
    /**
     * Export question with comprehensive data.
     *
     * @param int   $question_id Question ID.
     * @param array $options     Export options.
     * @return array Question data.
     */
    private function export_question_comprehensive( $question_id, $options ) {
        $question = get_post( $question_id );
        
        $question_data = array(
            'post_data' => $this->export_post_data( $question ),
            'post_meta' => $this->export_post_meta_preserved( $question_id ),
            'pro_quiz_question_data' => array(),
            'taxonomies' => array()
        );
        
        // Export ProQuiz question data
        $pro_question_id = get_post_meta( $question_id, 'question_pro_id', true );
        if ( $pro_question_id ) {
            $question_data['pro_quiz_question_data'] = array(
                'question_id' => $pro_question_id,
                'question_settings' => $this->export_pro_question_settings( $pro_question_id )
            );
        }
        
        // Export taxonomies if requested
        if ( $options['include_taxonomies'] ) {
            $question_data['taxonomies'] = $this->export_post_taxonomies( $question_id );
        }
        
        return $question_data;
    }
    
    /**
     * Export certificate with comprehensive data.
     *
     * @param int   $certificate_id Certificate ID.
     * @param array $options        Export options.
     * @return array Certificate data.
     */
    private function export_certificate_comprehensive( $certificate_id, $options ) {
        $certificate = get_post( $certificate_id );
        
        $certificate_data = array(
            'post_data' => $this->export_post_data( $certificate ),
            'post_meta' => $this->export_post_meta_preserved( $certificate_id ),
            'elementor_data' => array(),
            'taxonomies' => array()
        );
        
        // Export Elementor data if requested
        if ( $options['include_elementor'] ) {
            $certificate_data['elementor_data'] = $this->ld_export_get_elementor_data( $certificate_id );
        }
        
        // Export taxonomies if requested
        if ( $options['include_taxonomies'] ) {
            $certificate_data['taxonomies'] = $this->export_post_taxonomies( $certificate_id );
        }
        
        return $certificate_data;
    }
    
    /**
     * Export post taxonomies.
     *
     * @param int $post_id Post ID.
     * @return array Taxonomy data.
     */
    private function export_post_taxonomies( $post_id ) {
        $post_type = get_post_type( $post_id );
        $taxonomies = get_object_taxonomies( $post_type );
        $taxonomy_data = array();
        
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post_id, $taxonomy );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $taxonomy_data[ $taxonomy ] = array();
                foreach ( $terms as $term ) {
                    $taxonomy_data[ $taxonomy ][] = array(
                        'term_id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'description' => $term->description,
                        'parent' => $term->parent,
                        'term_meta' => get_term_meta( $term->term_id )
                    );
                }
            }
        }
        
        return $taxonomy_data;
    }
    
    /**
     * Export ProQuiz settings (placeholder for ProQuiz integration).
     *
     * @param int $pro_quiz_id ProQuiz ID.
     * @return array ProQuiz settings.
     */
    private function export_pro_quiz_settings( $pro_quiz_id ) {
        // This would integrate with ProQuiz tables
        // For now, return empty array as placeholder
        ld_log( 'export', "ProQuiz settings export - ID: {$pro_quiz_id} (placeholder)", 'debug' );
        return array();
    }
    
    /**
     * Export ProQuiz question settings (placeholder for ProQuiz integration).
     *
     * @param int $pro_question_id ProQuiz question ID.
     * @return array ProQuiz question settings.
     */
    private function export_pro_question_settings( $pro_question_id ) {
        // This would integrate with ProQuiz tables
        // For now, return empty array as placeholder
        ld_log( 'export', "ProQuiz question settings export - ID: {$pro_question_id} (placeholder)", 'debug' );
        return array();
    }
}