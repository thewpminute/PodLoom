/**
 * Store Resolvers
 *
 * Async data fetchers that automatically run when selectors return undefined.
 *
 * @package PodLoom
 */

import {
	setInitialDataLoaded,
	setTransistorShows,
	setRssFeeds,
	setRssTypography,
	setEpisodes,
	setEpisodesLoading,
	setSubscribePodcasts,
	setError,
} from './actions';

/**
 * Resolve initial data (shows, feeds, typography)
 *
 * This resolver runs when getAllSources() is called and data isn't loaded yet.
 */
export function* getAllSources() {
	try {
		const formData = new FormData();
		formData.append( 'action', 'podloom_get_block_init_data' );
		formData.append( 'nonce', window.podloomData.nonce );

		const response = yield {
			type: 'FETCH',
			url: window.podloomData.ajaxUrl,
			options: {
				method: 'POST',
				body: formData,
			},
		};

		const result = yield {
			type: 'PARSE_JSON',
			response,
		};

		if ( result.success ) {
			// Set Transistor shows
			if ( result.data.podloom_shows?.data ) {
				yield setTransistorShows( result.data.podloom_shows.data );
			}

			// Set RSS feeds (filter to valid only)
			if ( result.data.rss_feeds ) {
				const feedsArray = Object.values( result.data.rss_feeds ).filter(
					( feed ) => feed.valid
				);
				yield setRssFeeds( feedsArray );
			}

			// Set RSS typography
			if ( result.data.rss_typography ) {
				yield setRssTypography( result.data.rss_typography );
			}

			yield setInitialDataLoaded();
		} else {
			yield setError( 'initialData', 'Error loading block data.' );
			yield setInitialDataLoaded();
		}
	} catch ( error ) {
		yield setError( 'initialData', 'Error loading block data.' );
		yield setInitialDataLoaded();
	}
}

/**
 * Resolve episodes for a specific source
 *
 * This resolver runs when getEpisodes() is called for a source that hasn't been loaded.
 *
 * @param {string} sourceType 'transistor' or 'rss'
 * @param {string} sourceId   Show ID or feed ID
 */
export function* getEpisodes( sourceType, sourceId ) {
	if ( ! sourceId ) {
		return;
	}

	const sourceKey = `${ sourceType }:${ sourceId }`;

	yield setEpisodesLoading( sourceKey, true );

	try {
		if ( sourceType === 'transistor' ) {
			// Fetch Transistor episodes
			const params = new URLSearchParams( {
				action: 'podloom_get_episodes',
				nonce: window.podloomData.nonce,
				show_id: sourceId,
				page: '1',
				per_page: '20',
			} );

			const response = yield {
				type: 'FETCH',
				url: `${ window.podloomData.ajaxUrl }?${ params.toString() }`,
			};

			const result = yield {
				type: 'PARSE_JSON',
				response,
			};

			if ( result.success ) {
				const episodesData = result.data.data || [];
				const meta = result.data.meta || {};
				yield setEpisodes(
					sourceKey,
					episodesData,
					meta.currentPage || 1,
					( meta.currentPage || 1 ) < ( meta.totalPages || 1 )
				);
			} else {
				yield setError(
					sourceKey,
					result.data?.message || 'Failed to load episodes'
				);
				yield setEpisodesLoading( sourceKey, false );
			}
		} else if ( sourceType === 'rss' ) {
			// Fetch RSS episodes
			const formData = new FormData();
			formData.append( 'action', 'podloom_get_rss_episodes' );
			formData.append( 'nonce', window.podloomData.nonce );
			formData.append( 'feed_id', sourceId );
			formData.append( 'page', '1' );
			formData.append( 'per_page', '20' );

			const response = yield {
				type: 'FETCH',
				url: window.podloomData.ajaxUrl,
				options: {
					method: 'POST',
					body: formData,
				},
			};

			const result = yield {
				type: 'PARSE_JSON',
				response,
			};

			if ( result.success ) {
				const episodesData = result.data.episodes || [];
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
				yield setEpisodes(
					sourceKey,
					formattedEpisodes,
					result.data.page || 1,
					( result.data.page || 1 ) < ( result.data.pages || 1 )
				);
			} else {
				yield setError(
					sourceKey,
					result.data?.message || 'Failed to load RSS episodes'
				);
				yield setEpisodesLoading( sourceKey, false );
			}
		}
	} catch ( error ) {
		yield setError( sourceKey, 'Error loading episodes' );
		yield setEpisodesLoading( sourceKey, false );
	}
}

/**
 * Resolve subscribe podcasts
 *
 * This resolver runs when getSubscribePodcasts() is called and data isn't loaded.
 */
export function* getSubscribePodcasts() {
	try {
		const formData = new FormData();
		formData.append( 'action', 'podloom_get_subscribe_podcasts' );
		formData.append( 'nonce', window.podloomData.nonce );

		const response = yield {
			type: 'FETCH',
			url: window.podloomData.ajaxUrl,
			options: {
				method: 'POST',
				body: formData,
			},
		};

		const result = yield {
			type: 'PARSE_JSON',
			response,
		};

		if ( result.success ) {
			yield setSubscribePodcasts( result.data.podcasts || [] );
		} else {
			yield setSubscribePodcasts( [] );
		}
	} catch ( error ) {
		console.error( 'Error loading subscribe podcasts:', error );
		yield setSubscribePodcasts( [] );
	}
}
