<?php

namespace App\Http\Controllers;

use App\Models\Appraisal;
use App\Models\Approval;
use App\Models\ApprovalLayer;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Goal;
use App\Models\Proposed360;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ApprovalController extends Controller
{

    public function store(Request $request): RedirectResponse
    {
        DB::beginTransaction();
        try {
            // Determine next approver and status
            $currentLayer = ApprovalLayer::where('approver_id', $request->current_approver_id)
                ->where('employee_id', $request->employee_id)
                ->value('layer');

            $nextLayer = $currentLayer ? $currentLayer + 1 : 1;
            $nextApprover = ApprovalLayer::where('employee_id', $request->employee_id)
                ->where('layer', $nextLayer)
                ->value('approver_id');

            $statusRequest = $nextApprover ? 'Pending' : 'Approved';
            $statusForm = $nextApprover ? 'Submitted' : 'Approved';

            // Validate form submission
            if ($request->submit_type === 'submit_form') {
                $rules = [
                    'kpi.*' => 'required|string',
                    'target.*' => 'required|string',
                    'uom.*' => 'required|string',
                    'weightage.*' => 'required|integer|min:5|max:100',
                    'type.*' => 'required|string',
                ];

                $validator = Validator::make($request->all(), $rules, [
                    'weightage.*.integer' => 'Weightage harus berupa angka.',
                    'weightage.*.min' => 'Weightage minimal :min%.',
                    'weightage.*.max' => 'Weightage maksimal :max%.',
                ]);

                if ($validator->fails()) {
                    return back()->withErrors($validator)->withInput();
                }
            }

            // Prepare KPI data
            $kpiData = [];
            foreach ($request->input('kpi', []) as $index => $kpi) {
                $kpiData[$index] = [
                    'kpi' => $kpi,
                    'target' => $request->target[$index],
                    'uom' => $request->uom[$index],
                    'weightage' => $request->weightage[$index],
                    'type' => $request->type[$index],
                    'custom_uom' => $request->custom_uom[$index] ?? null,
                ];
            }

            $jsonData = json_encode($kpiData);

            // Get the current approver's ID from the request
            $approverId = $request->current_approver_id;

            // Use firstOrNew to handle both cases (existing/new)
            $snapshot = ApprovalSnapshots::firstOrNew([
                'form_id' => $request->id,
                'employee_id' => $approverId,
            ]);

            // For new records, set required fields
            if (!$snapshot->exists) {
                $snapshot->id = Str::uuid(); // Remove if using model UUID generation
                $snapshot->form_id = $request->id;
                $snapshot->employee_id = $approverId;
                $snapshot->created_by = Auth::user()->id;
            }

            // Update common fields for both cases
            $snapshot->form_data = $jsonData;
            $snapshot->updated_by = Auth::user()->id;

            // Save the record
            $snapshot->save();

            if (!$snapshot->save()) {
                throw new Exception('Gagal menyimpan snapshot persetujuan');
            }

            // Update goal status
            $goal = Goal::findOrFail($request->id);
            $goal->form_status = $statusForm;

            if (!$goal->save()) {
                throw new Exception('Gagal memperbarui status goal');
            }

            // Update approval request
            $approvalRequest = ApprovalRequest::where('form_id', $request->id)
                ->firstOrFail();
                
            $approvalRequest->current_approval_id = $nextApprover ?? $request->current_approver_id;
            $approvalRequest->status = $statusRequest;
            $approvalRequest->updated_by = Auth::id();
            $approvalRequest->messages = $request->messages;
            $approvalRequest->sendback_messages = null;
            $approvalRequest->sendback_to = null;

            if (!$approvalRequest->save()) {
                throw new Exception('Gagal memperbarui permintaan persetujuan');
            }

            // Update/create approval record
            $approval = Approval::firstOrNew([
                'request_id' => $approvalRequest->id,
                'approver_id' => $request->current_approver_id,
            ]);

            $approval->messages = $request->messages;
            $approval->status = 'Approved';
            $approval->created_by = Auth::id();

            if (!$approval->save()) {
                throw new Exception('Gagal menyimpan catatan persetujuan');
            }

            DB::commit();
            return redirect()->route('team-goals')->with('success', 'Data berhasil disubmit');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Approval process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => Auth::id(),
                'goal_id' => $request->id ?? 'N/A',
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    public function processAction(Request $request, ApprovalRequest $approvalRequest)
    {
        $request->validate([
            'action' => 'required|in:Approve,Sendback',
            'comments' => 'nullable|string',
            'peers' => 'nullable|array',
            'subs' => 'nullable|array',
        ]);

        $action = $request->input('action');
        $comments = $request->input('comments');
        $currentLoggedInEmployeeId = Auth::user()->employee_id;

        try {
            DB::beginTransaction();

            // Pastikan user yang melakukan aksi adalah salah satu approver saat ini
            $currentApprovers = json_decode($approvalRequest->current_approval_id, true);
            if (!in_array($currentLoggedInEmployeeId, $currentApprovers)) {
                // Asumsi current_approval_id berisi employee_id
                throw new Exception('You are not authorized to approve this request at this stage.');
            }

            // Ambil data transaksi Proposed360 untuk diperbarui
            $proposed360Transaction = Proposed360::find($approvalRequest->form_id);
            if (!$proposed360Transaction) {
                throw new Exception('Associated Proposed360 transaction not found.');
            }

            $currentStepNumber = $approvalRequest->current_step;
            $currentFlow = $approvalRequest->approvalFlow;

            if ($action === 'Approve') {
                // 1. Simpan data peers dan subs ke transaksi Proposed360
                $proposed360Transaction->update([
                    'peers' => json_encode($request->input('peers', [])),
                    'subordinates' => json_encode($request->input('subs', [])),
                ]);
                
                // 2. Catat di log bahwa persetujuan telah dilakukan
                ApprovalLog::create([
                    'approval_request_id' => $approvalRequest->id,
                    'approver_user_id' => $currentLoggedInEmployeeId,
                    'action_taken' => 'Approved',
                    'comments' => $comments,
                ]);

                // 3. Periksa apakah ini langkah terakhir
                $nextStepDefinition = $currentFlow->steps()->where('step_number', $currentStepNumber + 1)->first();
                if ($nextStepDefinition) {
                    $nextApprovers = $nextStepDefinition->approver_user_id ?? $nextStepDefinition->approver_role;
                    if (empty($nextApprovers)) {
                        throw new Exception('Next step has no defined approvers.');
                    }
                    $approvalRequest->update([
                        'current_step' => $currentStepNumber + 1,
                        'current_approval_id' => json_encode($nextApprovers),
                        'status' => 'Pending',
                    ]);
                } else {
                    // Ini adalah langkah terakhir, tandai sebagai Approved
                    $approvalRequest->update([
                        'current_step' => $currentStepNumber + 1,
                        'current_approval_id' => null,
                        'status' => 'Approved',
                    ]);

                    // Trigger logika finalisasi approval
                    $this->finalizeApproval($approvalRequest, $proposed360Transaction);
                }

            } elseif ($action === 'Sendback') {
                $approvalRequest->update([
                    'status' => 'Sendback',
                    'current_step' => 1,
                    'current_approval_id' => json_encode([$approvalRequest->employee_id]),
                    'sendback_messages' => $comments,
                    'sendback_to' => $approvalRequest->employee_id,
                ]);
                ApprovalLog::create([
                    'approval_request_id' => $approvalRequest->id,
                    'approver_user_id' => $currentLoggedInEmployeeId,
                    'action_taken' => 'Sendback',
                    'comments' => $comments,
                ]);
            }
            
            DB::commit();
            return back()->with('success', 'Tindakan persetujuan berhasil diproses.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Approval action failed for request ' . $approvalRequest->id . ': ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses tindakan persetujuan: ' . $e->getMessage());
        }
    }

    private function finalizeApproval(ApprovalRequest $approvalRequest, Proposed360 $proposed360Transaction)
    {
        // Pindahkan/salin data ke tabel final
        $peers = json_decode($proposed360Transaction->peers, true) ?? [];
        $subordinates = json_decode($proposed360Transaction->subordinates, true) ?? [];

        // Gabungkan peers dan subordinates menjadi satu array untuk approval
        $allApprovers = array_merge($peers, $subordinates);

        // Contoh: Masukkan setiap approver ke tabel approval_layer_appraisals
        foreach ($allApprovers as $approverId) {
            ApprovalLayerAppraisal::create([
                'appraisal_id' => $proposed360Transaction->id,
                'approver_id' => $approverId,
                'status' => 'Pending',
                // Kolom 'step', 'layer_type', 'layer' perlu ditentukan di sini
                // Ini membutuhkan logika bisnis tambahan untuk memetakan approverId ke layer yang benar
            ]);
        }
        
        // Opsional: Perbarui status transaksi Proposed360 ke 'Approved'
        $proposed360Transaction->update(['status' => 'Approved']);
    }
}
