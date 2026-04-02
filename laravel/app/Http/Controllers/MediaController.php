<?php

namespace App\Http\Controllers;

use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Retourne les URL relatives de la vignette et du PDF d’une œuvre.
     */
    public function index(Work $work)
    {
        $imagePath = $work->image_url;
        $pdfPath = $work->pdf_url ? 'pdf/' . $work->pdf_url : null;

        return response()->json([
            'image_url' => $imagePath
                ? '/uploads_images/' . $imagePath
                : null,
            'image_size' => $imagePath ? $this->safeDiskSize('uploads_images', $imagePath) : null,
            'image_mime' => $imagePath ? $this->safeDiskMimeType('uploads_images', $imagePath) : null,
            'pdf_url'   => $work->pdf_url
                ? '/uploads/pdf/' . $work->pdf_url
                : null,
            'pdf_size' => $pdfPath ? $this->safeDiskSize('uploads', $pdfPath) : null,
        ]);
    }

    /**
     * Upload / remplace la vignette et/ou le PDF.
     */
    public function store(Request $request, Work $work)
    {
        if ($forbidden = $this->forbidIfCannotEditStepOne($work)) {
            return $forbidden;
        }

        $shortTitle = $request->query('short_title', $work->short_title);
        if (!$shortTitle) {
            return response()->json(['error' => 'Short title is required'], 400);
        }

        // Validation : image ≤ 2 Mo, PDF ≤ 10 Mo
        $request->validate([
            'vignette' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'pdf'      => 'nullable|mimetypes:application/pdf|max:10240',
        ]);

        // ---------- VIGNETTE ----------
        if ($request->hasFile('vignette')) {
            if ($work->image_url) {
                Storage::disk('uploads_images')->delete($work->image_url);
                $this->deleteLegacyImage($work->image_url);
            }
            $img  = $request->file('vignette');
            $name = $img->hashName();
            \Log::debug('Uploading vignette', [
                'work_id'    => $work->id,
                'filename'   => $name,
                'target_dir' => public_path('uploads_images'),
                'is_dir'     => is_dir(public_path('uploads_images')),
                'is_writable'=> is_writable(public_path('uploads_images')),
            ]);
            Storage::disk('uploads_images')->putFileAs('', $img, $name);
            $work->image_url = $name;

            $this->mirrorToLegacy($name);
        }

        // ---------- PDF ----------
        if ($request->hasFile('pdf')) {
            if ($work->pdf_url) {
                Storage::disk('uploads')->delete('pdf/' . $work->pdf_url);
                $this->deleteLegacyPdf($work->pdf_url);
            }
            $pdf     = $request->file('pdf');
            $pdfName = $work->id . '.pdf';
            // Stocke sous public/uploads/pdf/{work_id}.pdf
            Storage::disk('uploads')->putFileAs('pdf', $pdf, $pdfName);
            $work->pdf_url = $pdfName;

            $this->mirrorPdfToLegacy($pdfName);
        }

        $work->save();

        return response()->json(['success' => true]);
    }

    /**
     * Supprime un média (vignette ou pdf).
     */
    public function destroy(Work $work, string $type)
    {
        if ($forbidden = $this->forbidIfCannotEditStepOne($work)) {
            return $forbidden;
        }

        if ($type === 'vignette' && $work->image_url) {
            Storage::disk('uploads_images')->delete($work->image_url);
            $this->deleteLegacyImage($work->image_url);
            $work->image_url = null;
        }

        if ($type === 'pdf' && $work->pdf_url) {
            Storage::disk('uploads')->delete('pdf/' . $work->pdf_url);
            $this->deleteLegacyPdf($work->pdf_url);
            $work->pdf_url = null;
        }

        $work->save();
        return response()->json(['success' => true]);
}

    private function forbidIfCannotEdit(Work $work)
    {
        if ($work->is_legacy) {
            return response()->json([
                'error' => 'Les œuvres legacy sont en lecture seule.',
            ], 403);
        }

        if (!auth()->check() || !auth()->user()->can('edit', $work)) {
            return response()->json([
                'error' => 'Vous n’avez pas la permission de modifier cette œuvre.',
            ], 403);
        }

        return null;
    }

    private function forbidIfCannotEditStepOne(Work $work)
    {
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Vous devez être connecté pour modifier cette œuvre.',
            ], 403);
        }

        return null;
    }

    private function mirrorToLegacy(string $fileName): void
    {
        $source = public_path('uploads_images/' . $fileName);
        if (!is_file($source)) {
            return;
        }

        $legacyRoot = base_path('../variance/uploads_images');
        $legacyExists = is_dir($legacyRoot);
        \Log::debug('Mirror legacy image path', [
            'legacy_root' => $legacyRoot,
            'exists'      => $legacyExists,
            'base_path'   => base_path(),
            'parent_exists' => is_dir(dirname($legacyRoot)),
        ]);
        if (!$legacyExists) {
            if (!@mkdir($legacyRoot, 0775, true) && !is_dir($legacyRoot)) {
                \Log::warning('Failed to create legacy uploads_images directory', ['path' => $legacyRoot]);
                return;
            }
        }

        $destination = $legacyRoot . '/' . $fileName;
        if (!@copy($source, $destination)) {
            \Log::warning('Failed to mirror image to legacy site', [
                'source' => $source,
                'destination' => $destination,
            ]);
        }
    }

    private function deleteLegacyImage(string $fileName): void
    {
        $legacyPath = base_path('../variance/uploads_images/' . $fileName);
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    private function mirrorPdfToLegacy(string $fileName): void
    {
        $source = public_path('uploads/pdf/' . $fileName);
        if (!is_file($source)) {
            return;
        }

        $legacyRoot = base_path('../variance/uploads/pdf');
        if (!is_dir($legacyRoot)) {
            if (!@mkdir($legacyRoot, 0775, true) && !is_dir($legacyRoot)) {
                \Log::warning('Failed to create legacy uploads/pdf directory', ['path' => $legacyRoot]);
                return;
            }
        }

        $destination = $legacyRoot . '/' . $fileName;
        if (!@copy($source, $destination)) {
            \Log::warning('Failed to mirror PDF to legacy site', [
                'source' => $source,
                'destination' => $destination,
            ]);
        }
    }

    private function deleteLegacyPdf(string $fileName): void
    {
        $legacyPath = base_path('../variance/uploads/pdf/' . $fileName);
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }

    private function safeDiskSize(string $disk, string $path): ?int
    {
        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeDiskMimeType(string $disk, string $path): ?string
    {
        try {
            return Storage::disk($disk)->mimeType($path);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
