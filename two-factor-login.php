<?php
/*
Plugin Name: WP Two Factor Auth
Plugin URI: http://oskarhane.com/plugin-two-factor-auth-for-wordpress
Description: Secure your WordPress login with two factor auth. Users will be prompted with a page to enter a One Time Password when they login.
Author: Oskar Hane, Volodymyr Kolesnykov
Author URI: http://oskarhane.com
Version: 4.5
License: GPLv2 or later
*/

defined('ABSPATH') || die();

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require __DIR__ . '/vendor/autoload.php';
}
elseif (file_exists(ABSPATH . 'vendor/autoload.php')) {
	require ABSPATH . 'vendor/autoload.php';
}

//error_reporting(E_ALL);
//ini_set("display_errors", true);
define('TFA_TEXT_DOMAIN', 'two-factor-auth');
define('TFA_MAIN_PLUGIN_PATH', dirname( __FILE__ ));

function getTFAClass()
{
	include_once TFA_MAIN_PLUGIN_PATH.'/hotp-php-master/hotp.php';
	include_once TFA_MAIN_PLUGIN_PATH.'/class.TFA.php';
	
	$tfa = new TFA(new HOTP());
	
	return $tfa;
}

function tfaInitLogin()
{			
	$tfa = getTFAClass();
	$res = $tfa->preAuth(array('log' => $_POST['user']));

	print json_encode(array('status' => $res));
	exit;
}
add_action( 'wp_ajax_nopriv_tfa-init-otp', 'tfaInitLogin');
add_action( 'wp_ajax_tfa-init-otp', 'tfaInitLogin');


function tfaLoginForm()
{
?>
<p id="tfa-block" style="display: none;">
	<label for="two_factor_auth"><?php _e("One Time Password", TFA_TEXT_DOMAIN); ?><br/>
		<input type="text" name="two_factor_code" id="two_factor_auth" autocomplete="off" disabled="disabled" style="margin-bottom: 2px;"/>
	</label>
	<span style="font-size: small; display: inline-block; margin-bottom: 16px;"><?php _e('(check your email or OTP-app to get this password)', TFA_TEXT_DOMAIN); ?></span>
</p>
<?php
}

add_action('login_form', 'tfaLoginForm');

function tfaVerifyCodeAndUser($user, $username, $password)
{
	
	
	$installed_version = get_option('tfa_version');
	if($installed_version < 4)
		return $user;
		
	$tfa = getTFAClass();
	
	if(is_wp_error($user))
		return $user;

	$params = $_POST;
	$params['log'] = $username;
	$params['caller'] = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI'];
	
	$code_ok = $tfa->authUserFromLogin($params);

	
	if(!$code_ok)
		return new WP_Error('authentication_failed', __('<strong>ERROR</strong>: The Two Factor Code you entered was incorrect.', TFA_TEXT_DOMAIN));
	
	if($user)
		return $user;
		
	return wp_authenticate_username_password(null, $username, $password);
}
add_filter('authenticate', 'tfaVerifyCodeAndUser', 99999999999, 3);//We want to be the last filter that runs.

function tfaRegisterTwoFactorAuthSettings()
{
	global $wp_roles;
	if (!isset($wp_roles))
		$wp_roles = new WP_Roles();
	
	foreach($wp_roles->role_names as $id => $name)
	{
		register_setting('tfa_user_roles_group', 'tfa_'.$id);
	}
	
	register_setting('tfa_default_hmac_group', 'tfa_default_hmac');
	register_setting('tfa_xmlrpc_status_group', 'tfa_xmlrpc_on');
	register_setting('tfa_email_group', 'tfa_email_group');
}


function tfaListDeliveryRadios($user_id)
{
	if(!$user_id)
		return;
	
			
	$types = array('email' => __('Email', TFA_TEXT_DOMAIN), 'third-party-apps' => __('Third party apps', TFA_TEXT_DOMAIN).' (Duo Mobile, Google Authenticator etc)'); 
	
	$setting = get_user_meta($user_id, 'tfa_delivery_type', true);
	$setting = $setting === false || !$setting ? 'email' : $setting;
		
	foreach($types as $id => $name)
		print '<input type="radio" name="tfa_delivery_type" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> - '.$name."<br>\n";
	
}

