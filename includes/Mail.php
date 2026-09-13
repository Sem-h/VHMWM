<?php
/**
 * WHMVM - SMTP Mail Sınıfı
 * PHPMailer benzeri SMTP mail gönderme sistemi
 */

declare(strict_types=1);

// Settings ve Database sınıflarını yükle (eğer yüklenmemişse)
if (!class_exists('Settings')) {
    require_once __DIR__ . '/Settings.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/Database.php';
}

class Mail
{
    // SMTP ayarları
    private string $smtpHost = '';
    private int $smtpPort = 587;
    private string $smtpUser = '';
    private string $smtpPass = '';
    private string $smtpEncryption = 'tls'; // tls, ssl, none
    private bool $smtpAuth = true;
    
    // Mail bilgileri
    private string $fromEmail = '';
    private string $fromName = '';
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private string $subject = '';
    private string $body = '';
    private string $altBody = '';
    private bool $isHtml = true;
    private array $attachments = [];
    private array $headers = [];
    
    // Hata mesajı
    private string $errorMessage = '';
    
    // Debug modu
    private bool $debug = false;
    private array $debugLog = [];

    /**
     * Constructor - Ayarları veritabanından yükle
     */
    public function __construct(bool $loadFromDb = true)
    {
        if ($loadFromDb) {
            $this->loadSettings();
        }
    }

    /**
     * Veritabanından SMTP ayarlarını yükle
     */
    public function loadSettings(): void
    {
        $this->smtpHost = Settings::get('smtp_host', '');
        $this->smtpPort = (int)Settings::get('smtp_port', 587);
        $this->smtpUser = Settings::get('smtp_username', '');
        $this->smtpPass = Settings::get('smtp_password', '');
        $this->smtpEncryption = Settings::get('smtp_encryption', 'tls');
        $this->smtpAuth = Settings::get('smtp_auth', '1') === '1';
        $this->fromEmail = Settings::get('smtp_from_email', Settings::get('company_email', ''));
        $this->fromName = Settings::get('smtp_from_name', Settings::get('site_name', 'WHMVM'));
    }

    /**
     * SMTP ayarlarını manuel ayarla
     */
    public function setSmtp(string $host, int $port = 587, string $user = '', string $pass = '', string $encryption = 'tls'): self
    {
        $this->smtpHost = $host;
        $this->smtpPort = $port;
        $this->smtpUser = $user;
        $this->smtpPass = $pass;
        $this->smtpEncryption = $encryption;
        $this->smtpAuth = !empty($user);
        return $this;
    }

