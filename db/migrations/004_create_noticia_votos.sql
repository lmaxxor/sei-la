CREATE TABLE IF NOT EXISTS noticia_votos (
    id_voto INT AUTO_INCREMENT PRIMARY KEY,
    id_noticia INT NOT NULL,
    id_utilizador INT NOT NULL,
    valor ENUM('up','down') NOT NULL,
    data_voto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_noticia_user (id_noticia, id_utilizador),
    FOREIGN KEY (id_noticia) REFERENCES noticias(id_noticia) ON DELETE CASCADE,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
