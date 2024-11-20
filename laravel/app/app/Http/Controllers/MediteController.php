<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;

class MediteController extends Controller
{
    public function runMedite(Request $request)
    {
        $request->validate([
            'source_version' => 'required|exists:versions,id',
            'target_version' => 'required|exists:versions,id',
            'lg_pivot' => 'required|integer',
            'ratio' => 'required|integer',
            'seuil' => 'required|integer',
        ]);

        $taskId = uniqid();
        return response()->json(['task_id' => $taskId]);
    }

    public function taskStatus($taskId)
    {
        $mockStatus = rand(0, 1) ? 'completed' : 'pending';

        if ($mockStatus === 'completed') {
            return response()->json(['status' => 'completed']);
        } else {
            return response()->json(['status' => 'pending']);
        }
    }

    public function createComparison(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:versions,id',
            'target_id' => 'required|exists:versions,id',
            'folder' => 'required|string|max:45',
            'number' => 'required|numeric',
            'prefix_label' => 'required|string|max:250',
        ]);

        Comparison::create($request->all());
        return response()->json(['message' => 'Comparison record created']);
    }
}
