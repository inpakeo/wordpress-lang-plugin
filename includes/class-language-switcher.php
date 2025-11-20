<?php
/**
 * Language Switcher Class
 *
 * Handles rendering of language switcher in different formats
 * and provides shortcode functionality.
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Hreflang_Language_Switcher {

    /**
     * Render language switcher
     *
     * @param array $args Arguments for rendering.
     * @return string HTML output.
     */
    public static function render( $args = array() ) {
        $defaults = array(
            'style' => '', // Will use global setting if empty
            'show_flags' => null,
            'show_names' => null,
            'show_current' => true,
            'echo' => false
        );

        $args = wp_parse_args( $args, $defaults );

        // Get plugin options
        $options = get_option( 'wp_hreflang_options', array() );

        // Use global settings if not specified
        if ( empty( $args['style'] ) ) {
            $args['style'] = isset( $options['switcher_style'] ) ? $options['switcher_style'] : 'dropdown';
        }

        if ( null === $args['show_flags'] ) {
            $args['show_flags'] = isset( $options['show_flags'] ) ? $options['show_flags'] : true;
        }

        if ( null === $args['show_names'] ) {
            $args['show_names'] = isset( $options['show_language_names'] ) ? $options['show_language_names'] : true;
        }

        // Get languages
        $language_manager = WP_Hreflang_Language_Manager::get_instance();
        $languages = $language_manager->get_enabled_languages();
        $current_lang = WP_Hreflang_Language_Manager::get_current_language();

        if ( empty( $languages ) || count( $languages ) < 2 ) {
            return '';
        }

        // Generate output based on style
        $output = '';

        switch ( $args['style'] ) {
            case 'dropdown':
                $output = self::render_dropdown( $languages, $current_lang, $args );
                break;

            case 'list':
                $output = self::render_list( $languages, $current_lang, $args );
                break;

            case 'flags':
                $output = self::render_flags( $languages, $current_lang, $args );
                break;

            default:
                $output = self::render_dropdown( $languages, $current_lang, $args );
                break;
        }

        if ( $args['echo'] ) {
            echo $output;
            return '';
        }

        return $output;
    }

    /**
     * Render dropdown style switcher
     *
     * @param array  $languages    Available languages.
     * @param string $current_lang Current language code.
     * @param array  $args         Arguments.
     * @return string HTML output.
     */
    private static function render_dropdown( $languages, $current_lang, $args ) {
        global $post;

        $post_id = is_singular() && $post ? $post->ID : 0;

        $output = '<div class="wp-hreflang-switcher wp-hreflang-dropdown">';
        $output .= '<select class="wp-hreflang-select" data-post-id="' . esc_attr( $post_id ) . '">';

        foreach ( $languages as $lang_code => $language ) {
            $flag = $args['show_flags'] ? $language['flag'] . ' ' : '';
            $name = $args['show_names'] ? $language['name'] : '';
            $selected = ( $lang_code === $current_lang ) ? ' selected' : '';

            $output .= sprintf(
                '<option value="%s"%s>%s%s</option>',
                esc_attr( $lang_code ),
                $selected,
                esc_html( $flag ),
                esc_html( $name )
            );
        }

        $output .= '</select>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render list style switcher
     *
     * @param array  $languages    Available languages.
     * @param string $current_lang Current language code.
     * @param array  $args         Arguments.
     * @return string HTML output.
     */
    private static function render_list( $languages, $current_lang, $args ) {
        global $post;

        $language_manager = WP_Hreflang_Language_Manager::get_instance();
        $post_id = is_singular() && $post ? $post->ID : 0;

        $output = '<div class="wp-hreflang-switcher wp-hreflang-list">';
        $output .= '<ul class="wp-hreflang-list-items">';

        foreach ( $languages as $lang_code => $language ) {
            $flag = $args['show_flags'] ? $language['flag'] . ' ' : '';
            $name = $args['show_names'] ? $language['name'] : '';
            $is_current = ( $lang_code === $current_lang );

            // Get translation URL
            $url = '#';
            if ( $post_id > 0 ) {
                $translation_id = $language_manager->get_translation( $post_id, $lang_code );
                if ( $translation_id ) {
                    $url = get_permalink( $translation_id );
                } else {
                    $url = add_query_arg( 'lang', $lang_code, home_url( '/' ) );
                }
            } else {
                $url = add_query_arg( 'lang', $lang_code, self::get_current_url() );
            }

            $class = $is_current ? ' class="wp-hreflang-current"' : '';

            $output .= sprintf(
                '<li%s><a href="%s" data-lang="%s" data-post-id="%d" class="wp-hreflang-link">%s%s</a></li>',
                $class,
                esc_url( $url ),
                esc_attr( $lang_code ),
                $post_id,
                esc_html( $flag ),
                esc_html( $name )
            );
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Render flags style switcher
     *
     * @param array  $languages    Available languages.
     * @param string $current_lang Current language code.
     * @param array  $args         Arguments.
     * @return string HTML output.
     */
    private static function render_flags( $languages, $current_lang, $args ) {
        global $post;

        $language_manager = WP_Hreflang_Language_Manager::get_instance();
        $post_id = is_singular() && $post ? $post->ID : 0;

        $output = '<div class="wp-hreflang-switcher wp-hreflang-flags">';

        foreach ( $languages as $lang_code => $language ) {
            $is_current = ( $lang_code === $current_lang );

            // Get translation URL
            $url = '#';
            if ( $post_id > 0 ) {
                $translation_id = $language_manager->get_translation( $post_id, $lang_code );
                if ( $translation_id ) {
                    $url = get_permalink( $translation_id );
                } else {
                    $url = add_query_arg( 'lang', $lang_code, home_url( '/' ) );
                }
            } else {
                $url = add_query_arg( 'lang', $lang_code, self::get_current_url() );
            }

            $class = $is_current ? 'wp-hreflang-flag wp-hreflang-current' : 'wp-hreflang-flag';

            $output .= sprintf(
                '<a href="%s" data-lang="%s" data-post-id="%d" class="%s" title="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $lang_code ),
                $post_id,
                esc_attr( $class ),
                esc_attr( $language['name'] ),
                esc_html( $language['flag'] )
            );
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'style' => '',
            'show_flags' => null,
            'show_names' => null,
        ), $atts, 'language_switcher' );

        // Convert string booleans to actual booleans
        if ( null !== $atts['show_flags'] ) {
            $atts['show_flags'] = filter_var( $atts['show_flags'], FILTER_VALIDATE_BOOLEAN );
        }

        if ( null !== $atts['show_names'] ) {
            $atts['show_names'] = filter_var( $atts['show_names'], FILTER_VALIDATE_BOOLEAN );
        }

        return self::render( $atts );
    }

    /**
     * Get current URL
     *
     * @return string
     */
    private static function get_current_url() {
        global $wp;
        $current_url = home_url( add_query_arg( array(), $wp->request ) );
        return remove_query_arg( 'lang', $current_url );
    }
}
