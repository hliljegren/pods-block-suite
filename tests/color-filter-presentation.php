<?php
/**
 * Standalone regression tests for color filter rendering and matching.
 *
 * Run with: php tests/color-filter-presentation.php [src|build]
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$render_dir = $argv[1] ?? 'src';
if ( ! in_array( $render_dir, array( 'src', 'build' ), true ) ) {
	fwrite( STDERR, "Expected src or build.\n" );
	exit( 1 );
}

class WP_Error {
	private $message;
	public function __construct( $code, $message ) {
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}
class Test_Color_Field {
	public function get_type() {
		return 'color';
	}
	public function get_label() {
		return 'Object color';
	}
}
class Test_Pod_Data {
	public $storage = 'meta';
	public $type = 'post_type';
	public function get_storage() {
		return $this->storage;
	}
	public function get_type() {
		return $this->type;
	}
	public function get_table_info() {
		return array( 'meta_table' => 'wp_postmeta', 'table' => 'wp_custom', 'pod_table' => 'wp_pods_post' );
	}
}
class Test_Color_Pod {
	public $pod_data;
	public function __construct() {
		$this->pod_data = new Test_Pod_Data();
	}
	public function valid() {
		return true;
	}
	public function fields( $name = null ) {
		$fields = array(
			'array_color'  => array( 'type' => 'color', 'label' => 'Array color' ),
			'object_color' => new Test_Color_Field(),
			'description'  => array( 'type' => 'text', 'label' => 'Description' ),
			'pages'        => array( 'type' => 'number', 'label' => 'Pages' ),
			'price'        => array( 'type' => 'currency', 'label' => 'Price' ),
		);
		return null === $name ? $fields : ( $fields[ $name ] ?? null );
	}
	public function find( $args ) {
		$GLOBALS['find_params'] = $args;
	}
	public function total() {
		return 0;
	}
	public function total_found() {
		return 0;
	}
}
class Test_WPDB {
	public $last_error = '';
	public $last_query = '';
	public $postmeta = 'wp_postmeta';
	public $values = array( '#FA479B', '#FA479A', '#FF0000', '#00FF00', '#000', '#FFF', '#808080', '#8B4513', 'invalid' );
	public $queries = 0;
	public function prepare( $query, ...$args ) {
		$args = count( $args ) === 1 && is_array( $args[0] ) ? $args[0] : $args;
		foreach ( $args as $arg ) {
			$query = preg_replace_callback( '/%s/', function () use ( $arg ) {
				return "'" . str_replace( "'", "''", $arg ) . "'";
			}, $query, 1 );
		}
		return $query;
	}
	public function get_col( $query ) {
		++$this->queries;
		$this->last_query = $query;
		return $this->values;
	}
	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}
}
function pods( $name ) {
	return $GLOBALS['test_pod'];
}
function post_type_exists( $type ) {
	return in_array( $type, array( 'post', 'page' ), true );
}
function sanitize_key( $value ) {
	return strtolower( $value );
}
function sanitize_text_field( $value ) {
	return str_replace( '<', '&lt;', $value );
}
function wp_unslash( $value ) {
	return $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function __( $value, $domain ) {
	return $value;
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr_e( $value, $domain ) {
	echo esc_attr( $value );
}
function esc_url( $value ) {
	return esc_attr( $value );
}
function esc_html_e( $value, $domain ) {
	echo esc_html( $value );
}
function esc_html__( $value, $domain ) {
	return esc_html( $value );
}
function selected( $value, $current ) {
	if ( $value === $current ) {
		echo 'selected="selected"';
	}
}
function apply_filters( $name, $value ) {
	if ( 'pods_block_suite_color_list' === $name ) {
		$value['brown"test'] = '<Brown>';
	} elseif ( 'pods_block_suite_color_ranges' === $name ) {
		$value['brown"test'] = array( array( 'hue' => array( 15, 45 ), 'lightness' => array( 15, 50 ) ) );
	}
	return $value;
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function get_transient( $key ) {
	return $GLOBALS['transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $expiration ) {
	$GLOBALS['transients'][ $key ] = $value;
}
function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
}
function get_option( $key, $default = false ) {
	return $GLOBALS['options'][ $key ] ?? $default;
}
function add_option( $key, $value, $deprecated = '', $autoload = false ) {
	if ( isset( $GLOBALS['options'][ $key ] ) ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function update_option( $key, $value, $autoload = false ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function wp_generate_uuid4() {
	static $id = 0;
	return 'test-version-' . ++$id;
}
function current_filter() {
	return $GLOBALS['current_filter'];
}
function _get_meta_table( $type ) {
	return 'wp_' . $type . 'meta';
}
function remove_query_arg( $args ) {
	return '/items';
}
function get_block_wrapper_attributes( $attributes ) {
	return 'class="' . esc_attr( $attributes['class'] ) . '"';
}

require dirname( __DIR__ ) . '/includes/render-helpers.php';
require dirname( __DIR__ ) . '/includes/color-filters.php';

$_SERVER['REQUEST_URI'] = '/items';
$wpdb                   = new Test_WPDB();
$test_pod               = new Test_Color_Pod();
$transients             = array();
$options                = array();

function expect_color_result( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}
function render_color_filter( $query = array(), $pagination = false, $search = false, $pod_name = 'post' ) {
	global $render_dir, $wpdb;
	$_GET       = $query;
	$attributes = array(
		'selectedPod'      => $pod_name,
		'enablePagination' => $pagination,
		'enableSearch'     => $search,
		'filterFields'     => array( 'array_color', 'object_color', 'description', 'pages', 'price' ),
	);
	$block = (object) array();
	ob_start();
	include dirname( __DIR__ ) . '/' . $render_dir . '/item-list-container/render.php';
	return ob_get_clean();
}

$html = render_color_filter();
foreach ( array( 'array_color', 'object_color' ) as $field ) {
	$id = 'filter-post-' . $field;
	expect_color_result( false !== strpos( $html, '<label for="' . $id . '">' ), "$field should have a label" );
	expect_color_result( false !== strpos( $html, '<select id="' . $id . '" name="pods_filter_post_' . $field . '" onchange="this.form.submit()">' ), "$field should have an enabled dropdown" );
}
expect_color_result( 2 === substr_count( $html, '<option value="red" >Red</option>' ), 'both color dropdowns should include red' );
expect_color_result( 2 === substr_count( $html, '<option value="brown&quot;test" >&lt;Brown&gt;</option>' ), 'custom color IDs and labels should be escaped' );
expect_color_result( false !== strpos( $html, 'placeholder="Filter Description..."' ), 'text filters should remain text inputs' );
expect_color_result( 0 === $wpdb->queries, 'an inactive color filter should not query colors' );
expect_color_result( false !== strpos( $find_params['where'], "t.post_status = 'publish'" ) && false !== strpos( $find_params['where'], "t.post_password = ''" ), 'post lists should exclude unpublished and password-protected items' );

render_color_filter( array( 'pods_search_post' => "reader's 100%" ), false, true );
expect_color_result( false !== strpos( $find_params['where'], "t.post_title LIKE '%reader''s 100\\%%'" ) && false !== strpos( $find_params['where'], 'EXISTS (' ), 'search should include escaped title, content and text metadata conditions' );
expect_color_result( false !== strpos( $find_params['where'], "pm_search.meta_key IN ( 'description' )" ), 'search should include text fields but not numeric or color fields' );
expect_color_result( 1 === substr_count( $find_params['where'], 't.post_title LIKE' ), 'search should be applied only once' );
render_color_filter( array(), false, true );
expect_color_result( false === strpos( $find_params['where'], 'LIKE' ), 'an empty search should not add search conditions' );
render_color_filter( array(), false, false, 'custom_pod' );
expect_color_result( ! isset( $find_params['where'] ) || false === strpos( $find_params['where'], 't.post_password' ), 'non-post Pods should not receive post-only conditions' );

foreach ( array( 'pages', 'price' ) as $field ) {
	foreach ( array( '=' => '=', '>' => '&gt;', '>=' => '&gt;=', '<' => '&lt;', '<=' => '&lt;=' ) as $operator => $attribute ) {
		$html = render_color_filter(
			array(
				'pods_filter_post_' . $field         => '42',
				'pods_filter_post_' . $field . '_op' => $operator,
			)
		);
		expect_color_result( false !== strpos( $html, '<option value="' . $attribute . '" selected="selected">' ), "$field should preserve the selected $operator operator in texturize-safe markup" );
		expect_color_result( false !== strpos( $find_params['where'], "CAST( $field.meta_value AS DECIMAL(12,2) ) $operator " ), "$field should apply the $operator comparison" );
	}
}
foreach ( array( array( '>' ), '>= OR 1=1' ) as $invalid_operator ) {
	render_color_filter( array( 'pods_filter_post_pages' => '42', 'pods_filter_post_pages_op' => $invalid_operator ) );
	expect_color_result( false !== strpos( $find_params['where'], 'AS DECIMAL(12,2) ) = ' ), 'invalid operators should fall back to equality' );
}

$ranges = pods_block_suite_get_color_ranges();
foreach ( array(
	'#FA479A' => 'red',
	'#FA479B' => 'red',
	'#ff0000' => 'red',
	'#ff0008' => 'red',
	'#ff00aa' => 'purple',
	'#00FF00' => 'green',
	'#000'    => 'black',
	'#FFF'    => 'white',
	'#808080' => 'gray',
) as $hex => $family ) {
	expect_color_result( pods_block_suite_color_matches_ranges( pods_block_suite_hex_to_hsl( $hex ), $ranges[ $family ] ), "$hex should match $family" );
}
expect_color_result( null === pods_block_suite_hex_to_hsl( '#abcd' ), 'invalid colors must be rejected' );
expect_color_result( ! pods_block_suite_color_matches_ranges( pods_block_suite_hex_to_hsl( '#00FF00' ), $ranges['red'] ), 'green must not match red' );
expect_color_result( ! pods_block_suite_valid_color_ranges( array( array( 'hue' => array( 'invalid', 30 ) ) ) ), 'invalid range bounds must be rejected' );
expect_color_result( pods_block_suite_color_matches_ranges( pods_block_suite_hex_to_hsl( '#FF0000' ), array( array( 'hue' => array( 345, 15 ), 'saturation' => array( 10, 100 ) ) ) ), 'hue wrapping and an inclusive 100% saturation should work' );

$html = render_color_filter( array( 'pods_filter_post_array_color' => 'red' ) );
expect_color_result( false !== strpos( $html, '<option value="red" selected="selected">Red</option>' ), 'the selected family should remain selected' );
expect_color_result( false !== strpos( $find_params['where'], "'#FA479A'" ) && false !== strpos( $find_params['where'], "'#FA479B'" ), 'similar pink colors should be included before the Pods query' );
expect_color_result( false === strpos( $find_params['where'], "'#00FF00'" ), 'nonmatching colors should be excluded' );
expect_color_result( 1 === $wpdb->queries, 'the first filter should query distinct colors' );
render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
expect_color_result( 1 === $wpdb->queries, 'subsequent filters should reuse cached colors' );
expect_color_result( false !== strpos( $find_params['where'], "'#00FF00'" ), 'the cached colors should be reclassified for green' );

$wpdb->values[] = '#008000';
$cache_key      = pods_block_suite_color_cache_key( 'wp_postmeta', 'array_color' );
$old_cache      = $transients[ $cache_key ];
$current_filter = 'updated_post_meta';
pods_block_suite_invalidate_color_cache( 1, 1, 'array_color' );
$transients[ $cache_key ] = $old_cache;
render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
expect_color_result( 2 === $wpdb->queries && false !== strpos( $find_params['where'], "'#008000'" ), 'metadata updates should invalidate the field cache, even if an old lookup finishes later' );
foreach ( array( 'added_post_meta', 'deleted_post_meta' ) as $event ) {
	$current_filter = $event;
	$before         = $wpdb->queries;
	pods_block_suite_invalidate_color_cache( 1, 1, 'array_color' );
	render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
	expect_color_result( $before + 1 === $wpdb->queries, "$event should invalidate the color cache" );
}

$html = render_color_filter( array( 'pods_filter_post_array_color' => 'brown"test' ) );
expect_color_result( false !== strpos( $find_params['where'], "'#8B4513'" ), 'custom family ranges should match their stored colors' );
$html = render_color_filter(
	array(
		'pods_filter_post_object_color' => 'green',
		'pods_filter_post_description'  => 'item',
		'pods_page_post'                => '2',
	),
	true
);
expect_color_result( 2 === $find_params['page'] && false !== strpos( $find_params['where'], '`object_color`.meta_value IN' ) && false !== strpos( $find_params['where'], 'description.meta_value LIKE' ), 'color filters should compose with text filters before pagination' );

$html = render_color_filter( array( 'pods_filter_post_array_color' => 'not-a-color' ) );
expect_color_result( false !== strpos( $html, 'The selected color is not available for filtering.' ) && false !== strpos( $find_params['where'], '1 = 0' ), 'unknown colors should produce an explicit error and no results' );
render_color_filter( array( 'pods_filter_post_array_color' => 'cyan' ) );
expect_color_result( false !== strpos( $find_params['where'], '1 = 0' ), 'a valid family with no matching colors should return no results' );

$test_pod->pod_data->storage = 'table';
render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
expect_color_result( false !== strpos( $wpdb->last_query, 'FROM `wp_pods_post`' ), 'table storage should read its Pod table' );
expect_color_result( false !== strpos( $find_params['where'], '`d`.`array_color` IN' ), 'extended table storage should filter the Pod data table' );
$test_pod->pod_data->type = 'pod';
render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
expect_color_result( false !== strpos( $wpdb->last_query, 'FROM `wp_custom`' ) && false !== strpos( $find_params['where'], '`t`.`array_color` IN' ), 'custom Pods should filter their main table' );

$test_pod->pod_data->storage = 'unsupported';
$html = render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
expect_color_result( false !== strpos( $html, 'Color filtering is not supported for this Pod storage.' ) && false !== strpos( $find_params['where'], '1 = 0' ), 'unsupported storage should produce an explicit error and no results' );

$test_pod->pod_data->storage = 'table';
$wpdb->last_error = 'test database error';
$previous_error_log = ini_set( 'error_log', '/dev/null' );
$html = render_color_filter( array( 'pods_filter_post_array_color' => 'green' ) );
ini_set( 'error_log', $previous_error_log );
expect_color_result( false !== strpos( $html, 'Color filtering is temporarily unavailable.' ) && false !== strpos( $find_params['where'], '1 = 0' ), 'database errors should be visible and must not silently return all results' );

echo "Color filter tests passed ($render_dir).\n";
