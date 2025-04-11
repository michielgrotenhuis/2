<?php
/**
 * Hook Loader for Blackwall Module
 * 
 * This file can be included in WISECP bootstrap process to ensure
 * hooks are registered early in the application lifecycle.
 */

// Check if this file is being included directly
if (defined('CORE_FOLDER')) {
    // Register hooks if Hook class exists
    if (class_exists('Hook')) {
        $hooks_file = __DIR__ . '/hooks/register_hooks.php';
        
        if (file_exists($hooks_file)) {
            include_once($hooks_file);
        }
    }
}
