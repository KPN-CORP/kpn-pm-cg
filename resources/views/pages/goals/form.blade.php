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

    /* gradient + animasi */
    background: linear-gradient(135deg,
      rgba(62,96,213,.65) 0%,
      rgba(167,139,250,.65) 50%,
      rgba(244,114,182,.65) 100%);
    background-size: 200% 200%;
    animation: gradient-move 4s ease-in-out infinite;

    /* FADE */
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
                                        <p class="mb-2"><span class="text-muted">Employee Name:</span> {{ $datas->first()->employee->fullname }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Employee ID:</span> {{ $datas->first()->employee->employee_id }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Job Level:</span> {{ $datas->first()->employee->job_level }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md">
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Business Unit:</span> {{ $datas->first()->employee->group_company }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Division:</span> {{ $datas->first()->employee->unit }}</p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <p class="mb-2"><span class="text-muted">Designation:</span> {{ $datas->first()->employee->designation_name }}</p>
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
        <!-- Page Heading -->
        <form id="goalForm" action="{{ route('goals.submit') }}" class="needs-validation" method="POST">
            @csrf
          @foreach ($datas as $index => $data)
          <input type="hidden" class="form-control" name="users_id" value="{{ Auth::user()->id }}">
          <input type="hidden" class="form-control" name="approver_id" value="{{ $data->approver_id }}">
          <input type="hidden" class="form-control" name="employee_id" id="employee_id" value="{{ $data->employee_id }}">
          <input type="hidden" class="form-control" name="category" value="Goals">
          <input type="hidden" class="form-control" id="period" value="{{ $period }}">
          @endforeach
          <!-- Content Row -->
          <div class="row">
            <div class="col-md">
                <h4>{{ __('Target') }} {{ $period }}</h4>
            </div>
            <div class="col-auto">
                <button type="button" id="getLatestGoal" class="btn btn-sm btn-outline-info rounded d-inline-flex align-items-center gap-2 d-none">
                <span class="label"><i class="ri-bard-fill me-1"></i>Get My Last Year’s Goal</span>
                <span class="loading d-none align-items-center gap-2">
                    <span class="spinner-border spinner-border-sm text-info me-1" role="status" aria-hidden="true"></span>
                    <span>Generating...</span>
                </span>
                </button>
            </div>
          </div>
          <div class="container-fluid mt-3 p-0">
            <div class="card col-md-12 m-0 shadow">
                <div class="card-body pb-0 px-2 px-md-3">
                    <div class="container-card">
                      @php $goalIndex = 0; @endphp
                      @foreach(['company' => 'Company Goals', 'division' => 'Division Goals', 'personal' => 'Personal Goals'] as $cluster => $title)
                        @if(!empty($clusterKPIs[$cluster]) || $cluster == 'personal' || $cluster == 'division')
                          <h5 class="mt-3">{{ $title }}</h5>
                          @php $clusterIndex = 0; @endphp
                          @if($cluster == 'personal' || $cluster == 'division')
                            <div id="{{ $cluster }}-goals">
                              <!-- Default Goal Card (Cannot be deleted) -->
                              <div class="card border-primary border col-md-12 mb-3 bg-primary-subtle">
                                  <div class="card-body">
                                      <h5 class="card-title fs-16 text-primary">Goal {{ $clusterIndex + 1 }}</h5>
                                      <input type="hidden" name="cluster[]" value="{{ $cluster }}">
                                      <div class="row">
                                        <div class="col-md">
                                            <div class="mb-3 position-relative">
                                                <textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea pb-2 pe-3" rows="2" placeholder="Input your goals.." required style="resize: none"></textarea>
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
                                                <textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions pb-2 pe-3" rows="2" placeholder="Input goal descriptions.." style="resize: none"></textarea>
                                            </div>
                                        </div>
                                      </div>
                                      <div class="row justify-content-between">
                                          <div class="col-md">
                                              <div class="mb-3">
                                                  <label class="form-label text-primary" for="target">Target</label>
                                                  <input type="text" oninput="validateDigits(this, {{ $goalIndex }})" class="form-control" required>
                                                  <input type="hidden" name="target[]" id="target{{ $goalIndex }}">
                                                  <div class="invalid-feedback">
                                                    {{ __('This field is mandatory') }}
                                                </div>
                                              </div>
                                          </div>
                                          <div class="col-md">
                                              <div class="mb-3">
                                                  <label class="form-label text-primary" for="uom">{{ __('Uom') }}</label>
                                                  <select class="form-select select2 max-w-full select-uom" data-id="{{ $goalIndex }}" name="uom[]" id="uom{{ $goalIndex }}" title="Unit of Measure" required>
                                                      <option value="">- Select -</option>
                                                      @foreach ($uomOption as $label => $options)
                                                      <optgroup label="{{ $label }}">
                                                          @foreach ($options as $option)
                                                              <option value="{{ $option }}">
                                                                  {{ $option }}
                                                              </option>
                                                          @endforeach
                                                      </optgroup>
                                                      @endforeach
                                                  </select>
                                                  <div class="invalid-feedback">
                                                    {{ __('This field is mandatory') }}
                                                </div>
                                                  <input type="text" class="form-control mt-2" name="custom_uom[]" id="custom_uom{{ $goalIndex }}" @style('display: none') placeholder="Enter UoM">
                                              </div>
                                          </div>
                                          <div class="col-md">
                                              <div class="mb-3">
                                                  <label class="form-label text-primary" for="type">{{ __('Type') }}</label>
                                                  <select class="form-select select-type" name="type[]" id="type{{ $goalIndex }}" required>
                                                      <option value="">- Select -</option>
                                                      <option value="Higher Better">Higher Better</option>
                                                      <option value="Lower Better">Lower Better</option>
                                                      <option value="Exact Value">Exact Value</option>
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
                                                      <input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" required>
                                                      <span class="input-group-text">%</span>
                                                        <div class="invalid-feedback">
                                                            {{ __('This field is mandatory') }}
                                                        </div>
                                                  </div>                                  
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                              @php $goalIndex++; $clusterIndex++; @endphp
                          @endif
                          @foreach($clusterKPIs[$cluster] ?? [] as $kpi)
                            <div class="card border-primary border col-md-12 mb-3 bg-primary-subtle">
                                <div class="card-body">
                                    <h5 class="card-title fs-16 text-primary">Goal {{ $clusterIndex + 1 }}</h5>
                                    <input type="hidden" name="cluster[]" value="{{ $cluster }}">
                                    <div class="row">
                                      <div class="col-md">
                                          <div class="mb-3 position-relative">
                                              <textarea name="kpi[]" id="kpi" class="form-control overflow-hidden kpi-textarea pb-2 pe-3" rows="2" placeholder="Input your goals.." {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'readonly' }} style="resize: none">{{ $kpi['kpi'] ?? old('kpi.' . $goalIndex) }}</textarea>
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
                                              <textarea name="description[]" id="kpi-description" class="form-control overflow-hidden kpi-descriptions pb-2 pe-3" rows="2" placeholder="Input goal descriptions.." style="resize: none" {{ in_array($cluster, ['personal', 'division']) ? '' : 'readonly' }}>{{ $kpi['description'] ?? old('description.' . $goalIndex) }}</textarea>
                                          </div>
                                      </div>
                                    </div>
                                    <div class="row justify-content-between">
                                        <div class="col-md">
                                            <div class="mb-3">
                                                <label class="form-label text-primary" for="target">Target</label>
                                                <input  type="text" oninput="validateDigits(this, {{ $goalIndex }})" value="{{ $kpi['target'] ?? old('target.' . $goalIndex) }}" class="form-control" {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'readonly' }}>
                                                <input type="hidden" name="target[]" id="target{{ $goalIndex }}" value="{{ $kpi['target'] ?? old('target.' . $goalIndex) }}">
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
                                                            <option value="{{ $option }}" {{ ($kpi['uom'] ?? '') == $option ? 'selected' : '' }}>
                                                                {{ $option }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                    @endforeach
                                                </select>
                                                <div class="invalid-feedback">
                                                  {{ __('This field is mandatory') }}
                                              </div>
                                                <input type="text" class="form-control mt-2" name="custom_uom[]" id="custom_uom{{ $goalIndex }}" @style('display: none') placeholder="Enter UoM" value="{{ $kpi['custom_uom'] ?? '' }}">
                                            </div>
                                        </div>
                                        <div class="col-md">
                                            <div class="mb-3">
                                                <label class="form-label text-primary" for="type">{{ __('Type') }}</label>
                                                <select class="form-select select-type" name="type[]" id="type{{ $goalIndex }}" {{ in_array($cluster, ['personal', 'division']) ? 'required' : 'disabled' }}>
                                                    <option value="">- Select -</option>
                                                    <option value="Higher Better" {{ ($kpi['type'] ?? '') == 'Higher Better' ? 'selected' : '' }}>Higher Better</option>
                                                    <option value="Lower Better" {{ ($kpi['type'] ?? '') == 'Lower Better' ? 'selected' : '' }}>Lower Better</option>
                                                    <option value="Exact Value" {{ ($kpi['type'] ?? '') == 'Exact Value' ? 'selected' : '' }}>Exact Value</option>
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
                                                    <input type="number" min="5" max="100" step="0.1" class="form-control" name="weightage[]" value="{{ $kpi['weightage'] ?? old('weightage.' . $goalIndex) }}" required>
                                                    <span class="input-group-text">%</span>
                                                      <div class="invalid-feedback">
                                                          {{ __('This field is mandatory') }}
                                                      </div>
                                                </div>                                  
                                            </div>
                                            {{ $errors->first("weightage") }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @php $goalIndex++; $clusterIndex++; @endphp
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
                <div class="card-footer">
                    <input type="hidden" id="count" value="{{ $goalIndex }}">
                    <input type="hidden" name="submit_type" id="submitType" value=""> <!-- Hidden input to store the button clicked -->
                    <div class="row">
                        <div class="col-md d-md-flex align-items-center">
                            <div class="mb-3 text-center text-md-start">
                                <h5>{{ __('Total Weightage') }} : <span class="font-weight-bold" id="totalWeightage">-</span></h5>
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <div class="mb-3 text-center">
                                <a id="submitButton" data-id="save_draft" name="save_draft" class="btn btn-outline-info rounded save-draft me-1"><span class="d-sm-inline d-none">Save as </span>Draft</a>
                                <a href="{{ url('goals') }}" class="btn btn-outline-secondary rounded me-1">{{ __('Cancel') }}</a>
                                <a id="submitButton" data-id="submit_form" name="submit_form" class="btn btn-primary rounded shadow"><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>{{ __('Submit') }}</a>
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