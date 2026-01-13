import $ from 'jquery';

document.addEventListener("DOMContentLoaded", function () {

    const layerTable = $("#layerTable").DataTable({
        dom: "lrtip",
        stateSave: true,
        fixedColumns: {
            leftColumns: 0,
            rightColumns: 1
        },
        pageLength: 25,
        scrollCollapse: true,
        scrollX: true
    });

    $('#customsearch').val(layerTable.search());

    $("#customsearch").on("keyup", function () {
        layerTable.search($(this).val()).draw();
    });

});

$(document).ready(function() {
    $('.open-import-modal').on('click', function() {
        var importModal = document.getElementById('importModal');
        
        // Initialize the Bootstrap modal
        var modal = new bootstrap.Modal(importModal);
        
        modal.show();
    });
});

function viewHistory(employeeId) {
    showLoader();
    fetch('/history-show', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ employee_id: employeeId })
    })
    .then(response => response.json())
    .then(data => {
        // Clear existing rows in the table body
        const tableBody = document.querySelector('#viewModal tbody');
        tableBody.innerHTML = '';

        // Populate table with new data
        data.forEach((item, index) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${index + 1}</td>
                <td>${item.fullname}</td>
                <td>
                    ${item.layers.split('|').map((layer, i) => `L${layer}: ${item.approver_names.split('|')[i]}`).join('<br>')}
                </td>
                <td>${item.updated_by_name ?? 'system'}</td>
                <td>${item.updated_at}</td>
            `;
            tableBody.appendChild(row);
        });
        hideLoader();
        var viewModal = document.getElementById('viewModal');
        // Initialize the Bootstrap modal
        var modal = new bootstrap.Modal(viewModal);
        
        modal.show();
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

window.viewHistory = viewHistory;

$(document).ready(function() {

// Apply filter when location dropdown value changes
$('#locationFilter').on('change', function() {
    applyLocationFilter(table);
});

// Apply filter when table is redrawn (e.g., when navigating to next page)
// table.on('draw.dt', function() {
//     applyLocationFilter(table);
// });
});

function applyLocationFilter(table) {
var locationId = $('#locationFilter').val().toUpperCase();

// Filter table based on location
table.column(10).search(locationId).draw(); // Adjust index based on your table structure
}


$(document).on('click', '.open-edit-modal', function() {
    var employeeId = $(this).data('bsEmployee-id');
    
    var fullname = $(this).data('bsFullname');
    var app = $(this).data('bsApp');
    $('#employeeId').text(employeeId);
    
    
    var layer = $(this).data('bsLayer');
    var appname = $(this).data('bsApp-name');
    
    // populateModal(employeeId, fullname, app, layer, appname);
    populateModal(employeeId, fullname, app, layer, appname, employeesData);
});

function populateModal(employeeId, fullName, app, layer, appName, employees) {

    $('#employee_id').val(employeeId);
    $('#employeeId').val(employeeId);
    $('#fullname').val(fullName+' - '+employeeId);

    let apps = [];
    let layers = [];
    let appNames = [];

    // if (typeof app === 'string' && app.indexOf("|") !== -1) {
    if (app.includes('|')) {
        // Jika nilai app mengandung karakter '|', lakukan pemisahan
        apps = app.split('|');
        layers = layer.split('|');
        appNames = appName.split('|');
    } else {
        // Jika tidak mengandung karakter '|', gunakan nilai langsung
        apps = [app]; // Ubah ke array untuk konsistensi
        layers = [layer];
        appNames = [appName];
    }

    $('#viewlayer').empty();
    $('#nikAppInputs').empty();
    var layerIndex = 1;

    if((apps.length+3)>6){
        var maxlayer = 6;
    }else{
        var maxlayer = (apps.length+3);
    }

    for (var i = 0; i < maxlayer; i++) {
        var selectOptions = "<option></option>";
        for (var j = 0; j < employees.length; j++) {
            var selected = (employees[j].employee_id == apps[i]) ? 'selected' : 'Select Employee';
            selectOptions += '<option value="' + employees[j].employee_id + '" ' + selected + '>' + employees[j].fullname + ' - ' + employees[j].employee_id + '</option>';
        }

        var disabled = (i > apps.length) ? 'disabled' : ''; // Disable additional layers initially
        var required = (i == 0) ? 'required' : '';
        $('#viewlayer').append('<div class="row mb-2"><label class="col-md-2 col-form-label">Layer ' + layerIndex + '</label><div class="col"><select name="nik_app[]" class="form-select select2"' + disabled + ' ' + required + '>' + selectOptions + '</select></div></div>');
        layerIndex++;
    }

    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#editModal'),
        placeholder: 'Select Layer Name',
        theme: "bootstrap-5",
        width: '100%',
        allowClear: true
    });

    // Add change event listener to enable the next layer only if the current one is selected
    $('#viewlayer .select2').each(function (index) {
        $(this).on('change', function () {
            if ($(this).val() !== '') {
                // Enable the next select element if current selection is not empty
                for (var i = index + 2; i < $('#viewlayer .select2').length; i++) {
                    if(i === index + 2){
                        $('#viewlayer .select2').eq(i).val('').prop('disabled', false).trigger('change');
                    }
                }
            } else {
                // Disable the subsequent select elements if the current one is cleared
                for (var i = index + 2; i < $('#viewlayer .select2').length; i++) {
                    $('#viewlayer .select2').eq(i).val('').prop('disabled', true).trigger('change');
                }
            }
        });
    });

    var editModal = document.getElementById('editModal');
    
    // Initialize the Bootstrap modal
    var modal = new bootstrap.Modal(editModal);
    
    modal.show();
}

$('#submitButton').on('click', function(e) {
    e.preventDefault();
    const form = $('#editForm').get(0);
    const submitButton = $('#submitButton');
    const spinner = submitButton.find(".spinner-border");

    if(form){
        if (form && form.checkValidity()) {
        // Disable submit button
        submitButton.prop('disabled', true);
        submitButton.addClass("disabled");
    
        // Remove d-none class from spinner if it exists
        if (spinner.length) {
            spinner.removeClass("d-none");
        }
    
        // Submit form
        form.submit();
        } else {
            // If the form is not valid, trigger HTML5 validation messages
            form.reportValidity();
        }
    }
});
$('#importButton').on('click', function(e) {
    e.preventDefault();
    const form = $('#importForm').get(0);
    const submitButton = $('#importButton');
    const spinner = submitButton.find(".spinner-border");

    if(form){
        if (form.checkValidity()) {
        // Disable submit button
        submitButton.prop('disabled', true);
        submitButton.addClass("disabled");
    
        // Remove d-none class from spinner if it exists
        if (spinner.length) {
            spinner.removeClass("d-none");
        }
    
        // Submit form
        form.submit();
        } else {
            // If the form is not valid, trigger HTML5 validation messages
            form.reportValidity();
        }
    }
});