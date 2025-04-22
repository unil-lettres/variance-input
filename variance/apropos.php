<?php require_once 'php/settings.inc.php'; ?>
<?php include_once('partials/cover/header.php') ?>
<div id="General-Wrapper">

	<header class="header-cover">
		<nav class="container">
			<a class="pull-left col-sm-3" href="<?php echo DIR_REL ?>/"><img class="logo" src="<?php echo DIR_REL ?>/img/full_logo_white.svg" alt="Logo Variance"></a>
			<?php include_once('partials/cover/navigation.php') ?>
		</nav>
	</header>

	<main>
		<div class="content-cover container tutoriel">
			<div class="col-lg-10 col-lg-offset-1 col-md-12">
				<div class="row">
					<h1>
						<?php
						if(isset($pagesInfos[$currentPage])){
							echo $pagesInfos[$currentPage][0];
						} ?>
					</h1>
                    <p>
                    <strong>Variance offre une alternative fonctionnelle aux « relevés de variantes » et autres difficultés soulevées par l’édition papier des œuvres à versions multiples.
                        La collection propose :</strong>
                    </p>
                    <ul>
                        <li>la lecture de chacune des éditions autorisées d’une œuvre dans sa singularité</li>
                        <li>des reproductions en mode image de toutes les éditions comparées</li>
                        <li>la comparaison des éditions ayant servi de base à la réécriture avec celles qui en résultent</li>
                        <li>un inventaire automatique de toutes les différences entre versions</li>
                        <li>et une notice décrivant succinctement la genèse manuscrite de l’œuvre, sa phase pré-éditoriale (d’élaboration de l’édition originale) et son évolution post-éditoriale (soit les modifications de l’œuvre d’édition en édition)</li>
                    </ul>
                    <p>
                    Parallèlement, les éditions Slatkine ouvrent une collection (papier) intitulée « Variance critique » : elle accueille des monographies, individuelles ou collectives, consacrées aux œuvres en variation éditées dans la collection numérique.
                    Les conditions de possibilité d’une génétique post-éditoriale se trouvent ainsi réunies, ouvrant la voie à l’étude systématique de la réécriture des œuvres après leur première publication (ex. passage de la revue au livre, du poème isolé au recueil, de l’article à l’essai, de l’édition originale à l’édition révisée, du récit à sa réécriture pour un jeune public, etc.).
                    Pour plus de détails sur la genèse post-éditoriale et des exemples d’œuvres du canon, voir : <a href="https://genesis.revues.org/1579" target="_blank">https://genesis.revues.org/1579</a>
                    </p>
                    <p>
                    Tout éditeur intéressé est invité à soumettre une proposition de publication à l’adresse <a href="mailto:variance@unil.ch">variance@unil.ch</a>. Il recevra un cahier des charges stipulant les documents à remettre pour l’édition numérique comparative.
                    </p>
                </div>

			</div>
		</div>
	</main>

	<?php include_once('partials/cover/footer_content.php') ?>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="<?php echo DIR_REL ?>/dist/js/main.min.js"></script>

</body>
</html>