<?php
if ( ! is_user_logged_in() ) {
	wp_redirect( wp_login_url( home_url( '/encrypted-contacts/' ) ) );
	exit;
}

$encrypted_contact_records = function_exists( 'encrypted_contacts_get_encrypted_records' ) ? encrypted_contacts_get_encrypted_records() : [];
$encrypted_contact_labels  = [
	'post_title'   => 'Name',
	'email'        => 'Email',
	'phone'        => 'Phone',
	'notes'        => 'Notes',
	'private_tags' => 'Tags',
];
?>
<!DOCTYPE html>
<html <?php wp_app_language_attributes(); ?>>
<head>
		<title><?php wp_app_the_title( 'Encrypted Contacts' ); ?></title>
	<?php wp_app_head(); ?>
</head>
<body class="wp-app-body">
<?php wp_app_body_open(); ?>

<main class="encrypted-contacts-app" data-encrypted-contacts-app>
	<header class="app-header">
		<div>
			<h1>Encrypted Contacts</h1>
			<p>Encrypted contacts live in WordPress and are decrypted by your browser when you know the password.</p>
			<button class="button button-primary app-action-button" type="button" data-unlock>Decrypt contacts</button>
			<p class="app-status" data-status role="status" aria-live="polite" hidden></p>
		</div>
	</header>

	<section class="contact-composer" data-contact-composer hidden>
		<div class="contact-composer__header">
			<h2 data-form-title>New contact</h2>
		</div>
		<form data-contact-form>
			<div class="field-grid">
				<label>
					Name or alias
					<input name="post_title" type="text" autocomplete="off">
				</label>
				<label>
					Email
					<input name="email" type="email" autocomplete="off">
				</label>
				<label>
					Phone
					<input name="phone" type="tel" autocomplete="off">
				</label>
				<label>
					Type
					<select name="contact_type">
						<option value="friend">Friend</option>
						<option value="family">Family</option>
						<option value="client">Client</option>
						<option value="colleague">Colleague</option>
						<option value="vendor">Vendor</option>
						<option value="service">Service</option>
						<option value="healthcare">Healthcare</option>
						<option value="other">Other</option>
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
				<button class="button button-primary app-action-button" type="submit" data-save-contact>Save encrypted contact</button>
				<button class="button app-secondary-button" type="button" data-cancel-edit>Cancel</button>
			</div>
		</form>
	</section>

	<section class="contact-list">
		<div class="contact-list__header">
			<div>
				<h2>Contacts</h2>
				<p><span data-contact-count><?php echo esc_html( count( $encrypted_contact_records ) ); ?></span> <span data-contact-count-label><?php echo 1 === count( $encrypted_contact_records ) ? 'encrypted contact' : 'encrypted contacts'; ?></span></p>
			</div>
			<div class="contact-list__actions">
				<div class="contact-import" data-import-dropzone hidden>
					<input type="file" accept=".vcf,.vcs,text/vcard,text/x-vcard" multiple data-import-files>
					<span>Drop vCard</span>
				</div>
				<button class="button button-primary app-action-button" type="button" data-add-contact hidden>Add contact</button>
			</div>
		</div>
		<div class="contact-list__items" data-contacts>
			<?php if ( $encrypted_contact_records ) : ?>
				<?php foreach ( $encrypted_contact_records as $index => $record ) : ?>
					<article class="contact-item contact-item--encrypted">
						<div class="contact-avatar">#</div>
						<div class="contact-item__body">
							<div class="contact-item__header">
								<h3><?php echo esc_html( 'Encrypted contact ' . ( $index + 1 ) ); ?></h3>
								<span>Encrypted</span>
							</div>
							<div class="encrypted-field-grid">
								<?php foreach ( $encrypted_contact_labels as $field => $label ) : ?>
									<?php
									$hex = encrypted_contacts_ciphertext_hex( isset( $record['encrypted'][ $field ] ) ? $record['encrypted'][ $field ] : null );
									if ( '' === $hex ) {
										continue;
									}
									?>
									<p>
										<span><?php echo esc_html( $label ); ?></span>
										<code><?php echo esc_html( $hex ); ?></code>
									</p>
								<?php endforeach; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else : ?>
				<p>No encrypted contacts saved yet.</p>
			<?php endif; ?>
		</div>
	</section>

	<template data-contact-template>
		<article class="contact-item">
			<div class="contact-avatar" data-contact-initial></div>
			<div class="contact-item__body">
				<div class="contact-item__header">
					<h3>
						<button class="contact-name-button" type="button" data-expand-contact aria-expanded="false" data-contact-field="post_title"></button>
					</h3>
					<div class="contact-item__actions">
						<span data-contact-field="contact_type"></span>
						<button class="button contact-edit-button" type="button" data-edit-contact>Edit</button>
						<button class="button contact-delete-button" type="button" data-delete-contact>Delete</button>
					</div>
				</div>
				<div class="contact-expanded" data-contact-expanded hidden>
					<div class="contact-detail-row">
						<p class="contact-detail"><a data-contact-link="email"></a></p>
						<p class="contact-detail"><a data-contact-link="phone"></a></p>
					</div>
					<p class="contact-notes" data-contact-field="notes"></p>
					<p class="contact-tags" data-contact-field="private_tags"></p>
				</div>
			</div>
		</article>
	</template>
</main>

<?php wp_app_body_close(); ?>
</body>
</html>
