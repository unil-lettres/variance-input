<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Comparison;

class MediteController extends Controller
{
    public function runMedite(Request $request)
    {
        // 1) Validate form input
        $request->validate([
            'source_version' => 'required|exists:versions,id',
            'target_version' => 'required|exists:versions,id',
            'lg_pivot'       => 'required|integer',
            'ratio'          => 'required|integer',
            'seuil'          => 'required|integer',
            // 'case_sensitive' + 'diacri_sensitive' can be nullable checkboxes
        ]);

        // 2) Retrieve file paths from DB
        //    The 'folder' column might be something like:
        //    "storage/app/public/uploads/<shortName>/versions/<filename>.txt"
        $sourceVersion = DB::table('versions')
            ->where('id', $request->input('source_version'))
            ->value('folder');

        $targetVersion = DB::table('versions')
            ->where('id', $request->input('target_version'))
            ->value('folder');

        if (!$sourceVersion || !$targetVersion) {
            return response()->json(['error' => 'Source or target version not found'], 404);
        }

        // 3) Convert 'folder' path to how Flask sees it
        //    e.g. "/app/uploads/<shortName>/versions/<filename>.txt"
        $sourceFile = $this->convertPathForFlask($sourceVersion);
        $targetFile = $this->convertPathForFlask($targetVersion);

        // 4) Convert checkboxes to 'on' or 'off'
        //    The Flask route treats 'on' → True, everything else → False
        $caseSensitive   = $request->has('case_sensitive') ? 'on' : 'off';
        $diacriSensitive = $request->has('diacri_sensitive') ? 'on' : 'off';

        // 5) Where the diff script will write its "result.xml" output
        //    Must be a path inside the Medite container
        $outputPath = '/app/uploads/result.xml';
        // or if you prefer a subfolder:
        // "/app/uploads/{$someAuthor}/{$someWorkId}/result.xml"

        // 6) Build payload for Flask container
        //    NOTE: We name them to match what the Celery task expects:
        //    def run_diff_script(
        //       source_filename, target_filename, lg_pivot, ratio, seuil,
        //       case_sensitive, diacri_sensitive, output_xml
        //    )
        $payload = [
            'source_filename'  => $sourceFile,
            'target_filename'  => $targetFile,
            'lg_pivot'         => $request->input('lg_pivot'),
            'ratio'            => $request->input('ratio'),
            'seuil'            => $request->input('seuil'),
            'case_sensitive'   => $caseSensitive,
            'diacri_sensitive' => $diacriSensitive,
            'output_xml'       => $outputPath
        ];

        Log::info('Payload for Medite API', $payload);

        // 7) POST to the Flask container
        try {
            $flaskUrl = 'http://medite:5000/run_diff2';
            $response = Http::asForm()->post($flaskUrl, $payload);

            if ($response->successful()) {
                $taskId = $response->json('task_id');
                return response()->json(['task_id' => $taskId], 200);
            }

            // If Flask returned an error or non-2xx status
            return response()->json([
                'error'   => 'Failed to submit task to Medite',
                'details' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            // If we couldn't reach Flask or some other exception
            Log::error('Exception while calling Medite', ['exception' => $e]);
            return response()->json([
                'error'   => 'An error occurred while communicating with the Medite service',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Poll the status of the Celery task from the Flask container
     */
    public function taskStatus($taskId)
    {
        $flaskUrl = "http://medite:5000/task_status/{$taskId}";

        try {
            $response = Http::get($flaskUrl);
            if ($response->failed()) {
                return response()->json([
                    'status' => 'failed',
                    'error'  => 'Flask returned an error or 4xx/5xx code',
                    'details'=> $response->body()
                ], 500);
            }
            return response()->json($response->json(), 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'error'  => 'Exception contacting Flask container',
                'message'=> $e->getMessage()
            ], 500);
        }
    }

    /**
     * Example method to create a row in 'comparisons' table
     */
    public function createComparison(Request $request)
    {
        $request->validate([
            'source_id'    => 'required|exists:versions,id',
            'target_id'    => 'required|exists:versions,id',
            'folder'       => 'required|string|max:45',
            'number'       => 'required|numeric',
            'prefix_label' => 'required|string|max:250',
        ]);

        Comparison::create($request->all());
        return response()->json(['message' => 'Comparison record created']);
    }

    /**
     * Convert a DB path like "storage/app/public/uploads/csb/versions/1csb.txt"
     * into "/app/uploads/csb/versions/1csb.txt" so the Flask container can see it.
     * 
     * Adjust if your folder structure differs. The key is that Docker
     * must mount the 'laravel/storage/app/public/uploads' folder into
     * the 'medite' container at '/app/uploads'.
     */
    private function convertPathForFlask($dbPath)
    {
        // This strips everything *before and including* "uploads"
        $relative = preg_replace('#^.*?uploads/#', '', $dbPath);
        return "/app/uploads/{$relative}";
    }     
}
