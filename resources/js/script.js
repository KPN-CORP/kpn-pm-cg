import $ from 'jquery';

import select2 from "select2"
select2();


document.addEventListener("DOMContentLoaded", function () {
    // Function to handle "Select All" button click
    $("#select-all").on("click", function () {
        $(".day-button").toggleClass("active");
        $("#select-all").toggleClass("active");
    });

    // Function to handle individual day button clicks
    $(".day-button").on("click", function () {
        $(this).toggleClass("active");
        if ($(".day-button.active").length === 7) {
            $("#select-all").addClass("active");
        } else {
            $("#select-all").removeClass("active");
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    // Hide the .reminders element initially
    // $('.reminders').hide();

    // Toggle the hidden attribute of .reminders based on the checkbox state
    $("#checkbox_reminder").on("change", function () {
        if ($(this).is(":checked")) {
            $(".reminders").removeAttr("hidden");
            // $("#messages").attr("required", "required");
        } else {
            $(".reminders").attr("hidden", true);
            // $("#messages").removeAttr("required");
        }
    });
});

function logout() {
    Swal.fire({
        title: "Confirm Logout",
        text: "Are you sure you want to log out?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3e60d5",
        cancelButtonColor: "#f15776",
        confirmButtonText: "Yes, logout",
        cancelButtonText: "Cancel",
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "/logout";
            const Toast = Swal.mixin({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
            });
            Toast.fire({
                icon: "success",
                title: "You are now logged out.",
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    $("#myModal").on("show.bs.modal", function (event) {
        var button = $(event.relatedTarget);
        var id = button.data("id");

        // Ajax request to fetch modal content from the server
        $.ajax({
            url: "/goals/" + id,
            method: "GET",
            success: function (response) {
                $(".modal-body").html(response);
            },
        });
    });
});


document.addEventListener("DOMContentLoaded", function () {
    $("#group_company").change(function () {
        const selectedGroupCompany = $(this).val();

        // Make AJAX request to fetch updated options
        $.ajax({
            url: "/changes-group-company",
            method: "GET",
            data: { groupCompany: selectedGroupCompany },
            dataType: "json",
            success: function (data) {
                // Update options for Location select
                $("#location").html(
                    '<option value="">- select location -</option>'
                );
                $.each(data.locations, function (index, location) {
                    $("#location").append(
                        `<option value="${location.work_area}">${location.area} (${location.company_name})</option>`
                    );
                });
            },
            error: function (xhr, status, error) {
                if (xhr.status === 401) {
                    Swal.fire({
                        icon: "error",
                        title: "Your Session is Ended",
                        text: "Login first.",
                    }).then(() => {
                        // Redirect to the home page after the SweetAlert is dismissed
                        window.location.href = "/"; // Adjust the home page URL as needed
                    });
                } else {
                    console.error("Error fetching data:", error);
                }
            },
        });
    });
});

$(".select2").select2({
    theme: "bootstrap-5",
});

function showLoader() {
    $("#status").show();
    $("#preloader").show();
}

window.showLoader = showLoader;

function hideLoader() {
    $("#preloader").hide();
}

window.hideLoader = hideLoader;

window.onload = function () {
    hideLoader();
};

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        hideLoader();
    }
});
