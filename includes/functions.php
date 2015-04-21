<?php

/*  Copyright 2013 CURE International  (email : info@cure.org)



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



/**

 * Ajax call to process donation using Authorize.Net 

 */

 

function pfund_auth_net_donation() {	

    $campaign_id = $_POST['post_id'];

    $gentime = $_POST['g'];    

    $msg = array();

    if ( wp_verify_nonce( $_POST['n'],  'pfund-donate-campaign'.$campaign_id.$gentime ) ) {

        $post = get_post( $campaign_id );

        $transaction_array = pfund_process_authorize_net();            

        if ($transaction_array['success']) {

            pfund_add_gift( $transaction_array, $post ); 

            $msg['success'] = true;

        } else {

            $msg['success'] = false;

            $msg['error'] = $transaction_array['error_msg'];

        }	

    } else {

        $msg['success'] = false;

        $msg['error'] =  __( 'You are not permitted to perform this action.', 'pfund' );        

    }

	echo json_encode($msg);

	die();

}



/**

 * Convert the passed in date to iso8601 (YYYY-MM-DD) format.

 * @param string $date date to convert.

 * @param string $format current format of date.

 * @return string date in iso8601 format.

 */

function pfund_date_to_iso8601( $date, $format ) {

	if( class_exists( 'DateTime' ) && method_exists( 'DateTime', 'createFromFormat' ) ) {

		$date = DateTime::createFromFormat( $format, $date );

		if ( $date ) {

			return $date->format( 'Y-m-d' );

		} else {

			return "";

		}

	} else {

		$date_map = array(

			'y'=>'year',

			'Y'=>'year',

			'm'=>'month',

			'n'=>'month',

			'd'=>'day',

			'j'=>'day'

		);

		$date_array = array(

			'error_count' => 0,

			'errors' => array()

		);



		$format = preg_split( '//', $format, -1, PREG_SPLIT_NO_EMPTY );

		$date = preg_split( '//', $date, -1, PREG_SPLIT_NO_EMPTY );

		$format_frag = $format[0];

		$format_idx = 0;

		$error_msg = null;



		foreach ( $date as $idx => $date_frag ) {

			if ( ! ctype_digit( $date_frag ) ) {

				$format_idx++;

				if ( !isset( $format[$format_idx] ) ) {

					$error_msg = 'An unexpected separator was encountered';

				} else {

					$format_frag = $format[$format_idx];

					if ( $date_frag != $format_frag ) {

						$error_msg = 'An unexpected separator was encountered';

					} else {

						$format_idx++;

						if ( ! isset( $format[$format_idx] ) ) {

							$error_msg = 'An unexpected character was encountered';

						} else {

							$format_frag = $format[$format_idx];

						}

					}

				}

				if ( isset( $error_msg ) ) {

					$date_array['error_count']++;

					$date_array['errors'][$idx] = $error_msg;

					break;

				}

			} else {

				$date_key = $date_map[$format_frag];

				if ( !isset( $date_array[$date_key] ) ) {

					$date_array[$date_key] = $date_frag;

				} else {

					$date_array[$date_key] .= $date_frag;

				}

			}



		}

		if ( isset( $date_array['month'] ) && isset( $date_array['day'] )

				&&  isset( $date_array['year'] ) )  {

			$gmttime = gmmktime( 0, 0, 0, $date_array['month'], $date_array['day'], $date_array['year'] );

			return gmdate( 'Y-m-d', $gmttime );

		} else {

			return '';

		}

	}

}



/**

 * Determine the short code for a specific personal fundraiser field.

 * @param string $id The id of the field.

 * @param string $type The type of field.

 * @return string the corresponding shortcode.

 */

function pfund_determine_shortcode( $id, $type = '' ) {

	$scode = '[pfund-'.$id;





	if ( $type == 'fixed' ) {

		$scode .= ' value="?"';

	}

	$scode .= ']';

	return $scode;

}



/**

 * Determine and return the proper location of the specified file.  This

 * function allows the use of .dev files when debugging.

 * @param string $name The name of the file, not including directory and

 * extension.

 * @param string $type The type of file.  Valid values are js or css.

 * @return string the file location to use.

 */

function pfund_determine_file_location( $name, $type ) {

	$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

	return PFUND_URL."$type/$name$suffix.$type";

}



/**

 * If the option is set to use ssl for campaigns, redirect campaign pages to 

 * secure.

 */

function pfund_force_ssl_for_campaign_pages() {

	global $post;     

    if ( ! is_admin() && $post && $post->post_type == 'pfund_campaign' ) {

        $options = get_option( 'pfund_options' ); 

        if ( ! empty ( $options['use_ssl'] ) && ! is_ssl() ) {

            $ssl_redirect = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

            $ssl_redirect = apply_filters( 'pfund_ssl_campaign_location', $ssl_redirect );

            wp_redirect( $ssl_redirect, 301 );

	   		exit();

	   	}

	}

}



/**

 * Filter to campaigns to use content from the cause they where created from.

 * @param string $content The current post content

 * @return string The cause content if the post is a personal fundraiser

 * campaign; otherwise return the content unmodified.

 */

function pfund_handle_content( $content ) {

	global $post, $pfund_update_message;

	if ( $post->post_type == 'teamcampaigns' ) {

		$causeid = get_post_meta( $post->ID, '_pfund_cause_id', true ) ;

		$cause = get_post( $post->ID );

		return $cause->post_content.$pfund_update_message;

	}

	if( $post->ID == null || ! pfund_is_pfund_post() ) {

		return $content;

	} 

	else if ( $post->post_type == 'pfund_campaign' ) {

		$causeid = get_post_meta( $post->ID, '_pfund_cause_id', true ) ;

		$cause = get_post( $causeid );

		return $cause->post_content.$pfund_update_message;

	} else if ( $post->post_type == 'pfund_cause' ) {

		return $post->post_content.$pfund_update_message;

	}

}



/**

 * Determine if current post is a personal fundraiser post type.

 * @param mixed $post_to_check the post to check.  If this is not passed, the

 * global $post object is used.

 * @param boolean $include_lists flag to indicate if the list post_types should

 * be checked as well.

 * @return boolean true if the current post is a personal fundraiser post type;

 * otherwise return false.

 */

function pfund_is_pfund_post( $post_to_check = false, $include_lists = false ) {

	if ( ! $post_to_check ) {

		global $post;

		$post_to_check = $post;

	}

	$pfund_post_types = array( 'pfund_cause', 'pfund_campaign' );

	if ( $include_lists ) {

		$pfund_post_types[] = 'pfund_cause_list';

		$pfund_post_types[] = 'pfund_campaign_list';

	}

	if( $post_to_check && $post_to_check->ID != null && in_array( $post_to_check->post_type, $pfund_post_types ) ) {

		return true;

	} else {

		return false;

	}

}



/**

 * Process an Authorize.Net donation.

 * @return array with the following keys:

 *   success -- boolean indicating if transaction was successful.

 *   amount -- Transaction amount

 *   donor_first_name -- Donor first name

 *   donor_last_name -- Donor last name

 *   donor_email -- Donor email

 *   error_code -- When an error occurs, one of the following values is returned:

 *		no_response_returned -- A response was not received from PayPal.

 *		auth_net_failure -- PayPal returned a failure.

 *		wp_error -- A WP error was returned.

 *		exception_encountered -- An unexpected exception was encountered.

 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.

 *	 error_msg -- Text message describing error encountered.

 */

