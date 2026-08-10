@extends('layouts_.vertical', ['page_title' => 'Update Achievement'])

@section('css')
<style>
.kpi-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 0.5rem;
    display: block;
    letter-spacing: 0.5px;
}
.kpi-value {
    font-size: 0.95rem;
    color: #212529;
}
.header-cluster {
    border: 2px solid #a82727 !important;
    background-color: #ffffff;
}
.text-cluster {
    color: #a82727;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}
</style>
@endsection

@section('content')
<div class="container-fluid pb-5">
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
        </div>
    @endif

    <div class="row align-items-center mb-4 mt-3">
        <div class="col">
            <h4 class="m-0 font-weight-bold text-primary">Update Achievement - Goal 2026</h4>
            <p class="text-muted m-0"><small>Please enter your actual achievements in numeric format.</small></p>
        </div>
        <div class="col-auto">
            <a href="{{ $redirect_back }}" class="btn btn-outline-secondary shadow-sm">
                <i class="ri-arrow-left-line align-middle me-1"></i> Back
            </a>
        </div>
    </div>

    <form action="{{ route('achievement.update') }}" class="needs-validation" method="POST">
        @csrf

        <input type="hidden" class="d-none" name="goal_id" value="{{ $goal_id }}" readonly />
        <div class="card shadow-sm border-0 bg-light pt-3 pb-4">
            <div class="mx-3">
                @foreach(['company', 'division', 'personal'] as $clusterKey)
                    @if(isset($grouped_form_data[$clusterKey]))

                        <div class="header-cluster px-4 py-3 {{ $loop->first ? '' : 'mt-4' }}">
                            <h6 class="m-0 fw-bold text-uppercase text-cluster">
                                {{ $cluster_titles[$clusterKey] }} : {{ number_format($cluster_weights[$clusterKey], 1) }}%
                            </h6>
                        </div>

                        <div class="bg-white border-start border-end border-bottom shadow-sm">
                            @foreach ($grouped_form_data[$clusterKey] as $index => $data)
                            <div class="row mx-0 py-4 {{ !$loop->last ? 'border-bottom' : '' }}">

                                <div class="col-12">
                                    <div class="row align-items-start">
                                        <div class="col-lg-3 col-md-6 mb-4 mb-lg-0 px-4">
                                            <span class="kpi-label">KPI {{ $index + 1 }}</span>
                                            <div class="kpi-value">{{ $data['kpi'] }}</div>
                                        </div>

                                        <div class="col-lg-2 col-md-6 mb-4 mb-lg-0 px-4 px-lg-2">
                                            <span class="kpi-label">Target</span>
                                            <div class="kpi-value">{{ $data['target'] }}</div>
                                        </div>

                                        <div class="col-lg-2 col-md-4 mb-4 mb-lg-0 px-4 px-lg-2">
                                            <span class="kpi-label">Satuan Unit</span>
                                            <div class="kpi-value">{{ $data['uom'] }}</div>
                                        </div>

                                        <div class="col-lg-2 col-md-4 mb-4 mb-lg-0 px-4 px-lg-2">
                                            <span class="kpi-label">Tipe</span>
                                            <div class="kpi-value">{{ $data['type'] }}</div>
                                        </div>

                                        <div class="col-lg-1 col-md-4 mb-4 mb-lg-0 px-4 px-lg-2">
                                            <span class="kpi-label">Bobot</span>
                                            <div class="kpi-value">{{ $data['weightage'] }}%</div>
                                        </div>

                                        <div class="col-lg-2 col-md-12 px-4 px-lg-2">
                                            @if ($clusterKey == "company")
                                                <label class="kpi-label text-success" for="achieve_{{ $data['original_index'] }}">
                                                    Achievement
                                                </label>
                                                <input type="number" step="any"
                                                    class="form-control form-control-sm border-success shadow-none fw-bold text-success"
                                                    id="achieve_{{ $data['original_index'] }}"
                                                    name="achievements[{{ $data['original_index'] }}]"
                                                    value="{{ $data['achievement'] }}"
                                                    placeholder="0"
                                                    >
                                            @else
                                                <label class="kpi-label text-success" for="achieve_{{ $data['original_index'] }}">
                                                    Achievement <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" step="any"
                                                    class="form-control form-control-sm border-success shadow-none fw-bold text-success"
                                                    id="achieve_{{ $data['original_index'] }}"
                                                    name="achievements[{{ $data['original_index'] }}]"
                                                    value="{{ $data['achievement'] }}"
                                                    placeholder="0"
                                                    required>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 px-4 mt-4">
                                    <span class="kpi-label">Deskripsi</span>
                                    <div class="text-muted" style="font-size: 0.85rem; line-height: 1.5;">
                                        {{ $data['description'] }}
                                    </div>
                                </div>

                            </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach

            </div>

            <div class="px-4 py-3 text-end mt-4">
                <a href="{{ $redirect_back }}" class="btn btn-light border shadow-sm me-2">Cancel</a>
                <button type="submit" class="btn btn-success shadow-sm px-4 spinner-border spinner-border-sm">
                    <i class="ri-save-line align-middle me-1"></i> Save Achievement
                </button>
            </div>

        </div>
    </form>
</div>
@endsection
