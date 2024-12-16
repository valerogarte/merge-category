<?php
/*
Plugin Name: Merge Category
Description: Plugin to merge WooCommerce categories.
Version: 1.0
Author: Daniel Valero González
Text Domain: merge-category
Domain Path: /languages
*/

// Prevent direct access
if ( !defined( 'ABSPATH' ) ) exit;

// Load plugin text domain
function merge_category_load_textdomain() {
    load_plugin_textdomain( 'merge-category', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'merge_category_load_textdomain' );

// Enqueue admin styles
function merge_category_enqueue_admin_styles() {
    wp_enqueue_style( 'merge-category-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), '1.0' );
}
add_action( 'admin_enqueue_scripts', 'merge_category_enqueue_admin_styles' );

// Add admin menu
function merge_category_add_menu() {
    add_menu_page(
        __('Merge Categories', 'merge-category'),
        __('Merge Categories', 'merge-category'),
        'manage_options',
        'merge-category',
        'merge_category_page'
    );
}
add_action( 'admin_menu', 'merge_category_add_menu' );

// Add link on the plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'merge_category_action_links' );
function merge_category_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=merge-category' ) ) . '">' . esc_html__( 'Merge Categories', 'merge-category' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

// Function to display hierarchical categories with proper escaping
function merge_category_display_categories_hierarchical( $categories, $parent_id = 0, $level = 0, $input_name = 'source_category' ) {
    foreach ( $categories as $category ) {
        if ( $category->parent == $parent_id ) {
            $count = intval( $category->count );
            $slug = sanitize_text_field( $category->slug );
            echo '<label class="elements-' . esc_attr( $count ) . '">' . str_repeat( "|____", $level ) . '<input type="radio" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $category->term_id ) . '"> ' . esc_html( $category->name ) . ' <i style="font-size: 10px;">[' . esc_html( $slug ) . ']</i> (' . esc_html( $count ) . ')</label><br>';
            merge_category_display_categories_hierarchical( $categories, $category->term_id, $level + 1, $input_name );
        }
    }
}

// Plugin page content with admin notices
function merge_category_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Process the form
    if ( isset( $_POST['merge_categories_nonce'] ) && wp_verify_nonce( $_POST['merge_categories_nonce'], 'merge_categories' ) ) {
        $source_category = isset( $_POST['source_category'] ) ? intval( $_POST['source_category'] ) : 0;
        $destination_category = isset( $_POST['destination_category'] ) ? intval( $_POST['destination_category'] ) : 0;

        if ( $source_category && $destination_category ) {
            if ( $source_category != $destination_category ) {
                merge_category_merge_categories( $source_category, $destination_category );
                add_action( 'admin_notices', 'merge_category_success_notice' );
            } else {
                add_action( 'admin_notices', 'merge_category_same_category_notice' );
            }
        } else {
            add_action( 'admin_notices', 'merge_category_selection_notice' );
        }
    }

    // Get all product categories
    $categories = get_terms( array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ) );

    // Detect categories with numeric suffix and their base category
    $categories_to_merge = array();
    foreach ( $categories as $category ) {
        if ( preg_match( '/-\d+$/', $category->slug ) ) {
            $base_slug = preg_replace( '/-\d+$/', '', $category->slug );
            foreach ( $categories as $base_category ) {
                if ( $base_category->slug === $base_slug ) {
                    $categories_to_merge[] = array(
                        'source'  => $category,
                        'destination' => $base_category
                    );
                    break;
                }
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Merge WooCommerce Categories', 'merge-category' ) . '</h1>';
    ?>
    <form method="post">
        <?php wp_nonce_field( 'merge_categories', 'merge_categories_nonce' ); ?>
        <div style="display: flex; justify-content: space-between;">
            <div style="flex: 1; margin-right: 20px;">
                <h2><?php _e('Source Category', 'merge-category'); ?></h2>
                <?php merge_category_display_categories_hierarchical($categories, 0, 0, 'source_category'); ?>
            </div>
            <div style="flex: 1;">
                <h2><?php _e('Destination Category', 'merge-category'); ?></h2>
                <?php merge_category_display_categories_hierarchical($categories, 0, 0, 'destination_category'); ?>
            </div>
        </div>
        <?php submit_button( __('Merge Categories', 'merge-category') ); ?>
    </form>
    <form method="post">
        <?php wp_nonce_field( 'merge_categories_auto', 'merge_categories_auto_nonce' ); ?>
        <?php if ( ! empty( $categories_to_merge ) ) : ?>
            <h2><?php _e('Detected categories to merge', 'merge-category'); ?></h2>
            <?php foreach ( $categories_to_merge as $pair ) : ?>
                <label>
                    <input type="checkbox" name="categories_to_merge[]" value="<?php echo esc_attr( $pair['source']->term_id . ',' . $pair['destination']->term_id ); ?>">
                    <?php printf( __('Merge \'%1$s\' <i style="font-size: 10px;">[%2$s]</i> (%3$d) ------------> \'%4$s\' <i style="font-size: 10px;">[%5$s]</i> (%6$d)', 'merge-category'), esc_html( $pair['source']->name ), esc_html( $pair['source']->slug ), $pair['source']->count, esc_html( $pair['destination']->name ), esc_html( $pair['destination']->slug ), $pair['destination']->count ); ?>
                </label><br>
            <?php endforeach; ?>
            <?php submit_button( __('Merge Selected Categories', 'merge-category') ); ?>
        <?php else : ?>
            <p><?php _e('No categories detected for automatic merging.', 'merge-category'); ?></p>
        <?php endif; ?>
    </form>
    <?php
    echo '</div>';
}

// Success notice
function merge_category_success_notice() {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Categories merged successfully.', 'merge-category' ) . '</p></div>';
}

// Same category notice
function merge_category_same_category_notice() {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'The source and destination categories cannot be the same.', 'merge-category' ) . '</p></div>';
}

// Selection notice
function merge_category_selection_notice() {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Please select two different categories.', 'merge-category' ) . '</p></div>';
}

// Function to merge categories
function merge_category_merge_categories( $source_id, $destination_id ) {
    // Get products in the source category
    $products = get_posts( array(
        'post_type' => 'product',
        'numberposts' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'terms' => $source_id,
            ),
        ),
        'fields' => 'ids',
    ) );

    // Assign products to the destination category
    foreach ( $products as $product_id ) {
        wp_set_object_terms( $product_id, array( $destination_id ), 'product_cat', true );
    }

    // Delete the source category
    wp_delete_term( $source_id, 'product_cat' );
}

// Process the automatic merge form with admin notices
function merge_category_process_auto_merge() {
    if ( isset( $_POST['merge_categories_auto_nonce'] ) && wp_verify_nonce( $_POST['merge_categories_auto_nonce'], 'merge_categories_auto' ) ) {
        if ( ! empty( $_POST['categories_to_merge'] ) && is_array( $_POST['categories_to_merge'] ) ) {
            foreach ( $_POST['categories_to_merge'] as $pair ) {
                list( $source_id, $destination_id ) = explode( ',', sanitize_text_field( $pair ) );
                merge_category_merge_categories( intval( $source_id ), intval( $destination_id ) );
            }
            add_action( 'admin_notices', 'merge_category_auto_success_notice' );
        } else {
            add_action( 'admin_notices', 'merge_category_auto_error_notice' );
        }
    }
}
add_action( 'admin_init', 'merge_category_process_auto_merge' );

// Auto merge success notice
function merge_category_auto_success_notice() {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Categories merged successfully.', 'merge-category' ) . '</p></div>';
}

// Auto merge error notice
function merge_category_auto_error_notice() {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'No categories selected for merging.', 'merge-category' ) . '</p></div>';
}
?>
