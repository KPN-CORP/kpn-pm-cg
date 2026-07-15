@extends('layouts_.vertical', ['page_title' => 'Performance Review'])

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

    <div class="detail-employee">
        <div class="row mb-2">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-2 pb-0">
                        <div class="row">
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee Name:</span> {{ $employee_name }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee ID:</span> {{ $employee_id }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Job Level:</span> {{ $employee_job_level }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Business Unit:</span> {{ $employee_group_company }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Division:</span> {{ $employee_unit }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Designation:</span> {{ $employee_designation_name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mandatory-field"></div>
        <div id="form-alert" class="alert alert-danger d-none"></div>
        <form id="performance-review-form" action="{{ route('performance-review.create-or-update') }}" class="needs-validation" method="POST">
            @csrf
            <input type="hidden" class="form-control" name="period" value="{{ $period }}">
            <input type="hidden" class="form-control" name="employee_id" value="{{ $employee_id }}">
            <input type="hidden" class="form-control" name="contributor_id" value="{{ $contributor_id }}">
            <div class="row mb-2">
                <div class="col-md-6">
                    <label for="performance_review_type" class="form-label">Objectives</label>
                    <select class="form-select form-select-sm select2" id="performance_review_type" name="performance_review_types[]" data-placeholder="Select Objectives" multiple required>
                        <option></option>
                        @foreach ($master_performance_review_types as $master_performance_review_type)
                            @php
                                $isSelected = false;

                                if ($performance_review_types) {
                                    foreach ($performance_review_types as $performance_review_type) {
                                        if ($master_performance_review_type->name == $performance_review_type["name"]) {
                                            $isSelected = true;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            <option value="{{ $master_performance_review_type->id }}" {{ $isSelected ? "selected" : "" }}>{{ $master_performance_review_type->name }}</option>
                        @endforeach
                        @if (!empty($performance_review_others_type_name))
                            <option value="0" selected>Others</option>
                        @else
                            <option value="0">Others</option>
                        @endif
                    </select>
                    <br>
                    <div class="row">
                        <div class="">
                            <input type="text" name="others_performance_review_type" id="others_performance_review_type"
                            class="form-control form-control-sm" placeholder="ex: Penilaian Kerja"
                            value="" style="{{ empty($performance_review_others_type_name) ? "display: none;" : "" }}">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-1">
                    <label for="performance_review_due_date" class="form-label">Due Date</label>
                    <input type="date" class="form-control form-control-sm" id="performance_review_due_date" name="performance_review_due_date" value="{{ $formatted_performance_review_due_date }}" required>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6 mt-2">
                    <label for="" class="form-label">Summary</label>
                    <textarea class="form-control form-control-sm" id="performance_review_summary" name="performance_review_summary" rows="4"
                        placeholder="Please add more detail of summary ...">{{ $performance_review_summary }}</textarea>
                </div>
                <div class="col-md-6 mt-2">
                    <label for="" class="form-label">Development Plan</label>
                    <textarea class="form-control form-control-sm" id="performance_review_development_plan" name="performance_review_development_plan" rows="4"
                        placeholder="Please add more detail of development plan ...">{{ $performance_review_development_plan }}</textarea>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-12 mt-2">
                    <label for="" class="form-label">Additional Notes</label>
                    <textarea class="form-control form-control-sm" id="performance_review_additional_notes" name="performance_review_additional_notes" rows="4"
                        placeholder="Please add more detail of additional notes ...">{{ $performance_review_additional_notes }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-4 mb-4">
                <button type="submit" class="btn btn-outline-primary rounded-pill me-2 draft-button"
                    name="action_draft" value="Draft" id="performance-review-save-draft">Save as
                    Draft</button>
                <button type="submit" class="btn btn-primary rounded-pill submit-button"
                    name="action_submit" value="Submitted" id="performance-review-submit">Submit</button>
            </div>
        </form>
    </div>
@endsection
@push('scripts')
    <script>
    </script>
@endpush
