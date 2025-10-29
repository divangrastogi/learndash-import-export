# LearnDash Export & Import User Guide

This guide provides detailed instructions for using the LearnDash Export & Import plugin to migrate courses between WordPress sites.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Exporting Courses](#exporting-courses)
3. [Importing Courses](#importing-courses)
4. [Bulk Operations](#bulk-operations)
5. [Export Options](#export-options)
6. [Troubleshooting](#troubleshooting)
7. [Testing Tools](#testing-tools)
8. [Logs and Monitoring](#logs-and-monitoring)

## Getting Started

### Prerequisites

Ensure your WordPress installation meets these requirements:
- WordPress 5.0+
- PHP 8.0+
- LearnDash 4.0+
- At least 512MB memory for large exports

### Plugin Installation

1. Download the plugin from your source
2. Go to WordPress Admin → Plugins → Add New
3. Upload the plugin zip file
4. Activate the plugin

The plugin will appear as "LD Export/Import" in your admin menu.

## Exporting Courses

### Single Course Export

1. Navigate to **LearnDash Export & Import → Export**
2. Select a single course from the dropdown
3. Configure your export options (see [Export Options](#export-options))
4. Click **"Export Course"**
5. The JSON file will download automatically

### Bulk Course Export

1. Go to the Export tab
2. Check the boxes next to multiple courses
3. Configure export options
4. Click **"Export Selected Courses"**
5. Monitor the progress bar
6. Download the combined JSON file when complete

### Export Options

#### Core Settings
- **Include Elementor Data**: Export Elementor page builder content and settings
- **Preserve Serialized Data**: Maintain complex serialized metadata
- **Include Certificates**: Export course certificate templates and settings
- **Include Quiz Questions**: Export quiz questions, answers, and settings
- **Include Taxonomies**: Preserve categories, tags, and custom taxonomies

#### Performance Settings
- **Chunk Size**: Control processing batch size (auto/manual)

## Importing Courses

### Basic Import Process

1. Navigate to **LearnDash Export & Import → Import**
2. Click **"Choose File"** and select your JSON export file
3. Choose duplicate handling method:
   - **Create New**: Import as new content (recommended)
   - **Update Existing**: Update existing courses with same IDs
   - **Skip Duplicates**: Skip content that already exists
4. Click **"Start Import"**
5. Monitor the progress and wait for completion

### Batch Import Monitoring

The import process uses batch processing for reliability:
- Progress is shown as a percentage
- You can monitor real-time status
- Large imports are processed in chunks
- The process is resumable if interrupted

### Post-Import Validation

After import completion:
1. Check the **Logs** tab for any errors or warnings
2. Verify course structure in LearnDash admin
3. Test course access and navigation
4. Review quiz settings and questions

## Bulk Operations

### Bulk Export Features

- Select multiple courses simultaneously
- Real-time progress tracking
- Automatic chunking for large datasets
- Resumable operations
- Combined output file

### Performance Considerations

For large bulk operations:
- Increase PHP memory limit to 512MB+
- Ensure adequate server timeout settings
- Monitor server resources during processing
- Use chunk size settings appropriately

## Export Options Detailed

### Include Elementor Data
Exports Elementor page builder templates, widgets, and settings associated with courses and lessons. This ensures visual layouts are preserved during migration.

### Preserve Serialized Data
Maintains complex LearnDash metadata that uses PHP serialization. This includes:
- Course progress tracking data
- Quiz attempt histories
- User enrollment information

### Include Certificates
Exports certificate templates and their settings:
- Certificate designs
- Dynamic content fields
- Completion requirements

### Include Quiz Questions
Exports complete quiz structure:
- Question content and settings
- Answer options
- Point values and grading
- Question categories

### Include Taxonomies
Preserves organizational structure:
- Course categories
- Tags
- Custom taxonomies
- Hierarchical relationships

## Troubleshooting

### Common Issues

#### Import Fails with Fatal Error
**Symptom**: Import stops with a fatal error message
**Solution**:
1. Check PHP error logs
2. Ensure adequate memory allocation
3. Verify LearnDash version compatibility
4. Try importing in smaller batches

#### Courses Not Displaying After Import
**Symptom**: Imported courses exist but don't show in course lists
**Solution**:
1. Clear all caches (WP Rocket, W3 Total Cache, etc.)
2. Regenerate permalinks
3. Check course status (published/draft)
4. Verify course meta data integrity

#### Missing Course Relationships
**Symptom**: Lessons and topics not properly linked to courses
**Solution**:
1. Use the testing tools to validate structure
2. Check import logs for relationship errors
3. Manually rebuild course structure if needed

### Memory Issues
For large exports/imports:
```php
// Add to wp-config.php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '1024M');
```

### Timeout Issues
```php
// Add to wp-config.php
set_time_limit(300); // 5 minutes
```

## Testing Tools

The plugin includes comprehensive testing utilities:

### Access Testing Tools
1. Go to **LearnDash Export & Import → Tests**
2. Run available test scripts

### Validation Scripts
Run these WP-CLI commands for testing:

```bash
# Validate import functionality
wp eval-file validate-import.php

# Test CLI import process
wp eval-file test-import-cli.php

# Test memory-safe import
wp eval-file test-memory-safe-import.php
```

### Test Results
- Review test output for errors
- Check logs for detailed information
- Use results to troubleshoot issues

## Logs and Monitoring

### Viewing Logs
1. Go to **LearnDash Export & Import → Logs**
2. Filter by operation type (export/import/delete)
3. Search for specific entries
4. Clear logs when needed

### Log Information
Logs contain:
- Operation timestamps
- Success/failure status
- Error messages and stack traces
- Performance metrics
- User actions

### Log Management
- Logs are paginated (50 entries per page)
- Automatic cleanup of old entries
- Export log data for analysis
- Clear logs to reduce database size

## Advanced Usage

### CLI Operations
For automated operations, use WP-CLI:

```bash
# Export courses via CLI
wp learndash export --course-ids=1,2,3 --output=/path/to/export.json

# Import via CLI
wp learndash import --file=/path/to/export.json --duplicate-handling=create_new
```

### Custom Development
The plugin provides hooks for customization:

```php
// Filter export data before processing
add_filter('learndash_export_data', 'custom_export_filter', 10, 2);

// Modify import behavior
add_action('learndash_import_course', 'custom_import_action', 10, 2);
```

### Database Cleanup
Use the **Delete All Data** feature carefully:
1. Backup your database first
2. Go to Tests tab
3. Click "Delete All LearnDash Data"
4. Confirm the operation

This removes all LearnDash content but preserves settings.

## Support and Resources

### Getting Help
- Check the logs for error details
- Run test scripts to identify issues
- Review WordPress and PHP error logs
- Contact support with specific error messages

### Best Practices
1. **Always backup** before major operations
2. **Test imports** on staging sites first
3. **Monitor resources** during large operations
4. **Use appropriate chunk sizes** for your server
5. **Clear caches** after imports
6. **Validate results** after each operation

### Performance Optimization
- Use bulk operations for multiple courses
- Configure appropriate chunk sizes
- Monitor server resources
- Schedule large operations during low-traffic periods
- Consider server upgrades for very large sites
