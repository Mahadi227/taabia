-- Payments table schema
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'GHS',
  `payment_method` varchar(50) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `gateway_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `status` (`status`),
  KEY `payment_method` (`payment_method`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add payment-related columns to orders table if they don't exist
ALTER TABLE `orders` 
ADD COLUMN IF NOT EXISTS `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending' AFTER `status`,
ADD COLUMN IF NOT EXISTS `payment_method` varchar(50) DEFAULT NULL AFTER `payment_status`,
ADD COLUMN IF NOT EXISTS `payment_reference` varchar(255) DEFAULT NULL AFTER `payment_method`,
ADD COLUMN IF NOT EXISTS `payment_date` timestamp NULL DEFAULT NULL AFTER `payment_reference`;

-- Add payment-related columns to users table if they don't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL AFTER `email`,
ADD COLUMN IF NOT EXISTS `country` varchar(2) DEFAULT 'GH' AFTER `phone`,
ADD COLUMN IF NOT EXISTS `currency_preference` varchar(3) DEFAULT 'GHS' AFTER `country`;

-- Add commission-related columns to order_items table
ALTER TABLE `order_items` 
ADD COLUMN IF NOT EXISTS `gross_revenue` decimal(10,2) GENERATED ALWAYS AS (`unit_price` * `quantity`) STORED AFTER `total_price`,
ADD COLUMN IF NOT EXISTS `platform_commission_rate` decimal(5,2) NOT NULL DEFAULT 20.00 AFTER `gross_revenue`,
ADD COLUMN IF NOT EXISTS `platform_commission_amount` decimal(10,2) GENERATED ALWAYS AS (`gross_revenue` * `platform_commission_rate` / 100) STORED AFTER `platform_commission_rate`,
ADD COLUMN IF NOT EXISTS `vendor_revenue` decimal(10,2) GENERATED ALWAYS AS (`gross_revenue` * (100 - `platform_commission_rate`) / 100) STORED AFTER `platform_commission_amount`,
ADD COLUMN IF NOT EXISTS `instructor_id` int(11) DEFAULT NULL AFTER `vendor_revenue`,
ADD COLUMN IF NOT EXISTS `vendor_id` int(11) DEFAULT NULL AFTER `instructor_id`,
ADD COLUMN IF NOT EXISTS `commission_paid` tinyint(1) NOT NULL DEFAULT 0 AFTER `vendor_id`,
ADD COLUMN IF NOT EXISTS `commission_paid_date` timestamp NULL DEFAULT NULL AFTER `commission_paid`;

-- Add foreign keys for instructor_id and vendor_id in order_items
ALTER TABLE `order_items` 
ADD CONSTRAINT `order_items_instructor_fk` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `order_items_vendor_fk` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Create commission tracking table
CREATE TABLE IF NOT EXISTS `commission_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) NOT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `gross_revenue` decimal(10,2) NOT NULL,
  `platform_commission` decimal(10,2) NOT NULL,
  `vendor_revenue` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `transaction_type` enum('course','product') NOT NULL DEFAULT 'course',
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `payout_method` varchar(50) DEFAULT NULL,
  `payout_reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_item_id` (`order_item_id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `status` (`status`),
  KEY `transaction_type` (`transaction_type`),
  KEY `paid_at` (`paid_at`),
  CONSTRAINT `commission_transactions_order_item_fk` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_transactions_instructor_fk` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_transactions_vendor_fk` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create trigger to automatically set instructor_id for course items
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `set_instructor_id_on_insert` 
BEFORE INSERT ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.course_id IS NOT NULL THEN
        SELECT instructor_id INTO NEW.instructor_id 
        FROM courses 
        WHERE id = NEW.course_id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS `set_instructor_id_on_update` 
BEFORE UPDATE ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.course_id IS NOT NULL AND (NEW.course_id != OLD.course_id OR OLD.course_id IS NULL) THEN
        SELECT instructor_id INTO NEW.instructor_id 
        FROM courses 
        WHERE id = NEW.course_id;
    END IF;
END//

-- Create trigger to automatically set vendor_id for product items
CREATE TRIGGER IF NOT EXISTS `set_vendor_id_on_insert` 
BEFORE INSERT ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.product_id IS NOT NULL THEN
        SELECT vendor_id INTO NEW.vendor_id 
        FROM products 
        WHERE id = NEW.product_id;
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS `set_vendor_id_on_update` 
BEFORE UPDATE ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.product_id IS NOT NULL AND (NEW.product_id != OLD.product_id OR OLD.product_id IS NULL) THEN
        SELECT vendor_id INTO NEW.vendor_id 
        FROM products 
        WHERE id = NEW.product_id;
    END IF;
END//

