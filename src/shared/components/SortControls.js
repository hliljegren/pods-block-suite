import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';

/**
 * Shared SortControls component for PODS container blocks.
 * Renders Sort Direction (DESC, ASC, RAND) and a grouped Order By field selector
 * filtered to sortable WordPress standard fields and PODS custom fields.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.orderBy  Current orderBy field slug.
 * @param {string}   props.orderDir Current sort direction ('DESC', 'ASC', or 'RAND').
 * @param {Object}   props.podData  Schema object for the target POD being sorted (contains fields and standardFields).
 * @param {Function} props.onChange Callback invoked with an object of attribute updates (e.g. setAttributes).
 * @return {JSX.Element} Rendered sort controls.
 */
export function SortControls( {
	orderBy = 'post_date',
	orderDir = 'DESC',
	podData = null,
	onChange,
} ) {
	const sortableWpFields = podData?.standardFields
		? podData.standardFields.filter( ( f ) => f.sortable !== false )
		: [];

	const sortablePodsFields = podData?.fields
		? podData.fields.filter( ( f ) => f.sortable !== false )
		: [];

	// Normalize legacy 't.post_date' values to 'post_date'
	const normalizedOrderBy = orderBy ? orderBy.replace( /^t\./, '' ) : 'post_date';
	const isRandomSort = orderDir === 'RAND' || orderBy === 'RAND()';

	return (
		<>
			<SelectControl
				label={ __( 'Sort Direction', 'pods-block-suite' ) }
				value={ isRandomSort ? 'RAND' : orderDir }
				options={ [
					{
						label: __( 'Descending (DESC / Newest or Z–A)', 'pods-block-suite' ),
						value: 'DESC',
					},
					{
						label: __( 'Ascending (ASC / Oldest or A–Z)', 'pods-block-suite' ),
						value: 'ASC',
					},
					{
						label: __( 'Random Order (RAND)', 'pods-block-suite' ),
						value: 'RAND',
					},
				] }
				onChange={ ( val ) => {
					const updates = { orderDir: val };
					if ( val !== 'RAND' && orderBy === 'RAND()' ) {
						updates.orderBy = 'post_date';
					}
					onChange( updates );
				} }
			/>

			{ ! isRandomSort && (
				<div className="components-base-control">
					<label
						className="components-base-control__label"
						htmlFor="pods-shared-orderby-select"
						style={ { display: 'block', marginBottom: '6px' } }
					>
						{ __( 'Order By', 'pods-block-suite' ) }
					</label>
					<select
						id="pods-shared-orderby-select"
						className="components-select-control__input"
						style={ { width: '100%', height: '36px' } }
						value={ normalizedOrderBy }
						onChange={ ( e ) => onChange( { orderBy: e.target.value } ) }
					>
						<optgroup label={ __( 'WordPress Standard Fields', 'pods-block-suite' ) }>
							{ sortableWpFields.length > 0 ? (
								sortableWpFields.map( ( f ) => (
									<option key={ f.name } value={ f.name }>
										{ f.label }
									</option>
								) )
							) : (
								<>
									<option value="post_date">
										{ __( 'Publish Date (post_date)', 'pods-block-suite' ) }
									</option>
									<option value="post_title">
										{ __( 'Post Title (post_title)', 'pods-block-suite' ) }
									</option>
									<option value="ID">
										{ __( 'Post ID (ID)', 'pods-block-suite' ) }
									</option>
								</>
							) }
						</optgroup>
						{ sortablePodsFields.length > 0 && (
							<optgroup label={ __( 'PODS Fields', 'pods-block-suite' ) }>
								{ sortablePodsFields.map( ( f ) => (
									<option key={ f.name } value={ f.name }>
										{ f.label } [{ f.type }]
									</option>
								) ) }
							</optgroup>
						) }
					</select>
				</div>
			) }
		</>
	);
}