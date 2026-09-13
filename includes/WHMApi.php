<?php
/**
 * WHMVM - WHM/cPanel API Sınıfı
 * Sunucu üzerindeki gerçek hesap bilgilerini çeker
 */
declare(strict_types=1);

class WHMApi {
    private string $host;
    private string $username;
    private string $apiToken;
    private int $port;
    private bool $ssl;
    
    public function __construct(array $server) {
        $this->host = $server['ip_address'] ?? $server['hostname'];
        $this->username = $server['username'] ?? 'root';
        $this->apiToken = $server['api_token'] ?? $server['password'] ?? '';
        $this->port = (int)($server['port'] ?? 2087);
        $this->ssl = ($server['secure'] ?? true) ? true : false;
    }
    
    /**
     * WHM API'ye istek gönder
     */
    private function request(string $function, array $params = []): ?array {
        $protocol = $this->ssl ? 'https' : 'http';
        $url = "{$protocol}://{$this->host}:{$this->port}/json-api/{$function}";
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: whm {$this->username}:{$this->apiToken}"
            ]
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Sunucu bağlantısını test et
     */
    public function testConnection(): array {
        $result = $this->request('version');
        
        if ($result && isset($result['version'])) {
            return [
                'success' => true,
                'message' => 'Bağlantı başarılı',
                'version' => $result['version']
            ];
        }
        
        return [
            'success' => false,
            'message' => 'API bağlantısı kurulamadı'
        ];
    }
    
    /**
     * Sunucudaki tüm hesapları listele
     */
    public function listAccounts(): ?array {
        $result = $this->request('listaccts');
        
        if ($result && isset($result['acct'])) {
            return $result['acct'];
        }
        
        return null;
    }
    
    /**
     * Toplam hesap sayısını al
     */
    public function getAccountCount(): int {
        $accounts = $this->listAccounts();
        return $accounts ? count($accounts) : 0;
    }
    
    /**
     * Sunucu istatistiklerini al
     */
    public function getServerStats(): ?array {
        $stats = [];
        
        // Hesap sayısı
        $accounts = $this->listAccounts();
        $stats['accounts'] = $accounts ? count($accounts) : 0;
        
        // Sunucu yükü
        $loadAvg = $this->request('loadavg');
        if ($loadAvg) {
            $stats['load'] = $loadAvg;
        }
        
        // Disk kullanımı
        $diskUsage = $this->request('getdiskusage');
        if ($diskUsage) {
            $stats['disk'] = $diskUsage;
        }
        
        return $stats;
    }
    
    /**
     * Belirli bir hesabın bilgilerini al
     */
    public function getAccountInfo(string $username): ?array {
        $result = $this->request('accountsummary', ['user' => $username]);
        
        if ($result && isset($result['acct'][0])) {
            return $result['acct'][0];
        }
        
        return null;
    }
    
    /**
     * Yeni hesap oluştur
     */
    public function createAccount(array $data): array {
        $params = [
            'username' => $data['username'],
            'domain' => $data['domain'],
            'password' => $data['password'] ?? $this->generatePassword(),
            'plan' => $data['plan'] ?? 'default',
            'contactemail' => $data['email'] ?? ''
        ];
        
        $result = $this->request('createacct', $params);
        
        if ($result && isset($result['result'][0]['status']) && $result['result'][0]['status'] == 1) {
            return [
                'success' => true,
                'message' => 'Hesap başarıyla oluşturuldu',
                'data' => $result['result'][0]
            ];
        }
        
        return [
            'success' => false,
            'message' => $result['result'][0]['statusmsg'] ?? 'Hesap oluşturulamadı'
        ];
    }
    
    /**
     * Hesap askıya al
     */
    public function suspendAccount(string $username, string $reason = ''): array {
        $result = $this->request('suspendacct', [
            'user' => $username,
            'reason' => $reason
        ]);
        
        if ($result && isset($result['result'][0]['status']) && $result['result'][0]['status'] == 1) {
            return ['success' => true, 'message' => 'Hesap askıya alındı'];
        }
        
        return ['success' => false, 'message' => 'Hesap askıya alınamadı'];
    }
    
    /**
     * Hesap askıdan çıkar
     */
    public function unsuspendAccount(string $username): array {
        $result = $this->request('unsuspendacct', ['user' => $username]);
        
        if ($result && isset($result['result'][0]['status']) && $result['result'][0]['status'] == 1) {
            return ['success' => true, 'message' => 'Hesap aktif edildi'];
        }
        
        return ['success' => false, 'message' => 'Hesap aktif edilemedi'];
    }
    
