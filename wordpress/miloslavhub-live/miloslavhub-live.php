<?php
/**
 * Plugin Name: Hlasuj! by MiloslavHub
 * Plugin URI: https://miloslavhub.cz/
 * Description: Živé hlasování pro přednášky: předměty, přednášky, banka otázek, stálé QR adresy, testovací režim, bodování, archiv a REST API pro hlasuj.miloslavhub.cz.
 * Version: 0.8.7
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Miloslav Hub
 * Author URI: https://miloslavhub.cz/
 * License: GPL-2.0-or-later
 * Text Domain: miloslavhub-live
 */

if (!defined('ABSPATH')) { exit; }

define('MHL_VERSION', '0.8.7');
define('MHL_SCHEMA_VERSION', '0.8.5');
define('MHL_FILE', __FILE__);
define('MHL_DIR', plugin_dir_path(__FILE__));
define('MHL_URL', plugin_dir_url(__FILE__));

require_once MHL_DIR . 'includes/class-mhl-db.php';
require_once MHL_DIR . 'includes/class-mhl-install.php';
require_once MHL_DIR . 'includes/class-mhl-core.php';
require_once MHL_DIR . 'includes/class-mhl-admin.php';
require_once MHL_DIR . 'includes/class-mhl-rest.php';

register_activation_hook(__FILE__, array('MHL_Install', 'activate'));

add_action('plugins_loaded', static function () {
    if (MHL_DB::configured() && get_option('mhl_external_db_schema_version') !== MHL_SCHEMA_VERSION) {
        MHL_DB::mark_schema_if_ready();
    }
    MHL_Core::init();
    if (is_admin()) { MHL_Admin::init(); }
    MHL_REST::init();
});

add_action('admin_notices', static function () {
    if (!current_user_can('manage_options')) { return; }
    if (!MHL_DB::configured()) {
        echo '<div class="notice notice-warning"><p><strong>MiloslavHub Live:</strong> Doplňte připojení k samostatné databázi do <code>wp-config.php</code> (konstanty <code>MHL_LIVE_DB_HOST</code>, <code>MHL_LIVE_DB_NAME</code>, <code>MHL_LIVE_DB_USER</code>, <code>MHL_LIVE_DB_PASSWORD</code>).</p></div>';
    }
});
