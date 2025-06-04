CREATE TABLE IF NOT EXISTS comunidade_curtidas (
    id_like INT AUTO_INCREMENT PRIMARY KEY,
    id_post INT NOT NULL,
    id_utilizador INT NOT NULL,
    data_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_user (id_post, id_utilizador),
    FOREIGN KEY (id_post) REFERENCES comunidade_posts(id_post) ON DELETE CASCADE,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
