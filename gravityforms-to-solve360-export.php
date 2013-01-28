<?php

/**
 * @package Gravity Forms to Solve360 Export
 * @subpackage plugin.php
 * @version 0.1
 * @todo Cleanup code
 * @todo Improve error handling
 * @todo Only delete temp files if there were no Solve response errors
 * @todo Investigate when 'ownership' is required
 * @todo Handle other Solve360 contact activities appropriately
 */

/*
Plugin Name: Gravity Forms to Solve360 Export
Plugin URI: http://duane.co.za/plugins/gravityforms-to-solve360-export
Description: Exports data from completed <a href="http://www.gravityforms.com/">Gravity Forms</a> to a specified <a href="http://norada.com/">Solve360</a> account.
Version: 0.1
Author: Duane Cilliers
Author URI: http://duane.co.za
Author Email: hello@duane.co.za
License: GPLv2 or later

	Copyright 2013 Duane Cilliers (hello@duane.co.za)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

class GravityFormsToSolve360Export {

	public $plugin_data;
	public $plugin_filename;
	public $debug;
	public $errors;
	public $warnings;
	public $options_url;
	public $management_url;
	public $contacts_url;
	public $user;
	public $token;
	public $email_to;
	public $email_from;
	public $email_cc;
	public $email_bcc;

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/

	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {

		$this->options_url = admin_url( 'options-general.php?page=gf-s360-export-options' );
		$this->management_url = admin_url( 'tools.php?page=gf-s360-export' );
		$this->contacts_url = 'https://secure.solve360.com/contacts';
		$this->debug = get_option( 'gf_s360_export_debug_mode' );
		$this->user = get_option( 'gf_s360_export_user' );
		$this->token = get_option( 'gf_s360_export_token' );
		$this->email_to = get_option( 'gf_s360_export_to' );
		$this->email_from = get_option( 'gf_s360_export_from' );;
		$this->email_cc = get_option( 'gf_s360_export_cc' );
		$this->email_bcc = get_option( 'gf_s360_export_bcc' );

		// Check that is_plugin_active() function exists before using it or deactivate_plugins()
		if ( ! function_exists('is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Custom functionality
		add_action( 'admin_menu', array( $this, 'admin_pages' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// hook onto gform_subm
		add_action( 'gform_after_submission', array( $this, 'form_submission' ), 10, 2 );

	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {

		// Ensure Gravity Forms is installed
		if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
			$this->errors .= '<p><a href="#affiliate_link,">Gravity Forms</a> is not activated, and is required.</p>';
			die( $this->errors );
		}

		// Ensure Curl is installed
		if ( ! in_array ('curl', get_loaded_extensions() ) ) {
			$this->errors .='<p><a href="http://php.net/manual/en/book.curl.php" target="_blank">PHP Curl</a> is not installed, and is required.</p>';
			die( $this->errors );
		}

		// Default settings
		$options = array(
			'gf_s360_export_debug_mode' => 'true'
		);
		foreach ( $options as $option => $value ) {
			update_option( $option, $value );
		}

	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {

		// TODO:  Define deactivation functionality here

	} // end deactivate

	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {

		// TODO: replace "gf-to-solve360-export-locale" with a unique value for your plugin
		load_plugin_textdomain( 'gf-to-solve360-export-locale', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

	} // end plugin_textdomain

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {

		wp_enqueue_style( 'gf-to-solve360-export-admin-styles', plugins_url( 'css/admin.css', __FILE__ ) );

	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {

		wp_enqueue_script( 'gf-to-solve360-export-admin-script', plugins_url( 'js/admin.js', __FILE__ ) );

	} // end register_admin_scripts

	/*--------------------------------------------*
	 * Core Functions
	 *---------------------------------------------*/

	/**
	 * Display configuration errors on all admin pages
	 */
	function admin_notices() {

		// Set errors and warnings
		if ( ! $this->user ) {
			echo '<div id="message" class="error"><p><strong>Error!</strong> <a href="' . $this->options_url . '">Solve360 user</a> is not set and is required!</p></div>';
		}
		if ( ! $this->token ) {
			echo '<div id="message" class="error"><p><strong>Error!</strong> <a href="' . $this->options_url . '">Solve360 token</a> is not set and is required!</p></div>';
		}
		if ( ! $this->email_to ) {
			echo '<div id="message" class="updated"><p><strong>Warning!</strong> <a href="' . $this->options_url . '">To: field</a> for notification emails is not set.</p></div>';
		}
		if ( ! $this->email_from ) {
			echo '<div id="message" class="updated"><p><strong>Warning!</strong> <a href="' . $this->options_url . '">From: field</a> for notification emails is not set.</p></div>';
		}

	} // end admin_notices()

	/**
	 * Create admin pages
	 */
	function admin_pages() {

		add_options_page( 'Gravity Forms to Solve360 Export', 'Gravity Forms to Solve360 Export', 'manage_options', 'gf-s360-export-options', array( $this, 'options_page' ) );

	} // end admin_pages

	/**
	 * Create options page
	 */
	function options_page() {

		$accepted_fields = array(
			'gf_s360_export_debug_mode',
			'gf_s360_export_user',
			'gf_s360_export_token',
			'gf_s360_export_to',
			'gf_s360_export_from',
			'gf_s360_export_cc',
			'gf_s360_export_bcc'
		);

		// Save data
		if ( $_POST && wp_verify_nonce( $_POST['gf_s360_export_nonce'], 'gf_s360_export_edit' ) ) {
			foreach ( $accepted_fields as $accepted_field ) {
				update_option( $accepted_field, $_POST[$accepted_field] );
			}
		}

		// Include options view
		require plugin_dir_path(__FILE__) . 'views/admin-options.php';

	} // end options_page

	/**
	 * Run main script as a background process when a form is submitted
	 * @param  object $entry The entry that was just created.
	 * @param  object $form  The current form.
	 */
	function form_submission( $entry, $form ) {

		// Assign variables
		$temp_dir = plugin_dir_path( __FILE__ ) . 'temp';
		$date = date('Y-m-d-H:i:s');

		// Create temp directory if it doesn't already exist
		if ( ! is_dir( $temp_dir ) )
			mkdir( $temp_dir );

		$entry = serialize($entry);
		$form = serialize($form);
		$user = isset( $this->user ) ? $this->user : false ;
		$token = isset( $this->token ) ? $this->token : false ;
		$contacts_url = isset( $this->contacts_url ) ? $this->contacts_url : false ;
		$to = isset( $this->email_to ) ? $this->email_to : false ;
		$from = isset( $this->email_from ) ? $this->email_from : false ;
		$cc = isset( $this->email_cc ) ? $this->email_cc : false ;
		$bcc = isset( $this->email_bcc ) ? $this->email_bcc : false ;
		$debug = $this->debug;

		$args = serialize( array(
	                  		'entry' => $entry,
	                  		'form' => $form,
					'user' => $user,
					'token' => $token,
					'contacts_url' => $contacts_url,
					'to' => $to,
					'from' => $from,
					'cc' => $cc,
					'bcc' => $bcc,
					'debug' => $debug
				) );
		$filename = "$temp_dir/temp-$date.txt";
		file_put_contents( $filename, $args );

		// Initiate background process
		$process_file = plugin_dir_path( __FILE__ ) . 'includes/process-form-data.php';
		$background_process = shell_exec( "php $process_file $filename > /dev/null 2>/dev/null &" );

	} // end form_submission( $entry, $form )

} // end class

$gravity_forms_to_solve360_export = new GravityFormsToSolve360Export();
