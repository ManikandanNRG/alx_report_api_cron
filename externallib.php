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
 * External API for ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/lib.php');

/**
 * External functions for the ALX Report API plugin.
 */
class local_alx_report_api_external extends external_api {

    /**
     * Validate HTTP method and security headers.
     *
     * @throws moodle_exception If validation fails
     */
    private static function validate_request_security() {
        // 1. Check if GET method is allowed (for development/testing)
        $allow_get_method = get_config('local_alx_report_api', 'allow_get_method');
        
        if (!$allow_get_method && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new moodle_exception('invalidrequestmethod', 'local_alx_report_api', '', null, 
                'Only POST method is allowed for security reasons');
        }

        // 2. Validate Content-Type for POST requests
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        $allowed_types = [
            'application/x-www-form-urlencoded',
            'application/json',
            'multipart/form-data'
        ];
        
        $valid_content_type = false;
        foreach ($allowed_types as $type) {
            if (strpos($content_type, $type) === 0) {
                $valid_content_type = true;
                break;
            }
        }
        
        if (!$valid_content_type) {
            throw new moodle_exception('invalidcontenttype', 'local_alx_report_api', '', null, 
                'Invalid Content-Type header');
        }

        // 3. Add security headers to response
        self::add_security_headers();
    }

    /**
     * Extract and validate token from Authorization header.
     *
     * @return string|false Token if valid, false otherwise
     */
    private static function get_authorization_token() {
        // Check for Authorization header
        $headers = getallheaders();
        $auth_header = null;
        
        // Case-insensitive header search
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $auth_header = $value;
                break;
            }
        }
        
        // Fallback: Check $_SERVER for Authorization header
        if (!$auth_header) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Extract Bearer token
        if (preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
            return trim($matches[1]);
        }
        
        return false;
    }

    /**
     * Enhanced token validation with security checks.
     *
     * @param string $token API token
     * @return array Token validation result with user and company info
     * @throws moodle_exception If token is invalid
     */
    private static function validate_secure_token($token) {
        global $DB;
        
        if (empty($token)) {
            throw new moodle_exception('missingtoken', 'local_alx_report_api', '', null, 
                'Authorization token is required');
        }
        
        // 1. Basic token format validation (not empty, reasonable length)
        if (strlen($token) < 16) {
            throw new moodle_exception('invalidtokenformat', 'local_alx_report_api', '', null, 
                'Invalid token format');
        }
        
        // 2. Get token info from Moodle's external tokens table
        $tokenrecord = $DB->get_record('external_tokens', 
            ['token' => $token, 'externalserviceid' => self::get_service_id()]);
            
        if (!$tokenrecord) {
            throw new moodle_exception('invalidtoken', 'local_alx_report_api', '', null, 
                'Invalid or expired token');
        }
        
        // 3. Check if token is active
        if (!$tokenrecord->validuntil || $tokenrecord->validuntil < time()) {
            throw new moodle_exception('expiredtoken', 'local_alx_report_api', '', null, 
                'Token has expired');
        }
        
        // 4. Get user info
        $user = $DB->get_record('user', ['id' => $tokenrecord->userid]);
        if (!$user || $user->deleted || $user->suspended) {
            throw new moodle_exception('invaliduser', 'local_alx_report_api', '', null, 
                'User account is not active');
        }
        
        // 5. Get company association
        $companyid = self::get_user_company($user->id);
        if (!$companyid) {
            throw new moodle_exception('nocompanyassociation', 'local_alx_report_api', '', null, 
                'User is not associated with any company');
        }
        
        // 6. Log successful authentication
        self::log_security_event($user->id, $companyid, 'token_validated', 'success');
        
        return [
            'user' => $user,
            'companyid' => $companyid,
            'token' => $tokenrecord
        ];
    }

    /**
     * Check rate limiting for the user (global daily limit).
     *
     * @param int $userid User ID
     * @throws moodle_exception If rate limit exceeded
     */
    private static function check_rate_limit($userid) {
        global $DB;
        
        // Get rate limit from settings (default 100 requests per day)
        $rate_limit = get_config('local_alx_report_api', 'rate_limit') ?: 100;
        
        // Calculate start of today (midnight)
        $today_start = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
        
        // Count requests from this user today
        $request_count = 0;
        if ($DB->get_manager()->table_exists('local_alx_api_logs')) {
            $request_count = $DB->count_records_select(
                'local_alx_api_logs', 
                'userid = ? AND timecreated >= ?', 
                [$userid, $today_start]
            );
        }
        
        // Check if limit exceeded
        if ($request_count >= $rate_limit) {
            throw new moodle_exception('ratelimitexceeded', 'local_alx_report_api', '', null, 
                "Daily rate limit exceeded. You have made {$request_count} requests today. Limit is {$rate_limit} requests per day. Try again tomorrow.");
        }
    }

    /**
     * Get the service ID for alx_report_api_custom.
     *
     * @return int Service ID
     */
    private static function get_service_id() {
        global $DB;
        
        static $service_id = null;
        if ($service_id === null) {
            $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
            $service_id = $service ? $service->id : 0;
        }
        
        return $service_id;
    }

    /**
     * Add security headers to the response.
     */
    private static function add_security_headers() {
        // Only add essential headers that won't break existing workflows
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS headers for cross-origin requests (like Power BI)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }

    /**
     * Log security events for monitoring.
     *
     * @param int $userid User ID
     * @param int $companyid Company ID
     * @param string $event_type Type of security event
     * @param string $status Status (success, failure, warning)
     * @param string $details Additional details
     */
    private static function log_security_event($userid, $companyid, $event_type, $status, $details = '') {
        global $DB;
        
        if (!$DB->get_manager()->table_exists('local_alx_api_logs')) {
            return;
        }
        
        $log = new stdClass();
        $log->userid = $userid;
        $log->companyid = $companyid;
        $log->endpoint = 'security_' . $event_type;
        $log->request_data = json_encode([
            'event_type' => $event_type,
            'status' => $status,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        $log->response_data = '';
        $log->timecreated = time();
        
        $DB->insert_record('local_alx_api_logs', $log);
    }

    /**
     * Write debug messages to a separate log file in moodledata.
     *
     * @param string $message Debug message to log
     */
    private static function debug_log($message) {
        global $CFG;
        
        $logfile = $CFG->dataroot . '/alx_report_api_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $logentry = "[$timestamp] $message\n";
        
        file_put_contents($logfile, $logentry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_course_progress_parameters() {
        return new external_function_parameters([
            'limit' => new external_value(PARAM_INT, 'Number of records to return (max 1000)', VALUE_DEFAULT, 100),
            'offset' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get course progress data for the authenticated user's company.
     *
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    public static function get_course_progress($limit = 100, $offset = 0) {
        global $DB, $USER;

        // 1. Validate parameters
        $params = self::validate_parameters(self::get_course_progress_parameters(), [
            'limit' => $limit,
            'offset' => $offset
        ]);

        // 2. Validate limit against configured maximum
        $max_records = get_config('local_alx_report_api', 'max_records') ?: 1000;
        if ($params['limit'] > $max_records) {
            throw new moodle_exception('limittoolarge', 'local_alx_report_api', '', $max_records, 
                "Requested limit ({$params['limit']}) exceeds maximum allowed ({$max_records}) records per request.");
        }

        // 3. Get current authenticated user
        if (!$USER || !$USER->id || $USER->id <= 0) {
            throw new moodle_exception('invaliduser', 'local_alx_report_api', '', null, 
                'User must be authenticated to access this service');
        }

        // 4. Check rate limiting (global daily limit)
        self::check_rate_limit($USER->id);

        // 5. Check GET method restriction (if enabled in settings)
        $allow_get_method = get_config('local_alx_report_api', 'allow_get_method');
        if (!$allow_get_method && $_SERVER['REQUEST_METHOD'] === 'GET') {
            throw new moodle_exception('invalidrequestmethod', 'local_alx_report_api', '', null, 
                'GET method is disabled. Only POST method is allowed for security reasons. Enable GET method in plugin settings for development/testing.');
        }

        // 4. Check rate limiting (global daily limit)
        self::check_rate_limit($USER->id);

        // 5. Get company association for the authenticated user
        $companyid = self::get_user_company($USER->id);
        if (!$companyid) {
            throw new moodle_exception('nocompanyassociation', 'local_alx_report_api', '', null, 
                'User is not associated with any company');
        }

        // 6. Log API access
        self::log_api_access($USER->id, $companyid, 'get_course_progress');

        // Get course progress data.
        $progressdata = self::get_company_course_progress($companyid, $params['limit'], $params['offset']);

        return $progressdata;
    }

    /**
     * Returns description of method return value.
     *
     * @return external_multiple_structure
     */
    public static function get_course_progress_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
                'firstname' => new external_value(PARAM_TEXT, 'User first name', VALUE_OPTIONAL),
                'lastname' => new external_value(PARAM_TEXT, 'User last name', VALUE_OPTIONAL),
                'email' => new external_value(PARAM_EMAIL, 'User email', VALUE_OPTIONAL),
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_OPTIONAL),
                'coursename' => new external_value(PARAM_TEXT, 'Course name', VALUE_OPTIONAL),
                'timecompleted' => new external_value(PARAM_TEXT, 'Completion date (Y-m-d H:i:s format)', VALUE_OPTIONAL),
                'timecompleted_unix' => new external_value(PARAM_INT, 'Completion timestamp (Unix format)', VALUE_OPTIONAL),
                'timestarted' => new external_value(PARAM_TEXT, 'Course start date (Y-m-d H:i:s format)', VALUE_OPTIONAL),
                'timestarted_unix' => new external_value(PARAM_INT, 'Course start timestamp (Unix format)', VALUE_OPTIONAL),
                'percentage' => new external_value(PARAM_FLOAT, 'Completion percentage', VALUE_OPTIONAL),
                'status' => new external_value(PARAM_TEXT, 'Completion status (completed, in_progress, not_started)', VALUE_OPTIONAL)
            ])
        );
    }

    /**
     * Get the company ID for a given user.
     *
     * @param int $userid User ID
     * @return int|false Company ID or false if not found
     */
    private static function get_user_company($userid) {
        global $DB;

        // Check if IOMAD is installed and get company association.
        if ($DB->get_manager()->table_exists('company_users')) {
            $company = $DB->get_record('company_users', ['userid' => $userid], 'companyid');
            return $company ? $company->companyid : false;
        }

        // Fallback: If IOMAD is not available, you might need to implement
        // your own company association logic here.
        return false;
    }

    /**
     * Get course progress data for a company using reporting table and incremental sync.
     *
     * @param int $companyid Company ID
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    private static function get_company_course_progress($companyid, $limit, $offset) {
        global $DB;

        // Debug logging
        self::debug_log("=== API Request Start (Combined Approach) ===");
        self::debug_log("Company ID: $companyid, Limit: $limit, Offset: $offset");

        // Get the API token for sync tracking
        $token = self::get_authorization_token();
        $token_hash = hash('sha256', $token);
        
        // Determine sync mode (full, incremental, or first)
        $sync_mode = local_alx_report_api_determine_sync_mode($companyid, $token);
        self::debug_log("Sync mode determined: $sync_mode");
        
        // Check cache first for incremental syncs
        $cache_key = "api_response_{$companyid}_{$limit}_{$offset}_{$sync_mode}";
        if ($sync_mode === 'incremental') {
            $cached_data = local_alx_report_api_cache_get($cache_key, $companyid);
            if ($cached_data !== false) {
                self::debug_log("Cache hit - returning cached data");
                return $cached_data;
            }
        }

        // Get enabled courses for this company
        $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
        self::debug_log("Enabled courses for company $companyid: " . implode(',', $enabled_courses));
        
        // If no courses are enabled, check if any settings exist for this company
        if (empty($enabled_courses)) {
            $existing_settings = local_alx_report_api_get_company_settings($companyid);
            $has_course_settings = false;
            foreach ($existing_settings as $setting_name => $value) {
                if (strpos($setting_name, 'course_') === 0) {
                    $has_course_settings = true;
                    break;
                }
            }
            
            if ($has_course_settings) {
                self::debug_log("Course settings exist but no courses enabled - returning empty array");
                // Update sync status even for empty results
                local_alx_report_api_update_sync_status($companyid, $token, 0, 'success');
                return [];
            }
        }

        // Get company field settings
        $field_settings = [];
        $field_names = ['userid', 'firstname', 'lastname', 'email', 'courseid', 'coursename', 
                       'timecompleted', 'timecompleted_unix', 'timestarted', 'timestarted_unix', 
                       'percentage', 'status'];
        
        foreach ($field_names as $field) {
            $field_settings[$field] = local_alx_report_api_get_company_setting($companyid, 'field_' . $field, 1);
        }
        self::debug_log("Field settings for company $companyid: " . json_encode($field_settings));

        // Build query based on sync mode
        $records = [];
        
        try {
            if ($sync_mode === 'incremental') {
                // Get sync status to determine last sync time
                $sync_status = local_alx_report_api_get_sync_status($companyid, $token);
                $last_sync_time = $sync_status ? $sync_status->last_sync_timestamp : 0;
                
                self::debug_log("Incremental sync - last sync time: " . date('Y-m-d H:i:s', $last_sync_time));
                
                // Query only changed records since last sync
                $sql = "SELECT *
                        FROM {local_alx_api_reporting}
                        WHERE companyid = :companyid
                            AND is_deleted = 0
                            AND last_updated > :last_sync_time";
                
                $params = [
                    'companyid' => $companyid,
                    'last_sync_time' => $last_sync_time
                ];
                
                // Add course filtering if enabled courses specified
                if (!empty($enabled_courses)) {
                    list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
                    $sql .= " AND courseid $course_sql";
                    $params = array_merge($params, $course_params);
                }
                
                $sql .= " ORDER BY last_updated DESC, userid, courseid";
                
                self::debug_log("Incremental SQL: " . $sql);
                $records = $DB->get_records_sql($sql, $params, $offset, $limit);
                
            } else {
                // Full sync or first sync - get all data from reporting table
                self::debug_log("Full/First sync - querying all data from reporting table");
                
                $sql = "SELECT *
                        FROM {local_alx_api_reporting}
                        WHERE companyid = :companyid
                            AND is_deleted = 0";
                
                $params = ['companyid' => $companyid];
                
                // Add course filtering if enabled courses specified
                if (!empty($enabled_courses)) {
                    list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
                    $sql .= " AND courseid $course_sql";
                    $params = array_merge($params, $course_params);
                }
                
                $sql .= " ORDER BY userid, courseid";
                
                self::debug_log("Full sync SQL: " . $sql);
                $records = $DB->get_records_sql($sql, $params, $offset, $limit);
            }
            
            self::debug_log("Found " . count($records) . " records from reporting table");
            
            // If no records found, check if reporting table is populated
            if (empty($records)) {
                self::debug_log("No records found - checking if reporting table is populated");
                
                $total_records = $DB->count_records('local_alx_api_reporting', [
                    'companyid' => $companyid,
                    'is_deleted' => 0
                ]);
                
                if ($total_records === 0) {
                    self::debug_log("Reporting table is empty - falling back to complex query");
                    // Fall back to the original complex query if reporting table is empty
                    // This handles both incremental sync with empty table AND first sync with empty table
                    return self::get_company_course_progress_fallback($companyid, $limit, $offset);
                }
            }
            
        } catch (Exception $e) {
            self::debug_log("Error querying reporting table: " . $e->getMessage());
            // Fall back to original complex query on error
            local_alx_report_api_update_sync_status($companyid, $token, 0, 'failed', $e->getMessage());
            return self::get_company_course_progress_fallback($companyid, $limit, $offset);
        }

        // Process records and build response
        $result = [];
        
        foreach ($records as $record) {
            // Convert Unix timestamps to readable format
            $timecompleted = $record->timecompleted > 0 ? date('Y-m-d H:i:s', $record->timecompleted) : '';
            $timestarted = $record->timestarted > 0 ? date('Y-m-d H:i:s', $record->timestarted) : '';
            
            // Build response dynamically based on company-specific field settings
            $response_item = [];
            
            if ($field_settings['userid']) {
                $response_item['userid'] = (int)$record->userid;
            }
            if ($field_settings['firstname']) {
                $response_item['firstname'] = $record->firstname;
            }
            if ($field_settings['lastname']) {
                $response_item['lastname'] = $record->lastname;
            }
            if ($field_settings['email']) {
                $response_item['email'] = $record->email;
            }
            if ($field_settings['courseid']) {
                $response_item['courseid'] = (int)$record->courseid;
            }
            if ($field_settings['coursename']) {
                $response_item['coursename'] = $record->coursename;
            }
            if ($field_settings['timecompleted']) {
                $response_item['timecompleted'] = $timecompleted;
            }
            if ($field_settings['timecompleted_unix']) {
                $response_item['timecompleted_unix'] = (int)$record->timecompleted;
            }
            if ($field_settings['timestarted']) {
                $response_item['timestarted'] = $timestarted;
            }
            if ($field_settings['timestarted_unix']) {
                $response_item['timestarted_unix'] = (int)$record->timestarted;
            }
            if ($field_settings['percentage']) {
                $response_item['percentage'] = (float)$record->percentage;
            }
            if ($field_settings['status']) {
                $response_item['status'] = $record->status;
            }
            
            $result[] = $response_item;
        }

        // Update sync status
        local_alx_report_api_update_sync_status($companyid, $token, count($result), 'success');
        
        // Cache the result for incremental syncs
        if ($sync_mode === 'incremental' && !empty($result)) {
            local_alx_report_api_cache_set($cache_key, $companyid, $result, 1800); // 30 minutes cache
        }
        
        self::debug_log("Final result count: " . count($result));
        self::debug_log("=== API Request End (Combined Approach) ===");

        return $result;
    }

    /**
     * Get course progress data for a company using the original complex query (fallback).
     *
     * @param int $companyid Company ID
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Course progress data
     */
    private static function get_company_course_progress_fallback($companyid, $limit, $offset) {
        global $DB;

        self::debug_log("=== FALLBACK: Using original complex query ===");

        // Get enabled courses for this company
        $enabled_courses = local_alx_report_api_get_enabled_courses($companyid);
        
        // Get company field settings
        $field_settings = [];
        $field_names = ['userid', 'firstname', 'lastname', 'email', 'courseid', 'coursename', 
                       'timecompleted', 'timecompleted_unix', 'timestarted', 'timestarted_unix', 
                       'percentage', 'status'];
        
        foreach ($field_names as $field) {
            $field_settings[$field] = local_alx_report_api_get_company_setting($companyid, 'field_' . $field, 1);
        }

        // Check if this is a first-time sync with time window restriction
        $token = self::get_authorization_token();
        $sync_mode = local_alx_report_api_determine_sync_mode($companyid, $token);
        $first_sync_hours = local_alx_report_api_get_company_setting($companyid, 'first_sync_hours', 0);
        
        self::debug_log("Fallback sync mode: $sync_mode, first_sync_hours: $first_sync_hours");

        // Use the original complex query logic
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
                AND ue.status = 0";

        $params = ['companyid' => $companyid];
        
        // Add time window filter for first sync if configured
        if ($sync_mode === 'first' && $first_sync_hours > 0) {
            $cutoff_time = time() - ($first_sync_hours * 3600);
            $sql .= " AND (cc.timecompleted > :cutoff_time OR cc.timecompleted = 0 OR cc.timecompleted IS NULL)";
            $params['cutoff_time'] = $cutoff_time;
            self::debug_log("Added time filter for first sync: cutoff_time = " . date('Y-m-d H:i:s', $cutoff_time));
        }
        
        // Add course filtering if enabled courses specified
        if (!empty($enabled_courses)) {
            list($course_sql, $course_params) = $DB->get_in_or_equal($enabled_courses, SQL_PARAMS_NAMED, 'course');
            $sql .= " AND c.id $course_sql";
            $params = array_merge($params, $course_params);
        }
        
        $sql .= " ORDER BY u.lastname, u.firstname, c.fullname";
        
        $records = $DB->get_records_sql($sql, $params, $offset, $limit);
        
        // Process records same as original logic
        $result = [];
        
        foreach ($records as $record) {
            $timecompleted = $record->timecompleted > 0 ? date('Y-m-d H:i:s', $record->timecompleted) : '';
            $timestarted = $record->timestarted > 0 ? date('Y-m-d H:i:s', $record->timestarted) : '';
            
            $response_item = [];
            
            if ($field_settings['userid']) {
                $response_item['userid'] = (int)$record->userid;
            }
            if ($field_settings['firstname']) {
                $response_item['firstname'] = $record->firstname;
            }
            if ($field_settings['lastname']) {
                $response_item['lastname'] = $record->lastname;
            }
            if ($field_settings['email']) {
                $response_item['email'] = $record->email;
            }
            if ($field_settings['courseid']) {
                $response_item['courseid'] = (int)$record->courseid;
            }
            if ($field_settings['coursename']) {
                $response_item['coursename'] = $record->coursename;
            }
            if ($field_settings['timecompleted']) {
                $response_item['timecompleted'] = $timecompleted;
            }
            if ($field_settings['timecompleted_unix']) {
                $response_item['timecompleted_unix'] = (int)$record->timecompleted;
            }
            if ($field_settings['timestarted']) {
                $response_item['timestarted'] = $timestarted;
            }
            if ($field_settings['timestarted_unix']) {
                $response_item['timestarted_unix'] = (int)$record->timestarted;
            }
            if ($field_settings['percentage']) {
                $response_item['percentage'] = (float)$record->percentage;
            }
            if ($field_settings['status']) {
                $response_item['status'] = $record->status;
            }
            
            $result[] = $response_item;
        }

        self::debug_log("Fallback result count: " . count($result) . " (sync_mode: $sync_mode, time_filter: " . ($first_sync_hours > 0 && $sync_mode === 'first' ? 'YES' : 'NO') . ")");
        return $result;
    }

    /**
     * Log API access for audit purposes.
     *
     * @param int $userid User ID making the request
     * @param int $companyid Company ID
     * @param string $endpoint API endpoint called
     */
    private static function log_api_access($userid, $companyid, $endpoint) {
        global $DB;

        $log = new stdClass();
        $log->userid = $userid;
        $log->companyid = $companyid;
        $log->endpoint = $endpoint;
        $log->ipaddress = getremoteaddr();
        $log->useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log->timecreated = time();

        // Create log table if it doesn't exist.
        self::ensure_log_table_exists();

        try {
            $DB->insert_record('local_alx_api_logs', $log);
        } catch (Exception $e) {
            // Logging should not break the API functionality.
            debugging('Failed to log API access: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Ensure the log table exists.
     */
    private static function ensure_log_table_exists() {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_alx_api_logs');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('endpoint', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);
            $table->add_field('useragent', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            try {
                $dbman->create_table($table);
            } catch (Exception $e) {
                debugging('Failed to create log table: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
} 
