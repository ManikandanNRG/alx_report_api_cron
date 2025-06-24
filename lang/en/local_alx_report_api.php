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
 * Language strings for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'ALX Report API';
$string['privacy:metadata'] = 'The ALX Report API plugin does not store any personal data.';

// General section.
$string['general'] = 'General';

// Error messages.
$string['invaliduser'] = 'Invalid user or user not authenticated';
$string['nocompanyassociation'] = 'User is not associated with any company';
$string['accessdenied'] = 'Access denied';
$string['invalidparameters'] = 'Invalid parameters provided';

// General strings.
$string['apiendpoint'] = 'API Endpoint';
$string['apidescription'] = 'Provides secure access to course progress data for Power BI integration';
$string['company'] = 'Company';
$string['courseProgress'] = 'Course Progress';
$string['lastAccessed'] = 'Last Accessed';

// Settings page strings.
$string['maxrecords'] = 'Maximum records per request';
$string['maxrecords_desc'] = 'Set the maximum number of records that can be returned in a single API request (1-1000)';
$string['logretention'] = 'Log retention (days)';
$string['logretention_desc'] = 'Number of days to keep API access logs (0 = keep forever)';
$string['ratelimit'] = 'Rate limit (requests per day)';
$string['ratelimit_desc'] = 'Maximum number of API requests per day per user (0 = no limit)';
$string['allow_get_method'] = 'Allow GET Method (Development Only)';
$string['allow_get_method_desc'] = 'Enable GET method for API requests (for testing/development). DISABLE this for production use - only POST method should be used for security.';
$string['apistatus'] = 'API Status';
$string['webservicesstatus'] = 'Web Services Status';
$string['restprotocolstatus'] = 'REST Protocol Status';
$string['apiservicestatus'] = 'API Service Status';
$string['quicklinks'] = 'Quick Links';
$string['webservicesoverview'] = 'Web Services Overview';
$string['managetokens'] = 'Manage Tokens';
$string['manageservices'] = 'Manage Services';
$string['apidocumentation'] = 'API Documentation';

// Field visibility settings.
$string['fieldheading'] = 'API Field Controls';
$string['fieldheading_desc'] = 'Configure which fields are included in the API response. Uncheck fields to hide them from clients.';

// User fields.
$string['field_userid'] = 'User ID';
$string['field_userid_desc'] = 'Include the numeric user ID in the response';
$string['field_firstname'] = 'First Name';
$string['field_firstname_desc'] = 'Include the user\'s first name in the response';
$string['field_lastname'] = 'Last Name';
$string['field_lastname_desc'] = 'Include the user\'s last name in the response';
$string['field_email'] = 'Email Address';
$string['field_email_desc'] = 'Include the user\'s email address in the response';

// Course fields.
$string['field_courseid'] = 'Course ID';
$string['field_courseid_desc'] = 'Include the numeric course ID in the response';
$string['field_coursename'] = 'Course Name';
$string['field_coursename_desc'] = 'Include the course name in the response';

// Progress fields.
$string['field_timecompleted'] = 'Completion Time (Human Readable)';
$string['field_timecompleted_desc'] = 'Include completion time in readable format (YYYY-MM-DD HH:MM:SS)';
$string['field_timecompleted_unix'] = 'Completion Time (Unix Timestamp)';
$string['field_timecompleted_unix_desc'] = 'Include completion time as Unix timestamp for calculations';
$string['field_timestarted'] = 'Start Time (Human Readable)';
$string['field_timestarted_desc'] = 'Include course start time in readable format (YYYY-MM-DD HH:MM:SS)';
$string['field_timestarted_unix'] = 'Start Time (Unix Timestamp)';
$string['field_timestarted_unix_desc'] = 'Include course start time as Unix timestamp for calculations';
$string['field_percentage'] = 'Completion Percentage';
$string['field_percentage_desc'] = 'Include completion percentage (0-100) in the response';
$string['field_status'] = 'Completion Status';
$string['field_status_desc'] = 'Include completion status (completed, in_progress, not_started, not_enrolled)';

// Company-specific settings.
$string['company_settings_title'] = 'ALX Report API - Company Settings';
$string['select_company'] = 'Select Company';
$string['company'] = 'Company';
$string['choose_company'] = 'Choose a company...';
$string['no_companies'] = 'No companies found. Please ensure IOMAD is properly installed and companies are created.';
$string['settings_for_company'] = 'API Settings for: {$a}';
$string['field_controls'] = 'Field Controls';
$string['save_settings'] = 'Save Settings';
$string['copy_from_template'] = 'Copy from Global Template';
$string['settings_saved'] = 'Company settings saved successfully';
$string['template_copied'] = 'Global template copied to company settings';
$string['go'] = 'Go';
$string['course_controls'] = 'Course Controls';
$string['course_controls_desc'] = 'Select which courses are available via API for this company. Unchecked courses will not appear in API responses.';
$string['no_courses_for_company'] = 'No courses found for this company. Please ensure courses are assigned to this company via IOMAD.';
$string['quick_course_actions'] = 'Quick actions:';
$string['select_all_courses'] = 'Select All';
$string['deselect_all_courses'] = 'Deselect All';

// Security error messages
$string['invalidrequestmethod'] = 'Only POST method is allowed for security reasons';
$string['invalidcontenttype'] = 'Invalid Content-Type header';
$string['missingauthheader'] = 'Authorization header is required';
$string['missingtoken'] = 'Authorization token is required';
$string['invalidtokenformat'] = 'Invalid token format';
$string['invalidtoken'] = 'Invalid or expired token';
$string['expiredtoken'] = 'Token has expired'; 

// Rate limiting and validation error messages
$string['ratelimitexceeded'] = 'Daily rate limit exceeded';
$string['limittoolarge'] = 'Requested limit is too large. Maximum allowed records per request is {$a}. Please reduce your limit parameter and try again.'; 

// Scheduled task strings
$string['sync_reporting_data_task'] = 'Sync reporting data incrementally';
$string['auto_sync_hours'] = 'Auto sync hours';
$string['auto_sync_hours_desc'] = 'Number of hours to look back for changes during automatic sync (default: 1 hour)';
$string['max_sync_time'] = 'Maximum sync execution time';
$string['max_sync_time_desc'] = 'Maximum time in seconds for sync task execution (default: 300 seconds)'; 
