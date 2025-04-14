# Blackwall (BotGuard) Website Protection Module for WISECP

## Overview

The Blackwall module is a comprehensive website protection solution for WISECP that integrates seamlessly with the BotGuard service. It provides robust security features to protect websites from various online threats.

## Features

- Automatic website protection registration
- DNS configuration verification
- Integrated statistics and event logging
- Multilingual support (English and Dutch)
- Flexible DNS configuration
- Seamless integration with WISECP

## Requirements

- WISECP Version: Compatible with WISECP v7.x and above
- PHP Version: 7.4+
- BotGuard API Access
- Domains with DNS management capabilities

## Installation

1. Download the latest release from the GitHub repository
2. Extract the files to `/modules/Blackwall/` in your WISECP installation
3. Log in to your WISECP admin panel
4. Navigate to Module Management
5. Find "Blackwall (BotGuard) Website Protection" and click Install
6. Configure the module with your BotGuard API credentials

## Configuration

### Module Settings

- **API Key**: Your BotGuard API key
- **Primary Server**: Primary BotGuard node address
- **Secondary Server**: Secondary BotGuard node address (optional)

### DNS Configuration

The module requires specific DNS configurations to protect your websites:

#### A Records (IPv4)
- `49.13.161.213` (bg-gk-01 node)
- `116.203.242.28` (bg-gk-02 node)

#### AAAA Records (IPv6)
- `2a01:4f8:c2c:5a72::1` (bg-gk-01 node)
- `2a01:4f8:1c1b:7008::1` (bg-gk-02 node)

## Client Area Features

- Domain protection status
- DNS configuration checker
- Statistics view
- Event log
- Protection settings management

## Admin Area Features

- Service management
- DNS configuration verification
- Direct link to BotGuard dashboard

## Hooks and Automation

- Automatic DNS verification
- Support ticket generation for incorrect DNS configurations
- Periodic DNS status checks

## Supported Languages

- English (en)
- Dutch (nl)

More languages can be easily added by creating additional language files.

## Security

- Secure API communication using Bearer token authentication
- Logging of all critical operations
- Multiple layers of error handling
- Protection against various web threats

## Troubleshooting

1. Ensure correct API key configuration
2. Verify DNS records match the required IP addresses
3. Check module logs for detailed error information
4. Confirm PHP version compatibility

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This module is proprietary software. Please refer to the LICENSE file for usage terms.

## Support

For support, please contact:
- Email: support@zencommerce.in
- Website: https://www.zencommerce.in

## Version

Current Version: 1.1

## Changelog

### Version 1.1
- Enhanced DNS verification process
- Improved error logging
- Added Dutch language support
- Stability improvements

## Disclaimer

The Blackwall module is developed by Zencommerce India in partnership with Blackwall, but is not the official module. Use at your own risk, and always maintain backups.
