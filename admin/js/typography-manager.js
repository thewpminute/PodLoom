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

            // Border settings range sliders
            this.bindRangeSlider('podloom_rss_border_width');
            this.bindRangeSlider('podloom_rss_border_radius');

            // Funding button range sliders
            this.bindRangeSlider('podloom_rss_funding_font_size');
            this.bindRangeSlider('podloom_rss_funding_border_radius');

            // Bind border settings for preview
            ['podloom_rss_border_color', 'podloom_rss_border_width_value', 'podloom_rss_border_style', 'podloom_rss_border_radius_value'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', () => this.updatePreview());
                    input.addEventListener('change', () => this.updatePreview());
                }
            });

            // Bind funding button settings for preview
            ['podloom_rss_funding_font_family', 'podloom_rss_funding_font_size_value', 'podloom_rss_funding_background_color',
             'podloom_rss_funding_text_color', 'podloom_rss_funding_border_radius_value'].forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', () => this.updatePreview());
                    input.addEventListener('change', () => this.updatePreview());
                }
            });
        },

        /**
         * Bind a range slider to sync with its corresponding number input
         */
        bindRangeSlider: function(baseName) {
            const rangeInput = document.getElementById(baseName + '_range');
            const valueInput = document.getElementById(baseName + '_value') || document.getElementById(baseName);

            if (rangeInput && valueInput) {
                // Sync range to value
                rangeInput.addEventListener('input', () => {
                    valueInput.value = rangeInput.value;
                    this.updatePreview();
                });

                // Sync value to range
                valueInput.addEventListener('input', () => {
                    const val = parseFloat(valueInput.value) || 0;
                    const min = parseFloat(rangeInput.min) || 0;
                    const max = parseFloat(rangeInput.max) || 100;
                    rangeInput.value = Math.min(max, Math.max(min, val));
                });
            }
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
            const minimalStylingNotice = document.getElementById('minimal-styling-notice');

            if (minimalStylingCheckbox && typographyContainer) {
                if (minimalStylingCheckbox.checked) {
                    // Hide typography settings and color palette
                    typographyContainer.style.display = 'none';
                    if (colorPaletteSection) {
                        colorPaletteSection.style.display = 'none';
                    }
                    // Show CSS classes notice
                    if (minimalStylingNotice) {
                        minimalStylingNotice.style.display = 'block';
                    }
                } else {
                    // Show typography settings and color palette
                    typographyContainer.style.display = '';
                    if (colorPaletteSection) {
                        colorPaletteSection.style.display = '';
                    }
                    // Hide CSS classes notice
                    if (minimalStylingNotice) {
                        minimalStylingNotice.style.display = 'none';
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

            // Handle player container margin based on description visibility
            const descriptionCheckbox = document.getElementById('podloom_rss_display_description');
            const playerContainer = document.querySelector('.podloom-player-container');
            if (descriptionCheckbox && playerContainer) {
                if (descriptionCheckbox.checked) {
                    playerContainer.style.marginBottom = '15px';
                } else {
                    playerContainer.style.marginBottom = '0';
                }
            }

            // Update custom player colors based on background
            if (playerContainer && bgColor) {
                const colors = this.calculatePlayerColors(bgColor);
                playerContainer.style.setProperty('--podloom-player-btn-bg', colors.btnBg);
                playerContainer.style.setProperty('--podloom-player-btn-icon', colors.btnIcon);
                playerContainer.style.setProperty('--podloom-player-btn', colors.btnBg);
                playerContainer.style.setProperty('--podloom-player-timeline', colors.timeline);
                playerContainer.style.setProperty('--podloom-player-progress', colors.progress);
                playerContainer.style.setProperty('--podloom-player-control', colors.control);
                playerContainer.style.setProperty('--podloom-player-time', colors.time);
                playerContainer.style.setProperty('--podloom-player-speed-bg', colors.speedBg);
                playerContainer.style.setProperty('--podloom-player-speed-border', colors.speedBorder);
                playerContainer.style.setProperty('--podloom-player-text', colors.text);
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

            // Update border styles
            if (previewContainer) {
                const borderColor = document.getElementById('podloom_rss_border_color')?.value || '#dddddd';
                const borderWidth = (document.getElementById('podloom_rss_border_width_value')?.value || '1') + 'px';
                const borderStyle = document.getElementById('podloom_rss_border_style')?.value || 'solid';
                const borderRadius = (document.getElementById('podloom_rss_border_radius_value')?.value || '8') + 'px';

                previewContainer.style.border = `${borderWidth} ${borderStyle} ${borderColor}`;
                previewContainer.style.borderRadius = borderRadius;
            }

            // Update funding button styles in preview
            const fundingBtn = document.getElementById('preview-funding-button');
            if (fundingBtn) {
                const fontFamily = document.getElementById('podloom_rss_funding_font_family')?.value || 'inherit';
                const fontSize = (document.getElementById('podloom_rss_funding_font_size_value')?.value || '13') + 'px';
                const bgColor = document.getElementById('podloom_rss_funding_background_color')?.value || '#2271b1';
                const textColor = document.getElementById('podloom_rss_funding_text_color')?.value || '#ffffff';
                const borderRadius = (document.getElementById('podloom_rss_funding_border_radius_value')?.value || '4') + 'px';

                fundingBtn.style.fontFamily = fontFamily;
                fundingBtn.style.fontSize = fontSize;
                fundingBtn.style.backgroundColor = bgColor;
                fundingBtn.style.color = textColor;
                fundingBtn.style.borderRadius = borderRadius;
            }
        },

        /**
         * Calculate player colors based on background color
         * Mirrors PHP podloom_calculate_theme_colors() for live preview
         */
        calculatePlayerColors: function(bgColor) {
            // Convert hex to RGB
            const hexToRgb = (hex) => {
                hex = hex.replace('#', '');
                return {
                    r: parseInt(hex.substring(0, 2), 16),
                    g: parseInt(hex.substring(2, 4), 16),
                    b: parseInt(hex.substring(4, 6), 16)
                };
            };

            // Calculate luminance
            const getLuminance = (rgb) => {
                const r = rgb.r / 255;
                const g = rgb.g / 255;
                const b = rgb.b / 255;
                return 0.2126 * r + 0.7152 * g + 0.0722 * b;
            };

            // Lighten a color by percentage
            const lightenColor = (hex, percent) => {
                const rgb = hexToRgb(hex);
                const r = Math.min(255, Math.round(rgb.r + (255 - rgb.r) * (percent / 100)));
                const g = Math.min(255, Math.round(rgb.g + (255 - rgb.g) * (percent / 100)));
                const b = Math.min(255, Math.round(rgb.b + (255 - rgb.b) * (percent / 100)));
                return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
            };

            // Darken a color by percentage
            const darkenColor = (hex, percent) => {
                const rgb = hexToRgb(hex);
                const r = Math.max(0, Math.round(rgb.r - (rgb.r * (percent / 100))));
                const g = Math.max(0, Math.round(rgb.g - (rgb.g * (percent / 100))));
                const b = Math.max(0, Math.round(rgb.b - (rgb.b * (percent / 100))));
                return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('');
            };

            const rgb = hexToRgb(bgColor);
            const luminance = getLuminance(rgb);
            const isDark = luminance < 0.5;

            if (isDark) {
                // Dark theme colors
                return {
                    btnBg: lightenColor(bgColor, 60),
                    btnIcon: lightenColor(bgColor, 95),
                    timeline: lightenColor(bgColor, 25),
                    progress: lightenColor(bgColor, 60),
                    control: lightenColor(bgColor, 50),
                    time: lightenColor(bgColor, 45),
                    speedBg: lightenColor(bgColor, 15),
                    speedBorder: lightenColor(bgColor, 30),
                    text: lightenColor(bgColor, 70)
                };
            } else {
                // Light theme colors
                return {
                    btnBg: darkenColor(bgColor, 75),
                    btnIcon: '#ffffff',
                    timeline: darkenColor(bgColor, 10),
                    progress: darkenColor(bgColor, 75),
                    control: darkenColor(bgColor, 50),
                    time: darkenColor(bgColor, 40),
                    speedBg: '#ffffff',
                    speedBorder: darkenColor(bgColor, 20),
                    text: darkenColor(bgColor, 70)
                };
            }
        }
    };

    // Export to window for initialization
    window.podloomTypographyManager = typographyManager;

})();
