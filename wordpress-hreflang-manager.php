<?php
/**
 * Plugin Name: WordPress Hreflang Manager
 * Plugin URI: https://github.com/inpakeo/wordpress-lang-plugin
 * Description: Simple and powerful multilingual WordPress plugin with automatic hreflang tags generation. Universal solution for any theme.
 * Version: 1.0.0
 * Author: Alexander Fedin
 * Author URI: https://github.com/o2alexanderfedin
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-hreflang-manager
 * Domain Path: /languages
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'WP_HREFLANG_VERSION', '1.0.0' );
define( 'WP_HREFLANG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_HREFLANG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_HREFLANG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class WP_Hreflang_Manager {

    /**
     * Single instance of the class
     *
     * @var WP_Hreflang_Manager
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return WP_Hreflang_Manager
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        require_once WP_HREFLANG_PLUGIN_DIR . 'includes/class-language-manager.php';
        require_once WP_HREFLANG_PLUGIN_DIR . 'includes/class-hreflang-generator.php';
        require_once WP_HREFLANG_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once WP_HREFLANG_PLUGIN_DIR . 'includes/class-language-widget.php';
        require_once WP_HREFLANG_PLUGIN_DIR . 'includes/class-language-switcher.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Initialize plugin components
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'default_language' => 'en',
            'languages' => array(
                'en' => array(
                    'name' => 'English',
                    'hreflang' => 'en',
                    'flag' => '🇬🇧',
                    'enabled' => true
                )
            ),
            'switcher_style' => 'dropdown',
            'show_flags' => true,
            'show_language_names' => true,
            'auto_redirect' => false
        );

        if ( ! get_option( 'wp_hreflang_options' ) ) {
            add_option( 'wp_hreflang_options', $default_options );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize language manager
        WP_Hreflang_Language_Manager::get_instance();

        // Initialize hreflang generator
        WP_Hreflang_Generator::get_instance();

        // Initialize admin settings (only in admin)
        if ( is_admin() ) {
            WP_Hreflang_Admin_Settings::get_instance();
        }

        // Register widget
        add_action( 'widgets_init', array( $this, 'register_widgets' ) );

        // Register shortcodes
        add_shortcode( 'language_switcher', array( 'WP_Hreflang_Language_Switcher', 'render_shortcode' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-hreflang-manager',
            false,
            dirname( WP_HREFLANG_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        register_widget( 'WP_Hreflang_Language_Widget' );
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'wp-hreflang-public',
            WP_HREFLANG_PLUGIN_URL . 'public/css/public-style.css',
            array(),
            WP_HREFLANG_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'wp-hreflang-public',
            WP_HREFLANG_PLUGIN_URL . 'public/js/public-script.js',
            array( 'jquery' ),
            WP_HREFLANG_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'wp-hreflang-public',
            'wpHreflangData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_hreflang_nonce' ),
                'currentLang' => WP_Hreflang_Language_Manager::get_current_language()
            )
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on plugin settings page and post edit pages
        if ( 'settings_page_wp-hreflang-settings' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wp-hreflang-admin',
            WP_HREFLANG_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            WP_HREFLANG_VERSION
        );

        // Enqueue JS (vanilla JS - no jQuery required)
        wp_enqueue_script(
            'wp-hreflang-admin',
            WP_HREFLANG_PLUGIN_URL . 'admin/js/admin-script.js',
            array(), // No dependencies - pure vanilla JS
            WP_HREFLANG_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'wp-hreflang-admin',
            'wpHreflangAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_hreflang_admin_nonce' )
            )
        );
    }
}

/**
 * Initialize the plugin
 */
function wp_hreflang_manager() {
    return WP_Hreflang_Manager::get_instance();
}

// Start the plugin
wp_hreflang_manager();
