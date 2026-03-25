import $ from 'jquery';

import Swal from "sweetalert2";
window.Swal = Swal;

$('#period').datepicker({
    format: "yyyy",           // Display only the year
    minViewMode: "years",      // Only allow year selection
    viewMode: "years",         // Start with the year view
    orientation: "bottom",
    autoclose: true,
});

$(document).ready(function() {
    const layerTable = $("#weightageTable").DataTable({
        fixedColumns: {
            leftColumns: 0,
            rightColumns: 1
        },
        pageLength: 25,
        scrollCollapse: true,
        scrollX: true
    });
})

$(document).ready(function() {
    $("input[id^='weightage']").each(function() {
        const $input = $(this);
        
        $input.on("keyup", function() {
            // Only allow numbers
            $input.val($input.val().replace(/[^0-9]/g, ''));
            
            // Only set limits if there is a value
            if ($input.val() !== '') {
                let numValue = parseInt($input.val(), 10);
                if (numValue > 90) {
                    $input.val(90);
                } else if (numValue < 0) {
                    $input.val(0);
                }
            }
        });
    });
});


document.addEventListener('DOMContentLoaded', function() {
    const archiveButtons = document.querySelectorAll('.archive');

    archiveButtons.forEach(function(archiveButton) {
        archiveButton.addEventListener('click', async function(event) {
            const archiveId = event.currentTarget.getAttribute('data-id');
            const result = await Swal.fire({
                title: 'Archive Weightage?',
                text: "This action will archive the selected item.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, archive it!',
                reverseButtons: true
            });

            if (result.isConfirmed) {
                $.ajax({
                    url: `/archive-weightage`, // Adjust based on your route
                    method: 'POST',
                    data: {
                        id: archiveId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire('Archived!', response.message, 'success').then(() => {
                            window.location.href = '/admin-weightage';
                        });
                    },
                    error: function(xhr) {
                        const errorMessage = xhr.responseJSON?.message || 'There was a problem archiving the item.';
                        Swal.fire('Error!', errorMessage, 'error').then(() => {
                            window.location.href = '/admin-weightage';
                        });
                    }
                });
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {

    const form = document.getElementById('form-weightage');
    const submitButton = document.getElementById('submit-weightage');
    if(submitButton){
        const submitId = submitButton.getAttribute('data-id');
        const spinner = submitButton.querySelector('.spinner-border');
    }
    const requiredFields = document.querySelectorAll('.required-input');
    let hasErrors = false;

    // Validation for job_level
    function validateJobLevel() {
        const jobLevelSelects = document.querySelectorAll('select[id^="job_level"]');
        jobLevelSelects.forEach(function(select) {
            const selectedOptions = $(select).val(); // Use jQuery to get selected values from select2
            const errorMessageElement = select.closest('.mb-3').querySelector('.error-message');

            if (!selectedOptions || selectedOptions.length === 0) {
                select.classList.add('is-invalid');
                errorMessageElement.textContent = 'Please select at least one job level.';
                hasErrors = true;
            } else {
                select.classList.remove('is-invalid');
                errorMessageElement.textContent = '';
            }
        });
    }

    // Validation for dynamically added weightage inputs
    function validateWeightages() {
        const assessmentForms = document.querySelectorAll('.assessment-form');
        assessmentForms.forEach(function(form, formIndex) {
            const weightageInputs = form.querySelectorAll(`input[name^='weightage-${formIndex}']`);
            const totalInput = form.querySelector(`#total-${formIndex}-0`);
            let totalWeightage = 0;

            weightageInputs.forEach(function(input) {
                const weightageValue = parseInt(input.value) || 0;

                if (weightageValue < 0 || weightageValue > 90) {
                    input.classList.add('is-invalid');
                    const errorMessageElement = input.closest('.mb-3').querySelector('.error-message');
                    errorMessageElement.textContent = 'Weightage must be between 0 and 90.';
                    hasErrors = true;
                } else {
                    input.classList.remove('is-invalid');
                    const errorMessageElement = input.closest('.mb-3').querySelector('.error-message');
                    errorMessageElement.textContent = '';
                }

                totalWeightage += weightageValue;
            });

            totalInput.value = totalWeightage;

            // Check if the total weightage is exactly 90
            if (totalWeightage !== 90) {
                totalInput.classList.add('is-invalid');
                const errorMessageElement = totalInput.closest('.mb-3').querySelector('.error-message');
                errorMessageElement.textContent = 'Total weightage must equal 90.';
                hasErrors = true;
            } else {
                totalInput.classList.remove('is-invalid');
                const errorMessageElement = totalInput.closest('.mb-3').querySelector('.error-message');
                errorMessageElement.textContent = '';
            }
        });
    }

    // Validation for form-name select fields
    function validateFormNames() {
        const formNameSelects = document.querySelectorAll('select[id^="form-name"]');
        formNameSelects.forEach(function(select) {
            const errorMessageElement = select.closest('.mb-3').querySelector('.error-message');
            if (select.disabled) {
                // Skip validation for disabled select (like for KPI competency)
                return;
            }

            const selectedOption = select.value.trim();

            if (!selectedOption) {
                select.classList.add('is-invalid');
                errorMessageElement.textContent = 'Please select a form name.';
                hasErrors = true;
            } else {
                select.classList.remove('is-invalid');
                errorMessageElement.textContent = '';
            }
        });
    }

    // New validation function for checking MasterWeightage data
    async function validateConfiguration(period, groupCompany) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '/check-master-weightage',
                method: 'POST',
                data: {
                    period: period,
                    group_company: groupCompany,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.exists) {
                        Swal.fire({
                            title: "Invalid Configuration",
                            text: `Weightage configuration on ${groupCompany} with period ${period} already exists. Please adjust your configuration.`,
                            icon: "error",
                            confirmButtonColor: "#f15776"
                        });

                        // Mark fields as invalid
                        const periodInput = document.querySelector('#period');
                        const groupCompanyInput = document.querySelector('#weightage-group-company');

                        periodInput.classList.add('is-invalid');
                        groupCompanyInput.classList.add('is-invalid');

                        const periodError = periodInput.closest('.mb-2').querySelector('.error-message');
                        const groupCompanyError = groupCompanyInput.closest('.mb-2').querySelector('.error-message');

                        periodError.textContent = 'Please adjust this fields';
                        groupCompanyError.textContent = 'Please adjust this fields';

                        hasErrors = true;
                        resolve(false); // Indicate that there are errors
                    } else {
                        resolve(true); // No errors, validation successful
                    }
                },
                error: function() {
                    Swal.fire({
                        title: "Error",
                        text: "An error occurred while checking configuration data.",
                        icon: "error",
                        confirmButtonColor: "#f15776"
                    });
                    hasErrors = true;
                    reject(); // Reject the promise in case of error
                }
            });
        });
    }
    

    // Validation on form submit
    if(submitButton){
        const submitId = submitButton.getAttribute('data-id');
        const spinner = submitButton.querySelector('.spinner-border');

        submitButton.addEventListener('click', async function(event) {
            hasErrors = false;
    
            // Validate all required fields
            requiredFields.forEach(function(input) {
                const errorMessageElement = input.closest('.mb-2').querySelector('.error-message');
                const inputValue = input.classList.contains('select2-hidden-accessible')
                    ? $(input).val() // For select2
                    : input.value.trim(); // For regular inputs
    
                if (!inputValue) {
                    input.classList.add('is-invalid');
                    if (errorMessageElement) {
                        errorMessageElement.textContent = 'This field is required';
                    }
                    hasErrors = true;
                } else {
                    input.classList.remove('is-invalid');
                    if (errorMessageElement) {
                        errorMessageElement.textContent = '';
                    }
                }
            });
    
            // Additional validation: Check MasterWeightage configuration
            const period = document.getElementById('period').value; // Ensure element ID is correct
            const groupCompany = document.getElementById('weightage-group-company').value; // Ensure element ID is correct
    
            // Validate job levels
            validateJobLevel();
    
            // Validate weightages for dynamically generated forms
            validateWeightages();
    
            // Validate form names
            // validateFormNames();
    
            // Prevent form submission if there are errors or configuration is invalid
            if (hasErrors) {
                event.preventDefault();
                Swal.fire({
                    title: "Error",
                    text: "Please fill out all required fields and ensure weightages total 90.",
                    icon: "error",
                    confirmButtonColor: "#f15776"
                });
            } else {
    
                if(submitId && submitId == 'create'){
                    // Call validateConfiguration and wait for the result
                    const isInvalid = await validateConfiguration(period, groupCompany);
    
                    // If the configuration is invalid, don't proceed with showing the Swal confirmation
                    if (!isInvalid) {
                        return; // Exit if there are validation errors
                    }
                }
                    Swal.fire({
                        title: "Save weightage?",
                        text: "This can't be reverted",
                        showCancelButton: true,
                        confirmButtonColor: "#3e60d5",
                        cancelButtonColor: "#f15776",
                        confirmButtonText: "Ok, save it",
                        reverseButtons: true,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Disable submit button
                            submitButton.disabled = true; // Equivalent to .prop("disabled", true)
                            submitButton.classList.add("disabled");
        
                            // Show spinner if it exists
                            if (spinner.length) {
                                spinner.classList.remove("d-none");
                            }
        
                            // Submit the form
                            form.submit();
        
                            // Show success message
                            Swal.fire({
                                title: "Layer saved successfully!",
                                icon: "success",
                                showConfirmButton: false,
                                timer: 1500, // Optional: Auto close the success message after 1.5 seconds
                            });
                        }
                    });
                    
                return false;
    
            }
        });
    }

    // Optional: Validate as the user types or selects a value
    requiredFields.forEach(function(input) {
        input.addEventListener('input', function() {
            const errorMessageElement = input.closest('.mb-2').querySelector('.error-message');
            if (input.value.trim() !== '') {
                input.classList.remove('is-invalid');
                if (errorMessageElement) {
                    errorMessageElement.textContent = '';
                }
            }
        });

        // Handle select2-specific changes
        if ($(input).hasClass('select2-hidden-accessible')) {
            $(input).on('change', function() {
                const errorMessageElement = input.closest('.mb-2').querySelector('.error-message');
                if ($(input).val()) {
                    input.classList.remove('is-invalid');
                    if (errorMessageElement) {
                        errorMessageElement.textContent = '';
                    }
                } else {
                    input.classList.add('is-invalid');
                    if (errorMessageElement) {
                        errorMessageElement.textContent = 'This field is required';
                    }
                }
            });
        }
    });

    // Real-time validation for dynamically added weightage inputs
    document.addEventListener('input', function(event) {
        if (event.target.classList.contains('weightage-input')) {
            validateWeightages();
        }
    });
    
});

