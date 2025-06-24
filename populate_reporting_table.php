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
 * Initial data population script for ALX Report API reporting table.
 * 
 * This script populates the reporting table with existing data from the main database.
 * Run this once after installing the combined approach database schema.
 *
 * Usage:
 * - Via web browser: /local/alx_report_api/populate_reporting_table.php
 * - Via CLI: php populate_reporting_table.php
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include Moodle config
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Security check
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up page
$PAGE->set_url('/local/alx_report_api/populate_reporting_table.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('ALX Report API - Populate Reporting Table');
$PAGE->set_heading('ALX Report API - Initial Data Population');

// Check if this is a CLI request
$is_cli = (php_sapi_name() === 'cli');

// Handle form submission
$action = optional_param('action', '', PARAM_ALPHA);
$companyid = optional_param('companyid', 0, PARAM_INT);
$batch_size = optional_param('batch_size', 1000, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

if ($action === 'populate' && $confirm) {
    if (!$is_cli) {
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Populating Reporting Table...');
        echo '<div class="alert alert-info">This may take several minutes depending on your data size. Please wait...</div>';
        echo '<pre id="progress-log">';
        flush();
    }
    
    $start_time = time();
    echo "Starting data population at " . date('Y-m-d H:i:s') . "\n";
    echo "Company ID: " . ($companyid > 0 ? $companyid : 'All companies') . "\n";
    echo "Batch size: $batch_size\n";
    echo str_repeat('-', 50) . "\n";
    flush();
    
    // Run the population
    $result = local_alx_report_api_populate_reporting_table($companyid, $batch_size);
    
    echo "\n" . str_repeat('-', 50) . "\n";
    echo "Population completed!\n";
    echo "Total processed: " . $result['total_processed'] . "\n";
    echo "Total inserted: " . $result['total_inserted'] . "\n";
    echo "Companies processed: " . $result['companies_processed'] . "\n";
    echo "Duration: " . $result['duration_seconds'] . " seconds\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors encountered:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    if (!$is_cli) {
        echo '</pre>';
        echo '<div class="alert alert-success mt-3">';
        echo '<h4>Population Complete!</h4>';
        echo '<p><strong>Total Records Processed:</strong> ' . $result['total_processed'] . '</p>';
        echo '<p><strong>Total Records Inserted:</strong> ' . $result['total_inserted'] . '</p>';
        echo '<p><strong>Duration:</strong> ' . $result['duration_seconds'] . ' seconds</p>';
        echo '</div>';
        
        if (!empty($result['errors'])) {
            echo '<div class="alert alert-warning">';
            echo '<h4>Errors Encountered:</h4>';
            echo '<ul>';
            foreach ($result['errors'] as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        echo '<p><a href="' . $CFG->wwwroot . '/local/alx_report_api/populate_reporting_table.php" class="btn btn-primary">Back to Population Tool</a></p>';
        echo $OUTPUT->footer();
    }
    
    exit;
}

if ($is_cli) {
    echo "ALX Report API - Reporting Table Population Tool\n";
    echo "============================================\n\n";
    
    // Get CLI parameters
    $options = getopt('', ['companyid:', 'batch-size:', 'help']);
    
    if (isset($options['help'])) {
        echo "Usage: php populate_reporting_table.php [options]\n\n";
        echo "Options:\n";
        echo "  --companyid=ID    Populate data for specific company ID (default: all companies)\n";
        echo "  --batch-size=N    Number of records to process per batch (default: 1000)\n";
        echo "  --help           Show this help message\n\n";
        echo "Examples:\n";
        echo "  php populate_reporting_table.php\n";
        echo "  php populate_reporting_table.php --companyid=5 --batch-size=500\n\n";
        exit;
    }
    
    $cli_companyid = isset($options['companyid']) ? (int)$options['companyid'] : 0;
    $cli_batch_size = isset($options['batch-size']) ? (int)$options['batch-size'] : 1000;
    
    echo "Starting population with:\n";
    echo "Company ID: " . ($cli_companyid > 0 ? $cli_companyid : 'All companies') . "\n";
    echo "Batch size: $cli_batch_size\n\n";
    echo "Press Enter to continue or Ctrl+C to cancel...";
    fgets(STDIN);
    
    // Run population
    $result = local_alx_report_api_populate_reporting_table($cli_companyid, $cli_batch_size);
    
    echo "\nPopulation Results:\n";
    echo "==================\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "Total processed: " . $result['total_processed'] . "\n";
    echo "Total inserted: " . $result['total_inserted'] . "\n";
    echo "Companies processed: " . $result['companies_processed'] . "\n";
    echo "Duration: " . $result['duration_seconds'] . " seconds\n";
    
    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    exit;
}

// Web interface
echo $OUTPUT->header();

// Check if reporting table exists
if (!$DB->get_manager()->table_exists('local_alx_api_reporting')) {
    echo $OUTPUT->notification('Reporting table does not exist. Please upgrade the plugin first.', 'error');
    echo $OUTPUT->footer();
    exit;
}

// Get current statistics
$total_reporting_records = $DB->count_records('local_alx_api_reporting');
$companies = local_alx_report_api_get_companies();

echo $OUTPUT->heading('ALX Report API - Populate Reporting Table');

echo '<div class="alert alert-info">';
echo '<h4>About This Tool</h4>';
echo '<p>This tool populates the reporting table with existing data from your main database. ';
echo 'This is required for the combined approach (separate reporting table + incremental sync) to work properly.</p>';
echo '<p><strong>Important:</strong> This process may take several minutes depending on your data size. ';
echo 'It is recommended to run this during off-peak hours.</p>';
echo '</div>';

// Show current status
echo '<div class="card mb-4">';
echo '<div class="card-header"><h5>Current Status</h5></div>';
echo '<div class="card-body">';
echo '<div class="row">';
echo '<div class="col-md-6">';
echo '<p><strong>Companies Available:</strong> ' . count($companies) . '</p>';
echo '<p><strong>Reporting Records:</strong> ' . number_format($total_reporting_records) . '</p>';
echo '</div>';
echo '<div class="col-md-6">';
if ($total_reporting_records > 0) {
    $last_update = $DB->get_field_select('local_alx_api_reporting', 'MAX(last_updated)', '1=1');
    echo '<p><strong>Last Update:</strong> ' . ($last_update ? date('Y-m-d H:i:s', $last_update) : 'Never') . '</p>';
    echo '<p><strong>Status:</strong> <span class="badge badge-success">Data Available</span></p>';
} else {
    echo '<p><strong>Last Update:</strong> Never</p>';
    echo '<p><strong>Status:</strong> <span class="badge badge-warning">No Data</span></p>';
}
echo '</div>';
echo '</div>';
echo '</div>';
echo '</div>';

// Population form
echo '<div class="card">';
echo '<div class="card-header"><h5>Populate Reporting Table</h5></div>';
echo '<div class="card-body">';

echo '<form method="post" id="populate-form">';
echo '<input type="hidden" name="action" value="populate">';

echo '<div class="form-group">';
echo '<label for="companyid">Company:</label>';
echo '<select name="companyid" id="companyid" class="form-control">';
echo '<option value="0">All Companies</option>';
foreach ($companies as $company) {
    echo '<option value="' . $company->id . '">' . htmlspecialchars($company->name) . '</option>';
}
echo '</select>';
echo '<small class="form-text text-muted">Select a specific company or process all companies.</small>';
echo '</div>';

echo '<div class="form-group">';
echo '<label for="batch_size">Batch Size:</label>';
echo '<input type="number" name="batch_size" id="batch_size" class="form-control" value="1000" min="100" max="5000">';
echo '<small class="form-text text-muted">Number of records to process per batch. Larger batches are faster but use more memory.</small>';
echo '</div>';

echo '<div class="form-check mb-3">';
echo '<input type="checkbox" name="confirm" value="1" id="confirm" class="form-check-input" required>';
echo '<label for="confirm" class="form-check-label">';
echo 'I understand this process may take several minutes and should be run during off-peak hours.';
echo '</label>';
echo '</div>';

if ($total_reporting_records > 0) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Warning:</strong> You already have ' . number_format($total_reporting_records) . ' records in the reporting table. ';
    echo 'This process will add new records but will not update existing ones. ';
    echo 'If you want to refresh existing data, you may need to clear the reporting table first.';
    echo '</div>';
}

echo '<button type="submit" class="btn btn-primary btn-lg" id="populate-btn">';
echo '<i class="fa fa-database"></i> Start Population Process';
echo '</button>';

echo '</form>';
echo '</div>';
echo '</div>';

// Statistics by company
if (!empty($companies) && $total_reporting_records > 0) {
    echo '<div class="card mt-4">';
    echo '<div class="card-header"><h5>Records by Company</h5></div>';
    echo '<div class="card-body">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Company</th><th>Total Records</th><th>Active Records</th><th>Last Updated</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($companies as $company) {
        $stats = local_alx_report_api_get_reporting_stats($company->id);
        $last_update = $stats['last_update'] ? date('Y-m-d H:i:s', $stats['last_update']) : 'Never';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($company->name) . '</td>';
        echo '<td>' . number_format($stats['total_records']) . '</td>';
        echo '<td>' . number_format($stats['active_records']) . '</td>';
        echo '<td>' . $last_update . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// JavaScript for form handling
echo '<script>
document.getElementById("populate-form").addEventListener("submit", function(e) {
    var btn = document.getElementById("populate-btn");
    btn.innerHTML = "<i class=\"fa fa-spinner fa-spin\"></i> Processing...";
    btn.disabled = true;
    
    // Show processing message
    var alert = document.createElement("div");
    alert.className = "alert alert-info mt-3";
    alert.innerHTML = "<strong>Processing...</strong> Please wait while the data is being populated. This page will refresh when complete.";
    document.getElementById("populate-form").appendChild(alert);
});
</script>';

echo $OUTPUT->footer(); 