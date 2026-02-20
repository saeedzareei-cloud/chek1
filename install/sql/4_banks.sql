-- =====================================================
-- جدول بانک‌ها (banks)
-- =====================================================
CREATE TABLE IF NOT EXISTS banks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

-- (اختیاری) اگر می‌خواهید در جدول checks به جای bank_name از bank_id استفاده کنید:
-- ALTER TABLE checks ADD COLUMN bank_id INT NULL AFTER bank_name;
-- ALTER TABLE checks ADD CONSTRAINT fk_checks_bank FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL;
