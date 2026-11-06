<?php
/**
 * View: VPDL Master Configuration & Settings Console
 * File: views/settings.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Access restricted to administrators only
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Access Denied: Administrator privileges required.', 'vpdl-management' ), 403 );
}

$base_url   = admin_url( 'admin.php?page=vpdl&view=settings' );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'company';
$msg_code   = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// Save Settings Form Handler
if ( isset( $_POST['vpdl_action'] ) && 'save_settings' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
    }

    update_option( 'vpdl_business_name', isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '' );
    update_option( 'vpdl_company_tag', isset( $_POST['company_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['company_tag'] ) ) : '' );
    update_option( 'vpdl_currency', isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '£' );
    update_option( 'vpdl_vat_rate', isset( $_POST['vat_rate'] ) ? floatval( wp_unslash( $_POST['vat_rate'] ) ) : 20.00 );
    update_option( 'vpdl_vat_number', isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '' );
    update_option( 'vpdl_quote_validity', isset( $_POST['quote_validity'] ) ? absint( $_POST['quote_validity'] ) : 30 );
    update_option( 'vpdl_phone', isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '' );
    update_option( 'vpdl_email', isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '' );
    update_option( 'vpdl_address', isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '' );
    update_option( 'vpdl_reg_no', isset( $_POST['reg_no'] ) ? sanitize_text_field( wp_unslash( $_POST['reg_no'] ) ) : '' );
    update_option( 'vpdl_pi_insurance', isset( $_POST['pi_insurance'] ) ? sanitize_text_field( wp_unslash( $_POST['pi_insurance'] ) ) : '' );
    update_option( 'vpdl_logo_url', isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '' );
    update_option( 'vpdl_quote_terms', isset( $_POST['quote_terms'] ) ? sanitize_textarea_field( wp_unslash( $_POST['quote_terms'] ) ) : '' );

    // Banking & BACS Remittance
    update_option( 'vpdl_bank_name', isset( $_POST['bank_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) : '' );
    update_option( 'vpdl_bank_account_name', isset( $_POST['bank_account_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account_name'] ) ) : '' );
    update_option( 'vpdl_bank_account_no', isset( $_POST['bank_account_no'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_account_no'] ) ) : '' );
    update_option( 'vpdl_bank_sort_code', isset( $_POST['bank_sort_code'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_sort_code'] ) ) : '' );
    update_option( 'vpdl_bank_iban', isset( $_POST['bank_iban'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_iban'] ) ) : '' );
    update_option( 'vpdl_bank_swift', isset( $_POST['bank_swift'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_swift'] ) ) : '' );

    wp_safe_redirect( add_query_arg( array( 'tab' => $active_tab, 'msg' => 'settings_saved' ), $base_url ) );
    exit;
}

$currency       = (string) get_option( 'vpdl_currency', '£' );
$b_name         = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$company_tag    = (string) get_option( 'vpdl_company_tag', 'Offers Project Management, Civil and Structural Engineering, Planning and Survey Services' );
$phone          = (string) get_option( 'vpdl_phone', '+44 3333 445 807' );
$email          = (string) get_option( 'vpdl_email', 'vpdl.uk@gmail.com' );
$address        = (string) get_option( 'vpdl_address', 'ESL Office 256B, 182-184 High Street North, London, England, E6 2JA' );
$reg_no         = (string) get_option( 'vpdl_reg_no', '08456916' );
$vat_number     = (string) get_option( 'vpdl_vat_number', '' );
$quote_validity = (int) get_option( 'vpdl_quote_validity', 30 );
$pi_insurance   = (string) get_option( 'vpdl_pi_insurance', 'Hiscox Insurance UK (Policy: PI-89210-UK)' );
$vat_rate       = (float) get_option( 'vpdl_vat_rate', 20.00 );
$logo_url       = (string) get_option( 'vpdl_logo_url', '' );
$quote_terms    = (string) get_option( 'vpdl_quote_terms', 'All professional fees are strictly excluding VAT unless stated otherwise. Quotation remains valid for 30 days from date of issuance. Payment milestones: 50% upon booking/site visit initiation, 50% upon drawing submission.' );

$bank_name     = (string) get_option( 'vpdl_bank_name', 'Barclays Bank UK PLC' );
$bank_acc_name = (string) get_option( 'vpdl_bank_account_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$bank_acc_no   = (string) get_option( 'vpdl_bank_account_no', '83749201' );
$bank_sort_cd  = (string) get_option( 'vpdl_bank_sort_code', '20-04-15' );
$bank_iban     = (string) get_option( 'vpdl_bank_iban', '' );
$bank_swift    = (string) get_option( 'vpdl_bank_swift', '' );

// Flash Notifications Mapping
$alerts = array(
    'settings_saved' => array( 'msg' => __( 'Settings successfully updated and synchronized across modules.', 'vpdl-management' ), 'type' => 'success' ),
);
?>

<div class="vpdl-settings-wrapper" id="vpdlSettingsModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-alert vpdl-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-settings-card vpdl-settings-header">
        <div class="vpdl-settings-header-left">
            <div class="vpdl-settings-header-icon">
                <?php echo vpdl_get_svg( 'settings', '', 26 ); ?>
            </div>
            <div>
                <h2 class="vpdl-settings-title"><?php esc_html_e( 'System Settings & Practice Configuration', 'vpdl-management' ); ?></h2>
                <div class="vpdl-settings-subtitle">
                    <?php esc_html_e( 'Corporate Registration, HMRC Tax Baseline & BACS Remittance Engine', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <?php if ( ! empty( $vat_number ) ) : ?>
                <span style="font-size: 11.5px; font-weight: 700; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(16, 185, 129, 0.25); font-family: monospace;">
                    VAT: <?php echo esc_html( $vat_number ); ?>
                </span>
            <?php endif; ?>
            <span style="font-size: 11.5px; font-weight: 700; color: var(--vpdl-accent); background: rgba(2, 132, 199, 0.1); padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(2, 132, 199, 0.25); font-family: monospace;">
                CRN: <?php echo esc_html( $reg_no ); ?>
            </span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="vpdl-tab-nav" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="company" id="vpdlSubTabBtnCompany" class="vpdl-tab-btn <?php echo ( 'company' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'company' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'planning', '', 18 ); ?>
            <span><?php esc_html_e( 'Corporate Profile', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="finance" id="vpdlSubTabBtnFinance" class="vpdl-tab-btn <?php echo ( 'finance' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'finance' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'accounts', '', 18 ); ?>
            <span><?php esc_html_e( 'Tax & Billing Rules', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="bank" id="vpdlSubTabBtnBank" class="vpdl-tab-btn <?php echo ( 'bank' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'bank' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'invoice', '', 18 ); ?>
            <span><?php esc_html_e( 'Banking & BACS', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- Form Workspace -->
    <form method="POST" action="<?php echo esc_url( $base_url ); ?>" id="vpdlMasterSettingsForm">
        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
        <input type="hidden" name="vpdl_action" value="save_settings">

        <!-- TAB 1: Corporate Profile -->
        <div id="vpdlTabPaneCompany" class="vpdl-tab-pane <?php echo ( 'company' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
            <div class="vpdl-settings-card" style="padding: 28px; max-width: 860px;">
                <div style="border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 14px; margin-bottom: 22px;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Company Identification & Brand Settings', 'vpdl-management' ); ?>
                    </h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                        <?php esc_html_e( 'These details populate document headers, invoices, and official CAD title blocks.', 'vpdl-management' ); ?>
                    </p>
                </div>

                <div class="vpdl-settings-grid-2 vpdl-form-group">
                    <div>
                        <label for="vpdl_business_name"><?php esc_html_e( 'Registered Business Name', 'vpdl-management' ); ?> *</label>
                        <input type="text" id="vpdl_business_name" name="business_name" value="<?php echo esc_attr( $b_name ); ?>" required class="vpdl-form-input">
                    </div>
                    <div>
                        <label for="vpdl_reg_no"><?php esc_html_e( 'Companies House CRN', 'vpdl-management' ); ?> *</label>
                        <input type="text" id="vpdl_reg_no" name="reg_no" value="<?php echo esc_attr( $reg_no ); ?>" required placeholder="08456916" class="vpdl-form-input" style="font-family: monospace;">
                    </div>
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdl_company_tag"><?php esc_html_e( 'Professional Scope Subtitle', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_company_tag" name="company_tag" value="<?php echo esc_attr( $company_tag ); ?>" class="vpdl-form-input">
                </div>

                <div class="vpdl-settings-grid-2 vpdl-form-group">
                    <div>
                        <label for="vpdl_phone"><?php esc_html_e( 'Office Phone / WhatsApp', 'vpdl-management' ); ?> *</label>
                        <input type="text" id="vpdl_phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" required class="vpdl-form-input" style="font-family: monospace;">
                    </div>
                    <div>
                        <label for="vpdl_email"><?php esc_html_e( 'Enquiries Email', 'vpdl-management' ); ?> *</label>
                        <input type="email" id="vpdl_email" name="email" value="<?php echo esc_attr( $email ); ?>" required class="vpdl-form-input">
                    </div>
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdl_address"><?php esc_html_e( 'Registered Office Address', 'vpdl-management' ); ?> *</label>
                    <textarea name="address" id="vpdl_address" rows="2" required class="vpdl-form-textarea"><?php echo esc_textarea( $address ); ?></textarea>
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdl_pi_insurance"><?php esc_html_e( 'Professional Indemnity (PI) Insurance', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_pi_insurance" name="pi_insurance" value="<?php echo esc_attr( $pi_insurance ); ?>" placeholder="Hiscox Insurance UK" class="vpdl-form-input">
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdlLogoUrlField"><?php esc_html_e( 'Header Logo URL', 'vpdl-management' ); ?></label>
                    <div style="display: flex; gap: 10px;">
                        <input type="url" name="logo_url" id="vpdlLogoUrlField" value="<?php echo esc_url( $logo_url ); ?>" placeholder="https://..." class="vpdl-form-input" style="flex: 1;">
                        <button type="button" data-action="open-media-uploader" style="padding: 0 18px; border-radius: 8px; font-size: 13px; font-weight: 700; background: var(--vpdl-badge-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main); cursor: pointer; white-space: nowrap;">
                            <?php esc_html_e( 'Media Library', 'vpdl-management' ); ?>
                        </button>
                    </div>
                    
                    <div id="vpdlLogoPreviewContainer" style="margin-top: 12px; <?php echo empty( $logo_url ) ? 'display:none;' : ''; ?>">
                        <div style="padding: 12px; background: rgba(255,255,255,0.03); border: 1px dashed var(--vpdl-border-color); border-radius: 8px; display: inline-flex; align-items: center; gap: 12px;">
                            <img id="vpdlLogoPreviewImg" src="<?php echo esc_url( $logo_url ); ?>" alt="Logo Preview" style="max-height: 40px; width: auto; object-fit: contain;">
                            <span style="font-size: 11.5px; color: var(--vpdl-text-muted); font-weight: 600;"><?php esc_html_e( 'Active Logo Preview', 'vpdl-management' ); ?></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="height: 44px; padding: 0 28px; font-weight: 700; background: var(--vpdl-accent); border: none; border-radius: 8px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Save Corporate Profile', 'vpdl-management' ); ?></span>
                </button>
            </div>
        </div>

        <!-- TAB 2: Tax & Billing Rules -->
        <div id="vpdlTabPaneFinance" class="vpdl-tab-pane <?php echo ( 'finance' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
            <div class="vpdl-settings-card" style="padding: 28px; max-width: 860px;">
                <div style="border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 14px; margin-bottom: 22px;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Taxation Baseline & Proposal Terms', 'vpdl-management' ); ?>
                    </h3>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                        <?php esc_html_e( 'Configure HMRC VAT percentages and legal conditions attached to client proposals.', 'vpdl-management' ); ?>
                    </p>
                </div>

                <div class="vpdl-settings-grid-3 vpdl-form-group">
                    <div>
                        <label for="vpdl_currency"><?php esc_html_e( 'Base Currency', 'vpdl-management' ); ?> *</label>
                        <input type="text" id="vpdl_currency" name="currency" value="<?php echo esc_attr( $currency ); ?>" required class="vpdl-form-input" style="font-family: monospace; font-weight: 700;">
                    </div>
                    <div>
                        <label for="vpdl_vat_number"><?php esc_html_e( 'HMRC VAT Number', 'vpdl-management' ); ?></label>
                        <input type="text" id="vpdl_vat_number" name="vat_number" value="<?php echo esc_attr( $vat_number ); ?>" placeholder="GB..." class="vpdl-form-input" style="font-family: monospace; font-weight: 700;">
                    </div>
                    <div>
                        <label for="vpdl_vat_rate"><?php esc_html_e( 'Standard VAT Rate (%)', 'vpdl-management' ); ?> *</label>
                        <input type="number" step="0.01" min="0" max="100" id="vpdl_vat_rate" name="vat_rate" value="<?php echo esc_attr( (string) $vat_rate ); ?>" required class="vpdl-form-input" style="font-family: monospace; font-weight: 700;">
                    </div>
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdl_quote_validity"><?php esc_html_e( 'Proposal Validity Period (Days)', 'vpdl-management' ); ?> *</label>
                    <input type="number" min="7" max="180" id="vpdl_quote_validity" name="quote_validity" value="<?php echo esc_attr( (string) $quote_validity ); ?>" class="vpdl-form-input" style="max-width: 220px; font-family: monospace; font-weight: 700;">
                </div>

                <div class="vpdl-form-group">
                    <label for="vpdl_quote_terms"><?php esc_html_e( 'Standard Terms & Remittance Conditions', 'vpdl-management' ); ?></label>
                    <textarea name="quote_terms" id="vpdl_quote_terms" rows="5" class="vpdl-form-textarea"><?php echo esc_textarea( $quote_terms ); ?></textarea>
                </div>

                <button type="submit" class="button button-primary" style="height: 44px; padding: 0 28px; font-weight: 700; background: var(--vpdl-accent); border: none; border-radius: 8px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Save Tax & Billing Rules', 'vpdl-management' ); ?></span>
                </button>
            </div>
        </div>

        <!-- TAB 3: Banking & BACS -->
        <div id="vpdlTabPaneBank" class="vpdl-tab-pane <?php echo ( 'bank' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
            <div class="vpdl-settings-bank-grid">
                
                <!-- Left Side Form -->
                <div class="vpdl-settings-card" style="padding: 28px;">
                    <div style="border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 14px; margin-bottom: 22px;">
                        <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                            <?php esc_html_e( 'BACS & Faster Payments Setup', 'vpdl-management' ); ?>
                        </h3>
                        <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                            <?php esc_html_e( 'Direct BACS remittance parameters printed on outgoing invoices.', 'vpdl-management' ); ?>
                        </p>
                    </div>

                    <div class="vpdl-settings-grid-2 vpdl-form-group">
                        <div>
                            <label for="vpdl_input_bank"><?php esc_html_e( 'Bank Institution', 'vpdl-management' ); ?> *</label>
                            <input type="text" name="bank_name" id="vpdl_input_bank" value="<?php echo esc_attr( $bank_name ); ?>" required data-action="update-bank-card" class="vpdl-form-input">
                        </div>
                        <div>
                            <label for="vpdl_input_acc_name"><?php esc_html_e( 'Account / Beneficiary Name', 'vpdl-management' ); ?> *</label>
                            <input type="text" name="bank_account_name" id="vpdl_input_acc_name" value="<?php echo esc_attr( $bank_acc_name ); ?>" required data-action="update-bank-card" class="vpdl-form-input">
                        </div>
                    </div>

                    <div class="vpdl-settings-grid-2 vpdl-form-group">
                        <div>
                            <label for="vpdl_input_acc_no"><?php esc_html_e( 'Account Number', 'vpdl-management' ); ?> *</label>
                            <input type="text" name="bank_account_no" id="vpdl_input_acc_no" value="<?php echo esc_attr( $bank_acc_no ); ?>" required data-action="update-bank-card" class="vpdl-form-input" style="font-family: monospace; font-weight: 700;">
                        </div>
                        <div>
                            <label for="vpdl_input_sort_code"><?php esc_html_e( 'Sort Code', 'vpdl-management' ); ?> *</label>
                            <input type="text" name="bank_sort_code" id="vpdl_input_sort_code" value="<?php echo esc_attr( $bank_sort_cd ); ?>" required data-action="update-bank-card" class="vpdl-form-input" style="font-family: monospace; font-weight: 700;">
                        </div>
                    </div>

                    <div class="vpdl-settings-grid-2 vpdl-form-group">
                        <div>
                            <label for="vpdl_input_iban"><?php esc_html_e( 'IBAN (Optional)', 'vpdl-management' ); ?></label>
                            <input type="text" name="bank_iban" id="vpdl_input_iban" value="<?php echo esc_attr( $bank_iban ); ?>" data-action="update-bank-card" class="vpdl-form-input" style="font-family: monospace;">
                        </div>
                        <div>
                            <label for="vpdl_input_swift"><?php esc_html_e( 'SWIFT / BIC (Optional)', 'vpdl-management' ); ?></label>
                            <input type="text" name="bank_swift" id="vpdl_input_swift" value="<?php echo esc_attr( $bank_swift ); ?>" data-action="update-bank-card" class="vpdl-form-input" style="font-family: monospace;">
                        </div>
                    </div>

                    <button type="submit" class="button button-primary" style="height: 44px; padding: 0 28px; font-weight: 700; background: var(--vpdl-accent); border: none; border-radius: 8px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Save Bank Details', 'vpdl-management' ); ?></span>
                    </button>
                </div>

                <!-- Right Side Live Preview Card -->
                <div class="vpdl-settings-card" style="padding: 24px; height: fit-content; background: linear-gradient(135deg, rgba(2, 132, 199, 0.05) 0%, rgba(2, 132, 199, 0.01) 100%); border: 1.5px solid rgba(2, 132, 199, 0.2);">
                    <div style="font-size: 11px; font-weight: 800; color: var(--vpdl-accent); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--vpdl-accent);"></span>
                        <?php esc_html_e( 'Invoice Footer Preview', 'vpdl-management' ); ?>
                    </div>

                    <h4 style="margin: 0 0 14px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Bank Remittance Advice', 'vpdl-management' ); ?>
                    </h4>

                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12.5px; line-height: 1.6; border-top: 1px dashed var(--vpdl-border-color); padding-top: 12px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Bank:', 'vpdl-management' ); ?></span>
                            <strong style="color: var(--vpdl-text-main);" id="cardBank"><?php echo esc_html( $bank_name ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Beneficiary:', 'vpdl-management' ); ?></span>
                            <strong style="color: var(--vpdl-text-main);" id="cardAccName"><?php echo esc_html( $bank_acc_name ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Account No:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; color: #10b981;" id="cardAccNo"><?php echo esc_html( $bank_acc_no ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Sort Code:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; color: #10b981;" id="cardSortCode"><?php echo esc_html( $bank_sort_cd ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'IBAN:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; color: var(--vpdl-text-main);" id="cardIban"><?php echo esc_html( $bank_iban ?: '—' ); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'SWIFT/BIC:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; color: var(--vpdl-text-main);" id="cardSwift"><?php echo esc_html( $bank_swift ?: '—' ); ?></strong>
                        </div>
                    </div>

                    <div style="margin-top: 16px; padding: 10px; border-radius: 6px; background: rgba(2, 132, 199, 0.06); font-size: 11.5px; color: var(--vpdl-text-muted); line-height: 1.5;">
                        <?php esc_html_e( 'Please quote the Invoice Number as the electronic payment reference.', 'vpdl-management' ); ?>
                    </div>
                </div>

            </div>
        </div>

    </form>
</div>