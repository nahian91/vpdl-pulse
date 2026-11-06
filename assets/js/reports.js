/**
 * View: VPDL Financial Performance Reports & Audit Statement Scripts
 * File: assets/js/reports.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlReportsModule');
        if (!moduleContainer) return;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-report-tab"]');
        var incomePane = document.getElementById('vpdlIncomeTabContent');
        var expensePane = document.getElementById('vpdlExpenseTabContent');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                if (tabKey === 'expense') {
                    if (incomePane) incomePane.classList.remove('is-active');
                    if (expensePane) expensePane.classList.add('is-active');
                } else {
                    if (expensePane) expensePane.classList.remove('is-active');
                    if (incomePane) incomePane.classList.add('is-active');
                }
            });
        });

        // Status Filter Select
        var statusSelect = moduleContainer.querySelector('[data-action="apply-status-filter"]');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                var url = new URL(window.location.href);
                url.searchParams.set('status_filter', statusSelect.value);
                url.searchParams.set('sub_tab', 'income');
                window.location.href = url.toString();
            });
        }

        // Print Trigger
        var printBtn = moduleContainer.querySelector('[data-action="print-report"]');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }
    });
})();