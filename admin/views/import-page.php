<?php
/**
 * Import page view.
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
    <h1><?php esc_html_e( 'LearnDash Import', 'learndash-export-import' ); ?></h1>

    <form id="ld-import-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'ld_import_nonce', 'nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Import File', 'learndash-export-import' ); ?></th>
                <td>
                    <input type="file" name="import_file" accept=".json" required />
                    <p class="description"><?php esc_html_e( 'Select a JSON file exported from LearnDash.', 'learndash-export-import' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Duplicate Handling', 'learndash-export-import' ); ?></th>
                <td>
                    <select name="duplicate_handling">
                        <option value="create_new"><?php esc_html_e( 'Create New', 'learndash-export-import' ); ?></option>
                        <option value="skip"><?php esc_html_e( 'Skip', 'learndash-export-import' ); ?></option>
                        <option value="overwrite"><?php esc_html_e( 'Overwrite', 'learndash-export-import' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Start Import', 'learndash-export-import' ); ?></button>
        </p>
    </form>

    <div id="import-progress" style="display:none;">
        <h3><?php esc_html_e( 'Import Progress', 'learndash-export-import' ); ?></h3>
        <div class="progress-bar">
            <div class="progress-fill" style="width:0%;"></div>
        </div>
        <p id="progress-text"><?php esc_html_e( 'Initializing...', 'learndash-export-import' ); ?></p>
    </div>

    <div class="ld-delete-section" style="margin-top: 50px; padding: 20px; border: 1px solid #ccc; background: #fff;">
        <h2><?php esc_html_e( 'Delete All LearnDash Data', 'learndash-export-import' ); ?></h2>
        <p><?php esc_html_e( 'This will permanently delete all LearnDash courses, lessons, quizzes, questions, certificates, and other related data. Settings will be preserved.', 'learndash-export-import' ); ?></p>
        <p><strong><?php esc_html_e( 'Warning: This action cannot be undone. Please backup your data before proceeding.', 'learndash-export-import' ); ?></strong></p>
        <button type="button" id="ld-delete-all-data" class="button button-secondary" style="background: #dc3232; color: #fff; border-color: #dc3232;">
            <?php esc_html_e( 'Delete All LearnDash Data', 'learndash-export-import' ); ?>
        </button>
        <div id="delete-progress" style="display:none; margin-top: 20px;">
            <div class="progress-bar">
                <div class="progress-fill" style="width:0%; background: #dc3232;"></div>
            </div>
            <p id="delete-progress-text"><?php esc_html_e( 'Deleting...', 'learndash-export-import' ); ?></p>
        </div>
    </div>
</div>