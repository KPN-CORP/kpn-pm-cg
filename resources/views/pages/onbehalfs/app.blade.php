@extends('layouts_.vertical', ['page_title' => 'On Behalf'])

@section('css')
<style>
  textarea.form-control {
    overflow: hidden; /* Prevent scrollbars from appearing */
    resize: none; /* Disable manual resizing by the user */
}
</style>
@endsection

@section('content')
<div class="mandatory-field">
            <div id="alertField" class="alert alert-danger alert-dismissible {{ Session::has('error') ? '':'fade' }}" role="alert" {{ Session::has('error') ? '':'hidden' }}>
                <strong>{!! Session::get('error') !!}{!! Session::get('errorMessage') !!}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
<meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Begin Page Content -->
    <div class="container-fluid">
      <div class="card">
        <div class="card-body">
            <div class="row">
              <div class="col-lg">
                <div class="mb-3">
                  <label class="form-label" for="category">Select Category :</label>
                  <select name="category" id="category" onchange="changeCategory(this.value)" class="form-select border-dark-subtle" @style('width: 120px')>
                      <option value="">- select -</option>
                      <option value="Goals">Goals</option>
                      <option value="Appraisal">Appraisal</option>
                      <option value="Rating">Rating</option>
                  </select>
                </div>
              </div>
            </div>
          <div class="row">
            <div class="col-md-auto">
              <div class="mr-4 d-md-block d-none">
                <button class="input-group-text bg-white border-dark-subtle" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasRight" aria-controls="offcanvasRight"><i class="ri-filter-line me-1"></i>Filters</button>
              </div>
            </div>
            <div class="col-md-auto">
              <div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-dark-subtle"><i class="ri-search-line"></i></span>
                  </div>
                  <input type="text" name="customsearch" id="customsearch" class="form-control border-left-0 border-dark-subtle" placeholder="search.." aria-label="search" aria-describedby="search">
                  <div class="d-sm-none input-group-append">
                    <a href="#" class="input-group-text bg-white border-dark-subtle" data-bs-toggle="modal" data-bs-target="#modalFilter"><i class="ri-filter-line"></i></a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
        <!-- Content Row -->
        <div id="contentOnBehalf">
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

        <div class="offcanvas offcanvas-end" tabindex="-1"  id="offcanvasRight" aria-labelledby="offcanvasRightLabel" aria-modal="false" role="dialog">
        <div class="offcanvas-header">
            <h4 id="offcanvasRightLabel">Filters</h4>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div> <!-- end offcanvas-header-->

        <div class="offcanvas-body">
          <form id="onbehalf_filter" action="{{ route('admin.onbehalf.content') }}" method="POST">
            @csrf
            <input type="hidden" id="filter_category" name="filter_category">
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="group_company">Group Company</label>
                            <select class="form-select select2" name="group_company[]" id="group_company" multiple>
                                @foreach ($groupCompanies as $groupCompany)
                                <option value="{{ $groupCompany }}">{{ $groupCompany }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="company">Company</label>
                            <select class="form-select select2" name="company[]" id="company" multiple>
                                @foreach ($companies as $company)
                                <option value="{{ $company->contribution_level_code }}">{{ $company->contribution_level . ' (' . $company->contribution_level_code . ')' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="mb-3">
                            <label class="form-label" for="location">Location</label>
                            <select class="form-select select2" name="location[]" id="location" multiple>
                                @foreach ($locations as $location)
                                <option value="{{ $location->work_area_code }}">{{ $location->office_area.' ('.$location->group_company.')' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
          </form>
        </div> <!-- end offcanvas-body-->
        <div class="offcanvas-footer p-3 text-end">
          <a class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">{{ __('Cancel') }}</a>
          <button type="submit" class="btn btn-primary" form="onbehalf_filter">Apply</button>
        </div>
    </div>
    </div>
    @endsection
      
@push('scripts')
 <script>
(function(){
  const CSRF = '{{ csrf_token() }}';

  async function postRevoke(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
      },
      body: new URLSearchParams(payload || {})
    });
    const data = await res.json().catch(()=>({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.message || 'Revoke gagal.');
    }
    return data;
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-revoke');
    if (!btn) return;

    e.preventDefault();

    const url    = btn.getAttribute('data-url');
    const rowSel = btn.getAttribute('data-row');
    const empId  = btn.getAttribute('data-emp');

    // Reason wajib
    const { value: reason } = await Swal.fire({
      title: 'Revoke Appraisal?',
      input: 'text',
      inputLabel: 'Reason',
      inputPlaceholder: 'Type a reason...',
      inputAttributes: { maxlength: 250, autocapitalize: 'off' },
      showCancelButton: true,
      confirmButtonText: 'Revoke',
      confirmButtonColor: '#dc3545',
      inputValidator: (v) => {
        if (!v || !v.trim()) return 'Reason wajib diisi';
        return undefined;
      },
    });
    if (reason === undefined) return; // user cancel

    // prevent double-click
    btn.disabled = true;

    try {
      const resp = await postRevoke(url, { reason: reason.trim() });

      // Hapus baris (vanilla)
      const tr = document.querySelector(rowSel);
      if (tr) tr.remove();

      Swal.fire({ icon: 'success', title: 'Revoked', text: resp.message || 'Appraisal revoked successfully.' });
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Revoke failed.' });
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>

@endpush