<?php

require_once 'php/settings.inc.php';
require_once 'backoff/includes/chapter_functions.php';
$params = array(
    'size' => 'font-medium',
    'leading' => 'lineheight-medium',
    'contrast' => 'light-bkg',
    'font' => 'serif',
);
if (!empty($_COOKIE['viewer_params'])) {
    $params = array_merge($params, json_decode($_COOKIE['viewer_params'], true));
} else {
    setcookie('viewer_params', json_encode($params), strtotime('next year'));
} ?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comparaisons</title>
    <meta name="description" content="description"/>
    <!--	The only one css file allowed to be in header-->
    <link rel="stylesheet" href="<?php echo DIR_REL ?>/app/js/imageviewer.css?v1">
    <link rel="stylesheet" href="<?php echo DIR_REL ?>/dist/css/screen.min.css?v3">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,600,300" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css">
    <link rel="icon" type="image/png" href="favicon.png"/>

    <!--	The only one plugin allowed to be in header-->
    <script src="<?php echo DIR_REL ?>/app/js/vendors/modernizr-custom.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <script type="application/javascript"
            src="https://cdnjs.cloudflare.com/ajax/libs/js-cookie/2.1.2/js.cookie.min.js"></script>

    <style type="text/css">
        .paging-image {
            display: none;
            position: relative;
        }

        .closer {
            position: absolute;
            top: 1em;
            right: 1em;
        }
    </style>

    <!--[if IE]>
    <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>

    <![endif]-->
</head>
<body class="cover_section">

<!--[if lt IE 9]>
<div style="background: #ffff76; padding: 20px;">
    <p style="text-align: center" class="browserupgrade">Vous utilisez un navigateur <strong>obsolète</strong>. Veuillez
        le<a style="text-decoration: underline;" href="http://browsehappy.com/">mettre à jour</a> afin d'améliorer votre
        expérience sur internet.</p>
</div>
<![endif]-->

<div id="message-error">
    <div class="message-content">
        <a href="/"><img src="/img/logo_min_variance.svg"></a>
        <p>Désolé, l'interface de Variance a été conçue pour une résolution minimum de 1200 pixels.</p>
    </div>

</div>

