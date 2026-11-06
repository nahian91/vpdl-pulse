<?php
/**
 * View: Project Management Consultancy (PMC) Service Catalog & Booking Engine
 * File: views/pmc.php
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
$base_url = admin_url( 'admin.php?page=vpdl&view=pmc' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'catalog' : 'history' );
$target_id   = isset( $_GET['inquiry_id'] ) ? absint( $_GET['inquiry_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete PMC Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_pmc_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete consultancy case.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_inq, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'history', 'msg' => 'case_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Edit PMC Case Handler
if ( isset( $_POST['vpdl_action'] ) && 'update_pmc_case' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $case_id        = isset( $_POST['inquiry_id'] ) ? absint( $_POST['inquiry_id'] ) : 0;
    $status         = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'In Progress';
    $followup       = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';
    $contractor     = isset( $_POST['contractor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contractor_name'] ) ) : '';
    $contract_type  = isset( $_POST['contract_type'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_type'] ) ) : '';
    $project_budget = isset( $_POST['project_budget'] ) ? sanitize_text_field( wp_unslash( $_POST['project_budget'] ) ) : '';
    $notes          = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

    if ( $case_id > 0 ) {
        $meta_tags = '';
        if ( ! empty( $contractor ) ) {
            $meta_tags .= '[Contractor/Builder: ' . $contractor . "] \n";
        }
        if ( ! empty( $contract_type ) ) {
            $meta_tags .= '[Contract Type: ' . $contract_type . "] \n";
        }
        if ( ! empty( $project_budget ) ) {
            $meta_tags .= '[Est Budget: ' . $currency . $project_budget . "] \n";
        }

        $cleaned_notes = preg_replace( '/\[Contractor\/Builder:.*?\]\s*/', '', $notes );
        $cleaned_notes = preg_replace( '/\[Contract Type:.*?\]\s*/', '', $cleaned_notes );
        $cleaned_notes = preg_replace( '/\[Est Budget:.*?\]\s*/', '', $cleaned_notes );
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

