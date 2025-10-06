<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Version;
use Illuminate\Support\Facades\Storage;

class EditorController extends Controller
{
    public function show($id)
    {
        $version = Version::findOrFail($id);
    
        // Get the XML file content
        $relativePath = $version->folder; // e.g. "storage/uploads/lvf/versions/1lvf.xml"
        
        // Remove leading "storage/" if present
        if (strpos($relativePath, 'storage/') === 0) {
            $relativePath = substr($relativePath, strlen('storage/'));
        }
    
        $path = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        
        if (!file_exists($path)) {
            abort(404, "XML file not found at: {$path}");
        }
    
        $xmlContent = file_get_contents($path);
    
        return view('components.main.editor', [
            'version'    => $version,
            'xmlContent' => $xmlContent,
        ]);
    }
    

    public function update(Request $request, $id)
    {
        $version = Version::findOrFail($id);
        $newXml = $request->getContent(); // raw XML body

        $relativePath = $version->folder;
        $path = storage_path("app/public/uploads/versions/{$version->folder}.xml");

        if (!file_exists($path)) {
            abort(404, "File not found: {$path}");
        }

        file_put_contents($path, $newXml);

        return response()->json(['message' => 'XML updated successfully']);
    }
}
