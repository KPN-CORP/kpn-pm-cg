import { log } from 'handlebars';
import $ from 'jquery';

document.addEventListener("DOMContentLoaded", function () {

    const layerAppraisalTable = $("#layerAppraisalTable").DataTable({
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

    $('#customsearch').val(layerAppraisalTable.search());

    $("#customsearch").on("keyup", function () {
        layerAppraisalTable.search($(this).val()).draw();
    });

    let previousValue = $('#manager').val();

    // Event listener for when an option is selected
    $('#manager').on('select2:select', function(e) {
        const selectedValue = e.params.data.id;

        // Show confirmation alert
        Swal.fire({
            title: 'Change the Manager?',
            text: 'Changing current manager will delete the approved appraisal. This action cannot be undone',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            reverseButtons: true,
            confirmButtonText: 'Yes, change it!',
            cancelButtonText: 'No, keep the current',
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                previousValue = selectedValue;
            } else {
                $('#manager').val(previousValue).trigger('change');
            }
        });
        e.preventDefault();
    });

    $('#submit-btn').click(function () {

        let title1;
        let title2;
        let text;
        let confirmText;

        const submitButton = $("#submit-btn");
        const spinner = submitButton.find(".spinner-border");

        title1 = "Save layer?";
        text = "This can't be reverted";
        title2 = "Layer saved successfully!";
        confirmText = "Ok, save it";

        // Get values of the select elements with ids containing sub
        let manager = $('#manager');

        let sub1 = $('#sub1');
        let sub2 = $('#sub2');
        let sub3 = $('#sub3');

        // Get values of the select elements with ids containing peer
        let peer1 = $('#peer1');
        let peer2 = $('#peer2');
        let peer3 = $('#peer3');

        // Get values of the select elements with ids containing calibrator
        let calibrators = $("select[name='calibrators[]']");

        // Clear previous invalid classes and error messages
        sub1.removeClass('is-invalid');
        sub2.removeClass('is-invalid');
        sub3.removeClass('is-invalid');
        peer1.removeClass('is-invalid');
        peer2.removeClass('is-invalid');
        peer3.removeClass('is-invalid');
        calibrators.removeClass('is-invalid');

        $('.error-message').text(''); // Clear all previous error messages

        // Step 1: Check for duplicates in peer selections
        let peerValues = [peer1.val(), peer2.val(), peer3.val()].filter(Boolean); // Filter out empty values
        let hasPeerDuplicates = peerValues.some((value, index) => peerValues.indexOf(value) !== index);

        if (!manager.val()) {
            Swal.fire({
                title: "Error",
                text: "Manager cannot empty.",
                icon: "error",
                confirmButtonColor: "#f15776"
            });

            manager.addClass('is-invalid')
            .siblings('.error-message')
            .text('Manager cannot be empty.');

            return false; // Prevent form submission if peers have duplicates
        }

        if (hasPeerDuplicates) {
            Swal.fire({
                title: "Error",
                text: "Peers must be unique.",
                icon: "error",
                confirmButtonColor: "#f15776"
            });

            // Add is-invalid class and show error message to duplicate peer select elements
            [peer1, peer2, peer3].forEach(function (element) {
                let value = element.val();
                if (value && [peer1, peer2, peer3].filter(e => e.val() === value).length > 1) {
                    element.addClass('is-invalid');
                    element.siblings('.error-message').text('Peers must be unique.');
                }
            });

            return false; // Prevent form submission if peers have duplicates
        }

        // Step 2: Check for duplicates in subordinate selections (if peer validation passed)
        let subValues = [sub1.val(), sub2.val(), sub3.val()].filter(Boolean); // Filter out empty values
        let hasSubDuplicates = subValues.some((value, index) => subValues.indexOf(value) !== index);

        if (hasSubDuplicates) {
            Swal.fire({
                title: "Error",
                text: "Subordinate must be unique.",
                icon: "error",
                confirmButtonColor: "#f15776"
            });

            // Add is-invalid class and show error message to duplicate subordinate select elements
            [sub1, sub2, sub3].forEach(function (element) {
                let value = element.val();
                if (value && [sub1, sub2, sub3].filter(e => e.val() === value).length > 1) {
                    element.addClass('is-invalid');
                    element.siblings('.error-message').text('Subordinate must be unique.');
                }
            });

            return false; // Prevent form submission if subordinates have duplicates
        }

        let firstCalibrator = calibrators.first();
        if (!firstCalibrator.val()) {
            Swal.fire({
                title: "Error",
                text: "Calibrator is required.",
                icon: "error",
                confirmButtonColor: "#f15776"
            });

            // Add is-invalid class and show error message on the first calibrator
            firstCalibrator.addClass('is-invalid');
            firstCalibrator.siblings('.error-message').text('Calibrator is required.');

            return false; // Stop execution if the first calibrator is not selected
        }

        // Step 3: Check for duplicates in calibrator selections (if peer and sub validation passed)
        let calibratorValues = calibrators.map(function () {
            return $(this).val();
        }).get().filter(Boolean); // Filter out empty values
        let hasCalibratorDuplicates = calibratorValues.some((value, index) => calibratorValues.indexOf(value) !== index);

        if (hasCalibratorDuplicates) {
            Swal.fire({
                title: "Error",
                text: "Calibrators must be unique.",
                icon: "error",
                confirmButtonColor: "#f15776"
            });

            // Add is-invalid class and show error message to duplicate calibrator select elements
            calibrators.each(function () {
                let value = $(this).val();
                if (value && calibratorValues.filter(e => e === value).length > 1) {
                    $(this).addClass('is-invalid');
                    $(this).siblings('.error-message').text('Calibrators must be unique.');
                }
            });

            return false; // Prevent form submission if calibrators have duplicates
        }

        // Proceed with confirmation dialog if all validations passed
        Swal.fire({
            title: title1,
            text: text,
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: confirmText,
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                // Disable submit button
                submitButton.prop("disabled", true);
                submitButton.addClass("disabled");

                // Show spinner if it exists
                if (spinner.length) {
                    spinner.removeClass("d-none");
                }

                // Submit the form
                document.getElementById("layer-appraisal").submit();

                // Show success message
                Swal.fire({
                    title: title2,
                    icon: "success",
                    showConfirmButton: false,
                    timer: 1500, // Optional: Auto close the success message after 1.5 seconds
                });
            }
        });

        return false; // Prevent default form submission
    });

    $(document).ready(function() {
        $('.selection2').select2({
            minimumInputLength: 1,
            placeholder: "Please select",
            allowClear: true,
            theme: 'bootstrap-5',
            ajax: {
                url: '/search-employee', // Route for your Laravel search endpoint
                dataType: 'json',
                delay: 250, // Wait 250ms before triggering request (debounce)
                data: function (params) {
                    return {
                        searchTerm: params.term, // Search term entered by the user
                        employeeId: $('#employee_id').val()
                    };
                },
                processResults: function (data) {
                    // Map the data to Select2 format
                    return {
                        results: $.map(data, function (item) {
                            return {
                                id: item.employee_id, // ID field for Select2
                                text: item.fullname + ' ' + item.employee_id // Text to display in Select2
                            };
                        })
                    };
                },
                cache: true
            }
        });
    });

});

