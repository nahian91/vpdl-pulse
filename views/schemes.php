<?php
/**
 * View: Construction Schemes (Quotation Generator & Area-Based Calculator)
 * File: views/schemes.php
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
$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
$t_services = $wpdb->prefix . 'vpdl_services';
$t_inv      = $wpdb->prefix . 'vpdl_invoices';

$currency = (string) get_option( 'vpdl_currency', '£' );
$vat_rate = (float) get_option( 'vpdl_vat_rate', 20.00 );
$base_url = admin_url( 'admin.php?page=vpdl&view=schemes' );
$is_admin = current_user_can( 'manage_options' ) || current_user_can( 'vpdl_manage_settings' );

// Handle Dynamic Schemes Pricing Options
$scheme1_rate = (float) get_option( 'vpdl_scheme1_rate', 2250.00 );
$scheme1_desc = (string) get_option( 'vpdl_scheme1_desc', 'Basic construction skeleton, shell conversion, internal plasterboard ready for decorative trades.' );

$scheme2_rate = (float) get_option( 'vpdl_scheme2_rate', 2500.00 );
$scheme2_desc = (string) get_option( 'vpdl_scheme2_desc', 'Fully plastered and serviced shell that meets all planning, building regulations, and mortgage specs.' );

$scheme3_rate = (float) get_option( 'vpdl_scheme3_rate', 3500.00 );
$scheme3_desc = (string) get_option( 'vpdl_scheme3_desc', 'Turnkey full-finish solution including 1st/2nd fix electrics, gas heating, bathrooms, and high-spec flooring.' );

// Handle Routing & Tab States
$action_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'list';
$pre_cust_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ( $pre_cust_id > 0 || 'add' === $action_mode ? 'builder' : 'registry' );
$target_id   = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
$msg_code    = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

// 1. Delete Quotation Handler
if ( isset( $_POST['vpdl_action'] ) && 'delete_quotation' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges to delete quotation.', 'vpdl-management' ), 403 );
    }

    $del_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
    if ( $del_id > 0 ) {
        $wpdb->delete( $t_quotes, array( 'id' => $del_id ), array( '%d' ) );
        wp_safe_redirect( add_query_arg( array( 'tab' => 'registry', 'msg' => 'quote_deleted' ), $base_url ) );
        exit;
    }
}

// 2. Edit Update Handler (Quotation Record)
if ( isset( $_POST['vpdl_action'] ) && 'update_quotation' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! current_user_can( 'vpdl_create_quotes' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    $quote_id  = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
    $status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Draft';
    $total_sqm = isset( $_POST['total_sqm'] ) ? (float) $_POST['total_sqm'] : 0.00;

    if ( $quote_id > 0 ) {
        $wpdb->update(
            $t_quotes,
            array(
                'status'    => $status,
                'total_sqm' => $total_sqm,
            ),
            array( 'id' => $quote_id ),
            array( '%s', '%f' ),
            array( '%d' )
        );

        wp_safe_redirect( add_query_arg( array( 'tab' => 'registry', 'msg' => 'quote_updated' ), $base_url ) );
        exit;
    }
}

// 3. Scheme Tariff Rate Update Handler
if ( isset( $_POST['vpdl_action'] ) && 'update_scheme_tariffs' === $_POST['vpdl_action'] ) {
    check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

    if ( ! $is_admin ) {
        wp_die( esc_html__( 'Forbidden: Insufficient privileges.', 'vpdl-management' ), 403 );
    }

    update_option( 'vpdl_scheme1_rate', floatval( $_POST['scheme1_rate'] ?? 2250.00 ) );
    update_option( 'vpdl_scheme1_desc', sanitize_textarea_field( wp_unslash( $_POST['scheme1_desc'] ?? '' ) ) );

    update_option( 'vpdl_scheme2_rate', floatval( $_POST['scheme2_rate'] ?? 2500.00 ) );
    update_option( 'vpdl_scheme2_desc', sanitize_textarea_field( wp_unslash( $_POST['scheme2_desc'] ?? '' ) ) );

    update_option( 'vpdl_scheme3_rate', floatval( $_POST['scheme3_rate'] ?? 3500.00 ) );
    update_option( 'vpdl_scheme3_desc', sanitize_textarea_field( wp_unslash( $_POST['scheme3_desc'] ?? '' ) ) );

    wp_safe_redirect( add_query_arg( array( 'tab' => 'schemes_tariff', 'msg' => 'tariff_updated' ), $base_url ) );
    exit;
}

$customers    = $wpdb->get_results( "SELECT id, name, phone, site_address, postcode FROM {$t_cust} ORDER BY name ASC" );
$all_services = $wpdb->get_results( "SELECT * FROM {$t_services} ORDER BY category ASC, code ASC" );

// Recent Quotations with Linked Invoice Status
$quotations = $wpdb->get_results(
    "SELECT q.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.site_address, c.postcode,
            i.invoice_number, i.payment_status
     FROM {$t_quotes} q 
     LEFT JOIN {$t_cust} c ON q.customer_id = c.id 
     LEFT JOIN {$t_inv} i ON i.quote_id = q.id
     ORDER BY q.id DESC 
     LIMIT 50"
);

// View / Edit Single Quote Data
$single_quote = null;
if ( ( 'view' === $action_mode || 'edit' === $action_mode ) && $target_id > 0 ) {
    $single_quote = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT q.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email, c.site_address, c.postcode,
                    i.invoice_number, i.payment_status
             FROM {$t_quotes} q 
             LEFT JOIN {$t_cust} c ON q.customer_id = c.id 
             LEFT JOIN {$t_inv} i ON i.quote_id = q.id
             WHERE q.id = %d",
            $target_id
        )
    );
}

// Flash Notifications Mapping
$alerts = array(
    'quote_deleted'  => array( 'msg' => __( 'Quotation proposal permanently removed from registry.', 'vpdl-management' ), 'type' => 'success' ),
    'quote_updated'  => array( 'msg' => __( 'Quotation record updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
    'tariff_updated' => array( 'msg' => __( 'Construction scheme rates and descriptions updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
);
?>

<div class="vpdl-schemes-wrapper" id="vpdlSchemesModule">

    <?php if ( isset( $alerts[ $msg_code ] ) ) : ?>
        <div class="vpdl-schemes-alert vpdl-schemes-alert-success">
            <?php echo vpdl_get_svg( 'check', '', 16 ); ?>
            <span><?php echo esc_html( $alerts[ $msg_code ]['msg'] ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header Panel -->
    <div class="vpdl-panel vpdl-schemes-header vpdl-no-print">
        <div class="vpdl-schemes-header-left">
            <div class="vpdl-schemes-header-icon">
                <?php echo vpdl_get_svg( 'schemes', '', 22 ); ?>
            </div>
            <div>
                <h2 class="vpdl-schemes-header-title"><?php esc_html_e( 'Construction Schemes & Quotation Builder', 'vpdl-management' ); ?></h2>
                <div class="vpdl-schemes-header-subtitle">
                    <?php esc_html_e( 'Area-Based (£/sq.m) Architectural Schemes & Modular Professional Proposals', 'vpdl-management' ); ?>
                </div>
            </div>
        </div>

        <div>
            <span class="vpdl-schemes-pill-amber">
                <?php esc_html_e( 'All Base Tariff Fees Are Excluding VAT', 'vpdl-management' ); ?>
            </span>
        </div>
    </div>

<?php if ( 'view' === $action_mode && $single_quote ) : 
    $step_completed = 2;
    if ( 'Paid' === $single_quote->payment_status ) {
        $step_completed = 5;
    } elseif ( ! empty( $single_quote->invoice_number ) || 'Converted' === $single_quote->status ) {
        $step_completed = 4;
    }
?>
    <!-- Visual Lifecycle Pipeline Tracker -->
    <div class="vpdl-panel vpdl-no-print" style="padding: 16px 24px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; overflow-x: auto; gap: 10px;">
        <?php 
        $steps = array(
            1 => __( '1. Enquiry Logged', 'vpdl-management' ),
            2 => __( '2. Proposal Issued', 'vpdl-management' ),
            3 => __( '3. Design Specs', 'vpdl-management' ),
            4 => __( '4. Invoiced Due', 'vpdl-management' ),
            5 => __( '5. BACS Settled', 'vpdl-management' ),
        );
        foreach ( $steps as $idx => $lbl ) :
            $is_done = $idx <= $step_completed;
            $color   = $is_done ? '#10b981' : 'var(--vpdl-text-muted)';
        ?>
            <div style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
                <div style="width: 24px; height: 24px; border-radius: 50%; background: <?php echo esc_attr( $color ); ?>; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800;">
                    <?php echo $idx < $step_completed ? '✓' : esc_html( (string) $idx ); ?>
                </div>
                <span style="font-size: 12.5px; font-weight: 700; color: <?php echo esc_attr( $color ); ?>;">
                    <?php echo esc_html( $lbl ); ?>
                </span>
            </div>
            <?php if ( $idx < 5 ) : ?>
                <div style="flex: 1; height: 2px; background: <?php echo $idx < $step_completed ? '#10b981' : 'var(--vpdl-border-color)'; ?>; min-width: 20px;"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- VIEW MODE: DETAILED QUOTATION DOSSIER -->
    <div class="vpdl-panel vpdl-schemes-card-pad">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(2, 132, 199, 0.1); color: var(--vpdl-accent); display: flex; align-items: center; justify-content: center;">
                    <?php echo vpdl_get_svg( 'schemes', '', 20 ); ?>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php echo esc_html( $single_quote->quote_number ); ?> • <span style="color: var(--vpdl-accent);"><?php echo esc_html( $single_quote->customer_name ); ?></span>
                    </h3>
                    <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;">
                        <?php printf( esc_html__( 'Issued on %1$s by %2$s', 'vpdl-management' ), esc_html( (string) $single_quote->created_at ), esc_html( (string) $single_quote->created_by ) ); ?>
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;" class="vpdl-no-print">
                <?php 
                $convert_nonce = wp_create_nonce( 'vpdl_quote_convert_nonce' );
                $convert_url   = admin_url( 'admin.php?page=vpdl&view=quotes&action=convert_invoice&quote_id=' . $single_quote->id . '&_wpnonce=' . $convert_nonce );
                $public_url    = home_url( '/?vpdl_view=quote&token=' . $single_quote->access_token ); 

                $clean_phone = preg_replace( '/[^0-9]/', '', (string) $single_quote->customer_phone );
                $quote_msg   = sprintf(
                    "Dear %1\$s,\nWe have prepared your official project quotation (%2\$s) for %3\$s %4\$s (Inc. VAT).\nView fee proposal online: %5\$s",
                    $single_quote->customer_name,
                    $single_quote->quote_number,
                    $currency,
                    number_format( (float) $single_quote->grand_total, 2 ),
                    $public_url
                );
                $wa_url      = 'https://wa.me/' . $clean_phone . '?text=' . rawurlencode( $quote_msg );
                ?>
                
                <?php if ( ! empty( $clean_phone ) ) : ?>
                    <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25);">
                        <span><?php esc_html_e( 'WhatsApp Quote', 'vpdl-management' ); ?></span>
                    </a>
                <?php endif; ?>

                <?php if ( 'Converted' !== $single_quote->status && empty( $single_quote->invoice_number ) ) : ?>
                    <a href="<?php echo esc_url( $convert_url ); ?>" data-action="confirm-convert" style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; background: var(--vpdl-accent); color: #ffffff;">
                        <span><?php esc_html_e( 'Convert to Final Invoice →', 'vpdl-management' ); ?></span>
                    </a>
                <?php else : ?>
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <?php printf( esc_html__( '✓ Invoiced (%s)', 'vpdl-management' ), esc_html( (string) $single_quote->invoice_number ) ); ?>
                    </span>
                <?php endif; ?>

                <button type="button" data-action="print-window" class="vpdl-schemes-action-btn vpdl-btn-edit">
                    <?php echo vpdl_get_svg( 'printer', '', 14 ); ?>
                    <span><?php esc_html_e( 'Print Proposal', 'vpdl-management' ); ?></span>
                </button>

                <a href="<?php echo esc_url( add_query_arg( 'tab', 'registry', $base_url ) ); ?>" class="vpdl-schemes-action-btn" style="background: var(--vpdl-border-color); color: var(--vpdl-text-main);">
                    <span>← <?php esc_html_e( 'Back', 'vpdl-management' ); ?></span>
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
            <div class="vpdl-panel" style="padding: 16px; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Client Contact', 'vpdl-management' ); ?></div>
                <div style="font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); margin-top: 6px; font-family: monospace;"><?php echo esc_html( $single_quote->customer_phone ); ?></div>
                <div style="font-size: 12px; color: var(--vpdl-text-muted); margin-top: 2px;"><?php echo esc_html( $single_quote->customer_email ?: __( 'No email on file', 'vpdl-management' ) ); ?></div>
            </div>

            <div class="vpdl-panel" style="padding: 16px; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Proposal Status', 'vpdl-management' ); ?></div>
                <div style="font-size: 16px; font-weight: 800; color: var(--vpdl-accent); margin-top: 6px;">
                    <?php echo esc_html( $single_quote->status ); ?>
                </div>
                <div style="font-size: 11.5px; color: var(--vpdl-text-muted); margin-top: 2px;">
                    <?php echo esc_html( (float) $single_quote->total_sqm > 0 ? $single_quote->total_sqm . ' sq.m floor area' : __( 'Fixed Estimate', 'vpdl-management' ) ); ?>
                </div>
            </div>

            <div class="vpdl-panel" style="padding: 16px; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Subtotal & VAT', 'vpdl-management' ); ?></div>
                <div style="font-size: 15px; font-weight: 800; color: var(--vpdl-text-main); margin-top: 6px; font-family: monospace;">
                    <?php echo esc_html( $currency . ' ' . number_format( (float) $single_quote->subtotal, 2 ) ); ?>
                </div>
                <div style="font-size: 11.5px; color: #f59e0b; margin-top: 2px; font-family: monospace;">
                    <?php printf( esc_html__( 'VAT: %1$s %2$s (%3$d%%)', 'vpdl-management' ), esc_html( $currency ), esc_html( number_format( (float) $single_quote->vat_amount, 2 ) ), absint( $vat_rate ) ); ?>
                </div>
            </div>

            <div class="vpdl-panel" style="padding: 16px; border-radius: 8px;">
                <div style="font-size: 11px; font-weight: 700; color: var(--vpdl-text-muted); text-transform: uppercase;"><?php esc_html_e( 'Grand Total (Inc. VAT)', 'vpdl-management' ); ?></div>
                <div style="font-size: 19px; font-weight: 800; color: #10b981; margin-top: 6px; font-family: monospace;">
                    <?php echo esc_html( $currency . ' ' . number_format( (float) $single_quote->grand_total, 2 ) ); ?>
                </div>
            </div>
        </div>

        <div class="vpdl-panel vpdl-schemes-card-pad-sm">
            <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 800; color: var(--vpdl-text-main);"><?php esc_html_e( 'Proposal Itemized Breakdowns', 'vpdl-management' ); ?></h4>
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="text-align: left; color: var(--vpdl-text-muted); border-bottom: 1.5px solid var(--vpdl-border-color);">
                        <th style="padding: 8px;"><?php esc_html_e( 'Description / Service Item', 'vpdl-management' ); ?></th>
                        <th style="padding: 8px; text-align: right; width: 150px;"><?php esc_html_e( 'Price (Excl. VAT)', 'vpdl-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $items = json_decode( (string) $single_quote->items_json, true );
                    if ( ! empty( $items ) && is_array( $items ) ) :
                        foreach ( $items as $it ) :
                            $item_desc  = $it['description'] ?? ( $it['desc'] ?? '' );
                            $item_price = $it['total'] ?? ( $it['unit_price'] ?? ( $it['price'] ?? 0.00 ) );
                    ?>
                        <tr style="border-bottom: 1px solid var(--vpdl-border-color);">
                            <td style="padding: 10px 8px; color: var(--vpdl-text-main); font-weight: 600;">
                                <?php echo esc_html( $item_desc ); ?>
                            </td>
                            <td style="padding: 10px 8px; text-align: right; font-family: monospace; font-weight: 700; color: #10b981;">
                                <?php echo esc_html( $currency . ' ' . number_format( (float) $item_price, 2 ) ); ?>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
                            <td colspan="2" style="text-align: center; color: var(--vpdl-text-muted); padding: 20px;"><?php esc_html_e( 'No item details available.', 'vpdl-management' ); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ( 'edit' === $action_mode && $single_quote ) : ?>
    <!-- EDIT MODE -->
    <div class="vpdl-panel vpdl-schemes-card-max">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 16px; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                <?php esc_html_e( 'Edit Quotation Proposal', 'vpdl-management' ); ?> (<?php echo esc_html( $single_quote->quote_number ); ?>)
            </h3>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'registry', $base_url ) ); ?>" style="color: var(--vpdl-text-muted); text-decoration: none; font-size: 13px; font-weight: 700;">
                × <?php esc_html_e( 'Cancel', 'vpdl-management' ); ?>
            </a>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
            <input type="hidden" name="vpdl_action" value="update_quotation">
            <input type="hidden" name="quote_id" value="<?php echo esc_attr( (string) $single_quote->id ); ?>">

            <div class="vpdl-schemes-form-group">
                <label for="vpdl_edit_quote_status"><?php esc_html_e( 'Proposal Status', 'vpdl-management' ); ?></label>
                <select name="status" id="vpdl_edit_quote_status" class="vpdl-schemes-select">
                    <option value="Draft" <?php selected( $single_quote->status, 'Draft' ); ?>><?php esc_html_e( 'Draft', 'vpdl-management' ); ?></option>
                    <option value="Sent" <?php selected( $single_quote->status, 'Sent' ); ?>><?php esc_html_e( 'Sent to Client', 'vpdl-management' ); ?></option>
                    <option value="Converted" <?php selected( $single_quote->status, 'Converted' ); ?>><?php esc_html_e( 'Converted (Won & Invoiced)', 'vpdl-management' ); ?></option>
                    <option value="Declined" <?php selected( $single_quote->status, 'Declined' ); ?>><?php esc_html_e( 'Declined', 'vpdl-management' ); ?></option>
                </select>
            </div>

            <div class="vpdl-schemes-form-group" style="margin-bottom: 24px;">
                <label for="vpdl_edit_quote_sqm"><?php esc_html_e( 'Total Measured Floor Area (sq.m)', 'vpdl-management' ); ?></label>
                <input type="number" step="0.01" min="0" id="vpdl_edit_quote_sqm" name="total_sqm" value="<?php echo esc_attr( (string) $single_quote->total_sqm ); ?>" class="vpdl-schemes-input" style="font-family: monospace;">
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="button button-primary" style="height: 42px; padding: 0 24px; font-weight: 700; border-radius: 8px; background: var(--vpdl-accent); border: none;">
                    <?php esc_html_e( 'Update Quotation', 'vpdl-management' ); ?>
                </button>
                <a href="<?php echo esc_url( add_query_arg( 'tab', 'registry', $base_url ) ); ?>" style="display: inline-flex; align-items: center; padding: 0 20px; font-size: 13px; font-weight: 700; color: var(--vpdl-text-muted); text-decoration: none;">
                    <?php esc_html_e( 'Discard', 'vpdl-management' ); ?>
                </a>
            </div>
        </form>
    </div>

<?php else : ?>
    <!-- TABS NAVIGATION -->
    <div class="vpdl-schemes-tab-nav vpdl-no-print" role="tablist">
        <button type="button" data-action="switch-tab" data-tab="builder" id="vpdlSchemeTabBtnBuilder" class="vpdl-schemes-tab-btn <?php echo ( 'builder' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'builder' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'schemes', '', 18 ); ?>
            <span><?php esc_html_e( 'Quotation Builder & Calculator', 'vpdl-management' ); ?></span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="registry" id="vpdlSchemeTabBtnRegistry" class="vpdl-schemes-tab-btn <?php echo ( 'registry' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'registry' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'invoice', '', 18 ); ?>
            <span><?php esc_html_e( 'Master Proposals Registry', 'vpdl-management' ); ?> (<?php echo esc_html( (string) count( $quotations ) ); ?>)</span>
        </button>

        <button type="button" data-action="switch-tab" data-tab="schemes_tariff" id="vpdlSchemeTabBtnTariff" class="vpdl-schemes-tab-btn <?php echo ( 'schemes_tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tab" aria-selected="<?php echo ( 'schemes_tariff' === $active_tab ) ? 'true' : 'false'; ?>">
            <?php echo vpdl_get_svg( 'settings', '', 18 ); ?>
            <span><?php esc_html_e( 'Schemes Tariff Manager', 'vpdl-management' ); ?></span>
        </button>
    </div>

    <!-- TAB 1: Quotation Builder & Calculator -->
    <div id="vpdlSchemeTabPaneBuilder" class="vpdl-schemes-tab-pane <?php echo ( 'builder' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-schemes-builder-grid">
            
            <!-- Left: Schemes Calculator & Quotation Form -->
            <div class="vpdl-panel vpdl-schemes-card-pad">
                <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Building Construction Works (Area-Based Pricing)', 'vpdl-management' ); ?>
                </h3>

                <form method="POST" action="<?php echo esc_url( $base_url ); ?>" id="vpdlQuoteForm">
                    <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                    <input type="hidden" name="vpdl_action" value="create_quotation">
                    <input type="hidden" name="subtotal" id="input_subtotal" value="0.00">
                    <div id="vpdlHiddenItemsContainer"></div>

                    <!-- Client & Area Input -->
                    <div class="vpdl-schemes-grid-2">
                        <div>
                            <label for="vpdl_target_customer" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Target Customer', 'vpdl-management' ); ?> *</label>
                            <select name="customer_id" id="vpdl_target_customer" required class="vpdl-schemes-select">
                                <option value=""><?php esc_html_e( '-- Select Target Customer --', 'vpdl-management' ); ?></option>
                                <?php foreach ( $customers as $c ) : ?>
                                    <option value="<?php echo esc_attr( (string) $c->id ); ?>" <?php selected( $pre_cust_id, $c->id ); ?>>
                                        <?php echo esc_html( $c->name . ' (' . $c->phone . ')' . ( $c->postcode ? ' - ' . $c->postcode : '' ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="vpdl_sqm" style="display: block; font-weight: 700; margin-bottom: 6px; font-size: 12.5px;"><?php esc_html_e( 'Floor Area (sq.m)', 'vpdl-management' ); ?> *</label>
                            <input type="number" step="0.01" min="0" id="vpdl_sqm" name="total_sqm" value="15.00" class="vpdl-schemes-input" style="font-weight: 700; font-size: 14.5px; font-family: monospace;">
                        </div>
                    </div>

                    <!-- Scheme Radio Options -->
                    <div class="vpdl-schemes-options-list">
                        <!-- Scheme 1 -->
                        <label class="vpdl-scheme-option-card">
                            <input type="radio" name="scheme_select" value="<?php echo esc_attr( (string) $scheme1_rate ); ?>" data-name="Building Construction Works - Scheme 1" checked style="margin-top: 3px;">
                            <div>
                                <strong class="vpdl-scheme-option-title"><?php esc_html_e( 'Building Construction Works - Scheme 1', 'vpdl-management' ); ?></strong>
                                <div class="vpdl-scheme-option-rate">
                                    <?php esc_html_e( 'Rate per Square Meter:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . number_format( $scheme1_rate, 2 ) ); ?>
                                </div>
                                <div class="vpdl-scheme-option-desc">
                                    <?php echo esc_html( $scheme1_desc ); ?>
                                </div>
                            </div>
                        </label>

                        <!-- Scheme 2 -->
                        <label class="vpdl-scheme-option-card">
                            <input type="radio" name="scheme_select" value="<?php echo esc_attr( (string) $scheme2_rate ); ?>" data-name="Building Construction Works - Scheme 2" style="margin-top: 3px;">
                            <div>
                                <strong class="vpdl-scheme-option-title"><?php esc_html_e( 'Building Construction Works - Scheme 2', 'vpdl-management' ); ?></strong>
                                <div class="vpdl-scheme-option-rate">
                                    <?php esc_html_e( 'Rate per Square Meter:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . number_format( $scheme2_rate, 2 ) ); ?>
                                </div>
                                <div class="vpdl-scheme-option-desc">
                                    <?php echo esc_html( $scheme2_desc ); ?>
                                </div>
                            </div>
                        </label>

                        <!-- Scheme 3 -->
                        <label class="vpdl-scheme-option-card">
                            <input type="radio" name="scheme_select" value="<?php echo esc_attr( (string) $scheme3_rate ); ?>" data-name="Building Construction Works - Scheme 3" style="margin-top: 3px;">
                            <div>
                                <strong class="vpdl-scheme-option-title"><?php esc_html_e( 'Building Construction Works - Scheme 3', 'vpdl-management' ); ?></strong>
                                <div class="vpdl-scheme-option-rate">
                                    <?php esc_html_e( 'Rate per Square Meter:', 'vpdl-management' ); ?> <?php echo esc_html( $currency . number_format( $scheme3_rate, 2 ) ); ?>
                                </div>
                                <div class="vpdl-scheme-option-desc">
                                    <?php echo esc_html( $scheme3_desc ); ?>
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Attach Professional Services -->
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                            <label style="font-weight: 800; font-size: 13px; color: var(--vpdl-text-main); display: flex; align-items: center; gap: 6px;">
                                <?php echo vpdl_get_svg( 'planning', '', 16 ); ?>
                                <span><?php esc_html_e( 'Attach Additional Professional Services (Optional)', 'vpdl-management' ); ?></span>
                            </label>
                            <input type="text" id="vpdlServiceSearch" placeholder="<?php esc_attr_e( 'Filter services...', 'vpdl-management' ); ?>" class="vpdl-schemes-input" style="font-size: 11.5px; padding: 4px 10px; width: 170px;">
                        </div>

                        <div style="max-height: 260px; overflow-y: auto; border: 1.5px solid var(--vpdl-border-color); border-radius: 10px; padding: 12px; background: rgba(0,0,0,0.01);">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;" id="vpdlServicesGrid">
                                <?php foreach ( $all_services as $srv ) : 
                                    $cat    = strtoupper( (string) $srv->category );
                                    $cat_bg = 'rgba(2, 132, 199, 0.1)';
                                    $cat_cl = 'var(--vpdl-accent)';
                                    if ( 'SDM' === $cat ) {
                                        $cat_bg = 'rgba(16, 185, 129, 0.1)';
                                        $cat_cl = '#10b981';
                                    } elseif ( 'PMC' === $cat ) {
                                        $cat_bg = 'rgba(168, 85, 247, 0.1)';
                                        $cat_cl = '#a855f7';
                                    } elseif ( 'BSM' === $cat ) {
                                        $cat_bg = 'rgba(245, 158, 11, 0.1)';
                                        $cat_cl = '#f59e0b';
                                    }
                                ?>
                                    <label class="vpdl-service-card">
                                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; margin-right: 8px;">
                                            <input type="checkbox" name="extra_services[]" value="<?php echo esc_attr( (string) $srv->fee ); ?>" data-code="<?php echo esc_attr( $srv->code ); ?>" data-desc="<?php echo esc_attr( $srv->description ); ?>" data-category="<?php echo esc_attr( $srv->category ); ?>" style="margin: 0; cursor: pointer;">
                                            <div style="overflow: hidden;">
                                                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                                    <span style="font-size: 9.5px; font-weight: 800; padding: 1px 6px; border-radius: 4px; background: <?php echo esc_attr( $cat_bg ); ?>; color: <?php echo esc_attr( $cat_cl ); ?>;">
                                                        <?php echo esc_html( $cat ); ?>
                                                    </span>
                                                    <strong style="font-size: 12px; font-family: monospace; color: var(--vpdl-text-main);"><?php echo esc_html( $srv->code ); ?></strong>
                                                </div>
                                                <div class="vpdl-service-title" title="<?php echo esc_attr( $srv->description ); ?>">
                                                    <?php echo esc_html( $srv->description ); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="font-family: monospace; font-weight: 800; font-size: 13px; color: #10b981; white-space: nowrap;">
                                            <?php echo esc_html( $currency . number_format( (float) $srv->fee, 0 ) ); ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="vpdl-schemes-btn-submit">
                        <?php esc_html_e( 'Generate & Save Official Quotation', 'vpdl-management' ); ?>
                    </button>
                </form>
            </div>

            <!-- Right: Live Financial Summary -->
            <div class="vpdl-schemes-summary-col">
                <div class="vpdl-panel vpdl-schemes-card-pad">
                    <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: var(--vpdl-text-main);">
                        <?php esc_html_e( 'Quotation Financial Summary', 'vpdl-management' ); ?>
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13.5px;">
                        <div class="vpdl-schemes-summary-line">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Scheme Construction Cost:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispSchemeCost">0.00</span></strong>
                        </div>
                        <div class="vpdl-schemes-summary-line">
                            <span style="color: var(--vpdl-text-muted);"><?php esc_html_e( 'Additional Services Fees:', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace;"><?php echo esc_html( $currency ); ?><span id="dispExtraCost">0.00</span></strong>
                        </div>
                        <div class="vpdl-schemes-summary-subtotal">
                            <span style="font-weight: 700;"><?php esc_html_e( 'Subtotal (Excl. VAT):', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; font-size: 15px;"><?php echo esc_html( $currency ); ?><span id="dispSubtotal">0.00</span></strong>
                        </div>
                        <div class="vpdl-schemes-summary-line">
                            <span style="color: var(--vpdl-text-muted);"><?php printf( esc_html__( 'Standard VAT (%d%%):', 'vpdl-management' ), absint( $vat_rate ) ); ?></span>
                            <strong style="font-family: monospace; color: #f59e0b;"><?php echo esc_html( $currency ); ?><span id="dispVat">0.00</span></strong>
                        </div>
                        <div class="vpdl-schemes-summary-grand">
                            <span style="font-weight: 800;"><?php esc_html_e( 'Grand Total (Inc. VAT):', 'vpdl-management' ); ?></span>
                            <strong style="font-family: monospace; font-weight: 800;"><?php echo esc_html( $currency ); ?><span id="dispGrandTotal">0.00</span></strong>
                        </div>
                    </div>
                </div>

                <div class="vpdl-panel vpdl-schemes-card-pad-sm" style="font-size: 12.5px; color: var(--vpdl-text-muted); line-height: 1.6;">
                    <div style="font-weight: 800; color: var(--vpdl-text-main); margin-bottom: 6px;"><?php esc_html_e( 'Statutory Quotation Notes:', 'vpdl-management' ); ?></div>
                    <?php esc_html_e( 'Quotations remain valid for 30 days from generation date. All architectural drawings, site inspections, and structural calculations adhere to UK Building Regulations and British Standards.', 'vpdl-management' ); ?>
                </div>
            </div>

        </div>
    </div>

    <!-- TAB 2: Master Proposals Registry -->
    <div id="vpdlSchemeTabPaneRegistry" class="vpdl-schemes-tab-pane <?php echo ( 'registry' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-panel vpdl-schemes-card-pad">
            <div style="margin-bottom: 20px;">
                <input type="text" id="vpdlSchemeSearch" placeholder="<?php esc_attr_e( 'Search proposals by quote number, customer, or phone...', 'vpdl-management' ); ?>" class="vpdl-schemes-input">
            </div>

            <div class="vpdl-schemes-table-wrap">
                <table class="vpdl-schemes-table" id="vpdlSchemesTable">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Quote Number', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Client / Lead', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Floor Area', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Grand Total', 'vpdl-management' ); ?></th>
                            <th><?php esc_html_e( 'Pipeline Stage', 'vpdl-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'vpdl-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $quotations ) ) : ?>
                            <?php foreach ( $quotations as $q ) : 
                                $is_converted  = ( 'Converted' === $q->status || ! empty( $q->invoice_number ) );
                                $convert_nonce = wp_create_nonce( 'vpdl_quote_convert_nonce' );
                                $convert_url   = admin_url( 'admin.php?page=vpdl&view=quotes&action=convert_invoice&quote_id=' . $q->id . '&_wpnonce=' . $convert_nonce );
                                
                                $clean_p  = preg_replace( '/[^0-9]/', '', (string) $q->customer_phone );
                                $quick_wa = 'https://wa.me/' . $clean_p . '?text=' . rawurlencode( sprintf( 'Hello %s, following up on your project proposal (%s).', $q->customer_name, $q->quote_number ) );
                            ?>
                                <tr class="vpdl-schemes-row">
                                    <td style="font-family: monospace; font-weight: 800; color: var(--vpdl-accent);">
                                        <?php echo esc_html( $q->quote_number ); ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--vpdl-text-main); font-size: 13.5px;"><?php echo esc_html( $q->customer_name ?: __( 'Prospective Lead', 'vpdl-management' ) ); ?></strong>
                                        <div style="font-size: 11px; color: var(--vpdl-text-muted); font-family: monospace; margin-top: 2px;"><?php echo esc_html( $q->customer_phone ); ?></div>
                                    </td>
                                    <td style="font-family: monospace;">
                                        <?php echo esc_html( (float) $q->total_sqm > 0 ? $q->total_sqm . ' sq.m' : '—' ); ?>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 800; color: #10b981; font-size: 14px;">
                                        <?php echo esc_html( $currency . ' ' . number_format( (float) $q->grand_total, 2 ) ); ?>
                                    </td>
                                    <td>
                                        <?php if ( 'Paid' === $q->payment_status ) : ?>
                                            <span class="vpdl-schemes-pill vpdl-schemes-pill-green">
                                                <?php esc_html_e( '5. Settled', 'vpdl-management' ); ?>
                                            </span>
                                        <?php elseif ( $is_converted ) : ?>
                                            <span class="vpdl-schemes-pill vpdl-schemes-pill-red">
                                                <?php esc_html_e( '4. Invoiced Due', 'vpdl-management' ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="vpdl-schemes-pill vpdl-schemes-pill-amber-bg">
                                                <?php esc_html_e( '2. Proposed', 'vpdl-management' ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <?php if ( ! empty( $clean_p ) ) : ?>
                                            <a href="<?php echo esc_url( $quick_wa ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'WhatsApp Follow-up', 'vpdl-management' ); ?>" style="padding: 6px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; background: rgba(16, 185, 129, 0.1); color: #10b981; margin-right: 4px; display: inline-block;">
                                                WA
                                            </a>
                                        <?php endif; ?>

                                        <?php if ( ! $is_converted ) : ?>
                                            <a href="<?php echo esc_url( $convert_url ); ?>" data-action="confirm-convert" style="padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); margin-right: 4px;">
                                                <?php esc_html_e( 'Convert →', 'vpdl-management' ); ?>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'view', 'quote_id' => $q->id ), $base_url ) ); ?>" class="vpdl-schemes-action-btn vpdl-btn-view">
                                            <?php esc_html_e( 'View', 'vpdl-management' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'mode' => 'edit', 'quote_id' => $q->id ), $base_url ) ); ?>" class="vpdl-schemes-action-btn vpdl-btn-edit">
                                            <?php esc_html_e( 'Edit', 'vpdl-management' ); ?>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" style="display:inline;" data-action="confirm-delete" data-type="<?php esc_attr_e( 'quotation', 'vpdl-management' ); ?>">
                                                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                                                <input type="hidden" name="vpdl_action" value="delete_quotation">
                                                <input type="hidden" name="quote_id" value="<?php echo esc_attr( (string) $q->id ); ?>">
                                                <button type="submit" class="vpdl-schemes-action-btn vpdl-btn-delete">
                                                    <?php esc_html_e( 'Delete', 'vpdl-management' ); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--vpdl-text-muted); padding: 50px 0;">
                                    <?php esc_html_e( 'No quotations generated yet.', 'vpdl-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: Schemes Tariff Manager -->
    <div id="vpdlSchemeTabPaneTariff" class="vpdl-schemes-tab-pane <?php echo ( 'schemes_tariff' === $active_tab ) ? 'is-active' : ''; ?>" role="tabpanel">
        <div class="vpdl-panel vpdl-schemes-card-tariff">
            <div style="border-bottom: 1px solid var(--vpdl-border-color); padding-bottom: 14px; margin-bottom: 22px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--vpdl-text-main);">
                    <?php esc_html_e( 'Manage Construction Schemes & Area-Based Rates', 'vpdl-management' ); ?>
                </h3>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--vpdl-text-muted);">
                    <?php esc_html_e( 'Set default square meter rates (£/sq.m) and detailed scope definitions for the 3 core build schemes.', 'vpdl-management' ); ?>
                </p>
            </div>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <?php wp_nonce_field( 'vpdl_secure_action', 'vpdl_action_nonce' ); ?>
                <input type="hidden" name="vpdl_action" value="update_scheme_tariffs">

                <!-- Scheme 1 -->
                <div class="vpdl-schemes-tariff-box">
                    <div class="vpdl-schemes-tariff-header">
                        <strong style="color: var(--vpdl-text-main); font-size: 14px;"><?php esc_html_e( 'Scheme 1: Basic Structural Shell', 'vpdl-management' ); ?></strong>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Rate (£/sq.m):', 'vpdl-management' ); ?></span>
                            <input type="number" step="1" min="100" name="scheme1_rate" value="<?php echo esc_attr( (string) $scheme1_rate ); ?>" required class="vpdl-schemes-input" style="width: 140px; font-family: monospace; font-weight: 800;">
                        </div>
                    </div>
                    <label for="vpdl_scheme1_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Scope Description:', 'vpdl-management' ); ?></label>
                    <textarea name="scheme1_desc" id="vpdl_scheme1_desc" rows="2" class="vpdl-schemes-textarea" style="font-size: 12.5px;"><?php echo esc_textarea( $scheme1_desc ); ?></textarea>
                </div>

                <!-- Scheme 2 -->
                <div class="vpdl-schemes-tariff-box">
                    <div class="vpdl-schemes-tariff-header">
                        <strong style="color: var(--vpdl-text-main); font-size: 14px;"><?php esc_html_e( 'Scheme 2: Serviced Shell & Building Regs Spec', 'vpdl-management' ); ?></strong>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Rate (£/sq.m):', 'vpdl-management' ); ?></span>
                            <input type="number" step="1" min="100" name="scheme2_rate" value="<?php echo esc_attr( (string) $scheme2_rate ); ?>" required class="vpdl-schemes-input" style="width: 140px; font-family: monospace; font-weight: 800;">
                        </div>
                    </div>
                    <label for="vpdl_scheme2_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Scope Description:', 'vpdl-management' ); ?></label>
                    <textarea name="scheme2_desc" id="vpdl_scheme2_desc" rows="2" class="vpdl-schemes-textarea" style="font-size: 12.5px;"><?php echo esc_textarea( $scheme2_desc ); ?></textarea>
                </div>

                <!-- Scheme 3 -->
                <div class="vpdl-schemes-tariff-box" style="margin-bottom: 24px;">
                    <div class="vpdl-schemes-tariff-header">
                        <strong style="color: var(--vpdl-text-main); font-size: 14px;"><?php esc_html_e( 'Scheme 3: Turnkey Full Luxury Finish', 'vpdl-management' ); ?></strong>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Rate (£/sq.m):', 'vpdl-management' ); ?></span>
                            <input type="number" step="1" min="100" name="scheme3_rate" value="<?php echo esc_attr( (string) $scheme3_rate ); ?>" required class="vpdl-schemes-input" style="width: 140px; font-family: monospace; font-weight: 800;">
                        </div>
                    </div>
                    <label for="vpdl_scheme3_desc" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: var(--vpdl-text-muted);"><?php esc_html_e( 'Scope Description:', 'vpdl-management' ); ?></label>
                    <textarea name="scheme3_desc" id="vpdl_scheme3_desc" rows="2" class="vpdl-schemes-textarea" style="font-size: 12.5px;"><?php echo esc_textarea( $scheme3_desc ); ?></textarea>
                </div>

                <?php if ( $is_admin ) : ?>
                    <button type="submit" class="button button-primary" style="height: 44px; padding: 0 32px; font-weight: 700; background: var(--vpdl-accent); border: none; border-radius: 8px; font-size: 14px;">
                        <?php esc_html_e( 'Save Scheme Tariff Rates', 'vpdl-management' ); ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

</div>