function pfund_process_authorize_net() {

	$return_array = array( 'success' => false );

	if ( ! (int)$_POST['cc_num'] || ! (int)$_POST['cc_amount'] || ! $_POST['cc_email'] || ! $_POST['cc_first_name']



		 || ! $_POST['cc_last_name'] || ! $_POST['cc_address'] || ! $_POST['cc_city'] || ! $_POST['cc_zip']) {

		if ( ! (int)$_POST['cc_num']) {

			$return_array['error_msg'] = __( 'Error: Please enter a valid Credit Card number.', 'pfund' );

		} elseif ( ! (int)$_POST['cc_amount']) {

			$return_array['error_msg'] = __( 'Error: Please enter a donation amount.', 'pfund' );

		} elseif ( ! $_POST['cc_email']) {

			$return_array['error_msg'] = __( 'Error: Please enter a valid email address.', 'pfund' );

		} elseif ( ! $_POST['cc_first_name']) {

			$return_array['error_msg'] = __( 'Error: Please enter your first name.', 'pfund' );

		} elseif ( ! $_POST['cc_last_name']) {

			$return_array['error_msg'] = __( 'Error: Please enter your last name.', 'pfund' );

		} elseif ( ! $_POST['cc_address']) {

			$return_array['error_msg'] = __( 'Error: Please enter your address.', 'pfund' );

		} elseif ( ! $_POST['cc_city']) {

			$return_array['error_msg'] = __( 'Error: Please enter your city.', 'pfund' );

		} elseif ( ! $_POST['cc_zip']) {

			$return_array['error_msg'] = __( 'Error: Please enter your zip code.', 'pfund' );

		}

		return $return_array;

	}

	

	//process Authorize.Net donation

	require('AuthnetAIM.class.php');

	 

	try {

		$pfund_options = get_option('pfund_options');

	    $email   = $_POST['cc_email'];

	    $product = ($pfund_options['authorize_net_product_name'] !='') ? $pfund_options['authorize_net_product_name'] : 'Donation';

	    $firstname = $_POST['cc_first_name'];

	    $lastname  = $_POST['cc_last_name'];

	    $address   = $_POST['cc_address'];

	    $city      = $_POST['cc_city'];

	    $state     = $_POST['cc_state'];

	    $zipcode   = $_POST['cc_zip'];

	 	    

	    $creditcard = $_POST['cc_num'];

	    $expiration = $_POST['cc_exp_month'] . '-' . $_POST['cc_exp_year'];

	    $total      = $_POST['cc_amount'];

	    $cvv        = $_POST['cc_cvv2'];

	    $invoice    = substr(time(), 0, 6);	    

	    

	    

	    $api_login = $pfund_options['authorize_net_api_login_id'];

	    $transaction_key = $pfund_options['authorize_net_transaction_key']; 

	 

	    $payment = new AuthnetAIM( $api_login, $transaction_key, ( $pfund_options['authorize_net_test_mode']==1 ) ? true : false );



	    $payment->setTransaction($creditcard, $expiration, $total, $cvv, $invoice);

	    $payment->setParameter("x_duplicate_window", 180);

	    $payment->setParameter("x_customer_ip", $_SERVER['REMOTE_ADDR']);

	    $payment->setParameter("x_email", $email);

	    $payment->setParameter("x_email_customer", FALSE);

	    $payment->setParameter("x_first_name", $firstname);

	    $payment->setParameter("x_last_name", $lastname);

	    $payment->setParameter("x_address", $address);

	    $payment->setParameter("x_city", $city);

	    $payment->setParameter("x_state", $state);

	    $payment->setParameter("x_zip", $zipcode);

	    $payment->setParameter("x_description", $product);



	    $payment->process();

	 

	    if ($payment->isApproved())  {

			// if success, return array

			$return_array['amount'] = $total;

			$return_array['donor_email'] = $email;



			if ( isset( $_POST['anonymous'] ) && $_POST['anonymous']==1) {

				$return_array['anonymous'] = true;

			} else {

				$return_array['donor_first_name'] = $firstname;

				$return_array['donor_last_name'] = $lastname;

			}

			$return_array['transaction_nonce'] = $_POST['n'];

			$return_array['success'] = true;



	    } else if ($payment->isDeclined()) {

	        // Get reason for the decline from the bank. This always says,

	        // "This credit card has been declined". Not very useful.

	        $reason = $payment->getResponseText();	 

	        $return_array['error_msg'] = __( 'This credit card has been declined.  Please use another form of payment.', 'pfund' );

	    } else if ($payment->isError()) {	 

	        // Capture a detailed error message. No need to refer to the manual

	        // with this one as it tells you everything the manual does.

	        $return_array['error_msg'] =  $payment->getResponseMessage();

	 

	        // We can tell what kind of error it is and handle it appropriately.

	        if ($payment->isConfigError()) {

	            // We misconfigured something on our end.

	            //$return_array['error_msg'] .= " Please notify the webmaster of this error.";

	        } else if ($payment->isTempError()) {

	            // Some kind of temporary error on Authorize.Net's end. 

	            // It should work properly "soon".

	            $return_array['error_msg'] .= __( '  Please try your donation again.', 'pfund' );

	        } else {

	            // All other errors.

	        }

	 

	    }

	} catch (AuthnetAIMException $e) {

	    $return_array['error_msg'] = sprintf( __( 'There was an error processing the transaction. Here is the error message: %s', 'pfund' ),  $e->__toString() );

	}

	return $return_array;

}



/**

 * Render the input fields for the personal fundraising fields.

 * @param int $postid The id of the campaign that is being edited.

 * @param string $campaign_title The title of the campaign being edited.

 * @param boolean $editing_campaign true if campaign is being edited;false if new campaign.

 * Defaults to true.

 * @param string $default_goal default goal for campaign.  Defaults to empty.

 * @return string The HTML for the input fields.

 */

 

 /**/

function pfund_render_fields1($postid, $campaign_title, $editing_campaign = true, $default_goal = ''){

	global $current_user, $post; 

	$options = get_option( 'pfund_options' );

	unset($options['fields']['first-name']);

	unset($options['fields']['last-name']);

	$inputfields = array();

	$matches = array();

	$result = preg_match_all( '/'.get_shortcode_regex().'/s', pfund_handle_content( $post->post_content ), $matches );

	$tags = $matches[2];

	$attrs = $matches[3];

	if ( is_admin() ) {

		$render_type = 'admin';

		if ( isset( $options['fields'] ) ) {

			foreach ( $options['fields'] as $field_id => $field ) {

				$field_value = get_post_meta( $postid, '_pfund_'.$field_id, true );

				$inputfields['pfund-'.$field_id] = array(

					'field' => $field,

					'value' => $field_value

				);

			}

			$content_idx = array_search('pfund-'.$field_id, $tags);

			if ( $content_idx !== false ){

				$inputfields['pfund-'.$field_id]['attrs'] = $attrs[$content_idx];

			}

		}

		$content = '';

	} else {

		$render_type = 'user';

		get_currentuserinfo();

		$inputfields = array();

		foreach( $tags as $idx => $tag ) {

			if ( $tag == 'pfund-days-left' ) {

				$tag = 'pfund-end-date';

			}

			$field_id = substr( $tag, 6 );			

			$field_value = get_post_meta( $postid, '_pfund_'.$field_id, true );

			if ( isset( $options['fields'][$field_id] ) ) {

				$inputfields[$tag] = array(

					'field' => $options['fields'][$field_id],

					'attrs' => $attrs[$idx],

					'value' => $field_value

				);

			}

		}

		$content = '<ul class="pfund-list">';

	}



	if ( ! isset( $inputfields['pfund-camp-title'] ) ) {

		$inputfields['pfund-camp-title'] = array(

			'field' => $options['fields']['camp-title'],

			'value' => $campaign_title

		);

	}



	if ( ! isset( $inputfields['pfund-camp-location'] ) ) {

		$inputfields['pfund-camp-location'] = array(

			'field' => $options['fields']['camp-location']

		);

	}



	if ( ! isset( $inputfields['pfund-gift-goal'] ) ) {

		$current_goal = get_post_meta( $postid, '_pfund_gift-goal', true );

		$inputfields['pfund-gift-goal'] = array(

			'field' => $options['fields']['gift-goal'],

			'value' => $current_goal

		);

	}

	if ( empty( $inputfields['pfund-gift-goal']['value'] ) ) {

		

		$inputfields['pfund-gift-goal']['value'] = $default_goal;

	}



	if ( ! isset( $inputfields['pfund-gift-tally'] ) ) {
		
		$current_tally = get_post_meta( $postid, '_pfund_gift-tally', true );
		//added 03-02-2015
		if($post->post_type == 'teamcampaigns'){
			$team_title = get_the_title( $postid );
	$personal_ids 	= $wpdb->get_results('SELECT post_id FROM '.$wpdb->prefix.'postmeta WHERE meta_value = "'.$team_title.'"' );
	$campaign_tally = 0;
	foreach($personal_ids as $personal_id){
		echo $campaign_tally += get_post_meta($personal_id->post_id,'_pfund_gift-tally',true);
		}
		$current_tally += $current_tally + $campaign_tally;
			}
			//end editing

		$inputfields['pfund-gift-tally'] = array(

			'field' => $options['fields']['gift-tally'],

			'value' => $current_tally

		);

	}



	uasort( $inputfields, '_pfund_sort_fields' );

	$hidden_inputs = '';

	$field_idx = 0;

	foreach( $inputfields as $tag => $field_data ) {

		$field = $field_data['field'];

		$value = pfund_get_value( $field_data, 'value' );



		$field_options = array(			

			'name' => $tag,

			'desc' => pfund_get_value( $field, 'desc' ),

			'label' => pfund_get_value( $field, 'label' ),

			'value' => $value,

			'render_type' => $render_type,

			'field_count' => $field_idx,

			'required' => pfund_get_value( $field, 'required', false )

		);

		if ( isset( $field_data['attrs'] ) ) {

			$field_options['attrs']= shortcode_parse_atts( $field_data['attrs'] );

		}

		switch ( $field['type'] ) {

			case 'camp_title':

				if ( ! is_admin() ) {

					$field_options['value'] = $campaign_title;

					$content .= _pfund_render_text_field( $field_options );					

					$field_idx++;

				}

				break;

			case 'camp_location':

				if ( ! is_admin() ) {

					if ( $editing_campaign ) {

						require_once( ABSPATH . 'wp-admin/includes/post.php' );

						list( $permalink, $post_name ) = get_sample_permalink( $postid );

					} else {

						$post_name = '';

					}

					$field_options['custom_validation'] = 'ajax[pfundSlug]';

					$field_options['value'] = $post_name;

					$field_options['pre_input'] = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['teamcampaigns_slug'] );					

					$content .= _pfund_render_text_field( $field_options );

					$field_idx++;

				}

				break;

			case 'fixed':

			case 'gift_tally':

				if ( is_admin() ) {

					$content .= _pfund_render_text_field( $field_options );

				} else if ( $field['type'] == 'fixed' ) {

					$attr = shortcode_parse_atts( $field_data['attrs'] );

					$hidden_inputs .= '	<input type="hidden" name="'.$tag.'" value="'.$attr["value"].'"/>';

				}

				break;

			case 'end_date':

			case 'date':

				$field_options['class'] = 'pfund-date';

				$field_options['value'] = pfund_format_date( 

						$field_options['value'],  

						$options['date_format']

				);

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'giver_tally':

				if ( is_admin() ) {

					$content .= _pfund_render_text_field( $field_options );

					$field_idx++;					

				}

				break;

			case 'user_goal':

				$field_options['custom_validation'] = 'custom[onlyNumber]';

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'text':

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'textarea':

                $field_options['class'] = 'pfund-textarea';

                $field_options = _pfund_add_validation_class($field_options);

				$field_content = '<textarea class="'.$field_options['class'].'" id="'.$tag.'" name="'.$tag.'" rows="10" cols="50" type="textarea">'.$value.'</textarea>';

				$content .= pfund_render_field_list_item( $field_content, $field_options);

				$field_idx++;

				break;

			case 'image':

                if ( ( isset( $field_options['required'] ) && $field_options['required'] ) ) {                

                    $field_options['custom_validation'] = 'funcCall[requiredFile]';

                }

				$content .= _pfund_render_image_field( $field_options );

				$field_idx++;

				break;

			case 'select':

				$field_content = pfund_render_select_field( $field['data'], $tag, $value );

				$content .= pfund_render_field_list_item( $field_content, $field_options );

				$field_idx++;

				break;

			case 'user_email':

				if ( empty ( $value ) && !is_admin() ) {

					$value = $current_user->user_email;

					$field_options['value'] = $value;

				}

				$field_options['custom_validation'] = 'custom[email]';

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'user_displayname':

				if ( empty ($value) && !is_admin() ) {

					$value = $current_user->display_name;

					$field_options['value'] = $value;

				}				

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			default:

				$content .= apply_filters( 'pfund_'.$field['type'].'_input', $field_options );

				$field_idx++;

		}

	}

	if ( ! is_admin() ) {

		$content .= '</ul>';

	}

	$content .= $hidden_inputs;

	//print_r($content);die();

	return $content;



} 

 /**/

