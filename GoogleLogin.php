<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
Plugin Name: Google Login
Description: Adds a "Sign in with Google" option to the GetSimple admin login page, linked to specific existing user accounts.
Version: 1.0
Author: risingisland
Author URI: https://ko-fi.com/ericmontgomery
*/

i18n_merge('GoogleLogin') || i18n_merge('GoogleLogin', 'en_US');

// ─── Constants ───────────────────────────────────────────────────────────────
define('GoogleLogin_DB_PATH', GSDATAOTHERPATH . 'GoogleLogin.db');
define('GoogleLogin_SLUG',    basename(__FILE__, '.php'));
define('GoogleLogin_STATE_COOKIE', 'gl_oauth_state');

// ─── Bootstrap ───────────────────────────────────────────────────────────────

register_plugin(
	GoogleLogin_SLUG,
	'Google Login',
	'1.0',
	'risingisland',
	'https://ko-fi.com/ericmontgomery',
	'Adds a "Sign in with Google" option to the admin login page, linked to specific existing user accounts.',
	'settings',
	'GoogleLogin_admin_page'
);

add_action('settings-sidebar', 'createSideMenu', array(
	GoogleLogin_SLUG,
	i18n_r('GoogleLogin/lang_Menu_Title') .' <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="18" height="18" viewBox="0 0 16 16"><path d="M0 0h16v16H0z" fill="none"/><g fill="none" fill-rule="evenodd" clip-rule="evenodd"><path fill="#f44336" d="M7.209 1.061c.725-.081 1.154-.081 1.933 0a6.57 6.57 0 0 1 3.65 1.82a100 100 0 0 0-1.986 1.93q-1.876-1.59-4.188-.734q-1.696.78-2.362 2.528a78 78 0 0 1-2.148-1.658a.26.26 0 0 0-.16-.027q1.683-3.245 5.26-3.86" opacity=".987"/><path fill="#ffc107" d="M1.946 4.92q.085-.013.161.027a78 78 0 0 0 2.148 1.658A7.6 7.6 0 0 0 4.04 7.99q.037.678.215 1.331L2 11.116Q.527 8.038 1.946 4.92" opacity=".997"/><path fill="#448aff" d="M12.685 13.29a26 26 0 0 0-2.202-1.74q1.15-.812 1.396-2.228H8.122V6.713q3.25-.027 6.497.055q.616 3.345-1.423 6.032a7 7 0 0 1-.51.49" opacity=".999"/><path fill="#43a047" d="M4.255 9.322q1.23 3.057 4.51 2.854a3.94 3.94 0 0 0 1.718-.626q1.148.812 2.202 1.74a6.62 6.62 0 0 1-4.027 1.684a6.4 6.4 0 0 1-1.02 0Q3.82 14.524 2 11.116z" opacity=".993"/></g></svg>'
));

// Fires on every page where plugins are loaded, including the admin login
// page, and (critically) fires BEFORE login_functions.php is ever included.
add_action('common', 'GoogleLogin_intercept');

// Fires above the login form on the admin login page.
add_action('index-login', 'GoogleLogin_render_login_ui');

GoogleLogin_init_db();

// ─────────────────────────────────────────────────────────────────────────────
// DATABASE
// ─────────────────────────────────────────────────────────────────────────────

function GoogleLogin_db() {
	static $db = null;
	if ($db === null) {
		$db = new SQLite3(GoogleLogin_DB_PATH);
		$db->busyTimeout(5000);
		$db->exec('PRAGMA journal_mode=WAL;');
	}
	return $db;
}

