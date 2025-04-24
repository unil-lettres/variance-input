<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class XhtmlController extends Controller
{
    public function run(Request $request)
    {
        $validated = $request->validate([
            'input_xml'  => 'required|string',
            'output_dir' => 'required|string',
        ]);

        $payload = [
            'input_xml'  => $validated['input_xml'],
            'output_dir' => $validated['output_dir'],
        ];

        try {
            $response = Http::timeout(30)->post('http://saxon:8080/run-xhtml', $payload);

            if ($response->successful()) {
                return response()->json($response->json());
            } else {
                return response()->json([
                    'error'   => 'Saxon error',
                    'details' => $response->json(),
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Failed to contact Saxon container',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}

