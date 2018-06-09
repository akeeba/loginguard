# 3.0.1

**New**

* Users page to see whihc users have 2SV enabled or not

# 3.0.0

**Other changes**

* Fully refactored to make better use of FOF 3 features
* Automatic encryption of TFA settings for GDPR compliance (only affects TFA methods which are newly created or used after installing this version)

**Removed features**

* IP addresses and User Agents are no longer collected for GDPR compliance.

**Bug fixes**

* The Log Out button results in an invalid token error (h/t Ivaylo V. for the heads up)

# 2.0.1

**Other changes**

* Using the built-in FOF page renderer instead of a custom one

**Bug fixes**

* Cannot update the GeoIP database from inside the component
* TOTP (Google Authenticator) plugin does not display the QR code due to missing JS file
* Leftover records after the user is deleted from Joomla

# 2.0.0

**New**

* Rewritten interface using the FOF framework
* Rewritten interface using our own CSS framework
* Preliminary Joomla! 4 compatibility (tested against 4.0.0 Alpha 2)
* Minimum requirements increased to PHP 5.4 or later. Tested up to and including PHP 7.2.
* Warn the user if either FOF or FEF is not installed.

**Bug fixes**

* PHP Notice when the user does not have any backup codes (it can only happen if you tamper with the database).
* Google's QR code API was deprecated, breaking the TOTP authentication plugin (gh-38)

# 1.2.1

**Other changes**

* Make the intent of Backup Codes more obvious
* Auto-focus the two step verification field
* Do not escape the LoginGuard method title (allows for title formatting, e.g. with the backup codes method) 

**Bug fixes**

* The emergency backup codes could be reused

# 1.2.0

**Other changes**

* Improved static media versioning.
* Security Key (U2F) plugin: start the U2F validation request immediately, without having to press the button on the screen.
* Security Key (U2F) plugin: do not show the confusing Validate button.
* Show TFA status in the Profile status page (before editing).

**Bug fixes**

* Missing file.
* PHP warnings on Joomla! 3.7.0 because Joomla! broke backwards compatibility, again.
* Disabling method batching doesn't display each authentication method separately in the captive page. 
* Backup Codes not shown in the authentication method selection page.
* Workaround for Joomla! Bug 16147 (https://github.com/joomla/joomla-cms/issues/16147) - Cannot access component after installation when cache is enabled

# 1.1.1

**Bug fixes**

* Missing file

# 1.1.0

**New features**

* Send authentication code by email
* Send authentication code by push message (using PushBullet)
* Send authentication code by mobile text message (using SMSAPI.com)
* Don't ask for 2SV when the Remember Me plugin logs you back in

**Bug fixes**

* The query disappears from the URL after authenticating the second factor
* You can see the first time setup page after logging out
* Some browser and server combinations end up with the browser sending double requests to the captive login page making U2F authentication all but impossible.

# 1.0.0

**New features**
* Two Step Verification for the front- and backend of your Joomla! site.
* Verification with Google Authenticator and compatible applications.
* Verification with YubiKey in OTP mode using the Yubico or custom validation servers.
* Verification with U2F hardware keys on Google Chrome (Linux, Windows, macOS, Android), Firefox (Linux, Windows, macOS) and Opera (Linux, Windows, macOS).
* Migrate settings from Joomla's Two Factor Authentication and our legacy Akeeba YubiKey Plugins for Joomla! Two Factor Authentication.
* Optional. Let your users manage their Two Step Verification settings from their user profile edit page.
* Optional. Automatically show a page where your users can set up Two Step Verification if they haven't done already (displays either the default page or a custom article).

**Other changes**
* Use the new U2F API 1.1
* Consistency of Confirm button appearing below the form, even in the backend.
* The plugins now put their data in the media folder, following Joomla's best practices. 

**Bug fixes**
* The submit button wasn't shown on the edit method page when using U2F.
* Try to notify when U2F is not supported by the browser instead of silently failing.
