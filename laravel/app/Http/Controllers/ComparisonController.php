<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Comparison;

class ComparisonController extends Controller
{
    /**
     * Return all comparisons connected to a work (by its versions).
     */
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

    /**
     * Delete comparison DB row **and** its generated HTML / XML files.
     * Files are stored in `uploads/comparisons/{comparison_id}.xml|html`.
     */
    public function destroy(int $id)
    {
        $comparison = Comparison::findOrFail($id);

        $relativeFolder = 'uploads/comparisons';
        $filename       = $comparison->id; // use unique id, not short name

        $htmlPath = "{$relativeFolder}/{$filename}.html";
        $xmlPath  = "{$relativeFolder}/{$filename}.xml";

        // Remove files if present
        Storage::disk('public')->delete([$htmlPath, $xmlPath]);

        // Remove DB record
        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted']);
    }
}
