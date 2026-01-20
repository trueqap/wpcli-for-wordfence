<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Manage Wordfence firewall
 *
 * ## EXAMPLES
 *
 *     # Show firewall status
 *     $ wp wfsec firewall status
 *
 *     # Block an IP
 *     $ wp wfsec firewall block 1.2.3.4
 *
 *     # Unblock an IP
 *     $ wp wfsec firewall unblock 1.2.3.4
 *
 *     # List blocked IPs
 *     $ wp wfsec firewall list
 */
class FirewallCommand
{
    /**
     * Show firewall status
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $status = WordfenceService::getFirewallStatus();

        $items = [
            ['Property' => 'Enabled', 'Value' => $status['enabled'] ? 'Yes' : 'No'],
            ['Property' => 'Learning Mode', 'Value' => $status['learning_mode'] ? 'Yes' : 'No'],
            ['Property' => 'WAF Status', 'Value' => $status['waf_status']],
            ['Property' => 'Rules Updated', 'Value' => $status['rules_updated']],
        ];

        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'json') {
            WP_CLI::log(json_encode($status, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items($format, $items, ['Property', 'Value']);
        }
    }

    /**
     * Enable the firewall
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall enable
     *
     * @when after_wp_load
     */
    public function enable($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (WordfenceService::setFirewallEnabled(true)) {
            WP_CLI::success('Firewall enabled.');
        } else {
            WP_CLI::error('Failed to enable firewall.');
        }
    }

    /**
     * Disable the firewall
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall disable
     *
     * @when after_wp_load
     */
    public function disable($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        WP_CLI::confirm('Are you sure you want to disable the firewall?', $assoc_args);

        if (WordfenceService::setFirewallEnabled(false)) {
            WP_CLI::success('Firewall disabled.');
        } else {
            WP_CLI::error('Failed to disable firewall.');
        }
    }

    /**
     * Block an IP address
     *
     * ## OPTIONS
     *
     * <ip>
     * : IP address to block
     *
     * [--reason=<reason>]
     * : Reason for blocking
     * ---
     * default: Blocked via CLI
     * ---
     *
     * [--duration=<seconds>]
     * : Block duration in seconds (0 = permanent)
     * ---
     * default: 0
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall block 1.2.3.4
     *     $ wp wfsec firewall block 1.2.3.4 --reason="Suspicious activity"
     *     $ wp wfsec firewall block 1.2.3.4 --duration=3600
     *
     * @when after_wp_load
     */
    public function block($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide an IP address.');
        }

        $ip = $args[0];
        $reason = $assoc_args['reason'] ?? 'Blocked via CLI';
        $duration = (int) ($assoc_args['duration'] ?? 0);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            WP_CLI::error('Invalid IP address.');
        }

        if (WordfenceService::isIPBlocked($ip)) {
            WP_CLI::warning(sprintf('IP %s is already blocked.', $ip));
            return;
        }

        if (WordfenceService::blockIP($ip, $reason, $duration)) {
            $durationText = $duration > 0 ? sprintf(' for %d seconds', $duration) : ' permanently';
            WP_CLI::success(sprintf('IP %s blocked%s.', $ip, $durationText));
        } else {
            WP_CLI::error(sprintf('Failed to block IP %s.', $ip));
        }
    }

    /**
     * Unblock an IP address
     *
     * ## OPTIONS
     *
     * <ip>
     * : IP address to unblock
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall unblock 1.2.3.4
     *
     * @when after_wp_load
     */
    public function unblock($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide an IP address.');
        }

        $ip = $args[0];

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            WP_CLI::error('Invalid IP address.');
        }

        if (WordfenceService::unblockIP($ip)) {
            WP_CLI::success(sprintf('IP %s unblocked.', $ip));
        } else {
            WP_CLI::error(sprintf('Failed to unblock IP %s.', $ip));
        }
    }

    /**
     * Check if an IP is blocked
     *
     * ## OPTIONS
     *
     * <ip>
     * : IP address to check
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec firewall check 1.2.3.4
     *
     * @when after_wp_load
     */
    public function check($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide an IP address.');
        }

        $ip = $args[0];

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            WP_CLI::error('Invalid IP address.');
        }

        if (WordfenceService::isIPBlocked($ip)) {
            WP_CLI::log(WP_CLI::colorize(sprintf('%RIP %s is BLOCKED%n', $ip)));
        } else {
            WP_CLI::log(WP_CLI::colorize(sprintf('%GIP %s is NOT blocked%n', $ip)));
        }
    }

    /**
     * List blocked IPs
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Number of results to show
     * ---
     * default: 50
     * ---
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
     *     $ wp wfsec firewall list
     *     $ wp wfsec firewall list --limit=100 --format=json
     *
     * @when after_wp_load
     * @alias ls
     */
    public function list_($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $limit = (int) ($assoc_args['limit'] ?? 50);
        $blocks = WordfenceService::getBlockedIPs($limit);

        if (empty($blocks)) {
            WP_CLI::log('No blocked IPs found.');
            return;
        }

        $items = array_map(function ($block) {
            return [
                'ID' => $block->id ?? 'N/A',
                'IP' => $block->IP ?? 'N/A',
                'Type' => $block->type ?? 'N/A',
                'Reason' => isset($block->reason) ? substr($block->reason, 0, 40) : 'N/A',
                'Expires' => isset($block->expiration) && $block->expiration > 0
                    ? date('Y-m-d H:i', $block->expiration)
                    : 'Never',
            ];
        }, $blocks);

        $format = $assoc_args['format'] ?? 'table';
        WP_CLI\Utils\format_items($format, $items, ['ID', 'IP', 'Type', 'Reason', 'Expires']);
    }
}
