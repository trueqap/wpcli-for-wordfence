<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Main Wordfence CLI command
 *
 * ## EXAMPLES
 *
 *     # Show Wordfence status
 *     $ wp wfsec status
 *
 *     # Activate license
 *     $ wp wfsec license activate YOUR_API_KEY
 */
class MainCommand
{
    /**
     * Show Wordfence overall status
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $scanStatus = WordfenceService::getScanStatus();
        $firewallStatus = WordfenceService::getFirewallStatus();
        $licenseInfo = WordfenceService::getLicenseInfo();
        $issueCount = WordfenceService::getIssueCount('new');

        WP_CLI::log('');
        WP_CLI::log(WP_CLI::colorize('%BWordfence Status%n'));
        WP_CLI::log(str_repeat('-', 40));

        // License - show warning if not active
        if (empty($licenseInfo['has_key'])) {
            WP_CLI::log(sprintf(
                'License: %s',
                WP_CLI::colorize('%RNOT ACTIVATED%n')
            ));
            WP_CLI::log(WP_CLI::colorize('  %YRun: wp wfsec license activate YOUR_API_KEY%n'));
        } else {
            WP_CLI::log(sprintf(
                'License: %s %s',
                $licenseInfo['type'] ?? 'Unknown',
                $licenseInfo['is_premium'] ? WP_CLI::colorize('%G(Premium)%n') : ''
            ));
        }

        // Firewall
        $fwStatus = $firewallStatus['enabled']
            ? WP_CLI::colorize('%GEnabled%n')
            : WP_CLI::colorize('%RDisabled%n');
        WP_CLI::log(sprintf('Firewall: %s', $fwStatus));

        if ($firewallStatus['learning_mode']) {
            WP_CLI::log(WP_CLI::colorize('  %YLearning Mode Active%n'));
        }

        // Scan
        $scanRunning = $scanStatus['running']
            ? WP_CLI::colorize('%YRunning%n') . ' (' . $scanStatus['stage'] . ')'
            : WP_CLI::colorize('%GIdle%n');
        WP_CLI::log(sprintf('Scan: %s', $scanRunning));
        WP_CLI::log(sprintf('Last Scan: %s', $scanStatus['last_scan']));

        // Issues
        $issueColor = $issueCount > 0 ? '%R' : '%G';
        WP_CLI::log(sprintf(
            'New Issues: %s',
            WP_CLI::colorize($issueColor . $issueCount . '%n')
        ));

        WP_CLI::log('');

        // Show activation reminder at the end if not active
        if (empty($licenseInfo['has_key'])) {
            WP_CLI::warning('Wordfence license is not activated. Some features may be limited.');
        }
    }

    /**
     * Export Wordfence settings to a file
     *
     * ## OPTIONS
     *
     * [<file>]
     * : Output file path (default: stdout)
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec export > settings.txt
     *     $ wp wfsec export /path/to/settings.txt
     *
     * @when after_wp_load
     */
    public function export($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $data = WordfenceService::exportSettings();

        if (empty($data)) {
            WP_CLI::error('Failed to export settings.');
        }

        if (!empty($args[0])) {
            file_put_contents($args[0], $data);
            WP_CLI::success(sprintf('Settings exported to %s', $args[0]));
        } else {
            echo $data;
        }
    }

    /**
     * Import Wordfence settings from a file
     *
     * ## OPTIONS
     *
     * <file>
     * : Input file path
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec import /path/to/settings.txt
     *
     * @when after_wp_load
     */
    public function import($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0]) || !file_exists($args[0])) {
            WP_CLI::error('Please provide a valid file path.');
        }

        $data = file_get_contents($args[0]);

        if (WordfenceService::importSettings($data)) {
            WP_CLI::success('Settings imported successfully.');
        } else {
            WP_CLI::error('Failed to import settings.');
        }
    }
}
