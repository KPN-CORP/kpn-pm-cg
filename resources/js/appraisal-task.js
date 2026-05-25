import $ from 'jquery';

function yearAppraisalTask(button) {
    showLoader();
    // Get the form
    var form = $(button).closest('form');

    // Submit the form
    form.submit();
}

window.yearAppraisalTask = yearAppraisalTask;

$(document).ready(function() {
    // Initialize DataTable for Team Appraisal
    var tableTeam = $('#tableAppraisalTeam').DataTable({
        stateSave: true,
        autoWidth: false,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="ri-download-cloud-2-line fs-16 me-1"></i>Download Report',
                className: 'btn btn-sm btn-outline-success',
                title: 'My Appraisal Team',
                exportOptions: {
                    columns: ':not(:first-child):not(:last-child)'
                },
                customize: function(csv) {
                    let csvRows = csv.split('\n');
                    let dt = $('#tableAppraisalTeam').DataTable();
                    let data = dt.rows().data().toArray();

                    // ambil semua key score dari baris pertama yang valid
                    let allScoreKeys = [];
                    if (data[0]?.kpi) {
                        allScoreKeys = Object.keys(data[0].kpi).filter(k => k.toLowerCase().includes('score'));
                    }

                    // Tambahkan header dinamis
                    csvRows[0] = csvRows[0].replace(/\r?\n|\r/g, '') + ',' + allScoreKeys.join(',');

                    for (let i = 1; i < csvRows.length; i++) {
                        if (csvRows[i] && data[i - 1]) {
                            let rowData = data[i - 1];
                            let scores = getScores(rowData);

                            let rowColumns = csvRows[i].split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);

                            const SCORE_START_INDEX = 7;

                            // masukkan semua score sesuai urutan key
                            allScoreKeys.forEach((k, index) => {
                                let targetIndex = SCORE_START_INDEX + index;
                                // Jika kolom di rowColumns sudah ada, ganti nilainya
                                if (targetIndex < rowColumns.length) {
                                    rowColumns[targetIndex] = scores[k];
                                } else {
                                    // Jika belum ada (kasus edge), tambahkan saja (seperti sebelumnya)
                                    rowColumns.push(scores[k]);
                                }
                            });

                            csvRows[i] = rowColumns.map(value => {

                                let stringValue = String(value);
                                stringValue = stringValue.replace(/\r/g, '');
                                if (stringValue.startsWith('"') && stringValue.endsWith('"')) {
                                    stringValue = stringValue.slice(1, -1);
                                }
                                if (stringValue.includes(',') || stringValue.includes('"')) {
                                    return `"${stringValue.replace(/"/g, '""')}"`;
                                }
                                return stringValue;
                            }).join(",");
                        }
                    }

                    return csvRows.join('\n');
                }
            }
        ],
        fixedColumns: {
            leftColumns: 0,
            rightColumns: 1
        },
        scrollCollapse: true,
        scrollX: true,
        paging: false,
        ajax: {
            url: '/appraisals-task/teams-data' + window.location.search,
            type: 'GET',
            dataSrc: ''
        },
        columns: [
            {
                className: 'dt-control',
                orderable: false,
                data: null,
                defaultContent: ''
             },
            { data: 'employee.employee_id' },
            { data: 'employee.fullname' },
            { data: 'employee.designation' },
            { data: 'employee.office_area' },
            {
                data: 'contributorStatus',
                className: 'text-center',
                render: function (data, type) {
                    if (type !== 'display') return data;

                    const val = (data ?? '').toString();
                    const cls =
                    val.toLowerCase() === 'draft'    ? 'secondary' :
                    val.toLowerCase() === 'approved' ? 'success'   :
                                                        'light text-body';

                    // escape singkat
                    const safe = val.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

                    return `<span class="badge bg-${cls}">${safe}</span>`;
                }
            },
            { data: 'approval_date',  className: 'text-end' },
            { data: 'action', className: 'sorting_1' }
        ]
    });

    // Add event listener for both tables
    addChildRowToggle(tableTeam, '#tableAppraisalTeam');
});


