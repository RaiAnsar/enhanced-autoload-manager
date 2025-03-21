<?php
/*
Plugin Name: Enhanced Autoload Manager
Version: 1.4
Description: Manages autoloaded data in the WordPress database, allowing for individual deletion or disabling of autoload entries.
Author: Rai Ansar
Author URI: https://raiansar.com
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: enhanced-autoload-manager
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Enhanced_Autoload_Manager {

    function __construct() {
        // Add the menu item under Tools
        add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
        // Handle actions for deleting and disabling autoloads
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        // Enqueue custom styles and scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // Add a link to the plugin page in the plugin list
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), [ $this, 'add_action_links' ] );
        // Add AJAX handler for refreshing autoload data
        add_action( 'wp_ajax_refresh_autoload_data', [ $this, 'refresh_autoload_data' ] );
        // Add AJAX handler for exporting autoload data
        add_action( 'wp_ajax_export_autoload_data', [ $this, 'export_autoload_data' ] );
        // Add AJAX handler for importing autoload data
        add_action( 'wp_ajax_import_autoload_data', [ $this, 'import_autoload_data' ] );
    }

    // Enqueue custom styles and scripts
    function enqueue_assets() {
        wp_enqueue_style( 'edal-manager-css', plugins_url( 'styles.css', __FILE__ ), array(), '1.4.0' );
        
        wp_enqueue_script( 'edal-manager-js', plugins_url( 'script.js', __FILE__ ), array('jquery'), '1.4.0', true );
        
        // Localize the script with new data
        wp_localize_script( 'edal-manager-js', 'edal_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'edal_nonce' ),
            'confirm_delete' => __( 'Are you sure you want to delete this option? This action cannot be undone.', 'enhanced-autoload-manager' ),
            'confirm_disable' => __( 'Are you sure you want to disable autoload for this option?', 'enhanced-autoload-manager' )
        ));
    }

    // Add the menu item under Tools
    function add_menu_item() {
        add_submenu_page( 'tools.php', 'Enhanced Autoload Manager', 'Enhanced Autoload Manager', 'manage_options', 'enhanced-autoload-manager', [ $this, 'display_page' ] );
    }

    // Add a link to the plugin page in the plugin list
    function add_action_links( $links ) {
        $links[] = '<a href="' . admin_url( 'tools.php?page=enhanced-autoload-manager' ) . '">' . __( 'Manage Autoloads', 'enhanced-autoload-manager' ) . '</a>';
        return $links;
    }

    // Function to get and process autoload data
    private function get_autoload_data($mode = 'basic', $search = '') {
        global $wpdb;
        
        // Get all options
        $all_options = wp_load_alloptions();
        $autoloads = [];
        
        foreach ($all_options as $key => $value) {
            // If search is provided, filter options by name
            if (!empty($search) && stripos($key, $search) === false) {
                continue;
            }
            
            $autoloads[] = [
                'option_name' => $key,
                'option_value' => $value,
                'option_size' => strlen($value),
                'is_core' => $this->is_core_autoload($key),
                'is_woocommerce' => strpos($key, 'woocommerce') === 0,
                'is_elementor' => strpos($key, '_elementor') === 0
            ];
        }
        
        // Filter by mode
        if ($mode === 'basic') {
            $autoloads = array_filter($autoloads, function($autoload) {
                return !$autoload['is_core'];
            });
        } elseif ($mode === 'woocommerce') {
            $autoloads = array_filter($autoloads, function($autoload) {
                return $autoload['is_woocommerce'];
            });
        } elseif ($mode === 'elementor') {
            $autoloads = array_filter($autoloads, function($autoload) {
                return $autoload['is_elementor'];
            });
        }
        
        return $autoloads;
    }
    
    // Calculate total autoload size
    private function calculate_total_autoload_size() {
        $all_options = wp_load_alloptions();
        $total_size = 0;
        
        foreach ($all_options as $key => $value) {
            $option_row = $GLOBALS['wpdb']->get_row(
                $GLOBALS['wpdb']->prepare(
                    "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
                    $key
                )
            );
            
            if ($option_row && $option_row->autoload === 'yes') {
                $total_size += strlen($value);
            }
        }
        
        update_option('total_autoload_size', $total_size, 'no');
        return $total_size;
    }
    
    // Display the plugin page
    function display_page() {
        global $wpdb;

        // Get the total autoload size in MBs
        $total_autoload_size = get_option('total_autoload_size');
        if (false === $total_autoload_size) {
            $total_autoload_size = $this->calculate_total_autoload_size();
        }
        $total_autoload_size_mb = round($total_autoload_size / 1024 / 1024, 2);

        // Check for mode, count, and search parameters - add nonce verification
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 'basic';
        $count = isset($_GET['count']) ? intval($_GET['count']) : 10;
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        // phpcs:enable
        $per_page = 20; // Items per page for pagination
        
        // Get filtered autoload data
        $autoloads = $this->get_autoload_data($mode, $search);

        usort($autoloads, function($a, $b) {
            return $b['option_size'] - $a['option_size'];
        });
        
        // Count total items for pagination
        $total_items = count($autoloads);
        $total_pages = ceil($total_items / $per_page);
        
        // Apply pagination if count is not set to all (-1)
        if ($count !== -1) {
            $autoloads = array_slice($autoloads, 0, $count);
        } else {
            // Calculate offset for pagination
            $offset = ($paged - 1) * $per_page;
            $autoloads = array_slice($autoloads, $offset, $per_page);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Enhanced Autoload Manager', 'enhanced-autoload-manager' ); ?></h1>
            <div class="notice notice-warning is-dismissible edal-notice">
                <p><?php esc_html_e( 'Warning: Please back up your database before making any changes. Expert mode is intended for experts only. Use Basic mode if you are new to autoloads or WordPress.', 'enhanced-autoload-manager' ); ?></p>
                <button type="button" class="notice-dismiss edal-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'enhanced-autoload-manager' ); ?></span></button>
            </div>
            
            <div class="edal-header-actions">
                <div class="edal-total-info">
                    <p class="total-autoload-size edal-total-size"><?php
                        $translated_text = sprintf(
                            /* translators: %s: total autoload size in MB */
                            __( 'The total autoload size is %s MB.', 'enhanced-autoload-manager' ),
                            esc_html( $total_autoload_size_mb )
                        );
                        echo esc_html( $translated_text );
                    ?></p>
                    <button type="button" id="edal-refresh-data" class="button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('edal_refresh_nonce')); ?>">
                        <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Data', 'enhanced-autoload-manager'); ?>
                    </button>
                </div>
                
                <div class="edal-tools">
                    <div class="edal-search-box">
                        <form method="get" action="">
                            <input type="hidden" name="page" value="enhanced-autoload-manager">
                            <input type="hidden" name="mode" value="<?php echo esc_attr($mode); ?>">
                            <input type="hidden" name="count" value="<?php echo esc_attr($count); ?>">
                            <input type="text" name="search" id="edal-search-input" placeholder="<?php esc_attr_e('Search options...', 'enhanced-autoload-manager'); ?>" value="<?php echo esc_attr($search); ?>">
                            <button type="submit" class="button"><span class="dashicons dashicons-search"></span></button>
                        </form>
                    </div>
                    
                    <div class="edal-import-export">
                        <button id="edal-export-btn" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Settings', 'enhanced-autoload-manager'); ?>
                        </button>
                        <button id="edal-import-btn" class="button button-secondary">
                            <span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import Settings', 'enhanced-autoload-manager'); ?>
                        </button>
                        <input type="file" id="edal-import-file" style="display: none;" accept=".json">
                    </div>
                </div>
            </div>

            <div class="nav-tab-wrapper edal-nav-tab-wrapper">
                <div class="edal-tab-section">
                    <a href="?page=enhanced-autoload-manager&mode=basic<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>" class="nav-tab <?php echo $mode === 'basic' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Basic', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=expert<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>" class="nav-tab <?php echo $mode === 'expert' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Expert', 'enhanced-autoload-manager' ); ?></a>
                </div>
                <div class="edal-tab-section">
                    <a href="?page=enhanced-autoload-manager&mode=all<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>" class="nav-tab <?php echo $mode === 'all' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'All', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=elementor<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>" class="nav-tab <?php echo $mode === 'elementor' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Elementor', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=woocommerce<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>" class="nav-tab <?php echo $mode === 'woocommerce' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'WooCommerce', 'enhanced-autoload-manager' ); ?></a>
                </div>
                <div class="edal-tab-section edal-tab-right">
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=10" class="nav-tab <?php echo $count === 10 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '10', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=20" class="nav-tab <?php echo $count === 20 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '20', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=50" class="nav-tab <?php echo $count === 50 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '50', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=100" class="nav-tab <?php echo $count === 100 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( '100', 'enhanced-autoload-manager' ); ?></a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=-1" class="nav-tab <?php echo $count === -1 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'All', 'enhanced-autoload-manager' ); ?></a>
                </div>
            </div>

            <div class="spacer"></div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 10%;"><?php esc_html_e( 'Autoload #', 'enhanced-autoload-manager' ); ?></th>
                        <th><?php esc_html_e( 'Option Name', 'enhanced-autoload-manager' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'enhanced-autoload-manager' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'enhanced-autoload-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $autoloads as $index => $autoload ) :
                        $size_kb = round( $autoload['option_size'] / 1024, 2 );
                        $size_display = $size_kb < 1024 ? $size_kb . ' KB' : round( $size_kb / 1024, 2 ) . ' MB';
                    ?>
                        <tr>
                            <td><?php echo esc_html( $index + 1 ); ?></td>
                            <td><?php echo esc_html( $autoload['option_name'] ); ?></td>
                            <td><?php echo esc_html( $size_display ); ?></td>
                            <td>
                                <?php $delete_nonce = wp_create_nonce('delete_autoload_' . $autoload['option_name']); ?>
                                <?php $disable_nonce = wp_create_nonce('disable_autoload_' . $autoload['option_name']); ?>
                                <a href="<?php echo esc_url( admin_url( 'tools.php?page=enhanced-autoload-manager&action=delete&option_name=' . urlencode( $autoload['option_name'] ) . '&_wpnonce=' . $delete_nonce ) ); ?>" class="button button-secondary edal-button edal-button-delete"><?php esc_html_e( 'Delete', 'enhanced-autoload-manager' ); ?></a>
                                <a href="<?php echo esc_url( admin_url( 'tools.php?page=enhanced-autoload-manager&action=disable&option_name=' . urlencode( $autoload['option_name'] ) . '&_wpnonce=' . $disable_nonce ) ); ?>" class="button button-secondary edal-button edal-button-disable"><?php esc_html_e( 'Disable', 'enhanced-autoload-manager' ); ?></a>
                                <a href="#" class="button button-secondary edal-button edal-button-expand" data-option="<?php echo esc_attr( $autoload['option_value'] ); ?>"><?php esc_html_e( 'Expand', 'enhanced-autoload-manager' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($count === -1 && $total_pages > 1): ?>
            <div class="edal-pagination">
                <?php
                $base_url = admin_url('tools.php?page=enhanced-autoload-manager&mode=' . $mode . '&count=' . $count);
                if (!empty($search)) {
                    $base_url .= '&search=' . urlencode($search);
                }
                
                // Previous page link
                if ($paged > 1) {
                    echo '<a href="' . esc_url($base_url . '&paged=' . ($paged - 1)) . '" class="button">&laquo; ' . esc_html__('Previous', 'enhanced-autoload-manager') . '</a>';
                }
                
                // Page numbers
                $start = max(1, $paged - 2);
                $end = min($total_pages, $paged + 2);
                
                if ($start > 1) {
                    echo '<a href="' . esc_url($base_url . '&paged=1') . '" class="button">1</a>';
                    if ($start > 2) {
                        echo '<span class="pagination-ellipsis">&hellip;</span>';
                    }
                }
                
                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $paged) {
                        echo '<span class="button button-primary">' . esc_html($i) . '</span>';
                    } else {
                        echo '<a href="' . esc_url($base_url . '&paged=' . $i) . '" class="button">' . esc_html($i) . '</a>';
                    }
                }
                
                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) {
                        echo '<span class="pagination-ellipsis">&hellip;</span>';
                    }
                    echo '<a href="' . esc_url($base_url . '&paged=' . $total_pages) . '" class="button">' . esc_html($total_pages) . '</a>';
                }
                
                // Next page link
                if ($paged < $total_pages) {
                    echo '<a href="' . esc_url($base_url . '&paged=' . ($paged + 1)) . '" class="button">' . esc_html__('Next', 'enhanced-autoload-manager') . ' &raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>
            
            <div class="edal-status">
                <span id="edal-status-message"></span>
            </div>
        </div>
        
        <!-- Option Value Modal -->
        <div id="option-value-modal" class="option-value-modal">
            <div class="option-value-content">
                <span class="close">&times;</span>
                <pre id="option-value-pre"></pre>
            </div>
        </div>
        
        <!-- Import Settings Modal -->
        <div id="import-modal" class="option-value-modal">
            <div class="option-value-content">
                <span class="close">&times;</span>
                <h2><?php esc_html_e('Import Autoload Settings', 'enhanced-autoload-manager'); ?></h2>
                <p><?php esc_html_e('Select a JSON file exported from Enhanced Autoload Manager.', 'enhanced-autoload-manager'); ?></p>
                <div class="import-controls">
                    <input type="file" id="import-file-input" accept=".json">
                    <button id="import-submit" class="button button-primary"><?php esc_html_e('Import', 'enhanced-autoload-manager'); ?></button>
                </div>
                <div id="import-status"></div>
            </div>
        </div>
        <?php
    }

    // Function to determine if an autoload option is core
    function is_core_autoload($option_name) {
        $core_autoloads = [
            '_transient_wp_core_block_css_files', 'rewrite_rules', 'wp_user_roles', 'cron', 'widget_', 'sidebars_widgets',
            'active_plugins', 'siteurl', 'home', 'admin_email', 'blogname', 'blogdescription', 'uploads_use_yearmonth_folders',
            'upload_path', 'upload_url_path', 'template', 'stylesheet', 'default_role', 'ping_sites', 'avatar_default',
            'avatar_rating', 'blog_charset', 'blog_public', 'gmt_offset', 'timezone_string', 'start_of_week', 'default_category',
            'default_ping_status', 'default_comment_status', 'permalink_structure', 'posts_per_page', 'posts_per_rss',
            'rss_use_excerpt', 'comment_order', 'thread_comments', 'thread_comments_depth', 'page_comments', 'comments_notify',
            'moderation_notify', 'moderation_keys', 'comment_max_links', 'require_name_email', 'show_avatars', 'close_comments_for_old_posts',
            'close_comments_days_old', 'page_comments', 'comments_per_page', 'default_comments_page', 'comment_moderation',
            'comment_whitelist', 'blacklist_keys', 'use_trackback', 'default_pingback_flag', 'show_on_front', 'page_on_front',
            'page_for_posts', 'link_manager_enabled', 'initial_db_version', 'db_version', 'finished_splitting_shared_terms',
            'finished_updating_comment_type', 'image_default_link_type', 'image_default_align', 'thumbnail_crop', 'uploads_use_yearmonth_folders',
            'use_balanceTags', 'use_smilies', 'moderation_keys', 'moderation_notify', 'thread_comments', 'page_comments',
            'comment_order', 'default_comments_page', 'page_comments', 'comments_per_page', 'avatar_default', 'avatar_rating',
            'close_comments_for_old_posts', 'comment_moderation', 'comment_whitelist', 'blacklist_keys', 'use_trackback',
            'default_pingback_flag', 'link_manager_enabled', 'initial_db_version', 'db_version', 'finished_splitting_shared_terms',
            'finished_updating_comment_type', 'medium_large_size_w', 'medium_large_size_h', 'total_autoload_size'
        ];
        foreach ($core_autoloads as $core) {
            if (strpos($option_name, $core) === 0) {
                return true;
            }
        }
        return false;
    }

    // AJAX handler for refreshing autoload data
    function refresh_autoload_data() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'edal_refresh_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'enhanced-autoload-manager')]);
        }
        
        // Calculate new total autoload size
        $total_size = $this->calculate_total_autoload_size();
        $total_size_mb = round($total_size / 1024 / 1024, 2);
        
        wp_send_json_success([
            'total_size_mb' => $total_size_mb,
            'message' => __('Autoload data refreshed successfully.', 'enhanced-autoload-manager')
        ]);
    }
    
    // AJAX handler for exporting autoload data
    function export_autoload_data() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'edal_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'enhanced-autoload-manager')]);
        }
        
        $export_data = [];
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $key => $value) {
            $option_row = $GLOBALS['wpdb']->get_row(
                $GLOBALS['wpdb']->prepare(
                    "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s",
                    $key
                )
            );
            
            if ($option_row) {
                $export_data[$key] = [
                    'value' => $value,
                    'autoload' => $option_row->autoload
                ];
            }
        }
        
        wp_send_json_success([
            'export_data' => $export_data,
            'filename' => 'autoload-manager-export-' . gmdate('Y-m-d') . '.json'
        ]);
    }
    
    // AJAX handler for importing autoload data
    function import_autoload_data() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'edal_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'enhanced-autoload-manager')]);
        }
        
        if (!isset($_POST['import_data']) || empty($_POST['import_data'])) {
            wp_send_json_error(['message' => __('No import data provided.', 'enhanced-autoload-manager')]);
        }
        
        $import_data = json_decode(stripslashes(sanitize_text_field(wp_unslash($_POST['import_data']))), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON data.', 'enhanced-autoload-manager')]);
        }
        
        $updated = 0;
        foreach ($import_data as $option_name => $option_data) {
            if (isset($option_data['autoload'])) {
                $autoload = $option_data['autoload'] === 'yes' ? 'yes' : 'no';
                update_option($option_name, $option_data['value'], $autoload);
                $updated++;
            }
        }
        
        // Refresh autoload size
        $this->calculate_total_autoload_size();
        
        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of updated options */
                __('%d options updated successfully.', 'enhanced-autoload-manager'), 
                $updated
            )
        ]);
    }
    
    // Handle the actions for deleting and disabling autoloads
    function handle_actions() {
        if ( ! isset( $_GET['page'], $_GET['_wpnonce'], $_GET['action'], $_GET['option_name'] ) || $_GET['page'] !== 'enhanced-autoload-manager' ) {
            return;
        }

        $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
        $option_name = sanitize_text_field( urldecode( sanitize_text_field( wp_unslash( $_GET['option_name'] ) ) ) );
        $nonce_action = $action . '_autoload_' . $option_name;

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
            wp_die( 'Nonce verification failed, action not allowed.', 'Nonce Verification Failed', array( 'response' => 403 ) );
        }

        global $wpdb;
        // Clear cache before making changes
        wp_cache_delete( 'alloptions' );
        delete_option( 'total_autoload_size' );

        if ( $action === 'delete' ) {
            delete_option($option_name);
        } elseif ( $action === 'disable' ) {
            update_option($option_name, get_option($option_name), 'no');
        }
        
        // Recalculate autoload size
        $this->calculate_total_autoload_size();

        wp_redirect( admin_url( 'tools.php?page=enhanced-autoload-manager' ) );
        exit;
    }
}

// Instantiate the class
new Enhanced_Autoload_Manager;
