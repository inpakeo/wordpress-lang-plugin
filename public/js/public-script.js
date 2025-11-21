/**
 * Public JavaScript for WP Hreflang Manager
 *
 * @package WP_Hreflang_Manager
 */

(function($) {
    'use strict';

    /**
     * Language Switcher Handler
     */
    var WPHreflangSwitcher = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Dropdown switcher (old select)
            $(document).on('change', '.wp-hreflang-select', this.handleDropdownChange.bind(this));

            // Custom dropdown trigger
            $(document).on('click', '.wp-hreflang-dropdown-trigger', this.handleCustomDropdownToggle.bind(this));

            // Custom dropdown items
            $(document).on('click', '.wp-hreflang-dropdown-item', this.handleCustomDropdownItemClick.bind(this));

            // Close dropdown when clicking outside
            $(document).on('click', this.handleOutsideClick.bind(this));

            // Link/Flag switcher
            $(document).on('click', '.wp-hreflang-link, .wp-hreflang-flag', this.handleLinkClick.bind(this));
        },

        /**
         * Handle custom dropdown toggle
         */
        handleCustomDropdownToggle: function(e) {
            e.stopPropagation();
            var $trigger = $(e.currentTarget);
            var $menu = $trigger.siblings('.wp-hreflang-dropdown-menu');

            // Close other dropdowns
            $('.wp-hreflang-dropdown-menu.active').not($menu).removeClass('active');
            $('.wp-hreflang-dropdown-trigger.active').not($trigger).removeClass('active');

            // Toggle current dropdown
            $trigger.toggleClass('active');
            $menu.toggleClass('active');
        },

        /**
         * Handle custom dropdown item click
         */
        handleCustomDropdownItemClick: function(e) {
            e.preventDefault();

            var $item = $(e.currentTarget);
            var href = $item.attr('href');

            // If link has valid href (not #), navigate
            if (href && href !== '#') {
                window.location.href = href;
            }

            // Close dropdown
            var $dropdown = $item.closest('.wp-hreflang-custom-dropdown');
            $dropdown.find('.wp-hreflang-dropdown-trigger').removeClass('active');
            $dropdown.find('.wp-hreflang-dropdown-menu').removeClass('active');
        },

        /**
         * Handle click outside dropdown
         */
        handleOutsideClick: function(e) {
            if (!$(e.target).closest('.wp-hreflang-custom-dropdown').length) {
                $('.wp-hreflang-dropdown-menu.active').removeClass('active');
                $('.wp-hreflang-dropdown-trigger.active').removeClass('active');
            }
        },

        /**
         * Handle dropdown change
         */
        handleDropdownChange: function(e) {
            var $select = $(e.currentTarget);
            var language = $select.val();
            var postId = $select.data('post-id') || 0;

            if (!language) {
                return;
            }

            this.switchLanguage(language, postId, $select);
        },

        /**
         * Handle link click
         */
        handleLinkClick: function(e) {
            var $link = $(e.currentTarget);

            // If link has valid href (not #), let it navigate normally
            if ($link.attr('href') !== '#' && $link.attr('href') !== '') {
                return true;
            }

            // Prevent default for AJAX switch
            e.preventDefault();

            var language = $link.data('lang');
            var postId = $link.data('post-id') || 0;

            if (!language) {
                return;
            }

            this.switchLanguage(language, postId, $link);
        },

        /**
         * Switch language via AJAX
         */
        switchLanguage: function(language, postId, $element) {
            var self = this;

            // Add loading class
            $element.closest('.wp-hreflang-switcher').addClass('loading');

            $.ajax({
                url: wpHreflangData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'switch_language',
                    nonce: wpHreflangData.nonce,
                    language: language,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success && response.data.redirect_url) {
                        // Redirect to translated page
                        window.location.href = response.data.redirect_url;
                    } else {
                        // Fallback: reload page with lang parameter
                        self.fallbackRedirect(language);
                    }
                },
                error: function() {
                    // Fallback: reload page with lang parameter
                    self.fallbackRedirect(language);
                },
                complete: function() {
                    // Remove loading class
                    $element.closest('.wp-hreflang-switcher').removeClass('loading');
                }
            });
        },

        /**
         * Fallback redirect
         */
        fallbackRedirect: function(language) {
            var url = new URL(window.location.href);
            url.searchParams.set('lang', language);
            window.location.href = url.toString();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WPHreflangSwitcher.init();
    });

})(jQuery);
