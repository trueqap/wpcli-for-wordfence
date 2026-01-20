<?php

namespace WordfenceCLI\Services;

/**
 * Service class to interact with Wordfence internals
 */
class WordfenceService
{
    /**
     * Check if Wordfence is available
     */
    public static function isAvailable(): bool
    {
        return class_exists('wordfence') && class_exists('wfConfig');
    }

    /**
     * Get Wordfence configuration value
     */
    public static function getConfig(string $key, $default = null)
    {
        if (!class_exists('wfConfig')) {
            return $default;
        }
        return \wfConfig::get($key, $default);
    }

    /**
     * Set Wordfence configuration value
     */
    public static function setConfig(string $key, $value): bool
    {
        if (!class_exists('wfConfig')) {
            return false;
        }
        \wfConfig::set($key, $value);
        return true;
    }

    /**
     * Get all issues
     */
    public static function getIssues(string $status = 'new'): array
    {
        if (!class_exists('wfIssues')) {
            return [];
        }

        $wfIssues = new \wfIssues();
        $result = $wfIssues->getIssues($status === 'all' ? null : $status);

        // wfIssues::getIssues() returns ['status' => [...issues]]
        // Flatten to just the issues array
        $issues = [];
        if (is_array($result)) {
            foreach ($result as $statusKey => $statusIssues) {
                if (is_array($statusIssues)) {
                    $issues = array_merge($issues, $statusIssues);
                }
            }
        }

        return $issues;
    }

    /**
     * Get issue count by status
     */
    public static function getIssueCount(string $status = 'new'): int
    {
        if (!class_exists('wfIssues')) {
            return 0;
        }

        $issues = new \wfIssues();
        $counts = $issues->getIssueCounts();

        if ($status === 'all') {
            return array_sum($counts);
        }

        return $counts[$status] ?? 0;
    }

    /**
     * Delete an issue
     */
    public static function deleteIssue(int $issueId): bool
    {
        if (!class_exists('wfIssues')) {
            return false;
        }

        $issues = new \wfIssues();
        return $issues->deleteIssue($issueId);
    }

    /**
     * Ignore an issue
     */
    public static function ignoreIssue(int $issueId): bool
    {
        if (!class_exists('wfIssues')) {
            return false;
        }

        $issues = new \wfIssues();
        return $issues->updateIssue($issueId, 'ignoreP');
    }

    /**
     * Start a scan
     *
     * @param string $scanType 'quick' or 'full'
     * @param bool $async If true, trigger via HTTP loopback for actual execution
     * @return array ['success' => bool, 'message' => string]
     */
    public static function startScan(string $scanType = 'quick', bool $async = true): array
    {
        if (!class_exists('wfConfig')) {
            return ['success' => false, 'message' => 'Wordfence not available.'];
        }

        // Check if scan is already running
        $scanRunning = (int) \wfConfig::get('wf_scanRunning', 0);
        if ($scanRunning && (time() - $scanRunning < 86400)) {
            return ['success' => false, 'message' => 'A scan is already running. Use "wp wordfence scan stop" first.'];
        }

        // Set scan type via config
        if ($scanType === 'full') {
            self::setConfig('scanType', 'manual');
        } else {
            self::setConfig('scanType', 'quick');
        }

        // Try direct wordfence::startScan() method first
        if (class_exists('wordfence') && method_exists('wordfence', 'startScan')) {
            try {
                \wordfence::startScan();
                return [
                    'success' => true,
                    'message' => 'Scan started.',
                    'triggered' => true,
                ];
            } catch (\Exception $e) {
                // Fall through to cron-based approach
            }
        }

        // Fallback: Schedule via WP Cron
        self::setConfig('lastScheduledScanStart', 0);
        self::setConfig('scheduledScansEnabled', 1);

        $scheduledStartTime = time();
        wp_clear_scheduled_hook('wordfence_start_scheduled_scan');
        $scheduled = wp_schedule_single_event($scheduledStartTime, 'wordfence_start_scheduled_scan', [$scheduledStartTime]);

        if ($scheduled === false) {
            return ['success' => false, 'message' => 'Failed to schedule scan.'];
        }

        // Trigger via HTTP loopback if async mode
        if ($async) {
            self::triggerCronViaHttp();
        }

        return [
            'success' => true,
            'message' => 'Scan scheduled.',
            'triggered' => false,
        ];
    }

    /**
     * Trigger WP Cron via HTTP loopback
     * This is necessary because Wordfence scan requires HTTP context
     */
    public static function triggerCronViaHttp(): bool
    {
        $cronUrl = site_url('wp-cron.php');

        // Use non-blocking request to trigger cron
        $args = [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
            'headers'   => [
                'Cache-Control' => 'no-cache',
            ],
        ];

        $response = wp_remote_post($cronUrl, $args);

        // Since it's non-blocking, we can't really check the result
        // But we return true to indicate we attempted
        return true;
    }

