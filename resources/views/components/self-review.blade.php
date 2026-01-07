<div class="form-group mb-2">
    <h4 class="mb-3">
        Objektif Kerja
    </h4>
    <input type="hidden" name="formData[{{ $formIndex }}][formName]" value="{{ $name }}">
    @forelse ($goalData as $index => $data)
    <div class="row">
        <div class="card col-md-12 mb-2 p-0 border border-primary bg-light-subtle">
            <div class="card-header">
                <h4>{{ __('Goal') }} {{ $index + 1 }}</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4 mb-3">
                        <div class="form-group">
                            <label class="form-label" for="kpi">KPI @if(isset($data['cluster'])) ({{ ucwords($data['cluster']) }}) @endif</label>
                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['kpi'] }}</p>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <div class="form-group">
                            <label class="form-label" for="weightage">{{ __('Weightage') }}</label>
                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['weightage'] }}%</p>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <div class="form-group">
                            <label class="form-label" for="type">{{ __('Type') }}</label>
                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['type'] }}</p>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <div class="form-group">
                            <label class="form-label" for="target">{{ __('Target In UoM') }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}</label>
                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['target'] }}</p>
                        </div>
                    </div>
                    <div class="col-lg-2 mb-3">
                        @if (strtolower($data['cluster']) != 'company' && !isset($data['actual']))
                            <div class="form-group">
                                <label class="form-label" for="target">{{ __('Achievement In') }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}
                                </label>
                                <input type="number" id="achievement-{{ $index + 1 }}" name="formData[{{ $formIndex }}][{{ $index }}][achievement]" placeholder="{{ __('Enter Achievement') }}.." value="{{ isset($data['actual']) ? $data['actual'] : "" }}" class="form-control achievement mt-1" />
                                <div class="text-danger error-message"></div>
                            </div> 
                        @else
                            <div class="form-group">
                                <label class="form-label {{ isset($data['actual']) ?? 'd-none' }}" for="target">{{ __('Achievement In') }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}
                                </label>
                                <input type="number" id="achievement-{{ $index + 1 }}" name="formData[{{ $formIndex }}][{{ $index }}][achievement]" placeholder="{{ __('Enter Achievement') }}.." value="{{ isset($data['actual']) ? $data['actual'] : "" }}" class="mt-1 d-none" />
                                <div class="text-danger error-message"></div>
                                <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['actual'] }}</p>
                            </div>                          
                        @endif
                    </div>
                </div>
                <hr class="mt-0 mb-2">
                <div class="row">
                    <div class="col-md mb-2">
                        <div class="form-group
                        ">
                            <label class="form-label
                            " for="description">Description</label>
                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['description'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
        <p>No form data available.</p>
    @endforelse
</div>