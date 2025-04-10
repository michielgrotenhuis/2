<?php 
return [
    // General
    'name'                          => 'Blackwall (BotGuard) Website Protection',
    'turn_back'                     => 'Back',
    
    // Module configuration
    'api_key'                       => 'API Key',
    'api_key_desc'                  => 'API key from your Blackwall account',
    'primary_server'                => 'Primary Server',
    'primary_server_desc'           => 'The address of the primary Blackwall node',
    'secondary_server'              => 'Secondary Server',
    'secondary_server_desc'         => 'The address of the secondary Blackwall node (optional)',
    
    // Product configuration
    'domain_name'                   => 'Domain Name',
    'domain_description'            => 'Domain protected by Blackwall',
    'user_email'                    => 'Email Address',
    'user_email_description'        => 'Email address for the Blackwall account',
    'first_name'                    => 'First Name',
    'first_name_description'        => 'User\'s first name',
    'last_name'                     => 'Last Name',
    'last_name_description'         => 'User\'s last name',
    
    // Admin service fields
    'blackwall_user_id'             => 'Blackwall User ID',
    'blackwall_user_id_description' => 'The user ID in the Blackwall system',
    'blackwall_api_key'             => 'Blackwall API Key',
    'blackwall_api_key_description' => 'API key for user access to Blackwall',
    
    // Client area
    'view_statistics'               => 'View Statistics',
    'view_events'                   => 'View Events Log',
    'edit_settings'                 => 'Edit Protection Settings',
    'service_info'                  => 'Blackwall Protection Information',
    'protected_domain'              => 'Protected Domain',
    'status'                        => 'Status',
    'setup_instructions'            => 'Setup Instructions',
    'instructions_step1'            => 'Step 1: Your domain has been registered with Blackwall protection.',
    'instructions_step2'            => 'Step 2: To complete the setup, you need to update your DNS settings to point to the Blackwall servers.',
    'instructions_step3'            => 'Step 3: Add the Blackwall IP addresses to your DNS A records or update your nameservers as directed in the Blackwall dashboard.',
    'instructions_step4'            => 'Step 4: Once DNS changes propagate (may take up to 48 hours), your website will be protected by Blackwall.',
    
    // DNS Configuration
    'dns_configured_correctly'      => 'DNS is correctly configured for Blackwall protection.',
    'dns_not_configured_correctly'  => 'DNS is not configured correctly. Please update your DNS settings according to the instructions.',
    'check_dns'                     => 'Check DNS Configuration',
    
    // Admin area
    'view_in_blackwall'             => 'View in Blackwall',
    'check_status'                  => 'Check Status',
    'domain_status'                 => 'Domain Status',
    
    // Success messages
    'success_settings_saved'        => 'Settings have been saved successfully.',
    
    // Error messages
    'error_api_key_required'        => 'API Key is required.',
    'error_invalid_api_key'         => 'Invalid API Key. Could not connect to Blackwall.',
    'error_api_connection'          => 'Error connecting to Blackwall API:',
    'error_missing_required_fields' => 'Domain and Email are required fields.',
    'error_missing_domain'          => 'Domain information is missing.',
    'error_cannot_change_domain'    => 'Cannot change domain name for existing service.',
];
