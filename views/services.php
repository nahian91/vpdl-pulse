<?php
/**
 * View: VPDL Enterprise Service Rates & Fee Catalog Manager
 * File: views/services.php
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

$t_services = $wpdb->prefix . 'vpdl_services';
$currency   = (string) get_option( 'vpdl_currency', '£' );
$vat_rate   = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url   = admin_url( 'admin.php?page=vpdl&view=services' );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'pdm';
$is_admin   = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );
$msg_code   = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Handle Tariff Add / Update / Delete Actions
if ( isset( $_POST['vpdl_action'] ) ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $sub_action = isset( $_POST['vpdl_action'] ) ? sanitize_key( wp_unslash( $_POST['vpdl_action'] ) ) : '';
    $tab_target = isset( $_POST['category'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['category'] ) ) ) : $active_tab;

    if ( 'add_service_tariff' === $sub_action ) {
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'PDM';
        $code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $desc     = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
        $fee      = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

        if ( ! empty( $code ) && ! empty( $desc ) ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_services} WHERE code = %s", $code ) );
            if ( ! $exists ) {
                $wpdb->insert(
                    $t_services,
                    array(
                        'category'    => $category,
                        'code'        => $code,
                        'description' => $desc,
                        'fee'         => $fee,
                    ),
                    array( '%s', '%s', '%s', '%f' )
                );
                wp_safe_redirect( add_query_arg( array( 'tab' => strtolower( $category ), 'msg' => 'tariff_added' ), $base_url ) );
                exit;
            } else {
                wp_safe_redirect( add_query_arg( array( 'tab' => strtolower( $category ), 'msg' => 'tariff_exists' ), $base_url ) );
                exit;
            }
        }
    } elseif ( 'update_service_tariff' === $sub_action ) {
        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        $category   = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'PDM';
        $desc       = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
        $fee        = isset( $_POST['fee'] ) ? floatval( $_POST['fee'] ) : 0.00;

        if ( $service_id > 0 && ! empty( $desc ) ) {
            $wpdb->update(
                $t_services,
                array(
                    'category'    => $category,
                    'description' => $desc,
                    'fee'         => $fee,
                ),
                array( 'id' => $service_id ),
                array( '%s', '%s', '%f' ),
                array( '%d' )
            );
            wp_safe_redirect( add_query_arg( array( 'tab' => strtolower( $category ), 'msg' => 'tariff_updated' ), $base_url ) );
            exit;
        }
    } elseif ( 'delete_service_tariff' === $sub_action ) {
        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        if ( $service_id > 0 ) {
            $wpdb->delete( $t_services, array( 'id' => $service_id ), array( '%d' ) );
            wp_safe_redirect( add_query_arg( array( 'tab' => $active_tab, 'msg' => 'tariff_deleted' ), $base_url ) );
            exit;
        }
    }
}

// Fetch Services by Category
$pdm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'PDM' ORDER BY code ASC" );
$sdm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'SDM' ORDER BY code ASC" );
$pmc_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'PMC' ORDER BY code ASC" );
$bsm_services = $wpdb->get_results( "SELECT * FROM {$t_services} WHERE category = 'BSM' ORDER BY code ASC" );

// Flash Notifications Mapping
$alerts = array(
    'tariff_added'   => array( 'msg' => __( 'New service fee item added to global registry.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'Service tariff updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_deleted' => array( 'msg' => __( 'Service tariff item removed from global catalog.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_exists'  => array( 'msg' => __( 'Error: Service code already exists in catalog.', 'vpdl-management' ), 'type' => 'error' ),
);
?>

<div class="vpdl-services-wrapper" id="vpdlServicesModule">

    <?php if ( isset( $alerts[$msg_code ] ) ) : ?>
        <div class="vpdl-srv-alert <?php echo 'error' === $alerts[$msg_code ]['type'] ? 'vpdl-srv-alert-error' : 'vpdl-srv-alert-success'; ?>">
            <?php echo vpdl_get_svg( 'error' === $alerts[$msg_code ]['type'] ? 'close' : 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[$msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-panel vpdl-srv-header-card">
        <div class="vpdl-srv-header-left">
            <div class="vpdl-srv-header-icon">
                <?php echo vpdl_get_svg( 'settings', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-srv-header-title"><?php esc_html_e( 'Service Rates & Fee Catalog', 'vpdl-management' ); ?></h2>
                <div class="vpdl-srv-header-subtitle">
                    <?php esc_html_e( 'Manage Practice Tariff Rates across PDM, SDM, PMC, and BSM Disciplines', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" data-action="export-csv" class="vpdl-srv-action-btn" style="background: var(--vpdl-card-bg); border: 1px solid var(--vpdl-border-color); color: var(--vpdl-text-main);">
                <?php echo vpdl_get_svg( 'file_text', '', 14 ); ?>
                <span><?php esc_html_e( 'Export CSV', 'vpdl-management' ); ?></span>
            </button>
            <span class="vpdl-srv-pill vpdl-srv-pill-green">
                <?php esc_html_e( 'Global Catalog Active', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

    <!-- Discipline Tabs Navigation -->
    <div class="vpdl-srv-tabs" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="pdm" id="vpdlServiceTabBtnPdm" class="vpdl-srv-tab-btn <?php echo ( 'pdm' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'pdm' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'planning', '', 18 ); ?>
            <span><?php esc_html_e( 'Planning Design (PDM)', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="sdm" id="vpdlServiceTabBtnSdm" class="vpdl-srv-tab-btn <?php echo ( 'sdm' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'sdm' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'structural', '', 18 ); ?>
            <span><?php esc_html_e( 'Structural Design (SDM)', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="pmc" id="vpdlServiceTabBtnPmc" class="vpdl-srv-tab-btn <?php echo ( 'pmc' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'pmc' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'consulting', '', 18 ); ?>
            <span><?php esc_html_e( 'Project Mgmt (PMC)', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="bsm" id="vpdlServiceTabBtnBsm" class="vpdl-srv-tab-btn <?php echo ( 'bsm' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'bsm' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'survey', '', 18 ); ?>
            <span><?php esc_html_e( 'Building Survey (BSM)', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB PANES -->
    <?php 
    $tabs_data = array(
        'pdm' => array( 'title' => __( 'Planning Design Management (PDM) Rate Card', 'vpdl-management' ), 'services' => $pdm_services, 'cat' => 'PDM' ),
        'sdm' => array( 'title' => __( 'Structural Design Management (SDM) Rate Card', 'vpdl-management' ), 'services' => $sdm_services, 'cat' => 'SDM' ),
        'pmc' => array( 'title' => __( 'Project Management Consultancy (PMC) Rate Card', 'vpdl-management' ), 'services' => $pmc_services, 'cat' => 'PMC' ),
        'bsm' => array( 'title' => __( 'Building Survey & Party Wall (BSM) Rate Card', 'vpdl-management' ), 'services' => $bsm_services, 'cat' => 'BSM' ),
    );

    foreach ( $tabs_data as $t_key =>$t_val ) : 
    ?>
        <div id="vpdlServiceTabPane<?php echo esc_attr( strtoupper( $t_key ) ); ?>" class="vpdl-srv-tab-pane <?php echo ( $t_key ===$active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Add New Service Item Form -->
                <?php if ( $is_admin ) : ?>
                    <div class="vpdl-panel" style="padding: 24px;">
                        <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                            <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
                            <span><?php printf( esc_html__( 'Add New %s Tariff Item', 'vpdl-management' ), esc_html( strtoupper( $t_val['cat'] ) ) ); ?></span>
                        </h3>

                        <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', $t_key,$base_url ) ); ?>">
                            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                            <input type="hidden" name="vpdl_action" value="add_service_tariff">
                            <input type="hidden" name="category" value="<?php echo esc_attr( $t_val['cat'] ); ?>">

                            <div class="vpdl-tariff-grid-add">
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Service Code', 'vpdl-management' ); ?> *</label>
                                    <input type="text" name="code" placeholder="e.g. <?php echo esc_attr( $t_val['cat'] ); ?> 05" required class="vpdl-srv-input" style="font-family: monospace; font-weight: 800; text-transform: uppercase;">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Item / Scope Description', 'vpdl-management' ); ?> *</label>
                                    <input type="text" name="description" placeholder="<?php esc_attr_e( 'e.g. Specialized Engineering Assessment & Report', 'vpdl-management' ); ?>" required class="vpdl-srv-input">
                                </div>
                                <div>
                                    <label style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;"><?php esc_html_e( 'Base Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                                    <input type="number" step="0.01" min="0" name="fee" placeholder="0.00" required class="vpdl-srv-input" style="font-family: monospace; font-weight: 800;">
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

                <!-- Active Tariff Table -->
                <div class="vpdl-panel" style="padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                        <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                            <?php echo esc_html( $t_val['title'] ); ?>
                        </h3>
                        <input type="text" id="vpdlSearchTariff_<?php echo esc_attr( $t_key ); ?>" placeholder="<?php esc_attr_e( 'Filter table...', 'vpdl-management' ); ?>" data-action="filter-tariff" data-tab="<?php echo esc_attr( $t_key ); ?>" class="vpdl-srv-input" style="font-size: 12px; width: 220px; padding: 6px 12px;">
                    </div>

                    <div class="vpdl-srv-table-wrap">
                        <table class="vpdl-srv-table" id="vpdlActiveTariffTable_<?php echo esc_attr( $t_key ); ?>">
                            <thead>
                                <tr>
                                    <th style="width: 100px;"><?php esc_html_e( 'Code', 'vpdl-management' ); ?></th>
                                    <th><?php esc_html_e( 'Description / Service Scope', 'vpdl-management' ); ?></th>
                                    <th style="text-align: right; width: 160px;"><?php esc_html_e( 'Fee (Excl. VAT)', 'vpdl-management' ); ?></th>
                                    <th style="text-align: right; width: 140px;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $t_val['services'] ) ) : ?>
                                    <?php foreach ( $t_val['services'] as$srv ) : ?>
                                        <tr class="vpdl-srv-row vpdl-tariff-item-row">
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
                                                    <button type="button" data-action="open-edit-modal" data-id="<?php echo (int) $srv->id; ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-fee="<?php echo (float) $srv->fee; ?>" data-cat="<?php echo esc_attr( $srv->category ); ?>" class="vpdl-srv-action-btn" style="background: var(--vpdl-badge-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); margin-right: 4px;">
                                                        <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                                        <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                                    </button>
                                                    <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', $t_key,$base_url ) ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'tariff item', 'vpdl-management' ); ?>">
                                                        <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                        <input type="hidden" name="vpdl_action" value="delete_service_tariff">
                                                        <input type="hidden" name="service_id" value="<?php echo (int) $srv->id; ?>">
                                                        <button type="submit" class="vpdl-srv-action-btn" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
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
                                            <?php esc_html_e( 'No service codes registered in this category.', 'vpdl-management' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- EDIT SERVICE TARIFF MODAL -->
<div id="vpdlEditServiceModal" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
    <div class="vpdl-modal-card-service">
        <div class="vpdl-modal-header-service">
            <h3 id="serviceModalTitle" style="margin: 0; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <?php echo vpdl_get_svg( 'settings', '', 16 ); ?>
                <span><?php esc_html_e( 'Edit Service Tariff', 'vpdl-management' ); ?> (<span id="modal_service_code" style="font-family: monospace; color: var(--vpdl-accent);"></span>)</span>
            </h3>
            <button type="button" data-action="close-modal" class="vpdl-modal-btn-close">
                <?php echo vpdl_get_svg( 'close', '', 16 ); ?>
            </button>
        </div>

        <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', $active_tab,$base_url ) ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_service_tariff">
            <input type="hidden" name="service_id" id="modal_service_id" value="">

            <div class="vpdl-srv-form-group">
                <label for="modal_service_cat"><?php esc_html_e( 'Discipline Category', 'vpdl-management' ); ?> *</label>
                <select name="category" id="modal_service_cat" class="vpdl-srv-select">
                    <option value="PDM"><?php esc_html_e( 'Planning Design (PDM)', 'vpdl-management' ); ?></option>
                    <option value="SDM"><?php esc_html_e( 'Structural Design (SDM)', 'vpdl-management' ); ?></option>
                    <option value="PMC"><?php esc_html_e( 'Project Management (PMC)', 'vpdl-management' ); ?></option>
                    <option value="BSM"><?php esc_html_e( 'Building Survey (BSM)', 'vpdl-management' ); ?></option>
                </select>
            </div>

            <div class="vpdl-srv-form-group">
                <label for="modal_service_desc"><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?> *</label>
                <input type="text" name="description" id="modal_service_desc" required class="vpdl-srv-input">
            </div>

            <div class="vpdl-srv-form-group" style="margin-bottom: 20px;">
                <label for="modal_service_fee"><?php esc_html_e( 'Standard Fee (Excl. VAT)', 'vpdl-management' ); ?> *</label>
                <input type="number" step="0.01" min="0" name="fee" id="modal_service_fee" required class="vpdl-srv-input" style="font-family: monospace; font-weight: 800;">
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                <button type="button" data-action="close-modal" class="vpdl-srv-action-btn" style="background: var(--vpdl-badge-bg); border-color: var(--vpdl-border-color); color: var(--vpdl-text-main); padding: 8px 16px;">
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