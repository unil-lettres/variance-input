<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Author;
use App\Models\Work;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

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
        $firstPermissionPerWork = Permission::query()
            ->select('work_id', DB::raw('MIN(id) as first_permission_id'))
            ->whereNotNull('work_id')
            ->groupBy('work_id');

        $works = Work::query()
            ->withCount('versions')
            ->leftJoinSub($firstPermissionPerWork, 'first_work_permissions', function ($join) {
                $join->on('first_work_permissions.work_id', '=', 'works.id');
            })
            ->leftJoin('permissions as creator_permissions', 'creator_permissions.id', '=', 'first_work_permissions.first_permission_id')
            ->leftJoin('users as creator_users', 'creator_users.id', '=', 'creator_permissions.user_id')
            ->where('works.author_id', $authorId)
            ->get([
                'works.id',
                'works.title',
                'works.short_title',
                'works.folder',
                'works.is_legacy',
                'works.created_at',
                'works.updated_at',
                'works.versions_count',
                DB::raw('creator_users.name as creator_name'),
            ]);
        return response()->json($works);
    }    

    public function index()
    {
        $authors = Author::all(['id', 'name', 'folder', 'is_legacy']);
        return response()->json($authors);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $author = Author::findOrFail($id);
        if ($author->is_legacy) {
            return response()->json([
                'error' => 'Les auteurs legacy sont en lecture seule.',
            ], 403);
        }
        $author->name = $request->input('name');
        $author->save();

        return response()->json($author); // or some success JSON
    }

    public function destroy($id)
    {
        $author = Author::findOrFail($id);

        if ($author->is_legacy) {
            return response()->json([
                'error' => 'Les auteurs legacy ne peuvent pas être supprimés.',
            ], 403);
        }

        if ($author->works()->exists()) {
            return response()->json(['error' => 'Impossible de supprimer cet auteur car des oeuvres lui sont associées.'], 409);
        }

        $authorId = $author->id;
        $authorName = $author->name;
        $author->delete();

        $this->audit('author.deleted', [
            'author_id' => $authorId,
            'author_name' => $authorName,
        ]);

        return response()->json(['success' => true]);
    }

}
