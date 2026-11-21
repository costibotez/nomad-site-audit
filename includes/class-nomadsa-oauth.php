<?php
defined( 'ABSPATH' ) || exit;

// Queries here use the plugin's own tables or read only counts, which WordPress has no API or cache for.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * A small OAuth 2.1 authorisation server (authorisation code with PKCE, dynamic client registration,
 * client ID metadata documents and refresh token rotation) for Codex and Claude.
 * Only administrators can approve a connection.
 */
class NomadSA_OAuth {

	public static $current_client = '';
	public static $current_user_id = 0;

	public static function issuer() {
		return home_url( '/nsa-oauth' );
	}

	public static function protected_resource_metadata() {
		return array(
			'resource'                 => NomadSA_Settings::mcp_url(),
			'authorization_servers'    => array( self::issuer() ),
			'scopes_supported'         => array( 'audit' ),
			'bearer_methods_supported' => array( 'header' ),
			'resource_name'            => get_bloginfo( 'name' ) . ' (Nomad Site Audit)',
		);
	}

	public static function authorization_server_metadata() {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_response_iss_parameter_supported' => true,
			'authorization_endpoint'                => home_url( '/nsa-oauth/authorize' ),
			'token_endpoint'                        => home_url( '/nsa-oauth/token' ),
			'registration_endpoint'                 => home_url( '/nsa-oauth/register' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                      => array( 'audit' ),
			'client_id_metadata_document_supported' => true,
		);
	}

	/* ---------------------------------------------------------------- Clients */

