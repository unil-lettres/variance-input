<?php
require __DIR__ . '/../dev/php/facsimile_navigation.php';

function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$comparison = ['id' => 146, 'folder' => '1alc-2alc-run1', 'source_folder' => '1alc', 'target_folder' => '2alc'];
$images = [];
foreach (range(1, 33) as $i) {
    $images[] = ['big' => sprintf('/uploads/1alc/img_1alc_%03d.jpg', $i)];
}
$before = $images;
$order = sourceFacsimileBrowsingOrder('edmond_henri_crisinel', 'alectone', $comparison, $images);
check($order === array_merge(range(1, 20), range(23, 30), [21, 22], range(31, 33)), 'Incorrect book sequence');
$sorted = $order; sort($sorted);
check($sorted === range(1, 33), 'Every scan must remain reachable exactly once');
check($images === $before, 'Manifest indices must remain unchanged');
foreach (['id' => 165, 'folder' => 'another-run', 'source_folder' => '2alc', 'target_folder' => '3alc'] as $key => $value) {
    $other = $comparison; $other[$key] = $value;
    check(sourceFacsimileBrowsingOrder('edmond_henri_crisinel', 'alectone', $other, $images) === null, 'Unrelated comparison changed');
}
check(sourceFacsimileBrowsingOrder('another', 'alectone', $comparison, $images) === null, 'Unrelated author changed');
check(sourceFacsimileBrowsingOrder('edmond_henri_crisinel', 'another', $comparison, $images) === null, 'Unrelated work changed');
$reordered = $images; [$reordered[21], $reordered[22]] = [$reordered[22], $reordered[21]];
foreach ([$reordered, array_slice($images, 0, 32), null] as $changed) {
    check(sourceFacsimileBrowsingOrder('edmond_henri_crisinel', 'alectone', $comparison, $changed) === null, 'Changed manifest must use default navigation');
}
echo "Alectone sequence, complete scan coverage, immutable indices and scope/drift guards passed.\n";
