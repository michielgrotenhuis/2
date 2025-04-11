<?php
/**
 * Register Hooks for Blackwall Module
 * This file registers all necessary hooks for the Blackwall module
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

// Include the DNS Hook class
require_once(__DIR__ . '/DnsHook.php');

// Register the hook for order creation/activation
if (class_exists('Hook')) {
    // Hook into order creation - when a new order is created
    Hook::add("OrderCreated", 1, function($params = []) {
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall OrderCreated hook triggered for order ID: " . $params['id']);
            // Call the DNS verification and ticket creation functionality
            BlackwallDnsHook::handleOrderActivated($params);
        }
    });
    
    // Hook into order activation - when an order is activated
    Hook::add("OrderActivation", 1, function($params = []) {
        // Check if this is Blackwall product (ID 105)
        if (isset($params['product_id']) && $params['product_id'] == 105) {
            error_log("Blackwall OrderActivation hook triggered for order ID: " . $params['id']);
            // Call the DNS verification and ticket creation functionality
            BlackwallDnsHook::handleOrderActivated($params);
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
                    foreach($orders as $order) {
                        // Convert to the format expected by the handler
                        $params = [
                            'id' => $order['id'],
                            'product_id' => $order['product_id'],
                            'options' => $order['options'],
                            'owner_id' => $order['owner_id']
                        ];
                        
                        BlackwallDnsHook::handleOrderActivated($params);
                    }
                    
                    error_log("Blackwall DailyCronJobs processed " . count($orders) . " orders");
                }
            } catch (Exception $e) {
                error_log("Blackwall DailyCronJobs error: " . $e->getMessage());
            }
        }
    });
}
