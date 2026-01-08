<?php
/*
 * Projet        : Variance
 * Fichier       : settings.inc.php
 * Auteur        : GLR
 * Copyright     : 2016 (c)
 * Date          : 19 janv. 2016
 *
 * Description   : Fichiers de paramètres
 *
 * Remarques     :
 * Modifications :
 */

define('ROOT', realpath(__DIR__ . '/../'));
require __DIR__ . '/../../vendor/autoload.php';

// On récupère les dossiers dans l'URI de la requête
$uri = preg_split('`/|\\\`', $_SERVER['REQUEST_URI'], -1, PREG_SPLIT_NO_EMPTY);

// On regarde ce qu'elle a en commun d'avec le chemin du script
$baseUrl = array_intersect(
    $uri,
    preg_split(
        '`/|\\\`',
        realpath(dirname(__FILE__)),
        -1,
        PREG_SPLIT_NO_EMPTY
    )
);
if (count($baseUrl)) {
    // S'il y a des trucs en commun…
    $uri = array_slice($uri, count($baseUrl));
    // … on se construit une base d'URL

    $baseUrl = '/' . implode('/', $baseUrl);
} else {
    // Sinon, c'est qu'on est à la racine de l'hébergement
    $baseUrl = '/';
}
/**
 * DIR REL
 * @var string
 */
define('DIR_REL', $baseUrl);


/**
 * The file upload root folder
 *
 * This <strong>MUST NOT</strong> contain the trailing slash
 * @var string
 */
define('RELATIVE_UPLOAD_ROOT', '/uploads');
define('UPLOAD_ROOT', realpath(__DIR__ . '/../../uploads'));


/**
 * The database host
 * @var string
 */
// define('DB_HOST', ROOT);
define('DB_HOST', getenv('MYSQL_SERVERNAME_DEV'));

/**
 * The SQL driver to use
 * @var string
 */
// define('PDO_DRIVER', 'sqlite');
define('PDO_DRIVER', 'mysql');

/**
 * The database name
 * @var string
 */
// define('DB_NAME', 'variance.sqlite')
define('DB_NAME', getenv('MYSQL_DATABASE'));

/**
 * The charset to connect to the database
 * @var string
 */
// define('DB_CHARSET', 'utf-8');
define('DB_CHARSET', 'utf8');

/**
 * The database user
 * @var string
 */
// define('DB_USER', 'charset=' . DB_CHARSET);
define('DB_USER', getenv('MYSQL_USER'));

/**
 * The database user's password
 * @var string
 */
// define('DB_PWD', null);
define('DB_PWD', getenv('MYSQL_PASSWORD'));

/**
 * The Data Source Name for the database
 * @var string
 */
// define('PDO_DSN', PDO_DRIVER . ':' . DB_HOST . '/' . DB_NAME);
define(
    'PDO_DSN',
    PDO_DRIVER . ':dbname=' . DB_NAME . ';host=' . DB_HOST . ';charset=' . DB_CHARSET
);

try {
    $cnx = new PDO(PDO_DSN, DB_USER, DB_PWD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));} catch (\Exception $e) {
    exit($e->getMessage());
}

function getOeuvreUrl($authorFolder, $workFolder, $comparisonId) {
    return '/dev/' . $authorFolder . '/' . $workFolder . '/comparaison/' . $comparisonId;
}

define('COMPARISON_COMPONENTS', [
    'd.xhtml',
    'i.xhtml',
    'r.xhtml',
    's.xhtml',
    'source.xhtml',
    'target.xhtml',
]);

function comparisonHasComponents($baseDir)
{
    foreach (COMPARISON_COMPONENTS as $file) {
        if (!is_file($baseDir . '/' . $file)) {
            return false;
        }
    }
    return true;
}

function comparisonIsPublished($authorFolder, $workFolder, $comparisonFolder)
{
    if (!$authorFolder || !$workFolder || !$comparisonFolder) {
        return false;
    }

    $baseDir = UPLOAD_ROOT . '/' . $authorFolder . '/' . $workFolder . '/' . $comparisonFolder;
    return comparisonHasComponents($baseDir);
}

function comparisonIsDraft($authorFolder, $workFolder, $comparisonId, $comparisonFolder)
{
    if (!$authorFolder || !$workFolder || !$comparisonId) {
        return false;
    }

    $draftDir = UPLOAD_ROOT . '/' . $authorFolder . '/' . $workFolder . '/comparisons/' . $comparisonId;
    if (!comparisonHasComponents($draftDir)) {
        return false;
    }

    if ($comparisonFolder && comparisonIsPublished($authorFolder, $workFolder, $comparisonFolder)) {
        return false;
    }

    return true;
}

function getWorkIdsWithDraftComparisons()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    global $cnx;
    $cache = [];
    $stmt = $cnx->query(
        'SELECT c.id as comparison_id,
                c.folder as comparison_folder,
                w.id as work_id,
                w.folder as work_folder,
                a.folder as author_folder
         FROM comparisons c
         INNER JOIN versions s ON c.source_id = s.id
         INNER JOIN works w ON s.work_id = w.id
         INNER JOIN authors a ON w.author_id = a.id'
    );

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (comparisonIsDraft(
            $row['author_folder'],
            $row['work_folder'],
            (int)$row['comparison_id'],
            $row['comparison_folder']
        )) {
            $cache[(int)$row['work_id']] = true;
        }
    }

    $cache = array_keys($cache);
    return $cache;
}
