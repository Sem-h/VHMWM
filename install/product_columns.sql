-- Products tablosuna eksik sütunları ekle
ALTER TABLE products ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) DEFAULT 0;
ALTER TABLE products ADD COLUMN IF NOT EXISTS order_priority INT DEFAULT 0;

