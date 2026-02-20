-- =====================================================
-- افزودن ستون‌های وضعیت واقعی چک‌ها (در صورت نیاز)
-- توجه: در MySQL قدیمی IF NOT EXISTS پشتیبانی نمی‌شود.
-- =====================================================
ALTER TABLE checks ADD COLUMN is_received TINYINT(1) NOT NULL DEFAULT 0 AFTER description;
ALTER TABLE checks ADD COLUMN received_date DATE NULL AFTER is_received;
