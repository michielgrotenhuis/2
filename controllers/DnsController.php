<?php
/**
 * DnsController - Handles DNS operations for the Blackwall module
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class DnsController
{
    private $module_name;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param string $module_name Module name for logging
     * @param LogHelper $logger Logger instance (optional)
     */
    public function __construct($module_name, $logger = null)
    {
        $this->module_name = $module_name;
        $this->logger = $logger;
    }
    
    /**
     * Log a message if logger is available
     * 
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $data Additional data
     * @param string $error_message Error message (optional)
     * @param string $trace Error trace (optional)
     */
    private function log($level, $message, $data = [], $error_message = null, $trace = null)
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level($message, $data, $error_message, $trace);
        } else {
            // Fallback logging
            if (class_exists('Events')) {
                Events::add('Product', $this->module_name, "{$level}: {$message}", 
                $data, $error_message, $trace);
            }
        }
    }
    
    /**
     * Get A record IPs for a domain
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPs found or default IP if lookup fails
     */
    public function getDomainARecords($domain) 
    {
        // Default fallback IP if DNS lookup fails
        $default_ip = ['1.23.45.67'];
        
        try {
            // Log the DNS lookup attempt
            $this->log('debug', 'DNS Lookup', ['domain' => $domain]);
            
            // Perform DNS lookup for A records
            $dns_records = @dns_get_record($domain, DNS_A);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPs from A records
                $ips = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ip']) && !empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                }
                
                // Log the results
                $this->log('debug', 'DNS Lookup Results', ['domain' => $domain, 'ips' => $ips]);
                
                // If we found IPs, return them
                if (!empty($ips)) {
                    return $ips;
                }
            }
            
            // If lookup failed or returned no results, also try with "www." prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_A);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ips = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ip']) && !empty($record['ip'])) {
                            $www_ips[] = $record['ip'];
                        }
                    }
                    
                    // Log the www results
                    $this->log('debug', 'DNS Lookup Results (www)', ['domain' => $www_domain, 'ips' => $www_ips]);
                    
                    if (!empty($www_ips)) {
                        return $www_ips;
                    }
                }
            }
            
            // Fallback - try PHP's gethostbyname as a last resort
            $ip = gethostbyname($domain);
            if ($ip && $ip !== $domain) {
                // Log the gethostbyname result
                $this->log('debug', 'DNS Lookup (gethostbyname)', ['domain' => $domain, 'ip' => $ip]);
                return [$ip];
            }
            
            // If all lookups failed, return default IP
            $this->log('warning', 'DNS Lookup Failed', 
                ['domain' => $domain, 'using_default' => $default_ip], 
                'DNS lookup failed, using default IP');
                
            return $default_ip;
        } catch (Exception $e) {
            // Log any errors and return default IP
            $this->log('error', 'DNS Lookup Error', 
                ['domain' => $domain, 'error' => $e->getMessage()], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return $default_ip;
        }
    }

    /**
     * Get AAAA record IPs for a domain (IPv6)
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPv6 addresses found or default IPv6 if lookup fails
     */
    public function getDomainAAAARecords($domain) 
    {
        // Default fallback IPv6 if DNS lookup fails
        $default_ipv6 = ['2a01:4f8:c2c:5a72::1'];
        
        try {
            // Log the DNS lookup attempt
            $this->log('debug', 'DNS AAAA Lookup', ['domain' => $domain]);
            
            // Perform DNS lookup for AAAA records
            $dns_records = @dns_get_record($domain, DNS_AAAA);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPv6 addresses from AAAA records
                $ipv6s = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                        $ipv6s[] = $record['ipv6'];
                    }
                }
                
                // Log the results
                $this->log('debug', 'DNS AAAA Lookup Results', ['domain' => $domain, 'ipv6s' => $ipv6s]);
                
                // If we found IPv6 addresses, return them
                if (!empty($ipv6s)) {
                    return $ipv6s;
                }
            }
            
            // If lookup failed or returned no results, also try with "www." prefix
            if (strpos($domain, 'www.') !== 0) {
                $www_domain = 'www.' . $domain;
                $www_dns_records = @dns_get_record($www_domain, DNS_AAAA);
                
                if ($www_dns_records && is_array($www_dns_records) && !empty($www_dns_records)) {
                    $www_ipv6s = [];
                    foreach ($www_dns_records as $record) {
                        if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                            $www_ipv6s[] = $record['ipv6'];
                        }
                    }
                    
                    // Log the www results
                    $this->log('debug', 'DNS AAAA Lookup Results (www)', 
                        ['domain' => $www_domain, 'ipv6s' => $www_ipv6s]);
                    
                    if (!empty($www_ipv6s)) {
                        return $www_ipv6s;
                    }
                }
            }
            
            // If all lookups failed, return default IPv6
            $this->log('warning', 'DNS AAAA Lookup Failed', 
                ['domain' => $domain, 'using_default' => $default_ipv6], 
                'DNS AAAA lookup failed, using default IPv6');
                
            return $default_ipv6;
        } catch (Exception $e) {
            // Log any errors and return default IPv6
            $this->log('error', 'DNS AAAA Lookup Error', 
                ['domain' => $domain, 'error' => $e->getMessage()], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return $default_ipv6;
        }
    }

    /**
     * Check if the domain DNS is correctly pointing to our protection servers
     * 
     * @param string $domain Domain to check
     * @return bool True if DNS is correctly configured, false otherwise
     */
    public function checkDomainDnsConfiguration($domain) 
    {
        // Define the required DNS records for Blackwall protection
        $required_records = BlackwallConstants::getDnsRecords();
        
        try {
            $this->log('debug', 'DNS Configuration Check', 
                ['domain' => $domain, 'required' => $required_records]);
            
            // Get current DNS records for the domain
            $a_records = @dns_get_record($domain, DNS_A);
            $aaaa_records = @dns_get_record($domain, DNS_AAAA);
            
            // Check if any of the required A records match
            $has_valid_a_record = false;
            foreach ($a_records as $record) {
                if (isset($record['ip']) && in_array($record['ip'], $required_records['A'])) {
                    $has_valid_a_record = true;
                    break;
                }
            }
            
            // Check if any of the required AAAA records match
            $has_valid_aaaa_record = false;
            foreach ($aaaa_records as $record) {
                if (isset($record['ipv6']) && in_array($record['ipv6'], $required_records['AAAA'])) {
                    $has_valid_aaaa_record = true;
                    break;
                }
            }
            
            $result = $has_valid_a_record && $has_valid_aaaa_record;
            
            $this->log('debug', 'DNS Configuration Check Result', [
                'domain' => $domain,
                'has_valid_a' => $has_valid_a_record,
                'has_valid_aaaa' => $has_valid_aaaa_record,
                'result' => $result ? 'Configured correctly' : 'Not configured correctly'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->log('error', 'DNS Configuration Check Error', 
                ['domain' => $domain, 'error' => $e->getMessage()], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return false;
        }
    }

    /**
     * Get the DNS node that the domain is connected to
     * 
     * @param string $domain Domain to check
     * @return array|null Node information or null if not connected
     */
    public function getConnectedNode($domain)
    {
        $gatekeeper_nodes = [
            'bg-gk-01' => [
                'ipv4' => BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                'ipv6' => BlackwallConstants::GATEKEEPER_NODE_1_IPV6
            ],
            'bg-gk-02' => [
                'ipv4' => BlackwallConstants::GATEKEEPER_NODE_2_IPV4,
                'ipv6' => BlackwallConstants::GATEKEEPER_NODE_2_IPV6
            ]
        ];
        
        try {
            // Get DNS records
            $a_records = @dns_get_record($domain, DNS_A);
            $aaaa_records = @dns_get_record($domain, DNS_AAAA);
            
            $ipv4_records = [];
            $ipv6_records = [];
            
            // Extract IP addresses
            foreach ($a_records as $record) {
                if (isset($record['ip'])) {
                    $ipv4_records[] = $record['ip'];
                }
            }
            
            foreach ($aaaa_records as $record) {
                if (isset($record['ipv6'])) {
                    $ipv6_records[] = $record['ipv6'];
                }
            }
            
            // Check if domain is connected to a node
            foreach ($gatekeeper_nodes as $node_name => $node_ips) {
                if (in_array($node_ips['ipv4'], $ipv4_records) || in_array($node_ips['ipv6'], $ipv6_records)) {
                    return [
                        'name' => $node_name,
                        'ipv4' => $node_ips['ipv4'],
                        'ipv6' => $node_ips['ipv6'],
                        'ipv4_status' => in_array($node_ips['ipv4'], $ipv4_records),
                        'ipv6_status' => in_array($node_ips['ipv6'], $ipv6_records)
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            $this->log('error', 'Error checking connected node', 
                ['domain' => $domain, 'error' => $e->getMessage()], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return null;
        }
    }

    /**
     * Register the DNS check hook for a domain
     * 
     * @param string $domain Domain to check
     * @param int $order_id Order ID
     * @param int $client_id Client ID (optional)
     * @return bool Success status
     */
    public function registerDnsCheckHook($domain, $order_id, $client_id = null)
    {
        try {
            $this->log('info', 'Registering DNS Check Hook', 
                ['domain' => $domain, 'order_id' => $order_id]);
            
            // Store DNS check meta data for the hook to use
            $meta_data = [
                'domain' => $domain,
                'order_id' => $order_id,
                'check_time' => time(),
                'product_id' => 105, // Hardcoded to match the hook
                'client_id' => $client_id ?? 0
            ];
            
            // Store this in a database table or file for the hook to access
            // For this example, we'll use a simple file-based approach
            $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $order_id) . '.json';
            file_put_contents($dns_check_file, json_encode($meta_data));
            
            $this->log('info', 'DNS Check Data Stored', 
                ['file' => $dns_check_file, 'data' => $meta_data]);
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error Registering DNS Check Hook', 
                ['domain' => $domain, 'order_id' => $order_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return false;
        }
    }
    
    /**
     * Get DNS check status for a domain
     * 
     * @param string $domain Domain to check
     * @return array DNS check status information
     */
    public function getDnsCheckStatus($domain)
    {
        $gatekeeper_nodes = [
            'bg-gk-01' => [
                'ipv4' => BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                'ipv6' => BlackwallConstants::GATEKEEPER_NODE_1_IPV6
            ],
            'bg-gk-02' => [
                'ipv4' => BlackwallConstants::GATEKEEPER_NODE_2_IPV4,
                'ipv6' => BlackwallConstants::GATEKEEPER_NODE_2_IPV6
            ]
        ];
        
        // Default check result
        $dns_check = [
            'status' => false,
            'connected_to' => null,
            'ipv4_status' => false,
            'ipv6_status' => false,
            'ipv4_records' => [],
            'ipv6_records' => [],
            'missing_records' => [
                [
                    'type' => 'A',
                    'value' => BlackwallConstants::GATEKEEPER_NODE_1_IPV4
                ],
                [
                    'type' => 'AAAA',
                    'value' => BlackwallConstants::GATEKEEPER_NODE_1_IPV6
                ]
            ]
        ];
        
        // Only try to check if domain is set
        if(!empty($domain)) {
            try {
                // Get A records
                $a_records = @dns_get_record($domain, DNS_A);
                if($a_records && is_array($a_records)) {
                    foreach($a_records as $record) {
                        if(isset($record['ip'])) {
                            $dns_check['ipv4_records'][] = $record['ip'];
                            
                            // Check if matches our nodes
                            if($record['ip'] == BlackwallConstants::GATEKEEPER_NODE_1_IPV4) {
                                $dns_check['ipv4_status'] = true;
                                $dns_check['connected_to'] = 'bg-gk-01';
                            } elseif($record['ip'] == BlackwallConstants::GATEKEEPER_NODE_2_IPV4) {
                                $dns_check['ipv4_status'] = true;
                                $dns_check['connected_to'] = 'bg-gk-02';
                            }
                        }
                    }
                }
                
                // Only check AAAA if we found a matching A record
                if($dns_check['ipv4_status']) {
                    // Get AAAA records
                    $aaaa_records = @dns_get_record($domain, DNS_AAAA);
                    if($aaaa_records && is_array($aaaa_records)) {
                        foreach($aaaa_records as $record) {
                            if(isset($record['ipv6'])) {
                                $dns_check['ipv6_records'][] = $record['ipv6'];
                                
                                // Check if matches our nodes - use the same node we found for A record
                                if($dns_check['connected_to'] == 'bg-gk-01' && $record['ipv6'] == BlackwallConstants::GATEKEEPER_NODE_1_IPV6) {
                                    $dns_check['ipv6_status'] = true;
                                } elseif($dns_check['connected_to'] == 'bg-gk-02' && $record['ipv6'] == BlackwallConstants::GATEKEEPER_NODE_2_IPV6) {
                                    $dns_check['ipv6_status'] = true;
                                }
                            }
                        }
                    }
                }
                
                // Status is true if both IPv4 and IPv6 match
                $dns_check['status'] = $dns_check['ipv4_status'] && $dns_check['ipv6_status'];
                
                // Update missing records based on which node we're connected to
                if($dns_check['connected_to']) {
                    $dns_check['missing_records'] = [];
                    
                    if(!$dns_check['ipv4_status']) {
                        $dns_check['missing_records'][] = [
                            'type' => 'A',
                            'value' => $gatekeeper_nodes[$dns_check['connected_to']]['ipv4']
                        ];
                    }
                    
                    if(!$dns_check['ipv6_status']) {
                        $dns_check['missing_records'][] = [
                            'type' => 'AAAA',
                            'value' => $gatekeeper_nodes[$dns_check['connected_to']]['ipv6']
                        ];
                    }
                }
            } catch(Exception $e) {
                // Silently fail - keep default values
                $this->log('error', 'Error checking DNS status', 
                    ['domain' => $domain, 'error' => $e->getMessage()], 
                    $e->getMessage(), 
                    $e->getTraceAsString());
            }
        }
        
        return $dns_check;
    }
}
