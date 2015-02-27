<?php
/*  Copyright 2013 John Kleinschmidt  (email : info@cure.org)

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

require_once( PFUND_DIR . '/includes/paypalfunctions.php' );

/**
 * Add a gift donation to the campaign.  Fired from the pfund_add_gift action.
 * This function will update the gift tally for the campaign, add a comment
 * detailing the donation and fire actions for additional processing.
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal.
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function pfund_add_gift( $transaction_array, $post ) {
	global $wpdb;
	$processed_transactions = get_post_meta( $post->ID, '_pfund_transaction_ids' );
	$transaction_nonce = $transaction_array['transaction_nonce'];
	//Make sure this transaction hasn't already been processed.
	if ( is_array( $processed_transactions ) && in_array( $transaction_nonce, $processed_transactions ) ) {
		return;
	} else if ( !is_array( $processed_transactions ) && $processed_transactions == $transaction_nonce ) {
		return;
	}
	if ( $transaction_array['success'] == true) {
		//Update gift tally
		$tally = get_post_meta( $post->ID, '_pfund_gift-tally', true );
		if ( $tally == '' ) {
			$tally = 0;
		}
		if ( ! empty( $transaction_array['tally_amount'] ) && is_numeric( $transaction_array['tally_amount'] ) ) {
			$tally += $transaction_array['tally_amount'];
		} else {
			$tally += $transaction_array['amount'];
		}
		
		update_post_meta( $post->ID, '_pfund_gift-tally', $tally );
		add_post_meta( $post->ID, '_pfund_transaction_ids', $transaction_nonce );

        if ( empty( $transaction_array['anonymous'] ) && (
                empty( $transaction_array['donor_email'] ) ||
                empty( $transaction_array['donor_first_name'] ) ||
                empty( $transaction_array['donor_last_name'] ) ) ) {
            $transaction_array['anonymous'] = true;
        }
        $transaction_array['date'] = new DateTime();

		add_post_meta( $post->ID, '_pfund_transactions', $transaction_array );
		_pfund_update_giver_tally( $post->ID );

		$options = get_option( 'pfund_options' );
		//Add comment for transaction.
		if ( isset( $transaction_array['anonymous'] ) &&
				$transaction_array['anonymous'] == true ) {
			$commentdata = array(
				'comment_post_ID' => $post->ID,
				'comment_author' => '',
				'comment_author_email' => '',                
				'comment_content' => sprintf(
					__( 'An anonymous gift of %s%s was received.', 'pfund' ),
					$options['currency_symbol'],
					number_format_i18n( floatval( $transaction_array['amount'] ), 2 )
				),
				'comment_approved' => 1
			);
		} else {
			$commentdata = array(
				'comment_post_ID' => $post->ID,
				'comment_author' => $transaction_array['donor_first_name'] . ' ' . $transaction_array['donor_last_name'],
				'comment_author_email' => $transaction_array['donor_email'],
				'comment_content' => sprintf(
					__( '%s %s donated %s%s.', 'pfund' ),
					$transaction_array['donor_first_name'],
					$transaction_array['donor_last_name'],
					$options['currency_symbol'],
					number_format_i18n( floatval( $transaction_array['amount'] ), 2 )
				),
				'comment_approved' => 1
			);
		}
		if ( ! empty( $transaction_array['comment'] ) ) {
			$commentdata['comment_content'] = $transaction_array['comment'];
		}
		$commentdata['comment_author_IP'] = '';
		$commentdata['comment_author_url'] = '';
		$commentdata = wp_filter_comment( $commentdata );
		$comment_id = wp_insert_comment( $commentdata );
		add_comment_meta($comment_id, 'pfund_trans_amount', $transaction_array['amount']);
		//Fire action for any additional processing.
		do_action( 'pfund_processed_transaction', $transaction_array, $post );
		$goal = get_post_meta( $post->ID, '_pfund_gift-goal', true );
		if ( $tally >= $goal ) {
			do_action('pfund_reached_user_goal', $transaction_array, $post, $goal );
		}
	}
}

/**
 * Shortcode handler for pfund-campaign-list to display the list of current
 * campaigns.
 * @return string HTML that contains the campaign list.
 */
function pfund_campaign_list() {
	global $wp_query;
	$id = $_REQUEST['id'];
	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
			array(), PFUND_VERSION );
	$post_query = array(
		'post_type' => 'pfund_campaign',
		'orderby' => 'title',
		'order' => 'ASC',
		'posts_per_page' => -1,
		'author' => $id
	);

	if ( isset(  $wp_query->query_vars['pfund_cause_id'] ) ) {
		$post_query['meta_query'] = array(
			array(
				'key' => '_pfund_cause_id',
				'value' => $wp_query->query_vars['pfund_cause_id']
			)
		);
	}
	$campaigns = get_posts($post_query);
	$list_content = '<ul class="pfund-list">';
	foreach ($campaigns as $campaign) {
		$list_content .= '<li>';
		$list_content .= '	<h2>';
		$list_content .= '		<a href="'.get_permalink($campaign->ID).'">'.$campaign->post_title.'</a></h2>';
		$list_content .= '</li>';

	}
	$list_content .= '</ul>';
	return $list_content;
}

/**
 * Shortcode handler for pfund-campaign-list to display the list of current
 * campaigns.
 * @return string HTML that contains the campaign list.
 */
function pfund_team_list() {
	global $wp_query;
	$id = $_REQUEST['id'];
	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
			array(), PFUND_VERSION );
	$post_query = array(
		'post_type' => 'teamcampaigns',
		'orderby' => 'title',
		'order' => 'ASC',
		'exclude' => '3464',
		'posts_per_page' => -1,
		'author' => $id
	);

	if ( isset(  $wp_query->query_vars['pfund_cause_id'] ) ) {
		$post_query['meta_query'] = array(
			array(
				'key' => '_pfund_cause_id',
				'value' => $wp_query->query_vars['pfund_cause_id']
			)
		);
	}
	$campaigns = get_posts($post_query);
	$list_content = '<ul class="pfund-list">';
	foreach ($campaigns as $campaign) {
		$list_content .= '<li>';
		$list_content .= '	<h2>';
		$list_content .= '		<a href="'.get_permalink($campaign->ID).'">'.$campaign->post_title.'</a></h2>';
		$list_content .= '</li>';

	}
	$list_content .= '</ul>';
	return $list_content;
}

/**
 * Shortcode handler for pfund-campaign-permalink to get the permalink for the
 * current campaign.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to get the permalink for by passing a "campaign_id" attribute.
 * @return string the permalink for the current campaign.
 */
function pfund_campaign_permalink( $attrs ) {
    $post = _pfund_get_shortcode_campaign( $attrs );
	if( $post->ID == null || $post->post_type != 'pfund_campaign' ) {
		return '';
	}
	return get_permalink( $post->ID );
}

/**
 * Shortcode handler for pfund-cause-list to display the list of current causes.
 * @return string HTML that contains the campaign list.
 */
function pfund_cause_list() {
	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
			array(), PFUND_VERSION );
	$options = get_option( 'pfund_options' );
	$causes = get_posts(
		array(
			'post_type' => 'pfund_cause',
			'orderby' => 'title',
			'order' => 'ASC',
			'posts_per_page' => -1
		)
	);
	$campaign_list_url = '/'.$options['campaign_slug'].'/?pfund_cause_id=';
	
	$user_can_create = _pfund_current_user_can_create( $options );
	$list_content = '<ul class="pfund-list">';
	foreach ($causes as $cause) {
		$list_content .= '<li>';
		$list_content .= '	<h2>';
		$list_content .= '		<a href="'.$campaign_list_url.$cause->ID.'">'.$cause->post_title.'</a></h2>';
		$list_content .= '<p class="pfund-cause-description">';
		$cause_img = get_post_meta($cause->ID, '_pfund_cause_image', true);
		if ( $cause_img ) {
			$list_content .= '<img class="pfund-image" width="184" src="'.wp_get_attachment_url( $cause_img ).'"/>';
		}
		$list_content .= '<span>';
		$list_content .= get_post_meta($cause->ID, '_pfund_cause_description', true);
		$list_content .= '</span>';
		$list_content .= '</p>';
		if ( $user_can_create ) {
			// $list_content .= '<p>';
			// $list_content .= '<a href="'.get_permalink($cause->ID).'">'.__( 'Create My Page', 'pfund' ).'</a>';
			// $list_content .= '</p>';
		}
		$list_content .= '</li>';

	}
	$list_content .= '</ul>';
	return $list_content;
}
/**/
// function pfund_team_list() {
// 	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
// 			array(), PFUND_VERSION );
// 	$options = get_option( 'pfund_options' );
// 	$team = get_posts(
// 		array(
// 			'post_type' => 'teamcampaigns',
// 			'orderby' => 'title',
// 			'order' => 'ASC',
// 			'exclude' => '3464',
// 			'posts_per_page' => -1
// 		)
// 	);
// 	$campaign_list_url = '/'.$options['teamcampaigns_slug'].'/?pfund_cause_id=';


// 	$user_can_create = _pfund_current_user_can_create( $options );
// 	$list_content = '<ul class="pfund-list">';
// 	foreach ($team as $tm) {
// 		$list_content .= '<li>';
// 		$list_content .= '	<h2>';
// 		$list_content .= '		<a href="'.$campaign_list_url.$tm->ID.'">'.$tm->post_title.'</a></h2>';
// 		$list_content .= '<p class="pfund-cause-description">';
// 		$cause_img = get_post_meta($tm->ID, '_pfund_cause_image', true);
// 		if ( $cause_img ) {
// 			$list_content .= '<img class="pfund-image" width="184" src="'.wp_get_attachment_url( $cause_img ).'"/>';
// 		}
// 		$list_content .= '<span>';
// 		$list_content .= get_post_meta($tm->ID, '_pfund_cause_description', true);
// 		$list_content .= '</span>';
// 		$list_content .= '</p>';
// 		if ( $user_can_create ) {
// 			// $list_content .= '<p>';
// 			// $list_content .= '<a href="'.get_permalink($tm->ID).'">'.__( 'Create My Page', 'pfund' ).'</a>';
// 			// $list_content .= '</p>';
// 		}
// 		$list_content .= '</li>';

// 	}
// 	$list_content .= '</ul>';
// 	return $list_content;
// }

/**/

/**
 * Handler for campaign comments shortcode (pfund-comments).
 * @return string The HTML for the campaign contents.
 */
