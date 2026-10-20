<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OverviewController extends Controller
{
    public function __invoke(AdminStats $stats): JsonResponse
    {
        return response()->json(['data' => $stats->overview()]);
    }
}
