<?php
/*
  Plugin Name: Membership Registration
  Plugin URI: http://honeystreet.com
  Description: Adds registration functionality. Extends the User Registration Add-on of Gravity Forms. Both Gravity Forms and it's User Registration Add-on are required plugins.
  Version: 1.0
  Author: Krissie VandeNoord, Honeystreet Design Studio
  Author URI: http://honeystreet.com
 */
 
 /*
  *
  * Login with email instead of username
  *
 */

remove_filter('authenticate', 'wp_authenticate_username_password', 20);

add_filter('authenticate', function($user, $email, $password){

    //Check for empty fields
    if(empty($email) || empty ($password)){        
        //create new error object and add errors to it.
        $error = new WP_Error();

        if(empty($email)){ //No email
            $error->add('empty_username', __('<strong>ERROR</strong>: Email field is empty.'));
        }
        else if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ //Invalid Email
            $error->add('invalid_username', __('<strong>ERROR</strong>: Email is invalid.'));
        }

        if(empty($password)){ //No password
            $error->add('empty_password', __('<strong>ERROR</strong>: Password field is empty.'));
        }

        return $error;
    }

    //Check if user exists in WordPress database
    $user = get_user_by('email', $email);

    //bad email
    if(!$user){
        $error = new WP_Error();
        $error->add('invalid', __('<strong>ERROR</strong>: Either the email or password you entered is invalid.'));
        return $error;
    }
    else{ //check password
        if(!wp_check_password($password, $user->user_pass, $user->ID)){ //bad password
            $error = new WP_Error();
            $error->add('invalid', __('<strong>ERROR</strong>: Either the email or password you entered is invalid.'));
            return $error;
        }else{
            return $user; //passed
        }
    }
}, 20, 3);

add_filter('gettext', function($text){
    if(in_array($GLOBALS['pagenow'], array('wp-login.php'))){
        if('Username' == $text){
            return 'Email';
        }
    }
    return $text;
}, 20);

/* 
 *
 * Reset Password on Front End with Gravity Forms
 *	
*/

// remember, the hook suffix, should contain the form id!
add_filter( "gform_field_validation_3", 'wp_doin_validation_3', 10, 4 );

/**
 * Let's verify for the user email or username provided
 *
 * @return ARRAY_A
 */
function wp_doin_validation_3($result, $value, $form, $field) {

	$classes = explode( ' ', $field['cssClass'] );

	// let's assume it's all valid
	$result['is_valid'] = true;

	// lets check if the user with such a username is already in the database
	if ( in_array( 'user-email', $classes ) ) {

		// this means that the user has specified email
		if ( strpos( $value, '@' ) ) {
			$user_data = get_user_by( 'email', trim( $value ) );

			if ( empty( $user_data ) ) {
				$result['is_valid'] = false;
				$result['message'] = 'No such email in database.';
			}

			$allow = check_if_reset_is_allowed( $user_data->ID );
		} else {
			// let's verify the username existence
			$user_id = username_exists( $value );

			if ( !$user_id ) {
				// let's mark this field is invalid
				$result['is_valid'] = false;
				$result['message'] = 'No such user in database.';
			}

			$allow = check_if_reset_is_allowed( $user_id );
		}
	}

	// if the password change is not allowed return false
	if ( !$allow ) {
		// let's mark this field is invalid
		$result['is_valid'] = false;
		$result['message'] = 'Password change is not allowed.';
	}

	return $result;
}


/**
 * Utility to check if password reset is allowed based on user id.
 * 
 * @param INT $user_id
 * @return BOOL true / false
 */
function check_if_reset_is_allowed($user_id) {
	$allow = apply_filters( 'allow_password_reset', true, $user_id );

	if ( !$allow ) {
		return false;
	} elseif ( is_wp_error( $allow ) ) {
		return false;
	}

	return true;
}



/**
 * Reset Password email notificaiton
 * @param type $form
 * @return type
 */
add_action( "gform_pre_submission_3", "wp_doin_pre_submission_3" );

