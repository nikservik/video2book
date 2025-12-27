<?php

namespace App\Http\Controllers;

use App\Http\Resources\StepVersionResource;
use App\Models\Step;
use Illuminate\Http\JsonResponse;

class StepController extends Controller
{
    public function versions(Step $step): JsonResponse
    {
        $versions = StepVersionResource::collection(
            $step->versions()
                ->with('inputStep.currentVersion')
                ->orderBy('version')
                ->get()
        )->resolve();

        return response()->json(['data' => $versions]);
    }
}
