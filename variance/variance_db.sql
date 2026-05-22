-- ==========================================
--  variance-full-dump (SCHEMA ONLY)
--  No data inserts: empty/fresh DB
-- ==========================================

/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
SET TIME_ZONE='+00:00';

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS; 
SET UNIQUE_CHECKS=0;

SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS; 
SET FOREIGN_KEY_CHECKS=0;

SET @OLD_SQL_MODE=@@SQL_MODE; 
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET @OLD_SQL_NOTES=@@SQL_NOTES; 
SET SQL_NOTES=0;

-- ======================
-- 1. authors
-- ======================
DROP TABLE IF EXISTS `authors`;
CREATE TABLE `authors` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `folder` VARCHAR(45) NOT NULL,
  `order` TINYINT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- ======================
-- 2. works
-- depends on authors.id
-- ======================
DROP TABLE IF EXISTS `works`;
CREATE TABLE `works` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `author_id` INT NOT NULL,
  `title` VARCHAR(80) NOT NULL,
  `folder` VARCHAR(45) NOT NULL,
  `desc` TEXT NOT NULL,
  `image_url` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`),
  KEY `fk_works_authors1_idx` (`author_id`),
  CONSTRAINT `fk_works_authors1`
    FOREIGN KEY (`author_id`)
    REFERENCES `authors` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- ======================
-- 3. versions
-- depends on works.id
-- ======================
DROP TABLE IF EXISTS `versions`;
CREATE TABLE `versions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `work_id` INT NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `folder` VARCHAR(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_work_version_folder` (`folder`,`work_id`),
  KEY `fk_versions_works_idx` (`work_id`),
  CONSTRAINT `fk_versions_works`
    FOREIGN KEY (`work_id`)
    REFERENCES `works` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- ======================
-- 4. chapters
-- no foreign key references
-- ======================
DROP TABLE IF EXISTS `chapters`;
CREATE TABLE `chapters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder` VARCHAR(45) DEFAULT NULL,
  `level` VARCHAR(120) DEFAULT NULL,
  `label_source` VARCHAR(250) DEFAULT NULL,
  `label_target` VARCHAR(250) DEFAULT NULL,
  `chapter_parent` INT DEFAULT NULL,
  `start_line_source` VARCHAR(6) DEFAULT NULL,
  `start_line_target` VARCHAR(6) DEFAULT NULL,
  `id_tome_source` TINYINT DEFAULT '0',
  `id_tome_target` TINYINT DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- ======================
-- 5. comparisons
-- depends on versions.id
-- ======================
DROP TABLE IF EXISTS `comparisons`;
CREATE TABLE `comparisons` (
  `source_id` INT NOT NULL,
  `target_id` INT NOT NULL,
  `folder` VARCHAR(45) NOT NULL,
  `number` FLOAT DEFAULT NULL,
  `prefix_label` VARCHAR(250) DEFAULT NULL,
  PRIMARY KEY (`target_id`,`source_id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`),
  KEY `fk_versions_has_target_idx` (`target_id`),
  KEY `fk_versions_has_source_idx` (`source_id`),
  CONSTRAINT `fk_versions_has_versions_versions1`
    FOREIGN KEY (`source_id`)
    REFERENCES `versions` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_versions_has_versions_versions2`
    FOREIGN KEY (`target_id`)
    REFERENCES `versions` (`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


-- ======================
-- Final Settings
-- ======================
SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET SQL_NOTES=@OLD_SQL_NOTES;
SET TIME_ZONE=@OLD_TIME_ZONE;

-- End of variance-full-dump (schema only)
