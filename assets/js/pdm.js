/**
 * View: Planning Design Management (PDM) Service Catalog & Booking Engine Scripts
 * File: assets/js/pdm.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlPdmModule');
        if (!moduleContainer) return;

        var vatRate = (typeof vpdlConfig !== 'undefined' && vpdlConfig.vat_rate) ? parseFloat(vpdlConfig.vat_rate) : 20.00;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var panes = {
            catalog: document.getElementById('vpdlPdmTabPaneCatalog'),
            history: document.getElementById('vpdlPdmTabPaneHistory'),
            tariff:  document.getElementById('vpdlPdmTabPaneTariff')
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

        // Live Fee Calculation
        var codeSelector = document.getElementById('vpdlPdmCodeSelector');
        if (codeSelector) {
            codeSelector.addEventListener('change', function() {
                var opt   = codeSelector.options[codeSelector.selectedIndex];
                var fee   = parseFloat(opt.getAttribute('data-fee')) || 0.00;
                var vat   = (fee * vatRate) / 100;
                var total = fee + vat;

                var dispNet   = document.getElementById('dispPdmNet');
                var dispVat   = document.getElementById('dispPdmVat');
                var dispTotal = document.getElementById('dispPdmTotal');

                if (dispNet) dispNet.textContent     = fee.toFixed(2);
                if (dispVat) dispVat.textContent     = vat.toFixed(2);
                if (dispTotal) dispTotal.textContent = total.toFixed(2);
            });
        }

        // Client Council Autofill
        var clientSelect = document.getElementById('vpdlPdmClientSelect');
        var councilField = document.getElementById('vpdlPdmCouncilField');
        if (clientSelect && councilField) {
            clientSelect.addEventListener('change', function() {
                var opt     = clientSelect.options[clientSelect.selectedIndex];
                var council = opt.getAttribute('data-council') || '';
                if (council) {
                    councilField.value = council;
                }
            });
        }

        // Modal Handlers
        var modal = document.getElementById('vpdlEditTariffModal');

        moduleContainer.querySelectorAll('[data-action="open-tariff-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id   = btn.getAttribute('data-id');
                var code = btn.getAttribute('data-code');
                var desc = btn.getAttribute('data-desc');
                var fee  = btn.getAttribute('data-fee');

                var idEl   = document.getElementById('modal_tariff_id');
                var codeEl = document.getElementById('modal_tariff_code');
                var descEl = document.getElementById('modal_tariff_desc');
                var feeEl  = document.getElementById('modal_tariff_fee');

                if (idEl) idEl.value        = id || '';
                if (codeEl) codeEl.textContent = code || '';
                if (descEl) descEl.value      = desc || '';
                if (feeEl) feeEl.value        = parseFloat(fee || 0).toFixed(2);

                if (modal) modal.style.display = 'flex';
            });
        });

        document.querySelectorAll('[data-action="close-tariff-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (modal) modal.style.display = 'none';
            });
        });

        // Delete Confirmations
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var itemType = form.getAttribute('data-type') || 'item';
                if (!window.confirm('Permanently remove this ' + itemType + '?')) {
                    e.preventDefault();
                }
            });
        });
    });
})();