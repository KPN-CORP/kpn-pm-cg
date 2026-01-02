<?php

namespace App\Http\Controllers;

use App\Exports\InvalidGoalImport;
use App\Imports\GoalsDataImportManager;
use App\Models\Appraisal;
use App\Models\ApprovalLayer;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use stdClass;

class TeamGoalController extends Controller
{
    protected $category;
    protected $user;
    protected $appService;
    protected $period;

    public function __construct(AppService $appService)
    {
        $this->category = 'Goals';
        $this->appService = $appService;
        $this->user = Auth::user()->employee_id;
        $this->period = $this->appService->goalPeriod();
    }

    function index(Request $request) {
        
        $user = Auth::user()->employee_id;

        // Retrieve the selected year from the request
        $filterYear = $request->input('filterYear');
        
        $datas = ApprovalLayer::with(['employee','subordinates' => function ($query) use ($user, $filterYear){
            $query->with(['goal', 'updatedBy', 'approval' => function ($query) {
                $query->with('approverName');
            }])->whereHas('goal', function ($query) {
                $query->whereNull('deleted_at');
            })->whereHas('approvalLayer', function ($query) use ($user) {
                $query->where('employee_id', $user)->orWhere('approver_id', $user);
            })->when($filterYear, function ($query) use ($filterYear) {
                $query->where('period', $filterYear);
            }, function ($query) {
                $query->where('period', $this->period);
            })->where('category', $this->category);
        }])->where('approver_id', $user)->get();
        
        $tasks = ApprovalLayer::with(['employee', 'subordinates' => function ($query) use ($user, $filterYear) {
            $query->with(['goal', 'updatedBy', 'initiated', 'approval' => function ($query) {
            $query->with('approverName');
            }])->whereHas('goal', function ($query) {
            $query->whereNull('deleted_at');
            })->whereHas('approvalLayer', function ($query) use ($user) {
            $query->where('employee_id', $user)->orWhere('approver_id', $user);
            })->when($filterYear, function ($query) use ($filterYear) {
            $query->where('period', $filterYear);
            }, function ($query) {
            $query->where('period', $this->period);
            });
        }])
        ->whereHas('subordinates', function ($query) use ($user, $filterYear) {
            $query->with(['goal', 'updatedBy', 'approval' => function ($query) {
            $query->with('approverName');
            }])->whereHas('goal', function ($query) {
            $query->whereNull('deleted_at');
            })->whereHas('approvalLayer', function ($query) use ($user) {
            $query->where('employee_id', $user)->orWhere('approver_id', $user);
            })->when($filterYear, function ($query) use ($filterYear) {
            $query->where('period', $filterYear);
            }, function ($query) {
            $query->where('period', $this->period);
            });
        })
        ->where('approver_id', $user)
        ->get()
        ->groupBy('employee_id')
        ->map(function ($groupedTasks) {
            return $groupedTasks->sortByDesc('layer')->first();
        })
        ->values(); // Reset the indexing after grouping and sorting
        
        $tasks->each(function($item) {
            $item->subordinates->map(function($subordinate) {
                // Format created_at
                $createdDate = Carbon::parse($subordinate->created_at);
                if ($createdDate->isToday()) {
                    $subordinate->formatted_created_at = 'Today ' . $createdDate->format('g:i A');
                } else {
                    $subordinate->formatted_created_at = $createdDate->format('d M Y');
                }
    
                // Format updated_at
                $updatedDate = Carbon::parse($subordinate->updated_at);
                if ($updatedDate->isToday()) {
                    $subordinate->formatted_updated_at = 'Today ' . $updatedDate->format('g:i A');
                } else {
                    $subordinate->formatted_updated_at = $updatedDate->format('d M Y');
                }
                
                // Determine name and approval layer
                if ($subordinate->sendback_to == $subordinate->employee->employee_id) {
                    $subordinate->name = $subordinate->employee->fullname . ' (' . $subordinate->employee->employee_id . ')';
                    $subordinate->approvalLayer = '';
                } else {
                    $subordinate->name = $subordinate->manager?->fullname . ' (' . $subordinate->current_approval_id . ')';
                    $subordinate->approvalLayer = ApprovalLayer::where('employee_id', $subordinate->employee_id)
                                                            ->where('approver_id', $subordinate->current_approval_id)
                                                            ->value('layer');
                }

                $appraisalCheck = Appraisal::where('goals_id', $subordinate->goal->id)->exists();

                $subordinate->appraisalCheck = $appraisalCheck;

                return $subordinate;
            });
        });

        $notasks = ApprovalLayer::with([
            'employee',
            'employee.managerL1',
        ])
        ->where('approver_id', $user)
        ->whereHas('employee', fn($q) => $q->where('access_menu->doj', 1))
        ->whereHas('employee', fn($q) => $q->whereNull('deleted_at'))
        ->whereDoesntHave('subordinates', function ($q) use ($user, $filterYear) {
            $q->where('period', $filterYear ?? $this->period)
              ->where('category', $this->category);
        })
        ->get();  

        $notasks = $notasks->map(function($item) {
            // Format created_at
            $doj = Carbon::parse($item->employee->date_of_joining);

            $isManager = ApprovalLayer::where('employee_id', $item->employee_id)
             ->where('approver_id', Auth::user()->employee_id)
             ->where('layer', 1)
             ->exists();

            $item->isManager = $isManager;
            $item->formatted_doj = $doj->format('d M Y');
            
            return $item;
        })->values(); // Reset the indexing after mapping

        $notasks = $notasks->sortByDesc('isManager')->values(); // Reset the indexing after sorting
        
        $data = [];
        $formData = [];

        foreach ($datas as $request) {

            $dataItem = new stdClass();
            $dataItem->request = $request;

            // Check if subordinates is not empty and has elements
            if ($request->subordinates->isNotEmpty()) {
            $firstSubordinate = $request->subordinates->first();
        
            // Check form status and created_by conditions
            if ($firstSubordinate->created_by != Auth::user()->id) {
                
                // Check if approval relation exists and has elements
                if ($firstSubordinate->approval->isNotEmpty()) {
                $approverName = $firstSubordinate->approval->first();
                $dataApprover = $approverName->approverName->fullname;
                } else {
                $dataApprover = '';
                }
        
                // Create object to store request and approver fullname
                $dataItem->approver_name = $dataApprover;
        
                // Add object to array $data
                
                $formData = json_decode($firstSubordinate->goal->form_data, true);
            }
            } else {
            // Handle case when subordinates is empty
            // Create object with empty or default values
            $dataItem->approver_name = ''; // or some default value
            
            // Add object to array $data
            $data[] = $dataItem;
            
            $formData = '';
            }

            $data[] = $dataItem;
        }
        
        $path = base_path('resources/goal.json');

        // Check if the JSON file exists
        if (!File::exists($path)) {
            // Handle the situation where the JSON file doesn't exist
            abort(500, 'JSON file does not exist.');
        }

        // Read the contents of the JSON file
        $options = json_decode(File::get($path), true);

        $uomOption = $options['UoM'];
        $typeOption = $options['Type'];

        $parentLink = __('Goal');
        $link = __('Task Box');

        $period = $this->period;
        $selectYear = Schedule::withTrashed()
        ->where('event_type', 'goals')
        ->where('schedule_periode', '!=', $period)
        ->selectRaw('DISTINCT schedule_periode as period')
        ->orderBy('period', 'ASC')
        ->get();

        return view('pages.goals.team-goal', compact('data', 'tasks', 'notasks', 'link', 'parentLink', 'formData', 'uomOption', 'typeOption', 'selectYear', 'period'));
       
    }
    
