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
 * Library functions for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the settings navigation with the ALX Report API settings
 *
 * This function is called when the settings navigation is being built.
 *
 * @param settings_navigation $settingsnav The settings navigation
 * @param navigation_node $context The context node
 */
function local_alx_report_api_extend_settings_navigation($settingsnav, $context) {
    // This function is intentionally left empty but prevents navigation conflicts
    // The plugin settings are already added via settings.php
    return;
}

/**
 * Get company information for API access logging and validation.
 *
 * @param int $companyid Company ID
 * @return object|false Company object or false if not found
 */
function local_alx_report_api_get_company_info($companyid) {
    global $DB;

    if ($DB->get_manager()->table_exists('company')) {
        return $DB->get_record('company', ['id' => $companyid], 'id, name, shortname');
    }

    return false;
}

/**
 * Check if a user has API access permissions.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @return bool True if user has access, false otherwise
 */
function local_alx_report_api_has_api_access($userid, $companyid) {
    global $DB;

    // Check if user belongs to the company.
    if ($DB->get_manager()->table_exists('company_users')) {
        $company_user = $DB->get_record('company_users', [
            'userid' => $userid,
            'companyid' => $companyid,
        ]);
        
        return !empty($company_user);
    }

    return false;
}

/**
 * Validate API token and get associated user and company.
 *
 * @param string $token API token
 * @return array|false Array with userid and companyid or false if invalid
 */
function local_alx_report_api_validate_token($token) {
    global $DB;

    // Get external token record.
    $tokenrecord = $DB->get_record('external_tokens', [
        'token' => $token,
        'tokentype' => EXTERNAL_TOKEN_PERMANENT,
    ]);

    if (!$tokenrecord) {
        return false;
    }

    // Check if token is for our service.
    $service = $DB->get_record('external_services', [
        'id' => $tokenrecord->externalserviceid,
        'shortname' => 'alx_report_api',
    ]);

    if (!$service) {
        return false;
    }

    // Get user's company.
    if ($DB->get_manager()->table_exists('company_users')) {
        $company_user = $DB->get_record('company_users', [
            'userid' => $tokenrecord->userid,
        ]);

        if ($company_user) {
            return [
                'userid' => $tokenrecord->userid,
                'companyid' => $company_user->companyid,
            ];
        }
    }

    return false;
}

/**
 * Clean up old API logs (for maintenance).
 *
 * @param int $days Number of days to keep logs (default: 90)
 * @return int Number of records deleted
 */
function local_alx_report_api_cleanup_logs($days = 90) {
    global $DB;

    $cutoff = time() - ($days * 24 * 60 * 60);
    
    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        return $DB->delete_records_select('local_alx_api_logs', 'timecreated < ?', [$cutoff]);
    }

    return 0;
}

/**
 * Get API usage statistics for a company.
 *
 * @param int $companyid Company ID
 * @param int $days Number of days to look back (default: 30)
 * @return array Usage statistics
 */
function local_alx_report_api_get_usage_stats($companyid, $days = 30) {
    global $DB;

    $cutoff = time() - ($days * 24 * 60 * 60);
    $stats = [
        'total_requests' => 0,
        'unique_users' => 0,
        'last_access' => 0,
    ];

    if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
        // Total requests.
        $stats['total_requests'] = $DB->count_records_select(
            'local_alx_api_logs',
            'companyid = ? AND timecreated > ?',
            [$companyid, $cutoff]
        );

        // Unique users.
        $sql = "SELECT COUNT(DISTINCT userid) 
                FROM {local_alx_api_logs} 
                WHERE companyid = ? AND timecreated > ?";
        $stats['unique_users'] = $DB->count_records_sql($sql, [$companyid, $cutoff]);

        // Last access.
        $last_access = $DB->get_field_select(
            'local_alx_api_logs',
            'MAX(timecreated)',
            'companyid = ?',
            [$companyid]
        );
        $stats['last_access'] = $last_access ?: 0;
    }

    return $stats;
}

