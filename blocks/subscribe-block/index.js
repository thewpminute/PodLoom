/**
 * PodLoom Subscribe Buttons Block
 *
 * Displays subscribe buttons for a selected podcast.
 *
 * @package PodLoom
 * @since 2.10.0
 */

( function() {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, RadioControl, ToggleControl, ColorPicker, Placeholder, Spinner, Button } = wp.components;
	const { useState, useEffect } = wp.element;
	const { __ } = wp.i18n;

	/**
	 * Register the block
	 */
	registerBlockType('podloom/subscribe-buttons', {
	title: __('PodLoom Subscribe Buttons', 'podloom-podcast-player'),
	icon: 'share',
	category: 'media',
	attributes: {
		source: {
			type: 'string',
			default: ''
		},
		iconSize: {
			type: 'string',
			default: 'medium'
		},
		colorMode: {
			type: 'string',
			default: 'brand'
		},
		customColor: {
			type: 'string',
			default: '#000000'
		},
		layout: {
			type: 'string',
			default: 'horizontal'
		},
		showLabels: {
			type: 'boolean',
			default: false
		}
	},

	/**
	 * Block Edit Component
	 */
	edit: function EditComponent({ attributes, setAttributes }) {
		const { source, iconSize, colorMode, customColor, layout, showLabels } = attributes;

		const [podcasts, setPodcasts] = useState([]);
		const [loading, setLoading] = useState(true);
		const [previewData, setPreviewData] = useState(null);
		const [previewLoading, setPreviewLoading] = useState(false);

		const blockProps = useBlockProps();

		// Load podcasts on mount
		useEffect(() => {
			loadPodcasts();
		}, []);

		// Load preview data when source or color settings change
		useEffect(() => {
			if (source) {
				loadPreviewData(source);
			} else {
				setPreviewData(null);
			}
		}, [source, colorMode, customColor]);

		/**
		 * Load available podcasts
		 */
		const loadPodcasts = async () => {
			setLoading(true);

			try {
				const formData = new FormData();
				formData.append('action', 'podloom_get_subscribe_podcasts');
				formData.append('nonce', podloomData.nonce);

				const response = await fetch(podloomData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				const result = await response.json();

				if (result.success) {
					setPodcasts(result.data.podcasts || []);
				}
			} catch (err) {
				console.error('Error loading podcasts:', err);
			} finally {
				setLoading(false);
			}
		};

		/**
		 * Load preview data for selected source
		 */
		const loadPreviewData = async (sourceId) => {
			setPreviewLoading(true);

			try {
				const formData = new FormData();
				formData.append('action', 'podloom_get_subscribe_preview');
				formData.append('nonce', podloomData.nonce);
				formData.append('source_id', sourceId);
				formData.append('color_mode', colorMode);
				if (colorMode === 'custom') {
					formData.append('custom_color', customColor);
				}

				const response = await fetch(podloomData.ajaxUrl, {
					method: 'POST',
					body: formData
				});
				const result = await response.json();

				if (result.success) {
					setPreviewData(result.data.links || []);
				} else {
					setPreviewData([]);
				}
			} catch (err) {
				console.error('Error loading preview:', err);
				setPreviewData([]);
			} finally {
				setPreviewLoading(false);
			}
		};

		/**
		 * Build podcast options for select
		 */
		const buildPodcastOptions = () => {
			const options = [
				{ label: __('-- Select a podcast --', 'podloom-podcast-player'), value: '' }
			];

			const transistorPodcasts = podcasts.filter(p => p.type === 'transistor');
			const rssPodcasts = podcasts.filter(p => p.type === 'rss');

			if (transistorPodcasts.length > 0) {
				options.push({
					label: __('━━ Transistor.fm ━━', 'podloom-podcast-player'),
					value: '__transistor_header__',
					disabled: true
				});
				transistorPodcasts.forEach(podcast => {
					options.push({
						label: '  ' + podcast.name,
						value: podcast.source_id
					});
				});
			}

			if (rssPodcasts.length > 0) {
				options.push({
					label: __('━━ RSS Feeds ━━', 'podloom-podcast-player'),
					value: '__rss_header__',
					disabled: true
				});
				rssPodcasts.forEach(podcast => {
					options.push({
						label: '  ' + podcast.name,
						value: podcast.source_id
					});
				});
			}

			return options;
		};


		/**
		 * Get icon size in pixels
		 */
		const getIconSizePx = () => {
			switch (iconSize) {
				case 'small': return 24;
				case 'large': return 48;
				default: return 32;
			}
		};

		/**
		 * Render preview buttons
		 */
		const renderPreview = () => {
			if (!previewData || previewData.length === 0) {
				return wp.element.createElement(
					'div',
					{ style: { padding: '20px', textAlign: 'center', color: '#666' } },
					__('No subscribe links configured for this podcast.', 'podloom-podcast-player')
				);
			}

			const iconSizePx = getIconSizePx();
			const containerStyle = {
				display: 'flex',
				flexWrap: 'wrap',
				gap: showLabels ? '12px' : '8px',
				alignItems: 'center',
				justifyContent: layout === 'horizontal' ? 'flex-start' : 'center',
				flexDirection: layout === 'vertical' ? 'column' : 'row'
			};

			const buttonStyle = {
				display: 'flex',
				alignItems: 'center',
				gap: '8px',
				textDecoration: 'none',
				color: 'inherit'
			};

			const iconStyle = {
				width: iconSizePx + 'px',
				height: iconSizePx + 'px',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center'
			};

			return wp.element.createElement(
				'div',
				{ className: 'podloom-subscribe-buttons podloom-subscribe-buttons--' + layout, style: containerStyle },
				previewData.map((link, index) => wp.element.createElement(
					'a',
					{
						key: index,
						href: link.url,
						target: '_blank',
						rel: 'noopener noreferrer',
						style: buttonStyle,
						title: link.name
					},
					wp.element.createElement(
						'span',
						{
							style: iconStyle,
							dangerouslySetInnerHTML: { __html: link.svg || '' }
						}
					),
					showLabels && wp.element.createElement(
						'span',
						{ style: { fontSize: '14px' } },
						link.name
					)
				))
			);
		};

		// Loading state
		if (loading) {
			return wp.element.createElement(
				'div',
				blockProps,
				wp.element.createElement(
					Placeholder,
					{ icon: 'share', label: __('PodLoom Subscribe Buttons', 'podloom-podcast-player') },
					wp.element.createElement(Spinner),
					wp.element.createElement('p', null, __('Loading podcasts...', 'podloom-podcast-player'))
				)
			);
		}

		// No podcasts configured
		if (podcasts.length === 0) {
			return wp.element.createElement(
				'div',
				blockProps,
				wp.element.createElement(
					Placeholder,
					{ icon: 'share', label: __('PodLoom Subscribe Buttons', 'podloom-podcast-player') },
					wp.element.createElement('p', null, __('No podcasts configured. Add a Transistor API key or RSS feed first.', 'podloom-podcast-player')),
					wp.element.createElement(
						Button,
						{
							variant: 'primary',
							href: podloomData.ajaxUrl.replace('/wp-admin/admin-ajax.php', '') + '/wp-admin/admin.php?page=podloom-settings'
						},
						__('Go to Settings', 'podloom-podcast-player')
					)
				)
			);
		}

		return wp.element.createElement(
			wp.element.Fragment,
			null,
			wp.element.createElement(
				InspectorControls,
				null,
				wp.element.createElement(
					PanelBody,
					{ title: __('Subscribe Settings', 'podloom-podcast-player'), initialOpen: true },
					wp.element.createElement(SelectControl, {
						label: __('Select Podcast', 'podloom-podcast-player'),
						value: source,
						options: buildPodcastOptions(),
						onChange: (value) => setAttributes({ source: value }),
						help: __('Choose which podcast to display subscribe buttons for', 'podloom-podcast-player')
					}),
					wp.element.createElement(RadioControl, {
						label: __('Icon Size', 'podloom-podcast-player'),
						selected: iconSize,
						options: [
							{ label: __('Small (24px)', 'podloom-podcast-player'), value: 'small' },
							{ label: __('Medium (32px)', 'podloom-podcast-player'), value: 'medium' },
							{ label: __('Large (48px)', 'podloom-podcast-player'), value: 'large' }
						],
						onChange: (value) => setAttributes({ iconSize: value })
					}),
					wp.element.createElement(RadioControl, {
						label: __('Color Mode', 'podloom-podcast-player'),
						selected: colorMode,
						options: [
							{ label: __('Brand Colors', 'podloom-podcast-player'), value: 'brand' },
							{ label: __('Monochrome', 'podloom-podcast-player'), value: 'mono' },
							{ label: __('Custom Color', 'podloom-podcast-player'), value: 'custom' }
						],
						onChange: (value) => setAttributes({ colorMode: value })
					}),
					colorMode === 'custom' && wp.element.createElement(
						'div',
						{ style: { marginTop: '12px' } },
						wp.element.createElement(ColorPicker, {
							color: customColor,
							onChange: (value) => setAttributes({ customColor: value }),
							enableAlpha: false
						})
					),
					wp.element.createElement(RadioControl, {
						label: __('Layout', 'podloom-podcast-player'),
						selected: layout,
						options: [
							{ label: __('Horizontal', 'podloom-podcast-player'), value: 'horizontal' },
							{ label: __('Vertical', 'podloom-podcast-player'), value: 'vertical' },
							{ label: __('Grid', 'podloom-podcast-player'), value: 'grid' }
						],
						onChange: (value) => setAttributes({ layout: value })
					}),
					wp.element.createElement(ToggleControl, {
						label: __('Show Labels', 'podloom-podcast-player'),
						checked: showLabels,
						onChange: (value) => setAttributes({ showLabels: value }),
						help: __('Display platform names next to icons', 'podloom-podcast-player')
					})
				)
			),
			wp.element.createElement(
				'div',
				blockProps,
				!source ? wp.element.createElement(
					Placeholder,
					{ icon: 'share', label: __('PodLoom Subscribe Buttons', 'podloom-podcast-player') },
					wp.element.createElement('p', null, __('Select a podcast from the sidebar to display subscribe buttons.', 'podloom-podcast-player'))
				) : previewLoading ? wp.element.createElement(
					'div',
					{ style: { padding: '20px', textAlign: 'center' } },
					wp.element.createElement(Spinner),
					wp.element.createElement('p', null, __('Loading preview...', 'podloom-podcast-player'))
				) : wp.element.createElement(
					'div',
					{ className: 'wp-block-podloom-subscribe-buttons' },
					renderPreview()
				)
			)
		);
	},

	/**
	 * Save function - returns null for dynamic block
	 */
	save: function() {
		return null;
	}
});
} )();
