<?php
/**
 * Hreflang Generator Class
 *
 * Automatically generates hreflang tags in the <head> section
 * according to Google guidelines.
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Hreflang_Generator {

    /**
     * Single instance
     *
     * @var WP_Hreflang_Generator
     */
    private static $instance = null;

    /**
     * Language manager instance
     *
     * @var WP_Hreflang_Language_Manager
     */
    private $language_manager;

    /**
     * Get instance
     *
     * @return WP_Hreflang_Generator
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
        $this->language_manager = WP_Hreflang_Language_Manager::get_instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add hreflang tags to head
        add_action( 'wp_head', array( $this, 'add_hreflang_tags' ), 1 );

        // Add language attribute to HTML tag
        add_filter( 'language_attributes', array( $this, 'modify_language_attributes' ) );

        // Add Open Graph locale tags
        add_action( 'wp_head', array( $this, 'add_og_locale_tags' ), 5 );
    }

    /**
     * Add hreflang tags to head
     */
    public function add_hreflang_tags() {
        global $post;

        $languages = $this->language_manager->get_enabled_languages();

        if ( empty( $languages ) ) {
            return;
        }

        // Get current URL
        $current_url = $this->get_current_url();

        // For singular posts/pages
        if ( is_singular() && $post ) {
            $this->add_singular_hreflang_tags( $post->ID );
        }
        // For archives and home page
        else {
            $this->add_archive_hreflang_tags( $current_url );
        }
    }

    /**
     * Add hreflang tags for singular posts/pages
     *
     * @param int $post_id Post ID.
     */
    private function add_singular_hreflang_tags( $post_id ) {
        $translations = $this->language_manager->get_all_translations( $post_id );
        $languages = $this->language_manager->get_enabled_languages();

        if ( empty( $translations ) ) {
            return;
        }

        $hreflang_urls = array();

        // Get URLs for all translations
        foreach ( $translations as $lang_code => $trans_post_id ) {
            if ( ! isset( $languages[ $lang_code ] ) ) {
                continue;
            }

            $url = get_permalink( $trans_post_id );
            if ( $url ) {
                $hreflang_code = $languages[ $lang_code ]['hreflang'];
                $hreflang_urls[ $hreflang_code ] = $url;
            }
        }

        // Output hreflang tags
        $this->output_hreflang_tags( $hreflang_urls );
    }

    /**
     * Add hreflang tags for archives and other pages
     *
     * @param string $current_url Current URL.
     */
    private function add_archive_hreflang_tags( $current_url ) {
        $languages = $this->language_manager->get_enabled_languages();
        $hreflang_urls = array();

        foreach ( $languages as $lang_code => $language ) {
            $hreflang_code = $language['hreflang'];
            $url = add_query_arg( 'lang', $lang_code, $current_url );
            $hreflang_urls[ $hreflang_code ] = $url;
        }

        // Output hreflang tags
        $this->output_hreflang_tags( $hreflang_urls );
    }

    /**
     * Output hreflang tags
     *
     * @param array $hreflang_urls Array of hreflang_code => url pairs.
     */
    private function output_hreflang_tags( $hreflang_urls ) {
        if ( empty( $hreflang_urls ) ) {
            return;
        }

        echo "\n<!-- Hreflang tags by WP Hreflang Manager -->\n";

        // Output each language
        foreach ( $hreflang_urls as $hreflang_code => $url ) {
            printf(
                '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                esc_attr( $hreflang_code ),
                esc_url( $url )
            );
        }

        // Add x-default (recommended by Google)
        $options = get_option( 'wp_hreflang_options', array() );
        $default_language = isset( $options['default_language'] ) ? $options['default_language'] : 'en';
        $languages = $this->language_manager->get_enabled_languages();

        if ( isset( $languages[ $default_language ] ) ) {
            $default_hreflang = $languages[ $default_language ]['hreflang'];

            if ( isset( $hreflang_urls[ $default_hreflang ] ) ) {
                printf(
                    '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
                    esc_url( $hreflang_urls[ $default_hreflang ] )
                );
            }
        }

        echo "<!-- End Hreflang tags -->\n\n";
    }

    /**
     * Modify language attributes in HTML tag
     *
     * @param string $output Language attributes.
     * @return string
     */
    public function modify_language_attributes( $output ) {
        $current_lang = WP_Hreflang_Language_Manager::get_current_language();
        $languages = $this->language_manager->get_enabled_languages();

        if ( isset( $languages[ $current_lang ] ) ) {
            $hreflang = $languages[ $current_lang ]['hreflang'];
            $output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $hreflang ) . '"', $output );
        }

        return $output;
    }

    /**
     * Add Open Graph locale tags
     */
    public function add_og_locale_tags() {
        $current_lang = WP_Hreflang_Language_Manager::get_current_language();
        $languages = $this->language_manager->get_enabled_languages();

        if ( ! isset( $languages[ $current_lang ] ) ) {
            return;
        }

        $hreflang = $languages[ $current_lang ]['hreflang'];

        // Convert hreflang to OG locale format (e.g., en-US to en_US)
        $og_locale = str_replace( '-', '_', $hreflang );

        echo '<meta property="og:locale" content="' . esc_attr( $og_locale ) . '" />' . "\n";

        // Add alternate locales
        foreach ( $languages as $lang_code => $language ) {
            if ( $lang_code === $current_lang ) {
                continue;
            }

            $alt_locale = str_replace( '-', '_', $language['hreflang'] );
            echo '<meta property="og:locale:alternate" content="' . esc_attr( $alt_locale ) . '" />' . "\n";
        }
    }

    /**
     * Get current URL
     *
     * @return string
     */
    private function get_current_url() {
        global $wp;

        $current_url = home_url( add_query_arg( array(), $wp->request ) );

        // Remove lang parameter if exists
        $current_url = remove_query_arg( 'lang', $current_url );

        return $current_url;
    }

    /**
     * Validate hreflang code format
     *
     * @param string $hreflang Hreflang code.
     * @return bool
     */
    public static function validate_hreflang( $hreflang ) {
        // Valid formats:
        // - ISO 639-1 language code (2 letters): en, fr, de
        // - ISO 639-1 + ISO 3166-1 Alpha 2 country code: en-US, en-GB, fr-FR
        // - x-default (special case)

        if ( $hreflang === 'x-default' ) {
            return true;
        }

        // Check format: xx or xx-XX
        return (bool) preg_match( '/^[a-z]{2}(-[A-Z]{2})?$/', $hreflang );
    }

    /**
     * Get suggested hreflang codes
     *
     * @return array
     */
    public static function get_suggested_hreflang_codes() {
        return array(
            'en' => 'English',
            'en-US' => 'English (United States)',
            'en-GB' => 'English (United Kingdom)',
            'es' => 'Spanish',
            'es-ES' => 'Spanish (Spain)',
            'es-MX' => 'Spanish (Mexico)',
            'fr' => 'French',
            'fr-FR' => 'French (France)',
            'fr-CA' => 'French (Canada)',
            'de' => 'German',
            'de-DE' => 'German (Germany)',
            'de-AT' => 'German (Austria)',
            'it' => 'Italian',
            'it-IT' => 'Italian (Italy)',
            'pt' => 'Portuguese',
            'pt-BR' => 'Portuguese (Brazil)',
            'pt-PT' => 'Portuguese (Portugal)',
            'ru' => 'Russian',
            'ru-RU' => 'Russian (Russia)',
            'ja' => 'Japanese',
            'ja-JP' => 'Japanese (Japan)',
            'zh' => 'Chinese',
            'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'ko' => 'Korean',
            'ko-KR' => 'Korean (South Korea)',
            'ar' => 'Arabic',
            'ar-SA' => 'Arabic (Saudi Arabia)',
            'nl' => 'Dutch',
            'nl-NL' => 'Dutch (Netherlands)',
            'pl' => 'Polish',
            'pl-PL' => 'Polish (Poland)',
            'tr' => 'Turkish',
            'tr-TR' => 'Turkish (Turkey)',
            'uk' => 'Ukrainian',
            'uk-UA' => 'Ukrainian (Ukraine)',
        );
    }
}