    function create($id) {

        $id = decrypt($id);

        $period = $this->appService->goalPeriod();
        $employee = Employee::where('employee_id', $id)->first();
        $access_menu = json_decode($employee->access_menu, true);
        $goal_access = $access_menu['goals'] ?? null;
        $doj_access = $access_menu['doj'] ?? null;

        if (!$goal_access || !$doj_access) {
            // User ID doesn't match the condition, show error message
            if ($this->user != $id) {
                Session::flash('error', [
                    'title' => 'Cannot create goal',
                    'message' => "This employee not granted access to initiate Goals"
                ]);
                return redirect('team-goals');
            } else {
                Session::flash('error', [
                    'title' => 'Cannot create goal',
                    'message' => "You are not granted access to initiate Goals."
                ]);
            }
            return redirect('goals');
        }

        $goal = Goal::where('employee_id', $id)->where('period', $period)->get();
        if ($goal->isNotEmpty()) {
            // User ID doesn't match the condition, show error message
            Session::flash('error', [
                'title' => 'Cannot create goal',
                'message' => "You already initiated Goals for $period."
            ]);

            return redirect('team-goals');

        }

        $datas = ApprovalLayer::with(['employee'])->where('employee_id', $id)->where('layer', 1)->get();  
        if (!$datas->first()) {
            Session::flash('error', [
                'title' => 'Cannot create goal',
                'message' => "There is no direct manager assigned in employee position!"
            ]);

            return redirect('team-goals');
        }

        $path = base_path('resources/goal.json');

        // Check if the JSON file exists
        if (!File::exists($path)) {
            // Handle the situation where the JSON file doesn't exist
            abort(500, 'JSON file does not exist.');
        }

        // Read the contents of the JSON file
        $uomOptions = json_decode(File::get($path), true);

        $uomOption = $uomOptions['UoM'];
        
        $parentLink = __('Goal');
        $link = 'Create';

        return view('pages.goals.form', compact('datas', 'link', 'parentLink', 'uomOption', 'period'));

    }

