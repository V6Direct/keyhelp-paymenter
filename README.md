# KeyHelp Extension for Paymenter

Automatically provision web hosting accounts on [KeyHelp](https://www.keyweb.de/en/keyhelp/keyhelp/) panel through [Paymenter](https://paymenter.org) billing system.

## Features

- ✅ Automatic account provisioning
- ✅ Suspend/Unsuspend accounts
- ✅ Terminate accounts
- ✅ Plan upgrades/downgrades
- ✅ Domain setup with Let's Encrypt SSL
- ✅ Customer self-service panel access

## Requirements

- Paymenter 1.3+
- KeyHelp panel with API access enabled

## Installation

1. Download and extract to `extensions/Servers/Keyhelp/`
2. Go to **Admin → Extensions** and enable KeyHelp
3. Create a server with your KeyHelp URL and API key
4. Create products and select a hosting plan

## Configuration

### Server Settings
| Field | Description |
|-------|-------------|
| KeyHelp URL | Full URL (e.g., `https://panel.example.com`) |
| API Key | From KeyHelp → Settings → API |

### Product Settings
| Field | Description |
|-------|-------------|
| Hosting Plan | Select from your KeyHelp plans |
| Create System Domain | Creates a subdomain for the account |
| Send Login Email | Emails credentials via KeyHelp |

## License

MIT License

## Author

V6Direct - [https://v6direct.org](https://v6direct.org)
