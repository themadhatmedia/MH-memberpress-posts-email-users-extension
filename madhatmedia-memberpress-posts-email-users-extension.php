<?php
/**
 * Plugin Name: MadHatMedia MemberPress Posts Email Users Extension
 * Plugin URI: https://madhatmafia.com
 * Description: Send email after creating new post based on the subscription
 * Version: 1.1
 * Author: Mad Hat Media LLC
 * Author URI: https://madhatmafia.com
 */

/*INCLUDE FILES*/
require_once('vendor/autoload.php');
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkAttachment;

if ( ! defined( 'WPINC' ) ) {
    exit;
}

 if (!function_exists('mhm_plugin_verify_account')) {
	function mhm_plugin_verify_account( $email, $license_key, $product_id ) {

		$data = wp_remote_get( 'https://madhatmafia.com/woocommerce/?wc-api=software-api&request=check&email='.$email.'&license_key='.$license_key.'&product_id='.$product_id);
		if ( isset( $data["body"])) {
			$data = json_decode($data["body"]);
			return $data->success;
			
		}
	}
}

/* DASHBOARD PAGE */
//
add_action('admin_menu', 'mhm_memberpress_post_email_postmark_menu_setup');

function mhm_memberpress_post_email_postmark_menu_setup() {

		add_menu_page('MHM MemberPress Post Email Postmark Extension', 'MHM MemberPress Post Email Postmark Extension', 'manage_options', 'mhm-memberpress-post-email-postmark-setup');
		
		$email = get_option('mhm_memberpress_post_email_postmark_extention_email');
		$license_key = get_option('mhm_memberpress_post_email_postmark_extention_license_key');
		$product_id = get_option('mhm_memberpress_post_email_postmark_extention_product_id');
		//$product_id = 'woocommerce-addon-retailer';
		
		add_submenu_page( 'mhm-memberpress-post-email-postmark-setup', 'License', 'License',
		'manage_options', 'mhm-memberpress-post-email-postmark-setup', 'mhm_memberpress_post_email_postmark_setup_callback');
	
		$verify = mhm_plugin_verify_account( $email, $license_key, $product_id ); 
		
		if ( isset($verify ) ) {
				if ( $verify == true ) {
				add_submenu_page( 'mhm-memberpress-post-email-postmark-setup', 'Postmark Credentials', 'Postmark Credentials',
			'manage_options', 'mhm-memberpress-post-email-postmark-credentials-setup', 'mhm_memberpress_post_email_postmark_credentials_setup_callback');
				}
		}
		
		
		

}
function mhm_memberpress_post_email_postmark_credentials_setup_callback() {
	if ( isset($_POST['mhm_memberpress_postmark_form']) ) {
		update_option('mhm_memberpress_post_email_postmark_extention_postmark_key', $_POST['key'] );
		echo "<script type='text/javascript'>
        window.location=document.location.href;
        </script>"; 
		
	}

	$key = get_option('mhm_memberpress_post_email_postmark_extention_postmark_key');
    ?>
    <div class="notice notice-<?php echo $data?> ">
	<h3>Postmark API Credential</h3>
		<form method="POST">
		  <p>
			<input style="display: inline;" type="text" value="<?php echo $key ?>" placeholder="Key" name="key" required />
			<input style="display: inline;" type="submit" value="Save" name="mhm_memberpress_postmark_form" />
		  </p>
		</form>

    </div>
    <?php

}

