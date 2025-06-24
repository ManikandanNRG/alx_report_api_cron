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
 * Scheduled task for syncing reporting data from main database.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_alx_report_api\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/alx_report_api/lib.php');

/**
 * Scheduled task to sync reporting data incrementally.
 */
class sync_reporting_data_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown in admin screens).
     *
     * @return string
     */
    public function get_name() {
        return get_string('sync_reporting_data_task', 'local_alx_report_api');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $start_time = time();
        $this->log_message("=== ALX Report API Incremental Sync Started ===");
        
        // Get sync configuration
        $sync_hours = get_config('local_alx_report_api', 'auto_sync_hours') ?: 1;
        $max_execution_time = get_config('local_alx_report_api', 'max_sync_time') ?: 300; // 5 minutes default
        
        $this->log_message("Sync configuration: {$sync_hours} hours back, max execution time: {$max_execution_time} seconds");
        
        // Set execution time limit
        set_time_limit($max_execution_time);
        
        $total_stats = [
            'companies_processed' => 0,
            'total_users_updated' => 0,
            'total_records_updated' => 0,
            'total_records_created' => 0,
            'total_errors' => 0,
            'companies_with_errors' => []
        ];

        try {
            // Get all companies with API access
            $companies = local_alx_report_api_get_companies();
            
            if (empty($companies)) {
                $this->log_message("No companies found for sync");
                return;
            }
            
            $this->log_message("Found " . count($companies) . " companies to process");
            
            foreach ($companies as $company) {
                if (time() - $start_time > $max_execution_time - 30) {
                    $this->log_message("Approaching execution time limit, stopping sync");
                    break;
                }
                
                $company_start = time();
                $this->log_message("Processing company: {$company->name} (ID: {$company->id})");
                
                try {
                    // Check if company has API settings (indicates they use the API)
                    $has_settings = $DB->record_exists('local_alx_api_settings', ['companyid' => $company->id]);
                    
                    if (!$has_settings) {
                        $this->log_message("Company {$company->id} has no API settings, skipping");
                        continue;
                    }
                    
                    // Run incremental sync for this company
                    $company_stats = $this->sync_company_changes($company->id, $sync_hours);
                    
                    $total_stats['companies_processed']++;
                    $total_stats['total_users_updated'] += $company_stats['users_updated'];
                    $total_stats['total_records_updated'] += $company_stats['records_updated'];
                    $total_stats['total_records_created'] += $company_stats['records_created'];
                    
                    if (!empty($company_stats['errors'])) {
                        $total_stats['total_errors'] += count($company_stats['errors']);
                        $total_stats['companies_with_errors'][] = $company->id;
                        
                        foreach ($company_stats['errors'] as $error) {
                            $this->log_message("Company {$company->id} error: {$error}");
                        }
                    }
                    
                    $company_duration = time() - $company_start;
                    $this->log_message("Company {$company->id} completed in {$company_duration}s: " .
                        "{$company_stats['users_updated']} users, {$company_stats['records_updated']} records updated");
                    
                    // Clear cache entries for this company to ensure fresh data
                    $this->clear_company_cache($company->id);
                    
                } catch (Exception $e) {
                    $total_stats['total_errors']++;
                    $total_stats['companies_with_errors'][] = $company->id;
                    $this->log_message("Company {$company->id} failed: " . $e->getMessage());
                }
            }
            
            // Log final statistics
            $total_duration = time() - $start_time;
            $this->log_message("=== Sync Completed in {$total_duration} seconds ===");
            $this->log_message("Companies processed: {$total_stats['companies_processed']}");
            $this->log_message("Total users updated: {$total_stats['total_users_updated']}");
            $this->log_message("Total records updated: {$total_stats['total_records_updated']}");
            $this->log_message("Total records created: {$total_stats['total_records_created']}");
            
            if ($total_stats['total_errors'] > 0) {
                $this->log_message("Total errors: {$total_stats['total_errors']}");
                $this->log_message("Companies with errors: " . implode(', ', $total_stats['companies_with_errors']));
            }
            
            // Update last sync timestamp
            set_config('last_auto_sync', time(), 'local_alx_report_api');
            set_config('last_sync_stats', json_encode($total_stats), 'local_alx_report_api');
            
        } catch (Exception $e) {
            $this->log_message("Critical sync error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync changes for a specific company.
     *
     * @param int $companyid Company ID
     * @param int $hours_back Hours to look back for changes
     * @return array Statistics
     */
    private function sync_company_changes($companyid, $hours_back) {
        global $DB;
        
        $cutoff_time = time() - ($hours_back * 3600);
        $stats = [
            'users_updated' => 0,
            'records_updated' => 0,
            'records_created' => 0,
            'errors' => []
        ];
        
        try {
            // Find users with recent course completion changes
            $completion_sql = "
                SELECT DISTINCT cc.userid, cu.companyid, cc.course as courseid
                FROM {course_completions} cc
                JOIN {company_users} cu ON cu.userid = cc.userid
                WHERE cc.timemodified > :cutoff_time
                  AND cu.companyid = :companyid";
            
            $params = ['cutoff_time' => $cutoff_time, 'companyid' => $companyid];
            $completion_changes = $DB->get_records_sql($completion_sql, $params);
            
            // Find users with recent module completion changes
            $module_sql = "
                SELECT DISTINCT cmc.userid, cu.companyid, cm.course as courseid
                FROM {course_modules_completion} cmc
                JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                JOIN {company_users} cu ON cu.userid = cmc.userid
                WHERE cmc.timemodified > :cutoff_time
                  AND cu.companyid = :companyid";
            
            $module_changes = $DB->get_records_sql($module_sql, $params);
            
            // Find users with recent enrollment changes
            $enrollment_sql = "
                SELECT DISTINCT ue.userid, cu.companyid, e.courseid
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {company_users} cu ON cu.userid = ue.userid
                WHERE ue.timemodified > :cutoff_time
                  AND cu.companyid = :companyid";
            
            $enrollment_changes = $DB->get_records_sql($enrollment_sql, $params);
            
            // Combine all changes
            $all_changes = [];
            foreach ([$completion_changes, $module_changes, $enrollment_changes] as $changes) {
                foreach ($changes as $change) {
                    $key = $change->userid . '_' . $change->companyid . '_' . $change->courseid;
                    $all_changes[$key] = $change;
                }
            }
            
            // Process each change
            foreach ($all_changes as $change) {
                if (local_alx_report_api_update_reporting_record($change->userid, $change->companyid, $change->courseid)) {
                    $stats['records_updated']++;
                }
            }
            
            $stats['users_updated'] = count($all_changes);
            
        } catch (Exception $e) {
            $stats['errors'][] = 'Company sync error: ' . $e->getMessage();
        }
        
        return $stats;
    }

    /**
     * Clear cache entries for a company to ensure fresh data.
     *
     * @param int $companyid Company ID
     */
    private function clear_company_cache($companyid) {
        global $DB;
        
        try {
            $deleted = $DB->delete_records('local_alx_api_cache', ['companyid' => $companyid]);
            if ($deleted > 0) {
                $this->log_message("Cleared {$deleted} cache entries for company {$companyid}");
            }
        } catch (Exception $e) {
            $this->log_message("Failed to clear cache for company {$companyid}: " . $e->getMessage());
        }
    }

    /**
     * Log a message with timestamp.
     *
     * @param string $message Message to log
     */
    private function log_message($message) {
        $timestamp = date('Y-m-d H:i:s');
        mtrace("[{$timestamp}] ALX Sync: {$message}");
    }
}