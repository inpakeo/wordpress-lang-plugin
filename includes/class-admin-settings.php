<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page in WordPress admin
 * with simple and intuitive interface for managing languages.
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Hreflang_Admin_Settings {

    /**
     * Single instance
     *
     * @var WP_Hreflang_Admin_Settings
     */
    private static $instance = null;

    /**
     * Options key
     *
     * @var string
     */
    private $options_key = 'wp_hreflang_options';

    /**
     * Get instance
     *
     * @return WP_Hreflang_Admin_Settings
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
    }

    /**
     * Get SVG flag URL
     *
     * @param string $lang_code Language code (e.g., 'en', 'fr', 'de')
     * @return string URL to SVG flag or empty string
     */
    private static function get_flag_svg_url( $lang_code ) {
        // Extract 2-letter code from full code (e.g., "cs" from "cs-CZ")
        $code = strtolower( substr( explode( '-', $lang_code )[0], 0, 2 ) );

        // Build path to SVG file (using 1x1 square format)
        $svg_path = WP_HREFLANG_PLUGIN_DIR . 'public/images/flags/1x1/' . $code . '.svg';

        // Check if SVG exists
        if ( file_exists( $svg_path ) ) {
            return WP_HREFLANG_PLUGIN_URL . 'public/images/flags/1x1/' . $code . '.svg';
        }

        return ''; // Return empty if no SVG found
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // AJAX handlers
        add_action( 'wp_ajax_wp_hreflang_add_language', array( $this, 'ajax_add_language' ) );
        add_action( 'wp_ajax_wp_hreflang_delete_language', array( $this, 'ajax_delete_language' ) );
        add_action( 'wp_ajax_wp_hreflang_toggle_language', array( $this, 'ajax_toggle_language' ) );
        add_action( 'wp_ajax_wp_hreflang_export_settings', array( $this, 'ajax_export_settings' ) );
        add_action( 'wp_ajax_wp_hreflang_import_settings', array( $this, 'ajax_import_settings' ) );

        // Add settings link on plugins page
        add_filter( 'plugin_action_links_' . WP_HREFLANG_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Hreflang Manager', 'wp-hreflang-manager' ),
            __( 'Hreflang Manager', 'wp-hreflang-manager' ),
            'manage_options',
            'wp-hreflang-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wp_hreflang_settings_group',
            $this->options_key,
            array( $this, 'sanitize_options' )
        );
    }

    /**
     * Get available languages database with flags and hreflang codes
     *
     * @return array
     */
    public static function get_available_languages_database() {
        return array(
            'en' => array( 'name' => 'English', 'hreflang' => 'en', 'flag' => '🇬🇧' ),
            'en-US' => array( 'name' => 'English (United States)', 'hreflang' => 'en-US', 'flag' => '🇺🇸' ),
            'en-GB' => array( 'name' => 'English (United Kingdom)', 'hreflang' => 'en-GB', 'flag' => '🇬🇧' ),
            'es' => array( 'name' => 'Spanish', 'hreflang' => 'es', 'flag' => '🇪🇸' ),
            'es-ES' => array( 'name' => 'Spanish (Spain)', 'hreflang' => 'es-ES', 'flag' => '🇪🇸' ),
            'es-MX' => array( 'name' => 'Spanish (Mexico)', 'hreflang' => 'es-MX', 'flag' => '🇲🇽' ),
            'fr' => array( 'name' => 'French', 'hreflang' => 'fr', 'flag' => '🇫🇷' ),
            'fr-FR' => array( 'name' => 'French (France)', 'hreflang' => 'fr-FR', 'flag' => '🇫🇷' ),
            'fr-CA' => array( 'name' => 'French (Canada)', 'hreflang' => 'fr-CA', 'flag' => '🇨🇦' ),
            'de' => array( 'name' => 'German', 'hreflang' => 'de', 'flag' => '🇩🇪' ),
            'de-DE' => array( 'name' => 'German (Germany)', 'hreflang' => 'de-DE', 'flag' => '🇩🇪' ),
            'de-AT' => array( 'name' => 'German (Austria)', 'hreflang' => 'de-AT', 'flag' => '🇦🇹' ),
            'de-CH' => array( 'name' => 'German (Switzerland)', 'hreflang' => 'de-CH', 'flag' => '🇨🇭' ),
            'it' => array( 'name' => 'Italian', 'hreflang' => 'it', 'flag' => '🇮🇹' ),
            'it-IT' => array( 'name' => 'Italian (Italy)', 'hreflang' => 'it-IT', 'flag' => '🇮🇹' ),
            'pt' => array( 'name' => 'Portuguese', 'hreflang' => 'pt', 'flag' => '🇵🇹' ),
            'pt-BR' => array( 'name' => 'Portuguese (Brazil)', 'hreflang' => 'pt-BR', 'flag' => '🇧🇷' ),
            'pt-PT' => array( 'name' => 'Portuguese (Portugal)', 'hreflang' => 'pt-PT', 'flag' => '🇵🇹' ),
            'ru' => array( 'name' => 'Russian', 'hreflang' => 'ru', 'flag' => '🇷🇺' ),
            'ru-RU' => array( 'name' => 'Russian (Russia)', 'hreflang' => 'ru-RU', 'flag' => '🇷🇺' ),
            'ja' => array( 'name' => 'Japanese', 'hreflang' => 'ja', 'flag' => '🇯🇵' ),
            'ja-JP' => array( 'name' => 'Japanese (Japan)', 'hreflang' => 'ja-JP', 'flag' => '🇯🇵' ),
            'zh' => array( 'name' => 'Chinese', 'hreflang' => 'zh', 'flag' => '🇨🇳' ),
            'zh-CN' => array( 'name' => 'Chinese (Simplified)', 'hreflang' => 'zh-CN', 'flag' => '🇨🇳' ),
            'zh-TW' => array( 'name' => 'Chinese (Traditional)', 'hreflang' => 'zh-TW', 'flag' => '🇹🇼' ),
            'zh-HK' => array( 'name' => 'Chinese (Hong Kong)', 'hreflang' => 'zh-HK', 'flag' => '🇭🇰' ),
            'ko' => array( 'name' => 'Korean', 'hreflang' => 'ko', 'flag' => '🇰🇷' ),
            'ko-KR' => array( 'name' => 'Korean (South Korea)', 'hreflang' => 'ko-KR', 'flag' => '🇰🇷' ),
            'ar' => array( 'name' => 'Arabic', 'hreflang' => 'ar', 'flag' => '🇸🇦' ),
            'ar-SA' => array( 'name' => 'Arabic (Saudi Arabia)', 'hreflang' => 'ar-SA', 'flag' => '🇸🇦' ),
            'ar-AE' => array( 'name' => 'Arabic (UAE)', 'hreflang' => 'ar-AE', 'flag' => '🇦🇪' ),
            'nl' => array( 'name' => 'Dutch', 'hreflang' => 'nl', 'flag' => '🇳🇱' ),
            'nl-NL' => array( 'name' => 'Dutch (Netherlands)', 'hreflang' => 'nl-NL', 'flag' => '🇳🇱' ),
            'nl-BE' => array( 'name' => 'Dutch (Belgium)', 'hreflang' => 'nl-BE', 'flag' => '🇧🇪' ),
            'pl' => array( 'name' => 'Polish', 'hreflang' => 'pl', 'flag' => '🇵🇱' ),
            'pl-PL' => array( 'name' => 'Polish (Poland)', 'hreflang' => 'pl-PL', 'flag' => '🇵🇱' ),
            'tr' => array( 'name' => 'Turkish', 'hreflang' => 'tr', 'flag' => '🇹🇷' ),
            'tr-TR' => array( 'name' => 'Turkish (Turkey)', 'hreflang' => 'tr-TR', 'flag' => '🇹🇷' ),
            'uk' => array( 'name' => 'Ukrainian', 'hreflang' => 'uk', 'flag' => '🇺🇦' ),
            'uk-UA' => array( 'name' => 'Ukrainian (Ukraine)', 'hreflang' => 'uk-UA', 'flag' => '🇺🇦' ),
            'sv' => array( 'name' => 'Swedish', 'hreflang' => 'sv', 'flag' => '🇸🇪' ),
            'sv-SE' => array( 'name' => 'Swedish (Sweden)', 'hreflang' => 'sv-SE', 'flag' => '🇸🇪' ),
            'no' => array( 'name' => 'Norwegian', 'hreflang' => 'no', 'flag' => '🇳🇴' ),
            'nb-NO' => array( 'name' => 'Norwegian (Bokmål)', 'hreflang' => 'nb-NO', 'flag' => '🇳🇴' ),
            'da' => array( 'name' => 'Danish', 'hreflang' => 'da', 'flag' => '🇩🇰' ),
            'da-DK' => array( 'name' => 'Danish (Denmark)', 'hreflang' => 'da-DK', 'flag' => '🇩🇰' ),
            'fi' => array( 'name' => 'Finnish', 'hreflang' => 'fi', 'flag' => '🇫🇮' ),
            'fi-FI' => array( 'name' => 'Finnish (Finland)', 'hreflang' => 'fi-FI', 'flag' => '🇫🇮' ),
            'cs' => array( 'name' => 'Czech', 'hreflang' => 'cs', 'flag' => '🇨🇿' ),
            'cs-CZ' => array( 'name' => 'Czech (Czech Republic)', 'hreflang' => 'cs-CZ', 'flag' => '🇨🇿' ),
            'hu' => array( 'name' => 'Hungarian', 'hreflang' => 'hu', 'flag' => '🇭🇺' ),
            'hu-HU' => array( 'name' => 'Hungarian (Hungary)', 'hreflang' => 'hu-HU', 'flag' => '🇭🇺' ),
            'ro' => array( 'name' => 'Romanian', 'hreflang' => 'ro', 'flag' => '🇷🇴' ),
            'ro-RO' => array( 'name' => 'Romanian (Romania)', 'hreflang' => 'ro-RO', 'flag' => '🇷🇴' ),
            'el' => array( 'name' => 'Greek', 'hreflang' => 'el', 'flag' => '🇬🇷' ),
            'el-GR' => array( 'name' => 'Greek (Greece)', 'hreflang' => 'el-GR', 'flag' => '🇬🇷' ),
            'th' => array( 'name' => 'Thai', 'hreflang' => 'th', 'flag' => '🇹🇭' ),
            'th-TH' => array( 'name' => 'Thai (Thailand)', 'hreflang' => 'th-TH', 'flag' => '🇹🇭' ),
            'vi' => array( 'name' => 'Vietnamese', 'hreflang' => 'vi', 'flag' => '🇻🇳' ),
            'vi-VN' => array( 'name' => 'Vietnamese (Vietnam)', 'hreflang' => 'vi-VN', 'flag' => '🇻🇳' ),
            'id' => array( 'name' => 'Indonesian', 'hreflang' => 'id', 'flag' => '🇮🇩' ),
            'id-ID' => array( 'name' => 'Indonesian (Indonesia)', 'hreflang' => 'id-ID', 'flag' => '🇮🇩' ),
            'he' => array( 'name' => 'Hebrew', 'hreflang' => 'he', 'flag' => '🇮🇱' ),
            'he-IL' => array( 'name' => 'Hebrew (Israel)', 'hreflang' => 'he-IL', 'flag' => '🇮🇱' ),
            'hi' => array( 'name' => 'Hindi', 'hreflang' => 'hi', 'flag' => '🇮🇳' ),
            'hi-IN' => array( 'name' => 'Hindi (India)', 'hreflang' => 'hi-IN', 'flag' => '🇮🇳' ),
            'bg' => array( 'name' => 'Bulgarian', 'hreflang' => 'bg', 'flag' => '🇧🇬' ),
            'bg-BG' => array( 'name' => 'Bulgarian (Bulgaria)', 'hreflang' => 'bg-BG', 'flag' => '🇧🇬' ),
            'hr' => array( 'name' => 'Croatian', 'hreflang' => 'hr', 'flag' => '🇭🇷' ),
            'hr-HR' => array( 'name' => 'Croatian (Croatia)', 'hreflang' => 'hr-HR', 'flag' => '🇭🇷' ),
            'sk' => array( 'name' => 'Slovak', 'hreflang' => 'sk', 'flag' => '🇸🇰' ),
            'sk-SK' => array( 'name' => 'Slovak (Slovakia)', 'hreflang' => 'sk-SK', 'flag' => '🇸🇰' ),
            'sl' => array( 'name' => 'Slovenian', 'hreflang' => 'sl', 'flag' => '🇸🇮' ),
            'sl-SI' => array( 'name' => 'Slovenian (Slovenia)', 'hreflang' => 'sl-SI', 'flag' => '🇸🇮' ),
        );
    }

    /**
     * Sanitize options
     *
     * @param array $input Input options.
     * @return array
     */
    public function sanitize_options( $input ) {
        $sanitized = array();

        // Sanitize default language
        if ( isset( $input['default_language'] ) ) {
            $sanitized['default_language'] = sanitize_text_field( $input['default_language'] );
        }

        // Sanitize languages
        if ( isset( $input['languages'] ) && is_array( $input['languages'] ) ) {
            $sanitized['languages'] = array();

            foreach ( $input['languages'] as $lang_code => $language ) {
                $lang_code = sanitize_text_field( $lang_code );

                $sanitized['languages'][ $lang_code ] = array(
                    'name' => isset( $language['name'] ) ? sanitize_text_field( $language['name'] ) : '',
                    'hreflang' => isset( $language['hreflang'] ) ? sanitize_text_field( $language['hreflang'] ) : '',
                    'flag' => isset( $language['flag'] ) ? sanitize_text_field( $language['flag'] ) : '',
                    'enabled' => isset( $language['enabled'] ) ? (bool) $language['enabled'] : true,
                );
            }
        }

        // Sanitize switcher style
        if ( isset( $input['switcher_style'] ) ) {
            $allowed_styles = array( 'dropdown', 'list', 'flags' );
            $sanitized['switcher_style'] = in_array( $input['switcher_style'], $allowed_styles ) ? $input['switcher_style'] : 'dropdown';
        }

        // Sanitize boolean options
        $sanitized['show_flags'] = isset( $input['show_flags'] ) ? (bool) $input['show_flags'] : true;
        $sanitized['show_language_names'] = isset( $input['show_language_names'] ) ? (bool) $input['show_language_names'] : true;
        $sanitized['auto_redirect'] = isset( $input['auto_redirect'] ) ? (bool) $input['auto_redirect'] : false;

        // Sanitize menu location
        if ( isset( $input['menu_location'] ) ) {
            $sanitized['menu_location'] = sanitize_text_field( $input['menu_location'] );
        }

        return $sanitized;
    }

    /**
     * Add action links on plugins page
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'options-general.php?page=wp-hreflang-settings' ) . '">' . __( 'Settings', 'wp-hreflang-manager' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = get_option( $this->options_key, array() );
        $languages = isset( $options['languages'] ) ? $options['languages'] : array();
        $default_language = isset( $options['default_language'] ) ? $options['default_language'] : 'en';
        $switcher_style = isset( $options['switcher_style'] ) ? $options['switcher_style'] : 'dropdown';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors(); ?>

            <div class="wp-hreflang-admin-container">
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'wp_hreflang_settings_group' );
                    ?>

                    <div class="wp-hreflang-section">
                        <h2><?php _e( 'Languages', 'wp-hreflang-manager' ); ?></h2>
                        <p><?php _e( 'Add and manage languages for your multilingual website. Each language will automatically generate hreflang tags.', 'wp-hreflang-manager' ); ?></p>

                        <div id="wp-hreflang-languages-list">
                            <?php foreach ( $languages as $lang_code => $language ) : ?>
                                <div class="wp-hreflang-language-item" data-lang-code="<?php echo esc_attr( $lang_code ); ?>">
                                    <div class="language-handle">
                                        <span class="dashicons dashicons-menu"></span>
                                    </div>

                                    <div class="language-flag">
                                        <input type="text"
                                               name="<?php echo $this->options_key; ?>[languages][<?php echo esc_attr( $lang_code ); ?>][flag]"
                                               value="<?php echo isset( $language['flag'] ) ? esc_attr( $language['flag'] ) : ''; ?>"
                                               placeholder="🇺🇸"
                                               class="small-text"
                                        />
                                    </div>

                                    <div class="language-code">
                                        <strong><?php echo esc_html( $lang_code ); ?></strong>
                                    </div>

                                    <div class="language-name">
                                        <input type="text"
                                               name="<?php echo $this->options_key; ?>[languages][<?php echo esc_attr( $lang_code ); ?>][name]"
                                               value="<?php echo isset( $language['name'] ) ? esc_attr( $language['name'] ) : ''; ?>"
                                               placeholder="<?php _e( 'Language Name', 'wp-hreflang-manager' ); ?>"
                                               class="regular-text"
                                        />
                                    </div>

                                    <div class="language-hreflang">
                                        <input type="text"
                                               name="<?php echo $this->options_key; ?>[languages][<?php echo esc_attr( $lang_code ); ?>][hreflang]"
                                               value="<?php echo isset( $language['hreflang'] ) ? esc_attr( $language['hreflang'] ) : ''; ?>"
                                               placeholder="en-US"
                                               class="regular-text"
                                        />
                                        <small><?php _e( 'Hreflang code (e.g., en, en-US, fr-FR)', 'wp-hreflang-manager' ); ?></small>
                                    </div>

                                    <div class="language-enabled">
                                        <label>
                                            <input type="checkbox"
                                                   name="<?php echo $this->options_key; ?>[languages][<?php echo esc_attr( $lang_code ); ?>][enabled]"
                                                   value="1"
                                                   <?php checked( isset( $language['enabled'] ) ? $language['enabled'] : false, true ); ?>
                                            />
                                            <?php _e( 'Enabled', 'wp-hreflang-manager' ); ?>
                                        </label>
                                    </div>

                                    <div class="language-actions">
                                        <button type="button" class="button delete-language" data-lang-code="<?php echo esc_attr( $lang_code ); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="wp-hreflang-add-language">
                            <h3><?php _e( 'Add New Language', 'wp-hreflang-manager' ); ?></h3>

                            <div class="add-language-card">
                                <div class="quick-select-section">
                                    <label for="language-quick-select" class="quick-select-label">
                                        <span class="dashicons dashicons-translation"></span>
                                        <?php _e( 'Quick Select', 'wp-hreflang-manager' ); ?>
                                    </label>

                                    <!-- Custom dropdown container -->
                                    <div class="custom-language-dropdown">
                                        <div class="dropdown-selected" id="custom-dropdown-trigger">
                                            <span class="selected-flag">
                                                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
                                                    <circle cx="256" cy="256" r="256" fill="#4a90e2"/>
                                                    <text x="256" y="320" font-size="200" text-anchor="middle" fill="white">🌐</text>
                                                </svg>
                                            </span>
                                            <span class="selected-text"><?php _e( '-- Choose a language --', 'wp-hreflang-manager' ); ?></span>
                                            <span class="dropdown-arrow">▼</span>
                                        </div>

                                        <div class="dropdown-menu" id="custom-dropdown-menu">
                                            <div class="dropdown-search">
                                                <input type="text"
                                                       id="language-search"
                                                       placeholder="<?php _e( 'Search language...', 'wp-hreflang-manager' ); ?>"
                                                       autocomplete="off">
                                            </div>
                                            <div class="dropdown-options" id="dropdown-options">
                                                <?php
                                                $available_langs = self::get_available_languages_database();
                                                foreach ( $available_langs as $code => $lang_data ) {
                                                    $flag_emoji = $lang_data['flag'];
                                                    $two_letter_code = substr( explode( '-', $code )[0], 0, 2 );
                                                    $flag_svg_url = self::get_flag_svg_url( $code );

                                                    // Prepare flag display: SVG or emoji fallback
                                                    if ( ! empty( $flag_svg_url ) ) {
                                                        $flag_display = sprintf( '<img src="%s" alt="%s" class="flag-svg" style="width:28px!important;height:28px!important;display:block!important;object-fit:cover!important;" />',
                                                            esc_url( $flag_svg_url ),
                                                            esc_attr( $lang_data['name'] )
                                                        );
                                                    } else {
                                                        $flag_display = esc_html( $flag_emoji );
                                                    }

                                                    printf(
                                                        '<div class="dropdown-option" data-value="%s" data-name="%s" data-hreflang="%s" data-flag="%s" data-flag-svg="%s" data-search="%s">
                                                            <span class="option-flag">%s</span>
                                                            <span class="option-text">
                                                                <span class="option-name">%s</span>
                                                                <span class="option-code">%s</span>
                                                            </span>
                                                        </div>',
                                                        esc_attr( $code ),
                                                        esc_attr( $lang_data['name'] ),
                                                        esc_attr( $lang_data['hreflang'] ),
                                                        esc_attr( $flag_emoji ),
                                                        esc_attr( $flag_svg_url ),
                                                        esc_attr( strtolower( $lang_data['name'] . ' ' . $code ) ),
                                                        $flag_display,
                                                        esc_html( $lang_data['name'] ),
                                                        esc_html( $code )
                                                    );
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hidden select for form submission (fallback) -->
                                    <select id="language-quick-select" class="quick-select-dropdown" style="display: none;">
                                        <option value=""><?php _e( '-- Choose a language --', 'wp-hreflang-manager' ); ?></option>
                                        <?php
                                        foreach ( $available_langs as $code => $lang_data ) {
                                            printf(
                                                '<option value="%s" data-name="%s" data-hreflang="%s" data-flag="%s">%s %s</option>',
                                                esc_attr( $code ),
                                                esc_attr( $lang_data['name'] ),
                                                esc_attr( $lang_data['hreflang'] ),
                                                esc_attr( $lang_data['flag'] ),
                                                esc_html( $lang_data['flag'] ),
                                                esc_html( $lang_data['name'] . ' (' . $code . ')' )
                                            );
                                        }
                                        ?>
                                    </select>

                                    <p class="help-text"><?php _e( 'Select from 70+ pre-configured languages with search', 'wp-hreflang-manager' ); ?></p>
                                </div>

                                <div class="divider-or">
                                    <span><?php _e( 'or enter custom values', 'wp-hreflang-manager' ); ?></span>
                                </div>

                                <div class="manual-input-section">
                                    <div class="input-grid">
                                        <div class="input-group">
                                            <label for="new-lang-code"><?php _e( 'Language Code', 'wp-hreflang-manager' ); ?> *</label>
                                            <input type="text"
                                                   id="new-lang-code"
                                                   placeholder="en"
                                                   class="lang-input"
                                                   maxlength="2" />
                                            <span class="input-hint"><?php _e( '2 letters', 'wp-hreflang-manager' ); ?></span>
                                        </div>

                                        <div class="input-group">
                                            <label for="new-lang-name"><?php _e( 'Language Name', 'wp-hreflang-manager' ); ?> *</label>
                                            <input type="text"
                                                   id="new-lang-name"
                                                   placeholder="English"
                                                   class="lang-input" />
                                            <span class="input-hint"><?php _e( 'Display name', 'wp-hreflang-manager' ); ?></span>
                                        </div>

                                        <div class="input-group">
                                            <label for="new-lang-hreflang"><?php _e( 'Hreflang Code', 'wp-hreflang-manager' ); ?> *</label>
                                            <input type="text"
                                                   id="new-lang-hreflang"
                                                   placeholder="en-US"
                                                   class="lang-input" />
                                            <span class="input-hint"><?php _e( 'ISO format', 'wp-hreflang-manager' ); ?></span>
                                        </div>

                                        <div class="input-group">
                                            <label for="new-lang-flag"><?php _e( 'Flag Emoji', 'wp-hreflang-manager' ); ?></label>
                                            <input type="text"
                                                   id="new-lang-flag"
                                                   placeholder="🇺🇸"
                                                   class="lang-input flag-input"
                                                   maxlength="4" />
                                            <span class="input-hint"><?php _e( 'Optional', 'wp-hreflang-manager' ); ?></span>
                                        </div>
                                    </div>

                                    <div class="action-row">
                                        <button type="button" id="add-language-btn" class="button button-primary button-large">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                            <?php _e( 'Add Language', 'wp-hreflang-manager' ); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="popular-languages">
                                    <span class="popular-label"><?php _e( 'Popular:', 'wp-hreflang-manager' ); ?></span>
                                    <span class="lang-badge">🇬🇧 English</span>
                                    <span class="lang-badge">🇪🇸 Spanish</span>
                                    <span class="lang-badge">🇫🇷 French</span>
                                    <span class="lang-badge">🇩🇪 German</span>
                                    <span class="lang-badge">🇷🇺 Russian</span>
                                    <span class="lang-badge">🇨🇳 Chinese</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wp-hreflang-section">
                        <h2><?php _e( 'General Settings', 'wp-hreflang-manager' ); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="default_language"><?php _e( 'Default Language', 'wp-hreflang-manager' ); ?></label>
                                </th>
                                <td>
                                    <select name="<?php echo $this->options_key; ?>[default_language]" id="default_language" class="regular-text">
                                        <?php if ( empty( $languages ) ) : ?>
                                            <option value=""><?php _e( 'No languages available. Please add a language first.', 'wp-hreflang-manager' ); ?></option>
                                        <?php else : ?>
                                            <?php foreach ( $languages as $lang_code => $language ) : ?>
                                                <option value="<?php echo esc_attr( $lang_code ); ?>" <?php selected( $default_language, $lang_code ); ?>>
                                                    <?php
                                                    $flag = isset( $language['flag'] ) ? $language['flag'] : '';
                                                    $name = isset( $language['name'] ) ? $language['name'] : $lang_code;
                                                    echo esc_html( $flag . ' ' . $name );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description"><?php _e( 'The default language for your website.', 'wp-hreflang-manager' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="switcher_style"><?php _e( 'Language Switcher Style', 'wp-hreflang-manager' ); ?></label>
                                </th>
                                <td>
                                    <select name="<?php echo $this->options_key; ?>[switcher_style]" id="switcher_style">
                                        <option value="dropdown" <?php selected( $switcher_style, 'dropdown' ); ?>><?php _e( 'Dropdown', 'wp-hreflang-manager' ); ?></option>
                                        <option value="list" <?php selected( $switcher_style, 'list' ); ?>><?php _e( 'List', 'wp-hreflang-manager' ); ?></option>
                                        <option value="flags" <?php selected( $switcher_style, 'flags' ); ?>><?php _e( 'Flags Only', 'wp-hreflang-manager' ); ?></option>
                                    </select>
                                    <p class="description"><?php _e( 'How the language switcher appears on your site.', 'wp-hreflang-manager' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e( 'Display Options', 'wp-hreflang-manager' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox"
                                                   name="<?php echo $this->options_key; ?>[show_flags]"
                                                   value="1"
                                                   <?php checked( isset( $options['show_flags'] ) ? $options['show_flags'] : true, true ); ?>
                                            />
                                            <?php _e( 'Show flags', 'wp-hreflang-manager' ); ?>
                                        </label>
                                        <br>
                                        <label>
                                            <input type="checkbox"
                                                   name="<?php echo $this->options_key; ?>[show_language_names]"
                                                   value="1"
                                                   <?php checked( isset( $options['show_language_names'] ) ? $options['show_language_names'] : true, true ); ?>
                                            />
                                            <?php _e( 'Show language names', 'wp-hreflang-manager' ); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php _e( 'Auto Redirect', 'wp-hreflang-manager' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="<?php echo $this->options_key; ?>[auto_redirect]"
                                               value="1"
                                               <?php checked( isset( $options['auto_redirect'] ) ? $options['auto_redirect'] : false, true ); ?>
                                        />
                                        <?php _e( 'Automatically redirect visitors based on their browser language', 'wp-hreflang-manager' ); ?>
                                    </label>
                                    <p class="description"><?php _e( 'First-time visitors will be redirected to their browser\'s language if available.', 'wp-hreflang-manager' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="menu_location"><?php _e( 'Add to Menu', 'wp-hreflang-manager' ); ?></label>
                                </th>
                                <td>
                                    <?php
                                    $nav_menus = get_registered_nav_menus();
                                    $selected_menu = isset( $options['menu_location'] ) ? $options['menu_location'] : '';
                                    ?>
                                    <select name="<?php echo $this->options_key; ?>[menu_location]" id="menu_location" class="regular-text">
                                        <option value=""><?php _e( 'None (use shortcode or widget)', 'wp-hreflang-manager' ); ?></option>
                                        <?php if ( ! empty( $nav_menus ) ) : ?>
                                            <?php foreach ( $nav_menus as $location => $description ) : ?>
                                                <option value="<?php echo esc_attr( $location ); ?>" <?php selected( $selected_menu, $location ); ?>>
                                                    <?php echo esc_html( $description ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else : ?>
                                            <option value="" disabled><?php _e( 'No menus registered by theme', 'wp-hreflang-manager' ); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e( 'Automatically add language switcher to selected menu location. Alternatively, use shortcode [language_switcher] or widget.', 'wp-hreflang-manager' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="wp-hreflang-section">
                        <h2><?php _e( 'Usage Instructions', 'wp-hreflang-manager' ); ?></h2>

                        <div class="usage-instructions">
                            <h3><?php _e( 'Widget', 'wp-hreflang-manager' ); ?></h3>
                            <p><?php _e( 'Go to Appearance → Widgets and add the "Language Switcher" widget to any widget area.', 'wp-hreflang-manager' ); ?></p>

                            <h3><?php _e( 'Shortcode', 'wp-hreflang-manager' ); ?></h3>
                            <p><?php _e( 'Use the following shortcode anywhere in your content:', 'wp-hreflang-manager' ); ?></p>
                            <code>[language_switcher]</code>

                            <h3><?php _e( 'Linking Translations', 'wp-hreflang-manager' ); ?></h3>
                            <p><?php _e( 'When editing a post or page, use the "Language Translations" metabox in the sidebar to:', 'wp-hreflang-manager' ); ?></p>
                            <ol>
                                <li><?php _e( 'Set the language of the current post/page', 'wp-hreflang-manager' ); ?></li>
                                <li><?php _e( 'Link it to translations in other languages', 'wp-hreflang-manager' ); ?></li>
                            </ol>

                            <h3><?php _e( 'How It Works', 'wp-hreflang-manager' ); ?></h3>
                            <p><?php _e( 'The plugin automatically:', 'wp-hreflang-manager' ); ?></p>
                            <ul>
                                <li><?php _e( 'Generates hreflang tags in the HTML head section', 'wp-hreflang-manager' ); ?></li>
                                <li><?php _e( 'Updates the lang attribute in the HTML tag', 'wp-hreflang-manager' ); ?></li>
                                <li><?php _e( 'Adds Open Graph locale tags for social media', 'wp-hreflang-manager' ); ?></li>
                                <li><?php _e( 'Handles language switching with cookies', 'wp-hreflang-manager' ); ?></li>
                            </ul>
                        </div>
                    </div>

                    <div class="wp-hreflang-section">
                        <h2><?php _e( 'Export / Import Settings', 'wp-hreflang-manager' ); ?></h2>
                        <p><?php _e( 'Backup your settings or transfer them to another site.', 'wp-hreflang-manager' ); ?></p>

                        <div class="wp-hreflang-export-import">
                            <div class="export-section">
                                <h3><?php _e( 'Export Settings', 'wp-hreflang-manager' ); ?></h3>
                                <p><?php _e( 'Download your current settings as a JSON file for backup or migration.', 'wp-hreflang-manager' ); ?></p>
                                <button type="button" id="wp-hreflang-export-btn" class="button button-secondary">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php _e( 'Export Settings', 'wp-hreflang-manager' ); ?>
                                </button>
                            </div>

                            <hr style="margin: 20px 0;">

                            <div class="import-section">
                                <h3><?php _e( 'Import Settings', 'wp-hreflang-manager' ); ?></h3>
                                <p><?php _e( 'Upload a JSON settings file to restore or migrate settings.', 'wp-hreflang-manager' ); ?></p>
                                <p class="description">
                                    <strong><?php _e( 'Warning:', 'wp-hreflang-manager' ); ?></strong>
                                    <?php _e( 'Importing will overwrite your current settings. Make sure to export first if you want to keep a backup.', 'wp-hreflang-manager' ); ?>
                                </p>
                                <input type="file" id="wp-hreflang-import-file" accept=".json" style="display: none;" />
                                <button type="button" id="wp-hreflang-import-btn" class="button button-secondary">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php _e( 'Import Settings', 'wp-hreflang-manager' ); ?>
                                </button>
                                <div id="wp-hreflang-import-result" style="margin-top: 10px;"></div>
                            </div>
                        </div>
                    </div>

                    <?php submit_button(); ?>
                </form>
            </div>
        </div>

        <!-- Custom Language Dropdown Script -->
        <script>
        (function() {
            console.log('🎨 Custom dropdown script loaded');

            const dropdownTrigger = document.getElementById('custom-dropdown-trigger');
            const dropdownMenu = document.getElementById('custom-dropdown-menu');
            const searchInput = document.getElementById('language-search');
            const dropdownOptions = document.getElementById('dropdown-options');
            const selectedFlag = document.querySelector('.selected-flag');
            const selectedText = document.querySelector('.selected-text');

            if (!dropdownTrigger || !dropdownMenu) {
                console.warn('Dropdown elements not found');
                return;
            }

            // Toggle dropdown
            dropdownTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const isActive = dropdownMenu.classList.contains('active');

                if (isActive) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            function openDropdown() {
                dropdownTrigger.classList.add('active');
                dropdownMenu.classList.add('active');
                setTimeout(() => searchInput && searchInput.focus(), 100);
                console.log('Dropdown opened');
            }

            function closeDropdown() {
                dropdownTrigger.classList.remove('active');
                dropdownMenu.classList.remove('active');
                if (searchInput) searchInput.value = '';
                showAllOptions();
                console.log('Dropdown closed');
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownTrigger.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    closeDropdown();
                }
            });

            // Search functionality
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    console.log('Searching:', searchTerm);
                    filterOptions(searchTerm);
                });

                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeDropdown();
                    }
                });
            }

            function filterOptions(searchTerm) {
                const options = dropdownOptions.querySelectorAll('.dropdown-option');
                let visibleCount = 0;

                options.forEach(option => {
                    const searchData = option.dataset.search || '';
                    if (!searchTerm || searchData.includes(searchTerm)) {
                        option.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        option.classList.add('hidden');
                    }
                });

                // Show no results message
                let noResultsMsg = dropdownOptions.querySelector('.dropdown-no-results');
                if (visibleCount === 0) {
                    if (!noResultsMsg) {
                        noResultsMsg = document.createElement('div');
                        noResultsMsg.className = 'dropdown-no-results';
                        noResultsMsg.textContent = 'No languages found';
                        dropdownOptions.appendChild(noResultsMsg);
                    }
                } else if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }

            function showAllOptions() {
                const options = dropdownOptions.querySelectorAll('.dropdown-option');
                options.forEach(option => option.classList.remove('hidden'));
                const noResultsMsg = dropdownOptions.querySelector('.dropdown-no-results');
                if (noResultsMsg) noResultsMsg.remove();
            }

            // Handle option selection
            if (dropdownOptions) {
                dropdownOptions.addEventListener('click', function(e) {
                    const option = e.target.closest('.dropdown-option');
                    if (!option) return;

                    const fullCode = option.dataset.value;
                    const name = option.dataset.name;
                    const hreflang = option.dataset.hreflang;
                    const flag = option.dataset.flag;
                    const flagSvg = option.dataset.flagSvg;

                    // Extract 2-letter code
                    const twoLetterCode = fullCode.split('-')[0].substring(0, 2);

                    console.log('🎯 Selected language:', {fullCode, twoLetterCode, name, hreflang, flag, flagSvg});

                    // Update dropdown display with SVG flag or emoji fallback
                    if (selectedFlag) {
                        if (flagSvg) {
                            selectedFlag.innerHTML = '<img src="' + flagSvg + '" alt="' + name + '" class="flag-svg" style="width:24px!important;height:24px!important;display:block!important;object-fit:cover!important;" />';
                        } else {
                            selectedFlag.textContent = flag;
                        }
                    }
                    if (selectedText) selectedText.textContent = name + ' (' + fullCode + ')';

                    // Mark option as selected
                    document.querySelectorAll('.dropdown-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    option.classList.add('selected');

                    // Fill form fields
                    const codeInput = document.getElementById('new-lang-code');
                    const nameInput = document.getElementById('new-lang-name');
                    const hreflangInput = document.getElementById('new-lang-hreflang');
                    const flagInput = document.getElementById('new-lang-flag');

                    if (codeInput) codeInput.value = twoLetterCode;
                    if (nameInput) nameInput.value = name;
                    if (hreflangInput) hreflangInput.value = hreflang;
                    if (flagInput) flagInput.value = flag;

                    console.log('✅ Fields filled! Code:', twoLetterCode);

                    // Close dropdown
                    setTimeout(() => closeDropdown(), 200);

                    // Focus add button
                    const addBtn = document.getElementById('add-language-btn');
                    if (addBtn) setTimeout(() => addBtn.focus(), 300);
                });
            }

            // Reset dropdown display when language is added
            const addBtn = document.getElementById('add-language-btn');
            if (addBtn) {
                addBtn.addEventListener('click', function() {
                    setTimeout(() => {
                        if (selectedFlag) {
                            selectedFlag.innerHTML = '<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><circle cx="256" cy="256" r="256" fill="#4a90e2"/><text x="256" y="320" font-size="200" text-anchor="middle" fill="white">🌐</text></svg>';
                        }
                        if (selectedText) selectedText.textContent = '-- Choose a language --';
                        document.querySelectorAll('.dropdown-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                    }, 500);
                });
            }

            console.log('✅ Custom dropdown initialized');
        })();
        </script>
        <?php
    }

    /**
     * AJAX: Add new language
     */
    public function ajax_add_language() {
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $lang_code = isset( $_POST['lang_code'] ) ? sanitize_text_field( $_POST['lang_code'] ) : '';
        $lang_name = isset( $_POST['lang_name'] ) ? sanitize_text_field( $_POST['lang_name'] ) : '';
        $lang_hreflang = isset( $_POST['lang_hreflang'] ) ? sanitize_text_field( $_POST['lang_hreflang'] ) : '';
        $lang_flag = isset( $_POST['lang_flag'] ) ? sanitize_text_field( $_POST['lang_flag'] ) : '';

        if ( empty( $lang_code ) || empty( $lang_name ) || empty( $lang_hreflang ) ) {
            wp_send_json_error( array( 'message' => 'All fields are required' ) );
        }

        $options = get_option( $this->options_key, array() );

        if ( isset( $options['languages'][ $lang_code ] ) ) {
            wp_send_json_error( array( 'message' => 'Language code already exists' ) );
        }

        $options['languages'][ $lang_code ] = array(
            'name' => $lang_name,
            'hreflang' => $lang_hreflang,
            'flag' => $lang_flag,
            'enabled' => true
        );

        update_option( $this->options_key, $options );

        wp_send_json_success( array( 'message' => 'Language added successfully' ) );
    }

    /**
     * AJAX: Delete language
     */
    public function ajax_delete_language() {
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $lang_code = isset( $_POST['lang_code'] ) ? sanitize_text_field( $_POST['lang_code'] ) : '';

        if ( empty( $lang_code ) ) {
            wp_send_json_error( array( 'message' => 'Invalid language code' ) );
        }

        $options = get_option( $this->options_key, array() );

        if ( ! isset( $options['languages'][ $lang_code ] ) ) {
            wp_send_json_error( array( 'message' => 'Language not found' ) );
        }

        // Check if this is the default language
        $default_language = isset( $options['default_language'] ) ? $options['default_language'] : '';
        if ( $lang_code === $default_language ) {
            wp_send_json_error( array( 'message' => 'Cannot delete the default language. Please set a different default language first.' ) );
        }

        unset( $options['languages'][ $lang_code ] );

        update_option( $this->options_key, $options );

        wp_send_json_success( array( 'message' => 'Language deleted successfully' ) );
    }

    /**
     * AJAX: Toggle language
     */
    public function ajax_toggle_language() {
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $lang_code = isset( $_POST['lang_code'] ) ? sanitize_text_field( $_POST['lang_code'] ) : '';
        $enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;

        if ( empty( $lang_code ) ) {
            wp_send_json_error( array( 'message' => 'Invalid language code' ) );
        }

        $options = get_option( $this->options_key, array() );

        if ( ! isset( $options['languages'][ $lang_code ] ) ) {
            wp_send_json_error( array( 'message' => 'Language not found' ) );
        }

        $options['languages'][ $lang_code ]['enabled'] = $enabled;

        update_option( $this->options_key, $options );

        wp_send_json_success( array( 'message' => 'Language updated successfully' ) );
    }

    /**
     * AJAX: Export settings
     */
    public function ajax_export_settings() {
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $options = get_option( $this->options_key, array() );

        // Prepare export data
        $export_data = array(
            'version' => WP_HREFLANG_VERSION,
            'export_date' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'options' => $options
        );

        // Return JSON data
        wp_send_json_success( array(
            'filename' => 'wp-hreflang-settings-' . date( 'Y-m-d-His' ) . '.json',
            'data' => wp_json_encode( $export_data, JSON_PRETTY_PRINT )
        ) );
    }

    /**
     * AJAX: Import settings
     */
    public function ajax_import_settings() {
        check_ajax_referer( 'wp_hreflang_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        // Check if file was uploaded
        if ( empty( $_FILES['import_file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded' ) );
        }

        $file = $_FILES['import_file'];

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array( 'message' => 'File upload error' ) );
        }

        // Check file type
        $file_extension = pathinfo( $file['name'], PATHINFO_EXTENSION );
        if ( $file_extension !== 'json' ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Please upload a JSON file.' ) );
        }

        // Check file size (max 2MB)
        $max_file_size = 2 * 1024 * 1024; // 2MB in bytes
        if ( $file['size'] > $max_file_size ) {
            wp_send_json_error( array( 'message' => 'File is too large. Maximum size is 2MB.' ) );
        }

        // Read file content
        $file_content = file_get_contents( $file['tmp_name'] );
        if ( $file_content === false ) {
            wp_send_json_error( array( 'message' => 'Failed to read file content' ) );
        }

        // Parse JSON
        $import_data = json_decode( $file_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'Invalid JSON format: ' . json_last_error_msg() ) );
        }

        // Validate import data structure
        if ( ! isset( $import_data['options'] ) || ! is_array( $import_data['options'] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid settings file structure' ) );
        }

        // Sanitize imported options
        $options = $this->sanitize_options( $import_data['options'] );

        // Update options
        update_option( $this->options_key, $options );

        wp_send_json_success( array(
            'message' => 'Settings imported successfully',
            'imported_from' => isset( $import_data['site_url'] ) ? $import_data['site_url'] : 'Unknown',
            'export_date' => isset( $import_data['export_date'] ) ? $import_data['export_date'] : 'Unknown'
        ) );
    }
}
