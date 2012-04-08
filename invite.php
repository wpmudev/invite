<?php
/*
Plugin Name: Invite
Plugin URI: http://premium.wpmudev.org/project/invite
Description: Allow your users to invite - via email - their friends and colleagues to check out their blog and sign up at your site!
Author: S H Mohanjith (Incsub), Andrew Billits (Incsub)
Version: 1.1.3
Author URI:
WDP ID: 9
Network: true
Text Domain: invite
*/

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
global $invite_message_subject, $invite_message_content;

$invite_message_subject = get_site_option("invite_message_subject", "SITE_NAME Invite");

$invite_message_content = get_site_option("invite_message_content", "Dear INVITE_EMAIL,

USER_EMAIL has sent you an invite to sign up at SITE_NAME - SITE_URL.

INVITE_MESSAGE

You can create your account here:
SIGNUP_URL

We are looking forward to seeing you on the site.

Cheers,

--The Team @ SITE_NAME");

/*
The following option allows users to import emails from their address book on their Hotmail, MSN, etc email accoints.
This feature is only available to Incsub clients due to the fact that it uses Incsub server resources.
*/
$invite_contact_importer = "disabled"; //options: "enabled" OR "disabled"
$invite_incsub_gateway_encryption = "on"; //options: "on" OR "off"
$invite_incsub_gateway = "http://gateway.wpmudev.org";
$invite_incsub_authcode = "";

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

add_action('admin_menu', 'invite_plug_pages');
add_action('network_admin_menu', 'invite_plug_network_pages');
add_action('init', 'invite_init');

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function invite_init() {
	global $invite_message_subject, $invite_message_content;
	
	if ( !is_multisite() ) {
		exit( __('The Invite plugin is only compatible with WordPress Multisite.', 'invite') );
	}
	
	load_plugin_textdomain('invite', false, 'invite/languages');
}

function invite_plug_pages() {
	global $wpdb, $wp_roles, $current_user;
	add_submenu_page('users.php', __('Invites', 'invite'), __('Invites', 'invite'), 'read', 'invite_main', 'invite_page_main_output');
}

function invite_plug_network_pages() {
	add_submenu_page('settings.php', __('Invite Settings', 'invite'), __('Invite Settings', 'invite'), 'manage_site_options', 'invite_settings', 'invite_settings_page_output');
}

function invite_send_email($tmp_invite_email, $tmp_invite_message) {
	global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $invite_message_subject, $invite_message_content, $invite_from_email;

	$tmp_username =  $wpdb->get_var("SELECT user_login FROM " . $wpdb->users . " WHERE ID = '" . $user_ID . "'");
	$tmp_user_email =  $wpdb->get_var("SELECT user_email FROM " . $wpdb->users . " WHERE ID = '" . $user_ID . "'");

	$message_content = $invite_message_content;
	$message_content = str_replace( "SITE_NAME", $current_site->site_name, $message_content );
	$message_content = str_replace( "SITE_URL", 'http://' . $current_site->domain . '', $message_content );
	$message_content = str_replace( "SIGNUP_URL", network_home_url('wp-login.php?action=register'), $message_content );
	$message_content = str_replace( "USERNAME", $tmp_username, $message_content );
	$message_content = str_replace( "USER_EMAIL", $tmp_user_email, $message_content );
	$message_content = str_replace( "INVITE_EMAIL", $tmp_invite_email, $message_content );
	$message_content = str_replace( "\'", "'", $message_content );

	if ($tmp_invite_message == ''){
		$message_content = str_replace( "INVITE_MESSAGE", '', $message_content );
	} else {
		$message_content = str_replace( "INVITE_MESSAGE", '"' . $tmp_invite_message . '"', $message_content );
	}
	
	$subject_content = $invite_message_subject;
	$subject_content = str_replace( "SITE_NAME", $current_site->site_name, $subject_content );

	$admin_email = 'admin@' . $current_site->domain;

	//$invite_from_email = "user"; //options: "user" OR "admin"
	$invite_from_email = "user";

	if ($invite_from_email == "user"){
		$from_email = $tmp_user_email;
	} else if ($invite_from_email == "admin"){
		$from_email = $admin_email;
	} else {
		$from_email = $admin_email;
	}
	
	$message_headers = "MIME-Version: 1.0\n" . "From: " . get_site_option( "site_name" ) .  " <{$from_email}>\n" . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
	wp_mail($tmp_invite_email, $subject_content, $message_content, $message_headers);
}

function invite_gateway_encrypt($data) {
	if(!isset($chars))
	{
		// 3 different symbols (or combinations) for obfuscation
		// these should not appear within the original text
		$sym = array('¶', '¥xQ', '|');
		
		foreach(range('a','z') as $key=>$val)
			$chars[$val] = str_repeat($sym[0],($key + 1)).$sym[1];
		$chars[' '] = $sym[2];
		
		unset($sym);
	}
	
	// encrypt
	$data = strtr(strtolower($data), $chars);
	return $data;
	
}

//------------------------------------------------------------------------//
//---Page Output Functions------------------------------------------------//
//------------------------------------------------------------------------//

function invite_settings_page_output() {
	global $wpdb, $wp_roles, $current_user, $current_site, $invite_contact_importer, $invite_incsub_gateway, $invite_incsub_authcode, $invite_incsub_gateway_encryption,  $invite_message_subject, $invite_message_content, $invite_from_email;
	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'invite') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
			?>
			<h2><?php _e('Invite Settings', 'invite') ?></h2>
            <form method="post" action="settings.php?page=invite_settings&action=process">
            <table class="form-table">
		<tr valign="top">
		    <th scope="row"><?php _e('Invitation Message Subject', 'invite') ?></th>
		    <td>
			<input name="invite_message_subject" id="invite_message_subject"
			value="<?php print $invite_message_subject; ?>" />
			<br /><?php _e('You can use following variables SITE_NAME', 'invite') ?>
		    </td>
		</tr>
		<tr valign="top">
		    <th scope="row"><?php _e('Invitation Message Content', 'invite') ?></th>
		    <td>
			<textarea name="invite_message_content"
				id="invite_message_content"
				rows="14" cols="70"><?php print $invite_message_content; ?></textarea>
			<br /><?php _e('You can use following variables SITE_NAME,SITE_URL,SIGNUP_URL,USERNAME,USER_EMAIL,INVITE_EMAIL"', 'invite') ?>
		    </td>
		</tr>
            </table>
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Save Changes', 'subscribe-by-email') ?>" />
            </p>
            </form>
			<?php
		break;
		//---------------------------------------------------//
		case "process":
			update_site_option( "invite_message_subject", stripcslashes($_POST[ 'invite_message_subject' ]) );
			update_site_option( "invite_message_content", stripcslashes($_POST[ 'invite_message_content' ]) );
			echo "
			<script type='text/javascript'>
			window.location='settings.php?page=invite_settings&updated=true&updatedmsg=" . urlencode(__('Settings saved.', 'invite')) . "';
			</script>
			";
		break;
	}
	echo '</div>';
}

