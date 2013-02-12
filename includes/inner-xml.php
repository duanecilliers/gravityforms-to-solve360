<?php

	switch ( strtolower($solve_field) ) {

	// ---- Begin Contact Fields ----

		case 'ownership':
			$contact_inner_xml .= "<ownership>$value</ownership>";
			break;
		case 'fullname':
			$firstname = $entry[$field['id']. '.3'];
			$lastname = $entry[$field['id']. '.6'];
			$contact_inner_xml .= "<firstname>$firstname</firstname>";
			$contact_inner_xml .= "<lastname>$lastname</lastname>";
			break;
		case 'jobtitle':
			$contact_inner_xml .= "<jobtitle>$value</jobtitle>";
			break;
		case 'company':
			$contact_inner_xml .= "<company>$value</company>";
			break;
		case 'businessemail':
			$contact_inner_xml .= "<businessemail>$value</businessemail>";
			break;
		case 'businessphonedirect':
			$contact_inner_xml .= "<businessphonedirect>$value</businessphonedirect>";
			break;
		case 'cellularphone':
			$contact_inner_xml .= "<cellularphone>$value</cellularphone>";
			break;
		case 'personalemail':
			$contact_inner_xml .= "<personalemail>$value</personalemail>";
			break;
		case 'otheremail':
			$contact_inner_xml .= "<otheremail>$value</otheremail>";
			break;
		case 'homephone':
			$contact_inner_xml .= "<homephone>$value</homephone>";
			break;
		case 'logo':
			$contact_inner_xml .= "<logo>$value</logo>";
			break;
		case 'assignedto':
			$contact_inner_xml .= "<assignedto>$value</assignedto>";
			break;
		case 'businessphonemain':
			$contact_inner_xml .= "<businessphonemain>$value</businessphonemain>";
			break;
		case 'businessphoneextension':
			$contact_inner_xml .= "<businessphoneextension>$value</businessphoneextension>";
			break;
		case 'businessfax':
			$contact_inner_xml .= "<businessfax>$value</businessfax>";
			break;
		case 'businessaddress':
			$contact_inner_xml .= "<businessaddress>$value</businessaddress>";
			break;
		case 'homeaddress':
			$contact_inner_xml .= "<homeaddress>$value</homeaddress>";
			break;
		case 'relatedto':
			$contact_inner_xml .= "<relatedto>$value</relatedto>";
			break;
		case 'archive':
			$contact_inner_xml .= "<archive>$value</archive>";
			break;
		case 'background':
			$contact_inner_xml .= "<background>$value</background>";
			break;
		case 'category':
			$category_xml .= "<category>$value</category>";
			break;

	// ---- End Contact Fields ----

	// ---- Begin Activity Fields ----

		case 'note' :
			$new_note_array[] = $solve_field_ref . ': ' . $value;
			break;
		case 'website' :
			$new_website_array[] = array( 'caption' => $solve_field_ref, 'url' => $value );
			break;

	// ---- End Activity Fields ----

		default:
			$contact_inner_xml .= '';
			break;
	}
?>
