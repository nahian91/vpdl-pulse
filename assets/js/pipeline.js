/**
 * View: VPDL Practice Operating Pipeline Scripts
 * File: assets/js/pipeline.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('vpdlPipelineSearch');
        var stageSelect = document.getElementById('vpdlStageSelect');
        var modal       = document.getElementById('vpdlSingleDossierModal');

        // Search & Filter Logic
        function filterPipeline() {
            var search      = searchInput ? searchInput.value.toLowerCase() : '';
            var stageKey    = stageSelect ? stageSelect.value : 'all';
            var rows        = document.querySelectorAll('#vpdlPipelineTable tbody tr.vpdl-pipe-row');
            var visibleRows = 0;

            rows.forEach(function(row) {
                var text         = row.textContent.toLowerCase();
                var rowStage     = row.getAttribute('data-stage') || '';
                var matchesText  = text.indexOf(search) > -1;
                var matchesStage = (stageKey === 'all') || (rowStage === stageKey);

                if (matchesText && matchesStage) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            var countEl = document.getElementById('vpdlFilteredCount');
            if (countEl) {
                countEl.textContent = visibleRows;
            }

            var labelEl = document.getElementById('vpdlCurrentFilterLabel');
            if (labelEl && stageSelect) {
                labelEl.textContent = stageSelect.options[stageSelect.selectedIndex].text;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterPipeline);
        }

        if (stageSelect) {
            stageSelect.addEventListener('change', filterPipeline);
        }

        // Interactive Stage Cards
        document.querySelectorAll('.vpdl-stage-card[data-action="select-stage"]').forEach(function(card) {
            card.addEventListener('click', function() {
                var stage = card.getAttribute('data-stage');
                if (stageSelect) {
                    stageSelect.value = stage;
                }

                document.querySelectorAll('.vpdl-stage-card').forEach(function(c) {
                    c.classList.remove('is-active');
                });
                card.classList.add('is-active');

                filterPipeline();
            });
        });

        // Reset Filter Button
        var resetBtn = document.querySelector('[data-action="reset-filter"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                if (stageSelect) {
                    stageSelect.value = 'all';
                }
                if (searchInput) {
                    searchInput.value = '';
                }
                document.querySelectorAll('.vpdl-stage-card').forEach(function(c) {
                    c.classList.remove('is-active');
                });
                filterPipeline();
            });
        }

        // Modal Handlers
        document.querySelectorAll('[data-action="open-dossier"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var rawData = btn.getAttribute('data-dossier');
                if (!rawData) return;

                try {
                    var d = JSON.parse(rawData);
                    document.getElementById('dossierClientName').textContent  = d.name || '—';
                    document.getElementById('dossierPhone').textContent      = d.phone || '—';
                    document.getElementById('dossierEmail').textContent      = d.email || 'No email registered';
                    document.getElementById('dossierSiteAddress').textContent = d.site_address || 'Address on file';
                    document.getElementById('dossierPostcode').textContent    = d.postcode || '—';
                    document.getElementById('dossierCouncil').textContent     = d.council_name ? 'LPA: ' + d.council_name : '';
                    document.getElementById('dossierService').textContent     = d.service_cat;
                    document.getElementById('dossierQuoteNum').textContent    = d.quote_number;
                    document.getElementById('dossierQuoteAmt').textContent    = d.quote_total;
                    document.getElementById('dossierInvoiceNum').textContent  = d.invoice_number;
                    document.getElementById('dossierFinalAmt').textContent    = d.invoice_total !== '—' ? d.invoice_total : d.quote_total;
                    document.getElementById('dossierStage').textContent       = d.stage_name;
                    document.getElementById('dossierDate').textContent        = 'Generated: ' + new Date().toLocaleDateString('en-GB');

                    if (modal) {
                        modal.style.display = 'flex';
                    }
                } catch(e) {
                    console.error('Failed to parse dossier payload', e);
                }
            });
        });

        var closeModalBtn = document.querySelector('[data-action="close-modal"]');
        if (closeModalBtn && modal) {
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        // Master Page Print
        var printMasterBtn = document.querySelector('[data-action="print-window"]');
        if (printMasterBtn) {
            printMasterBtn.addEventListener('click', function() {
                window.print();
            });
        }

        // Individual Dossier Print Window
        var printDossierBtn = document.querySelector('[data-action="print-dossier"]');
        if (printDossierBtn) {
            printDossierBtn.addEventListener('click', function() {
                var printArea = document.getElementById('dossierPrintArea');
                if (!printArea) return;

                var printWindow = window.open('', '_blank', 'height=650,width=800');
                if (!printWindow) return;

                printWindow.document.open();
                printWindow.document.write('<!DOCTYPE html><html><head><title>Practice Case Dossier</title>');
                printWindow.document.write('<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:30px;color:#0f172a;} table{width:100%;border-collapse:collapse;} td{padding:6px;}</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write('<div style="border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:18px;">');
                printWindow.document.write('<h2 style="margin:0;">Practice Case Dossier</h2>');
                printWindow.document.write('<div style="font-size:11px;color:#555;">Date: ' + new Date().toLocaleDateString('en-GB') + '</div>');
                printWindow.document.write('</div>');
                printWindow.document.write(printArea.innerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();

                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            });
        }
    });
})();