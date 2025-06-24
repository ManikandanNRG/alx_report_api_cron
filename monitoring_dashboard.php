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
 * Monitoring dashboard for ALX Report API plugin with combined approach metrics.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

// Require admin login
admin_externalpage_setup('local_alx_report_api_monitoring');

// Page setup
$PAGE->set_url('/local/alx_report_api/monitoring_dashboard.php');
$PAGE->set_title('ALX Report API - Monitoring Dashboard');
$PAGE->set_heading('ALX Report API - Monitoring Dashboard');

// Handle actions
$action = optional_param('action', '', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);

if ($action === 'clear_cache' && confirm_sesskey()) {
    $cleared = local_alx_report_api_cache_cleanup(0); // Clear all cache
    redirect($PAGE->url, "Cache cleared: $cleared entries removed", null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'reset_sync_status' && $companyid && confirm_sesskey()) {
    $DB->delete_records('local_alx_api_sync_status', ['companyid' => $companyid]);
    redirect($PAGE->url, "Sync status reset for company ID: $companyid", null, \core\output\notification::NOTIFY_SUCCESS);
}

// Start output
echo $OUTPUT->header();

// Dashboard CSS
echo '<style>
.dashboard-container {
    max-width: 1400px;
    margin: 20px auto;
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.dashboard-header h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 600;
}

.dashboard-header p {
    margin: 10px 0 0 0;
    font-size: 18px;
    opacity: 0.9;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 5px solid #28a745;
    transition: transform 0.3s;
}

.metric-card:hover {
    transform: translateY(-5px);
}

.metric-card.warning {
    border-left-color: #ffc107;
}

.metric-card.error {
    border-left-color: #dc3545;
}

.metric-card.info {
    border-left-color: #17a2b8;
}

.metric-title {
    font-size: 14px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    margin-bottom: 10px;
}

.metric-value {
    font-size: 32px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 5px;
}

.metric-description {
    font-size: 14px;
    color: #6c757d;
}

.section-title {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin: 40px 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 3px solid #28a745;
}

.table-container {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
}

.table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success {
    background: #d4edda;
    color: #155724;
}

.status-warning {
    background: #fff3cd;
    color: #856404;
}

.status-error {
    background: #f8d7da;
    color: #721c24;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    color: white;
    text-decoration: none;
}

.btn-warning {
    background: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background: #e0a800;
    color: #212529;
    text-decoration: none;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    color: white;
    text-decoration: none;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: #6c757d;
}

.mb-3 {
    margin-bottom: 1rem;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin: 20px 0;
    font-weight: 500;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.progress-bar {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    transition: width 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    font-weight: 600;
}
</style>';

echo '<div class="dashboard-container">';

// Dashboard Header
echo '<div class="dashboard-header">';
echo '<h1>📊 ALX Report API Monitoring Dashboard</h1>';
echo '<p>Combined Approach: Reporting Table + Incremental Sync Performance</p>';
echo '</div>';

// Get overall statistics
$companies = local_alx_report_api_get_companies();
$total_companies = count($companies);

// Reporting table statistics
$total_reporting_records = $DB->count_records('local_alx_api_reporting');
$active_reporting_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 0]);
$deleted_reporting_records = $DB->count_records('local_alx_api_reporting', ['is_deleted' => 1]);

// Sync status statistics
$total_sync_records = $DB->count_records('local_alx_api_sync_status');
$recent_syncs = $DB->count_records_select('local_alx_api_sync_status', 'last_sync_timestamp > ?', [time() - 86400]);

// Cache statistics
$total_cache_records = $DB->count_records('local_alx_api_cache');
$cache_hit_rate = 0;
if ($total_cache_records > 0) {
    $total_hits = $DB->get_field_sql('SELECT SUM(hit_count) FROM {local_alx_api_cache}');
    $cache_hit_rate = $total_hits ? round(($total_hits / $total_cache_records) * 100, 1) : 0;
}

// API logs statistics
$api_logs_exist = $DB->get_manager()->table_exists('local_alx_api_logs');
$total_api_calls = 0;
$recent_api_calls = 0;
if ($api_logs_exist) {
    $total_api_calls = $DB->count_records('local_alx_api_logs');
    $recent_api_calls = $DB->count_records_select('local_alx_api_logs', 'timecreated > ?', [time() - 86400]);
}

// Metrics Grid
echo '<div class="metrics-grid">';

// Reporting Table Metrics
echo '<div class="metric-card">';
echo '<div class="metric-title">Reporting Table Records</div>';
echo '<div class="metric-value">' . number_format($active_reporting_records) . '</div>';
echo '<div class="metric-description">Active records in reporting table</div>';
echo '</div>';

