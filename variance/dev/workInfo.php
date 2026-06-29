<?php require_once 'php/settings.inc.php'; ?>
<?php
$stmt = $cnx->prepare('SELECT a.id as a_id, a.name as a_name, a.folder as a_folder, w.id as w_id, w.title as w_title, w.folder as w_folder, w.desc as w_desc, w.pdf_url as w_pdf, w.is_legacy as w_is_legacy FROM `authors` a INNER JOIN works w ON w.author_id = a.id WHERE w.`id` = :id');
$stmt->execute(array(':id' => $_GET['id']));
if (!($element = $stmt->fetch(PDO::FETCH_ASSOC))):
	?><strong style="color: red">Œuvre non trouvée !</strong>
<?php endif; ?>
<?php

$pdfFile = trim((string) ($element['w_pdf'] ?? ''));
if ($pdfFile === '' || !is_file(UPLOAD_ROOT . '/pdf/' . basename($pdfFile))) {
    $legacyPdf = (int) $element['w_id'] . '.pdf';
    if ((bool) ($element['w_is_legacy'] ?? false) && is_file(UPLOAD_ROOT . '/pdf/' . $legacyPdf)) {
        $pdfFile = $legacyPdf;
    }
}
$pdfFile = basename($pdfFile);

if ($pdfFile === '' || !is_file(UPLOAD_ROOT . '/pdf/' . $pdfFile)) {
    http_response_code(404);
    echo '<strong style="color: red">Notice introuvable.</strong>';
    die();
}

$url = '/uploads/pdf/'.rawurlencode($pdfFile);

header('Location: '.$url );
die();

?>
