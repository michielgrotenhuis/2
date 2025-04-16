<?php
/**
 * Blackwall (BotGuard) Product Module for WISECP
 * This module allows WISECP to provision and manage BotGuard website protection services
 * 
 * Optimized version with improved performance, error handling, and correct upstream IP handling
 */

class Blackwall extends ProductModule
{
    private $helpers = [];
    private $startTime; // Track execution time for operations
    private $originIps = []; // Store original website IPs before Blackwall protection
    
    function __construct()
    {
        $this->_name = __CLASS__;
        parent::__construct();
        
        $this->loadHelpers();
        $this->startTime = microtime(true);
    }
    
    /**
     * Log execution time for performance monitoring
     * 
     * @param string $operation Name of the operation being timed
     */
    private function logExecutionTime($operation)
    {
        $endTime = microtime(true);
        $executionTime = $endTime - $this->startTime;
        
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->info(
                'Operation execution time',
                [
                    'operation' => $operation,
                    'execution_time' => round($executionTime, 4) . 's'
                ]
            );
        }
        
        // Reset timer for next operation
        $this->startTime = $endTime;
    }
    /**
     * Admin Area Buttons
     */
    public function adminArea_buttons()
    {
        $buttons = [];
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;
        
        if($domain) {
            $buttons['view_in_blackwall'] = [
                'text'  => $this->lang["view_in_blackwall"],
                'type'  => 'link',
                'url'   => 'https://apiv2.botguard.net/en/website/'.$domain.'/statistics?api-key='.$this->config["settings"]["api_key"],
                'target_blank' => true,
            ];
            
            $buttons['check_status'] = [
                'text'  => $this->lang["check_status"],
                'type'  => 'transaction',
            ];
            
            $buttons['check_dns'] = [
                'text'  => $this->lang["check_dns"],
                'type'  => 'transaction',
            ];
        }

        return $buttons;
    }

    /**
     * Admin Area Check Status
     */
    public function use_adminArea_check_status()
    {
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;

        if(!$domain) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_missing_domain"],
            ]);
            return false;
        }

        try {
            // Call the Botguard API to get the domain status
            $result = $this->helpers['api']->request('/website/' . $domain, 'GET');
            
            $status = isset($result['status']) ? $result['status'] : 'unknown';
            
            echo Utility::jencode([
                'status' => "successful",
                'message' => $this->lang["domain_status"] . ": " . $status,
            ]);
            return true;
        } catch (Exception $e) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
    /**
     * Admin Area Check DNS Configuration
     */
    public function use_adminArea_check_dns()
    {
        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;

        if(!$domain) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_missing_domain"],
            ]);
            return false;
        }

        try {
            // Check if the domain's DNS is properly configured
            $is_configured = $this->helpers['dns']->checkDomainDnsConfiguration($domain);
            
            if ($is_configured) {
                echo Utility::jencode([
                    'status' => "successful",
                    'message' => $this->lang["dns_configured_correctly"],
                ]);
            } else {
                echo Utility::jencode([
                    'status' => "warning",
                    'message' => $this->lang["dns_not_configured_correctly"],
                ]);
            }
            return true;
        } catch (Exception $e) {
            echo Utility::jencode([
                'status' => "error",
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get asset URL
     * 
     * @param string $path Asset path
     * @return string Full asset URL
     */
    public function asset_url($path) {
        return '/modules/Blackwall/assets/' . $path;
    }
    
    /**
     * Load helper classes
     */
    private function loadHelpers() 
    {
        $helper_dir = __DIR__ . '/helpers/';
        
        // Load BlackwallConstants first - ensure it's only loaded once
        if (!class_exists('BlackwallConstants')) {
            require_once(__DIR__ . '/BlackwallConstants.php');
        }
        
        if(file_exists($helper_dir . 'DnsHelper.php')) {
            require_once($helper_dir . 'DnsHelper.php');
            $this->helpers['dns'] = new DnsHelper($this->_name);
        }
        
        if(file_exists($helper_dir . 'ApiHelper.php')) {
            require_once($helper_dir . 'ApiHelper.php');
            $api_key = "";
            if(isset($this->config["settings"]["api_key"])) {
                $api_key = $this->config["settings"]["api_key"];
            }
            $this->helpers['api'] = new ApiHelper($api_key, $this->_name);
        }
        
        if(file_exists($helper_dir . 'LogHelper.php')) {
            require_once($helper_dir . 'LogHelper.php');
            $this->helpers['log'] = new LogHelper($this->_name);
        }
    }
    /**
     * Module Configuration Page
     */
    public function configuration()
    {
        $action = isset($_GET["action"]) ? $_GET["action"] : false;
        $action = Filter::letters_numbers($action);

        $vars = [
            'm_name'    => $this->_name,
            'area_link' => $this->area_link,
            'lang'      => $this->lang,
            'config'    => $this->config,
        ];
        
        return $this->get_page("admin/configuration".($action ? "-".$action : ''),$vars);
    }

    /**
     * Save Module Configuration
     */
    public function controller_save()
    {
        // Use raw POST data to preserve the exact API key format
        $api_key = isset($_POST["api_key"]) ? $_POST["api_key"] : "";
        
        // Use Filter for the other fields
        $primary_server = Filter::init("POST/primary_server", "hclear");
        $secondary_server = Filter::init("POST/secondary_server", "hclear");

        // Log the received API key for debugging
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->debug("Received API Key: " . substr($api_key, 0, 5) . '...');
        }

        $set_config = $this->config;

        if($set_config["settings"]["api_key"] != $api_key) 
            $set_config["settings"]["api_key"] = $api_key;
        
        if($set_config["settings"]["primary_server"] != $primary_server) 
            $set_config["settings"]["primary_server"] = $primary_server;
        
        if($set_config["settings"]["secondary_server"] != $secondary_server) 
            $set_config["settings"]["secondary_server"] = $secondary_server;

        if(Validation::isEmpty($api_key))
        {
            echo Utility::jencode([
                'status' => "error",
                'message' => $this->lang["error_api_key_required"],
            ]);
            return false;
        }

        // Save the configuration
        $this->save_config($set_config);
        
        // Log the saved API key for verification
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->debug("Saved API Key: " . substr($set_config["settings"]["api_key"], 0, 5) . '...');
        }

        echo Utility::jencode([
            'status' => "successful",
            'message' => $this->lang["success_settings_saved"],
        ]);

        return true;
    }
    /**
     * Generate a support ticket for DNS configuration
     * This is called by the hook when DNS is not configured correctly
     * 
     * @param string $domain Domain name
     * @param int $client_id Client ID
     * @param int $order_id Order ID
     * @return bool Success status
     */
    public function create_dns_configuration_ticket($domain, $client_id, $order_id)
    {
        error_log("create_dns_configuration_ticket called for domain: {$domain}, client: {$client_id}, order: {$order_id}");
        
        try {
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Creating DNS Configuration Ticket',
                    ['domain' => $domain, 'client_id' => $client_id, 'order_id' => $order_id]
                );
            }
            
            // Define the required DNS records for Blackwall protection
            $required_records = BlackwallConstants::getDnsRecords();
            
            // Get client language preference
            $client = User::getData($client_id);
            error_log("Client data: " . json_encode($client));
            
            $client_lang = isset($client['lang']) ? $client['lang'] : 'en';
            error_log("Using client language: {$client_lang}");
            
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
            $message = $this->get_dns_configuration_message($client_lang, $domain, $required_records);
            
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
            error_log("About to create ticket with data: " . json_encode($ticket_data));
            
            // Create the ticket - try multiple approaches to accommodate different WISECP versions
            $ticket_id = 0;
            
            try {
                if (class_exists('Models\\Tickets\\Tickets')) {
                    error_log("Using Models\\Tickets\\Tickets class");
                    $ticket_id = \Models\Tickets\Tickets::insert($ticket_data);
                } elseif (class_exists('Tickets')) {
                    error_log("Using Tickets class");
                    $ticket_id = Tickets::insert($ticket_data);
                } else {
                    error_log("ERROR: Could not find ticket class. Attempting fallback method");
                    
                    // Try a more direct approach
                    if (function_exists('Tickets_create')) {
                        error_log("Using Tickets_create function");
                        $ticket_id = Tickets_create($ticket_data);
                    } else {
                        throw new Exception("No ticket creation method available");
                    }
                }
            } catch (Exception $ticket_e) {
                error_log("Error creating ticket: " . $ticket_e->getMessage());
                error_log("Trace: " . $ticket_e->getTraceAsString());
                throw $ticket_e; // Re-throw to be handled by outer try/catch
            }
            
            if ($ticket_id) {
                error_log("Ticket created successfully with ID: {$ticket_id}");
            } else {
                error_log("Ticket creation failed - no ticket ID returned");
            }
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'DNS Configuration Ticket Created',
                    ['ticket_id' => $ticket_id]
                );
            }
            
            return (bool)$ticket_id;
        }
        catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("Exception in create_dns_configuration_ticket: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    __FUNCTION__,
                    ['order' => $this->order],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    /**
     * Get DNS configuration message
     *
     * @param string $lang Language code
     * @param string $domain Domain name
     * @param array $required_records Required DNS records
     * @return string Message content
     */
    private function get_dns_configuration_message($lang, $domain, $required_records) {
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
    /**
     * Client Area Display
     */
    public function clientArea()
    {
        $content = $this->clientArea_buttons_output();
        $_page   = $this->page;

        if(!$_page) $_page = 'home';

        $domain = isset($this->options["config"]["blackwall_domain"]) 
            ? $this->options["config"]["blackwall_domain"] 
            : false;
        
        // Use master API key from module settings
        $api_key = $this->config["settings"]["api_key"];
        
        // Get origin IPs if stored in config
        $origin_ips = [
            'ipv4' => isset($this->options["config"]["origin_ipv4"]) 
                ? json_decode($this->options["config"]["origin_ipv4"], true) 
                : [],
            'ipv6' => isset($this->options["config"]["origin_ipv6"]) 
                ? json_decode($this->options["config"]["origin_ipv6"], true) 
                : []
        ];
        
        $variables = [
            'domain' => $domain,
            'api_key' => $api_key,
            'origin_ips' => $origin_ips,
            'lang' => $this->lang,
        ];

        $content .= $this->get_page('client/'.$_page, $variables);
        return $content;
    }

    /**
     * Client Area Buttons
     */
    public function clientArea_buttons()
    {
        $buttons = [];
        
        if($this->page && $this->page != "home")
        {
            $buttons['home'] = [
                'text' => $this->lang["turn_back"],
                'type' => 'page-loader',
            ];
        }
        return $buttons;
    }
    /**
     * Admin Area Service Fields
     */
    public function adminArea_service_fields(){
        $config = $this->options["config"];
        
        $user_domain = isset($config["blackwall_domain"]) ? $config["blackwall_domain"] : NULL;
        
        $origin_ipv4 = isset($config["origin_ipv4"]) 
            ? implode(", ", json_decode($config["origin_ipv4"], true)) 
            : "";
        
        $origin_ipv6 = isset($config["origin_ipv6"]) 
            ? implode(", ", json_decode($config["origin_ipv6"], true)) 
            : "";
        
        return [
            'blackwall_domain' => [
                'wrap_width' => 100,
                'name' => $this->lang["domain_name"],
                'description' => $this->lang["domain_description"],
                'type' => "text",
                'value' => $user_domain,
            ],
            'origin_ipv4' => [
                'wrap_width' => 100,
                'name' => $this->lang["origin_ipv4"] ?? 'Origin IPv4 Addresses',
                'description' => $this->lang["origin_ipv4_description"] ?? 'Original website IPv4 addresses before Blackwall protection',
                'type' => "text",
                'value' => $origin_ipv4,
            ],
            'origin_ipv6' => [
                'wrap_width' => 100,
                'name' => $this->lang["origin_ipv6"] ?? 'Origin IPv6 Addresses',
                'description' => $this->lang["origin_ipv6_description"] ?? 'Original website IPv6 addresses before Blackwall protection',
                'type' => "text",
                'value' => $origin_ipv6,
            ],
        ];
    }

    /**
     * Save Admin Area Service Fields
     */
    public function save_adminArea_service_fields($data=[]){
        /* OLD DATA */
        $o_config = $data['old']['config'];
        
        /* NEW DATA */
        $n_config = $data['new']['config'];
        
        // Validate domain
        if(!isset($n_config['blackwall_domain']) || $n_config['blackwall_domain'] == '') {
            $this->error = $this->lang["error_missing_domain"];
            return false;
        }
        
        // Check if domain needs updating
        if($o_config['blackwall_domain'] != $n_config['blackwall_domain']) {
            // This would be complex to implement since it requires recreating
            // the domain in Blackwall. For simplicity, we'll disallow this.
            $this->error = $this->lang["error_cannot_change_domain"];
            return false;
        }
        
        // Process origin IPs if provided
        if (isset($n_config['origin_ipv4']) && trim($n_config['origin_ipv4']) !== '') {
            $ipv4_array = array_map('trim', explode(',', $n_config['origin_ipv4']));
            $n_config['origin_ipv4'] = json_encode(array_filter($ipv4_array));
        }
        
        if (isset($n_config['origin_ipv6']) && trim($n_config['origin_ipv6']) !== '') {
            $ipv6_array = array_map('trim', explode(',', $n_config['origin_ipv6']));
            $n_config['origin_ipv6'] = json_encode(array_filter($ipv6_array));
        }
        
        return [
            'config' => $n_config,
        ];
    }
    /**
     * Detect origin server IPs before applying Blackwall protection
     * 
     * @param string $domain Domain to check
     * @return array Origin server IPs
     */
    private function detectOriginIps($domain) {
        $this->logExecutionTime('Starting origin IP detection');
        
        $origin_ips = [
            'ipv4' => [],
            'ipv6' => []
        ];
        
        if(isset($this->helpers['log'])) {
            $this->helpers['log']->info(
                'Detecting origin server IPs',
                ['domain' => $domain]
            );
        }
        
        try {
            // Force DNS lookup even if DNS is failing
            if(isset($this->helpers['dns'])) {
                // Get original IPv4 records before pointing to Blackwall
                $ipv4_records = $this->helpers['dns']->getDomainARecords($domain, true);
                if($ipv4_records && is_array($ipv4_records) && !empty($ipv4_records)) {
                    // Filter out Blackwall IPs to avoid confusion
                    $blackwall_ipv4 = [
                        BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                        BlackwallConstants::GATEKEEPER_NODE_2_IPV4
                    ];
                    
                    $origin_ips['ipv4'] = array_values(array_diff($ipv4_records, $blackwall_ipv4));
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Detected origin IPv4 addresses',
                            ['ipv4' => $origin_ips['ipv4']]
                        );
                    }
                }
                
                // Get original IPv6 records before pointing to Blackwall
                $ipv6_records = $this->helpers['dns']->getDomainAAAARecords($domain, true);
                if($ipv6_records && is_array($ipv6_records) && !empty($ipv6_records)) {
                    // Filter out Blackwall IPs to avoid confusion
                    $blackwall_ipv6 = [
                        BlackwallConstants::GATEKEEPER_NODE_1_IPV6,
                        BlackwallConstants::GATEKEEPER_NODE_2_IPV6
                    ];
                    
                    $origin_ips['ipv6'] = array_values(array_diff($ipv6_records, $blackwall_ipv6));
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Detected origin IPv6 addresses',
                            ['ipv6' => $origin_ips['ipv6']]
                        );
                    }
                }
            }
        } catch (Exception $e) {
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->warning(
                    'Error detecting origin IPs',
                    ['domain' => $domain, 'error' => $e->getMessage()]
                );
            }
        }
        
        // If no IPs were detected, use default fallback values
        if(empty($origin_ips['ipv4'])) {
            // Try to find the domain in cPanel or hosting platform
            $origin_ips['ipv4'] = $this->findHostingIp($domain);
            
            // Still no luck? Use a common web hosting IP or localhost as fallback
            if(empty($origin_ips['ipv4'])) {
                $origin_ips['ipv4'] = ['127.0.0.1']; // localhost as fallback
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Using fallback IPv4 address',
                        ['domain' => $domain, 'fallback' => $origin_ips['ipv4']]
                    );
                }
            }
        }
        
        $this->logExecutionTime('Completed origin IP detection');
        
        return $origin_ips;
    }
    
    /**
     * Find hosting IP for a domain
     * Attempts to find the hosting IP from internal systems
     * 
     * @param string $domain Domain to check
     * @return array IPv4 addresses
     */
    private function findHostingIp($domain) {
        // Default IP if nothing can be found
        $default_ips = ['127.0.0.1'];
        
        // Try to find the hosting IP in WISECP hosting module
        try {
            // Check if we can get the IP from the hosting module
            if (class_exists('Models\\Products')) {
                $product_info = \Models\Products::get($this->order["pid"]);
                
                if(isset($product_info['module']) && $product_info['module']) {
                    // Module exists, try to get server IP
                    $server_id = $product_info['server_id'] ?? null;
                    
                    if($server_id) {
                        // Try to get server info
                        if(class_exists('Models\\Server')) {
                            $server_info = \Models\Server::get($server_id);
                            
                            if(isset($server_info['ip']) && $server_info['ip']) {
                                return [$server_info['ip']];
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->warning(
                    'Error finding hosting IP',
                    ['domain' => $domain, 'error' => $e->getMessage()]
                );
            }
        }
        
        return $default_ips;
    }
    /**
     * Create new Blackwall service
     * Optimized version with proper origin IP detection
     * 
     * @param array $order_options Additional order options
     * @return array|false Service creation result
     */
    public function create($order_options = [])
    {
        // Start the timer for performance monitoring
        $startTime = microtime(true);
        
        try {
            // First try to get domain from order options
            $user_domain = isset($this->order["options"]["domain"]) 
                ? $this->order["options"]["domain"] 
                : false;
            
            // If not found, try getting from requirements
            if(!$user_domain && isset($this->val_of_requirements["user_domain"])) {
                $user_domain = $this->val_of_requirements["user_domain"];
            }
            
            if(!$user_domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }

            // Get user information from WISECP user data
            $user_email = isset($this->user["email"]) ? $this->user["email"] : "";
            $user_first_name = isset($this->user["name"]) ? $this->user["name"] : "";
            $user_last_name = isset($this->user["surname"]) ? $this->user["surname"] : "";
            
            if(!$user_email) {
                $this->error = $this->lang["error_missing_required_fields"];
                return false;
            }
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Starting Blackwall service creation',
                    [
                        'domain' => $user_domain, 
                        'email' => $user_email,
                        'start_time' => date('Y-m-d H:i:s')
                    ]
                );
            }
            
            // IMPORTANT: Detect origin server IPs before applying Blackwall protection
            $origin_ips = $this->detectOriginIps($user_domain);
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Detected origin server IPs',
                    [
                        'domain' => $user_domain,
                        'ipv4' => $origin_ips['ipv4'],
                        'ipv6' => $origin_ips['ipv6']
                    ]
                );
            }
            // Step 1: Create the user in Botguard
            $user_data = [
                'email' => $user_email,
                'first_name' => $user_first_name,
                'last_name' => $user_last_name
            ];
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Creating user in Botguard',
                    $user_data
                );
            }
            
            $user_result = $this->helpers['api']->request('/user', 'POST', $user_data);
            
            // Log execution time
            $this->logExecutionTime('Creating user in Botguard');
            
            // Handle errors but continue with default values if needed
            if(isset($user_result['status']) && $user_result['status'] === 'error') {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error creating user in Botguard, using placeholder values',
                        ['error' => $user_result['message'] ?? 'Unknown error']
                    );
                }
                
                // Use default user ID and API key
                $user_id = 10000; // Placeholder ID
                $user_api_key = 'placeholder_api_key'; // Placeholder API key
            } else {
                // Extract the user ID and API key from the response
                $user_id = isset($user_result['id']) ? $user_result['id'] : 10000;
                $user_api_key = isset($user_result['api_key']) ? $user_result['api_key'] : 'placeholder_api_key';
            }
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'User created in Botguard',
                    ['user_id' => $user_id]
                );
            }
            // Step 2: Create the website in Botguard
            $website_data = [
                'domain' => $user_domain,
                'user_id' => $user_id,
                'status' => 'setup'
            ];
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Creating website in Botguard',
                    $website_data
                );
            }
            
            $website_result = $this->helpers['api']->request('/website', 'POST', $website_data);
            
            // Log execution time
            $this->logExecutionTime('Creating website in Botguard');
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Website created in Botguard',
                    $website_result
                );
            }
            
            // Step 3: Create the user in GateKeeper - but don't wait for completion
            try {
                $gatekeeper_user_data = [
                    'id' => $user_id,
                    'email' => $user_email,
                    'name' => $user_first_name . ' ' . $user_last_name,
                    'tag' => ['wisecp'] // Add tag
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Creating user in GateKeeper',
                        $gatekeeper_user_data
                    );
                }
                
                // Set shorter timeout for GateKeeper API
                $gatekeeper_user_result = $this->helpers['api']->gatekeeperRequest('/user', 'POST', $gatekeeper_user_data);
                
                // Log execution time
                $this->logExecutionTime('Creating user in GateKeeper');
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'User created in GateKeeper',
                        $gatekeeper_user_result
                    );
                }
            } catch (Exception $gk_user_e) {
                // Log error but continue - the operation is non-critical
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error creating user in GateKeeper - continuing anyway',
                        ['error' => $gk_user_e->getMessage()]
                    );
                }
            }
            // Step 4: Add the domain in GateKeeper with proper origin IPs - but don't wait for completion
            try {
                // IMPORTANT: Use detected origin IPs as upstream servers
                $domain_ips = empty($origin_ips['ipv4']) 
                    ? [BlackwallConstants::GATEKEEPER_NODE_1_IPV4, BlackwallConstants::GATEKEEPER_NODE_2_IPV4]
                    : $origin_ips['ipv4'];
                
                $domain_ipv6s = empty($origin_ips['ipv6'])
                    ? [BlackwallConstants::GATEKEEPER_NODE_1_IPV6, BlackwallConstants::GATEKEEPER_NODE_2_IPV6]
                    : $origin_ips['ipv6'];
                
                // Prepare GateKeeper website data with origin IPs as upstream
                $gatekeeper_website_data = [
                    'domain' => $user_domain,
                    'subdomain' => ['www'],
                    'ip' => $domain_ips,
                    'ipv6' => $domain_ipv6s,
                    'user_id' => $user_id,
                    'tag' => ['wisecp'],
                    'status' => BlackwallConstants::STATUS_SETUP,
                    'settings' => BlackwallConstants::getDefaultWebsiteSettings(),
                    // Add upstream configuration
                    'upstream' => [
                        'ip' => $origin_ips['ipv4'],
                        'ipv6' => $origin_ips['ipv6']
                    ]
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Creating domain in GateKeeper with proper upstream configuration',
                        [
                            'domain' => $user_domain, 
                            'upstream_ipv4' => $origin_ips['ipv4'],
                            'upstream_ipv6' => $origin_ips['ipv6']
                        ]
                    );
                }
                
                $gatekeeper_website_result = $this->helpers['api']->gatekeeperRequest('/website', 'POST', $gatekeeper_website_data);
                
                // Log execution time
                $this->logExecutionTime('Creating domain in GateKeeper');
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain created in GateKeeper',
                        $gatekeeper_website_result
                    );
                }
            } catch (Exception $gk_website_e) {
                // Log error but continue - the domain might already exist in GateKeeper
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error creating domain in GateKeeper - continuing anyway',
                        ['domain' => $user_domain, 'error' => $gk_website_e->getMessage()]
                    );
                }
            }
            // Step 5: Activate the domain by setting status to online in Botguard
            try {
                $update_data = [
                    'status' => BlackwallConstants::STATUS_ONLINE
                ];
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Updating domain status to online in Botguard',
                        ['domain' => $user_domain]
                    );
                }
                
                $update_result = $this->helpers['api']->request('/website/' . $user_domain, 'PUT', $update_data);
                
                // Log execution time
                $this->logExecutionTime('Updating domain status');
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status updated in Botguard',
                        $update_result
                    );
                }
            } catch (Exception $update_e) {
                // Log the error but continue - non-critical operation
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error updating domain status in Botguard - continuing anyway',
                        ['domain' => $user_domain, 'error' => $update_e->getMessage()]
                    );
                }
            }
            
            // Register hook for DNS verification after creation - but don't wait for completion
            if(isset($this->helpers['dns'])) {
                try {
                    $hook_result = $this->helpers['dns']->registerDnsCheckHook($user_domain, $this->order["id"]);
                    
                    // Log execution time
                    $this->logExecutionTime('Registering DNS check hook');
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'DNS check hook registered',
                            ['result' => $hook_result ? 'Success' : 'Failed']
                        );
                    }
                } catch (Exception $hook_e) {
                    // Log the error but continue - non-critical operation
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error registering DNS check hook - continuing anyway',
                            ['error' => $hook_e->getMessage()]
                        );
                    }
                }
            }
            
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Blackwall service creation completed',
                    [
                        'domain' => $user_domain,
                        'user_id' => $user_id,
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ]
                );
            }
            
            // Return the successful data to store in the service, including origin IPs
            return [
                'config' => [
                    'blackwall_domain' => $user_domain,
                    'blackwall_user_id' => $user_id,
                    'blackwall_api_key' => $user_api_key,
                    'origin_ipv4' => json_encode($origin_ips['ipv4']),
                    'origin_ipv6' => json_encode($origin_ips['ipv6']),
                ],
                'creation_info' => []
            ];
        } catch (Exception $e) {
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    'Error in service creation',
                    [
                        'domain' => isset($user_domain) ? $user_domain : 'N/A',
                        'error' => $e->getMessage(),
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    /**
     * Renewal of service with upstream IP preservation
     * 
     * @param array $order_options Additional order options
     * @return bool Success status
     */
    public function renewal($order_options=[])
    {
        // Start the timer for performance monitoring
        $startTime = microtime(true);
        
        try {
            // For renewal, we need to verify the domain is still active
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;

            // Retrieve stored origin IPs
            $origin_ipv4 = isset($this->options["config"]["origin_ipv4"]) 
                ? json_decode($this->options["config"]["origin_ipv4"], true) 
                : [];
                
            $origin_ipv6 = isset($this->options["config"]["origin_ipv6"]) 
                ? json_decode($this->options["config"]["origin_ipv6"], true) 
                : [];

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Starting service renewal',
                    [
                        'domain' => $domain, 
                        'user_id' => $user_id,
                        'origin_ipv4' => $origin_ipv4,
                        'origin_ipv6' => $origin_ipv6
                    ]
                );
            }
            
            // Call the Botguard API to verify the domain exists
            $result = $this->helpers['api']->request('/website/' . $domain, 'GET');
            
            // Log execution time
            $this->logExecutionTime('Getting domain status');
            // Check if domain is paused and reactivate if needed
            if(isset($result['status']) && $result['status'] === BlackwallConstants::STATUS_PAUSED) {
                $update_data = [
                    'status' => BlackwallConstants::STATUS_ONLINE
                ];
                
                // Update in Botguard
                $update_result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
                
                // Log execution time
                $this->logExecutionTime('Setting domain status to online');
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status updated in Botguard',
                        $update_result
                    );
                }
                
                // Also update in GateKeeper, ensuring origin IPs are preserved
                try {
                    // If we don't have origin IPs stored, try to detect them
                    if (empty($origin_ipv4) && empty($origin_ipv6)) {
                        $detected_ips = $this->detectOriginIps($domain);
                        $origin_ipv4 = $detected_ips['ipv4'];
                        $origin_ipv6 = $detected_ips['ipv6'];
                        
                        if(isset($this->helpers['log'])) {
                            $this->helpers['log']->info(
                                'Using detected origin IPs for renewal',
                                [
                                    'domain' => $domain,
                                    'origin_ipv4' => $origin_ipv4,
                                    'origin_ipv6' => $origin_ipv6
                                ]
                            );
                        }
                    }
                    
                    // Use Blackwall node IPs for protection
                    $gatekeeper_ipv4 = [
                        BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                        BlackwallConstants::GATEKEEPER_NODE_2_IPV4
                    ];
                    
                    $gatekeeper_ipv6 = [
                        BlackwallConstants::GATEKEEPER_NODE_1_IPV6,
                        BlackwallConstants::GATEKEEPER_NODE_2_IPV6
                    ];
                    
                    // Prepare GateKeeper update data with proper upstream configuration
                    $gatekeeper_data = [
                        'status' => BlackwallConstants::STATUS_ONLINE,
                        'ip' => $gatekeeper_ipv4,
                        'ipv6' => $gatekeeper_ipv6,
                        'user_id' => $user_id,
                        // Include upstream configuration
                        'upstream' => [
                            'ip' => $origin_ipv4,
                            'ipv6' => $origin_ipv6
                        ]
                    ];
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Updating website in GateKeeper with upstream configuration',
                            [
                                'domain' => $domain,
                                'upstream_ipv4' => $origin_ipv4,
                                'upstream_ipv6' => $origin_ipv6
                            ]
                        );
                    }
                    
                    $gatekeeper_result = $this->helpers['api']->gatekeeperRequest(
                        '/website/' . $domain,
                        'PUT',
                        $gatekeeper_data
                    );
                    // Log execution time
                    $this->logExecutionTime('Updating website in GateKeeper');
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Website updated in GateKeeper',
                            $gatekeeper_result
                        );
                    }
                } catch (Exception $gk_e) {
                    // Log error but continue - don't fail if GateKeeper update fails
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->warning(
                            'Error updating domain in GateKeeper - continuing anyway',
                            ['domain' => $domain, 'error' => $gk_e->getMessage()]
                        );
                    }
                }
            }
            
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Service renewal completed',
                    [
                        'domain' => $domain,
                        'user_id' => $user_id,
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ]
                );
            }
            
            return true;
        }
        catch (Exception $e) {
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    'Error in service renewal',
                    [
                        'domain' => isset($domain) ? $domain : 'N/A',
                        'error' => $e->getMessage(),
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    /**
     * Suspend service with origin IP preservation
     */
    public function suspend()
    {
        // Start the timer for performance monitoring
        $startTime = microtime(true);
        
        try {
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;
            
            // Retrieve stored origin IPs
            $origin_ipv4 = isset($this->options["config"]["origin_ipv4"]) 
                ? json_decode($this->options["config"]["origin_ipv4"], true) 
                : [];
                
            $origin_ipv6 = isset($this->options["config"]["origin_ipv6"]) 
                ? json_decode($this->options["config"]["origin_ipv6"], true) 
                : [];

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }

            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Starting service suspension',
                    [
                        'domain' => $domain, 
                        'user_id' => $user_id,
                        'origin_ipv4' => $origin_ipv4,
                        'origin_ipv6' => $origin_ipv6
                    ]
                );
            }
            
            // Step 1: Call the Botguard API to set domain status to 'paused'
            $update_data = [
                'status' => BlackwallConstants::STATUS_PAUSED
            ];
            
            $result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
            
            // Log execution time
            $this->logExecutionTime('Setting domain status to paused');
            
            // Skip GateKeeper operations if API call failed
            if(isset($result['status']) && $result['status'] === 'error') {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in Botguard - continuing anyway',
                        ['error' => $result['message'] ?? 'Unknown error']
                    );
                }
            } else {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to paused in Botguard',
                        $result
                    );
                }
            }
            // Step 2: Also update the domain status in GateKeeper, maintaining upstream configuration
            try {
                // Use Blackwall node IPs for protection
                $gatekeeper_ipv4 = [
                    BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                    BlackwallConstants::GATEKEEPER_NODE_2_IPV4
                ];
                
                $gatekeeper_ipv6 = [
                    BlackwallConstants::GATEKEEPER_NODE_1_IPV6,
                    BlackwallConstants::GATEKEEPER_NODE_2_IPV6
                ];
                
                // Prepare GateKeeper update data with proper upstream configuration
                $gatekeeper_data = [
                    'status' => BlackwallConstants::STATUS_PAUSED,
                    'ip' => $gatekeeper_ipv4,
                    'ipv6' => $gatekeeper_ipv6,
                    'user_id' => $user_id,
                    // Include upstream configuration
                    'upstream' => [
                        'ip' => $origin_ipv4,
                        'ipv6' => $origin_ipv6
                    ]
                ];
                
                $gatekeeper_result = $this->helpers['api']->gatekeeperRequest(
                    '/website/' . $domain, 
                    'PUT', 
                    $gatekeeper_data
                );
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to paused in GateKeeper',
                        $gatekeeper_result
                    );
                }
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in GateKeeper - continuing anyway',
                        ['domain' => $domain, 'error' => $gk_e->getMessage()],
                        $gk_e->getMessage(),
                        $gk_e->getTraceAsString()
                    );
                }
            }
            
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Service suspension completed',
                    [
                        'domain' => $domain,
                        'user_id' => $user_id,
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ]
                );
            }
            
            return true;
        } catch (Exception $e) {
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    'Error in service suspension',
                    [
                        'domain' => isset($domain) ? $domain : 'N/A',
                        'error' => $e->getMessage(),
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
    
    /**
     * Unsuspend service with origin IP preservation
     */
    public function unsuspend()
    {
        // Start the timer for performance monitoring
        $startTime = microtime(true);
        
        try {
            $domain = isset($this->options["config"]["blackwall_domain"]) 
                ? $this->options["config"]["blackwall_domain"] 
                : false;
            
            $user_id = isset($this->options["config"]["blackwall_user_id"]) 
                ? $this->options["config"]["blackwall_user_id"] 
                : false;
            
            // Retrieve stored origin IPs
            $origin_ipv4 = isset($this->options["config"]["origin_ipv4"]) 
                ? json_decode($this->options["config"]["origin_ipv4"], true) 
                : [];
                
            $origin_ipv6 = isset($this->options["config"]["origin_ipv6"]) 
                ? json_decode($this->options["config"]["origin_ipv6"], true) 
                : [];

            if(!$domain) {
                $this->error = $this->lang["error_missing_domain"];
                return false;
            }
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Starting service unsuspension',
                    [
                        'domain' => $domain, 
                        'user_id' => $user_id,
                        'origin_ipv4' => $origin_ipv4,
                        'origin_ipv6' => $origin_ipv6
                    ]
                );
            }
            
            // Step 1: Call the Botguard API to set domain status to 'online'
            $update_data = [
                'status' => BlackwallConstants::STATUS_ONLINE
            ];
            
            $result = $this->helpers['api']->request('/website/' . $domain, 'PUT', $update_data);
            
            // Log execution time
            $this->logExecutionTime('Setting domain status to online');
            
            // Skip GateKeeper operations if API call failed
            if(isset($result['status']) && $result['status'] === 'error') {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in Botguard - continuing anyway',
                        ['error' => $result['message'] ?? 'Unknown error']
                    );
                }
            } else {
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to online in Botguard',
                        $result
                    );
                }
            }
            
            // Step 2: Also update the domain status in GateKeeper, maintaining upstream configuration
            try {
                // If we don't have origin IPs stored, try to detect them
                if (empty($origin_ipv4) && empty($origin_ipv6)) {
                    $detected_ips = $this->detectOriginIps($domain);
                    $origin_ipv4 = $detected_ips['ipv4'];
                    $origin_ipv6 = $detected_ips['ipv6'];
                    
                    if(isset($this->helpers['log'])) {
                        $this->helpers['log']->info(
                            'Using detected origin IPs for unsuspension',
                            [
                                'domain' => $domain,
                                'origin_ipv4' => $origin_ipv4,
                                'origin_ipv6' => $origin_ipv6
                            ]
                        );
                    }
                    
                    // Update the stored origin IPs for future use
                    $current_config = isset($this->options["config"]) ? $this->options["config"] : [];
                    $current_config['origin_ipv4'] = json_encode($origin_ipv4);
                    $current_config['origin_ipv6'] = json_encode($origin_ipv6);
                    
                    // Try to update the order config
                    if (class_exists('Order')) {
                        if (method_exists('Order', 'set')) {
                            Order::set($this->order["id"], [
                                'options' => [
                                    'config' => $current_config
                                ]
                            ]);
                            
                            if(isset($this->helpers['log'])) {
                                $this->helpers['log']->info(
                                    'Updated stored origin IPs in order config',
                                    [
                                        'order_id' => $this->order["id"],
                                        'origin_ipv4' => $origin_ipv4,
                                        'origin_ipv6' => $origin_ipv6
                                    ]
                                );
                            }
                        }
                    }
                }
                
                // Use Blackwall node IPs for protection
                $gatekeeper_ipv4 = [
                    BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
                    BlackwallConstants::GATEKEEPER_NODE_2_IPV4
                ];
                
                $gatekeeper_ipv6 = [
                    BlackwallConstants::GATEKEEPER_NODE_1_IPV6,
                    BlackwallConstants::GATEKEEPER_NODE_2_IPV6
                ];
                // Prepare GateKeeper update data with proper upstream configuration
                $gatekeeper_data = [
                    'status' => BlackwallConstants::STATUS_ONLINE,
                    'ip' => $gatekeeper_ipv4,
                    'ipv6' => $gatekeeper_ipv6,
                    'user_id' => $user_id,
                    // Include upstream configuration
                    'upstream' => [
                        'ip' => $origin_ipv4,
                        'ipv6' => $origin_ipv6
                    ]
                ];
                
                $gatekeeper_result = $this->helpers['api']->gatekeeperRequest(
                    '/website/' . $domain, 
                    'PUT', 
                    $gatekeeper_data
                );
                
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->info(
                        'Domain status set to online in GateKeeper with upstream configuration',
                        [
                            'domain' => $domain,
                            'result' => $gatekeeper_result,
                            'upstream_ipv4' => $origin_ipv4,
                            'upstream_ipv6' => $origin_ipv6
                        ]
                    );
                }
                
                // Register hook for DNS verification after unsuspension
                $this->helpers['dns']->registerDnsCheckHook($domain, $this->order["id"], $this->order["owner_id"]);
            } catch (Exception $gk_e) {
                // Log error but continue - don't fail if GateKeeper update fails
                if(isset($this->helpers['log'])) {
                    $this->helpers['log']->warning(
                        'Error setting domain status in GateKeeper - continuing anyway',
                        ['domain' => $domain, 'error' => $gk_e->getMessage()],
                        $gk_e->getMessage(),
                        $gk_e->getTraceAsString()
                    );
                }
            }
            
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->info(
                    'Service unsuspension completed',
                    [
                        'domain' => $domain,
                        'user_id' => $user_id,
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ]
                );
            }
            
            return true;
        } catch (Exception $e) {
            // Calculate total execution time
            $totalExecutionTime = microtime(true) - $startTime;
            
            $this->error = $e->getMessage();
            if(isset($this->helpers['log'])) {
                $this->helpers['log']->error(
                    'Error in service unsuspension',
                    [
                        'domain' => isset($domain) ? $domain : 'N/A',
                        'error' => $e->getMessage(),
                        'total_execution_time' => round($totalExecutionTime, 4) . 's'
                    ],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
            return false;
        }
    }
}

// Hook loading outside the class definition
include __DIR__.DS."hook_loader.php";
