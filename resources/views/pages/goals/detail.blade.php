<div class="modal fade" id="modalDetail{{ $goalId }}" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl mt-2" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title h4" id="viewFormEmployeeLabel">Goals</h4>
                <button type="button" class="btn-close mr-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="input-group-md">
                    <input type="text" id="employee_name" class="form-control" placeholder="Search employee.." hidden>
                </div>
            </div>
            <div class="modal-body bg-primary-subtle">
                <div class="container-fluid py-3">
                    <form action="" method="post">
                        <div class="d-sm-flex align-items-center mb-3">
                                <h4 class="me-1">{{ $task->employee->fullname }}</h4><span class="text-muted h4">{{ $task->employee->employee_id }}</span>
                        </div>
                        @if ($sendbackMessages && $sendbackTo == $employeeId)
                        <div class="card col-md-12 mb-2 border border-dark">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md px-2">
                                        <div class="form-group">
                                            <h5>Revision Notes :</h5>
                                            <p class="mt-1 mb-0 text-muted">{{ $sendbackMessages }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        <!-- Content Row -->
                        <div class="container-card">
                            @php
                                $formData = json_decode($goalData, true);
                                
                                // Group KPIs by cluster if cluster field exists
                                $groupedData = [];
                                $clusterKPIs = [
                                    'company' => [],
                                    'division' => [],
                                    'individual' => []
                                ];
                                
                                if ($formData) {
                                    foreach ($formData as $index => $data) {
                                        // Determine cluster type
                                        if (isset($data['cluster'])) {
                                            $cluster = strtolower($data['cluster']);
                                            if (!isset($groupedData[$cluster])) {
                                                $groupedData[$cluster] = [];
                                            }
                                            $groupedData[$cluster][] = array_merge($data, ['index' => $index]);
                                        } else {
                                            // Fallback to individual if no cluster
                                            $groupedData['individual'][] = array_merge($data, ['index' => $index]);
                                        }
                                    }
                                }
                            @endphp
                            
                            @if ($formData)
                                @if (!empty($groupedData))
                                    {{-- Display clustered KPIs --}}
                                    @foreach ($groupedData as $clusterName => $kpis)
                                        <div class="mb-4">
                                            <div class="bg-light p-2 mb-2 rounded-top border-start border-3 border-primary">
                                                <h5 class="mb-0 text-primary text-uppercase fw-semibold">
                                                    {{ $clusterName === 'company' ? 'Company KPI' : ($clusterName === 'division' ? 'Division KPI' : 'Personal KPI') }}
                                                </h5>
                                            </div>
                                            
                                            @foreach ($kpis as $data)
                                                <div class="card col-md-12 mb-2 border border-primary">
                                                    <div class="card-header bg-white pb-0">
                                                        <h4>{{ __('Goal') }} {{ $data['index'] + 1 }}</h4>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-lg-5 mb-3">
                                                                <div class="form-group">
                                                                    <label class="form-label" for="kpi">KPI</label>
                                                                    <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['kpi'] }}</p>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-3 mb-3">
                                                                <div class="form-group">
                                                                    <label class="form-label" for="target">{{ __('Target In UoM') }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}</label>
                                                                    <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['target'] }}</p>
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
                                                        </div>
                                                        <hr class="mt-0 mb-2">
                                                        <div class="row">
                                                            <div class="col-md mb-2">
                                                                <div class="form-group">
                                                                    <label class="form-label" for="description">Description</label>
                                                                    <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['description'] ?? '-' }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                @else
                                    {{-- Fallback to simple display if no grouping --}}
                                    @foreach ($formData as $index => $data)
                                        <div class="card col-md-12 mb-2 border border-primary">
                                            <div class="card-header bg-white pb-0">
                                                <h4>{{ __('Goal') }} {{ $index + 1 }}</h4>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-lg-5 mb-3">
                                                        <div class="form-group">
                                                            <label class="form-label" for="kpi">KPI</label>
                                                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['kpi'] }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-3 mb-3">
                                                        <div class="form-group">
                                                            <label class="form-label" for="target">{{ __('Target In UoM') }} {{ is_null($data['custom_uom']) ? $data['uom']: $data['custom_uom'] }}</label>
                                                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['target'] }}</p>
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
                                                </div>
                                                <hr class="mt-0 mb-2">
                                                <div class="row">
                                                    <div class="col-md mb-2">
                                                        <div class="form-group">
                                                            <label class="form-label" for="description">Description</label>
                                                            <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['description'] ?? '-' }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            @else
                                <p>No form data available.</p>
                            @endif                
                        </div>
                    </form>
                </div>
            </div>
      </div>
    </div>
  </div>