/**
 * PodLoom RSS Manager
 * Handles RSS feed management and settings
 */

(function ($) {
    'use strict';

    const rssManager = {
        init: function () {
            this.bindEvents();
            // Feeds are now rendered server-side, no need to load on init
        },

        bindEvents: function () {
            // Toggle RSS container
            const rssEnabledCheckbox = document.getElementById('podloom_rss_enabled');
            if (rssEnabledCheckbox) {
                rssEnabledCheckbox.addEventListener('change', (e) => {
                    const container = document.getElementById('rss-feeds-container');
                    if (e.target.checked) {
                        container.style.display = 'block';
                        // Only load if container is empty (shouldn't be, but just in case)
                        const feedsList = document.getElementById('rss-feeds-list');
                        if (feedsList && feedsList.innerHTML.trim() === '') {
                            this.loadFeeds();
                        }
                    } else {
                        container.style.display = 'none';
                    }
                });
            }

            // Toggle color palette section when minimal styling changes
            const minimalStylingCheckbox = document.getElementById('podloom_rss_minimal_styling');
            if (minimalStylingCheckbox) {
                minimalStylingCheckbox.addEventListener('change', (e) => {
                    const colorPaletteSection = document.getElementById('color-palette-section');
                    if (colorPaletteSection) {
                        colorPaletteSection.style.display = e.target.checked ? 'none' : '';
                    }
                });
            }

            // Add new feed button
            const addButton = document.getElementById('add-new-rss-feed');
            if (addButton) {
                addButton.addEventListener('click', () => this.showAddFeedModal());
            }

            // Save RSS settings
            const saveButton = document.getElementById('save-rss-settings');
            if (saveButton) {
                saveButton.addEventListener('click', () => this.saveSettings());
            }

            // Event delegation for server-side rendered action buttons
            const feedsList = document.getElementById('rss-feeds-list');
            if (feedsList) {
                feedsList.addEventListener('click', (e) => {
                    const target = e.target.closest('button');
                    if (!target) return;

                    const feedId = target.dataset.feedId;
                    if (!feedId) return;

                    if (target.classList.contains('edit-feed')) {
                        const feedRow = target.closest('tr');
                        const feedName = feedRow.querySelector('td:first-child strong').textContent;
                        this.editFeed(feedId, feedName);
                    } else if (target.classList.contains('refresh-feed')) {
                        this.refreshFeed(feedId, target);
                    } else if (target.classList.contains('delete-feed')) {
                        this.deleteFeed(feedId);
                    } else if (target.classList.contains('view-feed-xml')) {
                        this.viewFeedXML(feedId);
                    }
                });
            }
            // Palette Switcher Logic
            const paletteContainer = document.querySelector('.podloom-palettes-grid');
            if (paletteContainer) {
                // Detect and highlight current palette on page load
                this.detectCurrentPalette();

                paletteContainer.addEventListener('click', (e) => {
                    const button = e.target.closest('.podloom-palette-btn');
                    if (!button) return;

                    const bg = button.dataset.bg;
                    const title = button.dataset.title;
                    const text = button.dataset.text;
                    const accent = button.dataset.accent;

                    // Update Background Color (Main Input)
                    const bgInput = document.getElementById('podloom_rss_background_color');
                    if (bgInput) {
                        bgInput.value = bg;
                        bgInput.dispatchEvent(new Event('input', { bubbles: true }));
                        bgInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    // Update Background Color (Typography Preview Input)
                    const previewBgInput = document.getElementById('rss_background_color');
                    if (previewBgInput) {
                        previewBgInput.value = bg;
                        previewBgInput.dispatchEvent(new Event('input', { bubbles: true }));
                        previewBgInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    // Update Typography Colors
                    const updateColor = (elementId, colorValue) => {
                        const input = document.getElementById(elementId);
                        if (input) {
                            input.value = colorValue;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };

                    updateColor('title_color', title);
                    updateColor('date_color', text);
                    updateColor('duration_color', text);
                    updateColor('description_color', text);

                    // Update Accent Color (Hidden Input)
                    const accentInput = document.getElementById('podloom_rss_accent_color');
                    if (accentInput) {
                        accentInput.value = accent;
                    }

                    // Update selected state
                    this.selectPalette(button);
                });
            }
        },

        /**
         * Detect and highlight the current palette based on saved background color
         */
        detectCurrentPalette: function() {
            const bgInput = document.getElementById('podloom_rss_background_color');
            if (!bgInput) return;

            const currentBg = bgInput.value.toLowerCase();
            const palettes = document.querySelectorAll('.podloom-palette-btn');

            palettes.forEach(btn => {
                if (btn.dataset.bg.toLowerCase() === currentBg) {
                    this.selectPalette(btn);
                }
            });
        },

        /**
         * Mark a palette button as selected
         */
        selectPalette: function(button) {
            // Remove selected state from all palettes
            document.querySelectorAll('.podloom-palette-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            // Add selected state to clicked palette
            button.classList.add('selected');
        },

        editFeed: function (feedId, feedName) {
            this.showModal(podloomData.strings.editFeedName, `
                <label for="rss-feed-name-edit">${podloomData.strings.feedName}</label>
                <input type="text" id="rss-feed-name-edit" value="${this.escapeHtml(feedName)}" />

                <div id="rss-modal-error" style="display: none;"></div>
            `, (modal) => {
                const name = document.getElementById('rss-feed-name-edit').value.trim();
                const errorDiv = document.getElementById('rss-modal-error');

                if (!name) {
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'rss-notice error';
                    errorDiv.textContent = podloomData.strings.enterFeedName;
                    return;
                }

                this.updateFeedName(feedId, name, modal);
            });
        },

        showAddFeedModal: function () {
            this.showModal(podloomData.strings.addNewFeed, `
                <label for="rss-feed-name">${podloomData.strings.feedName}</label>
                <input type="text" id="rss-feed-name" placeholder="${podloomData.strings.feedNamePlaceholder}" />

                <label for="rss-feed-url">${podloomData.strings.feedUrl}</label>
                <input type="url" id="rss-feed-url" placeholder="${podloomData.strings.feedUrlPlaceholder}" />

                <div id="rss-modal-progress" style="display: none; margin-top: 15px; color: #2271b1;">
                    <span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
                    ${podloomData.strings.addingFeedProgress}
                </div>

                <div id="rss-modal-error" style="display: none;"></div>
            `, (modal) => {
                const name = document.getElementById('rss-feed-name').value.trim();
                const url = document.getElementById('rss-feed-url').value.trim();
                const errorDiv = document.getElementById('rss-modal-error');

                if (!name || !url) {
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'rss-notice error';
                    errorDiv.textContent = podloomData.strings.fillAllFields;
                    return;
                }

                this.addFeed(name, url, modal, errorDiv);
            });
        },

        addFeed: function (name, url, modal, errorDiv) {
            const saveButton = modal.querySelector('.button-primary');
            const originalText = saveButton.textContent;
            const progressDiv = document.getElementById('rss-modal-progress');

            saveButton.textContent = podloomData.strings.adding;
            saveButton.disabled = true;

            if (progressDiv) {
                progressDiv.style.display = 'block';
                errorDiv.style.display = 'none';
            }

            $.post(podloomData.ajaxUrl, {
                action: 'podloom_add_rss_feed',
                nonce: podloomData.nonce,
                name: name,
                url: url
            }, (response) => {
                if (response.success) {
                    this.closeModal();
                    window.location.reload();
                } else {
                    if (progressDiv) {
                        progressDiv.style.display = 'none';
                    }
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'rss-notice error';
                    errorDiv.textContent = response.data.message || podloomData.strings.errorAddingFeed;
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                }
            });
        },

        updateFeedName: function (feedId, name, modal) {
            const saveButton = modal.querySelector('.button-primary');
            saveButton.textContent = podloomData.strings.saving;
            saveButton.disabled = true;

            $.post(podloomData.ajaxUrl, {
                action: 'podloom_update_rss_feed_name',
                nonce: podloomData.nonce,
                feed_id: feedId,
                name: name
            }, (response) => {
                if (response.success) {
                    this.closeModal();
                    window.location.reload();
                } else {
                    const errorDiv = modal.querySelector('#rss-modal-error');
                    errorDiv.style.display = 'block';
                    errorDiv.className = 'rss-notice error';
                    errorDiv.textContent = response.data.message || podloomData.strings.errorUpdatingFeed;
                    saveButton.textContent = podloomData.strings.save;
                    saveButton.disabled = false;
                }
            });
        },

        refreshFeed: function (feedId, button) {
            const originalText = button.innerHTML;
            button.innerHTML = podloomData.strings.refreshing;
            button.disabled = true;

            $.post(podloomData.ajaxUrl, {
                action: 'podloom_refresh_rss_feed',
                nonce: podloomData.nonce,
                feed_id: feedId
            }, (response) => {
                // Show appropriate message based on response
                let message = '';
                let type = 'success';

                if (response.success) {
                    if (response.data.not_modified) {
                        // 304 - Feed hasn't changed
                        message = podloomData.strings.feedUpToDate || 'Feed is up to date';
                    } else {
                        // 200 - Feed updated with new content
                        const count = response.data.episode_count || 0;
                        message = (podloomData.strings.feedRefreshed || 'Feed refreshed') + ' — ' + count + ' ' + (podloomData.strings.episodes || 'episodes');
                    }
                } else {
                    type = 'error';
                    if (response.data.cache_kept) {
                        // Error but cache preserved
                        message = (response.data.message || podloomData.strings.errorRefreshingFeed || 'Error refreshing feed') + ' — ' + (podloomData.strings.usingCachedData || 'using cached data');
                    } else {
                        message = response.data.message || podloomData.strings.errorRefreshingFeed || 'Error refreshing feed';
                    }
                }

                // Show notice and reload after a moment so user sees the message
                this.showNotice(message, type);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            });
        },

        deleteFeed: function (feedId) {
            if (!confirm(podloomData.strings.deleteFeedConfirm)) {
                return;
            }

            $.post(podloomData.ajaxUrl, {
                action: 'podloom_delete_rss_feed',
                nonce: podloomData.nonce,
                feed_id: feedId
            }, () => {
                window.location.reload();
            });
        },

        viewFeedXML: function (feedId) {
            $.post(podloomData.ajaxUrl, {
                action: 'podloom_get_raw_rss_feed',
                nonce: podloomData.nonce,
                feed_id: feedId
            }, (response) => {
                if (response.success) {
                    this.showXMLModal(response.data.xml);
                } else {
                    alert(podloomData.strings.errorLoadingFeed + ': ' + (response.data.message || podloomData.strings.unknownError));
                }
            });
        },

        showXMLModal: function (xml) {
            // Remove any existing modals first
            const existingBackdrop = document.getElementById('xml-modal-backdrop');
            const existingModal = document.getElementById('xml-viewer-modal');
            if (existingBackdrop) document.body.removeChild(existingBackdrop);
            if (existingModal) document.body.removeChild(existingModal);

            const backdrop = document.createElement('div');
            backdrop.id = 'xml-modal-backdrop';
            backdrop.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 100000; display: block;';

            const modal = document.createElement('div');
            modal.id = 'xml-viewer-modal';
            modal.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 4px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); z-index: 100001; max-width: 900px; width: 90%; max-height: 80vh; display: flex; flex-direction: column;';

            modal.innerHTML = `
                <div style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0;">${podloomData.strings.rssFeedXml}</h2>
                    <button type="button" id="xml-modal-close" style="cursor: pointer; font-size: 24px; color: #666; background: none; border: none; padding: 0; line-height: 1;">&times;</button>
                </div>
                <div style="padding: 20px; overflow-y: auto; flex-grow: 1;">
                    <div style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; overflow-x: auto; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.6; max-height: 60vh; white-space: pre-wrap; word-break: break-all;">${this.escapeHtml(xml)}</div>
                </div>
            `;

            document.body.appendChild(backdrop);
            document.body.appendChild(modal);

            const closeModal = () => {
                if (document.body.contains(backdrop)) document.body.removeChild(backdrop);
                if (document.body.contains(modal)) document.body.removeChild(modal);
            };

            modal.querySelector('#xml-modal-close').addEventListener('click', closeModal);
            backdrop.addEventListener('click', closeModal);
        },

        saveSettings: function () {
            const button = document.getElementById('save-rss-settings');
            button.textContent = podloomData.strings.saving;
            button.disabled = true;

            // Dynamically collect all RSS settings from the DOM
            // This ensures new options are automatically included without manual updates
            const data = {};
            const container = document.getElementById('rss-feeds-settings');

            if (container) {
                // Collect all inputs with name starting with 'podloom_rss_'
                container.querySelectorAll('input[name^="podloom_rss_"], select[name^="podloom_rss_"]').forEach(input => {
                    const name = input.name;
                    if (input.type === 'checkbox') {
                        data[name] = input.checked ? '1' : '0';
                    } else {
                        data[name] = input.value;
                    }
                });
            }

            // Add typography settings (these have different ID patterns)
            const elements = ['title', 'date', 'duration', 'description'];

            elements.forEach(element => {
                // Font family
                const fontFamily = document.getElementById(element + '_font_family');
                if (fontFamily) {
                    data['podloom_rss_' + element + '_font_family'] = fontFamily.value;
                }

                // Font size (combine value and unit)
                const fontSizeValue = document.getElementById(element + '_font_size_value');
                const fontSizeUnit = document.getElementById(element + '_font_size_unit');
                if (fontSizeValue && fontSizeUnit) {
                    data['podloom_rss_' + element + '_font_size'] = fontSizeValue.value + fontSizeUnit.value;
                }

                // Line height
                const lineHeight = document.getElementById(element + '_line_height_value');
                if (lineHeight) {
                    data['podloom_rss_' + element + '_line_height'] = lineHeight.value;
                }

                // Color
                const color = document.getElementById(element + '_color');
                if (color) {
                    data['podloom_rss_' + element + '_color'] = color.value;
                }

                // Font weight
                const fontWeight = document.getElementById(element + '_font_weight');
                if (fontWeight) {
                    data['podloom_rss_' + element + '_font_weight'] = fontWeight.value;
                }
            });

            // Border settings (add 'px' suffix to numeric values)
            const borderColor = document.getElementById('podloom_rss_border_color');
            if (borderColor) data['podloom_rss_border_color'] = borderColor.value;

            const borderWidth = document.getElementById('podloom_rss_border_width_value');
            if (borderWidth) data['podloom_rss_border_width'] = borderWidth.value + 'px';

            const borderStyle = document.getElementById('podloom_rss_border_style');
            if (borderStyle) data['podloom_rss_border_style'] = borderStyle.value;

            const borderRadius = document.getElementById('podloom_rss_border_radius_value');
            if (borderRadius) data['podloom_rss_border_radius'] = borderRadius.value + 'px';

            // Funding button settings (add 'px' suffix to numeric values)
            const fundingFontFamily = document.getElementById('podloom_rss_funding_font_family');
            if (fundingFontFamily) data['podloom_rss_funding_font_family'] = fundingFontFamily.value;

            const fundingFontSize = document.getElementById('podloom_rss_funding_font_size_value');
            if (fundingFontSize) data['podloom_rss_funding_font_size'] = fundingFontSize.value + 'px';

            const fundingBgColor = document.getElementById('podloom_rss_funding_background_color');
            if (fundingBgColor) data['podloom_rss_funding_background_color'] = fundingBgColor.value;

            const fundingTextColor = document.getElementById('podloom_rss_funding_text_color');
            if (fundingTextColor) data['podloom_rss_funding_text_color'] = fundingTextColor.value;

            const fundingBorderRadius = document.getElementById('podloom_rss_funding_border_radius_value');
            if (fundingBorderRadius) data['podloom_rss_funding_border_radius'] = fundingBorderRadius.value + 'px';

            // Save all settings in a single request
            $.post(podloomData.ajaxUrl, {
                action: 'podloom_save_all_rss_settings',
                nonce: podloomData.nonce,
                settings: JSON.stringify(data)
            }).done((response) => {
                button.textContent = podloomData.strings.saveRssSettings;
                button.disabled = false;
                if (response.success) {
                    this.showNotice(podloomData.strings.settingsSavedSuccess, 'success');
                } else {
                    this.showNotice(response.data.message || podloomData.strings.errorSavingSettings, 'error');
                }
            }).fail(() => {
                button.textContent = podloomData.strings.saveRssSettings;
                button.disabled = false;
                this.showNotice(podloomData.strings.errorSavingSettings, 'error');
            });
        },

        showModal: function (title, bodyHtml, onSave) {
            const backdrop = document.createElement('div');
            backdrop.id = 'rss-modal-backdrop';
            backdrop.style.display = 'block';

            const modal = document.createElement('div');
            modal.id = 'rss-modal';
            modal.style.display = 'flex';

            modal.innerHTML = `
                <div id="rss-modal-header">
                    <h2>${title}</h2>
                    <button type="button" id="rss-modal-close">&times;</button>
                </div>
                <div id="rss-modal-body">
                    ${bodyHtml}
                </div>
                <div id="rss-modal-footer">
                    <button type="button" class="button" id="rss-modal-cancel">${podloomData.strings.cancel}</button>
                    <button type="button" class="button button-primary" id="rss-modal-save">${podloomData.strings.save}</button>
                </div>
            `;

            document.body.appendChild(backdrop);
            document.body.appendChild(modal);

            const closeModal = () => {
                document.body.removeChild(backdrop);
                document.body.removeChild(modal);
            };

            modal.querySelector('#rss-modal-close').addEventListener('click', closeModal);
            modal.querySelector('#rss-modal-cancel').addEventListener('click', closeModal);
            modal.querySelector('#rss-modal-save').addEventListener('click', () => onSave(modal));
            backdrop.addEventListener('click', closeModal);
        },

        closeModal: function () {
            const backdrop = document.getElementById('rss-modal-backdrop');
            const modal = document.getElementById('rss-modal');
            if (backdrop) document.body.removeChild(backdrop);
            if (modal) document.body.removeChild(modal);
        },

        showNotice: function (message, type) {
            const container = document.getElementById('rss-feeds-settings');
            const notice = document.createElement('div');
            notice.className = `rss-notice ${type}`;
            notice.textContent = message;
            container.insertBefore(notice, container.firstChild);

            setTimeout(() => {
                notice.remove();
            }, 5000);
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Export to window for initialization
    window.podloomRssManager = rssManager;

})(jQuery);
