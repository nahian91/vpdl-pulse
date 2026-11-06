<?php
/**
 * View: VPDL Team, Role Switcher & Access Governance
 * File: views/team.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Access restricted to administrators only
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Access Denied: Administrator privileges required to manage team members.', 'vpdl-management' ), 403 );
}

global $wpdb;

$t_cust   = $wpdb->prefix . 'vpdl_customers';
$t_quotes = $wpdb->prefix . 'vpdl_quotations';
$t_inv    = $wpdb->prefix . 'vpdl_invoices';

$currency    = (string) get_option( 'vpdl_currency', '£' );
$base_url    = admin_url( 'admin.php?page=vpdl&view=team' );
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( 'add' === $action_mode ? 'create' : 'directory' );
$target_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// Role Definition Matrix
$defined_roles = array(
    'administrator' => array(
        'name'  => __( 'Managing Director', 'vpdl-management' ),
        'badge' => 'rgba(2, 132, 199, 0.12)',
        'color' => '#0284c7',
        'desc'  => __( 'Unrestricted access across terminal, audit reports, banking & system settings.', 'vpdl-management' ),
        'caps'  => array( 'Terminal Intake', 'Quotes & Billing', 'Ledger & Accounts', 'System Settings', 'Staff Provisioning' ),
    ),
    'vpdl_manager'  => array(
        'name'  => __( 'Operations Manager', 'vpdl-management' ),
        'badge' => 'rgba(16, 185, 129, 0.12)',
        'color' => '#10b981',
        'desc'  => __( 'Access to project pipeline, invoicing, reports & client records.', 'vpdl-management' ),
        'caps'  => array( 'Terminal Intake', 'Quotes & Billing', 'Client Dossier', 'Financial Reports' ),
    ),
    'vpdl_salesman' => array(
        'name'  => __( 'Specialist / Estimator', 'vpdl-management' ),
        'badge' => 'rgba(245, 158, 11, 0.12)',
        'color' => '#f59e0b',
        'desc'  => __( 'Terminal operations, survey/planning booking and quotation generation only.', 'vpdl-management' ),
        'caps'  => array( 'Terminal Intake', 'Create Quotes', 'Customer Dossier' ),
    ),
);

// Helper function to re-synchronize capabilities per role
function vpdl_sync_user_caps( $u_id, $role_slug ) {
    $u = new WP_User( $u_id );
    if ( ! $u->exists() ) {
        return;
    }

    $u->set_role( $role_slug );

    $u->add_cap( 'vpdl_access_terminal' );
    $u->add_cap( 'vpdl_manage_customers' );
    $u->add_cap( 'vpdl_create_quotes' );

    if ( in_array( $role_slug, array( 'administrator', 'vpdl_manager' ), true ) ) {
        $u->add_cap( 'vpdl_manage_invoices' );
        $u->add_cap( 'vpdl_view_finances' );
    } else {
        $u->remove_cap( 'vpdl_manage_invoices' );
        $u->remove_cap( 'vpdl_view_finances' );
    }

    if ( 'administrator' === $role_slug ) {
        $u->add_cap( 'vpdl_manage_team' );
        $u->add_cap( 'vpdl_manage_settings' );
    } else {
        $u->remove_cap( 'vpdl_manage_team' );
        $u->remove_cap( 'vpdl_manage_settings' );
    }
}

// ----------------------------------------------------
// 1. ACTION HANDLER: Inline Fast Role Switch
// ----------------------------------------------------
if ( isset( $_POST['vpdl_action'] ) && 'switch_team_role' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $target_uid = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
    $new_role   = isset( $_POST['new_role'] ) ? sanitize_key( wp_unslash( $_POST['new_role'] ) ) : 'vpdl_salesman';

    if ( $target_uid > 0 && array_key_exists( $new_role, $defined_roles ) ) {
        if ( $target_uid === get_current_user_id() && 'administrator' !== $new_role ) {
            wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => 'err_self_demote' ), $base_url ) );
            exit;
        } else {
            vpdl_sync_user_caps( $target_uid, $new_role );
            wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => 'role_switched' ), $base_url ) );
            exit;
        }
    }
}

// ----------------------------------------------------
// 2. ACTION HANDLER: Account Status Toggle (Active/Suspended)
// ----------------------------------------------------
if ( isset( $_POST['vpdl_action'] ) && 'toggle_account_status' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $target_uid = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
    if ( $target_uid > 0 && $target_uid !== get_current_user_id() ) {
        $current_status = get_user_meta( $target_uid, 'vpdl_account_status', true ) ?: 'active';
        $new_status     = ( 'active' === $current_status ) ? 'suspended' : 'active';
        update_user_meta( $target_uid, 'vpdl_account_status', $new_status );

        $code_msg = ( 'suspended' === $new_status ) ? 'account_suspended' : 'account_reinstated';
        wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => $code_msg ), $base_url ) );
        exit;
    }
}

// ----------------------------------------------------
// 3. ACTION HANDLER: Create Team Member
// ----------------------------------------------------
if ( isset( $_POST['vpdl_action'] ) && 'create_team_member' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $username      = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
    $email         = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
    $password      = isset( $_POST['user_pass'] ) ? trim( (string) wp_unslash( $_POST['user_pass'] ) ) : '';
    $fullname      = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
    $job_title     = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : 'Specialist';
    $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $accreditation = isset( $_POST['accreditation'] ) ? sanitize_text_field( wp_unslash( $_POST['accreditation'] ) ) : '';
    $role          = isset( $_POST['user_role'] ) ? sanitize_key( wp_unslash( $_POST['user_role'] ) ) : 'vpdl_salesman';

    if ( ! array_key_exists( $role, $defined_roles ) ) {
        $role = 'vpdl_salesman';
    }

    if ( ! empty( $username ) && is_email( $email ) && ! empty( $password ) ) {
        $user_id = wp_create_user( $username, $password, $email );
        if ( ! is_wp_error( $user_id ) ) {
            wp_update_user(
                array(
                    'ID'           => $user_id,
                    'display_name' => $fullname ?: $username,
                )
            );

            vpdl_sync_user_caps( $user_id, $role );
            update_user_meta( $user_id, 'vpdl_job_title', $job_title );
            update_user_meta( $user_id, 'vpdl_phone', $phone );
            update_user_meta( $user_id, 'vpdl_accreditation', $accreditation );
            update_user_meta( $user_id, 'vpdl_account_status', 'active' );

            wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => 'member_created' ), $base_url ) );
            exit;
        } else {
            wp_safe_redirect( add_query_arg( array( 'tab' => 'create', 'msg' => 'err_wp_error' ), $base_url ) );
            exit;
        }
    }
}

// ----------------------------------------------------
// 4. ACTION HANDLER: Update Member Profile
// ----------------------------------------------------
if ( isset( $_POST['vpdl_action'] ) && 'update_team_member' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $user_id       = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $fullname      = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
    $email         = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
    $job_title     = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
    $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $accreditation = isset( $_POST['accreditation'] ) ? sanitize_text_field( wp_unslash( $_POST['accreditation'] ) ) : '';
    $role          = isset( $_POST['user_role'] ) ? sanitize_key( wp_unslash( $_POST['user_role'] ) ) : 'vpdl_salesman';

    if ( ! array_key_exists( $role, $defined_roles ) ) {
        $role = 'vpdl_salesman';
    }

    if ( $user_id > 0 ) {
        wp_update_user(
            array(
                'ID'           => $user_id,
                'display_name' => $fullname,
                'user_email'   => $email,
            )
        );

        if ( $user_id !== get_current_user_id() || 'administrator' === $role ) {
            vpdl_sync_user_caps( $user_id, $role );
        }

        update_user_meta( $user_id, 'vpdl_job_title', $job_title );
        update_user_meta( $user_id, 'vpdl_phone', $phone );
        update_user_meta( $user_id, 'vpdl_accreditation', $accreditation );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => 'member_updated' ), $base_url ) );
        exit;
    }
}

// ----------------------------------------------------
// 5. ACTION HANDLER: Delete Member
// ----------------------------------------------------
if ( isset( $_POST['vpdl_action'] ) && 'delete_team_member' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( $user_id > 0 && $user_id !== get_current_user_id() ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'directory', 'msg' => 'member_deleted' ), $base_url ) );
        exit;
    }
}

// Query Roster
$team_users = get_users(
    array(
        'role__in' => array_keys( $defined_roles ),
        'orderby'  => 'registered',
        'order'    => 'DESC',
    )
);

$total_members = is_array( $team_users ) ? count( $team_users ) : 0;
$total_sales   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(subtotal), 0) FROM {$t_inv} WHERE payment_status = 'Paid'" );

$single_user = null;
if ( ( 'view' === $action_mode || 'edit' === $action_mode ) && $target_id > 0 ) {
    $single_user = get_userdata( $target_id );
}

// Flash Notifications Mapping
$alerts = array(
    'role_switched'      => array( 'msg' => __( 'Role switched successfully. Security permissions updated.', 'vpdl-management' ), 'type' => 'success' ),
    'account_suspended'  => array( 'msg' => __( 'Staff access suspended. Terminal login revoked.', 'vpdl-management' ), 'type' => 'success' ),
    'account_reinstated' => array( 'msg' => __( 'Staff account reinstated to active status.', 'vpdl-management' ), 'type' => 'success' ),
    'member_created'     => array( 'msg' => __( 'New personnel provisioned with assigned role switch privileges.', 'vpdl-management' ), 'type' => 'success' ),
    'member_updated'     => array( 'msg' => __( 'Staff dossier updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'member_deleted'     => array( 'msg' => __( 'Personnel account permanently deleted.', 'vpdl-management' ), 'type' => 'success' ),
    'err_self_demote'    => array( 'msg' => __( 'Security Guard: You cannot demote your own administrator account.', 'vpdl-management' ), 'type' => 'error' ),
    'err_wp_error'       => array( 'msg' => __( 'Error provisioning account. Username or email may already exist.', 'vpdl-management' ), 'type' => 'error' ),
);
?>

<div class="vpdl-team-box" id="vpdlTeamModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-alert <?php echo 'error' === $alerts[ $msg_code ]['type'] ? 'vpdl-alert-error' : 'vpdl-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[ $msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Top Command Toolbar -->
    <div class="vpdl-card" style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                <?php echo vpdl_get_svg( 'team', '', 22 ); ?>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Team Management & Security Hub', 'vpdl-management' ); ?></h2>
                <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                    <?php esc_html_e( 'Live Role Switcher, RBAC Permissions, Personnel Dossier & Account States', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <span style="font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); border: 1px solid rgba(2, 132, 199, 0.2);">
                <?php echo esc_html( number_format( $total_members ) ); ?> <?php esc_html_e( 'Registered Personnel', 'vpdl-management' ); ?>
            </span>
            <span style="font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">
                <?php esc_html_e( 'Practice Turnover:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . ' ' . number_format( $total_sales, 2 ) ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_user ) : 
    $u_clients = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$t_cust} WHERE assigned_to = %d", $single_user->ID ) );
    $u_quotes  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$t_quotes} WHERE created_by = %s", $single_user->display_name ) );
    $u_sales   = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(subtotal), 0) FROM {$t_inv} WHERE created_by = %s AND payment_status = 'Paid'", $single_user->display_name ) );
    $u_roles   = (array) $single_user->roles;
    $curr_r    = reset( $u_roles ) ?: 'vpdl_salesman';
    $role_info = $defined_roles[ $curr_r ] ?? $defined_roles['vpdl_salesman'];
    $u_status  = get_user_meta( $single_user->ID, 'vpdl_account_status', true ) ?: 'active';

    $u_job     = get_user_meta( $single_user->ID, 'vpdl_job_title', true ) ?: $role_info['name'];
    $u_phone   = get_user_meta( $single_user->ID, 'vpdl_phone', true ) ?: __( 'Not Specified', 'vpdl-management' );
    $u_accred  = get_user_meta( $single_user->ID, 'vpdl_accreditation', true );
?>
    <!-- VIEW MODE: DETAILED PERSONNEL DOSSIER -->
    <div class="vpdl-card" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 18px;">
                    <?php echo esc_html( strtoupper( substr( $single_user->display_name, 0, 1 ) ) ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_user->display_name ); ?>
                        <span style="font-family: monospace; font-weight: 600; color: var(--vpdl-accent);">@<?php echo esc_html( $single_user->user_login ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <strong><?php echo esc_html( $u_job ); ?></strong> <?php if ( $u_accred ) : ?>(<?php echo esc_html( $u_accred ); ?>)<?php endif; ?>
                        <span class="vpdl-status-dot <?php echo 'active' === $u_status ? 'vpdl-status-active' : 'vpdl-status-suspended'; ?>"></span>
                        <strong><?php echo esc_html( ucfirst( $u_status ) ); ?></strong>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'user_id' => $single_user->ID ), $base_url ) ); ?>" style="padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; background: var(--vpdl-card-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main); display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo vpdl_get_svg( 'edit', '', 14 ); ?>
                    <span><?php esc_html_e( 'Edit / Switch Role', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="padding: 8px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; background: var(--vpdl-accent); color: #ffffff; display: inline-flex; align-items: center; gap: 6px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to Roster', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="vpdl-card" style="padding: 16px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Direct Contact', 'vpdl-management' ); ?></div>
                <div style="font-size: 14px; font-weight: 800; color: var(--vpdl-text-main); margin-top: 6px; font-family: monospace;"><?php echo esc_html( $u_phone ); ?></div>
                <div style="font-size: 11.5px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $single_user->user_email ); ?></div>
            </div>

            <div class="vpdl-card" style="padding: 16px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Assigned Clients', 'vpdl-management' ); ?></div>
                <div style="font-size: 20px; font-weight: 800; color: var(--vpdl-accent); margin-top: 6px; font-family: monospace;"><?php echo esc_html( number_format( $u_clients ) ); ?></div>
            </div>

            <div class="vpdl-card" style="padding: 16px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Proposals Produced', 'vpdl-management' ); ?></div>
                <div style="font-size: 20px; font-weight: 800; color: #f59e0b; margin-top: 6px; font-family: monospace;"><?php echo esc_html( number_format( $u_quotes ) ); ?></div>
            </div>

            <div class="vpdl-card" style="padding: 16px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Realized Turnover', 'vpdl-management' ); ?></div>
                <div style="font-size: 20px; font-weight: 800; color: #10b981; margin-top: 6px; font-family: monospace;">
                    <?php echo esc_html( $currency . ' ' . number_format( $u_sales, 2 ) ); ?>
                </div>
            </div>
        </div>

        <!-- Role Entitlements Scope Preview -->
        <div style="padding: 18px; border-radius: 10px; background: rgba(0,0,0,0.02); border: 1px dashed var(--vpdl-border-color);">
            <div style="font-size: 12px; font-weight: 800; color: var(--vpdl-text-main); margin-bottom: 8px; text-transform: uppercase;">
                <?php printf( esc_html__( 'Active Privileges for %s:', 'vpdl-management' ), esc_html( $role_info['name'] ) ); ?>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php foreach ( $role_info['caps'] as $c ) : ?>
                    <span class="vpdl-role-pill" style="background: rgba(2, 132, 199, 0.08); color: var(--vpdl-accent); border: 1px solid rgba(2, 132, 199, 0.2);">
                        <?php echo vpdl_get_svg( 'check', '', 12 ); ?>
                        <span><?php echo esc_html( $c ); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_user ) : 
    $u_roles      = (array) $single_user->roles;
    $current_role = reset( $u_roles ) ?: 'vpdl_salesman';
    $edit_job     = get_user_meta( $single_user->ID, 'vpdl_job_title', true ) ?: '';
    $edit_phone   = get_user_meta( $single_user->ID, 'vpdl_phone', true ) ?: '';
    $edit_accred  = get_user_meta( $single_user->ID, 'vpdl_accreditation', true ) ?: '';
?>
    <!-- EDIT MODE: EDIT CREDENTIALS & ROLE SWITCH CARDS -->
    <div class="vpdl-card" style="padding: 28px; max-width: 800px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'edit', '', 16 ); ?>
                <span><?php esc_html_e( 'Modify Staff Dossier & Switch Role', 'vpdl-management' ); ?> (@<?php echo esc_html( $single_user->user_login ); ?>)</span>
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Cancel', 'vpdl-management' ); ?></span>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_team_member">
            <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $single_user->ID ); ?>">

            <!-- Interactive Role Switcher Radio Cards -->
            <div style="margin-bottom: 22px;">
                <label style="display: block; font-weight: 800; font-size: 13px; margin-bottom: 10px; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Switch Security Tier / Role (Real-Time Permissions Switch)', 'vpdl-management' ); ?> *
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                    <?php foreach ( $defined_roles as $r_key => $r_val ) : 
                        $is_checked = ( $current_role === $r_key );
                    ?>
                        <label class="vpdl-role-radio-card <?php echo $is_checked ? 'selected' : ''; ?>" id="label_role_<?php echo esc_attr( $r_key ); ?>">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <input type="radio" name="user_role" value="<?php echo esc_attr( $r_key ); ?>" <?php checked( $is_checked ); ?> data-action="select-role" data-key="<?php echo esc_attr( $r_key ); ?>" style="margin: 0;">
                                <strong style="font-size: 13px; color: <?php echo esc_attr( $r_val['color'] ); ?>;"><?php echo esc_html( $r_val['name'] ); ?></strong>
                            </div>
                            <p style="margin: 0; font-size: 11px; color: var(--vpdl-text-muted); line-height: 1.4;">
                                <?php echo esc_html( $r_val['desc'] ); ?>
                            </p>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="vpdl-team-grid-2">
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Display Name', 'vpdl-management' ); ?> *</label>
                    <input type="text" name="display_name" value="<?php echo esc_attr( $single_user->display_name ); ?>" required class="vpdl-team-input">
                </div>
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Official Email', 'vpdl-management' ); ?> *</label>
                    <input type="email" name="user_email" value="<?php echo esc_attr( $single_user->user_email ); ?>" required class="vpdl-team-input">
                </div>
            </div>

            <div class="vpdl-team-grid-2">
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Discipline / Job Title', 'vpdl-management' ); ?> *</label>
                    <select name="job_title" class="vpdl-team-select">
                        <option value="Lead Architectural Designer" <?php selected( $edit_job, 'Lead Architectural Designer' ); ?>><?php esc_html_e( 'Lead Architectural Designer (PDM)', 'vpdl-management' ); ?></option>
                        <option value="Chartered Structural Engineer" <?php selected( $edit_job, 'Chartered Structural Engineer' ); ?>><?php esc_html_e( 'Chartered Structural Engineer (SDM)', 'vpdl-management' ); ?></option>
                        <option value="Party Wall Surveyor" <?php selected( $edit_job, 'Party Wall Surveyor' ); ?>><?php esc_html_e( 'Party Wall Surveyor (BSM)', 'vpdl-management' ); ?></option>
                        <option value="Project Manager / Consultant" <?php selected( $edit_job, 'Project Manager / Consultant' ); ?>><?php esc_html_e( 'Project Manager / Consultant (PMC)', 'vpdl-management' ); ?></option>
                        <option value="Senior Sales Representative" <?php selected( $edit_job, 'Senior Sales Representative' ); ?>><?php esc_html_e( 'Senior Sales Representative', 'vpdl-management' ); ?></option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Direct Phone / WhatsApp', 'vpdl-management' ); ?></label>
                    <input type="tel" name="phone" value="<?php echo esc_attr( $edit_phone ); ?>" class="vpdl-team-input" style="font-family: monospace;">
                </div>
            </div>

            <div class="vpdl-team-box" style="margin-bottom: 24px;">
                <label style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Professional Accreditation', 'vpdl-management' ); ?></label>
                <input type="text" name="accreditation" value="<?php echo esc_attr( $edit_accred ); ?>" placeholder="<?php esc_attr_e( 'e.g. RIBA, IStructE, MFPWS, RICS', 'vpdl-management' ); ?>" class="vpdl-team-input">
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Save & Apply Role Changes', 'vpdl-management' ); ?></span>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TAB NAVIGATION -->
    <div class="vpdl-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="directory" id="vpdlTeamTabBtnDirectory" class="vpdl-tab <?php echo ( 'directory' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'directory' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'team', '', 18 ); ?>
            <span><?php esc_html_e( 'Personnel Directory', 'vpdl-management' ); ?> (<?php echo esc_html( number_format( $total_members ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="create" id="vpdlTeamTabBtnCreate" class="vpdl-tab <?php echo ( 'create' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'create' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
            <span><?php esc_html_e( 'Provision Personnel', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="matrix" id="vpdlTeamTabBtnMatrix" class="vpdl-tab <?php echo ( 'matrix' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'matrix' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
            <span><?php esc_html_e( 'RBAC Permission Matrix', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Roster Directory Table with Inline Role Switcher -->
    <div id="vpdlTeamTabPaneDirectory" class="vpdl-tab-pane <?php echo ( 'directory' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-card" style="padding: 24px;">
            <div style="margin-bottom: 20px;">
                <input type="text" id="vpdlTeamSearch" placeholder="<?php esc_attr_e( 'Filter staff by name, discipline, or email...', 'vpdl-management' ); ?>" class="vpdl-team-input">
            </div>

            <div style="overflow-x: auto;">
                <table class="vpdl-tbl" id="vpdlTeamTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Practitioner', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Job Title', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Switch Role', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Assigned Clients', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Turnover', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $team_users ) ) : ?>
                            <?php foreach ( $team_users as $member ) : 
                                $u_roles      = (array) $member->roles;
                                $primary_r    = reset( $u_roles ) ?: 'vpdl_salesman';
                                $role_meta    = $defined_roles[ $primary_r ] ?? $defined_roles['vpdl_salesman'];
                                $u_status     = get_user_meta( $member->ID, 'vpdl_account_status', true ) ?: 'active';
                                $client_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM {$t_cust} WHERE assigned_to = %d", $member->ID ) );
                                $sales_volume = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(subtotal), 0) FROM {$t_inv} WHERE created_by = %s AND payment_status = 'Paid'", $member->display_name ) );
                                $job_title    = get_user_meta( $member->ID, 'vpdl_job_title', true ) ?: $role_meta['name'];
                                $phone        = get_user_meta( $member->ID, 'vpdl_phone', true );
                            ?>
                                <tr class="vpdl-team-row">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">
                                                <?php echo esc_html( strtoupper( substr( $member->display_name, 0, 1 ) ) ); ?>
                                            </div>
                                            <div>
                                                <strong style="color: var(--vpdl-text-main); font-size: 13.5px;"><?php echo esc_html( $member->display_name ); ?></strong>
                                                <div style="font-size: 11px; color: var(--vpdl-text-muted); font-family: monospace;">@<?php echo esc_html( $member->user_login ); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color: var(--vpdl-text-main); font-size: 13px;"><?php echo esc_html( $job_title ); ?></strong>
                                        <?php if ( $phone ) : ?>
                                            <div style="font-family: monospace; font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $phone ); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Inline Live Role Switcher -->
                                    <td>
                                        <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="display:inline;">
                                            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                            <input type="hidden" name="vpdl_action" value="switch_team_role">
                                            <input type="hidden" name="target_user_id" value="<?php echo esc_attr( (string) $member->ID ); ?>">
                                            <select name="new_role" data-action="auto-submit" style="font-size: 11.5px; font-weight: 700; padding: 4px 8px; border-radius: 6px; border: 1.5px solid <?php echo esc_attr( $role_meta['color'] ); ?>; background: <?php echo esc_attr( $role_meta['badge'] ); ?>; color: <?php echo esc_attr( $role_meta['color'] ); ?>; cursor: pointer;">
                                                <?php foreach ( $defined_roles as $rk => $rv ) : ?>
                                                    <option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $primary_r, $rk ); ?>>
                                                        <?php echo esc_html( $rv['name'] ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>

                                    <!-- Account Status Switch (Active / Suspended) -->
                                    <td>
                                        <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="display:inline;">
                                            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                            <input type="hidden" name="vpdl_action" value="toggle_account_status">
                                            <input type="hidden" name="target_user_id" value="<?php echo esc_attr( (string) $member->ID ); ?>">
                                            <button type="submit" style="background: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 0;">
                                                <span class="vpdl-status-dot <?php echo 'active' === $u_status ? 'vpdl-status-active' : 'vpdl-status-suspended'; ?>"></span>
                                                <span style="font-size: 11.5px; font-weight: 700; color: <?php echo 'active' === $u_status ? '#10b981' : '#ef4444'; ?>;">
                                                    <?php echo esc_html( ucfirst( $u_status ) ); ?>
                                                </span>
                                            </button>
                                        </form>
                                    </td>

                                    <td style="font-family: monospace; font-weight: 700;">
                                        <?php echo esc_html( number_format( $client_count ) ); ?>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 800; color: #10b981; font-size: 13.5px;">
                                        <?php echo esc_html( $currency . ' ' . number_format( $sales_volume, 2 ) ); ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'user_id' => $member->ID ), $base_url ) ); ?>" style="padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); margin-right: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'user_id' => $member->ID ), $base_url ) ); ?>" style="padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; background: var(--vpdl-badge-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main); margin-right: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>
                                        <?php if ( $member->ID !== get_current_user_id() ) : ?>
                                            <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>" style="display:inline;" data-action="confirm-delete">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_team_member">
                                                <input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $member->ID ); ?>">
                                                <button type="submit" style="padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                    <?php echo vpdl_get_svg( 'trash', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Delete', 'vpdl-management' ); ?></span>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span style="font-size: 11px; color: var(--vpdl-text-muted); font-style: italic; font-weight: 600;"><?php esc_html_e( 'Current User', 'vpdl-management' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--vpdl-text-muted); padding: 40px 0;">
                                    <?php esc_html_e( 'No team accounts registered yet.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Provision Account Form with Role Switch Card Picker -->
    <div id="vpdlTeamTabPaneCreate" class="vpdl-tab-pane <?php echo ( 'create' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-card" style="padding: 28px; max-width: 820px;">
            <div style="margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Provision Professional Staff Account', 'vpdl-management' ); ?>
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                    <?php esc_html_e( 'Provision access credentials for Engineers, Surveyors, Architects, or Estimators with role-based governance.', 'vpdl-management' ); ?>
                </p>
            </div>

            <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'directory', $base_url ) ); ?>">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="create_team_member">

                <!-- Interactive Role Switcher Radio Cards in Creation -->
                <div style="margin-bottom: 22px;">
                    <label style="display: block; font-weight: 800; font-size: 13px; margin-bottom: 10px; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Select Target Security Role (Interactive Switch)', 'vpdl-management' ); ?> *
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                        <?php foreach ( $defined_roles as $r_key => $r_val ) : 
                            $is_def = ( 'vpdl_salesman' === $r_key );
                        ?>
                            <label class="vpdl-role-radio-card <?php echo $is_def ? 'selected' : ''; ?>" id="create_role_<?php echo esc_attr( $r_key ); ?>">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <input type="radio" name="user_role" value="<?php echo esc_attr( $r_key ); ?>" <?php checked( $is_def ); ?> data-action="select-create-role" data-key="<?php echo esc_attr( $r_key ); ?>" style="margin: 0;">
                                    <strong style="font-size: 13px; color: <?php echo esc_attr( $r_val['color'] ); ?>;"><?php echo esc_html( $r_val['name'] ); ?></strong>
                                </div>
                                <p style="margin: 0; font-size: 11px; color: var(--vpdl-text-muted); line-height: 1.4;">
                                    <?php echo esc_html( $r_val['desc'] ); ?>
                                </p>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vpdl-team-grid-3">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Full Name', 'vpdl-management' ); ?> *</label>
                        <input type="text" name="display_name" placeholder="<?php esc_attr_e( 'e.g. Robert Smith', 'vpdl-management' ); ?>" required class="vpdl-team-input">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Username', 'vpdl-management' ); ?> *</label>
                        <input type="text" name="user_login" placeholder="robertsmith" required class="vpdl-team-input">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Official Email Address', 'vpdl-management' ); ?> *</label>
                        <input type="email" name="user_email" placeholder="robert@vpdl.co.uk" required class="vpdl-team-input">
                    </div>
                </div>

                <div class="vpdl-team-grid-2">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Professional Discipline', 'vpdl-management' ); ?> *</label>
                        <select name="job_title" class="vpdl-team-select">
                            <option value="Lead Architectural Designer"><?php esc_html_e( 'Lead Architectural Designer (PDM)', 'vpdl-management' ); ?></option>
                            <option value="Chartered Structural Engineer"><?php esc_html_e( 'Chartered Structural Engineer (SDM)', 'vpdl-management' ); ?></option>
                            <option value="Party Wall Surveyor"><?php esc_html_e( 'Party Wall Surveyor (BSM)', 'vpdl-management' ); ?></option>
                            <option value="Project Manager / Consultant"><?php esc_html_e( 'Project Manager / Consultant (PMC)', 'vpdl-management' ); ?></option>
                            <option value="Senior Estimator & Surveyor" selected><?php esc_html_e( 'Senior Estimator & Surveyor', 'vpdl-management' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Direct Phone / WhatsApp', 'vpdl-management' ); ?></label>
                        <input type="tel" name="phone" placeholder="+44 7911 123456" class="vpdl-team-input" style="font-family: monospace;">
                    </div>
                </div>

                <div class="vpdl-team-grid-2" style="margin-bottom: 24px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Professional Accreditation', 'vpdl-management' ); ?></label>
                        <input type="text" name="accreditation" placeholder="<?php esc_attr_e( 'e.g. RIBA, IStructE, MFPWS', 'vpdl-management' ); ?>" class="vpdl-team-input">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Initial Password', 'vpdl-management' ); ?> *</label>
                        <input type="password" name="user_pass" required class="vpdl-team-input">
                    </div>
                </div>

                <button type="submit" class="button button-primary" style="height: 44px; padding: 0 28px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                    <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                    <span><?php esc_html_e( 'Provision Account & Grant Access', 'vpdl-management' ); ?></span>
                </button>
            </form>
        </div>
    </div>

    <!-- TAB 3: RBAC Permission Matrix Tab -->
    <div id="vpdlTeamTabPaneMatrix" class="vpdl-tab-pane <?php echo ( 'matrix' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-card" style="padding: 24px;">
            <div style="margin-bottom: 18px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Role-Based Access Control (RBAC) Entitlements Map', 'vpdl-management' ); ?>
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                    <?php esc_html_e( 'Every role switch instantly adjusts capabilities across terminal view, invoicing, and audit statements.', 'vpdl-management' ); ?>
                </p>
            </div>

            <table class="vpdl-tbl">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Security Role / Tier', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Assigned Capability Entitlements', 'vpdl-management' ); ?></th>
                        <th><?php esc_html_e( 'Operating Authority', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $defined_roles as $r_slug => $r_info ) : ?>
                        <tr>
                            <td>
                                <strong style="color: var(--vpdl-text-main); font-size: 13.5px;"><?php echo esc_html( $r_info['name'] ); ?></strong>
                                <div style="font-family: monospace; font-size: 11px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $r_slug ); ?></div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <?php foreach ( $r_info['caps'] as $cap ) : ?>
                                        <span class="vpdl-role-pill" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main);">
                                            <?php echo vpdl_get_svg( 'check', '', 12 ); ?>
                                            <span><?php echo esc_html( $cap ); ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td style="font-size: 12px; color: var(--vpdl-text-muted);">
                                <?php 
                                echo 'administrator' === $r_slug 
                                    ? esc_html__( 'Unrestricted Practice Authority', 'vpdl-management' ) 
                                    : ( 'vpdl_manager' === $r_slug 
                                        ? esc_html__( 'Operations & Accounting Management', 'vpdl-management' ) 
                                        : esc_html__( 'Terminal Intake & Estimations', 'vpdl-management' ) 
                                    ); 
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

</div>