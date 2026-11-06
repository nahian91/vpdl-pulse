<?php
/**
 * Plugin Name: VPDL Project Management & CRM Operating System
 * Plugin URI:       https://devnahian.com/vpdl-management
 * Description: Enterprise Civil, Structural & Architectural CRM, Supplier PO Flow, Automated Payment Receipts, Consultant Reporting & Quotation Engine with Hardened Security.
 * Version:          1.0.0
 * Author:           Abdullah Nahian
 * Author URI:       https://vpdl.co.uk
 * License:          GPL-2.0-or-later
 * License URI:      https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:      vpdl-management
 * Domain Path:      /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package VPDLManagement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VPDL_VERSION', '1.0.0' );
define( 'VPDL_FILE', __FILE__ );
define( 'VPDL_PATH', plugin_dir_path( __FILE__ ) );
define( 'VPDL_URL', plugin_dir_url( __FILE__ ) );

/**
 * 1. Modular Subsystem Includes
 */
require_once VPDL_PATH . 'inc/database.php';

/**
 * 2. Activation Hooks & Network Provisioning
 */
register_activation_hook( VPDL_FILE, 'vpdl_network_activate' );
add_action( 'wp_initialize_site', 'vpdl_on_initialize_site', 10, 2 );

/**
 * Configure Roles & Capabilities once during activation.
 */
function vpdl_setup_roles_and_caps() {
	$admin_role = get_role( 'administrator' );
	$caps       = array(
		'vpdl_access_terminal',
		'vpdl_manage_customers',
		'vpdl_manage_suppliers',
		'vpdl_create_quotes',
		'vpdl_manage_po',
		'vpdl_manage_invoices',
		'vpdl_view_receipts',
		'vpdl_view_consultant_reports',
		'vpdl_view_finances',
		'vpdl_manage_team',
		'vpdl_manage_settings',
	);

	if ( $admin_role ) {
		foreach ( $caps as $cap ) {
			$admin_role->add_cap( $cap );
		}
	}

	$sales_caps = array(
		'read'                         => true,
		'vpdl_access_terminal'         => true,
		'vpdl_manage_customers'        => true,
		'vpdl_manage_suppliers'        => true,
		'vpdl_create_quotes'           => true,
		'vpdl_manage_po'               => true,
		'vpdl_manage_invoices'         => true,
		'vpdl_view_receipts'           => true,
		'vpdl_view_consultant_reports' => true,
	);

	$sales_role = get_role( 'vpdl_salesman' );
	if ( ! $sales_role ) {
		add_role( 'vpdl_salesman', __( 'VPDL Staff Specialist', 'vpdl-management' ), $sales_caps );
	} else {
		foreach ( $sales_caps as $cap => $grant ) {
			$sales_role->add_cap( $cap, $grant );
		}
	}
}

/**
 * 3. Textdomain Loading
 */
