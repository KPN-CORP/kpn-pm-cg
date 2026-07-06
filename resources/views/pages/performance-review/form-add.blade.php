@extends('layouts_.vertical', ['page_title' => 'Goals'])

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
        <div class="row">
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
        <form id="performanceReviewForm" action="{{ route('performance-review.create') }}" class="needs-validation" method="POST">
        @csrf
        </form>
    </div>
@endsection
@push('scripts')
    <script>
        const uom = '{{ __('Uom') }}';
        const type = '{{ __('Type') }}';
        const weightage = '{{ __('Weightage') }}';
        const errorMessages = '{{ __('Error Messages') }}';
        const errorAlertMessages = '{{ __('Error Alert Messages') }}';
        const confirmTitle = '{{ __('Confirm Title') }}';
        const confirmMessages = '{{ __('Confirm Messages') }}';
        const errorConfirmMessages = '{{ __('Error Confirm Messages') }}';
        const errorConfirmWeightageMessages1 = '{{ __('Error Confirm Weightage Messages_1') }}';
        const errorConfirmWeightageMessages2 = '{{ __('Error Confirm Weightage Messages_2') }}';
        const textMandatory = '{{ __('This field is mandatory') }}';
    </script>
@endpush
