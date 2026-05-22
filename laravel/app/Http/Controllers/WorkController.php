<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
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

    public function suggestShortTitle(Request $request)
    {
        $title = trim((string) $request->query('title', ''));

        if ($title === '') {
            return response()->json([
                'short_title' => '',
            ]);
        }

        return response()->json([
            'short_title' => $this->generateUniqueWorkShortTitle($title),
        ]);
    }
    
    public function store(Request $request)
    {
        if ($request->user()?->isRestrictedVersionEditor()) {
            return response()->json([
                'error' => 'Accès limité à l’éditeur de versions.',
            ], 403);
        }

        $incomingShortTitle = strtolower(trim((string) $request->input('short_title', '')));
        if ($incomingShortTitle === '') {
            $incomingShortTitle = $this->generateUniqueWorkShortTitle($request->input('title', ''));
        }

        $request->merge([
            'short_title' => $incomingShortTitle,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:80',
            'short_title' => [
                'required',
                'string',
                'min:2',
                'max:8',
                'regex:/^[a-z]+$/',
                Rule::unique('works', 'short_title'),
            ],
            'catalog_group' => 'nullable|string|in:main,allographic',
            'author_id' => 'required|exists:authors,id',
        ], [
            'title.required' => 'Le titre de l’œuvre est obligatoire.',
            'title.min' => 'Le titre de l’œuvre doit contenir au moins 3 caractères.',
            'title.max' => 'Le titre de l’œuvre ne peut pas dépasser 80 caractères.',
            'short_title.required' => 'Le code abrégé est obligatoire.',
            'short_title.min' => 'Le code abrégé doit contenir entre 2 et 8 caractères.',
            'short_title.max' => 'Le code abrégé doit contenir entre 2 et 8 caractères.',
            'short_title.regex' => 'Le code abrégé ne peut contenir que des lettres minuscules (a à z).',
            'short_title.unique' => 'Ce code abrégé est déjà utilisé par une autre œuvre.',
            'catalog_group.in' => 'Le catalogue sélectionné est invalide.',
            'author_id.required' => 'L’auteur est obligatoire.',
            'author_id.exists' => 'L’auteur sélectionné est invalide.',
        ]);

        $validated['catalog_group'] = $validated['catalog_group'] ?? 'main';

        $author = \App\Models\Author::findOrFail($validated['author_id']);
    
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
        if ($forbidden = $this->forbidIfCannotEdit($work)) {
            return $forbidden;
        }
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
        $work = Work::findOrFail($workId);
        if ($forbidden = $this->forbidIfCannotEdit($work)) {
            return $forbidden;
        }

        $status->desc_status = $request->input('desc_status', $status->desc_status);
        $status->notice_status = $request->input('notice_status', $status->notice_status);
        $status->image_status = $request->input('image_status', $status->image_status);
        $status->comparison_status = $request->input('comparison_status', $status->comparison_status);
        $status->save();

        return response()->json(['success' => true]);
    }

    private function generateUniqueWorkShortTitle(?string $title): string
    {
        $base = $this->normalizeWorkShortTitleBase($title);
        $candidate = $base;
        $suffixIndex = 0;

        while (Work::where('short_title', $candidate)->exists()) {
            $suffix = $this->alphabeticSuffix($suffixIndex);
            $prefixLength = max(1, 8 - strlen($suffix));
            $candidate = substr($base, 0, $prefixLength) . $suffix;
            $suffixIndex++;
        }

        return $candidate;
    }

    private function normalizeWorkShortTitleBase(?string $title): string
    {
        $ascii = Str::lower(Str::ascii((string) $title));
        $wordInitials = $this->extractWorkShortTitleInitials($ascii);
        if (strlen($wordInitials) >= 2) {
            return substr($wordInitials, 0, 8);
        }

        $letters = preg_replace('/[^a-z]/', '', $ascii) ?? '';

        if (strlen($letters) >= 2) {
            return substr($letters, 0, 8);
        }

        if ($letters !== '') {
            return str_pad(substr($letters, 0, 8), 2, 'x');
        }

        return 'oeuvre';
    }

    private function extractWorkShortTitleInitials(string $asciiTitle): string
    {
        preg_match_all('/[a-z]+/', $asciiTitle, $matches);
        $words = $matches[0] ?? [];
        if (count($words) < 2) {
            return '';
        }

        return implode('', array_map(static fn (string $word) => $word[0], $words));
    }

    private function alphabeticSuffix(int $index): string
    {
        $suffix = '';
        $current = $index;

        do {
            $suffix = chr(97 + ($current % 26)) . $suffix;
            $current = intdiv($current, 26) - 1;
        } while ($current >= 0);

        return $suffix;
    }

    public function show($id)
    {
        $work = Work::findOrFail($id);
        return response()->json($work);
    }

    public function update(Request $request, $id)
    {
        $work = Work::findOrFail($id);
        if ($forbidden = $this->forbidIfCannotEdit($work)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title' => 'required|string|min:3|max:80',
            'catalog_group' => 'sometimes|string|in:main,allographic',
            'short_title' => 'sometimes|string',
        ], [
            'catalog_group.in' => 'Le catalogue sélectionné est invalide.',
        ]);

        if ($request->has('short_title')) {
            $incomingShort = strtolower($request->input('short_title', ''));
            $currentShort  = strtolower($work->short_title ?? '');

            if ($incomingShort !== $currentShort) {
                return response()->json([
                    'error' => "Le titre abrégé ne peut pas être modifié car il est utilisé pour les dossiers et fichiers liés à l'œuvre."
                ], 409);
            }
        }

        $payload = ['title' => $validated['title']];
        if (array_key_exists('catalog_group', $validated)) {
            $payload['catalog_group'] = $validated['catalog_group'];
        }

        $work->update($payload);

        return response()->json($work);
    }

    
    

    public function destroy($id)
    {
        $work = Work::findOrFail($id);
        if ($forbidden = $this->forbidIfCannotEdit($work)) {
            return $forbidden;
        }
    
        //  Prevent deletion if work has versions
        if ($work->versions()->exists()) {
            $versions = $work->versions()
                ->orderBy('name')
                ->get(['name'])
                ->pluck('name')
                ->all();

            return response()->json([
                'error' => sprintf(
                    'Impossible de supprimer cette œuvre : %d version(s) y sont encore rattachée(s)%s.',
                    count($versions),
                    $versions ? ' (' . implode(', ', array_slice($versions, 0, 3)) . (count($versions) > 3 ? ', …' : '') . ')' : ''
                ),
                'blocking_versions_count' => count($versions),
                'blocking_versions' => array_slice($versions, 0, 10),
            ], 400);
        }
    
        // Proceed with deletion
        $workId = $work->id;
        $workTitle = $work->title;
        $authorId = $work->author_id;
        $work->delete();

        $this->audit('work.deleted', [
            'work_id' => $workId,
            'work_title' => $workTitle,
            'author_id' => $authorId,
        ]);
    
        return response()->json(['success' => true]);
    }

    private function forbidIfCannotEdit(Work $work)
    {
        if ($work->is_legacy) {
            return response()->json([
                'error' => 'Les œuvres legacy sont en lecture seule.',
            ], 403);
        }

        if (!auth()->check() || !auth()->user()->can('edit', $work)) {
            return response()->json([
                'error' => 'Vous n’avez pas la permission de modifier cette œuvre.',
            ], 403);
        }

        return null;
    }

    private function forbidIfCannotEditStepOne(Work $work)
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Vous devez être connecté pour modifier cette œuvre.',
            ], 403);
        }

        return null;
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
