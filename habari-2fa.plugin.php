<?php
class Habari2FA extends Plugin
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
			Stack::add( 'admin_header_javascript', URL::get_from_filesystem( __FILE__ ) . '/lib/admin.js', 'h2fa-admin', 'jquery' );
		}
    }

	/**
	 * Add 2FA fields to the User form
	 *
	 * @access public
	 * @param object $form
	 * @param object $user
	 * @return void
	 */
	public function action_form_user( $form, $user )
	{
		$this->add_template( 'h2fa_textmulti', dirname( $this->get_file() ) . '/lib/textmulti.php' );
		$h2fa = $form->append( 'wrapper', 'two_factor', 'Two Factor Authentication' );
		$h2fa->class = 'container settings';

		$h2fa->append( 'static', 'two_factor', '<h2>' . _t( 'Two Factor Authentication' ) . '</h2>' );

		$h2fa->append( 'checkbox', 'h2fa_active', $user, _t( 'Enable' ), 'optionscontrol_checkbox' );
		$h2fa->h2fa_active->class[] = 'important item clear';

		$h2fa->append( 'checkbox', 'h2fa_relaxed_mode', $user, _t( 'Relaxed Mode' ), 'optionscontrol_checkbox' );
		$h2fa->h2fa_relaxed_mode->class[] = 'important item clear';
		$h2fa->h2fa_relaxed_mode->helptext = _t( 'Relaxed mode allows for more time drifting on your phone clock (±4 min) ');

		$h2fa->append( 'text', 'h2fa_description', $user, _t( 'Description' ), 'optionscontrol_text' );
		$h2fa->h2fa_description->class[] = 'important item clear';
		$h2fa->h2fa_description->helptext = _t( "Description that you'll see in the Google Authenticator (or similar) app on your phone." );
		$h2fa->h2fa_description->value = ( $user->info->h2fa_description != '' ) ? $user->info->h2fa_description : 'Habari Blog: ' . Options::get( 'title' );

		$h2fa->append( 'text', 'h2fa_secret', $user, _t( 'Secret' ), 'optionscontrol_text' );
		$h2fa->h2fa_secret->class[] = 'important item clear';
		$h2fa->h2fa_secret->readonly = 'readonly';
		$h2fa->h2fa_secret->helptext = '<input type="button" value="' . _t( 'Create new secret' ) . '" id="create_secret" /> <input type="button" value="' . _t( 'Show/Hide QR code' ) . '" id="show_hide_qr" />';

		$h2fa->append( 'textmulti', 'h2fa_remember', $user, _t( 'Remembered hosts'), 'h2fa_textmulti' );
		$h2fa->h2fa_remember->class[] = 'important item clear';
		$h2fa->h2fa_remember->helptext = _t( 'Remove entries to revoke access.' );

		// Only append the QR code if the form has been saved and we're active.  This ensures we have the relevant info for the QR code. It also saves an unnecessary call to Google
		if ( $user->info->h2fa_active ) {
			$chl = urlencode( "otpauth://totp/{$user->info->h2fa_description}?secret={$user->info->h2fa_secret}" );
			$qr_url = "https://chart.googleapis.com/chart?cht=qr&amp;chs=300x300&amp;chld=H|0&amp;chl={$chl}";
			$h2fa->append( 'static', 'qr_code', '<div class="formcontrol important item clear" id="qr_code" style="display: none"><span class="pct25">&nbsp;</span><span class="pct65"><img src="' . $qr_url . '"/><p>' . _t( 'Scan this with the Google Authenticator (or similar) app.' ) . '</p></span></div>' );
		} else {
			$h2fa->append( 'static', 'qr_code', '<div class="formcontrol important item clear" id="qr_code" style="display: none"><span class="pct25">&nbsp;</span><span class="pct65"><p>' . _t( 'Please check "Enable" above and save this form to view the QR code.' ) . '</p></span></div>' );
		}

		$form->move_after( $h2fa, $form->user_info );
	}

	/**
	 * Add Two Factor Authentication field to the login form
	 *
	 * We only use this if the account has Two Factor Authentication enabled
	 *
	 * @access public
	 * @return void
	 */
	public function action_theme_loginform_controls()
	{
		if ( ! isset( $_COOKIE['h2fa_remember'] ) ) {
			echo '<p><label for="h2fa_authenticator" class="incontent abovecontent">' . _t( 'Two Factor Authentication Code' ) . '</label>
			  <input type="text" name="otp" id="otp" placeholder="' . _t( 'Two factor authentication code' ) . '" class="styledformelement" autocomplete="off" title="' . _t( 'If you don\'t have Two Factor Authentication enabled for your user account, leave this field empty.' ) . '">
			  </p>
			  <p>'. _t( 'Remember Two Factor Authentication code for 30 days on this computer' ). '
			  <input type="checkbox" name="rem_h2fa_30days" id="rem_h2fa_30days" onclick="jQuery(\'#h2fa_name\').toggle();"></p>
			  <p><input style="display:none" type="text" name="h2fa_name" id="h2fa_name" placeholder="' . _t( 'Computer name' ) . '" class="styledformelement" autocomplete="off" title="' . _t( 'A unique name for this computer.' ) . '">
			  </p>';
		}
	}

	/**
	 * Verify 2FA code provided by user.
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
		if ( $user->info->h2fa_active == 1 ) {
			$secret = trim( $user->info->h2fa_secret );
			// If we're remembered, check the contents of the cookie. It should be a salted hash of the secret
			if ( isset( $_COOKIE['h2fa_remember'] ) ) {
				// Grab the current list of remembered hosts
				list( $h2fa_rem_user, $host, $token ) = explode( ':', $_COOKIE['h2fa_remember'] );
				$remembered = $user->info->h2fa_remember;
				if ( $username == $h2fa_rem_user && isset( $remembered[$host] ) && $remembered[$host] == $token  ) {
					return new StdClass();
				}
				else {
					// unset the cookie as it's probably bogus and bounce back to the login page
					setcookie( 'h2fa_remember', '', 1 );
					Utils::redirect();
				}
			}
			$relaxed = $user->info->h2fa_relaxed_mode;
			$otp = intval( trim( $_POST['otp'] ) );
			if ( ! self::verify( $secret, $otp, $relaxed ) ) {
				EventLog::log( _t( 'Invalid or expired Two Factor Authentication code' ), 'warning', 'authentication', 'habari' );
				Session::error( _t( 'Invalid/Expired Two Factor Authentication Code' ) );
				return false;
			}
			// Set the 30-day cookie if we've been asked to

			if ( isset( $_POST['rem_h2fa_30days'] ) ) {
				$token = md5( $_POST['h2fa_name'] . $secret . time() );
				// Cookie is username:host:token
				setcookie( 'h2fa_remember', "{$username}:{$_POST['h2fa_name']}:{$token}", time()+2592000 );
				$remembered = $user->info->h2fa_remember;
				// Save the cookie value to the DB for verification later
				// h2fa_remember is an array of machine => md5( $name . $secret );
				$remembered[$_POST['h2fa_name']] = $token;
				$user->info->h2fa_remember = $remembered;
				$user->update();
			}
		}
		return new StdClass(); // By default return an empty object so we fall through to the password authentication.
	}

	/*-----:[ HELPER FUNCTIONS ]:----- */

	/**
	 * Verify the 2FA code submitted.
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
}
?>
