<?php
/*
Plugin Name: SMS Media4u Login
Plugin URI: https://www.wpmarket.jp/product/sms_media4u_login/
Description: This plugin adds the functionality to send SMS by use of Media4u in order to login WordPress.
Author: Hiroaki Miyashita
Version: 1.0.1
Author URI: https://www.wpmarket.jp/
Text Domain: sms-media4u-login
Domain Path: /
*/

/*  Copyright 2022 Hiroaki Miyashita

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class sms_media4u_login {

	function __construct() {
		add_action( 'plugins_loaded', array(&$this, 'sms_media4u_login_plugins_loaded') );
		add_action( 'admin_menu', array(&$this, 'sms_media4u_login_admin_menu') );
		add_filter( 'login_redirect', array(&$this, 'sms_media4u_login_login_redirect'), 10, 3 );
		add_action( 'wp_authenticate', array(&$this, 'sms_media4u_login_wp_authenticate'), 10, 2 );
		add_action( 'login_form_sms', array(&$this, 'sms_media4u_login_login_form_sms') );
		add_action( 'admin_init', array(&$this, 'sms_media4u_login_admin_init') );
		add_action( 'show_user_profile', array(&$this, 'sms_media4u_login_user_profile') );
		add_action( 'edit_user_profile', array(&$this, 'sms_media4u_login_user_profile') );
		add_action( 'user_new_form', array(&$this, 'sms_media4u_login_user_profile') );
		add_action( 'profile_update', array(&$this, 'sms_media4u_login_profile_update') );
		add_action( 'user_register', array(&$this, 'sms_media4u_login_profile_update') );
	}
	
	function sms_media4u_login_plugins_loaded() {
		load_plugin_textdomain('sms-media4u-login', false, plugin_basename( dirname( __FILE__ ) ) );
		
		if ( !get_option('sms_media4u_authentication_key') ) :
			add_action( 'admin_notices', array(&$this, 'sms_media4u_login_admin_notices') );	
		endif;
	}
	
	function sms_media4u_login_admin_menu() {
		add_options_page(__('SMS Media4u Login', 'sms-media4u-login'), __('SMS Media4u Login', 'sms-media4u-login'), 'manage_options', 'sms_media4u_login', array(&$this, 'sms_media4u_login_option_page') );
	}
	
	function sms_media4u_login_admin_notices() {
		echo '<div class="error"><p><strong><a href="https://www.wpmarket.jp/product/sms_media4u_login/?domain='.esc_attr($_SERVER['HTTP_HOST']).'" target="_blank">'.__( 'In order to use SMS Media4u Login, you have to purchase the authentication key at the following site.', 'sms-media4u-login' ).'</a></strong></p></div>';
	}
	
	function sms_media4u_login_user_profile( $user ) {
?>
<h2><?php _e('SMS Media4u Login Options', 'sms-media4u-login'); ?></h2>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th><label for="tel"><?php _e('Tel', 'sms-media4u-login'); ?></label></th>
<td><input type="tel" name="tel" id="tel" value="<?php echo esc_attr( get_the_author_meta( 'tel', $user->ID ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th><label for="use_sms_media4u"><?php _e('Use', 'sms-media4u-login'); ?></label></th>
<td><input type="checkbox" name="use_sms_media4u" id="use_sms_media4u" value="1" <?php checked( get_user_meta( $user->ID, 'use_sms_media4u', true ), '1' ); ?> /></td>
</tr>
</tbody>
</table>
<?php
	}
	
	function sms_media4u_login_profile_update( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) ) return false;
		
		$tel = !empty( $_POST['tel'] ) ? sanitize_text_field( $_POST['tel'] ) : '';
		$use_sms_media4u = !empty( $_POST['use_sms_media4u'] ) ? sanitize_text_field( $_POST['use_sms_media4u'] ) : '';
	
		update_user_meta( $user_id, 'tel', $tel );
		update_user_meta( $user_id, 'use_sms_media4u', $use_sms_media4u );
	}

	function sms_media4u_login_login_redirect($redirect_to, $requested_redirect_to, $user) {
		if ( !is_wp_error( $user ) && get_option( 'sms_media4u_username' ) && get_option( 'sms_media4u_password' ) && get_option( 'sms_media4u_authentication_key' ) && !empty($user->tel) && !empty($user->use_sms_media4u) ) :
			$tel = preg_replace('/-/','', $user->tel);
			if ( strlen($tel) == 11 ) :
				$sms = $this->sms_media4u_login_send_sms( $tel );
				$additional_redirect_to = '';
				if ( $requested_redirect_to ) $additional_redirect_to = '&redirect_to=' . $requested_redirect_to;
				$redirect_to = site_url( 'wp-login.php?action=sms' . $additional_redirect_to, 'login_post' );
			endif;
		endif;
		return $redirect_to;
	}

	function sms_media4u_login_wp_authenticate( $username, $password ) {
		if ( isset( $_POST['_wpsmsnonce'] ) || wp_verify_nonce( $_POST['_wpsmsnonce'], 'sms_media4u_login_'.$_POST['user_id'] ) ) :
			if ( !empty($_POST['user_id']) ) :
				$user = new WP_User($_POST['user_id']);
				wp_set_current_user( $user->ID );
				wp_set_auth_cookie( $user->ID );
				$tel = preg_replace('/-/','', $user->tel);
				if ( !empty($_POST['code_resend']) ):
					if ( strlen($tel) == 11 ) :
						$sms = $this->sms_media4u_login_send_sms( $tel );
						$redirect_to = site_url( 'wp-login.php?action=sms', 'login_post' );
						wp_redirect( $redirect_to );
						exit;
					endif;
				endif;
				if ( !empty($tel) ) :
					$code = get_transient( $tel );
					if ( empty($_POST['code']) || $_POST['code'] != $code ) :
						$redirect_to = site_url( 'wp-login.php?action=sms&failed=1', 'login_post' );
						wp_redirect( $redirect_to );
						exit;
					endif;

					if ( empty($redirect_to) ) :
						if ( !empty($_POST['redirect_to']) ) :
							$redirect_to = $_POST['redirect_to'];
						else :
							$redirect_to = admin_url();
						endif;			
					endif;
					wp_safe_redirect( $redirect_to );
					exit;
				endif;
			endif;
		endif;
	}

	function sms_media4u_login_login_form_sms() {
		global $current_user;
		
		$errors = new WP_Error();
		if ( !empty($_REQUEST['failed']) ) :
			$errors->add( 'invalidcode', __( '<strong>Error</strong>: The verification code appears to be invalid. Please input it again.', 'sms-media4u-login' ) );
		endif;
		
		login_header( __( 'SMS Authentication', 'sms-media4u-login' ), '', $errors );
?>
		<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
			<?php if ( isset( $_GET['redirect_to'] ) && '' !== $_GET['redirect_to'] ) { ?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_url_raw( $_GET['redirect_to'] ); ?>" />
			<?php } ?>
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $current_user->ID ); ?>" />
			<?php wp_nonce_field( 'sms_media4u_login_'.$current_user->ID, '_wpsmsnonce', false ); ?>
			<p><?php _e( 'A verification code has been sent to the phone number associated with your account.', 'sms-media4u-login' ); ?></p>
			<p>
				<label for="user_login"><?php _e( 'Code', 'sms-media4u-login' ); ?></label>
				<input type="number" name="code" class="input" value="" size="20" autocapitalize="off" />
			</p>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Log In', 'sms-media4u-login' ); ?>"></p>
			<p>
				<input type="submit" class="button" name="code_resend" value="<?php _e( 'Resend the code', 'sms-media4u-login' ); ?>" />
			</p>
		</form>
<?php
		login_footer();
		
		wp_logout();
		wp_set_current_user(0);
		wp_clear_auth_cookie();
		exit;
	}
	
	function sms_media4u_login_check_authentication_key( $auth_key ) {
		$request = wp_remote_get('https://www.wpmarket.jp/auth/?gateway=media4u&domain='.$_SERVER['HTTP_HOST'].'&auth_key='.$auth_key);
		if ( ! is_wp_error( $request ) && $request['response']['code'] == 200 ) :
			if ( $request['body'] == 1 ) :
				return true;
			else :
				return false;
			endif;
		else :
			return false;
		endif;
	}
	
	function sms_media4u_login_admin_init() {
		if ( !empty($_POST['sms_media4u_login_options_submit']) && !empty($_POST['action']) && $_POST['action'] == 'update' && !empty($_POST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], 'sms_media4u_login' ) ) :
			if ( !empty($_POST['sms_media4u_authentication_key']) ) :
				$check_value = $this->sms_media4u_login_check_authentication_key( $_POST['sms_media4u_authentication_key'] );
				if ( $check_value == false ) :
					$sms_media4u_authentication_key = '';
				else :
					$sms_media4u_authentication_key = sanitize_text_field( $_POST['sms_media4u_authentication_key'] );					
				endif;
			endif;
		
			$sms_media4u_username = !empty( $_POST['sms_media4u_username'] ) ? sanitize_text_field( $_POST['sms_media4u_username'] ) : '';
			$sms_media4u_password = !empty( $_POST['sms_media4u_password'] ) ? sanitize_text_field( $_POST['sms_media4u_password'] ) : '';
		
			update_option( 'sms_media4u_username', $sms_media4u_username );
			update_option( 'sms_media4u_password', $sms_media4u_password );
			update_option( 'sms_media4u_authentication_key', $sms_media4u_authentication_key );
			wp_redirect(get_option('siteurl').'/wp-admin/admin.php?page=sms_media4u_login&message=options_updated');
			exit();
		endif;
	}

	function sms_media4u_login_option_page() {
		if ( !empty($_REQUEST['message']) && $_REQUEST['message'] == 'options_updated' ) :
			add_settings_error( 'sms_media4u_login', esc_attr( 'options_updated' ), __('Options updated.', 'sms-media4u-login'), 'updated' );
		endif;
?>
<div class="wrap">
<h1><?php _e('SMS Media4u Login Options', 'sms-media4u-login'); ?></h1>
<?php settings_errors(); ?>
	
<form method="post">
<input type="hidden" name="action" value="update" />
<?php wp_nonce_field( 'sms_media4u_login', '_wpnonce', true, true ); ?>

<table class="form-table" style="margin-bottom:5px;">
<tbody>
<tr>
<th><label for="username"><?php _e('Username', 'sms-media4u-login'); ?></label></th>
<td><input type="text" name="sms_media4u_username" id="sms_media4u_username" class="regular-text" value="<?php form_option( 'sms_media4u_username' ); ?>" />
<p class="description"><?php _e('Please input the username provided by Media4u.', 'sms-media4u-login'); ?></p></td>
</tr>
<tr>
<th><label for="password"><?php _e('Password', 'sms-media4u-login'); ?></label></th>
<td><input type="password" name="sms_media4u_password" id="sms_media4u_password" class="regular-text" value="<?php form_option( 'sms_media4u_password' ); ?>" />
<p class="description"><?php _e('Please input the password provided by Media4u.', 'sms-media4u-login'); ?></p></td>
</td>
</tr>
<tr>
<th><label for="authentication_key"><?php _e('Authentication Key', 'sms-media4u-login'); ?></label></th>
<td><input type="text" name="sms_media4u_authentication_key" id="sms_media4u_authentication_key" class="regular-text" value="<?php form_option( 'sms_media4u_authentication_key' ); ?>" />
<p class="description"><a href="https://www.wpmarket.jp/product/sms_media4u_login/?domain=<?php echo esc_attr($_SERVER['HTTP_HOST']); ?>" target="_blank"><?php _e( 'In order to use SMS Media4u Login, you have to purchase the authentication key at the following site.', 'sms-media4u-login' ); ?></a></p></td>
</tr>
<tr>
<td>
<p><input type="submit" name="sms_media4u_login_options_submit" value="<?php _e('Update Options &raquo;', 'sms-media4u-login'); ?>" class="button-primary" /></p>
</td></tr>
</tbody>
</table>
</form>
</div>
<?php
	}

	function sms_media4u_login_send_sms( $tel ) {
		$url = 'https://www.sms-ope.com/sms/api/';
		$username = get_option('sms_media4u_username');
		$password = get_option('sms_media4u_password');
		$code = sprintf("%06d", mt_Rand(1, 999999));

		if ( empty($username) || empty($password) ) return false;
		
		$data['smstext'] = sprintf(__('Validation Code is %s.', 'sms-media4u-login'), $code);
		$data['mobilenumber'] = $tel;
		
		$auth = base64_encode( $username . ':' . $password );
		$args = [
			'headers' => [
				'Authorization' => "Basic $auth"
			],
			'body' => $data
		];

		$response = wp_remote_post( $url, $args );
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code == 200 ) :
			set_transient( $tel, $code, 300);
			return true;
		else :
			return false;
		endif;
	}
	
}
$sms_media4u_login = new sms_media4u_login();
?>