<?php
/**
 * Renders the PODS Item List Container block on the frontend, including visitor filters and pagination.
 *
 * @var array    $attributes Saved block attributes.
 * @var string   $content    Fallback inner content.
 * @var WP_Block $block      Full block instance.
 */

if ( ! function_exists( 'pods' ) ) {
	return;
}

global $wpdb, $post;

$selected_pod      = isset( $attributes['selectedPod'] ) ? sanitize_key( $attributes['selectedPod'] ) : '';
$limit             = isset( $attributes['limit'] ) ? intval( $attributes['limit'] ) : 12;
$order_by          = isset( $attributes['orderBy'] ) ? sanitize_text_field( $attributes['orderBy'] ) : 't.post_date';
$order_dir         = isset( $attributes['orderDir'] ) && 'ASC' === strtoupper( $attributes['orderDir'] ) ? 'ASC' : 'DESC';
$enable_pagination = isset( $attributes['enablePagination'] ) ? (bool) $attributes['enablePagination'] : true;
$filter_fields     = isset( $attributes['filterFields'] ) && is_array( $attributes['filterFields'] ) ? array_map( 'sanitize_key', $attributes['filterFields'] ) : array();
$enable_search     = ! empty( $attributes['enableSearch'] );
$flex_direction    = isset( $attributes['flexDirection'] ) && 'column' === $attributes['flexDirection'] ? 'column' : 'row';
$columns           = isset( $attributes['columns'] ) ? max( 1, min( 6, absint( $attributes['columns'] ) ) ) : 3;
$link_to_post      = ! empty( $attributes['linkToPost'] );

if ( empty( $selected_pod ) ) {
	return;
}

// Validate allowed orderBy clauses for security
$allowed_order_by = array( 't.post_date', 't.post_title', 't.menu_order', 't.ID', 'RAND()' );
if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
	$order_by = 't.post_date';
}

// Initialize POD object to inspect schema & field definitions
$pod = pods( $selected_pod );
if ( ! $pod || ! $pod->valid() ) {
	return;
}

// 1. PROCESS INCOMING VISITOR FILTER VALUES & BUILD WHERE CLAUSES
$where_clauses   = array();
$active_filters  = array();
$has_active_form = false;

// Ensure only published items are fetched when the POD is post-type based
if ( post_type_exists( $selected_pod ) ) {
	$where_clauses[] = "t.post_status = 'publish'";
}

$search_param = 'pods_search_' . $selected_pod;
$search_value = isset( $_GET[ $search_param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $search_param ] ) ) : '';

if ( $enable_search && '' !== $search_value ) {
	$has_active_form = true;
	$like_term       = '%' . $wpdb->esc_like( $search_value ) . '%';
	$where_clauses[] = $wpdb->prepare(
		'( t.post_title LIKE %s OR t.post_content LIKE %s )',
		$like_term,
		$like_term
	);
}

$allowed_operators = array( '=', '>', '>=', '<', '<=' );

foreach ( $filter_fields as $f_slug ) {
	$field_obj = $pod->fields( $f_slug );
	if ( ! $field_obj ) {
		continue;
	}

	$f_type = is_object( $field_obj ) && method_exists( $field_obj, 'get_type' )
		? $field_obj->get_type()
		: ( $field_obj['type'] ?? 'text' );

	$param_name = 'pods_filter_' . $selected_pod . '_' . $f_slug;
	$op_param   = $param_name . '_op';

	$raw_val = isset( $_GET[ $param_name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) ) : '';
	$raw_op  = isset( $_GET[ $op_param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $op_param ] ) ) : '=';

	if ( ! in_array( $raw_op, $allowed_operators, true ) ) {
		$raw_op = '=';
	}

	$active_filters[ $f_slug ] = array(
		'value' => $raw_val,
		'op'    => $raw_op,
	);

	if ( '' === $raw_val ) {
		continue;
	}

	$has_active_form = true;

	if ( in_array( $f_type, array( 'number', 'currency' ), true ) && is_numeric( $raw_val ) ) {
		$num_val         = floatval( $raw_val );
		$where_clauses[] = $wpdb->prepare(
			"CAST( {$f_slug}.meta_value AS DECIMAL(12,2) ) {$raw_op} %f",
			$num_val
		);
	} elseif ( 'pick' === $f_type ) {
		$pick_object = is_object( $field_obj ) ? $field_obj->get_arg( 'pick_object', '' ) : ( $field_obj['pick_object'] ?? '' );
		if ( 'post_type' === $pick_object || 'taxonomy' === $pick_object || 'user' === $pick_object ) {
			$where_clauses[] = $wpdb->prepare( "{$f_slug}.ID = %d", absint( $raw_val ) );
		} else {
			$where_clauses[] = $wpdb->prepare( "{$f_slug}.meta_value = %s", $raw_val );
		}
	} elseif ( 'taxonomy' === $f_type ) {
		$where_clauses[] = $wpdb->prepare( "{$f_slug}.term_id = %d", absint( $raw_val ) );
	} elseif ( 'boolean' === $f_type ) {
		$bool_val        = ( '1' === $raw_val ) ? 1 : 0;
		$where_clauses[] = $wpdb->prepare( "{$f_slug}.meta_value = %d", $bool_val );
	} else {
		$like_val        = '%' . $wpdb->esc_like( $raw_val ) . '%';
		$where_clauses[] = $wpdb->prepare( "{$f_slug}.meta_value LIKE %s", $like_val );
	}
}

