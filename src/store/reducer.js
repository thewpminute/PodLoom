/**
 * Store Reducer
 *
 * Manages state updates for the PodLoom data store.
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
	SET_RENDERED_PLAYLIST_HTML,
	SET_PLAYLIST_HTML_LOADING,
	SET_ERROR,
	CLEAR_ERROR,
} from './constants';

/**
 * Default state shape
 */
const DEFAULT_STATE = {
	// Initial data loading flag
	initialDataLoaded: false,

	// Podcast sources
	transistorShows: [],
	rssFeeds: [],

	// RSS typography settings (shared across all RSS blocks)
	rssTypography: null,

	// Episodes by source key (format: 'transistor:123' or 'rss:abc')
	// Each entry: { items: [], page: 1, hasMore: true, loading: false }
	episodes: {},

	// Subscribe block data
	subscribePodcasts: [],
	subscribePreviews: {}, // Keyed by sourceId

	// Rendered episode HTML cache (for P2.0 tabs)
	renderedEpisodeHtml: {},

	// Rendered playlist HTML cache (keyed by playlistKey = feedId_maxEpisodes_order)
	renderedPlaylistHtml: {},
	playlistHtmlLoading: {},

	// Error states keyed by identifier
	errors: {},
};

/**
 * Reducer function
 *
 * @param {Object} state  Current state
 * @param {Object} action Action object
 * @return {Object} New state
 */
export default function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case SET_INITIAL_DATA_LOADED:
			return {
				...state,
				initialDataLoaded: true,
			};

		case SET_TRANSISTOR_SHOWS:
			return {
				...state,
				transistorShows: action.shows,
			};

		case SET_RSS_FEEDS:
			return {
				...state,
				rssFeeds: action.feeds,
			};

		case SET_RSS_TYPOGRAPHY:
			return {
				...state,
				rssTypography: action.typography,
			};

		case SET_EPISODES:
			return {
				...state,
				episodes: {
					...state.episodes,
					[ action.sourceKey ]: {
						items: action.episodes,
						page: action.page,
						hasMore: action.hasMore,
						loading: false,
					},
				},
			};

		case APPEND_EPISODES: {
			const existingEpisodes = state.episodes[ action.sourceKey ]?.items || [];
			return {
				...state,
				episodes: {
					...state.episodes,
					[ action.sourceKey ]: {
						items: [ ...existingEpisodes, ...action.episodes ],
						page: action.page,
						hasMore: action.hasMore,
						loading: false,
					},
				},
			};
		}

		case SET_EPISODES_LOADING:
			return {
				...state,
				episodes: {
					...state.episodes,
					[ action.sourceKey ]: {
						...( state.episodes[ action.sourceKey ] || {
							items: [],
							page: 0,
							hasMore: true,
						} ),
						loading: action.loading,
					},
				},
			};

		case SET_SUBSCRIBE_PODCASTS:
			return {
				...state,
				subscribePodcasts: action.podcasts,
			};

		case SET_SUBSCRIBE_PREVIEW:
			return {
				...state,
				subscribePreviews: {
					...state.subscribePreviews,
					[ action.sourceId ]: {
						links: action.links,
						colorMode: action.colorMode,
						customColor: action.customColor,
					},
				},
			};

		case SET_RENDERED_EPISODE_HTML:
			return {
				...state,
				renderedEpisodeHtml: {
					...state.renderedEpisodeHtml,
					[ action.episodeKey ]: action.html,
				},
			};

		case SET_RENDERED_PLAYLIST_HTML:
			return {
				...state,
				renderedPlaylistHtml: {
					...state.renderedPlaylistHtml,
					[ action.playlistKey ]: action.html,
				},
				playlistHtmlLoading: {
					...state.playlistHtmlLoading,
					[ action.playlistKey ]: false,
				},
			};

		case SET_PLAYLIST_HTML_LOADING:
			return {
				...state,
				playlistHtmlLoading: {
					...state.playlistHtmlLoading,
					[ action.playlistKey ]: action.loading,
				},
			};

		case SET_ERROR:
			return {
				...state,
				errors: {
					...state.errors,
					[ action.key ]: action.error,
				},
			};

		case CLEAR_ERROR: {
			const { [ action.key ]: removed, ...remainingErrors } = state.errors;
			return {
				...state,
				errors: remainingErrors,
			};
		}

		default:
			return state;
	}
}
