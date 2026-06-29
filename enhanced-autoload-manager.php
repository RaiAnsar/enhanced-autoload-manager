<?php
/*
Plugin Name: Enhanced Autoload Manager
Plugin URI: https://raiansar.com/enhanced-autoload-manager
Description: Manages autoloaded data in the WordPress database, allowing for individual deletion or disabling of autoload entries.
Version: 1.6.4
Author: Rai Ansar
Author URI: https://raiansar.com
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: enhanced-autoload-manager
Requires at least: 5.0
Tested up to: 7.0
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
    define('EDAL_VERSION', '1.6.4');
}

class Enhanced_Autoload_Manager {
    private $version = EDAL_VERSION;

    public function __construct() {
        // Add the menu item under Tools
        add_action( 'admin_menu', [ $this, 'add_menu_item' ] );
        // Handle actions for deleting and disabling autoloads
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        // Restore locked autoloads on multiple hooks to catch all scenarios
        add_action( 'admin_init', [ $this, 'restore_locked_autoloads' ] ); // Admin page loads
        add_action( 'init', [ $this, 'restore_locked_autoloads' ] );       // Every request (inc. cron)
        add_action( 'updated_option', [ $this, 'check_locked_option' ], 10, 3 ); // Real-time protection
        add_action( 'upgrader_process_complete', [ $this, 'restore_after_update' ], 10, 2 ); // After updates
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

    // Helper function to generate secure URLs with nonce
    private function get_admin_url($args = array()) {
        $base_args = array(
            'page' => 'enhanced-autoload-manager'
        );
        $url_args = array_merge($base_args, $args);
        $url = add_query_arg($url_args, admin_url('tools.php'));
        return wp_nonce_url($url, 'edal_view_page');
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
        // Store locked autoloads (non-autoloaded — can hold full option values)
        if (!get_option('edal_locked_autoloads')) {
            add_option('edal_locked_autoloads', array(), '', 'no');
        }
    }

    // Plugin deactivation hook
    public function deactivate() {
        // Clean up transients
        delete_transient('edal_autoload_cache');
        // Note: We don't delete options on deactivation, only on uninstall
    }

    // Enqueue custom styles and scripts
    public function enqueue_assets($hook) {
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
    public function add_menu_item() {
        add_submenu_page( 'tools.php', 'Enhanced Autoload Manager', 'E. Autoload Manager', 'manage_options', 'enhanced-autoload-manager', [ $this, 'display_page' ] );
    }

    // Add a link to the plugin page in the plugin list
    public function add_action_links( $links ) {
        $links[] = '<a href="' . admin_url( 'tools.php?page=enhanced-autoload-manager' ) . '">' . __( 'Manage Autoloads', 'enhanced-autoload-manager' ) . '</a>';
        return $links;
    }

    // Whether a raw `autoload` column value means "autoloaded". Handles the
    // WordPress 6.6+ values ('yes','on','auto-on','auto'), legacy 'yes', and a
    // stored boolean. Everything else ('no','off','auto-off') means not autoloaded.
    private function is_autoload_enabled($raw) {
        if (is_bool($raw)) {
            return $raw;
        }
        if (function_exists('wp_autoload_values_to_autoload')) {
            return in_array($raw, wp_autoload_values_to_autoload(), true);
        }
        return 'yes' === $raw;
    }

    // Set only the autoload flag for an option, across WordPress versions.
    private function set_autoload($option_name, $enabled) {
        if (function_exists('wp_set_option_autoload')) {
            wp_set_option_autoload($option_name, (bool) $enabled);
            return;
        }
        global $wpdb;
        // Direct write: pre-6.6 WordPress has no API to toggle only the autoload flag.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $wpdb->options,
            array('autoload' => $enabled ? 'yes' : 'no'),
            array('option_name' => $option_name)
        );
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
    }

    // Restore locked autoload values - Enhanced version
    public function restore_locked_autoloads($force = false) {
        // Run once per request (init + admin_init) for performance, but allow the
        // post-update hook to force a re-run: init/admin_init fire BEFORE an upgrade
        // modifies options, so without the force flag the post-update restore is lost.
        static $already_run = false;
        if ($already_run && !$force) {
            return 0;
        }
        $already_run = true;

        $locked_autoloads = get_option('edal_locked_autoloads', array());

        if (empty($locked_autoloads)) {
            return 0;
        }

        $restored_count = 0;
        $restored_options = array();

        global $wpdb;
        foreach ($locked_autoloads as $option_name => $locked_data) {
            // Upgrade old format (string) to new format (array)
            if (!is_array($locked_data)) {
                $locked_data = array(
                    'autoload' => $locked_data,
                    'value' => get_option($option_name),
                    'locked_at' => time()
                );
                // Save upgraded format
                $locked_autoloads[$option_name] = $locked_data;
            }

            // Read the raw autoload column (null = the option no longer exists). Must
            // be a live, uncached read — the lock check needs the actual DB state.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $current_autoload = $wpdb->get_var($wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                $option_name
            ));
            if (null === $current_autoload) {
                continue; // Option was deleted; nothing to restore.
            }

            // Compare by autoloaded-or-not, NOT the raw string. WordPress 6.6+ uses
            // 'on'/'off'/'auto-on'/'auto-off'/'auto' and normalizes the autoload value
            // on write, so a raw-string compare mis-fires and re-restores every request.
            $locked_enabled  = $this->is_autoload_enabled($locked_data['autoload']);
            $current_enabled = $this->is_autoload_enabled($current_autoload);

            $current_value    = get_option($option_name);
            $has_value        = array_key_exists('value', $locked_data);
            $value_changed    = $has_value
                && maybe_serialize($current_value) !== maybe_serialize($locked_data['value']);
            $autoload_changed = $current_enabled !== $locked_enabled;

            if (!$value_changed && !$autoload_changed) {
                continue;
            }

            if ($value_changed) {
                // One normalized write restores the value AND the autoload flag.
                update_option($option_name, $locked_data['value'], $locked_enabled);
            } elseif ($autoload_changed) {
                // Value is intact; only the autoload flag drifted.
                $this->set_autoload($option_name, $locked_enabled);
            }

            $restored_count++;
            $restored_options[] = $option_name;
        }

        // Update locked autoloads if we upgraded any (kept non-autoloaded — it can
        // hold full option values and shouldn't bloat alloptions).
        update_option('edal_locked_autoloads', $locked_autoloads, 'no');

        // Show admin notice if options were restored
        if ($restored_count > 0 && is_admin() && !wp_doing_ajax()) {
            add_action('admin_notices', function() use ($restored_count, $restored_options) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . esc_html__('Enhanced Autoload Manager:', 'enhanced-autoload-manager') . '</strong> ';
                $restored_message = sprintf(
                    /* translators: %d: number of locked options that were automatically restored */
                    _n(
                        '%d locked option was automatically restored.',
                        '%d locked options were automatically restored.',
                        $restored_count,
                        'enhanced-autoload-manager'
                    ),
                    absint($restored_count)
                );
                echo esc_html($restored_message);
                echo ' <a href="' . esc_url(admin_url('tools.php?page=enhanced-autoload-manager')) . '">' .
                     esc_html__('View details', 'enhanced-autoload-manager') . '</a>';
                echo '</p>';
                if (count($restored_options) <= 5) {
                    echo '<p><em>' . esc_html__('Restored options:', 'enhanced-autoload-manager') . ' ' .
                         esc_html(implode(', ', $restored_options)) . '</em></p>';
                }
                echo '</div>';
            });
        }

        return $restored_count;
    }

    // Real-time protection: Check if a locked option is being modified
    public function check_locked_option($option_name, $old_value, $new_value) {
        $locked_autoloads = get_option('edal_locked_autoloads', array());

        if (!isset($locked_autoloads[$option_name])) {
            return; // Not locked
        }

        $locked_data = $locked_autoloads[$option_name];
        if (!is_array($locked_data) || !array_key_exists('value', $locked_data)) {
            return; // Old/partial format; the periodic restore will handle it.
        }

        // Object/array-safe comparison (raw !== treats two equal objects as different).
        if (maybe_serialize($new_value) !== maybe_serialize($locked_data['value'])) {
            // Immediately restore the locked value
            remove_action( 'updated_option', [ $this, 'check_locked_option' ], 10 );
            update_option($option_name, $locked_data['value'], $this->is_autoload_enabled($locked_data['autoload']));
            add_action( 'updated_option', [ $this, 'check_locked_option' ], 10, 3 );

            // Log the attempt (debug only — gated behind WP_DEBUG).
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'Enhanced Autoload Manager: Prevented modification of locked option "%s"',
                    $option_name
                ));
            }
        }
    }

    // Restore locked options after WordPress/plugin updates
    public function restore_after_update($upgrader_object = null, $options = array()) {
        // Force past the once-per-request guard: init/admin_init already ran earlier
        // in this request, BEFORE the upgrade modified the options.
        $this->restore_locked_autoloads(true);
    }

    // Function to get and process autoload data
    private function get_autoload_data($mode = 'basic', $search = '') {
        // Get all options
        $all_options = wp_load_alloptions();
        $autoloads = [];
        $disabled_autoloads = get_option('edal_disabled_autoloads', array());
        $locked_autoloads = get_option('edal_locked_autoloads', array());

        // wp_load_alloptions() only returns autoload=yes options. Disabled
        // options (autoload=no) must be pulled in explicitly or they vanish from
        // every view — including the Disabled tab and their own Enable button.
        $values = $all_options;
        foreach ($disabled_autoloads as $name) {
            if (!isset($values[$name])) {
                $disabled_value = get_option($name, null);
                if ($disabled_value !== null) {
                    $values[$name] = maybe_serialize($disabled_value);
                }
            }
        }

        foreach ($values as $key => $value) {
            // If search is provided, filter options by name
            if (!empty($search) && stripos($key, $search) === false) {
                continue;
            }
            
            $autoloads[] = [
                'option_name' => $key,
                'option_value' => $value,
                'option_size' => strlen((string) $value),
                'is_core' => $this->is_core_autoload($key),
                'is_woocommerce' => strpos($key, 'woocommerce') === 0,
                'is_elementor' => strpos($key, '_elementor') === 0,
                'is_disabled' => in_array($key, $disabled_autoloads),
                'is_locked' => isset($locked_autoloads[$key])
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
        // wp_load_alloptions() already returns only autoloaded options, so the
        // sum of their value sizes is the total autoload size. No per-option
        // query needed (the old version ran one SELECT per option — an N+1 that
        // fired on every page load and refresh).
        $total_size = 0;
        foreach (wp_load_alloptions() as $value) {
            $total_size += strlen((string) $value);
        }

        update_option('edal_total_autoload_size', $total_size, 'no');
        return $total_size;
    }
    
    // Display the plugin page
    public function display_page() {
        global $wpdb;

        // Get the total autoload size in MBs
        $total_autoload_size = get_option('edal_total_autoload_size');
        if (false === $total_autoload_size) {
            $total_autoload_size = $this->calculate_total_autoload_size();
        }
        $total_autoload_size_mb = round($total_autoload_size / 1024 / 1024, 2);

        // Check if any GET parameters are present that require nonce verification
        $has_params = isset($_GET['mode']) || isset($_GET['count']) || isset($_GET['search']) || 
                      isset($_GET['paged']) || isset($_GET['orderby']) || isset($_GET['order']);
        
        $nonce_action = 'edal_view_page';
        
        // If parameters are present, verify nonce
        if ($has_params) {
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), $nonce_action)) {
                wp_die(esc_html__('Security check failed. Please refresh the page and try again.', 'enhanced-autoload-manager'));
            }
        }
        
        // Now safe to process parameters
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 'basic';
        $count = isset($_GET['count']) ? intval(wp_unslash($_GET['count'])) : 10;
        // -1 means "show all"; any other non-positive value would divide by zero below.
        if ($count !== -1 && $count < 1) {
            $count = 10;
        }
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $paged = isset($_GET['paged']) ? max(1, intval(wp_unslash($_GET['paged']))) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'size';
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';

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
        
        // Count total items
        $total_items = count($autoloads);
        
        // Apply limit or pagination
        if ($count === -1) {
            // Show ALL items without any pagination
            // $autoloads remains unchanged - show everything
        } else {
            // Apply pagination for limited views (10, 20, 50, 100)
            $total_pages = ceil($total_items / $count);
            $offset = ($paged - 1) * $count;
            $autoloads = array_slice($autoloads, $offset, $count);
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
                    <button type="button" id="edal-refresh-data" class="button button-primary" data-nonce="<?php echo esc_attr(wp_create_nonce('edal_nonce')); ?>">
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
                            <?php wp_nonce_field('edal_view_page', '_wpnonce', false); ?>
                            <div class="edal-search-input-wrapper">
                                <input type="text" name="search" id="edal-search-input" placeholder="<?php esc_attr_e('Search autoload options...', 'enhanced-autoload-manager'); ?>" value="<?php echo esc_attr($search); ?>" class="regular-text">
                                <button type="submit" class="button button-secondary"><span class="dashicons dashicons-search"></span></button>
                                <?php if (!empty($search)): ?>
                                <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="button button-link" title="<?php esc_attr_e('Clear search', 'enhanced-autoload-manager'); ?>">
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
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'basic', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'basic' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-shield"></span> <?php esc_html_e('Basic', 'enhanced-autoload-manager'); ?>
                    </a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'expert', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'expert' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e('Expert', 'enhanced-autoload-manager'); ?>
                    </a>
                </div>

                <!-- Plugin-specific Filters -->
                <div class="edal-tab-label"><?php esc_html_e('Plugin Filters', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section plugin-tabs">
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'elementor', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'elementor' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-editor-kitchensink"></span> <?php esc_html_e('Elementor', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'elementor'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_elementor']; })); ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'woocommerce', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-cart"></span> <?php esc_html_e('WooCommerce', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'woocommerce'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_woocommerce']; })); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Status Filters -->
                <div class="edal-tab-label"><?php esc_html_e('Status', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section filter-tabs">
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'all', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'all' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-list-view"></span> <?php esc_html_e('All', 'enhanced-autoload-manager'); ?>
                    </a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => 'disabled', 'search' => $search, 'count' => $count, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $mode === 'disabled' ? 'nav-tab-active' : ''; ?>">
                        <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Disabled', 'enhanced-autoload-manager'); ?>
                        <?php if ($mode === 'disabled'): ?>
                            <span class="edal-status-badge"><?php echo count(array_filter($autoloads, function($a) { return $a['is_disabled']; })); ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Items per page -->
                <div class="edal-tab-label"><?php esc_html_e('Items per page', 'enhanced-autoload-manager'); ?></div>
                <div class="edal-tab-section count-tabs">
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'search' => $search, 'count' => 10, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $count === 10 ? 'nav-tab-active' : ''; ?>">10</a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'search' => $search, 'count' => 20, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $count === 20 ? 'nav-tab-active' : ''; ?>">20</a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'search' => $search, 'count' => 50, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $count === 50 ? 'nav-tab-active' : ''; ?>">50</a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'search' => $search, 'count' => 100, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $count === 100 ? 'nav-tab-active' : ''; ?>">100</a>
                    <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'search' => $search, 'count' => -1, 'orderby' => $orderby, 'order' => $order))); ?>" class="nav-tab <?php echo $count === -1 ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('All', 'enhanced-autoload-manager'); ?></a>
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
                            <th style="width: 5%;"><?php esc_html_e('Autoload #', 'enhanced-autoload-manager'); ?></th>
                            <th style="width: 35%;" class="sortable <?php echo $orderby === 'name' ? 'sorted ' . esc_attr(strtolower($order)) : ''; ?>">
                                <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'orderby' => 'name', 'order' => ($orderby === 'name' && $order === 'ASC' ? 'DESC' : 'ASC'), 'count' => $count, 'search' => $search))); ?>">
                                    <?php esc_html_e('Option Name', 'enhanced-autoload-manager'); ?>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th style="width: 8%;" class="sortable <?php echo $orderby === 'size' ? 'sorted ' . esc_attr(strtolower($order)) : ''; ?>">
                                <a href="<?php echo esc_url($this->get_admin_url(array('mode' => $mode, 'orderby' => 'size', 'order' => ($orderby === 'size' && $order === 'ASC' ? 'DESC' : 'ASC'), 'count' => $count, 'search' => $search))); ?>">
                                    <?php esc_html_e('Size', 'enhanced-autoload-manager'); ?>
                                    <span class="sorting-indicator"></span>
                                </a>
                            </th>
                            <th style="width: 8%;"><?php esc_html_e('Status', 'enhanced-autoload-manager'); ?></th>
                            <th style="width: 44%;"><?php esc_html_e('Actions', 'enhanced-autoload-manager'); ?></th>
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
                                <td>
                                    <?php echo esc_html( $autoload['option_name'] ); ?>
                                    <?php if ($autoload['is_locked']): ?>
                                        <span class="edal-locked-indicator">
                                            <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Locked', 'enhanced-autoload-manager'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
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
                                        $lock_nonce = wp_create_nonce('lock_autoload_' . $autoload['option_name']);
                                        $unlock_nonce = wp_create_nonce('unlock_autoload_' . $autoload['option_name']);
                                    ?>
                                    <?php
                                        // Build action URLs with preserved filters
                                        $action_args = array(
                                            'page' => 'enhanced-autoload-manager',
                                            'option_name' => $autoload['option_name'],
                                            'mode' => $mode,
                                            'search' => $search,
                                            'count' => $count,
                                            'orderby' => $orderby,
                                            'order' => $order
                                        );
                                        
                                        $delete_url = add_query_arg(array_merge($action_args, array('action' => 'delete', '_wpnonce' => $delete_nonce)), admin_url('tools.php'));
                                        $disable_url = add_query_arg(array_merge($action_args, array('action' => 'disable', '_wpnonce' => $disable_nonce)), admin_url('tools.php'));
                                        $enable_url = add_query_arg(array_merge($action_args, array('action' => 'enable', '_wpnonce' => $enable_nonce)), admin_url('tools.php'));
                                        $lock_url = add_query_arg(array_merge($action_args, array('action' => 'lock', '_wpnonce' => $lock_nonce)), admin_url('tools.php'));
                                        $unlock_url = add_query_arg(array_merge($action_args, array('action' => 'unlock', '_wpnonce' => $unlock_nonce)), admin_url('tools.php'));
                                    ?>
                                    <?php if ($autoload['is_locked']): ?>
                                        <!-- Locked: Only show Unlock and Expand buttons -->
                                        <a href="<?php echo esc_url($unlock_url); ?>" class="button button-secondary edal-button edal-button-unlock" title="<?php esc_attr_e('Unlock this option to allow modifications', 'enhanced-autoload-manager'); ?>">
                                            <span class="dashicons dashicons-unlock"></span> <?php esc_html_e('Unlock', 'enhanced-autoload-manager'); ?>
                                        </a>
                                        <a href="#" class="button button-secondary edal-button edal-button-expand" data-option="<?php echo esc_attr( $autoload['option_value'] ); ?>">
                                            <span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e('Expand', 'enhanced-autoload-manager'); ?>
                                        </a>
                                        <span class="edal-locked-help-text" style="color: #666; font-style: italic; font-size: 12px;">
                                            <?php esc_html_e('(Unlock to modify)', 'enhanced-autoload-manager'); ?>
                                        </span>
                                    <?php else: ?>
                                        <!-- Not locked: Show all buttons -->
                                        <a href="<?php echo esc_url($lock_url); ?>" class="button button-secondary edal-button edal-button-lock" title="<?php esc_attr_e('Lock this option to prevent automatic changes', 'enhanced-autoload-manager'); ?>">
                                            <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Lock', 'enhanced-autoload-manager'); ?>
                                        </a>
                                        <?php if ($autoload['is_disabled']): ?>
                                            <a href="<?php echo esc_url($enable_url); ?>" class="button button-secondary edal-button edal-button-enable">
                                                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Enable', 'enhanced-autoload-manager'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url($disable_url); ?>" class="button button-secondary edal-button edal-button-disable">
                                                <span class="dashicons dashicons-hidden"></span> <?php esc_html_e('Disable', 'enhanced-autoload-manager'); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url($delete_url); ?>" class="button button-secondary edal-button edal-button-delete">
                                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'enhanced-autoload-manager'); ?>
                                        </a>
                                        <a href="#" class="button button-secondary edal-button edal-button-expand" data-option="<?php echo esc_attr( $autoload['option_value'] ); ?>">
                                            <span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e('Expand', 'enhanced-autoload-manager'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <?php 
            // Only show pagination if we're limiting results (count !== -1) AND there are multiple pages
            // When count is -1, we show ALL items without pagination
            if ($count !== -1 && $count > 0): 
                // Recalculate for limited view
                $limited_total_pages = ceil($total_items / $count);
                if ($limited_total_pages > 1):
            ?>
            <div class="edal-pagination">
                <?php
                $pagination_args = array(
                    'page' => 'enhanced-autoload-manager',
                    'mode' => $mode,
                    'count' => $count,
                    'orderby' => $orderby,
                    'order' => $order
                );
                if (!empty($search)) {
                    $pagination_args['search'] = $search;
                }
                
                // Previous page link
                if ($paged > 1) {
                    $prev_args = array_merge($pagination_args, array('paged' => $paged - 1));
                    $prev_url = $this->get_admin_url($prev_args);
                    echo '<a href="' . esc_url($prev_url) . '" class="button">&laquo; ' . esc_html__('Previous', 'enhanced-autoload-manager') . '</a>';
                }
                
                // Page numbers
                $start = max(1, $paged - 2);
                $end = min($limited_total_pages, $paged + 2);
                
                if ($start > 1) {
                    $first_args = array_merge($pagination_args, array('paged' => 1));
                    $first_url = $this->get_admin_url($first_args);
                    echo '<a href="' . esc_url($first_url) . '" class="button">1</a>';
                    if ($start > 2) {
                        echo '<span class="pagination-ellipsis">&hellip;</span>';
                    }
                }
                
                for ($i = $start; $i <= $end; $i++) {
                    if ($i == $paged) {
                        echo '<span class="button button-primary">' . esc_html($i) . '</span>';
                    } else {
                        $page_args = array_merge($pagination_args, array('paged' => $i));
                        $page_url = $this->get_admin_url($page_args);
                        echo '<a href="' . esc_url($page_url) . '" class="button">' . esc_html($i) . '</a>';
                    }
                }
                
                if ($end < $limited_total_pages) {
                    if ($end < $limited_total_pages - 1) {
                        echo '<span class="pagination-ellipsis">&hellip;</span>';
                    }
                    $last_args = array_merge($pagination_args, array('paged' => $limited_total_pages));
                    $last_url = $this->get_admin_url($last_args);
                    echo '<a href="' . esc_url($last_url) . '" class="button">' . esc_html($limited_total_pages) . '</a>';
                }
                
                // Next page link
                if ($paged < $limited_total_pages) {
                    $next_args = array_merge($pagination_args, array('paged' => $paged + 1));
                    $next_url = $this->get_admin_url($next_args);
                    echo '<a href="' . esc_url($next_url) . '" class="button">' . esc_html__('Next', 'enhanced-autoload-manager') . ' &raquo;</a>';
                }
                ?>
            </div>
            <?php 
                endif;
            endif; 
            ?>
            
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
                    esc_html__('Need custom WordPress plugins, WooCommerce sites, or server optimization? Contact %1$s at %2$s', 'enhanced-autoload-manager'),
                    '<strong>Rai Ansar</strong>',
                    '<a href="mailto:hi@raiansar.com">hi@raiansar.com</a>'
                );
            ?></p>
            <p class="edal-services"><?php esc_html_e('Specializing in: Custom WordPress Plugins • React.js & Next.js Development • WooCommerce Solutions • Server Management & Optimization', 'enhanced-autoload-manager'); ?></p>
            <p class="edal-sponsor"><?php
                printf(
                    /* translators: %s: link to the VisualSentinel app */
                    esc_html__('Also by Rai Ansar: %s', 'enhanced-autoload-manager'),
                    '<a href="' . esc_url('https://visualsentinel.com') . '" target="_blank" rel="noopener">VisualSentinel</a>'
                );
            ?></p>
        </div>
        <?php
    }

    // Function to determine if an autoload option is core
    private function is_core_autoload($option_name) {
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
            'finished_updating_comment_type', 'medium_large_size_w', 'medium_large_size_h', 'edal_total_autoload_size'
        ];
        foreach ($core_autoloads as $core) {
            if (strpos($option_name, $core) === 0) {
                return true;
            }
        }
        return false;
    }


    // Handle the actions for deleting and disabling autoloads
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'enhanced-autoload-manager') {
            return;
        }

        // Authorization: nonces guard against CSRF, but destructive actions also
        // require the capability. This runs on admin_init for every admin user.
        if (!current_user_can('manage_options')) {
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
            $locked_autoloads = get_option('edal_locked_autoloads', array());

            foreach ($selected_options as $option_name) {
                // Locked options are protected — skip them (matches the per-row UI,
                // which hides the action buttons for locked options).
                if (isset($locked_autoloads[$option_name])) {
                    continue;
                }
                if ($action === 'delete') {
                    delete_option($option_name);
                    // Remove from disabled list if it was there
                    $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                } elseif ($action === 'disable') {
                    if (get_option($option_name, null) !== null) {
                        // Change ONLY the autoload flag. update_option() with the same
                        // value short-circuits before applying autoload, so use the
                        // dedicated setter.
                        $this->set_autoload($option_name, false);
                        // Add to disabled list if not already there
                        if (!in_array($option_name, $disabled_autoloads)) {
                            $disabled_autoloads[] = $option_name;
                        }
                    }
                } elseif ($action === 'enable') {
                    if (get_option($option_name, null) !== null) {
                        $this->set_autoload($option_name, true);
                        // Remove from disabled list if it was there
                        $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                    }
                }
            }

            // Update the disabled autoloads list
            update_option('edal_disabled_autoloads', array_unique($disabled_autoloads));
            
            // Preserve current filters when redirecting
            $redirect_args = array(
                'page' => 'enhanced-autoload-manager',
                'bulk_action_complete' => $action
            );
            
            // Preserve filter parameters
            if (isset($_GET['mode'])) $redirect_args['mode'] = sanitize_text_field(wp_unslash($_GET['mode']));
            if (isset($_GET['search'])) $redirect_args['search'] = sanitize_text_field(wp_unslash($_GET['search']));
            if (isset($_GET['count'])) $redirect_args['count'] = intval(wp_unslash($_GET['count']));
            if (isset($_GET['orderby'])) $redirect_args['orderby'] = sanitize_text_field(wp_unslash($_GET['orderby']));
            if (isset($_GET['order'])) $redirect_args['order'] = sanitize_text_field(wp_unslash($_GET['order']));
            
            $redirect_url = add_query_arg($redirect_args, admin_url('tools.php'));
            $redirect_url = wp_nonce_url($redirect_url, 'edal_view_page');
            
            wp_redirect($redirect_url);
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

            // Remove from locked list if it was there
            $locked_autoloads = get_option('edal_locked_autoloads', array());
            if (isset($locked_autoloads[$option_name])) {
                unset($locked_autoloads[$option_name]);
                update_option('edal_locked_autoloads', $locked_autoloads, 'no');
            }
        } elseif ($action === 'disable') {
            if (get_option($option_name, null) !== null) {
                // Change ONLY the autoload flag. update_option() with an unchanged
                // value returns early before applying the autoload arg, so the old
                // code never actually disabled autoload — use the dedicated setter.
                $this->set_autoload($option_name, false);
                // Add to disabled list if not already there
                $disabled_autoloads = get_option('edal_disabled_autoloads', array());
                if (!in_array($option_name, $disabled_autoloads)) {
                    $disabled_autoloads[] = $option_name;
                    update_option('edal_disabled_autoloads', $disabled_autoloads);
                }
            }
        } elseif ($action === 'enable') {
            if (get_option($option_name, null) !== null) {
                $this->set_autoload($option_name, true);
                // Remove from disabled list if it was there
                $disabled_autoloads = get_option('edal_disabled_autoloads', array());
                $disabled_autoloads = array_diff($disabled_autoloads, array($option_name));
                update_option('edal_disabled_autoloads', $disabled_autoloads);
            }
        } elseif ($action === 'lock') {
            // Lock BOTH the autoload flag AND the option value
            global $wpdb;
            // Read the raw autoload column to capture the current state at lock time.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $current_autoload = $wpdb->get_var($wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                $option_name
            ));

            if ($current_autoload !== null) {
                $locked_autoloads = get_option('edal_locked_autoloads', array());
                // Store the autoload state as a normalized boolean (not the raw
                // column string, which varies by WP version), plus value + timestamp.
                $locked_autoloads[$option_name] = array(
                    'autoload' => $this->is_autoload_enabled($current_autoload),
                    'value' => get_option($option_name),
                    'locked_at' => time()
                );
                update_option('edal_locked_autoloads', $locked_autoloads, 'no');
            }
        } elseif ($action === 'unlock') {
            // Unlock the autoload value
            $locked_autoloads = get_option('edal_locked_autoloads', array());
            if (isset($locked_autoloads[$option_name])) {
                unset($locked_autoloads[$option_name]);
                update_option('edal_locked_autoloads', $locked_autoloads, 'no');
            }
        }

        // Clear cache before redirecting
        wp_cache_delete('alloptions', 'options');
        delete_option('edal_total_autoload_size');

        // Preserve current filters when redirecting
        $redirect_args = array(
            'page' => 'enhanced-autoload-manager',
            'action_complete' => $action
        );
        
        // Preserve filter parameters
        if (isset($_GET['mode'])) $redirect_args['mode'] = sanitize_text_field(wp_unslash($_GET['mode']));
        if (isset($_GET['search'])) $redirect_args['search'] = sanitize_text_field(wp_unslash($_GET['search']));
        if (isset($_GET['count'])) $redirect_args['count'] = intval(wp_unslash($_GET['count']));
        if (isset($_GET['orderby'])) $redirect_args['orderby'] = sanitize_text_field(wp_unslash($_GET['orderby']));
        if (isset($_GET['order'])) $redirect_args['order'] = sanitize_text_field(wp_unslash($_GET['order']));
        
        $redirect_url = add_query_arg($redirect_args, admin_url('tools.php'));
        $redirect_url = wp_nonce_url($redirect_url, 'edal_view_page');
        
        wp_redirect($redirect_url);
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
        
        // calculate_total_autoload_size() already persists the value (autoload=no).
        $total_autoload_size = $this->calculate_total_autoload_size();

        wp_send_json_success(array(
            'message' => __('Data refreshed successfully.', 'enhanced-autoload-manager'),
            'total_size_mb' => round($total_autoload_size / 1024 / 1024, 2)
        ));
    }

    public function ajax_export_settings() {
        check_ajax_referer('edal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'enhanced-autoload-manager')));
        }
        
        $settings = array(
            'disabled_autoloads' => get_option('edal_disabled_autoloads', array()),
            'edal_total_autoload_size' => get_option('edal_total_autoload_size', 0)
        );
        
        $filename = 'autoload-settings-' . gmdate('Y-m-d-H-i-s') . '.json';
        
        wp_send_json_success(array(
            'export_data' => $settings,
            'filename' => $filename
        ));
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
        
        if (isset($settings['edal_total_autoload_size'])) {
            update_option('edal_total_autoload_size', floatval($settings['edal_total_autoload_size']));
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
