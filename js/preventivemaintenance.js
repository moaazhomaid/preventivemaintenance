$(document).ready(function() {
    // Global variables
    let maintenanceId = $('input[name="id"]').val();
    let deviceChecksData = {};

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Load initial device checks data
    function loadDeviceChecks() {
        if (!maintenanceId) return;

        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/preventivemaintenance/ajax/getDeviceChecks.php',
            type: 'GET',
            data: {
                maintenance_id: maintenanceId
            },
            success: function(response) {
                if (response.success) {
                    deviceChecksData = response.device_checks.reduce((acc, check) => {
                        acc[check.id] = check;
                        return acc;
                    }, {});
                    refreshDeviceChecksTable();
                }
            },
            error: function(xhr) {
                showErrorMessage(__('Error loading device checks'));
            }
        });
    }

    // Refresh device checks table
    function refreshDeviceChecksTable() {
        const $table = $('.device_checks_table tbody');
        $table.empty();

        Object.values(deviceChecksData).forEach(check => {
            addDeviceCheckRow(check);
        });
        // Add empty row for new entries
        addEmptyDeviceCheckRow();
    }

    // Add a new device check row to table
    function addDeviceCheckRow(data) {
        const $table = $('.device_checks_table tbody');
        const rowHtml = generateDeviceCheckRowHtml(data);
        
        if (data.id && data.id.toString().startsWith('new_')) {
            // New row
            $table.append(rowHtml);
        } else {
            // Update existing row or insert new row
            const $existingRow = $table.find(`tr[data-id="${data.id}"]`);
            if ($existingRow.length) {
                $existingRow.replaceWith(rowHtml);
            } else {
                $table.append(rowHtml);
            }
        }

        // Reinitialize any special inputs (like select2, etc)
        initializeRowInputs($table.find(`tr[data-id="${data.id}"]`));
    }

    // Generate HTML for a device check row
    function generateDeviceCheckRowHtml(data) {
        const id = data.id || 'new_' + Math.random().toString(36).substr(2, 9);
        const isNew = !data.id || data.id.toString().startsWith('new_');

        return `
            <tr data-id="${id}" class="device_check_row">
                <td>
                    <input type="text" class="form-control device_name" 
                           name="device_name[${id}]" 
                           value="${data.device_name || ''}"
                           ${isNew ? '' : 'data-orig="' + (data.device_name || '') + '"'}>
                </td>
                <td>
                    <input type="text" class="form-control device_number"
                           name="device_number[${id}]"
                           value="${data.device_number || ''}"
                           ${isNew ? '' : 'data-orig="' + (data.device_number || '') + '"'}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_model"
                           name="device_checks[${id}][model]"
                           ${data.model ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_performance"
                           name="device_checks[${id}][performance]"
                           ${data.performance ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_temperature"
                           name="device_checks[${id}][temperature]"
                           ${data.temperature ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_clean"
                           name="device_checks[${id}][clean]"
                           ${data.clean ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_kasper"
                           name="device_checks[${id}][kasper]"
                           ${data.kasper ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_activation"
                           name="device_checks[${id}][activation]"
                           ${data.activation ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="check_update"
                           name="device_checks[${id}][update]"
                           ${data.update ? 'checked' : ''}>
                </td>
                <td>
                    <textarea class="form-control device_notes"
                              name="device_notes[${id}]"
                              rows="2">${data.notes || ''}</textarea>
                </td>
                <td class="text-center">
                    ${isNew ? 
                        `<button type="button" class="btn btn-sm btn-success add_device_check">
                            <i class="fas fa-plus"></i>
                         </button>` :
                        `<button type="button" class="btn btn-sm btn-danger delete_device_check">
                            <i class="fas fa-trash"></i>
                         </button>`
                    }
                </td>
            </tr>
        `;
    }

    // Handle adding new device check
    $(document).on('click', '.add_device_check', function() {
        const $row = $(this).closest('tr');
        const data = collectRowData($row);

        if (!data.device_name || !data.device_number) {
            showErrorMessage(__('Device name and number are required'));
            return;
        }

        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/preventivemaintenance/ajax/addDeviceCheck.php',
            type: 'POST',
            data: {
                ...data,
                maintenance_id: maintenanceId,
                _glpi_csrf_token: $('[name="_glpi_csrf_token"]').val()
            },
            success: function(response) {
                if (response.success) {
                    deviceChecksData[response.id] = response.device_check;
                    refreshDeviceChecksTable();
                    showSuccessMessage(__('Device check added successfully'));
                }
            },
            error: function(xhr) {
                showErrorMessage(__('Error adding device check'));
            }
        });
    });

    // Handle deleting device check
    $(document).on('click', '.delete_device_check', function() {
        const $row = $(this).closest('tr');
        const id = $row.data('id');

        if (confirm(__('Are you sure you want to delete this device check?'))) {
            $.ajax({
                url: CFG_GLPI.root_doc + '/plugins/preventivemaintenance/ajax/deleteDeviceCheck.php',
                type: 'POST',
                data: {
                    id: id,
                    _glpi_csrf_token: $('[name="_glpi_csrf_token"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        delete deviceChecksData[id];
                        refreshDeviceChecksTable();
                        showSuccessMessage(__('Device check deleted successfully'));
                    }
                },
                error: function(xhr) {
                    showErrorMessage(__('Error deleting device check'));
                }
            });
        }
    });

    // Handle updating device check
    function updateDeviceCheck($row) {
        const id = $row.data('id');
        if (!id || id.toString().startsWith('new_')) return;

        const data = collectRowData($row);
        const originalData = deviceChecksData[id];

        // Check if anything changed
        let hasChanges = false;
        for (let key in data) {
            if (data[key] !== originalData[key]) {
                hasChanges = true;
                break;
            }
        }

        if (!hasChanges) return;

        $.ajax({
            url: CFG_GLPI.root_doc + '/plugins/preventivemaintenance/ajax/updateDeviceCheck.php',
            type: 'POST',
            data: {
                ...data,
                id: id,
                _glpi_csrf_token: $('[name="_glpi_csrf_token"]').val()
            },
            success: function(response) {
                if (response.success) {
                    deviceChecksData[id] = response.device_check;
                    $row.find('input[type="text"], textarea').attr('data-orig', function() {
                        return $(this).val();
                    });
                }
            },
            error: function(xhr) {
                showErrorMessage(__('Error updating device check'));
                refreshDeviceChecksTable(); // Revert changes
            }
        });
    }

    // Collect data from a row
    function collectRowData($row) {
        return {
            device_name: $row.find('.device_name').val(),
            device_number: $row.find('.device_number').val(),
            model: $row.find('.check_model').prop('checked') ? 1 : 0,
            performance: $row.find('.check_performance').prop('checked') ? 1 : 0,
            temperature: $row.find('.check_temperature').prop('checked') ? 1 : 0,
            clean: $row.find('.check_clean').prop('checked') ? 1 : 0,
            kasper: $row.find('.check_kasper').prop('checked') ? 1 : 0,
            activation: $row.find('.check_activation').prop('checked') ? 1 : 0,
            update: $row.find('.check_update').prop('checked') ? 1 : 0,
            notes: $row.find('.device_notes').val()
        };
    }

    // Handle changes to existing device checks
    let updateTimeout;
    $(document).on('change keyup', '.device_check_row input, .device_check_row textarea', function() {
        const $row = $(this).closest('tr');
        const id = $row.data('id');
        
        if (!id || id.toString().startsWith('new_')) return;

        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(() => {
            updateDeviceCheck($row);
        }, 500);
    });

    // Helper function to show success message
    function showSuccessMessage(message) {
        const html = `
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        $('#maintenance-alerts').html(html).show();
    }

    // Helper function to show error message
    function showErrorMessage(message) {
        const html = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        $('#maintenance-alerts').html(html).show();
    }

    // Initialize everything
    function initialize() {
        // Add alerts container if not exists
        if (!$('#maintenance-alerts').length) {
            $('<div id="maintenance-alerts" class="mt-3"></div>').insertBefore('.device_checks_table');
        }

        loadDeviceChecks();
    }

    initialize();
});