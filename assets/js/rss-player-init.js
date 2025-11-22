/**
 * PodLoom RSS Player Initialization
 * Initializes Plyr.js if selected as the player type
 */
document.addEventListener('DOMContentLoaded', function () {
    // Check if Plyr is loaded and player type is set to plyr
    if (typeof Plyr !== 'undefined' && typeof podloomData !== 'undefined' && podloomData.playerType === 'plyr') {

        // Find all audio elements in RSS episode players
        const audioElements = document.querySelectorAll('.rss-episode-player audio');

        audioElements.forEach(function (audioElement) {
            // Initialize Plyr
            const player = new Plyr(audioElement, {
                controls: [
                    'rewind', // Rewind button
                    'play', // Play/pause playback
                    'fast-forward', // Fast forward button
                    'progress', // The progress bar and scrubber for playback and buffering
                    'current-time', // The current time of playback
                    'duration', // The full duration of the media
                    'mute', // Toggle mute
                    'volume', // Volume control
                    'speed', // Speed control (shows 1x, 1.5x etc)
                ],
                // Custom settings
                seekTime: 10, // Default seek time
                speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
                keyboard: { focused: true, global: false },
                tooltips: { controls: true, seek: true },
                i18n: {
                    speed: 'Speed',
                    normal: 'Normal',
                    rewind: 'Rewind 10s',
                    fastForward: 'Forward 30s',
                }
            });

            // Expose Plyr instance on the original audio element for other scripts to use
            audioElement.plyr = player;

            // Add custom class to wrapper for styling
            const wrapper = audioElement.closest('.plyr');
            if (wrapper) {
                wrapper.classList.add('podloom-plyr-theme');
            }

            // Update speed button text to show current speed (e.g. "1x")
            const updateSpeedLabel = () => {
                const speedBtn = wrapper.querySelector('[data-plyr="speed"]');
                if (speedBtn) {
                    speedBtn.innerHTML = player.speed + 'x';
                }
            };

            player.on('ready', updateSpeedLabel);
            player.on('ratechange', updateSpeedLabel);
        });

        console.log('PodLoom: Plyr initialized');
    }
});
