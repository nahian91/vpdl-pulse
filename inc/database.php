<?php
/**
 * VPDL Database Provisioning & Migration Handler
 *
 * @package VPDLManagement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles network-wide or single-site activation.
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 */
function vpdl_network_activate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		global $wpdb;
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			vpdl_install_single();
			vpdl_setup_roles_and_caps();
			restore_current_blog();
		}
	} else {
		vpdl_install_single();
		vpdl_setup_roles_and_caps();
	}
}

/**
 * Provisions tables for new sites created in a Multisite network.
 *
 * @param WP_Site $new_site New site object.
 * @param array   $args     Arguments.
 */
function vpdl_on_initialize_site( $new_site, $args ) {
	if ( is_plugin_active_for_network( plugin_basename( VPDL_FILE ) ) ) {
		switch_to_blog( (int) $new_site->blog_id );
		vpdl_install_single();
		vpdl_setup_roles_and_caps();
		restore_current_blog();
	}
}

/**
 * Provisions schema tables and default plugin settings.
 */
function vpdl_install_single() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$t_cust     = $wpdb->prefix . 'vpdl_customers';
	$t_supp     = $wpdb->prefix . 'vpdl_suppliers';
	$t_inq      = $wpdb->prefix . 'vpdl_inquiries';
	$t_services = $wpdb->prefix . 'vpdl_services';
	$t_quotes   = $wpdb->prefix . 'vpdl_quotations';
	$t_po       = $wpdb->prefix . 'vpdl_purchase_orders';
	$t_inv      = $wpdb->prefix . 'vpdl_invoices';
	$t_receipts = $wpdb->prefix . 'vpdl_receipts';
	$t_accounts = $wpdb->prefix . 'vpdl_accounts';

	// Mandatory: Exactly two spaces after PRIMARY KEY for dbDelta engine parser.
	$sql = "CREATE TABLE {$t_cust} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		email varchar(100) DEFAULT '' NOT NULL,
		phone varchar(30) NOT NULL,
		site_address text DEFAULT NULL,
		postcode varchar(20) DEFAULT '' NOT NULL,
		council_name varchar(100) DEFAULT '' NOT NULL,
		client_type varchar(50) DEFAULT 'Homeowner' NOT NULL,
		lead_source varchar(50) DEFAULT 'Direct' NOT NULL,
		assigned_to bigint(20) DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_phone (phone)
	) {$charset_collate};
	CREATE TABLE {$t_supp} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		company_name varchar(150) NOT NULL,
		contact_person varchar(100) DEFAULT '' NOT NULL,
		email varchar(100) DEFAULT '' NOT NULL,
		phone varchar(30) NOT NULL,
		category varchar(100) DEFAULT 'Materials' NOT NULL,
		address text DEFAULT NULL,
		vat_number varchar(50) DEFAULT '' NOT NULL,
		status varchar(30) DEFAULT 'Active' NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_category (category),
		KEY idx_status (status)
	) {$charset_collate};
	CREATE TABLE {$t_inq} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		customer_id mediumint(9) NOT NULL,
		service_category varchar(50) NOT NULL,
		assigned_consultant bigint(20) DEFAULT NULL,
		consultant_fee decimal(10,2) DEFAULT 0.00 NOT NULL,
		notes text DEFAULT NULL,
		status varchar(30) DEFAULT 'New' NOT NULL,
		next_followup_date date DEFAULT NULL,
		created_by varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_customer (customer_id),
		KEY idx_consultant (assigned_consultant),
		KEY idx_status (status)
	) {$charset_collate};
	CREATE TABLE {$t_services} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		category varchar(50) NOT NULL,
		code varchar(20) NOT NULL,
		description varchar(255) NOT NULL,
		fee decimal(10,2) NOT NULL DEFAULT 0.00,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code)
	) {$charset_collate};
	CREATE TABLE {$t_quotes} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		quote_number varchar(50) NOT NULL,
		customer_id mediumint(9) NOT NULL,
		items_json longtext NOT NULL,
		total_sqm decimal(10,2) DEFAULT 0.00,
		subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
		vat_amount decimal(10,2) NOT NULL DEFAULT 0.00,
		grand_total decimal(10,2) NOT NULL DEFAULT 0.00,
		status varchar(30) DEFAULT 'Draft' NOT NULL,
		access_token varchar(64) NOT NULL,
		created_by varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY quote_number (quote_number),
		UNIQUE KEY access_token (access_token),
		KEY idx_status (status)
	) {$charset_collate};
	CREATE TABLE {$t_po} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		po_number varchar(50) NOT NULL,
		supplier_id mediumint(9) NOT NULL,
		related_project varchar(150) DEFAULT '' NOT NULL,
		items_json longtext NOT NULL,
		subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
		vat_amount decimal(10,2) NOT NULL DEFAULT 0.00,
		grand_total decimal(10,2) NOT NULL DEFAULT 0.00,
		status varchar(30) DEFAULT 'Pending' NOT NULL,
		expected_delivery date DEFAULT NULL,
		notes text DEFAULT NULL,
		created_by varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY po_number (po_number),
		KEY idx_supplier (supplier_id),
		KEY idx_status (status)
	) {$charset_collate};
	CREATE TABLE {$t_inv} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		invoice_number varchar(50) NOT NULL,
		quote_id mediumint(9) DEFAULT NULL,
		customer_id mediumint(9) NOT NULL,
		items_json longtext NOT NULL,
		subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
		vat_amount decimal(10,2) NOT NULL DEFAULT 0.00,
		grand_total decimal(10,2) NOT NULL DEFAULT 0.00,
		payment_status varchar(30) DEFAULT 'Unpaid' NOT NULL,
		access_token varchar(64) NOT NULL,
		created_by varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY invoice_number (invoice_number),
		UNIQUE KEY access_token (access_token),
		KEY idx_payment_status (payment_status)
	) {$charset_collate};
	CREATE TABLE {$t_receipts} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		receipt_number varchar(50) NOT NULL,
		invoice_id mediumint(9) NOT NULL,
		customer_id mediumint(9) NOT NULL,
		amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
		payment_method varchar(50) DEFAULT 'Bank Transfer' NOT NULL,
		payment_reference varchar(100) DEFAULT '' NOT NULL,
		paid_date date NOT NULL,
		access_token varchar(64) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY receipt_number (receipt_number),
		UNIQUE KEY access_token (access_token),
		KEY idx_invoice (invoice_id),
		KEY idx_customer (customer_id),
		KEY idx_paid_date (paid_date)
	) {$charset_collate};
	CREATE TABLE {$t_accounts} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		title varchar(200) NOT NULL,
		type varchar(20) DEFAULT 'Income' NOT NULL,
		amount decimal(10,2) NOT NULL DEFAULT 0.00,
		vat_collected decimal(10,2) NOT NULL DEFAULT 0.00,
		reference_id varchar(50) DEFAULT NULL,
		entry_date date NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY idx_entry_date (entry_date)
	) {$charset_collate};";

	dbDelta( $sql );
	vpdl_seed_services_single();

	$defaults = array(
		'vpdl_business_name'     => 'VOLUME PLANNING AND DEVELOPMENTS LIMITED',
		'vpdl_company_tag'       => 'Offers Project Management, Civil and Structural Engineering, Planning and Survey Services',
		'vpdl_currency'          => '£',
		'vpdl_vat_rate'          => '20.00',
		'vpdl_vat_number'        => 'GB 123 4567 89',
		'vpdl_quote_validity'    => 30,
		'vpdl_phone'             => '+44 3333 445 807',
		'vpdl_email'             => 'vpdl.uk@gmail.com',
		'vpdl_address'           => 'ESL Office 256B, 182-184 High Street North, London, England, E6 2JA',
		'vpdl_reg_no'            => '08456916',
		'vpdl_logo_url'          => '',
		'vpdl_bank_name'         => 'Barclays Bank UK PLC',
		'vpdl_bank_account_name' => 'VOLUME PLANNING AND DEVELOPMENTS LIMITED',
		'vpdl_bank_account_no'   => '83749201',
		'vpdl_bank_sort_code'    => '20-04-15',
		'vpdl_version'           => VPDL_VERSION,
	);

	foreach ( $defaults as $key => $val ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $val );
		}
	}
}

