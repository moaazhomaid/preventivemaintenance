<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class PluginPreventiveMaintenancePreventiveMaintenance extends CommonDBTM {
    
    static $rightname = 'plugin_preventivemaintenance_preventivemaintenance';

    public static function getTypeName($nb = 0) {
        return __('Preventive Maintenance', 'preventivemaintenance');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return self::getTypeName(2);
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        self::showForItem($item, $withtemplate);
        return true;
    }

    public function defineTabs($options = []) {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public function showForm($ID, array $options = []) {
        global $CFG_GLPI;

        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Site Name')." <span class='red'>*</span></td>";
        echo "<td>";
        Html::autocompletionTextField($this, 'site_name');
        echo "</td>";
        echo "<td>".__('Visit Date')." <span class='red'>*</span></td>";
        echo "<td>";
        Html::showDateTimeField("visit_date", ['value' => $this->fields["visit_date"]]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Branch Manager')."</td>";
        echo "<td>";
        User::dropdown(['name' => 'branch_manager_id', 
                       'value' => $this->fields["branch_manager_id"],
                       'right' => 'all']);
        echo "</td>";
        echo "<td>".__('Engineer')."</td>";
        echo "<td>";
        User::dropdown(['name' => 'engineer_id',
                       'value' => $this->fields["engineer_id"],
                       'right' => 'all']);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>".__('Notes')."</td>";
        echo "<td colspan='3'>";
        Html::textarea([
            'name'    => 'notes',
            'value'   => $this->fields["notes"],
            'cols'    => 125,
            'rows'    => 3
        ]);
        echo "</td>";
        echo "</tr>";

        // Device Checks Table
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        self::showDeviceChecksForm($ID);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        // Add Excel Generation Button
        if ($ID > 0) {
            echo "<div class='center preventivemaintenance_excel_button'>";
            echo "<form method='post' action='".$this->getFormURL()."'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(__('Generate Excel Report'), [
                'name' => 'generate_excel',
                'class' => 'btn btn-primary'
            ]);
            Html::closeForm();
            echo "</div>";
        }

        return true;
    }

    private function showDeviceChecksForm($maintenanceId) {
        global $DB;

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_2'>";
        echo "<th colspan='11'>".__('Device Checks')."</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_2'>";
        echo "<th>".__('Device Name')."</th>";
        echo "<th>".__('Device Number')."</th>";
        echo "<th>".__('Model')."</th>";
        echo "<th>".__('Performance')."</th>";
        echo "<th>".__('Temperature')."</th>";
        echo "<th>".__('Clean')."</th>";
        echo "<th>".__('Kasper')."</th>";
        echo "<th>".__('Activation')."</th>";
        echo "<th>".__('Update')."</th>";
        echo "<th>".__('Notes')."</th>";
        echo "<th>".__('Actions')."</th>";
        echo "</tr>";

        // Get existing device checks
        if ($maintenanceId > 0) {
            $query = "SELECT * FROM glpi_plugin_preventivemaintenance_devicechecks 
                     WHERE maintenance_id = $maintenanceId 
                     ORDER BY id ASC";
            $result = $DB->query($query);

            while ($data = $DB->fetchAssoc($result)) {
                $this->showDeviceCheckRow($data);
            }
        }

        // Empty row for new device
        $this->showDeviceCheckRow();

        echo "</table>";
    }

    private function showDeviceCheckRow($data = null) {
        $id = $data['id'] ?? 'new_' . rand();

        echo "<tr class='tab_bg_1 device_check_row'>";
        
        echo "<td>";
        Html::autocompletionTextField(null, "device_name[$id]", [
            'value' => $data['device_name'] ?? ''
        ]);
        echo "</td>";

        echo "<td>";
        Html::autocompletionTextField(null, "device_number[$id]", [
            'value' => $data['device_number'] ?? ''
        ]);
        echo "</td>";

        // Checkboxes
        foreach (['model', 'performance', 'temperature', 'clean', 
                  'kasper', 'activation', 'update'] as $check) {
            echo "<td class='center'>";
            Html::showCheckbox([
                'name' => "device_checks[$id][$check]",
                'checked' => $data[$check] ?? 0
            ]);
            echo "</td>";
        }

        echo "<td>";
        Html::textarea([
            'name' => "device_notes[$id]",
            'value' => $data['notes'] ?? '',
            'cols' => 30,
            'rows' => 2
        ]);
        echo "</td>";

        echo "<td class='center'>";
        if (isset($data['id'])) {
            echo "<button type='button' class='btn btn-danger' 
                  onclick='deleteDeviceCheck($id)'>
                  <i class='fas fa-trash'></i></button>";
        } else {
            echo "<button type='button' class='btn btn-success' 
                  onclick='addDeviceCheck()'>
                  <i class='fas fa-plus'></i></button>";
        }
        echo "</td>";

        echo "</tr>";
    }

    public function prepareInputForAdd($input) {
        // Default document version from current date
        if (empty($input['document_version'])) {
            $input['document_version'] = date('d/m/Y');
        }
        return $input;
    }

    /**
     * Generate Excel report for the maintenance record
     */
    public function generateExcelReport($maintenance_id) {
        global $DB;
        
        if (!$this->getFromDB($maintenance_id)) {
            return false;
        }
        
        // Load all device checks for this maintenance
        $query = "SELECT * FROM glpi_plugin_preventivemaintenance_devicechecks 
                  WHERE maintenance_id = " . $maintenance_id . " 
                  ORDER BY device_number";
        $result = $DB->query($query);
        
        $device_checks = [];
        while ($row = $DB->fetchAssoc($result)) {
            $device_checks[] = $row;
        }
        $this->fields['device_checks'] = json_encode($device_checks);
        
        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set RTL and page orientation
        $sheet->setRightToLeft(true);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        
        // Set column widths
        $columnWidths = [
            'A' => 8,   // Serial number
            'B' => 25,  // Device name
            'C' => 15,  // Device number
            'D' => 12,  // Model
            'E' => 12,  // Performance
            'F' => 12,  // Temperature
            'G' => 12,  // Clean
            'H' => 12,  // Kasper
            'I' => 12,  // Activation
            'J' => 12,  // Updates
            'K' => 20   // Notes
        ];
        
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        
        // Title row with logo
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'الصيانة الوقائية للأجهزة الإلكترونية');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'name' => 'Traditional Arabic'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2']
            ]
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        
        // Document info row
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A2', 'الإصدار ' . $this->fields['document_version']);
        
        $sheet->mergeCells('E2:G2');
        $sheet->setCellValue('E2', 'كود الوثيقة IT-R-01');
        
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Traditional Arabic'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT
            ]
        ]);
        
        // Site info and date
        $sheet->setCellValue('A3', 'اسم الموقع:');
        $sheet->mergeCells('B3:D3');
        $sheet->setCellValue('B3', $this->fields['site_name']);
        
        $sheet->setCellValue('E3', 'تاريخ الزيارة:');
        $sheet->mergeCells('F3:H3');
        $visit_date = new \DateTime($this->fields['visit_date']);
        $sheet->setCellValue('F3', $visit_date->format('Y-m-d'));
        
        $sheet->setCellValue('K3', 'الملاحظات');
        
        // Style the info section
        $sheet->getStyle('A3:K3')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Traditional Arabic'
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F2F2F2']
            ]
        ]);
        
        // Table headers
        $headers = [
            'م',
            'اسم الجهاز',
            'رقمه',
            'Model',
            'Performance',
            'Temp',
            'Clean',
            'Kasper',
            'Activ',
            'Update',
            'ملاحظات'
        ];
        
        foreach ($headers as $col => $header) {
            $column = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue($column . '4', $header);
        }
        
        // Style headers
        $sheet->getStyle('A4:K4')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Traditional Arabic'
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        
        // Add devices data
        $row = 5;
        if (!empty($device_checks)) {
            foreach ($device_checks as $device) {
                if ($row > 24) break; // Maximum 20 devices
                
                $sheet->setCellValue('A' . $row, $row - 4);
                $sheet->setCellValue('B' . $row, $device['device_name']);
                $sheet->setCellValue('C' . $row, $device['device_number']);
                
                // Checkboxes
                $columns = ['D' => 'model', 'E' => 'performance', 'F' => 'temperature',
                           'G' => 'clean', 'H' => 'kasper', 'I' => 'activation',
                           'J' => 'update'];
                           
                foreach ($columns as $col => $field) {
                    $sheet->setCellValue($col . $row, 
                        !empty($device[$field]) ? '☑' : '☐');
                }
                
                if (!empty($device['notes'])) {
                    $sheet->setCellValue('K' . $row, $device['notes']);
                }
                
                $row++;
            }
        }
        
        // Fill remaining rows up to 20
        while ($row <= 24) {
            $sheet->setCellValue('A' . $row, $row - 4);
            foreach (range('D', 'J') as $col) {
                $sheet->setCellValue($col . $row, '☐');
            }
            $row++;
        }
        
        // Style all data rows
        $sheet->getStyle('A5:K24')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'font' => [
                'size' => 11,
                'name' => 'Traditional Arabic'
            ]
        ]);

        // Add signature section
        $lastRow = 25;
        
        // Signatures
        $sheet->mergeCells('A' . $lastRow . ':E' . $lastRow);
        $sheet->setCellValue('A' . $lastRow, 'توقيع مدير الفرع');
        
        $sheet->mergeCells('G' . $lastRow . ':K' . $lastRow);
        $sheet->setCellValue('G' . $lastRow, 'توقيع المهندس المختص');
        
        // Style signature section
        $sheet->getStyle('A' . $lastRow . ':K' . $lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'name' => 'Traditional Arabic'
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
        
        // Add signature lines and names
        $signatureRow = $lastRow + 1;
        $sheet->mergeCells('A' . $signatureRow . ':E' . $signatureRow);
        $sheet->mergeCells('G' . $signatureRow . ':K' . $signatureRow);
        
        $namesRow = $signatureRow + 1;
        $sheet->mergeCells('A' . $namesRow . ':E' . $namesRow);
        $sheet->mergeCells('G' . $namesRow . ':K' . $namesRow);
        
        // Add manager name if set
        if (!empty($this->fields['branch_manager_id'])) {
            $manager = new User();
            $manager->getFromDB($this->fields['branch_manager_id']);
            $sheet->setCellValue('A' . $namesRow, $manager->getFriendlyName());
        }
        
        // Add engineer name if set
        if (!empty($this->fields['engineer_id'])) {
            $engineer = new User();
            $engineer->getFromDB($this->fields['engineer_id']);
            $sheet->setCellValue('G' . $namesRow, $engineer->getFriendlyName());
        }
        
        // Style names
        $sheet->getStyle('A' . $namesRow . ':K' . $namesRow)->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set print area
        $sheet->getPageSetup()->setPrintArea('A1:K27');
        
        // Create Excel file
        $writer = new Xlsx($spreadsheet);
        
        // Generate temp file
        $tempFile = tempnam(GLPI_TMP_DIR, 'maintenance_report_');
        $writer->save($tempFile);
        
        if (!$tempFile) {
            return false;
        }
        
        // Create document in GLPI
        $doc = new Document();
        $doc_input = [
            'name' => sprintf(
                __('Preventive Maintenance Report - %s - %s'), 
                $this->fields['site_name'],
                date('Y-m-d')
            ),
            'entities_id' => $this->fields['entities_id'],
            'is_recursive' => 1,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'filename' => sprintf(
                'maintenance_report_%s_%s.xlsx',
                str_replace(' ', '_', $this->fields['site_name']),
                date('Y-m-d')
            )
        ];
        
        $doc_id = $doc->add($doc_input);
        
        if ($doc_id) {
            // Move file to GLPI document directory
            $dest = GLPI_DOC_DIR . '/' . Document::getUploadDir($doc_id);
            if (!is_dir($dest)) {
                mkdir($dest, 0777, true);
            }
            
            $destFile = $dest . '/' . $doc_input['filename'];
            
            if (rename($tempFile, $destFile)) {
                // Update document with final path
                $doc->update([
                    'id' => $doc_id,
                    '_filename' => [
                        '_prefix' => '',
                        '_filename' => $doc_input['filename']
                    ]
                ]);
                
                // Link document to preventive maintenance record
                $docItem = new Document_Item();
                $docItem->add([
                    'documents_id' => $doc_id,
                    'itemtype' => $this->getType(),
                    'items_id' => $this->getID()
                ]);
                
                return $doc_id;
            }
        }
        
        @unlink($tempFile); // Clean up temp file
        return false;
    }

    static function canCreate() {
        return Session::haveRight(self::$rightname, CREATE);
    }

    static function canView() {
        return Session::haveRight(self::$rightname, READ);
    }

    static function getMenuName() {
        return __('Preventive Maintenance', 'preventivemaintenance');
    }

    static function getMenuContent() {
        $menu = [];
        if (Session::haveRight(self::$rightname, READ)) {
            $menu['title'] = self::getMenuName();
            $menu['page'] = Plugin::getWebDir('preventivemaintenance').'/front/preventivemaintenance.php';
            $menu['icon']  = 'fas fa-tools';
        }
        return $menu;
    }

    /**
     * Cron task method for checking maintenance schedules
     */
    public static function cronPreventiveMaintenance($task) {
        global $DB;

        $maintenance = new self();
        $current_date = date('Y-m-d H:i:s');
        
        // Find upcoming maintenance tasks
        $query = "SELECT * FROM glpi_plugin_preventivemaintenance_maintenances 
                  WHERE next_maintenance_date <= '$current_date'
                  AND status = 'pending'";
        
        $result = $DB->query($query);
        $task_count = 0;

        while ($data = $DB->fetchAssoc($result)) {
            // Create ticket or notification for each maintenance due
            if ($maintenance->createMaintenanceNotification($data['id'])) {
                $task_count++;
            }
        }

        $task->addVolume($task_count);
        return true;
    }

    /**
     * Create notification for upcoming maintenance
     */
    private function createMaintenanceNotification($maintenance_id) {
        // Create notification/ticket logic here
        $notification = new Notification();
        
        $input = [
            'name'      => __('Preventive Maintenance Due', 'preventivemaintenance'),
            'entities_id' => $this->fields['entities_id'],
            'itemtype'  => $this->getType(),
            'event'     => 'maintenance_due'
        ];
        
        return $notification->add($input);
    }
}