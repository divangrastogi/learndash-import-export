<?php
/**
 * Plugin Name: LearnDash Exporter & Importer
 * Plugin URI: https://wbcomdesigns.com/
 * Description: A comprehensive tool to export and import LearnDash courses, lessons, topics, quizzes, and related data while maintaining relational integrity.
 * Version: 1.0.0
 * Author: WBCom Designs
 * Author URI: https://wbcomdesigns.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: learndash-export-import
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 8.0
 * LearnDash Version: 4.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 */
define( 'LEARNDASH_EXPORT_IMPORT_VERSION', '1.0.0' );

/**
 * Plugin basename.
 */
define( 'LEARNDASH_EXPORT_IMPORT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin directory path.
 */
define( 'LEARNDASH_EXPORT_IMPORT_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'LEARNDASH_EXPORT_IMPORT_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_learndash_export_import() {
    require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-activator.php';
    LD_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_learndash_export_import() {
    require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-deactivator.php';
    LD_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_learndash_export_import' );
register_deactivation_hook( __FILE__, 'deactivate_learndash_export_import' );

/**
 * Check if LearnDash is active.
 */
function ld_check_dependencies() {
    if ( ! class_exists( 'SFWD_LMS' ) ) {
        add_action( 'admin_notices', 'ld_missing_learndash_notice' );
        return false;
    }
    return true;
}

/**
 * Display admin notice if LearnDash is not active.
 */
function ld_missing_learndash_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'LearnDash Export & Import requires LearnDash to be installed and activated.', 'learndash-export-import' );
    echo '</p></div>';
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */
function run_learndash_export_import() {
    if ( ! ld_check_dependencies() ) {
        return;
    }
    
    require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-loader.php';
    $plugin = new LD_Loader();
    $plugin->run();
}

add_action( 'plugins_loaded', 'run_learndash_export_import' );