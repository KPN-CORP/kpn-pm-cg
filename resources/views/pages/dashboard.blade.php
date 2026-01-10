@extends('layouts_.vertical', ['page_title' => 'Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['resources/js/app.js'])
    <style>
        .kpi-card:hover { transform: translateY(-2px); transition: .2s; box-shadow: 0 6px 18px rgba(0,0,0,.06); }
        .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; }
        .progress { height:8px; }
        .status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px; }
        .badge-filter a { text-decoration: none; }
    </style>
@endsection

@section('content')
@php
    // ===== Dummy data (tanpa controller) =====
    $employee = (object)[
        'name' => 'Alfian N. F. Azis',
        'position' => 'Software Engineer',
        'unit' => 'HC Information System (CRPHC_ISD)',
        'period' => '2025',
        'avatar' => 'https://i.pravatar.cc/100?img=12',
    ];

    $kpis = [
        ['kpi'=>'Cycle Time Ticket Incident','target'=>'≤ 2 hari','progress'=>72,'weight'=>20,'status'=>'On Track'],
        ['kpi'=>'SLA Deployment Minor','target'=>'≥ 95%','progress'=>88,'weight'=>25,'status'=>'On Track'],
        ['kpi'=>'Dokumentasi Teknis','target'=>'8 modul','progress'=>50,'weight'=>15,'status'=>'At Risk'],
        ['kpi'=>'Optimization Cost Cloud','target'=>'-10%','progress'=>30,'weight'=>20,'status'=>'Behind'],
        ['kpi'=>'Uptime Core App','target'=>'≥ 99.5%','progress'=>93,'weight'=>20,'status'=>'On Track'],
    ];

    $tasks = [
        ['title'=>'Lengkapi evidence KPI "SLA Deployment Minor"','due'=>'12 Oct 2025','ref'=>'TASK-1092','cat'=>'Goals'],
        ['title'=>'Verifikasi progress "Dokumentasi Teknis"','due'=>'14 Oct 2025','ref'=>'TASK-1099','cat'=>'Goals'],
        ['title'=>'Baca feedback manager pada PA Mid-Year','due'=>'Today','ref'=>'TASK-1104','cat'=>'PA'],
        ['title'=>'Cek status usulan rekan (Proposed 360)','due'=>'15 Oct 2025','ref'=>'TASK-1110','cat'=>'Proposed 360'],
    ];

    $activeCat   = request('cat'); // null | Goals | PA | Proposed 360
    $catCounts   = collect($tasks)->groupBy('cat')->map->count(); // hanya kategori yang ada task
    $filtered    = $activeCat ? collect($tasks)->where('cat', $activeCat)->values() : collect($tasks);
    $baseUrl     = url()->current();

    $hasSubordinates = true;
    $teamMembers = [
        ['name'=>'Dewi A.','position'=>'Backend Dev','avatar'=>'https://i.pravatar.cc/100?img=32','progress'=>80,'status'=>'On Track'],
        ['name'=>'Raka P.','position'=>'Frontend Dev','avatar'=>'https://i.pravatar.cc/100?img=15','progress'=>60,'status'=>'At Risk'],
        ['name'=>'Salsa M.','position'=>'QA Engineer','avatar'=>'https://i.pravatar.cc/100?img=5','progress'=>90,'status'=>'On Track'],
    ];

    $avgProgress = (int) round(collect($kpis)->avg('progress'));

    // === Tambahan: agregasi untuk doughnut ===
    $kpiStatusCounts = collect($kpis)->groupBy('status')->map->count(); // ex: On Track, At Risk, Behind
    // urutan label yang konsisten
    $kpiLabels = ['On Track','At Risk','Behind'];
    $kpiSeries = collect($kpiLabels)->map(fn($l) => (int) ($kpiStatusCounts[$l] ?? 0));

    $taskCatCounts = collect($tasks)->groupBy('cat')->map->count(); // ex: Goals, PA, Proposed 360
    $taskLabels = array_values($taskCatCounts->keys()->toArray());
    $taskSeries = array_values($taskCatCounts->values()->toArray());
@endphp

