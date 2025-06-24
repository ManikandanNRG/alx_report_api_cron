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
 * Settings for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page in the Local plugins category.
    $settings = new admin_settingpage(
        'local_alx_report_api',
        new lang_string('pluginname', 'local_alx_report_api')
    );

    // Add to the local plugins category.
    $ADMIN->add('localplugins', $settings);

    // Add company settings as a separate admin page
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_company_settings',
        get_string('company_settings_title', 'local_alx_report_api'),
        new moodle_url('/local/alx_report_api/company_settings.php'),
        'moodle/site:config'
    ));

    // Add monitoring dashboard as a separate admin page
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_alx_report_api_monitoring',
        'ALX Report API - Monitoring Dashboard',
        new moodle_url('/local/alx_report_api/monitoring_dashboard.php'),
        'moodle/site:config'
    ));

    // Plugin configuration settings.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/generalheading',
        get_string('general', 'local_alx_report_api'),
        get_string('apidescription', 'local_alx_report_api')
    ));

    // Maximum records per API request.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_records',
        get_string('maxrecords', 'local_alx_report_api'),
        get_string('maxrecords_desc', 'local_alx_report_api'),
        1000,
        PARAM_INT,
        5
    ));

    // Log retention period.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/log_retention_days',
        get_string('logretention', 'local_alx_report_api'),
        get_string('logretention_desc', 'local_alx_report_api'),
        90,
        PARAM_INT,
        3
    ));

    // Rate limiting.
    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/rate_limit',
        get_string('ratelimit', 'local_alx_report_api'),
        get_string('ratelimit_desc', 'local_alx_report_api'),
        100,
        PARAM_INT,
        3
    ));

    // GET/POST method toggle for development/testing
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/allow_get_method',
        get_string('allow_get_method', 'local_alx_report_api'),
        get_string('allow_get_method_desc', 'local_alx_report_api'),
        '0'
    ));

    // Field visibility controls.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/fieldheading',
        get_string('fieldheading', 'local_alx_report_api'),
        get_string('fieldheading_desc', 'local_alx_report_api')
    ));

    // User fields.
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_userid',
        get_string('field_userid', 'local_alx_report_api'),
        get_string('field_userid_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_firstname',
        get_string('field_firstname', 'local_alx_report_api'),
        get_string('field_firstname_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_lastname',
        get_string('field_lastname', 'local_alx_report_api'),
        get_string('field_lastname_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_email',
        get_string('field_email', 'local_alx_report_api'),
        get_string('field_email_desc', 'local_alx_report_api'),
        1
    ));

    // Course fields.
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_courseid',
        get_string('field_courseid', 'local_alx_report_api'),
        get_string('field_courseid_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_coursename',
        get_string('field_coursename', 'local_alx_report_api'),
        get_string('field_coursename_desc', 'local_alx_report_api'),
        1
    ));

    // Progress fields.
    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_timecompleted',
        get_string('field_timecompleted', 'local_alx_report_api'),
        get_string('field_timecompleted_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_timecompleted_unix',
        get_string('field_timecompleted_unix', 'local_alx_report_api'),
        get_string('field_timecompleted_unix_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_timestarted',
        get_string('field_timestarted', 'local_alx_report_api'),
        get_string('field_timestarted_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_timestarted_unix',
        get_string('field_timestarted_unix', 'local_alx_report_api'),
        get_string('field_timestarted_unix_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_percentage',
        get_string('field_percentage', 'local_alx_report_api'),
        get_string('field_percentage_desc', 'local_alx_report_api'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_alx_report_api/field_status',
        get_string('field_status', 'local_alx_report_api'),
        get_string('field_status_desc', 'local_alx_report_api'),
        1
    ));

    // Auto-sync configuration.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/autosyncheading',
        'Automatic Sync Configuration',
        'Configure automatic background synchronization of reporting data'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/auto_sync_hours',
        get_string('auto_sync_hours', 'local_alx_report_api'),
        get_string('auto_sync_hours_desc', 'local_alx_report_api'),
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_alx_report_api/max_sync_time',
        get_string('max_sync_time', 'local_alx_report_api'),
        get_string('max_sync_time_desc', 'local_alx_report_api'),
        300,
        PARAM_INT
    ));

    // API status information.
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/statusheading',
        get_string('apistatus', 'local_alx_report_api'),
        ''
    ));

    // Web services status.
    $webservices_enabled = get_config('moodle', 'enablewebservices');
    $webservices_status = $webservices_enabled ? 
        '<span style="color: green;">✓ Enabled</span>' : 
        '<span style="color: red;">✗ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/webservices_status',
        get_string('webservicesstatus', 'local_alx_report_api'),
        $webservices_status
    ));

    // REST protocol status.
    $rest_enabled = strpos(get_config('moodle', 'webserviceprotocols'), 'rest') !== false;
    $rest_status = $rest_enabled ? 
        '<span style="color: green;">✓ Enabled</span>' : 
        '<span style="color: red;">✗ Disabled</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/rest_status',
        get_string('restprotocolstatus', 'local_alx_report_api'),
        $rest_status
    ));

    // Service status.
    global $DB;
    $service_exists = $DB->record_exists('external_services', ['shortname' => 'alx_report_api_custom']);
    $service_status = $service_exists ? 
        '<span style="color: green;">✓ Custom Service Created</span>' : 
        '<span style="color: red;">✗ Custom Service Not Found</span>';

    $settings->add(new admin_setting_heading(
        'local_alx_report_api/service_status',
        get_string('apiservicestatus', 'local_alx_report_api'),
        $service_status
    ));

    // Quick links with improved styling and working URLs.
            $alx_report_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
            $service_id = $alx_report_service ? $alx_report_service->id : '';
    
    $quicklinks_html = '<div style="margin: 20px 0;">
        <style>
            .alx_report_api-quicklinks {
                list-style-type: none;
                padding: 0;
                margin: 0;
            }
            .alx_report_api-quicklinks li {
                margin: 12px 0;
                padding: 8px 12px;
                background: #f8f9fa;
                border-left: 4px solid #007cba;
                border-radius: 4px;
            }
            .alx_report_api-quicklinks li:hover {
                background: #e9ecef;
            }
            .alx_report_api-quicklinks a {
                text-decoration: none;
                color: #007cba;
                font-weight: 500;
            }
            .alx_report_api-quicklinks a:hover {
                text-decoration: underline;
            }
            .alx_report_api-company-link {
                background: #d4edda !important;
                border-left-color: #28a745 !important;
            }
            .alx_report_api-company-link a {
                color: #155724 !important;
                font-weight: bold;
            }
        </style>
        <ul class="alx_report_api-quicklinks">
            <li><a href="' . $CFG->wwwroot . '/admin/webservice/tokens.php">' . get_string('managetokens', 'local_alx_report_api') . '</a></li>';
    
    if ($service_id) {
        $quicklinks_html .= '<li><a href="' . $CFG->wwwroot . '/admin/webservice/service.php?id=' . $service_id . '">' . get_string('manageservices', 'local_alx_report_api') . '</a></li>';
    } else {
        $quicklinks_html .= '<li><a href="' . $CFG->wwwroot . '/admin/webservice/service_functions.php">' . get_string('manageservices', 'local_alx_report_api') . '</a></li>';
    }
    
    $quicklinks_html .= '<li><a href="' . $CFG->wwwroot . '/admin/webservice/documentation.php">' . get_string('apidocumentation', 'local_alx_report_api') . '</a></li>
        </ul>
        <div style="margin-top: 40px; margin-bottom: 30px; padding-top: 20px; padding-bottom: 20px; border-top: 2px solid #28a745; text-align: center;">
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/company_settings.php" class="btn btn-success btn-lg" style="padding: 15px 30px; background: #28a745; border: none; border-radius: 8px; color: white; font-weight: bold; text-decoration: none; font-size: 16px; display: inline-block; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: all 0.3s; margin-right: 15px;">
                🏢 ' . get_string('company_settings_title', 'local_alx_report_api') . '
            </a>
            <a href="' . $CFG->wwwroot . '/local/alx_report_api/auto_sync_status.php" class="btn btn-info btn-lg" style="padding: 15px 30px; background: #17a2b8; border: none; border-radius: 8px; color: white; font-weight: bold; text-decoration: none; font-size: 16px; display: inline-block; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: all 0.3s;">
                🔄 Auto-Sync Status
            </a>
        </div>
    </div>';
    
    $settings->add(new admin_setting_heading(
        'local_alx_report_api/quicklinks',
        get_string('quicklinks', 'local_alx_report_api'),
        $quicklinks_html
    ));
} 
