<?php
/**
 * View: VPDL Official Invoicing Register & Billing Engine
 * File: views/invoices.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_manage_invoices' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_cust     = $wpdb->prefix . 'vpdl_customers';
$t_inv      = $wpdb->prefix . 'vpdl_invoices';
$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
$t_services = $wpdb->prefix . 'vpdl_services';
$t_receipts = $wpdb->prefix . 'vpdl_receipts';
$t_accounts = $wpdb->prefix . 'vpdl_accounts';

$currency         = (string) get_option( 'vpdl_currency', '£' );
$default_vat_rate = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url         = admin_url( 'admin.php?page=vpdl&view=invoices' );
$active_tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'list';
$status_filter    = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : 'all';
$is_admin         = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );
$msg_code         = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Direct Invoice Creation Handler
if ( isset( $_POST['vpdl_action'] ) && 'create_direct_invoice' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $customer_id    = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    $payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : 'Unpaid';
    $vat_treatment  = isset( $_POST['vat_treatment'] ) ? floatval( $_POST['vat_treatment'] ) : $default_vat_rate;
    $raw_items      = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();

    $clean_items = function_exists( 'vpdl_sanitize_items_payload' ) ? vpdl_sanitize_items_payload( $raw_items ) : array();
    $subtotal    = 0.00;

    foreach ( $clean_items as $ci ) {
        $subtotal += (float) ( $ci['total'] ?? ( $ci['unit_price'] ?? 0.00 ) );
    }

    $vat_amount  = ( $subtotal * $vat_treatment ) / 100;
    $grand_total = $subtotal + $vat_amount;
    $inv_num     = 'VPDL-INV-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
    $token       = wp_generate_password( 32, false, false );

    if ( $customer_id > 0 && $subtotal > 0 ) {
        $wpdb->insert(
            $t_inv,
            array(
                'invoice_number' => $inv_num,
                'quote_id'       => null,
                'customer_id'    => $customer_id,
                'items_json'     => wp_json_encode( $clean_items ),
                'subtotal'       => $subtotal,
                'vat_amount'     => $vat_amount,
                'grand_total'    => $grand_total,
                'payment_status' => $payment_status,
                'access_token'   => $token,
                'created_by'     => wp_get_current_user()->display_name,
            ),
            array( '%s', '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
        );
        $new_inv_id = $wpdb->insert_id;

        if ( 'Paid' === $payment_status && $new_inv_id ) {
            $rec_num   = 'VPDL-REC-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
            $rec_token = wp_generate_password( 32, false, false );

            $wpdb->insert(
                $t_receipts,
                array(
                    'receipt_number'    => $rec_num,
                    'invoice_id'        => $new_inv_id,
                    'customer_id'       => $customer_id,
                    'amount_paid'       => $grand_total,
                    'payment_method'    => 'Bank Transfer',
                    'payment_reference' => 'Direct Settlement ' . $inv_num,
                    'paid_date'         => current_time( 'Y-m-d' ),
                    'access_token'      => $rec_token,
                ),
                array( '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s' )
            );

            $wpdb->insert(
                $t_accounts,
                array(
                    'title'         => 'Payment for Invoice ' . $inv_num,
                    'type'          => 'Income',
                    'amount'        => $grand_total,
                    'vat_collected' => $vat_amount,
                    'reference_id'  => $inv_num,
                    'entry_date'    => current_time( 'Y-m-d' ),
                ),
                array( '%s', '%s', '%f', '%f', '%s', '%s' )
            );
        }

        wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'msg' => 'invoice_created' ), $base_url ) );
        exit;
    }
}

// 2. Single Invoice Delete Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_invoice' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_inv, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'msg' => 'invoice_deleted' ), $base_url ) );
        exit;
    }
}

// 3. Bulk Invoice Actions Handler
if ( isset( $_POST['vpdl_action'] ) && 'bulk_invoices_action' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
    $raw_ids     = isset( $_POST['invoice_ids'] ) && is_array( $_POST['invoice_ids'] ) ? array_map( 'absint', $_POST['invoice_ids'] ) : array();

    if ( ! empty( $raw_ids ) && ! empty( $bulk_action ) ) {
        if ( 'delete' === $bulk_action ) {
            if ( ! $is_admin ) {
                wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
            }
            foreach ( $raw_ids as $id ) {
                $wpdb->delete( $t_inv, array( 'id' => $id ), array( '%d' ) );
            }
            wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'msg' => 'bulk_deleted' ), $base_url ) );
            exit;
        } elseif ( in_array( $bulk_action, array( 'mark_paid', 'mark_unpaid' ), true ) ) {
            $target_status = 'mark_paid' === $bulk_action ? 'Paid' : 'Unpaid';
            foreach ( $raw_ids as $id ) {
                $wpdb->update( $t_inv, array( 'payment_status' => $target_status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
            }
            wp_safe_redirect( add_query_arg( array( 'tab' => 'list', 'msg' => 'bulk_updated' ), $base_url ) );
            exit;
        }
    }
}

$customers = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode FROM {$t_cust} ORDER BY name ASC" );

// Query Invoices with Filtering
$where_clause = '';
if ( 'paid' === $status_filter ) {
    $where_clause = " WHERE i.payment_status = 'Paid'";
} elseif ( 'unpaid' === $status_filter ) {
    $where_clause = " WHERE i.payment_status = 'Unpaid'";
} elseif ( 'cancelled' === $status_filter ) {
    $where_clause = " WHERE i.payment_status = 'Cancelled'";
}

$invoices = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.site_address, c.postcode, c.council_name 
     FROM {$t_inv} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id
     {$where_clause}
     ORDER BY i.id DESC"
);

$total_invoices_count = is_array( $invoices ) ? count( $invoices ) : 0;
$paid_gross_sum       = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0) FROM {$t_inv} WHERE payment_status = 'Paid'" );
$unpaid_gross_sum     = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0) FROM {$t_inv} WHERE payment_status = 'Unpaid'" );

$ready_quotes = $wpdb->get_results(
    "SELECT q.id, q.quote_number, q.grand_total, c.name AS customer_name 
     FROM {$t_quotes} q 
     LEFT JOIN {$t_cust} c ON q.customer_id = c.id 
     WHERE q.status != 'Converted' 
     ORDER BY q.id DESC 
     LIMIT 5"
);

// Flash Notifications Mapping
$alerts = array(
    'invoice_created' => array( 'msg' => __( 'Invoice generated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'invoice_deleted' => array( 'msg' => __( 'Invoice deleted permanently.', 'vpdl-management' ), 'type' => 'success' ),
    'bulk_updated'    => array( 'msg' => __( 'Selected invoices updated.', 'vpdl-management' ), 'type' => 'success' ),
    'bulk_deleted'    => array( 'msg' => __( 'Selected invoices deleted.', 'vpdl-management' ), 'type' => 'success' ),
);
?>

<div class="vpdl-invoices-wrapper" id="vpdlInvoicesModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-inv-alert vpdl-inv-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-panel vpdl-inv-header">
        <div class="vpdl-inv-header-left">
            <div class="vpdl-inv-header-icon">
                <?php echo vpdl_get_svg( 'invoice', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-inv-header-title"><?php esc_html_e( 'Invoices & Fiscal Ledger', 'vpdl-management' ); ?></h2>
                <div class="vpdl-inv-header-subtitle">
                    <?php esc_html_e( 'Official Client Billing, VAT Ledger & Shareable Customer Portals', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <span class="vpdl-inv-pill-settled">
                <?php esc_html_e( 'Settled:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . ' ' . number_format( $paid_gross_sum, 2 ) ); ?>
            </span>
            <span class="vpdl-inv-pill-due">
                <?php esc_html_e( 'Due:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . ' ' . number_format( $unpaid_gross_sum, 2 ) ); ?>
            </span>
        </div>
    </div>

    <!-- Quote Conversion Banner -->
    <?php if ( ! empty( $ready_quotes ) ) : ?>
        <div class="vpdl-panel" style="padding: 16px 20px; border-radius: 10px; background: rgba(2, 132, 199, 0.04); border: 1.5px dashed var(--vpdl-accent);">
            <div style="font-size: 13px; font-weight: 800; color: var(--vpdl-text-main); margin-bottom: 8px;">
                <?php esc_html_e( 'Ready Proposals for 1-Click Invoice Conversion:', 'vpdl-management' ); ?>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php
                $conv_nonce = wp_create_nonce( 'vpdl_quote_convert_nonce' );
                foreach ( $ready_quotes as $rq ) :
                    $conv_url = add_query_arg(
                        array(
                            'view'     => 'quotes',
                            'action'   => 'convert_invoice',
                            'quote_id' => $rq->id,
                            '_wpnonce' => $conv_nonce,
                        ),
                        admin_url( 'admin.php?page=vpdl' )
                    );
                ?>
                    <div style="background: var(--vpdl-card-bg); border: 1px solid var(--vpdl-border-color); border-radius: 6px; padding: 6px 12px; font-size: 12px; display: flex; align-items: center; gap: 10px;">
                        <span><strong><?php echo esc_html( $rq->customer_name ); ?></strong> (<?php echo esc_html( $rq->quote_number ); ?>) • <span style="color:#10b981; font-weight:800;"><?php echo esc_html( $currency . number_format( (float) $rq->grand_total, 2 ) ); ?></span></span>
                        <a href="<?php echo esc_url( $conv_url ); ?>" data-action="confirm-convert" style="color: var(--vpdl-accent); font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span><?php esc_html_e( 'Convert', 'vpdl-management' ); ?></span>
                            <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sub-Tabs Navigation & Quick Status Filters -->
    <div class="vpdl-inv-tab-nav">
        <div style="display: flex; gap: 8px;">
            <button type="button" data-action="switch-tab" data-tab="list" id="vpdlInvTabBtnList" class="vpdl-inv-tab-btn <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>">
                <?php echo vpdl_get_svg( 'invoice', '', 18 ); ?>
                <span><?php esc_html_e( 'Master Invoice Directory', 'vpdl-management' ); ?> (<?php echo esc_html( (string) $total_invoices_count ); ?>)</span>
            </button>

            <button type="button" data-action="switch-tab" data-tab="create" id="vpdlInvTabBtnCreate" class="vpdl-inv-tab-btn <?php echo ( 'create' === $active_tab ) ? 'is-active' : ''; ?>">
                <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                <span><?php esc_html_e( 'Create Direct Invoice', 'vpdl-management' ); ?></span>
            </button>
        </div>

        <!-- Quick Status Filter Pills -->
        <div style="display: flex; gap: 6px; align-items: center; font-size: 12px;">
            <span style="color: var(--vpdl-text-muted); font-weight: 700;"><?php esc_html_e( 'Filter:', 'vpdl-management' ); ?></span>
            <a href="<?php echo esc_url( add_query_arg( 'status_filter', 'all', $base_url ) ); ?>" class="vpdl-inv-filter-pill" style="background: <?php echo 'all' === $status_filter ? 'var(--vpdl-accent)' : 'rgba(0,0,0,0.04)'; ?>; color: <?php echo 'all' === $status_filter ? '#fff' : 'var(--vpdl-text-main)'; ?>;"><?php esc_html_e( 'All', 'vpdl-management' ); ?></a>
            <a href="<?php echo esc_url( add_query_arg( 'status_filter', 'unpaid', $base_url ) ); ?>" class="vpdl-inv-filter-pill" style="background: <?php echo 'unpaid' === $status_filter ? '#ef4444' : 'rgba(0,0,0,0.04)'; ?>; color: <?php echo 'unpaid' === $status_filter ? '#fff' : 'var(--vpdl-text-main)'; ?>;"><?php esc_html_e( 'Unpaid', 'vpdl-management' ); ?></a>
            <a href="<?php echo esc_url( add_query_arg( 'status_filter', 'paid', $base_url ) ); ?>" class="vpdl-inv-filter-pill" style="background: <?php echo 'paid' === $status_filter ? '#10b981' : 'rgba(0,0,0,0.04)'; ?>; color: <?php echo 'paid' === $status_filter ? '#fff' : 'var(--vpdl-text-main)'; ?>;"><?php esc_html_e( 'Paid', 'vpdl-management' ); ?></a>
        </div>
    </div>

    <!-- TAB 1: Directory Table -->
    <div id="vpdlInvTabPaneList" class="vpdl-inv-tab-pane <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-panel vpdl-inv-table-wrap">
            <div style="margin-bottom: 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <input type="text" id="vpdlInvoiceSearch" placeholder="<?php esc_attr_e( 'Search by invoice number, customer name, postcode, or phone...', 'vpdl-management' ); ?>" class="vpdl-inv-search-input">
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" id="vpdlBulkInvoiceForm">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="bulk_invoices_action">

                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 14px;">
                    <select name="bulk_action" class="vpdl-inv-select" style="width: auto; padding: 6px 10px; font-size: 12px; font-weight: 700;">
                        <option value=""><?php esc_html_e( 'Bulk Actions...', 'vpdl-management' ); ?></option>
                        <option value="mark_paid"><?php esc_html_e( 'Mark as Paid', 'vpdl-management' ); ?></option>
                        <option value="mark_unpaid"><?php esc_html_e( 'Mark as Unpaid', 'vpdl-management' ); ?></option>
                        <?php if ( $is_admin ) : ?>
                            <option value="delete"><?php esc_html_e( 'Delete Selected', 'vpdl-management' ); ?></option>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="button" style="font-size: 12px; font-weight: 700; height: 32px; border-radius: 6px; background: var(--vpdl-card-bg); border: 1px solid var(--vpdl-border-color); cursor: pointer;">
                        <?php esc_html_e( 'Apply Bulk', 'vpdl-management' ); ?>
                    </button>
                </div>

                <div class="vpdl-inv-table-responsive">
                    <table class="vpdl-inv-table" id="vpdlInvoicesTable">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="vpdlSelectAllInv"></th>
                                <th><?php esc_html_e( 'Invoice Number', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Client / Property Site', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Net Amount', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'VAT Amount', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Grand Total', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Customer Share Link', 'vpdl-management' ); ?></th>
                                <th class="vpdl-th-right"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $invoices ) ) : ?>
                                <?php foreach ( $invoices as $inv ) : 
                                    $public_url = home_url( '/?vpdl_view=invoice&token=' . $inv->access_token );
                                    $is_paid    = ( 'Paid' === $inv->payment_status );
                                    $status_cls = $is_paid ? 'vpdl-inv-status-paid' : ( 'Cancelled' === $inv->payment_status ? 'vpdl-inv-status-cancelled' : 'vpdl-inv-status-unpaid' );
                                ?>
                                    <tr class="vpdl-invoice-row">
                                        <td>
                                            <input type="checkbox" name="invoice_ids[]" value="<?php echo esc_attr( (string) $inv->id ); ?>" class="vpdl-inv-checkbox">
                                        </td>
                                        <td class="vpdl-inv-num-cell">
                                            <?php echo esc_html( $inv->invoice_number ); ?>
                                        </td>
                                        <td>
                                            <strong class="vpdl-inv-client-title"><?php echo esc_html( $inv->customer_name ?: __( 'Prospective Lead', 'vpdl-management' ) ); ?></strong>
                                            <div class="vpdl-inv-client-sub">
                                                <?php echo esc_html( wp_trim_words( $inv->site_address ?: $inv->customer_phone, 4 ) ); ?>
                                                <?php if ( ! empty( $inv->postcode ) ) : ?>
                                                    • <span style="font-family: monospace; color: var(--vpdl-accent);"><?php echo esc_html( $inv->postcode ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="vpdl-inv-mono-cell">
                                            <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->subtotal, 2 ) ); ?>
                                        </td>
                                        <td class="vpdl-inv-mono-cell" style="color: #f59e0b;">
                                            <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->vat_amount, 2 ) ); ?>
                                        </td>
                                        <td class="vpdl-inv-gross-cell">
                                            <?php echo esc_html( $currency . ' ' . number_format( (float) $inv->grand_total, 2 ) ); ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="update_payment_status">
                                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr( (string) $inv->id ); ?>">
                                                <select name="payment_status" data-action="auto-submit" class="vpdl-inv-status-select <?php echo esc_attr( $status_cls ); ?>">
                                                    <option value="Unpaid" <?php selected( $inv->payment_status, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'vpdl-management' ); ?></option>
                                                    <option value="Paid" <?php selected( $inv->payment_status, 'Paid' ); ?>><?php esc_html_e( 'Paid', 'vpdl-management' ); ?></option>
                                                    <option value="Cancelled" <?php selected( $inv->payment_status, 'Cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'vpdl-management' ); ?></option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px; align-items: center;">
                                                <input type="text" readonly value="<?php echo esc_url( $public_url ); ?>" class="vpdl-inv-link-input">
                                                <button type="button" data-action="copy-url" data-url="<?php echo esc_url( $public_url ); ?>" class="vpdl-inv-btn-copy">
                                                    <?php echo vpdl_get_svg( 'copy', '', 12 ); ?>
                                                    <span><?php esc_html_e( 'Copy', 'vpdl-management' ); ?></span>
                                                </button>
                                            </div>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <button type="button" data-action="open-print-modal" data-id="<?php echo esc_attr( (string) $inv->id ); ?>" class="vpdl-inv-action-btn vpdl-btn-view-inv">
                                                <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                                <span><?php esc_html_e( 'View / Print', 'vpdl-management' ); ?></span>
                                            </button>

                                            <?php if ( $is_admin ) : ?>
                                                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-ref="<?php echo esc_attr( $inv->invoice_number ); ?>">
                                                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                    <input type="hidden" name="vpdl_action" value="delete_invoice">
                                                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr( (string) $inv->id ); ?>">
                                                    <button type="submit" class="vpdl-inv-action-btn vpdl-btn-delete-inv">
                                                        <?php echo vpdl_get_svg( 'trash', '', 13 ); ?>
                                                        <span><?php esc_html_e( 'Delete', 'vpdl-management' ); ?></span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: var(--vpdl-text-muted); padding: 50px 0;">
                                        <?php esc_html_e( 'No client invoices issued yet. Click "+ Create Direct Invoice" to get started.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: Create Form -->
    <div id="vpdlInvTabPaneCreate" class="vpdl-inv-tab-pane <?php echo ( 'create' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-panel vpdl-inv-form-card">
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Issue Direct Professional VAT Invoice', 'vpdl-management' ); ?>
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                    <?php esc_html_e( 'Generate statutory UK VAT invoices with multi-line items, custom tax points, and payment terms.', 'vpdl-management' ); ?>
                </p>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="create_direct_invoice">

                <div class="vpdl-inv-grid-2">
                    <div>
                        <label for="vpdl_target_customer" style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Target Customer', 'vpdl-management' ); ?> *</label>
                        <select name="customer_id" id="vpdl_target_customer" required class="vpdl-inv-select">
                            <option value=""><?php esc_html_e( '-- Select Client --', 'vpdl-management' ); ?></option>
                            <?php foreach ( $customers as $c ) : ?>
                                <option value="<?php echo esc_attr( (string) $c->id ); ?>">
                                    <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="vpdl_payment_status" style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Payment Status', 'vpdl-management' ); ?></label>
                        <select name="payment_status" id="vpdl_payment_status" class="vpdl-inv-select">
                            <option value="Unpaid"><?php esc_html_e( 'Unpaid / Pending Clearance', 'vpdl-management' ); ?></option>
                            <option value="Paid"><?php esc_html_e( 'Paid (Settled in Full)', 'vpdl-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="vpdl-inv-grid-4">
                    <div>
                        <label for="vpdl_invoice_date" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Tax Point / Date', 'vpdl-management' ); ?> *</label>
                        <input type="date" name="invoice_date" id="vpdl_invoice_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required class="vpdl-inv-input">
                    </div>
                    <div>
                        <label for="vpdl_payment_terms" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Payment Terms', 'vpdl-management' ); ?></label>
                        <select name="payment_terms" id="vpdl_payment_terms" class="vpdl-inv-select">
                            <option value="14 Days Net" selected><?php esc_html_e( '14 Days Net', 'vpdl-management' ); ?></option>
                            <option value="Due on Receipt"><?php esc_html_e( 'Due on Receipt', 'vpdl-management' ); ?></option>
                            <option value="7 Days Net"><?php esc_html_e( '7 Days Net', 'vpdl-management' ); ?></option>
                            <option value="30 Days Net"><?php esc_html_e( '30 Days Net', 'vpdl-management' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="vpdlVatTreatment" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'VAT Treatment', 'vpdl-management' ); ?></label>
                        <select name="vat_treatment" id="vpdlVatTreatment" class="vpdl-inv-select">
                            <option value="20.00" selected><?php esc_html_e( 'Standard Rate (20%)', 'vpdl-management' ); ?></option>
                            <option value="5.00"><?php esc_html_e( 'Reduced Rate (5%)', 'vpdl-management' ); ?></option>
                            <option value="0.00"><?php esc_html_e( 'Zero Rated (0%)', 'vpdl-management' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="vpdl_po_number" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'PO / Client Ref (Opt)', 'vpdl-management' ); ?></label>
                        <input type="text" name="po_number" id="vpdl_po_number" placeholder="<?php esc_attr_e( 'e.g. PO-9801', 'vpdl-management' ); ?>" class="vpdl-inv-input" style="font-family: monospace;">
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 13px; font-weight: 800; margin-bottom: 8px; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Invoiced Items & Services Breakdown', 'vpdl-management' ); ?> *
                    </label>
                    
                    <div id="vpdlInvoiceItemsContainer" style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;">
                        <div class="vpdl-inv-item-row" data-index="0">
                            <input type="text" name="items[0][description]" placeholder="<?php esc_attr_e( 'e.g. Planning Design Architectural Drawing Package (PDM 01)', 'vpdl-management' ); ?>" required class="vpdl-inv-input" style="flex: 3;">
                            <input type="number" step="0.01" min="0" name="items[0][unit_price]" class="vpdl-inv-input vpdl-item-price-input" placeholder="<?php esc_attr_e( 'Net Price (£)', 'vpdl-management' ); ?>" required style="flex: 1; font-family: monospace;">
                            <input type="hidden" name="items[0][quantity]" value="1">
                            <input type="hidden" name="items[0][total]" class="vpdl-hidden-line-total" value="0.00">
                            <button type="button" data-action="remove-row" class="vpdl-btn-remove-row">
                                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                            </button>
                        </div>
                    </div>

                    <button type="button" id="vpdlAddInvoiceRowBtn" style="font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; background: var(--vpdl-badge-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main); cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                        <span><?php esc_html_e( 'Add Another Item / Service Line', 'vpdl-management' ); ?></span>
                    </button>
                </div>

                <div style="background: rgba(255, 255, 255, 0.03); border: 1.5px solid var(--vpdl-border-color); border-radius: 10px; padding: 16px; margin-bottom: 24px; font-size: 13.5px; line-height: 1.8;">
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php esc_html_e( 'Net Amount (Excl. VAT):', 'vpdl-management' ); ?></span>
                        <strong style="font-family: monospace; font-size: 15px;"><?php echo esc_html( $currency ); ?><span id="dispDirectNet">0.00</span></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><?php esc_html_e( 'Computed VAT Amount:', 'vpdl-management' ); ?></span>
                        <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispDirectVat">0.00</span></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1.5px solid var(--vpdl-border-color); padding-top: 8px; margin-top: 6px; color: #10b981; font-size: 16px;">
                        <span style="font-weight: 800;"><?php esc_html_e( 'Grand Total Payable (Inc. VAT):', 'vpdl-management' ); ?></span>
                        <strong style="font-family: monospace; font-weight: 900;"><?php echo esc_html( $currency ); ?><span id="dispDirectGross">0.00</span></strong>
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="height: 46px; padding: 0 32px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; font-size: 14.5px; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Finalize & Issue Official VAT Invoice', 'vpdl-management' ); ?></span>
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Perfect Print-Optimized Modal Window -->
<div id="vpdlPrintInvoiceModal" role="dialog" aria-modal="true" aria-labelledby="modal_inv_title">
    <div class="vpdl-modal-invoice-card">
        
        <div id="vpdlInvoicePrintableArea">
            
            <!-- Corporate Header -->
            <div style="display: flex; justify-content: space-between; border-bottom: 3px solid #0284c7; padding-bottom: 20px; margin-bottom: 24px; box-sizing: border-box;">
                <div style="max-width: 60%;">
                    <h2 style="margin: 0; font-size: 20px; font-weight: 900; color: #0284c7; letter-spacing: -0.3px;"><?php echo esc_html( (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' ) ); ?></h2>
                    <div style="font-size: 11.5px; color: #475569; margin-top: 4px;"><?php printf( esc_html__( 'Company Registered in England & Wales Reg No: %s', 'vpdl-management' ), esc_html( (string) get_option( 'vpdl_reg_no', '08456916' ) ) ); ?></div>
                    <?php $vat_num = (string) get_option( 'vpdl_vat_number', '' ); if ( ! empty( $vat_num ) ) : ?>
                        <div style="font-size: 11.5px; color: #475569;"><?php printf( esc_html__( 'HMRC VAT Registration No: %s', 'vpdl-management' ), esc_html( $vat_num ) ); ?></div>
                    <?php endif; ?>
                    <div style="font-size: 11.5px; color: #475569; margin-top: 2px;"><?php echo esc_html( (string) get_option( 'vpdl_address', 'ESL Office 256B, 182-184 High Street North, London, E6 2JA' ) ); ?></div>
                    <div style="font-size: 11.5px; color: #475569;"><?php printf( esc_html__( 'Tel: %1$s | Email: %2$s', 'vpdl-management' ), esc_html( (string) get_option( 'vpdl_phone', '+44 3333 445 807' ) ), esc_html( (string) get_option( 'vpdl_email', 'vpdl.uk@gmail.com' ) ) ); ?></div>
                </div>
                <div style="text-align: right; max-width: 40%;">
                    <h1 id="modal_inv_title" style="margin: 0; font-size: 26px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px;"><?php esc_html_e( 'VAT INVOICE', 'vpdl-management' ); ?></h1>
                    <div id="modal_inv_num" style="font-family: monospace; font-size: 14px; font-weight: 800; color: #0284c7; margin-top: 4px;"></div>
                    <div id="modal_status_pill" style="margin-top: 6px; display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: 800; font-size: 11px;"></div>
                </div>
            </div>

            <!-- Client & Tax Meta Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 24px; font-size: 13px; line-height: 1.6; box-sizing: border-box;">
                <div>
                    <strong style="font-size: 11px; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Invoiced To (Client):', 'vpdl-management' ); ?></strong>
                    <div id="modal_cust_name" style="font-size: 15px; font-weight: 800; color: #0f172a;"></div>
                    <div id="modal_site_addr" style="color: #475569; margin-top: 2px;"></div>
                    <div id="modal_cust_phone" style="font-size: 12px; color: #64748b; margin-top: 2px; font-family: monospace;"></div>
                </div>
                <div>
                    <strong style="font-size: 11px; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px;"><?php esc_html_e( 'Tax Point & Terms:', 'vpdl-management' ); ?></strong>
                    <div><?php esc_html_e( 'Tax Point / Date:', 'vpdl-management' ); ?> <strong id="modal_issue_date"></strong></div>
                    <div><?php esc_html_e( 'Payment Terms:', 'vpdl-management' ); ?> <strong><?php esc_html_e( '14 Days Net', 'vpdl-management' ); ?></strong></div>
                    <div><?php esc_html_e( 'Payment Due Date:', 'vpdl-management' ); ?> <strong id="modal_due_date"></strong></div>
                    <div><?php esc_html_e( 'Account Manager:', 'vpdl-management' ); ?> <strong id="modal_created_by"></strong></div>
                </div>
            </div>

            <!-- Detailed Items Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; box-sizing: border-box;">
                <thead>
                    <tr style="background: #f1f5f9; text-align: left; border-bottom: 2px solid #cbd5e1;">
                        <th style="padding: 10px 12px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11.5px;"><?php esc_html_e( 'Description of Professional Services', 'vpdl-management' ); ?></th>
                        <th style="padding: 10px 12px; text-align: right; width: 140px; font-weight: 700; color: #475569; text-transform: uppercase; font-size: 11.5px;"><?php esc_html_e( 'Amount (Ex. VAT)', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody id="modal_items_body"></tbody>
            </table>

            <!-- Financial Breakdown Totals -->
            <div style="display: flex; justify-content: flex-end; margin-bottom: 28px; box-sizing: border-box;">
                <div style="width: 300px; font-size: 13px; line-height: 1.8;">
                    <div style="display: flex; justify-content: space-between; color: #475569;">
                        <span><?php esc_html_e( 'Net Subtotal:', 'vpdl-management' ); ?></span> <strong id="modal_subtotal" style="font-family: monospace;"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; color: #64748b;">
                        <span><?php esc_html_e( 'HMRC VAT (20%):', 'vpdl-management' ); ?></span> <strong id="modal_vat" style="font-family: monospace; color: #f59e0b;"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 2px solid #0f172a; padding-top: 8px; margin-top: 6px; font-size: 17px; font-weight: 900; color: #0284c7;">
                        <span><?php esc_html_e( 'Total Payable:', 'vpdl-management' ); ?></span> <strong id="modal_grand" style="font-family: monospace;"></strong>
                    </div>
                </div>
            </div>

            <!-- BACS & Bank Remittance Box -->
            <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 16px; margin-bottom: 24px; font-size: 12px; line-height: 1.6; box-sizing: border-box;">
                <strong style="color: #0284c7; font-size: 13px; display: block; margin-bottom: 6px;"><?php esc_html_e( 'BACS Direct Bank Remittance Details:', 'vpdl-management' ); ?></strong>
                <div><?php esc_html_e( 'Bank Name:', 'vpdl-management' ); ?> <strong><?php echo esc_html( (string) get_option( 'vpdl_bank_name', 'Barclays Bank UK PLC' ) ); ?></strong></div>
                <div><?php esc_html_e( 'Account Name:', 'vpdl-management' ); ?> <strong><?php echo esc_html( (string) get_option( 'vpdl_bank_account_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' ) ); ?></strong></div>
                <div><?php esc_html_e( 'Sort Code:', 'vpdl-management' ); ?> <strong><?php echo esc_html( (string) get_option( 'vpdl_bank_sort_code', '20-00-00' ) ); ?></strong> • <?php esc_html_e( 'Account No:', 'vpdl-management' ); ?> <strong><?php echo esc_html( (string) get_option( 'vpdl_bank_account_no', '12345678' ) ); ?></strong></div>
                <div><?php esc_html_e( 'Mandatory Remittance Reference:', 'vpdl-management' ); ?> <span id="modal_bacs_ref" style="font-family: monospace; font-weight: 800; color: #0284c7;"></span></div>
            </div>

            <!-- Certification & Sign-off Footer -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px dashed #cbd5e1; padding-top: 16px; font-size: 11px; color: #64748b; box-sizing: border-box;">
                <div style="max-width: 60%;">
                    <?php esc_html_e( 'Certified under UK Building Regulations & MTD HMRC Compliance Framework.', 'vpdl-management' ); ?><br>
                    <?php esc_html_e( 'E.&O.E. All professional services governed by VPDL Standard Terms.', 'vpdl-management' ); ?>
                </div>
                <div style="text-align: right; max-width: 40%;">
                    <div style="font-family: cursive; font-size: 15px; color: #0f172a; font-weight: bold;" id="modal_sig_name"><?php esc_html_e( 'Authorized Officer', 'vpdl-management' ); ?></div>
                    <div style="border-top: 1px solid #0f172a; width: 140px; margin-top: 4px; padding-top: 2px; text-transform: uppercase; font-size: 9px; font-weight: 700;"><?php esc_html_e( 'Authorized Signatory', 'vpdl-management' ); ?></div>
                </div>
            </div>

        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px;" class="no-print">
            <button type="button" data-action="print-window" style="flex: 1; height: 42px; background: #0284c7; color: #fff; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'printer', '', 15 ); ?>
                <span><?php esc_html_e( 'Print Formal Invoice', 'vpdl-management' ); ?></span>
            </button>
            <button type="button" data-action="close-print-modal" style="flex: 1; height: 42px; background: #e2e8f0; color: #0f172a; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Close Window', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>
</div>

<script>
    window.vpdlInvoiceData = <?php echo wp_json_encode( $invoices ); ?>;
</script>