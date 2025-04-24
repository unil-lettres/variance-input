<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Comparison;

class MediteController extends Controller
{
    /**
     * Run Medite (Flask diff) between two uploaded versions.
     */
    public function runMedite(Request $request)
    {
        $request->validate([
            'source_version' => 'required|exists:versions,id',
            'target_version' => 'required|exists:versions,id',
            'lg_pivot'       => 'required|integer',
            'ratio'          => 'required|integer',
            'seuil'          => 'required|integer',
            'work_id'        => 'required|exists:works,id',
        ]);

        /** Optional booleans */
        $caseSensitive   = $request->has('case_sensitive');
        $diacriSensitive = $request->has('diacri_sensitive');

        /* ------------------------------------------------------------------
         | Build comparison short name (e.g. "1tst-2tst")
         |------------------------------------------------------------------ */
        $sourceShort = DB::table('versions')
                        ->where('id', $request->input('source_version'))
                        ->value('folder');   // now holds the short name
        $targetShort = DB::table('versions')
                        ->where('id', $request->input('target_version'))
                        ->value('folder');

        $comparisonShortName = "{$sourceShort}-{$targetShort}";

        /* ------------------------------------------------------------------
         | Ensure output directory exists
         |------------------------------------------------------------------ */
        $relativeFolder = 'uploads/comparisons';
        $storageFolder  = storage_path("app/public/{$relativeFolder}");
        if (!is_dir($storageFolder)) {
            mkdir($storageFolder, 0777, true);
        }

        /* ------------------------------------------------------------------
         | Persist a new comparison row (folder holds short name only)
         |------------------------------------------------------------------ */
        $comparison = new Comparison();
        $comparison->source_id        = $request->input('source_version');
        $comparison->target_id        = $request->input('target_version');
        $comparison->lg_pivot         = $request->input('lg_pivot');
        $comparison->ratio            = $request->input('ratio');
        $comparison->seuil            = $request->input('seuil');
        $comparison->case_sensitive   = $caseSensitive;
        $comparison->diacri_sensitive = $diacriSensitive;
        $comparison->prefix_label     = 'Auto Run';
        $comparison->number           = 1;
        $comparison->folder           = $comparisonShortName; // e.g. 1tst-2tst
        $comparison->save();

        /* ------------------------------------------------------------------
         | File names now use the comparison id (unique per run)
         |------------------------------------------------------------------ */
        $filenameBase = $comparison->id; // unique id
        $outputXml    = "/app/{$relativeFolder}/{$filenameBase}.xml";
        $outputHtml   = "/app/{$relativeFolder}/{$filenameBase}.html";

        /* ------------------------------------------------------------------
         | Resolve input file paths for Flask
         |------------------------------------------------------------------ */
        $sourceFile = $this->convertPathForFlask($sourceShort);
        $targetFile = $this->convertPathForFlask($targetShort);

        $payload = [
            'source_filename'  => $sourceFile,
            'target_filename'  => $targetFile,
            'lg_pivot'         => $request->input('lg_pivot'),
            'ratio'            => $request->input('ratio'),
            'seuil'            => $request->input('seuil'),
            'case_sensitive'   => $caseSensitive ? 'on' : 'off',
            'diacri_sensitive' => $diacriSensitive ? 'on' : 'off',
            'output_xml'       => $outputXml,
            'output_html'      => $outputHtml,
        ];

        /* ------------------------------------------------------------------
         | Call Flask service
         |------------------------------------------------------------------ */
        try {
            $response = Http::timeout(120)->asForm()->post('http://medite:5000/run_diff2', $payload);

            if ($response->successful()) {
                return response()->json([
                    'task_id'       => $response->json('task_id'),
                    'comparison_id' => $comparison->id,
                ]);
            }

            return response()->json([
                'error'   => 'Flask error',
                'details' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            Log::error('Medite call failed', ['e' => $e]);
            return response()->json([
                'error'   => 'Could not contact Medite',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check Celery task status
     */
    public function taskStatus($taskId)
    {
        try {
            $response = Http::get("http://medite:5000/task_status/{$taskId}");
            if ($response->failed()) {
                return response()->json([
                    'status'  => 'failed',
                    'error'   => 'Flask returned an error',
                    'details' => $response->body(),
                ], 500);
            }
            return response()->json($response->json(), 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'failed',
                'error'   => 'Exception contacting Flask',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map version short name to full path visible to Flask
     */
    private function convertPathForFlask(string $shortName): string
    {
        return "/app/uploads/versions/{$shortName}.xml";
    }
}
