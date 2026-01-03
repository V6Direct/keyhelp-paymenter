<?php

namespace Paymenter\Extensions\Servers\Keyhelp;

use App\Classes\Extension\Server;
use App\Models\Service;
use App\Rules\Domain;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * KeyHelp Server Extension for Paymenter
 * 
 * Integrates KeyHelp web hosting panel with Paymenter billing system.
 * Supports automatic account creation, suspension, and termination.
 * 
 * @author V6Direct
 * @version 1.0.0
 * @link https://github.com/V6Direct/keyhelp-paymenter
 */
class Keyhelp extends Server
{
    /**
     * Make an API request to KeyHelp
     */
    private function request(string $endpoint, string $method = 'get', array $data = [])
    {
        $host = rtrim($this->config('host'), '/');
        $apiKey = $this->config('api_key');

        if (empty($host) || empty($apiKey)) {
            throw new Exception('KeyHelp server not properly configured');
        }

        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->withoutVerifying()->{$method}($host . '/api/v2' . $endpoint, $data);

        if ($response->failed()) {
            $error = $response->json()['message'] ?? $response->body();
            throw new Exception('KeyHelp API Error: ' . $error);
        }

        return $response;
    }

    /**
     * Server configuration fields
     */
    public function getConfig($values = []): array
    {
        return [
            [
                'name' => 'host',
                'label' => 'KeyHelp URL',
                'type' => 'text',
                'required' => true,
                'description' => 'Full URL to your KeyHelp panel (e.g., https://panel.example.com)',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'password',
                'required' => true,
                'description' => 'API key from KeyHelp (Settings → API)',
            ],
        ];
    }

    /**
     * Fetch available hosting plans from KeyHelp
     */
    private function getHostingPlans(): array
    {
        try {
            $plans = $this->request('/hosting-plans')->json();
            $options = [];

            foreach ($plans as $plan) {
                $disk = $plan['resources']['disk_space'] ?? 0;
                $diskLabel = ($disk == -1) ? 'Unlimited' : $this->formatBytes($disk);
                $options[$plan['id']] = $plan['name'] . ' (' . $diskLabel . ')';
            }

            return $options;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }

    /**
     * Product configuration fields
     */
    public function getProductConfig($values = []): array
    {
        return [
            [
                'name' => 'id_hosting_plan',
                'label' => 'Hosting Plan',
                'type' => 'select',
                'required' => true,
                'description' => 'Select a hosting plan from KeyHelp',
                'options' => $this->getHostingPlans(),
            ],
            [
                'name' => 'create_system_domain',
                'label' => 'Create System Domain',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Create a system subdomain for the account',
            ],
            [
                'name' => 'send_login_credentials',
                'label' => 'Send Login Email',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Email login credentials to the customer via KeyHelp',
            ],
        ];
    }

    /**
     * Checkout configuration fields (customer-facing)
     */
    public function getCheckoutConfig($values = []): array
    {
        return [
            [
                'name' => 'domain',
                'type' => 'text',
                'label' => 'Domain',
                'required' => true,
                'validation' => [new Domain, 'required'],
                'placeholder' => 'example.com',
                'description' => 'Your domain name for this hosting account',
            ],
        ];
    }

    /**
     * Test the server connection
     */
    public function testConfig(): bool|string
    {
        try {
            $this->request('/server');
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Create a new hosting account
     */
    public function createServer(Service $service, $settings, $properties)
    {
        $username = $this->generateUsername($service);
        $password = Str::password(16);
        $domain = $properties['domain'] ?? null;

        $response = $this->request('/clients', 'post', [
            'username' => $username,
            'password' => $password,
            'email' => $service->user->email,
            'language' => 'en',
            'id_hosting_plan' => (int) $settings['id_hosting_plan'],
            'is_suspended' => false,
            'create_system_domain' => (bool) ($settings['create_system_domain'] ?? true),
            'send_login_credentials' => (bool) ($settings['send_login_credentials'] ?? true),
        ])->json();

        if (!isset($response['id'])) {
            throw new Exception('Failed to create KeyHelp account');
        }

        $clientId = $response['id'];

        // Store account details
        $service->properties()->updateOrCreate(
            ['key' => 'keyhelp_client_id'],
            ['name' => 'KeyHelp Client ID', 'value' => $clientId]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'username'],
            ['name' => 'Username', 'value' => $username]
        );
        $service->properties()->updateOrCreate(
            ['key' => 'password'],
            ['name' => 'Password', 'value' => $password]
        );

        // Add domain if provided
        if ($domain) {
            try {
                $this->request('/domains', 'post', [
                    'id_user' => $clientId,
                    'domain' => $domain,
                    'target_type' => 'webspace',
                    'ssl_enabled' => true,
                    'letsencrypt' => true,
                ]);

                $service->properties()->updateOrCreate(
                    ['key' => 'domain'],
                    ['name' => 'Domain', 'value' => $domain]
                );
            } catch (Exception $e) {
                // Domain creation failed, but account exists
            }
        }

        return true;
    }

    /**
     * Suspend a hosting account
     */
    public function suspendServer(Service $service, $settings, $properties)
    {
        $clientId = $properties['keyhelp_client_id'] ?? null;

        if (!$clientId) {
            throw new Exception('Service has not been provisioned');
        }

        $this->request('/clients/' . $clientId, 'put', [
            'is_suspended' => true,
        ]);

        return true;
    }

    /**
     * Unsuspend a hosting account
     */
    public function unsuspendServer(Service $service, $settings, $properties)
    {
        $clientId = $properties['keyhelp_client_id'] ?? null;

        if (!$clientId) {
            throw new Exception('Service has not been provisioned');
        }

        $this->request('/clients/' . $clientId, 'put', [
            'is_suspended' => false,
        ]);

        return true;
    }

    /**
     * Terminate/delete a hosting account
     */
    public function terminateServer(Service $service, $settings, $properties)
    {
        $clientId = $properties['keyhelp_client_id'] ?? null;

        if (!$clientId) {
            throw new Exception('Service has not been provisioned');
        }

        $this->request('/clients/' . $clientId, 'delete');

        return true;
    }

    /**
     * Upgrade/downgrade a hosting account
     */
    public function upgradeServer(Service $service, $settings, $properties)
    {
        $clientId = $properties['keyhelp_client_id'] ?? null;

        if (!$clientId) {
            throw new Exception('Service has not been provisioned');
        }

        $this->request('/clients/' . $clientId, 'put', [
            'id_hosting_plan' => (int) $settings['id_hosting_plan'],
        ]);

        return true;
    }

    /**
     * Get service actions displayed to customer
     */
    public function getActions(Service $service, $settings, $properties)
    {
        return [
            [
                'name' => 'username',
                'label' => 'Username',
                'type' => 'text',
                'text' => $properties['username'] ?? 'N/A',
            ],
            [
                'name' => 'password',
                'label' => 'Password',
                'type' => 'text',
                'text' => $properties['password'] ?? 'N/A',
            ],
            [
                'name' => 'domain',
                'label' => 'Domain',
                'type' => 'text',
                'text' => $properties['domain'] ?? 'N/A',
            ],
            [
                'name' => 'login',
                'label' => 'Login to KeyHelp',
                'type' => 'button',
                'text' => 'Open Panel',
                'url' => $this->config('host'),
            ],
        ];
    }

    /**
     * Generate a unique username
     */
    private function generateUsername(Service $service): string
    {
        $base = strtolower(explode('@', $service->user->email)[0]);
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        $base = substr($base, 0, 8);

        return $base . $service->id;
    }
}
