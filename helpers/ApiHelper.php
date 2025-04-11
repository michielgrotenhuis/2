<?php
/**
 * ApiHelper - Manages all API communication for the Blackwall module
 */
class ApiHelper
{
    private $api_key;
    private $module_name;
    private $timeout = 8; // Reduced default timeout in seconds
    private $connectTimeout = 5; // Connection timeout
    private $retries = 0; // Default to no retries
    private $lastError = null;
    private $apiFailure = false; // Track if any API call has failed
    
    /**
     * Constructor
     * 
     * @param string $api_key API key to use for requests
     * @param string $module_name Module name for logging
     * @param int $timeout Timeout in seconds
     * @param int $retries Number of retries
     */
    public function __construct($api_key, $module_name, $timeout = 8, $retries = 0)
    {
        $this->api_key = $api_key;
        $this->module_name = $module_name;
        $this->timeout = max(3, intval($timeout)); // Minimum 3 seconds
        $this->connectTimeout = min(5, intval($timeout)/2); // Connect timeout is half of total timeout
        $this->retries = 0; // No retries to prevent hanging
    }
    
    /**
     * Logging method
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log($level, $message, $context = [])
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
                    LogHelper::error($message, $context);
                    break;
                default:
                    // Fallback to general logging
                    LogHelper::info($message, $context);
            }
        } else {
            // Fallback logging if LogHelper is not available
            error_log(sprintf(
                "[%s] %s - %s",
                strtoupper($level),
                $message,
                json_encode($context)
            ));
        }
    }
    
    /**
     * Get the last error
     * 
     * @return string|null Last error message
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Check if there has been any API failure
     * 
     * @return bool True if any API call has failed
     */
    public function hasApiFailure()
    {
        return $this->apiFailure;
    }
    
    /**
     * Make a request to the Botguard API with timeout protection
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the module config
     * @return array Response data
     */
    public function request($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // If API already failed in this session, don't try again
        if ($this->apiFailure) {
            $this->lastError = "Skipping API call due to previous failure";
            $this->log('warning', 'Skipping API call due to previous failure', [
                'endpoint' => $endpoint,
                'method' => $method
            ]);
            
            // Return a basic response to allow the process to continue
            return ['status' => 'skip', 'message' => 'Operation skipped due to previous API failure'];
        }
        
        // Reset last error
        $this->lastError = null;
        
        // Start timing
        $startTime = microtime(true);
        
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            $this->lastError = "API key is required for Botguard API requests.";
            $this->apiFailure = true;
            throw new Exception($this->lastError);
        }
        
        // Build full API URL
        $url = 'https://apiv2.botguard.net' . $endpoint;
        
        // Log the API request
        $this->log('info', 'API Request: ' . $method . ' ' . $url, [
            'data' => $data,
            'api_key_first_chars' => substr($api_key, 0, 5) . '...'
        ]);
        