    /**
     * Gönderen bilgisi
     */
    public function setFrom(string $email, string $name = ''): self
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
        return $this;
    }

    /**
     * Alıcı ekle
     */
    public function addTo(string $email, string $name = ''): self
    {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * CC ekle
     */
    public function addCc(string $email, string $name = ''): self
    {
        $this->cc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * BCC ekle
     */
    public function addBcc(string $email, string $name = ''): self
    {
        $this->bcc[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Yanıt adresi
     */
    public function addReplyTo(string $email, string $name = ''): self
    {
        $this->replyTo[] = ['email' => $email, 'name' => $name];
        return $this;
    }

    /**
     * Konu
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * HTML içerik
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Düz metin alternatif
     */
    public function setAltBody(string $altBody): self
    {
        $this->altBody = $altBody;
        return $this;
    }

    /**
     * HTML modu
     */
    public function isHtml(bool $isHtml = true): self
    {
        $this->isHtml = $isHtml;
        return $this;
    }

    /**
     * Dosya ekle
     */
    public function addAttachment(string $path, string $name = ''): self
    {
        if (file_exists($path)) {
            $this->attachments[] = [
                'path' => $path,
                'name' => $name ?: basename($path),
                'type' => mime_content_type($path) ?: 'application/octet-stream'
            ];
        }
        return $this;
    }

    /**
     * Özel header ekle
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Debug modu
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Mail gönder (internal)
     */
    private function doSend(): bool
    {
        // Validasyon
        if (empty($this->to)) {
            $this->errorMessage = 'Alıcı adresi belirtilmedi.';
            return false;
        }

        if (empty($this->fromEmail)) {
            $this->errorMessage = 'Gönderen adresi belirtilmedi.';
            return false;
        }

        if (empty($this->subject)) {
            $this->errorMessage = 'Mail konusu belirtilmedi.';
            return false;
        }

        // SMTP host kontrolü
        if (empty($this->smtpHost)) {
            // SMTP ayarlanmamışsa PHP mail() kullan
            return $this->sendWithPhpMail();
        }

        // SMTP ile gönder
        return $this->sendWithSmtp();
    }
    

    /**
     * PHP mail() fonksiyonu ile gönder
     */
    private function sendWithPhpMail(): bool
    {
        $headers = $this->buildHeaders();
        $body = $this->buildBody();
        
        $toAddresses = [];
        foreach ($this->to as $recipient) {
            $toAddresses[] = $this->formatAddress($recipient['email'], $recipient['name']);
        }

        $result = @mail(implode(', ', $toAddresses), $this->subject, $body, $headers);
        
        if (!$result) {
            $this->errorMessage = 'PHP mail() fonksiyonu ile gönderim başarısız.';
            return false;
        }

        $this->logMail(true);
        return true;
    }

    /**
     * SMTP ile gönder
     */
    private function sendWithSmtp(): bool
    {
        try {
            // SMTP bağlantısı
            $socket = $this->connectSmtp();
            if (!$socket) {
                return false;
            }

            // EHLO/HELO
            $this->smtpCommand($socket, "EHLO " . gethostname());

            // TLS başlat
            if ($this->smtpEncryption === 'tls') {
                $this->smtpCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCommand($socket, "EHLO " . gethostname());
            }

            // Kimlik doğrulama
            if ($this->smtpAuth && !empty($this->smtpUser)) {
                $this->smtpCommand($socket, "AUTH LOGIN");
                $this->smtpCommand($socket, base64_encode($this->smtpUser));
                $response = $this->smtpCommand($socket, base64_encode($this->smtpPass));
                
                if (strpos($response, '235') === false && strpos($response, '250') === false) {
                    $this->errorMessage = 'SMTP kimlik doğrulama hatası: ' . $response;
                    fclose($socket);
                    return false;
                }
            }

            // Mail FROM
            $this->smtpCommand($socket, "MAIL FROM:<{$this->fromEmail}>");

            // RCPT TO
            foreach ($this->to as $recipient) {
                $this->smtpCommand($socket, "RCPT TO:<{$recipient['email']}>");
            }
            foreach ($this->cc as $recipient) {
                $this->smtpCommand($socket, "RCPT TO:<{$recipient['email']}>");
            }
            foreach ($this->bcc as $recipient) {
                $this->smtpCommand($socket, "RCPT TO:<{$recipient['email']}>");
            }

            // DATA
            $this->smtpCommand($socket, "DATA");

            // Mail içeriği
            $message = $this->buildFullMessage();
            fwrite($socket, $message);
            $response = $this->smtpCommand($socket, "\r\n.");

            // QUIT
            $this->smtpCommand($socket, "QUIT");
            fclose($socket);

            if (strpos($response, '250') !== false) {
                $this->logMail(true);
                return true;
            }

            $this->errorMessage = 'SMTP gönderim hatası: ' . $response;
            $this->logMail(false);
            return false;

        } catch (Exception $e) {
            $this->errorMessage = 'SMTP hatası: ' . $e->getMessage();
            $this->logMail(false);
            return false;
        }
    }

    /**
     * SMTP bağlantısı oluştur
     */
    private function connectSmtp(): mixed
    {
        $host = $this->smtpHost;
        
        // SSL için prefix
        if ($this->smtpEncryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client(
            "$host:{$this->smtpPort}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->errorMessage = "SMTP bağlantı hatası: $errstr ($errno)";
            return false;
        }

        // Sunucu karşılama
        $response = fgets($socket, 515);
        $this->debugLog[] = "S: $response";

        if (strpos($response, '220') === false) {
            $this->errorMessage = 'SMTP sunucu yanıt hatası: ' . $response;
            fclose($socket);
            return false;
        }

        return $socket;
    }

    /**
     * SMTP komut gönder
     */
    private function smtpCommand($socket, string $command): string
    {
        $this->debugLog[] = "C: $command";
        fwrite($socket, $command . "\r\n");
        
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        
        $this->debugLog[] = "S: $response";
        return $response;
    }

    /**
     * Mail headerlarını oluştur
     */
    private function buildHeaders(): string
    {
        $headers = [];
        
        // From
        $headers[] = "From: " . $this->formatAddress($this->fromEmail, $this->fromName);
        
        // Reply-To
        if (!empty($this->replyTo)) {
            $replyTo = [];
            foreach ($this->replyTo as $r) {
                $replyTo[] = $this->formatAddress($r['email'], $r['name']);
            }
            $headers[] = "Reply-To: " . implode(', ', $replyTo);
        }
        
        // CC
        if (!empty($this->cc)) {
            $ccAddresses = [];
            foreach ($this->cc as $c) {
                $ccAddresses[] = $this->formatAddress($c['email'], $c['name']);
            }
            $headers[] = "Cc: " . implode(', ', $ccAddresses);
        }
        
        // MIME
        $headers[] = "MIME-Version: 1.0";
        
        // Content-Type
        if (!empty($this->attachments)) {
            $boundary = md5(uniqid(time() . '', true));
            $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
        } elseif ($this->isHtml) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }
        
        // Özel headerlar
        foreach ($this->headers as $name => $value) {
            $headers[] = "$name: $value";
        }
        
        // X-Mailer
        $headers[] = "X-Mailer: WHMVM Mail System";
        
        return implode("\r\n", $headers);
    }

    /**
     * Mail body oluştur
     */
    private function buildBody(): string
    {
        if (empty($this->attachments)) {
            return $this->body;
        }

        $boundary = md5(uniqid(time() . '', true));
        $body = "";

        // HTML içerik
        $body .= "--$boundary\r\n";
        if ($this->isHtml) {
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $this->body . "\r\n\r\n";

        // Ekler
        foreach ($this->attachments as $attachment) {
            $content = chunk_split(base64_encode(file_get_contents($attachment['path'])));
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: {$attachment['type']}; name=\"{$attachment['name']}\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $content . "\r\n";
        }

        $body .= "--$boundary--";
        return $body;
    }

    /**
     * Tam mesaj oluştur (SMTP için)
     */
    private function buildFullMessage(): string
    {
        $message = "";
        
        // To
        $toAddresses = [];
        foreach ($this->to as $recipient) {
            $toAddresses[] = $this->formatAddress($recipient['email'], $recipient['name']);
        }
        $message .= "To: " . implode(', ', $toAddresses) . "\r\n";
        
        // From
        $message .= "From: " . $this->formatAddress($this->fromEmail, $this->fromName) . "\r\n";
        
        // Subject
        $message .= "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=\r\n";
        
        // Date
        $message .= "Date: " . date('r') . "\r\n";
        
        // Message-ID
        $message .= "Message-ID: <" . md5(uniqid(time() . '', true)) . "@" . gethostname() . ">\r\n";
        
        // CC
        if (!empty($this->cc)) {
            $ccAddresses = [];
            foreach ($this->cc as $c) {
                $ccAddresses[] = $this->formatAddress($c['email'], $c['name']);
            }
            $message .= "Cc: " . implode(', ', $ccAddresses) . "\r\n";
        }
        
        // Reply-To
        if (!empty($this->replyTo)) {
            $replyTo = [];
            foreach ($this->replyTo as $r) {
                $replyTo[] = $this->formatAddress($r['email'], $r['name']);
            }
            $message .= "Reply-To: " . implode(', ', $replyTo) . "\r\n";
        }
        
        // MIME
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "X-Mailer: WHMVM Mail System\r\n";
        
        // Özel headerlar
        foreach ($this->headers as $name => $value) {
            $message .= "$name: $value\r\n";
        }
        
        // Content type ve body
        if (!empty($this->attachments)) {
            $boundary = md5(uniqid(time() . '', true));
            $message .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
            $message .= $this->buildBody();
        } elseif ($this->isHtml) {
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->body;
        } else {
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $this->body;
        }
        
        return $message;
    }

    /**
     * Adres formatla
     */
    private function formatAddress(string $email, string $name = ''): string
    {
        if (empty($name)) {
            return $email;
        }
        return "=?UTF-8?B?" . base64_encode($name) . "?= <$email>";
    }

    /**
     * Mail logla
     */
    private function logMail(bool $success): void
    {
        try {
            $toEmails = array_column($this->to, 'email');
            
            Database::insert('email_logs', [
                'to_email' => implode(', ', $toEmails),
                'from_email' => $this->fromEmail,
                'subject' => $this->subject,
                'body' => $this->body,
                'status' => $success ? 'sent' : 'failed',
                'error_message' => $success ? null : $this->errorMessage,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            // Log hatası sessizce geç
        }
    }

    /**
     * Hata mesajı al
     */
    public function getError(): string
    {
        return $this->errorMessage;
    }

    /**
     * Debug logları al
     */
    public function getDebugLog(): array
    {
        return $this->debugLog;
    }

    /**
     * Tüm alanları temizle (yeni mail için)
     */
    public function clear(): self
    {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->replyTo = [];
        $this->subject = '';
        $this->body = '';
        $this->altBody = '';
        $this->attachments = [];
        $this->headers = [];
        $this->errorMessage = '';
        $this->debugLog = [];
        return $this;
    }

    // ==========================================
    // STATIC HELPER FONKSİYONLARI
    // ==========================================

    /**
     * Hızlı mail gönderme (static)
     */
    public static function send(string $toEmail, string $subject, string $body, string $toName = '', bool $isHtml = true): bool
    {
        $mail = new self();
        $mail->addTo($toEmail, $toName);
        $mail->setSubject($subject);
        $mail->setBody($body);
        $mail->isHtml($isHtml);
        return $mail->doSend();
    }

    // ==========================================
    // E-POSTA ŞABLONU FONKSİYONLARI
    // ==========================================

    /**
     * Şablon ile mail gönder
     */
    public static function sendTemplate(string $templateName, string $toEmail, array $variables = [], string $toName = ''): bool
    {
        // Şablonu getir
        $template = self::getTemplate($templateName);
        if (!$template) {
            return false;
        }

        // Şablon aktif değilse gönderme
        if ($template['status'] !== 'active') {
            return false;
        }

        // Global değişkenleri ekle
        $variables = array_merge(self::getGlobalVariables(), $variables);

        // Değişkenleri değiştir
        $subject = self::replaceVariables($template['subject'], $variables);
        $body = self::replaceVariables($template['body'], $variables);

        // HTML wrapper ekle
        $body = self::wrapHtmlTemplate($body);

        // Mail gönder
        $mail = new self();
        $mail->addTo($toEmail, $toName)
             ->setSubject($subject)
             ->setBody($body)
             ->isHtml(true);

        return $mail->doSend();
    }

    /**
     * Şablon getir
     */
    public static function getTemplate(string $name): ?array
    {
        try {
            return Database::fetch(
                "SELECT * FROM email_templates WHERE name = ?",
                [$name]
            );
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Tüm şablonları getir
     */
    public static function getAllTemplates(): array
    {
        try {
            return Database::fetchAll("SELECT * FROM email_templates ORDER BY category, name");
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Şablon kaydet/güncelle
     */
    public static function saveTemplate(int $id, array $data): bool
    {
        try {
            Database::update('email_templates', [
                'subject' => $data['subject'],
                'body' => $data['body'],
                'status' => $data['status'] ?? 'active',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Değişkenleri değiştir
     */
    public static function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', (string)$value, $content);
        }
        return $content;
    }

    /**
     * Global değişkenler
     */
    public static function getGlobalVariables(): array
    {
        return [
            'site_name' => Settings::get('site_name', 'WHMVM'),
            'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'company_name' => Settings::get('company_name', ''),
            'company_email' => Settings::get('company_email', ''),
            'company_phone' => Settings::get('company_phone', ''),
            'company_address' => Settings::get('company_address', ''),
            'current_date' => date('d.m.Y'),
            'current_time' => date('H:i'),
            'current_year' => date('Y')
        ];
    }

    /**
     * HTML template wrapper
     */
    public static function wrapHtmlTemplate(string $content): string
    {
        $siteName = Settings::get('site_name', 'WHMVM');
        $primaryColor = Settings::get('site_primary_color', '#6366f1');
        $logo = Settings::getLogo();
        $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $currentYear = date('Y');
        $companyEmail = Settings::get('company_email', '');
        $companyPhone = Settings::get('company_phone', '');
        
        $logoHtml = '';
        if (!empty($logo)) {
            $logoHtml = '<img src="' . $siteUrl . '/' . $logo . '" alt="' . $siteName . '" style="max-width: 420px; max-height: 120px; width: auto; height: auto;">';
        } else {
            $logoHtml = '<span style="font-size: 32px; font-weight: 700; color: #1a1a2e; letter-spacing: -1px;">' . $siteName . '</span>';
        }
        
        $contactHtml = '';
        if (!empty($companyEmail) || !empty($companyPhone)) {
            $contactParts = [];
            if (!empty($companyEmail)) {
                $contactParts[] = '<a href="mailto:' . $companyEmail . '" style="color: #6b7280; text-decoration: none;">' . $companyEmail . '</a>';
            }
            if (!empty($companyPhone)) {
                $contactParts[] = '<span style="color: #6b7280;">' . $companyPhone . '</span>';
            }
            $contactHtml = implode(' &nbsp;•&nbsp; ', $contactParts);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$siteName}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f0f4f8; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    
    <!-- Preheader (görünmez ama önizlemede görünür) -->
    <div style="display: none; max-height: 0; overflow: hidden;">
        {$siteName} - Bilgilendirme
    </div>
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f0f4f8;">
        <tr>
            <td style="padding: 30px 15px;">
                
                <!-- Ana Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 620px; margin: 0 auto;">
                    
                    <!-- Logo Header -->
                    <tr>
                        <td style="padding: 25px 0; text-align: center;">
                            <a href="{$siteUrl}" style="text-decoration: none;">
                                {$logoHtml}
                            </a>
                        </td>
                    </tr>
                    
                    <!-- Main Card -->
                    <tr>
                        <td>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);">
                                
                                <!-- Accent Bar -->
                                <tr>
                                    <td style="height: 4px; background: linear-gradient(90deg, {$primaryColor} 0%, #a855f7 50%, #ec4899 100%);"></td>
                                </tr>
                                
                                <!-- Content Area -->
                                <tr>
                                    <td style="padding: 45px 40px 40px 40px;">
                                        {$content}
                                    </td>
                                </tr>
                                
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 20px; text-align: center;">
                            
                            <!-- İletişim Bilgileri -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 20px; text-align: center;">
                                        {$contactHtml}
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Çizgi -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 0 50px 20px 50px;">
                                        <div style="height: 1px; background: linear-gradient(90deg, transparent 0%, #e5e7eb 50%, transparent 100%);"></div>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Copyright -->
                            <p style="margin: 0 0 8px 0; color: #9ca3af; font-size: 13px; line-height: 1.5;">
                                Bu e-posta <strong style="color: #6b7280;">{$siteName}</strong> tarafından gönderilmiştir.
                            </p>
                            <p style="margin: 0; color: #d1d5db; font-size: 12px;">
                                © {$currentYear} {$siteName}. Tüm hakları saklıdır.
                            </p>
                            
                            <!-- Unsubscribe Link (opsiyonel) -->
                            <p style="margin: 20px 0 0 0;">
                                <a href="{$siteUrl}/client/" style="display: inline-block; padding: 8px 20px; background: #f3f4f6; color: #6b7280; text-decoration: none; font-size: 12px; border-radius: 20px; transition: all 0.2s;">
                                    Müşteri Paneline Git →
                                </a>
                            </p>
                            
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
HTML;
    }

    /**
     * Test mail gönder
     */
    public static function sendTestMail(string $toEmail): array
    {
        try {
            $smtpHost = Settings::get('smtp_host', 'PHP Mail');
            $smtpPort = Settings::get('smtp_port', 'N/A');
            $smtpEnc = strtoupper(Settings::get('smtp_encryption', 'none'));
            
            $mail = new self();
            $mail->setDebug(true);
            $mail->addTo($toEmail)
                 ->setSubject('WHMVM Test E-postası')
                 ->setBody(self::wrapHtmlTemplate('
                    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Test E-postası Başarılı!</h2>
                    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
                        Bu e-posta, SMTP ayarlarınızın doğru yapılandırıldığını doğrulamak için gönderilmiştir.
                    </p>
                    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
                        <strong>Sunucu:</strong> ' . htmlspecialchars($smtpHost) . '<br>
                        <strong>Port:</strong> ' . htmlspecialchars($smtpPort) . '<br>
                        <strong>Şifreleme:</strong> ' . htmlspecialchars($smtpEnc) . '
                    </p>
                    <p style="color: #64748b; font-size: 14px; margin: 0;">
                        Gönderim zamanı: ' . date('d.m.Y H:i:s') . '
                    </p>
                 '))
                 ->isHtml(true);

            $result = $mail->doSend();

            return [
                'success' => $result,
                'error' => $mail->getError(),
                'debug' => $mail->getDebugLog()
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'Mail gönderim hatası: ' . $e->getMessage(),
                'debug' => []
            ];
        }
    }
}

