<?php
/**
 * Plugin Name: Orion Loan Manager
 * Description: Dashboard-only Loan Management System for WordPress (Borrowers, Loans, Monthly Charges, Payments, daily batch & allocation).
 * Version: 0.1.0
 * Author: Your Team
 * Requires PHP: 7.4
 * Text Domain: orion-loan-manager
 */

if (!defined('ABSPATH')) exit;

define('OLM_VERSION', '0.1.0');
define('OLM_DB_VERSION', '0.1.0');
define('OLM_PREFIX', $GLOBALS['wpdb']->prefix . 'olm_');

require_once __DIR__ . '/includes/Activator.php';
require_once __DIR__ . '/includes/Deactivator.php';
require_once __DIR__ . '/includes/Models.php';
require_once __DIR__ . '/includes/Scheduler.php';
require_once __DIR__ . '/includes/AdminMenus.php';
require_once __DIR__ . '/includes/Payments.php';

/**
 * Activation/Deactivation hooks
 */
register_activation_hook(__FILE__, ['OLM_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['OLM_Deactivator', 'deactivate']);

/**
 * Init
 */
add_action('plugins_loaded', function() {
    // Maybe run DB migrations
    OLM_Models::maybe_install();
});

/**
 * Admin assets (very light styles)
 */
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'orion-loan-manager') === false) return;
    wp_enqueue_style('olm-admin', plugin_dir_url(__FILE__) . 'admin/admin.css', [], OLM_VERSION);
});
