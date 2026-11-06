<?php
/**
 * View: Structural Design Management (SDM) Service Catalog & Booking Engine
 * File: views/sdm.php
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
$base_url = admin_url( 'admin.php?page=vpdl&view=sdm' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'catalog' : 'history' );
$target_id   = isset( $_GET['inquiry_id'] ) ? absint( $_GET['inquiry_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete SDM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_sdm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete structural case.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_inq, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Edit SDM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'update_sdm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $case_id   = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    $status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'In Progress';
    $followup  = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';
    $bc_ref    = isset( $_POST['bc_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['bc_ref'] ) ) : '';
    $beam_span = isset( $_POST['beam_span'] ) ? sanitize_text_field( wp_unslash( $_POST['beam_span'] ) ) : '';
    $notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    if ( $case_id > 0 ) {
        $meta_tags = '';
        if ( ! empty( $bc_ref ) ) {
            $meta_tags .= '[BC Ref: ' . $bc_ref . '] ';
        }
        if ( ! empty( $beam_span ) ) {
            $meta_tags .= '[Beam Span: ' . $beam_span . '] ';
        }

        $cleaned_notes = preg_replace( '/\[BC Ref:.*?\]\s*/', '', $notes );
        $cleaned_notes = preg_replace( '/\[Beam Span:.*?\]\s*/', '', $cleaned_notes );
        $final_notes   = trim( $meta_tags . "\n" . trim( $cleaned_notes ) );

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

