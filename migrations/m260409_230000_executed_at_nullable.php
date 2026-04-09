<?php

use yii\db\Migration;

class m260409_230000_executed_at_nullable extends Migration
{
    public function up()
    {
        // xml_feed_queue: executed_at and finished_at are only set when processing starts/ends
        $this->alterColumn('xml_feed_queue', 'executed_at', $this->dateTime()->null()->defaultValue(null));
        $this->alterColumn('xml_feed_queue', 'finished_at', $this->dateTime()->null()->defaultValue(null));

        // customers/orders: updated is auto-managed by MySQL ON UPDATE CURRENT_TIMESTAMP
        $this->execute("ALTER TABLE `customers` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE `orders` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    public function down()
    {
        $this->alterColumn('xml_feed_queue', 'executed_at', $this->dateTime()->notNull()->defaultValue('2000-01-01 00:00:00'));
        $this->alterColumn('xml_feed_queue', 'finished_at', $this->dateTime()->notNull()->defaultValue('2000-01-01 00:00:00'));

        $this->execute("ALTER TABLE `customers` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP");
        $this->execute("ALTER TABLE `orders` MODIFY `updated` TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00' ON UPDATE CURRENT_TIMESTAMP");
    }
}
