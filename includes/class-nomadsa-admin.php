<?php
defined( 'ABSPATH' ) || exit;

// Queries here use the plugin's own tables or read only counts, which WordPress has no API or cache for.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Settings, Nomad Site Audit: connection address, permissions, connected apps and activity.
 */
class NomadSA_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_nomadsa_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_nomadsa_revoke', array( __CLASS__, 'revoke' ) );
		add_action( 'admin_post_nomadsa_open_window', array( __CLASS__, 'open_window' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NOMADSA_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=nomad-site-audit' ) ) . '">Settings</a>' );
			return $links;
		} );
	}

	public static function menu() {
		add_options_page( 'Nomad Site Audit', 'Nomad Site Audit', 'manage_options', 'nomad-site-audit', array( __CLASS__, 'render' ) );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nomadsa_save' );
		$hosts = sanitize_text_field( wp_unslash( $_POST['allowed_hosts'] ?? '' ) );
		$fixes = ! empty( $_POST['allow_fixes'] );
		NomadSA_Settings::update( array(
			'enabled'       => ! empty( $_POST['enabled'] ),
			'allow_fixes'   => $fixes,
			'fixes_until'   => $fixes ? time() + DAY_IN_SECONDS : 0,
			'allowed_hosts' => '' !== trim( $hosts ) ? $hosts : NomadSA_Settings::defaults()['allowed_hosts'],
			'wpscan_token'  => sanitize_text_field( wp_unslash( $_POST['wpscan_token'] ?? '' ) ),
		) );
		wp_safe_redirect( admin_url( 'options-general.php?page=nomad-site-audit&saved=1' ) );
		exit;
	}

	public static function open_window() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nomadsa_open_window' );
		NomadSA_Settings::update( array( 'connect_until' => time() + 15 * MINUTE_IN_SECONDS ) );
		NomadSA_DB::log( 'connection_window', 'admin', 'opened', 'New connections allowed for 15 minutes' );
		wp_safe_redirect( admin_url( 'options-general.php?page=nomad-site-audit&window=1' ) );
		exit;
	}

	public static function revoke() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'nomadsa_revoke' );
		$client = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
		if ( 'all' === $client ) {
			NomadSA_OAuth::revoke_all();
		} elseif ( $client ) {
			NomadSA_OAuth::revoke_client( $client );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=nomad-site-audit&revoked=1' ) );
		exit;
	}

	public static function render() {
		global $wpdb;
		$s     = NomadSA_Settings::get();
		$conns = NomadSA_OAuth::connections();
		$log   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 100', NomadSA_DB::table( 'log' ) ), ARRAY_A );
		$perma = (bool) get_option( 'permalink_structure' );
		// Read only flags set by this plugin's own redirects after a nonce checked save.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1>Nomad Site Audit</h1>
			<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Settings saved.</p></div><?php endif; ?>
			<?php if ( isset( $_GET['revoked'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Access removed.</p></div><?php endif; ?>
			<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( ! $perma ) : ?><div class="notice notice-error"><p>The connector needs pretty permalinks. Choose any option other than Plain under Settings, Permalinks.</p></div><?php endif; ?>

			<h2>Connect Claude</h2>
			<p>1. Click <strong>Allow a new connection</strong> below. For safety, new connections are only accepted for the next 15 minutes and only once.<br>2. In Claude, open Settings, Connectors, Add custom connector, and paste this address.<br>3. Log in here when asked and click Allow.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 12px">
				<?php wp_nonce_field( 'nomadsa_open_window' ); ?>
				<input type="hidden" name="action" value="nomadsa_open_window">
				<?php if ( NomadSA_Settings::connect_window_open() ) : ?>
					<span class="dashicons dashicons-unlock" style="color:#00a32a"></span> New connections allowed for <?php echo esc_html( human_time_diff( time(), (int) $s['connect_until'] ) ); ?>.
				<?php else : ?>
					<button class="button button-primary">Allow a new connection</button> <span class="description">Existing connections keep working without this.</span>
				<?php endif; ?>
			</form>
			<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( NomadSA_Settings::mcp_url() ); ?>" onclick="this.select()"></p>
			<?php
			NomadSA_WellKnown::write();
			list( $ready, $detail ) = NomadSA_WellKnown::self_test();
			if ( true === $ready ) {
				echo '<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> Ready to connect. ' . esc_html( $detail ) . '</p>';
			} elseif ( false === $ready ) {
				echo '<div class="notice notice-warning inline"><p><strong>Not reachable yet.</strong> ' . esc_html( $detail ) . '</p></div>';
			} else {
				echo '<p class="description">' . esc_html( $detail ) . '</p>';
			}
			?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'nomadsa_save' ); ?>
				<input type="hidden" name="action" value="nomadsa_save">
				<table class="form-table" role="presentation">
					<tr><th scope="row">Connector</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> Allow connections</label><p class="description">Switch off to block all access straight away.</p></td></tr>
					<tr><th scope="row">Fixes</th><td><label><input type="checkbox" name="allow_fixes" value="1" <?php checked( NomadSA_Settings::fixes_active() ); ?>> Allow Claude to apply fixes for the next 24 hours</label>
						<?php if ( NomadSA_Settings::fixes_active() ) : ?>
							<p><strong>Fixes allowed for another <?php echo esc_html( human_time_diff( time(), (int) $s['fixes_until'] ) ); ?>.</strong> Save with this ticked to restart the 24 hours.</p>
						<?php endif; ?>
						<p class="description">Updates, security settings, search titles, alt text, content, theme files, clean up and database tidying. Every change is previewed first, logged and backed up where possible. Fixes switch off by themselves after 24 hours so a forgotten switch never stays open. Connections made while fixes were off need to reconnect to gain fix access.</p></td></tr>
					<tr><th scope="row"><label for="nomadsa-hosts">Allowed apps</label></th><td><input id="nomadsa-hosts" type="text" class="regular-text" name="allowed_hosts" value="<?php echo esc_attr( $s['allowed_hosts'] ); ?>"><p class="description">Domains that may receive a connection, comma separated. Keep this to Claude to stop anyone tricking an administrator into connecting another app.</p></td></tr>
					<tr><th scope="row"><label for="nomadsa-wpscan">WPScan API token</label></th><td><input id="nomadsa-wpscan" type="password" class="regular-text" name="wpscan_token" value="<?php echo esc_attr( $s['wpscan_token'] ); ?>" autocomplete="off"><p class="description">Optional. Adds known vulnerability checks for plugins. Free token from wpscan.com.</p></td></tr>
				</table>
				<?php submit_button( 'Save settings' ); ?>
			</form>

			<h2>Connected apps</h2>
			<?php if ( ! $conns ) : ?>
				<p>No active connections.</p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th>App</th><th>Approved by</th><th>Access</th><th>Last used (UTC)</th><th></th></tr></thead><tbody>
				<?php foreach ( $conns as $c ) :
					$client = NomadSA_OAuth::get_client( $c['client_id'] );
					$user   = get_userdata( (int) $c['user_id'] );
					?>
					<tr>
						<td><?php echo esc_html( $client['name'] ?? $c['client_id'] ); ?></td>
						<td><?php echo esc_html( $user ? $user->user_login : '?' ); ?></td>
						<td><?php echo esc_html( false !== strpos( $c['scope'], 'fix' ) ? 'Audit and fix' : 'Audit only' ); ?></td>
						<td><?php echo esc_html( $c['last_used'] ); ?></td>
						<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'nomadsa_revoke' ); ?><input type="hidden" name="action" value="nomadsa_revoke"><input type="hidden" name="client_id" value="<?php echo esc_attr( $c['client_id'] ); ?>"><button class="button">Disconnect</button></form></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px"><?php wp_nonce_field( 'nomadsa_revoke' ); ?><input type="hidden" name="action" value="nomadsa_revoke"><input type="hidden" name="client_id" value="all"><button class="button button-link-delete">Disconnect everything</button></form>
			<?php endif; ?>

			<h2>Activity</h2>
			<?php if ( ! $log ) : ?>
				<p>Nothing yet.</p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th>Time (UTC)</th><th>Tool</th><th>Type</th><th>Result</th><th>Summary</th></tr></thead><tbody>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td><code><?php echo esc_html( $row['tool'] ); ?></code></td>
						<td><?php echo esc_html( $row['kind'] ); ?></td>
						<td><?php echo 'applied' === $row['status'] ? '<strong>applied</strong>' : esc_html( $row['status'] ); ?></td>
						<td><?php echo esc_html( mb_substr( $row['summary'], 0, 160 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
}
