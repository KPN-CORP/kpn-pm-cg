import { log } from "handlebars";
import $ from "jquery";

import Swal from "sweetalert2";
window.Swal = Swal;

const userId = window.userID || null;

$(document).ready(function () {
    
    const reportFiles = window.reportFile || null;
    const reportFileDates = window.reportFileDate || null;
    const jobs = window.jobs || null;

    function getCurrentDateTime() {
        const now = new Date();
        const weekday = now.toLocaleString("en-US", { weekday: "long" });
        const day = String(now.getDate()).padStart(2, "0"); // Add leading zero if single digit
        const month = String(now.getMonth() + 1).padStart(2, "0"); // Get month (0-11), so add 1
        const year = now.getFullYear();

        // Get hours and minutes for the time (in 12-hour format)
        const hours = now.getHours() % 12 || 12; // 12-hour format, 0 becomes 12
        const minutes = String(now.getMinutes()).padStart(2, "0");
        const ampm = now.getHours() >= 12 ? "PM" : "AM"; // AM/PM

        return `${weekday}, ${day}/${month}/${year} ${hours}:${minutes} ${ampm}`;
    }

    $("#adminAppraisalTable").DataTable({
        stateSave: true,
        dom: "Bfrtip",
        buttons: [
            {
                extend: "csvHtml5",
                text: '<i class="ri-download-cloud-2-line fs-16 me-1"></i>Download Report',
                className: "btn btn-sm btn-outline-success me-1 mb-1",
                title: "PA Details",
                exportOptions: {
                    columns: ":not(:last-child)", // Excludes the last column (Details)
                    format: {
                        body: function (data, row, column, node) {
                            // Check if the <td> has a 'data-id' attribute and use that for the export
                            var dataId = $(node).attr("data-id");
                            return dataId ? dataId : data; // Use the data-id value if available, else fallback to default text
                        },
                    },
                },
            },
            {
                text: '<i class="ri-refresh-line fs-16 me-1 download-detail-icon"></i><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>Generate Report Details',
                className:
                    "btn btn-sm btn-outline-success mb-1 report-detail-btn me-1",
                available: function() {
                    return $('#permission-reportpadetail').attr('data-report-pa-detail') === 'true';
                },
                action: function (e, dt, node, config) {
                    let headers = dt
                        .columns(":not(:last-child)")
                        .header()
                        .toArray()
                        .map((header) => $(header).text().trim());
                    let rowNodes = dt
                        .rows({ filter: "applied" })
                        .nodes()
                        .toArray();
                    let rowData = dt
                        .rows({ filter: "applied" })
                        .data()
                        .toArray();
                    const BATCH_SIZE = 100; // Number of rows per Excel file

                    // Calculate number of batches needed
                    const totalBatches = Math.ceil(rowData.length / BATCH_SIZE);

                    const MINUTES_PER_BATCH = 1; // Example: each batch takes 5 minutes
                    const totalMinutes = totalBatches * MINUTES_PER_BATCH;


                    let combinedData = rowData.map((row, rowIndex) => {
                        let rowObject = {};
                        row.slice(0, -1).forEach((cellContent, colIndex) => {
                            let header = headers[colIndex];
                            let cellNode = $(rowNodes[rowIndex])
                                .find("td")
                                .eq(colIndex);
                            let dataId = cellNode.attr("data-id");
                            rowObject[header] = {
                                dataId: dataId ? dataId : cellContent,
                            };
                        });
                        return rowObject;
                    });

                    let reportDetailButton = document.querySelector(".report-detail-btn");
                    const spinner = reportDetailButton.querySelector(".spinner-border");
                    const icon = reportDetailButton.querySelector(".download-detail-icon");
                    
                    let downloadDetailButton = document.querySelector(".download-detail-btn");
                    let iconElement = downloadDetailButton.querySelector("i");
                    
                    let groupCompany = document.getElementById("group_company");                    
                    let filter = document.getElementById("filter");                    
                    let filterYear = document.getElementById("filter_year").value;                    

                    if(groupCompany.value){
                        if (combinedData.length > 0) {                        
                            document
                                .querySelectorAll(".report-detail-btn")
                                .forEach((button) => (button.disabled = true));
                            spinner.classList.remove("d-none");
                            icon.classList.add("d-none");
    
                            downloadDetailButton.classList.add("disabled");
    
                            downloadDetailButton.innerHTML = `
                                <i class="ri-loop-right-line fs-16 me-1 download-detail-icon"></i>
                                <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                Generating Reports
                            `;
    
                            // Split data into batches
                            const batches = [];
                            for (
                                let i = 0;
                                i < combinedData.length;
                                i += BATCH_SIZE
                            ) {
                                batches.push(combinedData.slice(i, i + BATCH_SIZE));
                            }
    
                            // Start the export process for all batches
                            fetch("/export-appraisal-detail", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/json",
                                    "X-CSRF-TOKEN": document
                                        .querySelector('meta[name="csrf-token"]')
                                        .getAttribute("content"),
                                },
                                body: JSON.stringify({
                                    headers: headers,
                                    data: combinedData,
                                    batchSize: BATCH_SIZE,
                                    period: filterYear,
                                }),
                            })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (
                                        data.message ===
                                        "Export is being processed in the background."
                                    ) {
                                        alert(
                                            `The reports are being processed. Please wait a moment. Estimated time remaining: ${totalMinutes} minutes.`
                                        );
                                        intervalCheckFile(true);
                                        checkJobs(userId);
                                        resetUI();
                                    } else {
                                        console.error("Unexpected response:", data);
                                        alert("Failed to start export.");
                                    }
                                })
                                .catch((error) => {
                                    console.error("Error:", error);
                                    alert("Failed to start the export process.");
                                });
                        } else {
                            alert("No employees found in the current table view.");
                        }
                    } else {
                        alert("Please select Group Company for your reports.");
                        filter.click();
                        groupCompany.focus();
                    }

                    function resetUI() {
                        spinner.classList.add("d-none");
                        icon.classList.remove("d-none");
                    }
                },
            },
            {
                className:
                    "btn btn-sm btn-light mb-1 download-detail-btn disabled",
                available: function() {
                    return $('#permission-reportpadetail').attr('data-report-pa-detail') === 'true';
                },
                action: function (e, dt, node, config) {
                    let headers = dt
                        .columns(":not(:last-child)")
                        .header()
                        .toArray()
                        .map((header) => $(header).text().trim());
                    let rowNodes = dt
                        .rows({ filter: "applied" })
                        .nodes()
                        .toArray();
                    let rowData = dt
                        .rows({ filter: "applied" })
                        .data()
                        .toArray();
                    const BATCH_SIZE = 100; // Number of rows per Excel file

                    // Calculate number of batches needed
                    const totalBatches = Math.ceil(rowData.length / BATCH_SIZE);

                    let combinedData = rowData.map((row, rowIndex) => {
                        let rowObject = {};
                        row.slice(0, -1).forEach((cellContent, colIndex) => {
                            let header = headers[colIndex];
                            let cellNode = $(rowNodes[rowIndex])
                                .find("td")
                                .eq(colIndex);
                            let dataId = cellNode.attr("data-id");
                            rowObject[header] = {
                                dataId: dataId ? dataId : cellContent,
                            };
                        });
                        return rowObject;
                    });

                    let reportDetailButton =
                        document.querySelector(".report-detail-btn");
                    const spinner =
                        reportDetailButton.querySelector(".spinner-border");
                    const icon = reportDetailButton.querySelector(
                        ".download-detail-icon"
                    );

                    let file = reportFiles;

                    fetch(`/appraisal-details/download/${file}`)
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error(
                                    `HTTP error! status: ${response.status}`
                                );
                            }
                            return response.blob();
                        })
                        .then((blob) => {
                            const link = document.createElement("a");
                            const url = URL.createObjectURL(blob);
                            link.href = url;
                            link.download = file;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            URL.revokeObjectURL(url);
                        })
                        .catch((error) => {
                            console.error(
                                "Error in download process:",
                                error
                            );
                            alert(
                                "There was an error downloading the file. Please try again."
                            );
                        });

                },
            },
        ],
        fixedColumns: {
            leftColumns: 0,
            rightColumns: 1,
        },
        scrollCollapse: true,
        scrollX: true,
        initComplete: function (settings, json) {
            // Get the current date and time
            const dateTime = getCurrentDateTime();

            // Insert the current date/time below the buttons
            const dateTimeHTML = `<div class="text-right mt-2" id="currentDateTime">${dateTime}</div>`;

            // Append the date and time below the button container
            $(this)
                .closest(".dataTables_wrapper")
                .find(".dt-buttons")
                .after(dateTimeHTML);
        },
    });

    // Button selector
    let downloadDetailButton = document.querySelector(".download-detail-btn");
    let reportDetailButton = document.querySelector(".report-detail-btn");

    // Check if reportFiles has value
    if (downloadDetailButton && window.jobs) {
        if (reportFiles && reportFiles.length > 0) {
            
            downloadDetailButton.classList.remove("disabled"); // Remove 'disabled' class
            downloadDetailButton.innerHTML = `
                <i class="ri-download-cloud-2-line fs-16 me-1 download-detail-icon"></i>
                <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                Reports last generated on ${reportFileDates}
            `;
        } else if (jobs && jobs.length > 0) {
            downloadDetailButton.innerHTML = `
                                <i class="ri-loop-right-line fs-16 me-1 download-detail-icon"></i>
                                <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                Generating Reports
                            `;
            reportDetailButton.classList.add("disabled");
        }else {
            downloadDetailButton.innerHTML = `
                <i class="ri-download-cloud-2-line fs-16 me-1 download-detail-icon"></i>
                <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                No Reports
            `;
        }
    }

    intervalCheckFile();
    
});

