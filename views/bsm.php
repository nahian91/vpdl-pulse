<?php
/**
 * View: Building Survey Management (BSM) Service Catalog & Booking Engine
 * File: views/bsm.php
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
$base_url = admin_url( 'admin.php?page=vpdl&view=bsm' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'catalog' : 'history' );
$target_id   = isset( $_GET['inquiry_id'] ) ? absint( $_GET['inquiry_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete BSM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_bsm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete survey case.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_inq, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Edit BSM Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'update_bsm_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $case_id        = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    $status         = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'In Progress';
    $followup       = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';
    $adjoining_addr = isset( $_POST['adjoining_address'] ) ? sanitize_text_field( wp_unslash( $_POST['adjoining_address'] ) ) : '';
    $adjoining_name = isset( $_POST['adjoining_name'] ) ? sanitize_text_field( wp_unslash( $_POST['adjoining_name'] ) ) : '';
    $soc_ref        = isset( $_POST['soc_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['soc_ref'] ) ) : '';
    $notes          = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    if ( $case_id > 0 ) {
        $meta_tags = '';
        if ( ! empty( $adjoining_name ) ) {
            $meta_tags .= '[Adjoining Owner: ' . $adjoining_name . "] \n";
        }
        if ( ! empty( $adjoining_addr ) ) {
            $meta_tags .= '[Adjoining Property: ' . $adjoining_addr . "] \n";
        }
        if ( ! empty( $soc_ref ) ) {
            $meta_tags .= '[SoC Reference: ' . $soc_ref . "] \n";
        }

        $cleaned_notes = preg_replace( '/\[Adjoining Owner:.*?\]\s*/', '', $notes );
        $cleaned_notes = preg_replace( '/\[Adjoining Property:.*?\]\s*/', '', $cleaned_notes );
        $cleaned_notes = preg_replace( '/\[SoC Reference:.*?\]\s*/', '', $cleaned_notes );
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

