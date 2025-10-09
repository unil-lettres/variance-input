<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Comparison;
use Illuminate\Support\Facades\Storage;

class EditorController extends Controller
{
    public function show(Comparison $comparison, Request $request)
    {
        $request->validate([
            'type' => 'in:source,target'
        ]);

        $type = $request->query('type', 'source');
        $isTarget = $type === 'target';

        if ($isTarget) {
          $version = $comparison->targetVersion;
          $path = $comparison->getTargetFilePath();
        } else {
          $version = $comparison->sourceVersion;
          $path = $comparison->getSourceFilePath();
        }

        if (!file_exists($path)) {
            abort(404, "XML file not found at: {$path}");
        }
    
        $xmlContent = file_get_contents($path);
    
        return view('components.main.editor', [
            'comparison' => $comparison,
            'version' => $version,
            'xmlContent' => $xmlContent,
            'isSource' => !$isTarget,
        ]);
    }
    

    public function update(Comparison $comparison, Request $request)
    {
        $request->validate([
            'type' => 'in:source,target'
        ]);

        $newXml = $request->getContent();
        $path = match ($request->query('type', 'source')) {
            'source' => $comparison->getSourceFilePath(),
            'target' => $comparison->getTargetFilePath(),
        };
        
        if (!file_exists($path)) {
            abort(404, "File not found: {$path}");
        }

        file_put_contents($path, $newXml);

        return response()->json(['message' => 'XML updated successfully']);
    }
}
