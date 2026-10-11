<?php
/**
 * Plugin Name:       Sani Materialverwaltung
 * Description:       Materialverwaltung des Sanitätsvereins Leuggern: Artikel, Lagerorte, Lieferanten, Inventar und PDF-Berichte. Einbinden mit dem Shortcode [sani_material].
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sanitätsverein Leuggern
 * License:           GPL-2.0-or-later
 * Text Domain:       sani-mv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SANI_MV_VERSION', '1.0.0' );
define( 'SANI_MV_DB_VERSION', 1 );
define( 'SANI_MV_FILE', __FILE__ );
define( 'SANI_MV_DIR', plugin_dir_path( __FILE__ ) );
define( 'SANI_MV_URL', plugin_dir_url( __FILE__ ) );

require_once SANI_MV_DIR . 'includes/class-access.php';
require_once SANI_MV_DIR . 'includes/class-store.php';
require_once SANI_MV_DIR . 'includes/class-rest.php';
require_once SANI_MV_DIR . 'includes/class-app.php';
require_once SANI_MV_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'Sani_MV_Store', 'install' ) );
add_action( 'plugins_loaded', array( 'Sani_MV_Store', 'maybe_upgrade' ) );

Sani_MV_Rest::init();
Sani_MV_App::init();
Sani_MV_Admin::init();
