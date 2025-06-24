<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Auto-sync status monitoring page for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/alx_report_api/lib.php');

// Check permissions.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page context
$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url('/local/alx_report_api/auto_sync_status.php');
$PAGE->set_title('Auto-Sync Status - ALX Report API');
$PAGE->set_heading('Auto-Sync Status Monitoring');

echo $OUTPUT->header();

// Get current configuration
$sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
$max_sync_time = get_config('local_alx_report_api', 'max_sync_time') ?: 300;
$last_sync = get_config('local_alx_report_api', 'last_auto_sync');
$last_stats = get_config('local_alx_report_api', 'last_sync_stats');

// Get scheduled task info
global $DB;
$task_record = $DB->get_record('task_scheduled', ['classname' => '\local_alx_report_api\task\sync_reporting_data_task']);

echo '<div class="container-fluid">';
echo '<div class="row">';

// Configuration Status
echo '<div class="col-md-6">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-primary text-white">';
echo '<h4 class="card-title mb-0">⚙️ Configuration Status</h4>';
echo '</div>';
echo '<div class="card-body">';

echo '<table class="table table-striped">';
echo '<tr><td><strong>Sync Interval:</strong></td><td>Every hour (at minute 0)</td></tr>';
echo '<tr><td><strong>Look-back Period:</strong></td><td>' . $sync_hours . ' hour(s)</td></tr>';
echo '<tr><td><strong>Max Execution Time:</strong></td><td>' . $max_sync_time . ' seconds</td></tr>';

if ($task_record) {
    $status = $task_record->disabled ? 
        '<span class="badge badge-danger">DISABLED</span>' : 
        '<span class="badge badge-success">ENABLED</span>';
    echo '<tr><td><strong>Task Status:</strong></td><td>' . $status . '</td></tr>';
    
    if ($task_record->nextruntime) {
        $next_run = userdate($task_record->nextruntime, '%Y-%m-%d %H:%M:%S');
        echo '<tr><td><strong>Next Scheduled Run:</strong></td><td>' . $next_run . '</td></tr>';
    }
    
    if ($task_record->lastruntime) {
        $last_run = userdate($task_record->lastruntime, '%Y-%m-%d %H:%M:%S');
        echo '<tr><td><strong>Last Run:</strong></td><td>' . $last_run . '</td></tr>';
    }
} else {
    echo '<tr><td><strong>Task Status:</strong></td><td><span class="badge badge-warning">NOT FOUND</span></td></tr>';
}

echo '</table>';
echo '</div>';
echo '</div>';
echo '</div>';

// Last Sync Statistics
echo '<div class="col-md-6">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-success text-white">';
echo '<h4 class="card-title mb-0">📊 Last Sync Statistics</h4>';
echo '</div>';
echo '<div class="card-body">';

