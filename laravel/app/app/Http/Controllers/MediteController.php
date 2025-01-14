<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    
        // Retrieve the full paths for source and target versions
        $sourceVersion = DB::table('versions')->where('id', $request->input('source_version'))->value('folder');
        $targetVersion = DB::table('versions')->where('id', $request->input('target_version'))->value('folder');
    
        if (!$sourceVersion || !$targetVersion) {
            return response()->json(['error' => 'Source or target version not found'], 404);
        }
    
        // Determine the full path for the html and xml result files to be created by Medite
        $resultsFolder = 'variance_data/';

        // Prepare the data for the Flask API call
        $payload = [
            'source_file' => $sourceVersion,
            'target_file' => $targetVersion,
            'lg_pivot' => $request->input('lg_pivot'),
            'ratio' => $request->input('ratio'),
            'seuil' => $request->input('seuil'),
            'case_sensitive' => $request->input('case_sensitive'),
            'diacri_sensitive' => $request->input('diacri_sensitive'),
            'output_xml' => $resultsFolder
        ];

        Log::info('Payload for Medite API', $payload);
    
        try {
            // Make the API call to the Flask app
            $response = Http::asForm()->post('http://medite:5000/run_diff2', $payload);
    
            // Check if the API call was successful
            if ($response->successful()) {
                $taskId = $response->json('task_id');
                return response()->json(['task_id' => $taskId], 200);
            }
    
            // Handle API errors
            return response()->json([
                'error' => 'Failed to submit task to Medite',
                'details' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            // Handle any exceptions during the API call
            return response()->json([
                'error' => 'An error occurred while communicating with the Medite service',
                'details' => $e->getMessage(),
            ], 500);
        }
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
