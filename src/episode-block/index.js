/**
 * PodLoom Episode Block
 *
 * Handles Transistor.fm episodes and RSS feed episodes with full Podcasting 2.0 support.
 * Uses @wordpress/data store for shared state management.
 *
 * @package PodLoom
 * @since 2.10.0
 */

import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Placeholder,
	Spinner,
	Button,
	RadioControl,
	ComboboxControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch, dispatch, select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

import { STORE_NAME } from '../store/constants';
import metadata from './block.json';

/**
 * Sanitize URL to prevent XSS attacks
 */
const sanitizeUrl = ( url ) => {
	if ( ! url ) return '';

	url = url.trim();
	const urlLower = url.toLowerCase();

	if (
		urlLower.startsWith( 'http://' ) ||
		urlLower.startsWith( 'https://' ) ||
		urlLower.startsWith( 'mailto:' )
	) {
		return url.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	if ( url.startsWith( '/' ) || url.startsWith( '#' ) ) {
		return url.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	}

	return '';
};

/**
 * Clean HTML for WordPress blocks
 */
const cleanHtmlForBlocks = ( html ) => {
	if ( ! html ) return '';

	const tempDiv = document.createElement( 'div' );
	tempDiv.innerHTML = html;

	const cleanNode = ( node ) => {
		if ( node.nodeType === Node.TEXT_NODE ) {
			return node.textContent;
		}

		if ( node.nodeType !== Node.ELEMENT_NODE ) {
			return '';
		}

		const tagName = node.tagName.toLowerCase();
		let result = '';

		const childContent = Array.from( node.childNodes )
			.map( ( child ) => cleanNode( child ) )
			.join( '' );

		switch ( tagName ) {
			case 'p':
				result = childContent + '\n\n';
				break;
			case 'br':
				result = '\n';
				break;
			case 'strong':
			case 'b':
				result = '<strong>' + childContent + '</strong>';
				break;
			case 'em':
			case 'i':
				result = '<em>' + childContent + '</em>';
				break;
			case 'a':
				const href = node.getAttribute( 'href' );
				const sanitizedHref = sanitizeUrl( href );
				if ( sanitizedHref ) {
					result = '<a href="' + sanitizedHref + '">' + childContent + '</a>';
				} else {
					result = childContent;
				}
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				result = '\n\n' + childContent + '\n\n';
				break;
			case 'ul':
			case 'ol':
				result = childContent;
				break;
			case 'li':
				result = '- ' + childContent + '\n';
				break;
			default:
				result = childContent;
				break;
		}

		return result;
	};

	let cleaned = cleanNode( tempDiv );
	cleaned = cleaned.replace( /\n{3,}/g, '\n\n' );
	cleaned = cleaned.trim();

	return cleaned;
};

/**
 * Edit component for the episode block
 */
function EditComponent( { attributes, setAttributes, clientId } ) {
	const {
		sourceType,
		episodeId,
		episodeTitle,
		showId,
		showTitle,
		showSlug,
		rssFeedId,
		rssEpisodeData,
		episodeDescription,
		embedHtml,
		theme,
		displayMode,
		playlistHeight,
		playlistMaxEpisodes,
		playlistOrder,
	} = attributes;

	// Local state for error display
	const [ error, setError ] = useState( '' );

	const blockProps = useBlockProps();

	// Get data from store
	const {
		transistorShows,
		rssFeeds,
		isLoaded,
		rssTypography,
		episodes,
		isEpisodesLoading,
		hasMore,
		renderedHtml,
		playlistHtml,
	} = useSelect(
		( storeSelect ) => {
			const store = storeSelect( STORE_NAME );
			const sources = store.getAllSources();
			const currentSourceId = sourceType === 'transistor' ? showId : rssFeedId;

			return {
				transistorShows: sources.transistorShows,
				rssFeeds: sources.rssFeeds,
				isLoaded: sources.isLoaded,
				rssTypography: store.getRssTypography(),
				episodes: currentSourceId
					? store.getEpisodes( sourceType, currentSourceId )
					: [],
				isEpisodesLoading: currentSourceId
					? store.isEpisodesLoading( sourceType, currentSourceId )
					: false,
				hasMore: currentSourceId
					? store.hasMoreEpisodes( sourceType, currentSourceId )
					: false,
				renderedHtml: rssEpisodeData
					? store.getRenderedEpisodeHtml( rssEpisodeData.id || rssEpisodeData.title )
					: null,
				playlistHtml: rssFeedId
					? store.getRenderedPlaylistHtml( `${ rssFeedId }_${ playlistMaxEpisodes }_${ playlistOrder }` )
					: null,
			};
		},
		[ sourceType, showId, rssFeedId, rssEpisodeData, playlistMaxEpisodes, playlistOrder ]
	);

	// Get dispatch functions
	const { fetchMoreEpisodes, fetchRenderedEpisodeHtml, fetchRenderedPlaylistHtml } = useDispatch( STORE_NAME );

	// Validate selected RSS feed still exists
	useEffect( () => {
		if ( sourceType === 'rss' && rssFeedId && rssFeeds.length > 0 ) {
			const feedExists = rssFeeds.find( ( feed ) => feed.id === rssFeedId );
			if ( ! feedExists ) {
				setAttributes( {
					rssFeedId: '',
					showTitle: '',
					episodeId: '',
					episodeTitle: '',
					episodeDescription: '',
					embedHtml: '',
					rssEpisodeData: null,
				} );
				setError(
					__(
						'The selected RSS feed has been removed. Please select a different feed.',
						'podloom-podcast-player'
					)
				);
			}
		}
	}, [ rssFeeds, rssFeedId, sourceType ] );

	// Fetch rendered HTML for selected RSS episode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'specific' && rssEpisodeData && rssFeedId ) {
			fetchRenderedEpisodeHtml( rssEpisodeData, rssFeedId );
		}
	}, [ sourceType, displayMode, rssEpisodeData, rssFeedId ] );

	// Fetch rendered HTML for latest RSS episode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'latest' && rssFeedId && episodes.length > 0 ) {
			const latestEpisode = episodes[ 0 ];
			if ( latestEpisode?.attributes ) {
				fetchRenderedEpisodeHtml(
					{
						id: latestEpisode.id,
						title: latestEpisode.attributes.title,
						audio_url: latestEpisode.attributes.audio_url,
						image: latestEpisode.attributes.image,
						description: latestEpisode.attributes.description,
						date: latestEpisode.attributes.date,
						duration: latestEpisode.attributes.duration,
						podcast20: latestEpisode.attributes.podcast20 || null,
					},
					rssFeedId
				);
			}
		}
	}, [ sourceType, displayMode, rssFeedId, episodes ] );

	// Fetch rendered HTML for RSS playlist mode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'playlist' && rssFeedId ) {
			fetchRenderedPlaylistHtml( rssFeedId, playlistMaxEpisodes, playlistOrder );
		}
	}, [ sourceType, displayMode, rssFeedId, playlistMaxEpisodes, playlistOrder ] );

	/**
	 * Load more episodes
	 */
	const loadMoreEpisodes = useCallback( () => {
		const sourceId = sourceType === 'transistor' ? showId : rssFeedId;
		if ( sourceId ) {
			fetchMoreEpisodes( sourceType, sourceId );
		}
	}, [ sourceType, showId, rssFeedId, fetchMoreEpisodes ] );

	/**
	 * Render RSS episode with typography (fallback)
	 */
	const renderRssEpisode = ( episode, typo ) => {
		if ( ! episode || ! typo ) return null;

		const display = typo.display || {
			artwork: true,
			title: true,
			date: true,
			duration: true,
			description: true,
		};

		const formatDate = ( timestamp ) => {
			const date = new Date( timestamp * 1000 );
			return date.toLocaleDateString();
		};

		const formatDuration = ( seconds ) => {
			if ( ! seconds ) return '';
			const hours = Math.floor( seconds / 3600 );
			const minutes = Math.floor( ( seconds % 3600 ) / 60 );
			const secs = seconds % 60;
			if ( hours > 0 ) {
				return `${ hours }:${ String( minutes ).padStart( 2, '0' ) }:${ String(
					secs
				).padStart( 2, '0' ) }`;
			}
			return `${ minutes }:${ String( secs ).padStart( 2, '0' ) }`;
		};

		return (
			<div className="wp-block-podloom-episode-player rss-episode-player">
				<div className="rss-episode-wrapper">
					{ display.artwork && episode.image && (
						<div className="rss-episode-artwork">
							<img src={ episode.image } alt={ episode.title } />
						</div>
					) }
					<div className="rss-episode-content">
						{ display.title && episode.title && (
							<h3 className="rss-episode-title">{ episode.title }</h3>
						) }
						<div className="rss-episode-meta">
							{ display.date && episode.date && (
								<span className="rss-episode-date">
									{ formatDate( episode.date ) }
								</span>
							) }
							{ display.duration && episode.duration && (
								<span className="rss-episode-duration">
									{ formatDuration( episode.duration ) }
								</span>
							) }
						</div>
						{ episode.audio_url && (
							<audio
								className={
									display.description && episode.description
										? 'rss-episode-audio'
										: 'rss-episode-audio rss-audio-last'
								}
								controls
								preload="metadata"
							>
								<source
									src={ episode.audio_url }
									type={ episode.audio_type || 'audio/mpeg' }
								/>
								{ __(
									'Your browser does not support the audio player.',
									'podloom-podcast-player'
								) }
							</audio>
						) }
						{ display.description && episode.description && (
							<div
								className="rss-episode-description"
								dangerouslySetInnerHTML={ { __html: episode.description } }
							/>
						) }
					</div>
				</div>
			</div>
		);
	};

	/**
	 * Handle episode selection
	 */
	const selectEpisode = ( episode ) => {
		if ( sourceType === 'transistor' ) {
			const html =
				theme === 'dark'
					? episode.attributes.embed_html_dark
					: episode.attributes.embed_html;
			setAttributes( {
				episodeId: episode.id,
				episodeTitle: episode.attributes.title,
				episodeDescription: episode.attributes.description || '',
				embedHtml: html,
				rssEpisodeData: null,
			} );
		} else {
			setAttributes( {
				episodeId: episode.id,
				episodeTitle: episode.attributes.title,
				embedHtml: '',
				rssEpisodeData: {
					title: episode.attributes.title,
					audio_url: episode.attributes.audio_url,
					image: episode.attributes.image,
					description: episode.attributes.description,
					content: episode.attributes.content,
					date: episode.attributes.date,
					duration: episode.attributes.duration,
					podcast20: episode.attributes.podcast20 || null,
				},
			} );
		}
	};

	/**
	 * Handle source selection
	 */
	const handleSourceChange = ( value ) => {
		const [ type, id ] = value.split( ':' );

		if ( type === 'transistor' ) {
			const selectedShow = transistorShows.find( ( show ) => show.id === id );
			setAttributes( {
				sourceType: 'transistor',
				showId: id,
				showTitle: selectedShow ? selectedShow.attributes.title : '',
				showSlug: selectedShow ? selectedShow.attributes.slug : '',
				rssFeedId: '',
				episodeId: '',
				episodeTitle: '',
				episodeDescription: '',
				embedHtml: '',
				rssEpisodeData: null,
			} );
		} else if ( type === 'rss' ) {
			const selectedFeed = rssFeeds.find( ( feed ) => feed.id === id );
			setAttributes( {
				sourceType: 'rss',
				showId: '',
				showSlug: '',
				rssFeedId: id,
				showTitle: selectedFeed ? selectedFeed.name : '',
				episodeId: '',
				episodeTitle: '',
				episodeDescription: '',
				embedHtml: '',
				rssEpisodeData: null,
			} );
		}

		setError( '' );
	};

	/**
	 * Handle theme change
	 */
	const handleThemeChange = ( newTheme ) => {
		setAttributes( { theme: newTheme } );

		if ( sourceType === 'transistor' && episodeId && episodes.length > 0 ) {
			const currentEpisode = episodes.find( ( ep ) => ep.id === episodeId );
			if ( currentEpisode ) {
				const html =
					newTheme === 'dark'
						? currentEpisode.attributes.embed_html_dark
						: currentEpisode.attributes.embed_html;
				setAttributes( { embedHtml: html } );
			}
		}
	};

	/**
	 * Handle paste description
	 */
	const handlePasteDescription = () => {
		let description = '';

		if ( sourceType === 'rss' && rssEpisodeData?.description ) {
			description = rssEpisodeData.description;
		} else if ( sourceType === 'transistor' && episodeDescription ) {
			description = episodeDescription;
		}

		if ( ! description ) {
			return;
		}

		const cleanedHtml = cleanHtmlForBlocks( description );
		const blockEditorStore = select( 'core/block-editor' );
		const blockIndex = blockEditorStore.getBlockIndex( clientId );
		const rootClientId = blockEditorStore.getBlockRootClientId( clientId );
		const paragraphs = cleanedHtml.split( '\n\n' ).filter( ( p ) => p.trim() !== '' );

		const blocks = paragraphs.map( ( paragraphText ) => {
			return wp.blocks.createBlock( 'core/paragraph', {
				content: paragraphText.trim(),
			} );
		} );

		dispatch( 'core/block-editor' ).insertBlocks( blocks, blockIndex + 1, rootClientId );
	};

	/**
	 * Get current source value for select
	 */
	const getCurrentSourceValue = () => {
		if ( sourceType === 'transistor' && showId ) {
			return `transistor:${ showId }`;
		} else if ( sourceType === 'rss' && rssFeedId ) {
			return `rss:${ rssFeedId }`;
		}
		return '';
	};

	/**
	 * Build source options
	 */
	const buildSourceOptions = () => {
		const options = [
			{ label: __( '-- Select a source --', 'podloom-podcast-player' ), value: '' },
		];

		if ( transistorShows.length > 0 ) {
			options.push( {
				label: __( '━━ Transistor.fm ━━', 'podloom-podcast-player' ),
				value: '__podloom_header__',
				disabled: true,
			} );
			transistorShows.forEach( ( show ) => {
				options.push( {
					label: '  ' + show.attributes.title,
					value: `transistor:${ show.id }`,
				} );
			} );
		}

		if ( rssFeeds.length > 0 ) {
			options.push( {
				label: __( '━━ RSS Feeds ━━', 'podloom-podcast-player' ),
				value: '__rss_header__',
				disabled: true,
			} );
			rssFeeds.forEach( ( feed ) => {
				options.push( {
					label: '  ' + feed.name,
					value: `rss:${ feed.id }`,
				} );
			} );
		}

		return options;
	};

	/**
	 * Get latest episode data for RSS
	 */
	const getLatestRssEpisodeData = () => {
		if ( episodes.length === 0 ) return null;
		const latest = episodes[ 0 ];
		if ( ! latest?.attributes ) return null;
		return {
			id: latest.id,
			title: latest.attributes.title,
			audio_url: latest.attributes.audio_url,
			image: latest.attributes.image,
			description: latest.attributes.description,
			date: latest.attributes.date,
			duration: latest.attributes.duration,
			podcast20: latest.attributes.podcast20 || null,
		};
	};

	// Loading state
	if ( ! isLoaded ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="microphone"
					label={ __( 'PodLoom Podcast Episode', 'podloom-podcast-player' ) }
				>
					<Spinner />
					<p>{ __( 'Loading sources...', 'podloom-podcast-player' ) }</p>
				</Placeholder>
			</div>
		);
	}

	// No sources configured
	if (
		! window.podloomData?.hasApiKey &&
		transistorShows.length === 0 &&
		rssFeeds.length === 0
	) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="microphone"
					label={ __( 'PodLoom Podcast Episode', 'podloom-podcast-player' ) }
				>
					<p>
						{ __(
							'Please configure your Transistor API key or add RSS feeds in the settings.',
							'podloom-podcast-player'
						) }
					</p>
					<Button
						variant="primary"
						href={ `${ window.podloomData.ajaxUrl.replace(
							'/wp-admin/admin-ajax.php',
							''
						) }/wp-admin/admin.php?page=podloom-settings` }
					>
						{ __( 'Go to Settings', 'podloom-podcast-player' ) }
					</Button>
				</Placeholder>
			</div>
		);
	}

	const supportsTransistorPlaylist = sourceType === 'transistor' && showSlug;
	const supportsRssPlaylist = sourceType === 'rss' && rssFeedId;
	const supportsLatest =
		( sourceType === 'transistor' && showSlug ) || ( sourceType === 'rss' && rssFeedId );

	// Get latest RSS episode for preview
	const latestRssEpisode = getLatestRssEpisodeData();
	const latestEpisodeKey = latestRssEpisode
		? latestRssEpisode.id || latestRssEpisode.title
		: null;

	// Render block content based on display mode
	const renderBlockContent = () => {
		// Transistor Latest Mode
		if ( displayMode === 'latest' && sourceType === 'transistor' && showId && showSlug ) {
			const iframeSrc = `https://share.transistor.fm/e/${ encodeURIComponent(
				showSlug
			) }/${ theme === 'dark' ? 'latest/dark' : 'latest' }`;
			return (
				<div
					dangerouslySetInnerHTML={ {
						__html: `<iframe width="100%" height="180" frameborder="no" scrolling="no" seamless src="${ iframeSrc }"></iframe>`,
					} }
				/>
			);
		}

		// Transistor Playlist Mode
		if ( displayMode === 'playlist' && sourceType === 'transistor' && showId && showSlug ) {
			const iframeSrc = `https://share.transistor.fm/e/${ encodeURIComponent(
				showSlug
			) }/${ theme === 'dark' ? 'playlist/dark' : 'playlist' }`;
			return (
				<div
					dangerouslySetInnerHTML={ {
						__html: `<iframe width="100%" height="${ parseInt(
							playlistHeight || 390
						) }" frameborder="no" scrolling="no" seamless src="${ iframeSrc }"></iframe>`,
					} }
				/>
			);
		}

		// Specific Episode Mode - Transistor
		if (
			displayMode === 'specific' &&
			sourceType === 'transistor' &&
			episodeId &&
			embedHtml
		) {
			return <div dangerouslySetInnerHTML={ { __html: embedHtml } } />;
		}

		// Latest Episode Mode - RSS
		if ( displayMode === 'latest' && sourceType === 'rss' && rssFeedId ) {
			if ( latestRssEpisode && rssTypography ) {
				// Check for server-rendered HTML in store
				const cachedHtml = select( STORE_NAME ).getRenderedEpisodeHtml( latestEpisodeKey );
				if ( cachedHtml ) {
					return <div dangerouslySetInnerHTML={ { __html: cachedHtml } } />;
				}
				return renderRssEpisode( latestRssEpisode, rssTypography );
			}
			return (
				<div
					style={ {
						background: '#f9f9f9',
						border: '1px solid #ddd',
						borderRadius: '8px',
						padding: '20px',
						minHeight: '180px',
						display: 'flex',
						flexDirection: 'column',
						justifyContent: 'center',
						alignItems: 'center',
					} }
				>
					<span
						className="dashicons dashicons-rss"
						style={ { fontSize: '48px', color: '#f8981d', marginBottom: '10px' } }
					/>
					<p
						style={ {
							margin: '0',
							fontSize: '14px',
							color: '#666',
							textAlign: 'center',
							fontWeight: '600',
						} }
					>
						{ __( 'Loading Latest RSS Episode...', 'podloom-podcast-player' ) }
					</p>
				</div>
			);
		}

		// Specific Episode Mode - RSS
		if (
			displayMode === 'specific' &&
			sourceType === 'rss' &&
			episodeId &&
			rssEpisodeData
		) {
			if ( rssTypography ) {
				if ( renderedHtml ) {
					return <div dangerouslySetInnerHTML={ { __html: renderedHtml } } />;
				}
				return renderRssEpisode( rssEpisodeData, rssTypography );
			}
			return (
				<div
					style={ {
						padding: '20px',
						background: '#f9f9f9',
						border: '1px solid #ddd',
						borderRadius: '8px',
					} }
				>
					<p style={ { margin: '0', fontSize: '14px', color: '#666' } }>
						{ __( 'Loading episode...', 'podloom-podcast-player' ) }
					</p>
				</div>
			);
		}

		// Playlist Mode - RSS
		if ( displayMode === 'playlist' && sourceType === 'rss' && rssFeedId ) {
			if ( playlistHtml ) {
				return <div dangerouslySetInnerHTML={ { __html: playlistHtml } } />;
			}
			return (
				<div
					style={ {
						background: '#f9f9f9',
						border: '1px solid #ddd',
						borderRadius: '8px',
						padding: '20px',
						minHeight: '200px',
						display: 'flex',
						flexDirection: 'column',
						justifyContent: 'center',
						alignItems: 'center',
					} }
				>
					<Spinner />
					<p style={ { margin: '10px 0 0 0', fontSize: '14px', color: '#666' } }>
						{ __( 'Loading playlist...', 'podloom-podcast-player' ) }
					</p>
				</div>
			);
		}

		// Placeholder
		return (
			<Placeholder
				icon="microphone"
				label={ __( 'PodLoom Podcast Episode', 'podloom-podcast-player' ) }
			>
				{ ! showId && ! rssFeedId ? (
					<p>
						{ __(
							'Please select a source from the sidebar.',
							'podloom-podcast-player'
						) }
					</p>
				) : displayMode === 'specific' && ! episodeId ? (
					<p>
						{ __(
							'Please select an episode from the sidebar.',
							'podloom-podcast-player'
						) }
					</p>
				) : (
					<p>
						{ __(
							'Please configure the block settings.',
							'podloom-podcast-player'
						) }
					</p>
				) }
			</Placeholder>
		);
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Episode Settings', 'podloom-podcast-player' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Select Source', 'podloom-podcast-player' ) }
						value={ getCurrentSourceValue() }
						options={ buildSourceOptions() }
						onChange={ handleSourceChange }
						help={ __(
							'Choose a Transistor show or RSS feed',
							'podloom-podcast-player'
						) }
					/>

					{ ( showId || rssFeedId ) && displayMode === 'specific' && (
						<div style={ { marginTop: '16px', marginBottom: '16px' } }>
							{ isEpisodesLoading && episodes.length === 0 ? (
								<div style={ { marginTop: '8px' } }>
									<Spinner />
									<p>
										{ __(
											'Loading episodes...',
											'podloom-podcast-player'
										) }
									</p>
								</div>
							) : (
								<ComboboxControl
									label={ __(
										'Search and Select Episode',
										'podloom-podcast-player'
									) }
									value={ episodeId }
									onChange={ ( selectedId ) => {
										if ( selectedId === '__load_more__' ) {
											loadMoreEpisodes();
											return;
										}
										const episode = episodes.find(
											( ep ) => ep.id === selectedId
										);
										if ( episode ) {
											selectEpisode( episode );
										} else {
											setAttributes( {
												episodeId: '',
												episodeTitle: '',
												episodeDescription: '',
												embedHtml: '',
												rssEpisodeData: null,
											} );
										}
									} }
									options={ [
										...episodes.map( ( episode ) => {
											const status = episode.attributes.status;
											const statusLabel =
												status === 'draft'
													? ' (Draft)'
													: status === 'scheduled'
													? ' (Scheduled)'
													: '';
											return {
												label: episode.attributes.title + statusLabel,
												value: episode.id,
											};
										} ),
										...( hasMore
											? [
													{
														label: isEpisodesLoading
															? __(
																	'Loading more episodes...',
																	'podloom-podcast-player'
															  )
															: __(
																	'Load More Episodes...',
																	'podloom-podcast-player'
															  ),
														value: '__load_more__',
													},
											  ]
											: [] ),
									] }
									help={
										episodes.length > 0
											? __(
													'Type to search episodes, or scroll to browse',
													'podloom-podcast-player'
											  )
											: null
									}
								/>
							) }
						</div>
					) }

					{ ( showId || rssFeedId ) && (
						<RadioControl
							label={ __( 'Display Mode', 'podloom-podcast-player' ) }
							selected={ displayMode }
							options={ [
								{
									label: __( 'Specific Episode', 'podloom-podcast-player' ),
									value: 'specific',
								},
								...( supportsLatest
									? [
											{
												label: __(
													'Latest Episode',
													'podloom-podcast-player'
												),
												value: 'latest',
											},
									  ]
									: [] ),
								...( supportsTransistorPlaylist || supportsRssPlaylist
									? [
											{
												label: __( 'Playlist', 'podloom-podcast-player' ),
												value: 'playlist',
											},
									  ]
									: [] ),
							] }
							onChange={ ( value ) => setAttributes( { displayMode: value } ) }
						/>
					) }

					{ displayMode === 'playlist' && supportsTransistorPlaylist && NumberControl && (
						<NumberControl
							label={ __( 'Playlist Height (px)', 'podloom-podcast-player' ) }
							value={ playlistHeight }
							onChange={ ( value ) =>
								setAttributes( { playlistHeight: parseInt( value ) || 390 } )
							}
							min={ 200 }
							max={ 1000 }
							step={ 10 }
						/>
					) }

					{ displayMode === 'playlist' && supportsRssPlaylist && NumberControl && (
						<NumberControl
							label={ __( 'Max Episodes', 'podloom-podcast-player' ) }
							value={ playlistMaxEpisodes }
							onChange={ ( value ) =>
								setAttributes( { playlistMaxEpisodes: parseInt( value ) || 25 } )
							}
							min={ 5 }
							step={ 5 }
						/>
					) }

					{ displayMode === 'playlist' && supportsRssPlaylist && (
						<RadioControl
							label={ __( 'Episode Order', 'podloom-podcast-player' ) }
							selected={ playlistOrder || 'episodic' }
							options={ [
								{
									label: __(
										'Episodic (newest first)',
										'podloom-podcast-player'
									),
									value: 'episodic',
								},
								{
									label: __(
										'Serial (oldest first)',
										'podloom-podcast-player'
									),
									value: 'serial',
								},
							] }
							onChange={ ( value ) => setAttributes( { playlistOrder: value } ) }
						/>
					) }

					{ sourceType === 'transistor' && showId && (
						<RadioControl
							label={ __( 'Player Theme', 'podloom-podcast-player' ) }
							selected={ theme }
							options={ [
								{ label: __( 'Light', 'podloom-podcast-player' ), value: 'light' },
								{ label: __( 'Dark', 'podloom-podcast-player' ), value: 'dark' },
							] }
							onChange={ handleThemeChange }
						/>
					) }

					{ displayMode === 'specific' && episodeId && (
						<div
							style={ {
								marginTop: '16px',
								padding: '12px',
								background: '#f0f0f0',
								borderRadius: '4px',
							} }
						>
							<strong>
								{ __( 'Selected Episode:', 'podloom-podcast-player' ) }
							</strong>
							<p style={ { margin: '8px 0 0 0', fontSize: '13px' } }>
								{ episodeTitle }
							</p>
							<Button
								variant="secondary"
								onClick={ handlePasteDescription }
								style={ { marginTop: '12px', width: '100%' } }
							>
								{ __( 'Paste Description', 'podloom-podcast-player' ) }
							</Button>
						</div>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>{ renderBlockContent() }</div>
		</>
	);
}

registerBlockType( metadata.name, {
	edit: EditComponent,
	save: () => null,
} );
