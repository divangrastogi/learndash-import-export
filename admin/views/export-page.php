<?php
/**
 * Enhanced Export page view with Elementor support and bulk operations.
 *
 * @since      2.0.0
 * @package    Learndash_Export_Import
 * @subpackage Learndash_Export_Import/admin/views
 * @author     WBCom Designs <admin@wbcomdesigns.com>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Get all courses for bulk selection
$courses = get_posts( array(
    'post_type' => 'sfwd-courses',
    'posts_per_page' => -1,
    'post_status' => 'any',
    'orderby' => 'title',
    'order' => 'ASC'
) );

// Check if Elementor is active
$elementor_active = defined( 'ELEMENTOR_VERSION' );
?>

<div class="wrap ld-export-enhanced">
    <h1><?php esc_html_e( 'LearnDash Export - Enhanced', 'learndash-export-import' ); ?></h1>
    
    <div class="ld-export-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#single-course" class="nav-tab nav-tab-active"><?php esc_html_e( 'Single Course', 'learndash-export-import' ); ?></a>
            <a href="#bulk-export" class="nav-tab"><?php esc_html_e( 'Bulk Export', 'learndash-export-import' ); ?></a>
            <a href="#export-settings" class="nav-tab"><?php esc_html_e( 'Export Settings', 'learndash-export-import' ); ?></a>
        </nav>
        
        <!-- Single Course Export Tab -->
        <div id="single-course" class="tab-content active">
            <h2><?php esc_html_e( 'Export Single Course', 'learndash-export-import' ); ?></h2>
            
            <form id="ld-single-export-form" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <input type="hidden" name="action" value="ld_single_export_download">
                <?php wp_nonce_field( 'ld_export_nonce', 'export_nonce' ); ?>
                <input type="hidden" name="export_type" value="single">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="course_id"><?php esc_html_e( 'Select Course', 'learndash-export-import' ); ?></label>
                        </th>
                        <td>
                            <select name="course_id" id="course_id" class="regular-text" required>
                                <option value=""><?php esc_html_e( '-- Select Course --', 'learndash-export-import' ); ?></option>
                                <?php foreach ( $courses as $course ) : ?>
                                    <option value="<?php echo esc_attr( $course->ID ); ?>">
                                        <?php echo esc_html( $course->post_title ); ?>
                                        (ID: <?php echo esc_html( $course->ID ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e( 'Choose a course to export with all its content.', 'learndash-export-import' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Export Course', 'learndash-export-import' ); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Bulk Export Tab -->
        <div id="bulk-export" class="tab-content">
            <h2><?php esc_html_e( 'Bulk Course Export', 'learndash-export-import' ); ?></h2>
            
            <form id="ld-bulk-export-form" method="post">
                <?php wp_nonce_field( 'ld_bulk_export', 'bulk_export_nonce' ); ?>
                <input type="hidden" name="export_type" value="bulk">
                
                <div class="bulk-selection-container">
                    <div class="selection-controls">
                        <button type="button" id="select-all-courses" class="button">
                            <?php esc_html_e( 'Select All', 'learndash-export-import' ); ?>
                        </button>
                        <button type="button" id="deselect-all-courses" class="button">
                            <?php esc_html_e( 'Deselect All', 'learndash-export-import' ); ?>
                        </button>
                        <span class="selected-count">0 <?php esc_html_e( 'courses selected', 'learndash-export-import' ); ?></span>
                    </div>
                    
                    <div class="courses-selection-grid">
                        <?php if ( ! empty( $courses ) ) : ?>
                            <?php foreach ( $courses as $course ) : 
                                $lesson_count = count( get_posts( array(
                                    'post_type' => 'sfwd-lessons',
                                    'meta_query' => array( array( 'key' => 'course_id', 'value' => $course->ID ) ),
                                    'posts_per_page' => -1,
                                    'fields' => 'ids'
                                ) ) );
                                
                                $has_elementor = get_post_meta( $course->ID, '_elementor_edit_mode', true ) === 'builder';
                            ?>
                                <div class="course-selection-item">
                                    <label>
                                        <input type="checkbox" name="selected_courses[]" value="<?php echo esc_attr( $course->ID ); ?>" class="course-checkbox">
                                        <div class="course-info">
                                            <h4><?php echo esc_html( $course->post_title ); ?></h4>
                                            <div class="course-meta">
                                                <span class="course-id">ID: <?php echo esc_html( $course->ID ); ?></span>
                                                <span class="lesson-count"><?php echo esc_html( $lesson_count ); ?> lessons</span>
                                                <?php if ( $has_elementor ) : ?>
                                                    <span class="elementor-badge"><?php esc_html_e( 'Elementor', 'learndash-export-import' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p><?php esc_html_e( 'No courses found.', 'learndash-export-import' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="start-bulk-export" disabled>
                        <?php esc_html_e( 'Start Bulk Export', 'learndash-export-import' ); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <!-- Export Settings Tab -->
        <div id="export-settings" class="tab-content">
            <h2><?php esc_html_e( 'Export Configuration', 'learndash-export-import' ); ?></h2>
            
            <?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Export settings saved successfully.', 'learndash-export-import' ); ?></p>
                </div>
            <?php endif; ?>
            
            <form id="ld-export-settings-form" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <input type="hidden" name="action" value="ld_export_settings_save">
                <?php wp_nonce_field( 'ld_export_settings', 'export_settings_nonce' ); ?>
                
                <table class="form-table export-settings">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Elementor Content', 'learndash-export-import' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="include_elementor" value="1" checked <?php echo ! $elementor_active ? 'disabled' : ''; ?>>
                                    <?php esc_html_e( 'Include Elementor page builder content', 'learndash-export-import' ); ?>
                                </label>
                                <?php if ( ! $elementor_active ) : ?>
                                    <p class="description error">
                                        <?php esc_html_e( 'Elementor is not installed or active.', 'learndash-export-import' ); ?>
                                    </p>
                                <?php else : ?>
                                    <p class="description">
                                        <?php esc_html_e( 'Exports Elementor page designs, widgets, and settings.', 'learndash-export-import' ); ?>
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Serialized Data', 'learndash-export-import' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="preserve_serialized" value="1" checked>
                                    <?php esc_html_e( 'Preserve serialized data integrity', 'learndash-export-import' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'Maintains exact serialization for complex WordPress data structures.', 'learndash-export-import' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Course Content', 'learndash-export-import' ); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="include_certificates" value="1" checked>
                                    <?php esc_html_e( 'Include certificates', 'learndash-export-import' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="include_quiz_questions" value="1" checked>
                                    <?php esc_html_e( 'Include quiz questions and settings', 'learndash-export-import' ); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="include_taxonomies" value="1" checked>
                                    <?php esc_html_e( 'Include taxonomies and terms', 'learndash-export-import' ); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Processing Options', 'learndash-export-import' ); ?></th>
                        <td>
                            <fieldset>
                                <label for="chunk_size"><?php esc_html_e( 'Chunk Size:', 'learndash-export-import' ); ?></label>
                                <select name="chunk_size" id="chunk_size">
                                    <option value="auto"><?php esc_html_e( 'Auto (Recommended)', 'learndash-export-import' ); ?></option>
                                    <option value="1">1 <?php esc_html_e( 'course per chunk', 'learndash-export-import' ); ?></option>
                                    <option value="2">2 <?php esc_html_e( 'courses per chunk', 'learndash-export-import' ); ?></option>
                                    <option value="5">5 <?php esc_html_e( 'courses per chunk', 'learndash-export-import' ); ?></option>
                                    <option value="10">10 <?php esc_html_e( 'courses per chunk', 'learndash-export-import' ); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Auto mode adjusts chunk size based on content complexity and server resources.', 'learndash-export-import' ); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Settings', 'learndash-export-import' ); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Progress Section -->
    <div id="export-progress-container" style="display:none;">
        <div class="export-progress-wrapper">
            <h3 id="progress-title"><?php esc_html_e( 'Export Progress', 'learndash-export-import' ); ?></h3>
            
            <div class="progress-info">
                <div class="progress-stats">
                    <span id="progress-courses">0/0</span> <?php esc_html_e( 'courses', 'learndash-export-import' ); ?> |
                    <span id="progress-percentage">0%</span> <?php esc_html_e( 'complete', 'learndash-export-import' ); ?> |
                    <span id="progress-time">--:--</span> <?php esc_html_e( 'elapsed', 'learndash-export-import' ); ?>
                </div>
                <div class="progress-current">
                    <?php esc_html_e( 'Current:', 'learndash-export-import' ); ?> <span id="current-item">--</span>
                </div>
            </div>
            
            <div class="progress-bar-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width:0%;"></div>
                </div>
            </div>
            
            <div class="progress-controls">
                <button type="button" id="pause-export" class="button"><?php esc_html_e( 'Pause', 'learndash-export-import' ); ?></button>
                <button type="button" id="resume-export" class="button" style="display:none;"><?php esc_html_e( 'Resume', 'learndash-export-import' ); ?></button>
                <button type="button" id="cancel-export" class="button button-secondary"><?php esc_html_e( 'Cancel', 'learndash-export-import' ); ?></button>
            </div>
            
            <div id="export-warnings" class="export-warnings" style="display:none;">
                <h4><?php esc_html_e( 'Warnings', 'learndash-export-import' ); ?></h4>
                <ul id="warnings-list"></ul>
            </div>
            
            <div id="export-complete" class="export-complete" style="display:none;">
                <h4><?php esc_html_e( 'Export Complete!', 'learndash-export-import' ); ?></h4>
                <p id="export-summary"></p>
                <div class="download-section">
                    <a href="#" id="download-link" class="button button-primary" style="display:none;">
                        <?php esc_html_e( 'Download Export File', 'learndash-export-import' ); ?>
                    </a>
                    <div class="file-info" id="file-info" style="display:none;">
                        <span class="file-size"></span> | 
                        <span class="courses-count"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ld-export-enhanced .nav-tab-wrapper {
    margin-bottom: 20px;
}

.ld-export-enhanced .tab-content {
    display: none;
}

.ld-export-enhanced .tab-content.active {
    display: block;
}

.bulk-selection-container {
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
    background: #f9f9f9;
}

.selection-controls {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #ddd;
}

.selection-controls .button {
    margin-right: 10px;
}

.selected-count {
    font-weight: bold;
    color: #0073aa;
}

.courses-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
    max-height: 400px;
    overflow-y: auto;
}

.course-selection-item {
    border: 1px solid #ddd;
    padding: 15px;
    background: white;
    border-radius: 4px;
    transition: all 0.2s;
}

.course-selection-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.course-selection-item label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    margin: 0;
}

.course-selection-item input[type="checkbox"] {
    margin: 5px 10px 0 0;
}

.course-info h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    line-height: 1.3;
}

.course-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 12px;
    color: #666;
}

.course-meta span {
    padding: 2px 6px;
    background: #f0f0f0;
    border-radius: 3px;
}

.elementor-badge {
    background: #9b0a46 !important;
    color: white !important;
}

.export-settings .description.error {
    color: #d63638;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: #f0f0f0;
    border-radius: 4px;
}

.progress-stats {
    font-weight: bold;
}

.progress-current {
    font-style: italic;
    color: #666;
}

.progress-bar-container {
    margin-bottom: 20px;
}

.progress-bar {
    width: 100%;
    height: 25px;
    background: #f0f0f0;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ddd;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #005a87);
    transition: width 0.3s ease;
    position: relative;
}

.progress-controls {
    margin-bottom: 20px;
}

.progress-controls .button {
    margin-right: 10px;
}

.export-warnings {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.export-warnings h4 {
    margin-top: 0;
    color: #856404;
}

.export-warnings ul {
    margin: 0;
    color: #856404;
}

.export-complete {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    padding: 20px;
    border-radius: 4px;
    text-align: center;
}

.export-complete h4 {
    margin-top: 0;
    color: #155724;
}

.download-section {
    margin-top: 15px;
}

.file-info {
    margin-top: 10px;
    font-size: 14px;
    color: #666;
}
</style>