function pfund_comments() {
	global $post;
	if( $post->ID == null || $post->post_type != 'pfund_campaign' ) {
		return '';
	}
	if ( $post->post_status != 'publish' ) {
		return '';
	}
	if($post->post_type=='teamcampaigns'){$comment_list = get_comments( array( 'post_id'=>$post->ID ) );
	$return_content = '<ul class="pfund-comments">';
	foreach ( $comment_list as $comment ) {
		$return_content .= '<li class="pfund-comment" id="comment-'.$comment->comment_ID.'">';
		$return_content .= '<div class="comment-author vcard">';					
		if ( function_exists( 'get_avatar' ) ) {
			$return_content .= get_avatar( $comment, 32 );
		}
		$return_content .= '<cite class="fn">';
		$return_content .= get_comment_author_link( $comment->comment_ID );
		$return_content .= '</cite>';
		$return_content .= '</div>';
		$return_content .= $comment->comment_content;
		$return_content .= '</li>';
	}
	
	$return_content .= '</ul>';
	
	if ( comments_open() ) {
		$return_content .= '<div id="pfund-comment-resp">';
		$return_content .= '<h3>'.__( 'Post a Comment', 'pfund' ).'</h3>';

		if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) {
			$return_content .= '<p>';
			
			$return_content .= sprintf(
				__( 'You must be <a href="%s">logged in</a> to post a comment.', 'pfund' ),
				wp_login_url( get_permalink() )
			);
			
		}  else {
			$return_content .= '<form action="'.get_option( 'siteurl' ) .'/wp-comments-post.php" method="post" id="commentform">';
			if ( ! is_user_logged_in() ) {
				$return_content .= '<p><input type="text" name="author" id="author" size="22" tabindex="1"/>';
				$return_content .= '<label for="author"><small>'.__( 'Name', 'pfund' ).'</small></label></p>';
				$return_content .= '<p><input type="text" name="email" id="email" size="22" tabindex="2" />';
				$return_content .= '<label for="email"><small>'.__( 'Mail (will not be published)', 'pfund' ).'</small></label></p>';
			}
			$return_content .= '<p><textarea name="comment" id="comment" cols="58" rows="10" tabindex="4"></textarea></p>';
			$return_content .= '<p><input name="submit" type="submit" id="submit" tabindex="5" value="'.esc_attr__( 'Submit Comment', 'pfund').'" />';
			$return_content .= get_comment_id_fields();
			$return_content .= '</p>';
			$return_content .= '</form>';
		}
		$return_content .= '</div>';
	}
	return $return_content;}
	$comment_list = get_comments( array( 'post_id'=>$post->ID ) );
	$return_content = '<ul class="pfund-comments">';
	foreach ( $comment_list as $comment ) {
		$return_content .= '<li class="pfund-comment" id="comment-'.$comment->comment_ID.'">';
		$return_content .= '<div class="comment-author vcard">';					
		if ( function_exists( 'get_avatar' ) ) {
			$return_content .= get_avatar( $comment, 32 );
		}
		$return_content .= '<cite class="fn">';
		$return_content .= get_comment_author_link( $comment->comment_ID );
		$return_content .= '</cite>';
		$return_content .= '</div>';
		$return_content .= $comment->comment_content;
		$return_content .= '</li>';
	}
	
	$return_content .= '</ul>';
	
	if ( comments_open() ) {
		$return_content .= '<div id="pfund-comment-resp">';
		$return_content .= '<h3>'.__( 'Post a Comment', 'pfund' ).'</h3>';

		if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) {
			$return_content .= '<p>';
			
			$return_content .= sprintf(
				__( 'You must be <a href="%s">logged in</a> to post a comment.', 'pfund' ),
				wp_login_url( get_permalink() )
			);
			
		}  else {
			$return_content .= '<form action="'.get_option( 'siteurl' ) .'/wp-comments-post.php" method="post" id="commentform">';
			if ( ! is_user_logged_in() ) {
				$return_content .= '<p><input type="text" name="author" id="author" size="22" tabindex="1"/>';
				$return_content .= '<label for="author"><small>'.__( 'Name', 'pfund' ).'</small></label></p>';
				$return_content .= '<p><input type="text" name="email" id="email" size="22" tabindex="2" />';
				$return_content .= '<label for="email"><small>'.__( 'Mail (will not be published)', 'pfund' ).'</small></label></p>';
			}
			$return_content .= '<p><textarea name="comment" id="comment" cols="58" rows="10" tabindex="4"></textarea></p>';
			$return_content .= '<p><input name="submit" type="submit" id="submit" tabindex="5" value="'.esc_attr__( 'Submit Comment', 'pfund').'" />';
			$return_content .= get_comment_id_fields();
			$return_content .= '</p>';
			$return_content .= '</form>';
		}
		$return_content .= '</div>';
	}
	return $return_content;
}

/**
 * Short code handler for pfund-days-left shortcode.  Returns the number of days
 * before the campaign ends.  Due to timezone differences if the end date is
 * within 24 hours of the current date, days left will return 1.  If the current
 * date is over 24 hours past the end date, days left will return 0.  Otherwise
 * the actual number of days will be returned.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the days left for by passing a "campaign_id" attribute.
 * @return int The number of days left in the campaign
 */
function pfund_days_left( $attrs ) {
    $post = _pfund_get_shortcode_campaign( $attrs );
	
	if($post->post_type=='teamcampaigns'){
		
	$postid = $post->ID;
	if ( _pfund_is_edit_new_campaign() ) {
		$postid = _pfund_get_new_campaign()->ID;
	}
	$end_date = get_post_meta( $post->ID, '_pfund_end-date', true );
	$now = time();
	$diff = ( strtotime( $end_date ) - $now );
	$days = round($diff / 86400);
	if ( $days < -1 ) {
		$days = 0;
	} else if ( $days <= 1 ) {
		$days = 1;
	}
	return $days;
		}
	
	if ( ! pfund_is_pfund_post( $post ) ){
		return '';
	}
	$postid = $post->ID;
	if ( _pfund_is_edit_new_campaign() ) {
		$postid = _pfund_get_new_campaign()->ID;
	}
	$end_date = get_post_meta( $post->ID, '_pfund_end-date', true );
	$now = time();
	$diff = ( strtotime( $end_date ) - $now );
	$days = round($diff / 86400);
	if ( $days < -1 ) {
		$days = 0;
	} else if ( $days <= 1 ) {
		$days = 1;
	}
	return $days;
}


/**
 * Direct causes and campaigns to the proper display template.
 * @return void
 */
function pfund_display_template() {
	global $post;
	if ( $post->post_type == 'teamcampaigns' ) {$options = get_option( 'pfund_options' );
	$script_reqs = array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-datepicker' );
	if ( $options['allow_registration'] ) {
		$script_reqs[] = 'jquery-form';
	}
    wp_enqueue_script( 'pfund-user', pfund_determine_file_location('user','js'),
			$script_reqs, PFUND_VERSION, true );
	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
			array(), PFUND_VERSION );
	$admin_email = get_option( 'admin_email' );
	$script_vars = array(
		'cancel_btn' => __( 'Cancel', 'pfund' ),
		'continue_editing_btn' => __( 'Continue Editing', 'pfund' ),
		'email_exists' => __( 'This email address is already registered', 'pfund' ),
		'invalid_email' =>__( 'Invalid email address', 'pfund' ),
		'login_btn' => __( 'Login', 'pfund' ),
		'mask_passwd' => __( 'Mask password', 'pfund' ),
		'ok_btn' => __( 'Ok', 'pfund' ),
        'processing_msg' => __( 'Processing...', 'pfund' ),        
		'register_btn' => __( 'Register', 'pfund' ),
		'register_fail' => sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you.  Please contact <a href="mailto:%s">us</a>.' ), $admin_email ),
		'reg_wait_msg' => __( 'Please wait while your registration is processed.', 'pfund' ),
		'save_warning' => __( 'Your campaign has not been saved.  If you would like to save your campaign, stay on this page, click on the Edit button and then click on the Ok button.', 'pfund' ),
        'thank_you_msg' => __( 'Thank you for your donation!', 'pfund' ),
		'unmask_passwd' => __( 'Unmask password', 'pfund' ),
		'username_exists' => __( 'This username is already registered', 'pfund' )        
	);
	if ( ! empty( $options['date_format'] ) ) {
		$script_vars['date_format'] = _pfund_get_jquery_date_fmt( $options['date_format'] );
	}
	$login_fn = apply_filters( 'pfund_login_javascript_function', '' );
	if ( ! empty( $login_fn ) ) {
		$script_vars['login_fn'] = $login_fn;
	}
	$register_fn = apply_filters( 'pfund_register_javascript_function', '' );
	if ( ! empty( $register_fn ) ) {
		$script_vars['register_fn'] = $register_fn;
	}
    $script_vars['validation_rules'] = pfund_get_validation_js();
	wp_localize_script( 'pfund-user', 'pfund', $script_vars );

    wp_enqueue_style( 'jquery-ui-pfund', PFUND_URL.'css/smoothness/jquery.ui.pfund.css', array(), '1.8.14' );    

	wp_enqueue_script( 'jquery-validationEngine', PFUND_URL.'js/jquery.validationEngine.js', array( 'jquery'), 1.7, true );
	wp_enqueue_style( 'jquery-validationEngine', PFUND_URL.'css/jquery.validationEngine.css', array(), 1.7 );

	$templates[] = 'page.php';
	$template = apply_filters( 'page_template', locate_template( $templates ) );

	if( '' != $template ) {
		load_template( $template );
		// The exit tells WP to not try to load any more templates
		exit;
	}}
	if ( ! pfund_is_pfund_post( $post, true ) ){
		//Only change the template for pfund causes and campaigns
		return;
	}
	$options = get_option( 'pfund_options' );
	$script_reqs = array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-datepicker' );
	if ( $options['allow_registration'] ) {
		$script_reqs[] = 'jquery-form';
	}
    wp_enqueue_script( 'pfund-user', pfund_determine_file_location('user','js'),
			$script_reqs, PFUND_VERSION, true );
	wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'),
			array(), PFUND_VERSION );
	$admin_email = get_option( 'admin_email' );
	$script_vars = array(
		'cancel_btn' => __( 'Cancel', 'pfund' ),
		'continue_editing_btn' => __( 'Continue Editing', 'pfund' ),
		'email_exists' => __( 'This email address is already registered', 'pfund' ),
		'invalid_email' =>__( 'Invalid email address', 'pfund' ),
		'login_btn' => __( 'Login', 'pfund' ),
		'mask_passwd' => __( 'Mask password', 'pfund' ),
		'ok_btn' => __( 'Ok', 'pfund' ),
        'processing_msg' => __( 'Processing...', 'pfund' ),        
		'register_btn' => __( 'Register', 'pfund' ),
		'register_fail' => sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you.  Please contact <a href="mailto:%s">us</a>.' ), $admin_email ),
		'reg_wait_msg' => __( 'Please wait while your registration is processed.', 'pfund' ),
		'save_warning' => __( 'Your campaign has not been saved.  If you would like to save your campaign, stay on this page, click on the Edit button and then click on the Ok button.', 'pfund' ),
        'thank_you_msg' => __( 'Thank you for your donation!', 'pfund' ),
		'unmask_passwd' => __( 'Unmask password', 'pfund' ),
		'username_exists' => __( 'This username is already registered', 'pfund' )        
	);
	if ( ! empty( $options['date_format'] ) ) {
		$script_vars['date_format'] = _pfund_get_jquery_date_fmt( $options['date_format'] );
	}
	$login_fn = apply_filters( 'pfund_login_javascript_function', '' );
	if ( ! empty( $login_fn ) ) {
		$script_vars['login_fn'] = $login_fn;
	}
	$register_fn = apply_filters( 'pfund_register_javascript_function', '' );
	if ( ! empty( $register_fn ) ) {
		$script_vars['register_fn'] = $register_fn;
	}
    $script_vars['validation_rules'] = pfund_get_validation_js();
	wp_localize_script( 'pfund-user', 'pfund', $script_vars );

    wp_enqueue_style( 'jquery-ui-pfund', PFUND_URL.'css/smoothness/jquery.ui.pfund.css', array(), '1.8.14' );    

	wp_enqueue_script( 'jquery-validationEngine', PFUND_URL.'js/jquery.validationEngine.js', array( 'jquery'), 1.7, true );
	wp_enqueue_style( 'jquery-validationEngine', PFUND_URL.'css/jquery.validationEngine.css', array(), 1.7 );

	$templates[] = 'page.php';
	$template = apply_filters( 'page_template', locate_template( $templates ) );

	if( '' != $template ) {
		load_template( $template );
		// The exit tells WP to not try to load any more templates
		exit;
	}
}

/**
 * Shortcode handler for pfund-donate shortcode.  Displays a button for
 * accepting donations.
 * @return string HTML for a donate button.
 */
