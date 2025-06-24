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
 * Upgrade script for the ALX Report API plugin.
 *
 * @package    local_alx_report_api
 * @copyright  2024 ALX Report API Plugin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool True on success
 */
function xmldb_local_alx_report_api_upgrade($oldversion) {
    global $DB, $CFG;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024011502) {
        // Ensure web services are enabled.
        if (!get_config('moodle', 'enablewebservices')) {
            set_config('enablewebservices', 1);
        }

        // Ensure REST protocol is enabled.
        $enabledprotocols = get_config('moodle', 'webserviceprotocols');
        if (strpos($enabledprotocols, 'rest') === false) {
            if (empty($enabledprotocols)) {
                set_config('webserviceprotocols', 'rest');
            } else {
                set_config('webserviceprotocols', $enabledprotocols . ',rest');
            }
        }

        // Ensure our service exists and is properly configured.
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if (!$service) {
            // Create the service if it doesn't exist.
            $service = new stdClass();
            $service->name = 'alx_report_api';
            $service->shortname = 'alx_report_api';
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timecreated = time();
            $service->timemodified = time();
            
            $serviceid = $DB->insert_record('external_services', $service);
            
            // Add function to service.
            $function = new stdClass();
            $function->externalserviceid = $serviceid;
            $function->functionname = 'local_alx_report_api_get_course_progress';
            $DB->insert_record('external_services_functions', $function);
        } else {
            // Update existing service to ensure it's properly configured.
            $service->enabled = 1;
            $service->restrictedusers = 1;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            
            // Ensure the function is added to the service.
            $function_exists = $DB->record_exists('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$function_exists) {
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->insert_record('external_services_functions', $function);
            }
        }

        // Ensure the log table exists.
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

            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011502, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011509) {
        // Create company settings table.
        $table = new xmldb_table('local_alx_api_settings');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting_name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('setting_value', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_company_setting', XMLDB_KEY_UNIQUE, ['companyid', 'setting_name']);
            
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('setting_name', XMLDB_INDEX_NOTUNIQUE, ['setting_name']);

            $dbman->create_table($table);
        }

        // Migrate old service shortname from 'brilliapi' to 'alx_report_api' for existing installations
        $old_service = $DB->get_record('external_services', ['shortname' => 'brilliapi']);
        if ($old_service) {
            $old_service->shortname = 'alx_report_api';
            $old_service->name = 'ALX Report API Service';
            $old_service->restrictedusers = 1;
            $old_service->enabled = 0; // Start disabled for admin configuration
            $old_service->timemodified = time();
            $DB->update_record('external_services', $old_service);
            
            // Update function name in service functions
            $old_function = $DB->get_record('external_services_functions', [
                'externalserviceid' => $old_service->id,
                'functionname' => 'local_brilliapi_get_course_progress'
            ]);
            if ($old_function) {
                $old_function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->update_record('external_services_functions', $old_function);
            }
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011509, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011519) {
        // Clean up and ensure proper service configuration
        
        // Remove any duplicate services
        $services = $DB->get_records('external_services', ['shortname' => 'alx_report_api']);
        if (count($services) > 1) {
            // Keep the first one, remove duplicates
            $keep_service = reset($services);
            foreach ($services as $service) {
                if ($service->id != $keep_service->id) {
                    $DB->delete_records('external_services_functions', ['externalserviceid' => $service->id]);
                    $DB->delete_records('external_services_users', ['externalserviceid' => $service->id]);
                    $DB->delete_records('external_services', ['id' => $service->id]);
                }
            }
        }
        
        // Ensure our service is properly configured
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->name = 'ALX Report API Service';
            $service->restrictedusers = 1;
            $service->enabled = 0; // Start disabled for admin configuration
            $service->downloadfiles = 0;
            $service->uploadfiles = 0;
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
            
            // Ensure correct function is associated
            $DB->delete_records('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_brilliapi_get_course_progress'
            ]);
            
            $correct_function = $DB->get_record('external_services_functions', [
                'externalserviceid' => $service->id,
                'functionname' => 'local_alx_report_api_get_course_progress'
            ]);
            
            if (!$correct_function) {
                $function = new stdClass();
                $function->externalserviceid = $service->id;
                $function->functionname = 'local_alx_report_api_get_course_progress';
                $DB->insert_record('external_services_functions', $function);
            }
        }
        
        // Clean up old brilliapi service if it still exists
        $old_service = $DB->get_record('external_services', ['shortname' => 'brilliapi']);
        if ($old_service) {
            $DB->delete_records('external_services_functions', ['externalserviceid' => $old_service->id]);
            $DB->delete_records('external_services_users', ['externalserviceid' => $old_service->id]);
            $DB->delete_records('external_services', ['id' => $old_service->id]);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011519, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011520) {
        // Fix access control exception by updating service configuration
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->restrictedusers = 0; // Set to 0 since we handle user restriction manually
            $service->enabled = 1; // Enable the service
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011520, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011521) {
        // Fix service to be custom with proper authentication
        $service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($service) {
            $service->restrictedusers = 1; // MUST be 1 for custom service with user restrictions
            $service->enabled = 1; // Enable the service
            $service->name = 'ALX Report API Service';
            $service->timemodified = time();
            $DB->update_record('external_services', $service);
        }
        
        // Clear all caches to ensure changes take effect
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011521, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011523) {
        // Remove any built-in services and create proper custom service
        
        // Remove old built-in service if it exists
        $old_builtin_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api']);
        if ($old_builtin_service) {
            $DB->delete_records('external_services_functions', ['externalserviceid' => $old_builtin_service->id]);
            $DB->delete_records('external_services_users', ['externalserviceid' => $old_builtin_service->id]);
            $DB->delete_records('external_services', ['id' => $old_builtin_service->id]);
        }
        
        // Create custom service if it doesn't exist
        $custom_service = $DB->get_record('external_services', ['shortname' => 'alx_report_api_custom']);
        if (!$custom_service) {
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
        
        // Clear all caches
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011523, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024011530) {
        // Create reporting table for combined approach (separate table + incremental sync)
        $table = new xmldb_table('local_alx_api_reporting');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('percentage', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'not_started');
            $table->add_field('last_updated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('is_deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_user_course', XMLDB_KEY_UNIQUE, ['userid', 'courseid', 'companyid']);
            
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('last_updated', XMLDB_INDEX_NOTUNIQUE, ['last_updated']);
            $table->add_index('userid_courseid', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
            $table->add_index('timecompleted', XMLDB_INDEX_NOTUNIQUE, ['timecompleted']);
            $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('is_deleted', XMLDB_INDEX_NOTUNIQUE, ['is_deleted']);

            $dbman->create_table($table);
        }

        // Create sync status table for incremental updates
        $table = new xmldb_table('local_alx_api_sync_status');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('token_hash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('last_sync_timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sync_mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'auto');
            $table->add_field('sync_window_hours', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '24');
            $table->add_field('last_sync_records', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('last_sync_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'success');
            $table->add_field('last_sync_error', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('total_syncs', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('updated_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_company_token', XMLDB_KEY_UNIQUE, ['companyid', 'token_hash']);
            
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('token_hash', XMLDB_INDEX_NOTUNIQUE, ['token_hash']);
            $table->add_index('last_sync_timestamp', XMLDB_INDEX_NOTUNIQUE, ['last_sync_timestamp']);
            $table->add_index('sync_mode', XMLDB_INDEX_NOTUNIQUE, ['sync_mode']);

            $dbman->create_table($table);
        }

        // Create cache table for performance optimization
        $table = new xmldb_table('local_alx_api_cache');
        
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cache_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('cache_data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('cache_timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('hit_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('last_accessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('unique_cache_key', XMLDB_KEY_UNIQUE, ['cache_key', 'companyid']);
            
            $table->add_index('cache_key', XMLDB_INDEX_NOTUNIQUE, ['cache_key']);
            $table->add_index('companyid', XMLDB_INDEX_NOTUNIQUE, ['companyid']);
            $table->add_index('expires_at', XMLDB_INDEX_NOTUNIQUE, ['expires_at']);
            $table->add_index('cache_timestamp', XMLDB_INDEX_NOTUNIQUE, ['cache_timestamp']);

            $dbman->create_table($table);
        }

        // Clear all caches
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024011530, 'local', 'alx_report_api');
    }

    if ($oldversion < 2024062401) {
        // Add automated cron sync system
        
        // The scheduled task is automatically registered via db/tasks.php
        // No database changes needed for this version - just task registration
        
        // Set default configuration values for auto-sync
        set_config('auto_sync_hours', 1, 'local_alx_report_api');
        set_config('max_sync_time', 300, 'local_alx_report_api');
        
        // Clear all caches to ensure new task is recognized
        cache_helper::purge_all();
        
        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024062401, 'local', 'alx_report_api');
    }

    return true;
} 
