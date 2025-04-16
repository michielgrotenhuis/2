<?php
/**
 * BlackwallWelcomeHook - Handles welcome message ticket creation for new Blackwall customers
 * Enhanced with improved debugging and reliability
 */

// Make sure BlackwallConstants is loaded
if (!class_exists('BlackwallConstants')) {
    require_once(dirname(__DIR__) . '/BlackwallConstants.php');
}

class BlackwallWelcomeHook
{
    /**
     * Handle order activated hook - creates welcome ticket
     * 
     * @param array $params Hook parameters
     * @return void
     */
    public static function handleOrderActivated($params = [])
    {
        // Direct debug to PHP error log
        error_log("===== BLACKWALL WELCOME HOOK TRIGGERED =====");
        error_log("Order ID: " . (isset($params['id']) ? $params['id'] : 'NOT SET'));
        error_log("Product ID: " . (isset($params['product_id']) ? $params['product_id'] : 'NOT SET'));
        
        // Check if this is Blackwall product (ID 105)
        if (!isset($params['product_id']) || $params['product_id'] != 105) {
            error_log("Skipping - Not a Blackwall product");
            return;
        }
        
        // Dump entire params array for debugging
        error_log("Full params array: " . print_r($params, true));
        
        $log_paths = [
            '/tmp/blackwall_welcome_hook.log',
            __DIR__ . '/../logs/blackwall_welcome_hook.log'
        ];
        
        // Log function with timestamp
        $debug_log = function($message, $data = null) use ($log_paths) {
            $timestamp = date('Y-m-d H:i:s');
            $log_message = "[{$timestamp}] {$message}\n";
            
            if ($data !== null) {
                if (is_array($data) || is_object($data)) {
                    $log_message .= print_r($data, true) . "\n";
                } else {
                    $log_message .= $data . "\n";
                }
            }
            
            // Try to write to both locations
            foreach ($log_paths as $log_path) {
                try {
                    // Create directory if it doesn't exist
                    $dir = dirname($log_path);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    
                    @file_put_contents($log_path, $log_message, FILE_APPEND);
                } catch (\Exception $e) {
                    // Log to PHP error log instead
                    error_log("Failed to write to log file: " . $e->getMessage());
                }
            }
            
            // Always log to PHP error log for easier debugging
            error_log("BlackwallWelcomeHook: " . $message);
        };
        
        $debug_log("Blackwall Welcome Hook triggered for order ID: " . $params['id']);
        
        // Get the domain name from order options
        $domain = isset($params['options']) && isset($params['options']['domain']) 
            ? $params['options']['domain'] 
            : '';
            
        if (empty($domain) && isset($params['options']) && isset($params['options']['config']) && 
            isset($params['options']['config']['blackwall_domain'])) {
            $domain = $params['options']['config']['blackwall_domain'];
        }
        
        error_log("Domain extracted: " . ($domain ?: 'NONE FOUND'));
        $debug_log("Domain: {$domain}");
            
        if (!empty($domain)) {
            // Check if we've already created a welcome ticket for this order
            $welcome_file = sys_get_temp_dir() . '/blackwall_welcome_' . md5($domain . $params['id']) . '.json';
            $ticket_already_created = false;
            
            if (file_exists($welcome_file)) {
                $welcome_data = json_decode(file_get_contents($welcome_file), true);
                $ticket_already_created = isset($welcome_data['ticket_created']) && $welcome_data['ticket_created'] === true;
                error_log("Welcome file found: " . ($ticket_already_created ? 'Ticket already created' : 'No ticket yet'));
            } else {
                error_log("No welcome file found at: " . $welcome_file);
            }
            
            if ($ticket_already_created) {
                $debug_log("Welcome ticket has already been created for this order - skipping");
                return;
            }
            
            // Function to create a welcome ticket
            $create_welcome_ticket = function($params, $domain) use ($debug_log) {
                error_log("Starting welcome ticket creation for domain: " . $domain);
                $debug_log("Creating welcome ticket for domain: {$domain}");
                
                try {
                    // Get client ID from order parameters
                    $client_id = isset($params['owner_id']) ? $params['owner_id'] : 0;
                    
                    if (!$client_id) {
                        error_log("ERROR: Client ID not found in params");
                        $debug_log("Client ID not found in params");
                        return false;
                    }
                    
                    error_log("Client ID: " . $client_id);
                    
                    // Get client data to determine language
                    $client = [];
                    $client_lang = 'en'; // Default language
                    $client_name = 'Customer';
                    $client_company = '';
                    
                    if (class_exists('User')) {
                        error_log("User class exists, getting user data");
                        $client = User::getData($client_id);
                        error_log("User data result: " . print_r($client, true));
                        
                        if (isset($client['lang'])) {
                            $client_lang = $client['lang'];
                        }
                        if (isset($client['name']) && isset($client['surname'])) {
                            $client_name = $client['name'] . ' ' . $client['surname'];
                        }
                        if (isset($client['company_name'])) {
                            $client_company = $client['company_name'];
                        }
                    } else {
                        error_log("WARNING: User class does not exist");
                    }
                    
                    error_log("Client language: " . $client_lang);
                    error_log("Client name: " . $client_name);
                    error_log("Client company: " . $client_company);
                    $debug_log("Client language: {$client_lang}");
                    
                    // Get the order details URL (substitute with actual URL pattern)
                    $order_detail_link = 'https://mijn.klikonline.nl/myaccount/app-details.php?id=' . $params['id'];
                    
                    // Generate the welcome message based on language
                    error_log("Generating welcome message in language: " . $client_lang);
                    $message = self::getWelcomeMessage($client_lang, [
                        'user_full_name' => $client_name,
                        'user_company_name' => $client_company,
                        'website_url' => 'https://' . $domain,
                        'order_detail_link' => $order_detail_link
                    ]);
                    
                    // Log message length for debugging
                    error_log("Welcome message generated. Length: " . strlen($message));
                    
                    // Prepare ticket data
                    $ticket_title = $client_lang == 'nl' 
                        ? "Welkom bij Blackwall Protection voor {$domain}" 
                        : "Welcome to Blackwall Protection for {$domain}";
                    
                    $ticket_data = [
                        'user_id' => $client_id,
                        'did' => 1, // Department ID - adjust as needed
                        'priority' => 2, // Medium priority
                        'status' => 'process', // In progress
                        'title' => $ticket_title,
                        'message' => $message,
                        'service' => $params['id'] // Order ID
                    ];
                    
                    error_log("About to create ticket with data: " . print_r($ticket_data, true));
                    
                    // Create the ticket - check all possible class paths
                    $ticket_id = 0;
                    $ticket_class_used = '';
                    
                    if (class_exists('\\Models\\Tickets\\Tickets')) {
                        error_log("Using \\Models\\Tickets\\Tickets class");
                        $ticket_class_used = '\\Models\\Tickets\\Tickets';
                        $ticket_id = \Models\Tickets\Tickets::insert($ticket_data);
                    } elseif (class_exists('Models\\Tickets\\Tickets')) {
                        error_log("Using Models\\Tickets\\Tickets class");
                        $ticket_class_used = 'Models\\Tickets\\Tickets';
                        $ticket_id = \Models\Tickets\Tickets::insert($ticket_data);
                    } elseif (class_exists('Tickets')) {
                        error_log("Using Tickets class");
                        $ticket_class_used = 'Tickets';
                        $ticket_id = Tickets::insert($ticket_data);
                    } else {
                        error_log("ERROR: No Tickets class found. Looking for existing classes:");
                        
                        // Debug to find the Tickets class
                        $declared_classes = get_declared_classes();
                        $possible_ticket_classes = array_filter($declared_classes, function($class) {
                            return stripos($class, 'ticket') !== false;
                        });
                        error_log("Possible ticket classes: " . print_r($possible_ticket_classes, true));
                        
                        throw new Exception("Ticket system not found - no appropriate Tickets class exists");
                    }
                    
                    error_log("Ticket creation result: " . ($ticket_id ? "Success (ID: $ticket_id)" : "Failed"));
                    $debug_log("Welcome ticket created: ID {$ticket_id} using class {$ticket_class_used}");
                    
                    // Mark that we've created a ticket for this order
                    $welcome_data = [
                        'domain' => $domain,
                        'order_id' => $params['id'],
                        'ticket_id' => $ticket_id,
                        'ticket_created' => true,
                        'ticket_time' => time()
                    ];
                    
                    $welcome_file = sys_get_temp_dir() . '/blackwall_welcome_' . md5($domain . $params['id']) . '.json';
                    $write_result = file_put_contents($welcome_file, json_encode($welcome_data));
                    error_log("Welcome file written: " . ($write_result ? "Success" : "Failed") . " to " . $welcome_file);
                    
                    return true;
                } catch (Exception $e) {
                    error_log("EXCEPTION in creating welcome ticket: " . $e->getMessage());
                    error_log("Exception trace: " . $e->getTraceAsString());
                    $debug_log("Exception occurred when creating welcome ticket: " . $e->getMessage());
                    $debug_log("Exception trace: " . $e->getTraceAsString());
                    return false;
                }
            };
            
            // Create the welcome ticket
            error_log("Calling create_welcome_ticket function");
            $result = $create_welcome_ticket($params, $domain);
            error_log("Create welcome ticket result: " . ($result ? "Success" : "Failed"));
        } else {
            error_log("ERROR: No domain found in order options");
            $debug_log("No domain found in order options");
        }
    }
    
