import $ from 'jquery';

import numeral from 'numeral';

import bootstrap from "bootstrap/dist/js/bootstrap.bundle.min.js";

import { GoogleGenAI } from "@google/genai";

import select2 from "select2"
select2(); 

document.addEventListener('DOMContentLoaded', function () {
    // Initialize all popovers on the page
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
});

function initSelect2($element) {
    $element.select2({
        theme: "bootstrap-5",
        width: '100%' 
    });
}

$(document).on('input change', '[name="weightage[]"]', function () {
  updateWeightageSummary();
});

$(document).ready(function() {
    // Initialize Select2 on the select elements
    $('.select-uom').select2({
         theme : "bootstrap-5",
    });

    // Event listener for select element with Select2
    $(document).on('change', 'select.select-uom', function (event) {
        let index = $(this).data('id');

        const uomSelect = $(this).val();
        // Event listener for select element
        const inputField = $("#custom_uom" + index);
        if (uomSelect === "Other") {
            // Display input field
            inputField.show(); // Show the input field
            inputField.prop("required", true); // Set input as required
        } else {
            inputField.hide(); // Hide the input field
            inputField.val("");
            inputField.prop("required", false); // Remove required attribute
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    // Get the value of the hidden input
    var managerId = $('input[name="manager_id"]').val();

    // Check if managerId is empty or not assigned
    if (managerId == "") {
        // Show SweetAlert alert
        Swal.fire({
            title: "No direct manager is assigned!",
            text: "Please contact admin to assign your manager",
            icon: "error",
            closeOnClickOutside: false, // Prevent closing by clicking outside alert
        }).then(function () {
            // Redirect back
            window.history.back();
        });
    }
});

// Function to fetch UoM data and populate select element
function populateUoMSelect($select, callback) {
    fetch("/units-of-measurement")
        .then(r => r.json())
        .then(data => {

            // Reset + Tambahkan default option
            $select
                .empty()
                .append('<option value="">- Select -</option>'); // DEFAULT

            Object.keys(data.UoM).forEach(category => {
                const $optgroup = $('<optgroup>').attr("label", category);

                data.UoM[category].forEach(unit => {
                    $optgroup.append(`<option value="${unit}">${unit}</option>`);
                });

                $select.append($optgroup);
            });
        })
        .then(() => {
            if (typeof callback === "function") callback();
        })
        .catch(err => console.error("Error fetching UoM data:", err));
}

document.addEventListener("DOMContentLoaded", function () {
    var x = 1;
    var count = $("#count").val();
    // var index = $("#count").val();
    var wrapper = $(".container-card"); // Fields wrapper
    
    function addField(val) {
        var max_fields = val === "input" ? 9 : 10 - count; // maximum input boxes allowed
        
        var currentCount = wrapper.children(".card").length; // jumlah card saat ini
        var index = currentCount + 1;                        // index card baru = last + 1

        // batas total card: input = 9, normal = 10
        var maxTotal = (val === "input") ? 9 : 10;        

        if (currentCount <= maxTotal) {
            // max input box allowed
            // x++; // text box increment
            // index++; // text box increment

            $(wrapper).append(
                '<div class="card border-primary border col-md-12 m-0 mt-3 bg-primary-subtle">' +
                    "<div class='card-body'><div class='row align-items-end'><div class='col'><h5 class='card-title fs-16 mb-0 text-primary'>Goal " +
                    (index ? index : x) +
                    "</h5></div>" +
                    "<div class='col-auto'><a class='btn-close btn-sm remove_field' type='button'></a></div></div>" +
                    '<div class="row mt-2">' +
                    '<div class="col-md">' +
                    '<div class="mb-3 position-relative">' +
                    '<textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea" placeholder="input your goal.." required style="padding-right: 40px; resize: none"></textarea>'+
                    '<div class="invalid-feedback">' + textMandatory + '</div>' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="row">  ' +
                    '<div class="col-md">' +
                    '<label class="form-label text-primary" for="kpi-description">Goal Descriptions</label>' +
                    '<div class="mb-3 position-relative">' +
                    '<textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions" rows="2" placeholder="Input goal descriptions.." style="padding-right: 40px; resize: none"></textarea>' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    '<div class="row">' +
                    '<div class="col-md mb-3">' +
                    '<label class="form-label text-primary" for="target">Target</label><input type="text" oninput="validateDigits(this, '
                    + index +
                    ')" class="form-control" required>' +
                    '<input type="hidden" name="target[]" id="target'
                    + index +'">' +
                    '<div class="invalid-feedback">' + textMandatory + '</div>' +
                    "</div>" +
                    '<div class="col-md mb-3">' +
                    '<label class="form-label text-primary" for="uom">'+ uom +'</label>' +
                    '<select class="form-select select2 select-uom" name="uom[]" id="uom' +
                    index +
                    '" data-id="' +
                    index +
                    '" title="Unit of Measure" required>' +
                    '<option value="">- Select -</option>' +
                    '</select><input type="text" name="custom_uom[]" id="custom_uom' +
                    index +
                    '" class="form-control mt-2" placeholder="Enter UoM" style="display: none" placeholder="Enter UoM">' +
                    '<div class="invalid-feedback">' + textMandatory + '</div>' +
                    "</div>" +
                    '<div class="col-md mb-3">' +
                    '<label class="form-label text-primary" for="type">'+ type +'</label>' +
                    '<select class="form-select select-type" name="type[]" id="type' +
                    index +
                    '" required>' +
                    '<option value="">- Select -</option>' +
                    '<option value="Higher Better">Higher Better</option>' +
                    '<option value="Lower Better">Lower Better</option>' +
                    '<option value="Exact Value">Exact Value</option>' +
                    "</select>" +
                    '<div class="invalid-feedback">' + textMandatory + '</div>' +
                    "</div>" +
                    '<div class="col-6 col-md-2 mb-3">' +
                    '<label class="form-label text-primary" for="weightage">'+ weightage +'</label>' +
                    '<div class="input-group">' +
                    '<input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" required>' +
                    '<span class="input-group-text">%</span>' +
                    '<div class="invalid-feedback">' + textMandatory + '</div>' +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>" +
                    "</div>"
            );
             // add input box
             // Reinitialize auto-resize and character counter for new textareas
            initializeTextareaEvents();

            var $select = $("#uom" + index);
            populateUoMSelect($select, function () {
                initSelect2($select);
            });

            document.querySelectorAll('[name="weightage[]"]').forEach(function(el){
                el.addEventListener("keyup", updateWeightageSummary);
            });

            // sinkron nilai count berdasarkan DOM terkini
            $("#count").val(wrapper.children(".card").length);
        } else {
            Swal.fire({
                title: "Oops, you've reached the maximum number of KPI",
                icon: "error",
                confirmButtonColor: "#3e60d5",
                confirmButtonText: "OK",
            });
        }
    }

    $(wrapper).on("click", ".remove_field", function (e) {
        e.preventDefault();
        // hapus card yang diklik
        $(this).closest(".card").remove();

        // reindex per cluster
        reindexCardsPerCluster();

        // perbarui count dari DOM
        $("#count").val($(".container-card .card").length);

        // hitung ulang total weightage
        updateWeightageSummary();
    });

    var addButton = document.getElementById("addButton");
    if(addButton){
        addButton.addEventListener("click", function () {
            var dataId = addButton.getAttribute("data-id");
            addField(dataId); // Add an empty input field
        });
    }

    // Handle add personal button
    var addPersonalButtons = document.querySelectorAll(".add-personal-btn");
    addPersonalButtons.forEach(function(button) {
        button.addEventListener("click", function () {
            var cluster = button.getAttribute("data-cluster");
            addFieldForCluster(cluster);
        });
    });
});

var firstSelect = document.getElementById("uom"); // Assuming your first select has an ID "uom1"
populateUoMSelect(firstSelect);

function addFieldForCluster(cluster) {
    var wrapper = document.getElementById(cluster + "-goals");
    if (!wrapper) return;

    var currentCount = wrapper.querySelectorAll(".card").length;
    if (currentCount >= 10) {
        Swal.fire({
            title: "Maximum goals reached",
            text: "You can only add up to 10 goals per cluster.",
            icon: "warning"
        });
        return;
    }

    var globalIndex = document.querySelectorAll('[name="kpi[]"]').length;
    var clusterIndex = currentCount + 1;

    var newCard = document.createElement("div");
    newCard.className = "card border-primary border col-md-12 mb-3 bg-primary-subtle";
    newCard.innerHTML = `
        <div class="card-body">
            <div class='row align-items-end'>
                <div class='col'><h5 class='card-title fs-16 mb-0 text-primary'>Goal ${clusterIndex}</h5></div>
                <div class='col-auto'><a class='btn-close remove_field' type='button'></a></div>
            </div>
            <input type="hidden" name="cluster[]" value="${cluster}">
            <div class="row mt-2">
                <div class="col-md">
                    <div class="mb-3 position-relative">
                        <textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea pb-2 pe-3" rows="2" placeholder="Input your goals.." required style="resize: none"></textarea>
                        <div class="invalid-feedback">${textMandatory}</div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md">
                    <div class="mb-3 position-relative">
                        <label class="form-label text-primary" for="kpi-description">Goal Descriptions</label>
                        <textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions pb-2 pe-3" rows="2" placeholder="Input goal descriptions.." style="resize: none"></textarea>
                    </div>
                </div>
            </div>
            <div class="row justify-content-between">
                <div class="col-md">
                    <div class="mb-3">
                        <label class="form-label text-primary" for="target">Target</label>
                        <input type="text" oninput="validateDigits(this, '${cluster}_${clusterIndex}')" class="form-control" required>
                        <input type="hidden" name="target[]" id="target${cluster}_${clusterIndex}">
                        <div class="invalid-feedback">${textMandatory}</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label class="form-label text-primary" for="uom">${uom}</label>
                        <select class="form-select select2 select-uom" name="uom[]" id="uom${cluster}_${clusterIndex}" data-id="${cluster}_${clusterIndex}" title="Unit of Measure" required>
                            <option value="">- Select -</option>
                        </select>
                        <input type="text" name="custom_uom[]" id="custom_uom${cluster}_${clusterIndex}" class="form-control mt-2" placeholder="Enter UoM" style="display: none">
                        <div class="invalid-feedback">${textMandatory}</div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="mb-3">
                        <label class="form-label text-primary" for="type">${type}</label>
                        <select class="form-select select-type" name="type[]" id="type${cluster}_${clusterIndex}" required>
                            <option value="">- Select -</option>
                            <option value="Higher Better">Higher Better</option>
                            <option value="Lower Better">Lower Better</option>
                            <option value="Exact Value">Exact Value</option>
                        </select>
                        <div class="invalid-feedback">${textMandatory}</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="mb-3">
                        <label class="form-label text-primary" for="weightage">${weightage}</label>
                        <div class="input-group">
                            <input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" required>
                            <span class="input-group-text">%</span>
                            <div class="invalid-feedback">${textMandatory}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    wrapper.appendChild(newCard);

    // Initialize events
    initializeTextareaEvents();
    var $select = $("#uom" + cluster + "_" + clusterIndex);
    populateUoMSelect($select, function () {
        initSelect2($select);
    });

    document.querySelectorAll('[name="weightage[]"]').forEach(function(el){
        el.addEventListener("keyup", updateWeightageSummary);
    });

    updateWeightageSummary();
}

function checkEmptyFields(submitType) {
    const alertField = $(".mandatory-field");
    alertField.html(`
        <div id="alertField" class="alert alert-danger alert-dismissible fade" role="alert" hidden>
            `+ errorMessages +`
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    if (submitType === "submit_form") {
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
                        document.getElementById("goalForm").classList.add("was-validated");
                    },
                });
                return false; // Prevent form submission
            }
        }
        return true; // All required fields are filled
    }
    return true; // All required fields are filled
}

