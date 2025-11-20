/**
 * PodLoom Typography Manager
 * Handles RSS player typography settings and live preview
 */

(function() {
    'use strict';

    const typographyManager = {
        init: function() {
            this.parseExistingValues();
            this.bindEvents();
            this.bindDisplayToggles();
            this.updatePreview();
            this.updateSectionVisibility();
        },

        parseExistingValues: function() {
            const elements = ['title', 'date', 'duration', 'description'];

            // Element-specific defaults matching PHP backend
            const defaults = {
                'title': '24px',
                'date': '14px',
                'duration': '14px',
                'description': '16px'
            };

            elements.forEach(element => {
                // Parse font size
                const fontSizeInput = document.getElementById(element + '_font_size');
                if (fontSizeInput) {
                    let value = fontSizeInput.value || defaults[element];

                    // Migrate old 'em' values to 'px' equivalents (one-time fix for existing installs)
                    // Convert em to px: 1em ≈ 16px baseline
                    if (value && value.includes('em')) {
                        const match = value.match(/^([\d.]+)em$/i);
                        if (match) {
                            const emValue = parseFloat(match[1]);
                            const pxValue = Math.round(emValue * 16);
                            value = pxValue + 'px';
                            // Update the hidden field so it saves correctly
                            fontSizeInput.value = value;
                        }
                    }

                    const match = value.match(/^([\d.]+)([a-z%]*)$/i);
                    if (match) {
                        const numValue = parseFloat(match[1]);
                        const unit = match[2] || 'px';

                        const valueInput = document.getElementById(element + '_font_size_value');
                        const unitSelect = document.getElementById(element + '_font_size_unit');
                        const rangeInput = document.getElementById(element + '_font_size_range');

                        if (valueInput) valueInput.value = numValue;
                        if (unitSelect) unitSelect.value = unit;
                        if (rangeInput && unit === 'px') rangeInput.value = Math.min(72, Math.max(8, numValue));
                    }
                    fontSizeInput.remove();
                }

                // Parse line height
                const lineHeightInput = document.getElementById(element + '_line_height');
                if (lineHeightInput) {
                    const value = parseFloat(lineHeightInput.value) || 1.5;
                    const valueInput = document.getElementById(element + '_line_height_value');
                    const rangeInput = document.getElementById(element + '_line_height_range');

                    if (valueInput) valueInput.value = value;
                    if (rangeInput) rangeInput.value = value;

                    lineHeightInput.remove();
                }
            });
        },

        bindEvents: function() {
            // Live preview on input change
            document.querySelectorAll('.typo-control').forEach(input => {
                input.addEventListener('input', () => this.updatePreview());
                input.addEventListener('change', () => this.updatePreview());
            });

            // Character limit input
            const charLimitInput = document.getElementById('podloom_rss_description_limit');
            if (charLimitInput) {
                charLimitInput.addEventListener('input', () => this.updatePreview());
                charLimitInput.addEventListener('change', () => this.updatePreview());
            }

            // Sync range sliders with number inputs
            document.querySelectorAll('.typo-range').forEach(range => {
                range.addEventListener('input', (e) => {
                    const element = e.target.dataset.element;
                    if (e.target.id.includes('font_size')) {
                        const valueInput = document.getElementById(element + '_font_size_value');
                        if (valueInput) valueInput.value = e.target.value;
                    } else if (e.target.id.includes('line_height')) {
                        const valueInput = document.getElementById(element + '_line_height_value');
                        if (valueInput) valueInput.value = e.target.value;
                    }
                    this.updatePreview();
                });
            });

            // Sync number inputs with range sliders
            document.querySelectorAll('.typo-size-value').forEach(input => {
                input.addEventListener('input', (e) => {
                    const element = e.target.dataset.element;
                    const unitSelect = document.getElementById(element + '_font_size_unit');
                    const unit = unitSelect?.value || 'px';

                    if (unit === 'px') {
                        const rangeInput = document.getElementById(element + '_font_size_range');
                        if (rangeInput) {
                            rangeInput.value = Math.min(72, Math.max(8, parseFloat(e.target.value) || 16));
                        }
                    }
                });
            });

            document.querySelectorAll('.typo-lineheight-value').forEach(input => {
                input.addEventListener('input', (e) => {
                    const element = e.target.dataset.element;
                    const rangeInput = document.getElementById(element + '_line_height_range');
                    if (rangeInput) {
                        rangeInput.value = Math.min(3, Math.max(0.5, parseFloat(e.target.value) || 1.5));
                    }
                });
            });
        },

        bindDisplayToggles: function() {
            // Show/hide typography sections based on display checkboxes
            const elements = ['title', 'date', 'duration', 'description'];

            elements.forEach(element => {
                const checkbox = document.getElementById(`podloom_rss_display_${element}`);
                if (checkbox) {
                    checkbox.addEventListener('change', () => {
                        this.updateSectionVisibility();
                        this.updatePreview();
                    });
                }
            });

            // Handle artwork checkbox (no typography section, but affects preview)
            const artworkCheckbox = document.getElementById('podloom_rss_display_artwork');
            if (artworkCheckbox) {
                artworkCheckbox.addEventListener('change', () => {
                    this.updatePreview();
                });
            }

            // Handle minimal styling toggle
            const minimalStylingCheckbox = document.getElementById('podloom_rss_minimal_styling');
            if (minimalStylingCheckbox) {
                minimalStylingCheckbox.addEventListener('change', () => {
                    this.toggleTypographySettings();
                });
                // Set initial state
                this.toggleTypographySettings();
            }
        },

        toggleTypographySettings: function() {
            const minimalStylingCheckbox = document.getElementById('podloom_rss_minimal_styling');
            const typographyContainer = document.querySelector('.typography-settings-container');
            const colorPaletteSection = document.getElementById('color-palette-section');
            const typographyWrapper = document.getElementById('typography-section-wrapper');

            if (minimalStylingCheckbox && typographyContainer) {
                if (minimalStylingCheckbox.checked) {
                    // Hide typography settings
                    typographyContainer.style.display = 'none';
                    // Hide color palette section
                    if (colorPaletteSection) {
                        colorPaletteSection.style.display = 'none';
                    }
                    // Show notice
                    let notice = document.getElementById('minimal-styling-notice');
                    if (!notice) {
                        notice = document.createElement('div');
                        notice.id = 'minimal-styling-notice';
                        notice.className = 'notice notice-info inline';
                        notice.style.marginTop = '0';
                        notice.style.marginBottom = '20px';
                        notice.innerHTML = '<p><strong>Minimal Styling Mode is enabled.</strong> Typography settings are disabled. Add your own CSS using the following classes:</p>' +
                            '<p><strong>Episode Elements:</strong> <code>.rss-episode-player</code>, <code>.rss-episode-title</code>, <code>.rss-episode-date</code>, <code>.rss-episode-duration</code>, <code>.rss-episode-description</code>, <code>.rss-episode-artwork</code>, <code>.rss-episode-audio</code></p>' +
                            '<p><strong>Podcasting 2.0 Elements:</strong> <code>.podcast20-tabs</code>, <code>.podcast20-tab-button</code>, <code>.podcast20-tab-panel</code>, <code>.podcast20-funding-button</code>, <code>.podcast20-transcripts</code>, <code>.transcript-format-button</code>, <code>.transcript-viewer</code>, <code>.podcast20-people</code>, <code>.podcast20-person</code>, <code>.podcast20-person-name</code>, <code>.podcast20-chapters-list</code>, <code>.chapter-item</code>, <code>.chapter-title</code>, <code>.chapter-timestamp</code></p>';
                        // Insert at the beginning of the typography wrapper (above the accordion)
                        if (typographyWrapper) {
                            typographyWrapper.insertBefore(notice, typographyWrapper.firstChild);
                        } else {
                            // Fallback if wrapper doesn't exist
                            typographyContainer.parentNode.insertBefore(notice, typographyContainer);
                        }
                    } else {
                        notice.style.display = 'block';
                    }
                } else {
                    // Show typography settings
                    typographyContainer.style.display = '';
                    // Show color palette section
                    if (colorPaletteSection) {
                        colorPaletteSection.style.display = '';
                    }
                    // Hide notice
                    const notice = document.getElementById('minimal-styling-notice');
                    if (notice) {
                        notice.style.display = 'none';
                    }
                }
            }
        },

        updateSectionVisibility: function() {
            const elements = ['title', 'date', 'duration', 'description'];

            elements.forEach(element => {
                const checkbox = document.getElementById(`podloom_rss_display_${element}`);
                const section = document.getElementById(`${element}_typography_section`);

                if (checkbox && section) {
                    section.style.display = checkbox.checked ? '' : 'none';
                }
            });
        },

        updatePreview: function() {
            const elements = ['title', 'date', 'duration', 'description'];

            // Update background color
            const bgColor = document.getElementById('rss_background_color')?.value || '#f9f9f9';
            const previewContainer = document.getElementById('rss-episode-preview');
            if (previewContainer) {
                previewContainer.style.background = bgColor;
            }

            // Show/hide artwork based on checkbox
            const artworkCheckbox = document.getElementById('podloom_rss_display_artwork');
            const previewArtwork = document.getElementById('preview-artwork');
            if (artworkCheckbox && previewArtwork) {
                previewArtwork.style.display = artworkCheckbox.checked ? 'block' : 'none';
            }

            // Handle date and duration in meta container
            const dateCheckbox = document.getElementById('podloom_rss_display_date');
            const durationCheckbox = document.getElementById('podloom_rss_display_duration');
            const previewMeta = document.getElementById('preview-meta');

            if (dateCheckbox && durationCheckbox && previewMeta) {
                // Hide entire meta container if both are unchecked
                if (!dateCheckbox.checked && !durationCheckbox.checked) {
                    previewMeta.style.display = 'none';
                } else {
                    previewMeta.style.display = 'flex';
                }
            }

            // Handle audio player margin based on description visibility
            const descriptionCheckbox = document.getElementById('podloom_rss_display_description');
            const audioPlayer = document.querySelector('.rss-episode-audio');
            if (descriptionCheckbox && audioPlayer) {
                if (descriptionCheckbox.checked) {
                    audioPlayer.style.marginBottom = '15px';
                } else {
                    audioPlayer.style.marginBottom = '0';
                }
            }

            elements.forEach(element => {
                const previewEl = document.getElementById('preview-' + element);
                if (!previewEl) return;

                // Check if element should be displayed
                const checkbox = document.getElementById(`podloom_rss_display_${element}`);
                if (checkbox) {
                    previewEl.style.display = checkbox.checked ? '' : 'none';
                }

                // Handle description character limit
                if (element === 'description') {
                    const charLimitInput = document.getElementById('podloom_rss_description_limit');
                    const charLimit = charLimitInput ? parseInt(charLimitInput.value) : 0;
                    const originalText = 'This is a sample episode description. It gives listeners an overview of what the episode is about and what they can expect to learn.';

                    if (charLimit > 0 && originalText.length > charLimit) {
                        let truncated = originalText.substring(0, charLimit);
                        // Try to break at last space
                        const lastSpace = truncated.lastIndexOf(' ');
                        if (lastSpace > charLimit * 0.8) {
                            truncated = truncated.substring(0, lastSpace);
                        }
                        previewEl.innerHTML = '<p>' + truncated + '…</p>';
                    } else {
                        previewEl.innerHTML = '<p>' + originalText + '</p>';
                    }
                }

                // Apply typography styles
                const fontFamily = document.getElementById(element + '_font_family')?.value || 'inherit';
                const fontSizeValue = document.getElementById(element + '_font_size_value')?.value || '16';
                const fontSizeUnit = document.getElementById(element + '_font_size_unit')?.value || 'px';
                const fontSize = fontSizeValue + fontSizeUnit;
                const lineHeight = document.getElementById(element + '_line_height_value')?.value || '1.5';
                const color = document.getElementById(element + '_color')?.value || '#000000';
                const fontWeight = document.getElementById(element + '_font_weight')?.value || 'normal';

                previewEl.style.fontFamily = fontFamily;
                previewEl.style.fontSize = fontSize;
                previewEl.style.lineHeight = lineHeight;
                previewEl.style.color = color;
                previewEl.style.fontWeight = fontWeight;
            });
        }
    };

    // Export to window for initialization
    window.podloomTypographyManager = typographyManager;

})();
