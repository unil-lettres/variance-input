<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Version extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'folder', 'work_id'];

    /**
     * Relationship: Work of the Version
     *
     * Defines a `belongsTo` relationship to the `Work` model, linking this
     * version to a specific work.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * Relationship: Comparisons where this is the Source Version
     *
     * Defines a `hasMany` relationship to the `Comparison` model for cases
     * where this version is used as the source.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comparisonsAsSource()
    {
        return $this->hasMany(Comparison::class, 'source_id');
    }

    /**
     * Relationship: Comparisons where this is the Target Version
     *
     * Defines a `hasMany` relationship to the `Comparison` model for cases
     * where this version is used as the target.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comparisonsAsTarget()
    {
        return $this->hasMany(Comparison::class, 'target_id');
    }

    /**
     * Relationship: Status of the Version
     *
     * Defines a `hasOne` relationship to the `VersionStatus` model, allowing
     * access to the status record for this version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(VersionStatus::class);
    }
    
    public function getFileSizeAttribute()
{
    $relative = str_replace('storage/', '', $this->folder);
    return Storage::disk('public')->size($relative) ?? 0;
}

    public function getFileSizeFormattedAttribute()
    {
        $size = $this->file_size;
        if ($size >= 1073741824) {
            return round($size / 1073741824, 2) . ' Go';
        } elseif ($size >= 1048576) {
            return round($size / 1048576, 2) . ' Mo';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' Ko';
        } else {
            return $size . ' octets';
        }
    }

    /**
     * Get Facsimiles associated with this Version as an array with the following structure:
     * [
     *   {
     *     'name': 'basename.jpg',
     *     'big': 'path/to/big/image.jpg',
     *     'thumb': 'path/to/thumb/image.jpg' or null,
     *     'hasThumb': true or false
     *   },
     *   ...
     * ]
     */
    public function getFacsimiles() {
      $work    = $this->work;
      $author  = $work->author;

      // Dossier relatif (dans storage/app/public)
      $dirRel = "uploads/{$author->folder}/{$work->folder}/{$this->folder}";
      $disk   = Storage::disk('public');

      if (! $disk->exists($dirRel)) {
          return null;          // aucun fichier
      }

      // Liste des fichiers
      $all = collect($disk->files($dirRel));

      return $all
          ->filter(fn ($p) => preg_match('/\.(jpe?g|png)$/i', $p) && ! str_contains($p, '_thumb'))
          ->values()
          ->map(function ($p) use ($disk) {

              // chemin miniature : img_*_thumb.jpg
              $thumbPath  = preg_replace('/(\.\w+)$/', '_thumb$1', $p);
              $thumbExist = $disk->exists($thumbPath);

              return [
                  'name'      => basename($p),
                  'big'       => '/storage/'.$p,                    // ✅ URL publique
                  'thumb'     => $thumbExist ? '/storage/'.$thumbPath : null,
                  'hasThumb'  => $thumbExist,
              ];
          });
    }
}
