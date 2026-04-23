<?php

use yii\db\Migration;

class m260319_191000_customers_user_customer_id_index extends Migration
{
    public function safeUp()
    {
        $this->execute("ALTER TABLE `customers` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $this->createIndex(
            'idx_customers_user_customer_id',
            'customers',
            ['user_id', 'customer_id']
        );
    }

    public function safeDown()
    {
        $this->dropIndex('idx_customers_user_customer_id', 'customers');
    }
}
