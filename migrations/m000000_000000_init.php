<?php

use yii\db\Migration;

/**
 * Full database schema baseline.
 * Source of truth: db_schema.sql (generated 2026-04-23).
 * All subsequent changes live in separate migrations.
 */
class m000000_000000_init extends Migration
{
    private array $tables = [
        'positions',
        'shoper_categories_language',
        'shoper_attributes_options',
        'shoper_access_tokens',
        'shoper_attributes',
        'shoper_billings',
        'shoper_categories',
        'shoper_currencies_list',
        'shoper_languages_list',
        'shoper_metafields',
        'shoper_producer',
        'shoper_status',
        'shoper_subscribers',
        'shoper_subscriptions',
        'shoper_user_address',
        'shoper_user_tag',
        'shoper_shops',
        'accesstokens',
        'customers',
        'disabled_feeds',
        'integration_data',
        'magazines',
        'orders',
        'ordersv2',
        'product',
        'queue_execution_log',
        'xml_feed_queue',
        'user_config',
        'user_data',
        'user',
        'app_config',
        'customers_backup',
        'customers_test',
    ];

    public function up(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');

        $this->execute("CREATE TABLE `accesstokens` (
            `id` int NOT NULL AUTO_INCREMENT,
            `id_user` int DEFAULT NULL,
            `access_token` mediumtext COLLATE utf8mb4_unicode_ci,
            `refresh_token` mediumtext COLLATE utf8mb4_unicode_ci,
            `expiry` int DEFAULT NULL,
            `scope` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `state` mediumtext COLLATE utf8mb4_unicode_ci,
            PRIMARY KEY (`id`),
            KEY `idx-user_config-id_user` (`id_user`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `app_config` (
            `id` int NOT NULL AUTO_INCREMENT,
            `key` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` varchar(155) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `customers` (
            `id` int NOT NULL AUTO_INCREMENT,
            `customer_id` varchar(65) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `registration` datetime NOT NULL,
            `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `lastname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `zip_code` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `phone` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT '',
            `newsletter_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `sms_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `nlf_time` datetime DEFAULT NULL,
            `data_permission` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `tags` mediumtext COLLATE utf8mb4_unicode_ci,
            `server_response` mediumtext COLLATE utf8mb4_unicode_ci,
            `error` mediumtext COLLATE utf8mb4_unicode_ci,
            `data_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `last_modification_date` datetime DEFAULT NULL,
            `user_id` int NOT NULL,
            `page` int NOT NULL,
            `parameters` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `is_wholesaler` int NOT NULL DEFAULT '0',
            `is_disabled` int NOT NULL DEFAULT '0',
            `country` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `verify_email` tinyint(1) DEFAULT NULL,
            `active_in_shops` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`),
            KEY `user_id` (`user_id`),
            KEY `email` (`email`),
            KEY `newsletter_frequency` (`newsletter_frequency`),
            KEY `sms_frequency` (`sms_frequency`),
            KEY `idx_customers_user_customer_id` (`user_id`, `customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `customers_backup` (
            `id` int NOT NULL AUTO_INCREMENT,
            `customer_id` int NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `registration` datetime NOT NULL,
            `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `lastname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `zip_code` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
            `phone` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `newsletter_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `sms_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `nlf_time` datetime DEFAULT NULL,
            `data_permission` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `tags` mediumtext COLLATE utf8mb4_unicode_ci,
            `server_response` mediumtext COLLATE utf8mb4_unicode_ci,
            `error` mediumtext COLLATE utf8mb4_unicode_ci,
            `data_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `last_modification_date` datetime DEFAULT NULL,
            `user_id` int NOT NULL,
            `page` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `customers_test` (
            `id` int NOT NULL AUTO_INCREMENT,
            `customer_id` int NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `registration` datetime NOT NULL,
            `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `lastname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `zip_code` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
            `phone` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `newsletter_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `sms_frequency` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `nlf_time` datetime DEFAULT NULL,
            `data_permission` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `tags` mediumtext COLLATE utf8mb4_unicode_ci,
            `data_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user_id` int NOT NULL,
            `page` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `disabled_feeds` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `integration_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `integration_data` (
            `id` int NOT NULL AUTO_INCREMENT,
            `customer_id` int NOT NULL,
            `task` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `magazines` (
            `id` int NOT NULL AUTO_INCREMENT,
            `location_id` int NOT NULL,
            `parent_id` int NOT NULL,
            `location_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `location_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `location_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `stock_id` int NOT NULL,
            `user_id` int NOT NULL,
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `orders` (
            `id` int NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `customer_id` int DEFAULT NULL,
            `created_on` datetime NOT NULL,
            `finished_on` datetime DEFAULT NULL,
            `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `phone` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `zip_code` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `country_code` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user_id` int NOT NULL,
            `page` int NOT NULL,
            `order_positions` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id_user_id` (`order_id`, `user_id`),
            KEY `user_id` (`user_id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `ordersv2` (
            `id` int NOT NULL AUTO_INCREMENT,
            `order_id` int NOT NULL,
            `customer_id` int DEFAULT NULL,
            `created_on` datetime NOT NULL,
            `finished_on` datetime DEFAULT NULL,
            `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `phone` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `zip_code` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `country_code` varchar(55) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `user_id` int NOT NULL,
            `page` int NOT NULL,
            `order_positions` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id_user_id` (`order_id`, `user_id`),
            KEY `order_id` (`order_id`),
            KEY `user_id` (`user_id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `positions` (
            `id` int NOT NULL AUTO_INCREMENT,
            `product_id` int NOT NULL,
            `amount` int NOT NULL,
            `price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `order_id` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `product` (
            `ID` int NOT NULL AUTO_INCREMENT,
            `PRODUCT_ID` int NOT NULL,
            `URL` varchar(550) COLLATE utf8mb4_unicode_ci NOT NULL,
            `TITLE` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `PRICE` double NOT NULL,
            `BRAND` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `DESCRIPTION` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `PRICE_BEFORE_DISCOUNT` double NOT NULL DEFAULT '0',
            `PRICE_WHOLESALE` double NOT NULL DEFAULT '0',
            `PRICE_BUY` double NOT NULL DEFAULT '0',
            `IMAGE` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `PRODUCT_LINE` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `CATEGORYTEXT` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `SHOW` varchar(55) COLLATE utf8mb4_unicode_ci NOT NULL,
            `PARAMETERS` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `VARIANT` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `PRICES` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `STOCK` int NOT NULL DEFAULT '0',
            `response` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `params_hash` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `user_id` int NOT NULL,
            `translation` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `fixed_url` int NOT NULL DEFAULT '0',
            `deleted` int NOT NULL DEFAULT '0',
            `parent_id` int NOT NULL DEFAULT '0',
            `variants_names` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `variants_values` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `from_api_page` int DEFAULT NULL,
            PRIMARY KEY (`ID`),
            KEY `user_id` (`user_id`),
            KEY `PRODUCT_ID` (`PRODUCT_ID`),
            KEY `URL` (`URL`),
            KEY `idx_product_user_product_id` (`user_id`, `PRODUCT_ID`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `queue_execution_log` (
            `id` int NOT NULL AUTO_INCREMENT,
            `queue_id` int NOT NULL,
            `user_id` int NOT NULL,
            `integration_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `phase` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
            `execution_time` float NOT NULL,
            `page` int NOT NULL DEFAULT '0',
            `max_page` int NOT NULL DEFAULT '0',
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_qel_created_at` (`created_at`),
            KEY `idx_qel_type_user` (`integration_type`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_shops` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `shop` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `shop_url` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `version` int DEFAULT NULL,
            `installed` smallint DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `shop` (`shop`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_access_tokens` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `shop_id` int UNSIGNED DEFAULT NULL,
            `expires_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            `access_token` char(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `refresh_token` char(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `shop_id` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_attributes` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `attribute_id` int NOT NULL,
            `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`),
            KEY `attribute_id` (`attribute_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_attributes_options` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_attributes_id` int NOT NULL,
            `option_id` int NOT NULL,
            `value` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_attributes_id` (`shoper_attributes_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_billings` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `shop_id` int UNSIGNED DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `shop_id` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_categories` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `category_id` int NOT NULL,
            `order` int NOT NULL,
            `root` int NOT NULL,
            `in_loyalty` int NOT NULL,
            `parent_id` int DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_categories_language` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_categories_id` int NOT NULL,
            `translation` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `description_bottom` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            `active` int NOT NULL,
            `isdefault` int NOT NULL,
            `seo_title` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `seo_description` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `seo_keywords` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `permalink` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_categories_id` (`shoper_categories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_currencies_list` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `currency_id` int NOT NULL,
            `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `rate` float NOT NULL,
            `active` int NOT NULL,
            `order` int NOT NULL,
            `default` int NOT NULL,
            `rate_sync` float NOT NULL,
            `rate_date` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_languages_list` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `locale` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
            `currency_id` int NOT NULL,
            `active` int NOT NULL,
            `order` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_metafields` (
            `id` int NOT NULL AUTO_INCREMENT,
            `metafield_id` int NOT NULL,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `object` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
            `key` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
            `namespace` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
            `description` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `type` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_producer` (
            `id` int NOT NULL AUTO_INCREMENT,
            `producer_id` int NOT NULL,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_status` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `status_id` int NOT NULL,
            `active` int NOT NULL,
            `default` int NOT NULL,
            `type` int NOT NULL,
            `order` int NOT NULL,
            `translation` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `message` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `order` (`order`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_subscribers` (
            `id` int NOT NULL AUTO_INCREMENT,
            `subscriber_id` int NOT NULL,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `email` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `active` int NOT NULL,
            `used` int DEFAULT '0',
            `dateadd` datetime NOT NULL,
            `ipaddress` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `lang_id` int NOT NULL,
            `groups` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `sync_flag` int NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`),
            KEY `email` (`email`),
            KEY `active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_subscriptions` (
            `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
            `shop_id` int UNSIGNED NOT NULL,
            `expires_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `shop_id` (`shop_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_user_address` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `address_book_id` int UNSIGNED NOT NULL,
            `user_id` int NOT NULL,
            `address_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `company_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `pesel` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
            `firstname` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `lastname` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `street_1` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `street_2` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `city` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `zip_code` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
            `state` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
            `country` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
            `default` int NOT NULL,
            `shipping_default` int NOT NULL,
            `phone` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
            `sortkey` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `country_code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
            `tax_identification_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `shoper_user_tag` (
            `id` int NOT NULL AUTO_INCREMENT,
            `shoper_shops_id` int UNSIGNED NOT NULL,
            `tag_id` int NOT NULL,
            `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            `lang_id` int NOT NULL,
            PRIMARY KEY (`id`),
            KEY `shoper_shops_id` (`shoper_shops_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `user` (
            `id` int NOT NULL AUTO_INCREMENT,
            `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `fronturl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `register_date` datetime NOT NULL,
            `active` tinyint NOT NULL DEFAULT '1',
            `registerToken` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `shop_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
            `user_type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `user_config` (
            `id` int NOT NULL AUTO_INCREMENT,
            `id_user` int DEFAULT NULL,
            `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx-user_config-id_user` (`id_user`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `user_data` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
            `value` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->execute("CREATE TABLE `xml_feed_queue` (
            `id` int NOT NULL AUTO_INCREMENT,
            `integrated` int NOT NULL,
            `next_integration_date` datetime NOT NULL,
            `executed_at` datetime DEFAULT NULL,
            `finished_at` datetime DEFAULT NULL,
            `integration_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `current_integrate_user` int NOT NULL,
            `page` int NOT NULL,
            `max_page` int NOT NULL,
            `parameters` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (`id`),
            KEY `current_integrate_user` (`current_integrate_user`),
            KEY `idx_feed_queue_main` (`integration_type`, `integrated`, `next_integration_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Foreign key constraints
        $this->execute("ALTER TABLE `accesstokens`
            ADD CONSTRAINT `accesstokens_ibfk_1`
            FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `customers`
            ADD CONSTRAINT `customers_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `disabled_feeds`
            ADD CONSTRAINT `disabled_feeds_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `integration_data`
            ADD CONSTRAINT `integration_data_ibfk_3`
            FOREIGN KEY (`customer_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `magazines`
            ADD CONSTRAINT `magazines_ibfk_2`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `orders`
            ADD CONSTRAINT `orders_ibfk_2`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `ordersv2`
            ADD CONSTRAINT `ordersv2_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `positions`
            ADD CONSTRAINT `positions_ibfk_2`
            FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `product`
            ADD CONSTRAINT `product_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_access_tokens`
            ADD CONSTRAINT `FK_access_tokens_shops`
            FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_attributes`
            ADD CONSTRAINT `shoper_attributes_ibfk_2`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `shoper_attributes_options`
            ADD CONSTRAINT `shoper_attributes_options_ibfk_2`
            FOREIGN KEY (`shoper_attributes_id`) REFERENCES `shoper_attributes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `shoper_billings`
            ADD CONSTRAINT `FK_billings_shops`
            FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_categories`
            ADD CONSTRAINT `shoper_categories_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_categories_language`
            ADD CONSTRAINT `shoper_categories_language_ibfk_1`
            FOREIGN KEY (`shoper_categories_id`) REFERENCES `shoper_categories` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_currencies_list`
            ADD CONSTRAINT `shoper_currencies_list_ibfk_2`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `shoper_languages_list`
            ADD CONSTRAINT `shoper_languages_list_ibfk_2`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute("ALTER TABLE `shoper_metafields`
            ADD CONSTRAINT `shoper_metafields_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_producer`
            ADD CONSTRAINT `shoper_producer_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_status`
            ADD CONSTRAINT `shoper_status_ibfk_2`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_subscribers`
            ADD CONSTRAINT `shoper_subscribers_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_subscriptions`
            ADD CONSTRAINT `FK_subscriptions_shops`
            FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_user_address`
            ADD CONSTRAINT `shoper_user_address_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `shoper_user_tag`
            ADD CONSTRAINT `shoper_user_tag_ibfk_1`
            FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `user_config`
            ADD CONSTRAINT `fk-user_config-id_user`
            FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `user_data`
            ADD CONSTRAINT `user_data_ibfk_1`
            FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE");

        $this->execute("ALTER TABLE `xml_feed_queue`
            ADD CONSTRAINT `xml_feed_queue_ibfk_1`
            FOREIGN KEY (`current_integrate_user`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");

        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->tables as $table) {
            $this->dropTable($table);
        }
        $this->execute('SET FOREIGN_KEY_CHECKS=1');
    }
}
