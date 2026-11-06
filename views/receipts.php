<?php
/**
 * View Partial: Client Paid Invoices & Official Payment Receipts
 * File: views/receipts.php
 * 
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_view_receipts' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;
$t_receipts = $wpdb->prefix . 'vpdl_receipts';
$t_inv      = $wpdb->prefix . 'vpdl_invoices';
$t_cust     = $wpdb->prefix . 'vpdl_customers';

$business_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$reg_no        = (string) get_option( 'vpdl_reg_no', '08456916' );
$vat_number    = (string) get_option( 'vpdl_vat_number', '' );
$currency      = (string) get_option( 'vpdl_currency', '£' );

$receipts = $wpdb->get_results(
    "SELECT r.*, i.invoice_number, i.subtotal, i.vat_amount, i.grand_total, i.items_json,
            c.name AS client_name, c.email AS client_email, c.phone AS client_phone, c.site_address, c.postcode
     FROM {$t_receipts} r
     LEFT JOIN {$t_inv} i ON r.invoice_id = i.id
     LEFT JOIN {$t_cust} c ON r.customer_id = c.id
     ORDER BY r.paid_date DESC, r.id DESC"
);

$total_receipts = is_array( $receipts ) ? count( $receipts ) : 0;

// Calculate Totals
$total_cleared = 0.00;
if ( ! empty( $receipts ) ) {
    foreach ( $receipts as $r ) {
        $total_cleared += (float) $r->amount_paid;
    }
}
$statutory_vat = ( $total_cleared * 20 ) / 120;
?>

<div class="vpdl-receipts-wrap" id="vpdlReceiptsModule">

    <!-- Header with Export CTA -->
    <div class="vpdl-receipts-header">
        <div>
            <h2 class="vpdl-receipts-title">
                <?php esc_html_e( 'Official Client Payment Receipts', 'vpdl-management' ); ?>
            </h2>
            <p class="vpdl-receipts-subtitle">
                <?php esc_html_e( 'Real-time ledger of cleared client invoices, verified BACS receipts, and claimable VAT statements.', 'vpdl-management' ); ?>
            </p>
        </div>

        <div>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'view' => 'receipts', 'export_ledger_csv' => '1' ), admin_url( 'admin.php?page=vpdl' ) ), 'vpdl_export_ledger' ) ); ?>" class="button button-primary vpdl-receipts-btn-export">
                <?php echo vpdl_get_svg( 'download', '', 14 ); ?>
                <span><?php esc_html_e( 'Export Bank Ledger (CSV)', 'vpdl-management' ); ?></span>
            </a>
        </div>
    </div>

    <!-- Quick Stat Ribbon -->
    <div class="vpdl-receipts-stats-grid">
        <div class="vpdl-panel vpdl-receipts-stat-card">
            <div class="vpdl-receipts-stat-label">
                <?php esc_html_e( 'Total Paid Receipts', 'vpdl-management' ); ?>
            </div>
            <div class="vpdl-receipts-stat-val vpdl-stat-val-main">
                <?php echo esc_html( (string) $total_receipts ); ?>
            </div>
        </div>

        <div class="vpdl-panel vpdl-receipts-stat-card">
            <div class="vpdl-receipts-stat-label">
                <?php esc_html_e( 'Total Cleared Settlement', 'vpdl-management' ); ?>
            </div>
            <div class="vpdl-receipts-stat-val vpdl-stat-val-green">
                <?php echo esc_html( $currency . ' ' . number_format( $total_cleared, 2 ) ); ?>
            </div>
        </div>

        <div class="vpdl-panel vpdl-receipts-stat-card">
            <div class="vpdl-receipts-stat-label">
                <?php esc_html_e( 'Statutory VAT Remittance (20%)', 'vpdl-management' ); ?>
            </div>
            <div class="vpdl-receipts-stat-val vpdl-stat-val-accent">
                <?php echo esc_html( $currency . ' ' . number_format( $statutory_vat, 2 ) ); ?>
            </div>
        </div>
    </div>

    <!-- Receipts Table Panel -->
    <div class="vpdl-panel vpdl-receipts-table-panel">
        <div class="vpdl-receipts-toolbar">
            <input type="text" id="vpdlReceiptsSearch" class="vpdl-receipts-search" placeholder="<?php esc_attr_e( 'Search by receipt #, invoice #, client, or method...', 'vpdl-management' ); ?>">
            <span id="vpdlReceiptsCountDisplay" class="vpdl-receipts-count-display">
                <?php printf( esc_html__( 'Showing %d cleared receipts', 'vpdl-management' ), absint( $total_receipts ) ); ?>
            </span>
        </div>

        <div class="vpdl-receipts-table-responsive">
            <table class="vpdl-receipts-table" id="vpdlReceiptsTable">
                <thead class="vpdl-receipts-thead">
                    <tr>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Receipt #', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Invoice Ref', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Client / Payee', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Cleared Amount', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Payment Method', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th"><?php esc_html_e( 'Settlement Date', 'vpdl-management' ); ?></th>
                        <th class="vpdl-receipts-th vpdl-receipts-th-right"><?php esc_html_e( 'Action', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $receipts ) ) : ?>
                        <tr>
                            <td colspan="7" class="vpdl-receipts-empty">
                                <?php esc_html_e( 'No cleared payment receipts found. Invoices marked as "Paid" will auto-generate receipts here.', 'vpdl-management' ); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $receipts as $r ) : 
                            $public_url = home_url( '/?vpdl_view=receipt&token=' . $r->access_token );
                            $receipt_payload = array(
                                'receipt_number'    => (string) $r->receipt_number,
                                'invoice_number'    => (string) $r->invoice_number,
                                'client_name'       => (string) ( $r->client_name ?: __( 'Valued Client', 'vpdl-management' ) ),
                                'client_phone'      => (string) ( $r->client_phone ?: '—' ),
                                'client_email'      => (string) ( $r->client_email ?: '—' ),
                                'site_address'      => (string) ( $r->site_address ?: '—' ),
                                'postcode'          => (string) ( $r->postcode ?: '' ),
                                'payment_method'    => (string) $r->payment_method,
                                'payment_reference' => (string) ( $r->payment_reference ?: $r->invoice_number ),
                                'paid_date'         => date_i18n( 'd F Y', strtotime( $r->paid_date ) ),
                                'amount_paid'       => $currency . ' ' . number_format( (float) $r->amount_paid, 2 ),
                                'subtotal'          => $currency . ' ' . number_format( (float) $r->subtotal, 2 ),
                                'vat_amount'        => $currency . ' ' . number_format( (float) $r->vat_amount, 2 ),
                                'public_url'        => $public_url,
                            );
                        ?>
                            <tr class="vpdl-receipts-row">
                                <td class="vpdl-receipts-td vpdl-receipts-num">
                                    <?php echo esc_html( $r->receipt_number ); ?>
                                </td>
                                <td class="vpdl-receipts-td vpdl-receipts-inv-ref">
                                    <?php echo esc_html( $r->invoice_number ); ?>
                                </td>
                                <td class="vpdl-receipts-td">
                                    <strong class="vpdl-receipts-client-title"><?php echo esc_html( $r->client_name ); ?></strong>
                                    <small class="vpdl-receipts-client-sub"><?php echo esc_html( $r->client_email ); ?></small>
                                </td>
                                <td class="vpdl-receipts-td vpdl-receipts-amt">
                                    <?php echo esc_html( $currency . ' ' . number_format( (float) $r->amount_paid, 2 ) ); ?>
                                </td>
                                <td class="vpdl-receipts-td">
                                    <span class="vpdl-receipts-method-tag">
                                        <?php echo esc_html( $r->payment_method ); ?>
                                    </span>
                                </td>
                                <td class="vpdl-receipts-td vpdl-receipts-date">
                                    <?php echo esc_html( date_i18n( 'd M Y', strtotime( $r->paid_date ) ) ); ?>
                                </td>
                                <td class="vpdl-receipts-td-right">
                                    <button type="button" data-action="view-receipt-modal" data-receipt="<?php echo esc_attr( wp_json_encode( $receipt_payload ) ); ?>" class="vpdl-receipts-action-btn">
                                        <?php echo vpdl_get_svg( 'printer', '', 13 ); ?>
                                        <span><?php esc_html_e( 'View Receipt', 'vpdl-management' ); ?></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Official Payment Receipt Dossier Modal -->
<div id="vpdlReceiptModal" role="dialog" aria-modal="true" aria-labelledby="receiptModalTitle">
    <div class="vpdl-modal-receipt-card">
        
        <div id="vpdlReceiptPrintableArea">
            <!-- Header -->
            <div class="vpdl-modal-receipt-header">
                <div>
                    <h2 class="vpdl-modal-bizname"><?php echo esc_html( $business_name ); ?></h2>
                    <div class="vpdl-modal-bizmeta">
                        <?php esc_html_e( 'CRN:', 'vpdl-management' ); ?> <?php echo esc_html( $reg_no ); ?> 
                        <?php if ( ! empty( $vat_number ) ) : ?> | <?php esc_html_e( 'VAT Reg:', 'vpdl-management' ); ?> <?php echo esc_html( $vat_number ); ?><?php endif; ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <h1 id="receiptModalTitle" class="vpdl-modal-doc-title"><?php esc_html_e( 'PAYMENT RECEIPT', 'vpdl-management' ); ?></h1>
                    <div id="modalReceiptNum" class="vpdl-modal-doc-ref"></div>
                    <span class="vpdl-modal-badge-settled"><?php esc_html_e( 'Cleared & Reconciled', 'vpdl-management' ); ?></span>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="vpdl-modal-details-grid">
                <div>
                    <strong class="vpdl-modal-details-title"><?php esc_html_e( 'Payer / Client Details', 'vpdl-management' ); ?></strong>
                    <div id="modalClientName" style="font-weight: 800; color: #0f172a;"></div>
                    <div id="modalClientAddress" style="color: #475569;"></div>
                    <div id="modalClientContact" style="font-family: monospace; font-size: 11.5px; color: #64748b; margin-top: 2px;"></div>
                </div>
                <div>
                    <strong class="vpdl-modal-details-title"><?php esc_html_e( 'Settlement Particulars', 'vpdl-management' ); ?></strong>
                    <div><?php esc_html_e( 'Invoice Ref:', 'vpdl-management' ); ?> <strong id="modalInvoiceRef" style="font-family: monospace;"></strong></div>
                    <div><?php esc_html_e( 'Payment Method:', 'vpdl-management' ); ?> <strong id="modalPaymentMethod"></strong></div>
                    <div><?php esc_html_e( 'Payment Ref:', 'vpdl-management' ); ?> <strong id="modalPaymentRef" style="font-family: monospace;"></strong></div>
                    <div><?php esc_html_e( 'Date Settled:', 'vpdl-management' ); ?> <strong id="modalPaidDate"></strong></div>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="vpdl-modal-receipt-summary">
                <div class="vpdl-modal-summary-line">
                    <span><?php esc_html_e( 'Invoice Net Equivalent:', 'vpdl-management' ); ?></span>
                    <strong id="modalReceiptSubtotal" style="font-family: monospace;"></strong>
                </div>
                <div class="vpdl-modal-summary-line">
                    <span><?php esc_html_e( 'Statutory VAT Content (20%):', 'vpdl-management' ); ?></span>
                    <strong id="modalReceiptVat" style="font-family: monospace; color: #f59e0b;"></strong>
                </div>
                <div class="vpdl-modal-summary-total">
                    <span><?php esc_html_e( 'Total Payment Cleared:', 'vpdl-management' ); ?></span>
                    <strong id="modalReceiptTotal" style="font-family: monospace;"></strong>
                </div>
            </div>

            <!-- Legal Disclaimer -->
            <div style="font-size: 11px; color: #64748b; line-height: 1.5; border-top: 1px dashed #cbd5e1; paddingTop: 12px;">
                <?php printf( esc_html__( 'This receipt confirms official full settlement of the referenced client invoice. Retain for taxation and accounting records under UK HMRC MTD guidelines. %s.', 'vpdl-management' ), esc_html( $business_name ) ); ?>
            </div>
        </div>

        <div class="vpdl-modal-footer no-print">
            <button type="button" data-action="close-receipt-modal" class="vpdl-btn-modal-close">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Close', 'vpdl-management' ); ?></span>
            </button>
            <button type="button" data-action="print-receipt" class="vpdl-btn-modal-print">
                <?php echo vpdl_get_svg( 'printer', '', 16 ); ?>
                <span><?php esc_html_e( 'Print Official Receipt', 'vpdl-management' ); ?></span>
            </button>
        </div>

    </div>
</div>