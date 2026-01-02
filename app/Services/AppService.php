<?php

namespace App\Services;

use App\Models\AppraisalContributor;
use App\Models\ApprovalLayer;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Assignment;
use App\Models\Calibration;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Flow;
use App\Models\FormGroupAppraisal;
use App\Models\KpiUnits;
use App\Models\MasterCalibration;
use App\Models\MasterRating;
use App\Models\MasterWeightage;
use App\Models\Schedule;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use stdClass;

class AppService
{
    public function formGroupAppraisal($employee_id, $form_name)
    {
        $employee = EmployeeAppraisal::select('employee_id', 'group_company', 'job_level', 'company_name', 'work_area_code')->where('employee_id', $employee_id)->first();
        
        $datas = FormGroupAppraisal::with(['formAppraisals', 'rating'])->where('name', $form_name)->get();

        $data = json_decode($datas, true);

        $criteria = [
            "job_level" => $employee->job_level,
            "work_area" => $employee->work_area_code,
            "group_company" => $employee->group_company,
        ];

        $filteredData = $this->filterByRestrict($data, $criteria);
        
        return [
            'status' => 'success',
            'data' => array_values($filteredData)[0] ?? []
        ];
    }

    private function filterByRestrict($data, $criteria) {
        return array_filter($data, function ($item) use ($criteria) {
            $restrict = $item['restrict'];
    
            // Check each criterion
            $jobLevelMatch = empty($restrict['job_level']) || 
            (isset($criteria['job_level']) && in_array($criteria['job_level'], $restrict['job_level']));
            $workAreaMatch = empty($restrict['work_area']) || 
            (isset($criteria['work_area']) && in_array($criteria['work_area'], $restrict['work_area']));
            $companyMatch = empty($restrict['company_name']) || 
                            (isset($criteria['company_name']) && in_array($criteria['company_name'], $restrict['company_name']));
            $groupCompanyMatch = empty($restrict['group_company']) || 
            (isset($criteria['group_company']) && in_array($criteria['group_company'], $restrict['group_company']));
    
            // Return true if all criteria match
            return $jobLevelMatch && $workAreaMatch && $companyMatch && $groupCompanyMatch;
        });
    }

    // Function to calculate the average score
    public function averageScore(
        array $formData,
        string $contributorType,
        array $sigapWeightage360
    ) {
        $roleWeight = $sigapWeightage360[$contributorType] ?? 0;

        $totalScore = 0;
        $totalCount = 0;

        foreach ($formData as $key => $section) {

            if (!is_numeric($key) || !is_array($section)) {
                continue;
            }

            foreach ($section as $subSection) {

                if (!isset($subSection['score'])) {
                    continue;
                }

                $score = is_numeric($subSection['score'])
                    ? (float) $subSection['score']
                    : 0;

                // 🎯 APPLY 360 WEIGHTAGE
                $weightedScore = $score * ($roleWeight / 100);

                $totalScore += $weightedScore;
                $totalCount++;
            }
        }

        return $totalCount > 0 ? $totalScore / $totalCount : 0;
    }


    public function averageScoreSigap(array $formData): float
    {
        $totalScore = 0;
        $totalCount = 0;

        foreach ($formData as $section) {
            if (!is_array($section)) {
                continue;
            }

            if (array_key_exists('score', $section) && is_numeric($section['score'])) {
                $totalScore += (int) $section['score'];
                $totalCount++;
            }
        }

        return $totalCount > 0 ? $totalScore / $totalCount : 0;
    }

    

    public function evaluate($achievement, $target, $type) {
        // Ensure inputs are numeric
        if (!is_numeric($achievement) || !is_numeric($target)) {
            throw new Exception('Achievement and target must be numeric');
        }
    
        // Convert to floats for consistent handling
        $achievement = floatval($achievement);
        $target = floatval($target);
    
        switch (strtolower($type)) {
            case 'higher better':
                if ($target == 0) {
                    return $achievement > 0 ? 100 : 0;
                }
                
                return ($achievement / $target) * 100;
    
            case 'lower better':
                if ($target == 0) {
                    return $achievement <= 0 ? 100 : 0;
                }
                if ($achievement <= 0) {
                    return 100;
                }

                return (2 - ($achievement / $target)) * 100;
    
            case 'exact value':
                $epsilon = 1e-6; // Adjust based on required precision
                return abs($achievement - $target) < $epsilon ? 100 : 0;
    
            default:
                throw new Exception('Invalid type'. $type);
        }
    }

    public function conversion($evaluate) {
        if ($evaluate < 60) {
            return 1;
        } elseif ($evaluate >= 60 && $evaluate < 95) {
            return 2;
        } elseif ($evaluate >= 95 && $evaluate <= 100) {
            return 3;
        } elseif ($evaluate > 100 && $evaluate <= 120) {
            return 4;
        } else {
            return 5;
        }
    }

    public function combineFormData($appraisalData, $goalData, $typeWeightage360, $employeeData, $period) {

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $period)->first();

        if (!$weightageData) {
            throw new Exception('Weightage data not found for the specified group company and period.');
        }

        $weightageFormData = json_decode($weightageData->form_data, true);

        $totalKpiScore = 0; // Initialize the total score
        $totalCultureScore = 0; // Initialize the total score
        $totalLeadershipScore = 0; // Initialize the total score
        $totalTechnicalScore = 0; // Initialize the total score
        $cultureAverageScore = 0; // Initialize Culture average score
        $leadershipAverageScore = 0; // Initialize Culture average score
        $technicalAverageScore = 0;
        $sigapAverageScore = 0; // Initialize SIGAP average score
        
        $jobLevel = $employeeData->job_level;

        
        // Convert to array if needed
        $appraisalDatas = is_object($appraisalData) ? (array) $appraisalData : $appraisalData;
        
        // Handle both cases:
        // 1. Direct array with formData key
        // 2. Array of form data
        if (isset($appraisalDatas) && is_array($appraisalDatas)) {
            // Case 1: appraisalDatas has formData key
            $availableContributorTypes = [$typeWeightage360]; // Use the passed contributor type
        } else {
            // Case 2: appraisalDatas is form data array itself
            // Use the passed contributor type as single value
            $availableContributorTypes = [$typeWeightage360];
        }

        $sigapWeightage360Map = $this->getSigap360Weightage(
            $weightageFormData,
            $jobLevel,
            $availableContributorTypes
        );
        
