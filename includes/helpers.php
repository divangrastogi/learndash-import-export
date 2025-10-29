<?php
/**
 * Helper functions.
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
 * Log a message.
 *
 * @param string $type    Log type.
 * @param string $message Message.
 * @param string $status  Status.
 */
function ld_log( $type, $message, $status = 'info' ) {
    global $wpdb;

    $table = $wpdb->prefix . 'ld_export_import_logs';

    $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $table,
        array(
            'type'    => $type,
            'message' => $message,
            'status'  => $status,
        )
    );
}

/**
 * Get logs.
 *
 * @param array $args Query args.
 * @return array Logs.
 */
function ld_get_logs( $args = array() ) {
    global $wpdb;

    $table = $wpdb->prefix . 'ld_export_import_logs';

    $defaults = array(
        'limit' => 50,
        'offset' => 0,
    );

    $args = wp_parse_args( $args, $defaults );

    $query = $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $args['limit'], $args['offset'] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

    return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Get total logs count.
 *
 * @return int Total logs count.
 */
function ld_get_logs_count() {
    global $wpdb;

    $table = $wpdb->prefix . 'ld_export_import_logs';

    $query = "SELECT COUNT(*) FROM $table"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery

    return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

function ld_clear_logs() {
    global $wpdb;

    $table = $wpdb->prefix . 'ld_export_import_logs';

    $wpdb->query( "DELETE FROM $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Enqueue admin styles.
 */
function ld_admin_enqueue_styles() {
    wp_enqueue_style( 'learndash-export-import', plugin_dir_url( __FILE__ ) . '../assets/css/admin.css', array(), LEARNDASH_EXPORT_IMPORT_VERSION, 'all' );
}

/**
 * Enqueue admin scripts.
 */
function ld_admin_enqueue_scripts() {
    wp_enqueue_script( 'learndash-export-import', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js', array( 'jquery' ), LEARNDASH_EXPORT_IMPORT_VERSION, false );
    wp_localize_script( 'learndash-export-import', 'ld_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
}

/**
 * Add admin menu pages.
 */
function ld_admin_add_menu_pages() {
    add_menu_page(
        __( 'LearnDash Export & Import', 'learndash-export-import' ),
        __( 'LD Export/Import', 'learndash-export-import' ),
        'manage_options',
        'learndash-export-import',
        'ld_admin_display_export_page',
        'dashicons-download',
        30
    );

    add_submenu_page(
        'learndash-export-import',
        __( 'Export', 'learndash-export-import' ),
        __( 'Export', 'learndash-export-import' ),
        'manage_options',
        'learndash-export-import',
        'ld_admin_display_export_page'
    );

    add_submenu_page(
        'learndash-export-import',
        __( 'Import', 'learndash-export-import' ),
        __( 'Import', 'learndash-export-import' ),
        'manage_options',
        'learndash-export-import-import',
        'ld_admin_display_import_page'
    );

    add_submenu_page(
        'learndash-export-import',
        __( 'Logs', 'learndash-export-import' ),
        __( 'Logs', 'learndash-export-import' ),
        'manage_options',
        'learndash-export-import-logs',
        'ld_admin_display_logs_page'
    );
}

/**
 * Display export page.
 */
function ld_admin_display_export_page() {
    include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/export-page.php';
}

/**
 * Display import page.
 */
function ld_admin_display_import_page() {
    include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/import-page.php';
}

/**
 * Display logs page.
 */
function ld_admin_display_logs_page() {
    $logs = ld_get_logs();
    include_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/views/logs-page.php';
}

/**
 * Handle export download.
 */
function ld_admin_handle_export_download() {
    check_admin_referer( 'ld_export_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    try {
        $exporter = new LD_Exporter();
        $data = $exporter->export( $_POST );
    } catch ( Exception $e ) {
        $data = array( 'error' => 'Export failed: ' . $e->getMessage() );
    }

    header( 'Content-Type: application/json' );
    header( 'Content-Disposition: attachment; filename="learndash-export-' . date( 'Y-m-d-H-i-s' ) . '.json"' );
    echo wp_json_encode( $data );
    exit;
}
