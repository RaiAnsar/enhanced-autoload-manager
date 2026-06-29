=== Enhanced Autoload Manager ===
Contributors: raiansar
Tags: autoload, autoload manager, performance, database cleanup, optimization
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.6.4
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Enhanced Autoload Manager - A sleek plugin to manage and optimize autoloaded data in your WordPress database, with a modern and intuitive interface.

== Description ==

The Enhanced Autoload Manager plugin allows you to easily manage the autoloaded data within your WordPress database. Autoloaded data can accumulate over time and slow down your website by loading unnecessary data on every page request. This plugin offers a simple, yet powerful interface to delete or disable specific autoload data, helping to improve the performance and speed of your site.

With the Enhanced Autoload Manager, you'll get a clear overview of the top autoload entries and their sizes. The plugin provides a modern and aesthetic interface with actionable buttons that let you either delete or disable the autoload option right from the dashboard. For convenience and clarity, the data sizes are displayed in KBs and MBs.

Beyond managing individual entries, Enhanced Autoload Manager also displays the total size of all autoloaded data, giving you a better sense of your site's autoload footprint.

== Installation ==

1. Upload the 'enhanced-autoload-manager' folder to the '/wp-content/plugins/' directory.
2. Activate the Enhanced Autoload Manager through the 'Plugins' menu in WordPress.
3. Navigate to 'Tools' > 'Enhanced Autoload Manager' in your WordPress admin to manage autoload data.

== Frequently Asked Questions ==

= Does it require any configuration? =

No, it's simple to use. Install and navigate to the Tools menu to start optimizing autoloaded data.

= Is it safe to delete autoload data? =

Always make sure to backup your database before deleting data. While the plugin is safe to use, caution is always recommended.

= What is the difference between Basic and Expert mode? =

Basic mode hides WordPress core autoload options to prevent accidental deletion of critical data. Expert mode shows all autoload options including core WordPress options - use with caution.

= Can I export my autoload settings? =

Yes, the plugin includes export and import functionality to backup your autoload settings or migrate them between sites.

== Screenshots ==

1. Main plugin interface showing autoload data with search functionality and action buttons
2. Expert mode warning with modern styling and dismissible notice  
3. Plugin navigation tabs with filtering options by mode, plugin, and status

== Features ==

- List autoloaded data entries sorted by size with configurable limit options
- Search functionality to find specific autoload options
- Pagination for easier navigation through large datasets
- Export and import autoload settings for backup or site migration
- Confirmation dialogs before deleting or disabling options
- Refresh button to update autoload data without reloading the page
- Display data size in a readable format (KB and MB)
- One-click action buttons to delete or disable autoload options
- Total autoloaded data size display on the plugin page
- Option to view autoload entry contents via expand button
- Filter options by core WordPress, WooCommerce, or Elementor
- Mobile-responsive design for better usability on all devices
- Simple, modern, and intuitive interface with no dependencies on external libraries

== Changelog ==

= 1.6.4 =
* Fixed: Disable/Enable now actually change the autoload flag. They previously re-saved the option with its unchanged value, which WordPress short-circuits before applying the autoload change — so "disabled" options kept autoloading. Now uses the dedicated autoload setter.
* Fixed: Bulk actions now respect locked options (they are skipped, matching the per-row buttons) and no longer leave orphaned lock entries.
* Improved: The plugin's own lock storage is no longer autoloaded, so locking large options can't bloat the autoload footprint.
* Fixed: Locking is now reliable on WordPress 6.6+. Locks compared the raw autoload column ('yes'/'no'), but modern WordPress uses 'on'/'off'/'auto-on'/'auto-off'/'auto' and normalizes values on save — so locked options were being "restored" on every page load with repeated notices. Locks now track the autoload state semantically.
* Fixed: Locked options are now restored immediately after a plugin/theme/WordPress update (the once-per-request guard previously skipped the post-update restore).
* Fixed: Locked option values are now compared in an object/array-safe way, so complex values no longer trigger false restores.
* Fixed: Disabled options no longer disappear from the list — they now stay visible (with an Enable button) and the Disabled tab works again
* Fixed: Removed an N+1 database query in total-size calculation (one query per option on every load/refresh); total autoload size is now computed from the already-loaded options
* Fixed: Prevented a fatal "division by zero" when an invalid items-per-page value was passed in the URL
* Security: Added explicit capability checks to all delete/disable/enable/lock actions (in addition to existing nonce checks)
* Fixed: Corrected the object cache group when clearing the alloptions cache after an action
* Compatibility: Tested up to WordPress 7.0; verified clean on PHP 8.5
* Maintenance: Replaced deprecated date()/current_time('timestamp') calls; removed a redundant option write

= 1.6.3 =
* CRITICAL FIX: Locking feature now reliably prevents automatic modifications from WordPress/plugin updates
* Fixed: Locked options now preserve BOTH autoload flag AND option value (not just flag)
* Fixed: Restore hooks now run on init, admin_init, updated_option, and upgrader_process_complete
* Fixed: Real-time protection against option value changes via updated_option hook
* Fixed: UI now hides Disable/Delete buttons for locked options to prevent user confusion
* Fixed: Deleted options are now properly removed from lock list
* Added: Admin notices when locked options are automatically restored
* Added: Automatic upgrade of old lock data format (string) to new format (array with value + timestamp)
* Added: Debug logging when WP_DEBUG is enabled for lock violations
* Added: Helpful tooltip "(Unlock to modify)" for locked options
* Improved: Lock data now includes autoload flag, full option value, and locked_at timestamp
* Improved: Prevents multiple restore executions in same request for better performance

= 1.5.3 =
* Fixed Plugin Check compliance issues
* Improved input sanitization and security
* Enhanced footer styling and responsive design
* Added proper WordPress.org submission headers
* Fixed duplicate dismiss buttons in warning notices

= 1.5.2 =
* Added modern gradient warning design with persistent dismissal
* Improved search bar placement and layout
* Enhanced CSS scoping to prevent WordPress admin conflicts
* Added activation and deactivation hooks
* Fixed AJAX handler naming consistency

= 1.4 =
* Added search functionality to find specific autoload options
* Implemented pagination for easier navigation through large datasets
* Added confirmation dialogs before deleting or disabling options
* Added export and import functionality for autoload settings
* Added refresh button to update autoload data without page reload
* Improved cache handling for better performance
* Added support for mobile devices with responsive design
* Fixed version inconsistencies across plugin files

= 1.3 =
* Added WordPress Nonce for extra layer of security

= 1.2 =
* Added total autoload size display
* Enhanced the user interface for modern and aesthetic look
* Improved display of data sizes in KB and MB

= 1.1 =
* First functional release with basic features

== Source Code ==

This plugin is open source and available on GitHub: https://github.com/RaiAnsar/enhanced-autoload-manager

== Support ==

Need custom WordPress plugins, WooCommerce sites, or server optimization? Contact **Rai Ansar** at hi@raiansar.com

Specializing in: Custom WordPress Plugins • React.js & Next.js Development • WooCommerce Solutions • Server Management & Optimization