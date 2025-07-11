# Enhanced Autoload Manager - Maintenance Guide

## Quick Start for Future Updates

### 1. Making Changes
```bash
# Navigate to plugin directory
cd /Users/rai/Desktop/Work/PerosnalWork/wp_plugins/enhanced-autoload-manager

# Make your changes to the code
# Update version number in:
# - enhanced-autoload-manager.php (header and EDAL_VERSION)
# - readme.txt (Stable tag)

# Test locally with WP_DEBUG enabled
```

### 2. Deploy to WordPress.org

```bash
# Check out SVN if not already done
svn checkout https://plugins.svn.wordpress.org/enhanced-autoload-manager enhanced-autoload-manager-svn --username raiansar

# Update SVN trunk
cd enhanced-autoload-manager-svn
cp -r ../enhanced-autoload-manager/* trunk/
svn add trunk/* --force
svn status

# Commit changes
svn commit -m "Update to version X.X.X - Brief description" --username raiansar

# Tag the new version
svn copy trunk tags/X.X.X
svn commit -m "Tagging version X.X.X" --username raiansar
```

### 3. Update Changelog

Always update the changelog in `readme.txt`:

```
== Changelog ==

= X.X.X =
* Feature: Description
* Fix: Description
* Enhancement: Description

= 1.5.4 =
* Fix: Corrected nonce validation security issue
* Fix: Added proper escaping for all translatable strings
* Fix: Prefixed option names to prevent conflicts
* Enhancement: Reduced tags to meet WordPress.org requirements
```

## Regular Maintenance Tasks

### Monthly
- Check WordPress.org support forum for issues
- Test with latest WordPress version
- Review and respond to user reviews

### Quarterly
- Security audit with Plugin Check
- Performance optimization review
- Update screenshots if UI changed

### Annually
- Review and update minimum PHP/WordPress requirements
- Comprehensive security review
- Update documentation

## Common Issues & Solutions

### Issue: Plugin not appearing in search
**Solution:** Wait 72 hours after upload, ensure tags are relevant

### Issue: SVN commit fails
**Solution:** Check credentials, ensure username is lowercase `raiansar`

### Issue: Assets not showing
**Solution:** Assets must be in `/assets/` directory at SVN root, not in trunk

## Version Numbering

Follow semantic versioning:
- **Major (X.0.0):** Breaking changes, major rewrites
- **Minor (1.X.0):** New features, enhancements
- **Patch (1.5.X):** Bug fixes, security updates

## Emergency Procedures

### Security Vulnerability Found
1. Fix immediately in local environment
2. Test thoroughly
3. Update version as patch release
4. Deploy to SVN ASAP
5. Email plugins@wordpress.org if critical

### Plugin Causing Site Issues
1. Investigate in local environment
2. If critical, consider adding check in main plugin file
3. Release hotfix version
4. Monitor support forum closely

## Support Workflow

1. **Check Support Forum Daily:** https://wordpress.org/support/plugin/enhanced-autoload-manager/
2. **Respond Promptly:** Within 24-48 hours
3. **Be Professional:** Always courteous and helpful
4. **Document Solutions:** Add to FAQ if recurring issue

## Asset Guidelines

### Banner (772x250px & 1544x500px)
- Clean, professional design
- Include plugin name
- Consistent with WordPress.org aesthetic

### Icon (128x128px & 256x256px)
- Simple, recognizable symbol
- Works on light and dark backgrounds
- Square format, can have transparency

### Screenshots
- Show actual plugin interface
- Include captions in readme.txt
- Update when UI changes significantly

## Contact Information

- **Developer:** Rai Ansar
- **Email:** hi@raiansar.com
- **Website:** https://raiansar.com
- **GitHub:** https://github.com/RaiAnsar/enhanced-autoload-manager

Remember: Always test thoroughly before releasing updates!