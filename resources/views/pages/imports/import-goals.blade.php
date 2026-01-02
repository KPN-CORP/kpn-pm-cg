@extends('layouts_.vertical', ['page_title' => 'Goals'])

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
                    <h3 class="card-title"></h3>
                    
                </div>
                  <div class="table-responsive">
                      <table class="table table-hover dt-responsive nowrap" id="scheduleTable" width="100%" cellspacing="0">
                          <thead class="thead-light">
                              <tr class="text-center">
                                  <th>No</th>
                                  <th>Submit Date</th>
                                  <th>Detail</th>
                                  <th>File Upload</th>
                              </tr>
                          </thead>
                          <tbody>
                            @foreach ($goals_imports as $index => $import)
                                <tr>
                                    <td class="text-center" style="width:5%">{{ $index + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($import->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>
                                        Success : {{$import->success}}, Error : {{$import->error}} 
                                        @if ($import->error > 0)
                                            <!-- Icon Information if errors are present -->
                                            <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalInfo{{$index}}"><i class="ri-information-line text-danger"></i></a>
                                        @endif
                                    </td>
                                    <td>
                                        <!-- Display the uploaded file (assuming there's a column 'file_uploads' for file path) -->
                                        <a href="{{ asset('storage/' . $import->file_uploads) }}" class="btn btn-primary btn-sm" target="_blank">Download</a>
                                    </td>
                                </tr>
                                <!--Modal Info-->
                                <div class="modal fade" id="modalInfo{{$index}}" tabindex="-1" aria-labelledby="modalInfoLabel" aria-hidden="true">
                                  <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                      <div class="modal-header">
                                        <h5 class="modal-title" id="modalInfoLabel">Goals Import Error Employee ID's</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                      </div>
                                        <div class="modal-body">
                                            @if (is_string($import->detail_error) && json_decode($import->detail_error))
                                                @php
                                                    $detailErrors = json_decode($import->detail_error, true);
                                                @endphp
                                        
                                                @if (is_array($detailErrors))
                                                    <ul>
                                                        @foreach ($detailErrors as $error)
                                                            @if (is_array($error)) {{-- Format Baru --}}
                                                                <li>
                                                                    <strong>Employee ID:</strong> {{ $error['employee_id'] ?? 'N/A' }} - 
                                                                    <strong>Message:</strong> {{ $error['message'] ?? 'No details provided.' }}
                                                                </li>
                                                            @else {{-- Format Lama --}}
                                                                <li>
                                                                    <strong>Employee ID:</strong> {{ $error }}
                                                                </li>
                                                            @endif
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    {{ $import->detail_error }}
                                                @endif
                                            @else
                                                {{ $import->detail_error }}
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

      <!-- Modal Pop-Up -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Import Goals</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs" id="importTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="regular-tab" data-bs-toggle="tab" data-bs-target="#regular" type="button" role="tab" aria-controls="regular" aria-selected="true">Regular Goals</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="clustering-tab" data-bs-toggle="tab" data-bs-target="#clustering" type="button" role="tab" aria-controls="clustering" aria-selected="false">Clustering KPI</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="importTabsContent">
                        <!-- Regular Goals Tab -->
                        <div class="tab-pane fade show active" id="regular" role="tabpanel" aria-labelledby="regular-tab">
                            <form action="{{ route('importgoals') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="alert alert-info">
                                                <strong>Notes:</strong>
                                                <ul class="mb-0">
                                                    <li>Template Import Goals can use from the File Export at menu Reports : <strong>Reports -> Detailed Goals -> Download</strong></li>
                                                    <li>Headers required: Employee_ID, Employee_Name, KPI, Target, UOM, Weightage, Type, Description, Current Approver ID, Period</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="file">Upload File</label>
                                        <input type="file" name="file" id="file" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Import Regular Goals</button>
                                </div>
                            </form>
                        </div>

                        <!-- Clustering KPI Tab -->
                        <div class="tab-pane fade" id="clustering" role="tabpanel" aria-labelledby="clustering-tab">
                            <form action="{{ route('importClusteringKPI') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col">
                                            <div class="alert alert-info">
                                                <strong>Notes:</strong>
                                                <ul class="mb-0">
                                                    <li>Import Clustering KPI with cluster information (company, division, personal)</li>
                                                    <li>Headers required: Employee_ID, KPI, Target, UoM, Weightage, Type, Period, Cluster</li>
                                                    <li>Optional headers: Employee_Name, Achievement</li>
                                                    <li>Current Approver ID always defaults to 'admin'</li>
                                                    <li>Achievement field will be mapped to KPI description</li>
                                                    <li>If UoM doesn't match available options, it will be set to "Other" and the value stored in custom_uom</li>
                                                    <li>Weightage validation is not enforced (each cluster can have different weightage distribution)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="file">Upload File</label>
                                        <input type="file" name="file" id="clustering_file" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Import Clustering KPI</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')

@endpush