/**
 * Get all companies available for API configuration.
 *
 * @return array Array of company objects
 */
function local_alx_report_api_get_companies() {
    global $DB;
    
    if ($DB->get_manager()->table_exists('company')) {
        return $DB->get_records('company', null, 'name ASC', 'id, name, shortname');
    }
    
    return [];
}

/**
 * Get company-specific setting value.
 *
 * @param int $companyid Company ID
 * @param string $setting_name Setting name (e.g., 'field_email', 'course_10')
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed Setting value
 */
function local_alx_report_api_get_company_setting($companyid, $setting_name, $default = 0) {
    global $DB;
    
    $setting = $DB->get_record('local_alx_api_settings', [
        'companyid' => $companyid,
        'setting_name' => $setting_name
    ]);
    
    return $setting ? $setting->setting_value : $default;
}

/**
 * Set company-specific setting value.
 *
 * @param int $companyid Company ID
 * @param string $setting_name Setting name
 * @param mixed $setting_value Setting value
 * @return bool True on success
 */
function local_alx_report_api_set_company_setting($companyid, $setting_name, $setting_value) {
    global $DB;
    
    $existing = $DB->get_record('local_alx_api_settings', [
        'companyid' => $companyid,
        'setting_name' => $setting_name
    ]);
    
    $time = time();
    
    if ($existing) {
        // Update existing setting
        $existing->setting_value = $setting_value;
        $existing->timemodified = $time;
        return $DB->update_record('local_alx_api_settings', $existing);
    } else {
        // Create new setting
        $setting = new stdClass();
        $setting->companyid = $companyid;
        $setting->setting_name = $setting_name;
        $setting->setting_value = $setting_value;
        $setting->timecreated = $time;
        $setting->timemodified = $time;
        return $DB->insert_record('local_alx_api_settings', $setting);
    }
}

/**
 * Get all settings for a specific company.
 *
 * @param int $companyid Company ID
 * @return array Array of settings keyed by setting name
 */
function local_alx_report_api_get_company_settings($companyid) {
    global $DB;
    
    $settings = $DB->get_records('local_alx_api_settings', 
        ['companyid' => $companyid], '', 'setting_name, setting_value');
    
    $result = [];
    foreach ($settings as $setting) {
        $result[$setting->setting_name] = $setting->setting_value;
    }
    
    return $result;
}

/**
 * Copy settings from one company to another (or from global defaults).
 *
 * @param int $from_companyid Source company ID (0 for global defaults)
 * @param int $to_companyid Target company ID
 * @return bool True on success
 */
function local_alx_report_api_copy_company_settings($from_companyid, $to_companyid) {
    global $DB;
    
    if ($from_companyid == 0) {
        // Copy from global defaults
        $global_settings = [
            'field_userid' => get_config('local_alx_report_api', 'field_userid') ?: 1,
            'field_firstname' => get_config('local_alx_report_api', 'field_firstname') ?: 1,
            'field_lastname' => get_config('local_alx_report_api', 'field_lastname') ?: 1,
            'field_email' => get_config('local_alx_report_api', 'field_email') ?: 1,
            'field_courseid' => get_config('local_alx_report_api', 'field_courseid') ?: 1,
            'field_coursename' => get_config('local_alx_report_api', 'field_coursename') ?: 1,
            'field_timecompleted' => get_config('local_alx_report_api', 'field_timecompleted') ?: 1,
            'field_timecompleted_unix' => get_config('local_alx_report_api', 'field_timecompleted_unix') ?: 1,
            'field_timestarted' => get_config('local_alx_report_api', 'field_timestarted') ?: 1,
            'field_timestarted_unix' => get_config('local_alx_report_api', 'field_timestarted_unix') ?: 1,
            'field_percentage' => get_config('local_alx_report_api', 'field_percentage') ?: 1,
            'field_status' => get_config('local_alx_report_api', 'field_status') ?: 1,
        ];
        
        // Copy field settings
        foreach ($global_settings as $setting_name => $setting_value) {
            local_alx_report_api_set_company_setting($to_companyid, $setting_name, $setting_value);
        }
        
        // Copy course settings (enable all courses by default)
        $company_courses = local_alx_report_api_get_company_courses($to_companyid);
        foreach ($company_courses as $course) {
            $course_setting = 'course_' . $course->id;
            local_alx_report_api_set_company_setting($to_companyid, $course_setting, 1);
        }
    } else {
        // Copy from another company
        $source_settings = local_alx_report_api_get_company_settings($from_companyid);
        foreach ($source_settings as $setting_name => $setting_value) {
            local_alx_report_api_set_company_setting($to_companyid, $setting_name, $setting_value);
        }
    }
    
    return true;
}

