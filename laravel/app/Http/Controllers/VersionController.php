<?php

namespace App\Http\Controllers;

use App\Models\Version;
use App\Models\Comparison;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

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
     * Store a newly uploaded version text file as {versionId}{shortTitle}.xml
     * in storage/app/public/uploads/{shortTitle}/versions
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'work_id'      => 'required|exists:works,id',
            'versionFile'  => 'required|file|mimetypes:text/xml,application/xml,text/plain|max:2048',
            'short_title'  => 'required|string|max:10',
            'name'         => 'required|string|max:100',  // Edition name
        ]);
    
        $file        = $request->file('versionFile');
        $editionName = $validated['name'];
        $shortTitle  = $validated['short_title'];
    
        // Create version record first to get the auto ID
        $version = Version::create([
            'work_id' => $validated['work_id'],
            'name'    => $editionName,
            'folder'  => '', // temp, will update after saving file
        ]);
    
        // Define new filename and storage path
        $filename        = "{$version->id}.xml";
        $folderPath      = "uploads/{$shortTitle}/versions";
        $fullStoragePath = "{$folderPath}/{$filename}";
    
        // Store the file
        $file->storeAs($folderPath, $filename, 'public');
    
        // Update the version's folder path
        $version->folder = "storage/{$fullStoragePath}";
        $version->save();
    
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
    
        // Check if version is used in any comparison
        $hasComparisons = Comparison::where('source_id', $version->id)
                          ->orWhere('target_id', $version->id)
                          ->exists();
    
        if ($hasComparisons) {
            return response()->json([
                'error' => 'Impossible de supprimer cette version car elle est utilisée dans une ou plusieurs comparaisons.'
            ], 400);
        }
    
        // Remove version file from disk
        if ($version->folder) {
            $relativePath = str_replace('storage/', '', $version->folder);
            Storage::disk('public')->delete($relativePath);
        }
    
        // Delete DB record
        $version->delete();
    
        return response()->json(['message' => 'Version supprimée avec succès']);
    }

    public function viewXmlClean($id)
    {
        // 1) Lookup version row
        $version = DB::table('versions')->where('id', $id)->first();
        if (!$version) {
            abort(404, "Version #{$id} not found");
        }
    
        // 2) Convert DB path to actual file path
        //    Example DB path: "storage/uploads/lvf/versions/1lvf.xml"
        $relativePath = preg_replace('#^storage/#', '', $version->folder);    
        $fullPath = storage_path("app/public/{$relativePath}");
    
        if (!file_exists($fullPath)) {
            Log::error("Version file not found at: " . $fullPath);
            abort(404, "File not found at: $fullPath");
        }
    
        // 3) Read raw XML (instead of simplexml_load_file)
        $xmlContent = file_get_contents($fullPath);
    
        // 4) Return raw XML with correct MIME type
        return response($xmlContent, 200)
            ->header('Content-Type', 'application/xml');
    }
    
    
    

}
