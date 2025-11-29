/**
 * PodLoom Subscribe Buttons Block
 *
 * Displays subscribe buttons for a selected podcast.
 *
 * @package PodLoom
 * @since 2.10.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RadioControl,
	ToggleControl,
	ColorPicker,
	Placeholder,
	Spinner,
	Button,
	RangeControl,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';

/**
 * Edit component for the subscribe block
 */
function EditComponent( { attributes, setAttributes } ) {
	const {
		source,
		iconSize,
		colorMode,
		customColor,
		layout,
		showLabels,
		iconGap,
		labelFontSize,
		labelFontFamily,
	} = attributes;

	const [ podcasts, setPodcasts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ previewData, setPreviewData ] = useState( null );
	const [ previewLoading, setPreviewLoading ] = useState( false );

	const blockProps = useBlockProps();

	// Load podcasts on mount
	useEffect( () => {
		loadPodcasts();
	}, [] );

	// Load preview data when source or color settings change
	useEffect( () => {
		if ( source ) {
			loadPreviewData( source );
		} else {
			setPreviewData( null );
		}
	}, [ source, colorMode, customColor ] );

	/**
	 * Load available podcasts
	 */
	const loadPodcasts = async () => {
		setLoading( true );

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_subscribe_podcasts' );
			formData.append( 'nonce', window.podloomData.nonce );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success ) {
				setPodcasts( result.data.podcasts || [] );
			}
		} catch ( err ) {
			console.error( 'Error loading podcasts:', err );
		} finally {
			setLoading( false );
		}
	};

	/**
	 * Load preview data for selected source
	 */
	const loadPreviewData = async ( sourceId ) => {
		setPreviewLoading( true );

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_subscribe_preview' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'source_id', sourceId );
			formData.append( 'color_mode', colorMode );
			if ( colorMode === 'custom' ) {
				formData.append( 'custom_color', customColor );
			}

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success ) {
				setPreviewData( result.data.links || [] );
			} else {
				setPreviewData( [] );
			}
		} catch ( err ) {
			console.error( 'Error loading preview:', err );
			setPreviewData( [] );
		} finally {
			setPreviewLoading( false );
		}
	};

	/**
	 * Build podcast options for select
	 */
	const buildPodcastOptions = () => {
		const options = [
			{ label: __( '-- Select a podcast --', 'podloom-podcast-player' ), value: '' },
		];

		const transistorPodcasts = podcasts.filter( ( p ) => p.type === 'transistor' );
		const rssPodcasts = podcasts.filter( ( p ) => p.type === 'rss' );

		if ( transistorPodcasts.length > 0 ) {
			options.push( {
				label: __( '━━ Transistor.fm ━━', 'podloom-podcast-player' ),
				value: '__transistor_header__',
				disabled: true,
			} );
			transistorPodcasts.forEach( ( podcast ) => {
				options.push( {
					label: '  ' + podcast.name,
					value: podcast.source_id,
				} );
			} );
		}

		if ( rssPodcasts.length > 0 ) {
			options.push( {
				label: __( '━━ RSS Feeds ━━', 'podloom-podcast-player' ),
				value: '__rss_header__',
				disabled: true,
			} );
			rssPodcasts.forEach( ( podcast ) => {
				options.push( {
					label: '  ' + podcast.name,
					value: podcast.source_id,
				} );
			} );
		}

		return options;
	};

	/**
	 * Font family options
	 */
	const fontFamilyOptions = [
		{ label: __( 'Inherit from theme', 'podloom-podcast-player' ), value: 'inherit' },
		{ label: 'System UI', value: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' },
		{ label: 'Arial', value: 'Arial, Helvetica, sans-serif' },
		{ label: 'Georgia', value: 'Georgia, "Times New Roman", serif' },
		{ label: 'Verdana', value: 'Verdana, Geneva, sans-serif' },
		{ label: 'Trebuchet MS', value: '"Trebuchet MS", Helvetica, sans-serif' },
		{ label: 'Courier New', value: '"Courier New", Courier, monospace' },
	];

	/**
	 * Render preview buttons
	 */
	const renderPreview = () => {
		if ( ! previewData || previewData.length === 0 ) {
			return (
				<div style={ { padding: '20px', textAlign: 'center', color: '#666' } }>
					{ __(
						'No subscribe links configured for this podcast.',
						'podloom-podcast-player'
					) }
				</div>
			);
		}

		const containerStyle = {
			display: 'flex',
			flexWrap: 'wrap',
			gap: iconGap + 'px',
			alignItems: 'center',
			justifyContent: layout === 'horizontal' ? 'flex-start' : 'center',
			flexDirection: layout === 'vertical' ? 'column' : 'row',
		};

		const buttonStyle = {
			display: 'flex',
			alignItems: 'center',
			gap: '8px',
			textDecoration: 'none',
			color: 'inherit',
		};

		const iconStyle = {
			width: iconSize + 'px',
			height: iconSize + 'px',
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'center',
		};

		const labelStyle = {
			fontSize: labelFontSize + 'px',
			fontFamily: labelFontFamily,
		};

		return (
			<div
				className={ `podloom-subscribe-buttons podloom-subscribe-buttons--${ layout }` }
				style={ containerStyle }
			>
				{ previewData.map( ( link, index ) => (
					<a
						key={ index }
						href={ link.url }
						target="_blank"
						rel="noopener noreferrer"
						style={ buttonStyle }
						title={ link.name }
					>
						<span
							style={ iconStyle }
							dangerouslySetInnerHTML={ { __html: link.svg || '' } }
						/>
						{ showLabels && (
							<span style={ labelStyle }>{ link.name }</span>
						) }
					</a>
				) ) }
			</div>
		);
	};

	// Loading state
	if ( loading ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="share"
					label={ __( 'PodLoom Subscribe Buttons', 'podloom-podcast-player' ) }
				>
					<Spinner />
					<p>{ __( 'Loading podcasts...', 'podloom-podcast-player' ) }</p>
				</Placeholder>
			</div>
		);
	}

	// No podcasts configured
	if ( podcasts.length === 0 ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="share"
					label={ __( 'PodLoom Subscribe Buttons', 'podloom-podcast-player' ) }
				>
					<p>
						{ __(
							'No podcasts configured. Add a Transistor API key or RSS feed first.',
							'podloom-podcast-player'
						) }
					</p>
					<Button
						variant="primary"
						href={
							window.podloomData.ajaxUrl.replace(
								'/wp-admin/admin-ajax.php',
								''
							) + '/wp-admin/admin.php?page=podloom-settings'
						}
					>
						{ __( 'Go to Settings', 'podloom-podcast-player' ) }
					</Button>
				</Placeholder>
			</div>
		);
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Subscribe Settings', 'podloom-podcast-player' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Select Podcast', 'podloom-podcast-player' ) }
						value={ source }
						options={ buildPodcastOptions() }
						onChange={ ( value ) => setAttributes( { source: value } ) }
						help={ __(
							'Choose which podcast to display subscribe buttons for',
							'podloom-podcast-player'
						) }
					/>

					<RangeControl
						label={ __( 'Icon Size', 'podloom-podcast-player' ) }
						value={ iconSize }
						onChange={ ( value ) => setAttributes( { iconSize: value } ) }
						min={ 16 }
						max={ 64 }
						step={ 2 }
					/>

					<RangeControl
						label={ __( 'Icon Spacing', 'podloom-podcast-player' ) }
						value={ iconGap }
						onChange={ ( value ) => setAttributes( { iconGap: value } ) }
						min={ 4 }
						max={ 48 }
						step={ 2 }
					/>

					<RadioControl
						label={ __( 'Color Mode', 'podloom-podcast-player' ) }
						selected={ colorMode }
						options={ [
							{
								label: __( 'Brand Colors', 'podloom-podcast-player' ),
								value: 'brand',
							},
							{
								label: __( 'Monochrome', 'podloom-podcast-player' ),
								value: 'mono',
							},
							{
								label: __( 'Custom Color', 'podloom-podcast-player' ),
								value: 'custom',
							},
						] }
						onChange={ ( value ) => setAttributes( { colorMode: value } ) }
					/>

					{ colorMode === 'custom' && (
						<div style={ { marginTop: '12px' } }>
							<ColorPicker
								color={ customColor }
								onChange={ ( value ) =>
									setAttributes( { customColor: value } )
								}
								enableAlpha={ false }
							/>
						</div>
					) }

					<RadioControl
						label={ __( 'Layout', 'podloom-podcast-player' ) }
						selected={ layout }
						options={ [
							{
								label: __( 'Horizontal', 'podloom-podcast-player' ),
								value: 'horizontal',
							},
							{
								label: __( 'Vertical', 'podloom-podcast-player' ),
								value: 'vertical',
							},
							{
								label: __( 'Grid', 'podloom-podcast-player' ),
								value: 'grid',
							},
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>

					<ToggleControl
						label={ __( 'Show Labels', 'podloom-podcast-player' ) }
						checked={ showLabels }
						onChange={ ( value ) => setAttributes( { showLabels: value } ) }
						help={ __(
							'Display platform names next to icons',
							'podloom-podcast-player'
						) }
					/>

					{ showLabels && (
						<>
							<RangeControl
								label={ __( 'Label Font Size', 'podloom-podcast-player' ) }
								value={ labelFontSize }
								onChange={ ( value ) => setAttributes( { labelFontSize: value } ) }
								min={ 10 }
								max={ 24 }
								step={ 1 }
							/>

							<SelectControl
								label={ __( 'Label Font Family', 'podloom-podcast-player' ) }
								value={ labelFontFamily }
								options={ fontFamilyOptions }
								onChange={ ( value ) => setAttributes( { labelFontFamily: value } ) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ ! source ? (
					<Placeholder
						icon="share"
						label={ __( 'PodLoom Subscribe Buttons', 'podloom-podcast-player' ) }
					>
						<p>
							{ __(
								'Select a podcast from the sidebar to display subscribe buttons.',
								'podloom-podcast-player'
							) }
						</p>
					</Placeholder>
				) : previewLoading ? (
					<div style={ { padding: '20px', textAlign: 'center' } }>
						<Spinner />
						<p>{ __( 'Loading preview...', 'podloom-podcast-player' ) }</p>
					</div>
				) : (
					<div className="wp-block-podloom-subscribe-buttons">
						{ renderPreview() }
					</div>
				) }
			</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: EditComponent,
	save: () => null,
} );
