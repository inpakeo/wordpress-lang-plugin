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
                                               value="<?php echo esc_attr( $language['flag'] ); ?>"
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
                                               value="<?php echo esc_attr( $language['name'] ); ?>"
                                               placeholder="<?php _e( 'Language Name', 'wp-hreflang-manager' ); ?>"
                                               class="regular-text"
                                        />
                                    </div>

                                    <div class="language-hreflang">
                                        <input type="text"
                                               name="<?php echo $this->options_key; ?>[languages][<?php echo esc_attr( $lang_code ); ?>][hreflang]"
                                               value="<?php echo esc_attr( $language['hreflang'] ); ?>"
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
                                                   <?php checked( $language['enabled'], true ); ?>
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
                            <div class="add-language-form">
                                <input type="text" id="new-lang-code" placeholder="<?php _e( 'Code (e.g., en)', 'wp-hreflang-manager' ); ?>" class="regular-text" />
                                <input type="text" id="new-lang-name" placeholder="<?php _e( 'Name (e.g., English)', 'wp-hreflang-manager' ); ?>" class="regular-text" />
                                <input type="text" id="new-lang-hreflang" placeholder="<?php _e( 'Hreflang (e.g., en-US)', 'wp-hreflang-manager' ); ?>" class="regular-text" />
                                <input type="text" id="new-lang-flag" placeholder="<?php _e( 'Flag (e.g., 🇺🇸)', 'wp-hreflang-manager' ); ?>" class="small-text" />
                                <button type="button" id="add-language-btn" class="button button-secondary">
                                    <?php _e( 'Add Language', 'wp-hreflang-manager' ); ?>
                                </button>
                            </div>

                            <p class="description">
                                <?php _e( 'Popular hreflang codes:', 'wp-hreflang-manager' ); ?>
                                <strong>en</strong> (English),
                                <strong>es</strong> (Spanish),
                                <strong>fr</strong> (French),
                                <strong>de</strong> (German),
                                <strong>ru</strong> (Russian),
                                <strong>zh</strong> (Chinese)
                            </p>
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
                                        <?php foreach ( $languages as $lang_code => $language ) : ?>
                                            <option value="<?php echo esc_attr( $lang_code ); ?>" <?php selected( $default_language, $lang_code ); ?>>
                                                <?php echo esc_html( $language['flag'] . ' ' . $language['name'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
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
