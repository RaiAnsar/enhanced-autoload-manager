=== Enhanced Autoload Manager ===
Contributors: raiansar
Tags: autoload, performance, database, optimization, speed
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.5.8
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

= 1.5.8 =
* Fixed filter state preservation on delete/disable/enable actions
* Action buttons now maintain current search, mode, and sort settings
* Redirects after actions preserve all filter parameters
* Improved user experience with consistent filtering

= 1.5.7 =
* Fixed search form security check error - added missing nonce field
* Fixed clear search link to include proper nonce
* Search functionality now properly validates security tokens

= 1.5.6 =
* Fixed AJAX refresh data error - corrected nonce verification issue
* Fixed export functionality returning undefined filename
* Corrected data key mismatch in refresh response (total_size_mb)
* Export now properly includes filename with timestamp

= 1.5.5 =
* CRITICAL FIX: Added nonce security to all navigation links
* Fixed security check failed error on all plugin operations
* Improved URL generation with proper WordPress nonce handling
* All navigation tabs now include proper security tokens

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