<?php
/**
 * Register Hooks for Blackwall Module - Extended Version
 * This file registers hooks for many different events that might trigger when an order is created/activated
 */

// Log that the hook file was included
error_log("===== BLACKWALL EXTENDED HOOKS REGISTRATION FILE LOADED =====");

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

// Create welcome ticket function - will be used by all hooks
function createBlackwallWelcomeTicket($params = []) {
    // Check if params contain the necessary data
    if (!isset($params['id']) || !isset($params['product_id'])) {
        error_log("Missing required params for createBlackwallWelcomeTicket");
        return;
    }
    
    // Check if this is a Blackwall product
    if ($params['product_id'] != 105) {
        error_log("Not a Blackwall product (ID: " . $params['product_id'] . ")");
        return;
    }
    
    error_log("Creating welcome ticket for Blackwall order ID: " . $params['id']);
    
    // Call the welcome hook if it exists
    if (class_exists('BlackwallWelcomeHook')) {
        try {
            BlackwallWelcomeHook::handleOrderActivated($params);
            error_log("Welcome ticket creation attempted for order ID: " . $params['id']);
        } catch (Exception $e) {
            error_log("Error creating welcome ticket: " . $e->getMessage());
        }
    } else {
        error_log("BlackwallWelcomeHook class not found");
    }
}

// Register the hooks for order creation/activation
if (class_exists('Hook')) {
    error_log("Hook class exists, registering extended hooks");
    
    // Register multiple hooks for different events
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
        Hook::add($hookName, 1, function($params = []) use ($hookName, $hookDescription) {
            error_log("Blackwall {$hookName} hook triggered ({$hookDescription})");
            error_log("Params: " . json_encode($params));
            
            createBlackwallWelcomeTicket($params);
        });
        
        error_log("Registered Blackwall {$hookName} hook ({$hookDescription})");
    }
    
    // Also add a manual trigger for admin area
    Hook::add("AdminArea", 1, function($params = []) {
        // Only execute this on a specific admin action
        $page = isset($_GET['controller']) ? $_GET['controller'] : '';
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        // Check for creating welcome ticket action
        if ($page == 'blackwall_ticket' && $action == 'create') {
            error_log("Manual welcome ticket creation requested");
            
            $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
            
            if ($order_id > 0) {
                error_log("Attempting to create welcome ticket for order ID: " . $order_id);
                
                // Load order data
                if (class_exists('Order')) {
                    $order = Order::get($order_id);
                    
                    if ($order) {
                        // Prepare params
                        $params = [
                            'id' => $order_id,
                            'product_id' => $order['product_id'],
                            'options' => $order['options'],
                            'owner_id' => $order['owner_id']
                        ];
                        
                        // Create welcome ticket
                        createBlackwallWelcomeTicket($params);
                        
                        echo json_encode(['status' => 'success', 'message' => 'Welcome ticket created']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Order class not found']);
                }
                
                exit;
            }
        }
    });
    
    error_log("Registered Blackwall AdminArea hook for manual ticket creation");
    
    // Daily cron job to check DNS configurations and create welcome tickets for existing orders
    Hook::add("DailyCronJobs", 1, function() {
        error_log("Blackwall DailyCronJobs hook triggered");
        
        // Check if we have the Order class
        if (class_exists('Order')) {
            try {
                // Get Blackwall orders from the last 7 days
                $orders = Order::getOrders(['product_id' => 105, 'date_range' => '-7 days']);
                
                if ($orders && is_array($orders)) {
                    error_log("Found " . count($orders) . " recent Blackwall orders");
                    
                    foreach($orders as $order) {
                        // Convert to the format expected by the handler
                        $params = [
                            'id' => $order['id'],
                            'product_id' => $order['product_id'],
                            'options' => $order['options'],
                            'owner_id' => $order['owner_id']
                        ];
                        
                        // Run DNS checks
                        if (class_exists('BlackwallDnsHook')) {
                            try {
                                BlackwallDnsHook::handleOrderActivated($params);
                            } catch (Exception $e) {
                                error_log("Error in DNS check: " . $e->getMessage());
                            }
                        }
                        
                        // Create welcome tickets if they don't exist yet
                        createBlackwallWelcomeTicket($params);
                    }
                    
                    error_log("Blackwall DailyCronJobs processed " . count($orders) . " orders");
                }
            } catch (Exception $e) {
                error_log("Blackwall DailyCronJobs error: " . $e->getMessage());
            }
        }
    });
    
    error_log("Registered Blackwall DailyCronJobs hook");
} else {
    error_log("ERROR: Hook class not found - cannot register Blackwall hooks");
}

// Log that the hook registration is complete
error_log("===== BLACKWALL EXTENDED HOOKS REGISTRATION COMPLETE =====");
