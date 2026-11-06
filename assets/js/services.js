/**
 * View: VPDL Enterprise Service Rates & Fee Catalog Manager Scripts
 * File: assets/js/services.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlServicesModule');
        if (!moduleContainer) return;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var panes = {
            pdm: document.getElementById('vpdlServiceTabPanePDM'),
            sdm: document.getElementById('vpdlServiceTabPaneSDM'),
            pmc: document.getElementById('vpdlServiceTabPanePMC'),
            bsm: document.getElementById('vpdlServiceTabPaneBSM')
        };

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                Object.keys(panes).forEach(function(key) {
                    if (panes[key]) {
                        if (key === tabKey) {
                            panes[key].classList.add('is-active');
                        } else {
                            panes[key].classList.remove('is-active');
                        }
                    }
                });
            });
        });

        // Filter Tariff Tables
        moduleContainer.querySelectorAll('[data-action="filter-tariff"]').forEach(function(input) {
            input.addEventListener('input', function() {
                var tabKey = input.getAttribute('data-tab');
                var filter = input.value.toLowerCase().trim();
                var pane   = document.getElementById('vpdlServiceTabPane' + tabKey.toUpperCase());
                if (!pane) return;

                var rows = pane.querySelectorAll('.vpdl-tariff-item-row');
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
                });
            });
        });

        // Modal Handlers
        var modal = document.getElementById('vpdlEditServiceModal');

        moduleContainer.querySelectorAll('[data-action="open-edit-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id   = btn.getAttribute('data-id');
                var code = btn.getAttribute('data-code');
                var desc = btn.getAttribute('data-desc');
                var fee  = btn.getAttribute('data-fee');
                var cat  = btn.getAttribute('data-cat');

                var idEl   = document.getElementById('modal_service_id');
                var codeEl = document.getElementById('modal_service_code');
                var descEl = document.getElementById('modal_service_desc');
                var feeEl  = document.getElementById('modal_service_fee');
                var catEl  = document.getElementById('modal_service_cat');

                if (idEl) idEl.value        = id || '';
                if (codeEl) codeEl.textContent = code || '';
                if (descEl) descEl.value      = desc || '';
                if (feeEl) feeEl.value        = parseFloat(fee || 0).toFixed(2);
                if (catEl) catEl.value        = cat || 'PDM';

                if (modal) modal.style.display = 'flex';
            });
        });

        moduleContainer.querySelectorAll('[data-action="close-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (modal) modal.style.display = 'none';
            });
        });

        // CSV Export
        var exportBtn = moduleContainer.querySelector('[data-action="export-csv"]');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                var activePane = moduleContainer.querySelector('.vpdl-srv-tab-pane.is-active');
                if (!activePane) return;

                var table = activePane.querySelector('table');
                if (!table) return;

                var csv = [];
                var rows = table.querySelectorAll('tr');

                rows.forEach(function(row) {
                    var cols = row.querySelectorAll('td, th');
                    var data = [];
                    cols.forEach(function(col, idx) {
                        if (idx < 3) { // Exclude actions column
                            data.push('"' + col.textContent.replace(/"/g, '""').trim() + '"');
                        }
                    });
                    csv.push(data.join(','));
                });

                var csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
                var downloadLink = document.createElement('a');
                downloadLink.download = 'vpdl-tariff-catalog.csv';
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = 'none';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            });
        }

        // Delete Confirmations
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var type = form.getAttribute('data-type') || 'item';
                if (!window.confirm('Permanently remove this ' + type + '?')) {
                    e.preventDefault();
                }
            });
        });
    });
})();