add_action( 'plugins_loaded', 'vpdl_load_textdomain' );
function vpdl_load_textdomain() {
	load_plugin_textdomain( 'vpdl-management', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Helper: Recursively sanitize line item structures from POST arrays
 *
 * @param array $items Raw submitted items.
 * @return array
 */
function vpdl_sanitize_items_payload( $items ) {
	if ( ! is_array( $items ) ) {
		return array();
	}
	$sanitized = array();
	foreach ( $items as $key => $item ) {
		if ( is_array( $item ) ) {
			$sanitized[ sanitize_key( $key ) ] = array(
				'description' => isset( $item['description'] ) ? sanitize_text_field( wp_unslash( $item['description'] ) ) : ( isset( $item['desc'] ) ? sanitize_text_field( wp_unslash( $item['desc'] ) ) : '' ),
				'category'    => isset( $item['category'] ) ? sanitize_text_field( wp_unslash( $item['category'] ) ) : '',
				'quantity'    => isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : ( isset( $item['qty'] ) ? floatval( $item['qty'] ) : 1 ),
				'unit_price'  => isset( $item['unit_price'] ) ? floatval( $item['unit_price'] ) : ( isset( $item['price'] ) ? floatval( $item['price'] ) : 0.00 ),
				'total'       => isset( $item['total'] ) ? floatval( $item['total'] ) : 0.00,
			);
		}
	}
	return $sanitized;
}

/**
 * 4. SVG Vector Engine
 */
function vpdl_get_svg( $icon_name, $class = '', $size = 20 ) {
	$icons = array(
		'dashboard'      => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
		'pipeline'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
		'customers'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'supplier'       => '<path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v1"/><rect x="16" y="8" width="6" height="8" rx="1"/><line x1="6" y1="12" x2="10" y2="12"/><line x1="8" y1="10" x2="8" y2="14"/>',
		'purchase_order' => '<path d="M6 2h12a2 2 0 0 1 2 2v16l-3-2-3 2-3-2-3 2-2-1.33V4a2 2 0 0 1 2-2z"/><line x1="9" y1="7" x2="15" y2="7"/><line x1="9" y1="11" x2="15" y2="11"/><line x1="9" y1="15" x2="12" y2="15"/>',
		'planning'       => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
		'structural'     => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
		'consulting'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="13" x2="13" y2="13"/>',
		'survey'         => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>',
		'schemes'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
		'invoice'        => '<path d="M14 2H6a2 2 0 0 0-2 2v16l3-1.5 3 1.5 3-1.5 3 1.5 3-1.5 3 1.5V4a2 2 0 0 0-2-2z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/>',
		'receipt'        => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"/><circle cx="12" cy="11" r="3"/><path d="m14 13-1.5-1.5"/>',
		'accounts'       => '<rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.5"/>',
		'team'           => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'reports'        => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/>',
		'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
		'check'          => '<polyline points="20 6 9 17 4 12"/>',
		'logout'         => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
		'sun'            => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
		'moon'           => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
		'printer'        => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
		'download'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
		'close'          => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
		'plus'           => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
		'edit'           => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
		'trash'          => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
		'eye'            => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
		'copy'           => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
		'arrow_right'    => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
		'arrow_left'     => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
		'file_text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
		'whatsapp'       => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
	);

	$stroke_body = $icons[ $icon_name ] ?? '<circle cx="12" cy="12" r="10"/>';
	$class_attr  = ! empty( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';

	$svg_raw = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%2$s style="display:inline-block; vertical-align:middle; flex-shrink:0;">%3$s</svg>',
		absint( $size ),
		$class_attr,
		$stroke_body
	);

	$allowed = array(
		'svg'      => array(
			'xmlns'           => true,
			'viewbox'         => true,
			'width'           => true,
			'height'          => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'style'           => true,
		),
		'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true ),
		'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
		'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
		'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ),
		'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true ),
		'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
	);

	return wp_kses( $svg_raw, $allowed );
}

/**
 * 5. Menu Registration
 */
add_action( 'admin_menu', 'vpdl_register_menu' );
function vpdl_register_menu() {
	$menu_svg = 'data:image/svg+xml;base64,' . base64_encode(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#a7aaad" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>'
	);

	add_menu_page(
		__( 'VPDL System', 'vpdl-management' ),
		__( 'VPDL System', 'vpdl-management' ),
		'vpdl_access_terminal',
		'vpdl',
		'vpdl_render_application',
		$menu_svg,
		2
	);
}

/**
 * 6. Scoped Asset Loading
 */
add_action( 'admin_enqueue_scripts', 'vpdl_enqueue_assets' );
function vpdl_enqueue_assets( $hook ) {
    if ( 'toplevel_page_vpdl' !== $hook ) {
        return;
    }

    wp_enqueue_media();

    wp_register_script( 'vpdl-core-runtime', false, array( 'jquery' ), VPDL_VERSION, true );
    wp_enqueue_script( 'vpdl-core-runtime' );

    wp_localize_script(
        'vpdl-core-runtime',
        'vpdlConfig',
        array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'security_token' => wp_create_nonce( 'vpdl_security_token' ),
            'currency'       => (string) get_option( 'vpdl_currency', '£' ),
            'vat_rate'       => (float) get_option( 'vpdl_vat_rate', 20.00 ),
            'current_user'   => wp_get_current_user()->display_name,
        )
    );

    // Determine the active view from the URL parameter (defaults to dashboard)
    $current_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';

    // Map view slugs to their corresponding asset handles and filenames
    $views_map = array(
        'dashboard'          => array( 'css' => 'dashboard', 'js' => 'dashboard' ),
        'pipeline'           => array( 'css' => 'pipeline', 'js' => 'pipeline' ),
        'customers'          => array( 'css' => 'customers', 'js' => 'customers' ),
        'suppliers'          => array( 'css' => 'suppliers', 'js' => 'suppliers' ),
        'accounts'           => array( 'css' => 'accounts', 'js' => 'accounts' ),
        'bsm'                => array( 'css' => 'bsm', 'js' => 'bsm' ),
        'consultant-reports' => array( 'css' => 'consultant-reports', 'js' => 'consultant-reports' ),
        'invoices'           => array( 'css' => 'invoices', 'js' => 'invoices' ),
        'pdm'                => array( 'css' => 'pdm', 'js' => 'pdm' ),
        'pmc'                => array( 'css' => 'pmc', 'js' => 'pmc' ),
        'purchase-orders'    => array( 'css' => 'purchase-orders', 'js' => 'purchase-orders' ),
        'quotes'             => array( 'css' => 'quotes', 'js' => 'quotes' ),
        'receipts'           => array( 'css' => 'receipts', 'js' => 'receipts' ),
        'reports'            => array( 'css' => 'reports', 'js' => 'reports' ),
        'schemes'            => array( 'css' => 'schemes', 'js' => 'schemes' ),
        'sdm'                => array( 'css' => 'sdm', 'js' => 'sdm' ),
        'services'           => array( 'css' => 'services', 'js' => 'services' ),
        'settings'           => array( 'css' => 'settings', 'js' => 'settings' ),
        'team'               => array( 'css' => 'team', 'js' => 'team' ),
    );

    // Default fallback to dashboard if view isn't found in map
    if ( ! array_key_exists( $current_view, $views_map ) ) {
        $current_view = 'dashboard';
    }

    $asset = $views_map[ $current_view ];
    $version = defined( 'VPDL_VERSION' ) ? VPDL_VERSION : '1.0.0';

    // Enqueue the scoped CSS file
    wp_enqueue_style(
        'vpdl-' . $asset['css'] . '-styles',
        plugins_url( 'assets/css/' . $asset['css'] . '.css', __FILE__ ),
        array(),
        $version
    );

    // Enqueue the scoped JS file
    wp_enqueue_script(
        'vpdl-' . $asset['js'] . '-scripts',
        plugins_url( 'assets/js/' . $asset['js'] . '.js', __FILE__ ),
        array( 'vpdl-core-runtime' ),
        $version,
        true
    );
}

/**
 * 7. Live Dashboard Metric Counts
 */
function vpdl_get_dashboard_counts() {
	global $wpdb;

	$t_cust     = $wpdb->prefix . 'vpdl_customers';
	$t_supp     = $wpdb->prefix . 'vpdl_suppliers';
	$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
	$t_po       = $wpdb->prefix . 'vpdl_purchase_orders';
	$t_inv      = $wpdb->prefix . 'vpdl_invoices';
	$t_receipts = $wpdb->prefix . 'vpdl_receipts';
	$t_inq      = $wpdb->prefix . 'vpdl_inquiries';

	$counts = array(
		'contacts'        => array( 'total' => 0, 'alert' => 0 ),
		'customer'        => array( 'total' => 0, 'alert' => 0 ),
		'supplier'        => array( 'total' => 0, 'alert' => 0 ),
		'quote'           => array( 'total' => 0, 'alert' => 0 ),
		'sales_order'     => array( 'total' => 0, 'alert' => 0 ),
		'works_order'     => array( 'total' => 0, 'alert' => 0 ),
		'purchase_order'  => array( 'total' => 0, 'alert' => 0 ),
		'inventory'       => array( 'total' => 0, 'alert' => 0 ),
		'delivery'        => array( 'total' => 0, 'alert' => 0 ),
		'invoice'         => array( 'total' => 0, 'alert' => 0 ),
		'receipts'        => array( 'total' => 0, 'alert' => 0 ),
		'diary'           => array( 'total' => 0, 'alert' => 0 ),
		'quality'         => array( 'total' => 0, 'alert' => 0 ),
		'scheduling'      => array( 'total' => 0, 'alert' => 0 ),
		'personnel'       => array( 'total' => 0, 'alert' => 0 ),
		'consultant_jobs' => array( 'total' => 0, 'alert' => 0 ),
	);

	$counts['customer']['total']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_cust}" );
	$counts['supplier']['total']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_supp}" );
	$counts['supplier']['alert']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_supp} WHERE status != 'Active'" );
	$counts['contacts']['total']          = $counts['customer']['total'] + $counts['supplier']['total'];
	$counts['quote']['total']             = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_quotes}" );
	$counts['quote']['alert']             = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_quotes} WHERE status IN ('Draft', 'Sent')" );
	$counts['purchase_order']['total']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_po}" );
	$counts['purchase_order']['alert']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_po} WHERE status IN ('Pending', 'Overdue')" );
	$counts['invoice']['total']         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inv}" );
	$counts['invoice']['alert']         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inv} WHERE payment_status = 'Unpaid'" );
	$counts['receipts']['total']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_receipts}" );
	$counts['receipts']['alert']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_receipts} WHERE paid_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)" );
	$counts['scheduling']['total']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inq}" );
	$counts['scheduling']['alert']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inq} WHERE status = 'New'" );
	$counts['personnel']['total']       = (int) count( get_users( array( 'role__in' => array( 'administrator', 'vpdl_salesman' ), 'fields' => 'ID' ) ) );
	$counts['consultant_jobs']['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inq} WHERE assigned_consultant IS NOT NULL AND assigned_consultant > 0" );
	$counts['consultant_jobs']['alert'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t_inq} WHERE assigned_consultant IS NOT NULL AND status IN ('New', 'In-Review')" );

	return $counts;
}

/**
 * 8. Hardened Form Submissions Router
 */
