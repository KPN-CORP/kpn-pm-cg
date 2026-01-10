@extends('layouts_.vertical', ['page_title' => 'Reports'])

@section('css')
@endsection

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <div class="row">
                <div class="col">
                  <div class="row justify-content-between align-items-start">
                    <div class="col-md-auto">
                      <div class="mb-3">
                          <label class="form-label" for="report_type">Select Report</label>
                          <select class="form-select border-dark-subtle" onchange="reportType(this.value)">
                          <option value="">- select -</option>
                          <option value="Goal">Detailed Goals</option>
                          </select>
                      </div> 
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md col-lg-4">
                        <div class="input-group flex-nowrap mb-3">
                          <label class="input-group-text border-dark-subtle" for="customsearch"><i class="ri-search-line"></i></label>
                          <input type="text" name="customsearch" id="customsearch" class="form-control border-dark-subtle" placeholder="search.." aria-label="search" aria-describedby="search">
                          <div class="d-md-none input-group-append">
                            <button class="input-group-text bg-white border-dark-subtle" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><i class="ri-filter-line"></i></button>
                          </div>
                        </div>
                    </div>
                    <div class="col-sm-auto d-none d-md-inline">
                      <div class="mb-3">
                        <button class="input-group-text bg-white border-dark-subtle" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><i class="ri-filter-line me-1"></i>Filters</button>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-md-auto d-flex">
                  <div class="mb-3 align-items-end">
                    <form id="exportForm" action="{{ route('export') }}" method="POST">
                      @csrf
                      <input type="hidden" name="export_report_type" id="export_report_type">
                      <input type="hidden" name="export_group_company" id="export_group_company">
                      <input type="hidden" name="export_company" id="export_company">
                      <input type="hidden" name="export_location" id="export_location">
                      <input type="hidden" name="export_period" id="export_period">
                      <a id="export" onclick="exportExcel()" class="btn btn-outline-secondary shadow disabled"><i class="ri-download-cloud-2-line me-1"></i><span>{{ __('Download') }}</span></a>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div id="report_content">
              <div class="row">
                <div class="col-md-12">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        {{ __('No Report Found. Please Select Report') }}
                    </div>
                </div>
                </div>
              </div>
          </div>
          <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel" aria-modal="false" role="dialog">
            <div class="offcanvas-header">
                <h5 id="offcanvasRightLabel">Filters</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div> <!-- end offcanvas-header-->

            <div class="offcanvas-body">
              <form id="report_filter" action="{{ url('/get-report-content') }}" method="POST">
                @csrf
                <input type="hidden" id="report_type" name="report_type">
                <div class="container-card">
                    <div class="row">
                        <div class="col">
                            <div class="mb-3">
                              @php
                                  $filterYear = request('filterYear');
                              @endphp
                                <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                                <select name="filterYear" id="filterYear" class="form-select" @style('width: 120px')>
                                    <option value="">{{ __('select all') }}</option>
                                    @foreach ($selectYear as $year)
                                        <option value="{{ $year->year }}" {{ $year->year == $filterYear ? 'selected' : '' }}>{{ $year->year }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
              </form>
            </div> <!-- end offcanvas-body-->
            <div class="offcanvas-footer p-3 text-end">
              <button type="button" id="offcanvas-cancel" class="btn btn-outline-secondary me-2" data-bs-dismiss="offcanvas">{{ __('Cancel') }}</button>
              <button type="submit" class="btn btn-primary" form="report_filter">Apply</button>
            </div>
        </div>
        </div>
      </div>
    </div>
  @endsection