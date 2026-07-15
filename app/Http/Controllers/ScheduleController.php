<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Location;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Permission;
use App\Models\RoleHasPermission;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;
use App\Jobs\SendReminderScheduleEmailJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Spatie\Permission\PermissionRegistrar;

class ScheduleController extends Controller
{
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $roles;

    public function __construct()
    {
        $this->roles = Auth::user()->roles;

        $restrictionData = [];

        if(!$this->roles->isEmpty()){
            $restrictionData = json_decode($this->roles->first()->restriction, true);
        }

        $this->permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $this->permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $this->permissionLocations = $restrictionData['work_area_code'] ?? [];

    }
    function schedule() {

        $userId = Auth::id();
        $parentLink = 'Settings';
        $link = 'Schedule';
        $today = Carbon::today();
        $schedules = Schedule::with('createdBy')->orderBy('created_at', 'desc')->get();
        $inactiveSchedules = Schedule::onlyTrashed()->with('createdBy')->orderBy('deleted_at', 'asc')->orderBy('created_at', 'desc')->get();
        $schedulemasterpa = schedule::where('event_type','masterschedulepa')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();
        $schedulemastergoals = schedule::where('event_type','masterschedulegoals')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();

        $schedulegoals = collect([]); // Default: koleksi kosong jika $schedulemastergoals null

        if ($schedulemastergoals) {
            $schedulegoals = schedule::where('event_type', 'goals')
                ->whereDate('start_date', '>=', $schedulemastergoals->start_date)
                ->whereDate('end_date', '<=', $schedulemastergoals->end_date)
                ->where('created_by', $userId)
                ->orderBy('created_at')
                ->pluck('id');
        }

        //buat validasi agar schedule goals yg bisa di edit hanya goals yg memiliki waktu $schedulemastergoals

        return view('pages.schedules.schedule', [
            'link' => $link,
            'parentLink' => $parentLink,
            'schedules' => $schedules,
            'inactiveSchedules' => $inactiveSchedules,
            'userId' => $userId,
            'schedulemasterpa' => $schedulemasterpa,
            'schedulemastergoals' => $schedulemastergoals,
            'schedulegoals' => $schedulegoals,
        ]);
    }
    function form() {
        $parentLink = 'Setting';
        $link = 'Schedules';
        $sublink = 'Create Schedules';

        $allowedGroupCompanies = collect($this->permissionGroupCompanies);
        if ($allowedGroupCompanies->isEmpty()) {
            $allowedGroupCompanies = collect(Employee::getUniqueGroupCompanies());
        }

        $pcompanies = $this->permissionCompanies;

        if (empty($pcompanies)) {
            $companies = Company::orderBy('contribution_level_code')->get();
        }else{
            $companies = Company::orderBy('contribution_level_code')->whereIn('contribution_level_code',$pcompanies)->get();
        }

        $plocations = $this->permissionLocations;

        if (empty($plocations)) {
            $locations = Location::orderBy('area')->get();
        }else{
            $locations = Location::orderBy('area')->whereIn('work_area',$plocations)->get();
        }

        $today = Carbon::today();
        $schedulemasterpa = schedule::where('event_type','masterschedulepa')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();
        $schedulemastergoals = schedule::where('event_type','masterschedulegoals')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();

        return view('pages.schedules.form', [
            'sublink' => $sublink,
            'link' => $link,
            'parentLink' => $parentLink,
            'locations' => $locations,
            'companies' => $companies,
            'allowedGroupCompanies' => $allowedGroupCompanies,
            'schedulemasterpa' => $schedulemasterpa,
            'schedulemastergoals' => $schedulemastergoals,
        ]);
    }
    function save(Request $req) {
        $link = 'schedule';
        $today = date('Y-m-d');
        $userId = Auth::id();
        $review360 = isset($req->review_360) ? $req->review_360 : 0;
        // Delete previous same event_type if it exists
        if ($req->event_type == 'masterschedulepa' || $req->event_type == 'masterschedulegoals') {
            Schedule::where('event_type', $req->event_type)->delete();
        } else {
            Schedule::where('event_type', $req->event_type)
                ->where('created_by', $userId)
                ->delete();
        }

        $model = new schedule;

        $model->schedule_name       = $req->schedule_name;
        $model->event_type          = $req->event_type;
        $model->schedule_periode    = $req->schedule_periode;
        $model->employee_type       = $req->input('employee_type') ? implode(',', $req->input('employee_type')) : '';
        $model->bisnis_unit         = $req->input('bisnis_unit') ? implode(',', $req->input('bisnis_unit')) : '';
        $model->company_filter      = $req->input('company_filter') ? implode(',', $req->input('company_filter')) : '';
        $model->location_filter     = $req->input('location_filter') ? implode(',', $req->input('location_filter')) : '';
        $model->review_360          = $review360;
        $model->start_join_date     = $req->start_join_date;
        $model->last_join_date      = $req->last_join_date;
        $model->start_date          = $req->start_date;
        $model->end_date            = $req->end_date;
        $model->checkbox_reminder   = isset($req->checkbox_reminder) ? $req->checkbox_reminder : 0;
        $model->created_by          = $userId;

        if ($req->checkbox_reminder == 1) {

            $model->inputState = $req->inputState;

            if ($req->inputState == 'repeaton') {
            $model->repeat_days = $req->repeat_days_selected;
            $model->before_end_date = null;
            } elseif ($req->inputState == 'beforeenddate') {
            $model->repeat_days = null;
            $model->before_end_date = $req->before_end_date;
            }

            $model->messages = $req->messages;
        } else {
            $model->messages = null;
            $model->repeat_days = null;
            $model->inputState = null;
            $model->before_end_date = null;
        }

        $model->save();

        if($req->event_type=="masterschedulepa"){

            //cek id di permission untuk schedulepa & masterschedulepa
            $idpermissions = Permission::where('name','schedulepa')->first();
            $idschedulepa = $idpermissions->id;

            //cek di role has permission yg memiliki akses ke id 6 schedule
            $idroles = RoleHasPermission::where('permission_id', '6')->whereNotIn('role_id',['1','8'])->get();

            //cek tanggal sesudah dan sebelum
            if($req->start_date <= $today && $req->end_date >= $today){

                foreach($idroles as $idrole){
                    //input data di RoleHasPermission dengan permission_id $idschedulepa untuk user yg memiliki akses permission_id=6
                    $existingPermission = RoleHasPermission::where('role_id', $idrole->role_id)
                                               ->where('permission_id', $idschedulepa)
                                               ->first();

                    if (!$existingPermission) {
                        RoleHasPermission::create([
                            'role_id' => $idrole->role_id,
                            'permission_id' => $idschedulepa,
                        ]);
                    }
                }
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }else if($req->event_type=="masterschedulegoals"){

            //cek id di permission untuk goals
            $idpermissions = Permission::where('name','goals')->first();
            $idschedulepa = $idpermissions->id;

            //cek di role has permission yg memiliki akses ke id 6 schedule
            $idroles = RoleHasPermission::where('permission_id', '6')->whereNotIn('role_id',['1','8'])->get();

            //cek tanggal sesudah dan sebelum
            if($req->start_date <= $today && $req->end_date >= $today){

                foreach($idroles as $idrole){
                    //input data di RoleHasPermission dengan permission_id $idschedulepa untuk user yg memiliki akses permission_id=6
                    $existingPermission = RoleHasPermission::where('role_id', $idrole->role_id)
                                               ->where('permission_id', $idschedulepa)
                                               ->first();

                    if (!$existingPermission) {
                        RoleHasPermission::create([
                            'role_id' => $idrole->role_id,
                            'permission_id' => $idschedulepa,
                        ]);
                    }
                }
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }else{
            $query = Employee::query();
            $querypa = EmployeeAppraisal::query();

            if ($model->location_filter) {
                $query->whereIn('work_area_code', explode(',', $model->location_filter));
                $querypa->whereIn('work_area_code', explode(',', $model->location_filter));
            }

            if ($model->company_filter) {
                $query->whereIn('contribution_level_code', explode(',', $model->company_filter));
                $querypa->whereIn('contribution_level_code', explode(',', $model->company_filter));
            }

            if ($model->bisnis_unit) {
                $query->whereIn('group_company', explode(',', $model->bisnis_unit));
                $querypa->whereIn('group_company', explode(',', $model->bisnis_unit));
            }

            if ($model->employee_type) {
                $query->whereIn('employee_type', explode(',', $model->employee_type));
                $querypa->whereIn('employee_type', explode(',', $model->employee_type));
            }

            $employeesToUpdate = $query->get();
            $employeesPAToUpdate = $querypa->get();
            // $employeesToUpdate = $query->where('date_of_joining', '<=', $model->last_join_date)->get();


            if($model->start_date <= $today && $model->end_date >= $today){
                $access_menu = 1;
                $accesspa = 1;
            }else{
                $access_menu = 0;
                $accesspa = 0;
            }

            foreach ($employeesToUpdate as $employee) {
                $doj = 1;

                if (!empty($model->start_join_date) && !empty($model->last_join_date)) {
                    $doj = ($employee->date_of_joining >= $model->start_join_date && $employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                } elseif (!empty($model->start_join_date)) {
                    $doj = ($employee->date_of_joining >= $model->start_join_date) ? 1 : 0;
                } elseif (!empty($model->last_join_date)) {
                    $doj = ($employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                }

                $accessMenuJson = json_decode($employee->access_menu, true);

                if($req->event_type=="goals"){
                    $accessMenuJson['goals'] = $access_menu;
                    $accessMenuJson['doj'] = $doj;
                }

                $updatedAccessMenu = json_encode($accessMenuJson);

                $employee->access_menu = $updatedAccessMenu;
                $employee->save();
            }
            foreach ($employeesPAToUpdate as $employee) {
                $createpa = 1;

                if (!empty($model->start_join_date) && !empty($model->last_join_date)) {
                    $createpa = ($employee->date_of_joining >= $model->start_join_date && $employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                } elseif (!empty($model->start_join_date)) {
                    $createpa = ($employee->date_of_joining >= $model->start_join_date) ? 1 : 0;
                } elseif (!empty($model->last_join_date)) {
                    $createpa = ($employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                }

                $accessMenuJson = json_decode($employee->access_menu, true);

                if($req->event_type=="schedulepa"){
                    $accessMenuJson['accesspa'] = $accesspa;
                    $accessMenuJson['createpa'] = $createpa;
                    $accessMenuJson['review360'] = $review360;
                }

                $updatedAccessMenu = json_encode($accessMenuJson);

                $employee->access_menu = $updatedAccessMenu;
                $employee->save();
            }
        }

        Alert::success('Success');
        return redirect()->intended(route('schedules', absolute: false));
    }
    function edit($encryptedId)
    {
        $id = Crypt::decrypt($encryptedId);
        $parentLink = 'Setting';
        $link = 'Schedules';
        $sublink = 'Update';
        $model = Schedule::find($id);
        $today = Carbon::today();
        $schedulemasterpa = Schedule::where('event_type','masterschedulepa')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();
        $schedulemastergoals = schedule::where('event_type','masterschedulegoals')
                            ->whereDate('start_date', '<=', $today)
                            ->whereDate('end_date', '>=', $today)
                            ->orderBy('created_at')
                            ->first();
        $hidediv=0;
        if($model->event_type=='masterschedulepa' || $model->event_type=='masterschedulegoals'){
            $hidediv=1;
        }else{
            $hidediv=0;
        }

        if(!$model)
            return redirect("schedules");

            return view('pages.schedules.edit', [
                'link' => $link,
                'sublink' => $sublink,
                'parentLink' => $parentLink,
                'model' => $model,
                'schedulemasterpa' => $schedulemasterpa,
                'schedulemastergoals' => $schedulemastergoals,
                'hidediv' => $hidediv,
            ]);
    }
    function update(Request $req) {
        $link = 'schedule';
        $model = Schedule::find($req->id_schedule);
        $review360 = isset($req->review_360) ? $req->review_360 : 0;

        $model->schedule_name       = $req->schedule_name;
        $model->employee_type       = !empty($req->employee_type) ? $req->employee_type : '';
        $model->bisnis_unit         = !empty($req->bisnis_unit) ? $req->bisnis_unit : '';
        $model->company_filter      = !empty($req->company_filter) ? $req->company_filter : '';
        $model->location_filter     = !empty($req->location_filter) ? $req->location_filter : '';
        $model->schedule_periode    = $req->schedule_periode;
        $model->review_360          = $review360;
        $model->start_join_date     = $req->start_join_date;
        $model->last_join_date      = $req->last_join_date;
        $model->start_date          = $req->start_date;
        $model->end_date            = $req->end_date;
        $model->checkbox_reminder   = isset($req->checkbox_reminder) ? $req->checkbox_reminder : 0;

        if ($req->checkbox_reminder == 1) {

            $model->inputState = $req->inputState;

            if ($req->inputState == 'repeaton') {
                $model->repeat_days = $req->repeat_days_selected;
                $model->before_end_date = null;
            } elseif ($req->inputState == 'beforeenddate') {
                $model->repeat_days = null;
                $model->before_end_date = $req->before_end_date;
            }

            $model->messages = $req->messages;
        } else {
            $model->messages = null;
            $model->repeat_days = null;
            $model->inputState = null;
            $model->before_end_date = null;
        }

        $model->save();

        $today = date('Y-m-d');
        if($req->event_type=="Master Schedule PA"){

            //cek id di permission untuk schedulepa & masterschedulepa
            $idpermissions = Permission::where('name','schedulepa')->first();
            $idschedulepa = $idpermissions->id;

            //cek di role has permission yg memiliki akses ke id 6 schedule
            $idroles = RoleHasPermission::where('permission_id', '6')->whereNotIn('role_id',['1','8'])->get();

            //cek tanggal sesudah dan sebelum
            if($req->start_date <= $today && $req->end_date >= $today){

                foreach($idroles as $idrole){
                    //input data di RoleHasPermission dengan permission_id $idschedulepa untuk user yg memiliki akses permission_id=6
                    $existingPermission = RoleHasPermission::where('role_id', $idrole->role_id)
                                               ->where('permission_id', $idschedulepa)
                                               ->first();

                    if (!$existingPermission) {
                        RoleHasPermission::create([
                            'role_id' => $idrole->role_id,
                            'permission_id' => $idschedulepa,
                        ]);
                    }
                }
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }else{
                foreach ($idroles as $idrole) {
                    RoleHasPermission::where('role_id', $idrole->role_id)
                                     ->where('permission_id', $idschedulepa)
                                     ->delete();
                }

                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }else{
            // dd('test');
            $query = Employee::query();
            $querypa = EmployeeAppraisal::query();

            if ($model->location_filter) {
                $query->whereIn('work_area_code', explode(',', $model->location_filter));
                $querypa->whereIn('work_area_code', explode(',', $model->location_filter));
            }

            if ($model->company_filter) {
                $query->whereIn('contribution_level_code', explode(',', $model->company_filter));
                $querypa->whereIn('contribution_level_code', explode(',', $model->company_filter));
            }

            if ($model->bisnis_unit) {
                $query->whereIn('group_company', explode(',', $model->bisnis_unit));
                $querypa->whereIn('group_company', explode(',', $model->bisnis_unit));
            }

            if ($model->employee_type) {
                $query->whereIn('employee_type', explode(',', $model->employee_type));
                $querypa->whereIn('employee_type', explode(',', $model->employee_type));
            }

            //$employeesToUpdate = $query->where('date_of_joining', '<=', $model->last_join_date)->get();
            $employeesToUpdate = $query->get();
            $employeesPAToUpdate = $querypa->get();

            if($model->start_date <= $today && $model->end_date >= $today){
                $access_menu = 1;
                $accesspa = 1;
            }else{
                $access_menu = 0;
                $accesspa = 0;
            }

            foreach ($employeesToUpdate as $employee) {
                if (!empty($model->start_join_date) && !empty($model->last_join_date)) {
                    $doj = ($employee->date_of_joining >= $model->start_join_date && $employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                } elseif (!empty($model->start_join_date)) {
                    $doj = ($employee->date_of_joining >= $model->start_join_date) ? 1 : 0;
                } elseif (!empty($model->last_join_date)) {
                    $doj = ($employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                }

                $accessMenuJson = json_decode($employee->access_menu, true);

                if($req->event_type=="Goals"){
                    $accessMenuJson['goals'] = $access_menu;
                    $accessMenuJson['doj'] = $doj;
                }

                $updatedAccessMenu = json_encode($accessMenuJson);

                $employee->access_menu = $updatedAccessMenu;
                $employee->save();
            }
            foreach ($employeesPAToUpdate as $employee) {
                if (!empty($model->start_join_date) && !empty($model->last_join_date)) {
                    $createpa = ($employee->date_of_joining >= $model->start_join_date && $employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                } elseif (!empty($model->start_join_date)) {
                    $createpa = ($employee->date_of_joining >= $model->start_join_date) ? 1 : 0;
                } elseif (!empty($model->last_join_date)) {
                    $createpa = ($employee->date_of_joining <= $model->last_join_date) ? 1 : 0;
                }

                $accessMenuJson = json_decode($employee->access_menu, true);

                if($req->event_type=="Schedule PA"){
                    $accessMenuJson['accesspa'] = $accesspa;
                    $accessMenuJson['createpa'] = $createpa;
                    $accessMenuJson['review360'] = $review360;
                }

                $updatedAccessMenu = json_encode($accessMenuJson);

                $employee->access_menu = $updatedAccessMenu;
                $employee->save();
            }
        }

        Alert::success('Success');
        return redirect()->intended(route('schedules', absolute: false));
    }
    public function softDelete($id)
    {
        $today = date('Y-m-d');
        $schedule = Schedule::findOrFail($id);

        if ($schedule->event_type == "masterschedulepa") {
            // Handle master schedule deletion
            $idpermissions = Permission::where('name', 'schedulepa')->first();
            $idschedulepa = $idpermissions->id;

            $idroles = RoleHasPermission::where('permission_id', '6')
                                        ->whereNotIn('role_id', ['1', '8'])->get();

            foreach ($idroles as $idrole) {
                RoleHasPermission::where('role_id', $idrole->role_id)
                                ->where('permission_id', $idschedulepa)
                                ->delete();
            }

            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        } else if ($schedule->event_type == "masterschedulegoals") {
            // Handle master schedule deletion
            $idpermissions = Permission::where('name', 'goals')->first();
            $idschedulegoals = $idpermissions->id;

            $idroles = RoleHasPermission::where('permission_id', '6')
                                        ->whereNotIn('role_id', ['1', '8'])->get();

            foreach ($idroles as $idrole) {
                RoleHasPermission::where('role_id', $idrole->role_id)
                                ->where('permission_id', $idschedulegoals)
                                ->delete();
            }

            app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        }else {
            if ($schedule->start_date <= $today && $schedule->end_date >= $today) {
                $query = Employee::query();
                $querypa = EmployeeAppraisal::query();

                if ($schedule->location_filter) {
                    $query->whereIn('work_area_code', explode(',', $schedule->location_filter));
                    $querypa->whereIn('work_area_code', explode(',', $schedule->location_filter));
                }

                if ($schedule->company_filter) {
                    $query->whereIn('contribution_level_code', explode(',', $schedule->company_filter));
                    $querypa->whereIn('contribution_level_code', explode(',', $schedule->company_filter));
                }

                if ($schedule->bisnis_unit) {
                    $query->whereIn('group_company', explode(',', $schedule->bisnis_unit));
                    $querypa->whereIn('group_company', explode(',', $schedule->bisnis_unit));
                }

                if ($schedule->employee_type) {
                    $query->whereIn('employee_type', explode(',', $schedule->employee_type));
                    $querypa->whereIn('employee_type', explode(',', $schedule->employee_type));
                }

                $query = $query; // pastikan ini query builder awal
                if (!empty($schedule->start_join_date) && !empty($schedule->last_join_date)) {
                    $query->whereBetween('date_of_joining', [$schedule->start_join_date, $schedule->last_join_date]);
                } elseif (!empty($schedule->start_join_date)) {
                    $query->where('date_of_joining', '>=', $schedule->start_join_date);
                } elseif (!empty($schedule->last_join_date)) {
                    $query->where('date_of_joining', '<=', $schedule->last_join_date);
                }

                $employeesToUpdate = $query->get();

                // Untuk employeesPA
                $querypa = $querypa; // query builder awal
                if (!empty($schedule->start_join_date) && !empty($schedule->last_join_date)) {
                    $querypa->whereBetween('date_of_joining', [$schedule->start_join_date, $schedule->last_join_date]);
                } elseif (!empty($schedule->start_join_date)) {
                    $querypa->where('date_of_joining', '>=', $schedule->start_join_date);
                } elseif (!empty($schedule->last_join_date)) {
                    $querypa->where('date_of_joining', '<=', $schedule->last_join_date);
                }

                $employeesPAToUpdate = $querypa->get();

                foreach ($employeesToUpdate as $employee) {
                    $accessMenuJson = json_decode($employee->access_menu, true);

                    $accessMenuJson['goals'] = 0;
                    $accessMenuJson['doj'] = 0;

                    $updatedAccessMenu = json_encode($accessMenuJson);

                    $employee->access_menu = $updatedAccessMenu;
                    $employee->save();
                }
                foreach ($employeesPAToUpdate as $employee) {
                    $accessMenuJson = json_decode($employee->access_menu, true);

                    $accessMenuJson['accesspa'] = 0;
                    $accessMenuJson['createpa'] = 0;
                    $accessMenuJson['review360'] = 0;

                    $updatedAccessMenu = json_encode($accessMenuJson);

                    $employee->access_menu = $updatedAccessMenu;
                    $employee->save();
                }
            }
        }

        // Soft delete the schedule
        $schedule->delete();

        // Redirect back with a success message
        return redirect()->route('schedules')->with('success', 'Schedule has been successfully deleted.');
    }

    function reminderDailySchedules() {
        $today = date('Y-m-d');
        $dayOfWeek = now()->format('D');

        $schedules = DB::table('schedules')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->where('checkbox_reminder', '=', 1)
            ->whereNull('deleted_at')
            ->whereIn('event_type', ['schedulepa', 'goals'])
            ->get();

        foreach ($schedules as $schedule) {

            if($schedule->checkbox_reminder=='1'){
                $sendReminder = false;

                if ($schedule->inputState == 'beforeenddate') {
                    $reminderStartDate = Carbon::parse($schedule->end_date)->subDays($schedule->before_end_date);
                    if (Carbon::today()->between($reminderStartDate, Carbon::parse($schedule->end_date))) {
                        $sendReminder = true;
                    }
                } elseif ($schedule->inputState == 'repeaton') {
                    $repeatDays = explode(',', $schedule->repeat_days);
                    if (in_array($dayOfWeek, $repeatDays)) {
                        $sendReminder = true;
                    }
                }

                if ($sendReminder) {
                    if($schedule->event_type=='goals'){
                        $query = Employee::query();
                        $query->doesntHave('goal');
                    }else if($schedule->event_type=='schedulepa'){
                        $query = EmployeeAppraisal::query();
                        $query->where(function ($q) {
                            // Kondisi pertama: karyawan yang tidak memiliki penilaian
                            $q->doesntHave('appraisalpa');

                            // Kondisi kedua: karyawan yang sudah memiliki penilaian tetapi statusnya masih draft
                            $q->orWhereHas('appraisalpa', function($q2) {
                                $q2->where('form_status', 'draft');
                            });
                        });
                    }

                    if ($schedule->employee_type) {
                        $query->whereIn('employee_type', explode(',', $schedule->employee_type));
                    }

                    if ($schedule->bisnis_unit) {
                        $query->whereIn('group_company', explode(',', $schedule->bisnis_unit));
                    }

                    if ($schedule->company_filter) {
                        $query->whereIn('contribution_level_code', explode(',', $schedule->company_filter));
                    }

                    if ($schedule->location_filter) {
                        $query->whereIn('work_area_code', explode(',', $schedule->location_filter));
                    }

                    $query->where('date_of_joining', '<=', $schedule->last_join_date);

                    $query->whereNotIn('job_level', ['9B', '10A', '10B']);

                    $employees = $query->get();
                    // dd($employees);

                    foreach ($employees as $employee) {
                        //$email = $employee->email;
                        $email = 'eriton.dewa@kpn-corp.com';
                        $name = $employee->fullname;
                        $message = $schedule->messages;

                        dispatch(new SendReminderScheduleEmailJob($email, $name, $message));
                        //echo "penerima : $email <br>nama : $name <br>isi email : $message <br>";
                    }
                }
            }
        }
    }
    function DailyUpdateSchedulePA() {
        $today = date('Y-m-d');
        $scheduledatas = schedule::where('event_type','masterschedulepa')->get();

        foreach($scheduledatas as $scheduledata){

            $idpermissions = Permission::where('name','schedulepa')->first();
            $idschedulepa = $idpermissions->id;

            //cek di role has permission yg memiliki akses ke id 6 schedule
            $idroles = RoleHasPermission::where('permission_id', '6')->whereNotIn('role_id',['1','8'])->get();

            if($scheduledata->start_date==$today){
                foreach($idroles as $idrole){
                    //input data di RoleHasPermission dengan permission_id $idschedulepa untuk user yg memiliki akses permission_id=6
                    $existingPermission = RoleHasPermission::where('role_id', $idrole->role_id)
                                            ->where('permission_id', $idschedulepa)
                                            ->first();
                    if (!$existingPermission) {
                        RoleHasPermission::create([
                            'role_id' => $idrole->role_id,
                            'permission_id' => $idschedulepa,
                        ]);
                    }
                }
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $scheduledata->end_date = Carbon::parse($scheduledata->end_date)->addDay()->format('Y-m-d');
            if($scheduledata->end_date==$today){
                foreach ($idroles as $idrole) {
                    RoleHasPermission::where('role_id', $idrole->role_id)
                                    ->where('permission_id', $idschedulepa)
                                    ->delete();
                }
                app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
            }
        }
    }
}
