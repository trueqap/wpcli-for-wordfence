<?php
/**
 * Plugin Name: WP CLI and Abilities for Wordfence
 * Plugin URI: https://github.com/trueqap/wpcli-for-wordfence
 * Description: WP-CLI commands and WordPress Abilities API integration for Wordfence Security - manage scans, issues, firewall, and license from CLI and REST API
 * Version: 1.0.0
 * Author: trueqap
 * Author URI: https://github.com/trueqap
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpcli-for-wordfence
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Requires Plugins: wordfence
 * Update URI: false
 */

namespace WordfenceCLI;

if (!defined('ABSPATH')) {
    exit;
}

define('WORDFENCE_CLI_VERSION', '1.0.0');
define('WORDFENCE_CLI_PATH', plugin_dir_path(__FILE__));

// Autoloader
if (file_exists(WORDFENCE_CLI_PATH . 'vendor/autoload.php')) {
    require_once WORDFENCE_CLI_PATH . 'vendor/autoload.php';
} else {
    // Manual autoload for non-composer installs
    spl_autoload_register(function ($class) {
        $prefix = 'WordfenceCLI\\';
        $base_dir = WORDFENCE_CLI_PATH . 'src/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Check if Wordfence is active
add_action('plugins_loaded', function() {
    if (!class_exists('wordfence')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('WPCLI for Wordfence requires Wordfence Security plugin to be installed and activated.', 'wpcli-for-wordfence');
            echo '</p></div>';
        });
        return;
    }

    // Initialize WordPress Abilities API support
    Abilities\AbilitiesProvider::init();
});

// Register WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    add_action('plugins_loaded', function() {
        if (!class_exists('wordfence')) {
            return;
        }

        \WP_CLI::add_command('wfsec', Commands\MainCommand::class);
        \WP_CLI::add_command('wfsec scan', Commands\ScanCommand::class);
        \WP_CLI::add_command('wfsec firewall', Commands\FirewallCommand::class);
        \WP_CLI::add_command('wfsec config', Commands\ConfigCommand::class);
        \WP_CLI::add_command('wfsec issues', Commands\IssuesCommand::class);
        \WP_CLI::add_command('wfsec license', Commands\LicenseCommand::class);
    }, 20);
}
