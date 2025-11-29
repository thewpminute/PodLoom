/**
 * PodLoom Episode Block
 *
 * Handles Transistor.fm episodes and RSS feed episodes with full Podcasting 2.0 support.
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
	__experimentalNumberControl as NumberControl
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { dispatch, select } from '@wordpress/data';

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

	const [ transistorShows, setTransistorShows ] = useState( [] );
	const [ rssFeeds, setRssFeeds ] = useState( [] );
	const [ episodes, setEpisodes ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ loadingShows, setLoadingShows ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ hasMorePages, setHasMorePages ] = useState( false );
	const [ isLoadingMore, setIsLoadingMore ] = useState( false );
	const [ latestRssEpisode, setLatestRssEpisode ] = useState( null );
	const [ rssTypography, setRssTypography ] = useState( null );
	const [ renderedEpisodeHtml, setRenderedEpisodeHtml ] = useState( {} );

	const blockProps = useBlockProps();

	// Load initial data
	useEffect( () => {
		loadInitialData();
	}, [] );

	// Load RSS typography if needed
	useEffect( () => {
		if ( sourceType === 'rss' && ! rssTypography ) {
			loadRssTypography();
		}
	}, [ sourceType ] );

	// Load latest RSS episode when in latest mode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'latest' && rssFeedId ) {
			loadLatestRssEpisode( rssFeedId );
		}
	}, [ sourceType, displayMode, rssFeedId ] );

	// Fetch rendered HTML for selected RSS episode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'specific' && rssEpisodeData ) {
			fetchRenderedEpisodeHtml( rssEpisodeData );
		}
	}, [ sourceType, displayMode, rssEpisodeData ] );

	// Fetch rendered HTML for latest RSS episode
	useEffect( () => {
		if ( sourceType === 'rss' && displayMode === 'latest' && latestRssEpisode ) {
			fetchRenderedEpisodeHtml( latestRssEpisode );
		}
	}, [ sourceType, displayMode, latestRssEpisode ] );

	// Set default show if available
	useEffect( () => {
		if ( ! showId && ! rssFeedId && window.podloomData?.defaultShow ) {
			const defaultTransistorShow = transistorShows.find(
				( show ) => show.id === window.podloomData.defaultShow
			);
			if ( defaultTransistorShow ) {
				setAttributes( {
					sourceType: 'transistor',
					showId: defaultTransistorShow.id,
					showTitle: defaultTransistorShow.attributes.title,
					showSlug: defaultTransistorShow.attributes.slug,
				} );
				return;
			}

			const defaultRssFeed = rssFeeds.find(
				( feed ) => feed.id === window.podloomData.defaultShow
			);
			if ( defaultRssFeed ) {
				setAttributes( {
					sourceType: 'rss',
					rssFeedId: defaultRssFeed.id,
					showTitle: defaultRssFeed.name,
				} );
			}
		}
	}, [ transistorShows, rssFeeds, showId, rssFeedId ] );

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

	// Load episodes when source changes
	useEffect( () => {
		if ( displayMode === 'specific' ) {
			if ( sourceType === 'transistor' && showId && ! episodeId ) {
				setCurrentPage( 1 );
				setEpisodes( [] );
				loadTransistorEpisodes( showId, 1 );
			} else if ( sourceType === 'rss' && rssFeedId && ! rssEpisodeData ) {
				setCurrentPage( 1 );
				setEpisodes( [] );
				loadRssEpisodes( rssFeedId, 1 );
			}
		}
	}, [ showId, rssFeedId, sourceType, displayMode ] );

	const loadInitialData = async () => {
		setLoadingShows( true );
		setError( '' );

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_block_init_data' );
			formData.append( 'nonce', window.podloomData.nonce );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success ) {
				if ( result.data.podloom_shows?.data ) {
					setTransistorShows( result.data.podloom_shows.data );
				}

				if ( result.data.rss_feeds ) {
					const feedsArray = Object.values( result.data.rss_feeds ).filter(
						( feed ) => feed.valid
					);
					setRssFeeds( feedsArray );
				}

				if ( result.data.rss_typography ) {
					setRssTypography( result.data.rss_typography );
				}
			} else {
				setError( __( 'Error loading block data.', 'podloom-podcast-player' ) );
			}
		} catch ( err ) {
			setError( __( 'Error loading block data.', 'podloom-podcast-player' ) );
		} finally {
			setLoadingShows( false );
		}
	};

	const loadTransistorEpisodes = async ( selectedShowId, page = 1, isLoadMore = false ) => {
		if ( isLoadMore ) {
			setIsLoadingMore( true );
		} else {
			setLoading( true );
		}
		setError( '' );

		try {
			const params = new URLSearchParams( {
				action: 'podloom_get_episodes',
				nonce: window.podloomData.nonce,
				show_id: selectedShowId,
				page: page.toString(),
				per_page: '20',
			} );

			const response = await fetch(
				`${ window.podloomData.ajaxUrl }?${ params.toString() }`
			);
			const result = await response.json();

			if ( result.success ) {
				const episodesData = result.data.data || [];
				const meta = result.data.meta || {};

				setEpisodes( ( prev ) => [ ...prev, ...episodesData ] );
				setHasMorePages( meta.currentPage < meta.totalPages );
				setCurrentPage( meta.currentPage );
			} else {
				setError(
					result.data.message ||
						__( 'Failed to load episodes', 'podloom-podcast-player' )
				);
			}
		} catch ( err ) {
			setError( __( 'Error loading episodes', 'podloom-podcast-player' ) );
		} finally {
			setLoading( false );
			setIsLoadingMore( false );
		}
	};

	const loadRssEpisodes = async ( feedId, page = 1, isLoadMore = false ) => {
		if ( isLoadMore ) {
			setIsLoadingMore( true );
		} else {
			setLoading( true );
		}
		setError( '' );

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_rss_episodes' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'feed_id', feedId );
			formData.append( 'page', page.toString() );
			formData.append( 'per_page', '20' );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success ) {
				const episodesData = result.data.episodes || [];

				const formattedEpisodes = episodesData.map( ( ep ) => ( {
					id: ep.id,
					type: 'rss_episode',
					attributes: {
						title: ep.title,
						status: 'published',
						audio_url: ep.audio_url,
						image: ep.image,
						description: ep.description,
						content: ep.content,
						date: ep.date,
						duration: ep.duration,
					},
				} ) );

				setEpisodes( ( prev ) => [ ...prev, ...formattedEpisodes ] );
				setHasMorePages( result.data.page < result.data.pages );
				setCurrentPage( result.data.page );
			} else {
				setError(
					result.data.message ||
						__( 'Failed to load RSS episodes', 'podloom-podcast-player' )
				);
			}
		} catch ( err ) {
			setError( __( 'Error loading RSS episodes', 'podloom-podcast-player' ) );
		} finally {
			setLoading( false );
			setIsLoadingMore( false );
		}
	};

	const loadMoreEpisodes = () => {
		const nextPage = currentPage + 1;
		if ( sourceType === 'transistor' ) {
			loadTransistorEpisodes( showId, nextPage, true );
		} else {
			loadRssEpisodes( rssFeedId, nextPage, true );
		}
	};

	const loadRssTypography = async () => {
		if ( rssTypography ) return;

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_rss_typography' );
			formData.append( 'nonce', window.podloomData.nonce );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success ) {
				setRssTypography( result.data );
			}
		} catch ( err ) {
			// Silently fail
		}
	};

	const loadLatestRssEpisode = async ( feedId ) => {
		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_rss_episodes' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'feed_id', feedId );
			formData.append( 'page', '1' );
			formData.append( 'per_page', '1' );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success && result.data.episodes?.length > 0 ) {
				setLatestRssEpisode( result.data.episodes[ 0 ] );
			}
		} catch ( err ) {
			// Silently fail
		}
	};

	const fetchRenderedEpisodeHtml = async ( episode ) => {
		if ( ! episode ) return;

		const episodeKey = episode.id || episode.title;

		if ( renderedEpisodeHtml[ episodeKey ] ) {
			return;
		}

		try {
			const formData = new FormData();
			formData.append( 'action', 'podloom_render_rss_episode' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'episode_data', JSON.stringify( episode ) );
			formData.append( 'feed_id', rssFeedId );

			const response = await fetch( window.podloomData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );
			const result = await response.json();

			if ( result.success && result.data.html ) {
				setRenderedEpisodeHtml( ( prev ) => ( {
					...prev,
					[ episodeKey ]: result.data.html,
				} ) );
			}
		} catch ( err ) {
			// Silently fail
		}
	};

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

		setEpisodes( [] );
	};

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

	const getCurrentSourceValue = () => {
		if ( sourceType === 'transistor' && showId ) {
			return `transistor:${ showId }`;
		} else if ( sourceType === 'rss' && rssFeedId ) {
			return `rss:${ rssFeedId }`;
		}
		return '';
	};

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

	// Loading state
	if ( loadingShows ) {
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
				const episodeKey = latestRssEpisode.id || latestRssEpisode.title;
				if ( renderedEpisodeHtml[ episodeKey ] ) {
					return (
						<div
							dangerouslySetInnerHTML={ {
								__html: renderedEpisodeHtml[ episodeKey ],
							} }
						/>
					);
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
				const episodeKey = rssEpisodeData.id || rssEpisodeData.title;
				if ( renderedEpisodeHtml[ episodeKey ] ) {
					return (
						<div
							dangerouslySetInnerHTML={ {
								__html: renderedEpisodeHtml[ episodeKey ],
							} }
						/>
					);
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
					<span
						className="dashicons dashicons-playlist-audio"
						style={ { fontSize: '48px', color: '#4caf50', marginBottom: '10px' } }
					/>
					<p
						style={ {
							margin: '0',
							fontSize: '16px',
							color: '#333',
							textAlign: 'center',
							fontWeight: '600',
						} }
					>
						{ __( 'RSS Playlist Player', 'podloom-podcast-player' ) }
					</p>
					<p
						style={ {
							margin: '8px 0 0 0',
							fontSize: '13px',
							color: '#666',
							textAlign: 'center',
						} }
					>
						{ __( 'Displays ', 'podloom-podcast-player' ) }
						{ playlistMaxEpisodes }
						{ __( ' episodes with an Episodes tab', 'podloom-podcast-player' ) }
					</p>
					<p
						style={ {
							margin: '4px 0 0 0',
							fontSize: '12px',
							color: '#999',
							textAlign: 'center',
						} }
					>
						{ __( 'Preview available on frontend', 'podloom-podcast-player' ) }
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
							{ loading && episodes.length === 0 ? (
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
										...( hasMorePages
											? [
													{
														label: isLoadingMore
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
							max={ 100 }
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
