<?php
/**
 * View: VPDL Client Directory & Lead Intelligence Registry
 * File: views/customers.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_cust   = $wpdb->prefix . 'vpdl_customers';
$t_inq    = $wpdb->prefix . 'vpdl_inquiries';
$t_quotes = $wpdb->prefix . 'vpdl_quotations';
$t_inv    = $wpdb->prefix . 'vpdl_invoices';

$currency = (string) get_option( 'vpdl_currency', '£' );
$base_url = admin_url( 'admin.php?page=vpdl&view=customers' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'add' : 'list' );
$target_id   = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
$feedback    = '';

// 1. Delete Customer Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_customer' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete customer.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_cust, array( 'id' => $del_id ), array( '%d' ) );
        $feedback = 'deleted';
    }
}

// Staff Selection List for Case Assignment
$team_users = get_users(
    array(
        'role__in' => array( 'vpdl_salesman', 'administrator', 'vpdl_manager' ),
        'orderby'  => 'display_name',
        'order'    => 'ASC',
    )
);

// Pagination Setup
$per_page        = 15;
$paged           = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$offset          = max( 0, ( $paged - 1 ) * $per_page );
$total_customers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$t_cust}" );
$total_pages     = ceil( $total_customers / $per_page );

// High-Performance Indexed Aggregate Query
$customers = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT c.*, 
                COALESCE(q_agg.total_quotes, 0) AS total_quotes,
                COALESCE(i_agg.total_invoices, 0) AS total_invoices,
                COALESCE(i_agg.lifetime_value, 0.00) AS lifetime_value,
                i_agg.last_deal_date,
                u.display_name AS agent_name
         FROM {$t_cust} c
         LEFT JOIN (
             SELECT customer_id, COUNT(id) AS total_quotes
             FROM {$t_quotes}
             GROUP BY customer_id
         ) q_agg ON c.id = q_agg.customer_id
         LEFT JOIN (
             SELECT customer_id, 
                    COUNT(id) AS total_invoices,
                    SUM(CASE WHEN payment_status != 'Cancelled' THEN grand_total ELSE 0 END) AS lifetime_value,
                    MAX(created_at) AS last_deal_date
             FROM {$t_inv}
             GROUP BY customer_id
         ) i_agg ON c.id = i_agg.customer_id
         LEFT JOIN {$wpdb->users} u ON c.assigned_to = u.ID
         ORDER BY c.id DESC
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    )
);

// View Mode Data Extraction
$single_customer    = null;
$customer_inquiries = array();
$customer_quotes    = array();
$customer_metrics   = null;

if ( 'view' === $action_mode && $target_id > 0 ) {
    $single_customer = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT c.*, u.display_name AS agent_name 
             FROM {$t_cust} c 
             LEFT JOIN {$wpdb->users} u ON c.assigned_to = u.ID 
             WHERE c.id = %d",
            $target_id
        )
    );

    if ( $single_customer ) {
        $customer_inquiries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t_inq} WHERE customer_id = %d ORDER BY id DESC",
                $target_id
            )
        );

        $customer_quotes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t_quotes} WHERE customer_id = %d ORDER BY id DESC",
                $target_id
            )
        );

        $customer_metrics = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COALESCE(SUM(CASE WHEN payment_status != 'Cancelled' THEN grand_total ELSE 0 END), 0.00) AS total_gross_spend,
                    COALESCE(SUM(CASE WHEN payment_status != 'Cancelled' THEN subtotal ELSE 0 END), 0.00) AS total_net_spend,
                    COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0.00) AS paid_amount,
                    COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' THEN grand_total ELSE 0 END), 0.00) AS outstanding_amount
                   FROM {$t_inv}
                   WHERE customer_id = %d",
                $target_id
            )
        );
    }
}

// Edit Mode Data Extraction
$edit_customer = null;
if ( 'edit' === $action_mode && $target_id > 0 ) {
    $edit_customer = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$t_cust} WHERE id = %d", $target_id )
    );
}
?>

<div class="vpdl-cust-container" id="vpdlCustomersModule">

    <?php if ( 'deleted' === $feedback ) : ?>
        <div class="vpdl-cust-alert vpdl-cust-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php esc_html_e( 'Customer record permanently deleted.', 'vpdl-management' ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-cust-card vpdl-cust-header-card">
        <div class="vpdl-cust-header-left">
            <div class="vpdl-cust-header-icon">
                <?php echo vpdl_get_svg( 'customers', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-cust-header-title"><?php esc_html_e( 'Client Management & Leads', 'vpdl-management' ); ?></h2>
                <div class="vpdl-cust-header-subtitle">
                    <?php esc_html_e( 'Directory, Lead Pipeline & Client Account Dossiers', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-cust-pill vpdl-pill-header-count">
                <?php echo esc_html( number_format( $total_customers ) ); ?> <?php esc_html_e( 'Total Clients Registered', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_customer ) : ?>
    <!-- VIEW MODE: DETAILED CLIENT DOSSIER -->
    <div class="vpdl-cust-card vpdl-cust-table-wrap">
        <div class="vpdl-dossier-topbar">
            <div class="vpdl-dossier-header-left">
                <div class="vpdl-dossier-main-icon">
                    <?php echo vpdl_get_svg( 'customers', '', 24 ); ?>
                </div>
                <div>
                    <h2 class="vpdl-dossier-name">
                        <?php echo esc_html( $single_customer->name ); ?>
                    </h2>
                    <div class="vpdl-dossier-meta">
                        <span><?php echo esc_html( $single_customer->client_type ?: __( 'Residential Homeowner', 'vpdl-management' ) ); ?></span>
                        <span style="font-family: monospace; font-weight: 700; color: var(--vpdl-accent);">#VPDL-CUST-<?php echo esc_html( (string) $single_customer->id ); ?></span>
                        <span><?php esc_html_e( 'Council:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_customer->council_name ?: __( 'Not Specified', 'vpdl-management' ) ); ?></strong></span>
                    </div>
                </div>
            </div>
            
            <div class="vpdl-dossier-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&customer_id=' . $single_customer->id ) ); ?>" class="vpdl-cust-action-btn vpdl-btn-view">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create Proposal', 'vpdl-management' ); ?></span>
                </a>
                <?php 
                $clean_view_phone = preg_replace( '/[^0-9]/', '', (string) $single_customer->phone );
                if ( ! empty( $clean_view_phone ) ) : 
                    ?>
                    <a href="https://wa.me/<?php echo esc_attr( $clean_view_phone ); ?>" target="_blank" rel="noopener noreferrer" class="vpdl-cust-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                        <?php echo vpdl_get_svg( 'whatsapp', '', 14 ); ?>
                        <span><?php esc_html_e( 'WhatsApp Direct', 'vpdl-management' ); ?></span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'customer_id' => $single_customer->id ), $base_url ) ); ?>" class="vpdl-cust-action-btn vpdl-btn-edit">
                    <?php echo vpdl_get_svg( 'edit', '', 14 ); ?>
                    <span><?php esc_html_e( 'Edit Profile', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-cust-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to Directory', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="vpdl-dossier-metrics-grid">
            <div class="vpdl-cust-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Phone & Contact', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-phone"><?php echo esc_html( $single_customer->phone ); ?></div>
                <div class="vpdl-dossier-sub-detail"><?php echo esc_html( $single_customer->email ?: __( 'No email registered', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-cust-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Total Invoiced (Gross)', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-gross">
                    <?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->total_gross_spend ?? 0 ), 2 ) ); ?>
                </div>
                <div class="vpdl-dossier-sub-detail">
                    <?php esc_html_e( 'Net:', 'vpdl-management' ); ?> <strong style="color: var(--vpdl-text-main); font-family: monospace;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $customer_metrics->total_net_spend ?? 0 ), 2 ) ); ?></strong>
                </div>
            </div>

            <div class="vpdl-cust-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Lead Origin & Rep', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-origin">
                    <?php echo esc_html( $single_customer->agent_name ?: __( 'System Assigned', 'vpdl-management' ) ); ?>
                </div>
                <div class="vpdl-dossier-sub-detail">
                    <?php esc_html_e( 'Source:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_customer->lead_source ?: 'Direct' ); ?></strong>
                </div>
            </div>

            <div class="vpdl-cust-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Site Property Address', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-addr">
                    <?php echo esc_html( $single_customer->site_address ?: __( 'Standard Consultation Site', 'vpdl-management' ) ); ?>
                    <?php if ( ! empty( $single_customer->postcode ) ) : ?>
                        <div style="font-family: monospace; color: var(--vpdl-accent); margin-top: 2px;"><?php echo esc_html( $single_customer->postcode ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Inquiries & Quotations Grid -->
        <div class="vpdl-dossier-grid-2col">
            <div class="vpdl-cust-card vpdl-dossier-stream-card">
                <h4 class="vpdl-dossier-stream-title">
                    <?php echo vpdl_get_svg( 'consulting', '', 17 ); ?>
                    <span><?php esc_html_e( 'Project Inquiries & Follow-ups', 'vpdl-management' ); ?></span>
                </h4>
                
                <?php if ( ! empty( $customer_inquiries ) ) : ?>
                    <div>
                        <?php foreach ( $customer_inquiries as $inq ) : ?>
                            <div class="vpdl-dossier-item-card">
                                <div class="vpdl-dossier-item-head">
                                    <strong style="color: var(--vpdl-accent);"><?php echo esc_html( $inq->service_category ); ?></strong>
                                    <span class="vpdl-cust-pill vpdl-pill-accent"><?php echo esc_html( $inq->status ); ?></span>
                                </div>
                                <div class="vpdl-dossier-item-body">
                                    <?php echo esc_html( $inq->notes ); ?>
                                </div>
                                <div class="vpdl-dossier-item-footer">
                                    <?php esc_html_e( 'Milestone:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $inq->next_followup_date ?: __( 'None Set', 'vpdl-management' ) ); ?></strong>
                                    <?php esc_html_e( 'Agent:', 'vpdl-management' ); ?> <?php echo esc_html( $inq->created_by ); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="vpdl-cust-empty-state">
                        <?php esc_html_e( 'No project inquiries logged for this client yet.', 'vpdl-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="vpdl-cust-card vpdl-dossier-stream-card">
                <h4 class="vpdl-dossier-stream-title">
                    <?php echo vpdl_get_svg( 'schemes', '', 17 ); ?>
                    <span><?php esc_html_e( 'Proposals & Client Share Links', 'vpdl-management' ); ?></span>
                </h4>

                <?php if ( ! empty( $customer_quotes ) ) : ?>
                    <div>
                        <?php
                        foreach ( $customer_quotes as $q ) :
                            $public_quote_url = home_url( '/?vpdl_view=quote&token=' . $q->access_token );
                            ?>
                            <div class="vpdl-dossier-item-card">
                                <div class="vpdl-dossier-item-head">
                                    <strong style="font-family: monospace; color: var(--vpdl-text-main);"><?php echo esc_html( $q->quote_number ); ?></strong>
                                    <strong style="color: #10b981; font-family: monospace;"><?php echo esc_html( $currency . ' ' . number_format( (float) $q->grand_total, 2 ) ); ?></strong>
                                </div>
                                <div class="vpdl-dossier-copy-row">
                                    <input type="text" readonly value="<?php echo esc_url( $public_quote_url ); ?>" class="vpdl-dossier-copy-input">
                                    <button type="button" data-action="copy-quote-link" data-url="<?php echo esc_url( $public_quote_url ); ?>" class="vpdl-cust-action-btn vpdl-dossier-copy-btn">
                                        <?php echo vpdl_get_svg( 'copy', '', 12 ); ?>
                                        <span><?php esc_html_e( 'Copy Link', 'vpdl-management' ); ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="vpdl-cust-empty-state">
                        <?php esc_html_e( 'No quotations prepared for this client.', 'vpdl-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $edit_customer ) : ?>
    <!-- EDIT MODE: CLIENT RECORD UPDATE FORM -->
    <div class="vpdl-cust-card vpdl-cust-form-card vpdl-card-center">
        <div class="vpdl-cust-form-header">
            <h3 class="vpdl-cust-form-title">
                <?php echo vpdl_get_svg( 'edit', '', 18 ); ?>
                <span><?php esc_html_e( 'Edit Client Profile & Dossier', 'vpdl-management' ); ?></span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-cust-btn-discard">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=vpdl' ) ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="save_customer">
            <input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $edit_customer->id ); ?>">

            <!-- 1. Primary Contacts -->
            <div class="vpdl-cust-grid-3 vpdl-cust-form-group">
                <div>
                    <label for="vpdl_edit_name"><?php esc_html_e( 'Client / Owner Name', 'vpdl-management' ); ?> *</label>
                    <input type="text" id="vpdl_edit_name" name="name" value="<?php echo esc_attr( $edit_customer->name ); ?>" required>
                </div>
                <div>
                    <label for="vpdl_edit_phone"><?php esc_html_e( 'Phone / WhatsApp', 'vpdl-management' ); ?> *</label>
                    <input type="tel" id="vpdl_edit_phone" name="phone" value="<?php echo esc_attr( $edit_customer->phone ); ?>" required>
                </div>
                <div>
                    <label for="vpdl_edit_email"><?php esc_html_e( 'Email Address', 'vpdl-management' ); ?></label>
                    <input type="email" id="vpdl_edit_email" name="email" value="<?php echo esc_attr( $edit_customer->email ); ?>">
                </div>
            </div>

            <!-- 2. Classification & Governance -->
            <div class="vpdl-cust-grid-3 vpdl-cust-form-group">
                <div>
                    <label for="vpdl_edit_client_type"><?php esc_html_e( 'Client Classification', 'vpdl-management' ); ?></label>
                    <select id="vpdl_edit_client_type" name="client_type">
                        <option value="Homeowner" <?php selected( $edit_customer->client_type, 'Homeowner' ); ?>><?php esc_html_e( 'Residential Homeowner', 'vpdl-management' ); ?></option>
                        <option value="Property Developer" <?php selected( $edit_customer->client_type, 'Property Developer' ); ?>><?php esc_html_e( 'Property Developer', 'vpdl-management' ); ?></option>
                        <option value="Commercial Contractor" <?php selected( $edit_customer->client_type, 'Commercial Contractor' ); ?>><?php esc_html_e( 'Commercial Contractor', 'vpdl-management' ); ?></option>
                        <option value="Architectural Partner" <?php selected( $edit_customer->client_type, 'Architectural Partner' ); ?>><?php esc_html_e( 'Corporate / Architect Partner', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_edit_council"><?php esc_html_e( 'Local Council (LPA)', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_edit_council" name="council_name" value="<?php echo esc_attr( $edit_customer->council_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. London Borough of Newham', 'vpdl-management' ); ?>">
                </div>
                <div>
                    <label for="vpdl_edit_assigned"><?php esc_html_e( 'Assigned Representative', 'vpdl-management' ); ?></label>
                    <select id="vpdl_edit_assigned" name="assigned_to">
                        <?php foreach ( $team_users as $u ) : ?>
                            <option value="<?php echo esc_attr( (string) $u->ID ); ?>" <?php selected( $edit_customer->assigned_to, $u->ID ); ?>>
                                <?php echo esc_html( $u->display_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 3. Project Location & Postcode -->
            <div class="vpdl-cust-grid-addr vpdl-cust-form-group">
                <div>
                    <label for="vpdl_edit_site_address"><?php esc_html_e( 'Project Property Address', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_edit_site_address" name="site_address" value="<?php echo esc_attr( $edit_customer->site_address ); ?>">
                </div>
                <div>
                    <label for="vpdl_edit_postcode"><?php esc_html_e( 'Site Postcode', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_edit_postcode" name="postcode" value="<?php echo esc_attr( $edit_customer->postcode ); ?>" placeholder="<?php esc_attr_e( 'e.g. E6 2JA', 'vpdl-management' ); ?>" style="font-family: monospace; font-weight: 700;">
                </div>
            </div>

            <div class="vpdl-cust-form-group">
                <label for="vpdl_edit_lead_source"><?php esc_html_e( 'Lead Acquisition Source', 'vpdl-management' ); ?></label>
                <select id="vpdl_edit_lead_source" name="lead_source">
                    <option value="Website Inbound" <?php selected( $edit_customer->lead_source, 'Website Inbound' ); ?>><?php esc_html_e( 'Website Inbound Form', 'vpdl-management' ); ?></option>
                    <option value="WhatsApp Direct" <?php selected( $edit_customer->lead_source, 'WhatsApp Direct' ); ?>><?php esc_html_e( 'Direct WhatsApp Contact', 'vpdl-management' ); ?></option>
                    <option value="Referral" <?php selected( $edit_customer->lead_source, 'Referral' ); ?>><?php esc_html_e( 'Word of Mouth / Client Referral', 'vpdl-management' ); ?></option>
                    <option value="Architect Partner" <?php selected( $edit_customer->lead_source, 'Architect Partner' ); ?>><?php esc_html_e( 'Architect / Builder Recommendation', 'vpdl-management' ); ?></option>
                    <option value="Returning Client" <?php selected( $edit_customer->lead_source, 'Returning Client' ); ?>><?php esc_html_e( 'Returning Client', 'vpdl-management' ); ?></option>
                </select>
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="vpdl-cust-btn-primary">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Update Client Dossier', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-cust-btn-discard">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION: ADD CUSTOMER VS ALL CUSTOMERS -->
    <div class="vpdl-cust-tab-nav" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="list" id="vpdlCustTabBtnList" class="vpdl-cust-tab-btn <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'list' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'customers', '', 18 ); ?>
            <span><?php esc_html_e( 'All Customers', 'vpdl-management' ); ?> (<?php echo esc_html( number_format( $total_customers ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="add" id="vpdlCustTabBtnAdd" class="vpdl-cust-tab-btn <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'add' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
            <span><?php esc_html_e( 'Add Customer', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: ALL CUSTOMERS DIRECTORY TABLE -->
    <div id="vpdlCustTabPaneList" class="vpdl-cust-tab-pane <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-cust-card vpdl-cust-table-wrap">
            <div class="vpdl-cust-search-row">
                <input type="text" id="vpdlCustomerSearch" placeholder="<?php esc_attr_e( 'Search clients by name, contact, postcode, or council...', 'vpdl-management' ); ?>" class="vpdl-cust-search-input">
            </div>

            <div class="vpdl-cust-responsive-table">
                <table class="vpdl-cust-table" id="vpdlClientsTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Client / Owner', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Classification', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Council / Postcode', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Assigned Rep', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Gross Settled', 'vpdl-management' ); ?></th>
                            <th class="vpdl-th-right"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $customers ) ) : ?>
                            <?php foreach ( $customers as $cust ) : ?>
                                <tr class="vpdl-cust-row">
                                    <td class="vpdl-cust-id-cell">
                                        #<?php echo esc_html( (string) $cust->id ); ?>
                                    </td>
                                    <td>
                                        <strong class="vpdl-cust-name-title"><?php echo esc_html( $cust->name ); ?></strong>
                                        <div class="vpdl-cust-name-phone"><?php echo esc_html( $cust->phone ); ?></div>
                                    </td>
                                    <td>
                                        <span class="vpdl-cust-pill vpdl-pill-accent">
                                            <?php echo esc_html( $cust->client_type ?: __( 'Homeowner', 'vpdl-management' ) ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="vpdl-cust-lpa-title"><?php echo esc_html( $cust->council_name ?: '—' ); ?></strong>
                                        <div class="vpdl-cust-lpa-postcode"><?php echo esc_html( $cust->postcode ?: '—' ); ?></div>
                                    </td>
                                    <td class="vpdl-cust-agent-cell">
                                        <?php echo esc_html( $cust->agent_name ?: __( 'System', 'vpdl-management' ) ); ?>
                                    </td>
                                    <td class="vpdl-cust-val-settled">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $cust->lifetime_value, 2 ) ); ?>
                                    </td>
                                    <td class="vpdl-cust-actions-cell">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'customer_id' => $cust->id ), $base_url ) ); ?>" class="vpdl-cust-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'customer_id' => $cust->id ), $base_url ) ); ?>" class="vpdl-cust-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" style="display:inline;" data-action="delete-customer">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_customer">
                                                <input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $cust->id ); ?>">
                                                <button type="submit" class="vpdl-cust-action-btn vpdl-btn-delete">
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
                                <td colspan="7" class="vpdl-cust-empty-state">
                                    <?php esc_html_e( 'No client accounts found. Click the "Add Customer" tab above to get started.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="vpdl-cust-pagination">
                    <div>
                        <?php 
                        $start = $offset + 1;
                        $end   = min( $offset + $per_page, $total_customers );
                        printf( esc_html__( 'Showing %1$d–%2$d of %3$d clients', 'vpdl-management' ), absint( $start ), absint( $end ), absint( $total_customers ) ); 
                        ?>
                    </div>
                    <div class="vpdl-cust-pagination-links">
                        <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'list', 'paged' => $i ), $base_url ) ); ?>" class="vpdl-cust-page-link <?php echo ( $i === $paged ) ? 'is-current' : ''; ?>">
                                <?php echo esc_html( (string) $i ); ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: ADD NEW CUSTOMER FORM -->
    <div id="vpdlCustTabPaneAdd" class="vpdl-cust-tab-pane <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-cust-card vpdl-cust-form-card">
            <div style="margin-bottom: 20px;">
                <h3 class="vpdl-cust-form-title">
                    <?php esc_html_e( 'Register New Client & Project Site Dossier', 'vpdl-management' ); ?>
                </h3>
                <p class="vpdl-cust-form-subtitle">
                    <?php esc_html_e( 'Create a new customer profile to link with Planning, Structural, Surveying or Construction Scheme quotes.', 'vpdl-management' ); ?>
                </p>
            </div>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=vpdl' ) ); ?>">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="save_customer">
                
                <!-- 1. Row: Name, Phone & Email -->
                <div class="vpdl-cust-grid-3 vpdl-cust-form-group">
                    <div>
                        <label for="vpdl_add_name"><?php esc_html_e( 'Client / Owner Name', 'vpdl-management' ); ?> *</label>
                        <input type="text" id="vpdl_add_name" name="name" required placeholder="<?php esc_attr_e( 'e.g. David Harrison', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_add_phone"><?php esc_html_e( 'Phone / WhatsApp Contact', 'vpdl-management' ); ?> *</label>
                        <input type="tel" id="vpdl_add_phone" name="phone" required placeholder="<?php esc_attr_e( 'e.g. +44 7911 123456', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_add_email"><?php esc_html_e( 'Email Address', 'vpdl-management' ); ?></label>
                        <input type="email" id="vpdl_add_email" name="email" placeholder="<?php esc_attr_e( 'e.g. client@example.co.uk', 'vpdl-management' ); ?>">
                    </div>
                </div>

                <!-- 2. Row: Client Classification, Council & Assigned Rep -->
                <div class="vpdl-cust-grid-3 vpdl-cust-form-group">
                    <div>
                        <label for="vpdl_add_client_type"><?php esc_html_e( 'Client Classification', 'vpdl-management' ); ?></label>
                        <select id="vpdl_add_client_type" name="client_type">
                            <option value="Homeowner"><?php esc_html_e( 'Residential Homeowner', 'vpdl-management' ); ?></option>
                            <option value="Property Developer"><?php esc_html_e( 'Property Developer', 'vpdl-management' ); ?></option>
                            <option value="Commercial Contractor"><?php esc_html_e( 'Commercial Contractor', 'vpdl-management' ); ?></option>
                            <option value="Architectural Partner"><?php esc_html_e( 'Corporate / Architect Partner', 'vpdl-management' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="vpdl_add_council"><?php esc_html_e( 'Local Council (LPA)', 'vpdl-management' ); ?></label>
                        <input type="text" id="vpdl_add_council" name="council_name" placeholder="<?php esc_attr_e( 'e.g. London Borough of Newham', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_add_assigned"><?php esc_html_e( 'Assigned Representative', 'vpdl-management' ); ?></label>
                        <select id="vpdl_add_assigned" name="assigned_to">
                            <?php foreach ( $team_users as $u ) : ?>
                                <option value="<?php echo esc_attr( (string) $u->ID ); ?>" <?php selected( get_current_user_id(), $u->ID ); ?>>
                                    <?php echo esc_html( $u->display_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 3. Row: Property Address & Postcode -->
                <div class="vpdl-cust-grid-addr vpdl-cust-form-group">
                    <div>
                        <label for="vpdl_add_address"><?php esc_html_e( 'Project Site Address', 'vpdl-management' ); ?></label>
                        <input type="text" id="vpdl_add_address" name="site_address" placeholder="<?php esc_attr_e( 'e.g. 182-184 High Street North, London', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_add_postcode"><?php esc_html_e( 'Site Postcode', 'vpdl-management' ); ?></label>
                        <input type="text" id="vpdl_add_postcode" name="postcode" placeholder="<?php esc_attr_e( 'e.g. E6 2JA', 'vpdl-management' ); ?>" style="font-family: monospace; font-weight: 700;">
                    </div>
                </div>

                <!-- 4. Row: Lead Source -->
                <div class="vpdl-cust-form-group">
                    <label for="vpdl_add_source"><?php esc_html_e( 'Lead Acquisition Source', 'vpdl-management' ); ?></label>
                    <select id="vpdl_add_source" name="lead_source">
                        <option value="Website Inbound"><?php esc_html_e( 'Website Inbound Form', 'vpdl-management' ); ?></option>
                        <option value="WhatsApp Direct"><?php esc_html_e( 'Direct WhatsApp Contact', 'vpdl-management' ); ?></option>
                        <option value="Referral"><?php esc_html_e( 'Word of Mouth / Client Referral', 'vpdl-management' ); ?></option>
                        <option value="Architect Partner"><?php esc_html_e( 'Architect / Builder Recommendation', 'vpdl-management' ); ?></option>
                        <option value="Returning Client"><?php esc_html_e( 'Returning Client', 'vpdl-management' ); ?></option>
                    </select>
                </div>

                <button type="submit" class="vpdl-cust-btn-primary">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Save & Register Client', 'vpdl-management' ); ?></span>
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

</div>