-- Script para corrigir a estrutura da tabela de usuários sem perder as relações existentes
USE portalusuarios;

-- Backup da tabela de usuários existente
CREATE TABLE IF NOT EXISTS usuarios_backup SELECT * FROM usuarios;

-- Adicionando as colunas que estão faltando na tabela de usuários
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS uuid VARCHAR(36) DEFAULT (UUID()) COMMENT 'Public identifier for users',
    ADD COLUMN IF NOT EXISTS status ENUM('pendente', 'ativo', 'suspenso', 'inativo') NOT NULL DEFAULT 'pendente' AFTER categoria,
    ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) DEFAULT 'default.jpg' AFTER status,
    ADD COLUMN IF NOT EXISTS bio TEXT AFTER foto_perfil,
    ADD COLUMN IF NOT EXISTS data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER bio,
    ADD COLUMN IF NOT EXISTS data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER data_criacao,
    ADD COLUMN IF NOT EXISTS ultima_conexao TIMESTAMP NULL AFTER data_atualizacao,
    ADD COLUMN IF NOT EXISTS token_reset VARCHAR(100) NULL AFTER ultima_conexao,
    ADD COLUMN IF NOT EXISTS token_expiracao DATETIME NULL AFTER token_reset,
    ADD INDEX IF NOT EXISTS idx_categoria (categoria),
    ADD INDEX IF NOT EXISTS idx_status (status);

-- Verifica se o usuário administrador já existe e o cria se não existir
INSERT IGNORE INTO usuarios (nome, sobrenome, email, senha, categoria, status)
VALUES ('Admin', 'System', 'admin@edubridge.com', '$2y$10$qS9UbJ2vpHl/qshfRaZRGOYoI6ANOWGPyj8AGQQZlvP5BeR8idPdO', 'empresa', 'ativo');

-- Certifique-se de que o usuário administrador está com status ativo
UPDATE usuarios SET status = 'ativo' WHERE email = 'admin@edubridge.com';

-- Criando tabela para logs se não existir
CREATE TABLE IF NOT EXISTS usuarios_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(50) NOT NULL,
    ip VARCHAR(45),
    user_agent VARCHAR(255),
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    detalhes JSON,
    
    INDEX idx_usuario (usuario_id),
    INDEX idx_acao (acao),
    INDEX idx_data (data_hora)
);

-- Exibe uma mensagem de confirmação
SELECT 'Estrutura do banco de dados corrigida com sucesso!' AS Mensagem;