<?php
/**
 * REST-Schnittstelle für die App: /wp-json/sani-mv/v1/…
 * Anmeldung über das WordPress-Cookie plus Nonce (X-WP-Nonce).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sani_MV_Rest {

	const NS = 'sani-mv/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NS,
			'/docs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_docs' ),
				'permission_callback' => array( 'Sani_MV_Access', 'can_read' ),
				'args'                => array(
					'since' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/docs/(?P<coll>[a-z]+)/(?P<id>[A-Za-z0-9_.~:-]{1,64})',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'put_doc' ),
					'permission_callback' => array( 'Sani_MV_Access', 'can_write' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_doc' ),
					'permission_callback' => array( 'Sani_MV_Access', 'can_write' ),
				),
			)
		);
	}

	public static function get_docs( WP_REST_Request $req ) {
		$now  = Sani_MV_Store::now();
		$docs = Sani_MV_Store::changes( (int) $req['since'] );
		$res  = rest_ensure_response(
			array(
				'now'  => $now,
				'docs' => $docs,
			)
		);
		$res->header( 'Cache-Control', 'no-store' );
		return $res;
	}

	public static function put_doc( WP_REST_Request $req ) {
		$coll = $req['coll'];
		if ( ! Sani_MV_Store::valid_coll( $coll ) ) {
			return new WP_Error( 'sani_mv_coll', 'Unbekannte Sammlung.', array( 'status' => 400 ) );
		}
		// Als Objekte dekodieren, damit leere Objekte ({}) nicht zu Listen ([]) werden.
		$body = json_decode( $req->get_body() );
		if ( ! is_object( $body ) || ! isset( $body->data ) || ! is_object( $body->data ) ) {
			return new WP_Error( 'sani_mv_body', 'Es fehlen Daten.', array( 'status' => 400 ) );
		}
		$r = Sani_MV_Store::put( $coll, $req['id'], $body->data );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
	}

	public static function delete_doc( WP_REST_Request $req ) {
		$coll = $req['coll'];
		if ( ! Sani_MV_Store::valid_coll( $coll ) ) {
			return new WP_Error( 'sani_mv_coll', 'Unbekannte Sammlung.', array( 'status' => 400 ) );
		}
		$r = Sani_MV_Store::delete( $coll, $req['id'] );
		return is_wp_error( $r ) ? $r : rest_ensure_response( array( 'ok' => true ) );
	}
}
