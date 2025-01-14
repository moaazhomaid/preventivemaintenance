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

// Verify device check exists
global $DB;
$query = "SELECT * FROM glpi_plugin_preventivemaintenance_devicechecks WHERE id = $id";
$result = $DB->query($query);

if ($DB->numrows($result) === 0) {
    http_response_code(404);
    die("Device check not found");
}

$current = $DB->fetchAssoc($result);

// Prepare update data
$update = [
    'device_name' => $_POST['device_name'] ?? $current['device_name'],
    'device_number' => $_POST['device_number'] ?? $current['device_number'],
    'model' => isset($_POST['model']) ? (int)$_POST['model'] : $current['model'],
    'performance' => isset($_POST['performance']) ? (int)$_POST['performance'] : $current['performance'],
    'temperature' => isset($_POST['temperature']) ? (int)$_POST['temperature'] : $current['temperature'],
    'clean' => isset($_POST['clean']) ? (int)$_POST['clean'] : $current['clean'],
    'kasper' => isset($_POST['kasper']) ? (int)$_POST['kasper'] : $current['kasper'],
    'activation' => isset($_POST['activation']) ? (int)$_POST['activation'] : $current['activation'],
    'update' => isset($_POST['update']) ? (int)$_POST['update'] : $current['update'],
    'notes' => $_POST['notes'] ?? $current['notes'],
    'date_mod' => $_SESSION["glpi_currenttime"]
];

// Update the device check
$success = $DB->update(
    'glpi_plugin_preventivemaintenance_devicechecks',
    $update,
    ['id' => $id]
);

header('Content-Type: application/json');

if ($success) {
    // Log the update
    $changes = [];
    foreach ($update as $key => $value) {
        if ($current[$key] != $value) {
            $changes[] = [$key, $current[$key], $value];
        }
    }
    
    if (!empty($changes)) {
        Log::history($current['maintenance_id'], 
            'PluginPreventiveMaintenancePreventiveMaintenance',
            $changes,
            0, Log::HISTORY_UPDATE_SUBITEM);
    }
    
    echo json_encode([
        'success' => true,
        'device_check' => array_merge($update, ['id' => $id])
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to update device check'
    ]);
}