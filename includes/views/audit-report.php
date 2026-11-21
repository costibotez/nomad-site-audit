<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( __( 'Site audit report', 'nomad-site-audit' ) . ' · ' . $brand ); ?></title>
	<?php wp_print_styles( 'nomadsa-audit-report' ); ?>
</head>
<body>
<main>
	<header>
		<p class="report-brand"><?php echo esc_html( $brand ); ?></p>
		<h1><?php esc_html_e( 'Site audit report', 'nomad-site-audit' ); ?></h1>
		<p class="report-meta"><?php echo esc_html( home_url() . ' · ' . NomadSA_Audit_Admin::date( $audit['created_at'] ) ); ?></p>
	</header>
	<section class="report-summary">
		<h2><?php esc_html_e( 'Audit summary', 'nomad-site-audit' ); ?></h2>
		<p>
			<?php
			/* translators: %d: number of findings. */
			echo esc_html( sprintf( _n( '%d finding needs attention.', '%d findings need attention.', count( $findings ), 'nomad-site-audit' ), count( $findings ) ) );
			?>
		</p>
		<p><?php esc_html_e( 'Scope includes security, database health, site-wide SEO, and up to 100 of the newest published posts and pages.', 'nomad-site-audit' ); ?></p>
		<?php if ( $audit['payload']['unavailable'] ) : ?>
			<p>
				<?php
				/* translators: %s: list of audit sections. */
				echo esc_html( sprintf( __( 'Sections unavailable in this run: %s.', 'nomad-site-audit' ), implode( ', ', $audit['payload']['unavailable'] ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $baseline ) : ?>
			<p>
				<?php
				/* translators: %s: date and time of comparison audit. */
				echo esc_html( sprintf( __( 'Compared with %s.', 'nomad-site-audit' ), NomadSA_Audit_Admin::date( $baseline['created_at'] ) ) );
				?>
			</p>
			<ul>
			<?php foreach ( NomadSA_Audit_Admin::group_labels() as $nomadsa_key => $nomadsa_label ) : ?>
				<li><strong><?php echo esc_html( count( $changes[ $nomadsa_key ] ) ); ?></strong> <?php echo esc_html( $nomadsa_label ); ?></li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<section>
		<h2><?php esc_html_e( 'Findings and next steps', 'nomad-site-audit' ); ?></h2>
		<?php if ( ! $findings ) : ?>
			<p><?php esc_html_e( 'The checks completed in this snapshot returned no findings requiring action.', 'nomad-site-audit' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $findings as $nomadsa_finding ) :
			list( , $nomadsa_guidance ) = NomadSA_Audit_History::guidance( $nomadsa_finding );
			?>
			<article class="report-finding">
				<h3><?php echo esc_html( $nomadsa_finding['title'] ); ?></h3>
				<p class="report-meta"><?php echo esc_html( NomadSA_Audit_Admin::priority( $nomadsa_finding['status'] ) . ' · ' . strtoupper( $nomadsa_finding['section'] ) ); ?></p>
				<?php if ( $nomadsa_finding['evidence'] ) : ?>
					<p><?php echo esc_html( $nomadsa_finding['evidence'] ); ?></p>
				<?php endif; ?>
				<p><?php echo esc_html( $nomadsa_guidance ); ?></p>
			</article>
		<?php endforeach; ?>
	</section>
	<?php if ( ! empty( $changes['resolved'] ) ) : ?>
		<section>
			<h2><?php esc_html_e( 'Resolved findings', 'nomad-site-audit' ); ?></h2>
			<ul>
			<?php foreach ( $changes['resolved'] as $nomadsa_finding ) : ?>
				<li><?php echo esc_html( $nomadsa_finding['title'] . ( $nomadsa_finding['evidence'] ? ' · ' . $nomadsa_finding['evidence'] : '' ) ); ?></li>
			<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</main>
</body>
</html>
