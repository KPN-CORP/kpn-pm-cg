@extends('layouts_.vertical', ['page_title' => 'Schedule'])

@section('css')
@endsection

@section('content')
    <!-- Begin Page Content -->
<div class="container-fluid">
    <div class="pt-3 row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-left">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><h4><a href="{{ route('schedules') }}">{{ $parentLink }}</a></h4></li>
                        <li class="breadcrumb-item">{{ $link }}</li>
                        <li class="breadcrumb-item active">{{ $sublink }}</li>
                    </ol>
                </div>
                
            </div>
        </div>
    </div>
    <div class="d-sm-flex align-items-center justify-content-right">
        <div class="card col-md-6">
            <div class="card-header d-flex bg-white justify-content-between">
                <h4 class="modal-title" id="viewFormEmployeeLabel">Schedule</h4>
                <a href="{{ route('schedules') }}" type="button" class="btn btn-close"></a>
            </div>
                <div class="card-body" @style('overflow-y: auto;')>
                    <div class="container-fluid">
                        <form id="scheduleForm" method="post" action="{{ route('update-schedule') }}">@csrf
                            <div class="row g-2">
                                <div class="mb-3 col-md-6">
                                    <label class="form-label" for="name">Schedule Name</label>
                                    <input type="text" class="form-control bg-light" placeholder="Enter name.." id="name" name="schedule_name" value="{{ $model->schedule_name }}" readonly>
                                    <input type="hidden" class="form-control bg-light" placeholder="Enter name.." id="id_schedule" name="id_schedule" value="{{ $model->id }}" readonly>
                                    <input type="hidden" id="event_type_s" name="event_type_s" value="{{ $model->event_type }}" readonly>
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label class="form-label" for="type">Event Type</label>
                                    <input type="text" class="form-control bg-light" id="event_type" name="event_type" 
                                    value="@if($model->event_type=='goals')Goals
                                    @elseif($model->event_type=='schedulepa')Schedule PA
                                    @elseif($model->event_type=='masterschedulepa')Master Schedule PA
                                    @elseif($model->event_type=='masterschedulegoals')Master Schedule Goal Settings
                                    @endif" readonly>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col-md-12">
                                    <div class="mb-2">
                                        <label class="form-label" for="type">Schedule Periode</label>
                                        <input type="text" class="form-control bg-light" id="schedule_periode" name="schedule_periode" value="{{ $model->schedule_periode }}" readonly>
                                    </div>
                                </div>
                            </div>
                            <div id="nonmaster1" @if($hidediv=='1') style="display:none" @endif>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Employee Type</label>
                                            <input type="text" class="form-control bg-light" id="employee_type" name="employee_type" value="{{ $model->employee_type }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Bisnis Unit</label>
                                            <input type="text" class="form-control bg-light" id="bisnis_unit" name="bisnis_unit" value="{{ $model->bisnis_unit }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Filter Company:</label>
                                                <input type="text" class="form-control bg-light" id="company_filter" name="company_filter" value="{{ $model->company_filter }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="row my-2">
                                    <div class="col-md-12">
                                        <div class="mb-2">
                                            <label class="form-label" for="type">Filter Locations:</label>
                                                <input type="text" class="form-control bg-light" id="location_filter" name="location_filter" value="{{ $model->location_filter }}" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                @if($schedulemasterpa)
                                <div class="row my-2">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="review_360" name="review_360" value="1" @if ($model->review_360 == 1) checked @endif>
                                                <label class="form-label" class="custom-control-label" for="review_360">Ignore 360 Review</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                            <div class="row my-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="start_join_date">Start Join Date</label>
                                        <input type="date" name="start_join_date" id="start_join_date" class="form-control" value="{{ $model->start_join_date }}" onclick="toggleMaster()" placeholder="mm/dd/yyyy">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="last_join_date">Last Join Date</label>
                                        <input type="date" name="last_join_date" id="last_join_date" class="form-control" value="{{ $model->last_join_date }}" required onclick="toggleMaster()" placeholder="mm/dd/yyyy">
                                    </div>
                                </div>
                            </div>
                            <div class="row my-2">
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="start">Start Date</label>
                                        <input type="date" name="start_date" class="form-control" id="start" value="{{ $model->start_date }}" placeholder="mm/dd/yyyy" onclick="toggleMaster()">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <label class="form-label" for="end">End Date</label>
                                        <input type="date" name="end_date" class="form-control" id="end" value="{{ $model->end_date }}" placeholder="mm/dd/yyyy" onclick="toggleMaster()">
                                    </div>
                                </div>
                            </div>
                            <div id="nonmaster2" @if($hidediv=='1') style="display:none" @endif>
                                <div class="row my-2">
                                    <div class="col-md-5">
                                        <div class="mb-2">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="checkbox_reminder" name="checkbox_reminder" value="1" @if ($model->checkbox_reminder == 1) checked @endif>
                                                <label class="form-label" class="custom-control-label" for="checkbox_reminder">Reminder</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="reminders" @if ($model->checkbox_reminder == 0) hidden @endif>
                                    <div class="row my-2">
                                        <div class="col-md-4">
                                            <div class="mb-2">
                                                <label class="form-label" for="inputState">Reminder By</label>
                                                <select id="inputState" name="inputState" class="form-select" onchange="toggleDivs()">
                                                    <option value="repeaton" @if ($model->inputState == 'repeaton') selected @endif>Repeat On</option>
                                                    <option value="beforeenddate" @if ($model->inputState == 'beforeenddate') selected @endif>Before End Date</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="repeaton" style="display: @if ($model->inputState == 'beforeenddate') none @endif">
                                        <div class="row">
                                            <div class="col-12 col-md-auto">
                                                @php $repeat_days = $repeat_days = explode(',', $model->repeat_days); @endphp
                                                <div class="btn-group mb-2 d-block d-md-flex" role="group" aria-label="Vertical button group">
                                                    <button type="button" name="repeat_days[]" value="Mon" class="btn btn-outline-primary btn-sm day-button {{ in_array('Mon', $repeat_days) ? 'active' : '' }}">Mon</button>
                                                    <button type="button" name="repeat_days[]" value="Tue" class="btn btn-outline-primary btn-sm day-button {{ in_array('Tue', $repeat_days) ? 'active' : '' }}">Tue</button>
                                                    <button type="button" name="repeat_days[]" value="Wed" class="btn btn-outline-primary btn-sm day-button {{ in_array('Wed', $repeat_days) ? 'active' : '' }}">Wed</button>
                                                    <button type="button" name="repeat_days[]" value="Thu" class="btn btn-outline-primary btn-sm day-button {{ in_array('Thu', $repeat_days) ? 'active' : '' }}">Thu</button>
                                                    <button type="button" name="repeat_days[]" value="Fri" class="btn btn-outline-primary btn-sm day-button {{ in_array('Fri', $repeat_days) ? 'active' : '' }}">Fri</button>
                                                    <button type="button" name="repeat_days[]" value="Sat" class="btn btn-outline-primary btn-sm day-button {{ in_array('Sat', $repeat_days) ? 'active' : '' }}">Sat</button>
                                                    <button type="button" name="repeat_days[]" value="Sun" class="btn btn-outline-primary btn-sm day-button {{ in_array('Sun', $repeat_days) ? 'active' : '' }}">Sun</button>
                                                </div>
                                            </div>
                                            <div class="col-md-auto text-end">
                                                <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="select-all">Select All</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="beforeenddate" style="display: @if ($model->inputState == 'repeaton' || $model->checkbox_reminder == 0) none @endif">
                                        <div class="col-md-4">
                                            <div class="input-group mb-3">
                                                <input type="text" class="form-control" name="before_end_date" oninput="validateInput(this)" value="{{ $model->before_end_date }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">Days</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row my-4">
                                        <div class="col-md-8">
                                            <div class="mb-2">
                                                <label class="form-label" for="messages">Messages</label>

                                                {{-- Quill Editor --}}
                                                <div id="editor-container" class="form-control" style="height: 200px;">
                                                    {!! $model->messages !!}
                                                </div>

                                                {{-- Hidden textarea untuk dikirim ke backend --}}
                                                <textarea name="messages" id="messages" class="d-none"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md d-md-flex justify-content-end text-center">
                                    <input type="hidden" name="repeat_days_selected" id="repeatDaysSelected">
                                    <a href="{{ route('schedules') }}" type="button" class="btn btn-danger rounded-pill shadow px-4 me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary rounded-pill shadow px-4">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
