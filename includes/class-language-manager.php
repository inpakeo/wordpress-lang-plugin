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

        echo '<hr style="margin: 15px 0;">';

        // Translations
        echo '<p><strong>' . __( 'Link translations:', 'wp-hreflang-manager' ) . '</strong></p>';

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

            echo '<select name="wp_hreflang_translations[' . esc_attr( $lang_code ) . ']" class="widefat">';
            echo '<option value="">' . __( '-- Select translation --', 'wp-hreflang-manager' ) . '</option>';

            foreach ( $posts as $trans_post ) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    $trans_post->ID,
                    selected( $translation_id, $trans_post->ID, false ),
                    esc_html( $trans_post->post_title )
                );
            }

            echo '</select>';
            echo '</p>';
        }

        echo '</div>';
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
}