function mhm_memberpress_post_email_postmark_get_membership_lists() {
        global $wpdb;

        $userData = array();

        $apiData = array();

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_members INNER JOIN {$wpdb->prefix}posts ON {$wpdb->prefix}mepr_members.memberships = {$wpdb->prefix}posts.ID";
        $mepr_members = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_events";
        $mepr_events = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_jobs";
        $mepr_jobs = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_rule_access_conditions";
        $mepr_rule_access_conditions = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_subscriptions";
        $mepr_subscriptions = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_tax_rates";
        $mepr_tax_rates = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_tax_rate_locations";
        $mepr_tax_rate_locations = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}mepr_transactions";
        $mepr_transactions = $wpdb->get_results( $sql, OBJECT );

        $sql = "SELECT * FROM {$wpdb->prefix}posts WHERE `post_status`='publish' AND `post_type` = 'memberpressproduct'";
        $member_memberships = $wpdb->get_results( $sql, OBJECT );

        $member_subscriptions = array();

        $users = get_users( array( 'fields' => array( 'ID' ) ) );
        foreach($users as $user_id){

            $user_info = get_userdata( $user_id->ID );
            $userData[ $user_id->ID ]['first_name'] = $user_info->first_name;
            $userData[ $user_id->ID ]['last_name'] = $user_info->last_name;
            $userData[ $user_id->ID ]['user_login'] = $user_info->user_login;
            $userData[ $user_id->ID ]['user_nicename'] = $user_info->user_nicename;
            $userData[ $user_id->ID ]['user_email'] = $user_info->user_email;
            $userData[ $user_id->ID ]['user_registered'] = $user_info->user_registered;
            $userData[ $user_id->ID ]['display_name'] = $user_info->display_name;

            $sql = "SELECT COUNT(user_id) as `totalPending` FROM {$wpdb->prefix}mepr_subscriptions WHERE `user_id` = " . $user_id->ID . " AND `status` = 'pending'";
            $subscription = $wpdb->get_results( $sql, OBJECT );

            $member_subscriptions[$user_id->ID]['pending'] = $subscription[0]->totalPending;

            $sql = "SELECT COUNT(user_id) as `totalActive` FROM {$wpdb->prefix}mepr_subscriptions WHERE `user_id` = " . $user_id->ID . " AND `status` = 'active'";
            $subscription = $wpdb->get_results( $sql, OBJECT );

            $member_subscriptions[$user_id->ID]['active'] = $subscription[0]->totalActive;

            $sql = "SELECT COUNT(user_id) as `totalExpire` FROM {$wpdb->prefix}mepr_subscriptions WHERE `user_id` = " . $user_id->ID . " AND `status` = 'cancelled'";
            $subscription = $wpdb->get_results( $sql, OBJECT );

            $member_subscriptions[$user_id->ID]['expire'] = $subscription[0]->totalExpire;


        }

        $apiData['mepr_user_memberships'] = '';

        foreach ( $mepr_members as $member ) {

            $memberships = $member->memberships;
            $data = array();

            foreach ( explode( ",", $memberships ) as $membership ) {

                $sql = "SELECT * FROM {$wpdb->prefix}posts WHERE `post_type` = 'memberpressproduct' AND `ID` = " . $membership;
                $sql_membership = $wpdb->get_results( $sql, OBJECT );

                $data[] = strtolower( $sql_membership[0]->post_title );

            }

            $apiData['mepr_user_memberships'][$member->user_id] = $data;

        }

        $apiData['mepr_members'] = $mepr_members;
        $apiData['mepr_events'] = $mepr_events;
        $apiData['mepr_jobs'] = $mepr_jobs;
        $apiData['mepr_rule_access_conditions'] = $mepr_rule_access_conditions;
        $apiData['mepr_subscriptions'] = $mepr_subscriptions;
        $apiData['mepr_tax_rates'] = $mepr_tax_rates;
        $apiData['mepr_tax_rate_locations'] = $mepr_tax_rate_locations;
        $apiData['mepr_transactions'] = $mepr_transactions;
        $apiData['member_subscriptions'] = $member_subscriptions;
        $apiData['member_memberships'] = $member_memberships;
        $apiData['blog_title'] = get_bloginfo();

        $apiData['users'] = $userData;

        echo json_encode( $apiData );

		exit();
}