function GoogleLogin_init_db() {
	GoogleLogin_db()->exec("
		CREATE TABLE IF NOT EXISTS gl_settings (
			key   TEXT PRIMARY KEY,
			value TEXT NOT NULL DEFAULT ''
		);
		CREATE TABLE IF NOT EXISTS gl_users (
			username     TEXT PRIMARY KEY,
			google_email TEXT NOT NULL UNIQUE,
			linked_at    INTEGER NOT NULL DEFAULT (strftime('%s','now'))
		);
	");
}

// ─── Settings helpers (client id / secret / enabled flag) ────────────────────

function GoogleLogin_get_setting($key, $default = '') {
	$st = GoogleLogin_db()->prepare('SELECT value FROM gl_settings WHERE key = :k');
	$st->bindValue(':k', $key, SQLITE3_TEXT);
	$row = $st->execute()->fetchArray(SQLITE3_ASSOC);
	return $row ? $row['value'] : $default;
}

function GoogleLogin_set_setting($key, $value) {
	$st = GoogleLogin_db()->prepare("
		INSERT INTO gl_settings (key, value) VALUES (:k, :v)
		ON CONFLICT(key) DO UPDATE SET value = excluded.value
	");
	$st->bindValue(':k', $key,   SQLITE3_TEXT);
	$st->bindValue(':v', $value, SQLITE3_TEXT);
	$st->execute();
}

function GoogleLogin_is_configured() {
	return GoogleLogin_get_setting('client_id') !== '' && GoogleLogin_get_setting('client_secret') !== '';
}

function GoogleLogin_is_enabled() {
	return GoogleLogin_is_configured() && GoogleLogin_get_setting('enabled', '1') === '1';
}

// ─── User-mapping helpers (Google email <-> GS username) ────────────────────

function GoogleLogin_get_all_gs_users() {
	$out = [];
	foreach (glob(GSUSERSPATH . '*.xml') as $file) {
		$data = @getXML($file);
		if ($data && isset($data->USR)) {
			$out[] = strtolower((string)$data->USR);
		}
	}
	sort($out);
	return $out;
}

function GoogleLogin_get_mappings() {
	$out = [];
	$res = GoogleLogin_db()->query('SELECT username, google_email FROM gl_users');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$out[$row['username']] = $row['google_email'];
	}
	return $out;
}

function GoogleLogin_find_username_by_email($email) {
	$st = GoogleLogin_db()->prepare('SELECT username FROM gl_users WHERE google_email = :e LIMIT 1');
	$st->bindValue(':e', strtolower(trim($email)), SQLITE3_TEXT);
	$row = $st->execute()->fetchArray(SQLITE3_ASSOC);
	return $row ? $row['username'] : null;
}

function GoogleLogin_set_mapping($username, $google_email) {
	$username = strtolower(trim($username));
	$google_email = strtolower(trim($google_email));
	if ($google_email === '') {
		$st = GoogleLogin_db()->prepare('DELETE FROM gl_users WHERE username = :u');
		$st->bindValue(':u', $username, SQLITE3_TEXT);
		$st->execute();
		return;
	}
	$st = GoogleLogin_db()->prepare("
		INSERT INTO gl_users (username, google_email, linked_at)
		VALUES (:u, :e, strftime('%s','now'))
		ON CONFLICT(username) DO UPDATE SET google_email = excluded.google_email, linked_at = excluded.linked_at
	");
	$st->bindValue(':u', $username,     SQLITE3_TEXT);
	$st->bindValue(':e', $google_email, SQLITE3_TEXT);
	$st->execute();
}

// ─────────────────────────────────────────────────────────────────────────────
// OAUTH FLOW
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Only ever act on the admin login page itself - never on front-end pages
 * or other admin pages. This keeps the whole OAuth surface confined to the
 * one place it's meant to run.
 */
function GoogleLogin_intercept() {
	if (is_frontend() || get_filename_id() !== 'index') return;
	if (empty($_GET['gl_action'])) return;

	if ($_GET['gl_action'] === 'start') {
		GoogleLogin_oauth_start();
	} elseif ($_GET['gl_action'] === 'callback') {
		GoogleLogin_oauth_callback();
	}
}

function GoogleLogin_redirect_uri() {
	global $SITEURL, $GSADMIN;
	return $SITEURL . $GSADMIN . '/index.php?gl_action=callback';
}

/** Send the browser back to the login page with an error code shown. */
function GoogleLogin_fail($reason) {
	global $SITEURL, $GSADMIN;
	header('Location: ' . $SITEURL . $GSADMIN . '/index.php?gl_err=' . rawurlencode($reason));
	exit;
}

function GoogleLogin_oauth_start() {
	if (!GoogleLogin_is_enabled()) {
		GoogleLogin_fail('not_configured');
	}

	global $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly;

	$state = bin2hex(random_bytes(16));
	// Short-lived (10 min), single-purpose CSRF token - intentionally NOT
	// using gs_setcookie()/create_cookie()'s long-lived cookie_time here.
	setcookie(
		GoogleLogin_STATE_COOKIE,
		$state,
		time() + 600,
		$cookie_path,
		isset($cookie_domain) ? $cookie_domain : '',
		$cookie_secure ?? false,
		$cookie_httponly ?? true
	);

	$params = http_build_query([
		'client_id'     => GoogleLogin_get_setting('client_id'),
		'redirect_uri'  => GoogleLogin_redirect_uri(),
		'response_type' => 'code',
		'scope'         => 'openid email',
		'state'         => $state,
		'prompt'        => 'select_account',
	]);

	header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
	exit;
}

function GoogleLogin_oauth_callback() {
	if (!GoogleLogin_is_enabled()) {
		GoogleLogin_fail('not_configured');
	}

	// Verify CSRF state, then immediately clear the cookie either way.
	$state_cookie = $_COOKIE[GoogleLogin_STATE_COOKIE] ?? '';
	$state_given  = $_GET['state'] ?? '';
	setcookie(GoogleLogin_STATE_COOKIE, '', time() - 3600, '/');

	if (!empty($_GET['error'])) {
		GoogleLogin_fail('google_denied');
	}
	if ($state_cookie === '' || $state_given === '' || !hash_equals($state_cookie, $state_given)) {
		GoogleLogin_fail('state_mismatch');
	}
	if (empty($_GET['code'])) {
		GoogleLogin_fail('no_code');
	}

	// ── Exchange authorization code for tokens ──
	$token_payload = http_build_query([
		'code'          => $_GET['code'],
		'client_id'     => GoogleLogin_get_setting('client_id'),
		'client_secret' => GoogleLogin_get_setting('client_secret'),
		'redirect_uri'  => GoogleLogin_redirect_uri(),
		'grant_type'    => 'authorization_code',
	]);

	$token_response = GoogleLogin_http_post('https://oauth2.googleapis.com/token', $token_payload);
	$token_data     = json_decode((string)$token_response, true);

	if (empty($token_data['access_token'])) {
		GoogleLogin_fail('token_exchange_failed');
	}

	// ── Fetch verified email from Google's userinfo endpoint ──
	$userinfo = GoogleLogin_http_get(
		'https://www.googleapis.com/oauth2/v3/userinfo',
		['Authorization: Bearer ' . $token_data['access_token']]
	);
	$userinfo_data = json_decode((string)$userinfo, true);

	if (empty($userinfo_data['email']) || empty($userinfo_data['email_verified'])) {
		GoogleLogin_fail('email_not_verified');
	}

	// ── Match against the admin-configured mapping table ──
	$username = GoogleLogin_find_username_by_email($userinfo_data['email']);
	if (!$username) {
		GoogleLogin_fail('no_matching_account');
	}

	// Re-derive the canonical username straight from the user's XML file
	// (mirrors exactly what login_functions.php does on a normal login).
	$user_xml = GSUSERSPATH . _id($username) . '.xml';
	if (!file_exists($user_xml)) {
		GoogleLogin_fail('account_missing');
	}
	$data = getXML($user_xml);

	global $USR, $cookie_redirect;
	$USR = strtolower((string)$data->USR);

	create_cookie();

	header('Location: ' . $cookie_redirect);
	exit;
}

// ─── Minimal cURL helpers (no external HTTP library dependency) ─────────────

function GoogleLogin_http_post($url, $payload, $headers = []) {
	$headers[] = 'Content-Type: application/x-www-form-urlencoded';
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $payload,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_TIMEOUT        => 10,
	]);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

function GoogleLogin_http_get($url, $headers = []) {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_TIMEOUT        => 10,
	]);
	$response = curl_exec($ch);
	curl_close($ch);
	return $response;
}

