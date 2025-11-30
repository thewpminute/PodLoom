/**
 * Store Actions
 *
 * Action creators for the PodLoom data store.
 *
 * @package PodLoom
 */

import {
	SET_INITIAL_DATA_LOADED,
	SET_TRANSISTOR_SHOWS,
	SET_RSS_FEEDS,
	SET_RSS_TYPOGRAPHY,
	SET_EPISODES,
	APPEND_EPISODES,
	SET_EPISODES_LOADING,
	SET_SUBSCRIBE_PODCASTS,
	SET_SUBSCRIBE_PREVIEW,
	SET_RENDERED_EPISODE_HTML,
	SET_ERROR,
	CLEAR_ERROR,
	STORE_NAME,
} from './constants';

/**
 * Mark initial data as loaded
 *
 * @return {Object} Action object
 */
export function setInitialDataLoaded() {
	return {
		type: SET_INITIAL_DATA_LOADED,
	};
}

/**
 * Set Transistor shows
 *
 * @param {Array} shows Array of Transistor show objects
 * @return {Object} Action object
 */
export function setTransistorShows( shows ) {
	return {
		type: SET_TRANSISTOR_SHOWS,
		shows,
	};
}

/**
 * Set RSS feeds
 *
 * @param {Array} feeds Array of RSS feed objects
 * @return {Object} Action object
 */
export function setRssFeeds( feeds ) {
	return {
		type: SET_RSS_FEEDS,
		feeds,
	};
}

/**
 * Set RSS typography settings
 *
 * @param {Object} typography Typography settings object
 * @return {Object} Action object
 */
export function setRssTypography( typography ) {
	return {
		type: SET_RSS_TYPOGRAPHY,
		typography,
	};
}

/**
 * Set episodes for a source (replaces existing)
 *
 * @param {string}  sourceKey Source key (e.g., 'transistor:123' or 'rss:abc')
 * @param {Array}   episodes  Array of episode objects
 * @param {number}  page      Current page number
 * @param {boolean} hasMore   Whether more pages are available
 * @return {Object} Action object
 */
export function setEpisodes( sourceKey, episodes, page, hasMore ) {
	return {
		type: SET_EPISODES,
		sourceKey,
		episodes,
		page,
		hasMore,
	};
}

/**
 * Append episodes to existing list (for pagination)
 *
 * @param {string}  sourceKey Source key
 * @param {Array}   episodes  Array of episode objects to append
 * @param {number}  page      Current page number
 * @param {boolean} hasMore   Whether more pages are available
 * @return {Object} Action object
 */
export function appendEpisodes( sourceKey, episodes, page, hasMore ) {
	return {
		type: APPEND_EPISODES,
		sourceKey,
		episodes,
		page,
		hasMore,
	};
}

/**
 * Set loading state for episodes
 *
 * @param {string}  sourceKey Source key
 * @param {boolean} loading   Loading state
 * @return {Object} Action object
 */
export function setEpisodesLoading( sourceKey, loading ) {
	return {
		type: SET_EPISODES_LOADING,
		sourceKey,
		loading,
	};
}

/**
 * Set subscribe podcasts list
 *
 * @param {Array} podcasts Array of podcast objects for subscribe block
 * @return {Object} Action object
 */
export function setSubscribePodcasts( podcasts ) {
	return {
		type: SET_SUBSCRIBE_PODCASTS,
		podcasts,
	};
}

/**
 * Set subscribe preview data for a source
 *
 * @param {string} sourceId    Source identifier
 * @param {Array}  links       Array of subscribe link objects
 * @param {string} colorMode   Color mode used for this preview
 * @param {string} customColor Custom color (if colorMode is 'custom')
 * @return {Object} Action object
 */
export function setSubscribePreview( sourceId, links, colorMode, customColor ) {
	return {
		type: SET_SUBSCRIBE_PREVIEW,
		sourceId,
		links,
		colorMode,
		customColor,
	};
}

/**
 * Set rendered episode HTML (for P2.0 tabs cache)
 *
 * @param {string} episodeKey Unique episode identifier
 * @param {string} html       Rendered HTML string
 * @return {Object} Action object
 */
export function setRenderedEpisodeHtml( episodeKey, html ) {
	return {
		type: SET_RENDERED_EPISODE_HTML,
		episodeKey,
		html,
	};
}

/**
 * Set an error
 *
 * @param {string} key   Error identifier
 * @param {string} error Error message
 * @return {Object} Action object
 */
export function setError( key, error ) {
	return {
		type: SET_ERROR,
		key,
		error,
	};
}

/**
 * Clear an error
 *
 * @param {string} key Error identifier to clear
 * @return {Object} Action object
 */
export function clearError( key ) {
	return {
		type: CLEAR_ERROR,
		key,
	};
}

