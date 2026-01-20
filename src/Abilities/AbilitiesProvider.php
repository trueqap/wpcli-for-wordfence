<?php

namespace WordfenceCLI\Abilities;

use WordfenceCLI\Services\WordfenceService;

/**
 * WordPress Abilities API provider for Wordfence
 *
 * Registers security-related abilities for AI agents and REST API access
 */
class AbilitiesProvider
{
    /**
     * Initialize abilities registration
     */
    public static function init(): void
    {
        add_action('wp_abilities_api_categories_init', [self::class, 'registerCategories']);
        add_action('wp_abilities_api_init', [self::class, 'registerAbilities']);
    }

    /**
     * Register ability categories
     */
    public static function registerCategories(): void
    {
        if (!function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category(
            'wordfence-security',
            [
                'label'       => __('Wordfence Security', 'wpcli-for-wordfence'),
                'description' => __('Security scanning, monitoring and protection abilities powered by Wordfence.', 'wpcli-for-wordfence'),
            ]
        );
    }

    /**
     * Register all abilities
     */
    public static function registerAbilities(): void
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        self::registerScanStatusAbility();
        self::registerScanStartAbility();
        self::registerScanStopAbility();
        self::registerIssuesListAbility();
        self::registerIssuesCountAbility();
        self::registerFirewallStatusAbility();
        self::registerLicenseStatusAbility();
    }