/**
 * Get all courses available to a specific company.
 *
 * @param int $companyid Company ID
 * @return array Array of course objects
 */
function local_alx_report_api_get_company_courses($companyid) {
    global $DB;
    
    if (!$DB->get_manager()->table_exists('company_course')) {
        return [];
    }
    
    $sql = "SELECT c.id, c.fullname, c.shortname, c.visible
            FROM {course} c
            JOIN {company_course} cc ON cc.courseid = c.id
            WHERE cc.companyid = :companyid
                AND c.visible = 1
                AND c.id != 1
            ORDER BY c.fullname ASC";
    
    return $DB->get_records_sql($sql, ['companyid' => $companyid]);
}

/**
 * Get enabled courses for a company based on settings.
 *
 * @param int $companyid Company ID
 * @return array Array of enabled course IDs
 */
function local_alx_report_api_get_enabled_courses($companyid) {
    global $DB;
    
    $enabled_courses = [];
    $company_settings = local_alx_report_api_get_company_settings($companyid);
    
    foreach ($company_settings as $setting_name => $setting_value) {
        if (strpos($setting_name, 'course_') === 0 && $setting_value == 1) {
            $course_id = (int)str_replace('course_', '', $setting_name);
            if ($course_id > 0) {
                $enabled_courses[] = $course_id;
            }
        }
    }
    
    return $enabled_courses;
}

/**
 * Check if a course is enabled for a company.
 *
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True if enabled, false otherwise
 */
function local_alx_report_api_is_course_enabled($companyid, $courseid) {
    $setting_name = 'course_' . $courseid;
    return local_alx_report_api_get_company_setting($companyid, $setting_name, 1) == 1;
}

// ===================================================================
// COMBINED APPROACH: REPORTING TABLE & INCREMENTAL SYNC FUNCTIONS
// ===================================================================

/**
 * Populate the reporting table with initial data from main database.
 * This is run once during setup to create the baseline reporting data.
 *
 * @param int $companyid Specific company ID (0 for all companies)
 * @param int $batch_size Number of records to process per batch
 * @return array Status information with counts and timing
 */
