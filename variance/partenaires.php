<?php require_once 'php/settings.inc.php';
include_once('partials/cover/header.php') ?>
<div id="General-Wrapper">

	<header class="header-cover">
		<nav class="container">
			<a class="pull-left col-sm-3" href="<?php echo DIR_REL ?>/"><img class="logo" src="<?php echo DIR_REL ?>/img/full_logo_white.svg" alt="Logo Variance"></a>
			<?php include_once('partials/cover/navigation.php') ?>
		</nav>
	</header>

	<main>
		<div class="content-cover container">
			<div class="col-lg-10 col-lg-offset-1 col-md-12 partners">
				<div class="row">
					<h1>
						<?php
						if(isset($pagesInfos[$currentPage])){
							echo $pagesInfos[$currentPage][0];
						} ?>
					</h1>
				</div>

                <div class="row">
                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-4">
                            <img src="<?= DIR_REL ?>/img/partenaires/unil_final.jpg" alt="UNIL"  class="img-responsive" />

                        </div>
                        <div class="right-part col-sm-offset-1 col-sm-7">
                            <h5>UNIL</h5>
                            <p>
                                Dans le cadre de sa politique de développement des humanités numériques, la Faculté des Lettres soutient activement le projet Variance depuis son origine.
                            </p>
                            <div class="dia_btn align-right primary small">
                                <a href="https://www.unil.ch/lettres/fr/home.html" target="_blank">Visiter le site</a>
                            </div>
                        </div>
                    </div>

                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-4">
                            <img src="<?= DIR_REL ?>/img/partenaires/slatkine_final.jpg" alt="Logo des éditions Slatkine : une licorne altière de dextre en senestre barrée d'un S" class="img-responsive" title="Editions Slatkine" />
                        </div>
                        <div class="right-part col-sm-offset-1 col-sm-7">
                            <h5>Editions Slatkine</h5>
                            <p>
                                5, rue des Chaudronniers, Case postale 3625, CH - 1211 Genève 3
                            </p>
                            <div class="dia_btn align-right primary small">
                                <a href="https://www.slatkine.com/fr/nouveautes/editions-slatkine" target="_blank">Visiter le site</a>
                            </div>
                        </div>
                    </div>

                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-4">
                            <img src="<?= DIR_REL ?>/img/partenaires/diabolo_final.jpg" alt="Logo de Diabolo Design" class="img-responsive" title="Diabolo Design" />
                        </div>
                        <div class="right-part col-sm-offset-1 col-sm-7">
                            <h5>Diabolo Design SA</h5>
                            <p>
                                Rte de la Crottaz 50, CH - 1802 Corseaux
                            </p>
                            <div class="dia_btn align-right primary small">
                                <a href="http://www.diabolo.com" target="_blank">Visiter le site</a>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-4">
                            <img src="<?= DIR_REL ?>/img/partenaires/BIS2_final.jpg" alt="Logo de l'Université de Lausane : abbrévition UNIL manuscrite" class="img-responsive" title="Université de Lausanne" />
                        </div>
                        <div class="right-part col-sm-offset-1 col-sm-7">
                            <h5>Bibliothèque interuniversitaire de la Sorbonne</h5>
                            <p>
                                17, rue de la Sorbonne, F – 75005 Paris
                            </p>
                            <div class="dia_btn align-right primary small">
                                <a href="http://www.bibliotheque.sorbonne.fr/biu/" target="_blank">Visiter le site</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="clearfix">
                    <h4>Collaborateurs</h4>
                    <p>
                        Plusieurs personnes ont œuvré, avec compétence et cordialité, à l’élaboration de <em>Variance</em>. Qu’elles soient ici vivement remerciées :
                    </p>
                    <p>
                        <strong>Alexandre Hurzeler</strong>, architecte-graphiste<br/>
                        <strong>Gaël Luisier</strong>, développeur<br/>
                        <strong>Lucio Merotta</strong>, développeur<br/>
                        <strong>Yannick Saraillon</strong>, original interface concept<br/>
                        <strong>William Christen</strong>, UI/UX designer, frontend développeur<br/>
                        <strong>Laurent Mauron</strong>, développeur<br/>
                    </p>
                </div>

                <div class="clearfix">
                    <h4>Mécènes</h4>
                    <p>
                        Différentes institutions ont soutenu financièrement le développement de la plateforme <em>Variance</em>. Les directeurs scientifiques de la collection leur expriment leur sincère gratitude.
                    </p>
                    <p>
                        <strong>La Fondation Me J.-J. van Walsem pro Universitate</strong><br/>
                        Rue de la Barre 8<br/>
                        CH - 1005 Lausanne<br/>
                    </p>
                    <p>
                        <strong>La Société Académique Vaudoise</strong><br/>
                        <a href="www.s-a-v.org" target="_blank">www.s-a-v.org</a><br/>
                        1, avenue de Montbenon<br/>
                        1002 Lausanne<br/>
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
