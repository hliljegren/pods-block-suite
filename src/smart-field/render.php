<?php
/**
 * Renders the PODS Smart Field block on the frontend and inside the editor's ServerSideRender.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Empty for dynamic leaf blocks.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods' ) ) {
	return;
}

$selected_pod          = isset( $attributes['selectedPod'] ) ? sanitize_key( $attributes['selectedPod'] ) : '';
$field_name            = isset( $attributes['fieldName'] ) ? sanitize_text_field( $attributes['fieldName'] ) : '';
$display_as            = isset( $attributes['displayAs'] ) ? sanitize_key( $attributes['displayAs'] ) : 'auto';
$layout_mode           = isset( $attributes['layoutMode'] ) ? sanitize_key( $attributes['layoutMode'] ) : 'column';
$image_size            = isset( $attributes['imageSize'] ) ? sanitize_key( $attributes['imageSize'] ) : 'large';
$link_to_media         = ! empty( $attributes['linkToMedia'] );
$link_to_related       = isset( $attributes['linkToRelated'] ) ? (bool) $attributes['linkToRelated'] : true;
$related_display_field = ! empty( $attributes['relatedDisplayField'] ) ? sanitize_text_field( $attributes['relatedDisplayField'] ) : 'post_title';
$render_as_link        = isset( $attributes['renderAsLink'] ) ? (bool) $attributes['renderAsLink'] : true;
$link_text             = isset( $attributes['linkText'] ) ? sanitize_text_field( $attributes['linkText'] ) : '';
$link_new_tab          = ! empty( $attributes['linkNewTab'] );
$color_shape           = isset( $attributes['colorShape'] ) ? sanitize_key( $attributes['colorShape'] ) : 'square';
$color_size            = isset( $attributes['colorSize'] ) ? max( 12, min( 200, absint( $attributes['colorSize'] ) ) ) : 32;
$show_color_label      = ! empty( $attributes['showColorLabel'] );
$html_tag              = isset( $attributes['htmlTag'] ) ? sanitize_key( $attributes['htmlTag'] ) : 'div';

$allowed_tags = array( 'div', 'p', 'h2', 'h3', 'span' );
if ( ! in_array( $html_tag, $allowed_tags, true ) ) {
	$html_tag = 'div';
}

$allowed_layouts = array( 'column', 'row', 'ul', 'ol' );
if ( ! in_array( $layout_mode, $allowed_layouts, true ) ) {
	$layout_mode = 'column';
}

$allowed_shapes = array( 'square', 'circle', 'text' );
if ( ! in_array( $color_shape, $allowed_shapes, true ) ) {
	$color_shape = 'square';
}

if ( empty( $field_name ) ) {
	return;
}

$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

// 1. Retrieve Post ID and Post Type from block context (or ServerSideRender query args)
$post_id   = $block->context['postId'] ?? 0;
$post_type = $block->context['postType'] ?? '';

if ( ! $post_id && isset( $_GET['context_post_id'] ) ) {
	$post_id = absint( $_GET['context_post_id'] );
}
if ( ! $post_type && isset( $_GET['context_post_type'] ) ) {
	$post_type = sanitize_key( $_GET['context_post_type'] );
}

if ( ! $post_id ) {
	$post_id = get_the_ID();
}
if ( $post_id && ! $post_type ) {
	$post_type = get_post_type( $post_id );
}

// Editor Preview Fallback: If inside a list container (where current page post_type differs from selectedPod),
// fetch a sample published item from selectedPod so the editor shows a live preview.
if ( $is_editor_preview && ! empty( $selected_pod ) && $post_type !== $selected_pod ) {
	$query_args = array(
		'post_type'      => $selected_pod,
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	);

	if ( 'post_thumbnail' === $field_name ) {
		$query_args['meta_key'] = '_thumbnail_id';
	}

	$sample_posts = get_posts( $query_args );
	if ( empty( $sample_posts ) && isset( $query_args['meta_key'] ) ) {
		unset( $query_args['meta_key'] );
		$sample_posts = get_posts( $query_args );
	}

	if ( ! empty( $sample_posts ) ) {
		$post_id   = $sample_posts[0]->ID;
		$post_type = $selected_pod;
	}
}

if ( ! $post_id || ! $post_type ) {
	return;
}

// 2. Initialize the PODS object
$pod = pods( $post_type, $post_id );

// 3. Detect field type and determine the effective display mode
$field_info = ( $pod && $pod->exists() ) ? $pod->fields( $field_name ) : null;
$field_type = '';

if ( is_array( $field_info ) && isset( $field_info['type'] ) ) {
	$field_type = $field_info['type'];
} elseif ( is_object( $field_info ) ) {
	if ( method_exists( $field_info, 'get_type' ) ) {
		$field_type = $field_info->get_type();
	} elseif ( isset( $field_info['type'] ) ) {
		$field_type = $field_info['type'];
	}
}

$effective_mode = 'text';
if ( 'auto' !== $display_as && in_array( $display_as, array( 'media', 'oembed', 'color', 'relation', 'link', 'text' ), true ) ) {
	$effective_mode = $display_as;
} elseif ( 'post_thumbnail' === $field_name || in_array( $field_type, array( 'file', 'media', 'avatar' ), true ) ) {
	$effective_mode = 'media';
} elseif ( 'oembed' === $field_type ) {
	$effective_mode = 'oembed';
} elseif ( 'color' === $field_type ) {
	$effective_mode = 'color';
} elseif ( 'pick' === $field_type ) {
	$effective_mode = 'relation';
} elseif ( 'website' === $field_type || 'permalink' === $field_name ) {
	$effective_mode = 'link';
}

/**
 * Helper closure to wrap an array of HTML items into either a semantic list (<ul>/<ol>) or Flexbox items.
 */
