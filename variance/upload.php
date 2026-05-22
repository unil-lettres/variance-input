<?php
/*
 * Projet        : Variance
 * Fichier       : index.php
 * Auteur        : GLR
 * Copyright     : 2016 (c)
 * Date          : 19 janv. 2016
 *
 * Description   : Gestionnaire d'upload de nouvelles versions
 *
 * Remarques     :
 * Modifications :
 */

 ini_set('display_errors', 1);
 ini_set('display_startup_errors', 1);
 error_reporting(E_ALL);
 
require_once 'upload_functions.php';
require_once 'php/settings.inc.php';



?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Nouvelle comparaison</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
<div class="container">
    <h1>Nouvelle comparaison</h1>
    <?php
    if (!empty($_POST)) {
        // Upload
        $transliterator = Transliterator::create('Any-Latin; Latin-ASCII; [\u0100-\u7fff] remove; Lower()');
        $author = array(
            'id' => str_replace(array(
                ' ',
                "'"
            ), array(
                '_',
                '-'
            ), $transliterator->transliterate($_POST['author'])),
            'name' => $_POST['author']
        );
        $work = array(
            'id' => str_replace(array(
                ' ',
                "'"
            ), array(
                '_',
                '-'
            ), $transliterator->transliterate($_POST['work'])
            ),
            'title' => $_POST['work'],
            'desc' => $_POST['desc'],
        );

        $path = $author['id'] . '/' . $work['id'];
        $baseName = $author['id'] . '--' . $work['id'];
        if (count($_POST['choices']) == 1) {
            $baseName .=  '--' . $_POST['comparisons'][$_POST['choices'][0]];
        }

        if (!file_exists(UPLOAD_ROOT . '/' . $path)) {
            $uploadOk = @mkdir(UPLOAD_ROOT . '/' . $path, 0777, true);
        } else {
            $uploadOk = chmod(UPLOAD_ROOT . '/' . $path, 0777);
        }
        $uploadOk = $uploadOk && move_uploaded_file($_FILES['archive']['tmp_name'], UPLOAD_ROOT . '/' . $baseName . '.zip');
        $archive = new ZipArchive();
        if ($archive->open(UPLOAD_ROOT . '/' . $baseName . '.zip') && $archive->extractTo(UPLOAD_ROOT . '/' . $path)) {
            $uploadOk = $uploadOk && true;
            $authorsStatement = $cnx->prepare('INSERT IGNORE INTO `authors` (`name`, `folder`) VALUES (:name, :id)');
            $authorsStatement->execute($author);
            $backIdStatement = $cnx->prepare('SELECT `id` FROM `authors` WHERE `folder` = ?');
            $backIdStatement->execute(array($author['id']));
            $work['author_id'] = $backIdStatement->fetchColumn();
            $worksStatement = $cnx->prepare('INSERT IGNORE INTO `works` (`author_id`, `title`, `folder`, `desc`) VALUES (:author_id, :title, :id, :desc)');
            $worksStatement->execute($work);
            $backIdStatement = $cnx->prepare('SELECT `id` FROM `works` WHERE `folder` = ?');
            $backIdStatement->execute(array($work['id']));
            $work = $backIdStatement->fetchColumn();
            $multipleQuery = 'INSERT IGNORE INTO `versions` (`work_id`, `name`, `folder`) VALUES';
            $author = array();
            foreach ($_POST['versions'] as $element) {
                $multipleQuery .= ' (?, ?, ?),';
                $author = array_merge($author, array(
                    $work,
                    $element,
                    $transliterator->transliterate($element),
                ));
                if (!file_exists(UPLOAD_ROOT . '/' . $path . '/' . end($author))) {
                    @mkdir(UPLOAD_ROOT . '/' . $path . '/' . end($author), 0777, true);
                } else {
                    chmod(UPLOAD_ROOT . '/' . $path . '/' . end($author), 0777);
                }
            }
            $multipleQuery = substr($multipleQuery, 0, -1);
            $stmt = $cnx->prepare($multipleQuery);
            $stmt->execute($author);
            $stmt = $cnx->query('SELECT `name`, `id` FROM `versions`');
            $work = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
            $multipleQuery = 'INSERT IGNORE INTO `comparisons` (`source_id`, `target_id`, `folder`) VALUES';
            $author = array();
            $leftComparaisonFileName = null;
            $rightComparaisonFileName = null;
            foreach ($_POST['choices'] as $choice) {
                $multipleQuery .= ' (?, ?, ?),';
                $folder = $transliterator->transliterate($_POST['comparisons'][$choice]);
                if (!file_exists(UPLOAD_ROOT . '/' . $path . '/' . $folder)) {
                    @mkdir(UPLOAD_ROOT . '/' . $path . '/' . $folder, 0777, true);
                } else {
                    chmod(UPLOAD_ROOT . '/' . $path . '/' . $folder, 0777);
                }
                $tmp = explode('-', $_POST['comparisons'][$choice]);
                $author[] = $work[$tmp[0]][0];
                $author[] = $work[$tmp[1]][0];
                $author[] = $folder;

                $leftComparaisonFileName = $tmp[0];
                $rightComparaisonFileName = $tmp[1];
            }
            $multipleQuery = substr($multipleQuery, 0, -1);
            $stmt = $cnx->prepare($multipleQuery);
            $stmt->execute($author);
        } else {
            $uploadOk = false;
        }

        // Comparison data
        copy(UPLOAD_ROOT . '/just_uploaded.xml', UPLOAD_ROOT . '/' . $path . '/' . $baseName . '.xml');

        include 'php/class/class.XMLManipulator.php';

        $content = file_get_contents(UPLOAD_ROOT . '/' . $path . '/' . $leftComparaisonFileName . '_lignes.txt');
        //$modified = iconv('WINDOWS-1252', 'UTF-8', $content);

        $content = \ForceUTF8\Encoding::toUTF8($content);

        $modified = $content;
        $modified = str_replace('\'', '', $modified);
        $modified = str_replace('\\', '', $modified);
        file_put_contents(UPLOAD_ROOT . '/' . $path . '/' . $leftComparaisonFileName . '_lignes.txt', $modified);


        $content = file_get_contents(UPLOAD_ROOT . '/' . $path . '/' . $rightComparaisonFileName . '_lignes.txt');
        //$modified = iconv('WINDOWS-1252', 'UTF-8', $content);

        $content = \ForceUTF8\Encoding::toUTF8($content);

        $modified = $content;
        $modified = str_replace('\'', '', $modified);
        $modified = str_replace('\\', '', $modified);
        file_put_contents(UPLOAD_ROOT . '/' . $path . '/' . $rightComparaisonFileName . '_lignes.txt', $modified);

        $xmlManipulator = new XMLManipulator(UPLOAD_ROOT . '/' . $path . '/' . $baseName . '.xml');
        $comparisons = $xmlManipulator->getComparisons();
        foreach ($comparisons as $i => $comparison) {
            if (!in_array($i, $_POST['choices'])) {
                continue;
            }

            $choicePath = $path . '/' . $_POST['comparisons'][$i];
            $transformations = $xmlManipulator->getTransformations();
            $text_a = new TextManipulator(UPLOAD_ROOT . '/' . $path . '/' . (string)$xmlManipulator->getSourceOriginalName(), UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml');

            // 02LaBelle_Mercure
            $text_b = new TextManipulator(UPLOAD_ROOT . '/' . $path . '/' . (string)$xmlManipulator->getTargetOriginalName(), UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');
            $charsInTextA = $xmlManipulator->getSourceSize();
            $lengthError_a = $charsInTextA - $text_a->getSize();
            $lengthError_b = $xmlManipulator->getTargetSize() - $text_b->getSize();
            // Médite counts a new line as 1 character, but they are Windows ones, we need to switch to UNIX ones
            $text_a->setLineEncoding();
            $text_b->setLineEncoding();
            $text_a->setOffset(-1); // Point de départ
            $text_b->setOffset($charsInTextA + 1); // Longueur de la chaîne
            $i = 0;
            while ($transformations[$i]['start'] < $charsInTextA && list($key, $transformation) = each($transformations)) {

                $debug = $transformation['start'] === 563;
                //file_put_contents(__DIR__.'/log.txt', date('Y-m-d H:i:s').' - modify source transformation'."\r\n", FILE_APPEND);
                $text_a->modify($transformation, $debug);

                $i++;

            }

            $text_a->endModify();
            $text_a->nl2br();
            while (list($key, $transformation) = each($transformations)) {
                //file_put_contents(__DIR__.'/log.txt', date('Y-m-d H:i:s').' - modify dest transformation'."\r\n", FILE_APPEND);
                $text_b->modify( $transformation);
            }
            $text_b->endModify();
            $text_b->nl2br();


            $text_b->mergeReplacements();

            // UTF-8 conversion
            $files = glob('{' . UPLOAD_ROOT . '/' . $choicePath . '/*.xhtml}', GLOB_BRACE);
            foreach ($files as $file) {
                $content  = file_get_contents( $file );
                //$modified = iconv( 'WINDOWS-1252', 'UTF-8', $content );

                $modified = \ForceUTF8\Encoding::toUTF8($content);

                file_put_contents( $file, $modified );
            }

            $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml');
            $modified = str_replace('|', '',  $content);
            $modified = str_replace('\'', '',  $modified);
            file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml', $modified);
            italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml');

            $contentT = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');
            $modifiedT = str_replace('\'', '',  $contentT);
            $modifiedT = str_replace('|', '',  $modifiedT);
            file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml', $modifiedT);
            italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');

            // Page breaks
            try {
                $pages = new SplFileObject(UPLOAD_ROOT . '/' . $path . '/'.$leftComparaisonFileName.'_lignes.txt');
                $pages->setCsvControl("\t");
                $pageBreaker = '<span class="page-marker" data-image-name="%2$03d"><span class="page-number">%1$s</span><img src="/img/settings/page_%3$s.svg" /></span>';
                $txtName = strstr($text_a->getFileName(), '.', true);
                $image_sources = [];
                while ($start = $pages->fgetcsv() and $start[0]) {

                    if (!empty($start[1])) {
                        $image_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_%3$03d.jpg';
                        $thumb_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_%3$03d_thumb.jpg';

                        $image_url = sprintf(strtolower($image_url_pattern), ($xmlManipulator->getSourceVersion()), strtolower($start[1]), strtolower($start[0]), 'right');
                        $thumb_url = sprintf(strtolower($thumb_url_pattern), ($xmlManipulator->getSourceVersion()), strtolower($start[1]), strtolower($start[0]), 'right');

                        $image_sources[] = [
                            'small' => $thumb_url,
                            'big' => $image_url
                        ];
                    } else {

                        $nbr = $start[0];
                        if ($nbr[0] === '0') {
                            $nbr = substr($nbr, 1);
                        }

                        $image_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_' . $nbr . '.jpg';
                        $thumb_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_' . $nbr . '_thumb.jpg';

                        $image_url = sprintf(strtolower($image_url_pattern), ($xmlManipulator->getSourceVersion()), strtolower($start[1]), strtolower($start[0]), 'right');
                        $thumb_url = sprintf(strtolower($thumb_url_pattern), ($xmlManipulator->getSourceVersion()), strtolower($start[1]), strtolower($start[0]), 'right');

                        $image_sources[] = [
                            'small' => $thumb_url,
                            'big' => $image_url
                        ];
                    }

                    if (empty($start[1]) || empty($start[2])) {
                        continue;
                    }


                    $parts = preg_split("/\s|(?<=\w)(?=[\-… .’,\/:;!?)])|(?<=[\-… .’\/,\"!()?])/u", trim(\ForceUTF8\Encoding::toUTF8($start[2])));

                    $displayPage = str_replace('.', '.<br />', $start[1]);
                    $text_a->pageBreakAt($parts, sprintf($pageBreaker, $displayPage, $start[0], 'right'));
                }

                file_put_contents(UPLOAD_ROOT . '/' . $path . '/' . $xmlManipulator->getSourceVersion() . '/images_source_' . strtolower($baseName) . '.json', json_encode($image_sources));
            } catch (\Exception $e) {
                var_dump($e);
            }

            try {
                $pages = new SplFileObject(UPLOAD_ROOT . '/' . $path . '/'.$rightComparaisonFileName.'_lignes.txt');
                $pages->setCsvControl("\t");
                $pageBreaker = '<span class="page-marker" data-image-name="%2$03d"><span class="page-number">%1$s</span><img src="/img/settings/page_%3$s.svg" /></span>';
                $txtName = strstr($text_a->getFileName(), '.', true);
                $image_sources = [];
                while ($start = $pages->fgetcsv() and $start[0]) {

                    if (!empty($start[1])) {
                        $image_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_%3$03d.jpg';
                        $thumb_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_%3$03d_thumb.jpg';

                        $image_url = sprintf(strtolower($image_url_pattern), ($xmlManipulator->getTargetVersion()), strtolower($start[1]), strtolower($start[0]), 'left');
                        $thumb_url = sprintf(strtolower($thumb_url_pattern), ($xmlManipulator->getTargetVersion()), strtolower($start[1]), strtolower($start[0]), 'left');

                        $image_sources[] = [
                            'small' => $thumb_url,
                            'big' => $image_url
                        ];
                    } else {

                        $nbr = $start[0];
                        if ($nbr[0] === '0') {
                            $nbr = substr($nbr, 1);
                        }

                        $image_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_' . $nbr . '.jpg';
                        $thumb_url_pattern = RELATIVE_UPLOAD_ROOT . '/' . $path . '/%1$s/img_%1$s_' . $nbr . '_thumb.jpg';

                        $image_url = sprintf(strtolower($image_url_pattern), ($xmlManipulator->getTargetVersion()), strtolower($start[1]), strtolower($start[0]), 'left');
                        $thumb_url = sprintf(strtolower($thumb_url_pattern), ($xmlManipulator->getTargetVersion()), strtolower($start[1]), strtolower($start[0]), 'left');

                        $image_sources[] = [
                            'small' => $thumb_url,
                            'big' => $image_url
                        ];
                    }

                    if (empty($start[1]) || empty($start[2])) {
                        continue;
                    }


                    $parts = preg_split("/\s|(?<=\w)(?=[\-… .’,\/:;!?)])|(?<=[\-… .’\/,\"!()?])/u", trim(\ForceUTF8\Encoding::toUTF8($start[2])));

                    $displayPage = str_replace('.', '.<br />', $start[1]);
                    $text_b->pageBreakAt($parts, sprintf($pageBreaker, $displayPage, $start[0], 'left'));
                }

                file_put_contents(UPLOAD_ROOT . '/' . $path . '/' . $xmlManipulator->getTargetVersion() . '/images_target_' . strtolower($baseName) . '.json', json_encode($image_sources));
            } catch (\Exception $e) {
                var_dump($e);
            }

            try {
                $files = glob('{' . UPLOAD_ROOT . '/' . $choicePath . '/*.xhtml}', GLOB_BRACE);
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    $modified = str_replace('\'', '', $content);
                    file_put_contents($file, $modified);
                }

                // TRAITEMENT DES ITALIQUES pour fichiers d, i, r, s.xhtml
                italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml', 10000);
                italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml', 10000);
                italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml', 10000);
                italiqueForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml', 10000);
                if (false) {
                    $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml');
                    $modified = str_replace('\\', '', $content);
                    file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml', $modified);

                    $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml');
                    $modified = str_replace('\\', '', $content);
                    file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml', $modified);

                    $content = @file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml');
                    $modified = str_replace('\\', '', $content);
                    file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml', $modified);

                    $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml');
                    $modified = str_replace('\\', '', $content);
                    file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml', $modified);
                }

                // Traitement des exposants ^XXXXX^
                $modified = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml');
                $matchesE = null;
                preg_match_all("/\^([^\^]*)\^/", $modified, $matchesE);
                $nbMatches = count($matchesE[0]);
                if ($nbMatches > 0) {
                    for ($i = 0; $i < $nbMatches; $i++) {
                        $modified = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $modified);

                        // Fichiers de substitution i,r,d,s
                        $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml');
                        $modifiedI = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $content);
                        file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml', $modifiedI);

                        $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml');
                        $modifiedR = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $content);
                        file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml', $modifiedR);

                        $content = @file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml');
                        $modifiedD = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $content);
                        file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml', $modifiedD);

                        $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml');
                        $modifiedS = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $content);
                        file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml', $modifiedS);
                    }
                }
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/source.xhtml', $modified);

                // PAGE DE DROITE
                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');
                $modified = str_replace('|', '',  $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml', $modified);

                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');
                $modified = str_replace('\'', '',  $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml', $modified);
                exposantForOneFile(UPLOAD_ROOT . '/' . $choicePath . '/target.xhtml');

                // TRAITEMENT DES | VERS pilcrow dans les remplacements
                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml');
                $modified = str_replace('|', '¶', $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/i.xhtml', $modified);

                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml');
                $modified = str_replace('|', '¶', $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/r.xhtml', $modified);

                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml');
                $modified = str_replace('|', '¶', $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/d.xhtml', $modified);

                $content = file_get_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml');
                $modified = str_replace('|', '¶', $content);
                file_put_contents(UPLOAD_ROOT . '/' . $choicePath . '/s.xhtml', $modified);


            } catch (\Exception $e) {
                var_dump($e);
                ?><div class="alert alert-info">Pas de retours à la ligne trouvés</div><?php
            }
            ?>
            <div class="alert alert-<?= $uploadOk ? 'success' : 'danger' ?>"><?php
                if ($uploadOk):
                    ?>La nouvelle version a correctement été envoyée<?php
                else:
                    ?>Erreur à l'envoi<?php
                endif; ?></div>
            <?php if ($lengthError_a || $lengthError_b): ?><div class="alert alert-warning"><?php
                if ($lengthError_a):
                    ?>La longueur de la source indiquée dans le fichier XML et celle du fichier fourni diffèrent de <?= $lengthError_a; ?> octets<?php
                endif;
                if ($lengthError_a && $lengthError_b):
                    ?><br /><?php
                endif;
                if ($lengthError_b):
                    ?>La longueur de la cible indiquée dans le fichier XML et celle du fichier fourni diffèrent de <?= $lengthError_b; ?> octets<?php
                endif; ?></div><?php
            endif;
        }
    }
    if (!empty($_FILES['xml'])) {
        move_uploaded_file($_FILES['xml']['tmp_name'], UPLOAD_ROOT . '/just_uploaded.xml');
        $xml = simplexml_load_file(UPLOAD_ROOT . '/just_uploaded.xml');
        $_POST['author'] = $xml->auteur->prenom . ' ' . $xml->auteur->nom;
        $_POST['work'] = (string)$xml->oeuvre->titre;
        $_POST['desc'] = '';//(string)$xml->oeuvre->titre;
        foreach ($xml->arbre->version as $version) {
            $_POST['versions'][] = (string)$version->attributes()->id;
        };
        foreach ($xml->informations as $information) {
            $_POST['comparisons'][] = $information->attributes()->vsource . '-' . $information->attributes()->vcible;
        }
    }
    ?>
    <form method="post" enctype="multipart/form-data" accept-charset="utf-8">
        <?php if (!empty($_FILES['xml'])):  ?>
            <div class="alert alert-info">Veuillez vérifier les informations récupérées du fichier XML</div>
            <div class="form-group">
                <label for="Author">Auteur</label>
                <input type="text" name="author" required="required" list="Authors" autocomplete="on" <?php if (!empty($_POST['author'])): ?>value="<?= $_POST['author']; ?>" <?php endif; ?>id="Author" class="form-control" />
                <p class="help-block">Afin d'éviter des mauvais classements, <strong>merci d'utiliser les propositions de l'auto-complétion</strong></p>
                <datalist id="Authors">
                    <?php
                    $queryResult = $cnx->query('SELECT name FROM authors');
                    while ($element = $queryResult->fetchColumn()): ?>
                    <option value="<?= $element ?>">
                        <?php endwhile; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="Work">Œuvre</label>
                <input type="text" name="work" required="required" list="Works" autocomplete="on" <?php if (!empty($_POST['work'])): ?>value="<?= $_POST['work']; ?>" <?php endif; ?>id="Work" class="form-control" />
                <datalist id="Works">
                    <?php
                    $queryResult = $cnx->query('SELECT title FROM works');
                    while ($element = $queryResult->fetchColumn()): ?>
                    <option value="<?= $element ?>">
                        <?php endwhile; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label for="Desc">Description</label>
                <textarea name="desc" id="Desc" class="form-control" style="resize: vertical;"><?php if (!empty($_POST['desc'])): echo $_POST['desc']; endif; ?></textarea>
            </div>
            <div class="form-group">
                <label>Comparaisons</label><br />
                <?php $first = true;
                foreach ($_POST['comparisons'] as $i => $comparison): ?>
                    <input type="hidden" name="comparisons[]" value="<?= $comparison; ?>" />
                    <label for="Ck_<?= $comparison; ?>" class="checkbox-inline">
                        <input type="checkbox" name="choices[]" value="<?= $i; ?>" checked="checked" id="Ck_<?= $comparison; ?>" /> <?= $comparison; ?>
                    </label>
                <?php endforeach; ?>
                <p class="help-block">Pour des raisons de synchronisation, l'ordre est repris depuis le fichier XML</p>
            </div>
            <div class="form-group">
                <label for="Archive">Archive complète</label>
                <input type="file" name="archive" accept="application/zip" id="Archive" class="form-control" required="required" />
            </div>
            <?php foreach ($_POST['versions'] as $version): ?>
                <input type="hidden" name="versions[]" value="<?= $version; ?>" />
            <?php endforeach;
        else: ?>
            <div class="form-group">
                <label for="XML">Fichier XML</label>
                <input type="file" name="xml" accept="text/xml" id="XML" class="form-control" required="required" />
            </div>
        <?php endif; ?>
        <div class="form-group">
            <button type="submit" class="btn btn-small btn-primary">Envoyer</button>
        </div>
    </form>
</div>
</body>
</html>