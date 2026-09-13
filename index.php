<?php
/**
 * WHMVM - Ana Sayfa (Veri Merkezi)
 */

declare(strict_types=1);

if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/install.php');
    exit;
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Affiliate.php';

session_name(SESSION_NAME);
session_start();

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    try {
        Affiliate::trackVisit($_GET['ref']);
    } catch (Throwable $e) {
        // Sessizce geç
    }
}

$pageTitle = 'Veri Merkezi, Sunucu Barındırma ve Hosting Hizmetleri';
$pageDescription = SITE_NAME . ' - Veri merkezi altyapısı, kabinet barındırma, fiziksel ve sanal sunucu çözümleri. Yedekli güç, 7/24 izleme ve teknik destek.';

/* ---------- Veri merkezi hizmet hatları ---------- */
$dcLines = [
    [
        'no' => '01',
        'slug' => 'co-location',
        'name' => 'Kabinet Barındırma',
        'desc' => 'Kendi donanımınızı veri merkezimizde barındırın. Güç, soğutma ve bağlantı bizden.',
        'url' => 'magaza.php?group=co-location',
        'kunye' => [
            ['Kabinet tipi', 'Tam / yarım / U bazlı'],
            ['Enerji', 'Ölçümlenen besleme'],
            ['Erişim', 'Randevulu, kilitli kabinet'],
        ],
    ],
    [
        'no' => '02',
        'slug' => 'fiziksel-sunucu',
        'name' => 'Fiziksel Sunucu',
        'desc' => 'Kaynaklarını kimseyle paylaşmayan, tamamen size tahsis edilmiş donanım.',
        'url' => 'magaza.php?group=fiziksel-sunucu',
        'kunye' => [
            ['Donanım', 'Tamamen size tahsisli'],
            ['Depolama', 'NVMe SSD'],
            ['Yönetim', 'IPMI / uzaktan erişim'],
        ],
    ],
    [
        'no' => '03',
        'slug' => 'vds',
        'name' => 'Sanal Sunucu (VDS)',
        'desc' => 'İşlemci, bellek ve diski kendiniz belirleyin; dakikalar içinde teslim.',
        'url' => 'vds-sunucu.php',
        'kunye' => [
            ['Yapılandırma', 'vCPU, RAM ve disk size ait'],
            ['Sanallaştırma', 'KVM'],
            ['Teslim', 'Dakikalar içinde'],
        ],
    ],
    [
        'no' => '04',
        'slug' => 'btk-log-sunucu',
        'name' => 'Yasal Log Kaydı',
        'desc' => '5651 sayılı kanun kapsamında zaman damgalı erişim kaydı saklama.',
        'url' => 'magaza.php?group=btk-log-sunucu',
        'kunye' => [
            ['Kapsam', '5651 sayılı kanun'],
            ['Kayıt', 'Zaman damgalı, imzalı'],
            ['Saklama', 'Yasal süre boyunca'],
        ],
    ],
];

// Ürünü olmayan hizmet hattını vitrinden çıkarma; fiyatı varsa göster
try {
    foreach ($dcLines as $i => $l) {
        if ($l['slug'] === 'vds') {
            continue;
        }
        $row = Database::fetch("
            SELECT (SELECT MIN(p.price_monthly) FROM products p
                     WHERE p.group_id = g.id AND p.is_active = 1 AND p.price_monthly > 0) AS fiyat,
                   (SELECT COUNT(*) FROM products p
                     WHERE p.group_id = g.id AND p.is_active = 1) AS adet
            FROM product_groups g WHERE g.slug = ?", [$l['slug']]);
        $dcLines[$i]['fiyat'] = !empty($row['fiyat']) ? (float) $row['fiyat'] : null;
        $dcLines[$i]['adet'] = (int) ($row['adet'] ?? 0);
    }
} catch (Exception $e) {
    // Fiyat olmadan da listelenir
}

/* VDS başlangıç fiyatı */
$dcVdsPrice = null;
try {
    $vp = [];
    foreach (Database::fetchAll("SELECT resource_type, unit_price FROM vds_pricing WHERE is_active = 1") as $r) {
        $vp[$r['resource_type']] = (float) $r['unit_price'];
    }
    if (!empty($vp)) {
        $dcVdsPrice = ($vp['base'] ?? 150) + 2 * ($vp['cpu'] ?? 25) + 4 * ($vp['ram'] ?? 25) + 50 * ($vp['disk'] ?? 3);
    }
} catch (Exception $e) {
    $dcVdsPrice = null;
}
foreach ($dcLines as $i => $l) {
    if ($l['slug'] === 'vds') {
        $dcLines[$i]['fiyat'] = $dcVdsPrice;
        $dcLines[$i]['adet'] = 1;
    }
    // Satista paketi olmayan hat, magaza yerine teklif formuna yonlendirilir
    if ((int) ($dcLines[$i]['adet'] ?? 0) === 0) {
        $dcLines[$i]['url'] = 'iletisim.php';
        $dcLines[$i]['fiyat'] = null;
    }
}

/* ---------- Barındırma ürünleri (katalog) ---------- */
$dcCatalogMeta = [
    'linux-hosting' => 'Paylaşımlı barındırma',
    'wordpress-hosting' => 'WordPress için ayarlı',
    'website-builder' => 'Kodsuz site kurucu',
    'kurumsal-hosting' => 'Kurumsal e-posta ve site',
    'windows-hosting' => 'Windows / ASP.NET',
    'arsiv-hosting' => 'Depolama ve yedek',
    'ekran-kartli-sunucu' => 'GPU destekli hesaplama',
];
$dcCatalog = [];
try {
    foreach (
        Database::fetchAll("
        SELECT g.name, g.slug,
               (SELECT MIN(p.price_monthly) FROM products p
                 WHERE p.group_id = g.id AND p.is_active = 1 AND p.price_monthly > 0) AS fiyat,
               (SELECT COUNT(*) FROM products p
                 WHERE p.group_id = g.id AND p.is_active = 1) AS adet,
               (SELECT p.name FROM products p
                 WHERE p.group_id = g.id AND p.is_active = 1 AND p.price_monthly > 0
                 ORDER BY p.price_monthly LIMIT 1) AS paket_adi,
               (SELECT p.description FROM products p
                 WHERE p.group_id = g.id AND p.is_active = 1 AND p.price_monthly > 0
                 ORDER BY p.price_monthly LIMIT 1) AS aciklama
        FROM product_groups g
        WHERE g.is_hidden = 0
        ORDER BY g.order_priority, g.id") as $g
    ) {
        if ((int) $g['adet'] === 0 || !isset($dcCatalogMeta[$g['slug']])) {
            continue;
        }

        // Giriş paketinin açıklamasından en fazla altı özellik satırı
        $ozellikler = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $g['aciklama']) as $satir) {
            $satir = trim(strip_tags($satir), " \t\-•*");
            if ($satir !== '') {
                $ozellikler[] = $satir;
            }
            if (count($ozellikler) === 6) {
                break;
            }
        }

        $dcCatalog[] = [
            'slug' => $g['slug'],
            'name' => $g['name'],
            'url' => 'magaza.php?group=' . $g['slug'],
            'note' => $dcCatalogMeta[$g['slug']],
            'fiyat' => $g['fiyat'] !== null ? (float) $g['fiyat'] : null,
            'adet' => (int) $g['adet'],
            'paket' => (string) ($g['paket_adi'] ?? ''),
            'ozellikler' => $ozellikler,
        ];
    }
} catch (Exception $e) {
    $dcCatalog = [];
}

