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

                        <div class="modal fade" id="modalDetail{{ $row->goal->id }}" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-xl mt-2" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="modal-title" id="viewFormEmployeeLabel">Goals</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                                        </button>
                                    <div class="input-group-md">
                                        <input type="text" id="employee_name" class="form-control" placeholder="Search employee.." hidden>
                                    </div>
                            </div>
                            <div class="modal-body bg-primary-subtle">
                                <div class="container-fluid py-3">
                                    <form action="" method="post">
                                    <div class="row">
                                        <div class="col">
                                            <div class="d-sm-flex align-items-center mb-2">
                                                    <h4 class="me-1">{{ $row->employee->fullname }}</h4><span class="text-muted h4">{{ $row->employee->employee_id }}</span>
                                            </div>
                                        </div>
                                    </div>
                                        <!-- Content Row -->
                                        <div class="container-card">
                                        @php
                                            $formData = json_decode($row->goal['form_data'], true);
                                        @endphp
                                        @if ($formData)
                                        @foreach ($formData as $index => $data)
                                            <div class="card mb-2 border border-primary">
                                                <div class="card-header pb-0 border-0 bg-white">
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
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      </div>
    </div>
</div>