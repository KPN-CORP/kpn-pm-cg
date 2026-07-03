<?php

namespace App\Http\Controllers;

use Exception;

class PerformanceReviewController extends Controller
{
    public function form() {
        try {
            return view('pages.performance-review.form', [
                'data' => []
            ]);
        } catch (Exception $e) {
            return view('pages.performance-review.form', [
                'data' => []
            ]);
        }
    }

    public function formEdit() {
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

    public function create() {
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

    public function update() {
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

    public function delete() {
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
