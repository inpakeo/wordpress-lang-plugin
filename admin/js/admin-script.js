/**
 * Admin JavaScript for WP Hreflang Manager
 * Pure Vanilla JS - No jQuery dependencies
 *
 * @package WP_Hreflang_Manager
 */

(function() {
    'use strict';

    /**
     * Main Admin Handler
     */
    class WPHreflangAdmin {
        constructor() {
            this.optionsKey = 'wp_hreflang_options';
            this.init();
        }

        /**
         * Initialize
         */
        init() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.bindEvents());
            } else {
                this.bindEvents();
            }
        }

        /**
         * Bind all events
         */
        bindEvents() {
            // Language quick select dropdown
            const quickSelect = document.getElementById('language-quick-select');
            if (quickSelect) {
                quickSelect.addEventListener('change', (e) => this.handleLanguageQuickSelect(e));
            }

            // Add language button
            const addBtn = document.getElementById('add-language-btn');
            if (addBtn) {
                addBtn.addEventListener('click', (e) => this.handleAddLanguage(e));
            }

            // Delete language buttons
            document.addEventListener('click', (e) => {
                if (e.target.closest('.delete-language')) {
                    this.handleDeleteLanguage(e);
                }
            });

            // Enter key in input fields
            const inputFields = ['new-lang-code', 'new-lang-name', 'new-lang-hreflang', 'new-lang-flag'];
            inputFields.forEach(id => {
                const field = document.getElementById(id);
                if (field) {
                    field.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const btn = document.getElementById('add-language-btn');
                            if (btn) btn.click();
                        }
                    });
                }
            });

            // Export settings button
            const exportBtn = document.getElementById('wp-hreflang-export-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', (e) => this.handleExportSettings(e));
            }

            // Import settings button
            const importBtn = document.getElementById('wp-hreflang-import-btn');
            if (importBtn) {
                importBtn.addEventListener('click', (e) => this.handleImportSettings(e));
            }

            // Initialize sortable
            this.initSortable();
        }

        /**
         * Initialize sortable functionality
         */
        initSortable() {
            const list = document.getElementById('wp-hreflang-languages-list');
            if (!list) return;

            let draggedElement = null;

            const items = list.querySelectorAll('.wp-hreflang-language-item');
            items.forEach(item => {
                const handle = item.querySelector('.language-handle');
                if (!handle) return;

                item.draggable = true;

                handle.style.cursor = 'move';

                item.addEventListener('dragstart', (e) => {
                    draggedElement = item;
                    item.style.opacity = '0.5';
                    e.dataTransfer.effectAllowed = 'move';
                });

                item.addEventListener('dragend', (e) => {
                    item.style.opacity = '1';
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';

                    if (draggedElement !== item) {
                        const rect = item.getBoundingClientRect();
                        const midpoint = rect.top + rect.height / 2;

                        if (e.clientY < midpoint) {
                            list.insertBefore(draggedElement, item);
                        } else {
                            list.insertBefore(draggedElement, item.nextSibling);
                        }
                    }
                });
            });
        }

        /**
         * Handle language quick select
         */
        handleLanguageQuickSelect(e) {
            const select = e.target;
            const selectedOption = select.options[select.selectedIndex];

            if (!selectedOption.value) {
                return;
            }

            // Get data attributes
            const code = selectedOption.value;
            const name = selectedOption.dataset.name;
            const hreflang = selectedOption.dataset.hreflang;
            const flag = selectedOption.dataset.flag;

            console.log('Quick Select:', { code, name, hreflang, flag });

            // Auto-fill input fields
            const codeInput = document.getElementById('new-lang-code');
            const nameInput = document.getElementById('new-lang-name');
            const hreflangInput = document.getElementById('new-lang-hreflang');
            const flagInput = document.getElementById('new-lang-flag');

            if (codeInput) codeInput.value = code;
            if (nameInput) nameInput.value = name;
            if (hreflangInput) hreflangInput.value = hreflang;
            if (flagInput) flagInput.value = flag;

            // Reset dropdown
            select.selectedIndex = 0;

            // Focus add button
            const addBtn = document.getElementById('add-language-btn');
            if (addBtn) addBtn.focus();
        }

        /**
         * Handle add language
         */
        handleAddLanguage(e) {
            e.preventDefault();

            const langCode = document.getElementById('new-lang-code')?.value.trim() || '';
            const langName = document.getElementById('new-lang-name')?.value.trim() || '';
            const langHreflang = document.getElementById('new-lang-hreflang')?.value.trim() || '';
            const langFlag = document.getElementById('new-lang-flag')?.value.trim() || '';

            console.log('Add Language:', { langCode, langName, langHreflang, langFlag });

            // Validation
            if (!langCode) {
                alert('Please enter a language code (e.g., en)');
                document.getElementById('new-lang-code')?.focus();
                return;
            }

            if (!langName) {
                alert('Please enter a language name (e.g., English)');
                document.getElementById('new-lang-name')?.focus();
                return;
            }

            if (!langHreflang) {
                alert('Please enter a hreflang code (e.g., en-US)');
                document.getElementById('new-lang-hreflang')?.focus();
                return;
            }

            // Validate language code format (2 letters)
            if (!/^[a-z]{2}$/i.test(langCode)) {
                alert('Language code must be 2 letters (e.g., en, fr, de)');
                document.getElementById('new-lang-code')?.focus();
                return;
            }

            // Validate hreflang format
            if (!/^[a-z]{2}(-[A-Z]{2})?$/i.test(langHreflang)) {
                alert('Hreflang code must be in format: xx or xx-XX (e.g., en or en-US)');
                document.getElementById('new-lang-hreflang')?.focus();
                return;
            }

            // Check if language already exists
            const existingItems = document.querySelectorAll('.wp-hreflang-language-item');
            for (let item of existingItems) {
                if (item.dataset.langCode === langCode) {
                    alert(`Language code "${langCode}" already exists`);
                    document.getElementById('new-lang-code')?.focus();
                    return;
                }
            }

            // Add language item
            this.addLanguageItem(langCode, langName, langHreflang, langFlag);

            // Clear inputs
            document.getElementById('new-lang-code').value = '';
            document.getElementById('new-lang-name').value = '';
            document.getElementById('new-lang-hreflang').value = '';
            document.getElementById('new-lang-flag').value = '';
            document.getElementById('new-lang-code')?.focus();

            // Show save reminder
            this.showSaveReminder();
        }

        /**
         * Add language item to list
         */
        addLanguageItem(langCode, langName, langHreflang, langFlag) {
            const list = document.getElementById('wp-hreflang-languages-list');
            if (!list) return;

            const optionsKey = this.optionsKey;

            const itemHtml = `
                <div class="wp-hreflang-language-item" data-lang-code="${this.escapeHtml(langCode)}" draggable="true">
                    <div class="language-handle" style="cursor: move;">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="language-flag">
                        <input type="text" name="${optionsKey}[languages][${this.escapeHtml(langCode)}][flag]" value="${this.escapeHtml(langFlag)}" placeholder="🇺🇸" class="small-text" />
                    </div>
                    <div class="language-code">
                        <strong>${this.escapeHtml(langCode)}</strong>
                    </div>
                    <div class="language-name">
                        <input type="text" name="${optionsKey}[languages][${this.escapeHtml(langCode)}][name]" value="${this.escapeHtml(langName)}" placeholder="Language Name" class="regular-text" />
                    </div>
                    <div class="language-hreflang">
                        <input type="text" name="${optionsKey}[languages][${this.escapeHtml(langCode)}][hreflang]" value="${this.escapeHtml(langHreflang)}" placeholder="en-US" class="regular-text" />
                        <small>Hreflang code (e.g., en, en-US, fr-FR)</small>
                    </div>
                    <div class="language-enabled">
                        <label>
                            <input type="checkbox" name="${optionsKey}[languages][${this.escapeHtml(langCode)}][enabled]" value="1" checked />
                            Enabled
                        </label>
                    </div>
                    <div class="language-actions">
                        <button type="button" class="button delete-language" data-lang-code="${this.escapeHtml(langCode)}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;

            list.insertAdjacentHTML('beforeend', itemHtml);

            // Re-initialize sortable for new item
            this.initSortable();
        }

        /**
         * Handle delete language
         */
        handleDeleteLanguage(e) {
            e.preventDefault();

            const button = e.target.closest('.delete-language');
            if (!button) return;

            const langCode = button.dataset.langCode;

            if (!confirm('Are you sure you want to delete this language?')) {
                return;
            }

            const item = button.closest('.wp-hreflang-language-item');
            if (item) {
                item.style.transition = 'opacity 0.3s ease';
                item.style.opacity = '0';
                setTimeout(() => {
                    item.remove();
                    this.showSaveReminder();
                }, 300);
            }
        }

        /**
         * Show save reminder
         */
        showSaveReminder() {
            const submitButton = document.querySelector('.wrap form .submit');
            if (!submitButton) return;

            submitButton.style.background = '#fff3cd';
            submitButton.style.padding = '10px';
            submitButton.style.borderLeft = '4px solid #f0ad4e';
            submitButton.style.transition = 'all 0.3s ease';

            setTimeout(() => {
                submitButton.style.background = '';
                submitButton.style.padding = '';
                submitButton.style.borderLeft = '';
            }, 3000);
        }

        /**
         * Handle export settings
         */
        handleExportSettings(e) {
            e.preventDefault();

            const button = e.target;
            const originalHtml = button.innerHTML;

            // Show loading state
            button.disabled = true;
            button.innerHTML = '<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> Exporting...';

            // Get nonce
            const nonce = window.wpHreflangAdmin?.nonce || '';

            fetch(window.wpHreflangAdmin?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wp_hreflang_export_settings',
                    nonce: nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    // Create download
                    const blob = new Blob([data.data.data], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    alert('Settings exported successfully!');
                } else {
                    alert('Export failed: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Export failed: Network error');
                console.error(error);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
            });
        }

        /**
         * Handle import settings
         */
        handleImportSettings(e) {
            e.preventDefault();

            const fileInput = document.getElementById('wp-hreflang-import-file');
            const file = fileInput?.files[0];

            if (!file) {
                alert('Please select a file to import');
                return;
            }

            if (!file.name.endsWith('.json')) {
                alert('Please select a valid JSON file');
                return;
            }

            if (!confirm('This will replace your current settings. Are you sure you want to continue?')) {
                return;
            }

            const button = e.target;
            const originalHtml = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear;"></span> Importing...';

            const nonce = window.wpHreflangAdmin?.nonce || '';
            const formData = new FormData();
            formData.append('action', 'wp_hreflang_import_settings');
            formData.append('nonce', nonce);
            formData.append('import_file', file);

            fetch(window.wpHreflangAdmin?.ajaxUrl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Settings imported successfully! The page will reload.');
                    window.location.reload();
                } else {
                    alert('Import failed: ' + (data.data?.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Import failed: Network error');
                console.error(error);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalHtml;
                if (fileInput) fileInput.value = '';
            });
        }

        /**
         * Escape HTML
         */
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    }

    // Initialize
    new WPHreflangAdmin();

    // Add CSS for rotation animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(359deg); }
        }
    `;
    document.head.appendChild(style);

})();
