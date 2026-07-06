<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewType;

class PerformanceReviewController extends Controller
{
    public function formAdd($id) {
        try {
            return view('pages.performance-review.form-add', [
                'data' => []
            ]);
        } catch (Exception $e) {
            return view('pages.performance-review.form-add', [
                'data' => []
            ]);
        }
    }

    public function formEdit($id) {
        try {
            return view('pages.performance-review.form-edit', [
                'data' => []
            ]);
        } catch (Exception $e) {
            return view('pages.performance-review.form-edit', [
                'data' => []
            ]);
        }
    }

    public function create(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'data' => []
            ]);
        }
    }

    public function update(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'data' => []
            ]);
        }
    }

    public function delete(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'data' => []
            ]);
        }
    }
}