function wp_doin_pre_submission_3($form) {
	global $wpdb, $wp_hasher;

	// get the submitted value
	$email_or_username = $_POST['input_1'];

	// let's check if the user has provided email or username
	if ( strpos( $email_or_username, '@' ) ) {
		$email = sanitize_email( $email_or_username );
		$user_data = get_user_by( 'email', $email );
	} else {
		$username = esc_attr( $email_or_username );
		$user_data = get_user_by( 'login', $username );
	}

	// Redefining user_login ensures we return the right case in the email.
	$user_login = $user_data->user_login;
	$user_email = $user_data->user_email;

	$key = wp_generate_password( 20, false );

	// Now insert the key, hashed, into the DB.
	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true );
	}

	// obtain new hashed password
	$hashed = $wp_hasher->HashPassword( $key );

	// update user with new activation key
	$wpdb->update( $wpdb->users, array( 'user_activation_key' => time().":".$hashed ), array( 'user_login' => $user_login ) );

	// construct the email message for the user
	$message = __( 'Someone requested that the password be reset for the following account:' ) . "\r\n\r\n";
	$message .= network_home_url( '/' ) . "\r\n\r\n";
	$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
	$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
	$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
	$message .= '<' . network_site_url( "/reset-password/?action=rp&method=gf&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";

	if ( is_multisite() ) {
		$blogname = $GLOBALS['current_site']->site_name;
	} else {
		/*
		 * The blogname option is escaped with esc_html on the way into the database
		 * in sanitize_option we want to reverse this for the plain text arena of emails.
		 */
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}
	
	$title = sprintf( __( '[%s] Password Reset' ), $blogname );

	/**
	 * Filter the subject of the password reset email.
	 *
	 * @since 2.8.0
	 *
	 * @param string $title Default email title.
	 */
	$title = apply_filters( 'retrieve_password_title', $title );

	/**
	 * Filter the message body of the password reset mail.
	 *
	 * @since 2.8.0
	 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
	 *
	 * @param string  $message    Default mail message.
	 * @param string  $key        The activation key.
	 * @param string  $user_login The username for the user.
	 * @param WP_User $user_data  WP_User object.
	 */
	$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );

	if ( $message && !wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) )
		wp_die( __( 'The e-mail could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );

	return;
}


/**
 * Check if the user has hit the proper rest password page. The check is identical to that 
 * from wp-login.php, hence extra $_GET['method'] parameter was included to exclude redirects
 * from wp-login.php file on standard password reset method.
 * 
 * @hook wp_head
 */
add_action( 'init', 'wp_doin_verify_user_key', 999 );

function wp_doin_verify_user_key() {

	global $gf_reset_user;

	// analyze wp-login.php for a better understanding of these values
	list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$rp_cookie = 'wp-resetpass-' . COOKIEHASH;

	// lets redirect the user on pass change, so that nobody could spoof his key
	if ( isset( $_GET['key'] ) and isset( $_GET['method'] ) ) {
		if ( $_GET['method'] == 'gf' ) {
			$value = sprintf( '%s:%s', wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
			setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
			wp_safe_redirect( remove_query_arg( array( 'key', 'login', 'method' ) ) );
			exit;
		}
	}

	// lets compare the validation cookie with the hash key stored with the database data
	// if they match user data will be returned
	if ( isset( $_COOKIE[$rp_cookie] ) && 0 < strpos( $_COOKIE[$rp_cookie], ':' ) ) {
		list( $rp_login, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[$rp_cookie] ), 2 );
		$user = check_password_reset_key( $rp_key, $rp_login );
		if ( isset( $_POST['pass1'] ) && !hash_equals( $rp_key, $_POST['rp_key'] ) ) {
			$user = false;
		}
	} else {
		$user = false;
	}

	// if any error occured make sure to remove the validation cookie
	if ( !$user || is_wp_error( $user ) ) {
		setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
	}

	// make sure our user is available for later reference
	$gf_reset_user = $user;
}


/**
 * Shortcode which is used to cover Gravity Forms shortcode. It will not render the password 
 * reset form in case of invalid pass.
 * 
 * @shortcode verify user pass
 */
add_shortcode( 'verify_user_pass', 'wp_doin_verify_user_pass' ); 
 
function wp_doin_verify_user_pass($args, $content = null) {

	// lets make usage of the custom global variable to fetch
	// the values from the safe / cookie validation functions
	global $gf_reset_user;

	// start output buffering for a more visually appealing output
	ob_start();

	if ( !$gf_reset_user || is_wp_error( $gf_reset_user ) ) {
		if ( $gf_reset_user && $gf_reset_user->get_error_code() === 'expired_key' )
			echo '<h2 class="error">The key has expired.</h2>';
		else
			echo '<h2 class="error">The key is invalid. <a href="/forgot-password">Forgot password?</a></h2>';
	} else {
		echo do_shortcode( $content );
	}

	return ob_get_clean();
}

 /*
  *
  * reset password form presubmission
  *
 */
add_action( "gform_pre_submission_4", "wp_doin_pre_submission_4" );

function wp_doin_pre_submission_4($form) {

	// we'll need the data created before to update the correct user
	global $gf_reset_user;

	list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$rp_cookie = 'wp-resetpass-' . COOKIEHASH;
	
	// get the old and new pass values
	$pass = $_POST['input_3'];
	

	// if we're doing a cron job let's forget about it
	if ( defined( 'DOING_CRON' ) || isset( $_GET['doing_wp_cron'] ) )
		return;

	// let's check if a user with given name exists
	// we're already doing that in the form validation, but this gives us another bridge of safety
	$user_id = username_exists( $gf_reset_user->ID );

	// let's validate the email and the user
	if ( !$user_id ) {

		// let's add another safety check to make sure that the passwords remain unchanged
		if ( !empty( $pass ) ) {
			reset_password( $gf_reset_user, $pass );
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
			wp_logout();
		}
	} else {
		// validation failed
		return;
	}
}


/* 
 *
 * Change default Login/Lost Password/Registration pages and remove Toolbar except for Admins
 *	Custom Login Error Message
*/

class WPSE29338_Admin {
    public static function setup() {
        add_filter('login_url', array(__CLASS__, 'modifyLoginURL'), 10, 2);
        add_filter('lostpassword_url', array(__CLASS__, 'modifyLostPasswordURL'), 10, 2);
        add_filter('register', array(__CLASS__, 'modifyRegisterURL'));
    }

    public static function modifyLoginURL($loginUrl, $redirect = '') {
        $loginUrl = site_url('/login/'); // Link to login URL

        if(!empty($redirect)) {
            $loginUrl = add_query_arg('redirect_to', urlencode($redirect), $loginUrl);
        }

        return $loginUrl;
    }

    public static function modifyLostPasswordURL($lostpwUrl, $redirect = '') {
        $lostpwUrl = site_url('/forgot-password/'); // Link to lostpassword URL

        if(!empty($redirect)) {
            $lostpwUrl = add_query_arg('redirect_to', urlencode($redirect), $lostpwUrl);
        }

        return $lostpwUrl;
    }

    public static function modifyRegisterURL($registerUrl) {
        if(!is_user_logged_in()) {
            if (get_option('users_can_register')) {
                $registerUrl = '<a href="' . site_url('/register/') . '" class="btn">' . __('Register') . '</a>'; // Link to register URL
            } else {
                $registerUrl = '';
            }
        }

        return $registerUrl;
    }
}
add_action('plugins_loaded', 'wpse29338_admin_init');
function wpse29338_admin_init() {
    WPSE29338_Admin::setup();
    if (!current_user_can('manage_options')) {
		add_filter('show_admin_bar', '__return_false');
	}
}

add_action( 'wp_login_failed', 'hex_front_end_login_fail' ); // hook failed login

function hex_front_end_login_fail( $user ) {
  $referrer = $_SERVER['HTTP_REFERER']; // where did the post submission come from?
  // if there’s a valid referrer, and it’s not the default log-in screen
  if ( !empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') && $user!=null ) {
    if ( !strstr($referrer, '?status=failed' )) { // make sure we don’t append twice
      wp_redirect( $referrer . '?status=failed'); // let’s append some information (login=failed) to the URL for the theme to use
    } else {
      wp_redirect( $referrer );
    }
    exit;
  }
}



/**
* Gravity Forms Custom Activation Template
* http://gravitywiz.com/customizing-gravity-forms-user-registration-activation-page
*/
add_action('wp', 'custom_maybe_activate_user', 9);
function custom_maybe_activate_user() {
	
	$template_path = plugin_dir_path( __FILE__ ) . 'gfur-activate-template/activate.php';
 
    $is_activate_page = isset( $_GET['page'] ) && $_GET['page'] == 'gf_activation';
    
    if( ! $is_activate_page  )
        return;
   
    require_once($template_path);
    
    
    exit();
}

add_action("gform_user_registered", "honeystreet_user_reg_email_confirm", 10, 4);
function honeystreet_user_reg_email_confirm($user_id,$user_config,$entry,$user_pass) {
	$from = 'user@emailaddress.com';
	$to = get_the_author_meta('user_email',$user_id);
	$subject = 'Your account has been created!';
	$body = 'Your account has been created. You may now <a href="http://xxxxx.org/login">login</a> with your email address, and your chosen password: ' . $user_pass;
	// Send Email
    wp_mail( 
	    // Send it to new user
	    $to, 
	    $subject, 
	    $body,
	    array (
	        'From:' . $from,
	        'Content-type: text/html'
	    ) 
	);

};