$(document).ready(function() {

    // Initialize DataTable for 360 Appraisal
    var table360 = $('#tableAppraisal360').DataTable({
        stateSave: true,
        autoWidth: false,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'csvHtml5',
                text: '<i class="ri-download-cloud-2-line fs-16 me-1"></i>Download Report',
                className: 'btn btn-sm btn-outline-success',
                title: 'My Appraisal 360',
                exportOptions: {
                    columns: ':not(:first-child):not(:last-child)'
                },
                customize: function(csv) {
                    let csvRows = csv.split('\n');
                    let dt = $('#tableAppraisal360').DataTable();
                    let data = dt.rows().data().toArray();

                    // ambil semua key score dari baris pertama yang valid
                    let allScoreKeys = [];
                    if (data[0]?.kpi) {
                        allScoreKeys = Object.keys(data[0].kpi).filter(k => k.toLowerCase().includes('score'));
                    }

                    // Tambahkan header dinamis
                    csvRows[0] = csvRows[0].replace(/\r?\n|\r/g, '') + ',' + allScoreKeys.join(',');

                    for (let i = 1; i < csvRows.length; i++) {
                        if (csvRows[i] && data[i - 1]) {
                            let rowData = data[i - 1];
                            let scores = getScores(rowData);

                            let rowColumns = csvRows[i].split(/,(?=(?:(?:[^"]*"){2})*[^"]*$)/);
                            while (rowColumns.length < 10) rowColumns.push('');

                            // masukkan semua score sesuai urutan key
                            allScoreKeys.forEach(k => {
                                rowColumns.push(scores[k]);
                            });

                            csvRows[i] = rowColumns.map(value => {

                                let stringValue = String(value);

                                stringValue = stringValue.replace(/\r/g, '');

                                if (stringValue.startsWith('"') && stringValue.endsWith('"')) {
                                    stringValue = stringValue.slice(1, -1);
                                }

                                if (stringValue.includes(',') || stringValue.includes('"')) {
                                    return `"${stringValue.replace(/"/g, '""')}"`;
                                }

                                return stringValue;
                            }).join(",");
                        }
                    }

                    return csvRows.join('\n');
                }

            }
        ],
        fixedColumns: {
            leftColumns: 0,
            rightColumns: 1
        },
        scrollCollapse: true,
        scrollX: true,
        paging: false,
        ajax: {
            url: '/appraisals-task/360-data' + window.location.search,
            type: 'GET',
            dataSrc: function (json) {
                const rows = Array.isArray(json) ? json : json.data ?? [];

                // filter baris yang seluruh value-nya 0, null, atau kosong
                return rows
                    .map(item => {
                        if (item.kpi) delete item.kpi; // tetap hapus kpi kalau ada
                        return item;
                    })
                    .filter(item => {
                        // ambil semua nilai numerik di level atas
                        const values = Object.values(item)
                            .filter(v => typeof v === 'number' || (!isNaN(v) && v !== null && v !== ''));

                        // cek apakah semua nilai 0
                        return values.some(v => Number(v) !== 0);
                    });
            }
        },
        columns: [
            {
                className: 'dt-control',
                orderable: false,
                data: null,
                defaultContent: ''
            },
            { data: 'employee.employee_id' },
            { data: 'employee.fullname' },
            { data: 'employee.designation' },
            { data: 'employee.office_area' },
            {
                data: 'contributorStatus',
                className: 'text-center',
                render: function (data, type) {
                    if (type !== 'display') return data;

                    const val = (data ?? '').toString();
                    const cls =
                    val.toLowerCase() === 'draft'    ? 'secondary' :
                    val.toLowerCase() === 'approved' ? 'success'   :
                                                        'light text-body';

                    // escape singkat
                    const safe = val.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

                    return `<span class="badge bg-${cls}">${safe}</span>`;
                }
            },
            { data: 'approval_date', className: 'text-end' },
            { data: 'action', className: 'sorting_1' }
        ]
    });

    // Add event listener for both tables
    addChildRowToggle(table360, '#tableAppraisal360');

});

// Function to get formatted scores for export
function getScores(rowData) {
    const scores = {};

    if (rowData?.kpi && typeof rowData.kpi === 'object') {
        Object.entries(rowData.kpi).forEach(([key, value]) => {
            if (key.toLowerCase().includes('score')) {
                scores[key] = value ?? 'N/A';
            }
        });
    }

    // Tambahkan default jika belum ada
    const defaults = ['total_score', 'kpi_score', 'culture_score', 'leadership_score'];
    defaults.forEach(d => {
        if (!(d in scores)) scores[d] = 'N/A';
    });

    return scores;
}


// Function to add child row toggle functionality
function addChildRowToggle(table, tableId, speed = 250) {
    $(tableId + ' tbody').on('click', 'td.dt-control', function () {
        var tr = $(this).closest('tr');
        var row = table.row(tr);

        if (row.child.isShown()) {
            // Close the row with animation
            $('div.slider', row.child()).slideUp(speed, function () {
                row.child.hide(); // After the slide-up animation, hide the row
                tr.removeClass('shown');
            });
        } else {
            // Format and show the child row but initially hide it with display:none
            row.child('<div class="slider" style="display:none;">' + formatChildRow(row.data()) + '</div>').show();
            // Then slide it down to make it visible with animation
            $('div.slider', row.child()).slideDown(speed);
            tr.addClass('shown');
        }
    });
}


// Function to format child row content
function formatChildRow(rowData) {
    // console.log(rowData);

    if (!rowData?.kpi || !rowData.kpi.kpi_status) {
        return '<div>No scores available</div>';
    }

    // Ambil semua key yang mengandung 'score'
    const scoreEntries = Object.entries(rowData.kpi)
        .filter(([key, value]) => key.toLowerCase().includes('score') && value !== undefined && value !== null && value !== 0 && value !== '');

    if (scoreEntries.length === 0) {
        return '<div>No scores available</div>';
    }

    // Buat konten HTML dinamis
    let content = '';
    scoreEntries.forEach(([key, value], i) => {
        // Format nama label jadi rapi, misal: total_score_1 → Total Score 1
        const label = key
            .replace(/_/g, ' ')        // ubah underscore ke spasi
            .replace(/\b\w/g, l => l.toUpperCase()); // kapitalisasi tiap kata

        content += `
            <div class="row">
                <div class="col-3 col-md-2">
                    <div class="mb-1 border-bottom border-secondary"><strong>${label}</strong></div>
                </div>
                <div class="col-auto">
                    <div class="mb-1 border-bottom border-secondary"><strong>: ${value}</strong></div>
                </div>
            </div>`;
    });

    return content;
}


$(document).ready(function() {
    let currentStep = $('.step').data('step');
    const totalSteps = $('.form-step').length;

    function updateStepper(step) {
        // Update circles
        $('.circle').removeClass('active completed');
        $('.circle').each(function(index) {
            if (index < step - 1) {
                $(this).addClass('completed');
            } else if (index == step - 1) {
                $(this).addClass('active');
            }
        });

        $('.label').removeClass('active');
        $('.label').each(function(index) {
            if (index < step - 1) {
                $(this).addClass('active');
            } else if (index == step - 1) {
                $(this).addClass('active');
            }
        });

        // Update connectors
        $('.connector').each(function(index) {
            if (index < step - 1) {
                $(this).addClass('completed');
            } else {
                $(this).removeClass('completed');
            }
        });

        // Update form steps visibility
        $('.form-step').removeClass('active').hide();
        $(`.form-step[data-step="${step}"]`).addClass('active').fadeIn();

        // Update navigation buttons
        if (step === 1) {
            $('.prev-btn').hide();
        } else {
            $('.prev-btn').show();
        }

        if (step === totalSteps) {
            $('.next-btn').hide();
            $('.submit-btn').show();
        } else {
            $('.next-btn').show();
            $('.submit-btn').hide();
        }
    }

    function validateInput(input) {
        // Allow any non-empty string except "-"
        const regex = /^-?\d+(\.\d+)?$/; // Match positive/negative integers and decimals
        return regex.test(input) || (input !== "-" && input.trim() !== "");
    }

    function validateStep(step) {
        let isValid = true;
        let firstInvalidElement = null;

        $(`.form-step[data-step="${step}"] .form-select, .form-step[data-step="${step}"] .form-control`).each(function() {
            const inputVal = $(this).val();

            // Use validateInput to validate the field's value
            if (!validateInput(inputVal)) {
                // console.log(inputVal);
                $(this).siblings('.error-message').text(errorMessages);
                $(this).addClass('border-danger');
                isValid = false;
                if (firstInvalidElement === null) {
                    firstInvalidElement = $(this);
                }
            } else {
                $(this).removeClass('border-danger');
                $(this).siblings('.error-message').text('');
            }
        });

        // Focus the first invalid element if any
        if (firstInvalidElement) {
            firstInvalidElement.focus();
        }

        return isValid;
    }


    $('.next-btn').click(function() {
        if (validateStep(currentStep)) {
            currentStep++;
            updateStepper(currentStep);
        }
    });

    // $('.submit-btn').click(function () {
    //     let submitType = $(this).data('id');
    //     document.getElementById("submitType").value = submitType;
    //     if (validateStep(currentStep)) {
    //         let title1;
    //         let title2;
    //         let text;
    //         let confirmText;

    //         const spinner = $(this).find(".spinner-border");

    //         if (submitType === "submit_form") {
    //             title1 = "Submit From?";
    //             text = "This can't be revert";
    //             title2 = "Appraisal submitted successfully!";
    //             confirmText = "Submit";

    //             Swal.fire({
    //                 title: title1,
    //                 text: text,
    //                 showCancelButton: true,
    //                 confirmButtonColor: "#3e60d5",
    //                 cancelButtonColor: "#f15776",
    //                 confirmButtonText: confirmText,
    //                 reverseButtons: true,
    //             }).then((result) => {
    //                 if (result.isConfirmed) {
    //                     // Disable submit button
    //                     $(this).prop("disabled", true);
    //                     $(this).addClass("disabled");

    //                     // Show spinner if it exists
    //                     if (spinner.length) {
    //                         spinner.removeClass("d-none");
    //                     }

    //                     document.getElementById("formAppraisalUser").submit();

    //                     // Show success message
    //                     Swal.fire({
    //                         title: title2,
    //                         icon: "success",
    //                         showConfirmButton: false,
    //                         timer: 1500, // Optional: Auto close the success message after 1.5 seconds
    //                     });
    //                 }
    //             });
    //         }

    //         return false; // Prevent default form submission
    //     }
    // });

    $('.prev-btn').click(function() {
        currentStep--;
        updateStepper(currentStep);
    });

    updateStepper(currentStep);
});


$(document).ready(function() {
    $('[id^="achievement"]').on('input', function() {
        let $this = $(this); // Cache the jQuery object
        let currentValue = $this.val();
        let validNumber = currentValue.replace(/[^0-9.-]/g, ''); // Allow digits, decimal points, and negative signs

        // Ensure only one decimal point and one negative sign at the start
        if (validNumber.indexOf('-') > 0) {
            validNumber = validNumber.replace('-', ''); // Remove if negative sign is not at the start
        }
        if ((validNumber.match(/\./g) || []).length > 1) {
            validNumber = validNumber.replace(/\.+$/, ''); // Remove extra decimal points
        }

        $this.val(validNumber);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var $window = $(window);
    var $stickyElement = $('.detail-employee');
    if ($stickyElement.length > 0) {
        var stickyOffset = $stickyElement.offset().top;

        function handleScroll() {
            if ($window.width() > 768) { // Check if viewport width is greater than 768px
                if ($window.scrollTop() > stickyOffset) {
                    $stickyElement.addClass('sticky-top');
                    $stickyElement.addClass('sticky-padding');
                } else {
                    $stickyElement.removeClass('sticky-top');
                    $stickyElement.removeClass('sticky-padding');
                }
            } else {
                $stickyElement.removeClass('sticky-top');
                $stickyElement.removeClass('sticky-padding');
            }
        }

        // Run on scroll and resize events
        $window.on('scroll', handleScroll);
        $window.on('resize', function() {
            // Update the stickyOffset on resize
            stickyOffset = $stickyElement.offset().top;
            handleScroll();
        });

        // Initial check
        handleScroll();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const teamTab = document.getElementById("teamTab");
    const reviewTab = document.getElementById('360-review-tab');

    if (teamTab && reviewTab) {
        teamTab.addEventListener('shown.bs.tab', function () {
            teamTab.classList.remove('btn-outline-secondary');
            teamTab.classList.add('btn-outline-primary');

            reviewTab.classList.remove('btn-outline-primary');
            reviewTab.classList.add('btn-outline-secondary');
        });

        reviewTab.addEventListener('shown.bs.tab', function () {
            reviewTab.classList.remove('btn-outline-secondary');
            reviewTab.classList.add('btn-outline-primary');

            teamTab.classList.remove('btn-outline-primary');
            teamTab.classList.add('btn-outline-secondary');
        });
        // Event listeners for 'shown' event when the tab becomes active
    }

});