function pfund_render_fields( $postid, $campaign_title, $editing_campaign = true, $default_goal = '' ) {

	global $current_user, $post;

	$options = get_option( 'pfund_options' );

	//echo '<pre>';

	//print_r($options);

	$inputfields = array();

	$matches = array();

	$result = preg_match_all( '/'.get_shortcode_regex().'/s', pfund_handle_content( $post->post_content ), $matches );



	$tags = $matches[2];

	$attrs = $matches[3];

	if ( is_admin() ) {

		$render_type = 'admin';

		if ( isset( $options['fields'] ) ) {

			foreach ( $options['fields'] as $field_id => $field ) {

				$field_value = get_post_meta( $postid, '_pfund_'.$field_id, true );

				$inputfields['pfund-'.$field_id] = array(

					'field' => $field,

					'value' => $field_value

				);

			}

			$content_idx = array_search('pfund-'.$field_id, $tags);

			if ( $content_idx !== false ){

				$inputfields['pfund-'.$field_id]['attrs'] = $attrs[$content_idx];

			}

		}

		$content = '';

	} else {

		$render_type = 'user';

		get_currentuserinfo();

		$inputfields = array();

		foreach( $tags as $idx => $tag ) {

			if ( $tag == 'pfund-days-left' ) {

				$tag = 'pfund-end-date';

			}

			$field_id = substr( $tag, 6 );			

			$field_value = get_post_meta( $postid, '_pfund_'.$field_id, true );

			if ( isset( $options['fields'][$field_id] ) ) {

				$inputfields[$tag] = array(

					'field' => $options['fields'][$field_id],

					'attrs' => $attrs[$idx],

					'value' => $field_value

				);

			}

		}

		$content = '<ul class="pfund-list">';

	}



	/*if ( ! isset( $inputfields['pfund-camp-title'] ) ) {

		$inputfields['pfund-camp-title'] = array(

			'field' => $options['fields']['camp-title'],

			'value' => $campaign_title

		);

	}*/

	/*if (! is_admin() ) {

		if ( ! isset( $inputfields['first-name'] ) ) {

			$inputfields['first-name'] = array(

				'field' => $options['fields']['first-name'],

				'value' => $current_user->user_firstname

			);

		}

		if ( ! isset( $inputfields['last-name'] ) ) {

			$inputfields['last-name'] = array(

				'field' => $options['fields']['last-name'],

				'value' => $current_user->user_lastname

			);

		}

	}*/

	if ( ! isset( $inputfields['pfund-camp-location'] ) ) {

		$inputfields['pfund-camp-location'] = array(

			'field' => $options['fields']['camp-location']

		);

	}



	if ( ! isset( $inputfields['pfund-gift-goal'] ) ) {

		$current_goal = get_post_meta( $postid, '_pfund_gift-goal', true );

		$inputfields['pfund-gift-goal'] = array(

			'field' => $options['fields']['gift-goal'],

			'value' => $current_goal

		);

	}

	if ( empty( $inputfields['pfund-gift-goal']['value'] ) ) {

		

		$inputfields['pfund-gift-goal']['value'] = $default_goal;

	}



	if ( ! isset( $inputfields['pfund-gift-tally'] ) ) {

		$current_tally = get_post_meta( $postid, '_pfund_gift-tally', true );

		$inputfields['pfund-gift-tally'] = array(

			'field' => $options['fields']['gift-tally'],

			'value' => $current_tally

		);

	}



	uasort( $inputfields, '_pfund_sort_fields' );

	$hidden_inputs = '';

	$field_idx = 0;

	foreach( $inputfields as $tag => $field_data ) {

		$field = $field_data['field'];

		$value = pfund_get_value( $field_data, 'value' );



		$field_options = array(			

			'name' => $tag,

			'desc' => pfund_get_value( $field, 'desc' ),

			'label' => pfund_get_value( $field, 'label' ),

			'value' => $value,

			'render_type' => $render_type,

			'field_count' => $field_idx,

			'required' => pfund_get_value( $field, 'required', false )

		);

		if ( isset( $field_data['attrs'] ) ) {

			$field_options['attrs']= shortcode_parse_atts( $field_data['attrs'] );

		}

		switch ( $field['type'] ) {

			case 'camp_title':

				if ( ! is_admin() ) {

					$field_options['value'] = $campaign_title;

					$content .= _pfund_render_text_field( $field_options );					

					$field_idx++;

				}

				break;

			case 'camp_location':

				if ( ! is_admin() ) {

					if ( $editing_campaign ) {

						require_once( ABSPATH . 'wp-admin/includes/post.php' );

						list( $permalink, $post_name ) = get_sample_permalink( $postid );

						

					} else {

						$post_name = '';

					}

					$field_options['custom_validation'] = 'ajax[pfundSlug]';

					$field_options['value'] = $post_name;

					$field_options['pre_input'] = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['campaign_slug'] );					

					$content .= _pfund_render_text_field( $field_options );

					$field_idx++;

				}

				break;

			case 'fixed':

			case 'gift_tally':

				if ( is_admin() ) {

					$content .= _pfund_render_text_field( $field_options );

				} else if ( $field['type'] == 'fixed' ) {

					$attr = shortcode_parse_atts( $field_data['attrs'] );

					$hidden_inputs .= '	<input type="hidden" name="'.$tag.'" value="'.$attr["value"].'"/>';

				}

				break;

			case 'end_date':

			case 'date':

				$field_options['class'] = 'pfund-date';

				$field_options['value'] = pfund_format_date( 

						$field_options['value'],  

						$options['date_format']

				);

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'giver_tally':

				if ( is_admin() ) {

					$content .= _pfund_render_text_field( $field_options );

					$field_idx++;					

				}

				break;

			case 'user_goal':

				$field_options['custom_validation'] = 'custom[onlyNumber]';

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'text':

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'textarea':

                $field_options['class'] = 'pfund-textarea';

                $field_options = _pfund_add_validation_class($field_options);

				$field_content = '<textarea class="'.$field_options['class'].'" id="'.$tag.'" name="'.$tag.'" rows="10" cols="50" type="textarea">'.$value.'</textarea>';

				$content .= pfund_render_field_list_item( $field_content, $field_options);

				$field_idx++;

				break;

			case 'image':

                if ( ( isset( $field_options['required'] ) && $field_options['required'] ) ) {                

                    $field_options['custom_validation'] = 'funcCall[requiredFile]';

                }

				$content .= _pfund_render_image_field( $field_options );

				$field_idx++;

				break;

			case 'select':

				$field_content = pfund_render_select_field( $field['data'], $tag, $value );

				$content .= pfund_render_field_list_item( $field_content, $field_options );

				$field_idx++;

				break;

			case 'user_email':

				if ( empty ( $value ) && !is_admin() ) {

					$value = $current_user->user_email;

					$field_options['value'] = $value;

				}

				$field_options['custom_validation'] = 'custom[email]';

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			case 'user_displayname':

				if ( empty ($value) && !is_admin() ) {

					$value = $current_user->display_name;

					$field_options['value'] = $value;

				}				

				$content .= _pfund_render_text_field( $field_options );

				$field_idx++;

				break;

			default:

				$content .= apply_filters( 'pfund_'.$field['type'].'_input', $field_options );

				$field_idx++;

		}

	}

	if ( ! is_admin() ) {

		$content .= '</ul>';

	}

	$content .= $hidden_inputs;

	

	/*if( ($current_user->user_firstname) && ($current_user->user_lastname) )

		$content .= '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script><script>		$(document).ready(function(){  

				jQuery("#pfund-camp-location").val("'.strtolower($current_user->user_firstname.'-'.$current_user->user_lastname).'");

			});</script>';

	elseif( ($current_user->user_firstname) && (!$current_user->user_lastname) )

		$content .= '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script><script>		$(document).ready(function(){  

				jQuery("#pfund-camp-location").val("'.strtolower($current_user->user_firstname).'");

			});</script>';

	elseif( (!$current_user->user_firstname) && ($current_user->user_lastname) )

		$content .= '<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script><script>		$(document).ready(function(){  

				jQuery("#pfund-camp-location").val("'.strtolower($current_user->user_lastname).'");

			});</script>';	*/



	return $content;



}



