/**
 * Podcasting 2.0 Tabs and Chapter Navigation
 * Handles tab switching and click-to-seek functionality for podcast chapters
 */

(function () {
    'use strict';

    /**
     * Screen reader live region for announcements
     * Creates a visually hidden element that screen readers will announce
     */
    var liveRegion = null;

    function ensureLiveRegion() {
        if (!liveRegion) {
            liveRegion = document.createElement('div');
            liveRegion.setAttribute('role', 'status');
            liveRegion.setAttribute('aria-live', 'polite');
            liveRegion.setAttribute('aria-atomic', 'true');
            liveRegion.className = 'podloom-sr-announcer';
            // Visually hidden but accessible to screen readers
            liveRegion.style.cssText = 'position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;';
            document.body.appendChild(liveRegion);
        }
        return liveRegion;
    }

    function announceToScreenReader(message) {
        var region = ensureLiveRegion();
        // Clear and set after a brief delay to ensure announcement
        region.textContent = '';
        setTimeout(function () {
            region.textContent = message;
        }, 50);
    }

    /**
     * Initialize tab switching
     */
    function initTabSwitching() {
        // Find all tab containers
        const tabContainers = document.querySelectorAll('.podcast20-tabs');

        tabContainers.forEach(function (container) {
            const tabButtons = container.querySelectorAll('.podcast20-tab-button');
            const tabPanels = container.querySelectorAll('.podcast20-tab-panel');

            // Set initial aria-hidden on non-active panels
            tabPanels.forEach(function (panel) {
                if (!panel.classList.contains('active')) {
                    panel.setAttribute('aria-hidden', 'true');
                } else {
                    panel.setAttribute('aria-hidden', 'false');
                }
            });

            // Set tabindex on buttons for arrow key navigation
            tabButtons.forEach(function (button, index) {
                button.setAttribute('tabindex', button.classList.contains('active') ? '0' : '-1');
            });

            tabButtons.forEach(function (button, index) {
                button.addEventListener('click', function () {
                    activateTab(button, tabButtons, tabPanels, container);
                });

                // Keyboard navigation for tabs
                button.addEventListener('keydown', function (e) {
                    let targetIndex = -1;
                    const currentIndex = index;

                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        targetIndex = (currentIndex + 1) % tabButtons.length;
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        targetIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        targetIndex = 0;
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        targetIndex = tabButtons.length - 1;
                    }

                    if (targetIndex >= 0) {
                        tabButtons[targetIndex].focus();
                        activateTab(tabButtons[targetIndex], tabButtons, tabPanels, container);
                    }
                });
            });
        });

        function activateTab(button, tabButtons, tabPanels, container) {
            const targetTab = button.getAttribute('data-tab');

            // Remove active class from all buttons and panels
            tabButtons.forEach(function (btn) {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
                btn.setAttribute('tabindex', '-1');
            });

            tabPanels.forEach(function (panel) {
                panel.classList.remove('active');
                panel.setAttribute('aria-hidden', 'true');
            });

            // Add active class to clicked button
            button.classList.add('active');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('tabindex', '0');

            // Show corresponding panel
            const targetPanel = container.querySelector('#tab-panel-' + targetTab);
            if (targetPanel) {
                targetPanel.classList.add('active');
                targetPanel.setAttribute('aria-hidden', 'false');
            }
        }
    }

    /**
     * Initialize chapter navigation when DOM is ready
     */
    function initChapterNavigation() {
        // Find all chapter lists on the page
        const chapterLists = document.querySelectorAll('.podcast20-chapters-list');

        chapterLists.forEach(function (chapterList) {
            // Find the closest RSS episode container
            const episodeContainer = chapterList.closest('.rss-episode-player');

            if (!episodeContainer) {
                console.warn('PodLoom: Chapter list found but no episode container');
                return;
            }

            // Find the audio player within this episode
            const audioPlayer = episodeContainer.querySelector('audio');

            if (!audioPlayer) {
                console.warn('PodLoom: Chapter list found but no audio player in episode container');
                return;
            }

            // Store original artwork
            const artworkContainer = episodeContainer.querySelector('.rss-episode-artwork img');
            if (artworkContainer) {
                // Store the original src if not already stored
                if (!artworkContainer.hasAttribute('data-original-src')) {
                    artworkContainer.setAttribute('data-original-src', artworkContainer.src);
                }
            }

            // Get all chapter items (make entire item clickable)
            const chapterItems = chapterList.querySelectorAll('.chapter-item');

            // Add click event to each chapter item
            chapterItems.forEach(function (item) {
                // Make the entire chapter item clickable and accessible
                item.style.cursor = 'pointer';
                item.setAttribute('role', 'button');
                item.setAttribute('tabindex', '0');

                // Handler for activating a chapter
                function activateChapter(e) {
                    // Don't trigger if clicking on the external link icon or its SVG
                    if (e.target.tagName === 'A' && e.target.classList.contains('chapter-external-link')) {
                        return;
                    }
                    // Check if clicking on SVG or path inside the external link
                    if (e.target.closest('.chapter-external-link')) {
                        return;
                    }

                    e.preventDefault();

                    const startTime = parseFloat(item.getAttribute('data-start-time'));

                    if (!isNaN(startTime)) {
                        // Seek to the chapter start time
                        audioPlayer.currentTime = startTime;

                        // Play the audio if it's not already playing
                        if (audioPlayer.paused) {
                            audioPlayer.play().catch(function (error) {
                                console.warn('PodLoom: Could not auto-play audio:', error);
                            });
                        }

                        // Update active state
                        updateActiveChapter(chapterList, startTime);

                        // Announce chapter change to screen readers
                        announceToScreenReader('Playing chapter: ' + (item.querySelector('.chapter-title')?.textContent || ''));
                    }
                }

                item.addEventListener('click', activateChapter);

                // Keyboard support: Enter and Space to activate
                item.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activateChapter(e);
                    }
                });
            });

            // Listen to timeupdate event to highlight current chapter
            audioPlayer.addEventListener('timeupdate', function () {
                const currentTime = audioPlayer.currentTime;
                updateActiveChapter(chapterList, currentTime);
            });
        });
    }

    /**
     * Update active chapter based on current playback time
     *
     * @param {HTMLElement} chapterList The chapter list container
     * @param {number} currentTime Current playback time in seconds
     */
    function updateActiveChapter(chapterList, currentTime) {
        const chapterItems = chapterList.querySelectorAll('.chapter-item');
        let activeChapter = null;

        // Find the chapter that should be active based on current time
        chapterItems.forEach(function (item) {
            const startTime = parseFloat(item.getAttribute('data-start-time'));

            if (!isNaN(startTime) && currentTime >= startTime) {
                activeChapter = item;
            }
        });

        // Remove active class and aria-current from all chapters
        chapterItems.forEach(function (item) {
            item.classList.remove('active');
            item.removeAttribute('aria-current');
        });

        // Add active class and aria-current to current chapter
        if (activeChapter) {
            activeChapter.classList.add('active');
            activeChapter.setAttribute('aria-current', 'true');
        }

        // Update artwork if chapter has image
        const episodeContainer = chapterList.closest('.rss-episode-player');
        if (episodeContainer) {
            const artworkImg = episodeContainer.querySelector('.rss-episode-artwork img');
            if (artworkImg) {
                // Check if active chapter has an image
                let chapterImgSrc = null;
                if (activeChapter) {
                    const chapterImg = activeChapter.querySelector('.chapter-img');
                    if (chapterImg) {
                        chapterImgSrc = chapterImg.src;
                    }
                }

                // If chapter has image, use it. Otherwise revert to original.
                if (chapterImgSrc) {
                    if (artworkImg.src !== chapterImgSrc) {
                        artworkImg.src = chapterImgSrc;
                    }
                } else {
                    const originalSrc = artworkImg.getAttribute('data-original-src');
                    if (originalSrc && artworkImg.src !== originalSrc) {
                        artworkImg.src = originalSrc;
                    }
                }
            }
        }
    }

    /**
     * Try to load transcripts with fallback support
     */
    function tryLoadTranscript(transcripts, index, button, content) {
        var viewer = content.closest('.transcript-viewer');

        if (index >= transcripts.length) {
            // All transcripts failed
            button.classList.remove('loading');
            button.removeAttribute('aria-busy');
            if (viewer) viewer.removeAttribute('aria-busy');

            const firstUrl = transcripts[0].url;

            // Safely escape URL to prevent XSS
            const escapedUrl = (firstUrl || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            content.innerHTML = '<div class="transcript-error" role="alert">Could not load transcript. <a href="' + escapedUrl + '" target="_blank" rel="noopener noreferrer">Open in new tab</a></div>';
            return;
        }

        const transcript = transcripts[index];
        const url = transcript.url;
        const type = transcript.type || 'text/plain';

        // Use WordPress AJAX proxy to bypass CORS
        const proxyUrl = typeof podloomTranscript !== 'undefined' && podloomTranscript.ajaxUrl
            ? podloomTranscript.ajaxUrl + '?action=podloom_fetch_transcript&url=' + encodeURIComponent(url)
            : url;

        fetch(proxyUrl)
            .then(function (response) {
                // Always parse JSON to get error details
                return response.json().then(function (data) {
                    if (!response.ok) {
                        // Log detailed error from server
                        const errorMsg = data.data && data.data.message ? data.data.message : 'HTTP ' + response.status;
                        console.error('Transcript proxy error (' + response.status + '):', errorMsg, 'URL:', url);
                        throw new Error(errorMsg);
                    }
                    return data;
                });
            })
            .then(function (data) {
                // Handle WordPress AJAX response format
                if (data.success && data.data && data.data.content) {
                    const parsed = parseTranscript(data.data.content, type);
                    content.innerHTML = parsed;

                    // Activate button and remove loading state
                    button.classList.remove('loading');
                    button.classList.add('active');
                    button.removeAttribute('aria-busy');
                    button.setAttribute('aria-expanded', 'true');
                    if (viewer) viewer.removeAttribute('aria-busy');

                    // Attach timestamp click handlers
                    attachTimestampHandlers(content);

                    // Announce to screen readers
                    announceToScreenReader('Transcript loaded');
                } else {
                    throw new Error(data.data ? data.data.message : 'Failed to load transcript');
                }
            })
            .catch(function (error) {
                console.warn('Transcript load failed for ' + url + ', trying next format...', error);
                // Try next transcript format
                tryLoadTranscript(transcripts, index + 1, button, content);
            });
    }

    /**
     * Initialize transcript loaders
     */
    function initTranscriptLoaders() {
        const transcriptButtons = document.querySelectorAll('.transcript-format-button');

        transcriptButtons.forEach(function (button) {
            // Set initial aria-expanded state
            button.setAttribute('aria-expanded', 'false');

            button.addEventListener('click', function () {
                const viewer = button.closest('.podcast20-transcripts').querySelector('.transcript-viewer');
                const content = viewer.querySelector('.transcript-content');

                // Toggle if already active
                if (button.classList.contains('active')) {
                    button.classList.remove('active');
                    button.setAttribute('aria-expanded', 'false');
                    viewer.style.display = 'none';
                    content.innerHTML = '';
                    return;
                }

                // Get all available transcripts
                let transcripts;
                try {
                    const transcriptsData = button.getAttribute('data-transcripts');
                    transcripts = JSON.parse(transcriptsData);
                } catch (e) {
                    // Fallback to single transcript from data attributes
                    transcripts = [{
                        url: button.getAttribute('data-url'),
                        type: button.getAttribute('data-type') || 'text/plain'
                    }];
                }

                // Show loading state with aria-busy
                button.classList.add('loading');
                button.setAttribute('aria-busy', 'true');
                viewer.style.display = 'block';
                viewer.setAttribute('aria-busy', 'true');
                content.innerHTML = '<div class="transcript-loading" role="status">Loading transcript...</div>';

                // Try loading transcripts with fallback
                tryLoadTranscript(transcripts, 0, button, content);
            });
        });

        // Close button handlers
        const closeButtons = document.querySelectorAll('.transcript-close');
        closeButtons.forEach(function (closeBtn) {
            closeBtn.addEventListener('click', function () {
                const viewer = closeBtn.closest('.transcript-viewer');
                const transcripts = closeBtn.closest('.podcast20-transcripts');
                const button = transcripts.querySelector('.transcript-format-button');
                const content = viewer.querySelector('.transcript-content');

                button.classList.remove('active');
                button.setAttribute('aria-expanded', 'false');
                viewer.style.display = 'none';
                content.innerHTML = '';

                // Return focus to the transcript button
                button.focus();
            });
        });
    }

    /**
     * Parse transcript based on type
     */
    function parseTranscript(text, type) {
        // Auto-detect HTML content even if type says text/plain
        // Some services (like Transistor) return HTML for .txt URLs
        var trimmedText = text.trim();
        if (trimmedText.startsWith('<!DOCTYPE') ||
            trimmedText.startsWith('<html') ||
            (trimmedText.includes('<body') && trimmedText.includes('</body>'))) {
            return parseHTMLTranscript(text);
        }

        // Parse based on declared type
        if (type.includes('html')) {
            return parseHTMLTranscript(text);
        } else if (type.includes('srt')) {
            return parseSRT(text);
        } else if (type.includes('vtt')) {
            return parseVTT(text);
        } else if (type.includes('json')) {
            return parseJSONTranscript(text);
        } else if (type.includes('text/plain') || type.includes('text/txt') || type.includes('.txt')) {
            return parsePlainText(text);
        }
        // Fallback for unknown types - treat as plain text
        return parsePlainText(text);
    }

    /**
     * Parse plain text transcript
     */
    function parsePlainText(text) {
        // Convert line breaks to paragraphs for better readability
        var lines = text.trim().split(/\n\s*\n/);
        var output = '';

        lines.forEach(function (paragraph) {
            if (paragraph.trim()) {
                output += '<p>' + escapeHTML(paragraph.trim().replace(/\n/g, '<br>')) + '</p>';
            }
        });

        return output || '<p>' + escapeHTML(text) + '</p>';
    }

    /**
     * Parse HTML transcript
     */
    function parseHTMLTranscript(html) {
        // Use DOMParser for safer HTML parsing
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var temp = document.createElement('div');

        // Try to find the main transcript content (avoid navigation, headers, etc.)
        var transcriptSelectors = [
            '.transcript',
            '.transcript-content',
            '.episode-transcript',
            '[data-transcript]',
            'article',
            'main',
            '.content',
            '.episode-content'
        ];

        var transcriptContent = null;
        for (var i = 0; i < transcriptSelectors.length; i++) {
            transcriptContent = doc.querySelector(transcriptSelectors[i]);
            if (transcriptContent) {
                break;
            }
        }

        // If we found a transcript container, use only that
        if (transcriptContent) {
            temp.appendChild(transcriptContent.cloneNode(true));
        } else {
            // Fallback: copy body content but remove common non-transcript elements
            if (doc.body) {
                Array.from(doc.body.childNodes).forEach(function (node) {
                    temp.appendChild(node.cloneNode(true));
                });
            }

            // Remove common page elements that aren't transcript content
            var unwantedSelectors = [
                'header', 'nav', 'footer',
                '.header', '.navigation', '.nav', '.footer',
                '.sidebar', '.menu', '.social',
                '[role="navigation"]', '[role="banner"]', '[role="complementary"]'
            ];
            unwantedSelectors.forEach(function (selector) {
                var elements = temp.querySelectorAll(selector);
                elements.forEach(function (el) { el.remove(); });
            });
        }

        // Remove dangerous elements (script, iframe, object, embed, etc.)
        var dangerousTags = ['script', 'iframe', 'object', 'embed', 'link', 'style', 'form'];
        dangerousTags.forEach(function (tag) {
            var elements = temp.querySelectorAll(tag);
            elements.forEach(function (el) { el.remove(); });
        });

        // Remove dangerous attributes (on*, style with expressions, etc.)
        var allElements = temp.querySelectorAll('*');
        allElements.forEach(function (el) {
            // Remove all on* event handlers
            Array.from(el.attributes).forEach(function (attr) {
                if (attr.name.startsWith('on') || attr.name === 'style') {
                    el.removeAttribute(attr.name);
                }
            });

            // Sanitize href attributes to prevent javascript: URLs
            if (el.hasAttribute('href')) {
                var href = el.getAttribute('href');
                if (href && (href.toLowerCase().startsWith('javascript:') || href.toLowerCase().startsWith('data:'))) {
                    el.removeAttribute('href');
                }
            }
        });

        // Look for elements with data-time or data-timestamp attributes and make them clickable
        var timeElements = temp.querySelectorAll('[data-time], [data-timestamp]');
        timeElements.forEach(function (el) {
            // Add transcript-timestamp class if not already present
            if (!el.classList.contains('transcript-timestamp')) {
                el.classList.add('transcript-timestamp');
            }
            // Normalize attribute: use data-time
            if (el.hasAttribute('data-timestamp') && !el.hasAttribute('data-time')) {
                el.setAttribute('data-time', el.getAttribute('data-timestamp'));
            }
        });

        // Look for common timestamp patterns and convert them
        // Pattern: [00:00:00] or [00:00] or similar
        var textNodes = getTextNodes(temp);
        textNodes.forEach(function (node) {
            var text = node.textContent;
            // Match timestamps like [00:00:00], [00:00], (00:00:00), (00:00)
            var timestampRegex = /[\[\(]?(\d{1,2}):(\d{2})(?::(\d{2}))?[\]\)]?/g;
            var match;
            var replacements = [];

            while ((match = timestampRegex.exec(text)) !== null) {
                var totalSeconds;

                if (match[3]) {
                    // Three parts: HH:MM:SS
                    var hours = parseInt(match[1], 10);
                    var minutes = parseInt(match[2], 10);
                    var seconds = parseInt(match[3], 10);
                    totalSeconds = hours * 3600 + minutes * 60 + seconds;
                } else {
                    // Two parts: MM:SS (not HH:MM!)
                    var minutes = parseInt(match[1], 10);
                    var seconds = parseInt(match[2], 10);
                    totalSeconds = minutes * 60 + seconds;
                }

                // Only convert if it looks like a reasonable timestamp (not too large)
                if (totalSeconds < 86400) { // Less than 24 hours
                    replacements.push({
                        match: match[0],
                        index: match.index,
                        time: totalSeconds
                    });
                }
            }

            // Apply replacements in reverse order to maintain indices
            if (replacements.length > 0) {
                var fragment = document.createDocumentFragment();
                var lastIndex = 0;

                replacements.forEach(function (replacement) {
                    // Add text before timestamp
                    if (replacement.index > lastIndex) {
                        fragment.appendChild(document.createTextNode(text.substring(lastIndex, replacement.index)));
                    }

                    // Create clickable timestamp
                    var span = document.createElement('span');
                    span.className = 'transcript-timestamp';
                    span.setAttribute('data-time', replacement.time);
                    span.textContent = replacement.match;
                    fragment.appendChild(span);

                    lastIndex = replacement.index + replacement.match.length;
                });

                // Add remaining text
                if (lastIndex < text.length) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
                }

                // Replace the text node with the fragment
                if (node.parentNode) {
                    node.parentNode.replaceChild(fragment, node);
                }
            }
        });

        return temp.innerHTML;
    }

    /**
     * Get all text nodes from an element
     */
    function getTextNodes(element) {
        var textNodes = [];
        var walk = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
        var node;
        while (node = walk.nextNode()) {
            // Skip empty text nodes
            if (node.textContent.trim()) {
                textNodes.push(node);
            }
        }
        return textNodes;
    }

    /**
     * Parse SRT subtitle format
     */
    function parseSRT(text) {
        var blocks = text.trim().split(/\n\s*\n/);
        var entries = [];

        // First pass: parse all SRT entries
        blocks.forEach(function (block) {
            var lines = block.split('\n');
            if (lines.length < 3) return;

            var timestamps = lines[1].split(' --> ');
            if (timestamps.length < 2) return;

            var startTime = srtTimeToSeconds(timestamps[0]);
            var endTime = srtTimeToSeconds(timestamps[1]);
            var textContent = lines.slice(2).join(' ').trim();

            if (!isNaN(startTime) && textContent) {
                entries.push({
                    startTime: startTime,
                    endTime: endTime,
                    text: textContent
                });
            }
        });

        if (entries.length === 0) {
            return '<p>No transcript content found.</p>';
        }

        // Second pass: group entries into chunks based on time gaps
        var chunks = [];
        var currentChunk = null;
        var gapThreshold = 3; // Start new paragraph if gap is 3+ seconds

        entries.forEach(function (entry, index) {
            var previousEntry = index > 0 ? entries[index - 1] : null;
            var timeGap = previousEntry ? (entry.startTime - previousEntry.endTime) : 0;

            // Start a new chunk if it's the first entry or there's a significant time gap
            if (!currentChunk || timeGap >= gapThreshold) {
                if (currentChunk) {
                    chunks.push(currentChunk);
                }
                currentChunk = {
                    startTime: entry.startTime,
                    texts: [entry.text]
                };
            } else {
                // Add to current chunk
                currentChunk.texts.push(entry.text);
            }
        });

        // Don't forget the last chunk
        if (currentChunk) {
            chunks.push(currentChunk);
        }

        // Build output with chunked paragraphs
        var output = '';
        chunks.forEach(function (chunk) {
            var combinedText = chunk.texts.join(' ');
            output += '<p><span class="transcript-timestamp" data-time="' + chunk.startTime + '">' +
                formatTime(chunk.startTime) + '</span> ' + escapeHTML(combinedText) + '</p>';
        });

        return output;
    }

    /**
     * Parse VTT subtitle format
     */
    function parseVTT(text) {
        // Remove WEBVTT header
        text = text.replace(/^WEBVTT\s*\n/, '');
        // Process similar to SRT
        return parseSRT(text);
    }

    /**
     * Parse JSON transcript
     */
    function parseJSONTranscript(text) {
        try {
            var data = JSON.parse(text);
            var output = '';

            if (data.segments && Array.isArray(data.segments)) {
                data.segments.forEach(function (segment) {
                    if (segment.startTime !== undefined && segment.body) {
                        output += '<p><span class="transcript-timestamp" data-time="' + segment.startTime + '">' +
                            formatTime(segment.startTime) + '</span>' + escapeHTML(segment.body) + '</p>';
                    }
                });
            }

            return output || '<p>No transcript content found.</p>';
        } catch (e) {
            return '<p class="transcript-error">Invalid JSON format.</p>';
        }
    }

    /**
     * Convert SRT timestamp to seconds
     */
    function srtTimeToSeconds(timestamp) {
        var parts = timestamp.split(':');
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        var seconds = parseFloat(parts[2].replace(',', '.'));
        return hours * 3600 + minutes * 60 + seconds;
    }

    /**
     * Format seconds to MM:SS or HH:MM:SS
     */
    function formatTime(seconds) {
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = Math.floor(seconds % 60);

        if (hours > 0) {
            return hours + ':' + pad(minutes) + ':' + pad(secs);
        }
        return minutes + ':' + pad(secs);
    }

    function pad(num) {
        return num < 10 ? '0' + num : num;
    }

    /**
     * Escape HTML for safe display
     */
    function escapeHTML(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Attach click handlers to transcript timestamps
     */
    function attachTimestampHandlers(content) {
        var timestamps = content.querySelectorAll('.transcript-timestamp');
        var transcriptContainer = content.closest('.podcast20-transcripts');
        var episodeContainer = transcriptContainer ? transcriptContainer.closest('.rss-episode-player') : null;

        if (!episodeContainer) return;

        var audioPlayer = episodeContainer.querySelector('audio');
        if (!audioPlayer) return;

        timestamps.forEach(function (timestamp) {
            // Make timestamps accessible
            timestamp.setAttribute('role', 'button');
            timestamp.setAttribute('tabindex', '0');

            function seekToTimestamp() {
                var time = parseFloat(timestamp.getAttribute('data-time'));
                if (!isNaN(time)) {
                    audioPlayer.currentTime = time;

                    if (audioPlayer.paused) {
                        audioPlayer.play().catch(function (error) {
                            console.warn('Could not auto-play audio:', error);
                        });
                    }

                    // Announce to screen readers
                    announceToScreenReader('Seeking to ' + timestamp.textContent);
                }
            }

            timestamp.addEventListener('click', seekToTimestamp);

            // Keyboard support
            timestamp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    seekToTimestamp();
                }
            });
        });
    }

    /**
     * Initialize skip buttons for audio navigation
     */
    function initSkipButtons() {
        var skipButtons = document.querySelectorAll('.podloom-skip-btn');

        skipButtons.forEach(function (button) {
            // Skip if already initialized
            if (button.hasAttribute('data-initialized')) return;
            button.setAttribute('data-initialized', 'true');

            button.addEventListener('click', function () {
                var skipAmount = parseInt(button.getAttribute('data-skip'), 10);
                var episodeContainer = button.closest('.rss-episode-player');

                if (!episodeContainer) return;

                var audioPlayer = episodeContainer.querySelector('audio');
                if (!audioPlayer) return;

                // Calculate new time, clamped to valid range
                var newTime = audioPlayer.currentTime + skipAmount;
                newTime = Math.max(0, Math.min(newTime, audioPlayer.duration || Infinity));

                audioPlayer.currentTime = newTime;
            });
        });
    }

    /**
     * Episode Prefetcher - loads additional episodes in background
     * Used by playlist players to seamlessly load more content
     */
    function createPrefetcher(feedId, initialEpisodes, totalCount) {
        var buffer = [];           // Prefetched but not yet rendered
        var displayed = initialEpisodes.slice(); // Currently shown episodes
        var loading = false;
        var hasMore = displayed.length < totalCount;
        var offset = displayed.length;
        var bufferSize = 20;       // Keep 20 episodes ahead
        var fetchSize = 20;        // Fetch 20 at a time

        function prefetch() {
            if (loading || !hasMore) return Promise.resolve([]);

            // Check if we need to prefetch (buffer running low)
            if (buffer.length >= bufferSize / 2) return Promise.resolve([]);

            loading = true;

            var ajaxUrl = typeof podloomPlaylist !== 'undefined' && podloomPlaylist.ajaxUrl
                ? podloomPlaylist.ajaxUrl
                : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

            var nonce = typeof podloomPlaylist !== 'undefined' && podloomPlaylist.nonce
                ? podloomPlaylist.nonce
                : '';

            return fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=podloom_get_rss_episodes_public' +
                      '&feed_id=' + encodeURIComponent(feedId) +
                      '&offset=' + offset +
                      '&limit=' + fetchSize +
                      '&nonce=' + encodeURIComponent(nonce)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                loading = false;
                if (data.success && data.data && data.data.episodes) {
                    var newEpisodes = data.data.episodes;
                    buffer = buffer.concat(newEpisodes);
                    offset += newEpisodes.length;
                    hasMore = data.data.has_more;
                    return newEpisodes;
                }
                return [];
            })
            .catch(function() {
                loading = false;
                return [];
            });
        }

        function getMore(count) {
            count = count || 10;

            // If buffer has enough, use it
            if (buffer.length >= count) {
                var toShow = buffer.splice(0, count);
                displayed = displayed.concat(toShow);

                // Trigger prefetch if buffer running low
                if (buffer.length < bufferSize / 2 && hasMore) {
                    prefetch();
                }

                return Promise.resolve(toShow);
            }

            // Need to fetch first
            return prefetch().then(function() {
                if (buffer.length > 0) {
                    var toShow = buffer.splice(0, Math.min(count, buffer.length));
                    displayed = displayed.concat(toShow);
                    return toShow;
                }
                return [];
            });
        }

        // Start prefetching immediately if more episodes exist
        if (hasMore) {
            setTimeout(prefetch, 1000); // Delay 1s to let page settle
        }

        return {
            getMore: getMore,
            hasMore: function() { return hasMore || buffer.length > 0; },
            getDisplayed: function() { return displayed; },
            isLoading: function() { return loading; }
        };
    }

    /**
     * Initialize RSS playlist player functionality
     * Handles episode switching, now-playing indicators, and auto-play next
     */
    function initPlaylistPlayer() {
        var playlistPlayers = document.querySelectorAll('.rss-playlist-player');

        playlistPlayers.forEach(function (playerContainer) {
            // Skip if already initialized
            if (playerContainer.hasAttribute('data-playlist-initialized')) {
                return;
            }
            playerContainer.setAttribute('data-playlist-initialized', 'true');

            // Get episode data from embedded JSON
            var dataScript = playerContainer.querySelector('.podloom-playlist-data');
            if (!dataScript) {
                console.warn('PodLoom: No playlist data found');
                return;
            }

            var episodes;
            try {
                episodes = JSON.parse(dataScript.textContent);
            } catch (e) {
                console.error('PodLoom: Failed to parse playlist data', e);
                return;
            }

            if (!episodes || episodes.length === 0) {
                return;
            }

            // Get player elements
            var audioPlayer = playerContainer.querySelector('.podloom-playlist-audio');
            var artworkImg = playerContainer.querySelector('.podloom-playlist-artwork');
            var titleEl = playerContainer.querySelector('.podloom-playlist-title');
            var dateEl = playerContainer.querySelector('.podloom-playlist-date');
            var durationEl = playerContainer.querySelector('.podloom-playlist-duration');
            var episodesList = playerContainer.querySelector('.podloom-episodes-list');

            if (!audioPlayer || !episodesList) {
                return;
            }

            var currentIndex = 0;
            var feedId = playerContainer.getAttribute('data-feed-id') || '';

            // Get total episode count and create prefetcher
            var totalCount = parseInt(playerContainer.getAttribute('data-total-episodes') || episodes.length, 10);
            var prefetcher = createPrefetcher(feedId, episodes, totalCount);

            /**
             * Format duration in seconds to human-readable format
             */
            function formatDuration(seconds) {
                if (!seconds || isNaN(seconds)) return '';

                seconds = parseInt(seconds, 10);
                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var secs = seconds % 60;

                if (hours > 0) {
                    return hours + ':' + padZero(minutes) + ':' + padZero(secs);
                }
                return minutes + ':' + padZero(secs);
            }

            function padZero(num) {
                return num < 10 ? '0' + num : num;
            }

            /**
             * Format Unix timestamp to localized date
             */
            function formatDate(timestamp) {
                if (!timestamp) return '';
                var date = new Date(timestamp * 1000);
                return date.toLocaleDateString();
            }

            /**
             * Update now-playing indicator in episode list
             */
            function updateNowPlayingIndicator(newIndex) {
                var items = episodesList.querySelectorAll('.podloom-episode-item');

                items.forEach(function (item, idx) {
                    var overlay = item.querySelector('.podloom-episode-play-overlay');
                    if (!overlay) return;

                    if (idx === newIndex) {
                        item.classList.add('podloom-episode-current');
                        item.setAttribute('aria-current', 'true');
                        // Replace play icon with now-playing animation
                        overlay.innerHTML = '<span class="podloom-now-playing-icon" aria-hidden="true">' +
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">' +
                            '<rect x="4" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite"/></rect>' +
                            '<rect x="10" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="8;16;8" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="0.8s" repeatCount="indefinite"/></rect>' +
                            '<rect x="16" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite" begin="0.2s"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite" begin="0.2s"/></rect>' +
                            '</svg></span>';
                    } else {
                        item.classList.remove('podloom-episode-current');
                        item.removeAttribute('aria-current');
                        // Replace with play icon
                        overlay.innerHTML = '<span class="podloom-play-icon" aria-hidden="true">' +
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">' +
                            '<polygon points="5,3 19,12 5,21"/>' +
                            '</svg></span>';
                    }
                });
            }

            /**
             * Update player metadata (title, date, artwork, duration)
             */
            function updatePlayerMetadata(episode) {
                if (titleEl && episode.title) {
                    titleEl.textContent = episode.title;
                }

                if (dateEl && episode.date) {
                    dateEl.textContent = formatDate(episode.date);
                }

                if (durationEl && episode.duration) {
                    durationEl.textContent = formatDuration(episode.duration);
                }

                if (artworkImg && episode.image) {
                    artworkImg.src = episode.image;
                    artworkImg.alt = episode.title || '';
                    // Store as original src for chapter navigation
                    artworkImg.setAttribute('data-original-src', episode.image);
                }
            }

            /**
             * Update Podcasting 2.0 tabs (Description, Credits, Chapters, Transcripts)
             */
            function updateP20Tabs(episode) {
                // Update Description tab
                var descPanel = playerContainer.querySelector('[data-tab-id="description"]');
                if (descPanel) {
                    var descContent = descPanel.querySelector('.podloom-playlist-description');
                    if (descContent) {
                        var desc = episode.content || episode.description || '';
                        descContent.innerHTML = desc;
                    }
                }

                // For Credits, Chapters, Transcripts - need to fetch from server
                // if podcast20 data exists in episode, use it
                var p20 = episode.podcast20;

                // Update Chapters tab
                // Note: chapters structure is { chapters: [...], url: '...' }
                var chaptersPanel = playerContainer.querySelector('[data-tab-id="chapters"]');
                if (chaptersPanel) {
                    var chaptersContent = chaptersPanel.querySelector('.podloom-playlist-chapters');
                    if (chaptersContent) {
                        // Check for nested chapters array
                        var chaptersArray = p20 && p20.chapters && p20.chapters.chapters ? p20.chapters.chapters : null;
                        if (chaptersArray && chaptersArray.length > 0) {
                            // Render chapters client-side
                            chaptersContent.innerHTML = renderChaptersHTML(chaptersArray);
                            // Reinitialize chapter click handlers
                            initChapterClickHandlers(chaptersContent, audioPlayer);
                        } else {
                            // Try to fetch via AJAX if we have a feed ID
                            if (feedId && episode.id) {
                                fetchEpisodeP20Data(feedId, episode.id, function (data) {
                                    var fetchedChapters = data && data.chapters && data.chapters.chapters ? data.chapters.chapters : null;
                                    if (fetchedChapters && fetchedChapters.length > 0) {
                                        chaptersContent.innerHTML = renderChaptersHTML(fetchedChapters);
                                        initChapterClickHandlers(chaptersContent, audioPlayer);
                                    } else {
                                        chaptersContent.innerHTML = '<p class="no-content">No chapters available for this episode.</p>';
                                    }
                                });
                            } else {
                                chaptersContent.innerHTML = '<p class="no-content">No chapters available for this episode.</p>';
                            }
                        }
                    }
                }

                // Update Credits tab
                var creditsPanel = playerContainer.querySelector('[data-tab-id="credits"]');
                if (creditsPanel) {
                    var creditsContent = creditsPanel.querySelector('.podloom-playlist-credits');
                    if (creditsContent) {
                        var hasPeople = p20 && ((p20.people_channel && p20.people_channel.length > 0) || (p20.people_episode && p20.people_episode.length > 0));
                        if (hasPeople) {
                            var allPeople = [];
                            if (p20.people_channel) allPeople = allPeople.concat(p20.people_channel);
                            if (p20.people_episode) allPeople = allPeople.concat(p20.people_episode);
                            creditsContent.innerHTML = renderCreditsHTML(allPeople);
                        } else if (feedId && episode.id) {
                            fetchEpisodeP20Data(feedId, episode.id, function (data) {
                                if (data) {
                                    var people = [];
                                    if (data.people_channel) people = people.concat(data.people_channel);
                                    if (data.people_episode) people = people.concat(data.people_episode);
                                    if (people.length > 0) {
                                        creditsContent.innerHTML = renderCreditsHTML(people);
                                    } else {
                                        creditsContent.innerHTML = '<p class="no-content">No credits available for this episode.</p>';
                                    }
                                } else {
                                    creditsContent.innerHTML = '<p class="no-content">No credits available for this episode.</p>';
                                }
                            });
                        } else {
                            creditsContent.innerHTML = '<p class="no-content">No credits available for this episode.</p>';
                        }
                    }
                }

                // Update Transcripts tab
                var transcriptsPanel = playerContainer.querySelector('[data-tab-id="transcripts"]');
                if (transcriptsPanel) {
                    var transcriptsContent = transcriptsPanel.querySelector('.podloom-playlist-transcripts');
                    if (transcriptsContent) {
                        if (p20 && p20.transcripts && p20.transcripts.length > 0) {
                            transcriptsContent.innerHTML = renderTranscriptsHTML(p20.transcripts);
                            // Reinitialize transcript loaders
                            initTranscriptLoadersInContainer(transcriptsContent);
                        } else if (feedId && episode.id) {
                            fetchEpisodeP20Data(feedId, episode.id, function (data) {
                                if (data && data.transcripts && data.transcripts.length > 0) {
                                    transcriptsContent.innerHTML = renderTranscriptsHTML(data.transcripts);
                                    initTranscriptLoadersInContainer(transcriptsContent);
                                } else {
                                    transcriptsContent.innerHTML = '<p class="no-content">No transcript available for this episode.</p>';
                                }
                            });
                        } else {
                            transcriptsContent.innerHTML = '<p class="no-content">No transcript available for this episode.</p>';
                        }
                    }
                }
            }

            /**
             * Render chapters HTML (matches PHP podloom_render_chapters structure)
             */
            function renderChaptersHTML(chapters) {
                if (!chapters || chapters.length === 0) {
                    return '<p class="no-content">No chapters available for this episode.</p>';
                }

                var html = '<div class="podcast20-chapters-list">';
                html += '<h4 class="chapters-heading">Chapters</h4>';

                chapters.forEach(function (chapter) {
                    var time = chapter.startTime || 0;
                    var formattedTime = formatDuration(time);
                    var title = escapeHTML(chapter.title || '');

                    html += '<div class="chapter-item" data-start-time="' + time + '">';

                    // Chapter image or placeholder
                    if (chapter.img) {
                        html += '<img src="' + escapeHTML(chapter.img) + '" alt="' + title + '" class="chapter-img" loading="lazy" />';
                    } else {
                        html += '<div class="chapter-img-placeholder"></div>';
                    }

                    // Chapter info
                    html += '<div class="chapter-info">';
                    html += '<button class="chapter-timestamp" data-start-time="' + time + '">' + formattedTime + '</button>';

                    // Chapter title with optional external link
                    html += '<span class="chapter-title">' + title;
                    if (chapter.url) {
                        html += ' <a href="' + escapeHTML(chapter.url) + '" target="_blank" rel="noopener noreferrer" class="chapter-external-link" title="Open chapter link">';
                        html += '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-left: 4px;">';
                        html += '<path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>';
                        html += '<path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>';
                        html += '</svg></a>';
                    }
                    html += '</span>';

                    html += '</div>'; // .chapter-info
                    html += '</div>'; // .chapter-item
                });

                html += '</div>'; // .podcast20-chapters-list

                return html;
            }

            /**
             * Render credits/people HTML (matches PHP podloom_render_people structure)
             */
            function renderCreditsHTML(people) {
                if (!people || people.length === 0) {
                    return '<p class="no-content">No credits available for this episode.</p>';
                }

                var html = '<div class="podcast20-people">';
                html += '<h4 class="podcast20-heading">Credits</h4>';
                html += '<div class="podcast20-people-list">';

                people.forEach(function (person) {
                    if (!person.name) return;

                    var name = escapeHTML(person.name);
                    var role = person.role ? escapeHTML(person.role) : '';

                    html += '<div class="podcast20-person">';

                    // Person image or default avatar
                    if (person.img) {
                        html += '<img src="' + escapeHTML(person.img) + '" alt="' + name + '" class="podcast20-person-img">';
                    } else {
                        html += '<div class="podcast20-person-avatar">';
                        html += '<svg width="40" height="40" viewBox="0 0 16 16" fill="currentColor">';
                        html += '<path d="M11 6a3 3 0 11-6 0 3 3 0 016 0z"/>';
                        html += '<path d="M2 0a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V2a2 2 0 00-2-2H2zm12 1a1 1 0 011 1v12a1 1 0 01-1 1v-1c0-1-1-4-6-4s-6 3-6 4v1a1 1 0 01-1-1V2a1 1 0 011-1h12z"/>';
                        html += '</svg></div>';
                    }

                    html += '<div class="podcast20-person-info">';

                    // Role first
                    if (role) {
                        html += '<span class="podcast20-person-role">' + role.charAt(0).toUpperCase() + role.slice(1) + '</span>';
                    }

                    // Name (with optional link)
                    if (person.href) {
                        html += '<a href="' + escapeHTML(person.href) + '" target="_blank" rel="noopener noreferrer" class="podcast20-person-name">' + name + '</a>';
                    } else {
                        html += '<span class="podcast20-person-name">' + name + '</span>';
                    }

                    html += '</div>'; // .podcast20-person-info
                    html += '</div>'; // .podcast20-person
                });

                html += '</div>'; // .podcast20-people-list
                html += '</div>'; // .podcast20-people

                return html;
            }

            /**
             * Render transcripts HTML (matches PHP podloom_render_transcripts structure)
             */
            function renderTranscriptsHTML(transcripts) {
                if (!transcripts || transcripts.length === 0) {
                    return '<p class="no-content">No transcript available for this episode.</p>';
                }

                // Sort by format preference: HTML > SRT > VTT > JSON > text/plain
                var formatPriority = {
                    'text/html': 1,
                    'application/x-subrip': 2,
                    'text/srt': 2,
                    'text/vtt': 3,
                    'application/json': 4,
                    'text/plain': 5
                };

                transcripts.sort(function (a, b) {
                    var aPriority = formatPriority[a.type] || 999;
                    var bPriority = formatPriority[b.type] || 999;
                    return aPriority - bPriority;
                });

                // Get the primary (highest priority) transcript
                var primary = transcripts[0];
                var transcriptsJson = JSON.stringify(transcripts);

                var html = '<div class="podcast20-transcripts">';
                html += '<div class="transcript-formats">';

                // Button with data attributes
                html += '<button class="transcript-format-button" data-url="' + escapeHTML(primary.url) + '" data-type="' + escapeHTML(primary.type || 'text/plain') + '" data-transcripts=\'' + escapeHTML(transcriptsJson).replace(/'/g, '&#39;') + '\'>';
                html += '<svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">';
                html += '<path d="M14 4.5V14a2 2 0 01-2 2H4a2 2 0 01-2-2V2a2 2 0 012-2h5.5L14 4.5zm-3 0A1.5 1.5 0 019.5 3V1H4a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V4.5h-2z"/>';
                html += '<path d="M3 9.5h10v1H3v-1zm0 2h10v1H3v-1z"/>';
                html += '</svg>';
                html += '<span>Click for Transcript</span>';
                html += '</button>';

                // Fallback external link
                html += ' <a href="' + escapeHTML(primary.url) + '" target="_blank" rel="noopener noreferrer" class="transcript-external-link" title="Open transcript in new tab">';
                html += '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">';
                html += '<path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>';
                html += '<path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>';
                html += '</svg></a>';

                html += '</div>'; // .transcript-formats

                // Transcript viewer (hidden)
                html += '<div class="transcript-viewer" style="display: none;">';
                html += '<div class="transcript-content"></div>';
                html += '<button class="transcript-close">Close</button>';
                html += '</div>'; // .transcript-viewer

                html += '</div>'; // .podcast20-transcripts

                return html;
            }

            /**
             * Initialize chapter click handlers in a container
             */
            function initChapterClickHandlers(container, audio) {
                var chapterItems = container.querySelectorAll('.chapter-item');
                chapterItems.forEach(function (item) {
                    item.style.cursor = 'pointer';
                    item.addEventListener('click', function (e) {
                        if (e.target.closest('.chapter-external-link')) return;
                        e.preventDefault();
                        var startTime = parseFloat(item.getAttribute('data-start-time'));
                        if (!isNaN(startTime)) {
                            audio.currentTime = startTime;
                            if (audio.paused) {
                                audio.play().catch(function (err) {
                                    console.warn('PodLoom: Could not auto-play', err);
                                });
                            }
                        }
                    });
                });
            }

            /**
             * Initialize transcript loaders in a specific container
             */
            function initTranscriptLoadersInContainer(container) {
                var buttons = container.querySelectorAll('.transcript-format-button');
                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var viewer = button.closest('.podcast20-transcripts').querySelector('.transcript-viewer');
                        var content = viewer.querySelector('.transcript-content');

                        if (button.classList.contains('active')) {
                            button.classList.remove('active');
                            viewer.style.display = 'none';
                            content.innerHTML = '';
                            return;
                        }

                        var transcripts;
                        try {
                            transcripts = JSON.parse(button.getAttribute('data-transcripts'));
                        } catch (e) {
                            transcripts = [];
                        }

                        if (transcripts.length === 0) return;

                        button.classList.add('loading');
                        viewer.style.display = 'block';
                        content.innerHTML = '<div class="transcript-loading">Loading transcript...</div>';

                        tryLoadTranscript(transcripts, 0, button, content);
                    });
                });

                var closeButtons = container.querySelectorAll('.transcript-close');
                closeButtons.forEach(function (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        var viewer = closeBtn.closest('.transcript-viewer');
                        var transcripts = closeBtn.closest('.podcast20-transcripts');
                        var button = transcripts.querySelector('.transcript-format-button');
                        var content = viewer.querySelector('.transcript-content');

                        button.classList.remove('active');
                        viewer.style.display = 'none';
                        content.innerHTML = '';
                    });
                });
            }

            /**
             * Fetch episode P2.0 data via AJAX
             */
            function fetchEpisodeP20Data(feedId, episodeId, callback) {
                if (typeof podloomPlaylist === 'undefined' || !podloomPlaylist.ajaxUrl) {
                    callback(null);
                    return;
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', podloomPlaylist.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success && response.data) {
                                    callback(response.data);
                                } else {
                                    callback(null);
                                }
                            } catch (e) {
                                callback(null);
                            }
                        } else {
                            callback(null);
                        }
                    }
                };

                xhr.send('action=podloom_get_episode_p20&feed_id=' + encodeURIComponent(feedId) + '&episode_id=' + encodeURIComponent(episodeId) + '&nonce=' + encodeURIComponent(podloomPlaylist.nonce || ''));
            }

            /**
             * Switch to a specific episode
             */
            function switchToEpisode(index) {
                if (index < 0 || index >= episodes.length) {
                    return;
                }

                var episode = episodes[index];
                currentIndex = index;

                // Update audio source
                var sourceEl = audioPlayer.querySelector('source');
                if (sourceEl) {
                    sourceEl.src = episode.audio_url;
                } else {
                    audioPlayer.src = episode.audio_url;
                }

                // Load and play
                audioPlayer.load();
                audioPlayer.play().catch(function (error) {
                    console.warn('PodLoom: Could not auto-play episode', error);
                });

                // Update metadata
                updatePlayerMetadata(episode);

                // Update now-playing indicator
                updateNowPlayingIndicator(index);

                // Update P2.0 tabs
                updateP20Tabs(episode);

                // Scroll the current episode into view in the list
                var currentItem = episodesList.querySelector('[data-episode-index="' + index + '"]');
                if (currentItem) {
                    currentItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                // Announce episode change to screen readers
                announceToScreenReader('Now playing: ' + (episode.title || 'Episode ' + (index + 1)));
            }

            /**
             * Escape HTML entities
             */
            function escapeHTML(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Click handler for episode items
            var episodeItems = episodesList.querySelectorAll('.podloom-episode-item');
            episodeItems.forEach(function (item, idx) {
                item.style.cursor = 'pointer';
                item.setAttribute('role', 'button');
                item.setAttribute('tabindex', '0');

                // Set initial aria-current for first episode
                if (idx === 0) {
                    item.setAttribute('aria-current', 'true');
                }

                function activateEpisode() {
                    var index = parseInt(item.getAttribute('data-episode-index'), 10);
                    if (!isNaN(index) && index !== currentIndex) {
                        switchToEpisode(index);
                    } else if (index === currentIndex && audioPlayer.paused) {
                        // If clicking current episode and paused, resume
                        audioPlayer.play().catch(function (err) {
                            console.warn('PodLoom: Could not play', err);
                        });
                    }
                }

                item.addEventListener('click', activateEpisode);

                // Keyboard support: Enter and Space to activate
                item.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activateEpisode();
                    }
                });
            });

            // Auto-play next episode when current one ends
            audioPlayer.addEventListener('ended', function () {
                var nextIndex = currentIndex + 1;
                if (nextIndex < episodes.length) {
                    switchToEpisode(nextIndex);
                }
            });

            // Update chapter highlighting for playlist player
            audioPlayer.addEventListener('timeupdate', function () {
                var chaptersContainer = playerContainer.querySelector('.podloom-playlist-chapters .podcast20-chapters-list');
                if (chaptersContainer) {
                    var chapterItems = chaptersContainer.querySelectorAll('.chapter-item');
                    var currentTime = audioPlayer.currentTime;
                    var activeChapter = null;

                    chapterItems.forEach(function (item) {
                        var startTime = parseFloat(item.getAttribute('data-start-time'));
                        if (!isNaN(startTime) && currentTime >= startTime) {
                            activeChapter = item;
                        }
                    });

                    chapterItems.forEach(function (item) {
                        item.classList.remove('active');
                    });

                    if (activeChapter) {
                        activeChapter.classList.add('active');
                    }

                    // Update artwork if chapter has image
                    if (artworkImg) {
                        var chapterImgSrc = null;
                        if (activeChapter) {
                            var chapterImg = activeChapter.querySelector('.chapter-img');
                            if (chapterImg) {
                                chapterImgSrc = chapterImg.src;
                            }
                        }

                        if (chapterImgSrc) {
                            if (artworkImg.src !== chapterImgSrc) {
                                artworkImg.src = chapterImgSrc;
                            }
                        } else {
                            var originalSrc = artworkImg.getAttribute('data-original-src');
                            if (originalSrc && artworkImg.src !== originalSrc) {
                                artworkImg.src = originalSrc;
                            }
                        }
                    }
                }
            });

            /**
             * Render additional episode items to the list
             */
            function renderMoreEpisodes(newEpisodes) {
                newEpisodes.forEach(function(episode, idx) {
                    var index = episodes.length;
                    episodes.push(episode); // Add to main episodes array

                    var item = document.createElement('div');
                    item.className = 'podloom-episode-item';
                    item.setAttribute('data-episode-index', index);
                    item.setAttribute('role', 'button');
                    item.setAttribute('tabindex', '0');
                    item.style.cursor = 'pointer';

                    var thumb = document.createElement('div');
                    thumb.className = 'podloom-episode-thumb';
                    if (episode.image) {
                        thumb.innerHTML = '<img src="' + escapeHTML(episode.image) + '" alt="' + escapeHTML(episode.title || '') + '" loading="lazy">';
                    } else {
                        thumb.classList.add('podloom-episode-thumb-placeholder');
                    }

                    var overlay = document.createElement('div');
                    overlay.className = 'podloom-episode-play-overlay';
                    overlay.innerHTML = '<span class="podloom-play-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg></span>';
                    thumb.appendChild(overlay);

                    var info = document.createElement('div');
                    info.className = 'podloom-episode-info';

                    var titleRow = document.createElement('div');
                    titleRow.className = 'podloom-episode-title-row';
                    titleRow.innerHTML = '<div class="podloom-episode-item-title">' + escapeHTML(episode.title || '') + '</div>';
                    info.appendChild(titleRow);

                    var metaRow = document.createElement('div');
                    metaRow.className = 'podloom-episode-meta-row';
                    if (episode.date) {
                        metaRow.innerHTML += '<span class="podloom-episode-item-date">' + formatDate(episode.date) + '</span>';
                    }
                    if (episode.duration) {
                        metaRow.innerHTML += '<span class="podloom-episode-item-duration">' + formatDuration(episode.duration) + '</span>';
                    }
                    info.appendChild(metaRow);

                    item.appendChild(thumb);
                    item.appendChild(info);
                    episodesList.appendChild(item);

                    // Add click handler
                    item.addEventListener('click', function() {
                        if (index !== currentIndex) {
                            switchToEpisode(index);
                        } else if (audioPlayer.paused) {
                            audioPlayer.play().catch(function() {});
                        }
                    });

                    // Keyboard support
                    item.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            item.click();
                        }
                    });
                });
            }

            // Scroll-based prefetch loading
            var loadingMore = false;
            episodesList.addEventListener('scroll', function() {
                if (loadingMore || !prefetcher.hasMore()) return;

                // Load more when scrolled within 200px of bottom
                var scrollBottom = episodesList.scrollHeight - episodesList.scrollTop - episodesList.clientHeight;
                if (scrollBottom < 200) {
                    loadingMore = true;
                    prefetcher.getMore(10).then(function(newEpisodes) {
                        if (newEpisodes.length > 0) {
                            renderMoreEpisodes(newEpisodes);
                        }
                        loadingMore = false;
                    });
                }
            });
        });
    }

    /**
     * Initialize playlist search and pagination
     * Handles search filtering (local + server) and "Load More" button
     */
    function initPlaylistSearchAndPagination() {
        var playlistPlayers = document.querySelectorAll('.rss-playlist-player');

        playlistPlayers.forEach(function (playerContainer) {
            // Skip if already initialized for search/pagination
            if (playerContainer.hasAttribute('data-search-pagination-initialized')) return;
            playerContainer.setAttribute('data-search-pagination-initialized', 'true');

            var feedId = playerContainer.getAttribute('data-feed-id');
            var totalEpisodes = parseInt(playerContainer.getAttribute('data-total'), 10) || 0;
            var loadedCount = parseInt(playerContainer.getAttribute('data-loaded'), 10) || 0;
            var loadStep = parseInt(playerContainer.getAttribute('data-step'), 10) || 20;
            var playlistOrder = playerContainer.getAttribute('data-order') || 'episodic';

            var searchInput = playerContainer.querySelector('.podloom-episodes-search-input');
            var searchClear = playerContainer.querySelector('.podloom-episodes-search-clear');
            var searchStatus = playerContainer.querySelector('.podloom-search-status');
            var episodesList = playerContainer.querySelector('.podloom-episodes-list');
            var loadMoreBtn = playerContainer.querySelector('.podloom-episodes-load-more');
            var noResultsEl = playerContainer.querySelector('.podloom-episodes-no-results');

            var localDebounceTimer = null;
            var serverDebounceTimer = null;
            var currentSearchTerm = '';
            var isSearching = false;

            // Get episodes data from embedded JSON
            var dataScript = playerContainer.querySelector('.podloom-playlist-data');
            var episodes = [];
            if (dataScript) {
                try {
                    episodes = JSON.parse(dataScript.textContent);
                } catch (e) {
                    console.error('PodLoom: Failed to parse playlist data');
                }
            }

            /**
             * Filter loaded episodes locally (fast)
             */
            function filterLocal(searchTerm) {
                searchTerm = searchTerm.toLowerCase().trim();
                var items = episodesList.querySelectorAll('.podloom-episode-item');
                var visibleCount = 0;

                items.forEach(function (item) {
                    var itemTerm = (item.getAttribute('data-search-term') || '').toLowerCase();
                    var matches = !searchTerm || itemTerm.indexOf(searchTerm) !== -1;

                    if (matches) {
                        item.classList.remove('podloom-search-hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('podloom-search-hidden');
                    }
                });

                // Hide load more during search
                if (loadMoreBtn) {
                    loadMoreBtn.closest('.podloom-episodes-load-more-wrapper').style.display = searchTerm ? 'none' : '';
                }

                return visibleCount;
            }

            /**
             * Search server for more results (when local results are insufficient)
             */
            function searchServer(searchTerm) {
                if (!searchTerm || searchTerm.length < 2 || isSearching) return;

                isSearching = true;

                // Show loading state
                if (searchStatus) {
                    searchStatus.textContent = 'Searching...';
                }

                var ajaxUrl = typeof podloomPlaylist !== 'undefined' && podloomPlaylist.ajaxUrl
                    ? podloomPlaylist.ajaxUrl
                    : '/wp-admin/admin-ajax.php';

                var formData = new FormData();
                formData.append('action', 'podloom_search_episodes');
                formData.append('feed_id', feedId);
                formData.append('search', searchTerm);
                formData.append('order', playlistOrder);
                formData.append('limit', '50');

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    isSearching = false;

                    if (data.success && data.data.episodes) {
                        displaySearchResults(data.data.episodes, searchTerm);
                    } else {
                        if (searchStatus) {
                            searchStatus.textContent = '';
                        }
                    }
                })
                .catch(function (error) {
                    isSearching = false;
                    console.error('PodLoom: Search failed', error);
                    if (searchStatus) {
                        searchStatus.textContent = '';
                    }
                });
            }

            /**
             * Display search results from server
             */
            function displaySearchResults(results, searchTerm) {
                // Hide all existing items first
                var existingItems = episodesList.querySelectorAll('.podloom-episode-item');
                existingItems.forEach(function (item) {
                    item.classList.add('podloom-search-hidden');
                });

                var visibleCount = 0;

                results.forEach(function (episode, idx) {
                    // Check if episode is already in DOM (by title match)
                    var existingItem = null;
                    existingItems.forEach(function (item) {
                        var titleEl = item.querySelector('.podloom-episode-item-title');
                        if (titleEl && titleEl.textContent === episode.title) {
                            existingItem = item;
                        }
                    });

                    if (existingItem) {
                        // Show existing item
                        existingItem.classList.remove('podloom-search-hidden');
                        visibleCount++;
                    } else {
                        // Add new item to DOM (from search results)
                        var newItem = createEpisodeItem(episode, 'search-' + idx);
                        newItem.classList.add('podloom-search-result');
                        episodesList.appendChild(newItem);
                        initEpisodeItemClick(newItem, playerContainer, episode);
                        visibleCount++;
                    }
                });

                // Update status
                if (searchStatus) {
                    searchStatus.textContent = visibleCount + ' episode' + (visibleCount !== 1 ? 's' : '') + ' found';
                }

                // Show/hide no results
                if (noResultsEl) {
                    noResultsEl.style.display = visibleCount === 0 ? '' : 'none';
                    if (visibleCount === 0) {
                        noResultsEl.textContent = 'No episodes found for "' + searchTerm + '"';
                    }
                }

                // Announce to screen readers
                announceToScreenReader(visibleCount + ' episode' + (visibleCount !== 1 ? 's' : '') + ' found');
            }

            /**
             * Create episode item HTML element
             */
            function createEpisodeItem(episode, index) {
                var div = document.createElement('div');
                div.className = 'podloom-episode-item';
                div.setAttribute('data-episode-index', index);
                div.setAttribute('data-search-term', (episode.title || '').toLowerCase());
                div.setAttribute('role', 'button');
                div.setAttribute('tabindex', '0');
                div.style.cursor = 'pointer';

                var date = episode.date ? new Date(episode.date * 1000).toLocaleDateString() : '';
                var duration = episode.duration ? formatDurationSimple(episode.duration) : '';

                var thumbHtml = '<div class="podloom-episode-thumb' + (!episode.image ? ' podloom-episode-thumb-placeholder' : '') + '">';
                if (episode.image) {
                    thumbHtml += '<img src="' + escapeHTMLAttr(episode.image) + '" alt="' + escapeHTMLAttr(episode.title) + '" loading="lazy" />';
                }
                thumbHtml += '<div class="podloom-episode-play-overlay">' +
                    '<span class="podloom-play-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg></span>' +
                    '</div></div>';

                var infoHtml = '<div class="podloom-episode-info">' +
                    '<div class="podloom-episode-title-row">' +
                    '<span class="podloom-episode-item-title">' + escapeHTMLAttr(episode.title) + '</span>' +
                    '</div>' +
                    '<div class="podloom-episode-meta-row">' +
                    (date ? '<span class="podloom-episode-item-date">' + date + '</span>' : '') +
                    (duration ? '<span class="podloom-episode-item-duration">' + duration + '</span>' : '') +
                    '</div></div>';

                div.innerHTML = thumbHtml + infoHtml;

                // Store episode data on element for later use
                div.podloomEpisodeData = episode;

                return div;
            }

            /**
             * Format duration in seconds to human readable
             */
            function formatDurationSimple(seconds) {
                if (!seconds || isNaN(seconds)) return '';
                seconds = parseInt(seconds, 10);
                var hours = Math.floor(seconds / 3600);
                var minutes = Math.floor((seconds % 3600) / 60);
                var secs = seconds % 60;
                if (hours > 0) {
                    return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (secs < 10 ? '0' : '') + secs;
                }
                return minutes + ':' + (secs < 10 ? '0' : '') + secs;
            }

            /**
             * Escape HTML for attribute values
             */
            function escapeHTMLAttr(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            /**
             * Clear search and restore original state
             */
            function clearSearch() {
                currentSearchTerm = '';

                if (searchInput) {
                    searchInput.value = '';
                }

                if (searchClear) {
                    searchClear.style.display = 'none';
                }

                if (searchStatus) {
                    searchStatus.textContent = '';
                }

                // Remove search result items (dynamically added)
                var searchResults = episodesList.querySelectorAll('.podloom-search-result');
                searchResults.forEach(function (item) {
                    item.remove();
                });

                // Show all original items
                var items = episodesList.querySelectorAll('.podloom-episode-item');
                items.forEach(function (item) {
                    item.classList.remove('podloom-search-hidden');
                });

                // Show load more wrapper if there are more episodes
                if (loadMoreBtn) {
                    var wrapper = loadMoreBtn.closest('.podloom-episodes-load-more-wrapper');
                    if (wrapper) {
                        wrapper.style.display = '';
                    }
                }

                // Hide no results
                if (noResultsEl) {
                    noResultsEl.style.display = 'none';
                }
            }

            /**
             * Handle search input
             */
            function handleSearchInput() {
                var searchTerm = searchInput.value.trim();
                currentSearchTerm = searchTerm;

                // Show/hide clear button
                if (searchClear) {
                    searchClear.style.display = searchTerm ? '' : 'none';
                }

                // Clear timers
                clearTimeout(localDebounceTimer);
                clearTimeout(serverDebounceTimer);

                if (!searchTerm) {
                    clearSearch();
                    return;
                }

                // Local filter (fast, 100ms debounce)
                localDebounceTimer = setTimeout(function () {
                    var localCount = filterLocal(searchTerm);

                    // Update status
                    if (searchStatus) {
                        if (totalEpisodes > loadedCount && searchTerm.length >= 2) {
                            searchStatus.textContent = localCount + ' found (searching...)';
                        } else {
                            searchStatus.textContent = localCount + ' episode' + (localCount !== 1 ? 's' : '') + ' found';
                        }
                    }

                    // If we have more episodes than loaded, also search server
                    if (totalEpisodes > loadedCount && searchTerm.length >= 2) {
                        // Server search (slower, 500ms total debounce)
                        serverDebounceTimer = setTimeout(function () {
                            if (currentSearchTerm === searchTerm) {
                                searchServer(searchTerm);
                            }
                        }, 400); // 400ms after local = 500ms total
                    } else {
                        // Show no results message if needed
                        if (noResultsEl) {
                            noResultsEl.style.display = localCount === 0 ? '' : 'none';
                            if (localCount === 0) {
                                noResultsEl.textContent = 'No episodes found for "' + searchTerm + '"';
                            }
                        }
                    }
                }, 100);
            }

            /**
             * Initialize click handler for an episode item
             */
            function initEpisodeItemClick(item, container, episodeData) {
                function activateEpisode() {
                    var data = episodeData || item.podloomEpisodeData;
                    if (!data || !data.audio_url) return;

                    // Find the audio player
                    var audio = container.querySelector('.podloom-playlist-audio');
                    if (!audio) return;

                    // Update audio source
                    var sourceEl = audio.querySelector('source');
                    if (sourceEl) {
                        sourceEl.src = data.audio_url;
                    } else {
                        audio.src = data.audio_url;
                    }

                    // Load and play
                    audio.load();
                    audio.play().catch(function (e) {
                        console.warn('PodLoom: Could not play', e);
                    });

                    // Update player metadata
                    updatePlayerMetadataFromEpisode(container, data);

                    // Update now-playing indicators
                    var allItems = container.querySelectorAll('.podloom-episode-item');
                    allItems.forEach(function (i) {
                        i.classList.remove('podloom-episode-current');
                        i.removeAttribute('aria-current');
                        // Reset play icon
                        var overlay = i.querySelector('.podloom-episode-play-overlay');
                        if (overlay) {
                            overlay.innerHTML = '<span class="podloom-play-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg></span>';
                        }
                    });
                    item.classList.add('podloom-episode-current');
                    item.setAttribute('aria-current', 'true');
                    // Update to now-playing icon
                    var overlay = item.querySelector('.podloom-episode-play-overlay');
                    if (overlay) {
                        overlay.innerHTML = '<span class="podloom-now-playing-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">' +
                            '<rect x="4" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite"/></rect>' +
                            '<rect x="10" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="8;16;8" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="0.8s" repeatCount="indefinite"/></rect>' +
                            '<rect x="16" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite" begin="0.2s"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite" begin="0.2s"/></rect>' +
                            '</svg></span>';
                    }

                    // Announce to screen readers
                    announceToScreenReader('Now playing: ' + (data.title || 'Episode'));
                }

                item.addEventListener('click', activateEpisode);
                item.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        activateEpisode();
                    }
                });
            }

            /**
             * Update player metadata from episode data
             */
            function updatePlayerMetadataFromEpisode(container, episode) {
                var titleEl = container.querySelector('.podloom-playlist-title');
                var dateEl = container.querySelector('.podloom-playlist-date');
                var durationEl = container.querySelector('.podloom-playlist-duration');
                var artworkEl = container.querySelector('.podloom-playlist-artwork');

                if (titleEl && episode.title) {
                    titleEl.textContent = episode.title;
                }

                if (dateEl && episode.date) {
                    dateEl.textContent = new Date(episode.date * 1000).toLocaleDateString();
                }

                if (durationEl && episode.duration) {
                    durationEl.textContent = formatDurationSimple(episode.duration);
                }

                if (artworkEl && episode.image) {
                    artworkEl.src = episode.image;
                    artworkEl.alt = episode.title || '';
                    artworkEl.setAttribute('data-original-src', episode.image);
                }
            }

            /**
             * Load more episodes via AJAX
             */
            function loadMoreEpisodes() {
                if (!loadMoreBtn || loadMoreBtn.hasAttribute('disabled')) return;

                loadMoreBtn.setAttribute('disabled', 'true');
                var loadMoreText = loadMoreBtn.querySelector('.podloom-load-more-text');
                var loadMoreLoading = loadMoreBtn.querySelector('.podloom-load-more-loading');
                if (loadMoreText) loadMoreText.style.display = 'none';
                if (loadMoreLoading) loadMoreLoading.style.display = '';

                var ajaxUrl = typeof podloomPlaylist !== 'undefined' && podloomPlaylist.ajaxUrl
                    ? podloomPlaylist.ajaxUrl
                    : '/wp-admin/admin-ajax.php';

                var formData = new FormData();
                formData.append('action', 'podloom_playlist_episodes');
                formData.append('feed_id', feedId);
                formData.append('offset', loadedCount);
                formData.append('limit', loadStep);
                formData.append('order', playlistOrder);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    loadMoreBtn.removeAttribute('disabled');
                    if (loadMoreText) loadMoreText.style.display = '';
                    if (loadMoreLoading) loadMoreLoading.style.display = 'none';

                    if (data.success && data.data.episodes) {
                        var newEpisodes = data.data.episodes;

                        // Add new episodes to DOM
                        newEpisodes.forEach(function (episode, idx) {
                            var newIndex = loadedCount + idx;
                            var newItem = createEpisodeItem(episode, newIndex);
                            episodesList.appendChild(newItem);
                            initEpisodeItemClick(newItem, playerContainer, episode);

                            // Add to episodes array
                            episodes.push(episode);
                        });

                        loadedCount += newEpisodes.length;
                        playerContainer.setAttribute('data-loaded', loadedCount);

                        // Update button
                        var remaining = totalEpisodes - loadedCount;
                        if (remaining <= 0 || !data.data.has_more) {
                            var wrapper = loadMoreBtn.closest('.podloom-episodes-load-more-wrapper');
                            if (wrapper) wrapper.style.display = 'none';
                        } else if (loadMoreText) {
                            loadMoreText.textContent = 'Load More (' + remaining + ' remaining)';
                        }
                    }
                })
                .catch(function (error) {
                    loadMoreBtn.removeAttribute('disabled');
                    if (loadMoreText) loadMoreText.style.display = '';
                    if (loadMoreLoading) loadMoreLoading.style.display = 'none';
                    console.error('PodLoom: Load more failed', error);
                });
            }

            // Event listeners
            if (searchInput) {
                searchInput.addEventListener('input', handleSearchInput);
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        clearSearch();
                        searchInput.blur();
                    }
                });
            }

            if (searchClear) {
                searchClear.addEventListener('click', function () {
                    clearSearch();
                    if (searchInput) searchInput.focus();
                });
            }

            if (loadMoreBtn) {
                loadMoreBtn.addEventListener('click', loadMoreEpisodes);
            }
        });
    }

    /**
     * Initialize custom audio player controls
     * Replaces native HTML5 audio controls with custom UI
     */
    function initCustomPlayer() {
        var players = document.querySelectorAll('.podloom-player-container');

        players.forEach(function (container) {
            // Skip if already initialized
            if (container.hasAttribute('data-player-initialized')) return;
            container.setAttribute('data-player-initialized', 'true');

            var audio = container.querySelector('.podloom-audio-element');
            if (!audio) return;

            // Audio element cleanup: ensure it's hidden from assistive tech
            // (custom controls handle accessibility) and remove native controls
            audio.setAttribute('aria-hidden', 'true');
            audio.removeAttribute('controls');
            audio.setAttribute('tabindex', '-1'); // Prevent keyboard focus

            // Store reference on container for external access if needed
            container.podloomAudio = audio;

            var playToggle = container.querySelector('.podloom-play-toggle');
            var timelineContainer = container.querySelector('.podloom-timeline-container');
            var timelineProgress = container.querySelector('.podloom-timeline-progress');
            var timelineSlider = container.querySelector('.podloom-timeline-slider');
            var currentTimeEl = container.querySelector('.podloom-current-time');
            var durationEl = container.querySelector('.podloom-duration');
            var speedBtn = container.querySelector('.podloom-speed-btn');

            var speeds = [1, 1.25, 1.5, 2];
            var currentSpeedIndex = 0;

            // Play/Pause toggle
            if (playToggle) {
                playToggle.addEventListener('click', function () {
                    if (audio.paused) {
                        audio.play().catch(function (err) {
                            console.warn('PodLoom: Could not play audio', err);
                        });
                    } else {
                        audio.pause();
                    }
                });
            }

            // Update play/pause icon and aria-label on audio events
            audio.addEventListener('play', function () {
                container.classList.add('is-playing');
                if (playToggle) {
                    playToggle.setAttribute('aria-label', playToggle.getAttribute('data-pause-label') || 'Pause');
                }
            });

            audio.addEventListener('pause', function () {
                container.classList.remove('is-playing');
                if (playToggle) {
                    playToggle.setAttribute('aria-label', playToggle.getAttribute('data-play-label') || 'Play');
                }
            });

            audio.addEventListener('ended', function () {
                container.classList.remove('is-playing');
                if (playToggle) {
                    playToggle.setAttribute('aria-label', playToggle.getAttribute('data-play-label') || 'Play');
                }
            });

            // Update timeline and time display on timeupdate
            audio.addEventListener('timeupdate', function () {
                var current = audio.currentTime;
                var duration = audio.duration || 0;

                // Update current time display
                if (currentTimeEl) {
                    currentTimeEl.textContent = formatTime(current);
                }

                // Update timeline progress
                if (duration > 0 && timelineProgress) {
                    var percent = (current / duration) * 100;
                    timelineProgress.style.width = percent + '%';
                }

                // Update slider value
                if (duration > 0 && timelineSlider) {
                    timelineSlider.value = current;
                }
            });

            // Update duration display when metadata loads
            audio.addEventListener('loadedmetadata', function () {
                if (durationEl) {
                    durationEl.textContent = formatTime(audio.duration);
                }
                if (timelineSlider) {
                    timelineSlider.max = audio.duration;
                }
            });

            // Handle duration update (for streams or when duration becomes available)
            audio.addEventListener('durationchange', function () {
                if (durationEl && audio.duration && isFinite(audio.duration)) {
                    durationEl.textContent = formatTime(audio.duration);
                }
                if (timelineSlider && audio.duration && isFinite(audio.duration)) {
                    timelineSlider.max = audio.duration;
                }
            });

            // Seek via timeline slider
            if (timelineSlider) {
                timelineSlider.addEventListener('input', function () {
                    var seekTime = parseFloat(timelineSlider.value);
                    if (!isNaN(seekTime)) {
                        audio.currentTime = seekTime;
                    }
                });
            }

            // Click on timeline container to seek
            if (timelineContainer) {
                timelineContainer.addEventListener('click', function (e) {
                    var rect = timelineContainer.getBoundingClientRect();
                    var clickX = e.clientX - rect.left;
                    var percent = clickX / rect.width;
                    var seekTime = percent * (audio.duration || 0);
                    if (!isNaN(seekTime) && isFinite(seekTime)) {
                        audio.currentTime = seekTime;
                    }
                });
            }

            // Speed toggle
            if (speedBtn) {
                speedBtn.addEventListener('click', function () {
                    currentSpeedIndex = (currentSpeedIndex + 1) % speeds.length;
                    var newSpeed = speeds[currentSpeedIndex];
                    audio.playbackRate = newSpeed;
                    speedBtn.textContent = newSpeed + 'x';
                });
            }

            // Note: Skip buttons are handled by initSkipButtons() which uses .podloom-skip-btn[data-skip]
        });
    }

    /**
     * Initialize all features when DOM is ready
     */
    /**
     * Process queued images for background caching
     * This runs after page load to avoid blocking rendering
     */
    function processImageCacheQueue() {
        // Check if we have the ajax URL (only available if image caching is enabled)
        if (typeof podloomImageCache === 'undefined' || !podloomImageCache.ajaxUrl) {
            return;
        }

        // Use fetch to process the queue in the background
        var nonce = podloomImageCache.nonce ? encodeURIComponent(podloomImageCache.nonce) : '';
        fetch(podloomImageCache.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=podloom_process_image_cache&podloom_image_cache_nonce=' + nonce
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success && data.data.remaining > 0) {
                    // More images to process, continue after a short delay
                    setTimeout(processImageCacheQueue, 1000);
                }
            })
            .catch(function () {
                // Silently fail - image caching is not critical
            });
    }

    function init() {
        initTabSwitching();
        initChapterNavigation();
        initTranscriptLoaders();
        initSkipButtons();
        initPlaylistPlayer();
        initPlaylistSearchAndPagination();
        initCustomPlayer();

        // Mark all players as ready (reveals them via CSS transition)
        document.querySelectorAll('.podloom-player-container:not(.podloom-ready), .rss-playlist-player:not(.podloom-ready)').forEach(function(player) {
            player.classList.add('podloom-ready');
        });

        // Process image cache queue after a short delay (let page render first)
        setTimeout(processImageCacheQueue, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

    // Re-initialize when new content is loaded (for dynamic content/AJAX)
    if (typeof window.MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 &&
                            (node.classList && (node.classList.contains('podcast20-tabs') ||
                                node.classList.contains('podcast20-chapters-list') ||
                                node.classList.contains('rss-playlist-player') ||
                                node.classList.contains('podloom-player-container')) ||
                                node.querySelector && (node.querySelector('.podcast20-tabs') ||
                                    node.querySelector('.podcast20-chapters-list') ||
                                    node.querySelector('.rss-playlist-player') ||
                                    node.querySelector('.podloom-player-container')))) {
                            init();
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // CSS animation detection for lazy-loaded players
    // Fires when podloomNodeInserted animation starts on uninitialized players
    // More reliable than MutationObserver for async DOM insertions (page builders, AJAX)
    function handlePlayerAnimationStart(event) {
        if (event.animationName === 'podloomNodeInserted') {
            var player = event.target;
            // Only initialize if not already ready
            if (!player.classList.contains('podloom-ready')) {
                init();
            }
        }
    }
    document.addEventListener('animationstart', handlePlayerAnimationStart, false);
    // Webkit prefix for older Safari
    document.addEventListener('webkitAnimationStart', handlePlayerAnimationStart, false);
})();
