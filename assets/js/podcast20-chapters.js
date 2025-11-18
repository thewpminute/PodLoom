/**
 * Podcasting 2.0 Tabs and Chapter Navigation
 * Handles tab switching and click-to-seek functionality for podcast chapters
 */

(function() {
    'use strict';

    /**
     * Initialize tab switching
     */
    function initTabSwitching() {
        // Find all tab containers
        const tabContainers = document.querySelectorAll('.podcast20-tabs');

        tabContainers.forEach(function(container) {
            const tabButtons = container.querySelectorAll('.podcast20-tab-button');
            const tabPanels = container.querySelectorAll('.podcast20-tab-panel');

            tabButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const targetTab = button.getAttribute('data-tab');

                    // Remove active class from all buttons and panels
                    tabButtons.forEach(function(btn) {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                    });

                    tabPanels.forEach(function(panel) {
                        panel.classList.remove('active');
                    });

                    // Add active class to clicked button
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');

                    // Show corresponding panel
                    const targetPanel = container.querySelector('#tab-panel-' + targetTab);
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                    }
                });
            });
        });
    }

    /**
     * Initialize chapter navigation when DOM is ready
     */
    function initChapterNavigation() {
        // Find all chapter lists on the page
        const chapterLists = document.querySelectorAll('.podcast20-chapters-list');

        chapterLists.forEach(function(chapterList) {
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

            // Get all chapter items (make entire item clickable)
            const chapterItems = chapterList.querySelectorAll('.chapter-item');

            // Add click event to each chapter item
            chapterItems.forEach(function(item) {
                // Make the entire chapter item clickable
                item.style.cursor = 'pointer';

                item.addEventListener('click', function(e) {
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
                            audioPlayer.play().catch(function(error) {
                                console.warn('PodLoom: Could not auto-play audio:', error);
                            });
                        }

                        // Update active state
                        updateActiveChapter(chapterList, startTime);
                    }
                });
            });

            // Listen to timeupdate event to highlight current chapter
            audioPlayer.addEventListener('timeupdate', function() {
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
        chapterItems.forEach(function(item) {
            const startTime = parseFloat(item.getAttribute('data-start-time'));

            if (!isNaN(startTime) && currentTime >= startTime) {
                activeChapter = item;
            }
        });

        // Remove active class from all chapters
        chapterItems.forEach(function(item) {
            item.classList.remove('active');
        });

        // Add active class to current chapter
        if (activeChapter) {
            activeChapter.classList.add('active');
        }
    }

    /**
     * Try to load transcripts with fallback support
     */
    function tryLoadTranscript(transcripts, index, button, content) {
        if (index >= transcripts.length) {
            // All transcripts failed
            button.classList.remove('loading');
            const firstUrl = transcripts[0].url;

            // Safely escape URL to prevent XSS
            const escapedUrl = (firstUrl || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            content.innerHTML = '<div class="transcript-error">Could not load transcript. <a href="' + escapedUrl + '" target="_blank" rel="noopener noreferrer">Open in new tab</a></div>';
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
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                // Handle WordPress AJAX response format
                if (data.success && data.data && data.data.content) {
                    const parsed = parseTranscript(data.data.content, type);
                    content.innerHTML = parsed;

                    // Activate button
                    button.classList.remove('loading');
                    button.classList.add('active');

                    // Attach timestamp click handlers
                    attachTimestampHandlers(content);
                } else {
                    throw new Error(data.data ? data.data.message : 'Failed to load transcript');
                }
            })
            .catch(function(error) {
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

        transcriptButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const viewer = button.closest('.podcast20-transcripts').querySelector('.transcript-viewer');
                const content = viewer.querySelector('.transcript-content');

                // Toggle if already active
                if (button.classList.contains('active')) {
                    button.classList.remove('active');
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

                // Show loading state
                button.classList.add('loading');
                viewer.style.display = 'block';
                content.innerHTML = '<div class="transcript-loading">Loading transcript...</div>';

                // Try loading transcripts with fallback
                tryLoadTranscript(transcripts, 0, button, content);
            });
        });

        // Close button handlers
        const closeButtons = document.querySelectorAll('.transcript-close');
        closeButtons.forEach(function(closeBtn) {
            closeBtn.addEventListener('click', function() {
                const viewer = closeBtn.closest('.transcript-viewer');
                const transcripts = closeBtn.closest('.podcast20-transcripts');
                const button = transcripts.querySelector('.transcript-format-button');
                const content = viewer.querySelector('.transcript-content');

                button.classList.remove('active');
                viewer.style.display = 'none';
                content.innerHTML = '';
            });
        });
    }

    /**
     * Parse transcript based on type
     */
    function parseTranscript(text, type) {
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

        lines.forEach(function(paragraph) {
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

        // Copy body content safely
        if (doc.body) {
            Array.from(doc.body.childNodes).forEach(function(node) {
                temp.appendChild(node.cloneNode(true));
            });
        }

        // Remove dangerous elements (script, iframe, object, embed, etc.)
        var dangerousTags = ['script', 'iframe', 'object', 'embed', 'link', 'style', 'form'];
        dangerousTags.forEach(function(tag) {
            var elements = temp.querySelectorAll(tag);
            elements.forEach(function(el) { el.remove(); });
        });

        // Remove dangerous attributes (on*, style with expressions, etc.)
        var allElements = temp.querySelectorAll('*');
        allElements.forEach(function(el) {
            // Remove all on* event handlers
            Array.from(el.attributes).forEach(function(attr) {
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
        timeElements.forEach(function(el) {
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
        textNodes.forEach(function(node) {
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

                replacements.forEach(function(replacement) {
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
        blocks.forEach(function(block) {
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

        entries.forEach(function(entry, index) {
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
        chunks.forEach(function(chunk) {
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
                data.segments.forEach(function(segment) {
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

        timestamps.forEach(function(timestamp) {
            timestamp.addEventListener('click', function() {
                var time = parseFloat(timestamp.getAttribute('data-time'));
                if (!isNaN(time)) {
                    audioPlayer.currentTime = time;
                    if (audioPlayer.paused) {
                        audioPlayer.play().catch(function(error) {
                            console.warn('Could not auto-play audio:', error);
                        });
                    }
                }
            });
        });
    }

    /**
     * Initialize all features when DOM is ready
     */
    function init() {
        initTabSwitching();
        initChapterNavigation();
        initTranscriptLoaders();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

    // Re-initialize when new content is loaded (for dynamic content/AJAX)
    if (typeof window.MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1 &&
                            (node.classList && (node.classList.contains('podcast20-tabs') ||
                             node.classList.contains('podcast20-chapters-list')) ||
                             node.querySelector && (node.querySelector('.podcast20-tabs') ||
                             node.querySelector('.podcast20-chapters-list')))) {
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
})();
