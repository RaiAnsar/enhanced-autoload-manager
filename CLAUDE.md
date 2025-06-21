# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Enhanced Autoload Manager" that helps manage autoloaded data in WordPress databases. The plugin provides a web interface to view, delete, disable, and manage WordPress autoload options to improve site performance.

## File Structure

- `enhanced-autoload-manager.php` - Main plugin file containing the core PHP class
- `script.js` - Primary JavaScript for modal handling, AJAX operations (export/import/refresh)
- `scripts.js` - Secondary JavaScript for bulk operations and form handling
- `styles.css` - CSS styling for the admin interface
- `README.md` & `readme.txt` - Plugin documentation (WordPress format)

## Architecture

### Main PHP Class: `Enhanced_Autoload_Manager`

The plugin follows WordPress plugin patterns with a single main class that:

- Hooks into WordPress admin system via `admin_menu`, `admin_init`, and `admin_enqueue_scripts`
- Handles AJAX endpoints for data refresh, export/import operations
- Manages autoload option operations (delete, disable, enable)
- Provides filtering and pagination for autoload data display

### Key Methods:
- `get_autoload_data()` - Retrieves and filters autoload options based on mode/search
- `calculate_total_autoload_size()` - Computes total size of autoloaded data
- `handle_actions()` - Processes form submissions for bulk operations
- `display_page()` - Renders the main admin interface

### Security Features:
- WordPress nonces for all form submissions and AJAX requests
- User capability checks (`manage_options`)
- Input sanitization and validation
- SQL injection protection via prepared statements

## Development Workflow

### No Build Process
This is a standard WordPress plugin that doesn't require compilation or build tools. Files are used directly.

### Testing
- Test in WordPress environment by installing the plugin
- Navigate to Tools > Enhanced Autoload Manager in WordPress admin
- Verify functionality across different modes (Basic, Expert, Plugin-specific filters)

### Plugin Installation
1. Copy plugin files to `/wp-content/plugins/enhanced-autoload-manager/`
2. Activate through WordPress admin Plugins page
3. Access via Tools menu

## Key Functionality Areas

### Data Management
- Autoload options are retrieved using `wp_load_alloptions()`
- Custom tracking of disabled autoloads via `edal_disabled_autoloads` option
- Real-time size calculations stored in `total_autoload_size` option

### Interface Modes
- **Basic**: Non-core WordPress options only
- **Expert**: All autoload options (includes WordPress core)
- **Plugin-specific**: Filters for WooCommerce, Elementor
- **Status filters**: All, Disabled options

### AJAX Operations
- Refresh data without page reload
- Export autoload settings as JSON
- Import settings from JSON file
- All operations use WordPress AJAX with proper nonce verification

## Common Tasks

### Adding New Filter Modes
Modify `get_autoload_data()` method and add corresponding UI tabs in `display_page()`

### Modifying UI
- Update `styles.css` for styling changes
- JavaScript changes go in `script.js` (modals, AJAX) or `scripts.js` (bulk operations)

### Security Updates
All user inputs are sanitized using WordPress functions like `sanitize_text_field()` and prepared statements for database queries.