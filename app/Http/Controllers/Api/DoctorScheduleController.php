<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorScheduleController extends Controller
{
    public function index()
    {
        $doctors = DB::table('doctor_schedules')
            ->where('is_active', 1)
            ->orderBy('department')
            ->orderBy('sort_order')
            ->get();

        $data = $doctors->groupBy('department')
            ->map(function ($items, $department) {

                return [
                    'department' => $department,

                    'doctors' => $items->map(function ($doctor) {
                        return [
                            'id' => $doctor->id,
                            'doctor_name' => $doctor->doctor_name,
                            'timing' => [
                                'start' => $doctor->start_time,
                                'end' => $doctor->end_time,
                            ],
                            'price' => json_decode($doctor->price, true),
                        ];
                    })->values(),
                ];
            })->values();

        return response()->json([
            'status' => true,
            'message' => 'Doctor schedules fetched successfully',
            'data' => $data,
        ]);
    }
}
