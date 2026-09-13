<?php
/**
 * VHM - HTML temizleyici
 *
 * Panelden girilen zengin metni yayımlanabilir hâle getirir. Beyaz liste
 * yaklaşımı kullanılır: listede olmayan her etiket ve öznitelik atılır,
 * böylece yeni bir saldırı vektörü eklendiğinde varsayılan davranış
 * "reddet" olur.
 */

declare(strict_types=1);

final class GuvenliHtml
{
    /** İzin verilen etiketler ve o etikette izin verilen öznitelikler */
    private const ETIKETLER = [
        'p' => [],
        'br' => [],
        'hr' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'title'],
        'code' => [],
        'pre' => [],
        'blockquote' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt'],
    ];

    /** Bağlantı ve görsel adreslerinde izin verilen şemalar */
    private const SEMALAR = ['http', 'https', 'mailto'];

    /**
     * Metni temizler. Girdi boşsa boş dize döner.
     */
    public static function temizle(string $ham): string
    {
        $ham = trim($ham);
        if ($ham === '') {
            return '';
        }

        $belge = new DOMDocument('1.0', 'UTF-8');

        $onceki = libxml_use_internal_errors(true);
        /* Parçalı HTML'i gövde içinde çözümle; başlık/kodlama bilgisi
           verilmezse DOMDocument metni latin1 sanıyor. */
        $belge->loadHTML(
            '<?xml encoding="UTF-8"><div id="kok">' . $ham . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        $kok = $belge->getElementById('kok');
        if ($kok === null) {
            return '';
        }

        self::dugumuGez($kok);

        $cikti = '';
        foreach ($kok->childNodes as $cocuk) {
            $cikti .= $belge->saveHTML($cocuk);
        }

        return trim($cikti);
    }

    /** Ağacı dolaşır; izinsiz düğümleri kaldırır, özniteliklerini süzer */
    private static function dugumuGez(DOMNode $dugum): void
    {
        /* Sondan başa gidilir: çocuk silindiğinde dizin kaymasın */
        for ($i = $dugum->childNodes->length - 1; $i >= 0; $i--) {
            $cocuk = $dugum->childNodes->item($i);
            if ($cocuk === null) {
                continue;
            }

            /* Yorum, işlem talimatı ve CDATA atılır */
            if ($cocuk->nodeType === XML_COMMENT_NODE || $cocuk->nodeType === XML_PI_NODE) {
                $dugum->removeChild($cocuk);
                continue;
            }

            if ($cocuk->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$cocuk instanceof DOMElement) {
                $dugum->removeChild($cocuk);
                continue;
            }

            $ad = strtolower($cocuk->nodeName);

            if (!array_key_exists($ad, self::ETIKETLER)) {
                /* script ve style'ın içeriği de gitmeli; diğerlerinde
                   metin korunup etiket kaldırılır. */
                if ($ad === 'script' || $ad === 'style' || $ad === 'iframe' || $ad === 'object' || $ad === 'embed') {
                    $dugum->removeChild($cocuk);
                    continue;
                }

                self::dugumuGez($cocuk);
                while ($cocuk->firstChild !== null) {
                    $dugum->insertBefore($cocuk->firstChild, $cocuk);
                }
                $dugum->removeChild($cocuk);
                continue;
            }

            self::ozniteligiSuz($cocuk, self::ETIKETLER[$ad]);
            self::dugumuGez($cocuk);
        }
    }

    /** Etikette izin verilmeyen öznitelikleri kaldırır */
    private static function ozniteligiSuz(DOMElement $ogem, array $izinli): void
    {
        for ($i = $ogem->attributes->length - 1; $i >= 0; $i--) {
            $oznitelik = $ogem->attributes->item($i);
            if ($oznitelik === null) {
                continue;
            }

            $ad = strtolower($oznitelik->nodeName);

            if (!in_array($ad, $izinli, true)) {
                $ogem->removeAttribute($oznitelik->nodeName);
                continue;
            }

            if (($ad === 'href' || $ad === 'src') && !self::adresGuvenli($oznitelik->nodeValue ?? '')) {
                $ogem->removeAttribute($oznitelik->nodeName);
            }
        }

        /* Dış bağlantılar yeni sekmede ve referans sızdırmadan açılır */
        if (strtolower($ogem->nodeName) === 'a' && $ogem->hasAttribute('href')) {
            $adres = $ogem->getAttribute('href');
            if (preg_match('#^https?://#i', $adres)) {
                $ogem->setAttribute('target', '_blank');
                $ogem->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /** javascript:, data: gibi şemaları eler */
    private static function adresGuvenli(string $adres): bool
    {
        $adres = trim($adres);
        if ($adres === '') {
            return false;
        }

        /* Göreli adres ve çapa serbest */
        if (str_starts_with($adres, '/') || str_starts_with($adres, '#')) {
            return true;
        }

        /* Şema yoksa göreli sayılır */
        if (!preg_match('#^([a-z][a-z0-9+.\-]*):#i', $adres, $eslesme)) {
            return true;
        }

        return in_array(strtolower($eslesme[1]), self::SEMALAR, true);
    }

    /** Etiketleri atıp düz metin çıkarır; özet üretmek için kullanılır */
    public static function ozet(string $html, int $uzunluk = 180): string
    {
        $metin = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $metin = preg_replace('/\s+/u', ' ', $metin) ?? $metin;

        if (mb_strlen($metin) <= $uzunluk) {
            return $metin;
        }

        $kisa = mb_substr($metin, 0, $uzunluk);
        $bosluk = mb_strrpos($kisa, ' ');

        return rtrim($bosluk !== false ? mb_substr($kisa, 0, $bosluk) : $kisa, ' ,.;:') . '…';
    }
}
