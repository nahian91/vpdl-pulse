/**
 * View: VPDL Minimal Numeric Matrix Dashboard Scripts
 * File: assets/js/dashboard.js
 * @version 1.0.0
 */

(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        var app = document.getElementById('vpdlDashboardApp');
        if (!app) {
            return;
        }

        // Interactive tile keyboard navigation
        var tiles = app.querySelectorAll('.vpdl-matrix-tile');
        tiles.forEach(function(tile) {
            tile.addEventListener('keydown', function(e) {
                if (e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    tile.click();
                }
            });
        });
    });
})();