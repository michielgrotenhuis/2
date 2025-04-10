<?php 
return [
    // General
    'name'                          => 'Blackwall (BotGuard) Websitebeveiliging',
    'turn_back'                     => 'Terug',
    
    // Module configuration
    'api_key'                       => 'API Sleutel',
    'api_key_desc'                  => 'API sleutel van uw Blackwall account',
    'primary_server'                => 'Primaire Server',
    'primary_server_desc'           => 'Het adres van de primaire Blackwall node',
    'secondary_server'              => 'Secundaire Server',
    'secondary_server_desc'         => 'Het adres van de secundaire Blackwall node (optioneel)',
    
    // Product configuration
    'domain_name'                   => 'Domeinnaam',
    'domain_description'            => 'Domein beschermd door Blackwall',
    'user_email'                    => 'E-mailadres',
    'user_email_description'        => 'E-mailadres voor het Blackwall account',
    'first_name'                    => 'Voornaam',
    'first_name_description'        => 'Voornaam van de gebruiker',
    'last_name'                     => 'Achternaam',
    'last_name_description'         => 'Achternaam van de gebruiker',
    
    // Admin service fields
    'blackwall_user_id'             => 'Blackwall Gebruikers-ID',
    'blackwall_user_id_description' => 'De gebruikers-ID in het Blackwall systeem',
    'blackwall_api_key'             => 'Blackwall API Sleutel',
    'blackwall_api_key_description' => 'API sleutel voor gebruikerstoegang tot Blackwall',
    
    // Client area
    'view_statistics'               => 'Bekijk Statistieken',
    'view_events'                   => 'Bekijk Gebeurtenissenlogboek',
    'edit_settings'                 => 'Bewerk Beveiligingsinstellingen',
    'service_info'                  => 'Blackwall Beveiligingsinformatie',
    'protected_domain'              => 'Beschermd Domein',
    'status'                        => 'Status',
    'setup_instructions'            => 'Installatie-instructies',
    'instructions_step1'            => 'Stap 1: Uw domein is geregistreerd met Blackwall beveiliging.',
    'instructions_step2'            => 'Stap 2: Om de installatie te voltooien, moet u uw DNS-instellingen bijwerken zodat deze naar de Blackwall servers verwijzen.',
    'instructions_step3'            => 'Stap 3: Voeg de Blackwall IP-adressen toe aan uw DNS A-records of werk uw nameservers bij zoals aangegeven in het Blackwall dashboard.',
    'instructions_step4'            => 'Stap 4: Zodra de DNS-wijzigingen zich verspreiden (dit kan tot 48 uur duren), zal uw website worden beschermd door Blackwall.',
    
    // DNS Configuration
    'dns_configured_correctly'      => 'DNS is correct geconfigureerd voor Blackwall beveiliging.',
    'dns_not_configured_correctly'  => 'DNS is niet correct geconfigureerd. Werk uw DNS-instellingen bij volgens de instructies.',
    'check_dns'                     => 'Controleer DNS-configuratie',
    
    // Admin area
    'view_in_blackwall'             => 'Bekijk in Blackwall',
    'check_status'                  => 'Controleer Status',
    'domain_status'                 => 'Domeinstatus',
    
    // Success messages
    'success_settings_saved'        => 'Instellingen zijn succesvol opgeslagen.',
    
    // Error messages
    'error_api_key_required'        => 'API Sleutel is vereist.',
    'error_invalid_api_key'         => 'Ongeldige API Sleutel. Kon geen verbinding maken met Blackwall.',
    'error_api_connection'          => 'Fout bij het verbinden met Blackwall API:',
    'error_missing_required_fields' => 'Domein en E-mail zijn verplichte velden.',
    'error_missing_domain'          => 'Domeininformatie ontbreekt.',
    'error_cannot_change_domain'    => 'Kan domeinnaam voor bestaande service niet wijzigen.',
];
