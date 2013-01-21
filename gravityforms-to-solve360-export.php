<?php

/**
 * @package Gravity Forms to Solve360 Export
 * @subpackage plugin.php
 * @version 0.1
 * @todo Search labels for activities and add the activity with contact ID (first check if the activity exists, update if it does, otherwise add it)
 * @todo Investigate when 'ownership' is required
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

		// Get form fields
		$fields = $form['fields'];
		$contact_inner_xml = '';
		$category_xml = '';
		$new_note_array = array();

		foreach ( $fields as $field ) {

			// Assign label and value
			if ( $field['type'] === 'hidden' ) {
				$label = isset( $field['label'] ) ? $field['label'] : '' ;
				$value = isset( $field['defaultValue'] ) ? $field['defaultValue'] : '' ;
			} else {
				$label = isset( $field['adminLabel'] ) ? $field['adminLabel'] : '' ;
				$value = isset( $entry[$field['id']] ) ? $entry[$field['id']] : '' ;
			}

			// Format inner XML
			if ( stripos( $label, 'solve360' ) !== false ) {

				$label_sep = explode(' ', $label, 3);
				$solve_field = isset( $label_sep[1] ) ? $label_sep[1] : '' ;
				$solve_field_ref = isset( $label_sep[2] ) ? $label_sep[2] : '' ;

				if ( strtolower($solve_field) === 'businessemail' ) {
					$businessemail  = $value;
				}

				require plugin_dir_path( __FILE__ ) . 'includes/inner-xml.php';

			} // end if ( stripos( $label, 'solve360' ) !== false )

		} // end foreach ( $fields as $field )

		$contact_inner_xml .= "<categories><add>$category_xml</add></categories>";

		/**
		 * Setup the contact's XML string
		 * EG: <name>firstname</name> when viewing the link below would translate to <firstname> in the $xml string.
		 * @link https://secure.solve360.com/contacts/fields/ Available Fields
		 * @var string
		 */
		$contact_xml = "<request>$contact_inner_xml</request>";

		if ( $this->debug ) {
			echo '<h2>Contact XML</h2>';
			echo $contact_xml;
		}

		/**
		 * Search Contacts for any matching email address using GET
		 * @link http://norada.com/norada/crm/external_api_reference_contacts External API Reference Contacts
		 * >> terminal command example below, enter "curl --help" for parameter reference <<
		 * curl -u '{user}:{token}' -v -X GET -H 'Content-Type: application/xml' -o 'result.xml' -d '<request><layout>1</layout><filtermode>byemail</filtermode><filtervalue>{email}</filtervalue></request>' https://secure.solve360.com/contacts
		 */

		$search_xml = "<request>
							<layout>1</layout>
							<filtermode>byemail</filtermode>
							<filtervalue>$businessemail</filtervalue>
						</request>";
		$options = array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD => $this->user .':'. $this->token,
						CURLOPT_URL => $this->contacts_url,
						CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
						CURLOPT_CUSTOMREQUEST => 'GET',
						CURLOPT_POSTFIELDS => $search_xml
					);
		$search_results = $this->solve360_api_request( $options );
		if ( $this->debug ) {
			echo '<h2>Search Results:</h2><pre>';
			print_r($search_results);
			echo '</pre>';
		}

		/**
		 * if there are matching results update contact, otherwise add the contact
		 */
		if ( (integer) $search_results->count >= 1 ) {

			$contact_id = current( $search_results )->id;

			echo ( $this->debug ) ? "<h2>Updating Contact</h2>" : '' ;

			/**
			 * Update contact
			 * @link http://norada.com/norada/crm/external_api_reference_contacts External API Reference Contacts
			 * >> terminal command example below, enter "curl --help" for parameter reference <<
			 * curl -u '{user}:{token}' -X PUT -H 'Content-Type: application/xml' -d '<request><businessemail>{business_email}</businessemail><categories><add><category>{category_id}</category></add></categories></request>' https://secure.solve360.com/contacts/{contact_id}
			 */
			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERPWD => $this->user .':'. $this->token,
				CURLOPT_URL => $this->contacts_url . "/$contact_id",
				CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
				CURLOPT_POST => true,
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_POSTFIELDS => $contact_xml
			);
			$update_response = $this->solve360_api_request( $options );
			if ( $this->debug ) {
				echo '<h2>Update Results:</h2><pre>';
				print_r($update_response);
				echo '</pre>';
			}

		}
		else {

			echo ( $this->debug ) ? "<h2>Adding Contact</h2>" : '' ;

			/**
			 * Create Contact using POST
			 * @link http://norada.com/norada/crm/external_api_reference_contacts External API Reference Contacts
			 * >> terminal command example below, enter "curl --help" for parameter reference <<
			 * curl -u '{user}:{token}' -v -X GET -H 'Content-Type: application/xml' -o 'result.xml' -d '<request><layout>1</layout><filtermode>byemail</filtermode><filtervalue>{email}</filtervalue></request>' https://secure.solve360.com/contacts
			 */
			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERPWD => $this->user .':'. $this->token,
				CURLOPT_URL => $this->contacts_url,
				CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $contact_xml
			);
			$add_response = $this->solve360_api_request( $options );
			if ( $this->debug ) {
				echo '<h2>Add Results:</h2><pre>';
				print_r($add_response);
				echo '</pre>';
			}

			$contact_id = ( $add_response->status == 'success' ) ? $add_response->item->id : '' ;

		}

		/**
		 * Add Activities
		 */
		if ( $contact_id ) {

			// Search contact Activities
			$options = array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD => $this->user .':'. $this->token,
						CURLOPT_URL => "$this->contacts_url/$contact_id",
						CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
						CURLOPT_CUSTOMREQUEST => 'GET'
					);
			$contact_response = $this->solve360_api_request( $options );
			if ( $this->debug ) {
				echo '<h2>Contact Response:</h2><pre>';
				print_r($contact_response);
				echo '</pre>';
			}
			$contact_activities = isset( $contact_response->activities ) ? $contact_response->activities : '' ;

			if ( $contact_activities ) {

				$note_array = array();

				foreach ( $contact_activities as $activity ) {
					switch ($activity->typeid) {
						case 1:
							$has_linkedemails = true;
							break;
						case 3:
							$has_note = true;
							$existing_note_array[] = $activity->name;
							break;
						default:
							# code...
							break;
					}

				} // end foreach ( $contact_activities as $activity )

			} // end if ( $contact_activities )

			// Add a view for linked emails activity if it doesn't already exist
			if ( ! $has_linkedemails ) {
				$linkedemails_xml = "<request><parent>$contact_id</parent></request>";
				$options = array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_USERPWD => $this->user .':'. $this->token,
					CURLOPT_URL => $this->contacts_url . '/linkedemails',
					CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => $linkedemails_xml
				);
				$linked_emails_response = $this->solve360_api_request( $options );
				if ( $this->debug ) {
					echo '<h2>linkedemails response:</h2><pre>';
					print_r($linked_emails_response);
					echo '</pre>';
				}
			} // end if ( ! $has_linkedemails )

			// Check if Activity Note exists. Update
			if ( $new_note_array ) {

				if ( $existing_note_array ) {
					$combined_note_array = array_merge( $new_note_array, $existing_note_array );
					$note_array = $this->remove_duplicates( $combined_note_array );
				} else {
					$note_array = $new_note_array;
				}

				echo 'Note array: <pre>';
					print_r($note_array);
					echo '</pre>';

				foreach ( $note_array as $note ) {
					$options = array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD => $this->user .':'. $this->token,
						CURLOPT_URL => $this->contacts_url . '/note',
						CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
						CURLOPT_POST => true,
						CURLOPT_POSTFIELDS => "<request><parent>$contact_id</parent><data><details>$note</details></data></request>"
					);
					$note_response = $this->solve360_api_request( $options );
					if ( $this->debug ) {
						echo '<h2>Note Activity response:</h2><pre>';
						print_r($note_response);
						echo '</pre>';
					}
				}

			}

		}

		echo "continuing<br>";

		echo '<pre>';
		print_r($entry);
		echo '</pre>';

		echo '<pre>';
		print_r($form);
		echo '</pre>';

		die();

	}

	/*--------------------------------------------*
	 * Helper Functions
	 *---------------------------------------------*/

	/**
	 * Remove both duplicates form an array
	 * @param  array $array The array to remove duplicates from
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

	}

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

	} // end admin_notice

	public function solve360_api_request( $curlopts ) {

		$ch = curl_init();
		curl_setopt_array( $ch, $curlopts );
		// Check if any error occured
		if ( curl_errno( $ch ) ) {
			echo 'Curl error: ' . curl_error($ch);
		}
		// Send the request & save response to $resp
		$resp = curl_exec($ch);
		$xml_response = new SimpleXmlElement($resp);
		// Close request to clear up some resources
		curl_close($ch);
		return $xml_response;

	}

} // end class

$gravity_forms_to_solve360_export = new GravityFormsToSolve360Export();
