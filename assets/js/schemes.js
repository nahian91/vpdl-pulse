/**
 * View: Construction Schemes (Quotation Generator & Area-Based Calculator) Scripts
 * File: assets/js/schemes.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlSchemesModule');
        if (!moduleContainer) return;

        var vatRate = (typeof vpdlConfig !== 'undefined' && vpdlConfig.vat_rate) ? parseFloat(vpdlConfig.vat_rate) : 20.00;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var panes = {
            builder:        document.getElementById('vpdlSchemeTabPaneBuilder'),
            registry:       document.getElementById('vpdlSchemeTabPaneRegistry'),
            schemes_tariff: document.getElementById('vpdlSchemeTabPaneTariff')
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

        // Live Quotation Calculator Engine
        function vpdlCalcQuote() {
            var sqmInput = document.getElementById('vpdl_sqm');
            var sqm = parseFloat(sqmInput ? sqmInput.value : 0) || 0;

            var schemeRadio = moduleContainer.querySelector('input[name="scheme_select"]:checked');
            var schemeRate  = schemeRadio ? (parseFloat(schemeRadio.value) || 0) : 0;
            var schemeName  = schemeRadio ? (schemeRadio.getAttribute('data-name') || 'Scheme Construction') : 'Scheme Construction';
            var schemeTotal = sqm * schemeRate;

            var extraTotal      = 0;
            var itemsContainer  = document.getElementById('vpdlHiddenItemsContainer');
            if (!itemsContainer) return;

            itemsContainer.innerHTML = '';

            if (schemeTotal > 0) {
                var descVal = schemeName + ' (' + sqm + ' sq.m @ £' + schemeRate.toFixed(2) + '/sq.m)';
                itemsContainer.innerHTML += '<input type="hidden" name="items[0][description]" value="' + descVal.replace(/"/g, '&quot;') + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[0][quantity]" value="' + sqm + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[0][unit_price]" value="' + schemeRate.toFixed(2) + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[0][total]" value="' + schemeTotal.toFixed(2) + '">';
            }

            var checkboxes = moduleContainer.querySelectorAll('input[name="extra_services[]"]:checked');
            var itemIndex  = 1;

            checkboxes.forEach(function(cb) {
                var fee  = parseFloat(cb.value) || 0;
                var code = cb.getAttribute('data-code') || '';
                var desc = cb.getAttribute('data-desc') || '';
                var cat  = cb.getAttribute('data-category') || '';
                extraTotal += fee;

                var fullDesc = code + ' - ' + desc;
                itemsContainer.innerHTML += '<input type="hidden" name="items[' + itemIndex + '][description]" value="' + fullDesc.replace(/"/g, '&quot;') + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[' + itemIndex + '][category]" value="' + cat.replace(/"/g, '&quot;') + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[' + itemIndex + '][quantity]" value="1">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[' + itemIndex + '][unit_price]" value="' + fee.toFixed(2) + '">';
                itemsContainer.innerHTML += '<input type="hidden" name="items[' + itemIndex + '][total]" value="' + fee.toFixed(2) + '">';
                itemIndex++;
            });

            var subtotal = schemeTotal + extraTotal;
            var vat      = (subtotal * vatRate) / 100;
            var grand    = subtotal + vat;

            var dispScheme = document.getElementById('dispSchemeCost');
            var dispExtra  = document.getElementById('dispExtraCost');
            var dispSub    = document.getElementById('dispSubtotal');
            var dispVat    = document.getElementById('dispVat');
            var dispGrand  = document.getElementById('dispGrandTotal');
            var inputSub   = document.getElementById('input_subtotal');

            if (dispScheme) dispScheme.textContent = schemeTotal.toFixed(2);
            if (dispExtra)  dispExtra.textContent  = extraTotal.toFixed(2);
            if (dispSub)    dispSub.textContent    = subtotal.toFixed(2);
            if (dispVat)    dispVat.textContent    = vat.toFixed(2);
            if (dispGrand)  dispGrand.textContent  = grand.toFixed(2);
            if (inputSub)   inputSub.value         = subtotal.toFixed(2);
        }

        // Calculation Event Listeners
        var sqmInput = document.getElementById('vpdl_sqm');
        if (sqmInput) {
            sqmInput.addEventListener('input', vpdlCalcQuote);
        }

        moduleContainer.querySelectorAll('input[name="scheme_select"]').forEach(function(radio) {
            radio.addEventListener('change', vpdlCalcQuote);
        });

        moduleContainer.querySelectorAll('input[name="extra_services[]"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var card = cb.closest('.vpdl-service-card');
                if (card) {
                    if (cb.checked) {
                        card.classList.add('is-selected');
                    } else {
                        card.classList.remove('is-selected');
                    }
                }
                vpdlCalcQuote();
            });
        });

        // Filter Additional Services
        var srvSearch = document.getElementById('vpdlServiceSearch');
        if (srvSearch) {
            srvSearch.addEventListener('input', function() {
                var query = srvSearch.value.toLowerCase().trim();
                var cards = moduleContainer.querySelectorAll('.vpdl-service-card');
                cards.forEach(function(card) {
                    var text = card.textContent.toLowerCase();
                    if (text.indexOf(query) > -1) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Registry Table Search
        var regSearch = document.getElementById('vpdlSchemeSearch');
        if (regSearch) {
            regSearch.addEventListener('input', function() {
                var filter = regSearch.value.toLowerCase().trim();
                var rows   = moduleContainer.querySelectorAll('.vpdl-schemes-row');
                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    if (text.indexOf(filter) > -1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Deletion Confirmations
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var type = form.getAttribute('data-type') || 'item';
                if (!window.confirm('Permanently delete this ' + type + '?')) {
                    e.preventDefault();
                }
            });
        });

        // Convert Confirmation
        moduleContainer.querySelectorAll('a[data-action="confirm-convert"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!window.confirm('Convert this proposal into an official tax invoice?')) {
                    e.preventDefault();
                }
            });
        });

        // Print Trigger
        moduleContainer.querySelectorAll('[data-action="print-window"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                window.print();
            });
        });

        // Initialize Calculation on Load
        vpdlCalcQuote();
    });
})();