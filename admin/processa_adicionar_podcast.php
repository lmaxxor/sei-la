<?php
// admin/processa_adicionar_podcast.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php');
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../includes/mailer.php';

// Função para gerar slugs únicos (pode ser movida para um ficheiro de helpers)
function gerarSlugPodcast($texto, $pdo, $id_podcast_atual = null) {
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        $slug = 'podcast-' . uniqid();
    }

    $i = 1;
    $original_slug = $slug;
    $sql_check = "SELECT id_podcast FROM podcasts WHERE slug_podcast = :slug";
    if ($id_podcast_atual !== null) {
        $sql_check .= " AND id_podcast != :id_atual";
    }
    $stmt = $pdo->prepare($sql_check);
    
    do {
        $params = [':slug' => $slug];
        if ($id_podcast_atual !== null) {
            $params[':id_atual'] = $id_podcast_atual;
        }
        $stmt->execute($params);
        if ($stmt->fetch()) { 
            $slug = $original_slug . '-' . $i; $i++; 
        } else {
            break;
        }
    } while (true);
    return $slug;
}

// Função para apagar uma pasta e todo o seu conteúdo recursivamente
function apagarPastaRecursivamente($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . DIRECTORY_SEPARATOR . $object)) {
                apagarPastaRecursivamente($dir . DIRECTORY_SEPARATOR . $object);
            } else {
                unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
    }
    return rmdir($dir);
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adicionar_podcast'])) {

    // Guardar dados submetidos para preencher o formulário em caso de erro
    $_SESSION['form_data_podcast'] = $_POST;

    // --- Obter dados do formulário ---
    $titulo_podcast = trim(filter_input(INPUT_POST, 'titulo_podcast', FILTER_SANITIZE_STRING));
    $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
    $id_assunto = filter_input(INPUT_POST, 'id_assunto', FILTER_VALIDATE_INT);
    $descricao_podcast = trim(filter_input(INPUT_POST, 'descricao_podcast', FILTER_SANITIZE_STRING));
    
    $tipo_material_apoio = $_POST['tipo_material_apoio'] ?? 'nenhum';
    $link_material_apoio_url_externo = trim(filter_input(INPUT_POST, 'link_material_apoio_url', FILTER_SANITIZE_URL));
    
    $visibilidade = $_POST['visibilidade'] ?? 'restrito_assinantes';
    $id_plano_minimo_input = filter_input(INPUT_POST, 'id_plano_minimo', FILTER_VALIDATE_INT);
    $id_plano_minimo = ($visibilidade === 'restrito_assinantes' && !empty($id_plano_minimo_input)) ? $id_plano_minimo_input : null;

    // --- Validação básica inicial ---
    if (empty($titulo_podcast) || !$id_categoria || !$id_assunto || empty($_FILES['audio_file']['name'])) {
        $_SESSION['form_message'] = "Título, categoria, assunto e ficheiro de áudio são obrigatórios.";
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }
    if ($tipo_material_apoio === 'upload_pdf' && empty($_FILES['pdf_file']['name'])) {
        $_SESSION['form_message'] = "Se selecionou 'Upload de PDF' para material de apoio, deve enviar um ficheiro.";
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }
    if ($tipo_material_apoio === 'link_externo' && empty($link_material_apoio_url_externo)) {
        $_SESSION['form_message'] = "Se selecionou 'Link Externo' para material de apoio, deve fornecer uma URL.";
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }
    if ($tipo_material_apoio === 'link_externo' && !empty($link_material_apoio_url_externo) && !filter_var($link_material_apoio_url_externo, FILTER_VALIDATE_URL)) {
        $_SESSION['form_message'] = "A URL fornecida para o material de apoio externo não é válida.";
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }


    // --- Obter slugs da categoria e assunto para estrutura de pastas ---
    $slug_categoria = '';
    $slug_assunto = '';
    try {
        $stmt_cat = $pdo->prepare("SELECT slug_categoria FROM categorias_podcast WHERE id_categoria = ?");
        $stmt_cat->execute([$id_categoria]);
        $cat_info = $stmt_cat->fetch();
        if ($cat_info) $slug_categoria = $cat_info['slug_categoria'];

        $stmt_ass = $pdo->prepare("SELECT slug_assunto FROM assuntos_podcast WHERE id_assunto = ?");
        $stmt_ass->execute([$id_assunto]);
        $ass_info = $stmt_ass->fetch();
        if ($ass_info) $slug_assunto = $ass_info['slug_assunto'];

        if (empty($slug_categoria) || empty($slug_assunto)) {
            throw new Exception("Não foi possível obter os slugs da categoria ou assunto.");
        }
    } catch (Exception $e) {
        error_log("Erro ao obter slugs para pastas: " . $e->getMessage());
        $_SESSION['form_message'] = "Erro ao preparar pastas para upload: " . $e->getMessage();
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }

    // --- Caminhos base para uploads ---
    // __DIR__ é /caminho/para/seu_projeto/admin
    // dirname(__DIR__) é /caminho/para/seu_projeto/
    $baseUploadPathOnServer = dirname(__DIR__) . '/uploads/'; 
    $baseUploadPathForDB = 'uploads/'; // Caminho relativo a partir da raiz do site para guardar no BD

    $url_audio_final_db = null;
    $link_material_apoio_final_db = null;

    // --- Processar Upload do Ficheiro de Áudio ---
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
        $audio_file = $_FILES['audio_file'];
        $audio_file_ext = strtolower(pathinfo($audio_file['name'], PATHINFO_EXTENSION));
        $allowed_audio_ext = ['mp3', 'wav', 'm4a', 'ogg', 'aac'];

        if (in_array($audio_file_ext, $allowed_audio_ext)) {
            if ($audio_file['size'] < 50000000) { // ~50MB
                $pasta_destino_audio_servidor = $baseUploadPathOnServer . 'audios/' . $slug_categoria . '/' . $slug_assunto . '/';
                $pasta_destino_audio_db = $baseUploadPathForDB . 'audios/' . $slug_categoria . '/' . $slug_assunto . '/';
                
                if (!is_dir($pasta_destino_audio_servidor)) {
                    if (!mkdir($pasta_destino_audio_servidor, 0775, true)) {
                        $_SESSION['form_message'] = "Erro crítico: Não foi possível criar a pasta de destino para o áudio.";
                        $_SESSION['form_message_type'] = "error";
                        header('Location: adicionar_podcast.php');
                        exit;
                    }
                }
                
                $audio_novo_nome = 'podcast_' . time() . '_' . uniqid() . '.' . $audio_file_ext;
                if (move_uploaded_file($audio_file['tmp_name'], $pasta_destino_audio_servidor . $audio_novo_nome)) {
                    $url_audio_final_db = $pasta_destino_audio_db . $audio_novo_nome;
                } else {
                    $_SESSION['form_message'] = "Erro ao guardar o ficheiro de áudio no servidor.";
                    $_SESSION['form_message_type'] = "error";
                    header('Location: adicionar_podcast.php');
                    exit;
                }
            } else {
                $_SESSION['form_message'] = "O ficheiro de áudio é demasiado grande (Máx: 50MB).";
                $_SESSION['form_message_type'] = "error";
                header('Location: adicionar_podcast.php');
                exit;
            }
        } else {
            $_SESSION['form_message'] = "Tipo de ficheiro de áudio não permitido. Permitidos: " . implode(', ', $allowed_audio_ext);
            $_SESSION['form_message_type'] = "error";
            header('Location: adicionar_podcast.php');
            exit;
        }
    } else if ($_FILES['audio_file']['error'] !== UPLOAD_ERR_NO_FILE) { // Se houve um erro diferente de "nenhum ficheiro enviado"
        $_SESSION['form_message'] = "Erro no upload do ficheiro de áudio: Código " . $_FILES['audio_file']['error'];
        $_SESSION['form_message_type'] = "error";
        header('Location: adicionar_podcast.php');
        exit;
    }
    // Se $url_audio_final_db continuar null e era obrigatório, a validação inicial já tratou.

    // --- Processar Material de Apoio ---
    if ($tipo_material_apoio === 'upload_pdf') {
        if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $pdf_file = $_FILES['pdf_file'];
            if (strtolower(pathinfo($pdf_file['name'], PATHINFO_EXTENSION)) === 'pdf') {
                if ($pdf_file['size'] < 10000000) { // ~10MB
                    $pasta_destino_material_servidor = $baseUploadPathOnServer . 'materiais/' . $slug_categoria . '/' . $slug_assunto . '/';
                    $pasta_destino_material_db = $baseUploadPathForDB . 'materiais/' . $slug_categoria . '/' . $slug_assunto . '/';

                    if (!is_dir($pasta_destino_material_servidor)) {
                         if (!mkdir($pasta_destino_material_servidor, 0775, true)) {
                            $_SESSION['form_message'] = "Erro crítico: Não foi possível criar a pasta de destino para o material de apoio.";
                            $_SESSION['form_message_type'] = "error";
                            header('Location: adicionar_podcast.php');
                            exit;
                        }
                    }
                    $pdf_novo_nome = 'material_' . time() . '_' . uniqid() . '.pdf';
                    if (move_uploaded_file($pdf_file['tmp_name'], $pasta_destino_material_servidor . $pdf_novo_nome)) {
                        $link_material_apoio_final_db = $pasta_destino_material_db . $pdf_novo_nome;
                    } else {
                        $_SESSION['form_message'] = "Erro ao guardar o PDF de apoio. Podcast será adicionado sem este material.";
                        $_SESSION['form_message_type'] = "warning"; // Aviso, não erro fatal para o podcast
                    }
                } else {
                     $_SESSION['form_message'] = "O ficheiro PDF é demasiado grande (Máx: 10MB). Material não adicionado.";
                     $_SESSION['form_message_type'] = "warning";
                }
            } else {
                $_SESSION['form_message'] = "Tipo de ficheiro de material inválido. Deve ser PDF. Material não adicionado.";
                $_SESSION['form_message_type'] = "warning";
            }
        } elseif ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $_SESSION['form_message'] = "Erro no upload do PDF de apoio: Código " . $_FILES['pdf_file']['error'] . ". Material não adicionado.";
            $_SESSION['form_message_type'] = "warning";
        }
    } elseif ($tipo_material_apoio === 'link_externo' && !empty($link_material_apoio_url_externo)) {
        $link_material_apoio_final_db = $link_material_apoio_url_externo; // Já validado no início
    }
    
    // --- Gerar Slug do Podcast ---
    $slug_podcast = gerarSlugPodcast($titulo_podcast, $pdo);

    // --- Inserir no Banco de Dados ---
    if ($url_audio_final_db) { // Só insere se o áudio foi carregado com sucesso
        try {
            $sql = "INSERT INTO podcasts (id_assunto, titulo_podcast, descricao_podcast, url_audio, link_material_apoio, visibilidade, id_plano_minimo, slug_podcast, data_publicacao) 
                    VALUES (:id_assunto, :titulo_podcast, :descricao_podcast, :url_audio, :link_material_apoio, :visibilidade, :id_plano_minimo, :slug_podcast, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_assunto', $id_assunto, PDO::PARAM_INT);
            $stmt->bindParam(':titulo_podcast', $titulo_podcast);
            $stmt->bindParam(':descricao_podcast', $descricao_podcast, PDO::PARAM_STR|PDO::PARAM_NULL);
            $stmt->bindParam(':url_audio', $url_audio_final_db);
            $stmt->bindParam(':link_material_apoio', $link_material_apoio_final_db, PDO::PARAM_STR|PDO::PARAM_NULL);
            $stmt->bindParam(':visibilidade', $visibilidade);
            $stmt->bindParam(':id_plano_minimo', $id_plano_minimo, PDO::PARAM_INT|PDO::PARAM_NULL);
            $stmt->bindParam(':slug_podcast', $slug_podcast);
            
            $stmt->execute();

            $success_message = "Podcast '" . htmlspecialchars($titulo_podcast) . "' adicionado com sucesso!";
            if (isset($_SESSION['form_message']) && $_SESSION['form_message_type'] === 'warning') { // Se houve aviso sobre material
                $success_message .= " " . $_SESSION['form_message']; // Anexa o aviso à mensagem de sucesso
            }
            $_SESSION['form_message'] = $success_message;
            $_SESSION['form_message_type'] = "success";
            unset($_SESSION['form_data_podcast']);

            $link = SITE_URL . '/player_podcast.php?slug=' . $slug_podcast;
            $subject = 'Novo podcast disponível';
            $msg = 'Confira o novo podcast: <a href="' . $link . '">' . htmlspecialchars($link) . '</a>';
            notifyUsers($pdo, 'notificar_novos_podcasts', $subject, $msg);

            header('Location: adicionar_podcast.php'); // Ou para gerir_podcasts.php
            exit;

        } catch (PDOException $e) {
            error_log("Erro de BD ao adicionar podcast: " . $e->getMessage());
            $_SESSION['form_message'] = "Erro ao adicionar podcast ao banco de dados: " . $e->getMessage();
            $_SESSION['form_message_type'] = "error";
            // Tentar apagar ficheiros carregados se a inserção no BD falhar
            if ($url_audio_final_db && file_exists($baseUploadPathOnServer . str_replace($baseUploadPathForDB, '', $url_audio_final_db))) {
                unlink($baseUploadPathOnServer . str_replace($baseUploadPathForDB, '', $url_audio_final_db));
            }
            if ($link_material_apoio_final_db && $tipo_material_apoio === 'upload_pdf' && file_exists($baseUploadPathOnServer . str_replace($baseUploadPathForDB, '', $link_material_apoio_final_db))) {
                unlink($baseUploadPathOnServer . str_replace($baseUploadPathForDB, '', $link_material_apoio_final_db));
            }
            header('Location: adicionar_podcast.php');
            exit;
        }
    } else {
        // Se $url_audio_final_db for null, significa que o upload do áudio falhou e a mensagem já foi definida.
        // Apenas redireciona de volta.
        header('Location: adicionar_podcast.php');
        exit;
    }

} else {
    header('Location: adicionar_podcast.php');
    exit;
}
?>
