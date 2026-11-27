<?php
/**
 * Color-family matching for visitor filters.
 *
 * @package PodsBlockSuite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns HSL ranges for each color ID. Bounds are inclusive at the start and
 * exclusive at the end, except that a saturation or lightness end of 100
 * includes 100. Hue ranges may wrap around 360 degrees.
 *
 * @return array Color IDs mapped to arrays of HSL ranges.
 */
function pods_block_suite_get_color_ranges() {
	$chromatic = array(
		'red'    => array( 343, 17 ),
		'orange' => array( 13, 47 ),
		'yellow' => array( 43, 77 ),
		'green'  => array( 73, 167 ),
		'cyan'   => array( 163, 197 ),
		'blue'   => array( 193, 257 ),
		'purple' => array( 253, 347 ),
	);
	$ranges = array();
	foreach ( $chromatic as $id => $hue ) {
		$ranges[ $id ] = array(
			array(
				'hue'        => $hue,
				'saturation' => array( 10, 100 ),
				'lightness'  => array( 15, 90 ),
			),
		);
	}
	$ranges['red'][] = array(
		'hue'        => array( 315, 345 ),
		'saturation' => array( 10, 100 ),
		'lightness'  => array( 60, 90 ),
	);
	$ranges['black'] = array( array( 'lightness' => array( 0, 15 ) ) );
	$ranges['white'] = array( array( 'lightness' => array( 90, 100 ) ) );
	$ranges['gray']  = array(
		array(
			'saturation' => array( 0, 10 ),
			'lightness'  => array( 15, 90 ),
		),
	);

	/**
	 * Filters the HSL ranges used for color-family matching.
	 *
	 * Each color ID maps to one or more ranges. Each range may specify hue
	 * (degrees), saturation and lightness (percent), as [start, end] bounds.
	 *
	 * @param array $ranges Color IDs mapped to arrays of HSL ranges.
	 */
	return apply_filters( 'pods_block_suite_color_ranges', $ranges );
}

/**
 * Validates the ranges supplied for a color family.
 *
 * @param mixed $ranges Ranges for one color family.
 * @return bool Whether all ranges are valid.
 */
