<?php
/**
 * Einstellungen → Materialverwaltung: Rechte, Datensicherung (Export) und Datenübernahme (Import).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sani_MV_Admin {

	const PAGE = 'sani-materialverwaltung';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sani_mv_save', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_sani_mv_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_sani_mv_import', array( __CLASS__, 'import' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SANI_MV_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page( 'Materialverwaltung', 'Materialverwaltung', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Einstellungen</a>' );
		return $links;
	}

	private static function back( $msg, $type = 'success' ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE,
					'sani_msg'    => rawurlencode( $msg ),
					'sani_status' => $type,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function page() {
		if ( ! Sani_MV_Access::can_admin() ) {
			return;
		}
		$counts = Sani_MV_Store::counts();
		$levels = Sani_MV_Access::levels();
		$msg    = isset( $_GET['sani_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sani_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = ( isset( $_GET['sani_status'] ) && 'error' === $_GET['sani_status'] ) ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$select = function ( $name, $current ) use ( $levels ) {
			$o = '<select name="' . esc_attr( $name ) . '">';
			foreach ( $levels as $cap => $label ) {
				$o .= '<option value="' . esc_attr( $cap ) . '"' . selected( $cap, $current, false ) . '>' . esc_html( $label ) . '</option>';
			}
			return $o . '</select>';
		};
		?>
		<div class="wrap">
			<h1>Materialverwaltung <span style="font-size:13px;color:#646970;font-weight:400">Version <?php echo esc_html( SANI_MV_VERSION ); ?></span></h1>
			<?php if ( $msg ) : ?>
				<div class="notice notice-<?php echo esc_attr( $status ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php endif; ?>

			<h2>Einbinden</h2>
			<p>Setze den Shortcode <code>[sani_material]</code> auf eine Seite, z. B. «Intern › Material». Angemeldete Benutzer mit Leserechten sehen dort die App, alle anderen einen Hinweis zur Anmeldung.
			Die Höhe lässt sich anpassen: <code>[sani_material hoehe="900px"]</code>.</p>
			<p><a class="button" href="<?php echo esc_url( Sani_MV_App::app_url() ); ?>" target="_blank" rel="noopener">App im eigenen Fenster öffnen</a></p>

			<h2>Rechte</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sani_mv_save' ); ?>
				<input type="hidden" name="action" value="sani_mv_save">
				<table class="form-table" role="presentation">
					<tr><th scope="row">Ansehen dürfen</th><td><?php echo $select( 'read_cap', Sani_MV_Access::read_cap() ); // phpcs:ignore ?></td></tr>
					<tr><th scope="row">Bearbeiten dürfen</th><td><?php echo $select( 'write_cap', Sani_MV_Access::write_cap() ); // phpcs:ignore ?>
						<p class="description">Wer bearbeiten darf, darf immer auch ansehen.</p></td></tr>
				</table>
				<?php submit_button( 'Rechte speichern' ); ?>
			</form>

			<h2>Daten</h2>
			<p>Gespeichert: <strong><?php echo (int) $counts['artikel']; ?></strong> Artikel,
				<strong><?php echo (int) $counts['plaetze']; ?></strong> Lagerplätze,
				<strong><?php echo (int) $counts['lieferanten']; ?></strong> Lieferanten.</p>

			<h3>Sicherung herunterladen</h3>
			<p>Alle Daten als JSON-Datei. Mach das vor jedem Plugin-Update.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sani_mv_export' ); ?>
				<input type="hidden" name="action" value="sani_mv_export">
				<?php submit_button( 'Sicherung herunterladen', 'secondary', 'submit', false ); ?>
			</form>

			<h3>Daten übernehmen</h3>
			<p>Lädt eine Sicherung (JSON) aus der App oder von dieser Seite. <strong>Alle aktuellen Daten werden dabei ersetzt.</strong></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sani_mv_import' ); ?>
				<input type="hidden" name="action" value="sani_mv_import">
				<p><input type="file" name="datei" accept=".json,application/json" required></p>
				<p><label><input type="checkbox" name="bestaetigt" value="1" required> Ich habe verstanden, dass die bestehenden Daten ersetzt werden.</label></p>
				<?php submit_button( 'Daten übernehmen', 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! Sani_MV_Access::can_admin() ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sani_mv_save' );
		$levels = Sani_MV_Access::levels();
		$read   = isset( $_POST['read_cap'] ) ? sanitize_key( wp_unslash( $_POST['read_cap'] ) ) : 'read';
		$write  = isset( $_POST['write_cap'] ) ? sanitize_key( wp_unslash( $_POST['write_cap'] ) ) : 'edit_others_posts';
		update_option( 'sani_mv_read_cap', isset( $levels[ $read ] ) ? $read : 'read' );
		update_option( 'sani_mv_write_cap', isset( $levels[ $write ] ) ? $write : 'edit_others_posts' );
		self::back( 'Rechte gespeichert.' );
	}

	public static function export() {
		if ( ! Sani_MV_Access::can_admin() ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sani_mv_export' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="Materialverwaltung_Sicherung_' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( Sani_MV_Store::export(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	public static function import() {
		if ( ! Sani_MV_Access::can_admin() ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sani_mv_import' );
		if ( empty( $_POST['bestaetigt'] ) ) {
			self::back( 'Bitte bestätige, dass die Daten ersetzt werden.', 'error' );
		}
		if ( empty( $_FILES['datei']['tmp_name'] ) || ! is_uploaded_file( $_FILES['datei']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::back( 'Keine Datei hochgeladen.', 'error' );
		}
		$raw     = file_get_contents( $_FILES['datei']['tmp_name'] ); // phpcs:ignore
		$payload = json_decode( $raw );
		$result  = Sani_MV_Store::import( $payload );
		if ( is_wp_error( $result ) ) {
			self::back( $result->get_error_message(), 'error' );
		}
		self::back( sprintf( '%d Einträge übernommen.', (int) $result ) );
	}
}
