/**
 * View Partial: Purchase Orders & Vendor Supply Chain Management Scripts
 * File: assets/js/purchase-orders.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlPoModule');
        if (!moduleContainer) return;

        var vatRate  = (typeof vpdlConfig !== 'undefined' && vpdlConfig.vat_rate) ? parseFloat(vpdlConfig.vat_rate) : 20.00;
        var currency = (typeof vpdlConfig !== 'undefined' && vpdlConfig.currency) ? vpdlConfig.currency : '£';
        var itemIndex = 1;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneList   = document.getElementById('vpdlPoTabPaneList');
        var paneAdd    = document.getElementById('vpdlPoTabPaneAdd');

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

        // Calculation Engine
        function calculatePoTotals() {
            var subtotal = 0;
            var rows = moduleContainer.querySelectorAll('.vpdl-po-item-row');

            rows.forEach(function(row) {
                var qtyInput   = row.querySelector('.vpdl-calc-qty');
                var priceInput = row.querySelector('.vpdl-calc-price');
                var totalCell  = row.querySelector('.vpdl-po-item-total');
                var hiddenLine = row.querySelector('.vpdl-hidden-line-total');

                var qty   = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
                var price = parseFloat(priceInput ? priceInput.value : 0) || 0;
                var lineTotal = qty * price;

                subtotal += lineTotal;

                if (totalCell) {
                    totalCell.textContent = currency + lineTotal.toFixed(2);
                }
                if (hiddenLine) {
                    hiddenLine.value = lineTotal.toFixed(2);
                }
            });

            var vatAmount  = (subtotal * vatRate) / 100;
            var grandTotal = subtotal + vatAmount;

            var dispSub = document.getElementById('poDisplaySubtotal');
            var dispVat = document.getElementById('poDisplayVat');
            var dispGrd = document.getElementById('poDisplayGrand');
            var inptSub = document.getElementById('poInputSubtotal');

            if (dispSub) dispSub.textContent = currency + subtotal.toFixed(2);
            if (dispVat) dispVat.textContent = currency + vatAmount.toFixed(2);
            if (dispGrd) dispGrd.textContent = currency + grandTotal.toFixed(2);
            if (inptSub) inptSub.value       = subtotal.toFixed(2);
        }

        // Add Line Item Row
        var btnAddRow = document.getElementById('vpdlBtnAddPoRow');
        var container = document.getElementById('vpdlPoItemsContainer');

        if (btnAddRow && container) {
            btnAddRow.addEventListener('click', function() {
                var row = document.createElement('div');
                row.className = 'vpdl-po-item-row';
                row.setAttribute('data-index', itemIndex);

                // Description Input
                var descInput = document.createElement('input');
                descInput.type = 'text';
                descInput.name = 'items[' + itemIndex + '][description]';
                descInput.placeholder = 'Item / Material Description';
                descInput.required = true;
                descInput.className = 'vpdl-po-input';

                // Qty Input
                var qtyInput = document.createElement('number');
                qtyInput.type = 'number';
                qtyInput.step = '1';
                qtyInput.min = '1';
                qtyInput.name = 'items[' + itemIndex + '][quantity]';
                qtyInput.value = '1';
                qtyInput.required = true;
                qtyInput.className = 'vpdl-po-input vpdl-calc-qty';

                // Price Input
                var priceInput = document.createElement('input');
                priceInput.type = 'number';
                priceInput.step = '0.01';
                priceInput.min = '0';
                priceInput.name = 'items[' + itemIndex + '][unit_price]';
                priceInput.value = '0.00';
                priceInput.required = true;
                priceInput.className = 'vpdl-po-input vpdl-calc-price';

                // Total Display
                var totalDiv = document.createElement('div');
                totalDiv.className = 'vpdl-po-item-total';
                totalDiv.textContent = currency + '0.00';

                // Hidden Total Input
                var hiddenTotal = document.createElement('input');
                hiddenTotal.type = 'hidden';
                hiddenTotal.name = 'items[' + itemIndex + '][total]';
                hiddenTotal.className = 'vpdl-hidden-line-total';
                hiddenTotal.value = '0.00';

                // Remove Button
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'vpdl-btn-remove-row';
                removeBtn.setAttribute('data-action', 'remove-row');
                removeBtn.title = 'Remove Row';
                removeBtn.textContent = '×';

                row.appendChild(descInput);
                row.appendChild(qtyInput);
                row.appendChild(priceInput);
                row.appendChild(totalDiv);
                row.appendChild(hiddenTotal);
                row.appendChild(removeBtn);

                container.appendChild(row);
                itemIndex++;

                calculatePoTotals();
            });
        }

        // Calculation & Row Event Delegation
        if (container) {
            container.addEventListener('input', function(e) {
                if (e.target.classList.contains('vpdl-calc-qty') || e.target.classList.contains('vpdl-calc-price')) {
                    calculatePoTotals();
                }
            });

            container.addEventListener('click', function(e) {
                if (e.target && e.target.getAttribute('data-action') === 'remove-row') {
                    var row = e.target.closest('.vpdl-po-item-row');
                    if (row && container.querySelectorAll('.vpdl-po-item-row').length > 1) {
                        row.remove();
                        calculatePoTotals();
                    }
                }
            });
        }

        // Live Table Search
        var searchInput  = document.getElementById('vpdlPoSearch');
        var countDisplay = document.getElementById('vpdlPoCountDisplay');
        var table        = document.getElementById('vpdlPoTable');

        if (searchInput && table) {
            var rows = table.querySelectorAll('tbody tr.vpdl-po-row');
            var totalCount = rows.length;

            searchInput.addEventListener('input', function() {
                var term = searchInput.value.toLowerCase().trim();
                var visible = 0;

                rows.forEach(function(r) {
                    var text = r.textContent.toLowerCase();
                    if (text.indexOf(term) > -1) {
                        r.style.display = '';
                        visible++;
                    } else {
                        r.style.display = 'none';
                    }
                });

                if (countDisplay) {
                    countDisplay.textContent = term === '' ? 'Showing ' + totalCount + ' orders' : 'Showing ' + visible + ' of ' + totalCount + ' orders';
                }
            });
        }

        // Modal Handler
        var modal = document.getElementById('vpdlPoViewModal');

        moduleContainer.querySelectorAll('[data-action="view-po-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var raw = btn.getAttribute('data-po');
                if (!raw) return;

                try {
                    var d = JSON.parse(raw);

                    document.getElementById('modalPoNum').textContent            = d.po_number || '—';
                    document.getElementById('modalPoVendorName').textContent     = d.supplier_name || '—';
                    document.getElementById('modalPoVendorPhone').textContent    = d.supplier_phone || '—';
                    document.getElementById('modalPoVendorCategory').textContent = d.supplier_category || '—';
                    document.getElementById('modalPoProject').textContent        = d.related_project || '—';
                    document.getElementById('modalPoDelivery').textContent       = d.expected_delivery || '—';
                    document.getElementById('modalPoStatus').textContent         = d.status || '—';
                    document.getElementById('modalPoSubtotal').textContent       = d.subtotal || '—';
                    document.getElementById('modalPoVat').textContent            = d.vat_amount || '—';
                    document.getElementById('modalPoGrand').textContent          = d.grand_total || '—';
                    document.getElementById('modalPoNotes').textContent          = d.notes || 'None listed.';

                    // Re-render Line Items
                    var tbody = document.getElementById('modalPoItemsBody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        if (Array.isArray(d.items) && d.items.length > 0) {
                            d.items.forEach(function(item) {
                                var tr = document.createElement('tr');
                                tr.style.borderBottom = '1px solid #f1f5f9';

                                var tdDesc = document.createElement('td');
                                tdDesc.style.padding = '8px 12px';
                                tdDesc.textContent = item.description || item.desc || '—';

                                var tdQty = document.createElement('td');
                                tdQty.style.padding = '8px 12px';
                                tdQty.style.textAlign = 'center';
                                tdQty.textContent = item.quantity || item.qty || '1';

                                var tdPrice = document.createElement('td');
                                tdPrice.style.padding = '8px 12px';
                                tdPrice.style.textAlign = 'right';
                                tdPrice.style.fontFamily = 'monospace';
                                var priceVal = parseFloat(item.unit_price || item.price || 0);
                                tdPrice.textContent = currency + priceVal.toFixed(2);

                                var tdTotal = document.createElement('td');
                                tdTotal.style.padding = '8px 12px';
                                tdTotal.style.textAlign = 'right';
                                tdTotal.style.fontFamily = 'monospace';
                                tdTotal.style.fontWeight = '700';
                                var totVal = parseFloat(item.total || (priceVal * (parseFloat(item.quantity || item.qty) || 1)));
                                tdTotal.textContent = currency + totVal.toFixed(2);

                                tr.appendChild(tdDesc);
                                tr.appendChild(tdQty);
                                tr.appendChild(tdPrice);
                                tr.appendChild(tdTotal);
                                tbody.appendChild(tr);
                            });
                        }
                    }

                    if (modal) {
                        modal.style.display = 'flex';
                    }
                } catch(err) {
                    console.error('Error parsing PO modal data', err);
                }
            });
        });

        var closeBtn = document.querySelector('[data-action="close-po-modal"]');
        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        var printBtn = document.querySelector('[data-action="print-po-modal"]');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                var printContent = document.getElementById('vpdlPoPrintArea');
                if (!printContent) return;

                var printWindow = window.open('', '_blank', 'height=650,width=800');
                if (!printWindow) return;

                printWindow.document.open();
                printWindow.document.write('<!DOCTYPE html><html><head><title>Purchase Order</title>');
                printWindow.document.write('<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:30px;color:#0f172a;} table{width:100%;border-collapse:collapse;} td,th{padding:8px;border-bottom:1px solid #ddd;}</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write('<div style="border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:18px;">');
                printWindow.document.write('<h2 style="margin:0;">Purchase Order Requisition</h2>');
                printWindow.document.write('<div style="font-size:11px;color:#555;">Date: ' + new Date().toLocaleDateString('en-GB') + '</div>');
                printWindow.document.write('</div>');
                printWindow.document.write(printContent.innerHTML);
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