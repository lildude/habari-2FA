<?php
class GoogleAuthenticator extends Plugin
{
	/**
     * Add custom Javascript to "User" page - we don't need to load it on others, so don't.
     *
     * @access public
     * @param object $theme
     * @return void
     */
    public function action_admin_header( $theme )
    {
		if ( Controller::get_var('page') == 'user' ) {
            Stack::add( 'admin_header_javascript', URL::get_from_filesystem( __FILE__ ) . '/lib/admin.js', 'ga-admin', 'jquery' );
		}
    }
	
	/**
	 * Add Google Authenticator fields to the User form
	 * 
	 * @access public
	 * @param object $form
	 * @param object $user
	 * @return void
	 */
	public function action_form_user( $form, $user )
	{
		$ga = $form->append( 'wrapper', 'google_authenticator', 'Google Authenticator' );
		$ga->class = 'container settings';
		
		$ga->append( 'static', 'google_authenticator', '<h2>' . _t( 'Google Authenticator' ) . '</h2>' );
		
		$ga->append( 'checkbox', 'active', 'user:ga_active', _t( 'Enable' ), 'optionscontrol_checkbox' );
		$ga->active->class[] = 'important item clear';
		$ga->active->value = ( $user->info->ga_active ) ?  $user->info->ga_active : 0;
		
		$ga->append( 'checkbox', 'relaxed_mode', 'user:ga_relaxed_mode', _t( 'Relaxed Mode' ), 'optionscontrol_checkbox' );
		$ga->relaxed_mode->class[] = 'important item clear';
		$ga->relaxed_mode->helptext = _t( 'Relaxed mode allows for more time drifting on your phone clock (±4 min) ');
		$ga->relaxed_mode->value = ( $user->info->ga_relaxed_mode ) ?  $user->info->ga_relaxed_mode : 0;
		
		$ga->append( 'text', 'description', 'user:ga_description', _t( 'Description' ), 'optionscontrol_text' );
		$ga->description->class[] = 'important item clear';
		$ga->description->helptext = _t( "Description that you'll see in the Google Authenticator app on your phone." );
		$ga->description->value = ( $user->info->ga_description != '' ) ? $user->info->ga_description : 'Habari Blog: ' . Options::get( 'title' );

		$ga->append( 'text', 'secret', 'user:ga_secret', _t( 'Secret' ), 'optionscontrol_text' );
		$ga->secret->class[] = 'important item clear';
		$ga->secret->helptext = '<input type="button" value="' . _t( 'Create new secret' ) . '" id="create_secret" /> <input type="button" value="' . _t( 'Show/Hide QR code' ) . '" id="show_hide_qr" />';
		$ga->secret->value = ( $user->info->ga_secret ) ? $user->info->ga_secret : self::create_secret();
		
		// Only append the QR code if the form has been saved and we're active.  This ensures we have the relevant info for the QR code. It also saves an unnecessary call to Google
		if ( $user->info->ga_active ) {
			$chl = urlencode( "otpauth://totp/{$user->info->ga_description}?secret={$user->info->ga_secret}" );
			$qr_url = "https://chart.googleapis.com/chart?cht=qr&amp;chs=300x300&amp;chld=H|0&amp;chl={$chl}";
			$ga->append( 'static', 'qr_code', '<div class="formcontrol important item clear" id="qr_code" style="display: none"><span class="pct25">&nbsp;</span><span class="pct65"><img src="' . $qr_url . '"/><p>' . _t( 'Scan this with the Google Authenticator app.' ) . '</p></span></div>' );
		} else {
			$ga->append( 'static', 'qr_code', '<div class="formcontrol important item clear" id="qr_code" style="display: none"><span class="pct25">&nbsp;</span><span class="pct65"><p>' . _t( 'Please check "Enable" above and save this form to view the QR code.' ) . '</p></span></div>' );
		}

		$form->move_after( $ga, $form->user_info );
	}
	
	/**
	 * Add Google Authenticator field to the login form
	 *
	 * We only use this if the account has Google Authenticator enabled
	 * 
	 * @access public
	 * @return void
	 */
	public function action_theme_loginform_controls()
	{
		echo '<label for="google_authenticator" class="incontent abovecontent">' . _t( 'Google Authenticator Code' ) . '</label>
			  <input type="text" name="otp" id="otp" placeholder="' . _t( 'google authenticator code' ) . '" class="styledformelement" autocomplete="off" title="' . _t( 'If you don\'t have Google Authenticator enabled for your user account, leave this field empty.' ) . '">';
	}
	
	/**
	 * Verify Google Authenticator code provided by user.
	 * 
	 * This authentication happens before Habari authenticates the username and password
	 * 
	 * @access public
	 * @param object $user
	 * @param string $username
	 * @return boolean|object Returns false on failure or an empty StdClass() object on success
	 */
	public function filter_user_authenticate( $user, $username )
	{
		// Get the user object
		$user = User::get_by_name( $username );
		
		// Is GA active for this user?
		if ( $user->info->ga_active == 1 ) {
			$secret = trim( $user->info->ga_secret );
			$relaxed = $user->info->ga_relaxed_mode;
			$otp = intval( trim( $_POST['otp'] ) );
			if ( ! self::verify( $secret, $otp, $relaxed ) ) {
				EventLog::log( _t( 'Invalid or expired Google Authenticator code' ), 'warning', 'authentication', 'habari' );
				Session::error( _t( 'Invalid/Expired Google Authenticator Code' ) );
				return false;
			}
		}
		return new StdClass(); // By default return an empty object so we fall through to the password authentication.
	}
	
	/*-----:[ HELPER FUNCTIONS ]:----- */
	
	/**
	 * Verify the Google Authenticator code submitted.
	 * 
	 * If the user has relaxed mode enabled, we allow 4 mins of leeway either side
	 * to allow for some wider time drift.  Normal leeway is 30 seconds either side
	 * 
	 * @access private
	 * @param string $secret
	 * @param string $otp
	 * @param boolean $relaxed
	 * @return boolean 
	 */
	private static function verify( $secret, $otp, $relaxed )
	{
		require_once( 'lib/base32.php' );
		if ( $relaxed ) {
			$start = -8;
			$end = 8; 
		} else {
			$start = -1;
			$end = 1; 	
		}

		$tm = floor( time() / 30 );

		$secret = Base32::decode( $secret );
		// Keys from before and after are valid too.
		for ( $i = $start; $i <= $end; $i++ ) {
			// Pack time into binary string
			$time = chr(0) . chr(0) . chr(0) . chr(0) . pack( 'N*', $tm+$i );
			// Hash it with users secret key
			$hm = hash_hmac( 'SHA1', $time, $secret, true );
			// Use last nibble of result as index/offset
			$offset = ord( substr( $hm, -1) ) & 0x0F;
			// grab 4 bytes of the result
			$hashpart = substr( $hm, $offset, 4 );
			// Unpack binary value
			$value = unpack( "N", $hashpart );
			$value = $value[1];
			// Only 32 bits
			$value = $value & 0x7FFFFFFF;
			$value = $value % 1000000;
			if ( $value == $otp ) {
				return true;
			}	
		}
		return false;
	}
	
	/**
	 * Generate a random 16 character secret.
	 * 
	 * @access private
	 * @return string $secret A 16 character secret, randomly chosen from the allowed Base32 characters
	 */
	private static function create_secret() 
	{
		$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // allowed characters in Base32
		$secret = '';
		for ( $i = 0; $i < 16; $i++ ) {
			$secret .= substr( $chars, rand( 0, strlen( $chars ) - 1 ), 1 );
		}
		return $secret;
	}
}
?>