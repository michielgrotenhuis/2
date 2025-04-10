<?php
/**
 * Advanced Order Processing System for Blackwall Module
 * Provides robust, resilient order creation mechanisms
 */

namespace Blackwall\OrderProcessing;

use Exception;
use Blackwall\Helpers\LogHelper;
use Blackwall\Helpers\ApiHelper;

/**
 * Custom Exception for Order Creation
 */
class OrderCreationException extends Exception {
    // Error type constants
    const INVALID_INPUT = 1001;
    const INVALID_DOMAIN = 1002;
    const CIRCUIT_BREAKER_OPEN = 2001;
    const USER_CREATION_FAILED = 3001;
    const WEBSITE_CREATION_FAILED = 3002;
    const GATEKEEPER_USER_CREATION_FAILED = 3003;
    const GATEKEEPER_WEBSITE_CREATION_FAILED = 3004;
    const WEBSITE_ACTIVATION_FAILED = 3005;
    const RETRY_LIMIT_EXCEEDED = 4001;
}

/**
 * Circuit Breaker Implementation
 */
class CircuitBreaker {
    private $failureThreshold;
    private $recoveryTime;
    private $failureCount = 0;
    private $lastFailureTime = 0;
    private $state = 'closed';

    /**
     * Constructor
     * 
     * @param int $failureThreshold Number of failures before opening circuit
     * @param int $recoveryTime Time to wait before attempting recovery
     */
    public function __construct($failureThreshold = 5, $recoveryTime = 60) {
        $this->failureThreshold = max(1, (int)$failureThreshold);
        $this->recoveryTime = max(1, (int)$recoveryTime);
    }

