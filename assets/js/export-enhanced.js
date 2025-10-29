/**
 * Enhanced Export JavaScript functionality
 * 
 * @since 2.0.0
 * @package Learndash_Export_Import
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Tab switching functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Bulk selection functionality
    var selectedCourses = [];
    var exportSession = null;
    var exportInterval = null;
    var startTime = null;
    
    // Select/Deselect all courses
    $('#select-all-courses').on('click', function() {
        $('.course-checkbox').prop('checked', true).trigger('change');
    });
    
    $('#deselect-all-courses').on('click', function() {
        $('.course-checkbox').prop('checked', false).trigger('change');
    });
    
    // Course selection change handler
    $('.course-checkbox').on('change', function() {
        updateSelectedCourses();
    });
    
    function updateSelectedCourses() {
        selectedCourses = [];
        $('.course-checkbox:checked').each(function() {
            selectedCourses.push($(this).val());
        });
        
        // Update UI
        $('.selected-count').text(selectedCourses.length + ' courses selected');
        $('#start-bulk-export').prop('disabled', selectedCourses.length === 0);
    }
    
    // Single course export
    $('#ld-single-export-form').on('submit', function(e) {
        var courseId = $('#course_id').val();
        if (!courseId) {
            e.preventDefault();
            alert('Please select a course to export.');
            return;
        }
        
        // Add export options to the form before submission
        var options = getExportOptions();
        var form = $(this);
        
        // Remove any existing option inputs
        form.find('input[name^="include_"], input[name="preserve_serialized"], input[name="chunk_size"]').remove();
        
        // Add option inputs
        for (var key in options) {
            if (options.hasOwnProperty(key)) {
                $('<input>').attr({
                    type: 'hidden',
                    name: key,
                    value: options[key]
                }).appendTo(form);
            }
        }
        
        // Form will submit normally to trigger download
    });
    
    // Bulk export
    $('#ld-bulk-export-form').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedCourses.length === 0) {
            alert('Please select at least one course to export.');
            return;
        }
        
        var options = getExportOptions();
        startBulkExport(selectedCourses, options);
    });
    
    function getExportOptions() {
        return {
            include_elementor: $('input[name="include_elementor"]').prop('checked'),
            preserve_serialized: $('input[name="preserve_serialized"]').prop('checked'),
            include_certificates: $('input[name="include_certificates"]').prop('checked'),
            include_quiz_questions: $('input[name="include_quiz_questions"]').prop('checked'),
            include_taxonomies: $('input[name="include_taxonomies"]').prop('checked'),
            chunk_size: $('select[name="chunk_size"]').val()
        };
    }
    
    function startBulkExport(courseIds, options) {
        // Initialize bulk export
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ld_initialize_bulk_export',
                course_ids: courseIds,
                options: options,
                nonce: $('input[name="bulk_export_nonce"]').val()
            },
            beforeSend: function() {
                showProgressContainer('Bulk Export');
                updateProgress(0, courseIds.length, 0, 'Initializing bulk export...');
            },
            success: function(response) {
                if (response.success) {
                    exportSession = response.data.session_id;
                    startExportProcessing();
                } else {
                    alert('Bulk export initialization failed: ' + response.data);
                    hideProgressContainer();
                }
            },
            error: function() {
                alert('Bulk export initialization failed. Please try again.');
                hideProgressContainer();
            }
        });
    }
    
    function startExportProcessing() {
        startTime = Date.now();
        exportInterval = setInterval(checkExportProgress, 2000); // Check every 2 seconds
        processNextChunk(0);
    }
    
    function processNextChunk(chunkIndex) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ld_ajax_bulk_export_courses',
                session_id: exportSession,
                chunk_index: chunkIndex,
                nonce: $('input[name="bulk_export_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update progress
                    updateProgressFromResponse(data);
                    
                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        showWarnings(data.warnings);
                    }
                    
                    // Check if export is complete
                    checkExportCompletion();
                    
                } else {
                    handleExportError('Chunk processing failed: ' + response.data.message);
                }
            },
            error: function() {
                handleExportError('Network error during export processing.');
            }
        });
    }
    
    function checkExportProgress() {
        if (!exportSession) return;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ld_get_export_progress',
                session_id: exportSession,
                nonce: $('input[name="bulk_export_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    var progress = response.data;
                    
                    updateProgress(
                        progress.processed_courses,
                        progress.total_courses,
                        progress.progress_percentage,
                        progress.current_item || 'Processing...'
                    );
                    
                    // Check if complete
                    if (progress.progress_percentage >= 100) {
                        completeExport();
                    }
                }
            }
        });
    }
    
    function updateProgressFromResponse(data) {
        if (data.current_item) {
            $('#current-item').text(data.current_item);
        }
        
        // Update time
        updateElapsedTime();
    }
    
    function updateProgress(processed, total, percentage, currentItem) {
        $('#progress-courses').text(processed + '/' + total);
        $('#progress-percentage').text(Math.round(percentage) + '%');
        $('#progress-fill').css('width', percentage + '%');
        $('#current-item').text(currentItem);
        
        updateElapsedTime();
    }
    
    function updateElapsedTime() {
        if (!startTime) return;
        
        var elapsed = Date.now() - startTime;
        var minutes = Math.floor(elapsed / 60000);
        var seconds = Math.floor((elapsed % 60000) / 1000);
        
        $('#progress-time').text(
            (minutes < 10 ? '0' : '') + minutes + ':' +
            (seconds < 10 ? '0' : '') + seconds
        );
    }
    
    function showWarnings(warnings) {
        var warningsHtml = '';
        warnings.forEach(function(warning) {
            warningsHtml += '<li>' + escapeHtml(warning) + '</li>';
        });
        
        $('#warnings-list').html(warningsHtml);
        $('#export-warnings').show();
    }
    
    function checkExportCompletion() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ld_check_export_completion',
                session_id: exportSession,
                nonce: $('input[name="bulk_export_nonce"]').val()
            },
            success: function(response) {
                if (response.success && response.data.complete) {
                    completeExport();
                }
            }
        });
    }
    
    function completeExport() {
        if (exportInterval) {
            clearInterval(exportInterval);
            exportInterval = null;
        }
        
        // Generate download file
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ld_generate_export_file',
                session_id: exportSession,
                nonce: $('input[name="bulk_export_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    var fileData = response.data;
                    showExportComplete(fileData);
                } else {
                    handleExportError('Failed to generate export file: ' + response.data);
                }
            },
            error: function() {
                handleExportError('Failed to generate export file.');
            }
        });
    }
    
    function showExportComplete(fileData) {
        $('#export-summary').text(
            'Successfully exported ' + fileData.courses_exported + ' courses.'
        );
        
        $('#download-link')
            .attr('href', fileData.file_url)
            .attr('download', fileData.filename)
            .show();
        
        $('.file-size').text(fileData.file_size_mb + ' MB');
        $('.courses-count').text(fileData.courses_exported + ' courses');
        $('#file-info').show();
        
        $('#export-complete').show();
        $('.progress-controls').hide();
    }
    
    function handleExportError(message) {
        if (exportInterval) {
            clearInterval(exportInterval);
            exportInterval = null;
        }
        
        alert('Export Error: ' + message);
        hideProgressContainer();
    }
    
    function showProgressContainer(title) {
        $('#progress-title').text(title);
        $('#export-progress-container').show();
        
        // Hide form tabs
        $('.ld-export-tabs').hide();
        
        // Reset progress state
        $('#export-warnings').hide();
        $('#export-complete').hide();
        $('.progress-controls').show();
        $('#pause-export').show();
        $('#resume-export').hide();
    }
    
    function hideProgressContainer() {
        $('#export-progress-container').hide();
        $('.ld-export-tabs').show();
        
        // Reset session
        exportSession = null;
        startTime = null;
        
        if (exportInterval) {
            clearInterval(exportInterval);
            exportInterval = null;
        }
    }
    
    // Progress control handlers
    var exportPaused = false;
    
    $('#pause-export').on('click', function() {
        exportPaused = true;
        if (exportInterval) {
            clearInterval(exportInterval);
            exportInterval = null;
        }
        
        $(this).hide();
        $('#resume-export').show();
        $('#current-item').text('Export paused...');
    });
    
    $('#resume-export').on('click', function() {
        exportPaused = false;
        exportInterval = setInterval(checkExportProgress, 2000);
        
        $(this).hide();
        $('#pause-export').show();
        $('#current-item').text('Resuming export...');
    });
    
    $('#cancel-export').on('click', function() {
        if (confirm('Are you sure you want to cancel the export?')) {
            cancelExport();
        }
    });
    
    function cancelExport() {
        if (exportSession) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ld_cancel_export',
                    session_id: exportSession,
                    nonce: $('input[name="bulk_export_nonce"]').val()
                }
            });
        }
        
        hideProgressContainer();
    }
    
    // Utility function
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Initialize UI state
    updateSelectedCourses();
});
