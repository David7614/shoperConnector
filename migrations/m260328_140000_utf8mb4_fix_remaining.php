<?php

use yii\db\Migration;

class m260328_140000_utf8mb4_fix_remaining extends Migration
{
    private $tables = [
        'shoper_metafields',
        'shoper_attributes',
        'shoper_attributes_options',
        'shoper_user_address',
        'shoper_producer',
        'shoper_user_tag',
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
