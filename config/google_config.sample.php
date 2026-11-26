<?php
// =========================================================================
// Google Sign-In credentials.
// 1. Copy this file to config/google_config.php ON THE SERVER (do not commit it).
// 2. Replace the three values below with your real ones from Google Cloud Console.
// =========================================================================
define('GOOGLE_CLIENT_ID', 'PASTE_YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'PASTE_YOUR_CLIENT_SECRET');
// Must match EXACTLY one of the "Authorized redirect URIs" set in Google Cloud Console.
define('GOOGLE_REDIRECT_URI', 'https://YOUR-DOMAIN/google_callback.php');
