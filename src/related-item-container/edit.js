import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RangeControl,
	CheckboxControl,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './editor.scss';

export default function Edit( { attributes, setAttributes, context } ) {
	const {
		selectedPod,
		relationshipField,
		limit,
		orderBy,
		orderDir,
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

	useEffect( () => {
		if ( schema && ! selectedPod && context.postType && schema[ context.postType ] ) {
			setAttributes( { selectedPod: context.postType } );
		}
	}, [ schema, selectedPod, context.postType ] );

	const activePodData = schema && selectedPod ? schema[ selectedPod ] : null;
	const relationshipFields = activePodData
		? activePodData.fields.filter( ( f ) => f.type === 'pick' )
		: [];

	// Resolve target POD metadata for sorting options
	const currentRelFieldMeta = relationshipFields.find( ( f ) => f.name === relationshipField ) || null;
	const targetPodSlug = currentRelFieldMeta?.relatedPod || '';
	const targetPodData = schema && targetPodSlug ? schema[ targetPodSlug ] : null;

	const sortableTargetPodsFields = targetPodData
		? targetPodData.fields.filter( ( f ) => f.sortable !== false )
		: [];
	const sortableTargetWpFields = targetPodData
		? targetPodData.standardFields.filter( ( f ) => f.sortable !== false )
		: [];

	const isRandomSort = orderDir === 'RAND';

	const podOptions = [
		{ label: __( '— Select POD —', 'pods-block-suite' ), value: '' },
		...( schema
			? Object.values( schema ).map( ( pod ) => ( {
					label: pod.label,
					value: pod.name,
			  } ) )
			: [] ),
	];

	const relationOptions = [
		{ label: __( '— Select relationship field —', 'pods-block-suite' ), value: '' },
		...relationshipFields.map( ( f ) => ( {
			label: `${ f.label } (${ f.name })`,
			value: f.name,
		} ) ),
	];

	const editorLabel = relationshipField
		? sprintf(
				/* translators: 1: Relationship field slug, 2: Layout description */
				__( 'PODS Related Items: %1$s (%2$s)', 'pods-block-suite' ),
				relationshipField,
				flexDirection === 'row'
					? sprintf( __( 'Row, %d cols', 'pods-block-suite' ), columns )
					: __( 'Column', 'pods-block-suite' )
		  )
		: __( 'PODS Related Item Container (select relationship field in sidebar)', 'pods-block-suite' );

	const blockProps = useBlockProps( {
		className: 'pods-related-item-container-editor',
		'data-editor-label': editorLabel,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Relationship Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					{ isLoadingSchema ? (
						<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
							<Spinner />
							<span>{ __( 'Loading PODS...', 'pods-block-suite' ) }</span>
						</div>
					) : (
						<>
							<SelectControl
								label={ __( '1. Parent POD', 'pods-block-suite' ) }
								value={ selectedPod }
								options={ podOptions }
								onChange={ ( val ) => {
									setAttributes( {
										selectedPod: val,
										relationshipField: '',
										orderBy: 'post_date',
									} );
								} }
							/>

							{ activePodData && relationshipFields.length > 0 ? (
								<SelectControl
									label={ __( '2. Relationship Field', 'pods-block-suite' ) }
									value={ relationshipField }
									options={ relationOptions }
									onChange={ ( val ) =>
										setAttributes( {
											relationshipField: val,
											orderBy: 'post_date',
										} )
									}
								/>
							) : (
								<TextControl
									label={ __( 'Relationship Field Slug', 'pods-block-suite' ) }
									help={ __(
										'Enter the slug of your PODS relationship (pick) field.',
										'pods-block-suite'
									) }
									value={ relationshipField }
									onChange={ ( val ) =>
										setAttributes( { relationshipField: val.trim() } )
									}
								/>
							) }
						</>
					) }

					<TextControl
						label={ __( 'Max Items (0 = Unlimited)', 'pods-block-suite' ) }
						type="number"
						value={ limit }
						onChange={ ( val ) => {
							const parsed = parseInt( val, 10 );
							setAttributes( { limit: isNaN( parsed ) ? 0 : Math.max( 0, parsed ) } );
						} }
					/>

					<SelectControl
						label={ __( 'Sort Direction', 'pods-block-suite' ) }
						value={ orderDir }
						options={ [
							{ label: __( 'Descending (DESC / Newest or Z–A)', 'pods-block-suite' ), value: 'DESC' },
							{ label: __( 'Ascending (ASC / Oldest or A–Z)', 'pods-block-suite' ), value: 'ASC' },
							{ label: __( 'Random Order (RAND)', 'pods-block-suite' ), value: 'RAND' },
						] }
						onChange={ ( val ) => setAttributes( { orderDir: val } ) }
					/>

					{ ! isRandomSort && (
						<div className="components-base-control">
							<label
								className="components-base-control__label"
								htmlFor="pods-related-orderby-select"
								style={ { display: 'block', marginBottom: '6px' } }
							>
								{ __( 'Order By', 'pods-block-suite' ) }
							</label>
							<select
								id="pods-related-orderby-select"
								className="components-select-control__input"
								style={ { width: '100%', height: '36px' } }
								value={ orderBy }
								onChange={ ( e ) => setAttributes( { orderBy: e.target.value } ) }
							>
								<optgroup label={ __( 'WordPress Standard Fields', 'pods-block-suite' ) }>
									{ sortableTargetWpFields.length > 0 ? (
										sortableTargetWpFields.map( ( f ) => (
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
								{ sortableTargetPodsFields.length > 0 && (
									<optgroup label={ __( 'PODS Fields', 'pods-block-suite' ) }>
										{ sortableTargetPodsFields.map( ( f ) => (
											<option key={ f.name } value={ f.name }>
												{ f.label } [{ f.type }]
											</option>
										) ) }
									</optgroup>
								) }
							</select>
						</div>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Layout & Card Settings', 'pods-block-suite' ) }
					initialOpen={ true }
				>
					<CheckboxControl
						label={ __( 'Make entire card clickable (Link to post)', 'pods-block-suite' ) }
						help={ __(
							'Wraps each related item in a link to its single post permalink.',
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