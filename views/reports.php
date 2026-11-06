<?php
/**
 * View: VPDL Financial Performance Reports & Audit Statement
 * File: views/reports.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Access restricted to administrators and financial officers only
if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
    wp_die( esc_html__( 'Access Denied: Administrator privileges required to view financial statements.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_inv      = $wpdb->prefix . 'vpdl_invoices';
$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
$t_accounts = $wpdb->prefix . 'vpdl_accounts';
$t_cust     = $wpdb->prefix . 'vpdl_customers';

$currency      = (string) get_option( 'vpdl_currency', '£' );
$business_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$vat_rate      = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url      = admin_url( 'admin.php?page=vpdl&view=reports' );

// 1. Date Range Filter Parameters
$today_str     = current_time( 'Y-m-d' );
$default_start = gmdate( 'Y-m-01', strtotime( $today_str ) );
$default_end   = $today_str;

$start_date_input = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $default_start;
$end_date_input   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $default_end;

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date_input ) ) {
    $start_date_input = $default_start;
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date_input ) ) {
    $end_date_input = $default_end;
}

$active_sub_tab = isset( $_GET['sub_tab'] ) ? sanitize_key( wp_unslash( $_GET['sub_tab'] ) ) : 'income';
$status_filter  = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : 'all';

// 2. Query Time Boundary
$start_datetime = $start_date_input . ' 00:00:00';
$end_datetime   = $end_date_input . ' 23:59:59';

// 3. High-Level Summary Metrics
$invoice_stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            COALESCE(SUM(subtotal), 0) AS total_net,
            COALESCE(SUM(vat_amount), 0) AS total_vat,
            COALESCE(SUM(grand_total), 0) AS total_gross,
            COUNT(id) AS total_count,
            SUM(CASE WHEN payment_status = 'Paid' THEN subtotal ELSE 0 END) AS paid_net,
            SUM(CASE WHEN payment_status = 'Paid' THEN vat_amount ELSE 0 END) AS paid_vat,
            SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END) AS paid_gross,
            SUM(CASE WHEN payment_status = 'Unpaid' THEN grand_total ELSE 0 END) AS unpaid_gross
         FROM {$t_inv} 
         WHERE created_at >= %s AND created_at <= %s AND payment_status != 'Cancelled'",
        $start_datetime,
        $end_datetime
    )
);

$paid_net_income   = (float) ( $invoice_stats->paid_net ?? 0.00 );
$paid_vat_accrued  = (float) ( $invoice_stats->paid_vat ?? 0.00 );
$paid_gross_total  = (float) ( $invoice_stats->paid_gross ?? 0.00 );
$unpaid_gross      = (float) ( $invoice_stats->unpaid_gross ?? 0.00 );
$total_invoices    = (int) ( $invoice_stats->total_count ?? 0 );

// Operational Outflows / Expenses
$expense_stats = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT 
            COALESCE(SUM(amount), 0) AS total_net_expense,
            COALESCE(SUM(vat_collected), 0) AS total_input_vat
         FROM {$t_accounts} 
         WHERE type = 'Expense' AND entry_date >= %s AND entry_date <= %s",
        $start_date_input,
        $end_date_input
    )
);

$total_expense     = (float) ( $expense_stats->total_net_expense ?? 0.00 );
$total_input_vat   = (float) ( $expense_stats->total_input_vat ?? 0.00 );
$net_vat_liability = $paid_vat_accrued - $total_input_vat;

$net_profit    = $paid_net_income - $total_expense;
$profit_margin = $paid_net_income > 0 ? round( ( $net_profit / $paid_net_income ) * 100, 1 ) : 0.0;

// 4. Detailed Ledger Query (Invoices)
$invoice_query_sql  = "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.postcode 
                       FROM {$t_inv} i 
                       LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
                       WHERE i.created_at >= %s AND i.created_at <= %s";
$invoice_query_args = array( $start_datetime, $end_datetime );

if ( 'all' !== $status_filter ) {
    $invoice_query_sql   .= ' AND i.payment_status = %s';
    $invoice_query_args[] = ucfirst( $status_filter );
}
$invoice_query_sql .= ' ORDER BY i.id DESC';

$detailed_invoices = $wpdb->get_results( $wpdb->prepare( $invoice_query_sql, $invoice_query_args ) );

// Detailed Expenses Query
$detailed_expenses = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$t_accounts} 
         WHERE type = 'Expense' AND entry_date >= %s AND entry_date <= %s 
         ORDER BY entry_date DESC, id DESC",
        $start_date_input,
        $end_date_input
    )
);

$date_range_label = sprintf( '%s – %s', date_i18n( 'M j, Y', strtotime( $start_date_input ) ), date_i18n( 'M j, Y', strtotime( $end_date_input ) ) );
?>

<div class="vpdl-report-container" id="vpdlReportsModule">

    <!-- Header & Audit Filter Toolbar -->
    <div class="vpdl-report-card" style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-accent); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--vpdl-accent); display: inline-block;"></span>
                <?php esc_html_e( 'Fiscal Performance & Audit Statement', 'vpdl-management' ); ?>
            </div>
            <h2 style="margin: 4px 0 0 0; font-size: 17px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php echo esc_html( $business_name ); ?> <span style="color: var(--vpdl-text-muted); font-weight: 600;"><?php echo esc_html( $date_range_label ); ?></span>
            </h2>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;" class="no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <input type="hidden" name="page" value="vpdl">
                <input type="hidden" name="view" value="reports">
                <input type="hidden" name="sub_tab" value="<?php echo esc_attr( $active_sub_tab ); ?>">
                <input type="hidden" name="status_filter" value="<?php echo esc_attr( $status_filter ); ?>">

                <div class="vpdl-date-picker-box">
                    <span style="font-size: 12px; font-weight: 700; color: var(--vpdl-text-muted);"><?php esc_html_e( 'From', 'vpdl-management' ); ?></span>
                    <input type="date" name="start_date" id="vpdlReportStart" value="<?php echo esc_attr( $start_date_input ); ?>">
                    <span style="font-size: 12px; font-weight: 700; color: var(--vpdl-text-muted);"><?php esc_html_e( 'To', 'vpdl-management' ); ?></span>
                    <input type="date" name="end_date" id="vpdlReportEnd" value="<?php echo esc_attr( $end_date_input ); ?>">
                </div>

                <button type="submit" class="button button-primary" style="height: 36px; border-radius: 8px; font-weight: 700; background: var(--vpdl-accent); border: none; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo vpdl_get_svg( 'check', '', 14 ); ?>
                    <span><?php esc_html_e( 'Apply Filter', 'vpdl-management' ); ?></span>
                </button>
            </form>

            <button type="button" data-action="print-report" style="height: 36px; padding: 0 14px; border-radius: 8px; font-weight: 700; font-size: 12.5px; background: var(--vpdl-card-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main); cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'printer', '', 15 ); ?>
                <span><?php esc_html_e( 'Print Statement', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>

    <!-- 4 Executive KPI Cards -->
    <div class="vpdl-kpi-grid">
        
        <!-- Realized Revenue -->
        <div class="vpdl-report-card vpdl-kpi-item">
            <div class="vpdl-kpi-label">
                <span><?php esc_html_e( 'Realized Revenue (Net)', 'vpdl-management' ); ?></span>
                <span style="color: #10b981;"><?php echo vpdl_get_svg( 'invoice', '', 16 ); ?></span>
            </div>
            <div class="vpdl-kpi-value" style="color: #10b981;">
                <?php echo esc_html( $currency . ' ' . number_format( $paid_net_income, 2 ) ); ?>
            </div>
            <div class="vpdl-kpi-footer">
                <span><?php printf( esc_html__( 'Gross: %s%s', 'vpdl-management' ), esc_html( $currency ), esc_html( number_format( $paid_gross_total, 2 ) ) ); ?></span>
                <span><?php printf( esc_html__( 'Output VAT: %s%s', 'vpdl-management' ), esc_html( $currency ), esc_html( number_format( $paid_vat_accrued, 2 ) ) ); ?></span>
            </div>
        </div>

        <!-- Operational Outflows -->
        <div class="vpdl-report-card vpdl-kpi-item">
            <div class="vpdl-kpi-label">
                <span><?php esc_html_e( 'Operational Outflows (Net)', 'vpdl-management' ); ?></span>
                <span style="color: #ef4444;"><?php echo vpdl_get_svg( 'accounts', '', 16 ); ?></span>
            </div>
            <div class="vpdl-kpi-value" style="color: #ef4444;">
                <?php echo esc_html( $currency . ' ' . number_format( $total_expense, 2 ) ); ?>
            </div>
            <div class="vpdl-kpi-footer">
                <span><?php printf( esc_html__( '%d Vouchers', 'vpdl-management' ), count( $detailed_expenses ) ); ?></span>
                <span><?php printf( esc_html__( 'Input VAT: %s%s', 'vpdl-management' ), esc_html( $currency ), esc_html( number_format( $total_input_vat, 2 ) ) ); ?></span>
            </div>
        </div>

        <!-- Net Operating Margin -->
        <div class="vpdl-report-card vpdl-kpi-item">
            <div class="vpdl-kpi-label">
                <span><?php esc_html_e( 'Net Operating Margin', 'vpdl-management' ); ?></span>
                <span style="color: #0284c7;"><?php echo vpdl_get_svg( 'reports', '', 16 ); ?></span>
            </div>
            <div class="vpdl-kpi-value" style="color: <?php echo ( $net_profit >= 0 ) ? '#0284c7' : '#ef4444'; ?>;">
                <?php echo esc_html( $currency . ' ' . number_format( $net_profit, 2 ) ); ?>
            </div>
            <div class="vpdl-kpi-footer">
                <strong style="color: <?php echo $net_profit >= 0 ? '#10b981' : '#ef4444'; ?>;">
                    <?php echo esc_html( $net_profit >= 0 ? __( 'Net Surplus', 'vpdl-management' ) : __( 'Operating Deficit', 'vpdl-management' ) ); ?>
                </strong>
                <span style="font-weight: 700; font-family: monospace;"><?php echo esc_html( (string) $profit_margin ); ?>% Margin</span>
            </div>
        </div>

        <!-- HMRC VAT Reconciliation Box -->
        <div class="vpdl-report-card vpdl-kpi-item">
            <div class="vpdl-kpi-label">
                <span><?php esc_html_e( 'HMRC VAT Balance (Box 5)', 'vpdl-management' ); ?></span>
                <span style="color: #f59e0b;"><?php echo vpdl_get_svg( 'schemes', '', 16 ); ?></span>
            </div>
            <div class="vpdl-kpi-value" style="color: #f59e0b;">
                <?php echo esc_html( $currency . ' ' . number_format( $net_vat_liability, 2 ) ); ?>
            </div>
            <div class="vpdl-kpi-footer">
                <span>Unsettled Due: <?php echo esc_html( $currency . number_format( $unpaid_gross, 2 ) ); ?></span>
                <span><?php echo ( $net_vat_liability >= 0 ) ? 'Net Payable' : 'Repayment'; ?></span>
            </div>
        </div>

    </div>

    <!-- Navigation Tabs -->
    <div class="vpdl-tab-nav no-print" role="tablist">
        <button type="button" data-action="switch-report-tab" data-tab="income" id="vpdlSubTabBtnIncome" class="vpdl-tab-trigger" style="background: <?php echo ( 'income' === $active_sub_tab ) ? 'var(--vpdl-accent)' : 'transparent'; ?>; color: <?php echo ( 'income' === $active_sub_tab ) ? '#fff' : 'var(--vpdl-text-muted)'; ?>;" role="tab" aria-selected="<?php echo ( 'income' === $active_sub_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'invoice', '', 16 ); ?>
            <span><?php esc_html_e( 'Revenue & Income Statement', 'vpdl-management' ); ?></span>
        </button>
        <button type="button" data-action="switch-report-tab" data-tab="expense" id="vpdlSubTabBtnExpense" class="vpdl-tab-trigger" style="background: <?php echo ( 'expense' === $active_sub_tab ) ? 'var(--vpdl-accent)' : 'transparent'; ?>; color: <?php echo ( 'expense' === $active_sub_tab ) ? '#fff' : 'var(--vpdl-text-muted)'; ?>;" role="tab" aria-selected="<?php echo ( 'expense' === $active_sub_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'accounts', '', 16 ); ?>
            <span><?php esc_html_e( 'Expense & Outflow Ledger', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Invoiced Revenue Statement -->
    <div id="vpdlIncomeTabContent" class="vpdl-tab-pane <?php echo ( 'income' === $active_sub_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-report-card" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Invoiced Revenue Records', 'vpdl-management' ); ?>
                </h3>

                <!-- Status Filter -->
                <div style="display: flex; align-items: center; gap: 8px; font-size: 12.5px;" class="no-print">
                    <span style="color: var(--vpdl-text-muted); font-weight: 600;"><?php esc_html_e( 'Payment Status:', 'vpdl-management' ); ?></span>
                    <select data-action="apply-status-filter" class="vpdl-reports-select" style="padding: 4px 8px; font-size: 12px;">
                        <option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Statuses', 'vpdl-management' ); ?></option>
                        <option value="Paid" <?php selected( $status_filter, 'Paid' ); ?>><?php esc_html_e( 'Paid (Settled)', 'vpdl-management' ); ?></option>
                        <option value="Unpaid" <?php selected( $status_filter, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid (Due)', 'vpdl-management' ); ?></option>
                    </select>
                </div>
            </div>

            <table class="vpdl-report-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Invoice Number', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Client / Property Owner', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Issued By', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Date & Time', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Net (Ex. VAT)', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'VAT Amount', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Grand Total', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $detailed_invoices ) ) : ?>
                        <?php foreach ( $detailed_invoices as $inv ) : 
                            $is_paid      = ( 'Paid' === $inv->payment_status );
                            $status_class = $is_paid ? 'vpdl-badge-paid' : ( 'Unpaid' === $inv->payment_status ? 'vpdl-badge-unpaid' : 'vpdl-badge-cancelled' );
                        ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 800; color: var(--vpdl-accent);">
                                    <?php echo esc_html( $inv->invoice_number ); ?>
                                </td>
                                <td>
                                    <strong style="color: var(--vpdl-text-main);"><?php echo esc_html( $inv->customer_name ?: __( 'Walk-in Client', 'vpdl-management' ) ); ?></strong>
                                    <div style="font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;">
                                        <?php echo esc_html( $inv->customer_phone ); ?>
                                        <?php if ( ! empty( $inv->postcode ) ) : ?>
                                            • <span style="font-family: monospace; color: var(--vpdl-accent);"><?php echo esc_html( $inv->postcode ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="color: var(--vpdl-text-muted); font-size: 12px;">
                                    <?php echo esc_html( $inv->created_by ); ?>
                                </td>
                                <td style="font-family: monospace; font-size: 11.5px; color: var(--vpdl-text-muted);">
                                    <?php echo esc_html( $inv->created_at ); ?>
                                </td>
                                <td style="font-family: monospace; font-weight: 700;">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->subtotal, 2 ) ); ?>
                                </td>
                                <td style="font-family: monospace; color: #f59e0b;">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->vat_amount, 2 ) ); ?>
                                </td>
                                <td>
                                    <span class="vpdl-badge <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( $inv->payment_status ); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; font-family: monospace; font-weight: 800; font-size: 14px; color: <?php echo $is_paid ? '#10b981' : 'var(--vpdl-text-main)'; ?>;">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->grand_total, 2 ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                <?php esc_html_e( 'No invoice records found for this filtered date range.', 'vpdl-management' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Summary Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 24px; padding-top: 16px; margin-top: 16px; border-top: 1.5px solid var(--vpdl-border-color); font-size: 14px;">
                <span><?php esc_html_e( 'Total Realized Revenue (Settled):', 'vpdl-management' ); ?></span>
                <strong style="font-family: monospace; font-size: 18px; color: #10b981;">
                    <?php echo esc_html( $currency . ' ' . number_format( $paid_gross_total, 2 ) ); ?>
                </strong>
            </div>
        </div>
    </div>

    <!-- TAB 2: Project Expenses & Disbursements Statement -->
    <div id="vpdlExpenseTabContent" class="vpdl-tab-pane <?php echo ( 'expense' === $active_sub_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-report-card" style="padding: 24px;">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Disbursements & Operational Outflows Ledger', 'vpdl-management' ); ?>
            </h3>

            <table class="vpdl-report-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Expense / Payee Description', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Reference ID', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Voucher Date', 'vpdl-management' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Claimable Input VAT', 'vpdl-management' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Disbursement (Ex. VAT)', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $detailed_expenses ) ) : ?>
                        <?php foreach ( $detailed_expenses as $exp ) : ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--vpdl-text-main);">
                                    <?php echo esc_html( $exp->title ); ?>
                                </td>
                                <td style="font-family: monospace; color: var(--vpdl-text-muted);">
                                    <?php echo esc_html( $exp->reference_id ?: '—' ); ?>
                                </td>
                                <td style="font-family: monospace; color: var(--vpdl-text-muted);">
                                    <?php echo esc_html( $exp->entry_date ); ?>
                                </td>
                                <td style="text-align: right; font-family: monospace; color: #f59e0b;">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $exp->vat_collected, 2 ) ); ?>
                                </td>
                                <td style="text-align: right; font-family: monospace; font-weight: 800; font-size: 14px; color: #ef4444;">
                                    -<?php echo esc_html( $currency . ' ' . number_format( (float) $exp->amount, 2 ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                <?php esc_html_e( 'No expense disbursements logged for this date range.', 'vpdl-management' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Summary Footer -->
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 24px; padding-top: 16px; margin-top: 16px; border-top: 1.5px solid var(--vpdl-border-color); font-size: 14px;">
                <span><?php esc_html_e( 'Total Operational Outflows:', 'vpdl-management' ); ?></span>
                <strong style="font-family: monospace; font-size: 18px; color: #ef4444;">
                    -<?php echo esc_html( $currency . ' ' . number_format( $total_expense, 2 ) ); ?>
                </strong>
            </div>
        </div>
    </div>

</div>