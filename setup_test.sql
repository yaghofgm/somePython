-- Cria o banco de dados
CREATE DATABASE IF NOT EXISTS meusite;
USE meusite;

-- Cria a tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    sobrenome VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    senha VARCHAR(255)
);

-- Insere um usuário de teste (senha: senha123)
INSERT INTO usuarios (nome, sobrenome, email, senha)
VALUES ('Ana', 'Silva', 'ana@email.com', SHA2('senha123', 256));
