<?php
if (!defined('ABSPATH')) { exit; }

class MHL_Install {
    public static function activate(): void {
        MHL_Core::register_content_types();
        if (get_option('mhl_settings', null) === null) {
            add_option('mhl_settings', MHL_Core::settings(), '', false);
        }
        flush_rewrite_rules();
    }
}
