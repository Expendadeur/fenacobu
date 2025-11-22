-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 25 oct. 2025 à 10:28
-- Version du serveur : 9.4.0
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `fenacobu1`
--

-- --------------------------------------------------------

--
-- Structure de la table `agences`
--

CREATE TABLE `agences` (
  `id_agence` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `adresse` text COLLATE utf8mb4_general_ci,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `horaires_ouverture` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `services_proposes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `agences`
--

INSERT INTO `agences` (`id_agence`, `nom`, `adresse`, `telephone`, `horaires_ouverture`, `services_proposes`, `created_at`) VALUES
(1, 'Agence Centrale Bujumbura', 'Avenue de l\'Indépendance, Bujumbura', '+257 22 123 456', NULL, NULL, '2025-10-12 10:26:13'),
(2, 'Agence Kiriri', 'Quartier Kiriri, Bujumbura', '+257 22 234 567', NULL, NULL, '2025-10-12 10:26:13'),
(3, 'Agence Gitega', 'Centre-ville, Gitega', '+257 22 345 678', NULL, NULL, '2025-10-12 10:26:13'),
(4, 'Agence Kanyosha', 'Kanyosha-MUGERE 6Avenue', '+257 66885511', NULL, NULL, '2025-10-25 04:16:19');

-- --------------------------------------------------------

--
-- Structure de la table `agents`
--

CREATE TABLE `agents` (
  `id_agent` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('Administrateur','Caissier','Conseiller') COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `id_agence` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_attempts` int DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_blocked` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `agents`
--

INSERT INTO `agents` (`id_agent`, `username`, `password`, `email`, `telephone`, `role`, `first_name`, `last_name`, `id_agence`, `is_active`, `last_login`, `failed_attempts`, `locked_until`, `password_reset_token`, `password_reset_expires`, `remember_token`, `created_at`, `updated_at`, `is_blocked`) VALUES
(6, 'Mike', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@fenacobu.bi', NULL, 'Administrateur', 'NIYUKURI', 'Mike', 1, 1, '2025-10-25 06:58:24', 0, NULL, NULL, NULL, NULL, '2025-10-13 06:24:25', '2025-10-25 06:58:24', 0),
(7, 'Janvier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'caissier@fenacobu.bi', '+257 22 234 567', 'Caissier', 'NZAMBIMANA', 'Janvier', 1, 1, '2025-10-25 08:15:09', 0, NULL, NULL, NULL, NULL, '2025-10-13 06:24:25', '2025-10-25 08:15:09', 0),
(8, 'Felicien', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'conseiller@fenacobu.bi', '+257 22 345 678', 'Conseiller', 'NTIRANYIBAGIRA', 'Felicien', NULL, 1, '2025-10-25 05:07:25', 0, NULL, NULL, NULL, NULL, '2025-10-13 06:24:25', '2025-10-25 05:07:25', 0),
(9, 'David', '$2y$10$V3/jQYBh32DCR7me1nc4wuQGTWej1R5HuaWVVN2Hd9T.dF6J9TdDi', 'davidniyokwizigira09@gmail.com', NULL, 'Caissier', 'David', 'NIYOKWIZIGIRA', 4, 1, '2025-10-25 06:50:04', 0, NULL, NULL, NULL, NULL, '2025-10-25 04:40:14', '2025-10-25 06:50:04', 0);

-- --------------------------------------------------------

--
-- Structure de la table `audit_log`
--

CREATE TABLE `audit_log` (
  `id_log` int NOT NULL,
  `id_agent` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `date_heure` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `details` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `capitaux_banque`
--

CREATE TABLE `capitaux_banque` (
  `id_capital` int NOT NULL,
  `date_mise_a_jour` date NOT NULL,
  `fonds_propres` decimal(15,2) NOT NULL COMMENT 'Capital de base + bénéfices accumulés',
  `reserves` decimal(15,2) NOT NULL COMMENT 'Réserve légale et autres',
  `liquidites` decimal(15,2) NOT NULL COMMENT 'Argent disponible immédiatement',
  `emprunts_interbancaires` decimal(15,2) DEFAULT '0.00' COMMENT 'Dettes envers autres banques'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id_client` int NOT NULL,
  `nom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `prenom` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `photo_profil` varchar(250) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adresse` text COLLATE utf8mb4_general_ci,
  `revenu_mensuel` decimal(15,2) DEFAULT '0.00',
  `score_credit` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id_client`, `nom`, `prenom`, `photo_profil`, `email`, `telephone`, `adresse`, `revenu_mensuel`, `score_credit`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'KAMARIZA', 'Deo', 'profile_68f85ca6b0acc_1761107110.jpg', 'kamariza91@gmail.com', '+257 69885511', 'KANYOSHA', 0.00, 0, 1, '2025-10-22 04:25:10', '2025-10-22 04:25:10'),
(2, 'BUKURU', 'Jean', 'profile_68fbb3fa23941_1761326074.png', 'bukuru91@gmail.com', '+257 69885811', 'KINAMA', 0.00, 0, 1, '2025-10-24 17:14:34', '2025-10-24 17:14:34'),
(3, 'KARENZO', 'Juvenal', 'profile_68fc565fc143b_1761367647.png', 'juvenal@gmail.com', '+257 64885511', 'MUHUTA MAGARA', 0.00, 0, 1, '2025-10-25 04:47:27', '2025-10-25 04:47:27');

-- --------------------------------------------------------

--
-- Structure de la table `comptes`
--

CREATE TABLE `comptes` (
  `id_compte` int NOT NULL,
  `num_compte` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `id_client` int NOT NULL,
  `id_type_compte` int NOT NULL,
  `id_agence_origine` int NOT NULL COMMENT 'Agence où le compte a été ouvert',
  `solde` decimal(15,2) DEFAULT '0.00',
  `solde_disponible` decimal(15,2) DEFAULT '0.00' COMMENT 'Solde - montants bloqués',
  `decouvert_actuel` decimal(15,2) DEFAULT '0.00',
  `statut` enum('Actif','Suspendu','Fermé','En attente') COLLATE utf8mb4_general_ci DEFAULT 'Actif',
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_derniere_operation` timestamp NULL DEFAULT NULL,
  `date_fermeture` timestamp NULL DEFAULT NULL,
  `motif_suspension` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `comptes`
--

INSERT INTO `comptes` (`id_compte`, `num_compte`, `id_client`, `id_type_compte`, `id_agence_origine`, `solde`, `solde_disponible`, `decouvert_actuel`, `statut`, `date_creation`, `date_derniere_operation`, `date_fermeture`, `motif_suspension`) VALUES
(1, 'COMPTE001', 1, 12, 1, 1245500.00, 1245500.00, 0.00, 'Actif', '2025-10-22 04:25:10', '2025-10-25 06:57:59', NULL, NULL),
(2, 'COMPTE002', 2, 11, 1, 2629000.00, 2629000.00, 0.00, 'Actif', '2025-10-24 17:14:34', '2025-10-25 05:37:42', NULL, NULL),
(3, 'COMPTE003', 3, 12, 4, 499000.00, 499000.00, 0.00, 'Actif', '2025-10-25 04:47:27', '2025-10-25 06:50:27', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `demandes_credit`
--

CREATE TABLE `demandes_credit` (
  `id_demande` int NOT NULL,
  `id_client` int NOT NULL,
  `id_type_credit` int NOT NULL,
  `id_agent` int DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `duree_mois` int NOT NULL,
  `statut` enum('En attente','En étude','Approuvé','Rejeté') COLLATE utf8mb4_general_ci DEFAULT 'En attente',
  `date_demande` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_traitement` timestamp NULL DEFAULT NULL,
  `commentaires` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dossiers_credit`
--

CREATE TABLE `dossiers_credit` (
  `id_dossier` int NOT NULL,
  `id_demande` int NOT NULL,
  `num_compte` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `date_approbation` date NOT NULL,
  `date_premier_remboursement` date NOT NULL,
  `date_dernier_remboursement` date NOT NULL,
  `montant_total_du` decimal(15,2) NOT NULL,
  `statut` enum('Actif','Clôturé','En défaut') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `echeances_credit`
--

CREATE TABLE `echeances_credit` (
  `id_echeance` int NOT NULL,
  `id_dossier` int NOT NULL,
  `numero_echeance` int NOT NULL,
  `date_echeance` date NOT NULL,
  `montant_capital` decimal(15,2) NOT NULL,
  `montant_interet` decimal(15,2) NOT NULL,
  `montant_total` decimal(15,2) NOT NULL,
  `statut` enum('A payer','Payé','En retard') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'A payer',
  `date_paiement` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `guichets`
--

CREATE TABLE `guichets` (
  `id_guichet` int NOT NULL,
  `id_agence` int NOT NULL,
  `numero_guichet` int DEFAULT NULL,
  `type_guichet` enum('Standard','Prioritaire','Entreprise','DAB','Caisse','Conseil') COLLATE utf8mb4_general_ci NOT NULL,
  `statut` enum('Actif','Fermé','Maintenance','Hors service') COLLATE utf8mb4_general_ci DEFAULT 'Actif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `guichets`
--

INSERT INTO `guichets` (`id_guichet`, `id_agence`, `numero_guichet`, `type_guichet`, `statut`, `created_at`) VALUES
(1, 1, 1, 'Standard', 'Actif', '2025-10-15 05:07:49'),
(2, 4, 2, 'Standard', 'Actif', '2025-10-25 04:17:35');

-- --------------------------------------------------------

--
-- Structure de la table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_address`, `username`, `success`, `user_agent`, `attempted_at`) VALUES
(1, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 06:39:12'),
(2, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 06:41:47'),
(3, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 14:59:04'),
(4, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:51:10'),
(5, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:54:00'),
(6, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 06:10:16'),
(7, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 12:11:45'),
(8, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-15 03:31:00'),
(9, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-15 03:33:32'),
(10, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-15 03:33:47'),
(11, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-15 05:42:51'),
(12, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 03:43:54'),
(13, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 03:44:37'),
(14, '::1', 'Felicien', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 03:45:21'),
(15, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 07:48:57'),
(16, '::1', 'Felicien', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:19:04'),
(17, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:19:29'),
(18, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 03:41:31'),
(19, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 03:43:53'),
(20, '::1', 'Felicien', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 04:52:27'),
(21, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 04:53:25'),
(22, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 05:08:49'),
(23, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 17:07:13'),
(24, '::1', 'Felicien', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 17:11:01'),
(25, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 17:11:37'),
(26, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 17:12:38'),
(27, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:07:33'),
(28, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:13:39'),
(29, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:28:33'),
(30, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:34:38'),
(31, '::1', 'David', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:46:04'),
(32, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:52:17'),
(33, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:54:06'),
(34, '::1', 'David', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:58:26'),
(35, '::1', 'Felicien', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 05:07:25'),
(36, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 05:24:40'),
(37, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 05:35:07'),
(38, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 06:45:40'),
(39, '::1', 'David', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 06:50:04'),
(40, '::1', 'Mike', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 06:58:24'),
(41, '::1', 'Janvier', 1, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 08:15:09');

-- --------------------------------------------------------

--
-- Structure de la table `log_operations`
--

CREATE TABLE `log_operations` (
  `id_log` int NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `operation_type` enum('INSERT','UPDATE','DELETE') COLLATE utf8mb4_general_ci NOT NULL,
  `record_id` int NOT NULL,
  `id_agent` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `log_operations`
--

INSERT INTO `log_operations` (`id_log`, `table_name`, `operation_type`, `record_id`, `id_agent`, `old_values`, `new_values`, `timestamp`) VALUES
(1, 'agents', 'UPDATE', 8, 6, NULL, NULL, '2025-10-15 03:36:03'),
(2, 'guichets', 'INSERT', 1, 6, NULL, NULL, '2025-10-15 05:07:49'),
(3, 'agents', 'UPDATE', 8, 6, NULL, NULL, '2025-10-16 03:45:01'),
(4, 'transactions', 'INSERT', 2, 7, NULL, NULL, '2025-10-24 04:25:45'),
(5, 'transactions', 'INSERT', 3, 7, NULL, NULL, '2025-10-24 04:27:00'),
(6, 'transactions', 'INSERT', 4, 7, NULL, NULL, '2025-10-24 05:11:18'),
(7, 'transactions', 'INSERT', 5, 7, NULL, NULL, '2025-10-24 17:08:16'),
(8, 'transactions', 'INSERT', 6, 7, NULL, NULL, '2025-10-24 17:09:41'),
(9, 'transactions', 'INSERT', 7, 7, NULL, NULL, '2025-10-24 17:14:34'),
(10, 'clients', 'INSERT', 2, 7, NULL, NULL, '2025-10-24 17:14:34'),
(11, 'comptes', 'INSERT', 2, 7, NULL, NULL, '2025-10-24 17:14:34'),
(12, 'transactions', 'INSERT', 8, 7, NULL, NULL, '2025-10-24 17:17:44'),
(13, 'transactions', 'INSERT', 12, 7, NULL, NULL, '2025-10-24 17:17:44'),
(14, 'agences', 'INSERT', 4, 6, NULL, NULL, '2025-10-25 04:16:19'),
(15, 'guichets', 'INSERT', 2, 6, NULL, NULL, '2025-10-25 04:17:35'),
(16, 'transactions', 'INSERT', 10, 7, NULL, NULL, '2025-10-25 04:30:37'),
(17, 'transactions', 'INSERT', 16, 7, NULL, NULL, '2025-10-25 04:30:37'),
(18, 'transactions', 'INSERT', 12, 7, NULL, NULL, '2025-10-25 04:34:05'),
(19, 'agents', 'INSERT', 9, 6, NULL, NULL, '2025-10-25 04:40:14'),
(20, 'transactions', 'INSERT', 13, 9, NULL, NULL, '2025-10-25 04:47:27'),
(21, 'clients', 'INSERT', 3, 9, NULL, NULL, '2025-10-25 04:47:27'),
(22, 'comptes', 'INSERT', 3, 9, NULL, NULL, '2025-10-25 04:47:27'),
(23, 'transactions', 'INSERT', 14, 7, NULL, NULL, '2025-10-25 04:55:27'),
(24, 'guichets', 'UPDATE', 2, 6, NULL, NULL, '2025-10-25 05:29:42'),
(25, 'agents', 'UPDATE', 6, 6, NULL, NULL, '2025-10-25 05:32:49'),
(26, 'types_transaction', 'INSERT', 24, 6, NULL, NULL, '2025-10-25 05:34:31'),
(27, 'transactions', 'INSERT', 16, 7, NULL, NULL, '2025-10-25 05:37:42'),
(28, 'transactions', 'INSERT', 17, 7, NULL, NULL, '2025-10-25 05:38:18'),
(29, 'transactions', 'INSERT', 18, 7, NULL, NULL, '2025-10-25 06:41:24'),
(30, 'transactions', 'INSERT', 19, 7, NULL, NULL, '2025-10-25 06:43:25'),
(31, 'transactions', 'INSERT', 20, 9, NULL, NULL, '2025-10-25 06:50:27'),
(32, 'transactions', 'INSERT', 21, 9, NULL, NULL, '2025-10-25 06:57:59');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_capital`
--

CREATE TABLE `mouvements_capital` (
  `id_mouvement` int NOT NULL,
  `id_capital` int DEFAULT NULL,
  `id_agent` int DEFAULT NULL,
  `type_mouvement` enum('Credit','Debit','Ajustement') COLLATE utf8mb4_general_ci NOT NULL,
  `montant` decimal(15,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `date_operation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `id_agent` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sessions`
--

INSERT INTO `sessions` (`id`, `id_agent`, `ip_address`, `user_agent`, `last_activity`, `created_at`) VALUES
('3qskl7f39k1nnndq4f9a7s03he', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 05:08:49', '2025-10-24 05:08:49'),
('d1o143p3jmmt2h77r66fpj5142', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 14:59:04', '2025-10-13 14:59:04'),
('e3ikec6tlvsbrqoah8nq6qgjev', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 12:11:45', '2025-10-14 12:11:45'),
('efjodctahbgqud1mere0c52g94', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 06:39:12', '2025-10-13 06:39:12'),
('giuapevvcloluj5sf02ke732or', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-24 17:12:38', '2025-10-24 17:12:38'),
('hk5f9da80lsivd2m10v9lhjodd', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 06:10:16', '2025-10-14 06:10:16'),
('k1cvbtqfvujcjfm82uptc1vjtf', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 08:15:09', '2025-10-25 08:15:09'),
('kf9hcngf13qiesgv12ru3uq5b7', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-13 06:41:47', '2025-10-13 06:41:47'),
('lmgceqoh8ik1bdo9693es4u099', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:19:29', '2025-10-21 08:19:29'),
('o4k7hnp8jlcamhstmvjr45fmep', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 04:51:10', '2025-10-14 04:51:10'),
('v2v7u1aoda0tnsjtlldahvmh1c', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-22 03:41:31', '2025-10-22 03:41:31');

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `id_transaction` int NOT NULL,
  `num_compte` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `id_type_transaction` int NOT NULL,
  `id_agent` int NOT NULL,
  `id_guichet` int DEFAULT NULL,
  `montant` decimal(15,2) NOT NULL,
  `frais` decimal(10,2) DEFAULT '0.00',
  `montant_total` decimal(15,2) NOT NULL COMMENT 'Montant + frais',
  `num_compte_destinataire` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Pour les virements',
  `statut` enum('En attente','En cours','Terminée','Annulée','Rejetée') COLLATE utf8mb4_general_ci DEFAULT 'En cours',
  `date_heure` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_validation` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `reference_externe` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Numéro de chèque, référence, etc.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`id_transaction`, `num_compte`, `id_type_transaction`, `id_agent`, `id_guichet`, `montant`, `frais`, `montant_total`, `num_compte_destinataire`, `statut`, `date_heure`, `date_validation`, `description`, `reference_externe`) VALUES
(1, 'COMPTE001', 19, 7, NULL, 700000.00, 0.00, 700000.00, NULL, 'Terminée', '2025-10-22 04:25:10', NULL, 'Dépôt initial à l\'ouverture du compte', NULL),
(2, 'COMPTE001', 19, 7, NULL, 150000.00, 0.00, 150000.00, NULL, 'Terminée', '2025-10-24 04:25:45', NULL, 'versement', NULL),
(3, 'COMPTE001', 21, 7, NULL, 100000.00, 500.00, 100500.00, NULL, 'Terminée', '2025-10-24 04:27:00', NULL, 'retrait', NULL),
(4, 'COMPTE001', 19, 7, NULL, 400000.00, 0.00, 400000.00, NULL, 'Terminée', '2025-10-24 05:11:18', NULL, 'VERSEMENT', NULL),
(5, 'COMPTE001', 19, 7, NULL, 250000.00, 0.00, 250000.00, NULL, 'Terminée', '2025-10-24 17:08:16', NULL, 'versement', NULL),
(6, 'COMPTE001', 20, 7, NULL, 500000.00, 0.00, 500000.00, NULL, 'Terminée', '2025-10-24 17:09:41', NULL, NULL, NULL),
(7, 'COMPTE002', 19, 7, NULL, 1500000.00, 0.00, 1500000.00, NULL, 'Terminée', '2025-10-24 17:14:34', NULL, 'Dépôt initial à l\'ouverture du compte', NULL),
(8, 'COMPTE002', 22, 7, NULL, 500000.00, 0.00, 500000.00, NULL, 'Terminée', '2025-10-24 17:17:44', NULL, 'Virement vers COMPTE001 - virement interne', NULL),
(9, 'COMPTE001', 22, 7, NULL, 500000.00, 0.00, 500000.00, NULL, 'Terminée', '2025-10-24 17:17:44', NULL, 'Virement reçu de COMPTE002 - virement interne', NULL),
(10, 'COMPTE001', 22, 7, NULL, 150000.00, 0.00, 150000.00, NULL, 'Terminée', '2025-10-25 04:30:37', NULL, 'Virement vers COMPTE002', NULL),
(11, 'COMPTE002', 22, 7, NULL, 150000.00, 0.00, 150000.00, NULL, 'Terminée', '2025-10-25 04:30:37', NULL, 'Virement reçu de COMPTE001', NULL),
(12, 'COMPTE002', 19, 7, NULL, 150000.00, 0.00, 150000.00, NULL, 'Terminée', '2025-10-25 04:34:05', NULL, 'kubitsa', NULL),
(13, 'COMPTE003', 19, 9, NULL, 500000.00, 0.00, 500000.00, NULL, 'Terminée', '2025-10-25 04:47:27', NULL, 'Dépôt initial à l\'ouverture du compte', NULL),
(14, 'COMPTE002', 19, 7, NULL, 1330000.00, 0.00, 1330000.00, NULL, 'Terminée', '2025-10-25 04:55:27', NULL, 'Kubitsa', NULL),
(16, 'COMPTE002', 24, 7, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 05:37:42', NULL, 'Frais de génération d\'historique de compte', NULL),
(17, 'COMPTE001', 24, 7, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 05:38:18', NULL, 'Frais de génération d\'historique de compte', NULL),
(18, 'COMPTE001', 24, 7, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 06:41:24', NULL, 'Frais de génération d\'historique de compte', NULL),
(19, 'COMPTE001', 24, 7, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 06:43:25', NULL, 'Frais de génération d\'historique de compte', NULL),
(20, 'COMPTE003', 24, 9, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 06:50:27', NULL, 'Frais de génération d\'historique de compte', NULL),
(21, 'COMPTE001', 24, 9, NULL, 1000.00, 0.00, 1000.00, NULL, 'Terminée', '2025-10-25 06:57:59', NULL, 'Frais de génération d\'historique de compte', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `types_compte`
--

CREATE TABLE `types_compte` (
  `id_type_compte` int NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Code unique: EPARGNE, COURANT, CREDIT, ENTREPRISE',
  `libelle` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `taux_interet` decimal(5,2) DEFAULT '0.00' COMMENT 'Taux pour compte épargne ou frais',
  `frais_gestion_mensuel` decimal(10,2) DEFAULT '0.00',
  `solde_minimum` decimal(15,2) DEFAULT '0.00',
  `decouvert_autorise` tinyint(1) DEFAULT '0',
  `montant_decouvert_max` decimal(15,2) DEFAULT '0.00',
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `types_compte`
--

INSERT INTO `types_compte` (`id_type_compte`, `code`, `libelle`, `description`, `taux_interet`, `frais_gestion_mensuel`, `solde_minimum`, `decouvert_autorise`, `montant_decouvert_max`, `actif`, `created_at`) VALUES
(11, 'EPARGNE', 'Compte Épargne', 'Compte d\'épargne avec intérêts', 2.50, 500.00, 10000.00, 0, 0.00, 1, '2025-10-12 10:29:51'),
(12, 'COURANT', 'Compte Courant', 'Compte courant pour opérations quotidiennes', 0.00, 1000.00, 5000.00, 1, 100000.00, 1, '2025-10-12 10:29:51'),
(13, 'ENTREPRISE', 'Compte Entreprise', 'Compte pour entreprises', 0.00, 5000.00, 50000.00, 1, 500000.00, 1, '2025-10-12 10:29:51');

-- --------------------------------------------------------

--
-- Structure de la table `types_credit`
--

CREATE TABLE `types_credit` (
  `id_type_credit` int NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `taux_interet` decimal(5,2) NOT NULL,
  `duree_max_mois` int NOT NULL,
  `montant_min` decimal(15,2) NOT NULL,
  `montant_max` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `types_transaction`
--

CREATE TABLE `types_transaction` (
  `id_type_transaction` int NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `libelle` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `categorie` enum('DEPOT','RETRAIT','VIREMENT','PAIEMENT','AUTRES') COLLATE utf8mb4_general_ci NOT NULL,
  `sens` enum('DEBIT','CREDIT') COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Impact sur le compte',
  `necessite_guichet` tinyint(1) DEFAULT '1',
  `frais_fixe` decimal(10,2) DEFAULT '0.00',
  `frais_pourcentage` decimal(5,2) DEFAULT '0.00',
  `montant_min` decimal(15,2) DEFAULT '0.00',
  `montant_max` decimal(15,2) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `types_transaction`
--

INSERT INTO `types_transaction` (`id_type_transaction`, `code`, `libelle`, `categorie`, `sens`, `necessite_guichet`, `frais_fixe`, `frais_pourcentage`, `montant_min`, `montant_max`, `actif`, `created_at`) VALUES
(19, 'DEPOT_ESPECE', 'Dépôt en espèces au guichet', 'DEPOT', 'CREDIT', 1, 0.00, 0.00, 1000.00, NULL, 1, '2025-10-12 10:29:10'),
(20, 'RETRAIT_GUICHET', 'Retrait au guichet', 'RETRAIT', 'DEBIT', 1, 0.00, 0.00, 1000.00, 1000000.00, 1, '2025-10-12 10:29:10'),
(21, 'RETRAIT_DAB', 'Retrait au DAB', 'RETRAIT', 'DEBIT', 0, 500.00, 0.00, 5000.00, 200000.00, 1, '2025-10-12 10:29:10'),
(22, 'VIREMENT_INTERNE', 'Virement interne', 'VIREMENT', 'DEBIT', 0, 0.00, 0.00, 1000.00, NULL, 1, '2025-10-12 10:29:10'),
(23, 'VIREMENT_EXTERNE', 'Virement externe', 'VIREMENT', 'DEBIT', 1, 2000.00, 0.50, 10000.00, NULL, 1, '2025-10-12 10:29:10'),
(24, 'FRAIS_HISTORIQUE', 'Frais génération historique', 'AUTRES', 'DEBIT', 1, 1000.00, 0.00, 5000.00, NULL, 1, '2025-10-25 05:34:31');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `agences`
--
ALTER TABLE `agences`
  ADD PRIMARY KEY (`id_agence`);

--
-- Index pour la table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id_agent`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_agence` (`id_agence`);

--
-- Index pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_agent` (`id_agent`),
  ADD KEY `idx_date` (`date_heure`);

--
-- Index pour la table `capitaux_banque`
--
ALTER TABLE `capitaux_banque`
  ADD PRIMARY KEY (`id_capital`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id_client`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Index pour la table `comptes`
--
ALTER TABLE `comptes`
  ADD PRIMARY KEY (`id_compte`),
  ADD UNIQUE KEY `unique_num_compte` (`num_compte`),
  ADD KEY `idx_client` (`id_client`),
  ADD KEY `idx_type_compte` (`id_type_compte`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_agence_origine` (`id_agence_origine`);

--
-- Index pour la table `demandes_credit`
--
ALTER TABLE `demandes_credit`
  ADD PRIMARY KEY (`id_demande`),
  ADD KEY `idx_client` (`id_client`),
  ADD KEY `idx_type_credit` (`id_type_credit`),
  ADD KEY `idx_agent` (`id_agent`);

--
-- Index pour la table `dossiers_credit`
--
ALTER TABLE `dossiers_credit`
  ADD PRIMARY KEY (`id_dossier`),
  ADD UNIQUE KEY `unique_demande` (`id_demande`),
  ADD KEY `idx_compte` (`num_compte`);

--
-- Index pour la table `echeances_credit`
--
ALTER TABLE `echeances_credit`
  ADD PRIMARY KEY (`id_echeance`),
  ADD KEY `idx_dossier` (`id_dossier`),
  ADD KEY `idx_date_echeance` (`date_echeance`);

--
-- Index pour la table `guichets`
--
ALTER TABLE `guichets`
  ADD PRIMARY KEY (`id_guichet`),
  ADD KEY `idx_agence` (`id_agence`);

--
-- Index pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  ADD KEY `idx_username_time` (`username`,`attempted_at`);

--
-- Index pour la table `log_operations`
--
ALTER TABLE `log_operations`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_table` (`table_name`),
  ADD KEY `idx_agent` (`id_agent`);

--
-- Index pour la table `mouvements_capital`
--
ALTER TABLE `mouvements_capital`
  ADD PRIMARY KEY (`id_mouvement`),
  ADD KEY `idx_capital` (`id_capital`),
  ADD KEY `idx_agent` (`id_agent`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent` (`id_agent`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id_transaction`),
  ADD KEY `idx_compte` (`num_compte`),
  ADD KEY `idx_type_transaction` (`id_type_transaction`),
  ADD KEY `idx_agent` (`id_agent`),
  ADD KEY `idx_guichet` (`id_guichet`),
  ADD KEY `idx_date` (`date_heure`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `types_compte`
--
ALTER TABLE `types_compte`
  ADD PRIMARY KEY (`id_type_compte`),
  ADD UNIQUE KEY `unique_code` (`code`);

--
-- Index pour la table `types_credit`
--
ALTER TABLE `types_credit`
  ADD PRIMARY KEY (`id_type_credit`);

--
-- Index pour la table `types_transaction`
--
ALTER TABLE `types_transaction`
  ADD PRIMARY KEY (`id_type_transaction`),
  ADD UNIQUE KEY `unique_code` (`code`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `agences`
--
ALTER TABLE `agences`
  MODIFY `id_agence` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `agents`
--
ALTER TABLE `agents`
  MODIFY `id_agent` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `capitaux_banque`
--
ALTER TABLE `capitaux_banque`
  MODIFY `id_capital` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id_client` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `comptes`
--
ALTER TABLE `comptes`
  MODIFY `id_compte` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `demandes_credit`
--
ALTER TABLE `demandes_credit`
  MODIFY `id_demande` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dossiers_credit`
--
ALTER TABLE `dossiers_credit`
  MODIFY `id_dossier` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `echeances_credit`
--
ALTER TABLE `echeances_credit`
  MODIFY `id_echeance` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `guichets`
--
ALTER TABLE `guichets`
  MODIFY `id_guichet` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT pour la table `log_operations`
--
ALTER TABLE `log_operations`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT pour la table `mouvements_capital`
--
ALTER TABLE `mouvements_capital`
  MODIFY `id_mouvement` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id_transaction` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `types_compte`
--
ALTER TABLE `types_compte`
  MODIFY `id_type_compte` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT pour la table `types_credit`
--
ALTER TABLE `types_credit`
  MODIFY `id_type_credit` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `types_transaction`
--
ALTER TABLE `types_transaction`
  MODIFY `id_type_transaction` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `fk_agents_agence` FOREIGN KEY (`id_agence`) REFERENCES `agences` (`id_agence`) ON DELETE SET NULL;

--
-- Contraintes pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE SET NULL;

--
-- Contraintes pour la table `comptes`
--
ALTER TABLE `comptes`
  ADD CONSTRAINT `fk_comptes_agence` FOREIGN KEY (`id_agence_origine`) REFERENCES `agences` (`id_agence`),
  ADD CONSTRAINT `fk_comptes_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comptes_type` FOREIGN KEY (`id_type_compte`) REFERENCES `types_compte` (`id_type_compte`);

--
-- Contraintes pour la table `demandes_credit`
--
ALTER TABLE `demandes_credit`
  ADD CONSTRAINT `fk_demandes_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_demandes_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_demandes_type` FOREIGN KEY (`id_type_credit`) REFERENCES `types_credit` (`id_type_credit`);

--
-- Contraintes pour la table `dossiers_credit`
--
ALTER TABLE `dossiers_credit`
  ADD CONSTRAINT `fk_dossiers_compte` FOREIGN KEY (`num_compte`) REFERENCES `comptes` (`num_compte`),
  ADD CONSTRAINT `fk_dossiers_demande` FOREIGN KEY (`id_demande`) REFERENCES `demandes_credit` (`id_demande`);

--
-- Contraintes pour la table `echeances_credit`
--
ALTER TABLE `echeances_credit`
  ADD CONSTRAINT `fk_echeances_dossier` FOREIGN KEY (`id_dossier`) REFERENCES `dossiers_credit` (`id_dossier`) ON DELETE CASCADE;

--
-- Contraintes pour la table `guichets`
--
ALTER TABLE `guichets`
  ADD CONSTRAINT `fk_guichets_agence` FOREIGN KEY (`id_agence`) REFERENCES `agences` (`id_agence`) ON DELETE CASCADE;

--
-- Contraintes pour la table `log_operations`
--
ALTER TABLE `log_operations`
  ADD CONSTRAINT `fk_log_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mouvements_capital`
--
ALTER TABLE `mouvements_capital`
  ADD CONSTRAINT `fk_mouvements_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_mouvements_capital` FOREIGN KEY (`id_capital`) REFERENCES `capitaux_banque` (`id_capital`);

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_compte` FOREIGN KEY (`num_compte`) REFERENCES `comptes` (`num_compte`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_transactions_guichet` FOREIGN KEY (`id_guichet`) REFERENCES `guichets` (`id_guichet`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transactions_type` FOREIGN KEY (`id_type_transaction`) REFERENCES `types_transaction` (`id_type_transaction`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
