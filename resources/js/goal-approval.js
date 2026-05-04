import $ from 'jquery';

import numeral from 'numeral';

import bootstrap from "bootstrap/dist/js/bootstrap.bundle.min.js";

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all dropdowns on the page
    const dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
    const dropdownList = dropdownTriggerList.map(function (dropdownTriggerEl) {
        return new bootstrap.Dropdown(dropdownTriggerEl);
    });
    updateWeightageSummary();
});

function checkEmptyFields() {
    const alertField = $(".mandatory-field");
    alertField.html(`
        <div id="alertField" class="alert alert-danger alert-dismissible fade" role="alert" hidden>
            `+ errorMessages +`
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
    var requiredInputs = document.querySelectorAll(
        "input[required], select[required], textarea[required]"
    );
    for (var i = 0; i < requiredInputs.length; i++) {
        if (requiredInputs[i].value.trim() === "") {
            Swal.fire({
                title: errorAlertMessages,
                confirmButtonColor: "#3e60d5",
                icon: "error",
                didClose: () => {
                    // Show the alert field after the SweetAlert2 modal is closed
                    var alertField = $("#alertField");
                    alertField.removeAttr("hidden").addClass("show");
                },
            });
            return false; // Prevent form submission
        }
    }
    return true; // All required fields are filled
}

function validate() {
    var weight = document.querySelectorAll('input[name="weightage[]"]');
    var sum = 0;
    for (var i = 0; i < weight.length; i++) {
        sum += parseFloat(weight[i].value) || 0; // Parse input value to float, default to 0 if NaN
    }

    if (sum != 90) {
        Swal.fire({
            title: "Submission failed",
            html: `Your current weightage is ${sum}%, <br>Please adjust to reach the total weightage of 90%`,
            confirmButtonColor: "#3e60d5",
            icon: "error",
            // If confirmed, proceed with form submission
        });
        return false; // Prevent form submission
    }

    return true; // Allow form submission
}

function validateWeightage() {
    // Get all input elements with name="weightage[]"
    var weightageInputs = document.getElementsByName("weightage[]");

    // Iterate through each input element
    for (var i = 0; i < weightageInputs.length; i++) {
        var input = weightageInputs[i];

        // Get the value of the input (convert to number)
        var value = parseFloat(input.value);

        // Check if value is below 5%
        if (value < 1) {
            // Display alert message
            Swal.fire({
                title: "The weightage cannot lower than 1%",
                confirmButtonColor: "#3e60d5",
                icon: "error",
                // If confirmed, proceed with form submission
            });
            weightageInputs.focus();
            return false; // Prevent form submission
        }
    }

    return true; // All weightages are valid
}

function confirmAprroval() {
    if (!checkEmptyFields()) {
        return false; // Stop submission if required fields are empty
    }
    if (!validateWeightage()) {
        return false; // Stop submission if required fields are empty
    }
    if (!validate()) {
        return false; // Stop submission if required fields are empty
    }

    let title1;
    let title2;
    let text;
    let confirmText;

    const submitButton = $("#submitButton");
    const spinner = submitButton.find(".spinner-border");

    title1 = "Do you want to submit?";
    title2 = "KPI submitted successfuly!";
    text = "You won't be able to revert this!";
    confirmText = "Submit";

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

            // Remove d-none class from spinner if it exists
            if (spinner.length) {
                spinner.removeClass("d-none");
            }

            document.getElementById("goalApprovalForm").submit();
            Swal.fire({
                title: title2,
                icon: "success",
                showConfirmButton: false,
                // If confirmed, proceed with form submission
            });
        }
    });

    return false; // Prevent default form submission
}

window.confirmAprroval = confirmAprroval;

function confirmAprrovalAdmin() {
    let title1;
    let title2;
    let text;
    let confirmText;

    const submitButton = $("#submitButton");
    const spinner = submitButton.find(".spinner-border");

    title1 = "Do you want to submit?";
    title2 = "KPI submitted successfuly!";
    text = "You won't be able to revert this!";
    confirmText = "Submit";

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

            // Remove d-none class from spinner if it exists
            if (spinner.length) {
                spinner.removeClass("d-none");
            }
            document.getElementById("goalApprovalAdminForm").submit();
            Swal.fire({
                title: title2,
                icon: "success",
                showConfirmButton: false,
                // If confirmed, proceed with form submission
            });
        }
    });

    return false; // Prevent default form submission
}

window.confirmAprrovalAdmin = confirmAprrovalAdmin;

function sendBack(id, nik, name) {
    let msg = $(`#messages${id}`);

    $("#request_id").val(id);
    $("#sendto").val(nik);

    const approver = $("#approver").val();

    let title1 = "Confirm you want to send back?";
    let title2 = "KPI sendback successfuly!";
    let text = `The goals will be sent back to ${name}`;
    let confirmText = "Submit";

    Swal.fire({
        title: title1,
        text: text,
        showCancelButton: true,
        confirmButtonColor: "#3e60d5",
        cancelButtonColor: "#f15776",
        confirmButtonText: confirmText,
        reverseButtons: true,
        input: "textarea",
        nputLabel: "Message",
        inputPlaceholder: "Type your message here...",
        inputAttributes: {
            "aria-label": "Type your message here",
        },
        inputValidator: (value) => {
            if (!value) {
                return "Message cannot be empty"; // Display error message if input is empty
            }
        },
    }).then((result) => {
        if (result.isConfirmed) {
            const message = result.value; // Get the input message value
            if (message.trim() !== "") {
                document.getElementById(
                    "sendback_message"
                ).value = `${approver} : ${message}`;
                // Menggunakan Ajax untuk mengirim data ke Laravel
                document.getElementById("goalSendbackForm").submit();
                Swal.fire({
                    title: title2,
                    icon: "success",
                    showConfirmButton: false,
                    // If confirmed, proceed with form submission
                });
            }
        }
    });

    return false;
}

window.sendBack = sendBack;

function updateWeightageSummary() {

    let total = 0;
    let company = 0;
    let division = 0;
    let personal = 0;

    document.querySelectorAll('.container-card .card').forEach(card => {

        const weightInput = card.querySelector('input[name="weightage[]"]');
        const clusterInput = card.querySelector('input[name="cluster[]"]');

        if (!weightInput || !clusterInput) return;

        const val = parseFloat(weightInput.value);
        const weight = isNaN(val) ? 0 : val;

        const cluster = clusterInput.value;

        total += weight;

        if (cluster === 'company') company += weight;
        if (cluster === 'division') division += weight;
        if (cluster === 'personal') personal += weight;
    });

    document.getElementById('totalCompany').innerText = company.toFixed(1) + '%';
    document.getElementById('totalDivision').innerText = division.toFixed(1) + '%';
    document.getElementById('totalPersonal').innerText = personal.toFixed(1) + '%';
    document.getElementById('totalWeightage').innerText = total.toFixed(1) + '%';

    if (total !== 90) {
        document.getElementById('totalWeightage').style.color = 'red';
    } else {
        document.getElementById('totalWeightage').style.color = '';
    }
}

// Add event listener for keyup event on all weightage inputs
var weightageInputs = document.getElementsByName("weightage[]");
for (var i = 0; i < weightageInputs.length; i++) {
    weightageInputs[i].addEventListener("keyup", updateWeightageSummary);
}

function changeCategory(val) {
    // Set filter category berdasarkan dropdown
    $("#filter_category").val(val);

    const form = $("#onbehalf_filter");
    const contentOnBehalf = $("#contentOnBehalf");
    const customsearch = $("#customsearch");
    const formData = form.serialize();

    function initializePopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (el) {
            return new bootstrap.Popover(el);
        });
    }

    showLoader();

    // Set CSRF token jika diperlukan
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
    });

    $.ajax({
        url: "/admin/onbehalf/content",
        method: "POST",
        data: formData,
        success: function (data) {
            contentOnBehalf.html(data);

            // Hapus instance sebelumnya jika ada
            if ($.fn.DataTable.isDataTable("#onBehalfTable")) {
                $("#onBehalfTable").DataTable().destroy();
            }

            // Inisialisasi ulang DataTable
            const onBehalfTable = $("#onBehalfTable").DataTable({
                dom: "lrtip",
                stateSave: true,
                fixedColumns: {
                    leftColumns: 0,
                    rightColumns: 1,
                },
                scrollCollapse: true,
                scrollX: true,
                pageLength: 25,
                columnDefs: [
                    { targets: [0], orderable: false },
                ],
            });

            // Re-init popover setiap table draw
            onBehalfTable.on("draw", initializePopovers);
            initializePopovers();

            // Event search custom
            customsearch.off("keyup").on("keyup", function () {
                onBehalfTable.search($(this).val()).draw();
            });

            // Event filter button
            $(".filter-btn").off("click").on("click", function () {
                const filterValue = $(this).data("id");
                onBehalfTable.search(filterValue === "all" ? "" : filterValue).draw();
            });

            hideLoader();
        },
        error: function (xhr, status, error) {
            console.error("Error fetching report content:", error);
            contentOnBehalf.html("Error fetching report content. Please try again.");
            hideLoader();
        },
    });
}