/**
 * Seeds default professional services rates and catalog items.
 */
function vpdl_seed_services_single() {
	global $wpdb;
	$table = $wpdb->prefix . 'vpdl_services';

	$services = array(
		array( 'category' => 'PDM', 'code' => 'PDM 00', 'description' => 'Site Visit / Project Initiation', 'fee' => 350.00 ),
		array( 'category' => 'PDM', 'code' => 'PDM 01', 'description' => 'Side & Rears/Garage/Garden Shed Extensions / Loft Conversion', 'fee' => 850.00 ),
		array( 'category' => 'PDM', 'code' => 'PDM 02', 'description' => 'New House Builds up to 2 Units', 'fee' => 1975.00 ),
		array( 'category' => 'PDM', 'code' => 'PDM 03', 'description' => 'Flat Conversion up to 2 Units', 'fee' => 1975.00 ),
		array( 'category' => 'PDM', 'code' => 'PDM 04', 'description' => 'Flat Conversion 3-5 Units', 'fee' => 4475.00 ),
		array( 'category' => 'SDM', 'code' => 'SDM 00', 'description' => 'Site Visit / Project Initiation', 'fee' => 350.00 ),
		array( 'category' => 'SDM', 'code' => 'SDM 01', 'description' => 'Side & Rears/Garage/Garden Shed Extensions (£975) / Loft Conversion (£1,225)', 'fee' => 975.00 ),
		array( 'category' => 'SDM', 'code' => 'SDM 02', 'description' => 'New House Builds up to 2 Units', 'fee' => 1225.00 ),
		array( 'category' => 'SDM', 'code' => 'SDM 03', 'description' => 'Flat Conversion up to 2 Units', 'fee' => 1600.00 ),
		array( 'category' => 'SDM', 'code' => 'SDM 04', 'description' => 'Flat Conversion 3-5 Units', 'fee' => 2350.00 ),
		array( 'category' => 'PMC', 'code' => 'PMC 00', 'description' => 'Site Visit / Project Initiation', 'fee' => 350.00 ),
		array( 'category' => 'PMC', 'code' => 'PMC 01', 'description' => 'Side & Rears/Garage/Garden Shed Extensions / Loft Conversion', 'fee' => 2475.00 ),
		array( 'category' => 'PMC', 'code' => 'PMC 02', 'description' => 'New House Builds up to 2 Units', 'fee' => 2475.00 ),
		array( 'category' => 'PMC', 'code' => 'PMC 03', 'description' => 'Flat Conversion up to 2 Units', 'fee' => 3225.00 ),
		array( 'category' => 'PMC', 'code' => 'PMC 04', 'description' => 'Flat Conversion 3-5 Units', 'fee' => 5725.00 ),
		array( 'category' => 'BSM', 'code' => 'BSM 00', 'description' => 'Site Visit / Project Initiation', 'fee' => 350.00 ),
		array( 'category' => 'BSM', 'code' => 'BSM 01', 'description' => 'Party Wall Agreements and Advice for Building Owner', 'fee' => 725.00 ),
		array( 'category' => 'BSM', 'code' => 'BSM 02', 'description' => 'Party Wall Agreements and Advice for two Adjoining Owner', 'fee' => 1075.00 ),
		array( 'category' => 'BSM', 'code' => 'BSM 03', 'description' => 'Party Wall Agreements and Advice for Adjoining Owner up to three', 'fee' => 1425.00 ),
	);

	foreach ( $services as $s ) {
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $s['code'] ) );
		if ( ! $exists ) {
			$wpdb->insert( $table, $s, array( '%s', '%s', '%s', '%f' ) );
		}
	}
}