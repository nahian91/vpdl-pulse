<?php
/**
 * View: VPDL Accounts, Financial Ledger & VAT Reconciliation
 * File: views/accounts.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Permissions gate: Administrators and authorized financial controllers only
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
    wp_die( esc_html__( 'Forbidden: Insufficient privileges to access financial accounts.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_cust     = $wpdb->prefix . 'vpdl_customers';
$t_inv      = $wpdb->prefix . 'vpdl_invoices';
$t_accounts = $wpdb->prefix . 'vpdl_accounts';

$currency   = (string) get_option( 'vpdl_currency', '£' );
$vat_rate   = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url   = admin_url( 'admin.php?page=vpdl&view=accounts' );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
$msg_code   = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete Ledger Entry Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_account_entry' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
        wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_accounts, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'outflows', 'msg' => 'entry_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Manual Ledger / Expense Ingestion Handler
if ( isset( $_POST['vpdl_action'] ) && 'add_account_entry' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
        wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
    }

    $title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    $type         = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'Expense';
    $category     = isset( $_POST['expense_category'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_category'] ) ) : 'General';
    $amount       = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.00;
    $vat_amount   = isset( $_POST['vat_amount'] ) ? floatval( $_POST['vat_amount'] ) : 0.00;
    $entry_date   = isset( $_POST['entry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_date'] ) ) : current_time( 'Y-m-d' );
    $reference_id = isset( $_POST['reference_id'] ) ? sanitize_text_field( wp_unslash( $_POST['reference_id'] ) ) : '';

    $full_title = '[' . $category . '] ' . $title;

    if ( ! empty( $title ) && $amount > 0 ) {
        $wpdb->insert(
            $t_accounts,
            array(
                'title'         => $full_title,
                'type'          => $type,
                'amount'        => $amount,
                'vat_collected' => $vat_amount,
                'reference_id'  => $reference_id,
                'entry_date'    => $entry_date,
            ),
            array( '%s', '%s', '%f', '%f', '%s', '%s' )
        );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'overview', 'msg' => 'entry_saved' ), $base_url ) );
        exit;
    }
}

// 3. Financial Analytics Calculations
$total_invoiced_net   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(subtotal), 0) FROM {$t_inv} WHERE payment_status = 'Paid'" );
$total_vat_accrued    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(vat_amount), 0) FROM {$t_inv} WHERE payment_status = 'Paid'" );
$total_invoiced_gross = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0) FROM {$t_inv} WHERE payment_status = 'Paid'" );

$total_expenses    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$t_accounts} WHERE type = 'Expense'" );
$total_expense_vat = (float) $wpdb->get_var( "SELECT COALESCE(SUM(vat_collected), 0) FROM {$t_accounts} WHERE type = 'Expense'" );

$net_margin   = $total_invoiced_net - $total_expenses;
$net_vat_hmrc = $total_vat_accrued - $total_expense_vat;

// Pending Receivables Query
$pending_invoices = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode 
     FROM {$t_inv} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
     WHERE i.payment_status = 'Unpaid' 
     ORDER BY i.id DESC"
);
$total_unpaid_due = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0) FROM {$t_inv} WHERE payment_status = 'Unpaid'" );

// Full Ledger Transactions List
$ledger_entries = $wpdb->get_results( "SELECT * FROM {$t_accounts} ORDER BY entry_date DESC, id DESC LIMIT 50" );

// Flash Notifications Mapping
$alerts = array(
    'entry_saved'   => array( 'msg' => __( 'Financial transaction recorded in ledger.', 'vpdl-management' ), 'type' => 'success' ),
    'entry_deleted' => array( 'msg' => __( 'Ledger transaction permanently removed.', 'vpdl-management' ), 'type' => 'success' ),
);
?>

<div class="vpdl-accounts-wrapper" id="vpdlAccountsModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-acc-alert vpdl-acc-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-acc-card vpdl-acc-header-card">
        <div class="vpdl-acc-header-left">
            <div class="vpdl-acc-header-icon">
                <?php echo vpdl_get_svg( 'accounts', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-acc-header-title"><?php esc_html_e( 'Financial Ledger & HMRC VAT Reconciliations', 'vpdl-management' ); ?></h2>
                <div class="vpdl-acc-header-subtitle">
                    <?php esc_html_e( 'Fiscal Audits, Capital Flows, Unsettled Invoices & Making Tax Digital Records', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php $export_nonce = wp_create_nonce( 'vpdl_export_ledger' ); ?>
            <a href="<?php echo esc_url( add_query_arg( array( 'view' => 'accounts', 'export_ledger_csv' => '1', '_wpnonce' => $export_nonce ), admin_url( 'admin.php?page=vpdl' ) ) ); ?>" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'reports', '', 14 ); ?>
                <span><?php esc_html_e( 'Export CSV (Accountant)', 'vpdl-management' ); ?></span>
            </a>
            <button type="button" data-action="toggle-expense-box" style="background: var(--vpdl-accent); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                <span><?php esc_html_e( 'Record Outflow / Expense', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>

    <!-- Top Financial KPI Cards -->
    <div class="vpdl-acc-kpi-grid">
        <div class="vpdl-acc-card vpdl-acc-kpi-box">
            <div class="vpdl-acc-kpi-lbl"><?php esc_html_e( 'Paid Inflow (Net)', 'vpdl-management' ); ?></div>
            <div class="vpdl-acc-kpi-val vpdl-val-green">
                <?php echo esc_html( $currency . ' ' . number_format( $total_invoiced_net, 2 ) ); ?>
            </div>
            <div class="vpdl-acc-kpi-sub">
                <?php esc_html_e( 'Gross Total:', 'vpdl-management' ); ?> <strong style="color: var(--vpdl-text-main); font-family: monospace;"><?php echo esc_html( $currency . ' ' . number_format( $total_invoiced_gross, 2 ) ); ?></strong>
            </div>
        </div>

        <div class="vpdl-acc-card vpdl-acc-kpi-box">
            <div class="vpdl-acc-kpi-lbl"><?php printf( esc_html__( 'Output VAT (%d%%)', 'vpdl-management' ), (int) $vat_rate ); ?></div>
            <div class="vpdl-acc-kpi-val vpdl-val-amber">
                <?php echo esc_html( $currency . ' ' . number_format( $total_vat_accrued, 2 ) ); ?>
            </div>
            <div class="vpdl-acc-kpi-sub">
                <?php esc_html_e( 'Net VAT Due to HMRC:', 'vpdl-management' ); ?> <strong style="color: #f59e0b; font-family: monospace;"><?php echo esc_html( $currency . ' ' . number_format( $net_vat_hmrc, 2 ) ); ?></strong>
            </div>
        </div>

        <div class="vpdl-acc-card vpdl-acc-kpi-box">
            <div class="vpdl-acc-kpi-lbl"><?php esc_html_e( 'Operational Outflows', 'vpdl-management' ); ?></div>
            <div class="vpdl-acc-kpi-val vpdl-val-red">
                <?php echo esc_html( $currency . ' ' . number_format( $total_expenses, 2 ) ); ?>
            </div>
            <div class="vpdl-acc-kpi-sub">
                <?php printf( esc_html__( 'Claimable Input VAT: %s%s', 'vpdl-management' ), esc_html( $currency ), number_format( $total_expense_vat, 2 ) ); ?>
            </div>
        </div>

        <div class="vpdl-acc-card vpdl-acc-kpi-box">
            <div class="vpdl-acc-kpi-lbl"><?php esc_html_e( 'Net Operative Margin', 'vpdl-management' ); ?></div>
            <div class="vpdl-acc-kpi-val" style="color: <?php echo $net_margin >= 0 ? '#10b981' : '#ef4444'; ?>;">
                <?php echo esc_html( $currency . ' ' . number_format( $net_margin, 2 ) ); ?>
            </div>
            <div class="vpdl-acc-kpi-sub">
                <?php echo esc_html( $net_margin >= 0 ? __( 'Net Operative Surplus', 'vpdl-management' ) : __( 'Deficit Registered', 'vpdl-management' ) ); ?>
            </div>
        </div>
    </div>

    <!-- Manual Expense Ingestion Box (Collapsible) -->
    <div id="vpdlEntryBox" class="vpdl-acc-card" style="display: none; padding: 24px; border: 2px solid var(--vpdl-accent);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 14px; margin-bottom: 18px;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Log Expense / Site Disbursement (HMRC Compliant)', 'vpdl-management' ); ?></h3>
            <button type="button" data-action="toggle-expense-box" style="background: transparent; border: none; cursor: pointer; color: var(--vpdl-text-muted); display: inline-flex; align-items: center;">
                <?php echo vpdl_get_svg( 'close', '', 18 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="add_account_entry">

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 14px;" class="vpdl-acc-form-group">
                <div>
                    <label for="vpdl_acc_title"><?php esc_html_e( 'Transaction Payee / Vendor Description', 'vpdl-management' ); ?> *</label>
                    <input type="text" id="vpdl_acc_title" name="title" placeholder="<?php esc_attr_e( 'e.g. Planning Portal Submission Fees / Structural Steel Supplier', 'vpdl-management' ); ?>" required class="vpdl-acc-input">
                </div>
                <div>
                    <label for="vpdl_acc_category"><?php esc_html_e( 'Accounting Head', 'vpdl-management' ); ?></label>
                    <select name="expense_category" id="vpdl_acc_category" class="vpdl-acc-select">
                        <option value="Statutory Fees"><?php esc_html_e( 'Council / Statutory Fees', 'vpdl-management' ); ?></option>
                        <option value="Subcontractor CIS"><?php esc_html_e( 'Subcontractor Labour (CIS)', 'vpdl-management' ); ?></option>
                        <option value="Materials"><?php esc_html_e( 'Materials & Equipment', 'vpdl-management' ); ?></option>
                        <option value="Professional Fees"><?php esc_html_e( 'Survey & Specialist Fees', 'vpdl-management' ); ?></option>
                        <option value="Software & Cad"><?php esc_html_e( 'Software & IT Licenses', 'vpdl-management' ); ?></option>
                        <option value="Travel & Site"><?php esc_html_e( 'Travel & Site Mileage', 'vpdl-management' ); ?></option>
                        <option value="Office Overheads"><?php esc_html_e( 'Office & Administrative', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_acc_type"><?php esc_html_e( 'Type', 'vpdl-management' ); ?></label>
                    <select name="type" id="vpdl_acc_type" class="vpdl-acc-select">
                        <option value="Expense"><?php esc_html_e( 'Expense / Disbursement', 'vpdl-management' ); ?></option>
                        <option value="Income"><?php esc_html_e( 'Direct Capital / Income', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_acc_date"><?php esc_html_e( 'Transaction Date', 'vpdl-management' ); ?></label>
                    <input type="date" id="vpdl_acc_date" name="entry_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="vpdl-acc-input">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-top: 14px;" class="vpdl-acc-form-group">
                <div>
                    <label for="vpdl_entry_amount"><?php esc_html_e( 'Net Amount (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                    <input type="number" step="0.01" min="0" name="amount" id="vpdl_entry_amount" data-action="calc-vat" required placeholder="0.00" class="vpdl-acc-input" style="font-family: monospace;">
                </div>
                <div>
                    <label for="vpdl_entry_vat"><?php esc_html_e( 'Claimable Input VAT (£)', 'vpdl-management' ); ?></label>
                    <input type="number" step="0.01" min="0" name="vat_amount" id="vpdl_entry_vat" placeholder="0.00" class="vpdl-acc-input" style="font-family: monospace;">
                </div>
                <div>
                    <label for="vpdl_entry_ref"><?php esc_html_e( 'Invoice / Receipt Voucher No.', 'vpdl-management' ); ?></label>
                    <input type="text" name="reference_id" id="vpdl_entry_ref" placeholder="<?php esc_attr_e( 'e.g. REC-9812 / BACS', 'vpdl-management' ); ?>" class="vpdl-acc-input" style="font-family: monospace;">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="button button-primary" style="height: 44px; padding: 0 28px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Commit Entry to Fiscal Ledger', 'vpdl-management' ); ?></span>
                </button>
            </div>
        </form>
    </div>

    <!-- Sub-Tabs Navigation -->
    <div class="vpdl-acc-tab-nav" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="overview" id="vpdlAccTabBtnOverview" class="vpdl-acc-tab-btn <?php echo ( 'overview' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'overview' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'dashboard', '', 18 ); ?>
            <span><?php esc_html_e( 'Ledger & Unpaid Invoices', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="outflows" id="vpdlAccTabBtnOutflows" class="vpdl-acc-tab-btn <?php echo ( 'outflows' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'outflows' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
            <span><?php esc_html_e( 'Expense Disbursements History', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $ledger_entries ) ); ?>)</span>
        </button>
    </div>

    <!-- TAB 1: OVERVIEW & UNPAID INVOICES DUAL GRID -->
    <div id="vpdlAccTabPaneOverview" class="vpdl-acc-tab-pane <?php echo ( 'overview' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-acc-dual-grid">
            
            <!-- Pending / Outstanding Receivables List -->
            <div class="vpdl-acc-card" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 12px; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'invoice', '', 18 ); ?>
                        <span><?php esc_html_e( 'Outstanding Client Receivables', 'vpdl-management' ); ?></span>
                    </h3>
                    <span style="font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 4px; background: rgba(239, 68, 68, 0.1); color: #ef4444; font-family: monospace;">
                        <?php echo esc_html( $currency . ' ' . number_format( $total_unpaid_due, 2 ) ); ?>
                    </span>
                </div>

                <?php if ( ! empty( $pending_invoices ) ) : ?>
                    <div class="vpdl-acc-feed-box">
                        <?php foreach ( $pending_invoices as $p_inv ) : ?>
                            <div class="vpdl-acc-feed-item">
                                <div>
                                    <strong style="color: var(--vpdl-text-main); font-size: 13.5px;"><?php echo esc_html( $p_inv->customer_name ); ?></strong>
                                    <div style="font-size: 11.5px; color: var(--vpdl-text-muted); font-family: monospace; margin-top: 2px;">
                                        <?php echo esc_html( $p_inv->invoice_number ); ?> • <?php echo esc_html( $p_inv->created_at ); ?>
                                    </div>
                                    <?php if ( ! empty( $p_inv->postcode ) ) : ?>
                                        <div style="font-size: 11px; color: var(--vpdl-accent); font-family: monospace; margin-top: 2px;"><?php echo esc_html( $p_inv->postcode ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <strong style="font-family: monospace; color: #ef4444; font-size: 14.5px;">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $p_inv->grand_total, 2 ) ); ?>
                                    </strong>
                                    <div style="margin-top: 4px;">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=invoices' ) ); ?>" style="font-size: 11px; color: var(--vpdl-accent); font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <span><?php esc_html_e( 'Settle Invoice', 'vpdl-management' ); ?></span>
                                            <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0; font-size: 13px;">
                        <?php esc_html_e( 'All client invoices are fully settled. No receivables due.', 'vpdl-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Outflows Feed -->
            <div class="vpdl-acc-card" style="padding: 24px;">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
                    <span><?php esc_html_e( 'Recent Outflows & Disbursements', 'vpdl-management' ); ?></span>
                </h3>

                <?php if ( ! empty( $ledger_entries ) ) : ?>
                    <div class="vpdl-acc-feed-box">
                        <?php foreach ( array_slice( $ledger_entries, 0, 8 ) as $entry ) : 
                            $is_exp = ( 'Expense' === $entry->type );
                        ?>
                            <div class="vpdl-acc-feed-item">
                                <div>
                                    <strong style="color: var(--vpdl-text-main); font-size: 13px;"><?php echo esc_html( $entry->title ); ?></strong>
                                    <div style="font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;">
                                        <?php echo esc_html( $entry->entry_date ); ?> • Ref: <span style="font-family: monospace;"><?php echo esc_html( $entry->reference_id ?: 'N/A' ); ?></span>
                                    </div>
                                </div>
                                <div style="text-align: right; font-family: monospace; font-weight: 800; font-size: 14px; color: <?php echo $is_exp ? '#ef4444' : '#10b981'; ?>;">
                                    <?php echo esc_html( ( $is_exp ? '-' : '+' ) . $currency . ' ' . number_format( (float) $entry->amount, 2 ) ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0; font-size: 13px;">
                        <?php esc_html_e( 'No disbursements recorded in the ledger yet.', 'vpdl-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- TAB 2: DETAILED OUTFLOWS & VOUCHERS LEDGER TABLE -->
    <div id="vpdlAccTabPaneOutflows" class="vpdl-acc-tab-pane <?php echo ( 'outflows' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-acc-card" style="padding: 24px;">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Complete Outflows & Operational Disbursements History', 'vpdl-management' ); ?>
            </h3>

            <div style="overflow-x: auto;">
                <table class="vpdl-acc-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Head / Vendor Details', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Voucher Ref.', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Date', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Claimable VAT', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Net Amount', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Action', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $ledger_entries ) ) : ?>
                            <?php foreach ( $ledger_entries as $e ) : 
                                $is_exp = ( 'Expense' === $e->type );
                            ?>
                                <tr class="vpdl-acc-row">
                                    <td style="font-weight: 700; color: var(--vpdl-text-main);">
                                        <?php echo esc_html( $e->title ); ?>
                                    </td>
                                    <td>
                                        <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: <?php echo $is_exp ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; color: <?php echo $is_exp ? '#ef4444' : '#10b981'; ?>;">
                                            <?php echo esc_html( $e->type ); ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted);">
                                        <?php echo esc_html( $e->reference_id ?: '—' ); ?>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted);">
                                        <?php echo esc_html( $e->entry_date ); ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; color: #f59e0b;">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $e->vat_collected, 2 ) ); ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; font-weight: 800; font-size: 14px; color: <?php echo $is_exp ? '#ef4444' : '#10b981'; ?>;">
                                        <?php echo esc_html( ( $is_exp ? '-' : '+' ) . $currency . ' ' . number_format( (float) $e->amount, 2 ) ); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <form method="POST" action="<?php echo esc_url( add_query_arg( array( 'tab' => 'outflows' ), $base_url ) ); ?>" style="display:inline;" data-action="confirm-delete">
                                            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                            <input type="hidden" name="vpdl_action" value="delete_account_entry">
                                            <input type="hidden" name="entry_id" value="<?php echo esc_attr( (string) $e->id ); ?>">
                                            <button type="submit" style="padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                <?php echo vpdl_get_svg( 'trash', '', 12 ); ?>
                                                <span><?php esc_html_e( 'Delete', 'vpdl-management' ); ?></span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                    <?php esc_html_e( 'No disbursements recorded in the ledger.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>