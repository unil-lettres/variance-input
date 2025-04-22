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
ini_set('display_errors', -1);
error_reporting(E_ALL);
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
        <!-- Partie "Catalogue" avec pagination -->
        <div class="content-cover container">
            <div class="col-lg-10 col-lg-offset-1 col-md-12 catalogue">
                <div class="row catalogue__title">
                    <h2 class="col-sm-9">
                        Catalogue
                    </h2>
					<?php
					$perPage = 4;
					$previous = array('a_id' => 0, 'a_name' => 0, 'a_folder' => 0, 'w_id' => 0, 'w_title' => 0, 'w_folder' => 0, 'w_desc' => 0);
					$authorFiltered = isset($_GET['author']) && trim($_GET['author']) != '' ? " AND a.folder='".htmlspecialchars($_GET['author'])."'" : '';
          $oeuvreFiltered = isset($_GET['oeuvre']) && trim($_GET['oeuvre']) != '' ? " AND w.folder='".htmlspecialchars($_GET['oeuvre'])."'" : '';
          $query = 'SELECT SQL_CALC_FOUND_ROWS a.id as a_id, a.name as a_name, a.folder as a_folder, w.id as w_id, w.image_url as w_image, w.title as w_title, w.folder as w_folder, w.desc as w_desc FROM `authors` a INNER JOIN works w ON w.author_id = a.id WHERE 1=1 '.$authorFiltered.' '.$oeuvreFiltered.' ORDER BY a_name ASC, w_title ASC LIMIT :limit OFFSET :offset';
					$page = (!empty($_GET['page']) ? intval($_GET['page']) : 1);
					$offset = ($page -1) * $perPage;

					$queryStatement = $cnx->prepare($query);
					$queryStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
					$queryStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
					$queryStatement->execute();
					$total = $cnx->query('SELECT found_rows()')->fetchColumn();
					$nbPages = ceil($total / $perPage);
					if ($nbPages > 1):?>
                        <div class="catalogue__pagination pull-right">
                            <span class="smaller-1">pages</span>
							<?php for ($i = 1; $i <= $nbPages; $i++): ?>
                                <a class="item <?php echo (($page == $i) ? 'active': '') ?>" href="?page=<?php echo $i; ?>"><?php echo $i ?></a>
							<?php endfor; ?>
                        </div>
					<?php endif; ?>
                </div><!--END ROW TITLE + PAGINATION-->
				<?php while ($element = $queryStatement->fetch(PDO::FETCH_ASSOC)): ?>
                    <div class="catalogue__item row">
						<?php if ($element['a_id'] != $previous['a_id']):
							$authorName = $element['a_name'];
							$authorFirstNameArr = explode(' ', $authorName, 2);
							$autorFirstName = array_shift($authorFirstNameArr);
							$autorLastName = implode($authorFirstNameArr);
							?>
                            <h3 class="catalogue__author"><span><?php echo $autorFirstName ?></span> <?php echo $autorLastName?></h3>
                            <hr>
						<?php endif; ?>
						<?php if ($element['w_id'] != $previous['w_id']): ?>
                            <div class="img col-sm-4">
                                <img class="img-responsive" src="<?= DIR_REL ?>/uploads_images/<?php echo $element['w_image'] ?>" alt="<?php echo $element['w_image'] ?>" />
                            </div>
						<?php endif; ?>



						<?php if ($element['w_id'] != $previous['w_id']): ?>
                            <div class="col-sm-8">
                                <h4><?php echo $element['w_title']; ?></h4>
                                <div>
									<?php
									$desc = $element['w_desc'];
                                    echo '<p>'. $desc .'</p>';

									?>
                                </div>
								<?php $query = 'SELECT c.number as c_number, c.folder AS c_folder, s.name as s_name, t.name AS t_name FROM comparisons c INNER JOIN versions s ON c.source_id = s.id INNER JOIN versions t ON c.target_id = t.id WHERE s.work_id = :id ORDER BY c.number ASC';
								$comparisonStatement = $cnx->prepare($query);
								$comparisonStatement->bindValue(':id', $element['w_id'], PDO::PARAM_INT);
								$comparisonStatement->execute();
								$version = $comparisonStatement->fetch();
								if ($version): ?>
                                    <p><strong>Comparaisons</strong></p>
                                    <ul class="catalogue-versions">
                                        <li><a title="cliquez pour comparer" href="<?php
											ob_start(); echo getOeuvreUrl($element['a_folder'], $element['w_folder'], $version['c_folder']); $url = ob_get_clean(); echo $url; ?>"><?php echo '<span>'. $version['c_number'].'. '.$version['s_name'] . ' </span><span class="arrow-versions">&rarr;</span> ' . $version['t_name']; ?></a></li><?php
										while ($version = $comparisonStatement->fetch()):
											?>
                                            <li><a href="<?php echo getOeuvreUrl($element['a_folder'], $element['w_folder'], $version['c_folder']); ?>"><?php echo '<span>'. $version['c_number'].'. '.$version['s_name'] . ' </span><span class="arrow-versions">&rarr;</span> ' . $version['t_name']; ?></a></li><?php
										endwhile; ?>
                                    </ul>

								<?php endif;?>
                                <div class="dia_btn align-right primary small">
                                    <a href="<?php echo $url; ?>">Comparer</a>
                                </div>
                            </div>
						<?php endif; ?>
                    </div>
					<?php
					$previous = $element;
				endwhile;
				if ($nbPages > 1):?>
                    <div class="catalogue__pagination pull-right">
                        <span class="smaller-1">pages</span>
						<?php for ($i = 1; $i <= $nbPages; $i++): ?>
                            <a class="item <?php echo (($page == $i) ? 'active': '') ?>" href="?page=<?php echo $i; ?>"><?php echo $i ?></a>
						<?php endfor; ?>
                    </div>
				<?php endif; ?>
            </div>
        </div>
        <!-- Fin de la partie "Catalogue" -->
    </main>

	<?php include_once('partials/cover/footer_content.php') ?>

</div>
<!-- END GENERAL-WRAPPER -->
<div class="breakpoints"></div>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="<?php echo DIR_REL ?>/dist/js/main.min.js"></script>

</body>
</html>