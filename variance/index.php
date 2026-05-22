<?php
/*
 * Projet        : Variance
 * Fichier       : index.php
 * Auteur        : GLR
 * Copyright	 : 2016 (c)
 * Date          : 12 janv. 2016
 *
 * Description   : Fichier qui permet de choisir la comparaison à afficher,
 *                 d'après l'auteur et l'œuvre
 *
 * Remarques     :
 * Modifications :
 */

require_once 'php/settings.inc.php';
?>


<?php include_once('partials/cover/header.php') ?>
<div id="General-Wrapper">
    <div class="modal-read-more-wrapper">
        <div class="modal-read-more">
            <div class="modal-read-more-inner">
                <a href="#" class="close-modal"><i class="fa fa-times" aria-hidden="true"></i></a>
                <div class="modal-read-more-content">
                    <p>
                        Variance offre une alternative fonctionnelle aux difficultés soulevées par l’édition papier des œuvres à versions multiples (tels que les « relevés de variantes »).<br/>
                        La collection propose :
                    </p>
                    <ul>
                        <li>la lecture de chacune des éditions autorisées d’une œuvre dans sa singularité</li>
                        <li>des reproductions en mode image de toutes les éditions comparées</li>
                        <li>la comparaison des éditions ayant servi de base à la réécriture avec celles qui en résultent</li>
                        <li>un inventaire automatique de toutes les différences entre versions</li>
                        <li>et une notice décrivant succinctement la genèse manuscrite de l’œuvre, sa phase pré-éditoriale (d’élaboration de l’édition originale) et son évolution post-éditoriale (soit les modifications de l’œuvre d’édition en édition)</li>
                    </ul>
                    <p>
                        C’est ainsi à la constitution collaborative d’une bibliothèque que vise la plateforme : les œuvres nouvelles sont introduites selon les propositions de chercheurs qualifiés (et après acceptation du comité scientifique) ; les utilisateurs peuvent suggérer au besoin des corrections des textes édités ; des outils pour l’annotation ou la fouille de textes seront développés au gré des besoins et des possibilités des éditeurs. La pérennité de la bibliothèque est assurée par l’Université de Lausanne qui en assure l’hébergement.
                    </p>

                    <p>
                        Parallèlement, les éditions Slatkine ouvrent une collection (papier) intitulée « Variance critique » : elle accueille des monographies, individuelles ou collectives, consacrées aux œuvres en variation éditées dans la collection numérique.<br/>
                        Les conditions de possibilité d’une génétique post-éditoriale se trouvent ainsi réunies, ouvrant la voie à l’étude systématique de la réécriture des œuvres après leur première publication (ex. passage de la revue au livre, du poème isolé au recueil, de l’article à l’essai, de l’édition originale à l’édition révisée, du récit à sa réécriture pour un jeune public, etc.).<br/>
                        Pour plus de détails sur la genèse post-éditoriale et des exemples d’œuvres du canon, voir : <a href="https://genesis.revues.org/1579" target="_blank">https://genesis.revues.org/1579</a>
                    </p>

                    <p>
                        Tout éditeur intéressé est invité à soumettre une proposition de publication à l’adresse <a href="&#x6d;&#97;&#x69;&#x6c;&#x74;&#x6f;&#58;&#118;&#97;&#x72;&#x69;&#x61;&#x6e;&#99;&#101;&#64;&#x75;&#x6e;&#105;&#108;&#x2e;&#x63;&#x68;">variance@unil.ch</a>. Il recevra un cahier des charges stipulant les documents à remettre pour l’édition numérique comparative.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <header class="header-cover">
        <nav class="container">
			<?php include_once('partials/cover/navigation.php') ?>
        </nav>

        <div class="header-cover__content container">
            <div class="col-lg-5 col-lg-offset-1 col-sm-6 col-sm-offset-0 txt">
                <h1><img class="logo" src="<?php echo DIR_REL ?>/img/full_logo_white.svg" alt="Logo Variance"></h1>
                <p>Variance met à la disposition des éditeurs, philologues ou généticiens, une plateforme simple, efficace, fiable et gratuite, pour la publication des œuvres ayant circulé en plusieurs versions.</p>
                <p>L’objectif de la collection est d’offrir un support qui permette de réunir toutes les versions d’une même œuvre, moderne ou ancienne, et d’assister l’interprétation des transformations, d’une édition à l’autre.</p>
                <div class="dia_btn primary align-right read-more">
                    <a href="/apropos.php">En savoir plus !</a>
                </div>
            </div>
            <div class="visuel">
                <img src="<?php echo DIR_REL ?>/img/cover_site/main_visuel.png">
            </div>
        </div>
    </header>

    <main>
        <div class="content-cover container">
            <div class="col-lg-10 col-lg-offset-1 col-md-12 catalogue">
                <?php
                $publishedWorkIds = array_map('intval', getWorkIdsWithPublishedComparisons());
                $comparisonQuery = 'SELECT c.number as c_number, c.prefix_label as c_prefix_label, c.folder AS c_folder, s.name as s_name, t.name AS t_name FROM comparisons c INNER JOIN versions s ON c.source_id = s.id INNER JOIN versions t ON c.target_id = t.id WHERE s.work_id = :id ORDER BY c.number ASC';
                $comparisonFilter = static function (array $comparison, array $element): bool {
                    return comparisonIsPublished($element['a_folder'], $element['w_folder'], $comparison['c_folder']);
                };
                $comparisonUrlBuilder = static function (array $comparison, array $element): string {
                    return getOeuvreUrl($element['a_folder'], $element['w_folder'], $comparison['c_folder']);
                };

                $catalogSectionTitle = 'Catalogue';
                $catalogWorkIds = $publishedWorkIds;
                $catalogGroup = 'main';
                $catalogPageParam = 'page';
                $catalogPaginate = true;
                $catalogHideWhenEmpty = false;
                $catalogPerPage = 40;
                $catalogEmptyMessage = 'Aucune comparaison publiée pour le moment.';
                $catalogComparisonQuery = $comparisonQuery;
                $catalogComparisonFilter = $comparisonFilter;
                $catalogComparisonUrlBuilder = $comparisonUrlBuilder;
                include __DIR__ . '/partials/cover/catalog_section.php';

                $catalogSectionTitle = 'Réécritures allographiques';
                $catalogWorkIds = $publishedWorkIds;
                $catalogGroup = 'allographic';
                $catalogPageParam = 'allographic_page';
                $catalogPaginate = false;
                $catalogHideWhenEmpty = true;
                $catalogPerPage = 40;
                $catalogEmptyMessage = 'Aucune comparaison publiée pour le moment.';
                include __DIR__ . '/partials/cover/catalog_section.php';
                ?>
            </div>
        </div>
    </main>

	<?php include_once('partials/cover/footer_content.php') ?>

</div>
<!-- END GENERAL-WRAPPER -->
<div class="breakpoints"></div>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="<?php echo DIR_REL ?>/dist/js/main.min.js"></script>

</body>
</html>
