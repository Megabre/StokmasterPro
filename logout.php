<?php
/**
 * Megabre StokMaster Pro
 * Logout Page
 * 
 * @author Ali Çömez / Slaweally
 * @website https://megabre.com
 */

// Include configuration
require_once 'config/config.php';

// Include core files
require_once CORE_PATH . 'Database.php';
require_once CORE_PATH . 'Session.php';
require_once CORE_PATH . 'Authentication.php';
require_once CORE_PATH . 'helpers.php';

// Initialize authentication
$auth = new Authentication();

// Log out user
$auth->logout();

// Redirect to login page
redirect('login.php');