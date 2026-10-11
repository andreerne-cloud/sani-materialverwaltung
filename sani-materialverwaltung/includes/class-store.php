<?php
/**
 * Datenspeicher: eine Tabelle mit JSON-Dokumenten in vier Sammlungen
 * (artikel, plaetze, lieferanten, meta). Gelöschte Dokumente bleiben als
 * Markierung stehen, damit offene Fenster die Löschung beim Abgleich mitbekommen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sani_MV_Store {

	const COLLECTIONS = array( 'artikel', 'plaetze', 'lieferanten', 'meta' );
	const ID_PATTERN  = '/^[A-Za-z0-9_.~:-]{1,64}$/'; // ohne @ und +: WordPress nutzt @ als Trennzeichen in Routen
	const MAX_BYTES   = 262144;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sani_mv_docs';
	}

	/** Tabelle anlegen oder an neue Version anpassen (dbDelta ändert nur, löscht nie). */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				coll varchar(32) NOT NULL,
				doc_id varchar(64) NOT NULL,
				data longtext NULL,
				deleted tinyint(1) NOT NULL DEFAULT 0,
				updated_at bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (coll,doc_id),
				KEY updated_at (updated_at)
			) {$charset};"
		);
		add_option( 'sani_mv_read_cap', 'read' );
		add_option( 'sani_mv_write_cap', 'edit_others_posts' );
		update_option( 'sani_mv_db_version', SANI_MV_DB_VERSION );
	}

	/**
	 * Läuft bei jedem Seitenaufruf, tut aber nur nach einem Plugin-Update etwas.
	 * Spätere Datenumstellungen kommen hier hinein (if ($from < 2) { … }).
	 */
	public static function maybe_upgrade() {
		$from = (int) get_option( 'sani_mv_db_version', 0 );
		if ( $from >= SANI_MV_DB_VERSION ) {
			return;
		}
		self::install();
	}

	/** Zeitstempel in Mikrosekunden, dient als Änderungszähler für den Abgleich. */
	public static function now() {
		return (int) round( microtime( true ) * 1000000 );
	}

	public static function valid_coll( $coll ) {
		return in_array( $coll, self::COLLECTIONS, true );
	}

	public static function valid_id( $id ) {
		return is_string( $id ) && preg_match( self::ID_PATTERN, $id );
	}

	/**
	 * Alle Dokumente, oder nur die seit $since geänderten (inkl. Löschungen).
	 * Ein kleines Überlappungsfenster fängt Schreibvorgänge ab, die während der Abfrage passieren.
	 */
	public static function changes( $since = 0 ) {
		global $wpdb;
		$table = self::table();
		if ( $since > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT coll, doc_id, data, deleted, updated_at FROM {$table} WHERE updated_at > %d", $since - 3000000 ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "SELECT coll, doc_id, data, deleted, updated_at FROM {$table} WHERE deleted = 0", ARRAY_A );
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'coll'    => $r['coll'],
				'id'      => $r['doc_id'],
				'deleted' => (bool) (int) $r['deleted'],
				'data'    => (int) $r['deleted'] ? null : json_decode( $r['data'] ),
			);
		}
		return $out;
	}

	public static function put( $coll, $id, $data ) {
		global $wpdb;
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
		if ( false === $json || strlen( $json ) > self::MAX_BYTES ) {
			return new WP_Error( 'sani_mv_too_large', 'Dokument ist zu gross.', array( 'status' => 400 ) );
		}
		$ok = $wpdb->replace(
			self::table(),
			array(
				'coll'       => $coll,
				'doc_id'     => $id,
				'data'       => $json,
				'deleted'    => 0,
				'updated_at' => self::now(),
				'updated_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d' )
		);
		return false === $ok ? new WP_Error( 'sani_mv_db', 'Speichern fehlgeschlagen.', array( 'status' => 500 ) ) : true;
	}

	public static function delete( $coll, $id ) {
		global $wpdb;
		$ok = $wpdb->replace(
			self::table(),
			array(
				'coll'       => $coll,
				'doc_id'     => $id,
				'data'       => null,
				'deleted'    => 1,
				'updated_at' => self::now(),
				'updated_by' => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d' )
		);
		return false === $ok ? new WP_Error( 'sani_mv_db', 'Löschen fehlgeschlagen.', array( 'status' => 500 ) ) : true;
	}

	public static function counts() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT coll, COUNT(*) AS n FROM {$table} WHERE deleted = 0 GROUP BY coll", ARRAY_A );
		$out   = array_fill_keys( self::COLLECTIONS, 0 );
		foreach ( (array) $rows as $r ) {
			$out[ $r['coll'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Alle Daten im selben Format wie der JSON-Export der App. */
	public static function export() {
		$collections = array_fill_keys( self::COLLECTIONS, array() );
		foreach ( self::changes( 0 ) as $d ) {
			$collections[ $d['coll'] ][ (string) $d['id'] ] = $d['data'];
		}
		foreach ( $collections as $k => $v ) {
			$collections[ $k ] = (object) $v;
		}
		return array(
			'format'      => 'sani-materialverwaltung',
			'version'     => 1,
			'exportiert'  => gmdate( 'c' ),
			'collections' => $collections,
		);
	}

	/**
	 * Daten aus einer Sicherung übernehmen. Ersetzt den gesamten Bestand.
	 * Gibt die Anzahl übernommener Dokumente zurück oder einen Fehler.
	 */
	public static function import( $payload ) {
		global $wpdb;
		// $payload ist mit json_decode() ohne assoc dekodiert (Objekte bleiben Objekte).
		if ( ! is_object( $payload ) || ( $payload->format ?? '' ) !== 'sani-materialverwaltung' || ! is_object( $payload->collections ?? null ) ) {
			return new WP_Error( 'sani_mv_format', 'Die Datei ist keine Sicherung der Materialverwaltung.' );
		}
		$docs = array();
		foreach ( get_object_vars( $payload->collections ) as $coll => $items ) {
			if ( ! self::valid_coll( $coll ) || ! is_object( $items ) ) {
				continue;
			}
			foreach ( get_object_vars( $items ) as $id => $data ) {
				$id = (string) $id;
				if ( self::valid_id( $id ) && is_object( $data ) ) {
					$docs[] = array( $coll, $id, $data );
				}
			}
		}
		if ( ! $docs ) {
			return new WP_Error( 'sani_mv_empty', 'Die Datei enthält keine Daten.' );
		}
		$table = self::table();
		$wpdb->query( "DELETE FROM {$table}" );
		foreach ( $docs as $d ) {
			$r = self::put( $d[0], $d[1], $d[2] );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		return count( $docs );
	}
}