/* ---------- Tesis bolgeleri: izometrik semadaki cikma etiketleriyle eslesir ---------- */
$isoDetay = [
    'jenerator' => [
        'baslik' => 'Jeneratör grubu',
        'ozet' => 'N+1 yedekli besleme',
        'metin' => 'Şehir şebekesinde kesinti olduğunda jeneratör grubu otomatik olarak devreye girer ve tesisin '
            . 'tüm yükünü üstlenir. Jeneratörler tam yüke ulaşana kadar geçen sürede besleme UPS sistemlerinden '
            . 'karşılanır, bu yüzden sunucular kesinti hissetmez.',
        'maddeler' => ['N+1 yedeklilik', 'Otomatik devreye girme', 'Yakıt stoğu ve periyodik test'],
    ],
    'lobi' => [
        'baslik' => 'Lobi ve giriş',
        'ozet' => 'Kontrollü fiziksel erişim',
        'metin' => 'Tesise giriş randevu ile yapılır. Ziyaretçi kaydı alınır, kimlik doğrulaması yapılır ve '
            . 'salonlara yetkili personel eşliğinde geçilir. Giriş noktaları kamera ile sürekli izlenir.',
        'maddeler' => ['Randevulu giriş', 'Ziyaretçi kaydı', 'Kamera ile 7/24 izleme'],
    ],
    'toplanti' => [
        'baslik' => 'Toplantı odaları',
        'ozet' => 'Müşteri görüşmeleri',
        'metin' => 'Kurulum planlaması, kapasite artışı ve teknik görüşmeler için müşterilere ayrılmış '
            . 'çalışma alanları. Tesiste işlem yapacak ekipler burada hazırlık yapabilir.',
        'maddeler' => ['Kurulum planlaması', 'Teknik görüşmeler'],
    ],
    'noc' => [
        'baslik' => 'NOC',
        'ozet' => '7/24 izleme merkezi',
        'metin' => 'Network Operations Center, tesisin ve müşteri hizmetlerinin kesintisiz izlendiği merkezdir. '
            . 'Sıcaklık, nem, güç tüketimi ve ağ trafiği sürekli takip edilir; belirlenen eşik aşıldığında '
            . 'nöbetçi ekip müdahale eder.',
        'maddeler' => ['7/24 izleme', 'Sıcaklık, nem ve güç takibi', 'Olay müdahale'],
    ],
    'ofis' => [
        'baslik' => 'Ofisler',
        'ozet' => 'Yönetim ve teknik ekip',
        'metin' => 'Yönetim, satış ve teknik ekiplerin çalışma alanı. Saha müdahalesi gerektiren durumlarda '
            . 'ekip aynı bina içinde olduğu için yerinde işlem hızlı yapılır.',
        'maddeler' => ['Yönetim ve teknik ekip', 'Hafta içi 09:00 – 18:00'],
    ],
    'telekom' => [
        'baslik' => 'Telekom odası',
        'ozet' => 'Operatör bağlantıları',
        'metin' => 'Operatör bağlantılarının tesise girdiği ve dağıtıldığı odadır. Omurga bağlantıları burada '
            . 'sonlanır, salonlara buradan taşınır. Trafik filtreleme ve yönlendirme de bu noktada yapılır.',
        'maddeler' => ['Yüksek kapasiteli omurga', 'DDoS filtreleme', 'IPv4 tahsisi ve yönlendirme'],
    ],
    'depo' => [
        'baslik' => 'Depo',
        'ozet' => 'Yedek donanım',
        'metin' => 'Yedek disk, güç kaynağı, kablo ve sarf malzemesinin bulunduğu alan. Arızalı bileşenin '
            . 'hızlı değiştirilebilmesi için yedek parça tesiste tutulur.',
        'maddeler' => ['Yedek donanım', 'Sarf malzemesi', 'Hızlı parça değişimi'],
    ],
    'yangin' => [
        'baslik' => 'Yangın söndürme',
        'ozet' => 'Gazlı söndürme sistemi',
        'metin' => 'Salonlarda erken duman algılama ve gazlı söndürme sistemi bulunur. Gazlı sistem, su '
            . 'kullanmadığı için yangını donanıma zarar vermeden bastırır.',
        'maddeler' => ['Gazlı söndürme sistemi', 'Erken duman algılama', 'Yangın algılama sensörleri'],
    ],
    'ups' => [
        'baslik' => 'UPS',
        'ozet' => 'Kesintisiz güç kaynağı',
        'metin' => 'Kesintisiz güç kaynakları, şebeke kesildiği anda yükü akü grubundan beslemeye geçer. '
            . 'Jeneratör devreye girene kadar hiçbir sunucu kapanmaz.',
        'maddeler' => ['Kesintisiz besleme', 'Akü grubu', 'Yedekli ünite yapısı'],
    ],
    'elektrik' => [
        'baslik' => 'Elektrik odası',
        'ozet' => 'Ana dağıtım panosu',
        'metin' => 'Ana dağıtım panosundan kabinetlere kadar enerji dağıtımı buradan yönetilir. Her kabinetin '
            . 'tüketimi ayrı ölçülür, böylece faturalama ve kapasite planlaması gerçek tüketime dayanır.',
        'maddeler' => ['Ana dağıtım panosu', 'Yedekli besleme yolları', 'Kabinet başına ölçüm'],
    ],
    'crac' => [
        'baslik' => 'CRAC üniteleri',
        'ozet' => 'N+1 yedekli soğutma',
        'metin' => 'Hassas kontrollü klima üniteleri koğuş sıcaklığını 18 – 24 °C, bağıl nemi %40 – %60 '
            . 'aralığında tutar. Üniteler N+1 yedeklidir; biri devre dışı kalsa da sıcaklık korunur.',
        'maddeler' => ['N+1 yedekli soğutma', '18 – 24 °C koğuş sıcaklığı', 'Sıcak/soğuk koridor düzeni'],
    ],
    'salon1' => [
        'baslik' => 'Salon-1',
        'ozet' => 'Kabinet koğuşu',
        'metin' => 'Yükseltilmiş döşeme, soğuk koridor kapatma ve kilitli kabinetlerden oluşan kabinet koğuşu. '
            . 'Kendi donanımınızı tam, yarım ya da U bazlı kabinet olarak barındırabilirsiniz.',
        'maddeler' => ['Tam, yarım ve U bazlı kabinet', 'Kilitli kabinet', 'Randevulu erişim'],
    ],
    'salon2' => [
        'baslik' => 'Salon-2',
        'ozet' => 'Kabinet koğuşu',
        'metin' => 'İkinci kabinet koğuşu; Salon-1 ile aynı standartta yükseltilmiş döşeme, koridor düzeni ve '
            . 'erişim kontrolü uygulanır. Kapasite artışları bu salondan karşılanır.',
        'maddeler' => ['Salon-1 ile aynı standart', 'Kapasite artışı', 'Kilitli kabinet'],
    ],
];

/* Referanslar */
$dcRefs = [];
try {
    $dcRefs = Database::fetchAll("SELECT name, logo, website, category, dark_logo FROM `references` WHERE is_active = 1 ORDER BY sort_order, id");
} catch (Exception $e) {
    $dcRefs = [];
}

require_once __DIR__ . '/theme/includes/header.php';
?>