// 3. New PMC Service Booking Handler
if ( isset( $_POST['vpdl_action'] ) && 'book_pmc_service' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $customer_id    = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
    $service_code   = isset( $_POST['service_code'] ) ? sanitize_text_field( wp_unslash( $_POST['service_code'] ) ) : '';
    $custom_desc    = isset( $_POST['service_sub_desc'] ) ? sanitize_text_field( wp_unslash( $_POST['service_sub_desc'] ) ) : '';
    $contractor     = isset( $_POST['contractor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['contractor_name'] ) ) : '';
    $contract_type  = isset( $_POST['contract_type'] ) ? sanitize_text_field( wp_unslash( $_POST['contract_type'] ) ) : 'JCT Minor Works';
    $site_cadence   = isset( $_POST['site_cadence'] ) ? sanitize_text_field( wp_unslash( $_POST['site_cadence'] ) ) : 'Bi-Weekly Inspection';
    $project_budget = isset( $_POST['project_budget'] ) ? sanitize_text_field( wp_unslash( $_POST['project_budget'] ) ) : '';
    $raw_notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
    $followup       = isset( $_POST['next_followup_date'] ) ? sanitize_text_field( wp_unslash( $_POST['next_followup_date'] ) ) : '';

    if ( $customer_id > 0 && ! empty( $service_code ) ) {
        $final_service_title = 'Project Management (' . $service_code . ')';
        if ( ! empty( $custom_desc ) ) {
            $final_service_title .= ' - ' . $custom_desc;
        }

        $full_notes = '';
        if ( ! empty( $contractor ) ) {
            $full_notes .= '[Contractor/Builder: ' . $contractor . "] \n";
        }
        $full_notes .= '[Contract Type: ' . $contract_type . "] \n";
        $full_notes .= '[Site Cadence: ' . $site_cadence . "] \n";
        if ( ! empty( $project_budget ) ) {
            $full_notes .= '[Est Budget: ' . $currency . $project_budget . "] \n";
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
if ( isset( $_POST['vpdl_action'] ) && 'add_pmc_tariff' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $code = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
    $desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
    $fee  = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

    if ( ! empty( $code ) && ! empty( $desc ) ) {
        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_services} WHERE category = 'PMC' AND code = %s AND description = %s", $code, $desc ) );
        if ( ! $exists ) {
            $wpdb->insert(
                $t_services,
                array(
                    'category'    => 'PMC',
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
if ( isset( $_POST['vpdl_action'] ) && 'update_pmc_tariff' === $_POST['vpdl_action'] ) {
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
if ( isset( $_POST['vpdl_action'] ) && 'delete_pmc_tariff' === $_POST['vpdl_action'] ) {
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

// PMC Services & Customers Lookup
$pmc_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'PMC' ORDER BY code ASC, fee ASC" );
$customers    = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode FROM {$t_cust} ORDER BY name ASC" );

// Existing PMC Inquiries History
$pmc_history = $wpdb->get_results(
    "SELECT i.*, c.name AS customer_name, c.phone AS customer_phone, c.site_address, c.postcode, c.email AS customer_email 
     FROM {$t_inq} i 
     LEFT JOIN {$t_cust} c ON i.customer_id = c.id 
     WHERE i.service_category LIKE '%Project Management%' 
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
    'case_deleted'   => array( 'msg' => __( 'PMC case permanently removed from register.', 'vpdl-management' ), 'type' => 'success' ),
    'case_updated'   => array( 'msg' => __( 'PMC case updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'case_booked'    => array( 'msg' => __( 'Project Management Consultancy booking logged into CRM successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_added'   => array( 'msg' => __( 'New PMC Tariff item added to catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'PMC Tariff item updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_deleted' => array( 'msg' => __( 'PMC Tariff item removed from catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_exists'  => array( 'msg' => __( 'Error: This exact PMC service option already exists.', 'vpdl-management' ), 'error' ),
);
?>

<div class="vpdl-pmc-container" id="vpdlPmcModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-pmc-alert <?php echo 'error' === $alerts[ $msg_code ]['type'] ? 'vpdl-pmc-alert-error' : 'vpdl-pmc-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[ $msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-pmc-card vpdl-pmc-header-card">
        <div class="vpdl-pmc-header-left">
            <div class="vpdl-pmc-header-icon">
                <?php echo vpdl_get_svg( 'consulting', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-pmc-header-title"><?php esc_html_e( 'Project Management Consultancy (PMC)', 'vpdl-management' ); ?></h2>
                <div class="vpdl-pmc-header-subtitle">
                    <?php esc_html_e( 'Contract Administration, Site Supervision, Quality Compliance & Build Delivery', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-pmc-pill vpdl-pill-amber">
                <?php esc_html_e( 'All Fees Are Excluding VAT', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_case ) : 
    preg_match( '/\[Contractor\/Builder:\s*(.*?)\]/', $single_case->notes, $builder_matches );
    $extracted_builder = $builder_matches[1] ?? __( 'Client Appointed / Direct Labour', 'vpdl-management' );

    preg_match( '/\[Contract Type:\s*(.*?)\]/', $single_case->notes, $contract_matches );
    $extracted_contract = $contract_matches[1] ?? __( 'JCT / Standard', 'vpdl-management' );

    preg_match( '/\[Est Budget:\s*(.*?)\]/', $single_case->notes, $budget_matches );
    $extracted_budget = $budget_matches[1] ?? __( 'Under Review', 'vpdl-management' );
?>
    <!-- VIEW MODE: DETAILED PMC CASE DOSSIER -->
    <div class="vpdl-pmc-card vpdl-pmc-card-pad">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                    <?php echo vpdl_get_svg( 'consulting', '', 20 ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_case->customer_name ); ?>
                        <span style="font-family: monospace; color: var(--vpdl-accent);">#PMC-CASE-<?php echo esc_html( (string) $single_case->id ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <?php echo esc_html( $single_case->service_category ); ?> • <?php esc_html_e( 'Framework:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $extracted_contract ); ?></strong>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=schemes&customer_id=' . $single_case->customer_id . '&service_category=PMC' ) ); ?>" class="vpdl-pmc-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create Proposal', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" class="vpdl-pmc-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to History', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div class="vpdl-pmc-metrics-grid">
            <div class="vpdl-pmc-card vpdl-pmc-metric-box">
                <div class="vpdl-pmc-metric-lbl"><?php esc_html_e( 'Client Contact', 'vpdl-management' ); ?></div>
                <div class="vpdl-pmc-metric-val" style="font-family: monospace;"><?php echo esc_html( $single_case->customer_phone ); ?></div>
                <div class="vpdl-pmc-metric-sub"><?php echo esc_html( $single_case->customer_email ?: __( 'No email on file', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-pmc-card vpdl-pmc-metric-box">
                <div class="vpdl-pmc-metric-lbl"><?php esc_html_e( 'Contract / Appointed Builder', 'vpdl-management' ); ?></div>
                <div class="vpdl-pmc-metric-val" style="color: var(--vpdl-accent);">
                    <?php echo esc_html( $extracted_builder ); ?>
                </div>
                <div class="vpdl-pmc-metric-sub">
                    <?php esc_html_e( 'Est. Build Value:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $extracted_budget ); ?></strong>
                </div>
            </div>

            <div class="vpdl-pmc-card vpdl-pmc-metric-box">
                <div class="vpdl-pmc-metric-lbl"><?php esc_html_e( 'Milestone Target Date', 'vpdl-management' ); ?></div>
                <div class="vpdl-pmc-metric-val" style="color: #10b981; font-family: monospace;">
                    <?php echo esc_html( $single_case->next_followup_date ?: __( 'None Set', 'vpdl-management' ) ); ?>
                </div>
                <div class="vpdl-pmc-metric-sub">
                    <?php esc_html_e( 'Status:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_case->status ); ?></strong>
                </div>
            </div>

            <div class="vpdl-pmc-card vpdl-pmc-metric-box">
                <div class="vpdl-pmc-metric-lbl"><?php esc_html_e( 'Site Property Location', 'vpdl-management' ); ?></div>
                <div class="vpdl-pmc-metric-val" style="font-size: 13px; line-height: 1.4;">
                    <?php echo esc_html( $single_case->site_address ?: __( 'Standard Consultancy Site', 'vpdl-management' ) ); ?>
                    <?php if ( ! empty( $single_case->postcode ) ) : ?>
                        <div style="font-family: monospace; color: var(--vpdl-accent); margin-top: 2px;"><?php echo esc_html( $single_case->postcode ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="vpdl-pmc-card" style="padding: 20px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Supervision Tasks & Contractor Specifications', 'vpdl-management' ); ?></h4>
            <div class="vpdl-pmc-notes-preview"><?php echo esc_html( $single_case->notes ?: __( 'No notes recorded.', 'vpdl-management' ) ); ?></div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_case ) : 
    preg_match( '/\[Contractor\/Builder:\s*(.*?)\]/', $single_case->notes, $builder_matches );
    $edit_builder = $builder_matches[1] ?? '';

    preg_match( '/\[Contract Type:\s*(.*?)\]/', $single_case->notes, $contract_matches );
    $edit_contract = $contract_matches[1] ?? 'JCT Minor Works';

    preg_match( '/\[Est Budget:\s*£?(.*?)\]/', $single_case->notes, $budget_matches );
    $edit_budget = $budget_matches[1] ?? '';
?>
    <!-- EDIT MODE: PMC CASE UPDATE FORM -->
    <div class="vpdl-pmc-card vpdl-pmc-card-max">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'edit', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit Project Management Case', 'vpdl-management' ); ?> (#PMC-CASE-<?php echo esc_html( (string) $single_case->id ); ?>)</span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_pmc_case">
            <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $single_case->id ); ?>">

            <div class="vpdl-pmc-grid-2 vpdl-pmc-form-group">
                <div>
                    <label for="vpdl_pmc_edit_status"><?php esc_html_e( 'Project Milestone Stage', 'vpdl-management' ); ?></label>
                    <select name="status" id="vpdl_pmc_edit_status" class="vpdl-pmc-select">
                        <option value="In Progress" <?php selected( $single_case->status, 'In Progress' ); ?>><?php esc_html_e( 'In Progress (Site Active)', 'vpdl-management' ); ?></option>
                        <option value="Pre-Tender Contractor Selection" <?php selected( $single_case->status, 'Pre-Tender Contractor Selection' ); ?>><?php esc_html_e( 'Pre-Tender & Contractor Vetting', 'vpdl-management' ); ?></option>
                        <option value="Foundation Sign-Off" <?php selected( $single_case->status, 'Foundation Sign-Off' ); ?>><?php esc_html_e( 'Foundation & Substructure Sign-off', 'vpdl-management' ); ?></option>
                        <option value="Superstructure & Steelwork" <?php selected( $single_case->status, 'Superstructure & Steelwork' ); ?>><?php esc_html_e( 'Superstructure & Steel Erection', 'vpdl-management' ); ?></option>
                        <option value="First Fix Electrics/Plumbing" <?php selected( $single_case->status, 'First Fix Electrics/Plumbing' ); ?>><?php esc_html_e( '1st Fix MEP Inspections', 'vpdl-management' ); ?></option>
                        <option value="Practical Completion" <?php selected( $single_case->status, 'Practical Completion' ); ?>><?php esc_html_e( 'Practical Completion & Snagging', 'vpdl-management' ); ?></option>
                        <option value="Won" <?php selected( $single_case->status, 'Won' ); ?>><?php esc_html_e( 'Won / Fully Handed Over', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_pmc_edit_contractor"><?php esc_html_e( 'Appointed Contractor / Builder', 'vpdl-management' ); ?></label>
                    <input type="text" name="contractor_name" id="vpdl_pmc_edit_contractor" value="<?php echo esc_attr( $edit_builder ); ?>" placeholder="<?php esc_attr_e( 'e.g. Apex Construction Ltd', 'vpdl-management' ); ?>" class="vpdl-pmc-input">
                </div>
            </div>

            <div class="vpdl-pmc-grid-3 vpdl-pmc-form-group">
                <div>
                    <label for="vpdl_pmc_edit_contract_type"><?php esc_html_e( 'Contract Administration', 'vpdl-management' ); ?></label>
                    <select name="contract_type" id="vpdl_pmc_edit_contract_type" class="vpdl-pmc-select">
                        <option value="JCT Minor Works Building Contract" <?php selected( $edit_contract, 'JCT Minor Works Building Contract' ); ?>><?php esc_html_e( 'JCT Minor Works', 'vpdl-management' ); ?></option>
                        <option value="JCT Intermediate Building Contract" <?php selected( $edit_contract, 'JCT Intermediate Building Contract' ); ?>><?php esc_html_e( 'JCT Intermediate', 'vpdl-management' ); ?></option>
                        <option value="JCT Design and Build" <?php selected( $edit_contract, 'JCT Design and Build' ); ?>><?php esc_html_e( 'JCT Design & Build', 'vpdl-management' ); ?></option>
                        <option value="NEC4 Engineering & Construction" <?php selected( $edit_contract, 'NEC4 Engineering & Construction' ); ?>><?php esc_html_e( 'NEC4 Short Contract', 'vpdl-management' ); ?></option>
                        <option value="Client Direct Management" <?php selected( $edit_contract, 'Client Direct Management' ); ?>><?php esc_html_e( 'Direct Trades', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="vpdl_pmc_edit_budget"><?php esc_html_e( 'Est. Construction Budget', 'vpdl-management' ); ?></label>
                    <input type="number" step="1000" min="0" name="project_budget" id="vpdl_pmc_edit_budget" value="<?php echo esc_attr( $edit_budget ); ?>" placeholder="e.g. 85000" class="vpdl-pmc-input" style="font-family: monospace;">
                </div>
                <div>
                    <label for="vpdl_pmc_edit_followup"><?php esc_html_e( 'Next Milestone Audit', 'vpdl-management' ); ?></label>
                    <input type="date" name="next_followup_date" id="vpdl_pmc_edit_followup" value="<?php echo esc_attr( $single_case->next_followup_date ); ?>" class="vpdl-pmc-input">
                </div>
            </div>

            <div class="vpdl-pmc-form-group">
                <label for="vpdl_pmc_edit_notes"><?php esc_html_e( 'Supervision Tasks & Inspection Scope', 'vpdl-management' ); ?></label>
                <textarea name="notes" id="vpdl_pmc_edit_notes" rows="4" class="vpdl-pmc-textarea"><?php echo esc_textarea( $single_case->notes ); ?></textarea>
            </div>

            <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Update PMC Case', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION -->
    <div class="vpdl-pmc-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="catalog" id="vpdlPmcTabBtnCatalog" class="vpdl-pmc-tab-btn <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'catalog' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'consulting', '', 18 ); ?>
            <span><?php esc_html_e( 'Service Assignment', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="history" id="vpdlPmcTabBtnHistory" class="vpdl-pmc-tab-btn <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'history' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'reports', '', 18 ); ?>
            <span><?php esc_html_e( 'PMC Project Tracking & Milestones', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $pmc_history ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="tariff" id="vpdlPmcTabBtnTariff" class="vpdl-pmc-tab-btn <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'tariff' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 18 ); ?>
            <span><?php esc_html_e( 'PMC Tariff Manager', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Service Booking & Assignment Form -->
    <div id="vpdlPmcTabPaneCatalog" class="vpdl-pmc-tab-pane <?php echo ( 'catalog' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-pmc-grid-catalog">
            
            <!-- Left Side: Form -->
            <div class="vpdl-pmc-card vpdl-pmc-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'customers', '', 18 ); ?>
                    <span><?php esc_html_e( 'Assign Consultancy Service', 'vpdl-management' ); ?></span>
                </h3>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                    <input type="hidden" name="vpdl_action" value="book_pmc_service">
                    <input type="hidden" name="service_sub_desc" id="vpdlPmcSubDesc" value="">

                    <div class="vpdl-pmc-grid-2 vpdl-pmc-form-group">
                        <div>
                            <label for="vpdl_pmc_customer_select"><?php esc_html_e( 'Select Client', 'vpdl-management' ); ?> *</label>
                            <select name="customer_id" id="vpdl_pmc_customer_select" required class="vpdl-pmc-select">
                                <option value=""><?php esc_html_e( '-- Choose Existing Customer --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $customers as $c ) : ?>
                                    <option value="<?php echo esc_attr( (string) $c->id ); ?>">
                                        <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="vpdlPmcCodeSelector"><?php esc_html_e( 'Project Scope / Package', 'vpdl-management' ); ?> *</label>
                            <select name="service_code" id="vpdlPmcCodeSelector" required class="vpdl-pmc-select">
                                <option value=""><?php esc_html_e( '-- Select PMC Service --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $pmc_services as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s->code ); ?>" data-fee="<?php echo esc_attr( (string) $s->fee ); ?>" data-desc="<?php echo esc_attr( $s->description ); ?>">
                                        <?php echo esc_html( $s->code . ' - ' . $s->description . ' (' . $currency . number_format( (float) $s->fee, 2 ) . ')' ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Contract Framework & Appointed Builder -->
                    <div class="vpdl-pmc-grid-2 vpdl-pmc-form-group">
                        <div>
                            <label for="vpdl_pmc_contract_type"><?php esc_html_e( 'Contract Administration Framework', 'vpdl-management' ); ?></label>
                            <select name="contract_type" id="vpdl_pmc_contract_type" class="vpdl-pmc-select">
                                <option value="JCT Minor Works Building Contract"><?php esc_html_e( 'JCT Minor Works Building Contract', 'vpdl-management' ); ?></option>
                                <option value="JCT Intermediate Building Contract"><?php esc_html_e( 'JCT Intermediate Contract', 'vpdl-management' ); ?></option>
                                <option value="JCT Design and Build"><?php esc_html_e( 'JCT Design & Build Framework', 'vpdl-management' ); ?></option>
                                <option value="NEC4 Engineering & Construction"><?php esc_html_e( 'NEC4 Short Contract', 'vpdl-management' ); ?></option>
                                <option value="Client Direct Management"><?php esc_html_e( 'Client Direct Trade Contractor', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_pmc_contractor_name"><?php esc_html_e( 'Appointed Contractor / Builder Name', 'vpdl-management' ); ?></label>
                            <input type="text" name="contractor_name" id="vpdl_pmc_contractor_name" placeholder="<?php esc_attr_e( 'e.g. Apex Construction Ltd', 'vpdl-management' ); ?>" class="vpdl-pmc-input">
                        </div>
                    </div>

                    <!-- Site Cadence & Construction Budget -->
                    <div class="vpdl-pmc-grid-2 vpdl-pmc-form-group">
                        <div>
                            <label for="vpdl_pmc_site_cadence"><?php esc_html_e( 'Site Supervision Frequency', 'vpdl-management' ); ?></label>
                            <select name="site_cadence" id="vpdl_pmc_site_cadence" class="vpdl-pmc-select">
                                <option value="Weekly Inspection & Progress Log"><?php esc_html_e( 'Weekly Inspection & Audit', 'vpdl-management' ); ?></option>
                                <option value="Bi-Weekly Milestone Review" selected><?php esc_html_e( 'Bi-Weekly Milestone Check', 'vpdl-management' ); ?></option>
                                <option value="Monthly Valuations Only"><?php esc_html_e( 'Monthly Valuation & Stage Review', 'vpdl-management' ); ?></option>
                                <option value="Critical Sign-off Stages Only"><?php esc_html_e( 'Critical Sign-off Stages (Concrete/Steel/Roof)', 'vpdl-management' ); ?></option>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_pmc_project_budget"><?php esc_html_e( 'Estimated Construction Budget', 'vpdl-management' ); ?></label>
                            <input type="number" step="1000" min="0" name="project_budget" id="vpdl_pmc_project_budget" placeholder="e.g. 85000" class="vpdl-pmc-input" style="font-family: monospace;">
                        </div>
                    </div>

                    <div class="vpdl-pmc-form-group">
                        <label for="vpdl_pmc_next_followup_date"><?php esc_html_e( 'First Inspection / Milestone Date', 'vpdl-management' ); ?></label>
                        <input type="date" name="next_followup_date" id="vpdl_pmc_next_followup_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="vpdl-pmc-input">
                    </div>

                    <div class="vpdl-pmc-form-group">
                        <label for="vpdl_pmc_notes"><?php esc_html_e( 'Consultancy Tasks & Scope Notes', 'vpdl-management' ); ?></label>
                        <textarea name="notes" id="vpdl_pmc_notes" rows="3" placeholder="<?php esc_attr_e( 'e.g. Pre-construction contractor vetting, foundation sign-off audit, interim valuations, snagging inspection...', 'vpdl-management' ); ?>" class="vpdl-pmc-textarea"></textarea>
                    </div>

                    <!-- Live Pricing Summary -->
                    <div class="vpdl-pmc-pricing-summary">
                        <div class="vpdl-pmc-pricing-row">
                            <span><?php esc_html_e( 'Net Fee:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispPmcNet">0.00</span></strong>
                        </div>
                        <div class="vpdl-pmc-pricing-row">
                            <span><?php printf( esc_html__( 'VAT (%d%%):', 'vpdl-management' ), absint( $vat_rate ) ); ?></span>
                            <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispPmcVat">0.00</span></strong>
                        </div>
                        <div class="vpdl-pmc-pricing-grand">
                            <span><?php esc_html_e( 'Gross Amount:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispPmcTotal">0.00</span></strong>
                        </div>
                    </div>

                    <button type="submit" class="vpdl-pmc-btn-primary">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Confirm PMC Assignment', 'vpdl-management' ); ?></span>
                    </button>
                </form>
            </div>

            <!-- Right Side: Rate Chart & Services List -->
            <div class="vpdl-pmc-card vpdl-pmc-card-pad">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Official Rate Card (PMC)', 'vpdl-management' ); ?>
                    </h3>
                    <button type="button" data-action="switch-tab" data-tab="tariff" style="font-size: 11.5px; font-weight: 700; color: var(--vpdl-accent); background: transparent; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                        <span><?php esc_html_e( 'Edit Tariff', 'vpdl-management' ); ?></span>
                        <?php echo vpdl_get_svg( 'arrow_right', '', 12 ); ?>
                    </button>
                </div>

                <div class="vpdl-pmc-table-wrap">
                    <table class="vpdl-pmc-table">
                        <thead>
                            <tr>
                                <th style="width: 75px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 110px;"><?php esc_html_e( 'Fee (Ex. VAT)', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $pmc_services ) ) : ?>
                                <?php foreach ( $pmc_services as $s ) : ?>
                                    <tr class="vpdl-pmc-row">
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
                                        <?php esc_html_e( 'No PMC service codes configured.', 'vpdl-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 18px; padding: 14px; border-radius: 8px; background: rgba(2, 132, 199, 0.04); font-size: 12px; color: var(--vpdl-text-muted); line-height: 1.5;">
                    <strong><?php esc_html_e( 'Consultancy Details:', 'vpdl-management' ); ?></strong> <?php esc_html_e( 'Covers contract administration, site compliance reviews, progress reporting, contractor valuation certificates, and snagging inspections across residential and commercial conversions.', 'vpdl-management' ); ?>
                </div>
            </div>

        </div>
    </div>

    <!-- TAB 2: Project Tracking History -->
    <div id="vpdlPmcTabPaneHistory" class="vpdl-pmc-tab-pane <?php echo ( 'history' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-pmc-card vpdl-pmc-card-pad">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Project Management Consultancy Cases Ledger', 'vpdl-management' ); ?>
            </h3>

            <div class="vpdl-pmc-table-wrap">
                <table class="vpdl-pmc-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Client / Location', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Consultancy Scope', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Builder & Contract', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Milestone Date', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $pmc_history ) ) : ?>
                            <?php foreach ( $pmc_history as $case ) : 
                                preg_match( '/\[Contractor\/Builder:\s*(.*?)\]/', $case->notes, $b_matches );
                                $tbl_builder = $b_matches[1] ?? __( 'Direct Contractor', 'vpdl-management' );

                                preg_match( '/\[Contract Type:\s*(.*?)\]/', $case->notes, $c_matches );
                                $tbl_contract = $c_matches[1] ?? __( 'Standard', 'vpdl-management' );
                            ?>
                                <tr class="vpdl-pmc-row">
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
                                        <span class="vpdl-pmc-pill vpdl-pill-accent">
                                            <?php echo esc_html( $case->service_category ); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <div style="color: var(--vpdl-text-main); font-weight: 600;"><?php echo esc_html( $tbl_builder ); ?></div>
                                        <div style="font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $tbl_contract ); ?></div>
                                    </td>
                                    <td style="font-family: monospace; color: var(--vpdl-text-muted); font-size: 12px;">
                                        <?php echo esc_html( $case->next_followup_date ?: '—' ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-pmc-pill vpdl-pill-green">
                                            <?php echo esc_html( $case->status ); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-pmc-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'inquiry_id' => $case->id ), $base_url ) ); ?>" class="vpdl-pmc-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'consultancy case', 'vpdl-management' ); ?>">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_pmc_case">
                                                <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $case->id ); ?>">
                                                <button type="submit" class="vpdl-pmc-action-btn vpdl-btn-delete">
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
                                    <?php esc_html_e( 'No active Project Management Consultancy cases registered.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Tariff Manager -->
    <div id="vpdlPmcTabPaneTariff" class="vpdl-pmc-tab-pane <?php echo ( 'tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Add New Tariff Item Form -->
            <?php if ( $is_admin ) : ?>
                <div class="vpdl-pmc-card vpdl-pmc-card-pad">
                    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                        <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                        <span><?php esc_html_e( 'Add New Project Management Tariff Item', 'vpdl-management' ); ?></span>
                    </h3>

                    <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                        <input type="hidden" name="vpdl_action" value="add_pmc_tariff">

                        <div class="vpdl-tariff-grid-add">
                            <div>
                                <label for="vpdl_pmc_tariff_code" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Service Code', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_pmc_tariff_code" name="code" placeholder="<?php esc_attr_e( 'e.g. PMC 05', 'vpdl-management' ); ?>" required class="vpdl-pmc-input" style="font-family: monospace; font-weight: 800; text-transform: uppercase;">
                            </div>
                            <div>
                                <label for="vpdl_pmc_tariff_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Item / Scope Description', 'vpdl-management' ); ?> *</label>
                                <input type="text" id="vpdl_pmc_tariff_desc" name="description" placeholder="<?php esc_attr_e( 'e.g. Commercial Build-out Contract Administration', 'vpdl-management' ); ?>" required class="vpdl-pmc-input">
                            </div>
                            <div>
                                <label for="vpdl_pmc_tariff_fee" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Base Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                                <input type="number" step="0.01" min="0" id="vpdl_pmc_tariff_fee" name="fee" placeholder="0.00" required class="vpdl-pmc-input" style="font-family: monospace; font-weight: 800;">
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

            <!-- Active PMC Tariff Repeater -->
            <div class="vpdl-pmc-card vpdl-pmc-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'consulting', '', 16 ); ?>
                    <span><?php esc_html_e( 'Active Project Management (PMC) Tariff Catalog', 'vpdl-management' ); ?></span>
                </h3>

                <div class="vpdl-pmc-table-wrap">
                    <table class="vpdl-pmc-table">
                        <thead>
                            <tr>
                                <th style="width: 100px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Description / Service Scope', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 160px;"><?php esc_html_e( 'Fee (Excl. VAT)', 'vpdl-management' ); ?></th>
                                <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $pmc_services ) ) : ?>
                                <?php foreach ( $pmc_services as $srv ) : ?>
                                    <tr class="vpdl-pmc-row">
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
                                                <button type="button" data-action="open-tariff-modal" data-id="<?php echo (int) $srv->id; ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-fee="<?php echo (float) $srv->fee; ?>" class="vpdl-pmc-action-btn vpdl-btn-edit">
                                                    <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                                </button>
                                                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'tariff item', 'vpdl-management' ); ?>">
                                                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                    <input type="hidden" name="vpdl_action" value="delete_pmc_tariff">
                                                    <input type="hidden" name="service_id" value="<?php echo (int) $srv->id; ?>">
                                                    <button type="submit" class="vpdl-pmc-action-btn vpdl-btn-delete">
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
                                        <?php esc_html_e( 'No PMC service codes registered in catalog.', 'vpdl-management' ); ?>
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

<!-- EDIT PMC TARIFF MODAL -->
<div id="vpdlEditPmcTariffModal" role="dialog" aria-modal="true" aria-labelledby="pmcTariffModalTitle">
    <div class="vpdl-modal-card-tariff">
        <div class="vpdl-modal-header-tariff">
            <h3 id="pmcTariffModalTitle" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit PMC Tariff Service', 'vpdl-management' ); ?> (<span id="modal_pmc_tariff_code" style="font-family: monospace; color: var(--vpdl-accent);"></span>)</span>
            </h3>
            <button type="button" data-action="close-tariff-modal" class="vpdl-modal-btn-close">
                <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_pmc_tariff">
            <input type="hidden" name="service_id" id="modal_pmc_tariff_id" value="">

            <div class="vpdl-pmc-form-group">
                <label for="modal_pmc_tariff_desc"><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?> *</label>
                <input type="text" name="description" id="modal_pmc_tariff_desc" required class="vpdl-pmc-input">
            </div>

            <div class="vpdl-pmc-form-group">
                <label for="modal_pmc_tariff_fee"><?php esc_html_e( 'Standard Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                <input type="number" step="0.01" min="0" name="fee" id="modal_pmc_tariff_fee" required class="vpdl-pmc-input" style="font-family: monospace; font-weight: 800;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; margin-top: 20px;">
                <button type="button" data-action="close-tariff-modal" class="vpdl-pmc-action-btn" style="background: var(--vpdl-card-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); padding: 8px 16px;">
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