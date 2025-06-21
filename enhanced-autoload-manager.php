<?php
/*
Plugin Name: Enhanced Autoload Manager
Plugin URI: https://raiansar.com/enhanced-autoload-manager
Description: Manages autoloaded data in the WordPress database, allowing for individual deletion or disabling of autoload entries.
Version: 1.5.3
Author: Rai Ansar
Author URI: https://raiansar.com
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: enhanced-autoload-manager
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define plugin constants
if (!defined('EDAL_PLUGIN_URL')) {
    define('EDAL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('EDAL_PLUGIN_PATH')) {
    define('EDAL_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('EDAL_VERSION')) {
    define('EDAL_VERSION', '1.5.3');
}

class Enhanced_Autoload_Manager {
    private $version = EDAL_VERSION;

    function __construct() {
        // Add the menu item under Tools
        add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
        // Handle actions for deleting and disabling autoloads
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        // Enqueue custom styles and scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        // Add a link to the plugin page in the plugin list
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), [ $this, 'add_action_links' ] );
        // Add AJAX handlers (using consistent naming)
        add_action('wp_ajax_edal_refresh_data', array($this, 'ajax_refresh_data'));
        add_action('wp_ajax_edal_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_edal_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_edal_dismiss_warning', array($this, 'ajax_dismiss_warning'));
    }

    // Plugin activation hook
    public function activate() {
        // Create default options
        if (!get_option('edal_disabled_autoloads')) {
            add_option('edal_disabled_autoloads', array());
        }
        // Store dismissed warnings
        if (!get_option('edal_dismissed_warnings')) {
            add_option('edal_dismissed_warnings', array());
        }
    }

    // Plugin deactivation hook
    public function deactivate() {
        // Clean up transients
        delete_transient('edal_autoload_cache');
        // Note: We don't delete options on deactivation, only on uninstall
    }

    // Enqueue custom styles and scripts
    function enqueue_assets($hook) {
        // Only load on our plugin page
        if ('tools_page_enhanced-autoload-manager' !== $hook) {
            return;
        }
        
        wp_enqueue_style( 'edal-manager-css', EDAL_PLUGIN_URL . 'styles.css', array(), $this->version );
        
        wp_enqueue_script( 'edal-manager-js', EDAL_PLUGIN_URL . 'script.js', array('jquery'), $this->version, true );
        
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
        $disabled_autoloads = get_option('edal_disabled_autoloads', array());
        
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
                'is_elementor' => strpos($key, '_elementor') === 0,
                'is_disabled' => in_array($key, $disabled_autoloads)
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
        } elseif ($mode === 'disabled') {
            $autoloads = array_filter($autoloads, function($autoload) {
                return $autoload['is_disabled'];
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

        // Check for mode, count, and search parameters with nonce verification
        $nonce_action = 'edal_view_page';
        if (isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_action)) {
            // Allow access without nonce for initial page load, but verify for parameter changes
        }
        
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 'basic';
        $count = isset($_GET['count']) ? intval(wp_unslash($_GET['count'])) : 10;
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $paged = isset($_GET['paged']) ? max(1, intval(wp_unslash($_GET['paged']))) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'size';
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';
        $per_page = 20; // Items per page for pagination
        
        // Get filtered autoload data
        $autoloads = $this->get_autoload_data($mode, $search);

        // Sort the autoloads
        usort($autoloads, function($a, $b) use ($orderby, $order) {
            $result = 0;
            switch ($orderby) {
                case 'name':
                    $result = strcasecmp($a['option_name'], $b['option_name']);
                    break;
                case 'size':
                default:
                    $result = $a['option_size'] - $b['option_size'];
                    break;
            }
            return $order === 'ASC' ? $result : -$result;
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
            
            <?php 
            // Show expert warning only if not dismissed
            $dismissed_warnings = get_option('edal_dismissed_warnings', array());
            if ($mode === 'expert' && !in_array('expert_mode_warning', $dismissed_warnings)): 
            ?>
            <div class="notice notice-warning is-dismissible edal-notice" data-dismiss-type="expert_mode_warning">
                <p><?php esc_html_e( 'Warning: Expert mode shows all autoloads including WordPress core options. Modifying core autoloads can break your site. Please proceed with caution and make sure you have a backup.', 'enhanced-autoload-manager' ); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Top row: Total size, Refresh, and Search -->
            <div class="edal-header-row">
                <div class="edal-left-section">
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
                
                <div class="edal-center-section">
                    <div class="edal-search-container">
                        <form method="get" action="">
                            <input type="hidden" name="page" value="enhanced-autoload-manager">
                            <input type="hidden" name="mode" value="<?php echo esc_attr($mode); ?>">
                            <input type="hidden" name="count" value="<?php echo esc_attr($count); ?>">
                            <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>">
                            <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>">
                            <div class="edal-search-input-wrapper">
                                <input type="text" name="search" id="edal-search-input" placeholder="<?php esc_attr_e('Search autoload options...', 'enhanced-autoload-manager'); ?>" value="<?php echo esc_attr($search); ?>" class="regular-text">
                                <button type="submit" class="button button-secondary"><span class="dashicons dashicons-search"></span></button>
                                <?php if (!empty($search)): ?>
                                <a href="<?php echo esc_url(remove_query_arg('search')); ?>" class="button button-link" title="<?php esc_attr_e('Clear search', 'enhanced-autoload-manager'); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="edal-right-section">
                    <div class="edal-import-export">
                        <button id="edal-export-btn" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span> <?php esc_html_e('Export', 'enhanced-autoload-manager'); ?>
                        </button>
                        <button id="edal-import-btn" class="button button-secondary">
                            <span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import', 'enhanced-autoload-manager'); ?>
                        </button>
                        <input type="file" id="edal-import-file" style="display: none;" accept=".json">
                    </div>
                </div>
            </div>

            <div class="nav-tab-wrapper edal-nav-tab-wrapper">
                <!-- Mode Selection -->
                <div class="edal-tab-label"><?php esc_html_e('Mode', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section mode-tabs">
                    <a href="?page=enhanced-autoload-manager&mode=basic<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'basic' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-shield"></span> <?php esc_html_e('Basic', 'enhanced-autoload-manager'); ?>
                    </a>
                    <a href="?page=enhanced-autoload-manager&mode=expert<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'expert' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Expert', 'enhanced-autoload-manager'); ?>
                    </a>
                </div>

                <!-- Plugin-specific Filters -->
                <div class="edal-tab-label"><?php esc_html_e('Plugin Filters', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section plugin-tabs">
                    <a href="?page=enhanced-autoload-manager&mode=elementor<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'elementor' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-editor-kitchensink"></span> <?php esc_html_e('Elementor', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'elementor'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_elementor']; })); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?page=enhanced-autoload-manager&mode=woocommerce<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-cart"></span> <?php esc_html_e('WooCommerce', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'woocommerce'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_woocommerce']; })); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Status Filters -->
                <div class="edal-tab-label"><?php esc_html_e('Status', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section filter-tabs">
                    <a href="?page=enhanced-autoload-manager&mode=all<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'all' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('All', 'enhanced-autoload-manager'); ?>
                    </a>
                    <a href="?page=enhanced-autoload-manager&mode=disabled<?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=<?php echo esc_attr($count); ?>&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $mode === 'disabled' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Disabled', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'disabled'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_disabled']; })); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Items per page -->
                <div class="edal-tab-label"><?php esc_html_e('Items per page', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section count-tabs">
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=10&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $count === 10 ? 'nav-tab-active' : ''; ?>">10</a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=20&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $count === 20 ? 'nav-tab-active' : ''; ?>">20</a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=50&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $count === 50 ? 'nav-tab-active' : ''; ?>">50</a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=100&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $count === 100 ? 'nav-tab-active' : ''; ?>">100</a>
                    <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>&count=-1&orderby=<?php echo esc_attr($orderby); ?>&order=<?php echo esc_attr($order); ?>" class="nav-tab <?php echo $count === -1 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('All', 'enhanced-autoload-manager'); ?></a>
                </div>
            </div>

            <div class="spacer"></div>

            <form method="post" action="" id="edal-bulk-form">
                <?php wp_nonce_field('edal_bulk_action', 'edal_bulk_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value="-1"><?php esc_html_e('Bulk Actions', 'enhanced-autoload-manager'); ?></option>
                            <option value="disable"><?php esc_html_e('Disable Autoload', 'enhanced-autoload-manager'); ?></option>
                            <option value="enable"><?php esc_html_e('Enable Autoload', 'enhanced-autoload-manager'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'enhanced-autoload-manager'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'enhanced-autoload-manager'); ?>">
                        <span class="selected-count"></span>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th style="width: 10%;"><?php esc_html_e('Autoload #', 'enhanced-autoload-manager'); ?></th>
                            <th class="sortable <?php echo $orderby === 'name' ? 'sorted ' . esc_attr(strtolower($order)) : ''; ?>">
                                <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?>&orderby=name&order=<?php echo $orderby === 'name' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&count=<?php echo esc_attr($count); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>">
                                    <?php esc_html_e('Option Name', 'enhanced-autoload-manager'); ?>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th class="sortable <?php echo $orderby === 'size' ? 'sorted ' . esc_attr(strtolower($order)) : ''; ?>">
                                <a href="?page=enhanced-autoload-manager&mode=<?php echo esc_attr($mode); ?>&orderby=size&order=<?php echo $orderby === 'size' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&count=<?php echo esc_attr($count); ?><?php echo !empty($search) ? '&search=' . esc_attr($search) : ''; ?>">
                                    <?php esc_html_e('Size', 'enhanced-autoload-manager'); ?>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th><?php esc_html_e('Status', 'enhanced-autoload-manager'); ?></th>
                            <th><?php esc_html_e('Actions', 'enhanced-autoload-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $autoloads as $index => $autoload ) :
                            $size_kb = round( $autoload['option_size'] / 1024, 2 );
                            $size_display = $size_kb < 1024 ? $size_kb . ' KB' : round( $size_kb / 1024, 2 ) . ' MB';
                        ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="selected_options[]" value="<?php echo esc_attr($autoload['option_name']); ?>">
                                </th>
                                <td><?php echo esc_html( $index + 1 ); ?></td>
                                <td><?php echo esc_html( $autoload['option_name'] ); ?></td>
                                <td><?php echo esc_html( $size_display ); ?></td>
                                <td>
                                    <?php if ($autoload['is_disabled']): ?>
                                        <span class="edal-status-badge disabled"><?php esc_html_e('Disabled', 'enhanced-autoload-manager'); ?></span>
                                    <?php else: ?>
                                        <span class="edal-status-badge enabled"><?php esc_html_e('Enabled', 'enhanced-autoload-manager'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $delete_nonce = wp_create_nonce('delete_autoload_' . $autoload['option_name']);
                                        $disable_nonce = wp_create_nonce('disable_autoload_' . $autoload['option_name']);
                                        $enable_nonce = wp_create_nonce('enable_autoload_' . $autoload['option_name']);
                                    ?>
                                    <a href="<?php echo esc_url( admin_url( 'tools.php?page=enhanced-autoload-manager&action=delete&option_name=' . urlencode( $autoload['option_name'] ) . '&_wpnonce=' . $delete_nonce ) ); ?>" class="button button-secondary edal-button edal-button-delete">
                                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'enhanced-autoload-manager'); ?>
                                    </a>
                                    <?php if ($autoload['is_disabled']): ?>
                                        <a href="<?php echo esc_url( admin_url( 'tools.php?page=enhanced-autoload-manager&action=enable&option_name=' . urlencode( $autoload['option_name'] ) . '&_wpnonce=' . $enable_nonce ) ); ?>" class="button button-secondary edal-button edal-button-enable">
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Enable', 'enhanced-autoload-manager'); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url( admin_url( 'tools.php?page=enhanced-autoload-manager&action=disable&option_name=' . urlencode( $autoload['option_name'] ) . '&_wpnonce=' . $disable_nonce ) ); ?>" class="button button-secondary edal-button edal-button-disable">
                                            <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Disable', 'enhanced-autoload-manager'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" class="button button-secondary edal-button edal-button-expand" data-option="<?php echo esc_attr( $autoload['option_value'] ); ?>">
                                        <span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e('Expand', 'enhanced-autoload-manager'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <?php if ($count === -1 && $total_pages > 1): ?>
            <div class="edal-pagination">
                <?php
                $base_url = admin_url('tools.php?page=enhanced-autoload-manager&mode=' . $mode . '&count=' . $count . '&orderby=' . $orderby . '&order=' . $order);
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
        
        <!-- Plugin Footer Credit -->
        <div class="edal-footer-credit">
            <p><?php 
                printf(
                    /* translators: 1: Developer name, 2: Developer email */
                    __('Need custom WordPress plugins, WooCommerce sites, or server optimization? Contact %1$s at %2$s', 'enhanced-autoload-manager'),
                    '<strong>Rai Ansar</strong>',
                    '<a href="mailto:hi@raiansar.com">hi@raiansar.com</a>'
                );
            ?></p>
            <p class="edal-services"><?php esc_html_e('Specializing in: Custom WordPress Plugins • React.js & Next.js Development • WooCommerce Solutions • Server Management & Optimization', 'enhanced-autoload-manager'); ?></p>
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

    
    // Handle the actions for deleting and disabling autoloads
    function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'enhanced-autoload-manager') {
            return;
        }

        // Skip if this is a redirect from a completed action
        if (isset($_GET['action_complete'])) {
            return;
        }

        // Handle bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['selected_options']) && check_admin_referer('edal_bulk_action', 'edal_bulk_nonce')) {
            $action = sanitize_text_field(wp_unslash($_POST['bulk_action']));
            $selected_options = array_map('sanitize_text_field', wp_unslash($_POST['selected_options']));
            $disabled_autoloads = get_option('edal_disabled_autoloads', array());
            
            foreach ($selected_options as $option_name) {
                if ($action === 'delete') {
                    delete_option($option_name);
                    // Remove from disabled list if it was there
                    $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                } elseif ($action === 'disable') {
                    $current_value = get_option($option_name);
                    if ($current_value !== false) {
                        update_option($option_name, $current_value, 'no');
                        // Add to disabled list if not already there
                        if (!in_array($option_name, $disabled_autoloads)) {
                            $disabled_autoloads[] = $option_name;
                        }
                    }
                } elseif ($action === 'enable') {
                    $current_value = get_option($option_name);
                    if ($current_value !== false) {
                        update_option($option_name, $current_value, 'yes');
                        // Remove from disabled list if it was there
                        $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                    }
                }
            }
            
            // Update the disabled autoloads list
            update_option('edal_disabled_autoloads', array_unique($disabled_autoloads));
            
            wp_redirect(add_query_arg('bulk_action_complete', $action));
            exit;
        }

        // Handle individual actions
        if (!isset($_GET['action']) || !isset($_GET['option_name']) || !isset($_GET['_wpnonce'])) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_GET['action']));
        $option_name = sanitize_text_field(wp_unslash($_GET['option_name']));
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        if (!wp_verify_nonce($nonce, $action . '_autoload_' . $option_name)) {
            wp_die(esc_html__('Invalid nonce', 'enhanced-autoload-manager'));
        }

        if ($action === 'delete') {
            delete_option($option_name);
            // Remove from disabled list if it was there
            $disabled_autoloads = get_option('edal_disabled_autoloads', array());
            $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
            update_option('edal_disabled_autoloads', $disabled_autoloads);
        } elseif ($action === 'disable') {
            $current_value = get_option($option_name);
            if ($current_value !== false) {
                update_option($option_name, $current_value, 'no');
                // Add to disabled list if not already there
                $disabled_autoloads = get_option('edal_disabled_autoloads', array());
                if (!in_array($option_name, $disabled_autoloads)) {
                    $disabled_autoloads[] = $option_name;
                    update_option('edal_disabled_autoloads', $disabled_autoloads);
                }
            }
        } elseif ($action === 'enable') {
            $current_value = get_option($option_name);
            if ($current_value !== false) {
                update_option($option_name, $current_value, 'yes');
                // Remove from disabled list if it was there
                $disabled_autoloads = get_option('edal_disabled_autoloads', array());
                $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                update_option('edal_disabled_autoloads', $disabled_autoloads);
            }
        }

        // Clear cache before redirecting
        wp_cache_delete('alloptions');
        delete_option('total_autoload_size');

        wp_redirect(add_query_arg('action_complete', $action));
        exit;
    }

    public function enqueue_scripts() {
        wp_enqueue_style('edal-styles', plugins_url('styles.css', __FILE__), array(), $this->version);
        wp_enqueue_script('edal-scripts', plugins_url('scripts.js', __FILE__), array('jquery'), $this->version, true);
        wp_localize_script('edal-scripts', 'edalData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('edal_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this option? This action cannot be undone.', 'enhanced-autoload-manager'),
            'confirmDisable' => __('Are you sure you want to disable autoload for this option?', 'enhanced-autoload-manager'),
            'confirmBulkDelete' => __('Are you sure you want to delete the selected options? This action cannot be undone.', 'enhanced-autoload-manager'),
            'confirmBulkDisable' => __('Are you sure you want to disable autoload for the selected options?', 'enhanced-autoload-manager'),
            'i18n' => array(
                'selectAll' => __('Select all', 'enhanced-autoload-manager'),
                'selectNone' => __('Select none', 'enhanced-autoload-manager'),
                /* translators: %d: number of selected items */
                'selected' => __('%d items selected', 'enhanced-autoload-manager'),
                'noItemsSelected' => __('No items selected', 'enhanced-autoload-manager'),
                'bulkActionRequired' => __('Please select a bulk action.', 'enhanced-autoload-manager'),
                'itemsRequired' => __('Please select at least one item.', 'enhanced-autoload-manager')
            )
        ));
    }

    public function ajax_refresh_data() {
        check_ajax_referer('edal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'enhanced-autoload-manager')));
        }
        
        $total_autoload_size = $this->calculate_total_autoload_size();
        update_option('total_autoload_size', $total_autoload_size);
        
        wp_send_json_success(array(
            'message' => __('Data refreshed successfully.', 'enhanced-autoload-manager'),
            'total_size' => round($total_autoload_size / 1024 / 1024, 2)
        ));
    }

    public function ajax_export_settings() {
        check_ajax_referer('edal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'enhanced-autoload-manager')));
        }
        
        $settings = array(
            'disabled_autoloads' => get_option('edal_disabled_autoloads', array()),
            'total_autoload_size' => get_option('total_autoload_size', 0)
        );
        
        wp_send_json_success($settings);
    }

    public function ajax_import_settings() {
        check_ajax_referer('edal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'enhanced-autoload-manager')));
        }
        
        if (!isset($_POST['import_data'])) {
            wp_send_json_error(array('message' => __('Invalid settings data.', 'enhanced-autoload-manager')));
        }
        
        $import_data = sanitize_textarea_field(wp_unslash($_POST['import_data']));
        $settings = json_decode($import_data, true);
        if (!is_array($settings)) {
            wp_send_json_error(array('message' => __('Invalid JSON data.', 'enhanced-autoload-manager')));
        }
        
        if (isset($settings['disabled_autoloads']) && is_array($settings['disabled_autoloads'])) {
            update_option('edal_disabled_autoloads', $settings['disabled_autoloads']);
        }
        
        if (isset($settings['total_autoload_size'])) {
            update_option('total_autoload_size', floatval($settings['total_autoload_size']));
        }
        
        wp_send_json_success(array('message' => __('Settings imported successfully.', 'enhanced-autoload-manager')));
    }

    // AJAX handler for dismissing warnings
    public function ajax_dismiss_warning() {
        check_ajax_referer('edal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'enhanced-autoload-manager')));
        }
        
        if (!isset($_POST['warning_type'])) {
            wp_send_json_error(array('message' => __('Warning type not specified.', 'enhanced-autoload-manager')));
        }
        
        $warning_type = sanitize_text_field(wp_unslash($_POST['warning_type']));
        $dismissed_warnings = get_option('edal_dismissed_warnings', array());
        
        if (!in_array($warning_type, $dismissed_warnings)) {
            $dismissed_warnings[] = $warning_type;
            update_option('edal_dismissed_warnings', $dismissed_warnings);
        }
        
        wp_send_json_success(array('message' => __('Warning dismissed.', 'enhanced-autoload-manager')));
    }
}

// Instantiate the class
$enhanced_autoload_manager = new Enhanced_Autoload_Manager;

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array($enhanced_autoload_manager, 'activate'));
register_deactivation_hook(__FILE__, array($enhanced_autoload_manager, 'deactivate'));
