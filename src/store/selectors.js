/**
 * Store Selectors
 *
 * Selector functions for accessing the PodLoom data store.
 *
 * @package PodLoom
 */

/**
 * Check if initial data has been loaded
 *
 * @param {Object} state Store state
 * @return {boolean} True if initial data is loaded
 */
export function isInitialDataLoaded( state ) {
	return state.initialDataLoaded;
}

/**
 * Get Transistor shows
 *
 * @param {Object} state Store state
 * @return {Array} Array of Transistor show objects
 */
export function getTransistorShows( state ) {
	return state.transistorShows;
}

/**
 * Get RSS feeds
 *
 * @param {Object} state Store state
 * @return {Array} Array of RSS feed objects
 */
export function getRssFeeds( state ) {
	return state.rssFeeds;
}

/**
 * Get all sources combined (Transistor shows + RSS feeds)
 *
 * Returns an object with separate arrays for building dropdown options.
 *
 * @param {Object} state Store state
 * @return {Object} Object with transistorShows and rssFeeds arrays
 */
export function getAllSources( state ) {
	return {
		transistorShows: state.transistorShows,
		rssFeeds: state.rssFeeds,
		isLoaded: state.initialDataLoaded,
	};
}

/**
 * Get RSS typography settings
 *
 * @param {Object} state Store state
 * @return {Object|null} Typography settings or null if not loaded
 */
export function getRssTypography( state ) {
	return state.rssTypography;
}

/**
 * Get episodes data for a specific source
 *
 * @param {Object} state     Store state
 * @param {string} sourceKey Source key (e.g., 'transistor:123' or 'rss:abc')
 * @return {Object|undefined} Episodes data object or undefined if not loaded
 */
export function getEpisodesData( state, sourceKey ) {
	return state.episodes[ sourceKey ];
}

/**
 * Get episodes array for a specific source
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {Array} Array of episode objects (empty if not loaded)
 */
export function getEpisodes( state, sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;
	return state.episodes[ sourceKey ]?.items || [];
}

/**
 * Check if episodes are loading for a source
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {boolean} True if loading
 */
export function isEpisodesLoading( state, sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;
	return state.episodes[ sourceKey ]?.loading || false;
}

/**
 * Check if more episodes are available for a source
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {boolean} True if more pages available
 */
export function hasMoreEpisodes( state, sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;
	return state.episodes[ sourceKey ]?.hasMore ?? true;
}

/**
 * Get current page for episodes
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {number} Current page number (0 if not loaded)
 */
export function getEpisodesPage( state, sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;
	return state.episodes[ sourceKey ]?.page || 0;
}

/**
 * Get latest episode for a source
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {Object|null} Latest episode or null if not loaded
 */
export function getLatestEpisode( state, sourceType, sourceId ) {
	const episodes = getEpisodes( state, sourceType, sourceId );
	return episodes.length > 0 ? episodes[ 0 ] : null;
}

/**
 * Get subscribe podcasts list
 *
 * @param {Object} state Store state
 * @return {Array} Array of podcast objects for subscribe block
 */
export function getSubscribePodcasts( state ) {
	return state.subscribePodcasts;
}

/**
 * Check if subscribe podcasts have been loaded
 *
 * @param {Object} state Store state
 * @return {boolean} True if podcasts are loaded
 */
export function hasSubscribePodcasts( state ) {
	return state.subscribePodcasts.length > 0;
}

/**
 * Get subscribe preview data for a source
 *
 * @param {Object} state       Store state
 * @param {string} sourceId    Source identifier
 * @param {string} colorMode   Color mode to match
 * @param {string} customColor Custom color to match (optional)
 * @return {Array|null} Array of subscribe links or null if not cached/mismatched
 */
export function getSubscribePreview( state, sourceId, colorMode, customColor ) {
	const preview = state.subscribePreviews[ sourceId ];
	if ( ! preview ) {
		return null;
	}
	// Check if cached preview matches requested color settings
	if ( preview.colorMode !== colorMode ) {
		return null;
	}
	if ( colorMode === 'custom' && preview.customColor !== customColor ) {
		return null;
	}
	return preview.links;
}

/**
 * Get rendered episode HTML from cache
 *
 * @param {Object} state      Store state
 * @param {string} episodeKey Episode identifier
 * @return {string|null} Rendered HTML or null if not cached
 */
export function getRenderedEpisodeHtml( state, episodeKey ) {
	return state.renderedEpisodeHtml[ episodeKey ] || null;
}

/**
 * Get rendered playlist HTML from cache
 *
 * @param {Object} state       Store state
 * @param {string} playlistKey Playlist identifier (feedId_maxEpisodes_order)
 * @return {string|null} Rendered HTML or null if not cached
 */
export function getRenderedPlaylistHtml( state, playlistKey ) {
	return state.renderedPlaylistHtml[ playlistKey ] || null;
}

/**
 * Check if playlist HTML is loading
 *
 * @param {Object} state       Store state
 * @param {string} playlistKey Playlist identifier
 * @return {boolean} True if loading
 */
export function isPlaylistHtmlLoading( state, playlistKey ) {
	return state.playlistHtmlLoading[ playlistKey ] || false;
}

/**
 * Get error for a specific key
 *
 * @param {Object} state Store state
 * @param {string} key   Error key
 * @return {string|null} Error message or null
 */
export function getError( state, key ) {
	return state.errors[ key ] || null;
}

/**
 * Check if a source has any error
 *
 * @param {Object} state      Store state
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 * @return {string|null} Error message or null
 */
export function getSourceError( state, sourceType, sourceId ) {
	const sourceKey = `${ sourceType }:${ sourceId }`;
	return state.errors[ sourceKey ] || null;
}
