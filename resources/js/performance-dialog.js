import { log } from 'handlebars';
import $ from 'jquery';

function filterTriggerPerformanceDialogTask(button) {
    showLoader();

    var form = $(button).closest('form');

    form.submit();
}

window.filterTriggerPerformanceDialogTask = filterTriggerPerformanceDialogTask;

function setPerformanceDialogSchedule() {
    document.getElementById('performance-dialog-schedule-form-employee-id').value = "";
    document.getElementById('performance-dialog-schedule-form-employee-id').disabled = true;
    document.getElementById('performance-dialog-schedule-form-employee-elem').style = 'display: block';
    document.getElementById('performance-dialog-schedule-form-employee-name-elem').style = 'display: none';
    document.getElementById('performance-dialog-schedule-form-employee').disabled = false;
    document.getElementById('performance-dialog-schedule-form-employee-name').value = '';
}

window.setPerformanceDialogSchedule = setPerformanceDialogSchedule;

function setPerformanceDialogScheduleEmployee(employee_id, employee_name) {
    document.getElementById('performance-dialog-schedule-form-employee-elem').style = 'display: none';
    document.getElementById('performance-dialog-schedule-form-employee-name-elem').style = 'display: block';
    document.getElementById('performance-dialog-schedule-form-employee-name').value = employee_name + ' (' + employee_id + ')';
    document.getElementById('performance-dialog-schedule-form-employee-id').value = employee_id;
    document.getElementById('performance-dialog-schedule-form-employee-id').disabled = false;
    document.getElementById('performance-dialog-schedule-form-employee').disabled = true;
}

window.setPerformanceDialogScheduleEmployee = setPerformanceDialogScheduleEmployee;

document.addEventListener("DOMContentLoaded", function () {
    $(document).ready(function() {
        var tableData = $('#tablePerformanceDialog').DataTable({
            stateSave: true,
            autoWidth: false,
            dom: 'Bfrtip',
            fixedColumns: {
                leftColumns: 0,
                rightColumns: 1
            },
            scrollCollapse: true,
            scrollX: true,
            paging: true,
            pageLength: 10,
            lengthChange: false,
            buttons: [
                {
                    extend: 'csvHtml5',
                    text: '<i class="ri-download-cloud-2-line fs-16 me-1"></i>Download Report',
                    className: 'btn btn-sm btn-outline-success',
                    title: 'Performance Dialog',
                    exportOptions: {
                        columns: ':not(:first-child):not(:last-child)'
                    }
                }
            ],
        });

        tableData.on('order.dt search.dt', function () {
            let i = 1;

            tableData
                .cells(null, 0, { search: 'applied', order: 'applied' })
                .every(function () {
                    this.data(i++);
                });
        }).draw();

        addChildRowToggle(tableData, '#tablePerformanceDialog');
    });

    $('#importPerformanceDialog').on('submit', function () {
        const button = $('#importPerformanceDialogButton');
        const spinner = button.find('.spinner-border');

        button.prop('disabled', true);
        spinner.removeClass('d-none');
    });

    $('#performance-dialog-submit').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-dialog-form');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        const scheduleDate = new Date($('#performance_dialog_start_date').val());
        const now = new Date();

        if (now < scheduleDate) {
            Swal.fire({
                icon: 'error',
                title: 'Cannot Submit',
                text: 'You can only submit this performance dialog on or after the scheduled date.'
            });

            return;
        }

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Submit performance dialog?",
            text: "This can't be reverted",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: "Ok, submit it",
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);
            spinner.removeClass('d-none');

            let formData = form.serializeArray();

            formData.push({
                name: 'action_submit',
                value: 'submit'
            });

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: $.param(formData),

                success: function (response) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1200);

                },

                error: function (xhr) {
                    button.prop('disabled', false);
                    spinner.addClass('d-none');

                    let html = '';

                    html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#performance-dialog-save-draft').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-dialog-form');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        const scheduleDate = new Date($('#performance_dialog_start_date').val());
        const now = new Date();

        if (now < scheduleDate) {
            Swal.fire({
                icon: 'error',
                title: 'Cannot Submit',
                text: 'You can only submit this performance dialog on or after the scheduled date.'
            });

            return;
        }

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Save as draft this performance dialog?",
            text: "This can't be reverted",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: "Ok, save it as draft",
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);
            spinner.removeClass('d-none');

            let formData = form.serializeArray();

            formData.push({
                name: 'action_draft',
                value: 'draft'
            });

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: $.param(formData),

                success: function (response) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1200);

                },

                error: function (xhr) {
                    button.prop('disabled', false);
                    spinner.addClass('d-none');

                    let html = '';

                    html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#performance-dialog-approve').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-dialog-form');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Approve performance dialog?",
            text: "This can't be reverted",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: "Ok, approve it",
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);
            spinner.removeClass('d-none');

            let formData = form.serializeArray();

            formData.push({
                name: 'action_approve',
                value: 'approve'
            });

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: $.param(formData),

                success: function (response) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1200);

                },

                error: function (xhr) {
                    button.prop('disabled', false);
                    spinner.addClass('d-none');

                    let html = '';

                    html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#performance-dialog-acknowledge').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-dialog-form');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Acknowledge performance dialog?",
            text: "This can't be reverted",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: "Ok, acknowledge it",
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);
            spinner.removeClass('d-none');

            let formData = form.serializeArray();

            formData.push({
                name: 'action_acknowledge',
                value: 'acknowledge'
            });

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: $.param(formData),

                success: function (response) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1200);

                },

                error: function (xhr) {
                    button.prop('disabled', false);
                    spinner.addClass('d-none');

                    let html = '';

                    html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#performance-dialog-delete').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-dialog-form');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Delete performance dialog?",
            text: "This can't be reverted",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3e60d5",
            cancelButtonColor: "#f15776",
            confirmButtonText: "Ok, delete it",
            reverseButtons: true,
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            button.prop('disabled', true);
            spinner.removeClass('d-none');

            let formData = form.serializeArray();

            formData.push({
                name: 'action_delete',
                value: 'delete'
            });

            $.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: $.param(formData),

                success: function (response) {
                    Swal.fire({
                        title: "Success",
                        text: response.message,
                        icon: "success",
                        timer: 1200,
                        showConfirmButton: false
                    });

                    setTimeout(function () {
                        window.location.href = response.redirect;
                    }, 1200);

                },

                error: function (xhr) {
                    button.prop('disabled', false);
                    spinner.addClass('d-none');

                    let html = '';

                    html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#schedule-performance-dialog-submit').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#schedule-performance-dialog');

        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        button.prop('disabled', true);
        spinner.removeClass('d-none');

        let formData = form.serializeArray();

        $('#schedule-performance-dialog-form-alert').addClass('d-none').empty();

        $.ajax({
            url: form.attr('action'),
            type: form.attr('method'),
            data: $.param(formData),

            success: function (response) {
                Swal.fire({
                    title: "Success",
                    text: response.message,
                    icon: "success",
                    timer: 1200,
                    showConfirmButton: false
                });

                setTimeout(function () {
                    window.location.href = response.redirect;
                }, 1200);
            },

            error: function (xhr) {
                button.prop('disabled', false);
                spinner.addClass('d-none');

                let html = '';

                html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';

                $('#schedule-performance-dialog-form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
            }
        });
    });

    $('#performance_dialog_type').on('change', function () {
        const values = $(this).val() || [];

        if (values.includes('0')) {
            $('#others_performance_dialog_type')
                .show()
                .prop('required', true);
        } else {
            $('#others_performance_dialog_type')
                .hide()
                .prop('required', false)
                .val('');
        }
    });
});