<style>
    /* ==========================================
       Ana sayfa - veri merkezi
       Tesis odaklı kurumsal düzen: teknik panel
       görünümü, ölçülü renk, net hiyerarşi.
       ========================================== */
    .dc {
        --dc-line: var(--border-color);
        --dc-hair: var(--border-light);
        --dc-panel: var(--bg-primary);
        --dc-accent: var(--primary);
        --dc-radius: 12px;
    }

    /* Kart baglantilari metin rengini miras alir; butonlar kendi rengini korur */
    .dc a:not(.btn) {
        color: inherit;
    }

    .dc-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--dc-accent);
        margin-bottom: var(--space-4);
    }

    .dc-label::before {
        content: '';
        width: 22px;
        height: 2px;
        background: var(--dc-accent);
    }

    /* ===== Hero =====
       Ortalanmis metin bloku, altinda tam genislik
       kabinet koridoru ve kenardan kenara olcu seridi. */
    .dc-hero {
        position: relative;
        overflow: hidden;
        padding-top: clamp(24px, 2vw, 34px);
        border-bottom: 1px solid var(--dc-line);
    }

    .dc-hero-copy {
        position: relative;
        z-index: 2;
        text-align: center;
        padding-bottom: clamp(12px, 1.4vw, 20px);
    }

    .dc-hero .dc-label {
        justify-content: center;
        margin-bottom: var(--space-4);
    }

    /* ortalanmis duzende tire iki yanda */
    .dc-hero .dc-label::after {
        content: '';
        width: 22px;
        height: 2px;
        background: var(--dc-accent);
    }

    .dc-hero h1 {
        margin: 0 auto var(--space-4);
        max-width: 44ch;
        font-size: clamp(29px, 2.3vw + 16px, 45px);
        font-weight: 700;
        line-height: 1.16;
        letter-spacing: -0.03em;
        color: var(--text-primary);
        text-wrap: balance;
    }

    .dc-hero h1 em {
        font-style: normal;
        color: var(--primary-light);
        white-space: nowrap;
    }

    .dc-hero-lead {
        margin: 0 auto var(--space-5);
        max-width: 82ch;
        font-size: var(--text-md);
        line-height: 1.65;
        color: var(--text-muted);
        text-wrap: pretty;
    }

    .dc-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: var(--space-3);
    }

    .dc-actions .btn i {
        font-size: 11px;
    }

    /* ===== Kabinet koridoru ===== */
    .dc-corridor {
        /* kabinet yuzu / kenar / raf cizgisi - temaya gore */
        --cw-face: #323c67;
        --cw-edge: #4b5892;
        --cw-line: #626fa8;
        position: relative;
        width: 100%;
        line-height: 0;
        /* ust kenar arka plana erisin */
        -webkit-mask-image: linear-gradient(to bottom, transparent 0%, #000 17%, #000 100%);
        mask-image: linear-gradient(to bottom, transparent 0%, #000 17%, #000 100%);
    }

    .dc-corridor svg {
        display: block;
        width: 100%;
        height: clamp(108px, 8.4vw, 128px);
    }

    [data-theme="light"] .dc-corridor {
        --cw-face: #16204a;
        --cw-edge: #26326a;
        --cw-line: #6f7dae;
    }

    /* ===== Olcu seridi =====
       Her veri kendi gostergesiyle: uptime yayi, N+1 bloklari,
       sicaklik araligi bandi, 7 gunluk sureklilik noktalari. */
    .dc-band {
        position: relative;
        z-index: 2;
        border-top: 1px solid var(--dc-line);
        background: var(--dc-panel);
    }

    .dc-band-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .dc-bi {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-4) var(--space-5) var(--space-4) 0;
        border-right: 1px solid var(--dc-hair);
    }

    .dc-band-grid .dc-bi+.dc-bi {
        padding-left: var(--space-5);
    }

    .dc-band-grid .dc-bi:last-child {
        border-right: none;
        padding-right: 0;
    }

    /* gosterge */
    .dc-bi-fig {
        flex-shrink: 0;
        width: 46px;
        height: 46px;
    }

    .dc-bi-fig svg {
        display: block;
        width: 100%;
        height: 100%;
    }

    .dc-bi-fig .yol {
        fill: none;
        stroke: var(--dc-hair);
        stroke-width: 4;
        stroke-linecap: round;
    }

    .dc-bi-fig .dolu {
        fill: none;
        stroke: var(--dc-accent);
        stroke-width: 4;
        stroke-linecap: round;
    }

    .dc-bi-fig .blok {
        fill: var(--dc-accent);
    }

    .dc-bi-fig .yedek {
        fill: none;
        stroke: var(--dc-accent);
        stroke-width: 2.5;
        stroke-dasharray: 2.5 2.5;
    }

    .dc-bi-fig .nokta {
        fill: var(--dc-accent);
    }

    /* metin */
    .dc-bi-txt {
        min-width: 0;
    }

    .dc-bi-key {
        display: block;
        margin-bottom: 6px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .dc-bi b {
        font-size: var(--text-lg);
        font-weight: 700;
        letter-spacing: -0.025em;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .dc-bi-txt>span:last-child {
        white-space: nowrap;
    }

    .dc-bi span {
        margin-left: 7px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    @media (max-width: 1100px) {
        .dc-band-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dc-band-grid .dc-bi:nth-child(2) {
            border-right: none;
            padding-right: 0;
        }

        .dc-band-grid .dc-bi:nth-child(3) {
            padding-left: 0;
        }

        .dc-band-grid .dc-bi:nth-child(-n+2) {
            border-bottom: 1px solid var(--dc-hair);
        }
    }

    @media (max-width: 560px) {
        .dc-band-grid {
            grid-template-columns: 1fr;
        }

        .dc-bi,
        .dc-band-grid .dc-bi+.dc-bi,
        .dc-band-grid .dc-bi:nth-child(3) {
            padding: var(--space-4) 0;
            border-right: none;
            border-bottom: 1px solid var(--dc-hair);
        }

        .dc-band-grid .dc-bi:last-child {
            border-bottom: none;
        }
    }

    /* ===== Bölümler ===== */
    .dc-sec {
        padding: clamp(40px, 4.5vw, 72px) 0;
    }

    .dc-sec.is-tint {
        background: var(--dc-panel);
        border-top: 1px solid var(--dc-line);
        border-bottom: 1px solid var(--dc-line);
    }

    .dc-head {
        max-width: 680px;
        margin-bottom: clamp(24px, 2.5vw, 40px);
    }

    .dc-head h2 {
        font-size: clamp(22px, 1.4vw + 16px, 32px);
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.025em;
        color: var(--text-primary);
    }

    .dc-head p {
        margin-top: var(--space-3);
        font-size: var(--text-md);
        color: var(--text-muted);
        line-height: 1.65;
    }

    /* ===== Tesis yetenekleri =====
       Izometrik tesis kesiti, tiklanabilir cikma etiketleri ve ayrinti paneli. */
    .dc-iso {
        position: relative;
        /* container disinda: ekran genisligine yayilir, yan kenarlik yok */
        padding: var(--space-5) 0;
        border-top: 1px solid var(--dc-line);
        border-bottom: 1px solid var(--dc-line);
        background: var(--dc-panel);
        background-image:
            linear-gradient(to right, color-mix(in srgb, var(--border-color) 40%, transparent) 1px, transparent 1px),
            linear-gradient(to bottom, color-mix(in srgb, var(--border-color) 40%, transparent) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    .dc-iso svg {
        display: block;
        width: 100%;
        height: auto;
    }

    /* cikma etiketleri */
    .dc-iso-t {
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.06em;
        fill: var(--text-primary);
    }

    .dc-iso-s {
        font-family: inherit;
        font-size: 11.5px;
        font-weight: 500;
        fill: var(--text-muted);
    }

    .dc-iso-l {
        fill: none;
        stroke: var(--dc-accent);
        stroke-opacity: 0.5;
        stroke-width: 1;
    }

    .dc-iso-d {
        fill: var(--dc-accent);
    }

    /* tiklanabilir durum */
    .dc-iso-cal {
        cursor: pointer;
        outline: none;
    }

    .dc-iso-hit {
        transition: fill 0.15s ease;
    }

    .dc-iso-cal:hover .dc-iso-hit,
    .dc-iso-cal:focus-visible .dc-iso-hit {
        fill: color-mix(in srgb, var(--primary) 12%, transparent);
    }

    .dc-iso-cal.is-on .dc-iso-hit {
        fill: color-mix(in srgb, var(--primary) 16%, transparent);
    }

    .dc-iso-cal .dc-iso-t,
    .dc-iso-cal .dc-iso-l,
    .dc-iso-cal .dc-iso-d {
        transition: fill 0.15s ease, stroke 0.15s ease, stroke-opacity 0.15s ease, stroke-width 0.15s ease;
    }

    .dc-iso-cal:hover .dc-iso-t,
    .dc-iso-cal:focus-visible .dc-iso-t,
    .dc-iso-cal.is-on .dc-iso-t {
        fill: var(--dc-accent);
    }

    .dc-iso-cal:hover .dc-iso-l,
    .dc-iso-cal:focus-visible .dc-iso-l,
    .dc-iso-cal.is-on .dc-iso-l {
        stroke-opacity: 1;
        stroke-width: 1.6;
    }

    .dc-iso-cal.is-on .dc-iso-d,
    .dc-iso-cal:focus-visible .dc-iso-d {
        stroke: var(--dc-accent);
        stroke-opacity: 0.3;
        stroke-width: 6;
    }

    /* dar ekran secim cubugu */
    .dc-det-nav {
        display: none;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: var(--space-4);
    }

    .dc-det-chip {
        padding: 7px 14px;
        border: 1px solid var(--dc-line);
        border-radius: 999px;
        background: transparent;
        font-family: inherit;
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
    }

    .dc-det-chip:hover {
        border-color: var(--dc-accent);
        color: var(--dc-accent);
    }

    .dc-det-chip.is-on {
        background: var(--dc-accent);
        border-color: transparent;
        color: #fff;
    }

    /* ayrinti paneli */
    .dc-det-wrap {
        min-height: 150px;
        margin-top: var(--space-5);
        padding: var(--space-5) var(--space-3) var(--space-2);
        border-top: 1px solid var(--dc-hair);
    }

    .dc-det {
        display: none;
    }

    .dc-det.is-on {
        display: block;
    }

    .dc-det header {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--space-3);
        margin-bottom: var(--space-3);
    }

    .dc-det header b {
        font-size: var(--text-md);
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--text-primary);
    }

    .dc-det header span {
        font-size: var(--text-xs);
        font-weight: 600;
        color: var(--dc-accent);
    }

    .dc-det p {
        max-width: 88ch;
        margin-bottom: var(--space-4);
        font-size: var(--text-sm);
        line-height: 1.7;
        color: var(--text-muted);
    }

    .dc-det ul {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .dc-det li {
        padding: 6px 13px;
        border: 1px solid var(--dc-line);
        border-radius: 999px;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Ayrinti baloncugu ===== */
    .dc-pop {
        position: absolute;
        z-index: 6;
        width: min(360px, calc(100% - 16px));
        padding: var(--space-5);
        border: 1px solid var(--dc-line);
        border-radius: var(--dc-radius);
        background: var(--bg-body);
        box-shadow: 0 20px 52px -18px rgba(0, 0, 0, 0.55);
        animation: dc-pop-in 0.16s var(--ease-out, ease-out);
    }

    .dc-pop[hidden] {
        display: none;
    }

    @keyframes dc-pop-in {
        from {
            opacity: 0;
            transform: translateY(6px);
        }
    }

    /* etikete bakan ok */
    .dc-pop::before {
        content: '';
        position: absolute;
        top: 22px;
        width: 10px;
        height: 10px;
        border: 1px solid var(--dc-line);
        background: var(--bg-body);
        transform: rotate(45deg);
    }

    .dc-pop.is-left::before {
        left: -6px;
        border-right: 0;
        border-top: 0;
    }

    .dc-pop.is-right::before {
        right: -6px;
        border-left: 0;
        border-bottom: 0;
    }

    .dc-pop-x {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 7px;
        background: transparent;
        font-size: 20px;
        line-height: 1;
        color: var(--text-muted);
        cursor: pointer;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .dc-pop-x:hover {
        background: var(--surface-2);
        color: var(--text-primary);
    }

    .dc-pop .dc-det header {
        padding-right: 28px;
    }

    .dc-pop .dc-det p {
        max-width: none;
    }

    /* Genis ekranda ayrinti baloncukta acilir; alttaki panel gizli kalir */
    @media (min-width: 993px) {
        .dc-det-wrap {
            display: none;
        }
    }
    /* Dar ekranda cikma etiketleri okunacak boyutun altina duser */
    @media (max-width: 992px) {
        .dc-iso-labels {
            display: none;
        }

        .dc-det-nav {
            display: flex;
        }

        .dc-pop {
            display: none !important;
        }
    }

    /* ===== Hizmet hatları =====
       Tek cerceveli panel, icinde hairline ile ayrilmis dort sutun. */
    .dc-lines {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--dc-line);
        border-radius: var(--dc-radius);
        background: var(--dc-panel);
        overflow: hidden;
    }

    .dc-line {
        display: flex;
        flex-direction: column;
        padding: var(--space-5);
        border-right: 1px solid var(--dc-hair);
        transition: background-color 0.16s ease;
    }

    .dc-lines .dc-line:last-child {
        border-right: none;
    }

    .dc-line:hover {
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .dc-line-no {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: var(--space-4);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.15em;
        color: var(--dc-accent);
        font-variant-numeric: tabular-nums;
    }

    .dc-line-no::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--dc-hair);
    }

    .dc-line h3 {
        margin-bottom: var(--space-2);
        font-size: var(--text-md);
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--text-primary);
    }

    .dc-line>p {
        margin-bottom: var(--space-4);
        font-size: var(--text-sm);
        line-height: 1.6;
        color: var(--text-muted);
    }

    /* teknik kunye */
    .dc-line dl {
        flex: 1;
        margin: 0 0 var(--space-4);
        padding-top: var(--space-2);
        border-top: 1px solid var(--dc-hair);
    }

    .dc-line dl>div {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 9px 0;
        border-bottom: 1px solid var(--dc-hair);
    }

    .dc-line dl>div:last-child {
        border-bottom: none;
    }

    .dc-line dt {
        flex-shrink: 0;
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .dc-line dd {
        margin: 0;
        font-size: var(--text-xs);
        font-weight: 600;
        text-align: right;
        color: var(--text-muted);
    }

    /* fiyat ve eylem */
    .dc-line-price {
        margin-bottom: var(--space-4);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .dc-line-price b {
        display: block;
        margin-bottom: 1px;
        font-size: var(--text-lg);
        font-weight: 700;
        letter-spacing: -0.025em;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .dc-line-go {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: 10px 16px;
        border: 1px solid var(--dc-line);
        border-radius: 9px;
        font-size: var(--text-sm);
        font-weight: 600;
        color: var(--text-primary);
        transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease;
    }

    .dc-line-go i {
        font-size: 10px;
    }

    .dc-line:hover .dc-line-go {
        background: var(--dc-accent);
        border-color: transparent;
        color: #fff;
    }

    @media (max-width: 1100px) {
        .dc-lines {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dc-lines .dc-line:nth-child(2) {
            border-right: none;
        }

        .dc-lines .dc-line:nth-child(-n+2) {
            border-bottom: 1px solid var(--dc-hair);
        }
    }

    @media (max-width: 640px) {
        .dc-lines {
            grid-template-columns: 1fr;
        }

        .dc-lines .dc-line {
            border-right: none;
            border-bottom: 1px solid var(--dc-hair);
        }

        .dc-lines .dc-line:last-child {
            border-bottom: none;
        }
    }

    /* ===== Barındırma vitrini =====
       Sekmeler + seçilen hizmetin giriş paketi.
       Tablo yerine tek pakete odaklanan gösterim. */
    .dc-kat {
        border: 1px solid var(--dc-line);
        border-radius: var(--dc-radius);
        background: var(--dc-panel);
        overflow: hidden;
    }

    /* sekme şeridi */
    .dc-kat-tabs {
        display: flex;
        overflow-x: auto;
        border-bottom: 1px solid var(--dc-line);
        background: color-mix(in srgb, var(--primary) 4%, transparent);
        scrollbar-width: none;
    }

    .dc-kat-tabs::-webkit-scrollbar {
        display: none;
    }

    .dc-kat-tab {
        position: relative;
        flex: 1 0 auto;
        padding: 15px var(--space-5);
        border: 0;
        border-right: 1px solid var(--dc-hair);
        background: transparent;
        font-family: inherit;
        font-size: var(--text-sm);
        font-weight: 600;
        white-space: nowrap;
        color: var(--text-muted);
        cursor: pointer;
        transition: background-color 0.16s ease, color 0.16s ease;
    }

    .dc-kat-tabs .dc-kat-tab:last-child {
        border-right: none;
    }

    .dc-kat-tab:hover {
        color: var(--text-primary);
    }

    .dc-kat-tab.is-on {
        background: var(--dc-panel);
        color: var(--text-primary);
    }

    /* seçili sekmenin alt çizgisi */
    .dc-kat-tab.is-on::after {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: -1px;
        height: 2px;
        background: var(--dc-accent);
    }

    .dc-kat-tab small {
        display: block;
        margin-top: 3px;
        font-size: var(--text-xs);
        font-weight: 400;
        color: var(--text-muted);
    }

    /* panel */
    .dc-kat-panel {
        display: none;
        grid-template-columns: minmax(0, 1fr) 300px;
    }

    .dc-kat-panel.is-on {
        display: grid;
    }

    .dc-kat-ozet {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: var(--space-5);
        border-right: 1px solid var(--dc-hair);
    }

    .dc-kat-key {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 13px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .dc-kat-key::before {
        content: '';
        flex-shrink: 0;
        width: 14px;
        height: 2px;
        background: var(--dc-accent);
    }

    .dc-kat-ozet ul {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px var(--space-5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .dc-kat-ozet li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: var(--text-sm);
        line-height: 1.5;
        color: var(--text-gray);
    }

    .dc-kat-ozet li i {
        flex-shrink: 0;
        margin-top: 3px;
        font-size: 11px;
        color: var(--dc-accent);
    }

    /* fiyat sütunu */
    .dc-kat-fiyat {
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: var(--space-5);
        background: color-mix(in srgb, var(--primary) 4%, transparent);
    }

    .dc-kat-fiyat .paket {
        margin-bottom: var(--space-4);
        font-size: var(--text-base);
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--text-primary);
    }

    .dc-kat-fiyat .paket span {
        display: block;
        margin-top: 2px;
        font-size: var(--text-xs);
        font-weight: 400;
        color: var(--text-muted);
    }

    .dc-kat-fiyat .tutar {
        font-size: clamp(28px, 1.6vw + 20px, 38px);
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1;
        color: var(--text-primary);
        font-variant-numeric: tabular-nums;
    }

    .dc-kat-fiyat .tutar small {
        font-size: var(--text-base);
        font-weight: 400;
        color: var(--text-muted);
    }

    .dc-kat-fiyat .not {
        margin: 8px 0 var(--space-5);
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    .dc-kat-fiyat .btn {
        width: 100%;
    }

    @media (max-width: 900px) {
        .dc-kat-panel.is-on {
            grid-template-columns: 1fr;
        }

        .dc-kat-ozet {
            border-right: none;
            border-bottom: 1px solid var(--dc-hair);
        }

        .dc-kat-tab {
            flex: 0 0 auto;
        }
    }

    @media (max-width: 560px) {
        .dc-kat-ozet ul {
            grid-template-columns: 1fr;
        }
    }

    /* ===== Referanslar ===== */
    .dc-refs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1px;
        background: var(--dc-line);
        border: 1px solid var(--dc-line);
        border-radius: var(--dc-radius);
        overflow: hidden;
    }

    .dc-ref {
        display: flex;
        align-items: center;
        gap: var(--space-4);
        padding: var(--space-4) var(--space-5);
        background: var(--dc-panel);
        transition: background-color 0.16s ease;
    }

    .dc-ref:hover {
        background: color-mix(in srgb, var(--primary) 6%, var(--dc-panel));
    }

    .dc-ref-logo {
        width: 58px;
        height: 40px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .dc-ref-logo.is-dark {
        background: #fff;
        border-radius: 6px;
        padding: 4px;
    }

    .dc-ref-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .dc-ref b {
        display: block;
        font-size: var(--text-base);
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.3;
    }

    .dc-ref span {
        font-size: var(--text-xs);
        color: var(--text-muted);
    }

    /* ===== Kapanış =====
       Bolum basligi ustte, iletisim kanallari altta dort sutunlu kunye paneli. */
    .dc-contact {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border: 1px solid var(--dc-line);
        border-radius: var(--dc-radius);
        background: var(--dc-panel);
        overflow: hidden;
    }

    .dc-ch {
        display: block;
        padding: var(--space-5);
        border-right: 1px solid var(--dc-hair);
        transition: background-color 0.16s ease;
    }

    .dc-contact .dc-ch:last-child {
        border-right: none;
    }

    a.dc-ch:hover {
        background: color-mix(in srgb, var(--primary) 5%, transparent);
    }

    .dc-ch-key {
        display: flex;
        align-items: center;
        gap: 9px;
        margin-bottom: 11px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--text-gray);
    }

    .dc-ch-key::before {
        content: '';
        flex-shrink: 0;
        width: 14px;
        height: 2px;
        background: var(--dc-accent);
    }

    .dc-ch b {
        display: block;
        margin-bottom: 5px;
        font-size: var(--text-base);
        font-weight: 700;
        line-height: 1.35;
        letter-spacing: -0.015em;
        color: var(--text-primary);
        overflow-wrap: anywhere;
    }

    a.dc-ch:hover b {
        color: var(--dc-accent);
    }

    .dc-ch span {
        display: block;
        font-size: var(--text-xs);
        line-height: 1.55;
        color: var(--text-muted);
    }

    /* baslik bloğu */
    .dc-contact-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: var(--space-4) var(--space-6);
        margin-bottom: clamp(20px, 2vw, 30px);
    }

    .dc-contact-head>div {
        max-width: 620px;
    }

    .dc-contact-head h2 {
        font-size: clamp(20px, 1.2vw + 15px, 28px);
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.02em;
        color: var(--text-primary);
        text-wrap: balance;
    }

    .dc-contact-head p {
        margin-top: var(--space-3);
        font-size: var(--text-md);
        line-height: 1.65;
        color: var(--text-muted);
    }


    /* ===== Duyarlılık ===== */
    @media (max-width: 1100px) {
        .dc-facility {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dc-lines {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 1100px) {
        .dc-contact {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dc-contact .dc-ch:nth-child(2) {
            border-right: none;
        }

        .dc-contact .dc-ch:nth-child(-n+2) {
            border-bottom: 1px solid var(--dc-hair);
        }
    }

    @media (max-width: 640px) {
        .dc-contact,
        .dc-facility,
        .dc-lines {
            grid-template-columns: 1fr;
        }

        .dc-contact .dc-ch {
            border-right: none;
            border-bottom: 1px solid var(--dc-hair);
        }

        .dc-contact .dc-ch:last-child {
            border-bottom: none;
        }
    }
</style>

<div class="dc">

    <!-- Hero -->
    <?php
    /* Kabinet koridoru: tek kacis noktali basit perspektif projeksiyonu.
       lerp(nokta, kacis noktasi, t) ile her kabinetin ekran koordinati bulunur. */
    $vpX = 800.0;
    $vpY = 112.0;
    $lerp = static fn(float $a, float $b, float $t): float => round($a + ($b - $a) * $t, 1);

    // Sol ve sag sirada, koridora bakan yuzun on kenarlari
    $sides = [
        ['bx' => -190.0, 'tx' => -190.0],  // sol
        ['bx' => 1790.0, 'tx' => 1790.0], // sag
    ];
    $frontBottomY = 420.0;
    $frontTopY = -200.0;

    // Derinlige gore sikisan t degerleri
    $depths = [];
    for ($i = 0; $i <= 9; $i++) {
        $depths[] = $i / ($i + 2.3);
    }
    ?>

    <section class="dc-hero">
        <div class="container dc-hero-copy">
            <span class="dc-label"><?= Lang::e('anasayfa.rozet', 'Veri Merkezi') ?></span>
            <h1><?= Lang::t('anasayfa.baslik', 'Kabinetten buluta, <em>tek tesis</em> altında') ?></h1>
            <p class="dc-hero-lead">
                <?= Lang::e('anasayfa.aciklama', 'Donanımınızı kabinetimize yerleştirin ya da hazır sunucularımızı kiralayın. Yedekli enerji, kontrollü iklimlendirme ve kesintisiz izleme tesisin standardıdır.') ?>
            </p>

            <div class="dc-actions">
                <a href="iletisim.php" class="btn btn-primary">
                    <?= Lang::e('ortak.teklif-alin', 'Teklif Alın') ?> <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#hizmetler" class="btn btn-outline"><?= Lang::e('ortak.hizmetleri-inceleyin', 'Hizmetleri İnceleyin') ?></a>
            </div>
        </div>

        <!-- Kabinet koridoru -->
        <div class="dc-corridor" aria-hidden="true">
            <svg viewBox="0 0 1600 330" preserveAspectRatio="xMidYMax slice" fill="none">
                <defs>
                    <radialGradient id="dcAisle" cx="0.5" cy="0.5" r="0.5">
                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.55" />
                        <stop offset="0.55" stop-color="var(--primary)" stop-opacity="0.14" />
                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0" />
                    </radialGradient>
                    <linearGradient id="dcFloor" x1="0" y1="1" x2="0" y2="0">
                        <stop offset="0" stop-color="var(--primary)" stop-opacity="0.02" />
                        <stop offset="1" stop-color="var(--primary)" stop-opacity="0.16" />
                    </linearGradient>
                </defs>

                <!-- zemin -->
                <path d="M0 330 L1600 330 L<?= $vpX ?> <?= $vpY ?> Z" fill="url(#dcFloor)" />

                <!-- tavan aydinlatma seridi -->
                <path d="M640 -12 L960 -12 L<?= $vpX ?> <?= $vpY ?> Z" fill="url(#dcAisle)" opacity="0.5" />

                <!-- koridor sonu isigi -->
                <ellipse cx="<?= $vpX ?>" cy="<?= $vpY ?>" rx="230" ry="120" fill="url(#dcAisle)" />

                <?php
                // Uzaktan yakina ciz ki yakindakiler ustte kalsin
                for ($i = count($depths) - 2; $i >= 0; $i--):
                    $tA = $depths[$i];
                    $tB = $tA + ($depths[$i + 1] - $tA) * 0.86; // kabinetler arasi ince bosluk
                    $fade = 1 - $tA * 0.92;

                    foreach ($sides as $s):
                        $ax1 = $lerp($s['bx'], $vpX, $tA);
                        $ay1 = $lerp($frontBottomY, $vpY, $tA);
                        $ax2 = $lerp($s['bx'], $vpX, $tB);
                        $ay2 = $lerp($frontBottomY, $vpY, $tB);
                        $tx1 = $lerp($s['tx'], $vpX, $tA);
                        $ty1 = $lerp($frontTopY, $vpY, $tA);
                        $tx2 = $lerp($s['tx'], $vpX, $tB);
                        $ty2 = $lerp($frontTopY, $vpY, $tB);
                        ?>
                        <!-- kabinet yuzu -->
                        <path d="M<?= $ax1 ?> <?= $ay1 ?> L<?= $ax2 ?> <?= $ay2 ?> L<?= $tx2 ?> <?= $ty2 ?> L<?= $tx1 ?> <?= $ty1 ?> Z"
                            fill="var(--cw-face)" fill-opacity="<?= round(0.62 * $fade, 3) ?>"
                            stroke="var(--cw-edge)" stroke-opacity="<?= round(0.9 * $fade, 3) ?>"
                            stroke-width="1" />

                        <!-- on kenar isigi -->
                        <path d="M<?= $ax1 ?> <?= $ay1 ?> L<?= $tx1 ?> <?= $ty1 ?>" stroke="var(--primary)"
                            stroke-opacity="<?= round(0.45 * $fade, 3) ?>" stroke-width="1.5" />

                        <?php if ($i < 6):
                            // yakin kabinetlerde sunucu raflari
                            $adet = 9 - $i;
                            for ($u = 1; $u <= $adet; $u++):
                                $k = $u / ($adet + 1);
                                $sx1 = round($ax1 + ($tx1 - $ax1) * $k, 1);
                                $sy1 = round($ay1 + ($ty1 - $ay1) * $k, 1);
                                $sx2 = round($ax2 + ($tx2 - $ax2) * $k, 1);
                                $sy2 = round($ay2 + ($ty2 - $ay2) * $k, 1);
                                ?>
                                <path d="M<?= $sx1 ?> <?= $sy1 ?> L<?= $sx2 ?> <?= $sy2 ?>"
                                    stroke="var(--cw-line)"
                                    stroke-opacity="<?= round(0.55 * $fade, 3) ?>" stroke-width="1" />
                                <?php if ($u % 2 === 1): ?>
                                    <circle cx="<?= round($sx1 + ($sx2 - $sx1) * 0.22, 1) ?>"
                                        cy="<?= round($sy1 + ($sy2 - $sy1) * 0.22, 1) ?>"
                                        r="<?= round(2.4 * $fade, 2) ?>" fill="#4ade80"
                                        fill-opacity="<?= round(0.9 * $fade, 3) ?>" />
                                <?php endif; ?>
                            <?php endfor;
                        endif; ?>
                    <?php endforeach;
                endfor; ?>
            </svg>
        </div>

        <!-- Olcu seridi -->
        <div class="dc-band">
            <div class="container">
                <div class="dc-band-grid">

                    <!-- Uptime: neredeyse tam yay -->
                    <div class="dc-bi">
                        <span class="dc-bi-fig" aria-hidden="true">
                            <svg viewBox="0 0 44 44">
                                <circle class="yol" cx="22" cy="22" r="16" />
                                <circle class="dolu" cx="22" cy="22" r="16" stroke-dasharray="99 100.5"
                                    transform="rotate(-90 22 22)" />
                            </svg>
                        </span>
                        <span class="dc-bi-txt">
                            <span class="dc-bi-key"><?= Lang::e('serit.calisma-suresi', 'Çalışma süresi') ?></span>
                            <b>%99,9</b><span><?= Lang::e('serit.uptime-not', 'yıllık hedef') ?></span>
                        </span>
                    </div>

                    <!-- Enerji: uc besleme + bir yedek -->
                    <div class="dc-bi">
                        <span class="dc-bi-fig" aria-hidden="true">
                            <svg viewBox="0 0 44 44">
                                <rect class="blok" x="5" y="12" width="7" height="20" rx="3" />
                                <rect class="blok" x="15" y="12" width="7" height="20" rx="3" />
                                <rect class="blok" x="25" y="12" width="7" height="20" rx="3" />
                                <rect class="yedek" x="35" y="12" width="7" height="20" rx="3" />
                            </svg>
                        </span>
                        <span class="dc-bi-txt">
                            <span class="dc-bi-key"><?= Lang::e('serit.enerji', 'Enerji') ?></span>
                            <b>N+1</b><span><?= Lang::e('serit.enerji-not', 'yedekli besleme') ?></span>
                        </span>
                    </div>

                    <!-- Iklimlendirme: 0-40 olceginde 18-24 bandi -->
                    <div class="dc-bi">
                        <span class="dc-bi-fig" aria-hidden="true">
                            <svg viewBox="0 0 44 44">
                                <path class="yol" d="M6 22h32" />
                                <path class="dolu" d="M17 22h10" stroke-width="6" />
                                <path class="yol" d="M6 16v12M38 16v12" stroke-width="2" />
                            </svg>
                        </span>
                        <span class="dc-bi-txt">
                            <span class="dc-bi-key"><?= Lang::e('serit.iklimlendirme', 'İklimlendirme') ?></span>
                            <b>18 – 24 °C</b><span><?= Lang::e('serit.iklim-not', 'koğuş sıcaklığı') ?></span>
                        </span>
                    </div>

                    <!-- Operasyon: yedi gun kesintisiz -->
                    <div class="dc-bi">
                        <span class="dc-bi-fig" aria-hidden="true">
                            <svg viewBox="0 0 44 44">
                                <path class="yol" d="M4 22h36" stroke-width="1.5" />
                                <?php for ($g = 0; $g < 7; $g++): ?>
                                    <circle class="nokta" cx="<?= 4 + $g * 6 ?>" cy="22" r="2.1" />
                                <?php endfor; ?>
                            </svg>
                        </span>
                        <span class="dc-bi-txt">
                            <span class="dc-bi-key"><?= Lang::e('serit.operasyon', 'Operasyon') ?></span>
                            <b>7/24</b><span><?= Lang::e('serit.operasyon-not', 'izleme ve müdahale') ?></span>
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Tesis yetenekleri -->
    <?php
    /* ---------- Izometrik tesis kesiti ----------
       Projeksiyon: ekran = (x - y) * cos30, (x + y) * sin30 - z
       x saga-asagi, y sola-asagi, z yukari artar.
       Cati kaldirilmis kesit: her odanin yalnizca arka iki duvari cizilir. */
    $isoS = 5.0;
    $isoOX = 569.0;
    $isoOY = 152.0;

    $iso = static function (float $x, float $y, float $z) use ($isoS, $isoOX, $isoOY): array {
        return [
            round($isoOX + ($x - $y) * 0.866 * $isoS, 1),
            round($isoOY + ($x + $y) * 0.5 * $isoS - $z * $isoS, 1),
        ];
    };
    $pt = static fn(array $p): string => $p[0] . ',' . $p[1];

    $poly = static function (array $ps, string $fill, string $stroke = '', float $sw = 0.6) use ($pt): string {
        return '<polygon points="' . implode(' ', array_map($pt, $ps)) . '" fill="' . $fill . '"'
            . ($stroke !== '' ? ' stroke="' . $stroke . '" stroke-width="' . $sw . '"' : '') . '/>';
    };

    /* Duz zemin yamasi (yalnizca ust yuz) */
    $slab = static function (float $x, float $y, float $w, float $d, float $z, string $c, string $stroke = '') use ($iso, $poly): string {
        return $poly([$iso($x, $y, $z), $iso($x + $w, $y, $z), $iso($x + $w, $y + $d, $z), $iso($x, $y + $d, $z)], $c, $stroke);
    };

    /* Hacim: on-sol yuz, on-sag yuz, ust yuz */
    $box = static function (float $x, float $y, float $w, float $d, float $h, string $c, float $z0 = 0.0, string $ustStroke = '') use ($iso, $poly): string {
        return $poly([$iso($x, $y + $d, $h), $iso($x + $w, $y + $d, $h), $iso($x + $w, $y + $d, $z0), $iso($x, $y + $d, $z0)],
                'color-mix(in srgb, ' . $c . ' 54%, #000)')
            . $poly([$iso($x + $w, $y, $h), $iso($x + $w, $y + $d, $h), $iso($x + $w, $y + $d, $z0), $iso($x + $w, $y, $z0)],
                'color-mix(in srgb, ' . $c . ' 75%, #000)')
            . $poly([$iso($x, $y, $h), $iso($x + $w, $y, $h), $iso($x + $w, $y + $d, $h), $iso($x, $y + $d, $h)], $c, $ustStroke);
    };

    /* ---------- Yerlesim ---------- */
    $W_H = 3.4;                 // duvar yuksekligi
    $W_T = 0.7;                 // duvar kalinligi
    $W_C = '#d8dee8';           // duvar
    $W_S = '#f1f5f9';           // duvar ust kenari
    $EQ = '#7c8798';            // ekipman govdesi
    $RACK = '#2b3648';          // kabinet govdesi
    $DOOR = '#1b2433';          // kabinet on kapagi

    // [x, y, w, d, zemin rengi]
    $isoOdalar = [
        'lobi' => [0, 0, 22, 14, '#e8edf4'],
        'toplanti' => [0, 15, 22, 9, '#e8edf4'],
        'noc' => [0, 25, 22, 14, '#bae6fd'],
        'ofis' => [0, 40, 22, 20, '#e8edf4'],
        'kor1' => [22, 0, 4, 60, '#f6f8fb'],
        'ups' => [26, 0, 18, 13, '#fde68a'],
        'elektrik' => [26, 14, 18, 13, '#fbcfe8'],
        'telekom' => [26, 28, 18, 11, '#ddd6fe'],
        'depo' => [26, 40, 18, 8, '#e2e8f0'],
        'yangin' => [26, 49, 18, 11, '#fecaca'],
        'kor2' => [44, 0, 6, 60, '#f6f8fb'],
        'salon1' => [50, 0, 48, 26, '#dfe5ee'],
        'tesisat' => [50, 26, 48, 5.5, '#f6f8fb'],
        'salon2' => [50, 31.5, 48, 28.5, '#dfe5ee'],
    ];

    /* Hacimler tek listede; derinlik anahtari ile ressam sirasina sokulur */
    $isoHacim = [];
    $ekle = static function (array &$L, float $x, float $y, float $w, float $d, float $h, string $c, float $z0 = 0.0, ?float $derinlik = null, string $st = ''): void {
        $L[] = [$x, $y, $w, $d, $h, $c, $z0, $derinlik ?? ($x + $w + $y + $d), $st];
    };

    // Odalarin arka iki duvari
    foreach ($isoOdalar as $o) {
        $ekle($isoHacim, $o[0] - $W_T, $o[1] - $W_T, $W_T, $o[3] + $W_T, $W_H, $W_C, 0.0, null, $W_S);
        $ekle($isoHacim, $o[0] - $W_T, $o[1] - $W_T, $o[2] + $W_T, $W_T, $W_H, $W_C, 0.0, null, $W_S);
    }
    // Bina cevresinde alcak parapet
    $ekle($isoHacim, -$W_T, 60, 98 + $W_T, $W_T, 1.5, $W_C, 0.0, null, $W_S);
    $ekle($isoHacim, 98, -$W_T, $W_T, 60 + 2 * $W_T, 1.5, $W_C, 0.0, null, $W_S);

    // Jenerator grubu: uc unite, disarida kaide uzerinde
    foreach ([7.0, 14.5, 22.0] as $gy) {
        $ekle($isoHacim, -20, $gy, 13, 5.5, 4.0, $EQ);
        $ekle($isoHacim, -18, $gy + 1.2, 3, 3, 4.9, '#94a3b8');   // egzoz bacasi
    }
    // UPS: uc kabin + akku sirasi
    foreach ([28.0, 34.0, 40.0] as $ux) {
        $ekle($isoHacim, $ux, 2.5, 4, 7.5, 3.9, $EQ);
    }
    $ekle($isoHacim, 28, 10.6, 16, 1.7, 2.3, $EQ);
    // Elektrik: pano sirasi + trafo
    foreach ([28.0, 34.0, 40.0] as $ex) {
        $ekle($isoHacim, $ex, 16, 4.5, 2.5, 4.3, $EQ);
    }
    $ekle($isoHacim, 28, 22, 15, 3, 3.1, $EQ);
    // Telekom: iki rack sirasi
    $ekle($isoHacim, 28, 30, 14, 2.5, 3.9, $RACK);
    $ekle($isoHacim, 28, 34.5, 14, 2.5, 3.9, $RACK);
    // Depo: raflar
    foreach ([41.5, 44.5] as $dy) {
        $ekle($isoHacim, 28, $dy, 15, 1.8, 2.6, $EQ);
    }
    // Yangin: gaz tupu bataryasi
    for ($i = 0; $i < 8; $i++) {
        $ekle($isoHacim, 28 + $i * 1.9, 51, 1.4, 1.4, 3.4, $EQ);
    }
    // NOC izleme masalari
    $ekle($isoHacim, 3, 29, 15, 2.2, 1.4, $EQ);
    $ekle($isoHacim, 3, 33.5, 15, 2.2, 1.4, $EQ);
    // Toplanti masasi, lobi bankosu, ofis masalari
    $ekle($isoHacim, 5, 18, 12, 3.5, 1.3, $EQ);
    $ekle($isoHacim, 4, 6, 10, 2.2, 1.7, $EQ);
    foreach ([43.0, 48.0, 53.0] as $oy) {
        $ekle($isoHacim, 4, $oy, 6, 3, 1.3, $EQ);
        $ekle($isoHacim, 13, $oy, 6, 3, 1.3, $EQ);
    }
    // CRAC uniteleri: Salon-1 siralarindan sonra, Salon-2 siralarindan once
    foreach ([51.0, 63.0, 75.0, 87.0] as $cx) {
        $ekle($isoHacim, $cx, 26.8, 9, 3.2, 5.6, '#0891b2', 0.0, 122.0);
    }

    /* Kabinet siralari: her sirada tek tek kabinet */
    $isoSira = [];
    $KAB = 3.0;
    $SIRA_W = 39.0;
    foreach ([3.0, 9.5, 16.0, 21.5, 34.5, 41.0, 47.5, 54.0] as $ry) {
        $isoSira[] = [54.5, $ry, $SIRA_W, 3.2, 4.3];
        $ekle($isoHacim, 54.5, $ry, $SIRA_W, 3.2, 4.3, $RACK);
    }

    /* Soguk koridor kapatma: sira ciftleri arasindaki koridorun ustu */
    $isoKapatma = [
        [54.5, 6.2, $SIRA_W, 3.3],
        [54.5, 19.2, $SIRA_W, 2.3],
        [54.5, 37.7, $SIRA_W, 3.3],
        [54.5, 50.7, $SIRA_W, 3.3],
    ];

    usort($isoHacim, static fn(array $a, array $b): int => $a[7] <=> $b[7]);

    /* Cikma etiketleri: [taraf, etiket y, hedef(x,y,z), buyuk harf baslik, anahtar]
       Baslik metni elle buyuk harfli; CSS text-transform Turkce 'i' harfini bozuyor. */
    $isoLabels = [
        ['sol', 128, [-13, 16, 4.0], 'JENERATÖR GRUBU', 'jenerator'],
        ['sol', 184, [9, 7, 1.7], 'LOBİ VE GİRİŞ', 'lobi'],
        ['sol', 240, [11, 19, 1.3], 'TOPLANTI ODALARI', 'toplanti'],
        ['sol', 296, [11, 31, 1.4], 'NOC', 'noc'],
        ['sol', 352, [11, 50, 1.3], 'OFİSLER', 'ofis'],
        ['sol', 408, [35, 32, 3.9], 'TELEKOM ODASI', 'telekom'],
        ['sol', 464, [35, 43, 2.6], 'DEPO', 'depo'],
        ['sol', 520, [33, 52, 3.4], 'YANGIN SÖNDÜRME', 'yangin'],
        ['sag', 140, [36, 6, 3.9], 'UPS', 'ups'],
        ['sag', 218, [36, 18, 4.3], 'ELEKTRİK', 'elektrik'],
        ['sag', 296, [79, 28, 5.6], 'CRAC ÜNİTELERİ', 'crac'],
        ['sag', 386, [74, 12, 4.3], 'SALON-1', 'salon1'],
        ['sag', 480, [74, 47, 4.3], 'SALON-2', 'salon2'],
    ];
    $isoIlk = 'jenerator';   // acilista secili bolge
    ?>

    <section class="dc-sec">
        <div class="container">
            <div class="dc-head">
                <span class="dc-label"><?= Lang::e('etiket.tesis', 'Tesis') ?></span>
                <h2><?= Lang::e('tesis.baslik', 'Veri merkezi perspektifi') ?></h2>
                <p><?= Lang::e('tesis.aciklama', 'Tesisin bölüm yerleşimi: enerji, soğutma, telekom ve kabinet salonları. Bölüm başlığına tıklayarak ayrıntısını görebilirsiniz.') ?></p>
            </div>
        </div>

        <!-- Panel container disinda: ekran genisligine yayilir -->
        <div class="dc-iso">
                <svg viewBox="0 88 1360 492" fill="none" role="img"
                    aria-label="Veri merkezi izometrik kesiti: jeneratör grubu, lobi, toplantı odaları, NOC, ofisler, UPS, elektrik odası, telekom odası, depo, yangın söndürme, CRAC üniteleri ve iki kabinet salonu">
                    <defs>
                        <filter id="dcIsoShadow" x="-25%" y="-25%" width="150%" height="160%">
                            <feDropShadow dx="0" dy="22" stdDeviation="18" flood-color="#000"
                                flood-opacity="0.42" />
                        </filter>
                    </defs>

                    <!-- doseme plakalari (golgeli) -->
                    <g filter="url(#dcIsoShadow)">
                        <?= $box(-22, 4, 17, 22, 0.6, '#64748b') ?>
                        <?= $box(-1.4, -1.4, 100.8, 62.8, 0.75, '#aab4c2') ?>
                    </g>

                    <!-- oda zeminleri -->
                    <?php foreach ($isoOdalar as $o): ?>
                        <?= $slab($o[0], $o[1], $o[2], $o[3], 0.75, $o[4]) ?>
                    <?php endforeach; ?>

                    <!-- salonlarda yukseltilmis doseme karolari -->
                    <?php
                    foreach ([$isoOdalar['salon1'], $isoOdalar['salon2']] as $sl):
                        for ($gx = 3.0; $gx < $sl[2]; $gx += 3.0):
                            $a = $iso($sl[0] + $gx, $sl[1], 0.76);
                            $b = $iso($sl[0] + $gx, $sl[1] + $sl[3], 0.76);
                            ?>
                            <path d="M<?= $pt($a) ?> L<?= $pt($b) ?>" stroke="#94a3b8" stroke-opacity="0.45"
                                stroke-width="0.6" />
                        <?php endfor;
                        for ($gy = 3.0; $gy < $sl[3]; $gy += 3.0):
                            $a = $iso($sl[0], $sl[1] + $gy, 0.76);
                            $b = $iso($sl[0] + $sl[2], $sl[1] + $gy, 0.76);
                            ?>
                            <path d="M<?= $pt($a) ?> L<?= $pt($b) ?>" stroke="#94a3b8" stroke-opacity="0.45"
                                stroke-width="0.6" />
                        <?php endfor;
                    endforeach; ?>

                    <!-- duvarlar, ekipman ve kabinet siralari -->
                    <?php foreach ($isoHacim as $v): ?>
                        <?= $box($v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[8]) ?>
                    <?php endforeach; ?>

                    <!-- tek tek kabinetler: ust yuz + on kapak -->
                    <?php foreach ($isoSira as [$rx, $ry, $rw, $rd, $rh]):
                        for ($k = 0; $k + $KAB <= $rw + 0.01; $k += $KAB):
                            $x0 = $rx + $k + 0.25;
                            $x1 = $rx + $k + $KAB - 0.25;
                            echo $poly([
                                $iso($x0, $ry + 0.25, $rh), $iso($x1, $ry + 0.25, $rh),
                                $iso($x1, $ry + $rd - 0.25, $rh), $iso($x0, $ry + $rd - 0.25, $rh),
                            ], '#3b4759');
                            echo $poly([
                                $iso($x0, $ry + $rd, $rh - 0.35), $iso($x1, $ry + $rd, $rh - 0.35),
                                $iso($x1, $ry + $rd, 0.95), $iso($x0, $ry + $rd, 0.95),
                            ], $DOOR);
                            $l1 = $iso($x1 - 0.35, $ry + $rd, $rh - 0.7);
                            $l2 = $iso($x1 - 0.35, $ry + $rd, $rh - 1.6);
                            ?>
                            <path d="M<?= $pt($l1) ?> L<?= $pt($l2) ?>" stroke="#4ade80" stroke-opacity="0.85"
                                stroke-width="0.8" />
                        <?php endfor;
                    endforeach; ?>

                    <!-- soguk koridor kapatma panelleri -->
                    <?php foreach ($isoKapatma as [$kx, $ky, $kw, $kd]): ?>
                        <?= $slab($kx, $ky, $kw, $kd, 4.6, 'rgba(226,232,240,0.22)', 'rgba(226,232,240,0.5)') ?>
                    <?php endforeach; ?>

                    <!-- ---------- tiklanabilir cikma etiketleri ---------- -->
                    <g class="dc-iso-labels">
                        <?php foreach ($isoLabels as [$taraf, $ly, $hedef, $baslik, $anahtar]):
                            [$tx, $ty] = $iso($hedef[0], $hedef[1], $hedef[2]);
                            $solda = $taraf === 'sol';
                            $lx = $solda ? 28 : 1332;
                            $ex = $solda ? 184 : 1078;
                            $alt = $isoDetay[$anahtar]['ozet'];
                            ?>
                            <g class="dc-iso-cal" data-zone="<?= $anahtar ?>" data-side="<?= $taraf ?>"
                                role="button" tabindex="0" aria-controls="dcPop" aria-expanded="false">
                                <title><?= htmlspecialchars($isoDetay[$anahtar]['baslik']) ?> hakkında bilgi</title>
                                <rect class="dc-iso-hit" x="<?= $solda ? 22 : 1122 ?>" y="<?= $ly - 19 ?>" width="212"
                                    height="42" rx="8" fill="transparent" />
                                <text class="dc-iso-t" x="<?= $lx ?>" y="<?= $ly ?>"
                                    text-anchor="<?= $solda ? 'start' : 'end' ?>"><?= htmlspecialchars($baslik) ?></text>
                                <text class="dc-iso-s" x="<?= $lx ?>" y="<?= $ly + 16 ?>"
                                    text-anchor="<?= $solda ? 'start' : 'end' ?>"><?= htmlspecialchars($alt) ?></text>
                                <path class="dc-iso-l" d="M<?= $lx ?> <?= $ly + 25 ?> H<?= $ex ?> L<?= $tx ?> <?= $ty ?>" />
                                <circle class="dc-iso-d" cx="<?= $tx ?>" cy="<?= $ty ?>" r="3.2" />
                            </g>
                        <?php endforeach; ?>
                    </g>
                </svg>

                <!-- Etiketin yaninda acilan ayrinti baloncugu (genis ekran) -->
                <div class="dc-pop" id="dcPop" role="dialog" aria-labelledby="dcPopBaslik" hidden>
                    <button type="button" class="dc-pop-x" aria-label="Kapat">&times;</button>
                    <div class="dc-pop-body dc-det is-on"></div>
                </div>

                <!-- Dar ekranda cikma etiketleri okunmaz; secim cubugu devreye girer -->
                <div class="container">
                <div class="dc-det-nav">
                    <?php foreach ($isoDetay as $k => $d): ?>
                        <button type="button" class="dc-det-chip<?= $k === $isoIlk ? ' is-on' : '' ?>"
                            data-zone="<?= $k ?>"><?= htmlspecialchars(Lang::tv('tesis', $d['baslik'])) ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Secili bolumun ayrintisi -->
                <div class="dc-det-wrap">
                    <?php foreach ($isoDetay as $k => $d): ?>
                        <article class="dc-det<?= $k === $isoIlk ? ' is-on' : '' ?>" id="dcDet-<?= $k ?>"
                            data-zone="<?= $k ?>">
                            <header>
                                <b><?= htmlspecialchars(Lang::tv('tesis', $d['baslik'])) ?></b>
                                <span><?= htmlspecialchars(Lang::tv('tesis', $d['ozet'])) ?></span>
                            </header>
                            <p><?= htmlspecialchars(Lang::tv('tesis', $d['metin'])) ?></p>
                            <ul>
                                <?php foreach ($d['maddeler'] as $m): ?>
                                    <li><?= htmlspecialchars(Lang::tv('tesis', $m)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
                </div>
        </div>
    </section>

    <script>
        /* Tesis semasi: etikete tiklayinca yanindaki baloncukta ayrinti acilir.
           Dar ekranda baloncuk yerine cip cubugu + alttaki panel calisir. */
        (function () {
            var kok = document.querySelector('.dc-iso');
            if (!kok) return;

            var etiketler = kok.querySelectorAll('.dc-iso-cal');
            var cipler = kok.querySelectorAll('.dc-det-chip');
            var detaylar = kok.querySelectorAll('.dc-det-wrap .dc-det');
            var pop = kok.querySelector('#dcPop');
            var popGovde = pop.querySelector('.dc-pop-body');
            var popKapat = pop.querySelector('.dc-pop-x');
            var acikAnahtar = null;
            var acikGrup = null;

            function kaynak(anahtar) {
                for (var i = 0; i < detaylar.length; i++) {
                    if (detaylar[i].getAttribute('data-zone') === anahtar) return detaylar[i];
                }
                return null;
            }

            function isaretle(anahtar) {
                [].forEach.call(etiketler, function (e) {
                    var acik = e.getAttribute('data-zone') === anahtar;
                    e.classList.toggle('is-on', acik);
                    e.setAttribute('aria-expanded', acik ? 'true' : 'false');
                });
                [].forEach.call(cipler, function (c) {
                    c.classList.toggle('is-on', c.getAttribute('data-zone') === anahtar);
                });
                [].forEach.call(detaylar, function (d) {
                    d.classList.toggle('is-on', d.getAttribute('data-zone') === anahtar);
                });
            }

            function kapat() {
                acikAnahtar = null;
                acikGrup = null;
                pop.hidden = true;
                [].forEach.call(etiketler, function (e) {
                    e.classList.remove('is-on');
                    e.setAttribute('aria-expanded', 'false');
                });
            }

            /* Baloncugu etiketin yanina yerlestir: sol etiketlerde saga, sag etiketlerde sola */
            function yerlestir(g) {
                var pr = kok.getBoundingClientRect();
                var hedef = g.querySelector('.dc-iso-hit') || g;
                var lr = hedef.getBoundingClientRect();
                var solda = g.getAttribute('data-side') === 'sol';

                pop.classList.toggle('is-left', solda);
                pop.classList.toggle('is-right', !solda);
                pop.hidden = false;

                var pw = pop.offsetWidth;
                var ph = pop.offsetHeight;
                var x = solda ? (lr.right - pr.left + 14) : (lr.left - pr.left - pw - 14);
                var y = lr.top - pr.top - 12;

                x = Math.max(8, Math.min(x, pr.width - pw - 8));
                y = Math.max(8, Math.min(y, Math.max(8, pr.height - ph - 8)));

                pop.style.left = Math.round(x) + 'px';
                pop.style.top = Math.round(y) + 'px';
            }

            function ac(g) {
                var anahtar = g.getAttribute('data-zone');
                if (anahtar === acikAnahtar) { kapat(); return; }

                var kay = kaynak(anahtar);
                if (!kay) return;

                popGovde.innerHTML = kay.innerHTML;
                var bas = popGovde.querySelector('b');
                if (bas) bas.id = 'dcPopBaslik';

                acikAnahtar = anahtar;
                acikGrup = g;
                isaretle(anahtar);
                yerlestir(g);
            }

            [].forEach.call(etiketler, function (g) {
                g.addEventListener('click', function () { ac(g); });
                g.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter' || ev.key === ' ' || ev.key === 'Spacebar') {
                        ev.preventDefault();
                        ac(g);
                    }
                });
            });

            /* Dar ekran: cipler alttaki paneli degistirir */
            [].forEach.call(cipler, function (c) {
                c.addEventListener('click', function () {
                    isaretle(c.getAttribute('data-zone'));
                });
            });

            popKapat.addEventListener('click', kapat);

            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' && !pop.hidden) kapat();
            });

            document.addEventListener('click', function (ev) {
                if (pop.hidden) return;
                if (pop.contains(ev.target)) return;
                if (ev.target.closest && ev.target.closest('.dc-iso-cal')) return;
                kapat();
            });

            window.addEventListener('resize', function () {
                if (pop.hidden || !acikGrup) return;
                if (window.innerWidth <= 992) { kapat(); return; }
                yerlestir(acikGrup);
            });
        })();
    </script>

    <!-- Hizmet hatları -->
    <section class="dc-sec is-tint" id="hizmetler">
        <div class="container">
            <div class="dc-head">
                <span class="dc-label"><?= Lang::e('etiket.hizmet-hatlari', 'Hizmet Hatları') ?></span>
                <h2><?= Lang::e('hatlar.baslik', 'Donanımınız bizde, ya da bizim donanımımız sizde') ?></h2>
                <p><?= Lang::e('hatlar.aciklama', 'Kabinet barındırmadan sanal sunucuya kadar dört ana hizmet hattı.') ?></p>
            </div>

            <div class="dc-lines">
                <?php foreach ($dcLines as $l):
                    // Satista paketi olmayan hat teklife yonlendirilir
                    $teklif = empty($l['fiyat']);
                    ?>
                    <a href="<?= htmlspecialchars($l['url']) ?>" class="dc-line">
                        <span class="dc-line-no"><?= htmlspecialchars($l['no']) ?></span>
                        <h3><?= htmlspecialchars(Lang::tv('hizmet', $l['name'])) ?></h3>
                        <p><?= htmlspecialchars(Lang::tv('hizmet', $l['desc'])) ?></p>

                        <dl>
                            <?php foreach ($l['kunye'] as [$etiket, $deger]): ?>
                                <div>
                                    <dt><?= htmlspecialchars(Lang::tv('kunye', $etiket)) ?></dt>
                                    <dd><?= htmlspecialchars(Lang::tv('kunye', $deger)) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>

                        <div class="dc-line-price">
                            <?php if ($teklif): ?>
                                <b><?= Lang::e('ortak.teklife-gore', 'Teklife göre') ?></b>
                                <?= Lang::e('ortak.kapasiteye-gore', 'Kapasiteye göre fiyatlanır') ?>
                            <?php else: ?>
                                <b>₺<?= number_format((float) $l['fiyat'], 0, ',', '.') ?></b>
                                <?= Lang::e('ortak.aylik-baslangic', 'aylık başlangıç · KDV hariç') ?>
                            <?php endif; ?>
                        </div>

                        <span class="dc-line-go">
                            <?= $teklif ? Lang::e('ortak.teklif-alin', 'Teklif Alın') : Lang::e('ortak.incele', 'İncele') ?>
                            <i class="fas fa-arrow-right"></i>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Barındırma vitrini -->
    <?php if (!empty($dcCatalog)): ?>
        <section class="dc-sec">
            <div class="container">
                <div class="dc-head">
                    <span class="dc-label"><?= Lang::e('etiket.barindirma', 'Barındırma') ?></span>
                    <h2><?= Lang::e('katalog.baslik', 'Hazır barındırma paketleri') ?></h2>
                    <p><?= Lang::e('katalog.aciklama', 'Kendi sunucunuzu yönetmek istemiyorsanız, altyapımız üzerinde hazır paketler.') ?></p>
                </div>

                <div class="dc-kat">
                    <div class="dc-kat-tabs" role="tablist">
                        <?php foreach ($dcCatalog as $i => $c): ?>
                            <button type="button" class="dc-kat-tab<?= $i === 0 ? ' is-on' : '' ?>" role="tab"
                                data-kat="<?= htmlspecialchars($c['slug']) ?>"
                                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                aria-controls="kat-<?= htmlspecialchars($c['slug']) ?>">
                                <?= htmlspecialchars(Lang::tv('hizmet', $c['name'])) ?>
                                <small><?= htmlspecialchars(Lang::tv('hizmet', $c['note'])) ?></small>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($dcCatalog as $i => $c): ?>
                        <div class="dc-kat-panel<?= $i === 0 ? ' is-on' : '' ?>" role="tabpanel"
                            id="kat-<?= htmlspecialchars($c['slug']) ?>"
                            data-kat="<?= htmlspecialchars($c['slug']) ?>">

                            <div class="dc-kat-ozet">
                                <span class="dc-kat-key"><?= Lang::e('katalog.giris-paketi', 'Giriş paketinde neler var') ?></span>
                                <ul>
                                    <?php foreach ($c['ozellikler'] as $o): ?>
                                        <li>
                                            <i class="fas fa-check"></i>
                                            <?= htmlspecialchars(Lang::tv('ozellik', $o)) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="dc-kat-fiyat">
                                <?php if ($c['paket'] !== ''): ?>
                                    <div class="paket">
                                        <?= htmlspecialchars($c['paket']) ?>
                                        <span><?= $c['adet'] ?> <?= Lang::e('katalog.paket-secenegi', 'paket seçeneği') ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="tutar">
                                    <?php if ($c['fiyat'] !== null): ?>
                                        ₺<?= number_format($c['fiyat'], 0, ',', '.') ?><small>/ay</small>
                                    <?php else: ?>
                                        <small><?= Lang::e('ortak.teklife-gore', 'Teklife göre') ?></small>
                                    <?php endif; ?>
                                </div>
                                <p class="not"><?= Lang::e('katalog.kdv', 'Aylık, KDV hariç') ?></p>

                                <a href="<?= htmlspecialchars($c['url']) ?>" class="btn btn-primary">
                                    <?= Lang::e('katalog.paketleri-gor', 'Paketleri gör') ?>
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <script>
            /* Barındırma vitrini: sekme seçimi */
            (function () {
                var kok = document.querySelector('.dc-kat');
                if (!kok) return;

                var sekmeler = kok.querySelectorAll('.dc-kat-tab');
                var paneller = kok.querySelectorAll('.dc-kat-panel');

                function sec(anahtar) {
                    [].forEach.call(sekmeler, function (s) {
                        var acik = s.getAttribute('data-kat') === anahtar;
                        s.classList.toggle('is-on', acik);
                        s.setAttribute('aria-selected', acik ? 'true' : 'false');
                    });
                    [].forEach.call(paneller, function (p) {
                        p.classList.toggle('is-on', p.getAttribute('data-kat') === anahtar);
                    });
                }

                [].forEach.call(sekmeler, function (s) {
                    s.addEventListener('click', function () {
                        sec(s.getAttribute('data-kat'));
                    });
                });
            })();
        </script>
    <?php endif; ?>

    <!-- Referanslar -->
    <?php if (!empty($dcRefs)): ?>
        <section class="dc-sec is-tint">
            <div class="container">
                <div class="dc-head">
                    <span class="dc-label"><?= Lang::e('etiket.referanslar', 'Referanslar') ?></span>
                    <h2><?= Lang::e('referans.baslik', 'Altyapımızı kullanan kurumlar') ?></h2>
                </div>

                <div class="dc-refs">
                    <?php foreach ($dcRefs as $ref): ?>
                        <a href="<?= htmlspecialchars($ref['website'] ?: '#') ?>" class="dc-ref" target="_blank"
                            rel="noopener noreferrer">
                            <?php if (!empty($ref['logo'])): ?>
                                <span class="dc-ref-logo<?= !empty($ref['dark_logo']) ? ' is-dark' : '' ?>">
                                    <img src="<?= htmlspecialchars($ref['logo']) ?>"
                                        alt="<?= htmlspecialchars($ref['name']) ?>" loading="lazy"
                                        onerror="this.closest('.dc-ref-logo').remove()">
                                </span>
                            <?php endif; ?>
                            <div>
                                <b><?= htmlspecialchars($ref['name']) ?></b>
                                <span><?= htmlspecialchars($ref['category'] ?? '') ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- İletişim -->
    <section class="dc-sec">
        <div class="container">
            <?php
            $dcTel = $companyPhone ?? '';
            $dcMail = $companyEmail ?? '';
            $dcAdres = trim((string) Settings::get('company_address', ''));
            $dcTelHref = 'tel:' . preg_replace('/[^0-9+]/', '', $dcTel);
            $dcHarita = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($dcAdres);
            ?>

            <div class="dc-contact-head">
                <div>
                    <span class="dc-label"><?= Lang::e('etiket.iletisim', 'İletişim') ?></span>
                    <h2><?= Lang::e('iletisim.baslik', 'Tesisimizi yerinde görmek ister misiniz?') ?></h2>
                    <p><?= Lang::e('iletisim.aciklama', 'Kabinet ihtiyacınızı, enerji ve bağlantı gereksinimlerinizi konuşalım. Randevu oluşturup veri merkezimizi yerinde inceleyebilirsiniz.') ?></p>
                </div>
                <a href="iletisim.php" class="btn btn-primary">
                    <?= Lang::e('iletisim.randevu', 'Randevu Talep Edin') ?>
                </a>
            </div>

            <div class="dc-contact">
                <?php if ($dcTel !== ''): ?>
                    <a href="<?= htmlspecialchars($dcTelHref) ?>" class="dc-ch">
                        <span class="dc-ch-key"><?= Lang::e('iletisim.anahtar-telefon', 'Telefon') ?></span>
                        <b dir="ltr"><?= htmlspecialchars($dcTel) ?></b>
                        <span><?= Lang::e('iletisim.satis-hatti', 'Satış hattı · hafta içi 09:00 – 18:00') ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($dcMail !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars($dcMail) ?>" class="dc-ch">
                        <span class="dc-ch-key"><?= Lang::e('iletisim.anahtar-eposta', 'E-posta') ?></span>
                        <b dir="ltr"><?= htmlspecialchars($dcMail) ?></b>
                        <span><?= Lang::e('iletisim.eposta-not', 'Teklif ve sorularınız için') ?></span>
                    </a>
                <?php endif; ?>

                <?php if ($dcAdres !== ''): ?>
                    <a href="<?= htmlspecialchars($dcHarita) ?>" class="dc-ch" target="_blank" rel="noopener noreferrer">
                        <span class="dc-ch-key"><?= Lang::e('iletisim.anahtar-adres', 'Adres') ?></span>
                        <b><?= htmlspecialchars($dcAdres) ?></b>
                        <span><?= Lang::e('iletisim.adres-not', 'Tesis konumu · haritada aç') ?></span>
                    </a>
                <?php endif; ?>

                <a href="client/tickets.php" class="dc-ch">
                    <span class="dc-ch-key"><?= Lang::e('iletisim.anahtar-destek', 'Destek') ?></span>
                    <b><?= Lang::e('iletisim.destek-talebi', 'Müşteri paneli') ?></b>
                    <span><?= Lang::e('iletisim.destek-not', '7/24 destek kaydı açabilirsiniz') ?></span>
                </a>
            </div>
        </div>
    </section>

</div>

<?php require_once __DIR__ . '/theme/includes/footer.php'; ?>