window.changeCategory = changeCategory;


document.addEventListener("DOMContentLoaded", function () {
    const form = $("#onbehalf_filter");
    const contentOnBehalf = $("#contentOnBehalf");
    const customsearch = $("#customsearch");

    function initializePopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }

    // Submit form event handler
    form.on("submit", function (event) {
        event.preventDefault(); // Prevent default form submission behavior
        const formData = form.serialize();
        showLoader();

        // Send AJAX request to fetch and display report content
        $.ajax({
            url: "/admin/onbehalf/content", // Endpoint URL to fetch report content
            method: "POST",
            data: formData, // Send serialized form data
            success: function (data) {
                contentOnBehalf.html(data); // Update report content

                const onBehalfTable = $("#onBehalfTable").DataTable({
                    dom: "lrtip",
                    stateSave: true,
                    fixedColumns: {
                        leftColumns: 0,
                        rightColumns: 1
                    },
                    scrollCollapse: true,
                    scrollX: true,
                    pageLength: 25,
                    columnDefs: [
                        { targets: [0], orderable: false }, // Disable sorting for the first column
                    ],
                });

                onBehalfTable.on('draw', function () {
                    initializePopovers();
                });

                customsearch.on("keyup", function () {
                    onBehalfTable.search($(this).val()).draw();
                });

                $(".filter-btn").on("click", function () {
                    const filterValue = $(this).data("id");

                    if (filterValue === "all") {
                        onBehalfTable.search("").draw(); // Clear the search for 'All Task'
                    } else {
                        onBehalfTable.search(filterValue).draw();
                    }
                });

                initializePopovers();

                hideLoader();

                $("#offcanvas-cancel").click();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching report content:", error);
                // Optionally display an error message to the user
                contentOnBehalf.html(
                    "Error fetching report content. Please try again."
                );
            },
        });
    });
});