function pfund_donate_button() {
	global $post;
	$options = get_option( 'pfund_options' );
	if($post->post_type=='teamcampaigns'){$page_url = get_permalink( $post );
	$gentime = time();
	$returnparms = array(
		'g' => $gentime,
		'n' => 	wp_create_nonce( 'pfund-donate-campaign'.$post->ID.$gentime ),
		'pfund_action'=>'donate-campaign',
		't' => 'pp'
	);
	$return_url = $page_url . '?' . http_build_query($returnparms);
	$returnparms['t'] = 'ipn';
	$notify_url = $page_url . '?' . http_build_query($returnparms);
	$donate_btn = $options['paypal_donate_btn'];
	if ( ! empty( $donate_btn ) ) {
		$btn_doc = new DOMDocument();
		$btn_doc->loadHTML( $donate_btn );
		$form_node = $btn_doc->getElementsByTagName('form')->item(0);
		$form_node->setAttribute( 'class' , 'pfund-donate-form' );
		_pfund_create_input_node( $btn_doc, $form_node, 'return', $return_url );
		_pfund_create_input_node( $btn_doc, $form_node, 'cancel_return', $page_url );
		_pfund_create_input_node( $btn_doc, $form_node, 'notify_url', $notify_url );
		$tmp_node = $btn_doc->createElement( 'br' );
		$form_node->appendChild($tmp_node);
		$tmp_node = $btn_doc->createElement( 'label',
				__('Anonymous gift', 'pfund')
		);
		$tmp_node->setAttribute( 'for' , 'pfund-anonymous-donate' );
		$form_node->appendChild($tmp_node);
		$tmp_node = _pfund_create_input_node( $btn_doc, $form_node, 'custom', 'anon', 'checkbox' );
		$tmp_node->setAttribute( 'id', 'pfund-anonymous-donate' );
		$donate_btn = $btn_doc->saveHTML();
		$form_start = strpos( $donate_btn, '<form' );
		$form_length = (strpos( $donate_btn, '</form>', $form_start ) - $form_start) + 7;
		$donate_btn = substr( $donate_btn, $form_start, $form_length );
	}
	$donate_btn = apply_filters( 'pfund_donate_button', $donate_btn, $page_url, $return_url );
	return $donate_btn;}
	if ( ! pfund_is_pfund_post() ) {
		return '';
	}	
	$page_url = get_permalink( $post );
	$gentime = time();
	$returnparms = array(
		'g' => $gentime,
		'n' => 	wp_create_nonce( 'pfund-donate-campaign'.$post->ID.$gentime ),
		'pfund_action'=>'donate-campaign',
		't' => 'pp'
	);
	$return_url = $page_url . '?' . http_build_query($returnparms);
	$returnparms['t'] = 'ipn';
	$notify_url = $page_url . '?' . http_build_query($returnparms);
	$donate_btn = $options['paypal_donate_btn'];
	if ( ! empty( $donate_btn ) ) {
		$btn_doc = new DOMDocument();
		$btn_doc->loadHTML( $donate_btn );
		$form_node = $btn_doc->getElementsByTagName('form')->item(0);
		$form_node->setAttribute( 'class' , 'pfund-donate-form' );
		_pfund_create_input_node( $btn_doc, $form_node, 'return', $return_url );
		_pfund_create_input_node( $btn_doc, $form_node, 'cancel_return', $page_url );
		_pfund_create_input_node( $btn_doc, $form_node, 'notify_url', $notify_url );
		$tmp_node = $btn_doc->createElement( 'br' );
		$form_node->appendChild($tmp_node);
		$tmp_node = $btn_doc->createElement( 'label',
				__('Anonymous gift', 'pfund')
		);
		$tmp_node->setAttribute( 'for' , 'pfund-anonymous-donate' );
		$form_node->appendChild($tmp_node);
		$tmp_node = _pfund_create_input_node( $btn_doc, $form_node, 'custom', 'anon', 'checkbox' );
		$tmp_node->setAttribute( 'id', 'pfund-anonymous-donate' );
		$donate_btn = $btn_doc->saveHTML();
		$form_start = strpos( $donate_btn, '<form' );
		$form_length = (strpos( $donate_btn, '</form>', $form_start ) - $form_start) + 7;
		$donate_btn = substr( $donate_btn, $form_start, $form_length );
	}
	$donate_btn = apply_filters( 'pfund_donate_button', $donate_btn, $page_url, $return_url );
	return $donate_btn;
}


/**
 * Shortcode handler for pfund-authorize-net-donate-form shortcode.  
 * Displays Authorize.Net donate form
 * @return string HTML for donate form.
 */
function pfund_authorize_net_donate_form() {
	global $post;
	$options = get_option( 'pfund_options' );
	if ( ! pfund_is_pfund_post() ) {
		return '';
	}	
	
	$api_login_id = $options['authorize_net_api_login_id'];
	$transaction_key = $options['authorize_net_transaction_key'];
	$donate_form = '';
	
	if ( ! empty( $transaction_key ) && ! empty( $api_login_id ) ) {
        $gentime = time();
		$donate_form = '<div class="pfund-auth-net-donate"><a href="#">Donate</a></div>';
        $donate_form .= '<form class="pfund-auth-net-form">';
        $donate_form .= _pfund_auth_net_input( 'cc_first_name', __( 'First Name', 'pfund' ) );
		$donate_form .= _pfund_auth_net_input( 'cc_last_name', __( 'Last Name', 'pfund' ) );
        $donate_form .= _pfund_auth_net_input( 'cc_address', __( 'Address', 'pfund' ) );
        $donate_form .= _pfund_auth_net_input( 'cc_city', __( 'City', 'pfund' ) );
		$donate_form .= _pfund_auth_net_state_options();
        $donate_form .= _pfund_auth_net_input( 'cc_zip', __( 'Zip', 'pfund' ) );
		$donate_form .= _pfund_auth_net_input( 'cc_email', __( 'Email', 'pfund' ) );
        $donate_form .= _pfund_auth_net_input( 'cc_num', __( 'Credit Card Number', 'pfund' ) );
		$donate_form .= _pfund_auth_net_month_options();
        $donate_form .= _pfund_auth_net_year_options();
        $donate_form .= _pfund_auth_net_input( 'cc_cvv2', __( 'Security Code', 'pfund' ) );
		$donate_form .= _pfund_auth_net_input( 'cc_amount', __( 'Donation Amount', 'pfund' ) );
        $donate_form .= '<div id="pfund-input-anonymous"><input id="pfund-input-anonymous-checkbox" type="checkbox" name="anonymous" value="1"> Anonymous gift</div>';
        $donate_form .= '<input type="hidden" name="post_id" value="' . $post->ID . '">';
        $donate_form .= '<input type="hidden" name="g" value="' . $gentime . '">';
        $donate_form .= '<button type="submit" value="Donate" id="pfund_donate_button">'.__( 'Donate', 'pfund' ).'</button>';
        $donate_form .= wp_nonce_field( 'pfund-donate-campaign'.$post->ID.$gentime, 'n', false, false );
        $donate_form .= '<div class="pfund-auth-net-secure-donations"><a href="#" class="pfund-auth-net-secure-donations-link">';
        $donate_form .= __( 'Donations are processed securely via Authorize.Net', 'pfund' ).'</a></div>';
        $donate_form .= _pfund_auth_net_secure_donation_text();
        $donate_form .='</form>';
        wp_enqueue_script( 'pfund-auth-net', pfund_determine_file_location('auth.net','js'), array(), PFUND_VERSION, true );        
	}
	return $donate_form;
}

/**
 * Shortcode handler for pfund-edit to generate campaign creation/editing form
 * and button to edit the personal fundraising fields.
 * @return string HTML for form and edit button.
 */
