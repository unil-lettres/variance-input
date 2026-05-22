-- MySQL dump 10.13  Distrib 8.4.4, for Linux (x86_64)
--
-- Host: localhost    Database: variance
-- ------------------------------------------------------
-- Server version	8.4.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `authors`
--

DROP TABLE IF EXISTS `authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `folder` varchar(45) NOT NULL,
  `order` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authors`
--

LOCK TABLES `authors` WRITE;
/*!40000 ALTER TABLE `authors` DISABLE KEYS */;
INSERT INTO `authors` VALUES (1,'Anatole France','anatole_france',NULL);
/*!40000 ALTER TABLE `authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chapters`
--

DROP TABLE IF EXISTS `chapters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chapters` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `folder` varchar(45) DEFAULT NULL,
  `level` varchar(120) DEFAULT NULL,
  `label_source` varchar(250) DEFAULT NULL,
  `label_target` varchar(250) DEFAULT NULL,
  `chapter_parent` int DEFAULT NULL,
  `start_line_source` varchar(6) DEFAULT NULL,
  `start_line_target` varchar(6) DEFAULT NULL,
  `id_tome_source` tinyint DEFAULT '0',
  `id_tome_target` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chapters`
--

LOCK TABLES `chapters` WRITE;
/*!40000 ALTER TABLE `chapters` DISABLE KEYS */;
/*!40000 ALTER TABLE `chapters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comparisons`
--

DROP TABLE IF EXISTS `comparisons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comparisons` (
  `source_id` int NOT NULL,
  `target_id` int NOT NULL,
  `folder` varchar(45) NOT NULL,
  `number` float DEFAULT NULL,
  `prefix_label` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`target_id`,`source_id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`),
  KEY `fk_versions_has_target_idx` (`target_id`),
  KEY `fk_versions_has_source_idx` (`source_id`),
  CONSTRAINT `fk_versions_has_versions_versions1` FOREIGN KEY (`source_id`) REFERENCES `versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_versions_has_versions_versions2` FOREIGN KEY (`target_id`) REFERENCES `versions` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comparisons`
--

LOCK TABLES `comparisons` WRITE;
/*!40000 ALTER TABLE `comparisons` DISABLE KEYS */;
INSERT INTO `comparisons` VALUES (19,1,'0_1csb-1csb',NULL,NULL),(21,1,'0_2csb-1csb',NULL,NULL),(23,1,'0_3csb-1csb',NULL,NULL),(1,2,'1csb-2csb',NULL,NULL),(2,15,'2csb-3csb',NULL,NULL);
/*!40000 ALTER TABLE `comparisons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `versions`
--

DROP TABLE IF EXISTS `versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `work_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `folder` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_work_version_folder` (`folder`,`work_id`),
  KEY `fk_versions_works_idx` (`work_id`),
  CONSTRAINT `fk_versions_works` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versions`
--

LOCK TABLES `versions` WRITE;
/*!40000 ALTER TABLE `versions` DISABLE KEYS */;
INSERT INTO `versions` VALUES (1,1,'Calmann-Lévy (1881)','1csb'),(2,1,'Calmann-Lévy (1903)','2csb'),(15,1,'Calmann-Lévy (1922)','3csb'),(19,1,'« La Fée » (1879-1880)','0_1csb'),(21,1,'« Une très-vieille histoire d’amour » (1880)','0_2csb'),(23,1,'« Le crime de Sylvestre Bonnard » (1880-1881)','0_3csb');
/*!40000 ALTER TABLE `versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `works`
--

DROP TABLE IF EXISTS `works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `works` (
  `id` int NOT NULL AUTO_INCREMENT,
  `author_id` int NOT NULL,
  `title` varchar(80) NOT NULL,
  `folder` varchar(45) NOT NULL,
  `desc` text NOT NULL,
  `image_url` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folder_UNIQUE` (`folder`),
  KEY `fk_works_authors1_idx` (`author_id`),
  CONSTRAINT `fk_works_authors1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `works`
--

LOCK TABLES `works` WRITE;
/*!40000 ALTER TABLE `works` DISABLE KEYS */;
INSERT INTO `works` VALUES (1,1,'Le crime de Sylvestre Bonnard','le_crime_de_sylvestre_bonnard','D’abord paru en revue entre 1879 et 1881, puis en volume chez Calmann-Lévy en 1881, <I>Le Crime de Sylvestre Bonnard</I> offrit à Anatole France sa première notoriété. Le roman souffrait pourtant de graves défauts de composition narrative et de cohérence chronologique. L’auteur retravailla son récit une première fois en vue de l’édition de 1903, mais celle-ci resta imparfaite, et une nouvelle révision intervint en vue de la dernière édition, qui parut en 1922, quelques mois après que l\'écrivain eut reçu le prix Nobel.',NULL);
/*!40000 ALTER TABLE `works` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-02-28 12:48:10
