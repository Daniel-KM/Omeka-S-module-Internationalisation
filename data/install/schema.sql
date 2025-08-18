CREATE TABLE `site_page_relation` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `page_id` INT NOT NULL,
    `related_page_id` INT NOT NULL,
    INDEX `IDX_B0B528C2C4663E4` (`page_id`),
    INDEX `IDX_B0B528C2335FA941` (`related_page_id`),
    UNIQUE INDEX `idx_site_page_relation` (`page_id`, `related_page_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `translating` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `lang` VARCHAR(8) NOT NULL,
    `string` LONGTEXT NOT NULL,
    `translation` LONGTEXT NOT NULL,
    INDEX `idx_translating_lang_string` (`lang`, `string`(190)),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `site_page_relation` ADD CONSTRAINT `FK_B0B528C2C4663E4` FOREIGN KEY (`page_id`) REFERENCES `site_page` (`id`) ON DELETE CASCADE;
ALTER TABLE `site_page_relation` ADD CONSTRAINT `FK_B0B528C2335FA941` FOREIGN KEY (`related_page_id`) REFERENCES `site_page` (`id`) ON DELETE CASCADE;