<!-- Tambahkan script JavaScript untuk mengumpulkan nilai repeat_days[] -->
@push('scripts')
<script>

    var reminderChecked = document.getElementById('checkbox_reminder').checked;

    var repeatDaysButtons = document.getElementsByName('repeat_days[]');
    var repeatDaysSelected = [];

    
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        
        var messageContent = quill.root.innerHTML.trim();
        // set ke textarea hidden
        document.getElementById('messages').value = messageContent;

        // VALIDASI MESSAGE
        if (reminderChecked && (messageContent === '' || messageContent === '<p><br></p>')) {
            e.preventDefault();
            alert('Message wajib diisi jika Reminder dicentang.');
            document.getElementById('editor-container').focus();
            return false;
        }

        // kumpulkan repeat days
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
    function toggleMaster() {
        
        var event_type = document.getElementById("event_type_s");
        const startInput = document.getElementById('start');
        const endInput = document.getElementById('end');
        const startJoinInput = document.getElementById('start_join_date');
        const endJoinInput = document.getElementById('last_join_date');
        
        // console.log(startInput);
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
                startJoinInput.setAttribute("required", true);
            }            

            if (lastJoinDateFromDB) {
                startJoinInput.max = lastJoinDateFromDB;
                endJoinInput.max = lastJoinDateFromDB;
                endJoinInput.setAttribute("required", true);
            }
        } else if (event_type.value === 'goals') {
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
                startJoinInput.setAttribute("required", true);
            }

                        console.log(lastJoinDateFromDB);


            if (lastJoinDateFromDB) {
                startJoinInput.max = lastJoinDateFromDB;
                endJoinInput.max = lastJoinDateFromDB;
                endJoinInput.setAttribute("required", true);
            }
        }else {
            // Clear min and max if event_type is not 'schedulepa'
            startInput.min = '';
            startInput.max = '';
            endInput.min = '';
            endInput.max = '';
            startJoinInput.removeAttribute("required");
        }
    }

    function validateInput(input) {
        //input.value = input.value.replace(/[^0-9,]/g, '');
        input.value = input.value.replace(/[^0-9]/g, '');
    }
</script>
@endpush