if ($last_sync && $last_stats) {
    $stats = json_decode($last_stats, true);
    $sync_time = userdate($last_sync, '%Y-%m-%d %H:%M:%S');
    
    echo '<p><strong>Last Sync:</strong> ' . $sync_time . '</p>';
    echo '<table class="table table-striped">';
    echo '<tr><td><strong>Companies Processed:</strong></td><td>' . ($stats['companies_processed'] ?? 0) . '</td></tr>';
    echo '<tr><td><strong>Users Updated:</strong></td><td>' . ($stats['total_users_updated'] ?? 0) . '</td></tr>';
    echo '<tr><td><strong>Records Updated:</strong></td><td>' . ($stats['total_records_updated'] ?? 0) . '</td></tr>';
    echo '<tr><td><strong>Records Created:</strong></td><td>' . ($stats['total_records_created'] ?? 0) . '</td></tr>';
    
    $error_count = $stats['total_errors'] ?? 0;
    if ($error_count > 0) {
        echo '<tr><td><strong>Errors:</strong></td><td><span class="badge badge-danger">' . $error_count . '</span></td></tr>';
        if (!empty($stats['companies_with_errors'])) {
            echo '<tr><td><strong>Companies with Errors:</strong></td><td>' . implode(', ', $stats['companies_with_errors']) . '</td></tr>';
        }
    } else {
        echo '<tr><td><strong>Errors:</strong></td><td><span class="badge badge-success">0</span></td></tr>';
    }
    echo '</table>';
} else {
    echo '<div class="alert alert-info">';
    echo '<strong>No sync data available yet.</strong><br>';
    echo 'The automatic sync task has not run yet or no statistics are available.';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// System Status
echo '<div class="row">';
echo '<div class="col-12">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-info text-white">';
echo '<h4 class="card-title mb-0">🔧 System Status & Actions</h4>';
echo '</div>';
echo '<div class="card-body">';

// Check if cron is running
$last_cron = get_config('tool_task', 'lastcronstart');
$cron_status = '';
if ($last_cron) {
    $time_since_cron = time() - $last_cron;
    if ($time_since_cron < 3600) { // Less than 1 hour
        $cron_status = '<span class="badge badge-success">RUNNING (last run ' . format_time($time_since_cron) . ' ago)</span>';
    } else {
        $cron_status = '<span class="badge badge-warning">DELAYED (last run ' . format_time($time_since_cron) . ' ago)</span>';
    }
} else {
    $cron_status = '<span class="badge badge-danger">NOT RUNNING</span>';
}

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<h5>System Health</h5>';
echo '<table class="table table-striped">';
echo '<tr><td><strong>Moodle Cron Status:</strong></td><td>' . $cron_status . '</td></tr>';

// Check reporting table status
$total_reporting_records = $DB->count_records('local_alx_api_reporting');
echo '<tr><td><strong>Reporting Table Records:</strong></td><td>' . number_format($total_reporting_records) . '</td></tr>';

// Check cache table status
$total_cache_records = $DB->count_records('local_alx_api_cache');
echo '<tr><td><strong>Cache Table Records:</strong></td><td>' . number_format($total_cache_records) . '</td></tr>';

echo '</table>';
echo '</div>';

echo '<div class="col-md-6">';
echo '<h5>Quick Actions</h5>';
echo '<div class="btn-group-vertical" style="width: 100%;">';

// Link to scheduled tasks management
echo '<a href="' . $CFG->wwwroot . '/admin/tool/task/scheduledtasks.php" class="btn btn-primary mb-2">';
echo '📅 Manage Scheduled Tasks</a>';

// Link to task logs
echo '<a href="' . $CFG->wwwroot . '/admin/tool/task/index.php" class="btn btn-info mb-2">';
echo '📋 View Task Logs</a>';

// Link to manual sync
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/sync_reporting_data.php" class="btn btn-warning mb-2">';
echo '🔄 Manual Sync</a>';

// Link to company settings
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/company_settings.php" class="btn btn-success mb-2">';
echo '🏢 Company Settings</a>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// Instructions
echo '<div class="row">';
echo '<div class="col-12">';
echo '<div class="card">';
echo '<div class="card-header bg-dark text-white">';
echo '<h4 class="card-title mb-0">📖 How It Works</h4>';
echo '</div>';
echo '<div class="card-body">';

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<h5>Automatic Sync Process</h5>';
echo '<ol>';
echo '<li><strong>Scheduled Task:</strong> Runs every hour at minute 0 (e.g., 10:00, 11:00, 12:00)</li>';
echo '<li><strong>Change Detection:</strong> Looks for course completions, module completions, and enrollment changes in the last ' . $sync_hours . ' hour(s)</li>';
echo '<li><strong>Data Update:</strong> Updates the reporting table with fresh data from main database</li>';
echo '<li><strong>Cache Clear:</strong> Clears old cache entries to ensure fresh API responses</li>';
echo '<li><strong>Statistics:</strong> Logs performance statistics for monitoring</li>';
echo '</ol>';
echo '</div>';

echo '<div class="col-md-6">';
echo '<h5>Benefits</h5>';
echo '<ul>';
echo '<li><strong>Fresh Data:</strong> Reporting table stays up-to-date automatically</li>';
echo '<li><strong>Fast API:</strong> API calls get fresh data from optimized reporting table</li>';
echo '<li><strong>Efficient Caching:</strong> Cache expires hourly, ensuring balance between speed and freshness</li>';
echo '<li><strong>No Manual Work:</strong> Completely automated - no human intervention needed</li>';
echo '<li><strong>Multi-Company:</strong> Handles all companies automatically</li>';
echo '</ul>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// Reporting Table Data View
echo '<div class="row">';
echo '<div class="col-12">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-warning text-white">';
echo '<h4 class="card-title mb-0">📊 Reporting Table Data</h4>';
echo '</div>';
echo '<div class="card-body">';

// Get reporting table statistics by company
$reporting_sql = "
    SELECT 
        r.companyid,
        c.name as company_name,
        COUNT(*) as total_records,
        COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed_records,
        COUNT(CASE WHEN r.status = 'in_progress' THEN 1 END) as in_progress_records,
        COUNT(CASE WHEN r.status = 'not_started' THEN 1 END) as not_started_records,
        MAX(r.last_updated) as last_updated,
        MIN(r.created_at) as oldest_record,
        COUNT(CASE WHEN r.is_deleted = 1 THEN 1 END) as deleted_records
    FROM {local_alx_api_reporting} r
    JOIN {company} c ON c.id = r.companyid
    WHERE r.is_deleted = 0
    GROUP BY r.companyid, c.name
    ORDER BY total_records DESC";

$reporting_stats = $DB->get_records_sql($reporting_sql);

if (!empty($reporting_stats)) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover">';
    echo '<thead class="thead-dark">';
    echo '<tr>';
    echo '<th>Company</th>';
    echo '<th>Total Records</th>';
    echo '<th>Completed</th>';
    echo '<th>In Progress</th>';
    echo '<th>Not Started</th>';
    echo '<th>Last Updated</th>';
    echo '<th>Oldest Record</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($reporting_stats as $stat) {
        $completion_rate = $stat->total_records > 0 ? round(($stat->completed_records / $stat->total_records) * 100, 1) : 0;
        $last_updated = $stat->last_updated ? userdate($stat->last_updated, '%Y-%m-%d %H:%M') : 'Never';
        $oldest_record = $stat->oldest_record ? userdate($stat->oldest_record, '%Y-%m-%d') : 'Unknown';
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($stat->company_name) . '</strong><br>';
        echo '<small class="text-muted">ID: ' . $stat->companyid . '</small></td>';
        echo '<td><span class="badge badge-primary">' . number_format($stat->total_records) . '</span></td>';
        echo '<td><span class="badge badge-success">' . number_format($stat->completed_records) . '</span><br>';
        echo '<small class="text-muted">' . $completion_rate . '%</small></td>';
        echo '<td><span class="badge badge-warning">' . number_format($stat->in_progress_records) . '</span></td>';
        echo '<td><span class="badge badge-secondary">' . number_format($stat->not_started_records) . '</span></td>';
        echo '<td>' . $last_updated . '</td>';
        echo '<td>' . $oldest_record . '</td>';
        echo '<td>';
        echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/view_reporting_data.php?companyid=' . $stat->companyid . '" class="btn btn-sm btn-info">View Data</a>';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<div class="alert alert-warning">';
    echo '<h5>⚠️ No Reporting Data Found</h5>';
    echo '<p>The reporting table is empty. This could mean:</p>';
    echo '<ul>';
    echo '<li>The automatic sync has not run yet</li>';
    echo '<li>No historical data has been populated</li>';
    echo '<li>No companies have API access configured</li>';
    echo '</ul>';
    echo '<p><strong>Recommended Actions:</strong></p>';
    echo '<ol>';
    echo '<li><a href="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php">Populate Historical Data</a></li>';
    echo '<li><a href="' . $CFG->wwwroot . '/local/alx_report_api/sync_reporting_data.php">Run Manual Sync</a></li>';
    echo '<li>Wait for the next hourly automatic sync</li>';
    echo '</ol>';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// Recent Sync Activity Log
echo '<div class="row">';
echo '<div class="col-12">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-secondary text-white">';
echo '<h4 class="card-title mb-0">📋 Recent Sync Activity</h4>';
echo '</div>';
echo '<div class="card-body">';

// Get recent sync activity from task logs (if available)
$recent_logs_sql = "
    SELECT 
        tl.timestart,
        tl.timeend,
        tl.result,
        tl.output,
        tl.hostname
    FROM {task_log} tl
    WHERE tl.classname = :classname
    ORDER BY tl.timestart DESC
    LIMIT 10";

$recent_logs = $DB->get_records_sql($recent_logs_sql, ['classname' => '\local_alx_report_api\task\sync_reporting_data_task']);

if (!empty($recent_logs)) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Start Time</th>';
    echo '<th>Duration</th>';
    echo '<th>Result</th>';
    echo '<th>Output Summary</th>';
    echo '<th>Server</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($recent_logs as $log) {
        $duration = $log->timeend ? ($log->timeend - $log->timestart) . 's' : 'Running...';
        $result_badge = $log->result == 1 ? 'badge-success' : 'badge-danger';
        $result_text = $log->result == 1 ? 'Success' : 'Failed';
        
        // Extract key info from output
        $output_summary = '';
        if ($log->output) {
            if (strpos($log->output, 'Companies processed:') !== false) {
                preg_match('/Companies processed: (\d+)/', $log->output, $matches);
                $companies = $matches[1] ?? '0';
                preg_match('/Total records updated: (\d+)/', $log->output, $matches);
                $records = $matches[1] ?? '0';
                $output_summary = "{$companies} companies, {$records} records";
            } else {
                $output_summary = substr(strip_tags($log->output), 0, 100) . '...';
            }
        }
        
        echo '<tr>';
        echo '<td>' . userdate($log->timestart, '%Y-%m-%d %H:%M:%S') . '</td>';
        echo '<td>' . $duration . '</td>';
        echo '<td><span class="badge ' . $result_badge . '">' . $result_text . '</span></td>';
        echo '<td>' . htmlspecialchars($output_summary) . '</td>';
        echo '<td>' . htmlspecialchars($log->hostname) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
} else {
    echo '<div class="alert alert-info">';
    echo '<strong>No sync activity logs found.</strong><br>';
    echo 'This could mean the automatic sync has not run yet, or task logging is not enabled.';
    echo '</div>';
}

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row

