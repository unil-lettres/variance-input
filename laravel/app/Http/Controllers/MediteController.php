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
    $request->validate([
        'source_version' => 'required|exists:versions,id',
        'target_version' => 'required|exists:versions,id',
        'lg_pivot'       => 'required|integer',
        'ratio'          => 'required|integer',
        'seuil'          => 'required|integer',
    ]);

    // Convert booleans
    $caseSensitive   = $request->has('case_sensitive') ? true : false;
    $diacriSensitive = $request->has('diacri_sensitive') ? true : false;

    // Define destination folder
    $workId = $request->input('work_id');
    $shortTitle = DB::table('works')->where('id', $workId)->value('short_title');
    
    // 1. Define relative path
    $relativeFolder = "uploads/{$shortTitle}/comparisons";
    $storageFolder = storage_path("app/public/{$relativeFolder}");
    
    // 2. Ensure folder exists
    if (!file_exists($storageFolder)) {
        mkdir($storageFolder, 0777, true);
    }
    
    // 3. Create empty comparison
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
    $comparison->folder           = $relativeFolder;
    $comparison->save();
    
    // 4. Define output paths for Flask (inside container)
    $filenameBase = $comparison->id;
    $outputXml  = "/app/{$relativeFolder}/{$filenameBase}.xml";
    $outputHtml = "/app/{$relativeFolder}/{$filenameBase}.html";    

    // Step 3: Convert source/target paths
    $sourceVersion = DB::table('versions')->where('id', $request->input('source_version'))->value('folder');
    $targetVersion = DB::table('versions')->where('id', $request->input('target_version'))->value('folder');

    $sourceFile = $this->convertPathForFlask($sourceVersion);
    $targetFile = $this->convertPathForFlask($targetVersion);

    // Step 4: Build payload for Flask
    $payload = [
        'source_filename'  => $sourceFile,
        'target_filename'  => $targetFile,
        'lg_pivot'         => $request->input('lg_pivot'),
        'ratio'            => $request->input('ratio'),
        'seuil'            => $request->input('seuil'),
        'case_sensitive'   => $caseSensitive ? 'on' : 'off',
        'diacri_sensitive' => $diacriSensitive ? 'on' : 'off',
        'output_xml'       => $outputXml,
        'output_html'      => $outputHtml, // Optional
    ];

    // Step 5: Post to Flask
    try {
        $flaskUrl = 'http://medite:5000/run_diff2';
        $response = Http::timeout(120)->asForm()->post($flaskUrl, $payload);

        if ($response->successful()) {
            $taskId = $response->json('task_id');
            return response()->json(['task_id' => $taskId, 'comparison_id' => $comparison->id]);
        }

        return response()->json([
            'error' => 'Flask error',
            'details' => $response->json(),
        ], $response->status());
    } catch (\Exception $e) {
        Log::error('Medite call failed', ['e' => $e]);
        return response()->json([
            'error' => 'Could not contact Medite',
            'details' => $e->getMessage()
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
    // public function createComparison(Request $request)
    // {
    //     $request->validate([
    //         'source_id'    => 'required|exists:versions,id',
    //         'target_id'    => 'required|exists:versions,id',
    //         'folder'       => 'required|string|max:45',
    //         'number'       => 'required|numeric',
    //         'prefix_label' => 'required|string|max:250',
    //     ]);

    //     Comparison::create($request->all());
    //     return response()->json(['message' => 'Comparison record created']);
    // }

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
    
//     public function saveComparison(Request $request)
// {
//     // Validate the required fields
//     $data = $request->validate([
//         'source_id'        => 'required|exists:versions,id',
//         'target_id'        => 'required|exists:versions,id',
//         'lg_pivot'         => 'required|integer',
//         'ratio'            => 'required|integer',
//         'seuil'            => 'required|integer',
//         'case_sensitive'   => 'required|boolean',
//         'diacri_sensitive' => 'required|boolean',
//         'folder'           => 'nullable|string|max:255',
//         'prefix_label'     => 'nullable|string|max:255',
//         'number'           => 'nullable|numeric',
//     ]);

//     // Compute the next available number regardless of any incoming value
//     $maxNumber = \App\Models\Comparison::max('number') ?? 0;
//     $data['number'] = $maxNumber + 1;

//     // Create the record in DB
//     $comparison = new \App\Models\Comparison();
//     $comparison->source_id        = $data['source_id'];
//     $comparison->target_id        = $data['target_id'];
//     $comparison->folder           = $data['folder'] ?? 'my-medite-outputs';
//     $comparison->number           = $data['number'];
//     $comparison->prefix_label     = $data['prefix_label'] ?? 'Comparison';
//     $comparison->lg_pivot         = $data['lg_pivot'];
//     $comparison->ratio            = $data['ratio'];
//     $comparison->seuil            = $data['seuil'];
//     $comparison->case_sensitive   = $data['case_sensitive'];
//     $comparison->diacri_sensitive = $data['diacri_sensitive'];
//     $comparison->save();

//     return response()->json([
//         'message'      => 'Comparison saved successfully',
//         'comparisonId' => $comparison->id,
//     ]);
// }



}
