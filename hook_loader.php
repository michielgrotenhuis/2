<?php
/**
 * Robust Hook Loader for Blackwall Module
 * This file is included after the Blackwall class definition to register hooks properly
 */

// More robust mechanism to prevent multiple executions
// Use a static variable in combination with a global flag and a filesystem lock
if (!function_exists('blackwall_register_hooks')) {
    /**
     * Register all Blackwall hooks once
     * This function will only execute its contents once per PHP process
     * 
     * @return void
     */
    function blackwall_register_hooks() {
        // Use static variable to ensure this code only runs once per request
        static $hooks_registered = false;
        
        // Check if we've already registered hooks in this request
        if ($hooks_registered) {
            error_log("Blackwall hooks already registered in this request - skipping");
            return;
        }
        
        // Check global flag to catch cases where the static variable is reset (unlikely)
        if (!empty($GLOBALS['BLACKWALL_HOOKS_LOADED'])) {
            error_log("Blackwall hooks already loaded via global flag - skipping");
            return;
        }
        
        // Try to obtain a lock file to prevent concurrent hook registrations
        $lock_file = sys_get_temp_dir() . '/blackwall_hooks.lock';
        $lock_handle = @fopen($lock_file, 'w+');
        
        if (!$lock_handle) {
            error_log("Warning: Could not create Blackwall hook lock file - continuing anyway");
        } elseif (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
            // Another process is currently registering hooks
            error_log("Another process is registering Blackwall hooks - skipping");
            fclose($lock_handle);
            return;
        }
        
        // Set the flag immediately to prevent race conditions
        $GLOBALS['BLACKWALL_HOOKS_LOADED'] = true;
        $hooks_registered = true;
        
        // Direct debug to PHP error log
        error_log("===== BLACKWALL HOOK LOADER STARTED =====");
        
        try {
            // Make sure BlackwallConstants is loaded
            if (!class_exists('BlackwallConstants')) {
                require_once(__DIR__ . '/BlackwallConstants.php');
                error_log("Loaded BlackwallConstants.php");
            }
            
            // Include the Hook classes
            $hooks_dir = __DIR__ . '/hooks';
            $dns_hook_file = $hooks_dir . '/DnsHook.php';
            $welcome_hook_file = $hooks_dir . '/BlackwallWelcomeHook.php';
            
            if (file_exists($dns_hook_file)) {
                require_once($dns_hook_file);
                error_log("Loaded DnsHook.php");
            } else {
                error_log("ERROR: DnsHook.php not found at: " . $dns_hook_file);
            }
            
            if (file_exists($welcome_hook_file)) {
                require_once($welcome_hook_file);
                error_log("Loaded BlackwallWelcomeHook.php");
            } else {
                error_log("ERROR: BlackwallWelcomeHook.php not found at: " . $welcome_hook_file);
            }
            
            // Register the hooks only if the Hook class exists
            if (class_exists('Hook')) {
                error_log("Hook class exists, registering hooks");
                
                // Add general hook logging for ALL order activations to check if hooks are firing
                try {
                    // Using a unique hook ID to avoid collisions with existing hooks
                    Hook::add("OrderActivation", -99, function($params = []) {
                        error_log("GENERAL OrderActivation hook triggered with parameters: " . json_encode($params));
                    });
                    error_log("Added general hook tracking");
                } catch (Exception $e) {
                    error_log("Error adding general hook tracking: " . $e->getMessage());
                }
                
                // Hook for order activation
                try {
                    // Using a specific priority that might not be used by other hooks
                    Hook::add("OrderActivation", 105, function($params = []) {
                        error_log("Blackwall OrderActivation hook triggered");
                        
                        // Check if this is a Blackwall product
                        if (!isset($params['product_id']) || 
                            ($params['product_id'] != 105 && $params['product_id'] != '105')) {
                            error_log("Skipping - Not a Blackwall product. Product ID: " . 
                                     (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
                            return;
                        }
                        
                        error_log("FOUND BLACKWALL PRODUCT - PROCESSING HOOKS");
                        
                        // Call both hooks
                        if (class_exists('BlackwallDnsHook')) {
                            try {
                                error_log("Calling BlackwallDnsHook::handleOrderActivated");
                                BlackwallDnsHook::handleOrderActivated($params);
                                error_log("DNS hook completed");
                            } catch (Exception $e) {
                                error_log("ERROR in DNS hook: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                            }
                        } else {
                            error_log("ERROR: BlackwallDnsHook class not found at execution time");
                        }
                        
                        if (class_exists('BlackwallWelcomeHook')) {
                            try {
                                error_log("Calling BlackwallWelcomeHook::handleOrderActivated");
                                BlackwallWelcomeHook::handleOrderActivated($params);
                                error_log("Welcome hook completed");
                            } catch (Exception $e) {
                                error_log("ERROR in Welcome hook: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                            }
                        } else {
                            error_log("ERROR: BlackwallWelcomeHook class not found at execution time");
                        }
                        
                        return true; // Signal we've handled this hook
                    });
                    
                    error_log("Successfully registered OrderActivation hook");
                } catch (Exception $e) {
                    error_log("ERROR registering OrderActivation hook: " . $e->getMessage());
                }
                
                // Daily cron job to check DNS configurations
                try {
                    Hook::add("DailyCronJobs", 105, function() {
                        error_log("Blackwall DailyCronJobs hook triggered");
                        
                        // Check if we have the Order class
                        if (class_exists('Order')) {
                            try {
                                // Try to explicitly load DnsHook and WelcomeHook classes again
                                $hooks_dir = __DIR__ . '/hooks';
                                $dns_hook_file = $hooks_dir . '/DnsHook.php';
                                $welcome_hook_file = $hooks_dir . '/BlackwallWelcomeHook.php';
                                
                                if (file_exists($dns_hook_file)) {
                                    require_once($dns_hook_file);
                                }
                                
                                if (file_exists($welcome_hook_file)) {
                                    require_once($welcome_hook_file);
                                }
                                
                                // Get Blackwall orders
                                try {
                                    // Try to get all recent Blackwall orders
                                    $orders = Order::getOrders([
                                        'product_id' => [105, '105'], 
                                        'date_range' => '-30 days'
                                    ]);
                                    error_log("Found orders using getOrders method");
                                } catch (Exception $e1) {
                                    error_log("Error with primary order retrieval method: " . $e1->getMessage());
                                    
                                    // Fallback method
                                    try {
                                        $orders = [];
                                        error_log("Trying alternate order retrieval method");
                                        // Your fallback code here
                                    } catch (Exception $e2) {
                                        error_log("Error with fallback order retrieval method: " . $e2->getMessage());
                                        $orders = [];
                                    }
                                }
                                
                                if ($orders && is_array($orders) && !empty($orders)) {
                                    error_log("Found " . count($orders) . " recent Blackwall orders for DNS check");
                                    
                                    foreach($orders as $order) {
                                        // Convert to the format expected by the handler
                                        $params = [
                                            'id' => $order['id'],
                                            'product_id' => $order['product_id'],
                                            'options' => $order['options'],
                                            'owner_id' => $order['owner_id']
                                        ];
                                        
                                        error_log("Processing order ID: " . $order['id']);
                                        
                                        // Run DNS checks
                                        if (class_exists('BlackwallDnsHook')) {
                                            try {
                                                BlackwallDnsHook::handleOrderActivated($params);
                                                error_log("DNS check completed for order ID: " . $order['id']);
                                            } catch (Exception $e) {
                                                error_log("Error in DNS check for order ID " . $order['id'] . ": " . 
                                                         $e->getMessage() . "\n" . $e->getTraceAsString());
                                            }
                                        } else {
                                            error_log("ERROR: BlackwallDnsHook class not found during cron job");
                                        }
                                        
                                        // Also run welcome checks
                                        if (class_exists('BlackwallWelcomeHook')) {
                                            try {
                                                BlackwallWelcomeHook::handleOrderActivated($params);
                                                error_log("Welcome check completed for order ID: " . $order['id']);
                                            } catch (Exception $e) {
                                                error_log("Error in welcome check for order ID " . $order['id'] . ": " . 
                                                         $e->getMessage() . "\n" . $e->getTraceAsString());
                                            }
                                        } else {
                                            error_log("ERROR: BlackwallWelcomeHook class not found during cron job");
                                        }
                                    }
                                } else {
                                    error_log("No recent Blackwall orders found for DNS check or orders not returned as array");
                                    if ($orders) {
                                        error_log("Orders data type: " . gettype($orders));
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Error processing orders in DailyCronJobs: " . $e->getMessage() . "\n" . 
                                         $e->getTraceAsString());
                            }
                        } else {
                            error_log("ERROR: Order class not found in DailyCronJobs");
                        }
                        
                        return true; // Signal we've handled this hook
                    });
                    
                    error_log("Successfully registered DailyCronJobs hook");
                } catch (Exception $e) {
                    error_log("ERROR registering DailyCronJobs hook: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: Hook class not found - cannot register Blackwall hooks");
            }
            
            // For development only - manually trigger DNS checks - REMOVE THIS IN PRODUCTION
            if (defined('BLACKWALL_DEBUG') && BLACKWALL_DEBUG === true) {
                try {
                    error_log("Testing manual DNS hook execution");
                    
                    if (class_exists('Order')) {
                        // Get a sample order to test
                        $test_orders = Order::getOrders(['product_id' => 105, 'limit' => 1]);
                        
                        if (!empty($test_orders) && is_array($test_orders)) {
                            $test_order = reset($test_orders); // Get first order
                            
                            $params = [
                                'id' => $test_order['id'],
                                'product_id' => $test_order['product_id'],
                                'options' => $test_order['options'],
                                'owner_id' => $test_order['owner_id']
                            ];
                            
                            error_log("Found test order ID: " . $test_order['id']);
                            
                            if (class_exists('BlackwallDnsHook')) {
                                BlackwallDnsHook::handleOrderActivated($params);
                                error_log("Manual DNS hook completed for test order");
                            }
                        } else {
                            error_log("No test orders found for manual execution");
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in manual hook execution: " . $e->getMessage());
                }
            }
            
            error_log("===== BLACKWALL HOOK LOADER COMPLETED =====");
        } catch (Exception $e) {
            error_log("Uncaught exception in hook_loader.php: " . $e->getMessage());
            error_log($e->getTraceAsString());
        }
        
        // Release the lock if we obtained it
        if (isset($lock_handle) && is_resource($lock_handle)) {
            flock($lock_handle, LOCK_UN);
            fclose($lock_handle);
        }
    }
}

// Call the function to register hooks once
blackwall_register_hooks();
