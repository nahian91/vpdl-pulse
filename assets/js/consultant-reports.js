/**
 * View Partial: Consultant Job, Fee & Performance Reports Scripts
 * File: assets/js/consultant-reports.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlConsultantReportsModule');
        if (!moduleContainer) return;

        // Auto Filter Dropdowns
        moduleContainer.querySelectorAll('select[data-action="auto-filter"]').forEach(function(select) {
            select.addEventListener('change', function() {
                if (select.form) {
                    select.form.submit();
                }
            });
        });

        // Print Report Handler
        var printBtn = moduleContainer.querySelector('[data-action="print-report"]');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }

        // Live Client-Side Search Filter
        var searchInput  = document.getElementById('vpdlConsultantSearch');
        var countDisplay = document.getElementById('vpdlConsultantCountDisplay');
        var table        = document.getElementById('vpdlConsultantTable');

        if (searchInput && table) {
            var rows       = table.querySelectorAll('tbody tr.vpdl-reports-row');
            var totalCount = rows.length;

            searchInput.addEventListener('input', function() {
                var term    = searchInput.value.toLowerCase().trim();
                var visible = 0;

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    if (text.indexOf(term) > -1) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (countDisplay) {
                    if (term === '') {
                        countDisplay.textContent = 'Showing ' + totalCount + ' assigned jobs';
                    } else {
                        countDisplay.textContent = 'Showing ' + visible + ' of ' + totalCount + ' assigned jobs';
                    }
                }
            });
        }
    });
})();