    /**
     * Start scan synchronously via direct Wordfence call
     * Note: This may not work in CLI context due to Wordfence's HTTP-based architecture
     */
    public static function startScanDirect(string $scanType = 'quick'): array
    {
        if (!class_exists('wfScanEngine')) {
            return ['success' => false, 'message' => 'Wordfence scan engine not available.'];
        }

        // Check if scan is already running
        $scanRunning = \wfConfig::get('wf_scanRunning', 0);
        if ($scanRunning && (time() - $scanRunning < 86400)) {
            return ['success' => false, 'message' => 'A scan is already running.'];
        }

        try {
            // Set scan type
            if ($scanType === 'full') {
                self::setConfig('scanType', 'manual');
            } else {
                self::setConfig('scanType', 'quick');
            }

            // Try to use Wordfence's scan controller
            if (class_exists('wfScanController') && method_exists('wfScanController', 'startScan')) {
                \wfScanController::startScan();
                return ['success' => true, 'message' => 'Scan started directly.'];
            }

            // Fallback: try scan engine
            $engine = new \wfScanEngine();
            $engine->go();
            return ['success' => true, 'message' => 'Scan started via engine.'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to start scan: ' . $e->getMessage()];
        }
    }

    /**
     * Get scan status
     */
    public static function getScanStatus(): array
    {
        if (!class_exists('wfConfig')) {
            return ['running' => false, 'stage' => 'unknown'];
        }

        // Check if scan is running via config
        $scanRunning = (int) \wfConfig::get('wf_scanRunning', 0);
        $running = $scanRunning && (time() - $scanRunning < 86400);

        $stage = \wfConfig::get('wf_scanStage', '') ?: \wfConfig::get('scanStage', '');

        // Get last scan info from wfStatus table
        $lastScanInfo = self::getLastScanInfo();
        $lastScan = $lastScanInfo['timestamp'] ?? 0;

        return [
            'running' => (bool) $running,
            'stage' => $stage ?: ($lastScanInfo['stage'] ?? 'N/A'),
            'last_scan' => $lastScan ? date('Y-m-d H:i:s', $lastScan) : 'Never',
            'last_scan_timestamp' => $lastScan,
            'last_scan_duration' => $lastScanInfo['duration'] ?? null,
            'last_scan_result' => $lastScanInfo['result'] ?? null,
        ];
    }

    /**
     * Get last scan info from wfStatus table
     */
    public static function getLastScanInfo(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wfstatus';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        // Get latest scan completion message
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ctime, msg FROM {$table} WHERE msg LIKE %s OR msg LIKE %s OR msg LIKE %s ORDER BY ctime DESC LIMIT 1",
                '%vizsgálat befejez%',
                '%scan complete%',
                'SUM_FINAL%'
            ),
            ARRAY_A
        );

        if (!$result) {
            return [];
        }

        // Get duration from message
        $duration = null;
        if (preg_match('/(\d+)\s*(másodperc|second)/i', $result['msg'], $matches)) {
            $duration = (int) $matches[1];
        }

