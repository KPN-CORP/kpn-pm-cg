<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion toggled" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="{{ url('/') }}">
        <div class="sidebar-brand-icon">
            <img @style('width: 30px;') src="{{ asset('storage/img/logos/kpn.png') }}" alt="kpn logo">
        </div>
        <div class="sidebar-brand-text mx-3">Performance Management</div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    <div class="sidebar-heading">
        Menu
    </div>

    {{-- <li class="nav-item">
        <a class="nav-link" href="{{ route('tasks') }}">
            <i class="fas fa-fw fa-tasks"></i>
            <span>Tasks</span></a>
    </li> --}}
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseGoals"
            aria-expanded="true" aria-controls="collapseGoals">
            <i class="fas fa-fw fa-flag-checkered"></i>
            <span>Goals</span>
        </a>
        <div id="collapseGoals" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{ route('goals') }}">{{ __('My Goal') }}</a>
                @if(auth()->user()->isApprover())
                <a class="collapse-item" href="{{ route('team-goals') }}">{{ __('Task Box') }}</a>
                @endif
                {{-- <a class="collapse-item" href="{{ route('roles') }}">Role</a> --}}
            </div>
        </div>
    </li>

    {{-- <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>{{ __('Appraisal') }}</span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Calibration</span></a>
    </li> --}}
    <li class="nav-item">
        @if (auth()->user()->isApprover())
            <a class="nav-link" href="{{ url('/reports') }}">
                <i class="fas fa-fw fa-file-alt"></i>
                <span>{{ __('Report') }}</span>
            </a>
        @endif
    </li>


    <!-- Divider -->
    <hr class="sidebar-divider">

    <!-- Heading -->
    @if(auth()->check())
    @can('adminmenu')

    <div class="sidebar-heading">
        Admin
    </div>

    @can('viewsetting')
    <li class="nav-item">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseSettings"
            aria-expanded="true" aria-controls="collapseSettings">
            <i class="fas fa-fw fa-cog"></i>
            <span>Settings</span>
        </a>
        <div id="collapseSettings" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                @can('viewschedule')
                    <a class="collapse-item" href="{{ route('schedules') }}">Schedule</a>
                @endcan
                @can('viewlayer')
                    <a class="collapse-item" href="{{ route('layer') }}">Layer</a>
                @endcan
                @can('viewrole')
                    <a class="collapse-item" href="{{ route('roles') }}">Role</a>
                @endcan
                {{-- <a class="collapse-item" href="{{ route('employees') }}">Employee</a> --}}
            </div>
        </div>
    </li>
    @endcan
    @can('viewonbehalf')
    <li class="nav-item">
        <a class="nav-link" href="{{ url('/admin/onbehalf') }}">
            <i class="fas fa-fw fa-user-friends"></i>
            <span>On Behalf</span>
        </a>
    </li>
    @endcan
    @can('viewreport')
    <li class="nav-item">
        <a class="nav-link" href="{{ url('/admin/reports') }}">
            <i class="fas fa-fw fa-file-alt"></i>
            <span>{{ __('Report') }}</span>
        </a>
    </li>
    @endcan
    @can('viewreport')
    <li class="nav-item">
        <a class="nav-link" href="{{ url('/admin-appraisal') }}">
            <i class="fas fa-fw fa-file-alt"></i>
            <span>{{ __('Appraisal') }}</span>
        </a>
    </li>
    @endcan


    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">
    @endcan
    @endif

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>