// ─────────────────────────────────────────────────────────────────────────────
// LOGIN PAGE UI  (hooked to index-login)
// ─────────────────────────────────────────────────────────────────────────────

function GoogleLogin_render_login_ui() {
	if (!empty($_GET['gl_err'])) {
		$msg = GoogleLogin_error_message($_GET['gl_err']);
		echo '<div style="background:#fff0f0;border:1px solid #fca5a5;color:#b91c1c;padding:10px 14px;border-radius:5px;font-size:.85rem;margin-bottom:14px">' . htmlspecialchars($msg) . '</div>';
	}

	if (!GoogleLogin_is_enabled()) return;

	echo '
	<a href="index.php?gl_action=start" style="display:flex;align-items:center;justify-content:center;gap:10px;background:#fff;border:1px solid #d0d5dd;border-radius:6px;padding:10px 16px;margin-bottom:16px;text-decoration:none;color:#3c4043;font-family:system-ui,sans-serif;font-size:.9rem;font-weight:500">
		<svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.5 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.2-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.5 5.1 29.6 3 24 3 16.3 3 9.7 7.4 6.3 14.7z"/><path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14-5.1l-6.5-5.4C29.4 36 26.9 37 24 37c-5.3 0-9.7-3.3-11.3-8l-6.6 5.1C9.6 40.6 16.3 45 24 45z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.2 4.3-4.1 5.6l6.5 5.4C41.4 35.6 45 30.3 45 24c0-1.2-.1-2.3-.4-3.5z"/></svg>
		' . i18n_r('GoogleLogin/lang_Sign_in_button') . '
	</a>
	<div style="text-align:center;color:#888;font-size:.8rem;margin-bottom:16px">' . i18n_r('GoogleLogin/lang_Or_divider') . '</div>';
}

