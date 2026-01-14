@extends('layouts_.vertical', ['page_title' => 'Schedule'])

@section('css')
@endsection

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">
        <!-- Page Heading -->
        
        <div class="d-sm-flex align-items-center justify-content-right">
            <div class="card col-md-6">
                <div class="card-header d-flex bg-white justify-content-between">
                    <h4 class="modal-title" id="viewFormEmployeeLabel">{{ $sublink }}</h4>
                    <a href="{{ route('schedules') }}" type="button" class="btn btn-close"></a>
                </div>
                <div class="card-body" @style('overflow-y: auto;')>
                    <div class="container-fluid">
                        <form id="scheduleForm" method="post" action="{{ route('save-schedule') }}">@csrf
                            <div class="row g-2">
                                <div class="mb-3 col-md-6">
                                    <label class="form-label" for="name">Schedule Name</label>
                                    <input type="text" class="form-control" placeholder="Enter name.." id="name" name="schedule_name" required>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label class="form-label" for="type">Event Type </label>
                                    <select name="event_type" id="event_type" class="form-select" onchange="toggleMaster()" required>
                                        <option value="">- select type -</option>
                                        @if(auth()->check())
                                            @can('schedulepa')
                                                @if($schedulemasterpa)
                                                    <option value="schedulepa">Schedule PA</option>
                                                @endif
                                            @endcan
                                            @can('goals')
                                                @if($schedulemastergoals)
                                                    <option value="goals">Schedule Goals</option>
                                                @endif
                                            @endcan
                                            @can('masterschedulepa')
                                                <option value="masterschedulepa">Master Schedule PA</option>
                                            @endcan
                                            @can('masterschedulegoals')
                                                <option value="masterschedulegoals">Master Schedule Goals</option>
                                            @endcan
                                        @endif
                                    </select>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label" for="type">Schedule Period</label>
                                        <input name="schedule_periode" id="schedule_periode" type="text" class="form-control" placeholder="-" readonly>
                                        <select id="schedule_periode_master" onchange="changePeriod()" class="form-select d-none" class="form-select">
                                            <option value="">-</option>
                                            @for($year = now()->year - 1; $year <= now()->year + 1; $year++)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div id="nonmaster1">
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Employee Type</label>
                                            <select name="employee_type[]" class="form-select bg-light select2" placeholder="Please Select" multiple>
                                                <option value="Permanent">Permanent</option>
                                                <option value="Contract">Contract</option>
                                                <option value="Probation">Probation</option>
                                                <option value="Service Bond">Service Bond</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Business Unit</label>
                                            <select name="bisnis_unit[]" id="bisnis_unit" class="form-select bg-light select2" multiple required>
                                                @foreach($allowedGroupCompanies as $allowedGroupCompaniy)
                                                    <option value="{{ $allowedGroupCompaniy }}">{{ $allowedGroupCompaniy }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Filter Company:</label>
                                            <select class="form-select bg-light select2" name="company_filter[]" multiple>
                                                <option value="">Select Company...</option>
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->contribution_level_code }}">{{ $company->contribution_level_code." (".$company->contribution_level.")" }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Filter Locations:</label>
                                            <select class="form-select bg-light select2" name="location_filter[]" multiple>
                                                <option value="">Select location...</option>
                                                @foreach($locations as $location)
                                                    <option value="{{ $location->work_area }}">{{ $location->area." (".$location->company_name.")" }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row my-2" id="check360" style="display:none">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="review_360" name="review_360" value="1">
                                                <label class="form-label" class="custom-control-label" for="review_360">Ignore 360 Review</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="start_join_date">Start Join Date</label>
                                        <input type="date" name="start_join_date" id="start_join_date" class="form-control" placeholder="mm/dd/yyyy">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="last_join_date">Last Join Date</label>
                                        <input type="date" name="last_join_date" id="last_join_date" class="form-control" placeholder="mm/dd/yyyy">
                                    </div>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="start">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" id="start" placeholder="mm/dd/yyyy"  required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="end">End Date</label>
                                        <input type="date" name="end_date" class="form-control" id="end" placeholder="mm/dd/yyyy" required>
                                    </div>
                                </div>
                            </div>
                            <div id="nonmaster2">
                                <div class="row my-2">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="checkbox_reminder" name="checkbox_reminder" value="1">
                                                <label class="form-label" class="custom-control-label" for="checkbox_reminder">Reminder</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="reminders" hidden><hr>
                                    <div class="row my-2">
                                        <div class="col-md-12">
                                            <div class="mb-2">
                                                <label class="form-label" for="inputState">Reminder By</label>
                                                <select id="inputState" name="inputState" class="form-select" onchange="toggleDivs()">
                                                    <option value="repeaton" selected>Repeat On</option>
                                                    <option value="beforeenddate">Before End Date</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="repeaton">
                                        <div class="row">
                                            <div class="col-md-auto">
                                                <div class="btn-group mb-2 d-block d-md-flex" role="group" aria-label="Vertical button group">
                                                    <button type="button" name="repeat_days[]" value="Mon" class="btn btn-outline-primary btn-sm day-button">Mon</button>
                                                    <button type="button" name="repeat_days[]" value="Tue" class="btn btn-outline-primary btn-sm day-button">Tue</button>
                                                    <button type="button" name="repeat_days[]" value="Wed" class="btn btn-outline-primary btn-sm day-button">Wed</button>
                                                    <button type="button" name="repeat_days[]" value="Thu" class="btn btn-outline-primary btn-sm day-button">Thu</button>
                                                    <button type="button" name="repeat_days[]" value="Fri" class="btn btn-outline-primary btn-sm day-button">Fri</button>
                                                    <button type="button" name="repeat_days[]" value="Sat" class="btn btn-outline-primary btn-sm day-button">Sat</button>
                                                    <button type="button" name="repeat_days[]" value="Sun" class="btn btn-outline-primary btn-sm day-button">Sun</button>
                                                </div>
                                            </div>
                                            <div class="col-md-auto text-end">
                                                <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="select-all">Select All</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="beforeenddate" style="display: none;">
                                        <div class="col-md-12">
                                            <div class="input-group mb-3">
                                                <input type="text" class="form-control" name="before_end_date" oninput="validateInput(this)">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">Days</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row my-4">
                                        <div class="col-md-12">
                                            <div class="mb-2">
                                                <label class="form-label" for="messages">Messages</label>
                                                <div id="editor-container" class="form-control bg-light" style="height: 200px;"></div>
                                                <textarea name="messages" id="messages" class="d-none"></textarea>
                                                
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-md-flex justify-content-end text-center">
                                    <input type="hidden" name="repeat_days_selected" id="repeatDaysSelected">
                                    <a href="{{ route('schedules') }}" type="button" class="btn btn-outline-secondary shadow px-4 me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary shadow px-4">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
<!-- Tambahkan script JavaScript untuk mengumpulkan nilai repeat_days[] -->
@push('scripts')
<script>
    var quill = new Quill('#editor-container', {
        theme: 'snow'
    });

    document.getElementById('scheduleForm').addEventListener('submit', function() {
        document.querySelector('textarea[name=messages]').value = quill.root.innerHTML;
    });
    document.getElementById('scheduleForm').addEventListener('submit', function() {
        var repeatDaysButtons = document.getElementsByName('repeat_days[]');
        var repeatDaysSelected = [];
        repeatDaysButtons.forEach(function(button) {
            if (button.classList.contains('active')) {
                repeatDaysSelected.push(button.value);
            }
        });
        document.getElementById('repeatDaysSelected').value = repeatDaysSelected.join(',');
    });

    function toggleDivs() {
        var selectBox = document.getElementById("inputState");
        var repeatOnDiv = document.getElementById("repeaton");
        var beforeEndDateDiv = document.getElementById("beforeenddate");
        
        if (selectBox.value === "repeaton") {
            repeatOnDiv.style.display = "block";
            beforeEndDateDiv.style.display = "none";
        } else {
            repeatOnDiv.style.display = "none";
            beforeEndDateDiv.style.display = "block";
        }
    }

    function changePeriod() {
        var schedulePeriode = document.getElementById("schedule_periode");
        var schedulePeriodeMaster = document.getElementById("schedule_periode_master");
        schedulePeriode.value = schedulePeriodeMaster.value;
    }

    function toggleMaster() {
        var event_type = document.getElementById("event_type");
        var nonmaster1 = document.getElementById("nonmaster1");
        var nonmaster2 = document.getElementById("nonmaster2");
        var check360 = document.getElementById("check360");
        var schedulePeriode = document.getElementById("schedule_periode");
        var schedulePeriodeMaster = document.getElementById("schedule_periode_master");
        var bisnis_unit = document.getElementById("bisnis_unit");
        var last_join_date = document.getElementById("last_join_date");
        const startInput = document.getElementById('start');
        const endInput = document.getElementById('end');
        const startJoinInput = document.getElementById('start_join_date');
        const endJoinInput = document.getElementById('last_join_date');

        if (startInput) {
            startInput.value = "";
        }
        if (endInput) {
            endInput.value = "";
        }
        
        if (event_type.value === "masterschedulepa" || event_type.value === "masterschedulegoals") {
            nonmaster1.style.display = "none";
            nonmaster2.style.display = "none";
            bisnis_unit.removeAttribute("required");
            last_join_date.removeAttribute("required");
            schedulePeriode.classList.add("d-none");
            schedulePeriodeMaster.classList.remove("d-none");
            schedulePeriodeMaster.setAttribute("required", "required");
        } else {
            event_type.value === "schedulepa" ? schedulePeriode.value = "{{ $schedulemasterpa->schedule_periode ?? "" }}" : schedulePeriode.value = "{{ $schedulemastergoals->schedule_periode ?? "" }}";
            nonmaster1.style.display = "block";
            nonmaster2.style.display = "block";
            bisnis_unit.setAttribute("required", "required");

            // last_join_date.setAttribute("required", "required");
            schedulePeriode.classList.remove("d-none");
            schedulePeriodeMaster.classList.add("d-none");
            schedulePeriodeMaster.removeAttribute("required");

        }

        if (event_type.value === 'schedulepa') {
            // Set min and max from the server-rendered values
            const startDate = "{{ optional($schedulemasterpa)->start_date ? \Carbon\Carbon::parse(optional($schedulemasterpa)->start_date)->format('Y-m-d') : '' }}";
            const endDate = "{{ optional($schedulemasterpa)->end_date ? \Carbon\Carbon::parse(optional($schedulemasterpa)->end_date)->format('Y-m-d') : '' }}";
            startInput.min = startDate;
            startInput.max = endDate;
            endInput.min = startDate;
            endInput.max = endDate;

            const startJoinDateFromDB = "{{ optional($schedulemasterpa)->start_join_date ? \Carbon\Carbon::parse(optional($schedulemasterpa)->start_join_date)->format('Y-m-d') : '' }}";
            const lastJoinDateFromDB = "{{ optional($schedulemasterpa)->last_join_date ? \Carbon\Carbon::parse(optional($schedulemasterpa)->last_join_date)->format('Y-m-d') : '' }}";

            if (startJoinDateFromDB) {
                startJoinInput.min = startJoinDateFromDB;
                endJoinInput.min = startJoinDateFromDB;
                startJoinInput.value = startJoinDateFromDB;
                startJoinInput.setAttribute("required", true);
            }

            if (lastJoinDateFromDB) {
                startJoinInput.max = lastJoinDateFromDB;
                endJoinInput.max = lastJoinDateFromDB;
                endJoinInput.value = lastJoinDateFromDB;
                endJoinInput.setAttribute("required", true);
            }
            check360.style.display = "block";
        } else if (event_type.value === 'goals'){
            // Set min and max from the server-rendered values
            const startDate = "{{ optional($schedulemastergoals)->start_date ? \Carbon\Carbon::parse(optional($schedulemastergoals)->start_date)->format('Y-m-d') : '' }}";
            const endDate = "{{ optional($schedulemastergoals)->end_date ? \Carbon\Carbon::parse(optional($schedulemastergoals)->end_date)->format('Y-m-d') : '' }}";
            startInput.min = startDate;
            startInput.max = endDate;
            endInput.min = startDate;
            endInput.max = endDate;

            const startJoinDateFromDB = "{{ optional($schedulemastergoals)->start_join_date ? \Carbon\Carbon::parse(optional($schedulemastergoals)->start_join_date)->format('Y-m-d') : '' }}";
            const lastJoinDateFromDB = "{{ optional($schedulemastergoals)->last_join_date ? \Carbon\Carbon::parse(optional($schedulemastergoals)->last_join_date)->format('Y-m-d') : '' }}";

            if (startJoinDateFromDB) {
                startJoinInput.min = startJoinDateFromDB;
                endJoinInput.min = startJoinDateFromDB;
                startJoinInput.value = startJoinDateFromDB;
                startJoinInput.setAttribute("required", true);
            }

            if (lastJoinDateFromDB) {
                startJoinInput.max = lastJoinDateFromDB;
                endJoinInput.max = lastJoinDateFromDB;
                endJoinInput.value = lastJoinDateFromDB;
                endJoinInput.setAttribute("required", true);
            }
            check360.style.display = "none";
        }else {
            // Clear min and max if event_type is not 'schedulepa'
            startInput.min = '';
            startInput.max = '';
            endInput.min = '';
            endInput.max = '';
            startJoinInput.removeAttribute("required");
            endJoinInput.removeAttribute("required");
            check360.style.display = "none";
        }
    }

    function validateInput(input) {
        //input.value = input.value.replace(/[^0-9,]/g, '');
        input.value = input.value.replace(/[^0-9]/g, '');
    }
</script>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
        });
    });
</script>
@endpush
