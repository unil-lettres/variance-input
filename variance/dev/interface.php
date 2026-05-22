<?php
require_once 'php/settings.inc.php';
?>
<!doctype html>
<html class="no-js" lang="fr">
<head>
	<meta charset="utf-8">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>titre</title>
		<meta name="description" content="description"/>
	<!--	The only one css file allowed to be in header-->


	<link rel="stylesheet" href="<?php echo DIR_REL ?>/app/js/imageviewer.css">
	<link rel="stylesheet" href="<?php echo DIR_REL ?>/dist/css/screen.min.css">
	<link href='https://fonts.googleapis.com/css?family=Open+Sans:400,600,300' rel='stylesheet' type='text/css'>
	<!--	The only one plugin allowed to be in header-->
	<script src="<?php echo DIR_REL ?>/app/js/vendors/modernizr-custom.js"></script>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
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
	<p style="text-align: center" class="browserupgrade">Vous utilisez un navigateur <strong>obsolète</strong>. Veuillez le<a style="text-decoration: underline;" href="http://browsehappy.com/">mettre à jour</a> afin d'améliorer votre expérience sur internet.</p>
</div>
<![endif]-->

<script type="text/javascript">
	$(document).ready(function(){
		$('.mod_settings').click(function(){
			var currentSection = $(this).data('cat');

			if(!$('#content').hasClass('open') && !$(this).hasClass('active')){
				$('#content').addClass('open');
				$('#tools').addClass('open');
				$(this).addClass('active');
				$('#tools').find('.cat-item').hide(0, function(){
					$('#tools').find("#"+currentSection).fadeIn(0);
				});
			}else if($(this).hasClass('active')){
				$('#tools').find('.cat-item').fadeOut();
				$('#content').removeClass('open');
				$('#tools').removeClass('open');
				$(this).removeClass('active');
			}else{
				$('#tools').find('.cat-item').hide(0, function(){
					$('#tools').find("#"+currentSection).fadeIn(0);
				});
				$('.mod_settings').removeClass('active');
				$(this).addClass('active');
			}
		});
		$('.settings_button').click(function(){
			var currentSetting = $(this).data('settings');
			//alert(currentSetting);
			if(!$('#settings').hasClass('open') && !$(this).hasClass('active')){
				$('.js-settings').fadeOut(0, function(){
					$("#" + currentSetting).fadeIn();
					});
				$('#settings').addClass('open');
				$(this).addClass('active');
			}else if($(this).hasClass('active')){
				$('#settings').removeClass('open');
				$(this).removeClass('active');
			}else{
				$('.settings_button').removeClass('active');
				$('.js-settings').hide(0, function(){
					$("#" + currentSetting).fadeIn();
				});
				$(this).addClass('active');
			}
		});

		$('.js-serif').click(function(){
			$('.interface').addClass('serif');
			$('.interface').removeClass('ss-serif');
			$(this).addClass('active');
			$('.js-ss-serif').removeClass('active');
		});

		$('.js-ss-serif').click(function(){
			$('.interface').addClass('ss-serif');
			$('.interface').removeClass('serif');
			$(this).addClass('active');
			$('.js-serif').removeClass('active');
		});


		$('.js-dark-bkg').click(function(){
			$('.interface').addClass('dark-bkg');
			$('.interface').removeClass('light-bkg');
			$(this).addClass('active');
			$('.js-light-bkg').removeClass('active');
		});

		$('.js-light-bkg').click(function(){
			$('.interface').addClass('light-bkg');
			$('.interface').removeClass('dark-bkg');
			$(this).addClass('active');
			$('.js-dark-bkg').removeClass('active');
		});

		$('.js-lineheight-big').click(function(){
			$('.interface').addClass('lineheight-big');
			$('.interface').removeClass('lineheight-small');
			$(this).addClass('active');
			$('.js-lineheight-small, .js-lineheight-medium').removeClass('active');
		});

		$('.js-lineheight-small').click(function(){
			$('.interface').addClass('lineheight-small');
			$('.interface').removeClass('lineheight-big');
			$(this).addClass('active');
			$('.js-lineheight-big, .js-lineheight-medium').removeClass('active');
		});


		$('.js-lineheight-medium').click(function(){
			$('.interface').removeClass('lineheight-small lineheight-big');
			$(this).addClass('active');
			$('.js-lineheight-big, .js-lineheight-small').removeClass('active');
		});


		$('.js-font-small').click(function(){
			$('.interface').addClass('font-small');
			$('.interface').removeClass('font-big font-medium');
			$(this).addClass('active');
			$('.js-font-big, .js-font-medium').removeClass('active');
		});

		$('.js-font-medium').click(function(){
			$('.interface').removeClass('font-big font-small');
			$(this).addClass('active');
			$('.js-font-big, .js-font-small').removeClass('active');
		});

		$('.js-font-big').click(function(){
			$('.interface').addClass('font-big');
			$('.interface').removeClass('font-small font-medium');
			$(this).addClass('active');
			$('.js-font-medium, .js-font-small').removeClass('active');
		});

		$('#comparisonId').change(function(){
			$('#btnVC').addClass('change');
		});


		$('.paging-image__wrapper').ImageViewer();


	});

