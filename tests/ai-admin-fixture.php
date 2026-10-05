<?php
// Served exclusively by tests/run.py on loopback, with no WordPress or provider.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', array('127.0.0.1','::1'), true)) { http_response_code(403); exit; }
define('ABSPATH', __DIR__);
class WP_Post { public int $ID=42; }
function get_user_meta(...$args): int { return 0; }
function get_current_user_id(): int { return 7; }
function rest_url($path): string { return '/test-api/'.$path; }
function wp_create_nonce($action): string { return 'synthetic-nonce'; }
function esc_url($value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value): string { return esc_url($value); }
function disabled($condition): void { if ($condition) echo 'disabled'; }
require __DIR__.'/../wordpress/miloslavhub-live/includes/class-mhl-ai-admin.php';
?><!doctype html><html lang="cs"><meta charset="utf-8"><title>AI pilot — syntetický test</title>
<form><label>Otázka <input id="title" value="Které číslo není sudé?"></label>
<label>A <input name="mhl_options[]" value="2"></label><label>B <input name="mhl_options[]" value="3"></label><label>C <input name="mhl_options[]" value=""></label>
<?php MHL_AI_Admin::render(new WP_Post()); ?></form><script src="/test-ai/ai-admin.js"></script></html>
