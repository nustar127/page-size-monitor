<?php

/**
 * Plugin Name: Page size monitor
 * Plugin URI: https://example.com/age-size-monitor
 * Description: page size.
 * Version: 1.0.0
 * Author: nu127
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: page-size-monitor
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PSM_VERSION', '1.0.0');
define('PSM_PLUGIN_FILE', __FILE__);
define('PSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PSM_PLUGIN_DIR . 'includes/class-page-analyzer.php';

function psm_init()
{
    new PSMAnalyzer();
}

add_action('plugins_loaded', 'psm_init');
