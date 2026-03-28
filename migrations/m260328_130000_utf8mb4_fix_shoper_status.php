<?php

use yii\db\Migration;

class m260328_130000_utf8mb4_fix_shoper_status extends Migration
{
    public function safeUp()
    {
        $this->execute("ALTER TABLE `shoper_status` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function safeDown()
    {
        $this->execute("ALTER TABLE `shoper_status` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    }
}
