CREATE DATABASE IF NOT EXISTS project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE project;

CREATE TABLE IF NOT EXISTS authors (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(191) NOT NULL,
    `password` CHAR(60) NOT NULL,
    `name` VARCHAR(255),
    `slug` VARCHAR(255),
    `biography` TEXT,
    `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id`),
    CONSTRAINT `email` UNIQUE(`email`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;

CREATE TABLE IF NOT EXISTS pages (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `contents` TEXT NOT NULL,
    `menu_display` BOOLEAN NOT NULL DEFAULT 0,
    PRIMARY KEY(`id`),
    CONSTRAINT `slug` UNIQUE(`slug`),
    INDEX (`menu_display`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;

CREATE TABLE IF NOT EXISTS posts (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `contents` TEXT NOT NULL,
    `author_id` INT UNSIGNED NOT NULL,
    `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(`id`),
    CONSTRAINT `slug` UNIQUE(`slug`),
    FOREIGN KEY(`author_id`) REFERENCES authors(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=dynamic;

CREATE TABLE IF NOT EXISTS sessions (
    `id` CHAR(86) NOT NULL,
    `auth_token` CHAR(32) NOT NULL,
    `author_id` INT UNSIGNED,
    `ip_address` INT UNSIGNED NOT NULL,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(`id`),
    FOREIGN KEY(`author_id`) REFERENCES authors(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `colophon` TEXT DEFAULT NULL,
    `pretty_links` BOOLEAN NOT NULL DEFAULT 0,
    `posts_per_page` INT UNSIGNED NOT NULL DEFAULT 5,
    PRIMARY KEY(`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO settings
    (`title`, `description`, `colophon`)
VALUES
    ('CIT336 Project', 'A very simple blogging application.', 'Nam commodo ultricies mollis. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Duis at aliquet justo, ac egestas eros.');