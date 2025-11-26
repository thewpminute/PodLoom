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

        // Generic Accordion Toggle Handler
        function initAccordion(toggleId, contentId, arrowSelector) {
            const toggle = document.getElementById(toggleId);
            if (toggle) {
                const content = document.getElementById(contentId);
                const arrow = toggle.querySelector(arrowSelector);

                toggle.addEventListener('click', function() {
                    if (content.style.display === 'none') {
                        content.style.display = 'block';
                        arrow.classList.add('rotated');
                    } else {
                        content.style.display = 'none';
                        arrow.classList.remove('rotated');
                    }
                });
            }
        }

        // Danger Zone Toggle
        initAccordion('danger-zone-toggle', 'danger-zone-content', '.danger-zone-arrow');

        // Typography Accordion Toggle
        initAccordion('typography-accordion-toggle', 'typography-accordion-content', '.podloom-accordion-arrow');

        // Reset confirmation
        window.confirmReset = function() {
            return confirm('Are you sure you want to reset all plugin settings? This action cannot be undone.');
        };
    });

})(jQuery);
