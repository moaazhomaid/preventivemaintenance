<?php

include('../../../inc/includes.php');

$plugin = new Plugin();

if (!$plugin->isActivated('preventivemaintenance')) {
   Html::displayNotFoundError();
}

$maintenance = new PluginPreventiveMaintenancePreventiveMaintenance();

if (isset($_POST['add'])) {
    Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', CREATE);
    
    $newID = $maintenance->add($_POST);
    Html::redirect($maintenance->getFormURLWithID($newID));

} else if (isset($_POST['update'])) {
    Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', UPDATE);
    
    $maintenance->update($_POST);
    Html::back();

} else if (isset($_POST['delete'])) {
    Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', DELETE);
    
    $maintenance->delete($_POST);
    Html::redirect(Plugin::getWebDir('preventivemaintenance').'/front/preventivemaintenance.php');

} else if (isset($_POST['generate_excel'])) {
    Session::checkRight('plugin_preventivemaintenance_preventivemaintenance', READ);
    
    $doc_id = $maintenance->generateExcelReport($_POST['id']);
    if ($doc_id) {
        $doc = new Document();
        $doc->getFromDB($doc_id);
        Html::redirect($doc->getDownloadLink());
    }
    Html::back();
}

Html::header(
    __('Preventive Maintenance', 'preventivemaintenance'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPreventiveMaintenancePreventiveMaintenance'
);

$maintenance->display($_GET);

Html::footer();