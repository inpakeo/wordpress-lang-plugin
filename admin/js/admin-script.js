/**
 * Admin JavaScript for WP Hreflang Manager
 *
 * @package WP_Hreflang_Manager
 */

(function($) {
    'use strict';

    /**
     * Admin Handler
     */
    var WPHreflangAdmin = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initSortable();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Add language button
            $('#add-language-btn').on('click', this.handleAddLanguage.bind(this));

            // Delete language button
            $(document).on('click', '.delete-language', this.handleDeleteLanguage.bind(this));

            // Enter key in add language inputs
            $('#new-lang-code, #new-lang-name, #new-lang-hreflang, #new-lang-flag').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $('#add-language-btn').trigger('click');
                }
            });

            // Export settings button
            $('#wp-hreflang-export-btn').on('click', this.handleExportSettings.bind(this));

            // Import settings button
            $('#wp-hreflang-import-btn').on('click', this.handleImportSettings.bind(this));
        },

        /**
         * Initialize sortable
         */
        initSortable: function() {
            if (typeof $.fn.sortable === 'function') {
                $('#wp-hreflang-languages-list').sortable({
                    handle: '.language-handle',
                    placeholder: 'ui-sortable-placeholder',
                    axis: 'y',
                    cursor: 'move',
                    opacity: 0.8
                });
            }
        },

        /**
         * Handle add language
         */
        handleAddLanguage: function(e) {
            e.preventDefault();

            var langCode = $('#new-lang-code').val().trim();
            var langName = $('#new-lang-name').val().trim();
            var langHreflang = $('#new-lang-hreflang').val().trim();
            var langFlag = $('#new-lang-flag').val().trim();

            // Validation
            if (!langCode) {
                alert('Please enter a language code (e.g., en)');
                $('#new-lang-code').focus();
                return;
            }

            if (!langName) {
                alert('Please enter a language name (e.g., English)');
                $('#new-lang-name').focus();
                return;
            }

            if (!langHreflang) {
                alert('Please enter a hreflang code (e.g., en-US)');
                $('#new-lang-hreflang').focus();
                return;
            }

            // Validate language code format (2 letters)
            if (!/^[a-z]{2}$/i.test(langCode)) {
                alert('Language code must be 2 letters (e.g., en, fr, de)');
                $('#new-lang-code').focus();
                return;
            }

            // Validate hreflang format
            if (!/^[a-z]{2}(-[A-Z]{2})?$/i.test(langHreflang)) {
                alert('Hreflang code must be in format: xx or xx-XX (e.g., en or en-US)');
                $('#new-lang-hreflang').focus();
                return;
            }

            // Check if language already exists
            var exists = false;
            $('#wp-hreflang-languages-list .wp-hreflang-language-item').each(function() {
                if ($(this).data('lang-code') === langCode) {
                    exists = true;
                    return false;
                }
            });

            if (exists) {
                alert('Language code "' + langCode + '" already exists');
                $('#new-lang-code').focus();
                return;
            }

            // Create new language item
            this.addLanguageItem(langCode, langName, langHreflang, langFlag);

            // Clear inputs
            $('#new-lang-code, #new-lang-name, #new-lang-hreflang, #new-lang-flag').val('');
            $('#new-lang-code').focus();

            // Show save reminder
            this.showSaveReminder();
        },

        /**
         * Add language item to list
         */
        addLanguageItem: function(langCode, langName, langHreflang, langFlag) {
            var optionsKey = 'wp_hreflang_options';

            var html = '<div class="wp-hreflang-language-item" data-lang-code="' + this.escapeHtml(langCode) + '">' +
                '<div class="language-handle">' +
                    '<span class="dashicons dashicons-menu"></span>' +
                '</div>' +
                '<div class="language-flag">' +
                    '<input type="text" name="' + optionsKey + '[languages][' + this.escapeHtml(langCode) + '][flag]" value="' + this.escapeHtml(langFlag) + '" placeholder="🇺🇸" class="small-text" />' +
                '</div>' +
                '<div class="language-code">' +
                    '<strong>' + this.escapeHtml(langCode) + '</strong>' +
                '</div>' +
                '<div class="language-name">' +
                    '<input type="text" name="' + optionsKey + '[languages][' + this.escapeHtml(langCode) + '][name]" value="' + this.escapeHtml(langName) + '" placeholder="Language Name" class="regular-text" />' +
                '</div>' +
                '<div class="language-hreflang">' +
                    '<input type="text" name="' + optionsKey + '[languages][' + this.escapeHtml(langCode) + '][hreflang]" value="' + this.escapeHtml(langHreflang) + '" placeholder="en-US" class="regular-text" />' +
                    '<small>Hreflang code (e.g., en, en-US, fr-FR)</small>' +
                '</div>' +
                '<div class="language-enabled">' +
                    '<label>' +
                        '<input type="checkbox" name="' + optionsKey + '[languages][' + this.escapeHtml(langCode) + '][enabled]" value="1" checked />' +
                        'Enabled' +
                    '</label>' +
                '</div>' +
                '<div class="language-actions">' +
                    '<button type="button" class="button delete-language" data-lang-code="' + this.escapeHtml(langCode) + '">' +
                        '<span class="dashicons dashicons-trash"></span>' +
                    '</button>' +
                '</div>' +
            '</div>';

            $('#wp-hreflang-languages-list').append(html);
        },

        /**
         * Handle delete language
         */
        handleDeleteLanguage: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var langCode = $button.data('lang-code');

            if (!confirm('Are you sure you want to delete this language?')) {
                return;
            }

            $button.closest('.wp-hreflang-language-item').fadeOut(300, function() {
                $(this).remove();
            });

            this.showSaveReminder();
        },

        /**
         * Show save reminder
         */
        showSaveReminder: function() {
            var $submitButton = $('.wrap form .submit');

            if ($submitButton.length) {
                $submitButton.css({
                    'background': '#fff3cd',
                    'padding': '10px',
                    'border-left': '4px solid #f0ad4e',
                    'transition': 'all 0.3s ease'
                });

                setTimeout(function() {
                    $submitButton.css({
                        'background': '',
                        'padding': '',
                        'border-left': ''
                    });
                }, 3000);
            }
        },

        /**
         * Handle export settings
         */
        handleExportSettings: function(e) {
            e.preventDefault();

            var $button = $(e.currentTarget);
            var originalHtml = $button.html();

            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt spin"></span> Exporting...');

            // Get nonce from the page
            var nonce = $('#wp_hreflang_admin_nonce').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_hreflang_export_settings',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Create download link
                        var blob = new Blob([response.data.data], { type: 'application/json' });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);

                        // Show success message
                        alert('Settings exported successfully!');
                    } else {
                        alert('Export failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Export failed: Network error');
                },
                complete: function() {
                    // Restore button state
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },

        /**
         * Handle import settings
         */
        handleImportSettings: function(e) {
            e.preventDefault();

            var $fileInput = $('#wp-hreflang-import-file');
            var file = $fileInput[0].files[0];

            if (!file) {
                alert('Please select a file to import');
                return;
            }

            // Validate file type
            if (!file.name.endsWith('.json')) {
                alert('Please select a valid JSON file');
                return;
            }

            // Confirm import
            if (!confirm('This will replace your current settings. Are you sure you want to continue?')) {
                return;
            }

            var $button = $(e.currentTarget);
            var originalHtml = $button.html();

            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt spin"></span> Importing...');

            // Get nonce from the page
            var nonce = $('#wp_hreflang_admin_nonce').val();

            // Create FormData
            var formData = new FormData();
            formData.append('action', 'wp_hreflang_import_settings');
            formData.append('nonce', nonce);
            formData.append('import_file', file);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('Settings imported successfully! The page will reload.');
                        window.location.reload();
                    } else {
                        alert('Import failed: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Import failed: Network error');
                },
                complete: function() {
                    // Restore button state
                    $button.prop('disabled', false).html(originalHtml);
                    // Clear file input
                    $fileInput.val('');
                }
            });
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WPHreflangAdmin.init();
    });

})(jQuery);