$format_items_output = function ( array $items ) use ( $layout_mode ) {
	if ( empty( $items ) ) {
		return '';
	}

	if ( 'ul' === $layout_mode || 'ol' === $layout_mode ) {
		$list_items = array_map(
			function ( $item ) {
				return '<li>' . $item . '</li>';
			},
			$items
		);
		return sprintf( '<%1$s class="pods-smart-field-list">%2$s</%1$s>', $layout_mode, implode( "\n", $list_items ) );
	}

	if ( count( $items ) > 1 ) {
		$wrapped = array_map(
			function ( $item ) {
				return '<div class="pods-smart-field-item">' . $item . '</div>';
			},
			$items
		);
		return implode( "\n", $wrapped );
	}

	return $items[0];
};

$rendered_content = '';

// 4A. MODE: MEDIA (Featured Image, PODS Images, Video, Audio, Files)
if ( 'media' === $effective_mode ) {
	$attachment_ids = array();

	if ( 'post_thumbnail' === $field_name ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$attachment_ids[] = absint( $thumb_id );
		}
	} elseif ( $pod && $pod->exists() ) {
		$raw_files = $pod->field( $field_name );

		if ( is_numeric( $raw_files ) ) {
			$attachment_ids[] = absint( $raw_files );
		} elseif ( is_array( $raw_files ) ) {
			if ( isset( $raw_files['ID'] ) ) {
				$attachment_ids[] = absint( $raw_files['ID'] );
			} else {
				foreach ( $raw_files as $file_item ) {
					if ( is_array( $file_item ) && isset( $file_item['ID'] ) ) {
						$attachment_ids[] = absint( $file_item['ID'] );
					} elseif ( is_numeric( $file_item ) ) {
						$attachment_ids[] = absint( $file_item );
					} elseif ( is_string( $file_item ) && filter_var( $file_item, FILTER_VALIDATE_URL ) ) {
						$found_id = attachment_url_to_postid( $file_item );
						if ( $found_id ) {
							$attachment_ids[] = $found_id;
						}
					}
				}
			}
		}
	}

	$attachment_ids = array_filter( $attachment_ids );

	if ( ! empty( $attachment_ids ) ) {
		$items_html = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$mime_type = get_post_mime_type( $attachment_id );
			$file_url  = wp_get_attachment_url( $attachment_id );
			$item_html = '';

			if ( $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
				$img_html = wp_get_attachment_image( $attachment_id, $image_size );
				if ( $link_to_media && $file_url ) {
					$item_html = sprintf(
						'<a href="%s" class="pods-smart-field-media-link">%s</a>',
						esc_url( $file_url ),
						$img_html
					);
				} else {
					$item_html = $img_html;
				}
			} elseif ( $mime_type && strpos( $mime_type, 'video/' ) === 0 && $file_url ) {
				$item_html = sprintf( '<video controls preload="metadata" src="%s"></video>', esc_url( $file_url ) );
			} elseif ( $mime_type && strpos( $mime_type, 'audio/' ) === 0 && $file_url ) {
				$item_html = sprintf( '<audio controls preload="metadata" src="%s"></audio>', esc_url( $file_url ) );
			} elseif ( $file_url ) {
				$file_title = get_the_title( $attachment_id ) ?: basename( $file_url );
				$item_html  = sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $file_url ),
					esc_html( $file_title )
				);
			}

			if ( ! empty( $item_html ) ) {
				$items_html[] = $item_html;
			}
		}

		$rendered_content = $format_items_output( $items_html );
	}

