<?php
/**
 * VHM - Yönetim paneli menü ağacı
 *
 * Menünün tek kaynağı burasıdır. Eskiden 39 bağlantı doğrudan header.php
 * içinde yazılıydı; gruplama işleve göre değil, sayfalar eklendikçe
 * oluşmuştu. Altyapı sayfaları "Ürün İşlemleri" altında, içerik ve
 * politika sayfaları "Destek" altında duruyordu.
 *
 * Her bölüm katlanabilir; yalnızca açık sayfanın bölümü açılır. Böylece
 * kenar çubuğu 39 satır yerine bölüm başlıkları + tek açık bölüm kadar yer
 * kaplar.
 *
 * Dizi biçimi:
 *   'anahtar' => [
 *      'ad'      => bölüm başlığı
 *      'ikon'    => Font Awesome sınıfı
 *      'ogeler'  => [ [dosya, etiket, ikon, (rozet sorgusu)] ... ]
 *   ]
 */

declare(strict_types=1);

/**
 * @return array<string, array{ad: string, ikon: string, ogeler: array<int, array{0:string,1:string,2:string,3?:string}>}>
 */
function yonetimMenusu(): array
{
    return [
        'genel' => [
            'ad' => 'Genel',
            'ikon' => 'fa-gauge',
            'ogeler' => [
                ['dashboard.php', 'Kontrol paneli', 'fa-gauge-high'],
            ],
        ],

        'satis' => [
            'ad' => 'Satış',
            'ikon' => 'fa-cart-shopping',
            'ogeler' => [
                ['clients.php', 'Müşteriler', 'fa-users'],
                ['orders.php', 'Siparişler', 'fa-cart-shopping', 'siparis'],
                ['invoices.php', 'Faturalar', 'fa-file-invoice', 'fatura'],
                ['transactions.php', 'Ödemeler', 'fa-credit-card'],
                ['proposals.php', 'Teklifler', 'fa-file-signature'],
                ['affiliates.php', 'Satış ortakları', 'fa-handshake'],
                ['affiliate-withdrawals.php', 'Çekim talepleri', 'fa-money-bill-transfer'],
            ],
        ],

        'katalog' => [
            'ad' => 'Katalog',
            'ikon' => 'fa-boxes-stacked',
            'ogeler' => [
                ['products.php', 'Ürünler', 'fa-box'],
                ['product-groups.php', 'Ürün grupları', 'fa-folder-tree'],
                ['product-types.php', 'Ürün türleri', 'fa-tags'],
                ['config-options.php', 'Yapılandırma seçenekleri', 'fa-sliders'],
                ['vds-pricing.php', 'VDS fiyatlandırma', 'fa-calculator'],
                ['domain-fiyatlari.php', 'Alan adı fiyatları', 'fa-tag'],
            ],
        ],

        'altyapi' => [
            'ad' => 'Altyapı',
            'ikon' => 'fa-server',
            'ogeler' => [
                ['services.php', 'Aktif hizmetler', 'fa-cubes'],
                ['servers.php', 'Sunucular', 'fa-server'],
                ['esxi-servers.php', 'ESXi sunucuları', 'fa-hard-drive'],
                ['esxi-vms.php', 'Sanal makineler', 'fa-display'],
                ['domains.php', 'Alan adları', 'fa-globe'],
                ['hizmet-bolgeleri.php', 'Hizmet bölgeleri', 'fa-map-location-dot'],
            ],
        ],

        'talepler' => [
            'ad' => 'Talepler',
            'ikon' => 'fa-inbox',
            'ogeler' => [
                ['tickets.php', 'Destek talepleri', 'fa-life-ring', 'destek'],
                ['cancellations.php', 'İptal talepleri', 'fa-ban', 'iptal'],
                ['kesif-talepleri.php', 'Keşif talepleri', 'fa-location-dot', 'kesif'],
                ['iletisim-mesajlari.php', 'İletişim mesajları', 'fa-envelope', 'iletisim'],
                ['marka-talepleri.php', 'Marka tescil talepleri', 'fa-trademark', 'marka'],
            ],
        ],

        'icerik' => [
            'ad' => 'İçerik',
            'ikon' => 'fa-book-open',
            'ogeler' => [
                ['bilgi-bankasi.php', 'Bilgi bankası', 'fa-book'],
                ['references.php', 'Referanslar', 'fa-building-columns'],
                ['sla.php', 'SLA ve iade kuralları', 'fa-file-contract'],
                ['seo-manager.php', 'SEO yönetimi', 'fa-magnifying-glass-chart'],
                ['menus.php', 'Site menüsü', 'fa-bars'],
                ['languages.php', 'Diller ve çeviriler', 'fa-language'],
            ],
        ],

        'sistem' => [
            'ad' => 'Sistem',
            'ikon' => 'fa-gear',
            'ogeler' => [
                ['settings.php', 'Ayarlar', 'fa-gear'],
                ['bank-accounts.php', 'Banka hesapları', 'fa-building-columns'],
                ['paytr.php', 'PayTR ödeme', 'fa-credit-card'],
                ['integrations.php', 'Entegrasyonlar', 'fa-plug'],
                ['modules.php', 'Modüller', 'fa-puzzle-piece'],
                ['email-templates.php', 'E-posta şablonları', 'fa-envelope-open-text'],
                ['admins.php', 'Yöneticiler', 'fa-user-shield'],
                ['sistem-kayitlari.php', 'Sistem kayıtları', 'fa-clipboard-list'],
                ['updates.php', 'Güncelleme', 'fa-rotate'],
                ['whmcs-import.php', 'WHMCS içe aktarma', 'fa-file-import'],
            ],
        ],
    ];
}

