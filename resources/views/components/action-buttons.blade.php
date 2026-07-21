@forelse ($team->contributors as $contributor)
    @if (!$team->calibrationCheck)
        <a href="{{ route('appraisals-360.review', encrypt($team->employee->employee_id)) }}" title="{{ __('Revise') }}" type="button" class="btn btn-outline-info btn-sm m-1 me-0"><i class="ri-edit-line"></i></a>
    @endif
    @if ($contributor->status != 'Draft')
        <a href="{{ route('appraisals-task.detail', encrypt($contributor->id)) }}" title="{{ __('Details') }}" type="button" class="btn btn-outline-secondary btn-sm m-1"><i class="ri-file-text-line"></i></a>
    @endif
    {{-- @if (strtolower($contributor->status) == 'approved')
        <a href="{{ route('performance-review.form', ["contributor_id" => encrypt($contributor->id), "period" => $contributor->period]) }}" title="{{ __('Performance Review') }}" type="button" class="btn btn-outline-warning btn-sm m-1"><i class="ri-clipboard-line"></i></a>
    @endif --}}
@empty
    @if ($team->layer_type === 'manager' && empty(json_decode($team->approvalRequest, true)))
        <a href="{{ route('appraisals-task.initiate', encrypt($team->employee->employee_id)) }}" type="button" class="btn btn-outline-primary btn-sm m-1">{{ __('Initiate') }}</a>
    @else
        <a href="{{ route('appraisals-360.review', encrypt($team->employee->employee_id)) }}" type="button" class="btn btn-outline-warning btn-sm m-1">{{ __('Review') }}</a>
    @endif
@endforelse
