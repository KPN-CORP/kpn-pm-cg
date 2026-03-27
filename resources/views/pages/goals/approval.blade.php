@extends('layouts_.vertical', ['page_title' => 'Approval Goals'])

@section('css')
@endsection

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">
        
    @if ($errors->any())
    <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
    </div>
    @endif
    <!-- Page Heading -->
    @foreach ($data as $index => $row)
    <div class="detail-employee">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-2 pb-0">
                        <div class="row">
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee Name:</span> {{ $row->request->employee->fullname }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee ID:</span> {{ $row->request->employee->employee_id }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Job Level:</span> {{ $row->request->employee->job_level }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Business Unit:</span> {{ $row->request->employee->group_company }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Division:</span> {{ $row->request->employee->unit }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Designation:</span> {{ $row->request->employee->designation_name }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
        <div class="mandatory-field"></div>
        @foreach ($data as $index => $row)
        <form id="goalApprovalForm" action="{{ route('approval.goal') }}" method="post">
            @csrf
            <input type="hidden" name="id" value="{{ $row->request->goal->id }}">
            <input type="hidden" name="employee_id" value="{{ $row->request->employee_id }}">
            <input type="hidden" name="current_approver_id" value="{{ $row->request->current_approval_id }}">
            <div class="row">
                <div class="col-md">
                    <h4>{{ __('Target') }} {{ $row->request->period }}</h4>
                </div>
              </div>
              <!-- Content Row -->
              <div class="container-fluid p-0">
                <div class="card col-md-12 mb-3 shadow">
                    <div class="card-body pb-0 px-2 px-md-3">
                            @php
                            $formData = json_decode($row->request->goal->form_data, true);

                            $groupedFormData = collect($formData)
                                ->groupBy('cluster')
                                ->toArray();

                            $clusterTotals = [
                                'company' => 0,
                                'division' => 0,
                                'personal' => 0,
                            ];

                            foreach ($formData as $item) {
                                $cluster = $item['cluster'] ?? null;
                                if (isset($clusterTotals[$cluster])) {
                                    $clusterTotals[$cluster] += (float) $item['weightage'];
                                }
                            }
                            @endphp
                        <div class="container-card">
                            @foreach(['company' => 'Company Goals', 'division' => 'Division Goals', 'personal' => 'Personal Goals'] as $cluster => $title)

                            @php 
                                $clusterData = $groupedFormData[$cluster] ?? [];
                                $i = 1;
                            @endphp

                            @if(!empty($clusterData))

                                <h5 class="mt-3">{{ $title }}</h5>

                                @foreach ($clusterData as $data)

                                <div class="card border-primary border col-md-12 mb-3 bg-primary-subtle">
                                  <div class="card-body">
                                      <div class='row align-items-end'>
                                          <div class='col'>
                                            <h5 class='card-title fs-16 mb-0 text-primary'>Goal {{ $i++ }}
                                                <input type="hidden" name="cluster[]" value="{{ $cluster }}">
                                    </h5></div>
                                          {{-- @if ($index >= 1)
                                              <div class='col-auto'><a class='btn-close remove_field' type='button'></a></div>
                                          @endif --}}
                                      </div>
                                      <div class="row mt-2">
                                          <div class="col-md">
                                              <div class="mb-3 position-relative">
                                                <textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea pb-2 pe-3" rows="2" placeholder="Input your goals.." readonly style="resize: none">{{ $data['kpi'] }}</textarea>
                                              </div>
                                          </div>
                                      </div>
                                      <div class="row">
                                        <div class="col-md">
                                            <div class="mb-3 position-relative">
                                                <label class="form-label text-primary" for="kpi-description">Goal Descriptions</label>
                                                <textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions pb-2 pe-3" rows="2" placeholder="Input goal descriptions.." style="resize: none" readonly>{{ $data['description'] ?? "" }}</textarea>
                                            </div>
                                        </div>
                                      </div>
                                      <div class="row justify-content-between">
                                          <div class="col-md">
                                              <div class="mb-3">
                                                  <label class="form-label text-primary" for="target">Target</label>
                                                  <input type="text" oninput="validateDigits(this, {{ $index }})" value="{{ $data['target'] }}" class="form-control" readonly>
                                                  <input type="hidden" name="target[]" id="target{{ $index }}" value="{{ $data['target'] }}">
                                              </div>
                                          </div>
                                          <div class="col-md">
                                            <div class="mb-3">
                                                <label class="form-label text-primary" for="uom">{{ __('Uom') }}</label>
                                                <input type="text" name="uom[]" id="uom" value="{{ $data['uom'] !== 'Other' ? $data['uom'] : $data['custom_uom'] }}" class="form-control bg-secondary-subtle" readonly>
                                            </div>
                                          </div>
                                          <div class="col-md">
                                            <div class="mb-3">
                                                <label class="form-label text-primary" for="type">{{ __('Type') }}</label>
                                                <input type="text" name="type[]" id="type" value="{{ $data['type'] }}" class="form-control bg-secondary-subtle" readonly>
                                            </div>
                                          </div>
                                          <div class="col-6 col-md-2">
                                              <div class="mb-3">
                                                  <label class="form-label text-primary" for="weightage">{{ __('Weightage') }}</label>
                                                  <div class="input-group flex-nowrap ">
                                                      <input type="number" min="1" max="100" step="0.1" class="form-control text-center" name="weightage[]" value="{{ $data['weightage'] }}" readonly>
                                                      <div class="input-group-append">
                                                          <span class="input-group-text">%</span>
                                                      </div>
                                                  </div>                                  
                                                  {{ $errors->first("weightage") }}
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>

                                @endforeach

                            @endif

                        @endforeach
                          <div class="row">
                              <div class="col-lg">
                                  <div class="mt-2 mb-3">
                                      <label class="form-label" for="messages">Messages (Optional)</label>
                                      <textarea name="messages" id="messages{{ $row->request->id }}" class="form-control" placeholder="Enter messages..">{{ $row->request->messages }}</textarea>
                                  </div>
                              </div>
                          </div>            
                      </div>
                </form>
                <div class="row">
                    <div class="col-md d-md-flex align-items-center">
                            <div class="mb-3 text-center text-md-start">
                                <h5>Total Weightage</h5>
                                <div>Company: {{ $clusterTotals['company'] }}%</div>
                                <div>Division: {{ $clusterTotals['division'] }}%</div>
                                <div>Personal: {{ $clusterTotals['personal'] }}%</div>
                                <hr>
                                <div><strong>Total: {{ array_sum($clusterTotals) }}%</strong></div>
                            </div>
                        </div>
                    <div class="col-lg">
                        <form id="goalSendbackForm" action="{{ route('sendback.goal') }}" method="post">
                            @csrf
                            <input type="hidden" name="request_id" id="request_id">
                            <input type="hidden" name="sendto" id="sendto">
                            <input type="hidden" name="sendback" id="sendback" value="Sendback">
                            <textarea @style('display: none') name="sendback_message" id="sendback_message"></textarea>
                            <input type="hidden" name="form_id" value="{{ $row->request->form_id }}">
                            
                            <input type="hidden" name="approver" id="approver" value="{{ $row->request->manager->fullname.' ('.$row->request->manager->employee_id.')' }}">
                            
                            <input type="hidden" name="employee_id" value="{{ $row->request->employee_id }}">
                            @if ($row->request->sendback_messages)
                            <div class="d-flex align-items-center my-2">
                                <div class="form-group w-100">
                                    <label>Sendback Messages</label>
                                    <textarea class="form-control bg-warning-subtle" @disabled(true)>{{ $row->request->sendback_messages }}</textarea>
                                </div>
                            </div>
                            @endif
                            <div class="row">
                                <div class="col-lg">
                                    <div class="text-center text-lg-end mb-3">
                                        <a class="btn btn-warning rounded px-2 me-2 dropdown-toggle" href="javascript:void(0)" role="button" aria-haspopup="true" data-bs-toggle="dropdown" data-bs-offset="0,10" aria-expanded="false">{{ __('Send Back') }}</a>
                                            <div class="dropdown-menu shadow-sm">
                                                <h6 class="dropdown-header dark">Select person below :</h6>
                                                @if ($row->request->created_by == $row->request->employee->id)
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="sendBack('{{ $row->request->id }}','{{ $row->request->employee->employee_id }}','{{ $row->request->employee->fullname }}')">{{ $row->request->employee->fullname .' '.$row->request->employee->employee_id }}</a>
                                                @endif
                                                @foreach ($row->request->approval as $item)
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="sendBack('{{ $item->request_id }}','{{ $item->approver_id }}','{{ $item->approverName->fullname }}')">{{ $item->approverName->fullname.' '.$item->approver_id }}</a>
                                                @endforeach
                                            </div> 
                                        <a href="{{ url('team-goals') }}" class="btn btn-outline-secondary rounded px-2 me-2">{{ __('Cancel') }}</a>
                                        <a href="javascript:void(0)" id="submitButton" onclick="confirmAprroval()" class="btn btn-primary rounded px-2"><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>{{ __('Approve') }}</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
        @endforeach
    </div>
@endsection
@push('scripts')
    <script>
        const uom = '{{ __('Uom') }}';
        const type = '{{ __('Type') }}';
        const weightage = '{{ __('Weightage') }}';
        const errorMessages = '{{ __('Error Messages') }}';
        const errorAlertMessages = '{{ __('Error Alert Messages') }}';
        const errorConfirmMessages = '{{ __('Error Confirm Messages') }}';
    </script>
    @endpush