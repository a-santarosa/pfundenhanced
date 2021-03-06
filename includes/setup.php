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

/**
 * Activate the plugin
 */
function pfund_activate( $flush_rules = true ) {

	if ( version_compare( get_bloginfo( 'version' ), '3.6', '<' ) ) {
		deactivate_plugins( PFUND_BASENAME );
	} else {
		$options_changed = false;
		$pfund_options = get_option( 'pfund_options' );
		if ( ! $pfund_options ) {
			//Setup default options
			$pfund_options = array(
				'allow_registration' => false,
				'campaign_slug' => 'give',
				'cause_slug' => 'causes',
				'teamcampaigns_slug' => 'team',
				'currency_symbol' => '$',
				'date_format' => 'm/d/y',
				'login_required' => true,
				'mandrill' => false,
				'use_ssl' => false,
				'authorize_net_test_mode' => false,
				'submit_role' => array( 'administrator' ),
				'fields' => array(
					'camp-title' => array(
						'label' => __( 'Title', 'pfund' ),
						'desc' => __( 'The title of your campaign', 'pfund' ),
						'type' => 'camp_title',
						'required' => true
					),
					'camp-location' => array(
						'label' => __( 'URL', 'pfund' ),
						'desc' => __( 'The URL for your campaign', 'pfund' ),
						'type' => 'camp_location',
						'required' => true
					),
					'end-date'  => array(
						'label' => __( 'End Date', 'pfund' ),
						'desc' => __( 'The date your campaign ends', 'pfund' ),
						'type' => 'end_date',
						'required' => false
					),
					'gift-goal' => array(
						'label' => __( 'Goal', 'pfund' ),
						'desc' => __( 'The amount you hope to raise', 'pfund' ),
						'type' => 'user_goal',
						'required' => true
					),
					'gift-tally' => array(
						'label' => __( 'Total Raised', 'pfund' ),
						'desc' => __( 'Total donations received', 'pfund' ),
						'type' => 'gift_tally',
						'required' => true
					),
					'giver-tally' => array(
						'label' => __( 'Giver Tally', 'pfund' ),
						'desc' => __( 'The number of unique givers for the campaign.', 'pfund' ),
						'type' => 'giver_tally',
						'required' => true
					),
					'photo' => array(
						'label' => __( 'Personal Photo', 'pfund' ),
						'desc' => __( 'The Personal Photo of unique campaign.', 'pfund' ),
						'type' => 'image',
						'required' => true
					),
					'message' => array(
						'label' => __( 'Personal Message', 'pfund' ),
						'desc' => __( 'The Personal Message of campaign.', 'pfund' ),
						'type' => 'textarea',
						'required' => true
					)
				)
			);
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['team_root'] ) ) {
			$page = array(
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'post_status' => 'publish',
				'post_content' => '',
				'post_name' => '_team_listing',
				'post_title' => __( 'Team Listing', 'pfund' ),
				'post_content' => '',
				'post_type' => 'pfund_team_list'
			);
			$cause_root_id = wp_insert_post( $page );
			$pfund_options['team_root'] = $cause_root_id;
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['cause_root'] ) ) {
			$page = array(
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'post_status' => 'publish',
				'post_content' => '',
				'post_name' => '_causes_listing',
				'post_title' => __( 'Causes Listing', 'pfund' ),
				'post_content' => '',
				'post_type' => 'pfund_cause_list'
			);
			$cause_root_id = wp_insert_post( $page );
			$pfund_options['cause_root'] = $cause_root_id;
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['campaign_root'] ) ) {
			$page = array(
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'post_status' => 'publish',
				'post_content' => '',
				'post_name' => '_campaign_listing',
				'post_title' => __( 'Campaign Listing', 'pfund' ),
				'post_content' => '',
				'post_type' => 'pfund_campaign_list'
			);
			$cause_root_id = wp_insert_post( $page );
			$pfund_options['campaign_root'] = $cause_root_id;
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['date_format'] ) ) {
			$pfund_options['date_format'] = 'm/d/y';
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['mandrill'] ) ) {
			$pfund_options['mandrill'] = false;
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['paypal_sandbox'] ) ) {
			$pfund_options['paypal_sandbox'] = false;
			$options_changed = true;
		}
		
		if ( ! isset( $pfund_options['use_ssl'] ) ) {
			$pfund_options['use_ssl'] = false;
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['authorize_net_test_mode'] ) ) {
			$pfund_options['authorize_net_test_mode'] = false;
			$options_changed = true;
		}
		
		
		if ( ! isset( $pfund_options['fields']['end-date'] ) ) {
			$pfund_options['fields']['end-date'] = array(
				'label' => __( 'End Date', 'pfund' ),
				'desc' => __( 'The date your campaign ends', 'pfund' ),
				'type' => 'end_date',
				'required' => false
			);
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['fields']['giver-tally'] ) ) {
			$pfund_options['fields']['giver-tally'] = array(
				'label' => __( 'Giver Tally', 'pfund' ),
				'desc' => __( 'The number of unique givers for the campaign.', 'pfund' ),
				'type' => 'giver_tally',
				'required' => true
			);
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['fields']['photo'] ) ) {
			$pfund_options['fields']['photo'] = array(
				'label' => __( 'Persoanl Photo', 'pfund' ),
				'desc' => __( 'The Persoanl Photo campaign.', 'pfund' ),
				'type' => 'image',
				'required' => true
			);
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['fields']['message'] ) ) {
			$pfund_options['fields']['message'] = array(
				'label' => __( 'Persoanl Message', 'pfund' ),
				'desc' => __( 'The Persoanl Message campaign.', 'pfund' ),
				'type' => 'message',
				'required' => true
			);
			$options_changed = true;
		}
		if ( ! isset( $pfund_options['campaign_listing'] ) ) {
			$pfund_options['campaign_listing'] = true;
			$options_changed = true;
		}

		if ( ! isset( $pfund_options['cause_listing'] ) ) {
			$pfund_options['cause_listing'] = true;
			$options_changed = true;
		}

        if ( ! isset( $pfund_options['mandrill_email_publish_html'] ) ) {
            $pfund_options['mandrill_email_publish_html'] = '<h1>Your campaign has been approved</h1>'.PHP_EOL;
            $pfund_options['mandrill_email_publish_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_html'] .= 'Your campaign, *|CAMP_TITLE|* has been approved.<br/>'.PHP_EOL;
            $pfund_options['mandrill_email_publish_html'] .= 'You can view your campaign at: *|CAMP_URL|*.<br/>'.PHP_EOL;
        }
        
        if ( ! isset( $pfund_options['mandrill_email_publish_text'] ) ) {
            $pfund_options['mandrill_email_publish_text'] = 'Dear *|NAME|*'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_text'] .= 'Your campaign, *|CAMP_TITLE|* has been approved.'.PHP_EOL;
            $pfund_options['mandrill_email_publish_text'] .= 'You can view your campaign at: *|CAMP_URL|*.'.PHP_EOL;
        }  
		
		 if ( ! isset( $pfund_options['mandrill_email_publish_text1'] ) ) {
            $pfund_options['mandrill_email_publish_text1'] = "Dear |fname| |lname|,".PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_text1'] .= "Thank you for registering your team for the Hospice Dash.".PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_text1'] .= "You can view your team and its members here: |team_campaign_link|".PHP_EOL.PHP_EOL;
			$pfund_options['mandrill_email_publish_text1'] = "If you are interested in creating your own fundraising page to support Niagara Hospice's work, |cuase_link|".PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_text1'] .= "Happy training!".PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_publish_text1'] .= "The Hospice Dash Committee".PHP_EOL.PHP_EOL;
        }         
        
        if ( ! isset( $pfund_options['mandrill_email_donate_html'] ) ) {
            $pfund_options['mandrill_email_donate_html'] = '<h1>Your fundraiser received a donation!</h1>'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= '*|IF:DONOR_ANON=true|*'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= 'An anonymous gift of *|DONATE_AMT|* has been received for your fundraiser, *|CAMP_TITLE|*.<br/>'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= '*|ELSE:|*'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= '*|DONOR_FNAM|* *|DONOR_LNAM|* donated *|DONATE_AMT|* to your fundraiser, <a href="*|CAMP_URL|*">*|CAMP_TITLE|*</a>.<br/>'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= 'If you would like to thank *|DONOR_FNAM|*, you can email *|DONOR_FNAM|* at *|DONOR_EMAL|*.<br/>'.PHP_EOL;
            $pfund_options['mandrill_email_donate_html'] .= '*|END:IF|*'.PHP_EOL;
        }
        
        if ( ! isset( $pfund_options['mandrill_email_donate_text'] ) ) {
            $pfund_options['mandrill_email_donate_text'] = 'Dear *|NAME|*,'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='*|IF:DONOR_ANON=true|*'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='An anonymous gift of *|DONATE_AMT|* has been received for your fundraiser, *|CAMP_TITLE|*.'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='*|ELSE:|*'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='*|DONOR_FNAM|* *|DONOR_LNAM|* donated *|DONATE_AMT|* to your fundraiser, *|CAMP_TITLE|*.'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='If you would like to thank *|DONOR_FNAM|*, you can email *|DONOR_FNAM|* at *|DONOR_EMAL|*.'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='*|END:IF|*'.PHP_EOL;
            $pfund_options['mandrill_email_donate_text'] .='You can view your fundraiser at: *|CAMP_URL|*.'.PHP_EOL;
        }
        
        if ( ! isset( $pfund_options['mandrill_email_goal_html'] ) ) {
            $pfund_options['mandrill_email_goal_html'] = '<h1>Your fundraiser has reached its goal!</h1>'.PHP_EOL;
            $pfund_options['mandrill_email_goal_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_goal_html'] .= 'Congratulations!  Your campaign goal of *|GOAL_AMT|* has been met!<br/>'.PHP_EOL.PHP_EOL;
			$pfund_options['mandrill_email_goal_html'] .= 'You can view your campaign at: *|CAMP_URL|*.<br/>'.PHP_EOL.PHP_EOL;
        }
                
        if ( ! isset( $pfund_options['mandrill_email_goal_text'] ) ) {
            $pfund_options['mandrill_email_goal_text'] = 'Dear *|NAME|*,'.PHP_EOL.PHP_EOL;
            $pfund_options['mandrill_email_goal_text'] .= 'Congratulations!  Your campaign goal of *|GOAL_AMT|* has been met!'.PHP_EOL;
			$pfund_options['mandrill_email_goal_text'] .= 'You can view your campaign at: *|CAMP_URL|*.'.PHP_EOL;
        }
        
		if ( isset( $pfund_options['version'] ) ) {
			$old_version = 	$pfund_options['version'];
		} else {
			$old_version = 	'0.7';
		}

		if ( version_compare( $old_version, '0.7.3', '<' ) ) {
			_pfund_add_sample_cause();
			_pfund_add_sample_team();
			if ( ! in_array( 'administrator', $pfund_options['submit_role'] ) ) {
				$pfund_options['submit_role'][] = 'administrator';
				$options_changed = true;
			}
		}

		if ( empty( $pfund_options['paypal_donate_btn'] ) ) {
			$sample_btn = '<form action="" method="post">';
			$sample_btn .= '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" onclick="alert(\'This is a test button.  Please view the readme to setup your PayPal donate button.\');return false;">';
			$sample_btn .= '<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">';
			$sample_btn .= '</form>';
			$pfund_options['paypal_donate_btn'] = $sample_btn;
			$options_changed = true;
		}

		if ( $old_version != PFUND_VERSION ) {
			$pfund_options['version'] = PFUND_VERSION;
			$options_changed = true;
		}
		if ( $options_changed == true ) {
			update_option( 'pfund_options', $pfund_options );
		}
	}
	_pfund_register_types();

	$role = get_role( 'administrator' );
	if ( !empty( $role ) ) {
		$role->add_cap( 'edit_campaign' );
		
	}
	pfund_add_rewrite_rules( $flush_rules );
}

