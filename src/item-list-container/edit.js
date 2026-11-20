import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	RangeControl,
	CheckboxControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const {
		selectedPod,
		limit,
		orderBy,
		orderDir,
		enablePagination,
		filterFields,
		enableSearch,
		flexDirection,
		justifyContent,
		columns,
		linkToPost,
	} = attributes;

	const [ schema, setSchema ] = useState( null );
	const [ isLoadingSchema, setIsLoadingSchema ] = useState( true );

	useEffect( () => {
		let isMounted = true;
		apiFetch( { path: '/pods-block-suite/v1/schema' } )
			.then( ( data ) => {
				if ( isMounted ) {
					setSchema( data || {} );
					setIsLoadingSchema( false );
				}
			} )
			.catch( () => {
				if ( isMounted ) {
					setSchema( {} );
					setIsLoadingSchema( false );
				}
			} );

		return () => {
			isMounted = false;
		};
	}, [] );

	const activePodData = schema && selectedPod ? schema[ selectedPod ] : null;
	const availableFields = activePodData ? activePodData.fields : [];
	const sortablePodsFields = availableFields.filter( ( f ) => f.sortable !== false );
	const sortableWpFields = activePodData
		? activePodData.standardFields.filter( ( f ) => f.sortable !== false )
		: [];

	// Normalize legacy 't.post_date' values to 'post_date'
	const normalizedOrderBy = orderBy ? orderBy.replace( /^t\./, '' ) : 'post_date';
	const isRandomSort = orderDir === 'RAND' || orderBy === 'RAND()';

	const podOptions = [
		{ label: __( '— Select POD —', 'pods-block-suite' ), value: '' },
		...( schema
			? Object.values( schema ).map( ( pod ) => ( {
					label: pod.label,
					value: pod.name,
			  } ) )
			: [] ),
	];

	const toggleFilterField = ( fieldSlug, isChecked ) => {
		if ( isChecked ) {
			if ( ! filterFields.includes( fieldSlug ) ) {
				setAttributes( { filterFields: [ ...filterFields, fieldSlug ] } );
			}
		} else {
			setAttributes( {
				filterFields: filterFields.filter( ( item ) => item !== fieldSlug ),
			} );
		}
	};

	const editorLabel = selectedPod
		? sprintf(
				/* translators: 1: POD slug, 2: Number of active visitor filters, 3: Layout description */
				__( 'PODS Item List: %1$s • %2$d filters • %3$s', 'pods-block-suite' ),
				selectedPod,
				filterFields.length + ( enableSearch ? 1 : 0 ),
				flexDirection === 'row'
					? sprintf( __( 'Row (%d cols)', 'pods-block-suite' ), columns )
					: __( 'Column', 'pods-block-suite' )
		  )
		: __( 'PODS Item List Container (select a POD in sidebar)', 'pods-block-suite' );

	const blockProps = useBlockProps( {
		className: 'pods-item-list-container-editor',
		'data-editor-label': editorLabel,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return (
		<>
			<InspectorControls>
				{/* 1. DATA SOURCE & SORTING */}
				<PanelBody
					title={ __( 'PODS Query Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					{ isLoadingSchema ? (
						<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
							<Spinner />
							<span>{ __( 'Loading PODS...', 'pods-block-suite' ) }</span>
						</div>
					) : (
						<SelectControl
							label={ __( 'Select POD to list', 'pods-block-suite' ) }
							value={ selectedPod }
							options={ podOptions }
							onChange={ ( val ) => {
								setAttributes( {
									selectedPod: val,
									filterFields: [],
									orderBy: 'post_date',
								} );
							} }
						/>
					) }

					<TextControl
						label={ __( 'Items per page (Limit)', 'pods-block-suite' ) }
						help={ __(
							'Enter -1 to show all items without pagination.',
							'pods-block-suite'
						) }
						type="number"
						value={ limit }
						onChange={ ( val ) => {
							const parsed = parseInt( val, 10 );
							setAttributes( { limit: isNaN( parsed ) ? 12 : parsed } );
						} }
					/>

					<SelectControl
						label={ __( 'Sort Direction', 'pods-block-suite' ) }
						value={ isRandomSort ? 'RAND' : orderDir }
						options={ [
							{ label: __( 'Descending (DESC / Newest or Z–A)', 'pods-block-suite' ), value: 'DESC' },
							{ label: __( 'Ascending (ASC / Oldest or A–Z)', 'pods-block-suite' ), value: 'ASC' },
							{ label: __( 'Random Order (RAND)', 'pods-block-suite' ), value: 'RAND' },
						] }
						onChange={ ( val ) => {
							const updates = { orderDir: val };
							if ( val !== 'RAND' && orderBy === 'RAND()' ) {
								updates.orderBy = 'post_date';
							}
							setAttributes( updates );
						} }
					/>

					{ ! isRandomSort && (
						<div className="components-base-control">
							<label
								className="components-base-control__label"
								htmlFor="pods-list-orderby-select"
								style={ { display: 'block', marginBottom: '6px' } }
							>
								{ __( 'Order By', 'pods-block-suite' ) }
							</label>
							<select
								id="pods-list-orderby-select"
								className="components-select-control__input"
								style={ { width: '100%', height: '36px' } }
								value={ normalizedOrderBy }
								onChange={ ( e ) => setAttributes( { orderBy: e.target.value } ) }
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
											<option value="post_date">{ __( 'Publish Date (post_date)', 'pods-block-suite' ) }</option>
											<option value="post_title">{ __( 'Post Title (post_title)', 'pods-block-suite' ) }</option>
											<option value="ID">{ __( 'Post ID (ID)', 'pods-block-suite' ) }</option>
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

					<CheckboxControl
						label={ __( 'Enable Pagination', 'pods-block-suite' ) }
						help={ __(
							'Displays Previous / Page X of Y / Next navigation below the list.',
							'pods-block-suite'
						) }
						checked={ enablePagination }
						onChange={ ( val ) => setAttributes( { enablePagination: val } ) }
					/>
				</PanelBody>

				{/* 2. FRONTEND VISITOR FILTERS */}
				<PanelBody
					title={ __( 'Visitor Filters (Frontend)', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<CheckboxControl
						label={ __( 'Enable Keyword Search Box', 'pods-block-suite' ) }
						help={ __(
							'Adds a text search input for visitors to search titles and content.',
							'pods-block-suite'
						) }
						checked={ enableSearch }
						onChange={ ( val ) => setAttributes( { enableSearch: val } ) }
					/>

					{ selectedPod && availableFields.length > 0 && (
						<div style={ { marginTop: '12px' } }>
							<p style={ { fontWeight: 600, marginBottom: '8px' } }>
								{ __( 'Select fields visitors can filter by:', 'pods-block-suite' ) }
							</p>
							{ availableFields.map( ( field ) => (
								<CheckboxControl
									key={ field.name }
									label={ `${ field.label } [${ field.type }]` }
									checked={ filterFields.includes( field.name ) }
									onChange={ ( isChecked ) =>
										toggleFilterField( field.name, isChecked )
									}
								/>
							) ) }
						</div>
					) }

					{ selectedPod && availableFields.length === 0 && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'This POD has no custom fields defined yet.',
								'pods-block-suite'
							) }
						</Notice>
					) }
				</PanelBody>

				{/* 3. LAYOUT & CARD SETTINGS */}
				<PanelBody
					title={ __( 'Layout & Card Settings', 'pods-block-suite' ) }
					initialOpen={ false }
				>
					<CheckboxControl
						label={ __( 'Make entire card clickable (Link to post)', 'pods-block-suite' ) }
						help={ __(
							'Wraps each item in a link pointing to its single post permalink.',
							'pods-block-suite'
						) }
						checked={ linkToPost }
						onChange={ ( val ) => setAttributes( { linkToPost: val } ) }
					/>

					<SelectControl
						label={ __( 'Flex Direction', 'pods-block-suite' ) }
						value={ flexDirection }
						options={ [
							{ label: __( 'Row (horizontal grid)', 'pods-block-suite' ), value: 'row' },
							{ label: __( 'Column (vertical)', 'pods-block-suite' ), value: 'column' },
						] }
						onChange={ ( val ) => setAttributes( { flexDirection: val } ) }
					/>

					<SelectControl
						label={ __( 'Justify Content', 'pods-block-suite' ) }
						help={ __(
							'Controls how items are aligned and distributed along the flex axis.',
							'pods-block-suite'
						) }
						value={ justifyContent }
						options={ [
							{ label: __( 'Center (center)', 'pods-block-suite' ), value: 'center' },
							{ label: __( 'Start / Left (flex-start)', 'pods-block-suite' ), value: 'flex-start' },
							{ label: __( 'End / Right (flex-end)', 'pods-block-suite' ), value: 'flex-end' },
							{ label: __( 'Space Between (space-between)', 'pods-block-suite' ), value: 'space-between' },
							{ label: __( 'Space Around (space-around)', 'pods-block-suite' ), value: 'space-around' },
							{ label: __( 'Space Evenly (space-evenly)', 'pods-block-suite' ), value: 'space-evenly' },
						] }
						onChange={ ( val ) => setAttributes( { justifyContent: val } ) }
					/>

					{ flexDirection === 'row' && (
						<RangeControl
							label={ __( 'Columns per row', 'pods-block-suite' ) }
							value={ columns }
							onChange={ ( val ) => setAttributes( { columns: val } ) }
							min={ 1 }
							max={ 6 }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...innerBlocksProps } />
		</>
	);
}