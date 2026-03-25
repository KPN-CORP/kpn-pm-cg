@extends('layouts_.vertical', ['page_title' => 'Team Goals'])

@section('css')
@endsection

@section('content')
    <!-- Begin Page Content -->
            <div class="container-fluid">
                @if (session('success'))
                    <div class="alert alert-success mt-3">
                        {!! session('success') !!}
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger mt-3">
                        {!! is_array(session('error')) ? session('error')['message'] : session('error') !!}
                    </div>
                @endif
                <div class="row my-3">
                    <div class="col-md">
                        <ul class="nav nav-pills justify-content-md-start justify-content-center" id="myTab" role="tablist">
                            <li class="nav-item">
                              <button class="btn btn-outline-primary position-relative active me-2 mb-3" id="initiated-tab" data-bs-toggle="tab" data-bs-target="#initiated" type="button" role="tab" aria-controls="initiated" aria-selected="true">
                                {{ __('Approval') }}
                                <span class="position-absolute top-0 start-100 translate-middle badge bg-danger {{ $notificationGoal ? '' : 'd-none' }}">
                                    {{ $notificationGoal }}
                                </span>
                              </button>
                            </li>
                            <li class="nav-item">
                              <button class="btn btn-outline-secondary position-relative mb-3" id="not-initiated-tab" data-bs-toggle="tab" data-bs-target="#not-initiated" type="button" role="tab" aria-controls="not-initiated" aria-selected="false">
                                {{ __('Not Initiated') }}
                                <span class="position-absolute top-0 start-100 translate-middle badge bg-danger {{ count($notasks) ? '' : 'd-none' }}">
                                    {{ count($notasks) }}
                                </span>
                              </button>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#importModal">Import Goals</button>
                    </div>
                </div>
                <div class="tab-content">
                    <div class="tab-pane active show" id="initiated" role="tabpanel">
                        <div class="row rounded mb-2">
                            <div class="col-lg-auto text-center">
                              <div class="align-items-center">
                                  <button class="btn btn-outline-primary btn-sm my-1 me-1 filter-btn" data-id="All Task">{{ __('All Task') }}</button>
                                  <button class="btn btn-outline-primary btn-sm my-1 me-1 filter-btn" data-id="draft">Draft</button>
                                  <button class="btn btn-outline-primary btn-sm my-1 me-1 filter-btn" data-id="{{ __('Waiting For Revision') }}">{{ __('Waiting For Revision') }}</button>
                                  <button class="btn btn-outline-primary btn-sm my-1 me-1 filter-btn" data-id="{{ __('Pending') }}">{{ __('Pending') }}</button>
                                  <button class="btn btn-outline-primary btn-sm my-1 me-1 filter-btn" data-id="{{ __('Approved') }}">{{ __('Approved') }}</button>
                              </div>
                            </div>
                          </div>
                        <form id="formYearGoal" action="{{ route('team-goals') }}" method="GET">
                            @php
                                $filterYear = request('filterYear');
                            @endphp
                            <div class="row align-items-end justify-content-between">
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                        <select name="filterYear" id="filterYear" onchange="yearGoal(this)" class="form-select">
                                            @if ($period)
                                                <option value="{{ $period }}" {{ $period == $filterYear ? 'selected' : '' }}>{{ $period }}</option>  
                                            @endif
                                            @foreach ($selectYear as $year)
                                                <option value="{{ $year->period }}" {{ $year->period == $filterYear ? 'selected' : '' }}>{{ $year->period }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                                                </div>
                                                <input type="text" name="customsearch" id="customsearch" class="form-control border-left-0" placeholder="search.." aria-label="search" aria-describedby="search">
                                                <div class="d-sm-none input-group-append">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div class="row px-2">
                            <div class="col-lg-12 p-0">
                                <div class="mt-3 p-2 bg-primary-subtle rounded shadow">
                                    <div class="row">
                                        <div class="col d-flex align-items-center">
                                            <h5 class="m-0 w-100">
                                                <a class="text-dark d-block" data-bs-toggle="collapse" href="#dataTasks" role="button" aria-expanded="false" aria-controls="dataTasks">
                                                    <i class="ri-arrow-down-s-line fs-18"></i>Goals {{ $filterYear ?? $period }} <span class="text-muted">({{ count($tasks) }})</span>
                                                </a>
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <form id="exportInitiatedForm" action="{{ route('team-goals.initiated') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="employee_id" id="employee_id" value="{{ Auth()->user()->employee_id }}">
                                                <input type="hidden" name="filterYear" id="filterYear" value="{{ $filterYear ?? $period }}">
                                                @if (count($tasks))
                                                    <button id="report-button" type="submit" class="btn btn-sm btn-success float-end"><i class="ri-download-cloud-2-line me-1"></i><span>{{ __('Download') }}</span></button>
                                                @endif
                                            </form>
                                        </div>
                                    </div>
                                    <div class="collapse show" id="dataTasks">
                                        <div class="card mb-0 mt-2">
                                            <div class="card-body py-1" id="task-container-1">
                                                <!-- task -->
                                                @forelse ($tasks as $index => $task)
                                                @php
                                                    $subordinates = $task->subordinates;
                                                    $firstSubordinate = $subordinates->isNotEmpty() ? $subordinates->first() : null;
                                                    $formStatus = $firstSubordinate ? $firstSubordinate->goal->form_status : null;
                                                    $goalId = $firstSubordinate ? $firstSubordinate->goal->id : null;
                                                    $appraisalCheck = $firstSubordinate ? $firstSubordinate->appraisalCheck : null;
                                                    $goalPeriod = $firstSubordinate ? $firstSubordinate->goal->period : null;
                                                    $goalData = $firstSubordinate ? $firstSubordinate->goal['form_data'] : null;
                                                    $createdAt = $firstSubordinate ? $firstSubordinate->formatted_created_at : null;
                                                    $updatedAt = $firstSubordinate ? $firstSubordinate->formatted_updated_at : null;
                                                    $updatedBy = $firstSubordinate ? $firstSubordinate->updatedBy : null;
                                                    $status = $firstSubordinate ? $firstSubordinate->status : null;
                                                    $approverId = $firstSubordinate ? $firstSubordinate->current_approval_id : null;
                                                    $sendbackTo = $firstSubordinate ? $firstSubordinate->sendback_to : null;
                                                    $employeeId = $firstSubordinate ? $firstSubordinate->employee_id : null;
                                                    $sendbackMessages = $firstSubordinate ? $firstSubordinate->sendback_messages : null;
                                                    $employeeName = $firstSubordinate ? $firstSubordinate->name : null;
                                                    $approvalLayer = $firstSubordinate ? $firstSubordinate->approvalLayer : null;
                                                    $accessMenu = json_decode($firstSubordinate->employee->access_menu, true);
                                                    $goals = $accessMenu['goals'] ?? null;
                                                    $doj = $accessMenu['doj'] ?? null;
                                                @endphp
                                                <div class="row mt-2 mb-2 task-card" data-status="{{ $formStatus == 'Draft' ? 'draft' : ($status == 'Pending' ? __('Pending') : ($subordinates->isNotEmpty() ? ($status == 'Sendback' ? __('Waiting For Revision') : __($status)) : 'no data')) }}">
                                                    <div class="col">
                                                        <div class="row">
                                                            <div class="col-md mb-sm-0 p-2">
                                                                <div id="tooltip-container">
                                                                    <img src="{{ asset('storage/img/profiles/user.png') }}" alt="image" class="avatar-xs rounded-circle me-1" data-bs-container="#tooltip-container" data-bs-toggle="tooltip" data-bs-placement="bottom"  data-bs-original-title="{{ __('Initiated By') }} {{ $task->employee->fullname.' ('.$task->employee->employee_id.')' }}">
                                                                    {{ $task->employee->fullname }} <span class="text-muted">{{ $task->employee->employee_id }}</span>
                                                                </div>
                                                            </div> <!-- end col -->
                                                            <div class="col-auto p-2 d-none d-md-block text-end">
                                                                <div class="mb-2">
                                                                    @if ($period == $goalPeriod && $formStatus != 'Draft' && $status != 'Sendback' && !$appraisalCheck && $goals)
                                                                        <a class="btn btn-sm btn-outline-warning me-1 fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('goals.edit', $goalId) }}" onclick="showLoader()">{{ __('Revise Goals') }}</a>
                                                                    @endif
                                                                    @if ($period == $goalPeriod && $task->employee->employee_id == Auth::user()->employee_id || !$subordinates->isNotEmpty() || $formStatus == 'Draft')
                                                                        @if ($formStatus == 'submitted' || $formStatus == 'Approved' || $appraisalCheck)
                                                                        <a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $goalId }}"><i class="ri-file-text-line"></i></a>
                                                                        @endif
                                                                        <a class="btn btn-sm me-1 btn-outline-warning fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('team-goals.edit', $goalId) }}" onclick="showLoader()">{{ __('Edit') }}</a>
                                                                    @else
                                                                        @if ($period == $goalPeriod && $approverId == Auth::user()->employee_id && $status === 'Pending' || $sendbackTo == Auth::user()->employee_id && $status === 'Sendback' || !$subordinates->isNotEmpty() || Auth::user()->employee_id == $firstSubordinate->initiated->employee_id && $status === 'Sendback' && $task->employee->employee_id != Auth::user()->employee_id)
                                                                            <a class="btn btn-sm me-1 btn-outline-warning fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('team-goals.edit', $goalId) }}" onclick="showLoader()">{{ $status === 'Sendback' ? __('Revise Goals') : __('Edit') }}</a>
                                                                            @if ($status != 'Sendback' && Auth::user()->employee_id != $firstSubordinate->initiated->employee_id && !$appraisalCheck)
                                                                                <a href="{{ route('team-goals.approval', $goalId) }}" class="btn btn-sm btn-outline-primary font-weight-medium" onclick="showLoader()">Act</a>
                                                                            @endif
                                                                        @else
                                                                            <a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $goalId }}"><i class="ri-file-text-line"></i></a>
                                                                        @endif
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-3 p-2">
                                                                <h5>{{ __('Initiated By') }}</h5>
                                                                <p class="mt-2 mb-0 text-muted">{{ $subordinates->isNotEmpty() ?$firstSubordinate->initiated->name .' ('.$firstSubordinate->initiated->employee_id.')' : '-' }}</p>
                                                            </div>
                                                            <div class="col-md-2 p-2">
                                                                <h5>{{ __('Initiated Date') }}</h5>
                                                                <p class="mt-2 mb-0 text-muted">{{ $createdAt ? $createdAt : '-' }}</p>
                                                            </div>
                                                            <div class="col-md-2 p-2">
                                                                <h5>Updated By</h5>
                                                                <p class="mt-2 mb-0 text-muted">{{ $updatedBy ? $updatedBy->name : '-' }}</p>
                                                            </div>
                                                            <div class="col-md-2 p-2">
                                                                <h5>{{ __('Last Updated On') }}</h5>
                                                                <p class="mt-2 mb-0 text-muted">{{ $updatedAt ? $updatedAt : '-' }}</p>
                                                            </div>
                                                            <div class="col-md-3 p-2">
                                                                <h5>Status</h5>
                                                                <a href="javascript:void(0)" data-bs-id="{{ $task->employee_id }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="{{ $formStatus == 'Draft' ? 'Draft' : ($approvalLayer ? 'Manager L'.$approvalLayer.' : '.$employeeName : $employeeName) }}" class="badge {{ $subordinates->isNotEmpty() ? ($formStatus == 'Draft' || $status == 'Sendback' ? 'bg-dark-subtle text-dark' : ($status === 'Approved' || $appraisalCheck ? 'bg-success' : 'bg-warning')) : 'bg-dark-subtle text-secondary'}} rounded-pill py-1 px-2">
                                                                    {{ $appraisalCheck ? __('Approved') : ($formStatus == 'Draft' ? 'Draft': ($status == 'Pending' ? __('Pending') : ($subordinates->isNotEmpty() ? ($status == 'Sendback' ? __('Waiting For Revision') : __($status)) : 'No Data'))) }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-auto d-md-none d-block">
                                                        <div class="align-items-center text-end py-2">
                                                            @if ($period == $goalPeriod && $formStatus != 'Draft' && $status != 'Sendback' && !$appraisalCheck && $goals)
                                                                <a class="btn btn-sm btn-outline-warning me-1 fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('goals.edit', $goalId) }}" onclick="showLoader()">{{ __('Revise Goals') }}</a>
                                                            @endif
                                                            @if ($task->employee->employee_id == Auth::user()->employee_id || !$subordinates->isNotEmpty() || $formStatus == 'Draft')
                                                                @if ($period == $goalPeriod && $formStatus == 'submitted' || $formStatus == 'Approved' || $appraisalCheck)
                                                                <a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $goalId }}"><i class="ri-file-text-line"></i></a>
                                                                @endif
                                                                <a class="btn btn-sm me-1 btn-outline-warning fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('team-goals.edit', $goalId) }}" onclick="showLoader()">{{ __('Edit') }}</a>
                                                            @else
                                                                @if ($period == $goalPeriod && $approverId == Auth::user()->employee_id && $status === 'Pending' || $sendbackTo == Auth::user()->employee_id && $status === 'Sendback' || !$subordinates->isNotEmpty())
                                                                    <a class="btn btn-sm me-1 btn-outline-warning fw-semibold {{ Auth::user()->employee_id == $firstSubordinate->initiated->employee_id ? '' : 'd-none' }}" href="{{ route('team-goals.edit', $goalId) }}" onclick="showLoader()">{{ $status === 'Sendback' ? __('Revise Goals') : __('Edit') }}</a>
                                                                    @if ($status != 'Sendback' && Auth::user()->employee_id != $firstSubordinate->initiated->employee_id && !$appraisalCheck)
                                                                        <a href="{{ route('team-goals.approval', $goalId) }}" class="btn btn-sm btn-outline-primary font-weight-medium" onclick="showLoader()">Act</a>
                                                                    @endif
                                                                @else
                                                                    <a href="javascript:void(0)" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $goalId }}"><i class="ri-file-text-line"></i></a>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if($index < count($tasks) - 1)
                                                        <hr class="mb-1 mt-2">
                                                    @endif
                                                </div>
                                                {{-- @if ($tasks) --}}
                                                    @include('pages.goals.detail')
                                                {{-- @endif --}}
                                                @empty
                                                <div class="p-3">
                                                    <div id="no-data-1" class="text-center">
                                                        <h5 class="text-muted">No Data</h5>
                                                    </div>
                                                </div>
                                                @endforelse
                                                <!-- end task -->
                                                
                                            </div> <!-- end card-body-->
                                        </div> <!-- end card -->
                                    </div> <!-- end .collapse-->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="not-initiated" role="tabpanel">
                        <form id="formYearGoal" action="{{ route('team-goals') }}" method="GET">
                            @php
                                $filterYear = request('filterYear');
                            @endphp
                            <div class="row align-items-end justify-content-between">
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                        <select name="filterYear" id="filterYear" onchange="yearGoal(this)" class="form-select">
                                            @if ($period)
                                                <option value="{{ $period }}" {{ $period == $filterYear ? 'selected' : '' }}>{{ $period }}</option>  
                                            @endif
                                            @foreach ($selectYear as $year)
                                                <option value="{{ $year->period }}" {{ $year->period == $filterYear ? 'selected' : '' }}>{{ $year->period }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-2">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                <span class="input-group-text bg-white"><i class="ri-search-line"></i></span>
                                                </div>
                                                <input type="text" name="customsearch" id="customsearch" class="form-control border-left-0" placeholder="search.." aria-label="search" aria-describedby="search">
                                                <div class="d-sm-none input-group-append">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div class="row px-2">
                            <div class="col-lg-12 p-0">
                                <div class="mt-3 p-2 bg-secondary-subtle rounded shadow">
                                    <div class="row">
                                        <div class="col d-flex align-items-center">
                                            <h5 class="m-0 w-100">
                                                <a class="text-dark d-block" data-bs-toggle="collapse" href="#noDataTasks" role="button" aria-expanded="false" aria-controls="noDataTasks">
                                                    <i class="ri-arrow-down-s-line fs-18"></i>Not Initiated <span class="text-muted">({{ count($notasks) }})</span>
                                                </a>
                                            </h5>
                                        </div>
                                        <div class="col-auto">
                                            <form id="exportNotInitiatedForm" action="{{ route('team-goals.notInitiated') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="employee_id" id="employee_id" value="{{ Auth()->user()->employee_id }}">
                                                <input type="hidden" name="filterYear" id="filterYear" value="{{ $filterYear ?? $period }}">
                                                @if (count($notasks))
                                                    <button id="report-button" type="submit" class="btn btn-sm btn-success float-end"><i class="ri-download-cloud-2-line me-1"></i><span>{{ __('Download Template') }}</span></button>
                                                @endif
                                            </form>
                                        </div>
                                    </div>
                                
                                    <div class="collapse show" id="noDataTasks">
                                        <div class="card mt-2 mb-0 d-flex border border-secondary">
                                            <div class="card-body py-1 align-items-center" id="task-container-2">
                                                <!-- task -->
                                                @forelse ($notasks as $index => $notask)
                                                @php
                                                    $subordinates = $notask->subordinates;
                                                    $firstSubordinate = $subordinates->isNotEmpty() ? $subordinates->first() : null;
                                                    $formStatus = $firstSubordinate ? $firstSubordinate->goal->form_status : null;
                                                    $goalId = $firstSubordinate ? $firstSubordinate->goal->id : null;
                                                    $goalData = $firstSubordinate ? $firstSubordinate->goal['form_data'] : null;
                                                    $createdAt = $firstSubordinate ? $firstSubordinate->created_at : null;
                                                    $updatedAt = $firstSubordinate ? $firstSubordinate->updated_at : null;
                                                    $updatedBy = $firstSubordinate ? $firstSubordinate->updatedBy : null;
                                                    $status = $firstSubordinate ? $firstSubordinate->status : null;
                                                    $approverId = $firstSubordinate ? $firstSubordinate->current_approval_id : null;
                                                    $sendbackTo = $firstSubordinate ? $firstSubordinate->sendback_to : null;
                                                    $employeeId = $firstSubordinate ? $firstSubordinate->employee_id : null;
                                                    $sendbackTo = $firstSubordinate ? $firstSubordinate->sendback_to : null;
                                                @endphp
                                                <div class="row mt-2 mb-2 task-card d-flex" data-status="no data">
                                                    <div class="col-sm-12 col-md-6 p-2 d-flex align-items-center">
                                                        <div id="tooltip-container">
                                                            <img src="{{ asset('storage/img/profiles/user.png') }}" alt="image" class="avatar-xs rounded-circle me-1" data-bs-container="#tooltip-container" data-bs-toggle="tooltip" data-bs-placement="bottom"  data-bs-original-title="{{ $notask->employee->fullname.' ('.$notask->employee->employee_id.')' }}">
                                                            {{ $notask->employee->fullname }} <span class="text-muted">{{ $notask->employee->employee_id }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-sm-12 col-md mb-2">
                                                        <label class="form-label">Date of Joining</label>
                                                        <span class="d-flex align-items-center text-muted">{{ $notask->formatted_doj }}</span>
                                                    </div>
                                                    <div class="col-sm-12 col-md mb-2">
                                                        <label class="form-label">Status</label>
                                                        <div><a href="javascript:void(0)" id="approval{{ $employeeId }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Employee has not set goals yet." data-bs-id="{{ $employeeId }}" class="badge bg-dark-subtle text-dark rounded-pill py-1 px-2">Not Initiated</a></div>

                                                    </div>
                                                    <div class="col-auto d-flex align-items-center ms-auto">
                                                        @php
                                                            // Decode the JSON string to an array
                                                            $accessMenu = json_decode($notask->employee->access_menu, true);
                                                            // Get the 'goals' and 'doj' values
                                                            $goals = $accessMenu['goals'] ?? null;
                                                            $doj = $accessMenu['doj'] ?? null;
                                                            $managerL1 = $notask?->employee?->managerL1;
                                                        @endphp
                                                        @if ((!$filterYear || $filterYear == $period) && $doj && $goals && $notask->isManager)
                                                            <button data-id="{{ encrypt($notask->employee->employee_id) }}" id="initiateBtn{{ $index }}" class="btn btn-outline-primary btn-sm">{{ __('Initiate') }}</button>
                                                        @else
                                                            <div><a href="javascript:void(0)" id="approval{{ $employeeId }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="Manager L1 : {{ $managerL1 ? $managerL1->fullname.' ('.$managerL1->employee_id.')' : '-' }}" data-bs-id="{{ $employeeId }}" class="badge bg-warning fw-bold rounded-pill py-1 px-2">view L1</a></div>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if($index < count($notasks) - 1)
                                                    <hr>
                                                @endif
                                                @empty
                                                <div class="p-3">
                                                    <div id="no-data-1" class="text-center">
                                                        <h5 class="text-muted">No Data</h5>
                                                    </div>
                                                </div>
                                                @endforelse
                                            </div> <!-- end card-body-->
                                        </div> <!-- end card -->
                                    </div> <!-- end .collapse-->
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div> 
                
    </div>
    <!-- Modal Pop-Up -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Goals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form id="importGoal" action="{{ route('importgoalsmanager') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col">
                                <div class="alert alert-info">
                                    <strong>Notes:</strong>
                                    <ul class="mb-0">
                                        <li>{{ __('Note Import Goal Manager') }}<strong><br> > Tab "{{ __('Not Initiated') }}" -> {{ __('Download') }}</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
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