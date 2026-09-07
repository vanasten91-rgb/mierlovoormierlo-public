<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<title>Inloggen | <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="mvm-login-page">
	<main class="mvm-login" aria-labelledby="mvm-login-title">
		<section class="mvm-login__brand" aria-label="Mierlo voor Mierlo">
			<a class="mvm-login__home" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Naar de homepage van Mierlo voor Mierlo">
				<img src="<?php echo esc_url( MVM_PLATFORM_URL . 'assets/mvm-login-logo.jpg' ); ?>" width="830" height="597" alt="Mierlo voor Mierlo">
			</a>
			<p>Alles uit Mierlo, voor Mierlo.</p>
		</section>

		<section class="mvm-login__panel">
			<div class="mvm-login__card">
				<p class="mvm-login__eyebrow">Mijn Mierlo</p>
				<h1 id="mvm-login-title">Welkom terug</h1>
				<p class="mvm-login__intro">Log veilig in om je MvM-account, voorkeuren en nieuwsbrief te beheren.</p>
				<form class="mvm-login__form" data-mvm-login-form novalidate>
					<label for="mvm-login-identifier">E-mailadres of gebruikersnaam</label>
					<input id="mvm-login-identifier" name="login" type="text" autocomplete="username" autocapitalize="none" required>

					<label for="mvm-login-password">Wachtwoord</label>
					<div class="mvm-login__password">
						<input id="mvm-login-password" name="password" type="password" autocomplete="current-password" required>
						<button type="button" data-mvm-password-toggle aria-controls="mvm-login-password" aria-pressed="false">Toon</button>
					</div>

					<label class="mvm-login__remember"><input name="remember" type="checkbox" value="1"> Ingelogd blijven op dit apparaat</label>
					<div data-mvm-recaptcha></div>
					<p class="mvm-login__status" data-mvm-login-status role="status" aria-live="polite"></p>
					<button class="mvm-login__submit" type="submit">Inloggen bij MvM</button>
				</form>

				<p class="mvm-login__recover"><a href="<?php echo esc_url( home_url( '/password-recover/' ) ); ?>">Wachtwoord vergeten?</a></p>
				<nav class="mvm-login__legal" aria-label="Juridische informatie">
					<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Privacybeleid</a>
					<span aria-hidden="true">·</span>
					<a href="<?php echo esc_url( home_url( '/algemene-voorwaarden/' ) ); ?>">Algemene voorwaarden</a>
				</nav>
				<p class="mvm-login__recaptcha-note">Deze pagina wordt beschermd door reCAPTCHA. Het privacybeleid en de voorwaarden van Google zijn van toepassing.</p>
			</div>
			<p class="mvm-login__back"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Terug naar Mierlo voor Mierlo</a></p>
		</section>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