function autoResize(textarea) {
    // Reset the height to auto to calculate the new height correctly
    textarea.style.height = 'auto';
    // Set the height to the scrollHeight to fit the content
    textarea.style.height = textarea.scrollHeight + 'px';
}

// Automatically resize on page load
document.querySelectorAll('textarea[readonly]').forEach(textarea => {
    autoResize(textarea);
});

function revokeGoal(button) {
    const goalId = button.getAttribute('data-id');
    const employee = button.getAttribute('data-name');
    const form = $("#onbehalf_filter");
    const contentOnBehalf = $("#contentOnBehalf");
    const customsearch = $("#customsearch");
    const formData = form.serialize();

    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to grant access for<br><strong>${employee}</strong><br>to revise their goals.`, // Ensures <br> works correctly
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: "#3e60d5",
        cancelButtonColor: "#f15776",
        confirmButtonText: "Yes, grant access!",
        cancelButtonText: "Cancel",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/goals-revoke', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id: goalId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Revise Granted!', 'The employee now can revise their goals.', 'success');
                    $(button).addClass('d-none'); // Hide button after successful action

                    $.ajax({
                        url: "/admin/onbehalf/content",
                        method: "POST",
                        data: formData,
                        success: function (data) {
                            contentOnBehalf.html(data); // Update report content

                            // Destroy existing DataTable if it exists
                            if ($.fn.DataTable.isDataTable("#onBehalfTable")) {
                                $("#onBehalfTable").DataTable().destroy();
                            }

                            // Initialize DataTable
                            const onBehalfTable = $("#onBehalfTable").DataTable({
                                dom: "lrtip",
                                stateSave: true,
                                fixedColumns: {
                                    leftColumns: 0,
                                    rightColumns: 1
                                },
                                scrollCollapse: true,
                                scrollX: true,
                                pageLength: 25,
                                columnDefs: [
                                    { targets: [0], orderable: false }, // Disable sorting for the first column
                                ],
                            });

                            // Reinitialize Popovers after DataTable redraw
                            onBehalfTable.on('draw', function () {
                                initializePopovers();
                            });

                            // Search functionality
                            customsearch.off("keyup").on("keyup", function () {
                                onBehalfTable.search($(this).val()).draw();
                            });

                            // Filter buttons
                            $(".filter-btn").off("click").on("click", function () {
                                const filterValue = $(this).data("id");
                                onBehalfTable.search(filterValue === "all" ? "" : filterValue).draw();
                            });
                        },
                        error: function (xhr, status, error) {
                            console.error("Error fetching report content:", error);
                            contentOnBehalf.html("Error fetching report content. Please try again.");
                            hideLoader();
                        }
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while revoking the goal.', 'error');
            });
        }
    });
}

window.revokeGoal = revokeGoal;