function pfund_edit() {
	global $post, $current_user;
	$current_user = wp_get_current_user();
	/**/
	if ( ! _pfund_current_user_can_create( ) ) {
		return '';	
	}
	if ( $post->post_type == 'teamcampaigns' ) {
		if( $post->post_type == 'teamcampaigns') {
		$editing_campaign = _pfund_is_edit_new_campaign();
		if ( $editing_campaign ) {
			$campaign = _pfund_get_new_campaign();
			$campaign_id = $campaign->ID;
			$campaign_title = $campaign->post_title;
		} else {
			$campaign_title = $post->post_title;			
			$campaign_id = null;
		}
		$default_goal = get_post_meta( $post->ID, '_pfund_cause_default_goal', true);
	} else {
		$editing_campaign = true;
		$campaign_id = $post->ID;
		$campaign_title = $post->post_title;
		$campaign = $post;
		$default_goal = '';
	}

	$wait_title = esc_attr__( 'Please wait', 'pfund' );
	if ( ($editing_campaign) && $post->post_name=='team-creation'  ) {
		$dialog_title = esc_attr__( 'Edit Campaign', 'pfund' );
		$dialog_desc = esc_html__( 'Change your campaign by editing the information below.', 'pfund' );
		$wait_desc = esc_html__( 'Please wait while your campaign is updated.', 'pfund' );
		$dialog_id = 'pfund-edit-dialog';
	} else {
		if($post->post_name=='team-creation'){
		$dialog_title = esc_attr__( 'Create Team Campaign', 'pfund' );
		$dialog_desc = esc_html__( 'Please fill in the following information to create your Team campaign.', 'pfund' );
		$wait_desc = esc_html__( 'Please wait while your Team campaign is created.', 'pfund' );
		$dialog_id = 'pfund-add-dialog';
		}
	}
        $dropdown_options = 
	$return_form = '<div id="pfund-wait-dialog" style="display:none;" title="'.$wait_title.'">';
	$return_form .= '<div>'.$wait_desc.'</div>';
	$return_form .= '</div>';
	$return_form .= '<div id="'.$dialog_id.'" style="display:none;" title="'.$dialog_title.'">';
	$return_form .= '<div>'.$dialog_desc.'</div>';
	$return_form .= '<form enctype="multipart/form-data" action="" method="post" name="pfund_form" id="pfund-form">';

	if (($post->ID !='') && ($post->post_name!='team-creation')) {
			
		$return_form .= '	<input type="hidden" name="pfund_action" value="update-teamcampaigns"/>';
		$return_form .= '	<input id="pfund-teamcampaigns-id" type="hidden" name="pfund_teamcampaigns_id" value="'.$post->ID.'"/>';
		$return_form .= wp_nonce_field( 'pfund-update-teamcampaigns'.$post->ID, 'n', true , false );
	} else {
		/**/
		global $wpdb;
		$table_name = $wpdb->prefix . "posts"; 
        $sql = $wpdb->get_results("SELECT DISTINCT(`post_title`),`id` FROM ".$table_name." WHERE `post_type` = 'pfund_cause' AND `post_status`='publish'");
$return_form .= '<label for="cause-slug"><b>Select Cause</b><abbr title="required">*</abbr></label><select name="cause-slug"><option value="">Select Any One</option>';
foreach($sql as $data)
{
$return_form .= '<option value="'. $data->id.'" '.(($value==$data->post_title)? 'selected=selected':"").'>'.$data->post_title.'</option>';

 }
 $return_form .= '</select>';
/**/
		$return_form .= '	<input type="hidden" name="pfund_action" value="create-teamcampaigns"/>
		<input type="hidden" name="post_type" value="teamcampaigns"/>';
		$return_form .= wp_nonce_field( 'pfund-create-teamcampaigns'.$post->ID, 'n', true , false );
	}
	
	$return_form .= pfund_render_fields1( $post->ID, $campaign_title, $editing_campaign, $default_goal );
    //$mee=get_post_meta($campaign_id,'_pfund_message');

//$return_form .='<label for="pfund-photo" style="font-weight:bold;">Personal Photo<abbr title="required">*</abbr></label>	<input type="file" name="pfund-photo" id="pfund-photo" >';
    
	//$return_form .='<label for="pfund-message" style="font-weight:bold;">Personal Message<abbr title="required">*</abbr></label>	
	//<textarea name="pfund-message" id="pfund-message" style="height:200px">'.$me[0].'</textarea>';
 $return_form .= '</select>';?>

<?php
    
	$return_form .= '</form>';
	$return_form .= '</div>';
	//$message = get_post_meta($post->ID,'_pfund_message',true);
	//$img = get_post_meta($post->ID,'_pfund_photo',true);
	//$image = wp_get_attachment_url($img);
	//if($image!=''){$return_form .="<img src=".$image." style='width:250px;margin-top:10px;margin-bottom:10px;' />";}
	//if($message!=''){$return_form .= "<p style='margin:0; font-weight:bold;'>Personal Message : </p><div style='margin-bottom:20px;'>".$message."</div>";}
	//$return_form .= do_shortcode('[pfund-donate]')."<p></p>";
	$validateSlug = array(
		'file' => PFUND_URL.'validate-slug.php',
		'alertTextLoad' => __( 'Please wait while we validate this url', 'pfund' ),
		'alertText' => __( '* Another fundraiser has the same First and Last Name.  Please use a middle name or letter to make your campaign unique.', 'pfund' )
	);
	if ( $editing_campaign ) {
		$validateSlug['extraData'] = $campaign_id;
	}
	//$return_form .= '<button class="pfund-edit-btn">'. __( 'Edit', 'pfund' ).'</button>';
	$matv = get_post_meta($post->ID,'team_members');
	$members_id[] = explode(',',$matv[0]);
	global $wpdb;
	$sql1 = array();
    /*$table_name = $wpdb->prefix . "posts"; 
	$table_name1 = $wpdb->prefix . "postmeta"; 
	
	$sql1 = $wpdb->get_results("SELECT post_title,post_author,id,post_name FROM ".$table_name." as a INNER JOIN ".$table_name1." as b WHERE a.`ID` = b.`post_id` AND `post_type` = 'pfund_campaign' AND `meta_key`='team_campaigns' AND `meta_value`='".$matv[0]."'"); */
	foreach($members_id[0] as $mem_id){
		if($mem_id != '')
		$sql1[] = $wpdb->get_results("SELECT fname, lname FROM ". $wpdb->prefix."events_attendee WHERE id = ".$mem_id);
		}
	//foreach($sql1 as $data1){
	//foreach($data1 as $data2){	
	//$p[]=$data2;}
	//}
   // $result = array_unique($p);
   
   /* added 03-02-2015*/
	$team_title = get_the_title( $post->ID );
	$personal_ids 	= $wpdb->get_results('SELECT post_id FROM '.$wpdb->prefix.'postmeta WHERE meta_value = "'.$team_title.'"' );
	$data_array = array();
	foreach($personal_ids as $personal_id){
		$data_array[] = array('id' => $personal_id->post_id,
					'fname' => get_post_meta($personal_id->post_id,'_pfund_first-name',true),
					'lname' => get_post_meta($personal_id->post_id,'_pfund_last-name',true),
					'url' => get_post_meta($personal_id->post_id,'_pfund_camp-location',true)
					);
		
		}
/*end editing*/
    $i=0;
	$return_form .='<div style="padding:10px 0px;margin-top:20px;">
	<h2>Lists Of Team Members</h2>
	<table>
	<thead><tr><th style="width:150px;">Team Member Name</th><th>|        Team Members with Fundraising Pages</th></tr></thead>
	<tbody>
	';
	foreach($sql1 as $data1){
		foreach($data1 as $data_member){
	//$raised=get_post_meta($data1->id,'_pfund_gift-tally');
	//if($data1->post_author != $same){	
	//$userdata=get_userdata( $data1 );
        if($data_member->fname!=''){ 	
	$return_form .='<tr><td style="text-align:center;">'.$data_member->fname.' '.$data_member->lname.'</td><td style="text-align:center;">No fundraising page.</td></tr>';
	}	
	//}
	/*$total[] =$raised[0];
	$same=$data1->post_author;
	$i++;*/}
	}
	foreach($data_array as $data){
        if($data['fname']!=''){ 	
	$return_form .='<tr><td style="text-align:center;">'.$data['fname'].' '.$data['lname'].'</td><td style="text-align:center;"><a target="_blank" href="'.site_url().'/give/'.$data['url'].'">'.$data['fname'].' '.$data['lname'].'</a></td></tr>';
		}
	}
	$return_form .='</table>';
   /* if(!empty($total)){
	//$return_form .='<h2>Total Raised By Team :- $'.array_sum($total).'</h2>';
	$return_form .=
	'<script type="text/javascript">
	jQuery( document ).ready(function() {
	jQuery( "ul li:nth-child(2) .highlight" ).text( "$'.array_sum($total).'" );
	jQuery( "ul li:nth-child(3).pfund-stat ").hide();
	});
	</script>';
	}*/
	
	$return_form .='</div>';
	return $return_form;	
	}
	
	/**/
	
	if ( ! pfund_is_pfund_post() ){
		return '';
	} else if ( ! _pfund_current_user_can_create( ) ) {
		return '';	
	} else if ( $post->post_type == 'pfund_campaign' && $post->post_author != $current_user->ID ) {
		return '';
	}
	if( $post->post_type == 'pfund_cause') {
		$editing_campaign = _pfund_is_edit_new_campaign();
		if ( $editing_campaign ) {
			$campaign = _pfund_get_new_campaign();
			$campaign_id = $campaign->ID;
			$campaign_title = $current_user->user_firstname.' '.$current_user->user_lastname;//$campaign->post_title;
		} else {
			$campaign_title = $current_user->user_firstname.' '.$current_user->user_lastname;
			$campaign_id = null;
		}
		$default_goal = get_post_meta( $post->ID, '_pfund_cause_default_goal', true);
	} else {
		$editing_campaign = true;
		$campaign_id = $post->ID;
		$campaign_title = $post->post_title;
		$campaign = $post;
		$default_goal = '';
	}

	$wait_title = esc_attr__( 'Please wait', 'pfund' );
	if ( $editing_campaign ) {
		$dialog_title = esc_attr__( 'Edit Campaign', 'pfund' );
		$dialog_desc = esc_html__( 'Change your campaign by editing the information below.', 'pfund' );
		$wait_desc = esc_html__( 'Please wait while your campaign is updated.', 'pfund' );
		$dialog_id = 'pfund-edit-dialog';
	} else {
		$dialog_title = esc_attr__( 'Create Campaign', 'pfund' );
		$dialog_desc = esc_html__( 'Please fill in the following information to create your campaign.', 'pfund' );
		$wait_desc = esc_html__( 'Please wait while your campaign is created.', 'pfund' );
		$dialog_id = 'pfund-add-dialog';
	}
        $dropdown_options = 
	$return_form = '<div id="pfund-wait-dialog" style="display:none;" title="'.$wait_title.'">';
	$return_form .= '<div>'.$wait_desc.'</div>';
	$return_form .= '</div>';
	$return_form .= '<div id="'.$dialog_id.'" style="display:none;" title="'.$dialog_title.'">';
	$return_form .= '<div>'.$dialog_desc.'</div>';
	$return_form .= '<form enctype="multipart/form-data" action="" method="post" name="pfund_form" id="pfund-form">';

	if ( $editing_campaign ) {
			
		$return_form .= '	<input type="hidden" name="pfund_action" value="update-campaign"/>';
		$return_form .= '	<input id="pfund-campaign-id" type="hidden" name="pfund_campaign_id" value="'.$campaign_id.'"/>';
		$return_form .= wp_nonce_field( 'pfund-update-campaign'.$campaign_id, 'n', true , false );
		
	} else {
		$return_form .= '	<input type="hidden" name="pfund_action" value="create-campaign"/>';
		$return_form .= wp_nonce_field( 'pfund-create-campaign'.$post->ID, 'n', true , false );
		
	}
	
	$return_form .= pfund_render_fields( $campaign_id, $campaign_title, $editing_campaign, $default_goal );
	//$return_form .='<label>First Name</label><input type="text" name="pfund_first-name" value=""/>';

//$return_form .='<label for="pfund-photo" style="font-weight:bold;">Personal Photo<abbr title="required">*</abbr></label>	<input type="file" name="pfund-photo" id="pfund-photo" >';
    //$me=get_post_meta($campaign_id,'_pfund_message');
	//$return_form .='<label for="pfund-message" style="font-weight:bold;">Personal Message<abbr title="required">*</abbr></label>	
	//<textarea name="pfund-message" id="pfund-message" style="height:200px">
	//'.$me[0].'
	//</textarea>';
	

$value = get_post_meta( $campaign_id, 'team_campaigns', true );
global $wpdb;
$table_name = $wpdb->prefix . "posts";
$table_name1 = $wpdb->prefix . "postmeta";
 
$sql = $wpdb->get_results("SELECT DISTINCT(`post_title`) FROM ".$table_name." as a INNER JOIN ".$table_name1." as b WHERE  a.`ID` = b.`post_id` AND `post_type` = 'teamcampaigns' AND `post_status`='publish'");


$return_form .= '<label for="pfund-teamcampaigns" style=" font-weight: bold;margin-right: 5px;">Select Team </label>';
$return_form .= '<select name="team_campaigns"><option value="" selected="selected" >Select Team</option>';
foreach($sql as $data)
{
	if($data->post_title!='team-creation'){
$return_form .= '<option value="'. $data->post_title.'"' . /*(($value == $data->post_title) ? "selected" : "selected").*/'>'.$data->post_title.'</option>';	

	}

 }
 $return_form .= '</select>';?>

<?php
	
	$return_form .= '</form>';
	$return_form .= '</div>';
	//$message = get_post_meta($campaign_id,'_pfund_message',true);
	//$img = get_post_meta($campaign_id,'_pfund_photo',true);
	//$image = wp_get_attachment_url($img);
	//if($image!=''){$return_form .="<img src=".$image." style='width:250px;margin-top:10px;margin-bottom:10px;' />";}
	//if($message!=''){$return_form .= "<p style='margin:0; font-weight:bold;'>Personal Message : </p><div style='margin-bottom:20px;'>".$message."</div>";}
	
	//$return_form .= do_shortcode('[pfund-donate]')."<p></p>";
	$validateSlug = array(
		'file' => PFUND_URL.'validate-slug.php',
		'alertTextLoad' => __( 'Please wait while we validate this location', 'pfund' ),
		'alertText' => __( '* This location is already taken', 'pfund' )
	);
	if ( $editing_campaign ) {
		$validateSlug['extraData'] = $campaign_id;
	}
	$return_form .= '<button class="pfund-edit-btn">'. __( 'Edit', 'pfund' ).'</button>';

	return $return_form;	
}



/**
 * Shortcode handler for pfund-giver-list shortcode.  Returns markup for the
 * list of supporters for the current campaign.
 * @param array $attrs the attributes for the shortcode.  The supported
 * attributes are:
 * -- row_max -- Number of supporters to display in one row
 * -- row_end_class -- Class to apply to last support in a row.
 * -- campaign_id -- The id of the campaign to display the giver list for.
 * @return string the HTML representing the list of supporters for the current
 * campaign.
 */
function pfund_giver_list( $attrs ) {
    $post = _pfund_get_shortcode_campaign( $attrs );
	if ( ! empty( $attrs ) && ! empty( $attrs['row_max'] ) &&
			! empty( $attrs['row_end_class'] ) ) {
		$row_end_class = $attrs['row_end_class'];
		$row_max = $attrs['row_max'];
	} else {
		$row_end_class = 'row-end clearfix';
		$row_max = 3;
	}
	$max_givers = -1;
	if ( ! empty( $attrs['max_givers'] ) ) {
		$max_givers = intval( $attrs['max_givers'] );
	}
	if ( ! pfund_is_pfund_post() ) {
		return '';
	} else {		
		$givers = get_post_meta( $post->ID, '_pfund_givers', true );
		if ( empty( $givers ) ) {
			return '';
		}
		if ( $max_givers > -1 && count($givers) > $max_givers ) {
			$email_array = array_rand( $givers, $max_givers );
		} else {
			$email_array = array_keys( $givers );
		}
		$giver_count = 0;
		$list = '<ul class="pfund-supporters-list">';
		foreach( $email_array as $email ) {
			$donor = $givers[$email];
			$giver_count++;
			$class = 'pfund-supporter';
			if ($giver_count % $row_max == 0) {
				$class .= ' '.$row_end_class;
			}
			$list .= '<li class="'.$class.'">';
			$list .= '	<span class="pfund-supporter-img">';
			$list .= get_avatar($email, '50');
			$list .= '	</span>';
			$list .= '	<span class="pfund-supporter-name">';
			$list .= $donor['first_name'].' '.$donor['last_name'];
			$list .= '	</span>';
			$list .= '</li>';
		}
		$list .= '</ul>';
		return $list;
	}
}

/**
 * Handler for actions performed on a cause.
 * @param mixed $posts The current posts provided by the_posts filter.
 * @return mixed $posts The current posts provided by the_posts filter.
 */
