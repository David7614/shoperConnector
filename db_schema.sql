-- Adminer 4.8.0 MySQL 5.5.5-10.3.38-MariaDB-0ubuntu0.20.04.1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DROP TABLE IF EXISTS `accesstokens`;
CREATE TABLE `accesstokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `expiry` int(55) DEFAULT NULL,
  `scope` varchar(255) DEFAULT NULL,
  `state` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx-user_config-id_user` (`id_user`),
  CONSTRAINT `accesstokens_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(55) NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(55) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `registration` datetime NOT NULL,
  `first_name` varchar(255) NOT NULL DEFAULT '',
  `lastname` varchar(255) NOT NULL DEFAULT '',
  `zip_code` varchar(55) NOT NULL DEFAULT '',
  `phone` varchar(55) DEFAULT '',
  `newsletter_frequency` varchar(55) DEFAULT NULL,
  `sms_frequency` varchar(55) DEFAULT NULL,
  `nlf_time` datetime DEFAULT NULL,
  `data_permission` varchar(55) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `server_response` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `data_hash` varchar(255) DEFAULT NULL,
  `last_modification_date` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `parameters` text NOT NULL DEFAULT '',
  `is_wholesaler` int(1) NOT NULL DEFAULT 0,
  `is_disabled` int(1) NOT NULL DEFAULT 0,
  `country` varchar(25) CHARACTER SET utf8 COLLATE utf8_polish_ci NOT NULL DEFAULT '',
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `customers_backup`;
CREATE TABLE `customers_backup` (
  `id` int(55) NOT NULL AUTO_INCREMENT,
  `customer_id` int(55) NOT NULL,
  `email` varchar(255) NOT NULL,
  `registration` datetime NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `lastname` varchar(255) NOT NULL,
  `zip_code` varchar(55) NOT NULL,
  `phone` varchar(55) DEFAULT NULL,
  `newsletter_frequency` varchar(55) DEFAULT NULL,
  `sms_frequency` varchar(55) DEFAULT NULL,
  `nlf_time` datetime DEFAULT NULL,
  `data_permission` varchar(55) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `server_response` text DEFAULT NULL,
  `error` text DEFAULT NULL,
  `data_hash` varchar(255) DEFAULT NULL,
  `last_modification_date` datetime DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `customers_test`;
CREATE TABLE `customers_test` (
  `id` int(55) NOT NULL AUTO_INCREMENT,
  `customer_id` int(55) NOT NULL,
  `email` varchar(255) NOT NULL,
  `registration` datetime NOT NULL,
  `first_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `lastname` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `zip_code` varchar(55) NOT NULL,
  `phone` varchar(55) DEFAULT NULL,
  `newsletter_frequency` varchar(55) DEFAULT NULL,
  `sms_frequency` varchar(55) DEFAULT NULL,
  `nlf_time` datetime DEFAULT NULL,
  `data_permission` varchar(55) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `data_hash` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `disabled_feeds`;
CREATE TABLE `disabled_feeds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `integration_type` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `disabled_feeds_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `integration_data`;
CREATE TABLE `integration_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(55) NOT NULL,
  `task` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `integration_data_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `magazines`;
