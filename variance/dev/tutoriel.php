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
                    <div class="clearfix">
                        <p><em>Variance</em> consiste d’abord en un comparateur de textes*, appliqué ici aux différentes versions publiées d’une même œuvre. Une double fenêtre donne accès à deux versions : à gauche, le texte identifié comme base de la réécriture, à droite, le texte qui en résulte.</p>
                    </div>
                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-7">
                            <p>
                                Dans le corps des textes, comparés deux à deux, certains segments sont colorés : il s’agit des séquences réécrites. Les couleurs sont fonction du type de variation:
                            </p>
                            <ul>
                                <li><span class="span_s">suppression</span>: une séquence du premier texte n’apparaît pas dans le second texte</li>
                                <li><span class="span_i">insertion</span>: une séquence du second texte n’apparaît pas dans le premier texte</li>
                                <li><span class="span_r">remplacement</span>: une séquence du premier texte est transformée en une séquence différente du second texte</li>
                                <li><span class="span_d">déplacement</span>: une même séquence (d’au moins 7 caractères) apparaît à des endroits différents des deux textes</li>
                            </ul>
                            <p><em>Variance</em> permet ainsi de lire, verticalement, chaque version dans sa continuité, et de suivre, horizontalement, les transformations de l’œuvre au fil de ses rééditions.</p>

                        </div>
                        <div class="left-part col-sm-5">
                            <img src="img/tutoriel/comparaison_couleurs.jpg" />
                        </div>
                    </div>

                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-7">
                            <h4>Mode de circulation</h4>
                            <p>L’interface offre deux modes de circulation entre les versions comparées :</p>
                            <ol>
                                <li>Les passages non colorés sont identifiés comme séquences communes aux deux textes. Au clic, les deux versions s’alignent à l’endroit du passage commun.</li>
                                <li>Les variations sont regroupées en inventaires, dans la marge noire, à gauche. La sélection de l’une des variations inventoriées et numérotées provoque l’alignement des textes à l’endroit concerné.</li>
                            </ol>
                        </div>
                        <div class="left-part col-sm-5">
                            <img src="img/tutoriel/comparaison1.jpg" />
                        </div>
                    </div>
                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-7">
                            <h4>Icones de pages</h4>
                            <p>Des icones pages figurent dans les marges des deux textes comparés. Ils indiquent la pagination des documents originaux et y donnent accès. La confrontation du texte à sa source permet notamment de vérifier l’exactitude de l’édition. Les lecteurs sont invités à signaler d’éventuelles erreurs à l’adresse <a href="&#x6d;&#97;&#x69;&#x6c;&#x74;&#x6f;&#58;&#118;&#97;&#x72;&#x69;&#x61;&#x6e;&#99;&#101;&#64;&#x75;&#x6e;&#105;&#108;&#x2e;&#x63;&#x68;">variance@unil.ch</a>.</p>
                        </div>
                        <div class="left-part col-sm-5">
                            <img src="img/tutoriel/icones_pages.jpg" />
                        </div>
                    </div>

                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-7">
                            <h4>Menu de versions</h4>
                            <p>Dans la marge de gauche, le livre fait descendre un en-tête de navigation : il comporte le menu des versions de l’œuvre en cours de lecture et la Notice décrivant les étapes d’écriture. On pourra également, depuis cet en-tête, naviguer d’une œuvre à l’autre au sein du catalogue de la collection.
                            </p>
                        </div>
                        <div class="left-part col-sm-5">
                            <img src="img/tutoriel/icones_livre.jpg" />
                        </div>
                    </div>

                    <div class="clearfix centered-content">
                        <div class="right-part col-sm-7">
                            <h4>Options d'affichage</h4>
                            <p>De retour sur la marge de gauche, l’engrenage offre quelques options d’affichage (contraste, corps, intelignage, type de police) ; les choix seront mémorisés pour la prochaine consultation.</p>
                        </div>
                        <div class="left-part col-sm-5">
                            <img src="img/tutoriel/icone_settings.jpg" />
                        </div>
                    </div>
                    <p>
                        * Les comparaisons proposées dans <em>Variance</em> ont été réalisées à l’aide du logiciel d’alignement textuel monolingue medite (Machine pour l’étude diachronique des textes), développé par Jean-Gabriel Ganascia au <em>Laboratoire d’Informatique de Paris 6</em>.
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