<div id="General-Wrapper">
    <div class="loader">
        <div class="container">
            <div class="circle"><img src="/img/logo_min_variance.svg"></div>
        </div>
    </div>
    <main class="container-fluid interface <?= implode(' ', $params); ?>">
        <nav id="navBar" class="interface__nav clearfix">
            <a href="/" title="Retour au catalogue" class="logo"><img src="/img/logo_min_variance.svg"></a>
            <a href="#" title="Options d'affichage" class="settings_button" id="workButton" data-settings="USC"><img
                        src="/img/cog.svg"></a>
            <a href="#" title="Choisir une comparaison" class="settings_button" id="bookButton" data-settings="BSC"><img
                        src="/img/book.svg"></a>
            <a href="#" title="Suppression" class="mod_settings delete" data-cat="delete" id="Delete"><img
                        src="/img/delete.svg"><span>Suppression</span></a>
            <a href="#" title="Insertion" class="mod_settings add" data-cat="add" id="Add"><img
                        src="/img/add.svg"><span>Insertion</span></a>
            <a href="#" title="Remplacement" class="mod_settings replace" data-cat="replace" id="Replace"><img
                        src="/img/replace.svg"><span>Remplacement</span></a>
            <a href="#" title="Déplacement" class="mod_settings move" data-cat="move" id="Move"><img
                        src="/img/move.svg"><span>Déplacement</span></a>
            <div class="small_btns">
              <a href="javascript:void(0)" title="Orthographe" onclick="displayVariation('o')" class="button_variation button_variation_o">O</a>
              <a href="javascript:void(0)" title="Ponctuation" onclick="displayVariation('p')" class="button_variation button_variation_p">P</a>
            </div>
        </nav>
        <div class="interface__main">
            <section id="settings" class="interface__settings">
                <div class="settings_wrapper">
                    <form class="book_settings js-settings" id="BSC">
                        <div class="book_settings__item author pull-left" style="margin-right:10px">
                            <span class="label">Auteur</span>
                            <span class="author__name"><?php
                                if (!empty($_GET['author'])):
                                    $authorStatement = $cnx->prepare('SELECT `name` FROM authors WHERE `folder` = :folder');
                                    $authorStatement->execute(array('folder' => $_GET['author']));
                                    if ($name = $authorStatement->fetchColumn()) {
                                        echo $name;
                                        ?><input type="hidden" name="author"
                                                 value="<?php echo $_GET['author']; ?>" /><?php
                                        $path = $_GET['author'];
                                    }
                                endif; ?></span>
                        </div>

                        <div class="book_settings__item oeuvre pull-left">
                            <span class="label">Œuvre</span>
                            <span class="oeuvre__name"><?php
                                if (!empty($_GET['work'])):
                                    $workStatement = $cnx->prepare('SELECT `id`, `title` FROM works WHERE `folder` = :folder');
                                    $workStatement->execute(array('folder' => $_GET['work']));
                                    if ($work = $workStatement->fetch(PDO::FETCH_ASSOC)) {
                                        echo $work['title'];
                                        ?><input type="hidden" name="work" value="<?php echo $_GET['work']; ?>" /><?php
                                        $path .= '/' . $_GET['work'];
                                    }
                                endif; ?></span>
                        </div>


                        <div class="book_settings__item comparison pull-left">
                            <span class="label">Comparaisons</span>
                            <?php if (!empty($_GET['work']) && ($content = glob(UPLOAD_ROOT . '/' . $path . '/*-*', GLOB_ONLYDIR))): ?>
                                <select name="comparison" id="comparisonId" class="form-control">
                                    <?php $versionNames = array(); ?>
                                    <?php $optionEntries = array(); ?>
                                    <?php foreach ($content as $element): ?>
                                        <?php

                                        $folders = explode('-', substr(strrchr($element, '/'), 1));

                                        $comparisonName = implode('-', $folders);

                                        $foldersSql = '"' . implode('","', $folders) . '"';

                                        $foldersStatement = $cnx->prepare('SELECT c.number as c_number, c.folder as c_folder, c.prefix_label as c_prefix_label, v.name as name, v.folder folder FROM versions v, comparisons c WHERE v.folder IN (' . $foldersSql . ') AND work_id = :workid AND c.folder LIKE :folder ORDER BY c.number ASC');
                                        $foldersStatement->execute(array('workid' => $work['id'], 'folder' => $comparisonName));


                                        while ($folderName = $foldersStatement->fetch(PDO::FETCH_ASSOC)) {
                                            $prefix = isset($folderName['c_prefix_label'])
                                                ? trim($folderName['c_prefix_label'])
                                                : '';
                                            if ($prefix !== '' && stripos($prefix, 'auto') === 0) {
                                                $prefix = '';
                                            }
                                            if ($prefix !== '' && substr($prefix, -1) !== ' ') {
                                                $prefix .= ' ';
                                            }

                                            $versionNames[$folderName['folder']] = array(
                                                $folderName['c_number'],
                                                $prefix,
                                                $folderName['name']
                                            );
                                        }

                                        $sourceLabel = isset($versionNames[$folders[0]])
                                            ? trim($versionNames[$folders[0]][1] . $versionNames[$folders[0]][2])
                                            : '';
                                        $targetLabel = isset($versionNames[$folders[1]][2])
                                            ? $versionNames[$folders[1]][2]
                                            : '';

                                        $isSelected = !empty($_GET['comparison']) && (UPLOAD_ROOT . '/' . $_GET['comparison'] == $element);
                                        if ($isSelected) {
                                            $path = $_GET['comparison'];
                                        }

                                        $optionEntries[] = array(
                                            'order'    => isset($versionNames[$folders[0]][0]) ? (int)$versionNames[$folders[0]][0] : PHP_INT_MAX,
                                            'label'    => trim($sourceLabel) . ' -> ' . $targetLabel,
                                            'value'    => getOeuvreUrl($_GET['author'], $_GET['work'], substr(strrchr($element, '/'), 1)),
                                            'selected' => $isSelected,
                                        );
                                        ?>
                                    <?php endforeach; ?>
                                    <?php
                                    usort($optionEntries, function ($a, $b) {
                                        if ($a['order'] === $b['order']) {
                                            return strcmp($a['label'], $b['label']);
                                        }
                                        return ($a['order'] < $b['order']) ? -1 : 1;
                                    });

                                    $displayIndex = 1;
                                    foreach ($optionEntries as $entry) {
                                        $label = $displayIndex . '. ' . $entry['label'];
                                        ?>
                                        <option value="<?php echo $entry['value']; ?>"<?php if ($entry['selected']): ?> selected="selected"<?php endif; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php
                                        $displayIndex++;
                                    }
                                    ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="book_settings__item book-info pull-left">
                            <span class="label">Notice</span>
                            <a href="<?php echo DIR_REL . '/workInfo.php?id=' . $work['id'] ?>" target="_blank"
                               class="book-info__name"><img src="/img/book_info.svg"/></a>
                        </div>

                        <a href="#" title="Cacher" class="btn_validate" data-type="comparison"><img
                                    src="/img/btn_up.svg"></a>
                    </form>
                    <div class="ui_settings js-settings" id="USC">
                        <div class="ui_settings__size pull-left ui_settings__item">
                            <span class="label">Taille</span>
                            <div class="settings-btns">
                                <a href="#"
                                   class="<?php if ($params['size'] == 'font-big'): ?>active <?php endif; ?>js-font-big"
                                   title="Grande">
                                    <img src="/img/settings/font_size_big.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['size'] == 'font-medium'): ?>active <?php endif; ?>js-font-medium"
                                   title="Moyenne">
                                    <img src="/img/settings/font_size_medium.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['size'] == 'font-small'): ?>active <?php endif; ?>js-font-small"
                                   title="Petite">
                                    <img src="/img/settings/font_size_small.svg">
                                </a>
                            </div>
                        </div>
                        <div class="ui_settings__line ui_settings__item pull-left">
                            <span class="label">Interlignage</span>
                            <div class="settings-btns">
                                <a href="#"
                                   class="<?php if ($params['leading'] == 'lineheight-big'): ?>active <?php endif; ?>js-lineheight-big"
                                   title="Grande">
                                    <img src="/img/settings/line_height_big.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['leading'] == 'lineheight-medium'): ?>active <?php endif; ?>js-lineheight-medium"
                                   title="Moyenne">
                                    <img src="/img/settings/line_height_medium.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['leading'] == 'lineheight-small'): ?>active <?php endif; ?>js-lineheight-small"
                                   title="Petite">
                                    <img src="/img/settings/line_height_small.svg">
                                </a>
                            </div>
                        </div>
                        <div class="ui_settings__line ui_settings__item pull-left">
                            <span class="label">Contraste</span>
                            <div class="settings-btns">
                                <a href="#"
                                   class="<?php if ($params['contrast'] == 'light-bkg'): ?>active <?php endif; ?>js-light-bkg"
                                   title="Moyenne">
                                    <img src="/img/settings/contrast_black.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['contrast'] == 'dark-bkg'): ?>active <?php endif; ?>inverted js-dark-bkg"
                                   title="Grande">
                                    <img src="/img/settings/contrast_white.svg">
                                </a>
                            </div>
                        </div>
                        <div class="ui_settings__line ui_settings__item pull-left">
                            <span class="label">Style typographique</span>
                            <div class="settings-btns">
                                <a href="#"
                                   class="<?php if ($params['font'] === 'ss-serif'): ?>active <?php endif; ?>js-ss-serif"
                                   title="Sans empattement">
                                    <img src="/img/settings/font_ss_serif.svg">
                                </a>
                                <a href="#"
                                   class="<?php if ($params['font'] === 'serif'): ?>active <?php endif; ?>js-serif"
                                   title="Avec empattements">
                                    <img src="/img/settings/font_serif.svg">
                                </a>
                            </div>
                        </div>
                        <a href="#" title="Cacher" class="btn_validate" data-type="settings"><img src="/img/btn_up.svg"></a>
                    </div>
                </div>
            </section>
            <section id="tools" class="interface__tools">
                <div class="cat-item" id="delete">
                    <ol>
                        <?php @include UPLOAD_ROOT . '/' . $path . '/s.xhtml'; ?>
                    </ol>
                </div>
                <div class="cat-item" id="add">
                    <ol>
                        <?php @include UPLOAD_ROOT . '/' . $path . '/i.xhtml'; ?>
                    </ol>
                </div>
                <div class="cat-item" id="replace">
                    <ol>
                        <?php @include UPLOAD_ROOT . '/' . $path . '/r.xhtml'; ?>
                    </ol>
                </div>
                <div class="cat-item" id="move">
                    <ol>
                        <?php @include UPLOAD_ROOT . '/' . $path . '/d.xhtml'; ?>
                    </ol>
                </div>
            </section>

            <a href="#" title="Afficher les table des matières" id="chapters-btn"><img src="/img/chapters.svg"
                                                                                       title="Table des matières"
                                                                                       alt="Table des matières"/></a>

            <?php $versions = explode('-', substr(strrchr($path, '/'), 1));
            $pathForImages = substr($path, 0, strrpos($path, '/')); ?>
            <section id="content" class="interface__content clearfix">
                <div id="js-workarea-left" class="col-sm-6 workarea workarea--left">
                    <div class="paging-image">
                        <div class="paging-image__wrapper"
                             data-src="<?php echo DIR_REL . RELATIVE_UPLOAD_ROOT . '/' . $pathForImages . '/' . $versions[0] . '/img_' . $versions[0]; ?>_001.jpg"
                             data-high-res-src="<?php echo DIR_REL . RELATIVE_UPLOAD_ROOT . '/' . $pathForImages . '/' . $versions[0] . '/img_' . $versions[0]; ?>_001.jpg">
                        </div>
                        <a href="#" class="closer"><i class="fa fa-times" aria-hidden="true"></i></a>
                        <a href="#" class="prev" title="Page précédente"><i class="fa fa-caret-up"
                                                                            aria-hidden="true"></i></a>
                        <a href="#" class="next" title="Page suivante"><i class="fa fa-caret-down"
                                                                          aria-hidden="true"></i></a>
                        <a href="#" class="zoom" title="Double-clic sur l'image pour zoomer"><i class="fa fa-search"
                                                                                                aria-hidden="true"></i></a>
                    </div>
                    <div class="workarea--left__container wrkarea">
                        <div class="txt-container" dir="ltr">
                            <?php @include UPLOAD_ROOT . '/' . $path . '/source.xhtml'; ?>
                        </div>
                    </div>
                </div>
                <div id="js-workarea-right" class="col-sm-6 workarea workarea--right">
                    <div class="paging-image">
                        <div class="paging-image__wrapper"
                             data-src="<?php echo DIR_REL . RELATIVE_UPLOAD_ROOT . '/' . $pathForImages . '/' . $versions[1] . '/img_' . $versions[1]; ?>_001.jpg"
                             data-high-res-src="<?php echo DIR_REL . RELATIVE_UPLOAD_ROOT . '/' . $pathForImages . '/' . $versions[1] . '/img_' . $versions[1]; ?>_001.jpg">
                        </div>
                        <a href="#" class="closer"><i class="fa fa-times" aria-hidden="true"></i></a>
                        <a href="#" class="prev" title="Page précédente"><i class="fa fa-caret-up"
                                                                            aria-hidden="true"></i></a>
                        <a href="#" class="next" title="Page suivante"><i class="fa fa-caret-down"
                                                                          aria-hidden="true"></i></a>
                        <a href="#" class="zoom" title="Double-clic sur l'image pour zoomer"><i class="fa fa-search"
                                                                                                aria-hidden="true"></i></a>
                    </div>
                    <div class="workarea--right__container wrkarea">
                        <div class="txt-container">
                            <?php @include UPLOAD_ROOT . '/' . $path . '/target.xhtml'; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<div id="chapters-overlay">
    <a href="#" id="chapters-btn_close"><img src="/img/close.svg" alt="Fermer"/></a>
    <div id="Chapters">
        <?php
        $workId = getWorkIdByWorkName($_GET['work']);
        $versions = explode('-', substr(strrchr($path, '/'), 1));
        $folder = substr(strrchr($_GET['comparison'], '/'), 1);
        ?>
        <div class="container">
            <div class="row titleChapters">
                <div class="col-lg-6 chptrs_v-title">
                    <?php echo displayNameVersion($workId, $versions[0]); ?>
                </div>
                <div class="col-lg-6 chptrs_v-title">
                    <?php echo displayNameVersion($workId, $versions[1]); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6 chptrs_list">
                    <?php displayChapters($folder, 'source'); ?>
                </div>
                <div class="col-lg-6 chptrs_list">
                    <?php displayChapters($folder, 'target'); ?>
                </div>
            </div>
        </div>
        <?php
        ?>
    </div>
