/**
 * View: VPDL Master Configuration & Settings Console Scripts
 * File: assets/js/settings.js
 * @version 1.0.0
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var moduleContainer = document.getElementById('vpdlSettingsModule');
        if (!moduleContainer) return;

        // Tab Navigation
        var tabButtons = moduleContainer.querySelectorAll('[data-action="switch-tab"]');
        var paneCompany = document.getElementById('vpdlTabPaneCompany');
        var paneFinance = document.getElementById('vpdlTabPaneFinance');
        var paneBank    = document.getElementById('vpdlTabPaneBank');

        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tabKey = btn.getAttribute('data-tab');

                tabButtons.forEach(function(b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });

                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');

                if (paneCompany) paneCompany.classList.remove('is-active');
                if (paneFinance) paneFinance.classList.remove('is-active');
                if (paneBank) paneBank.classList.remove('is-active');

                if (tabKey === 'finance') {
                    if (paneFinance) paneFinance.classList.add('is-active');
                } else if (tabKey === 'bank') {
                    if (paneBank) paneBank.classList.add('is-active');
                } else {
                    if (paneCompany) paneCompany.classList.add('is-active');
                }
            });
        });

        // Bank Card Live Preview Update
        function updateBankCardPreview() {
            var bank     = document.getElementById('vpdl_input_bank') ? document.getElementById('vpdl_input_bank').value : '';
            var accName  = document.getElementById('vpdl_input_acc_name') ? document.getElementById('vpdl_input_acc_name').value : '';
            var accNo    = document.getElementById('vpdl_input_acc_no') ? document.getElementById('vpdl_input_acc_no').value : '';
            var sortCode = document.getElementById('vpdl_input_sort_code') ? document.getElementById('vpdl_input_sort_code').value : '';
            var iban     = document.getElementById('vpdl_input_iban') ? document.getElementById('vpdl_input_iban').value : '';
            var swift    = document.getElementById('vpdl_input_swift') ? document.getElementById('vpdl_input_swift').value : '';

            var cardB  = document.getElementById('cardBank');
            var cardAN = document.getElementById('cardAccName');
            var cardAC = document.getElementById('cardAccNo');
            var cardSC = document.getElementById('cardSortCode');
            var cardIB = document.getElementById('cardIban');
            var cardSW = document.getElementById('cardSwift');

            if (cardB) cardB.textContent  = bank || '—';
            if (cardAN) cardAN.textContent = accName || '—';
            if (cardAC) cardAC.textContent = accNo || '—';
            if (cardSC) cardSC.textContent = sortCode || '—';
            if (cardIB) cardIB.textContent = iban || '—';
            if (cardSW) cardSW.textContent = swift || '—';
        }

        moduleContainer.querySelectorAll('[data-action="update-bank-card"]').forEach(function(inp) {
            inp.addEventListener('input', updateBankCardPreview);
        });

        // Media Library Uploader for Logo
        var mediaBtn = moduleContainer.querySelector('[data-action="open-media-uploader"]');
        if (mediaBtn) {
            mediaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof wp !== 'undefined' && wp.media) {
                    var uploader = wp.media({
                        title: 'Select VPDL Official Logo',
                        button: { text: 'Use this Logo' },
                        multiple: false
                    }).on('select', function() {
                        var attachment = uploader.state().get('selection').first().toJSON();
                        var logoField = document.getElementById('vpdlLogoUrlField');
                        var previewCont = document.getElementById('vpdlLogoPreviewContainer');
                        var previewImg = document.getElementById('vpdlLogoPreviewImg');

                        if (logoField) logoField.value = attachment.url;
                        if (previewImg && previewCont) {
                            previewImg.src = attachment.url;
                            previewCont.style.display = 'block';
                        }
                    }).open();
                } else {
                    alert('Media Uploader is not available. Please enter the image URL manually.');
                }
            });
        }

        updateBankCardPreview();
    });
})();