/**

 * Render a drop down

 * @param string $values newline delimited values for the dropdown

 * @param string $name name of drop down

 * @param string $currentValue the value in the drop down that should be

 * selected.

 * @return string The HTML for the dropdown.

 */

function pfund_render_select_field( $values, $name = '', $currentValue = '' ) {

	$values = preg_split( "/[\n]+/", $values );

	$content = '<select name="'.$name.'" value="'.$name.'>';

	foreach( $values as $value ) {

		$content .= '<option value="' . trim( $value ) . '"'.selected( $currentValue, $value, false ).'>'.$value.'</option>';

	}

	$content .= '</select>';

	return $content;

}



/**

 * Save the personal fundraising fields for the specified campaign.

 * @param string $campid The id of the campaign to save the personal fundraising

 * fields to.

 */

function pfund_save_campaign_fields( $campid ) {

	$options = get_option( 'pfund_options' );	

	if ( isset( $options['fields'] ) ) {

		$fieldname = '';

		foreach ( $options['fields'] as $field_id => $field ) {

			$fieldname = 'pfund-'.$field_id;			

			switch( $field['type'] ) {

				case 'end_date':

				case 'date':

					if ( isset( $_REQUEST[$fieldname] ) ) {

						$date_format = pfund_get_value( $options, 'date_format', 'm/d/y' );

						if ( isset( $_REQUEST[$fieldname] ) && empty( $_REQUEST[$fieldname] ) ) {

							$date_to_save = $_REQUEST[$fieldname];

						} else {

							$date_to_save = pfund_date_to_iso8601( $_REQUEST[$fieldname] , $date_format );

						}

						update_post_meta( $campid, "_pfund_".$field_id, $date_to_save );

					}

				case 'image':

					 _pfund_attach_uploaded_image( $fieldname, $campid, "_pfund_".$field_id );

					break;

				case 'user_goal':

				case 'gift_tally':

					if ( isset( $_REQUEST[$fieldname] ) ) {

						update_post_meta( $campid, "_pfund_".$field_id, absint( $_REQUEST[$fieldname] ) );

					}

					break;

				default:

					if ( isset( $_REQUEST[$fieldname] ) ) {

						if ( is_array( $_REQUEST[$fieldname] ) ) {

							$value_to_save = $_REQUEST[$fieldname];

						} else {

							$value_to_save = strip_tags( $_REQUEST[$fieldname] );

						}

						update_post_meta( $campid, "_pfund_".$field_id, $value_to_save );

					}

					break;

			}

		}

	}

}



/**

 * Send a mandrill transactional email

 * @param string $email the email address to send to.

 * @param array $merge_vars An array of the email merge variables.

 * @param string $subject the subject for the email.

 * @param string $config the prefix name of the pfund options containing the mandrill

 * properties for the text version of the email, the html version and the optional template.

 * @param string $html the name of the pfund option containing the html version 

 * of the email.  

 * @return boolean flag indicating if send was successful.

 */

function pfund_send_mandrill_email($email, $merge_vars, $subject, $config) {

	$options = get_option( 'pfund_options' );

    $api_key = $options['mandrill_api_key'];    

    $from_email = apply_filters( 'wp_mail_from', get_option( 'admin_email' ) );

	$from_name = apply_filters( 'wp_mail_from_name',  '');

    $text = $options[$config.'_text'];

    $html = $options[$config.'_html'];

    $template = '';

    if ( isset( $options[$config.'_template'] ) ) {

        $template = $options[$config.'_template'];

    }

    $global_merge_vars = array();

    foreach ($merge_vars as $key => $value) {

        $global_merge_vars[] = array(

            'name'=>$key,

            'content'=>$value

        );

    }       

    $message_array = array (

        'key' => $api_key,

        'message'=> array (

            'html' => $html,

            'text' => $text,

            'subject'=> $subject,

            'from_email' => $from_email,

            'to' => array(

                array(

                    'email' => $email

                )

            ),

            'global_merge_vars' => $global_merge_vars

        )

    );

    if ( ! empty( $from_name ) ) {

        $message_array['message']['from_name'] = $from_name;

    }

    

    $action = 'send.json';

    if ( ! empty( $template ) ) {

        $message_array['template_name'] = $template;

        $message_array['template_content'] = array(

            array(

                'name' => 'unused',

                'content' => ''

            )

        ); //Use an empty array for the template since we are not modifying parts of the template.

        $action = 'send-template.json';

    }

    $response_info = wp_remote_post("https://mandrillapp.com/api/1.0/messages/$action", 

        array(

            'body' => $message_array

        )

    );

    

    $response_body = null;

    if (is_array( $response_info ) ) {

        $response_body = $response_info['body'];

    } else {

        error_log( "Mandrill call to send email failed.  The response was: " . print_r( $response_info, true ) );

    }



    $obj = json_decode($response_body);        

    if ( is_array( $obj ) ) {

        $result = $obj[0];

    } else {

        $result = $obj;

    }

    if ( $result->status === 'sent' ) {

        return true;

    } else {

        if ( $result->status === 'error' ) {

            error_log( "Mandrill call returned an error.  The response was: " . print_r( $result, true ) );

        }

        return false;



    }

}



/**

 * Add the specified image file upload to the specified post

 * @param string $fieldname Name of the file in the request.

 * @param string $postid The id of the post to attach the file to.

 * @param string $metaname The name of the metadata field to store the

 * attachment in.

 */

function _pfund_attach_uploaded_image( $fieldname, $postid, $metaname ) {

	if( isset( $_FILES[$fieldname] ) && is_uploaded_file( $_FILES[$fieldname]['tmp_name'] ) ) {

		$data = media_handle_upload( $fieldname, $postid, array( 'post_status' => 'private' ) );

		if( is_wp_error( $data ) ) {

			error_log("error adding image for personal fundraising:".print_r( $data, true ) );

		} else {

			update_post_meta( $postid, $metaname, $data );

		}

	}

}



/**

 * Format the specified date with the specified format.

 * @param string $date either an iso8601 (YYYY-MM-DD) formatted date or a

 * mm/dd/yy date.

 * @param string $format the format to return the date in.

 * @return string the formatted date.

 */

function pfund_format_date( $date, $format ) {

	if ( empty($date) ) {

		return $date;

	}

	//Date is stored in old format of m/d/y

	if ( strlen( $date ) == 8 ) {

		$date = pfund_date_to_iso8601( $date, 'm/d/y' );

	}

	return gmdate( $format, strtotime( $date ) );

}



/**

 * Determine the proper contact information for the specified campaign.  If the

 * campaign has a user display name and user email field, use those values instead

 * of the post author's contact information.  This function is necessary for use

 * cases where the campaign is created by an administrator, but the notifications

 * should be sent to another contact.

 * @param <mixed> $post The post representing the campaign to get the contact

 * information for

 * @param <mixed> $options The current personal fundraiser options.

 * @return <mixed> a WP_User object containing the contact information for

 * the specified campaign.

 */

function pfund_get_contact_info( $post, $options = array() ) {

	$metavalues = get_post_custom( $post->ID );

	$contact_email = '';

	$contact_name = '';

	foreach( $metavalues as $metakey => $metavalue ) {

		if ( strpos( $metakey, "_pfund_" ) === 0 ) {

			$field_id = substr( $metakey , 7);

			if ( isset($options['fields'][$field_id]) ) {

				$field_info = $options['fields'][$field_id];

				if ( ! empty( $field_info )  && ! empty( $metavalue[0] ) ) {

					switch( $field_info['type'] ) {

						case 'user_email':

							$contact_email = $metavalue[0];

							break;

						case 'user_displayname':

							$contact_name = $metavalue[0];

							break;

					}

					if ( ! empty( $contact_email ) && ! empty( $contact_name ) ) {

						break;

					}

				}

			}

		}

	}

	$contact_data = clone get_userdata($post->post_author);

	if ( $contact_data->user_email != $contact_email ) {

		$contact_data->user_email = $contact_email;

		$contact_data->display_name = $contact_name;

		$contact_data->ID = -1;

	}

	return $contact_data;

}



/**

 * Convenience function to count number of published fundraisers

 * @return int number of published fundraising campaigns.

 */

