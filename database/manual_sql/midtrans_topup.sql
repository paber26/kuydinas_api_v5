ALTER TABLE `users`
ADD COLUMN `coin_balance` BIGINT NOT NULL DEFAULT 0;

CREATE TABLE `topup_packages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `coin_amount` BIGINT NOT NULL,
    `bonus_coin` BIGINT NOT NULL DEFAULT 0,
    `price` BIGINT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `topup_transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `topup_package_id` BIGINT UNSIGNED NOT NULL,
    `order_id` VARCHAR(100) NOT NULL,
    `gateway` VARCHAR(50) NOT NULL DEFAULT 'midtrans',
    `snap_token` VARCHAR(255) NULL,
    `redirect_url` TEXT NULL,
    `gross_amount` BIGINT NOT NULL,
    `coin_amount` BIGINT NOT NULL,
    `bonus_coin` BIGINT NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `transaction_status` VARCHAR(50) NULL,
    `fraud_status` VARCHAR(50) NULL,
    `payment_type` VARCHAR(50) NULL,
    `paid_at` TIMESTAMP NULL,
    `expired_at` TIMESTAMP NULL,
    `raw_response` JSON NULL,
    `raw_notification` JSON NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_topup_order_id` (`order_id`),
    KEY `topup_transactions_user_id_index` (`user_id`),
    KEY `topup_transactions_package_id_index` (`topup_package_id`),
    CONSTRAINT `fk_topup_transactions_user`
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_topup_transactions_package`
      FOREIGN KEY (`topup_package_id`) REFERENCES `topup_packages`(`id`) ON DELETE RESTRICT
);

CREATE TABLE `wallet_transactions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `amount` BIGINT NOT NULL,
    `coin_amount` BIGINT NOT NULL DEFAULT 0,
    `balance_before` BIGINT NOT NULL,
    `balance_after` BIGINT NOT NULL,
    `reference_type` VARCHAR(100) NULL,
    `reference_id` BIGINT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `wallet_transactions_user_id_index` (`user_id`),
    KEY `wallet_transactions_reference_index` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_wallet_transactions_user`
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

INSERT INTO `topup_packages` (`name`, `coin_amount`, `bonus_coin`, `price`, `is_active`)
VALUES
('Paket 1', 100, 0, 10000, 1),
('Paket 2', 250, 50, 20000, 1),
('Paket 3', 400, 120, 30000, 1),
('Paket 4', 550, 200, 40000, 1);
