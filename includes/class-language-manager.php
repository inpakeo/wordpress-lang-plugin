<?php
/**
 * Language Manager Class
 *
 * Manages all language-related operations including:
 * - Getting/setting current language
 * - Managing language configurations
 * - Handling language cookies
 * - Linking posts/pages between languages
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Hreflang_Language_Manager {

    /**
     * Single instance
     *
     * @var WP_Hreflang_Language_Manager
     */
    private static $instance = null;

    /**
     * Current language code
     *
     * @var string
     */
    private $current_language = '';

    /**
     * Get instance
     *
     * @return WP_Hreflang_Language_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->set_current_language();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add language query var
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

        // Add rewrite rules
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );

        // Handle language switching
        add_action( 'init', array( $this, 'handle_language_switch' ) );

        // Update current language after WordPress finishes parsing the request
        add_action( 'wp', array( $this, 'update_current_language_from_post' ), 5 );

        // Add language meta box to posts/pages
        add_action( 'add_meta_boxes', array( $this, 'add_language_meta_box' ) );

        // Save language meta
        add_action( 'save_post', array( $this, 'save_language_meta' ), 10, 2 );

        // AJAX handler for language switch
        add_action( 'wp_ajax_switch_language', array( $this, 'ajax_switch_language' ) );
        add_action( 'wp_ajax_nopriv_switch_language', array( $this, 'ajax_switch_language' ) );

        // AJAX handler for post duplication
        add_action( 'wp_ajax_wp_hreflang_duplicate_post', array( $this, 'ajax_duplicate_post' ) );

        // Add language switcher to menu if configured
        add_filter( 'wp_nav_menu_items', array( $this, 'add_switcher_to_menu' ), 10, 2 );
    }

    /**
     * Add language query var
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    /**
     * Add rewrite rules for language URLs
     */
    public function add_rewrite_rules() {
        $languages = $this->get_available_languages();

        foreach ( $languages as $lang_code => $language ) {
            if ( ! $language['enabled'] ) {
                continue;
            }

            // Add rewrite rule for language prefix
            add_rewrite_tag( '%lang%', '(' . $lang_code . ')' );
        }
    }

    /**
     * Set current language based on cookie, URL, or default
     * Note: For singular posts/pages, language is updated later via update_current_language_from_post()
     */
    private function set_current_language() {
        $options = $this->get_options();
        $default_language = isset( $options['default_language'] ) ? $options['default_language'] : 'en';

        // Check URL parameter first (highest priority)
        if ( isset( $_GET['lang'] ) && $this->is_valid_language( sanitize_text_field( $_GET['lang'] ) ) ) {
            $this->current_language = sanitize_text_field( $_GET['lang'] );
            $this->set_language_cookie( $this->current_language );
            return;
        }

        // Check cookie
        if ( isset( $_COOKIE['wp_hreflang_language'] ) ) {
            $cookie_lang = sanitize_text_field( wp_unslash( $_COOKIE['wp_hreflang_language'] ) );
            if ( $this->is_valid_language( $cookie_lang ) ) {
                $this->current_language = $cookie_lang;
                return;
            }
        }

        // Check browser language
        $auto_redirect = isset( $options['auto_redirect'] ) ? $options['auto_redirect'] : false;
        if ( $auto_redirect && ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            $browser_lang = $this->get_browser_language();
            if ( $browser_lang ) {
                $this->current_language = $browser_lang;
                $this->set_language_cookie( $this->current_language );
                return;
            }
        }

        // Use default language
        // Note: For singular posts, this will be updated by update_current_language_from_post()
        $this->current_language = $default_language;
    }

    /**
     * Get browser language
     *
     * @return string|false
     */
    private function get_browser_language() {
        if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
            return false;
        }

        $languages = $this->get_available_languages();
        // Sanitize the HTTP_ACCEPT_LANGUAGE header
        $accept_language = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
        $accepted = explode( ',', $accept_language );

        foreach ( $accepted as $lang ) {
            // Parse language code from Accept-Language format (e.g., "en-US;q=0.9" or "en")
            $lang = trim( $lang );
            // Remove quality value if present
            $lang_parts = explode( ';', $lang );
            $lang_code = $lang_parts[0];

            // Extract just the language part (first 2 characters before any dash)
            $lang_code = substr( $lang_code, 0, 2 );

            if ( isset( $languages[ $lang_code ] ) && isset( $languages[ $lang_code ]['enabled'] ) && $languages[ $lang_code ]['enabled'] ) {
                return $lang_code;
            }
        }

        return false;
    }

    /**
     * Update current language from post meta after WordPress finishes parsing the request
     * This hook runs after wp_query is populated, so we have access to the current post
     */
    public function update_current_language_from_post() {
        // Only check for singular pages (posts, pages, custom post types)
        if ( ! is_singular() ) {
            return;
        }

        // Skip if language was explicitly set via URL parameter
        if ( isset( $_GET['lang'] ) ) {
            return;
        }

        global $post;
        if ( empty( $post ) ) {
            return;
        }

        // Get the language of the current post
        $post_lang = get_post_meta( $post->ID, '_wp_hreflang_language', true );

        // If post has a language set and it's valid, update current language
        if ( ! empty( $post_lang ) && $this->is_valid_language( $post_lang ) ) {
            // Only update if it's different from current to avoid unnecessary cookie updates
            if ( $this->current_language !== $post_lang ) {
                $this->current_language = $post_lang;
                // Set cookie to remember user's language preference
                $this->set_language_cookie( $this->current_language );
            }
        }
    }

    /**
     * Handle language switch
     */
    public function handle_language_switch() {
        if ( isset( $_GET['switch_language'] ) ) {
            $new_lang = sanitize_text_field( $_GET['switch_language'] );

            if ( $this->is_valid_language( $new_lang ) ) {
                $this->current_language = $new_lang;
                $this->set_language_cookie( $new_lang );

                // Redirect to translated page if exists
                if ( isset( $_GET['redirect_to'] ) ) {
                    $redirect_url = esc_url_raw( $_GET['redirect_to'] );
                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }
        }
    }

    /**
     * AJAX handler for language switch
     */
    public function ajax_switch_language() {
        check_ajax_referer( 'wp_hreflang_nonce', 'nonce' );

        $language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $this->is_valid_language( $language ) ) {
            wp_send_json_error( array( 'message' => 'Invalid language' ) );
        }

        $this->set_language_cookie( $language );

        // Get translated post URL if exists
        $translated_url = '';
        if ( $post_id > 0 ) {
            $translated_post_id = $this->get_translation( $post_id, $language );
            if ( $translated_post_id ) {
                $translated_url = get_permalink( $translated_post_id );
            }
        }

        if ( empty( $translated_url ) ) {
            $translated_url = add_query_arg( 'lang', $language, home_url( '/' ) );
        }

        wp_send_json_success( array(
            'redirect_url' => $translated_url,
            'language' => $language
        ) );
    }

    /**
     * Set language cookie
     *
     * @param string $language Language code.
     */
    private function set_language_cookie( $language ) {
        // Don't set cookie if headers already sent
        if ( headers_sent() ) {
            return;
        }

        $secure = is_ssl();
        $samesite = 'Lax';

        // For PHP 7.3+ use options array
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie(
                'wp_hreflang_language',
                $language,
                array(
                    'expires' => time() + ( 86400 * 365 ),
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => $samesite
                )
            );
        } else {
            // Fallback for older PHP versions
            setcookie(
                'wp_hreflang_language',
                $language,
                time() + ( 86400 * 365 ),
                COOKIEPATH,
                COOKIE_DOMAIN,
                $secure,
                true
            );
        }
    }

    /**
     * Check if language is valid
     *
     * @param string $language Language code.
     * @return bool
     */
    private function is_valid_language( $language ) {
        $languages = $this->get_available_languages();
        return isset( $languages[ $language ] ) && $languages[ $language ]['enabled'];
    }

    /**
     * Get current language
     *
     * @return string
     */
    public static function get_current_language() {
        $instance = self::get_instance();
        return $instance->current_language;
    }

    /**
     * Get available languages
     *
     * @return array
     */
    public function get_available_languages() {
        $options = $this->get_options();
        return isset( $options['languages'] ) ? $options['languages'] : array();
    }

    /**
     * Get enabled languages only
     *
     * @return array
     */
    public function get_enabled_languages() {
        $languages = $this->get_available_languages();
        return array_filter( $languages, function( $lang ) {
            return isset( $lang['enabled'] ) && $lang['enabled'];
        } );
    }

    /**
     * Get plugin options
     *
     * @return array
     */
    private function get_options() {
        return get_option( 'wp_hreflang_options', array() );
    }

    /**
     * Add language meta box to post edit screen
     */
    public function add_language_meta_box() {
        $post_types = get_post_types( array( 'public' => true ) );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'wp_hreflang_translations',
                __( 'Language Translations', 'wp-hreflang-manager' ),
                array( $this, 'render_language_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render language meta box
     *
     * @param WP_Post $post Post object.
     */
    public function render_language_meta_box( $post ) {
        wp_nonce_field( 'wp_hreflang_save_translations', 'wp_hreflang_translations_nonce' );

        $current_post_lang = get_post_meta( $post->ID, '_wp_hreflang_language', true );
        if ( empty( $current_post_lang ) ) {
            $options = $this->get_options();
            $current_post_lang = isset( $options['default_language'] ) ? $options['default_language'] : 'en';
        }

        $languages = $this->get_enabled_languages();
        $translations = get_post_meta( $post->ID, '_wp_hreflang_translations', true );
        if ( ! is_array( $translations ) ) {
            $translations = array();
        }

        echo '<div class="wp-hreflang-meta-box">';

        // Instructions
        echo '<div style="background: #f0f6fc; border-left: 3px solid #2271b1; padding: 10px; margin-bottom: 15px;">';
        echo '<p style="margin: 0; font-size: 12px; line-height: 1.5;">';
        echo '<strong>' . __( 'How to use:', 'wp-hreflang-manager' ) . '</strong><br>';
        echo __( '1. Set the language for this post below', 'wp-hreflang-manager' ) . '<br>';
        echo __( '2. Create posts in other languages and set their language', 'wp-hreflang-manager' ) . '<br>';
        echo __( '3. Link translations together by selecting posts from dropdowns', 'wp-hreflang-manager' );
        echo '</p>';
        echo '</div>';

        // Current post language
        echo '<p><strong>' . __( 'This post language:', 'wp-hreflang-manager' ) . '</strong></p>';
        echo '<select name="wp_hreflang_language" id="wp_hreflang_language" class="widefat">';
        foreach ( $languages as $lang_code => $language ) {
            printf(
                '<option value="%s" %s>%s %s</option>',
                esc_attr( $lang_code ),
                selected( $current_post_lang, $lang_code, false ),
                esc_html( $language['flag'] ),
                esc_html( $language['name'] )
            );
        }
        echo '</select>';
        echo '<p class="description" style="margin-top: 5px;">' . __( 'Select the language of this post', 'wp-hreflang-manager' ) . '</p>';

        echo '<hr style="margin: 15px 0;">';

        // Translations
        echo '<p><strong>' . __( 'Link translations:', 'wp-hreflang-manager' ) . '</strong></p>';
        echo '<p class="description" style="margin-top: 5px; margin-bottom: 10px;">' . __( 'Link this post to its translations in other languages', 'wp-hreflang-manager' ) . '</p>';

        foreach ( $languages as $lang_code => $language ) {
            if ( $lang_code === $current_post_lang ) {
                continue;
            }

            $translation_id = isset( $translations[ $lang_code ] ) ? $translations[ $lang_code ] : '';

            echo '<p>';
            echo '<label>' . esc_html( $language['flag'] ) . ' ' . esc_html( $language['name'] ) . ':</label><br>';

            // Get posts in this language
            $args = array(
                'post_type' => $post->post_type,
                'posts_per_page' => -1,
                'post_status' => array( 'publish', 'draft' ),
                'meta_query' => array(
                    array(
                        'key' => '_wp_hreflang_language',
                        'value' => $lang_code,
                        'compare' => '='
                    )
                ),
                'exclude' => array( $post->ID )
            );

            $posts = get_posts( $args );

            if ( empty( $posts ) ) {
                // No posts available in this language - show duplicate button
                echo '<div style="display: flex; gap: 8px; align-items: center;">';
                echo '<select name="wp_hreflang_translations[' . esc_attr( $lang_code ) . ']" class="widefat" disabled style="flex: 1;">';
                echo '<option value="">' . sprintf( __( 'No %s posts available yet', 'wp-hreflang-manager' ), $language['name'] ) . '</option>';
                echo '</select>';

                // Duplicate button
                echo '<button type="button" class="button button-primary wp-hreflang-duplicate-btn"
                             data-post-id="' . esc_attr( $post->ID ) . '"
                             data-lang-code="' . esc_attr( $lang_code ) . '"
                             data-lang-name="' . esc_attr( $language['name'] ) . '"
                             style="white-space: nowrap; flex-shrink: 0;">
                        <span class="dashicons dashicons-admin-page" style="margin-top: 3px;"></span> '
                        . sprintf( __( 'Duplicate to %s', 'wp-hreflang-manager' ), $language['name'] ) .
                      '</button>';
                echo '</div>';

                echo '<p class="description" style="margin-top: 3px; font-size: 11px;">';
                echo sprintf(
                    __( 'Click "Duplicate" to copy this post with all content and images to %s, or create a new blank post.', 'wp-hreflang-manager' ),
                    '<strong>' . esc_html( $language['name'] ) . '</strong>'
                );
                echo ' <a href="' . esc_url( admin_url( 'post-new.php?post_type=' . $post->post_type ) ) . '" target="_blank" style="text-decoration: none;">' . __( 'Create blank', 'wp-hreflang-manager' ) . '</a>';
                echo '</p>';
            } else {
                // Posts available - show dropdown
                echo '<select name="wp_hreflang_translations[' . esc_attr( $lang_code ) . ']" class="widefat">';
                echo '<option value="">' . __( '-- Select translation --', 'wp-hreflang-manager' ) . '</option>';

                foreach ( $posts as $trans_post ) {
                    // Get post title with fallback
                    $title = ! empty( $trans_post->post_title ) ? $trans_post->post_title : __( '(No title)', 'wp-hreflang-manager' );

                    // Add status indicator for drafts
                    $status_text = '';
                    if ( $trans_post->post_status === 'draft' ) {
                        $status_text = ' — ' . __( 'Draft', 'wp-hreflang-manager' );
                    }

                    // Add post ID for identification
                    $display_text = sprintf( '%s (ID: %d)%s', $title, $trans_post->ID, $status_text );

                    printf(
                        '<option value="%d" %s>%s</option>',
                        $trans_post->ID,
                        selected( $translation_id, $trans_post->ID, false ),
                        esc_html( $display_text )
                    );
                }

                echo '</select>';
            }

            echo '</p>';
        }

        echo '</div>';

        // Add inline JavaScript for duplicate functionality
        ?>
        <script>
        (function() {
            console.log('🎯 Metabox duplicate script loaded');

            // Handle duplicate button clicks
            document.addEventListener('click', function(e) {
                if (e.target.closest('.wp-hreflang-duplicate-btn')) {
                    e.preventDefault();
                    const btn = e.target.closest('.wp-hreflang-duplicate-btn');

                    const postId = btn.dataset.postId;
                    const langCode = btn.dataset.langCode;
                    const langName = btn.dataset.langName;

                    if (!confirm('Duplicate this post to ' + langName + '?\n\nThis will copy all content, images, and metadata.')) {
                        return;
                    }

                    const originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-update-alt" style="animation: rotation 2s infinite linear; margin-top: 3px;"></span> Duplicating...';

                    fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'wp_hreflang_duplicate_post',
                            nonce: '<?php echo wp_create_nonce( 'wp_hreflang_admin_nonce' ); ?>',
                            post_id: postId,
                            lang_code: langCode
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ ' + data.data.message + '\n\nOpening new post for editing...');
                            window.open(data.data.edit_url, '_blank');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (data.data.message || 'Unknown error'));
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ Network error');
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    });
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Save language meta
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_language_meta( $post_id, $post ) {
        // Check nonce
        if ( ! isset( $_POST['wp_hreflang_translations_nonce'] ) ||
             ! wp_verify_nonce( $_POST['wp_hreflang_translations_nonce'], 'wp_hreflang_save_translations' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save post language
        if ( isset( $_POST['wp_hreflang_language'] ) ) {
            $language = sanitize_text_field( $_POST['wp_hreflang_language'] );
            update_post_meta( $post_id, '_wp_hreflang_language', $language );
        }

        // Save translations
        if ( isset( $_POST['wp_hreflang_translations'] ) && is_array( $_POST['wp_hreflang_translations'] ) ) {
            $translations = array_map( 'absint', $_POST['wp_hreflang_translations'] );

            // Remove empty translations
            $translations = array_filter( $translations );

            update_post_meta( $post_id, '_wp_hreflang_translations', $translations );

            // Update reverse translations
            foreach ( $translations as $lang_code => $translation_id ) {
                if ( $translation_id > 0 ) {
                    $reverse_translations = get_post_meta( $translation_id, '_wp_hreflang_translations', true );
                    if ( ! is_array( $reverse_translations ) ) {
                        $reverse_translations = array();
                    }

                    $post_lang = get_post_meta( $post_id, '_wp_hreflang_language', true );
                    // Only set reverse translation if post_lang is valid
                    if ( ! empty( $post_lang ) ) {
                        $reverse_translations[ $post_lang ] = $post_id;
                        update_post_meta( $translation_id, '_wp_hreflang_translations', $reverse_translations );
                    }
                }
            }
        }
    }

    /**
     * Get translation for a post
     *
     * @param int    $post_id  Post ID.
     * @param string $language Target language code.
     * @return int|false Translation post ID or false if not found.
     */
    public function get_translation( $post_id, $language ) {
        $translations = get_post_meta( $post_id, '_wp_hreflang_translations', true );

        if ( is_array( $translations ) && isset( $translations[ $language ] ) ) {
            return absint( $translations[ $language ] );
        }

        return false;
    }

    /**
     * Get all translations for a post
     *
     * @param int $post_id Post ID.
     * @return array Array of language_code => post_id pairs.
     */
    public function get_all_translations( $post_id ) {
        $translations = get_post_meta( $post_id, '_wp_hreflang_translations', true );

        if ( ! is_array( $translations ) ) {
            return array();
        }

        // Add current post
        $post_lang = get_post_meta( $post_id, '_wp_hreflang_language', true );
        if ( empty( $post_lang ) ) {
            $options = $this->get_options();
            $post_lang = isset( $options['default_language'] ) ? $options['default_language'] : 'en';
        }

        $translations[ $post_lang ] = $post_id;

        return $translations;
    }

    /**
     * Add language switcher to menu
     *
     * @param string $items Menu items HTML.
     * @param object $args  Menu arguments.
     * @return string Modified menu items.
     */
    public function add_switcher_to_menu( $items, $args ) {
        // Get plugin options
        $options = $this->get_options();

        // Check if menu location is set and matches current menu
        if ( empty( $options['menu_location'] ) || $args->theme_location !== $options['menu_location'] ) {
            return $items;
        }

        // Get language switcher HTML
        $switcher_html = WP_Hreflang_Language_Switcher::render( array(
            'echo' => false
        ) );

        // Wrap switcher in menu item
        if ( ! empty( $switcher_html ) ) {
            $menu_item = '<li class="menu-item menu-item-language-switcher">' . $switcher_html . '</li>';
            $items .= $menu_item;
        }

        return $items;
    }

    /**
     * AJAX handler: Duplicate post to another language
     */
    public function ajax_duplicate_post() {
        // Check nonce
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        // Check permissions
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'wp-hreflang-manager' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $target_lang = isset( $_POST['lang_code'] ) ? sanitize_text_field( $_POST['lang_code'] ) : '';

        if ( ! $post_id || ! $target_lang ) {
            wp_send_json_error( array( 'message' => __( 'Invalid parameters', 'wp-hreflang-manager' ) ) );
        }

        // Get original post
        $original_post = get_post( $post_id );
        if ( ! $original_post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found', 'wp-hreflang-manager' ) ) );
        }

        // Create duplicate post
        $new_post_data = array(
            'post_title'    => $original_post->post_title,
            'post_content'  => $original_post->post_content,
            'post_excerpt'  => $original_post->post_excerpt,
            'post_status'   => 'draft', // Create as draft for review
            'post_type'     => $original_post->post_type,
            'post_author'   => get_current_user_id(),
        );

        // Insert new post
        $new_post_id = wp_insert_post( $new_post_data );

        if ( is_wp_error( $new_post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to create post', 'wp-hreflang-manager' ) ) );
        }

        // Set language for new post
        update_post_meta( $new_post_id, '_wp_hreflang_language', $target_lang );

        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            set_post_thumbnail( $new_post_id, $thumbnail_id );
        }

        // Copy all post meta (except language-specific ones)
        $post_meta = get_post_meta( $post_id );
        $exclude_meta = array( '_wp_hreflang_language', '_wp_hreflang_translations', '_edit_lock', '_edit_last' );

        foreach ( $post_meta as $meta_key => $meta_values ) {
            if ( in_array( $meta_key, $exclude_meta ) ) {
                continue;
            }

            foreach ( $meta_values as $meta_value ) {
                add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
            }
        }

        // Link posts as translations
        $original_lang = get_post_meta( $post_id, '_wp_hreflang_language', true );
        if ( empty( $original_lang ) ) {
            $options = $this->get_options();
            $original_lang = isset( $options['default_language'] ) ? $options['default_language'] : 'en';
        }

        // Update original post translations
        $original_translations = get_post_meta( $post_id, '_wp_hreflang_translations', true );
        if ( ! is_array( $original_translations ) ) {
            $original_translations = array();
        }
        $original_translations[ $target_lang ] = $new_post_id;
        update_post_meta( $post_id, '_wp_hreflang_translations', $original_translations );

        // Update new post translations
        $new_translations = array( $original_lang => $post_id );
        update_post_meta( $new_post_id, '_wp_hreflang_translations', $new_translations );

        wp_send_json_success( array(
            'message' => sprintf( __( 'Post duplicated successfully! New post ID: %d', 'wp-hreflang-manager' ), $new_post_id ),
            'new_post_id' => $new_post_id,
            'edit_url' => get_edit_post_link( $new_post_id, 'raw' )
        ) );
    }
}
