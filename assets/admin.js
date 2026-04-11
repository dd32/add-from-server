/**
 * Add From Server — admin page app.
 *
 * Written in plain ES2017+ and wp.element.createElement so no JSX
 * transpilation or bundling is required.
 */
( function ( wp, settings ) {
	if ( ! wp || ! settings ) {
		return;
	}

	const { createElement: h, useState, useEffect, useCallback, Fragment } = wp.element;
	const { Button, Spinner, Notice, CheckboxControl, Card, CardBody } = wp.components;
	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// wp-api-fetch is pre-configured with the correct REST root URL and
	// nonce by WordPress, but we install a fresh nonce middleware defensively
	// in case this script runs before the inline WP config has executed.
	if ( settings.restNonce && apiFetch.createNonceMiddleware ) {
		apiFetch.use( apiFetch.createNonceMiddleware( settings.restNonce ) );
	}

	function breadcrumbs( path, onNavigate ) {
		const parts = ( path || '/' ).split( '/' ).filter( Boolean );
		const items = [
			h(
				Button,
				{
					key: 'root',
					variant: 'link',
					onClick: () => onNavigate( '/' ),
				},
				settings.root || '/'
			),
		];
		let current = '';
		parts.forEach( ( part, i ) => {
			current += '/' + part;
			items.push( h( 'span', { key: 'sep-' + i, className: 'afs-sep' }, ' / ' ) );
			items.push(
				h(
					Button,
					{
						key: 'part-' + i,
						variant: 'link',
						onClick: ( ( p ) => () => onNavigate( p ) )( current ),
					},
					part
				)
			);
		} );
		return h( 'div', { className: 'afs-breadcrumbs' }, items );
	}

	function FileBrowser() {
		const [ path, setPath ] = useState( '/' );
		const [ data, setData ] = useState( null );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( null );
		const [ selected, setSelected ] = useState( [] );
		const [ importing, setImporting ] = useState( false );
		const [ results, setResults ] = useState( null );

		const load = useCallback( ( p ) => {
			setLoading( true );
			setError( null );
			setSelected( [] );
			apiFetch( {
				path: '/add-from-server/v1/browse?path=' + encodeURIComponent( p ),
			} )
				.then( ( res ) => {
					setData( res );
					setPath( res.path );
				} )
				.catch( ( err ) => {
					setError( err.message || __( 'Unable to load directory.', 'add-from-server' ) );
				} )
				.finally( () => setLoading( false ) );
		}, [] );

		useEffect( () => {
			load( '/' );
		}, [ load ] );

		const toggle = ( file ) => {
			setSelected( ( prev ) =>
				prev.includes( file ) ? prev.filter( ( f ) => f !== file ) : [ ...prev, file ]
			);
		};

		const doImport = () => {
			if ( ! selected.length ) {
				return;
			}
			setImporting( true );
			setResults( null );
			apiFetch( {
				path: '/add-from-server/v1/import',
				method: 'POST',
				data: { files: selected },
			} )
				.then( ( res ) => setResults( res ) )
				.catch( ( err ) =>
					setError( err.message || __( 'Import failed.', 'add-from-server' ) )
				)
				.finally( () => {
					setImporting( false );
					setSelected( [] );
				} );
		};

		if ( error ) {
			return h(
				Notice,
				{ status: 'error', isDismissible: true, onRemove: () => setError( null ) },
				error
			);
		}

		if ( ! data || loading ) {
			return h( 'div', { className: 'afs-loading' }, h( Spinner, null ) );
		}

		const rows = [];

		if ( path !== '/' ) {
			rows.push(
				h(
					'tr',
					{ key: '..' },
					h( 'td', { className: 'check-column' }, '' ),
					h(
						'td',
						null,
						h(
							Button,
							{ variant: 'link', onClick: () => load( path.replace( /\/[^/]*$/, '' ) || '/' ) },
							'.. ' + __( '(Parent)', 'add-from-server' )
						)
					)
				)
			);
		}

		data.directories.forEach( ( dir ) => {
			rows.push(
				h(
					'tr',
					{ key: 'd-' + dir.path },
					h( 'td', { className: 'check-column' }, '' ),
					h(
						'td',
						null,
						h(
							Button,
							{ variant: 'link', onClick: () => load( dir.path ) },
							dir.name + '/'
						)
					)
				)
			);
		} );

		data.files.forEach( ( file ) => {
			const disabled = ! file.readable || ! file.mime;
			rows.push(
				h(
					'tr',
					{
						key: 'f-' + file.path,
						className: disabled ? 'afs-file-disabled' : '',
					},
					h(
						'th',
						{ className: 'check-column' },
						h( CheckboxControl, {
							checked: selected.includes( file.path ),
							disabled,
							onChange: () => toggle( file.path ),
							__nextHasNoMarginBottom: true,
						} )
					),
					h(
						'td',
						null,
						h( 'label', null, file.name ),
						disabled
							? h(
									'p',
									{ className: 'description' },
									file.readable
										? __( 'File type not permitted.', 'add-from-server' )
										: __( 'File not readable.', 'add-from-server' )
							  )
							: null
					)
				)
			);
		} );

		return h(
			Fragment,
			null,
			breadcrumbs( path, load ),
			results
				? h(
						Notice,
						{
							status: results.success ? 'success' : 'warning',
							isDismissible: true,
							onRemove: () => setResults( null ),
						},
						results.results.map( ( r, i ) =>
							h(
								'p',
								{ key: i },
								r.success
									? sprintf(
											/* translators: %s: filename */
											__( '%s imported successfully.', 'add-from-server' ),
											r.file
									  )
									: sprintf(
											/* translators: 1: filename, 2: error */
											__( '%1$s failed: %2$s', 'add-from-server' ),
											r.file,
											r.message
									  )
							)
						)
				  )
				: null,
			h(
				'table',
				{ className: 'widefat afs-browser' },
				h(
					'thead',
					null,
					h(
						'tr',
						null,
						h( 'td', { className: 'check-column' }, '' ),
						h( 'td', null, __( 'File', 'add-from-server' ) )
					)
				),
				h( 'tbody', null, rows )
			),
			h(
				'p',
				{ className: 'afs-actions' },
				h(
					Button,
					{
						variant: 'primary',
						disabled: ! selected.length || importing,
						onClick: doImport,
					},
					importing ? h( Spinner, null ) : null,
					__( 'Import Selected', 'add-from-server' )
				)
			)
		);
	}

	function App() {
		return h(
			Card,
			{ className: 'afs-card' },
			h( CardBody, null, h( FileBrowser, null ) )
		);
	}

	wp.domReady( function () {
		const container = document.getElementById( 'add-from-server-app' );
		if ( ! container ) {
			return;
		}
		if ( wp.element.createRoot ) {
			wp.element.createRoot( container ).render( h( App, null ) );
		} else {
			wp.element.render( h( App, null ), container );
		}
	} );
} )( window.wp, window.addFromServerSettings );
