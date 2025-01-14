<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', READ);

if (!isset($_GET['maintenance_id'])) {
    http_response_code(400);
    die("Missing parameter");
}

$maintenance_id = (int)$_GET['maintenance_id'];

// Verify maintenance record exists
$maintenance = new PluginPreventiveMaintenancePreventiveMaintenance();
if (!$maintenance->getFromDB($maintenance_id)) {
    http_response_code(404);
    die("Maintenance record not found");
}

// Get all device checks for the maintenance record
global $DB;
$query = "SELECT * FROM glpi_plugin_preventivemaintenance_devicechecks 
          WHERE maintenance_id = $maintenance_id 
          ORDER BY id ASC";
$result = $DB->query($query);

$device_checks = [];
while ($row = $DB->fetchAssoc($result)) {
    // Convert boolean fields
    foreach (['model', 'performance', 'temperature', 'clean', 
              'kasper', 'activation', 'update'] as $field) {
        $row[$field] = (bool)$row[$field];
    }
    $device_checks[] = $row;
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'device_checks' => $device_checks
]);