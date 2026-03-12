-- Sistema de Gerenciamento de Funcionários
-- Script de criação do banco de dados
-- Compatível com MySQL 5.7+

CREATE DATABASE IF NOT EXISTS sistema_funcionarios CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_funcionarios;

-- Tabela de empresas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(255) NOT NULL,
    cnpj VARCHAR(18) NULL,
    endereco TEXT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de postos de trabalho/filiais
CREATE TABLE IF NOT EXISTS postos_trabalho (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    endereco TEXT NULL,
    empresa_id INT NOT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
);

-- Tabela de usuários do sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    hierarquia ENUM('visualizador', 'controlador', 'administrador', 'gerente') NOT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de permissões de usuários por empresa
CREATE TABLE IF NOT EXISTS usuario_empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    empresa_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_empresa (usuario_id, empresa_id)
);

-- Tabela de treinamentos
CREATE TABLE IF NOT EXISTS treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    carga_horaria INT NOT NULL,
    prazo_validade INT NOT NULL COMMENT 'Validade em meses',
    descricao TEXT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de funcionários
CREATE TABLE IF NOT EXISTS funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NOT NULL UNIQUE,
    matricula VARCHAR(50) NOT NULL UNIQUE,
    foto VARCHAR(255) NULL,
    aso_data DATE NOT NULL COMMENT 'Data do ASO',
    aso_validade DATE NOT NULL COMMENT 'Validade do ASO (1 ano)',
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de relacionamento funcionário x treinamentos
CREATE TABLE IF NOT EXISTS funcionario_treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    treinamento_id INT NOT NULL,
    data_realizacao DATE NOT NULL,
    data_validade DATE NOT NULL,
    certificado VARCHAR(255) NULL,
    status ENUM('valido', 'vencido', 'a_vencer') DEFAULT 'valido',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (treinamento_id) REFERENCES treinamentos(id) ON DELETE CASCADE
);

-- Tabela de relacionamento funcionário x postos de trabalho
CREATE TABLE IF NOT EXISTS funcionario_postos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    posto_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE,
    FOREIGN KEY (posto_id) REFERENCES postos_trabalho(id) ON DELETE CASCADE
);

-- Tabela de auditoria
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tabela VARCHAR(50) NOT NULL,
    registro_id INT NOT NULL,
    acao ENUM('create', 'update', 'delete') NOT NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir dados iniciais (usando INSERT IGNORE para evitar duplicatas)

