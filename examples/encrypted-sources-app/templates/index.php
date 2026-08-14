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
			<button class="button button-primary app-action-button" type="button" data-unlock>Unlock encrypted fields</button>
			<p class="app-status" data-status role="status" aria-live="polite" hidden></p>
		</div>
	</header>

	<section class="source-composer" data-locked>
		<form data-source-form>
			<div class="field-grid">
				<label>
					Name or alias
					<input name="post_title" type="text" autocomplete="off">
				</label>
				<label>
					Contact
					<input name="contact" type="text" autocomplete="off">
				</label>
				<label>
					Risk
					<select name="source_risk">
						<option value="low">Low</option>
						<option value="medium">Medium</option>
						<option value="high">High</option>
					</select>
				</label>
				<label>
					Workflow
					<select name="source_workflow">
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
			<div class="form-actions">
				<button class="button button-primary app-action-button" type="submit" data-save-source>Save encrypted source</button>
				<button class="button app-secondary-button" type="button" data-cancel-edit hidden>Cancel edit</button>
			</div>
		</form>
	</section>

		<section class="source-list" data-locked>
			<h2>Sources</h2>
			<div data-sources></div>
		</section>

		<template data-source-template>
			<article class="source-item">
				<div class="source-item__header">
					<h3 data-source-field="post_title"></h3>
					<div class="source-item__actions">
						<span data-source-field="source_risk"></span>
						<button class="button source-edit-button" type="button" data-edit-source>Edit</button>
					</div>
				</div>
				<p class="source-contact" data-source-field="contact"></p>
				<p data-source-field="notes"></p>
				<p class="source-tags" data-source-field="private_tags"></p>
			</article>
		</template>
	</main>

<?php wp_app_body_close(); ?>
</body>
</html>
