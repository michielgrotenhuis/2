<?php
/**
 * DnsHelper - Manages DNS operations for the Blackwall module
 */
class DnsHelper
{
    private $module_name;
    private $user_id = null;
    private $dnsCache = []; // Cache for DNS lookups
    private $cacheTTL = 3600; // Cache time-to-live in seconds (1 hour)
    private $dnsFailure = false; // Track if a DNS lookup has failed
    
    // Default values to use when DNS lookups fail
    private $defaultIpv4 = ['1.2.3.4'];
    private $defaultIpv6 = ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
    
    /**
     * Constructor
     * 
     * @param string $module_name Module name for logging
     */
    public function __construct($module_name)
    {
        $this->module_name = $module_name;
        
        // Initialize cache with Blackwall's GateKeeper node IPs
        if (class_exists('BlackwallConstants')) {
            // Cache both GateKeeper nodes' IPs
            $this->defaultIpv4 = [
                BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                BlackwallConstants::GATEKEEPER_NODE_2_IPV4
            ];
            $this->defaultIpv6 = [
                BlackwallConstants::GATEKEEPER_NODE_1_IPV6,
                BlackwallConstants::GATEKEEPER_NODE_2_IPV6
            ];
        }
    }
    
    /**
     * Logging method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string|null $additional_message Additional log message
     * @param string|null $trace Error trace
     */
    private function log($level, $message, $context = [], $additional_message = null, $trace = null)
    {
        // Add timestamp to all logs
        $context['timestamp'] = date('Y-m-d H:i:s');
        
        // Check if LogHelper exists, use it; otherwise fallback to error_log
        if (class_exists('LogHelper')) {
            switch ($level) {
                case 'debug':
                    LogHelper::debug($message, $context);
                    break;
                case 'info':
                    LogHelper::info($message, $context);
                    break;
                case 'warning':
                    LogHelper::warning($message, $context);
                    break;
                case 'error':
                    if ($trace) {
                        // If trace is available, include it in the context
                        $context['trace'] = $trace;
                    }
                    LogHelper::error($message, $context);
                    break;
                default:
                    // Fallback to general logging
                    LogHelper::info($message, $context);
            }
        } else {
            // Fallback logging if LogHelper is not available
            $log_message = sprintf(
                "[%s] %s - %s %s %s",
                strtoupper($level),
                $message,
                json_encode($context),
                $additional_message ? "- {$additional_message}" : '',
                $trace ? "- TRACE: {$trace}" : ''
            );
            error_log($log_message);
        }
    }
    
    /**
     * Set user ID for operations that require it
     * 
     * @param int $user_id User ID
     * @return $this
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
        return $this;
    }
    
    /**
     * Check if DNS lookups should be skipped
     * 
     * @return boolean True if DNS lookups should be skipped
     */
    private function shouldSkipDnsLookups()
    {
        return $this->dnsFailure;
    }
    
    /**
     * Get A record IPs for a domain (with efficient caching)
     * 
     * @param string $domain Domain to lookup
     * @param bool $forceLookup Force a new DNS lookup (ignore failure state)
     * @return array Array of IPs found or default IP if lookup fails
     */
    public function getDomainARecords($domain, $forceLookup = false) {
        // Start timing
        $startTime = microtime(true);
        
        // If DNS lookups are failing and not forcing a lookup, return defaults
        if ($this->shouldSkipDnsLookups() && !$forceLookup) {
            $this->log('debug', 'Skipping DNS lookup due to previous failure', [
                'domain' => $domain,
                'using_defaults' => $this->defaultIpv4
            ]);
            return $this->defaultIpv4;
        }
        
        // Check cache first
        if (isset($this->dnsCache[$domain]['A']) && 
            isset($this->dnsCache[$domain]['timestamp']) && 
            (time() - $this->dnsCache[$domain]['timestamp'] < $this->cacheTTL)) {
            
            $this->log('debug', 'Using cached DNS A records', [
                'domain' => $domain,
                'cached_ips' => $this->dnsCache[$domain]['A']
            ]);
            
            return $this->dnsCache[$domain]['A'];
        }
        
        // Set a short timeout for DNS operations
        $old_timeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 3); // 3 second timeout
        
