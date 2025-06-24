<?php
// This file is part of Moodle - http://moodle.org/
//
// Simple script to create missing database tables

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/ddllib.php');

// Simple login check
require_login();
require_capability('moodle/site:config', context_system::instance());

// Page setup
$PAGE->set_url('/local/alx_report_api/create_tables.php');
$PAGE->set_title('Create Tables - ALX Report API');
$PAGE->set_context(context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">';
echo '<h2>🔧 Create Missing Database Tables</h2>';

// Check which tables are missing
$tables_to_check = [
    'local_alx_api_logs',
    'local_alx_api_settings', 
    'local_alx_api_reporting',
    'local_alx_api_sync_status',
    'local_alx_api_cache'
];

$missing_tables = [];
foreach ($tables_to_check as $table) {
    if (!$DB->get_manager()->table_exists($table)) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    echo '<h3>✅ All Tables Exist</h3>';
    echo '<p>All required database tables are already created. Your database error might be caused by something else.</p>';
    echo '<p><a href="company_settings.php?companyid=1" class="btn btn-primary">Go Back to Company Settings</a></p>';
    echo '</div>';
} else {
    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">';
    echo '<h3>⚠️ Missing Tables Found</h3>';
    echo '<p>The following tables are missing and need to be created:</p>';
    echo '<ul>';
    foreach ($missing_tables as $table) {
        echo '<li><code>' . $table . '</code></li>';
    }
    echo '</ul>';
    echo '</div>';
    
    if ($action === 'create') {
        echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        echo '<h3>🔧 Creating Tables...</h3>';
        
        try {
            // Get the database manager
            $dbman = $DB->get_manager();
            
            // Read the install.xml file
            $xmldb_file = new xmldb_file(__DIR__ . '/db/install.xml');
            if (!$xmldb_file->fileExists()) {
                throw new Exception('install.xml file not found');
            }
            
            $xmldb_file->loadXMLStructure();
            $xmldb_structure = $xmldb_file->getStructure();
            
            $created_tables = [];
            $errors = [];
            
            foreach ($missing_tables as $table_name) {
                try {
                    $xmldb_table = $xmldb_structure->getTable($table_name);
                    if ($xmldb_table) {
                        $dbman->create_table($xmldb_table);
                        $created_tables[] = $table_name;
                        echo '<p>✅ Created table: <code>' . $table_name . '</code></p>';
                    } else {
                        $errors[] = "Table definition not found for: $table_name";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error creating $table_name: " . $e->getMessage();
                }
            }
            
            if (!empty($created_tables)) {
                echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                echo '<h4>🎉 Tables Created Successfully!</h4>';
                echo '<p>Created ' . count($created_tables) . ' tables. You can now try saving your company settings again.</p>';
                echo '<p><a href="company_settings.php?companyid=1" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go Back to Company Settings</a></p>';
                echo '</div>';
            }
            
            if (!empty($errors)) {
                echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;">';
                echo '<h4>❌ Some Errors Occurred</h4>';
                foreach ($errors as $error) {
                    echo '<p>' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;">';
            echo '<h4>❌ Error</h4>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<div style="text-align: center; margin: 20px 0;">';
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="create">';
        echo '<button type="submit" style="background: #dc3545; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">🔧 Create Missing Tables</button>';
        echo '</form>';
        echo '</div>';
        
        echo '<div style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        echo '<h4>💡 What This Will Do</h4>';
        echo '<p>This will create the missing database tables required for the ALX Report API plugin to function properly.</p>';
        echo '<p><strong>Safe:</strong> This only creates missing tables and does not affect existing data.</p>';
        echo '</div>';
    }
}

echo '</div>';

echo $OUTPUT->footer();
?> 