/**
 * Add personal fundraiser rewrite rules
 * @param boolean $flush_rules If true, flush the rewrite rules
 */
function pfund_add_rewrite_rules( $flush_rules = true ) {
	$options = get_option( 'pfund_options' );
	$campaign_root = $options['campaign_slug'];
	$cause_root = $options['cause_slug'];
	$team_root = $options['teamcampaigns_slug'];	

	if ( $options['campaign_listing'] ) {
		add_rewrite_rule("$campaign_root$", "index.php?pfund_action=campaign-list",'top');
	}
	add_rewrite_rule($campaign_root.'/([0-9]+)/?', 'index.php?post_type=pfund_campaign&p=$matches[1]&preview=true','top');
	if ( $options['cause_listing']  ) {
		add_rewrite_rule("$cause_root$", "index.php?pfund_action=cause-list",'top');
	}
	if ( $options['team_listing']  ) {
		add_rewrite_rule("$team_root$", "index.php?pfund_action=team-list",'top');
	}
	if ( $flush_rules ) {
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin.
 */
function pfund_init() {
	global $pfund_processed_action;
	$pfund_options = get_option( 'pfund_options' );
	if ( ! isset( $pfund_options['version'] ) || $pfund_options['version'] != PFUND_VERSION ) {
        //DO NOT flush rewrite rules in this case, because this was triggered by the init action which
        //means not all rewrite rules have been declared.
		pfund_activate(false);
	}
	$pfund_processed_action = false;
	_pfund_load_translation_file();
	_pfund_register_types();
	pfund_add_rewrite_rules( false );	
	if ( ! is_admin() ) {
		pfund_setup_shortcodes();
	}
}


/**
 * Before personal fundraiser options are saved, add/update sort order for the
 * fields.
 * @param mixed $new_options The options that are about to be saved.
 * @param mixed $old_options The current options.
 * @return mixed the options to save.
 */
function pfund_pre_update_options( $new_options, $old_options ) {
	$i=0;
	foreach ( $new_options['fields'] as $idx => $field) {
		$field['sortorder'] = $i++;		
		$new_options['fields'][$idx] = $field;		
	}

	$checkboxes = array( 'allow_registration', 'approval_required', 
		'campaign_listing','cause_listing','team_listing','login_required',  'mandrill',
		'paypal_sandbox', 'use_ssl', 'authorize_net_test_mode' );
	foreach ( $checkboxes as $field_name) {
		if ( isset( $new_options[$field_name] )
				&& $new_options[$field_name] == 'true' ) {
			$new_options[$field_name] = true;
		} else {
			$new_options[$field_name] = false;
		}
	}

    if ( is_array( $old_options ) ) {
        $new_options = array_merge( $old_options, $new_options );
    }
	return $new_options;
}


/**
 * Add personal fundraiser query vars.
 * @param array $query_array current list of query vars
 * @return array updated list of query vars.
 */
function pfund_query_vars( $query_array ) {
	$query_array[] = 'pfund_action';
	$query_array[] = 'pfund_cause_id';
	return $query_array;
}


/**
 * Handler that fires when personal fundraiser options are updated.
 * @param mixed $oldvalue options before they were updated.
 * @param mixed $newvalue options after they were updated.
 */
function pfund_update_options( $oldvalue, $newvalue ) {
	_pfund_register_types();
	$current_submit_roles = $newvalue['submit_role'];

	global $wp_roles;
	$avail_roles = $wp_roles->get_names();
	foreach ( $avail_roles as $key => $desc ) {
		$role = get_role( $key );
		if( in_array ( $key, $current_submit_roles ) ) {
			$role->add_cap( 'edit_campaign' );		
		} else {
			$role->remove_cap( 'edit_campaign' );
		}
	}
	pfund_add_rewrite_rules();	
}

/**
 * Adds a sample cause for plugin demonstration purposes.
 */
function _pfund_add_sample_cause() {
	$stat_li = '<li class="pfund-stat"><span class="highlight">%s</span>%s</li>';
	$sample_content = '<ul class="pfu">';
	$sample_content .= sprintf( $stat_li, '$[pfund-gift-goal]', __( 'funding goal', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '$[pfund-gift-tally]', __( 'raised', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '[pfund-giver-tally]', __( 'givers', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '[pfund-days-left]', __( 'days left', 'pfund' ) );
	
	$sample_content .= '</ul>';
	$sample_content .= '<div style="clear: both;" class="det">';
	$sample_content .= '	<p>'.__( 'I have an event on [pfund-end-date] that I am involved with for my cause.', 'pfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'I am hoping to raise $[pfund-gift-goal] for my cause.', 'pfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'So far I have raised $[pfund-gift-tally].  If you would like to contribute to my cause, click on the donate button below:', 'pfund' ).'</p>';
	$sample_content .='<p class="pht">[pfund-photo]</p>'; 
	$sample_content .='<p style="margin:0; font-weight:bold;">Personal Message : </p>[pfund-message]'; 	$sample_content .= '<p>[pfund-donate]<p>';
	$sample_content .= '</div>';
	$sample_content .= '[pfund-edit]';
		
	$cause = array(
		'post_name' => 'sample-cause',
		'post_title' => __( 'Help Raise Money For My Cause', 'pfund' ),
		'post_content' => $sample_content,
		'post_status' => 'publish',
		'post_type' => 'pfund_cause'
	);
	$cause_root_id = wp_insert_post( $cause );
}

/**/
function _pfund_add_sample_team() {
	/*$stat_li = '<li class="pfund-stat"><span class="highlight">%s</span>%s</li>';
	$sample_content = '<ul class="pfu">';
	$sample_content .= sprintf( $stat_li, '$[pfund-gift-goal]', __( 'funding goal', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '$[pfund-gift-tally]', __( 'raised', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '[pfund-giver-tally]', __( 'givers', 'pfund' ) );
	$sample_content .= sprintf( $stat_li, '[pfund-days-left]', __( 'days left', 'pfund' ) );
	
	$sample_content .= '</ul>';
	$sample_content .= '<div style="clear: both;" class="det">';
	$sample_content .= '	<p>'.__( 'I have an event on [pfund-end-date] that I am involved with for my cause.', 'pfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'I am hoping to raise $[pfund-gift-goal] for my cause.', 'pfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'So far I have raised $[pfund-gift-tally].  If you would like to contribute to my cause, click on the donate button below:', 'pfund' ).'</p>';
	$sample_content .='<p class="pht">[pfund-photo]</p>'; 
	$sample_content .='<p style="margin:0; font-weight:bold;">Personal Message : </p>[pfund-message]';
	$sample_content .= '<p>[pfund-donate]<p>';
	$sample_content .= '</div>';
	$sample_content .= '[pfund-edit]';*/
		$sample_content = "<h1>SUPPORTING NIAGARA HOSPICE CARE</h1>
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
</div>[pfund-edit]";
	$cause = array(
		'post_name' => 'team-creation',
		'post_title' => __( 'team-creation', 'pfund' ),
		'post_content' => $sample_content,
		'post_status' => 'publish',
		'post_type' => 'teamcampaigns'
	);
	$cause_root_id = wp_insert_post( $cause );
}

/**/

/**
 * Loads the translation file; fired from init action.
 */
function _pfund_load_translation_file() {
	load_plugin_textdomain( 'pfund', false, PFUND_FOLDER . 'translations' );
}

/**
 * Register the post types used by personal fundraiser.
 */
function _pfund_register_types() {
	$pfund_options = get_option( 'pfund_options' );
	$template_def = array(
		'public' => true,
		'query_var' => 'pfund_cause',
		'rewrite' => array(
			'slug' => $pfund_options['cause_slug'],
			'with_front' => false,
		),
		'label' => __( 'Causes', 'pfund' ),
		'labels' => array(
			'name' => __( 'Causes', 'pfund' ),
			'singular_name' => __( 'Cause', 'pfund' ),
			'add_new' => __( 'Add New Cause', 'pfund' ),
			'add_new_item' => __( 'Add New Cause', 'pfund' ),
			'edit_item' => __( 'Edit Cause', 'pfund' ),
			'view_item' => __( 'View Cause', 'pfund' ),
			'search_items' => __( 'Search Causes', 'pfund' ),
			'not_found' => __( 'No Causes Found', 'pfund' ),
			'not_found_in_trash' => __( 'No Causes Found In Trash', 'pfund' ),
		),
		'map_meta_cap' => true,
		);
	register_post_type( 'pfund_cause', $template_def );
	register_post_type( 'pfund_cause_list' );

	$campaign_def = array(
		'public' => true,
		'query_var' => 'pfund_campaign',
		'rewrite' => array(
			'slug' => $pfund_options['campaign_slug'],
			'with_front' => false
		),
		
		'label' => __( 'Campaigns', 'pfund' ),
		'labels' => array(
			'name' => __( 'Campaigns', 'pfund' ),
			'singular_name' => __( 'Campaign', 'pfund' ),
			'add_new' => __( 'Add New Campaign', 'pfund' ),
			'add_new_item' => __( 'Add New Campaign', 'pfund' ),
			'edit_item' => __( 'Edit Campaign', 'pfund' ),
			'view_item' => __( 'View Campaign', 'pfund' ),
			'search_items' => __( 'Search Campaigns', 'pfund' ),
			'not_found' => __( 'No Campaigns Found', 'pfund' ),
			'not_found_in_trash' => __( 'No Campaigns Found In Trash', 'pfund' ),
		),
		'supports' => array(
			'title','comments','editor'
		),
		// map_meta_cap will allow us to remap the existing capabilities with new capabilities to match the new custom post type
        'map_meta_cap' => true,
        // capabilities are what we are customising so lets remap them
        'capabilities' => array(
            'edit_post' => 'edit_Campaign',
            'edit_posts' => 'edit_Campaigns',
            'edit_others_posts' => 'edit_other_Campaigns',
            'publish_posts' => 'publish_Campaigns',
            'edit_publish_posts' => 'edit_publish_Campaigns',
            'read_post' => 'read_Campaign',
            'read_private_posts' => 'read_private_Campaigns',
            'delete_post' => 'delete_Campaign',
			'delete_posts' => 'delete_Campaigns',
			'delete_others_posts' => 'delete_others_Campaigns',	
  	
        ),
        // capability_type defines how to make words plural, by default the
        // second word has an 's' added to it and for 'lesson' that's fine
        // however when it comes to words like gallery the plural would become
        // galleries so it's worth adding your own regardless of the plural.
        'capability_type' => array('Campaign', 'Campaigns'),
	);
	
	register_post_type( 'pfund_campaign', $campaign_def );
	register_post_type( 'pfund_campaign_list' );

$args = array(
'public' => true,
		'query_var' => 'teamcampigns',
		'rewrite' => array(
			'slug' => $pfund_options['teamcampaigns_slug'],
			'with_front' => true,
		),
  'label' => __( 'Team Campaigns' ),
'labels' =>                             array(
						
						'all_items'           => 	'Team Campaigns',
						'menu_name'	          =>	'Team Campaigns',
						'singular_name'       =>	'Team Campaigns',
					 	'edit_item'           =>	'Edit Team Campaigns',
					 	 'new_item'            =>	'New Team Campaigns',
						 'add_new'             =>  'Add New Team Campaign',
					 	'view_item'           =>	'View Team Campaigns',
					 	'items_archive'       =>	'Team Campaigns Archive',
					 	'search_items'        =>	'Search Team Campaigns',
					 	'not_found'	          =>	'No Team Campaigns found.',
					 	'not_found_in_trash'  => 'No Team Campaigns found in trash.'
					),
	    'supports'      =>	array( 'title', 'revisions','comments','editor'),
		'map_meta_cap' => true,
	    'public'		  => true,
	
    		
);
register_post_type( 'Team Campaigns', $args );
}
function manage_lesson_capabilitiestt() {
    // gets the role to add capabilities to
    $admin = get_role('administrator');
    $subscriber = get_role('subscriber');
	// replicate all the remapped capabilites from the custom post type lesson
    $caps = array(
    	'edit_Campaign',
    	'edit_Campaigns',
    	'edit_other_Campaigns',
    	'publish_Campaigns',
    	'edit_published_Campaigns',
    	'read_Campaign',
    	'read_private_Campaigns',
    	'delete_Campaign',
	    'delete_Campaigns',
		'delete_others_Campaigns'	
    );
    // give all the capabilities to the administrator
    foreach ($caps as $cap) {
	    $admin->add_cap( $cap );
    }
    // limited the capabilities to the editor or a custom role 
    $subscriber->add_cap( 'edit_Campaign' );
    $subscriber->add_cap( 'edit_Campaigns' );
    $subscriber->add_cap('read_Campaigns');
	global $wp_roles;
	$wp_roles->remove_cap( 'subscriber', 'publish_Campaigns' );
	$wp_roles->add_cap( 'administrator', 'delete_published_Campaigns' );
	$wp_roles->add_cap( 'administrator', 'delete_private_Campaigns' );
	$wp_roles->add_cap( 'administrator', 'delete_Campaigns' );
	$wp_roles->add_cap( 'administrator', 'delete_others_Campaigns' );
}
add_action( 'admin_init', 'manage_lesson_capabilitiestt');
?>
