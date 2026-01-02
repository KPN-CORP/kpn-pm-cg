<div class="form-group mb-2">

    {{-- Hidden Form Name --}}
    <input type="hidden"
           name="formData[{{ $formIndex }}][formName]"
           value="{{ $name }}">

    @php
        $lang = session('locale')
            ? session('locale')
            : env('APP_LOCALE', env('APP_FALLBACK_LOCALE'));
    @endphp

    {{-- ============================= --}}
    {{-- RATING GUIDANCE --}}
    {{-- ============================= --}}
    <div class="alert alert-light border mb-4 fs-14 d-none">
        <strong>Rating Guidance</strong>
        <ul class="mb-0 mt-2">
            @foreach ($ratings as $rating)
                <li>
                    <strong>{{ $rating['value'] }}</strong> :
                    {{ $lang === 'id' ? $rating['desc_idn'] : $rating['desc_eng'] }}
                </li>
            @endforeach
        </ul>
    </div>

    {{-- ============================= --}}
    {{-- SIGAP VALUES --}}
    {{-- ============================= --}}
    @foreach($data as $groupIndex => $group)

        <div class="mb-5">

            {{-- VALUE TITLE --}}
            <h4 class="fw-bold mb-1">{{ $group['title'] }}</h4>

            {{-- VALUE DEFINITION --}}
            @if(!empty($group['definition']))
                <p class="text-muted fs-14 mb-4">
                    {!! $group['definition'] !!}
                </p>
            @endif

            {{-- BEHAVIORS --}}
            <div class="card mb-3 shadow-sm">
                <div class="card-body">

                    {{-- Behavior --}}
                    <h6 class="fw-semibold mb-3">
                        {{ $group['title'] }}
                    </h6>

                    {{-- LEVEL DESCRIPTIONS --}}
                    <ul class="list-unstyled fs-14 mb-3">
                        @foreach($group['items'] as $level => $desc)
                            <li class="mb-2">
                                <strong>{{ $level }}</strong> :
                                {{ $lang === 'id'
                                    ? $desc['desc_idn']
                                    : $desc['desc_eng'] }}
                            </li>
                        @endforeach
                    </ul>

                    {{-- SCORE --}}
                    <div class="text-end">
                        <select
                            class="form-select form-select-sm w-auto d-inline"
                            name="formData[{{ $formIndex }}][{{ $groupIndex }}][score]"
                            required
                        >
                            <option value="">Select</option>

                            @foreach($ratings as $rating)
                                <option
                                    value="{{ $rating['value'] }}"
                                    {{ isset($group['score'])
                                        && (
                                            (isset($isManager) && $isManager)
                                            || ($contributorTransaction ?? false)
                                            || ($sefInitiate ?? false)
                                        )
                                        && $group['score'] == $rating['value']
                                        ? 'selected' : '' }}
                                >
                                    {{ $rating['value'] }}
                                </option>
                            @endforeach
                        </select>

                        <div class="text-danger fs-12 error-message"></div>
                    </div>

                </div>
            </div>

        </div>

    @endforeach
</div>


{{-- ============================= --}}
{{-- CLIENT SIDE VALIDATION --}}
{{-- ============================= --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        let valid = true;

        document.querySelectorAll('select[required]').forEach(function (el) {
            const error = el.parentElement.querySelector('.error-message');
            if (!el.value) {
                valid = false;
                error.textContent = 'Required';
            } else {
                error.textContent = '';
            }
        });

        if (!valid) {
            e.preventDefault();
        }
    });
});
</script>
@endpush
