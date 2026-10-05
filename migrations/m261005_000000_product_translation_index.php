<?php

use yii\db\Migration;

/**
 * Feed produktowy moze byc ograniczony do jednego jezyka (user_config.shoper_feed_language),
 * wiec batchByPrimaryKey() filtruje po (user_id, translation) i stronicuje po ID.
 * Bez tego indeksu MySQL dokladal filesort przy kazdej partii.
 */
class m261005_000000_product_translation_index extends Migration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `product`
            ADD KEY `idx_product_user_translation_id` (`user_id`, `translation`, `ID`)");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `product`
            DROP INDEX `idx_product_user_translation_id`");
    }
}
