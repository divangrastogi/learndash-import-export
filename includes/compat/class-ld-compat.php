<?php
/**
 * LearnDash Export/Import - Compatibility layer
 * Adds runtime normalizations for LearnDash + Elementor integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'LD_Compat' ) ) {
    class LD_Compat {
        public static function init() {
            // Require LearnDash
            if ( ! class_exists( 'SFWD_LMS' ) ) {
                return;
            }

            // Normalize lessons list structure used by LD30 + LD Elementor templates
            add_filter( 'learndash_get_course_lessons_list', [ __CLASS__, 'filter_lessons_list' ], 1, 3 );

            // Normalize simple lessons collection to be IDs (avoids object/array confusion)
            add_filter( 'learndash_get_course_lessons', [ __CLASS__, 'filter_course_lessons' ], 999, 2 );

            // Normalize course steps mapping to guarantee integer IDs at all levels
            add_filter( 'learndash_course_steps', [ __CLASS__, 'filter_course_steps' ], 999, 2 );

            // Force-correct args for LD30 + Elementor course template (Template name is 'course')
            add_filter( 'learndash_template_args:course', [ __CLASS__, 'filter_template_args_course_index' ], 999, 5 );
            add_filter( 'ld_template_args_course', [ __CLASS__, 'filter_ld_template_args_course_index' ], 999, 3 );

            // Also correct args for course/listing.php if needed
            add_filter( 'learndash_template_args:course/listing.php', [ __CLASS__, 'filter_template_args_course_listing' ], 999, 5 );
            add_filter( 'ld_template_args_course/listing.php', [ __CLASS__, 'filter_ld_template_args_course_listing' ], 999, 3 );

            // Global fallback: ensure course template always has required args
            add_filter( 'learndash_template_args', [ __CLASS__, 'filter_template_args_global' ], 999, 5 );
        }

        /**
         * Convert lessons to the exact array format LD30 expects: [ 'post' => WP_Post, 'ID' => int ]
         */
        public static function filter_lessons_list( $lessons, $course_id, $user_id = null ) {
            // If LearnDash returns nothing or an invalid type, try to rebuild from ld_course_steps
            if ( empty( $lessons ) || ! is_array( $lessons ) ) {
                $rebuilt = [];
                $cid     = $course_id ? (int) $course_id : (int) learndash_get_course_id();
                if ( $cid ) {
                    $steps = get_post_meta( $cid, 'ld_course_steps', true );
                    if ( is_array( $steps ) && isset( $steps['h']['sfwd-lessons'] ) && is_array( $steps['h']['sfwd-lessons'] ) ) {
                        foreach ( $steps['h']['sfwd-lessons'] as $lid ) {
                            $post = get_post( (int) $lid );
                            if ( $post instanceof WP_Post ) {
                                $rebuilt[] = [ 'post' => $post, 'ID' => (int) $post->ID ];
                            }
                        }
                    }
                }
                if ( ! empty( $rebuilt ) ) {
                    $lessons = $rebuilt;
                } else {
                    return $lessons;
                }
            }

            // Avoid interfering with admin builder UIs
            if ( is_admin() && ! wp_doing_ajax() ) {
                return $lessons;
            }

            $fixed = [];
            foreach ( $lessons as $lesson ) {
                if ( is_array( $lesson ) && isset( $lesson['post'] ) && $lesson['post'] instanceof WP_Post ) {
                    $fixed[] = $lesson;
                    continue;
                }

                if ( is_object( $lesson ) ) {
                    if ( isset( $lesson->ID ) ) {
                        $post = get_post( $lesson->ID );
                        if ( $post ) {
                            $fixed[] = [ 'post' => $post, 'ID' => (int) $post->ID ];
                        }
                        continue;
                    }

                    if ( isset( $lesson->post ) && $lesson->post instanceof WP_Post ) {
                        $fixed[] = [ 'post' => $lesson->post, 'ID' => (int) $lesson->post->ID ];
                        continue;
                    }
                }

                if ( is_numeric( $lesson ) ) {
                    $post = get_post( (int) $lesson );
                    if ( $post ) {
                        $fixed[] = [ 'post' => $post, 'ID' => (int) $post->ID ];
                    }
                }
            }

            return $fixed;
        }

        /**
         * Convert lessons collection to an array of integer IDs.
         */
        public static function filter_course_lessons( $lessons, $course_id ) {
            if ( empty( $lessons ) ) {
                return $lessons;
            }

            if ( is_admin() && ! wp_doing_ajax() ) {
                return $lessons;
            }

            $ids = [];
            foreach ( $lessons as $lesson ) {
                if ( is_object( $lesson ) && isset( $lesson->ID ) ) {
                    $ids[] = (int) $lesson->ID;
                } elseif ( is_array( $lesson ) && isset( $lesson['ID'] ) ) {
                    $ids[] = (int) $lesson['ID'];
                } elseif ( is_numeric( $lesson ) ) {
                    $ids[] = (int) $lesson;
                }
            }

            return array_values( array_unique( array_filter( $ids ) ) );
        }

        /**
         * Ensure course steps ('h' + nested 'steps') contain only integer IDs.
         */
        public static function filter_course_steps( $steps, $course_id ) {
            if ( empty( $steps ) || ! is_array( $steps ) ) {
                return $steps;
            }

            if ( is_admin() && ! wp_doing_ajax() ) {
                return $steps;
            }

            // Normalize top-level hierarchy arrays
            if ( isset( $steps['h'] ) && is_array( $steps['h'] ) ) {
                foreach ( [ 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ] as $type ) {
                    if ( isset( $steps['h'][ $type ] ) && is_array( $steps['h'][ $type ] ) ) {
                        $steps['h'][ $type ] = array_map( function( $item ) {
                            if ( is_object( $item ) && isset( $item->ID ) ) {
                                return (int) $item->ID;
                            }
                            if ( is_array( $item ) && isset( $item['ID'] ) ) {
                                return (int) $item['ID'];
                            }
                            return (int) $item;
                        }, $steps['h'][ $type ] );
                    }
                }
            }

            // Normalize nested steps mapping
            if ( isset( $steps['steps'] ) && is_array( $steps['steps'] ) ) {
                foreach ( $steps['steps'] as $parent => $by_type ) {
                    if ( ! is_array( $by_type ) ) {
                        continue;
                    }
                    foreach ( $by_type as $t => $items ) {
                        if ( is_array( $items ) ) {
                            $steps['steps'][ $parent ][ $t ] = array_map( function( $item ) {
                                if ( is_object( $item ) && isset( $item->ID ) ) {
                                    return (int) $item->ID;
                                }
                                if ( is_array( $item ) && isset( $item['ID'] ) ) {
                                    return (int) $item['ID'];
                                }
                                return (int) $item;
                            }, $items );
                        }
                    }
                }
            }

            return $steps;
        }

        /**
         * Backfill/normalize template args for course/index.php (LD30 + Elementor).
         * Ensures $course_id is set and $lessons is in the expected array format.
         */
        public static function filter_template_args_course_index( $args, $name, $file_path, $echo, $template_instance ) {
            if ( is_admin() && ! wp_doing_ajax() ) {
                return $args;
            }

            $course_id = isset( $args['course_id'] ) ? (int) $args['course_id'] : 0;
            if ( ! $course_id ) {
                $course_id = (int) learndash_get_course_id();
                if ( $course_id ) {
                    $args['course_id'] = $course_id;
                }
            }

            if ( $course_id ) {
                $args['lessons'] = learndash_get_course_lessons_list( $course_id );

                // Fallback: derive lessons from ld_course_steps if empty
                if ( empty( $args['lessons'] ) ) {
                    $steps = get_post_meta( $course_id, 'ld_course_steps', true );
                    if ( is_array( $steps ) && isset( $steps['h']['sfwd-lessons'] ) && is_array( $steps['h']['sfwd-lessons'] ) ) {
                        $rebuilt = [];
                        foreach ( $steps['h']['sfwd-lessons'] as $lid ) {
                            $post = get_post( (int) $lid );
                            if ( $post instanceof WP_Post ) {
                                $rebuilt[] = [ 'post' => $post, 'ID' => (int) $post->ID ];
                            }
                        }
                        if ( ! empty( $rebuilt ) ) {
                            $args['lessons'] = $rebuilt;
                        }
                    }
                }

                // Provide lesson_topics if not present
                if ( empty( $args['lesson_topics'] ) && ! empty( $args['lessons'] ) ) {
                    $lesson_topics = [];
                    foreach ( $args['lessons'] as $lesson ) {
                        $lesson_id = is_array( $lesson ) && isset( $lesson['post']->ID ) ? (int) $lesson['post']->ID : ( is_numeric( $lesson ) ? (int) $lesson : 0 );
                        if ( $lesson_id ) {
                            $lesson_topics[ $lesson_id ] = learndash_topic_dots( $lesson_id, false, 'array', null, $course_id );
                        }
                    }
                    $args['lesson_topics'] = $lesson_topics;
                }

                // Provide quizzes if not present
                if ( ! isset( $args['quizzes'] ) ) {
                    $args['quizzes'] = learndash_get_course_quiz_list( $course_id );
                }
            }

            // Ensure user_id is available for hooks like 'learndash-course-before' and '...-after'
            if ( ! isset( $args['user_id'] ) || ! is_numeric( $args['user_id'] ) ) {
                $args['user_id'] = (int) get_current_user_id();
            }

            // Ensure content exists for tabs template usage
            if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) {
                $args['content'] = $course_id ? (string) get_post_field( 'post_content', $course_id ) : '';
            }

            // Ensure course content listing appears within the Content tab if not already present
            if ( $course_id && stripos( (string) $args['content'], '[course_content' ) === false ) {
                $listing = do_shortcode( sprintf( '[course_content course_id="%d" wrapper="0"]', $course_id ) );
                if ( is_string( $listing ) && strlen( trim( $listing ) ) > 0 ) {
                    $args['content'] .= "\n" . $listing;
                }
            }

            // Ensure materials is defined (string). Pull from LD settings if available, else empty string
            if ( ! isset( $args['materials'] ) ) {
                $materials = '';
                if ( $course_id && function_exists( 'learndash_get_setting' ) ) {
                    $mat = learndash_get_setting( $course_id, 'course_materials' );
                    if ( is_string( $mat ) ) {
                        $materials = $mat;
                    }
                }
                $args['materials'] = $materials;
            }

            return $args;
        }

        /** Wrapper for legacy ld_ namespace filter */
        public static function filter_ld_template_args_course_index( $args, $file_path, $echo ) {
            return self::filter_template_args_course_index( $args, 'course/index.php', $file_path, $echo, null );
        }

        /**
         * Backfill/normalize template args for course/listing.php as a safeguard.
         */
        public static function filter_template_args_course_listing( $args, $name, $file_path, $echo, $template_instance ) {
            if ( is_admin() && ! wp_doing_ajax() ) {
                return $args;
            }

            $course_id = isset( $args['course_id'] ) ? (int) $args['course_id'] : 0;
            if ( ! $course_id ) {
                $course_id = (int) learndash_get_course_id();
                if ( $course_id ) {
                    $args['course_id'] = $course_id;
                }
            }

            if ( $course_id && ( empty( $args['lessons'] ) || ! is_array( $args['lessons'] ) ) ) {
                $args['lessons'] = learndash_get_course_lessons_list( $course_id );

                // Fallback: derive lessons from ld_course_steps if empty
                if ( empty( $args['lessons'] ) ) {
                    $steps = get_post_meta( $course_id, 'ld_course_steps', true );
                    if ( is_array( $steps ) && isset( $steps['h']['sfwd-lessons'] ) && is_array( $steps['h']['sfwd-lessons'] ) ) {
                        $rebuilt = [];
                        foreach ( $steps['h']['sfwd-lessons'] as $lid ) {
                            $post = get_post( (int) $lid );
                            if ( $post instanceof WP_Post ) {
                                $rebuilt[] = [ 'post' => $post, 'ID' => (int) $post->ID ];
                            }
                        }
                        if ( ! empty( $rebuilt ) ) {
                            $args['lessons'] = $rebuilt;
                        }
                    }
                }
            }

            // Provide lesson_topics if not present
            if ( $course_id && empty( $args['lesson_topics'] ) && ! empty( $args['lessons'] ) ) {
                $lesson_topics = [];
                foreach ( $args['lessons'] as $lesson ) {
                    $lesson_id = is_array( $lesson ) && isset( $lesson['post']->ID ) ? (int) $lesson['post']->ID : ( is_numeric( $lesson ) ? (int) $lesson : 0 );
                    if ( $lesson_id ) {
                        $lesson_topics[ $lesson_id ] = learndash_topic_dots( $lesson_id, false, 'array', null, $course_id );
                    }
                }
                $args['lesson_topics'] = $lesson_topics;
            }

            // Provide quizzes if not present
            if ( $course_id && ! isset( $args['quizzes'] ) ) {
                $args['quizzes'] = learndash_get_course_quiz_list( $course_id );
            }

            return $args;
        }

        /** Wrapper for legacy ld_ namespace filter */
        public static function filter_ld_template_args_course_listing( $args, $file_path, $echo ) {
            return self::filter_template_args_course_listing( $args, 'course/listing.php', $file_path, $echo, null );
        }

        /**
         * Global args filter: backfill for any course template call path.
         */
        public static function filter_template_args_global( $args, $name, $file_path, $echo, $template_instance ) {
            if ( is_admin() && ! wp_doing_ajax() ) {
                return $args;
            }

            if ( 'course' !== $name && 'course/index.php' !== $name ) {
                return $args;
            }

            $course_id = isset( $args['course_id'] ) ? (int) $args['course_id'] : 0;
            if ( ! $course_id ) {
                $course_id = (int) learndash_get_course_id();
                if ( $course_id ) {
                    $args['course_id'] = $course_id;
                }
            }

            if ( $course_id ) {
                if ( ! isset( $args['lessons'] ) || ! is_array( $args['lessons'] ) ) {
                    $args['lessons'] = learndash_get_course_lessons_list( $course_id );
                }
                if ( ! isset( $args['materials'] ) ) {
                    $materials = '';
                    if ( function_exists( 'learndash_get_setting' ) ) {
                        $mat = learndash_get_setting( $course_id, 'course_materials' );
                        if ( is_string( $mat ) ) {
                            $materials = $mat;
                        }
                    }
                    $args['materials'] = $materials;
                }
                if ( ! isset( $args['content'] ) || ! is_string( $args['content'] ) ) {
                    $args['content'] = (string) get_post_field( 'post_content', $course_id );
                }
            }

            if ( ! isset( $args['user_id'] ) || ! is_numeric( $args['user_id'] ) ) {
                $args['user_id'] = (int) get_current_user_id();
            }

            return $args;
        }
    }
}