function pfund_get_total_published_campaigns() {

    $count_posts = wp_count_posts( 'pfund_campaign' );

    $published_posts = $count_posts->publish;

    return number_format( $published_posts, 0 ,".","," );

}



function pfund_get_validation_js() {

	$validateSlug = array(

		'file' => PFUND_URL.'validate-slug.php',

		'alertTextLoad' => __( 'Please wait while we validate this location', 'pfund' ),

		'alertText' => __( '* This location is already taken', 'pfund' )

	);
	
	$validateCaptcha = array(

		'file' => PFUND_URL.'validate-captcha.php',

		'alertTextLoad' => __( 'Please wait while we validate this captcha', 'pfund' ),

		'alertText' => __( '* Invalid Captcha.', 'pfund' )

	);

    $required_validation = array(

        'regex' => 'none',

        'alertText' =>  __( '* This field is required', 'pfund' ),

        'alertTextCheckboxMultiple' =>  __( '* Please select an option', 'pfund' ),

        'alertTextCheckboxe' =>  __( '* This checkbox is required', 'pfund' )

    );

    $required_file_validation = array(

        'nname' =>  'pfund_validate_required_file',

        'alertText' =>  __( '* This field is required', 'pfund' )

    );    



    $length_validation = array(

        'regex' => 'none',

        'alertText' =>  __( '*Between ', 'pfund' ),

        'alertText2' => __( ' and ', 'pfund' ),

        'alertText3' => __( ' characters allowed', 'pfund' )

    );

    $email_validation = array(

        'regex' => '/^[a-zA-Z0-9_\.\-]+\@([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9]{2,4}$/',

        'alertText' =>  __( '* Invalid email address', 'pfund' )

    );

    $number_validation = array(

        'regex' => '/^[0-9\ ]+$/',

        'alertText' =>  __( '* Numbers only', 'pfund' )

    );

    return array(

        'pfundSlug' => $validateSlug,
		
		/*'pfundCaptcha' => $validateCaptcha,*/

        'required' => $required_validation,

        'length' => $length_validation,

        'email' => $email_validation,

        'onlyNumber' => $number_validation,

        'requiredFile' => $required_file_validation

    );

}



/**

 * Utility function to get value from array.  If the value doesn't exist,

 * return the specified default value.

 * @param array $array The array to pull the value from.

 * @param string $key The array key to use to get the value.

 * @param mixed $default The optional default to use if the key doesn't exist.

 * This value defaults to an empty string.

 * @return mixed The specified value from the array or the default if it doesn't

 * exist

 */

function pfund_get_value( $array, $key, $default = '' ) {

	if ( isset( $array[$key] ) ) {

		return $array[$key];

	} else {

		return $default;

	}

}



/**

 * Handles registering a new user.

 *

 * @param array $value_array Array of fields to create user.

 * @return int|WP_Error Either user's ID or error on failure.

 */

function pfund_register_user($value_array=array()) {

    if (empty($value_array)) {

        $value_array = $_POST;

    }

    $user_login = $value_array['pfund_user_login'];

    $user_pass = $value_array['pfund_user_pass'];

    if (empty($user_pass)) {

        $user_pass = wp_generate_password();        

    }    

    $user_email = $value_array['pfund_user_email'];

    $first_name = $value_array['pfund_user_first_name'];

    $last_name = $value_array['pfund_user_last_name'];



    $errors = new WP_Error();



    $sanitized_user_login = sanitize_user( $user_login );

    $user_email = apply_filters( 'user_registration_email', $user_email );



    // Check the username

    if ( $sanitized_user_login == '' ) {

        $errors->add( 'empty_username', 'true');

    } elseif ( ! validate_username( $user_login ) ) {

        $errors->add( 'invalid_username', 'true');

        $sanitized_user_login = '';

    } elseif ( username_exists( $sanitized_user_login ) ) {

        $errors->add( 'username_exists', 'true');

    }



    // Check the e-mail address

    if ( $user_email == '' ) {

        $errors->add( 'empty_email', 'true');

    } elseif ( ! is_email( $user_email ) ) {

        $errors->add( 'invalid_email', 'true');

        $user_email = '';

    } elseif ( email_exists( $user_email ) ) {

        $errors->add( 'email_exists', 'true');

    }



    do_action( 'register_post', $sanitized_user_login, $user_email, $errors );



    $errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );



    if ( $errors->get_error_code() )

        return $errors;





    $user_login = $sanitized_user_login;

    $userdata = compact('user_login', 'user_email', 'user_pass','first_name','last_name');

    $user_id = wp_insert_user($userdata);

    if (! $user_id ) {

        $errors->add( 'registerfail', 'true' );

        return $errors;

    }





    wp_new_user_notification($user_id, $user_pass);



    return $user_id;

}





/**

 * Render the field using the specified render type.

 * @param string $field_contents actual input field to render.*

 * @param array $field_options named options for field.

 * @return string the rendered HTML.

 */

function pfund_render_field_list_item( $field_contents, $field_options ) {

	$content = '<li>';

	$content .= '	<label for="'.$field_options['name'].'">'.$field_options['label'];

	if ( isset( $field_options['required'] ) && $field_options['required'] ) {

		$content .= '<abbr title="'.esc_attr__( 'required', 'pfund' ).'">*</abbr>';

	}

	$content .= '</label>';

	$content .= $field_contents;

	if ( isset( $field_options['render_type'] ) &&  

			$field_options['render_type'] == 'user' &&

			! empty( $field_options['desc'] ) ) {

		$content .= '<div class="pfund-field-desc"><em><small>'.$field_options['desc'].'</small></em></div>';

	}

	$content .= '</li>';

	return $content;

}



/**

 * Add validation class to the specified field options if field needs validation.

 * @param array $field_options named options for field.

 * @return array updated field options including validation class if needed.

 */

function _pfund_add_validation_class($field_options) {

	if ( ( isset( $field_options['required'] ) && $field_options['required'] ) ||

			isset( $field_options['custom_validation'] ) ) {

		$field_options['class'] .= ' validate[';

		if ( $field_options['required'] ) {

			$field_options['class'] .= 'required';

			if ( isset( $field_options['custom_validation'] ) ) {

				$field_options['class'] .= ',';

			}

		}

		if ( isset( $field_options['custom_validation'] ) ) {

			$field_options['class'] .=  $field_options['custom_validation'];

		}

		$field_options['class'] .= ']';

	}

    return $field_options;

}



/**

 * Render an image input field, including a display of the current image.

 * @param array $field_options named options for field.  Keys are:

 *	--name name of the field

 *	--label label to display with field.

 *	--value link to current image.

 * @return string HTML markup for image file upload/display.

 */

function _pfund_render_image_field( $field_options ) {

	if ( ! empty ( $field_options['value'] ) ) {

		$field_options['additional_content'] = '<img class="pfund-image" width="184" src="'.wp_get_attachment_url( $field_options['value'] ).'">';

	}

	$field_options['class'] = 'pfund-image';

	$field_options['type'] = 'file';

	return _pfund_render_text_field( $field_options );

	

}



/**

 * Render the HTML for a text input field

 * @param array $field_options named options for field.  Keys are:

 *	--name the name/id of the text field

 *	--label The label to display next to the input field.

 *	--class  The class name for the input field.

 *	--value The value for the input field.

 *	--type The type of input field.  Defaults to text.

 *	--additional_content Additional HTML to display.

 * @return string The HTML of a text input field.

 */

function _pfund_render_text_field( $field_options = '') {

	$defaults = array(

		'class' => 'pfund-text',

		'type' => 'text',

		'value' => '',

	);

	$field_options = array_merge( $defaults, $field_options );

    $field_options = _pfund_add_validation_class($field_options);

	$content = '';

	if ( isset( $field_options['pre_input'] ) ) {

		$content .= $field_options['pre_input'];

	}

	$content .= '	<input class="'.$field_options['class'].'" id="'.$field_options['name'].'"';

	$content .= '		type="'.$field_options['type'].'" name="'.$field_options['name'].'"';

	if ( $field_options['type'] == 'file' ) {

        if ( ! empty( $field_options['value'] ) ) {

            $content .= ' data-pfund-file-set="true"';

        }

	} else {

        $content .= ' value="'.esc_attr( $field_options['value'] ).'"';

    }

	$content .= '/>';

	if ( isset( $field_options['additional_content'] ) ) {

		$content .= $field_options['additional_content'];

	}

	return pfund_render_field_list_item( $content, $field_options );

}



/**

 * Sort the specified fields using the fields sortorder.

 * @param mixed $field the original field

 * @param mixed $compare_field the field to compare.

 * @return int indicating if fields are equal, greater than or less than one

 * another.

 */

function _pfund_sort_fields( $field, $compare_field ) {

	$field_order = $field['field']['sortorder'];

	$compare_order = $compare_field['field']['sortorder'];



	if($field_order == $compare_order) {

		return 0;

	} else {

		return ( $field_order < $compare_order ) ? -1 : 1;

	}

	

}

function posts_for_current_author($query) {



	if(!is_admin && !current_user_can( 'administrator' )){

	if($query->is_admin) {



		global $user_ID;

		$query->set('author',  $user_ID);

	}}

if($query->is_admin==''){return $query;}

	return $query;

}

add_filter('pre_get_posts', 'posts_for_current_author');

