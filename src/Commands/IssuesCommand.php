<?php

namespace WordfenceCLI\Commands;

use WP_CLI;
use WordfenceCLI\Services\WordfenceService;

/**
 * Manage Wordfence issues
 *
 * ## EXAMPLES
 *
 *     # List all new issues
 *     $ wp wfsec issues list
 *
 *     # List ignored issues
 *     $ wp wfsec issues list --status=ignored
 *
 *     # Delete an issue
 *     $ wp wfsec issues delete 123
 *
 *     # Ignore an issue
 *     $ wp wfsec issues ignore 123
 */
class IssuesCommand
{
    /**
     * List security issues
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status (new, ignoreP, ignoreC, all)
     * ---
     * default: new
     * options:
     *   - new
     *   - ignoreP
     *   - ignoreC
     *   - all
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
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec issues list
     *     $ wp wfsec issues list --status=all
     *     $ wp wfsec issues list --format=json
     *
     * @when after_wp_load
     * @alias ls
     */
    public function list_($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $status = $assoc_args['status'] ?? 'new';
        $format = $assoc_args['format'] ?? 'table';

        $issues = WordfenceService::getIssues($status);

        if ($format === 'count') {
            WP_CLI::log((string) count($issues));
            return;
        }

        if (empty($issues)) {
            WP_CLI::log(sprintf('No %s issues found.', $status === 'all' ? '' : $status));
            return;
        }

        $items = array_map(function ($issue) {
            $data = is_array($issue['data']) ? $issue['data'] : json_decode($issue['data'], true);
            $time = isset($issue['time']) ? (int) $issue['time'] : 0;

            return [
                'ID' => $issue['id'] ?? 'N/A',
                'Type' => $issue['type'] ?? 'N/A',
                'Severity' => $issue['severity'] ?? 'N/A',
                'Status' => $issue['status'] ?? 'N/A',
                'Short Description' => isset($issue['shortMsg'])
                    ? substr($issue['shortMsg'], 0, 50)
                    : 'N/A',
                'File' => isset($data['file'])
                    ? basename($data['file'])
                    : 'N/A',
                'Time' => $time ? date('Y-m-d H:i', $time) : 'N/A',
            ];
        }, $issues);

        WP_CLI\Utils\format_items($format, $items, ['ID', 'Type', 'Severity', 'Status', 'Short Description', 'File', 'Time']);
    }

    /**
     * Show issue details
     *
     * ## OPTIONS
     *
     * <id>
     * : Issue ID
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: yaml
     * options:
     *   - yaml
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec issues show 123
     *
     * @when after_wp_load
     */
    public function show($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args[0])) {
            WP_CLI::error('Please provide an issue ID.');
        }

        $issueId = (int) $args[0];
        $issues = WordfenceService::getIssues('all');

        $issue = null;
        foreach ($issues as $i) {
            if ((int) $i['id'] === $issueId) {
                $issue = $i;
                break;
            }
        }

        if (!$issue) {
            WP_CLI::error(sprintf('Issue %d not found.', $issueId));
        }

        // Parse data if JSON
        if (isset($issue['data']) && is_string($issue['data'])) {
            $issue['data'] = json_decode($issue['data'], true);
        }

        $format = $assoc_args['format'] ?? 'yaml';

        if ($format === 'json') {
            WP_CLI::log(json_encode($issue, JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items('yaml', [$issue], array_keys($issue));
        }
    }

    /**
     * Delete an issue
     *
     * ## OPTIONS
     *
     * <id>...
     * : One or more issue IDs to delete
     *
     * [--all]
     * : Delete all issues
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec issues delete 123
     *     $ wp wfsec issues delete 123 456 789
     *     $ wp wfsec issues delete --all
     *
     * @when after_wp_load
     */
    public function delete($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (isset($assoc_args['all'])) {
            WP_CLI::confirm('Are you sure you want to delete ALL issues?', $assoc_args);

            $issues = WordfenceService::getIssues('all');
            $deleted = 0;

            foreach ($issues as $issue) {
                if (WordfenceService::deleteIssue((int) $issue['id'])) {
                    $deleted++;
                }
            }

            WP_CLI::success(sprintf('Deleted %d issues.', $deleted));
            return;
        }

        if (empty($args)) {
            WP_CLI::error('Please provide one or more issue IDs, or use --all.');
        }

        $deleted = 0;
        $failed = 0;

        foreach ($args as $id) {
            if (WordfenceService::deleteIssue((int) $id)) {
                $deleted++;
            } else {
                $failed++;
                WP_CLI::warning(sprintf('Failed to delete issue %d.', $id));
            }
        }

        if ($deleted > 0) {
            WP_CLI::success(sprintf('Deleted %d issue(s).', $deleted));
        }

        if ($failed > 0 && $deleted === 0) {
            WP_CLI::error('Failed to delete any issues.');
        }
    }

    /**
     * Ignore an issue
     *
     * ## OPTIONS
     *
     * <id>...
     * : One or more issue IDs to ignore
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec issues ignore 123
     *     $ wp wfsec issues ignore 123 456 789
     *
     * @when after_wp_load
     */
    public function ignore($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        if (empty($args)) {
            WP_CLI::error('Please provide one or more issue IDs.');
        }

        $ignored = 0;
        $failed = 0;

        foreach ($args as $id) {
            if (WordfenceService::ignoreIssue((int) $id)) {
                $ignored++;
            } else {
                $failed++;
                WP_CLI::warning(sprintf('Failed to ignore issue %d.', $id));
            }
        }

        if ($ignored > 0) {
            WP_CLI::success(sprintf('Ignored %d issue(s).', $ignored));
        }

        if ($failed > 0 && $ignored === 0) {
            WP_CLI::error('Failed to ignore any issues.');
        }
    }

    /**
     * Show issue counts
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
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp wfsec issues count
     *
     * @when after_wp_load
     */
    public function count($args, $assoc_args)
    {
        if (!WordfenceService::isAvailable()) {
            WP_CLI::error('Wordfence is not available.');
        }

        $newCount = WordfenceService::getIssueCount('new');
        $ignoredCount = WordfenceService::getIssueCount('ignoreP') + WordfenceService::getIssueCount('ignoreC');
        $totalCount = WordfenceService::getIssueCount('all');

        $items = [
            ['Status' => 'New', 'Count' => $newCount],
            ['Status' => 'Ignored', 'Count' => $ignoredCount],
            ['Status' => 'Total', 'Count' => $totalCount],
        ];

        $format = $assoc_args['format'] ?? 'table';

        if ($format === 'json') {
            WP_CLI::log(json_encode([
                'new' => $newCount,
                'ignored' => $ignoredCount,
                'total' => $totalCount,
            ], JSON_PRETTY_PRINT));
        } else {
            WP_CLI\Utils\format_items($format, $items, ['Status', 'Count']);
        }
    }
}
