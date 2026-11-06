/**
 * View: VPDL Client Directory & Lead Intelligence Registry Scripts
 * File: assets/js/customers.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlCustomersModule');
        if (!moduleContainer) {
            return;
        }

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneList   = document.getElementById('vpdlCustTabPaneList');
        var paneAdd    = document.getElementById('vpdlCustTabPaneAdd');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                if (tabKey === 'add') {
                    if (paneList) paneList.classList.remove('is-active');
                    if (paneAdd) paneAdd.classList.add('is-active');
                } else {
                    if (paneAdd) paneAdd.classList.remove('is-active');
                    if (paneList) paneList.classList.add('is-active');
                }
            });
        });

        // Live Search Filter
        var searchInput = document.getElementById('vpdlCustomerSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var filter = searchInput.value.toLowerCase();
                var rows   = moduleContainer.querySelectorAll('.vpdl-cust-row');

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
                });
            });
        }

        // Copy Proposal Link via Clipboard API
        var copyButtons = moduleContainer.querySelectorAll('[data-action="copy-quote-link"]');
        copyButtons.forEach(function(copyBtn) {
            copyBtn.addEventListener('click', function() {
                var url = copyBtn.getAttribute('data-url');
                if (!url) return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function() {
                        var label = copyBtn.querySelector('span');
                        if (label) {
                            var prev = label.textContent;
                            label.textContent = 'Copied!';
                            setTimeout(function() {
                                label.textContent = prev;
                            }, 2000);
                        }
                    }).catch(function() {
                        window.prompt('Copy to clipboard: Ctrl+C, Enter', url);
                    });
                } else {
                    window.prompt('Copy to clipboard: Ctrl+C, Enter', url);
                }
            });
        });

        // Confirm Customer Deletion
        var deleteForms = moduleContainer.querySelectorAll('form[data-action="delete-customer"]');
        deleteForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var confirmMsg = 'Permanently remove this customer record and their dossier?';
                if (!window.confirm(confirmMsg)) {
                    e.preventDefault();
                }
            });
        });
    });
})();