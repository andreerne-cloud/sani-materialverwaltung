<?php
/**
 * Wer darf lesen und wer darf bearbeiten.
 * Die Stufen sind WordPress-Berechtigungen, einstellbar unter Einstellungen → Materialverwaltung.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sani_MV_Access {

	/** Auswahl in den Einstellungen: Berechtigung => Bezeichnung */
	public static function levels() {
		return array(
			'read'              => 'Alle angemeldeten Benutzer (auch Abonnenten)',
			'edit_posts'        => 'Mitarbeiter, Autoren, Redakteure und Administratoren',
			'publish_posts'     => 'Autoren, Redakteure und Administratoren',
			'edit_others_posts' => 'Redakteure und Administratoren',
			'manage_options'    => 'Nur Administratoren',
		);
	}

	public static function read_cap() {
		return self::valid( get_option( 'sani_mv_read_cap', 'read' ), 'read' );
	}

	public static function write_cap() {
		return self::valid( get_option( 'sani_mv_write_cap', 'edit_others_posts' ), 'edit_others_posts' );
	}

	private static function valid( $cap, $fallback ) {
		return array_key_exists( $cap, self::levels() ) ? $cap : $fallback;
	}

	public static function can_read() {
		return is_user_logged_in() && ( current_user_can( self::read_cap() ) || self::can_write() );
	}

	public static function can_write() {
		return is_user_logged_in() && current_user_can( self::write_cap() );
	}

	public static function can_admin() {
		return current_user_can( 'manage_options' );
	}
}
