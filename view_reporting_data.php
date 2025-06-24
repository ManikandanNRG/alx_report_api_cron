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
 * View reporting table data for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

// Check permissions.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page context
$context = context_system::instance();
$PAGE->set_context($context);

$companyid = optional_param('companyid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);

$PAGE->set_url('/local/alx_report_api/view_reporting_data.php', ['companyid' => $companyid]);
$PAGE->set_title('View Reporting Data - ALX Report API');
$PAGE->set_heading('Reporting Table Data');

// Get company info
global $DB;
$company = null;
if ($companyid) {
    $company = $DB->get_record('company', ['id' => $companyid]);
    if (!$company) {
        print_error('Company not found');
    }
}

echo $OUTPUT->header();

echo '<div class="container-fluid">';

// Company selection if not specified
if (!$companyid) {
    echo '<div class="row">';
    echo '<div class="col-12">';
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h4>📊 Select Company to View Data</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    
    $companies_sql = "
        SELECT DISTINCT r.companyid, c.name, COUNT(*) as record_count
        FROM {local_alx_api_reporting} r
        JOIN {company} c ON c.id = r.companyid
        WHERE r.is_deleted = 0
        GROUP BY r.companyid, c.name
        ORDER BY c.name";
    
    $companies = $DB->get_records_sql($companies_sql);
    
    if ($companies) {
        echo '<div class="list-group">';
        foreach ($companies as $comp) {
            echo '<a href="?companyid=' . $comp->companyid . '" class="list-group-item list-group-item-action">';
            echo '<div class="d-flex w-100 justify-content-between">';
            echo '<h5 class="mb-1">' . htmlspecialchars($comp->name) . '</h5>';
            echo '<span class="badge badge-primary badge-pill">' . number_format($comp->record_count) . ' records</span>';
            echo '</div>';
            echo '<small>Company ID: ' . $comp->companyid . '</small>';
            echo '</a>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning">';
        echo '<h5>No Data Available</h5>';
        echo '<p>No reporting data found. The reporting table appears to be empty.</p>';
        echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php" class="btn btn-primary">Check Sync Status</a>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo $OUTPUT->footer();
    exit;
}

// Show data for selected company
echo '<div class="row">';
echo '<div class="col-12">';

// Company header
echo '<div class="d-flex justify-content-between align-items-center mb-4">';
echo '<div>';
echo '<h2>📊 ' . htmlspecialchars($company->name) . ' - Reporting Data</h2>';
echo '<p class="text-muted">Company ID: ' . $companyid . '</p>';
echo '</div>';
echo '<div>';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/view_reporting_data.php" class="btn btn-secondary">🔙 Back to Company List</a>';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php" class="btn btn-primary ml-2">📈 Sync Status</a>';
echo '</div>';
echo '</div>';

// Summary statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'not_started' THEN 1 END) as not_started,
        COUNT(CASE WHEN status = 'not_enrolled' THEN 1 END) as not_enrolled,
        MAX(last_updated) as last_updated,
        COUNT(DISTINCT userid) as unique_users,
        COUNT(DISTINCT courseid) as unique_courses
    FROM {local_alx_api_reporting}
    WHERE companyid = :companyid AND is_deleted = 0";

$stats = $DB->get_record_sql($stats_sql, ['companyid' => $companyid]);

echo '<div class="row mb-4">';
echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h3 class="text-primary">' . number_format($stats->total_records) . '</h3>';
echo '<p class="card-text">Total Records</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h3 class="text-success">' . number_format($stats->completed) . '</h3>';
echo '<p class="card-text">Completed</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h3 class="text-info">' . number_format($stats->unique_users) . '</h3>';
echo '<p class="card-text">Unique Users</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<div class="col-md-3">';
echo '<div class="card text-center">';
echo '<div class="card-body">';
echo '<h3 class="text-warning">' . number_format($stats->unique_courses) . '</h3>';
echo '<p class="card-text">Unique Courses</p>';
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Filters
echo '<div class="card mb-4">';
echo '<div class="card-header">';
echo '<h5>🔍 Filters</h5>';
echo '</div>';
echo '<div class="card-body">';
echo '<form method="get" class="form-inline">';
echo '<input type="hidden" name="companyid" value="' . $companyid . '">';
echo '<div class="form-group mr-3">';
echo '<label for="status" class="mr-2">Status:</label>';
echo '<select name="status" id="status" class="form-control">';
echo '<option value="">All Statuses</option>';
echo '<option value="completed"' . ($status === 'completed' ? ' selected' : '') . '>Completed</option>';
echo '<option value="in_progress"' . ($status === 'in_progress' ? ' selected' : '') . '>In Progress</option>';
echo '<option value="not_started"' . ($status === 'not_started' ? ' selected' : '') . '>Not Started</option>';
echo '<option value="not_enrolled"' . ($status === 'not_enrolled' ? ' selected' : '') . '>Not Enrolled</option>';
echo '</select>';
echo '</div>';
echo '<div class="form-group mr-3">';
echo '<label for="perpage" class="mr-2">Per Page:</label>';
echo '<select name="perpage" id="perpage" class="form-control">';
echo '<option value="25"' . ($perpage == 25 ? ' selected' : '') . '>25</option>';
echo '<option value="50"' . ($perpage == 50 ? ' selected' : '') . '>50</option>';
echo '<option value="100"' . ($perpage == 100 ? ' selected' : '') . '>100</option>';
echo '<option value="200"' . ($perpage == 200 ? ' selected' : '') . '>200</option>';
echo '</select>';
echo '</div>';
echo '<button type="submit" class="btn btn-primary">Apply Filters</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Data table
$where_conditions = ['companyid = :companyid', 'is_deleted = 0'];
$params = ['companyid' => $companyid];

if ($status) {
    $where_conditions[] = 'status = :status';
    $params['status'] = $status;
}

$where_clause = implode(' AND ', $where_conditions);

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM {local_alx_api_reporting} WHERE $where_clause";
$total_records = $DB->count_records_sql($count_sql, $params);

// Get data with pagination
$data_sql = "
    SELECT 
        id, userid, firstname, lastname, email, courseid, coursename,
        timecompleted, timestarted, percentage, status, last_updated
    FROM {local_alx_api_reporting}
    WHERE $where_clause
    ORDER BY last_updated DESC, userid, courseid";

$offset = $page * $perpage;
$records = $DB->get_records_sql($data_sql, $params, $offset, $perpage);

echo '<div class="card">';
echo '<div class="card-header">';
echo '<h5>📋 Data Records</h5>';
echo '<small class="text-muted">Showing ' . ($offset + 1) . '-' . min($offset + $perpage, $total_records) . ' of ' . number_format($total_records) . ' records</small>';
echo '</div>';
echo '<div class="card-body">';

if ($records) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped table-hover table-sm">';
    echo '<thead class="thead-dark">';
    echo '<tr>';
    echo '<th>User</th>';
    echo '<th>Course</th>';
    echo '<th>Status</th>';
    echo '<th>Progress</th>';
    echo '<th>Completed</th>';
    echo '<th>Started</th>';
    echo '<th>Last Updated</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($records as $record) {
        $status_badge = '';
        switch ($record->status) {
            case 'completed':
                $status_badge = 'badge-success';
                break;
            case 'in_progress':
                $status_badge = 'badge-warning';
                break;
            case 'not_started':
                $status_badge = 'badge-secondary';
                break;
            case 'not_enrolled':
                $status_badge = 'badge-light';
                break;
            default:
                $status_badge = 'badge-dark';
        }
        
        echo '<tr>';
        echo '<td>';
        echo '<strong>' . htmlspecialchars($record->firstname . ' ' . $record->lastname) . '</strong><br>';
        echo '<small class="text-muted">' . htmlspecialchars($record->email) . '</small><br>';
        echo '<small class="text-muted">ID: ' . $record->userid . '</small>';
        echo '</td>';
        echo '<td>';
        echo '<strong>' . htmlspecialchars($record->coursename) . '</strong><br>';
        echo '<small class="text-muted">ID: ' . $record->courseid . '</small>';
        echo '</td>';
        echo '<td><span class="badge ' . $status_badge . '">' . ucfirst(str_replace('_', ' ', $record->status)) . '</span></td>';
        echo '<td>';
        if ($record->percentage > 0) {
            echo '<div class="progress" style="height: 20px;">';
            echo '<div class="progress-bar" role="progressbar" style="width: ' . $record->percentage . '%">';
            echo round($record->percentage, 1) . '%';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<span class="text-muted">0%</span>';
        }
        echo '</td>';
        echo '<td>' . ($record->timecompleted ? userdate($record->timecompleted, '%Y-%m-%d %H:%M') : '-') . '</td>';
        echo '<td>' . ($record->timestarted ? userdate($record->timestarted, '%Y-%m-%d %H:%M') : '-') . '</td>';
        echo '<td>' . userdate($record->last_updated, '%Y-%m-%d %H:%M') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Pagination
    if ($total_records > $perpage) {
        $total_pages = ceil($total_records / $perpage);
        $base_url = $PAGE->url;
        $base_url->params(['companyid' => $companyid, 'status' => $status, 'perpage' => $perpage]);
        
        echo '<nav aria-label="Data pagination">';
        echo '<ul class="pagination justify-content-center">';
        
        // Previous page
        if ($page > 0) {
            $prev_url = clone $base_url;
            $prev_url->param('page', $page - 1);
            echo '<li class="page-item"><a class="page-link" href="' . $prev_url . '">Previous</a></li>';
        }
        
        // Page numbers
        $start_page = max(0, $page - 2);
        $end_page = min($total_pages - 1, $page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $page_url = clone $base_url;
            $page_url->param('page', $i);
            $active = ($i == $page) ? ' active' : '';
            echo '<li class="page-item' . $active . '"><a class="page-link" href="' . $page_url . '">' . ($i + 1) . '</a></li>';
        }
        
        // Next page
        if ($page < $total_pages - 1) {
            $next_url = clone $base_url;
            $next_url->param('page', $page + 1);
            echo '<li class="page-item"><a class="page-link" href="' . $next_url . '">Next</a></li>';
        }
        
        echo '</ul>';
        echo '</nav>';
    }
    
} else {
    echo '<div class="alert alert-info">';
    echo '<h5>No Records Found</h5>';
    echo '<p>No data records match the current filters.</p>';
    echo '</div>';
}

echo '</div>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer(); 