    /**
     * Get welcome message based on language
     * 
     * @param string $lang Language code
     * @param array $data Replacement data for the message
     * @return string Welcome message
     */
    private static function getWelcomeMessage($lang, $data)
    {
        // Default to English if language not supported
        if (!in_array($lang, ['en', 'nl'])) {
            $lang = 'en';
        }
        
        // Default values for replacements
        $defaults = [
            'user_full_name' => 'Valued Customer',
            'user_company_name' => '',
            'website_url' => 'your website',
            'order_detail_link' => '#'
        ];
        
        // Merge defaults with provided data
        $data = array_merge($defaults, $data);
        $domain = parse_url($data['website_url'], PHP_URL_HOST) ?: $data['website_url'];
        if (strpos($domain, 'https://') === 0) {
            $domain = str_replace('https://', '', $domain);
        }
        
        // Define the required DNS records for Blackwall protection
        $required_records = [
            'A' => BlackwallConstants::GATEKEEPER_NODE_1_IPV4,
            'AAAA' => BlackwallConstants::GATEKEEPER_NODE_1_IPV6
        ];
        
        // Alternative node
        $alternative_node = [
            'name' => 'bg-gk-02',
            'ipv4' => BlackwallConstants::GATEKEEPER_NODE_2_IPV4,
            'ipv6' => BlackwallConstants::GATEKEEPER_NODE_2_IPV6
        ];
        
        // Get welcome message template
        if ($lang == 'nl') {
            $message = self::getDutchWelcomeTemplate();
        } else {
            $message = self::getEnglishWelcomeTemplate();
        }
        
        // Replace placeholders
        $replacements = [
            '{user_full_name}' => $data['user_full_name'],
            '{user_company_name}' => $data['user_company_name'],
            '{website_url}' => $data['website_url'],
            '{domain}' => $domain,
            '{order_detail_link}' => $data['order_detail_link'],
            '{ipv4_record}' => $required_records['A'],
            '{ipv6_record}' => $required_records['AAAA'],
            '{alt_node_name}' => $alternative_node['name'],
            '{alt_ipv4_record}' => $alternative_node['ipv4'],
            '{alt_ipv6_record}' => $alternative_node['ipv6']
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
    
    /**
     * Get English welcome message template
     * 
     * @return string English welcome message template
     */
    private static function getEnglishWelcomeTemplate()
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; color: #333;">
    <div style="padding: 30px 0; background-color: #f5f5f5; background-image: url('https://mijn.klikonline.nl/templates/system/images/mailbg2.jpg'); background-size: cover; background-position: center top; text-align: center;">
        <div style="display: inline-block; background: rgba(0, 0, 0, 0.27); padding: 20px 25px; border-radius: 10px; color: white;">
            <h1 style="margin: 0; font-size: 28px;">Welcome to Blackwall Protection!</h1>
        </div>
    </div>

    <div style="max-width: 650px; margin: 0 auto; padding: 30px 20px;">
        <p style="text-align: center; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
            <span style="font-size: 16pt;">Dear <strong>{user_full_name}</strong></span>
            <br>
            <span style="font-size: 12pt;">{user_company_name}</span>
        </p>

        <p style="text-align: center; font-size: 16pt; color: #4CAF50;"><strong>Congratulations on securing your website with Blackwall!</strong></p>
        <p style="text-align: center; font-size: 14pt;">Just <strong>3 simple steps</strong> to complete your setup:</p>

        <div style="background-color: #f9f9f9; border-left: 4px solid #8bc34a; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 5px 0;"><strong>Step 1:</strong> Create Blackwall account (Completed!)</p>
            <p style="margin: 5px 0;"><strong>Step 2:</strong> Set up DNS records (Instructions below)</p>
            <p style="margin: 5px 0;"><strong>Step 3:</strong> Configure your services</p>
        </div>

        <p><strong>What happens next?</strong></p>
        <p>You're now just a few clicks away from complete website protection. Here's what's happening behind the scenes: when you set up your DNS records, all traffic to your website will first pass through Blackwall's protection system. We'll filter out the bad actors and only let legitimate visitors through to your site!</p>

        <p><strong>DNS Records to Add</strong><br><span style="color: #d5d5d5;">------------------------------------</span></p>

        <div style="background-color: #f6f6f6; padding: 20px; border-radius: 8px; margin: 15px 0;">
            <p><strong>Add these two records to your domain's DNS configuration:</strong></p>

            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr style="background-color: #eaeaea;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Record Type</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Value</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Purpose</th>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dddddd;">A</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">{ipv4_record}</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">Connect to Blackwall Protection (IPv4)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dddddd;">AAAA</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">{ipv6_record}</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">Connect to Blackwall Protection (IPv6)</td>
                </tr>
            </table>

            <p><strong>Need to protect subdomains too?</strong> Make sure they also point to the same Blackwall node as your main domain (e.g., blog.yourdomain.com).</p>
        </div>

        <p><strong>Alternative Nodes</strong></p>
        <p>You can also use this alternative node if you prefer:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="background-color: #eaeaea;">
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Node</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">IPv4 Record (A)</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">IPv6 Record (AAAA)</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_node_name}</td>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_ipv4_record}</td>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_ipv6_record}</td>
            </tr>
        </table>

        <p style="font-style: italic; color: #666666;">Note: Choose either bg-gk-01 or bg-gk-02, but use the same node for both your A and AAAA records. DNS changes can take up to 24 hours to work worldwide.</p>

        <p><strong>Step 3: Configure Your Services</strong></p>
        <p>Once your DNS is set up, you're ready to customize your protection! Just click the button below to access your Services page and start configuring your security settings.</p>

        <div style="text-align: center; width: 90%; margin: 35px auto; border: 1px solid #dfdfdf; padding: 20px 0; background: #f6f6f6; border-radius: 10px;">
            <p>Access your Blackwall protection settings</p>
            <a style="display: inline-block; padding: 12px 30px; margin: 10px 0; background-color: #8bc34a; color: white; font-size: 16px; text-decoration: none; border-radius: 50px;" href="{order_detail_link}" target="_blank" rel="noopener">Manage Service</a>
        </div>

        <p style="text-align: center; font-weight: bold; margin-top: 30px;">Need more help?</p>
        <p style="text-align: center;">Not a tech wizard? No problem! We're here to help with any step of the process.</p>
        <p style="text-align: center;">For detailed guides, visit our <a href="https://mijn.klikonline.nl/knowledgebase/botguard" target="_blank">knowledge base</a>.</p>
        <p style="text-align: center;">Or just <a href="https://mijn.klikonline.nl/myaccount/create-support-requests" target="_blank">submit a quick support ticket</a> and we'll be happy to do the setup for you!</p>

        <p style="text-align: center; margin-top: 30px;">We're thrilled to have you on board and look forward to keeping your website safe!</p>
        <p style="text-align: center; margin-bottom: 30px;">The Blackwall Protection Team</p>
    </div>