function GoogleLogin_error_message($code) {
	$map = [
		'not_configured'       => i18n_r('GoogleLogin/lang_Err_not_configured'),
		'google_denied'        => i18n_r('GoogleLogin/lang_Err_denied'),
		'state_mismatch'       => i18n_r('GoogleLogin/lang_Err_generic'),
		'no_code'              => i18n_r('GoogleLogin/lang_Err_generic'),
		'token_exchange_failed'=> i18n_r('GoogleLogin/lang_Err_generic'),
		'email_not_verified'   => i18n_r('GoogleLogin/lang_Err_unverified'),
		'no_matching_account'  => i18n_r('GoogleLogin/lang_Err_no_match'),
		'account_missing'      => i18n_r('GoogleLogin/lang_Err_no_match'),
	];
	return $map[$code] ?? i18n_r('GoogleLogin/lang_Err_generic');
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN SETTINGS PAGE
// ─────────────────────────────────────────────────────────────────────────────

function GoogleLogin_admin_page() {
	$view  = $_GET['gl_view'] ?? 'settings';
	$saved = false;

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gl_save_settings'])) {
		GoogleLogin_set_setting('client_id',     trim($_POST['client_id']     ?? ''));
		GoogleLogin_set_setting('client_secret', trim($_POST['client_secret'] ?? ''));
		GoogleLogin_set_setting('enabled', !empty($_POST['enabled']) ? '1' : '0');
		$saved = true;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gl_save_users'])) {
		$emails = $_POST['google_email'] ?? [];
		foreach ($emails as $username => $email) {
			GoogleLogin_set_mapping($username, trim((string)$email));
		}
		$saved = true;
		$view  = 'users';
	}

	GoogleLogin_admin_styles();
	echo '<div class="gl-wrap">';
	echo '<header>';
	echo '<h3><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="22" height="22" viewBox="0 0 16 16"><path d="M0 0h16v16H0z" fill="none"/><g fill="none" fill-rule="evenodd" clip-rule="evenodd"><path fill="#f44336" d="M7.209 1.061c.725-.081 1.154-.081 1.933 0a6.57 6.57 0 0 1 3.65 1.82a100 100 0 0 0-1.986 1.93q-1.876-1.59-4.188-.734q-1.696.78-2.362 2.528a78 78 0 0 1-2.148-1.658a.26.26 0 0 0-.16-.027q1.683-3.245 5.26-3.86" opacity=".987"/><path fill="#ffc107" d="M1.946 4.92q.085-.013.161.027a78 78 0 0 0 2.148 1.658A7.6 7.6 0 0 0 4.04 7.99q.037.678.215 1.331L2 11.116Q.527 8.038 1.946 4.92" opacity=".997"/><path fill="#448aff" d="M12.685 13.29a26 26 0 0 0-2.202-1.74q1.15-.812 1.396-2.228H8.122V6.713q3.25-.027 6.497.055q.616 3.345-1.423 6.032a7 7 0 0 1-.51.49" opacity=".999"/><path fill="#43a047" d="M4.255 9.322q1.23 3.057 4.51 2.854a3.94 3.94 0 0 0 1.718-.626q1.148.812 2.202 1.74a6.62 6.62 0 0 1-4.027 1.684a6.4 6.4 0 0 1-1.02 0Q3.82 14.524 2 11.116z" opacity=".993"/></g></svg> ' . i18n_r('GoogleLogin/lang_Page_Title') . '</h3>';
	echo '<p>' . i18n_r('GoogleLogin/lang_Description') . '</p>';
	echo '<hr>';
	echo '</header>';

	echo '<div class="gl-tabs">';
	echo '<a href="?id=' . GoogleLogin_SLUG . '&gl_view=settings" class="gl-tab' . ($view === 'settings' ? ' active' : '') . '"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="1.2em" height="1.2em"  viewBox="0 0 128 128"><path d="M0 0h128v128H0z" fill="none"/><path fill="#ea4535" d="M80.6 40.3h.4l-.2-.2l14-14v-.3c-11.8-10.4-28.1-14-43.2-9.5S24.9 32.8 20.7 48c.2-.1.5-.2.8-.2c5.2-3.4 11.4-5.4 17.9-5.4c2.2 0 4.3.2 6.4.6c.1-.1.2-.1.3-.1c9-9.9 24.2-11.1 34.6-2.6z"/><path fill="#557ebf" d="M108.1 47.8c-2.3-8.5-7.1-16.2-13.8-22.1L80 39.9c6 4.9 9.5 12.3 9.3 20v2.5c16.9 0 16.9 25.2 0 25.2H63.9v20h-.1l.1.2h25.4c14.6.1 27.5-9.3 31.8-23.1s-1-28.8-13-36.9"/><path fill="#36a852" d="M39 107.9h26.3V87.7H39c-1.9 0-3.7-.4-5.4-1.1l-15.2 14.6v.2c6 4.3 13.2 6.6 20.7 6.6z"/><path fill="#f9bc15" d="M40.2 41.9c-14.9.1-28.1 9.3-32.9 22.8c-4.8 13.6 0 28.5 11.8 37.3l15.6-14.9c-8.6-3.7-10.6-14.5-4-20.8c6.6-6.4 17.8-4.4 21.7 3.8L68 55.2C61.4 46.9 51.1 42 40.2 42.1z"/></svg> ' . i18n_r('GoogleLogin/lang_Settings_Tab') . '</a>';
	echo '<a href="?id=' . GoogleLogin_SLUG . '&gl_view=users" class="gl-tab' . ($view === 'users' ? ' active' : '') . '"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="1.2em" height="1.2em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M12 2a5 5 0 1 0 0 10a5 5 0 1 0 0-10M4 22h16c.55 0 1-.45 1-1v-1c0-3.86-3.14-7-7-7h-4c-3.86 0-7 3.14-7 7v1c0 .55.45 1 1 1"/></svg> ' . i18n_r('GoogleLogin/lang_Users_Tab') . '</a>';
	echo '<a href="?id=' . GoogleLogin_SLUG . '&gl_view=help" class="gl-tab' . ($view === 'help' ? ' active' : '') . '"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="1.2em" height="1.2em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="currentColor" d="M12.838 17.638q.362-.363.362-.888t-.362-.888-.888-.362-.887.363-.363.887.363.888.887.362.888-.363M12 22q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9 2 12t.788-3.9 2.137-3.175T8.1 2.788 12 2t3.9.788 3.175 2.137T21.213 8.1 22 12t-.788 3.9-2.137 3.175-3.175 2.138T12 22m.1-14.3q.625 0 1.088.4t.462 1q0 .55-.337.975t-.763.8q-.575.5-1.012 1.1t-.438 1.35q0 .35.263.588t.612.237q.375 0 .638-.25t.337-.625q.1-.525.45-.937t.75-.788q.575-.55.988-1.2t.412-1.45q0-1.275-1.037-2.087T12.1 6q-.95 0-1.812.4T8.975 7.625q-.175.3-.112.638t.337.512q.35.2.725.125t.625-.425q.275-.375.688-.575t.862-.2"/></svg> ' . i18n_r('GoogleLogin/lang_Help_Tab') . '</a>';
	echo '</div>';

	if ($saved) {
		echo '<div class="gl-notice">' . i18n_r('GoogleLogin/lang_Saved') . '</div>';
	}

	if ($view === 'users') {
		GoogleLogin_admin_view_users();
	} elseif ($view === 'help') {
		GoogleLogin_admin_view_help();
	} else {
		GoogleLogin_admin_view_settings();
	}

	echo '</div>';
}

