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
	CheckboxControl,
	Spinner,
	Notice,
} from '@wordpress/components';
import { usePodsSchema } from '../shared/hooks/usePodsSchema';
import { SortControls } from '../shared/components/SortControls';
import { LayoutControls } from '../shared/components/LayoutControls';
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

	const { isLoadingSchema, podOptions, activePodData } = usePodsSchema( {
		selectedPod,
	} );

	const availableFields = activePodData ? activePodData.fields : [];

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

					<SortControls
						orderBy={ orderBy }
						orderDir={ orderDir }
						podData={ activePodData }
						onChange={ setAttributes }
					/>

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
					<LayoutControls
						flexDirection={ flexDirection }
						justifyContent={ justifyContent }
						columns={ columns }
						linkToPost={ linkToPost }
						onChange={ setAttributes }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...innerBlocksProps } />
		</>
	);
}