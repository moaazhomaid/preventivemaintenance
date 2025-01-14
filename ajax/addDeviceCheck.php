<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', UPDATE);

// Check CSRF token
if (!isset($_POST['_glpi_csrf_token']) || !Session::validateCSRFToken($_POST['_glpi_csrf_token'])) {
    http_response_code(400);
    exit();
}

if (!isset($_POST['maintenance_id'])) {
    http_response_code(400);
    die("Missing parameter");
}

$maintenance_id = (int)$_POST['maintenance_id'];

// Verify maintenance record exists
$maintenance = new PluginPreventiveMaintenancePreventiveMaintenance();
if (!$maintenance->getFromDB($maintenance_id)) {
    http_response_code(404);
    die("Maintenance record not found");
}

// Prepare device check data
$input = [
    'maintenance_id' => $maintenance_id,
    'device_name' => $_POST['device_name'] ?? '',
    'device_number' => $_POST['device_number'] ?? '',
    'model' => (int)($_POST['model'] ?? 0),
    'performance' => (int)($_POST['performance'] ?? 0),
    'temperature' => (int)($_POST['temperature'] ?? 0),
    'clean' => (int)($_POST['clean'] ?? 0),
    'kasper' => (int)($_POST['kasper'] ?? 0),
    'activation' => (int)($_POST['activation'] ?? 0),
    'update' => (int)($_POST['update'] ?? 0),
    'notes' => $_POST['notes'] ?? '',
    'date_creation' => $_SESSION["glpi_currenttime"],
    'date_mod' => $_SESSION["glpi_currenttime"]
];

// Insert new device check
global $DB;
$success = $DB->insert('glpi_plugin_preventivemaintenance_devicechecks', $input);

header('Content-Type: application/json');

if ($success) {
    $id = $DB->insert_id();
    
    // Log the addition
    Log::history($maintenance_id, 'PluginPreventiveMaintenancePreventiveMaintenance', 
        [0, '', 'Device check added'],
        0, Log::HISTORY_ADD_SUBITEM);
    
    echo json_encode([
        'success' => true,
        'id' => $id,
        'device_check' => array_merge($input, ['id' => $id])
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add device check'
    ]);
}