add_action( 'admin_init', 'vpdl_handle_form_submissions' );
function vpdl_handle_form_submissions() {
	if ( ! isset( $_POST['vpdl_action'] ) ) {
		return;
	}

	check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

	global $wpdb;
	$t_cust     = $wpdb->prefix . 'vpdl_customers';
	$t_supp     = $wpdb->prefix . 'vpdl_suppliers';
	$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
	$t_po       = $wpdb->prefix . 'vpdl_purchase_orders';
	$t_inv      = $wpdb->prefix . 'vpdl_invoices';
	$t_receipts = $wpdb->prefix . 'vpdl_receipts';
	$t_accounts = $wpdb->prefix . 'vpdl_accounts';
	$t_inq      = $wpdb->prefix . 'vpdl_inquiries';

	$action   = sanitize_key( wp_unslash( $_POST['vpdl_action'] ) );
	$base_url = admin_url( 'admin.php?page=vpdl' );

	// Save Customer
	if ( 'save_customer' === $action ) {
		if ( ! current_user_can( 'vpdl_manage_customers' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$site         = sanitize_textarea_field( wp_unslash( $_POST['site_address'] ?? '' ) );
		$postcode     = strtoupper( sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) ) );
		$council_name = sanitize_text_field( wp_unslash( $_POST['council_name'] ?? '' ) );
		$client_type  = sanitize_text_field( wp_unslash( $_POST['client_type'] ?? 'Homeowner' ) );
		$lead_source  = sanitize_text_field( wp_unslash( $_POST['lead_source'] ?? 'Direct' ) );
		$assigned_to  = absint( $_POST['assigned_to'] ?? get_current_user_id() );
		$cust_id      = absint( $_POST['customer_id'] ?? 0 );

		if ( empty( $name ) || empty( $phone ) ) {
			wp_safe_redirect( add_query_arg( array( 'view' => 'customers', 'msg' => 'err_missing_data' ), $base_url ) );
			exit;
		}

		$data = array(
			'name'         => $name,
			'phone'        => $phone,
			'email'        => $email,
			'site_address' => $site,
			'postcode'     => $postcode,
			'council_name' => $council_name,
			'client_type'  => $client_type,
			'lead_source'  => $lead_source,
			'assigned_to'  => $assigned_to,
		);
		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $cust_id > 0 ) {
			$wpdb->update( $t_cust, $data, array( 'id' => $cust_id ), $format, array( '%d' ) );
			wp_safe_redirect( add_query_arg( array( 'view' => 'customers', 'msg' => 'customer_updated' ), $base_url ) );
			exit;
		}

		$wpdb->insert( $t_cust, $data, $format );
		$new_cust_id = $wpdb->insert_id;

		if ( ! empty( $_POST['service_category'] ) ) {
			$category      = sanitize_text_field( wp_unslash( $_POST['service_category'] ) );
			$consultant_id = absint( $_POST['assigned_consultant'] ?? 0 );
			$consult_fee   = floatval( $_POST['consultant_fee'] ?? 0.00 );
			$notes         = sanitize_textarea_field( wp_unslash( $_POST['initial_notes'] ?? '' ) );

			$wpdb->insert(
				$t_inq,
				array(
					'customer_id'         => $new_cust_id,
					'service_category'    => $category,
					'assigned_consultant' => $consultant_id > 0 ? $consultant_id : null,
					'consultant_fee'      => $consult_fee,
					'notes'               => $notes,
					'status'              => 'New',
					'created_by'          => wp_get_current_user()->display_name,
				),
				array( '%d', '%s', '%d', '%f', '%s', '%s', '%s' )
			);
		}

		wp_safe_redirect( add_query_arg( array( 'view' => 'customers', 'msg' => 'customer_created' ), $base_url ) );
		exit;
	}

	// Save Supplier
	if ( 'save_supplier' === $action ) {
		if ( ! current_user_can( 'vpdl_manage_suppliers' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$company = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
		$contact = sanitize_text_field( wp_unslash( $_POST['contact_person'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$cat     = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'Materials' ) );
		$address = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );
		$vat_no  = sanitize_text_field( wp_unslash( $_POST['vat_number'] ?? '' ) );
		$supp_id = absint( $_POST['supplier_id'] ?? 0 );

		if ( empty( $company ) || empty( $phone ) ) {
			wp_safe_redirect( add_query_arg( array( 'view' => 'suppliers', 'msg' => 'err_missing_data' ), $base_url ) );
			exit;
		}

		$data = array(
			'company_name'   => $company,
			'contact_person' => $contact,
			'phone'          => $phone,
			'email'          => $email,
			'category'       => $cat,
			'address'        => $address,
			'vat_number'     => $vat_no,
			'status'         => 'Active',
		);
		$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $supp_id > 0 ) {
			$wpdb->update( $t_supp, $data, array( 'id' => $supp_id ), $format, array( '%d' ) );
			wp_safe_redirect( add_query_arg( array( 'view' => 'suppliers', 'msg' => 'supplier_updated' ), $base_url ) );
			exit;
		}

		$wpdb->insert( $t_supp, $data, $format );
		wp_safe_redirect( add_query_arg( array( 'view' => 'suppliers', 'msg' => 'supplier_created' ), $base_url ) );
		exit;
	}

	// Create Purchase Order
	if ( 'create_purchase_order' === $action ) {
		if ( ! current_user_can( 'vpdl_manage_po' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$supplier_id = absint( $_POST['supplier_id'] ?? 0 );
		$project     = sanitize_text_field( wp_unslash( $_POST['related_project'] ?? '' ) );
		$subtotal    = floatval( $_POST['subtotal'] ?? 0.00 );
		$vat_rate    = floatval( get_option( 'vpdl_vat_rate', 20.00 ) );
		$vat_amount  = ( $subtotal * $vat_rate ) / 100;
		$grand_total = $subtotal + $vat_amount;
		$status      = sanitize_key( $_POST['status'] ?? 'Pending' );
		$delivery    = sanitize_text_field( wp_unslash( $_POST['expected_delivery'] ?? current_time( 'Y-m-d' ) ) );
		$notes       = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

		$items_raw  = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items_json = wp_json_encode( vpdl_sanitize_items_payload( $items_raw ) );
		$po_num     = 'VPDL-PO-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );

		$wpdb->insert(
			$t_po,
			array(
				'po_number'         => $po_num,
				'supplier_id'       => $supplier_id,
				'related_project'   => $project,
				'items_json'        => $items_json,
				'subtotal'          => $subtotal,
				'vat_amount'        => $vat_amount,
				'grand_total'       => $grand_total,
				'status'            => $status,
				'expected_delivery' => $delivery,
				'notes'             => $notes,
				'created_by'        => wp_get_current_user()->display_name,
			),
			array( '%s', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'view' => 'purchase-orders', 'msg' => 'po_created' ), $base_url ) );
		exit;
	}

	// Create Quotation
	if ( 'create_quotation' === $action ) {
		if ( ! current_user_can( 'vpdl_create_quotes' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$subtotal    = floatval( $_POST['subtotal'] ?? 0.00 );
		$total_sqm   = floatval( $_POST['total_sqm'] ?? 0.00 );
		$vat_rate    = floatval( get_option( 'vpdl_vat_rate', 20.00 ) );
		$vat_amount  = ( $subtotal * $vat_rate ) / 100;
		$grand_total = $subtotal + $vat_amount;

		$items_raw  = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items_json = wp_json_encode( vpdl_sanitize_items_payload( $items_raw ) );
		$quote_num  = 'VPDL-Q-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
		$token      = wp_generate_password( 32, false, false );

		$wpdb->insert(
			$t_quotes,
			array(
				'quote_number' => $quote_num,
				'customer_id'  => $customer_id,
				'items_json'   => $items_json,
				'total_sqm'    => $total_sqm,
				'subtotal'     => $subtotal,
				'vat_amount'   => $vat_amount,
				'grand_total'  => $grand_total,
				'status'       => 'Sent',
				'access_token' => $token,
				'created_by'   => wp_get_current_user()->display_name,
			),
			array( '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'view' => 'quotes', 'msg' => 'quote_created' ), $base_url ) );
		exit;
	}

	// Create Invoice
	if ( 'create_invoice' === $action ) {
		if ( ! current_user_can( 'vpdl_manage_invoices' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$quote_id    = ! empty( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : null;
		$subtotal    = floatval( $_POST['subtotal'] ?? 0.00 );
		$vat_rate    = floatval( get_option( 'vpdl_vat_rate', 20.00 ) );
		$vat_amount  = ( $subtotal * $vat_rate ) / 100;
		$grand_total = $subtotal + $vat_amount;
		$status      = sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? 'Unpaid' ) );

		$items_raw  = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items_json = wp_json_encode( vpdl_sanitize_items_payload( $items_raw ) );
		$inv_num    = 'VPDL-INV-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
		$token      = wp_generate_password( 32, false, false );

		$wpdb->insert(
			$t_inv,
			array(
				'invoice_number' => $inv_num,
				'quote_id'       => $quote_id,
				'customer_id'    => $customer_id,
				'items_json'     => $items_json,
				'subtotal'       => $subtotal,
				'vat_amount'     => $vat_amount,
				'grand_total'    => $grand_total,
				'payment_status' => $status,
				'access_token'   => $token,
				'created_by'     => wp_get_current_user()->display_name,
			),
			array( '%s', '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);
		$new_inv_id = $wpdb->insert_id;

		if ( 'Paid' === $status ) {
			$rec_num   = 'VPDL-REC-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
			$rec_token = wp_generate_password( 32, false, false );

			$wpdb->insert(
				$t_receipts,
				array(
					'receipt_number'    => $rec_num,
					'invoice_id'        => $new_inv_id,
					'customer_id'       => $customer_id,
					'amount_paid'       => $grand_total,
					'payment_method'    => 'Bank Transfer',
					'payment_reference' => 'Direct Settlement ' . $inv_num,
					'paid_date'         => current_time( 'Y-m-d' ),
					'access_token'      => $rec_token,
				),
				array( '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s' )
			);

			$wpdb->insert(
				$t_accounts,
				array(
					'title'         => 'Payment for Invoice ' . $inv_num,
					'type'          => 'Income',
					'amount'        => $grand_total,
					'vat_collected' => $vat_amount,
					'reference_id'  => $inv_num,
					'entry_date'    => current_time( 'Y-m-d' ),
				),
				array( '%s', '%s', '%f', '%f', '%s', '%s' )
			);
		}

		wp_safe_redirect( add_query_arg( array( 'view' => 'invoices', 'msg' => 'invoice_created' ), $base_url ) );
		exit;
	}

	// Save Accounts Entry
	if ( 'save_account_entry' === $action ) {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		$type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'Income' ) );
		$amount     = floatval( $_POST['amount'] ?? 0.00 );
		$vat        = floatval( $_POST['vat_collected'] ?? 0.00 );
		$ref        = sanitize_text_field( wp_unslash( $_POST['reference_id'] ?? '' ) );
		$entry_date = sanitize_text_field( wp_unslash( $_POST['entry_date'] ?? current_time( 'Y-m-d' ) ) );

		if ( empty( $title ) || $amount <= 0 ) {
			wp_safe_redirect( add_query_arg( array( 'view' => 'accounts', 'msg' => 'err_missing_data' ), $base_url ) );
			exit;
		}

		$wpdb->insert(
			$t_accounts,
			array(
				'title'         => $title,
				'type'          => $type,
				'amount'        => $amount,
				'vat_collected' => $vat,
				'reference_id'  => $ref,
				'entry_date'    => $entry_date,
			),
			array( '%s', '%s', '%f', '%f', '%s', '%s' )
		);

		wp_safe_redirect( add_query_arg( array( 'view' => 'accounts', 'msg' => 'entry_saved' ), $base_url ) );
		exit;
	}

	// Save Settings
	if ( 'save_settings' === $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
		}

		$fields = array(
			'vpdl_business_name'     => 'sanitize_text_field',
			'vpdl_company_tag'       => 'sanitize_text_field',
			'vpdl_currency'          => 'sanitize_text_field',
			'vpdl_vat_rate'          => 'floatval',
			'vpdl_vat_number'        => 'sanitize_text_field',
			'vpdl_quote_validity'    => 'absint',
			'vpdl_phone'             => 'sanitize_text_field',
			'vpdl_email'             => 'sanitize_email',
			'vpdl_address'           => 'sanitize_textarea_field',
			'vpdl_reg_no'            => 'sanitize_text_field',
			'vpdl_logo_url'          => 'esc_url_raw',
			'vpdl_bank_name'         => 'sanitize_text_field',
			'vpdl_bank_account_name' => 'sanitize_text_field',
			'vpdl_bank_account_no'   => 'sanitize_text_field',
			'vpdl_bank_sort_code'    => 'sanitize_text_field',
		);

		foreach ( $fields as $opt_key => $clean_func ) {
			if ( isset( $_POST[ $opt_key ] ) ) {
				$raw = wp_unslash( $_POST[ $opt_key ] );
				update_option( $opt_key, call_user_func( $clean_func, $raw ) );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'view' => 'settings', 'msg' => 'settings_saved' ), $base_url ) );
		exit;
	}
}

/**
 * 9. Hardened Invoice Status Update (Secure Capability + Nonce)
 */
add_action( 'admin_init', 'vpdl_handle_invoice_status_update' );
function vpdl_handle_invoice_status_update() {
	if ( ! isset( $_POST['vpdl_action'] ) || 'update_payment_status' !== $_POST['vpdl_action'] ) {
		return;
	}

	check_admin_referer( 'vpdl_secure_action', 'vpdl_action_nonce' );

	if ( ! current_user_can( 'vpdl_manage_invoices' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Forbidden: Insufficient privileges to update invoices.', 'vpdl-management' ), 403 );
	}

	global $wpdb;
	$t_inv      = $wpdb->prefix . 'vpdl_invoices';
	$t_receipts = $wpdb->prefix . 'vpdl_receipts';
	$t_accounts = $wpdb->prefix . 'vpdl_accounts';

	$invoice_id     = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
	$payment_status = isset( $_POST['payment_status'] ) ? sanitize_key( wp_unslash( $_POST['payment_status'] ) ) : 'Unpaid';

	if ( $invoice_id > 0 ) {
		$wpdb->update(
			$t_inv,
			array( 'payment_status' => $payment_status ),
			array( 'id' => $invoice_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( 'Paid' === $payment_status ) {
			$inv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t_inv} WHERE id = %d", $invoice_id ) );
			if ( $inv ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t_receipts} WHERE invoice_id = %d", $invoice_id ) );
				if ( ! $exists ) {
					$rec_num   = 'VPDL-REC-' . strtoupper( current_time( 'Ym' ) ) . '-' . wp_rand( 1000, 9999 );
					$rec_token = wp_generate_password( 32, false, false );

					$wpdb->insert(
						$t_receipts,
						array(
							'receipt_number'    => $rec_num,
							'invoice_id'        => $invoice_id,
							'customer_id'       => $inv->customer_id,
							'amount_paid'       => $inv->grand_total,
							'payment_method'    => 'Bank Settlement',
							'payment_reference' => 'Invoice ' . $inv->invoice_number,
							'paid_date'         => current_time( 'Y-m-d' ),
							'access_token'      => $rec_token,
						),
						array( '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s' )
					);

					$wpdb->insert(
						$t_accounts,
						array(
							'title'         => 'Settlement for Invoice ' . $inv->invoice_number,
							'type'          => 'Income',
							'amount'        => $inv->grand_total,
							'vat_collected' => $inv->vat_amount,
							'reference_id'  => $inv->invoice_number,
							'entry_date'    => current_time( 'Y-m-d' ),
						),
						array( '%s', '%s', '%f', '%f', '%s', '%s' )
					);
				}
			}
		}
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'vpdl', 'view' => 'invoices', 'msg' => 'status_updated', 'tab' => 'list' ), admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * 10. Ledger CSV Export
 */
add_action( 'admin_init', 'vpdl_handle_ledger_csv_export' );
function vpdl_handle_ledger_csv_export() {
	if ( ! isset( $_GET['view'], $_GET['export_ledger_csv'] ) || ( 'accounts' !== $_GET['view'] && 'receipts' !== $_GET['view'] ) ) {
		return;
	}

	check_admin_referer( 'vpdl_export_ledger' );

	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'vpdl_view_finances' ) ) {
		wp_die( esc_html__( 'Forbidden.', 'vpdl-management' ), 403 );
	}

	global $wpdb;
	$t_accounts = $wpdb->prefix . 'vpdl_accounts';
	$entries    = $wpdb->get_results( "SELECT * FROM {$t_accounts} ORDER BY entry_date DESC" );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=VPDL-Fiscal-Ledger-' . sanitize_file_name( current_time( 'Y-m-d' ) ) . '.csv' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'Transaction ID', 'Date', 'Type', 'Title / Payee', 'Amount (Ex. VAT)', 'Claimable VAT', 'Gross Amount', 'Reference' ) );

	foreach ( $entries as $row ) {
		$gross = (float) $row->amount + (float) $row->vat_collected;
		fputcsv(
			$output,
			array(
				$row->id,
				$row->entry_date,
				$row->type,
				$row->title,
				number_format( (float) $row->amount, 2, '.', '' ),
				number_format( (float) $row->vat_collected, 2, '.', '' ),
				number_format( $gross, 2, '.', '' ),
				$row->reference_id,
			)
		);
	}
	fclose( $output );
	exit;
}

/**
 * 11. Application Layout Shell & Whitelist Routing (Supports Subfolders)
 */
function vpdl_render_application() {
	if ( ! current_user_can( 'vpdl_access_terminal' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'vpdl-management' ), 403 );
	}

	$is_admin = current_user_can( 'manage_options' );

	$valid_views = array(
		'dashboard',
		'pipeline',
		'customers',
		'suppliers',
		'purchase-orders',
		'quotes',
		'pdm',
		'sdm',
		'pmc',
		'bsm',
		'schemes',
		'invoices',
		'receipts',
		'consultant-reports',
		'services',
		'accounts',
		'team',
		'reports',
		'settings',
	);

	$admin_only_views = array( 'services', 'accounts', 'team', 'reports', 'settings' );

	$current_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';

	if ( ! in_array( $current_view, $valid_views, true ) ) {
		$current_view = 'dashboard';
	}

	if ( ! $is_admin && in_array( $current_view, $admin_only_views, true ) ) {
		$current_view = 'dashboard';
	}

	$base_url = admin_url( 'admin.php?page=vpdl' );
	$msg_code = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';

	$flash_map = array(
		'customer_created' => array( 'msg' => __( 'Customer profile and inquiry created successfully.', 'vpdl-management' ), 'type' => 'success' ),
		'customer_updated' => array( 'msg' => __( 'Customer record updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
		'supplier_created' => array( 'msg' => __( 'Supplier registered into the directory.', 'vpdl-management' ), 'type' => 'success' ),
		'supplier_updated' => array( 'msg' => __( 'Supplier details updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
		'po_created'       => array( 'msg' => __( 'Purchase Order generated and logged into live ledger.', 'vpdl-management' ), 'type' => 'success' ),
		'quote_created'    => array( 'msg' => __( 'Quotation generated and download link prepared.', 'vpdl-management' ), 'type' => 'success' ),
		'invoice_created'  => array( 'msg' => __( 'Invoice generated and assigned access token.', 'vpdl-management' ), 'type' => 'success' ),
		'receipt_created'  => array( 'msg' => __( 'Invoice marked as Paid. Official Payment Receipt generated.', 'vpdl-management' ), 'type' => 'success' ),
		'status_updated'   => array( 'msg' => __( 'Invoice payment status updated successfully.', 'vpdl-management' ), 'type' => 'success' ),
		'entry_saved'      => array( 'msg' => __( 'Financial transaction logged successfully.', 'vpdl-management' ), 'type' => 'success' ),
		'settings_saved'   => array( 'msg' => __( 'VPDL Configuration Updated.', 'vpdl-management' ), 'type' => 'success' ),
		'err_forbidden'    => array( 'msg' => __( 'Security Error: Action not permitted.', 'vpdl-management' ), 'type' => 'error' ),
		'err_missing_data' => array( 'msg' => __( 'Required fields missing. Please review and try again.', 'vpdl-management' ), 'type' => 'error' ),
	);

	$b_name = (string) get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
	?>
	<div class="vpdl-shell" id="vpdlAppShell" data-theme="light">
		<style>
			:root, [data-theme="light"] {
				--vpdl-sidebar-bg: #ffffff;
				--vpdl-sidebar-border: #e2e8f0;
				--vpdl-sidebar-brand-text: #0f172a;
				--vpdl-sidebar-text: #475569;
				--vpdl-sidebar-text-hover: #0284c7;
				--vpdl-sidebar-text-muted: #94a3b8;
				--vpdl-sidebar-hover-bg: #f8fafc;
				--vpdl-sidebar-active-bg: #f0f9ff;
				--vpdl-sidebar-active-text: #0284c7;
				--vpdl-sidebar-user-card: #f8fafc;
				--vpdl-sidebar-user-name: #0f172a;
				--vpdl-theme-btn-bg: #f1f5f9;
				--vpdl-theme-btn-active: #ffffff;
				--vpdl-theme-btn-shadow: 0 1px 3px rgba(0,0,0,0.1);
				--vpdl-canvas-bg: #f8fafc;
				--vpdl-topbar-bg: #ffffff;
				--vpdl-card-bg: #ffffff;
				--vpdl-border-color: #e2e8f0;
				--vpdl-text-main: #0f172a;
				--vpdl-text-muted: #64748b;
				--vpdl-accent: #0284c7;
				--vpdl-accent-hover: #0369a1;
				--vpdl-input-bg: #ffffff;
				--vpdl-input-border: #cbd5e1;
				--vpdl-shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
			}

			[data-theme="dark"] {
				--vpdl-sidebar-bg: #0b0f19;
				--vpdl-sidebar-border: #1f2937;
				--vpdl-sidebar-brand-text: #f9fafb;
				--vpdl-sidebar-text: #9ca3af;
				--vpdl-sidebar-text-hover: #38bdf8;
				--vpdl-sidebar-text-muted: #6b7280;
				--vpdl-sidebar-hover-bg: rgba(255, 255, 255, 0.04);
				--vpdl-sidebar-active-bg: rgba(56, 189, 248, 0.12);
				--vpdl-sidebar-active-text: #38bdf8;
				--vpdl-sidebar-user-card: rgba(255, 255, 255, 0.03);
				--vpdl-sidebar-user-name: #f9fafb;
				--vpdl-theme-btn-bg: rgba(0, 0, 0, 0.35);
				--vpdl-theme-btn-active: #1f2937;
				--vpdl-theme-btn-shadow: 0 1px 2px rgba(0,0,0,0.3);
				--vpdl-canvas-bg: #070a12;
				--vpdl-topbar-bg: #0b0f19;
				--vpdl-card-bg: #0f172a;
				--vpdl-border-color: #1f2937;
				--vpdl-text-main: #f9fafb;
				--vpdl-text-muted: #9ca3af;
				--vpdl-accent: #38bdf8;
				--vpdl-accent-hover: #0284c7;
				--vpdl-input-bg: #111827;
				--vpdl-input-border: #374151;
				--vpdl-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.2);
			}

			html.wp-toolbar, body.toplevel_page_vpdl {
				padding: 0 !important;
				margin: 0 !important;
				overflow-x: hidden !important;
				overflow-y: hidden !important;
				height: 100% !important;
			}
			.toplevel_page_vpdl #adminmenumain,
			.toplevel_page_vpdl #wpadminbar,
			.toplevel_page_vpdl #wpfooter { 
				display: none !important; 
			}
			.toplevel_page_vpdl #wpcontent,
			.toplevel_page_vpdl #wpbody,
			.toplevel_page_vpdl #wpbody-content {
				margin: 0 !important;
				padding: 0 !important;
				width: 100% !important;
				max-width: 100% !important;
				overflow-x: hidden !important;
				overflow-y: hidden !important;
				height: 100% !important;
			}

			.vpdl-shell {
				display: flex !important;
				width: 100% !important;
				max-width: 100% !important;
				height: 100vh !important;
				max-height: 100vh !important;
				overflow: hidden !important;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
				background: var(--vpdl-canvas-bg);
				color: var(--vpdl-text-main);
				box-sizing: border-box;
				position: relative;
			}

			.vpdl-nav-scroll-wrap { 
				scrollbar-width: thin; 
				scrollbar-color: rgba(148, 163, 184, 0.2) transparent; 
			}
			.vpdl-nav-scroll-wrap::-webkit-scrollbar { width: 4px; }
			.vpdl-nav-scroll-wrap::-webkit-scrollbar-thumb { 
				background: rgba(148, 163, 184, 0.2); 
				border-radius: 4px; 
			}

			.vpdl-nav-item a {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 10px 14px;
				color: var(--vpdl-sidebar-text);
				text-decoration: none;
				border-radius: 8px;
				font-size: 13.5px;
				font-weight: 500;
				transition: all 0.15s ease;
			}

			.vpdl-nav-item a:hover { 
				color: var(--vpdl-sidebar-text-hover); 
				background: var(--vpdl-sidebar-hover-bg); 
			}
			.vpdl-nav-item.vpdl-active a { 
				background: var(--vpdl-sidebar-active-bg) !important; 
				color: var(--vpdl-sidebar-active-text) !important; 
				font-weight: 700; 
			}

			.vpdl-theme-btn {
				background: transparent;
				border: none;
				cursor: pointer;
				color: var(--vpdl-sidebar-text-muted);
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 6px;
				padding: 6px 12px;
				border-radius: 6px;
				font-size: 12px;
				font-weight: 600;
				transition: all 0.15s ease;
				flex: 1;
			}
			.vpdl-theme-btn.vpdl-theme-active {
				background: var(--vpdl-theme-btn-active) !important;
				color: var(--vpdl-accent) !important;
				box-shadow: var(--vpdl-theme-btn-shadow);
			}

			.vpdl-panel {
				background: var(--vpdl-card-bg);
				border: 1px solid var(--vpdl-border-color);
				border-radius: 12px;
				color: var(--vpdl-text-main);
				box-shadow: var(--vpdl-shadow-sm);
				box-sizing: border-box;
			}

			.vpdl-shell input, .vpdl-shell select, .vpdl-shell textarea {
				background: var(--vpdl-input-bg) !important;
				border: 1px solid var(--vpdl-input-border) !important;
				color: var(--vpdl-text-main) !important;
				border-radius: 8px !important;
				font-size: 13.5px !important;
				box-sizing: border-box;
			}

			.vpdl-shell input:focus, .vpdl-shell select:focus, .vpdl-shell textarea:focus {
				outline: none !important;
				border-color: var(--vpdl-accent) !important;
				box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15) !important;
			}
		</style>

		<aside class="vpdl-aside" style="width: 270px; min-width: 270px; height: 100vh; max-height: 100vh; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; box-sizing: border-box; padding: 20px 14px; background: var(--vpdl-sidebar-bg); border-right: 1px solid var(--vpdl-sidebar-border); color: var(--vpdl-sidebar-text); z-index: 20; transition: background 0.15s ease, border-color 0.15s ease;">
			<div class="vpdl-nav-scroll-wrap" style="flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; min-height: 0; padding-right: 4px; margin-bottom: 12px;">
				<div class="vpdl-brand" style="display: flex; align-items: center; gap: 12px; padding: 6px 8px 18px 8px; border-bottom: 1px solid var(--vpdl-sidebar-border); margin-bottom: 16px;">
					<div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 15px;">
						V
					</div>
					<div>
						<div style="font-size: 14.5px; font-weight: 800; color: var(--vpdl-sidebar-brand-text); letter-spacing: 0.2px;">VPDL LIMITED</div>
						<div style="font-size: 10px; color: var(--vpdl-sidebar-text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 2px;">
							<?php echo $is_admin ? esc_html__( 'Director Portal', 'vpdl-management' ) : esc_html__( 'Specialist Terminal', 'vpdl-management' ); ?>
						</div>
					</div>
				</div>

				<div class="vpdl-nav-group-title" style="font-size: 11px; font-weight: 700; color: var(--vpdl-sidebar-text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin: 12px 10px 6px;"><?php esc_html_e( 'Core Suite', 'vpdl-management' ); ?></div>
				<ul class="vpdl-nav" style="list-style: none; margin: 0; padding: 0;">
					<li class="vpdl-nav-item <?php echo ( 'dashboard' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'dashboard', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'dashboard', '', 19 ); ?>
							<span><?php esc_html_e( 'Dashboard', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'pipeline' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'pipeline', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'pipeline', '', 19 ); ?>
							<span><?php esc_html_e( 'Work Pipeline', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'customers' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'customers', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'customers', '', 19 ); ?>
							<span><?php esc_html_e( 'Clients & Leads', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'suppliers' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'suppliers', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'supplier', '', 19 ); ?>
							<span><?php esc_html_e( 'Suppliers Directory', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'purchase-orders' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'purchase-orders', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'purchase_order', '', 19 ); ?>
							<span><?php esc_html_e( 'Purchase Orders', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'quotes' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'quotes', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'planning', '', 19 ); ?>
							<span><?php esc_html_e( 'Quotations & Proposals', 'vpdl-management' ); ?></span>
						</a>
					</li>
				</ul>

				<div class="vpdl-nav-group-title" style="font-size: 11px; font-weight: 700; color: var(--vpdl-sidebar-text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin: 18px 10px 6px;"><?php esc_html_e( 'Services Portfolio', 'vpdl-management' ); ?></div>
				<ul class="vpdl-nav" style="list-style: none; margin: 0; padding: 0;">
					<li class="vpdl-nav-item <?php echo ( 'pdm' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'pdm', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'planning', '', 19 ); ?>
							<span><?php esc_html_e( 'Planning Design (PDM)', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'sdm' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'sdm', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'structural', '', 19 ); ?>
							<span><?php esc_html_e( 'Structural Design (SDM)', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'pmc' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'pmc', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'consulting', '', 19 ); ?>
							<span><?php esc_html_e( 'Project Mgmt (PMC)', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'bsm' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'bsm', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'survey', '', 19 ); ?>
							<span><?php esc_html_e( 'Building Survey (BSM)', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'schemes' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'schemes', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'schemes', '', 19 ); ?>
							<span><?php esc_html_e( 'Construction Schemes', 'vpdl-management' ); ?></span>
						</a>
					</li>
				</ul>

				<div class="vpdl-nav-group-title" style="font-size: 11px; font-weight: 700; color: var(--vpdl-sidebar-text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin: 18px 10px 6px;"><?php esc_html_e( 'Finance & Reports', 'vpdl-management' ); ?></div>
				<ul class="vpdl-nav" style="list-style: none; margin: 0; padding: 0;">
					<li class="vpdl-nav-item <?php echo ( 'invoices' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'invoices', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'invoice', '', 19 ); ?>
							<span><?php esc_html_e( 'Invoices & Billing', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'receipts' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'receipts', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'receipt', '', 19 ); ?>
							<span><?php esc_html_e( 'Paid Receipts', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<li class="vpdl-nav-item <?php echo ( 'consultant-reports' === $current_view ) ? 'vpdl-active' : ''; ?>">
						<a href="<?php echo esc_url( add_query_arg( 'view', 'consultant-reports', $base_url ) ); ?>">
							<?php echo vpdl_get_svg( 'reports', '', 19 ); ?>
							<span><?php esc_html_e( 'Consultant Reports', 'vpdl-management' ); ?></span>
						</a>
					</li>
					<?php if ( $is_admin ) : ?>
						<li class="vpdl-nav-item <?php echo ( 'services' === $current_view ) ? 'vpdl-active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'view', 'services', $base_url ) ); ?>">
								<?php echo vpdl_get_svg( 'settings', '', 19 ); ?>
								<span><?php esc_html_e( 'Service Rates & Fees', 'vpdl-management' ); ?></span>
							</a>
						</li>
						<li class="vpdl-nav-item <?php echo ( 'accounts' === $current_view ) ? 'vpdl-active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'view', 'accounts', $base_url ) ); ?>">
								<?php echo vpdl_get_svg( 'accounts', '', 19 ); ?>
								<span><?php esc_html_e( 'Accounts & VAT', 'vpdl-management' ); ?></span>
							</a>
						</li>
						<li class="vpdl-nav-item <?php echo ( 'team' === $current_view ) ? 'vpdl-active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'view', 'team', $base_url ) ); ?>">
								<?php echo vpdl_get_svg( 'team', '', 19 ); ?>
								<span><?php esc_html_e( 'Team & Agents', 'vpdl-management' ); ?></span>
							</a>
						</li>
						<li class="vpdl-nav-item <?php echo ( 'reports' === $current_view ) ? 'vpdl-active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'view', 'reports', $base_url ) ); ?>">
								<?php echo vpdl_get_svg( 'reports', '', 19 ); ?>
								<span><?php esc_html_e( 'Audit Reports', 'vpdl-management' ); ?></span>
							</a>
						</li>
						<li class="vpdl-nav-item <?php echo ( 'settings' === $current_view ) ? 'vpdl-active' : ''; ?>">
							<a href="<?php echo esc_url( add_query_arg( 'view', 'settings', $base_url ) ); ?>">
								<?php echo vpdl_get_svg( 'settings', '', 19 ); ?>
								<span><?php esc_html_e( 'Settings', 'vpdl-management' ); ?></span>
							</a>
						</li>
					<?php endif; ?>
				</ul>
			</div>

			<div style="flex-shrink: 0; border-top: 1px solid var(--vpdl-sidebar-border); padding-top: 14px;">
				<div style="display: flex; align-items: center; justify-content: space-between; background: var(--vpdl-theme-btn-bg); border: 1px solid var(--vpdl-sidebar-border); border-radius: 8px; padding: 4px; margin-bottom: 12px;">
					<button type="button" id="vpdlThemeLightBtn" class="vpdl-theme-btn" onclick="vpdlSetTheme('light')">
						<?php echo vpdl_get_svg( 'sun', '', 15 ); ?>
						<span><?php esc_html_e( 'Light', 'vpdl-management' ); ?></span>
					</button>
					<button type="button" id="vpdlThemeDarkBtn" class="vpdl-theme-btn" onclick="vpdlSetTheme('dark')">
						<?php echo vpdl_get_svg( 'moon', '', 15 ); ?>
						<span><?php esc_html_e( 'Dark', 'vpdl-management' ); ?></span>
					</button>
				</div>

				<div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px; border-radius: 8px; background: var(--vpdl-sidebar-user-card); border: 1px solid var(--vpdl-sidebar-border);">
					<div style="display: flex; align-items: center; gap: 10px; overflow: hidden;">
						<div style="width: 32px; height: 32px; border-radius: 6px; background: #0284c7; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: #fff;">
							<?php echo esc_html( strtoupper( substr( wp_get_current_user()->display_name, 0, 1 ) ) ); ?>
						</div>
						<div style="overflow: hidden;">
							<div style="font-size: 12.5px; font-weight: 700; color: var(--vpdl-sidebar-user-name); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
								<?php echo esc_html( wp_get_current_user()->display_name ); ?>
							</div>
							<div style="font-size: 10.5px; color: var(--vpdl-sidebar-text-muted);">
								<?php echo $is_admin ? esc_html__( 'Executive Director', 'vpdl-management' ) : esc_html__( 'Staff Specialist', 'vpdl-management' ); ?>
							</div>
						</div>
					</div>
					<a href="<?php echo esc_url( wp_logout_url( admin_url( 'index.php' ) ) ); ?>" title="<?php esc_attr_e( 'Logout', 'vpdl-management' ); ?>" style="color: #ef4444; display: flex; align-items: center; justify-content: center; padding: 6px; border-radius: 6px; text-decoration: none;">
						<?php echo vpdl_get_svg( 'logout', '', 16 ); ?>
					</a>
				</div>
			</div>
		</aside>

		<main class="vpdl-canvas" style="flex: 1; min-width: 0; height: 100vh; display: flex; flex-direction: column; overflow: hidden; background: var(--vpdl-canvas-bg); color: var(--vpdl-text-main); box-sizing: border-box; transition: background 0.15s ease;">
			<header style="height: 64px; min-height: 64px; background: var(--vpdl-topbar-bg); border-bottom: 1px solid var(--vpdl-border-color); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-sizing: border-box; z-index: 10; transition: background 0.15s ease, border-color 0.15s ease;">
				<div style="display: flex; align-items: center; gap: 12px;">
					<h1 style="margin: 0; font-size: 17px; font-weight: 700; color: var(--vpdl-text-main);"><?php echo esc_html( $b_name ); ?></h1>
					<span style="display: inline-block; width: 4px; height: 4px; border-radius: 50%; background: var(--vpdl-text-muted);"></span>
					<span style="font-size: 13px; color: var(--vpdl-text-muted); font-weight: 500;">
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $current_view ) ) ); ?>
					</span>
				</div>
				<div style="display: flex; align-items: center; gap: 14px;">
					<div style="font-size: 12.5px; font-weight: 600; color: var(--vpdl-text-muted); display: flex; align-items: center; gap: 6px;">
						<span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981;"></span>
						<?php echo esc_html( current_time( 'D, M j, Y' ) ); ?>
					</div>
				</div>
			</header>

			<div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 32px; box-sizing: border-box;">
				<?php if ( isset( $flash_map[ $msg_code ] ) ) :
					$is_error = 'error' === $flash_map[ $msg_code ]['type'];
				?>
					<div style="<?php echo $is_error ? 'background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;' : 'background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;'; ?> padding: 12px 18px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
						<?php echo vpdl_get_svg( $is_error ? 'settings' : 'check', '', 16 ); ?>
						<span><?php echo esc_html( $flash_map[ $msg_code ]['msg'] ); ?></span>
					</div>
				<?php endif; ?>

				<?php
				// Support modular subfolder views (e.g. views/accounts/accounts.php) or flat files
				$subfolder_path = VPDL_PATH . 'views/' . $current_view . '/' . $current_view . '.php';
				$flat_path      = VPDL_PATH . 'views/' . $current_view . '.php';

				$view_path = '';
				if ( file_exists( $subfolder_path ) ) {
					$view_path = $subfolder_path;
					// Enqueue modular view assets if they exist
					$css_url = VPDL_URL . 'views/' . $current_view . '/' . $current_view . '.css';
					$js_url  = VPDL_URL . 'views/' . $current_view . '/' . $current_view . '.js';
					if ( file_exists( VPDL_PATH . 'views/' . $current_view . '/' . $current_view . '.css' ) ) {
						wp_enqueue_style( 'vpdl-' . $current_view . '-style', $css_url, array(), VPDL_VERSION );
					}
					if ( file_exists( VPDL_PATH . 'views/' . $current_view . '/' . $current_view . '.js' ) ) {
						wp_enqueue_script( 'vpdl-' . $current_view . '-script', $js_url, array( 'jquery' ), VPDL_VERSION, true );
					}
				} elseif ( file_exists( $flat_path ) ) {
					$view_path = $flat_path;
				}

				if ( ! empty( $view_path ) ) {
					include $view_path;
				} else {
					echo '<div class="vpdl-panel" style="padding: 48px 24px; text-align: center;">';
					echo '<h3 style="margin-top:0; font-size:18px; font-weight:700;">' . esc_html( ucwords( str_replace( '-', ' ', $current_view ) ) ) . ' ' . esc_html__( 'Module', 'vpdl-management' ) . '</h3>';
					echo '<p style="color: var(--vpdl-text-muted); font-size:13.5px;">' . esc_html__( 'Configured view partial not found:', 'vpdl-management' ) . ' <code>views/' . esc_html( $current_view ) . '.php</code></p>';
					echo '</div>';
				}
				?>
			</div>
		</main>
	</div>

	<script>
		function vpdlSetTheme(theme) {
			var shell = document.getElementById('vpdlAppShell');
			var btnLight = document.getElementById('vpdlThemeLightBtn');
			var btnDark = document.getElementById('vpdlThemeDarkBtn');
			if (!shell) return;

			if (theme === 'dark') {
				shell.setAttribute('data-theme', 'dark');
				document.documentElement.setAttribute('data-theme', 'dark');
				try { localStorage.setItem('vpdl_theme_pref', 'dark'); } catch(e){}
				if (btnDark && btnLight) {
					btnDark.classList.add('vpdl-theme-active');
					btnLight.classList.remove('vpdl-theme-active');
				}
			} else {
				shell.setAttribute('data-theme', 'light');
				document.documentElement.setAttribute('data-theme', 'light');
				try { localStorage.setItem('vpdl_theme_pref', 'light'); } catch(e){}
				if (btnDark && btnLight) {
					btnLight.classList.add('vpdl-theme-active');
					btnDark.classList.remove('vpdl-theme-active');
				}
			}
		}

		(function() {
			var saved = null;
			try { saved = localStorage.getItem('vpdl_theme_pref'); } catch(e){}
			if (saved === 'dark') {
				vpdlSetTheme('dark');
			} else {
				vpdlSetTheme('light');
			}
		})();
	</script>
	<?php
}

/**
 * 12. Arithmetic Captcha Login Shield
 */
add_action( 'login_form', 'vpdl_add_math_captcha' );
function vpdl_add_math_captcha() {
	$num1 = wp_rand( 3, 9 );
	$num2 = wp_rand( 2, 8 );
	$sum  = $num1 + $num2;

	$captcha_token = wp_generate_password( 24, false, false );
	set_transient( 'vpdl_captcha_' . $captcha_token, $sum, 300 );
	?>
	<p class="vpdl-captcha-wrap" style="margin-bottom: 20px;">
		<label for="vpdl_captcha_answer" style="display: block; font-weight: 700; margin-bottom: 6px; color: #334155; font-size: 13px;">
			<?php printf( esc_html__( 'Security Check: %1$d + %2$d = ?', 'vpdl-management' ), absint( $num1 ), absint( $num2 ) ); ?>
			<span style="color: #ef4444;">*</span>
		</label>
		<input type="number" name="vpdl_captcha_answer" id="vpdl_captcha_answer" class="input" value="" size="20" required autocomplete="off" placeholder="<?php esc_attr_e( 'Enter answer', 'vpdl-management' ); ?>">
		<input type="hidden" name="vpdl_captcha_token" value="<?php echo esc_attr( $captcha_token ); ?>">
	</p>
	<?php
}

add_filter( 'authenticate', 'vpdl_verify_math_captcha', 30, 3 );
function vpdl_verify_math_captcha( $user, $username, $password ) {
	if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || empty( $username ) || empty( $password ) || is_wp_error( $user ) ) {
		return $user;
	}

	if ( isset( $_POST['vpdl_captcha_answer'], $_POST['vpdl_captcha_token'] ) ) {
		$token  = sanitize_text_field( wp_unslash( $_POST['vpdl_captcha_token'] ) );
		$answer = intval( wp_unslash( $_POST['vpdl_captcha_answer'] ) );

		$correct_sum = get_transient( 'vpdl_captcha_' . $token );
		delete_transient( 'vpdl_captcha_' . $token );

		if ( false === $correct_sum || $answer !== intval( $correct_sum ) ) {
			return new WP_Error( 'invalid_captcha', __( '<strong>SECURITY WARNING</strong>: Incorrect calculation answer. Access rejected.', 'vpdl-management' ) );
		}
	} else {
		return new WP_Error( 'invalid_captcha', __( '<strong>SECURITY WARNING</strong>: Captcha answer missing.', 'vpdl-management' ) );
	}

	return $user;
}

