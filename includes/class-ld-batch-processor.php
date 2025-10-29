<?php
/**
 * Batch processing functionality.
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
 * Class LD_Batch_Processor
 */
class LD_Batch_Processor {

    /**
     * Ensure batch table exists and has correct structure.
     */
    private function ensure_table_exists() {
        global $wpdb;

        $table = $wpdb->prefix . 'ld_export_import_batches';

        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            ld_log( 'batch', 'Batch table missing, attempting to create: ' . $table );

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table (
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
            $result = dbDelta( $sql );

            if ( empty( $result ) ) {
                ld_log( 'batch', 'Batch table creation failed - dbDelta returned empty', 'error' );
            } else {
                ld_log( 'batch', 'Batch table creation result: ' . print_r( $result, true ) );
            }

            // Verify table was created
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                ld_log( 'batch', 'Batch table successfully created' );
            } else {
                ld_log( 'batch', 'Batch table creation failed - table still does not exist', 'error' );
            }
        } else {
            ld_log( 'batch', 'Batch table already exists: ' . $table );

            // Check if table has required columns
            $columns = $wpdb->get_col( "DESCRIBE $table", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

            $required_columns = array( 'args', 'data', 'created_at', 'updated_at' );

            foreach ( $required_columns as $column ) {
                if ( ! in_array( $column, $columns, true ) ) {
                    ld_log( 'batch', "Table missing column: $column, attempting to add", 'error' );

                    // Add missing column
                    $alter_sql = '';
                    if ( $column === 'args' ) {
                        $alter_sql = "ALTER TABLE $table ADD COLUMN args longtext";
                    } elseif ( $column === 'data' ) {
                        $alter_sql = "ALTER TABLE $table ADD COLUMN data longtext";
                    } elseif ( $column === 'created_at' ) {
                        $alter_sql = "ALTER TABLE $table ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP";
                    } elseif ( $column === 'updated_at' ) {
                        $alter_sql = "ALTER TABLE $table ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                    }

                    if ( $alter_sql ) {
                        $wpdb->query( $alter_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        ld_log( 'batch', "Added column $column to batch table" );
                    }
                }
            }

            // Verify all columns exist
            $columns_after = $wpdb->get_col( "DESCRIBE $table", 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $missing = array_diff( $required_columns, $columns_after );
            if ( ! empty( $missing ) ) {
                ld_log( 'batch', 'Still missing columns after alter: ' . implode( ', ', $missing ), 'error' );
            } else {
                ld_log( 'batch', 'All required columns present in batch table' );
            }
        }
    }

    /**
     * Process export in batches.
     *
     * @param array $args Arguments.
     * @return string Batch ID.
     */
    public function start_export_batch( $args = array() ) {
        $this->ensure_table_exists();
        $batch_id = 'export_' . uniqid();

        $batch_data = array(
            'batch_id' => $batch_id,
            'type' => 'export',
            'status' => 'pending',
            'offset_val' => 0,
            'limit_val' => isset( $args['batch_size'] ) ? $args['batch_size'] : 100,
            'args' => wp_json_encode( $args ),
        );

        $this->save_batch( $batch_data );

        // Schedule the batch.
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( time(), 'ld_process_batch', array( 'batch_id' => $batch_id ) );
        } else {
            wp_schedule_single_event( time(), 'ld_process_batch', array( 'batch_id' => $batch_id ) );
        }

        return $batch_id;
    }

    /**
     * Process import in batches.
      *
      * @param array $data Data to import.
      * @param array $args Arguments.
      * @return string Batch ID.
      */
     public function start_import_batch( $data, $args = array() ) {
         $this->ensure_table_exists();

         $batch_id = 'import_' . uniqid();

         ld_log( 'batch', 'Starting import batch: ' . $batch_id );

         $batch_data = array(
             'batch_id' => $batch_id,
             'type' => 'import',
             'status' => 'processing',
             'data' => wp_json_encode( $data ),
             'args' => wp_json_encode( $args ),
         );

         $save_result = $this->save_batch( $batch_data );

         if ( false === $save_result ) {
             ld_log( 'batch', 'Failed to save batch, aborting: ' . $batch_id, 'error' );
             return false;
         }

         // Process immediately instead of scheduling
         ld_log( 'batch', 'Processing batch immediately: ' . $batch_id );
         $this->process_batch( $batch_id );

         return $batch_id;
     }

    /**
     * Process a batch.
      *
      * @param string $batch_id Batch ID.
      */
     public function process_batch( $batch_id ) {
         ld_log( 'batch', 'Processing batch: ' . $batch_id );

         $batch = $this->get_batch( $batch_id );

         if ( ! $batch || 'completed' === $batch['status'] ) {
             ld_log( 'batch', 'Batch already processed or not found: ' . $batch_id );
             return;
         }

         if ( 'export' === $batch['type'] ) {
             $this->process_export_batch( $batch );
         } elseif ( 'import' === $batch['type'] ) {
             $this->process_import_batch( $batch );
         }
     }

    /**
     * Process export batch.
     *
     * @param array $batch Batch data.
     */
    private function process_export_batch( $batch ) {
        $args = json_decode( $batch['args'], true );
        $args = is_array( $args ) ? $args : array();
        
        $exporter = new LD_Exporter();
        $data = $exporter->export( array_merge( $args, array( 'offset' => $batch['offset_val'] ) ) );

        // Update batch.
        $batch['offset_val'] += $batch['limit_val'];
        $batch['status'] = 'completed'; // For now, assume one batch.

        $this->update_batch( $batch );
    }

    /**
     * Process import batch.
     *
     * @param array $batch Batch data.
     */
    private function process_import_batch( $batch ) {
        $data = json_decode( $batch['data'], true );
        $args = json_decode( $batch['args'], true );
        
        $data = is_array( $data ) ? $data : array();
        $args = is_array( $args ) ? $args : array();
        
        $importer = new LD_Importer();
        $result = $importer->import( $data, $args );

        $batch['status'] = 'completed';

        $this->update_batch( $batch );
    }

    /**
     * Save batch to database.
      *
      * @param array $batch Batch data.
      */
     private function save_batch( $batch ) {
         global $wpdb;

         $table = $wpdb->prefix . 'ld_export_import_batches';

         // Check if table exists
         if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
             ld_log( 'batch', 'Batch table does not exist: ' . $table, 'error' );
             return false;
         }

         $result = $wpdb->insert( $table, $batch ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

         if ( false === $result ) {
             ld_log( 'batch', 'Failed to save batch: ' . $wpdb->last_error, 'error' );
         } else {
             ld_log( 'batch', 'Batch saved successfully: ' . $batch['batch_id'] );
         }

         return $result;
     }

    /**
     * Get batch from database.
      *
      * @param string $batch_id Batch ID.
      * @return array|null Batch data.
      */
     public function get_batch( $batch_id ) {
         $this->ensure_table_exists();

         global $wpdb;

         $table = $wpdb->prefix . 'ld_export_import_batches';

         $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE batch_id = %s", $batch_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

         if ( null === $row ) {
             ld_log( 'batch', 'Batch not found in database: ' . $batch_id, 'error' );
         } else {
             ld_log( 'batch', 'Batch retrieved successfully: ' . $batch_id . ' (status: ' . $row['status'] . ')' );
         }

         return $row;
     }

    /**
     * Update batch in database.
     *
     * @param array $batch Batch data.
     */
    private function update_batch( $batch ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ld_export_import_batches';

        $wpdb->update( $table, $batch, array( 'batch_id' => $batch['batch_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}