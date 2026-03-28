<?php

use yii\db\Migration;

class m260328_150000_utf8mb4_fix_core_tables extends Migration
{
    private $tables = [
        'customers',
        'positions',
        'product',
        'user_config',
    ];

    public function safeUp()
    {
        foreach ($this->tables as $table) {
            $this->execute("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    public function safeDown()
    {
        foreach ($this->tables as $table) {
            $this->execute("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
        }
    }
}
