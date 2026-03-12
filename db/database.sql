-- Script SQL para criação das tabelas do banco de dados Gestão de Terceiros

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL, -- Armazenará o hash da senha
  `tipo` ENUM('admin', 'comum') NOT NULL DEFAULT 'comum',
  `filiais_permitidas` TEXT NULL, -- IDs das filiais separadas por vírgula para tipo 'comum'
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Empresas Prestadoras
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL UNIQUE,
  `cnpj` VARCHAR(18) NULL, -- Adicionado CNPJ
  `endereco` VARCHAR(255) NULL,
  `numero` VARCHAR(20) NULL,
  `complemento` VARCHAR(100) NULL,
  `bairro` VARCHAR(100) NULL,
  `cidade` VARCHAR(100) NULL,
  `estado` VARCHAR(2) NULL, -- Sigla do estado (UF)
  `cep` VARCHAR(9) NULL, -- Formato com hífen
  `telefone` VARCHAR(20) NULL, -- Adicionado telefone
  `email_contato` VARCHAR(255) NULL, -- Adicionado email de contato
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Filiais (Locais de Atuação)
CREATE TABLE IF NOT EXISTS `filiais` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL,
  `endereco` VARCHAR(255) NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Terceiros (Colaboradores)
CREATE TABLE IF NOT EXISTS `terceiros` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_completo` VARCHAR(255) NOT NULL,
  `foto_path` VARCHAR(255) NULL,
  `empresa_id` INT NOT NULL,
  `filial_id` INT NOT NULL,
  `aso_data` DATE NULL,
  `aso_aplicavel` BOOLEAN DEFAULT TRUE,
  `epi_data` DATE NULL, -- Treinamento de EPI (sem validade fixa)
  `epi_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr10_data` DATE NULL, -- Validade: 2 anos
  `nr10_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr11_data` DATE NULL, -- Validade: 1 ano
  `nr11_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr12_data` DATE NULL, -- Validade: 1 ano
  `nr12_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr18_data` DATE NULL, -- Validade: 1 ano
  `nr18_aplicavel` BOOLEAN DEFAULT TRUE,
  `integracao_data` DATE NULL, -- Validade: 1 ano
  `integracao_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr20_data` DATE NULL, -- Validade: 1 ano
  `nr20_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr33_data` DATE NULL, -- Validade: 1 ano
  `nr33_aplicavel` BOOLEAN DEFAULT TRUE,
  `nr35_data` DATE NULL, -- Validade: 2 anos
  `nr35_aplicavel` BOOLEAN DEFAULT TRUE,
  `observacoes` TEXT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`filial_id`) REFERENCES `filiais`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Script de População Inicial (Exemplo)

-- Criar usuário administrador padrão
-- A senha é 'admin123', mas será hasheada pelo PHP na criação real ou manualmente se necessário.
-- INSERT INTO `usuarios` (`nome`, `email`, `senha`, `tipo`) VALUES ('Administrador', 'admin@exemplo.com', '$2y$10$...', 'admin'); -- Substituir '...' pelo hash real

-- Criar empresa exemplo
INSERT INTO `empresas` (`nome`) VALUES ('Empresa Exemplo Ltda');

-- Criar filial exemplo
INSERT INTO `filiais` (`nome`, `endereco`) VALUES ('Filial Principal', 'Rua Exemplo, 123');

-- Nota: O usuário admin deve ser criado via script PHP ou manualmente com senha hasheada.
-- Este script SQL apenas cria as tabelas e insere dados básicos de exemplo para empresas e filiais.

