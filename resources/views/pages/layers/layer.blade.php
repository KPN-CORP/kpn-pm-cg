@extends('layouts_.vertical', ['page_title' => 'Layers'])

@section('css')
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Begin Page Content -->
    <div class="container-fluid"> 
        <div class="row">
            <div class="col-lg">
                <div class="mb-3 text-end">
                    <button type="button" class="btn btn-primary open-import-modal" title="Import">Import Layer</button>
                </div>
            </div>
        </div>    
        <div class="row">
            <div class="col-md-auto">
              <div class="mb-3">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-dark-subtle"><i class="ri-search-line"></i></span>
                  </div>
                  <input type="text" name="customsearch" id="customsearch" class="form-control  border-dark-subtle border-left-0" placeholder="search.." aria-label="search" aria-describedby="search">
                </div>
              </div>
            </div>
        </div>
        <!-- Content Row -->
        <div class="row">
          <div class="col-md-12">
            <div class="card shadow mb-4">
              <div class="card-body">
                  <div class="table-responsive">
                      <table class="table table-sm activate-select dt-responsive nowrap w-100" id="layerTable">
                          <thead class="thead-light">
                              <tr class="text-center">
                                <th>#</th>
                                <th>NIK</th>
                                <th>Name</th>
                                <th>PT</th>
                                <th>Area</th>
                                <th>BU</th>
                                <th>Superior</th>                                 
                                <th class="sorting_1">Actions</th>
                              </tr>
                          </thead>
                          <tbody>
                            @foreach($approvalLayers as $index => $approvalLayer)
                                <tr>
                                    <td>
                                        {{ $index + 1 }}
                                    </td>
                                    <td>{{ $approvalLayer->employee_id }}</td>
                                    <td>{{ $approvalLayer->fullname }}</td>
                                    <td>{{ $approvalLayer->contribution_level_code }}</td>
                                    <td>{{ $approvalLayer->office_area }}</td>
                                    <td>{{ $approvalLayer->group_company }}</td>
                                    <td>
                                        @php
                                            $layersArray = explode('|', $approvalLayer->layers);
                                            $approverNamesArray = explode('|', $approvalLayer->approver_names);
                                        @endphp
                                        @foreach($layersArray as $index => $layer)
                                        @if(isset($approverNamesArray[$index]) && !empty($approverNamesArray[$index]))
                                            {{ "L".$layer }} : {{ $approverNamesArray[$index] }}<br>
                                        @else
                                            {{ "L".$layer }} : No approver available<br>
                                        @endif
                                    @endforeach
                                    </td>
                                    <td class="text-center sorting_1">
                                        <button type="button" class="btn btn-sm btn-outline-warning open-edit-modal mb-1"
                                        data-bs-employee-id="{{ $approvalLayer->employee_id }}"
                                        data-bs-fullname="{{ $approvalLayer->fullname }}"
                                        data-bs-app="{{ $approvalLayer->approver_ids }}"
                                        data-bs-layer="{{ $approvalLayer->layers }}"
                                        data-bs-app-name="{{ $approvalLayer->approver_names }}"
                                        title="Edit"><i class="ri-edit-box-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary open-view-modal mb-1" title="History"
                                            onclick="viewHistory('{{ $approvalLayer->employee_id }}')">
                                            <i class="ri-history-line"></i>
                                        </button>
                                    </td>
                              </tr>
                              @endforeach
                          </tbody>
                      </table>
                  </div>
              </div>
            </div>
          </div>
      </div>
    </div>

<!-- Modal -->
<div class="modal fade" id="editModal" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="editModalLabel">Update Superior</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form for editing employee details -->
                <form id="editForm" action="{{ route('update-layer') }}" method="POST">
                    @csrf
                    <input type="hidden" name="employee_id" id="employee_id">
                    <div class="row">
                        <label class="col-auto col-form-label">Employee</label>
                        <div class="col">
                            <input type="text" class="form-control" id="fullname" name="fullname" readonly>
                        </div>
                    </div>
                    <hr>
                    <div id="viewlayer">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="submitButton" class="btn btn-primary"><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- importModal -->
<div class="modal fade" id="importModal" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="importModalLabel">Import Superior</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" action="{{ route('import-layer') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col">
                            <div class="mb-4 mt-2">
                                <label class="form-label" for="excelFile">Upload Excel File</label>
                                <input type="file" class="form-control" id="excelFile" name="excelFile" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="mb-2">
                                <label class="form-label" for="fullname">Download Templete here : </label>
                                <a href="{{ asset('storage/templates/template_import_layer_goal.xls') }}" class="badge-outline-primary p-1" download><i class="ri-file-text-line me-1"></i>Import_Excel_Template</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="importButton" class="btn btn-primary"><span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>Import Data</button>
            </div>
        </div>
    </div>
</div>

<!-- view history -->
<div class="modal fade" id="viewModal" role="dialog" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-full-width" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="viewModalLabel">View History</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table dt-responsive table-hover" id="historyTable" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr class="text-center">
                            <th>#</th>
                            <th>Name</th>
                            <th>Superior</th>
                            <th>Updated By</th>
                            <th>Updated At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Rows will be added dynamically using JavaScript -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    var employeesData = {!! json_encode($employees) !!};
</script>
@endpush