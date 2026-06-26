<?php
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( home_url( '/encrypted-sources/' ) ) );
	exit;
}
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
	<title><?php echo wp_app_title( 'Encrypted Sources' ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
<?php wp_app_body_open(); ?>

<main class="encrypted-sources-app" data-encrypted-sources-app>
	<header class="app-header">
		<div>
			<h1>Encrypted Sources</h1>
			<p>Structural source records live in WordPress. Names, notes, contact details, and private tags decrypt only in this browser.</p>
		</div>
		<button class="button button-primary" type="button" data-unlock>Unlock</button>
	</header>

	<section class="source-composer" data-locked>
		<form data-source-form>
			<div class="field-grid">
				<label>
					Name or alias
					<input name="name" type="text" autocomplete="off">
				</label>
				<label>
					Contact
					<input name="contact" type="text" autocomplete="off">
				</label>
				<label>
					Risk
					<select name="risk">
						<option value="low">Low</option>
						<option value="medium">Medium</option>
						<option value="high">High</option>
					</select>
				</label>
				<label>
					Workflow
					<select name="workflow">
						<option value="active">Active</option>
						<option value="verify">Verify</option>
						<option value="archived">Archived</option>
					</select>
				</label>
			</div>
			<label>
				Private tags
				<input name="private_tags" type="text" autocomplete="off" placeholder="comma-separated">
			</label>
			<label>
				Notes
				<textarea name="notes" rows="5"></textarea>
			</label>
			<button class="button button-primary" type="submit">Save encrypted source</button>
		</form>
	</section>

	<section class="source-list" data-locked>
		<h2>Sources</h2>
		<div data-sources></div>
	</section>
</main>

<?php wp_app_body_close(); ?>
</body>
</html>
