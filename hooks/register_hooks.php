<?php
/**
 * Register Hooks for Blackwall Module
 * This file registers all necessary hooks for the Blackwall module
 * Enhanced with improved debugging and reliability
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
    
    // Hook into ALL possible events that might be triggered when an order is created or activated
    $hooks = [
        "OrderCreated" => "Order creation",
        "OrderActivation" => "Order activation",
        "ProductCreated" => "Product creation",
        "ProductReady" => "Product ready",
        "OrderDetails" => "Order details",
        "OrderView" => "Order view",
        "NewOrder" => "New order",
        "OrderUpgradeFinished" => "Order upgrade",
        "AfterProductModule" => "After product module"
    ];
    
    foreach ($hooks as $hookName => $hookDescription) {
        try {
            Hook::add($hookName, 1, function($params = []) use ($hookName, $hookDescription) {
                error_log("Blackwall {$hookName} hook triggered ({$hookDescription})");
                
                // Check if this is Blackwall product (ID 105)
                if (isset($params['product_id']) && $params['product_id'] == 105) {
                    error_log("Blackwall {$hookName} hook - processing for product ID 105");
                    
                    // Get the real order info if we only have the ID
                    if (isset($params['id']) && (!isset($params['options']) || empty($params['options']))) {
                        if (class_exists('Order')) {
                            error_log("Fetching complete order data for ID: " . $params['id']);
                            $order = Order::get($params['id']);
                            if ($order && isset($order['options'])) {
                                $params['options'] = $order['options'];
                                $params['owner_id'] = $order['owner_id'];
                                error_log("Successfully fetched order data");
                            }
                        }
                    }
                    
                    // Call both hooks
                    try {
                        if (class_exists('BlackwallDnsHook')) {
                            error_log("Calling BlackwallDnsHook::handleOrderActivated from {$hookName}");
                            BlackwallDnsHook::handleOrderActivated($params);
                            error_log("DNS hook completed");
                        }
                    } catch (\Exception $e) {
                        error_log("ERROR in DNS hook: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    }
                    
                    try {
                        if (class_exists('BlackwallWelcomeHook')) {
                            error_log("Calling BlackwallWelcomeHook::handleOrderActivated from {$hookName}");
                            BlackwallWelcomeHook::handleOrderActivated($params);
                            error_log("Welcome hook completed");
                        }
                    } catch (\Exception $e) {
                        error_log("ERROR in Welcome hook: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    }
                } else {
                    error_log("Skipping hook - not a Blackwall product. Product ID: " . (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
                }
            });
            
            error_log("Successfully registered {$hookName} hook");
        } catch (\Exception $e) {
            error_log("ERROR registering {$hookName} hook: " . $e->getMessage());
        }
    }
    
    // Daily cron job to check DNS configurations for existing orders
    try {
        Hook::add("DailyCronJobs", 1, function() {
            error_log("Blackwall DailyCronJobs hook triggered");
            
            // Check if we have the Order class
            if (class_exists('Order')) {
                try {
                    // Get Blackwall orders from the last 30 days
                    $orders = Order::getOrders(['product_id' => 105, 'date_range' => '-30 days']);
                    
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
                            
                            error_log("Processing order ID: " . $order['id']);
                            
                            // Run DNS checks
                            try {
                                if (class_exists('BlackwallDnsHook')) {
                                    BlackwallDnsHook::handleOrderActivated($params);
                                    error_log("DNS check completed for order ID: " . $order['id']);
                                }
                            } catch (\Exception $e) {
                                error_log("Error in DNS check for order ID " . $order['id'] . ": " . $e->getMessage());
                            }
                            
                            // Also run welcome checks
                            try {
                                if (class_exists('BlackwallWelcomeHook')) {
                                    BlackwallWelcomeHook::handleOrderActivated($params);
                                    error_log("Welcome check completed for order ID: " . $order['id']);
                                }
                            } catch (\Exception $e) {
                                error_log("Error in welcome check for order ID " . $order['id'] . ": " . $e->getMessage());
                            }
                        }
                    } else {
                        error_log("No recent Blackwall orders found for DNS check");
                    }
                } catch (\Exception $e) {
                    error_log("Error processing orders in DailyCronJobs: " . $e->getMessage());
                }
            } else {
                error_log("ERROR: Order class not found in DailyCronJobs");
            }
        });
        
        error_log("Successfully registered DailyCronJobs hook");
    } catch (\Exception $e) {
        error_log("ERROR registering DailyCronJobs hook: " . $e->getMessage());
    }
} else {
    error_log("ERROR: Hook class not found - cannot register Blackwall hooks");
}

// Log that the hook registration is complete
error_log("===== BLACKWALL HOOKS REGISTRATION COMPLETE =====");
