<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">
    {{-- @if(session('system') == 'kpnpm') --}}
    <!-- Brand Logo Light -->
    <a href="{{ Url('/') }}" class="logo logo-light">
        <span class="logo-lg">
            <img src="{{ asset('storage/img/logo.png') }}" alt="logo">
        </span>
        <span class="logo-sm">
            <img src="{{ asset('storage/img/logo-sm.png') }}" alt="small logo">
        </span>
    </a>

    <!-- Brand Logo Dark -->
    <a href="{{ Url('/') }}" class="logo logo-dark">
        <span class="logo-lg">
            <img src="{{ asset('storage/img/logo-dark.png') }}" alt="logo">
        </span>
        <span class="logo-sm">
            <img src="{{ asset('storage/img/logo-sm.png') }}" alt="small logo">
        </span>
    </a>
    
    <!-- Sidebar Hover Menu Toggle Button -->
    <div class="button-sm-hover" data-bs-toggle="tooltip" data-bs-placement="right" title="Show Full Sidebar">
        <i class="ri-checkbox-blank-circle-line align-middle"></i>
    </div>

    <!-- Full Sidebar Menu Close Button -->
    {{-- <div class="button-close-fullsidebar">
        <i class="ri-close-fill align-middle"></i>
    </div> --}}

    <!-- Sidebar -left -->
    <div class="h-100" id="leftside-menu-container" data-simplebar>
        <!--- Sidemenu -->
        <ul class="side-nav">

            <li class="side-nav-title">Navigation</li>
            @if(auth()->id() == '23886')
                <li class="side-nav-item">
                    <a href="{{ route('dashboard') }}" onclick="showLoader()" class="side-nav-link d-none">
                        <i class="ri-dashboard-line"></i>
                        <span> Dashboard </span>
                    </a>
                </li>
            @endif
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarGoals" aria-expanded="false" aria-controls="sidebarGoals" class="side-nav-link">
                    <i class="ri-focus-2-line"></i>
                    <span>{{ __('Goal') }}</span>
                    @if ($notificationGoal)
                        <span class="badge bg-danger float-end">{{ $notificationGoal }}</span>    
                    @else
                        <span class="menu-arrow"></span>  
                    @endif
                </a>
                <div class="collapse" id="sidebarGoals">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('goals') }}">{{ __('My Goal') }}</a>
                        </li>
                        @if(auth()->user()->isApprover())
                        <li>
                            <a href="{{ route('team-goals') }}">{{ __('Task Box') }}<span class="badge bg-danger float-end {{ $notificationGoal ? '' : 'd-none' }}">{{ $notificationGoal }}</span></a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @if ($appraisalPeriod)
                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#sidebarAppraisal" aria-expanded="false" aria-controls="sidebarAppraisal" class="side-nav-link">
                        <i class="ri-list-check-3"></i>
                        <span>{{ __('Appraisal') }}</span>
                        <span class="badge bg-danger float-end {{ $notificationAppraisal ? '' : 'd-none' }}">{{ $notificationAppraisal }}</span>    
                        <span class="menu-arrow {{ $notificationAppraisal ? 'd-none' : '' }}"></span>  
                    </a>
                    <div class="collapse" id="sidebarAppraisal">
                        <ul class="side-nav-second-level">
                            <li>
                                <a href="{{ route('appraisals') }}">{{ __('My Appraisal') }}</a>
                            </li>
                            <li>
                                <a href="{{ route('appraisals-task') }}">{{ __('Task Box') }}<span class="badge bg-danger float-end {{ $notificationAppraisal ? '' : 'd-none' }}">{{ $notificationAppraisal }}</span></a>
                            </li>
                        </ul>
                    </div>
                </li>
                @if(auth()->user()->isCalibrator() && $userRatingAccess())
                <li class="side-nav-item">
                    <a href="{{ route('rating') }}" onclick="showLoader()" class="side-nav-link">
                        <i class="ri-star-line"></i>
                        <span> Rating </span>
                    </a>
                </li>
                @endif
                @if($flowAccess('Propose 360'))
                    <li class="side-nav-item d-none">
                        <a href="{{ route('proposed360') }}" onclick="showLoader()" class="side-nav-link">
                            <i class="ri-team-line"></i>
                            <span> {{ __('Propose 360') }} </span>
                            {{-- <span class="badge bg-danger float-end">{{ $notificationProposed360 }}</span>     --}}
                        </a>
                    </li>
                @endif
            @endif
            @if (auth()->user()->isApprover())
            <li class="side-nav-item">
                <a href="{{ url('/reports') }}" class="side-nav-link">
                    <i class="ri-file-chart-line"></i>
                    <span>{{ __('Report') }}</span>
                </a>
            </li>
            @endif
            <li class="side-nav-item">
                <a href="{{ url('/guides') }}" class="side-nav-link">
                    <i class="ri-file-text-line"></i>
                    <span> User Guide </span>
                </a>
            </li>

            @if(auth()->check())
                @can('adminmenu')
                <li class="side-nav-title">Admin</li>
                <li class="side-nav-item d-none">
                    <a href="{{ route('admin-tasks') }}" class="side-nav-link">
                        <i class="ri-task-line"></i>
                        <span> Admin Tasks </span>
                    </a>
                </li>
                @can('viewsetting')
                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#sidebarSettings" aria-expanded="false" aria-controls="sidebarSettings" class="side-nav-link">
                        <i class="ri-user-settings-line"></i>
                        <span> Admin Settings </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarSettings">
                        <ul class="side-nav-second-level">
                            <li class="side-nav-item">
                                <a data-bs-toggle="collapse" href="#sidebarTS" aria-expanded="false" aria-controls="sidebarTS">
                                    <span> Target Setting </span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <div class="collapse" id="sidebarTS">
                                    <ul class="side-nav-third-level">
                                        @can('viewlayer')
                                        <li>
                                            <a href="{{ route('layer') }}">{{ 'Layer' }}</a>
                                        </li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                            <li class="side-nav-item">
                                <a data-bs-toggle="collapse" href="#sidebarPA" aria-expanded="false" aria-controls="sidebarPA">
                                    <span> Performance Appraisal </span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <div class="collapse" id="sidebarPA">
                                    <ul class="side-nav-third-level">
                                        @can('layerpa')
                                        <li>
                                            <a href="{{ route('layer-appraisal') }}">{{ 'Layer' }}</a>
                                        </li>
                                        @endcan
                                        @can('mastercalibration')
                                        <li>
                                            <a href="{{ route('admcalibrations') }}">Calibration</a>
                                        </li>
                                        @endcan
                                        @can('masterrating')
                                        <li>
                                            <a href="{{ route('admratings') }}">Rating</a>
                                        </li>
                                        @endcan
                                        @can('masterweightage')
                                        <li>
                                            <a href="{{ route('admin-weightage') }}">Weightage</a>
                                        </li>
                                        @endcan
                                        @can('master360')
                                        <li>
                                            <a href="{{ route('admin-master360') }}">Master 360</a>
                                        </li>
                                        @endcan
                                    </ul>
                                </div>
                            </li>
                            @can('viewrole')
                            <li>
                                <a href="{{ route('roles') }}">Roles & Permissions</a>
                            </li>
                            @endcan
                            @can('viewschedule')
                            <li>
                                <a href="{{ route('schedules') }}">Schedule</a>
                            </li>
                            @endcan

                            @can('importgoals')
                            <li>
                                <a href="{{ route('importg') }}">Import Goals</a>
                            </li>
                            @endcan
                            @can('importkpi')
                            <li>
                                <a href="{{ route('importkpi') }}">Import Quartal Achievement</a>
                            </li>
                            @endcan
                            @can('reminderpa')
                            <li>
                                <a href="{{ route('reminderpaindex') }}">Reminder PA</a>
                            </li>
                            @endcan
                            @can('viewflowsetting')
                            <li class="side-nav-item">
                                <a data-bs-toggle="collapse" href="#sidebarFlow" aria-expanded="false" aria-controls="sidebarFlow">
                                    <span> Flow Builder </span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <div class="collapse" id="sidebarFlow">
                                    <ul class="side-nav-third-level">
                                        <li>
                                            <a href="{{ route('flows.index') }}">Flows</a>
                                        </li>
                                        <li>
                                            <a href="{{ route('approval-flow.index') }}">Approval Flow</a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                            <li class="side-nav-item">
                                <a href="{{ route('assignments.index') }}">Assignments</a>

                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan
                @can('viewonbehalf')
                <li class="side-nav-item">
                    <a href="{{ route('onbehalf') }}" class="side-nav-link">
                        <i class="ri-group-line"></i>
                        <span> On Behalfs </span>
                    </a>
                </li>
                @endcan
                @can('viewreport')
                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#sidebarReports" aria-expanded="false" aria-controls="sidebarReports" class="side-nav-link">
                        <i class="ri-file-chart-line"></i>
                        <span> {{ __('Report') }} </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarReports">
                        <ul class="side-nav-second-level">
                            <li>
                                <a href="{{ route('admin.reports') }}" onclick="showLoader()">{{ __('Report') }}</a>
                            </li>
                            @can('reportpa')
                            <li>
                                <a href="{{ route('admin.appraisal') }}" onclick="showLoader()">{{ __('Appraisal') }}</a>
                            </li>
                            @endcan
                        </ul>
                    </div>
                </li>
                @endcan
                @can('viewimport')
                <li class="side-nav-item">
                    <a data-bs-toggle="collapse" href="#sidebarImports" aria-expanded="false" aria-controls="sidebarImports" class="side-nav-link">
                        <i class="ri-file-chart-line"></i>
                        <span> Imports </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse" id="sidebarImports">
                        <ul class="side-nav-second-level">
                            @can('importgoals')
                            <li>
                                <a href="{{ route('importg') }}">{{ __('Import Goals') }}</a>
                            </li>
                            @endcan
                            @if (auth()->user()->hasRole('superadmin'))
                            <li>
                                <a href="{{ route('importRating') }}">{{ __('Import Rating') }}</a>
                            </li>
                            @endif
                        </ul>
                    </div>
                </li>
                @endcan
                @if (auth()->user()->hasRole('superadmin'))
                    @can('viewaudittrail')
                    <li class="side-nav-item">
                        <a href="{{ route('audit-trail') }}" class="side-nav-link">
                            <i class="ri-list-check-2"></i>
                            <span> Audit Trail </span>
                        </a>
                    </li>
                    @endcan
                @endif
                @endcan
            @endif

        </ul>
        <!--- End Sidemenu -->

        <div class="clearfix"></div>
    </div>
</div>
<!-- ========== Left Sidebar End ========== -->
