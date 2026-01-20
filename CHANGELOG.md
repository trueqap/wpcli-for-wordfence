# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-20

### Added

- Initial release
- WP-CLI commands under `wp wfsec` namespace:
  - `wp wfsec status` - Show overall Wordfence status
  - `wp wfsec scan start` - Start security scan
  - `wp wfsec scan status` - Show scan status
  - `wp wfsec scan log` - View scan log
  - `wp wfsec scan stop` - Stop running scan
  - `wp wfsec issues ls` - List security issues
  - `wp wfsec issues count` - Count issues by status
  - `wp wfsec issues delete <id>` - Delete an issue
  - `wp wfsec issues ignore <id>` - Ignore an issue
  - `wp wfsec firewall status` - Show firewall status
  - `wp wfsec firewall enable/disable` - Toggle firewall
  - `wp wfsec license status` - Show license info
  - `wp wfsec license activate <key>` - Activate license
  - `wp wfsec license deactivate` - Deactivate license
  - `wp wfsec config get <key>` - Get config value
  - `wp wfsec config set <key> <value>` - Set config value
  - `wp wfsec config list` - List config options
  - `wp wfsec export` - Export settings
  - `wp wfsec import <file>` - Import settings
- WordPress Abilities API integration (WordPress 6.9+):
  - `wpcli-for-wordfence/scan-status` - Get scan status
  - `wpcli-for-wordfence/scan-start` - Start scan
  - `wpcli-for-wordfence/scan-stop` - Stop scan
  - `wpcli-for-wordfence/issues-list` - List issues
  - `wpcli-for-wordfence/issues-count` - Count issues
  - `wpcli-for-wordfence/firewall-status` - Get firewall status
  - `wpcli-for-wordfence/license-status` - Get license status
- REST API endpoints via Abilities API
- Permission checks (`manage_options` capability required)
- SQL injection protection with `$wpdb->prepare()`
- PSR-4 autoloading

### Security

- All database queries use prepared statements
- All Abilities require administrator capability
- Input validation for IP addresses and API keys
- Output escaping for admin notices

[1.0.0]: https://github.com/trueqap/wpcli-for-wordfence/releases/tag/v1.0.0
