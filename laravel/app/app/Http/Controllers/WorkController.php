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
    
    // Add new work
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:80',
            'author_id' => 'required|exists:authors,id',
        ]);

        $work = Work::create([
            'title' => $request->title,
            'author_id' => $request->author_id,
        ]);
        
        /*
        WorkStatus::create([
            'work_id' => $work->id,
        ]);
        */
        
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

    // get checkboxes statuses
    public function getStatus($workId)
    {
    $status = \App\Models\WorkStatus::where('work_id', $workId)->firstOrFail();
    return response()->json($status);
    }

    // Set checkbox statuses
    public function updateStatus(Request $request, $workId)
    {
    $status = \App\Models\WorkStatus::where('work_id', $workId)->firstOrFail();

    // Update the status fields based on the incoming request data
    $status->desc_status = $request->input('desc_status', $status->desc_status);
    $status->notice_status = $request->input('notice_status', $status->notice_status);
    $status->image_status = $request->input('image_status', $status->image_status);
    $status->comparison_status = $request->input('comparison_status', $status->comparison_status);
    $status->save();

    return response()->json(['success' => true]);
    }
}
