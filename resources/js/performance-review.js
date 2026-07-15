import { log } from 'handlebars';
import $ from 'jquery';

document.addEventListener("DOMContentLoaded", function () {
    $('#performance-review-submit').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-review-form');

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Submit performance review?",
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

                    if (xhr.status === 422) {
                        $.each(xhr.responseJSON.errors, function (key, value) {
                            html += '<li>' + value[0] + '</li>';
                        });
                    } else {
                        html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';
                    }

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });

    $('#performance-review-save-draft').click(function (e) {
        e.preventDefault();

        let button = $(this);
        let spinner = button.find('.spinner-border');
        let form = $('#performance-review-form');

        $('#form-alert').addClass('d-none').empty();

        Swal.fire({
            title: "Save as draft this performance review?",
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

                    if (xhr.status === 422) {
                        $.each(xhr.responseJSON.errors, function (key, value) {
                            html += '<li>' + value[0] + '</li>';
                        });
                    } else {
                        html += '<li>' + (xhr.responseJSON?.message ?? 'Something went wrong.') + '</li>';
                    }

                    $('#form-alert').removeClass('d-none').html('<ul class="mb-0">' + html + '</ul>');
                }
            });
        });
    });
});
