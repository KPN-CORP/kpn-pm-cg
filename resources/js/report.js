import $ from 'jquery';

function hideLoader() {
    $("#preloader").hide();
}

import bootstrap from "bootstrap/dist/js/bootstrap.bundle.min.js";
import { log } from 'handlebars';

function adminReportType(val) {
    $("#report_type").val(val);
    const reportForm = $("#admin_report_filter");
    const exportButton = $("#export");
    const reportContentDiv = $("#report_content");
    const customsearch = $("#customsearch");
    const formData = reportForm.serialize();

    initializePopovers();

    showLoader();
    if (val) {
        exportButton.removeClass("disabled"); // Enable export button
    } else {
        exportButton.addClass("disabled"); // Enable export button
    }
    $.ajax({
        url: "/admin/get-report-content", // Endpoint URL to fetch report content
        method: "POST",
        data: formData, // Send serialized form data
        success: function (data) {
            //alert(data);
            reportContentDiv.html(data); // Update report content

            const reportGoalsTable = $("#adminReportTable").DataTable({
                dom: "lrtip",
                stateSave: true,
                pageLength: 50,
                scrollCollapse: true,
                scrollX: true
            });

            // Retrieve previous search value from stateSave
            let savedState = reportGoalsTable.state.loaded();  
            if (savedState && savedState.search.search) {
                reportGoalsTable.search(savedState.search.search).draw();
                customsearch.val(savedState.search.search); // Set input value
            }

            reportGoalsTable.on('draw', function () {
                initializePopovers();
            });
            customsearch.keyup(function () {
                reportGoalsTable.search($(this).val()).draw();
            });

            $(".filter-btn").on("click", function () {
                const filterValue = $(this).data("id");

                if (filterValue === "all") {
                    reportGoalsTable.search("").draw(); // Clear the search for 'All Task'
                } else {
                    reportGoalsTable.search(filterValue).draw();
                }
            });

            initializePopovers();

            hideLoader();
        },
        error: function (xhr, status, error) {
            console.error("Error fetching report content:", error);
            // Optionally display an error message to the user
            reportContentDiv.html("");
        },
    });
    return; // Prevent default form submission
}

window.adminReportType = adminReportType;

document.addEventListener("DOMContentLoaded", function () {
    const reportForm = $("#admin_report_filter");
    const exportButton = $("#export");
    const reportContentDiv = $("#report_content");
    const customsearch = $("#customsearch");

    // Submit form event handler
    reportForm.on("submit", function (event) {
        event.preventDefault(); // Prevent default form submission behavior
        const formData = reportForm.serialize(); // Serialize form data
        showLoader();

        // Send AJAX request to fetch and display report content
        $.ajax({
            url: "/admin/get-report-content", // Endpoint URL to fetch report content
            method: "POST",
            data: formData, // Send serialized form data
            success: function (data) {
                reportContentDiv.html(data); // Update report content
                exportButton.removeClass("disabled"); // Enable export button

                const reportGoalsTable = $("#adminReportTable").DataTable({
                    dom: "lrtip",
                    stateSave: true,
                    pageLength: 50,
                    scrollCollapse: true,
                    scrollX: true
                });

                // Retrieve previous search value from stateSave
                let savedState = reportGoalsTable.state.loaded();  
                if (savedState && savedState.search.search) {
                    reportGoalsTable.search(savedState.search.search).draw();
                    customsearch.val(savedState.search.search); // Set input value
                }
                
                customsearch.on("keyup", function () {
                    reportGoalsTable.search($(this).val()).draw();
                });

                $(".filter-btn").on("click", function () {
                    const filterValue = $(this).data("id");

                    if (filterValue === "all") {
                        reportGoalsTable.search("").draw(); // Clear the search for 'All Task'
                    } else {
                        reportGoalsTable.search(filterValue).draw();
                    }
                });

                initializePopovers();

                hideLoader();

                $("#offcanvas-cancel").click();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching report content:", error);
                // Optionally display an error message to the user
                reportContentDiv.html(
                    "Error fetching report content. Please try again."
                );
            },
        });
    });

    // Optional: Add event listener for exportButton if needed
    exportButton.on("click", function () {
        const reportContent = reportContentDiv.html();
        // Code here to handle exporting the report content
    });
});

function initializePopovers() {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

}

