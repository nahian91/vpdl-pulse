/**
 * View: VPDL Accounts, Financial Ledger & VAT Reconciliation Scripts
 * File: assets/js/accounts.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlAccountsModule');
        if (!moduleContainer) return;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneOverview = document.getElementById('vpdlAccTabPaneOverview');
        var paneOutflows = document.getElementById('vpdlAccTabPaneOutflows');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                if (tabKey === 'outflows') {
                    if (paneOverview) paneOverview.classList.remove('is-active');
                    if (paneOutflows) paneOutflows.classList.add('is-active');
                } else {
                    if (paneOutflows) paneOutflows.classList.remove('is-active');
                    if (paneOverview) paneOverview.classList.add('is-active');
                }
            });
        });

        // Toggle Expense Ingestion Box
        moduleContainer.querySelectorAll('[data-action="toggle-expense-box"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var box = document.getElementById('vpdlEntryBox');
                if (box) {
                    box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
                    if (box.style.display === 'block') {
                        box.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });

        // Live VAT Estimator
        var amountInput = document.getElementById('vpdl_entry_amount');
        if (amountInput) {
            amountInput.addEventListener('input', function() {
                var amt = parseFloat(amountInput.value) || 0;
                var vatField = document.getElementById('vpdl_entry_vat');
                if (vatField && vatField.value === '') {
                    var vatRate = (typeof vpdlConfig !== 'undefined' && vpdlConfig.vat_rate) ? parseFloat(vpdlConfig.vat_rate) : 20.00;
                    var estVat = (amt * (vatRate / (100 + vatRate)));
                    vatField.placeholder = estVat.toFixed(2);
                }
            });
        }

        // Delete Confirmations
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!window.confirm('Permanently remove this ledger record?')) {
                    e.preventDefault();
                }
            });
        });
    });
})();