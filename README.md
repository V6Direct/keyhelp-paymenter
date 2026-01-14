# KeyHelp Extension for Paymenter

Automatically provision and manage web hosting accounts on [KeyHelp](https://www.keyweb.de/en/keyhelp/keyhelp/) through the [Paymenter](https://paymenter.org) billing system.[1]

## Features

- ✅ Automatic account provisioning on KeyHelp when an order is activated.[1]
- ✅ Suspend/unsuspend accounts from Paymenter.[1]
- ✅ Terminate accounts directly from Paymenter.[1]
- ✅ Plan upgrades/downgrades using KeyHelp hosting plans.[1]
- ✅ Domain setup with SSL via KeyHelp (for supported plans/configurations).[1]
- ✅ Customer self‑service panel access via login link/SSO from the client area.[1]

## Requirements

- Paymenter 1.4+ (recommended: latest stable).[1]
- KeyHelp panel with API access enabled and an API key with sufficient permissions.[1]

## Installation

1. Download or clone this repository into:

   `extensions/Servers/Keyhelp/`

2. In the Paymenter admin area, go to **Configuration → Servers** and create a new server of type **KeyHelp** with your panel URL and API key.

3. (Recommended) Clear caches so Paymenter picks up the new server module and views:

   ```bash
   cd /var/www/paymenter
   php artisan view:clear
   php artisan optimize:clear
   php artisan config:clear
   ```

2. In the Paymenter admin area, go to **Configuration → Servers** (or **Extensions/Modules** depending on your version) and ensure the KeyHelp server type is available/enabled.[1]
3. Create a new server using the **KeyHelp** type, enter your KeyHelp URL and API key, and save.[1]
4. Create products and select the KeyHelp server and a hosting plan in the product’s module settings.[1]

## Configuration

### Server Settings

| Field        | Description                                                |
|-------------|------------------------------------------------------------|
| KeyHelp URL | Full URL, e.g. `https://panel.example.com`                 |
| API Key     | API token from KeyHelp settings (API section)              | [1]

### Product Settings

| Field              | Description                                                 |
|--------------------|-------------------------------------------------------------|
| Hosting Plan       | One of your KeyHelp hosting plans used for provisioning     |
| Create System Domain | Optionally create an initial/system domain for the account |
| Send Login Email   | Let KeyHelp send its own login email to the customer       | [1]

## License

MIT License.

## Author

V6Direct - [https://v6direct.org](https://v6direct.org)[1]
