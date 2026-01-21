=== WP CLI and Abilities for Wordfence ===
Contributors: trueqap
Tags: wordfence, wp-cli, security, firewall, scan
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP-CLI commands and WordPress Abilities API integration for Wordfence Security plugin.

== Description ==

This plugin extends Wordfence Security with WP-CLI commands and WordPress Abilities API support, enabling command-line and REST API management of scans, issues, firewall, and license.

**Features:**

* Full WP-CLI support under `wp wfsec` namespace
* WordPress Abilities API integration (WordPress 6.9+)
* REST API endpoints for automation and AI agents
* Manage scans, issues, firewall, and license from CLI
* JSON/CSV/Table output formats

**Requirements:**

* WordPress 6.9 or higher
* PHP 8.0 or higher
* WP-CLI 2.5 or higher
* Wordfence Security plugin installed and activated

== Installation ==

1. Upload the `wpcli-for-wordfence` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure Wordfence Security is installed and activated
4. Use WP-CLI commands: `wp wfsec status`

== Frequently Asked Questions ==

= Does this plugin require Wordfence? =

Yes, this plugin requires Wordfence Security to be installed and activated. It provides CLI and API access to Wordfence functionality.

= What WP-CLI commands are available? =

* `wp wfsec status` - Show overall Wordfence status
* `wp wfsec scan start` - Start security scan
* `wp wfsec scan status` - Show scan status
* `wp wfsec scan log` - View scan log
* `wp wfsec scan stop` - Stop running scan
* `wp wfsec issues ls` - List security issues
* `wp wfsec issues count` - Count issues by status
* `wp wfsec firewall status` - Show firewall status
* `wp wfsec firewall enable/disable` - Toggle firewall
* `wp wfsec license status` - Show license info
* `wp wfsec config get/set` - Manage configuration

= How do I use the REST API? =

The plugin uses WordPress Application Passwords for authentication. Create an application password in your WordPress profile, then use Basic Authentication with the REST API endpoints.

Example:
`POST /wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-status/execute`

= What permissions are required? =

All WP-CLI commands and API abilities require `manage_options` capability (Administrator role).

== Screenshots ==

1. WP-CLI scan status output
2. Issues list in table format
3. Firewall status display

== Changelog ==

= 1.0.0 =
* Initial release
* WP-CLI commands under `wp wfsec` namespace
* WordPress Abilities API integration
* REST API endpoints via Abilities API
* Support for scans, issues, firewall, license, and configuration management

== Upgrade Notice ==

= 1.0.0 =
Initial release.
