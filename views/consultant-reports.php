<?php
/**
 * View Partial: Consultant Job, Fee & Performance Reports
 * File: views/consultant-reports.php
 * 
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_view_consultant_reports' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;
$t_inq  = $wpdb->prefix . 'vpdl_inquiries';
$t_cust = $wpdb->prefix . 'vpdl_customers';

// Fetch users with specialist or admin roles
$consultants = get_users( array( 'role__in' => array( 'administrator', 'vpdl_salesman' ) ) );

$selected_consultant = isset( $_GET['consultant_id'] ) ? absint( $_GET['consultant_id'] ) : 0;
$status_filter       = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : '';

// Build Filter SQL Query
$where_clauses = array( 'i.assigned_consultant IS NOT NULL AND i.assigned_consultant > 0' );
$params        = array();

if ( $selected_consultant > 0 ) {
    $where_clauses[] = 'i.assigned_consultant = %d';
    $params[]        = $selected_consultant;
}

if ( ! empty( $status_filter ) ) {
    $where_clauses[] = 'i.status = %s';
    $params[]        = $status_filter;
}

$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

$query = "SELECT i.*, c.name AS client_name, c.phone AS client_phone, c.site_address, c.postcode, u.display_name AS consultant_name, u.user_email AS consultant_email
          FROM {$t_inq} i
          LEFT JOIN {$t_cust} c ON i.customer_id = c.id
          LEFT JOIN {$wpdb->users} u ON i.assigned_consultant = u.ID
          {$where_sql}
          ORDER BY i.created_at DESC";

if ( ! empty( $params ) ) {
    $jobs = $wpdb->get_results( $wpdb->prepare( $query, $params ) );
} else {
    $jobs = $wpdb->get_results( $query );
}

// Calculate Totals for Metric Ribbon
$total_allocated_fees = 0.00;
$active_jobs_count    = 0;
$completed_jobs_count = 0;

if ( ! empty( $jobs ) ) {
    foreach ( $jobs as $j ) {
        $total_allocated_fees += (float) $j->consultant_fee;
        if ( in_array( $j->status, array( 'Completed', 'Approved', 'Won' ), true ) ) {
            $completed_jobs_count++;
        } else {
            $active_jobs_count++;
        }
    }
}

$currency      = (string) get_option( 'vpdl_currency', '£' );
$business_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$base_url      = admin_url( 'admin.php?page=vpdl&view=consultant-reports' );
$total_jobs    = is_array( $jobs ) ? count( $jobs ) : 0;
?>

<div class="vpdl-reports-wrapper" id="vpdlConsultantReportsModule">

    <!-- Header Controls -->
    <div class="vpdl-reports-header no-print">
        <div>
            <h2 class="vpdl-reports-title">
                <?php esc_html_e( 'Consultant Job & Performance Ledger', 'vpdl-management' ); ?>
            </h2>
            <p class="vpdl-reports-subtitle">
                <?php esc_html_e( 'Direct audit of engineer and specialist job allocations, stage delivery, and billable disbursements.', 'vpdl-management' ); ?>
            </p>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="vpdl-reports-filter-form">
            <input type="hidden" name="page" value="vpdl">
            <input type="hidden" name="view" value="consultant-reports">

            <select name="consultant_id" data-action="auto-filter" class="vpdl-reports-select">
                <option value="0"><?php esc_html_e( 'All Consultants / Specialists', 'vpdl-management' ); ?></option>
                <?php foreach ( $consultants as $c ) : ?>
                    <option value="<?php echo esc_attr( (string) $c->ID ); ?>" <?php selected( $selected_consultant, $c->ID ); ?>>
                        <?php echo esc_html( $c->display_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status_filter" data-action="auto-filter" class="vpdl-reports-select">
                <option value=""><?php esc_html_e( 'All Job Statuses', 'vpdl-management' ); ?></option>
                <option value="New" <?php selected( $status_filter, 'New' ); ?>><?php esc_html_e( 'New', 'vpdl-management' ); ?></option>
                <option value="In-Progress" <?php selected( $status_filter, 'In-Progress' ); ?>><?php esc_html_e( 'In-Progress', 'vpdl-management' ); ?></option>
                <option value="In-Review" <?php selected( $status_filter, 'In-Review' ); ?>><?php esc_html_e( 'In-Review', 'vpdl-management' ); ?></option>
                <option value="Completed" <?php selected( $status_filter, 'Completed' ); ?>><?php esc_html_e( 'Completed', 'vpdl-management' ); ?></option>
            </select>

            <button type="button" data-action="print-report" class="button" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 6px 14px;">
                <?php echo vpdl_get_svg( 'printer', '', 14 ); ?>
                <span><?php esc_html_e( 'Print Audit', 'vpdl-management' ); ?></span>
            </button>

            <?php if ( $selected_consultant > 0 || ! empty( $status_filter ) ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="vpdl-reports-btn-reset">
                    <?php esc_html_e( 'Reset Filters', 'vpdl-management' ); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Printable Container Area -->
    <div id="vpdlConsultantPrintArea" style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Summary Metrics Ribbon -->
        <div class="vpdl-reports-stats-grid">
            <div class="vpdl-panel vpdl-reports-stat-card">
                <div class="vpdl-reports-stat-lbl">
                    <?php esc_html_e( 'Total Assigned Jobs', 'vpdl-management' ); ?>
                </div>
                <div class="vpdl-reports-stat-val vpdl-stat-val-main">
                    <?php echo esc_html( (string) $total_jobs ); ?>
                </div>
            </div>

            <div class="vpdl-panel vpdl-reports-stat-card">
                <div class="vpdl-reports-stat-lbl">
                    <?php esc_html_e( 'Active / In-Review', 'vpdl-management' ); ?>
                </div>
                <div class="vpdl-reports-stat-val vpdl-stat-val-red">
                    <?php echo esc_html( (string) $active_jobs_count ); ?>
                </div>
            </div>

            <div class="vpdl-panel vpdl-reports-stat-card">
                <div class="vpdl-reports-stat-lbl">
                    <?php esc_html_e( 'Completed Schemes', 'vpdl-management' ); ?>
                </div>
                <div class="vpdl-reports-stat-val vpdl-stat-val-green">
                    <?php echo esc_html( (string) $completed_jobs_count ); ?>
                </div>
            </div>

            <div class="vpdl-panel vpdl-reports-stat-card">
                <div class="vpdl-reports-stat-lbl">
                    <?php esc_html_e( 'Allocated Fees / Share', 'vpdl-management' ); ?>
                </div>
                <div class="vpdl-reports-stat-val vpdl-stat-val-accent">
                    <?php echo esc_html( $currency . ' ' . number_format( $total_allocated_fees, 2 ) ); ?>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="vpdl-panel vpdl-reports-table-panel">
            <div class="vpdl-reports-toolbar no-print">
                <input type="text" id="vpdlConsultantSearch" class="vpdl-reports-search" placeholder="<?php esc_attr_e( 'Search consultant, client, site, or discipline...', 'vpdl-management' ); ?>">
                <span id="vpdlConsultantCountDisplay" class="vpdl-reports-count-display">
                    <?php printf( esc_html__( 'Showing %d assigned jobs', 'vpdl-management' ), absint( $total_jobs ) ); ?>
                </span>
            </div>

            <div class="vpdl-reports-table-wrap">
                <table class="vpdl-reports-table" id="vpdlConsultantTable">
                    <thead class="vpdl-reports-thead">
                        <tr>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Consultant', 'vpdl-management' ); ?></th>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Client / Project Site', 'vpdl-management' ); ?></th>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Discipline', 'vpdl-management' ); ?></th>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Disbursement Fee', 'vpdl-management' ); ?></th>
                            <th class="vpdl-reports-th"><?php esc_html_e( 'Assigned Date', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $jobs ) ) : ?>
                            <tr>
                                <td colspan="6" class="vpdl-reports-empty">
                                    <?php esc_html_e( 'No consultant project records match the selected criteria.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $jobs as $job ) : 
                                $status_class = 'vpdl-status-default';
                                if ( in_array( $job->status, array( 'Completed', 'Approved', 'Won' ), true ) ) {
                                    $status_class = 'vpdl-status-completed';
                                } elseif ( 'In-Progress' === $job->status || 'In Progress' === $job->status ) {
                                    $status_class = 'vpdl-status-progress';
                                } elseif ( 'New' === $job->status ) {
                                    $status_class = 'vpdl-status-new';
                                }
                            ?>
                                <tr class="vpdl-reports-row">
                                    <td class="vpdl-reports-td">
                                        <strong class="vpdl-reports-consultant-title"><?php echo esc_html( $job->consultant_name ?: __( 'Unassigned', 'vpdl-management' ) ); ?></strong>
                                        <small class="vpdl-reports-consultant-email"><?php echo esc_html( $job->consultant_email ?: '—' ); ?></small>
                                    </td>
                                    <td class="vpdl-reports-td">
                                        <strong class="vpdl-reports-client-title"><?php echo esc_html( $job->client_name ?: __( 'Unknown Client', 'vpdl-management' ) ); ?></strong>
                                        <div class="vpdl-reports-client-meta">
                                            <?php echo esc_html( $job->site_address . ( $job->postcode ? ', ' . $job->postcode : '' ) ); ?>
                                        </div>
                                    </td>
                                    <td class="vpdl-reports-td">
                                        <span class="vpdl-reports-dept-pill">
                                            <?php echo esc_html( $job->service_category ); ?>
                                        </span>
                                    </td>
                                    <td class="vpdl-reports-td">
                                        <span class="vpdl-reports-status-pill <?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $job->status ); ?>
                                        </span>
                                    </td>
                                    <td class="vpdl-reports-td vpdl-reports-fee">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $job->consultant_fee, 2 ) ); ?>
                                    </td>
                                    <td class="vpdl-reports-td vpdl-reports-date">
                                        <?php echo esc_html( date_i18n( 'd M Y', strtotime( $job->created_at ) ) ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>