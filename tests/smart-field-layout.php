<?php
/**
 * Standalone regression tests for Smart Field layout rendering.
 *
 * Run with: php tests/smart-field-layout.php [src|build]
 */

define( 'ABSPATH', __DIR__ . '/' );
$render_dir = $argv[1] ?? 'src';
if ( ! in_array( $render_dir, array( 'src', 'build' ), true ) ) {
	fwrite( STDERR, "Expected src or build.\n" );
	exit( 1 );
}

class Layout_Test_Pod {
	public function exists() {
		return false;
	}
}
function pods( ...$args ) {
	return new Layout_Test_Pod();
}
function sanitize_key( $value ) {
	return strtolower( $value );
}
function sanitize_text_field( $value ) {
	return $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function get_post( $id ) {
	return (object) array( 'post_type' => 'post' );
}
function post_type_exists( $type ) {
	return 'post' === $type;
}
function pods_block_suite_can_render_post( $post ) {
	return true;
}
function get_the_title( $id ) {
	return 'Example title';
}
function get_post_thumbnail_id( $id ) {
	return 42;
}
function get_post_mime_type( $id ) {
	return 'image/png';
}
function wp_get_attachment_url( $id ) {
	return 'https://example.test/image.png';
}
function wp_get_attachment_image( $id, $size ) {
	return '<img src="https://example.test/image.png" width="80" height="80" alt="">';
}
function wp_kses_post( $value ) {
	return $value;
}
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function get_block_wrapper_attributes( $attributes ) {
	return 'class="wp-block-pods-block-suite-smart-field ' . $attributes['class'] . '" style="' . $attributes['style'] . '"';
}

function render_layout_field( $field, $layout = null ) {
	global $render_dir;
	$attributes = array( 'fieldName' => $field );
	if ( null !== $layout ) {
		$attributes['layoutMode'] = $layout;
	}
	$block = (object) array( 'context' => array( 'postId' => 1, 'postType' => 'post' ) );
	ob_start();
	include dirname( __DIR__ ) . '/' . $render_dir . '/smart-field/render.php';
	return ob_get_clean();
}

foreach ( array( 'post_title', 'post_thumbnail' ) as $field ) {
	$default = render_layout_field( $field );
	$column  = render_layout_field( $field, 'column' );
	if ( false === strpos( $default, 'is-layout-row' ) || false === strpos( $default, '--pods-justify-content: center' ) || false === strpos( $column, 'is-layout-column' ) ) {
		fwrite( STDERR, "FAIL: $field must default to a centered row and preserve an explicit column layout.\n" );
		exit( 1 );
	}
}

echo "Smart Field layout tests passed ($render_dir).\n";
