<?php
/**
 * Logs page view.
 *
 * @since      1.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/admin/views
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div class="wrap">
    <h1><?php esc_html_e( 'LearnDash Export/Import Logs', 'learndash-export-import' ); ?></h1>

    <p class="submit">
        <button type="button" class="button button-secondary" id="ld-clear-logs">
            <?php esc_html_e( 'Delete Logs', 'learndash-export-import' ); ?>
        </button>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Type', 'learndash-export-import' ); ?></th>
                <th><?php esc_html_e( 'Message', 'learndash-export-import' ); ?></th>
                <th><?php esc_html_e( 'Status', 'learndash-export-import' ); ?></th>
                <th><?php esc_html_e( 'Date', 'learndash-export-import' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $logs ) : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->type ); ?></td>
                        <td><?php echo esc_html( $log->message ); ?></td>
                        <td><?php echo esc_html( $log->status ); ?></td>
                        <td><?php echo esc_html( $log->created_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e( 'No logs found.', 'learndash-export-import' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="ld-logs-pagination">
            <div class="tablenav-pages">
                <?php
                $pagination_args = array(
                    'base' => add_query_arg( 'paged', '%#%' ),
                    'format' => '',
                    'prev_text' => __( '&laquo; Previous', 'learndash-export-import' ),
                    'next_text' => __( 'Next &raquo;', 'learndash-export-import' ),
                    'total' => $total_pages,
                    'current' => $current_page,
                );
                echo paginate_links( $pagination_args );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>