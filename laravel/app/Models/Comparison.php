<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comparison extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'case_sensitive'   => 'boolean',
        'diacri_sensitive' => 'boolean',
    ];

    /**
     * Relationship: Source Version of the Comparison
     *
     * Defines a `belongsTo` relationship to the `Version` model, associating
     * this comparison with its source version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceVersion()
    {
        return $this->belongsTo(Version::class, 'source_id');
    }

    /**
     * Relationship: Target Version of the Comparison
     *
     * Defines a `belongsTo` relationship to the `Version` model, associating
     * this comparison with its target version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function targetVersion()
    {
        return $this->belongsTo(Version::class, 'target_id');
    }

    /**
     * Relationship: Status of the Comparison
     *
     * Defines a `hasOne` relationship to the `ComparisonStatus` model. This
     * allows access to the status record specific to this comparison.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function status()
    {
        return $this->hasOne(ComparisonStatus::class);
    }

    public function getSourceFilePath()
    {
        return $this->getFilePath('source');
    }

    public function getTargetFilePath()
    {
        return $this->getFilePath('target');
    }

    private function getFilePath($type = 'source')
    {
        $isTarget = $type === 'target';
        $version = $isTarget ? $this->targetVersion : $this->sourceVersion;
        $work = $version->work;
        $author = $work->author;

        return storage_path("app/public/uploads/{$author->folder}/{$work->folder}/comparisons/{$this->id}/{$type}.xhtml");
    }
}
