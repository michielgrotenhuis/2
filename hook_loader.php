<?php
/**
 * Robust Hook Loader for Blackwall Module
 * This can be included in WISECP's bootstrap process
 */

// Define a dedicated hook loader function to prevent namespace collisions
if (!function_exists('blackwall_register_hooks')) {
    function blackwall_register_hooks() {
        // Log that hook registration is starting
        error_log("Blackwall Hook Registration: Starting");

        // Get paths
        $module_dir = dirname(__FILE__);
        $hooks_dir = $module_dir . '/hooks';
        
        // Make sure BlackwallConstants is loaded
        if (!class_exists('BlackwallConstants')) {
            if (file_exists($module_dir . '/BlackwallConstants.php')) {
                require_once($module_dir . '/BlackwallConstants.php');
                error_log("Blackwall Hook Registration: Loaded BlackwallConstants");
            } else {
                error_log("Blackwall Hook Registration: ERROR - BlackwallConstants.php not found");
            }
        }

        // Include hook classes
        $hook_files = [
            $hooks_dir . '/DnsHook.php',
            $hooks_dir . '/BlackwallWelcomeHook.php'
        ];

        foreach ($hook_files as $file) {
            if (file_exists($file)) {
                require_once($file);
                error_log("Blackwall Hook Registration: Loaded " . basename($file));
            } else {
                error_log("Blackwall Hook Registration: ERROR - " . basename($file) . " not found");
            }
        }

        // Check if Hook class exists before attempting to register hooks
        if (!class_exists('Hook')) {
            error_log("Blackwall Hook Registration: ERROR - Hook class not found, cannot register hooks");
            return false;
        }

        // Register the hooks
        try {
            // Hook for order activation
            Hook::add("OrderActivation", 1, function($params = []) {
                error_log("Blackwall Hook: OrderActivation triggered, params: " . json_encode($params));
                
                // Check if this is a Blackwall product
                if (!isset($params['product_id']) || $params['product_id'] != 105) {
                    error_log("Blackwall Hook: Skipping - Not a Blackwall product");
                    return;
                }
                
                // Call the DNS hook
                if (class_exists('BlackwallDnsHook')) {
                    try {
                        BlackwallDnsHook::handleOrderActivated($params);
                        error_log("Blackwall Hook: DNS check completed");
                    } catch (Exception $e) {
                        error_log("Blackwall Hook: ERROR in DNS check - " . $e->getMessage());
                    }
                }
                
                // Call the welcome hook
                if (class_exists('BlackwallWelcomeHook')) {
                    try {
                        BlackwallWelcomeHook::handleOrderActivated($params);
                        error_log("Blackwall Hook: Welcome message check completed");
                    } catch (Exception $e) {
                        error_log("Blackwall Hook: ERROR in welcome message - " . $e->getMessage());
                    }
                }
            });
            
            // Hook for scheduled daily DNS checks
            Hook::add("DailyCronJobs", 1, function() {
                error_log("Blackwall Hook: DailyCronJobs triggered");
                
                if (!class_exists('Order')) {
                    error_log("Blackwall Hook: Order class not found, skipping DNS checks");
                    return;
                }
                
                try {
                    // Get recent Blackwall orders
                    $orders = Order::getOrders(['product_id' => 105, 'date_range' => '-7 days']);
                    error_log("Blackwall Hook: Found " . count($orders) . " recent orders to check");
                    
                    if (is_array($orders)) {
                        foreach ($orders as $order) {
                            $params = [
                                'id' => $order['id'],
                                'product_id' => $order['product_id'],
                                'options' => $order['options'],
                                'owner_id' => $order['owner_id']
                            ];
                            
                            // Call the DNS hook
                            if (class_exists('BlackwallDnsHook')) {
                                try {
                                    BlackwallDnsHook::handleOrderActivated($params);
                                    error_log("Blackwall Hook: DNS check completed for order " . $order['id']);
                                } catch (Exception $e) {
                                    error_log("Blackwall Hook: ERROR in DNS check for order " . $order['id'] . " - " . $e->getMessage());
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Blackwall Hook: ERROR in DailyCronJobs - " . $e->getMessage());
                }
            });

            error_log("Blackwall Hook Registration: Successfully registered all hooks");
            return true;
        } catch (Exception $e) {
            error_log("Blackwall Hook Registration: ERROR - Exception during hook registration: " . $e->getMessage());
            return false;
        }
    }
}

// Execute the hook registration
blackwall_register_hooks();
