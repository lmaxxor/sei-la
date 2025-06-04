<?php
// db/config.php

// Configurações do Banco de Dados
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'u582136142_AudioTO');
define('DB_USER', getenv('DB_USER') ?: 'u582136142_AudioTO');
define('DB_PASS', getenv('DB_PASS') ?: 'change_me'); // Mantenha esta informação segura!
define('DB_CHARSET', 'utf8mb4');

// Configurações da Aplicação (Exemplos)
define('SITE_URL', 'http://localhost/audio_to'); // Mude para o URL do seu site em produção
define('UPLOADS_PATH', $_SERVER['DOCUMENT_ROOT'] . '/audio_to/uploads/'); // Caminho absoluto para a pasta de uploads
define('UPLOADS_URL', SITE_URL . '/uploads/'); // URL base para aceder aos uploads

// Configurações de Sessão (opcional, pode estar num ficheiro de inicialização global)
// define('SESSION_SAVE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/audio_to/sessao'); // Exemplo de caminho para sessões

// Outras configurações que possa precisar
// define('ADMIN_EMAIL', 'admin@example.com');

?>
