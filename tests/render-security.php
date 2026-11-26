<?php
/**
 * Standalone regression tests for post visibility during block rendering.
 *
 * Run with: php tests/render-security.php [src|build]
 */

define( 'ABSPATH', __DIR__ . '/' );

$render_dir = $argv[1] ?? 'src';
if ( ! in_array( $render_dir, array( 'src', 'build' ), true ) ) {
	fwrite( STDERR, "Expected src or build.\n" );
	exit( 1 );
}

class WP_Post {
	public $ID;
	public $post_type = 'post';
	public $post_status;
	public $locked;
	public $content;

	public function __construct( $id, $status, $locked = false ) {
		$this->ID          = $id;
		$this->post_status = $status;
		$this->locked      = $locked;
		$this->content     = 'secret-' . $id;
	}
}

class WP_Block {
	public $context;
	public $inner_blocks;
	public $parsed_block;

	public function __construct( $parsed_block = array(), $context = array() ) {
		$this->parsed_block = $parsed_block;
		$this->context      = $context;
		$this->inner_blocks = array();
	}

	public function render() {
		return get_post( $this->context['postId'] )->content;
	}
}

class Test_Pod {
	private $index = 0;
	private $ids = array( 1, 4 );

	public function valid() {
		return true;
	}

	public function exists() {
		return false;
	}

	public function find( $args ) {
		global $list_where;
		$list_where = $args['where'] ?? '';
		if ( false !== strpos( $list_where, "t.post_password = ''" ) ) {
			$this->ids = array( 1 );
		}
	}

	public function total() {
		return count( $this->ids );
	}

	public function fetch() {
		return $this->index++ < count( $this->ids );
	}

	public function id() {
		return $this->ids[ $this->index - 1 ];
	}
}

$posts        = array(
	1 => new WP_Post( 1, 'publish' ),
	2 => new WP_Post( 2, 'private' ),
	3 => new WP_Post( 3, 'draft' ),
	4 => new WP_Post( 4, 'publish', true ),
	5 => new WP_Post( 5, 'trash' ),
);
$capabilities = array();
$post         = null;

function get_post( $id ) {
	global $posts;
	return $id instanceof WP_Post ? $id : ( $posts[ $id ] ?? null );
}
function get_post_status( $post ) {
	return $post->post_status;
}
function get_post_status_object( $status ) {
	return (object) array(
		'public'    => 'publish' === $status,
		'private'   => 'private' === $status,
		'protected' => 'draft' === $status,
		'internal'  => 'trash' === $status,
	);
}
function post_password_required( $post ) {
	return $post->locked;
}
function is_post_publicly_viewable( $post ) {
	return 'publish' === $post->post_status;
}
function current_user_can( $capability, $id ) {
	global $capabilities;
	return ! empty( $capabilities[ $capability ][ $id ] );
}
function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page' ), true );
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_key( $value ) {
	return strtolower( $value );
}
function sanitize_text_field( $value ) {
	return $value;
}
function pods( ...$args ) {
	return new Test_Pod();
}
function setup_postdata( $post ) {}
function wp_reset_postdata() {}
function add_filter( ...$args ) {}
function remove_filter( ...$args ) {}
function apply_filters( $name, $value ) {
	return $value;
}
function get_post_field( $field, $id ) {
	return get_post( $id )->content;
}
function wp_kses_post( $value ) {
	return $value;
}
function esc_attr( $value ) {
	return $value;
}
function get_block_wrapper_attributes( $attributes ) {
	return 'class="' . $attributes['class'] . '"';
}

require dirname( __DIR__ ) . '/includes/render-helpers.php';

function expect_result( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function render_smart_field( $id, $type = 'post' ) {
	global $render_dir;
	$attributes = array( 'fieldName' => 'post_content' );
	$block      = new WP_Block( array(), array( 'postId' => $id, 'postType' => $type ) );
	ob_start();
	include dirname( __DIR__ ) . '/' . $render_dir . '/smart-field/render.php';
	return ob_get_clean();
}

function render_item_container( $id ) {
	global $render_dir;
	$attributes               = array( 'customPostId' => $id );
	$block                    = new WP_Block();
	$block->inner_blocks      = array( new WP_Block() );
	ob_start();
	include dirname( __DIR__ ) . '/' . $render_dir . '/item-container/render.php';
	return ob_get_clean();
}

function render_item_list() {
	global $render_dir;
	$attributes               = array( 'selectedPod' => 'post', 'enablePagination' => false );
	$block                    = new WP_Block();
	$block->inner_blocks      = array( new WP_Block() );
	ob_start();
	include dirname( __DIR__ ) . '/' . $render_dir . '/item-list-container/render.php';
	return ob_get_clean();
}

$container               = new WP_Block();
$container->inner_blocks = array( new WP_Block() );

foreach ( array( 2, 3, 4, 5 ) as $id ) {
	expect_result( '' === pods_block_suite_render_contextual_inner_blocks( $container, $id, 'post' ), "container must not render inaccessible post $id" );
	expect_result( '' === render_smart_field( $id ), "standalone Smart Field must not render inaccessible post $id" );
}
expect_result( 'secret-1' === pods_block_suite_render_contextual_inner_blocks( $container, 1, 'post' ), 'public post should render' );
expect_result( false !== strpos( render_smart_field( 1 ), 'secret-1' ), 'standalone Smart Field should render public content' );
expect_result( '' === render_item_container( 2 ), 'item container must not disclose a private post' );
expect_result( false !== strpos( render_item_container( 1 ), 'secret-1' ), 'item container should render a public post' );
$list_html = render_item_list();
expect_result( false !== strpos( $list_html, 'secret-1' ), 'list should render a public item' );
expect_result( false === strpos( $list_html, 'secret-4' ), 'list must not disclose a locked item' );
expect_result( false !== strpos( $list_where, "t.post_password = ''" ), 'list query must exclude password-protected posts' );
expect_result( false !== strpos( $list_where, "t.post_status = 'publish'" ), 'list query must exclude unpublished posts' );
expect_result( '' === render_smart_field( 2, 'custom_pod' ), 'a non-post context must not bypass access checks for WordPress fields' );
expect_result( '' === pods_block_suite_render_contextual_inner_blocks( $container, 2, 'custom_pod' ), 'a non-post context must not expose a colliding private post to inner blocks' );
expect_result( '' === render_smart_field( 1, 'page' ), 'mismatched post type must not render' );

$capabilities['read_post'][2] = true;
$capabilities['edit_post'][3] = true;
$capabilities['edit_post'][4] = true;
expect_result( 'secret-2' === pods_block_suite_render_contextual_inner_blocks( $container, 2, 'post' ), 'authorized private post should render' );
expect_result( false !== strpos( render_smart_field( 3 ), 'secret-3' ), 'authorized draft should render' );
expect_result( '' === render_smart_field( 4 ), 'edit permission must not bypass a post password' );
$posts[4]->locked = false;
expect_result( false !== strpos( render_smart_field( 4 ), 'secret-4' ), 'unlocked password-protected post should render' );
expect_result( false === strpos( render_item_list(), 'secret-4' ), 'list must exclude password-protected posts even when unlocked' );
expect_result( '' === pods_block_suite_render_contextual_inner_blocks( $container, 99, 'post' ), 'missing post should not render' );

echo "Post rendering security tests passed ($render_dir).\n";
