<?php
/**
 * ApiHelper - Manages all API communication for the Blackwall module
 */
class ApiHelper
{
    private $api_key;
    private $module_name;
    private $timeout = 20; // Default timeout in seconds
    private $retries = 1; // Default number of retries
    private $lastError = null;
    
    /**
     * Constructor
     * 
     * @param string $api_key API key to use for requests
     * @param string $module_name Module name for logging
     * @param int $timeout Timeout in seconds
     * @param int $retries Number of retries
     */
    public function __construct($api_key, $module_name, $timeout = 20, $retries = 1)
    {
        $this->api_key = $api_key;
        $this->module_name = $module_name;
        $this->timeout = max(5, intval($timeout)); // Minimum 5 seconds
        $this->retries = max(0, intval($retries)); // Minimum 0 retries
    }
    
    /**
     * Logging method (replacing previous static save_log())
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log($level, $message, $context = [])
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
     * Make a request to the Botguard API
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the module config
     * @return array Response data
     */
    public function request($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // Reset last error
        $this->lastError = null;
        
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            $this->lastError = "API key is required for Botguard API requests.";
            throw new Exception($this->lastError);
        }
        
        // Build full API URL
        $url = 'https://apiv2.botguard.net' . $endpoint;
        
        // Log the API request
        $this->log(
            'info', 
            'API Request: ' . $method . ' ' . $url,
            [
                'data' => $data,
                'api_key_first_chars' => substr($api_key, 0, 5) . '...'
            ]
        );
        
        // Implement retry logic
        $attempts = 0;
        $max_attempts = $this->retries + 1;
        $last_error = null;
        