function GoogleLogin_render_copyable_uri($uri) {
	$esc = htmlspecialchars($uri);
	echo '
	<div class="gl-copy-box">
		<code id="gl-redirect-uri" title="' . i18n_r('GoogleLogin/lang_Click_To_Select') . '" onclick="GoogleLogin_selectCode(this)">' . $esc . '</code>
		<button type="button" class="gl-copy-btn" onclick="GoogleLogin_copyCode()">' . i18n_r('GoogleLogin/lang_Copy') . '</button>
		<span id="gl-copy-feedback" class="gl-copy-feedback"></span>
	</div>
	<script>
	function GoogleLogin_selectCode(el){
		var range = document.createRange();
		range.selectNodeContents(el);
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	}
	function GoogleLogin_showCopyFeedback(msg){
		var fb = document.getElementById("gl-copy-feedback");
		if (!fb) return;
		fb.textContent = msg;
		setTimeout(function(){ fb.textContent = ""; }, 2000);
	}
	function GoogleLogin_copyCode(){
		var el = document.getElementById("gl-redirect-uri");
		if (!el) return;
		var text = el.textContent;
		GoogleLogin_selectCode(el);

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text)
				.then(function(){ GoogleLogin_showCopyFeedback(' . json_encode(i18n_r('GoogleLogin/lang_Copied')) . '); })
				.catch(function(){ GoogleLogin_showCopyFeedback(' . json_encode(i18n_r('GoogleLogin/lang_Select_Fallback')) . '); });
			return;
		}

		try {
			var ok = document.execCommand("copy");
			GoogleLogin_showCopyFeedback(ok ? ' . json_encode(i18n_r('GoogleLogin/lang_Copied')) . ' : ' . json_encode(i18n_r('GoogleLogin/lang_Select_Fallback')) . ');
		} catch (e) {
			GoogleLogin_showCopyFeedback(' . json_encode(i18n_r('GoogleLogin/lang_Select_Fallback')) . ');
		}
	}
	</script>';
}