// Manual Sync Trigger
echo '<div class="row">';
echo '<div class="col-12">';
echo '<div class="card mb-4">';
echo '<div class="card-header bg-danger text-white">';
echo '<h4 class="card-title mb-0">🚀 Manual Sync Trigger</h4>';
echo '</div>';
echo '<div class="card-body">';

echo '<div class="alert alert-warning">';
echo '<strong>⚠️ Testing & Troubleshooting Only</strong><br>';
echo 'Use this to manually trigger the sync task for testing purposes. The automatic hourly sync should handle regular operations.';
echo '</div>';

echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<h5>Trigger Sync Now</h5>';
echo '<p>This will run the same sync process that runs automatically every hour.</p>';
echo '<form method="post" action="' . $CFG->wwwroot . '/local/alx_report_api/manual_sync_trigger.php">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<button type="submit" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to manually trigger the sync? This may take several minutes.\');">';
echo '🔄 Trigger Sync Now</button>';
echo '</form>';
echo '</div>';

echo '<div class="col-md-6">';
echo '<h5>What This Does</h5>';
echo '<ul>';
echo '<li>Scans for changes in the last ' . $sync_hours . ' hour(s)</li>';
echo '<li>Updates the reporting table with fresh data</li>';
echo '<li>Clears cache entries for affected companies</li>';
echo '<li>Logs statistics and performance metrics</li>';
echo '<li>Takes 30 seconds to 5 minutes depending on data volume</li>';
echo '</ul>';
echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row
echo '</div>'; // End container

echo $OUTPUT->footer();