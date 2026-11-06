<?php
/**
 * View Partial: Purchase Orders & Vendor Supply Chain Management
 * File: views/purchase-orders.php
 *
 * @package VPDLManagement
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'vpdl_manage_po' ) && ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
}

global $wpdb;
$t_po   = $wpdb->prefix . 'vpdl_purchase_orders';
$t_supp = $wpdb->prefix . 'vpdl_suppliers';

$currency      = (string) get_option( 'vpdl_currency', '£' );
$vat_rate      = (float) get_option( 'vpdl_vat_rate', 20.00 );
$business_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
$reg_no        = (string) get_option( 'vpdl_reg_no', '08456916' );
$vat_number    = (string) get_option( 'vpdl_vat_number', '' );
$base_url      = admin_url( 'admin.php?page=vpdl&view=purchase-orders' );

// Preset Supplier preselection if navigated from Supplier Dossier
$preset_supplier_id = isset( $_GET['supplier_id'] ) ? absint( $_GET['supplier_id'] ) : 0;
$active_tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( $preset_supplier_id > 0 ? 'add' : 'list' );

// Active Suppliers
$suppliers = $wpdb->get_results( "SELECT id, company_name, category, phone, email FROM {$t_supp} WHERE status = 'Active' ORDER BY company_name ASC" );

// Purchase Orders Query with Supplier Join
$purchase_orders = $wpdb->get_results(
    "SELECT po.*, s.company_name AS supplier_name, s.phone AS supplier_phone, s.email AS supplier_email, s.category AS supplier_category
     FROM {$t_po} po 
     LEFT JOIN {$t_supp} s ON po.supplier_id = s.id 
     ORDER BY po.id DESC"
);

$total_orders = is_array( $purchase_orders ) ? count( $purchase_orders ) : 0;
?>

<div class="vpdl-po-container" id="vpdlPoModule">

    <!-- Header Panel -->
    <div class="vpdl-po-card vpdl-po-header-card">
        <div class="vpdl-po-header-left">
            <div class="vpdl-po-header-icon">
                <?php echo vpdl_get_svg( 'purchase_order', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-po-header-title"><?php esc_html_e( 'Purchase Orders & Supply Chain', 'vpdl-management' ); ?></h2>
                <div class="vpdl-po-header-subtitle">
                    <?php esc_html_e( 'Issue procurement orders, track material delivery milestones, and sync inventory restocking.', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-po-pill vpdl-po-pill-count">
                <?php printf( esc_html__( 'Total Orders: %d', 'vpdl-management' ), absint( $total_orders ) ); ?>
            </span>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="vpdl-po-tab-nav" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="list" id="vpdlPoTabBtnList" class="vpdl-po-tab-btn <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'list' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'purchase_order', '', 18 ); ?>
            <span><?php esc_html_e( 'All Purchase Orders', 'vpdl-management' ); ?> (<?php echo esc_html( number_format( $total_orders ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="add" id="vpdlPoTabBtnAdd" class="vpdl-po-tab-btn <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'add' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'plus', '', 16 ); ?>
            <span><?php esc_html_e( 'Issue Purchase Order', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: ALL PURCHASE ORDERS TABLE -->
    <div id="vpdlPoTabPaneList" class="vpdl-po-tab-pane <?php echo ( 'list' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-po-card vpdl-po-table-wrap">
            <div class="vpdl-po-search-row">
                <input type="text" id="vpdlPoSearch" placeholder="<?php esc_attr_e( 'Search PO number, supplier, or project site...', 'vpdl-management' ); ?>" class="vpdl-po-search-input">
                <span id="vpdlPoCountDisplay" class="vpdl-po-count-display">
                    <?php printf( esc_html__( 'Showing %d orders', 'vpdl-management' ), absint( $total_orders ) ); ?>
                </span>
            </div>

            <div class="vpdl-po-responsive-table">
                <table class="vpdl-po-table" id="vpdlPoTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'PO Number', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Supplier / Vendor', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Project / Site', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Gross Amount', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Delivery Date', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'vpdl-management' ); ?></th>
                            <th class="vpdl-th-right"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $purchase_orders ) ) : ?>
                            <tr>
                                <td colspan="7" class="vpdl-po-empty-state">
                                    <?php esc_html_e( 'No purchase orders recorded yet. Switch to the "Issue Purchase Order" tab to begin procurement.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php
                            foreach ( $purchase_orders as $po ) :
                                $status_class = 'vpdl-status-pending';
                                if ( 'Received' === $po->status ) {
                                    $status_class = 'vpdl-status-received';
                                } elseif ( 'Cancelled' === $po->status ) {
                                    $status_class = 'vpdl-status-cancelled';
                                }

                                $po_payload = array(
                                    'po_number'         => (string) $po->po_number,
                                    'supplier_name'     => (string) ( $po->supplier_name ?: __( 'Unknown Supplier', 'vpdl-management' ) ),
                                    'supplier_phone'    => (string) ( $po->supplier_phone ?: '—' ),
                                    'supplier_email'    => (string) ( $po->supplier_email ?: '—' ),
                                    'supplier_category' => (string) ( $po->supplier_category ?: '—' ),
                                    'related_project'   => (string) $po->related_project,
                                    'expected_delivery' => (string) ( $po->expected_delivery ? date_i18n( 'd M Y', strtotime( $po->expected_delivery ) ) : '—' ),
                                    'status'            => (string) $po->status,
                                    'notes'             => (string) $po->notes,
                                    'created_by'        => (string) $po->created_by,
                                    'created_at'        => (string) date_i18n( 'd M Y, H:i', strtotime( $po->created_at ) ),
                                    'subtotal'          => $currency . ' ' . number_format( (float) $po->subtotal, 2 ),
                                    'vat_amount'        => $currency . ' ' . number_format( (float) $po->vat_amount, 2 ),
                                    'grand_total'       => $currency . ' ' . number_format( (float) $po->grand_total, 2 ),
                                    'items'             => json_decode( (string) $po->items_json, true ) ?: array(),
                                );
                                ?>
                                <tr class="vpdl-po-row">
                                    <td class="vpdl-po-num-cell">
                                        <?php echo esc_html( $po->po_number ); ?>
                                    </td>
                                    <td>
                                        <strong class="vpdl-po-vendor-title"><?php echo esc_html( $po->supplier_name ?: __( 'Unknown Supplier', 'vpdl-management' ) ); ?></strong>
                                        <span class="vpdl-po-vendor-meta"><?php echo esc_html( $po->supplier_phone ?: '—' ); ?></span>
                                    </td>
                                    <td class="vpdl-po-project-cell">
                                        <?php echo esc_html( $po->related_project ); ?>
                                    </td>
                                    <td class="vpdl-po-gross-cell">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $po->grand_total, 2 ) ); ?>
                                    </td>
                                    <td class="vpdl-po-date-cell">
                                        <?php echo esc_html( $po->expected_delivery ? date_i18n( 'd M Y', strtotime( $po->expected_delivery ) ) : '—' ); ?>
                                    </td>
                                    <td>
                                        <span class="vpdl-po-status-badge <?php echo esc_attr( $status_class ); ?>">
                                            <?php echo esc_html( $po->status ); ?>
                                        </span>
                                    </td>
                                    <td class="vpdl-po-actions-cell">
                                        <button type="button" data-action="view-po-modal" data-po="<?php echo esc_attr( wp_json_encode( $po_payload ) ); ?>" class="vpdl-po-btn-view">
                                            <?php echo vpdl_get_svg( 'eye', '', 13 ); ?>
                                            <span><?php esc_html_e( 'View', 'vpdl-management' ); ?></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: ISSUE NEW PURCHASE ORDER FORM -->
    <div id="vpdlPoTabPaneAdd" class="vpdl-po-tab-pane <?php echo ( 'add' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-po-card vpdl-po-form-card">
            <div class="vpdl-po-form-header">
                <h3 class="vpdl-po-form-title">
                    <?php echo vpdl_get_svg( 'plus', '', 18 ); ?>
                    <span><?php esc_html_e( 'New Purchase Order Requisition', 'vpdl-management' ); ?></span>
                </h3>
                <p class="vpdl-po-form-subtitle">
                    <?php esc_html_e( 'Requisition materials, structural components, or sub-contracted technical services.', 'vpdl-management' ); ?>
                </p>
            </div>

            <form method="POST" action="<?php echo esc_url( admin_url( 'admin.php?page=vpdl' ) ); ?>" id="vpdlPoCreateForm">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="create_purchase_order">

                <!-- Row 1: Supplier, Project, and Delivery Date -->
                <div class="vpdl-po-grid-3">
                    <div>
                        <label for="vpdl_po_supplier_id"><?php esc_html_e( 'Supplier / Vendor', 'vpdl-management' ); ?> <span class="vpdl-po-req">*</span></label>
                        <select name="supplier_id" id="vpdl_po_supplier_id" required class="vpdl-po-select">
                            <option value=""><?php esc_html_e( '-- Choose Supplier --', 'vpdl-management' ); ?></option>
                            <?php foreach ( $suppliers as $supp ) : ?>
                                <option value="<?php echo esc_attr( (string) $supp->id ); ?>" <?php selected( $preset_supplier_id, $supp->id ); ?>>
                                    <?php echo esc_html( $supp->company_name . ' (' . $supp->category . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="vpdl_po_related_project"><?php esc_html_e( 'Related Project / Site', 'vpdl-management' ); ?> <span class="vpdl-po-req">*</span></label>
                        <input type="text" name="related_project" id="vpdl_po_related_project" required class="vpdl-po-input" placeholder="<?php esc_attr_e( 'e.g. 9-11 Chelford Extension', 'vpdl-management' ); ?>">
                    </div>

                    <div>
                        <label for="vpdl_po_expected_delivery"><?php esc_html_e( 'Expected Delivery Date', 'vpdl-management' ); ?> <span class="vpdl-po-req">*</span></label>
                        <input type="date" name="expected_delivery" id="vpdl_po_expected_delivery" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required class="vpdl-po-input">
                    </div>
                </div>

                <!-- Row 2: Status -->
                <div class="vpdl-po-form-group" style="max-width: 300px;">
                    <label for="vpdl_po_status"><?php esc_html_e( 'Initial PO Status', 'vpdl-management' ); ?></label>
                    <select name="status" id="vpdl_po_status" class="vpdl-po-select">
                        <option value="Pending"><?php esc_html_e( 'Pending Confirmation', 'vpdl-management' ); ?></option>
                        <option value="Received"><?php esc_html_e( 'Received & Checked', 'vpdl-management' ); ?></option>
                        <option value="Overdue"><?php esc_html_e( 'Overdue Milestone', 'vpdl-management' ); ?></option>
                    </select>
                </div>

                <!-- Line Items Section -->
                <div class="vpdl-po-items-section">
                    <label><?php esc_html_e( 'Procurement Line Items & Materials', 'vpdl-management' ); ?></label>

                    <div class="vpdl-po-items-header">
                        <div><?php esc_html_e( 'Item Description', 'vpdl-management' ); ?></div>
                        <div><?php esc_html_e( 'Qty', 'vpdl-management' ); ?></div>
                        <div><?php esc_html_e( 'Unit Price (£)', 'vpdl-management' ); ?></div>
                        <div style="text-align: right;"><?php esc_html_e( 'Line Total', 'vpdl-management' ); ?></div>
                        <div></div>
                    </div>

                    <div id="vpdlPoItemsContainer" class="vpdl-po-items-container">
                        <!-- Initial Row (Index 0) -->
                        <div class="vpdl-po-item-row" data-index="0">
                            <input type="text" name="items[0][description]" placeholder="<?php esc_attr_e( 'e.g. 203x133x25 UB Universal Steel Beam (6m length)', 'vpdl-management' ); ?>" required class="vpdl-po-input">
                            <input type="number" step="1" min="1" name="items[0][quantity]" value="1" required class="vpdl-po-input vpdl-calc-qty">
                            <input type="number" step="0.01" min="0" name="items[0][unit_price]" value="0.00" required class="vpdl-po-input vpdl-calc-price">
                            <div class="vpdl-po-item-total"><?php echo esc_html( $currency ); ?>0.00</div>
                            <input type="hidden" name="items[0][total]" class="vpdl-hidden-line-total" value="0.00">
                            <button type="button" class="vpdl-btn-remove-row" data-action="remove-row" title="<?php esc_attr_e( 'Remove Row', 'vpdl-management' ); ?>">×</button>
                        </div>
                    </div>

                    <button type="button" id="vpdlBtnAddPoRow" class="vpdl-btn-add-item">
                        <?php echo vpdl_get_svg( 'plus', '', 14 ); ?>
                        <span><?php esc_html_e( 'Add Another Item', 'vpdl-management' ); ?></span>
                    </button>
                </div>

                <!-- Totals & Notes -->
                <div class="vpdl-po-totals-grid">
                    <div>
                        <label for="vpdl_po_notes"><?php esc_html_e( 'Requisition Notes & Delivery Instructions', 'vpdl-management' ); ?></label>
                        <textarea name="notes" id="vpdl_po_notes" rows="4" class="vpdl-po-textarea" placeholder="<?php esc_attr_e( 'Site access gates, offloading hoist instructions, site contact...', 'vpdl-management' ); ?>"></textarea>
                    </div>

                    <div class="vpdl-po-summary-box">
                        <div class="vpdl-po-summary-row">
                            <span><?php esc_html_e( 'Subtotal Ex. VAT:', 'vpdl-management' ); ?></span>
                            <strong id="poDisplaySubtotal"><?php echo esc_html( $currency ); ?>0.00</strong>
                        </div>
                        <div class="vpdl-po-summary-row">
                            <span><?php printf( esc_html__( 'VAT (%s%%):', 'vpdl-management' ), esc_html( (string) $vat_rate ) ); ?></span>
                            <strong id="poDisplayVat"><?php echo esc_html( $currency ); ?>0.00</strong>
                        </div>
                        <div class="vpdl-po-summary-grand">
                            <span><?php esc_html_e( 'Grand Total:', 'vpdl-management' ); ?></span>
                            <strong id="poDisplayGrand"><?php echo esc_html( $currency ); ?>0.00</strong>
                        </div>
                        <input type="hidden" name="subtotal" id="poInputSubtotal" value="0.00">
                    </div>
                </div>

                <div class="vpdl-po-submit-row">
                    <a href="<?php echo esc_url( add_query_arg( 'tab', 'list', $base_url ) ); ?>" class="vpdl-po-btn-discard">
                        <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                    </a>
                    <button type="submit" class="vpdl-po-btn-submit">
                        <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
                        <span><?php esc_html_e( 'Issue Purchase Order', 'vpdl-management' ); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Purchase Order Dossier View Modal -->
<div id="vpdlPoViewModal" role="dialog" aria-modal="true" aria-labelledby="poModalTitle">
    <div class="vpdl-modal-card">
        <div class="vpdl-modal-header">
            <div>
                <h2 id="poModalTitle" class="vpdl-modal-bizname"><?php echo esc_html( $business_name ); ?></h2>
                <div class="vpdl-modal-bizmeta">
                    <?php esc_html_e( 'CRN:', 'vpdl-management' ); ?> <?php echo esc_html( $reg_no ); ?> 
                    <?php if ( ! empty( $vat_number ) ) : ?> | <?php esc_html_e( 'VAT:', 'vpdl-management' ); ?> <?php echo esc_html( $vat_number ); ?><?php endif; ?>
                </div>
            </div>
            <div style="text-align: right;">
                <span class="vpdl-modal-badge"><?php esc_html_e( 'Purchase Order Requisition', 'vpdl-management' ); ?></span>
                <div style="font-size: 11px; font-family: monospace; font-weight: 800; color: #0284c7; margin-top: 5px;" id="modalPoNum"></div>
            </div>
        </div>

        <div id="vpdlPoPrintArea">
            <div class="vpdl-modal-meta-grid">
                <div class="vpdl-modal-box">
                    <strong class="vpdl-modal-box-title"><?php esc_html_e( 'Vendor Information', 'vpdl-management' ); ?></strong>
                    <div id="modalPoVendorName" style="font-weight: 800; font-size: 13.5px; color: #0f172a;"></div>
                    <div id="modalPoVendorPhone" style="font-family: monospace; font-size: 12px; margin-top: 2px;"></div>
                    <div id="modalPoVendorCategory" style="font-size: 11.5px; color: #64748b; margin-top: 2px;"></div>
                </div>

                <div class="vpdl-modal-box">
                    <strong class="vpdl-modal-box-title"><?php esc_html_e( 'Project & Dispatch', 'vpdl-management' ); ?></strong>
                    <div><?php esc_html_e( 'Project:', 'vpdl-management' ); ?> <strong id="modalPoProject" style="color: #0f172a;"></strong></div>
                    <div style="margin-top: 2px;"><?php esc_html_e( 'Delivery Due:', 'vpdl-management' ); ?> <strong id="modalPoDelivery" style="font-family: monospace;"></strong></div>
                    <div style="margin-top: 2px;"><?php esc_html_e( 'Status:', 'vpdl-management' ); ?> <span id="modalPoStatus" style="font-weight: 700;"></span></div>
                </div>
            </div>

            <!-- Itemized Breakdown -->
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 10px 12px; font-weight: 700; color: #64748b;"><?php esc_html_e( 'Description', 'vpdl-management' ); ?></th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #64748b; text-align: center;"><?php esc_html_e( 'Qty', 'vpdl-management' ); ?></th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #64748b; text-align: right;"><?php esc_html_e( 'Unit Price', 'vpdl-management' ); ?></th>
                            <th style="padding: 10px 12px; font-weight: 700; color: #64748b; text-align: right;"><?php esc_html_e( 'Total', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="modalPoItemsBody">
                        <!-- Injected dynamically via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Financial Recap -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 4px;">
                    <span><?php esc_html_e( 'Net Subtotal:', 'vpdl-management' ); ?></span>
                    <strong id="modalPoSubtotal" style="font-family: monospace;"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 6px;">
                    <span><?php esc_html_e( 'VAT Total:', 'vpdl-management' ); ?></span>
                    <strong id="modalPoVat" style="font-family: monospace;"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; border-top: 1px solid #cbd5e1; padding-top: 6px; color: #0284c7;">
                    <span><?php esc_html_e( 'Grand Total:', 'vpdl-management' ); ?></span>
                    <strong id="modalPoGrand" style="font-family: monospace;"></strong>
                </div>
            </div>

            <div style="padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11.5px; margin-bottom: 12px;">
                <strong><?php esc_html_e( 'Notes / Offloading Instructions:', 'vpdl-management' ); ?></strong>
                <div id="modalPoNotes" style="margin-top: 3px; color: #475569;"></div>
            </div>
        </div>

        <div class="vpdl-modal-footer">
            <button type="button" data-action="close-po-modal" class="vpdl-btn-close-modal">
                <?php echo vpdl_get_svg( 'close', '', 14 ); ?>
                <span><?php esc_html_e( 'Close', 'vpdl-management' ); ?></span>
            </button>
            <button type="button" data-action="print-po-modal" class="vpdl-btn-print-modal">
                <?php echo vpdl_get_svg( 'printer', '', 16 ); ?>
                <span><?php esc_html_e( 'Print Purchase Order', 'vpdl-management' ); ?></span>
            </button>
        </div>
    </div>
</div>