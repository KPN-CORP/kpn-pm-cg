<div class="modal fade" id="modalDetail{{ $row->goal->id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl mt-2" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title h4" id="viewFormEmployeeLabel">KPI's</span>
                  <button type="button" class="btn-close mr-3" data-bs-dismiss="modal" aria-label="Close"></button>
              <div class="input-group-md">
                  <input type="text" id="employee_name" class="form-control" placeholder="Search employee.." hidden>
              </div>
        </div>
        <div class="modal-body bg-primary-subtle">
          <div class="container-fluid py-3">
              <form action="" method="post">
                  <div class="d-sm-flex align-items-center mb-4">
                        <h4 class="me-1">{{ $row->employee->fullname }}</h4> <span class="h4 text-muted">{{ $row->employee->employee_id }}</span>
                  </div>
                  <!-- Content Row -->
                  <div class="container-card">
                    @php
                        $formData = json_decode($row->goal['form_data'], true);
                        // Group by cluster for backward compatibility
                        $groupedFormData = [];
                        foreach ($formData as $item) {
                            $cluster = $item['cluster'] ?? 'personal';
                            $groupedFormData[$cluster][] = $item;
                        }
                    @endphp
                    @if ($groupedFormData)
                    @foreach(['company' => 'Company Goals', 'division' => 'Division Goals', 'personal' => 'Personal Goals'] as $cluster => $title)
                      @if(!empty($groupedFormData[$cluster]))
                        <h5 class="mt-3">{{ $title }}</h5>
                        @foreach ($groupedFormData[$cluster] as $index => $data)
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
                        @endforeach
                      @endif
                    @endforeach
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