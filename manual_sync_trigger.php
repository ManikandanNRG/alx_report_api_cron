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
 * Manual sync trigger for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Check permissions.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page context
$context = context_system::instance();
$PAGE->set_context($context);

// Check session key.
require_sesskey();

$PAGE->set_url('/local/alx_report_api/manual_sync_trigger.php');
$PAGE->set_title('Manual Sync Trigger - ALX Report API');
$PAGE->set_heading('Manual Sync Trigger');

echo $OUTPUT->header();

echo '<div class="container-fluid">';
echo '<div class="row">';
echo '<div class="col-12">';

echo '<div class="alert alert-info">';
echo '<h4>🚀 Manual Sync Triggered</h4>';
echo '<p>Starting the sync process now. This page will show real-time progress...</p>';
echo '</div>';

// Flush output to show progress
if (ob_get_level()) {
    ob_end_flush();
}
flush();

try {
    // Get the task class
    $task = new \local_alx_report_api\task\sync_reporting_data_task();
    
    echo '<div class="card">';
    echo '<div class="card-header bg-primary text-white">';
    echo '<h5>📊 Sync Progress</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    echo '<div class="progress mb-3">';
    echo '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 25%">Starting...</div>';
    echo '</div>';
    
    $start_time = time();
    echo '<p><strong>Start Time:</strong> ' . userdate($start_time) . '</p>';
    
    // Capture output
    ob_start();
    
    // Execute the task
    $task->execute();
    
    // Get the output
    $output = ob_get_clean();
    
    $end_time = time();
    $duration = $end_time - $start_time;
    
    echo '<div class="progress mb-3">';
    echo '<div class="progress-bar bg-success" role="progressbar" style="width: 100%">Completed!</div>';
    echo '</div>';
    
    echo '<p><strong>End Time:</strong> ' . userdate($end_time) . '</p>';
    echo '<p><strong>Duration:</strong> ' . $duration . ' seconds</p>';
    
    echo '<div class="alert alert-success">';
    echo '<h5>✅ Sync Completed Successfully</h5>';
    echo '<p>The manual sync has completed. Check the output below for details.</p>';
    echo '</div>';
    
    if ($output) {
        echo '<h6>Sync Output:</h6>';
        echo '<pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;">';
        echo htmlspecialchars($output);
        echo '</pre>';
    }
    
    echo '</div>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">';
    echo '<h5>❌ Sync Failed</h5>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '</div>';
    
    if ($e->getTraceAsString()) {
        echo '<h6>Stack Trace:</h6>';
        echo '<pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;">';
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    }
}

echo '<div class="mt-4">';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php" class="btn btn-primary">🔙 Back to Auto-Sync Status</a>';
echo '<a href="' . $CFG->wwwroot . '/local/alx_report_api/sync_reporting_data.php" class="btn btn-secondary ml-2">🔄 Manual Sync Options</a>';
echo '</div>';

echo '</div>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer(); 