function reportType(val) {
    $("#report_type").val(val);
    const reportForm = $("#report_filter");
    const exportButton = $("#export");
    const reportContentDiv = $("#report_content");
    const customsearch = $("#customsearch");
    const formData = reportForm.serialize();
    
    showLoader();
    if (val) {
        exportButton.removeClass("disabled"); // Enable export button
    } else {
        exportButton.addClass("disabled"); // Disable export button
    }

    $.ajax({
        url: "/get-report-content", // Endpoint URL to fetch report content
        method: "POST",
        data: formData, // Send serialized form data
        success: function (data) {
            reportContentDiv.html(data); // Update report content

            const reportGoalsTable = $("#reportGoalsTable").DataTable({
                dom: "lrtip",
                stateSave: true,
                pageLength: 50,
                scrollCollapse: true,
                scrollX: true
            });

            // Reinitialize popovers after the table is drawn
            reportGoalsTable.on('draw', function () {
                initializePopovers();
            });

            // Initialize popovers for the newly loaded content
            initializePopovers();

            customsearch.keyup(function () {
                reportGoalsTable.search($(this).val()).draw();
            });

            $(".filter-btn").on("click", function () {
                const filterValue = $(this).data("id");

                if (filterValue === "all") {
                    reportGoalsTable.search("").draw(); // Clear the search for 'All Task'
                } else {
                    reportGoalsTable.search(filterValue).draw();
                }
            });

            hideLoader();
        },
        error: function (xhr, status, error) {
            console.error("Error fetching report content:", error);
            // Optionally display an error message to the user
            reportContentDiv.html("");
            hideLoader();
        },
    });

    return; // Prevent default form submission
}

window.reportType = reportType;

document.addEventListener("DOMContentLoaded", function () {
    const reportForm = $("#report_filter");
    const exportButton = $("#export");
    const reportContentDiv = $("#report_content");
    const customsearch = $("#customsearch");

    // Submit form event handler
    reportForm.on("submit", function (event) {
        event.preventDefault(); // Prevent default form submission behavior

        const formData = reportForm.serialize(); // Serialize form data

        showLoader();

        // Send AJAX request to fetch and display report content
        $.ajax({
            url: "/get-report-content", // Endpoint URL to fetch report content
            method: "POST", // Use POST method
            data: formData, // Send serialized form data
            success: function (data) {
                reportContentDiv.html(data); // Update report content with the returned HTML
                exportButton.removeClass("disabled"); // Enable export button

                const reportGoalsTable = $("#reportGoalsTable").DataTable({
                    dom: "lrtip",
                    stateSave: true,
                    pageLength: 50,
                    scrollCollapse: true,
                    scrollX: true
                });
                customsearch.on("keyup", function () {
                    reportGoalsTable.search($(this).val()).draw();
                });

                $(".filter-btn").on("click", function () {
                    const filterValue = $(this).data("id");

                    if (filterValue === "all") {
                        reportGoalsTable.search("").draw(); // Clear the search for 'All Task'
                    } else {
                        reportGoalsTable.search(filterValue).draw();
                    }
                });
                initializePopovers();
                hideLoader();

                $("#offcanvas-cancel").click();
            },
            error: function (xhr, status, error) {
                console.error("Error fetching report content:", error);
                // Optionally display an error message to the user
                reportContentDiv.html(
                    "Error fetching report content. Please try again."
                );
            },
        });
    });

    // Optional: Add event listener for exportButton if needed
    exportButton.on("click", function () {
        const reportContent = reportContentDiv.html();
        // Code here to handle exporting the report content
    });
});

function exportExcel() {
    const exportForm = $("#exportForm");
    const reportType = $("#report_type").val();
    const groupCompany = $("#group_company").val();
    const company = $("#company").val();
    const location = $("#location").val();
    const period = $("#filterYear").val();
    
    
    $("#export_report_type").val(reportType);
    $("#export_group_company").val(groupCompany);
    $("#export_company").val(company);
    $("#export_location").val(location);
    $("#export_period").val(period);
    
    // Submit the form
    exportForm.submit();
}

window.exportExcel = exportExcel;

function handleDeleteEmployeePA(element) {
    var id = element.getAttribute('data-id');

    Swal.fire({
        title: 'Are you sure?',
        text: "This Employee will be terminated!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: "#3e60d5",
        cancelButtonColor: "#f15776",
        confirmButtonText: 'Yes, delete it!',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            // If confirmed, make AJAX DELETE request using Fetch
            fetch('/admemployeedestroy', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                // Success: Show success message
                if (data.success) {
                    Swal.fire(
                        'Deleted!',
                        'The employee has been terminated.',
                        'success'
                    );
                    // Optionally, remove the deleted employee from the DOM (e.g., remove the row from a table)
                    element.closest('tr').remove();
                }
            })
            .catch(error => {
                // Error: Show error message
                Swal.fire(
                    'Error!',
                    'There was a problem deleting the employee.',
                    'error'
                );
            });
        }
    });
}

// Make the function accessible globally
window.handleDeleteEmployeePA = handleDeleteEmployeePA;


function showEditModal(employee) {
    // Isi data dari karyawan yang akan diedit ke dalam modal
    document.getElementById('editEmployeeId').value = employee.employee_id;
    document.getElementById('editFullname').value = employee.fullname;
    document.getElementById('editDateOfJoining').value = employee.date_of_joining;
    document.getElementById('editContributionLevelCode').value = employee.contribution_level_code;
    document.getElementById('editUnit').value = employee.unit;
    document.getElementById('editDesignationName').value = employee.designation_code;
    document.getElementById('editJobLevel').value = employee.job_level;
    document.getElementById('editOfficeArea').value = employee.work_area_code;

    // Tampilkan modal
    var editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    editModal.show();
}   

window.showEditModal = showEditModal;

