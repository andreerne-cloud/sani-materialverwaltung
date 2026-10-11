<?php
/**
 * Beim Löschen des Plugins werden nur die Einstellungen entfernt.
 * Die Materialdaten (Tabelle wp_sani_mv_docs) bleiben bewusst erhalten,
 * damit ein versehentliches Löschen keine Daten vernichtet.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sani_mv_read_cap' );
delete_option( 'sani_mv_write_cap' );
