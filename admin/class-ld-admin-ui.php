<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/admin
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class LD_Admin_UI
 */
class LD_Admin_UI {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string $plugin_name       The name of this plugin.
     * @param      string $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize AJAX hooks immediately
        $this->init_ajax_hooks();
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/css/admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, LEARNDASH_EXPORT_IMPORT_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, false );
        
        // Enhanced export functionality
        wp_enqueue_script( $this->plugin_name . '-export-enhanced', LEARNDASH_EXPORT_IMPORT_URL . 'assets/js/export-enhanced.js', array( 'jquery' ), $this->version, false );

        wp_localize_script( $this->plugin_name, 'ld_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'import_nonce' => wp_create_nonce( 'ld_import_nonce' ),
            'batch_status_nonce' => wp_create_nonce( 'ld_batch_status_nonce' ),
            'process_item_nonce' => wp_create_nonce( 'ld_process_item_nonce' ),
            'export_nonce' => wp_create_nonce( 'ld_export_nonce' ),
            'bulk_export_nonce' => wp_create_nonce( 'ld_bulk_export' ),
            'delete_nonce' => wp_create_nonce( 'ld_delete_nonce' ),
            'clear_logs_nonce' => wp_create_nonce( 'ld_clear_logs_nonce' ),
        ) );
    }

    /**
     * Initialize AJAX hooks.
     */
    public function init_ajax_hooks() {
        // Export AJAX handlers
        add_action( 'wp_ajax_ld_initialize_bulk_export', array( $this, 'handle_bulk_export_init_ajax' ) );
        add_action( 'wp_ajax_ld_ajax_bulk_export_courses', array( $this, 'handle_bulk_export_chunk_ajax' ) );
        add_action( 'wp_ajax_ld_get_export_progress', array( $this, 'handle_export_progress_ajax' ) );
        add_action( 'wp_ajax_ld_generate_export_file', array( $this, 'handle_export_file_generation_ajax' ) );
        add_action( 'wp_ajax_ld_check_export_completion', array( $this, 'handle_export_completion_check_ajax' ) );
        add_action( 'wp_ajax_ld_cancel_export', array( $this, 'handle_export_cancel_ajax' ) );
        
        // Import AJAX handlers (existing)
        add_action( 'wp_ajax_ld_import', array( $this, 'handle_import_ajax' ) );
        add_action( 'wp_ajax_ld_batch_status', array( $this, 'handle_batch_status_ajax' ) );
        add_action( 'wp_ajax_ld_clear_logs', array( $this, 'handle_clear_logs_ajax' ) );
        add_action( 'wp_ajax_ld_process_import_item', array( $this, 'handle_process_import_item_ajax' ) );
        add_action( 'wp_ajax_ld_delete_all_data', array( $this, 'handle_delete_all_data_ajax' ) );
    }
    
    /**
     * Add menu pages.
     */
    public function add_menu_pages() {
        add_menu_page(
            __( 'LearnDash Export & Import', 'learndash-export-import' ),
            __( 'LD Export/Import', 'learndash-export-import' ),
            'manage_options',
            'learndash-export-import',
            array( $this, 'display_export_page' ),
            'dashicons-download',
            30
        );

        add_submenu_page(
            'learndash-export-import',
            __( 'Export', 'learndash-export-import' ),
            __( 'Export', 'learndash-export-import' ),
            'manage_options',
            'learndash-export-import',
            array( $this, 'display_export_page' )
        );

        add_submenu_page(
            'learndash-export-import',
            __( 'Import', 'learndash-export-import' ),
            __( 'Import', 'learndash-export-import' ),
            'manage_options',
            'ld-export-import-import',
            array( $this, 'display_import_page' )
        );

        add_submenu_page(
            'learndash-export-import',
            __( 'Logs', 'learndash-export-import' ),
            __( 'Logs', 'learndash-export-import' ),
            'manage_options',
            'learndash-export-import-logs',
            array( $this, 'display_logs_page' )
        );
    }

    /**
     * Display export page.
     */
    public function display_export_page() {
        include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/export-page.php';
    }

    /**
     * Display import page.
     */
    public function display_import_page() {
        include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/import-page.php';
    }

    /**
     * Display logs page.
     */
    public function display_logs_page() {
        $per_page = 50;
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        $logs = ld_get_logs( array(
            'limit' => $per_page,
            'offset' => $offset,
        ) );

        $total_logs = ld_get_logs_count();
        $total_pages = ceil( $total_logs / $per_page );

        include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/logs-page.php';
    }

    /**
     * Display test page.
     */
    public function display_test_page() {
        include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/test-page.php';
    }

    /**
     * Handle export AJAX.
     */
    public function handle_export_ajax() {
        check_ajax_referer( 'ld_export_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $batch_processor = new LD_Batch_Processor();
        $batch_id = $batch_processor->start_export_batch( $_POST );

        wp_send_json_success( array( 'batch_id' => $batch_id ) );
    }

    /**
     * Handle import AJAX.
     */
    public function handle_import_ajax() {
        check_ajax_referer( 'ld_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        // Validate file upload.
        if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'File upload failed. Please try again.' );
        }

        $file = $_FILES['import_file'];
        
        // Validate file type.
        if ( $file['type'] !== 'application/json' && pathinfo( $file['name'], PATHINFO_EXTENSION ) !== 'json' ) {
            wp_send_json_error( 'Please upload a valid JSON file.' );
        }

        // Read and validate JSON data.
        $json_content = file_get_contents( $file['tmp_name'] );
        if ( ! $json_content ) {
            wp_send_json_error( 'Could not read the uploaded file.' );
        }

        $data = json_decode( $json_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid JSON file. Error: ' . json_last_error_msg() );
        }

        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Invalid data format in JSON file.' );
        }

        // Log data structure for debugging.
        ld_log( 'import', 'Import data structure: ' . print_r( array_keys( $data ), true ) );
        if ( isset( $data['courses'] ) ) {
            ld_log( 'import', 'Found ' . count( $data['courses'] ) . ' courses in import data' );
        }
        if ( isset( $data['groups'] ) ) {
            ld_log( 'import', 'Found ' . count( $data['groups'] ) . ' groups in import data' );
        }

        // Prepare import arguments.
        $args = array(
            'duplicate_handling' => isset( $_POST['duplicate_handling'] ) ? sanitize_text_field( $_POST['duplicate_handling'] ) : 'create_new',
        );

        try {
            $items = $this->prepare_import_items( $data );
            $total_items = count( $items );

            if ( $total_items <= 0 ) {
                wp_send_json_error( 'No importable items found in file.' );
            }

            $batch_id = 'ld_import_' . uniqid();

            set_transient( 'ld_import_payload_' . $batch_id, array(
                'data' => $data,
                'args' => $args,
                'total_items' => $total_items,
            ), HOUR_IN_SECONDS );

            set_transient( 'ld_import_progress_' . $batch_id, array(
                'status' => 'pending',
                'processed' => 0,
                'imported' => 0,
                'skipped' => 0,
                'errors' => array(),
            ), HOUR_IN_SECONDS );

            wp_send_json_success( array(
                'batch_mode' => true,
                'batch_id' => $batch_id,
                'total_items' => $total_items,
                'message' => 'Import queued. Processing will begin shortly.',
            ) );

        } catch ( Exception $e ) {
            ld_log( 'import', 'Import failed: ' . $e->getMessage(), 'error' );
            wp_send_json_error( 'Import failed: ' . $e->getMessage() );
        }
    }

    public function handle_process_import_item_ajax() {
        check_ajax_referer( 'ld_process_item_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        $batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';
        $index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : 0;

        if ( empty( $batch_id ) ) {
            wp_send_json_error( 'Batch ID is required.' );
        }

        $payload = get_transient( 'ld_import_payload_' . $batch_id );
        $progress = get_transient( 'ld_import_progress_' . $batch_id );

        if ( ! $payload || ! $progress ) {
            wp_send_json_error( 'Import session expired. Please restart the import.' );
        }

        $items = $this->prepare_import_items( $payload['data'] );

        if ( $index >= count( $items ) ) {
            $progress['status'] = 'completed';
            $progress['processed'] = $payload['total_items'];

            set_transient( 'ld_import_progress_' . $batch_id, $progress, HOUR_IN_SECONDS );
            delete_transient( 'ld_import_payload_' . $batch_id );

            wp_send_json_success( array(
                'status' => 'completed',
                'progress' => 100,
                'processed' => $progress['processed'],
                'summary' => $this->build_progress_summary( $progress ),
            ) );
        }

        $item = $items[ $index ];

        $importer = new LD_Importer();
        $result = array( 'imported' => 0, 'skipped' => 0, 'errors' => array() );

        try {
            if ( 'group' === $item['type'] ) {
                $result = $importer->import_group( $item['data'], $payload['args'] );
            } else {
                $result = $importer->import_course( $item['data'], $payload['args'] );
            }
        } catch ( Exception $e ) {
            $result['errors'][] = $e->getMessage();
        }

        $progress['status'] = 'processing';
        $progress['processed'] = $index + 1;
        $progress['imported'] += isset( $result['imported'] ) ? (int) $result['imported'] : 0;
        $progress['skipped'] += isset( $result['skipped'] ) ? (int) $result['skipped'] : 0;
        if ( ! empty( $result['errors'] ) ) {
            $progress['errors'] = array_merge( $progress['errors'], (array) $result['errors'] );
        }

        set_transient( 'ld_import_progress_' . $batch_id, $progress, HOUR_IN_SECONDS );

        $percentage = $this->calculate_transient_progress_percentage( $progress['processed'], $payload['total_items'] );

        if ( $progress['processed'] >= $payload['total_items'] ) {
            $progress['status'] = 'completed';
            $percentage = 100;
            set_transient( 'ld_import_progress_' . $batch_id, $progress, HOUR_IN_SECONDS );
            delete_transient( 'ld_import_payload_' . $batch_id );
        }

        wp_send_json_success( array(
            'status' => $progress['status'],
            'progress' => $percentage,
            'processed' => $progress['processed'],
            'summary' => $this->build_progress_summary( $progress ),
        ) );
    }

    /**
     * Handle batch status check AJAX.
     */
    public function handle_batch_status_ajax() {
        check_ajax_referer( 'ld_batch_status_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        $batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( $_POST['batch_id'] ) : '';

        if ( empty( $batch_id ) ) {
            wp_send_json_error( 'Batch ID is required.' );
        }

        $progress = get_transient( 'ld_import_progress_' . $batch_id );
        $payload = get_transient( 'ld_import_payload_' . $batch_id );

        if ( ! $progress ) {
            wp_send_json_error( 'Batch not found or expired.' );
        }

        $total_items = isset( $payload['total_items'] ) ? $payload['total_items'] : 0;
        $percentage = $this->calculate_transient_progress_percentage( 
            isset( $progress['processed'] ) ? $progress['processed'] : 0, 
            $total_items 
        );

        wp_send_json_success( array(
            'status' => isset( $progress['status'] ) ? $progress['status'] : 'pending',
            'progress' => $percentage,
            'processed' => isset( $progress['processed'] ) ? $progress['processed'] : 0,
            'total' => $total_items,
            'summary' => $this->build_progress_summary( $progress ),
        ) );
    }

    private function prepare_import_items( $data ) {
        $items = array();

        if ( isset( $data['groups'] ) && is_array( $data['groups'] ) ) {
            foreach ( $data['groups'] as $group ) {
                $items[] = array(
                    'type' => 'group',
                    'data' => $group,
                );
            }
        }

        if ( isset( $data['courses'] ) && is_array( $data['courses'] ) ) {
            foreach ( $data['courses'] as $course ) {
                $items[] = array(
                    'type' => 'course',
                    'data' => $course,
                );
            }
        }

        return $items;
    }

    private function build_progress_summary( $progress ) {
        return array(
            'imported' => isset( $progress['imported'] ) ? (int) $progress['imported'] : 0,
            'skipped' => isset( $progress['skipped'] ) ? (int) $progress['skipped'] : 0,
            'errors' => isset( $progress['errors'] ) ? (array) $progress['errors'] : array(),
        );
    }

    private function calculate_transient_progress_percentage( $processed, $total ) {
        if ( $total <= 0 ) {
            return 0;
        }

        return min( 100, round( ( $processed / $total ) * 100 ) );
    }

    public function handle_clear_logs_ajax() {
        check_ajax_referer( 'ld_clear_logs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        ld_clear_logs();

        wp_send_json_success( array( 'message' => 'Logs cleared.' ) );
    }
    
    /**
     * Handle single export download (form submission).
     */
    public function handle_single_export_download() {
        // Check if this is our form submission
        if ( ! isset( $_POST['export_nonce'] ) || ! wp_verify_nonce( $_POST['export_nonce'], 'ld_export_nonce' ) ) {
            wp_die( 'Security check failed' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $course_id = intval( $_POST['course_id'] );
        $options = $this->get_export_options_from_post();

        ld_log( 'export', "Single export download requested - Course ID: {$course_id}", 'info' );

        try {
            $exporter = new LD_Exporter();
            $course_data = $exporter->ld_export_get_all_course_data( $course_id, $options );

            // Create export file immediately for single course
            $export_data = array(
                'export_meta' => array(
                    'version' => '2.0',
                    'plugin_version' => LEARNDASH_EXPORT_IMPORT_VERSION,
                    'timestamp' => current_time( 'mysql' ),
                    'site_url' => get_site_url(),
                    'export_type' => 'single_course',
                    'course_id' => $course_id
                ),
                'courses' => array( $course_data )
            );

            $this->generate_download_file( $export_data, 'single-course-' . $course_id );

        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
            if ( empty( $error_message ) ) {
                $error_message = 'Unknown error occurred during export';
            }
            ld_log( 'export', 'Single export failed: ' . $error_message, 'error' );
            wp_die( 'Export failed: ' . esc_html( $error_message ) );
        }
    }

    /**
     * Get export options from POST data.
     */
    private function get_export_options_from_post() {
        return array(
            'include_elementor' => isset( $_POST['include_elementor'] ) ? 1 : 0,
            'preserve_serialized' => isset( $_POST['preserve_serialized'] ) ? 1 : 0,
            'include_certificates' => isset( $_POST['include_certificates'] ) ? 1 : 0,
            'include_quiz_questions' => isset( $_POST['include_quiz_questions'] ) ? 1 : 0,
            'include_taxonomies' => isset( $_POST['include_taxonomies'] ) ? 1 : 0,
            'chunk_size' => sanitize_text_field( $_POST['chunk_size'] ?? 'auto' ),
        );
    }

    /**
     * Handle bulk export initialization AJAX.
     */
    public function handle_bulk_export_init_ajax() {
        check_ajax_referer( 'ld_bulk_export', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $course_ids = isset( $_POST['course_ids'] ) ? array_map( 'intval', $_POST['course_ids'] ) : array();
        $options = isset( $_POST['options'] ) ? $_POST['options'] : array();
        
        if ( empty( $course_ids ) ) {
            wp_send_json_error( 'No courses selected' );
        }
        
        try {
            $bulk_exporter = new LD_Bulk_Exporter();
            $session_id = $bulk_exporter->initialize_bulk_export( $course_ids, $options );
            
            wp_send_json_success( array(
                'session_id' => $session_id,
                'total_courses' => count( $course_ids ),
                'message' => 'Bulk export initialized successfully'
            ) );
            
        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
            if ( empty( $error_message ) ) {
                $error_message = 'Unknown error occurred during bulk export initialization';
            }
            wp_send_json_error( $error_message );
        }
    }
    
    /**
     * Handle bulk export chunk processing AJAX.
     */
    public function handle_bulk_export_chunk_ajax() {
        // Delegate to the bulk exporter - it handles its own JSON responses
        $bulk_exporter = new LD_Bulk_Exporter();
        $bulk_exporter->ld_ajax_bulk_export_courses();
    }
    
    /**
     * Handle export progress check AJAX.
     */
    public function handle_export_progress_ajax() {
        check_ajax_referer( 'ld_bulk_export', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $session_id = sanitize_text_field( $_POST['session_id'] );
        
        try {
            $bulk_exporter = new LD_Bulk_Exporter();
            $progress = $bulk_exporter->get_export_progress( $session_id );
            
            if ( $progress ) {
                wp_send_json_success( $progress );
            } else {
                wp_send_json_error( 'Progress data not found' );
            }
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * Handle export file generation AJAX.
     */
    public function handle_export_file_generation_ajax() {
        check_ajax_referer( 'ld_bulk_export', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $session_id = sanitize_text_field( $_POST['session_id'] );
        
        try {
            $bulk_exporter = new LD_Bulk_Exporter();
            $file_info = $bulk_exporter->generate_export_file( $session_id );
            
            wp_send_json_success( $file_info );
            
        } catch ( Exception $e ) {
            ld_log( 'export', 'File generation failed: ' . $e->getMessage(), 'error' );
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * Generate download file for single course export.
     */
    private function generate_download_file( $export_data, $filename_prefix ) {
        $json_data = wp_json_encode( $export_data, JSON_PRETTY_PRINT );
        
        $filename = $filename_prefix . '-' . date( 'Y-m-d-H-i-s' ) . '.json';
        
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json_data ) );
        
        echo $json_data;
        
        ld_log( 'export', "Single course export file generated: {$filename}", 'info' );
        
        exit;
    }

    /**
     * Handle export settings form submission.
     */
    public function handle_export_settings_save() {
        // Check if this is our form submission
        if ( ! isset( $_POST['export_settings_nonce'] ) || ! wp_verify_nonce( $_POST['export_settings_nonce'], 'ld_export_settings' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        // Save settings to options
        $settings = array(
            'include_elementor' => isset( $_POST['include_elementor'] ) ? 1 : 0,
            'preserve_serialized' => isset( $_POST['preserve_serialized'] ) ? 1 : 0,
            'include_certificates' => isset( $_POST['include_certificates'] ) ? 1 : 0,
            'include_quiz_questions' => isset( $_POST['include_quiz_questions'] ) ? 1 : 0,
            'include_taxonomies' => isset( $_POST['include_taxonomies'] ) ? 1 : 0,
            'chunk_size' => sanitize_text_field( $_POST['chunk_size'] ?? 'auto' ),
        );

        update_option( 'learndash_export_settings', $settings );

        // Redirect back with success message
        wp_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
        exit;
    }

    /**
     * Handle export completion check AJAX.
     */
    public function handle_export_completion_check_ajax() {
        check_ajax_referer( 'ld_bulk_export', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $session_id = sanitize_text_field( $_POST['session_id'] );
        
        try {
            $bulk_exporter = new LD_Bulk_Exporter();
            $progress = $bulk_exporter->get_export_progress( $session_id );
            
            if ( $progress && $progress['progress_percentage'] >= 100 ) {
                wp_send_json_success( array( 'complete' => true ) );
            } else {
                wp_send_json_success( array( 'complete' => false ) );
            }
            
        } catch ( Exception $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }
    
    /**
     * Handle export cancel AJAX.
     */
    public function handle_export_cancel_ajax() {
        check_ajax_referer( 'ld_bulk_export', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $session_id = sanitize_text_field( $_POST['session_id'] );
        
        // Clean up session data
        delete_transient( "ld_export_session_{$session_id}" );
        delete_transient( "ld_export_progress_{$session_id}" );
        
        // Clean up any chunks
        for ( $i = 0; $i < 100; $i++ ) { // Reasonable upper limit
            delete_transient( "ld_export_chunk_{$session_id}_{$i}" );
        }
        
        ld_log( 'export', "Export session {$session_id} cancelled by user", 'info' );

        wp_send_json_success( array( 'message' => 'Export cancelled successfully' ) );
    }

    /**
     * Handle delete all LearnDash data AJAX.
     */
    public function handle_delete_all_data_ajax() {
        check_ajax_referer( 'ld_delete_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized access.' );
        }

        try {
            $this->delete_all_learndash_data();
            ld_log( 'delete', 'All LearnDash data deleted successfully', 'info' );
            wp_send_json_success( array( 'message' => 'All LearnDash data has been deleted successfully.' ) );
        } catch ( Exception $e ) {
            ld_log( 'delete', 'Delete failed: ' . $e->getMessage(), 'error' );
            wp_send_json_error( 'Delete failed: ' . $e->getMessage() );
        }
    }

    /**
     * Delete all LearnDash data except settings.
     */
    private function delete_all_learndash_data() {
        global $wpdb;

        // Increase execution time to handle large deletions
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        // Post types to delete
        $post_types = array(
            'sfwd-courses',
            'sfwd-lessons',
            'sfwd-topic',
            'sfwd-quiz',
            'sfwd-question',
            'sfwd-certificates',
            // 'groups', // LearnDash groups - commented out to avoid potential issues
        );

        // Delete posts
        foreach ( $post_types as $post_type ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s", $post_type ) );
            // Also delete post meta
            $wpdb->query( $wpdb->prepare( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = %s", $post_type ) );
        }

        // Delete user meta related to LearnDash
        $user_meta_patterns = array(
            'course_progress%',
            'quiz_progress%',
            'learndash_course_progress%',
            'learndash_quiz_progress%',
            'ld_course_progress%',
            'ld_quiz_progress%',
            'course_completed_%',
            'quiz_completed_%',
            'learndash_course_access_%',
            'learndash_group_users_%',
        );

        foreach ( $user_meta_patterns as $pattern ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $pattern ) );
        }

        // Note: LearnDash custom tables data deletion commented out to avoid potential issues
        // Uncomment and test carefully
        /*
        $learndash_tables = array(
            'learndash_user_activity',
            'learndash_user_activity_meta',
        );

        foreach ( $learndash_tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
                $wpdb->query( "TRUNCATE TABLE {$table_name}" );
            }
        }
        */

        // Delete options that are not settings (keep learndash_* settings)
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ld_%' AND option_name NOT LIKE 'learndash_%'" );

        // Delete transients
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ld_%' OR option_name LIKE '_transient_timeout_ld_%'" );

        // Clear any caches
        wp_cache_flush();

        // ld_log( 'delete', 'Deletion completed', 'info' ); // Commented out to avoid potential issues
    }
}