function pfund_handle_action( $posts ) {
	global $pfund_processed_action, $pfund_processing_action, $wp_query;
	if ( empty ( $posts ) ) {
		return $posts;
	}		

	$post = $posts[0];
	if ( isset( $wp_query->query_vars['pfund_action'] ) 
			&& ! $pfund_processed_action && ! $pfund_processing_action ) {
			
		$pfund_processing_action = true;
		$action = $wp_query->query_vars['pfund_action'];
		
		switch( $action ) {
			case 'teamcampaigns-created':
                _pfund_campaign_created1( $post );
                break;
				case 'update-teamcampaigns':
				_pfund_save_teamcamp( $post, 'update' );
				update_post_meta($post->ID,'team_campaigns',$_POST['team_campaigns']);
				break;
				case 'team-list':
				$posts = _pfund_team_list_page();
				break;
				
		}
		if($_POST['post_type']=='teamcampaigns'){
			switch( $action ) {
			case 'campaign-list':
				$posts = _pfund_campaign_list_page();
				break;
			case 'cause-list':
				$posts = _pfund_cause_list_page();
				break;
			case 'team-list':
				$posts = _pfund_team_list_page();
				break;	
			case 'create-campaign':
				_pfund_save_camp( $post, 'add' );
				break;
			case 'create-teamcampaigns':
				_pfund_save_teamcamp( $post, 'add' );
				break;	
            case 'campaign-created':
                _pfund_campaign_created( $post );
                break;
			case 'teamcampaigns-created':
                _pfund_campaign_created1( $post );
                break;
			case 'donate-campaign':
				_pfund_process_donate( $post );
				break;
			case 'donate-thanks':
				_pfund_display_thanks();
				break;
			case 'update-campaign':
				_pfund_save_camp( $post, 'update' );
				update_post_meta($post->ID,'team_campaigns',$_POST['team_campaigns']);
				break;
				case 'update-teamcampaigns':
				_pfund_save_teamcamp( $post, 'update' );
				update_post_meta($post->ID,'team_campaigns',$_POST['team_campaigns']);
				break;
			case 'user-login':
				_pfund_save_camp( $post, 'user-login' );
				break;
		}
			}	
		
		if ( ! in_array( $action, array( 'cause-list', 'campaign-list' ) ) ) {			
			if ( ! pfund_is_pfund_post( $post ) ){
				return $posts;
			}
			if ( _pfund_is_edit_new_campaign() ) {
				$referer_action = 'pfund-'.$action._pfund_get_new_campaign()->ID;
			} else {
				$referer_action = 'pfund-'.$action.$post->ID;
			}
			if( $action == 'donate-campaign' ) {
				$referer_action .= $_REQUEST['g'];
			}			
			if( $action == 'user-login' ) {
				global $current_user;
				get_currentuserinfo();
				$save_user = $current_user;
				$current_user = new WP_User(0);
				check_admin_referer( $referer_action, 'n' );
				$current_user = $save_user;
			} else {
				check_admin_referer( $referer_action, 'n' );
			}
		}
	
			
		switch( $action ) {
			case 'team-list':
				$posts = _pfund_team_list_page();
				break;
			case 'campaign-list':
				$posts = _pfund_campaign_list_page();
				break;
			case 'cause-list':
				$posts = _pfund_cause_list_page();
				break;
			case 'create-campaign':
				_pfund_save_camp( $post, 'add' );
				break;
			case 'create-teamcampaigns':
				_pfund_save_camp( $post, 'add' );
				break;	
            case 'campaign-created':
                _pfund_campaign_created( $post );
                break;
			case 'teamcampaigns-created':
                _pfund_campaign_created1( $post );
                break;
			case 'donate-campaign':
				_pfund_process_donate( $post );
				break;
			case 'donate-thanks':
				_pfund_display_thanks();
				break;
			case 'update-campaign':
				_pfund_save_camp( $post, 'update' );
				update_post_meta($post->ID,'team_campaigns',$_POST['team_campaigns']);
				break;
				case 'update-teamcampaigns':
				_pfund_save_teamcamp( $post, 'update' );
				update_post_meta($post->ID,'team_campaigns',$_POST['team_campaigns']);
				break;
			case 'user-login':
				_pfund_save_camp( $post, 'user-login' );
				break;
		}
		if( ! empty( $posts ) ) {
			$wp_query->is_home = false;
			$wp_query->queried_object = $posts[0];
			$wp_query->queried_object_id = $posts[0]->ID;
			$wp_query->is_page = true;
			$wp_query->is_singular = true;
		}
		$pfund_processed_action = true;
		$pfund_processing_action = false;
	}
	return $posts;
}

/**
 * For personal fundraising campaigns, if the user just updated a campaign
 * title, pull the new value from the request; otherwise just use the
 * saved title.
 * @param string $atitle The current title.
 * @param int $post_id The post to display the title for.
 * @return string the title to display.
 */
function pfund_handle_title( $atitle, $post_id = 0 ) {
    global $post;
	if ( ! pfund_is_pfund_post( ) || $post_id != $post->ID ){
		return $atitle;
	}
	return pfund_get_value( $_REQUEST, 'pfund-camp-title', $atitle );
}

/**
 * Shortcode handler for pfund-progress-bar shortcode.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the progress bar for by passing a "campaign_id" attribute.
 * @return string HTML markup for progress bar.
 */
function pfund_progress_bar( $attrs ) {
    $post = _pfund_get_shortcode_campaign( $attrs );
	/*if ( ! pfund_is_pfund_post( $post ) ){
		return '';
	}*/
	$postid = $post->ID;
	if ( _pfund_is_edit_new_campaign() ) {
		$postid = _pfund_get_new_campaign()->ID;
	}

	$options = get_option( 'pfund_options' );
	$goal = get_post_meta( $postid, '_pfund_gift-goal', true );
	$tally = get_post_meta( $postid, '_pfund_gift-tally', true );
	if ( $tally == '' ) {
		$tally = 0;
	}
	$remaining = ($goal - $tally);
	$funding_percentage = 1;
	if ( $remaining <= 0 ) {
		$funding_percentage = 1;
	} else if ( $tally < $goal ) {
		$funding_percentage = ($tally / $goal);
	}
	$goal_length = intval( ( 240 * $funding_percentage ) );
	$return_content = '<div class="pfund-progress-meter ">';
	$return_content .= '	<p class="pfund-progress-met">';
	$return_content .= '		<span class="pfund-amount">'.$options['currency_symbol'].'';
	$return_content .=				number_format_i18n( floatval( $tally ) );
	$return_content .= '		</span> '.__('Raised', 'pfund');
	$return_content .= '	</p>';
	$return_content .= '	<div class="pfund-progress-bar">';
	$return_content .= '		<div class="pfund-amount-raised" style="width:'.$goal_length.'px;"></div>';
	$return_content .= '	</div>';
	$return_content .= '	<p class="pfund-progress-goal">'.__('Goal:', 'pfund').' ';
	$return_content .= '		<span class="pfund-amount">'.$options['currency_symbol'].'';
	$return_content .=				number_format_i18n( floatval( $goal ) );
	$return_content .= '		</span>';
	$return_content .= '	</p>';
	$return_content .= '</div>';
	return $return_content;
}

/**
 * Send an email when a campaign receives a donation
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal. 
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function pfund_send_donate_email( $transaction_array, $post ) {
    $options = get_option( 'pfund_options' );
    $author_data = pfund_get_contact_info( $post, $options );
	if ( ! empty( $author_data->user_email ) && 
            apply_filters ('pfund_mail_on_donate', true, $transaction_array, $author_data ) ) {		
		$campaignUrl = get_permalink( $post );
		$trans_amount = number_format_i18n( floatval( $transaction_array['amount'] ), 2 );
		if ( $options['mandrill'] ) {
			$merge_vars = array(
				'NAME' => $author_data->display_name,
				'CAMP_TITLE' => $post->post_title,
				'CAMP_URL' => $campaignUrl,
				'DONATE_AMT' => $options['currency_symbol'].$trans_amount
			);			
			if ( isset( $transaction_array['anonymous'] ) &&
					$transaction_array['anonymous'] == true ) {
				$merge_vars['DONOR_ANON'] = 'true';
			} else {
				$merge_vars['DONOR_ANON'] = 'false';
				$merge_vars['DONOR_FNAM'] = $transaction_array['donor_first_name'];
				$merge_vars['DONOR_LNAM'] = $transaction_array['donor_last_name'];
				$merge_vars['DONOR_EMAL'] = $transaction_array['donor_email'];
			}
            pfund_send_mandrill_email( $author_data->user_email, $merge_vars, __( 'A donation has been received', 'pfund' ), 'mandrill_email_donate');
		} else {
			$pub_message = sprintf(__( 'Dear %s,', 'pfund' ), $author_data->display_name ).PHP_EOL;
			if ( isset( $transaction_array['anonymous'] ) &&
				$transaction_array['anonymous'] == true ) {
				$pub_message .= sprintf(__( 'An anonymous gift of %s%s has been received for your campaign, %s.', 'pfund' ),
						$options['currency_symbol'],
						$trans_amount,
						$post->post_title).PHP_EOL;
			} else {
				$pub_message .= sprintf(__( '%s %s donated %s%s to your campaign, %s.', 'pfund' ),
						$transaction_array['donor_first_name'],
						$transaction_array['donor_last_name'],
						$options['currency_symbol'],
						$trans_amount,
						$post->post_title).PHP_EOL;
				$pub_message .= sprintf(__( 'If you would like to thank %s, you can email %s at %s.', 'pfund' ),
						$transaction_array['donor_first_name'],
						$transaction_array['donor_first_name'],
						$transaction_array['donor_email']).PHP_EOL;
			}			
			$pub_message .= sprintf(__( 'You can view your campaign at: %s.', 'pfund' ), $campaignUrl ).PHP_EOL;
			wp_mail($author_data->user_email, __( 'A donation has been received', 'pfund' ) , $pub_message);
		}
	}
}

/**
 * Send an email when a campaign goal has been reached.
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal.
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function pfund_send_goal_reached_email( $transaction_array, $post, $goal ) {
    $options = get_option( 'pfund_options' );
    $author_data = pfund_get_contact_info( $post, $options );
	if ( ! empty( $author_data->user_email ) && 
            apply_filters ('pfund_mail_on_goal_reached', true, $transaction_array, $author_data  ) ) {		
		$campaignUrl = get_permalink( $post );
		if ( $options['mandrill'] ) {
			$merge_vars = array(
				'NAME' => $author_data->display_name,
				'CAMP_TITLE' => $post->post_title,
				'CAMP_URL' => $campaignUrl,
				'GOAL_AMT' => $options['currency_symbol'].number_format_i18n( floatval( $goal ) )
			);
            pfund_send_mandrill_email( $author_data->user_email, $merge_vars, __( 'Campaign goal met!', 'pfund' ), 'mandrill_email_goal');
		} else {
			$pub_message = sprintf(__( 'Dear %s,', 'pfund' ), $author_data->display_name).PHP_EOL;
		
			$pub_message .= sprintf(__( 'Congratulations!  Your campaign goal of %s has been met!', 'pfund' ),
					number_format_i18n( floatval( $goal ) ),
					$post->post_title ).PHP_EOL;
			$pub_message .= sprintf(__( 'You can view your campaign at: %s.', 'pfund' ), $campaignUrl ).PHP_EOL;
			wp_mail( $author_data->user_email, __( 'Campaign goal met!', 'pfund' ) , $pub_message );
		}
	}
}



/**
 * Setup the short codes that personal fundraiser uses.
 */
function pfund_setup_shortcodes() {
	add_shortcode( 'pfund-authorize-net-donate-form', 'pfund_authorize_net_donate_form' );
	add_shortcode( 'pfund-campaign-list', 'pfund_campaign_list' );
	add_shortcode( 'pfund-campaign-permalink', 'pfund_campaign_permalink');
	add_shortcode( 'pfund-cause-list', 'pfund_cause_list' );
	add_shortcode( 'pfund-team-list', 'pfund_team_list' );
	add_shortcode( 'pfund-comments', 'pfund_comments' );
	add_shortcode( 'pfund-days-left', 'pfund_days_left' );
	
	add_shortcode( 'pfund-donate', 'pfund_donate_button' );
	add_shortcode( 'pfund-edit', 'pfund_edit' );
	add_shortcode( 'pfund-teamedit', 'pfund_teamedit' );
	add_shortcode( 'pfund-giver-list', 'pfund_giver_list' );
	add_shortcode( 'pfund-progress-bar', 'pfund_progress_bar' );
    add_shortcode( 'pfund-total-campaigns', 'pfund_get_total_published_campaigns' );
	add_shortcode( 'pfund-user-avatar', 'pfund_user_avatar' );        
	$options = get_option( 'pfund_options' );
	if ( isset( $options['fields'] ) ) {
		foreach ( $options['fields'] as $field_id => $field ) {
			add_shortcode( 'pfund-'.$field_id, '_pfund_dynamic_shortcode' );
		}
	}
}

/**
 * Get the avatar for the user associated to this campaign.
 * @param array $attrs the attributes for the shortcode.  You can specify the
 * size of the avatar by passing a "size" attribute.
 * @return the avatar for the user associated to this campaign.
 */
function pfund_user_avatar( $attrs ){
	global $post;
	if( $post->ID == null || $post->post_type != 'pfund_campaign' ) {
		$user_email = '';
	} else {
		$options = get_option( 'pfund_options' );
		$contact_info = pfund_get_contact_info( $post, $options );
		$user_email = $contact_info->user_email;
	}
	if ( ! empty ( $attrs['size'] ) ) {
		$size = $attrs['size'];
	} else {
		$size = '';
	}
	return get_avatar( $user_email, $size );
}

