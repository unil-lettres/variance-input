-- phpMyAdmin SQL Dump
-- version 4.9.6
-- https://www.phpmyadmin.net/
--
-- Hôte : nnsk.myd.infomaniak.com
-- Généré le :  ven. 14 juil. 2023 à 11:14
-- Version du serveur :  5.6.49-log
-- Version de PHP :  7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `nnsk_variancetest`
--

-- --------------------------------------------------------

--
-- Structure de la table `authors`
--

CREATE TABLE `authors` (
  `id` int(11) NOT NULL,
  `name` varchar(45) NOT NULL,
  `folder` varchar(45) NOT NULL,
  `order` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `chapters`
--

CREATE TABLE `chapters` (
  `id` int(11) UNSIGNED NOT NULL,
  `folder` varchar(45) DEFAULT NULL,
  `level` varchar(120) DEFAULT NULL,
  `label_source` varchar(250) DEFAULT NULL,
  `label_target` varchar(250) DEFAULT NULL,
  `chapter_parent` int(11) DEFAULT NULL,
  `start_line_source` varchar(6) DEFAULT NULL,
  `start_line_target` varchar(6) DEFAULT NULL,
  `id_tome_source` tinyint(4) DEFAULT '0',
  `id_tome_target` tinyint(4) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `comparisons`
--

CREATE TABLE `comparisons` (
  `source_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `folder` varchar(45) NOT NULL,
  `number` float DEFAULT NULL,
  `prefix_label` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `versions`
--

CREATE TABLE `versions` (
  `id` int(11) NOT NULL,
  `work_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `folder` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `works`
--

CREATE TABLE `works` (
  `id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `title` varchar(80) NOT NULL,
  `folder` varchar(45) NOT NULL,
  `desc` text NOT NULL,
  `image_url` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folder_UNIQUE` (`folder`);

--
-- Index pour la table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level` (`level`);

--
-- Index pour la table `comparisons`
--
ALTER TABLE `comparisons`
  ADD PRIMARY KEY (`target_id`,`source_id`),
  ADD UNIQUE KEY `folder_UNIQUE` (`folder`),
  ADD KEY `fk_versions_has_target_idx` (`target_id`),
  ADD KEY `fk_versions_has_source_idx` (`source_id`);

--
-- Index pour la table `versions`
--
ALTER TABLE `versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_work_version_folder` (`folder`,`work_id`),
  ADD KEY `fk_versions_works_idx` (`work_id`);

--
-- Index pour la table `works`
--
ALTER TABLE `works`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folder_UNIQUE` (`folder`),
  ADD KEY `fk_works_authors1_idx` (`author_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `authors`
--
ALTER TABLE `authors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT pour la table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=814;

--
-- AUTO_INCREMENT pour la table `versions`
--
ALTER TABLE `versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=399;

--
-- AUTO_INCREMENT pour la table `works`
--
ALTER TABLE `works`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `comparisons`
--
ALTER TABLE `comparisons`
  ADD CONSTRAINT `fk_versions_has_versions_versions1` FOREIGN KEY (`source_id`) REFERENCES `versions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_versions_has_versions_versions2` FOREIGN KEY (`target_id`) REFERENCES `versions` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

--
-- Contraintes pour la table `versions`
--
ALTER TABLE `versions`
  ADD CONSTRAINT `fk_versions_works` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON UPDATE CASCADE;

--
-- Contraintes pour la table `works`
--
ALTER TABLE `works`
  ADD CONSTRAINT `fk_works_authors1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
