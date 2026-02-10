@extends('layouts_.vertical', ['page_title' => 'Reports'])

@section('css')
<style>
    .popover {
    max-width: none; /* Allow popover to grow as wide as content */
    width: auto; /* Automatically adjust width based on content */
    white-space: nowrap; /* Prevent content from wrapping to the next line */
}
</style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Begin Page Content -->
    <!-- Spinner -->
<div id="loading-spinner" style="display: none;">
    <p>Export is in progress...</p>
    <!-- You can add a spinner icon here -->
</div>
    <div class="container-fluid"> 
        @if (session('success'))
            <div class="alert alert-success mt-3">
                {!! session('success') !!}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mt-3">
                {!! session('error') !!}
            </div>
        @endif
        <input id="permission-reportpadetail" data-report-pa-detail="{{ Auth::user()->can('reportpadetail') ? 'true' : 'false' }}" type="hidden">
        <div class="row">
            <div class="col-auto">
                <div class="mb-3 p-1 bg-info-subtle rounded shadow">
                    <span class="mx-2">M = Manager</span>|
                    <span class="mx-2">C = Calibrator</span>|
                    <span class="mx-2">P = Peers</span>|
                    <span class="mx-2">S = Subordinate</span>|
                    <span class="mx-2"><i class="ri-check-line bg-success-subtle text-success rounded fs-18"></i> = Done</span>|
                    <span class="mx-2"><i class="ri-error-warning-line bg-warning-subtle text-warning rounded fs-20"></i> = Pending</span>
                </div>
            </div>
        </div>
        <!-- Content Row -->
        <div class="row">
            <div class="col">
                <div class="d-md">
                  <button class="input-group-text bg-white border-dark-subtle float-end mb-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" id="filter" aria-controls="offcanvasRight"><i class="ri-filter-line me-1"></i>Filters</button>
                </div>
            </div>
          <div class="col-md-12">
            <div class="card shadow mb-4">
              <div class="card-body">
                <table class="table table-sm table-bordered table-hover activate-select dt-responsive nowrap w-100 fs-14 align-middle" id="adminAppraisalTable">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Employee ID</th>
                            <th>Employee Name</th>
                            <th class="d-none">Form ID</th>
                            <th class="d-none">Business Unit</th>
                            <th>M</th>
                            @foreach(['P1', 'P2', 'P3'] as $peers)
                            <th>{{ $peers }}</th>
                            @endforeach
                            @foreach(['S1', 'S2', 'S3'] as $subordinate)
                            <th>{{ $subordinate }}</th>
                            @endforeach
                            @foreach($layerHeaders as $calibrator)
                                <th>{{ $calibrator }}</th>
                            @endforeach
                            <th class="sorting_1">Final Rating</th>
                            <th class="sorting_1 {{ auth()->user()->can('reportpadetail') ? '' : 'd-none' }}">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $index => $employee)
                        <tr data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" data-bs-content="{!! $employee['popoverContent'] !!}">
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $employee['id'] }}</td>
                            <td>{{ $employee['name'] }} {{ $employee['accessPA'] ? ( $employee['appraisalStatus'] ? '' : '(Not Initiated)' ) : '(Not Eligible)' }}</td>
                            <td class="d-none">{{ $employee['appraisalStatus'] ? $employee['appraisalStatus']['id'] : '' }}</td>
                            <td class="d-none">{{ $employee['groupCompany'] }}</td>
    
                            {{-- Manager Layers --}}
                            @php
                                $managerLayer = $employee['approvalStatus']['manager'][0] ?? null;
                            @endphp
                            <td class="text-center
                                @if ($managerLayer) 
                                    {{ $managerLayer['status'] ? 'table-success' : 'table-warning' }} 
                                @else
                                    table-light
                                @endif
                            "
                            data-id="{{ $managerLayer ? ($managerLayer['status'] ? 'Approved - '.$managerLayer['approver_name'].' ('.$managerLayer['approver_id'].')' : 'Pending - '.$managerLayer['approver_name'].' ('.$managerLayer['approver_id'].')') : '-' }}">
                                @if ($managerLayer)
                                    @if($managerLayer['status'])
                                        <i class="ri-check-line text-success fs-20 fw-medium"></i>
                                    @else
                                        <i class="ri-error-warning-line text-warning fs-20 fw-medium"></i>
                                    @endif
                                @endif
                            </td>

                            {{-- Peers Layers --}}
                            @foreach (range(1, 3) as $layer)
                                @php
                                    $peerLayer = $employee['approvalStatus']['peers'][$layer - 1] ?? null;
                                @endphp
                                <td class="text-center
                                    @if ($peerLayer) 
                                        {{ $peerLayer['status'] ? 'table-success' : 'table-warning' }} 
                                    @else
                                        table-light
                                    @endif
                                "
                                data-id="{{ $peerLayer ? ($peerLayer['status'] ? 'Approved - '.$peerLayer['approver_name'].' ('.$peerLayer['approver_id'].')' : 'Pending - '.$peerLayer['approver_name'].' ('.$peerLayer['approver_id'].')') : '-' }}">
                                    @if ($peerLayer)
                                        @if($peerLayer['status'])
                                            <i class="ri-check-line text-success fs-20 fw-medium"></i>
                                        @else
                                            <i class="ri-error-warning-line text-warning fs-20 fw-medium"></i>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
    
                            {{-- Subordinate Layers --}}
                            @foreach (range(1, 3) as $layer)
                                @php
                                    $subordinateLayer = $employee['approvalStatus']['subordinate'][$layer - 1] ?? null;
                                @endphp
                                <td class="text-center
                                    @if ($subordinateLayer) 
                                        {{ $subordinateLayer['status'] ? 'table-success' : 'table-warning' }} 
                                    @else
                                        table-light
                                    @endif"
                                data-id="{{ $subordinateLayer ? ($subordinateLayer['status'] ? 'Approved - '.$subordinateLayer['approver_name'].' ('.$subordinateLayer['approver_id'].')' : 'Pending - '.$subordinateLayer['approver_name'].' ('.$subordinateLayer['approver_id'].')') : '-' }}">
                                    @if ($subordinateLayer)
                                        @if($subordinateLayer['status'])
                                            <i class="ri-check-line text-success fs-20 fw-medium"></i>
                                        @else
                                            <i class="ri-error-warning-line text-warning fs-20 fw-medium"></i>
                                        @endif
                                    @endif
                                </td>
                            @endforeach

                            {{-- Calibrator Layers --}}
                            @foreach ($layerBody as $layer)
                                @php
                                    $calibratorLayer = collect($employee['approvalStatus']['calibrator'] ?? [])->firstWhere('layer', $layer);
                                @endphp
                                <td class="text-center
                                    @if ($calibratorLayer) 
                                        {{ $calibratorLayer['status'] ? 'table-success' : 'table-warning' }} 
                                    @else
                                        table-light
                                    @endif"
                                    data-id="{{ 
                                        $calibratorLayer 
                                            ? ($calibratorLayer['status'] 
                                                ? 'Approved - ' . $calibratorLayer['approver_name'] . ' (' . $calibratorLayer['approver_id'] . ') ' . $calibratorLayer['rating'] ?? '' 
                                                : 'Pending - ' . $calibratorLayer['approver_name'] . ' (' . $calibratorLayer['approver_id'] . ') ' . $calibratorLayer['rating'] ?? '') 
                                            : '-' 
                                    }}"                                    
                                    >
                                    @if ($calibratorLayer)
                                        @if($calibratorLayer['status'])
                                            <i class="ri-check-line text-success fs-20 fw-medium"></i>
                                        @else
                                            <i class="ri-error-warning-line text-warning fs-20 fw-medium"></i>{{ $calibratorLayer['status'] }}
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            
                            <td class="text-center">{{ $employee['finalScore'] }}</td>
                            <td class="sorting_1 text-center {{ auth()->user()->can('reportpadetail') ? '' : 'd-none' }}">
                                @can('reportpadetail')
                                    @if ($employee['appraisalStatus'] && count(collect($employee['approvalStatus'])) != 0)
                                        <a href="{{ route('admin.appraisal.details', encrypt($employee['id'].'_'.$filterInputs['filter_year'])) }}" class="btn btn-sm btn-outline-info"><i class="ri-eye-line"></i></a>
                                    @else
                                        <a class="btn btn-sm btn-outline-secondary" onclick="alert('no data appraisal or pending reviewer')"><i class="ri-eye-line"></i></a>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
              </div>
            </div>
          </div>
      </div>

    <div class="offcanvas offcanvas-end" tabindex="-1"  id="offcanvasRight" aria-labelledby="offcanvasRightLabel" aria-modal="false" role="dialog">
        <div class="offcanvas-header">
            <h5 id="offcanvasRightLabel">Filters</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div> <!-- end offcanvas-header-->

        <div class="offcanvas-body">
          <form id="admin_appraisal_filter" action="{{ url('admin-appraisal') }}" method="GET">
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="filter_year">{{ __('Year') }}</label>
                            <select name="filter_year" id="filter_year" class="form-select">
                                <option value="">{{ __('select all') }}</option>
                                <option value="2024" {{ $filterInputs['filter_year'] == '2024' ? 'selected' : '' }}>2024</option>
                                <option value="2025" {{ $filterInputs['filter_year'] == '2025' ? 'selected' : '' }}>2025</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="group_company">Group Company</label>
                            <select class="form-select select2" name="group_company" id="group_company">
                                <option value="">- {{ __('select') }} -</option>
                                @foreach ($groupCompanies as $item)
                                    <option 
                                        value="{{ $item }}"
                                        {{ $item == $filterInputs['group_company'] ? 'selected' : '' }}
                                    >
                                        {{ $item }}
                                    </option>
                                @endforeach

                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="company">Company</label>
                            <select class="form-select select2" name="company[]" id="company" multiple>
                                @foreach ($companies as $item)
                                    <option {{ in_array($item->contribution_level_code, $filterInputs['company']) ? 'selected' : '' }} value="{{ $item->contribution_level_code }}">{{ $item->company_name .' ('.$item->contribution_level_code.')' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="location">Location</label>
                            <select class="form-select select2" name="location[]" id="location" multiple>
                                @foreach ($locations as $item)
                                    <option
                                        value="{{ $item }}"
                                        {{ in_array($item, $filterInputs['location'] ?? []) ? 'selected' : '' }}
                                    >
                                        {{ $item }}
                                    </option>
                                @endforeach

                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="unit">Unit</label>
                            <select class="form-select select2" name="unit[]" id="unit" multiple>
                                @foreach ($units as $item)
                                    <option
                                        value="{{ $item }}"
                                        {{ in_array($item, $filterInputs['unit'] ?? []) ? 'selected' : '' }}
                                    >
                                        {{ $item }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
          </form>
        </div> <!-- end offcanvas-body-->
        <div class="offcanvas-footer p-3 text-end">
          <button type="button" id="offcanvas-cancel" class="btn btn-outline-secondary me-2" data-bs-dismiss="offcanvas">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary" form="admin_appraisal_filter">Apply</button>
        </div>
    </div>

    </div>
@endsection
@push('scripts')
<script>
    var employeesData = {!! json_encode($datas) !!};
    window.userID = {!! json_encode(auth()->user()->id) !!};
    window.reportFile = {!! json_encode($reportFiles['name'] ?? null) !!};
    window.reportFileDate = {!! json_encode($reportFiles['last_modified'] ?? null) !!};
    window.jobs = {!! json_encode($jobs ?? []) !!};
</script>
@endpush