        while ($attempts < $max_attempts) {
            $attempts++;
            
            try {
                // Initialize cURL
                $ch = curl_init();
                
                // Setup common cURL options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout / 2);
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
                $this->log(
                    'info',
                    'API Response: ' . $method . ' ' . $url,
                    [
                        'status_code' => $info['http_code'],
                        'response' => $response,
                        'error' => $err
                    ]
                );
                
                // Handle errors
                if ($err) {
                    $this->log(
                        'error', 
                        'cURL Error for ' . $url, 
                        ['error' => $err]
                    );
                    
                    $last_error = 'cURL Error: ' . $err;
                    
                    // Only retry on connection errors, not on application-level errors
                    if (strpos($err, 'timed out') !== false || 
                        strpos($err, 'connection') !== false || 
                        strpos($err, 'resolve host') !== false) {
                        
                        if ($attempts < $max_attempts) {
                            $this->log('warning', 'Retrying API request after error', [
                                'attempt' => $attempts,
                                'max_attempts' => $max_attempts,
                                'error' => $err
                            ]);
                            
                            // Wait before retry (exponential backoff)
                            usleep(min(1000000, 100000 * pow(2, $attempts)));
                            continue;
                        }
                    }
                    
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
                }
                
                // Parse response
                $response_data = json_decode($response, true);
                
                // Handle JSON parse errors
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->log(
                        'error', 
                        'JSON Parse Error', 
                        [
                            'error' => json_last_error_msg(),
                            'response' => $response
                        ]
                    );
                    
                    $last_error = 'JSON Parse Error: ' . json_last_error_msg();
                    
                    // Don't retry on JSON parse errors
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
                }
                
                // Handle error responses
                if (isset($response_data['status']) && $response_data['status'] === 'error') {
                    $this->log(
                        'error', 
                        'API Error', 
                        ['message' => $response_data['message']]
                    );
                    
                    $last_error = 'API Error: ' . $response_data['message'];
                    
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
                }
                
                // Handle specific HTTP status codes
                if ($info['http_code'] >= 400) {
                    $this->log(
                        'error', 
                        'HTTP Error', 
                        [
                            'status_code' => $info['http_code'], 
                            'response' => $response
                        ]
                    );
                    
                    $last_error = 'HTTP Error: ' . $info['http_code'] . ' - ' . $response;
                    
                    // Retry on certain HTTP errors
                    if (in_array($info['http_code'], [429, 500, 502, 503, 504]) && $attempts < $max_attempts) {
                        $this->log('warning', 'Retrying API request after HTTP error', [
                            'attempt' => $attempts,
                            'max_attempts' => $max_attempts,
                            'status_code' => $info['http_code']
                        ]);
                        
                        // Wait before retry (exponential backoff)
                        usleep(min(1000000, 100000 * pow(2, $attempts)));
                        continue;
                    }
                    
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
                }
                
                // Success - return the data
                return $response_data;
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                
                // If this is the last attempt, re-throw the exception
                if ($attempts >= $max_attempts) {
                    $this->lastError = $last_error;
                    throw $e;
                }
                
                // Otherwise, log and continue
                $this->log('warning', 'API request attempt failed, retrying', [
                    'attempt' => $attempts,
                    'max_attempts' => $max_attempts,
                    'error' => $last_error
                ]);
                
                // Wait before retry (exponential backoff)
                usleep(min(1000000, 100000 * pow(2, $attempts)));
            }
        }
        
        // If we get here, all attempts have failed
        $this->lastError = $last_error ?? 'Unknown error';
        throw new Exception($this->lastError);
    }

    /**
     * Make a request to the GateKeeper API
     * 
     * @param string $endpoint API endpoint to call
     * @param string $method HTTP method to use
     * @param array $data Data to send with the request
     * @param string $override_api_key Optional API key to use instead of the module config
     * @return array Response data
     */
    public function gatekeeperRequest($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // Reset last error
        $this->lastError = null;
        
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            $this->lastError = "API key is required for GateKeeper API requests.";
            throw new Exception($this->lastError);
        }

        // Build full API URL
        $url = 'https://api.blackwall.klikonline.nl:8443/v1.0' . $endpoint;
        
        // Log the API request
        $this->log(
            'info', 
            'GateKeeper API Request: ' . $method . ' ' . $url,
            [
                'data' => $data,
                'api_key_first_chars' => substr($api_key, 0, 5) . '...'
            ]
        );
        
        // Implement retry logic
        $attempts = 0;
        $max_attempts = $this->retries + 1;
        $last_error = null;
        
        while ($attempts < $max_attempts) {
            $attempts++;
            
            try {
                // Initialize cURL
                $ch = curl_init();
                
                // Setup common cURL options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout / 2);
                
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
                $this->log(
                    'info',
                    'GateKeeper API Response: ' . $method . ' ' . $url,
                    [
                        'status_code' => $info['http_code'],
                        'response' => $response,
                        'error' => $err
                    ]
                );
                
                // Handle errors
                if ($err) {
                    $this->log(
                        'error', 
                        'GateKeeper cURL Error', 
                        ['error' => $err]
                    );
                    
                    $last_error = 'cURL Error: ' . $err;
                    
                    // Only retry on connection errors, not on application-level errors
                    if (strpos($err, 'timed out') !== false || 
                        strpos($err, 'connection') !== false || 
                        strpos($err, 'resolve host') !== false) {
                        
                        if ($attempts < $max_attempts) {
                            $this->log('warning', 'Retrying GateKeeper API request after error', [
                                'attempt' => $attempts,
                                'max_attempts' => $max_attempts,
                                'error' => $err
                            ]);
                            
                            // Wait before retry (exponential backoff)
                            usleep(min(1000000, 100000 * pow(2, $attempts)));
                            continue;
                        }
                    }
                    
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
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
                        $this->log(
                            'error', 
                            'GateKeeper API Error', 
                            ['message' => $response_data['message']]
                        );
                        
                        $last_error = 'GateKeeper API Error: ' . $response_data['message'];
                        
                        $this->lastError = $last_error;
                        throw new Exception($last_error);
                    }
                    
                    return $response_data;
                }
                
                // Handle specific HTTP status codes
                if ($info['http_code'] >= 400) {
                    $this->log(
                        'error', 
                        'GateKeeper HTTP Error', 
                        [
                            'status_code' => $info['http_code'], 
                            'response' => $response
                        ]
                    );
                    
                    $last_error = 'HTTP Error: ' . $info['http_code'] . ' - ' . $response;
                    
                    // Retry on certain HTTP errors
                    if (in_array($info['http_code'], [429, 500, 502, 503, 504]) && $attempts < $max_attempts) {
                        $this->log('warning', 'Retrying GateKeeper API request after HTTP error', [
                            'attempt' => $attempts,
                            'max_attempts' => $max_attempts,
                            'status_code' => $info['http_code']
                        ]);
                        
                        // Wait before retry (exponential backoff)
                        usleep(min(1000000, 100000 * pow(2, $attempts)));
                        continue;
                    }
                    
                    $this->lastError = $last_error;
                    throw new Exception($last_error);
                }
                
                // If we got here, return empty array for empty responses (like 204 No Content)
                return [];
                
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                
                // If this is the last attempt, re-throw the exception
                if ($attempts >= $max_attempts) {
                    $this->lastError = $last_error;
                    throw $e;
                }
                
                // Otherwise, log and continue
                $this->log('warning', 'GateKeeper API request attempt failed, retrying', [
                    'attempt' => $attempts,
                    'max_attempts' => $max_attempts,
                    'error' => $last_error
                ]);
                
                // Wait before retry (exponential backoff)
                usleep(min(1000000, 100000 * pow(2, $attempts)));
            }
        }
        
        // If we get here, all attempts have failed
        $this->lastError = $last_error ?? 'Unknown error';
        throw new Exception($this->lastError);
    }
}
