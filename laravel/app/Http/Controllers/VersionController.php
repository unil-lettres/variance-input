<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Version;
use Illuminate\Support\Facades\Storage;

class VersionController extends Controller
{
    /**
     * List versions for a given work_id
     */
    public function index(Request $request)
    {
        $workId = $request->query('work_id');
        if (!$workId) {
            return response()->json(['error' => 'work_id is required'], 400);
        }

        $versions = Version::where('work_id', $workId)->get();
        return response()->json($versions, 200);
    }

    /**
     * Store a newly uploaded version text file as {versionId}{shortTitle}.txt
     * in storage/app/public/uploads/{shortTitle}/versions
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_id'      => 'required|exists:works,id',
            'version_id'   => 'required|string|max:10',         // ✅ Add this
            'versionFile'  => 'required|file|mimes:txt,text|max:2048',
            'short_title'  => 'required|string|max:10',
            'name'         => 'required|string|max:45',         // Edition name
        ]);
    
        $file         = $request->file('versionFile');
        $versionId    = $validated['version_id'];              // ✅ now safe
        $editionName  = $validated['name'];                    // ✅ consistent
        $shortTitle   = $validated['short_title'];
    
        $finalName  = $versionId . $shortTitle . '.txt';
        $folderPath = "uploads/{$shortTitle}/versions";
        $storedPath = $file->storeAs($folderPath, $finalName, 'public');
        $dbPath     = "storage/{$storedPath}";
    
        $version = Version::create([
            'work_id' => $validated['work_id'],
            'name'    => $editionName,
            'folder'  => $dbPath,
        ]);
    
        return response()->json([
            'message' => 'Version uploaded successfully!',
            'version' => $version,
        ], 201);
    }
    

    /**
     * Update the version's user-friendly name
     */
    public function update(Request $request, $id)
    {
        $version = Version::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:45',
        ]);

        $version->update(['name' => $validated['name']]);

        return response()->json($version, 200);
    }

    /**
     * Delete the version from DB and remove file from disk
     */
    public function destroy($id)
    {
        $version = Version::findOrFail($id);

        if ($version->folder) {
            $relativePath = str_replace('storage/', '', $version->folder);
            Storage::disk('public')->delete($relativePath);
        }

        $version->delete();

        return response()->json(['message' => 'Version deleted successfully!']);
    }
}
