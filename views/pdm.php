<?php
/**
 * View: Planning Design Management (PDM) Service Catalog & Booking Engine
 * File: views/pdm.php
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

$t_cust     = $wpdb->prefix . 'vpdl_customers';
$t_services = $wpdb->prefix . 'vpdl_services';
$t_inq      = $wpdb->prefix . 'vpdl_inquiries';

$currency = (string) get_option( 'vpdl_currency', '£' );
$vat_rate = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url = admin_url( 'admin.php?page=vpdl&view=pdm' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'catalog' : 'history' );
$target_id   = isset( $_GET['inquiry_id'] ) ? absint( $_GET['inquiry_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete PDM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_pdm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete planning case.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_inq, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Edit PDM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'update_pdm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $case_id     = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    $status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'In Progress';
    $followup    = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';
    $notes       = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
    $council_ref = isset( $_POST['council_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['council_ref'] ) ) : '';
    $portal_link = isset( $_POST['portal_link'] ) ? esc_url_raw( wp_unslash( $_POST['portal_link'] ) ) : '';

    if ( $case_id > 0 ) {
        $meta_tags = '';
        if ( ! empty( $council_ref ) ) {
            $meta_tags .= '[LPA Ref: ' . $council_ref . "] \n";
        }
        if ( ! empty( $portal_link ) ) {
            $meta_tags .= '[Portal: ' . $portal_link . "] \n";
        }

        $cleaned_notes = preg_replace( '/\[LPA Ref:.*?\]\s*/', '', $notes );
        $cleaned_notes = preg_replace( '/\[Portal:.*?\]\s*/', '', $cleaned_notes );
        $final_notes   = trim( $meta_tags . trim( $cleaned_notes ) );

        $wpdb->update(
            $t_inq,
            array(
                'status'             => $status,
                'next_followup_date' => $followup,
                'notes'              => $final_notes,
            ),
            array( 'id' => $case_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_updated' ), $base_url ) );
        exit;
    }
}

// 3. New PDM Service Booking Handler
if ( isset( $_POST['vpdl_action'] ) && 'book_pdm_service' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $customer_id     = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    $service_code    = isset( $_POST['service_code'] ) ? sanitize_text_field( wp_unslash( $_POST['service_code'] ) ) : '';
    $app_type        = isset( $_POST['app_type'] ) ? sanitize_text_field( wp_unslash( $_POST['app_type'] ) ) : 'Householder Planning';
    $council_name    = isset( $_POST['council_name'] ) ? sanitize_text_field( wp_unslash( $_POST['council_name'] ) ) : '';
    $council_ref     = isset( $_POST['council_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['council_ref'] ) ) : '';
    $is_conservation = isset( $_POST['is_conservation'] ) ? sanitize_text_field( wp_unslash( $_POST['is_conservation'] ) ) : 'No';
    $portal_link     = isset( $_POST['portal_link'] ) ? esc_url_raw( wp_unslash( $_POST['portal_link'] ) ) : '';
    $raw_notes       = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
    $followup        = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';

    if ( $customer_id > 0 && ! empty( $service_code ) ) {
        $final_service_title = 'Planning Design (' . $service_code . ')';

        $full_notes = '';
        $full_notes .= '[App Type: ' . $app_type . "] \n";
        if ( ! empty( $council_name ) ) {
            $full_notes .= '[Council/LPA: ' . $council_name . "] \n";
        }
        if ( ! empty( $council_ref ) ) {
            $full_notes .= '[LPA Ref: ' . $council_ref . "] \n";
        }
        $full_notes .= '[Conservation Area: ' . $is_conservation . "] \n";
        if ( ! empty( $portal_link ) ) {
            $full_notes .= '[Portal: ' . $portal_link . "] \n";
        }
        $full_notes .= $raw_notes;

        $wpdb->insert(
            $t_inq,
            array(
                'customer_id'        => $customer_id,
                'service_category'   => $final_service_title,
                'notes'              => $full_notes,
                'status'             => 'Validation Pending',
                'next_followup_date' => ! empty( $followup ) ? $followup : current_time( 'Y-m-d' ),
                'created_by'         => wp_get_current_user()->display_name,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_booked' ), $base_url ) );
        exit;
    }
}

// 4. Tariff Rate Card: Add New Service Code
if ( isset( $_POST['vpdl_action'] ) && 'add_pdm_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
    $desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
    $fee  = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

    if ( ! empty( $code ) && ! empty( $desc ) ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_services} WHERE category = 'PDM' AND code = %s", $code ) );
        if ( ! $exists ) {
            $wpdb->insert(
                $t_services,
                array(
                    'category'    => 'PDM',
                    'code'        => $code,
                    'description' => $desc,
                    'fee'         => $fee,
                ),
                array( '%s', '%s', '%s', '%f' )
            );
            wp_safe_redirect( add_query_arg( array( 'tab' => 'tariff', 'msg' => 'tariff_added' ), $base_url ) );
            exit;
        } else {
            wp_safe_redirect( add_query_arg( array( 'tab' => 'tariff', 'msg' => 'tariff_exists' ), $base_url ) );
            exit;
        }
    }
}

// 5. Tariff Rate Card: Update Service Code
if ( isset( $_POST['vpdl_action'] ) && 'update_pdm_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
    $desc       = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
    $fee        = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

    if ( $service_id > 0 && ! empty( $desc ) ) {
        $wpdb->update(
            $t_services,
            array(
                'description' => $desc,
                'fee'         => $fee,
            ),
            array( 'id' => $service_id ),
            array( '%s', '%f' ),
            array( '%d' )
        );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'tariff', 'msg' => 'tariff_updated' ), $base_url ) );
        exit;
    }
}

