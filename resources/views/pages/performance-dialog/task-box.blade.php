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
                                    <div class="col-md-3">
                                        <div class="mb-2">
                                            <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                            <select name="filterYear" id="filterYear" onchange="yearPerformanceDialogTask(this)" class="form-select">
                                                @foreach ($performance_dialog_years as $year)
                                                    <option value="{{ $year }}" {{ $year == $period ? 'selected' : '' }}>
                                                        {{ $year }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-auto">
                                        <button type="button" class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#importModal">Import Performance Dialog</button>
                                    </div>
                                </div>
                            </form>
                            <div class="tab-pane fade show active" id="team" role="tabpanel" aria-labelledby="team-tab">
                                <div class="table-responsive">
                                    <table id="tablePerformanceDialog" class="table table-hover table-sm activate-select dataTables_scrollHeadInner">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Period</th>
                                                <th>{{ __('Initiated Date') }}</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                                <th class="sorting_1">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($performance_dialogs as $row)
                                                <tr>
                                                    <td class="text-center"></td>
                                                    <td>{{ $row->employee_id ?? '-' }}</td>
                                                    <td>{{ $row->employee ? $row->employee->fullname : '-' }}</td>
                                                    <td>{{ $row->period ?? '-' }}</td>
                                                    <td>{{ $row->formatted_created_at ?? '-' }}</td>
                                                    <td>{{ $row->formatted_due_date ?? '-' }}</td>
                                                    <td class="text-center">
                                                        @php
                                                            $status = strtolower($row->status ?? '');
                                                            $class = match($status) {
                                                                'draft' => 'secondary',
                                                                'approved' => 'success',
                                                                default => 'light text-body'
                                                            };
                                                        @endphp
                                                        <span class="badge bg-{{ $class }}">
                                                            {{ $row->status }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        @if ($row->status != "Draft")
                                                            <a class="btn btn-sm btn-outline-primary fw-semibold" href="{{ route('performance-dialog.form-view', $row->id) }}" onclick="showLoader()">
                                                                <i class="ri-eye-line"></i>
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
                <form id="importGoal" action="{{ route('performance-dialog-task.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        {{-- <div class="row">
                            <div class="col">
                                <div class="alert alert-info">
                                    <strong>Notes:</strong>
                                    <ul class="mb-0">
                                        <li>{{ __('Note Import Performance Dialog Manager') }}<strong><br> > Tab "{{ __('Not Initiated') }}" -> {{ __('Download') }}</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div> --}}
                        <div class="form-group">
                            <label for="file">Upload File</label>
                            <input type="file" name="file" id="file" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" id="importGoalsButton" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                            Import
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