echo '<div class="metric-card info">';
echo '<div class="metric-title">Companies</div>';
echo '<div class="metric-value">' . $total_companies . '</div>';
echo '<div class="metric-description">Total companies configured</div>';
echo '</div>';

echo '<div class="metric-card warning">';
echo '<div class="metric-title">Deleted Records</div>';
echo '<div class="metric-value">' . number_format($deleted_reporting_records) . '</div>';
echo '<div class="metric-description">Soft-deleted records</div>';
echo '</div>';

echo '<div class="metric-card">';
echo '<div class="metric-title">Cache Hit Rate</div>';
echo '<div class="metric-value">' . $cache_hit_rate . '%</div>';
echo '<div class="metric-description">API response cache efficiency</div>';
echo '</div>';

echo '<div class="metric-card info">';
echo '<div class="metric-title">Recent Syncs (24h)</div>';
echo '<div class="metric-value">' . $recent_syncs . '</div>';
echo '<div class="metric-description">Sync operations in last 24 hours</div>';
echo '</div>';

if ($api_logs_exist) {
    echo '<div class="metric-card">';
    echo '<div class="metric-title">API Calls (24h)</div>';
    echo '<div class="metric-value">' . number_format($recent_api_calls) . '</div>';
    echo '<div class="metric-description">API requests in last 24 hours</div>';
    echo '</div>';
}

echo '</div>';

// Company-specific reporting statistics
echo '<h2 class="section-title">📈 Company Statistics</h2>';
echo '<div class="table-container">';
echo '<table class="table">';
echo '<thead>';
echo '<tr>';
echo '<th>Company</th>';
echo '<th>Total Records</th>';
echo '<th>Active Records</th>';
echo '<th>Deleted Records</th>';
echo '<th>Last Updated</th>';
echo '<th>Completion Rate</th>';
echo '<th>Actions</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($companies as $company) {
    $stats = local_alx_report_api_get_reporting_stats($company->id);
    $last_update = $stats['last_update'] ? date('Y-m-d H:i:s', $stats['last_update']) : 'Never';
    
    // Calculate completion rate
    $completion_rate = 0;
    if ($stats['active_records'] > 0) {
        $completion_rate = round(($stats['completed_courses'] / $stats['active_records']) * 100, 1);
    }
    
    echo '<tr>';
    echo '<td><strong>' . htmlspecialchars($company->name) . '</strong></td>';
    echo '<td>' . number_format($stats['total_records']) . '</td>';
    echo '<td>' . number_format($stats['active_records']) . '</td>';
    echo '<td>' . number_format($stats['deleted_records']) . '</td>';
    echo '<td>' . $last_update . '</td>';
    echo '<td>';
    echo '<div class="progress-bar">';
    echo '<div class="progress-fill" style="width: ' . $completion_rate . '%">' . $completion_rate . '%</div>';
    echo '</div>';
    echo '</td>';
    echo '<td>';
    echo '<a href="company_settings.php?companyid=' . $company->id . '" class="btn btn-primary btn-sm">Settings</a> ';
    echo '<a href="?action=reset_sync_status&companyid=' . $company->id . '&sesskey=' . sesskey() . '" class="btn btn-warning btn-sm" onclick="return confirm(\'Reset sync status for this company?\')">Reset Sync</a>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';

// Sync Status Overview
echo '<h2 class="section-title">🔄 Sync Status Overview</h2>';
echo '<div class="table-container">';