function mhm_memberpress_post_email_postmark_setup_callback() {
	if ( isset($_POST['mhm_verify_wp_membership_form']) ) {
		update_option('mhm_memberpress_post_email_postmark_extention_email', $_POST['email'] );
		update_option('mhm_memberpress_post_email_postmark_extention_license_key', $_POST['license_key'] );
		update_option('mhm_memberpress_post_email_postmark_extention_product_id', 'memberpress-post-email-postmark' );
		echo "<script type='text/javascript'>
        window.location=document.location.href;
        </script>"; 
		
	}

	$email = get_option('mhm_memberpress_post_email_postmark_extention_email');
	$license_key = get_option('mhm_memberpress_post_email_postmark_extention_license_key');
	$product_id = get_option('mhm_memberpress_post_email_postmark_extention_product_id');
	$verify = mhm_plugin_verify_account( $email, $license_key, $product_id ); 
	
	if ( !$verify ) {
		echo "<h3>The license key entered was invalid. Please check your credentials and try again.</h3>";
	} else {
		echo "<h3>Your license is successfully verified.</h3>";
	}	
	
    ?>
    <div class="notice notice-<?php echo $data?> is-dismissible">
	<h3>Software key for Madhatmedia MemberPress Post Email Postmark Extension plugin</h3>
		<form method="POST">
		  <p>
			<input style="display: inline;" type="text" value="<?php echo $email ?>" placeholder="Email" name="email" required />
			<input style="display: inline;" type="text" value="<?php echo $license_key ?>" placeholder="License Key" name="license_key" required />
			<input style="display: inline;" type="submit" value="Submit" name="mhm_verify_wp_membership_form" />
		  </p>
		</form>

    </div>
    <?php
}


