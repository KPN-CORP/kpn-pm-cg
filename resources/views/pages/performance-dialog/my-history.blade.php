@extends('layouts_.vertical', ['page_title' => 'My History'])

@section('css')
    <style>
        .performance-dialog-card {
            overflow: hidden;
            transition:
                opacity 0.25s ease-in-out,
                transform 0.25s ease-in-out,
                max-height 0.75s cubic-bezier(0.4, 0, 0.2, 1),
                margin 0.25s,
                padding 0.25s;
            will-change: opacity, transform, max-height;
            opacity: 1;
            transform: translateY(0);
            max-height: 5000px;
        }

        .performance-dialog-card.is-hiding {
            opacity: 0;
            transform: translateY(16px);
            max-height: 0 !important;
            margin: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            pointer-events: none;
        }

        .performance-dialog-card.is-showing {
            opacity: 1;
            transform: translateY(0);
            max-height: 5000px;
            padding-top: 1rem;
            padding-bottom: 1rem;
            pointer-events: auto;
        }

        .performance-dialog-card.is-gone {
            display: none !important;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="mandatory-field">
            <div id="alertField" class="alert alert-danger alert-dismissible {{ Session::has('error') ? '':'fade' }}" role="alert" {{ Session::has('error') ? '':'hidden' }}>
                <strong>{{ Session::get('error')['message'] ?? null }}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <form id="form-filter-performance-dialog" action="{{ route('performance-dialog') }}" method="GET">
            <div class="row align-items-end">
                <div class="col-auto">
                    <div class="mb-3">
                        <label class="form-label" for="period">{{ __('Year') }}</label>
                        <select name="period" id="period" onchange="filterPerformanceDialogYears(this.value)" class="form-select border-secondary" style="width: 180px">
                            <option value="">{{ __('select all') }}</option>
                            @foreach ($performance_dialog_years as $year)
                                <option value="{{ $year }}" {{ $year == $period ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col">
                    <div class="mb-3 text-end">
                        <a href="{{ route('performance-dialog.form') }}" onclick="showLoader()" class="btn btn-primary shadow">{{ __('Create Performance Dialog') }}</a>
                    </div>
                </div>
            </div>
        </form>
        @forelse ($performance_dialogs as $index => $row)
            <div class="row">
                <div class="col-md-12">
                <div class="card shadow p-0 performance-dialog-card" data-year="{{ $row->period }}">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between pb-0">
                        <h4 class="m-0 font-weight-bold text-primary">{{ __('Performance Dialog') }} {{ $row->period }}</h4>
                        @if ($row->status == 'Draft')
                            <a class="btn btn-outline-warning fw-semibold" href="{{ route('performance-dialog.form-edit', $row->id) }}" onclick="showLoader()">
                                {{ __('Edit') }}
                            </a>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg col-sm-12">
                                <div id="alertDraft" class="alert alert-danger alert-dismissible {{ $row->status == 'Draft' ? '':'fade' }}" role="alert" {{ $row->status == 'Draft' ? '':'hidden' }}>
                                    <div class="row text-primary fs-5 align-items-center">
                                        <div class="col-auto my-auto">
                                            <i class="ri-error-warning-line h3 fw-light"></i>
                                        </div>
                                        <div class="col p-0">
                                            <strong>{{ __('Draft Performance Dialog Alert Message Open') }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row px-2">
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Initiated By') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->employeeCreatedBy ? $row->employeeCreatedBy->fullname.' ('.$row->employeeCreatedBy->employee_id.')' : '-'}}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Initiated Date') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->created_at }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Last Updated On') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->updated_at }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Adjusted By') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->employeeUpdatedBy ? $row->employeeUpdatedBy->fullname.' ('.$row->employeeUpdatedBy->employee_id.')' : '-' }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>Status</h5>
                                <div>
                                    <a href="javascript:void(0)" data-bs-id="{{ $row->employee_id }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="{{ $row->status }}"
                                        class="badge {{ $row->status == 'Draft' ? 'bg-secondary' : ($row->status == 'Pending' ? 'bg-warning' : ($row->status == 'Approved' ? 'bg-success' : 'text-bg-light'))}} rounded-pill py-1 px-2">
                                        {{ $row->status }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col text-end">
                                <a data-bs-toggle="collapse" href="#collapse{{ $index }}" aria-expanded="true" aria-controls="collapse{{ $index }}">
                                    Details <i class="ri-arrow-right-s-line"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="collapse" id="collapse{{ $index }}" style="">
                        <div class="card-body p-0">
                            <table class="table table-striped table-bordered m-0">
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="bg-light">
                                            <strong>Information</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td scope="row">
                                            <div class="row p-2">
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>Start Date</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ $row->start_date ?? "-" }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>End Date</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ $row->end_date ?? "-" }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>Due Date</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ $row->due_date ?? "-" }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row p-2">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <h5>Summary</h5>
                                                        <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $row->summary ?? '-' }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <h5>Development Plan</h5>
                                                        <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $row->development_plan ?? '-' }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <h5>Additional Notes</h5>
                                                        <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $row->additional_notes ?? '-' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col text-end">
                                    <a data-bs-toggle="collapse" href="#collapse{{ $index }}" aria-expanded="true" aria-controls="collapse{{ $index }}">Close <i class="ri-arrow-up-s-line"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        @empty
            <div class="row">
                <div class="col-md-12">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        {{ __('No Performance Dialog Found. Please Create Your Performance Dialog ') }}<i class="ri-arrow-right-up-line"></i>
                    </div>
                </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection

@push('scripts')
    @if(Session::has('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                icon: "error",
                title: "{{ Session::get('error')['title'] }}",
                text: "{{ Session::get('error')['message'] }}",
                confirmButtonText: "OK",
                });
            });
        </script>
    @endif

    <script>
        function hideCard(card) {
            if (card.classList.contains('is-gone')) return;

            card.classList.remove('is-showing');
            card.classList.add('is-hiding');

            const onEnd = (e) => {
                if (e.propertyName !== 'max-height') return;

                card.classList.add('is-gone');
                card.removeEventListener('transitionend', onEnd);
            };

            card.addEventListener('transitionend', onEnd);
        }

        function showCard(card) {
            card.classList.remove('is-gone');
            card.offsetHeight;
            card.classList.remove('is-hiding');
            card.classList.add('is-showing');
        }

        function filterPerformanceDialogYears(year) {
            const cards = document.querySelectorAll('.performance-dialog-card');
            const selected = (year || '').toString().trim();

            cards.forEach(card => {
                const cardYear = (card.dataset.year || '').toString().trim();
                const shouldShow = !selected || cardYear === selected;

                shouldShow ? showCard(card) : hideCard(card);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sel = document.getElementById('period');

            if (sel) filterPerformanceDialogYears(sel.value);
        });

        window.filterPerformanceDialogYears = filterPerformanceDialogYears;
    </script>
@endpush
