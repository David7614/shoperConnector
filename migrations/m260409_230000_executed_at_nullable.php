<?php

use yii\db\Migration;

class m260409_230000_executed_at_nullable extends Migration
{
    public function up()
    {
        // xml_feed_queue: executed_at and finished_at are only set when processing starts/ends
        // Both must be changed in one statement — MySQL strict mode rejects ALTER on a table
        // that has any column with '0000-00-00 00:00:00' default
        $this->execute("ALTER TABLE `xml_feed_queue` MODIFY `executed_at` DATETIME NULL DEFAULT NULL, MODIFY `finished_at` DATETIME NULL DEFAULT NULL");

        // customers/orders: updated is auto-managed by MySQL ON UPDATE CURRENT_TIMESTAMP
        $this->execute("ALTER TABLE `customers` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE `orders` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `xml_feed_queue` MODIFY `executed_at` DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00', MODIFY `finished_at` DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00'");

        $this->execute("ALTER TABLE `customers` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE `orders` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP");
    }
}