-- Create trigger to create commission transaction when order item is created
CREATE TRIGGER IF NOT EXISTS `create_commission_transaction` 
AFTER INSERT ON `order_items`
FOR EACH ROW
BEGIN
    IF NEW.instructor_id IS NOT NULL THEN
        INSERT INTO commission_transactions (
            order_item_id, 
            instructor_id, 
            vendor_id,
            gross_revenue, 
            platform_commission, 
            vendor_revenue, 
            commission_rate,
            transaction_type
        ) VALUES (
            NEW.id,
            NEW.instructor_id,
            NULL,
            NEW.gross_revenue,
            NEW.platform_commission_amount,
            NEW.vendor_revenue,
            NEW.platform_commission_rate,
            'course'
        );
    ELSEIF NEW.vendor_id IS NOT NULL THEN
        INSERT INTO commission_transactions (
            order_item_id, 
            instructor_id, 
            vendor_id,
            gross_revenue, 
            platform_commission, 
            vendor_revenue, 
            commission_rate,
            transaction_type
        ) VALUES (
            NEW.id,
            NULL,
            NEW.vendor_id,
            NEW.gross_revenue,
            NEW.platform_commission_amount,
            NEW.vendor_revenue,
            NEW.platform_commission_rate,
            'product'
        );
    END IF;
END//

DELIMITER ;

-- Create payment webhooks log table
CREATE TABLE IF NOT EXISTS `payment_webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `gateway` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `payload` text NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `gateway` (`gateway`),
  KEY `reference` (`reference`),
  KEY `processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment transactions table for detailed tracking
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `status` varchar(50) NOT NULL,
  `gateway_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `gateway` (`gateway`),
  KEY `gateway_transaction_id` (`gateway_transaction_id`),
  CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create payment_methods table if it doesn't exist
CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `currency` varchar(3) NOT NULL DEFAULT 'GHS',
  `min_amount` decimal(10,2) NOT NULL DEFAULT 1.00,
  `max_amount` decimal(10,2) NOT NULL DEFAULT 1000000.00,
  `fee_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fee_fixed` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `enabled` (`enabled`),
  KEY `currency` (`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample payment methods configuration
INSERT INTO `payment_methods` (`name`, `code`, `gateway`, `enabled`, `currency`, `min_amount`, `max_amount`, `fee_percentage`, `fee_fixed`, `created_at`) VALUES
('Paystack', 'paystack', 'paystack', 1, 'GHS', 1.00, 1000000.00, 1.5, 0.00, NOW()),
('Flutterwave', 'flutterwave', 'flutterwave', 1, 'GHS', 1.00, 1000000.00, 1.4, 0.00, NOW()),
('MTN Mobile Money', 'mtn_momo', 'mtn_momo', 1, 'GHS', 1.00, 10000.00, 0.0, 0.00, NOW()),
('Stripe', 'stripe', 'stripe', 1, 'USD', 0.50, 100000.00, 2.9, 0.30, NOW()),
('PayPal', 'paypal', 'paypal', 1, 'USD', 1.00, 100000.00, 2.9, 0.30, NOW());

-- Create commission settings table
CREATE TABLE IF NOT EXISTS `commission_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default commission settings
INSERT INTO `commission_settings` (`setting_key`, `setting_value`, `description`) VALUES
('default_commission_rate', '20.00', 'Commission par défaut de la plateforme en pourcentage'),
('instructor_commission_rate', '20.00', 'Commission pour les instructeurs en pourcentage'),
('vendor_commission_rate', '15.00', 'Commission pour les vendeurs en pourcentage'),
('min_payout_amount', '50.00', 'Montant minimum pour les paiements'),
('payout_schedule', 'monthly', 'Fréquence des paiements (weekly, monthly, quarterly)'),
('payout_day', '15', 'Jour du mois pour les paiements mensuels'),
('auto_payout_enabled', '1', 'Activer les paiements automatiques (0=non, 1=oui)');

-- Create instructor payout accounts table
CREATE TABLE IF NOT EXISTS `instructor_payout_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `instructor_id` int(11) NOT NULL,
  `payout_method` varchar(50) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `mobile_money_number` varchar(20) DEFAULT NULL,
  `paypal_email` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `instructor_id` (`instructor_id`),
  KEY `payout_method` (`payout_method`),
  KEY `is_default` (`is_default`),
  CONSTRAINT `instructor_payout_accounts_fk` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create vendor payout accounts table
CREATE TABLE IF NOT EXISTS `vendor_payout_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_id` int(11) NOT NULL,
  `payout_method` varchar(50) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `mobile_money_number` varchar(20) DEFAULT NULL,
  `paypal_email` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `vendor_id` (`vendor_id`),
  KEY `payout_method` (`payout_method`),
  KEY `is_default` (`is_default`),
  CONSTRAINT `vendor_payout_accounts_fk` FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