// 6. Tariff Rate Card: Delete Service Code
if ( isset( $_POST['vpdl_action'] ) && 'delete_pdm_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
    if ( $service_id > 0 ) {
        $wpdb->delete( $t_services, array( 'id' => $service_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'tariff', 'msg' => 'tariff_deleted' ), $base_url ) );
        exit;
    }
}

// PDM Services & Customers Lookup
$pdm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'PDM' ORDER BY code ASC" );
$customers    = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode, council_name FROM {$t_cust} ORDER BY name ASC" );

// Existing PDM Inquiries History
$pdm_history = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode, c.email AS customer_email
     FROM {$t_inq} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
     WHERE i.service_category LIKE '%Planning Design%' 
     ORDER BY i.id DESC 
     LIMIT 50"
);

// View / Edit Single Case Data
$single_case = null;
if ( ( 'view' === $action_mode || 'edit' === $action_mode ) && $target_id > 0 ) {
    $single_case = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode, c.email AS customer_email 
             FROM {$t_inq} i 
             LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
             WHERE i.id = %d",
            $target_id
        )
    );
}

// Flash Notifications Mapping
$alerts = array(
    'case_deleted'   => array( 'msg' => __( 'Planning Design case permanently removed from register.', 'vpdl-management' ), 'type' => 'success' ),
    'case_updated'   => array( 'msg' => __( 'Planning case updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'case_booked'    => array( 'msg' => __( 'Planning Design booking logged into CRM successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_added'   => array( 'msg' => __( 'New PDM Tariff item added to catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'PDM Tariff item updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_deleted' => array( 'msg' => __( 'PDM Tariff item removed from catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_exists'  => array( 'msg' => __( 'Error: Service code already exists in catalog.', 'vpdl-management' ), 'type' => 'error' ),
);
?>

<div class="vpdl-pdm-container" id="vpdlPdmModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-pdm-alert <?php echo 'error' === $alerts[ $msg_code ]['type'] ? 'vpdl-pdm-alert-error' : 'vpdl-pdm-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[ $msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-pdm-card vpdl-pdm-header-card">
        <div class="vpdl-pdm-header-left">
            <div class="vpdl-pdm-header-icon">
                <?php echo vpdl_get_svg( 'planning', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-pdm-header-title"><?php esc_html_e( 'Planning Design Management (PDM)', 'vpdl-management' ); ?></h2>
                <div class="vpdl-pdm-header-subtitle">
                    <?php esc_html_e( 'Architectural Drawings, Permitted Development, Full Planning & Conversion Portfolios', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-pdm-pill vpdl-pill-amber">
                <?php esc_html_e( 'All Fees Are Excluding VAT', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_case ) : 
    preg_match( '/\[LPA Ref:\s*(.*?)\]/', $single_case->notes, $ref_matches );
    $extracted_lpa_ref = $ref_matches[1] ?? __( 'Pending Submission', 'vpdl-management' );

    preg_match( '/\[App Type:\s*(.*?)\]/', $single_case->notes, $app_matches );
    $extracted_app_type = $app_matches[1] ?? __( 'Planning Application', 'vpdl-management' );

    preg_match( '/\[Portal:\s*(.*?)\]/', $single_case->notes, $portal_matches );
    $extracted_portal_link = $portal_matches[1] ?? '';
?>
    <!-- VIEW MODE: DETAILED PDM CASE DOSSIER -->
    <div class="vpdl-pdm-card vpdl-pdm-card-pad">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                    <?php echo vpdl_get_svg( 'planning', '', 20 ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_case->customer_name ); ?>
                        <span style="font-family: monospace; color: var(--vpdl-accent);">#PDM-CASE-<?php echo esc_html( (string) $single_case->id ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <?php echo esc_html( $extracted_app_type ); ?> • <strong><?php echo esc_html( $single_case->service_category ); ?></strong>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&customer_id=' . $single_case->customer_id ) ); ?>" class="vpdl-pdm-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create Proposal', 'vpdl-management' ); ?></span>
                </a>
                <?php if ( ! empty( $extracted_portal_link ) ) : ?>
                    <a href="<?php echo esc_url( $extracted_portal_link ); ?>" target="_blank" rel="noopener noreferrer" class="vpdl-pdm-action-btn" style="background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); border-color: rgba(2, 132, 199, 0.2); padding: 8px 14px;">
                        <span><?php esc_html_e( 'Planning Portal', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="vpdl-pdm-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to History', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div class="vpdl-pdm-metrics-grid">
            <div class="vpdl-pdm-card vpdl-pdm-metric-box">
                <div class="vpdl-pdm-metric-lbl"><?php esc_html_e( 'Client Contact', 'vpdl-management' ); ?></div>
                <div class="vpdl-pdm-metric-val" style="font-family: monospace;"><?php echo esc_html( $single_case->customer_phone ); ?></div>
                <div class="vpdl-pdm-metric-sub"><?php echo esc_html( $single_case->customer_email ?: __( 'No email on file', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-pdm-card vpdl-pdm-metric-box">
                <div class="vpdl-pdm-metric-lbl"><?php esc_html_e( 'Planning Lifecycle Status', 'vpdl-management' ); ?></div>
                <div class="vpdl-pdm-metric-val" style="color: #10b981;">
                    <?php echo esc_html( $single_case->status ); ?>
                </div>
                <div class="vpdl-pdm-metric-sub">
                    <?php esc_html_e( 'Lead Agent:', 'vpdl-management' ); ?> <?php echo esc_html( $single_case->created_by ); ?>
                </div>
            </div>

            <div class="vpdl-pdm-card vpdl-pdm-metric-box">
                <div class="vpdl-pdm-metric-lbl"><?php esc_html_e( 'Council LPA Ref / Milestone', 'vpdl-management' ); ?></div>
                <div class="vpdl-pdm-metric-val" style="color: var(--vpdl-accent); font-family: monospace;">
                    <?php echo esc_html( $extracted_lpa_ref ); ?>
                </div>
                <div class="vpdl-pdm-metric-sub">
                    <?php esc_html_e( 'Target Date:', 'vpdl-management' ); ?> <?php echo esc_html( $single_case->next_followup_date ?: __( 'None Set', 'vpdl-management' ) ); ?>
                </div>
            </div>

            <div class="vpdl-pdm-card vpdl-pdm-metric-box">
                <div class="vpdl-pdm-metric-lbl"><?php esc_html_e( 'Site Property Location', 'vpdl-management' ); ?></div>
                <div class="vpdl-pdm-metric-val" style="font-size: 13px; line-height: 1.4;">
                    <?php echo esc_html( $single_case->site_address ?: __( 'Standard Consultation Site', 'vpdl-management' ) ); ?>
                    <?php if ( ! empty( $single_case->postcode ) ) : ?>
                        <div style="font-family: monospace; color: var(--vpdl-accent); margin-top: 2px;"><?php echo esc_html( $single_case->postcode ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vpdl-pdm-card" style="padding: 20px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Scope Notes & Planning Details', 'vpdl-management' ); ?></h4>
            <div class="vpdl-pdm-notes-preview"><?php echo esc_html( $single_case->notes ?: __( 'No notes recorded.', 'vpdl-management' ) ); ?></div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_case ) : 
    preg_match( '/\[LPA Ref:\s*(.*?)\]/', $single_case->notes, $ref_matches );
    $edit_lpa_ref = $ref_matches[1] ?? '';

    preg_match( '/\[Portal:\s*(.*?)\]/', $single_case->notes, $portal_matches );
    $edit_portal = $portal_matches[1] ?? '';
?>
    <!-- EDIT MODE: PDM CASE UPDATE FORM -->
    <div class="vpdl-pdm-card vpdl-pdm-card-max">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'edit', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit Planning Design Case', 'vpdl-management' ); ?> (#PDM-CASE-<?php echo esc_html( (string) $single_case->id ); ?>)</span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_pdm_case">
            <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $single_case->id ); ?>">

            <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                <div>
                    <label for="vpdl_edit_status"><?php esc_html_e( 'Planning Lifecycle Status', 'vpdl-management' ); ?></label>
                    <select name="status" id="vpdl_edit_status" class="vpdl-pdm-select">
                        <option value="In Progress" <?php selected( $single_case->status, 'In Progress' ); ?>><?php esc_html_e( 'In Progress (Drawings)', 'vpdl-management' ); ?></option>
                        <option value="Validation Pending" <?php selected( $single_case->status, 'Validation Pending' ); ?>><?php esc_html_e( 'Submitted - Validation Pending', 'vpdl-management' ); ?></option>
                        <option value="Consultation Period" <?php selected( $single_case->status, 'Consultation Period' ); ?>><?php esc_html_e( 'Under Public Consultation', 'vpdl-management' ); ?></option>
                        <option value="Approved" <?php selected( $single_case->status, 'Approved' ); ?>><?php esc_html_e( 'Planning Granted (Approved)', 'vpdl-management' ); ?></option>
                        <option value="Refused" <?php selected( $single_case->status, 'Refused' ); ?>><?php esc_html_e( 'Application Refused', 'vpdl-management' ); ?></option>
                        <option value="Appeal Lodged" <?php selected( $single_case->status, 'Appeal Lodged' ); ?>><?php esc_html_e( 'Planning Appeal Lodged', 'vpdl-management' ); ?></option>
                        <option value="Won" <?php selected( $single_case->status, 'Won' ); ?>><?php esc_html_e( 'Won / Closed', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_edit_council_ref"><?php esc_html_e( 'Council LPA Reference No.', 'vpdl-management' ); ?></label>
                    <input type="text" name="council_ref" id="vpdl_edit_council_ref" value="<?php echo esc_attr( $edit_lpa_ref ); ?>" placeholder="<?php esc_attr_e( 'e.g. 24/01482/HSE', 'vpdl-management' ); ?>" class="vpdl-pdm-input" style="font-family: monospace;">
                </div>
            </div>

            <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                <div>
                    <label for="vpdl_edit_followup"><?php esc_html_e( 'Statutory Decision / Target Date', 'vpdl-management' ); ?></label>
                    <input type="date" name="next_followup_date" id="vpdl_edit_followup" value="<?php echo esc_attr( $single_case->next_followup_date ); ?>" class="vpdl-pdm-input">
                </div>
                <div>
                    <label for="vpdl_edit_portal_link"><?php esc_html_e( 'Planning Portal Direct URL', 'vpdl-management' ); ?></label>
                    <input type="url" name="portal_link" id="vpdl_edit_portal_link" value="<?php echo esc_attr( $edit_portal ); ?>" placeholder="https://www.planningportal.co.uk/..." class="vpdl-pdm-input">
                </div>
            </div>

            <div class="vpdl-pdm-form-group">
                <label for="vpdl_edit_notes"><?php esc_html_e( 'Scope Notes & Conditions', 'vpdl-management' ); ?></label>
                <textarea name="notes" id="vpdl_edit_notes" rows="4" class="vpdl-pdm-textarea"><?php echo esc_textarea( $single_case->notes ); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Update Planning Case', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION -->
    <div class="vpdl-pdm-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="catalog" id="vpdlPdmTabBtnCatalog" class="vpdl-pdm-tab-btn <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'catalog' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'planning', '', 18 ); ?>
            <span><?php esc_html_e( 'Service Assignment', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="history" id="vpdlPdmTabBtnHistory" class="vpdl-pdm-tab-btn <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'history' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
            <span><?php esc_html_e( 'Planning Applications History', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $pdm_history ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="tariff" id="vpdlPdmTabBtnTariff" class="vpdl-pdm-tab-btn <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'tariff' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 18 ); ?>
            <span><?php esc_html_e( 'PDM Tariff Manager', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Service Assignment & Direct Booking -->
    <div id="vpdlPdmTabPaneCatalog" class="vpdl-pdm-tab-pane <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-pdm-grid-catalog">
            
            <!-- Left Side: Form -->
            <div class="vpdl-pdm-card vpdl-pdm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'customers', '', 18 ); ?>
                    <span><?php esc_html_e( 'Assign Planning Service', 'vpdl-management' ); ?></span>
                </h3>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                    <input type="hidden" name="vpdl_action" value="book_pdm_service">

                    <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                        <div>
                            <label for="vpdlPdmClientSelect"><?php esc_html_e( 'Select Client', 'vpdl-management' ); ?> *</label>
                            <select name="customer_id" id="vpdlPdmClientSelect" required class="vpdl-pdm-select">
                                <option value=""><?php esc_html_e( '-- Choose Existing Customer --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $customers as $c ) : ?>
                                    <option value="<?php echo esc_attr( (string) $c->id ); ?>" data-council="<?php echo esc_attr( $c->council_name ); ?>">
                                        <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="vpdlPdmCodeSelector"><?php esc_html_e( 'Service Requirement', 'vpdl-management' ); ?> *</label>
                            <select name="service_code" id="vpdlPdmCodeSelector" required class="vpdl-pdm-select">
                                <option value=""><?php esc_html_e( '-- Select Planning Service --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $pdm_services as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->code ); ?>" data-fee="<?php echo esc_attr( (string) $s->fee ); ?>">
                                        <?php echo esc_html( $s->code . ' - ' . $s->description . ' (' . $currency . number_format( (float) $s->fee, 2 ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                        <div>
                            <label for="vpdl_pdm_app_type"><?php esc_html_e( 'Application Route', 'vpdl-management' ); ?> *</label>
                            <select name="app_type" id="vpdl_pdm_app_type" class="vpdl-pdm-select">
                                <option value="Householder Planning Permission"><?php esc_html_e( 'Householder Planning Permission', 'vpdl-management' ); ?></option>
                                <option value="Lawful Development Certificate (LDC)"><?php esc_html_e( 'Permitted Development (LDC)', 'vpdl-management' ); ?></option>
                                <option value="Full Planning Permission"><?php esc_html_e( 'Full Planning Permission', 'vpdl-management' ); ?></option>
                                <option value="Prior Approval (Larger Extension)"><?php esc_html_e( 'Prior Approval (Larger Extension)', 'vpdl-management' ); ?></option>
                                <option value="Listed Building Consent"><?php esc_html_e( 'Listed Building Consent', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_pdm_conservation"><?php esc_html_e( 'Conservation Area?', 'vpdl-management' ); ?></label>
                            <select name="is_conservation" id="vpdl_pdm_conservation" class="vpdl-pdm-select">
                                <option value="No"><?php esc_html_e( 'No (Standard Rights)', 'vpdl-management' ); ?></option>
                                <option value="Yes (Article 4/Conservation)"><?php esc_html_e( 'Yes (Restricted PD Rights)', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                        <div>
                            <label for="vpdlPdmCouncilField"><?php esc_html_e( 'Local Council (LPA)', 'vpdl-management' ); ?></label>
                            <input type="text" name="council_name" id="vpdlPdmCouncilField" placeholder="<?php esc_attr_e( 'e.g. London Borough of Newham', 'vpdl-management' ); ?>" class="vpdl-pdm-input">
                        </div>
                        <div>
                            <label for="vpdl_pdm_council_ref"><?php esc_html_e( 'Council LPA Ref (If Exists)', 'vpdl-management' ); ?></label>
                            <input type="text" name="council_ref" id="vpdl_pdm_council_ref" placeholder="<?php esc_attr_e( 'e.g. 24/01482/HSE', 'vpdl-management' ); ?>" class="vpdl-pdm-input" style="font-family: monospace;">
                        </div>
                    </div>

                    <div class="vpdl-pdm-grid-2 vpdl-pdm-form-group">
                        <div>
                            <label for="vpdl_pdm_survey_date"><?php esc_html_e( 'Site Survey Date', 'vpdl-management' ); ?></label>
                            <input type="date" name="next_followup_date" id="vpdl_pdm_survey_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="vpdl-pdm-input">
                        </div>
                        <div>
                            <label for="vpdl_pdm_portal_link"><?php esc_html_e( 'Portal Submission URL', 'vpdl-management' ); ?></label>
                            <input type="url" name="portal_link" id="vpdl_pdm_portal_link" placeholder="https://www.planningportal.co.uk/..." class="vpdl-pdm-input">
                        </div>
                    </div>

                    <div class="vpdl-pdm-form-group">
                        <label for="vpdl_pdm_notes"><?php esc_html_e( 'Site Notes / Scope Details', 'vpdl-management' ); ?></label>
                        <textarea name="notes" id="vpdl_pdm_notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. 3m rear single-storey extension, L-shaped dormer loft conversion...', 'vpdl-management' ); ?>" class="vpdl-pdm-textarea"></textarea>
                    </div>

                    <!-- Live Pricing Summary -->
                    <div class="vpdl-pdm-pricing-summary">
                        <div class="vpdl-pdm-pricing-row">
                            <span><?php esc_html_e( 'Net Fee:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispPdmNet">0.00</span></strong>
                        </div>
                        <div class="vpdl-pdm-pricing-row">
                            <span><?php printf( esc_html__( 'VAT (%d%%):', 'vpdl-management' ), absint( $vat_rate ) ); ?></span>
                            <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispPdmVat">0.00</span></strong>
                        </div>
                        <div class="vpdl-pdm-pricing-grand">
                            <span><?php esc_html_e( 'Gross Amount:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispPdmTotal">0.00</span></strong>
                        </div>
                    </div>

                    <button type="submit" class="vpdl-pdm-btn-primary">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Confirm PDM Assignment', 'vpdl-management' ); ?></span>
                    </button>
                </form>
            </div>

            <!-- Right Side: Rate Chart & Services List -->
            <div class="vpdl-pdm-card vpdl-pdm-card-pad">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Official Rate Card (PDM)', 'vpdl-management' ); ?>
                    </h3>
                    <button type="button" data-action="switch-tab" data-tab="tariff" style="font-size: 11.5px; font-weight: 700; color: var(--vpdl-accent); background: transparent; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                        <span><?php esc_html_e( 'Edit Tariff', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </button>
                </div>

                <div class="vpdl-pdm-table-wrap">
                    <table class="vpdl-pdm-table">
                        <thead>
                            <tr>
                                <th style="width: 75px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 110px;"><?php esc_html_e( 'Fee (Ex. VAT)', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $pdm_services ) ) : ?>
                                <?php foreach ( $pdm_services as $s ) : ?>
                                    <tr class="vpdl-pdm-row">
                                        <td style="font-family: monospace; font-weight: 800; color: var(--vpdl-accent);">
                                            <?php echo esc_html( $s->code ); ?>
                                        </td>
                                        <td style="color: var(--vpdl-text-main); font-weight: 600; line-height: 1.4;">
                                            <?php echo esc_html( $s->description ); ?>
                                        </td>
                                        <td style="text-align: right; font-family: monospace; font-weight: 800; color: #10b981; white-space: nowrap;">
                                            <?php echo esc_html( $currency . ' ' . number_format( (float) $s->fee, 2 ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--vpdl-text-muted); padding: 30px;">
                                        <?php esc_html_e( 'No PDM service codes configured.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 18px; padding: 14px; border-radius: 8px; background: rgba(2, 132, 199, 0.04); font-size: 12px; color: var(--vpdl-text-muted); line-height: 1.5;">
                    <strong><?php esc_html_e( 'Scope & Deliverables:', 'vpdl-management' ); ?></strong> <?php esc_html_e( 'Includes measured site survey, existing & proposed CAD drawings, design & access statements, and LPA planning portal submission handling.', 'vpdl-management' ); ?>
                </div>
            </div>

        </div>
    </div>

    <!-- TAB 2: Applications Tracking History -->
    <div id="vpdlPdmTabPaneHistory" class="vpdl-pdm-tab-pane <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-pdm-card vpdl-pdm-card-pad">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Planning Design & Permitted Development Register', 'vpdl-management' ); ?>
            </h3>

            <div class="vpdl-pdm-table-wrap">
                <table class="vpdl-pdm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Client / Location', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Service / App Route', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Authority & Ref', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Milestone / Review', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $pdm_history ) ) : ?>
                            <?php foreach ( $pdm_history as $case ) : 
                                preg_match( '/\[Council\/LPA:\s*(.*?)\]/', $case->notes, $c_matches );
                                $tbl_council = $c_matches[1] ?? '—';

                                preg_match( '/\[LPA Ref:\s*(.*?)\]/', $case->notes, $r_matches );
                                $tbl_ref = $r_matches[1] ?? '';
                            ?>
                                <tr class="vpdl-pdm-row">
                                    <td>
                                        <strong style="color: var(--vpdl-text-main); font-size: 13.5px;"><?php echo esc_html( $case->customer_name ); ?></strong>
                                        <div style="font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;">
                                            <?php echo esc_html( wp_trim_words( $case->site_address ?: $case->customer_phone, 4 ) ); ?>
                                            <?php if ( ! empty( $case->postcode ) ) : ?>
                                                • <span style="font-family: monospace; color: var(--vpdl-accent);"><?php echo esc_html( $case->postcode ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="vpdl-pdm-pill vpdl-pill-accent">
                                            <?php echo esc_html( $case->service_category ); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <div style="color: var(--vpdl-text-main); font-weight: 600;"><?php echo esc_html( $tbl_council ); ?></div>
                                        <?php if ( ! empty( $tbl_ref ) ) : ?>
                                            <div style="font-family: monospace; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $tbl_ref ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted); font-size: 12px;">
                                        <?php echo esc_html( $case->next_followup_date ?: '—' ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-pdm-pill vpdl-pill-green">
                                            <?php echo esc_html( $case->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-pdm-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-pdm-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'planning design case', 'vpdl-management' ); ?>">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_pdm_case">
                                                <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $case->id ); ?>">
                                                <button type="submit" class="vpdl-pdm-action-btn vpdl-btn-delete">
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
                                <td colspan="6" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                    <?php esc_html_e( 'No active Planning Design applications registered.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Tariff Manager -->
    <div id="vpdlPdmTabPaneTariff" class="vpdl-pdm-tab-pane <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Add New Tariff Item Form -->
            <?php if ( $is_admin ) : ?>
                <div class="vpdl-pdm-card vpdl-pdm-card-pad">
                    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                        <span><?php esc_html_e( 'Add New Planning Design Tariff Item', 'vpdl-management' ); ?></span>
                    </h3>

                    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                        <input type="hidden" name="vpdl_action" value="add_pdm_tariff">

                        <div class="vpdl-tariff-grid-add">
                            <div>
                                <label for="vpdl_tariff_code" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Service Code', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_tariff_code" name="code" placeholder="<?php esc_attr_e( 'e.g. PDM 05', 'vpdl-management' ); ?>" required class="vpdl-pdm-input" style="font-family: monospace; font-weight: 800; text-transform: uppercase;">
                            </div>
                            <div>
                                <label for="vpdl_tariff_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Item / Scope Description', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_tariff_desc" name="description" placeholder="<?php esc_attr_e( 'e.g. Retrospective Planning / Certificate of Lawfulness', 'vpdl-management' ); ?>" required class="vpdl-pdm-input">
                            </div>
                            <div>
                                <label for="vpdl_tariff_fee" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Base Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                                <input type="number" step="0.01" min="0" id="vpdl_tariff_fee" name="fee" placeholder="0.00" required class="vpdl-pdm-input" style="font-family: monospace; font-weight: 800;">
                            </div>
                            <div>
                                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 22px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;">
                                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                                    <span><?php esc_html_e( 'Add to Catalog', 'vpdl-management' ); ?></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Active PDM Tariff Repeater -->
            <div class="vpdl-pdm-card vpdl-pdm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'planning', '', 16 ); ?>
                    <span><?php esc_html_e( 'Active Planning Design (PDM) Tariff Catalog', 'vpdl-management' ); ?></span>
                </h3>

                <div class="vpdl-pdm-table-wrap">
                    <table class="vpdl-pdm-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description / Service Scope', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 160px;"><?php esc_html_e( 'Fee (Excl. VAT)', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $pdm_services ) ) : ?>
                                <?php foreach ( $pdm_services as $srv ) : ?>
                                    <tr class="vpdl-pdm-row">
                                        <td style="font-family: monospace; font-weight: 800; color: var(--vpdl-accent);">
                                            <?php echo esc_html( $srv->code ); ?>
                                        </td>
                                        <td style="font-weight: 600; color: var(--vpdl-text-main);">
                                            <?php echo esc_html( $srv->description ); ?>
                                        </td>
                                        <td style="text-align: right; font-family: monospace; font-weight: 800; color: #10b981; font-size: 14px;">
                                            <?php echo esc_html( $currency . ' ' . number_format( (float) $srv->fee, 2 ) ); ?>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <?php if ( $is_admin ) : ?>
                                                <button type="button" data-action="open-tariff-modal" data-id="<?php echo (int) $srv->id; ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-fee="<?php echo (float) $srv->fee; ?>" class="vpdl-pdm-action-btn vpdl-btn-edit">
                                                    <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                                </button>
                                                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'tariff item', 'vpdl-management' ); ?>">
                                                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                    <input type="hidden" name="vpdl_action" value="delete_pdm_tariff">
                                                    <input type="hidden" name="service_id" value="<?php echo (int) $srv->id; ?>">
                                                    <button type="submit" class="vpdl-pdm-action-btn vpdl-btn-delete">
                                                        <?php echo vpdl_get_svg( 'trash', '', 13 ); ?>
                                                        <span><?php esc_html_e( 'Delete', 'vpdl-management' ); ?></span>
                                                    </button>
                                                </form>
                                            <?php else : ?>
                                                <span style="font-size: 11px; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Protected', 'vpdl-management' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                        <?php esc_html_e( 'No PDM service codes registered in catalog.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
<?php endif; ?>

</div>

<!-- EDIT TARIFF MODAL -->
<div id="vpdlEditTariffModal" role="dialog" aria-modal="true" aria-labelledby="tariffModalTitle">
    <div class="vpdl-modal-card-tariff">
        <div class="vpdl-modal-header-tariff">
            <h3 id="tariffModalTitle" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit PDM Tariff Service', 'vpdl-management' ); ?> (<span id="modal_tariff_code" style="font-family: monospace; color: var(--vpdl-accent);"></span>)</span>
            </h3>
            <button type="button" data-action="close-tariff-modal" class="vpdl-modal-btn-close">
                <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_pdm_tariff">
            <input type="hidden" name="service_id" id="modal_tariff_id" value="">

            <div class="vpdl-pdm-form-group">
                <label for="modal_tariff_desc"><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?> *</label>
                <input type="text" name="description" id="modal_tariff_desc" required class="vpdl-pdm-input">
            </div>

            <div class="vpdl-pdm-form-group">
                <label for="modal_tariff_fee"><?php esc_html_e( 'Standard Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                <input type="number" step="0.01" min="0" name="fee" id="modal_tariff_fee" required class="vpdl-pdm-input" style="font-family: monospace; font-weight: 800;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top: 20px;">
                <button type="button" data-action="close-tariff-modal" class="vpdl-pdm-action-btn" style="background: var(--vpdl-card-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                    <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
                </button>
                <button type="submit" class="button button-primary" style="height: 38px; padding: 0 20px; font-weight: 700; background: var(--vpdl-accent); border: none; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo vpdl_get_svg( 'check', '', 14 ); ?>
                    <span><?php esc_html_e( 'Save Changes', 'vpdl-management' ); ?></span>
                </button>
            </div>
        </form>
    </div>
</div>