        try {
            // Initialize cURL
            $ch = curl_init();
            
            // Setup common cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
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
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            // Log the response
            $this->log('info', 'API Response: ' . $method . ' ' . $url, [
                'status_code' => $info['http_code'],
                'execution_time' => round($executionTime, 4) . 's',
                'error' => $err
            ]);
            
            // Handle errors
            if ($err) {
                $this->log('error', 'cURL Error for ' . $url, ['error' => $err]);
                $this->lastError = 'cURL Error: ' . $err;
                $this->apiFailure = true;
                
                // Return a basic success response to prevent hanging
                return ['status' => 'error', 'message' => 'API request failed, continuing with default values'];
            }
            
            // Parse response
            $response_data = json_decode($response, true);
            
            // Handle JSON parse errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('error', 'JSON Parse Error', [
                    'error' => json_last_error_msg(),
                    'response' => $response
                ]);
                
                $this->lastError = 'JSON Parse Error: ' . json_last_error_msg();
                
                // Return a basic success response
                return ['status' => 'error', 'message' => 'Response parsing failed, continuing with defaults'];
            }
            
            // Handle error responses
            if (isset($response_data['status']) && $response_data['status'] === 'error') {
                $this->log('error', 'API Error', ['message' => $response_data['message']]);
                $this->lastError = 'API Error: ' . $response_data['message'];
                
                // Don't set api failure flag for application-level errors
                
                // Return the error response as-is
                return $response_data;
            }
            
            // Handle specific HTTP status codes
            if ($info['http_code'] >= 400) {
                $this->log('error', 'HTTP Error', [
                    'status_code' => $info['http_code'], 
                    'response' => $response
                ]);
                
                $this->lastError = 'HTTP Error: ' . $info['http_code'];
                $this->apiFailure = true;
                
                // Return a basic success response
                return ['status' => 'error', 'message' => 'API request failed with HTTP ' . $info['http_code']];
            }
            
            // Success - return the data
            return $response_data;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->apiFailure = true;
            
            $this->log('error', 'Exception in API request', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            // Return a basic success response
            return ['status' => 'error', 'message' => 'API request failed with exception: ' . $e->getMessage()];
        }
    }

    /**
     * Make a request to the GateKeeper API with timeout protection
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the module config
     * @return array Response data
     */
    public function gatekeeperRequest($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // If API already failed in this session, don't try again
        if ($this->apiFailure) {
            $this->lastError = "Skipping GateKeeper API call due to previous failure";
            $this->log('warning', 'Skipping GateKeeper API call due to previous failure', [
                'endpoint' => $endpoint,
                'method' => $method
            ]);
            
            // Return a basic response to allow the process to continue
            return ['status' => 'skip', 'message' => 'Operation skipped due to previous API failure'];
        }
        
        // Reset last error
        $this->lastError = null;
        
        // Start timing
        $startTime = microtime(true);
        
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            $this->lastError = "API key is required for GateKeeper API requests.";
            $this->apiFailure = true;
            throw new Exception($this->lastError);
        }

        // Build full API URL
        $url = 'https://api.blackwall.klikonline.nl:8443/v1.0' . $endpoint;
        
        // Log the API request
        $this->log('info', 'GateKeeper API Request: ' . $method . ' ' . $url, [
            'data' => $data,
            'api_key_first_chars' => substr($api_key, 0, 5) . '...'
        ]);
        
        try {
            // Initialize cURL
            $ch = curl_init();
            
            // Setup common cURL options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
            
            // SSL verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            // Set headers including Authorization but with the correct Content-Type
            $headers = [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/x-www-form-urlencoded', // Changed from application/json
                'Accept: application/json'
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // Set up the request based on HTTP method
            switch ($method) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    if (!empty($data)) {
                        // Convert JSON data to form-encoded data
                        $form_data = http_build_query($data);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
                    }
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    if (!empty($data)) {
                        // Convert JSON data to form-encoded data
                        $form_data = http_build_query($data);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $form_data);
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
            
            // Calculate execution time
            $executionTime = microtime(true) - $startTime;
            
            // Log the response
            $this->log('info', 'GateKeeper API Response: ' . $method . ' ' . $url, [
                'status_code' => $info['http_code'],
                'execution_time' => round($executionTime, 4) . 's',
                'error' => $err
            ]);
            
            // Handle errors
            if ($err) {
                $this->log('error', 'GateKeeper cURL Error', ['error' => $err]);
                $this->lastError = 'cURL Error: ' . $err;
                $this->apiFailure = true;
                
                // Return a basic success response
                return ['status' => 'error', 'message' => 'GateKeeper API request failed, continuing with defaults'];
            }
            
            // Parse response if it's JSON
            if (!empty($response)) {
                $response_data = json_decode($response, true);
                
                // Handle JSON parse errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // For non-JSON responses, return as-is
                    return ['raw_response' => $response];
                }
                
                // Handle error responses
                if (isset($response_data['status']) && $response_data['status'] === 'error') {
                    $this->log('error', 'GateKeeper API Error', ['message' => $response_data['message']]);
                    $this->lastError = 'GateKeeper API Error: ' . $response_data['message'];
                    
                    // Don't set api failure flag for application-level errors
                    
                    // Return the error response as-is
                    return $response_data;
                }
                
                return $response_data;
            }
            
            // Handle specific HTTP status codes
            if ($info['http_code'] >= 400) {
                $this->log('error', 'GateKeeper HTTP Error', [
                    'status_code' => $info['http_code'], 
                    'response' => $response
                ]);
                
                $this->lastError = 'HTTP Error: ' . $info['http_code'];
                $this->apiFailure = true;
                
                // Return a basic success response
                return ['status' => 'error', 'message' => 'GateKeeper API request failed with HTTP ' . $info['http_code']];
            }
            
            // If we got here, return empty array for empty responses (like 204 No Content)
            return [];
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->apiFailure = true;
            
            $this->log('error', 'Exception in GateKeeper API request', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            // Return a basic success response
            return ['status' => 'error', 'message' => 'GateKeeper API request failed with exception: ' . $e->getMessage()];
        }
    }
}