// 3. New SDM Service Booking Handler
if ( isset( $_POST['vpdl_action'] ) && 'book_sdm_service' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $customer_id   = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    $service_code  = isset( $_POST['service_code'] ) ? sanitize_text_field( wp_unslash( $_POST['service_code'] ) ) : '';
    $custom_desc   = isset( $_POST['service_sub_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['service_sub_desc'] ) ) : '';
    $calc_category = isset( $_POST['calc_category'] ) ? sanitize_text_field( wp_unslash( $_POST['calc_category'] ) ) : '';
    $bc_route      = isset( $_POST['bc_route'] ) ? sanitize_text_field( wp_unslash( $_POST['bc_route'] ) ) : 'Local Authority (LABC)';
    $bc_ref        = isset( $_POST['bc_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['bc_ref'] ) ) : '';
    $beam_span     = isset( $_POST['beam_span'] ) ? sanitize_text_field( wp_unslash( $_POST['beam_span'] ) ) : '';
    $raw_notes     = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
    $followup      = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';

    if ( $customer_id > 0 && ! empty( $service_code ) ) {
        $final_service_title = 'Structural Design (' . $service_code . ')';
        if ( ! empty( $custom_desc ) ) {
            $final_service_title .= ' - ' . $custom_desc;
        }

        $full_notes = '';
        if ( ! empty( $calc_category ) ) {
            $full_notes .= '[Scope: ' . $calc_category . "] \n";
        }
        $full_notes .= '[Building Control: ' . $bc_route . "] \n";
        if ( ! empty( $bc_ref ) ) {
            $full_notes .= '[BC Ref: ' . $bc_ref . "] \n";
        }
        if ( ! empty( $beam_span ) ) {
            $full_notes .= '[Beam Span: ' . $beam_span . "] \n";
        }
        $full_notes .= $raw_notes;

        $wpdb->insert(
            $t_inq,
            array(
                'customer_id'        => $customer_id,
                'service_category'   => $final_service_title,
                'notes'              => $full_notes,
                'status'             => 'In Progress',
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
if ( isset( $_POST['vpdl_action'] ) && 'add_sdm_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
    $desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
    $fee  = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

    if ( ! empty( $code ) && ! empty( $desc ) ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_services} WHERE category = 'SDM' AND code = %s AND description = %s", $code, $desc ) );
        if ( ! $exists ) {
            $wpdb->insert(
                $t_services,
                array(
                    'category'    => 'SDM',
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
if ( isset( $_POST['vpdl_action'] ) && 'update_sdm_tariff' === $_POST['vpdl_action'] ) {
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
if ( isset( $_POST['vpdl_action'] ) && 'delete_sdm_tariff' === $_POST['vpdl_action'] ) {
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

// SDM Services & Customers Lookup
$sdm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'SDM' ORDER BY code ASC, fee ASC" );
$customers    = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode FROM {$t_cust} ORDER BY name ASC" );

// Existing SDM Inquiries History
$sdm_history = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode, c.email AS customer_email 
     FROM {$t_inq} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
     WHERE i.service_category LIKE '%Structural Design%' 
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
    'case_deleted'   => array( 'msg' => __( 'Structural case permanently removed from register.', 'vpdl-management' ), 'type' => 'success' ),
    'case_updated'   => array( 'msg' => __( 'Structural case updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'case_booked'    => array( 'msg' => __( 'Structural Design booking logged into CRM successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_added'   => array( 'msg' => __( 'New SDM Tariff item added to catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'SDM Tariff item updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_deleted' => array( 'msg' => __( 'SDM Tariff item removed from catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_exists'  => array( 'msg' => __( 'Error: This exact SDM service option already exists.', 'vpdl-management' ), 'type' => 'error' ),
);
?>

<div class="vpdl-sdm-container" id="vpdlSdmModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-sdm-alert <?php echo 'error' === $alerts[ $msg_code ]['type'] ? 'vpdl-sdm-alert-error' : 'vpdl-sdm-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[ $msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-sdm-card vpdl-sdm-header-card">
        <div class="vpdl-sdm-header-left">
            <div class="vpdl-sdm-header-icon">
                <?php echo vpdl_get_svg( 'structural', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-sdm-header-title"><?php esc_html_e( 'Structural Design Management (SDM)', 'vpdl-management' ); ?></h2>
                <div class="vpdl-sdm-header-subtitle">
                    <?php esc_html_e( 'Civil & Structural Engineering Calculations, Steel RSJ Sizing, Padstones & Building Regs Pack', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-sdm-pill vpdl-pill-amber">
                <?php esc_html_e( 'All Engineering Fees Are Excluding VAT', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_case ) : 
    preg_match( '/\[BC Ref:\s*(.*?)\]/', $single_case->notes, $bc_matches );
    $extracted_bc_ref = $bc_matches[1] ?? __( 'Pending Building Control', 'vpdl-management' );

    preg_match( '/\[Beam Span:\s*(.*?)\]/', $single_case->notes, $span_matches );
    $extracted_span = $span_matches[1] ?? __( 'Standard Span', 'vpdl-management' );

    preg_match( '/\[Building Control:\s*(.*?)\]/', $single_case->notes, $route_matches );
    $extracted_route = $route_matches[1] ?? 'LABC';
?>
    <!-- VIEW MODE: DETAILED SDM CASE DOSSIER -->
    <div class="vpdl-sdm-card vpdl-sdm-card-pad">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                    <?php echo vpdl_get_svg( 'structural', '', 20 ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_case->customer_name ); ?>
                        <span style="font-family: monospace; color: var(--vpdl-accent);">#SDM-CASE-<?php echo esc_html( (string) $single_case->id ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <?php echo esc_html( $single_case->service_category ); ?> • <?php esc_html_e( 'Route:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $extracted_route ); ?></strong>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&customer_id=' . $single_case->customer_id . '&service_category=SDM' ) ); ?>" class="vpdl-sdm-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create Proposal', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="vpdl-sdm-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to History', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div class="vpdl-sdm-metrics-grid">
            <div class="vpdl-sdm-card vpdl-sdm-metric-box">
                <div class="vpdl-sdm-metric-lbl"><?php esc_html_e( 'Client Contact', 'vpdl-management' ); ?></div>
                <div class="vpdl-sdm-metric-val" style="font-family: monospace;"><?php echo esc_html( $single_case->customer_phone ); ?></div>
                <div class="vpdl-sdm-metric-sub"><?php echo esc_html( $single_case->customer_email ?: __( 'No email on file', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-sdm-card vpdl-sdm-metric-box">
                <div class="vpdl-sdm-metric-lbl"><?php esc_html_e( 'Calculations Status', 'vpdl-management' ); ?></div>
                <div class="vpdl-sdm-metric-val" style="color: #10b981;">
                    <?php echo esc_html( $single_case->status ); ?>
                </div>
                <div class="vpdl-sdm-metric-sub">
                    <?php esc_html_e( 'Structural Engineer:', 'vpdl-management' ); ?> <?php echo esc_html( $single_case->created_by ); ?>
                </div>
            </div>

            <div class="vpdl-sdm-card vpdl-sdm-metric-box">
                <div class="vpdl-sdm-metric-lbl"><?php esc_html_e( 'Building Control Ref / Span', 'vpdl-management' ); ?></div>
                <div class="vpdl-sdm-metric-val" style="color: var(--vpdl-accent); font-family: monospace;">
                    <?php echo esc_html( $extracted_bc_ref ); ?>
                </div>
                <div class="vpdl-sdm-metric-sub">
                    <?php esc_html_e( 'Span:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $extracted_span ); ?></strong>
                </div>
            </div>

            <div class="vpdl-sdm-card vpdl-sdm-metric-box">
                <div class="vpdl-sdm-metric-lbl"><?php esc_html_e( 'Site Property Location', 'vpdl-management' ); ?></div>
                <div class="vpdl-sdm-metric-val" style="font-size: 13px; line-height: 1.4;">
                    <?php echo esc_html( $single_case->site_address ?: __( 'Standard Engineering Site', 'vpdl-management' ) ); ?>
                    <?php if ( ! empty( $single_case->postcode ) ) : ?>
                        <div style="font-family: monospace; color: var(--vpdl-accent); margin-top: 2px;"><?php echo esc_html( $single_case->postcode ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vpdl-sdm-card" style="padding: 20px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Engineering Specifications & Notes', 'vpdl-management' ); ?></h4>
            <div class="vpdl-sdm-notes-preview"><?php echo esc_html( $single_case->notes ?: __( 'No notes recorded.', 'vpdl-management' ) ); ?></div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_case ) : 
    preg_match( '/\[BC Ref:\s*(.*?)\]/', $single_case->notes, $bc_matches );
    $edit_bc_ref = $bc_matches[1] ?? '';

    preg_match( '/\[Beam Span:\s*(.*?)\]/', $single_case->notes, $span_matches );
    $edit_span = $span_matches[1] ?? '';
?>
    <!-- EDIT MODE: SDM CASE UPDATE FORM -->
    <div class="vpdl-sdm-card vpdl-sdm-card-max">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'edit', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit Structural Design Case', 'vpdl-management' ); ?> (#SDM-CASE-<?php echo esc_html( (string) $single_case->id ); ?>)</span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_sdm_case">
            <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $single_case->id ); ?>">

            <div class="vpdl-sdm-grid-2 vpdl-sdm-form-group">
                <div>
                    <label for="vpdl_sdm_edit_status"><?php esc_html_e( 'Case Pipeline Status', 'vpdl-management' ); ?></label>
                    <select name="status" id="vpdl_sdm_edit_status" class="vpdl-sdm-select">
                        <option value="New" <?php selected( $single_case->status, 'New' ); ?>><?php esc_html_e( 'New', 'vpdl-management' ); ?></option>
                        <option value="In Progress" <?php selected( $single_case->status, 'In Progress' ); ?>><?php esc_html_e( 'In Progress (Modeling)', 'vpdl-management' ); ?></option>
                        <option value="Calculations Ready" <?php selected( $single_case->status, 'Calculations Ready' ); ?>><?php esc_html_e( 'Calculations Ready (Pack Issued)', 'vpdl-management' ); ?></option>
                        <option value="Building Control Approved" <?php selected( $single_case->status, 'Building Control Approved' ); ?>><?php esc_html_e( 'Building Control Approved', 'vpdl-management' ); ?></option>
                        <option value="Revision Requested" <?php selected( $single_case->status, 'Revision Requested' ); ?>><?php esc_html_e( 'BC Query / Revision Requested', 'vpdl-management' ); ?></option>
                        <option value="Won" <?php selected( $single_case->status, 'Won' ); ?>><?php esc_html_e( 'Won / Closed', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_sdm_edit_bc_ref"><?php esc_html_e( 'Building Control Ref No.', 'vpdl-management' ); ?></label>
                    <input type="text" name="bc_ref" id="vpdl_sdm_edit_bc_ref" value="<?php echo esc_attr( $edit_bc_ref ); ?>" placeholder="<?php esc_attr_e( 'e.g. BC/24/00892', 'vpdl-management' ); ?>" class="vpdl-sdm-input" style="font-family: monospace;">
                </div>
            </div>

            <div class="vpdl-sdm-grid-2 vpdl-sdm-form-group">
                <div>
                    <label for="vpdl_sdm_edit_followup"><?php esc_html_e( 'Target Delivery / Inspection Date', 'vpdl-management' ); ?></label>
                    <input type="date" name="next_followup_date" id="vpdl_sdm_edit_followup" value="<?php echo esc_attr( $single_case->next_followup_date ); ?>" class="vpdl-sdm-input">
                </div>
                <div>
                    <label for="vpdl_sdm_edit_beam_span"><?php esc_html_e( 'Primary Opening Span (m)', 'vpdl-management' ); ?></label>
                    <input type="text" name="beam_span" id="vpdl_sdm_edit_beam_span" value="<?php echo esc_attr( $edit_span ); ?>" placeholder="<?php esc_attr_e( 'e.g. 4.2m Clear Opening', 'vpdl-management' ); ?>" class="vpdl-sdm-input">
                </div>
            </div>

            <div class="vpdl-sdm-form-group">
                <label for="vpdl_sdm_edit_notes"><?php esc_html_e( 'Engineering Specifications / Notes', 'vpdl-management' ); ?></label>
                <textarea name="notes" id="vpdl_sdm_edit_notes" rows="4" class="vpdl-sdm-textarea"><?php echo esc_textarea( $single_case->notes ); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Update Structural Case', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION -->
    <div class="vpdl-sdm-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="catalog" id="vpdlSdmTabBtnCatalog" class="vpdl-sdm-tab-btn <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'catalog' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'structural', '', 18 ); ?>
            <span><?php esc_html_e( 'Service Assignment', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="history" id="vpdlSdmTabBtnHistory" class="vpdl-sdm-tab-btn <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'history' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
            <span><?php esc_html_e( 'Structural Calculations Log', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $sdm_history ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="tariff" id="vpdlSdmTabBtnTariff" class="vpdl-sdm-tab-btn <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'tariff' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 18 ); ?>
            <span><?php esc_html_e( 'SDM Tariff Manager', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Service Booking & Assignment Form -->
    <div id="vpdlSdmTabPaneCatalog" class="vpdl-sdm-tab-pane <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-sdm-grid-catalog">
            
            <!-- Left Side: Booking & Inquiry Form -->
            <div class="vpdl-sdm-card vpdl-sdm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'customers', '', 18 ); ?>
                    <span><?php esc_html_e( 'Assign Structural Service', 'vpdl-management' ); ?></span>
                </h3>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                    <input type="hidden" name="vpdl_action" value="book_sdm_service">
                    <input type="hidden" name="service_sub_desc" id="vpdlSdmSubDesc" value="">

                    <div class="vpdl-sdm-grid-2 vpdl-sdm-form-group">
                        <div>
                            <label for="vpdl_sdm_customer_select"><?php esc_html_e( 'Select Client', 'vpdl-management' ); ?> *</label>
                            <select name="customer_id" id="vpdl_sdm_customer_select" required class="vpdl-sdm-select">
                                <option value=""><?php esc_html_e( '-- Choose Existing Customer --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $customers as $c ) : ?>
                                    <option value="<?php echo esc_attr( (string) $c->id ); ?>">
                                        <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="vpdlSdmCodeSelector"><?php esc_html_e( 'Structural Package Option', 'vpdl-management' ); ?> *</label>
                            <select name="service_code" id="vpdlSdmCodeSelector" required class="vpdl-sdm-select">
                                <option value=""><?php esc_html_e( '-- Select Structural Service --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $sdm_services as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->code ); ?>" data-fee="<?php echo esc_attr( (string) $s->fee ); ?>" data-desc="<?php echo esc_attr( $s->description ); ?>">
                                        <?php echo esc_html( $s->code . ' - ' . $s->description . ' (' . $currency . number_format( (float) $s->fee, 2 ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="vpdl-sdm-grid-2 vpdl-sdm-form-group">
                        <div>
                            <label for="vpdl_sdm_calc_cat"><?php esc_html_e( 'Primary Engineering Focus', 'vpdl-management' ); ?> *</label>
                            <select name="calc_category" id="vpdl_sdm_calc_cat" class="vpdl-sdm-select">
                                <option value="Steel RSJ Beams & Load Bearing Wall Removal" selected><?php esc_html_e( 'Steel RSJ Beams & Load Bearing Knockout', 'vpdl-management' ); ?></option>
                                <option value="Loft Ridge & Purlin Steel Framing"><?php esc_html_e( 'Loft Ridge & Purlin Steel Framing', 'vpdl-management' ); ?></option>
                                <option value="Chimney Breast Gallows Brackets / Steel Support"><?php esc_html_e( 'Chimney Breast Removal & Steel Support', 'vpdl-management' ); ?></option>
                                <option value="Foundation Underpinning & Raft Calcs"><?php esc_html_e( 'Foundation Underpinning & Concrete Raft', 'vpdl-management' ); ?></option>
                                <option value="Timber Floor Joists & Trimmer Calculations"><?php esc_html_e( 'Timber Joists & Trimmer Sizing', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_sdm_beam_span"><?php esc_html_e( 'Clear Opening / Beam Span (m)', 'vpdl-management' ); ?></label>
                            <input type="text" name="beam_span" id="vpdl_sdm_beam_span" placeholder="<?php esc_attr_e( 'e.g. 3.8m or 4.5m opening', 'vpdl-management' ); ?>" class="vpdl-sdm-input">
                        </div>
                    </div>

                    <div class="vpdl-sdm-grid-2 vpdl-sdm-form-group">
                        <div>
                            <label for="vpdl_sdm_bc_route"><?php esc_html_e( 'Building Control Authority', 'vpdl-management' ); ?></label>
                            <select name="bc_route" id="vpdl_sdm_bc_route" class="vpdl-sdm-select">
                                <option value="Local Authority (LABC)"><?php esc_html_e( 'Local Authority Building Control (LABC)', 'vpdl-management' ); ?></option>
                                <option value="Approved Inspector (Private BC)"><?php esc_html_e( 'Approved Inspector (Private BC Body)', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_sdm_bc_ref"><?php esc_html_e( 'Building Control Ref (If known)', 'vpdl-management' ); ?></label>
                            <input type="text" name="bc_ref" id="vpdl_sdm_bc_ref" placeholder="<?php esc_attr_e( 'e.g. BC/24/00892', 'vpdl-management' ); ?>" class="vpdl-sdm-input" style="font-family: monospace;">
                        </div>
                    </div>

                    <div class="vpdl-sdm-form-group">
                        <label for="vpdl_sdm_next_date"><?php esc_html_e( 'Site Inspection / Delivery Target Date', 'vpdl-management' ); ?></label>
                        <input type="date" name="next_followup_date" id="vpdl_sdm_next_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="vpdl-sdm-input">
                    </div>

                    <div class="vpdl-sdm-form-group">
                        <label for="vpdl_sdm_notes"><?php esc_html_e( 'Engineering Specifications / Scope Notes', 'vpdl-management' ); ?></label>
                        <textarea name="notes" id="vpdl_sdm_notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. Steel RSJ requirements for rear wall knockout, concrete padstone sizing, Building Regs Part A compliance package...', 'vpdl-management' ); ?>" class="vpdl-sdm-textarea"></textarea>
                    </div>

                    <!-- Live Pricing Summary -->
                    <div class="vpdl-sdm-pricing-summary">
                        <div class="vpdl-sdm-pricing-row">
                            <span><?php esc_html_e( 'Net Fee:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispSdmNet">0.00</span></strong>
                        </div>
                        <div class="vpdl-sdm-pricing-row">
                            <span><?php printf( esc_html__( 'VAT (%d%%):', 'vpdl-management' ), absint( $vat_rate ) ); ?></span>
                            <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispSdmVat">0.00</span></strong>
                        </div>
                        <div class="vpdl-sdm-pricing-grand">
                            <span><?php esc_html_e( 'Gross Amount:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispSdmTotal">0.00</span></strong>
                        </div>
                    </div>

                    <button type="submit" class="vpdl-sdm-btn-primary">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Confirm SDM Assignment', 'vpdl-management' ); ?></span>
                    </button>
                </form>
            </div>

            <!-- Right Side: Rate Chart & Services List -->
            <div class="vpdl-sdm-card vpdl-sdm-card-pad">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Official Rate Card (SDM)', 'vpdl-management' ); ?>
                    </h3>
                    <button type="button" data-action="switch-tab" data-tab="tariff" style="font-size: 11.5px; font-weight: 700; color: var(--vpdl-accent); background: transparent; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                        <span><?php esc_html_e( 'Edit Tariff', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </button>
                </div>

                <div class="vpdl-sdm-table-wrap">
                    <table class="vpdl-sdm-table">
                        <thead>
                            <tr>
                                <th style="width: 75px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 110px;"><?php esc_html_e( 'Fee (Ex. VAT)', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $sdm_services ) ) : ?>
                                <?php foreach ( $sdm_services as $s ) : ?>
                                    <tr class="vpdl-sdm-row">
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
                                        <?php esc_html_e( 'No SDM service codes configured.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 18px; padding: 14px; border-radius: 8px; background: rgba(2, 132, 199, 0.04); font-size: 12px; color: var(--vpdl-text-muted); line-height: 1.5;">
                    <strong><?php esc_html_e( 'Structural Deliverables:', 'vpdl-management' ); ?></strong> <?php esc_html_e( 'Deliverables include full structural calculation packages for Building Control approval, steel beam sizing schedules, connection details, padstone specifications, and foundation layout coordination.', 'vpdl-management' ); ?>
                </div>
            </div>

        </div>
    </div>

    <!-- TAB 2: Calculations Tracking History -->
    <div id="vpdlSdmTabPaneHistory" class="vpdl-sdm-tab-pane <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-sdm-card vpdl-sdm-card-pad">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Structural Engineering Calculations Register', 'vpdl-management' ); ?>
            </h3>

            <div class="vpdl-sdm-table-wrap">
                <table class="vpdl-sdm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Client / Site', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Package Scope', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Calculations & BC Ref', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Target Date', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $sdm_history ) ) : ?>
                            <?php foreach ( $sdm_history as $case ) : 
                                preg_match( '/\[BC Ref:\s*(.*?)\]/', $case->notes, $b_matches );
                                $tbl_bc_ref = $b_matches[1] ?? '';

                                preg_match( '/\[Scope:\s*(.*?)\]/', $case->notes, $s_matches );
                                $tbl_scope = $s_matches[1] ?? __( 'Structural Calculations', 'vpdl-management' );
                            ?>
                                <tr class="vpdl-sdm-row">
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
                                        <span class="vpdl-sdm-pill vpdl-pill-accent">
                                            <?php echo esc_html( $case->service_category ); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <div style="color: var(--vpdl-text-main); font-weight: 600;"><?php echo esc_html( wp_trim_words( $tbl_scope, 5 ) ); ?></div>
                                        <?php if ( ! empty( $tbl_bc_ref ) ) : ?>
                                            <div style="font-family: monospace; color: var(--vpdl-text-muted); margin-top: 2px;"><?php printf( esc_html__( 'BC Ref: %s', 'vpdl-management' ), esc_html( $tbl_bc_ref ) ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted); font-size: 12px;">
                                        <?php echo esc_html( $case->next_followup_date ?: '—' ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-sdm-pill vpdl-pill-green">
                                            <?php echo esc_html( $case->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-sdm-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-sdm-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'structural design case', 'vpdl-management' ); ?>">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_sdm_case">
                                                <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $case->id ); ?>">
                                                <button type="submit" class="vpdl-sdm-action-btn vpdl-btn-delete">
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
                                    <?php esc_html_e( 'No active Structural Design calculations registered.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Tariff Manager -->
    <div id="vpdlSdmTabPaneTariff" class="vpdl-sdm-tab-pane <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Add New Tariff Item Form -->
            <?php if ( $is_admin ) : ?>
                <div class="vpdl-sdm-card vpdl-sdm-card-pad">
                    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                        <span><?php esc_html_e( 'Add New Structural Design Tariff Item', 'vpdl-management' ); ?></span>
                    </h3>

                    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                        <input type="hidden" name="vpdl_action" value="add_sdm_tariff">

                        <div class="vpdl-tariff-grid-add">
                            <div>
                                <label for="vpdl_sdm_tariff_code" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Service Code', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_sdm_tariff_code" name="code" placeholder="<?php esc_attr_e( 'e.g. SDM 05', 'vpdl-management' ); ?>" required class="vpdl-sdm-input" style="font-family: monospace; font-weight: 800; text-transform: uppercase;">
                            </div>
                            <div>
                                <label for="vpdl_sdm_tariff_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Item / Scope Description', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_sdm_tariff_desc" name="description" placeholder="<?php esc_attr_e( 'e.g. Retaining Wall & Foundation Underpinning Design', 'vpdl-management' ); ?>" required class="vpdl-sdm-input">
                            </div>
                            <div>
                                <label for="vpdl_sdm_tariff_fee" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Base Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                                <input type="number" step="0.01" min="0" id="vpdl_sdm_tariff_fee" name="fee" placeholder="0.00" required class="vpdl-sdm-input" style="font-family: monospace; font-weight: 800;">
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

            <!-- Active SDM Tariff Repeater -->
            <div class="vpdl-sdm-card vpdl-sdm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'structural', '', 16 ); ?>
                    <span><?php esc_html_e( 'Active Structural Design (SDM) Tariff Catalog', 'vpdl-management' ); ?></span>
                </h3>

                <div class="vpdl-sdm-table-wrap">
                    <table class="vpdl-sdm-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description / Service Scope', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 160px;"><?php esc_html_e( 'Fee (Excl. VAT)', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $sdm_services ) ) : ?>
                                <?php foreach ( $sdm_services as $srv ) : ?>
                                    <tr class="vpdl-sdm-row">
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
                                                <button type="button" data-action="open-tariff-modal" data-id="<?php echo (int) $srv->id; ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-fee="<?php echo (float) $srv->fee; ?>" class="vpdl-sdm-action-btn vpdl-btn-edit">
                                                    <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                                </button>
                                                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'tariff item', 'vpdl-management' ); ?>">
                                                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                    <input type="hidden" name="vpdl_action" value="delete_sdm_tariff">
                                                    <input type="hidden" name="service_id" value="<?php echo (int) $srv->id; ?>">
                                                    <button type="submit" class="vpdl-sdm-action-btn vpdl-btn-delete">
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
                                        <?php esc_html_e( 'No SDM service codes registered in catalog.', 'vpdl-management' ); ?>
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

<!-- EDIT SDM TARIFF MODAL -->
<div id="vpdlEditSdmTariffModal" role="dialog" aria-modal="true" aria-labelledby="tariffModalTitle">
    <div class="vpdl-modal-card-tariff">
        <div class="vpdl-modal-header-tariff">
            <h3 id="tariffModalTitle" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit SDM Tariff Service', 'vpdl-management' ); ?> (<span id="modal_sdm_tariff_code" style="font-family: monospace; color: var(--vpdl-accent);"></span>)</span>
            </h3>
            <button type="button" data-action="close-tariff-modal" class="vpdl-modal-btn-close">
                <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_sdm_tariff">
            <input type="hidden" name="service_id" id="modal_sdm_tariff_id" value="">

            <div class="vpdl-sdm-form-group">
                <label for="modal_sdm_tariff_desc"><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?> *</label>
                <input type="text" name="description" id="modal_sdm_tariff_desc" required class="vpdl-sdm-input">
            </div>

            <div class="vpdl-sdm-form-group">
                <label for="modal_sdm_tariff_fee"><?php esc_html_e( 'Standard Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                <input type="number" step="0.01" min="0" name="fee" id="modal_sdm_tariff_fee" required class="vpdl-sdm-input" style="font-family: monospace; font-weight: 800;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top: 20px;">
                <button type="button" data-action="close-tariff-modal" class="vpdl-sdm-action-btn" style="background: var(--vpdl-badge-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); padding: 8px 16px;">
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