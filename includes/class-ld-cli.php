<?php
/**
 * WP-CLI commands.
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
 * Class LD_CLI_Export_Command
 */
class LD_CLI_Export_Command {

    /**
     * Export LearnDash data.
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Number of items to export per batch.
     * ---
     * default: 100
     * ---
     *
     * [--file=<file>]
     * : Output file path.
     * ---
     * default: /tmp/ld-export.json
     * ---
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $limit = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : 100;
        $file = isset( $assoc_args['file'] ) ? $assoc_args['file'] : '/tmp/ld-export.json';

        $exporter = new LD_Exporter();
        $data = $exporter->export( array( 'batch_size' => $limit ) );

        file_put_contents( $file, wp_json_encode( $data ) );

        WP_CLI::success( "Exported to $file" );
    }
}

/**
 * Class LD_CLI_Import_Command
 */
class LD_CLI_Import_Command {

    /**
     * Import LearnDash data.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the JSON file to import.
     *
     * [--duplicate-handling=<handling>]
     * : How to handle duplicates: create_new, skip, overwrite.
     * ---
     * default: create_new
     * ---
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $file = $args[0];
        $handling = isset( $assoc_args['duplicate-handling'] ) ? $assoc_args['duplicate-handling'] : 'create_new';

        if ( ! file_exists( $file ) ) {
            WP_CLI::error( "File $file does not exist." );
        }

        $data = json_decode( file_get_contents( $file ), true );

        if ( ! $data ) {
            WP_CLI::error( 'Invalid JSON file.' );
        }

        $importer = new LD_Importer();
        $result = $importer->import( $data, array( 'duplicate_handling' => $handling ) );

        WP_CLI::success( "Imported {$result['imported']} items, skipped {$result['skipped']}." );
    }
}