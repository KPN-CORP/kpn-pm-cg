<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;

use App\Models\PerformanceDialog;
use App\Models\PerformanceDialogType;
use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Models\Goal;
use App\Services\AppService;
use App\Mail\AchievementSubmissionNotifMail;

class AchievementController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function form(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $loggedInUserID = $loggedInUser->id;
            $loggedInEmployeeID = $loggedInUser->employee_id;

            $goalID = $request->goal_id;

            $goal = Goal::where("id", $goalID)->whereNull("deleted_at")->first();
            if (!$goal) {
                Session::flash('error', [
                    'title' => 'Cannot update achievement',
                    'message' => "Goal not found"
                ]);
                return redirect()->back();
            }

            $formData = json_decode($goal->form_data, true);

            $groupedFormData = [
                'company' => [],
                'division' => [],
                'personal' => [],
            ];

            foreach ($formData as $index => $row) {
                $cluster = strtolower($row['cluster']);

                if (!isset($groupedFormData[$cluster])) {
                    continue;
                }

                $groupedFormData[$cluster][] = [
                    'original_index' => $index,
                    'kpi' => $row['kpi'] ?? '',
                    'target' => $row['target'] ?? '',
                    'uom' => $row['custom_uom'] ?: ($row['uom'] ?? ''),
                    'type' => $row['type'] ?? '',
                    'weightage' => $row['weightage'] ?? '',
                    'achievement' => $row['achievement'] ?? '',
                    'description' => $row['description'] ?? '',
                ];
            }

            $clusterTitles = [
                'company' => 'Company Goals',
                'division' => 'Division Goals',
                'personal' => 'Personal Goals',
            ];

            $clusterWeights = [
                'company' => 0,
                'division' => 0,
                'personal' => 0,
            ];

            if (!empty($groupedFormData)) {
                foreach ($groupedFormData as $clusterKey => $items) {
                    foreach ($items as $item) {
                        $clusterWeights[$clusterKey] += (float) ($item['weightage'] ?? 0);
                    }
                }
            }

            $redirectBack = route('goals');

            return view('pages.achievement.form', [
                "parentLink" => "Achievement",
                "link" => "Form",
                "goal_id" => $goalID,
                "grouped_form_data" => $groupedFormData,
                "cluster_titles" => $clusterTitles,
                "cluster_weights" => $clusterWeights,
                "is_achievement_submitted" => $goal->is_achievement_submitted,
                "redirect_back" => $redirectBack,
            ]);
        } catch (Exception $e) {
            Session::flash('error', [
                'title' => 'Error',
                'message' => "General error: " . $e->getMessage()
            ]);

            return redirect()->back();
        }
    }

    public function update(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $loggedInUserID = $loggedInUser->id;
            $loggedInEmployeeID = $loggedInUser->employee_id;

            $goalID = $request->goal_id;
            $achievements = $request->achievements;

            $goal = Goal::where("id", $goalID)->whereNull("deleted_at")->first();
            if (!$goal) {
                Session::flash('error', [
                    'title' => 'Cannot update achievement',
                    'message' => "Goal not found"
                ]);
                return redirect()->back();
            }

            $approvalLayer = ApprovalLayer::with(['employee', 'employeeManager'])
                ->where('employee_id', $loggedInEmployeeID)
                ->where('layer', 1)
                ->first();
            if (!$approvalLayer) {
                Session::flash('error', [
                    'title' => 'Cannot update achievement',
                    'message' => "Approval layer not found"
                ]);
                return redirect()->back();
            }
            if (!$approvalLayer->employee) {
                Session::flash('error', [
                    'title' => 'Cannot update achievement',
                    'message' => "Employee not found"
                ]);
                return redirect()->back();
            }
            if (!$approvalLayer->employeeManager) {
                Session::flash('error', [
                    'title' => 'Cannot update achievement',
                    'message' => "Manager not found"
                ]);
                return redirect()->back();
            }

            $employee = $approvalLayer->employee;
            $manager = $approvalLayer->employeeManager;

            $formData = json_decode($goal->form_data, true);

            foreach ($formData as $index => &$row) {
                $row['achievement'] = $achievements[$index] ?? '';
            }
            unset($row);

            $goal->update([
                'form_data' => json_encode($formData),
                'is_achievement_submitted' => true
            ]);

            if (!empty($manager->email)) {
                Mail::to($manager->email)
                    ->bcc('dali.kewara@kpn-corp.com')
                    ->queue(new AchievementSubmissionNotifMail($employee->fullname ?? "",
                    [
                        "employee_manager_name" => $manager->fullname ?? "-",
                        "employee_name" => $employee->fullname ?? "-",
                        "url" => "",
                    ]));
            }

            Session::flash('success', [
                'title' => 'Success',
                'message' => 'Achievement has been updated.',
            ]);

            return redirect()->route('goals');
        } catch (Exception $e) {
            Session::flash('error', [
                'title' => 'Cannot update achievement',
                'message' => 'Error: ' . $e->getMessage()
            ]);
            return redirect()->back();
        }
    }
}
