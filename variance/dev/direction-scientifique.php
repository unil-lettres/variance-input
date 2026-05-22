<?php require_once 'php/settings.inc.php';
include_once('partials/cover/header.php'); ?>
<div id="General-Wrapper">

	<header class="header-cover">
		<nav class="container">
			<a class="pull-left col-sm-3" href="<?php echo DIR_REL ?>/">
                <img class="logo" src="<?php echo DIR_REL ?>/img/full_logo_white.svg" alt="Logo Variance">
            </a>
			<?php include_once('partials/cover/navigation.php') ?>
		</nav>
	</header>

	<main>
		<!-- Partie "Catalogue" avec pagination -->
		<div class="content-cover container">
			<div class="col-lg-10 col-lg-offset-1 col-md-12">
				<div class="row clearfix">
					<h1>
						<?php
						if(isset($pagesInfos[$currentPage])){
							echo $pagesInfos[$currentPage][0];
						} ?>
					</h1>
                    <div class="clearfix row">
                        <div class="clearfix col-lg-6 col-md-6">
                            <h3>Directeurs scientifiques</h3>
                            <div class="pull-left vcard">
                                <h5>Rudolf Mahrer</h5>
                                <p>
                                    Université de Lausanne<br/>
                                    Faculté des Lettres<br/>
                                    Anthropole<br/>
                                    Section de français<br/>
                                    1015 Lausanne<br/>
                                    <a href="&#109;&#x61;&#105;&#x6c;&#x74;&#111;&#x3a;&#x72;&#117;&#x64;&#x6f;&#x6c;&#x66;&#x2e;&#109;&#97;&#x68;&#114;&#x65;&#x72;&#64;&#117;&#110;&#x69;&#108;&#46;&#x63;&#x68;">rudolf.mahrer@unil.ch</a>
                                </p>
                            </div>
                            <div class="pull-left vcard">
                                <h5>Joël Zufferey</h5>
                                <p>
                                    Université de Lausanne<br/>
                                    Faculté des Lettres<br/>
                                    Anthropole<br/>
                                    Section de français<br/>
                                    1015 Lausanne<br/>
                                    <a href="&#109;&#97;&#105;&#x6c;&#116;&#x6f;&#x3a;&#x6a;&#x6f;&#101;&#108;&#46;&#122;&#x75;&#102;&#102;&#101;&#x72;&#101;&#121;&#64;&#117;&#x6e;&#105;&#108;&#x2e;&#x63;&#104;">joel.zufferey@unil.ch</a>
                                </p>
                            </div>
                        </div>
                        <div class="clearfix col-lg-6 col-md-6">
                            <h3>Coordinateur des éditions</h3>
                            <div class="pull-left vcard">
                                <h5>Maxime Hoffmann</h5>
                                <p>
                                    Université de Lausanne<br/>
                                    Faculté des Lettres<br/>
                                    Anthropole</br/>
                                    Section de français<br/>
                                    1015 Lausanne<br/>
                                    <a href="&#109;&#097;&#105;&#108;&#116;&#111;:&#109;&#097;&#120;&#105;&#109;&#101;&#046;&#104;&#111;&#102;&#102;&#109;&#097;&#110;&#110;&#064;&#117;&#110;&#105;&#108;&#046;&#099;&#104;">maxime.hoffmann@unil.ch</a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="clearfix">
                        <h3>Comité scientifique</h3>
                        <p>
                            Marc Escola (Université de Lausanne)<br/>
                            Gilles Philippe (Université de Lausanne)<br/>
                            Anne Réach-Ngô (Université de Haute-Alsace)</br/>
                        </p>
                    </div>
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