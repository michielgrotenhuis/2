<?php
/**
 * WebsiteModel - Model for website data in the Blackwall module
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class WebsiteModel
{
    private $domain;
    private $user_id;
    private $status;
    private $ip_addresses = [];
    private $ipv6_addresses = [];
    private $subdomains = [];
    private $settings = [];
    private $created_at;
    private $updated_at;
    
    /**
     * Constructor
     * 
     * @param string $domain Domain name
     * @param int $user_id User ID
     * @param string $status Status
     */
    public function __construct($domain, $user_id, $status = null)
    {
        $this->domain = $domain;
        $this->user_id = $user_id;
        $this->status = $status ?: BlackwallConstants::STATUS_SETUP;
        $this->created_at = time();
        $this->updated_at = time();
        // Set default settings
        $this->settings = BlackwallConstants::getDefaultWebsiteSettings();
    }
    
    /**
     * Get domain name
     * 
     * @return string Domain name
     */
    public function getDomain()
    {
        return $this->domain;
    }
    
    /**
     * Get user ID
     * 
     * @return int User ID
     */
    public function getUserId()
    {
        return $this->user_id;
    }
    
    /**
     * Get status
     * 
     * @return string Status
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Set status
     * 
     * @param string $status Status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get IP addresses
     * 
     * @return array IP addresses
     */
    public function getIpAddresses()
    {
        return $this->ip_addresses;
    }
    
    /**
     * Set IP addresses
     * 
     * @param array $ip_addresses IP addresses
     * @return $this
     */
    public function setIpAddresses(array $ip_addresses)
    {
        $this->ip_addresses = $ip_addresses;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Add IP address
     * 
     * @param string $ip_address IP address
     * @return $this
     */
    public function addIpAddress($ip_address)
    {
        if (!in_array($ip_address, $this->ip_addresses)) {
            $this->ip_addresses[] = $ip_address;
            $this->updated_at = time();
        }
        return $this;
    }
    
    /**
     * Get IPv6 addresses
     * 
     * @return array IPv6 addresses
     */
    public function getIpv6Addresses()
    {
        return $this->ipv6_addresses;
    }
    
    /**
     * Set IPv6 addresses
     * 
     * @param array $ipv6_addresses IPv6 addresses
     * @return $this
     */
    public function setIpv6Addresses(array $ipv6_addresses)
    {
        $this->ipv6_addresses = $ipv6_addresses;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Add IPv6 address
     * 
     * @param string $ipv6_address IPv6 address
     * @return $this
     */
    public function addIpv6Address($ipv6_address)
    {
        if (!in_array($ipv6_address, $this->ipv6_addresses)) {
            $this->ipv6_addresses[] = $ipv6_address;
            $this->updated_at = time();
        }
        return $this;
    }
    
    /**
     * Get subdomains
     * 
     * @return array Subdomains
     */
    public function getSubdomains()
    {
        return $this->subdomains;
    }
    
    /**
     * Set subdomains
     * 
     * @param array $subdomains Subdomains
     * @return $this
     */
    public function setSubdomains(array $subdomains)
    {
        $this->subdomains = $subdomains;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Add subdomain
     * 
     * @param string $subdomain Subdomain
     * @return $this
     */
    public function addSubdomain($subdomain)
    {
        if (!in_array($subdomain, $this->subdomains)) {
            $this->subdomains[] = $subdomain;
            $this->updated_at = time();
        }
        return $this;
    }
    
    /**
     * Get settings
     * 
     * @return array Settings
     */
    public function getSettings()
    {
        return $this->settings;
    }
    
    /**
     * Set settings
     * 
     * @param array $settings Settings
     * @return $this
     */
    public function setSettings(array $settings)
    {
        $this->settings = $settings;
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get setting
     * 
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function getSetting($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->settings;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set setting
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return $this
     */
    public function setSetting($key, $value)
    {
        $keys = explode('.', $key);
        $ref = &$this->settings;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $ref[$k] = $value;
                break;
            }
            
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            
            $ref = &$ref[$k];
        }
        
        $this->updated_at = time();
        return $this;
    }
    
    /**
     * Get created at timestamp
     * 
     * @return int Created at timestamp
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }
    
    /**
     * Get updated at timestamp
     * 
     * @return int Updated at timestamp
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }
    
    /**
     * Convert to array
     * 
     * @return array Model data as array
     */
    public function toArray()
    {
        return [
            'domain' => $this->domain,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'ip' => $this->ip_addresses,
            'ipv6' => $this->ipv6_addresses,
            'subdomain' => $this->subdomains,
            'settings' => $this->settings,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
    
    /**
     * Create from array
     * 
     * @param array $data Model data
     * @return static New instance
     */
    public static function fromArray(array $data)
    {
        $model = new static($data['domain'], $data['user_id'], $data['status'] ?? null);
        
        if (isset($data['ip']) && is_array($data['ip'])) {
            $model->setIpAddresses($data['ip']);
        }
        
        if (isset($data['ipv6']) && is_array($data['ipv6'])) {
            $model->setIpv6Addresses($data['ipv6']);
        }
        
        if (isset($data['subdomain']) && is_array($data['subdomain'])) {
            $model->setSubdomains($data['subdomain']);
        }
        
        if (isset($data['settings']) && is_array($data['settings'])) {
            $model->setSettings($data['settings']);
        }
        
        if (isset($data['created_at'])) {
            $model->created_at = $data['created_at'];
        }
        
        if (isset($data['updated_at'])) {
            $model->updated_at = $data['updated_at'];
        }
        
        return $model;
    }
}
