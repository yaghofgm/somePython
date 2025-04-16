USE portalusuarios;

-- Tabela para armazenar universidades e seus cursos
CREATE TABLE IF NOT EXISTS universidades_cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    universidade_nome VARCHAR(100) NOT NULL,
    curso_nome VARCHAR(100) NOT NULL,
    preco_total DECIMAL(10,2) NOT NULL COMMENT 'Preço total do curso em reais',
    mensalidade DECIMAL(10,2) NOT NULL COMMENT 'Valor da mensalidade em reais',
    duracao_semestres INT NOT NULL COMMENT 'Duração do curso em semestres',
    salario_esperado DECIMAL(10,2) NOT NULL COMMENT 'Salário médio esperado após formatura em reais',
    area_conhecimento ENUM('exatas', 'humanas', 'biologicas', 'tecnologia', 'artes', 'saude', 'negocios', 'outros') NOT NULL,
    nivel ENUM('graduacao', 'pos_graduacao', 'mestrado', 'doutorado', 'tecnico') NOT NULL DEFAULT 'graduacao',
    modalidade ENUM('presencial', 'ead', 'hibrido') NOT NULL DEFAULT 'presencial',
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Garantir que cada combinação universidade/curso seja única
    UNIQUE KEY unq_universidade_curso (universidade_nome, curso_nome, nivel),
    
    -- Índices para buscas rápidas
    INDEX idx_universidade (universidade_nome),
    INDEX idx_curso (curso_nome),
    INDEX idx_area (area_conhecimento),
    INDEX idx_salario (salario_esperado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir alguns dados de exemplo
INSERT INTO universidades_cursos 
(universidade_nome, curso_nome, preco_total, mensalidade, duracao_semestres, salario_esperado, area_conhecimento, nivel, modalidade)
VALUES
('USP', 'Ciência da Computação', 0.00, 0.00, 8, 8500.00, 'tecnologia', 'graduacao', 'presencial'),
('USP', 'Engenharia Civil', 0.00, 0.00, 10, 7800.00, 'exatas', 'graduacao', 'presencial'),
('USP', 'Medicina', 0.00, 0.00, 12, 12000.00, 'saude', 'graduacao', 'presencial'),

('UNICAMP', 'Ciência da Computação', 0.00, 0.00, 8, 8200.00, 'tecnologia', 'graduacao', 'presencial'),
('UNICAMP', 'Direito', 0.00, 0.00, 10, 7500.00, 'humanas', 'graduacao', 'presencial'),

('PUC-SP', 'Administração', 76000.00, 1900.00, 8, 6500.00, 'negocios', 'graduacao', 'presencial'),
('PUC-SP', 'Psicologia', 88000.00, 1800.00, 10, 5800.00, 'humanas', 'graduacao', 'presencial'),

('INSPER', 'Engenharia de Computação', 140000.00, 4375.00, 8, 9500.00, 'tecnologia', 'graduacao', 'presencial'),
('INSPER', 'Administração', 120000.00, 3750.00, 8, 8200.00, 'negocios', 'graduacao', 'presencial'),

('UNIP', 'Ciência da Computação', 38400.00, 800.00, 8, 4500.00, 'tecnologia', 'graduacao', 'presencial'),
('UNIP', 'Ciência da Computação', 28800.00, 600.00, 8, 4300.00, 'tecnologia', 'graduacao', 'ead'),
('UNIP', 'Enfermagem', 48000.00, 1000.00, 8, 3800.00, 'saude', 'graduacao', 'presencial'),

('Estácio', 'Análise e Desenvolvimento de Sistemas', 19200.00, 400.00, 5, 3800.00, 'tecnologia', 'graduacao', 'ead'),
('Estácio', 'Marketing Digital', 24000.00, 500.00, 4, 3500.00, 'negocios', 'tecnico', 'ead'),

('USP', 'MBA em Gestão de Negócios', 40000.00, 0.00, 4, 12000.00, 'negocios', 'pos_graduacao', 'presencial'),
('FGV', 'MBA Executivo', 75000.00, 0.00, 4, 15000.00, 'negocios', 'pos_graduacao', 'presencial');