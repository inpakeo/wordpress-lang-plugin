<?php
/**
 * Language Widget Class
 *
 * WordPress widget for displaying language switcher.
 *
 * @package WP_Hreflang_Manager
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Hreflang_Language_Widget extends WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        $widget_ops = array(
            'classname' => 'wp_hreflang_language_widget',
            'description' => __( 'Display a language switcher for your multilingual site.', 'wp-hreflang-manager' ),
        );

        parent::__construct(
            'wp_hreflang_language_widget',
            __( 'Language Switcher', 'wp-hreflang-manager' ),
            $widget_ops
        );
    }

    /**
     * Front-end display of widget
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';
        $title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

        $style = ! empty( $instance['style'] ) ? $instance['style'] : '';
        $show_flags = isset( $instance['show_flags'] ) ? (bool) $instance['show_flags'] : true;
        $show_names = isset( $instance['show_names'] ) ? (bool) $instance['show_names'] : true;

        echo $args['before_widget'];

        if ( ! empty( $title ) ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        // Render language switcher
        echo WP_Hreflang_Language_Switcher::render( array(
            'style' => $style,
            'show_flags' => $show_flags,
            'show_names' => $show_names,
            'echo' => false
        ) );

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form
     *
     * @param array $instance Previously saved values from database.
     */
    public function form( $instance ) {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Languages', 'wp-hreflang-manager' );
        $style = ! empty( $instance['style'] ) ? $instance['style'] : '';
        $show_flags = isset( $instance['show_flags'] ) ? (bool) $instance['show_flags'] : true;
        $show_names = isset( $instance['show_names'] ) ? (bool) $instance['show_names'] : true;

        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php _e( 'Title:', 'wp-hreflang-manager' ); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text"
                   value="<?php echo esc_attr( $title ); ?>"
            />
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>">
                <?php _e( 'Display Style:', 'wp-hreflang-manager' ); ?>
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr( $this->get_field_id( 'style' ) ); ?>"
                    name="<?php echo esc_attr( $this->get_field_name( 'style' ) ); ?>"
            >
                <option value="" <?php selected( $style, '' ); ?>>
                    <?php _e( '-- Use Global Setting --', 'wp-hreflang-manager' ); ?>
                </option>
                <option value="dropdown" <?php selected( $style, 'dropdown' ); ?>>
                    <?php _e( 'Dropdown', 'wp-hreflang-manager' ); ?>
                </option>
                <option value="list" <?php selected( $style, 'list' ); ?>>
                    <?php _e( 'List', 'wp-hreflang-manager' ); ?>
                </option>
                <option value="flags" <?php selected( $style, 'flags' ); ?>>
                    <?php _e( 'Flags Only', 'wp-hreflang-manager' ); ?>
                </option>
            </select>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_flags' ) ); ?>"
                   value="1"
                   <?php checked( $show_flags, true ); ?>
            />
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_flags' ) ); ?>">
                <?php _e( 'Show flags', 'wp-hreflang-manager' ); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   id="<?php echo esc_attr( $this->get_field_id( 'show_names' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_names' ) ); ?>"
                   value="1"
                   <?php checked( $show_names, true ); ?>
            />
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_names' ) ); ?>">
                <?php _e( 'Show language names', 'wp-hreflang-manager' ); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     * @return array Updated safe values to be saved.
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();

        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
        $instance['style'] = ( ! empty( $new_instance['style'] ) ) ? sanitize_text_field( $new_instance['style'] ) : '';
        $instance['show_flags'] = isset( $new_instance['show_flags'] ) ? (bool) $new_instance['show_flags'] : false;
        $instance['show_names'] = isset( $new_instance['show_names'] ) ? (bool) $new_instance['show_names'] : false;

        return $instance;
    }
}