        if (!empty($appraisalDatas) && is_array($appraisalDatas)) {
            foreach ($appraisalDatas['formData'] as &$form) {
                
                // Validate form structure
                if (!is_array($form) || !isset($form['formName'])) {
                    continue;
                }
                if ($form['formName'] === "KPI") {
                    foreach ($form as $key => &$entry) {
                        if (is_array($entry) && isset($goalData[$key])) {
                            $entry = array_merge($entry, $goalData[$key]);
        
                            // Adding "percentage" key
                            if (isset($entry['achievement'], $entry['target'], $entry['type'])) {
                                $entry['percentage'] = $this->evaluate($entry['achievement'], $entry['target'], $entry['type']);
                                $entry['conversion'] = $this->conversion($entry['percentage']);
                                $entry['final_score'] = $entry['conversion'] * $entry['weightage'] / 100;
        
                                // Add the final_score to the total score
                                $totalKpiScore += $entry['final_score'];
                            }
                        }
                    }
                } elseif ($form['formName'] === "Culture") {
                    // Calculate average score for Culture form
                    $cultureAverageScore = $this->averageScore(
                        $form,
                        $typeWeightage360,
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Leadership") {
                    // Calculate average score for Leadership form
                    $leadershipAverageScore = $this->averageScore(
                        $form,
                        $typeWeightage360,
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Technical") {
                    // Calculate average score for Technical form
                    $technicalAverageScore = $this->averageScore(
                        $form,
                        $typeWeightage360,
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Sigap") {
                    // Calculate average score for Sigap form
                    $sigapAverageScore = $this->averageScoreSigap($form);
                }
                
            }
        } else {
            // Handle the case where formData is null or not an array
            $appraisalDatas = []; // Optionally, set to an empty array
        }
        
        $weightageContent = json_decode($weightageData->form_data, true);
        
        $kpiWeightage = 0;
        $cultureWeightage = 0;
        $leadershipWeightage = 0;
        $technicalWeightage = 0;
        $sigapWeightage = 0;
        $kpiWeightage360 = 0;
        $cultureWeightage360 = 0;
        $leadershipWeightage360 = 0;
        $technicalWeightage360 = 0;
        $sigapWeightage360 = 0;

        foreach ($weightageContent as $item) {
            // Validate required keys exist before processing
            if (!isset($item['jobLevel']) || !is_array($item['jobLevel'])) {
                continue; // Skip invalid items
            }
            if (!isset($item['competencies']) || !is_array($item['competencies'])) {
                continue; // Skip invalid items
            }

            if (in_array($jobLevel, $item['jobLevel'])) {
                foreach ($item['competencies'] as $competency) {
                    // Validate competency structure
                    if (!is_array($competency)) {
                        continue;
                    }

                    $employeeWeightage = 0;

                    if (isset($competency['weightage360']) && is_array($competency['weightage360'])) {
                        foreach ($competency['weightage360'] as $weightage360) {
                            if (is_array($weightage360) && isset($weightage360[$typeWeightage360])) {
                                $employeeWeightage += floatval($weightage360[$typeWeightage360]);
                            }
                        }
                    }

                    // Validate required competency keys
                    if (!isset($competency['competency']) || !isset($competency['weightage'])) {
                        continue;
                    }

                    switch ($competency['competency']) {
                        case 'KPI':
                            $kpiWeightage = floatval($competency['weightage']);
                            $kpiWeightage360 = $employeeWeightage;
                            break;
                        case 'Culture':
                            $cultureWeightage = floatval($competency['weightage']);
                            $cultureWeightage360 = $employeeWeightage;
                            break;
                        case 'Leadership':
                            $leadershipWeightage = floatval($competency['weightage']);
                            $leadershipWeightage360 = $employeeWeightage;
                            break;
                        case 'Technical':
                            $technicalWeightage = floatval($competency['weightage']);
                            $technicalWeightage360 = $employeeWeightage;
                            break;
                        case 'Sigap':
                            $sigapWeightage = floatval($competency['weightage']);
                            $sigapWeightage360 = $employeeWeightage;
                            break;
                    }
                }
                break; // Exit after processing the relevant job level
            }
        }

        // Safely divide by 100 with validation
        $appraisalDatas['kpiWeightage360'] = $kpiWeightage360 ?? 0; // get KPI 360 weightage
        $appraisalDatas['cultureWeightage360'] = ($cultureWeightage360 ?? 0) > 0 ? $cultureWeightage360 / 100 : 0; // get Culture 360 weightage
        $appraisalDatas['leadershipWeightage360'] = ($leadershipWeightage360 ?? 0) > 0 ? $leadershipWeightage360 / 100 : 0; // get Leadership 360 weightage
        $appraisalDatas['technicalWeightage360'] = ($technicalWeightage360 ?? 0) > 0 ? $technicalWeightage360 / 100 : 0; // get Technical 360 weightage
        $appraisalDatas['sigapWeightage360'] = ($sigapWeightage360 ?? 0) > 0 ? $sigapWeightage360 / 100 : 0; // get SIGAP 360 weightage

        $appraisalDatas['cultureWeightage'] = $cultureWeightage ?? 0; // get Culture Weightage
        $appraisalDatas['leadershipWeightage'] = $leadershipWeightage ?? 0; // get Leadership Weightage
        $appraisalDatas['technicalWeightage'] = $technicalWeightage ?? 0; // get Technical Weightage
        $appraisalDatas['sigapWeightage'] = $sigapWeightage ?? 0; // get SIGAP Weightage
        
        // Add the total scores to the appraisalData
        $appraisalDatas['totalKpiScore'] = round($totalKpiScore, 2); // get KPI Final Score
        $appraisalDatas['totalCultureScore'] = round($cultureAverageScore, 2); // get KPI Final Score
        $appraisalDatas['totalLeadershipScore'] = round($leadershipAverageScore, 2); // get KPI Final Score
        $appraisalDatas['totalTechnicalScore'] = round($technicalAverageScore, 2);
        $appraisalDatas['totalSigapScore'] = round($sigapAverageScore  * $sigapWeightage / 100, 2);
        $appraisalDatas['cultureScore360'] = $cultureAverageScore * $cultureWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['leadershipScore360'] = $leadershipAverageScore * $leadershipWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['technicalScore360'] = $technicalAverageScore * $technicalWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['sigapScore360'] = $sigapAverageScore * $sigapWeightage360 / 100; // get SIGAP Final Score
        $appraisalDatas['cultureAverageScore'] = ($cultureAverageScore * $cultureWeightage / 100) * $appraisalDatas['cultureWeightage360']; // get Culture Average Score
        $appraisalDatas['leadershipAverageScore'] = ($leadershipAverageScore * $leadershipWeightage / 100) * $appraisalDatas['leadershipWeightage360']; // get Leadership Average Score
        $appraisalDatas['technicalAverageScore'] = ($technicalAverageScore * $technicalWeightage / 100) * $appraisalDatas['technicalWeightage360']; // get Technical Average Score
        $appraisalDatas['sigapAverageScore'] = ($sigapAverageScore * $sigapWeightage / 100) * $appraisalDatas['sigapWeightage360']; // get SIGAP Average Score
        
        $appraisalDatas['kpiScore'] = round($totalKpiScore * $kpiWeightage / 100, 2) ; // get KPI Final Score
        $appraisalDatas['cultureScore'] = round($cultureAverageScore * $cultureWeightage / 100, 2); // get KPI Final Score
        $appraisalDatas['leadershipScore'] = round($leadershipAverageScore  * $leadershipWeightage / 100, 2); // get KPI Final Score
        $appraisalDatas['technicalScore'] = round($technicalAverageScore  * $technicalWeightage / 100, 2);
        $appraisalDatas['sigapScore'] = round($sigapAverageScore  * $sigapWeightage / 100, 2);

        $scores = [$totalKpiScore,$cultureAverageScore,$leadershipAverageScore,$technicalAverageScore,$sigapAverageScore];
        // get KPI Final Score

        $appraisalDatas['totalScore'] =  $totalKpiScore + $appraisalDatas['cultureScore'] + $appraisalDatas['leadershipScore'] + $appraisalDatas['technicalScore'] + $appraisalDatas['sigapScore']; // Update

        $appraisalDatas['contributorRating'] = $appraisalDatas['totalScore']; // old

        return $appraisalDatas;
    }

    public function combineSummaryFormData($appraisalData, $goalData, $employeeData, $period) {
        $totalKpiScore = 0; // Initialize the total score
        $totalCultureScore = 0; // Initialize the total score
        $totalLeadershipScore = 0; // Initialize the total score
        $totalTechnicalScore = 0; // Initialize the total score
        $totalSigapScore = 0; // Initialize the total score
        $cultureAverageScore = 0; // Initialize Culture average score
        $leadershipAverageScore = 0; // Initialize Culture average score
        $technicalAverageScore = 0; // Initialize Culture average score
        $sigapAverageScore = 0; // Initialize SIGAP average score

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')
                ->where('period', $period)
                ->first();

        if (!$weightageData) {
            throw new Exception('Weightage data not found for the specified group company and period.');
        }

        $weightageFormData = json_decode($weightageData->form_data, true);

        // contributor yang benar-benar ada
        $availableContributorTypes = array_unique(
            array_column($appraisalData['calculated_data'], 'contributor_type')
        );

        // ambil + pooling weightage sigap
        $sigapWeightage360Map = $this->getSigap360Weightage(
            $weightageFormData,
            $jobLevel,
            $availableContributorTypes
        );

        $result = $this->averageScores($appraisalData['calculated_data']);

        foreach ($result as $appraisalDatas) {

            if (!empty($appraisalDatas['formData']) && is_array($appraisalDatas['formData'])) {

            foreach ($appraisalDatas['formData'] as $form) {
                // Validate form structure before accessing keys
                if (!is_array($form) || !isset($form['formName'])) {
                    continue;
                }
                if ($form['formName'] === "Culture") {
                    // Calculate average score for Culture form
                    $cultureAverageScore = $this->averageScore(
                        $form,
                        $appraisalDatas['contributor_type'],
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Leadership") {
                    // Calculate average score for Leadership form
                    $leadershipAverageScore = $this->averageScore(
                        $form,
                        $appraisalDatas['contributor_type'],
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Technical") {
                    // Calculate average score for Technical form
                    $technicalAverageScore = $this->averageScore(
                        $form,
                        $appraisalDatas['contributor_type'],
                        $sigapWeightage360Map
                    );
                } elseif ($form['formName'] === "Sigap") {
                    // Calculate average score for SIGAP form
                    $sigapAverageScore = $this->averageScore(
                        $form,
                        $appraisalDatas['contributor_type'],
                        $sigapWeightage360Map
                    );
                }
            }
            
            
            // Sum the culture and leadership scores across all contributor types
            $totalCultureScore += $cultureAverageScore;
            $totalLeadershipScore += $leadershipAverageScore;
            $totalTechnicalScore += $technicalAverageScore;
            $totalSigapScore += $sigapAverageScore;
        
            } else {
            // Handle the case where formData is null or not an array
            $appraisalDatas['formData'] = []; // Optionally, set to an empty array
            }
            
            $weightageContent = json_decode($weightageData->form_data, true);
        
            $kpiWeightage = 0;
            $cultureWeightage = 0;
            $leadershipWeightage = 0;
            $technicalWeightage = 0;
            $sigapWeightage = 0;
            $kpiWeightage360 = 0;
            $cultureWeightage360 = 0;
            $leadershipWeightage360 = 0;
            $technicalWeightage360 = 0;
            $sigapWeightage360 = 0;
        
            foreach ($weightageContent as $item) {
                // Validate required keys exist before processing
                if (!isset($item['jobLevel']) || !is_array($item['jobLevel'])) {
                    continue;
                }
                if (!isset($item['competencies']) || !is_array($item['competencies'])) {
                    continue;
                }

                if (in_array($jobLevel, $item['jobLevel'])) {
                    foreach ($item['competencies'] as $competency) {
                        // Validate competency structure
                        if (!is_array($competency)) {
                            continue;
                        }

                        $employeeWeightage = 0;
        
                        if (isset($competency['weightage360']) && is_array($competency['weightage360'])) {
                            foreach ($competency['weightage360'] as $weightage360) {
                                if (is_array($weightage360) && isset($weightage360[$appraisalDatas['contributor_type']])) {
                                    $employeeWeightage += floatval($weightage360[$appraisalDatas['contributor_type']]);
                                }
                            }
                        }
        
                        // Validate required competency keys
                        if (!isset($competency['competency']) || !isset($competency['weightage'])) {
                            continue;
                        }

                        switch ($competency['competency']) {
                            case 'KPI':
                                $kpiWeightage = floatval($competency['weightage']);
                                $kpiWeightage360 = $employeeWeightage;
                                break;
                            case 'Culture':
                                $cultureWeightage = floatval($competency['weightage']);
                                $cultureWeightage360 = $employeeWeightage;
                                break;
                            case 'Leadership':
                                $leadershipWeightage = floatval($competency['weightage']);
                                $leadershipWeightage360 = $employeeWeightage;
                                break;
                            case 'Technical':
                                $technicalWeightage = floatval($competency['weightage']);
                                $technicalWeightage360 = $employeeWeightage;
                                break;
                            case 'Sigap':
                                $sigapWeightage = floatval($competency['weightage']);
                                $sigapWeightage360 = $employeeWeightage;
                                break;
                        }
                    }
                break; // Exit after processing the relevant job level
            }
            }
        }
        
        // Check if result array has data before processing to avoid division by zero
        if (empty($result)) {
            // Return early with default values if no data to process
            $appraisalDatas = $appraisalData['summary'] ?? [];
            return array_merge($appraisalDatas, [
                'totalKpiScore' => 0,
                'totalCultureScore' => 0,
                'totalLeadershipScore' => 0,
                'totalTechnicalScore' => 0,
                'totalSigapScore' => 0,
                'totalScore' => 0,
                'contributorRating' => 0,
            ]);
        }

        // Calculate the average result of $totalCultureScore
        $totalCultureScore = $totalCultureScore / count($result);
        $totalLeadershipScore = $totalLeadershipScore / count($result);
        $totalTechnicalScore = $totalTechnicalScore / count($result);
        $totalSigapScore = $totalSigapScore;


        $appraisalDatas = $appraisalData['summary'];
        
        if (!empty($appraisalDatas['formData']) && is_array($appraisalDatas['formData'])) {
            foreach ($appraisalDatas['formData'] as &$form) {
                if ($form['formName'] === "KPI") {
                    foreach ($form as $key => &$entry) {
                        if (is_array($entry) && isset($goalData[$key])) {
                            $entry = array_merge($entry, $goalData[$key]);
        
                            // Adding "percentage" key
                            if (isset($entry['achievement'], $entry['target'], $entry['type'])) {
                                $entry['percentage'] = $this->evaluate($entry['achievement'], $entry['target'], $entry['type']);
                                $entry['conversion'] = $this->conversion($entry['percentage']);
                                $entry['final_score'] = $entry['conversion'] * $entry['weightage'] / 100;
        
                                // Add the final_score to the total score
                                $totalKpiScore += $entry['final_score'];
                            }
                        }
                    }
                }
            }
        } else {
            // Handle the case where formData is null or not an array
            $appraisalDatas['formData'] = []; // Optionally, set to an empty array
        }

        $appraisalDatas['kpiWeightage360'] = $kpiWeightage360 ?? 0; // get KPI 360 weightage
        $appraisalDatas['cultureWeightage360'] = ($cultureWeightage360 ?? 0) > 0 ? $cultureWeightage360 / 100 : 0; // get Culture 360 weightage
        $appraisalDatas['leadershipWeightage360'] = ($leadershipWeightage360 ?? 0) > 0 ? $leadershipWeightage360 / 100 : 0; // get Leadership 360 weightage
        $appraisalDatas['technicalWeightage360'] = ($technicalWeightage360 ?? 0) > 0 ? $technicalWeightage360 / 100 : 0; // get Technical 360 weightage
        $appraisalDatas['sigapWeightage360'] = ($sigapWeightage360 ?? 0) > 0 ? $sigapWeightage360 / 100 : 0; // get SIGAP 360 weightage
        
        // Add the total scores to the appraisalData
        $appraisalDatas['totalKpiScore'] = round($totalKpiScore, 2); // get KPI Final Score
        $appraisalDatas['totalCultureScore'] = round($totalCultureScore, 2); // get KPI Final Score
        // $appraisalDatas['totalCultureScore'] = round($cultureAverageScore * $cultureWeightage / 100 , 2); // get KPI Final Score
        $appraisalDatas['totalLeadershipScore'] = round($totalLeadershipScore, 2); // get KPI Final Score
        $appraisalDatas['totalTechnicalScore'] = round($totalTechnicalScore, 2); // get KPI Final Score
        $appraisalDatas['totalSigapScore'] = round($totalSigapScore * $sigapWeightage / 100, 2); // get SIGAP Final Score
        // $appraisalDatas['totalLeadershipScore'] = round($leadershipAverageScore * $leadershipWeightage / 100 , 2); // get KPI Final Score
        $appraisalDatas['cultureScore360'] = $cultureAverageScore * $cultureWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['leadershipScore360'] = $leadershipAverageScore * $leadershipWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['technicalScore360'] = $technicalAverageScore * $technicalWeightage360 / 100; // get KPI Final Score
        $appraisalDatas['sigapScore360'] = $sigapAverageScore * $sigapWeightage360 / 100; // get SIGAP Final Score
        $appraisalDatas['cultureAverageScore'] = ($cultureAverageScore * $cultureWeightage / 100) * $appraisalDatas['cultureWeightage360']; // get Culture Average Score
        $appraisalDatas['leadershipAverageScore'] = ($leadershipAverageScore * $leadershipWeightage / 100) * $appraisalDatas['leadershipWeightage360']; // get Leadership Average Score
        $appraisalDatas['technicalAverageScore'] = ($technicalAverageScore * $technicalWeightage / 100) * $appraisalDatas['technicalWeightage360']; // get Technical Average Score
        $appraisalDatas['sigapAverageScore'] = ($sigapAverageScore * $sigapWeightage / 100) * $appraisalDatas['sigapWeightage360']; // get SIGAP Average Score

        
        $appraisalDatas['kpiScore'] = $totalKpiScore * $kpiWeightage / 100; // get KPI Final Score
        $appraisalDatas['cultureScore'] = $totalCultureScore * $cultureWeightage / 100; // get KPI Final Score
        $appraisalDatas['leadershipScore'] = $totalLeadershipScore  * $leadershipWeightage / 100; // get KPI Final Score
        $appraisalDatas['technicalScore'] = $totalTechnicalScore  * $technicalWeightage / 100; // get KPI Final Score
        $appraisalDatas['sigapScore'] = $totalSigapScore  * $sigapWeightage / 100; // get SIGAP Final Score

        $scores = [$totalKpiScore,$cultureAverageScore,$leadershipAverageScore,$totalTechnicalScore,$totalSigapScore];
        // get KPI Final Score

        $appraisalDatas['totalScore'] =  round($totalKpiScore + $appraisalDatas['cultureScore'] + $appraisalDatas['leadershipScore'] + $appraisalDatas['technicalScore'] + $appraisalDatas['sigapScore'], 2); // Update

        $appraisalDatas['contributorRating'] = $appraisalDatas['totalScore'];
    
        return $appraisalDatas;
    }

    private function getSigap360Weightage(
        array $weightageFormData,
        string $jobLevel,
        array $availableContributorTypes
    ): array {
        $weightages = [
            'employee' => 0,
            'manager' => 0,
            'peers' => 0,
            'subordinate' => 0,
        ];

        foreach ($weightageFormData as $item) {
            if (!in_array($jobLevel, $item['jobLevel'])) {
                continue;
            }

            foreach ($item['competencies'] as $competency) {
                if ($competency['competency'] !== 'Sigap') {
                    continue;
                }

                foreach ($competency['weightage360'] as $row) {
                    foreach ($row as $role => $value) {
                        $weightages[$role] = (float) $value;
                    }
                }
                break;
            }
            break;
        }

        /**
         * ============================
         * POOL UNUSED WEIGHTAGE
         * ============================
         */
        $pooled = 0;

        foreach ($weightages as $role => $value) {
            if ($role !== 'manager' && $value > 0 && !in_array($role, $availableContributorTypes)) {
                $pooled += $value;
                $weightages[$role] = 0;
            }
        }

        $weightages['manager'] += $pooled;

        return $weightages;
    }


    function averageScores(array $data): array
    {
        // Group data by contributor_type
        $groupedData = [];
        foreach ($data as $entry) {
            $contributorType = $entry['contributor_type'];
            $groupedData[$contributorType][] = $entry;
        }

        $result = [];
        foreach ($groupedData as $contributorType => $entries) {
            // Clone the structure from the first entry
            $mergedEntry = $entries[0];
            $mergedEntry['formData'] = [];

            foreach ($entries[0]['formData'] as $index => $formData) {
                $mergedFormData = $formData;
                $mergedFormData[0] = [];

                // Ensure the current formData is properly structured
                if (!isset($formData[0]) || !is_array($formData[0])) {
                    continue;
                }

                // Calculate averages for all scores in the same index
                foreach ($formData[0] as $scoreIndex => $scoreData) {
                    // Ensure the scoreData has the expected structure
                    if (!isset($scoreData['score']) || !is_numeric($scoreData['score'])) {
                        continue;
                    }

                    $totalScore = 0;
                    $count = 0;

                    foreach ($entries as $entry) {
                        // Validate structure before accessing
                        if (isset($entry['formData'][$index][0][$scoreIndex]['score']) &&
                            is_numeric($entry['formData'][$index][0][$scoreIndex]['score'])) {
                            $totalScore += $entry['formData'][$index][0][$scoreIndex]['score'];
                            $count++;
                        }
                    }

                    // Avoid division by zero
                    if ($count > 0) {
                        $mergedFormData[0][$scoreIndex] = [
                            'score' => $totalScore / $count,
                        ];
                    }
                }

                // Push merged form data
                $mergedEntry['formData'][] = $mergedFormData;
            }

            $result[] = $mergedEntry;
        }

        return $result;
    }

    // Function to merge scores
    function mergeScores($formData, $filteredFormData) {

        foreach ($formData['formData'] as $formData) {
            $formName = $formData['formName'];
            foreach ($filteredFormData as &$section) {
                if ($section['name'] === $formName) {
                    foreach ($formData as $key => $value) {
                        if (is_numeric($key)) {
                            if (isset($value['score'])) {
                                if (is_array($value['score'])) {
                                    foreach ($value['score'] as $scoreIndex => $scoreValue) {
                                        if (isset($section['data'][$key]['score'][$scoreIndex])) {
                                            $section['data'][$key]['score'][$scoreIndex] += $scoreValue;
                                        } else {
                                            $section['data'][$key]['score'][$scoreIndex] = $scoreValue;
                                        }
                                    }
                                } else {
                                    // Handle single score value (for Sigap or other forms)
                                    $section['data'][$key]['score'] = $value['score'] ?? null;

                                }
                            }
                        }
                    }
                }
            }
        }
        return $filteredFormData;
    }

    function formatDate($date)
    {
        // Parse the date using Carbon
        $carbonDate = Carbon::parse($date);

        // Check if the date is today
        if ($carbonDate->isToday()) {
            return 'Today ' . $carbonDate->format('ga');
        } else {
            return $carbonDate->format('d M Y');
        }
    }

    private function mergeFormData(array $formDataSets)
    {
        $mergedData = [];

        foreach ($formDataSets as $formData) {
            foreach ($formData['formData'] as $form) {

                $formName = $form['formName'];

                // Check if formName already exists in the merged data
                $existingFormIndex = collect($mergedData)->search(function ($item) use ($formName) {
                    return $item['formName'] === $formName;
                });

                if ($existingFormIndex !== false) {
                    // Merge scores for the existing form
                    foreach ($form as $key => $scores) {
                        if (is_numeric($key)) {
                            $mergedData[$existingFormIndex][$key] = array_merge($mergedData[$existingFormIndex][$key] ?? [], $scores);
                        }
                    }
                } else {
                    // Add the form to the merged data
                    $mergedData[] = $form;
                }
            }
        }

        return [
            'formGroupName' => 'Appraisal Form',
            'formData' => $mergedData
        ];

    }

    function suggestedRating($id, $formId)
    {
        try {

            $datasQuery = AppraisalContributor::with(['employee'])->where('appraisal_id', $formId);
                $datas = $datasQuery->get();

                $checkSnapshot = ApprovalSnapshots::where('form_id', $formId)->where('created_by', $datas->first()->employee->id)
                    ->orderBy('created_at', 'desc');

                // Check if `datas->first()->employee->id` exists
                if ($checkSnapshot) {
                    $query = $checkSnapshot;
                }else{
                    $query = ApprovalSnapshots::where('form_id', $formId)
                    ->orderBy('created_at', 'asc');
                }
                
                $employeeForm = $query->first();

                $data = [];
                $appraisalDataCollection = [];
                $goalDataCollection = [];

                $formGroupContent = $this->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form');
                
                if (!$formGroupContent) {
                    $appraisalForm = ['data' => ['formData' => []]];
                } else {
                    $appraisalForm = $formGroupContent;
                }
                
                $cultureData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
                $leadershipData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
                $technicalData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Technical') ?? [];
                $sigapData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];                
                
                if($employeeForm){

                    // Create data item object
                    $dataItem = new stdClass();
                    $dataItem->request = $employeeForm;
                    $dataItem->name = $employeeForm->name;
                    $dataItem->goal = $employeeForm->goal;
                    $data[] = $dataItem;
    
                    // Get appraisal form data for each record
                    $appraisalData = [];
                    
                    if ($employeeForm->form_data) {
                        $appraisalData = json_decode($employeeForm->form_data, true);
                        $contributorType = $employeeForm->contributor_type;
                        $appraisalData['contributor_type'] = 'employee';
                    }
    
                    // Get goal form data for each record
                    $goalData = [];
                    if ($employeeForm->goal && $employeeForm->goal->form_data) {
                        $goalData = json_decode($employeeForm->goal->form_data, true);
                        $goalDataCollection[] = $goalData;
                    }
                    
                    // Combine the appraisal and goal data for each contributor
                    $employeeData = $employeeForm->employee; // Get employee data
            
                    $formData[] = $appraisalData;

                }
                
                foreach ($datas as $request) {

                    // Create data item object
                    $dataItem = new stdClass();
                    $dataItem->request = $request;
                    $dataItem->name = $request->name;
                    $dataItem->goal = $request->appraisal->goal;
                    $data[] = $dataItem;
                    
                    // Get appraisal form data for each record
                    $appraisalData = [];

                    if ($request->form_data) {
                        $appraisalData = json_decode($request->form_data, true);
                        $contributorType = $request->contributor_type;
                        $appraisalData['contributor_type'] = $contributorType;
                    }

                    // Get goal form data for each record
                    $goalData = [];
                    if ($request->appraisal->goal && $request->appraisal->goal->form_data) {
                        $goalData = json_decode($request->appraisal->goal->form_data, true);
                        $goalDataCollection[] = $goalData;
                    }
                    
                    // Combine the appraisal and goal data for each contributor
                    $employeeData = $request->employee; // Get employee data
            
                    $formData[] = $appraisalData;

                }

                $jobLevel = $employeeData->job_level;

                $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $request->period)->first();
                            
                $weightageContent = json_decode($weightageData->form_data, true);
                
                $result = $this->appraisalSummary($weightageContent, $formData, $employeeData->employee_id, $jobLevel);     // Call the appraisal summary method
                
                $formData = $this->combineSummaryFormData($result, $goalData, $employeeData, $request->period);

                if (isset($formData['totalKpiScore'])) {
                    $formData['kpiScore'] = round($formData['kpiScore'], 2);
                    $formData['cultureScore'] = round($formData['cultureScore'], 2);
                    $formData['leadershipScore'] = round($formData['leadershipScore'], 2);
                    $formData['technicalScore'] = round($formData['technicalScore'], 2);
                    $formData['sigapScore'] = round($formData['sigapScore'], 2);
                }

                foreach ($formData['formData'] as &$form) {
                    if ($form['formName'] === 'Leadership') {
                        foreach ($leadershipData as $index => $leadershipItem) {
                            foreach ($leadershipItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => $form[$index][$itemIndex]['score']
                                    ];
                                }
                            }
                            $form[$index]['title'] = $leadershipItem['title'];
                        }
                    }
                    if ($form['formName'] === 'Technical') {
                        foreach ($technicalData as $index => $technicalItem) {
                            foreach ($technicalItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => $form[$index][$itemIndex]['score']
                                    ];
                                }
                            }
                            $form[$index]['title'] = $technicalItem['title'];
                        }
                    }
                    
                    if ($form['formName'] === 'Culture') {
                        foreach ($cultureData as $index => $cultureItem) {
                            foreach ($cultureItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => $form[$index][$itemIndex]['score']
                                    ];
                                }
                            }
                            $form[$index]['title'] = $cultureItem['title'];
                        }
                    }

                    if ($form['formName'] === 'Sigap') {
                        foreach ($sigapData as $index => $sigapItem) {
                            foreach ($sigapItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => $form[$index][$itemIndex]['score']
                                    ];
                                }
                            }
                            $form[$index]['title'] = $sigapItem['title'];
                            $form[$index]['items'] = $sigapItem['items'];
                        }
                    }
                
                }       
                
                $appraisalData = $formData['totalScore'];
            
            return $appraisalData;

        }catch (\Exception $e) {
            // Log the error and return an appropriate message or value
            Log::error('Error calculating suggested rating: ' . $e->getMessage());
            return 0;  // You can also return null or any fallback value as needed
        }

    }

    public function convertRating(float $value, $formID): ?string
    {
        $formGroup = MasterCalibration::where('id_calibration_group', $formID)->first();
        
        $condition = null;
        
        $roundedValue = (int) round($value);

        if ($value == 0) {
            // If value is 0, get the record with the smallest value
            $condition = MasterRating::orderBy('value', 'asc')->first();
        } else {
            // Otherwise, proceed with the original query logic
            $condition = MasterRating::where(function ($query) use ($formGroup, $value) {
                $query->where('id_rating_group', $formGroup->id_rating_group)
                      ->where('min_range', '<=', $value)
                      ->where('max_range', '>=', $value);
            })
            ->orWhere(function ($query) use ($formGroup, $roundedValue) {
                        $query->where('id_rating_group', $formGroup->id_rating_group)
                              ->where('min_range', 0)
                              ->where('max_range', 0)
                              ->where('value', $roundedValue); // Use rounded value here
                    })
                    ->orderBy('min_range', 'desc')
                    ->first();
        }

        return $condition ? $condition->parameter : null;
    }

    public function processApproval($employee, $approver)
    {
        $currentLayer = ApprovalLayerAppraisal::where('employee_id', $employee)
                        ->where('approver_id', $approver)
                        ->where('layer_type', 'calibrator')
                        ->orderBy('layer', 'asc')
                        ->first();

        $nextLayer = [];
        if ($currentLayer) {
            // Find the next approver in the sequence
            $nextLayer = ApprovalLayerAppraisal::where('employee_id', $employee)
                            ->where('layer', '>', $currentLayer->layer)
                            ->where('layer_type', 'calibrator')
                            ->orderBy('layer', 'asc')
                            ->first();
        }

        return $nextLayer ? [
            'current_approver_id' => $currentLayer->approver_id,
            'next_approver_id' => $nextLayer->approver_id,
            'layer' => $nextLayer->layer,
        ] : null; // null means finish the calibrator layer.

    }

    public function ratingValue($employee, $approver, $period)
    {
        $rating = Calibration::select('appraisal_id','employee_id', 'approver_id', 'rating', 'status', 'period')
                        ->where('employee_id', $employee)
                        ->where('approver_id', $approver)
                        ->where('status', 'Approved')
                        ->where('period', $period)
                        ->first();

        return $rating ? $rating->rating : null; // null means finish the calibrator layer.

    }

    // public function ratingValue($employee, $approver, $period)
    // {

    //     $rating = Calibration::with(['masterCalibration'])
    //                     ->where('employee_id', $employee)
    //                     ->where('approver_id', $approver)
    //                     ->where('status', 'Approved')
    //                     ->where('period', $period)
    //                     ->first();
                        
    //     $id_rating = $rating->masterCalibration->first()->id_rating_group;
        
    //     $ratings = MasterRating::select('parameter', 'value')
    //                 ->where('id_rating_group', $id_rating)
    //                 ->get();
        
    //     $ratingMap = $ratings->pluck('parameter', 'value')->toArray();

    //     $convertedValue = $ratingMap[$rating->rating] ?? null;

    //     return $rating ? $convertedValue : null; // null means finish the calibrator layer.

    // }

    public function ratingAllowedCheck($employeeId)
    {
        // Cari data pada ApprovalLayerAppraisal berdasarkan employee_id
        $approvalLayerAppraisals = ApprovalLayerAppraisal::with(['approver', 'employee'])->where('employee_id', $employeeId)->where('layer_type', '!=', 'calibrator')->get();
        
        // Simpan data yang tidak ada di AppraisalContributor
        $notFoundData = [];
        
        foreach ($approvalLayerAppraisals as $approvalLayer) {
            
            $review360 = json_decode($approvalLayer->employee->access_menu, true);

            // if (!array_key_exists('review360', $review360)) {
            //     $review360['review360'] = 0;
            // }
            
            // Cek apakah kombinasi employee_id dan approver_id tidak ada di AppraisalContributor
            $appraisalContributor = AppraisalContributor::where('employee_id', $approvalLayer->employee_id)
                                                        ->where('contributor_id', $approvalLayer->approver_id)
                                                        ->where('period', $this->appraisalPeriod())
                                                        ->first();
            // Jika tidak ditemukan, tambahkan ke notFoundData
            if (is_null($appraisalContributor) && isset($review360['review360']) && $review360['review360'] == 0) {
                $notFoundData[] = [
                    'employee_id'  => $approvalLayer->employee_id,
                    'approver_id'  => $approvalLayer->approver_id,
                    'approver_name'  => $approvalLayer->approver->fullname,
                    'layer_type'   => $approvalLayer->layer_type,
                ];
            }
        }
        
        // Jika ada data yang tidak ditemukan di AppraisalContributor, kembalikan datanya
        if (!empty($notFoundData)) {
            return [
                'status' => false,
                'message' => '360 Review incomplete process',
                'data' => $notFoundData
            ];
        }
        
        // Jika semua data ada, kembalikan pesan data lengkap
        return [
            'status' => true,
            'message' => '360 Review completed',
            'data' => $review360['review360'],
        ];
    }

    public function goalPeriod()
    {
        $today = Carbon::today()->toDateString();

        $period = Schedule::where('event_type', 'goals')
                        ->orderBy('id', 'desc')
                        ->value('schedule_periode');

        return $period;
    }

    public function goalActivePeriod()
    {
        $today = Carbon::today()->toDateString();

        $period = Schedule::where('event_type', 'goals')
                        ->where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->orderBy('id', 'desc')
                        ->value('schedule_periode');
        return $period;
    }

    public function appraisalPeriod()
    {
        $today = Carbon::today()->toDateString();

        $period = Schedule::where('event_type', 'masterschedulepa')
                        ->orderBy('id', 'desc')
                        ->value('schedule_periode');
        return $period;
    }

    public function proposed360()
    {
        return $this->checkFlowAccess('Propose 360');
    }

    public function checkFlowAccess(string $moduleTransaction): bool
    {
        $user = Auth::user();

        $employee = $user->employee ?? null;

        if (!$employee) {
            return false;
        }

        $flow = Flow::where('module_transaction', $moduleTransaction)->first();

        if (!$flow) {
            return false;
        }

        $assignmentIds = json_decode($flow->assignments, true) ?? [];
        $assignments = Assignment::whereIn('id', $assignmentIds)->get();

        foreach ($assignments as $assignment) {
            $restriction = json_decode($assignment->restriction, true) ?? [];

            $pass = true;
            foreach ($restriction as $field => $allowedValues) {
                if (!in_array($employee->{$field} ?? null, $allowedValues, true)) {
                    $pass = false;
                    break;
                }
            }

            if ($pass) {
                return true; // kalau ada satu assignment yang match, lolos
            }
        }

        return false;
    }

    public function checkKpiUnit(): bool 
    {
        $user = KpiUnits::with(['masterCalibration' => function($query) {
                $query->where('period', $this->appraisalPeriod());
            }])->where('employee_id', Auth::user()->employee_id)->where('status_aktif', 'T')->where('periode', $this->appraisalPeriod())->first();

        if (!$user) return false;

        // rule Anda di sini
        return true; // contoh
    }

    public function appraisalActivePeriod()
    {
        $today = Carbon::today()->toDateString();

        $period = Schedule::where('event_type', 'masterschedulepa')
                        ->where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->orderBy('id', 'desc')
                        ->value('schedule_periode');
        return $period;
    }

    public function getDataByName($data, $name) {
        foreach ($data as $item) {
            if ($item['name'] === $name) {
                return $item['data'];
            }
        }
        return null;
    }

    public function getNotificationCountsGoal($user, $filterYear)
    {
        $period = $filterYear && $this->goalPeriod() != $filterYear ? null : $this->goalPeriod();
        
        $category = 'Goals';

        $tasks = ApprovalRequest::where([
            ['current_approval_id', $user],
            ['period', $period],
            ['category', $category],
            ['status', 'Pending'],
        ])
        ->whereHas('goal', function ($query) {
            $query->where('form_status', 'Submitted');
        })
        ->whereHas('employee', function ($query) {
            $query->whereNull('deleted_at');
        })
        ->get();
        
        $isApprover = $tasks->count();
        
        // Output the result
        return $isApprover;
    }

    public function getNotificationCountsAppraisal($user, $filterYear)
    {
        $period = $filterYear && $this->appraisalPeriod() != $filterYear ? null : $this->appraisalPeriod();

        // Count for teams notifications
        $dataTeams = ApprovalLayerAppraisal::with(['approver', 'contributors' => function($query) use ($user, $period) {
            $query->where('contributor_id', $user)->where('period', $period);
        }, 'goal' => function($query) use ($period) {
            $query->where('period', $period);
        }])
        ->where('approver_id', $user)
        ->where('layer_type', 'manager')
        ->whereHas('employee', function ($query) {
            $query->where(function($q) {
                $q->whereRaw('json_valid(access_menu)')
                  ->whereJsonContains('access_menu', ['createpa' => 1]);
            });
        })
        ->whereDoesntHave('appraisal', function ($query) {
            $query->where('form_status', 'Draft');
        })
        ->get();

        $notifTeams = $dataTeams->filter(function ($item) {
            return $item->contributors->isEmpty() && $item->goal->isNotEmpty();
        })->count();
        
        // Count for 360 appraisal notifications
        $data360 = ApprovalLayerAppraisal::with(['approver', 'contributors' => function($query) use ($user, $period) {
            $query->where('contributor_id', $user)->where('period', $period);
        }, 'appraisal' => function($query) use ($period) {
            $query->where('period', $period);
        }])
        ->where('approver_id', $user)
            ->whereNotIn('layer_type', ['manager', 'calibrator'])
            ->whereHas('employee', function ($query) {
                $query->where(function($q) {
                    $q->whereRaw('json_valid(access_menu)')
                      ->whereJsonContains('access_menu', ['createpa' => 1]);
                });
            })
            ->get()
            ->filter(function ($item) {
                return $item->appraisal != null && $item->contributors->isEmpty();
            });
        
        $notif360 = $data360->count();

        $notifData = $notifTeams + $notif360;
        
        return $notifData;
    }

    function appraisalSummary($weightages, $formData, $employeeID, $jobLevel) {

        $calculatedFormData = [];

        $checkLayer = ApprovalLayerAppraisal::where('employee_id', $employeeID)
        ->where('layer_type', '!=', 'calibrator')
        ->selectRaw('layer_type, COUNT(*) as count')
        ->groupBy('layer_type')
        ->get();

        $layerCounts = $checkLayer->pluck('count', 'layer_type')->toArray();

        $managerCount = $layerCounts['manager'] ?? 0;
        $peersCount = $layerCounts['peers'] ?? 0;
        $subordinateCount = $layerCounts['subordinate'] ?? 0;

        $calculatedFormData = []; // Initialize result array

        // Loop through $formData first to structure results by formGroupName and contributor_type
        foreach ($formData as $data) {
            // Validate data structure
            if (!is_array($data) || !isset($data['contributor_type']) || !isset($data['formData'])) {
                continue;
            }

            $contributorType = $data['contributor_type'];
            $formGroupName = $data['formGroupName'] ?? 'Unknown';
            $formDataWithCalculatedScores = []; // Array to store calculated scores for the group

            foreach ($data['formData'] as &$form) {
                // Validate form structure
                if (!is_array($form) || !isset($form['formName'])) {
                    continue;
                }

                $formName = $form['formName'];
                $calculatedForm = ["formName" => $formName];
                if ($formName === "KPI") {
                    // Directly copy KPI achievements
                    foreach ($form as $key => $achievement) {
                        if (is_numeric($key)) {
                            $calculatedForm[$key] = $achievement;
                        }
                    }
                } else {
                    // Process other forms
                    foreach ($weightages as $item) {
                        // Validate item structure
                        if (!is_array($item) || !isset($item['jobLevel']) || !isset($item['competencies'])) {
                            continue;
                        }

                        if (in_array($jobLevel, $item['jobLevel'])) {
                            foreach ($item['competencies'] as $competency) {
                                // Validate competency structure
                                if (!is_array($competency) || !isset($competency['competency'])) {
                                    continue;
                                }

                                if ($competency['competency'] == $formName) {
                                    // Handle weightage360
                                    $weightage360 = 0;
    
                                    if (isset($competency['weightage360']) && is_array($competency['weightage360'])) {
                                        // Extract weightages for each type
                                        $weightageValues = collect($competency['weightage360'])->flatMap(function ($weightage) {
                                            return is_array($weightage) ? $weightage : [];
                                        });
    
                                        $weightage360 = $weightageValues[$contributorType] ?? 0;
    
                                        if ($contributorType == 'manager') {
                                            if ($subordinateCount > 0) {
                                                $weightage360 ?? 0;
                                            }
                                            // Adjust weightages
                                            if ($subordinateCount == 0) {
                                                $weightage360 += $weightageValues['subordinate'] ?? 0;
                                            }
                                            if ($peersCount == 0) {
                                                $weightage360 += $weightageValues['peers'] ?? 0;
                                            }
                                        }
                                    }
    
                                    // Calculate weighted scores
                                    foreach ($form as $key => $scores) {
                                        if (is_numeric($key) && is_array($scores)) {
                                            $calculatedForm[$key] = [];
                                            foreach ($scores as $scoreData) {
                                                if (is_array($scoreData) && isset($scoreData['score'])) {
                                                    $score = floatval($scoreData['score']);
                                                    $weightedScore = $score;
                                                    $calculatedForm[$key][] = ["score" => $weightedScore];
                                                } elseif (is_numeric($scoreData)) {
                                                    $score = floatval($scoreData);
                                                    $weightedScore = $score;
                                                    $calculatedForm[$key][] = ["score" => $weightedScore];
                                                }
                                            }
                                        }
                                    }
                                    // foreach ($form as $key => $value) {
                                    //     if (!is_numeric($key)) {
                                    //         continue;
                                    //     }

                                    //     // SIGAP → single score
                                    //     if (isset($value['score'])) {
                                    //         $score = is_numeric($value['score']) ? (int) $value['score'] : 0;

                                    //         $weightedScore = $score;

                                    //         $calculatedForm[$key][] = [
                                    //             'score' => $weightedScore
                                    //         ];
                                    //     }

                                    //     // TECHNICAL / 360 → array of scores
                                    //     elseif (is_array($value)) {
                                    //         foreach ($value as $scoreData) {
                                    //             if (!isset($scoreData['score'])) {
                                    //                 continue;
                                    //             }

                                    //             $score = is_numeric($scoreData['score'])
                                    //                 ? (int) $scoreData['score']
                                    //                 : 0;

                                    //             $weightedScore = $score;

                                    //             $calculatedForm[$key][] = [
                                    //                 'score' => $weightedScore
                                    //             ];
                                    //         }
                                    //     }
                                    // }

                                }
                            }
                        }
                    }
                }

                
                $formDataWithCalculatedScores[] = $calculatedForm;
            }
            $calculatedFormData[] = [
                "formGroupName" => $formGroupName,
                "formData" => $formDataWithCalculatedScores,
                "contributor_type" => $contributorType
            ];

        }
        
        // Second part: Calculate summary averages
        $averages = [];
        // ===============================
        // 1. BUILD WEIGHTAGE MAP (SIGAP)
        // ===============================
        $weightage360Map = [];

        foreach ($weightages[0]['competencies'] as $competency) {
            if ($competency['competency'] === 'Sigap') {
                foreach ($competency['weightage360'] as $row) {
                    foreach ($row as $role => $value) {
                        $weightage360Map[$role] = (float) $value;
                    }
                }
            }
        }

        // ===============================
        // 2. GET EXISTING CONTRIBUTOR TYPES
        // ===============================
        $existingContributorTypes = [];

        foreach ($calculatedFormData as $contributorData) {
            $existingContributorTypes[] = $contributorData['contributor_type'];
        }

        // ===============================
        // 3. POOL UNUSED WEIGHTAGE TO MANAGER
        // ===============================
        $pooledWeightToManager = 0;

        foreach ($weightage360Map as $role => $weight) {
            if ($role !== 'manager' && !in_array($role, $existingContributorTypes)) {
                $pooledWeightToManager += $weight;
                unset($weightage360Map[$role]);
            }
        }

        $weightage360Map['manager'] =
            ($weightage360Map['manager'] ?? 0) + $pooledWeightToManager;

        // ===============================
        // 4. PROCESS CONTRIBUTOR DATA
        // ===============================
        $summedScores = [];

        foreach ($calculatedFormData as $contributorData) {

            $contributorType = $contributorData['contributor_type'];
            $roleWeight = $weightage360Map[$contributorType] ?? 0;

            foreach ($contributorData['formData'] as $form) {

                $formName = $form['formName'];

                // ===============================
                // KPI (ONLY FROM MANAGER)
                // ===============================
                if ($formName === 'KPI') {

                    if ($contributorType === 'manager') {
                        foreach ($form as $key => $value) {
                            if (is_numeric($key)) {
                                $summedScores[$formName][$key] = [
                                    'achievement' => $value['achievement'] ?? null
                                ];
                            }
                        }
                    }

                    continue;
                }

                // ===============================
                // SIGAP / CULTURE / LEADERSHIP
                // ===============================
                foreach ($form as $key => $values) {

                    if (!is_numeric($key)) {
                        continue;
                    }

                    if (!isset($summedScores[$formName][$key])) {
                        $summedScores[$formName][$key] = [];
                    }

                    /**
                     * ─────────────────────────────
                     * SIGAP → single score
                     * Pattern:
                     * 0 => ['score' => 5]
                     * ─────────────────────────────
                     */
                    if (isset($values['score'])) {

                        $score = is_numeric($values['score']) ? (float) $values['score'] : 0;
                        $weightedScore = $score * ($roleWeight / 100);

                        if (!isset($summedScores[$formName][$key][0])) {
                            $summedScores[$formName][$key][0] = [
                                'score' => 0,
                                'count' => 0
                            ];
                        }

                        $summedScores[$formName][$key][0]['score'] += $weightedScore;
                        $summedScores[$formName][$key][0]['count']++;

                        $summedScores[$formName][$key][0]['average'] =
                            $summedScores[$formName][$key][0]['score']
                            / $summedScores[$formName][$key][0]['count'];

                        continue;
                    }

                    /**
                     * ─────────────────────────────
                     * NON-SIGAP → multi score
                     * Pattern:
                     * 0 => [
                     *   ['score' => 3],
                     *   ['score' => 4]
                     * ]
                     * ─────────────────────────────
                     */
                    foreach ($values as $index => $scoreData) {

                        if (!isset($scoreData['score'])) {
                            continue;
                        }

                        if (!isset($summedScores[$formName][$key][$index])) {
                            $summedScores[$formName][$key][$index] = [
                                'score' => 0,
                                'count' => 0
                            ];
                        }

                        $score = is_numeric($scoreData['score']) ? (float) $scoreData['score'] : 0;
                        $weightedScore = $score * ($roleWeight / 100);

                        $summedScores[$formName][$key][$index]['score'] += $weightedScore;
                        $summedScores[$formName][$key][$index]['count']++;

                        $summedScores[$formName][$key][$index]['average'] =
                            $summedScores[$formName][$key][$index]['score']
                            / $summedScores[$formName][$key][$index]['count'];
                    }
                }
            }
        }

        // Format the summary response
        $summary = [
            "formGroupName" => "Appraisal Form",
            "formData" => [],
            "contributor_type" => "summary"
        ];

        // Add KPI first if exists
        if (isset($summedScores['KPI'])) {
            $kpiForm = [
                "formName" => "KPI"
            ];
            foreach ($summedScores['KPI'] as $key => $value) {
                $kpiForm[$key] = $value; // Include KPI data in the summary
            }
            $summary['formData'][] = $kpiForm; // Add KPI to the summary
        }

        // Add Culture and Leadership
        foreach (['Culture', 'Leadership', 'Technical', 'Sigap'] as $formName) {
            if (isset($summedScores[$formName])) {
                $form = [
                    "formName" => $formName
                ];
                foreach ($summedScores[$formName] as $key => $scores) {
                    $form[$key] = $scores; // Include Culture or Leadership data in the summary
                }
                $summary['formData'][] = $form; // Add Culture or Leadership to the summary
            }
        }
        
        // Return both calculated data and summary
        return [
            'calculated_data' => $calculatedFormData,
            'summary' => $summary
        ];
    }

    function appraisalSummaryWithout360Calculation($weightages, $formData, $employeeID, $jobLevel) {

        $calculatedFormData = [];

        $checkLayer = ApprovalLayerAppraisal::where('employee_id', $employeeID)
        ->where('layer_type', '!=', 'calibrator')
        ->selectRaw('layer_type, COUNT(*) as count')
        ->groupBy('layer_type')
        ->get();

        $layerCounts = $checkLayer->pluck('count', 'layer_type')->toArray();

        $managerCount = $layerCounts['manager'] ?? 0;
        $peersCount = $layerCounts['peers'] ?? 0;
        $subordinateCount = $layerCounts['subordinate'] ?? 0;

        $calculatedFormData = []; // Initialize result array

        // Loop through $formData first to structure results by formGroupName and contributor_type
        foreach ($formData as $data) {
            $contributorType = $data['contributor_type'];
            $formGroupName = $data['formGroupName'];
            $formDataWithCalculatedScores = []; // Array to store calculated scores for the group

            foreach ($data['formData'] as $form) {
                $formName = $form['formName'];
                $calculatedForm = ["formName" => $formName];
                if ($formName === "KPI") {
                    // Directly copy KPI achievements
                    foreach ($form as $key => $achievement) {
                        if (is_numeric($key)) {
                            $calculatedForm[$key] = $achievement;
                        }
                    }
                } else {
                    // Process other forms
                    foreach ($weightages as $item) {
                        if(in_array($jobLevel, $item['jobLevel'])){
                            foreach ($item['competencies'] as $competency) {
                                if ($competency['competency'] == $formName) {
                                    // Handle weightage360
                                    $weightage360 = 0;
    
                                    if (isset($competency['weightage360'])) {
                                        // Extract weightages for each type
                                        $weightageValues = collect($competency['weightage360'])->flatMap(function ($weightage) {
                                            return $weightage;
                                        });
    
                                        $weightage360 = $weightageValues[$contributorType] ?? 0;
    
                                        if($contributorType == 'manager'){
                                            if ($subordinateCount > 0) {
                                                $weightage360 ?? 0;
                                            }
                                            // Adjust weightages
                                            if ($subordinateCount == 0) {
                                                $weightage360 += $weightageValues['subordinate'] ?? 0;
                                            }
                                            if ($peersCount == 0) {
                                                $weightage360 += $weightageValues['peers'] ?? 0;
                                            }
                                            // if ($subordinateCount == 0 && $peersCount == 0) {
                                            //     $weightage360 += ($weightageValues['subordinate'] ?? 0) + ($weightageValues['peers'] ?? 0);
                                            // }
                                        }

                                    }
    
                                    // Calculate weighted scores
                                    foreach ($form as $key => $scores) {
                                        if (is_numeric($key)) {
                                            $calculatedForm[$key] = [];
                                            foreach ($scores as $scoreData) {
                                                $score = $scoreData['score'];
                                                $weightedScore = $score;
                                                $calculatedForm[$key][] = ["score" => $weightedScore];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $formDataWithCalculatedScores[] = $calculatedForm;
            }
            
            $calculatedFormData[] = [
                "formGroupName" => $formGroupName,
                "formData" => $formDataWithCalculatedScores,
                "contributor_type" => $contributorType
            ];

        }
        
        // Second part: Calculate summary averages
        $averages = [];

        // Iterate through each contributor's data
        foreach ($calculatedFormData as $contributorData) {

            $contributorType = $contributorData['contributor_type'];
            
            foreach ($contributorData['formData'] as $form) {
                $formName = $form['formName'];
                
                if ($formName === 'KPI') {
                    // Store KPI values (only from manager)
                    if ($contributorType === 'manager') {
                        foreach ($form as $key => $value) {
                            if (is_numeric($key)) {
                                // Initialize if not already set
                                if (!isset($summedScores[$formName][$key])) {
                                    $summedScores[$formName][$key] = ["achievement" => $value['achievement']];
                                }
                            }
                        }
                    }
                } else {

                    // Process forms like Culture and Leadership
                    foreach ($form as $key => $values) {

                        if (is_numeric($key)) {
                            // Initialize if not already set
                            if (!isset($summedScores[$formName][$key])) {
                                $summedScores[$formName][$key] = [];
                            }

                            // Apply weightage if peers or subordinate count is non-zero
                            foreach ($values as $index => $scoreData) {
                                // Ensure the array exists for this index
                                if (!isset($summedScores[$formName][$key][$index])) {
                                    $summedScores[$formName][$key][$index] = ["score" => 0];
                                }
                                // Accumulate the score
                                $summedScores[$formName][$key][$index]['score'] += $scoreData['score'];
                            }
                            
                        }
                        // if (is_numeric($key)) {
                        //     // Initialize if not already set
                        //     if (!isset($summedScores[$formName][$key])) {
                        //         $summedScores[$formName][$key] = [];
                        //     }

                        //     // if ($peersCount == 0 || $subordinateCount == 0) {
                        //     //     // Sum scores directly without weightage
                        //     //     $totalScore = 0;
                        //     //     $scoreCount = count($values);
                    
                        //     //     foreach ($values as $index => $scoreData) {
                        //     //         $totalScore += $scoreData['score'];
                        //     //     }
                    
                        //     //     // Calculate the average score
                        //     //     $averageScore = $scoreCount > 0 ? $totalScore / $scoreCount : 0;
                    
                        //     //     // Store the average score at this index
                        //     //     $summedScores[$formName][$key][] = ["score" => $averageScore];
                        //     // } else {
                        //         // Apply weightage if peers or subordinate count is non-zero
                        //         foreach ($values as $index => $scoreData) {
                        //             // Ensure the array exists for this index
                        //             if (!isset($summedScores[$formName][$key][$index])) {
                        //                 $summedScores[$formName][$key][$index] = ["score" => 0];
                        //             }
                        //             // Accumulate the score
                        //             $summedScores[$formName][$key][$index]['score'] += $scoreData['score'];
                        //         }
                        //     // }
                            
                        // }
                    }
                }
            }
        }

        // Format the summary response
        $summary = [
            "formGroupName" => "Appraisal Form",
            "formData" => [],
            "contributor_type" => "summary"
        ];

        // Add KPI first if exists
        if (isset($summedScores['KPI'])) {
            $kpiForm = [
                "formName" => "KPI"
            ];
            foreach ($summedScores['KPI'] as $key => $value) {
                $kpiForm[$key] = $value; // Include KPI data in the summary
            }
            $summary['formData'][] = $kpiForm; // Add KPI to the summary
        }

        // Add Culture and Leadership
        foreach (['Culture', 'Leadership', 'Technical'] as $formName) {
            if (isset($summedScores[$formName])) {
                $form = [
                    "formName" => $formName
                ];
                foreach ($summedScores[$formName] as $key => $scores) {
                    $form[$key] = $scores; // Include Culture or Leadership data in the summary
                }
                $summary['formData'][] = $form; // Add Culture or Leadership to the summary
            }
        }
        
        // Return both calculated data and summary
        return [
            'calculated_data' => $calculatedFormData,
            'summary' => $summary
        ];
    }

    public function getClusterKPIs($employee_id)
    {
        // For now, return sample company and division KPIs
        // In future, this can be fetched from admin tables or APIs
        $companyKPIs = [
            [
                'cluster' => 'company',
                'kpi' => 'Company KPI 1',
                'target' => 100,
                'uom' => 'Percent (%)',
                'type' => 'Higher Better',
                'description' => 'Company level KPI',
                'weightage' => null, // To be filled by user
                'custom_uom' => null
            ]
        ];

        $divisionKPIs = [
            [
                'cluster' => 'division',
                'kpi' => 'Division KPI 1',
                'target' => 50,
                'uom' => 'Number',
                'type' => 'Exact Value',
                'description' => 'Division level KPI',
                'weightage' => null, // To be filled by user
                'custom_uom' => null
            ]
        ];

        return [
            'company' => $companyKPIs,
            'division' => [],
            'personal' => [] // Personal will be added by user
        ];
    }

}