<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Models\Version;
use App\Models\Comparison;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Store a newly uploaded version (plain-text) and a TEI-XML wrapper
     */
    public function store(Request $request)
    {
        // 1) Validate input: work_id, edition name, and the file
        $validated = $request->validate([
            'work_id'     => 'required|exists:works,id',
            'name'        => 'required|string|max:100',      // Edition name
            'versionFile' => 'required|file|mimetypes:text/plain|max:2048',
        ]);

        // 2) Fetch the work to get its short_title
        $work       = Work::findOrFail($validated['work_id']);
        $shortTitle = $work->short_title;

        // 3) Determine next available number for this work
        $nextNumber = Version::where('work_id', $work->id)->count() + 1;

        // 4) Build base filename and storage path
        $baseName   = "{$nextNumber}{$shortTitle}";
        $folderPath = 'uploads/versions'; // relative to storage/app/public

        // 5) Store the original plain-text file
        $txtFilename    = "{$baseName}.txt";
        $txtStoragePath = "{$folderPath}/{$txtFilename}";
        $request->file('versionFile')
                ->storeAs($folderPath, $txtFilename, 'public');

        // 6) Read the just-stored text so we can wrap it with TEI
        $txtFullPath       = storage_path("app/public/{$txtStoragePath}");
        $rawText           = File::get($txtFullPath);
        $escapedText       = htmlspecialchars($rawText, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        // 7) Build a minimal TEI wrapper required by Medite
        $sanitizedShortTitle = preg_replace('/[^A-Za-z0-9]/', '', strtolower($shortTitle));
        $xmlId               = "v{$nextNumber}{$sanitizedShortTitle}";

        $teiXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TEI xml:id="{$xmlId}" xmlns="http://www.tei-c.org/ns/1.0">
  <teiHeader>
    <fileDesc>
      <titleStmt><title>{$validated['name']}</title></titleStmt>
      <publicationStmt><p>Test</p></publicationStmt>
      <sourceDesc><p>Generated for testing</p></sourceDesc>
    </fileDesc>
  </teiHeader>
  <text>
    <body>
      <div>
        <p>
{$escapedText}
        </p>
      </div>
    </body>
  </text>
</TEI>
XML;

        // 8) Persist the .xml file next to the .txt
        $xmlFilename = "{$baseName}.xml";
        $xmlPath     = "{$folderPath}/{$xmlFilename}";
        Storage::disk('public')->put($xmlPath, $teiXml);

        // 9) Create the Version record with just the short name (baseName)
        $version = Version::create([
            'work_id' => $work->id,
            'name'    => $validated['name'],
            'folder'  => $baseName,
        ]);

        // 10) Return the new version
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
     * Delete the version from DB and remove files from disk
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

        // Compute full file paths based on version->folder (which is now base name)
        $baseName         = $version->folder;
        $relativeXmlPath  = "uploads/versions/{$baseName}.xml";
        $relativeTxtPath  = "uploads/versions/{$baseName}.txt";

        Storage::disk('public')->delete($relativeXmlPath);
        Storage::disk('public')->delete($relativeTxtPath);

        // Delete DB record
        $version->delete();

        return response()->json(['message' => 'Version supprimée avec succès']);
    }

    /**
     * Return raw TEI-XML for display / download
     */
    public function viewXmlClean($id)
    {
        // 1) Lookup version row
        $version = DB::table('versions')->where('id', $id)->first();
        if (!$version) {
            abort(404, "Version #{$id} not found");
        }

        // 2) Convert base name to full file path
        $baseName     = $version->folder;
        $relativePath = "uploads/versions/{$baseName}.xml";
        $fullPath     = storage_path("app/public/{$relativePath}");

        if (!file_exists($fullPath)) {
            Log::error("Version file not found at: " . $fullPath);
            abort(404, "File not found at: $fullPath");
        }

        // 3) Read raw XML
        $xmlContent = file_get_contents($fullPath);

        // 4) Return raw XML with correct MIME type
        return response($xmlContent, 200)
            ->header('Content-Type', 'application/xml');
    }
}
