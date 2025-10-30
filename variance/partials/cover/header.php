<?php
$pagesInfos = array(
	'index.php' => ['Catalogue', 'catalogue desc'],
	'apropos.php' => ['A propos', 'a propos desc'],
	'tutoriel.php' => ['Tutoriel', 'tutoriel desc'],
	'partenaires.php' => ['Partenaires', 'Partenaires desc'],
	'direction_scientifique.php' => ['Direction Scientifique', 'Direction Scientifique desc']
);

$currentPage = basename($_SERVER['REQUEST_URI']) ;

?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if (isset($pagesInfos[$currentPage])) { ?>
	<title><?php echo (isset($pagesInfos[$currentPage][0]) ? $pagesInfos[$currentPage][0] :'Variance') ?></title>
	<meta name="description" content="<?php echo (isset($pagesInfos[$currentPage][1]) ? $pagesInfos[$currentPage][1] :'Variance Description') ?>"/>
	<?php } else { ?>
	<title>Variance</title>
	<?php } ?>
	<!--	The only one css file allowed to be in header-->
	<link rel="stylesheet" href="<?php echo DIR_REL ?>/dist/css/screen.min.css">
	<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,600,300' rel='stylesheet' type='text/css'>
	<!--	The only one plugin allowed to be in header-->
	<script src="<?php echo DIR_REL ?>/app/js/vendors/modernizr-custom.js"></script>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />

	<!--[if IE]>
	<script src="https://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
	<!--[if lt IE 9]>
	<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
	<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
	<![endif]-->
</head>
<body class="cover_section">

<!--[if lt IE 9]>
<div style="background: #ffff76; padding: 20px;">
	<p style="text-align: center" class="browserupgrade">Vous utilisez un navigateur <strong>obsolète</strong>. Veuillez le<a style="text-decoration: underline;" href="http://browsehappy.com/">mettre à jour</a> afin d'améliorer votre expérience sur internet.</p>
</div>
<![endif]-->
