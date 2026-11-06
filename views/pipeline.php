<?php
/**
 * View: VPDL Practice Operating Pipeline (Enquiry to Final Invoice)
 * File: views/pipeline.php
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

$t_inq    = $wpdb->prefix . 'vpdl_inquiries';
$t_quotes = $wpdb->prefix . 'vpdl_quotations';
$t_inv    = $wpdb->prefix . 'vpdl_invoices';
$t_cust   = $wpdb->prefix . 'vpdl_customers';

$currency      = (string) get_option( 'vpdl_currency', '£' );
$business_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$reg_no        = (string) get_option( 'vpdl_reg_no', '08456916' );
$vat_number    = (string) get_option( 'vpdl_vat_number', '' );
$b_address     = (string) get_option( 'vpdl_address', 'London, England' );
$b_email       = (string) get_option( 'vpdl_email', 'vpdl.uk@gmail.com' );
$b_phone       = (string) get_option( 'vpdl_phone', '+44 3333 445 807' );

// Core Stage Aggregates
$count_inq  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_inq} WHERE status != 'Won'" );
$count_quot = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_quotes} WHERE status != 'Converted'" );
$count_prog = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_inq} WHERE status = 'In Progress'" );
$count_due  = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_inv} WHERE payment_status = 'Unpaid'" );
$count_paid = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_inv} WHERE payment_status = 'Paid'" );

// Financial Velocity Metrics
$sum_due   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0.00) FROM {$t_inv} WHERE payment_status = 'Unpaid'" );
$sum_paid  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(grand_total), 0.00) FROM {$t_inv} WHERE payment_status = 'Paid'" );
$total_all = $count_inq + $count_quot + $count_prog + $count_due + $count_paid;
$conv_rate = $total_all > 0 ? round( ( $count_paid / $total_all ) * 100, 1 ) : 0;

// Optimized Lifecycle Extraction via Subqueries Grouped by Customer ID
$active_projects = $wpdb->get_results(
    "SELECT c.id AS customer_id, c.name, c.phone, c.email, c.postcode, c.site_address, c.council_name, c.client_type,
            q.quote_number, q.grand_total AS quote_total, q.status AS quote_status, q.total_sqm,
            i.invoice_number, i.grand_total AS invoice_total, i.payment_status,
            inq.service_category, inq.status AS inq_status, inq.notes AS inquiry_notes
     FROM {$t_cust} c
     LEFT JOIN (
         SELECT customer_id, MAX(id) as max_id FROM {$t_quotes} GROUP BY customer_id
     ) latest_q ON c.id = latest_q.customer_id
     LEFT JOIN {$t_quotes} q ON q.id = latest_q.max_id
     LEFT JOIN (
         SELECT customer_id, MAX(id) as max_id FROM {$t_inv} GROUP BY customer_id
     ) latest_i ON c.id = latest_i.customer_id
     LEFT JOIN {$t_inv} i ON i.id = latest_i.max_id
     LEFT JOIN (
         SELECT customer_id, MAX(id) as max_id FROM {$t_inq} GROUP BY customer_id
     ) latest_inq ON c.id = latest_inq.customer_id
     LEFT JOIN {$t_inq} inq ON inq.id = latest_inq.max_id
     ORDER BY c.id DESC
     LIMIT 100"
);
?>

<div class="vpdl-pipeline-container" id="vpdlPipelineModule">

    <!-- Print Audit Header -->
    <div class="vpdl-print-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <h1 class="vpdl-modal-bizname"><?php echo esc_html( $business_name ); ?></h1>
                <div class="vpdl-modal-bizmeta">
                    <?php esc_html_e( 'UK Practice Registry CRN:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $reg_no ); ?></strong> 
                    <?php if ( ! empty( $vat_number ) ) : ?> | <?php esc_html_e( 'VAT:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $vat_number ); ?></strong><?php endif; ?>
                </div>
            </div>
            <div style="text-align: right; font-size: 11px; color: #475569;">
                <div><?php esc_html_e( 'Audit Scope:', 'vpdl-management' ); ?> <strong><?php esc_html_e( 'Operational Practice Pipeline', 'vpdl-management' ); ?></strong></div>
                <div><?php esc_html_e( 'Generated:', 'vpdl-management' ); ?> <strong><?php echo esc_html( current_time( 'd M Y, H:i T' ) ); ?></strong></div>
            </div>
        </div>
    </div>

    <!-- Executive Bar -->
    <div class="vpdl-pipe-panel vpdl-pipe-exec-bar vpdl-no-print">
        <div class="vpdl-pipe-exec-left">
            <div class="vpdl-pipe-icon-badge">
                <?php echo vpdl_get_svg( 'pipeline', '', 22 ); ?>
            </div>
            <div>
                <div class="vpdl-pipe-heading-group">
                    <h2 class="vpdl-pipe-title">
                        <?php esc_html_e( 'Operating Workflow & Progression Pipeline', 'vpdl-management' ); ?>
                    </h2>
                    <span class="vpdl-badge vpdl-badge-live">
                        <span class="vpdl-badge-dot"></span>
                        <?php esc_html_e( 'Live Ledger', 'vpdl-management' ); ?>
                    </span>
                </div>
                <div class="vpdl-pipe-subtitle">
                    <?php esc_html_e( 'Lifecycle: Enquiry • Proposal • Specifications • Review • Billing • Settlement', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="vpdl-pipe-metrics">
                <div>
                    <div class="vpdl-metric-lbl"><?php esc_html_e( 'Due BACS', 'vpdl-management' ); ?></div>
                    <div class="vpdl-metric-val vpdl-val-due"><?php echo esc_html( $currency . number_format( $sum_due, 2 ) ); ?></div>
                </div>
                <div>
                    <div class="vpdl-metric-lbl"><?php esc_html_e( 'Realized', 'vpdl-management' ); ?></div>
                    <div class="vpdl-metric-val vpdl-val-paid"><?php echo esc_html( $currency . number_format( $sum_paid, 2 ) ); ?></div>
                </div>
                <div>
                    <div class="vpdl-metric-lbl"><?php esc_html_e( 'Conversion', 'vpdl-management' ); ?></div>
                    <div class="vpdl-metric-val vpdl-val-conv"><?php echo esc_html( (string) $conv_rate ); ?>%</div>
                </div>
            </div>

            <button type="button" data-action="print-window" class="vpdl-btn-action">
                <?php echo vpdl_get_svg( 'printer', '', 15 ); ?>
                <span><?php esc_html_e( 'Export Master Audit', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>

    <!-- 5 Core Interactive Cards with Lifecycle Mappings -->
    <div class="vpdl-stage-grid vpdl-no-print">
        <!-- Stage 1 -->
        <div class="vpdl-pipe-panel vpdl-stage-card vpdl-card-stage-1" data-action="select-stage" data-stage="stage-1">
            <div class="vpdl-stage-card-header">
                <span class="vpdl-stage-tag vpdl-tag-1"><?php esc_html_e( 'Stage 01: Enquiry', 'vpdl-management' ); ?></span>
                <span class="vpdl-stage-step"><?php esc_html_e( 'Step 1', 'vpdl-management' ); ?></span>
            </div>
            <div class="vpdl-stage-count"><?php echo esc_html( (string) $count_inq ); ?></div>
            <div class="vpdl-stage-desc"><?php esc_html_e( 'Inbound Discovery', 'vpdl-management' ); ?></div>
            <div class="vpdl-stage-subtext"><?php esc_html_e( '1. Client Submits Requirements', 'vpdl-management' ); ?></div>
        </div>

        <!-- Stage 2 -->
        <div class="vpdl-pipe-panel vpdl-stage-card vpdl-card-stage-2" data-action="select-stage" data-stage="stage-2">
            <div class="vpdl-stage-card-header">
                <span class="vpdl-stage-tag vpdl-tag-2"><?php esc_html_e( 'Stage 02: Proposed', 'vpdl-management' ); ?></span>
                <span class="vpdl-stage-step"><?php esc_html_e( 'Steps 2 & 3', 'vpdl-management' ); ?></span>
            </div>
            <div class="vpdl-stage-count"><?php echo esc_html( (string) $count_quot ); ?></div>
            <div class="vpdl-stage-desc"><?php esc_html_e( 'Quotation & Deposit', 'vpdl-management' ); ?></div>
            <div class="vpdl-stage-subtext"><?php esc_html_e( '2. Quote Review • 3. Deposit Acceptance', 'vpdl-management' ); ?></div>
        </div>

        <!-- Stage 3 -->
        <div class="vpdl-pipe-panel vpdl-stage-card vpdl-card-stage-3" data-action="select-stage" data-stage="stage-3">
            <div class="vpdl-stage-card-header">
                <span class="vpdl-stage-tag vpdl-tag-3"><?php esc_html_e( 'Stage 03: Executing', 'vpdl-management' ); ?></span>
                <span class="vpdl-stage-step"><?php esc_html_e( 'Steps 4 & 5', 'vpdl-management' ); ?></span>
            </div>
            <div class="vpdl-stage-count"><?php echo esc_html( (string) $count_prog ); ?></div>
            <div class="vpdl-stage-desc"><?php esc_html_e( 'Design & Technical Review', 'vpdl-management' ); ?></div>
            <div class="vpdl-stage-subtext"><?php esc_html_e( '4. Build Specs • 5. Client Review', 'vpdl-management' ); ?></div>
        </div>

        <!-- Stage 4 -->
        <div class="vpdl-pipe-panel vpdl-stage-card vpdl-card-stage-4" data-action="select-stage" data-stage="stage-4">
            <div class="vpdl-stage-card-header">
                <span class="vpdl-stage-tag vpdl-tag-4"><?php esc_html_e( 'Stage 04: Invoiced', 'vpdl-management' ); ?></span>
                <span class="vpdl-stage-step"><?php esc_html_e( 'Steps 6, 7 & 8', 'vpdl-management' ); ?></span>
            </div>
            <div class="vpdl-stage-count vpdl-count-due"><?php echo esc_html( (string) $count_due ); ?></div>
            <div class="vpdl-stage-desc"><?php esc_html_e( 'Approval & Final Bill', 'vpdl-management' ); ?></div>
            <div class="vpdl-stage-subtext"><?php esc_html_e( '6. Sign-off • 7. Handover • 8. VAT Invoice', 'vpdl-management' ); ?></div>
        </div>

        <!-- Stage 5 -->
        <div class="vpdl-pipe-panel vpdl-stage-card vpdl-card-stage-5" data-action="select-stage" data-stage="stage-5">
            <div class="vpdl-stage-card-header">
                <span class="vpdl-stage-tag vpdl-tag-5"><?php esc_html_e( 'Stage 05: Settled', 'vpdl-management' ); ?></span>
                <span class="vpdl-stage-step"><?php esc_html_e( 'Complete', 'vpdl-management' ); ?></span>
            </div>
            <div class="vpdl-stage-count vpdl-count-paid"><?php echo esc_html( (string) $count_paid ); ?></div>
            <div class="vpdl-stage-desc"><?php esc_html_e( 'BACS Reconciled', 'vpdl-management' ); ?></div>
            <div class="vpdl-stage-subtext"><?php esc_html_e( 'Full Payment Settled & Reconciled', 'vpdl-management' ); ?></div>
        </div>
    </div>

    <!-- Active Stage Filter State Bar -->
    <div id="vpdlStageAnalyticsBar" class="vpdl-pipe-panel vpdl-stage-stream-bar vpdl-no-print">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span class="vpdl-stream-label"><?php esc_html_e( 'Active Stream:', 'vpdl-management' ); ?></span>
            <strong id="vpdlCurrentFilterLabel" class="vpdl-stream-active"><?php esc_html_e( 'All Stages & Sub-steps', 'vpdl-management' ); ?></strong>
        </div>
        <div class="vpdl-stream-stats">
            <div><?php esc_html_e( 'Filtered Records:', 'vpdl-management' ); ?> <strong id="vpdlFilteredCount" style="font-family: monospace;"><?php echo count( $active_projects ); ?></strong></div>
            <div><?php esc_html_e( 'Stream Conversion:', 'vpdl-management' ); ?> <strong style="font-family: monospace; color: #10b981;"><?php echo esc_html( (string) $conv_rate ); ?>%</strong></div>
            <button type="button" data-action="reset-filter" class="vpdl-btn-link"><?php esc_html_e( 'Reset Filters', 'vpdl-management' ); ?></button>
        </div>
    </div>

    <!-- Main Table Panel -->
    <div class="vpdl-pipe-panel vpdl-pipe-table-panel">
        <div class="vpdl-no-print vpdl-pipe-filter-row">
            <h3 class="vpdl-pipe-table-title"><?php esc_html_e( 'Practice Project Lifecycle Register', 'vpdl-management' ); ?></h3>

            <div class="vpdl-filter-controls">
                <input type="text" id="vpdlPipelineSearch" placeholder="<?php esc_attr_e( 'Search client, postcode, invoice...', 'vpdl-management' ); ?>" class="vpdl-input-search">
                <select id="vpdlStageSelect" class="vpdl-select-stage">
                    <option value="all"><?php esc_html_e( 'All Stages & Sub-steps', 'vpdl-management' ); ?></option>
                    <option value="stage-1"><?php esc_html_e( 'Stage 01: Client Enquiry Logged', 'vpdl-management' ); ?></option>
                    <option value="stage-2"><?php esc_html_e( 'Stage 02: Quotation / Deposit Acceptance', 'vpdl-management' ); ?></option>
                    <option value="stage-3"><?php esc_html_e( 'Stage 03: Build Specs / Client Review', 'vpdl-management' ); ?></option>
                    <option value="stage-4"><?php esc_html_e( 'Stage 04: Approval / Handover / Invoice', 'vpdl-management' ); ?></option>
                    <option value="stage-5"><?php esc_html_e( 'Stage 05: Remittance Settled & Reconciled', 'vpdl-management' ); ?></option>
                </select>
            </div>
        </div>

        <div class="vpdl-pipe-table-wrapper">
            <table class="vpdl-pipe-table" id="vpdlPipelineTable">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Client Entity & Site', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Discipline', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Quotation Record', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'VAT Invoice', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Gross Billing', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Lifecycle Stage', 'vpdl-management' ); ?></th>
                        <th class="vpdl-no-print vpdl-th-right"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $active_projects ) ) : ?>
                        <?php
                        foreach ( $active_projects as $p ) :
                            if ( 'Paid' === $p->payment_status ) {
                                $filter_group = 'stage-5';
                                $step_badge   = __( 'Stage 05 • Settled', 'vpdl-management' );
                                $step_detail  = __( 'Remittance Reconciled', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s5';
                            } elseif ( ! empty( $p->invoice_number ) ) {
                                $filter_group = 'stage-4';
                                $step_badge   = __( 'Stage 04 • Step 8', 'vpdl-management' );
                                $step_detail  = __( 'Final VAT Invoice Due', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s4';
                            } elseif ( 'Converted' === $p->quote_status ) {
                                $filter_group = 'stage-4';
                                $step_badge   = __( 'Stage 04 • Step 6/7', 'vpdl-management' );
                                $step_detail  = __( 'Sign-off & Handover', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s2';
                            } elseif ( 'In Progress' === $p->inq_status ) {
                                $filter_group = 'stage-3';
                                $step_badge   = __( 'Stage 03 • Step 4/5', 'vpdl-management' );
                                $step_detail  = __( 'Dev & Client Review', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s3';
                            } elseif ( ! empty( $p->quote_number ) ) {
                                $filter_group = 'stage-2';
                                $step_badge   = __( 'Stage 02 • Step 2/3', 'vpdl-management' );
                                $step_detail  = __( 'Quoted & Deposit Out', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s2';
                            } else {
                                $filter_group = 'stage-1';
                                $step_badge   = __( 'Stage 01 • Step 1', 'vpdl-management' );
                                $step_detail  = __( 'Client Enquiry Logged', 'vpdl-management' );
                                $badge_class  = 'vpdl-badge-s1';
                            }

                            $dept_label = ! empty( $p->service_category ) ? strtoupper( $p->service_category ) : 'PDM';
                            $clean_num  = ! empty( $p->phone ) ? preg_replace( '/[^0-9]/', '', (string) $p->phone ) : '';
                            $wa_message = sprintf( 'Hello %s, following up regarding your %s project (%s) with VPDL.', $p->name, $dept_label, $step_detail );
                            $wa_link    = 'https://wa.me/' . $clean_num . '?text=' . rawurlencode( $wa_message );

                            $single_data = array(
                                'customer_id'    => (int) $p->customer_id,
                                'name'           => (string) $p->name,
                                'phone'          => (string) $p->phone,
                                'email'          => (string) $p->email,
                                'postcode'       => (string) $p->postcode,
                                'site_address'   => (string) $p->site_address,
                                'council_name'   => (string) $p->council_name,
                                'service_cat'    => $dept_label,
                                'quote_number'   => $p->quote_number ?: __( 'Not Issued', 'vpdl-management' ),
                                'quote_total'    => $p->quote_total ? $currency . number_format( (float) $p->quote_total, 2 ) : '—',
                                'invoice_number' => $p->invoice_number ?: __( 'Not Invoiced', 'vpdl-management' ),
                                'invoice_total'  => $p->invoice_total ? $currency . number_format( (float) $p->invoice_total, 2 ) : '—',
                                'payment_status' => $p->payment_status ?: __( 'Unbilled', 'vpdl-management' ),
                                'stage_name'     => $step_badge . ' - ' . $step_detail,
                            );
                            ?>
                            <tr class="vpdl-pipe-row" data-stage="<?php echo esc_attr( $filter_group ); ?>">
                                <td>
                                    <div class="vpdl-entity-name"><?php echo esc_html( $p->name ); ?></div>
                                    <div class="vpdl-entity-meta">
                                        <span class="vpdl-meta-phone"><?php echo esc_html( $p->phone ); ?></span>
                                        <?php if ( ! empty( $p->postcode ) ) : ?>
                                            <span>•</span>
                                            <span class="vpdl-meta-postcode"><?php echo esc_html( $p->postcode ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="vpdl-dept-badge"><?php echo esc_html( $dept_label ); ?></span>
                                </td>
                                <td class="vpdl-mono-cell">
                                    <?php if ( ! empty( $p->quote_number ) ) : ?>
                                        <div class="vpdl-mono-title"><?php echo esc_html( $p->quote_number ); ?></div>
                                        <div class="vpdl-mono-sub"><?php echo esc_html( $p->quote_status ); ?></div>
                                    <?php else : ?>
                                        <span class="vpdl-mono-sub">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="vpdl-mono-cell">
                                    <?php if ( ! empty( $p->invoice_number ) ) : ?>
                                        <div class="vpdl-mono-inv-title"><?php echo esc_html( $p->invoice_number ); ?></div>
                                        <div class="<?php echo 'Paid' === $p->payment_status ? 'vpdl-status-paid' : 'vpdl-status-unpaid'; ?> vpdl-mono-sub">
                                            <?php echo esc_html( $p->payment_status ); ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="vpdl-mono-sub">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="vpdl-gross-amount">
                                    <?php
                                    $bill_amt = (float) ( $p->invoice_total ?: $p->quote_total );
                                    echo esc_html( $currency . ' ' . number_format( $bill_amt, 2 ) );
                                    ?>
                                </td>
                                <td>
                                    <div class="vpdl-lifecycle-cell">
                                        <span class="vpdl-badge <?php echo esc_attr( $badge_class ); ?>">
                                            <?php echo esc_html( $step_badge ); ?>
                                        </span>
                                        <span class="vpdl-stage-detail-lbl">
                                            <?php echo esc_html( $step_detail ); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="vpdl-actions-cell vpdl-no-print">
                                    <button type="button" data-action="open-dossier" data-dossier="<?php echo esc_attr( wp_json_encode( $single_data ) ); ?>" class="vpdl-btn-action">
                                        <?php echo vpdl_get_svg( 'file_text', '', 14 ); ?>
                                        <span><?php esc_html_e( 'Case File', 'vpdl-management' ); ?></span>
                                    </button>

                                    <?php if ( ! empty( $clean_num ) ) : ?>
                                        <a href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer" class="vpdl-btn-action vpdl-btn-wa">
                                            <?php echo vpdl_get_svg( 'whatsapp', '', 14 ); ?>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ( empty( $p->quote_number ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&mode=add&customer_id=' . $p->customer_id ) ); ?>" class="vpdl-btn-action vpdl-btn-quote">
                                            <span><?php esc_html_e( 'Quote', 'vpdl-management' ); ?></span>
                                            <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                                        </a>
                                    <?php elseif ( empty( $p->invoice_number ) ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=invoices&tab=create' ) ); ?>" class="vpdl-btn-action vpdl-btn-invoice">
                                            <span><?php esc_html_e( 'Invoice', 'vpdl-management' ); ?></span>
                                            <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                                        </a>
                                    <?php elseif ( 'Unpaid' === $p->payment_status ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=invoices' ) ); ?>" class="vpdl-btn-action vpdl-btn-settle">
                                            <span><?php esc_html_e( 'Settle', 'vpdl-management' ); ?></span>
                                            <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="vpdl-badge vpdl-badge-s5">
                                            <?php echo vpdl_get_svg( 'check', '', 12 ); ?>
                                            <span><?php esc_html_e( 'Settled', 'vpdl-management' ); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="7" class="vpdl-empty-state">
                                <strong><?php esc_html_e( 'No active projects found in the practice pipeline.', 'vpdl-management' ); ?></strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Individual Case Audit Dossier Modal -->
<div id="vpdlSingleDossierModal" role="dialog" aria-modal="true" aria-labelledby="dossierModalTitle">
    <div class="vpdl-modal-card">
        <div class="vpdl-modal-header">
            <div>
                <h2 id="dossierModalTitle" class="vpdl-modal-bizname"><?php echo esc_html( $business_name ); ?></h2>
                <div class="vpdl-modal-bizmeta">
                    <?php esc_html_e( 'CRN:', 'vpdl-management' ); ?> <?php echo esc_html( $reg_no ); ?> 
                    <?php if ( ! empty( $vat_number ) ) : ?>| <?php esc_html_e( 'VAT:', 'vpdl-management' ); ?> <?php echo esc_html( $vat_number ); ?><?php endif; ?>
                </div>
                <div class="vpdl-modal-bizcontact">
                    <?php echo esc_html( $b_address ); ?> | <?php esc_html_e( 'Tel:', 'vpdl-management' ); ?> <?php echo esc_html( $b_phone ); ?>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="vpdl-modal-badge"><?php esc_html_e( 'Practice Case Dossier', 'vpdl-management' ); ?></span>
                <div class="vpdl-modal-date" id="dossierDate"></div>
            </div>
        </div>

        <div id="dossierPrintArea">
            <div class="vpdl-dossier-grid">
                <div class="vpdl-dossier-box">
                    <strong class="vpdl-dossier-box-title"><?php esc_html_e( 'Client & Contact', 'vpdl-management' ); ?></strong>
                    <div id="dossierClientName" class="vpdl-dossier-val-name"></div>
                    <div id="dossierPhone" class="vpdl-dossier-val-phone"></div>
                    <div id="dossierEmail" class="vpdl-dossier-val-email"></div>
                </div>

                <div class="vpdl-dossier-box">
                    <strong class="vpdl-dossier-box-title"><?php esc_html_e( 'Project Site & Authority', 'vpdl-management' ); ?></strong>
                    <div id="dossierSiteAddress" style="font-weight: 600; font-size: 12px; color: #0f172a;"></div>
                    <div style="margin-top: 4px;">
                        <?php esc_html_e( 'Postcode:', 'vpdl-management' ); ?> <strong id="dossierPostcode" style="font-family: monospace;"></strong>
                    </div>
                    <div id="dossierCouncil" style="font-size: 11px; color: #64748b;"></div>
                </div>
            </div>

            <div class="vpdl-dossier-table-box">
                <table class="vpdl-dossier-table">
                    <tr>
                        <td class="vpdl-d-lbl"><?php esc_html_e( 'Engineering Service Head:', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-val" id="dossierService"></td>
                    </tr>
                    <tr>
                        <td class="vpdl-d-lbl"><?php esc_html_e( 'Quotation Proposal Ref:', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-val" id="dossierQuoteNum" style="font-family: monospace;"></td>
                    </tr>
                    <tr>
                        <td class="vpdl-d-lbl"><?php esc_html_e( 'Quoted Amount (Gross):', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-val" id="dossierQuoteAmt" style="font-family: monospace;"></td>
                    </tr>
                    <tr>
                        <td class="vpdl-d-lbl"><?php esc_html_e( 'Official VAT Invoice Ref:', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-val" id="dossierInvoiceNum" style="font-family: monospace;"></td>
                    </tr>
                    <tr class="vpdl-d-total-row">
                        <td style="font-weight: 800; color: #0f172a;"><?php esc_html_e( 'Final Gross Billed (Inc. VAT):', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-final-amt" id="dossierFinalAmt"></td>
                    </tr>
                    <tr>
                        <td class="vpdl-d-lbl"><?php esc_html_e( 'Current Lifecycle Stage:', 'vpdl-management' ); ?></td>
                        <td class="vpdl-d-val" id="dossierStage"></td>
                    </tr>
                </table>
            </div>

            <div class="vpdl-dossier-compliance">
                <?php printf( esc_html__( 'This individual audit dossier is certified under the Practice Management Operating Framework of %s. All calculations and building specifications comply with UK Building Regulations.', 'vpdl-management' ), esc_html( $business_name ) ); ?>
            </div>
        </div>

        <div class="vpdl-modal-footer vpdl-no-print">
            <button type="button" data-action="close-modal" class="vpdl-btn-close-modal">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Close', 'vpdl-management' ); ?></span>
            </button>
            <button type="button" data-action="print-dossier" class="vpdl-btn-print-modal">
                <?php echo vpdl_get_svg( 'printer', '', 16 ); ?>
                <span><?php esc_html_e( 'Print Dossier / Save PDF', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>
</div>