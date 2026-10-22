<?php

/**
 * Browsing order is separate from the one-based manifest indices in markers.
 * Alectone's source scans 021/022 (section III/page 45) precede 023–030
 * (pages 35–42) in filename order. Keep the indexed manifest untouched.
 */
function sourceFacsimileBrowsingOrder($author, $work, $comparison, $images)
{
    if ($author !== 'edmond_henri_crisinel' || $work !== 'alectone'
        || (int) ($comparison['id'] ?? 0) !== 146
        || ($comparison['folder'] ?? '') !== '1alc-2alc-run1'
        || ($comparison['source_folder'] ?? '') !== '1alc'
        || ($comparison['target_folder'] ?? '') !== '2alc'
        || !is_array($images) || count($images) !== 33) {
        return null;
    }

    // Fail back to the ordinary order if an editor replaces/reorders this set.
    foreach (array_values($images) as $index => $image) {
        $expected = sprintf('img_1alc_%03d.jpg', $index + 1);
        if (!is_array($image) || !is_string($image['big'] ?? null)
            || basename(parse_url($image['big'] ?? '', PHP_URL_PATH) ?? '') !== $expected) {
            return null;
        }
    }

    return array_merge(range(1, 20), range(23, 30), [21, 22], range(31, 33));
}
