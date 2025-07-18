/**
 * Enhanced Autoload Manager Script
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Variables for DOM elements
        const expandButtons = $('.edal-button-expand');
        const modal = $('#option-value-modal');
        const importModal = $('#import-modal');
        const pre = $('#option-value-pre');
        const closeModal = $('.close');
        const deleteButtons = $('.edal-button-delete');
        const disableButtons = $('.edal-button-disable');
        const refreshButton = $('#edal-refresh-data');
        const exportButton = $('#edal-export-btn');
        const importButton = $('#edal-import-btn');
        const importFileInput = $('#edal-import-file');
        const importSubmitButton = $('#import-submit');
        const importFileDisplay = $('#import-file-input');
        const statusMessage = $('#edal-status-message');
        const dismissButton = $('.edal-notice .notice-dismiss');
        const notice = $('.edal-notice');

        // Initialize event listeners
        initializeListeners();

        /**
         * Initialize all event listeners
         */
        function initializeListeners() {
            // Option value expansion
            expandButtons.on('click', handleExpandClick);
            
            // Modal close actions
            closeModal.on('click', closeAllModals);
            $(window).on('click', handleWindowClick);
            
            // Notice dismissal with AJAX
            if (dismissButton.length) {
                dismissButton.on('click', function() {
                    const $notice = $(this).closest('.edal-notice');
                    const warningType = $notice.data('dismiss-type');
                    
                    if (warningType) {
                        // Send AJAX request to dismiss warning permanently
                        $.ajax({
                            url: edal_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'edal_dismiss_warning',
                                nonce: edal_ajax.nonce,
                                warning_type: warningType
                            },
                            success: function(response) {
                                if (response.success) {
                                    $notice.fadeOut();
                                }
                            },
                            error: function() {
                                // Still hide the notice even if AJAX fails
                                $notice.fadeOut();
                            }
                        });
                    } else {
                        // Fallback for notices without dismiss type
                        $notice.fadeOut();
                    }
                });
            }
            
            // Add confirmation dialog to delete buttons
            deleteButtons.on('click', function(e) {
                if (!confirm(edal_ajax.confirm_delete)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Add confirmation dialog to disable buttons
            disableButtons.on('click', function(e) {
                if (!confirm(edal_ajax.confirm_disable)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Refresh data button
            refreshButton.on('click', handleRefreshClick);
            
            // Export button
            exportButton.on('click', handleExportClick);
            
            // Import buttons
            importButton.on('click', function() {
                importModal.show();
            });
            
            // Import submission
            importSubmitButton.on('click', handleImportSubmission);
        }

        /**
         * Handle expand button click
         */
        function handleExpandClick(event) {
            event.preventDefault();
            const optionValue = $(this).data('option');
            pre.text(optionValue);
            modal.show();
        }

        /**
         * Close all modals
         */
        function closeAllModals() {
            modal.hide();
            importModal.hide();
        }

        /**
         * Handle clicks outside modals
         */
        function handleWindowClick(event) {
            if ($(event.target).is(modal) || $(event.target).is(importModal)) {
                closeAllModals();
            }
        }

        /**
         * Handle refresh button click
         */
        function handleRefreshClick() {
            const button = $(this);
            const originalText = button.html();
            const nonce = button.data('nonce');
            
            // Show loading state
            button.html('<span class="dashicons dashicons-update edal-spin"></span> ' + 
                       'Refreshing...');
            button.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: edal_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'edal_refresh_data',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update the total size display
                        $('.edal-total-size').text('The total autoload size is ' + 
                                                 response.data.total_size_mb + ' MB.');
                        
                        // Show success message
                        statusMessage.text(response.data.message)
                                   .addClass('edal-success-message')
                                   .removeClass('edal-error-message')
                                   .show();
                        
                        // Hide message after 3 seconds
                        setTimeout(function() {
                            statusMessage.fadeOut();
                        }, 3000);
                        
                        // Reload the page to refresh the data display
                        window.location.reload();
                    } else {
                        // Show error message
                        statusMessage.text(response.data.message)
                                   .addClass('edal-error-message')
                                   .removeClass('edal-success-message')
                                   .show();
                    }
                },
                error: function() {
                    statusMessage.text('An error occurred. Please try again.')
                               .addClass('edal-error-message')
                               .removeClass('edal-success-message')
                               .show();
                },
                complete: function() {
                    // Restore button state
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            });
        }

        /**
         * Handle export button click
         */
        function handleExportClick() {
            const button = $(this);
            const originalText = button.html();
            
            // Show loading state
            button.html('<span class="dashicons dashicons-download edal-spin"></span> ' + 
                       'Exporting...');
            button.prop('disabled', true);
            
            // Send AJAX request
            $.ajax({
                url: edal_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'edal_export_settings',
                    nonce: edal_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create a downloadable file
                        const dataStr = "data:text/json;charset=utf-8," + 
                                      encodeURIComponent(JSON.stringify(response.data.export_data));
                        const downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", response.data.filename);
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                        
                        // Show success message
                        statusMessage.text('Export completed successfully.')
                                   .addClass('edal-success-message')
                                   .removeClass('edal-error-message')
                                   .show();
                        
                        // Hide message after 3 seconds
                        setTimeout(function() {
                            statusMessage.fadeOut();
                        }, 3000);
                    } else {
                        // Show error message
                        statusMessage.text(response.data.message)
                                   .addClass('edal-error-message')
                                   .removeClass('edal-success-message')
                                   .show();
                    }
                },
                error: function() {
                    statusMessage.text('An error occurred during export. Please try again.')
                               .addClass('edal-error-message')
                               .removeClass('edal-success-message')
                               .show();
                },
                complete: function() {
                    // Restore button state
                    button.html(originalText);
                    button.prop('disabled', false);
                }
            });
        }

        /**
         * Handle import submission
         */
        function handleImportSubmission() {
            const fileInput = document.getElementById('import-file-input');
            const importStatus = $('#import-status');
            
            if (!fileInput.files || fileInput.files.length === 0) {
                importStatus.text('Please select a file to import.')
                           .addClass('edal-error-message')
                           .removeClass('edal-success-message');
                return;
            }
            
            const file = fileInput.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const importData = e.target.result;
                    
                    // Send AJAX request
                    $.ajax({
                        url: edal_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'edal_import_settings',
                            nonce: edal_ajax.nonce,
                            import_data: importData
                        },
                        beforeSend: function() {
                            importStatus.text('Importing data...')
                                       .removeClass('edal-error-message')
                                       .removeClass('edal-success-message');
                        },
                        success: function(response) {
                            if (response.success) {
                                importStatus.text(response.data.message)
                                           .addClass('edal-success-message')
                                           .removeClass('edal-error-message');
                                
                                // Reload the page after successful import
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                importStatus.text(response.data.message)
                                           .addClass('edal-error-message')
                                           .removeClass('edal-success-message');
                            }
                        },
                        error: function() {
                            importStatus.text('An error occurred during import. Please try again.')
                                       .addClass('edal-error-message')
                                       .removeClass('edal-success-message');
                        }
                    });
                } catch (error) {
                    importStatus.text('Invalid import file. Please select a valid JSON file.')
                               .addClass('edal-error-message')
                               .removeClass('edal-success-message');
                }
            };
            
            reader.readAsText(file);
        }
    });
})(jQuery);