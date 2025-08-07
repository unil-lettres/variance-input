<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Comparison;

class MediteController extends Controller
{

    /**
     * POST /api/comparisons
     * Create (or reuse) a comparison row with full Medite metadata.
     */
    public function createComparison(Request $request)
    {
        /* ─── 1. Validate input ───────────────────────────────────────────── */
        $data = $request->validate([
            /* mandatory */
            'source_id' => 'required|exists:versions,id',
            'target_id' => 'required|exists:versions,id',
            'folder'    => 'required|string|max:255',

            /* optional but accepted */
            'lg_pivot'         => 'nullable|integer',
            'ratio'            => 'nullable|integer',
            'seuil'            => 'nullable|integer',
            'sep'              => 'nullable|string|max:50',
            'case_sensitive'   => 'nullable|boolean',
            'diacri_sensitive' => 'nullable|boolean',
        ]);

        Log::debug('createComparison payload', $data);


        /* ─── 2. Re-use if the folder already exists ─────────────────────── */
        if ($cmp = Comparison::where('folder', $data['folder'])->first()) {
            return response()->json($cmp, 200);            // OK → existing row
        }

        /* ─── 3. Insert a new row (fill every NOT-NULL col) ──────────────── */
        $cmp = Comparison::create([
            'source_id'        => $data['source_id'],
            'target_id'        => $data['target_id'],
            'folder'           => $data['folder'],

            /* Medite parameters (fallback to sensible defaults) */
            'lg_pivot'         => $data['lg_pivot']         ?? 7,
            'ratio'            => $data['ratio']            ?? 15,
            'seuil'            => $data['seuil']            ?? 50,
            'sep'              => $data['sep']              ?? ',.;?!',
            'case_sensitive'   => $data['case_sensitive']   ?? false,
            'diacri_sensitive' => $data['diacri_sensitive'] ?? false,

            /* house-keeping */
            'prefix_label'     => 'Auto',
            'number'           => 1,
        ]);

        return response()->json($cmp, 201);                // Created
    }


    /*──────────────────────── 2.  Run Medite (Flask) ───────────────────────*/
    public function runMedite(Request $request)
    {
        /* ───── Validation ───── */
        $validated = $request->validate([
            'source_version' => 'required|exists:versions,id',
            'target_version' => 'required|exists:versions,id',
            'work_id'        => 'required|exists:works,id',
            'lg_pivot'       => 'required|integer',
            'ratio'          => 'required|integer',
            'seuil'          => 'required|integer',
            'sep'            => 'nullable|string',
        ]);

        $caseSensitive   = $request->has('case_sensitive');
        $diacriSensitive = $request->has('diacri_sensitive');
        $separators      = $validated['sep'] ?? ',.;?!';

        /* ───── Short names for versions ───── */
        $sourceShort = DB::table('versions')
                        ->where('id', $validated['source_version'])
                        ->value('folder');
        $targetShort = DB::table('versions')
                        ->where('id', $validated['target_version'])
                        ->value('folder');
        $comparisonShort = "$sourceShort-$targetShort";

        /* ───── Create DB row first ───── */
        $cmp = Comparison::create([
            'source_id'        => $validated['source_version'],
            'target_id'        => $validated['target_version'],
            'lg_pivot'         => $validated['lg_pivot'],
            'ratio'            => $validated['ratio'],
            'seuil'            => $validated['seuil'],
            'case_sensitive'   => $caseSensitive,
            'diacri_sensitive' => $diacriSensitive,
            'prefix_label'     => 'Auto Run',
            'number'           => 1,
            'folder'           => $comparisonShort,
        ]);

        /* ───── Paths for Flask outputs ───── */
        $baseDir   = $request->input('xhtml_output_dir');     // provided by JS
        $outputXml = $request->input('output_xml');           // provided by JS
        if (!$outputXml) {                                    // Fallback
            $relative = "uploads/comparisons/{$cmp->id}";
            $baseDir  = "/app/{$relative}";
            $outputXml = "{$baseDir}/{$cmp->id}.xml";
        }

        /* ───── Source / Target absolute paths ───── */
        $sourceFile = $this->convertPath($sourceShort);
        $targetFile = $this->convertPath($targetShort);

        $payload = [
            'source_filename'   => $sourceFile,
            'target_filename'   => $targetFile,
            'lg_pivot'          => $validated['lg_pivot'],
            'ratio'             => $validated['ratio'],
            'seuil'             => $validated['seuil'],
            'sep'               => $separators,
            'case_sensitive'    => $caseSensitive ? 'on' : 'off',
            'diacri_sensitive'  => $diacriSensitive ? 'on' : 'off',
            'output_xml'        => $outputXml,
            'xhtml_output_dir'  => $baseDir,
        ];

        /* ───── Call Flask ───── */
        try {
            $resp = Http::timeout(120)
                        ->asForm()
                        ->post('http://medite:5000/run_diff2', $payload);

            if ($resp->successful()) {
                return response()->json([
                    'task_id'       => $resp->json('task_id'),
                    'comparison_id' => $cmp->id,
                ]);
            }

            return response()->json([
                'error'   => 'Flask error',
                'details' => $resp->json(),
            ], $resp->status());
        } catch (\Exception $e) {
            Log::error('Medite call failed', ['e' => $e]);
            return response()->json([
                'error'   => 'Could not contact Medite',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /*──────────────────────── 3.  Poll Celery ───────────────────────*/
    public function taskStatus($taskId)
    {
        try {
            $r = Http::get("http://medite:5000/task_status/{$taskId}");
            return response()->json($r->json(), $r->successful() ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'failed',
                'error'   => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*──────────────────────── Helper ───────────────────────*/
    private function convertPath(string $short): string
    {
        return "/app/uploads/versions/{$short}.xml";
    }
}
