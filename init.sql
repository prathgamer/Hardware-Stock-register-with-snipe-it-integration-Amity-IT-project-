CREATE DATABASE IF NOT EXISTS hardware_db;
USE hardware_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `personal_snipeit_token` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `snipeit_url` varchar(255) DEFAULT '',
  `snipeit_token` text DEFAULT NULL,
  `low_stock_threshold` int(11) DEFAULT 5,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Stock Table
CREATE TABLE IF NOT EXISTS `stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `page_no` varchar(50) DEFAULT '',
  `initial_stock` int(11) DEFAULT 0,
  `in_qty` int(11) DEFAULT 0,
  `out_qty` int(11) DEFAULT 0,
  `current_stock` int(11) DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `inventory_type` varchar(50) DEFAULT 'hardware',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Employees Table
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_id` varchar(100) NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `form_no` varchar(100) DEFAULT '',
  `item_name` varchar(255) NOT NULL,
  `in_out` varchar(10) NOT NULL,
  `qty` int(11) NOT NULL,
  `emp_id` varchar(100) DEFAULT '',
  `from_to` varchar(255) DEFAULT '',
  `item_details` text DEFAULT NULL,
  `grn_number` varchar(100) DEFAULT '',
  `stock_entry` varchar(100) DEFAULT '',
  `image_file` varchar(255) DEFAULT '',
  `tag_no` varchar(100) DEFAULT '',
  `serial_no` varchar(100) DEFAULT '',
  `purpose` varchar(255) DEFAULT '',
  `user_id` int(11) NOT NULL,
  `inventory_type` varchar(50) DEFAULT 'hardware',
  `snipeit_asset_id` int(11) DEFAULT NULL,
  `snipeit_user_id` int(11) DEFAULT NULL,
  `snipeit_synced` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;