<!-- Start Content-->
<div class="container-fluid">

    {{-- Top: title + daterange (sesuai template) --}}
    <div class="row">
        <div class="col-12 mb-3">
            <div class="page-title-box">
                <div class="page-title-right m-0">
                    <form class="d-flex">
                        <div class="input-group">
                            <input type="text" class="form-control shadow border-0" id="dash-daterange">
                            <span class="input-group-text bg-success border-success text-white">
                                <i class="ri-calendar-todo-fill fs-13"></i>
                            </span>
                        </div>
                        <a href="javascript:void(0);" class="btn btn-success ms-2 flex-shrink-0">
                            <i class="ri-refresh-line"></i> Refresh
                        </a>
                    </form>
                </div>
                <div class="d-flex align-items-center mt-2">
                    <img src="{{ $employee->avatar }}" class="avatar me-2" alt="avatar">
                    <div>
                        <div class="fw-bold">{{ $employee->name }}</div>
                        <small class="text-muted">{{ $employee->position }} · {{ $employee->unit }} · Period {{ $employee->period }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 1: CTA + ringkasan KPI + ringkasan Task --}}
    <div class="row">
        <div class="col-xl-4 col-lg-6">
            <div class="card cta-box overflow-hidden">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div>
                            <h3 class="mt-0 fw-normal cta-box-title mb-3">
                                Pantau <b>KPI</b> & <b>Tasks</b> Anda secara real-time
                            </h3>
                            <a href="#" class="link-success link-offset-3 fw-bold">Lihat Panduan KPI
                                <i class="ri-arrow-right-line"></i></a>
                        </div>
                        <img class="ms-3" src="/images/svg/email-campaign.svg" width="92" alt="Illustration">
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary: Total KPI + Avg Progress (chart placeholder) --}}
        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <h5 class="text-uppercase fs-13 mt-0 text-truncate">Total KPI</h5>
                            <h2 class="my-2 py-1">{{ count($kpis) }}</h2>
                            <p class="mb-0 text-muted text-truncate">
                                <span class="text-success me-2"><i class="ri-arrow-up-line"></i> {{ $avgProgress }}%</span>
                                <span class="text-nowrap">Avg progress</span>
                            </p>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <div id="kpi-summary-chart" data-colors="#16a7e9"></div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        </div>

        {{-- Summary: Tasks Pending (chart placeholder) --}}
        <div class="col-xl-4 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <h5 class="text-uppercase fs-13 mt-0 text-truncate">Tasks Pending</h5>
                            <h2 class="my-2 py-1">{{ $filtered->count() }}</h2>
                            <p class="mb-0 text-muted text-truncate">
                                <span class="text-muted">Kategori: {{ $activeCat ?: 'All' }}</span>
                            </p>
                        </div>
                        <div class="col-6">
                            <div class="text-end">
                                <div id="task-summary-chart" data-colors="#47ad77"></div>
                            </div>
                        </div>
                    </div>
                </div> 
            </div>
        </div>
    </div>

    {{-- Row 2: My KPIs (cards) + Task List (dengan badge filter kategori) + Recent Activities --}}
    <div class="row">
        {{-- My KPIs (cards) --}}
        <div class="col-xl-8 col-lg-12">
            <div class="card">
                <div class="d-flex card-header justify-content-between align-items-center">
                    <h4 class="header-title mb-0">My KPIs</h4>
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle arrow-none card-drop p-0" data-bs-toggle="dropdown">
                            <i class="ri-more-2-fill"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="javascript:void(0);" class="dropdown-item">Export</a>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row">
                        @foreach($kpis as $row)
                            @php
                                $badge = ['On Track'=>'success','At Risk'=>'warning','Behind'=>'danger'][$row['status']] ?? 'secondary';
                                $dot   = ['On Track'=>'#28a745','At Risk'=>'#ffc107','Behind'=>'#dc3545'][$row['status']] ?? '#6c757d';
                            @endphp
                            <div class="col-md-6 col-lg-6 mb-3">
                                <div class="card kpi-card border-0">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-1">{{ $row['kpi'] }}</h6>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Target: {{ $row['target'] }}</small>
                                            <small class="text-muted">{{ $row['weight'] }}%</small>
                                        </div>
                                        <div class="progress mt-2 mb-2">
                                            <div class="progress-bar bg-{{ $badge }}" style="width: {{ $row['progress'] }}%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><span class="status-dot" style="background: {{ $dot }}"></span>{{ $row['status'] }}</span>
                                            <span class="text-muted small">{{ $row['progress'] }}%</span>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent border-top-0 text-end">
                                        <a href="#" class="btn btn-sm btn-outline-secondary">Detail</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if(empty($kpis))
                            <div class="col-12 text-center text-muted">Belum ada KPI.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Task List + filter kategori (badge muncul hanya bila ada task kategori tsb) --}}
        <div class="col-xl-4 col-lg-12">
            <div class="card">
                <div class="d-flex card-header justify-content-between align-items-center">
                    <h4 class="header-title mb-0">My Tasks</h4>
                    <a href="{{ $baseUrl }}" class="btn btn-sm btn-light {{ $activeCat ? '' : 'fw-bold' }}">All</a>
                </div>

                @if($catCounts->isNotEmpty())
                <div class="px-3 pt-2 pb-0">
                    <div class="badge-filter">
                        @foreach($catCounts as $cat => $count)
                            <a href="{{ $baseUrl.'?cat='.urlencode($cat) }}"
                               class="badge badge-pill me-1 {{ $activeCat === $cat ? 'bg-primary text-white' : 'bg-light text-body' }}">
                                {{ $cat }} ({{ $count }})
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered table-hover table-borderless mb-0">
                            <thead class="border-top border-bottom bg-light-subtle border-light">
                                <tr>
                                    <th>Task</th>
                                    <th style="width: 30%;">Info</th>
                                    <th style="width: 1%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filtered as $t)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $t['title'] }}</div>
                                            <small class="text-muted">Kategori: {{ $t['cat'] }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">Due: {{ $t['due'] }}</small><br>
                                            <small class="text-muted">Ref: {{ $t['ref'] }}</small>
                                        </td>
                                        <td class="text-end">
                                            <a href="#" class="btn btn-sm btn-outline-secondary">Detail</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">Tidak ada task pada kategori ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div> 
            </div>

            {{-- Recent Activities (ringkas) --}}
            <div class="card">
                <div class="d-flex card-header justify-content-between align-items-center">
                    <h4 class="header-title mb-0">Recent Activities</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-2">
                        <span class="badge bg-light text-body me-2">Today 09:20</span>
                        <div>Submit self-review untuk KPI "SLA Deployment Minor"</div>
                    </div>
                    <div class="d-flex mb-2">
                        <span class="badge bg-light text-body me-2">Yesterday 16:05</span>
                        <div>Update progress "Dokumentasi Teknis" dari 30% → 50%</div>
                    </div>
                    <div class="d-flex">
                        <span class="badge bg-light text-body me-2">07 Oct 2025</span>
                        <div>Komentar dari Manager pada "Optimization Cost Cloud"</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Row 3: My Team (muncul jika punya bawahan) --}}
    @if($hasSubordinates)
    <div class="row">
        <div class="col-xxl-12">
            <div class="card">
                <div class="d-flex card-header justify-content-between align-items-center">
                    <h4 class="header-title mb-0">My Team</h4>
                    <a href="javascript:void(0);" class="btn btn-sm btn-light">Export <i class="ri-download-line ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-centered table-hover table-borderless mb-0">
                            <thead class="border-top border-bottom bg-light-subtle border-light">
                                <tr>
                                    <th>Member</th>
                                    <th>Role</th>
                                    <th style="width: 35%;">Progress</th>
                                    <th>Status</th>
                                    <th style="width: 1%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($teamMembers as $m)
                                    @php $badge = ['On Track'=>'success','At Risk'=>'warning','Behind'=>'danger'][$m['status']] ?? 'secondary'; @endphp
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="{{ $m['avatar'] }}" class="avatar me-2" alt="avatar">
                                                <div class="fw-bold">{{ $m['name'] }}</div>
                                            </div>
                                        </td>
                                        <td>{{ $m['position'] }}</td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: {{ $m['progress'] }}%"></div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-{{ $badge }}">{{ $m['status'] }}</span></td>
                                        <td class="text-end">
                                            <a href="#" class="btn btn-sm btn-outline-secondary">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                                @if(empty($teamMembers))
                                    <tr><td colspan="5" class="text-muted">Belum ada data tim.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div> 
            </div>
        </div>
    </div>
    @endif

