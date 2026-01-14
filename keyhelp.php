<?php

namespace Paymenter\Extensions\Servers\Keyhelp;

use App\Classes\Extension\Server;
use App\Models\Service;
use App\Rules\Domain;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

class Keyhelp extends Server
{
    private function request($endpoint, $method = 'get', $data = [])
    {
        $host = rtrim($this->config('host'), '/');
        $apiKey = $this->config('api_key');

        if (empty($host) || empty($apiKey)) {
            throw new Exception('KeyHelp server not properly configured');
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->withoutVerifying()->$method($host . '/api/v1' . $endpoint, $data)->throw();

            return $response;
        } catch (Exception $e) {
            throw new Exception('KeyHelp API Error: ' . $e->getMessage());
        }
    }

public function clientResetPassword(Service $service, $settings, $properties)
{
    if (empty($properties['keyhelp_client_id'])) {
        throw new Exception('Service has not been created');
    }

    $clientId    = (int) $properties['keyhelp_client_id'];
    $newPassword = Str::password(16);

    // Update password in KeyHelp
    $this->request('/clients/' . $clientId, 'put', [
        'password' => $newPassword,
    ]);

    // Store new password in Paymenter
    $service->properties()->updateOrCreate(
        ['key' => 'password'],
        ['name' => 'Password', 'value' => $newPassword]
    );

    session()->flash('keyhelp_password_reset', $newPassword);

    return true;
}


private function getLoginUrl(int $clientId): ?string
{
    try {
        // KeyHelp expects GET on /login/{id}
        $response = $this->request('/login/' . $clientId, 'get');

        // $response is a Laravel HTTP Response
        $data = $response->json();

        return $data['url'] ?? null;
    } catch (\Throwable $e) {
        \Log::warning('KeyHelp SSO login failed for client ' . $clientId . ': ' . $e->getMessage());
        return null;
    }
}



    public function boot()
{
    View::addNamespace('keyhelp', __DIR__ . '/resources/views');
}


public function getConfig($values = []): array
{
    return [
        [
            'name'        => 'host',
            'label'       => 'KeyHelp Hostname',
            'type'        => 'text',
            'required'    => true,
            'description' => 'The hostname of your KeyHelp server (e.g., https://panel.example.com)',
        ],
        [
            'name'        => 'api_key',
            'label'       => 'API Key',
            'type'        => 'password',
            'required'    => true,
            'description' => 'Your KeyHelp API key',
        ],

        
        [
            'name'        => 'system_url',
            'label'       => 'System URL (display)',
            'type'        => 'text',
            'required'    => false,
            'description' => 'Display URL for this server (e.g. vweb03.eu)',
        ],
        [
            'name'        => 'ipv4_address',
            'label'       => 'IPv4 Address',
            'type'        => 'text',
            'required'    => false,
            'description' => 'Public IPv4 of this server',
        ],
        [
            'name'        => 'ipv6_address',
            'label'       => 'IPv6 Address',
            'type'        => 'text',
            'required'    => false,
            'description' => 'Public IPv6 of this server',
        ],
        [
            'name'        => 'server_location',
            'label'       => 'Server Location',
            'type'        => 'text',
            'required'    => false,
            'description' => 'Location (e.g. SkyLink, Eygelshoven)',
        ],
        [
            'name'        => 'ddos_protection',
            'label'       => 'DDoS Protection',
            'type'        => 'text',
            'required'    => false,
            'description' => 'DDoS protection description (e.g. inkl DDoS Protection)',
        ],
    ];
}


