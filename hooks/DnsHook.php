<?php
/**
 * BlackwallDnsHook - Handles DNS verification hooks for Blackwall module
 * Improved version with better logging and error handling
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class BlackwallDnsHook
{
    /**
     * Handle order activated hook
     * 
     * @param array $params Hook parameters
     * @return void
     */
    public static function handleOrderActivated($params = [])
    {
        // Log all parameters for debugging
        error_log("DNS Hook - handleOrderActivated called with parameters: " . json_encode($params));
        
        // Check if this is Blackwall product (ID 105)
        if (!isset($params['product_id']) || ($params['product_id'] != 105 && $params['product_id'] != '105')) {
            error_log("DNS Hook - Skipping - Not a Blackwall product. Product ID: " . 
                     (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
            return;
        }
        
        $log_paths = [
            '/tmp/blackwall_dns_hook.log',
            __DIR__ . '/../logs/blackwall_dns_hook.log'
        ];
        
        // Log function with timestamp
        $debug_log = function($message, $data = null) use ($log_paths) {
            $timestamp = date('Y-m-d H:i:s');
            $log_message = "[{$timestamp}] {$message}\n";
            
            if ($data !== null) {
                if (is_array($data) || is_object($data)) {
                    $log_message .= print_r($data, true) . "\n";
                } else {
                    $log_message .= $data . "\n";
                }
            }
            
            // Try to write to both locations
            foreach ($log_paths as $log_path) {
                try {
                    // Create directory if it doesn't exist
                    $dir = dirname($log_path);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    
                    @file_put_contents($log_path, $log_message, FILE_APPEND);
                } catch (\Exception $e) {
                    // Silently fail if we can't write to this location
                    error_log("Failed to write to log file: " . $e->getMessage());
                }
            }
            
            // Also log to PHP error log for easier debugging
            error_log("BlackwallDnsHook: " . $message);
        };
        
        $debug_log("Blackwall DNS Hook triggered for order ID: " . $params['id']);
        $debug_log("Full params:", $params);
        
        // Get the domain name from order options
        $domain = isset($params['options']) && isset($params['options']['domain']) 
            ? $params['options']['domain'] 
            : '';
            
        if (empty($domain) && isset($params['options']) && isset($params['options']['config']) && 
            isset($params['options']['config']['blackwall_domain'])) {
            $domain = $params['options']['config']['blackwall_domain'];
        }
        
        $debug_log("Domain: {$domain}");
            
        if (!empty($domain)) {
            // Define the required DNS records for Blackwall protection
            $required_records = BlackwallConstants::getDnsRecords();
            
            // Function to check DNS configuration
            $check_dns_configuration = function($domain, $required_records) use ($debug_log) {
                $debug_log("Starting DNS check for domain: {$domain}");
                
                try {
                    // Get current DNS records for the domain
                    $a_records = @dns_get_record($domain, DNS_A);
                    $debug_log("A records found:", $a_records);
                    
                    $aaaa_records = @dns_get_record($domain, DNS_AAAA);
                    $debug_log("AAAA records found:", $aaaa_records);
                    
                    // Check if any of the required A records match
                    $has_valid_a_record = false;
                    foreach ($a_records as $record) {
                        if (isset($record['ip']) && in_array($record['ip'], $required_records['A'])) {
                            $has_valid_a_record = true;
                            $debug_log("Found valid A record: {$record['ip']}");
                            break;
                        }
                    }
                    
                    // Check if any of the required AAAA records match
                    $has_valid_aaaa_record = false;
                    foreach ($aaaa_records as $record) {
                        if (isset($record['ipv6']) && in_array($record['ipv6'], $required_records['AAAA'])) {
                            $has_valid_aaaa_record = true;
                            $debug_log("Found valid AAAA record: {$record['ipv6']}");
                            break;
                        }
                    }
                    
                    $result = $has_valid_a_record && $has_valid_aaaa_record;
                    $debug_log("DNS check result: " . ($result ? 'Correctly configured' : 'Not correctly configured'));
                    return $result;
                } catch (\Exception $e) {
                    $debug_log("Exception in DNS check: " . $e->getMessage());
                    $debug_log("Exception trace: " . $e->getTraceAsString());
                    return false; // Default to false on error
                }
            };
            
            // Function to check if the time has expired
            $is_time_expired = function($order_id, $wait_time_hours) use ($debug_log, $domain) {
                $debug_log("Checking time expiration for order ID: {$order_id}, wait time: {$wait_time_hours} hours");
                
                // Check if there's a stored DNS check time
                $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $order_id) . '.json';
                $debug_log("DNS check file path: {$dns_check_file}");
                
                if (file_exists($dns_check_file)) {
                    $dns_check_data = json_decode(file_get_contents($dns_check_file), true);
                    $debug_log("DNS check file contents:", $dns_check_data);
                    
                    if (isset($dns_check_data['check_time'])) {
                        $activation_time = $dns_check_data['check_time'];
                        $debug_log("Found stored DNS check time: " . date('Y-m-d H:i:s', $activation_time));
                    } else {
                        // Fallback to current time minus 1 hour
                        $activation_time = time() - 3600;
                        $debug_log("No check time in data, using fallback time: " . date('Y-m-d H:i:s', $activation_time));
                    }
                } else {
                    // If file doesn't exist, create it now to start the timer
                    $activation_time = time();
                    $debug_log("No DNS check file exists, creating new one with current time: " . date('Y-m-d H:i:s', $activation_time));
                    
                    $dns_check_data = [
                        'domain' => $domain,
                        'order_id' => $order_id,
                        'check_time' => $activation_time,
                        'product_id' => 105,
                        'client_id' => isset($params['owner_id']) ? $params['owner_id'] : 0
                    ];
                    
                    $dir = dirname($dns_check_file);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    
                    $write_result = @file_put_contents($dns_check_file, json_encode($dns_check_data));
                    $debug_log("Created new DNS check file: " . ($write_result ? "Success" : "Failed"));
                    
                    // Return false since we just started the timer
                    return false;
                }
                
                $current_time = time();
                $wait_time_seconds = $wait_time_hours * 3600; // Convert hours to seconds
                
                $time_difference = $current_time - $activation_time;
                $hours_elapsed = round($time_difference / 3600, 2);
                
                $debug_log("Current time: " . date('Y-m-d H:i:s', $current_time));
                $debug_log("Time difference: {$time_difference} seconds ({$hours_elapsed} hours)");
                $debug_log("Required wait time: {$wait_time_seconds} seconds");
                
                $result = ($time_difference >= $wait_time_seconds);
                $debug_log("Wait time expired: " . ($result ? 'Yes' : 'No'));
                
                return $result;
            };
            
            // Check if DNS is correctly configured
            $is_dns_configured = $check_dns_configuration($domain, $required_records);
            
            // Wait for the specified time before sending ticket (6 hours)
            $wait_time = 0.1; // TEMPORARY: Set to 6 minutes (0.1 hours) for testing, change back to 6 hours in production
            $time_expired = $is_time_expired($params['id'], $wait_time);
            
            // Function to create a ticket
            $create_ticket = function($params, $domain, $required_records) use ($debug_log) {
                $debug_log("Creating ticket for domain: {$domain}");
                
                try {
                    // Get client ID from order parameters
                    $client_id = isset($params['owner_id']) ? $params['owner_id'] : 0;
                    
                    if (!$client_id) {
                        $debug_log("Client ID not found in params");
                        return false;
                    }
                    
                    $debug_log("Client ID: {$client_id}");
                    
                    // Initialize Blackwall module instance to use its methods
                    if (!class_exists('Blackwall')) {
                        $debug_log("Blackwall class not found, attempting to load it");
                        require_once(dirname(__DIR__) . '/Blackwall.php');
                    }
                    
                    if (!class_exists('Blackwall')) {
                        $debug_log("ERROR: Still can't find Blackwall class after trying to load it");
                        return false;
                    }
                    
                    $debug_log("Creating new Blackwall instance");
                    $blackwall = new Blackwall();
                    
                    // Create the ticket using the module's method
                    $debug_log("About to call create_dns_configuration_ticket for domain: {$domain}");
                    $result = $blackwall->create_dns_configuration_ticket($domain, $client_id, $params['id']);
                    
                    $debug_log("Ticket creation result: " . ($result ? "Success" : "Failed"));
                    
                    // If ticket was created successfully, update the DNS check file to prevent duplicate tickets
                    if ($result) {
                        $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $params['id']) . '.json';
                        if (file_exists($dns_check_file)) {
                            $dns_check_data = json_decode(file_get_contents($dns_check_file), true);
                            $dns_check_data['ticket_created'] = true;
                            $dns_check_data['ticket_time'] = time();
                            $write_result = file_put_contents($dns_check_file, json_encode($dns_check_data));
                            $debug_log("Updated DNS check file with ticket information: " . ($write_result ? "Success" : "Failed"));
                        }
                    }
                    
                    return $result;
                } catch (\Exception $e) {
                    $debug_log("Exception occurred when creating ticket: " . $e->getMessage());
                    $debug_log("Exception trace: " . $e->getTraceAsString());
                    return false;
                }
            };
            
            // If DNS is not configured correctly and wait time has passed
            if (!$is_dns_configured && $time_expired) {
                $debug_log("DNS not configured correctly and wait time expired - creating ticket");
                
                // Check if we've already created a ticket for this order
                $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $params['id']) . '.json';
                $ticket_already_created = false;
                
                if (file_exists($dns_check_file)) {
                    $dns_check_data = json_decode(file_get_contents($dns_check_file), true);
                    $ticket_already_created = isset($dns_check_data['ticket_created']) && $dns_check_data['ticket_created'] === true;
                    $debug_log("DNS check file exists. Ticket already created: " . ($ticket_already_created ? "Yes" : "No"));
                } else {
                    $debug_log("DNS check file does not exist, no ticket has been created yet");
                }
                
                if ($ticket_already_created) {
                    $debug_log("Ticket has already been created for this order - skipping");
                } else {
                    $ticket_result = $create_ticket($params, $domain, $required_records);
                    $debug_log("Final ticket creation result: " . ($ticket_result ? "Success" : "Failure"));
                }
            } else {
                $debug_log("Not creating ticket - DNS configured: " . ($is_dns_configured ? 'Yes' : 'No') . 
                           ", Time expired: " . ($time_expired ? 'Yes' : 'No'));
            }
        } else {
            $debug_log("No domain found in order options");
        }
    }
}
