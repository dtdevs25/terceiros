-- phpMyAdmin SQL Dump - Clean Version
-- Gestão de Terceiros - Idempotent Script

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Estrutura da tabela `empresas`
--
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cnpj` varchar(18) NOT NULL COMMENT 'CNPJ da empresa',
  `endereco` varchar(255) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` char(2) DEFAULT NULL,
  `cep` varchar(9) DEFAULT NULL,
  `telefone` varchar(15) DEFAULT NULL,
  `email_contato` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`),
  UNIQUE KEY `cnpj_unique` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estrutura da tabela `filiais`
--
CREATE TABLE IF NOT EXISTS `filiais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estrutura da tabela `terceiros`
--
CREATE TABLE IF NOT EXISTS `terceiros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome_completo` varchar(255) NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `empresa_id` int NOT NULL,
  `filial_id` int NOT NULL,
  `aso_data` date DEFAULT NULL,
  `aso_aplicavel` tinyint(1) DEFAULT '1',
  `epi_data` date DEFAULT NULL,
  `epi_aplicavel` tinyint(1) DEFAULT '1',
  `nr10_data` date DEFAULT NULL,
  `nr10_aplicavel` tinyint(1) DEFAULT '1',
  `nr11_data` date DEFAULT NULL,
  `nr11_aplicavel` tinyint(1) DEFAULT '1',
  `nr12_data` date DEFAULT NULL,
  `nr12_aplicavel` tinyint(1) DEFAULT '1',
  `nr18_data` date DEFAULT NULL,
  `nr18_aplicavel` tinyint(1) DEFAULT '1',
  `integracao_data` date DEFAULT NULL,
  `integracao_aplicavel` tinyint(1) DEFAULT '1',
  `nr20_data` date DEFAULT NULL,
  `nr20_aplicavel` tinyint(1) DEFAULT '1',
  `nr33_data` date DEFAULT NULL,
  `nr33_aplicavel` tinyint(1) DEFAULT '1',
  `nr35_data` date DEFAULT NULL,
  `nr35_aplicavel` tinyint(1) DEFAULT '1',
  `observacoes` text,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `filial_id` (`filial_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estrutura da tabela `log_atividades`
--
CREATE TABLE IF NOT EXISTS `log_atividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `terceiro_id` int NOT NULL,
  `nome_colaborador` varchar(255) NOT NULL,
  `data_liberacao` datetime NOT NULL,
  `termo_aceito` tinyint(1) NOT NULL DEFAULT '0',
  `assinatura` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo de imagem da assinatura',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `doc_path` varchar(255) DEFAULT NULL COMMENT 'Caminho do arquivo Word do termo',
  PRIMARY KEY (`id`),
  KEY `terceiro_id` (`terceiro_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Estrutura da tabela `usuarios`
--
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','comum') NOT NULL DEFAULT 'comum',
  `filiais_permitidas` text,
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token_recuperacao` varchar(255) DEFAULT NULL,
  `token_expiracao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Restrições
ALTER TABLE `log_atividades`
  ADD CONSTRAINT `log_atividades_ibfk_1` FOREIGN KEY (`terceiro_id`) REFERENCES `terceiros` (`id`) ON DELETE CASCADE;

ALTER TABLE `terceiros`
  ADD CONSTRAINT `terceiros_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `terceiros_ibfk_2` FOREIGN KEY (`filial_id`) REFERENCES `filiais` (`id`) ON DELETE CASCADE;

COMMIT;
