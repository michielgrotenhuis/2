<?php
/**
 * ApiHelper - Manages all API communication for the Blackwall module
 */
class ApiHelper
{
    private $api_key;
    private $module_name;
    
    /**
     * Constructor
     * 
     * @param string $api_key API key to use for requests
     * @param string $module_name Module name for logging
     */
    public function __construct($api_key, $module_name)
    {
        $this->api_key = $api_key;
        $this->module_name = $module_name;
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
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            throw new Exception("API key is required for Botguard API requests.");
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
            throw new Exception('cURL Error: ' . $err);
        }
        
        // Parse response
        $response_data = json_decode($response, true);
        
        // Handle error responses
        if (isset($response_data['status']) && $response_data['status'] === 'error') {
            $this->log(
                'error', 
                'API Error', 
                ['message' => $response_data['message']]
            );
            throw new Exception('API Error: ' . $response_data['message']);
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
     * @param string $override_api_key Optional API key to use instead of the module config
     * @return array Response data
     */
    public function gatekeeperRequest($endpoint, $method = 'GET', $data = [], $override_api_key = null)
    {
        // Get API key from module config or use override if provided
        $api_key = $override_api_key ?: $this->api_key;
        
        if (empty($api_key)) {
            throw new Exception("API key is required for GateKeeper API requests.");
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
            throw new Exception('cURL Error: ' . $err);
        }
        
        // Parse response if it's JSON
        if (!empty($response)) {
            $response_data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // If it's a valid JSON response
                if (isset($response_data['status']) && $response_data['status'] === 'error') {
                    $this->log(
                        'error', 
                        'GateKeeper API Error', 
                        ['message' => $response_data['message']]
                    );
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