function madhatmedia_posts_email_users( $post_id ) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	
	$email = get_option('mhm_memberpress_post_email_postmark_extention_email');
	$license_key = get_option('mhm_memberpress_post_email_postmark_extention_license_key');
	$product_id = get_option('mhm_memberpress_post_email_postmark_extention_product_id');
	$verify = mhm_plugin_verify_account( $email, $license_key, $product_id );
	$key = get_option('mhm_memberpress_post_email_postmark_extention_postmark_key');
    $client = new PostmarkClient($key);


        $categories = wp_get_post_categories( $post_id );

        $results = file_get_contents( mhm_memberpress_post_email_postmark_get_membership_lists());


        $results = json_decode( $results );
        $memberships = array();

        $members = $results->mepr_members;
        $membershipsArray = $results->member_memberships;
        $catNames = array();
        $emails = array();
        $titles = array();
        $email_recipient = '';

        if (isset($_POST['post_category']) && isset($verify) ) {
            foreach ( $_POST['post_category'] as $key => $cat_id ) {

                if ($cat_id == 0) {
                    continue;
                }
                $cat_name = get_cat_name( $cat_id );


                $catNames[] = strtolower( $cat_name );


            }
        }

        $membership_exists = false;

        foreach ( $membershipsArray as $membership ) {

            $membership_title = $membership->post_title;

                $keys = preg_filter('~' . strtolower( $membership_title ) . '~', '$0', $catNames);

                if ( ! empty( $keys ) ) {

                    $membership_exists = true;
                    
                }

            

        }


        foreach ( $members as $member ) {

            $memberships = explode( ',', $member->memberships );

            foreach ( $memberships as $membership ) {

                $key = mhm_memberpress_email_users_extention_searchForId( $membership, $membershipsArray );
                $membership_title = $membershipsArray[$key]->post_title;
                $titles[] = $membership_title;

                if ( in_array( strtolower( $membership_title ), $catNames ) ) {

                    $emailUserID = $member->user_id;

                    $userdata = get_userdata( $emailUserID );


                    //$emails[] = 'Bcc: ' . $userdata->user_email;
                    $email_recipient .= $userdata->user_email.',';
  
                }

                if ( strpos( strtolower( $membership_title ), 'vip' ) !== false && $membership_exists ) {

                    $emailUserID = $member->user_id;

                    $userdata = get_userdata( $emailUserID );

                    $emails[] = $userdata->user_email;
                    $email_recipient .= $userdata->user_email.',';

                }

                if ( strtolower( $membership_title ) == 'tipster' && $membership_exists ) {

                    $emailUserID = $member->user_id;

                    $userdata = get_userdata( $emailUserID );

                    $emails[] = $userdata->user_email;
                    $email_recipient .= $userdata->user_email.',';


                }

                if ( strtolower( $membership_title ) == 'free' && ! in_array( 'testcat', $catNames ) && ! in_array( 'blog', $catNames ) ) {

                    $emailUserID = $member->user_id;

                    $userdata = get_userdata( $emailUserID );

                    $emails[] = $userdata->user_email;
                    $email_recipient .= $userdata->user_email.',';

                }


                foreach ( $catNames as $catName ) {


                    if ( strpos( strtolower( $membership_title ), strtolower( $catName ) ) !== false ) {

                        $emailUserID = $member->user_id;

                        $userdata = get_userdata( $emailUserID );

                        $emails[] = $userdata->user_email;
                        $email_recipient .= $userdata->user_email.',';

                    }


                    if ( strpos( strtolower( $membership_title ), $catName ) !== false ) {

                        $emailUserID = $member->user_id;

                        $userdata = get_userdata( $emailUserID );

                        $emails[] = $userdata->user_email;
                        $email_recipient .= $userdata->user_email.',';

                    }


                    if ( strpos( strtolower( $membership_title ), $catName ) === 0 ) {

                        $emailUserID = $member->user_id;

                        $userdata = get_userdata( $emailUserID );

                        $emails[] = $userdata->user_email;
                        $email_recipient .= $userdata->user_email.',';
 
                    }

                }

            }

        }

        $emails = array_unique($emails);

    if ( count( $emails ) > 0 ) {

        $emails[] = 'succute@yahoo.com';
        $email_recipient .= 'succute@yahoo.com,';
        //$emails[] = 'From: BetHub <tips@bethub.pro>';
      
    }


    
    $myPost = get_post( $post_id );


    if ( count( $emails ) > 0 ) {

        if ( ( $myPost->post_type == 'post' || $myPost->post_type == 'revision' ) && $myPost->post_type != 'page' ) {

                $screen = get_current_screen();

                $post   = get_post( $post_id );

                if ( $screen->post_type != 'page' && $_POST ) {

                    $output =  apply_filters( 'the_content', $post->post_content );

                    $to = 'tips@bethub.pro';
                    $subject = $post->post_title;
                    $body = $output;
					
                    $user_recipients = array_chunk($emails, 45, true);
    	
    					$message= ['To' => $to,
                 		'Bcc' => $email_recipient,
                 		'Subject' => $subject,
                 		'HtmlBody' =>$body,
                 		'From' => "BetHub <tips@bethub.pro>"];

    					$responses = $client->sendEmail( 'BetHub <tips@bethub.pro>', $to, $subject, $body, NULL, NULL, true, NULL, NULL, implode( ",", $emails ) );
    					set_time_limit(0);


                }


        }

    }

        remove_action( 'save_post', 'madhatmedia_posts_email_users' );



}

add_action( 'save_post', 'madhatmedia_posts_email_users' );

function mhm_memberpress_email_users_extention_searchForId($id, $array) {
   foreach ($array as $key => $val) {
       if ($val->ID === $id) {
           return $key;
       }
   }
   return null;
}

