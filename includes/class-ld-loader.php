<?php
/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
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
 * Class LD_Loader
 */
class LD_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string $hook          The name of the WordPress action that is being registered.
     * @param    object $component     A reference to the instance of the object on which the action is defined.
     * @param    string $callback      The name of the function definition on the $component.
     * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     * @param    string $hook          The name of the WordPress filter that is being registered.
     * @param    object $component     A reference to the instance of the object on which the filter is defined.
     * @param    string $callback      The name of the function definition on the $component.
     * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @since    1.0.0
     * @access   private
     * @param    array  $hooks         The collection of hooks that is being registered (that is, actions or filters).
     * @param    string $hook          The name of the WordPress filter that is being registered.
     * @param    object $component     A reference to the instance of the object on which the filter is defined.
     * @param    string $callback      The name of the function definition on the $component.
     * @param    int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param    int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     * @return   array                                  The collection of actions and filters registered with WordPress.
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }

        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cli_hooks();

        if ( class_exists( 'LD_Compat' ) ) {
            LD_Compat::init();
        }
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - LD_Exporter. Orchestrates the hooks of the plugin.
     * - LD_Importer. Defines internationalization functionality.
     * - LD_Admin. Defines all hooks for the admin area.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'admin/class-ld-admin-ui.php';

        /**
         * The class responsible for exporting LearnDash data.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-exporter.php';

        /**
         * The class responsible for importing LearnDash data.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-importer.php';

        /**
         * The class responsible for data mapping.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-data-mapper.php';

        /**
         * The class responsible for batch processing.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-batch-processor.php';

        /**
         * The class responsible for bulk exporting.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-bulk-exporter.php';

        /**
         * Helper functions.
         */
        require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/helpers.php';

        // Compat layer (optional include with safety check)
        $compat_file = LEARNDASH_EXPORT_IMPORT_DIR . 'includes/compat/class-ld-compat.php';
        if ( file_exists( $compat_file ) ) {
            require_once $compat_file;
        } else {
            add_action( 'admin_notices', function () use ( $compat_file ) {
                echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( 'LearnDash Export/Import: Optional compat file not found: %s', $compat_file ) ) . '</p></div>';
            } );
        }

        /**
         * WP-CLI commands.
         */
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once LEARNDASH_EXPORT_IMPORT_DIR . 'includes/class-ld-cli.php';
        }
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $admin_ui = new LD_Admin_UI( 'learndash-export-import', LEARNDASH_EXPORT_IMPORT_VERSION );
        
        add_action( 'admin_enqueue_scripts', array( $admin_ui, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $admin_ui, 'enqueue_scripts' ) );
        add_action( 'admin_menu', array( $admin_ui, 'add_menu_pages' ) );
        add_action( 'admin_post_ld_export_settings_save', array( $admin_ui, 'handle_export_settings_save' ) );
        add_action( 'admin_post_ld_single_export_download', array( $admin_ui, 'handle_single_export_download' ) );
        add_action( 'admin_post_ld_export_download', 'ld_admin_handle_export_download' );
        add_action( 'wp_ajax_ld_import', array( $admin_ui, 'handle_import_ajax' ) );
        add_action( 'wp_ajax_ld_batch_status', array( $admin_ui, 'handle_batch_status_ajax' ) );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $this->add_action( 'ld_process_batch', array( new LD_Batch_Processor(), 'process_batch' ), 10, 1 );
    }

    /**
     * Register WP-CLI commands.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_cli_hooks() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'ld export', 'LD_CLI_Export_Command' );
            WP_CLI::add_command( 'ld import', 'LD_CLI_Import_Command' );
        }
    }
}