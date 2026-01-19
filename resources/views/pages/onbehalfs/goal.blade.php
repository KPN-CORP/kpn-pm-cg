<div class="row">
    <div class="col-md-12">
      <div class="card shadow mb-4">
        <div class="card-header">
          <div class="row rounded">
            <div class="col-md-auto text-center">
                <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="all">{{ __('All Task') }}</button>
                <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="draft">Draft</button>
                <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="{{ __('Waiting For Revision') }}">{{ __('Waiting For Revision') }}</button>
                <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="{{ __('Pending') }}">{{ __('Pending') }}</button>
                <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn" data-id="{{ __('Approved') }}">{{ __('Approved') }}</button>
            </div>
          </div>
        </div>
        <div class="card-body">
            <table class="table table-sm table-hover align-middle activate-select dt-responsive nowrap w-100 fs-14" id="onBehalfTable">
                <thead class="thead-light">
                    <tr class="text-center">
                        <th>Employees</th>
                        <th>Goals</th>
                        <th>Approval Status</th>
                        <th>Initiated On</th>
                        <th>{{ __('Initiated By') }}</th>
                        <th>{{ __('Last Updated On') }}</th>
                        <th>Updated By</th>
                        <th class="sorting_1">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                    <tr>
                      <td>{{ $row->employee->fullname .' ('.$row->employee->employee_id.')'}}</td>
                      <td class="text-center">
                        <a href="javascript:void(0)" class="btn btn-outline-secondary rounded btn-sm {{ $row->goal->form_status === 'Draft' ? 'disabled' : '' }}" data-bs-toggle="modal" data-bs-target="#modalDetail{{ $row->goal->id }}"><i class="ri-file-text-line"></i></a>
                      </td>
                      <td class="text-center">
                        <a href="javascript:void(0)" data-bs-id="{{ $row->employee_id }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="{{ $row->approvalLayer ? 'Manager L'.$row->approvalLayer.' : '.$row->name : $row->name }}" class="badge py-1 px-2 rounded-pill {{ $row->goal->form_status == 'Draft' || $row->status == 'Sendback' ? 'bg-secondary' : ($row->status === 'Approved' ? 'bg-success' : 'bg-warning')}} ">{{ $row->goal->form_status == 'Draft' ? 'Draft': ($row->status == 'Pending' ? __('Pending') : ($row->status == 'Sendback' ? 'Waiting For Revision' : $row->status)) }}</a></td>
                      <td class="text-center">{{ $row->formatted_created_at }}</td>
                      <td>{{ $row->initiated ? $row->initiated->name .' ('. $row->initiated->employee_id .')'  : '-' }}</td>
                      <td class="text-center">{{ $row->formatted_updated_at }}</td>
                      <td>{{ $row->updatedBy ? $row->updatedBy->name.' ('.$row->updatedBy->employee_id.')' : '-' }}</td>
                      @if ($data)
                      @include('pages.onbehalfs.goal_detail')
                      @endif
                      <td class="text-center sorting_1 px-1">
                        @can('approvalonbehalf')
                        <div class="btn-group dropstart">
                          <button class="btn btn-sm {{ $row->status != 'Sendback' && $row->goal->form_status != 'Draft' && $row->access ? 'btn-primary' : 'btn-light disabled' }} px-1 rounded" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" id="animated-preview" data-bs-offset="0,10">
                            Action
                          </button>
                          <div class="dropdown-menu dropdown-menu-animated">
                            @if ( $row->status != 'Sendback' && $row->goal->form_status != 'Draft')
                              @if ( $row->status === 'Pending')
                                <a class="dropdown-item" href="{{ route('admin.create.approval.goal', $row->form_id) }}">Approve</a>
                              @endif
                                <a class="dropdown-item" href="javascript:void(0)" id="revoke-btn-{{ $row->goal->id }}" onclick="revokeGoal(this)" data-id="{{ $row->goal->id }}" data-name="{{ $row->employee->fullname . ' - ' . $row->employee->employee_id }}">Revise</a>
                            @else
                            <a class="dropdown-item disabled" href="javascript:void(0)">{{ __('No Action') }}</a>
                            @endif
                          </div>
                        </div>
                        @else
                        {{ "-" }}
                        @endcan
                      </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      </div>
    </div>
</div>
     
