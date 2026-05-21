@extends('layouts_.vertical', ['page_title' => 'Appraisal'])

@section('css')
<style>
.dataTables_scrollHeadInner {
    width: 100% !important;
}
.table-responsive, .dataTables_scroll {
    width: 100%;
}
</style>
@endsection

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif
        <div class="mandatory-field">
            <div id="alertField" class="alert alert-danger alert-dismissible {{ Session::has('error') ? '':'fade' }}" role="alert" {{ Session::has('error') ? '':'hidden' }}>
                <strong>{{ Session::get('error') }}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3" id="myTab" role="tablist">
                            <li class="nav-item">
                              <button class="btn btn-outline-primary position-relative active me-2" id="team-tab" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab" aria-controls="team" aria-selected="true">
                                {{ __('My Team') }}
                                <span class="position-absolute top-0 start-100 translate-middle badge bg-danger {{ $notifDataTeams ? $notifDataTeams : 'd-none' }}">
                                  {{ $notifDataTeams }}
                                </span>
                              </button>
                            </li>
                            <li class="nav-item">
                              <button class="btn btn-outline-secondary position-relative" id="360-review-tab" data-bs-toggle="tab" data-bs-target="#360-review" type="button" role="tab" aria-controls="360-review" aria-selected="false">
                                {{ __('Appraisal 360') }}
                                <span class="position-absolute top-0 start-100 translate-middle badge bg-danger {{ $notifData360 ? $notifData360 : 'd-none' }}">
                                  {{ $notifData360 }}
                                </span>
                              </button>
                            </li>
                          </ul>
                          <div class="tab-content" id="myTabContent">
                            <form id="formAppraisalTaskGoal" action="{{ route('appraisals-task') }}" method="GET">
                                @php
                                    $filterYear = request('filterYear');
                                @endphp
                                <div class="row align-items-end justify-content-between">
                                    <div class="col-md-3">
                                        <div class="mb-2">
                                            <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                            <select name="filterYear" id="filterYear" onchange="yearAppraisalTask(this)" class="form-select">
                                                @if ($period)
                                                    <option value="{{ $period }}" {{ $period == $filterYear ? 'selected' : '' }}>{{ $period }}</option>
                                                @endif
                                                @foreach ($selectYear as $year)
                                                    <option value="{{ $year->period }}" {{ $year->period == $filterYear ? 'selected' : '' }}>{{ $year->period }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <div class="tab-pane fade show active" id="team" role="tabpanel" aria-labelledby="team-tab">
                                <div class="table-responsive">
                                    <table id="tableAppraisalTeam" class="table table-hover table-sm activate-select dataTables_scrollHeadInner">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Designation</th>
                                                <th>Office</th>
                                                <th>Status</th>
                                                <th>{{ __('Initiated Date') }}</th>
                                                <th class="sorting_1">Action</th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="360-review" role="tabpanel" aria-labelledby="360-review-tab">
                                    <table id="tableAppraisal360" class="table table-hover activate-select dataTables_scrollHeadInner" @style('width : 100%')>
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Designation</th>
                                                <th>Office</th>
                                                <th>Status</th>
                                                <th>{{ __('Initiated Date') }}</th>
                                                <th class="sorting_1">Action</th>
                                            </tr>
                                        </thead>
                                    </table>
                            </div>
                          </div>
                    </div> <!-- end card-body -->
                </div> <!-- end card-->
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
                    title: "Cannot initiate appraisal!",
                    text: '{{ Session::get('error') }}',
                    confirmButtonText: "OK",
                });
            });
        </script>
        @endif
    @endpush
