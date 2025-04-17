CREATE DATABASE IF NOT EXISTS portalusuarios;
USE portalusuarios;

-- Drop existing tables if needed for clean reinstall
-- DROP TABLE IF EXISTS usuarios_logs;
-- DROP TABLE IF EXISTS financiamentos;
-- DROP TABLE IF EXISTS usuarios;

-- User table with enhanced fields
CREATE TABLE
  `usuarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `nome` varchar(100) NOT NULL,
    `sobrenome` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `senha` varchar(255) NOT NULL,
    `categoria` enum(
      'investidor',
      'estudante',
      'universidade',
      'empresa',
      'admin'
    ) NOT NULL,
    `status` enum('pendente', 'ativo', 'suspenso', 'inativo') NOT NULL DEFAULT 'pendente',
    `foto_perfil` varchar(255) DEFAULT 'default.jpg',
    `bio` text DEFAULT NULL,
    `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
    `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `ultima_conexao` timestamp NULL DEFAULT NULL,
    `token_reset` varchar(100) DEFAULT NULL,
    `token_expiracao` datetime DEFAULT NULL,
    `telefone` varchar(20) DEFAULT NULL,
    `uuid` varchar(36) DEFAULT uuid() COMMENT 'Public identifier for users',
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_categoria` (`categoria`),
    KEY `idx_status` (`status`)
  ) ENGINE = InnoDB AUTO_INCREMENT = 10 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci

-- Student-specific profile information
CREATE TABLE
  `perfil_estudante` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `universidade_id` int(11) DEFAULT NULL,
    `curso_id` int(11) DEFAULT NULL,
    `ano_ingresso` int(11) DEFAULT NULL,
    `semestre_atual` int(11) DEFAULT NULL,
    `nacionalidade` enum('US', 'MEX', 'BR') DEFAULT NULL,
    `gpa` decimal(3, 2) DEFAULT NULL,
    `cv_path` varchar(255) DEFAULT NULL,
    `linkedin` varchar(255) DEFAULT NULL,
    `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `usuario_id` (`usuario_id`),
    KEY `idx_universidade` (`universidade_id`),
    KEY `idx_curso` (`curso_id`),
    CONSTRAINT `perfil_estudante_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `perfil_estudante_relation_2` FOREIGN KEY (`universidade_id`) REFERENCES `perfil_universidade` (`id`) ON DELETE CASCADE,
    CONSTRAINT `perfil_estudante_relation_3` FOREIGN KEY (`curso_id`) REFERENCES `curso_universidade` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB AUTO_INCREMENT = 3 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci

CREATE TABLE
  `perfil_universidade` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) DEFAULT NULL,
    `nome` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `perfil_universidade_relation_1` (`usuario_id`),
    CONSTRAINT `perfil_universidade_relation_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB AUTO_INCREMENT = 5 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci

-- Investor-specific profile information
CREATE TABLE
  `perfil_investidor` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `area_interesse` text DEFAULT NULL,
    `valor_disponivel` decimal(15, 2) DEFAULT NULL,
    `descricao` text DEFAULT NULL,
    `linkedin` varchar(255) DEFAULT NULL,
    `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `usuario_id` (`usuario_id`),
    CONSTRAINT `perfil_investidor_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Company-specific profile information
CREATE TABLE
  `perfil_empresa` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `nome_empresa` varchar(255) DEFAULT NULL,
    `industria` varchar(100) DEFAULT NULL,
    `website` varchar(255) DEFAULT NULL,
    `descricao` text DEFAULT NULL,
    `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `usuario_id` (`usuario_id`),
    CONSTRAINT `perfil_empresa_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE
  `curso_universidade` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `universidade_id` int(11) DEFAULT NULL COMMENT 'foreign key to uni',
    `nome_curso` varchar(255) DEFAULT 'NULL',
    `gpa_medio` decimal(3, 2) DEFAULT NULL,
    `custo_semestre` double DEFAULT NULL,
    `salario_esperado` double DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `curso_universidade_relation_2` (`universidade_id`),
    CONSTRAINT `curso_universidade_relation_2` FOREIGN KEY (`universidade_id`) REFERENCES `perfil_universidade` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB AUTO_INCREMENT = 9 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci

CREATE TABLE
  `usuarios_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` int(11) NOT NULL,
    `acao` varchar(50) NOT NULL,
    `ip` varchar(45) DEFAULT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `data_hora` timestamp NOT NULL DEFAULT current_timestamp(),
    `detalhes` longtext CHARACTER SET utf8mb4 COLLATE = utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalhes`)),
    PRIMARY KEY (`id`),
    KEY `idx_usuario` (`usuario_id`),
    KEY `idx_acao` (`acao`),
    KEY `idx_data` (`data_hora`),
    CONSTRAINT `usuarios_logs_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
  ) ENGINE = InnoDB AUTO_INCREMENT = 68 DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