// 2. PAGINATION & QUERY EXECUTION
$page_param   = 'pods_page_' . $selected_pod;
$current_page = ( $enable_pagination && isset( $_GET[ $page_param ] ) ) ? max( 1, absint( $_GET[ $page_param ] ) ) : 1;

$find_params = array(
	'limit'      => $limit,
	'page'       => $current_page,
	'pagination' => $enable_pagination,
	'orderby'    => ( 'RAND()' === $order_by ) ? 'RAND()' : "{$order_by} {$order_dir}",
);

if ( ! empty( $where_clauses ) ) {
	$find_params['where'] = implode( ' AND ', $where_clauses );
}

$pod->find( $find_params );

// 3. BUILD FRONTEND VISITOR FILTER FORM HTML (IF ENABLED)
$filter_form_html = '';
if ( ! empty( $filter_fields ) || $enable_search ) {
	ob_start();
	?>
	<form method="get" class="pods-list-filters-form" action="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>">
		<?php
		foreach ( $_GET as $g_key => $g_val ) {
			if ( is_string( $g_val ) && strpos( $g_key, 'pods_filter_' . $selected_pod ) !== 0 && $g_key !== $search_param && $g_key !== $page_param ) {
				printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $g_key ), esc_attr( wp_unslash( $g_val ) ) );
			}
		}

		foreach ( $filter_fields as $f_slug ) {
			$field_obj = $pod->fields( $f_slug );
			if ( ! $field_obj ) {
				continue;
			}

			$f_label = is_object( $field_obj ) && method_exists( $field_obj, 'get_label' )
				? $field_obj->get_label()
				: ( $field_obj['label'] ?? $f_slug );

			$f_type = is_object( $field_obj ) && method_exists( $field_obj, 'get_type' )
				? $field_obj->get_type()
				: ( $field_obj['type'] ?? 'text' );

			$param_name = 'pods_filter_' . $selected_pod . '_' . $f_slug;
			$op_param   = $param_name . '_op';
			$cur_val    = $active_filters[ $f_slug ]['value'] ?? '';
			$cur_op     = $active_filters[ $f_slug ]['op'] ?? '=';
			$input_id   = 'filter-' . esc_attr( $selected_pod . '-' . $f_slug );
			?>
			<div class="pods-filter-control">
				<label for="<?php echo $input_id; ?>"><?php echo esc_html( $f_label ); ?></label>

				<?php if ( in_array( $f_type, array( 'pick', 'taxonomy', 'boolean' ), true ) ) : ?>
					<?php
					$options = array();

					if ( 'boolean' === $f_type ) {
						$options = array(
							'1' => __( 'Yes', 'pods-block-suite' ),
							'0' => __( 'No', 'pods-block-suite' ),
						);
					} elseif ( 'pick' === $f_type ) {
						$pick_object = is_object( $field_obj ) ? $field_obj->get_arg( 'pick_object', '' ) : ( $field_obj['pick_object'] ?? '' );
						$pick_val    = is_object( $field_obj ) ? $field_obj->get_arg( 'pick_val', '' ) : ( $field_obj['pick_val'] ?? '' );

						if ( 'custom-simple' === $pick_object ) {
							$custom_raw = is_object( $field_obj ) ? $field_obj->get_arg( 'pick_custom', '' ) : ( $field_obj['pick_custom'] ?? '' );
							$lines      = preg_split( '/\r\n|\r|\n/', (string) $custom_raw );
							foreach ( $lines as $line ) {
								$line = trim( $line );
								if ( '' === $line ) {
									continue;
								}
								if ( false !== strpos( $line, '|' ) ) {
									list( $opt_val, $opt_lbl ) = explode( '|', $line, 2 );
									$options[ trim( $opt_val ) ] = trim( $opt_lbl );
								} else {
									$options[ $line ] = $line;
								}
							}
						} elseif ( 'post_type' === $pick_object && ! empty( $pick_val ) ) {
							$rel_posts = get_posts(
								array(
									'post_type'      => $pick_val,
									'posts_per_page' => 200,
									'orderby'        => 'title',
									'order'          => 'ASC',
								)
							);
							foreach ( $rel_posts as $rp ) {
								$options[ (string) $rp->ID ] = $rp->post_title;
							}
						} elseif ( 'taxonomy' === $pick_object && ! empty( $pick_val ) ) {
							$terms = get_terms( array( 'taxonomy' => $pick_val, 'hide_empty' => true ) );
							if ( ! is_wp_error( $terms ) ) {
								foreach ( $terms as $term ) {
									$options[ (string) $term->term_id ] = $term->name;
								}
							}
						}

						if ( empty( $options ) ) {
							$distinct_vals = $wpdb->get_col(
								$wpdb->prepare(
									"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
									 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
									 WHERE p.post_type = %s AND p.post_status = 'publish'
									 AND pm.meta_key = %s AND pm.meta_value != ''
									 ORDER BY pm.meta_value ASC",
									$selected_pod,
									$f_slug
								)
							);
							foreach ( $distinct_vals as $dv ) {
								$options[ $dv ] = $dv;
							}
						}
					} elseif ( 'taxonomy' === $f_type ) {
						$terms = get_terms( array( 'taxonomy' => $f_slug, 'hide_empty' => true ) );
						if ( ! is_wp_error( $terms ) ) {
							foreach ( $terms as $term ) {
								$options[ (string) $term->term_id ] = $term->name;
							}
						}
					}
					?>
					<select id="<?php echo $input_id; ?>" name="<?php echo esc_attr( $param_name ); ?>" onchange="this.form.submit()">
						<option value=""><?php echo esc_html( sprintf( __( 'All (%s)', 'pods-block-suite' ), $f_label ) ); ?></option>
						<?php foreach ( $options as $opt_value => $opt_label ) : ?>
							<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( (string) $cur_val, (string) $opt_value ); ?>>
								<?php echo esc_html( $opt_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

				<?php elseif ( in_array( $f_type, array( 'number', 'currency' ), true ) ) : ?>
					<div class="pods-filter-numeric-group">
						<select class="pods-filter-operator" name="<?php echo esc_attr( $op_param ); ?>" aria-label="<?php esc_attr_e( 'Comparison operator', 'pods-block-suite' ); ?>">
							<option value="=" <?php selected( $cur_op, '=' ); ?>>=</option>
							<option value=">" <?php selected( $cur_op, '>' ); ?>>&gt;</option>
							<option value=">=" <?php selected( $cur_op, '>=' ); ?>>&ge;</option>
							<option value="<" <?php selected( $cur_op, '<' ); ?>>&lt;</option>
							<option value="<=" <?php selected( $cur_op, '<=' ); ?>>&le;</option>
						</select>
						<input
							id="<?php echo $input_id; ?>"
							type="number"
							step="any"
							name="<?php echo esc_attr( $param_name ); ?>"
							value="<?php echo esc_attr( $cur_val ); ?>"
							placeholder="<?php esc_attr_e( 'Value...', 'pods-block-suite' ); ?>"
						/>
					</div>

				<?php else : ?>
					<input
						id="<?php echo $input_id; ?>"
						type="text"
						name="<?php echo esc_attr( $param_name ); ?>"
						value="<?php echo esc_attr( $cur_val ); ?>"
						placeholder="<?php echo esc_attr( sprintf( __( 'Filter %s...', 'pods-block-suite' ), $f_label ) ); ?>"
					/>
				<?php endif; ?>
			</div>
			<?php
		}

		if ( $enable_search ) :
			?>
			<div class="pods-filter-control">
				<label for="search-<?php echo esc_attr( $selected_pod ); ?>"><?php esc_html_e( 'Keyword Search', 'pods-block-suite' ); ?></label>
				<input
					id="search-<?php echo esc_attr( $selected_pod ); ?>"
					type="text"
					name="<?php echo esc_attr( $search_param ); ?>"
					value="<?php echo esc_attr( $search_value ); ?>"
					placeholder="<?php esc_attr_e( 'Search...', 'pods-block-suite' ); ?>"
				/>
			</div>
		<?php endif; ?>

		<div class="pods-filter-actions">
			<button type="submit" class="pods-filter-submit"><?php esc_html_e( 'Filter', 'pods-block-suite' ); ?></button>
			<?php if ( $has_active_form ) : ?>
				<?php
				$reset_args = array( $search_param, $page_param );
				foreach ( $filter_fields as $f_slug ) {
					$reset_args[] = 'pods_filter_' . $selected_pod . '_' . $f_slug;
					$reset_args[] = 'pods_filter_' . $selected_pod . '_' . $f_slug . '_op';
				}
				?>
				<a href="<?php echo esc_url( remove_query_arg( $reset_args ) ); ?>" class="pods-filter-reset">
					<?php esc_html_e( 'Reset filters', 'pods-block-suite' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>
	<?php
	$filter_form_html = ob_get_clean();
}

// 4. LOOP THROUGH MATCHING POD ITEMS AND RENDER INNER BLOCKS
$items_html    = '';
$original_post = $post;

if ( $pod->total() > 0 ) {
	while ( $pod->fetch() ) {
		$item_id        = absint( $pod->id() );
		$item_post_type = get_post_type( $item_id ) ?: $selected_pod;

		// Set global postdata if this is a standard WP post type
		$post_obj = get_post( $item_id );
		if ( $post_obj ) {
			$post = $post_obj;
			setup_postdata( $post );
		}

		// Inject postId and postType into ALL nested inner blocks via render_block_context
		$context_filter = static function ( $context ) use ( $item_id, $item_post_type ) {
			$context['postId']   = $item_id;
			$context['postType'] = $item_post_type;
			return $context;
		};

		add_filter( 'render_block_context', $context_filter, 10, 1 );

		$single_item_html = '';
		if ( ! empty( $block->inner_blocks ) ) {
			foreach ( $block->inner_blocks as $inner_block ) {
				// Re-instantiate WP_Block so render_block_context runs for it and all nested children
				$contextual_block  = new WP_Block(
					$inner_block->parsed_block,
					array(
						'postId'   => $item_id,
						'postType' => $item_post_type,
					)
				);
				$single_item_html .= $contextual_block->render();
			}
		}

		remove_filter( 'render_block_context', $context_filter, 10 );

		if ( ! empty( $single_item_html ) ) {
			$permalink = $link_to_post ? get_permalink( $item_id ) : '';
			if ( $link_to_post && $permalink ) {
				$items_html .= sprintf(
					'<a href="%1$s" class="pods-list-item pods-list-item-link" data-item-id="%2$d">%3$s</a>',
					esc_url( $permalink ),
					$item_id,
					$single_item_html
				);
			} else {
				$items_html .= sprintf(
					'<div class="pods-list-item" data-item-id="%1$d">%2$s</div>',
					$item_id,
					$single_item_html
				);
			}
		}
	}

	// Restore original global $post
	$post = $original_post;
	if ( $original_post ) {
		setup_postdata( $original_post );
	} else {
		wp_reset_postdata();
	}
} else {
	$items_html = '<p class="pods-list-empty">' . esc_html__( 'No items matched your filter criteria.', 'pods-block-suite' ) . '</p>';
}

// 5. BUILD PAGINATION HTML
$pagination_html = '';
if ( $enable_pagination && $limit > 0 ) {
	$total_found = $pod->total_found();
	$total_pages = (int) ceil( $total_found / $limit );

	if ( $total_pages > 1 ) {
		$prev_url = ( $current_page > 1 ) ? add_query_arg( $page_param, $current_page - 1 ) : '';
		$next_url = ( $current_page < $total_pages ) ? add_query_arg( $page_param, $current_page + 1 ) : '';

		ob_start();
		?>
		<nav class="pods-list-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'pods-block-suite' ); ?>">
			<span class="pods-pagination-status">
				<?php
				printf(
					/* translators: 1: Current page number, 2: Total number of pages */
					esc_html__( 'Page %1$d of %2$d', 'pods-block-suite' ),
					$current_page,
					$total_pages
				);
				?>
			</span>
			<div class="pods-pagination-links">
				<?php if ( $prev_url ) : ?>
					<a href="<?php echo esc_url( $prev_url ); ?>" class="pods-pagination-prev">&laquo; <?php esc_html_e( 'Previous', 'pods-block-suite' ); ?></a>
				<?php endif; ?>
				<?php if ( $next_url ) : ?>
					<a href="<?php echo esc_url( $next_url ); ?>" class="pods-pagination-next"><?php esc_html_e( 'Next', 'pods-block-suite' ); ?> &raquo;</a>
				<?php endif; ?>
			</div>
		</nav>
		<?php
		$pagination_html = ob_get_clean();
	}
}

// 6. OUTPUT COMPLETE BLOCK
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pods-item-list-container',
		'style' => sprintf( '--pods-columns: %d;', $columns ),
	)
);

printf(
	'<div %1$s>%2$s<div class="pods-item-list-grid is-direction-%3$s">%4$s</div>%5$s</div>',
	$wrapper_attributes,
	$filter_form_html,
	esc_attr( $flex_direction ),
	$items_html,
	$pagination_html
);