    /**
     * Determine if request is allowed
     * 
     * @return bool
     */
    public function allowRequest() {
        // Check if in open state and recovery time has passed
        if ($this->state === 'open') {
            if (time() - $this->lastFailureTime > $this->recoveryTime) {
                $this->state = 'half-open';
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Record a successful request
     */
    public function recordSuccess() {
        $this->failureCount = 0;
        $this->state = 'closed';
    }

    /**
     * Record a failure
     */
    public function recordFailure() {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
        }
    }

    /**
     * Force circuit breaker to open state
     */
    public function forceOpen() {
        $this->state = 'open';
        $this->lastFailureTime = time();
    }

    /**
     * Get current circuit breaker state
     * 
     * @return string Current state
     */
    public function getState() {
        return $this->state;
    }
}

/**
 * Order Queue Management
 */
class OrderQueue {
    private $queue = [];
    private $maxQueueSize;
    private $storagePath;

    /**
     * Constructor
     * 
     * @param int $maxQueueSize Maximum number of orders in queue
     * @param string|null $storagePath Path to store persistent queue
     */
    public function __construct($maxQueueSize = 100, $storagePath = null) {
        $this->maxQueueSize = max(1, (int)$maxQueueSize);
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/blackwall_order_queue.json';
        
        // Load existing queue
        $this->loadQueue();
    }

    /**
     * Load queue from persistent storage
     */
    private function loadQueue() {
        if (file_exists($this->storagePath)) {
            try {
                $queueData = file_get_contents($this->storagePath);
                $decodedData = json_decode($queueData, true);
                $this->queue = is_array($decodedData) ? $decodedData : [];
            } catch (Exception $e) {
                // If file is corrupted, start with empty queue
                $this->queue = [];
            }
        }
    }

    /**
     * Save queue to persistent storage
     * 
     * @return bool Success status
     */
    private function saveQueue() {
        try {
            return (bool)file_put_contents($this->storagePath, json_encode($this->queue), LOCK_EX);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Enqueue an order
     * 
     * @param array $orderData Order details
     * @return array Queued order with additional metadata
     * @throws Exception If queue is full
     */
    public function enqueue($orderData) {
        if (count($this->queue) >= $this->maxQueueSize) {
            throw new Exception("Order queue is full. Please try again later.");
        }

        $queuedOrder = [
            'data' => $orderData,
            'timestamp' => time(),
            'status' => 'pending',
            'attempts' => 0,
            'unique_id' => uniqid('order_', true)
        ];

        $this->queue[] = $queuedOrder;
        $this->saveQueue();
        return $queuedOrder;
    }

    /**
     * Dequeue an order
     * 
     * @return array|null Dequeued order or null if queue is empty
     */
    public function dequeue() {
        $order = array_shift($this->queue);
        $this->saveQueue();
        return $order;
    }

    /**
     * Get queue length
     * 
     * @return int Number of orders in queue
     */
    public function getQueueLength() {
        return count($this->queue);
    }

    /**
     * Clear expired orders from queue
     * 
     * @param int $expirationTime Maximum time an order can stay in queue (seconds)
     * @return int Number of cleared orders
     */
    public function clearExpiredOrders($expirationTime = 3600) {
        $initialCount = count($this->queue);
        $currentTime = time();
        
        $this->queue = array_filter($this->queue, function($order) use ($currentTime, $expirationTime) {
            return ($currentTime - $order['timestamp']) <= $expirationTime;
        });
        
        $this->saveQueue();
        return $initialCount - count($this->queue);
    }

    /**
     * Mark order as processed
     * 
     * @param string $uniqueId Unique order identifier
     * @return bool Success status
     */
    public function markOrderProcessed($uniqueId) {
        foreach ($this->queue as &$order) {
            if ($order['unique_id'] === $uniqueId) {
                $order['status'] = 'processed';
                return $this->saveQueue();
            }
        }
        return false;
    }

    /**
     * Get all queued orders
     * 
     * @return array Current queue
     */
    public function getQueue() {
        return $this->queue;
    }
}

/**
 * Order Manager for Creating Blackwall Services
 */
class BlackwallOrderManager {
    private $logger;
    private $apiClient;
    private $circuitBreaker;

    /**
     * Constructor
     * 
     * @param LogHelper $logger Logging utility
     * @param ApiHelper $apiClient API communication client
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker instance
     */
    public function __construct(LogHelper $logger, ApiHelper $apiClient, CircuitBreaker $circuitBreaker = null) {
        $this->logger = $logger;
        $this->apiClient = $apiClient;
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker();
    }

    /**
     * Advanced Order Creation Method
     * 
     * @param array $orderData Order details
     * @return array Order creation result
     * @throws OrderCreationException If order creation fails
     */
    public function createOrder($orderData) {
        // Unique identifier for this order creation attempt
        $orderCreationId = uniqid('blackwall_order_', true);
        $startTime = microtime(true);

        try {
            // Validate input data
            $this->validateOrderData($orderData);

            // Check circuit breaker status
            if (!$this->circuitBreaker->allowRequest()) {
                throw new OrderCreationException(
                    "Circuit breaker is open. Service temporarily unavailable.", 
                    OrderCreationException::CIRCUIT_BREAKER_OPEN
                );
            }

            // Perform order creation
            $result = $this->performOrderCreation($orderData, $orderCreationId);

            // Log successful order creation
            $this->logger->info('Order Creation Successful', [
                'order_id' => $orderCreationId,
                'domain' => $orderData['domain'],
                'execution_time' => microtime(true) - $startTime
            ]);

            // Mark circuit breaker as successful
            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (OrderCreationException $e) {
            // Handle specific order creation exceptions
            $this->handleOrderCreationException($e, $orderCreationId, $orderData);
            
            // Record circuit breaker failure
            $this->circuitBreaker->recordFailure();
            
            // Re-throw the exception
            throw $e;
        } catch (Exception $e) {
            // Handle unexpected exceptions
            $this->handleUnexpectedException($e, $orderCreationId, $orderData);
            
            // Force circuit breaker open for unexpected errors
            $this->circuitBreaker->forceOpen();
            
            // Convert to OrderCreationException
            throw new OrderCreationException(
                "Unexpected error: " . $e->getMessage(),
                OrderCreationException::RETRY_LIMIT_EXCEEDED,
                $e
            );
        }
    }

    /**
     * Validate Order Data
     * 
     * @param array $orderData Order details to validate
     * @throws OrderCreationException If validation fails
     */
    private function validateOrderData($orderData) {
        $requiredFields = ['domain', 'email', 'first_name', 'last_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($orderData[$field])) {
                throw new OrderCreationException(
                    "Missing required field: {$field}", 
                    OrderCreationException::INVALID_INPUT
                );
            }
        }

        // Additional domain validation
        if (!$this->isValidDomain($orderData['domain'])) {
            throw new OrderCreationException(
                "Invalid domain: {$orderData['domain']}", 
                OrderCreationException::INVALID_DOMAIN
            );
        }
    }

    /**
     * Validate domain
     * 
     * @param string $domain Domain to validate
     * @return bool Validation result
     */
    private function isValidDomain($domain) {
        // More comprehensive domain validation
        return (bool) preg_match('/^(?:[-A-Za-z0-9]+\.)+[A-Za-z]{2,}$/', $domain);
    }

    /**
     * Perform actual order creation process
     * 
     * @param array $orderData Order details
     * @param string $orderCreationId Unique order creation identifier
     * @return array Order creation result
     * @throws OrderCreationException If creation fails
     */
    private function performOrderCreation($orderData, $orderCreationId) {
        // Log start of creation process
        $this->logger->info('Starting order creation process', [
            'order_id' => $orderCreationId,
            'domain' => $orderData['domain']
        ]);

        // Attempt to create Botguard user
        $user = $this->createBotguardUser($orderData);

        // Create website in Botguard
        $website = $this->createBotguardWebsite($user, $orderData);

        // Create user in GateKeeper
        $this->createGatekeeperUser($user);

        // Create website in GateKeeper
        $this->createGatekeeperWebsite($website, $user, $orderData);

        // Return user and website details
        return [
            'user_id' => $user['id'],
            'website_id' => $website['id'],
            'api_key' => $user['api_key'],
            'domain' => $orderData['domain']
        ];
    }

    /**
     * Create Botguard User
     * 
     * @param array $orderData Order details
     * @return array Created user details
     * @throws OrderCreationException If user creation fails
     */
    private function createBotguardUser($orderData) {
        try {
            // Log attempt
            $this->logger->info('Creating Botguard user', [
                'email' => $orderData['email']
            ]);
            
            $result = $this->apiClient->createUser(
                $orderData['email'], 
                $orderData['first_name'], 
                $orderData['last_name']
            );
            
            // Log success
            $this->logger->info('Botguard user created', [
                'user_id' => $result['id'] ?? 'unknown'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Botguard user creation failed', [
                'email' => $orderData['email'],
                'error' => $e->getMessage()
            ]);
            
            throw new OrderCreationException(
                "Botguard user creation failed: " . $e->getMessage(), 
                OrderCreationException::USER_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Create Botguard Website
     * 
     * @param array $user User details
     * @param array $orderData Order details
     * @return array Created website details
     * @throws OrderCreationException If website creation fails
     */
    private function createBotguardWebsite($user, $orderData) {
        try {
            // Log attempt
            $this->logger->info('Creating Botguard website', [
                'domain' => $orderData['domain'],
                'user_id' => $user['id']
            ]);
            
            $result = $this->apiClient->createWebsite(
                $orderData['domain'], 
                $user['id']
            );
            
            // Log success
            $this->logger->info('Botguard website created', [
                'domain' => $orderData['domain'],
                'website_id' => $result['id'] ?? 'unknown'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('Botguard website creation failed', [
                'domain' => $orderData['domain'],
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new OrderCreationException(
                "Botguard website creation failed: " . $e->getMessage(), 
                OrderCreationException::WEBSITE_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Create Gatekeeper User
     * 
     * @param array $user User details
     * @throws OrderCreationException If gatekeeper user creation fails
     */
    private function createGatekeeperUser($user) {
        try {
            // Log attempt
            $this->logger->info('Creating GateKeeper user', [
                'user_id' => $user['id']
            ]);
            
            $result = $this->apiClient->createGatekeeperUser($user['id']);
            
            // Log success
            $this->logger->info('GateKeeper user created', [
                'user_id' => $user['id']
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('GateKeeper user creation failed', [
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new OrderCreationException(
                "Gatekeeper user creation failed: " . $e->getMessage(), 
                OrderCreationException::GATEKEEPER_USER_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Create Gatekeeper Website
     * 
     * @param array $website Website details
     * @param array $user User details
     * @param array $orderData Order details
     * @throws OrderCreationException If gatekeeper website creation fails
     */
    private function createGatekeeperWebsite($website, $user, $orderData) {
        try {
            // Dynamically get domain IPs
            $domainIps = $this->getDomainIps($orderData['domain']);
            
            // Log attempt
            $this->logger->info('Creating GateKeeper website', [
                'domain' => $orderData['domain'],
                'user_id' => $user['id'],
                'ip_count' => [
                    'ipv4' => count($domainIps['ipv4']),
                    'ipv6' => count($domainIps['ipv6'])
                ]
            ]);
            
            $result = $this->apiClient->createGatekeeperWebsite(
                $orderData['domain'], 
                $user['id'], 
                $domainIps['ipv4'], 
                $domainIps['ipv6']
            );
            
            // Log success
            $this->logger->info('GateKeeper website created', [
                'domain' => $orderData['domain'],
                'user_id' => $user['id']
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('GateKeeper website creation failed', [
                'domain' => $orderData['domain'],
                'user_id' => $user['id'],
                'error' => $e->getMessage()
            ]);
            
            throw new OrderCreationException(
                "Gatekeeper website creation failed: " . $e->getMessage(), 
                OrderCreationException::GATEKEEPER_WEBSITE_CREATION_FAILED,
                $e
            );
        }
    }

    /**
     * Get Domain IP Addresses
     * 
     * @param string $domain Domain to lookup
     * @return array Domain IP addresses
     */
    private function getDomainIps($domain) {
        return [
            'ipv4' => $this->getIPv4Records($domain),
            'ipv6' => $this->getIPv6Records($domain)
        ];
    }

    /**
     * Get IPv4 Records
     * 
     * @param string $domain Domain to lookup
     * @return array IPv4 addresses
     */
    private function getIPv4Records($domain) {
        // Fallback IP if lookup fails
        $defaultIp = ['1.2.3.4'];
        
        try {
            $ipv4 = gethostbynamel($domain);
            return is_array($ipv4) && !empty($ipv4) ? $ipv4 : $defaultIp;
        } catch (Exception $e) {
            $this->logger->warning('IPv4 Lookup Failed', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return $defaultIp;
        }
    }

    /**
     * Get IPv6 Records
     * 
     * @param string $domain Domain to lookup
     * @return array IPv6 addresses
     */
    private function getIPv6Records($domain) {
        // Fallback IPv6 if lookup fails
        $defaultIpv6 = ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        
        try {
            $ipv6 = [];
            
            if (function_exists('dns_get_record')) {
                $records = dns_get_record($domain, DNS_AAAA);
                
                if (is_array($records)) {
                    foreach ($records as $record) {
                        if (isset($record['ipv6'])) {
                            $ipv6[] = $record['ipv6'];
                        }
                    }
                }
            }
            
            return !empty($ipv6) ? $ipv6 : $defaultIpv6;
        } catch (Exception $e) {
            $this->logger->warning('IPv6 Lookup Failed', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return $defaultIpv6;
        }
    }

    /**
     * Handle Order Creation Exceptions
     * 
     * @param OrderCreationException $e Exception to handle
     * @param string $orderCreationId Unique order creation identifier
     * @param array $orderData Original order data
     */
    private function handleOrderCreationException($e, $orderCreationId, $orderData) {
        // Log the specific exception
        $this->logger->error('Order Creation Failed', [
            'order_id' => $orderCreationId,
            'domain' => $orderData['domain'] ?? 'N/A',
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage()
        ]);

        // Send an alert for certain error types
        if (in_array($e->getCode(), [
            OrderCreationException::CIRCUIT_BREAKER_OPEN,
            OrderCreationException::RETRY_LIMIT_EXCEEDED
        ])) {
            $this->sendAdminAlert($e, $orderCreationId, $orderData);
        }
    }

    /**
     * Handle Unexpected Exceptions
     * 
     * @param Exception $e Unexpected exception
     * @param string $orderCreationId Unique order creation identifier
     * @param array $orderData Original order data
     */
    private function handleUnexpectedException($e, $orderCreationId, $orderData) {
        // Log unexpected exception
        $this->logger->critical('Unexpected Order Creation Error', [
            'order_id' => $orderCreationId,
            'domain' => $orderData['domain'] ?? 'N/A',
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Send critical alert for all unexpected errors
        $this->sendCriticalAdminAlert($e, $orderCreationId, $orderData);
    }

    /**
     * Send Admin Alert
     * 
     * @param Exception $e Exception details
     * @param string $orderCreationId Unique order creation identifier
     * @param array $orderData Original order data
     */
    private function sendAdminAlert($e, $orderCreationId, $orderData) {
        // Implement admin notification logic
        // Could send an email, create a support ticket, etc.
        $this->logger->warning('Admin Alert Triggered', [
            'order_id' => $orderCreationId,
            'domain' => $orderData['domain'] ?? 'N/A',
            'error_message' => $e->getMessage()
        ]);
        
        // In a real implementation, add code to send email or other notifications here
    }

    /**
     * Send Critical Admin Alert
     * 
     * @param Exception $e Unexpected exception
     * @param string $orderCreationId Unique order creation identifier
     * @param array $orderData Original order data
     */
    private function sendCriticalAdminAlert($e, $orderCreationId, $orderData) {
        // Implement critical alert notification logic
        $this->logger->critical('Critical Admin Alert', [
            'order_id' => $orderCreationId,
            'domain' => $orderData['domain'] ?? 'N/A',
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // In a real implementation, add code to send urgent notifications here
    }
}
