<?php
/**
 * WHMVM GitHub Auto-Updater
 * GitHub Releases üzerinden güncelleme kontrolü ve kurulumu yapar.
 */

class GithubUpdater
{
    private $repo;
    private $token;
    private $currentVersion;
    private $updateFile;
    private $extractPath;

    public function __construct(string $repo, string $currentVersion, ?string $token = null)
    {
        $this->repo = $repo;
        $this->currentVersion = ltrim($currentVersion, 'v'); // 'v1.0.0' -> '1.0.0'
        $this->token = $token;
        $this->updateFile = dirname(__DIR__) . '/tmp/update.zip'; // Temp klasörü
        $this->extractPath = dirname(__DIR__); // Kök dizin

        // Temp klasörünü oluştur
        if (!file_exists(dirname($this->updateFile))) {
            mkdir(dirname($this->updateFile), 0755, true);
        }
    }

    /**
     * Yeni sürüm kontrolü yap
     */
    public function checkForUpdate()
    {
        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMVM-Updater'); // GitHub User-Agent zorunlu kılar

        if ($this->token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: token {$this->token}",
                "Accept: application/vnd.github.v3+json"
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['error' => "GitHub API Hatası: $httpCode. Repo adı veya Token hatalı olabilir."];
        }

        $data = json_decode($response, true);

        if (!isset($data['tag_name'])) {
            return ['error' => 'Sürüm bilgisi alınamadı. Release oluşturulmamış olabilir.'];
        }

        $latestVersion = ltrim($data['tag_name'], 'v');

        // Versiyon karşılaştırma
        if (version_compare($latestVersion, $this->currentVersion, '>')) {
            return [
                'has_update' => true,
                'version' => $latestVersion,
                'current_version' => $this->currentVersion,
                'download_url' => $data['zipball_url'], // Source code zip
                // Eğer attach edilmiş binary varsa onu tercih edebilirsiniz:
                // 'download_url' => $data['assets'][0]['browser_download_url'] ?? $data['zipball_url'],
                'notes' => $data['body'],
                'published_at' => $data['published_at']
            ];
        }

        return [
            'has_update' => false,
            'current_version' => $this->currentVersion,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Güncellemeyi indir ve kur
     */
    public function install($downloadUrl)
    {
        // 1. İndir
        $fp = fopen($this->updateFile, 'w+');
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WHMVM-Updater');

        if ($this->token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: token {$this->token}",
                "Accept: application/vnd.github.v3+json"
            ]);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => "İndirme başarısız. HTTP Kod: $httpCode"];
        }

        // 2. ZIP Aç
        $zip = new ZipArchive;
        if ($zip->open($this->updateFile) === TRUE) {

            // GitHub zipball içindeki ana klasör adını bul (örn: user-repo-hash)
            $firstFile = $zip->getNameIndex(0);
            $rootFolder = substr($firstFile, 0, strpos($firstFile, '/'));

            // Dosyaları geçici bir yere aç
            $tempExtract = dirname(__DIR__) . '/tmp/extracted';
            $zip->extractTo($tempExtract);
            $zip->close();

            // 3. Dosyaları Taşı (Ana dizine kopyala, üzerine yaz)
            $sourceDir = $tempExtract . '/' . $rootFolder;
            $this->recursiveCopy($sourceDir, $this->extractPath);

            // Temizlik
            $this->deleteDir($tempExtract);
            @unlink($this->updateFile);

            return ['success' => true, 'message' => 'Güncelleme başarıyla kuruldu!'];
        } else {
            return ['success' => false, 'message' => 'ZIP dosyası açılamadı.'];
        }
    }

    private function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    // Config dosyalarını ezmemek için kontrol (Opsiyonel)
                    // if ($file !== 'config.php') { ... }
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function deleteDir($dirPath)
    {
        if (!is_dir($dirPath))
            return;
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/')
            $dirPath .= '/';
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file))
                $this->deleteDir($file);
            else
                unlink($file);
        }
        rmdir($dirPath);
    }
}
