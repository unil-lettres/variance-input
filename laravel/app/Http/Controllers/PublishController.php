<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Comparison;

class PublishController extends Controller
{
    public function publish(Request $request)
    {
        $request->validate(['comparison_id' => 'required|integer']);

        // 1. Récupération de la comparaison --------------------------------
        /** @var Comparison $comparison */
        $comparison = Comparison::findOrFail($request->input('comparison_id'));

        // 2. Récupérer l’œuvre via la version source ------------------------
        $workId = DB::table('versions')
                    ->where('id', $comparison->source_id)
                    ->value('work_id');

        if (!$workId) {
            return response()->json([
                'error' => 'Impossible de retrouver l’œuvre pour cette comparaison'
            ], 422);
        }

        // 3. Récupérer les « folder » auteur / œuvre ------------------------
        $work  = DB::table('works')->where('id', $workId)->first(['folder', 'author_id']);
        $authorFolder = DB::table('authors')
                          ->where('id', $work->author_id)
                          ->value('folder');

        // 4. Construire le dossier de destination final ---------------------
        $destDir = "uploads/{$authorFolder}/{$work->folder}/{$comparison->folder}";
        $destPath = storage_path("app/public/{$destDir}");

        if (!is_dir($destPath)) {
            mkdir($destPath, 0777, true);
        }

        // 5. Copier les XHTML déjà générés ----------------------------------
        //   (ils se trouvent dans uploads/comparisons/{id}/)
        $srcDir = storage_path("app/public/uploads/comparisons/{$comparison->id}");

        foreach (glob("{$srcDir}/*.xhtml") as $file) {
            $basename = basename($file);
            Storage::disk('public')->put(
                "{$destDir}/{$basename}",
                file_get_contents($file)
            );
        }

        return response()->json([
            'status'       => 'ok',
            'published_to' => $destDir
        ]);
    }
}
