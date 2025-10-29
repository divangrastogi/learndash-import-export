# LearnDash Export & Import

A comprehensive tool to export and import LearnDash courses, lessons, topics, quizzes, and related data while maintaining relational integrity.

## Description

The LearnDash Export & Import plugin provides a complete solution for migrating LearnDash content between WordPress sites. It preserves all course relationships, quiz settings, user progress tracking, and custom metadata during export/import operations.

## Features

- **Complete Course Export**: Export entire courses including lessons, topics, quizzes, and certificates
- **Bulk Export**: Export multiple courses simultaneously with progress tracking
- **Relational Integrity**: Maintains all LearnDash relationships and dependencies
- **Flexible Import**: Import courses with duplicate handling options
- **Batch Processing**: Handles large imports efficiently with progress monitoring
- **Elementor Integration**: Optional support for Elementor page builder data
- **Certificate Support**: Export and import course certificates
- **Quiz Questions**: Include quiz questions and answers in exports
- **Taxonomy Preservation**: Maintain categories and tags
- **Comprehensive Logging**: Detailed logs for troubleshooting
- **Testing Tools**: Built-in test utilities for validation

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 8.0 or higher
- **LearnDash**: 4.0 or higher
- **Memory**: 512MB minimum recommended for large exports

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Click "Install Now" and then "Activate"

## Usage

### Export

1. Navigate to **LearnDash Export & Import** in the WordPress admin menu
2. Click on the **Export** tab
3. Select courses to export
4. Configure export options:
   - Include Elementor data
   - Preserve serialized data
   - Include certificates
   - Include quiz questions
   - Include taxonomies
   - Set chunk size (auto/manual)
5. Click "Export Selected Courses"
6. Download the generated JSON file

### Import

1. Click on the **Import** tab
2. Upload your JSON export file
3. Choose duplicate handling method:
   - Create new (recommended)
   - Update existing
   - Skip duplicates
4. Click "Start Import"
5. Monitor progress and review results

### Bulk Operations

The plugin supports bulk export for multiple courses with real-time progress tracking and resumable operations.

## Recent Fixes (v1.0.0)

### Critical Bug Fixes

**Issue #1 - Fatal Error During Import**
- **Problem**: Missing `handle_batch_status_ajax` method caused fatal errors during batch import status checks
- **Fix**: Added the missing AJAX handler method to `admin/class-ld-admin-ui.php`
- **Impact**: Import operations now complete successfully without fatal errors

**Issue #2 - Imported Courses Not Displaying**
- **Problem**: Imported courses wouldn't display properly due to missing `ld_course_steps` meta data
- **Fix**: Added `rebuild_course_structure()` method in `includes/class-ld-importer.php` to properly rebuild course relationships
- **Impact**: Imported courses now display correctly with full navigation and lesson structure

### Files Modified
- `admin/class-ld-admin-ui.php` - Added AJAX handler for batch status checks
- `includes/class-ld-importer.php` - Enhanced import logic with structure rebuilding

### Testing
Run validation scripts to ensure imports work correctly:
```bash
wp eval-file validate-import.php
wp eval-file test-import-cli.php
```

## Support

For support and bug reports, please contact WBCom Designs at admin@wbcomdesigns.com

## Changelog

### v1.0.0
- Initial release with complete export/import functionality
- Fixed critical import bugs
- Added comprehensive logging and testing tools
- Elementor integration support
- Batch processing for large operations

## License

GPL-2.0+ - See license file for details

## Author

**WBCom Designs**
- Website: https://wbcomdesigns.com/
- Email: admin@wbcomdesigns.com
