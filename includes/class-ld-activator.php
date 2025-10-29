<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
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
 * Class LD_Activator
 */
class LD_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        self::create_logs_table();
        self::create_batch_table();
    }

    /**
     * Create logs table for storing export/import logs.
     *
     * @since 1.0.0
     */
    private static function create_logs_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ld_export_import_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'info',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Create batch table for tracking batch processing.
     *
     * @since 1.0.0
     */
    private static function create_batch_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ld_export_import_batches';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            batch_id varchar(100) NOT NULL,
            type varchar(50) NOT NULL,
            offset_val int NOT NULL DEFAULT 0,
            limit_val int NOT NULL DEFAULT 100,
            status varchar(20) NOT NULL DEFAULT 'pending',
            args longtext,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY batch_id (batch_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}