<?php
/**
 * WHMVM - VMware ESXi Entegrasyonu
 * VM Power Operations: Start, Stop, Restart, Shutdown
 * 
 * Desteklenen SSH Yöntemleri:
 * 1. phpseclib (Pure PHP - Önerilen, extension gerektirmez)
 * 2. php-ssh2 extension
 */
declare(strict_types=1);

// phpseclib autoload
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class ESXi
{
    private string $host;
    private string $username;
    private string $password;
    private int $port;
    private string $connectionType;
    private $sshConnection = null;
    private $phpseclib = null; // phpseclib3\Net\SSH2 instance
    private array $lastError = [];
    private string $sshMethod = 'none'; // 'phpseclib', 'ssh2', 'none'
    
    public function __construct(string $host, string $username, string $password, int $port = 22, string $connectionType = 'ssh')
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
        $this->connectionType = $connectionType;
        
        // Kullanılabilir SSH yöntemini belirle
        $this->detectSSHMethod();
    }
    
    /**
     * Mevcut SSH yöntemini belirle
     */
    private function detectSSHMethod(): void
    {
        // Önce phpseclib kontrol et (önerilen)
        if (class_exists('\phpseclib4\Net\SSH2')) {
            $this->sshMethod = 'phpseclib';
            return;
        }
        
        // php-ssh2 extension kontrol et
        if (function_exists('ssh2_connect')) {
            $this->sshMethod = 'ssh2';
            return;
        }
        
        $this->sshMethod = 'none';
    }
    
    /**
     * SSH yöntemi bilgisi
     */
    public function getSSHMethod(): string
    {
        return $this->sshMethod;
    }
    
    /**
     * Bağlantı testi
     */
    public function testConnection(): array
    {
        if ($this->sshMethod === 'none') {
            return [
                'success' => false,
                'message' => 'SSH bağlantısı için gerekli kütüphane bulunamadı. phpseclib yüklü olmalı.',
                'details' => null
            ];
        }
        
        if ($this->connectionType === 'ssh') {
            return $this->testSSHConnection();
        } else {
            return $this->testAPIConnection();
        }
    }
    
    /**
     * SSH bağlantı testi
     */
    private function testSSHConnection(): array
    {
        try {
            if ($this->sshMethod === 'phpseclib') {
                return $this->testPhpseclibConnection();
            } else {
                return $this->testSSH2Connection();
            }
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }
    
    /**
     * phpseclib ile bağlantı testi
     */
    private function testPhpseclibConnection(): array
    {
        try {
            $ssh = new \phpseclib4\Net\SSH2($this->host, $this->port);
            $ssh->setTimeout(10);
            
            if (!$ssh->login($this->username, $this->password)) {
                return [
                    'success' => false,
                    'message' => 'Kimlik doğrulama başarısız. Kullanıcı adı/şifre kontrol edin.',
                    'details' => null
                ];
            }
            
            // ESXi versiyonunu al
            $version = trim($ssh->exec('vmware -v'));
            
            // VM sayısını al
            $vmCount = trim($ssh->exec('vim-cmd vmsvc/getallvms | tail -n +2 | wc -l'));
            
            $ssh->disconnect();
            
            return [
                'success' => true,
                'message' => 'Bağlantı başarılı!',
                'details' => [
                    'version' => $version,
                    'vm_count' => (int)$vmCount,
                    'connection_type' => 'SSH (phpseclib)',
                    'ssh_method' => 'phpseclib'
                ]
            ];
            
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }
    
    /**
     * php-ssh2 ile bağlantı testi
     */
    private function testSSH2Connection(): array
    {
        $connection = @ssh2_connect($this->host, $this->port, [], [
            'disconnect' => function($reason, $message, $language) {}
        ]);
        
        if (!$connection) {
            return [
                'success' => false,
                'message' => 'ESXi sunucusuna bağlanılamadı. IP/Port kontrol edin.',
                'details' => null
            ];
        }
        
        if (!@ssh2_auth_password($connection, $this->username, $this->password)) {
            return [
                'success' => false,
                'message' => 'Kimlik doğrulama başarısız. Kullanıcı adı/şifre kontrol edin.',
                'details' => null
            ];
        }
        
        // ESXi versiyonunu al
        $stream = ssh2_exec($connection, 'vmware -v');
        stream_set_blocking($stream, true);
        $version = trim(stream_get_contents($stream));
        fclose($stream);
        
        // VM sayısını al
        $stream = ssh2_exec($connection, 'vim-cmd vmsvc/getallvms | tail -n +2 | wc -l');
        stream_set_blocking($stream, true);
        $vmCount = trim(stream_get_contents($stream));
        fclose($stream);
        
        return [
            'success' => true,
            'message' => 'Bağlantı başarılı!',
            'details' => [
                'version' => $version,
                'vm_count' => (int)$vmCount,
                'connection_type' => 'SSH (ssh2 extension)',
                'ssh_method' => 'ssh2'
            ]
        ];
    }
    
    /**
     * API bağlantı testi (vSphere SOAP API)
     */
    private function testAPIConnection(): array
    {
        try {
            $wsdl = "https://{$this->host}/sdk/vimService.wsdl";
            
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $client = new SoapClient($wsdl, [
                'location' => "https://{$this->host}/sdk/",
                'trace' => 1,
                'exceptions' => true,
                'stream_context' => $context,
                'cache_wsdl' => WSDL_CACHE_NONE
            ]);
            
            $serviceContent = $client->RetrieveServiceContent([
                '_this' => ['_' => 'ServiceInstance', 'type' => 'ServiceInstance']
            ]);
            
            $sessionManager = $serviceContent->returnval->sessionManager;
            
            $client->Login([
                '_this' => $sessionManager,
                'userName' => $this->username,
                'password' => $this->password
            ]);
            
            $aboutInfo = $serviceContent->returnval->about;
            
            $client->Logout(['_this' => $sessionManager]);
            
            return [
                'success' => true,
                'message' => 'API bağlantısı başarılı!',
                'details' => [
                    'version' => $aboutInfo->version ?? 'Bilinmiyor',
                    'build' => $aboutInfo->build ?? '',
                    'connection_type' => 'vSphere API'
                ]
            ];
            
        } catch (SoapFault $e) {
            return [
                'success' => false,
                'message' => 'API Hatası: ' . $e->getMessage(),
                'details' => null
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Bağlantı hatası: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }
    
    /**
     * SSH bağlantısı kur
     */
    private function connectSSH(): bool
    {
        if ($this->sshMethod === 'none') {
            $this->lastError = ['code' => 'NO_SSH', 'message' => 'SSH kütüphanesi bulunamadı'];
            return false;
        }
        
        if ($this->sshMethod === 'phpseclib') {
            return $this->connectPhpseclib();
        } else {
            return $this->connectSSH2();
        }
    }
    
    /**
     * phpseclib ile bağlan
     */
    private function connectPhpseclib(): bool
    {
        if ($this->phpseclib !== null) {
            return true;
        }
        
        try {
            $this->phpseclib = new \phpseclib4\Net\SSH2($this->host, $this->port);
            $this->phpseclib->setTimeout(30);
            
            if (!$this->phpseclib->login($this->username, $this->password)) {
                $this->lastError = ['code' => 'AUTH_FAILED', 'message' => 'Kimlik doğrulama başarısız'];
                $this->phpseclib = null;
                return false;
            }
            
            return true;
        } catch (Throwable $e) {
            $this->lastError = ['code' => 'EXCEPTION', 'message' => $e->getMessage()];
            $this->phpseclib = null;
            return false;
        }
    }
    
    /**
     * ssh2 extension ile bağlan
     */
    private function connectSSH2(): bool
    {
        if ($this->sshConnection) {
            return true;
        }
        
        try {
            $this->sshConnection = @ssh2_connect($this->host, $this->port);
            
            if (!$this->sshConnection) {
                $this->lastError = ['code' => 'CONNECTION_FAILED', 'message' => 'Sunucuya bağlanılamadı'];
                return false;
            }
            
            if (!@ssh2_auth_password($this->sshConnection, $this->username, $this->password)) {
                $this->lastError = ['code' => 'AUTH_FAILED', 'message' => 'Kimlik doğrulama başarısız'];
                return false;
            }
            
            return true;
        } catch (Throwable $e) {
            $this->lastError = ['code' => 'EXCEPTION', 'message' => $e->getMessage()];
            return false;
        }
    }
    
    /**
     * SSH komut çalıştır
     */
    private function executeSSH(string $command): ?string
    {
        if (!$this->connectSSH()) {
            return null;
        }
        
        try {
            if ($this->sshMethod === 'phpseclib') {
                $output = $this->phpseclib->exec($command);
                return $output !== false ? $output : null;
            } else {
                $stream = ssh2_exec($this->sshConnection, $command);
                
                if (!$stream) {
                    $this->lastError = ['code' => 'EXEC_FAILED', 'message' => 'Komut çalıştırılamadı'];
                    return null;
                }
                
                stream_set_blocking($stream, true);
                $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
                stream_set_blocking($errorStream, true);
                
                $output = stream_get_contents($stream);
                $error = stream_get_contents($errorStream);
                
                fclose($stream);
                fclose($errorStream);
                
                if (!empty($error)) {
                    $this->lastError = ['code' => 'COMMAND_ERROR', 'message' => $error];
                }
                
                return $output;
            }
        } catch (Throwable $e) {
            $this->lastError = ['code' => 'EXCEPTION', 'message' => $e->getMessage()];
            return null;
        }
    }
    
    /**
     * Tüm VM'leri listele
     */
    public function listVMs(): array
    {
        $output = $this->executeSSH('vim-cmd vmsvc/getallvms');
        
        if ($output === null) {
            return [];
        }
        
        $lines = explode("\n", trim($output));
        $vms = [];
        
        array_shift($lines);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            preg_match('/^(\d+)\s+(\S+)\s+\[([^\]]+)\]\s+(\S+)\s+(\S+)\s*(.*)/', $line, $matches);
            
            if (count($matches) >= 5) {
                $vmid = $matches[1];
                $name = $matches[2];
                
                $state = $this->getVMPowerState($vmid);
                
                $vms[] = [
                    'vmid' => $vmid,
                    'name' => $name,
                    'datastore' => $matches[3] ?? '',
                    'vmx_path' => $matches[4] ?? '',
                    'guest_os' => $matches[5] ?? '',
                    'state' => $state
                ];
            }
        }
        
        return $vms;
    }
    
    /**
     * VM power state al
     */
    public function getVMPowerState(string $vmid): string
    {
        $output = $this->executeSSH("vim-cmd vmsvc/power.getstate {$vmid}");
        
        if ($output === null) {
            return 'unknown';
        }
        
        if (strpos($output, 'Powered on') !== false) {
            return 'running';
        } elseif (strpos($output, 'Powered off') !== false) {
            return 'stopped';
        } elseif (strpos($output, 'Suspended') !== false) {
            return 'suspended';
        }
        
        return 'unknown';
    }
    
    /**
     * VM bilgilerini al
     */
    public function getVMInfo(string $vmid): ?array
    {
        $output = $this->executeSSH("vim-cmd vmsvc/get.summary {$vmid}");
        
        if ($output === null) {
            return null;
        }
        
        $info = [
            'vmid' => $vmid,
            'name' => '',
            'state' => $this->getVMPowerState($vmid),
            'cpu' => 0,
            'memory_mb' => 0,
            'guest_os' => '',
            'ip_address' => '',
            'vmware_tools' => 'not_installed',
            'uptime' => 0
        ];
        
        if (preg_match('/name = "([^"]+)"/', $output, $m)) {
            $info['name'] = $m[1];
        }
        if (preg_match('/numCpu = (\d+)/', $output, $m)) {
            $info['cpu'] = (int)$m[1];
        }
        if (preg_match('/memorySizeMB = (\d+)/', $output, $m)) {
            $info['memory_mb'] = (int)$m[1];
        }
        if (preg_match('/guestFullName = "([^"]+)"/', $output, $m)) {
            $info['guest_os'] = $m[1];
        }
        if (preg_match('/ipAddress = "([^"]+)"/', $output, $m)) {
            $info['ip_address'] = $m[1];
        }
        if (preg_match('/toolsStatus = "([^"]+)"/', $output, $m)) {
            $info['vmware_tools'] = $m[1];
        }
        if (preg_match('/uptimeSeconds = (\d+)/', $output, $m)) {
            $info['uptime'] = (int)$m[1];
        }
        
        return $info;
    }
    
    /**
     * VM'i başlat (Power On)
     */
    public function powerOn(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState === 'running') {
            return [
                'success' => false,
                'message' => 'VM zaten çalışıyor.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.on {$vmid}");
        
        if ($output === null) {
            return [
                'success' => false,
                'message' => $this->lastError['message'] ?? 'Bilinmeyen hata',
                'state' => $currentState
            ];
        }
        
        sleep(2);
        $newState = $this->getVMPowerState($vmid);
        
        return [
            'success' => $newState === 'running',
            'message' => $newState === 'running' ? 'VM başarıyla başlatıldı.' : 'VM başlatılamadı.',
            'state' => $newState
        ];
    }
    
    /**
     * VM'i kapat (Power Off - Zorla)
     */
    public function powerOff(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState === 'stopped') {
            return [
                'success' => false,
                'message' => 'VM zaten kapalı.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.off {$vmid}");
        
        if ($output === null) {
            return [
                'success' => false,
                'message' => $this->lastError['message'] ?? 'Bilinmeyen hata',
                'state' => $currentState
            ];
        }
        
        sleep(2);
        $newState = $this->getVMPowerState($vmid);
        
        return [
            'success' => $newState === 'stopped',
            'message' => $newState === 'stopped' ? 'VM kapatıldı.' : 'VM kapatılamadı.',
            'state' => $newState
        ];
    }
    
    /**
     * VM'i düzgün kapat (Graceful Shutdown - VMware Tools gerekli)
     */
    public function shutdown(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState === 'stopped') {
            return [
                'success' => false,
                'message' => 'VM zaten kapalı.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.shutdown {$vmid}");
        
        if ($output === null || strpos($output, 'not support') !== false) {
            return $this->powerOff($vmid);
        }
        
        for ($i = 0; $i < 15; $i++) {
            sleep(2);
            $newState = $this->getVMPowerState($vmid);
            if ($newState === 'stopped') {
                return [
                    'success' => true,
                    'message' => 'VM düzgün şekilde kapatıldı.',
                    'state' => $newState
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'VM kapatma zaman aşımına uğradı.',
            'state' => $this->getVMPowerState($vmid)
        ];
    }
    
    /**
     * VM'i yeniden başlat (Reset - Zorla)
     */
    public function reset(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState !== 'running') {
            return [
                'success' => false,
                'message' => 'VM çalışmıyor, yeniden başlatılamaz.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.reset {$vmid}");
        
        if ($output === null) {
            return [
                'success' => false,
                'message' => $this->lastError['message'] ?? 'Bilinmeyen hata',
                'state' => $currentState
            ];
        }
        
        sleep(3);
        $newState = $this->getVMPowerState($vmid);
        
        return [
            'success' => true,
            'message' => 'VM yeniden başlatıldı.',
            'state' => $newState
        ];
    }
    
    /**
     * VM'i düzgün yeniden başlat (Reboot - VMware Tools gerekli)
     */
    public function reboot(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState !== 'running') {
            return [
                'success' => false,
                'message' => 'VM çalışmıyor, yeniden başlatılamaz.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.reboot {$vmid}");
        
        if ($output === null || strpos($output, 'not support') !== false) {
            return $this->reset($vmid);
        }
        
        sleep(5);
        $newState = $this->getVMPowerState($vmid);
        
        return [
            'success' => true,
            'message' => 'VM düzgün şekilde yeniden başlatılıyor.',
            'state' => $newState
        ];
    }
    
    /**
     * VM'i askıya al (Suspend)
     */
    public function suspend(string $vmid): array
    {
        $currentState = $this->getVMPowerState($vmid);
        
        if ($currentState !== 'running') {
            return [
                'success' => false,
                'message' => 'VM çalışmıyor, askıya alınamaz.',
                'state' => $currentState
            ];
        }
        
        $output = $this->executeSSH("vim-cmd vmsvc/power.suspend {$vmid}");
        
        if ($output === null) {
            return [
                'success' => false,
                'message' => $this->lastError['message'] ?? 'Bilinmeyen hata',
                'state' => $currentState
            ];
        }
        
        sleep(3);
        $newState = $this->getVMPowerState($vmid);
        
        return [
            'success' => $newState === 'suspended',
            'message' => $newState === 'suspended' ? 'VM askıya alındı.' : 'VM askıya alınamadı.',
            'state' => $newState
        ];
    }
    
    /**
     * Son hatayı al
     */
    public function getLastError(): array
    {
        return $this->lastError;
    }
    
    /**
     * Bağlantıyı kapat
     */
    public function disconnect(): void
    {
        if ($this->phpseclib !== null) {
            $this->phpseclib->disconnect();
            $this->phpseclib = null;
        }
        $this->sshConnection = null;
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
