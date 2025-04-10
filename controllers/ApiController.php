<?php
/**
 * ApiController - Handles API communication for the Blackwall module
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class ApiController
{
    private $api_key;
    private $module_name;
    private $logger;
    
    /**
     * Constructor
     * 
     * @param string $api_key API key to use for requests
     * @param string $module_name Module name for logging
     * @param LogHelper $logger Logger instance (optional)
     */
    public function __construct($api_key, $module_name, $logger = null)
    {
        $this->api_key = $api_key;
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
     * Create a user in Botguard
     * 
     * @param string $email User email
     * @param string $first_name User first name
     * @param string $last_name User last name
     * @return array User data
     */
    public function createUser($email, $first_name, $last_name)
    {
        $data = [
            'email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name
        ];
        
        $this->log('info', 'Creating user in Botguard', $data);
        
        $result = $this->request('/user', 'POST', $data);
        
        $this->log('info', 'User created in Botguard', [
            'user_id' => isset($result['id']) ? $result['id'] : null,
            'api_key_first_chars' => isset($result['api_key']) ? substr($result['api_key'], 0, 5) . '...' : 'null'
        ]);
        
        return $result;
    }
    
    /**
     * Create a user in GateKeeper
     * 
     * @param string $user_id User ID in Botguard
     * @return array Result data
     */
    public function createGatekeeperUser($user_id)
    {
        $data = [
            'id' => $user_id,
            'tag' => 'wisecp'
        ];
        
        $this->log('info', 'Creating user in GateKeeper', $data);
        
        $result = $this->gatekeeperRequest('/user', 'POST', $data);
        
        $this->log('info', 'User created in GateKeeper', $result);
        
        return $result;
    }
    
    /**
     * Create a website in Botguard
     * 
     * @param string $domain Domain name
     * @param string $user_id User ID
     * @return array Website data
     */
    public function createWebsite($domain, $user_id)
    {
        $data = [
            'domain' => $domain,
            'user' => $user_id
        ];
        
        $this->log('info', 'Creating domain in Botguard', $data);
        
        $result = $this->request('/website', 'POST', $data);
        
        $this->log('info', 'Domain created in Botguard', $result);
        
        return $result;
    }
    
    /**
     * Create a website in GateKeeper
     * 
     * @param string $domain Domain name
     * @param string $user_id User ID
     * @param array $domain_ips Domain IPv4 addresses
     * @param array $domain_ipv6s Domain IPv6 addresses
     * @return array Result data
     */
    public function createGatekeeperWebsite($domain, $user_id, $domain_ips, $domain_ipv6s)
    {
        $data = [
            'domain' => $domain,
            'subdomain' => ['www'],
            'ip' => $domain_ips,
            'ipv6' => $domain_ipv6s,
            'user_id' => $user_id,
            'tag' => ['wisecp'],
            'status' => BlackwallConstants::STATUS_SETUP,
            'settings' => BlackwallConstants::getDefaultWebsiteSettings()
        ];
        
        $this->log('info', 'Creating domain in GateKeeper', $data);
        
        $result = $this->gatekeeperRequest('/website', 'POST', $data);
        
        $this->log('info', 'Domain created in GateKeeper', $result);
        
        return $result;
    }
    
    /**
     * Update website status in Botguard
     * 
     * @param string $domain Domain name
     * @param string $status New status
     * @return array Result data
     */
    public function updateWebsiteStatus($domain, $status)
    {
        $data = [
            'status' => $status
        ];
        
        $this->log('info', "Setting domain status to {$status} in Botguard", ['domain' => $domain, 'data' => $data]);
        
        $result = $this->request('/website/' . $domain, 'PUT', $data);
        
        $this->log('info', "Domain status set to {$status} in Botguard", $result);
        
        return $result;
    }
    
    /**
     * Update website in GateKeeper
     * 
     * @param string $domain Domain name
     * @param string $user_id User ID
     * @param array $domain_ips Domain IPv4 addresses
     * @param array $domain_ipv6s Domain IPv6 addresses
     * @param string $status Website status
     * @return array Result data
     */
    public function updateGatekeeperWebsite($domain, $user_id, $domain_ips, $domain_ipv6s, $status)
    {
        $data = [
            'domain' => $domain,
            'user_id' => $user_id,
            'subdomain' => ['www'],
            'ip' => $domain_ips,
            'ipv6' => $domain_ipv6s,
            'status' => $status,
            'settings' => BlackwallConstants::getDefaultWebsiteSettings()
        ];
        
        $this->log('info', "Setting domain status to {$status} in GateKeeper", ['domain' => $domain, 'data' => $data]);
        
        $result = $this->gatekeeperRequest('/website/' . $domain, 'PUT', $data);
        
        $this->log('info', "Domain status set to {$status} in GateKeeper", $result);
        
        return $result;
    }
    
    /**
     * Get website details from Botguard
     * 
     * @param string $domain Domain name
     * @return array Website data
     */
    public function getWebsite($domain)
    {
        $result = $this->request('/website/' . $domain, 'GET');
        return $result;
    }
    
    /**
     * Delete website from Botguard
     * 
     * @param string $domain Domain name
     * @return array Result data
     */
    public function deleteWebsite($domain)
    {
        $this->log('info', 'Deleting domain from Botguard', ['domain' => $domain]);
        
        $result = $this->request('/website/' . $domain, 'DELETE');
        
        $this->log('info', 'Domain deleted from Botguard', $result);
        
        return $result;
    }
    
    /**
     * Delete website from GateKeeper
     * 
     * @param string $domain Domain name
     * @return array Result data
     */
    public function deleteGatekeeperWebsite($domain)
    {
        $this->log('info', 'Deleting domain from GateKeeper', ['domain' => $domain]);
        
        $result = $this->gatekeeperRequest('/website/' . $domain, 'DELETE');
        
        $this->log('info', 'Domain deleted from GateKeeper', $result);
        
        return $result;
    }
    
    /**
     * Make a request to the Botguard API
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the instance one
     * @return array Response data
     */
    public function request($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // Get API key from instance or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            throw new Exception("API key is required for Botguard API requests.");
        }
        
        // Build full API URL
        $url = 'https://apiv2.botguard.net' . $endpoint;
        
        // Log the API request
        $this->log('debug', 'API Request: ' . $method . ' ' . $url, [
            'data' => $data,
            'api_key_first_chars' => substr($api_key, 0, 5) . '...'
        ]);
        
        // Initialize cURL
        $ch = curl_init();
        
        // Setup common cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Set headers including Authorization
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set up the request based on HTTP method
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default: // GET
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Log the response
        $this->log('debug', 'API Response: ' . $method . ' ' . $url, [
            'status_code' => $info['http_code'],
            'response' => $response,
            'error' => $err
        ]);
        
        // Handle errors
        if ($err) {
            throw new Exception('cURL Error: ' . $err);
        }
        
        // Parse response
        $response_data = json_decode($response, true);
        
        // Handle error responses
        if (isset($response_data['status']) && $response_data['status'] === 'error') {
            throw new Exception('API Error: ' . $response_data['message']);
        }
        
        // Handle specific HTTP status codes
        if ($info['http_code'] >= 400) {
            throw new Exception('HTTP Error: ' . $info['http_code'] . ' - ' . $response);
        }
        
        return $response_data;
    }

    /**
     * Make a request to the GateKeeper API
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the instance one
     * @return array Response data
     */
    public function gatekeeperRequest($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // Get API key from instance or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            throw new Exception("API key is required for GateKeeper API requests.");
        }

        // Build full API URL
        $url = 'https://api.blackwall.klikonline.nl:8443/v1.0' . $endpoint;
        
        // Log the API request
        $this->log('debug', 'GateKeeper API Request: ' . $method . ' ' . $url, [
            'data' => $data,
            'api_key_first_chars' => substr($api_key, 0, 5) . '...'
        ]);
        
        // Initialize cURL
        $ch = curl_init();
        
        // Setup common cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Set headers including Authorization
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set up the request based on HTTP method
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    $json_data = json_encode($data);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    $json_data = json_encode($data);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default: // GET
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        // Execute the request
        $response = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Log the response
        $this->log('debug', 'GateKeeper API Response: ' . $method . ' ' . $url, [
            'status_code' => $info['http_code'],
            'response' => $response,
            'error' => $err
        ]);
        
        // Handle errors
        if ($err) {
            throw new Exception('cURL Error: ' . $err);
        }
        
        // Parse response if it's JSON
        if (!empty($response)) {
            $response_data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // If it's a valid JSON response
                if (isset($response_data['status']) && $response_data['status'] === 'error') {
                    throw new Exception('GateKeeper API Error: ' . $response_data['message']);
                }
                return $response_data;
            }
        }
        
        // If we got here, return the raw response for non-JSON responses
        // or return an empty array for empty responses (like 204 No Content)
        return !empty($response) ? ['raw_response' => $response] : [];
    }
}
