<?php
/**
 * Plugin Name: Simple HTML Exporter
 * Description: Mass or selectively export posts and pages as raw HTML files in a ZIP package.
 * Version:     1.1.0
 * Author:      Charles Belton - charles@beltoncreative.com
 * Text Domain: simple-html-exporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SIMPLE_HTML_EXPORTER_PAGE_SLUG', 'simple-html-exporter' );
define( 'SIMPLE_HTML_EXPORTER_NONCE_ACTION', 'simple_html_exporter_export' );

add_action( 'admin_menu', 'simple_html_exporter_register_tools_page' );

/**
 * Register exporter under Tools.
 */
function simple_html_exporter_register_tools_page() {
	add_management_page(
		__( 'Simple HTML Exporter', 'simple-html-exporter' ),
		__( 'HTML Exporter', 'simple-html-exporter' ),
		'manage_options',
		SIMPLE_HTML_EXPORTER_PAGE_SLUG,
		'simple_html_exporter_render_admin_page'
	);
}

/**
 * Admin UI: select post type, posts, and export mode.
 */
function simple_html_exporter_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$selected_post_type = isset( $_GET['post_type_filter'] )
		? sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ) )
		: 'page';

	$post_types = get_post_types( array( 'public' => true ), 'objects' );

	$posts = get_posts(
		array(
			'post_type'      => $selected_post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Simple HTML Exporter', 'simple-html-exporter' ); ?></h1>
		<p><?php esc_html_e( 'Select posts or pages to export as raw', 'simple-html-exporter' ); ?> <code>.html</code> <?php esc_html_e( 'files packaged into a', 'simple-html-exporter' ); ?> <code>.zip</code> <?php esc_html_e( 'archive. Files are named', 'simple-html-exporter' ); ?> <code>[type]-[slug].html</code>.</p>

		<form method="get" style="margin-bottom: 20px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( SIMPLE_HTML_EXPORTER_PAGE_SLUG ); ?>" />
			<label for="post_type_filter"><strong><?php esc_html_e( 'Filter content type:', 'simple-html-exporter' ); ?></strong></label>
			<select name="post_type_filter" id="post_type_filter" onchange="this.form.submit()">
				<?php foreach ( $post_types as $post_type_object ) : ?>
					<option value="<?php echo esc_attr( $post_type_object->name ); ?>" <?php selected( $selected_post_type, $post_type_object->name ); ?>>
						<?php echo esc_html( $post_type_object->label ); ?> (<?php echo esc_html( $post_type_object->name ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="simple_html_exporter_download" />
			<?php wp_nonce_field( SIMPLE_HTML_EXPORTER_NONCE_ACTION, 'simple_html_exporter_nonce' ); ?>

			<div style="background: #fff; padding: 15px; border: 1px solid #ccc; margin-bottom: 15px;">
				<label>
					<input type="checkbox" id="simple-html-exporter-select-all" />
					<strong><?php esc_html_e( 'Select / Deselect All', 'simple-html-exporter' ); ?></strong>
				</label>
			</div>

			<div style="max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ccc; padding: 10px 15px;">
				<?php if ( ! empty( $posts ) ) : ?>
					<?php foreach ( $posts as $post_item ) : ?>
						<div style="padding: 4px 0;">
							<label>
								<input
									type="checkbox"
									name="simple_html_exporter_post_ids[]"
									value="<?php echo esc_attr( (string) $post_item->ID ); ?>"
									class="simple-html-exporter-post-cb"
								/>
								<strong><?php echo esc_html( $post_item->post_title ); ?></strong>
								<code>(<?php echo esc_html( $post_item->post_type . '-' . $post_item->post_name ); ?>.html)</code>
								&mdash; <em><?php echo esc_html( $post_item->post_status ); ?></em>
							</label>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No content found for this post type.', 'simple-html-exporter' ); ?></p>
				<?php endif; ?>
			</div>

			<div style="margin-top: 20px;">
				<h3><?php esc_html_e( 'Export options', 'simple-html-exporter' ); ?></h3>
				<label style="display: block; margin-bottom: 10px;">
					<input type="radio" name="simple_html_exporter_mode" value="raw" checked />
					<strong><?php esc_html_e( 'Raw editor markup', 'simple-html-exporter' ); ?></strong>
					<?php esc_html_e( '(best for pasting back into the block or classic editor)', 'simple-html-exporter' ); ?>
				</label>
				<label style="display: block; margin-bottom: 15px;">
					<input type="radio" name="simple_html_exporter_mode" value="rendered" />
					<strong><?php esc_html_e( 'Rendered HTML', 'simple-html-exporter' ); ?></strong>
					<?php esc_html_e( '(runs the_content filters and shortcodes first)', 'simple-html-exporter' ); ?>
				</label>

				<input type="submit" class="button button-primary button-hero" value="<?php esc_attr_e( 'Export selected to ZIP', 'simple-html-exporter' ); ?>" />
			</div>
		</form>
	</div>

	<script>
		document.getElementById('simple-html-exporter-select-all').addEventListener('change', function() {
			var checkboxes = document.querySelectorAll('.simple-html-exporter-post-cb');
			for (var i = 0; i < checkboxes.length; i++) {
				checkboxes[i].checked = this.checked;
			}
		});
	</script>
	<?php
}

add_action( 'admin_post_simple_html_exporter_download', 'simple_html_exporter_handle_download' );

/**
 * Build ZIP from selected posts and send as download.
 */
function simple_html_exporter_handle_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized.', 'simple-html-exporter' ) );
	}

	if (
		! isset( $_POST['simple_html_exporter_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['simple_html_exporter_nonce'] ) ),
			SIMPLE_HTML_EXPORTER_NONCE_ACTION
		)
	) {
		wp_die( esc_html__( 'Invalid security nonce.', 'simple-html-exporter' ) );
	}

	if ( empty( $_POST['simple_html_exporter_post_ids'] ) || ! is_array( $_POST['simple_html_exporter_post_ids'] ) ) {
		wp_die( esc_html__( 'No posts or pages were selected for export.', 'simple-html-exporter' ) );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( esc_html__( 'The PHP ZipArchive extension is not enabled on this server.', 'simple-html-exporter' ) );
	}

	$post_ids    = array_map( 'intval', wp_unslash( $_POST['simple_html_exporter_post_ids'] ) );
	$export_mode = (
		isset( $_POST['simple_html_exporter_mode'] )
		&& 'rendered' === sanitize_text_field( wp_unslash( $_POST['simple_html_exporter_mode'] ) )
	) ? 'rendered' : 'raw';

	$upload_dir   = wp_upload_dir();
	$zip_basename = 'simple-html-export-' . gmdate( 'Y-m-d-His' ) . '.zip';
	$zip_path     = $upload_dir['basedir'] . '/' . $zip_basename;

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		wp_die( esc_html__( 'Could not create ZIP archive on the server.', 'simple-html-exporter' ) );
	}

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			continue;
		}

		if ( 'rendered' === $export_mode ) {
			$content = apply_filters( 'the_content', $post->post_content );
		} else {
			$content = $post->post_content;
		}

		$file_header  = "<!--\n";
		$file_header .= '  Title: ' . $post->post_title . "\n";
		$file_header .= '  ID: ' . $post->ID . "\n";
		$file_header .= '  Slug: ' . $post->post_name . "\n";
		$file_header .= '  Type: ' . $post->post_type . "\n";
		$file_header .= "-->\n\n";

		$file_body = $file_header . $content;
		$entry_name = $post->post_type . '-' . $post->post_name . '.html';

		$zip->addFromString( $entry_name, $file_body );
	}

	$zip->close();

	if ( ! file_exists( $zip_path ) ) {
		wp_die( esc_html__( 'ZIP creation failed.', 'simple-html-exporter' ) );
	}

	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $zip_basename . '"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	readfile( $zip_path );
	wp_delete_file( $zip_path );
	exit;
}
