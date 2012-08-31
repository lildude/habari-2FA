Google Authenticator For Habari
===============================

The Google Authenticator plugin for Habari gives you two-factor authentication using the Google Authenticator app for Android/iPhone/Blackberry.


Installation:
-------------

1.  Download the plugin and extract into your user/plugins directory
2.  Log into Habari and activate the Google Authenticator plugin
3.  Go to the Users configuration and select your user
4.  Check `Enable` to activate Google Authenticator for this user
5.  Modify the `Description` if you wish. This is the description you'll see in the Google Authenticator App.
6.  A unique `Secret` should be automatically generated.  Feel free to regenerate a new one at any time.
7.  Save your settings.  This is required in order for the QR code to be generated using the correct content.
8.  Once you've save your settings, click `Show/Hide QR code` to view your QR code
9.  Open Google Authenticator on your phone and add a new account.  The easiest option is to scan the QR code, but you can manually enter the account information.  Remember to select "Time based" for the key type if you chose to enter it manually.
10.  That's it. No more steps.


Usage:
------

Once active and configured, using Google Authenticator with Habari is like using it with any other service.  When a user with "Google Authenticator" enabled on their account logs in, they will need to specify a Google Authenticator code generated on their phone in the `Google Authenticator Code` field on the login form.

The `Google Authenticator Code` is only considered and used for users that have Google Authenticator enabled.
