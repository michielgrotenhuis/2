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
    
    /**
     * Constructor
     * 
     * @param string $module_name Module name for logging
     */
    public function __construct($module_name)
    {
        $this->module_name = $module_name;
    }
    
    /**
     * Logging method (replacing previous static save_log())
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @param string|null $additional_message Additional log message
     * @param string|null $trace Error trace
     */
    private function log($level, $message, $context = [], $additional_message = null, $trace = null)
    {
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
     * Get A record IPs for a domain (with caching)
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPs found or default IP if lookup fails
     */
    public function getDomainARecords($domain) {
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
        
        // Default fallback IP if DNS lookup fails
        $default_ip = ['1.23.45.67'];
        
        try {
            // Log the DNS lookup attempt
            $this->log('info', 'DNS Lookup', ['domain' => $domain]);
            
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
                $this->log('info', 'DNS Lookup Results', ['domain' => $domain, 'ips' => $ips]);
                
                // If we found IPs, cache and return them
                if (!empty($ips)) {
                    // Cache the results
                    $this->dnsCache[$domain]['A'] = $ips;
                    $this->dnsCache[$domain]['timestamp'] = time();
                    
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
                    $this->log('info', 'DNS Lookup Results (www)', ['domain' => $www_domain, 'ips' => $www_ips]);
                    
                    if (!empty($www_ips)) {
                        // Cache the results
                        $this->dnsCache[$domain]['A'] = $www_ips;
                        $this->dnsCache[$domain]['timestamp'] = time();
                        
                        return $www_ips;
                    }
                }
            }
            
            // Fallback - try PHP's gethostbyname as a last resort
            $ip = gethostbyname($domain);
            if ($ip && $ip !== $domain) {
                // Log the gethostbyname result
                $this->log('info', 'DNS Lookup (gethostbyname)', ['domain' => $domain, 'ip' => $ip]);
                
                // Cache the result
                $this->dnsCache[$domain]['A'] = [$ip];
                $this->dnsCache[$domain]['timestamp'] = time();
                
                return [$ip];
            }
            
            // If all lookups failed, cache and return default IP
            $this->log('warning', 'DNS Lookup Failed', 
                ['domain' => $domain, 'using_default' => $default_ip],
                'DNS lookup failed, using default IP'
            );
            
            // Cache the default IP
            $this->dnsCache[$domain]['A'] = $default_ip;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            return $default_ip;
        } catch (Exception $e) {
            // Log any errors and return default IP
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
                ['domain' => $domain, 'order_id' => $order_id]
            );
            
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
            
            $this->log('info', 'DNS Check Data Stored', 
                ['file' => $dns_check_file, 'data' => $meta_data]
            );
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error Registering DNS Check Hook', 
                ['domain' => $domain, 'order_id' => $order_id],
                $e->getMessage(),
                $e->getTraceAsString()
            );
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
        
        return true;
    }
    
    /**
     * Get DNS check status for a domain
     * 
     * @param string $domain Domain to check
     * @return array DNS check status information
     */
    public function getDnsCheckStatus($domain)
    {
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
}'DNS Lookup Error', 
                ['domain' => $domain, 'error' => $e->getMessage()],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            
            // Cache the default IP on error
            $this->dnsCache[$domain]['A'] = $default_ip;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            return $default_ip;
        }
    }

    /**
     * Get AAAA record IPs for a domain (IPv6) with caching
     * 
     * @param string $domain Domain to lookup
     * @return array Array of IPv6 addresses found or default IPv6 if lookup fails
     */
    public function getDomainAAAARecords($domain) {
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
        
        // Default fallback IPv6 if DNS lookup fails
        $default_ipv6 = ['2a01:4f8:c2c:5a72::1'];
        
        try {
            // Log the DNS lookup attempt
            $this->log('info', 'DNS AAAA Lookup', ['domain' => $domain]);
            
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
                $this->log('info', 'DNS AAAA Lookup Results', ['domain' => $domain, 'ipv6s' => $ipv6s]);
                
                // If we found IPv6 addresses, cache and return them
                if (!empty($ipv6s)) {
                    // Cache the results
                    $this->dnsCache[$domain]['AAAA'] = $ipv6s;
                    $this->dnsCache[$domain]['timestamp'] = time();
                    
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
                    $this->log('info', 'DNS AAAA Lookup Results (www)', 
                        ['domain' => $www_domain, 'ipv6s' => $www_ipv6s]
                    );
                    
                    if (!empty($www_ipv6s)) {
                        // Cache the results
                        $this->dnsCache[$domain]['AAAA'] = $www_ipv6s;
                        $this->dnsCache[$domain]['timestamp'] = time();
                        
                        return $www_ipv6s;
                    }
                }
            }
            
            // If all lookups failed, cache and return default IPv6
            $this->log('warning', 'DNS AAAA Lookup Failed', 
                ['domain' => $domain, 'using_default' => $default_ipv6],
                'DNS AAAA lookup failed, using default IPv6'
            );
            
            // Cache the default IPv6
            $this->dnsCache[$domain]['AAAA'] = $default_ipv6;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            return $default_ipv6;
        } catch (Exception $e) {
            // Log any errors and return default IPv6
            $this->log('error', 'DNS AAAA Lookup Error', 
                ['domain' => $domain, 'error' => $e->getMessage()],
                $e->getMessage(),
                $e->getTraceAsString()
            );
            
            // Cache the default IPv6 on error
            $this->dnsCache[$domain]['AAAA'] = $default_ipv6;
            $this->dnsCache[$domain]['timestamp'] = time();
            
            return $default_ipv6;
        }
    }

    /**
     * Check if the domain DNS is correctly pointing to our protection servers
     * 
     * @param string $domain Domain to check
     * @return bool True if DNS is correctly configured, false otherwise
     */
    public function checkDomainDnsConfiguration($domain) {
        if (!class_exists('BlackwallConstants')) {
            require_once(dirname(dirname(__FILE__)) . '/BlackwallConstants.php');
        }
        
        // Define the required DNS records for Blackwall protection
        $required_records = BlackwallConstants::getDnsRecords();
        
        try {
            $this->log('info', 'DNS Configuration Check', 
                ['domain' => $domain, 'required' => $required_records]
            );
            
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
            
            $this->log('info', 'DNS Configuration Check Result', [
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
                $e->getTraceAsString()
            );
                
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
            $this->log('error',
