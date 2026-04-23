<?php

use yii\db\Migration;

/**
 * Schema improvements identified 2026-04-23:
 * 1. product: UNIQUE composite (user_id, PRODUCT_ID, translation) for findOne lookups
 * 2. integration_data: UNIQUE composite (customer_id, task) for upsert pattern
 * 3. product.IMAGE: extend varchar(250) → varchar(500) to avoid URL truncation
 */
class m260423_000000_schema_improvements extends Migration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `product`
            ADD UNIQUE KEY `idx_product_user_product_translation` (`user_id`, `PRODUCT_ID`, `translation`)");

        $this->execute("ALTER TABLE `integration_data`
            ADD UNIQUE KEY `idx_integration_data_customer_task` (`customer_id`, `task`)");

        $this->execute("ALTER TABLE `product`
            MODIFY COLUMN `IMAGE` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `product`
            DROP INDEX `idx_product_user_product_translation`");

        $this->execute("ALTER TABLE `integration_data`
            DROP INDEX `idx_integration_data_customer_task`");

        $this->execute("ALTER TABLE `product`
            MODIFY COLUMN `IMAGE` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
    }
}
