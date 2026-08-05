@extends('layouts_.vertical', ['page_title' => 'Performance Dialog - My History'])

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
                            <form id="formPerformanceDialogFilter" action="{{ route('performance-dialog.my-history') }}" method="GET">
                                @php
                                    $filterYear = request('filterYear');
                                @endphp
                                <div class="row align-items-end justify-content-between">
                                    <div class="d-flex align-items-end gap-3 mb-2">
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
                                </div>
                            </form>
                            <div class="tab-pane fade show active" id="team" role="tabpanel" aria-labelledby="team-tab">
                                <div class="table-responsive">
                                    <table id="tablePerformanceDialog" class="table table-hover table-sm activate-select dataTables_scrollHeadInner">
                                        <thead>
                                            <tr>
                                                <th class="text-center">No</th>
                                                <th>Employee ID</th>
                                                <th>Employee Name</th>
                                                <th>Manager Name</th>
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
                                                    <td>{{ $row['employee_manager_name'] }}</td>
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
                                                        @if ($row['is_action_download'])
                                                            <a class="btn btn-sm btn-outline-info" href="{{ route('performance-dialog.download', $row['id']) }}" target="_blank"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Download"
                                                            >
                                                                <i class="ri-download-line"></i>
                                                            </a>
                                                        @endif
                                                        @if ($row['is_action_acknowledge'])
                                                            <a class="btn btn-sm btn-outline-warning" href="{{ route('performance-dialog.form-acknowledge', [
                                                                'id' => $row['id'],
                                                                'action' => 'acknowledge',
                                                            ]) }}"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-placement="top"
                                                            title="Acknowledge"
                                                            >
                                                                <i class="ri-task-line"></i>
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
@endsection
@push('scripts')
    @if(Session::has('error'))
        <script>
        </script>
    @endif
@endpush
