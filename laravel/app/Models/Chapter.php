<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'folder',
        'level',
        'label_source',
        'label_target',
        'chapter_parent',
        'start_line_source',
        'start_line_target',
        'id_tome_source',
        'id_tome_target',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'chapter_parent');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'chapter_parent');
    }

    public function scopeForFolder(Builder $query, string $folder): Builder
    {
        return $query->where('folder', $folder);
    }
}