    /**
     * Register scan-status ability
     */
    private static function registerScanStatusAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/scan-status',
            [
                'label'       => __('Get Scan Status', 'wpcli-for-wordfence'),
                'description' => __('Returns the current Wordfence security scan status, including whether a scan is running, the current stage, and last scan information.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'running' => [
                            'type'        => 'boolean',
                            'description' => __('Whether a scan is currently running', 'wpcli-for-wordfence'),
                        ],
                        'stage' => [
                            'type'        => 'string',
                            'description' => __('Current scan stage or last completed stage', 'wpcli-for-wordfence'),
                        ],
                        'last_scan' => [
                            'type'        => 'string',
                            'description' => __('Date and time of the last completed scan', 'wpcli-for-wordfence'),
                        ],
                        'last_scan_timestamp' => [
                            'type'        => 'integer',
                            'description' => __('Unix timestamp of the last completed scan', 'wpcli-for-wordfence'),
                        ],
                        'last_scan_duration' => [
                            'type'        => ['integer', 'null'],
                            'description' => __('Duration of the last scan in seconds', 'wpcli-for-wordfence'),
                        ],
                        'last_scan_result' => [
                            'type'        => ['string', 'null'],
                            'description' => __('Result message from the last scan', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeScanStatus'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register scan-start ability
     */
    private static function registerScanStartAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/scan-start',
            [
                'label'       => __('Start Security Scan', 'wpcli-for-wordfence'),
                'description' => __('Initiates a new Wordfence security scan on the WordPress site. Returns success status and any relevant messages.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'scan_type' => [
                            'type'        => 'string',
                            'enum'        => ['quick', 'full'],
                            'default'     => 'quick',
                            'description' => __('Type of scan: quick (faster) or full (comprehensive)', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'success' => [
                            'type'        => 'boolean',
                            'description' => __('Whether the scan was started successfully', 'wpcli-for-wordfence'),
                        ],
                        'message' => [
                            'type'        => 'string',
                            'description' => __('Status message about the scan initiation', 'wpcli-for-wordfence'),
                        ],
                        'triggered' => [
                            'type'        => 'boolean',
                            'description' => __('Whether the scan was triggered immediately', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeScanStart'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register scan-stop ability
     */
    private static function registerScanStopAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/scan-stop',
            [
                'label'       => __('Stop Security Scan', 'wpcli-for-wordfence'),
                'description' => __('Stops a currently running Wordfence security scan.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'success' => [
                            'type'        => 'boolean',
                            'description' => __('Whether the scan was stopped successfully', 'wpcli-for-wordfence'),
                        ],
                        'message' => [
                            'type'        => 'string',
                            'description' => __('Status message', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeScanStop'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register issues-list ability
     */
    private static function registerIssuesListAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/issues-list',
            [
                'label'       => __('List Security Issues', 'wpcli-for-wordfence'),
                'description' => __('Returns a list of security issues found by Wordfence, including malware, vulnerabilities, and other threats.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'status' => [
                            'type'        => 'string',
                            'enum'        => ['new', 'ignoreP', 'ignoreC', 'all'],
                            'default'     => 'new',
                            'description' => __('Filter issues by status: new, ignoreP (permanently ignored), ignoreC (ignored until changed), or all', 'wpcli-for-wordfence'),
                        ],
                        'limit' => [
                            'type'        => 'integer',
                            'default'     => 50,
                            'minimum'     => 1,
                            'maximum'     => 500,
                            'description' => __('Maximum number of issues to return', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'issues' => [
                            'type'        => 'array',
                            'description' => __('List of security issues', 'wpcli-for-wordfence'),
                            'items'       => [
                                'type'       => 'object',
                                'default'    => [],
                                'properties' => [
                                    'id' => [
                                        'type'        => 'integer',
                                        'description' => __('Issue ID', 'wpcli-for-wordfence'),
                                    ],
                                    'type' => [
                                        'type'        => 'string',
                                        'description' => __('Issue type (e.g., file, knownfile, skippedPaths)', 'wpcli-for-wordfence'),
                                    ],
                                    'severity' => [
                                        'type'        => 'integer',
                                        'description' => __('Severity level (1-100, higher is more severe)', 'wpcli-for-wordfence'),
                                    ],
                                    'status' => [
                                        'type'        => 'string',
                                        'description' => __('Issue status', 'wpcli-for-wordfence'),
                                    ],
                                    'short_message' => [
                                        'type'        => 'string',
                                        'description' => __('Short description of the issue', 'wpcli-for-wordfence'),
                                    ],
                                    'long_message' => [
                                        'type'        => 'string',
                                        'description' => __('Detailed description of the issue', 'wpcli-for-wordfence'),
                                    ],
                                    'time_found' => [
                                        'type'        => 'string',
                                        'description' => __('When the issue was first detected', 'wpcli-for-wordfence'),
                                    ],
                                    'data' => [
                                        'type'        => 'object',
                                        'default'     => [],
                                        'description' => __('Additional issue-specific data', 'wpcli-for-wordfence'),
                                    ],
                                ],
                            ],
                        ],
                        'total_count' => [
                            'type'        => 'integer',
                            'description' => __('Total number of issues matching the filter', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeIssuesList'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register issues-count ability
     */
    private static function registerIssuesCountAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/issues-count',
            [
                'label'       => __('Count Security Issues', 'wpcli-for-wordfence'),
                'description' => __('Returns the count of security issues by status.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'new' => [
                            'type'        => 'integer',
                            'description' => __('Number of new issues', 'wpcli-for-wordfence'),
                        ],
                        'ignoreP' => [
                            'type'        => 'integer',
                            'description' => __('Number of permanently ignored issues', 'wpcli-for-wordfence'),
                        ],
                        'ignoreC' => [
                            'type'        => 'integer',
                            'description' => __('Number of issues ignored until changed', 'wpcli-for-wordfence'),
                        ],
                        'total' => [
                            'type'        => 'integer',
                            'description' => __('Total number of issues', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeIssuesCount'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register firewall-status ability
     */
    private static function registerFirewallStatusAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/firewall-status',
            [
                'label'       => __('Get Firewall Status', 'wpcli-for-wordfence'),
                'description' => __('Returns the current Wordfence Web Application Firewall (WAF) status and configuration.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'enabled' => [
                            'type'        => 'boolean',
                            'description' => __('Whether the firewall is enabled', 'wpcli-for-wordfence'),
                        ],
                        'learning_mode' => [
                            'type'        => 'boolean',
                            'description' => __('Whether learning mode is active', 'wpcli-for-wordfence'),
                        ],
                        'waf_status' => [
                            'type'        => 'string',
                            'description' => __('Current WAF status', 'wpcli-for-wordfence'),
                        ],
                        'rules_updated' => [
                            'type'        => 'string',
                            'description' => __('When firewall rules were last updated', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeFirewallStatus'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    /**
     * Register license-status ability
     */
    private static function registerLicenseStatusAbility(): void
    {
        wp_register_ability(
            'wpcli-for-wordfence/license-status',
            [
                'label'       => __('Get License Status', 'wpcli-for-wordfence'),
                'description' => __('Returns the current Wordfence license information and status.', 'wpcli-for-wordfence'),
                'category'    => 'wordfence-security',
                'input_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [],
                ],
                'output_schema' => [
                    'type'       => 'object',
                    'default'    => [],
                    'properties' => [
                        'type' => [
                            'type'        => 'string',
                            'description' => __('License type (free, premium, care, response)', 'wpcli-for-wordfence'),
                        ],
                        'is_premium' => [
                            'type'        => 'boolean',
                            'description' => __('Whether this is a paid/premium license', 'wpcli-for-wordfence'),
                        ],
                        'is_expired' => [
                            'type'        => 'boolean',
                            'description' => __('Whether the license has expired', 'wpcli-for-wordfence'),
                        ],
                        'has_key' => [
                            'type'        => 'boolean',
                            'description' => __('Whether an API key is configured', 'wpcli-for-wordfence'),
                        ],
                    ],
                ],
                'execute_callback'    => [self::class, 'executeLicenseStatus'],
                'permission_callback' => [self::class, 'canManageSecurity'],
                'meta'                => [
                    'show_in_rest' => true,
                ],
            ]
        );
    }

    // =========================================================================
    // Execute Callbacks
    // =========================================================================

    /**
     * Execute scan-status ability
     */
    public static function executeScanStatus(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'running' => false,
                'stage' => 'Wordfence not available',
                'last_scan' => 'N/A',
                'last_scan_timestamp' => 0,
                'last_scan_duration' => null,
                'last_scan_result' => null,
            ];
        }

        return WordfenceService::getScanStatus();
    }

    /**
     * Execute scan-start ability
     */
    public static function executeScanStart(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'success' => false,
                'message' => 'Wordfence not available.',
                'triggered' => false,
            ];
        }

        $scanType = $input['scan_type'] ?? 'quick';
        return WordfenceService::startScan($scanType, true);
    }

    /**
     * Execute scan-stop ability
     */
    public static function executeScanStop(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'success' => false,
                'message' => 'Wordfence not available.',
            ];
        }

        $stopped = WordfenceService::stopScan();
        return [
            'success' => $stopped,
            'message' => $stopped ? 'Scan stop requested.' : 'Failed to stop scan.',
        ];
    }

    /**
     * Execute issues-list ability
     */
    public static function executeIssuesList(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'issues' => [],
                'total_count' => 0,
            ];
        }

        $status = $input['status'] ?? 'new';
        $limit = $input['limit'] ?? 50;

        $allIssues = WordfenceService::getIssues($status);
        $issues = array_slice($allIssues, 0, $limit);

        $formattedIssues = array_map(function ($issue) {
            $data = $issue['data'] ?? [];
            if (is_string($data)) {
                $data = @unserialize($data);
                if (!is_array($data)) {
                    $data = [];
                }
            } elseif (!is_array($data)) {
                $data = [];
            }

            return [
                'id' => (int) ($issue['id'] ?? 0),
                'type' => $issue['type'] ?? 'unknown',
                'severity' => (int) ($issue['severity'] ?? 0),
                'status' => $issue['status'] ?? 'unknown',
                'short_message' => $issue['shortMsg'] ?? '',
                'long_message' => $issue['longMsg'] ?? '',
                'time_found' => isset($issue['time']) ? date('Y-m-d H:i:s', (int) $issue['time']) : '',
                'data' => $data,
            ];
        }, $issues);

        return [
            'issues' => $formattedIssues,
            'total_count' => count($allIssues),
        ];
    }

    /**
     * Execute issues-count ability
     */
    public static function executeIssuesCount(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'new' => 0,
                'ignoreP' => 0,
                'ignoreC' => 0,
                'total' => 0,
            ];
        }

        $new = WordfenceService::getIssueCount('new');
        $ignoreP = WordfenceService::getIssueCount('ignoreP');
        $ignoreC = WordfenceService::getIssueCount('ignoreC');

        return [
            'new' => $new,
            'ignoreP' => $ignoreP,
            'ignoreC' => $ignoreC,
            'total' => $new + $ignoreP + $ignoreC,
        ];
    }

    /**
     * Execute firewall-status ability
     */
    public static function executeFirewallStatus(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'enabled' => false,
                'learning_mode' => false,
                'waf_status' => 'Wordfence not available',
                'rules_updated' => 'N/A',
            ];
        }

        return WordfenceService::getFirewallStatus();
    }

    /**
     * Execute license-status ability
     */
    public static function executeLicenseStatus(array $input = []): array
    {
        if (!WordfenceService::isAvailable()) {
            return [
                'type' => 'unknown',
                'is_premium' => false,
                'is_expired' => false,
                'has_key' => false,
            ];
        }

        $info = WordfenceService::getLicenseInfo();

        return [
            'type' => $info['type'] ?? 'unknown',
            'is_premium' => $info['is_premium'] ?? false,
            'is_expired' => $info['is_expired'] ?? false,
            'has_key' => $info['has_key'] ?? false,
        ];
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    /**
     * Check if current user can manage security settings
     */
    public static function canManageSecurity(): bool
    {
        return current_user_can('manage_options');
    }
}
