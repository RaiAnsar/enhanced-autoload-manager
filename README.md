# Enhanced Autoload Manager

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Version](https://img.shields.io/badge/Version-1.5.3-orange.svg)](https://github.com/RaiAnsar/enhanced-autoload-manager/releases)

A modern WordPress plugin for managing and optimizing autoloaded database options with an intuitive interface.

## 📋 Description

The Enhanced Autoload Manager helps you optimize your WordPress database by providing complete control over autoloaded options. Autoloaded data can accumulate over time and significantly impact your website's performance by loading unnecessary data on every page request.

### 🎯 Key Benefits
- **Performance Optimization** - Reduce database overhead by managing autoloaded options
- **Database Cleanup** - Remove obsolete and unnecessary autoloaded data
- **Site Speed Improvement** - Faster page loads through optimized autoload management
- **Safety Features** - Built-in warnings and confirmation dialogs to prevent accidents

## ✨ Features

### Core Functionality
- 🔍 **Smart Search** - Find specific autoload options instantly
- 📊 **Size Analysis** - View autoload data sizes in KB/MB for easy prioritization
- 🗂️ **Advanced Filtering** - Filter by WordPress core, WooCommerce, Elementor, or custom options
- 📱 **Responsive Design** - Works perfectly on desktop, tablet, and mobile devices

### Management Tools
- ✅ **Bulk Actions** - Manage multiple options simultaneously
- 🔄 **Export/Import** - Backup and restore autoload settings
- 🎛️ **Expert Mode** - Advanced view for experienced users (with safety warnings)
- 📄 **Pagination** - Navigate large datasets efficiently

### Safety & Security
- 🛡️ **WordPress Nonces** - CSRF protection for all actions
- ⚠️ **Safety Warnings** - Alerts for potentially dangerous operations
- 🔒 **Input Sanitization** - All user inputs properly sanitized and validated
- ✅ **Plugin Check Compliant** - Passes all WordPress.org security standards

## 🚀 Installation

### From WordPress Admin
1. Go to **Plugins > Add New**
2. Search for "Enhanced Autoload Manager"
3. Click **Install** and then **Activate**
4. Navigate to **Tools > Enhanced Autoload Manager**

### Manual Installation
1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/enhanced-autoload-manager/) or [GitHub](https://github.com/RaiAnsar/enhanced-autoload-manager)
2. Upload the `enhanced-autoload-manager` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu
4. Go to **Tools > Enhanced Autoload Manager**

### Via WP-CLI
```bash
wp plugin install enhanced-autoload-manager --activate
```

## 📖 Usage

### Basic Mode (Recommended)
- Shows only non-core WordPress autoload options
- Safe for beginners and general use
- Filters out critical WordPress core options

### Expert Mode (Advanced Users)
- Displays ALL autoload options including WordPress core
- Shows safety warning before enabling
- Requires careful handling to avoid site breakage

### Key Operations
1. **View Autoloads** - See all autoloaded options with sizes
2. **Search Options** - Find specific entries quickly
3. **Delete Options** - Remove unnecessary autoloaded data
4. **Disable Autoload** - Keep data but stop autoloading
5. **Export Settings** - Backup your configuration
6. **Import Settings** - Restore from backup

## 🔧 Development

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Local Development
```bash
# Clone the repository
git clone https://github.com/RaiAnsar/enhanced-autoload-manager.git

# Navigate to your WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Copy or symlink the plugin
cp -r enhanced-autoload-manager ./
```

### Code Standards
- Follows WordPress Coding Standards
- PSR-4 autoloading compatible
- Comprehensive input sanitization
- Proper nonce verification
- Plugin Check validated

## 📸 Screenshots

1. **Main Interface** - Clean, modern dashboard showing autoload data
2. **Expert Mode Warning** - Safety notification with dismissible design  
3. **Navigation Tabs** - Intuitive filtering and pagination controls

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Workflow
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPLv3 License - see the [LICENSE](LICENSE) file for details.

## 📞 Support & Contact

**Need custom WordPress development?**

🧑‍💻 **Rai Ansar**  
📧 **Email:** [hi@raiansar.com](mailto:hi@raiansar.com)  
🌐 **Website:** [raiansar.com](https://raiansar.com)  

### Specializing in:
- 🔌 Custom WordPress Plugins
- ⚛️ React.js & Next.js Development  
- 🛒 WooCommerce Solutions
- 🖥️ Server Management & Optimization

## 🏷️ Version History

See [readme.txt](readme.txt) for detailed version history.

---

**⭐ If you find this plugin helpful, please consider starring the repository and leaving a review on WordPress.org!**