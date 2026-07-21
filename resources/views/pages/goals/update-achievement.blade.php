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
    <div class="row align-items-center mb-4 mt-3">
        <div class="col">
            <h4 class="m-0 font-weight-bold text-primary">Update Achievement - Goal 2026</h4>
            <p class="text-muted m-0"><small>Please enter your actual achievements in numeric format.</small></p>
        </div>
        <div class="col-auto">
            <a href="#" class="btn btn-outline-secondary shadow-sm">
                <i class="ri-arrow-left-line align-middle me-1"></i> Back
            </a>
        </div>
    </div>

    <form action="#" method="POST">
        @csrf
        @method('PUT')

        <div class="card shadow-sm border-0 bg-light pt-3 pb-4">
            <div class="mx-3">

                @php
                    $dummyData = [
                        'company' => [
                            [
                                'original_index' => 0,
                                'kpi' => 'Clinker Production',
                                'target' => '7100000',
                                'uom' => 'Tons/year',
                                'type' => 'Higher Better',
                                'weightage' => '8',
                                'achievement' => '',
                                'description' => 'Memastikan produksi clinker mencapai target tahunan tanpa ada kendala operasional mayor.'
                            ],
                            [
                                'original_index' => 1,
                                'kpi' => 'Cement Production',
                                'target' => '5100000',
                                'uom' => 'Tons/year',
                                'type' => 'Higher Better',
                                'weightage' => '8',
                                'achievement' => '',
                                'description' => 'Memenuhi demand pasar domestik dengan kualitas semen sesuai standar.'
                            ]
                        ],
                        'division' => [
                            [
                                'original_index' => 2,
                                'kpi' => 'Zero Fatality Incident',
                                'target' => '0',
                                'uom' => 'Case',
                                'type' => 'Lower Better',
                                'weightage' => '15',
                                'achievement' => '',
                                'description' => 'Menjaga lingkungan kerja tetap aman dan meminimalisir angka kecelakaan tambang.'
                            ]
                        ],
                        'personal' => [
                            [
                                'original_index' => 3,
                                'kpi' => 'Penyelesaian Modul Performance Dialog',
                                'target' => '100',
                                'uom' => '%',
                                'type' => 'Higher Better',
                                'weightage' => '10',
                                'achievement' => '',
                                'description' => 'Mendevelop fitur My History & Task Box tepat waktu dan bug-free.'
                            ]
                        ]
                    ];

                    $clusterTitles = [
                        'company' => 'Company Goals',
                        'division' => 'Division Goals',
                        'personal' => 'Personal Goals'
                    ];

                    $clusterWeights = [
                        'company' => 40.0,
                        'division' => 30.0,
                        'personal' => 30.0
                    ];
                @endphp

                @foreach(['company', 'division', 'personal'] as $clusterKey)
                    @if(isset($dummyData[$clusterKey]))

                        <div class="header-cluster px-4 py-3 {{ $loop->first ? '' : 'mt-4' }}">
                            <h6 class="m-0 fw-bold text-uppercase text-cluster">
                                {{ $clusterTitles[$clusterKey] }} : {{ number_format($clusterWeights[$clusterKey], 1) }}%
                            </h6>
                        </div>

                        <div class="bg-white border-start border-end border-bottom shadow-sm">
                            @foreach ($dummyData[$clusterKey] as $index => $data)
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
                <a href="#" class="btn btn-light border shadow-sm me-2">Cancel</a>
                <button type="submit" class="btn btn-success shadow-sm px-4">
                    <i class="ri-save-line align-middle me-1"></i> Save Achievement
                </button>
            </div>

        </div>
    </form>
</div>
@endsection