function jquery_remove_counts()

{

	?>

	<script type="text/javascript">

	jQuery(function(){

		jQuery("li.all").remove();

		jQuery("li.publish").remove();

		jQuery("li.trash").remove();

		jQuery("li.pending").remove();

	});

	</script>

	<?php

}



global $current_user;

if ( !function_exists('wp_get_current_user') ) {

function wp_get_current_user() {

// Insert pluggable.php before calling get_currentuserinfo()

require (ABSPATH . WPINC . '/pluggable.php');

global $current_user;

get_currentuserinfo();

return $current_user;

}

}

$subscriber = get_role( 'subscriber' );

$user_ID = get_current_user_id();

    global $current_user;

    get_currentuserinfo();

    $user_id = $current_user->ID;

$user = new WP_User( $user_id );

if($user->roles[0]!='administrator' )

{

	add_action('admin_footer', 'jquery_remove_counts');

}

add_action('pre_get_posts', 'filter_posts_list');

function filter_posts_list($query)

{

    //$pagenow holds the name of the current page being viewed

     global $pagenow;

 

    //$current_user uses the get_currentuserinfo() method to get the currently logged in user's data

     global $current_user;

     get_currentuserinfo();

     

        //Shouldn't happen for the admin, but for any role with the edit_posts capability and only on the posts list page, that is edit.php

        if(!current_user_can('administrator') && current_user_can('edit_posts') && ('edit.php' == $pagenow))

     {

        //global $query's set() method for setting the author as the current user's id

        $query->set('author', $current_user->ID);

        }

}

// ajax code for team

add_action( 'wp_ajax_Load_team_data', 'Load_team_data_callback' );

add_action( 'wp_ajax_nopriv_Load_team_data', 'Load_team_data_callback' );

function Load_team_data_callback()

{

     ?>

	 <div id="join_div" style="height: 50px;margin-left:10px;">

				<?php

		$tarr=array('post_type'		=>	'teamcampaigns',
					'post_status'	=>	'publish', 
					'numberposts'   =>	-1, 
					'orderby'       => 'post_title',
					'order'         => 'ASC'
					);

		$exist_teams=get_posts($tarr);

		echo '<div style="float:left;margin-right:20px;">Select Team : <select name="join_teams" id="join_teams" onchange="loadnewteambox(this.value);"><option value="">Select Team</option>';

		if(sizeof($exist_teams)>0):

			foreach($exist_teams as $team):

			if( $team->post_title!='team-creation'){

				echo '<option value="'.$team->ID.'">'.$team->post_title.'</option>';

			}

			endforeach;

			echo '<option value="create_team">Create a team</option>';

		echo '</select></div>';

		echo '<div id="new_create_team" style="display:none;"><input placeholder="Enter Team Name" type="text" name="team_name" id="team_name" />
<input class="pfund-text" id="pfund-captcha" name="pfund-captcha" placeholder="Enter Image Text" type="text">
<img src="'.PFUND_URL.'includes/captcha.php" />

                <input type="button" class="btn_event_form_submit ui-button ui-button-big ui-priority-primary ui-state-default ui-state-hover ui-state-focus ui-corner-all" id="create_team_button" value="Create Team" onclick="loadnewname();"/></div>';
		endif;

				?>

               	</div>

                

				<?php

   die();

}

add_action('wp_head','save_cj_team_data');

if(!function_exists('save_cj_team_data')):

function save_cj_team_data()

{

	global $wpdb;
	$team_value = $_POST['join_teams'];
	if(isset($_POST['join_team']) and $_POST['join_team']=='yes'):
	
	$team_members  =  get_post_meta($team_value,'team_members',true);
	
	$reg_id = $wpdb->get_row("SELECT registration_id,id FROM ".$wpdb->prefix."events_attendee WHERE email='".$_POST['email']."' AND id NOT IN ('".$team_members."')  ORDER BY id DESC LIMIT 1 "); 
	
	
    $xreg_id = $wpdb->get_results("SELECT id FROM ".$wpdb->prefix."events_attendee WHERE registration_id='".$reg_id->registration_id."' and id !='".$reg_id->id."'"); 
	
    $xatid = $reg_id->id;
	
	if(sizeof($xreg_id)>0)

	{

	    foreach($xreg_id as $xid):

		$xatid.=','.$xid->id;

		endforeach;

	}

	

	if(is_numeric($team_value))

	{

	   $check  =  get_post_meta($team_value,'team_members',true);

        //echo $check;	   

   	   if($check==""){

	   

	     update_post_meta($team_value,'team_members',$xatid);

		

	  }

	  else{

	  $impl=$check.','.$xatid;
	

	  update_post_meta($team_value,'team_members',$impl);

	  }

	 // Email
	$pfund_options = get_option( 'pfund_options' );
	$to      = $_POST['email'];

	$subject = 'Team URL';
	$old_message = $pfund_options['mandrill_email_publish_text1'];
	$search = array('|fname|','|lname|','|team_campaign_link|','|cuase_link|');
	$replace = array($_POST['fname'],$_POST['lname'],'<a href="'.get_permalink($post_id).'">here</a>','<a href="http://hospicedash.com/causes/hospice-dash-5k/">click here.</a>');
	$message = str_ireplace($search, $replace, $old_message);
	//$message = 'Dear '.$_POST['fname'].' '.$_POST['lname'].',<br/><br/>Thank you for registering your team for the Hospice Dash.<br/><br/>You can view your team and its members here: <a href="'.get_permalink($post_id).'">here</a><br/><br/>If you are interested in creating your own fundraising page to support Niagara Hospice&#39;s work, <a href="http://hospicedash.com/causes/hospice-dash-5k/">click here.</a><br/><br/>Happy training!<br/><br/>The Hospice Dash Committee';

	$headers  = 'MIME-Version: 1.0' . "\r\n";

	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	$headers .= 'From: WordPress <mrizzo@nhalliance.com>' . "\r\n";

	mail($to, $subject, $message, $headers);

	}

	else

	{

	$get_event_date=$wpdb->get_row("SELECT end_date FROM ".$wpdb->prefix."events_detail WHERE id=".$_POST['event_id']."");

	$end_date=$get_event_date->end_date;

	// Add new team in db and associate this regestraint 

    // Create post object

   $new_team = array(

  'post_title'    => $team_value,

  'post_status'   => 'publish',

  'post_author'   => 1,

  'post_type'     =>'teamcampaigns',

  'post_content'  => "<h1>SUPPORTING NIAGARA HOSPICE CARE</h1>
<ul class='pfu'>
  <li class='pfund-stat'><span class='highlight'>$[pfund-gift-goal]</span>funding goal</li>
  <li class='pfund-stat'><span class='highlight'>$[pfund-gift-tally]</span>raised</li>
  <li class='pfund-stat'><span class='highlight'>[pfund-giver-tally]</span>givers</li>
  <li class='pfund-stat'><span class='highlight'>[pfund-days-left]</span>days left</li>
  <li class='pfund-stat' style='background:none'>[pfund-progress-bar]</li>
</ul>
<div style='clear: both;' class='det'>
<br/>
<h2>HI OUR TEAM IS, [pfund-camp-title] </h2> 
<p>We are raising funds to support Niagara Hospice patients and their families . Please support our team effort by clicking on the donate button below, or donate on a team member's page by clicking below. Thank you for supporting Niagara Hospice!</p>
<p style='margin:0; font-weight:bold;'>My Personal Message : </p>
<p>[pfund-message]</p>
<p class='pht'>[pfund-photo]</p>
<p>[pfund-donate]<p>
</div>[pfund-edit]"

  );

  $team_meta=array('_pfund_camp-title'=>$team_value,'_pfund_camp-location'=>$team_value,'_pfund_gift-tally'=>0,'_pfund_gift-goal'=>0,'_pfund_end-date'=>$end_date);

   // Insert the post into the database

   $post_id = wp_insert_post( $new_team );


   foreach($team_meta as $metakey=>$metaval): 

   add_post_meta( $post_id, $metakey, $metaval); 

   endforeach;

	/*Email*/
	$pfund_options = get_option( 'pfund_options' );
	$to      = $_POST['email'];

	$subject = 'Team URL';
	$old_message = $pfund_options['mandrill_email_publish_text1'];
	$search = array('|fname|','|lname|','|team_campaign_link|','|cuase_link|');
	$replace = array($_POST['fname'],$_POST['lname'],'<a href="'.get_permalink($post_id).'">here</a>','<a href="http://hospicedash.com/causes/hospice-dash-5k/">click here.</a>');
	$new_message = str_ireplace($search, $replace, $old_message);
	//$message = 'Dear '.$_POST['fname'].' '.$_POST['lname'].',<br/><br/>Thank you for registering your team for the Hospice Dash.<br/><br/>You can view your team and its members here: <a href="'.get_permalink($post_id).'">here</a><br/><br/>If you are interested in creating your own fundraising page to support Niagara Hospice&#39;s work, <a href="http://hospicedash.com/causes/hospice-dash-5k/">click here.</a><br/><br/>Happy training!<br/><br/>The Hospice Dash Committee';
	$message = $new_message;
	$headers  = 'MIME-Version: 1.0' . "\r\n";

	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	$headers .= 'From: WordPress <mrizzo@nhalliance.com>' . "\r\n";

	mail($to, $subject, $message, $headers);

	}

	update_post_meta( $post_id, 'team_members', $xatid);

	update_post_meta( $post_id, '_pfund_cause_id', $_POST['cause-slug']);

	endif;

}

