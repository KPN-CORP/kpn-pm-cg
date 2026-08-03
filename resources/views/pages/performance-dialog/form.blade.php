@extends('layouts_.vertical', ['page_title' => 'Performance Dialog'])

@section('css')
<style>
    .ai-loader{display:inline-flex;gap:.35rem;align-items:center}
    .ai-loader .dot{
        width:.55rem;height:.55rem;border-radius:50%;
        background:linear-gradient(90deg,#8ab4ff,#a78bfa,#f472b6);
        filter:saturate(115%); animation:ai-bounce 1.1s infinite ease-in-out;
        box-shadow:0 0 .35rem rgba(80,102,255,.35);
    }
    .ai-loader .dot:nth-child(2){animation-delay:.15s}
    .ai-loader .dot:nth-child(3){animation-delay:.30s}
    @keyframes ai-bounce{0%,80%,100%{transform:scale(.6);opacity:.6}40%{transform:scale(1);opacity:1}}
</style>
<style>
    .container-fluid.p-0{
        position: relative;
        border-radius: 12px;
        overflow: hidden;
    }

    .section-loader{
        position: absolute; inset: 0; z-index: 1050;
        display: flex; align-items: center; justify-content: center;
        pointer-events: all;
        border-radius: inherit;
        background: linear-gradient(135deg,
            rgba(62,96,213,.65) 0%,
            rgba(167,139,250,.65) 50%,
            rgba(244,114,182,.65) 100%);
        background-size: 200% 200%;
        animation: gradient-move 4s ease-in-out infinite;
        opacity: 1; transition: opacity .35s ease;
    }
    .section-loader.is-fading{ opacity: 0; }

    @keyframes gradient-move{
        0%{background-position:0% 0%}
        50%{background-position:100% 100%}
        100%{background-position:0% 0%}
    }

    .section-loader__inner{
        display:inline-flex; gap:.5rem; align-items:center;
        padding:.5rem .75rem; border-radius:.75rem;
        background: rgba(255,255,255,.65); backdrop-filter: blur(2px);
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .select2-selection,
    .select2-selection * {
        cursor: pointer !important;
    }

    .form-control[readonly],
    .form-select[readonly],
    textarea.form-control[readonly] {
        background-color: #e9ecef;
        opacity: 1;
        color: #6c757d;
        cursor: not-allowed;
    }

    .select2-readonly + .select2 .select2-selection {
        pointer-events: none;
        cursor: default !important;
        background-color: #e9ecef;
    }

    .select2-readonly + .select2 .select2-selection * {
        cursor: default !important;
    }
</style>
@endsection

@section('content')
    <div class="container-fluid">

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
        </div>
    @endif

    @if ($is_show_employee_detail)
        <div class="detail-employee">
            <div class="row mb-2">
                <div class="col-12">
                    <div class="card shadow-sm bg-primary text-white">
                        <div class="card-body p-2 pb-0">
                            <div class="row">
                                <div class="col-md">
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Employee Name:</strong> {{ $employee_name }}</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Employee ID:</strong> {{ $employee_id }}</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Job Level:</strong> {{ $employee_job_level }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md">
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Business Unit:</strong> {{ $employee_group_company }}</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Division:</strong> {{ $employee_unit }}</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <p class="mb-2"><strong>Designation:</strong> {{ $employee_designation_name }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <br/>
    @endif

    <div class="mandatory-field"></div>
        <div id="form-alert" class="alert alert-danger d-none"></div>
        <form id="performance-dialog-form"
            @if ($is_form_acknowledge)
                action="{{ route('performance-dialog.acknowledge') }}"
            @elseif ($is_form_delete)
                action="{{ route('performance-dialog-task.delete') }}"
            @else
                action="{{ route('performance-dialog.create-or-update') }}"
            @endif
            class="needs-validation" method="POST">
            @csrf
            <input type="hidden" class="form-control" name="id" value="{{ $id }}" readonly>
            <input type="hidden" class="form-control" name="period" value="{{ $period }}" readonly>
            <input type="hidden" class="form-control" name="employee_id" value="{{ $employee_id }}" readonly>
            <input type="hidden" class="form-control" name="employee_manager_id" value="{{ $employee_manager_id }}" readonly>
            <div class="row mb-2">
                @php
                    $colNum = "6";

                    if ($is_show_select_employee && $is_show_start_date) {
                        $colNum = "4";
                    }
                @endphp
                @if ($is_show_select_employee)
                    <div class="col-md-{{ $colNum }}">
                        <label for="performance_dialog_employee" class="form-label">Employees</label>
                        <select class="form-select form-select-sm select2" id="performance_dialog_employee" name="performance_dialog_employee_ids[]" data-placeholder="Select Employees" multiple required>
                            <option></option>
                            @foreach ($reportees as $row)
                                @if ($row->employee && $row->employee->fullname)
                                    <option value="{{ $row->employee_id }}">{{ $row->employee->fullname }} ({{ $row->employee_id }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-{{ $colNum }}">
                    <label for="performance_dialog_type" class="form-label">Objectives</label>
                    <select class="form-select form-select-sm select2 {{ $is_performance_dialog_types_readonly ? 'select2-readonly' : '' }}" id="performance_dialog_type" name="performance_dialog_types[]" data-placeholder="Select Objectives" multiple required {{ $is_performance_dialog_types_readonly ? 'readonly' : '' }}>
                        <option></option>
                        @foreach ($master_performance_dialog_types as $master_performance_dialog_type)
                            @php
                                $isSelected = false;

                                if ($performance_dialog_types) {
                                    foreach ($performance_dialog_types as $performance_dialog_type) {
                                        if ($master_performance_dialog_type->name == $performance_dialog_type["name"]) {
                                            $isSelected = true;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <option value="{{ $master_performance_dialog_type->id }}" {{ $isSelected ? "selected" : "" }}>{{ $master_performance_dialog_type->name }}</option>
                        @endforeach
                        @if (!empty($performance_dialog_others_type_name))
                            <option value="0" selected>Others</option>
                        @else
                            <option value="0">Others</option>
                        @endif
                    </select>
                    <div class="row">
                        <div class="">
                            <input type="text" name="others_performance_dialog_type" id="others_performance_dialog_type"
                            class="form-control form-control-sm" placeholder="ex: Penilaian Kerja"
                            value="{{ $performance_dialog_others_type_name }}" style="{{ empty($performance_dialog_others_type_name) ? "display: none" : "" }};margin-top:10px" {{ $is_others_performance_dialog_type_readonly ? 'readonly' : '' }}>
                        </div>
                    </div>
                </div>
                @if ($is_show_start_date)
                    <div class="col-md-{{ $colNum }}">
                        <label for="performance_dialog_start_date" class="form-label">Schedule Date</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="performance_dialog_start_date" name="performance_dialog_start_date" value="{{ $performance_dialog_start_date }}" required {{ $is_performance_dialog_start_date_readonly ? 'readonly' : '' }}>
                    </div>
                @endif
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <label for="" class="form-label">Development Plan</label>
                    <textarea class="form-control form-control-sm" id="performance_dialog_development_plan" name="performance_dialog_development_plan" rows="4"
                        placeholder="Please add more detail of development plan ..." {{ $is_performance_dialog_development_plan_readonly ? 'readonly' : '' }} required>{{ $performance_dialog_development_plan }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="performance_dialog_due_date" class="form-label">Due Date</label>
                    <input type="datetime-local" class="form-control form-control-sm" id="performance_dialog_due_date" name="performance_dialog_due_date" value="{{ $performance_dialog_due_date }}" required {{ $is_performance_dialog_due_date_readonly ? 'readonly' : '' }}>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6">
                    <label for="" class="form-label">Summary</label>
                    <textarea class="form-control form-control-sm" id="performance_dialog_summary" name="performance_dialog_summary" rows="4"
                        placeholder="Please add more detail of summary ..." {{ $is_performance_dialog_summary_readonly ? 'readonly' : '' }} required>{{ $performance_dialog_summary }}</textarea>
                </div>
                <div class="col-md-6">
                    <label for="" class="form-label">Additional Notes</label>
                    <textarea class="form-control form-control-sm" id="performance_dialog_additional_notes" name="performance_dialog_additional_notes" rows="4"
                        placeholder="Please add more detail of additional notes ..." {{ $is_performance_dialog_additional_notes_readonly ? 'readonly' : '' }}>{{ $performance_dialog_additional_notes }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4 mb-4">
                @if ($is_form_acknowledge)
                    <a class="btn btn-outline-secondary rounded-pill submit-button me-2" href="{{ $redirect_back }}">Back</a>
                    <button type="submit" class="btn btn-primary rounded-pill submit-button"
                        name="action_acknowledge" value="Acknowledge" id="performance-dialog-acknowledge">Acknowledge</button>
                @elseif ($is_form_delete)
                    <a class="btn btn-outline-secondary rounded-pill submit-button me-2" href="{{ $redirect_back }}">Back</a>
                    <button type="submit" class="btn btn-primary rounded-pill submit-button"
                        name="action_delete" value="Delete" id="performance-dialog-delete">Delete</button>
                @elseif ($is_form_approval)
                    <a class="btn btn-outline-secondary rounded-pill submit-button me-2" href="{{ $redirect_back }}">Back</a>
                    <button type="submit" class="btn btn-primary rounded-pill submit-button"
                        name="action_approve" value="Approve" id="performance-dialog-approve">Approve</button>
                @elseif ($is_form_create || $is_form_edit)
                    <button type="submit" class="btn btn-outline-primary rounded-pill me-2 draft-button"
                        name="action_draft" value="Draft" id="performance-dialog-save-draft">Save as
                        Draft</button>
                    <button type="submit" class="btn btn-primary rounded-pill submit-button"
                        name="action_submit" value="Submitted" id="performance-dialog-submit">Submit</button>
                @else
                    <a class="btn btn-outline-secondary rounded-pill submit-button" href="{{ $redirect_back }}">Back</a>
                @endif
            </div>
        </form>
    </div>
@endsection
@push('scripts')
    @if($is_performance_dialog_types_readonly)
        <script>
            $(document).ready(function () {
                $('#performance_dialog_type').on('select2:opening', function (e) {
                    if ($(this).hasClass('select2-readonly')) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        </script>
    @endif
@endpush