/**
 * Generate an input field for the Authorize.Net donation form.
 * @param type $name the name/id of the field.
 * @param type $label the label to display for the field.
 * @return string the generated HTML.
 */
function _pfund_auth_net_input( $name, $label ) {
	$retval = '<div id="pfund-input-' . $name . '">';
    $retval .= '<label for="' . $name . '">' . $label . ':</label>';
    $retval .= '<input type="text" name="' . $name . '" id="'. $name .'" class="validate[required]">';
	$retval .= '</div>';
    return $retval;
}

/**
 * Generate the markup for the month options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _pfund_auth_net_month_options() {
	$retval = '<div>';
    $retval .= '<label for="cc_exp_month">';
    $retval .=  __( 'Expiration Month:', 'pfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_exp_month" name="cc_exp_month">';
	for ( $i=1; $i<=12; $i++ ) {
		$padded = ( $i < 10 ? '0' : '' ) . $i;
		$retval .= '<option value="' . $padded . '">' . $padded . ' - ' . date("M", mktime(0, 0, 0, $i, 1, 2012)) . '</option>';
    }
    $retval .= '</select></div>';
    return $retval;
}

/**
 * Generate the markup the Authorize.Net security message.
 * @return string the generated HTML.
 */
function _pfund_auth_net_secure_donation_text() {
    $donation_text = '<div class="pfund-auth-net-secure-donations-text">';        
    $donation_text .= '<p>';
    $donation_text .= sprintf( __( 'You can donate to %s with confidence.  ', 'pfund' ), get_bloginfo('name') );
    $f_text .= __( 'We have partnered with <a target="_blank" href="http://www.authorize.net">Authorize.Net</a>, a leading payment gateway since 1996, to accept credit cards and electronic check payments safely and securely for our donors.', 'pfund' );
    $donation_text .= '</p>';
    $donation_text .= '<p>';
    $donation_text .= __( 'The Authorize.Net Payment Gateway manages the complex routing of sensitive customer information through the electronic check and credit card processing networks.  ', 'pfund' );
    $donation_text .= __( 'See an <a target="_blank" href="http://www.authorize.net/resources/howitworksdiagram/">online payments diagram</a> to see how it works.  ', 'pfund' );
    $donation_text .= '</p>';
    $donation_text .= '<p>'. __(  'The company adheres to strict industry standards for payment processing, including:', 'pfund' ) . '</p>';
    $donation_text .= '<ul>';
    $donation_text .= '<li>' . __( '128-bit Secure Sockets Layer (SSL) technology for secure Internet Protocol (IP) transactions.', 'pfund' ) . '</li>';
    $donation_text .= '<li>' . __( 'Industry leading encryption hardware and software methods and security protocols to protect customer information.', 'pfund' ) . '</li>';
    $donation_text .= '<li>' . __( 'Compliance with the Payment Card Industry Data Security Standard (PCI DSS).', 'pfund' ) . '</li>';
    $donation_text .= '</ul>';
    $donation_text .= '<p>' . __( 'For additional information regarding the privacy of your sensitive cardholder data, please read the <a target="_blank" href="http://www.authorize.net/company/privacy/">Authorize.Net Privacy Policy</a>.', 'pfund' ).'</p>';
    $donation_text .= '</div>';
    return $donation_text;
}

/**
 * Generate the markup for the month options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _pfund_auth_net_state_options() {
	$states = array(
	 'AL' => 'Alabama',
	 'AK' => 'Alaska',
	 'AZ' => 'Arizona',
	 'AR' => 'Arkansas',
	 'CA' => 'California',
	 'CO' => 'Colorado',
	 'CT' => 'Connecticut',
	 'DE' => 'Delaware',
	 'DC' => 'Dist. of Columbia',
	 'FL' => 'Florida',
	 'GA' => 'Georgia',
	 'HI' => 'Hawaii',
	 'ID' => 'Idaho',
	 'IL' => 'Illinois',
	 'IN' => 'Indiana',
	 'IA' => 'Iowa',
	 'KS' => 'Kansas',
	 'KY' => 'Kentucky',
	 'LA' => 'Louisiana',
	 'ME' => 'Maine',
	 'MD' => 'Maryland',
	 'MA' => 'Massachusetts',
	 'MI' => 'Michigan',
	 'MN' => 'Minnesota',
	 'MS' => 'Mississippi',
	 'MO' => 'Missouri',
	 'MT' => 'Montana',
	 'NE' => 'Nebraska',
	 'NV' => 'Nevada',
	 'NH' => 'New Hampshire',
	 'NJ' => 'New Jersey',
	 'NM' => 'New Mexico',
	 'NY' => 'New York',
	 'NC' => 'North Carolina',
	 'ND' => 'North Dakota',
	 'OH' => 'Ohio',
	 'OK' => 'Oklahoma',
	 'OR' => 'Oregon',
	 'PA' => 'Pennsylvania',
	 'RI' => 'Rhode Island',
	 'SC' => 'South Carolina',
	 'SD' => 'South Dakota',
	 'TN' => 'Tennessee',
	 'TX' => 'Texas',
	 'UT' => 'Utah',
	 'VT' => 'Vermont',
	 'VA' => 'Virginia',
	 'WA' => 'Washington',
	 'WV' => 'West Virginia',
	 'WI' => 'Wisconsin',
	 'WY' => 'Wyoming',
	);
		
	$retval = '<div>';
    $retval .= '<label for="cc_state">';
    $retval .= __( 'State:', 'pfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_state" name="cc_state">';
	foreach ( $states as $abbr => $state ) {
		$retval .= '<option value="' . $abbr . '">' . $abbr . ' - ' . $state . '</option>';
	}
	$retval .= '</select></div>';	
    return $retval;
}

/**
 * Generate the markup for the year options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _pfund_auth_net_year_options() {
	$retval = '<div>';
    $retval .= '<label for="cc_exp_year">';
    $retval .= __( 'Expiration Year:', 'pfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_exp_year" name="cc_exp_year">';
	$year = date("Y");
	for ( $i=$year; $i<=$year + 8; $i++ ) {
		$retval .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $retval .= '</select></div>';
    return $retval;
}

/**
 * Display the list of current campaigns
 * @return array with 1 page/post that contains the campaign list.
 */
function _pfund_campaign_list_page() {
	$options = get_option( 'pfund_options' );
	$page = get_page( $options['campaign_root']);
	$page->post_title = __( 'Campaign List', 'pfund' );
	$page->post_content = pfund_campaign_list();
	return array( $page );

}

/**/
function _pfund_campaign_created1( $campaign ) {
    $update_title = esc_attr__( 'Team Campaign added', 'pfund');    
    //$camp_url = get_permalink( $campaign->ID );
	$camp_url = get_permalink( $campaign->ID );
    //print_r($camp_url);die();
	$update_message = __( 'Your Team campaign has been created and is now available for public viewing here at <a href="%s">%s</a>.', 'pfund' );
    $update_content = sprintf( $update_message, $camp_url, $camp_url );
    _pfund_set_update_message1( $update_title, $update_content);    
}
/**/

function _pfund_campaign_created( $campaign ) {
    $update_title = esc_attr__( 'Campaign added', 'pfund');    
    $camp_url = get_permalink( $campaign->ID );
    $update_message = __( 'Your campaign has been created and is now available for public viewing here at <a href="%s">%s</a>.', 'pfund' );
    $update_content = sprintf( $update_message, $camp_url, $camp_url );
    _pfund_set_update_message( $update_title, $update_content);    
}

/**
 * Display the list of current causes
 * @return array with 1 page/post that contains the campaign list.
 */
function _pfund_cause_list_page() {
	
	$options = get_option( 'pfund_options' );
	$page = get_page( $options['cause_root']);
	$page->post_title = __( 'Cause List', 'pfund' );
	$page->post_content = pfund_cause_list();
	return array( $page );
}

function _pfund_team_list_page() {
	
	$options = get_option( 'pfund_options' );
	$page = get_page( $options['cause_root']);
	$page->post_title = __( 'Team List', 'pfund' );
	$page->post_content = pfund_team_list();
	return array( $page );
}

/**
 * Create an HTML input field in the specified document.
 * @param DOMDocument $doc The HTML document to add the field to.
 * @param DOMNode $parent The parent to add the field to.
 * @param string $name The name of the input field.
 * @param string $value The value for the input field.
 * @param string $type The type of input field.  Defaults to hidden.
 * @return DOMNode The node representing the input field.
 */
function _pfund_create_input_node( $doc, $parent, $name, $value, $type = 'hidden' ) {
	$input_node = $doc->createElement('input');
	$input_node->setAttribute( 'type', $type );
	$input_node->setAttribute( 'name' , $name );
	$input_node->setAttribute( 'value' , $value );
	return $parent->appendChild( $input_node );
}

/**
 * Determines if current user can create campaigns or edit the current campaign.
 * @param array $pfund_options The current options for personal fundraiser.
 * If the value isn't passed in, the option is retrieved.
 * @return boolean true if user can create; otherwise false.
 */
function _pfund_current_user_can_create( $pfund_options = array() ) {
	global $pfund_current_user_can_create;

	if ( isset ($pfund_current_user_can_create) ) {
		return $pfund_current_user_can_create;
	} else {
		$pfund_current_user_can_create = false;
		if ( empty( $pfund_options ) ) {
			$pfund_options = get_option( 'pfund_options' );
		}
		if ( !is_user_logged_in() ) {
			$pfund_current_user_can_create = ! $pfund_options['login_required'];
		} else  {
			$pfund_current_user_can_create = _pfund_current_user_can_submit( $pfund_options );
		}
		return $pfund_current_user_can_create;
	}
}

/**
 * Determines if current user can submit campaigns
 * @param array $pfund_options The current options for personal fundraiser.
 * If the value isn't passed in, the option is retrieved.
 * @return boolean true if user can submit; otherwise false.
 */
