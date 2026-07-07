<?php

$i18n = [

# Basics
	'lang_Menu_Title'			=>	'Google Login',
	'lang_Page_Title'			=>	'Google Login',
	'lang_Description'			=>	'Adds a "Sign in with Google" option to the admin login page, linked to specific existing user accounts.',

# Tabs
	'lang_Settings_Tab'		=>	'OAuth Settings',
	'lang_Users_Tab'			=>	'User Mapping',

# Settings view
	'lang_Client_ID'			=>	'Google Client ID',
	'lang_Client_Secret'		=>	'Google Client Secret',
	'lang_Enabled'				=>	'Enable "Sign in with Google" on the login page',
	'lang_Redirect_URI_Note'	=>	'Add this exact Authorized redirect URI in your Google Cloud Console OAuth client:',
	'lang_Click_To_Select'		=>	'Click to select',
	'lang_Copy'					=>	'Copy',
	'lang_Copied'				=>	'Copied!',
	'lang_Select_Fallback'		=>	'Selected - press Ctrl/Cmd+C to copy',
	'lang_Save'					=>	'Save',
	'lang_Saved'				=>	'Saved.',

# Help tab
	'lang_Help_Tab'				=>	'Help',
	'lang_Help_Intro'			=>	'Follow these steps in the Google Cloud Console to get a Client ID and Client Secret for the Settings tab.',
	'lang_Help_Console_Link'	=>	'Open Google Cloud Console Credentials',
	'lang_Help_Step1'			=>	'Create or select a project, then go to <strong>APIs &amp; Services &rarr; OAuth consent screen</strong> and set the app name, support email, and authorized domain (this may appear as <strong>Google Auth Platform &rarr; Branding</strong>). "Testing" mode is fine here, since only accounts you link below can ever sign in regardless.',
	'lang_Help_Step2'			=>	'Go to <strong>APIs &amp; Services &rarr; Credentials &rarr; Create Credentials &rarr; OAuth client ID</strong>, and choose <strong>Web application</strong> as the type.',
	'lang_Help_Step3'			=>	'Under <strong>Authorized redirect URIs</strong>, add this exact URI, then click Create:',
	'lang_Help_Step4'			=>	'Copy the Client ID and Client Secret Google shows you into the Settings tab.',
	'lang_Help_Https_Note'		=>	'Google requires HTTPS for this redirect URI on any real domain. A local Laragon/HTTP environment will need its own separate OAuth client using <code>http://localhost</code> for testing.',

# Users view
	'lang_Users_Info'			=>	'Link each GetSimple user account to the Google account they should be allowed to sign in with. Leave blank to disable Google sign-in for that user.',
	'lang_Username'				=>	'GetSimple Username',
	'lang_Google_Email'			=>	'Linked Google Email',
	'lang_No_account_linked'	=>	'Not linked',

# Login page UI
	'lang_Sign_in_button'		=>	'Sign in with Google',
	'lang_Or_divider'			=>	'or sign in with your password',

# Errors shown on the login page
	'lang_Err_not_configured'	=>	'Google sign-in is not configured yet.',
	'lang_Err_denied'			=>	'Google sign-in was cancelled.',
	'lang_Err_unverified'		=>	'Your Google email address is not verified.',
	'lang_Err_no_match'			=>	'No GetSimple account is linked to that Google account.',
	'lang_Err_generic'			=>	'Google sign-in failed. Please try again or use your password.',

];
