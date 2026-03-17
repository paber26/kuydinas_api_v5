ALTER TABLE `users`
ADD COLUMN `coin_balance` BIGINT NOT NULL DEFAULT 0 AFTER `password`;

CREATE TABLE `wallet_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `amount` BIGINT NOT NULL,
    `balance_before` BIGINT NOT NULL,
    `balance_after` BIGINT NOT NULL,
    `reference_type` VARCHAR(100) NULL,
    `reference_id` BIGINT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `wallet_transactions_user_id_index` (`user_id`),
    KEY `wallet_transactions_reference_index` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_wallet_transactions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
