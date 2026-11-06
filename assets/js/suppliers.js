/**
 * View Partial: Suppliers Directory & Vendor Management Scripts
 * File: assets/js/suppliers.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlSuppliersModule');
        if (!moduleContainer) {
            return;
        }

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneList   = document.getElementById('vpdlSuppTabPaneList');
        var paneAdd    = document.getElementById('vpdlSuppTabPaneAdd');

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
        var searchInput  = document.getElementById('vpdlSupplierSearch');
        var countDisplay = document.getElementById('vpdlSupplierCountDisplay');
        var table        = document.getElementById('vpdlSuppliersTable');

        if (searchInput && table) {
            var rows       = table.querySelectorAll('tbody tr.vpdl-supp-row');
            var totalCount = rows.length;

            searchInput.addEventListener('input', function() {
                var term         = searchInput.value.toLowerCase().trim();
                var visibleCount = 0;

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    if (text.indexOf(term) > -1) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (countDisplay) {
                    if (term === '') {
                        countDisplay.textContent = 'Showing ' + totalCount + ' vendors';
                    } else {
                        countDisplay.textContent = 'Showing ' + visibleCount + ' of ' + totalCount + ' vendors';
                    }
                }
            });
        }

        // Confirm Supplier Deletion
        var deleteForms = moduleContainer.querySelectorAll('form[data-action="delete-supplier"]');
        deleteForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var confirmMsg = 'Permanently delete this supplier record?';
                if (!window.confirm(confirmMsg)) {
                    e.preventDefault();
                }
            });
        });
    });
})();