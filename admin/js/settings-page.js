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
                    toggleButton.setAttribute('aria-label', toggleButton.getAttribute('aria-label').replace('Toggle', 'Hide'));
                } else {
                    apiKeyInput.type = 'password';
                    icon.classList.remove('dashicons-hidden');
                    icon.classList.add('dashicons-visibility');
                    toggleButton.setAttribute('aria-label', toggleButton.getAttribute('aria-label').replace('Hide', 'Toggle'));
                }
            });
        }

        // Generic Accordion Toggle Handler with ARIA support
        function initAccordion(toggleId, contentId, arrowSelector) {
            const toggle = document.getElementById(toggleId);
            if (toggle) {
                const content = document.getElementById(contentId);
                const arrow = toggle.querySelector(arrowSelector);

                toggle.addEventListener('click', function() {
                    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

                    if (isExpanded) {
                        // Collapse
                        content.style.display = 'none';
                        content.setAttribute('aria-hidden', 'true');
                        toggle.setAttribute('aria-expanded', 'false');
                        arrow.classList.remove('rotated');
                    } else {
                        // Expand
                        content.style.display = 'block';
                        content.setAttribute('aria-hidden', 'false');
                        toggle.setAttribute('aria-expanded', 'true');
                        arrow.classList.add('rotated');
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
