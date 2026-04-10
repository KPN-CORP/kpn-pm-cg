<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AppService;

class RestrictBusinessUnitMiddleware
{
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            abort(401);
        }

        $user = Auth::user();

        // ✅ Superadmin selalu boleh lewat
        if ($user->hasRole('superadmin') || $user->employee_id == '01123040006' || $user->employee_id == '01124040017') {
            return $next($request);
        }

        // Pastikan relasi employee ada
        if (!$user->employee) {
            abort(401, 'Employee data not found.');
        }

        // 🔒 Restrict hanya BU Cement
        if ($user->employee->group_company !== 'Cement') {
            abort(401, 'Access restricted to Cement Business Unit.');
        }

        return $next($request);
    }
}
