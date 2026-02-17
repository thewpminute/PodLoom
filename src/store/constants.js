/**
 * Store Constants
 *
 * Action type constants for the PodLoom data store.
 *
 * @package PodLoom
 */

export const STORE_NAME = 'podloom/data';

// Initial data actions
export const SET_INITIAL_DATA_LOADED = 'SET_INITIAL_DATA_LOADED';
export const SET_TRANSISTOR_SHOWS = 'SET_TRANSISTOR_SHOWS';
export const SET_RSS_FEEDS = 'SET_RSS_FEEDS';
export const SET_RSS_TYPOGRAPHY = 'SET_RSS_TYPOGRAPHY';

// Episode actions
export const SET_EPISODES = 'SET_EPISODES';
export const APPEND_EPISODES = 'APPEND_EPISODES';
export const SET_EPISODES_LOADING = 'SET_EPISODES_LOADING';

// Subscribe podcasts actions
export const SET_SUBSCRIBE_PODCASTS = 'SET_SUBSCRIBE_PODCASTS';
export const SET_SUBSCRIBE_PREVIEW = 'SET_SUBSCRIBE_PREVIEW';

// Rendered HTML cache actions
export const SET_RENDERED_EPISODE_HTML = 'SET_RENDERED_EPISODE_HTML';
export const SET_RENDERED_PLAYLIST_HTML = 'SET_RENDERED_PLAYLIST_HTML';
export const SET_PLAYLIST_HTML_LOADING = 'SET_PLAYLIST_HTML_LOADING';

// Error actions
export const SET_ERROR = 'SET_ERROR';
export const CLEAR_ERROR = 'CLEAR_ERROR';
