import { __ } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Custom hook that fetches the PODS Block Suite schema from the REST API,
 * optionally auto-selects the POD from block context, and provides formatted dropdown options.
 *
 * @param {Object}   options                 Configuration options.
 * @param {string}   options.selectedPod     Currently selected POD slug.
 * @param {string}   options.contextPostType Post type from block context (optional).
 * @param {Function} options.onAutoSelectPod Callback invoked when a POD is auto-selected from context.
 * @return {Object} Schema state, loading flag, active POD data, and formatted podOptions.
 */
export function usePodsSchema( {
	selectedPod = '',
	contextPostType = '',
	onAutoSelectPod = null,
} = {} ) {
	const [ schema, setSchema ] = useState( null );
	const [ isLoadingSchema, setIsLoadingSchema ] = useState( true );

	// 1. Fetch schema once when the component mounts
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

	// 2. Automatically pre-select POD from block context when available
	useEffect( () => {
		if (
			schema &&
			! selectedPod &&
			contextPostType &&
			schema[ contextPostType ] &&
			typeof onAutoSelectPod === 'function'
		) {
			onAutoSelectPod( contextPostType );
		}
	}, [ schema, selectedPod, contextPostType, onAutoSelectPod ] );

	// 3. Build formatted SelectControl options for all registered PODS
	const podOptions = useMemo( () => {
		const options = [
			{ label: __( '— Select POD —', 'pods-block-suite' ), value: '' },
		];

		if ( schema ) {
			Object.values( schema ).forEach( ( pod ) => {
				options.push( {
					label: pod.label,
					value: pod.name,
				} );
			} );
		}

		return options;
	}, [ schema ] );

	const activePodData = schema && selectedPod ? schema[ selectedPod ] || null : null;

	return {
		schema,
		isLoadingSchema,
		podOptions,
		activePodData,
	};
}