add_action( 'buddyforms_after_save_post', function( $post_id ) {

   

        if ( $_POST['form_slug'] == 'tips' ) {

            $category = $_POST['category'];
            $buddyforms_form_title = $_POST['buddyforms_form_title'];
            $buddyforms_form_content = $_POST['buddyforms_form_content'];

            $key = get_option('mhm_memberpress_post_email_postmark_extention_postmark_key');
			$client = new PostmarkClient($key);


			$categories = wp_get_post_categories( $post_id );

			$results = file_get_contents( mhm_memberpress_post_email_postmark_get_membership_lists());


			$results = json_decode( $results );
			$memberships = array();

			$members = $results->mepr_members;
			$membershipsArray = $results->member_memberships;
			$catNames = array();
			$emails = array();
			$titles = array();
			$email_recipient = '';

			if (isset($_POST['category'])) {
				foreach ( $_POST['category'] as $key => $cat_id ) {

					if ($cat_id == 0) {
						continue;
					}
					$cat_name = get_cat_name( $cat_id );


					$catNames[] = strtolower( $cat_name );


				}
			}

			$membership_exists = false;

			foreach ( $membershipsArray as $membership ) {

				$membership_title = $membership->post_title;

					$keys = preg_filter('~' . strtolower( $membership_title ) . '~', '$0', $catNames);

					if ( ! empty( $keys ) ) {

						$membership_exists = true;
						
					}

				

			}


			foreach ( $members as $member ) {

				$memberships = explode( ',', $member->memberships );

				foreach ( $memberships as $membership ) {

					$key = mhm_memberpress_email_users_extention_searchForId( $membership, $membershipsArray );
					$membership_title = $membershipsArray[$key]->post_title;
					$titles[] = $membership_title;

					if ( in_array( strtolower( $membership_title ), $catNames ) ) {

						$emailUserID = $member->user_id;

						$userdata = get_userdata( $emailUserID );


						//$emails[] = 'Bcc: ' . $userdata->user_email;
						$email_recipient .= $userdata->user_email.',';
	  
					}

					if ( strpos( strtolower( $membership_title ), 'vip' ) !== false && $membership_exists ) {

						$emailUserID = $member->user_id;

						$userdata = get_userdata( $emailUserID );

						$emails[] = $userdata->user_email;
						$email_recipient .= $userdata->user_email.',';

					}

					if ( strtolower( $membership_title ) == 'tipster' && $membership_exists ) {

						$emailUserID = $member->user_id;

						$userdata = get_userdata( $emailUserID );

						$emails[] = $userdata->user_email;
						$email_recipient .= $userdata->user_email.',';


					}

					if ( strtolower( $membership_title ) == 'free' && ! in_array( 'testcat', $catNames ) && ! in_array( 'blog', $catNames ) ) {

						$emailUserID = $member->user_id;

						$userdata = get_userdata( $emailUserID );

						$emails[] = $userdata->user_email;
						$email_recipient .= $userdata->user_email.',';

					}


					foreach ( $catNames as $catName ) {


						if ( strpos( strtolower( $membership_title ), strtolower( $catName ) ) !== false ) {

							$emailUserID = $member->user_id;

							$userdata = get_userdata( $emailUserID );

							$emails[] = $userdata->user_email;
							$email_recipient .= $userdata->user_email.',';

						}


						if ( strpos( strtolower( $membership_title ), $catName ) !== false ) {

							$emailUserID = $member->user_id;

							$userdata = get_userdata( $emailUserID );

							$emails[] = $userdata->user_email;
							$email_recipient .= $userdata->user_email.',';

						}


						if ( strpos( strtolower( $membership_title ), $catName ) === 0 ) {

							$emailUserID = $member->user_id;

							$userdata = get_userdata( $emailUserID );

							$emails[] = $userdata->user_email;
							$email_recipient .= $userdata->user_email.',';
	 
						}

					}

				}

			}

			$emails = array_unique($emails);

			if ( count( $emails ) > 0 ) {

				$emails[] = 'succute@yahoo.com';
				$email_recipient .= 'succute@yahoo.com,';
				//$emails[] = 'From: BetHub <tips@bethub.pro>';
			  
			}


    
			$myPost = get_post( $post_id );


			if ( count( $emails ) > 0 ) {

			  
				$output =  apply_filters( 'the_content', $buddyforms_form_content );

				$to = 'tips@bethub.pro';
				$subject = $buddyforms_form_title;
				$body = $output;

				$user_recipients = array_chunk($emails, 45, true);
	
				$message= ['To' => $to,
				'Bcc' => $email_recipient,
				'Subject' => $subject,
				'HtmlBody' =>$body,
				'From' => "BetHub <tips@bethub.pro>"];

				$responses = $client->sendEmail( 'BetHub <tips@bethub.pro>', $to, $subject, $body, NULL, NULL, true, NULL, NULL, implode( ",", $emails ) );
				set_time_limit(0);

			}

        }

    

});