# WP CLI and Abilities for Wordfence

WP-CLI commands and WordPress Abilities API integration for Wordfence Security plugin.

## Requirements

- WordPress 6.9+
- PHP 8.0+
- WP-CLI 2.5+
- Wordfence Security plugin installed and activated

## Installation

1. Upload the `wpcli-for-wordfence` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Run `composer install` (optional, for autoloader optimization)

## Quick Start - Typical Workflow

```bash
# 1. Check overall status
wp wfsec status

# 2. Start a scan
wp wfsec scan start

# 3. Check scan progress
wp wfsec scan status

# 4. View scan log
wp wfsec scan log

# 5. List found issues
wp wfsec issues ls

# 6. View issue count
wp wfsec issues count
```

## Available Commands

### General

```bash
# Show overall Wordfence status
wp wfsec status

# Export settings to file
wp wfsec export /path/to/settings.txt

# Import settings from file
wp wfsec import /path/to/settings.txt
```

### License

```bash
# Show license status
wp wfsec license status

# Activate license with API key
wp wfsec license activate YOUR_API_KEY

# Deactivate license
wp wfsec license deactivate

# Check if license is active (for scripting)
wp wfsec license check
```

### Scan

```bash
# Start a quick scan
wp wfsec scan start

# Start a full scan
wp wfsec scan start --type=full

# Check scan status (shows Running, Stage, Last Scan time)
wp wfsec scan status
wp wfsec scan status --format=json

# View scan log (detailed progress messages)
wp wfsec scan log
wp wfsec scan log --limit=50

# Watch scan progress in real-time
wp wfsec scan watch
wp wfsec scan watch --interval=10

# Stop running scan
wp wfsec scan stop

# Show last scan results summary
wp wfsec scan history
```

**Note:** Wordfence scans run in the background via HTTP callbacks. The scan typically completes in a few seconds for quick scans.

### Firewall

```bash
# Show firewall status
wp wfsec firewall status

# Enable/disable firewall
wp wfsec firewall enable
wp wfsec firewall disable

# Block an IP
wp wfsec firewall block 1.2.3.4
wp wfsec firewall block 1.2.3.4 --reason="Suspicious activity"
wp wfsec firewall block 1.2.3.4 --duration=3600

# Unblock an IP
wp wfsec firewall unblock 1.2.3.4

# Check if IP is blocked
wp wfsec firewall check 1.2.3.4

# List blocked IPs
wp wfsec firewall list
wp wfsec firewall list --limit=100 --format=json
```

### Configuration

```bash
# Get a config value
wp wfsec config get firewallEnabled

# Set a config value
wp wfsec config set firewallEnabled 1

# List common config keys
wp wfsec config list

# List all config values
wp wfsec config list --all

# Reset config to default
wp wfsec config reset firewallEnabled
```

### Issues

```bash
# List new issues (ls is alias for list_)
wp wfsec issues ls

# List all issues
wp wfsec issues ls --status=all

# List ignored issues
wp wfsec issues ls --status=ignoreP

# Show issue count
wp wfsec issues count

# Show issue details
wp wfsec issues show 123

# Delete an issue
wp wfsec issues delete 123

# Delete multiple issues
wp wfsec issues delete 123 456 789

# Delete all issues
wp wfsec issues delete --all

# Ignore an issue
wp wfsec issues ignore 123
```

## Output Formats

Most commands support multiple output formats:

```bash
wp wfsec issues ls --format=table  # default
wp wfsec issues ls --format=json
wp wfsec issues ls --format=csv
wp wfsec scan status --format=json
wp wfsec scan log --format=json
```

## WordPress Abilities API

This plugin supports the WordPress Abilities API (WordPress 6.9+), exposing Wordfence functionality for AI agents, automation tools, and REST API access.

### Authentication Setup

The REST API requires authentication via WordPress Application Passwords.

**1. Enable Application Passwords (if Wordfence blocks it):**

```bash
wp wfsec config set loginSec_disableApplicationPasswords 0
```

**2. Create an Application Password:**

- Go to WordPress Admin → Users → Your Profile
- Scroll to "Application Passwords"
- Enter a name (e.g., "API Access") and click "Add New"
- Copy the generated password (spaces are part of the format)

**3. Use in API requests:**

```bash
# Base64 encode credentials: username:application_password
AUTH=$(echo -n "username:XXXX XXXX XXXX XXXX XXXX XXXX" | base64)

# Use in Authorization header
curl -H "Authorization: Basic $AUTH" https://example.com/wp-json/wp/v2/abilities
```

### Available Abilities

| Ability | Description |
|---------|-------------|
| `wpcli-for-wordfence/scan-status` | Get current scan status and last scan info |
| `wpcli-for-wordfence/scan-start` | Start a security scan (quick/full) |
| `wpcli-for-wordfence/scan-stop` | Stop a running scan |
| `wpcli-for-wordfence/issues-list` | List security issues with filtering |
| `wpcli-for-wordfence/issues-count` | Get issue counts by status |
| `wpcli-for-wordfence/firewall-status` | Get WAF status and configuration |
| `wpcli-for-wordfence/license-status` | Get license information |

### REST API Endpoints

```bash
# List all abilities
GET /wp-json/wp/v2/abilities

# Get specific ability info
GET /wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-status

# Execute an ability
POST /wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-start/execute
Content-Type: application/json
{"scan_type": "quick"}
```

### Example: cURL

```bash
# Set credentials
SITE="https://example.com"
USER="admin"
PASS="XXXX XXXX XXXX XXXX XXXX XXXX"
AUTH=$(echo -n "$USER:$PASS" | base64)

# Get scan status
curl -s -H "Authorization: Basic $AUTH" \
  "$SITE/wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-status/execute" \
  -X POST -H "Content-Type: application/json" -d '{}'

# Start a scan
curl -s -H "Authorization: Basic $AUTH" \
  "$SITE/wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-start/execute" \
  -X POST -H "Content-Type: application/json" -d '{"scan_type": "quick"}'

# List issues
curl -s -H "Authorization: Basic $AUTH" \
  "$SITE/wp-json/wp/v2/abilities/wpcli-for-wordfence/issues-list/execute" \
  -X POST -H "Content-Type: application/json" -d '{"status": "new"}'
```

### Example: PHP

```php
<?php
$site = 'https://example.com';
$user = 'admin';
$pass = 'XXXX XXXX XXXX XXXX XXXX XXXX';

$auth = base64_encode("$user:$pass");

// Execute ability
$response = wp_remote_post("$site/wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-status/execute", [
    'headers' => [
        'Authorization' => "Basic $auth",
        'Content-Type'  => 'application/json',
    ],
    'body' => json_encode([]),
]);

$data = json_decode(wp_remote_retrieve_body($response), true);
print_r($data);
```

### Example: JavaScript (Node.js)

```javascript
const site = 'https://example.com';
const user = 'admin';
const pass = 'XXXX XXXX XXXX XXXX XXXX XXXX';
const auth = Buffer.from(`${user}:${pass}`).toString('base64');

// Get scan status
const response = await fetch(
  `${site}/wp-json/wp/v2/abilities/wpcli-for-wordfence/scan-status/execute`,
  {
    method: 'POST',
    headers: {
      'Authorization': `Basic ${auth}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({}),
  }
);

const data = await response.json();
console.log(data);
```

### Permission Requirements

All abilities require `manage_options` capability (Administrator role).

## Links

- **GitHub:** https://github.com/trueqap/wpcli-for-wordfence
- **Issues:** https://github.com/trueqap/wpcli-for-wordfence/issues

## License

GPL-2.0-or-later
