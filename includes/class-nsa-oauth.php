<?php
defined( 'ABSPATH' ) || exit;

/**
 * A small OAuth 2.1 authorisation server (authorisation code with PKCE, dynamic client registration,
 * client ID metadata documents and refresh token rotation) so Claude can connect as a custom connector.
 * Only administrators can approve a connection.
 */
class NSA_OAuth {

	public static $current_client = '';
	public static $current_scope  = array();

	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	public static function protected_resource_metadata() {
		return array(
			'resource'                 => NSA_Settings::mcp_url(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( 'audit', 'fix' ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => get_bloginfo( 'name' ) . ' (Nomad Site Audit)',
		);
	}

	public static function authorization_server_metadata() {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => home_url( '/nsa-oauth/authorize' ),
			'token_endpoint'                        => home_url( '/nsa-oauth/token' ),
			'registration_endpoint'                 => home_url( '/nsa-oauth/register' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                      => array( 'audit', 'fix' ),
			'client_id_metadata_document_supported' => true,
		);
	}

	/* ---------------------------------------------------------------- Clients */

	public static function register() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			NSA_HTTP::oauth_error( 'invalid_request', 'Use POST.', 405 );
		}
		if ( ! NSA_Settings::get( 'enabled' ) ) {
			NSA_HTTP::oauth_error( 'access_denied', 'The connector is switched off on this site.', 403 );
		}
		if ( ! NSA_Settings::connect_window_open() ) {
			NSA_HTTP::oauth_error( 'access_denied', 'New connections are closed. In WordPress, go to Settings, Nomad Site Audit and click "Allow a new connection", then try again within 15 minutes.', 403 );
		}
		// At most 10 registrations an hour, and forget registrations that never connected.
		$count = (int) get_transient( 'nsa_register_count' );
		if ( $count >= 10 ) {
			NSA_HTTP::oauth_error( 'access_denied', 'Too many registrations. Try again in an hour.', 429 );
		}
		set_transient( 'nsa_register_count', $count + 1, HOUR_IN_SECONDS );
		self::prune_clients();
		$data = NSA_HTTP::body_params();
		$uris = isset( $data['redirect_uris'] ) && is_array( $data['redirect_uris'] ) ? array_values( array_filter( $data['redirect_uris'], 'is_string' ) ) : array();
		if ( empty( $uris ) ) {
			NSA_HTTP::oauth_error( 'invalid_redirect_uri', 'At least one redirect URI is required.' );
		}
		foreach ( $uris as $uri ) {
			if ( ! self::redirect_allowed( $uri ) ) {
				NSA_HTTP::oauth_error( 'invalid_redirect_uri', 'Redirect URI not allowed by this site: ' . $uri );
			}
		}
		global $wpdb;
		$client_id = 'nsa_' . wp_generate_password( 32, false, false );
		$name      = isset( $data['client_name'] ) ? sanitize_text_field( $data['client_name'] ) : 'Unnamed app';
		$wpdb->insert( NSA_DB::table( 'clients' ), array(
			'client_id'     => $client_id,
			'client_name'   => mb_substr( $name, 0, 200 ),
			'redirect_uris' => wp_json_encode( $uris ),
			'created_at'    => NSA_DB::now(),
		) );
		NSA_HTTP::json( array(
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'client_name'                => $name,
			'redirect_uris'              => $uris,
			'grant_types'                => array( 'authorization_code', 'refresh_token' ),
			'response_types'             => array( 'code' ),
			'token_endpoint_auth_method' => 'none',
		), 201 );
	}

