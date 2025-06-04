CREATE TABLE IF NOT EXISTS comunidade_posts (
    id_post INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizador INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ativo TINYINT(1) DEFAULT 1,
    total_curtidas INT DEFAULT 0,
    total_comentarios INT DEFAULT 0,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comunidade_comentarios (
    id_comentario INT AUTO_INCREMENT PRIMARY KEY,
    id_post INT NOT NULL,
    id_utilizador INT NOT NULL,
    texto TEXT NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (id_post) REFERENCES comunidade_posts(id_post) ON DELETE CASCADE,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