    private function getHostingPlans(): array
    {
        try {
            $response = $this->request('/hosting-plans', 'get');
            $plans = $response->json();

            $options = [];
            foreach ($plans as $plan) {
                $diskSpace = $plan['resources']['disk_space'] ?? 0;
                $diskDisplay = ($diskSpace == -1) ? 'Unlimited' : $this->formatBytes($diskSpace);
                $options[$plan['id']] = $plan['name'] . ' (' . $diskDisplay . ')';
            }

            return $options;
        } catch (Exception $e) {
            return [];
        }
    }

private function formatDiskUsage(?array $diskSpace): string
{
    if (!$diskSpace || !isset($diskSpace['value'])) {
        return 'N/A';
    }

    $used = (int) $diskSpace['value'];
    $max  = isset($diskSpace['max']) ? (int) $diskSpace['max'] : -1;

    $usedFormatted = $this->formatBytes($used);

    // -1 or 0 means unlimited
    if ($max <= 0) {
        return $usedFormatted . ' / Unlimited';
    }

    $maxFormatted = $this->formatBytes($max);
    $percent      = $max > 0 ? round(($used / $max) * 100, 1) : 0;

    return $usedFormatted . ' / ' . $maxFormatted . ' (' . $percent . '%)';
}

private function formatBytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }

    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }

    return round($bytes / 1024, 2) . ' KB';
}


    public function getProductConfig($values = []): array
    {
        $plans = $this->getHostingPlans();

        return [
            [
                'name' => 'id_hosting_plan',
                'label' => 'Hosting Plan',
                'type' => 'select',
                'required' => true,
                'description' => 'Select a hosting plan from KeyHelp',
                'options' => $plans,
            ],
            [
                'name' => 'send_login_credentials',
                'label' => 'Send Login Credentials',
                'type' => 'checkbox',
                'default' => true,
                'description' => 'Email login credentials to the user',
            ],
        ];
    }

    private function getClientStats(int $clientId): array
{
    try {
        $response = $this->request('/clients/' . $clientId . '/stats', 'get');
        $stats = $response->json();

        return is_array($stats) ? $stats : [];
    } catch (Exception $e) {
        \Log::warning('Failed to fetch KeyHelp stats: ' . $e->getMessage());

        return [];
    }
}

    public function getCheckoutConfig(): array
    {
        return [
            [
                'name' => 'domain',
                'type' => 'text',
                'label' => 'Domain',
                'required' => true,
                'validation' => [new Domain, 'required'],
                'placeholder' => 'domain.com',
            ],
        ];
    }

    public function testConfig(): bool|string
    {
        try {
            $this->request('/server', 'get');
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function createServer(Service $service, $settings, $properties)
    {
        $username = $this->generateUsername($service);
        $password = Str::password(16);
        $email = $service->user->email;
        $domain = $properties['domain'] ?? null;

        $data = [
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'language' => 'en',
            'id_hosting_plan' => (int) $settings['id_hosting_plan'],
            'is_suspended' => false,
            'send_login_credentials' => (bool) ($settings['send_login_credentials'] ?? true),
        ];

        $response = $this->request('/clients', 'post', $data)->json();

        if (!isset($response['id'])) {
            throw new Exception('Failed to create KeyHelp account: No client ID returned');
        }

        $clientId = $response['id'];

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
                \Log::warning('Failed to add domain to KeyHelp: ' . $e->getMessage());
            }
        }

        return [
            'username' => $username,
            'password' => $password,
            'domain' => $domain,
        ];
    }

    public function suspendServer(Service $service, $settings, $properties)
    {
        if (!isset($properties['keyhelp_client_id'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('/clients/' . $properties['keyhelp_client_id'], 'put', [
            'is_suspended' => true,
        ]);

        return true;
    }

    public function unsuspendServer(Service $service, $settings, $properties)
    {
        if (!isset($properties['keyhelp_client_id'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('/clients/' . $properties['keyhelp_client_id'], 'put', [
            'is_suspended' => false,
        ]);

        return true;
    }

    public function terminateServer(Service $service, $settings, $properties)
    {
        if (!isset($properties['keyhelp_client_id'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('/clients/' . $properties['keyhelp_client_id'], 'delete');

        $service->properties()->where('key', 'keyhelp_client_id')->delete();

        return true;
    }

    public function upgradeServer(Service $service, $settings, $properties)
    {
        if (!isset($properties['keyhelp_client_id'])) {
            throw new Exception('Service has not been created');
        }

        $this->request('/clients/' . $properties['keyhelp_client_id'], 'put', [
            'id_hosting_plan' => (int) $settings['id_hosting_plan'],
        ]);

        return true;
    }

public function getActions(Service $service, $settings, $properties)
{
    $diskUsageText = 'N/A';

    if (!empty($properties['keyhelp_client_id'])) {
        $stats = $this->getClientStats((int) $properties['keyhelp_client_id']);
        if (isset($stats['disk_space']) && is_array($stats['disk_space'])) {
            $diskUsageText = $this->formatDiskUsage($stats['disk_space']);
        }
    }

    $loginUrl = $this->config('host');
    if (!empty($properties['keyhelp_client_id'])) {
        $loginUrl = $this->getLoginUrl((int) $properties['keyhelp_client_id']) ?: $loginUrl;
    }

    return [
        // Login Data tab
        [
            'name'     => 'login_data',
            'label'    => 'Login Data',
            'type'     => 'view',
            'function' => 'getLoginView',
        ],

        // System info
        [
            'name'  => 'domain',
            'label' => 'Domain',
            'type'  => 'text',
            'text'  => $properties['domain'] ?? 'N/A',
        ],
        [
            'name'  => 'system_url',
            'label' => 'URL',
            'type'  => 'text',
            'text'  => $this->config('system_url') ?: 'N/A',
        ],
        [
            'name'  => 'ipv4_address',
            'label' => 'IPv4 Address',
            'type'  => 'text',
            'text'  => $this->config('ipv4_address') ?: 'N/A',
        ],
        [
            'name'  => 'ipv6_address',
            'label' => 'IPv6 Address',
            'type'  => 'text',
            'text'  => $this->config('ipv6_address') ?: 'N/A',
        ],
        [
            'name'  => 'server_location',
            'label' => 'Server Location',
            'type'  => 'text',
            'text'  => $this->config('server_location') ?: 'N/A',
        ],
        [
            'name'  => 'ddos_protection',
            'label' => 'DDoS Protection',
            'type'  => 'text',
            'text'  => $this->config('ddos_protection') ?: 'N/A',
        ],
        [
            'name'  => 'disk_usage',
            'label' => 'Disk Usage',
            'type'  => 'text',
            'text'  => $diskUsageText,
        ],

        // Login button
        [
            'name'  => 'login_panel',
            'label' => 'Login to KeyHelp',
            'type'  => 'button',
            'text'  => 'Login to KeyHelp',
            'url'   => $loginUrl,
        ],

        // Password reset tab (view)
        [
            'name'     => 'client_reset_password',
            'label'    => 'Password Reset',
            'type'     => 'view',
            'function' => 'getClientResetView',
        ],
        [
            'name'     => 'client_reset_password_action',
            'label'    => 'Password Reset Action',
            'type'     => 'button',
            'text'     => 'Hidden',
            'function' => 'clientResetPassword',
        ],

    ];
}




public function getLoginView(Service $service, $settings, $properties, $view = null)
{
    return view('keyhelp::login', [
        'service'    => $service,
        'properties' => $properties,
    ]);
}

public function getClientResetView(Service $service, $settings, $properties, $view = null)
{
    return view('keyhelp::reset', [
        'service'    => $service,
        'properties' => $properties,
    ]);
}


    protected function generateUsername(Service $service): string
    {
        $base = strtolower(explode('@', $service->user->email)[0]);
        $base = preg_replace('/[^a-z0-9]/', '', $base);
        $base = substr($base, 0, 8);

        return $base . substr($service->id, -4);
    }


}
