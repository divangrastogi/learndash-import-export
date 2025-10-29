<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
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
 * Class LD_Deactivator
 */
class LD_Deactivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Optionally drop tables, but for safety, leave them.
        // self::drop_tables();
    }

    /**
     * Drop custom tables (uncomment if needed).
     *
     * @since 1.0.0
     */
    private static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'ld_export_import_logs',
            $wpdb->prefix . 'ld_export_import_batches',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }
}