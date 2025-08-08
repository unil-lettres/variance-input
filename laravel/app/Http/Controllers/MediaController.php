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
        return response()->json([
            'image_url' => $work->image_url
                ? '/uploads_images/' . $work->image_url
                : null,
            'pdf_url'   => $work->pdf_url
                ? '/uploads/pdf/' . $work->pdf_url
                : null,
        ]);
    }

    /**
     * Upload / remplace la vignette et/ou le PDF.
     */
    public function store(Request $request, Work $work)
    {
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
            }
            $img  = $request->file('vignette');
            $name = $img->hashName();
            Storage::disk('uploads_images')->putFileAs('', $img, $name);
            $work->image_url = $name;
        }

        // ---------- PDF ----------
        if ($request->hasFile('pdf')) {
            if ($work->pdf_url) {
                Storage::disk('uploads')->delete('pdf/' . $work->pdf_url);
            }
            $pdf     = $request->file('pdf');
            $pdfName = $work->id . '.pdf';
            // Stocke sous public/uploads/pdf/{work_id}.pdf
            Storage::disk('uploads')->putFileAs('pdf', $pdf, $pdfName);
            $work->pdf_url = $pdfName;
        }

        $work->save();

        return response()->json(['success' => true]);
    }

    /**
     * Supprime un média (vignette ou pdf).
     */
    public function destroy(Work $work, string $type)
    {
        if ($type === 'vignette' && $work->image_url) {
            Storage::disk('uploads_images')->delete($work->image_url);
            $work->image_url = null;
        }

        if ($type === 'pdf' && $work->pdf_url) {
            Storage::disk('uploads')->delete('pdf/' . $work->pdf_url);
            $work->pdf_url = null;
        }

        $work->save();
        return response()->json(['success' => true]);
    }
}
