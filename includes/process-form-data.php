<?php

	/**
	 * @package Gravity Forms to Solve360 Export
	 * @subpackage process-form-data.php
	 * @version 0.1
	 */

	/**
	 * Curl boilerplate to keep code DRY
	 * @param  array $curlopts Curl Options
	 * @return XML        Response
	 */
	function curl_request( $curlopts ) {

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

	} // end curl_request( $curlopts )

	function remove_duplicates($array) {
		$value_count = array();
		foreach ($array as $value) {
			$value_count[$value]++;
		}
		$return = array();
		foreach ($value_count as $value => $count) {
			if ( $count == 1 ) {
				$return[] = $value;
			}
		}
		return $return;
	}

	$filename = $argv[1];
	$args = unserialize( file_get_contents( $filename) );

	$entry = unserialize( $args['entry'] );
	$form = unserialize( $args['form'] );
	$user = $args['user'];
	$token = $args['token'];
	$contacts_url = $args['contacts_url'];
	$email_to = $args['to'];
	$email_from = $args['from'];
	$email_cc = $args['cc'];
	$email_bcc = $args['bcc'];
	$debug = ( $args['debug'] == 'true' ) ? true : false ;
	$message = '';

	// Get form fields
	$fields = $form['fields'];
	$contact_inner_xml = '';
	$category_xml = '';
	$new_note_array = array();

	foreach ( $fields as $field ) {

		// Assign label and value
		if ( $field['type'] == 'hidden' ) {
			$label = ! empty( $field['label'] ) ? $field['label'] : '' ;
			$value = ! empty( $field['defaultValue'] ) ? $field['defaultValue'] : $entry[$field['id']] ;
		} else {
			$label = ! empty( $field['adminLabel'] ) ? $field['adminLabel'] : '' ;
			$value = ! empty( $entry[$field['id']] ) ? $entry[$field['id']] : '' ;
		}

		// Format inner XML
		if ( stripos( $label, 'solve360' ) !== false ) {

			$label_sep = explode(' ', $label, 3);
			$solve_field = isset( $label_sep[1] ) ? $label_sep[1] : '' ;
			$solve_field_ref = isset( $label_sep[2] ) ? $label_sep[2] : '' ;

			if ( strtolower($solve_field) === 'businessemail' ) {
				$businessemail  = $value;
			}

			require dirname( __FILE__ ) . '/inner-xml.php';

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
					CURLOPT_USERPWD => $user .':'. $token,
					CURLOPT_URL => $contacts_url,
					CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
					CURLOPT_CUSTOMREQUEST => 'GET',
					CURLOPT_POSTFIELDS => $search_xml
				);
	$search_results = curl_request( $options );
	if ( $debug ) {
		$message .= '<h2>Contact Search Results:</h2><pre>' . print_r($search_results, true) . '</pre>';
	}

	/**
	 * if there are matching results update contact, otherwise add the contact
	 */
	if ( (integer) $search_results->count >= 1 ) {

		$contact_id = current( $search_results )->id;

		/**
		 * Update contact
		 * @link http://norada.com/norada/crm/external_api_reference_contacts External API Reference Contacts
		 * >> terminal command example below, enter "curl --help" for parameter reference <<
		 * curl -u '{user}:{token}' -X PUT -H 'Content-Type: application/xml' -d '<request><businessemail>{business_email}</businessemail><categories><add><category>{category_id}</category></add></categories></request>' https://secure.solve360.com/contacts/{contact_id}
		 */
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => $user .':'. $token,
			CURLOPT_URL => $contacts_url . "/$contact_id",
			CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
			CURLOPT_POST => true,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_POSTFIELDS => $contact_xml
		);
		$update_response = curl_request( $options );

		if ( $debug ) {
			$message .= '<h2>Contact Exists, Update Results:</h2><pre>' . print_r($update_response, true) . '</pre>';
		}

		if ( $update_response->status == 'success' ) {
			$notification_subject = "Solve360 Contact $firstname $lastname Updated";
			$notification_message = "https://secure.solve360.com/contact/$contact_id";
		} else {
			$notification_subject = "Error Solve360 Contact $firstname $lastname Not Updated";
			$notification_message = print_r($update_response->errors, true);
		}

	}
	else {

		/**
		 * Create Contact using POST
		 * @link http://norada.com/norada/crm/external_api_reference_contacts External API Reference Contacts
		 * >> terminal command example below, enter "curl --help" for parameter reference <<
		 * curl -u '{user}:{token}' -v -X GET -H 'Content-Type: application/xml' -o 'result.xml' -d '<request><layout>1</layout><filtermode>byemail</filtermode><filtervalue>{email}</filtervalue></request>' https://secure.solve360.com/contacts
		 */
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD => $user .':'. $token,
			CURLOPT_URL => $contacts_url,
			CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $contact_xml
		);
		$add_response = curl_request( $options );
		if ( $debug ) {
			$message .= '<h2>Contact doesn\'t exist, Add Results:</h2><pre>' . print_r($add_response, true) . '</pre>';
		}

		$contact_id = ( $add_response->status == 'success' ) ? $add_response->item->id : '' ;

		if ( $add_response->status == 'success' ) {
			$notification_subject = "Solve360 Contact $firstname $lastname Added";
			$notification_message = "https://secure.solve360.com/contact/$contact_id";
		} else {
			$notification_subject = "Error Solve360 Contact $firstname $lastname Not Added";
			$notification_message = print_r($add_response->errors, true);
		}

	}

	// Set email headers
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	// Additional headers
	$headers .= "From: $email_from" . "\r\n";
	$headers .= "CC: $email_cc" . "\r\n";
	$headers .= "BCC: $email_bcc" . "\r\n";

	/**
	 * Email Notifications
	 */
	mail( $email_to, $notification_subject, $notification_message, $headers );
	if ( $update_response->errors || $add_response->errors ) {
		exit(); // Terminate script if there are update or add errors
	}

	/**
	 * If the the contact already exists or was successfully added,
	 * -> Search contact's activities
	 * -> Loop through activities and ...
	 * -> Either by push existing activity types to an array or set an associated variable to true
	 * -> Process Activities
	 * -> | Add a view for linked emails activity if it doesn't already exist
	 * -> | Merge existing notes with new notes, remove all duplicates and post to Solve360
	 */
	if ( $contact_id ) {

		// Search contact Activities
		$options = array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_USERPWD => $user .':'. $token,
					CURLOPT_URL => "$contacts_url/$contact_id",
					CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
					CURLOPT_CUSTOMREQUEST => 'GET'
				);
		$contact_response = curl_request( $options );
		if ( $debug ) {
			$message .= '<h2>Contact search:</h2><pre>' . print_r($contact_response, true) . '</pre>';
		}
		$contact_activities = isset( $contact_response->activities ) ? $contact_response->activities : '' ;

		if ( $contact_activities ) {

			if ( $debug ) {
				$message .= '<h2>Contact Activities:</h2><pre>' . print_r( $contact_activities, true ) . '</pre>';
			}

			$existing_note_array = array();
			$merged_note_array = array();

			// Loop through current contact's activities
			foreach ( $contact_activities as $activities ) {

				foreach ( $activities as $activity ) {

					if ( $debug ) {
						$message .= "<h2>Actvity:</h2><pre>" . print_r($activity, true) . '</pre>';
						$message .= "<h2>typeid:</h2> " . $activity->typeid;
					}

					// Either by push existing activity types to an array or set an associated variable to true
					switch ($activity->typeid) {
						case 90:
							$has_linkedemails = true;
							break;
						case 3:
							$has_note = true;
							array_push( $existing_note_array, (string) $activity->name );
							break;
						default:
							# code...
							break;
					} // end switch

				} // end foreach ( $activities as $activity )

			} // end foreach ( $contact_activities as $activity )

		} // end if ( $contact_activities )

		/**
		 * Process Activities
		 */

		// Add a view for linked emails activity if it doesn't already exist
		if ( ! $has_linkedemails ) {
			$linkedemails_xml = "<request><parent>$contact_id</parent></request>";
			$options = array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_USERPWD => $user .':'. $token,
				CURLOPT_URL => $contacts_url . '/linkedemails',
				CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => $linkedemails_xml
			);
			$linked_emails_response = curl_request( $options );
			if ( $debug ) {
				$message .='<h2>linkedemails response:</h2><pre>' . print_r($linked_emails_response, true) . '</pre>';
			}
		} // end if ( ! $has_linkedemails )

		/**
		 * Merge existing notes with new notes, remove duplicates and post to Solve360
		 */
		if ( $new_note_array ) {

			if ( $debug ) {
				$message .= "<h2>New Note Array</h2><pre>" . print_r($new_note_array, true) . '</pre>';
			}

			if ( $existing_note_array ) {

				if ($debug) {
					$message .= '<h2>Existing Note Array</h2><pre>' . print_r($existing_note_array, true) . '</pre>';
				}

				$combined_note_array = array_merge( $new_note_array, $existing_note_array );
				if ($debug) {
					$message .= '<h2>Combined Note Array</h2><pre>' . print_r($combined_note_array, true) . '</pre>';
				}

				/**
				 * Remove all duplicate array elements
				 * http://stackoverflow.com/questions/3691369/how-can-i-remove-all-duplicates-from-an-array-in-php
				 */
				$merged_note_array = remove_duplicates( $combined_note_array );

				// Remove existing array values from merged array
				foreach ( $existing_note_array as $key => $value ) {
					if ( ( $key = array_search( $value, $merged_note_array ) ) !== false ) {
						unset( $merged_note_array[$key] );
					}
				}

			} else {
				$merged_note_array = $new_note_array;
			}

			if ( $debug ) {
				$message .= "<h2>Merged Note Array</h2><pre>" . print_r($merged_note_array, true) . "</pre>";
			}

			// Post Notes to Solve360
			if ( count($merged_note_array) > 0 ) {
				foreach ( $merged_note_array as $note ) {
					$options = array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD => $user .':'. $token,
						CURLOPT_URL => $contacts_url . '/note',
						CURLOPT_HTTPHEADER => array('Content-Type: application/xml'),
						CURLOPT_POST => true,
						CURLOPT_POSTFIELDS => "<request><parent>$contact_id</parent><data><details>$note</details></data></request>"
					);
					$note_response = curl_request( $options );
					if ( $debug ) {
						echo '<h2>Note Activity response:</h2><pre>' . print_r($note_response, true) . '</pre>';
					}
				}
			} // end if ( count($merged_note_array) > 0 )

		} // end if ( $new_note_array )

	} // end if ( $contact_id )

	/**
	 * Delete entry and form objects files
	 */
	unlink( $filename );

	/**
	 * Debug with email as the script runs in the background, output can't be seen on screen
	 */

	if ( $debug ) {
		// To send HTML mail, the Content-type header must be set

		$debug_entry = print_r( $entry, true );
		$debug_form = print_r( $form, true );

		$message .= "<h2>Entry: </h2><pre>$debug_entry</pre>";
		$message .= "<h2>Form: </h2><pre>$debug_form</pre>";

		// Mail it
		mail( 'duane@signpost.co.za', 'Debug Responses', $message, $headers);
	}


?>
