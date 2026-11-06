/**
 * View: VPDL Team, Role Switcher & Access Governance Scripts
 * File: assets/js/team.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlTeamModule');
        if (!moduleContainer) return;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneDirectory = document.getElementById('vpdlTeamTabPaneDirectory');
        var paneCreate    = document.getElementById('vpdlTeamTabPaneCreate');
        var paneMatrix    = document.getElementById('vpdlTeamTabPaneMatrix');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                if (paneDirectory) paneDirectory.classList.remove('is-active');
                if (paneCreate) paneCreate.classList.remove('is-active');
                if (paneMatrix) paneMatrix.classList.remove('is-active');

                if (tabKey === 'create') {
                    if (paneCreate) paneCreate.classList.add('is-active');
                } else if (tabKey === 'matrix') {
                    if (paneMatrix) paneMatrix.classList.add('is-active');
                } else {
                    if (paneDirectory) paneDirectory.classList.add('is-active');
                }
            });
        });

        // Live Search Filter
        var searchInput = document.getElementById('vpdlTeamSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var filter = searchInput.value.toLowerCase().trim();
                var rows   = moduleContainer.querySelectorAll('.vpdl-team-row');

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
                });
            });
        }

        // Auto Submit Role Dropdown
        moduleContainer.querySelectorAll('select[data-action="auto-submit"]').forEach(function(sel) {
            sel.addEventListener('change', function() {
                if (sel.form) sel.form.submit();
            });
        });

        // Role Radio Card Selectors (Edit Mode)
        moduleContainer.querySelectorAll('input[data-action="select-role"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                var key = radio.getAttribute('data-key');
                moduleContainer.querySelectorAll('.vpdl-role-radio-card').forEach(function(c) {
                    c.classList.remove('selected');
                });
                var label = document.getElementById('label_role_' + key);
                if (label) label.classList.add('selected');
            });
        });

        // Role Radio Card Selectors (Create Mode)
        moduleContainer.querySelectorAll('input[data-action="select-create-role"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                var key = radio.getAttribute('data-key');
                moduleContainer.querySelectorAll('.vpdl-role-radio-card').forEach(function(c) {
                    c.classList.remove('selected');
                });
                var label = document.getElementById('create_role_' + key);
                if (label) label.classList.add('selected');
            });
        });

        // Delete Confirmations
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"], form[onsubmit*="confirm"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!window.confirm('Revoke access and permanently delete this account?')) {
                    e.preventDefault();
                }
            });
        });
    });
})();