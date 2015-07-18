<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Installation</title>
	<meta name="api-url" content="<?php echo 'http://', $_SERVER['HTTP_HOST'], dirname($_SERVER['REQUEST_URI']), '/installation-files/php/api.php'; ?>">
	<meta name="base-url" content="<?php echo 'http://', $_SERVER['HTTP_HOST'], dirname($_SERVER['REQUEST_URI']), '/'; ?>">

	<!-- Styles -->
	<link href="installation-files/css/vendor.css" rel="stylesheet">
	<link href="installation-files/css/layout.css" rel="stylesheet">
	<link href="installation-files/css/controls.css" rel="stylesheet">
	<link href="installation-files/css/animations.css" rel="stylesheet">
	<link href="installation-files/css/fonts.css" rel="stylesheet">
</head>
<body>
	<div id="wrap">

		<!-- Header -->
		<header>
			<div class="container" id="containerHeader"></div>

			<!-- Title -->
			<section class="title">
				<div class="container" id="containerTitle"></div>
			</section>
		</header>

		<!-- Body -->
		<section class="body">
			<div class="container" id="containerBody"></div>
		</section>
	</div>

	<!-- Footer -->
	<footer>
		<div class="container" id="containerFooter"></div>
	</footer>

	<!-- Render Partials -->
	<?php
		$partialList = array(
			'header',
			'title',
			'footer',
			'check',
			'check/fail',
			'config',
			'config/fail',
			'config/database',
			'config/database/mysql',
			'config/database/pgsql',
			'config/database/sqlite',
			'config/database/sqlsrv',
			'config/admin',
			'config/advanced',
			'config/general',
			'config/mail',
			'config/mail/mail',
			'config/mail/smtp',
			'starter',
			'progress',
			'progress/fail',
			'complete',
		);
	?>

	<?php foreach ($partialList as $file): ?>
		<script type="text/template" data-partial="<?= $file ?>">
			<?php include 'installation-files/partials/'.$file.'.htm'; ?>
		</script>
	<?php endforeach ?>

	<script src="installation-files/js/jquery-2.1.4.min.js"></script>
	<script src="installation-files/js/mustache.min.js"></script>
	<script src="installation-files/js/jquery.waterfall.js"></script>
	<script src="installation-files/js/main.js"></script>
	<script src="installation-files/js/check.js"></script>
	<script src="installation-files/js/config.js"></script>
	<script src="installation-files/js/starter.js"></script>
	<script src="installation-files/js/progress.js"></script>
	<script src="installation-files/js/complete.js"></script>
</body>
</html>