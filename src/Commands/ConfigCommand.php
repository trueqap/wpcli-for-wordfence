<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Manage Wordfence configuration
 *
 * ## EXAMPLES
 *
 *     # Get a config value
 *     $ wp wfsec config get firewallEnabled
 *
 *     # Set a config value
 *     $ wp wfsec config set firewallEnabled 1
 *
 *     # List all config values
 *     $ wp wfsec config list
 */
class ConfigCommand
{
    /**
     * Common config keys for reference
     */
    private static $commonKeys = [
        'firewallEnabled' => 'Enable/disable firewall (1/0)',
        'learningModeEnabled' => 'Enable/disable learning mode (1/0)',
        'scheduleScan' => 'Enable/disable scheduled scans (1/0)',
        'scansEnabled_core' => 'Scan WordPress core files (1/0)',
        'scansEnabled_plugins' => 'Scan plugin files (1/0)',
        'scansEnabled_themes' => 'Scan theme files (1/0)',
        'scansEnabled_malware' => 'Scan for malware signatures (1/0)',
        'scansEnabled_fileContents' => 'Scan file contents (1/0)',
        'alertOn_critical' => 'Alert on critical issues (1/0)',
        'alertOn_warnings' => 'Alert on warnings (1/0)',
        'alertEmails' => 'Email addresses for alerts (comma-separated)',
        'liveTraf_enabled' => 'Enable live traffic logging (1/0)',
        'liveTrafficEnabled' => 'Enable live traffic view (1/0)',
        'loginSecurityEnabled' => 'Enable login security features (1/0)',
        'loginSec_maxFailures' => 'Max login failures before lockout',
        'loginSec_countFailMins' => 'Count failures within X minutes',
        'loginSec_lockoutMins' => 'Lockout duration in minutes',
        'blockFakeBots' => 'Block fake Google crawlers (1/0)',
        'bannedURLs' => 'Banned URL patterns (one per line)',
        'whitelisted' => 'Whitelisted IPs (one per line)',
        'howGetIPs' => 'How to get visitor IPs',
    ];

    /**
     * Get a config value
     *
     * ## OPTIONS
     *
     * <key>
     * : Config key to get
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec config get firewallEnabled
     *
     * @when after_wp_load
     */
    public function get($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide a config key.');
        }

        $key = $args[0];
        $value = WordfenceService::getConfig($key);

        if (is_array($value) || is_object($value)) {
            WP_CLI::log(json_encode($value, JSON_PRETTY_PRINT));
        } elseif (is_bool($value)) {
            WP_CLI::log($value ? 'true' : 'false');
        } elseif ($value === null) {
            WP_CLI::log('(not set)');
        } else {
            WP_CLI::log((string) $value);
        }
    }

    /**
     * Set a config value
     *
     * ## OPTIONS
     *
     * <key>
     * : Config key to set
     *
     * <value>
     * : Value to set
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec config set firewallEnabled 1
     *     $ wp wfsec config set alertEmails "admin@example.com"
     *
     * @when after_wp_load
     */
    public function set($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (count($args) < 2) {
            WP_CLI::error('Please provide a config key and value.');
        }

        $key = $args[0];
        $value = $args[1];

        // Convert string booleans
        if ($value === 'true') {
            $value = 1;
        } elseif ($value === 'false') {
            $value = 0;
        }

        if (WordfenceService::setConfig($key, $value)) {
            WP_CLI::success(sprintf('Config "%s" set to "%s".', $key, $value));
        } else {
            WP_CLI::error('Failed to set config value.');
        }
    }

    /**
     * List common config keys
     *
     * ## OPTIONS
     *
     * [--all]
     * : Show all config values (may be large)
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec config list
     *     $ wp wfsec config list --all
     *
     * @when after_wp_load
     * @alias ls
     */
    public function list_($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $showAll = isset($assoc_args['all']);
        $format = $assoc_args['format'] ?? 'table';

        if ($showAll) {
            // Get all config from database
            global $wpdb;
            $table = $wpdb->prefix . 'wfConfig';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results(
                "SELECT name, val FROM {$table} ORDER BY name",
                ARRAY_A
            );

            $items = array_map(function ($row) {
                $value = $row['val'];
                if (strlen($value) > 50) {
                    $value = substr($value, 0, 47) . '...';
                }
                return [
                    'Key' => $row['name'],
                    'Value' => $value,
                ];
            }, $results);
        } else {
            // Show common keys with their current values
            $items = [];
            foreach (self::$commonKeys as $key => $description) {
                $value = WordfenceService::getConfig($key, '(not set)');
                if (is_array($value)) {
                    $value = '[array]';
                } elseif (strlen((string) $value) > 30) {
                    $value = substr($value, 0, 27) . '...';
                }
                $items[] = [
                    'Key' => $key,
                    'Value' => (string) $value,
                    'Description' => $description,
                ];
            }
        }

        if (empty($items)) {
            WP_CLI::log('No config values found.');
            return;
        }

        $columns = $showAll ? ['Key', 'Value'] : ['Key', 'Value', 'Description'];
        WP_CLI\Utils\format_items($format, $items, $columns);
    }

    /**
     * Reset a config value to default
     *
     * ## OPTIONS
     *
     * <key>
     * : Config key to reset
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec config reset firewallEnabled
     *
     * @when after_wp_load
     */
    public function reset($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide a config key.');
        }

        $key = $args[0];

        // Delete the config to reset to default
        global $wpdb;
        $table = $wpdb->prefix . 'wfConfig';
        $wpdb->delete($table, ['name' => $key]);

        WP_CLI::success(sprintf('Config "%s" reset to default.', $key));
    }
}