function tfaListAlgorithmRadios($user_id)
{
	if(!$user_id)
		return;
	
			
	$types = array('totp' => __('TOTP (time based)', TFA_TEXT_DOMAIN), 'hotp' => __('HOTP (event based)', TFA_TEXT_DOMAIN)); 
	
	$setting = get_user_meta($user_id, 'tfa_algorithm_type', true);
	$setting = $setting === false || !$setting ? 'totp' : $setting;

	foreach($types as $id => $name)
		print '<input type="radio" name="tfa_algorithm_type" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> - '.$name."<br>\n";
}

function tfaListUserRolesCheckboxes()
{
	global $wp_roles;
	if (!isset($wp_roles))
		$wp_roles = new WP_Roles();
	
	foreach($wp_roles->role_names as $id => $name)
	{	
		$setting = get_option('tfa_'.$id);
		$setting = $setting === false || $setting ? 1 : 0;
		
		print '<input type="checkbox" name="tfa_'.$id.'" value="1" '.($setting ? 'checked="checked"' :'').'> '.$name."<br>\n";
	}
	
}

function tfaListEmailSettings() 
{
	$setting = get_option('tfa_email_group');

	print __('Email Address From', TFA_TEXT_DOMAIN).': <input type="text" name="tfa_email_group[email_address]" value="'.$setting['email_address'].'"><br>';
	print __('Email Address From Name', TFA_TEXT_DOMAIN).': <input type="text" name="tfa_email_group[email_name]" value="'.$setting['email_name'].'"><br>';

}

function tfaListDefaultHMACRadios()
{
	$tfa = getTFAClass();
	$setting = get_option('tfa_default_hmac');
	$setting = $setting === false || !$setting ? $tfa->default_hmac : $setting;
	
	$types = array('totp' => __('TOTP (time based)', TFA_TEXT_DOMAIN), 'hotp' => __('HOTP (event based)', TFA_TEXT_DOMAIN));
	
	foreach($types as $id => $name)
		print '<input type="radio" name="tfa_default_hmac" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> - '.$name."<br>\n";
}


function tfaListXMLRPCStatusRadios()
{
	$tfa = getTFAClass();
	$setting = get_option('tfa_xmlrpc_on');
	$setting = $setting === false || !$setting ? 0 : 1;
	
	$types = array('0' => __('OFF', TFA_TEXT_DOMAIN), '1' => __('ON', TFA_TEXT_DOMAIN));
	
	foreach($types as $id => $name)
		print '<input type="radio" name="tfa_xmlrpc_on" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> - '.$name."<br>\n";
}


function tfaShowAdminSettingsPage()
{
	$tfa = getTFAClass();
	global $wp_roles;
	include TFA_MAIN_PLUGIN_PATH.'/admin_settings.php';
}

function tfaShowUserSettingsPage()
{
	$tfa = getTFAClass();
	global $current_user;
	include TFA_MAIN_PLUGIN_PATH.'/user_settings.php';
}


function tfaAddUserSettingsMenu() 
{
	global $current_user;
	$tfa = getTFAClass();
	
	if(!$tfa->isActivatedForUser($current_user->ID))
		return;
	
	add_menu_page('Two Factor Auth', 'Two Factor Auth', 'read', 'two-factor-auth-user', 'tfaShowUserSettingsPage', plugin_dir_url(__FILE__).'img/tfa_admin_icon_16x16.png', 72);
}
add_action('admin_menu', 'tfaAddUserSettingsMenu');

function addTwoFactorAuthAdminMenu()
{
	add_action( 'admin_init', 'tfaRegisterTwoFactorAuthSettings' );
	add_options_page('Two Factor Auth', 'Two Factor Auth', 'manage_options', 'two-factor-auth', 'tfaShowAdminSettingsPage');
}

