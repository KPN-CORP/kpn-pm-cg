@extends('layouts_.vertical', ['page_title' => 'KPI Company'])

@section('css')
@endsection

@section('content')
    <div class="container-fluid">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible border-0 fade show" role="alert">
                <button type="button" class="btn-close btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <strong>Success - </strong> {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible border-0 fade show" role="alert">
                <button type="button" class="btn-close btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                <strong>Error - </strong> {{ session('error') }}
            </div>
        @endif
        <div class="row">
            <div class="col-10">
            <div class="col-md-auto">
              <div class="mb-3">
                <div class="input-group" style="width: 30%;">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-dark-subtle"><i class="ri-search-line"></i></span>
                  </div>
                  <input type="text" name="customsearch" id="customsearch" class="form-control  border-dark-subtle border-left-0" placeholder="search.." aria-label="search" aria-describedby="search">
                </div>
              </div>
            </div>
            </div>
            <div class="col-2" style="text-align:right">
                <button type="button" class="btn btn-primary shadow" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
            </div>
        </div>
        
        <div class="row">
          <div class="col-md-12">
            <div class="card shadow mb-4">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="card-title">KPI Company Achievement Import History</h3>
                </div>
                  <div class="table-responsive">
                      <table class="table table-hover dt-responsive nowrap" id="importTable" width="100%" cellspacing="0">
                          <thead class="thead-light">
                              <tr class="text-center">
                                  <th>No</th>
                                  <th>Import Date</th>
                                  <th>Results</th>
                                  <th>File</th>
                              </tr>
                          </thead>
                          <tbody>
                            @foreach ($imports as $index => $import)
                                <tr>
                                    <td class="text-center" style="width:5%">{{ $index + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($import->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>
                                        <span class="badge bg-success">Success: {{$import->success}}</span>
                                        @if ($import->error > 0)
                                            <span class="badge bg-danger">Error: {{$import->error}}</span>
                                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalInfo{{$index}}">
                                                <i class="ri-information-line text-danger"></i>
                                            </a>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ asset('storage/' . $import->file_uploads) }}" class="btn btn-primary btn-sm" target="_blank">Download</a>
                                    </td>
                                </tr>
                                <!--Modal Info-->
                                <div class="modal fade" id="modalInfo{{$index}}" tabindex="-1" aria-labelledby="modalInfoLabel" aria-hidden="true">
                                  <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="modalInfoLabel">KPI Company Import Errors</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                        <div class="modal-body">
                                            @if (is_string($import->detail_error) && json_decode($import->detail_error))
                                                @php
                                                    $detailErrors = json_decode($import->detail_error, true);
                                                @endphp
                                        
                                                @if (is_array($detailErrors))
                                                    <table class="table table-sm table-striped">
                                                        <thead>
                                                            <tr>
                                                                <th>Row</th>
                                                                <th>Employee ID</th>
                                                                <th>Error Message</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                        @foreach ($detailErrors as $error)
                                                            <tr>
                                                                @if (is_array($error))
                                                                    <td>{{ $error['row'] ?? 'N/A' }}</td>
                                                                    <td>{{ $error['employee_id'] ?? 'N/A' }}</td>
                                                                    <td>{{ $error['message'] ?? 'No details provided.' }}</td>
                                                                @else
                                                                    <td colspan="3">{{ $error }}</td>
                                                                @endif
                                                            </tr>
                                                        @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    @if(is_array($import->detail_error))
    <ul>
        @foreach($import->detail_error as $err)
            <li>
                Row {{ $err['row'] ?? '-' }} |
                NIK: {{ $err['employee_id'] ?? '-' }} |
                {{ $err['message'] ?? 'Unknown error' }}
            </li>
        @endforeach
    </ul>
@else
    <span>-</span>
@endif
                                                @endif
                                            @else
                                                                                            @if(is_array($import->detail_error))
                                                <ul>
                                                    @foreach($import->detail_error as $err)
                                                        <li>
                                                            Row {{ $err['row'] ?? '-' }} |
                                                            NIK: {{ $err['employee_id'] ?? '-' }} |
                                                            {{ $err['message'] ?? 'Unknown error' }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span>-</span>
                                            @endif
                                            @endif
                                        </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                            @endforeach
                        </tbody>
                      </table>
                  </div>
              </div>
            </div>
          </div>
      </div>

      <!-- Modal Pop-Up Import -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Import KPI Company Achievement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form action="{{ route('importKpiCompany') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-body">
                            <div class="row">
                                <div class="col">
                                    <div class="alert alert-info">
                                        <strong>Notes:</strong>
                                        <ul class="mb-0">
                                            <li>Import KPI Company Achievement data to update employee achievements</li>
                                            <li>Headers required: Employee_ID, KPI_Description, Target, UOM, Weightage, Type, Period</li>
                                            <li>Optional headers: Achievement (can be added later)</li>
                                            <li>Employee IDs must exist in HCIS database</li>
                                            <li>Duplicate entries (same employee + KPI + period) will be updated</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <a href="{{ route('downloadTemplateImportKPICompany') }}" class="btn btn-outline-info">
                                    <i class="bi bi-download"></i> Download Template Import
                                </a>
                            </div>
                            <div class="form-group mb-3">
                                <label for="period" class="form-label">Period (Year)</label>
                                <input type="number" name="period" id="period" class="form-control" min="2000" max="2100" value="{{ date('Y') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="file" class="form-label">Upload File (Excel)</label>
                                <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.csv,.xls" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Import Achievement Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#importTable').DataTable({
                responsive: true,
                ordering: true,
                order: [[1, 'desc']]
            });
        });
    </script>
@endpush