// Run the function every 60 seconds (60000 ms)
let intervalId; // Store the interval ID globally

function intervalCheckFile(status) {
    if (window.jobs){
        if ((window.jobs && window.jobs.length > 0) || status) {
            intervalId = setInterval(() => {
                checkFileAvailability(status);
            }, 60000); // Execute every 60 seconds
        } else {
            console.log("Jobs is empty. Skipping check.");
        }
    }
}

function checkFileAvailability(status) {
    if(window.jobs){
        if ((window.jobs && window.jobs.length > 0) || status) {
            console.log(`Checking reports.`);
            const baseFileName = `appraisal_details_${userId}`;
            fetch("/check-file", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute("content"),
                },
                body: JSON.stringify({ file: baseFileName }),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.exists) {
                        console.log("File found. Reloading the page.");
                        clearInterval(intervalId); // Kill the interval
                        location.reload(); // Reload the page
                    }
                })
                .catch((error) => console.error("Error checking file:", error));
        } else {
            console.log("No report processing.");
        }
    }
}

function checkJobs(id) {
    console.log(`Checking jobs.`);
        fetch("/check-jobs", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),
            },
            body: JSON.stringify({ user: id }),
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.exists) {
                console.log(data.message);
            }
        })
        .catch((error) =>
            console.error("Error checking jobs:", error)
        );
}

