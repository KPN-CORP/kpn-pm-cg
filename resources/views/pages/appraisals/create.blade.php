@extends('layouts_.vertical', ['page_title' => 'Appraisal'])

@section('css')
<style>
.file-card .filename{
    max-width:120px;
    display:inline-block;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

</style>
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Begin Page Content -->
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="row justify-content-center">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-center">
                        <div class="col col-md-10 text-center">
                            <div class="stepper mt-3 d-flex justify-content-between justify-content-md-around">
                                @foreach ($filteredFormData as $index => $tabs)
                                <div class="step" data-step="{{ $step }}"></div>
                                    <div class="step d-flex flex-column align-items-center" data-step="{{ $index + 1 }}">
                                        <div class="circle {{ $step == $index + 1 ? 'active' : ($step > $index + 1 ? 'completed' : '') }}">
                                            <i class="{{ $tabs['icon'] }}"></i>
                                        </div>
                                        <div class="label">{{ $tabs['name'] }}</div>
                                    </div>
                                    @if ($index < count($filteredFormData) - 1)
                                        <div class="connector {{ $step > $index + 1 ? 'completed' : '' }} col mx-md-4 d-none d-md-block"></div>
                                    @endif
                                @endforeach
                            </div>
                                              
                        </div>
                    </div>
                    {{-- @if ($row->request->employee->group_company == 'Cement') --}}
                    <div class="card-body m-0 py-2">
                        @php
                            $achievement = [
                                ["month" => "January", "value" => "-"],
                                ["month" => "February", "value" => "-"],
                                ["month" => "March", "value" => "-"],
                                ["month" => "April", "value" => "-"],
                                ["month" => "May", "value" => "-"],
                                ["month" => "June", "value" => "-"],
                                ["month" => "July", "value" => "-"],
                                ["month" => "August", "value" => "-"],
                                ["month" => "September", "value" => "-"],
                                ["month" => "October", "value" => "-"],
                                ["month" => "November", "value" => "-"],
                                ["month" => "December", "value" => "-"],
                            ];
                            if ($achievements && $achievements->isNotEmpty()) {
                                $achievement = json_decode($achievements->first()->data, true);
                            }
                        @endphp

                        @if ($viewAchievement)
                        <div class="rounded mb-2 p-3 bg-white text-primary align-items-center d-none">
                            <div class="row mb-2">
                                <span class="fs-16 mx-1">
                                    Achievements
                                </span>      
                            </div>   
                            <div class="row">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0 text-center align-middle">
                                        <thead class="bg-primary-subtle">
                                            <tr>
                                                @forelse ($achievement as $item)
                                                    <th>{{ substr($item['month'], 0, 3) }}</th>
                                                @empty
                                                    <th colspan="{{ count($achievement) }}">No Data
                                                @endforelse
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            <tr>
                                                @foreach ($achievement as $item)
                                                    <td>{{ $item['value'] }}</td>
                                                @endforeach
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        @endif                    
                    </div>
                    {{-- @endif --}}
                    <div class="card-body">
                        <form id="formAppraisalUser" action="{{ route('appraisal.submit') }}" enctype="multipart/form-data" method="POST">
                        @csrf
                        <input type="hidden" id="user_id" value="{{ Auth::user()->employee_id }}">
                        <input type="hidden" id="employee_id" name="employee_id" value="{{ $goal->employee_id }}">
                        <input type="hidden" name="form_group_id" value="{{ $formGroupData['data']['id'] }}">
                        <input type="hidden" class="form-control" name="approver_id" value="{{ $approval->approver_id }}">
                        <input type="hidden" name="formGroupName" value="{{ $formGroupData['data']['name'] }}">
                            @foreach ($filteredFormData as $index => $row)
                                <div class="form-step {{ $step == $index + 1 ? 'active' : '' }}" data-step="{{ $index + 1 }}">
                                    <div class="card-title h4 mb-4">{{ $row['title'] }}</div>
                                    @include($row['blade'], [
                                    'id' => 'input_' . strtolower(str_replace(' ', '_', $row['title'])),
                                    'formIndex' => $index,
                                    'name' => $row['name'],
                                    'data' => $row['data'],
                                    'ratings' => $ratings ?? []
                                    ])
                                </div>
                            @endforeach
                            <div class="row">
                                <div class="col-md">
                                <div class="mb-3">
                                    <label for="attachment" class="form-label">Supporting documents for achievement result</label>
                                    <input class="form-control" id="attachment_pm" name="attachment[]" type="file" multiple>
                                    <small id="totalSizeInfo" class="form-text text-muted mt-1">Total: 0 B / 10 MB</small>
                                    <div id="fileCards" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                                </div>
                                <div class="col-md">
                                <div class="mb-3 text-end">
                                    <a data-id="submit_draft" data-step="create" type="button" class="btn btn-sm btn-outline-secondary submit-draft">
                                    <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>{{ __('Save as Draft') }}
                                    </a>
                                </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-center py-2">
                                <a type="button" class="btn btn-light border me-3 prev-btn" style="display:none;"><i class="ri-arrow-left-line"></i>{{ __('Prev') }}</a>
                                <a type="button" class="btn btn-primary next-btn">{{ __('Next') }} <i class="ri-arrow-right-line"></i></a>
                                <a data-id="submit_form" class="btn btn-primary submit-user px-md-4" style="display:none;">
                                <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>{{ __('Submit') }}
                                </a>
                            </div>

                            <input type="hidden" name="submit_type" id="submitType" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        const errorMessages = '{{ __('Empty Messages') }}';
        // create: langsung init
        initFileUploader({
            form:  '#formAppraisalUser',
            input: '#attachment',
            cards: '#fileCards',
            total: '#totalSizeInfo',
            // keepName tak dipakai di create, biarkan default
        });
    </script>
@endpush
