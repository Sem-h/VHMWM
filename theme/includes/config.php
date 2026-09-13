<?php
/**
 * WHMVM - Frontend Konfigürasyon
 * Site ayarları ve menü yapılandırması (veritabanından dinamik)
 */

// Database ve Settings sınıflarını dahil et
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Settings.php';

// Site Ayarları
$siteConfig = [
    'name' => Settings::get('site_name', SITE_NAME ?? 'WHMVM'),
    'phone' => Settings::get('company_phone', '0212 123 45 67'),
    'email' => Settings::get('company_email', 'info@whmvm.com'),
    'address' => Settings::get('company_address', 'İstanbul, Türkiye'),
];

/**
 * Veritabanından menü öğelerini çek
 */
function getMenuItems(string $menuType = 'main', ?int $parentId = null): array
{
    try {
        // Tablo var mı kontrol et
        $tableExists = Database::fetchColumn(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'menu_items'"
        );

        if (!$tableExists) {
            return getDefaultMenu($menuType);
        }

        if ($parentId === null) {
            $items = Database::fetchAll(
                "SELECT * FROM menu_items WHERE menu_type = ? AND parent_id IS NULL AND is_active = 1 ORDER BY sort_order",
                [$menuType]
            );
        } else {
            $items = Database::fetchAll(
                "SELECT * FROM menu_items WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order",
                [$parentId]
            );
        }

        // Alt menüleri ekle
        foreach ($items as &$item) {
            $children = getMenuItems($menuType, (int) $item['id']);
            if (!empty($children)) {
                $item['children'] = $children;
            }
        }

        return $items;

    } catch (Exception $e) {
        return getDefaultMenu($menuType);
    }
}

/**
 * Varsayılan menü (veritabanı yoksa)
 */