	public static function register() {
		if ( 'POST' !== NomadSA_HTTP::method() ) {
			NomadSA_HTTP::oauth_error( 'invalid_request', 'Use POST.', 405 );
		}
		if ( ! NomadSA_Settings::get( 'enabled' ) ) {
			NomadSA_HTTP::oauth_error( 'access_denied', 'The connector is switched off on this site.', 403 );
		}
		if ( ! NomadSA_Settings::connect_window_open() ) {
			NomadSA_HTTP::oauth_error( 'access_denied', 'New connections are closed. In WordPress, go to Settings, Nomad Site Audit and click "Allow a new connection", then try again within 15 minutes.', 403 );
		}
		// At most 10 registrations an hour, and forget registrations that never connected.
		$count = (int) get_transient( 'nomadsa_register_count' );
		if ( $count >= 10 ) {
			NomadSA_HTTP::oauth_error( 'access_denied', 'Too many registrations. Try again in an hour.', 429 );
		}
		set_transient( 'nomadsa_register_count', $count + 1, HOUR_IN_SECONDS );
		self::prune_clients();
		$data = NomadSA_HTTP::body_params();
		$uris = $data['redirect_uris'] ?? null;
		if ( ! is_array( $uris ) || ! $uris || count( $uris ) > 10 || array_values( $uris ) !== $uris ) {
			NomadSA_HTTP::oauth_error( 'invalid_redirect_uri', 'At least one redirect URI is required.' );
		}
		foreach ( $uris as $uri ) {
			if ( ! self::redirect_allowed( $uri ) ) {
				NomadSA_HTTP::oauth_error( 'invalid_redirect_uri', 'A redirect URI is invalid or is not allowed by this site.' );
			}
		}
		global $wpdb;
		$client_id = 'nomadsa_' . wp_generate_password( 32, false, false );
		$name      = self::client_name( $data['client_name'] ?? null, 'Unnamed app' );
		$saved = $wpdb->insert( NomadSA_DB::table( 'clients' ), array(
			'client_id'     => $client_id,
			'client_name'   => $name,
			'redirect_uris' => wp_json_encode( $uris ),
			'created_at'    => NomadSA_DB::now(),
		) );
		if ( false === $saved ) {
			NomadSA_HTTP::oauth_error( 'server_error', 'Could not register the client.', 500 );
		}

		NomadSA_HTTP::json( array(
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
		$wpdb->query( $wpdb->prepare(
			'DELETE FROM %i WHERE created_at < %s AND client_id NOT IN ( SELECT DISTINCT client_id FROM %i )',
			NomadSA_DB::table( 'clients' ),
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			NomadSA_DB::table( 'tokens' )
		) );
	}

	/** Returns array( name, redirect_uris ) or null. */
	public static function get_client( $client_id, $allow_fetch = true ) {
		if ( '' === (string) $client_id ) {
			return null;
		}
		if ( 0 === strpos( $client_id, 'https://' ) ) {
			return self::get_metadata_client( $client_id, $allow_fetch );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE client_id = %s', NomadSA_DB::table( 'clients' ), $client_id ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		return array(
			'name'          => $row['client_name'],
			'redirect_uris' => (array) json_decode( $row['redirect_uris'], true ),
		);
	}

	/** Client ID metadata documents: the client ID is a URL that describes the app. */
	private static function get_metadata_client( $url, $allow_fetch ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! self::is_codex_metadata( $url ) && ! self::redirect_allowed( $url ) ) {
			return null;
		}
		$key    = 'nomadsa_cimd_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( ! $allow_fetch ) {
			return self::is_codex_metadata( $url ) ? array( 'name' => 'Codex' ) : null;
		}
		$res = wp_safe_remote_get( $url, array( 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 32 * KB_IN_BYTES ) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$doc = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $doc ) || ( $doc['client_id'] ?? '' ) !== $url || ! is_array( $doc['redirect_uris'] ?? null ) ) {
			return null;
		}
		$client = array(
			'name'          => self::client_name( $doc['client_name'] ?? null, $host ),
			'redirect_uris' => array_values( array_filter( $doc['redirect_uris'], array( __CLASS__, 'redirect_allowed' ) ) ),
		);
		set_transient( $key, $client, DAY_IN_SECONDS );
		return $client;
	}

	private static function is_codex_metadata( $url ) {
		return (bool) preg_match( '#^https://chatgpt\.com/oauth/codex/(?:[A-Za-z0-9_-]{1,64}/)?client\.json$#D', $url );
	}

	/** Native clients can choose a loopback port at login. All other URI components must match. */
	private static function matches_redirect( $uri, array $registered ) {
		if ( in_array( $uri, $registered, true ) ) {
			return true;
		}
		$parts = wp_parse_url( $uri );
		if ( 'http' !== $parts['scheme'] || ! in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '[::1]' ), true ) ) {
			return false;
		}
		$portless = preg_replace( '#^(http://(?:localhost|127\.0\.0\.1|\[::1\])):[0-9]+(?=/|$)#i', '$1', $uri );
		return $portless !== $uri && in_array( $portless, $registered, true );
	}

	public static function host_allowed( $host ) {
		$host = strtolower( (string) $host );
		foreach ( NomadSA_Settings::allowed_hosts() as $allowed ) {
			if ( $host === $allowed || ( strlen( $host ) > strlen( $allowed ) && substr( $host, -strlen( $allowed ) - 1 ) === '.' . $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	private static function valid_url( $uri ) {
		if ( ! is_string( $uri ) || '' === $uri || strlen( $uri ) > 2048
			|| preg_match( '/[[:cntrl:] ]|\\\\/', $uri )
			|| $uri !== esc_url_raw( $uri, array( 'http', 'https' ) ) ) {
			return false;
		}
		$parts = wp_parse_url( $uri );
		return ! empty( $parts['scheme'] ) && ! empty( $parts['host'] )
			&& ! isset( $parts['fragment'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}

	public static function redirect_allowed( $uri ) {
		if ( ! self::valid_url( $uri ) ) {
			return false;
		}
		$parts    = wp_parse_url( $uri );
		$host     = strtolower( $parts['host'] );
		$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '[::1]' ), true );
		if ( 'https' !== $parts['scheme'] && ! ( 'http' === $parts['scheme'] && $is_local ) ) {
			return false;
		}
		return self::host_allowed( $host );
	}

	private static function client_name( $name, $default ) {
		return is_string( $name ) ? ( wp_html_excerpt( sanitize_text_field( $name ), 200 ) ?: $default ) : $default;
	}

	private static function valid_client_id( $id ) {
		return is_string( $id ) && strlen( $id ) <= 191 && (
			preg_match( '/^[A-Za-z0-9_-]+$/D', $id )
			|| ( 0 === strpos( $id, 'https://' ) && self::valid_url( $id ) )
		);
	}

	/* ---------------------------------------------------------------- Authorise */

	public static function authorize() {
		nocache_headers();
		$method = NomadSA_HTTP::method();
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			NomadSA_HTTP::oauth_error( 'invalid_request', 'Use GET or POST.', 405 );
		}
		$p = array();
		foreach ( array( 'response_type', 'client_id', 'redirect_uri', 'state', 'scope', 'code_challenge', 'code_challenge_method', 'resource' ) as $key ) {
			// The approval form posts back to this query string and verifies a request-bound nonce.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact protocol values are validated below.
			$value = wp_unslash( $_GET[ $key ] ?? '' );
			if ( ! is_string( $value ) || strlen( $value ) > 2048 || preg_match( '/[^ -~]/', $value ) ) {
				NomadSA_HTTP::oauth_error( 'invalid_request', 'Malformed authorization parameter.' );
			}
			$p[ $key ] = $value;
		}
		if ( ! self::valid_client_id( $p['client_id'] ) || ! self::redirect_allowed( $p['redirect_uri'] ) ) {
			self::page( 'Address not allowed', '<p>The app identifier or return address is invalid or is not on the allowed list.</p>' );
		}

		if ( ! NomadSA_Settings::get( 'enabled' ) ) {
			self::page( 'Connector switched off', '<p>The audit connector is switched off on this site. An administrator can switch it on under Settings, Nomad Site Audit.</p>' );
		}

		if ( ! is_user_logged_in() ) {
			$here = home_url( '/nsa-oauth/authorize' ) . '?' . http_build_query( $p );
			wp_safe_redirect( wp_login_url( $here ) );
			exit;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			self::page( 'Administrator required', '<p>Only an administrator can approve connections.</p>' );
		}
		if ( ! NomadSA_Settings::connect_window_open() ) {
			self::page( 'New connections are closed', '<p>For safety, this site only accepts a new connection for 15 minutes after an administrator allows it.</p><p>If you started this connection yourself, go to <a href="' . esc_url( admin_url( 'options-general.php?page=nomad-site-audit' ) ) . '">Settings, Nomad Site Audit</a>, click <strong>Allow a new connection</strong>, then connect again from your MCP client.</p><p class="note">If you did not start this, someone may be trying to get access to your site. Close this page.</p>' );
		}

		$client = self::get_client( $p['client_id'] );
		if ( ! $client ) {
			self::page( 'Unknown app', '<p>This app is not registered with this site.</p>' );
		}
		if ( ! self::matches_redirect( $p['redirect_uri'], $client['redirect_uris'] ) ) {
			self::page( 'Address not allowed', '<p>The return address does not match the registered app.</p>' );
		}
		if ( 'code' !== $p['response_type'] ) {
			self::redirect_error( $p, 'unsupported_response_type', 'Only the authorisation code flow is supported.' );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_-]{43}$/D', $p['code_challenge'] ) || 'S256' !== $p['code_challenge_method'] ) {
			self::redirect_error( $p, 'invalid_request', 'PKCE with S256 is required.' );
		}

		if ( '' !== $p['resource'] && NomadSA_Settings::mcp_url() !== $p['resource'] ) {
			self::redirect_error( $p, 'invalid_target', 'The connector resource does not match.' );
		}
		if ( '' !== $p['scope'] && ! in_array( 'audit', explode( ' ', $p['scope'] ), true ) ) {
			self::redirect_error( $p, 'invalid_scope', 'This connector offers the audit scope.' );
		}

		$nonce_action = 'nomadsa_authorize_' . hash( 'sha256', wp_json_encode( $p ) );
		if ( 'POST' === $method ) {
			check_admin_referer( $nonce_action );
			if ( empty( $_POST['approve'] ) ) {
				self::redirect_error( $p, 'access_denied', 'The administrator declined the connection.' );
			}
			if ( ! NomadSA_Settings::consume_connection_window() ) {
				self::redirect_error( $p, 'access_denied', 'The connection window has already been used.' );
			}
			$code = wp_generate_password( 48, false, false );
			$saved = set_transient( 'nomadsa_code_' . hash( 'sha256', $code ), array(
				'client_id'      => $p['client_id'],
				'redirect_uri'   => $p['redirect_uri'],
				'code_challenge' => $p['code_challenge'],
				'user_id'        => get_current_user_id(),
			), 10 * MINUTE_IN_SECONDS );
			if ( ! $saved ) {
				self::redirect_error( $p, 'server_error', 'Could not save the authorization code. Open a new connection window and try again.' );
			}
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
		$html .= '<form method="post">' . wp_nonce_field( $nonce_action, '_wpnonce', false, false );
		$html .= '<p><strong>Read-only audit access</strong> includes site settings, plugins, content, files, and logs used to check security, search, and site health.</p>';
		$html .= '<p class="note"><strong>Only click Allow if you started this connection yourself in the last few minutes.</strong> If someone sent you this link, click Deny.</p>';
		$html .= '<p><button class="primary" name="approve" value="1">Allow</button> <button name="deny" value="1">Deny</button></p></form>';
		$html .= '<p class="note">You can disconnect at any time under Settings, Nomad Site Audit.</p>';
		self::page( 'Connect to Nomad Site Audit', $html );
	}

	private static function redirect_to_client( $uri, array $params ) {
		$params = array_filter( $params, function ( $v ) {
			return '' !== $v && null !== $v;
		} );
		$sep = ( false === strpos( $uri, '?' ) ) ? '?' : '&';
		// The return address was checked against the registered app and the allowed list above,
		// so add its host to the safe list for this one redirect.
		$host = (string) wp_parse_url( $uri, PHP_URL_HOST );
		add_filter( 'allowed_redirect_hosts', function ( $hosts ) use ( $host ) {
			$hosts[] = $host;
			return $hosts;
		} );
		wp_safe_redirect( $uri . $sep . http_build_query( $params ) );
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

	private static function page_allowed_html() {
		return array(
			'p'      => array( 'class' => true ),
			'strong' => array(),
			'a'      => array( 'href' => true ),
			'div'    => array( 'class' => true ),
			'label'  => array(),
			'form'   => array( 'method' => true ),
			'input'  => array( 'type' => true, 'name' => true, 'value' => true, 'id' => true, 'checked' => true, 'disabled' => true ),
			'button' => array( 'class' => true, 'name' => true, 'value' => true ),
		);
	}

	private static function page( $title, $body ) {
		header( 'X-Frame-Options: DENY' );
		header( 'X-Robots-Tag: noindex' );
		wp_die(
			'<h1>' . esc_html( $title ) . '</h1>' . wp_kses( $body, self::page_allowed_html() ),
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}


	/* ---------------------------------------------------------------- Tokens */

	public static function token() {
		if ( 'POST' !== NomadSA_HTTP::method() ) {
			NomadSA_HTTP::oauth_error( 'invalid_request', 'Use POST.', 405 );
		}
		if ( ! NomadSA_Settings::get( 'enabled' ) ) {
			NomadSA_HTTP::oauth_error( 'access_denied', 'The connector is switched off on this site.', 403 );
		}
		$d = NomadSA_HTTP::body_params();
		$client_id = $d['client_id'] ?? '';
		if ( isset( $d['client_id'] ) && ! self::valid_client_id( $client_id ) ) {
			NomadSA_HTTP::oauth_error( 'invalid_client', 'The client identifier is invalid.' );
		}
		if ( isset( $d['resource'] ) && NomadSA_Settings::mcp_url() !== $d['resource'] ) {
			NomadSA_HTTP::oauth_error( 'invalid_target', 'The connector resource does not match.' );
		}

		if ( 'authorization_code' === ( $d['grant_type'] ?? '' ) ) {
			$code = $d['code'] ?? null;
			$verifier = $d['code_verifier'] ?? null;
			if ( ! is_string( $code ) || ! preg_match( '/^[A-Za-z0-9]{48}$/D', $code )
				|| ! is_string( $verifier ) || ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $verifier )
				|| ! self::redirect_allowed( $d['redirect_uri'] ?? null ) ) {
				NomadSA_HTTP::oauth_error( 'invalid_grant', 'The authorization code, redirect URI or PKCE verifier is invalid.' );
			}
			$key = 'nomadsa_code_' . hash( 'sha256', $code );
			$data = get_transient( $key );
			if ( ! is_array( $data ) || $client_id !== $data['client_id'] || $d['redirect_uri'] !== $data['redirect_uri'] ) {
				NomadSA_HTTP::oauth_error( 'invalid_grant', 'The authorization code is expired or does not match the client.' );
			}
			$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
			if ( ! hash_equals( $data['code_challenge'], $challenge ) || ! self::is_auditor( $data['user_id'] )
				|| ! delete_transient( $key ) ) {
				NomadSA_HTTP::oauth_error( 'invalid_grant', 'The code is no longer valid for this request.' );
			}
			NomadSA_HTTP::json( self::issue_tokens( $data['client_id'], (int) $data['user_id'] ) );
		}

		if ( 'refresh_token' === ( $d['grant_type'] ?? '' ) ) {
			$row = self::find_token( $d['refresh_token'] ?? null, 'refresh' );
			if ( ! $row || ( '' !== $client_id && $client_id !== $row['client_id'] ) || ! self::is_auditor( $row['user_id'] ) ) {
				NomadSA_HTTP::oauth_error( 'invalid_grant', 'The refresh token is invalid or has expired.' );
			}
			global $wpdb;
			$rotated = $wpdb->update( NomadSA_DB::table( 'tokens' ), array( 'revoked' => 1 ), array( 'id' => $row['id'], 'revoked' => 0 ) );
			if ( 1 !== $rotated ) {
				NomadSA_HTTP::oauth_error( 'invalid_grant', 'The refresh token has already been used.' );
			}
			NomadSA_HTTP::json( self::issue_tokens( $row['client_id'], (int) $row['user_id'] ) );
		}

		NomadSA_HTTP::oauth_error( 'unsupported_grant_type', 'Unsupported grant type.' );
	}

	private static function issue_tokens( $client_id, $user_id ) {
		global $wpdb;
		$access  = 'nomadsa_at_' . wp_generate_password( 48, false, false );
		$refresh = 'nomadsa_rt_' . wp_generate_password( 48, false, false );
		$life    = (int) NomadSA_Settings::get( 'token_lifetime' );
		foreach ( array( array( $access, 'access', $life ), array( $refresh, 'refresh', (int) NomadSA_Settings::get( 'refresh_lifetime' ) ) ) as $t ) {
			$wpdb->insert( NomadSA_DB::table( 'tokens' ), array(
				'token_hash' => hash( 'sha256', $t[0] ),
				'kind'       => $t[1],
				'client_id'  => $client_id,
				'user_id'    => $user_id,
				'scope'      => 'audit',
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $t[2] ),
				'created_at' => NomadSA_DB::now(),
			) );
		}
		// Housekeeping: remove long expired tokens.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at < %s', NomadSA_DB::table( 'tokens' ), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		return array(
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => $life,
			'refresh_token' => $refresh,
			'scope'         => 'audit',
		);
	}

	private static function find_token( $token, $kind ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_~-]{1,128}$/D', $token ) ) {
			return null;
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM %i WHERE token_hash = %s AND kind = %s AND revoked = 0 AND expires_at > %s',
			NomadSA_DB::table( 'tokens' ), hash( 'sha256', $token ), $kind, NomadSA_DB::now()
		), ARRAY_A );
		return $row ?: null;
	}

	/** Validates the credential without setting a WordPress login or current user. */
	public static function authenticate_request() {
		if ( ! NomadSA_Settings::get( 'enabled' ) ) {
			return false;
		}
		$row = self::find_token( NomadSA_HTTP::bearer_token(), 'access' );
		if ( ! $row || ! self::is_auditor( $row['user_id'] )
			|| ! in_array( 'audit', explode( ' ', $row['scope'] ), true ) ) {
			return false;
		}
		self::$current_client  = $row['client_id'];
		self::$current_user_id = (int) $row['user_id'];

		if ( empty( $row['last_used_at'] ) || strtotime( $row['last_used_at'] . ' UTC' ) < time() - 300 ) {
			global $wpdb;
			$wpdb->update( NomadSA_DB::table( 'tokens' ), array( 'last_used_at' => NomadSA_DB::now() ), array( 'id' => $row['id'] ) );
		}
		return true;
	}

	private static function is_auditor( $user_id ) {
		$user = get_user_by( 'id', (int) $user_id );
		return $user && user_can( $user, 'manage_options' );
	}

	/** Lists apps with active tokens for the settings page. */
	public static function connections() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT client_id, user_id, MIN(created_at) AS first_seen, MAX(COALESCE(last_used_at, created_at)) AS last_used
			 FROM %i WHERE revoked = 0 AND expires_at > %s GROUP BY client_id, user_id ORDER BY last_used DESC',
			NomadSA_DB::table( 'tokens' ), NomadSA_DB::now()
		), ARRAY_A );
	}

	public static function revoke_client( $client_id ) {
		global $wpdb;
		$wpdb->update( NomadSA_DB::table( 'tokens' ), array( 'revoked' => 1 ), array( 'client_id' => $client_id ) );
	}

	public static function revoke_all() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET revoked = 1', NomadSA_DB::table( 'tokens' ) ) );
	}
}