-- Usuário administrador padrão (senha: admin123)
INSERT IGNORE INTO usuarios (nome, email, senha, hierarquia) VALUES 
('Administrador', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gerente');

-- Empresa exemplo
INSERT IGNORE INTO empresas (id, razao_social, cnpj, endereco) VALUES 
(1, 'Empresa Exemplo Ltda', '12.345.678/0001-90', 'Rua Exemplo, 123 - Centro - São Paulo/SP');

-- Posto de trabalho exemplo
INSERT IGNORE INTO postos_trabalho (id, nome, endereco, empresa_id) VALUES 
(1, 'Matriz', 'Rua Exemplo, 123 - Centro - São Paulo/SP', 1);

-- Vincular usuário à empresa
INSERT IGNORE INTO usuario_empresas (usuario_id, empresa_id) VALUES (1, 1);

-- Treinamentos padrão
INSERT IGNORE INTO treinamentos (nome, carga_horaria, prazo_validade, descricao) VALUES 
('NR-10 - Segurança em Instalações e Serviços em Eletricidade', 40, 24, 'Treinamento obrigatório para trabalhos com eletricidade'),
('NR-35 - Trabalho em Altura', 8, 24, 'Treinamento para trabalhos em altura superior a 2 metros'),
('NR-33 - Espaços Confinados', 16, 12, 'Treinamento para trabalhos em espaços confinados'),
('Primeiros Socorros', 8, 24, 'Treinamento básico de primeiros socorros'),
('Combate a Incêndio', 4, 12, 'Treinamento de prevenção e combate a incêndios');

-- Configurações padrão
INSERT IGNORE INTO configuracoes (chave, valor, descricao) VALUES 
('sistema_nome', 'Sistema de Gerenciamento de Funcionários', 'Nome do sistema'),
('empresa_nome', 'Sua Empresa', 'Nome da empresa proprietária do sistema'),
('email_alertas', 'admin@sistema.com', 'E-mail para envio de alertas'),
('dias_alerta_aso', '30', 'Dias de antecedência para alerta de vencimento do ASO'),
('dias_alerta_treinamento', '30', 'Dias de antecedência para alerta de vencimento de treinamentos');

-- Índices (Se falhar porque já existe, ignoramos - MySQL 5.7 não tem CREATE INDEX IF NOT EXISTS)
-- No CapRover/Docker, se o banco for novo, isso rodará 100%. Se já existirem, os erros não param o script dependendo do executor.
-- Para garantir, vamos tentar dropar se existir via procedimento se necessário, mas geralmente CREATE TABLE IF NOT EXISTS já ajuda.

-- Views para facilitar consultas
DROP VIEW IF EXISTS vw_funcionarios_status;
CREATE VIEW vw_funcionarios_status AS
SELECT 
    f.id,
    f.nome,
    f.cpf,
    f.matricula,
    f.foto,
    f.aso_data,
    f.aso_validade,
    f.status,
    CASE 
        WHEN f.aso_validade < CURDATE() THEN 'vencido'
        WHEN f.aso_validade <= DATE_ADD(CURDATE(), INTERVAL 15 DAY) THEN 'vence_15_dias'
        WHEN f.aso_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'vence_30_dias'
        ELSE 'valido'
    END as aso_status,
    (SELECT COUNT(*) FROM funcionario_treinamentos ft WHERE ft.funcionario_id = f.id AND ft.data_validade < CURDATE()) as treinamentos_vencidos,
    (SELECT COUNT(*) FROM funcionario_treinamentos ft WHERE ft.funcionario_id = f.id AND ft.data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as treinamentos_a_vencer,
    CASE 
        WHEN f.aso_validade < CURDATE() OR 
             (SELECT COUNT(*) FROM funcionario_treinamentos ft WHERE ft.funcionario_id = f.id AND ft.data_validade < CURDATE()) > 0 
        THEN 'inapto'
        ELSE 'apto'
    END as aptidao_trabalho
FROM funcionarios f
WHERE f.status = 'ativo';

DROP VIEW IF EXISTS vw_dashboard_contadores;
CREATE VIEW vw_dashboard_contadores AS
SELECT 
    (SELECT COUNT(*) FROM funcionarios WHERE status = 'ativo') as total_funcionarios,
    (SELECT COUNT(*) FROM funcionarios WHERE status = 'ativo' AND aso_validade < CURDATE()) as asos_vencidos,
    (SELECT COUNT(*) FROM funcionarios WHERE status = 'ativo' AND aso_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as asos_a_vencer,
    (SELECT COUNT(*) FROM funcionario_treinamentos WHERE data_validade < CURDATE()) as treinamentos_vencidos,
    (SELECT COUNT(*) FROM funcionario_treinamentos WHERE data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as treinamentos_a_vencer,
    (SELECT COUNT(*) FROM empresas WHERE status = 'ativo') as total_empresas,
    (SELECT COUNT(*) FROM postos_trabalho WHERE status = 'ativo') as total_postos;

-- Triggers para auditoria automática
DROP TRIGGER IF EXISTS tr_funcionarios_audit_insert;
DELIMITER $$
CREATE TRIGGER tr_funcionarios_audit_insert
AFTER INSERT ON funcionarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabela, registro_id, acao, dados_novos, ip_address)
    VALUES (IFNULL(@current_user_id, 1), 'funcionarios', NEW.id, 'create', 
            JSON_OBJECT('nome', NEW.nome, 'cpf', NEW.cpf, 'matricula', NEW.matricula, 'status', NEW.status),
            IFNULL(@current_user_ip, '127.0.0.1'));
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_funcionarios_audit_update;
DELIMITER $$
CREATE TRIGGER tr_funcionarios_audit_update
AFTER UPDATE ON funcionarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabela, registro_id, acao, dados_anteriores, dados_novos, ip_address)
    VALUES (IFNULL(@current_user_id, 1), 'funcionarios', NEW.id, 'update',
            JSON_OBJECT('nome', OLD.nome, 'cpf', OLD.cpf, 'matricula', OLD.matricula, 'status', OLD.status),
            JSON_OBJECT('nome', NEW.nome, 'cpf', NEW.cpf, 'matricula', NEW.matricula, 'status', NEW.status),
            IFNULL(@current_user_ip, '127.0.0.1'));
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS tr_funcionarios_audit_delete;
DELIMITER $$
CREATE TRIGGER tr_funcionarios_audit_delete
BEFORE DELETE ON funcionarios
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (usuario_id, tabela, registro_id, acao, dados_anteriores, ip_address)
    VALUES (IFNULL(@current_user_id, 1), 'funcionarios', OLD.id, 'delete',
            JSON_OBJECT('nome', OLD.nome, 'cpf', OLD.cpf, 'matricula', OLD.matricula, 'status', OLD.status),
            IFNULL(@current_user_ip, '127.0.0.1'));
END$$
DELIMITER ;

-- Procedure para atualizar status de treinamentos
DROP PROCEDURE IF EXISTS sp_atualizar_status_treinamentos;
DELIMITER $$
CREATE PROCEDURE sp_atualizar_status_treinamentos()
BEGIN
    UPDATE funcionario_treinamentos 
    SET status = CASE 
        WHEN data_validade < CURDATE() THEN 'vencido'
        WHEN data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'a_vencer'
        ELSE 'valido'
    END;
END$$
DELIMITER ;

-- Event para executar a procedure diariamente
DROP EVENT IF EXISTS ev_atualizar_status_treinamentos;
CREATE EVENT ev_atualizar_status_treinamentos
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
CALL sp_atualizar_status_treinamentos();

-- Ativar o event scheduler
SET GLOBAL event_scheduler = ON;