document.addEventListener("DOMContentLoaded", function () {
    const appraisalId = document.getElementById("appraisal_id");
    const typeButtons = document.querySelectorAll(".type-button");
    const detailContent = document.getElementById("detailContent");
    const loadingSpinner = document.getElementById("loadingSpinner");

    if(appraisalId && typeButtons && detailContent){

        typeButtons.forEach((button) => {
            button.addEventListener("click", function () {
                const contributorId = this.dataset.id;
                const id = contributorId + "_" + appraisalId.value;

                // console.log(id); (for debugging)
    
                // Check if id is null or undefined
                if (!contributorId) {
                    detailContent.innerHTML = `
                                <div class="alert alert-secondary" role="alert">
                                    No data available for this item.
                                </div>
                            `;
                    return; // Exit the function early if id is null or invalid
                }
    
                // Show loading spinner
                loadingSpinner.classList.remove("d-none");
                detailContent.innerHTML = "";
    
                // Make AJAX request
                fetch(`/admin-appraisal/get-detail-data/${id}`)
                    .then((response) => {
                        // Hide the loading spinner
                        loadingSpinner.classList.add("d-none");
    
                        // Check if the response is successful (status code 200-299)
                        if (!response.ok) {
                            throw new Error(
                                `HTTP error! status: ${response.status}`
                            );
                        }
    
                        return response.text();
                    })
                    .then((html) => {
                        // Check if the response is empty
                        if (!html.trim()) {
                            detailContent.innerHTML = `
                            <div class="alert alert-secondary" role="alert">
                                No data available for this item.
                            </div>
                        `;
                        } else {
                            detailContent.innerHTML = html;
                        }
                    })
                    .catch((error) => {
                        // Handle any errors, including network errors and non-OK responses
                        loadingSpinner.classList.add("d-none");
                        detailContent.innerHTML = `
                        <div class="alert alert-secondary" role="alert">
                            No data available for this item.
                        </div>
                    `;
                        console.error("Error:", error);
                    });
            });
        });
    }
});