<?php

ini_set('display_errors', -1);
ini_set('max_execution_time', 99999999);
ini_set('memory_limit', '7024M');
ini_set("pcre.recursion_limit", "-1");
ini_set("pcre.backtrack_limit", "-1");

// TRAITEMENT DES ITALIQUES SUR LES CONTENUS
function italiqueForOneFile($filename, $nbLoop = 1000) {

    for ($j = 0; $j < $nbLoop; $j++) {
        $isDebug = $j === 191;
        $content = file_get_contents($filename);
        if (strpos($filename, 'target')>0) {
            //exit;
        }
        $nb = null;
        $modified = preg_replace_callback('/([\\\\])(?:(?=(\\\\?))\2.)*?\1/mu', function ($matches) {
            $openTags = 1;
            $closedTags = 1;
            $without_slashes = str_replace('\\', '', $matches[0]);
            $string = preg_replace('/(<[^\/>]+>)/mu', '$0<em>', $without_slashes, -1, $countOpenTags);
            $openTags = $openTags + $countOpenTags;

            if (empty($string)) {
                $string = $without_slashes;
            }

            $string = preg_replace('/(<[$\/][a-zA-Z]>)/mu', '</em>$0', $string, -1, $countClosedTags);
            $closedTags = $countClosedTags + $closedTags;

            if (empty($string)) {
                $string = $without_slashes;
            }

            $string = '<em>' . $string . '</em>';

            if ($openTags != $closedTags) {
                while ($openTags != $closedTags) {
                    if ($openTags > $closedTags) {
                        $string .= '</em>';
                        $closedTags++;
                    } else {
                        $string = '<em>' . $string;
                        $openTags++;
                    }
                }
            }

            return $string;

        }, $content, 1, $nb);

        if ($modified) {
            file_put_contents($filename, $modified);
        }
    }
}

function exposantForOneFile($filename)
{
    $modified = file_get_contents($filename);
    $matchesE = null;
    preg_match_all("/\^([^\^]*)\^/", $modified, $matchesE);
    $nbMatches = count($matchesE[0]);
    if ($nbMatches > 0) {
        for ($i = 0; $i < $nbMatches; $i++) {
            $modified = str_replace($matchesE[0][$i], '<sup>' . $matchesE[1][$i] . '</sup>', $modified);
        }
        file_put_contents($filename, $modified);
    }
}