function local_alx_report_api_populate_reporting_table($companyid = 0, $batch_size = 1000) {
    global $DB;
    
    $start_time = time();
    $total_processed = 0;
    $total_inserted = 0;
    $errors = [];
    
    try {
        // Get companies to process
        if ($companyid > 0) {
            $companies = [$DB->get_record('company', ['id' => $companyid])];
        } else {
            $companies = $DB->get_records('company', null, 'id ASC');
        }
        
        foreach ($companies as $company) {
            if (!$company) continue;
            
            // Get enabled courses for this company
            $enabled_courses = local_alx_report_api_get_enabled_courses($company->id);
            if (empty($enabled_courses)) {
                // If no courses enabled, enable all company courses
                $company_courses = local_alx_report_api_get_company_courses($company->id);
                $enabled_courses = array_column($company_courses, 'id');
            }
            
            if (empty($enabled_courses)) {
                continue; // Skip if no courses available
            }
            
            // Build the complex query to get all user-course data
            list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
            
            $sql = "
                SELECT DISTINCT
                    u.id as userid,
                    u.firstname,
                    u.lastname,
                    u.email,
                    c.id as courseid,
                    c.fullname as coursename,
                    COALESCE(cc.timecompleted, 
                        (SELECT MAX(cmc.timemodified) 
                         FROM {course_modules_completion} cmc
                         JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                         WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1), 0) as timecompleted,
                    COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
                    COALESCE(
                        CASE 
                            WHEN cc.timecompleted > 0 THEN 100.0
                            ELSE COALESCE(
                                (SELECT AVG(CASE WHEN cmc.completionstate = 1 THEN 100.0 ELSE 0.0 END)
                                 FROM {course_modules_completion} cmc
                                 JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                                 WHERE cm.course = c.id AND cmc.userid = u.id), 0.0)
                        END, 0.0) as percentage,
                    CASE 
                        WHEN cc.timecompleted > 0 THEN 'completed'
                        WHEN EXISTS(
                            SELECT 1 FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
                        ) THEN 'completed'
                        WHEN EXISTS(
                            SELECT 1 FROM {course_modules_completion} cmc
                            JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                            WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
                        ) THEN 'in_progress'
                        WHEN ue.id IS NOT NULL THEN 'not_started'
                        ELSE 'not_enrolled'
                    END as status
                FROM {user} u
                JOIN {company_users} cu ON cu.userid = u.id
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                WHERE cu.companyid = :companyid
                    AND u.deleted = 0
                    AND u.suspended = 0
                    AND c.visible = 1
                    AND c.id $course_sql
                    AND ue.status = 0
                ORDER BY u.id, c.id";
            
            $params = array_merge(['companyid' => $company->id], $course_params);
            
            // Process in batches
            $offset = 0;
            while (true) {
                $records = $DB->get_records_sql($sql, $params, $offset, $batch_size);
                if (empty($records)) {
                    break;
                }
                
                $batch_inserted = 0;
                $current_time = time();
                
                foreach ($records as $record) {
                    // Check if record already exists
                    $existing = $DB->get_record('local_alx_api_reporting', [
                        'userid' => $record->userid,
                        'courseid' => $record->courseid,
                        'companyid' => $company->id
                    ]);
                    
                    if (!$existing) {
                        // Insert new record
                        $reporting_record = new stdClass();
                        $reporting_record->userid = $record->userid;
                        $reporting_record->companyid = $company->id;
                        $reporting_record->courseid = $record->courseid;
                        $reporting_record->firstname = $record->firstname;
                        $reporting_record->lastname = $record->lastname;
                        $reporting_record->email = $record->email;
                        $reporting_record->coursename = $record->coursename;
                        $reporting_record->timecompleted = $record->timecompleted;
                        $reporting_record->timestarted = $record->timestarted;
                        $reporting_record->percentage = $record->percentage;
                        $reporting_record->status = $record->status;
                        $reporting_record->last_updated = $current_time;
                        $reporting_record->is_deleted = 0;
                        $reporting_record->created_at = $current_time;
                        $reporting_record->updated_at = $current_time;
                        
                        $DB->insert_record('local_alx_api_reporting', $reporting_record);
                        $batch_inserted++;
                    }
                }
                
                $total_processed += count($records);
                $total_inserted += $batch_inserted;
                $offset += $batch_size;
                
                // Break if we got fewer records than batch size (end of data)
                if (count($records) < $batch_size) {
                    break;
                }
            }
        }
        
    } catch (Exception $e) {
        $errors[] = 'Population error: ' . $e->getMessage();
    }
    
    $end_time = time();
    $duration = $end_time - $start_time;
    
    return [
        'success' => empty($errors),
        'total_processed' => $total_processed,
        'total_inserted' => $total_inserted,
        'duration_seconds' => $duration,
        'errors' => $errors,
        'companies_processed' => count($companies ?? [])
    ];
}

/**
 * Update a single record in the reporting table.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True on success
 */
