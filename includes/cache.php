<?php
/**
 * Cache Helper Functions
 *
 * Provides object cache support with automatic fallback to transients.
 * Uses Redis/Memcached when available for better performance.
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get cached value with object cache support
 *
 * @param string $key Cache key
 * @param string $group Cache group (default: 'podloom')
 * @return mixed|false Cached value or false if not found
 */
function podloom_cache_get( $key, $group = 'podloom' ) {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_get( $key, $group );
	} else {
		// Fallback to transients - prefix key with group
		return get_transient( $group . '_' . $key );
	}
}

/**
 * Set cached value with object cache support
 *
 * @param string $key Cache key
 * @param mixed  $value Value to cache
 * @param string $group Cache group (default: 'podloom')
 * @param int    $expiration Expiration time in seconds (0 = no expiration for object cache)
 * @return bool True on success, false on failure
 */
function podloom_cache_set( $key, $value, $group = 'podloom', $expiration = 0 ) {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_set( $key, $value, $group, $expiration );
	} else {
		// Fallback to transients - prefix key with group
		return set_transient( $group . '_' . $key, $value, $expiration );
	}
}

/**
 * Delete cached value with object cache support
 *
 * @param string $key Cache key
 * @param string $group Cache group (default: 'podloom')
 * @return bool True on success, false on failure
 */
function podloom_cache_delete( $key, $group = 'podloom' ) {
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_delete( $key, $group );
	} else {
		// Fallback to transients - prefix key with group
		return delete_transient( $group . '_' . $key );
	}
}
