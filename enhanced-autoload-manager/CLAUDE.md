# CLAUDE.md - Enhanced Autoload Manager

This file provides guidance to Claude Code (claude.ai/code) when working with the Enhanced Autoload Manager WordPress plugin.

## Project Overview

Enhanced Autoload Manager is a WordPress plugin that helps manage autoloaded data in WordPress databases. The plugin provides a web interface to view, delete, disable, and manage WordPress autoload options to improve site performance.

## Key Information

- **Plugin Name:** Enhanced Autoload Manager
- **Current Version:** 1.5.4
- **Author:** Rai Ansar
- **Website:** https://raiansar.com
- **WordPress.org:** https://wordpress.org/plugins/enhanced-autoload-manager
- **GitHub:** https://github.com/RaiAnsar/enhanced-autoload-manager

## File Structure

```
enhanced-autoload-manager/
├── enhanced-autoload-manager.php   # Main plugin file
├── script.js                       # JavaScript for AJAX operations
├── styles.css                      # Plugin styling
├── readme.txt                      # WordPress.org readme
├── README.md                       # GitHub readme
├── LICENSE                         # GPLv3 license
├── credentials.md                  # SVN credentials (DO NOT COMMIT)
├── CLAUDE.md                       # This file
└── .gitignore                      # Git ignore rules
```

## Architecture

### Main PHP Class: `Enhanced_Autoload_Manager`

The plugin follows WordPress coding standards with a single main class that:
- Hooks into WordPress admin system
- Handles AJAX endpoints for data operations
- Manages autoload option operations
- Provides filtering and pagination

### Key Features
1. **View Modes:** Basic, Expert, Plugin-specific filters
2. **Operations:** Delete, Disable/Enable autoload options
3. **Import/Export:** JSON format for settings backup
4. **Search & Filter:** Real-time search and filtering
5. **Bulk Actions:** Handle multiple options at once

## Security Standards

All code must follow WordPress security best practices:
- ✅ Nonce verification for all forms and AJAX
- ✅ Capability checks (`manage_options`)
- ✅ Input sanitization (sanitize_text_field, intval, etc.)
- ✅ Output escaping (esc_html__, esc_attr, esc_url)
- ✅ SQL injection protection via WordPress APIs
- ✅ Prefixed option names (`edal_` prefix)

## Development Workflow

### Local Development
1. Use WordPress local environment
2. Enable WP_DEBUG for testing
3. Test with Plugin Check tool
4. Verify PHP 7.4+ compatibility

### Version Control
- **GitHub:** Development and collaboration
- **SVN:** WordPress.org distribution

### Release Process
1. Update version in all locations:
   - Plugin header (enhanced-autoload-manager.php)
   - EDAL_VERSION constant
   - readme.txt stable tag
2. Test thoroughly with WP_DEBUG
3. Run Plugin Check tool
4. Commit to GitHub
5. Deploy to WordPress.org via SVN

## SVN Deployment

### Initial Setup
```bash
svn checkout https://plugins.svn.wordpress.org/enhanced-autoload-manager enhanced-autoload-manager-svn --username raiansar
```

### Deploy New Version
```bash
# Copy files to trunk
cp -r enhanced-autoload-manager/* enhanced-autoload-manager-svn/trunk/

# Add/update files
svn add trunk/* --force
svn commit -m "Update to version X.X.X" --username raiansar

# Create tag
svn copy trunk tags/X.X.X
svn commit -m "Tagging version X.X.X" --username raiansar
```

### Assets Management
Plugin assets go in the `assets/` directory in SVN root:
- `banner-772x250.png` - Plugin banner
- `banner-1544x500.png` - High-res banner
- `icon-128x128.png` - Plugin icon
- `icon-256x256.png` - High-res icon
- `screenshot-*.png` - Plugin screenshots

## Common Tasks

### Adding New Features
1. Follow existing code patterns
2. Maintain backward compatibility
3. Add proper security checks
4. Update documentation

### Fixing Bugs
1. Reproduce the issue
2. Add debugging with WP_DEBUG
3. Fix with minimal changes
4. Test thoroughly
5. Update changelog in readme.txt

### Updating Dependencies
- This plugin has no external dependencies
- Uses only WordPress core functions
- jQuery provided by WordPress

## Testing Checklist

Before any release:
- [ ] WP_DEBUG shows no errors
- [ ] Plugin Check passes
- [ ] All AJAX operations work
- [ ] Import/Export functions properly
- [ ] Bulk actions execute correctly
- [ ] Search and filtering work
- [ ] Mobile responsive design works
- [ ] Compatible with latest WordPress

## Support Information

- **WordPress.org Forums:** Check plugin support forum
- **GitHub Issues:** For development discussions
- **Email:** hi@raiansar.com (for professional inquiries)

## Important Notes

1. **NEVER commit credentials.md to version control**
2. Always test with different user roles
3. Maintain backward compatibility
4. Follow WordPress coding standards
5. Keep security as top priority
6. Document significant changes

## Quick Reference

- **Plugin Prefix:** `edal_`
- **Text Domain:** `enhanced-autoload-manager`
- **Capability Required:** `manage_options`
- **Minimum PHP:** 7.4
- **Minimum WordPress:** 5.0
- **License:** GPLv3 or later