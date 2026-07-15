/**
 * Vin Eyewear - Contact Page JavaScript
 * Handles interactive store location map switching with keyboard accessibility
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var storeCards = document.querySelectorAll('.store-card');
    var mapContainers = document.querySelectorAll('.map-container');

    if (!storeCards.length || !mapContainers.length) {
        return;
    }

    /**
     * Activate the selected store and display its respective map iframe.
     * @param {string} storeId The ID of the store (e.g. 'long-bien', 'tay-ho')
     */
    function activateStore(storeId) {
        // 1. Remove active state from all store cards
        storeCards.forEach(function(card) {
            card.classList.remove('active');
        });

        // 2. Remove active state from all map containers
        mapContainers.forEach(function(map) {
            map.classList.remove('active');
        });

        // 3. Add active state to the current store card
        var targetCard = document.querySelector('.store-card[data-store-id="' + storeId + '"]');
        if (targetCard) {
            targetCard.classList.add('active');
        }

        // 4. Add active state to the corresponding map viewport container
        var targetMap = document.getElementById('map-' + storeId);
        if (targetMap) {
            targetMap.classList.add('active');
        }
    }

    // Bind event listeners to each store card
    storeCards.forEach(function(card) {
        var storeId = card.getAttribute('data-store-id');
        if (!storeId) return;

        // Mouse click activation
        card.addEventListener('click', function() {
            activateStore(storeId);
        });

        // Accessibility activation via Enter or Space when focused
        card.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault(); // Prevent browser scrolling on Spacebar keypress
                activateStore(storeId);
            }
        });
    });
});