function validate(submitType) {
    // Check if this is a cluster form (has containers with id ending in "-goals")
    var isClusterForm = document.querySelector('[id$="-goals"]') !== null;

    var weight = document.querySelectorAll('input[name="weightage[]"]');
    var sum = 0;
    for (var i = 0; i < weight.length; i++) {
        sum += parseFloat(weight[i].value) || 0; // Parse input value to integer, default to 0 if NaN
    }

    // Skip total weightage validation for cluster forms
    if (!isClusterForm && sum != 100 && submitType === "submit_form") {
        Swal.fire({
            title: "Submit failed",
            html: `Your current weightage is ${sum}%, <br>Please adjust to reach the total weightage of 100%`,
            confirmButtonColor: "#3e60d5",
            icon: "error",
            // If confirmed, proceed with form submission
        });
        return false; // Prevent form submission
    }

    return true; // Allow form submission
}

function validateWeightage(submitType) {
    // Get all input elements with name="weightage[]"
    var weightageInputs = document.getElementsByName("weightage[]");

    // Iterate through each input element
    for (var i = 0; i < weightageInputs.length; i++) {
        var input = weightageInputs[i];

        // Get the value of the input (convert to number)
        var value = parseFloat(input.value);

        // Check if value is below 5%
        if (value < 5 && submitType === "submit_form") {
            // Display alert message
            Swal.fire({
                title: "The weightage cannot lower than 5%",
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

$(document).on('click', '#submitButton', function (event) {
    event.preventDefault();

    let submitType = $(this).data('id');

    document.getElementById("submitType").value = submitType; // Set the value of the hidden input field
    // Now you can call the confirmSubmission() function to show the confirmation dialog
    // Check for empty required fields
    if (!checkEmptyFields(submitType)) {
        return false; // Stop submission if required fields are empty
    }
    if (!validateWeightage(submitType)) {
        return false; // Stop submission if required fields are empty
    }
    if (!validate(submitType)) {
        return false; // Stop submission if required fields are empty
    }
    return confirmSubmission(submitType);
});

function confirmSubmission(submitType) {
    let title1;
    let title2;
    let text;
    let confirmText;

    const submitButton = $("#submitButton");
    const spinner = submitButton.find(".spinner-border");

    if (submitType === "save_draft") {
        title1 = "Do you want to save this form?";
        title2 = "Form saved successfuly!";
        text = "Your data will be saved as draft";
        confirmText = "Save";
    } else {
        title1 = "Do you want to submit?";
        title2 = "KPI submitted successfuly!";
        text =
            "The goal will go through approval process once you submitted it";
        confirmText = "Submit";
    }

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

            document.getElementById("goalForm").submit();
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

// Function to calculate and display the sum of weightage inputs
function updateWeightageSummary() {
    // Check if this is a cluster form (has containers with id ending in "-goals")
    var isClusterForm = document.querySelector('[id$="-goals"]') !== null;

    // Get all input elements with name="weightage[]"
    var weightageInputs = document.getElementsByName("weightage[]");
    var totalSum = 0;

    // Iterate through each input element
    for (var i = 0; i < weightageInputs.length; i++) {
        var input = weightageInputs[i];

        // Get the value of the input (convert to number)
        var value = parseFloat(input.value);

        // Check if the value is a valid number and within the allowed range
        if (!isNaN(value) && value >= 5 && value <= 100) {
            totalSum += value; // Add valid value to total sum
        }
    }

    // Display the total sum in a summary element
    var summaryElement = document.getElementById("totalWeightage");
    var summaryContainer = summaryElement ? summaryElement.closest('h5, h4') : null;

    if (isClusterForm) {
        // For cluster forms, hide the total weightage display
        if (summaryContainer) {
            summaryContainer.style.display = 'none';
        }
    } else {
        // For non-cluster forms, show and validate total = 100%
        if (summaryContainer) {
            summaryContainer.style.display = 'block';
        }
        if (totalSum != 100) {
            summaryElement.classList.remove("text-success");
            summaryElement.classList.add("text-danger"); // Add text-danger class
            // Add or update a sibling element to display the additional message
            if (summaryElement) {
                summaryElement.textContent = totalSum + "% of 100%";
            }
        } else {
            summaryElement.classList.remove("text-danger"); // Remove text-danger class
            summaryElement.classList.add("text-success"); // Remove text-danger class
            // Hide the message element if totalSum is 100
            if (summaryElement) {
                summaryElement.textContent = totalSum.toFixed(0) + "%";
            }
        }
    }
}

// Add event listener for keyup event on all weightage inputs
var weightageInputs = document.getElementsByName("weightage[]");
for (var i = 0; i < weightageInputs.length; i++) {
    weightageInputs[i].addEventListener("keyup", updateWeightageSummary);
}

function validateDigits(input, index) {
    // Ambil angka + titik
    let numericValue = input.value.replace(/[^0-9.]/g, '');

    // Hanya izinkan 1 titik desimal
    const parts = numericValue.split('.');
    if (parts.length > 2) {
        // Gabungkan semua bagian setelah titik pertama
        numericValue = parts[0] + '.' + parts.slice(1).join('');
    }

    // Batasi maksimum 20 karakter
    if (numericValue.length > 20) {
        numericValue = numericValue.slice(0, 20);
    }

    // Simpan ke hidden input
    document.getElementById('target' + index).value = numericValue;

    // Set kembali value input dengan hasil bersih
    input.value = numericValue;

    // Format angka hanya jika tidak ada titik desimal
    if (!numericValue.includes('.') && numericValue !== '') {
        input.value = numeral(numericValue).format('0,0');
    }
}

window.validateDigits = validateDigits;

function initializeTextareaEvents() {
  const all = document.querySelectorAll('.kpi-textarea, .kpi-descriptions');

  all.forEach((textarea) => {
    const parent = textarea.parentNode;
    parent.classList.add('position-relative'); // jaga posisi

    // 1) Buat counter HANYA jika belum ada
    if (!textarea.dataset.counterAttached) {
      let counter = parent.querySelector('.char-counter');
      if (!counter) {
        counter = document.createElement('small');
        counter.classList.add('text-muted', 'position-absolute', 'bottom-0', 'end-0', 'pe-1', 'char-counter');
        counter.textContent = '0/1000';
        parent.appendChild(counter);
      }
      textarea.dataset.counterAttached = '1';
    }

    // 2) Tambah listener HANYA sekali
    if (!textarea.dataset.listenerAttached) {
      const updateCounter = () => {
        const max = 1000;
        const len = textarea.value.length;
        const el = parent.querySelector('.char-counter');
        if (el) el.textContent = `${len}/${max}`;
      };

      const autoResize = () => {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
      };

      textarea.addEventListener('input', updateCounter);
      textarea.addEventListener('input', autoResize);

      // tandai sudah dipasang
      textarea.dataset.listenerAttached = '1';

      // trigger awal
      updateCounter();
      autoResize();
    }
  });
}


$(document).on('click', '#getLatestGoal', function(){
    const btn = this;
    const period = document.getElementById('period').value;
    const employeeId = document.getElementById('employee_id').value;
    toggleLoading(btn, true);
    showSectionLoader('.container-fluid.p-0');

    fetch(`/goals/latest/${employeeId}`)
        .then(r => r.json())
        .then(async res => { // Add async here to use await
            if(!res.success){ 
                Swal.fire("Data not found","Sorry, I cannot find your latest goals","info"); 
                return;
            }

            let goals = res.data;
            if(typeof goals === "string"){ 
                try{ goals = JSON.parse(goals); }catch(e){ goals = []; } 
            }

            if(!Array.isArray(goals) || goals.length===0){
                Swal.fire("Data not found","","info"); 
                return;
            }

            const wrapper = $(".container-card"); 
            wrapper.html(''); 
            $("#count").val(goals.length);

            // Use Promise.all to optimize all texts concurrently
            const optimizedGoals = await Promise.all(goals.map(async (g) => {
                // const optimizedKPI = g.kpi;
                // const optimizedDescription = g.description;
                const optimizedKPI = await optimizeText('', g.kpi, period);
                const optimizedDescription = await optimizeText(g.kpi, g.description, period);
                return { ...g, kpi: optimizedKPI, description: optimizedDescription };
            }));

            optimizedGoals.forEach((g, i) => {
                const index = i + 1;
                wrapper.append(`
                  <div class="card border-primary border col-md-12 m-0 mt-3 bg-primary-subtle">
                    <div class="card-body">
                      <div class="row align-items-end">
                        <div class="col"><h5 class="card-title fs-16 mb-0 text-primary">Goal ${index}</h5></div>
                        <div class="col-auto"><a class="btn-close btn-sm remove_field" type="button"></a></div>
                      </div>
                      <div class="row mt-2">
                        <div class="col-md">
                          <div class="mb-3 position-relative">
                          <textarea name="kpi[]" class="form-control overflow-hidden kpi-textarea" required>${g.kpi??''}</textarea>
                          </div>
                        </div>
                      </div>
                      <div class="row"><div class="col-md">
                        <label class="form-label text-primary">Goal Descriptions</label>
                        <div class="mb-3 position-relative">
                        <textarea name="description[]" class="form-control overflow-hidden kpi-descriptions" rows="2">${g.description??''}</textarea>
                        </div>
                      </div></div>
                      <div class="row">
                        <div class="col-md mb-3">
                          <label class="form-label text-primary">Target</label>
                          <input type="text" oninput="validateDigits(this, ${index})" class="form-control" required value="${g.target??''}">
                          <input type="hidden" name="target[]" id="target${index}" value="${g.target??''}">
                        </div>
                        <div class="col-md mb-3">
                          <label class="form-label text-primary">UoM</label>
                          <select class="form-select select2 select-uom" name="uom[]" id="uom${index}" data-id="${index}" required>
                            <option value="">- Select -</option>
                          </select>
                          <input type="text" name="custom_uom[]" id="custom_uom${index}" class="form-control mt-2" placeholder="Enter UoM" style="display:none" value="${g.custom_uom??''}">
                        </div>
                        <div class="col-md mb-3">
                          <label class="form-label text-primary">Type</label>
                          <select class="form-select select-type" name="type[]" id="type${index}" required>
                            <option value="">- Select -</option>
                            <option value="Higher Better" ${(g.type==="Higher Better")?"selected":""}>Higher Better</option>
                            <option value="Lower Better" ${(g.type==="Lower Better")?"selected":""}>Lower Better</option>
                            <option value="Exact Value" ${(g.type==="Exact Value")?"selected":""}>Exact Value</option>
                          </select>
                        </div>
                        <div class="col-6 col-md-2 mb-3">
                          <label class="form-label text-primary">Weightage</label>
                          <div class="input-group">
                            <input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" required value="${g.weightage??''}">
                            <span class="input-group-text">%</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                `);

                const $select = $("#uom" + index);
                populateUoMSelect($select, function () {
                    initSelect2($select);
                    $select.val(g.uom ?? "").trigger("change");
                });

            });

            $('.select-uom').select2({ theme: "bootstrap-5" });
            initializeTextareaEvents();
            updateWeightageSummary();

        })
        .catch(err => {
            console.error(err);
            Swal.fire("Error", "Gagal generate data", "error");
        })
        .finally(() => {
            toggleLoading(btn, false);
            const $btn = $(btn);
            $btn.removeClass("btn-outline-info").addClass("btn-outline-secondary");

            hideSectionLoader('.container-fluid.p-0');

        });
});

// Get API Key and Endpoint from environment variables
const GEMINI_API_KEY = import.meta.env.VITE_GOOGLE_GENAI_API_KEY;
const GEMINI_API_ENDPOINT = import.meta.env.VITE_GOOGLE_GENAI_API_ENDPOINT;

async function optimizeText(kpi, text, period) {
    if (!text) return '';    

    // Validate if the necessary variables are present
    if (!GEMINI_API_KEY || !GEMINI_API_ENDPOINT) {
        console.error("API Key or Endpoint is not configured.");
        return text;
    }
    
    
    const genAI = new GoogleGenAI({
        apiKey: GEMINI_API_KEY,
        baseUrl: GEMINI_API_ENDPOINT,
    });    

    const promptKpi = `Improve this KPI text: "${text}". Make it concise, clear, and impactful. 
    If the text contains any year, always replace it with ${period}. 
    If no year exists, do not add one. 
    Adjust to the language used in the original text. 
    Do not add extra symbols, punctuation, or formatting beyond what is needed for clarity. 
    Maintain a professional tone and return only the improved text without any introduction or explanation.`;

    const promptDesc = `Improve this description text: "${text}". The description is for a goal with the KPI "${kpi}". 
    Make it concise, clear, and easy to understand. 
    If the text contains any year, always replace it with ${period}. 
    If no year exists, do not add one. 
    Adjust to the language used in the original text. 
    Do not add extra symbols, punctuation, or formatting beyond what is needed for clarity. 
    Maintain a professional tone and return only the improved text without any introduction or explanation.`;


    
    // Define the prompt for text optimization
    const prompt = !kpi ? promptKpi : promptDesc;
    
    try {

        const response = await genAI.models.generateContent({
            model: 'gemini-2.0-flash',
            contents: prompt,
        });

        // The API response might have leading/trailing whitespace, trim it.
        return response.text;
    } catch (error) {
        console.error("Error optimizing text with Gemini:", error);
        return text; // Return original text in case of an error
    }
}

// (async () => {
//   const result = await optimizeText("Single-Sign On (SSO) Darwinbox - May 2024: Create HCIS x Darwinbox API.", 2025);
//   console.log("Hasil:", result);
// })();

function toggleLoading(btn, on){
  const $btn = $(btn);
  $btn.prop("disabled", true).toggleClass("disabled", true);
  $btn.find(".label").toggleClass("d-none", on).text("Goals Generated");
  $btn.find(".loading").toggleClass("d-none", !on);
}

function showSectionLoader(selector){
  const $host = $(selector);
  if (!$host.length) return;

  // jika belum ada overlay, buat
  if ($host.find('.section-loader').length === 0) {
    $host.append(`
      <div class="section-loader">
        <div class="section-loader__inner">
          <span><i class="ri-bard-fill me-1"></i>Generating...</span>
        </div>
      </div>
    `);
  } else {
    $host.find('.section-loader').removeClass('d-none is-fading').css('opacity', 1);
  }
}

function hideSectionLoader(selector, opts){
  const $ov = $(selector).find('.section-loader');
  if(!$ov.length) return;

  const { fade=true, delay=380, remove=true } = opts || {};
  if(!fade){
    remove ? $ov.remove() : $ov.addClass('d-none');
    return;
  }
  $ov.addClass('is-fading');
  setTimeout(()=> { remove ? $ov.remove() : $ov.addClass('d-none').removeClass('is-fading'); }, delay);
}

// Run initialization when page loads
document.addEventListener("DOMContentLoaded", initializeTextareaEvents);

function reindexCardsPerCluster() {
    // Get all cluster containers (those with id ending in "-goals")
    const clusterContainers = document.querySelectorAll('[id$="-goals"]');

    clusterContainers.forEach(container => {
        const clusterId = container.getAttribute('id');
        const cluster = clusterId.replace('-goals', ''); // Extract cluster name from id
        const cards = container.querySelectorAll('.card');

        cards.forEach((card, index) => {
            const clusterIndex = index + 1;

            // update title
            card.querySelector(".card-title").textContent = "Goal " + clusterIndex;

            // update target input
            const targetInput = card.querySelector('input[id^="target"]');
            if (targetInput) {
                targetInput.id = "target" + cluster + "_" + clusterIndex;
            }

            // update oninput for target
            const targetVisibleInput = card.querySelector('input[oninput]');
            if (targetVisibleInput) {
                targetVisibleInput.setAttribute("oninput", `validateDigits(this, ${cluster + "_" + clusterIndex})`);
            }

            // update UoM select
            const uomSelect = card.querySelector('select.select-uom');
            if (uomSelect) {
                uomSelect.id = "uom" + cluster + "_" + clusterIndex;
                uomSelect.setAttribute("data-id", cluster + "_" + clusterIndex);
            }

            // update custom_uom
            const customUomInput = card.querySelector('input[id^="custom_uom"]');
            if (customUomInput) {
                customUomInput.id = "custom_uom" + cluster + "_" + clusterIndex;
            }

            // update type select
            const typeSelect = card.querySelector('select.select-type');
            if (typeSelect) {
                typeSelect.id = "type" + cluster + "_" + clusterIndex;
            }
        });
    });
}