</div>
HTML;
    }
    
    /**
     * Get Dutch welcome message template
     * 
     * @return string Dutch welcome message template
     */
    private static function getDutchWelcomeTemplate()
    {
        return <<<HTML
<div style="font-family: Arial, sans-serif; color: #333;">
    <div style="padding: 30px 0; background-color: #f5f5f5; background-image: url('https://mijn.klikonline.nl/templates/system/images/mailbg2.jpg'); background-size: cover; background-position: center top; text-align: center;">
        <div style="display: inline-block; background: rgba(0, 0, 0, 0.27); padding: 20px 25px; border-radius: 10px; color: white;">
            <h1 style="margin: 0; font-size: 28px;">Welkom bij Blackwall Bescherming!</h1>
        </div>
    </div>

    <div style="max-width: 650px; margin: 0 auto; padding: 30px 20px;">
        <p style="text-align: center; border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
            <span style="font-size: 16pt;">Beste <strong>{user_full_name}</strong></span>
            <br>
            <span style="font-size: 12pt;">{user_company_name}</span>
        </p>

        <p style="text-align: center; font-size: 16pt; color: #4CAF50;"><strong> Gefeliciteerd met het beveiligen van je website met Blackwall! </strong></p>
        <p style="text-align: center; font-size: 14pt;">Slechts <strong>3 eenvoudige stappen</strong> om je installatie te voltooien:</p>

        <div style="background-color: #f9f9f9; border-left: 4px solid #8bc34a; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 5px 0;"><strong>Stap 1:</strong> Blackwall-account aanmaken (Voltooid!)</p>
            <p style="margin: 5px 0;"><strong>Stap 2:</strong> DNS-records instellen (Instructies hieronder)</p>
            <p style="margin: 5px 0;"><strong>Stap 3:</strong> Configureer je diensten</p>
        </div>

        <p><strong>Wat gebeurt er hierna?</strong></p>
        <p>Je bent nu nog maar een paar klikken verwijderd van complete websitebescherming. Hier is wat er achter de schermen gebeurt: wanneer je je DNS-records instelt, zal al het verkeer naar je website eerst door het beschermingssysteem van Blackwall gaan. Wij filteren de kwaadwillende bezoekers eruit en laten alleen legitieme bezoekers door naar je site!</p>

        <p><strong>DNS-records om toe te voegen</strong><br><span style="color: #d5d5d5;">------------------------------------</span></p>

        <div style="background-color: #f6f6f6; padding: 20px; border-radius: 8px; margin: 15px 0;">
            <p><strong>Voeg deze twee records toe aan de DNS-configuratie van je domein:</strong></p>

            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <tr style="background-color: #eaeaea;">
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Recordtype</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Waarde</th>
                    <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Doel</th>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dddddd;">A</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">{ipv4_record}</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">Verbinden met Blackwall Bescherming (IPv4)</td>
                </tr>
                <tr>
                    <td style="padding: 10px; border: 1px solid #dddddd;">AAAA</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">{ipv6_record}</td>
                    <td style="padding: 10px; border: 1px solid #dddddd;">Verbinden met Blackwall Bescherming (IPv6)</td>
                </tr>
            </table>

            <p><strong>Moet je ook subdomeinen beschermen?</strong> Zorg ervoor dat ze ook naar dezelfde Blackwall-node wijzen als je hoofddomein (bijv. blog.jouwdomein.nl).</p>
        </div>

        <p><strong>Alternatieve Nodes</strong></p>
        <p>Je kunt ook deze alternatieve node gebruiken als je dat wilt:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr style="background-color: #eaeaea;">
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">Node</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">IPv4-record (A)</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #dddddd;">IPv6-record (AAAA)</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_node_name}</td>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_ipv4_record}</td>
                <td style="padding: 10px; border: 1px solid #dddddd;">{alt_ipv6_record}</td>
            </tr>
        </table>

        <p style="font-style: italic; color: #666666;">Opmerking: Kies voor bg-gk-01 of bg-gk-02, maar gebruik dezelfde node voor zowel je A- als AAAA-records. DNS-wijzigingen kunnen tot 24 uur duren voordat ze wereldwijd werken.</p>

        <p><strong>Stap 3: Configureer je diensten</strong></p>
        <p>Zodra je DNS is ingesteld, ben je klaar om je bescherming aan te passen! Klik gewoon op de onderstaande knop om naar je Services-pagina te gaan en begin met het configureren van je beveiligingsinstellingen.</p>

        <div style="text-align: center; width: 90%; margin: 35px auto; border: 1px solid #dfdfdf; padding: 20px 0; background: #f6f6f6; border-radius: 10px;">
            <p>Toegang tot je Blackwall-beveiligingsinstellingen</p>
            <a style="display: inline-block; padding: 12px 30px; margin: 10px 0; background-color: #8bc34a; color: white; font-size: 16px; text-decoration: none; border-radius: 50px;" href="{order_detail_link}" target="_blank" rel="noopener">Beheer Dienst</a>
        </div>

        <p style="text-align: center; font-weight: bold; margin-top: 30px;">Meer hulp nodig?</p>
        <p style="text-align: center;">Geen techneut? Geen probleem! We staan klaar om te helpen bij elke stap van het proces.</p>
        <p style="text-align: center;">Voor gedetailleerde handleidingen, bezoek onze <a href="https://mijn.klikonline.nl/knowledgebase/botguard" target="_blank">kennisbank</a>.</p>
        <p style="text-align: center;">Of dien gewoon <a href="https://mijn.klikonline.nl/myaccount/create-support-requests" target="_blank">een snelle supportticket in</a> en we helpen je graag met de setup!</p>

        <p style="text-align: center; margin-top: 30px;">We zijn verheugd je aan boord te hebben en kijken ernaar uit om je website veilig te houden!</p>
        <p style="text-align: center; margin-bottom: 30px;">Het Blackwall Beschermingsteam</p>
    </div>
</div>
HTML;
    }
}
