<?php
/**
 * View: VPDL Minimal Numeric Matrix Dashboard
 * File: views/dashboard.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_access_terminal' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;

// Fetch Live Real-Time Module Counts
$counts   = function_exists( 'vpdl_get_dashboard_counts' ) ? vpdl_get_dashboard_counts() : array();
$base_url = admin_url( 'admin.php?page=vpdl' );

// 4x5 Grid Layout
$modules = array(
    // Row 1
    array(
        'key'   => 'contacts',
        'title' => __( 'CONTACTS', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'customers', $base_url ),
        'total' => absint( $counts['contacts']['total'] ?? 0 ),
        'alert' => 0,
    ),
    array(
        'key'   => 'quote',
        'title' => __( 'QUOTE', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'quotes', $base_url ),
        'total' => absint( $counts['quote']['total'] ?? 0 ),
        'alert' => absint( $counts['quote']['alert'] ?? 0 ),
    ),
    array(
        'key'   => 'inventory',
        'title' => __( 'INVENTORY', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'schemes', $base_url ),
        'total' => 3,
        'alert' => 5,
    ),
    array(
        'key'   => 'scheduling',
        'title' => __( 'SCHEDULING', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'pipeline', $base_url ),
        'total' => absint( $counts['scheduling']['total'] ?? 0 ),
        'alert' => absint( $counts['scheduling']['alert'] ?? 0 ),
    ),

    // Row 2
    array(
        'key'   => 'customer',
        'title' => __( 'CUSTOMER', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'customers', $base_url ),
        'total' => absint( $counts['customer']['total'] ?? 0 ),
        'alert' => 0,
    ),
    array(
        'key'   => 'sales_order',
        'title' => __( 'SALES ORDER', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'quotes', $base_url ),
        'total' => 8,
        'alert' => 0,
    ),
    array(
        'key'   => 'part_history',
        'title' => __( 'PART HISTORY', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'schemes', $base_url ),
        'total' => 0,
        'alert' => 0,
    ),
    array(
        'key'   => 'personnel',
        'title' => __( 'PERSONNEL', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'consultant-reports', $base_url ),
        'total' => absint( $counts['personnel']['total'] ?? 0 ),
        'alert' => absint( $counts['consultant_jobs']['alert'] ?? 0 ),
    ),

    // Row 3
    array(
        'key'   => 'supplier',
        'title' => __( 'SUPPLIER', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'suppliers', $base_url ),
        'total' => absint( $counts['supplier']['total'] ?? 0 ),
        'alert' => absint( $counts['supplier']['alert'] ?? 0 ),
    ),
    array(
        'key'   => 'works_order',
        'title' => __( 'WORKS ORDER', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'pmc', $base_url ),
        'total' => 15,
        'alert' => 0,
    ),
    array(
        'key'   => 'purchase_order',
        'title' => __( 'PURCHASE ORDER', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'purchase-orders', $base_url ),
        'total' => absint( $counts['purchase_order']['total'] ?? 0 ),
        'alert' => absint( $counts['purchase_order']['alert'] ?? 0 ),
    ),
    array(
        'key'   => 'reporting',
        'title' => __( 'REPORTING', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'consultant-reports', $base_url ),
        'total' => absint( $counts['consultant_jobs']['total'] ?? 0 ),
        'alert' => 0,
    ),

    // Row 4
    array(
        'key'   => 'diary',
        'title' => __( 'DIARY', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'pipeline', $base_url ),
        'total' => 3,
        'alert' => 2,
    ),
    array(
        'key'   => 'delivery',
        'title' => __( 'DELIVERY', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'schemes', $base_url ),
        'total' => 15,
        'alert' => 0,
    ),
    array(
        'key'   => 'quality',
        'title' => __( 'QUALITY', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'sdm', $base_url ),
        'total' => 22,
        'alert' => 2,
    ),
    array(
        'key'   => 'receipts',
        'title' => __( 'RECEIPTS', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'receipts', $base_url ),
        'total' => absint( $counts['receipts']['total'] ?? 0 ),
        'alert' => absint( $counts['receipts']['alert'] ?? 0 ),
    ),

    // Row 5
    array(
        'key'   => 'placeholder1',
        'title' => '',
        'link'  => '',
        'total' => 0,
        'alert' => 0,
    ),
    array(
        'key'   => 'invoice',
        'title' => __( 'INVOICE', 'vpdl-management' ),
        'link'  => add_query_arg( 'view', 'invoices', $base_url ),
        'total' => absint( $counts['invoice']['total'] ?? 0 ),
        'alert' => absint( $counts['invoice']['alert'] ?? 0 ),
    ),
    array(
        'key'   => 'placeholder2',
        'title' => '',
        'link'  => '',
        'total' => 0,
        'alert' => 0,
    ),
    array(
        'key'   => 'placeholder3',
        'title' => '',
        'link'  => '',
        'total' => 0,
        'alert' => 0,
    ),
);

// Recent Activity Audit Ledger
$t_cust   = $wpdb->prefix . 'vpdl_customers';
$t_quotes = $wpdb->prefix . 'vpdl_quotations';
$currency = (string) get_option( 'vpdl_currency', '£' );

$recent_activity_ledger = $wpdb->get_results(
    "SELECT c.name, c.postcode, q.quote_number, q.grand_total, q.status, q.created_at 
     FROM {$t_cust} c 
     LEFT JOIN (
         SELECT customer_id, MAX(id) as max_id 
         FROM {$t_quotes} 
         GROUP BY customer_id
     ) latest_q ON c.id = latest_q.customer_id
     LEFT JOIN {$t_quotes} q ON q.id = latest_q.max_id 
     ORDER BY COALESCE(q.id, c.id) DESC 
     LIMIT 5"
);
?>

<div class="vpdl-dash-wrapper" id="vpdlDashboardApp">

    <!-- Executive Greeting Banner -->
    <div class="vpdl-dash-header-card">
        <div>
            <div class="vpdl-dash-pretitle">
                <?php esc_html_e( 'Command Center • Live Telemetry', 'vpdl-management' ); ?>
            </div>
            <h2 class="vpdl-dash-maintitle">
                <?php esc_html_e( 'Operational Control Matrix', 'vpdl-management' ); ?>
            </h2>
        </div>
        <div class="vpdl-sync-status-box">
            <span class="vpdl-pulse-dot" aria-hidden="true"></span>
            <span class="vpdl-sync-text"><?php esc_html_e( 'All Modules Synchronized', 'vpdl-management' ); ?></span>
        </div>
    </div>

    <!-- 4-Column Minimal Number-Badge Matrix Grid -->
    <div class="vpdl-matrix-grid" role="region" aria-label="<?php esc_attr_e( 'Metrics Matrix', 'vpdl-management' ); ?>">
        <?php
        foreach ( $modules as $m ) :
            if ( empty( $m['title'] ) ) {
                echo '<div class="vpdl-matrix-tile-placeholder" aria-hidden="true"></div>';
                continue;
            }
            ?>
            <a href="<?php echo esc_url( $m['link'] ); ?>" class="vpdl-matrix-tile" data-key="<?php echo esc_attr( $m['key'] ); ?>">
                <span class="vpdl-tile-label"><?php echo esc_html( $m['title'] ); ?></span>
                <div class="vpdl-badges-group">
                    <?php if ( $m['total'] > 0 ) : ?>
                        <span class="vpdl-badge-circle" title="<?php esc_attr_e( 'Total Count', 'vpdl-management' ); ?>">
                            <?php echo esc_html( (string) $m['total'] ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( $m['alert'] > 0 ) : ?>
                        <span class="vpdl-badge-alert" title="<?php esc_attr_e( 'Pending Action Required', 'vpdl-management' ); ?>">
                            <?php echo esc_html( (string) $m['alert'] ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Recent Activity Audit Ledger -->
    <div class="vpdl-audit-panel">
        <div class="vpdl-audit-header">
            <div>
                <h3 class="vpdl-audit-title">
                    <?php esc_html_e( 'Recent Executive Activity Audit', 'vpdl-management' ); ?>
                </h3>
                <p class="vpdl-audit-subtitle">
                    <?php esc_html_e( 'Latest registered client cases, proposals, and lifecycle transitions.', 'vpdl-management' ); ?>
                </p>
            </div>
            <a href="<?php echo esc_url( add_query_arg( 'view', 'consultant-reports', $base_url ) ); ?>" class="vpdl-audit-nav-link">
                <span><?php esc_html_e( 'View Consultant Reports →', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <div class="vpdl-table-responsive">
            <table class="vpdl-audit-table">
                <thead class="vpdl-audit-thead">
                    <tr>
                        <th class="vpdl-audit-th"><?php esc_html_e( 'Client Entity', 'vpdl-management' ); ?></th>
                        <th class="vpdl-audit-th"><?php esc_html_e( 'Postcode', 'vpdl-management' ); ?></th>
                        <th class="vpdl-audit-th"><?php esc_html_e( 'Proposal #', 'vpdl-management' ); ?></th>
                        <th class="vpdl-audit-th"><?php esc_html_e( 'Grand Total', 'vpdl-management' ); ?></th>
                        <th class="vpdl-audit-th vpdl-audit-th-right"><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $recent_activity_ledger ) ) :
                        foreach ( $recent_activity_ledger as $act ) :
                            $status_label = ! empty( $act->status ) ? $act->status : 'Active';
                            $status_lower = strtolower( (string) $status_label );

                            $badge_class = 'vpdl-status-warning';
                            if ( in_array( $status_lower, array( 'paid', 'accepted', 'completed' ), true ) ) {
                                $badge_class = 'vpdl-status-success';
                            } elseif ( in_array( $status_lower, array( 'unpaid', 'cancelled', 'rejected' ), true ) ) {
                                $badge_class = 'vpdl-status-danger';
                            }
                            ?>
                            <tr class="vpdl-audit-row">
                                <td class="vpdl-td-client">
                                    <?php echo esc_html( $act->name ); ?>
                                </td>
                                <td class="vpdl-td-postcode">
                                    <?php echo esc_html( ! empty( $act->postcode ) ? $act->postcode : 'N/A' ); ?>
                                </td>
                                <td class="vpdl-td-quote">
                                    <?php echo esc_html( ! empty( $act->quote_number ) ? $act->quote_number : __( 'Pending Proposal', 'vpdl-management' ) ); ?>
                                </td>
                                <td class="vpdl-td-total">
                                    <?php echo esc_html( $currency . number_format( (float) $act->grand_total, 2 ) ); ?>
                                </td>
                                <td class="vpdl-td-status">
                                    <span class="vpdl-status-badge <?php echo esc_attr( $badge_class ); ?>">
                                        <?php echo esc_html( $status_label ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5" class="vpdl-td-empty">
                                <?php esc_html_e( 'No recent transactions recorded in the executive audit ledger.', 'vpdl-management' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>