endif;

/*add_action('action_hook_espresso_email_after_payment','send_team_link');

if(!function_exists('send_team_link')):

function send_team_link()

{

	$to      = $_POST['email'];

	$subject = 'Team URL';

	$message = 'Dear '.$_POST['fname'].' '.$_POST['lname'].',<br/>Thank you for registering. You can view your team here:'.get_permalink($post_id);

	$headers  = 'MIME-Version: 1.0' . "\r\n";

	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	$headers .= 'From: WordPress <mrizzo@nhalliance.com>' . "\r\n";

	mail($to, $subject, $message, $headers);

}

endif;*/

// ajax code for team

add_action( 'wp_ajax_Edit_Load_team_data', 'Edit_Load_team_data_callback' );

add_action( 'wp_ajax_nopriv_Edit_Load_team_data', 'Edit_Load_team_data_callback' );

function Edit_Load_team_data_callback()

{

     $member_id = $_REQUEST['member_id'];

	 $post_id   = $_REQUEST['post_id'];

	 ?>

	 <div id="join_div" style="height: 50px;margin-left:10px;">

		<?php

		$tarr=array('post_type'=>'teamcampaigns','post_status'=>'publish');

		$exist_teams=get_posts($tarr);

		echo '<div style="float:left;margin-right:20px;">Your Team : <select style="margin-left:80px;" name="join_teams" id="join_teams" onchange="loadnewteambox(this.value);"><option value="">Select Team</option>';

		if(sizeof($exist_teams)>0):

			foreach($exist_teams as $team):

			if($team->post_title!='team-creation'){?>

			    <option value="<?php echo $team->ID; ?>" <?php if($team->ID==$post_id){echo "selected='selected'";}?>><?php echo $team->post_title;?></option>

				<?php

			}

			endforeach;

			echo '<option value="create_team">Create a team</option>';

		echo '</select></div>';

		echo '<div id="new_create_team" style="display:none;"><input placeholder="Enter Team Name" type="text" name="team_name" id="team_name" />

                <input type="button" class="btn_event_form_submit ui-button ui-button-big ui-priority-primary ui-state-default ui-state-hover ui-state-focus ui-corner-all" id="create_team_button" value="Create Team" onclick="loadnewname();"/></div>';

		endif;

				?>

               	</div>

                

				<?php

   die();

}

add_action('wp_head','update_attendee_team');

function update_attendee_team()

{
	

    global $wpdb;

    if(!empty($_POST['submit']) && $_POST['attendee_action']=='update_attendee'):

	$join_team    = $_POST['join_teams'];

	$current_team = $_POST['current_team'];

	$mem_id       = $_POST['id'];

	if(!is_numeric($join_team))

	{

	    // create new team

		    $get_event_date=$wpdb->get_row("SELECT end_date FROM ".$wpdb->prefix."events_detail WHERE id=".$_POST['event_id']."");

			$end_date=$get_event_date->end_date;

			// Add new team in db and associate this regestraint 

			// Create post object

		   $new_team = array(

		  'post_title'    => $join_team,

		  'post_content'  => '',

		  'post_status'   => 'publish',

		  'post_author'   => 1,

		  'post_type'     =>'teamcampaigns'

		  );

		  $team_meta=array('_pfund_camp-title'=>$join_team,'_pfund_camp-location'=>$join_team,'_pfund_gift-tally'=>0,'_pfund_gift-goal'=>0,'_pfund_end-date'=>$end_date);

		   // Insert the post into the database

		   $post_id = wp_insert_post( $new_team );

		   foreach($team_meta as $metakey=>$metaval): 

		   add_post_meta( $post_id, $metakey, $metaval); 

		   endforeach;

		   update_post_meta($post_id,'team_members',$mem_id);

		 $old_team      =  get_post_meta($current_team,'team_members',true);

           $exp           =  explode(',',$old_team);

	       if($key=array_search($mem_id,$exp)!==FALSE)

	       {

	        unset($exp[$key]);

	       }

		  $implode = implode(',',$exp);

		  update_post_meta($current_team,'team_members',$implode);

			

	}

	else if($join_team!=$current_team)

	{

       $old_team      =  get_post_meta($current_team,'team_members',true);

       $exp           =  explode(',',$old_team);

	  

	   

	   if($key=array_search($mem_id,$exp)!==FALSE)

	   {

	    $key=array_search($mem_id,$exp);

	     

		// unset($exp[$key]);

		 

	   }

		  

		 $implode = implode(',',$exp);

		 

         //die();		

		update_post_meta($current_team,'team_members',$implode);

	   

	   $members  =  get_post_meta($join_team,'team_members',true);	

	   $impl     =  $members.','.$mem_id;

	   update_post_meta($join_team,'team_members',$impl);

	}

	endif;

    

}

/**

 * Adds a Team members box to the main column on the Team Campaigns edit screens.

 */

function team_members_add_meta_box() {



	$screens = array( 'teamcampaigns' );



	foreach ( $screens as $screen ) {



		add_meta_box(

			'team_members',

			__( 'Add/Remove Team Members', 'teammembers_textdomain' ),

			'team_members_meta_box_callback',

			$screen,

			'normal',

			'high'

		);

	}

}

add_action( 'add_meta_boxes', 'team_members_add_meta_box' );



/**

 * Prints the box content.

 * 

 * @param WP_Post $post The object for the current post/page.

 */

function team_members_meta_box_callback( $post ) {

	?>

    <style type="text/css">

    #load_member{left: 155px;

    position: absolute;

    width: 205px;z-index:1;

	background: none repeat scroll 0 0 #f1f1f1;

    border: 1px solid #e4e4e4;

	display:none;

	}

	#load_member > ul {

    padding: 0;

	margin:0;

}

#load_member li {

    background: none repeat scroll 0 0 #fff;

    padding: 7px;

	margin:2px 0 0 0;

}

#load_member li:hover{background: none repeat scroll 0 0 #f2f2f2;}

    </style>

    <?php

    global $wpdb;

    	

	// Add an nonce field so we can check for it later.

	wp_nonce_field( 'team_members_meta_box', 'team_members_meta_box_nonce' );



	/*

	 * Use get_post_meta() to retrieve an existing value

	 * from the database and use the value for the form.

	 */

	$get_value = get_post_meta( $post->ID, 'team_members', true );

	$value = trim($get_value,',');

	$tm = explode(',',$value);

	



	echo '<label for="team_members">';

	_e( 'Remove Team Members : ', 'teammembers_textdomain' );

	echo '</label> ';

	echo '<input type="hidden" id="post_id" value="'.$post->ID.'">';

	//print_r($tm);

	foreach($tm as $val):
	
	$query  = "SELECT id,fname,lname FROM ".$wpdb->prefix."events_attendee WHERE id=".$val."";

	$result = $wpdb->get_row($query);
	if($result->fname != ''){
	echo '<input type="checkbox" name="remove_members[]" value="'.$val.'">'.$result->fname.' '.$result->lname.'&nbsp;&nbsp;';
	}
	endforeach;
	/* added 03-02-2015*/
	$team_title = get_the_title( $_POST['post_id'] );
	$personal_ids 	= $wpdb->get_results('SELECT post_id FROM '.$wpdb->prefix.'postmeta WHERE meta_value = "'.$team_title.'"' );
	$data_array = array();
	foreach($personal_ids as $personal_id){
		$data_array[] = array('id' => $personal_id->post_id,
					'fname' => get_post_meta($personal_id->post_id,'_pfund_first-name',true),
					'lname' => get_post_meta($personal_id->post_id,'_pfund_last-name',true)
					);
		
		}
		foreach($data_array as $data){
        if(!empty($data['fname'])){ 	
	echo '<input type="checkbox" name="remove_teamcampaigns[]" value="'.$data['id'].'">'.$data['fname'].' '.$data['lname'].'&nbsp;&nbsp;';
		}
	}
/*end editing*/
	echo '<br><br>';

	echo '<label for="team_members">';

	_e( 'Add Team Members  : ', 'teammembers_textdomain' );

	echo '</label> &nbsp;&nbsp;&nbsp;&nbsp;';

	

    echo '<input type="hidden" name="add_member_id" id="add_member_id" value="">';	

	echo '<input type="text" name="add_members" id="add_members" value="" style="position:relative;" autocomplete="off"/>';

	echo  '<div id="load_member"></div>';

	

	

}

add_action('admin_footer','load_search_member_js');

function load_search_member_js()

{

   ?>

   <script>

   var $ast=jQuery.noConflict();

   $ast(document).ready(function(){

	   //alert('test');

	   ajaxurl='<?php echo admin_url("admin-ajax.php");?>';

	   

	   $ast('#add_members').keyup(function(){

		   var smem=$ast('#add_members').val();

		   var post_id=$ast('#post_id').val();

		   var MIN_LENGTH = 2;

		   if(smem!=='' && smem.length >= MIN_LENGTH){

			    $ast('#load_member').show();

				$ast.ajax({

					type:'post',

					data:{'action':'Load_search_member','smem':smem,'post_id':post_id},

					url:ajaxurl,

					success: function(response)

					    {

						   //alert(response);

						   $ast('#load_member').html(response);

						}

					});

			   

			   }

			   else

			   {

				    $ast('#load_member').hide();

			   }

		   

		   });

	   });

	   function add_new_member(member_id,member_name)

	   {

	     document.getElementById('add_members').value=member_name;
		 

		 document.getElementById('add_member_id').value=member_id;

		 document.getElementById('load_member').style.display='none';

	   }

   </script>

   <?php	

}

