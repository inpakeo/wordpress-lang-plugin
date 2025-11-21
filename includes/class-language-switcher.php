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
     * Get SVG flag URL or emoji fallback
     *
     * @param string $lang_code Language code (e.g., 'en', 'fr', 'de')
     * @param string $emoji_fallback Emoji flag fallback
     * @return array Array with 'type' (svg|emoji) and 'content' (URL or emoji)
     */
    private static function get_flag_display( $lang_code, $emoji_fallback = '' ) {
        // Extract 2-letter code from full code (e.g., "cs" from "cs-CZ")
        $code = strtolower( substr( explode( '-', $lang_code )[0], 0, 2 ) );

        // Build path to SVG file (using 1x1 square format)
        $svg_path = WP_HREFLANG_PLUGIN_DIR . 'public/images/flags/1x1/' . $code . '.svg';

        // Check if SVG exists
        if ( file_exists( $svg_path ) ) {
            return array(
                'type' => 'svg',
                'url' => WP_HREFLANG_PLUGIN_URL . 'public/images/flags/1x1/' . $code . '.svg',
                'alt' => $code
            );
        }

        return array(
            'type' => 'emoji',
            'content' => $emoji_fallback
        );
    }

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

        $language_manager = WP_Hreflang_Language_Manager::get_instance();
        $post_id = is_singular() && $post ? $post->ID : 0;

        // Get current language data
        $current_language = isset( $languages[ $current_lang ] ) ? $languages[ $current_lang ] : array();
        $current_flag_data = self::get_flag_display( $current_lang, isset( $current_language['flag'] ) ? $current_language['flag'] : '' );
        $current_name = isset( $current_language['name'] ) ? $current_language['name'] : $current_lang;

        // Build current flag display
        $current_flag_html = '';
        if ( $args['show_flags'] ) {
            if ( $current_flag_data['type'] === 'svg' ) {
                $current_flag_html = sprintf(
                    '<img src="%s" alt="%s" class="flag-svg" style="width:22px!important;height:22px!important;display:inline-block!important;object-fit:cover!important;" />',
                    esc_url( $current_flag_data['url'] ),
                    esc_attr( $current_name )
                );
            } else {
                $current_flag_html = esc_html( $current_flag_data['content'] );
            }
        }

        $current_display_name = $args['show_names'] ? $current_name : '';

        $output = '<div class="wp-hreflang-switcher wp-hreflang-dropdown wp-hreflang-custom-dropdown">';

        // Dropdown trigger
        $output .= '<div class="wp-hreflang-dropdown-trigger" data-post-id="' . esc_attr( $post_id ) . '">';
        if ( $args['show_flags'] ) {
            $output .= '<span class="wp-hreflang-current-flag">' . $current_flag_html . '</span>';
        }
        if ( $args['show_names'] ) {
            $output .= '<span class="wp-hreflang-current-name">' . esc_html( $current_display_name ) . '</span>';
        }
        $output .= '<span class="wp-hreflang-dropdown-arrow">▼</span>';
        $output .= '</div>';

        // Dropdown menu
        $output .= '<div class="wp-hreflang-dropdown-menu">';

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

            $flag_data = self::get_flag_display( $lang_code, isset( $language['flag'] ) ? $language['flag'] : '' );
            $name = isset( $language['name'] ) ? $language['name'] : $lang_code;

            // Build flag HTML
            $flag_html = '';
            if ( $args['show_flags'] ) {
                if ( $flag_data['type'] === 'svg' ) {
                    $flag_html = sprintf(
                        '<img src="%s" alt="%s" class="flag-svg" style="width:22px!important;height:22px!important;display:inline-block!important;object-fit:cover!important;" />',
                        esc_url( $flag_data['url'] ),
                        esc_attr( $name )
                    );
                } else {
                    $flag_html = esc_html( $flag_data['content'] );
                }
            }

            $item_class = $is_current ? 'wp-hreflang-dropdown-item wp-hreflang-current' : 'wp-hreflang-dropdown-item';

            $output .= sprintf(
                '<a href="%s" class="%s" data-lang="%s">',
                esc_url( $url ),
                esc_attr( $item_class ),
                esc_attr( $lang_code )
            );

            if ( $args['show_flags'] ) {
                $output .= '<span class="wp-hreflang-item-flag">' . $flag_html . '</span>';
            }

            if ( $args['show_names'] ) {
                $output .= '<span class="wp-hreflang-item-name">' . esc_html( $name ) . '</span>';
            }

            $output .= '</a>';
        }

        $output .= '</div>'; // .wp-hreflang-dropdown-menu
        $output .= '</div>'; // .wp-hreflang-switcher

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

            $flag_data = self::get_flag_display( $lang_code, isset( $language['flag'] ) ? $language['flag'] : '' );
            $name = $args['show_names'] && isset( $language['name'] ) ? $language['name'] : '';

            // Build flag HTML
            $flag_html = '';
            if ( $args['show_flags'] ) {
                if ( $flag_data['type'] === 'svg' ) {
                    $flag_html = sprintf(
                        '<img src="%s" alt="%s" class="flag-svg" style="width:20px!important;height:20px!important;display:inline-block!important;object-fit:cover!important;" /> ',
                        esc_url( $flag_data['url'] ),
                        esc_attr( $name )
                    );
                } else {
                    $flag_html = esc_html( $flag_data['content'] ) . ' ';
                }
            }

            $class = $is_current ? ' class="wp-hreflang-current"' : '';

            $output .= sprintf(
                '<li%s><a href="%s" data-lang="%s" data-post-id="%d" class="wp-hreflang-link">%s%s</a></li>',
                $class,
                esc_url( $url ),
                esc_attr( $lang_code ),
                $post_id,
                $flag_html,
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

            $flag_data = self::get_flag_display( $lang_code, isset( $language['flag'] ) ? $language['flag'] : '' );
            $name = isset( $language['name'] ) ? $language['name'] : $lang_code;

            $class = $is_current ? 'wp-hreflang-flag wp-hreflang-current' : 'wp-hreflang-flag';

            // Build flag HTML
            if ( $flag_data['type'] === 'svg' ) {
                $flag_html = sprintf(
                    '<img src="%s" alt="%s" class="flag-svg" style="width:32px!important;height:32px!important;display:inline-block!important;object-fit:cover!important;" />',
                    esc_url( $flag_data['url'] ),
                    esc_attr( $name )
                );
            } else {
                $flag_html = esc_html( $flag_data['content'] );
            }

            $output .= sprintf(
                '<a href="%s" data-lang="%s" data-post-id="%d" class="%s" title="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $lang_code ),
                $post_id,
                esc_attr( $class ),
                esc_attr( $name ),
                $flag_html
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

        // Get full current URL with protocol and query string
        $current_url = home_url( $wp->request );

        // Add query string if present
        if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
            $query_string = sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );
            $current_url = add_query_arg( $query_string, '', $current_url );
        }

        // Remove lang parameter if exists
        return remove_query_arg( 'lang', $current_url );
    }
}