function _pfund_current_user_can_submit( $pfund_options = array() ) {
	if ( empty( $pfund_options ) ) {

		$pfund_options = get_option( 'pfund_options' );
	}
	if ( ! empty ($pfund_options['submit_role']) ) {
		foreach ( $pfund_options['submit_role'] as $role ) {
			if ( current_user_can ( $role ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Determine what the status of the campaign should be set to.
 * @param string $current_status The current status of the campaign.
 * @return string the status to use.
 */
function _pfund_determine_campaign_status( $current_status = '') {
	$options = get_option( 'pfund_options' );
	if ($current_status == 'publish') {
		return $current_status ;
	}
	if ( _pfund_current_user_can_submit() ) {
		if ( $options['approval_required'] ) {
			return 'pending';
		} else {
			return 'publish';
		}
	} else {
		//return 'draft';
		return 'publish';		
	}
}

/**
 * Displays a thank you message after a donation has been received.
 */
function _pfund_display_thanks() {
	global $pfund_update_message;
	$title = esc_attr__( 'Thanks for donating', 'pfund');
	$pfund_update_message = '<div id="pfund-update-dialog" style="display:none;" title="'.$title.'">';
	$pfund_update_message .= '<div>'.__( 'Thank you for your donation!', 'pfund' ).'</div></div>';
}

/**
 * Handler for the dynamic shortcodes created by personal fundraiser fields.
 * @param array $attrs the attributes for the shortcode
 * @param string $content the content between the shortcode begin and end tags.
 * @param string $tag the name of the shortcode.
 * @return string The data associated to the specified personal fundraiser field.
 */
function _pfund_dynamic_shortcode( $attrs, $content, $tag ) {
	
	
	$post = _pfund_get_shortcode_campaign( $attrs );
	
	if($post->post_type=='teamcampaigns'){
		$postid = $post->ID;	
	if ( _pfund_is_edit_new_campaign() ) {
		$postid = _pfund_get_new_campaign()->ID;
	}

	$options = get_option( 'pfund_options' );
	
	$field_id = substr( $tag, 6 );
	$field = $options['fields'][$field_id];

	$data = get_post_meta( $postid, '_pfund_'.$field_id, true );
	
	$return_data = '';
	switch ( $field['type'] ) {
		case 'end_date':
		case 'date':
			if( ! empty( $data ) ) {
				$return_data = pfund_format_date( $data , $options['date_format'] );
			}
			break;
		case 'text':
		$return_data = wpautop( make_clickable( $data ) );
			break;
		case 'textarea':
			$return_data = wpautop( make_clickable( $data ) );
			break;
		case 'image':
			if( empty( $data ) && isset( $attrs['default'] ) ) {
				$img_src = $attrs['default'];
			} else if ( ! empty( $data ) ) {
				$img_src = wp_get_attachment_url( $data );
			}
			if ( ! empty( $img_src ) ) {
				$return_data = '<img class="pfund-img" src="' .$img_src. '" />';
			}
			break;
		case 'user_goal':
		case 'gift_tally':
		case 'giver_tally':
			if ( empty ( $data ) ) {
				$data = '0';
			}
			$return_data = number_format_i18n( floatval( $data ) );
			break;
		default:
			$return_data = apply_filters( 'pfund_'.$field['type'].'_shortcode', $data );
	}
	if ( ! empty ( $attrs['esc_js'] ) && $attrs['esc_js'] == 'true' ) {
		$return_data = esc_js($return_data);
	}
	return $return_data;}
	if ( ! pfund_is_pfund_post( $post ) ){
		return '';
	}

	$postid = $post->ID;	
	if ( _pfund_is_edit_new_campaign() ) {
		$postid = _pfund_get_new_campaign()->ID;
	}

	$options = get_option( 'pfund_options' );
	$field_id = substr( $tag, 6 );
	$field = $options['fields'][$field_id];

	$data = get_post_meta( $postid, '_pfund_'.$field_id, true );
	$return_data = '';
	switch ( $field['type'] ) {
		case 'end_date':
		case 'date':
			if( ! empty( $data ) ) {
				$return_data = pfund_format_date( $data , $options['date_format'] );
			}
			break;
		case 'text':
		$return_data =   $data  ;
		break;
		case 'textarea':
			$return_data = wpautop( make_clickable( $data ) );
			break;
		case 'image':
			if( empty( $data ) && isset( $attrs['default'] ) ) {
				$img_src = $attrs['default'];
			} else if ( ! empty( $data ) ) {
				$img_src = wp_get_attachment_url( $data );
			}
			if ( ! empty( $img_src ) ) {
				$return_data = '<img class="pfund-img" src="' .$img_src. '" />';
			}
			break;
		case 'user_goal':
		case 'gift_tally':
		case 'giver_tally':
			if ( empty ( $data ) ) {
				$data = '0';
			}
			$return_data = number_format_i18n( floatval( $data ) );
			break;
		default:
			$return_data = apply_filters( 'pfund_'.$field['type'].'_shortcode', $data );
	}
	if ( ! empty ( $attrs['esc_js'] ) && $attrs['esc_js'] == 'true' ) {
		$return_data = esc_js($return_data);
	}
	return $return_data;
}

/**
 * Convert php date format to jquery date format.
 * Derived from http://icodesnip.com/snippet/php/convert-php-date-style-dateformat-to-the-equivalent-jquery-ui-datepicker-string
 * @param string $date_format php date format to convert.
 * @return string corresponding jquery date format.
 */
function _pfund_get_jquery_date_fmt( $date_format ) {
    $php_patterns = array(
        //day
        '/d/',        //day of the month
        '/j/',        //day of the month with no leading zeros
        //month
        '/m/',        //numeric month leading zeros
        '/n/',        //numeric month no leading zeros
        //year
        '/Y/',        //full numeric year
        '/y/'     //numeric year: 2 digit
    );
    $jquery_formats = array(
        'dd','d',
        'mm','m',
        'yy','y'
    );
    return preg_replace($php_patterns, $jquery_formats, $date_format);
}

/**
 * When a new campaign has been created, get that campaign.
 * @return mixed the new campaign or null if the current campaign isn't a
 * new campaign.
 */
function _pfund_get_new_campaign() {
	global $pfund_new_campaign;
	
	$new_campaign_actions = array( 'update-campaign', 'user-login' );
	$action = pfund_get_value( $_REQUEST, 'pfund_action' );
	if ( ! isset( $pfund_new_campaign ) &&
			isset( $_REQUEST['pfund_campaign_id'] )
			&& in_array( $action, $new_campaign_actions ) ) {
		$campaign_id = $_REQUEST['pfund_campaign_id'];
		$campaign = get_post( $campaign_id );
		$referer_action = 'pfund-'.$action.$campaign_id;
		if( $action == 'user-login' ) {
			global $current_user;
			get_currentuserinfo();
			$save_user = $current_user;
			$current_user = new WP_User( 0 );
		}
		if ( wp_verify_nonce( $_REQUEST['n'], $referer_action ) ) {
			$pfund_new_campaign = $campaign;
		}
		if( $action == 'user-login' ) {
			$current_user = $save_user;
		}
	}
	return $pfund_new_campaign;	
}

/*
 * Get the campaign to use for a shortcode based on the attributes passed to 
 * the shortcode.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the progress bar for by passing a "campaign_id" attribute.
 * @return the campaign to use for the shortcode.
 */
function _pfund_get_shortcode_campaign($attrs) {
    global $post;
    if ( ! empty ( $attrs['campaign_id'] ) ) {
        $campaign = get_post( $attrs['campaign_id'] );
        wp_enqueue_style( 'pfund-user', pfund_determine_file_location('user','css'), 
                array(), PFUND_VERSION );
    } else {
        $campaign = $post;
    }
    return $campaign;
}

/**
 * Generate HTML text input fields.
 * @param string $id DOM id for field.
 * @param string $label Label to display with text input field.
 * @param string $name Input field name.
 * @param string $class Class to apply to input field.
 * @param string $additional_content Additional content to display.
 * @return string The generated HTML.
 */
function _pfund_generate_input_field( $id, $label, $name, $class, $additional_content = '' ) {
	$input_field = '<li>';
	$input_field .= '	<label for="'.$id.'">'.$label;
	$input_field .= '		<abbr title="'.esc_attr__( 'required', 'pfund' ).'">*</abbr>';
	$input_field .= '	</label><br/>';
	$input_field .= '	<input id="'.$id.'" type="text" name="'.$name.'" class="'.$class.'" value=""/>';	
	if ( ! empty( $additional_content ) ) {
		$input_field .= $additional_content;
	}
	$input_field .= '</li>';
	return $input_field;
}

/**
 * Determine if a new campaign is being edited
 * @return boolean true if a new campaign is being edited; false otherwise.
 */
function _pfund_is_edit_new_campaign() {	
	global $pfund_new_campaign, $pfund_is_edit_new_campaign;
	if ( ! isset( $pfund_is_edit_new_campaign ) ) {
		_pfund_get_new_campaign();
		if ( isset( $pfund_new_campaign ) ){
			$pfund_is_edit_new_campaign = true;
		} else {
			$pfund_is_edit_new_campaign = false;
		}
	}
	return $pfund_is_edit_new_campaign ;
}
/**
 * Process a donation to a campaign.
 */
function _pfund_process_donate( $post ){
	//Handle various payment platforms.
	$confirmation_type = $_REQUEST['t'];
	switch( $confirmation_type ) {
		case 'pp':
			$transaction_array = pfund_process_paypal_pdt();
			break;
		case 'ipn':
			$transaction_array = pfund_process_paypal_ipn();
			break;
		default:
			$transaction_array = array();
			if (isset($_REQUEST['a'])) {
				$transaction_array['amount'] = $_REQUEST['a'];
				$transaction_array['success'] = true;
			}
	}

	if (isset($_REQUEST['n'])) {
		$transaction_array['transaction_nonce'] = $_REQUEST['n'];
	}

	//Allow integration of other transactions processing systems
	$transaction_array = apply_filters( 'pfund_transaction_array', $transaction_array );
	if ( ! empty( $transaction_array ) ) {
		do_action( 'pfund_add_gift', $transaction_array, $post );
	}
	
	//For IPN response exit since this is a server-side call.
	if ( $confirmation_type == 'ipn' ) {
		exit();
	}
	_pfund_display_thanks();
}

/**
 * Save the campaign
 * @param mixed $post the current cause or campaign to use to save the campaign.
 * @param string $update_type either 'add' or 'update.
 */
 
/**/
function _pfund_save_teamcamp( $post, $update_type = 'add' ) {
	global $pfund_new_campaign, 			
			$pfund_is_edit_new_campaign;
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php');

	$options = get_option( 'pfund_options' );
	/*if ( ! _pfund_current_user_can_create( $options ) ) {
		return;
	}*/

	if ( $update_type == 'user-login' ) {
		$campaign_fields = array();
	} else {
		$campaign_fields = array(
			'post_name' => strip_tags( $_REQUEST['pfund-camp-location'] ),
			'post_title' => strip_tags( $_REQUEST['pfund-camp-title'] ),
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
	}

	if ( $update_type == 'update' || $update_type == 'user-login' ) {
		if ( $post->post_type == 'teamcampaigns' ) {
			if ( _pfund_is_edit_new_campaign() ) {
				$campaign = _pfund_get_new_campaign();
				$campaign_id = $campaign->ID;
				$current_status = $campaign->post_status;
			} else {
				return;
			}
		} else {
			$campaign_id = $_REQUEST['pfund_campaign_id'];
			$campaign = get_post( $campaign_id );
			$current_status = $campaign->post_status;
		}
	} else {
		$current_status = '';
	}
	$status = _pfund_determine_campaign_status( $current_status );
	if ( $status != 'publish' ) {
		$campaign_fields['post_status'] = $status;
	}

	if ( $update_type == 'add') {
		$campaign_fields['post_type'] = 'teamcampaigns';
		$slug_id = $_POST['cause-slug'];
		$campaign_id = wp_insert_post( $campaign_fields );
		//exit;
		update_post_meta($campaign_id, '_pfund_cause_id', $slug_id );
		$pfund_is_edit_new_campaign = true;
		$update_title = esc_attr__( 'Team Campaign added', 'pfund');
        if ( $status == 'pending' && is_user_logged_in() ) {
            $update_message = __( 'Your Team campaign has been created and submitted for approval.  Once your campaign has been approved you will receive an email notifying you.', 'pfund' );
        } else {
            $update_message = __( 'Your Team campaign has been created. %s','pfund' );
        }
} else {
		$campaign_fields['ID'] = $campaign_id;
		if ( $status != 'publish' ) {
			$campaign_fields['post_status'] = $status;
		}
		wp_update_post( $campaign_fields );
		$update_title = esc_attr__( 'Team Campaign updated', 'pfund' );
		if ( $status == 'publish' ) {
			$update_message = __( 'Your Team campaign has been updated and is available for public viewing at <a href="%s">%s</a>.', 'pfund' );            
		} else if ( $status == 'pending' && is_user_logged_in() ) {
			$update_message = __( 'Your Team campaign has been updated and submitted for approval.  Once your Team campaign has been approved you will receive an email notifying you.', 'pfund' );
		} else {			
			$update_message = __( 'Your Team campaign has been updated.  %s', 'pfund' );			
		}
}
	if ( $update_type != 'user-login' ) {
		pfund_save_campaign_fields( $campaign_id );
	}
	
	if ($pfund_is_edit_new_campaign) {
		$pfund_new_campaign = get_post( $campaign_id );
	}
	
	$additional_content = '';
	if ( $status == 'publish' || ( $status == 'pending' && is_user_logged_in() ) ) {
        if ( $status == 'publish' ) {
			wp_publish_post( $campaign_id );		
            $camp_url = get_permalink( $campaign_id );
			if ( $update_type == 'add' || $update_type == 'user-login' ) {
                $added_parms = array(
                    'pfund_action' => 'teamcampaigns-created',
                    'n' => 	wp_create_nonce( 'pfund-campaign-created'.$campaign_id ),
                );
                $camp_url .= '?'. http_build_query( $added_parms );
				wp_safe_redirect( $camp_url );
                return;
            }
        } else if ( $status == 'pending' && is_user_logged_in() ) {			
            $camp_url = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['campaign_slug'] ).$campaign_id;           
        }
        $update_content = sprintf( $update_message, $camp_url, $camp_url );
	} else {
		$previewparms = array(
			'pfund_action' => 'user-login',
			'n' => 	wp_create_nonce( 'pfund-user-login'.$campaign_id ),
			'pfund_campaign_id' =>$campaign_id
		);
		$preview_url = get_permalink( $post->ID ) . '?'. http_build_query( $previewparms );
		$login_link = wp_login_url( $preview_url );
		if ( $options['allow_registration'] ) {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="pfund-login-link" href="%s">Login</a> or <a id="pfund-register-link" href="#">Register</a>.', 'pfund' ) );
			$additional_content = '<div id="pfund-register-dialog" style="display:none;" title="'.esc_attr__( 'Register','pfund' ).'">';
			$additional_content .= '<form name="pfund_create_account_form" id="pfund-create-account-form" action="'.PFUND_URL.'register-user.php" method="post">';
			$additional_content .= '<ul class="pfund-list">';
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-username',
					__( 'Username', 'pfund' ),
					'pfund_user_login',
					'validate[required,length[0,60]]' );
			$mask_password = '<div class="pfund-field-desc"><small><a id="pfund-mask-pass" href="#">'.__( 'Mask password', 'pfund' ).'</a></small></div>';
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-pass', __( 'Password', 'pfund' ),
					'pfund_user_pass',
					'validate[required,length[0,20]]',
					$mask_password );
			$additional_content .= _pfund_generate_input_field(
					'pfund-register-email',
					__( 'Email', 'pfund' ),
					'pfund_user_email',
					'validate[required,custom[email],length[0,100]]' );
			$additional_content .= _pfund_generate_input_field(
					'pfund-register-fname',
					__( 'First Name', 'pfund' ),
					'pfund_user_first_name',
					'validate[required,length[0,100]]' );
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-lname',
					__( 'Last Name', 'pfund' ),
					'pfund_user_last_name',
					'validate[required,length[0,100]]' );
			$additional_content .= '</form>';
			$additional_content .= '<form name="pfund_login_form" id="pfund-login-form" action="'.wp_login_url().'" method="post">';
			$additional_content .= '<input type="hidden" name="log" id="pfund-user-login">';
			$additional_content .= '<input type="hidden" name="pwd" id="pfund-user-pass">';
			$additional_content .= '<input type="hidden" name="redirect_to" value="'.$preview_url.'">';
			$additional_content .= '</ul">';
			$additional_content .= '</form>';
			$additional_content .= '</div>';
		} else {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="pfund-login-link" href="%s">Login</a>.', 'pfund' ) );
		}
		$update_content = sprintf( $update_message, $login_link );
	}
	_pfund_set_update_message( $update_title, $update_content. $additional_content );
	
}
/**/ 
 
function _pfund_save_camp( $post, $update_type = 'add' ) {
	global $pfund_new_campaign, 			
			$pfund_is_edit_new_campaign;
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php');

	$options = get_option( 'pfund_options' );

	if ( ! _pfund_current_user_can_create( $options ) ) {
		return;
	}

	if ( $update_type == 'user-login' ) {
		$campaign_fields = array();
	} else {
		$campaign_fields = array(
			'post_name' => strip_tags( $_REQUEST['pfund-first-name'].'-'.$_REQUEST['pfund-last-name'] ),
			'post_title' => strip_tags( $_REQUEST['pfund-first-name'].' '.$_REQUEST['pfund-last-name'] ),
			'post_content' => '<h1>RAISE FUNDS FOR NIAGARA HOSPICE CARE</h1>
<ul class="pfu">
  <li class="pfund-stat"><span class="highlight">$[pfund-gift-goal]</span>funding goal</li>
  <li class="pfund-stat"><span class="highlight">$[pfund-gift-tally]</span>raised</li>
  <li class="pfund-stat"><span class="highlight">[pfund-giver-tally]</span>givers</li>
  <li class="pfund-stat"><span class="highlight">[pfund-days-left]</span>days left</li>
  <li class="pfund-stat" style="background:none">[pfund-progress-bar]</li>
</ul>
<div style="clear: both;" class="det">
<br/>
<h2>Fundraising page for [pfund-first-name] [pfund-last-name]</h2>	
<p>I am raising funds to support Niagara Hospice patients and their families.</p>
<p>Please support my effort by clicking on the donate button below.</p>
<p style="margin:0; font-weight:bold;">My Personal Message : </p>
<p>[pfund-message]</p>
<p class="pht">[pfund-photo]</p>
<p>[pfund-donate]<p>
</div>[pfund-edit]'
		);
	}
	if ( $update_type == 'update' || $update_type == 'user-login' ) {
		if ( $post->post_type == 'pfund_cause' ) {
			if ( _pfund_is_edit_new_campaign() ) {
				$campaign = _pfund_get_new_campaign();
				$campaign_id = $campaign->ID;
				$current_status = $campaign->post_status;
			} else {
				return;
			}
		} else {
			$campaign_id = $_REQUEST['pfund_campaign_id'];
			$campaign = get_post( $campaign_id );
			$current_status = $campaign->post_status;
		}
	} else {
		$current_status = '';
	}
	$status = _pfund_determine_campaign_status( $current_status );
	if ( $status != 'publish' ) {
		$campaign_fields['post_status'] = $status;
	}

	if ( $update_type == 'add' ) {
		$campaign_fields['post_type'] = 'pfund_campaign';
		$campaign_id = wp_insert_post( $campaign_fields );
		update_post_meta( $campaign_id, '_pfund_cause_id', $post->ID );
		update_post_meta( $campaign_id, 'team_campaigns', $_POST['team_campaigns'] );
		$pfund_is_edit_new_campaign = true;
		$update_title = esc_attr__( 'Campaign added', 'pfund');
        if ( $status == 'pending' && is_user_logged_in() ) {
            $update_message = __( 'Your campaign has been created and submitted for approval.  Once your campaign has been approved you will receive an email notifying you.', 'pfund' );
        } else {
            $update_message = __( 'Your campaign has been created. %s','pfund' );
        }
	} else {
		$campaign_fields['ID'] = $campaign_id;
		if ( $status != 'publish' ) {
			$campaign_fields['post_status'] = $status;
		}
		wp_update_post( $campaign_fields );
		$update_title = esc_attr__( 'Campaign updated', 'pfund' );
		if ( $status == 'publish' ) {
			$update_message = __( 'Your campaign has been updated and is available for public viewing at <a href="%s">%s</a>.', 'pfund' );            
		} else if ( $status == 'pending' && is_user_logged_in() ) {
			$update_message = __( 'Your campaign has been updated and submitted for approval.  Once your campaign has been approved you will receive an email notifying you.', 'pfund' );
		} else {			
			$update_message = __( 'Your campaign has been updated.  %s', 'pfund' );			
		}
	}
	if ( $update_type != 'user-login' ) {
		pfund_save_campaign_fields( $campaign_id );
	}
	
	if ($pfund_is_edit_new_campaign) {
		$pfund_new_campaign = get_post( $campaign_id );
	}
	$additional_content = '';
	if ( $status == 'publish' || ( $status == 'pending' && is_user_logged_in() ) ) {
        if ( $status == 'publish' ) {
            wp_publish_post( $campaign_id );		
            $camp_url = get_permalink( $campaign_id );
            if ( $update_type == 'add' || $update_type == 'user-login' ) {
                $added_parms = array(
                    'pfund_action' => 'campaign-created',
                    'n' => 	wp_create_nonce( 'pfund-campaign-created'.$campaign_id ),
                );
                $camp_url .= '?'. http_build_query( $added_parms );
                wp_safe_redirect( $camp_url );
                return;
            }
        } else if ( $status == 'pending' && is_user_logged_in() ) {
            $camp_url = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['campaign_slug'] ).$campaign_id;           
        }
        $update_content = sprintf( $update_message, $camp_url, $camp_url );
	} else {
		$previewparms = array(
			'pfund_action' => 'user-login',
			'n' => 	wp_create_nonce( 'pfund-user-login'.$campaign_id ),
			'pfund_campaign_id' =>$campaign_id
		);
		$preview_url = get_permalink( $post->ID ) . '?'. http_build_query( $previewparms );
		$login_link = wp_login_url( $preview_url );
		if ( $options['allow_registration'] ) {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="pfund-login-link" href="%s">Login</a> or <a id="pfund-register-link" href="#">Register</a>.', 'pfund' ) );
			$additional_content = '<div id="pfund-register-dialog" style="display:none;" title="'.esc_attr__( 'Register','pfund' ).'">';
			$additional_content .= '<form name="pfund_create_account_form" id="pfund-create-account-form" action="'.PFUND_URL.'register-user.php" method="post">';
			$additional_content .= '<ul class="pfund-list">';
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-username',
					__( 'Username', 'pfund' ),
					'pfund_user_login',
					'validate[required,length[0,60]]' );
			$mask_password = '<div class="pfund-field-desc"><small><a id="pfund-mask-pass" href="#">'.__( 'Mask password', 'pfund' ).'</a></small></div>';
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-pass', __( 'Password', 'pfund' ),
					'pfund_user_pass',
					'validate[required,length[0,20]]',
					$mask_password );
			$additional_content .= _pfund_generate_input_field(
					'pfund-register-email',
					__( 'Email', 'pfund' ),
					'pfund_user_email',
					'validate[required,custom[email],length[0,100]]' );
			$additional_content .= _pfund_generate_input_field(
					'pfund-register-fname',
					__( 'First Name', 'pfund' ),
					'pfund_user_first_name',
					'validate[required,length[0,100]]' );
			$additional_content .= _pfund_generate_input_field( 
					'pfund-register-lname',
					__( 'Last Name', 'pfund' ),
					'pfund_user_last_name',
					'validate[required,length[0,100]]' );
			$additional_content .= '</form>';
			$additional_content .= '<form name="pfund_login_form" id="pfund-login-form" action="'.wp_login_url().'" method="post">';
			$additional_content .= '<input type="hidden" name="log" id="pfund-user-login">';
			$additional_content .= '<input type="hidden" name="pwd" id="pfund-user-pass">';
			$additional_content .= '<input type="hidden" name="redirect_to" value="'.$preview_url.'">';
			$additional_content .= '</ul">';
			$additional_content .= '</form>';
			$additional_content .= '</div>';
		} else {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="pfund-login-link" href="%s">Login</a>.', 'pfund' ) );
		}
		$update_content = sprintf( $update_message, $login_link );
	}
	_pfund_set_update_message( $update_title, $update_content. $additional_content );
}

