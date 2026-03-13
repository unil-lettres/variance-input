<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class ProcessFacsimileImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    /**
     * @param  int    $versionId   Version identifier (for logs only)
     * @param  string $basename    Base filename such as img_folder_001
     * @param  string $queuedDisk  Filesystem disk where the original upload is stored
     * @param  string $queuedPath  Relative path (on $queuedDisk) to the original upload
     * @param  string $outputDir   Target directory relative to the public disk
     * @param  int    $maxLongEdge Maximum long edge for the display image
     * @param  int    $mainQuality JPEG quality for the display image
     * @param  int    $thumbWidth  Width for the thumbnail
     * @param  int    $thumbQuality JPEG quality for the thumbnail
     */
    public function __construct(
        public int $versionId,
        public string $basename,
        public string $queuedDisk,
        public string $queuedPath,
        public string $outputDir,
        public int $maxLongEdge,
        public int $mainQuality,
        public int $thumbWidth,
        public int $thumbQuality,
        public ?int $sourcePage = null,
    ) {
        $this->onQueue('facsimiles');
    }

    public function handle(ImageManager $manager): void
    {
        @ini_set('memory_limit', config('variance.facsimile_memory_limit', '512M'));

        if ($this->isCancelled()) {
            Storage::disk($this->queuedDisk)->delete($this->queuedPath);
            return;
        }

        $queuedDisk = Storage::disk($this->queuedDisk);
        if (! $queuedDisk->exists($this->queuedPath)) {
            Log::warning('Facsimile queued file missing', [
                'version_id' => $this->versionId,
                'basename'   => $this->basename,
                'disk'       => $this->queuedDisk,
                'path'       => $this->queuedPath,
            ]);
            return;
        }

        $sourcePath = $queuedDisk->path($this->queuedPath);

        $publicDisk = Storage::disk('public');
        $publicDisk->makeDirectory($this->outputDir);

        $mainPath  = "{$this->outputDir}/{$this->basename}.jpg";
        $thumbPath = "{$this->outputDir}/{$this->basename}_thumb.jpg";

        try {
            $original = $this->readSourceImage($manager, $sourcePath);

            $origWidth  = $original->width();
            $origHeight = $original->height();

            $display = clone $original;
            $longEdge = max($origWidth, $origHeight);
            if ($longEdge > $this->maxLongEdge) {
                $ratio   = $this->maxLongEdge / $longEdge;
                $targetW = max(1, (int) round($origWidth * $ratio));
                $targetH = max(1, (int) round($origHeight * $ratio));
                $display->scale($targetW, $targetH);
            }

            $mainEncoded = $display->encode(new JpegEncoder(quality: $this->mainQuality));
            if ($this->isCancelled()) {
                $queuedDisk->delete($this->queuedPath);
                return;
            }
            $publicDisk->put($mainPath, (string) $mainEncoded);

            $thumbImage = clone $original;
            $thumbImage->scale($this->thumbWidth);
            $thumbEncoded = $thumbImage->encode(new JpegEncoder(quality: $this->thumbQuality));
            $publicDisk->put($thumbPath, (string) $thumbEncoded);

            unset($thumbImage, $thumbEncoded, $display, $mainEncoded, $original);
            gc_collect_cycles();

            $queuedDisk->delete($this->queuedPath);
        } catch (\Throwable $e) {
            Log::error('Facsimile processing failed', [
                'version_id' => $this->versionId,
                'basename'   => $this->basename,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function isCancelled(): bool
    {
        $flag = storage_path('app/private/facsimile_cancel/' . $this->versionId . '.flag');
        return File::exists($flag);
    }

    private function readSourceImage(ImageManager $manager, string $sourcePath)
    {
        if ($this->sourcePage === null) {
            return $manager->read($sourcePath);
        }

        if (!class_exists(\Imagick::class)) {
            throw new \RuntimeException('Le support TIFF multipage nécessite l’extension Imagick.');
        }

        $imagick = new \Imagick();
        try {
            $imagick->readImage($sourcePath . '[' . $this->sourcePage . ']');
            $imagick->setImageFormat('jpeg');
            $blob = $imagick->getImageBlob();
        } finally {
            $imagick->clear();
            $imagick->destroy();
        }

        if (!is_string($blob) || $blob === '') {
            throw new \RuntimeException('Impossible de lire la page TIFF demandée.');
        }

        return $manager->read($blob);
    }
}
