@extends('layouts_.vertical', ['page_title' => 'Goals'])

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

    <div class="detail-employee">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-2 pb-0">
                        <div class="row">
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee Name:</span> {{ $approvalRequest->employee->fullname }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee ID:</span> {{ $approvalRequest->employee->employee_id }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Job Level:</span> {{ $approvalRequest->employee->job_level }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Business Unit:</span> {{ $approvalRequest->employee->group_company }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Division:</span> {{ $approvalRequest->employee->unit }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Designation:</span> {{ $approvalRequest->employee->designation_name }}</p>
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
        <form id="goalForm" action="{{ route('goals.update') }}" class="needs-validation" method="POST">
        @csrf
          <input type="hidden" class="form-control" name="id" value="{{ $goal->id }}">
          <input type="hidden" class="form-control" name="employee_id" value="{{ $goal->employee_id }}">
          <input type="hidden" class="form-control" name="category" value="Goals">
          <!-- Content Row -->
          <div class="row">
            <div class="col-md">
                <h4>{{ __('Target') }} {{ $goal->period }}</h4>
            </div>
          </div>
          <div class="container-fluid p-0">
            <div class="card col-md-12 mb-3 shadow">
                <div class="card-body pb-0 px-2 px-md-3">
                    <div class="container-card">
                    @php $goalIndex = 0; @endphp
                    @foreach(['company' => 'Company Goals', 'division' => 'Division Goals', 'personal' => 'Personal Goals'] as $cluster => $title)
                      @if(!empty($data[$cluster]))
                        <h5 class="mt-3">{{ $title }}</h5>
                        @if($cluster == 'personal' || $cluster == 'division')
                          <div id="{{ $cluster }}-goals">
                        @endif
                        @foreach($data[$cluster] as $index => $row)
                          <div class="card border-primary border col-md-12 mb-3 bg-primary-subtle">
                              <div class="card-body">
                                  <div class='row align-items-end'>
                                    <div class='col'><h5 class='card-title fs-16 mb-0 text-primary'>Goal {{ $goalIndex + 1 }}</h5></div>
                                    @if ($cluster == 'personal' && $index >= 1)
                                        <div class='col-auto'><a class='btn-close remove_field' type='button'></a></div>
                                    @endif
                                  </div>
                                  <input type="hidden" name="cluster[]" value="{{ $cluster }}">
                                  <div class="row mt-2">
                                      <div class="col-md">
                                        <div class="mb-3 position-relative">
                                              <textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea pb-2 pe-3" rows="2" placeholder="Input your goals.." {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'readonly' }} style="resize: none">{{ $row['kpi'] }}</textarea>
                                              <div class="invalid-feedback">
                                                  {{ __('This field is mandatory') }}
                                              </div>
                                        </div>
                                      </div>
                                  </div>
                                  <div class="row">
                                      <div class="col-md">
                                          <div class="mb-3 position-relative">
                                              <label class="form-label text-primary" for="kpi-description">Goal Descriptions</label>
                                              <textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions pb-2 pe-3" rows="2" placeholder="Input goal descriptions.." style="resize: none">{{ $row['description'] ?? "" }}</textarea>
                                          </div>
                                      </div>
                                  </div>
                                  <div class="row justify-content-between">
                                      <div class="col-md">
                                          <div class="mb-3">
                                            <label class="form-label text-primary" for="target">Target</label>
                                            <input type="text" oninput="validateDigits(this, {{ $goalIndex }})" value="{{ $row['target'] }}" class="form-control" {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'readonly' }}>
                                            <input type="hidden" name="target[]" id="target{{ $goalIndex }}" value="{{ $row['target'] }}">
                                            <div class="invalid-feedback">
                                              {{ __('This field is mandatory') }}
                                          </div>
                                        </div>
                                      </div>
                                      <div class="col-md">
                                        <div class="mb-3">
                                            <label class="form-label text-primary" for="uom">{{ __('Uom') }}</label>
                                            <select class="form-select select2 max-w-full select-uom" data-id="{{ $goalIndex }}" name="uom[]" id="uom{{ $goalIndex }}" title="Unit of Measure" {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'disabled' }}>
                                                <option value="">- Select -</option>
                                                @foreach ($uomOption as $label => $options)
                                                <optgroup label="{{ $label }}">
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}" {{ ($row['uom'] ?? '') == $option ? 'selected' : '' }}>
                                                            {{ $option }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                                @endforeach
                                            </select>
                                            <div class="invalid-feedback">
                                              {{ __('This field is mandatory') }}
                                            </div>
                                            <input 
                                                type="text" 
                                                name="custom_uom[]" 
                                                id="custom_uom{{ $goalIndex }}" 
                                                class="form-control mt-2" 
                                                value="{{ $row['custom_uom'] }}" 
                                                placeholder="Enter UoM" 
                                                @if (($row['uom'] ?? '') !== 'Other') 
                                                    style="display: none;" 
                                                @endif 
                                            >
                                        </div>
                                      </div>
                                      <div class="col-md">
                                        <div class="mb-3">
                                            <label class="form-label text-primary" for="type">{{ __('Type') }}</label>
                                            <select class="form-select" name="type[]" id="type{{ $goalIndex }}" {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'disabled' }}>
                                                @foreach ($typeOption as $label => $options)
                                                    @foreach ($options as $option)
                                                        <option value="{{ $option }}" {{ ($row['type'] ?? '') == $option ? 'selected' : '' }}>
                                                            {{ $option }}
                                                        </option>
                                                    @endforeach
                                                @endforeach
                                            </select>
                                            <div class="invalid-feedback">
                                              {{ __('This field is mandatory') }}
                                          </div>
                                        </div>
                                      </div>
                                      <div class="col-6 col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label text-primary" for="weightage">{{ __('Weightage') }}</label>
                                              <div class="input-group">
                                                  <input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" value="{{ $row['weightage'] }}" required>
                                                  <span class="input-group-text">%</span>
                                                  <div class="invalid-feedback">
                                                      {{ __('This field is mandatory') }}
                                                  </div>
                                              </div>                              
                                            {{ $errors->first("weightage") }}
                                        </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          @php $goalIndex++; @endphp
                        @endforeach
                        @if($cluster == 'personal' || $cluster == 'division')
                          </div>
                          <div class="mt-3">
                            <a class="btn btn-outline-primary rounded add-personal-btn" data-cluster="{{ $cluster }}"><i class="ri-add-line me-1"></i><span>{{ __('Add ' . ucfirst($cluster) . ' Goal') }}</span></a>
                          </div>
                        @endif
                      @endif
                    @endforeach
                      </div>
                      <input type="hidden" id="count" value="{{ $goalIndex }}">
                      
                      @if ($approvalRequest->sendback_messages)
                          <div class="row">
                              <div class="col">
                                  <div class="mb-3">
                                      <label class="form-label">{{ __('Send Back Messages') }}</label>
                                      <textarea class="form-control" rows="5" @disabled(true)>{{ $approvalRequest->sendback_messages }}</textarea>
                                  </div>
                              </div>
                          </div>
                      @endif
                      <div class="row align-items-center">
                          <div class="col">
                              <input type="hidden" name="submit_type" id="submitType" value=""> <!-- Hidden input to store the button clicked -->
                              <div class="mb-3 text-center text-md-start">
                                  <h5>{{ __('Total Weightage') }} : <span class="font-weight-bold text-success" id="totalWeightage">{{ $totalWeightages.'%' }}</span></h5>
                              </div>
                          </div>
                          <div class="col-md-auto">
                              <div class="mb-3 text-center">
                                  @if ($goal->form_status=='Draft')
                                  <a id="submitButton" name="save_draft" class="btn btn-outline-info rounded save-draft me-1" data-id="save_draft" ><i class="fas fa-save d-sm-none"></i><span class="d-sm-inline d-none">Save as </span>Draft</a>  
                                  @endif
                                  <a href="{{ url()->previous() }}" class="btn btn-outline-secondary rounded px-3 me-1">{{ __('Cancel') }}</a>
                                  <a id="submitButton" data-id="submit_form" name="submit_form" class="btn btn-primary rounded px-3 shadow"><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>{{ __('Submit') }}</a>
                              </div>
                          </div>
                      </div>
                </div>
            </div>
          </div>
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