add_action('wp_ajax_Load_search_member','Load_search_member_callback');

function Load_search_member_callback()

{

	global $wpdb;

	

	$get_exit_mem   =    get_post_meta($_POST['post_id'],'team_members',true);

	$exit_mem       =    trim($get_exit_mem,',');
	
	$attendee_name= $_POST['smem'];
	
	if(!empty($exit_mem)):

	$query="SELECT * FROM ".$wpdb->prefix."events_attendee WHERE id NOT IN(".$exit_mem.") AND (fname LIKE '".$attendee_name."%' OR lname LIKE '".$attendee_name."%')";
	
	else:
	
	$query="SELECT * FROM ".$wpdb->prefix."events_attendee WHERE fname LIKE '".$attendee_name."%' OR lname LIKE '".$attendee_name."%'";
	endif;

	//echo $query;

	$result=$wpdb->get_results($query);

	if($wpdb->num_rows>0):

	echo '<ul>';

	foreach($result as $val):

		echo '<li onclick=add_new_member("'.$val->id.'","'.$val->fname .'&nbsp;'. $val->lname.'")>';

		echo '<input type="hidden" name="att_id" value="'.$val->id.'">';

		echo $val->fname.' '.$val->lname;

		echo '</li>';

	endforeach;

	echo '</ul>';

	endif;

	die();

}



function add_script() { 

	$ptype = get_post_type(get_the_ID());

	if($ptype == 'teamcampaigns')

	{

		 echo '<script>

		jQuery(document).ready(function(){

			

			jQuery("#pfund-camp-location").attr("readonly", true);

			var t_name = jQuery("#pfund-camp-title").val().toLowerCase();

			if(t_name != "")

				jQuery("#pfund-camp-location").val(t_name);

			jQuery("#pfund-camp-title").change(function(){

				var team_name = jQuery("#pfund-camp-title").val().toLowerCase();

				var url = team_name;

				jQuery("#pfund-camp-location").val(url);

				

				});

		});</script>';

	}

	else

	{

    echo '<script>

		jQuery(document).ready(function(){

			jQuery("#pfund-camp-location").attr("readonly", true);

			jQuery("#pfund-last-name").change(function(){

				var last_name = jQuery("#pfund-last-name").val().toLowerCase();

				var first_name = jQuery("#pfund-first-name").val().toLowerCase();

				var url = first_name+"-"+last_name;

				jQuery("#pfund-camp-location").val(url);

				});
			
		}); </script>';

	}

}

add_action('wp_footer', 'add_script');



/*

 * Removes empty paragraph tags (<p></p>) and line break tags (<br>)

 * from shortcodes caused by WordPress's wpautop function.

 */

function remove_empty_tags_around_shortcodes($content) {

    $tags = array(

        '<p>[' => '[',

        ']</p>' => ']',

        ']<br>' => ']',

        ']<br />' => ']'

    );

 

    $content = strtr($content, $tags);

    return $content;

}

add_filter('the_content', 'remove_empty_tags_around_shortcodes');

function update_content_add_team_campaign($post_id)
{
	global $post, $wpdb;
	if ( ! wp_is_post_revision( $post_id ) ){
		// unhook this function so it doesn't loop infinitely
		remove_action('save_post', 'update_content_add_team_campaign');
		
	if($post->post_type=='teamcampaigns' && is_admin())
	{
		$args = array('ID' =>$post->ID, 
		'post_content' => "<h1>SUPPORTING NIAGARA HOSPICE CARE</h1>
<ul class='pfu'>
  <li class='pfund-stat'><span class='highlight'>$[pfund-gift-goal]</span>funding goal</li>
  <li class='pfund-stat'><span class='highlight'>$[pfund-gift-tally]</span>raised</li>
  <li class='pfund-stat'><span class='highlight'>[pfund-giver-tally]</span>givers</li>
  <li class='pfund-stat'><span class='highlight'>[pfund-days-left]</span>days left</li>
  <li class='pfund-stat' style='background:none'>[pfund-progress-bar]</li>
</ul>
<div style='clear: both;' class='det'>
<br/>
<h2>HI OUR TEAM IS, [pfund-camp-title] </h2> 
<p>We are raising funds to support Niagara Hospice patients and their families . Please support our team effort by clicking on the donate button below, or donate on a team member's page by clicking below. Thank you for supporting Niagara Hospice!</p>
<p style='margin:0; font-weight:bold;'>My Personal Message : </p>
<p>[pfund-message]</p>
<p class='pht'>[pfund-photo]</p>
<p>[pfund-donate]<p>
</div>[pfund-edit]");
	wp_update_post($args);
	
	// re-hook this function
		add_action('save_post', 'update_content_add_team_campaign');
	}
	if($post->post_type == 'pfund_campaign'){
	$team_title = get_post_meta( $post_id,'team_campaigns',true );
	$team_camp_id 	= $wpdb->get_var('SELECT ID FROM '.$wpdb->prefix.'posts WHERE post_title = "'.$team_title.'" AND post_status = "publish"' );

	$team_tally = get_post_meta($team_camp_id,'_pfund_gift-tally',true);
	$team_goal = get_post_meta($team_camp_id,'_pfund_gift-goal',true);
		$campaign_tally =  get_post_meta($post_id,'_pfund_gift-tally',true);
		$campaign_goal =  get_post_meta($post_id,'_pfund_gift-goal',true);
		
		if(isset($_POST['pfund-gift-tally'])){
			 $campaign_tally = $_POST['pfund-gift-tally'] - $campaign_tally;
			}else{
		$campaign_tally = get_post_meta($post_id,'_pfund_gift-tally',true);
			}
		if(isset($_POST['pfund-gift-goal'])){
			if($_POST['pfund-gift-goal'] >= $campaign_goal || $_POST['pfund-gift-goal'] <= $campaign_goal){
				$team_goal = $team_goal + ($_POST['pfund-gift-goal'] - $campaign_goal );
				if($team_goal <= 0 ){
					$team_goal = 0;
					}
				}else{
			$team_goal = $team_goal + $_POST['pfund-gift-goal'];
			}
		}
			$team_tally = $team_tally + $campaign_tally;
		update_post_meta($team_camp_id,'_pfund_gift-tally',$team_tally);
		update_post_meta($team_camp_id,'_pfund_gift-goal',$team_goal);
			}
	}
	/*
	 * We need to verify this came from our screen and with proper authorization,
	 * because the save_post action can be triggered at other times.
	 */

	// Check if our nonce is set.
	if ( ! isset( $_POST['team_members_meta_box_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['team_members_meta_box_nonce'], 'team_members_meta_box' ) ) {
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}
	/* OK, it's safe for us to save the data now. */

	// Sanitize user input.
	$exit_mem   =    get_post_meta($post->ID,'team_members',true); 
	
	$explode    =    explode(',',$exit_mem); 
	
	// remove members from team
	if(isset($_POST['remove_members'])) {
		$remove_mem =     $_POST['remove_members'];
		foreach($remove_mem as $mval)
		 {
		$key = array_search($mval,$explode);
		unset($explode[$key]);
		}
		//$explode = array_values($explode);
		$implode = implode(',',$explode);
		update_post_meta( $post->ID, 'team_members', $implode );
	}

	// Add members in team
	if(!empty($exit_mem)){
	if(isset($_POST['add_member_id']) && !empty($_POST['add_member_id'])):
		
		$add_mem[]  =    $_POST['add_member_id'] ;
	
		$explode    =    explode(',',$exit_mem);
		$result     =    array_merge($add_mem,$explode);
		$result		= 	 array_unique($result); 
		$implode    = implode(',',$result);
				
		update_post_meta( $post->ID, 'team_members', $implode );
		
	endif;
	}else{
		if(isset($_POST['add_member_id']) && !empty($_POST['add_member_id'])):
		update_post_meta( $post->ID, 'team_members', $_POST['add_member_id']);
		endif;
		}
	//remove team campaigns
	if(isset($_POST['remove_teamcampaigns'])){
		
	
		foreach($_POST['remove_teamcampaigns'] as $camp_id){
	$team_title = get_post_meta( $camp_id,'team_campaigns',true );
	$team_camp_id 	= $wpdb->get_var('SELECT ID FROM '.$wpdb->prefix.'posts WHERE post_title = "'.$team_title.'" AND post_status = "publish"' );
	$team_goal = get_post_meta($team_camp_id,'_pfund_gift-goal',true);
			$campaign_goal =  get_post_meta($camp_id,'_pfund_gift-goal',true);
			
			$team_goal = $team_goal - $campaign_goal;
			
			update_post_meta($team_camp_id, '_pfund_gift-goal', $team_goal);
			update_post_meta($camp_id,'team_campaigns','');
			}
		}
}

add_action( 'save_post','update_content_add_team_campaign');
