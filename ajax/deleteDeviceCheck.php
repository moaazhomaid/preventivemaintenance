<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', UPDATE);

// Check CSRF token
if (!isset($_POST['_glpi_csrf_token']) || !Session::validateCSRFToken($_POST['_glpi_csrf_token'])) {
    http_response_code(400);
    exit();
}

if (!isset($_POST['id'])) {
    http_response_code(400);
    die("Missing parameter");
}

$id = (int)$_POST['id'];

global $DB;

// Verify the device check exists and belongs to a maintenance record
$query = "SELECT m.entities_id 
          FROM glpi_plugin_preventivemaintenance_devicechecks d
          JOIN glpi_plugin_preventivemaintenance_maintenances m ON d.maintenance_id = m.id
          WHERE d.id = $id";
$result = $DB->query($query);

if ($DB->numrows($result) === 0) {
    http_response_code(404);
    die("Device check not found");
}

$row = $DB->fetchAssoc($result);

// Delete the device check
$success = $DB->query("DELETE FROM glpi_plugin_preventivemaintenance_devicechecks WHERE id = $id");

header('Content-Type: application/json');

if ($success) {
    // Log the deletion
    Log::history($id, 'PluginPreventiveMaintenancePreventiveMaintenance', 
        [0, '', 'Device check deleted'],
        0, Log::HISTORY_DELETE_SUBITEM);
        
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete device check'
    ]);
}