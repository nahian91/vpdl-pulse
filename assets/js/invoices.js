/**
 * View: VPDL Official Invoicing Register & Billing Engine Scripts
 * File: assets/js/invoices.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlInvoicesModule');
        if (!moduleContainer) return;

        var vpdlInvoiceData = (typeof window.vpdlInvoiceData !== 'undefined') ? window.vpdlInvoiceData : [];
        var currencySymbol  = (typeof vpdlConfig !== 'undefined' && vpdlConfig.currency) ? vpdlConfig.currency : '£';

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneList   = document.getElementById('vpdlInvTabPaneList');
        var paneCreate = document.getElementById('vpdlInvTabPaneCreate');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                });
                btn.classList.add('is-active');

                if (tabKey === 'create') {
                    if (paneList) paneList.classList.remove('is-active');
                    if (paneCreate) paneCreate.classList.add('is-active');
                } else {
                    if (paneCreate) paneCreate.classList.remove('is-active');
                    if (paneList) paneList.classList.add('is-active');
                }
            });
        });

        // Select All Checkbox
        var selectAllCb = document.getElementById('vpdlSelectAllInv');
        if (selectAllCb) {
            selectAllCb.addEventListener('change', function() {
                var checkboxes = moduleContainer.querySelectorAll('.vpdl-inv-checkbox');
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAllCb.checked;
                });
            });
        }

        // Calculation Engine
        function recalcDirectInvoiceRows() {
            var priceInputs = moduleContainer.querySelectorAll('.vpdl-item-price-input');
            var net = 0.00;
            priceInputs.forEach(function(inp) {
                var val = parseFloat(inp.value) || 0.00;
                net += val;
                var row = inp.closest('.vpdl-inv-item-row');
                if (row) {
                    var lineTotal = row.querySelector('.vpdl-hidden-line-total');
                    if (lineTotal) lineTotal.value = val.toFixed(2);
                }
            });

            var vatSelector = document.getElementById('vpdlVatTreatment');
            var vatRate = vatSelector ? parseFloat(vatSelector.value) : 20.00;
            var vat = (net * vatRate) / 100;
            var gross = net + vat;

            var dispNet   = document.getElementById('dispDirectNet');
            var dispVat   = document.getElementById('dispDirectVat');
            var dispGross = document.getElementById('dispDirectGross');

            if (dispNet) dispNet.textContent     = net.toFixed(2);
            if (dispVat) dispVat.textContent     = vat.toFixed(2);
            if (dispGross) dispGross.textContent = gross.toFixed(2);
        }

        var vatSelector = document.getElementById('vpdlVatTreatment');
        if (vatSelector) {
            vatSelector.addEventListener('change', recalcDirectInvoiceRows);
        }

        // Add Invoice Line Item
        var addRowBtn = document.getElementById('vpdlAddInvoiceRowBtn');
        var container = document.getElementById('vpdlInvoiceItemsContainer');

        if (addRowBtn && container) {
            addRowBtn.addEventListener('click', function() {
                var rows = container.querySelectorAll('.vpdl-inv-item-row');
                var idx = rows.length;

                var row = document.createElement('div');
                row.className = 'vpdl-inv-item-row';
                row.setAttribute('data-index', idx);

                var descInput = document.createElement('input');
                descInput.type = 'text';
                descInput.name = 'items[' + idx + '][description]';
                descInput.placeholder = 'e.g. Structural Design & RSJ Calculations';
                descInput.required = true;
                descInput.className = 'vpdl-inv-input';
                descInput.style.flex = '3';

                var priceInput = document.createElement('input');
                priceInput.type = 'number';
                priceInput.step = '0.01';
                priceInput.min = '0';
                priceInput.name = 'items[' + idx + '][unit_price]';
                priceInput.placeholder = 'Net Price (£)';
                priceInput.required = true;
                priceInput.className = 'vpdl-inv-input vpdl-item-price-input';
                priceInput.style.flex = '1';
                priceInput.style.fontFamily = 'monospace';

                var qtyHidden = document.createElement('input');
                qtyHidden.type = 'hidden';
                qtyHidden.name = 'items[' + idx + '][quantity]';
                qtyHidden.value = '1';

                var totalHidden = document.createElement('input');
                totalHidden.type = 'hidden';
                totalHidden.name = 'items[' + idx + '][total]';
                totalHidden.className = 'vpdl-hidden-line-total';
                totalHidden.value = '0.00';

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'vpdl-btn-remove-row';
                removeBtn.setAttribute('data-action', 'remove-row');
                removeBtn.innerHTML = '×';

                row.appendChild(descInput);
                row.appendChild(priceInput);
                row.appendChild(qtyHidden);
                row.appendChild(totalHidden);
                row.appendChild(removeBtn);

                container.appendChild(row);
            });
        }

        if (container) {
            container.addEventListener('input', function(e) {
                if (e.target && e.target.classList.contains('vpdl-item-price-input')) {
                    recalcDirectInvoiceRows();
                }
            });

            container.addEventListener('click', function(e) {
                var btn = e.target.closest('[data-action="remove-row"]');
                if (btn) {
                    var rows = container.querySelectorAll('.vpdl-inv-item-row');
                    if (rows.length > 1) {
                        btn.closest('.vpdl-inv-item-row').remove();
                        container.querySelectorAll('.vpdl-inv-item-row').forEach(function(r, i) {
                            var desc = r.querySelector('input[type="text"]');
                            var prc  = r.querySelector('.vpdl-item-price-input');
                            var qty  = r.querySelector('input[name*="[quantity]"]');
                            var tot  = r.querySelector('.vpdl-hidden-line-total');
                            if (desc) desc.name = 'items[' + i + '][description]';
                            if (prc) prc.name   = 'items[' + i + '][unit_price]';
                            if (qty) qty.name   = 'items[' + i + '][quantity]';
                            if (tot) tot.name   = 'items[' + i + '][total]';
                        });
                        recalcDirectInvoiceRows();
                    } else {
                        alert('At least one item row is required.');
                    }
                }
            });
        }

        // Live Search Filter
        var searchInput = document.getElementById('vpdlInvoiceSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var filter = searchInput.value.toLowerCase().trim();
                var rows   = moduleContainer.querySelectorAll('.vpdl-invoice-row');

                rows.forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
                });
            });
        }

        // Copy URL Link
        moduleContainer.querySelectorAll('[data-action="copy-url"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url = btn.getAttribute('data-url');
                if (!url) return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function() {
                        var span = btn.querySelector('span');
                        if (span) {
                            var old = span.textContent;
                            span.textContent = 'Copied!';
                            setTimeout(function() { span.textContent = old; }, 2000);
                        }
                    }).catch(function() {
                        window.prompt('Copy: Ctrl+C, Enter', url);
                    });
                } else {
                    window.prompt('Copy: Ctrl+C, Enter', url);
                }
            });
        });

        // Status Auto-Submit
        moduleContainer.querySelectorAll('select[data-action="auto-submit"]').forEach(function(sel) {
            sel.addEventListener('change', function() {
                sel.form.submit();
            });
        });

        // Delete Confirm
        moduleContainer.querySelectorAll('form[data-action="confirm-delete"]').forEach(function(f) {
            f.addEventListener('submit', function(e) {
                var ref = f.getAttribute('data-ref') || '';
                if (!window.confirm('Permanently delete invoice ' + ref + '?')) {
                    e.preventDefault();
                }
            });
        });

        // Modal Print Window
        var modal = document.getElementById('vpdlPrintInvoiceModal');

        moduleContainer.querySelectorAll('[data-action="open-print-modal"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-id');
                var inv = vpdlInvoiceData.find(function(item) { return item.id == id; });
                if (!inv) return;

                document.getElementById('modal_inv_num').textContent    = inv.invoice_number || '—';
                document.getElementById('modal_cust_name').textContent  = inv.customer_name || 'Valued Client';
                document.getElementById('modal_site_addr').textContent  = (inv.site_address || 'As per quotation') + (inv.postcode ? ' (' + inv.postcode + ')' : '');
                document.getElementById('modal_cust_phone').textContent = 'Phone: ' + (inv.customer_phone || 'N/A') + ' | Email: ' + (inv.customer_email || 'N/A');
                document.getElementById('modal_subtotal').textContent   = currencySymbol + ' ' + parseFloat(inv.subtotal || 0).toFixed(2);
                document.getElementById('modal_vat').textContent        = currencySymbol + ' ' + parseFloat(inv.vat_amount || 0).toFixed(2);
                document.getElementById('modal_grand').textContent      = currencySymbol + ' ' + parseFloat(inv.grand_total || 0).toFixed(2);
                document.getElementById('modal_bacs_ref').textContent   = inv.invoice_number || '—';
                document.getElementById('modal_created_by').textContent = inv.created_by || 'Admin';

                // Dates
                var createdDate = new Date(inv.created_at ? inv.created_at.replace(/-/g, '/') : Date.now());
                var dueDate = new Date(createdDate);
                dueDate.setDate(dueDate.getDate() + 14);

                document.getElementById('modal_issue_date').textContent = createdDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
                document.getElementById('modal_due_date').textContent   = dueDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });

                // Status Pill
                var statusPill = document.getElementById('modal_status_pill');
                var isPaid = (inv.payment_status === 'Paid');
                statusPill.textContent = 'STATUS: ' + (inv.payment_status || 'UNPAID').toUpperCase();
                statusPill.style.background = isPaid ? '#ecfdf5' : '#fef2f2';
                statusPill.style.color      = isPaid ? '#059669' : '#dc2626';

                // Render Table Items
                var tbody = document.getElementById('modal_items_body');
                tbody.innerHTML = '';

                try {
                    var items = JSON.parse(inv.items_json);
                    if (Array.isArray(items) && items.length > 0) {
                        items.forEach(function(it) {
                            var tr = document.createElement('tr');
                            tr.style.borderBottom = '1px solid #f1f5f9';

                            var tdDesc = document.createElement('td');
                            tdDesc.style.padding = '10px 12px';
                            tdDesc.style.fontWeight = '600';
                            tdDesc.style.color = '#0f172a';
                            tdDesc.textContent = it.description || it.desc || 'Professional Architectural / Structural Services';

                            var tdPrice = document.createElement('td');
                            tdPrice.style.padding = '10px 12px';
                            tdPrice.style.textAlign = 'right';
                            tdPrice.style.fontFamily = 'monospace';
                            tdPrice.style.fontWeight = '700';
                            var prc = parseFloat(it.total || it.unit_price || it.price || inv.subtotal);
                            tdPrice.textContent = currencySymbol + ' ' + prc.toFixed(2);

                            tr.appendChild(tdDesc);
                            tr.appendChild(tdPrice);
                            tbody.appendChild(tr);
                        });
                    } else {
                        var tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #f1f5f9';
                        tr.innerHTML = '<td style="padding:10px 12px; font-weight:600; color:#0f172a;">Building & Consultancy Services</td><td style="padding:10px 12px; text-align:right; font-family:monospace; font-weight:700;">' + currencySymbol + ' ' + parseFloat(inv.subtotal).toFixed(2) + '</td>';
                        tbody.appendChild(tr);
                    }
                } catch(e) {
                    var tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #f1f5f9';
                    tr.innerHTML = '<td style="padding:10px 12px; font-weight:600; color:#0f172a;">Building Construction / Planning Services</td><td style="padding:10px 12px; text-align:right; font-family:monospace; font-weight:700;">' + currencySymbol + ' ' + parseFloat(inv.subtotal).toFixed(2) + '</td>';
                    tbody.appendChild(tr);
                }

                if (modal) modal.style.display = 'flex';
            });
        });

        var closeModalBtn = document.querySelector('[data-action="close-print-modal"]');
        if (closeModalBtn && modal) {
            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        var printWindowBtn = document.querySelector('[data-action="print-window"]');
        if (printWindowBtn) {
            printWindowBtn.addEventListener('click', function() {
                window.print();
            });
        }
    });
})();