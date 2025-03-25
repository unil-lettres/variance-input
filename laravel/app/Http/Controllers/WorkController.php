<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Work;
use App\Models\WorkStatus;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;

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
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:80',
            'short_title' => [
                'required',
                'string',
                'min:3',
                'max:8',
                'regex:/^[a-zA-Z0-9_-]+$/',
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
        $work = Work::findOrFail($id);
    
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:80',
            'short_title' => [
                'required',
                'string',
                'min:3',
                'max:8',
                'regex:/^[a-zA-Z0-9_-]+$/',
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
    
        $work->update($validated);
    
        return response()->json($work);
    }
    

    public function destroy($id)
    {
        $work = Work::findOrFail($id);
        $work->delete();

        return response()->json(['success' => true]);
    }
}