function pods_block_suite_valid_color_ranges( $ranges ) {
	if ( ! is_array( $ranges ) || empty( $ranges ) ) {
		return false;
	}
	foreach ( $ranges as $range ) {
		if ( ! is_array( $range ) || empty( $range ) || array_diff( array_keys( $range ), array( 'hue', 'saturation', 'lightness' ) ) ) {
			return false;
		}
		foreach ( $range as $component => $bounds ) {
			if ( ! is_array( $bounds ) || count( $bounds ) !== 2 || ! isset( $bounds[0], $bounds[1] ) || ! is_numeric( $bounds[0] ) || ! is_numeric( $bounds[1] ) ) {
				return false;
			}
			$start = (float) $bounds[0];
			$end   = (float) $bounds[1];
			$max   = 'hue' === $component ? 360 : 100;
			if ( ! is_finite( $start ) || ! is_finite( $end ) || $start < 0 || $start >= $max || $end < 0 || $end > $max || $start === $end || ( 'hue' !== $component && $start > $end ) ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Returns a representative HSL color for a family's first matching range.
 *
 * @param mixed $ranges Ranges for one color family.
 * @return string CSS color, or an empty string for invalid ranges.
 */
function pods_block_suite_color_swatch( $ranges ) {
	if ( ! pods_block_suite_valid_color_ranges( $ranges ) ) {
		return '';
	}
	$range = reset( $ranges );
	$hue   = 0;
	if ( isset( $range['hue'] ) ) {
		$start = (float) $range['hue'][0];
		$end   = (float) $range['hue'][1];
		$hue   = fmod( ( $start + ( $end < $start ? $end + 360 : $end ) ) / 2, 360 );
	}
	$saturation = isset( $range['saturation'] ) ? ( $range['saturation'][0] + $range['saturation'][1] ) / 2 : ( isset( $range['hue'] ) ? 70 : 0 );
	$lightness  = isset( $range['lightness'] ) ? ( $range['lightness'][0] + $range['lightness'][1] ) / 2 : 50;

	return sprintf( 'hsl(%s, %s%%, %s%%)', $hue, $saturation, $lightness );
}

/**
 * Converts a three- or six-digit hexadecimal color to HSL.
 *
 * @param string $hex Stored color.
 * @return array|null HSL values, or null for an invalid color.
 */
function pods_block_suite_hex_to_hsl( $hex ) {
	if ( ! is_string( $hex ) || ! preg_match( '/^#?([a-f0-9]{3}|[a-f0-9]{6})$/i', $hex, $matches ) ) {
		return null;
	}
	$hex = $matches[1];
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$r     = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g     = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b     = hexdec( substr( $hex, 4, 2 ) ) / 255;
	$max   = max( $r, $g, $b );
	$min   = min( $r, $g, $b );
	$delta = $max - $min;
	$light = ( $max + $min ) / 2;
	$hue   = 0;
	$sat   = 0;

	if ( $delta > 0 ) {
		$sat = $delta / ( 1 - abs( 2 * $light - 1 ) );
		if ( $max === $r ) {
			$hue = 60 * fmod( ( $g - $b ) / $delta, 6 );
		} elseif ( $max === $g ) {
			$hue = 60 * ( ( $b - $r ) / $delta + 2 );
		} else {
			$hue = 60 * ( ( $r - $g ) / $delta + 4 );
		}
		if ( $hue < 0 ) {
			$hue += 360;
		}
	}

	return array(
		'hue'        => $hue,
		'saturation' => min( 100, max( 0, $sat * 100 ) ),
		'lightness'  => $light * 100,
	);
}

/**
 * Checks whether a color matches at least one valid HSL range.
 *
 * @param array $hsl    Converted color.
 * @param array $ranges Ranges for one color family.
 * @return bool Whether a range matches.
 */
function pods_block_suite_color_matches_ranges( array $hsl, array $ranges ) {
	foreach ( $ranges as $range ) {
		if ( ! is_array( $range ) || empty( $range ) || array_diff( array_keys( $range ), array( 'hue', 'saturation', 'lightness' ) ) ) {
			continue;
		}
		$matches = true;
		foreach ( $range as $component => $bounds ) {
			if ( ! is_array( $bounds ) || count( $bounds ) !== 2 || ! isset( $bounds[0], $bounds[1] ) || ! is_numeric( $bounds[0] ) || ! is_numeric( $bounds[1] ) ) {
				$matches = false;
				break;
			}
			$value = $hsl[ $component ];
			$start = (float) $bounds[0];
			$end   = (float) $bounds[1];
			if ( 'hue' === $component && $start > $end ) {
				$matches = $value >= $start || $value < $end;
			} else {
				$matches = $value >= $start && ( $value < $end || ( 'hue' !== $component && 100.0 === $end && $value <= $end ) );
			}
			if ( ! $matches ) {
				break;
			}
		}
		if ( $matches ) {
			return true;
		}
	}
	return false;
}

/**
 * Builds the transient name for distinct colors in a metadata field.
 *
 * @param string $table Metadata table.
 * @param string $field Metadata key.
 * @return string Transient name.
 */
function pods_block_suite_color_cache_key( $table, $field ) {
	return 'pods_bs_colors_' . md5( $table . ':' . $field );
}

/**
 * Builds the option name for a metadata field's color cache version.
 *
 * @param string $table Metadata table.
 * @param string $field Metadata key.
 * @return string Option name.
 */
function pods_block_suite_color_version_key( $table, $field ) {
	return 'pods_bs_color_version_' . md5( $table . ':' . $field );
}

/**
 * Invalidates cached colors when WordPress metadata changes.
 *
 * @param mixed  $meta_id Metadata ID or IDs.
 * @param int    $object_id Object ID.
 * @param string $meta_key Metadata key.
 */
function pods_block_suite_invalidate_color_cache( $meta_id, $object_id, $meta_key ) {
	if ( preg_match( '/^(?:added|updated|deleted)_(post|term|comment)_meta$/', current_filter(), $matches ) ) {
		$table = _get_meta_table( $matches[1] );
		if ( $table ) {
			$key = pods_block_suite_color_version_key( $table, $meta_key );
			if ( false !== get_option( $key, false ) ) {
				if ( ! update_option( $key, wp_generate_uuid4(), false ) ) {
					error_log( 'PODS Block Suite could not invalidate a color filter cache.' );
				}
			}
		}
	}
}

/**
 * Retrieves distinct stored colors and their HSL values for a Pod field.
 *
 * @param Pods   $pod   Initialized Pod.
 * @param string $field Field name.
 * @return array|WP_Error Stored values mapped to HSL, or an error.
 */
function pods_block_suite_get_field_colors( $pod, $field ) {
	global $wpdb;

	$pod_data  = $pod->pod_data;
	$storage   = $pod_data->get_storage();
	$type      = $pod_data->get_type();
	$info      = $pod_data->get_table_info();
	$is_meta   = 'meta' === $storage && in_array( $type, array( 'post_type', 'media', 'taxonomy', 'user', 'comment' ), true );
	$cacheable = $is_meta && 'user' !== $type;

	if ( $is_meta ) {
		$table = $info['meta_table'] ?? '';
	} elseif ( 'table' === $storage ) {
		$table = in_array( $type, array( 'pod', 'table' ), true ) ? ( $info['table'] ?? '' ) : ( $info['pod_table'] ?? '' );
	} else {
		return new WP_Error( 'unsupported_color_storage', __( 'Color filtering is not supported for this Pod storage.', 'pods-block-suite' ) );
	}
	if ( ! is_string( $table ) || ! preg_match( '/^[a-zA-Z0-9_$]+$/', $table ) || ! preg_match( '/^[a-z0-9_-]+$/', $field ) ) {
		return new WP_Error( 'invalid_color_storage', __( 'Color filtering could not access the Pod field.', 'pods-block-suite' ) );
	}

	if ( $cacheable ) {
		$version_key = pods_block_suite_color_version_key( $table, $field );
		$version     = get_option( $version_key, false );
		if ( false === $version ) {
			add_option( $version_key, wp_generate_uuid4(), '', false );
			$version = get_option( $version_key, false );
		}
		if ( false === $version ) {
			error_log( 'PODS Block Suite could not initialize a color filter cache.' );
			return new WP_Error( 'color_cache_failed', __( 'Color filtering is temporarily unavailable.', 'pods-block-suite' ) );
		}
		$key    = pods_block_suite_color_cache_key( $table, $field );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['version'], $cached['colors'] ) && $version === $cached['version'] && is_array( $cached['colors'] ) ) {
			return $cached['colors'];
		}
	}
	if ( $is_meta ) {
		$sql = $wpdb->prepare( "SELECT DISTINCT meta_value FROM `{$table}` WHERE meta_key = %s AND meta_value <> ''", $field );
	} else {
		$sql = "SELECT DISTINCT `{$field}` FROM `{$table}` WHERE `{$field}` <> ''";
	}
	$values = $wpdb->get_col( $sql );
	if ( '' !== $wpdb->last_error ) {
		error_log( 'PODS Block Suite color filter query failed: ' . $wpdb->last_error );
		return new WP_Error( 'color_query_failed', __( 'Color filtering is temporarily unavailable.', 'pods-block-suite' ) );
	}
	$colors = array();
	foreach ( $values as $value ) {
		$hsl = pods_block_suite_hex_to_hsl( $value );
		if ( null !== $hsl ) {
			$colors[ $value ] = $hsl;
		}
	}
	if ( $cacheable ) {
		set_transient( $key, array( 'version' => $version, 'colors' => $colors ), HOUR_IN_SECONDS );
	}
	return $colors;
}
