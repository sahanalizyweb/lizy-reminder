<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    /** Staff members that reminders can be assigned to (is_staff accounts only). */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::where('is_staff', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