function addPluginSettingsLink($links)
{
	
	$link = '<a href="options-general.php?page=two-factor-auth">'.__('Settings', TFA_TEXT_DOMAIN).'</a>';
	array_unshift($links, $link);
	return $links;
}

function tfaSaveSettings()
{
	global $current_user;
	if(!empty($_GET['tfa_change_to_email']) && !empty($_GET['tfa_user_id']))
	{
		$tfa = getTFAClass();
		
		if(is_admin())
			$tfa->changeUserDeliveryTypeTo($_GET['tfa_user_id'], 'email');
	
		$goto = site_url().remove_query_arg(array('tfa_user_id', 'tfa_change_to_email'));
		wp_safe_redirect($goto);
		exit;
	}
	
	if(!empty($_GET['tfa_priv_key_reset']))
	{
		delete_user_meta($current_user->ID, 'tfa_priv_key_64');
		delete_user_meta($current_user->ID, 'tfa_panic_codes_64');
		wp_safe_redirect(site_url().remove_query_arg('tfa_priv_key_reset'));
		exit;
	}
}

function tfaAddJSToLogin()
{
	if(isset($_GET['action']) && $_GET['action'] != 'logout' && $_GET['action'] != 'login')
		return;
	
	wp_enqueue_script( 'tfa-ajax-request', plugin_dir_url( __FILE__ ) . 'tfa.min.js', array(), '4.5', true );
	wp_localize_script( 'tfa-ajax-request', 'tfaSettings', array(
		'ajaxurl' => admin_url('admin-ajax.php')
	));
}
add_action('login_enqueue_scripts', 'tfaAddJSToLogin');

function tfaShowHOTPOffSyncMessage()
{
	global $current_user;
	$is_off_sync = get_user_meta($current_user->ID, 'tfa_hotp_off_sync', true);
	if(!$is_off_sync)
		return;
	
	?>
	<div class="error">
    	<h3>Two Factor Auth re-sync needed</h3>
        <p>
        	You need to resync your mobile app for <strong>Two Factor Auth</strong> since the OTP you last used is many steps ahead 
        	of the server.
        	<br>
        	Please re-sync or you might not be able to log in if you generate more OTP:s without logging in.
        	<br><br>
        	<a href="admin.php?page=two-factor-auth-user&warning_button_clicked=1" class="button">Click here and re-scan the QR-Code</a>
        </p>
    </div>
	
	<?php
	
}
//Show off sync message for hotp
add_action('admin_notices', 'tfaShowHOTPOffSyncMessage');

	
if(is_admin())
{
	//Save settings
	add_action('admin_init', 'tfaSaveSettings');
	
	//Add to Settings menu
	add_action('admin_menu', 'addTwoFactorAuthAdminMenu');
	
	//Add settings link in plugin list
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_".$plugin, 'addPluginSettingsLink' );
}

function installTFA()
{
	$error = false;
	if (version_compare(PHP_VERSION, '5.4', '<' ))
	{
		$error = true;
		$flag = 'PHP version 5.4 or higher.';
	}
	elseif(!function_exists('openssl_encrypt'))
	{
		$error = true;
		$flag = 'OpenSSL extension. See <a href="http://www.php.net/manual/en/openssl.installation.php" target="_blank">PHP.net &gt;&gt;</a> for more info.';
	}
	
	if($error)
	{
		deactivate_plugins( basename( __FILE__ ) );
		die('<p>The <strong>Two Factor Auth</strong> plugin requires '.$flag.'</p>');
	}
	
	$tfa = getTFAClass();
	$tfa->upgrade();
}
register_activation_hook(__FILE__, 'installTFA');


function tfaSetLanguages()
{
	load_plugin_textdomain(TFA_TEXT_DOMAIN, false, substr(__DIR__, strlen(\WP_PLUGIN_DIR) + 1) . '/languages/');
}

add_action('init', 'tfaSetLanguages');

?>