/**
 * Fetch more episodes (thunk action for pagination)
 *
 * This is a generator function that fetches the next page of episodes.
 *
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 */
export function* fetchMoreEpisodes( sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;

	// Get current state to determine next page
	const currentData = yield {
		type: 'SELECT',
		selectorName: 'getEpisodesData',
		args: [ sourceKey ],
	};

	const nextPage = ( currentData?.page || 0 ) + 1;

	// Set loading state
	yield setEpisodesLoading( sourceKey, true );

	try {
		let response;

		if ( sourceType === 'transistor' ) {
			// Fetch Transistor episodes
			const params = new URLSearchParams( {
				action: 'podloom_get_episodes',
				nonce: window.podloomData.nonce,
				show_id: sourceId,
				page: nextPage.toString(),
				per_page: '20',
			} );

			const fetchResponse = yield {
				type: 'FETCH',
				url: `${ window.podloomData.ajaxUrl }?${ params.toString() }`,
			};
			response = yield {
				type: 'PARSE_JSON',
				response: fetchResponse,
			};

			if ( response.success ) {
				const episodesData = response.data.data || [];
				const meta = response.data.meta || {};
				yield appendEpisodes(
					sourceKey,
					episodesData,
					meta.currentPage,
					meta.currentPage < meta.totalPages
				);
			} else {
				yield setError(
					sourceKey,
					response.data?.message || 'Failed to load episodes'
				);
			}
		} else {
			// Fetch RSS episodes
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_rss_episodes' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'feed_id', sourceId );
			formData.append( 'page', nextPage.toString() );
			formData.append( 'per_page', '20' );

			const fetchResponse = yield {
				type: 'FETCH',
				url: window.podloomData.ajaxUrl,
				options: {
					method: 'POST',
					body: formData,
				},
			};
			response = yield {
				type: 'PARSE_JSON',
				response: fetchResponse,
			};

			if ( response.success ) {
				const episodesData = response.data.episodes || [];
				// Format RSS episodes to match Transistor structure
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
						podcast20: ep.podcast20 || null,
					},
				} ) );
				yield appendEpisodes(
					sourceKey,
					formattedEpisodes,
					response.data.page,
					response.data.page < response.data.pages
				);
			} else {
				yield setError(
					sourceKey,
					response.data?.message || 'Failed to load RSS episodes'
				);
			}
		}
	} catch ( error ) {
		yield setError( sourceKey, 'Error loading episodes' );
		yield setEpisodesLoading( sourceKey, false );
	}
}

/**
 * Fetch rendered episode HTML from server
 *
 * @param {Object} episode Episode data object
 * @param {string} feedId  RSS feed ID
 */
export function* fetchRenderedEpisodeHtml( episode, feedId ) {
	if ( ! episode ) {
		return;
	}

	const episodeKey = episode.id || episode.title;

	try {
		const formData = new FormData();
		formData.append( 'action', 'podloom_render_rss_episode' );
		formData.append( 'nonce', window.podloomData.nonce );
		formData.append( 'episode_data', JSON.stringify( episode ) );
		formData.append( 'feed_id', feedId );

		const fetchResponse = yield {
			type: 'FETCH',
			url: window.podloomData.ajaxUrl,
			options: {
				method: 'POST',
				body: formData,
			},
		};
		const response = yield {
			type: 'PARSE_JSON',
			response: fetchResponse,
		};

		if ( response.success && response.data.html ) {
			yield setRenderedEpisodeHtml( episodeKey, response.data.html );
		}
	} catch ( error ) {
		// Silently fail - will fall back to JavaScript rendering
		console.warn( 'PodLoom: Failed to fetch rendered episode HTML', error );
	}
}

/**
 * Fetch subscribe preview data
 *
 * @param {string} sourceId    Source identifier
 * @param {string} colorMode   Color mode
 * @param {string} customColor Custom color (optional)
 */
export function* fetchSubscribePreview( sourceId, colorMode, customColor ) {
	try {
		const formData = new FormData();
		formData.append( 'action', 'podloom_get_subscribe_preview' );
		formData.append( 'nonce', window.podloomData.nonce );
		formData.append( 'source_id', sourceId );
		formData.append( 'color_mode', colorMode );
		if ( colorMode === 'custom' ) {
			formData.append( 'custom_color', customColor );
		}

		const fetchResponse = yield {
			type: 'FETCH',
			url: window.podloomData.ajaxUrl,
			options: {
				method: 'POST',
				body: formData,
			},
		};
		const response = yield {
			type: 'PARSE_JSON',
			response: fetchResponse,
		};

		if ( response.success ) {
			yield setSubscribePreview(
				sourceId,
				response.data.links || [],
				colorMode,
				customColor
			);
		} else {
			yield setSubscribePreview( sourceId, [], colorMode, customColor );
		}
	} catch ( error ) {
		console.error( 'Error loading subscribe preview:', error );
		yield setSubscribePreview( sourceId, [], colorMode, customColor );
	}
}
