<?php

function chapter_analyzeLevels($data, $folder)
{
    global $cnx;

    $rows = [];
    $i = 0;
    foreach ($data as $row) {
        if ($i === 0) {
            $i++;
            continue;
        }
        $rowData = [];
        foreach ($row as $cell) {
            $rowData[] = $cell;
        }
        $rows[] = $rowData;
    }

    foreach ($rows as $itemRow) {
        $level = $itemRow[0];
        $label = $itemRow[1];
        $startLine = intval($itemRow[2]);
        $endLine = intval($itemRow[3]);

        $levels = explode('.', $level);
        //Parent level
        if (count($levels) === 1) {
            $queryStatement = $cnx->prepare("INSERT INTO `chapters` (`id`, `folder`, `label_chapter`, `chapter_parent`, `start_line_comparison1`, `start_line_comparison2`, `level`)
VALUES
	(NULL, '" . addslashes($folder) . "', '" . addslashes($label) . "', 0, " . $startLine . ", " . $endLine . ", '" . $level . "');");
            $queryStatement->execute();
        } else {
            $parentLevel = substr($level, 0, strrpos($level, '.'));
            $queryStatement = $cnx->prepare("SELECT id from `chapters` WHERE folder='" . addslashes($folder) . "'
                AND level='" . $parentLevel . "'");
            $queryStatement->execute();
            if ($queryStatement->rowCount() === 0) {
                return false;
            }

            $element = $queryStatement->fetch(PDO::FETCH_ASSOC);
            $idParent = $element['id'];

            $queryStatement = $cnx->prepare("INSERT INTO `chapters` (`id`, `folder`, `label_chapter`, `chapter_parent`, `start_line_comparison1`, `start_line_comparison2`, `level`)
VALUES
	(NULL, '" . addslashes($folder) . "', '" . addslashes($label) . "', " . $idParent . ", " . $startLine . ", " . $endLine . ", '" . $level . "');");
            $queryStatement->execute();
        }
    }
    return true;
}

function displayChapters($folder, $way)
{
    echo '<ul>';
    echo displayOneLevelChapter($folder, $way, 0);
    echo '</ul>';
}

function getWorkIdByWorkName($workName) {
    global $cnx;
    $chapters = $cnx->prepare('SELECT `id` FROM works WHERE `folder` = :folder');
    $chapters->execute(array('folder' => $workName));
    $element = $chapters->fetch(PDO::FETCH_ASSOC);
    return $element['id'];
}

function displayNameVersion($workId, $versionName)
{
    global $cnx;
    $chapters = $cnx->prepare('SELECT `name` FROM versions WHERE `folder` = :folder AND work_id=:work_id');
    $chapters->execute(array('folder' => $versionName, 'work_id' => $workId));
    $element = $chapters->fetch(PDO::FETCH_ASSOC);
    return $element['name'];
}

function displayOneLevelChapter($folder, $way, $parentId)
{
    ini_set('display_errors', -1);
    echo '<ul>';
    global $cnx;
    $chapters = $cnx->prepare('SELECT `id`, `folder`, `level`, `label_source`, `label_target`, `chapter_parent`, `start_line_source`, `start_line_target`, id_tome_source, id_tome_target FROM chapters WHERE `folder` = :folder AND `chapter_parent` = :parent');
    $chapters->execute(array('folder' => $folder, 'parent' => $parentId));
    while ($element = $chapters->fetch(PDO::FETCH_ASSOC)):
        $aLink = 'goToPageNumber(\'' . $element['start_line_source'] . '\',\'' . $element['start_line_target'] . '\',\'' . $element['id_tome_source'] . '\',\'' . $element['id_tome_target'] . '\')';
        $aLabel = (($way === 'source') ? $element['label_source'] : $element['label_target']);
        if (trim($aLabel) === '') {
            $aLabel = '&nbsp;';
            $aLink = '';
        }
        echo '<li> <a href="javascript:void(0)" onclick="' . $aLink . '">' . $aLabel . '</a>';
        displayOneLevelChapter($folder, $way, $element['id']);
        echo '</li>';
    endwhile;
    echo '</ul>';

}