    /**
     * Hesap sil
     */
    public function terminateAccount(string $username): array {
        $result = $this->request('removeacct', [
            'user' => $username,
            'keepdns' => 0
        ]);
        
        if ($result && isset($result['result'][0]['status']) && $result['result'][0]['status'] == 1) {
            return ['success' => true, 'message' => 'Hesap silindi'];
        }
        
        return ['success' => false, 'message' => 'Hesap silinemedi'];
    }
    
    /**
     * Rastgele şifre oluştur
     */
    private function generatePassword(int $length = 16): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }
}

/**
 * Plesk API Sınıfı
 */
class PleskApi {
    private string $host;
    private string $username;
    private string $password;
    private int $port;
    
    public function __construct(array $server) {
        $this->host = $server['ip_address'] ?? $server['hostname'];
        $this->username = $server['username'] ?? 'admin';
        $this->password = $server['password'] ?? '';
        $this->port = (int)($server['port'] ?? 8443);
    }
    
    private function request(string $xml): ?array {
        $url = "https://{$this->host}:{$this->port}/enterprise/control/agent.php";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml',
                'HTTP_AUTH_LOGIN: ' . $this->username,
                'HTTP_AUTH_PASSWD: ' . $this->password
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            return $this->parseXml($response);
        }
        
        return null;
    }
    
    private function parseXml(string $xml): array {
        $result = simplexml_load_string($xml);
        return json_decode(json_encode($result), true);
    }
    
    public function testConnection(): array {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <packet version="1.6.3.0">
                <server><get><gen_info/></get></server>
            </packet>';
        
        $result = $this->request($xml);
        
        if ($result && isset($result['server']['get']['result']['status']) && $result['server']['get']['result']['status'] === 'ok') {
            return ['success' => true, 'message' => 'Bağlantı başarılı'];
        }
        
        return ['success' => false, 'message' => 'Bağlantı kurulamadı'];
    }
    
    public function getAccountCount(): int {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <packet version="1.6.3.0">
                <webspace><get><filter/><dataset><gen_info/></dataset></get></webspace>
            </packet>';
        
        $result = $this->request($xml);
        
        if ($result && isset($result['webspace']['get']['result'])) {
            $accounts = $result['webspace']['get']['result'];
            return is_array($accounts) ? count($accounts) : 0;
        }
        
        return 0;
    }
}

/**
 * DirectAdmin API Sınıfı
 */
class DirectAdminApi {
    private string $host;
    private string $username;
    private string $password;
    private int $port;
    
    public function __construct(array $server) {
        $this->host = $server['ip_address'] ?? $server['hostname'];
        $this->username = $server['username'] ?? 'admin';
        $this->password = $server['password'] ?? '';
        $this->port = (int)($server['port'] ?? 2222);
    }
    
    private function request(string $cmd, array $params = []): ?array {
        $url = "https://{$this->host}:{$this->port}/{$cmd}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => !empty($params),
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}"
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            parse_str($response, $result);
            return $result;
        }
        
        return null;
    }
    
    public function testConnection(): array {
        $result = $this->request('CMD_API_SHOW_ADMINS');
        
        if ($result && !isset($result['error'])) {
            return ['success' => true, 'message' => 'Bağlantı başarılı'];
        }
        
        return ['success' => false, 'message' => 'Bağlantı kurulamadı'];
    }
    
    public function getAccountCount(): int {
        $result = $this->request('CMD_API_SHOW_ALL_USERS');
        
        if ($result && isset($result['list'])) {
            $users = explode('&', $result['list']);
            return count($users);
        }
        
        return 0;
    }
}

/**
 * Server API Factory
 */
class ServerApi {
    public static function create(array $server): ?object {
        $module = strtolower($server['module'] ?? 'cpanel');
        
        return match ($module) {
            'cpanel', 'whm' => new WHMApi($server),
            'plesk' => new PleskApi($server),
            'directadmin' => new DirectAdminApi($server),
            default => null
        };
    }
    
    /**
     * Sunucudan gerçek hesap sayısını al
     */
    public static function getAccountCount(array $server): int {
        $api = self::create($server);
        
        if ($api && method_exists($api, 'getAccountCount')) {
            return $api->getAccountCount();
        }
        
        return 0;
    }
    
    /**
     * Sunucu bağlantısını test et
     */
    public static function testConnection(array $server): array {
        $api = self::create($server);
        
        if (!$api) {
            return ['success' => false, 'message' => 'Desteklenmeyen modül'];
        }
        
        if (method_exists($api, 'testConnection')) {
            return $api->testConnection();
        }
        
        return ['success' => false, 'message' => 'Test fonksiyonu bulunamadı'];
    }
}

