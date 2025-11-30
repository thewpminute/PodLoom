/**
 * PodLoom Data Store
 *
 * Central data store for managing podcast data across all blocks.
 * Uses @wordpress/data for state management with automatic caching.
 *
 * @package PodLoom
 */

import { createReduxStore, register } from '@wordpress/data';

import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';

/**
 * Custom controls for handling async operations
 *
 * These controls handle the yield statements in our action generators.
 */
const controls = {
	FETCH( action ) {
		return fetch( action.url, action.options );
	},
	PARSE_JSON( action ) {
		return action.response.json();
	},
	SELECT( action ) {
		return wp.data.select( STORE_NAME )[ action.selectorName ]( ...( action.args || [] ) );
	},
};

/**
 * Create and register the store
 */
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
	controls,
} );

register( store );

export { STORE_NAME };
export default store;
