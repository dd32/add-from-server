/**
 * Add From Server — block editor sidebar plugin.
 *
 * Registers a sidebar plugin in the post editor that opens a modal
 * letting the user pick files from the server filesystem and import
 * them into the media library. On success, the selected attachment
 * is inserted as a core/image block (for images) or reported to the
 * user so they can insert it via the usual media workflow.
 */
( function ( wp, settings ) {
	if ( ! wp || ! settings || ! wp.plugins || ! wp.editPost ) {
		return;
	}

	const { createElement: h, useState, useEffect, useCallback, Fragment } = wp.element;
	const { Button, Modal, Spinner, Notice, CheckboxControl } = wp.components;
	const { __, sprintf } = wp.i18n;
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { dispatch } = wp.data;
	const apiFetch = wp.apiFetch;
	const serverIcon = ( wp.icons && wp.icons.server ) || 'upload';

	if ( settings.restNonce && apiFetch.createNonceMiddleware ) {
		apiFetch.use( apiFetch.createNonceMiddleware( settings.restNonce ) );
	}

	function FilePickerModal( { onClose } ) {
		const [ path, setPath ] = useState( '/' );
		const [ data, setData ] = useState( null );
		const [ loading, setLoading ] = useState( true );
		const [ error, setError ] = useState( null );
		const [ selected, setSelected ] = useState( [] );
		const [ importing, setImporting ] = useState( false );

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
				.catch( ( err ) =>
					setError( err.message || __( 'Unable to load directory.', 'add-from-server' ) )
				)
				.finally( () => setLoading( false ) );
		}, [] );

		useEffect( () => {
			load( '/' );
		}, [ load ] );

		const toggle = ( file ) =>
			setSelected( ( prev ) =>
				prev.includes( file ) ? prev.filter( ( f ) => f !== file ) : [ ...prev, file ]
			);

		const doImport = () => {
			if ( ! selected.length ) {
				return;
			}
			setImporting( true );
			apiFetch( {
				path: '/add-from-server/v1/import',
				method: 'POST',
				data: { files: selected },
			} )
				.then( ( res ) => {
					const inserted = [];
					( res.results || [] ).forEach( ( r ) => {
						if ( r.success && r.attachment_id ) {
							inserted.push( r.attachment_id );
						}
					} );

					if ( inserted.length && dispatch( 'core/block-editor' ) ) {
						inserted.forEach( ( id ) => {
							apiFetch( { path: '/wp/v2/media/' + id } )
								.then( ( media ) => {
									const block = wp.blocks.createBlock( 'core/image', {
										id: media.id,
										url: media.source_url,
										alt: media.alt_text || '',
										caption: ( media.caption && media.caption.rendered ) || '',
									} );
									dispatch( 'core/block-editor' ).insertBlocks( block );
								} )
								.catch( () => {} );
						} );
					}

					const failed = ( res.results || [] ).filter( ( r ) => ! r.success );
					if ( failed.length ) {
						setError(
							failed
								.map( ( f ) =>
									sprintf(
										/* translators: 1: filename, 2: error */
										__( '%1$s: %2$s', 'add-from-server' ),
										f.file,
										f.message
									)
								)
								.join( '\n' )
						);
					} else {
						onClose();
					}
				} )
				.catch( ( err ) =>
					setError( err.message || __( 'Import failed.', 'add-from-server' ) )
				)
				.finally( () => setImporting( false ) );
		};

		const renderRows = () => {
			if ( ! data ) {
				return null;
			}
			const rows = [];

			if ( path !== '/' ) {
				rows.push(
					h(
						'li',
						{ key: 'parent' },
						h(
							Button,
							{ variant: 'link', onClick: () => load( path.replace( /\/[^/]*$/, '' ) || '/' ) },
							'.. ' + __( '(Parent)', 'add-from-server' )
						)
					)
				);
			}

			data.directories.forEach( ( dir ) => {
				rows.push(
					h(
						'li',
						{ key: 'd-' + dir.path, className: 'afs-row-dir' },
						h(
							Button,
							{ variant: 'link', onClick: () => load( dir.path ) },
							dir.name + '/'
						)
					)
				);
			} );

			data.files.forEach( ( file ) => {
				const disabled = ! file.readable || ! file.mime;
				rows.push(
					h(
						'li',
						{
							key: 'f-' + file.path,
							className: 'afs-row-file' + ( disabled ? ' afs-disabled' : '' ),
						},
						h( CheckboxControl, {
							label: file.name,
							checked: selected.includes( file.path ),
							disabled,
							onChange: () => toggle( file.path ),
							__nextHasNoMarginBottom: true,
						} )
					)
				);
			} );

			return h( 'ul', { className: 'afs-file-list' }, rows );
		};

		return h(
			Modal,
			{
				title: __( 'Add From Server', 'add-from-server' ),
				onRequestClose: onClose,
				className: 'afs-editor-modal',
				size: 'large',
			},
			error
				? h(
						Notice,
						{ status: 'error', isDismissible: true, onRemove: () => setError( null ) },
						error
				  )
				: null,
			h(
				'p',
				{ className: 'afs-current-path' },
				h( 'strong', null, __( 'Path:', 'add-from-server' ) ),
				' ',
				path
			),
			loading ? h( Spinner, null ) : renderRows(),
			h(
				'div',
				{ className: 'afs-modal-actions' },
				h(
					Button,
					{ variant: 'secondary', onClick: onClose, disabled: importing },
					__( 'Cancel', 'add-from-server' )
				),
				' ',
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

	function Sidebar() {
		const [ isOpen, setIsOpen ] = useState( false );

		return h(
			Fragment,
			null,
			h(
				PluginSidebarMoreMenuItem,
				{ target: 'add-from-server-sidebar', icon: serverIcon },
				__( 'Add From Server', 'add-from-server' )
			),
			h(
				PluginSidebar,
				{
					name: 'add-from-server-sidebar',
					title: __( 'Add From Server', 'add-from-server' ),
					icon: serverIcon,
				},
				h(
					'div',
					{ className: 'afs-sidebar-body' },
					h(
						'p',
						null,
						__(
							'Pick files from the server filesystem to import into the media library and insert into your post.',
							'add-from-server'
						)
					),
					h(
						Button,
						{ variant: 'primary', onClick: () => setIsOpen( true ) },
						__( 'Browse files…', 'add-from-server' )
					),
					isOpen ? h( FilePickerModal, { onClose: () => setIsOpen( false ) } ) : null
				)
			)
		);
	}

	registerPlugin( 'add-from-server', { render: Sidebar, icon: serverIcon } );
} )( window.wp, window.addFromServerSettings );
