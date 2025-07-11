jQuery(document).ready(function($) {
    // Handle bulk form submission
    $('#edal-bulk-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $bulkAction = $form.find('select[name="bulk_action"]');
        var $selectedItems = $form.find('input[name="selected_options[]"]:checked');
        
        if ($bulkAction.val() === '-1') {
            alert(edalData.i18n.bulkActionRequired);
            return;
        }
        
        if ($selectedItems.length === 0) {
            alert(edalData.i18n.itemsRequired);
            return;
        }
        
        var confirmMessage = $bulkAction.val() === 'delete' ? 
            edalData.confirmBulkDelete : 
            edalData.confirmBulkDisable;
            
        if (confirm(confirmMessage)) {
            $form.off('submit').submit();
        }
    });
    
    // Handle select all checkbox
    $('#cb-select-all-1').on('change', function() {
        var $checkboxes = $('input[name="selected_options[]"]');
        var $selectAll = $(this);
        
        $checkboxes.prop('checked', $selectAll.prop('checked'));
        updateSelectedCount();
    });
    
    // Handle individual checkboxes
    $('input[name="selected_options[]"]').on('change', function() {
        updateSelectedCount();
    });
    
    // Update selected count
    function updateSelectedCount() {
        var $selectedItems = $('input[name="selected_options[]"]:checked');
        var $selectAll = $('#cb-select-all-1');
        
        // Update select all checkbox state
        $selectAll.prop('checked', 
            $selectedItems.length === $('input[name="selected_options[]"]').length);
        
        // Update selected count text
        var count = $selectedItems.length;
        var $countText = $('.selected-count');
        
        if (count === 0) {
            if ($countText.length === 0) {
                $('.bulkactions').append('<span class="selected-count"></span>');
            }
            $('.selected-count').text(edalData.i18n.noItemsSelected);
        } else {
            if ($countText.length === 0) {
                $('.bulkactions').append('<span class="selected-count"></span>');
            }
            $('.selected-count').text(edalData.i18n.selected.replace('%d', count));
        }
    }
    
    // Handle individual action buttons
    $('.edal-button-delete').on('click', function(e) {
        e.preventDefault();
        if (confirm(edalData.confirmDelete)) {
            window.location.href = $(this).attr('href');
        }
    });
    
    $('.edal-button-disable').on('click', function(e) {
        e.preventDefault();
        if (confirm(edalData.confirmDisable)) {
            window.location.href = $(this).attr('href');
        }
    });
    
    // Handle expand button
    $('.edal-button-expand').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $row = $button.closest('tr');
        var $expandedRow = $row.next('.expanded-row');
        var optionValue = $button.data('option');
        
        if ($expandedRow.length === 0) {
            // Create expanded row
            $expandedRow = $('<tr class="expanded-row"><td colspan="5"><div class="expanded-content"></div></td></tr>');
            $row.after($expandedRow);
            
            // Format and display the option value
            try {
                var formattedValue = JSON.stringify(JSON.parse(optionValue), null, 2);
                $expandedRow.find('.expanded-content').html('<pre>' + formattedValue + '</pre>');
            } catch (e) {
                $expandedRow.find('.expanded-content').html('<pre>' + optionValue + '</pre>');
            }
            
            $button.text(edalData.i18n.collapse);
        } else {
            // Remove expanded row
            $expandedRow.remove();
            $button.text(edalData.i18n.expand);
        }
    });
    
    // Handle refresh data button
    $('#edal-refresh-data').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();
        
        $button.prop('disabled', true)
            .html('<span class="dashicons dashicons-update spinning"></span> ' + edalData.i18n.refreshing);
        
        $.ajax({
            url: edalData.ajaxurl,
            type: 'POST',
            data: {
                action: 'edal_refresh_data',
                nonce: edalData.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data.message || edalData.i18n.refreshError);
                }
            },
            error: function() {
                alert(edalData.i18n.refreshError);
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Handle import/export buttons
    $('#edal-export-btn').on('click', function() {
        var data = {
            action: 'edal_export_settings',
            nonce: edalData.nonce
        };
        
        $.post(edalData.ajaxurl, data, function(response) {
            if (response.success) {
                var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'edal-settings.json';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert(response.data.message || edalData.i18n.exportError);
            }
        });
    });
    
    $('#edal-import-btn').on('click', function() {
        $('#edal-import-file').click();
    });
    
    $('#edal-import-file').on('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var settings = JSON.parse(e.target.result);
                
                $.ajax({
                    url: edalData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'edal_import_settings',
                        nonce: edalData.nonce,
                        settings: settings
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            alert(response.data.message || edalData.i18n.importError);
                        }
                    },
                    error: function() {
                        alert(edalData.i18n.importError);
                    }
                });
            } catch (e) {
                alert(edalData.i18n.invalidFile);
            }
        };
        reader.readAsText(file);
    });
}); 