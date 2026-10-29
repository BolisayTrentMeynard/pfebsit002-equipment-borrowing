<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;

class EquipmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Equipment::orderBy('name')->get(['id', 'name', 'asset_tag', 'status'])]);
    }
}
