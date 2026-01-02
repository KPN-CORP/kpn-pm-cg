<div class="row">
    <div class="col">
        <div class="mb-2 text-primary fw-semibold fs-16 {{ $formData['totalKpiScore'] ? '' : 'd-none'}}">
            Total Score : {{ $formData['totalScore'] }}
        </div>
    </div>
</div>
@forelse ($appraisalData['formData'] as $indexItem => $item)
<div class="row">
    <button class="btn rounded mb-2 py-2 bg-secondary-subtle bg-opacity-10 text-primary align-items-center d-flex justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $indexItem }}" aria-expanded="false" aria-controls="collapse-{{ $indexItem }}">
        <span class="fs-16 ms-1">
            {{ $item['formName'] }} 
            | Score : {{ $item['formName'] === 'KPI' ? $appraisalData['totalKpiScore'] : ($item['formName'] === 'Culture' ? $appraisalData['totalCultureScore'] : ($item['formName'] === 'Leadership' ? $appraisalData['totalLeadershipScore'] : ($item['formName'] === 'Technical' ? $appraisalData['totalTechnicalScore'] : ($item['formName'] === 'Sigap' ? $appraisalData['totalSigapScore'] : $appraisalData['totalSigapScore'])))) }}
        </span>  
        <span>
            <p class="d-none d-md-inline me-1">Details</p><i class="ri-arrow-down-s-line"></i>
        </span>                               
    </button>
    @if ($item['formName'] == 'Leadership')
    <div class="collapse" id="collapse-{{ $indexItem }}">
        <div class="card card-body mb-3">
            @forelse($formData['formData'] as $form)
                @if($form['formName'] === 'Leadership')
                    @foreach($form as $key => $item)
                        @if(is_numeric($key))
                        <div class="{{ $loop->last ? '':'border-bottom' }} mb-3">
                            @if(isset($item['title']))
                                <h5 class="mb-3"><u>{!! $item['title'] !!}</u></h5>
                            @endif
                            @foreach($item as $subKey => $subItem)
                                @if(is_array($subItem))
                                <ul class="ps-3">
                                    <li>
                                        <div>
                                            @if(isset($subItem['formItem']))
                                                <p class="mb-1">{!! $subItem['formItem'] !!}</p>
                                            @endif
                                            @if(isset($subItem['score']))
                                                <p><strong>Score:</strong> {{ $subItem['score'] }}</p>
                                            @endif
                                        </div>
                                    </li>
                                </ul>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    @endforeach
                @endif
            @empty
                <p>No Data</p>
            @endforelse
        </div>
    </div>
    @elseif($item['formName'] == 'Sigap')
    <div class="collapse" id="collapse-{{ $indexItem }}">
        <div class="card card-body mb-3">
            @forelse($formData['formData'] as $form)
                @if($form['formName'] === 'Sigap')
                    @foreach($form as $key => $item)
                        @if(is_numeric($key))
                            @php
                                $score = ($formData['contributor_type'] == 'summary') ? ($item[0]['score'] ?? null) : ($item['score'] ?? null);
                            @endphp

                            <div class="{{ $loop->last ? '' : 'border-bottom' }} mb-3">
                                {{-- Title --}}
                                @if(isset($item['title']))
                                    <h5 class="mb-3"><u>{{ $item['title'] }}</u></h5>
                                @endif

                                {{-- Score --}}
                                @if($score)
                                    <p><strong>Score:</strong> {{ $score }}</p>
                                @endif

                                {{-- Description --}}
                                @if($score && isset($item['items'][$score]))
                                    <div class="alert border mt-2">
                                        <p class="mb-1">
                                            {{ $item['items'][$score]['desc_idn'] }}
                                        </p>
                                        <p class="mb-0 italic" style="font-style: italic;">
                                            {{ $item['items'][$score]['desc_eng'] }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                @endif

            @empty
                <p>No Data</p>
            @endforelse
        </div>
        </div>
    @elseif($item['formName'] == 'Culture')
    <div class="collapse" id="collapse-{{ $indexItem }}">
        <div class="card card-body mb-3">
            @forelse($formData['formData'] as $form)
                @if($form['formName'] === 'Culture')
                    @foreach($form as $key => $item)
                        @if(is_numeric($key))
                        <div class="{{ $loop->last ? '':'border-bottom' }} mb-3">
                            @if(isset($item['title']))
                                <h5 class="mb-3"><u>{!! $item['title'] !!}</u></h5>
                            @endif
                            @foreach($item as $subKey => $subItem)
                                @if(is_array($subItem))
                                <ul class="ps-3">
                                    <li>
                                        <div>
                                            @if(isset($subItem['formItem']))
                                                <p class="mb-1">{!! $subItem['formItem'] !!}</p>
                                            @endif
                                            @if(isset($subItem['score']))
                                                <p><strong>Score:</strong> {{ $subItem['score'] }}</p>
                                            @endif
                                        </div>
                                    </li>
                                </ul>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    @endforeach
                @endif
            @empty
                <p>No Data</p>
            @endforelse
        </div>
    </div>
    @else 
    <div class="collapse" id="collapse-{{ $indexItem }}">
        <div class="card card-body mb-3 py-0">
            @forelse ($formData['formData'] as $form)
            @if ($form['formName'] === 'KPI')
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>KPI</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Weightage') }}</th>
                            <th>Target</th>
                            <th>{{ __('Actual') }}</th>
                            <th>{{ __('Achievement') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($form as $key => $data)
                        @if (is_array($data))
                        <tr>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0">{{ $key + 1 }}</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['kpi'] }}</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted">{{ $data['type'] }}</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted">{{ $data['weightage'] }}%</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted">{{ $data['target'] }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted">{{ $data['achievement'] }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}</p>
                            </td>
                            <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                                <p class="mt-1 mb-0 text-muted">{{ round($data['percentage']) }}%</p>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @empty
            <p>No form data available.</p>
            @endforelse
        </div>
    </div>
    @endif
</div>
@empty
    No Data
@endforelse