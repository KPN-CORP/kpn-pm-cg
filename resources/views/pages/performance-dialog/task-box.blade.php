@extends('layouts_.vertical', ['page_title' => 'Performance Dialog - Task Box'])

@section('css')
<style>
    .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    .table-responsive, .dataTables_scroll {
        width: 100%;
    }
    #tablePerformanceDialog tbody td {
        vertical-align: middle;
    }
</style>
@endsection

@section('content')
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif
        <div class="mandatory-field">
            <div id="alertField" class="alert alert-danger alert-dismissible {{ Session::has('error') ? '':'fade' }}" role="alert" {{ Session::has('error') ? '':'hidden' }}>
                @php
                    $error = Session::get('error');

                    if (is_array($error)) {
                        $error = implode(', ', $error);
                    }
                @endphp
                <strong>
                    {!! $error !!}
                </strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                          <div class="tab-content" id="myTabContent">
                            <form id="formPerformanceDialogFilter" action="{{ route('performance-dialog-task') }}" method="GET">
                                @php
                                    $filterYear = request('filterYear');
                                @endphp
                                <div class="row align-items-end justify-content-between">
                                    <div class="d-flex align-items-end gap-3">
                                        <div style="width: 140px;">
                                            <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                            <select name="filterYear" id="filterYear" onchange="filterTriggerPerformanceDialogTask(this)" class="form-select">
                                                @foreach ($performance_dialog_years as $year)
                                                    <option value="{{ $year }}" {{ $year == $period ? 'selected' : '' }}>
                                                        {{ $year }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div style="width: 180px;">
                                            <label class="form-label" for="filterStatus">Status</label>
                                            <select name="filterStatus" id="filterStatus" onchange="filterTriggerPerformanceDialogTask(this)" class="form-select">
                                                @foreach ($performance_dialog_statuses as $pd_status)
                                                    <option value="{{ $pd_status }}" {{ $pd_status == $current_active_status ? 'selected' : '' }}>
                                                        {{ $pd_status }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div style="width: 160px;">
                                            <label for="filterInitiateDate" class="form-label">Initiate Date</label>
                                            <input type="date" class="form-control form-control-sm" id="filterInitiateDate" name="filterInitiateDate" value="{{ $current_filter_initiate_date }}" onchange="filterTriggerPerformanceDialogTask(this)">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <p style="float:right;margin-top:-40px;display:inline-block">
                                            <span><strong>Total Team:</strong> {{ $total_team }}</span> |
                                            <span><strong>Done:</strong> {{ $total_done }}</span> |
                                            <span><strong>Scheduled:</strong> {{ $total_scheduled }}</span> |
                                            <span><strong>Draft:</strong> {{ $total_draft }}</span> |
                                            <span><strong>Overdue:</strong> {{ $total_overdue }}</span> |
                                            <span><strong>Not Scheduled:</strong> {{ $total_not_scheduled }}</span>
                                        </p>
                                        <button style="float:right;margin-left:5px" type="button" class="btn btn-outline-info shadow" data-bs-toggle="modal" data-bs-target="#importModal"><i class="ri-upload-2-line"></i> Import</button>
                                        <a style="float:right;margin-left:5px" class="btn btn-primary shadow" href="{{ route('performance-dialog.form') }}" onclick="showLoader()"><i class="ri-file-line"></i> Initiate</a>
                                        <button style="float:right;margin-left:5px" type="button" class="btn btn-outline-warning shadow" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="setPerformanceDialogSchedule()"><i class="ri-calendar-line"></i> Set Schedule</button>
                                    </div>
                                </div>
                            </form>
                            <div class="tab-pane fade show active" id="team" role="tabpanel" aria-labelledby="team-tab">
                                <div class="table-responsive">
                                    <table id="tablePerformanceDialog" class="table table-hover table-sm activate-select dataTables_scrollHeadInner">
                                        <thead>
                                            <tr>
                                                <th class="text-center">No</th>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Schedule Date</th>
                                                <th>Initiated Date</th>
                                                <th class="text-center">Status</th>
                                                <th class="text-center sorting_1">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($rows as $row)
                                                <tr>
                                                    <td class="text-center"></td>
                                                    <td>{{ $row['employee_id'] }}</td>
                                                    <td>{{ $row['employee_name'] }}</td>
                                                    <td>{{ $row['formatted_schedule_at'] }}</td>
                                                    <td>{{ $row['formatted_initiated_at'] }}</td>
                                                    <td class="text-center">
                                                        @php
                                                            $status = strtolower($row['status']);
                                                            $statusClass = match($status) {
                                                                'draft' => 'secondary',
                                                                'overdue' => 'secondary',
                                                                'scheduled' => 'warning',
                                                                'approved' => 'success',
                                                                'done' => 'success',
                                                                default => 'light text-body'
                                                            };
                                                        @endphp
                                                        <span class="badge bg-{{ $statusClass }}">
                                                            {{ $row['status'] }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($row['is_action_schedule'])
                                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="setPerformanceDialogScheduleEmployee('{{ $row['employee_id'] }}', '{{ $row['employee_name'] }}')"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Set Schedule"
                                                            >
                                                                <i class="ri-calendar-line"></i>
                                                            </button>
                                                        @endif
                                                        @if ($row['is_action_initiate'])
                                                            <a class="btn btn-sm btn-primary" href="{{ route('performance-dialog.form') }}" onclick="showLoader()"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Initiate"
                                                            >
                                                                <i class="ri-file-line"></i>
                                                            </a>
                                                        @endif
                                                        @if ($row['is_action_edit_initiate'])
                                                            <a class="btn btn-sm btn-primary" href="{{ route('performance-dialog.form-edit', [
                                                                'id' => $row['id'],
                                                                'action' => 'edit',
                                                            ]) }}" onclick="showLoader()"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Initiate"
                                                            >
                                                                <i class="ri-file-line"></i>
                                                            </a>
                                                        @endif
                                                        @if ($row['is_action_edit'])
                                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('performance-dialog.form-edit', [
                                                                'id' => $row['id'],
                                                                'action' => 'edit',
                                                            ]) }}" onclick="showLoader()"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Edit"
                                                            >
                                                                <i class="ri-pencil-line"></i>
                                                            </a>
                                                        @endif
                                                        @if ($row['is_action_download'])
                                                            <a class="btn btn-sm btn-outline-info" href="{{ route('performance-dialog.download', $row['id']) }}" target="_blank"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Download"
                                                            >
                                                                <i class="ri-download-line"></i>
                                                            </a>
                                                        @endif
                                                        @if ($row['is_action_delete'])
                                                            <a class="btn btn-sm btn-outline-danger" href="{{ route('performance-dialog.form-delete', [
                                                                'id' => $row['id'],
                                                                'action' => 'delete',
                                                            ]) }}"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Delete"
                                                            >
                                                                <i class="ri-delete-bin-line"></i>
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Performance Dialog</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importPerformanceDialog" action="{{ route('performance-dialog-task.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">
                                    <strong>Notes:</strong>
                                    <ul class="mb-0">
                                        <li>Template Import Performance Dialog can be downloaded <strong><a href="{{ asset('templates/template_performance_dialog.xlsx') }}" style="text-decoration: underline" download>here</a></strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="file">Upload File<span class="text-danger">*</span></label>
                            <input type="file" name="file" id="file" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="importPerformanceDialogButton" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                            Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel">Set Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div id="schedule-performance-dialog-form-alert" class="alert alert-danger d-none"></div>
                <form id="schedule-performance-dialog" action="{{ route('performance-dialog-task.set-schedule') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="employee_id" id="performance-dialog-schedule-form-employee-id" class="form-control">
                        <div id="performance-dialog-schedule-form-employee-name-elem" class="form-group mb-2">
                            <label for="performance-dialog-schedule-form-employee-name" class="form-label">Employee</label>
                            <input type="text" class="form-control form-control-sm" id="performance-dialog-schedule-form-employee-name" name="employee_name" value="" disabled>
                        </div>
                        <div class="form-group mb-2">
                            <label for="performance-dialog-schedule-form-schedule-date" class="form-label">Schedule Date<span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control form-control-sm" id="performance-dialog-schedule-form-schedule-date" name="start_date" required>
                        </div>
                        <div id="performance-dialog-schedule-form-employee-elem" class="form-group">
                            <label for="performance-dialog-schedule-form-employee" class="form-label">Employees<span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm select2" id="performance-dialog-schedule-form-employee" name="employee_ids[]" data-placeholder="Select Employees" multiple required>
                                <option></option>
                                @foreach ($reportees as $row)
                                    @if ($row->employee && $row->employee->fullname)
                                        <option value="{{ $row->employee_id }}">{{ $row->employee->fullname }} ({{ $row->employee_id }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="schedule-performance-dialog-submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                            Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    @if(Session::has('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: "error",
                    title: "Cannot initiate performance dialog!",
                    text: '{!! Session::get('error_client') ?? $error !!}',
                    confirmButtonText: "OK",
                });
            });
        </script>
    @endif
@endpush
