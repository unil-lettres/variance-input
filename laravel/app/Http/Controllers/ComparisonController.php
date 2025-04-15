<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ComparisonController extends Controller
{
    public function getByWork(Request $request)
    {
        $request->validate(['work_id' => 'required|exists:works,id']);

        $versionIds = DB::table('versions')
            ->where('work_id', $request->input('work_id'))
            ->pluck('id');

        $comparisons = Comparison::with(['sourceVersion', 'targetVersion'])
            ->whereIn('source_id', $versionIds)
            ->orWhereIn('target_id', $versionIds)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($comparisons);
    }

    public function destroy($id)
    {
        $comparison = Comparison::findOrFail($id);
    
        // Build the relative paths to the .html and .xml files
        $folder = $comparison->folder; // e.g. 'uploads/lvf/comparisons'
        $filename = $comparison->id;
    
        $htmlPath = "{$folder}/{$filename}.html";
        $xmlPath  = "{$folder}/{$filename}.xml";
    
        // Delete files if they exist
        Storage::disk('public')->delete([$htmlPath, $xmlPath]);
    
        // Delete the DB record
        $comparison->delete();
    
        return response()->json(['message' => 'Comparison deleted']);
    }

}

