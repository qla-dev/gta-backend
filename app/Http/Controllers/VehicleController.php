<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::orderBy('sort_order')->get();

        return response()->json(['vehicles' => $vehicles]);
    }
}
