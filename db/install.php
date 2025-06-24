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
 * Post installation and migration code for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to run after the plugin has been installed.
 */
function xmldb_local_alx_report_api_install() {
    global $DB, $CFG;

    // Enable web services if not already enabled.
    if (!get_config('moodle', 'enablewebservices')) {
        set_config('enablewebservices', 1);
    }

    // Enable REST protocol if not already enabled.
    $enabledprotocols = get_config('moodle', 'webserviceprotocols');
    if (strpos($enabledprotocols, 'rest') === false) {
        if (empty($enabledprotocols)) {
            set_config('webserviceprotocols', 'rest');
        } else {
            set_config('webserviceprotocols', $enabledprotocols . ',rest');
        }
    }

    // Create custom service (since we removed it from services.php)
    $existing_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
    if (!$existing_service) {
        $service = new stdClass();
        $service->name = 'ALX Report API Service';
        $service->shortname = 'alx_report_api_custom';
        $service->enabled = 1;
        $service->restrictedusers = 1;  // This makes it a CUSTOM service
        $service->downloadfiles = 0;
        $service->uploadfiles = 0;
        $service->timecreated = time();
        $service->timemodified = time();
        
        $serviceid = $DB->insert_record('external_services', $service);
        
        // Add function to the custom service
        $function = new stdClass();
        $function->externalserviceid = $serviceid;
        $function->functionname = 'local_alx_report_api_get_course_progress';
        $DB->insert_record('external_services_functions', $function);
    }

    return true;
} 
