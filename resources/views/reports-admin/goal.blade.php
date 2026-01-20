<div class="row">
    <div class="col-md-12">
      <div class="card shadow mb-4">
        <div class="card-header">
            <div class="row">
              <div class="col-md-auto text-center">
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="all">{{ __('All Task') }}</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="draft">Draft</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="waiting for revision">{{ __('Waiting For Revision') }}</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="waiting for approval">{{ __('Pending') }}</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="approved">{{ __('Approved') }}</button>
              </div>
            </div>
          </div>
        <div class="card-body">
            <table class="table table-sm table-hover nowrap align-middle w-100" id="adminReportTable" cellspacing="0">
                <thead class="thead-light">
                    <tr>
                        <th>Employees</th>
                        <th>KPI</th>
                        <th>Goal Status</th>
                        <th>Period</th>
                        <th>Approval Status</th>
                        <th>Initiated On</th>
                        <th>{{ __('Initiated By') }}</th>
                        <th>{{ __('Last Updated On') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                    <tr>
                        <td><p class="m-0">{{ $row->employee->fullname }} <span class="text-muted">{{ $row->employee_id }}</span></p></td>
                        <td class="text-center">
                            <a href="javascript:void(0)" class="btn btn-light btn-sm font-weight-medium" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $row->goal->id }}"><i class="ri-search-line"></i></a>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $row->goal->form_status == 'Approved' ? 'bg-success' : ($row->goal->form_status == 'Draft' ? 'badge-outline-secondary' : 'bg-secondary')}} px-1">{{ $row->goal->form_status == 'Draft' ? 'Draft' : $row->goal->form_status }}</span>
                        </td>
                        <td>{{ $row->goal->period }}</td>
                        <td class="text-center">
                        <a href="javascript:void(0)" data-bs-id="{{ $row->employee_id }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="{{ $row->goal->form_status=='Draft' ? 'Draft' : ($row->approvalLayer ? 'Manager L'.$row->approvalLayer.' : '.$row->name : $row->name) }}" class="badge {{ $row->status == 'Approved' ? 'bg-success' : ( $row->status=='Sendback' || $row->goal->form_status=='Draft' ? 'bg-secondary' : 'bg-warning' ) }} px-1">{{ $row->status == 'Pending' ? ($row->goal->form_status=='Draft' ? 'Not Started' : __('Pending')) : ( $row->status=='Sendback'? 'Waiting For Revision' : $row->status) }}</a>
                        </td>
                        <td class="text-center">{{ $row->formatted_created_at }}</td>
                        <td>{{ $row->initiated->name ?? '' }}<br>{{ $row->initiated->employee_id ?? '' }}</td>
                        <td class="text-center">{{ $row->formatted_updated_at }}</td>

                        <div class="modal fade" id="modalDetail{{ $row->goal->id }}" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-xl mt-2" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title h4">Goals</span>
            <button type="button" class="btn-close mr-3" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body bg-secondary-subtle">
            <div class="container-fluid py-3">
                <form>
                    <div class="d-sm-flex align-items-center mb-4">
                        <h4 class="me-1">{{ $row->employee->fullname }}</h4>
                        <span class="h4 text-muted">{{ $row->employee->employee_id }}</span>
                    </div>

                    <div class="container-card">
                        @php
                            $formData = json_decode($row->goal['form_data'], true);

                            // Group by cluster (default: personal)
                            $groupedFormData = [];
                            if (is_array($formData)) {
                                foreach ($formData as $item) {
                                    $cluster = $item['cluster'] ?? 'personal';
                                    $groupedFormData[$cluster][] = $item;
                                }
                            }
                        @endphp

                        @if (!empty($groupedFormData))
                            @foreach ([
                                'company' => 'Company Goals',
                                'division' => 'Division Goals',
                                'personal' => 'Personal Goals'
                            ] as $cluster => $title)

                                @if (!empty($groupedFormData[$cluster]))
                                    <h5 class="mt-4 mb-2">{{ $title }}</h5>

                                    @foreach ($groupedFormData[$cluster] as $index => $data)
                                        <div class="card col-md-12 mb-3 shadow-none border-1 border-dark">
                                            <div class="card-header bg-white border-0 pb-0">
                                                <h4 class="mb-0">KPI {{ $index + 1 }}</h4>
                                            </div>

                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-lg-5 mb-3">
                                                        <label class="form-label">KPI</label>
                                                        <p class="text-muted mb-0" style="white-space: pre-line">
                                                            {{ $data['kpi'] }}
                                                        </p>
                                                    </div>

                                                    <div class="col-lg-3 mb-3">
                                                        <label class="form-label">
                                                            Target ({{ $data['uom'] === 'Other' ? $data['custom_uom'] : $data['uom'] }})
                                                        </label>
                                                        <p class="text-muted mb-0">
                                                            {{ $data['target'] }}
                                                        </p>
                                                    </div>

                                                    <div class="col-lg-2 mb-3">
                                                        <label class="form-label">Weightage</label>
                                                        <p class="text-muted mb-0">
                                                            {{ $data['weightage'] }}%
                                                        </p>
                                                    </div>

                                                    <div class="col-lg-2 mb-3">
                                                        <label class="form-label">Type</label>
                                                        <p class="text-muted mb-0">
                                                            {{ $data['type'] }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <hr class="mt-1 mb-2">

                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <label class="form-label">Description</label>
                                                        <p class="text-muted mb-0" style="white-space: pre-line">
                                                            {{ $data['description'] ?? '-' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            @endforeach
                        @else
                            <p class="text-muted">No form data available.</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


                          </div>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      </div>
    </div>
</div>