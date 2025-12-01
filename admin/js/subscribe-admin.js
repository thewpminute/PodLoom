/**
 * Subscribe Links Admin JavaScript
 *
 * Handles accordion toggling, saving links, and Transistor sync.
 *
 * @package PodLoom
 * @since 2.10.0
 */

(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		initAccordions();
		initSaveButtons();
		initSyncButtons();
	});

	/**
	 * Initialize accordion toggles.
	 */
	function initAccordions() {
		var toggles = document.querySelectorAll('.podloom-subscribe-podcast__toggle');

		toggles.forEach(function(toggle) {
			toggle.addEventListener('click', function() {
				var expanded = this.getAttribute('aria-expanded') === 'true';
				var content = this.closest('.podloom-subscribe-podcast').querySelector('.podloom-subscribe-podcast__content');

				this.setAttribute('aria-expanded', !expanded);

				if (content) {
					if (expanded) {
						content.style.display = 'none';
						content.setAttribute('aria-hidden', 'true');
					} else {
						content.style.display = 'block';
						content.setAttribute('aria-hidden', 'false');
					}
				}
			});
		});
	}

	/**
	 * Initialize save buttons.
	 */
	function initSaveButtons() {
		var buttons = document.querySelectorAll('.podloom-save-subscribe-links');

		buttons.forEach(function(button) {
			button.addEventListener('click', function() {
				var podcast = this.closest('.podloom-subscribe-podcast');
				var sourceId = podcast.dataset.sourceId;
				var inputs = podcast.querySelectorAll('.podloom-subscribe-link__input');
				var status = podcast.querySelector('.podloom-save-status');
				var links = {};
				var strings = podloomData.strings || {};

				inputs.forEach(function(input) {
					var platform = input.dataset.platform;
					var value = input.value.trim();
					if (value) {
						links[platform] = value;
					}
				});

				// Disable button during save.
				button.disabled = true;
				button.textContent = strings.saving || 'Saving...';
				status.textContent = '';
				status.className = 'podloom-save-status';

				// Send AJAX request.
				var formData = new FormData();
				formData.append('action', 'podloom_save_subscribe_links');
				formData.append('nonce', podloomData.nonce);
				formData.append('source_id', sourceId);
				formData.append('links', JSON.stringify(links));

				fetch(podloomData.ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						status.textContent = strings.saved || 'Saved!';
						status.className = 'podloom-save-status success';
						updateLinkCount(podcast, Object.keys(links).length);
					} else {
						status.textContent = data.data || (strings.error || 'Error saving');
						status.className = 'podloom-save-status error';
					}
				})
				.catch(function() {
					status.textContent = strings.error || 'Error saving';
					status.className = 'podloom-save-status error';
				})
				.finally(function() {
					button.disabled = false;
					button.textContent = strings.saveLinks || 'Save Links';
				});
			});
		});
	}

	/**
	 * Initialize Transistor sync buttons.
	 */
	function initSyncButtons() {
		var buttons = document.querySelectorAll('.podloom-sync-transistor');

		buttons.forEach(function(button) {
			button.addEventListener('click', function() {
				var showId = this.dataset.showId;
				var podcast = this.closest('.podloom-subscribe-podcast');
				var status = podcast.querySelector('.podloom-sync-status');
				var strings = podloomData.strings || {};

				// Disable button during sync.
				button.disabled = true;
				var originalText = button.innerHTML;
				button.innerHTML = '<span class="dashicons dashicons-update spinning"></span> ' + (strings.syncing || 'Syncing...');
				status.textContent = '';
				status.className = 'podloom-sync-status';

				// Send AJAX request.
				var formData = new FormData();
				formData.append('action', 'podloom_sync_transistor_links');
				formData.append('nonce', podloomData.nonce);
				formData.append('show_id', showId);

				fetch(podloomData.ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						// Update form inputs with synced values.
						var links = data.data.links || {};
						var inputs = podcast.querySelectorAll('.podloom-subscribe-link__input');

						inputs.forEach(function(input) {
							var platform = input.dataset.platform;
							if (links[platform]) {
								input.value = links[platform];
							}
						});

						status.textContent = (strings.synced || 'Synced!') + ' (' + Object.keys(links).length + ' links)';
						status.className = 'podloom-sync-status success';
						updateLinkCount(podcast, Object.keys(links).length);
					} else {
						status.textContent = data.data || (strings.error || 'Error syncing');
						status.className = 'podloom-sync-status error';
					}
				})
				.catch(function() {
					status.textContent = strings.error || 'Error syncing';
					status.className = 'podloom-sync-status error';
				})
				.finally(function() {
					button.disabled = false;
					button.innerHTML = originalText;
				});
			});
		});
	}

	/**
	 * Update the link count badge.
	 *
	 * @param {Element} podcast Podcast container element.
	 * @param {number} count Number of links.
	 */
	function updateLinkCount(podcast, count) {
		var countEl = podcast.querySelector('.podloom-subscribe-podcast__count');
		if (countEl) {
			countEl.textContent = count + (count === 1 ? ' link' : ' links');
		}
	}

	// Add spinning animation for sync button.
	var style = document.createElement('style');
	style.textContent = '@keyframes podloom-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } .dashicons.spinning { animation: podloom-spin 1s linear infinite; }';
	document.head.appendChild(style);
})();
