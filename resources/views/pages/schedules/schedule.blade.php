@extends('layouts_.vertical', ['page_title' => 'Schedule'])

@section('css')
{{-- <meta name="csrf-token" content="{{ csrf_token() }}"> --}}
@endsection

@section('content')

    <!-- Begin Page Content -->
    <div class="container-fluid">
        @if (session('success'))
            {{-- <div class="alert alert-success">
                {{ session('success') }}
            </div> --}}
            <div class="alert alert-success alert-dismissible border-0 fade show" role="alert">
                <button type="button" class="btn-close btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <strong>Success - </strong> {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <!-- Page Heading -->
        <div class="pt-1 row">
        </div>

        <div class="row justify-content-between">
            <div class="col-md-4 order-2 order-md-0">
                <div class="mb-3">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-dark-subtle">
                                <i class="ri-search-line"></i>
                            </span>
                        </div>
                        <input type="text" name="customsearch" id="customsearch"
                               class="form-control border-dark-subtle border-left-0"
                               placeholder="search.." aria-label="search">
                    </div>
                </div>
            </div>
            <div class="col-md-2 order-1 order-md-0 text-md-end">
                <div class="mb-3">
                    <a href="{{ route('schedules.form') }}"
                       class="btn btn-primary shadow">Create Schedule</a>
                </div>
            </div>
        </div>
        <!-- Content Row -->
        <div class="row">
          <div class="col-md-12">
            <div class="card shadow mb-4">
              <div class="card-body">
                <ul class="nav nav-tabs nav-justified nav-bordered mb-3" id="tablist" role="tablist">
                    <li class="nav-item">
                        <a href="#active" data-bs-toggle="tab" aria-expanded="true" class="nav-link active" aria-selected="true" role="tab">
                            Active Schedule
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#archived" data-bs-toggle="tab" aria-expanded="false" class="nav-link" aria-selected="false" tabindex="-1" role="tab">
                            Archived
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane show active" id="active" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover dt-responsive nowrap activate-select" id="scheduleTable" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr class="text-center">
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Reminder</th>
                                        <th>Days</th>
                                        <th>Created By</th>
                                        <th class="sorting_1">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach($schedules as $schedule)
                                    <tr>
                                        <td>{{ $loop->index + 1 }}</td>
                                        <td style="width: 20%; word-wrap: break-word; white-space: normal;">{{ $schedule->schedule_name }}</td>
                                        <td>
                                            @if($schedule->event_type == 'goals'){{ 'Goal Setting' }}
                                            @elseif($schedule->event_type == 'schedulepa'){{ 'PA '.$schedule->schedule_periode }}
                                            @elseif($schedule->event_type == 'schedulepr'){{ 'PR '.$schedule->schedule_periode }}
                                            @elseif($schedule->event_type == 'masterschedulepa'){{ 'Master PA '.$schedule->schedule_periode }}
                                            @elseif($schedule->event_type == 'masterschedulegoals'){{ 'Master Goal Settings '.$schedule->schedule_periode }}
                                            @elseif($schedule->event_type == 'masterschedulepr'){{ 'Master PR '.$schedule->schedule_periode }}
                                            @endif
                                        </td>
                                        <td>{{ $schedule->start_date }}</td>
                                        <td>{{ $schedule->end_date }}</td>
                                        <td>@if($schedule->checkbox_reminder == '1') Yes @else No @endif</td>
                                        <td>@if($schedule->checkbox_reminder == 1)
                                                @if($schedule->repeat_days <> '')
                                                    {{ $schedule->repeat_days }}
                                                @else
                                                    {{ $schedule->before_end_date . ' Days Before End Date' }}
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ isset($schedule->createdBy->name) ? $schedule->createdBy->name : '-' }}</td>
                                        <td class="text-center sorting_1">
                                            @if($schedule->created_by == $userId && !$schedule->deleted_at)
                                                @if($schedule->event_type == 'schedulepa')
                                                    @if($schedulemasterpa)
                                                        <a href="{{ route('edit-schedule', \Crypt::encrypt($schedule->id)) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="ri-edit-box-line"></i></a>
                                                        {{-- <a class="btn btn-sm btn-danger" title="Delete" onclick="handleDelete(this)" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a> --}}
                                                        <a class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete({{ $schedule->id }})" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a>
                                                    @endif
                                                @elseif($schedule->event_type == 'goals')
                                                    @if($schedulemastergoals)
                                                        <a href="{{ route('edit-schedule', \Crypt::encrypt($schedule->id)) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="ri-edit-box-line"></i></a>
                                                        <a class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete({{ $schedule->id }})" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a>
                                                    @endif
                                                @elseif($schedule->event_type == 'schedulepr')
                                                    @if($schedulemasterpr)
                                                        <a href="{{ route('edit-schedule', \Crypt::encrypt($schedule->id)) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="ri-edit-box-line"></i></a>
                                                        {{-- <a class="btn btn-sm btn-danger" title="Delete" onclick="handleDelete(this)" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a> --}}
                                                        <a class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete({{ $schedule->id }})" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a>
                                                    @endif
                                                @else
                                                    <a href="{{ route('edit-schedule', \Crypt::encrypt($schedule->id)) }}" class="btn btn-sm btn-outline-warning" title="Edit"><i class="ri-edit-box-line"></i></a>
                                                    <a class="btn btn-sm btn-danger" title="Delete" onclick="confirmDelete({{ $schedule->id }})" data-id="{{ $schedule->id }}"><i class="ri-delete-bin-line"></i></a>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane" id="archived" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover dt-responsive nowrap" id="inactiveScheduleTable" width="100%" cellspacing="0">
                                <thead class="thead-light">
                                    <tr class="text-center">
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Reminder</th>
                                        <th>Days</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach($inactiveSchedules as $index => $inactiveSchedule)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td style="width: 20%; word-wrap: break-word; white-space: normal;">{{ $inactiveSchedule->schedule_name }}</td>
                                        <td>
                                            @if($inactiveSchedule->event_type == 'goals'){{ 'Goal Setting' }}
                                            @elseif($inactiveSchedule->event_type == 'schedulepa'){{ 'PA '.$inactiveSchedule->schedule_periode }}
                                            @elseif($inactiveSchedule->event_type == 'schedulepr'){{ 'PR '.$inactiveSchedule->schedule_periode }}
                                            @elseif($inactiveSchedule->event_type == 'masterschedulepa'){{ 'Master PA '.$inactiveSchedule->schedule_periode }}
                                            @elseif($inactiveSchedule->event_type == 'masterschedulegoals'){{ 'Master Goal Settings '.$inactiveSchedule->schedule_periode }}
                                            @elseif($inactiveSchedule->event_type == 'masterschedulepr'){{ 'Master PR '.$inactiveSchedule->schedule_periode }}
                                            @endif
                                        </td>
                                        <td>{{ $inactiveSchedule->start_date }}</td>
                                        <td>{{ $inactiveSchedule->end_date }}</td>
                                        <td>@if($inactiveSchedule->checkbox_reminder == '1') Yes @else No @endif</td>
                                        <td>@if($inactiveSchedule->checkbox_reminder == 1)
                                                @if($inactiveSchedule->repeat_days <> '')
                                                    {{ $inactiveSchedule->repeat_days }}
                                                @else
                                                    {{ $inactiveSchedule->before_end_date . ' Days Before End Date' }}
                                                @endif
                                            @endif
                                        </td>
                                        <td>{{ isset($inactiveSchedule->createdBy->name) ? $inactiveSchedule->createdBy->name : '-' }}</td>
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
    <form id="delete-form" action="" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

@endsection
@push('scripts')
<script>
    function confirmDelete(scheduleId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This schedule will be archived!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, archive it!',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Atur action pada form tersembunyi dan submit
                var form = document.getElementById('delete-form');
                form.action = '/schedule/' + scheduleId + '/delete';
                form.submit();
            }
        });
    }
</script>
@endpush
