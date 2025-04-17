<?php

namespace App\Http\Controllers;

use App\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /**
     * Retourne les URL de la vignette et du PDF d’une œuvre.
     */
    public function index(Work $work)
    {
        return response()->json([
            'image_url' => $work->image_url,
            'pdf_url'   => $work->pdf_url,
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

        // Validation : image ≤ 2 Mo, PDF ≤ 10 Mo
        $request->validate([
            'vignette' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'pdf'      => 'nullable|mimetypes:application/pdf|max:10240',
        ]);

        // ---------- VIGNETTE ----------
        if ($request->hasFile('vignette')) {
            if ($work->image_url) {
                Storage::disk('public')->delete(Str::after($work->image_url, 'storage/'));
            }
            $img      = $request->file('vignette');
            $imgPath  = "uploads/{$shortTitle}/vignette";
            $imgName  = $img->hashName();               // nom unique pour éviter les collisions
            $img->storeAs($imgPath, $imgName, 'public');
            $work->image_url = "storage/{$imgPath}/{$imgName}";
        }

        // ---------- PDF ----------
        if ($request->hasFile('pdf')) {
            if ($work->pdf_url) {
                Storage::disk('public')->delete(Str::after($work->pdf_url, 'storage/'));
            }
            $pdf      = $request->file('pdf');
            $pdfPath  = "uploads/{$shortTitle}/pdf";
            $pdfName  = $pdf->getClientOriginalName();  // on garde le nom d’origine
            $pdf->storeAs($pdfPath, $pdfName, 'public');
            $work->pdf_url = "storage/{$pdfPath}/{$pdfName}";
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
            Storage::disk('public')->delete(Str::after($work->image_url, 'storage/'));
            $work->image_url = null;
        }

        if ($type === 'pdf' && $work->pdf_url) {
            Storage::disk('public')->delete(Str::after($work->pdf_url, 'storage/'));
            $work->pdf_url = null;
        }

        $work->save();
        return response()->json(['success' => true]);
    }
}
