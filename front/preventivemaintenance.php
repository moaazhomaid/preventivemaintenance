<?php

include ('../../../inc/includes.php');

Session::checkRight("plugin_preventivemaintenance_preventivemaintenance", READ);

Html::header(
    __('Preventive Maintenance', 'preventivemaintenance'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPreventiveMaintenancePreventiveMaintenance'
);

Search::show('PluginPreventiveMaintenancePreventiveMaintenance');

Html::footer();