    function edit($id) {

        $period = $this->appService->goalPeriod();

        $goalsQuery = Goal::with(['approvalRequest' => function ($query) {
            $query->where('created_by', Auth::user()->id);
        }])->where('id', $id);
        $goal =  $goalsQuery->first();
        $goalsCheck = $goalsQuery->where('period', $period)->get();

        $parentLink = __('Goal');
        $link = __('Edit');

        $path = base_path('resources/goal.json');

        if(!$goal){
            Session::flash('error', [
                'title' => 'Cannot update goal',
                'message' => "Goal data not found."
            ]);
            return redirect()->route('team-goals');
        }else{

            if ($goalsCheck->isEmpty() || $goalsCheck->first()->approvalRequest == null) {
                // User ID doesn't match the condition, show error message
                Session::flash('error', [
                    'title' => 'Permission Denied',
                    'message' => "You do not have permission to edit this goal."
                ]);
    
                if ($this->user != $goal->employee_id) {
                    return redirect('team-goals');
                }
                return redirect('goals');
            }

            // Check if the JSON file exists
            if (!File::exists($path)) {
                // Handle the situation where the JSON file doesn't exist
                abort(500, 'JSON file does not exist.');
            }

            $approvalRequest = ApprovalRequest::with(['employee' => function($q) {
                $q->select('id', 'fullname', 'employee_id', 'designation_name', 'job_level', 'group_company', 'unit');
            }])->where('form_id', $goal->id)->first();
            // Read the contents of the JSON file
            $formData = json_decode($goal->form_data, true);

            $formCount = count($formData);

            $options = json_decode(File::get($path), true);
            $uomOption = $options['UoM'];
            $typeOption = $options['Type'];

            $selectedUoM = [];
            $selectedType = [];
            $weightage = [];
            $totalWeightages = 0;
            
            foreach ($formData as $index => $row) {
                $selectedUoM[$index] = $row['uom'] ?? '';
                $selectedType[$index] = $row['type'] ?? '';
                $weightage[$index] = $row['weightage'] ?? '';
                $totalWeightages += (float)$weightage[$index];
            }

            $data = json_decode($goal->form_data, true);
            
            return view('pages.goals.edit', compact('goal', 'formCount', 'link', 'data', 'uomOption', 'selectedUoM', 'typeOption', 'selectedType', 'approvalRequest', 'totalWeightages', 'parentLink'));
        }

    }

