<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Version;
use Illuminate\Support\Facades\Storage;

class VersionController extends Controller
{
    public function index(Request $request)
    {
        $workId = $request->query('work_id');
    
        if (!$workId) {
            return response()->json(['error' => 'work_id is required'], 400);
        }
    
        $versions = \App\Models\Version::where('work_id', $workId)->get();
    
        return response()->json($versions, 200);
    }
    
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'work_id' => 'required|exists:works,id',
            'name' => 'required|string|max:45',
            'xmlFile' => 'required|file|mimes:xml|max:1024',
        ]);
    
        // Save the file to Laravel's storage/app directory
        $file = $request->file('xmlFile');
        $fileName = $file->getClientOriginalName();
        $folderPath = "xml/{$request->work_id}";
        $filePath = $file->storeAs($folderPath, $fileName);
    
        \App\Models\Version::create([
            'work_id' => $request->work_id,
            'name' => $request->name,
            'folder' => $folderPath,
        ]);
    
        return response()->json(['message' => 'Version uploaded successfully!', 'path' => $filePath]);
    }
    
    

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