function GoogleLogin_admin_view_settings() {
	$client_id     = htmlspecialchars(GoogleLogin_get_setting('client_id'));
	$client_secret = htmlspecialchars(GoogleLogin_get_setting('client_secret'));
	$enabled       = GoogleLogin_get_setting('enabled', '1') === '1';
	$redirect_uri_raw = GoogleLogin_redirect_uri();

	echo '<div class="gl-info-box">' . i18n_r('GoogleLogin/lang_Redirect_URI_Note') . '</div>';
	GoogleLogin_render_copyable_uri($redirect_uri_raw);

	echo '
	<form method="post">
		<div class="gl-row">
			<label><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1em" height="1em" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="#2764e7" d="M18 3a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V7q0-.053.005-.102A3.994 3.994 0 0 1 6 3zm-1 12H7a1 1 0 0 0 0 2h10a1 1 0 0 0 0-2M9 7a2.995 2.995 0 0 0-2.995 2.898A1 1 0 0 0 6 10a3 3 0 1 0 3-3m8 4h-2a1 1 0 0 0 0 2h2a1 1 0 0 0 0-2m0-4h-2a1 1 0 0 0 0 2h2a1 1 0 0 0 0-2"/></svg> ' . i18n_r('GoogleLogin/lang_Client_ID') . '</label>
			<input type="text" name="client_id" value="' . $client_id . '" style="width:100%;max-width:420px;padding:8px 10px;border:1px solid #d0d5dd;border-radius:5px">
		</div>
		<div class="gl-row">
			<label><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1em" height="1em" viewBox="0 0 20 24"><path d="M0 0h20v24H0z" fill="none"/><path fill="#2764e7" d="m8.312 22.15l1.385-6.461l-1.385-1.846l-1.846-.923zm3.692 0l1.846-9.229l-1.846.923l-1.385 1.846zm2.307-14.565a.4.4 0 0 0-.058-.087a3.9 3.9 0 0 0-1.401-.114l.016-.001a13 13 0 0 0-2.493.29l.084-.015a1.6 1.6 0 0 1-.616-.001l.01.002a12.4 12.4 0 0 0-2.396-.274h-.013a4 4 0 0 0-1.413.121l.028-.006a.4.4 0 0 0-.057.084l-.001.002q.029.26.058.39q.045.057.106.093l.002.001a.26.26 0 0 1 .108.149v.002c.034.075.07.174.102.274l.006.022q.08.24.101.296t.108.245c.044.1.085.18.129.258l-.007-.013q.036.058.13.202a.7.7 0 0 0 .171.193l.002.001q.08.05.202.137a.7.7 0 0 0 .248.114l.005.001q.13.029.296.058c.103.018.222.029.344.029h.011h-.001l.07.001c.284 0 .553-.067.791-.186l-.01.005c.197-.098.356-.246.466-.428l.003-.005c.08-.142.151-.307.204-.479l.005-.018c.047-.164.103-.305.172-.439l-.006.013a.28.28 0 0 1 .252-.18h.174a.28.28 0 0 1 .252.178l.001.002c.062.12.119.262.161.409l.004.017c.058.191.129.356.215.51l-.007-.013c.112.187.272.335.462.43l.006.003c.228.114.497.181.781.181l.074-.002h-.003h.01q.182 0 .356-.031l-.012.002q.166-.029.296-.058a.7.7 0 0 0 .255-.117l-.002.001q.122-.086.202-.137a.7.7 0 0 0 .171-.191l.002-.003q.094-.144.13-.202c.038-.065.079-.145.115-.227l.007-.017q.086-.187.108-.245t.101-.296c.038-.122.074-.221.116-.316l-.008.02a.26.26 0 0 1 .107-.151l.001-.001a.4.4 0 0 0 .107-.093l.001-.001a3 3 0 0 0 .059-.376l.001-.014zm5.999 12.676A3.58 3.58 0 0 1 19.257 23a3.9 3.9 0 0 1-2.809.995h.009H3.855a3.9 3.9 0 0 1-2.803-.998l.004.003a3.58 3.58 0 0 1-1.05-2.748v.009q0-.88.065-1.701c.06-.684.157-1.302.292-1.905l-.018.095a10 10 0 0 1 .565-1.847l-.024.066a5.9 5.9 0 0 1 .923-1.501l-.007.008a4.1 4.1 0 0 1 1.324-1.067l.023-.011l-1.298-3.172h3.086a5.5 5.5 0 0 1-.32-1.845v-.002q0-.173.029-.462q-2.8-.577-2.8-1.385q0-.822 3.028-1.428c.215-.748.468-1.389.773-2l-.03.067A7.6 7.6 0 0 1 6.65.518l-.01.013a1.4 1.4 0 0 1 1.095-.534c.457.054.868.213 1.22.453L8.946.444c.343.234.754.393 1.198.446l.013.001a2.7 2.7 0 0 0 1.22-.453l-.009.006a2.7 2.7 0 0 1 1.2-.446l.013-.001c.443.002.837.209 1.094.531l.002.003a7.5 7.5 0 0 1 .997 1.597l.019.047c.274.543.527 1.184.722 1.85l.021.082q3.028.606 3.028 1.428q0 .808-2.8 1.385a5.6 5.6 0 0 1-.301 2.347l.012-.039h3.086l-1.178 3.248c.631.341 1.151.81 1.541 1.377l.01.015c.415.591.74 1.282.935 2.025l.01.045c.173.612.319 1.353.411 2.111l.007.074c.073.629.114 1.358.114 2.097v.048v-.003z"/></svg> ' . i18n_r('GoogleLogin/lang_Client_Secret') . '</label>
			<input type="password" name="client_secret" value="' . $client_secret . '" style="width:100%;max-width:420px;padding:8px 10px;border:1px solid #d0d5dd;border-radius:5px">
		</div>
		<div class="gl-row">
			<label><input type="checkbox" name="enabled" value="1" ' . ($enabled ? 'checked' : '') . '> ' . i18n_r('GoogleLogin/lang_Enabled') . '</label>
		</div>
		<button type="submit" name="gl_save_settings" value="1" class="gl-btn">' . i18n_r('GoogleLogin/lang_Save') . '</button>
	</form>';
}

