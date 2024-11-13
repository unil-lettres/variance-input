<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Author;
use App\Models\Work;
use App\Models\Permission;

class AuthorController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $author = Author::create(['name' => $request->name]);

        Permission::create([
            'user_id' => auth()->id(),
            'author_id' => $author->id,
            'permission_type' => 'edit',
        ]);

        return response()->json($author, 201);
    }

    public function getWorksByAuthor($authorId)
    {
        $works = Work::where('author_id', $authorId)->get(['id', 'title']);
        return response()->json($works);
    }

    public function index()
    {
        $authors = Author::all(['id', 'name']);
        return response()->json($authors);
    }
}
