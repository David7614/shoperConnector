<?php

use yii\db\Migration;

class m260328_120000_utf8mb4_fix extends Migration
{
    private $tables = [
        'shoper_categories_language',
        'xml_feed_queue',
    ];

    public function safeUp()
    {
        $this->execute("ALTER TABLE `xml_feed_queue` MODIFY `executed_at` DATETIME NULL DEFAULT NULL, MODIFY `finished_at` DATETIME NULL DEFAULT NULL");
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
