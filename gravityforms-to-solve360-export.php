<?php

/**
 * @package Gravity Forms to Solve360 Export
 * @subpackage plugin.php
 * @version 0.1
 * @todo Cleanup code
 * @todo Refine process_entries() function
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
	public $entries_dir;
	public $output_dir;
	public $pid_dir;

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
		$this->entries_dir = plugin_dir_path( __FILE__ ) . 'entries';
		$this->output_dir = plugin_dir_path( __FILE__ ) . 'output';
		$this->pid_dir = plugin_dir_path( __FILE__ ) . 'pid';

		if ( ! is_dir( $this->entries_dir ) )
			mkdir( $this->entries_dir );

		if ( ! is_dir( $this->output_dir ) )
			mkdir( $this->output_dir );

		if ( ! is_dir( $this->pid_dir ) )
			mkdir( $this->pid_dir );

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

		// hook onto daily event
		add_action( 'gfs360e_daily_event', array( $this, 'process_entries' ) );

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

		// Schedule an event to occur daily to process unprocessed form entries
		wp_schedule_event( time(), 'daily', 'gfs360e_daily_event' );

	} // end activate

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param boolean $network_wide True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog
	 */
	public function deactivate( $network_wide ) {

		wp_clear_scheduled_hook( 'gfs360e_daily_event' );

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
		add_management_page( 'Gravity Forms to Solve360 Export', 'Gravity Forms to Solve360 Export', 'manage_options', 'gf-s360-export-management', array( $this, 'management_page') );

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

	function management_page() {

		$this->process_entries();

	} // end management_page()

	/**
	 * Run main script as a background process when a form is submitted
	 * @param  object $entry The entry that was just created.
	 * @param  object $form  The current form.
	 */
	function form_submission( $entry, $form ) {

		// Assign variables
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

		$process_file = plugin_dir_path( __FILE__ ) . 'includes/process-form-data.php';
		$date = date('Y-m-d-H:i:s');

		$entries_dir = $this->entries_dir . '/submission';
		if ( ! is_dir( $entries_dir ) )
			mkdir( $entries_dir );
		$entry_file = "$entries_dir/$date.txt";

		$output_dir = $this->output_dir . '/submission';
		if ( ! is_dir( $output_dir ) )
			mkdir( $output_dir );
		$output_file = "$output_dir/$date.txt";

		$pid_dir = $this->pid_dir . '/submission';
		if ( ! is_dir( $pid_dir ) )
			mkdir( $pid_dir );
		$pid_file = "$pid_dir/$date.txt";

		file_put_contents( $entry_file, $args );

		// Pass $entry_file, $output_file and $pid_file as arguments
		$cmd = "php $process_file $entry_file $output_file $pid_file";

		exec( sprintf( '%s > %s 2>&1 & echo $! >> %s', $cmd, $output_file, $pid_file ) );

	} // end form_submission( $entry, $form )

	/**
	 * Loops over /entries/submission/--files-- (aka form entries that haven't been successfully exported to Solve360)
	 * if a submission process is currently running, skip that entry
	 * else re-run process-form-data.php via exec(), print output to /output/cron/ and pid to /pid/cron/ with the current date
	 * if output file is empty, delete it along with the respective pid file
	 */
	function process_entries() {

		$submission_pid_dir = $this->pid_dir . '/submission';
		$submission_output_dir = $this->output_dir . '/submission';
		$submission_entries_dir = $this->entries_dir . '/submission';

		$cron_pid_dir = $this->pid_dir . '/cron';
		$cron_output_dir = $this->output_dir . '/cron';

		if ( ! is_dir( $cron_pid_dir ) )
			mkdir( $cron_pid_dir );

		if ( ! is_dir( $cron_output_dir ) )
			mkdir( $cron_output_dir );

		$process_file = plugin_dir_path( __FILE__ ) . 'includes/process-form-data.php';

		if ( $handle = opendir( $submission_pid_dir ) ) {
			while ( false !== ( $entry = readdir( $handle ) ) ) { // loop through submission pids, while the pid directory is not empty
				if ( $entry != '.' && $entry != '..' ) {

					// if a form submission process isn't running for the entry, re-run process-form-data.php
					if ( ! $this->is_running( file_get_contents( "$submission_pid_dir/$entry" ) ) ) {

						$date = date('Y-m-d-H:i:s');

						// Assign related files to variables
						$submission_entry_file = "$submission_entries_dir/$entry";
						$submission_output_file = "$submission_output_dir/$entry";
						$submission_pid_file = "$submission_pid_dir/$entry";

						// Cron exec() output and pid files
						$cron_output_file = "$cron_output_dir/$date.txt";
						$cron_pid_file = "$cron_pid_dir/$date.txt";

						// Pass $submission_entry_file and associated $submission_output_file and $submission_pid_file as arguments
						$cmd = "php $process_file $submission_entry_file $submission_output_file $submission_pid_file";
						exec( sprintf( '%s > %s 2>&1 & echo $! >> %s', $cmd, $cron_output_file, $cron_pid_file ) );

						if ( 0 == filesize( $cron_output_file ) ) {
							unlink( $cron_output_file );
							unlink( $cron_pid_file );
						}

					}

				}
			}
			closedir( $handle );
		}

	} // end process_entries()

	function is_running( $pid ) {

		try {
			$result = shell_exec( sprintf( "ps %d", $pid ) );
			if ( count( preg_split( "/\n/", $result ) ) > 2 ) {
				return true;
			}
		} catch( Exception $e ) {}

		return false;

	} // end is_running( $pid )

} // end class

$gravity_forms_to_solve360_export = new GravityFormsToSolve360Export();
