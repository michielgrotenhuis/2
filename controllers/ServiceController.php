<?php
/**
 * ServiceController - Handles service management for the Blackwall module
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class ServiceController
{
    private $module_name;
    private $logger;
    private $api_controller;
    private $dns_controller;
    
    /**
     * Constructor
     * 
     * @param string $module_name Module name for logging
     * @param ApiController $api_controller API controller instance
     * @param DnsController $dns_controller DNS controller instance
     * @param LogHelper $logger Logger instance (optional)
     */
    public function __construct($module_name, $api_controller, $dns_controller, $logger = null)
    {
        $this->module_name = $module_name;
        $this->api_controller = $api_controller;
        $this->dns_controller = $dns_controller;
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
     * Create a new service
     * 
     * @param string $domain Domain name
     * @param string $email User email
     * @param string $first_name User first name
     * @param string $last_name User last name
     * @param int $order_id Order ID
     * @param int $client_id Client ID
     * @return array Service data or false on failure
     */
    public function createService($domain, $email, $first_name, $last_name, $order_id, $client_id = 0)
    {
        try {
            // Step 1: Create a subaccount in Botguard
            $this->log('info', 'Creating service', [
                'domain' => $domain,
                'email' => $email,
                'name' => $first_name,
                'surname' => $last_name
            ]);
            
            $subaccount_result = $this->api_controller->createUser($email, $first_name, $last_name);
            
            // Extract the user ID and API key from the response
            $user_id = isset($subaccount_result['id']) ? $subaccount_result['id'] : null;
            $user_api_key = isset($subaccount_result['api_key']) ? $subaccount_result['api_key'] : null;
            
            if (!$user_id) {
                throw new Exception("Failed to get user ID from Botguard API response");
            }
            
            // Step 2: Also create user in GateKeeper
            try {
                $this->api_controller->createGatekeeperUser($user_id);
            } catch (Exception $gk_user_e) {
                // Log error but continue - the user might already exist in GateKeeper
                $this->log('warning', 'Error creating user in GateKeeper', 
                    ['error' => $gk_user_e->getMessage()], 
                    $gk_user_e->getMessage(), 
                    $gk_user_e->getTraceAsString());
            }
            
            // Step 3: Add the domain to the subaccount in Botguard
            $website_result = $this->api_controller->createWebsite($domain, $user_id);
            
            // Step 4: Also add the domain in GateKeeper
            try {
                // Get the A records for the domain
                $domain_ips = $this->dns_controller->getDomainARecords($domain);
                // Get AAAA records if available
                $domain_ipv6s = $this->dns_controller->getDomainAAAARecords($domain);
                
                $this->api_controller->createGatekeeperWebsite($domain, $user_id, $domain_ips, $domain_ipv6s);
            } catch (Exception $gk_website_e) {
                // Log error but continue - the domain might already exist in GateKeeper
                $this->log('warning', 'Error creating domain in GateKeeper', 
                    ['domain' => $domain, 'error' => $gk_website_e->getMessage()], 
                    $gk_website_e->getMessage(), 
                    $gk_website_e->getTraceAsString());
            }
            
            // Step 5: Add a delay before updating the domain status
            sleep(2);
            
            // Step 6: Activate the domain by setting status to online in Botguard
            try {
                $this->api_controller->updateWebsiteStatus($domain, BlackwallConstants::STATUS_ONLINE);
            } catch (Exception $update_e) {
                // Log the error but continue
                $this->log('warning', 'Error updating domain status in Botguard', 
                    ['domain' => $domain, 'error' => $update_e->getMessage()], 
                    $update_e->getMessage(), 
                    $update_e->getTraceAsString());
            }
            
            // Step 7: Also update the domain status in GateKeeper
            try {
                // Get the A records for the domain (refreshed)
                $domain_ips = $this->dns_controller->getDomainARecords($domain);
                // Get AAAA records if available (refreshed)
                $domain_ipv6s = $this->dns_controller->getDomainAAAARecords($domain);
                
                $this->api_controller->updateGatekeeperWebsite(
                    $domain, 
                    $user_id, 
                    $domain_ips, 
                    $domain_ipv6s, 
                    BlackwallConstants::STATUS_ONLINE
                );
                
                // Step 8: Register hook for DNS verification after creation
                $this->dns_controller->registerDnsCheckHook($domain, $order_id, $client_id);
            } catch (Exception $gk_update_e) {
                // Log the error but continue
                $this->log('warning', 'Error updating domain status in GateKeeper', 
                    ['domain' => $domain, 'error' => $gk_update_e->getMessage()], 
                    $gk_update_e->getMessage(), 
                    $gk_update_e->getTraceAsString());
            }
            
            // Return the successful data to store in the service
            return [
                'blackwall_domain' => $domain,
                'blackwall_user_id' => $user_id,
                'blackwall_api_key' => $user_api_key,
            ];
        } catch (Exception $e) {
            $this->log('error', 'Error creating service', 
                ['domain' => $domain, 'email' => $email], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            throw $e;
        }
    }
    
    /**
     * Renew a service
     * 
     * @param string $domain Domain name
     * @param int $user_id User ID
     * @param int $order_id Order ID
     * @param int $client_id Client ID
     * @return bool Success status
     */
    public function renewService($domain, $user_id, $order_id, $client_id = 0)
    {
        try {
            // Call the Botguard API to verify the domain exists
            $result = $this->api_controller->getWebsite($domain);
            
            // Check if domain is paused and reactivate if needed
            if(isset($result['status']) && $result['status'] === BlackwallConstants::STATUS_PAUSED) {
                // Update in Botguard
                $this->api_controller->updateWebsiteStatus($domain, BlackwallConstants::STATUS_ONLINE);
                
                // Also update in GateKeeper
                try {
                    // Get the A records for the domain
                    $domain_ips = $this->dns_controller->getDomainARecords($domain);
                    // Get AAAA records if available
                    $domain_ipv6s = $this->dns_controller->getDomainAAAARecords($domain);
                    
                    $this->api_controller->updateGatekeeperWebsite(
                        $domain,
                        $user_id,
                        $domain_ips,
                        $domain_ipv6s,
                        BlackwallConstants::STATUS_ONLINE
                    );
                    
                    // Register hook for DNS verification after renewal
                    $this->dns_controller->registerDnsCheckHook($domain, $order_id, $client_id);
                } catch (Exception $gk_e) {
                    // Log but continue
                    $this->log('warning', 'GateKeeper update error during renewal', 
                        ['domain' => $domain, 'error' => $gk_e->getMessage()], 
                        $gk_e->getMessage(), 
                        $gk_e->getTraceAsString());
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error renewing service', 
                ['domain' => $domain, 'user_id' => $user_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            throw $e;
        }
    }
    
    /**
     * Suspend a service
     * 
     * @param string $domain Domain name
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function suspendService($domain, $user_id)
    {
        try {
            // Step 1: Call the Botguard API to set domain status to 'paused'
            $this->api_controller->updateWebsiteStatus($domain, BlackwallConstants::STATUS_PAUSED);
            
            // Step 2: Also update the domain status in GateKeeper
            try {
                // Get the A records for the domain
                $domain_ips = $this->dns_controller->getDomainARecords($domain);
                // Get AAAA records if available
                $domain_ipv6s = $this->dns_controller->getDomainAAAARecords($domain);
                
                $this->api_controller->updateGatekeeperWebsite(
                    $domain,
                    $user_id,
                    $domain_ips,
                    $domain_ipv6s,
                    BlackwallConstants::STATUS_PAUSED
                );
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                $this->log('warning', 'Error setting domain status in GateKeeper', 
                    ['domain' => $domain, 'error' => $gk_e->getMessage()], 
                    $gk_e->getMessage(), 
                    $gk_e->getTraceAsString());
            }
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error suspending service', 
                ['domain' => $domain, 'user_id' => $user_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            throw $e;
        }
    }
    
    /**
     * Unsuspend a service
     * 
     * @param string $domain Domain name
     * @param int $user_id User ID
     * @param int $order_id Order ID
     * @param int $client_id Client ID
     * @return bool Success status
     */
    public function unsuspendService($domain, $user_id, $order_id, $client_id = 0)
    {
        try {
            // Step 1: Call the Botguard API to set domain status to 'online'
            $this->api_controller->updateWebsiteStatus($domain, BlackwallConstants::STATUS_ONLINE);
            
            // Step 2: Also update the domain status in GateKeeper
            try {
                // Get the A records for the domain
                $domain_ips = $this->dns_controller->getDomainARecords($domain);
                // Get AAAA records if available
                $domain_ipv6s = $this->dns_controller->getDomainAAAARecords($domain);
                
                $this->api_controller->updateGatekeeperWebsite(
                    $domain,
                    $user_id,
                    $domain_ips,
                    $domain_ipv6s,
                    BlackwallConstants::STATUS_ONLINE
                );
                
                // Register hook for DNS verification after unsuspension
                $this->dns_controller->registerDnsCheckHook($domain, $order_id, $client_id);
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                $this->log('warning', 'Error setting domain status in GateKeeper', 
                    ['domain' => $domain, 'error' => $gk_e->getMessage()], 
                    $gk_e->getMessage(), 
                    $gk_e->getTraceAsString());
            }
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error unsuspending service', 
                ['domain' => $domain, 'user_id' => $user_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            throw $e;
        }
    }
    
    /**
     * Delete a service
     * 
     * @param string $domain Domain name
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function deleteService($domain, $user_id)
    {
        try {
            // Step 1: Delete the domain from Botguard
            $this->api_controller->deleteWebsite($domain);
            
            // Step 2: Also delete the domain from GateKeeper
            try {
                $this->api_controller->deleteGatekeeperWebsite($domain);
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper deletion fails
                $this->log('warning', 'Error deleting domain from GateKeeper', 
                    ['domain' => $domain, 'error' => $gk_e->getMessage()], 
                    $gk_e->getMessage(), 
                    $gk_e->getTraceAsString());
            }
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error deleting service', 
                ['domain' => $domain, 'user_id' => $user_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            throw $e;
        }
    }
    
    /**
     * Create a support ticket for DNS configuration
     * 
     * @param string $domain Domain name
     * @param int $client_id Client ID
     * @param int $order_id Order ID
     * @return bool Success status
     */
    public function createDnsConfigurationTicket($domain, $client_id, $order_id)
    {
        try {
            $this->log('info', 'Creating DNS Configuration Ticket', 
                ['domain' => $domain, 'client_id' => $client_id, 'order_id' => $order_id]);
            
            // Define the required DNS records for Blackwall protection
            $required_records = BlackwallConstants::getDnsRecords();
            
            // Get client language preference
            $client = [];
            if (class_exists('User')) {
                $client = User::getData($client_id);
            }
            $client_lang = isset($client['lang']) ? $client['lang'] : 'en';
            
            // Get localized title
            $title_locale = [
                'en' => "DNS Configuration Required for {$domain}",
                'de' => "DNS-Konfiguration erforderlich für {$domain}",
                'fr' => "Configuration DNS requise pour {$domain}",
                'es' => "Configuración DNS requerida para {$domain}",
                'nl' => "DNS-configuratie vereist voor {$domain}",
            ];
            
            // Default to English if language not found
            $title = isset($title_locale[$client_lang]) ? $title_locale[$client_lang] : $title_locale['en'];
            
            // Create the ticket message with Markdown formatting
            $message = $this->getDnsConfigurationMessage($client_lang, $domain, $required_records);
            
            // Prepare ticket data
            $ticket_data = [
                'user_id' => $client_id,
                'did' => 1, // Department ID - adjust as needed
                'priority' => 2, // Medium priority
                'status' => 'process', // In progress
                'title' => $title,
                'message' => $message,
                'service' => $order_id // Order ID
            ];
            
            // Create the ticket
            $ticket_id = 0;
            if (class_exists('Models\\Tickets\\Tickets')) {
                $ticket_id = \Models\Tickets\Tickets::insert($ticket_data);
            } elseif (class_exists('Tickets')) {
                $ticket_id = Tickets::insert($ticket_data);
            } else {
                throw new Exception("Ticket system not found");
            }
            
            $this->log('info', 'DNS Configuration Ticket Created', ['ticket_id' => $ticket_id]);
            
            return true;
        } catch (Exception $e) {
            $this->log('error', 'Error Creating DNS Configuration Ticket', 
                ['domain' => $domain, 'client_id' => $client_id], 
                $e->getMessage(), 
                $e->getTraceAsString());
                
            return false;
        }
    }
    
    /**
     * Get localized DNS configuration message
     * 
     * @param string $lang Language code
     * @param string $domain Domain name
     * @param array $required_records Required DNS records
     * @return string Localized message content
     */
    private function getDnsConfigurationMessage($lang, $domain, $required_records)
    {
        // Basic English template for all messages
        $message = "# DNS Configuration Instructions for {$domain}\n\n";
        $message .= "⚠️ **Important Notice:** Your domain **{$domain}** is not correctly configured for Blackwall protection.\n\n";
        $message .= "For Blackwall to protect your website, you need to point your domain to our protection servers using the DNS settings below:\n\n";
        
        // A Records section
        $message .= "## A Records\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        foreach ($required_records['A'] as $ip) {
            $message .= "| A | @ | {$ip} |\n";
        }
        
        // AAAA Records section
        $message .= "\n## AAAA Records (IPv6)\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        foreach ($required_records['AAAA'] as $ipv6) {
            $message .= "| AAAA | @ | {$ipv6} |\n";
        }
        
        // Instructions for www subdomain
        $message .= "\n## www Subdomain\n\n";
        $message .= "If you want to use www.{$domain}, you should also add the same records for the www subdomain or create a CNAME record:\n\n";
        $message .= "| Record Type | Name | Value |\n";
        $message .= "|------------|------|-------|\n";
        $message .= "| CNAME | www | {$domain} |\n";
        
        // DNS propagation note
        $message .= "\n## DNS Propagation\n\n";
        $message .= "After updating your DNS settings, it may take up to 24-48 hours for the changes to propagate globally. During this time, you may experience intermittent connectivity to your website.\n\n";
        
        // Support note
        $message .= "## Need Help?\n\n";
        $message .= "If you need assistance with these settings, please reply to this ticket. Our team will be happy to guide you through the process.\n\n";
        $message .= "You can also check your current DNS configuration using online tools like [MXToolbox](https://mxtoolbox.com/DNSLookup.aspx) or [DNSChecker](https://dnschecker.org/).\n\n";
        
        // Localize the message based on language if needed
        switch ($lang) {
            case 'de':
                // German translation would go here
                break;
            case 'fr':
                // French translation would go here
                break;
            case 'es':
                // Spanish translation would go here
                break;
            case 'nl':
                // Dutch translation would go here
                break;
        }
        
        return $message;
    }
}
