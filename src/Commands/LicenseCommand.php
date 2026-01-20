<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Manage Wordfence license
 *
 * ## EXAMPLES
 *
 *     # Show license info
 *     $ wp wfsec license status
 *
 *     # Activate license
 *     $ wp wfsec license activate YOUR_API_KEY
 *
 *     # Deactivate license
 *     $ wp wfsec license deactivate
 */
class LicenseCommand
{
    /**
     * Show license status
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
     *     $ wp wfsec license status
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $info = WordfenceService::getLicenseInfo();
        $format = $assoc_args['format'] ?? 'table';

        // Check if license is active
        if (empty($info['has_key'])) {
            WP_CLI::warning('Wordfence license is NOT activated!');
            WP_CLI::log('');
            WP_CLI::log('To activate your license, run:');
            WP_CLI::log(WP_CLI::colorize('  %Gwp wfsec license activate YOUR_API_KEY%n'));
            WP_CLI::log('');
            WP_CLI::log('You can get your free API key at:');
            WP_CLI::log('  https://www.wordfence.com/');
            WP_CLI::log('');
            return;
        }

        $items = [
            [
                'Property' => 'Status',
                'Value' => WP_CLI::colorize('%GActive%n'),
            ],
            [
                'Property' => 'Type',
                'Value' => $info['type'] ?? 'Unknown',
            ],
            [
                'Property' => 'Premium',
                'Value' => $info['is_premium'] ? 'Yes' : 'No',
            ],
            [
                'Property' => 'Expired',
                'Value' => $info['is_expired'] ? 'Yes' : 'No',
            ],
            [
                'Property' => 'Key',
                'Value' => $info['key'] ?? 'None',
            ],
        ];

        if ($format === 'json') {
            WP_CLI::log(json_encode([
                'status' => 'active',
                'type' => $info['type'] ?? 'Unknown',
                'is_premium' => $info['is_premium'],
                'is_expired' => $info['is_expired'],
                'key' => $info['key'],
            ], JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items($format, $items, ['Property', 'Value']);
        }
    }

    /**
     * Activate Wordfence license
     *
     * ## OPTIONS
     *
     * <api_key>
     * : Your Wordfence API key
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec license activate YOUR_API_KEY
     *
     * @when after_wp_load
     */
    public function activate($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide your Wordfence API key.');
        }

        $apiKey = $args[0];

        WP_CLI::log('Activating Wordfence license...');

        $result = WordfenceService::activateLicense($apiKey);

        if ($result['success']) {
            WP_CLI::success($result['message']);
            if (isset($result['type'])) {
                WP_CLI::log(sprintf('License type: %s', $result['type']));
            }
        } else {
            WP_CLI::error($result['message']);
        }
    }

    /**
     * Deactivate Wordfence license
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec license deactivate
     *
     * @when after_wp_load
     */
    public function deactivate($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        WP_CLI::confirm('Are you sure you want to deactivate your Wordfence license?', $assoc_args);

        if (WordfenceService::deactivateLicense()) {
            WP_CLI::success('Wordfence license deactivated.');
        } else {
            WP_CLI::error('Failed to deactivate license.');
        }
    }

    /**
     * Check if license is active (for scripting)
     *
     * Returns exit code 0 if active, 1 if not.
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec license check && echo "License is active"
     *
     * @when after_wp_load
     */
    public function check($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (WordfenceService::isLicenseActive()) {
            WP_CLI::log('License is active.');
            return;
        } else {
            WP_CLI::error('License is NOT active. Run: wp wfsec license activate YOUR_API_KEY');
        }
    }
}
