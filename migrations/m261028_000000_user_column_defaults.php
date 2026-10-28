<?php

use yii\db\Migration;

/**
 * Dodawanie admina w admin/admins zapisuje User bez register_date, registerToken, uuid i shop_type.
 * Kolumny byly NOT NULL bez defaultu, wiec insert konczyl sie bledem 1364.
 */
class m261028_000000_user_column_defaults extends Migration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `user`
            MODIFY `register_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY `registerToken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            MODIFY `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            MODIFY `client_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            MODIFY `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            MODIFY `shop_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `user`
            MODIFY `register_date` datetime NOT NULL,
            MODIFY `registerToken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY `client_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            MODIFY `shop_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL");
    }
}
