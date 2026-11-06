<?php
/**
 * View: VPDL Quotations & Scope Proposals Engine
 * File: views/quotes.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_create_quotes' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_quotes    = $wpdb->prefix . 'vpdl_quotations';
$t_cust      = $wpdb->prefix . 'vpdl_customers';
$t_inv       = $wpdb->prefix . 'vpdl_invoices';
$currency    = (string) get_option( 'vpdl_currency', '£' );
$vat_rate    = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url    = admin_url( 'admin.php?page=vpdl&view=quotes' );
$schemes_url = admin_url( 'admin.php?page=vpdl&view=schemes' );
$inv_base    = admin_url( 'admin.php?page=vpdl&view=invoices' );

$user_can_manage = current_user_can( 'vpdl_create_quotes' ) || current_user_can( 'manage_options' );

// ==========================================
// 1. DYNAMIC SERVER-SIDE ACTION HANDLERS
// ==========================================

// Action A: Status Switcher
if ( isset( $_GET['action'], $_GET['quote_id'] ) && 'update_status' === $_GET['action'] ) {
    check_admin_referer( 'vpdl_quote_status_nonce' );

    if ( $user_can_manage ) {
        $qid        = absint( $_GET['quote_id'] );
        $new_status = isset( $_GET['new_status'] ) ? sanitize_key( wp_unslash( $_GET['new_status'] ) ) : '';
        $allowed    = array( 'Draft', 'Sent', 'Accepted', 'Rejected', 'Invoiced' );

        $normalized = ucfirst( strtolower( $new_status ) );
        if ( in_array( $normalized, $allowed, true ) ) {
            $wpdb->update(
                $t_quotes,
                array( 'status' => $normalized ),
                array( 'id' => $qid ),
                array( '%s' ),
                array( '%d' )
            );
            wp_safe_redirect( add_query_arg( array( 'msg' => 'status_updated' ), $base_url ) );
            exit;
        }
    }
}

// Action B: Convert Quote into Official Invoice
if ( isset( $_GET['action'], $_GET['quote_id'] ) && 'convert_invoice' === $_GET['action'] ) {
    check_admin_referer( 'vpdl_quote_convert_nonce' );

    if ( current_user_can( 'vpdl_manage_invoices' ) || current_user_can( 'manage_options' ) ) {
        $qid   = absint( $_GET['quote_id'] );
        $quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_quotes} WHERE id = %d", $qid ) );

        if ( $quote ) {
            $existing_inv = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_inv} WHERE quote_id = %d", $qid ) );

            if ( $existing_inv ) {
                wp_safe_redirect( add_query_arg( array( 'invoice_id' => $existing_inv, 'msg' => 'inv_exists' ), $inv_base ) );
                exit;
            }

            $inv_number = 'VPDL-INV-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
            $token      = wp_generate_password( 32, false, false );

            $wpdb->insert(
                $t_inv,
                array(
                    'invoice_number' => $inv_number,
                    'quote_id'       => $quote->id,
                    'customer_id'    => $quote->customer_id,
                    'items_json'     => $quote->items_json,
                    'subtotal'       => $quote->subtotal,
                    'vat_amount'     => $quote->vat_amount,
                    'grand_total'    => $quote->grand_total,
                    'payment_status' => 'Unpaid',
                    'access_token'   => $token,
                    'created_by'     => wp_get_current_user()->display_name,
                ),
                array( '%s', '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
            );

            // Mark Quote as Invoiced
            $wpdb->update( $t_quotes, array( 'status' => 'Invoiced' ), array( 'id' => $quote->id ), array( '%s' ), array( '%d' ) );

            wp_safe_redirect( add_query_arg( array( 'view' => 'invoices', 'msg' => 'invoice_created' ), admin_url( 'admin.php?page=vpdl' ) ) );
            exit;
        }
    }
}

// Action C: Delete Quote
if ( isset( $_GET['action'], $_GET['quote_id'] ) && 'delete_quote' === $_GET['action'] ) {
    check_admin_referer( 'vpdl_quote_delete_nonce' );

    if ( current_user_can( 'manage_options' ) ) {
        $qid = absint( $_GET['quote_id'] );
        $wpdb->delete( $t_quotes, array( 'id' => $qid ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'msg' => 'quote_deleted' ), $base_url ) );
        exit;
    }
}

// ==========================================
// 2. DYNAMIC FILTERS, COUNTERS & PAGINATION
// ==========================================
$search_query   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status_filter  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$council_filter = isset( $_GET['council'] ) ? sanitize_text_field( wp_unslash( $_GET['council'] ) ) : '';
$paged          = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
$per_page       = 15;
$offset         = max( 0, ( $paged - 1 ) * $per_page );

$where_clauses  = array( '1=1' );
$params         = array();

if ( ! empty( $search_query ) ) {
    $where_clauses[] = '(q.quote_number LIKE %s OR c.name LIKE %s OR c.phone LIKE %s OR c.postcode LIKE %s OR c.email LIKE %s)';
    $like            = '%' . $wpdb->esc_like( $search_query ) . '%';
    $params          = array_merge( $params, array( $like, $like, $like, $like, $like ) );
}

if ( ! empty( $status_filter ) ) {
    $where_clauses[] = 'q.status = %s';
    $params[]        = $status_filter;
}

if ( ! empty( $council_filter ) ) {
    $where_clauses[] = 'c.council_name = %s';
    $params[]        = $council_filter;
}

$where_sql = implode( ' AND ', $where_clauses );

// Total Count for Pagination
$count_query = "SELECT COUNT(q.id) FROM {$t_quotes} q LEFT JOIN {$t_cust} c ON q.customer_id = c.id WHERE {$where_sql}";
$total_items = ! empty( $params ) ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) ) : (int) $wpdb->get_var( $count_query );
$total_pages = ceil( $total_items / $per_page );

// Main Dynamic Query
$query = "SELECT q.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.postcode AS customer_postcode, c.council_name, c.site_address
          FROM {$t_quotes} q 
          LEFT JOIN {$t_cust} c ON q.customer_id = c.id 
          WHERE {$where_sql} 
          ORDER BY q.id DESC 
          LIMIT %d OFFSET %d";

$exec_params = array_merge( $params, array( $per_page, $offset ) );
$quotes      = $wpdb->get_results( $wpdb->prepare( $query, $exec_params ) );

// Fetch Councils for Filter Dropdown
$all_councils = $wpdb->get_col( "SELECT DISTINCT council_name FROM {$t_cust} WHERE council_name != '' ORDER BY council_name ASC" );

// Real-Time Global Metrics (Consolidated Single Query)
$metrics_row = $wpdb->get_row(
    "SELECT 
        COALESCE(SUM(grand_total), 0.00) AS total_sum,
        COALESCE(SUM(CASE WHEN status = 'Accepted' THEN grand_total ELSE 0 END), 0.00) AS accepted_sum,
        COUNT(CASE WHEN status = 'Sent' THEN 1 END) AS sent_count,
        COUNT(CASE WHEN status = 'Invoiced' THEN 1 END) AS invoiced_count
     FROM {$t_quotes}"
);

$metric_total_sum = (float) ( $metrics_row->total_sum ?? 0.00 );
$metric_accepted  = (float) ( $metrics_row->accepted_sum ?? 0.00 );
$metric_sent      = (int) ( $metrics_row->sent_count ?? 0 );
$metric_invoiced  = (int) ( $metrics_row->invoiced_count ?? 0 );

// Modal Active Quote Loader
$active_quote = null;
if ( isset( $_GET['action'], $_GET['quote_id'] ) && 'view' === $_GET['action'] ) {
    $view_qid = absint( $_GET['quote_id'] );
    foreach ( $quotes as $candidate ) {
        if ( (int) $candidate->id === $view_qid ) {
            $active_quote = $candidate;
            break;
        }
    }
    if ( ! $active_quote ) {
        $active_quote = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT q.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.postcode AS customer_postcode, c.council_name, c.site_address
                 FROM {$t_quotes} q 
                 LEFT JOIN {$t_cust} c ON q.customer_id = c.id 
                 WHERE q.id = %d",
                $view_qid
            )
        );
    }
}
?>

<div class="vpdl-quotes-wrap" id="vpdlQuotesModule">

    <!-- 1. Header Toolbar -->
    <div class="vpdl-q-header">
        <div>
            <h2 class="vpdl-q-header-title">
                <?php esc_html_e( 'Quotations & Scope Proposals', 'vpdl-management' ); ?>
            </h2>
            <p class="vpdl-q-header-desc">
                <?php esc_html_e( 'Real-time pricing calculations, status lifecycles, and invoice transformation.', 'vpdl-management' ); ?>
            </p>
        </div>
        <div>
            <a href="<?php echo esc_url( $schemes_url ); ?>" class="vpdl-q-btn-launch">
                <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                <span><?php esc_html_e( 'Launch Schemes Engine', 'vpdl-management' ); ?></span>
            </a>
        </div>
    </div>

    <!-- 2. Dynamic Metric Telemetry -->
    <div class="vpdl-q-metrics-grid">
        <div class="vpdl-q-card vpdl-q-metric-box">
            <div class="vpdl-q-metric-label"><?php esc_html_e( 'Total Quotation Value', 'vpdl-management' ); ?></div>
            <div class="vpdl-q-metric-value">
                <?php echo esc_html( $currency . number_format( $metric_total_sum, 2 ) ); ?>
            </div>
            <div class="vpdl-q-metric-sub"><?php esc_html_e( 'Lifetime tracked quotes', 'vpdl-management' ); ?></div>
        </div>

        <div class="vpdl-q-card vpdl-q-metric-box">
            <div class="vpdl-q-metric-label vpdl-q-val-blue"><?php esc_html_e( 'Awaiting Approval', 'vpdl-management' ); ?></div>
            <div class="vpdl-q-metric-value vpdl-q-val-blue">
                <?php echo esc_html( (string) $metric_sent ); ?>
            </div>
            <div class="vpdl-q-metric-sub"><?php esc_html_e( 'Sent to client', 'vpdl-management' ); ?></div>
        </div>

        <div class="vpdl-q-card vpdl-q-metric-box">
            <div class="vpdl-q-metric-label vpdl-q-val-green"><?php esc_html_e( 'Converted Volume', 'vpdl-management' ); ?></div>
            <div class="vpdl-q-metric-value vpdl-q-val-green">
                <?php echo esc_html( $currency . number_format( $metric_accepted, 2 ) ); ?>
            </div>
            <div class="vpdl-q-metric-sub"><?php esc_html_e( 'Accepted proposals', 'vpdl-management' ); ?></div>
        </div>

        <div class="vpdl-q-card vpdl-q-metric-box">
            <div class="vpdl-q-metric-label vpdl-q-val-amber"><?php esc_html_e( 'Billed Out', 'vpdl-management' ); ?></div>
            <div class="vpdl-q-metric-value vpdl-q-val-amber">
                <?php echo esc_html( (string) $metric_invoiced ); ?> <?php esc_html_e( 'Quotes', 'vpdl-management' ); ?>
            </div>
            <div class="vpdl-q-metric-sub"><?php esc_html_e( 'Transferred to billing', 'vpdl-management' ); ?></div>
        </div>
    </div>

    <!-- 3. Dynamic Filtering Controls -->
    <div class="vpdl-q-card" style="padding: 16px 20px;">
        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="vpdl-q-filter-form">
            <input type="hidden" name="page" value="vpdl">
            <input type="hidden" name="view" value="quotes">

            <div class="vpdl-q-filter-search-box">
                <input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e( 'Filter quote number, name, phone, email, postcode...', 'vpdl-management' ); ?>" class="vpdl-q-filter-input">
            </div>

            <div class="vpdl-q-filter-select-box">
                <select name="status" class="vpdl-q-filter-select">
                    <option value=""><?php esc_html_e( 'All Statuses', 'vpdl-management' ); ?></option>
                    <?php foreach ( array( 'Draft', 'Sent', 'Accepted', 'Rejected', 'Invoiced' ) as $st ) : ?>
                        <option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status_filter, $st ); ?>><?php echo esc_html( $st ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ( ! empty( $all_councils ) ) : ?>
                <div class="vpdl-q-filter-select-box">
                    <select name="council" class="vpdl-q-filter-select">
                        <option value=""><?php esc_html_e( 'All Councils', 'vpdl-management' ); ?></option>
                        <?php foreach ( $all_councils as $c_name ) : ?>
                            <option value="<?php echo esc_attr( $c_name ); ?>" <?php selected( $council_filter, $c_name ); ?>><?php echo esc_html( $c_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <button type="submit" class="vpdl-q-filter-submit">
                <?php esc_html_e( 'Apply', 'vpdl-management' ); ?>
            </button>

            <?php if ( ! empty( $search_query ) || ! empty( $status_filter ) || ! empty( $council_filter ) ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="vpdl-q-btn" style="height: 38px; box-sizing: border-box; padding: 0 14px;">
                    <?php esc_html_e( 'Clear', 'vpdl-management' ); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- 4. Quotations Data Registry -->
    <div class="vpdl-q-card vpdl-q-table-responsive">
        <table class="vpdl-q-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Quote Reference', 'vpdl-management' ); ?></th>
                    <th><?php esc_html_e( 'Client / Property Site', 'vpdl-management' ); ?></th>
                    <th><?php esc_html_e( 'Calculated Scope', 'vpdl-management' ); ?></th>
                    <th><?php esc_html_e( 'Financial Breakdown', 'vpdl-management' ); ?></th>
                    <th><?php esc_html_e( 'Lifecycle', 'vpdl-management' ); ?></th>
                    <th style="text-align: right;"><?php esc_html_e( 'Operations', 'vpdl-management' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $quotes ) ) : ?>
                    <?php
                    $st_nonce   = wp_create_nonce( 'vpdl_quote_status_nonce' );
                    $conv_nonce = wp_create_nonce( 'vpdl_quote_convert_nonce' );
                    $del_nonce  = wp_create_nonce( 'vpdl_quote_delete_nonce' );

                    foreach ( $quotes as $q ) :
                        $items       = json_decode( (string) $q->items_json, true );
                        $item_count  = is_array( $items ) ? count( $items ) : 0;
                        $status_slug = strtolower( (string) $q->status );
                        $share_link  = home_url( '/?vpdl_view=quote&token=' . $q->access_token );
                        ?>
                        <tr>
                            <!-- Reference -->
                            <td>
                                <div class="vpdl-q-mono-ref">
                                    <?php echo esc_html( $q->quote_number ); ?>
                                </div>
                                <div class="vpdl-q-date-sub">
                                    <?php printf( esc_html__( 'Issued %s', 'vpdl-management' ), esc_html( date_i18n( 'd M Y', strtotime( $q->created_at ) ) ) ); ?>
                                </div>
                                <div class="vpdl-q-author-sub">
                                    <?php printf( esc_html__( 'By %s', 'vpdl-management' ), esc_html( $q->created_by ) ); ?>
                                </div>
                            </td>

                            <!-- Client Info -->
                            <td>
                                <div class="vpdl-q-client-name">
                                    <?php echo esc_html( $q->customer_name ?: __( 'Unknown Lead', 'vpdl-management' ) ); ?>
                                </div>
                                <div class="vpdl-q-client-sub">
                                    <?php echo esc_html( $q->customer_postcode ?: __( 'Postcode Missing', 'vpdl-management' ) ); ?>
                                    <?php if ( ! empty( $q->council_name ) ) : ?>
                                        • <?php echo esc_html( $q->council_name ); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="vpdl-q-client-phone">
                                    <?php echo esc_html( $q->customer_phone ); ?>
                                </div>
                            </td>

                            <!-- Scope / Items -->
                            <td>
                                <div class="vpdl-q-scope-title">
                                    <?php echo floatval( $q->total_sqm ) > 0 ? esc_html( number_format( $q->total_sqm, 2 ) . ' sqm' ) : esc_html__( 'Service Schedule', 'vpdl-management' ); ?>
                                </div>
                                <div class="vpdl-q-scope-sub">
                                    <?php printf( esc_html( _n( '%s line item', '%s line items', $item_count, 'vpdl-management' ) ), esc_html( (string) $item_count ) ); ?>
                                </div>
                            </td>

                            <!-- Financial Breakdown -->
                            <td>
                                <div class="vpdl-q-gross-total">
                                    <?php echo esc_html( $currency . number_format( (float) $q->grand_total, 2 ) ); ?>
                                </div>
                                <div class="vpdl-q-net-breakdown">
                                    Net: <?php echo esc_html( $currency . number_format( (float) $q->subtotal, 2 ) ); ?> | VAT: <?php echo esc_html( $currency . number_format( (float) $q->vat_amount, 2 ) ); ?>
                                </div>
                            </td>

                            <!-- Status Badge & Inline Update -->
                            <td>
                                <span class="vpdl-q-badge vpdl-q-badge-<?php echo esc_attr( $status_slug ); ?>">
                                    <?php echo esc_html( $q->status ); ?>
                                </span>

                                <?php if ( $user_can_manage ) : ?>
                                    <select data-action="status-select" class="vpdl-q-status-select">
                                        <option value=""><?php esc_html_e( 'Move status...', 'vpdl-management' ); ?></option>
                                        <?php
                                        foreach ( array( 'Draft', 'Sent', 'Accepted', 'Rejected' ) as $s_opt ) :
                                            if ( $s_opt === $q->status ) {
                                                continue;
                                            }
                                            $s_url = add_query_arg(
                                                array(
                                                    'action'     => 'update_status',
                                                    'quote_id'   => $q->id,
                                                    'new_status' => $s_opt,
                                                    '_wpnonce'   => $st_nonce,
                                                ),
                                                $base_url
                                            );
                                            ?>
                                            <option value="<?php echo esc_url( $s_url ); ?>"><?php echo esc_html( $s_opt ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </td>

                            <!-- Operations -->
                            <td style="text-align: right;">
                                <div class="vpdl-q-actions-flex">
                                    <!-- Copy Public Share Link -->
                                    <button type="button" data-action="copy-share-link" data-url="<?php echo esc_url( $share_link ); ?>" class="vpdl-q-btn" title="<?php esc_attr_e( 'Copy Client Link', 'vpdl-management' ); ?>">
                                        <?php echo vpdl_get_svg( 'copy', '', 14 ); ?>
                                    </button>

                                    <!-- View Proposal Modal -->
                                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'quote_id' => $q->id ), $base_url ) ); ?>" class="vpdl-q-btn" title="<?php esc_attr_e( 'View & Print Document', 'vpdl-management' ); ?>">
                                        <?php echo vpdl_get_svg( 'file_text', '', 14 ); ?>
                                        <span style="margin-left: 4px;"><?php esc_html_e( 'Proposal', 'vpdl-management' ); ?></span>
                                    </a>

                                    <!-- Convert to Official Invoice -->
                                    <?php
                                    if ( 'Invoiced' !== $q->status && ( current_user_can( 'vpdl_manage_invoices' ) || current_user_can( 'manage_options' ) ) ) :
                                        $conv_url = add_query_arg(
                                            array(
                                                'action'   => 'convert_invoice',
                                                'quote_id' => $q->id,
                                                '_wpnonce' => $conv_nonce,
                                            ),
                                            $base_url
                                        );
                                        ?>
                                        <a href="<?php echo esc_url( $conv_url ); ?>" data-action="confirm-invoice" class="vpdl-q-btn vpdl-q-btn-invoice" title="<?php esc_attr_e( 'Convert into Tax Invoice', 'vpdl-management' ); ?>">
                                            <?php echo vpdl_get_svg( 'invoice', '', 14 ); ?>
                                            <span style="margin-left: 4px;"><?php esc_html_e( 'To Invoice', 'vpdl-management' ); ?></span>
                                        </a>
                                    <?php endif; ?>

                                    <!-- Delete Permanently -->
                                    <?php
                                    if ( current_user_can( 'manage_options' ) ) :
                                        $del_url = add_query_arg(
                                            array(
                                                'action'   => 'delete_quote',
                                                'quote_id' => $q->id,
                                                '_wpnonce' => $del_nonce,
                                            ),
                                            $base_url
                                        );
                                        ?>
                                        <a href="<?php echo esc_url( $del_url ); ?>" data-action="confirm-delete" data-ref="<?php echo esc_attr( $q->quote_number ); ?>" class="vpdl-q-btn vpdl-q-btn-delete" title="<?php esc_attr_e( 'Delete Record', 'vpdl-management' ); ?>">
                                            <?php echo vpdl_get_svg( 'trash', '', 14 ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" class="vpdl-q-empty-state">
                            <div class="vpdl-q-empty-title"><?php esc_html_e( 'No quotations match your parameters.', 'vpdl-management' ); ?></div>
                            <div class="vpdl-q-empty-desc"><?php esc_html_e( 'Initiate a new project calculation using the Schemes Engine.', 'vpdl-management' ); ?></div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 5. Dynamic Pagination Bar -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="vpdl-q-pagination">
            <div class="vpdl-q-pagination-info">
                <?php printf( esc_html__( 'Showing page %1$s of %2$s (%3$s records)', 'vpdl-management' ), '<strong>' . esc_html( (string) $paged ) . '</strong>', '<strong>' . esc_html( (string) $total_pages ) . '</strong>', esc_html( (string) $total_items ) ); ?>
            </div>
            <div class="vpdl-q-pagination-links">
                <?php if ( $paged > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>" class="vpdl-q-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <?php echo vpdl_get_svg( 'arrow_left', '', 12 ); ?>
                        <span><?php esc_html_e( 'Previous', 'vpdl-management' ); ?></span>
                    </a>
                <?php endif; ?>
                <?php if ( $paged < $total_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>" class="vpdl-q-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span><?php esc_html_e( 'Next', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 6. Interactive Document / Proposal Modal Window -->
    <?php
    if ( $active_quote ) :
        $active_items    = json_decode( (string) $active_quote->items_json, true );
        $co_name         = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
        $co_addr         = (string) get_option( 'vpdl_address', '' );
        $co_phone        = (string) get_option( 'vpdl_phone', '' );
        $co_email        = (string) get_option( 'vpdl_email', '' );
        $co_vat          = (string) get_option( 'vpdl_vat_number', '' );
        $co_reg          = (string) get_option( 'vpdl_reg_no', '' );
        $validity        = (int) get_option( 'vpdl_quote_validity', 30 );
        $modal_share_url = home_url( '/?vpdl_view=quote&token=' . $active_quote->access_token );
        ?>
        <div id="vpdlQuoteModal" class="no-print-overlay">
            <div class="vpdl-q-modal-card">

                <!-- Action Controls inside Modal -->
                <div class="no-print vpdl-q-modal-topbar">
                    <div class="vpdl-q-modal-actions">
                        <button type="button" data-action="print-window" class="vpdl-q-modal-print-btn">
                            <?php echo vpdl_get_svg( 'printer', '', 14 ); ?>
                            <span><?php esc_html_e( 'Print / PDF Export', 'vpdl-management' ); ?></span>
                        </button>
                        <button type="button" data-action="copy-share-link" data-url="<?php echo esc_url( $modal_share_url ); ?>" class="vpdl-q-modal-copy-btn">
                            <?php echo vpdl_get_svg( 'copy', '', 14 ); ?>
                            <span><?php esc_html_e( 'Copy Share Link', 'vpdl-management' ); ?></span>
                        </button>
                    </div>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="vpdl-q-modal-close-btn">
                        <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
                    </a>
                </div>

                <!-- Printable Document Area -->
                <div id="vpdlPrintableArea">
                    <div class="vpdl-q-doc-header">
                        <div>
                            <div class="vpdl-q-co-name"><?php echo esc_html( $co_name ); ?></div>
                            <div class="vpdl-q-co-meta">
                                <?php echo esc_html( $co_addr ); ?><br>
                                <?php printf( esc_html__( 'Phone: %1$s • Email: %2$s', 'vpdl-management' ), esc_html( $co_phone ), esc_html( $co_email ) ); ?><br>
                                <?php printf( esc_html__( 'Company Reg: %1$s • VAT: %2$s', 'vpdl-management' ), esc_html( $co_reg ), esc_html( $co_vat ) ); ?>
                            </div>
                        </div>
                        <div>
                            <div class="vpdl-q-doc-title"><?php esc_html_e( 'QUOTATION', 'vpdl-management' ); ?></div>
                            <div class="vpdl-q-doc-ref">
                                <?php echo esc_html( $active_quote->quote_number ); ?>
                            </div>
                            <div class="vpdl-q-doc-meta">
                                <?php printf( esc_html__( 'Date: %s', 'vpdl-management' ), esc_html( date_i18n( 'd F Y', strtotime( $active_quote->created_at ) ) ) ); ?>
                            </div>
                            <div class="vpdl-q-doc-meta">
                                <?php printf( esc_html__( 'Validity: %d Days', 'vpdl-management' ), absint( $validity ) ); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Client & Site Details -->
                    <div class="vpdl-q-parties-grid">
                        <div>
                            <div class="vpdl-q-section-title"><?php esc_html_e( 'Client Particulars', 'vpdl-management' ); ?></div>
                            <div class="vpdl-q-party-name"><?php echo esc_html( $active_quote->customer_name ); ?></div>
                            <div class="vpdl-q-party-details">
                                <?php printf( esc_html__( 'Phone: %s', 'vpdl-management' ), esc_html( $active_quote->customer_phone ) ); ?><br>
                                <?php printf( esc_html__( 'Email: %s', 'vpdl-management' ), esc_html( $active_quote->customer_email ) ); ?>
                            </div>
                        </div>
                        <div>
                            <div class="vpdl-q-section-title"><?php esc_html_e( 'Project / Site Location', 'vpdl-management' ); ?></div>
                            <div class="vpdl-q-party-details" style="margin-top: 4px; font-weight: 600; color: #0f172a;">
                                <?php echo nl2br( esc_html( $active_quote->site_address ) ); ?><br>
                                <?php printf( esc_html__( 'Postcode: %s', 'vpdl-management' ), esc_html( $active_quote->customer_postcode ) ); ?><br>
                                <?php printf( esc_html__( 'Council: %s', 'vpdl-management' ), esc_html( $active_quote->council_name ) ); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Items Scope Breakdown -->
                    <table class="vpdl-q-items-table">
                        <thead class="vpdl-q-items-thead">
                            <tr>
                                <th style="text-align: left;"><?php esc_html_e( 'Description of Works', 'vpdl-management' ); ?></th>
                                <th style="text-align: center;"><?php esc_html_e( 'Discipline', 'vpdl-management' ); ?></th>
                                <th style="text-align: right;"><?php esc_html_e( 'Qty / Scope', 'vpdl-management' ); ?></th>
                                <th style="text-align: right;"><?php esc_html_e( 'Amount (Ex. VAT)', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( is_array( $active_items ) && ! empty( $active_items ) ) : ?>
                                <?php foreach ( $active_items as $item ) : ?>
                                    <tr class="vpdl-q-item-row">
                                        <td class="vpdl-q-item-td">
                                            <div class="vpdl-q-item-desc">
                                                <?php echo esc_html( $item['description'] ?? ( $item['name'] ?? __( 'Professional Engineering Service', 'vpdl-management' ) ) ); ?>
                                            </div>
                                            <?php if ( ! empty( $item['notes'] ) ) : ?>
                                                <div class="vpdl-q-item-notes"><?php echo esc_html( $item['notes'] ); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="vpdl-q-item-cat">
                                            <?php echo esc_html( $item['category'] ?? 'General' ); ?>
                                        </td>
                                        <td class="vpdl-q-item-qty">
                                            <?php echo esc_html( (string) ( $item['quantity'] ?? ( $item['qty'] ?? '1' ) ) ); ?>
                                        </td>
                                        <td class="vpdl-q-item-fee">
                                            <?php echo esc_html( $currency . number_format( (float) ( $item['total'] ?? ( $item['unit_price'] ?? ( $item['fee'] ?? 0.00 ) ) ), 2 ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" style="padding: 16px; text-align: center; color: #64748b;"><?php esc_html_e( 'Custom Single Project Package', 'vpdl-management' ); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Totals Block -->
                    <div class="vpdl-q-totals-container">
                        <div class="vpdl-q-totals-block">
                            <div class="vpdl-q-totals-line">
                                <span><?php esc_html_e( 'Net Subtotal:', 'vpdl-management' ); ?></span>
                                <span class="vpdl-q-totals-line-val"><?php echo esc_html( $currency . number_format( (float) $active_quote->subtotal, 2 ) ); ?></span>
                            </div>
                            <div class="vpdl-q-totals-line">
                                <span><?php printf( esc_html__( 'VAT (%s%%):', 'vpdl-management' ), esc_html( (string) $vat_rate ) ); ?></span>
                                <span class="vpdl-q-totals-line-val"><?php echo esc_html( $currency . number_format( (float) $active_quote->vat_amount, 2 ) ); ?></span>
                            </div>
                            <div class="vpdl-q-totals-grand">
                                <span><?php esc_html_e( 'Grand Total:', 'vpdl-management' ); ?></span>
                                <span style="font-family: monospace;"><?php echo esc_html( $currency . number_format( (float) $active_quote->grand_total, 2 ) ); ?></span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>

</div>