</div>
<!-- container -->
@endsection

@push('scripts')
    {{-- ApexCharts (pakai CDN untuk cepat). Jika sudah include di layout, hapus baris ini. --}}
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
      (function () {
        // Util warna dari data-colors (fallback ke default)
        function getColors(el, fallback) {
          var colors = (el.getAttribute('data-colors') || fallback || '#16a7e9').split(',');
          return colors.map(function(c){ return c.trim(); });
        }

        // ===== Doughnut: Total KPI (by Status) =====
        var kpiEl = document.getElementById('kpi-summary-chart');
        if (kpiEl) {
          var kpiOptions = {
            chart: { type: 'donut', height: 160, sparkline: { enabled: true } },
            labels: @json($kpiLabels),
            series: @json($kpiSeries),
            colors: getColors(kpiEl, '#28a745,#ffc107,#dc3545'),
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 2 },
            tooltip: { y: { formatter: (v) => v + ' KPI' } },
            plotOptions: {
              pie: { donut: { size: '70%' } }
            }
          };
          new ApexCharts(kpiEl, kpiOptions).render();
        }

        // ===== Doughnut: Tasks Pending (by Category) =====
        var taskEl = document.getElementById('task-summary-chart');
        if (taskEl) {
          var taskOptions = {
            chart: { type: 'donut', height: 160, sparkline: { enabled: true } },
            labels: @json($taskLabels),
            series: @json($taskSeries),
            colors: getColors(taskEl, '#16a7e9,#47ad77,#ffc35a,#f15776'),
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 2 },
            tooltip: { y: { formatter: (v) => v + ' task' } },
            plotOptions: {
              pie: { donut: { size: '70%' } }
            }
          };
          new ApexCharts(taskEl, taskOptions).render();
        }
      })();
    </script>
@endpush