function GoogleLogin_admin_view_users() {
	$usernames = GoogleLogin_get_all_gs_users();
	$mappings  = GoogleLogin_get_mappings();

	echo '<form method="post">';
	echo '<div class="gl-info-box">' . i18n_r('GoogleLogin/lang_Users_Info') . '</div>';
	echo '<table class="gl-table"><thead><tr><th><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="18" height="18" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path fill="#2764e7" d="M12 2a5 5 0 1 0 0 10a5 5 0 1 0 0-10M4 22h16c.55 0 1-.45 1-1v-1c0-3.86-3.14-7-7-7h-4c-3.86 0-7 3.14-7 7v1c0 .55.45 1 1 1"/></svg> ' . i18n_r('GoogleLogin/lang_Username') . '</th><th><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="16" height="16" viewBox="0 0 512 512"><path d="M0 0h512v512H0z" fill="none"/><path fill="#4285f4" d="M34.9 448h81.5V250.2L0 163v250.2C0 432.5 15.7 448 34.9 448"/><path fill="#34a853" d="M395.6 448h81.5c19.3 0 34.9-15.7 34.9-34.9V163l-116.4 87.3"/><path fill="#fbbc04" d="M395.6 99v151.3L512 163v-46.5c0-43.2-49.3-67.8-83.8-41.9"/><path fill="#ea4335" d="M116.4 250.2V99L256 203.7L395.6 99v151.3L256 355"/><path fill="#c5221f" d="M0 116.4V163l116.4 87.3V99L83.8 74.5C49.2 48.6 0 73.2 0 116.4"/></svg> ' . i18n_r('GoogleLogin/lang_Google_Email') . '</th></tr></thead><tbody>';

	foreach ($usernames as $username) {
		$linked = htmlspecialchars($mappings[$username] ?? '');
		echo '<tr>
			<td>' . htmlspecialchars($username) . '</td>
			<td><input type="email" name="google_email[' . htmlspecialchars($username) . ']" value="' . $linked . '" placeholder="' . i18n_r('GoogleLogin/lang_No_account_linked') . '" style="width:100%;max-width:320px;padding:6px 10px;border:1px solid #d0d5dd;border-radius:5px"></td>
		</tr>';
	}

	echo '</tbody></table>';
	echo '<button type="submit" name="gl_save_users" value="1" class="gl-btn" style="margin-top:14px">' . i18n_r('GoogleLogin/lang_Save') . '</button>';
	echo '</form>';
}

