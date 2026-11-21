<?php
/**
 * Renders the PODS Related Item Container block on the frontend.
 *
 * @var array    $attributes Saved block attributes.
 * @var string   $content    Fallback inner content.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods' ) ) {
	return;
}

$raw_rel_field      = ! empty( $attributes['relationshipField'] ) ? $attributes['relationshipField'] : ( $attributes['relationField'] ?? '' );
$relationship_field = sanitize_key( $raw_rel_field );
$raw_limit          = isset( $attributes['limit'] ) && $attributes['limit'] > 0 ? $attributes['limit'] : ( $attributes['maxItems'] ?? 0 );
$limit              = absint( $raw_limit );
$order_by           = isset( $attributes['orderBy'] ) ? sanitize_text_field( $attributes['orderBy'] ) : 'post_date';
$raw_order_dir      = isset( $attributes['orderDir'] ) ? strtoupper( $attributes['orderDir'] ) : 'DESC';
$order_dir          = in_array( $raw_order_dir, array( 'ASC', 'DESC', 'RAND' ), true ) ? $raw_order_dir : 'DESC';

$layout          = pods_block_suite_sanitize_layout_attributes( $attributes, 'column' );
$flex_direction  = $layout['flex_direction'];
$justify_content = $layout['justify_content'];
$columns         = $layout['columns'];
$link_to_post    = $layout['link_to_post'];

if ( empty( $relationship_field ) ) {
	return;
}

$parent_id   = $block->context['postId'] ?? get_the_ID();
$parent_type = $block->context['postType'] ?? ( $parent_id ? get_post_type( $parent_id ) : '' );

if ( ! $parent_id || ! $parent_type ) {
	return;
}

$pod = pods( $parent_type, $parent_id );
if ( ! $pod || ! $pod->exists() ) {
	return;
}

$raw_related = $pod->field( $relationship_field );
$related_ids = array();

if ( is_numeric( $raw_related ) ) {
	$related_ids[] = absint( $raw_related );
} elseif ( is_array( $raw_related ) ) {
	if ( isset( $raw_related['ID'] ) ) {
		$related_ids[] = absint( $raw_related['ID'] );
	} else {
		foreach ( $raw_related as $item ) {
			if ( is_array( $item ) && isset( $item['ID'] ) ) {
				$related_ids[] = absint( $item['ID'] );
			} elseif ( is_numeric( $item ) ) {
				$related_ids[] = absint( $item );
			}
		}
	}
}

$related_ids = array_values( array_filter( $related_ids ) );

if ( empty( $related_ids ) ) {
	return;
}

// Sort related item IDs BEFORE applying the limit
if ( 'RAND' === $order_dir ) {
	shuffle( $related_ids );
} else {
	$clean_order_by = preg_replace( '/^t\./', '', $order_by );
	$first_type     = get_post_type( $related_ids[0] );
	$target_pod     = $first_type ? pods( $first_type ) : null;
	$field_type     = pods_block_suite_get_field_type( $target_pod, $clean_order_by );

	$is_numeric_sort = in_array( $clean_order_by, array( 'ID', 'menu_order' ), true )
		|| in_array( $field_type, array( 'number', 'currency' ), true );

	$sort_values = array();
	foreach ( $related_ids as $rid ) {
		$r_post = get_post( $rid );
		if ( ! $r_post ) {
			$sort_values[ $rid ] = '';
			continue;
		}

		switch ( $clean_order_by ) {
			case 'post_date':
				$sort_values[ $rid ] = $r_post->post_date;
				break;
			case 'post_title':
				$sort_values[ $rid ] = $r_post->post_title;
				break;
			case 'post_author':
				$sort_values[ $rid ] = get_the_author_meta( 'display_name', $r_post->post_author );
				break;
			case 'menu_order':
				$sort_values[ $rid ] = (int) $r_post->menu_order;
				break;
			case 'ID':
				$sort_values[ $rid ] = (int) $rid;
				break;
			default:
				$item_pod = pods( $r_post->post_type, $rid );
				$raw_val  = ( $item_pod && $item_pod->exists() ) ? $item_pod->field( $clean_order_by ) : '';
				if ( is_array( $raw_val ) ) {
					$raw_val = $raw_val['post_title'] ?? (string) reset( $raw_val );
				}
				$sort_values[ $rid ] = $is_numeric_sort ? floatval( $raw_val ) : (string) $raw_val;
				break;
		}
	}

	usort(
		$related_ids,
		static function ( $a, $b ) use ( $sort_values, $order_dir, $is_numeric_sort ) {
			$val_a = $sort_values[ $a ] ?? '';
			$val_b = $sort_values[ $b ] ?? '';

			if ( $is_numeric_sort ) {
				$cmp = (float) $val_a <=> (float) $val_b;
			} else {
				$cmp = strnatcasecmp( (string) $val_a, (string) $val_b );
			}

			return ( 'DESC' === $order_dir ) ? -$cmp : $cmp;
		}
	);
}

if ( $limit > 0 ) {
	$related_ids = array_slice( $related_ids, 0, $limit );
}

$items_html = '';

foreach ( $related_ids as $rel_id ) {
	$rel_post_type = get_post_type( $rel_id );
	if ( ! $rel_post_type ) {
		continue;
	}

	$single_item_html = pods_block_suite_render_contextual_inner_blocks( $block, $rel_id, $rel_post_type );

	if ( ! empty( $single_item_html ) ) {
		$permalink = $link_to_post ? get_permalink( $rel_id ) : '';
		if ( $link_to_post && $permalink ) {
			$items_html .= sprintf(
				'<a href="%1$s" class="pods-related-item pods-related-item-link" data-related-id="%2$d">%3$s</a>',
				esc_url( $permalink ),
				$rel_id,
				$single_item_html
			);
		} else {
			$items_html .= sprintf(
				'<div class="pods-related-item" data-related-id="%1$d">%2$s</div>',
				$rel_id,
				$single_item_html
			);
		}
	}
}

if ( empty( $items_html ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pods-related-item-container',
		'style' => sprintf( '--pods-columns: %d; --pods-justify-content: %s;', $columns, esc_attr( $justify_content ) ),
	)
);

printf(
	'<div %1$s><div class="pods-related-items-grid is-direction-%2$s">%3$s</div></div>',
	$wrapper_attributes,
	esc_attr( $flex_direction ),
	$items_html
);