function invite_page_main_output() {
	global $wpdb, $wp_roles, $current_user, $current_site, $invite_contact_importer, $invite_incsub_gateway, $invite_incsub_authcode, $invite_incsub_gateway_encryption;

	if (isset($_GET['updated'])) {
		?><div id="message" class="updated fade"><p><?php _e(urldecode($_GET['updatedmsg']), 'invite') ?></p></div><?php
	}
	echo '<div class="wrap">';
	switch( $_GET[ 'action' ] ) {
		//---------------------------------------------------//
		default:
		?>
			<h2><?php _e('Send Invites', 'invite') ?></h2>
            <p><?php _e('Send your colleagues and friends an invitation to signup at', 'invite') ?> <?php echo $current_site->site_name; ?>
            <?php
            if ($invite_contact_importer == "enabled"){
                ?>
                <br /><br />
               <strong> <?php _e('Do you use GMail, Hotmail, Lycos, MSN or Yahoo for email?', 'invite') ?> <br /> <a href="users.php?page=invite_main&action=importer"><?php _e('Click here', 'invite') ?></a> <?php _e('to easily import email addresses from those accounts (completely safely!)', 'invite') ?></strong><br /><br />
                <?php
            }
            ?>
            </p> 
            <form method="post" action="users.php?page=invite_main&action=process">
            <table class="form-table">
            <tr valign="top">
            <th scope="row"><?php _e('Special Message:', 'invite') ?></th>
            <td>
            <textarea name="invite_message" id="invite_message" rows='3' cols='45' style="width: 95%" wrap="soft"></textarea>
            <br /><?php _e('Optional. Include a message with your invitations.', 'invite') ?></td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Email Addresses:', 'invite') ?></th>
            <td>
			<?php
            $tmp_invite_imported_emails = $_POST['invite_imported_emails'];
            $tmp_invite_imported_emails = str_replace( ",", ', ', $tmp_invite_imported_emails );
            ?>
            <textarea name="invite_emails" id="invite_content" rows='8' cols='45' style="width: 95%" wrap="virtual"><?php echo $tmp_invite_imported_emails; ?></textarea>
            <br /><?php _e('Place a comma between each email address (ex john@site.com, bob@site.com).', 'invite') ?></td>
            </tr>
            </table>
            
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Send Invite(s)', 'invite') ?>" />
            </p>
            </form>
		<?php
		break;
		//---------------------------------------------------//
		case "importer":
		?>
			<h2><?php _e('Import Contacts', 'invite') ?></h2>
            <?php
			//check gateway authorization
			$tmp_curl_url = $invite_incsub_gateway . '/auth/?auth_code=' . $invite_incsub_authcode;
			$agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)";
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$tmp_curl_url);
			curl_setopt($ch, CURLOPT_USERAGENT, $agent);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			$tmp_returned_data=curl_exec ($ch);
			$tmp_gateway_auth = $tmp_returned_data;
			if ($tmp_gateway_auth == 'valid'){
			//---------------------------------------------------//
			?>
			<p><?php _e('Import a list of your contacts from your web-based email account.', 'invite') ?></p>
			<p><?php _e('After this you can choose which ones you want to send invites too.', 'invite') ?></p>
            <form method="post" action="users.php?page=invite_main&action=import_process">
            <table class="form-table">
            <tr valign="top">
            <th scope="row"><?php _e('Sevice:', 'invite') ?></th>
            <td>
            <select name="invite_service" id="invite_service">
                <option value="gmail">GMail</option>
                <option value="hotmail">Hotmail</option>
                <option value="lycos">Lycos</option>
                <option value="msn">MSN</option>
                <option value="yahoo">Yahoo</option>
            </select>
            <br /><?php _e('Please choose your email service.', 'invite') ?></td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Email:', 'invite') ?></th>
            <td>
            <input name="invite_email" type="text" id="invite_email" style="width: 95%" value="" size="45" />
            <br /><?php _e('ex: john@hotmail.com', 'invite') ?></td>
            </tr>
            <tr valign="top">
            <th scope="row"><?php _e('Password:', 'invite') ?></th>
            <td>
           <input name="invite_password" type="password" id="invite_password" style="width: 95%" value="" size="45" />
            <br /><?php //_e('') ?></td>
            </tr>
            </table>
            
            <p class="submit">
            <input type="submit" name="Submit" value="<?php _e('Import Email Addresses', 'invite') ?>" />
            </p>
            </form>
            <?php
			//---------------------------------------------------//
			} else {
				//gateway info invalid
			?>
            <p><?php _e('This feature is temporarily unavaiable.', 'invite') ?></p>
			<?php
			}
			?>
        <?php
		break;
		//---------------------------------------------------//
		case "import_process":
			?>
            <h2><?php _e('Import Contacts', 'invite') ?></h2>
            <?php
			$tmp_invite_email = $_POST['invite_email'];
			$tmp_invite_password = $_POST['invite_password'];
			$tmp_invite_service = $_POST['invite_service'];
			//import emails
			if ($invite_incsub_gateway_encryption == 'on'){
				$tmp_invite_password = invite_gateway_encrypt($tmp_invite_password);
				$tmp_invite_email = invite_gateway_encrypt($tmp_invite_email);
				
				$tmp_curl_url = $invite_incsub_gateway . '/invite/?auth_code=' . $invite_incsub_authcode . '&username=' . urlencode($tmp_invite_email) . '&password=' . urlencode($tmp_invite_password) . '&service=' . $tmp_invite_service . '&encryption=on';
			} else {
				$tmp_curl_url = $invite_incsub_gateway . '/invite/?auth_code=' . $invite_incsub_authcode . '&username=' . $tmp_invite_email . '&password=' . $tmp_invite_password . '&service=' . $tmp_invite_service . '&encryption=off';
			}
			$agent = "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.4) Gecko/20030624 Netscape/7.1 (ax)";
			$ch = curl_init();
			//echo $tmp_curl_url;
			curl_setopt($ch, CURLOPT_URL,$tmp_curl_url);
			curl_setopt($ch, CURLOPT_USERAGENT, $agent);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			$tmp_returned_data=curl_exec ($ch);
			$tmp_email_data = $tmp_returned_data;
			if ($tmp_email_data == 'failed' || $tmp_email_data == ''){
				?>
				<p><?php _e('There was a problem while trying to import your contacts. Please check your information and try again. <a href="users.php?page=invite_main&action=importer">Click here</a> to try again.', 'invite') ?></p>
                <?php
			} else {
				?>
                <p><?php _e('Email addresses successfully imported!', 'invite') ?></p>
                <form method="post" action="users.php?page=invite_main">
				<input name="invite_imported_emails" type="hidden" id="invite_imported_emails"value="<?php echo $tmp_email_data; ?>"/>
                
                <p class="submit">
                <input type="submit" name="Submit" value="<?php _e('Continue', 'invite') ?>" />
                </p>
                </form>
				<?php
			}
		break;
		//---------------------------------------------------//
		case "process":
			$tmp_invite_message = $_POST['invite_message'];
			$tmp_invite_emails = $_POST['invite_emails'];
			$tmp_invite_emails = ',,' . $tmp_invite_emails . ',,';
			$tmp_invite_emails = str_replace( " ", '', $tmp_invite_emails );
			$tmp_invite_emails_array = explode(",", $tmp_invite_emails);
			?>
			<p><?php _e('Sending Invite(s)!', 'invite') ?></p>
			<?php
			foreach ($tmp_invite_emails_array as $tmp_email){
				invite_send_email($tmp_email, $tmp_invite_message);
			}
			
			echo "
			<SCRIPT LANGUAGE='JavaScript'>
			window.location='users.php?page=invite_main&updated=true&updatedmsg=" . urlencode(__('Invite(s) sent.', 'invite')) . "';
			</script>
			";
		break;
		//---------------------------------------------------//
		case "temp":
		break;
		//---------------------------------------------------//
	}
	echo '</div>';
}

if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
