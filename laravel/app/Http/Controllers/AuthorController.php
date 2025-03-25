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
            'name' => 'required|string|max:45',
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
        $works = Work::where('author_id', $authorId)->get(['id', 'title', 'short_title']);
        return response()->json($works);
    }    

    public function index()
    {
        $authors = Author::all(['id', 'name']);
        return response()->json($authors);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $author = Author::findOrFail($id);
        $author->name = $request->input('name');
        $author->save();

        return response()->json($author); // or some success JSON
    }

    public function destroy($id)
    {
        $author = Author::findOrFail($id);

        if ($author->works()->exists()) {
            return response()->json(['error' => 'Impossible de supprimer cet auteur car des oeuvres lui sont associées.'], 409);
        }

        $author->delete();

        return response()->json(['success' => true]);
    }

}
