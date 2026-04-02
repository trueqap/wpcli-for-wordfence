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
        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'json') {
            WP_CLI::log(json_encode($status, JSON_PRETTY_PRINT));
            return;
        }

        $stageText = $status['stage'] ?: 'Idle';
        if (!empty($status['stage_progress'])) {
            $stageText .= ' — ' . $status['stage_progress'];
        }

        $items = [
            ['Property' => 'Running',  'Value' => $status['running'] ? 'Yes' : 'No'],
            ['Property' => 'Stage',    'Value' => $stageText],
            ['Property' => 'Progress', 'Value' => $status['stages_total'] > 0
                ? sprintf('%d/%d stages (%d%%)', $status['stages_complete'], $status['stages_total'], $status['overall_progress'])
                : 'N/A'],
            ['Property' => 'Last Scan', 'Value' => $status['last_scan']],
        ];

        if (!empty($status['last_scan_duration'])) {
            $items[] = ['Property' => 'Duration', 'Value' => $status['last_scan_duration'] . ' seconds'];
        }

        // Summary counters
        $summary = $status['summary'] ?? [];
        if (!empty($summary)) {
            $counterParts = [];
            foreach (['scannedFiles' => 'files', 'scannedPlugins' => 'plugins', 'scannedThemes' => 'themes', 'scannedPosts' => 'posts', 'scannedComments' => 'comments', 'scannedURLs' => 'URLs'] as $key => $label) {
                if (!empty($summary[$key])) {
                    $counterParts[] = number_format($summary[$key]) . ' ' . $label;
                }
            }
            if (!empty($counterParts)) {
                $items[] = ['Property' => 'Scanned', 'Value' => implode(', ', $counterParts)];
            }
        }

        WP_CLI\Utils\format_items($format, $items, ['Property', 'Value']);

        // Show stage pipeline if scan is running
        if ($status['running'] && !empty($status['stage_details'])) {
            WP_CLI::log('');
            WP_CLI::log(self::formatStagePipeline($status['stage_details']));
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
     * Displays per-stage progress, live summary counters, and a real-time
     * activity log — the same information shown in the Wordfence admin panel.
     *
     * ## OPTIONS
     *
     * [--interval=<seconds>]
     * : Refresh interval in seconds
     * ---
     * default: 5
     * ---
     *
     * [--verbose]
     * : Show real-time activity log messages from the wfStatus table
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec scan watch
     *     $ wp wfsec scan watch --interval=3
     *     $ wp wfsec scan watch --verbose
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
        $verbose = isset($assoc_args['verbose']);

        WP_CLI::log('Watching scan progress... (Press Ctrl+C to stop)');
        WP_CLI::log('');

        $lastStage = '';
        $startTime = time();
        $lastCtime = (float) (time() - 5); // Start from a few seconds ago

        while (true) {
            $status = WordfenceService::getScanStatus();
            $elapsed = time() - $startTime;

            // --- Header line: stage + progress ---
            $stageText = $status['stage'] ?: 'N/A';
            if (!empty($status['stage_progress'])) {
                $stageText .= ' ' . $status['stage_progress'];
            }

            $overallText = '';
            if ($status['stages_total'] > 0) {
                $overallText = sprintf(
                    ' | Overall: %d/%d stages (%d%%)',
                    $status['stages_complete'],
                    $status['stages_total'],
                    $status['overall_progress']
                );
            }

            $headerLine = sprintf(
                '[%s] %s | Stage: %s%s | Elapsed: %s',
                date('H:i:s'),
                $status['running'] ? WP_CLI::colorize('%GRunning%n') : WP_CLI::colorize('%RNot running%n'),
                $stageText,
                $overallText,
                gmdate('H:i:s', $elapsed)
            );
            WP_CLI::log($headerLine);

            // --- Stage pipeline (visual progress bar) ---
            if (!empty($status['stage_details'])) {
                $pipeline = self::formatStagePipeline($status['stage_details']);
                WP_CLI::log('  ' . $pipeline);
            }

            // --- Summary counters ---
            $summary = $status['summary'] ?? [];
            if (!empty($summary)) {
                $counters = [];
                if (!empty($summary['scannedFiles'])) {
                    $counters[] = 'Files: ' . number_format($summary['scannedFiles']);
                }
                if (!empty($summary['scannedPlugins'])) {
                    $counters[] = 'Plugins: ' . $summary['scannedPlugins'];
                }
                if (!empty($summary['scannedThemes'])) {
                    $counters[] = 'Themes: ' . $summary['scannedThemes'];
                }
                if (!empty($summary['scannedPosts'])) {
                    $counters[] = 'Posts: ' . number_format($summary['scannedPosts']);
                }
                if (!empty($summary['scannedComments'])) {
                    $counters[] = 'Comments: ' . number_format($summary['scannedComments']);
                }
                if (!empty($summary['scannedURLs'])) {
                    $counters[] = 'URLs: ' . number_format($summary['scannedURLs']);
                }
                if (!empty($counters)) {
                    WP_CLI::log('  ' . WP_CLI::colorize('%c' . implode(' | ', $counters) . '%n'));
                }
            }

            // --- Real-time log stream (verbose mode) ---
            if ($verbose) {
                $logData = WordfenceService::getStatusSince($lastCtime);
                $lastCtime = $logData['last_ctime'];
                foreach ($logData['entries'] as $entry) {
                    $msg = $entry['message'];
                    // Color SUM_ messages for visibility
                    if (strpos($msg, 'SUM_') === 0) {
                        $msg = WP_CLI::colorize('%y' . $msg . '%n');
                    }
                    WP_CLI::log(sprintf('    [%s] %s', $entry['time'], $msg));
                }
            }

            // --- Stage change notification ---
            if ($status['stage'] !== $lastStage && !empty($status['stage']) && $status['stage'] !== 'N/A') {
                WP_CLI::log(WP_CLI::colorize(sprintf('  %G▶ Stage changed to: %s%n', $status['stage'])));
                $lastStage = $status['stage'];
            }

            // --- Scan finished ---
            if (!$status['running']) {
                WP_CLI::log('');
                WP_CLI::log(WP_CLI::colorize('%YScan complete.%n'));
                WP_CLI::log(sprintf('Last scan: %s', $status['last_scan']));

                if (!empty($status['last_scan_duration'])) {
                    WP_CLI::log(sprintf('Duration: %d seconds', $status['last_scan_duration']));
                }

                $issues = WordfenceService::getIssues('new');
                if (count($issues) > 0) {
                    WP_CLI::log(WP_CLI::colorize(sprintf('%RIssues found: %d%n', count($issues))));
                    WP_CLI::log('Use "wp wfsec issues list" to view.');
                } else {
                    WP_CLI::log(WP_CLI::colorize('%GNo issues found.%n'));
                }
                break;
            }

            sleep($interval);
        }
    }

    /**
     * Format the stage pipeline as a single-line visual indicator.
     *
     * Example output:
     *   ✓ Server State  ✓ File Changes  ▶ Malware Scan [67%]  ○ Content Safety  ○ Password Strength
     */
    private static function formatStagePipeline(array $stageDetails): string
    {
        $parts = [];
        foreach ($stageDetails as $data) {
            $name = $data['name'];
            $status = $data['status'];
            $progress = $data['progress'] ?? 0;

            if (in_array($status, ['complete-success', 'complete-warning'], true)) {
                $icon = $status === 'complete-warning'
                    ? WP_CLI::colorize('%y⚠%n')
                    : WP_CLI::colorize('%g✓%n');
                $parts[] = $icon . ' ' . $name;
            } elseif (in_array($status, ['running', 'running-warning'], true)) {
                $pctText = $progress > 0 ? " [{$progress}%]" : '';
                $parts[] = WP_CLI::colorize('%B▶ ' . $name . $pctText . '%n');
            } else {
                // pending
                $parts[] = WP_CLI::colorize('%w○ ' . $name . '%n');
            }
        }

        return implode('  ', $parts);
    }
}
