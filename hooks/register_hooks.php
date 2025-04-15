<?php
/**
 * Register Hooks for Blackwall Module
 * This file registers all necessary hooks for the Blackwall module
 * Enhanced with additional debugging
 */

// Log that the hook file was included
error_log("===== BLACKWALL HOOKS REGISTRATION FILE LOADED =====");

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
    error_log("Loaded BlackwallConstants.php");
} else {
    error_log("BlackwallConstants class already exists");
}

// Include the Hook classes
$hooks_dir = __DIR__;
$dns_hook_file = $hooks_dir . '/DnsHook.php';
$welcome_hook_file = $hooks_dir . '/BlackwallWelcomeHook.php';

if (file_exists($dns_hook_file)) {
    require_once($dns_hook_file);
    error_log("Loaded DnsHook.php from: " . $dns_hook_file);
} else {
    error_log("ERROR: DnsHook.php not found at: " . $dns_hook_file);
}

if (file_exists($welcome_hook_file)) {
    require_once($welcome_hook_file);
    error_log("Loaded BlackwallWelcomeHook.php from: " . $welcome_hook_file);
} else {
    error_log("ERROR: BlackwallWelcomeHook.php not found at: " . $welcome_hook_file);
}

// Register the hooks for order creation/activation
if (class_exists('Hook')) {
    error_log("Hook class exists, registering hooks");
    
    // Hook into order creation - when a new order is created
    Hook::add("OrderCreated", 1, function($params = []) {
        error_log("OrderCreated hook triggered with params: " . print_r($params, true));
        
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall OrderCreated hook triggered for order ID: " . $params['id']);
            
            // Call the DNS verification and ticket creation functionality
            if (class_exists('BlackwallDnsHook')) {
                error_log("Calling BlackwallDnsHook::handleOrderActivated()");
                try {
                    BlackwallDnsHook::handleOrderActivated($params);
                    error_log("BlackwallDnsHook::handleOrderActivated() completed");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallDnsHook::handleOrderActivated(): " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallDnsHook class not found");
            }
            
            // Call the welcome message ticket creation functionality
            if (class_exists('BlackwallWelcomeHook')) {
                error_log("Calling BlackwallWelcomeHook::handleOrderActivated()");
                try {
                    BlackwallWelcomeHook::handleOrderActivated($params);
                    error_log("BlackwallWelcomeHook::handleOrderActivated() completed");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallWelcomeHook::handleOrderActivated(): " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallWelcomeHook class not found");
            }
        } else {
            error_log("Skipping hook - not a Blackwall product. Product ID: " . 
                (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
        }
    });
    
    // Hook into order activation - when an order is activated
    Hook::add("OrderActivation", 1, function($params = []) {
        error_log("OrderActivation hook triggered with params: " . print_r($params, true));
        
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall OrderActivation hook triggered for order ID: " . $params['id']);
            
            // Call the DNS verification and ticket creation functionality
            if (class_exists('BlackwallDnsHook')) {
                error_log("Calling BlackwallDnsHook::handleOrderActivated() from OrderActivation");
                try {
                    BlackwallDnsHook::handleOrderActivated($params);
                    error_log("BlackwallDnsHook::handleOrderActivated() completed from OrderActivation");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallDnsHook::handleOrderActivated() from OrderActivation: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallDnsHook class not found in OrderActivation");
            }
            
            // Call the welcome message ticket creation functionality
            if (class_exists('BlackwallWelcomeHook')) {
                error_log("Calling BlackwallWelcomeHook::handleOrderActivated() from OrderActivation");
                try {
                    BlackwallWelcomeHook::handleOrderActivated($params);
                    error_log("BlackwallWelcomeHook::handleOrderActivated() completed from OrderActivation");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallWelcomeHook::handleOrderActivated() from OrderActivation: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallWelcomeHook class not found in OrderActivation");
            }
        } else {
            error_log("Skipping OrderActivation hook - not a Blackwall product. Product ID: " . 
                (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
        }
    });
    
    // Daily cron job to check DNS configurations for existing orders
    Hook::add("DailyCronJobs", 1, function() {
        error_log("Blackwall DailyCronJobs hook triggered");
        
        // Check if we have the Order class
        if (class_exists('Order')) {
            try {
                // Get Blackwall orders from the last 7 days
                $orders = Order::getOrders(['product_id' => 105, 'date_range' => '-7 days']);
                
                if ($orders && is_array($orders)) {
                    error_log("Found " . count($orders) . " recent Blackwall orders for DNS check");
                    
                    foreach($orders as $order) {
                        // Convert to the format expected by the handler
                        $params = [
                            'id' => $order['id'],
                            'product_id' => $order['product_id'],
                            'options' => $order['options'],
                            'owner_id' => $order['owner_id']
                        ];
                        
                        error_log("Processing DNS check for order ID: " . $order['id']);
                        
                        // Only run DNS checks - no need to send welcome messages for existing orders
                        if (class_exists('BlackwallDnsHook')) {
                            try {
                                BlackwallDnsHook::handleOrderActivated($params);
                                error_log("DNS check completed for order ID: " . $order['id']);
                            } catch (Exception $e) {
                                error_log("Error in DNS check for order ID " . $order['id'] . ": " . $e->getMessage());
                            }
                        } else {
                            error_log("ERROR: BlackwallDnsHook class not found for order ID: " . $order['id']);
                        }
                    }
                    
                    error_log("Blackwall DailyCronJobs processed " . count($orders) . " orders");
                } else {
                    error_log("No recent Blackwall orders found for DNS check");
                }
            } catch (Exception $e) {
                error_log("Blackwall DailyCronJobs error: " . $e->getMessage());
            }
        } else {
            error_log("ERROR: Order class not found in DailyCronJobs");
        }
    });
    
    // Hook for after service creation is complete
    Hook::add("ProductCreated", 1, function($params = []) {
        error_log("ProductCreated hook triggered with params: " . print_r($params, true));
        
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall ProductCreated hook triggered for order ID: " . 
                (isset($params['id']) ? $params['id'] : 'NOT SET'));
            
            // Send welcome message ticket
            if (class_exists('BlackwallWelcomeHook')) {
                error_log("Calling BlackwallWelcomeHook::handleOrderActivated() from ProductCreated");
                try {
                    BlackwallWelcomeHook::handleOrderActivated($params);
                    error_log("BlackwallWelcomeHook::handleOrderActivated() completed from ProductCreated");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallWelcomeHook::handleOrderActivated() from ProductCreated: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallWelcomeHook class not found in ProductCreated");
            }
        } else {
            error_log("Skipping ProductCreated hook - not a Blackwall product. Product ID: " . 
                (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
        }
    });
    
    // Add additional hooks for troubleshooting
    Hook::add("ProductReady", 1, function($params = []) {
        error_log("ProductReady hook triggered with params: " . print_r($params, true));
        
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall ProductReady hook triggered for order ID: " . 
                (isset($params['id']) ? $params['id'] : 'NOT SET'));
            
            // Send welcome message ticket as an additional attempt
            if (class_exists('BlackwallWelcomeHook')) {
                error_log("Calling BlackwallWelcomeHook::handleOrderActivated() from ProductReady");
                try {
                    BlackwallWelcomeHook::handleOrderActivated($params);
                    error_log("BlackwallWelcomeHook::handleOrderActivated() completed from ProductReady");
                } catch (Exception $e) {
                    error_log("ERROR in BlackwallWelcomeHook::handleOrderActivated() from ProductReady: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: BlackwallWelcomeHook class not found in ProductReady");
            }
        }
    });
} else {
    error_log("ERROR: Hook class not found - cannot register Blackwall hooks");
}

// Log that the hook registration is complete
error_log("===== BLACKWALL HOOKS REGISTRATION COMPLETE =====");
