<?php

use yii\db\Migration;

/**
 * Import klientow robi findOne per rekord po (sklep, address_book_id), (sklep, user_id, default)
 * i (sklep, subscriber_id). Bez tych indeksow kazde zapytanie skanowalo wszystkie wiersze sklepu,
 * co przy pelnym imporcie dawalo ~23 s na strone 50 rekordow.
 */
class m261020_000001_shoper_customer_lookup_indexes extends Migration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `shoper_user_address`
            ADD KEY `idx_user_address_shop_book` (`shoper_shops_id`, `address_book_id`),
            ADD KEY `idx_user_address_shop_user_default` (`shoper_shops_id`, `user_id`, `default`)");

        $this->execute("ALTER TABLE `shoper_subscribers`
            ADD KEY `idx_subscribers_shop_subscriber` (`shoper_shops_id`, `subscriber_id`)");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `shoper_user_address`
            DROP INDEX `idx_user_address_shop_book`,
            DROP INDEX `idx_user_address_shop_user_default`");

        $this->execute("ALTER TABLE `shoper_subscribers`
            DROP INDEX `idx_subscribers_shop_subscriber`");
    }
}