        try {
            // Log the DNS lookup attempt
            $this->log('info', 'DNS Lookup', ['domain' => $domain]);
            
            // Perform DNS lookup for A records with a time limit
            $dns_records = @dns_get_record($domain, DNS_A);
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            $this->log('debug', 'DNS lookup completed', [
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPs from A records
                $ips = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ip']) && !empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                }
                
                // If we found IPs, cache and return them
                if (!empty($ips)) {
                    // Cache the results
                    $this->dnsCache[$domain]['A'] = $ips;
                    $this->dnsCache[$domain]['timestamp'] = time();
                    
                    $this->log('info', 'DNS Lookup Results', [
                        'domain' => $domain, 
                        'ips' => $ips,
                        'execution_time' => round($executionTime, 4) . 's'
                    ]);
                    
                    // Reset socket timeout
                    ini_set('default_socket_timeout', $old_timeout);
                    
                    return $ips;
                }
            }
            
            // If lookup was slow, mark DNS as failing to avoid future lookups
            if ($executionTime > 2) {
                $this->dnsFailure = true;
                $this->log('warning', 'DNS lookup too slow, marking as failed', [
                    'domain' => $domain,
                    'execution_time' => round($executionTime, 4) . 's'
                ]);
            }
            
            // Log warning and use defaults
            $this->log('warning', 'DNS Lookup Failed, using defaults', [
                'domain' => $domain,
                'using_defaults' => $this->defaultIpv4,
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Cache the default values
            $this->dnsCache[$domain]['A'] = $this->defaultIpv4;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            // Reset socket timeout
            ini_set('default_socket_timeout', $old_timeout);
            
            return $this->defaultIpv4;
            
        } catch (Exception $e) {
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            // Log any errors and return default IP
            $this->log('error', 'DNS Lookup Error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Mark DNS as failing
            $this->dnsFailure = true;
            
            // Cache the default IP on error
            $this->dnsCache[$domain]['A'] = $this->defaultIpv4;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            // Reset socket timeout
            ini_set('default_socket_timeout', $old_timeout);
            
            return $this->defaultIpv4;
        }
    }

    /**
     * Get AAAA record IPs for a domain (IPv6) with efficient caching
     * 
     * @param string $domain Domain to lookup
     * @param bool $forceLookup Force a new DNS lookup (ignore failure state)
     * @return array Array of IPv6 addresses found or default IPv6 if lookup fails
     */
    public function getDomainAAAARecords($domain, $forceLookup = false) {
        // Start timing
        $startTime = microtime(true);
        
        // If DNS lookups are failing and not forcing a lookup, return defaults
        if ($this->shouldSkipDnsLookups() && !$forceLookup) {
            $this->log('debug', 'Skipping DNS AAAA lookup due to previous failure', [
                'domain' => $domain,
                'using_defaults' => $this->defaultIpv6
            ]);
            return $this->defaultIpv6;
        }
        
        // Check cache first
        if (isset($this->dnsCache[$domain]['AAAA']) && 
            isset($this->dnsCache[$domain]['timestamp']) && 
            (time() - $this->dnsCache[$domain]['timestamp'] < $this->cacheTTL)) {
            
            $this->log('debug', 'Using cached DNS AAAA records', [
                'domain' => $domain,
                'cached_ipv6s' => $this->dnsCache[$domain]['AAAA']
            ]);
            
            return $this->dnsCache[$domain]['AAAA'];
        }
        
        // Set a short timeout for DNS operations
        $old_timeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 3); // 3 second timeout
        
        try {
            // Log the DNS lookup attempt
            $this->log('info', 'DNS AAAA Lookup', ['domain' => $domain]);
            
            // Perform DNS lookup for AAAA records with a time limit
            $dns_records = @dns_get_record($domain, DNS_AAAA);
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            $this->log('debug', 'DNS AAAA lookup completed', [
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Check if we got valid results
            if ($dns_records && is_array($dns_records) && !empty($dns_records)) {
                // Extract IPv6 addresses from AAAA records
                $ipv6s = [];
                foreach ($dns_records as $record) {
                    if (isset($record['ipv6']) && !empty($record['ipv6'])) {
                        $ipv6s[] = $record['ipv6'];
                    }
                }
                
                // If we found IPv6 addresses, cache and return them
                if (!empty($ipv6s)) {
                    // Cache the results
                    $this->dnsCache[$domain]['AAAA'] = $ipv6s;
                    $this->dnsCache[$domain]['timestamp'] = time();
                    
                    $this->log('info', 'DNS AAAA Lookup Results', [
                        'domain' => $domain, 
                        'ipv6s' => $ipv6s,
                        'execution_time' => round($executionTime, 4) . 's'
                    ]);
                    
                    // Reset socket timeout
                    ini_set('default_socket_timeout', $old_timeout);
                    
                    return $ipv6s;
                }
            }
            
            // If lookup was slow, mark DNS as failing to avoid future lookups
            if ($executionTime > 2) {
                $this->dnsFailure = true;
                $this->log('warning', 'DNS AAAA lookup too slow, marking as failed', [
                    'domain' => $domain,
                    'execution_time' => round($executionTime, 4) . 's'
                ]);
            }
            
            // Log warning and use defaults
            $this->log('warning', 'DNS AAAA Lookup Failed, using defaults', [
                'domain' => $domain,
                'using_defaults' => $this->defaultIpv6,
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Cache the default values
            $this->dnsCache[$domain]['AAAA'] = $this->defaultIpv6;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            // Reset socket timeout
            ini_set('default_socket_timeout', $old_timeout);
            
            return $this->defaultIpv6;
            
        } catch (Exception $e) {
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            // Log any errors and return default IPv6
            $this->log('error', 'DNS AAAA Lookup Error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            // Mark DNS as failing
            $this->dnsFailure = true;
            
            // Cache the default IPv6 on error
            $this->dnsCache[$domain]['AAAA'] = $this->defaultIpv6;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            // Reset socket timeout
            ini_set('default_socket_timeout', $old_timeout);
            
            return $this->defaultIpv6;
        }
    }

    /**
     * Check if the domain DNS is correctly pointing to our protection servers
     * With safety timeouts
     * 
     * @param string $domain Domain to check
     * @return bool True if DNS is correctly configured, false otherwise
     */
    public function checkDomainDnsConfiguration($domain) {
        // Set a time limit for this function
        $startTime = microtime(true);
        
        if (!class_exists('BlackwallConstants')) {
            require_once(dirname(dirname(__FILE__)) . '/BlackwallConstants.php');
        }
        
        // Define the required DNS records for Blackwall protection
        $required_records = BlackwallConstants::getDnsRecords();
        
        try {
            $this->log('info', 'Starting DNS Configuration Check', ['domain' => $domain]);
            
            // Get current DNS records for the domain - use cached values if available
            $a_ips = $this->getDomainARecords($domain);
            $aaaa_ips = $this->getDomainAAAARecords($domain);
            
            // Check if any of the required A records match
            $has_valid_a_record = false;
            foreach ($a_ips as $ip) {
                if (in_array($ip, $required_records['A'])) {
                    $has_valid_a_record = true;
                    break;
                }
            }
            
            // Check if any of the required AAAA records match
            $has_valid_aaaa_record = false;
            foreach ($aaaa_ips as $ipv6) {
                if (in_array($ipv6, $required_records['AAAA'])) {
                    $has_valid_aaaa_record = true;
                    break;
                }
            }
            
            $result = $has_valid_a_record && $has_valid_aaaa_record;
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            $this->log('info', 'DNS Configuration Check Result', [
                'domain' => $domain,
                'has_valid_a' => $has_valid_a_record,
                'has_valid_aaaa' => $has_valid_aaaa_record,
                'result' => $result ? 'Configured correctly' : 'Not configured correctly',
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            return $result;
        } catch (Exception $e) {
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            $this->log('error', 'DNS Configuration Check Error', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4) . 's'
            ]);
                
            return false;
        }
    }

    /**
     * Get the DNS node that the domain is connected to
     * with safety timeouts
     * 
     * @param string $domain Domain to check
     * @return array|null Node information or null if not connected
     */
    public function getConnectedNode($domain)
    {
        // Start timing
        $startTime = microtime(true);
        
        if (!class_exists('BlackwallConstants')) {
            require_once(dirname(dirname(__FILE__)) . '/BlackwallConstants.php');
        }
        
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
            // Get DNS records - use cached values if available
            $ipv4_records = $this->getDomainARecords($domain);
            $ipv6_records = $this->getDomainAAAARecords($domain);
            
            // Check if domain is connected to a node
            foreach ($gatekeeper_nodes as $node_name => $node_ips) {
                if (in_array($node_ips['ipv4'], $ipv4_records) || in_array($node_ips['ipv6'], $ipv6_records)) {
                    // Calculate execution time
                    $executionTime = microtime(true) - $startTime;
                    
                    $this->log('info', 'Found connected node', [
                        'domain' => $domain,
                        'node' => $node_name,
                        'execution_time' => round($executionTime, 4) . 's'
                    ]);
                    
                    return [
                        'name' => $node_name,
                        'ipv4' => $node_ips['ipv4'],
                        'ipv6' => $node_ips['ipv6'],
                        'ipv4_status' => in_array($node_ips['ipv4'], $ipv4_records),
                        'ipv6_status' => in_array($node_ips['ipv6'], $ipv6_records)
                    ];
                }
            }
            
            // No node found
            $executionTime = microtime(true) - $startTime;
            $this->log('info', 'No connected node found', [
                'domain' => $domain,
                'execution_time' => round($executionTime, 4) . 's'
            ]);
            
            return null;
        } catch (Exception $e) {
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            $this->log('error', 'Error checking connected node', [
                'domain' => $domain, 
                'error' => $e->getMessage(),
                'execution_time' => round($executionTime, 4) . 's'
            ]);
                
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
            $this->log('info', 'Registering DNS Check Hook', [
                'domain' => $domain, 
                'order_id' => $order_id
            ]);
            
            // Store DNS check meta data for the hook to use
            $meta_data = [
                'domain' => $domain,
                'order_id' => $order_id,
                'check_time' => time(),
                'product_id' => 105, // Hardcoded to match the hook
                'client_id' => $client_id ?? $this->user_id ?? 0
            ];
            
            // Store this in a database table or file for the hook to access
            // For this example, we'll use a simple file-based approach
            $dns_check_file = sys_get_temp_dir() . '/blackwall_dns_check_' . md5($domain . $order_id) . '.json';
            file_put_contents($dns_check_file, json_encode($meta_data));
            
            $this->log('info', 'DNS Check Data Stored', [
                'file' => $dns_check_file, 
                'data' => $meta_data
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error Registering DNS Check Hook', [
                'domain' => $domain, 
                'order_id' => $order_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Clear DNS cache
     * 
     * @param string $domain Optional domain to clear from cache
     * @return bool Success status
     */
    public function clearDnsCache($domain = null)
    {
        if ($domain && isset($this->dnsCache[$domain])) {
            unset($this->dnsCache[$domain]);
            $this->log('debug', 'Cleared DNS cache for domain', ['domain' => $domain]);
        } else {
            $this->dnsCache = [];
            $this->log('debug', 'Cleared all DNS cache');
        }
        
        // Reset the failure flag
        $this->dnsFailure = false;
        
        return true;
    }
    
    /**
     * Get DNS check status for a domain
     * Using cached values to prevent hanging
     * 
     * @param string $domain Domain to check
     * @return array DNS check status information
     */
    public function getDnsCheckStatus($domain)
    {
        // Start timing
        $startTime = microtime(true);
        
        if (!class_exists('BlackwallConstants')) {
            require_once(dirname(dirname(__FILE__)) . '/BlackwallConstants.php');
        }
        
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
                $this->log('info', 'Starting DNS check status', ['domain' => $domain]);
                
                // Get A and AAAA records - use cached values if available
                $dns_check['ipv4_records'] = $this->getDomainARecords($domain);
                
                // Check if matches our nodes
                foreach ($dns_check['ipv4_records'] as $ip) {
                    if($ip == BlackwallConstants::GATEKEEPER_NODE_1_IPV4) {
                        $dns_check['ipv4_status'] = true;
                        $dns_check['connected_to'] = 'bg-gk-01';
                        break;
                    } elseif($ip == BlackwallConstants::GATEKEEPER_NODE_2_IPV4) {
                        $dns_check['ipv4_status'] = true;
                        $dns_check['connected_to'] = 'bg-gk-02';
                        break;
                    }
                }
                
                // Only check AAAA if we found a matching A record
                if($dns_check['ipv4_status']) {
                    // Get AAAA records
                    $dns_check['ipv6_records'] = $this->getDomainAAAARecords($domain);
                    
                    // Check if matches our nodes - use the same node we found for A record
                    foreach ($dns_check['ipv6_records'] as $ipv6) {
                        if($dns_check['connected_to'] == 'bg-gk-01' && $ipv6 == BlackwallConstants::GATEKEEPER_NODE_1_IPV6) {
                            $dns_check['ipv6_status'] = true;
                            break;
                        } elseif($dns_check['connected_to'] == 'bg-gk-02' && $ipv6 == BlackwallConstants::GATEKEEPER_NODE_2_IPV6) {
                            $dns_check['ipv6_status'] = true;
                            break;
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
                
                // Calculate execution time
                $executionTime = microtime(true) - $startTime;
                
                $this->log('info', 'DNS check status completed', [
                    'domain' => $domain,
                    'status' => $dns_check['status'] ? 'Connected' : 'Not connected',
                    'node' => $dns_check['connected_to'],
                    'execution_time' => round($executionTime, 4) . 's'
                ]);
                
            } catch(Exception $e) {
                // Calculate execution time
                $executionTime = microtime(true) - $startTime;
                
                // Silently fail - keep default values
                $this->log('error', 'Error checking DNS status', [
                    'domain' => $domain, 
                    'error' => $e->getMessage(),
                    'execution_time' => round($executionTime, 4) . 's'
                ]);
            }
        }
        
        return $dns_check;
    }
}