// 3. New BSM Service Booking Handler
if ( isset( $_POST['vpdl_action'] ) && 'book_bsm_service' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $customer_id    = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    $service_code   = isset( $_POST['service_code'] ) ? sanitize_text_field( wp_unslash( $_POST['service_code'] ) ) : '';
    $custom_desc    = isset( $_POST['service_sub_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['service_sub_desc'] ) ) : '';
    $notice_type    = isset( $_POST['notice_type'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_type'] ) ) : '';
    $surveyor_role  = isset( $_POST['surveyor_role'] ) ? sanitize_text_field( wp_unslash( $_POST['surveyor_role'] ) ) : 'Agreed Surveyor';
    $adjoining_name = isset( $_POST['adjoining_name'] ) ? sanitize_text_field( wp_unslash( $_POST['adjoining_name'] ) ) : '';
    $adjoining_addr = isset( $_POST['adjoining_address'] ) ? sanitize_text_field( wp_unslash( $_POST['adjoining_address'] ) ) : '';
    $soc_ref        = isset( $_POST['soc_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['soc_ref'] ) ) : '';
    $raw_notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
    $followup       = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';

    if ( $customer_id > 0 && ! empty( $service_code ) ) {
        $final_service_title = 'Building Survey (' . $service_code . ')';
        if ( ! empty( $custom_desc ) ) {
            $final_service_title .= ' - ' . $custom_desc;
        }

        $full_notes = '';
        if ( ! empty( $notice_type ) ) {
            $full_notes .= '[Notice: ' . $notice_type . "] \n";
        }
        $full_notes .= '[Surveyor Role: ' . $surveyor_role . "] \n";
        if ( ! empty( $adjoining_name ) ) {
            $full_notes .= '[Adjoining Owner: ' . $adjoining_name . "] \n";
        }
        if ( ! empty( $adjoining_addr ) ) {
            $full_notes .= '[Adjoining Property: ' . $adjoining_addr . "] \n";
        }
        if ( ! empty( $soc_ref ) ) {
            $full_notes .= '[SoC Reference: ' . $soc_ref . "] \n";
        }
        $full_notes .= $raw_notes;

        $wpdb->insert(
            $t_inq,
            array(
                'customer_id'        => $customer_id,
                'service_category'   => $final_service_title,
                'notes'              => $full_notes,
                'status'             => 'Notices Prepared',
                'next_followup_date' => ! empty( $followup ) ? $followup : gmdate( 'Y-m-d', strtotime( '+14 days' ) ),
                'created_by'         => wp_get_current_user()->display_name,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_booked' ), $base_url ) );
        exit;
    }
}

// 4. Tariff Rate Card: Add New Service Code
if ( isset( $_POST['vpdl_action'] ) && 'add_bsm_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
    $desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
    $fee  = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

    if ( ! empty( $code ) && ! empty( $desc ) ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_services} WHERE category = 'BSM' AND code = %s AND description = %s", $code, $desc ) );
        if ( ! $exists ) {
            $wpdb->insert(
                $t_services,
                array(
                    'category'    => 'BSM',
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
if ( isset( $_POST['vpdl_action'] ) && 'update_bsm_tariff' === $_POST['vpdl_action'] ) {
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
if ( isset( $_POST['vpdl_action'] ) && 'delete_bsm_tariff' === $_POST['vpdl_action'] ) {
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

// BSM Services & Customers Lookup
$bsm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'BSM' ORDER BY code ASC, fee ASC" );
$customers    = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode FROM {$t_cust} ORDER BY name ASC" );

// Existing BSM Inquiries History
$bsm_history = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode, c.email AS customer_email 
     FROM {$t_inq} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
     WHERE i.service_category LIKE '%Building Survey%' 
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
    'case_deleted'   => array( 'msg' => __( 'Building survey case permanently removed from register.', 'vpdl-management' ), 'type' => 'success' ),
    'case_updated'   => array( 'msg' => __( 'Building survey case updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'case_booked'    => array( 'msg' => __( 'Building survey booking logged into CRM successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_added'   => array( 'msg' => __( 'New BSM Tariff item added to catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'BSM Tariff item updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_deleted' => array( 'msg' => __( 'BSM Tariff item removed from catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_exists'  => array( 'msg' => __( 'Error: This exact BSM service option already exists.', 'vpdl-management' ), 'type' => 'error' ),
);
?>

<div class="vpdl-bsm-container" id="vpdlBsmModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-bsm-alert <?php echo 'error' === $alerts[ $msg_code ]['type'] ? 'vpdl-bsm-alert-error' : 'vpdl-bsm-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[ $msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-bsm-card vpdl-bsm-header-card">
        <div class="vpdl-bsm-header-left">
            <div class="vpdl-bsm-header-icon">
                <?php echo vpdl_get_svg( 'survey', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-bsm-header-title"><?php esc_html_e( 'Building Survey Management (BSM)', 'vpdl-management' ); ?></h2>
                <div class="vpdl-bsm-header-subtitle">
                    <?php esc_html_e( 'Statutory Party Wall etc. Act 1996, Schedules of Condition & Boundary Dispute Awards', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-bsm-pill vpdl-pill-amber">
                <?php esc_html_e( 'All Statutory Fees Are Excluding VAT', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_case ) : 
    preg_match( '/\[Notice:\s*(.*?)\]/', $single_case->notes, $n_matches );
    $extracted_notice = $n_matches[1] ?? 'Party Structure (Section 2)';

    preg_match( '/\[Surveyor Role:\s*(.*?)\]/', $single_case->notes, $r_matches );
    $extracted_role = $r_matches[1] ?? 'Agreed Surveyor';

    preg_match( '/\[Adjoining Owner:\s*(.*?)\]/', $single_case->notes, $ao_matches );
    $extracted_ao_name = $ao_matches[1] ?? __( 'Adjoining Property Owner', 'vpdl-management' );

    preg_match( '/\[Adjoining Property:\s*(.*?)\]/', $single_case->notes, $ap_matches );
    $extracted_ao_addr = $ap_matches[1] ?? __( 'Neighbouring Boundary', 'vpdl-management' );
?>
    <!-- VIEW MODE: DETAILED BSM CASE DOSSIER -->
    <div class="vpdl-bsm-card vpdl-bsm-card-pad">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                    <?php echo vpdl_get_svg( 'survey', '', 20 ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_case->customer_name ); ?>
                        <span style="font-family: monospace; color: var(--vpdl-accent);">#BSM-CASE-<?php echo esc_html( (string) $single_case->id ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <?php esc_html_e( 'Capacity:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $extracted_role ); ?></strong> • <?php echo esc_html( $extracted_notice ); ?>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&customer_id=' . $single_case->customer_id . '&service_category=BSM' ) ); ?>" class="vpdl-bsm-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create Proposal', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="vpdl-bsm-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to History', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div class="vpdl-bsm-metrics-grid">
            <div class="vpdl-bsm-card vpdl-bsm-metric-box">
                <div class="vpdl-bsm-metric-lbl"><?php esc_html_e( 'Building Owner (Client)', 'vpdl-management' ); ?></div>
                <div class="vpdl-bsm-metric-val" style="font-family: monospace;"><?php echo esc_html( $single_case->customer_phone ); ?></div>
                <div class="vpdl-bsm-metric-sub"><?php echo esc_html( $single_case->customer_email ?: __( 'No email on file', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-bsm-card vpdl-bsm-metric-box">
                <div class="vpdl-bsm-metric-lbl"><?php esc_html_e( 'Adjoining Neighbour (AO)', 'vpdl-management' ); ?></div>
                <div class="vpdl-bsm-metric-val" style="color: var(--vpdl-accent);">
                    <?php echo esc_html( $extracted_ao_name ); ?>
                </div>
                <div class="vpdl-bsm-metric-sub">
                    <?php echo esc_html( $extracted_ao_addr ); ?>
                </div>
            </div>

            <div class="vpdl-bsm-card vpdl-bsm-metric-box">
                <div class="vpdl-bsm-metric-lbl"><?php esc_html_e( 'Notice Response / Deadline', 'vpdl-management' ); ?></div>
                <div class="vpdl-bsm-metric-val" style="color: #10b981; font-family: monospace;">
                    <?php echo esc_html( $single_case->next_followup_date ?: __( 'None Set', 'vpdl-management' ) ); ?>
                </div>
                <div class="vpdl-bsm-metric-sub">
                    <?php esc_html_e( 'Status:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_case->status ); ?></strong>
                </div>
            </div>

            <div class="vpdl-bsm-card vpdl-bsm-metric-box">
                <div class="vpdl-bsm-metric-lbl"><?php esc_html_e( 'Appointed Surveyor', 'vpdl-management' ); ?></div>
                <div class="vpdl-bsm-metric-val" style="font-size: 14px;">
                    <?php echo esc_html( $single_case->created_by ); ?>
                </div>
                <div class="vpdl-bsm-metric-sub">
                    <?php echo esc_html( $single_case->site_address ?: __( 'Standard Survey Site', 'vpdl-management' ) ); ?>
                </div>
            </div>
        </div>

        <div class="vpdl-bsm-card" style="padding: 20px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Party Wall Notes & Adjoining Details', 'vpdl-management' ); ?></h4>
            <div class="vpdl-bsm-notes-preview"><?php echo esc_html( $single_case->notes ?: __( 'No notes recorded.', 'vpdl-management' ) ); ?></div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_case ) : 
    preg_match( '/\[Adjoining Owner:\s*(.*?)\]/', $single_case->notes, $ao_matches );
    $edit_ao_name = $ao_matches[1] ?? '';

    preg_match( '/\[Adjoining Property:\s*(.*?)\]/', $single_case->notes, $ap_matches );
    $edit_ao_addr = $ap_matches[1] ?? '';

    preg_match( '/\[SoC Reference:\s*(.*?)\]/', $single_case->notes, $soc_matches );
    $edit_soc_ref = $soc_matches[1] ?? '';
?>
    <!-- EDIT MODE: BSM CASE UPDATE FORM -->
    <div class="vpdl-bsm-card vpdl-bsm-card-max">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'edit', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit Building Survey Case', 'vpdl-management' ); ?> (#BSM-CASE-<?php echo esc_html( (string) $single_case->id ); ?>)</span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_bsm_case">
            <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $single_case->id ); ?>">

            <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                <div>
                    <label for="vpdl_bsm_edit_status"><?php esc_html_e( 'Party Wall Legal Stage', 'vpdl-management' ); ?></label>
                    <select name="status" id="vpdl_bsm_edit_status" class="vpdl-bsm-select">
                        <option value="Notices Prepared" <?php selected( $single_case->status, 'Notices Prepared' ); ?>><?php esc_html_e( 'Notices Prepared', 'vpdl-management' ); ?></option>
                        <option value="Notices Served (14-Day Clock)" <?php selected( $single_case->status, 'Notices Served (14-Day Clock)' ); ?>><?php esc_html_e( 'Notices Served (14-Day Clock)', 'vpdl-management' ); ?></option>
                        <option value="Consent Granted" <?php selected( $single_case->status, 'Consent Granted' ); ?>><?php esc_html_e( 'Adjoining Owner Consented', 'vpdl-management' ); ?></option>
                        <option value="Dispute Arisen (Dissent)" <?php selected( $single_case->status, 'Dispute Arisen (Dissent)' ); ?>><?php esc_html_e( 'Dispute Arisen (Dissent)', 'vpdl-management' ); ?></option>
                        <option value="Schedule of Condition Booked" <?php selected( $single_case->status, 'Schedule of Condition Booked' ); ?>><?php esc_html_e( 'Schedule of Condition Booked', 'vpdl-management' ); ?></option>
                        <option value="Award Agreed & Signed" <?php selected( $single_case->status, 'Award Agreed & Signed' ); ?>><?php esc_html_e( 'Party Wall Award Agreed & Signed', 'vpdl-management' ); ?></option>
                        <option value="Won" <?php selected( $single_case->status, 'Won' ); ?>><?php esc_html_e( 'Won / Closed', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_bsm_edit_followup"><?php esc_html_e( 'Statutory 14-Day / Inspection Date', 'vpdl-management' ); ?></label>
                    <input type="date" name="next_followup_date" id="vpdl_bsm_edit_followup" value="<?php echo esc_attr( $single_case->next_followup_date ); ?>" class="vpdl-bsm-input">
                </div>
            </div>

            <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                <div>
                    <label for="vpdl_bsm_edit_ao_name"><?php esc_html_e( 'Adjoining Owner Name', 'vpdl-management' ); ?></label>
                    <input type="text" name="adjoining_name" id="vpdl_bsm_edit_ao_name" value="<?php echo esc_attr( $edit_ao_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Mr. John Jenkins', 'vpdl-management' ); ?>" class="vpdl-bsm-input">
                </div>
                <div>
                    <label for="vpdl_bsm_edit_soc_ref"><?php esc_html_e( 'Schedule of Condition Ref', 'vpdl-management' ); ?></label>
                    <input type="text" name="soc_ref" id="vpdl_bsm_edit_soc_ref" value="<?php echo esc_attr( $edit_soc_ref ); ?>" placeholder="<?php esc_attr_e( 'e.g. SOC/24/001', 'vpdl-management' ); ?>" class="vpdl-bsm-input" style="font-family: monospace;">
                </div>
            </div>

            <div class="vpdl-bsm-form-group">
                <label for="vpdl_bsm_edit_ao_addr"><?php esc_html_e( 'Adjoining Property Address', 'vpdl-management' ); ?></label>
                <input type="text" name="adjoining_address" id="vpdl_bsm_edit_ao_addr" value="<?php echo esc_attr( $edit_ao_addr ); ?>" placeholder="<?php esc_attr_e( 'e.g. 180 High Street North, London, E6 2JA', 'vpdl-management' ); ?>" class="vpdl-bsm-input">
            </div>

            <div class="vpdl-bsm-form-group">
                <label for="vpdl_bsm_edit_notes"><?php esc_html_e( 'Party Wall Notes & Adjoining Details', 'vpdl-management' ); ?></label>
                <textarea name="notes" id="vpdl_bsm_edit_notes" rows="4" class="vpdl-bsm-textarea"><?php echo esc_textarea( $single_case->notes ); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Update Survey Case', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION -->
    <div class="vpdl-bsm-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="catalog" id="vpdlBsmTabBtnCatalog" class="vpdl-bsm-tab-btn <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'catalog' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'survey', '', 18 ); ?>
            <span><?php esc_html_e( 'Service Assignment', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="history" id="vpdlBsmTabBtnHistory" class="vpdl-bsm-tab-btn <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'history' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
            <span><?php esc_html_e( 'Party Wall Cases Log', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $bsm_history ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="tariff" id="vpdlBsmTabBtnTariff" class="vpdl-bsm-tab-btn <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'tariff' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 18 ); ?>
            <span><?php esc_html_e( 'BSM Tariff Manager', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Service Booking & Assignment Form -->
    <div id="vpdlBsmTabPaneCatalog" class="vpdl-bsm-tab-pane <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-bsm-grid-catalog">
            
            <!-- Left Side: Form -->
            <div class="vpdl-bsm-card vpdl-bsm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'customers', '', 18 ); ?>
                    <span><?php esc_html_e( 'Assign Party Wall Service', 'vpdl-management' ); ?></span>
                </h3>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                    <input type="hidden" name="vpdl_action" value="book_bsm_service">
                    <input type="hidden" name="service_sub_desc" id="vpdlBsmSubDesc" value="">

                    <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                        <div>
                            <label for="vpdl_bsm_customer_select"><?php esc_html_e( 'Building Owner (Client)', 'vpdl-management' ); ?> *</label>
                            <select name="customer_id" id="vpdl_bsm_customer_select" required class="vpdl-bsm-select">
                                <option value=""><?php esc_html_e( '-- Choose Existing Customer --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $customers as $c ) : ?>
                                    <option value="<?php echo esc_attr( (string) $c->id ); ?>">
                                        <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="vpdlBsmCodeSelector"><?php esc_html_e( 'Party Wall Package', 'vpdl-management' ); ?> *</label>
                            <select name="service_code" id="vpdlBsmCodeSelector" required class="vpdl-bsm-select">
                                <option value=""><?php esc_html_e( '-- Select Survey Service --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $bsm_services as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->code ); ?>" data-fee="<?php echo esc_attr( (string) $s->fee ); ?>" data-desc="<?php echo esc_attr( $s->description ); ?>">
                                        <?php echo esc_html( $s->code . ' - ' . $s->description . ' (' . $currency . number_format( (float) $s->fee, 2 ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Notice Category & Surveyor Role -->
                    <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                        <div>
                            <label for="vpdl_bsm_notice_type"><?php esc_html_e( 'Statutory Notice Category', 'vpdl-management' ); ?> *</label>
                            <select name="notice_type" id="vpdl_bsm_notice_type" class="vpdl-bsm-select">
                                <option value="Section 2: Party Structure Notice" selected><?php esc_html_e( 'Section 2: Party Structure (Beams/Knockout/Chimney)', 'vpdl-management' ); ?></option>
                                <option value="Section 6: 3m / 6m Adjacent Excavation"><?php esc_html_e( 'Section 6: Adjacent Excavation (Within 3m or 6m)', 'vpdl-management' ); ?></option>
                                <option value="Section 1: Line of Junction Notice"><?php esc_html_e( 'Section 1: Line of Junction Notice (New Boundary Wall)', 'vpdl-management' ); ?></option>
                                <option value="Combined Section 2 & 6 Notices"><?php esc_html_e( 'Combined Section 2 & Section 6 Notices', 'vpdl-management' ); ?></option>
                                <option value="Schedule of Condition Only"><?php esc_html_e( 'Schedule of Condition Only (No Notice Required)', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_bsm_surveyor_role"><?php esc_html_e( 'Appointed Surveyor Capacity', 'vpdl-management' ); ?></label>
                            <select name="surveyor_role" id="vpdl_bsm_surveyor_role" class="vpdl-bsm-select">
                                <option value="Agreed Surveyor (Both Parties)" selected><?php esc_html_e( 'Agreed Surveyor (Both Parties Jointly)', 'vpdl-management' ); ?></option>
                                <option value="Building Owner's Appointed Surveyor"><?php esc_html_e( "Building Owner's Appointed Surveyor", 'vpdl-management' ); ?></option>
                                <option value="Adjoining Owner's Surveyor"><?php esc_html_e( "Adjoining Owner's Appointed Surveyor", 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Neighbour Owner & Address -->
                    <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                        <div>
                            <label for="vpdl_bsm_adjoining_name"><?php esc_html_e( 'Adjoining Owner Name(s)', 'vpdl-management' ); ?></label>
                            <input type="text" name="adjoining_name" id="vpdl_bsm_adjoining_name" placeholder="<?php esc_attr_e( 'e.g. Mr. & Mrs. Jenkins', 'vpdl-management' ); ?>" class="vpdl-bsm-input">
                        </div>
                        <div>
                            <label for="vpdl_bsm_adjoining_address"><?php esc_html_e( 'Adjoining Property Address', 'vpdl-management' ); ?></label>
                            <input type="text" name="adjoining_address" id="vpdl_bsm_adjoining_address" placeholder="<?php esc_attr_e( 'e.g. 180 High Street North, London', 'vpdl-management' ); ?>" class="vpdl-bsm-input">
                        </div>
                    </div>

                    <div class="vpdl-bsm-grid-2 vpdl-bsm-form-group">
                        <div>
                            <label for="vpdl_bsm_soc_ref"><?php esc_html_e( 'Schedule of Condition Ref / Job Code', 'vpdl-management' ); ?></label>
                            <input type="text" name="soc_ref" id="vpdl_bsm_soc_ref" placeholder="<?php esc_attr_e( 'e.g. SOC/24/001', 'vpdl-management' ); ?>" class="vpdl-bsm-input" style="font-family: monospace;">
                        </div>
                        <div>
                            <label for="vpdl_bsm_next_followup_date"><?php esc_html_e( 'Survey / Notice Date', 'vpdl-management' ); ?></label>
                            <input type="date" name="next_followup_date" id="vpdl_bsm_next_followup_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="vpdl-bsm-input">
                        </div>
                    </div>

                    <div class="vpdl-bsm-form-group">
                        <label for="vpdl_bsm_notes"><?php esc_html_e( 'Adjoining Properties & Survey Notes', 'vpdl-management' ); ?></label>
                        <textarea name="notes" id="vpdl_bsm_notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. Boundary wall shared on the east elevation, basement underpinning, 3m deep foundation notice served...', 'vpdl-management' ); ?>" class="vpdl-bsm-textarea"></textarea>
                    </div>

                    <!-- Live Pricing Summary -->
                    <div class="vpdl-bsm-pricing-summary">
                        <div class="vpdl-bsm-pricing-row">
                            <span><?php esc_html_e( 'Net Fee:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispBsmNet">0.00</span></strong>
                        </div>
                        <div class="vpdl-bsm-pricing-row">
                            <span><?php printf( esc_html__( 'VAT (%d%%):', 'vpdl-management' ), absint( $vat_rate ) ); ?></span>
                            <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispBsmVat">0.00</span></strong>
                        </div>
                        <div class="vpdl-bsm-pricing-grand">
                            <span><?php esc_html_e( 'Gross Amount:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispBsmTotal">0.00</span></strong>
                        </div>
                    </div>

                    <button type="submit" class="vpdl-bsm-btn-primary">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Confirm BSM Assignment', 'vpdl-management' ); ?></span>
                    </button>
                </form>
            </div>

            <!-- Right Side: Rate Chart & Services List -->
            <div class="vpdl-bsm-card vpdl-bsm-card-pad">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Official Rate Card (BSM)', 'vpdl-management' ); ?>
                    </h3>
                    <button type="button" data-action="switch-tab" data-tab="tariff" style="font-size: 11.5px; font-weight: 700; color: var(--vpdl-accent); background: transparent; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                        <span><?php esc_html_e( 'Edit Tariff', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </button>
                </div>

                <div class="vpdl-bsm-table-wrap">
                    <table class="vpdl-bsm-table">
                        <thead>
                            <tr>
                                <th style="width: 75px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 110px;"><?php esc_html_e( 'Fee (Ex. VAT)', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $bsm_services ) ) : ?>
                                <?php foreach ( $bsm_services as $s ) : ?>
                                    <tr class="vpdl-bsm-row">
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
                                        <?php esc_html_e( 'No BSM service codes configured.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 18px; padding: 14px; border-radius: 8px; background: rgba(2, 132, 199, 0.04); font-size: 12px; color: var(--vpdl-text-muted); line-height: 1.5;">
                    <strong><?php esc_html_e( 'Statutory Party Wall Scope:', 'vpdl-management' ); ?></strong> <?php esc_html_e( 'BSM services cover preparation and serving of statutory Party Wall notices under the Party Wall etc. Act 1996, schedules of condition inspections, and award dispute resolution per adjoining owner.', 'vpdl-management' ); ?>
                </div>
            </div>

        </div>
    </div>

    <!-- TAB 2: Case Tracking History -->
    <div id="vpdlBsmTabPaneHistory" class="vpdl-bsm-tab-pane <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-bsm-card vpdl-bsm-card-pad">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Building Survey & Party Wall Cases Ledger', 'vpdl-management' ); ?>
            </h3>

            <div class="vpdl-bsm-table-wrap">
                <table class="vpdl-bsm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Building Owner / Site', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Service / Scope', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Adjoining Neighbour', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Statutory Deadline', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Legal Status', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $bsm_history ) ) : ?>
                            <?php foreach ( $bsm_history as $case ) : 
                                preg_match( '/\[Adjoining Owner:\s*(.*?)\]/', $case->notes, $ao_matches );
                                $tbl_ao = $ao_matches[1] ?? __( 'Neighbour', 'vpdl-management' );

                                preg_match( '/\[Adjoining Property:\s*(.*?)\]/', $case->notes, $ap_matches );
                                $tbl_ap = $ap_matches[1] ?? __( 'Boundary', 'vpdl-management' );
                            ?>
                                <tr class="vpdl-bsm-row">
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
                                        <span class="vpdl-bsm-pill vpdl-pill-accent">
                                            <?php echo esc_html( $case->service_category ); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <div style="color: var(--vpdl-text-main); font-weight: 600;"><?php echo esc_html( $tbl_ao ); ?></div>
                                        <div style="font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( wp_trim_words( $tbl_ap, 4 ) ); ?></div>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted); font-size: 12px;">
                                        <?php echo esc_html( $case->next_followup_date ?: '—' ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-bsm-pill vpdl-pill-green">
                                            <?php echo esc_html( $case->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-bsm-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-bsm-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'building survey case', 'vpdl-management' ); ?>">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_bsm_case">
                                                <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $case->id ); ?>">
                                                <button type="submit" class="vpdl-bsm-action-btn vpdl-btn-delete">
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
                                    <?php esc_html_e( 'No active Building Survey or Party Wall cases registered.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Tariff Manager -->
    <div id="vpdlBsmTabPaneTariff" class="vpdl-bsm-tab-pane <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Add New Tariff Item Form -->
            <?php if ( $is_admin ) : ?>
                <div class="vpdl-bsm-card vpdl-bsm-card-pad">
                    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                        <span><?php esc_html_e( 'Add New Party Wall & Survey Tariff Item', 'vpdl-management' ); ?></span>
                    </h3>

                    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                        <input type="hidden" name="vpdl_action" value="add_bsm_tariff">

                        <div class="vpdl-tariff-grid-add">
                            <div>
                                <label for="vpdl_bsm_tariff_code" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Service Code', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_bsm_tariff_code" name="code" placeholder="<?php esc_attr_e( 'e.g. BSM 04', 'vpdl-management' ); ?>" required class="vpdl-bsm-input" style="font-family: monospace; font-weight: 800; text-transform: uppercase;">
                            </div>
                            <div>
                                <label for="vpdl_bsm_tariff_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Item / Scope Description', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_bsm_tariff_desc" name="description" placeholder="<?php esc_attr_e( 'e.g. Third Surveyor Referral & Dispute Resolution', 'vpdl-management' ); ?>" required class="vpdl-bsm-input">
                            </div>
                            <div>
                                <label for="vpdl_bsm_tariff_fee" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Base Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                                <input type="number" step="0.01" min="0" id="vpdl_bsm_tariff_fee" name="fee" placeholder="0.00" required class="vpdl-bsm-input" style="font-family: monospace; font-weight: 800;">
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

            <!-- Active BSM Tariff Repeater -->
            <div class="vpdl-bsm-card vpdl-bsm-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'survey', '', 16 ); ?>
                    <span><?php esc_html_e( 'Active Party Wall & Surveying (BSM) Tariff Catalog', 'vpdl-management' ); ?></span>
                </h3>

                <div class="vpdl-bsm-table-wrap">
                    <table class="vpdl-bsm-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description / Service Scope', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 160px;"><?php esc_html_e( 'Fee (Excl. VAT)', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $bsm_services ) ) : ?>
                                <?php foreach ( $bsm_services as $srv ) : ?>
                                    <tr class="vpdl-bsm-row">
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
                                                <button type="button" data-action="open-tariff-modal" data-id="<?php echo (int) $srv->id; ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-fee="<?php echo (float) $srv->fee; ?>" class="vpdl-bsm-action-btn vpdl-btn-edit">
                                                    <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                                </button>
                                                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'tariff item', 'vpdl-management' ); ?>">
                                                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                    <input type="hidden" name="vpdl_action" value="delete_bsm_tariff">
                                                    <input type="hidden" name="service_id" value="<?php echo (int) $srv->id; ?>">
                                                    <button type="submit" class="vpdl-bsm-action-btn vpdl-btn-delete">
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
                                        <?php esc_html_e( 'No BSM service codes registered in catalog.', 'vpdl-management' ); ?>
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

<!-- EDIT BSM TARIFF MODAL -->
<div id="vpdlEditBsmTariffModal" role="dialog" aria-modal="true" aria-labelledby="bsmTariffModalTitle">
    <div class="vpdl-modal-card-tariff">
        <div class="vpdl-modal-header-tariff">
            <h3 id="bsmTariffModalTitle" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit BSM Tariff Service', 'vpdl-management' ); ?> (<span id="modal_bsm_tariff_code" style="font-family: monospace; color: var(--vpdl-accent);"></span>)</span>
            </h3>
            <button type="button" data-action="close-tariff-modal" class="vpdl-modal-btn-close">
                <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_bsm_tariff">
            <input type="hidden" name="service_id" id="modal_bsm_tariff_id" value="">

            <div class="vpdl-bsm-form-group">
                <label for="modal_bsm_tariff_desc"><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?> *</label>
                <input type="text" name="description" id="modal_bsm_tariff_desc" required class="vpdl-bsm-input">
            </div>

            <div class="vpdl-bsm-form-group">
                <label for="modal_bsm_tariff_fee"><?php esc_html_e( 'Standard Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                <input type="number" step="0.01" min="0" name="fee" id="modal_bsm_tariff_fee" required class="vpdl-bsm-input" style="font-family: monospace; font-weight: 800;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top: 20px;">
                <button type="button" data-action="close-tariff-modal" class="vpdl-bsm-action-btn" style="background: var(--vpdl-card-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); padding: 8px 16px;">
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