/**
 * Rozet sayıları. Sorgu başarısız olursa 0 döner; menü hiçbir durumda
 * hata yüzünden çökmez.
 *
 * @return array<string, int>
 */
function yonetimRozetleri(): array
{
    $sorgular = [
        'destek' => "SELECT COUNT(*) FROM tickets WHERE status IN ('open','customer_reply','on_hold')",
        'iptal' => "SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'",
        'kesif' => "SELECT COUNT(*) FROM kesif_talepleri WHERE durum = 'yeni'",
        'iletisim' => "SELECT COUNT(*) FROM iletisim_mesajlari WHERE durum = 'yeni'",
        'marka' => "SELECT COUNT(*) FROM marka_tescil_talepleri WHERE durum = 'yeni'",
        'siparis' => "SELECT COUNT(*) FROM orders WHERE status = 'pending'",
        'fatura' => "SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'",
    ];

    $rozet = [];
    foreach ($sorgular as $anahtar => $sql) {
        try {
            $rozet[$anahtar] = (int) Database::fetchColumn($sql);
        } catch (Throwable $e) {
            $rozet[$anahtar] = 0;
        }
    }

    return $rozet;
}

/** Bulunduğumuz sayfanın hangi bölümde olduğunu bulur */
function yonetimAktifBolum(string $dosya): string
{
    foreach (yonetimMenusu() as $anahtar => $bolum) {
        foreach ($bolum['ogeler'] as $oge) {
            if ($oge[0] === $dosya) {
                return $anahtar;
            }
        }
    }

    /* Liste sayfasından açılan ayrıntı sayfaları da bölümü açık tutsun */
    $yakinlik = [
        'satis' => ['client-', 'invoice-', 'order-', 'proposal-'],
        'katalog' => ['product-'],
        'altyapi' => ['server-', 'service-', 'domain-view', 'esxi-'],
        'talepler' => ['ticket-'],
        'icerik' => ['email-template-'],
        'sistem' => ['whmcs-'],
    ];
    foreach ($yakinlik as $bolum => $onEkler) {
        foreach ($onEkler as $onEk) {
            if (str_starts_with($dosya, $onEk)) {
                return $bolum;
            }
        }
    }

    return 'genel';
}
