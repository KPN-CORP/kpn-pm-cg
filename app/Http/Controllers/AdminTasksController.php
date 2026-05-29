<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\EmployeeAppraisal;
use App\Models\Proposed360;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\AppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminTasksController extends Controller
{
    protected $user;
    protected $appService;
    protected $period;
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $roles;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
        $this->user = Auth::user()->employee_id;
        $this->period = $this->appService->appraisalPeriod();

        $this->roles = Auth::user()->roles;

        $restrictionData = [];
        if(!is_null($this->roles)){
            $restrictionData = json_decode($this->roles->first()->restriction, true);
        }

        $this->permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $this->permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $this->permissionLocations = $restrictionData['work_area_code'] ?? [];
    }

    public function index(Request $request)
    {
        $user       = Auth::user();
        $userRoles  = $this->getUserRoleNames($user);       // role user
        $userEmpId  = (string) $user->employee_id;          // employee_id user

        if (empty($userRoles) && empty($userEmpId)) {
            return view('pages.admin-task.app', [
                'tasks'=>collect(),
                'empMap'=>collect(),
                'roleCandidates'=>collect()
            ])->with('error','Anda tidak memiliki role untuk task approval.');
        }

        $q       = trim((string) $request->get('q', ''));
        $perPage = 15;

        // Scope employee
        $scopedEmpIds = $this->buildEmployeeScopeIds();

        $base = ApprovalRequest::query()
            ->select('id','form_id','employee_id','category','period','current_step',
                    'total_steps','current_approval_id', 'approval_flow_id','status','created_at')
            ->where('status','Pending')
            ->where('category', 'Proposed360')
            ->when(!empty($scopedEmpIds), fn($q) => $q->whereIn('employee_id', $scopedEmpIds));

        // Search
        if ($q !== '') {
            $empIdsFromName = EmployeeAppraisal::query()
                ->where('fullname','like',"%{$q}%")
                ->orWhere('employee_id','like',"%{$q}%")
                ->limit(500)
                ->pluck('employee_id')
                ->all();

            $base->where(function($w) use ($q, $empIdsFromName){
                $w->where('employee_id','like',"%{$q}%")
                ->orWhereIn('employee_id', $empIdsFromName)
                ->orWhere('category','like',"%{$q}%")
                ->orWhere('period','like',"%{$q}%")
                ->orWhere('current_approval_id','like',"%{$q}%");
            });
        }

        // Ambil data
        $tasks = $base->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $tasks->getCollection()->transform(function ($t) {

            $cur  = $t->approval_flow_id;
            $step = (int) $t->current_step;

            // CASE 1: specific user
            if (ctype_digit((string) $t->current_approval_id)) {
                $t->resolved_roles = [];
                $t->approval_candidates = $this->buildRoleCandidatesMap($t->current_approval_id ? [(string)$t->current_approval_id] : []);
                return $t;

            }

            // CASE 2: flow role
            $roles = $this->getStepRoles($cur, $step);

            $t->resolved_roles      = $roles;
            $t->approval_candidates = $this->buildRoleCandidatesMap($roles);

            return $t;
        });

        //=========== MAP EMPLOYEE ===========
        $empIds = $tasks->pluck('employee_id')->filter()->unique()->values()->all();
        $empMap = EmployeeAppraisal::select('employee_id','fullname','designation_name')
            ->whereIn('employee_id', $empIds)->get()->keyBy('employee_id');

        $roleNamesUsed = [];

        $roleNamesUsed = array_values(array_unique($roleNamesUsed));
        $roleCandidates = $this->getRoleCandidatesLabels($roleNamesUsed);

        $parentLink = __('Admin Tasks');
        $link       = __('Tasks');

        if ($request->ajax()) {
            return response()->view('pages.admin-task._list',
                compact('tasks','empMap','roleCandidates'));
        }

        return view('pages.admin-task.app',
            compact('parentLink','link','tasks','empMap','roleCandidates'));
    }

    private function buildRoleCandidatesMap(array $roleNames)
    {
        $map = collect();

        // -----------------------------------------------------------
        // 1. SPAITE PERMISSION (PRIMARY SOURCE)
        // -----------------------------------------------------------
        if (
            class_exists(\Spatie\Permission\Models\Role::class) &&
            Schema::hasTable('model_has_roles')
        ) {
            $roleModels   = \Spatie\Permission\Models\Role::whereIn('name', $roleNames)
                            ->get(['id','name']);
            $roleIdByName = $roleModels->pluck('id','name');

            if ($roleIdByName->isNotEmpty()) {
                $rows = DB::table('model_has_roles')
                    ->whereIn('role_id', $roleIdByName->values())
                    ->get(['role_id','model_type','model_id']);

                $userIds = $rows->pluck('model_id')->unique()->values();
                $userEmp = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('employee_id', 'id');

                // Ambil employee info
                $empMap = EmployeeAppraisal::whereIn(
                    'employee_id',
                    array_filter($userEmp->values()->all())
                )
                ->get()
                ->keyBy('employee_id');

                foreach ($roleIdByName as $rName => $rId) {

                    // employee_id per role
                    $empIds = $rows->where('role_id', $rId)
                        ->map(fn($r) => (string)($userEmp[$r->model_id] ?? ''))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    // label
                    $labels = collect($empIds)->map(function ($eid) use ($empMap) {
                        $fullname = $empMap[$eid]->fullname ?? null;
                        return ($fullname ?? $eid) . " ({$eid})";
                    })->toArray();

                    // Jika role kosong, fallback ke employee_id langsung
                    if (empty($labels)) {
                        $map[$rName] = [$rName . ' (no assigned user)'];
                    } else {
                        $map[$rName] = $labels;
                    }
                }
            }
        }

        // -----------------------------------------------------------
        // 2. FALLBACK PIVOT
        // -----------------------------------------------------------
        if ($map->isEmpty()) {
            $roles = DB::table('roles')
                ->whereIn('name', $roleNames)
                ->get(['id','name']);

            if ($roles->isNotEmpty()) {

                $pivotNames = collect(['role_user', 'user_roles'])
                    ->filter(fn($t) => Schema::hasTable($t));

                $roleIdByName = $roles->mapWithKeys(fn($r) => [
                    $r->name => $r->id
                ]);

                $all = [];
                foreach ($pivotNames as $p) {
                    $rows = DB::table($p)
                        ->whereIn('role_id', $roleIdByName->values())
                        ->get(['role_id','user_id']);

                    foreach ($rows as $r) {
                        $all[$r->role_id][] = $r->user_id;
                    }
                }

                // pull user-employee mapping
                $userEmp = DB::table('users')
                    ->whereIn('id', array_merge(...array_values($all ?: [])))
                    ->pluck('employee_id','id');

                $empMap = EmployeeAppraisal::whereIn(
                    'employee_id',
                    array_values(array_filter($userEmp->values()->all()))
                )
                ->get()
                ->keyBy('employee_id');

                foreach ($roleIdByName as $rName => $rId) {
                    $uids = array_unique($all[$rId] ?? []);

                    $labels = collect($uids)->map(function ($uid) use ($userEmp, $empMap) {
                        $eid = (string)($userEmp[$uid] ?? '');
                        if ($eid === '') {
                            return null;
                        }
                        $fullname = $empMap[$eid]->fullname ?? null;
                        return ($fullname ?? $eid) . " ({$eid})";
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                    // fallback jika tetap kosong
                    if (empty($labels)) {
                        $map[$rName] = [$rName . ' (no assigned user)'];
                    } else {
                        $map[$rName] = $labels;
                    }
                }
            }
        }

        // -----------------------------------------------------------
        // 3. FINAL FALLBACK — role tidak ditemukan di mana pun
        // -----------------------------------------------------------
        if ($map->isEmpty() && !empty($roleNames)) {

            foreach ($roleNames as $roleName) {

                // cek apakah roleName berisi employee_id
                if (ctype_digit($roleName)) {
                    $emp = EmployeeAppraisal::where('employee_id', $roleName)->first();
                    $label = $emp ? "{$emp->fullname} ({$roleName})" : $roleName;
                    $map[$roleName] = [$label];
                } else {
                    // last fallback → tampilkan role tanpa user
                    $map[$roleName] = [$roleName . ' (unassigned)'];
                }
            }
        }

        return $map;
    }


    protected function getStepRoles($flowId, $stepName): array
    {
        if (!$flowId || trim($stepName) === '') {
            return [];
        }

        $roleString = \App\Models\ApprovalFlowStep::query()
            ->where('approval_flow_id', $flowId)
            ->whereRaw('LOWER(step_name) = ?', [strtolower($stepName)])
            ->value('approver_role');

        return $roleString
            ? collect($roleString)
                ->map(fn($r) => trim($r))
                ->filter()
                ->values()
                ->all()
            : [];
    }


    protected function buildEmployeeScopeQuery(): Builder
    {
        // Ambil dari properti yg sudah Anda set di __construct
        $groupCompanies = $this->permissionGroupCompanies;     // match ke kolom: company_name / group_company
        $companies      = $this->permissionCompanies;          // match ke kolom: contribution_level_code
        $locations      = $this->permissionLocations;          // match ke kolom: work_area (atau work_area_code)

        return EmployeeAppraisal::query()
            ->select('employee_id')
            ->when(!empty($groupCompanies) || !empty($companies) || !empty($locations), function($q) use ($groupCompanies, $companies, $locations){
                $q->where(function($w) use ($groupCompanies, $companies, $locations){
                    if (!empty($groupCompanies)) {
                        $w->orWhereIn('group_company', $groupCompanies);
                        // jika field Anda bernama group_company, ganti baris di atas
                        // $w->orWhereIn('group_company', $groupCompanies);
                    }
                    if (!empty($companies)) {
                        $w->orWhereIn('contribution_level_code', $companies);
                    }
                    if (!empty($locations)) {
                        $w->orWhereIn('work_area_code', $locations); // atau work_area_code
                    }
                });
            });
    }

    protected function buildEmployeeScopeIds(): array
    {
        // Untuk whereIn subquery berbasis array (aman bila jumlahnya tidak ratusan ribu)
        return $this->buildEmployeeScopeQuery()
            ->limit(50000) // guard
            ->pluck('employee_id')
            ->all();
    }

    public function detail(string $id)
    {
        $user  = Auth::user();
        $roles = $this->getUserRoleNames($user);

        $req = ApprovalRequest::with('manager')->findOrFail($id);
        if ($req->status !== 'Pending') {
            abort(403, 'Task tidak dalam status Pending.');
        }

        // --- ROLE CHECK ---
        if (!$roles) {
            abort(403, 'Anda tidak memiliki role untuk task ini.');
        }

        $employee = EmployeeAppraisal::select(
                'employee_id','fullname','designation_name','manager_l1_id','manager_l2_id',
                'group_company','contribution_level_code','work_area_code'
            )
            ->where('employee_id', $req->employee_id)
            ->first();

        if (!$employee) {
            abort(404, 'Data karyawan tidak ditemukan.');
        }

        $permGroup  = $this->permissionGroupCompanies ?? [];
        $permComp   = $this->permissionCompanies ?? [];
        $permLoc    = $this->permissionLocations ?? [];

        $hasRestriction = !empty($permGroup) || !empty($permComp) || !empty($permLoc);

        // Jika ada restriction → karyawan harus match minimal salah satu (OR)
        if ($hasRestriction) {
            $matchGroup = !empty($permGroup) && in_array((string)$employee->group_company, $permGroup, true);
            // Jika field Anda bernama group_company, ganti ke $employee->group_company

            $matchComp  = !empty($permComp) && in_array((string)$employee->contribution_level_code, $permComp, true);

            $matchLoc   = !empty($permLoc) && in_array((string)$employee->work_area_code, $permLoc, true);
            // Jika pakai work_area_code, sesuaikan

            if (!($matchGroup || $matchComp || $matchLoc)) {
                abort(403, 'Anda tidak memiliki akses (perusahaan/lokasi) untuk task ini.');
            }
        }

        // --- initiator & form detail (tetap) ---
        $initiator = EmployeeAppraisal::select('employee_id','fullname')
            ->where('id', $req->created_by)->first();

        $formDetail = null;
        if ($req->category === 'Proposed360') {
            $formDetail = Proposed360::select('id','scope','peers','subordinates','managers','notes','appraisal_year','proposer_employee_id','employee_id')
                ->find($req->form_id);
            if ($formDetail) {
                $formDetail->peers        = is_string($formDetail->peers) ? json_decode($formDetail->peers, true) : ($formDetail->peers ?? []);
                $formDetail->subordinates = is_string($formDetail->subordinates) ? json_decode($formDetail->subordinates, true) : ($formDetail->subordinates ?? []);
                $formDetail->managers     = is_string($formDetail->managers) ? json_decode($formDetail->managers, true) : ($formDetail->managers ?? []);
            }
        }

        $parentLink = __('Tasks');
        $link = __('Approval Details');

        $cur        = $req->approval_flow_id;
        $stepNumber = (int) $req->current_step;

        $roles = $this->getStepRoles($cur, $stepNumber);
        $req->resolved_roles = $roles;

        // Jika role saat ini adalah salah satu manager level, gunakan current_approval_id sebagai kandidat approver
        $managerRoles = ['L1 Manager', 'L2 Manager'];
        if (is_array($roles) && array_intersect($roles, $managerRoles)) {
            $roles = $req->current_approval_id ? [(string)$req->current_approval_id] : [];
        }

        // Kandidat untuk role ini (tooltip/list)
        $candidates = $this->buildRoleCandidatesMap($roles) ?? [];

        return view('pages.admin-task.show', compact('parentLink','link','req','employee','initiator','formDetail','candidates'));
    }

    public function action(string $id, Request $request, ApprovalEngine $engine)
    {
        $request->validate([
            'action'  => 'required|in:APPROVE,REJECT',
            'message' => 'nullable|string|max:1000',
        ]);

        $user       = Auth::user();
        $actorEmpId = (string) ($user->employee_id ?? '');
        $roles      = $this->getUserRoleNames($user);

        /** @var ApprovalRequest $req */
        $req = ApprovalRequest::lockForUpdate()->findOrFail($id);
        if ($req->status !== 'Pending') {
            return back()->with('error','Task tidak dalam status Pending.');
        }
        if (empty($roles)) {
            return back()->with('error','Anda tidak memiliki role untuk task ini.');
        }

        // Permission 3 parameter (sudah Anda tambah sebelumnya)…
        // ... cek perusahaan/lokasi di sini (dipertahankan)

        $isOverride = $this->canOverrideApproval($user); // ⬅️ dinamis

        try {
            DB::beginTransaction();

            if ($request->action === 'APPROVE') {
                $engine->approve((string) $req->form_id, $actorEmpId, $isOverride);
                DB::commit();
                return redirect()->route('admin-tasks')->with('success','Task Approved Successfully');
            }

            $engine->reject((string) $req->form_id, $actorEmpId, null, $request->message);
            DB::commit();
            return redirect()->route('admin-tasks')->with('success','Task has been Sendbacked.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error','Gagal memproses task: '.$e->getMessage());
        }
    }

    protected function scopedEmployeeIds(): array
    {
        $user  = Auth::user();
        $roles = $user->roles ?? collect(); // jika pakai relasi
        $restrictionData = [];
        if ($roles && $roles->first()?->restriction) {
            $restrictionData = json_decode($roles->first()->restriction, true) ?? [];
        }

        $groupCompanies = $restrictionData['group_company'] ?? [];
        $companies      = $restrictionData['contribution_level_code'] ?? [];
        $locations      = $restrictionData['work_area_code'] ?? [];

        return EmployeeAppraisal::query()
            ->select('employee_id')
            ->when(!empty($groupCompanies) || !empty($companies) || !empty($locations), function ($q) use ($groupCompanies, $companies, $locations) {
                $q->where(function ($w) use ($groupCompanies, $companies, $locations) {
                    if (!empty($groupCompanies)) {
                        $w->orWhereIn('group_company', $groupCompanies);
                    }
                    if (!empty($companies)) {
                        $w->orWhereIn('contribution_level_code', $companies);
                    }
                    if (!empty($locations)) {
                        $w->orWhereIn('work_area_code', $locations);
                    }
                });
            })
            ->limit(50000)
            ->pluck('employee_id')
            ->all();
    }

    public function indexHistory(Request $request)
    {
        $user  = Auth::user();
        $roles = $this->getUserRoleNames($user);
        if (empty($roles)) {
            return view('pages.admin-task.approval-history', [
                'histories'=>collect(), 'empMap'=>collect()
            ])->with('error','Anda tidak memiliki role.');
        }

        $q       = trim((string) $request->get('q', ''));
        $perPage = 15;

        $scopedEmpIds = $this->buildEmployeeScopeIds();

        $base = ApprovalRequest::query()
            ->select('id','form_id','employee_id','category','period','current_step','total_steps','current_approval_id','status','created_at')
            ->where('status','Approved')
            ->where('category','Proposed360') // samakan batasan seperti sample Anda
            ->when(!empty($scopedEmpIds), fn($q2)=>$q2->whereIn('employee_id', $scopedEmpIds));

        if ($q !== '') {
            $empIdsFromName = EmployeeAppraisal::query()
                ->where('fullname','like',"%{$q}%")
                ->orWhere('employee_id','like',"%{$q}%")
                ->limit(500)->pluck('employee_id')->all();

            $base->where(function($w) use ($q, $empIdsFromName){
                $w->where('employee_id','like',"%{$q}%")
                  ->orWhereIn('employee_id', $empIdsFromName)
                  ->orWhere('category','like',"%{$q}%")
                  ->orWhere('period','like',"%{$q}%")
                  ->orWhere('current_approval_id','like',"%{$q}%");
            });
        }

        $histories = $base->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        $empIds = $histories->pluck('employee_id')->filter()->unique()->values()->all();
        $empMap = EmployeeAppraisal::select('employee_id','fullname','designation_name')
            ->whereIn('employee_id', $empIds)->get()->keyBy('employee_id');

        $parentLink = __('Admin Tasks'); $link = __('History Approval');

        return view('pages.admin-task.approval-history', compact('parentLink','link','histories','empMap'));
    }

    public function detailHistory(string $id)
    {
        $req = ApprovalRequest::findOrFail($id);
        if ($req->status !== 'Approved') abort(404, 'Data bukan final approval.');

        $employee = EmployeeAppraisal::select('employee_id','fullname','designation_name','manager_l1_id','manager_l2_id','group_company','contribution_level_code','work_area_code')
        ->where('employee_id', $req->employee_id)->first();

        $initiator = EmployeeAppraisal::select('employee_id','fullname')
            ->where('id', $req->created_by)->first();

        // ===== Proposed360: format list "Fullname (ID)" =====
        $formDetail = null; $peersList=[]; $subsList=[]; $mgrsList=[];
        if ($req->category === 'Proposed360') {
            $formDetail = Proposed360::select('id','scope','peers','subordinates','managers','notes','appraisal_year','proposer_employee_id','employee_id')
                ->find($req->form_id);

            if ($formDetail) {
                $peers = is_string($formDetail->peers) ? json_decode($formDetail->peers, true) : ($formDetail->peers ?? []);
                $subs  = is_string($formDetail->subordinates) ? json_decode($formDetail->subordinates, true) : ($formDetail->subordinates ?? []);
                $mgrs  = is_string($formDetail->managers) ? json_decode($formDetail->managers, true) : ($formDetail->managers ?? []);

                $allIds = collect([$peers,$subs,$mgrs])->flatten()->filter()->unique()->values();
                $nameMap = EmployeeAppraisal::whereIn('employee_id', $allIds)->pluck('fullname','employee_id');

                $fmt = fn($ids)=>collect($ids)->map(fn($id)=>sprintf('%s (%s)', $nameMap[$id] ?? $id, $id))->all();
                $peersList = $fmt($peers);
                $subsList  = $fmt($subs);
                $mgrsList  = $fmt($mgrs);
            }
        }

        // ===== Approved By & Approved On dari log =====
        $approvedByEmpId = null;
        $approvedByName  = null;
        $approvedOn      = $req->updated_at;
        $approvedIsOverride = false;
        $approvedByRole  = null;

        $log = ApprovalLog::where('approval_request_id', $req->id)
            ->whereIn('action', ['APPROVE','APPROVE_OVERRIDE'])
            ->orderByDesc('acted_at')->orderByDesc('id')
            ->first();

        if ($log) {
            $approvedByEmpId   = (string) $log->actor_employee_id;
            $approvedOn        = $log->acted_at ?: $log->created_at ?: $approvedOn;
            $approvedIsOverride= $log->action === 'APPROVE_OVERRIDE';

            $approvedByName    = EmployeeAppraisal::where('employee_id', $approvedByEmpId)->value('fullname');

            // Jika override → tampilkan role dari user approver
            if ($approvedIsOverride) {
                $approverUser = User::with('roles:name')    // relasi roles harus ada
                    ->where('employee_id', $approvedByEmpId)
                    ->first();
                $approvedByRole = optional($approverUser?->roles?->first())->name;
            }
        }

        $parentLink = __('History Approval'); $link = __('Detail');

        return view('pages.admin-task.approval-history-detail', compact(
            'req','employee','initiator','formDetail',
            'peersList','subsList','mgrsList',
            'approvedByEmpId','approvedByName','approvedOn',
            'approvedIsOverride','approvedByRole', 'parentLink','link'
        ));
    }

    /** ---------- Helpers ---------- */

    protected function canOverrideApproval($user): bool
    {
        // A. Pakai Spatie Permission (jika ada)
        if ($user->roles()->exists()) {
            return true;
        }

        // B. Cek flag di tabel roles (misal kolom `can_override`)
        if (isset($user->roles)) {
            $hasFlag = $user->roles->contains(fn($r) => (bool)($r->can_override ?? false));
            if ($hasFlag) return true;
        }

        // C. Config fallback (bisa Anda ubah tanpa code change)
        $overrideRoles = config('approval.override_roles', []); // ex: ['Admin','Super Admin']
        if (!empty($overrideRoles) && isset($user->roles)) {
            $names = $user->roles->pluck('name')->all();
            return (bool) array_intersect($names, $overrideRoles);
        }

        return false;
    }

    // Ambil semua nama role milik user (Spatie > fallback)
    protected function getUserRoleNames($user): array
    {
        if (!$user) return [];
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        $names = [];
        if (Schema::hasTable('model_has_roles')) {
            $rows = DB::table('model_has_roles')->where('model_type', get_class($user))->where('model_id',$user->getKey())->pluck('role_id');
            if ($rows->isNotEmpty() && Schema::hasTable('roles')) {
                $names = array_merge($names, DB::table('roles')->whereIn('id',$rows)->pluck('name')->toArray());
            }
        }
        foreach (['role_user','user_roles'] as $pivot) {
            if (!Schema::hasTable($pivot) || !Schema::hasTable('roles')) continue;
            $rids = DB::table($pivot)->where('user_id',$user->getKey())->pluck('role_id');
            if ($rids->isNotEmpty()) {
                $names = array_merge($names, DB::table('roles')->whereIn('id',$rids)->pluck('name')->toArray());
            }
        }
        return array_values(array_unique(array_filter($names)));
    }

    // Map: role name → ["Fullname (employee_id)", ...]
    protected function getRoleCandidatesLabels(array $roleNames): array
    {
        $roleNames = array_values(array_unique(array_filter($roleNames)));
        if (empty($roleNames)) return [];

        // Spatie
        $map = [];
        if (class_exists(\Spatie\Permission\Models\Role::class) && Schema::hasTable('model_has_roles')) {
            $roleModels = \Spatie\Permission\Models\Role::whereIn('name',$roleNames)->get(['id','name']);
            $rows = DB::table('model_has_roles')->whereIn('role_id',$roleModels->pluck('id'))->get();
            $users = DB::table('users')->whereIn('id', $rows->pluck('model_id')->unique())->pluck('employee_id','id');
            $empIds = array_values(array_unique(array_filter($users->values()->all())));
            $empMap = EmployeeAppraisal::select('employee_id','fullname')->whereIn('employee_id',$empIds)->get()->keyBy('employee_id');

            foreach ($roleModels as $rm) {
                $uids = $rows->where('role_id',$rm->id)->pluck('model_id')->unique()->values();
                $labels = [];
                foreach ($uids as $uid) {
                    $eid = (string)($users[$uid] ?? '');
                    if ($eid === '') continue;
                    $labels[] = ($empMap[$eid]->fullname ?? $eid).' ('.$eid.')';
                }
                $map[$rm->name] = $labels;
            }
        }

        // Fallback pivot umum
        if (empty($map) && Schema::hasTable('roles')) {
            $roles = DB::table('roles')->whereIn('name',$roleNames)->get(['id','name']);
            $roleIdByName = $roles->pluck('id','name');
            $all = [];
            foreach (['role_user','user_roles'] as $pivot) {
                if (!Schema::hasTable($pivot)) continue;
                $rows = DB::table($pivot)->whereIn('role_id',$roleIdByName->values())->get(['role_id','user_id']);
                foreach ($rows as $r) $all[$r->role_id][] = $r->user_id;
            }
            $userIds = array_values(array_unique(array_merge(...array_values($all ?: []))));
            $users = DB::table('users')->whereIn('id',$userIds)->pluck('employee_id','id');
            $empIds = array_values(array_unique(array_filter($users->values()->all())));
            $empMap = EmployeeAppraisal::select('employee_id','fullname')->whereIn('employee_id',$empIds)->get()->keyBy('employee_id');

            foreach ($roleIdByName as $name => $rid) {
                $uids = array_unique($all[$rid] ?? []);
                $labels = [];
                foreach ($uids as $uid) {
                    $eid = (string)($users[$uid] ?? '');
                    if ($eid === '') continue;
                    $labels[] = ($empMap[$eid]->fullname ?? $eid).' ('.$eid.')';
                }
                $map[$name] = $labels;
            }
        }

        return $map;
    }
}
