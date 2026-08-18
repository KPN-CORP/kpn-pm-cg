<div class="row">
    <div class="col-md-12">
      <div class="card shadow mb-4">
        <div class="card-header">
            <div class="row">
              <div class="col-md-auto text-center">
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="All">All</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="Draft">Draft</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="Not Scheduled">Not Scheduled</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="Scheduled">Scheduled</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="Overdue">Overdue</button>
                  <button class="btn btn-outline-primary btn-sm px-2 my-1 me-1 filter-btn-performance-dialog" data-id="Done">Done</button>
              </div>
            </div>
          </div>
        <div class="card-body">
            <table class="table table-sm table-hover nowrap align-middle w-100" id="adminReportTable" cellspacing="0">
                <thead class="thead-light">
                    <tr>
                        <th>Employee</th>
                        <th>Manager</th>
                        <th>Schedule Date</th>
                        <th>Initiate Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data as $row)
                        <tr>
                            <td>{{ $row['employee_name'] }} ({{ $row['employee_id'] }})</td>
                            <td>{{ $row['employee_manager_name'] }} ({{ $row['employee_manager_id'] }})</td>
                            <td>{{ $row['formatted_schedule_at'] }}</td>
                            <td>{{ $row['formatted_initiated_at'] }}</td>
                            <td class="text-center">
                                @php
                                    $status = strtolower($row['status']);
                                    $statusClass = match($status) {
                                        'draft' => 'secondary',
                                        'overdue' => 'secondary',
                                        'scheduled' => 'warning',
                                        'approved' => 'success',
                                        'done' => 'success',
                                        default => 'light text-body'
                                    };
                                @endphp
                                <span class="badge bg-{{ $statusClass }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($row['is_action_download'])
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('performance-dialog.download', $row['id']) }}" target="_blank"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    title="Download"
                                    >
                                        <i class="ri-download-line"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      </div>
    </div>
</div>
