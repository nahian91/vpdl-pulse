/**
 * View Partial: Client Paid Invoices & Official Payment Receipts Scripts
 * File: assets/js/receipts.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlReceiptsModule');
        if (!moduleContainer) return;

        var searchInput  = document.getElementById('vpdlReceiptsSearch');
        var countDisplay = document.getElementById('vpdlReceiptsCountDisplay');
        var table        = document.getElementById('vpdlReceiptsTable');
        var modal        = document.getElementById('vpdlReceiptModal');

        // Live Search Filter
        if (searchInput && table) {
            var rows = table.querySelectorAll('tbody tr.vpdl-receipts-row');
            var totalCount = rows.length;

            searchInput.addEventListener('input', function() {
                var term = searchInput.value.toLowerCase().trim();
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
                    countDisplay.textContent = term === '' ? 'Showing ' + totalCount + ' cleared receipts' : 'Showing ' + visible + ' of ' + totalCount + ' cleared receipts';
                }
            });
        }

        // Modal Handlers
        moduleContainer.querySelectorAll('[data-action="view-receipt-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var raw = btn.getAttribute('data-receipt');
                if (!raw) return;

                try {
                    var d = JSON.parse(raw);

                    document.getElementById('modalReceiptNum').textContent      = d.receipt_number || '—';
                    document.getElementById('modalInvoiceRef').textContent      = d.invoice_number || '—';
                    document.getElementById('modalClientName').textContent      = d.client_name || '—';
                    document.getElementById('modalClientAddress').textContent   = (d.site_address || '') + (d.postcode ? ' (' + d.postcode + ')' : '');
                    document.getElementById('modalClientContact').textContent   = 'Tel: ' + (d.client_phone || '—') + ' | ' + (d.client_email || '—');
                    document.getElementById('modalPaymentMethod').textContent   = d.payment_method || 'Bank Transfer';
                    document.getElementById('modalPaymentRef').textContent     = d.payment_reference || '—';
                    document.getElementById('modalPaidDate').textContent        = d.paid_date || '—';
                    document.getElementById('modalReceiptSubtotal').textContent = d.subtotal || '—';
                    document.getElementById('modalReceiptVat').textContent      = d.vat_amount || '—';
                    document.getElementById('modalReceiptTotal').textContent    = d.amount_paid || '—';

                    if (modal) {
                        modal.style.display = 'flex';
                    }
                } catch (e) {
                    console.error('Error loading receipt data', e);
                }
            });
        });

        var closeBtn = document.querySelector('[data-action="close-receipt-modal"]');
        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        var printBtn = document.querySelector('[data-action="print-receipt"]');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }
    });
})();