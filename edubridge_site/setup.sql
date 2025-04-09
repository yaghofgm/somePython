CREATE DATABASE IF NOT EXISTS portalusuarios;
USE portalusuarios;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    sobrenome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    categoria ENUM('investidor', 'estudante', 'universidade', 'empresa') NOT NULL
);