CREATE TABLE `magazines` (
  `id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_path` varchar(255) NOT NULL,
  `location_code` varchar(255) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  KEY `user_id` (`user_id`),
  CONSTRAINT `magazines_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `created_on` datetime NOT NULL,
  `finished_on` datetime DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(55) DEFAULT NULL,
  `zip_code` varchar(55) DEFAULT NULL,
  `country_code` varchar(55) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `page` int(11) NOT NULL,
  `order_positions` text NOT NULL,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id_user_id` (`order_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


DROP TABLE IF EXISTS `positions`;
CREATE TABLE `positions` (
  `id` int(55) NOT NULL AUTO_INCREMENT,
  `product_id` int(55) NOT NULL,
  `amount` int(55) NOT NULL,
  `price` varchar(255) NOT NULL,
  `order_id` int(55) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `positions_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `product`;
CREATE TABLE `product` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(55) NOT NULL,
  `URL` varchar(550) NOT NULL,
  `TITLE` varchar(250) NOT NULL,
  `PRICE` double NOT NULL,
  `BRAND` varchar(250) NOT NULL,
  `DESCRIPTION` text NOT NULL,
  `PRICE_BEFORE_DISCOUNT` double NOT NULL DEFAULT 0,
  `PRICE_WHOLESALE` double NOT NULL DEFAULT 0,
  `PRICE_BUY` double NOT NULL DEFAULT 0,
  `IMAGE` varchar(250) NOT NULL DEFAULT '',
  `PRODUCT_LINE` varchar(250) NOT NULL,
  `CATEGORYTEXT` text NOT NULL,
  `SHOW` varchar(55) NOT NULL,
  `PARAMETERS` text NOT NULL,
  `VARIANT` text NOT NULL,
  `PRICES` text NOT NULL DEFAULT '-',
  `STOCK` int(11) NOT NULL DEFAULT 0,
  `response` text NOT NULL,
  `params_hash` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `translation` varchar(5) NOT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fixed_url` int(1) NOT NULL DEFAULT 0,
  `deleted` int(1) NOT NULL DEFAULT 0,
  `parent_id` int(1) NOT NULL DEFAULT 0,
  `variants_names` varchar(250) NOT NULL DEFAULT '',
  `variants_values` varchar(250) NOT NULL DEFAULT '',
  `from_api_page` int(11) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_access_tokens`;
CREATE TABLE `shoper_access_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(10) unsigned DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `access_token` char(50) DEFAULT NULL,
  `refresh_token` char(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `FK_access_tokens_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `shoper_attributes`;
CREATE TABLE `shoper_attributes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_attributes_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_attributes_options`;
CREATE TABLE `shoper_attributes_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_attributes_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `value` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_attributes_id` (`shoper_attributes_id`),
  CONSTRAINT `shoper_attributes_options_ibfk_1` FOREIGN KEY (`shoper_attributes_id`) REFERENCES `shoper_attributes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_billings`;
CREATE TABLE `shoper_billings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `FK_billings_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `shoper_categories`;
CREATE TABLE `shoper_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `category_id` int(11) NOT NULL,
  `order` int(11) NOT NULL,
  `root` int(11) NOT NULL,
  `in_loyalty` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_categories_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_categories_language`;
CREATE TABLE `shoper_categories_language` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_categories_id` int(11) NOT NULL,
  `translation` varchar(5) NOT NULL,
  `name` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `description_bottom` text NOT NULL,
  `active` int(11) NOT NULL,
  `isdefault` int(11) NOT NULL,
  `seo_title` varchar(250) NOT NULL,
  `seo_description` varchar(250) NOT NULL,
  `seo_keywords` varchar(250) NOT NULL,
  `permalink` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_categories_id` (`shoper_categories_id`),
  CONSTRAINT `shoper_categories_language_ibfk_1` FOREIGN KEY (`shoper_categories_id`) REFERENCES `shoper_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_metafields`;
CREATE TABLE `shoper_metafields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metafield_id` int(11) NOT NULL,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `object` varchar(10) NOT NULL,
  `key` varchar(25) NOT NULL,
  `namespace` varchar(15) NOT NULL,
  `description` varchar(250) NOT NULL,
  `type` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_metafields_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_producer`;
CREATE TABLE `shoper_producer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producer_id` int(11) NOT NULL,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `name` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_producer_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_shops`;
CREATE TABLE `shoper_shops` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `shop` varchar(128) DEFAULT NULL,
  `shop_url` varchar(512) DEFAULT NULL,
  `version` int(11) DEFAULT NULL,
  `installed` smallint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `shop` (`shop`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `shoper_status`;
CREATE TABLE `shoper_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `status_id` int(11) NOT NULL,
  `active` int(11) NOT NULL,
  `default` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `order` int(11) NOT NULL,
  `translation` varchar(5) NOT NULL,
  `name` varchar(250) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order` (`order`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_status_ibfk_2` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_subscribers`;
CREATE TABLE `shoper_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscriber_id` int(11) NOT NULL,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `email` varchar(250) NOT NULL,
  `active` int(11) NOT NULL,
  `dateadd` datetime NOT NULL,
  `ipaddress` varchar(50) DEFAULT NULL,
  `lang_id` int(11) NOT NULL,
  `groups` varchar(250) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_subscribers_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_subscriptions`;
CREATE TABLE `shoper_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(10) unsigned NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`),
  CONSTRAINT `FK_subscriptions_shops` FOREIGN KEY (`shop_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `shoper_user_address`;
CREATE TABLE `shoper_user_address` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `address_book_id` int(11) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_name` varchar(250) NOT NULL,
  `company_name` varchar(250) NOT NULL,
  `pesel` varchar(25) NOT NULL,
  `firstname` varchar(250) NOT NULL,
  `lastname` varchar(250) NOT NULL,
  `street_1` varchar(250) NOT NULL,
  `street_2` varchar(250) NOT NULL,
  `city` varchar(250) NOT NULL,
  `zip_code` varchar(15) NOT NULL,
  `state` varchar(15) NOT NULL,
  `country` varchar(15) NOT NULL,
  `default` int(11) NOT NULL,
  `shipping_default` int(11) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `sortkey` varchar(250) NOT NULL,
  `country_code` varchar(5) NOT NULL,
  `tax_identification_number` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_user_address_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `shoper_user_tag`;
CREATE TABLE `shoper_user_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shoper_shops_id` int(11) unsigned NOT NULL,
  `tag_id` int(11) NOT NULL,
  `name` varchar(250) NOT NULL,
  `lang_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `shoper_shops_id` (`shoper_shops_id`),
  CONSTRAINT `shoper_user_tag_ibfk_1` FOREIGN KEY (`shoper_shops_id`) REFERENCES `shoper_shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `fronturl` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `register_date` datetime NOT NULL,
  `active` tinyint(3) NOT NULL DEFAULT 1,
  `registerToken` varchar(255) NOT NULL,
  `client_id` varchar(255) NOT NULL,
  `client_secret` varchar(255) NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `shop_type` varchar(10) NOT NULL,
  `user_type` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `user_config`;
CREATE TABLE `user_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx-user_config-id_user` (`id_user`),
  CONSTRAINT `fk-user_config-id_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `user_data`;
CREATE TABLE `user_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `value` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_data_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_polish_ci;


DROP TABLE IF EXISTS `xml_feed_queue`;
CREATE TABLE `xml_feed_queue` (
  `id` int(55) NOT NULL AUTO_INCREMENT,
  `integrated` int(55) NOT NULL,
  `next_integration_date` datetime NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `finished_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `integration_type` varchar(255) NOT NULL,
  `current_integrate_user` int(55) NOT NULL,
  `page` int(55) NOT NULL,
  `max_page` int(55) NOT NULL,
  `parameters` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `current_integrate_user` (`current_integrate_user`),
  CONSTRAINT `xml_feed_queue_ibfk_1` FOREIGN KEY (`current_integrate_user`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


-- 2023-07-25 06:55:03