$sync_statuses = $DB->get_records_sql("
    SELECT ss.*, c.name as company_name
    FROM {local_alx_api_sync_status} ss
    JOIN {company} c ON c.id = ss.companyid
    ORDER BY ss.last_sync_timestamp DESC
    LIMIT 20
");

if (empty($sync_statuses)) {
    echo '<div class="alert alert-info">No sync status records found. Sync tracking will begin after the first API call.</div>';
} else {
    echo '<table class="table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Company</th>';
    echo '<th>Last Sync</th>';
    echo '<th>Sync Mode</th>';
    echo '<th>Records Returned</th>';
    echo '<th>Status</th>';
    echo '<th>Total Syncs</th>';
    echo '<th>Sync Window</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($sync_statuses as $sync) {
        $last_sync = date('Y-m-d H:i:s', $sync->last_sync_timestamp);
        $status_class = $sync->last_sync_status === 'success' ? 'status-success' : 'status-error';
        
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($sync->company_name) . '</strong></td>';
        echo '<td>' . $last_sync . '</td>';
        echo '<td>' . ucfirst($sync->sync_mode) . '</td>';
        echo '<td>' . number_format($sync->last_sync_records) . '</td>';
        echo '<td><span class="status-badge ' . $status_class . '">' . ucfirst($sync->last_sync_status) . '</span></td>';
        echo '<td>' . number_format($sync->total_syncs) . '</td>';
        echo '<td>' . $sync->sync_window_hours . 'h</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

echo '</div>';

// Cache Performance
echo '<h2 class="section-title">⚡ Cache Performance</h2>';
echo '<div class="table-container">';

$cache_stats = $DB->get_records_sql("
    SELECT c.cache_key, c.companyid, co.name as company_name, c.hit_count, 
           c.cache_timestamp, c.expires_at, c.last_accessed
    FROM {local_alx_api_cache} c
    LEFT JOIN {company} co ON co.id = c.companyid
    ORDER BY c.hit_count DESC, c.last_accessed DESC
    LIMIT 20
");

if (empty($cache_stats)) {
    echo '<div class="alert alert-info">No cache entries found. Cache will be populated after API calls.</div>';
} else {
    echo '<table class="table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Cache Key</th>';
    echo '<th>Company</th>';
    echo '<th>Hit Count</th>';
    echo '<th>Created</th>';
    echo '<th>Expires</th>';
    echo '<th>Last Accessed</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($cache_stats as $cache) {
        $created = date('Y-m-d H:i:s', $cache->cache_timestamp);
        $expires = date('Y-m-d H:i:s', $cache->expires_at);
        $last_accessed = date('Y-m-d H:i:s', $cache->last_accessed);
        $is_expired = $cache->expires_at < time();
        $status_class = $is_expired ? 'status-error' : 'status-success';
        $status_text = $is_expired ? 'Expired' : 'Active';
        
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($cache->cache_key) . '</code></td>';
        echo '<td>' . htmlspecialchars($cache->company_name ?: 'Unknown') . '</td>';
        echo '<td>' . number_format($cache->hit_count) . '</td>';
        echo '<td>' . $created . '</td>';
        echo '<td>' . $expires . '</td>';
        echo '<td>' . $last_accessed . '</td>';
        echo '<td><span class="status-badge ' . $status_class . '">' . $status_text . '</span></td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}

echo '</div>';

// Recent API Activity (if logs exist)
if ($api_logs_exist && $recent_api_calls > 0) {
    echo '<h2 class="section-title">📡 Recent API Activity</h2>';
    echo '<div class="table-container">';
    
    $recent_logs = $DB->get_records_sql("
        SELECT l.*, c.name as company_name, u.firstname, u.lastname
        FROM {local_alx_api_logs} l
        LEFT JOIN {company} c ON c.id = l.companyid
        LEFT JOIN {user} u ON u.id = l.userid
        WHERE l.timecreated > ?
        ORDER BY l.timecreated DESC
        LIMIT 20
    ", [time() - 86400]);
    
    echo '<table class="table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Time</th>';
    echo '<th>Company</th>';
    echo '<th>User</th>';
    echo '<th>Endpoint</th>';
    echo '<th>Response Size</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($recent_logs as $log) {
        $time = date('Y-m-d H:i:s', $log->timecreated);
        $user_name = $log->firstname && $log->lastname ? $log->firstname . ' ' . $log->lastname : 'Unknown';
        $response_size = strlen($log->response_data);
        
        echo '<tr>';
        echo '<td>' . $time . '</td>';
        echo '<td>' . htmlspecialchars($log->company_name ?: 'Unknown') . '</td>';
        echo '<td>' . htmlspecialchars($user_name) . '</td>';
        echo '<td>' . htmlspecialchars($log->endpoint) . '</td>';
        echo '<td>' . number_format($response_size) . ' bytes</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// System Actions
echo '<h2 class="section-title">🛠️ System Actions</h2>';
echo '<div class="table-container">';
echo '<div class="text-center">';
echo '<p class="text-muted mb-3">Perform maintenance operations on the combined approach system.</p>';
echo '<a href="populate_reporting_table.php" class="btn btn-primary">📊 Populate Reporting Table</a> ';
echo '<a href="sync_reporting_data.php" class="btn btn-primary">🔄 Background Sync</a> ';
echo '<a href="?action=clear_cache&sesskey=' . sesskey() . '" class="btn btn-warning" onclick="return confirm(\'Clear all cache entries?\')">🗑️ Clear Cache</a> ';
echo '<a href="company_settings.php" class="btn btn-primary">⚙️ Company Settings</a>';
echo '</div>';
echo '</div>';

echo '</div>'; // dashboard-container

echo $OUTPUT->footer(); 