	/** Removes registered apps older than a day that never received a token. */
	public static function prune_clients() {
		global $wpdb;
		$c = NSA_DB::table( 'clients' );
		$t = NSA_DB::table( 'tokens' );
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $c WHERE created_at < %s AND client_id NOT IN ( SELECT DISTINCT client_id FROM $t )",
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
		) );
	}

	/** Returns array( name, redirect_uris ) or null. */
	public static function get_client( $client_id ) {
		if ( '' === (string) $client_id ) {
			return null;
		}
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			return self::get_metadata_client( $client_id );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . NSA_DB::table( 'clients' ) . ' WHERE client_id = %s', $client_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		return array(
			'name'          => $row['client_name'],
			'redirect_uris' => (array) json_decode( $row['redirect_uris'], true ),
		);
	}

	/** Client ID metadata documents: the client ID is a URL that describes the app. */
	private static function get_metadata_client( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! self::host_allowed( $host ) ) {
			return null;
		}
		$key    = 'nsa_cimd_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = wp_safe_remote_get( $url, array( 'timeout' => 8, 'redirection' => 0 ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$doc = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $doc ) || ( $doc['client_id'] ?? '' ) !== $url || empty( $doc['redirect_uris'] ) ) {
			return null;
		}
		$client = array(
			'name'          => sanitize_text_field( $doc['client_name'] ?? $host ),
			'redirect_uris' => array_values( array_filter( (array) $doc['redirect_uris'], 'is_string' ) ),
		);
		set_transient( $key, $client, DAY_IN_SECONDS );
		return $client;
	}

	public static function host_allowed( $host ) {
		$host = strtolower( (string) $host );
		foreach ( NSA_Settings::allowed_hosts() as $allowed ) {
			if ( $host === $allowed || ( strlen( $host ) > strlen( $allowed ) && substr( $host, -strlen( $allowed ) - 1 ) === '.' . $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	public static function redirect_allowed( $uri ) {
		$parts = wp_parse_url( $uri );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || ! empty( $parts['fragment'] ) ) {
			return false;
		}
		$host     = strtolower( $parts['host'] );
		$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true );
		if ( 'https' !== $parts['scheme'] && ! ( 'http' === $parts['scheme'] && $is_local ) ) {
			return false;
		}
		return self::host_allowed( $host );
	}

	/* ---------------------------------------------------------------- Authorise */

	public static function authorize() {
		nocache_headers();
		$p = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'scope', 'code_challenge', 'code_challenge_method', 'resource' ) as $k ) {
			$p[ $k ] = isset( $_REQUEST[ $k ] ) ? (string) wp_unslash( $_REQUEST[ $k ] ) : '';
		}

		if ( ! NSA_Settings::get( 'enabled' ) ) {
			self::page( 'Connector switched off', '<p>The Claude connector is switched off on this site. An administrator can switch it on under Settings, Nomad Site Audit.</p>' );
		}

		$client = self::get_client( $p['client_id'] );
		if ( ! $client ) {
			self::page( 'Unknown app', '<p>This app is not registered with this site.</p>' );
		}
		if ( ! in_array( $p['redirect_uri'], $client['redirect_uris'], true ) || ! self::redirect_allowed( $p['redirect_uri'] ) ) {
			self::page( 'Address not allowed', '<p>The return address for this app is not on the allowed list for this site.</p>' );
		}
		if ( 'code' !== $p['response_type'] ) {
			self::redirect_error( $p, 'unsupported_response_type', 'Only the authorisation code flow is supported.' );
		}
		if ( '' === $p['code_challenge'] || 'S256' !== $p['code_challenge_method'] ) {
			self::redirect_error( $p, 'invalid_request', 'PKCE with S256 is required.' );
		}

		if ( ! is_user_logged_in() ) {
			$here = home_url( '/nsa-oauth/authorize' ) . '?' . http_build_query( $p );
			wp_safe_redirect( wp_login_url( $here ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::page( 'Administrator required', '<p>Only an administrator can connect this site to Claude.</p>' );
		}
		if ( ! NSA_Settings::connect_window_open() ) {
			self::page( 'New connections are closed', '<p>For safety, this site only accepts a new connection for 15 minutes after an administrator allows it.</p><p>If you started this connection yourself, go to <a href="' . esc_url( admin_url( 'options-general.php?page=nomad-site-audit' ) ) . '">Settings, Nomad Site Audit</a>, click <strong>Allow a new connection</strong>, then connect again from Claude.</p><p class="note">If you did not start this, someone may be trying to get access to your site. Close this page.</p>' );
		}

		$fixes_on  = NSA_Settings::fixes_active();
		$requested = array_filter( explode( ' ', $p['scope'] ) );
		$want_fix  = empty( $requested ) || in_array( 'fix', $requested, true );

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			check_admin_referer( 'nsa_authorize' );
			if ( empty( $_POST['approve'] ) ) {
				self::redirect_error( $p, 'access_denied', 'The administrator declined the connection.' );
			}
			$scope = array( 'audit' );
			if ( $fixes_on && $want_fix && ! empty( $_POST['grant_fix'] ) ) {
				$scope[] = 'fix';
			}
			$code = wp_generate_password( 48, false, false );
			set_transient( 'nsa_code_' . hash( 'sha256', $code ), array(
				'client_id'      => $p['client_id'],
				'redirect_uri'   => $p['redirect_uri'],
				'code_challenge' => $p['code_challenge'],
				'user_id'        => get_current_user_id(),
				'scope'          => implode( ' ', $scope ),
			), 10 * MINUTE_IN_SECONDS );
			// One approval per window: close it so a second request needs a new decision.
			NSA_Settings::update( array( 'connect_until' => 0 ) );
			self::redirect_to_client( $p['redirect_uri'], array(
				'code'  => $code,
				'state' => $p['state'],
				'iss'   => self::issuer(),
			) );
		}

		$user  = wp_get_current_user();
		$app   = esc_html( $client['name'] );
		$back  = esc_html( wp_parse_url( $p['redirect_uri'], PHP_URL_HOST ) );
		$site  = esc_html( get_bloginfo( 'name' ) );
		$html  = '<p><strong>' . $app . '</strong> (' . $back . ') wants to connect to <strong>' . $site . '</strong> as ' . esc_html( $user->user_login ) . '.</p>';
		$html .= '<form method="post">' . wp_nonce_field( 'nsa_authorize', '_wpnonce', true, false );
		$html .= '<div class="scope"><label><input type="checkbox" checked disabled> <strong>Audit</strong>: read settings, plugins, content and code to check security, search and site health. Secret keys and passwords are never shared.</label></div>';
		if ( $fixes_on && $want_fix ) {
			$html .= '<div class="scope"><label><input type="checkbox" name="grant_fix" value="1" checked> <strong>Fix</strong>: apply changes such as updates, security settings and search titles. Each change is previewed first, logged and backed up where possible.</label></div>';
		} elseif ( ! $fixes_on ) {
			$html .= '<p class="note">Fixes are switched off on this site, so this connection can only audit. You can switch them on under Settings, Nomad Site Audit.</p>';
		}
		$html .= '<p class="note"><strong>Only click Allow if you started this connection yourself in the last few minutes.</strong> If someone sent you this link, click Deny.</p>';
		$html .= '<p><button class="primary" name="approve" value="1">Allow</button> <button name="deny" value="1">Deny</button></p></form>';
		$html .= '<p class="note">You can disconnect at any time under Settings, Nomad Site Audit.</p>';
		self::page( 'Connect to Claude', $html );
	}

	private static function redirect_to_client( $uri, array $params ) {
		$params = array_filter( $params, function ( $v ) {
			return '' !== $v && null !== $v;
		} );
		$sep = ( false === strpos( $uri, '?' ) ) ? '?' : '&';
		// wp_redirect, not wp_safe_redirect: the client is external and was validated above.
		wp_redirect( $uri . $sep . http_build_query( $params ) );
		exit;
	}

	private static function redirect_error( $p, $error, $description ) {
		self::redirect_to_client( $p['redirect_uri'], array(
			'error'             => $error,
			'error_description' => $description,
			'state'             => $p['state'],
			'iss'               => self::issuer(),
		) );
	}

	private static function page( $title, $body ) {
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: DENY' );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
		echo '<title>' . esc_html( $title ) . '</title><style>
			body{font:16px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f3f4f6;color:#1f2937;margin:0;padding:40px 16px}
			.box{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
			h1{font-size:20px;margin:0 0 16px}.scope{border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:10px 0}
			button{font:inherit;padding:10px 18px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
			button.primary{background:#2563eb;border-color:#2563eb;color:#fff}.note{color:#6b7280;font-size:14px}
		</style></head><body><div class="box"><h1>' . esc_html( $title ) . '</h1>' . $body . '</div></body></html>';
		exit;
	}

	/* ---------------------------------------------------------------- Tokens */

	public static function token() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			NSA_HTTP::oauth_error( 'invalid_request', 'Use POST.', 405 );
		}
		$d     = NSA_HTTP::body_params();
		$grant = (string) ( $d['grant_type'] ?? '' );

		if ( 'authorization_code' === $grant ) {
			$code = (string) ( $d['code'] ?? '' );
			$key  = 'nsa_code_' . hash( 'sha256', $code );
			$data = get_transient( $key );
			delete_transient( $key ); // Single use.
			if ( ! is_array( $data ) ) {
				NSA_HTTP::oauth_error( 'invalid_grant', 'The code is invalid or has expired.' );
			}
			if ( ( $d['client_id'] ?? '' ) !== $data['client_id'] || ( $d['redirect_uri'] ?? '' ) !== $data['redirect_uri'] ) {
				NSA_HTTP::oauth_error( 'invalid_grant', 'Client or redirect URI does not match.' );
			}
			$verifier  = (string) ( $d['code_verifier'] ?? '' );
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			if ( '' === $verifier || ! hash_equals( $data['code_challenge'], $challenge ) ) {
				NSA_HTTP::oauth_error( 'invalid_grant', 'PKCE verification failed.' );
			}
			NSA_HTTP::json( self::issue_tokens( $data['client_id'], (int) $data['user_id'], $data['scope'] ) );
		}

		if ( 'refresh_token' === $grant ) {
			$row = self::find_token( (string) ( $d['refresh_token'] ?? '' ), 'refresh' );
			if ( ! $row || ( isset( $d['client_id'] ) && $d['client_id'] !== $row['client_id'] ) ) {
				NSA_HTTP::oauth_error( 'invalid_grant', 'The refresh token is invalid or has expired.' );
			}
			global $wpdb;
			$wpdb->update( NSA_DB::table( 'tokens' ), array( 'revoked' => 1 ), array( 'id' => $row['id'] ) ); // Rotation.
			// The fix scope is kept; whether fixes work is decided on every call by the 24 hour switch.
			NSA_HTTP::json( self::issue_tokens( $row['client_id'], (int) $row['user_id'], $row['scope'] ) );
		}

		NSA_HTTP::oauth_error( 'unsupported_grant_type', 'Unsupported grant type.' );
	}

	private static function issue_tokens( $client_id, $user_id, $scope ) {
		global $wpdb;
		$access  = 'nsa_at_' . wp_generate_password( 48, false, false );
		$refresh = 'nsa_rt_' . wp_generate_password( 48, false, false );
		$life    = (int) NSA_Settings::get( 'token_lifetime' );
		foreach ( array( array( $access, 'access', $life ), array( $refresh, 'refresh', (int) NSA_Settings::get( 'refresh_lifetime' ) ) ) as $t ) {
			$wpdb->insert( NSA_DB::table( 'tokens' ), array(
				'token_hash' => hash( 'sha256', $t[0] ),
				'kind'       => $t[1],
				'client_id'  => $client_id,
				'user_id'    => $user_id,
				'scope'      => $scope,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $t[2] ),
				'created_at' => NSA_DB::now(),
			) );
		}
		// Housekeeping: remove long expired tokens.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . NSA_DB::table( 'tokens' ) . ' WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => $life,
			'refresh_token' => $refresh,
			'scope'         => $scope,
		);
	}

	private static function find_token( $token, $kind ) {
		if ( '' === $token ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . NSA_DB::table( 'tokens' ) . ' WHERE token_hash = %s AND kind = %s AND revoked = 0 AND expires_at > %s',
			hash( 'sha256', $token ), $kind, NSA_DB::now()
		), ARRAY_A );
		return $row ?: null;
	}

	/** Validates the Bearer token on an MCP request and signs in its user. Returns true or false. */
	public static function authenticate_request() {
		if ( ! NSA_Settings::get( 'enabled' ) ) {
			return false;
		}
		$row = self::find_token( NSA_HTTP::bearer_token(), 'access' );
		if ( ! $row ) {
			return false;
		}
		$user = get_user_by( 'id', (int) $row['user_id'] );
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			return false;
		}
		wp_set_current_user( $user->ID );
		self::$current_client = $row['client_id'];
		self::$current_scope  = array_filter( explode( ' ', $row['scope'] ) );
		if ( empty( $row['last_used_at'] ) || strtotime( $row['last_used_at'] . ' UTC' ) < time() - 300 ) {
			global $wpdb;
			$wpdb->update( NSA_DB::table( 'tokens' ), array( 'last_used_at' => NSA_DB::now() ), array( 'id' => $row['id'] ) );
		}
		return true;
	}

	public static function can_fix() {
		return in_array( 'fix', self::$current_scope, true ) && NSA_Settings::fixes_active();
	}

	/** Lists apps with active tokens for the settings page. */
	public static function connections() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT client_id, user_id, MAX(scope) AS scope, MIN(created_at) AS first_seen, MAX(COALESCE(last_used_at, created_at)) AS last_used
			 FROM ' . NSA_DB::table( 'tokens' ) . ' WHERE revoked = 0 AND expires_at > %s GROUP BY client_id, user_id ORDER BY last_used DESC',
			NSA_DB::now()
		), ARRAY_A );
	}

	public static function revoke_client( $client_id ) {
		global $wpdb;
		$wpdb->update( NSA_DB::table( 'tokens' ), array( 'revoked' => 1 ), array( 'client_id' => $client_id ) );
	}

	public static function revoke_all() {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . NSA_DB::table( 'tokens' ) . ' SET revoked = 1' );
	}
}
