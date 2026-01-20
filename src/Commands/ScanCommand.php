<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Manage Wordfence scans
 *
 * ## EXAMPLES
 *
 *     # Start a quick scan
 *     $ wp wfsec scan start
 *
 *     # Start a full scan
 *     $ wp wfsec scan start --type=full
 *
 *     # Check scan status
 *     $ wp wfsec scan status
 *
 *     # Stop running scan
 *     $ wp wfsec scan stop
 */
class ScanCommand
{
    /**
     * Start a Wordfence scan
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Scan type (quick or full)
     * ---
     * default: quick
     * options:
     *   - quick
     *   - full
     * ---
     *
     * [--no-trigger]
     * : Only schedule, do not trigger via HTTP
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan start
     *     $ wp wfsec scan start --type=full
     *
     * @when after_wp_load
     */
    public function start($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $type = $assoc_args['type'] ?? 'quick';
        $async = !isset($assoc_args['no-trigger']);

        WP_CLI::log(sprintf('Starting %s scan...', $type));

        $result = WordfenceService::startScan($type, $async);

        if ($result['success']) {
            if (!empty($result['triggered'])) {
                WP_CLI::success(sprintf('%s scan started.', ucfirst($type)));
                WP_CLI::log('');
                WP_CLI::log('The scan is now running in the background.');
                WP_CLI::log('Check status with: wp wfsec scan status');
                WP_CLI::log('');
                WP_CLI::log(WP_CLI::colorize('%YNote:%n Wordfence scans run via HTTP callbacks.'));
                WP_CLI::log('Progress can be monitored in the Wordfence admin panel.');
            } else {
                WP_CLI::success(sprintf('%s scan scheduled.', ucfirst($type)));
                WP_CLI::log('');
                WP_CLI::log('The scan will run on next page load or cron execution.');
                WP_CLI::log('To trigger immediately:');
                WP_CLI::log(WP_CLI::colorize('  %Gwp cron event run --due-now%n'));
                WP_CLI::log('');
                WP_CLI::log('Check status with: wp wfsec scan status');
            }
        } else {
            WP_CLI::error($result['message']);
        }
    }

    /**
     * Get current scan status
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
     *     $ wp wfsec scan status
     *     $ wp wfsec scan status --format=json
     *
     * @when after_wp_load
     */
    public function status($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $status = WordfenceService::getScanStatus();

        $items = [
            [
                'Property' => 'Running',
                'Value' => $status['running'] ? 'Yes' : 'No',
            ],
            [
                'Property' => 'Stage',
                'Value' => $status['stage'] ?: 'Idle',
            ],
            [
                'Property' => 'Last Scan',
                'Value' => $status['last_scan'],
            ],
        ];

        // Add duration if available
        if (!empty($status['last_scan_duration'])) {
            $items[] = [
                'Property' => 'Duration',
                'Value' => $status['last_scan_duration'] . ' seconds',
            ];
        }

        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'json') {
            WP_CLI::log(json_encode($status, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items($format, $items, ['Property', 'Value']);
        }
    }

    /**
     * Show recent scan log entries
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Number of log entries to show
     * ---
     * default: 20
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
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan log
     *     $ wp wfsec scan log --limit=50
     *
     * @when after_wp_load
     */
    public function log($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $limit = (int) ($assoc_args['limit'] ?? 20);
        $format = $assoc_args['format'] ?? 'table';

        $logs = WordfenceService::getScanLog($limit);

        if (empty($logs)) {
            WP_CLI::log('No scan log entries found.');
            return;
        }

        if ($format === 'json') {
            WP_CLI::log(json_encode($logs, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items($format, $logs, ['time', 'level', 'type', 'message']);
        }
    }

    /**
     * Stop a running scan
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan stop
     *
     * @when after_wp_load
     */
    public function stop($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $status = WordfenceService::getScanStatus();

        if (!$status['running']) {
            WP_CLI::warning('No scan is currently running.');
            return;
        }

        if (WordfenceService::stopScan()) {
            WP_CLI::success('Scan stopped.');
        } else {
            WP_CLI::error('Failed to stop scan.');
        }
    }

    /**
     * Show scan history / last scan results
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan results
     *
     * @when after_wp_load
     * @alias results
     */
    public function history($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $status = WordfenceService::getScanStatus();
        $issues = WordfenceService::getIssues('new');

        WP_CLI::log('');
        WP_CLI::log(WP_CLI::colorize('%BLast Scan Results%n'));
        WP_CLI::log(str_repeat('-', 40));
        WP_CLI::log(sprintf('Last Scan: %s', $status['last_scan']));
        WP_CLI::log(sprintf('Issues Found: %d', count($issues)));
        WP_CLI::log('');

        if (count($issues) > 0) {
            WP_CLI::log('Use "wp wfsec issues list" to see all issues.');
        }
    }

    /**
     * Watch scan progress in real-time
     *
     * ## OPTIONS
     *
     * [--interval=<seconds>]
     * : Refresh interval in seconds
     * ---
     * default: 5
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan watch
     *     $ wp wfsec scan watch --interval=10
     *
     * @when after_wp_load
     */
    public function watch($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $interval = (int) ($assoc_args['interval'] ?? 5);
        if ($interval < 1) {
            $interval = 5;
        }

        WP_CLI::log('Watching scan status... (Press Ctrl+C to stop)');
        WP_CLI::log('');

        $lastStage = '';
        $startTime = time();

        while (true) {
            $status = WordfenceService::getScanStatus();
            $elapsed = time() - $startTime;

            // Clear line and print status
            $line = sprintf(
                "\r[%s] Running: %s | Stage: %s | Elapsed: %s",
                date('H:i:s'),
                $status['running'] ? WP_CLI::colorize('%GYes%n') : WP_CLI::colorize('%RNo%n'),
                $status['stage'] ?: 'N/A',
                gmdate('H:i:s', $elapsed)
            );

            // Pad line to clear any previous longer output
            WP_CLI::log(str_pad($line, 100));

            // If stage changed, log it
            if ($status['stage'] !== $lastStage && !empty($status['stage'])) {
                WP_CLI::log(sprintf('  -> Stage changed to: %s', $status['stage']));
                $lastStage = $status['stage'];
            }

            // If scan stopped, show final message
            if (!$status['running']) {
                WP_CLI::log('');
                WP_CLI::log(WP_CLI::colorize('%YScan is not running.%n'));
                WP_CLI::log(sprintf('Last scan: %s', $status['last_scan']));

                $issues = WordfenceService::getIssues('new');
                if (count($issues) > 0) {
                    WP_CLI::log(sprintf('Issues found: %d', count($issues)));
                    WP_CLI::log('Use "wp wfsec issues list" to view.');
                }
                break;
            }

            sleep($interval);
        }
    }
}
