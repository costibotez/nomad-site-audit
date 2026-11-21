<?php
defined( 'ABSPATH' ) || exit;

class NomadSA_Audit_Admin {

	const SLUG = 'nomadsa-audit';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'admin_post_nomadsa_audit_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nomadsa_audit_report', array( __CLASS__, 'report' ) );
	}

	public static function menu() {
		add_management_page( 'Nomad Site Audit', 'Nomad Site Audit', 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function styles( $page ) {
		if ( 'tools_page_' . self::SLUG === $page ) {
			wp_enqueue_style( 'nomadsa-audit', plugins_url( 'assets/audit-admin.css', NOMADSA_FILE ), array(), NOMADSA_VERSION );
		}
	}

	public static function url( array $args = array() ) {
		return add_query_arg( array( 'page' => self::SLUG ) + $args, admin_url( 'tools.php' ) );
	}

	private static function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage audits.', 'nomad-site-audit' ), '', array( 'response' => 403 ) );
		}
	}

	public static function handle() {
		self::authorize();
		check_admin_referer( 'nomadsa_audit_action' );
		$command = sanitize_key( wp_unslash( $_POST['command'] ?? '' ) );
		$id = absint( sanitize_text_field( wp_unslash( $_POST['run'] ?? '' ) ) );
		$notice = __( 'The requested action is unavailable.', 'nomad-site-audit' );
		$anchor = '#nomadsa-history';

		switch ( $command ) {
			case 'audit':
				$result = NomadSA_Audit_Schedule::run_audit( 'manual' );
				$notice = is_wp_error( $result ) ? $result : __( 'The audit snapshot was saved.', 'nomad-site-audit' );
				$id = is_wp_error( $result ) ? 0 : $result;
				break;
			case 'performance':
				$result = NomadSA_Audit_Schedule::run_performance();
				$notice = is_wp_error( $result ) ? $result : __( 'The performance sample was saved.', 'nomad-site-audit' );
				$anchor = '#nomadsa-performance';
				break;
			case 'save':
				$notice = self::save_config();
				$anchor = '#nomadsa-monitoring-settings';
				break;
			case 'pin':
				if ( NomadSA_Audit_History::audit( $id ) ) {
					update_option( 'nomadsa_audit_baseline', $id, false );
					$notice = __( 'This audit is now the comparison baseline.', 'nomad-site-audit' );
				}
				break;
			case 'unpin':
				delete_option( 'nomadsa_audit_baseline' );
				$notice = __( 'Future audits will compare with the preceding snapshot.', 'nomad-site-audit' );
				break;
			case 'progress':
			case 'recheck':
				$finding_id = sanitize_text_field( wp_unslash( $_POST['finding'] ?? '' ) );
				$notice = self::update_checklist( $id, $finding_id, $command );
				$anchor = '#nomadsa-checklist';
				$id = 0;
				break;
		}

		set_transient( 'nomadsa_audit_notice_' . get_current_user_id(), array(
			'message' => is_wp_error( $notice ) ? $notice->get_error_message() : $notice,
			'error' => is_wp_error( $notice ),
		), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( $id ? array( 'run' => $id ) : array() ) . $anchor );
		exit;
	}

	private static function save_config() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- handle() verifies the nonce before dispatching.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each URL is normalized and validated before storage.
		$pages = wp_unslash( $_POST['pages'] ?? '' );
		$pages = is_string( $pages ) && strlen( $pages ) <= 4000 ? NomadSA_Performance::normalize_urls( $pages ) : new WP_Error( 'nomadsa_pages', __( 'Enter up to five page URLs.', 'nomad-site-audit' ) );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}
		$weekly = ! empty( $_POST['weekly'] );
		$email = sanitize_email( sanitize_text_field( wp_unslash( $_POST['recipient'] ?? '' ) ) );
		if ( $weekly && ! is_email( $email ) ) {
			return new WP_Error( 'nomadsa_recipient', __( 'Enter a valid email address to enable the weekly digest.', 'nomad-site-audit' ) );
		}

		$old = NomadSA_Audit_Schedule::config();
		$key = sanitize_text_field( wp_unslash( $_POST['crux_key'] ?? '' ) );
		if ( '' !== $key && ! preg_match( '/^[A-Za-z0-9_-]{10,100}$/D', $key ) ) {
			return new WP_Error( 'nomadsa_crux', __( 'Enter a valid Chrome UX Report API key.', 'nomad-site-audit' ) );
		}
		$data = array(
			'weekly' => $weekly,
			'recipient' => $email,
			'pages' => $pages,
			'crux_key' => ! empty( $_POST['remove_crux_key'] ) ? '' : ( $key ?: $old['crux_key'] ),
			'report_name' => wp_html_excerpt( sanitize_text_field( wp_unslash( $_POST['report_name'] ?? '' ) ), 120 ),
			'accent' => sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['accent'] ?? '' ) ) ) ?: '#17546b',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! update_option( 'nomadsa_audit_config', $data, false ) && $data !== NomadSA_Audit_Schedule::config() ) {
			return new WP_Error( 'nomadsa_config', __( 'The settings could not be saved.', 'nomad-site-audit' ) );
		}
		$result = NomadSA_Audit_Schedule::sync();
		return is_wp_error( $result ) ? $result : __( 'Audit settings saved.', 'nomad-site-audit' );
	}

	private static function update_checklist( $id, $finding_id, $command ) {
		$latest = NomadSA_Audit_History::recent()[0] ?? null;
		$audit = $latest && (int) $latest['id'] === $id ? NomadSA_Audit_History::audit( $id ) : null;
		$finding = $audit ? ( NomadSA_Audit_History::findings( $audit['payload'] )[ $finding_id ] ?? null ) : null;
		if ( ! $finding ) {
			return new WP_Error( 'nomadsa_finding', __( 'Open the latest audit before updating this finding.', 'nomad-site-audit' ) );
		}
		if ( 'progress' === $command ) {
			return NomadSA_Audit_History::set_state( $finding_id, 'in_progress', $id )
				? __( 'Finding marked in progress.', 'nomad-site-audit' )
				: new WP_Error( 'nomadsa_save_state', __( 'The checklist status could not be saved.', 'nomad-site-audit' ) );
		}
		$status = NomadSA_Audit_Schedule::recheck( $finding_id, $id, $finding );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		if ( 'unverified' === $status ) {
			return new WP_Error( 'nomadsa_recheck', __( 'The recheck was inconclusive. The finding remains on your checklist.', 'nomad-site-audit' ) );
		}
		return 'verified' === $status
			? __( 'The recheck verified that the finding is resolved.', 'nomad-site-audit' )
			: __( 'The recheck still found the issue.', 'nomad-site-audit' );
	}

	private static function comparison( array $audit ) {
		$id = (int) $audit['id'];
		$pinned = NomadSA_Audit_History::baseline();
		$baseline = $pinned && $pinned < $id ? NomadSA_Audit_History::audit( $pinned ) : null;
		$baseline = $baseline ?: NomadSA_Audit_History::previous( $id );
		return array( $baseline, $baseline ? NomadSA_Audit_History::compare( $audit['payload'], $baseline['payload'] ) : array() );
	}

	public static function report() {
		self::authorize();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- used to verify the report-specific nonce below.
		$id = absint( sanitize_text_field( wp_unslash( $_GET['run'] ?? '' ) ) );
		check_admin_referer( 'nomadsa_audit_report_' . $id );
		$audit = NomadSA_Audit_History::audit( $id );
		if ( ! $audit ) {
			wp_die( esc_html__( 'This audit snapshot is unavailable.', 'nomad-site-audit' ), '', array( 'response' => 404 ) );
		}
		$config = NomadSA_Audit_Schedule::config();
		$brand = $config['report_name'] ?: get_bloginfo( 'name' );
		$accent = sanitize_hex_color( $config['accent'] ) ?: '#17546b';
		$findings = NomadSA_Audit_History::findings( $audit['payload'] );
		list( $baseline, $changes ) = self::comparison( $audit );

		wp_register_style( 'nomadsa-audit-report', false, array(), NOMADSA_VERSION );
		$stylesheet = file_get_contents( NOMADSA_DIR . 'assets/audit-report.css' );
		wp_add_inline_style( 'nomadsa-audit-report', ':root{--nomadsa-accent:' . $accent . '}' . $stylesheet );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: DENY' );
		header( 'X-Robots-Tag: noindex' );
		if ( empty( $_GET['preview'] ) ) {
			header( 'Content-Disposition: attachment; filename="nomad-site-audit-' . $id . '.html"' );
		}
		require NOMADSA_DIR . 'includes/views/audit-report.php';
		exit;
	}

	public static function date( $utc ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $utc . ' UTC' ) );
	}

	public static function group_labels() {
		return array(
			'new' => __( 'New', 'nomad-site-audit' ),
			'worsened' => __( 'Worsened', 'nomad-site-audit' ),
			'recurring' => __( 'Recurring', 'nomad-site-audit' ),
			'resolved' => __( 'Resolved', 'nomad-site-audit' ),
			'unverified' => __( 'Not rechecked', 'nomad-site-audit' ),
		);
	}

	public static function priority( $status ) {
		return 'fail' === $status ? __( 'High priority', 'nomad-site-audit' ) : __( 'Needs attention', 'nomad-site-audit' );
	}

	private static function action_form( $command, $label, $id = 0, $finding = '', $primary = false ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php self::action_fields( $command ); ?>
			<input type="hidden" name="run" value="<?php echo esc_attr( $id ); ?>">
			<?php if ( '' !== $finding ) : ?>
				<input type="hidden" name="finding" value="<?php echo esc_attr( $finding ); ?>">
			<?php endif; ?>
			<button type="submit" class="<?php echo $primary ? 'button button-primary' : 'button'; ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	private static function action_fields( $command ) {
		?>
		<input type="hidden" name="action" value="nomadsa_audit_action">
		<input type="hidden" name="command" value="<?php echo esc_attr( $command ); ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'nomadsa_audit_action' ) ); ?>">
		<?php
	}

	public static function render() {
		self::authorize();
		$config = NomadSA_Audit_Schedule::config();
		$history = NomadSA_Audit_History::recent();
		$latest = isset( $history[0] ) ? (int) $history[0]['id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only history selection.
		$selected = absint( sanitize_text_field( wp_unslash( $_GET['run'] ?? '' ) ) ) ?: $latest;
		$audit = $selected ? NomadSA_Audit_History::audit( $selected ) : null;
		$notice_key = 'nomadsa_audit_notice_' . get_current_user_id();
		$notice = get_transient( $notice_key );
		if ( $notice ) {
			delete_transient( $notice_key );
		}
		?>
		<div class="wrap nomadsa-audit">
			<h1>Nomad Site Audit</h1>
			<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=nomad-site-audit' ) ); ?>"><?php esc_html_e( 'Connector settings', 'nomad-site-audit' ); ?></a></p>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo $notice['error'] ? 'notice-warning' : 'notice-success'; ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>
			<nav aria-label="<?php esc_attr_e( 'Audit sections', 'nomad-site-audit' ); ?>" class="nomadsa-actions">
				<a href="#nomadsa-history"><?php esc_html_e( 'Audit history', 'nomad-site-audit' ); ?></a>
				<a href="#nomadsa-checklist"><?php esc_html_e( 'Remediation checklist', 'nomad-site-audit' ); ?></a>
				<a href="#nomadsa-performance"><?php esc_html_e( 'Performance', 'nomad-site-audit' ); ?></a>
				<a href="#nomadsa-monitoring-settings"><?php esc_html_e( 'Audit settings', 'nomad-site-audit' ); ?></a>
			</nav>
			<?php self::render_history( $history, $audit, $selected ); ?>
			<?php self::render_checklist( $latest ); ?>
			<?php self::render_performance( $config ); ?>
			<?php self::render_settings( $config ); ?>
		</div>
		<?php
	}

	private static function render_history( array $history, $audit, $selected ) {
		$pinned = NomadSA_Audit_History::baseline();
		?>
		<section class="nomadsa-panel" id="nomadsa-history">
			<h2><?php esc_html_e( 'Audit history', 'nomad-site-audit' ); ?></h2>
			<p><?php esc_html_e( 'Snapshots cover security, database health, site-wide SEO, and up to 100 of your newest published posts and pages.', 'nomad-site-audit' ); ?></p>
			<div class="nomadsa-actions">
				<?php self::action_form( 'audit', __( 'Run audit', 'nomad-site-audit' ), 0, '', true ); ?>
				<?php if ( $history ) : ?>
					<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
						<label for="nomadsa-run"><?php esc_html_e( 'Snapshot', 'nomad-site-audit' ); ?></label>
						<select name="run" id="nomadsa-run">
							<?php foreach ( $history as $run ) : ?>
								<option value="<?php echo esc_attr( $run['id'] ); ?>" <?php selected( $selected, $run['id'] ); ?>><?php echo esc_html( self::date( $run['created_at'] ) . ' · #' . $run['id'] . ( 'weekly' === $run['source'] ? ' · ' . __( 'Weekly', 'nomad-site-audit' ) : '' ) . ( $pinned === (int) $run['id'] ? ' · ' . __( 'Baseline', 'nomad-site-audit' ) : '' ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button class="button"><?php esc_html_e( 'View', 'nomad-site-audit' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php if ( ! $audit ) : ?>
				<p><?php echo $selected ? esc_html__( 'That snapshot is unavailable. Select another audit from history.', 'nomad-site-audit' ) : esc_html__( 'Run your first audit to start a history.', 'nomad-site-audit' ); ?></p>
			<?php else : ?>
				<?php self::render_snapshot( $audit, $pinned ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_snapshot( array $audit, $pinned ) {
		list( $baseline, $changes ) = self::comparison( $audit );
		$findings = NomadSA_Audit_History::findings( $audit['payload'] );
		$args = array( 'action' => 'nomadsa_audit_report', 'run' => $audit['id'], '_wpnonce' => wp_create_nonce( 'nomadsa_audit_report_' . $audit['id'] ) );
		$download = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		?>
		<h3><?php echo esc_html( self::date( $audit['created_at'] ) . ' · #' . $audit['id'] ); ?></h3>
		<div class="nomadsa-actions">
			<a class="button" href="<?php echo esc_url( $download ); ?>"><?php esc_html_e( 'Download HTML report', 'nomad-site-audit' ); ?></a>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'preview', '1', $download ) ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Preview report in a new tab', 'nomad-site-audit' ); ?>"><?php esc_html_e( 'Preview report', 'nomad-site-audit' ); ?></a>
			<?php
			if ( $pinned === (int) $audit['id'] ) {
				self::action_form( 'unpin', __( 'Clear baseline', 'nomad-site-audit' ), $audit['id'] );
			} else {
				self::action_form( 'pin', __( 'Use as baseline', 'nomad-site-audit' ), $audit['id'] );
			}
			?>
		</div>
		<?php if ( $baseline ) : ?>
			<p>
				<?php
				/* translators: %s: date and time of comparison audit. */
				echo esc_html( sprintf( __( 'Compared with %s.', 'nomad-site-audit' ), self::date( $baseline['created_at'] ) ) );
				?>
			</p>
			<div class="nomadsa-metrics">
				<?php foreach ( self::group_labels() as $key => $label ) : ?>
					<details <?php echo empty( $changes[ $key ] ) ? '' : 'class="nomadsa-has-findings"'; ?>>
						<summary><?php echo esc_html( $label ); ?> <strong><?php echo esc_html( count( $changes[ $key ] ) ); ?></strong></summary>
						<?php self::finding_titles( $changes[ $key ] ); ?>
					</details>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'A second snapshot will show what changed.', 'nomad-site-audit' ); ?></p>
		<?php endif; ?>
		<?php if ( $audit['payload']['unavailable'] ) : ?>
			<p>
				<?php
				/* translators: %s: list of audit sections. */
				echo esc_html( sprintf( __( 'Sections unavailable in this run: %s.', 'nomad-site-audit' ), implode( ', ', $audit['payload']['unavailable'] ) ) );
				?>
			</p>
		<?php endif; ?>
		<details class="nomadsa-findings"><summary>
			<?php
			/* translators: %d: number of findings. */
			echo esc_html( sprintf( _n( '%d finding in this snapshot', '%d findings in this snapshot', count( $findings ), 'nomad-site-audit' ), count( $findings ) ) );
			?>
		</summary><?php self::finding_titles( $findings ); ?></details>
		<?php
	}

	private static function finding_titles( array $findings ) {
		if ( ! $findings ) {
			echo '<p>' . esc_html__( 'None.', 'nomad-site-audit' ) . '</p>';
			return;
		}
		echo '<ul class="ul-disc">';
		foreach ( $findings as $finding ) {
			echo '<li>' . esc_html( $finding['title'] . ( $finding['evidence'] ? ' · ' . $finding['evidence'] : '' ) ) . '</li>';
		}
		echo '</ul>';
	}

	private static function render_checklist( $latest ) {
		$audit = $latest ? NomadSA_Audit_History::audit( $latest ) : null;
		$findings = $audit ? NomadSA_Audit_History::findings( $audit['payload'] ) : array();
		$states = $findings ? NomadSA_Audit_History::checklist() : array();
		?>
		<section class="nomadsa-panel" id="nomadsa-checklist">
			<h2><?php esc_html_e( 'Remediation checklist', 'nomad-site-audit' ); ?></h2>
			<?php if ( ! $findings ) : ?>
				<p><?php echo $audit ? esc_html__( 'The latest snapshot has no findings to review.', 'nomad-site-audit' ) : esc_html__( 'Run an audit to create your checklist.', 'nomad-site-audit' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Apply changes in WordPress or with your host, then recheck the affected audit section.', 'nomad-site-audit' ); ?></p>
				<div class="nomadsa-table-scroll">
					<table class="widefat striped">
						<thead><tr><th scope="col"><?php esc_html_e( 'Finding', 'nomad-site-audit' ); ?></th><th scope="col"><?php esc_html_e( 'Next step', 'nomad-site-audit' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'nomad-site-audit' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $findings as $id => $finding ) :
							list( $url, $guidance ) = NomadSA_Audit_History::guidance( $finding );
							$saved = $states[ $id ] ?? null;
							$state = $saved->state ?? 'open';
							if ( 'verified' === $state && (int) $saved->audit_id !== $latest ) {
								$state = 'open';
							}
							$labels = array( 'open' => __( 'Open', 'nomad-site-audit' ), 'in_progress' => __( 'In progress', 'nomad-site-audit' ), 'verified' => __( 'Verified resolved', 'nomad-site-audit' ) );
							?>
							<tr>
								<td><strong><?php echo esc_html( $finding['title'] ); ?></strong><br>
									<span class="description"><?php echo esc_html( self::priority( $finding['status'] ) . ( $finding['evidence'] ? ' · ' . $finding['evidence'] : '' ) ); ?></span>
								</td>
								<td><?php echo esc_html( $guidance ); ?><br><a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open WordPress screen', 'nomad-site-audit' ); ?></a></td>
								<td><strong><?php echo esc_html( $labels[ $state ] ?? $labels['open'] ); ?></strong>
									<div class="nomadsa-actions">
										<?php if ( 'open' === $state ) { self::action_form( 'progress', __( 'Start', 'nomad-site-audit' ), $latest, $id ); } ?>
										<?php self::action_form( 'recheck', __( 'Recheck', 'nomad-site-audit' ), $latest, $id ); ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_performance( array $config ) {
		?>
		<section class="nomadsa-panel" id="nomadsa-performance">
			<h2><?php esc_html_e( 'Performance monitoring', 'nomad-site-audit' ); ?></h2>
			<?php if ( ! $config['pages'] ) : ?>
				<p><?php esc_html_e( 'Add public page URLs in Audit settings to start measuring them daily.', 'nomad-site-audit' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Response time measures the complete request from your WordPress server. Core Web Vitals use mobile field data from the Chrome UX Report when available.', 'nomad-site-audit' ); ?></p>
				<?php self::action_form( 'performance', __( 'Measure now', 'nomad-site-audit' ) ); ?>
				<?php foreach ( NomadSA_Performance::history( $config['pages'] ) as $url => $samples ) : ?>
					<h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a></h3>
					<?php if ( $samples ) : ?>
						<?php self::sample_table( $samples ); ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Waiting for a first sample.', 'nomad-site-audit' ); ?></p>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function sample_table( array $samples ) {
		$fields = array(
			'disabled' => __( 'API key required', 'nomad-site-audit' ),
			'https_required' => __( 'Requires HTTPS', 'nomad-site-audit' ),
			'no_data' => __( 'No field data for this URL', 'nomad-site-audit' ),
			'error' => __( 'Field data unavailable', 'nomad-site-audit' ),
		);
		?>
		<div class="nomadsa-table-scroll">
			<table class="widefat striped">
				<thead><tr><th scope="col"><?php esc_html_e( 'Sample', 'nomad-site-audit' ); ?></th><th scope="col">HTTP</th><th scope="col"><?php esc_html_e( 'Response (ms)', 'nomad-site-audit' ); ?></th><th scope="col"><?php esc_html_e( 'HTML (KB)', 'nomad-site-audit' ); ?></th><th scope="col"><?php esc_html_e( 'Mobile Core Web Vitals (p75)', 'nomad-site-audit' ); ?></th><th scope="col"><?php esc_html_e( 'Regression', 'nomad-site-audit' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $samples as $index => $row ) :
					$metrics = $row['vitals']['metrics'];
					$flags = isset( $samples[ $index + 1 ] ) ? NomadSA_Performance::regressions( $row, $samples[ $index + 1 ] ) : array();
					?>
					<tr>
						<td><?php echo esc_html( self::date( $row['created_at'] ) ); ?></td>
						<td><?php echo esc_html( $row['status'] ?: __( 'No response', 'nomad-site-audit' ) ); ?></td>
						<td><?php echo esc_html( $row['response_ms'] ?? '—' ); ?></td>
						<td><?php echo esc_html( ( $row['size_capped'] ? '≥' : '' ) . ( $row['html_kb'] ?? '—' ) ); ?></td>
						<td>
							<?php if ( $metrics ) : ?>
								<?php echo esc_html( 'LCP ' . ( isset( $metrics['lcp'] ) ? $metrics['lcp'] . ' ms' : '—' ) . ' · INP ' . ( isset( $metrics['inp'] ) ? $metrics['inp'] . ' ms' : '—' ) . ' · CLS ' . ( $metrics['cls'] ?? '—' ) ); ?>
							<?php else : ?>
								<?php echo esc_html( $fields[ $row['vitals']['status'] ] ?? '—' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $flags ? implode( ', ', $flags ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_settings( array $config ) {
		$weekly = wp_next_scheduled( NomadSA_Audit_Schedule::WEEKLY );
		$daily = wp_next_scheduled( NomadSA_Audit_Schedule::DAILY );
		$delivery = get_option( 'nomadsa_audit_delivery', array() );
		$delivery_labels = array(
			'accepted' => __( 'Digest accepted by WordPress mail.', 'nomad-site-audit' ),
			'quiet' => __( 'The latest audit had no alerts to send.', 'nomad-site-audit' ),
			'audit_failed' => __( 'The scheduled audit could not finish.', 'nomad-site-audit' ),
			'mail_failed' => __( 'WordPress could not send the last digest.', 'nomad-site-audit' ),
		);
		?>
		<section class="nomadsa-panel" id="nomadsa-monitoring-settings">
			<h2><?php esc_html_e( 'Audit settings', 'nomad-site-audit' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php self::action_fields( 'save' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Weekly digest', 'nomad-site-audit' ); ?></th><td>
						<label><input type="checkbox" name="weekly" value="1" <?php checked( $config['weekly'] ); ?>> <?php esc_html_e( 'Run a weekly audit and email high-priority findings and regressions', 'nomad-site-audit' ); ?></label>
						<?php if ( $weekly ) : ?>
							<p class="description"><?php
							/* translators: %s: next scheduled run in the WordPress site's timezone. */
							echo esc_html( sprintf( __( 'Next run: %s', 'nomad-site-audit' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $weekly ) ) );
							?></p>
						<?php endif; ?>
						<?php if ( isset( $delivery_labels[ $delivery['status'] ?? '' ] ) ) : ?>
							<p class="description"><?php echo esc_html( self::date( $delivery['date'] ) . ' · ' . $delivery_labels[ $delivery['status'] ] ); ?></p>
						<?php endif; ?>
					</td></tr>
					<tr><th scope="row"><label for="nomadsa-recipient"><?php esc_html_e( 'Digest recipient', 'nomad-site-audit' ); ?></label></th><td><input type="email" id="nomadsa-recipient" name="recipient" value="<?php echo esc_attr( $config['recipient'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></td></tr>
					<tr><th scope="row"><label for="nomadsa-pages"><?php esc_html_e( 'Pages to monitor', 'nomad-site-audit' ); ?></label></th><td>
						<textarea id="nomadsa-pages" name="pages" rows="5" class="large-text code" placeholder="/&#10;/contact/"><?php echo esc_textarea( implode( "\n", $config['pages'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Add up to five public URLs on this site, one per line. WordPress measures them daily. Clear the list to stop monitoring.', 'nomad-site-audit' ); ?></p>
						<?php if ( $daily ) : ?>
							<p class="description"><?php
							/* translators: %s: next scheduled run in the WordPress site's timezone. */
							echo esc_html( sprintf( __( 'Next run: %s', 'nomad-site-audit' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $daily ) ) );
							?></p>
						<?php endif; ?>
					</td></tr>
					<tr><th scope="row"><label for="nomadsa-crux"><?php esc_html_e( 'Chrome UX Report API key', 'nomad-site-audit' ); ?></label></th><td>
						<input type="password" id="nomadsa-crux" name="crux_key" value="" autocomplete="new-password" class="regular-text">
						<p class="description"><?php esc_html_e( 'Optional. Saving a key allows the plugin to send monitored page URLs to Google for mobile Core Web Vitals. The key is required only when adding or replacing it.', 'nomad-site-audit' ); ?></p>
						<?php if ( $config['crux_key'] ) : ?>
							<label><input type="checkbox" name="remove_crux_key" value="1"> <?php esc_html_e( 'Remove saved key', 'nomad-site-audit' ); ?></label>
						<?php endif; ?>
					</td></tr>
					<tr><th scope="row"><label for="nomadsa-report-name"><?php esc_html_e( 'Report brand name', 'nomad-site-audit' ); ?></label></th><td><input type="text" id="nomadsa-report-name" name="report_name" maxlength="120" value="<?php echo esc_attr( $config['report_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="regular-text"></td></tr>
					<tr><th scope="row"><label for="nomadsa-accent"><?php esc_html_e( 'Report accent colour', 'nomad-site-audit' ); ?></label></th><td><input type="color" id="nomadsa-accent" name="accent" value="<?php echo esc_attr( $config['accent'] ); ?>"></td></tr>
				</table>
				<?php submit_button( __( 'Save audit settings', 'nomad-site-audit' ) ); ?>
			</form>
		</section>
		<?php
	}
}