// 4B. MODE: OEMBED (YouTube, Vimeo, SoundCloud, etc.)
} elseif ( 'oembed' === $effective_mode && $pod && $pod->exists() ) {
	$raw_embed = $pod->field( $field_name );
	if ( is_array( $raw_embed ) ) {
		$raw_embed = reset( $raw_embed );
	}

	$embed_url = is_string( $raw_embed ) ? trim( $raw_embed ) : '';

	if ( ! empty( $embed_url ) ) {
		$embed_html = wp_oembed_get( $embed_url );
		if ( $embed_html ) {
			$rendered_content = sprintf( '<div class="pods-smart-field-oembed">%s</div>', $embed_html );
		} else {
			// Fallback to clickable link if URL does not match a known oEmbed provider
			$rendered_content = sprintf(
				'<a href="%1$s" class="pods-smart-field-url-link" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $embed_url ),
				esc_html( $embed_url )
			);
		}
	}

// 4C. MODE: COLOR PICKER (Square swatch, Circle swatch, or Hex text)
} elseif ( 'color' === $effective_mode && $pod && $pod->exists() ) {
	$raw_color = $pod->field( $field_name );
	if ( is_array( $raw_color ) ) {
		$raw_color = reset( $raw_color );
	}

	$color_str = is_string( $raw_color ) ? trim( $raw_color ) : '';
	if ( '' !== $color_str && '#' !== $color_str[0] ) {
		$color_str = '#' . $color_str;
	}

	$sanitized_hex = sanitize_hex_color( $color_str );

	if ( ! empty( $sanitized_hex ) ) {
		if ( 'text' === $color_shape ) {
			$rendered_content = sprintf(
				'<span class="pods-smart-field-color-label">%s</span>',
				esc_html( $sanitized_hex )
			);
		} else {
			$swatch_html = sprintf(
				'<span class="pods-smart-field-color-swatch is-shape-%1$s" style="background-color:%2$s;width:%3$dpx;height:%3$dpx;" title="%2$s" aria-label="%2$s"></span>',
				esc_attr( $color_shape ),
				esc_attr( $sanitized_hex ),
				$color_size
			);

			$label_html = $show_color_label
				? sprintf( '<span class="pods-smart-field-color-label">%s</span>', esc_html( $sanitized_hex ) )
				: '';

			$rendered_content = sprintf(
				'<span class="pods-smart-field-color-wrap">%1$s%2$s</span>',
				$swatch_html,
				$label_html
			);
		}
	}

// 4D. MODE: RELATIONSHIP (Single and one-to-many relations)
} elseif ( 'relation' === $effective_mode && $pod && $pod->exists() ) {
	$raw_related = $pod->field( $field_name );
	$related_ids = array();

	if ( is_numeric( $raw_related ) ) {
		$related_ids[] = absint( $raw_related );
	} elseif ( is_array( $raw_related ) ) {
		if ( isset( $raw_related['ID'] ) ) {
			$related_ids[] = absint( $raw_related['ID'] );
		} else {
			foreach ( $raw_related as $rel_item ) {
				if ( is_array( $rel_item ) && isset( $rel_item['ID'] ) ) {
					$related_ids[] = absint( $rel_item['ID'] );
				} elseif ( is_numeric( $rel_item ) ) {
					$related_ids[] = absint( $rel_item );
				}
			}
		}
	}

	$related_ids = array_filter( $related_ids );

	if ( ! empty( $related_ids ) ) {
		$items_html = array();

		foreach ( $related_ids as $rel_id ) {
			$rel_post_type = get_post_type( $rel_id );
			if ( ! $rel_post_type ) {
				continue;
			}

			$label_text = '';
			if ( 'post_title' === $related_display_field ) {
				$label_text = get_the_title( $rel_id );
			} else {
				$rel_pod = pods( $rel_post_type, $rel_id );
				if ( $rel_pod && $rel_pod->exists() ) {
					$label_text = $rel_pod->display( $related_display_field );
				}
				if ( empty( $label_text ) ) {
					$label_text = get_the_title( $rel_id );
				}
			}

			if ( empty( $label_text ) ) {
				continue;
			}

			$permalink = $link_to_related ? get_permalink( $rel_id ) : '';
			if ( $link_to_related && $permalink ) {
				$items_html[] = sprintf(
					'<a href="%s" class="pods-smart-field-relation-link">%s</a>',
					esc_url( $permalink ),
					wp_kses_post( $label_text )
				);
			} else {
				$items_html[] = sprintf(
					'<span class="pods-smart-field-relation-text">%s</span>',
					wp_kses_post( $label_text )
				);
			}
		}

		$rendered_content = $format_items_output( $items_html );
	}

// 4E. MODE: URL / WEBSITE LINK
} elseif ( 'link' === $effective_mode ) {
	$raw_url = '';
	if ( 'permalink' === $field_name ) {
		$raw_url = get_permalink( $post_id );
	} elseif ( $pod && $pod->exists() ) {
		$raw_url = $pod->field( $field_name );
	}

	if ( is_array( $raw_url ) ) {
		$raw_url = reset( $raw_url );
	}

	$url_string = is_string( $raw_url ) ? trim( $raw_url ) : '';

	if ( ! empty( $url_string ) ) {
		if ( $render_as_link ) {
			$final_label  = ! empty( $link_text ) ? $link_text : $url_string;
			$target_attrs = $link_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

			$rendered_content = sprintf(
				'<a href="%1$s" class="pods-smart-field-url-link"%2$s>%3$s</a>',
				esc_url( $url_string ),
				$target_attrs,
				esc_html( $final_label )
			);
		} else {
			$rendered_content = sprintf(
				'<%1$s class="pods-smart-field-text">%2$s</%1$s>',
				$html_tag,
				esc_html( $url_string )
			);
		}
	}

// 4F. MODE: STANDARD WORDPRESS FIELDS & PODS TEXT/GENERAL FIELDS
} else {
	$display_value = '';

	switch ( $field_name ) {
		case 'post_title':
			$display_value = get_the_title( $post_id );
			break;
		case 'post_content':
			$raw_content   = get_post_field( 'post_content', $post_id );
			$display_value = apply_filters( 'the_content', $raw_content );
			break;
		case 'post_excerpt':
			$display_value = has_excerpt( $post_id )
				? get_the_excerpt( $post_id )
				: wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30 );
			break;
		case 'post_date':
			$display_value = get_the_date( '', $post_id );
			break;
		case 'ID':
			$display_value = (string) $post_id;
			break;
		case 'post_author':
			$author_id     = get_post_field( 'post_author', $post_id );
			$display_value = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
			break;
		case 'permalink':
			$display_value = get_permalink( $post_id );
			break;
		default:
			if ( $pod && $pod->exists() ) {
				$display_value = $pod->display( $field_name );
			}
			break;
	}

	if ( ! empty( $display_value ) || '0' === (string) $display_value ) {
		$rendered_content = sprintf(
			'<%1$s class="pods-smart-field-text">%2$s</%1$s>',
			$html_tag,
			wp_kses_post( $display_value )
		);
	}
}

// 5. Empty state handling
if ( empty( $rendered_content ) ) {
	if ( $is_editor_preview ) {
		echo '<div class="pods-smart-field-empty" style="opacity:0.6;font-style:italic;">' .
			sprintf(
				/* translators: %s: PODS field slug */
				esc_html__( 'PODS Smart Field: "%s" is empty or could not be found.', 'pods-block-suite' ),
				esc_html( $field_name )
			) .
			'</div>';
	}
	return;
}

// 6. Render outer wrapper with active layout class
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => sprintf( 'pods-smart-field is-layout-%s', esc_attr( $layout_mode ) ),
	)
);

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes,
	$rendered_content
);