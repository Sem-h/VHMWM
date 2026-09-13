<?php

class GithubUpdater
{
    // Veritabanından ayarları çek
    public function getSettings()
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('github_repo', 'github_token')");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'repo' => $result['github_repo'] ?? '',
            'token' => $result['github_token'] ?? ''
        ];
    }

    // Ayarları kaydet
    public function saveSettings($repo, $token)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute(['github_repo', $repo]);
        $stmt->execute(['github_token', $token]);
    }

    // Güncellemeleri kontrol et
    public function checkForUpdates($currentVersion)
    {
        $settings = $this->getSettings();
        if (empty($settings['repo'])) {
            return ['success' => false, 'error' => 'Repo ayarlanmamış.'];
        }

        $url = "https://api.github.com/repos/{$settings['repo']}/releases/latest";
        $headers = [
            'User-Agent: WHMVM-Updater',
            'Accept: application/vnd.github.v3+json'
        ];

        if (!empty($settings['token'])) {
            $headers[] = "Authorization: token {$settings['token']}";
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // SSL Verify false (hostinglerde sorun çıkmaması için)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL Hatası: $error"];
        }

        if ($httpCode === 401) {
            return ['success' => false, 'error' => 'GitHub API Hatası: 401 (Yetkisiz). Token hatalı veya repo gizli.'];
        }

        if ($httpCode === 404) {
            return ['success' => false, 'error' => 'GitHub API Hatası: 404. Repo bulunamadı.'];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "GitHub API Hatası: $httpCode"];
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['tag_name'])) {
            return ['success' => false, 'error' => 'Geçersiz API yanıtı.'];
        }

        $latestVersion = ltrim($data['tag_name'], 'v'); // v1.0.1 -> 1.0.1
        $current = ltrim($currentVersion, 'v');

        return [
            'success' => true,
            'updateAvailable' => version_compare($latestVersion, $current, '>'),
            'latestVersion' => $latestVersion,
            'release' => $data
        ];
    }

    // Güncellemeyi indir ve kur
    public function performUpdate($version)
    {
        $check = $this->checkForUpdates('0.0.0'); // Sadece son release'i çekmek için
        if (!$check['success']) {
            return $check;
        }

        $downloadUrl = $check['release']['zipball_url'] ?? null;
        if (!$downloadUrl) {
            return ['success' => false, 'error' => 'İndirme URL\'i bulunamadı.'];
        }

        // 1. Dosyayı İndir
        $tempZip = sys_get_temp_dir() . '/whmvm_update.zip';
        $settings = $this->getSettings();

        $fp = fopen($tempZip, 'w+');
        $ch = curl_init($downloadUrl);

        $headers = ['User-Agent: WHMVM-Updater'];
        if (!empty($settings['token'])) {
            $headers[] = "Authorization: token {$settings['token']}";
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);

        if (curl_errno($ch)) {
            fclose($fp);
            return ['success' => false, 'error' => 'İndirme başarısız: ' . curl_error($ch)];
        }
        curl_close($ch);
        fclose($fp);

        // 2. ZIP'i Aç ve Dosyaları Üzerine Yaz
        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            $extractPath = dirname(dirname(__DIR__)); // Ana dizin (admin/includes/ -> root)

            // GitHub zipballs usually have a wrapper folder like user-repo-hash/
            // We need to extract strip contents
            // Basit çözüm: Hepsini çıkarıp sonra taşıyalım mı?
            // Veya doğrudan üzerine yazalım. GitHub zipball içinde klasör oluyor.

            // Geçici klasöre çıkar
            $tempExtract = sys_get_temp_dir() . '/whmvm_extract_' . time();
            mkdir($tempExtract);
            $zip->extractTo($tempExtract);
            $zip->close();

            // İçindeki tek klasörü bul
            $files = scandir($tempExtract);
            $innerFolder = null;
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..' && is_dir("$tempExtract/$f")) {
                    $innerFolder = "$tempExtract/$f";
                    break;
                }
            }

            if ($innerFolder) {
                // Dosyaları kopyala (Recursive)
                $this->recursiveCopy($innerFolder, $extractPath);

                // Temizlik
                $this->deleteDir($tempExtract);
                unlink($tempZip);

                // Config dosyasının üzerine yazılmamasını sağla
                // Biz exclude etmiştik ama yine de dikkat.

                // Versiyon numarasını güncelle
                $this->updateConfigVersion($version);

                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'ZIP yapısı geçersiz.'];
            }

        } else {
            return ['success' => false, 'error' => 'ZIP dosyası açılamadı.'];
        }
    }

    private function recursiveCopy($src, $dst)
    {
        $dir = opendir($src);
        if (!is_dir($dst))
            mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                if (is_dir("$src/$file")) {
                    $this->recursiveCopy("$src/$file", "$dst/$file");
                } else {
                    // Config.php'yi koru
                    if ($file !== 'config.php' && $file !== '.htaccess') {
                        copy("$src/$file", "$dst/$file");
                    }
                }
            }
        }
        closedir($dir);
    }

    private function deleteDir($dirPath)
    {
        if (!is_dir($dirPath))
            return;
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    private function updateConfigVersion($newVersion)
    {
        $configFile = dirname(dirname(__DIR__)) . '/config/config.php';
        if (!file_exists($configFile))
            return;

        $content = file_get_contents($configFile);
        // define('APP_VERSION', 'x.x.x'); satırını bul ve değiştir
        $pattern = "/define\s*\(\s*['\"]APP_VERSION['\"]\s*,\s*['\"][^'\"]+['\"]\s*\);/";
        $replacement = "define('APP_VERSION', '$newVersion');";

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $replacement, $content);
            file_put_contents($configFile, $newContent);
        }
    }
}