/**
 * 13. Custom Login Page Styling
 */
add_action( 'login_enqueue_scripts', 'vpdl_custom_login_style' );
function vpdl_custom_login_style() {
	$logo_url = (string) get_option( 'vpdl_logo_url', '' );
	?>
	<style>
		body.login {
			background: linear-gradient(135deg, #090d16 0%, #0c1c2e 50%, #0369a1 100%) !important;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			margin: 0 !important;
		}
		#login { width: 440px !important; padding: 20px !important; }
		.login form {
			background: rgba(255, 255, 255, 0.96) !important;
			border-radius: 20px !important;
			box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
			padding: 38px !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
		}
		.login h1 a {
			<?php if ( ! empty( $logo_url ) ) : ?>
				background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
				background-size: contain !important;
				width: 100% !important;
				height: 65px !important;
			<?php else : ?>
				background-image: none !important;
				text-indent: 0 !important;
				color: #0f172a !important;
				font-size: 20px !important;
				font-weight: 900 !important;
				width: 100% !important;
				height: auto !important;
				text-align: center;
			<?php endif; ?>
		}
		.login input.input {
			border-radius: 10px !important;
			border: 1.5px solid #cbd5e1 !important;
			padding: 12px 14px !important;
			height: 46px !important;
			font-size: 14.5px !important;
			width: 100% !important;
		}
		.login .button.wp-submit {
			background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
			border: none !important;
			height: 48px !important;
			border-radius: 12px !important;
			font-weight: 800 !important;
			width: 100% !important;
			box-shadow: 0 8px 20px rgba(2, 132, 199, 0.35) !important;
			cursor: pointer;
			color: #fff !important;
		}
		#nav a, #backtoblog a { color: rgba(255, 255, 255, 0.85) !important; font-weight: 600 !important; font-size: 12.5px; text-decoration: none; }
	</style>
	<?php
}

/**
 * 14. Custom Login Branding & Redirection
 */
add_filter( 'login_footertext', 'vpdl_custom_login_footer' );
function vpdl_custom_login_footer() {
	$b_name = get_option( 'vpdl_business_name', 'VOLUME PLANNING AND DEVELOPMENTS LIMITED' );
	return sprintf( esc_html__( '&copy; %1$d %2$s &bull; Enterprise Operating System', 'vpdl-management' ), (int) current_time( 'Y' ), esc_html( $b_name ) );
}

add_filter( 'login_redirect', 'vpdl_login_redirect_dashboard', 10, 3 );
function vpdl_login_redirect_dashboard( $redirect_to, $requested_redirect_to, $user ) {
	if ( isset( $user->ID ) ) {
		if ( user_can( $user, 'vpdl_access_terminal' ) || user_can( $user, 'manage_options' ) ) {
			return admin_url( 'admin.php?page=vpdl' );
		}
	}
	return $redirect_to;
}

/**
 * 15. Public Token View Route
 */
add_action( 'template_redirect', 'vpdl_handle_public_token_view' );
function vpdl_handle_public_token_view() {
	if ( ! isset( $_GET['vpdl_view'], $_GET['token'] ) ) {
		return;
	}

	global $wpdb;
	$type  = sanitize_key( wp_unslash( $_GET['vpdl_view'] ) );
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );

	if ( ! preg_match( '/^[A-Za-z0-9]{32,64}$/', $token ) ) {
		wp_die( esc_html__( 'Invalid document token structure.', 'vpdl-management' ), 400 );
	}

	$valid_types = array(
		'quote'   => $wpdb->prefix . 'vpdl_quotations',
		'invoice' => $wpdb->prefix . 'vpdl_invoices',
		'receipt' => $wpdb->prefix . 'vpdl_receipts',
	);

	if ( ! array_key_exists( $type, $valid_types ) ) {
		return;
	}

	$table  = $valid_types[ $type ];
	$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE access_token = %s", $token ) );

	if ( ! $record ) {
		wp_die( esc_html__( 'Invalid or expired document token.', 'vpdl-management' ), 404 );
	}

	nocache_headers();

	$template_file = VPDL_PATH . 'views/public-' . $type . '.php';
	if ( file_exists( $template_file ) ) {
		include $template_file;
		exit;
	}
}