function local_alx_report_api_update_reporting_record($userid, $companyid, $courseid) {
    global $DB;
    
    try {
        // Get fresh data from main database
        $sql = "
            SELECT DISTINCT
                u.id as userid,
                u.firstname,
                u.lastname,
                u.email,
                c.id as courseid,
                c.fullname as coursename,
                COALESCE(cc.timecompleted, 
                    (SELECT MAX(cmc.timemodified) 
                     FROM {course_modules_completion} cmc
                     JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                     WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1), 0) as timecompleted,
                COALESCE(cc.timestarted, ue.timecreated, 0) as timestarted,
                COALESCE(
                    CASE 
                        WHEN cc.timecompleted > 0 THEN 100.0
                        ELSE COALESCE(
                            (SELECT AVG(CASE WHEN cmc.completionstate = 1 THEN 100.0 ELSE 0.0 END)
                             FROM {course_modules_completion} cmc
                             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                             WHERE cm.course = c.id AND cmc.userid = u.id), 0.0)
                    END, 0.0) as percentage,
                CASE 
                    WHEN cc.timecompleted > 0 THEN 'completed'
                    WHEN EXISTS(
                        SELECT 1 FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
                    ) THEN 'completed'
                    WHEN EXISTS(
                        SELECT 1 FROM {course_modules_completion} cmc
                        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0
                    ) THEN 'in_progress'
                    WHEN ue.id IS NOT NULL THEN 'not_started'
                    ELSE 'not_enrolled'
                END as status
            FROM {user} u
            JOIN {company_users} cu ON cu.userid = u.id
            LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
            LEFT JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
            JOIN {course} c ON c.id = :courseid2
            LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
            WHERE u.id = :userid
                AND cu.companyid = :companyid
                AND u.deleted = 0
                AND u.suspended = 0
                AND c.visible = 1";
        
        $params = [
            'userid' => $userid,
            'companyid' => $companyid,
            'courseid' => $courseid,
            'courseid2' => $courseid
        ];
        
        $record = $DB->get_record_sql($sql, $params);
        
        if (!$record) {
            // User not found or not enrolled, mark as deleted
            return local_alx_report_api_soft_delete_reporting_record($userid, $companyid, $courseid);
        }
        
        // Check if reporting record exists
        $existing = $DB->get_record('local_alx_api_reporting', [
            'userid' => $userid,
            'courseid' => $courseid,
            'companyid' => $companyid
        ]);
        
        $current_time = time();
        
        if ($existing) {
            // Update existing record
            $existing->firstname = $record->firstname;
            $existing->lastname = $record->lastname;
            $existing->email = $record->email;
            $existing->coursename = $record->coursename;
            $existing->timecompleted = $record->timecompleted;
            $existing->timestarted = $record->timestarted;
            $existing->percentage = $record->percentage;
            $existing->status = $record->status;
            $existing->last_updated = $current_time;
            $existing->is_deleted = 0;
            $existing->updated_at = $current_time;
            
            return $DB->update_record('local_alx_api_reporting', $existing);
        } else {
            // Insert new record
            $reporting_record = new stdClass();
            $reporting_record->userid = $record->userid;
            $reporting_record->companyid = $companyid;
            $reporting_record->courseid = $record->courseid;
            $reporting_record->firstname = $record->firstname;
            $reporting_record->lastname = $record->lastname;
            $reporting_record->email = $record->email;
            $reporting_record->coursename = $record->coursename;
            $reporting_record->timecompleted = $record->timecompleted;
            $reporting_record->timestarted = $record->timestarted;
            $reporting_record->percentage = $record->percentage;
            $reporting_record->status = $record->status;
            $reporting_record->last_updated = $current_time;
            $reporting_record->is_deleted = 0;
            $reporting_record->created_at = $current_time;
            $reporting_record->updated_at = $current_time;
            
            return $DB->insert_record('local_alx_api_reporting', $reporting_record);
        }
        
    } catch (Exception $e) {
        debugging('Error updating reporting record: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Soft delete a reporting record (mark as deleted instead of removing).
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @param int $courseid Course ID
 * @return bool True on success
 */
function local_alx_report_api_soft_delete_reporting_record($userid, $companyid, $courseid) {
    global $DB;
    
    $existing = $DB->get_record('local_alx_api_reporting', [
        'userid' => $userid,
        'courseid' => $courseid,
        'companyid' => $companyid
    ]);
    
    if ($existing) {
        $existing->is_deleted = 1;
        $existing->last_updated = time();
        $existing->updated_at = time();
        return $DB->update_record('local_alx_api_reporting', $existing);
    }
    
    return true; // Already doesn't exist
}

/**
 * Sync user data across all their courses for a specific company.
 *
 * @param int $userid User ID
 * @param int $companyid Company ID
 * @return int Number of records updated
 */
function local_alx_report_api_sync_user_data($userid, $companyid) {
    global $DB;
    
    $updated_count = 0;
    
    // Get all courses for this company
    $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
    
    foreach ($enabled_courses as $courseid) {
        if (local_alx_report_api_update_reporting_record($userid, $companyid, $courseid)) {
            $updated_count++;
        }
    }
    
    return $updated_count;
}

/**
 * Get sync status for a company and token combination.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @return object|false Sync status object or false if not found
 */
function local_alx_report_api_get_sync_status($companyid, $token) {
    global $DB;
    
    $token_hash = hash('sha256', $token);
    
    return $DB->get_record('local_alx_api_sync_status', [
        'companyid' => $companyid,
        'token_hash' => $token_hash
    ]);
}

/**
 * Update sync status after an API call.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @param int $records_count Number of records returned
 * @param string $status Sync status (success/failed)
 * @param string $error_message Error message if failed
 * @return bool True on success
 */
function local_alx_report_api_update_sync_status($companyid, $token, $records_count, $status = 'success', $error_message = null) {
    global $DB;
    
    $token_hash = hash('sha256', $token);
    $current_time = time();
    
    $existing = $DB->get_record('local_alx_api_sync_status', [
        'companyid' => $companyid,
        'token_hash' => $token_hash
    ]);
    
    if ($existing) {
        // Update existing record
        $existing->last_sync_timestamp = $current_time;
        $existing->last_sync_records = $records_count;
        $existing->last_sync_status = $status;
        $existing->last_sync_error = $error_message;
        $existing->total_syncs = $existing->total_syncs + 1;
        $existing->updated_at = $current_time;
        
        return $DB->update_record('local_alx_api_sync_status', $existing);
    } else {
        // Create new record
        $sync_status = new stdClass();
        $sync_status->companyid = $companyid;
        $sync_status->token_hash = $token_hash;
        $sync_status->last_sync_timestamp = $current_time;
        $sync_status->sync_mode = 'auto';
        $sync_status->sync_window_hours = 24;
        $sync_status->last_sync_records = $records_count;
        $sync_status->last_sync_status = $status;
        $sync_status->last_sync_error = $error_message;
        $sync_status->total_syncs = 1;
        $sync_status->created_at = $current_time;
        $sync_status->updated_at = $current_time;
        
        return $DB->insert_record('local_alx_api_sync_status', $sync_status);
    }
}

/**
 * Determine sync mode for a company/token combination.
 *
 * @param int $companyid Company ID
 * @param string $token API token
 * @return string Sync mode: 'full', 'incremental', or 'first'
 */
function local_alx_report_api_determine_sync_mode($companyid, $token) {
    $sync_status = local_alx_report_api_get_sync_status($companyid, $token);
    
    if (!$sync_status) {
        return 'first'; // First time sync
    }
    
    if ($sync_status->sync_mode === 'disabled') {
        return 'full'; // Always full sync if disabled
    }
    
    if ($sync_status->last_sync_status === 'failed') {
        return 'full'; // Full sync after failure
    }
    
    // Check if last sync was too long ago
    $sync_window_seconds = $sync_status->sync_window_hours * 3600;
    $time_since_last_sync = time() - $sync_status->last_sync_timestamp;
    
    if ($time_since_last_sync > $sync_window_seconds) {
        return 'full'; // Full sync if too much time passed
    }
    
    return 'incremental'; // Normal incremental sync
}

/**
 * Get cached data.
 *
 * @param string $cache_key Cache key
 * @param int $companyid Company ID
 * @return mixed Cached data or false if not found/expired
 */
function local_alx_report_api_cache_get($cache_key, $companyid) {
    global $DB;
    
    $cache_record = $DB->get_record('local_alx_api_cache', [
        'cache_key' => $cache_key,
        'companyid' => $companyid
    ]);
    
    if (!$cache_record) {
        return false;
    }
    
    // Check if expired
    if ($cache_record->expires_at < time()) {
        // Delete expired cache
        $DB->delete_records('local_alx_api_cache', ['id' => $cache_record->id]);
        return false;
    }
    
    // Update hit count and last accessed
    $cache_record->hit_count++;
    $cache_record->last_accessed = time();
    $DB->update_record('local_alx_api_cache', $cache_record);
    
    return json_decode($cache_record->cache_data, true);
}

/**
 * Set cached data.
 *
 * @param string $cache_key Cache key
 * @param int $companyid Company ID
 * @param mixed $data Data to cache
 * @param int $ttl Time to live in seconds (default: 1 hour)
 * @return bool True on success
 */
function local_alx_report_api_cache_set($cache_key, $companyid, $data, $ttl = 3600) {
    global $DB;
    
    $current_time = time();
    $expires_at = $current_time + $ttl;
    
    $existing = $DB->get_record('local_alx_api_cache', [
        'cache_key' => $cache_key,
        'companyid' => $companyid
    ]);
    
    if ($existing) {
        // Update existing cache
        $existing->cache_data = json_encode($data);
        $existing->cache_timestamp = $current_time;
        $existing->expires_at = $expires_at;
        $existing->last_accessed = $current_time;
        
        return $DB->update_record('local_alx_api_cache', $existing);
    } else {
        // Create new cache entry
        $cache_record = new stdClass();
        $cache_record->cache_key = $cache_key;
        $cache_record->companyid = $companyid;
        $cache_record->cache_data = json_encode($data);
        $cache_record->cache_timestamp = $current_time;
        $cache_record->expires_at = $expires_at;
        $cache_record->hit_count = 0;
        $cache_record->last_accessed = $current_time;
        
        return $DB->insert_record('local_alx_api_cache', $cache_record);
    }
}

/**
 * Clean up expired cache entries.
 *
 * @param int $max_age_hours Maximum age in hours (default: 24)
 * @return int Number of entries cleaned up
 */
function local_alx_report_api_cache_cleanup($max_age_hours = 24) {
    global $DB;
    
    $cutoff_time = time() - ($max_age_hours * 3600);
    
    return $DB->delete_records_select('local_alx_api_cache', 'expires_at < ?', [$cutoff_time]);
}

/**
 * Get reporting table statistics.
 *
 * @param int $companyid Company ID (0 for all companies)
 * @return array Statistics array
 */
function local_alx_report_api_get_reporting_stats($companyid = 0) {
    global $DB;
    
    $stats = [];
    
    if ($companyid > 0) {
        $where = 'companyid = ?';
        $params = [$companyid];
    } else {
        $where = '1=1';
        $params = [];
    }
    
    // Total records
    $stats['total_records'] = $DB->count_records_select('local_alx_api_reporting', $where, $params);
    
    // Active records (not deleted)
    $stats['active_records'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND is_deleted = 0', $params);
    
    // Deleted records
    $stats['deleted_records'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND is_deleted = 1', $params);
    
    // Completed courses
    $stats['completed_courses'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND status = ? AND is_deleted = 0', array_merge($params, ['completed']));
    
    // In progress courses
    $stats['in_progress_courses'] = $DB->count_records_select('local_alx_api_reporting', 
        $where . ' AND status = ? AND is_deleted = 0', array_merge($params, ['in_progress']));
    
    // Last update time
    $last_update = $DB->get_field_select('local_alx_api_reporting', 'MAX(last_updated)', $where, $params);
    $stats['last_update'] = $last_update ?: 0;
    
    return $stats;
} 
