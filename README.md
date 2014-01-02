Habari Two Factor Authentication Plugin
=======================================

This plugin gives your Habari blog two-factor authentication using the Google Authenticator app for Android/iPhone/Blackberry, or any other application that uses the same implementation.


Installation:
-------------

1.  Download the plugin and extract into your user/plugins directory
2.  Log into Habari and activate the Two Factor Authentication plugin
3.  Go to the Users configuration and select your user
4.  Check `Enable` to activate Two Factor Authentication for this user
5.  Modify the `Description` if you wish. This is the description you'll see in the Google Authenticator (or similar) app.
6.  A unique `Secret` should be automatically generated.  Feel free to regenerate a new one at any time.
7.  Save your settings.  This is required in order for the QR code to be generated using the correct content.
8.  Once you've save your settings, click `Show/Hide QR code` to view your QR code
9.  Open Google Authenticator (or similar app) on your phone and add a new account.  The easiest option is to scan the QR code, but you can manually enter the account information.  Remember to select "Time based" for the key type if you chose to enter it manually.
10.  That's it. No more steps.


Usage:
------

Once active and configured, using Two Factor Authentication (2FA) with Habari is like using it with any other service.  When a user with Two Factor Authentication enabled on their account logs in, they will need to specify a code generated on their phone in the `Two Factor Authentication Code` field on the login form.

The `Two Factor Authentication Code` is only considered and used for users that have Two Factor Authentication enabled.


Change Log:
-----------

* 1.2
  * Added "Remember me on this computer for 30 days" functionality.
  * Access to previously remembered hosts can also be revoked from the User's preferences page.
* 1.1
  * Implemented a better method of saving the user preferences and at the same time removed duplicate code.
* 1.0
  * Initial release.
