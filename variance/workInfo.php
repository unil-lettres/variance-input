<?php require_once 'php/settings.inc.php'; ?>
<?php
$stmt = $cnx->prepare('SELECT a.id as a_id, a.name as a_name, a.folder as a_folder, w.id as w_id, w.title as w_title, w.folder as w_folder, w.desc as w_desc FROM `authors` a INNER JOIN works w ON w.author_id = a.id WHERE w.`id` = :id');
$stmt->execute(array(':id' => $_GET['id']));
if (!($element = $stmt->fetch(PDO::FETCH_ASSOC))):
	?><strong style="color: red">Œuvre non trouvée !</strong>
<?php endif; ?>
<?php

$url = DIR_REL.'/uploads/pdf/'.$element['w_id'].'.pdf';

header('Location: '.$url );
die();

?>
