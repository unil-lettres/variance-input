<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Work;

class WorkController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'author_id' => 'required|exists:authors,id',
        ]);

        $work = Work::create([
            'title' => $request->title,
            'author_id' => $request->author_id,
        ]);

        return response()->json($work, 201);
    }
}