    function approval($id) {

        // Mengambil data pengajuan berdasarkan employee_id atau manager_id
        $datas = ApprovalRequest::with(['employee', 'goal', 'manager', 'approval' => function ($query) {
            $query->with('approverName'); // Load nested relationship
        }])->where('form_id', $id)->get();

        $data = [];
        
        foreach ($datas as $request) {
            // Memeriksa status form dan pembuatnya
            if ($request->goal->form_status != 'Draft' || $request->created_by == Auth::user()->id) {
                // Mengambil nilai fullname dari relasi approverName
                if ($request->approval->first()) {
                    $approverName = $request->approval->first();
                    $dataApprover = $approverName->approverName->fullname;
                }else{
                    $dataApprover = '';
                }
        
                // Buat objek untuk menyimpan data request dan approver fullname
                $dataItem = new stdClass();

                $dataItem->request = $request;
                $dataItem->approver_name = $dataApprover;
              

                // Tambahkan objek $dataItem ke dalam array $data
                $data[] = $dataItem;
                
            }
        }
        
        $formData = [];
        if($datas->isNotEmpty()){
            $formData = json_decode($datas->first()->goal->form_data, true);
        }

        $path = base_path('resources/goal.json');

        // Check if the JSON file exists
        if (!File::exists($path)) {
            // Handle the situation where the JSON file doesn't exist
            abort(500, 'JSON file does not exist.');
        }

        // Read the contents of the JSON file
        $options = json_decode(File::get($path), true);

        $uomOption = $options['UoM'];
        $typeOption = $options['Type'];

        $parentLink = __('Goal');
        $link = 'Approval';

        return view('pages.goals.approval', compact('data', 'link', 'parentLink', 'formData', 'uomOption', 'typeOption'));

    }


    public function getTooltipContent(Request $request)
    {
        $approvalRequest = ApprovalRequest::with(['manager', 'employee'])->where('employee_id', $request->id)->where('category', $this->category)->first();

        if($approvalRequest){
            if ($approvalRequest->sendback_to == $approvalRequest->employee->employee_id) {
                $name = $approvalRequest->employee->fullname.' ('.$approvalRequest->employee->employee_id.')';
                $approvalLayer = '';
            }else{
                $name = $approvalRequest->manager->fullname.' ('.$approvalRequest->manager->employee_id.')';
                $approvalLayer = ApprovalLayer::where('employee_id', $approvalRequest->employee_id)->where('approver_id', $approvalRequest->current_approval_id)->value('layer');
            }
        }
        return response()->json(['name' => $name, 'layer' => $approvalLayer]);

    }

    public function unitOfMeasurement()
    {
        $uom = file_get_contents(base_path('resources/goal.json'));
        return response()->json(json_decode($uom, true));
    }

    public function import(Request $request)
    {
        // Validasi file
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        // Pastikan file terupload
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store($path='public/uploads');
            Log::info("File uploaded successfully: " . $filePath);
        } else {
            Log::error("File upload failed."); 
            return back()->with('error', "File upload failed.");
        }
        DB::enableQueryLog();
        // Jalankan proses impor
        try {
            
            $import = new GoalsDataImportManager($filePath, $this->user, $this->period);
            Excel::import($import, $filePath);
            
            // Simpan data ke database setelah semua baris diproses
            $import->saveToDatabase();
            
            // Simpan transaksi
            $import->saveTransaction();
            
            $invalidEmployees = $import->getInvalidEmployees();
            dd($invalidEmployees);
            
            $message = 'Data imported successfully.';

            if (!empty($invalidEmployees)) {
                session()->put('invalid_employees', $invalidEmployees);
                $message = 'Some of import data failed! <a href="' . route('export.invalid.goal') . '"><u>Click here to download the list of errors.</u></a>';
                return redirect()->back()->with('error', $message);
            }
            return redirect()->back()->with('success', $message);
            Log::info(Auth::id() ." Goal import : Data imported successfully.");
            
        } catch (ValidationException $e) {
            // Catch the validation exception and redirect back with a custom error message
            return redirect()->back()->with('error', $e->errors()['error'][0]);
        } catch (\Exception $e) {
            $errorMessage = "Import failed: " . $e->getMessage(); // Define a variable
            Log::error(Auth::id() . " " . $errorMessage);
            return back()->with('error', $errorMessage);
        }
        $queries = DB::getQueryLog();
        Log::info(Auth::id() ." Executed queries import goals manager: ", $queries);
        // Redirect dengan pesan sukses
        return redirect()->back()->with('success', 'Goals imported successfully!');
    }

    public function exportInvalidGoal()
    {
        // Retrieve the invalid employees from the session or another source
        $invalidEmployees = session('invalid_employees');

        if (empty($invalidEmployees)) {
            return redirect()->back()->with('success', 'No invalid employees to export.');
        }

        // Export the invalid employees to an Excel file
        return Excel::download(new InvalidGoalImport($invalidEmployees), 'errors_goal_import.xlsx');
    }

}