function getDefaultMenu(string $menuType): array
{
    if ($menuType === 'main') {
        return [
            ['title' => 'Ana Sayfa', 'url' => 'index.php', 'icon' => 'fa-home'],
            [
                'title' => 'Hosting',
                'url' => 'magaza.php?group=linux-hosting',
                'icon' => 'fa-server',
                'children' => [
                    [
                        'title' => 'Hosting Hizmetleri',
                        'children' => [
                            ['title' => 'Linux Web Hosting', 'url' => 'magaza.php?group=linux-hosting', 'icon' => 'fa-server', 'description' => 'Yüksek Performanslı Linux Web Hosting hizmetimiz ile web siteleriniz uçacak!'],
                            ['title' => 'Kurumsal Hosting', 'url' => 'magaza.php?group=kurumsal-hosting', 'icon' => 'fa-building', 'description' => 'Tamamen Yüksek kaynaklar ile yapılandırılmış Kurumsal Hosting paketlerimiz ile trafik ve mail sorunu yaşamayacaksınız!'],
                        ]
                    ],
                    [
                        'title' => 'E-Ticaret Hosting',
                        'children' => [
                            ['title' => 'Web Site Builder', 'url' => 'magaza.php?group=website-builder', 'icon' => 'fa-shopping-basket', 'description' => 'Hazır websitesi aracı ile Harika bir web sitesi oluşturmak için hızlıca sipariş verebilirsiniz!.'],
                            ['title' => 'Windows Hosting', 'url' => 'magaza.php?group=windows-hosting', 'icon' => 'fa-windows', 'description' => 'Web Sitenizde ASP, .NET, MVC MSSQL gibi teknolojileri kullanıyorsanız, Windows planlarımıza göz atmanızı öneririz.'],
                        ]
                    ],
                    [
                        'title' => 'Hosting Hizmetleri',
                        'children' => [
                            ['title' => 'Wordpress Hosting', 'url' => 'magaza.php?group=wordpress-hosting', 'icon' => 'fa-wordpress', 'description' => 'Wordpress Hosting paketimiz sayesinde ziyaretçilerinize hızlı bir blog sunun!'],
                            ['title' => 'Arşiv Hosting', 'url' => 'magaza.php?group=arsiv-hosting', 'icon' => 'fa-archive', 'description' => 'Tablet, Telefon, Bilgisayar cihazlarınız üzerinde bulundurduğunuz dosya ve yedeklere her an her yerden erişmek ve düzenlemek ister misiniz?'],
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Sunucu',
                'url' => 'vds-sunucu.php',
                'icon' => 'fa-database',
                'children' => [
                    [
                        'title' => 'Sunucu Çözümleri',
                        'children' => [
                            ['title' => 'VDS Sunucu', 'url' => 'vds-sunucu.php', 'icon' => 'fa-hdd', 'description' => 'Tam sanallaştırma ile izole kaynaklar.'],
                            ['title' => 'Dedicated Sunucu', 'url' => 'magaza.php?group=fiziksel-sunucu', 'icon' => 'fa-server', 'description' => 'Fiziksel sunucu performansı.'],
                        ]
                    ]
                ]
            ],
            ['title' => 'Domain', 'url' => 'alan-adi.php', 'icon' => 'fa-globe'],
            ['title' => 'SSL Sertifikası', 'url' => 'ssl-sertifikasi.php', 'icon' => 'fa-lock'],
            ['title' => 'İletişim', 'url' => 'iletisim.php', 'icon' => 'fa-envelope'],
        ];
    }
    return [];
}

/**
 * Menüyü header.php formatına dönüştür
 */
function convertMenuToHeaderFormat(array $items): array
{
    $menu = [];

    foreach ($items as $item) {
        $menuItem = [
            'label' => $item['title'],
            'url' => $item['url'] ?: '#',
            'icon' => $item['icon'] ?? 'fa-link'
        ];

        // Alt menü varsa
        if (!empty($item['children'])) {
            $submenu = [];

            // 3-Seviyeli yapı (Parent -> Section -> Items)
            // Eğer children'ın children'ı varsa, bu bir section'dır.
            $hasSections = false;
            foreach ($item['children'] as $child) {
                if (!empty($child['children'])) {
                    $hasSections = true;
                    break;
                }
            }

            if ($hasSections) {
                // Section yapısı
                foreach ($item['children'] as $section) {
                    $sectionItems = [];
                    if (!empty($section['children'])) {
                        foreach ($section['children'] as $child) {
                            $sectionItems[] = [
                                'label' => $child['title'],
                                'url' => $child['url'],
                                'icon' => $child['icon'] ?? 'fa-angle-right',
                                'desc' => $child['description'] ?? ''
                            ];
                        }
                    }

                    $submenu[] = [
                        'title' => $section['title'],
                        'items' => $sectionItems
                    ];
                }
            } else {
                // Basit liste (Section yok) - Otomatik 3 sütuna dağıt
                $allChildren = [];
                foreach ($item['children'] as $child) {
                    $allChildren[] = [
                        'label' => $child['title'],
                        'url' => $child['url'],
                        'icon' => $child['icon'] ?? 'fa-angle-right',
                        'desc' => $child['description'] ?? ''
                    ];
                }

                // 3 sütuna böl
                $totalItems = count($allChildren);
                $columns = 3;
                $perColumn = ceil($totalItems / $columns);

                // Sütun başlıkları (parent menüye göre)
                $sectionTitles = [
                    'Web Hosting' => ['Hosting Hizmetleri', 'E-Ticaret Hosting', 'Özel Hosting'],
                    'Sunucu' => ['Sunucu Çözümleri', 'VDS & VPS', 'Fiziksel Sunucular'],
                    'Diğer Hizmetler' => ['Yazılım', 'Ek Hizmetler', 'Destek']
                ];
                $titles = $sectionTitles[$item['title']] ?? ['Hizmetler', 'Çözümler', 'Diğer'];

                // Öğeleri sütunlara dağıt
                $chunks = array_chunk($allChildren, max(1, $perColumn));
                foreach ($chunks as $index => $chunk) {
                    if (!empty($chunk)) {
                        $submenu[] = [
                            'title' => $titles[$index] ?? '',
                            'items' => $chunk
                        ];
                    }
                }
            }

            $menuItem['submenu'] = $submenu;
        }

        $menu[] = $menuItem;
    }

    return $menu;
}

// Ana Menü - Veritabanından çek
$dbMenuItems = getMenuItems('main');
// $dbMenuItems = []; // FORCE DEFAULT for design verification

if (!empty($dbMenuItems)) {
    $mainMenu = convertMenuToHeaderFormat($dbMenuItems);
} else {
    // Varsayılan menü (veritabanı bağlantısı yoksa veya boşsa)
    $mainMenu = convertMenuToHeaderFormat(getDefaultMenu('main'));
}

// Footer Linkleri
$footerLinks = [
    'services' => [
        'title' => 'Hizmetlerimiz',
        'links' => [
            ['label' => 'Web Hosting', 'url' => 'magaza.php?group=linux-hosting'],
            ['label' => 'VDS Sunucu', 'url' => 'vds-sunucu.php'],
            ['label' => 'Cloud Sunucu', 'url' => 'bulut-sunucu.php'],
            ['label' => 'Dedicated Sunucu', 'url' => 'magaza.php?group=fiziksel-sunucu'],
            ['label' => 'Domain Kaydı', 'url' => 'alan-adi.php'],
            ['label' => 'SSL Sertifikası', 'url' => 'ssl-sertifikasi.php'],
        ]
    ],
    'company' => [
        'title' => 'Kurumsal',
        'links' => [
            ['label' => 'Hakkımızda', 'url' => 'hakkimizda.php'],
            ['label' => 'İletişim', 'url' => 'iletisim.php'],
            ['label' => 'Referanslar', 'url' => 'referanslar.php'],
            ['label' => 'Blog', 'url' => 'blog.php'],
            ['label' => 'Kariyer', 'url' => 'kariyer.php'],
        ]
    ],
    'support' => [
        'title' => 'Destek',
        'links' => [
            ['label' => 'Bilgi Bankası', 'url' => 'bilgi-bankasi.php'],
            ['label' => 'Destek Talebi', 'url' => 'client/tickets.php'],
            ['label' => 'Sunucu Durumu', 'url' => 'sistem-durumu.php'],
            ['label' => 'SLA', 'url' => 'sla.php'],
        ]
    ],
    'legal' => [
        'title' => 'Yasal',
        'links' => [
            ['label' => 'Kullanım Şartları', 'url' => 'kullanim-sartlari.php'],
            ['label' => 'Gizlilik Politikası', 'url' => 'gizlilik-politikasi.php'],
            ['label' => 'KVKK', 'url' => 'kvkk.php'],
            ['label' => 'İptal ve İade', 'url' => 'iade-politikasi.php'],
        ]
    ]
];

// Sosyal Medya
$socialMedia = [
    ['icon' => 'fa-facebook-f', 'url' => Settings::get('social_facebook', '#'), 'label' => 'Facebook'],
    ['icon' => 'fa-twitter', 'url' => Settings::get('social_twitter', '#'), 'label' => 'Twitter'],
    ['icon' => 'fa-instagram', 'url' => Settings::get('social_instagram', '#'), 'label' => 'Instagram'],
    ['icon' => 'fa-linkedin-in', 'url' => Settings::get('social_linkedin', '#'), 'label' => 'LinkedIn'],
    ['icon' => 'fa-youtube', 'url' => Settings::get('social_youtube', '#'), 'label' => 'YouTube'],
];
