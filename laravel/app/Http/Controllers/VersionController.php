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
    /* ───────────────────────── PUBLIC ENDPOINTS ───────────────────────── */

    /** List versions for a given work */
    public function index(Request $request)
    {
        $workId = $request->query('work_id');
        if (!$workId) {
            return response()->json(['error' => 'work_id is required'], 400);
        }
        return response()->json(Version::where('work_id', $workId)->get(), 200);
    }

    /** Upload → save .txt untouched → generate TEI (UTF‑8 LF) */
    public function store(Request $request)
    {
        /* 1. Validate */
        $validated = $request->validate([
            'work_id'     => 'required|exists:works,id',
            'name'        => 'required|string|max:100',
            'versionFile' => 'required|file|mimetypes:text/plain|max:2048',
        ]);

        /* 2. Context */
        $work        = Work::findOrFail($validated['work_id']);
        $shortTitle  = $work->short_title;
        $nextNumber  = Version::where('work_id', $work->id)->count() + 1;
        $baseName    = "{$nextNumber}{$shortTitle}";
        $folderPath  = 'uploads/versions'; // storage/app/public

        /* 3. Persist raw .txt (no conversion) */
        $txtFilename    = "{$baseName}.txt";
        $txtStoragePath = "{$folderPath}/{$txtFilename}";
        $request->file('versionFile')->storeAs($folderPath, $txtFilename, 'public');

        /* 4. Read & normalise → UTF‑8 LF */
        $fullTxt = storage_path("app/public/{$txtStoragePath}");
        $utf8    = $this->readFileAsUtf8($fullTxt, $request->input('original_encoding'));
        $utf8    = $this->normalizeCharacters($utf8);
        $utf8    = $this->collapseSpacesAndTabs($utf8);

        /* 5. Insert line‑break tags */
        $lines        = explode("\n", $utf8);
        $escapedLines = array_map(fn($l) => htmlspecialchars($l, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $lines);
        $bodyWithLb   = implode("\n          <lb/>\n", $escapedLines);

        /* 6. TEI skeleton */
        $xmlId = 'v' . $nextNumber . preg_replace('/[^A-Za-z0-9]/', '', strtolower($shortTitle));
        $tei = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
               "<TEI xml:id=\"{$xmlId}\" xmlns=\"http://www.tei-c.org/ns/1.0\">\n".
               "  <teiHeader>\n    <fileDesc>\n      <titleStmt><title>{$validated['name']}</title></titleStmt>\n      <publicationStmt><p>Imported via Variance</p></publicationStmt>\n      <sourceDesc><p>Generated automatically</p></sourceDesc>\n    </fileDesc>\n  </teiHeader>\n  <text>\n    <body>\n      <div>\n        <p>\n          {$bodyWithLb}\n        </p>\n      </div>\n    </body>\n  </text>\n</TEI>";

        /* 7. Save .xml */
        Storage::disk('public')->put("{$folderPath}/{$baseName}.xml", $tei);

        /* 8. DB row */
        $version = Version::create([
            'work_id' => $work->id,
            'name'    => $validated['name'],
            'folder'  => $baseName,
        ]);

        return response()->json([
            'message' => 'Version uploaded successfully!',
            'version' => $version,
        ], 201);
    }

    /** Update name */
    public function update(Request $req, $id)
    {
        $version = Version::findOrFail($id);
        $version->update($req->validate(['name' => 'required|string|max:45']));
        return response()->json($version, 200);
    }

    /** Delete version; tolerate missing files */
    public function destroy($id)
    {
        $version = Version::findOrFail($id);

        // Prevent deletion if used in a comparison
        $inUse = Comparison::where('source_id', $version->id)
                 ->orWhere('target_id', $version->id)
                 ->exists();
        if ($inUse) {
            return response()->json(['error' => 'Impossible de supprimer : version utilisée.'], 400);
        }

        $disk  = Storage::disk('public');
        $base  = $version->folder;
        $paths = [
            "uploads/versions/{$base}.xml",
            "uploads/versions/{$base}.txt",
        ];

        $missing = [];
        foreach ($paths as $p) {
            if ($disk->exists($p)) {
                $disk->delete($p);
            } else {
                $missing[] = $p;
            }
        }

        // Remove DB record regardless of file presence
        $version->delete();

        $message = 'Version supprimée avec succès';
        if ($missing) {
            $message .= ' — fichiers introuvables : ' . implode(', ', $missing);
        }

        return response()->json(['message' => $message]);
    }

    /** Serve TEI */
    public function viewXmlClean($id)
    {
        $version = DB::table('versions')->find($id) ?? abort(404);
        $path    = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        file_exists($path) || abort(404);
        return response(file_get_contents($path), 200)->header('Content-Type', 'application/xml');
    }

    /* ──────────────────────────── HELPERS ──────────────────────────── */

    /** Detect + convert arbitrary bytes to UTF‑8 LF */
    private function readFileAsUtf8(string $absPath, ?string $hint = null): string
    {
        $bytes = file_get_contents($absPath);
        // Choose source encoding
        $enc = null;
        if ($hint) {
            if (stripos($hint, 'UTF-8') !== false) {
                $enc = 'UTF-8';
            } elseif (stripos($hint, 'WINDOWS-1252') !== false || stripos($hint, 'ISO-8859') !== false) {
                $enc = 'Windows-1252';
            }
        }
        $enc ??= mb_detect_encoding($bytes, ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'ASCII'], true) ?: 'Windows-1252';

        $utf8 = mb_convert_encoding($bytes, 'UTF-8', $enc);
        return str_replace(["\r\n", "\r"], "\n", $utf8);
    }

    /** Unicode tidy‑up (subset of original txt2tei.py) */
    private function normalizeCharacters(string $txt): string
    {
        return str_replace([
            "\u{2013}", "\u{2212}", "\u{2010}", "\u{2011}", "\u{00AD}", "\u{2026}",
            "\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2018}", "\u{2019}", "\u{02BC}", "\u{00B4}", "\u{02C8}",
            "\u{00A0}", "\u{2002}", "\u{2003}", "\u{2009}", "\u{202F}", "\u{200B}", "\u{FEFF}"
        ], [
            "\u{2014}", '-', '-', '-', '', '...',
            '"', '"', '"', '"', "'", "'", "'", "'", "'",
            ' ', ' ', ' ', ' ', ' ', '', ''
        ], $txt);
    }

    /** Collapse runs of spaces/tabs but leave newlines */
    private function collapseSpacesAndTabs(string $txt): string
    {
        $txt = str_replace("\t", ' ', $txt);
        return preg_replace('/ {2,}/', ' ', $txt);
    }
}