function initializeSelect2() {
        $('.assessment-form .select2').each(function() {
            if (!$(this).data('select2')) {
                $(this).select2({
                    placeholder: 'please select',
                    theme: "bootstrap-5",
                    width: '100%',
                    dropdownParent: $(this).parent()
                });
            }
        });
    }


$(document).ready(function() {
    // Function to generate a single assessment form
    function generateAssessmentForm(formIndex) {
        const formHtml = $('<div>', {
            class: 'card bg-light assessment-form mb-4'
        }).append(
            // Card Header
            $('<div>', {
                class: 'card-header pb-0'
            }).append(
                $('<h5>').text(`Assessment Form ${formIndex + 1}`)
            ),
            // Card Body
            $('<div>', {
                class: 'card-body'
            }).append(
                // Job Level Section
                $('<div>', { class: 'row' }).append(
                    $('<div>', { class: 'col-md' }).append(
                        $('<div>', { class: 'mb-3' }).append(
                            $('<h5>').text('Job Level'),
                            $('<select>', {
                                name: `job_level[${formIndex}][]`,
                                id: `job_level-${formIndex}`,
                                class: 'form-select select2',
                                multiple: true
                            }).append(
                                jobLevels.map(level => 
                                    $('<option>', {
                                        value: level.job_level,
                                        text: level.job_level
                                    })
                                )
                            ),
                            $('<div>', { class: 'text-danger error-message fs-14' })
                        )
                    )
                ),
                // Competencies Section
                defaultCompetencies.map((competency, index) => 
                    $('<div>', { class: 'row align-items-center' }).append(
                        // Competency Name
                        $('<div>', { class: 'col-md-4' }).append(
                            $('<div>', { class: 'mb-3' }).append(
                                $('<h5>').text(competency.competency_name),
                                $('<input>', {
                                    type: 'hidden',
                                    name: `competency-${formIndex}-${index}`,
                                    value: competency.competency_name
                                })
                            )
                        ),
                        // Weightage Input
                        $('<div>', { class: 'col-md-4' }).append(
                            $('<div>', { class: 'mb-3' }).append(
                                $('<h5>').text('Weightage'),
                                $('<div>', { class: 'input-group' }).append(
                                    $('<input>', {
                                        type: 'number',
                                        name: `weightage-${formIndex}-${index}`,
                                        id: `weightage-${formIndex}-${index}`,
                                        class: 'form-control weightage-input',
                                        min: 0,
                                        max: 90
                                    }),
                                    $('<span>', { class: 'input-group-text' }).append(
                                        $('<i>', { class: 'ri-percent-line' })
                                    )
                                ),
                                $('<div>', { class: 'text-danger error-message fs-14' })
                            )
                        ),
                        // Form Name Select
                        $('<div>', { class: 'col-md-4' }).append(
                            $('<div>', {
                                class: `mb-3 ${competency.competency_name === 'KPI' ? 'd-none' : ''}`
                            }).append(
                                $('<h5>').text('Form Name'),
                                $('<select>', {
                                    name: `form-name-${formIndex}${index}`,
                                    id: `form-name-${formIndex}${index}`,
                                    class: 'form-select select2',
                                    required: competency.competency_name !== 'KPI',
                                    disabled: competency.competency_name === 'KPI'
                                }).append(
                                    $('<option>', {
                                        value: '',
                                        text: 'please select'
                                    }),
                                    formAppraisals.map(form => 
                                        $('<option>', {
                                            value: form.name+'_'+form.desc,
                                            text: form.name+'_'+form.desc
                                        })
                                    )
                                ),
                                $('<div>', { class: 'text-danger error-message fs-14' })
                            )
                        ),

                        $('<div>', { class: 'col-md' }).append(
                            $('<div>', {
                                class: `mb-3 ${competency.competency_name === 'KPI' ? 'd-none' : ''}`
                            }).append(
                                $('<h5>').text(competency.competency_name + ' Weightage 360 in %'),
                                $('<select>', {
                                    name: `weightage-360-${formIndex}-${index}`,
                                    id: `weightage-360-${formIndex}-${index}`,
                                    class: 'form-select select2',
                                    required: competency.competency_name !== 'KPI',
                                    disabled: competency.competency_name === 'KPI'
                                }).append(
                                    $('<option>', {
                                        value: '',
                                        text: 'please select'
                                    }),
                                    data360s.map(data => {
                                        // Parse form_data if it is in JSON string format
                                        let formData = typeof data.form_data === 'string' ? JSON.parse(data.form_data) : data.form_data;
                                        
                                        // Convert formData array of objects into a single object
                                        let formDataObject = {};
                                        formData.forEach(item => {
                                            let key = Object.keys(item)[0];
                                            formDataObject[key] = item[key];
                                        });
                                    
                                        // Create a string in the format "employee: 20, manager: 30, peers: 30, subordinate: 20"
                                        let formDataText = Object.entries(formDataObject).map(([key, value]) => `${key}: ${value}`).join(', ');
                                    
                                        // Create the option element
                                        return $('<option>', {
                                            value: data.form_data,
                                            text: `${data.name} { ${formDataText} }`
                                        });
                                    })
                                ),
                                $('<div>', { class: 'text-danger error-message fs-14' })
                            )
                        )
                    )
                ),
                // Total Section
                $('<div>', { class: 'row align-items-center' }).append(
                    $('<div>', { class: 'col-md-4' }).append(
                        $('<div>', { class: 'mb-3' }).append(
                            $('<h5>').text('Total')
                        )
                    ),
                    $('<div>', { class: 'col-md-4' }).append(
                        $('<div>', { class: 'mb-3' }).append(
                            $('<div>', { class: 'input-group' }).append(
                                $('<input>', {
                                    id: `total-${formIndex}-0`,
                                    type: 'number',
                                    class: 'form-control',
                                    min: 0,
                                    max: 90,
                                    readonly: true
                                }),
                                $('<span>', { class: 'input-group-text' }).append(
                                    $('<i>', { class: 'ri-percent-line' })
                                )
                            ),
                            $('<div>', { class: 'invalid-feedback' }),
                            $('<div>', { class: 'text-danger error-message fs-14' })
                        )
                    ),
                    $('<div>', { class: 'col-md' })
                )
            )
        );

        return formHtml;
    }

    // Function to initialize the form generation
    function initializeAssessmentForms() {
        const $formContainer = $('#assessment-forms-container');
        const $numberSelect = $('#number_assessment_form');
        
        function clearForms() {
            $formContainer.empty();
        }
        
        function generateForms(count) {
            clearForms();
            for (let i = 0; i < count; i++) {
                const $form = generateAssessmentForm(i);
                $formContainer.append($form);
            }
            setupWeightageCalculation();
            initializeSelect2();
        }
        
        $numberSelect.on('change', function() {
            const count = parseInt($(this).val()) || 0;
            generateForms(count);
        });
    }

    // Function to setup weightage calculation
    function setupWeightageCalculation() {
        $('.assessment-form').each(function(formIndex) {
            const $form = $(this);
            const $weightageInputs = $form.find('.weightage-input');
            const $totalInput = $form.find(`#total-${formIndex}-0`);
            
            $weightageInputs.on('input', function() {
                let total = 0;
                $weightageInputs.each(function() {
                    total += parseInt($(this).val()) || 0;
                });
                
                $totalInput.val(total);
                
                if (total !== 90) {
                    $totalInput.addClass('is-invalid');
                    $totalInput.siblings('.invalid-feedback').text('Total weightage must equal 90%');
                } else {
                    $totalInput.removeClass('is-invalid');
                    $totalInput.siblings('.invalid-feedback').text('');
                }
            });
        });
    }

    // Initialize everything
    initializeAssessmentForms();
});
