<?php
/**
 * WHMVM - Sepet İşlemleri
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Settings.php';

session_name(SESSION_NAME);
session_start();

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$productId = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

// Sepet başlat
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ürünü sepete ekle
if ($action === 'add' && $productId) {
    $product = Database::fetch("SELECT * FROM products WHERE id = ? AND is_active = 1", [$productId]);
    
    if ($product) {
        $billingCycle = $_GET['billing_cycle'] ?? $_POST['billing_cycle'] ?? 'monthly';
        $configOptions = $_POST['config_options'] ?? [];
        
        // Fiyat hesapla
        $price = match($billingCycle) {
            'monthly' => (float)($product['price_monthly'] ?? 0),
            'quarterly' => (float)($product['price_quarterly'] ?? 0),
            'semiannually' => (float)($product['price_semiannually'] ?? 0),
            'annually' => (float)($product['price_annually'] ?? 0),
            default => (float)($product['price_monthly'] ?? 0)
        };
        
        // Yapılandırma seçeneklerini işle
        $configTotal = 0;
        $selectedOptions = [];
        
        if (!empty($configOptions)) {
            foreach ($configOptions as $optionId => $valueId) {
                try {
                    $optValue = Database::fetch(
                        "SELECT cov.*, co.name as option_name FROM config_option_values cov 
                         JOIN config_options co ON cov.option_id = co.id 
                         WHERE cov.id = ?",
                        [(int)$valueId]
                    );
                    if ($optValue) {
                        $optPrice = match($billingCycle) {
                            'monthly' => (float)($optValue['price_monthly'] ?? 0),
                            'annually' => (float)($optValue['price_annually'] ?? 0),
                            default => (float)($optValue['price_monthly'] ?? 0)
                        };
                        $configTotal += $optPrice;
                        $selectedOptions[] = [
                            'option_id' => $optionId,
                            'value_id' => $valueId,
                            'option_name' => $optValue['option_name'],
                            'value_name' => $optValue['name'],
                            'price' => $optPrice
                        ];
                    }
                } catch (Exception $e) {}
            }
        }
        
        $cartItem = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_type' => $product['type'],
            'billing_cycle' => $billingCycle,
            'price' => $price,
            'setup_fee' => (float)($product['setup_fee'] ?? 0),
            'config_options' => $selectedOptions,
            'config_total' => $configTotal,
            'total' => $price + $configTotal + (float)($product['setup_fee'] ?? 0),
            'added_at' => time()
        ];
        
        // Aynı ürün varsa güncelle, yoksa ekle
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product['id'] && $item['billing_cycle'] == $billingCycle) {
                $_SESSION['cart'][$key] = $cartItem;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = $cartItem;
        }
        
        // Sepet sayfasına yönlendir
        header('Location: /cart.php');
        exit;
    }
}

// Ürünü sepetten kaldır
if ($action === 'remove') {
    $index = (int)($_GET['index'] ?? -1);
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex
    }
    header('Location: checkout.php');
    exit;
}

// Sepeti temizle
if ($action === 'clear') {
    $_SESSION['cart'] = [];
    header('Location: order.php');
    exit;
}

// Varsayılan - yapılandırma sayfasına yönlendir
if ($productId && $action !== 'add') {
    header('Location: order-configure.php?id=' . $productId);
    exit;
}

// Aksi halde checkout'a git
header('Location: checkout.php');
exit;

