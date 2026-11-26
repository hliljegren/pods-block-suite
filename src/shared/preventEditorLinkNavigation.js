export function preventEditorLinkNavigation( event ) {
	const link = event.target.closest?.( 'a[href]' );

	if ( link && event.currentTarget.contains( link ) ) {
		event.preventDefault();
	}
}
