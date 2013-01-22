<?php

/**
 * @package Gravity Forms to Solve360 Export
 * @subpackage plugin.php
 * @version 0.1
 * @todo Search labels for activities and add the activity with contact ID (first check if the activity exists, update if it does, otherwise add it)
 * @todo Investigate when 'ownership' is required (not required)
 * @todo pass plugin filename with shell_exec for use in process-form-data.php
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
	public $start_date;
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
		$this->start_date = get_option( 'gf_s360_export_start_date' );
		$this->email_to = get_option( 'gf_s360_export_to' );
		$this->email_from = get_option( 'gf_s360_export_from' );;
		$this->email_cc = get_option( 'gf_s360_export_cc' );
		$this->email_bcc = get_option( 'gf_s360_export_bcc' );

		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( $this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Custom functionality
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_pages' ) );

		// hook onto gform_subm
		add_action( 'gform_after_submission', array( $this, 'form_submission' ), 10, 2 );

	} // end constructor

	/**
	 * Fired when the plugin is activated.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function activate( $network_wide ) {

		// Check that is_plugin_active() function exists before using it or deactivate_plugins()
		if ( ! function_exists('is_plugin_active' ) )
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Check Gravity Forms is installed
		if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
			$this->errors .= '<p><a href="#affiliate_link,">Gravity Forms</a> is not activated, and is required.</p>';
			die( $this->errors );
		}

		// Check Curl is installed
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
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {

		// TODO:	Define deactivation functionality here

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

	public function admin_init() {

		/**
		 * The plugins data
		 * @var array
		 */
		$this->plugin_data = get_plugins( '/' . plugin_basename( dirname( __FILE__ ) ) );
		reset( $this->plugin_data );
		/**
		 * The plugin filename
		 * @var string
		 */
		$this->plugin_filename = key( $this->plugin_data );
	}

	/**
	 * Create admin pages
	 */
	function admin_pages() {

		add_options_page( 'Gravity Forms to Solve360 Export', 'Gravity Forms to Solve360 Export', 'manage_options', 'gf-s360-export-options', array( $this, 'options_page' ) );
		add_management_page( 'Gravity Forms to Solve360 Export', 'Gravity Forms to Solve360 Export', 'manage_options', 'gf-s360-export', array( $this, 'management_page' ) );

	} // end admin_pages

	/**
	 * Create options page
	 */
	function options_page() {

		$accepted_fields = array(
			'gf_s360_export_debug_mode',
			'gf_s360_export_start_date',
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
	 * Create management page
	 */
	function management_page() {

		// Set errors and warnings
		if ( ! $this->user ) {
			$this->errors .= $this->admin_notice( 'error', '<strong>Error!</strong> <a href="' . $this->options_url . '">Solve360 user</a> is not set and is required!' );
		}
		if ( ! $this->token ) {
			$this->errors .= $this->admin_notice( 'error', '<strong>Error!</strong> <a href="' . $this->options_url . '">Solve360 token</a> is not set and is required!' );
		}
		if ( ! $this->email_to ) {
			$this->warnings .= $this->admin_notice( 'updated', '<strong>Warning!</strong> <a href="' . $this->options_url . '">To: field</a> for notification emails is not set.' );
		}
		if ( ! $this->email_from ) {
			$this->warnings .= $this->admin_notice( 'updated', '<strong>Warning!</strong> <a href="' . $this->options_url . '">From: field</a> for notification emails is not set.' );
		}

		// Include management view
		require plugin_dir_path( __FILE__ ) . 'views/admin-management.php';

	} // end gtse_export_gravity_data

	/**
	 * When a gform is submitted, Update/Create contact and add Activities if any exist
	 * @param  object $entry The entry that was just created.
	 * @param  object $form  The current form.
	 */
	function form_submission( $entry, $form ) {

		// Serialize $entry object and place contents in file
		$entry = serialize($entry);
		$entry_filename = plugin_dir_path( __FILE__ ) . 'entries/entry-string-' . time() . '.txt';
		file_put_contents( $entry_filename, $entry );

		// Serialize $form object and place contents in file
		$form = serialize($form);
		$form_filename = plugin_dir_path( __FILE__ ) . 'entries/form-string-' . time() . '.txt';
		file_put_contents( $form_filename, $form);

		// $plugin_filename = $this->plugin_filename;

		// Initiate background process
		ini_set('error_reporting', E_ALL);
		$process_file = plugin_dir_path( __FILE__ ) . 'includes/process-form-data.php';
		$background_process = shell_exec( "php $process_file $entry_filename $form_filename " . $this->user . " " . $this->token . " " . $this->contacts_url . " " . $this->debug ." > /dev/null 2>/dev/null &" );

		die('testing...');

	} // end form_submission( $entry, $form )

	/*--------------------------------------------*
	 * Helper Functions
	 *---------------------------------------------*/

	/**
	 * [run_in_background description]
	 * @param  [type]  $command  [description]
	 * @param  integer $priority [description]
	 * @return [type]            [description]
	 */
	public function run_in_background( $command, $priority = 0 ) {

		if ( $priority ) {
			$PID = shell_exec( "nohup nice -n $priority $command 2> /dev/null & echo $!" );
		} else {
			$PID = shell_exec( "nohup $command 2> /dev/null & echo $!" );
		}
		return( $PID );

	} // end run_in_background( $command, $priority = 0 )

	/**
	 * [is_process_running description]
	 * @param  [type]  $PID [description]
	 * @return boolean      [description]
	 */
	public function is_process_running( $PID ) {

		exec( "ps $PID", $ProcessState );
		return( count( $ProcessState ) >= 2 );

	} // end is_process_running( $PID )

	/**
	 * Remove both duplicates form an array
	 * @param  array $array The array to remove duplicates from
	 * @link http://stackoverflow.com/questions/3691369/how-can-i-remove-all-duplicates-from-an-array-in-php Remove both duplicates from an array
	 * @return array        The new array
	 */
	public function remove_duplicates($array) {

		$valueCount = array();
		foreach ($array as $value) {
			$valueCount[$value]++;
		}
		$return = array();
			foreach ($valueCount as $value => $count) {
			if ( $count == 1 ) {
				$return[] = $value;
			}
		}
		return $return;

	} // end remove_duplicates($array)

	/**
	 * Display an admin notice
	 * @param  string $type    accepts updated|error
	 * @param  string $message Admin notice text
	 */
	public function admin_notice( $type, $message ) {

		if ( $type !== 'updated' || $type !== 'error' ) {
			return false;
		}
		echo "<div class='$type'><p>$message</p></div>";

	} // end admin_notice( $type, $message )

} // end class

$gravity_forms_to_solve360_export = new GravityFormsToSolve360Export();