        // Get current stage if scan is running
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $stage = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT msg FROM {$table} WHERE msg LIKE %s ORDER BY ctime DESC LIMIT 1",
                'SUM_START%'
            )
        );

        return [
            'timestamp' => (int) floor((float) $result['ctime']),
            'duration' => $duration,
            'result' => $result['msg'],
            'stage' => $stage ? preg_replace('/^SUM_START:/', '', $stage) : null,
        ];
    }

    /**
     * Get recent scan log entries
     */
    public static function getScanLog(int $limit = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wfstatus';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ctime, level, type, msg FROM {$table} ORDER BY ctime DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return array_map(function ($row) {
            return [
                'time' => date('Y-m-d H:i:s', (int) floor((float) $row['ctime'])),
                'level' => $row['level'],
                'type' => $row['type'],
                'message' => $row['msg'],
            ];
        }, $results ?: []);
    }

    /**
     * Stop running scan
     */
    public static function stopScan(): bool
    {
        if (!class_exists('wfConfig')) {
            return false;
        }

        // Clear the scan lock
        \wfConfig::set('wf_scanRunning', '');
        \wfConfig::set('wf_scanStage', '');
        \wfConfig::set('wfKillRequested', 1);

        // Also use wfUtils if available
        if (class_exists('wfUtils') && method_exists('wfUtils', 'clearScanLock')) {
            \wfUtils::clearScanLock();
        }

        return true;
    }

    /**
     * Get blocked IPs
     */
    public static function getBlockedIPs(int $limit = 100): array
    {
        global $wpdb;

        if (!class_exists('wfBlock')) {
            return [];
        }

        $blocks = \wfBlock::getAllBlocks($limit);
        return $blocks ?: [];
    }

    /**
     * Block an IP
     */
    public static function blockIP(string $ip, string $reason = 'Blocked via CLI', int $duration = 0): bool
    {
        if (!class_exists('wfBlock')) {
            return false;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $expiration = $duration > 0 ? time() + $duration : 0;

        \wfBlock::createIP(
            $reason,
            $ip,
            $expiration,
            time(),
            false,
            \wfBlock::TYPE_IP_MANUAL
        );

        return true;
    }

    /**
     * Unblock an IP
     */
    public static function unblockIP(string $ip): bool
    {
        if (!class_exists('wfBlock')) {
            return false;
        }

        \wfBlock::unblockIP($ip);
        return true;
    }

    /**
     * Check if IP is blocked
     */
    public static function isIPBlocked(string $ip): bool
    {
        if (!class_exists('wfBlock')) {
            return false;
        }

        return \wfBlock::isWhitelisted($ip) === false && \wfBlock::isBlocked($ip);
    }

    /**
     * Get firewall status
     */
    public static function getFirewallStatus(): array
    {
        if (!class_exists('wfConfig')) {
            return [];
        }

        return [
            'enabled' => (bool) \wfConfig::get('firewallEnabled', true),
            'learning_mode' => (bool) \wfConfig::get('learningModeEnabled', false),
            'waf_status' => \wfConfig::get('wafStatus', 'unknown'),
            'rules_updated' => \wfConfig::get('wafRulesLastUpdated', 0)
                ? date('Y-m-d H:i:s', \wfConfig::get('wafRulesLastUpdated'))
                : 'Never',
        ];
    }

    /**
     * Enable/disable firewall
     */
    public static function setFirewallEnabled(bool $enabled): bool
    {
        if (!class_exists('wfConfig')) {
            return false;
        }

        \wfConfig::set('firewallEnabled', $enabled ? 1 : 0);
        return true;
    }

    /**
     * Get license info
     */
    public static function getLicenseInfo(): array
    {
        if (!class_exists('wfLicense')) {
            return [];
        }

        $license = \wfLicense::current();

        // Get API key from config (getKey() method may not exist in all versions)
        $apiKey = self::getConfig('apiKey', '');

        return [
            'type' => method_exists($license, 'getType') ? $license->getType() : 'unknown',
            'is_premium' => method_exists($license, 'isPaid') ? $license->isPaid() : false,
            'is_expired' => method_exists($license, 'isExpired') ? $license->isExpired() : false,
            'key' => $apiKey ? substr($apiKey, 0, 8) . '...' : 'None',
            'has_key' => !empty($apiKey),
        ];
    }

    /**
     * Check if license is active/valid
     */
    public static function isLicenseActive(): bool
    {
        $apiKey = self::getConfig('apiKey', '');
        return !empty($apiKey);
    }

    /**
     * Activate license with API key
     */
    public static function activateLicense(string $apiKey): array
    {
        if (!class_exists('wfLicense')) {
            return ['success' => false, 'message' => 'Wordfence license class not available.'];
        }

        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'API key cannot be empty.'];
        }

        // Clean the key
        $apiKey = trim($apiKey);

        // Validate key format (Wordfence keys are typically alphanumeric)
        if (!preg_match('/^[A-Za-z0-9]+$/', $apiKey)) {
            return ['success' => false, 'message' => 'Invalid API key format.'];
        }

        try {
            // Use Wordfence's API to verify and activate the key
            if (class_exists('wfAPI')) {
                $api = new \wfAPI($apiKey, \wfUtils::getWPVersion());

                // Test the key by making an API call
                $keyData = $api->call('check_api_key', [], ['apiKey' => $apiKey]);

                if (isset($keyData['ok']) && $keyData['ok']) {
                    // Key is valid, save it
                    self::setConfig('apiKey', $apiKey);
                    self::setConfig('isPaid', isset($keyData['isPaid']) ? $keyData['isPaid'] : 0);

                    // Clear any cached license data
                    if (method_exists('wfLicense', 'clearCachedLicense')) {
                        \wfLicense::clearCachedLicense();
                    }

                    return [
                        'success' => true,
                        'message' => 'License activated successfully.',
                        'type' => isset($keyData['isPaid']) && $keyData['isPaid'] ? 'Premium' : 'Free',
                    ];
                } else {
                    $errorMsg = isset($keyData['errorMsg']) ? $keyData['errorMsg'] : 'Invalid API key.';
                    return ['success' => false, 'message' => $errorMsg];
                }
            } else {
                // Fallback: just save the key without verification
                self::setConfig('apiKey', $apiKey);
                return [
                    'success' => true,
                    'message' => 'API key saved (could not verify with Wordfence API).',
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Activation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Deactivate/remove license
     */
    public static function deactivateLicense(): bool
    {
        self::setConfig('apiKey', '');
        self::setConfig('isPaid', 0);

        if (method_exists('wfLicense', 'clearCachedLicense')) {
            \wfLicense::clearCachedLicense();
        }

        return true;
    }

    /**
     * Get live traffic entries
     */
    public static function getLiveTraffic(int $limit = 50): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wfHits';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY ctime DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Export settings
     */
    public static function exportSettings(): string
    {
        if (!class_exists('wfImportExportController')) {
            return '';
        }

        return \wfImportExportController::export();
    }

    /**
     * Import settings
     */
    public static function importSettings(string $data): bool
    {
        if (!class_exists('wfImportExportController')) {
            return false;
        }

        try {
            \wfImportExportController::import($data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
