<?php

use yii\db\Migration;

/**
 * Tabela customers trzyma pelna baze klientow (bez deleteAll w kazdym cyklu),
 * wiec duplikaty z rownoleglych workerow zostawalyby na stale - unikalny klucz je blokuje.
 * Feed klientow filtruje po (user_id, updated) przy trybie inkrementalnym.
 */
class m261020_000000_customers_unique extends Migration
{
    public function up(): void
    {
        $this->execute("DELETE c1 FROM `customers` c1
            JOIN `customers` c2
              ON c1.user_id = c2.user_id
             AND c1.customer_id = c2.customer_id
             AND c1.id < c2.id");

        $this->execute("ALTER TABLE `customers`
            DROP INDEX `idx_customers_user_customer_id`,
            ADD UNIQUE KEY `uniq_customers_user_customer_id` (`user_id`, `customer_id`),
            ADD KEY `idx_customers_user_updated` (`user_id`, `updated`)");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `customers`
            DROP INDEX `uniq_customers_user_customer_id`,
            DROP INDEX `idx_customers_user_updated`,
            ADD KEY `idx_customers_user_customer_id` (`user_id`, `customer_id`)");
    }
}