/**
 * Update the total number of unique givers for this campaign.  A giver is
 * considered unique by email address.  All anonymous gifts are considered
 * unique givers.
 * @param <type> $campaign_id
 */
function _pfund_update_giver_tally( $campaign_id ) {
	$givers = get_post_meta( $campaign_id, '_pfund_givers', true );
	if ( empty( $givers )) {
		$givers = array();
	}
	$total_giver_tally = 0;
	$new_giver = false;
	$transactions = get_post_meta( $campaign_id, '_pfund_transactions' );
	foreach ( $transactions as $transaction ) {
		if ( isset( $transaction['anonymous'] ) &&
				$transaction['anonymous'] == true ) {
			$total_giver_tally++;
			$new_giver = true;
		} else if ( ! empty( $transaction['donor_email']) &&
				! array_key_exists( $transaction['donor_email'], $givers ) ) {
			$total_giver_tally++;
			$new_giver = true;
			$givers[$transaction['donor_email']] = array(
				'first_name' => $transaction['donor_first_name'],
				'last_name' => $transaction['donor_last_name'],
			);
		}
	}
	$giver_tally = get_post_meta( $campaign_id, '_pfund_giver-tally', true );
	if ( empty( $giver_tally ) ) {
		$giver_tally = $total_giver_tally;
	} else if ( $new_giver ) {
		$giver_tally++;
	}
	update_post_meta( $campaign_id, '_pfund_giver-tally', $giver_tally );
	update_post_meta( $campaign_id, '_pfund_givers', $givers );
}

/**
 * Create the update message dialog box.
 * @param type $update_title The title to display in the dialog box.
 * @param type $update_content The content to display in the dialog box.
 * @param type $additional_content Additional content that needs to be displayed
 * alongside the update message.
 */
function _pfund_set_update_message( $update_title, $update_content, $additional_content = '' ) {
	
    global $pfund_update_message; 
    $pfund_update_message = '<div id="pfund-update-dialog" style="display:none;" title="'.$update_title.'">';
	$pfund_update_message .= '<div>'.$update_content.'</div></div>';
	$pfund_update_message .= $additional_content;
	
}

function _pfund_set_update_message1( $update_title, $update_content, $additional_content = '' ) {
	global $pfund_update_message; 
    $pfund_update_message = '<div id="pfund-update-dialog" style="display:none;" title="'.$update_title.'">';
	$pfund_update_message .= '<div>'.$update_content.'</div></div>';
	$pfund_update_message .= $additional_content;
	echo $pfund_update_message;
	
}
?>