</script>
<div id="General-Wrapper">
	<main class="container-fluid interface">
		<nav id="navBar" class="interface__nav clearfix">
			<a href="#" class="logo"><img src="/img/logo_min_variance.svg"></a>
			<a href="#" class="settings_button" id="workButton" data-settings="USC"><img src="img/cog.svg"></a>
			<a href="#" class="settings_button" id="bookButton" data-settings="BSC"><img src="img/book.svg"></a>
			<a href="#" class="mod_settings add" data-cat="add" id="" ><img src="img/add.svg"><span>Insertion</span></a>
			<a href="#" class="mod_settings delete" data-cat="delete" id="" ><img src="img/delete.svg"><span>Suppression</span></a>
			<a href="#" class="mod_settings replace" data-cat="replace" id="" ><img src="img/replace.svg"><span>Remplacement</span></a>
			<a href="#" class="mod_settings move" data-cat="move" id="" ><img src="img/move.svg"><span>Déplacement</span></a>
		</nav>
		<div class="interface__main">
			<section id="settings" class="interface__settings">
				<div class="settings_wrapper">
					<div class="book_settings js-settings" id="BSC">
						<div class="book_settings__item author pull-left">
							<span class="label">Autheur</span>
							<span class="author__name"><span class="firstName">H.</span>de Balsac</span>
						</div>

						<div class="book_settings__item oeuvre pull-left">
							<span class="label">Oeuvre</span>
							<span class="oeuvre__name">La peau de chagrin.</span>
						</div>

						<div class="book_settings__item book-info pull-left">
							<span class="label">Informations</span>
							<span class="book-info__name"><img src="img/book_info.svg"></span>
						</div>

						<div class="book_settings__item comparison pull-left">
							<span class="label">Comparaisons</span>
							<select id="comparisonId">
								<option value="1975|1976">1975|1976</option>
								<option value="1975|1976">1975|1976</option>
								<option value="1975|1976">1975|1976</option>
								<option value="1975|1976">1975|1976</option>
							</select>
						</div>
						<a href="#" title="selectionnez une comparaison" id="btnVC" class="btn_validate"><img src="img/btn_validate.svg"></a>
					</div>
					<div class="ui_settings js-settings" id="USC">
						<div class="ui_settings__size pull-left ui_settings__item">
							<span class="label">Taille</span>
							<div class="settings-btns">
								<a href="#" class="js-font-big" title="Grande">
									<img src="/img/settings/font_size_big.svg" >
								</a>
								<a class="active js-font-medium" href="#" title="Moyenne">
									<img src="/img/settings/font_size_medium.svg" >
								</a>
								<a href="#" class="js-font-small" title="Petite">
									<img src="/img/settings/font_size_small.svg" >
								</a>
							</div>
						</div>
						<div class="ui_settings__line ui_settings__item pull-left">
							<span class="label">Interlignage</span>
							<div class="settings-btns">
								<a href="#" class="js-lineheight-big" title="Grande">
									<img src="/img/settings/line_height_big.svg" >
								</a>
								<a class="active js-lineheight-medium" href="#" title="Moyenne">
									<img src="/img/settings/line_height_medium.svg" >
								</a>
								<a href="#" class="js-lineheight-small" title="Petite">
									<img src="/img/settings/line_height_small.svg" >
								</a>
							</div>
						</div>
						<div class="ui_settings__line ui_settings__item pull-left">
							<span class="label">Contrast</span>
							<div class="settings-btns">
								<a class="active js-light-bkg" href="#" title="Moyenne">
									<img src="/img/settings/contrast_black.svg" >
								</a>
								<a class="inverted js-dark-bkg" href="#" title="Grande">
									<img src="/img/settings/contrast_white.svg" >
								</a>
							</div>
						</div>
						<div class="ui_settings__line ui_settings__item pull-left">
							<span class="label">Style typographique</span>
							<div class="settings-btns">
								<a class="active js-ss-serif"  href="#" title="Moyenne">
									<img src="/img/settings/font_ss_serif.svg" >
								</a>
								<a href="#" class="js-serif" title="Grande">
									<img src="/img/settings/font_serif.svg" >
								</a>
							</div>
						</div>
						<a href="#" title="selectionnez une comparaison" id="btnVC" class="btn_validate"><img src="img/btn_validate.svg"></a>
					</div>
				</div>
			</section>
			<section id="tools" class="interface__tools">
				<div class="cat-item mCustomScrollbar" data-mcs-theme="minimal-dark" id="add">
					<ol>
						<li><a>add</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>	<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>	<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
					</ol>
				</div>
				<div class="cat-item mCustomScrollbar" data-mcs-theme="minimal-dark" id="delete">
					<ol>
						<li><a>delete</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li><li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li><li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
					</ol>
				</div>
				<div class="cat-item mCustomScrollbar" data-mcs-theme="minimal-dark" id="replace">
					<ol>
						<li><a>replace</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam<li><a>replace</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam<li><a>replace</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
					</ol>
				</div>
				<div class="cat-item mCustomScrollbar" data-mcs-theme="minimal-dark" id="move">
					<ol>
						<li><a>move</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
						<li><a>et justo duo dolores</a></li>
						<li><a> dolores et ea rebum. St et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a> duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li><li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam et justo duo dolores</a></li>
						<li><a>accusam </a></li>
						<li><a>sa justo duo dolores</a></li>
						<li><a>to duo dolores</a></li>
						<li><a>sam et justo duo dolores</a></li>
						<li><a> dolores</a></li>
					</ol>
				</div>
			</section>
			<section id="content" class="interface__content clearfix">
				<div id="js-workarea-left"  class="col-sm-6 workarea workarea--left">
					<div class="paging-image">
						<div class="paging-image__wrapper" data-src="/uploads_images/img1.jpg" data-high-res-src="/uploads_images/img1.jpg">
						</div>
					</div>
					<div class="workarea--left__container mCustomScrollbar" dir="rtl" data-mcs-theme="minimal-dark">
						<div class="txt-container">
							<p>Duis autem vel eum iriure <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> dolor <a href="#bd_00082" class="span_d" id="ad_00082"> la totalisation </a>in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu <a href="#bc_00011" class="span_c" id="ac_00011"> la contemplation </a>feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
							<p>At vero <a class="page-marker"><span class="page-number">002</span><img src="/img/settings/page_left.svg"></a>eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
							<p>Duis <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> autem vel <span class="span_s" id="as_00004">encore loin d’être parfaitement univoque. La</span> molestie consequat, vel illum dolore eu feugiat nulla facilisis.</p>
							<p>Duis autem vel eum iriure dolor in <a href="#bc_00014" class="span_c" id="ac_00014"> faire </a> hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
							<p>At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd <span class="span_s" id="as_00004">encore loin d’être parfaitement univoque. La</span>gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren,<a href="#ar_00015" class="span_r" id="br_00015">ambition légitime. Comme il est légitime – en prenant garde d’éviter les anachronismes – d’appliquer au passé les moyens qui nous servent à nous comprendre nous-mêmes, dans</a> kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
							<p>Duis autem vel eum <a href="#br_00010" class="span_r" id="ar_00010">le décret de notre volonté; <a href="#ad_00136" class="span_d" id="bd_00136"> psychologique</a> en voulant dépasser et prolonger nos antécédents nous leur conférons</a>iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis.</p>

							<p>Duis autem vel eum iriure <a href="#ad_00136" class="span_d" id="bd_00136"> psychologique</a>dolor in hendrerit <a href="#bd_00082" class="span_d" id="ad_00082"> la totalisation </a>in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
							<p>At vero eos <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> et accusam et justo <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> duo dolores et ea rebum. Stet clita kasd gubergren, <a href="#bc_00014" class="span_c" id="ac_00014"> faire </a>no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
							Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis.
						</div>
						<div class="pagination_spacer"></div>
					</div>
				</div>
				<div id="js-workarea-right" data-mcs-theme="minimal-dark" class="col-sm-6 workarea workarea--right mCustomScrollbar">
					<div class="pagination_spacer"></div>
					<div class="txt-container">
						<p>Duis autem vel eum iriureautem vel eum iriureautem vel eum iriureautem vel eum iriure <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> dolor <a href="#bd_00082" class="span_d" id="ad_00082"> la totalisation </a>in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu <a href="#bc_00011" class="span_c" id="ac_00011"> la contemplation </a>feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
						<p>At vero <a class="page-marker"><span class="page-number">002</span><img src="/img/settings/page_right.svg"></a>eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
						<p>Duis <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> autem vel <span class="span_s" id="as_00004">encore loin d’être parfaitement univoque. La</span> molestie consequat, vel illum dolore eu feugiat nulla facilisis.</p>
						<p>Duis autem vel eum iriure dolor in <a href="#bc_00014" class="span_c" id="ac_00014"> faire </a> hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
						<p>At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd <span class="span_s" id="as_00004">encore loin d’être parfaitement univoque. La</span>gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.<a class="page-marker"><span class="page-number">003</span><img src="/img/settings/page_right.svg"></a> At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren,<a href="#ar_00015" class="span_r" id="br_00015">ambition légitime. Comme il est légitime – en prenant garde d’éviter les anachronismes – d’appliquer au passé les moyens qui nous servent à nous comprendre nous-mêmes, dans</a> kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
						<p>Duis autem vel eum <a href="#br_00010" class="span_r" id="ar_00010">le décret de notre volonté; <a href="#ad_00136" class="span_d" id="bd_00136"> psychologique</a> en voulant dépasser et prolonger nos antécédents nous leur conférons</a>iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis.</p>

						<p>Duis autem vel eum iriure <a href="#ad_00136" class="span_d" id="bd_00136"> psychologique</a>dolor in hendrerit <a href="#bd_00082" class="span_d" id="ad_00082"> la totalisation </a>in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.</p>
						<p>At vero eos <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> et accusam et justo <a href="#bd_00096" class="span_d" id="ad_00096"> lieu de</a> duo dolores et ea rebum. Stet clita kasd gubergren, <a href="#bc_00014" class="span_c" id="ac_00014"> faire </a>no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, At accusam aliquyam diam diam dolore dolores duo eirmod eos erat, et nonumy sed tempor et et invidunt justo labore Stet clita ea et gubergren, kasd magna no rebum. sanctus sea sed takimata ut vero voluptua. est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat.</p>
						Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis.
					</div>

				</div>
			</section>
		</div>
	</main>
</div>
<!-- END GENERAL-WRAPPER -->
<div class="breakpoints"></div>



<script src="<?php echo DIR_REL ?>/app/js/imageviewer.min.js"></script>
<script src="<?php echo DIR_REL ?>/dist/js/main.min.js"></script>

</body>
</html>
