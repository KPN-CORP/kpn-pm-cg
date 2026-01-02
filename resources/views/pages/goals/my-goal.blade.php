@extends('layouts_.vertical', ['page_title' => 'Goals'])

@section('css')
<style>
.goal-card {
    overflow: hidden;
    transition:
        opacity 0.25s ease-in-out,
        transform 0.25s ease-in-out,
        max-height 0.75s cubic-bezier(0.4, 0, 0.2, 1),
        margin 0.25s,
        padding 0.25s;
    will-change: opacity, transform, max-height;
    opacity: 1;
    transform: translateY(0);
    max-height: 5000px; /* fallback for large content, can be overridden inline */
}

.goal-card.is-hiding {
    opacity: 0;
    transform: translateY(16px);
    max-height: 0 !important;
    margin: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    pointer-events: none;
}

.goal-card.is-showing {
    opacity: 1;
    transform: translateY(0);
    max-height: 5000px; /* or set via JS for dynamic content */
    padding-top: 1rem;
    padding-bottom: 1rem;
    pointer-events: auto;
}

.goal-card.is-gone {
    display: none !important;
}
</style>
@endsection

@section('content')
    <!-- Begin Page Content -->
    <div class="container-fluid">
        <!-- Page Heading -->
        <div class="mandatory-field">
            <div id="alertField" class="alert alert-danger alert-dismissible {{ Session::has('error') ? '':'fade' }}" role="alert" {{ Session::has('error') ? '':'hidden' }}>
                <strong>{{ Session::get('error')['message'] ?? null }}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <form id="formYearGoal" action="{{ route('goals') }}" method="GET">
            @php
                $filterYear = request('filterYear');
            @endphp
            <div class="row align-items-end">
                <div class="col-auto">
                    <div class="mb-3">
                        <label class="form-label" for="filterYear">{{ __('Year') }}</label>
                        <select name="filterYear" id="filterYear" onchange="filterGoals(this.value)" 
                                class="form-select border-secondary" style="width: 180px">
                        <option value="">{{ __('select all') }}</option>
                        @foreach ($selectYear as $year)
                            <option value="{{ $year->year }}" {{ $year->year == $filterYear ? 'selected' : '' }}>
                            {{ $year->year }}
                            </option>
                        @endforeach
                        </select>
                    </div>
                </div>
                <div class="col">
                    <div class="mb-3 text-end">
                        <a href="{{ $access ? route('goals.form', encrypt(Auth::user()->employee_id)) : '#' }}" onclick="showLoader()" class="btn {{ $access ? 'btn-primary shadow' : 'btn-secondary-subtle disabled' }}">{{ __('Create Goal') }}</a>
                    </div>
                </div>
            </div>
        </form>
        @forelse ($data as $goalIndex => $row)
            @php
                // Assuming $dateTimeString is the date string '2024-04-29 06:52:40'
                $formData = json_decode($row->request->goal['form_data'], true);
                // Group by cluster for backward compatibility
                $groupedFormData = [];
                foreach ($formData as $item) {
                    $cluster = $item['cluster'] ?? 'personal';
                    $groupedFormData[$cluster][] = $item;
                }
            @endphp
            <div class="row">
                <div class="col-md-12">
                <div class="card shadow p-0 goal-card" data-year="{{ $row->request->period }}">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between pb-0">
                        <h4 class="m-0 font-weight-bold text-primary">{{ __('Goal') }} {{ $row->request->period }}</h4>
                        @if ($period == $row->request->goal->period && !$row->request->appraisalCheck && $access)
                            @if (Auth::user()->employee_id == $row->request->initiated->employee_id)
                                @if (
                                    $row->request->goal->form_status != 'Draft' && 
                                    $row->request->created_by == Auth::user()->id
                                )
                                    <a class="btn btn-outline-warning fw-semibold" 
                                    href="{{ route('goals.edit', $row->request->goal->id) }}" 
                                    onclick="showLoader()">
                                    {{ __('Revise Goals') }}
                                    </a>
                                @elseif (
                                    $row->request->goal->form_status == 'Draft' || 
                                    ($row->request->status == 'Pending' && count($row->request->approval) == 0) || 
                                    $row->request->sendback_to == $row->request->employee_id
                                )
                                    <a class="btn btn-outline-warning fw-semibold" 
                                    href="{{ route('goals.edit', $row->request->goal->id) }}" 
                                    onclick="showLoader()">
                                    {{ $row->request->status === 'Sendback' ? __('Revise Goals') : __('Edit') }}
                                    </a>
                                @endif
                            @else
                                <!-- Hide the button if the current user is not the initiated employee -->
                                <span class="d-none"></span>
                            @endif
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg col-sm-12">
                                <div id="alertDraft" class="alert alert-danger alert-dismissible {{ $row->request->goal->form_status == 'Draft' ? '':'fade' }}" role="alert" {{ $row->request->goal->form_status == 'Draft' ? '':'hidden' }}>
                                    <div class="row text-primary fs-5 align-items-center">
                                        <div class="col-auto my-auto">
                                            <i class="ri-error-warning-line h3 fw-light"></i>
                                        </div>
                                        <div class="col p-0">
                                            <strong>{{ $period == $row->request->goal->period && !$row->request->appraisalCheck && $access ? __('Draft Goal Alert Message Open') : __('Draft Goal Alert Message Closed') }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row px-2">
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Initiated By') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->request->initiated->name.' ('.$row->request->initiated->employee_id.')' }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Initiated Date') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->request->formatted_created_at }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Last Updated On') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->request->formatted_updated_at }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>{{ __('Adjusted By') }}</h5>
                                <p class="mt-2 mb-0 text-muted">{{ $row->request->updatedBy ? $row->request->updatedBy->name.' '.$row->request->updatedBy->employee_id : '-' }}{{ $row->request->updated_by != auth()->user()->id && empty($adjustByManager) && auth()->check() && auth()->user()->roles->isNotEmpty() && $period == $row->request->goal->period && $row->request->initiated->employee_id != $row->request->employee_id ? ' (Admin)': '' }}</p>
                            </div>
                            <div class="col-lg col-sm-12 p-2">
                                <h5>Status</h5>
                                <div>
                                    <a href="javascript:void(0)" data-bs-id="{{ $row->request->employee_id }}" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-content="{{ $row->request->goal->form_status == 'Draft' ? 'Draft' : ($row->approvalLayer && $row->request->goal->form_status == 'Approved' ? 'Manager L'.$row->approvalLayer.' : '.$row->name : 'Goals were auto-approved after you submitted PA '.$row->request->period ) }}" class="badge {{ $row->request->goal->form_status == 'Draft' || $row->request->sendback_to == $row->request->employee_id ? 'bg-secondary' : ($row->request->appraisalCheck ? ($row->request->status === 'Approved' ? 'bg-success' : 'text-bg-light' ) : 'bg-warning')}} rounded-pill py-1 px-2">
                                        {{ $row->request->goal->form_status == 'Draft' ? 'Draft': ($row->request->status == 'Approved' ? __('Approved') : ($row->request->appraisalCheck ? 'Auto Approved' : ($row->request->sendback_to == $row->request->employee_id ? 'Waiting For Revision' : __($row->request->status)))) }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        @if ($row->request->sendback_messages && $row->request->sendback_to == $row->request->employee_id && !$row->request->appraisalCheck)
                            <hr class="mt-2 mb-2">
                            <div class="row p-2">
                                <div class="col-lg col-sm-12 px-2">
                                    <div class="form-group">
                                        <h5>Revision Notes :</h5>
                                        <p class="mt-1 mb-0 text-muted">{{ $row->request->sendback_messages }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col text-end">
                                <a data-bs-toggle="collapse" href="#collapse{{ $goalIndex }}" aria-expanded="true" aria-controls="collapse{{ $goalIndex }}">
                                    Detail <i class="ri-arrow-down-s-line"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="collapse" id="collapse{{ $goalIndex }}" style="">
                        <div class="card-body p-0">
                            <table class="table table-striped table-bordered m-0">
                                <tbody>
                                @if ($groupedFormData)
                                @foreach(['company' => 'Company Goals', 'division' => 'Division Goals', 'personal' => 'Personal Goals'] as $cluster => $title)
                                  @if(!empty($groupedFormData[$cluster]))
                                    <tr>
                                      <td colspan="5" class="bg-light">
                                        <strong>{{ $title }} :</strong>
                                      </td>
                                    </tr>
                                    @foreach ($groupedFormData[$cluster] as $index => $data)
                                    <tr>
                                        <td scope="row">
                                            <div class="row p-2">
                                                <div class="col-lg-4 col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>KPI {{ $index + 1 }}</h5>
                                                        <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['kpi'] }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>Target</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ $data['target'] }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>{{ __('Uom') }}</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ is_null($data['custom_uom']) ? $data['uom'] : $data['custom_uom'] }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>{{ __('Type') }}</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ ucwords($data['type']) }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-lg col-sm-12 p-2">
                                                    <div class="form-group">
                                                        <h5>{{ __('Weightage') }}</h5>
                                                        <p class="mt-1 mb-0 text-muted">{{ $data['weightage'] }}%</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row p-2">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <h5>{{ __('Description') }}</h5>
                                                        <p class="mt-1 mb-0 text-muted" @style('white-space: pre-line')>{{ $data['description'] ?? '-' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                  @endif
                                @endforeach
                                @else
                                <tr>
                                    <td colspan="5" class="text-center">No goals data available</td>
                                </tr>
                                @endif 
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col text-end">
                                    <a data-bs-toggle="collapse" href="#collapse{{ $goalIndex }}" aria-expanded="true" aria-controls="collapse{{ $goalIndex }}">
                                        Close <i class="ri-arrow-up-s-line"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        @empty
            <div class="row">
                <div class="col-md-12">
                <div class="card shadow mb-4">
                    <div class="card-body">
                        {{ __('No Goals Found. Please Create Your Goals ') }}<i class="ri-arrow-right-up-line"></i>
                    </div>
                </div>
                </div>
            </div>
        @endforelse
    </div>
    @endsection
   @push('scripts')
@if(Session::has('error'))
<script>
  document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
      icon: "error",
      title: "{{ Session::get('error')['title'] }}",
      text: "{{ Session::get('error')['message'] }}",
      confirmButtonText: "OK",
    });
  });
</script>
@endif

<script>
  function hideCard(card) {
    if (card.classList.contains('is-gone')) return;

    card.classList.remove('is-showing');
    card.classList.add('is-hiding');

    const onEnd = (e) => {
      if (e.propertyName !== 'max-height') return;
      card.classList.add('is-gone');
      card.removeEventListener('transitionend', onEnd);
    };
    card.addEventListener('transitionend', onEnd);
  }

  function showCard(card) {
    card.classList.remove('is-gone');

    // force reflow biar transisi jalan
    card.offsetHeight;

    card.classList.remove('is-hiding');
    card.classList.add('is-showing');
  }

  function filterGoals(year) {
    const cards = document.querySelectorAll('.goal-card');
    const selected = (year || '').toString().trim();

    cards.forEach(card => {
      const cardYear = (card.dataset.year || '').toString().trim();
      const shouldShow = !selected || cardYear === selected;

      shouldShow ? showCard(card) : hideCard(card);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('filterYear');
    if (sel) filterGoals(sel.value);
  });

  window.filterGoals = filterGoals;
</script>

@endpush