$(document).ready(function(){
    const maxCalibrators = 10;

    $('#add-calibrator').on('click', function() {
        showLoader();
        if (calibratorCount < maxCalibrators) {
            calibratorCount++;

            // Create the new calibrator row with dynamic employee options
            let options = '<option value="">- Please Select -</option>';

            const newCalibrator = `
                <div class="row mb-2" id="calibrator-row-${calibratorCount}">
                    <div class="col-10">
                        <h5>Calibrator ${calibratorCount}</h5>
                        <select name="calibrators[]" id="calibrator${calibratorCount}" class="form-select selection2">
                            ${options}
                        </select>
                        <div class="text-danger error-message fs-14"></div>
                    </div>
                    <div class="col-2 d-flex align-items-end justify-content-end">
                        <div class="mt-1">
                            <a class="btn btn-outline-danger rounded remove-calibrator" data-calibrator-id="${calibratorCount}">
                            <i class="ri-delete-bin-line"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;

            $('#calibrator-container').append(newCalibrator);

            $('.selection2').select2({
                minimumInputLength: 1,
                placeholder: "Please select",
                allowClear: true,
                theme: 'bootstrap-5',
                ajax: {
                    url: '/search-employee', // Route for your Laravel search endpoint
                    dataType: 'json',
                    delay: 250, // Wait 250ms before triggering request (debounce)
                    data: function (params) {
                        return {
                            searchTerm: params.term, // Search term entered by the user
                            employeeId: $('#employee_id').val()
                        };
                    },
                    processResults: function (data) {
                        // Map the data to Select2 format
                        return {
                            results: $.map(data, function (item) {
                                return {
                                    id: item.employee_id, // ID field for Select2
                                    text: item.fullname + ' ' + item.employee_id // Text to display in Select2
                                };
                            })
                        };
                    },
                    cache: true
                }
            });

            // updateRemoveButtons();
        } else {
            Swal.fire({
                title: "Oops!",
                text: "You've reached the maximum number of Calibrator",
                icon: "error",
                confirmButtonColor: "#3e60d5",
                confirmButtonText: "OK",
            });
        }
        hideLoader();
    });

    function updateRemoveButtons() {
        // $('.remove-calibrator').prop('disabled', false); // Enable all remove buttons
        $(`#calibrator-row-${calibratorCount} .remove-calibrator`).prop('disabled', false); // Ensure the latest one is enabled
    }

    $(document).on('click', '.remove-calibrator', function() {
        // Always remove the latest calibrator row
        $(`#calibrator-row-${calibratorCount}`).remove();
        calibratorCount--;
        updateRemoveButtons(); // Update buttons visibility
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

document.addEventListener('DOMContentLoaded', function () {
    const detailModal = document.getElementById('detailModal');

    if (detailModal) {
        detailModal.addEventListener('show.bs.modal', async function (event) {
            const button = event.relatedTarget;
            const employeeId = button.getAttribute('data-bs-id');

            // Show loading state before fetching data
            showLoadingState();

            try {
                // Fetch the employee details using async/await
                const data = await fetchEmployeeDetails(employeeId);

                // Populate the modal with the retrieved data
                populateModal(data);

                // Populate history table
                populateHistoryTable(data.history);
            } catch (error) {
                console.error('Error fetching employee details:', error);
                showErrorMessage('Unable to retrieve employee details. Please try again.');
            } finally {
                // Hide loading state
                hideLoadingState();
            }
        });
    }

    // Function to fetch employee details from the backend
    async function fetchEmployeeDetails(employeeId) {
        const response = await fetch(`/employee-layer-appraisal/details/${employeeId}`);

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        return await response.json();
    }

    // Function to show loading indicator (can be a spinner or overlay)
    function showLoadingState() {
        const modalBody = detailModal.querySelector('#historyTable tbody');
        modalBody.innerHTML = '<div class="loading-spinner">Loading...</div>';
    }

    // Function to hide loading indicator
    function hideLoadingState() {
        const loadingSpinner = detailModal.querySelector('.loading-spinner');
        if (loadingSpinner) {
            loadingSpinner.remove();
        }
    }

    // Function to populate modal fields with employee data
    function populateModal(data) {
        detailModal.querySelector('.fullname').textContent = data.fullname || 'N/A';
        detailModal.querySelector('.employee_id').textContent = data.employee_id || 'N/A';
        detailModal.querySelector('.formattedDoj').textContent = data.formattedDoj || 'N/A';
        detailModal.querySelector('.group_company').textContent = data.group_company || 'N/A';
        detailModal.querySelector('.company_name').textContent = data.company_name || 'N/A';
        detailModal.querySelector('.unit').textContent = data.unit || 'N/A';
        detailModal.querySelector('.designation').textContent = data.designation || 'N/A';
        detailModal.querySelector('.office_area').textContent = data.office_area || 'N/A';
    }

    // Function to populate history table with employee history data
    function populateHistoryTable(history) {
        const historyTableBody = detailModal.querySelector('#historyTable tbody');
        historyTableBody.innerHTML = ''; // Clear previous entries

        if (history.length === 0) {
            historyTableBody.innerHTML = '<tr><td colspan="4" class="text-center">No history available.</td></tr>';
            return;
        }

        history.forEach((entry, index) => {
            const row = `<tr>
                            <td>${entry.layer_type + ' ' + entry.layer || 'N/A'}</td>
                            <td>${entry.fullname + ' (' + entry.employee_id + ')' || 'N/A'}</td>
                            <td class="text-center">${entry.updated_by}</td>
                            <td class="text-center">${entry.updated_at || 'N/A'}</td>
                        </tr>`;
            historyTableBody.insertAdjacentHTML('beforeend', row);
        });
    }

    // Function to display an error message inside the modal
    function showErrorMessage(message) {
        const modalBody = detailModal.querySelector('#historyTable tbody');
        modalBody.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
});

function applyLocationFilter(table) {
var locationId = $('#locationFilter').val().toUpperCase();

// Filter table based on location
table.column(10).search(locationId).draw(); // Adjust index based on your table structure
}

$('#importButton').on('click', function(e) {
    e.preventDefault();
    const form = $('#importForm').get(0);
    const submitButton = $('#importButton');
    const spinner = submitButton.find(".spinner-border");
    console.log(spinner);

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
});
