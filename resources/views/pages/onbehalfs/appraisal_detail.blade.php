<div class="modal fade" id="modalDetail{{ $row->request->appraisal->id }}" tabindex="-1" aria-labelledby="modalDetailLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Appraisal Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
            <div class="modal-body bg-primary-subtle">
                <div class="container-fluid py-3">
                    <div class="d-sm-flex align-items-center mb-4">
                        <h4 class="me-1">{{ $row->request->employee->fullname }}</h4>
                        <span class="h4 text-muted">{{ $row->request->employee->employee_id }}</span>
                    </div>
                    @php
                        $year = date('Y', strtotime($row->request->created_at));
                        $formData = json_decode($row->request->appraisal->form_data, true);
                    @endphp
                    <div class="row">
                        <div class="col">
                            <div class="card">
                                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                                    <h4 class="m-0 font-weight-bold text-primary">Appraisal {{ $row->request->appraisal->period }}</h4>

                                    @if ($row->request->status == 'Pending' && count($row->request->approval) == 0 || $row->request->sendback_to == $row->request->employee_id)
                                        <a class="btn btn-outline-warning fw-semibold rounded-pill" href="{{ route('edit.appraisal', $row->request->appraisal->id) }}">{{ __('Edit') }}</a>
                                    @endif
                                </div>

                                <div class="card-body mb-2 bg-light-subtle">
                                    <div class="row px-2">
                                        <div class="col-lg col-sm-12 p-2">
                                            <h5>{{ __('Initiated By') }}</h5>
                                            <p class="mt-2 mb-0 text-muted">{{ $row->request->initiated->name }} ({{ $row->request->initiated->employee_id }})</p>
                                        </div>
                                        <div class="col-lg col-sm-12 p-2">
                                            <h5>{{ __('Initiated Date') }}</h5>
                                            <p class="mt-2 mb-0 text-muted">{{ $row->request->formatted_created_at }}</p>
                                        </div>
                                        <div class="col-lg col-sm-12 p-2">
                                            <h5>{{ __('Last Updated On') }}</h5>
                                            <p class="mt-2 mb-0 text-muted">{{ $row->request->formatted_updated_at }}</p>
                                        </div>
                                        <div class="col-lg col-sm-12 p-2">
                                            <h5>Final Rating</h5>
                                            <p class="mt-2 mb-0 text-muted">-</p>
                                        </div>
                                        <div class="col-lg col-sm-12 p-2">
                                            <h5>Status</h5>
                                            <div>
                                                @php
                                                    $popoverContent = match (true) {
                                                        in_array($row->request->appraisal->first()?->goal?->form_status, ['Draft']) => 'Draft',
                                                        default => $row->approvalLayer ? "Manager L{$row->approvalLayer} : {$row->name}" : $row->name,
                                                    };

                                                    $badgeClass = match (true) {
                                                        in_array($row->request->appraisal->first()?->goal?->form_status, ['Draft']),
                                                        $row->request->appraisal->form_status === 'Draft',
                                                        $row->request->sendback_to == $row->request->employee_id => 'bg-secondary',
                                                        $row->request->status === 'Approved' => 'bg-success',
                                                        default => 'bg-warning',
                                                    };

                                                    $statusText = match ($row->request->status) {
                                                        'Pending' => __('Pending'),
                                                        default => $row->request->sendback_to == $row->request->employee_id ? __('Waiting For Revision') : $row->request->status,
                                                    };
                                                @endphp

                                                <a href="javascript:void(0)"
                                                    data-bs-id="{{ $row->request->employee_id }}"
                                                    data-bs-toggle="popover"
                                                    data-bs-trigger="hover focus"
                                                    data-bs-content="{{ $popoverContent }}"
                                                    class="badge {{ $badgeClass }} rounded-pill py-1 px-2">
                                                    {{ $statusText }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if($row->request->appraisal?->file)
                                    <div class="card-body m-0 py-2">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label for="attachment" class="form-label">Supporting documents :</label>
                                                <div class="d-flex align-items-center gap-1">
                                                    <a href="{{ asset($row->request->appraisal->file) }}" target="_blank" class="badge rounded-pill text-bg-warning px-2 py-1" style="font-size: 0.75rem">
                                                        attachment <i class="ri-file-text-line"></i>
                                                    </a>
                                                    @if ($row->request->status != 'Approved')
                                                        <a href="javascript:void(0);" onclick="deleteFile(this, '{{ $row->request->appraisal->id }}')" class="badge rounded-pill text-bg-light p-1">
                                                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                                            <i class="ri-close-line" style="font-size: 1rem"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="card-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="mb-2 text-primary fw-semibold fs-16">
                                                Total Score : {{ round($row->formData['totalScore'], 2) }}
                                            </div>
                                        </div>
                                    </div>

                                    @forelse ($row->formData['formData'] as $indexItem => $item)
                                        <div class="row">
                                            <button class="btn rounded mb-2 py-2 bg-white border-opacity-50 border-primary bg-opacity-10 text-primary align-items-center d-flex justify-content-between"
                                                    {{-- type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#collapse-{{ $indexItem }}"
                                                    aria-expanded="false"
                                                    aria-controls="collapse-{{ $indexItem }}" --}}
                                                    >
                                                <span class="fs-16 ms-1">
                                                    {{ $item['formName'] }}
                                                    | Score : {{
                                                        match ($item['formName']) {
                                                            'KPI' => $row->formData['totalKpiScore'],
                                                            'Culture' => $row->formData['totalCultureScore'],
                                                            'Leadership' => $row->formData['totalLeadershipScore'],
                                                            'Sigap' => $row->formData['totalSigapScore'],
                                                            default => '-'
                                                        }
                                                    }}
                                                </span>
                                                {{-- <span>
                                                    <p class="d-none d-md-inline me-1">Details</p>
                                                    <i class="ri-arrow-down-s-line"></i>
                                                </span> --}}
                                            </button>

                                            <div class="collapse" id="collapse-{{ $indexItem }}">
                                                <div class="card card-body mb-3">
                                                    @includeWhen($item['formName'] == 'Leadership', 'partials.leadership-detail', ['formData' => $formData])
                                                    @includeWhen($item['formName'] == 'Culture', 'partials.culture-detail', ['formData' => $formData])
                                                    @includeWhen($item['formName'] == 'KPI', 'partials.kpi-detail', ['form' => $item])
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p>No Data</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>