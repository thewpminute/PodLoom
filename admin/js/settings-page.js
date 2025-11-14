/**
 * PodLoom Settings Page JavaScript
 * General settings page functionality
 */

(function($) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // API Key Visibility Toggle
        const toggleButton = document.getElementById('toggle_api_key_visibility');
        if (toggleButton) {
            const apiKeyInput = document.getElementById('podloom_api_key');
            const icon = toggleButton.querySelector('.dashicons');

            toggleButton.addEventListener('click', function() {
                if (apiKeyInput.type === 'password') {
                    apiKeyInput.type = 'text';
                    icon.classList.remove('dashicons-visibility');
                    icon.classList.add('dashicons-hidden');
                } else {
                    apiKeyInput.type = 'password';
                    icon.classList.remove('dashicons-hidden');
                    icon.classList.add('dashicons-visibility');
                }
            });
        }

        // Danger Zone Toggle
        const dangerZoneToggle = document.getElementById('danger-zone-toggle');
        if (dangerZoneToggle) {
            const dangerZoneContent = document.getElementById('danger-zone-content');
            const arrow = dangerZoneToggle.querySelector('.danger-zone-arrow');

            dangerZoneToggle.addEventListener('click', function() {
                if (dangerZoneContent.style.display === 'none') {
                    dangerZoneContent.style.display = 'block';
                    arrow.classList.add('rotated');
                } else {
                    dangerZoneContent.style.display = 'none';
                    arrow.classList.remove('rotated');
                }
            });
        }

        // Reset confirmation
        window.confirmReset = function() {
            return confirm('Are you sure you want to reset all plugin settings? This action cannot be undone.');
        };
    });

})(jQuery);
