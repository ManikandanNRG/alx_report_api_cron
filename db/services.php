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
 * Web service function definitions for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_alx_report_api_get_course_progress' => array(
        'classname' => 'local_alx_report_api_external',
        'methodname' => 'get_course_progress',
        'classpath' => 'local/alx_report_api/externallib.php',
        'description' => 'Get course progress data for company users',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,  // Must be true for custom services
        'requiredcapability' => ''
    )
);  

// NO SERVICES DEFINED HERE - This prevents built-in service creation  
