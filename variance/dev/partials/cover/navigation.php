<?php
$pages = array(
	'index.php' => 'Accueil',
	'apropos.php' => 'A propos',
	'tutoriel.php' => 'Tutoriel',
	'partenaires.php' => 'Partenaires',
	'direction-scientifique.php' => 'Direction scientifique',
	'admin/' => 'Admin'
);
$currentPage = basename($_SERVER['REQUEST_URI']) ;
?>
	<ul class="pull-right">
		<?php

        foreach ($pages as $filename => $pageTitle) {
// Petit hack afin que '/' & 'index.php' soit la même page.
            if($currentPage == ""){
                $filename = 'index.php';
                $currentPage = 'index.php';
            }

			if ($filename == $currentPage) {
			    ?>
				<li><a class="active" href="<?php echo $filename ; ?>"><?php echo $pageTitle ; ?></a></li>
			<?php } else { ?>
				<li><a href="<?php echo $filename ; ?>"><?php echo $pageTitle ; ?></a></li>
			<?php
			} //if
		} //foreach
		?>
		<li><a href="mailto:variance@unil.ch">Contact</a></li>
	</ul>