function GoogleLogin_admin_view_help() {
	$redirect_uri_raw = GoogleLogin_redirect_uri();

	echo '
	<div class="gl-help">
		<p style="margin-bottom:14px">' . i18n_r('GoogleLogin/lang_Help_Intro') . '</p>

		<p style="margin-bottom:10px">
			<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="gl-btn" style="text-decoration:none;display:inline-block"><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;" width="1.2em" height="1.2em"  viewBox="0 0 128 128"><path d="M0 0h128v128H0z" fill="none"/><path fill="#ea4535" d="M80.6 40.3h.4l-.2-.2l14-14v-.3c-11.8-10.4-28.1-14-43.2-9.5S24.9 32.8 20.7 48c.2-.1.5-.2.8-.2c5.2-3.4 11.4-5.4 17.9-5.4c2.2 0 4.3.2 6.4.6c.1-.1.2-.1.3-.1c9-9.9 24.2-11.1 34.6-2.6z"/><path fill="#557ebf" d="M108.1 47.8c-2.3-8.5-7.1-16.2-13.8-22.1L80 39.9c6 4.9 9.5 12.3 9.3 20v2.5c16.9 0 16.9 25.2 0 25.2H63.9v20h-.1l.1.2h25.4c14.6.1 27.5-9.3 31.8-23.1s-1-28.8-13-36.9"/><path fill="#36a852" d="M39 107.9h26.3V87.7H39c-1.9 0-3.7-.4-5.4-1.1l-15.2 14.6v.2c6 4.3 13.2 6.6 20.7 6.6z"/><path fill="#f9bc15" d="M40.2 41.9c-14.9.1-28.1 9.3-32.9 22.8c-4.8 13.6 0 28.5 11.8 37.3l15.6-14.9c-8.6-3.7-10.6-14.5-4-20.8c6.6-6.4 17.8-4.4 21.7 3.8L68 55.2C61.4 46.9 51.1 42 40.2 42.1z"/></svg> ' . i18n_r('GoogleLogin/lang_Help_Console_Link') . ' &rarr;</a>
		</p>

		<ol style="margin:16px 0 16px 20px;line-height:1.9;font-size:.85rem">
			<li>' . i18n_r('GoogleLogin/lang_Help_Step1') . '</li>
			<li>' . i18n_r('GoogleLogin/lang_Help_Step2') . '</li>
			<li>' . i18n_r('GoogleLogin/lang_Help_Step3') . '</li>
		</ol>';

	GoogleLogin_render_copyable_uri($redirect_uri_raw);

	echo '
		<ol start="4" style="margin:16px 0 16px 20px;line-height:1.9;font-size:.85rem">
			<li>' . i18n_r('GoogleLogin/lang_Help_Step4') . '</li>
		</ol>

		<div class="gl-info-box">' . i18n_r('GoogleLogin/lang_Help_Https_Note') . '</div>
	</div>';
}

function GoogleLogin_admin_styles() {
	echo '
	<style>
		.gl-wrap{max-width:940px;margin:20px 0;font-size:.9rem}
		.gl-wrap h3{font-size:1.1rem;font-weight:700;margin-bottom:16px}
		.gl-wrap header p {color: grey;}
		.gl-wrap a { text-decoration:none!important}
		.gl-wrap hr{margin:10px 0 30px; border: 0; border-top: 1px dotted #8c8c8c; border-bottom: 1px dotted #fff;}
		.gl-tabs{display:flex;border-bottom:1px solid #e5e7eb;margin-bottom:38px;;}
		.gl-tab{padding:8px 16px;text-decoration:none;color:#555;font-weight:600;font-size:.85rem;border-bottom:2px solid transparent}
		.gl-tab.active{color:#2764e7!important;border-bottom-color:#2764e7}
		.gl-row{margin-bottom:14px}
		.gl-row label{display:block;font-weight:600;font-size:.82rem;margin-bottom:5px;color:#1a1a2e}
		.gl-btn{background:#2764e7;color:#fff!important;border:none;padding:.55rem 1.4rem;border-radius:5px;font-size:.85rem;font-weight:600;cursor:pointer}
		.gl-btn:hover{background:#0094f0}
		.gl-info-box{background:#f0f6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:10px 13px;border-radius:6px;font-size:.82rem;margin-bottom:16px;line-height:1.6;word-break:break-all}
		.gl-notice{background:#f0fdf4;border:1px solid #86efac;color:#166534;padding:8px 13px;border-radius:6px;font-size:.85rem;margin-bottom:16px}
		.gl-table{width:100%;border-collapse:collapse}
		.gl-table th{text-align:left;font-size:.78rem;color:#888;padding:6px 8px;border-bottom:1px solid #e5e7eb}
		.gl-table td{padding:6px 8px;border-bottom:1px solid #f4f4f4}
		.gl-copy-box{display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap}
		.gl-copy-box code{background:#f8f9fa;border:1px solid #e5e7eb;border-radius:5px;padding:8px 12px;font-size:.82rem;word-break:break-all;cursor:pointer;flex:1;min-width:200px}
		.gl-copy-btn{background:#fff;border:1px solid #d0d5dd;color:#333;padding:7px 14px;border-radius:5px;font-size:.8rem;font-weight:600;cursor:pointer;white-space:nowrap}
		.gl-copy-btn:hover{background:#f4f4f4}
		.gl-copy-feedback{font-size:.78rem;color:#166534;font-weight:600}
		#gl-redirect-uri{color:#CF3805;}
	</style>';
}
