<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Work;
use App\Models\WorkStatus;
use App\Models\Permission;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Str;

class WorkController extends Controller
{
    // WORK_SELECTOR.BLADE
    // Check with WorkPolicy for edit rights
    public function canEdit($id)
    {
        $work = Work::findOrFail($id);

        $canEdit = auth()->user()->can('edit', $work);

        return response()->json(['canEdit' => $canEdit]);
    }
    
    public function store(Request $request)
    {
        $request->merge([
            'short_title' => strtolower($request->input('short_title', '')),
        ]);

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:80',
            'short_title' => [
                'required',
                'string',
                'min:2',
                'max:8',
                'regex:/^[a-z]+$/',
                // Uniqueness scoped to the author
                function ($attribute, $value, $fail) use ($request) {
                    if (Work::where('author_id', $request->author_id)->where('short_title', $value)->exists()) {
                        $fail("Le titre abrégé '$value' existe déjà pour cet auteur.");
                    }
                }
            ],
            'author_id' => 'required|exists:authors,id',
        ]);
    
        $work = Work::create($validated);
    
        $work->workStatus()->create([]);
    
        Permission::create([
            'user_id' => auth()->id(),
            'work_id' => $work->id,
            'permission_type' => 'edit',
        ]);
    
        return response()->json($work, 201);
    }
    
    // DESCRIPTION.BLADE
    // Get description field
    public function getDescription($id)
    {
        $description = Work::findOrFail($id)->desc;
        return response()->json(['description' => $description]);
    }
    
    // Update description field
    public function updateDescription(Request $request, $workId)
    {
        //$request->validate([
        //    'desc' => 'required|string',
        //]);

        $work = Work::findOrFail($workId);
        $work->desc = $request->desc;
        $work->save();

        return response()->json(['success' => true, 'message' => 'Description updated successfully']);
    }

    // STATUS.BLADE
    // Get checkboxes statuses
    public function getStatus($workId)
    {
        $status = WorkStatus::where('work_id', $workId)->firstOrFail();
        return response()->json($status);
    }

    // Set checkbox statuses
    public function updateStatus(Request $request, $workId)
    {
        $status = WorkStatus::where('work_id', $workId)->firstOrFail();

        $status->desc_status = $request->input('desc_status', $status->desc_status);
        $status->notice_status = $request->input('notice_status', $status->notice_status);
        $status->image_status = $request->input('image_status', $status->image_status);
        $status->comparison_status = $request->input('comparison_status', $status->comparison_status);
        $status->save();

        return response()->json(['success' => true]);
    }

    public function show($id)
    {
        $work = Work::findOrFail($id);
        return response()->json($work);
    }

    public function update(Request $request, $id)
{
    $work = Work::with('versions')->findOrFail($id); // eager load versions

    $request->merge([
        'short_title' => strtolower($request->input('short_title', '')),
    ]);

    $validated = $request->validate([
        'title' => 'required|string|min:3|max:80',
        'short_title' => [
            'required',
            'string',
            'min:2',
            'max:8',
            'regex:/^[a-z]+$/',
            function ($attribute, $value, $fail) use ($work) {
                $exists = Work::where('author_id', $work->author_id)
                    ->where('short_title', $value)
                    ->where('id', '!=', $work->id)
                    ->exists();
                if ($exists) {
                    $fail("Un autre travail utilise déjà le titre abrégé '$value' pour ce même auteur.");
                }
            }
        ],
    ]);

    $oldShort = $work->short_title;
    $newShort = $validated['short_title'];

    if ($oldShort !== $newShort) {
        $work->loadMissing('author:id,folder');

        // Prevent edits once versions exist (XML filenames depend on short title)
        if ($work->versions()->exists()) {
            return response()->json([
                'error' => "Impossible de modifier le titre abrégé car des versions existent déjà pour cette œuvre."
            ], 409);
        }

        if (!empty($work->image_url) || !empty($work->pdf_url)) {
            return response()->json([
                'error' => "Impossible de modifier le titre abrégé car des médias (vignette ou PDF) sont déjà associés à cette œuvre."
            ], 409);
        }

        // Guard against comparisons linked to this work (future-proof)
        $versionIds = $work->versions->pluck('id');
        if ($versionIds->isNotEmpty()) {
            $hasComparisons = DB::table('comparisons')
                ->whereIn('source_id', $versionIds)
                ->orWhereIn('target_id', $versionIds)
                ->exists();

            if ($hasComparisons) {
                return response()->json([
                    'error' => "Impossible de modifier le titre abrégé car des comparaisons existent déjà pour cette œuvre."
                ], 409);
            }
        }

        // Ensure uploads/<author>/<work>/ is empty before allowing rename
        $authorFolder = $work->author->folder ?? null;
        $workFolder   = $work->folder ?? null;
        if ($authorFolder && $workFolder) {
            $relativePath = $authorFolder . '/' . $workFolder;
            $uploadsDisk  = Storage::disk('uploads');
            if ($uploadsDisk->exists($relativePath)) {
                $containsFiles = !empty($uploadsDisk->allFiles($relativePath));
                $containsDirs  = !empty($uploadsDisk->directories($relativePath));
                if ($containsFiles || $containsDirs) {
                    return response()->json([
                        'error' => "Impossible de modifier le titre abrégé car des fichiers sont déjà présents dans le dossier de l'œuvre."
                    ], 409);
                }
            }
        }
    }

    $work->update($validated);
    $work->save();

    return response()->json($work);
}

    
    

    public function destroy($id)
    {
        $work = Work::findOrFail($id);
    
        //  Prevent deletion if work has versions
        if ($work->versions()->exists()) {
            return response()->json([
                'error' => 'Impossible de supprimer cette œuvre car elle contient encore des versions.'
            ], 400);
        }
    
        // Proceed with deletion
        $work->delete();
    
        return response()->json(['success' => true]);
    }
    



    // public function storeMedia(Request $request, $id)
    // {
    //     $work = Work::findOrFail($id);
    //     $shortTitle = $request->query('short_title');
    
    //     if (!$shortTitle) {
    //         return response()->json(['error' => 'Short title is required'], 400);
    //     }
    
    //     // Validate file types and sizes (MB = kilobytes)
    //     $request->validate([
    //         'vignette' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',  // max 2MB
    //         'pdf' => 'nullable|file|mimes:pdf|max:10240',                   // max 10MB
    //     ]);
    
    //     if ($request->hasFile('vignette')) {
    //         // 🧹 Remove old file
    //         if ($work->image_url) {
    //             $oldPath = str_replace('storage/', '', $work->image_url);
    //             Storage::disk('public')->delete($oldPath);
    //         }
    
    //         $file = $request->file('vignette');
    //         $originalName = $file->getClientOriginalName();
    //         $storePath = "uploads/{$shortTitle}/vignette";
    //         $relativePath = "storage/{$storePath}/{$originalName}";
    
    //         // Store file (keep original name)
    //         $file->storeAs($storePath, $originalName, 'public');
    //         $work->image_url = $relativePath;
    //     }
    
    //     if ($request->hasFile('pdf')) {
    //         if ($work->pdf_url) {
    //             $oldPath = str_replace('storage/', '', $work->pdf_url);
    //             Storage::disk('public')->delete($oldPath);
    //         }
    
    //         $file = $request->file('pdf');
    //         $originalName = $file->getClientOriginalName();
    //         $storePath = "uploads/{$shortTitle}/pdf";
    //         $relativePath = "storage/{$storePath}/{$originalName}";
    
    //         $file->storeAs($storePath, $originalName, 'public');
    //         $work->pdf_url = $relativePath;
    //     }
    
    //     $work->save();
    
    //     return response()->json(['success' => true]);
    // }
    

    // public function getMedia($id)
    // {
    //     $work = Work::findOrFail($id);

    //     return response()->json([
    //         'image_url' => $work->image_url,
    //         'pdf_url' => $work->pdf_url,
    //     ]);
    // }

    // public function destroyMedia($workId, $type)
    // {
    //     \Log::debug("Called destroyMedia", [
    //         'workId' => $workId,
    //         'type' => $type
    //     ]);
        
    //     $work = Work::findOrFail($workId);
    
    //     if ($type === 'vignette' && $work->image_url) {
    //         $relativePath = str_replace('storage/', '', $work->image_url);
            
    //         \Log::debug("Trying to delete image:", ['path' => $relativePath]);
        
    //         $deleted = Storage::disk('public')->delete($relativePath);
    //         \Log::debug("Image deleted?", ['result' => $deleted]);
        
    //         if ($deleted) {
    //             $work->image_url = null;
    //         }
    //     }
        
        
    
    //     if ($type === 'pdf' && $work->pdf_url) {
    //         Storage::disk('public')->delete(str_replace('storage/', '', $work->pdf_url));
    //         $work->pdf_url = null;
    //     }
    
    //     $work->save();
    
    //     return response()->json(['success' => true]);
    // }
    

}
