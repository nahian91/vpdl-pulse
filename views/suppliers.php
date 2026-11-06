<?php
/**
 * View Partial: Suppliers Directory & Vendor Management (Tabbed + Dossier Edition)
 * File: views/suppliers.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_manage_suppliers' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;
$t_supp = $wpdb->prefix . 'vpdl_suppliers';
$t_po   = $wpdb->prefix . 'vpdl_purchase_orders';

$currency = (string) get_option( 'vpdl_currency', '£' );
$base_url = admin_url( 'admin.php?page=vpdl&view=suppliers' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );
$feedback = '';

// Handle Delete Supplier
if ( isset( $_POST['vpdl_action'] ) && 'delete_supplier' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete supplier.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_supp, array( 'id' => $del_id ), array( '%d' ) );
        $feedback = 'deleted';
    }
}

// Mode & ID Extraction
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$target_id   = isset( $_GET['supplier_id'] ) ? absint( $_GET['supplier_id'] ) : 0;

// Edit Mode Resolution
$edit_id       = isset( $_GET['edit_supplier'] ) ? absint( $_GET['edit_supplier'] ) : 0;
$edit_supplier = null;
if ( $edit_id > 0 || ( 'edit' === $action_mode && $target_id > 0 ) ) {
    $resolve_id    = $edit_id > 0 ? $edit_id : $target_id;
    $edit_supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_supp} WHERE id = %d", $resolve_id ) );
}

// View Mode Resolution
$single_supplier  = null;
$supplier_pos     = array();
$supplier_metrics = null;

if ( 'view' === $action_mode && $target_id > 0 ) {
    $single_supplier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_supp} WHERE id = %d", $target_id ) );
    if ( $single_supplier ) {
        $supplier_pos     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t_po} WHERE supplier_id = %d ORDER BY id DESC", $target_id ) );
        $supplier_metrics = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(id) AS total_orders,
                    COALESCE(SUM(grand_total), 0.00) AS total_spend,
                    COALESCE(SUM(CASE WHEN status = 'Pending' THEN grand_total ELSE 0 END), 0.00) AS pending_spend
                   FROM {$t_po} 
                   WHERE supplier_id = %d",
                $target_id
            )
        );
    }
}

// Active Tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( $edit_supplier ? 'add' : 'list' );

// Suppliers Directory Query
$suppliers       = $wpdb->get_results( "SELECT * FROM {$t_supp} ORDER BY company_name ASC" );
$total_suppliers = is_array( $suppliers ) ? count( $suppliers ) : 0;

$categories = array(
    'Structural Steel',
    'Substructure Concrete',
    'Timber Engineering',
    'Specialist Surveys',
    'General Building Materials',
    'Electrical & Plumbing',
);
?>

<div class="vpdl-supp-container" id="vpdlSuppliersModule">

    <?php if ( 'deleted' === $feedback ) : ?>
        <div class="vpdl-supp-alert vpdl-supp-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php esc_html_e( 'Supplier record permanently deleted.', 'vpdl-management' ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-supp-card vpdl-supp-header-card">
        <div class="vpdl-supp-header-left">
            <div class="vpdl-supp-header-icon">
                <?php echo vpdl_get_svg( 'supplier', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-supp-header-title"><?php esc_html_e( 'Suppliers & Vendor Directory', 'vpdl-management' ); ?></h2>
                <div class="vpdl-supp-header-subtitle">
                    <?php esc_html_e( 'Manage construction material suppliers, fabricators, and trade vendors linked to Purchase Orders.', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-supp-pill vpdl-pill-header-count">
                <?php echo esc_html( number_format( $total_suppliers ) ); ?> <?php esc_html_e( 'Total Vendors Registered', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_supplier ) : ?>
    <!-- VIEW MODE: DETAILED SUPPLIER DOSSIER -->
    <div class="vpdl-supp-card vpdl-supp-table-wrap">
        <div class="vpdl-dossier-topbar">
            <div class="vpdl-dossier-header-left">
                <div class="vpdl-dossier-main-icon">
                    <?php echo vpdl_get_svg( 'supplier', '', 24 ); ?>
                </div>
                <div>
                    <h2 class="vpdl-dossier-name">
                        <?php echo esc_html( $single_supplier->company_name ); ?>
                    </h2>
                    <div class="vpdl-dossier-meta">
                        <span><?php echo esc_html( $single_supplier->category ); ?></span>
                        <span style="font-family: monospace; font-weight: 700; color: var(--vpdl-accent);">#VPDL-SUPP-<?php echo esc_html( (string) $single_supplier->id ); ?></span>
                        <span><?php esc_html_e( 'VAT No:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_supplier->vat_number ?: __( 'Unregistered', 'vpdl-management' ) ); ?></strong></span>
                    </div>
                </div>
            </div>
            
            <div class="vpdl-dossier-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=vpdl&view=purchase-orders&supplier_id=' . $single_supplier->id ) ); ?>" class="vpdl-supp-action-btn vpdl-btn-view">
                    <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                    <span><?php esc_html_e( 'Create PO', 'vpdl-management' ); ?></span>
                </a>
                <?php 
                $clean_supp_phone = preg_replace( '/[^0-9]/', '', (string) $single_supplier->phone );
                if ( ! empty( $clean_supp_phone ) ) : 
                    ?>
                    <a href="https://wa.me/<?php echo esc_attr( $clean_supp_phone ); ?>" target="_blank" rel="noopener noreferrer" class="vpdl-supp-action-btn" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); padding: 8px 14px;">
                        <?php echo vpdl_get_svg( 'whatsapp', '', 14 ); ?>
                        <span><?php esc_html_e( 'WhatsApp Direct', 'vpdl-management' ); ?></span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'add', 'edit_supplier' => $single_supplier->id ), $base_url ) ); ?>" class="vpdl-supp-action-btn vpdl-btn-edit">
                    <?php echo vpdl_get_svg( 'edit', '', 14 ); ?>
                    <span><?php esc_html_e( 'Edit Profile', 'vpdl-management' ); ?></span>
                </a>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-supp-action-btn" style="background: var(--vpdl-accent); color: #ffffff; padding: 8px 16px;">
                    <?php echo vpdl_get_svg( 'arrow_left', '', 14 ); ?>
                    <span><?php esc_html_e( 'Back to Directory', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="vpdl-dossier-metrics-grid">
            <div class="vpdl-supp-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Contact Person & Phone', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-phone"><?php echo esc_html( $single_supplier->phone ); ?></div>
                <div class="vpdl-dossier-sub-detail"><?php echo esc_html( $single_supplier->contact_person ? sprintf( __( 'Rep: %s', 'vpdl-management' ), $single_supplier->contact_person ) : __( 'No representative listed', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-supp-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Total Procurement Spend', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-gross">
                    <?php echo esc_html( $currency . ' ' . number_format( (float) ( $supplier_metrics->total_spend ?? 0 ), 2 ) ); ?>
                </div>
                <div class="vpdl-dossier-sub-detail">
                    <?php esc_html_e( 'Pending Orders:', 'vpdl-management' ); ?> <strong style="color: var(--vpdl-text-main); font-family: monospace;"><?php echo esc_html( $currency . ' ' . number_format( (float) ( $supplier_metrics->pending_spend ?? 0 ), 2 ) ); ?></strong>
                </div>
            </div>

            <div class="vpdl-supp-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Purchase Orders Logged', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val" style="color: var(--vpdl-accent);">
                    <?php echo esc_html( (string) ( $supplier_metrics->total_orders ?? 0 ) ); ?>
                </div>
                <div class="vpdl-dossier-sub-detail">
                    <?php esc_html_e( 'Category:', 'vpdl-management' ); ?> <strong><?php echo esc_html( $single_supplier->category ); ?></strong>
                </div>
            </div>

            <div class="vpdl-supp-card vpdl-dossier-metric-box">
                <div class="vpdl-dossier-metric-lbl"><?php esc_html_e( 'Business / Warehouse Address', 'vpdl-management' ); ?></div>
                <div class="vpdl-dossier-metric-val vpdl-dossier-val-addr">
                    <?php echo esc_html( $single_supplier->address ?: __( 'Standard Warehouse Depot', 'vpdl-management' ) ); ?>
                </div>
            </div>
        </div>

        <!-- Purchase Orders Linked to Vendor -->
        <div class="vpdl-supp-card" style="padding: 20px;">
            <h4 style="margin: 0 0 16px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 8px;">
                <?php echo vpdl_get_svg( 'purchase_order', '', 17 ); ?>
                <span><?php esc_html_e( 'Purchase Orders Issued to Vendor', 'vpdl-management' ); ?></span>
            </h4>

            <?php if ( ! empty( $supplier_pos ) ) : ?>
                <div class="vpdl-supp-responsive-table">
                    <table class="vpdl-supp-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'PO Number', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Related Project', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Expected Delivery', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Grand Total', 'vpdl-management' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $supplier_pos as $po ) : ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: 700; color: var(--vpdl-accent);">
                                        <?php echo esc_html( $po->po_number ); ?>
                                    </td>
                                    <td><?php echo esc_html( $po->related_project ?: '—' ); ?></td>
                                    <td><?php echo esc_html( $po->expected_delivery ?: '—' ); ?></td>
                                    <td style="font-family: monospace; font-weight: 800; color: #10b981;">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $po->grand_total, 2 ) ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-supp-pill vpdl-pill-accent"><?php echo esc_html( $po->status ); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="vpdl-supp-empty-state">
                    <?php esc_html_e( 'No purchase orders have been dispatched to this vendor yet.', 'vpdl-management' ); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else : ?>

    <!-- TABS NAVIGATION: ALL SUPPLIERS VS ADD/EDIT SUPPLIER -->
    <div class="vpdl-supp-tab-nav" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="list" id="vpdlSuppTabBtnList" class="vpdl-supp-tab-btn <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'list' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'supplier', '', 18 ); ?>
            <span><?php esc_html_e( 'All Suppliers', 'vpdl-management' ); ?> (<?php echo esc_html( number_format( $total_suppliers ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="add" id="vpdlSuppTabBtnAdd" class="vpdl-supp-tab-btn <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'add' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( $edit_supplier ? 'edit' : 'plus', '', 16 ); ?>
            <span><?php echo $edit_supplier ? esc_html__( 'Edit Supplier', 'vpdl-management' ) : esc_html__( 'Add Supplier', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: ALL SUPPLIERS TABLE -->
    <div id="vpdlSuppTabPaneList" class="vpdl-supp-tab-pane <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-supp-card vpdl-supp-table-wrap">
            <div class="vpdl-supp-search-row">
                <input type="text" id="vpdlSupplierSearch" placeholder="<?php esc_attr_e( 'Search suppliers by company, contact, category, or VAT number...', 'vpdl-management' ); ?>" class="vpdl-supp-search-input">
                <span id="vpdlSupplierCountDisplay" class="vpdl-supp-count-display">
                    <?php printf( esc_html__( 'Showing %d vendors', 'vpdl-management' ), absint( $total_suppliers ) ); ?>
                </span>
            </div>

            <div class="vpdl-supp-responsive-table">
                <table class="vpdl-supp-table" id="vpdlSuppliersTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Company & Contact', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Category', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Phone / Email', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'VAT Number', 'vpdl-management' ); ?></th>
                            <th class="vpdl-th-right"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $suppliers ) ) : ?>
                            <tr>
                                <td colspan="5" class="vpdl-supp-empty-state">
                                    <?php esc_html_e( 'No suppliers registered in the directory. Click the "Add Supplier" tab to register one.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $suppliers as $s ) : ?>
                                <tr class="vpdl-supp-row">
                                    <td>
                                        <strong class="vpdl-supp-company-title"><?php echo esc_html( $s->company_name ); ?></strong>
                                        <?php if ( ! empty( $s->contact_person ) ) : ?>
                                            <span class="vpdl-supp-contact-sub"><?php printf( esc_html__( 'Contact: %s', 'vpdl-management' ), esc_html( $s->contact_person ) ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-supp-pill vpdl-pill-accent">
                                            <?php echo esc_html( $s->category ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="vpdl-supp-phone-val"><?php echo esc_html( $s->phone ); ?></div>
                                        <?php if ( ! empty( $s->email ) ) : ?>
                                            <span class="vpdl-supp-email-sub"><?php echo esc_html( $s->email ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="vpdl-supp-vat-val">
                                        <?php echo esc_html( $s->vat_number ?: 'N/A' ); ?>
                                    </td>
                                    <td class="vpdl-supp-actions-cell">
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'supplier_id' => $s->id ), $base_url ) ); ?>" class="vpdl-supp-action-btn vpdl-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </a>

                                        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'add', 'edit_supplier' => $s->id ), $base_url ) ); ?>" class="vpdl-supp-action-btn vpdl-btn-edit">
                                            <?php echo vpdl_get_svg( 'edit', '', 13 ); ?>
                                            <span><?php esc_html_e( 'Edit', 'vpdl-management' ); ?></span>
                                        </a>

                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" style="display:inline;" data-action="delete-supplier">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_supplier">
                                                <input type="hidden" name="supplier_id" value="<?php echo esc_attr( (string) $s->id ); ?>">
                                                <button type="submit" class="vpdl-supp-action-btn vpdl-btn-delete">
                                                    <?php echo vpdl_get_svg( 'trash', '', 13 ); ?>
                                                    <span><?php esc_html_e( 'Delete', 'vpdl-management' ); ?></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: ADD / EDIT SUPPLIER FORM -->
    <div id="vpdlSuppTabPaneAdd" class="vpdl-supp-tab-pane <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-supp-card vpdl-supp-form-card">
            <div class="vpdl-supp-form-header">
                <div>
                    <h3 class="vpdl-supp-form-title">
                        <?php echo vpdl_get_svg( $edit_supplier ? 'edit' : 'plus', '', 18 ); ?>
                        <span><?php echo $edit_supplier ? esc_html__( 'Edit Supplier Profile', 'vpdl-management' ) : esc_html__( 'Register New Supplier & Vendor', 'vpdl-management' ); ?></span>
                    </h3>
                    <p class="vpdl-supp-form-subtitle">
                        <?php esc_html_e( 'Store vendor credentials, supply category, and tax details to populate Purchase Orders.', 'vpdl-management' ); ?>
                    </p>
                </div>
                <?php if ( $edit_supplier ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-supp-btn-discard">
                        <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                        <span><?php esc_html_e( 'Cancel Edit', 'vpdl-management' ); ?></span>
                    </a>
                <?php endif; ?>
            </div>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=vpdl' ) ); ?>">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="save_supplier">
                <?php if ( $edit_supplier ) : ?>
                    <input type="hidden" name="supplier_id" value="<?php echo esc_attr( (string) $edit_supplier->id ); ?>">
                <?php endif; ?>

                <!-- Row 1: Company Name & Contact Person -->
                <div class="vpdl-supp-grid-2 vpdl-supp-form-group">
                    <div>
                        <label for="vpdl_supp_company_name"><?php esc_html_e( 'Company Name', 'vpdl-management' ); ?> <span class="vpdl-supp-req">*</span></label>
                        <input type="text" id="vpdl_supp_company_name" name="company_name" value="<?php echo esc_attr( $edit_supplier->company_name ?? '' ); ?>" required class="vpdl-supp-input" placeholder="<?php esc_attr_e( 'e.g. British Steel Fabrication Ltd', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_supp_contact_person"><?php esc_html_e( 'Contact Person', 'vpdl-management' ); ?></label>
                        <input type="text" id="vpdl_supp_contact_person" name="contact_person" value="<?php echo esc_attr( $edit_supplier->contact_person ?? '' ); ?>" class="vpdl-supp-input" placeholder="<?php esc_attr_e( 'e.g. Mark Evans', 'vpdl-management' ); ?>">
                    </div>
                </div>

                <!-- Row 2: Phone, Email & Supply Category -->
                <div class="vpdl-supp-grid-3 vpdl-supp-form-group">
                    <div>
                        <label for="vpdl_supp_phone"><?php esc_html_e( 'Phone Number', 'vpdl-management' ); ?> <span class="vpdl-supp-req">*</span></label>
                        <input type="text" id="vpdl_supp_phone" name="phone" value="<?php echo esc_attr( $edit_supplier->phone ?? '' ); ?>" required class="vpdl-supp-input" placeholder="<?php esc_attr_e( 'e.g. +44 1633 892012', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_supp_email"><?php esc_html_e( 'Email Address', 'vpdl-management' ); ?></label>
                        <input type="email" id="vpdl_supp_email" name="email" value="<?php echo esc_attr( $edit_supplier->email ?? '' ); ?>" class="vpdl-supp-input" placeholder="<?php esc_attr_e( 'e.g. orders@supplier.co.uk', 'vpdl-management' ); ?>">
                    </div>
                    <div>
                        <label for="vpdl_supp_category"><?php esc_html_e( 'Supply Category', 'vpdl-management' ); ?></label>
                        <select id="vpdl_supp_category" name="category" class="vpdl-supp-select">
                            <?php 
                            $selected_cat = $edit_supplier->category ?? 'Structural Steel';
                            foreach ( $categories as $cat ) {
                                echo '<option value="' . esc_attr( $cat ) . '" ' . selected( $selected_cat, $cat, false ) . '>' . esc_html( $cat ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Row 3: VAT Number -->
                <div class="vpdl-supp-form-group">
                    <label for="vpdl_supp_vat_number"><?php esc_html_e( 'VAT Registration Number', 'vpdl-management' ); ?></label>
                    <input type="text" id="vpdl_supp_vat_number" name="vat_number" value="<?php echo esc_attr( $edit_supplier->vat_number ?? '' ); ?>" class="vpdl-supp-input" placeholder="<?php esc_attr_e( 'e.g. GB 992 4810 11', 'vpdl-management' ); ?>">
                </div>

                <!-- Row 4: Business Address -->
                <div class="vpdl-supp-form-group">
                    <label for="vpdl_supp_address"><?php esc_html_e( 'Business Address', 'vpdl-management' ); ?></label>
                    <textarea id="vpdl_supp_address" name="address" rows="3" class="vpdl-supp-textarea" placeholder="<?php esc_attr_e( 'Full corporate or warehouse address', 'vpdl-management' ); ?>"><?php echo esc_textarea( $edit_supplier->address ?? '' ); ?></textarea>
                </div>

                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="vpdl-supp-btn-primary">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php echo $edit_supplier ? esc_html__( 'Update Supplier Profile', 'vpdl-management' ) : esc_html__( 'Save & Register Supplier', 'vpdl-management' ); ?></span>
                    </button>
                    <?php if ( $edit_supplier ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-supp-btn-discard">
                            <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

</div>