</div>

<script src="<?php echo DIR_REL ?>/app/js/imageviewer.min.js"></script>
<script src="https://cdn.jsdelivr.net/jquery.mcustomscrollbar/3.1.5/jquery.mCustomScrollbar.concat.min.js"></script>

<script type="text/javascript">
    $(window).on('load', function () {
        setTimeout(loadInterface, 1000);
        setTimeout(settingUp, 5000);

        if ($("#Chapters li").length === 0) {
            $("#chapters-btn").hide();
        }

        function loadInterface() {
            $('.loader').fadeOut('fast')
        }

        function settingUp() {
            $('#settings').removeClass('open');
            $('.settings_button').removeClass('active');
            $('#bookButton').addClass('book-highlight');
        }
    });


    $('.txt-container .page-marker').on('mouseover', function () {
        $(this).closest('a').addClass('is-hover');
    }).on('mouseout', function () {
        $(this).closest('a').removeClass('is-hover');
    });

    $(document).ready(function () {
        if ($("#content").find("[data-tags=\"o\"]").length === 0) {
            $('.button_variation').hide();
        }

        $('.mod_settings').click(function () {
            var currentSection = $(this).data('cat');

            $('#chapters-btn').hide();

            if (!$('#content').hasClass('open') && !$(this).hasClass('active')) {
                $('#content').addClass('open');
                $('#tools').addClass('open');
                $(this).addClass('active');
                $('#tools').find('.cat-item').hide(0, function () {
                    $('#tools').find("#" + currentSection).fadeIn(0);
                });
            } else if ($(this).hasClass('active')) {
                $('#tools').find('.cat-item').fadeOut();
                $('#content').removeClass('open');
                $('#tools').removeClass('open');
                $(this).removeClass('active');
                if ($("#Chapters li").length > 0) {
                    $("#chapters-btn").show();
                }
            } else {
                $('#tools').find('.cat-item').hide(0, function () {
                    $('#tools').find("#" + currentSection).fadeIn(0);
                });
                $('.mod_settings').removeClass('active');
                $(this).addClass('active');
            }
        });


        $('.settings_button').click(function () {
            var currentSetting = $(this).data('settings');
            if (!$('#settings').hasClass('open') && !$(this).hasClass('active')) {
                $('.js-settings').fadeOut(0, function () {
                    $("#" + currentSetting).fadeIn(0);
                });
                $('#settings').addClass('open');
                $(this).addClass('active');
            } else if ($(this).hasClass('active')) {
                $('#settings').removeClass('open');
                $(this).removeClass('active');
            } else {
                $('.settings_button').removeClass('active');
                $('.js-settings').fadeOut(0, function () {
                    $("#" + currentSetting).show();
                });
                $(this).addClass('active');

            }
        });

        $('.js-serif').click(function () {
            $('.interface').addClass('serif');
            $('.interface').removeClass('ss-serif');
            $(this).addClass('active');
            $('.js-ss-serif').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['font'] = 'serif';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-ss-serif').click(function () {
            $('.interface').addClass('ss-serif');
            $('.interface').removeClass('serif');
            $(this).addClass('active');
            $('.js-serif').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['font'] = 'ss-serif';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-dark-bkg').click(function () {
            $('.interface').addClass('dark-bkg');
            $('.interface').removeClass('light-bkg');
            $(this).addClass('active');
            $('.js-light-bkg').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['contrast'] = 'dark-bkg';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-light-bkg').click(function () {
            $('.interface').addClass('light-bkg');
            $('.interface').removeClass('dark-bkg');
            $(this).addClass('active');
            $('.js-dark-bkg').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['contrast'] = 'light-bkg';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-lineheight-big').click(function () {
            $('.interface').addClass('lineheight-big');
            $('.interface').removeClass('lineheight-small');
            $(this).addClass('active');
            $('.js-lineheight-small, .js-lineheight-medium').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['leading'] = 'lineheight-big';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-lineheight-small').click(function () {
            $('.interface').addClass('lineheight-small');
            $('.interface').removeClass('lineheight-big');
            $(this).addClass('active');
            $('.js-lineheight-big, .js-lineheight-medium').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['leading'] = 'lineheight-small';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-lineheight-medium').click(function () {
            $('.interface').removeClass('lineheight-small lineheight-big');
            $(this).addClass('active');
            $('.js-lineheight-big, .js-lineheight-small').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['leading'] = 'lineheight-medium';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-font-small').click(function () {
            $('.interface').addClass('font-small');
            $('.interface').removeClass('font-big font-medium');
            $(this).addClass('active');
            $('.js-font-big, .js-font-medium').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['size'] = 'font-small';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-font-medium').click(function () {
            $('.interface').removeClass('font-big font-small');
            $(this).addClass('active');
            $('.js-font-big, .js-font-small').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['size'] = 'font-medium';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });

        $('.js-font-big').click(function () {
            $('.interface').addClass('font-big');
            $('.interface').removeClass('font-small font-medium');
            $(this).addClass('active');
            $('.js-font-medium, .js-font-small').removeClass('active');
            var cookie = Cookies.getJSON('viewer_params');
            cookie['size'] = 'font-big';
            Cookies.set('viewer_params', cookie, {expires: 365});
        });


//        Changement de comportement... lorsque
//        $('#comparisonId').change(function(){
//            $('.btn_validate').addClass('change');
//        });

        var viewerA = ImageViewer('#js-workarea-left .paging-image__wrapper');
        var viewerB = ImageViewer('#js-workarea-right .paging-image__wrapper');

        <?php
        if (!empty($_GET['comparison'])) {

        $expldedPath = explode('/', $_GET['comparison']);

        $baseName = implode('--', $expldedPath);
        $chainedPaths = explode('-', array_pop($expldedPath));

        $jsonPath = implode('/', $expldedPath);



        ?>
        <?php
        $imagesSourcePath = UPLOAD_ROOT . '/' . $jsonPath . '/' . $chainedPaths[0] . '/' . 'images_source_' . $baseName . '.json';
        $imagesTargetPath = UPLOAD_ROOT . '/' . $jsonPath . '/' . $chainedPaths[1] . '/' . 'images_target_' . $baseName . '.json';

        $imagesSourceJson = '[]';
        if (is_file($imagesSourcePath)) {
            $content = @file_get_contents($imagesSourcePath);
            if ($content !== false && strlen(trim($content)) > 0) {
                $imagesSourceJson = $content;
            }
        }

        $imagesTargetJson = '[]';
        if (is_file($imagesTargetPath)) {
            $content = @file_get_contents($imagesTargetPath);
            if ($content !== false && strlen(trim($content)) > 0) {
                $imagesTargetJson = $content;
            }
        }
        ?>
        var imagesSource = <?php echo $imagesSourceJson; ?>;
        var imagesTarget = <?php echo $imagesTargetJson; ?>;

        var currentSourceIdx = 0;
        var currentTargetIdx = 0;

        function showImage(viewer, imageArray, imageIndex) {
            if (!Array.isArray(imageArray) || !imageArray.length) {
                return;
            }
            var idx = Number(imageIndex) || 1;
            if (idx < 1) { idx = 1; }
            if (idx > imageArray.length) { idx = imageArray.length; }
            var imgObj = imageArray[idx - 1];
            if (!imgObj) {
                return;
            }
            viewer.load(imgObj.small || imgObj.big, imgObj.big || imgObj.small || '');
        }
        <?php
        }
        ?>

        $('.page-marker').click(function (e) {
            var imageName = this.dataset.imageName;
            var $workarea = $(this).closest('.workarea');

            $('.paging-image', $workarea).slideDown(400, function () {
                if ($workarea.is('#js-workarea-left')) {

                    currentSourceIdx = imageName;
                    showImage(viewerA, imagesSource, imageName);
                } else {

                    currentTargetIdx = imageName;
                    showImage(viewerB, imagesTarget, imageName);
                }
            });
            return false;
        });

        $('.prev').click(function (e) {
            var $workarea = $(e.currentTarget).closest('.workarea');

            if ($workarea.is('#js-workarea-left')) {

                currentSourceIdx--;

                if (currentSourceIdx < 0) {
                    currentSourceIdx = 0;
                }
                showImage(viewerA, imagesSource, currentSourceIdx);
            } else {

                currentTargetIdx--;

                if (currentTargetIdx < 0) {
                    currentTargetIdx = 0;
                }
                showImage(viewerB, imagesTarget, currentTargetIdx);
            }

        });

        $('.next').click(function (e) {
            var $workarea = $(e.currentTarget).closest('.workarea');

            if ($workarea.is('#js-workarea-left')) {

                currentSourceIdx++;

                if (currentSourceIdx > imagesSource.length) {
                    currentSourceIdx = imagesSource.length;
                }
                showImage(viewerA, imagesSource, currentSourceIdx);
            } else {

                currentTargetIdx++;

                if (currentTargetIdx > imagesTarget.length) {
                    currentTargetIdx = imagesTarget.length;
                }
                showImage(viewerB, imagesTarget, currentTargetIdx);
            }

        });


        $('#js-workarea-left .page-marker').first().click();
        $('#js-workarea-right .page-marker').first().click();

        var params = {
            scrollInertia: 0
        };
        var commonsLeft = [];
        var commonsRight = [];

        $('.sync').each(function () {
            var $this = $(this);
            var id = $this.attr('id');
            var href = $this.attr('href');
            var corresp = $this.attr('corresp');
            var isSelfLink = !href || href.replace(/^#/, '') === id;

            if (corresp && isSelfLink) {
                $this.attr('href', '#' + corresp);
                return;
            }

            if (!$this.hasClass('span_c') || !isSelfLink || !id) {
                return;
            }

            if ($this.closest('#js-workarea-right').length) {
                commonsRight.push($this);
            } else if ($this.closest('#js-workarea-left').length) {
                commonsLeft.push($this);
            }
        });

        var pairCount = Math.min(commonsLeft.length, commonsRight.length);
        if (commonsLeft.length !== commonsRight.length) {
            console.warn('Nombre de segments communs différent entre les colonnes', commonsLeft.length, commonsRight.length);
        }

        for (var idx = 0; idx < pairCount; idx++) {
            var $left = commonsLeft[idx];
            var $right = commonsRight[idx];
            if (!$left || !$right) {
                continue;
            }

            var leftId = $left.attr('id');
            var rightId = $right.attr('id');

            if (leftId && (!$left.attr('href') || $left.attr('href').replace(/^#/, '') === leftId)) {
                $left.attr('href', '#' + rightId);
            }

            if (rightId && (!$right.attr('href') || $right.attr('href').replace(/^#/, '') === rightId)) {
                $right.attr('href', '#' + leftId);
            }
        }

        ensureGhostStyle();
        ensureReplacementCrossLinks();

        var ghostState = {
            '#js-workarea-left': {last: null},
            '#js-workarea-right': {last: null}
        };

        $('.sync').click(function (e) {
            var $link = $(this);
            var originalTarget = $link.attr('href');
            var targetSelectors = normalizeTargetSelectors(originalTarget);
            var primaryTarget = targetSelectors.length ? targetSelectors[0] : null;

            if ($link.hasClass('sync-twice')) {
                var baseId = $link.attr('id').substr(2);
                var $paired = $('#b' + baseId);
                scrollContainerToElement($paired.closest('.wrkarea'), $paired);
                $("#b" + baseId).addClass("highlight-text").delay(4000).queue(function () {
                    $(this).removeClass("highlight-text").dequeue();
                });
                primaryTarget = '#a' + baseId;
                targetSelectors = [primaryTarget];
            } else if ($link.closest('.wrkarea').length > 0) {
                var linkId = $link.attr('id');
                var $linkElement = $('#' + linkId);
                var $currentParent = $link.closest('.wrkarea');
                scrollContainerToElement($currentParent, $linkElement);
                if (primaryTarget) {
                    var $primaryTargetElement = $(primaryTarget);
                    if ($primaryTargetElement.length) {
                        scrollContainerToElement($primaryTargetElement.closest('.wrkarea'), $primaryTargetElement);
                    }
                }
                setTimeout(function (id) {
                    var $el = $('#' + id);
                    if ($el.length) {
                        $el.addClass("highlight-text").delay(4000).queue(function () {
                            $(this).removeClass("highlight-text").dequeue();
                        });
                    }
                }, 800, linkId);
            }

            processTargets(targetSelectors);
            return false;
            e.stopPropagation();
        });

        $('.span_i, .span_s').click(function () {

            $(this).addClass("highlight-text").delay(4000).queue(function () {
                $(this).removeClass("highlight-text").dequeue();
            });
        })

        function ensureGhostStyle() {
            if ($('#ghost-anchor-style').length) {
                return;
            }
            var css = '.ghost-anchor{display:inline-block;width:0;height:0;margin:0;padding:0;border:0;pointer-events:none;}';
            $('<style>', {id: 'ghost-anchor-style', text: css}).appendTo('head');
        }

        function ensureReplacementCrossLinks() {
            $('#replace .sync').each(function () {
                var selectors = normalizeTargetSelectors($(this).attr('href'));
                if (selectors.length !== 2) {
                    return;
                }

                var $first = $(selectors[0]);
                var $second = $(selectors[1]);

                if ($first.length && $second.length) {
                    $first.attr('href', selectors[1]);
                    $second.attr('href', selectors[0]);
                }
            });
        }

        function findReferenceInOtherColumn($element, otherSideSelector) {
            var $previous = $element.prevAll('.sync').filter(function () {
                return getCounterpartInOtherColumn($(this), otherSideSelector).length > 0;
            }).first();

            if ($previous.length) {
                var $previousCounterpart = getCounterpartInOtherColumn($previous, otherSideSelector);
                if ($previousCounterpart.length) {
                    return {element: $previousCounterpart, placement: 'after'};
                }
            }

            var $next = $element.nextAll('.sync').filter(function () {
                return getCounterpartInOtherColumn($(this), otherSideSelector).length > 0;
            }).first();

            if ($next.length) {
                var $nextCounterpart = getCounterpartInOtherColumn($next, otherSideSelector);
                if ($nextCounterpart.length) {
                    return {element: $nextCounterpart, placement: 'before'};
                }
            }

            return null;
        }

        function getCounterpartInOtherColumn($candidate, otherSideSelector) {
            if (!$candidate || !$candidate.length) {
                return $();
            }

            var ghostCounterpartId = $candidate.attr('data-ghost-counterpart');
            if (ghostCounterpartId) {
                var $ghost = $('#' + ghostCounterpartId);
                if ($ghost.length && $ghost.closest(otherSideSelector).length) {
                    return $ghost;
                }
            }

            var corresp = $candidate.attr('corresp');
            if (corresp) {
                var $corresp = $('#' + corresp);
                if ($corresp.length && $corresp.closest(otherSideSelector).length) {
                    return $corresp;
                }
            }

            var href = $candidate.attr('href');
            if (href && href.charAt(0) === '#') {
                var $target = $(href);
                if ($target.length && $target.closest(otherSideSelector).length) {
                    return $target;
                }
            }

            var candidateId = $candidate.attr('id');
            if (candidateId) {
                var altId = null;
                if (candidateId.charAt(0) === 'a') {
                    altId = '#b' + candidateId.substr(1);
                } else if (candidateId.charAt(0) === 'b') {
                    altId = '#a' + candidateId.substr(1);
                }
                if (altId) {
                    var $alt = $(altId);
                    if ($alt.length && $alt.closest(otherSideSelector).length) {
                        return $alt;
                    }
                }
            }

            return $();
        }

        function scrollContainerToElement($container, $element, linesAbove) {
            if (!$container || !$container.length || !$element || !$element.length) {
                return;
            }
            if (typeof linesAbove !== 'number' || linesAbove <= 0) {
                linesAbove = 1;
            }
            var lineHeight = parseFloat($element.css('line-height'));
            if (isNaN(lineHeight) || !lineHeight) {
                var fontSize = parseFloat($element.css('font-size'));
                lineHeight = fontSize ? fontSize * 1.2 : 20;
            }

            var targetScroll = $container.scrollTop() - $container.offset().top + $element.offset().top - (lineHeight * linesAbove);
            if (targetScroll < 0) {
                targetScroll = 0;
            }
            $container.scrollTop(targetScroll);
        }

        function alignPartnerColumn($element) {
            if (!$element || !$element.length) {
                return;
            }

            var targetId = $element.attr('id');
            var ghostId = $element.attr('data-ghost-counterpart');
            var $ghost = ghostId ? $('#' + ghostId) : $();

            if (!$ghost.length) {
                $ghost = ensureGhostForElement($element);
            }

            if (!$ghost.length && targetId) {
                $ghost = $('.ghost-anchor[data-ghost-for="' + targetId + '"]').first();
            }

            if ($ghost.length) {
                var linesAbove = 1;
                if ($element.hasClass('span_s') || $element.hasClass('span_i')) {
                    linesAbove = 2;
                }
                scrollContainerToElement($ghost.closest('.wrkarea'), $ghost, linesAbove);
            }
        }

        function processTargets(selectors) {
            if (!selectors || !selectors.length) {
                return;
            }

            selectors.forEach(function (selector) {
                setTimeout(function (targetSelector) {
                    if (!targetSelector || targetSelector.charAt(0) !== '#') {
                        return;
                    }
                    var $targetElement = $(targetSelector);
                    if (!$targetElement.length) {
                        return;
                    }
                    scrollContainerToElement($targetElement.closest('.wrkarea'), $targetElement);
                    alignPartnerColumn($targetElement);

                    setTimeout(function (selectorToHighlight) {
                        var $el = $(selectorToHighlight);
                        if ($el.length) {
                            $el.addClass("highlight-text").delay(4000).queue(function () {
                                $(this).removeClass("highlight-text").dequeue();
                            });
                        }
                    }, 800, targetSelector);
                }, 800, selector);
            });
        }

        function normalizeTargetSelectors(raw) {
            if (!raw) {
                return [];
            }

            if (Array.isArray(raw)) {
                return raw.slice();
            }

            return raw.split(',').map(function (item) {
                var cleaned = item.trim();
                if (!cleaned) {
                    return null;
                }
                cleaned = cleaned.replace(/^#+/, '');
                return '#' + cleaned;
            }).filter(function (item) {
                return !!item && item !== '#';
            });
        }

        function ensureGhostForElement($element) {
            var elementId = $element.attr('id');
            if (!elementId) {
                return $();
            }

            var otherSideSelector = getOtherSideSelector($element);
            if (!otherSideSelector) {
                return $();
            }

            var ghostId = $element.attr('data-ghost-counterpart');
            if (ghostId) {
                var $existingGhost = $('#' + ghostId);
                if ($existingGhost.length) {
                    return $existingGhost;
                }
            }

            var $already = $('.ghost-anchor[data-ghost-for="' + elementId + '"]').first();
            if ($already.length) {
                $element.attr('data-ghost-counterpart', $already.attr('id'));
                ghostState[otherSideSelector].last = $already;
                return $already;
            }

            var reference = findReferenceInOtherColumn($element, otherSideSelector);
            var generatedId = 'ghost-' + elementId;
            var $ghost = $('<span/>', {
                id: generatedId,
                'class': 'ghost-anchor',
                'data-ghost-for': elementId,
                'aria-hidden': 'true'
            });

            if (reference && reference.element.length) {
                if (reference.placement === 'after') {
                    reference.element.after($ghost);
                } else {
                    reference.element.before($ghost);
                }
            } else {
                var state = ghostState[otherSideSelector];
                if (state && state.last && state.last.closest(otherSideSelector).length) {
                    state.last.after($ghost);
                } else {
                    var $container = $(otherSideSelector + ' .txt-container');
                    if ($container.length) {
                        $container.append($ghost);
                    }
                }
            }

            $element.attr('data-ghost-counterpart', generatedId);
            ghostState[otherSideSelector].last = $ghost;
            return $ghost;
        }

        function getOtherSideSelector($element) {
            if ($element.closest('#js-workarea-left').length) {
                return '#js-workarea-right';
            }
            if ($element.closest('#js-workarea-right').length) {
                return '#js-workarea-left';
            }
            return null;
        }

        $('.closer').click(function (e) {
            e.preventDefault();
            $(e.currentTarget).parent().slideUp();
        });

        //$('.workarea--left__container, .workarea--right__container').mCustomScrollbar({scrollbarPosition: 'outside'});
        $("#bookButton").click();

        var zoomedSource = false;
        var zoomedTarget = false;
        $(".zoom").click(function (e) {
            e.preventDefault();

            var $workarea = $(this).closest('.workarea');

            if ($workarea.is('#js-workarea-left')) {

                if (zoomedSource) {
                    viewerA.resetZoom();
                } else {
                    viewerA.zoom(200);
                }
                zoomedSource = !zoomedSource;
            } else {
                if (zoomedTarget) {
                    viewerB.resetZoom();
                } else {
                    viewerB.zoom(200);
                }

                zoomedTarget = !zoomedTarget;
            }

            return false;
        });

//        $(".btn_validate").click(function() {
//            $(this).closest('.js-settings').css('display', 'none');
//            $(this).closest('section').removeClass('open');
//            var $id = $(this).closest('.js-settings').prop('id');
//
//            $('[data-settings="'+$id+'"]').removeClass('active');
//
//            if($(this).data('type') == 'comparison') {
//                var $selectValue = $('#comparisonId').val();
//                if ($selectValue.length > 1) {
//                    var currentLocation = window.location.href;
//                    var lastIndex = currentLocation.lastIndexOf('&comparison');
//                    var newLocation = currentLocation.substr(0, lastIndex) + '&comparison=' + $selectValue;
//                    window.location.href = newLocation;
//                }
//            }
//        })

        $(".btn_validate").click(function () {
            $(this).closest('section').removeClass('open');
            var $id = $(this).closest('.js-settings').prop('id');
            $(this).closest('.js-settings').fadeOut('fast');

            $('[data-settings="' + $id + '"]').removeClass('active');
        });


        $('#comparisonId').change(function () {
            var $selectValue = $(this).val();
            if ($selectValue.length > 1) {
                var currentLocation = window.location.href;
                window.location.href = $selectValue;
            }
        })

        $('#chapters-btn').click(function () {
            $('#chapters-overlay').fadeIn();
        })

        $('#chapters-btn_close').click(function () {
            $('#chapters-overlay').fadeOut();
        })
    });

    function goToPageNumber(pageLeftNumber, pageRightNumber, idTomeSource, idTomeTarget) {
        var leftObj = $("#js-workarea-left span.page-number").filter(function () {
            return $(this).text() === pageLeftNumber;
        });

        if ($(leftObj[idTomeSource]) && $(leftObj[idTomeSource]).length == 1) {

            $('.workarea--left__container.wrkarea').scrollTop(0);
            $('.workarea--left__container.wrkarea').animate({
                scrollTop: $(leftObj[idTomeSource]).offset().top - ($('.workarea--left__container.wrkarea').offset().top + 50)
            }, 'fast');

            $(leftObj[idTomeSource]).click();
        }

        var rightObj = $("#js-workarea-right span.page-number").filter(function () {
            return $(this).text() === pageRightNumber;
        });

        if ($(rightObj[idTomeTarget]) && $(rightObj[idTomeTarget]).length == 1) {

            $('.workarea--right__container.wrkarea').scrollTop(0);
            $('.workarea--right__container.wrkarea').animate({
                scrollTop: $(rightObj[idTomeTarget]).offset().top - ($('.workarea--right__container.wrkarea').offset().top + 50)
            }, 'fast');

            $(rightObj[idTomeTarget]).click();
        }

        $('#chapters-overlay').fadeOut();
    }

    variationActive = false;
    currentVariations = [];
    function displayVariation(variation) {
        var previousVariations = currentVariations.slice(0) //Clone array

        var index = currentVariations.indexOf(variation)
        if (index === -1) {
            currentVariations.push(variation)
        }

        if (index > -1) {
            currentVariations.splice(index, 1)
        }

        currentVariations.sort()

        if (previousVariations === currentVariations) {
            currentVariations = []
        }
        $("#content").find("[data-tags]").removeClass('inactiveVariation');
        $("#tools").find("[data-tags]").removeClass('inactiveVariation');
        $(".button_variation").removeClass('strike_button');

        if (currentVariations.length > 0) {
            currentVariations.forEach(function (itemVariation) {
                $("#content").find("[data-tags='" + itemVariation + "']").addClass('inactiveVariation');
                $("#tools").find("[data-tags='" + itemVariation + "']").addClass('inactiveVariation');
                $(".button_variation_" + itemVariation).addClass('strike_button');
            });

            $("#content").find("[data-tags='" + currentVariations.join(', ') + "']").addClass('inactiveVariation');
            $("#tools").find("[data-tags='" + currentVariations.join(', ') + "']").addClass('inactiveVariation');
        }
    }

</script>

<style>
    #General-Wrapper #tools .inactiveVariation {
        color:#aba8a8
    }
</style>


</body>
</html>
