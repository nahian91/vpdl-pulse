/**
 * View: VPDL Quotations & Scope Proposals Engine Scripts
 * File: assets/js/quotes.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlQuotesModule');
        if (!moduleContainer) {
            return;
        }

        // Inline Status Select Dropdown
        moduleContainer.querySelectorAll('select[data-action="status-select"]').forEach(function(select) {
            select.addEventListener('change', function() {
                if (select.value) {
                    window.location.href = select.value;
                }
            });
        });

        // Copy Share Link via Clipboard API
        moduleContainer.querySelectorAll('[data-action="copy-share-link"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var url = btn.getAttribute('data-url');
                if (!url) return;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function() {
                        var span = btn.querySelector('span');
                        if (span) {
                            var prev = span.textContent;
                            span.textContent = 'Copied!';
                            setTimeout(function() {
                                span.textContent = prev;
                            }, 2000);
                        } else {
                            alert('Client proposal link copied!');
                        }
                    }).catch(function() {
                        window.prompt('Copy to clipboard: Ctrl+C, Enter', url);
                    });
                } else {
                    window.prompt('Copy to clipboard: Ctrl+C, Enter', url);
                }
            });
        });

        // Invoice Conversion Confirmation
        moduleContainer.querySelectorAll('[data-action="confirm-invoice"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!window.confirm('Generate an official tax invoice from this quote?')) {
                    e.preventDefault();
                }
            });
        });

        // Delete Quote Confirmation
        moduleContainer.querySelectorAll('[data-action="confirm-delete"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var ref = link.getAttribute('data-ref') || '';
                var msg = ref ? 'Permanently delete quotation ' + ref + '?' : 'Permanently delete this quotation?';
                if (!window.confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        // Print Window Trigger
        moduleContainer.querySelectorAll('[data-action="print-window"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                window.print();
            });
        });
    });
})();