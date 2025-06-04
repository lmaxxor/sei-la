<?php
// admin/gerir_podcasts.php

// Gestor de Sessão e Autenticação
require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php'); // Garante que apenas administradores acessem

// Conexão com Banco de Dados
require_once __DIR__ . '/../db/db_connect.php'; // $pdo deve estar disponível aqui
require_once __DIR__ . '/../sessao/csrf.php';
$csrfToken = getCsrfToken();

// --- FUNÇÕES UTILITÁRIAS ---

/**
 * Sanitiza texto para exibição segura em HTML.
 */
function sanitizarParaHTML(?string $texto): string {
    return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Envia uma resposta JSON e termina o script.
 */
function enviarRespostaJSON(bool $ok, string $msg, array $extra = []): void {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

/**
 * Gera um slug único para um podcast.
 */
function gerarSlugUnicoPodcast(string $texto, PDO $pdo, ?int $id_podcast_atual = null): string {
    $slugBase = $texto;
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slugBase);
    if ($slug === false || empty(trim((string)$slug))) {
        $slug = preg_replace('/[^\pL\pN\s-]/u', '', $slugBase);
    }
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        $slug = 'podcast-' . bin2hex(random_bytes(4));
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

/**
 * Apaga uma pasta e todo o seu conteúdo recursivamente.
 */
function apagarPastaRecursivamente(string $dir): bool {
    if (!is_dir($dir)) return false;
    $objects = scandir($dir);
    if ($objects === false) return false;
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path) && !is_link($path)) {
                if (!apagarPastaRecursivamente($path)) return false;
            } else {
                if (!@unlink($path)) return false;
            }
        }
    }
    return @rmdir($dir);
}

/**
 * Valida o tipo MIME de um arquivo carregado.
 */
function validarTipoMIME(string $caminhoTemporario, array $tiposPermitidos): bool {
    if (!file_exists($caminhoTemporario) || !is_readable($caminhoTemporario)) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) return false;
    $tipoMimeReal = finfo_file($finfo, $caminhoTemporario);
    finfo_close($finfo);
    return in_array($tipoMimeReal, $tiposPermitidos, true);
}

// --- PROCESSAMENTO DE AÇÕES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        enviarRespostaJSON(false, 'Erro de segurança.');
    }

    $action = $_POST['action'];
    $projectRoot = dirname(__DIR__);
    $baseUploadPathOnServer = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    $baseUploadPathForDB = 'uploads/';

    define('MAX_AUDIO_SIZE', 50 * 1024 * 1024); // 50MB
    define('MAX_PDF_SIZE', 10 * 1024 * 1024);   // 10MB
    $allowed_audio_mime_types = ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/x-hx-aac-adts']; // Adicionado 'audio/x-hx-aac-adts' para alguns AACs
    $allowed_pdf_mime_types = ['application/pdf', 'application/x-pdf'];


    if ($action === 'list_podcasts') {
        $filtro_categoria_id = filter_input(INPUT_POST, 'filtro_categoria', FILTER_VALIDATE_INT);
        $filtro_assunto_id = filter_input(INPUT_POST, 'filtro_assunto', FILTER_VALIDATE_INT);
        $busca_titulo_raw = $_POST['busca_titulo'] ?? null;
        $busca_titulo = !empty($busca_titulo_raw) ? trim($busca_titulo_raw) : null;

        $sql = "SELECT
                    p.id_podcast, p.titulo_podcast, p.data_publicacao, p.visibilidade, p.slug_podcast,
                    p.url_audio, /* ADICIONADO PARA LISTAGEM */
                    a.nome_assunto, a.slug_assunto as slug_assunto_podcast,
                    c.nome_categoria, c.slug_categoria
                FROM podcasts p
                JOIN assuntos_podcast a ON p.id_assunto = a.id_assunto
                JOIN categorias_podcast c ON a.id_categoria = c.id_categoria
                WHERE 1=1";
        $params = [];
        if ($filtro_categoria_id && $filtro_categoria_id !== 'todos') { $sql .= " AND c.id_categoria = :id_categoria"; $params[':id_categoria'] = $filtro_categoria_id; }
        if ($filtro_assunto_id && $filtro_assunto_id !== 'todos') { $sql .= " AND a.id_assunto = :id_assunto"; $params[':id_assunto'] = $filtro_assunto_id; }
        if ($busca_titulo !== null) { $sql .= " AND p.titulo_podcast LIKE :titulo"; $params[':titulo'] = '%' . $busca_titulo . '%'; }
        $sql .= " ORDER BY p.data_publicacao DESC, p.id_podcast DESC";

        try {
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $podcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($podcasts as &$p) {
                $p['titulo_podcast'] = sanitizarParaHTML($p['titulo_podcast']);
                $p['nome_categoria'] = sanitizarParaHTML($p['nome_categoria']);
                $p['nome_assunto'] = sanitizarParaHTML($p['nome_assunto']);
                $p['slug_podcast'] = sanitizarParaHTML($p['slug_podcast']);
                $p['url_audio'] = $p['url_audio'] ? sanitizarParaHTML($p['url_audio']) : null; // Sanitizar URL do áudio para exibição segura no title, por exemplo
            } unset($p);
            enviarRespostaJSON(true, 'Podcasts listados.', ['podcasts' => $podcasts]);
        } catch (PDOException $e) { error_log("Erro PDO ao listar podcasts: " . $e->getMessage()); enviarRespostaJSON(false, 'Erro ao buscar podcasts.'); }
    }
    elseif ($action === 'get_podcast_details') {
        $id_podcast = filter_input(INPUT_POST, 'id_podcast', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if (!$id_podcast) enviarRespostaJSON(false, 'ID do podcast inválido.');
        try {
            $stmt = $pdo->prepare("SELECT p.*, a.id_categoria, c.slug_categoria, a.slug_assunto as slug_assunto_atual FROM podcasts p JOIN assuntos_podcast a ON p.id_assunto = a.id_assunto JOIN categorias_podcast c ON a.id_categoria = c.id_categoria WHERE p.id_podcast = :id_podcast");
            $stmt->bindParam(':id_podcast', $id_podcast, PDO::PARAM_INT); $stmt->execute(); $podcast_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($podcast_data) { foreach ($podcast_data as $key => $value) { if ($value === null) $podcast_data[$key] = ''; } enviarRespostaJSON(true, 'Detalhes do podcast obtidos.', ['podcast' => $podcast_data]); }
            else { enviarRespostaJSON(false, 'Podcast não encontrado.'); }
        } catch (PDOException $e) { error_log("Erro PDO get_podcast_details: " . $e->getMessage()); enviarRespostaJSON(false, 'Erro ao carregar dados do podcast.'); }
    }
    elseif ($action === 'edit_podcast_submit') {
        $id_podcast = filter_input(INPUT_POST, 'id_podcast', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $titulo_podcast = sanitizarParaHTML(trim($_POST['titulo_podcast'] ?? ''));
        $id_categoria_nova = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $id_assunto_novo = filter_input(INPUT_POST, 'id_assunto', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $descricao_podcast = sanitizarParaHTML(trim($_POST['descricao_podcast'] ?? ''));
        $tipo_material_apoio = $_POST['tipo_material_apoio'] ?? 'nenhum';
        $link_material_apoio_url_externo_raw = $_POST['link_material_apoio_url'] ?? '';
        $link_material_apoio_url_externo = trim(filter_var($link_material_apoio_url_externo_raw, FILTER_SANITIZE_URL) ?: '');
        $visibilidade = $_POST['visibilidade'] ?? 'restrito_assinantes';
        $id_plano_minimo_input = filter_input(INPUT_POST, 'id_plano_minimo', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

        $url_audio_atual_db = $_POST['url_audio_atual'] ?? null;
        $link_material_apoio_atual_db = $_POST['link_material_apoio_atual'] ?? null;

        if (!$id_podcast || empty($titulo_podcast) || !$id_categoria_nova || !$id_assunto_novo) {
            enviarRespostaJSON(false, 'Dados inválidos. Título, categoria e assunto são obrigatórios.');
        }
        $valid_visibilidade = ['publico', 'restrito_assinantes'];
        if (!in_array($visibilidade, $valid_visibilidade, true)) enviarRespostaJSON(false, 'Opção de visibilidade inválida.');
        $id_plano_minimo = ($visibilidade === 'restrito_assinantes' && $id_plano_minimo_input) ? $id_plano_minimo_input : null;

        $valid_tipos_material = ['nenhum', 'upload_pdf', 'link_externo'];
        if (!in_array($tipo_material_apoio, $valid_tipos_material, true)) enviarRespostaJSON(false, 'Tipo de material de apoio inválido.');
        if ($tipo_material_apoio === 'link_externo') {
            if (empty($link_material_apoio_url_externo)) enviarRespostaJSON(false, 'URL do material de apoio externo é obrigatória.');
            elseif (!filter_var($link_material_apoio_url_externo, FILTER_VALIDATE_URL)) enviarRespostaJSON(false, 'A URL fornecida não é válida.');
        }

        $url_audio_final_db = $url_audio_atual_db;
        $link_material_apoio_final_db = $link_material_apoio_atual_db;
        $caminho_audio_antigo_servidor = $url_audio_atual_db ? $baseUploadPathOnServer . $url_audio_atual_db : null;
        $caminho_material_antigo_servidor = ($link_material_apoio_atual_db && !filter_var($link_material_apoio_atual_db, FILTER_VALIDATE_URL))
                                          ? $baseUploadPathOnServer . $link_material_apoio_atual_db : null;

        $pdo->beginTransaction();
        try {
            $stmt_slugs_novos = $pdo->prepare("SELECT c.slug_categoria, a.slug_assunto FROM categorias_podcast c JOIN assuntos_podcast a ON c.id_categoria = a.id_categoria WHERE c.id_categoria = :id_cat AND a.id_assunto = :id_ass");
            $stmt_slugs_novos->execute([':id_cat' => $id_categoria_nova, ':id_ass' => $id_assunto_novo]);
            $novos_slugs = $stmt_slugs_novos->fetch(PDO::FETCH_ASSOC);
            if (!$novos_slugs) throw new Exception("Nova categoria ou assunto não encontrados.");
            $slug_nova_categoria = $novos_slugs['slug_categoria'];
            $slug_novo_assunto = $novos_slugs['slug_assunto'];

            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $audio_file = $_FILES['audio_file'];
                if ($audio_file['size'] > MAX_AUDIO_SIZE) throw new Exception('Áudio grande demais (Máx: '.(MAX_AUDIO_SIZE / 1024 / 1024).'MB).');
                if (!validarTipoMIME($audio_file['tmp_name'], $allowed_audio_mime_types)) throw new Exception('Tipo de áudio não permitido.');
                $audio_file_ext = strtolower(pathinfo($audio_file['name'], PATHINFO_EXTENSION));
                $pasta_destino_audio_servidor = $baseUploadPathOnServer . 'audios/' . $slug_nova_categoria . '/' . $slug_novo_assunto . '/';
                $pasta_destino_audio_db = $baseUploadPathForDB . 'audios/' . $slug_nova_categoria . '/' . $slug_novo_assunto . '/';
                if (!is_dir($pasta_destino_audio_servidor)) { if (!mkdir($pasta_destino_audio_servidor, 0775, true)) throw new Exception("Falha ao criar pasta de áudio."); }
                $audio_novo_nome = 'podcast_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $audio_file_ext;
                $novo_caminho_audio_servidor = $pasta_destino_audio_servidor . $audio_novo_nome;
                if (move_uploaded_file($audio_file['tmp_name'], $novo_caminho_audio_servidor)) {
                    if ($caminho_audio_antigo_servidor && file_exists($caminho_audio_antigo_servidor) && $caminho_audio_antigo_servidor !== $novo_caminho_audio_servidor) { @unlink($caminho_audio_antigo_servidor); }
                    $url_audio_final_db = $pasta_destino_audio_db . $audio_novo_nome;
                } else { throw new Exception('Erro ao guardar novo áudio.');}
            }

            $material_antigo_para_apagar = $caminho_material_antigo_servidor;
            if ($tipo_material_apoio === 'upload_pdf' && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                $pdf_file = $_FILES['pdf_file'];
                if ($pdf_file['size'] > MAX_PDF_SIZE) throw new Exception('PDF grande demais (Máx: '.(MAX_PDF_SIZE / 1024 / 1024).'MB).');
                if (!validarTipoMIME($pdf_file['tmp_name'], $allowed_pdf_mime_types)) throw new Exception('Tipo de PDF não permitido.');
                $pdf_file_ext = strtolower(pathinfo($pdf_file['name'], PATHINFO_EXTENSION));
                $pasta_destino_material_servidor = $baseUploadPathOnServer . 'materiais/' . $slug_nova_categoria . '/' . $slug_novo_assunto . '/';
                $pasta_destino_material_db = $baseUploadPathForDB . 'materiais/' . $slug_nova_categoria . '/' . $slug_novo_assunto . '/';
                if (!is_dir($pasta_destino_material_servidor)) { if (!mkdir($pasta_destino_material_servidor, 0775, true)) throw new Exception("Falha ao criar pasta de material."); }
                $pdf_novo_nome = 'material_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $pdf_file_ext;
                $novo_caminho_material_servidor = $pasta_destino_material_servidor . $pdf_novo_nome;
                if (move_uploaded_file($pdf_file['tmp_name'], $novo_caminho_material_servidor)) {
                    if ($material_antigo_para_apagar && file_exists($material_antigo_para_apagar) && $material_antigo_para_apagar !== $novo_caminho_material_servidor) { @unlink($material_antigo_para_apagar); }
                    $link_material_apoio_final_db = $pasta_destino_material_db . $pdf_novo_nome;
                } else { throw new Exception('Erro ao guardar novo PDF.'); }
            } elseif ($tipo_material_apoio === 'link_externo') {
                if ($material_antigo_para_apagar && file_exists($material_antigo_para_apagar)) { @unlink($material_antigo_para_apagar); }
                $link_material_apoio_final_db = $link_material_apoio_url_externo;
            } elseif ($tipo_material_apoio === 'nenhum') {
                if ($material_antigo_para_apagar && file_exists($material_antigo_para_apagar)) { @unlink($material_antigo_para_apagar); }
                $link_material_apoio_final_db = null;
            }

            $stmt_old_podcast = $pdo->prepare("SELECT titulo_podcast, slug_podcast FROM podcasts WHERE id_podcast = ?");
            $stmt_old_podcast->execute([$id_podcast]);
            $old_podcast_data = $stmt_old_podcast->fetch();
            if (!$old_podcast_data) throw new Exception("Podcast original não encontrado.");
            $slug_podcast_final = $old_podcast_data['slug_podcast'];
            if ($titulo_podcast !== $old_podcast_data['titulo_podcast']) {
                $slug_podcast_final = gerarSlugUnicoPodcast($titulo_podcast, $pdo, $id_podcast);
            }

            $sql_update = "UPDATE podcasts SET id_assunto = :id_assunto, titulo_podcast = :titulo_podcast, descricao_podcast = :descricao_podcast, url_audio = :url_audio, link_material_apoio = :link_material_apoio, visibilidade = :visibilidade, id_plano_minimo = :id_plano_minimo, slug_podcast = :slug_podcast, data_atualizacao = NOW() WHERE id_podcast = :id_podcast_edit";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                ':id_assunto' => $id_assunto_novo, ':titulo_podcast' => $titulo_podcast, ':descricao_podcast' => $descricao_podcast,
                ':url_audio' => $url_audio_final_db, ':link_material_apoio' => $link_material_apoio_final_db,
                ':visibilidade' => $visibilidade, ':id_plano_minimo' => $id_plano_minimo,
                ':slug_podcast' => $slug_podcast_final, ':id_podcast_edit' => $id_podcast
            ]);
            $pdo->commit();
            enviarRespostaJSON(true, 'Podcast atualizado com sucesso!');
        } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log("PDO Erro edit_podcast: " . $e->getMessage()); enviarRespostaJSON(false, 'Erro de BD ao atualizar.');
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log("Geral Erro edit_podcast: " . $e->getMessage()); enviarRespostaJSON(false, $e->getMessage()); }
    }
    elseif ($action === 'delete_podcast') {
        $id_podcast = filter_input(INPUT_POST, 'id_podcast', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if (!$id_podcast) enviarRespostaJSON(false, 'ID do podcast inválido.');
        $pdo->beginTransaction();
        try {
            $stmt_info = $pdo->prepare("SELECT p.url_audio, p.link_material_apoio FROM podcasts p WHERE p.id_podcast = :id_podcast");
            $stmt_info->bindParam(':id_podcast', $id_podcast, PDO::PARAM_INT); $stmt_info->execute(); $podcast_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
            if (!$podcast_info) { $pdo->rollBack(); enviarRespostaJSON(false, 'Podcast não encontrado.'); }
            $stmt_delete = $pdo->prepare("DELETE FROM podcasts WHERE id_podcast = :id_podcast");
            $stmt_delete->bindParam(':id_podcast', $id_podcast, PDO::PARAM_INT); $stmt_delete->execute();
            if ($stmt_delete->rowCount() > 0) {
                $audio_path_server = $podcast_info['url_audio'] ? $baseUploadPathOnServer . $podcast_info['url_audio'] : null;
                if ($audio_path_server && file_exists($audio_path_server)) @unlink($audio_path_server);
                $material_path_server = ($podcast_info['link_material_apoio'] && !filter_var($podcast_info['link_material_apoio'], FILTER_VALIDATE_URL)) ? $baseUploadPathOnServer . $podcast_info['link_material_apoio'] : null;
                if ($material_path_server && file_exists($material_path_server)) @unlink($material_path_server);
                $pdo->commit(); enviarRespostaJSON(true, 'Podcast apagado com sucesso!');
            } else { $pdo->rollBack(); enviarRespostaJSON(false, 'Erro ao apagar podcast do BD.'); }
        } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log("PDO Erro delete_podcast: " . $e->getMessage()); if (str_contains($e->getMessage(), "foreign key")) { enviarRespostaJSON(false, 'Não é possível apagar: podcast possui dados relacionados.');} else {enviarRespostaJSON(false, 'Erro ao excluir.');}}
    }
     else {
        enviarRespostaJSON(false, 'Ação desconhecida.');
    }
}

// --- Lógica para exibição da página HTML ---
$pageTitle = "Gerir Podcasts";
$userName = sanitizarParaHTML($_SESSION['user_nome_completo'] ?? 'Admin');
$avatarUrl = sanitizarParaHTML($_SESSION['user_avatar_url'] ?? '');
if (empty($avatarUrl)) { $initials = ''; $nameParts = explode(' ', trim($userName)); $initials .= !empty($nameParts[0]) ? strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8')) : 'A'; if (count($nameParts) > 1) $initials .= strtoupper(mb_substr(end($nameParts), 0, 1, 'UTF-8')); elseif (mb_strlen($nameParts[0], 'UTF-8') > 1 && $initials === strtoupper(mb_substr($nameParts[0], 0, 1, 'UTF-8'))) $initials .= strtoupper(mb_substr($nameParts[0], 1, 1, 'UTF-8')); if(empty($initials) || mb_strlen($initials, 'UTF-8') > 2) $initials = "AD"; $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($initials) . "&background=0d6efd&color=fff&size=40&rounded=true&bold=true"; }

$categorias_filtro = []; $assuntos_filtro_todos_php = []; $planos_assinatura = [];
try {
    $stmt_cat_filtro = $pdo->query("SELECT id_categoria, nome_categoria, slug_categoria FROM categorias_podcast ORDER BY nome_categoria ASC");
    $categorias_filtro = $stmt_cat_filtro->fetchAll(PDO::FETCH_ASSOC);
    $stmt_ass_filtro = $pdo->query("SELECT id_assunto, id_categoria, nome_assunto, slug_assunto FROM assuntos_podcast ORDER BY nome_assunto ASC");
    $assuntos_filtro_todos_php = $stmt_ass_filtro->fetchAll(PDO::FETCH_ASSOC);
    $stmt_planos = $pdo->query("SELECT id_plano, nome_plano FROM planos_assinatura WHERE ativo = TRUE ORDER BY nome_plano ASC");
    $planos_assinatura = $stmt_planos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Erro PDO ao buscar dados para filtros/modais: " . $e->getMessage());}
$assuntos_filtro_todos_json = json_encode($assuntos_filtro_todos_php);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <title><?php echo sanitizarParaHTML($pageTitle); ?> - Admin Audio TO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .sidebar { min-height: 100vh; width: 280px; background-color: #212529; color: #fff; } /* Bootstrap dark */
        .sidebar .nav-link { color: rgba(255,255,255,.8); padding-top: .75rem; padding-bottom: .75rem; }
        .sidebar .nav-link:hover { color: #fff; background-color: #343a40; }
        .sidebar .nav-link.active { color: #fff; background-color: #0d6efd; } /* Bootstrap primary */
        .sidebar .nav-link i.fa-fw { width: 1.25em; } /* Para alinhar ícones na sidebar */
        .main-content-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        .top-header { border-bottom: 1px solid #dee2e6; }
        .table th { font-weight: 600; }
        .btn-spinner { display: inline-block; width: 1em; height: 1em; vertical-align: -0.125em; border: .2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: .75s linear infinite spinner-border; }
        @keyframes spinner-border { to { transform: rotate(360deg); } }
        .alert-sm { padding: 0.5rem 0.75rem; font-size: 0.875rem; } /* Alertas menores para infos */
        .form-label { font-weight: 500; }
        .badge.bg-success-subtle { background-color: var(--bs-success-bg-subtle) !important; color: var(--bs-success-text-emphasis) !important; }
        .badge.bg-warning-subtle { background-color: var(--bs-warning-bg-subtle) !important; color: var(--bs-warning-text-emphasis) !important; }
    </style>
</head>
<body>

<div class="d-flex">
    <nav id="sidebarMenu" class="sidebar p-3 d-none d-md-flex flex-column">
        <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <span class="fs-4">Admin Audio TO</span>
        </a>
        <hr class="text-white">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="index.php" class="nav-link text-white">
                    <i class="fas fa-home fa-fw me-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="gerir_podcasts.php" class="nav-link active">
                    <i class="fas fa-podcast fa-fw me-2"></i> Gerir Podcasts
                </a>
            </li>
            </ul>
        <hr class="text-white">
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo $avatarUrl; ?>" alt="" width="32" height="32" class="rounded-circle me-2">
                <strong><?php echo $userName; ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="#">Configurações</a></li>
                <li><a class="dropdown-item" href="#">Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php">Sair</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content-wrapper">
        <header class="navbar navbar-light bg-light sticky-top top-header p-0 shadow-sm">
            <div class="container-fluid">
                <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                 <div class="navbar-nav ms-auto">
                    <div class="nav-item text-nowrap d-md-none"> <a class="nav-link px-3" href="#"><?php echo $userName; ?></a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-grow-1 p-3 p-md-4">
            <div class="container-fluid">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo sanitizarParaHTML($pageTitle); ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="adicionar_podcast.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus me-2"></i>Adicionar Podcast
                        </a>
                    </div>
                </div>

                <div id="feedbackMessage" class="d-none"></div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form id="formFiltros">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label for="filtro_titulo" class="form-label">Buscar por Título</label>
                                    <input type="text" id="filtro_titulo" name="filtro_titulo" class="form-control form-control-sm" placeholder="Digite o título...">
                                </div>
                                <div class="col-md-3">
                                    <label for="filtro_categoria" class="form-label">Categoria</label>
                                    <select id="filtro_categoria" name="filtro_categoria" class="form-select form-select-sm">
                                        <option value="todos">Todas Categorias</option>
                                        <?php foreach ($categorias_filtro as $cat): ?>
                                            <option value="<?php echo $cat['id_categoria']; ?>"><?php echo sanitizarParaHTML($cat['nome_categoria']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filtro_assunto" class="form-label">Assunto</label>
                                    <select id="filtro_assunto" name="filtro_assunto" class="form-select form-select-sm" disabled>
                                        <option value="todos">Todos Assuntos</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                   <button type="button" id="btnLimparFiltros" class="btn btn-outline-secondary btn-sm w-100">Limpar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                     <div class="card-header bg-light py-2">
                        <h5 class="mb-0 card-title">Lista de Podcasts</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3" style="width:25%;">Título</th>
                                    <th style="width:15%;">Categoria</th>
                                    <th class="d-none d-sm-table-cell" style="width:15%;">Assunto</th>
                                    <th class="d-none d-md-table-cell" style="width:10%;">Publicado</th>
                                    <th class="text-center" style="width:10%;">Áudio</th>
                                    <th style="width:10%;">Visibilidade</th>
                                    <th class="text-end px-3" style="width:15%;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="podcastListTableBody">
                                <tr id="loadingPodcasts"><td colspan="7" class="text-center p-4"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Carregando podcasts...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="noPodcastsMessage" class="text-center p-4 text-muted d-none">
                        <i class="fas fa-microphone-slash fa-3x mb-2 text-secondary"></i><br>
                        Nenhum podcast encontrado com os filtros atuais.
                    </div>
                </div>
            </div>
        </main>
         <footer class="bg-light text-center text-muted p-3 mt-auto border-top">
            <small>&copy; <?php echo date("Y"); ?> Painel Admin Audio TO. Todos os direitos reservados.</small>
        </footer>
    </div>
</div>

<div class="modal fade" id="modalEditarPodcast" tabindex="-1" aria-labelledby="modalEditarPodcastLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formEditarPodcast" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="id_podcast" id="edit_id_podcast">
                <input type="hidden" name="action" value="edit_podcast_submit">
                <input type="hidden" name="url_audio_atual" id="edit_url_audio_atual">
                <input type="hidden" name="link_material_apoio_atual" id="edit_link_material_apoio_atual">
                <input type="hidden" name="slug_categoria_atual_para_pasta" id="edit_slug_categoria_atual_para_pasta">
                <input type="hidden" name="slug_assunto_atual_para_pasta" id="edit_slug_assunto_atual_para_pasta">

                <div class="modal-header">
                    <h5 class="modal-title" id="editModalTitle">Editar Podcast</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="editModalFeedback" class="d-none mb-3"></div>

                    <div class="mb-3">
                        <label for="edit_titulo_podcast" class="form-label">Título do Podcast <span class="text-danger">*</span></label>
                        <input type="text" id="edit_titulo_podcast" name="titulo_podcast" class="form-control form-control-sm" required maxlength="255">
                        <div class="invalid-feedback">Título é obrigatório.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_id_categoria" class="form-label">Categoria <span class="text-danger">*</span></label>
                            <select id="edit_id_categoria" name="id_categoria" class="form-select form-select-sm" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias_filtro as $cat): ?>
                                    <option value="<?php echo $cat['id_categoria']; ?>" data-slug-categoria="<?php echo sanitizarParaHTML($cat['slug_categoria']); ?>"><?php echo sanitizarParaHTML($cat['nome_categoria']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Categoria é obrigatória.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_id_assunto" class="form-label">Assunto <span class="text-danger">*</span></label>
                            <select id="edit_id_assunto" name="id_assunto" class="form-select form-select-sm" required disabled>
                                <option value="">Selecione categoria primeiro...</option>
                            </select>
                            <div class="invalid-feedback">Assunto é obrigatório.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_descricao_podcast" class="form-label">Descrição</label>
                        <textarea id="edit_descricao_podcast" name="descricao_podcast" rows="3" class="form-control form-control-sm"></textarea>
                    </div>

                    <hr class="my-3">
                    <h6>Ficheiro de Áudio</h6>
                    <div id="edit_audio_existente_info" class="alert alert-secondary alert-sm py-1 px-2 d-none" role="alert" style="font-size: 0.875rem;"></div>
                    <div class="mb-3">
                        <label for="edit_audio_file" class="form-label">Substituir Áudio (opcional)</label>
                        <input type="file" name="audio_file" id="edit_audio_file" class="form-control form-control-sm" accept="audio/*">
                        <small class="form-text text-muted">Máx: <?php echo MAX_AUDIO_SIZE / 1024 / 1024; ?>MB. Tipos permitidos: mp3, ogg, wav, m4a, aac.</small>
                    </div>

                    <hr class="my-3">
                    <h6>Material de Apoio</h6>
                     <div id="edit_material_existente_info" class="alert alert-secondary alert-sm py-1 px-2 d-none" role="alert" style="font-size: 0.875rem;"></div>
                    <div class="mb-3">
                        <label for="edit_tipo_material_apoio" class="form-label">Tipo de Material</label>
                        <select name="tipo_material_apoio" id="edit_tipo_material_apoio" class="form-select form-select-sm">
                            <option value="nenhum">Nenhum</option>
                            <option value="upload_pdf">Substituir/Adicionar PDF</option>
                            <option value="link_externo">Usar/Alterar Link Externo</option>
                        </select>
                    </div>
                    <div id="edit_campo_upload_pdf" class="mb-3 d-none">
                        <label for="edit_pdf_file" class="form-label">Ficheiro PDF (opcional)</label>
                        <input type="file" name="pdf_file" id="edit_pdf_file" class="form-control form-control-sm" accept=".pdf,application/pdf">
                         <small class="form-text text-muted">Máx: <?php echo MAX_PDF_SIZE / 1024 / 1024; ?>MB.</small>
                    </div>
                    <div id="edit_campo_link_externo" class="mb-3 d-none">
                        <label for="edit_link_material_apoio_url" class="form-label">URL do Material</label>
                        <input type="url" name="link_material_apoio_url" id="edit_link_material_apoio_url" class="form-control form-control-sm" placeholder="https://exemplo.com/material.pdf">
                        <div class="invalid-feedback">URL inválida.</div>
                    </div>

                    <hr class="my-3">
                    <h6>Configurações de Acesso</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_visibilidade" class="form-label">Visibilidade <span class="text-danger">*</span></label>
                            <select id="edit_visibilidade" name="visibilidade" class="form-select form-select-sm" required>
                                <option value="restrito_assinantes">Restrito a Assinantes</option>
                                <option value="publico">Público</option>
                            </select>
                        </div>
                        <div id="edit_campo_plano_minimo" class="col-md-6 d-none">
                            <label for="edit_id_plano_minimo" class="form-label">Plano Mínimo (se restrito)</label>
                            <select id="edit_id_plano_minimo" name="id_plano_minimo" class="form-select form-select-sm">
                                <option value="">Todos Assinantes Ativos</option>
                                <?php foreach ($planos_assinatura as $plano): ?>
                                    <option value="<?php echo $plano['id_plano']; ?>"><?php echo sanitizarParaHTML($plano['nome_plano']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-submit-edit-podcast">
                        <span class="btn-text">Guardar Alterações</span>
                        <span class="btn-spinner d-none ms-1"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
<script>
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>"']/g, function (match) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
        });
    }

    const todosAssuntosParaFiltro = <?php echo $assuntos_filtro_todos_json; ?>;
    const todosAssuntosParaModal = <?php echo $assuntos_filtro_todos_json; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const filtroCategoriaSelect = document.getElementById('filtro_categoria');
        const filtroAssuntoSelect = document.getElementById('filtro_assunto');
        const filtroTituloInput = document.getElementById('filtro_titulo');
        const btnLimparFiltros = document.getElementById('btnLimparFiltros');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const podcastListTableBody = document.getElementById('podcastListTableBody');
        const loadingPodcastsRow = document.getElementById('loadingPodcasts');
        const noPodcastsMessageDiv = document.getElementById('noPodcastsMessage');
        const feedbackMessageDiv = document.getElementById('feedbackMessage');

        const modalEditarPodcastEl = document.getElementById('modalEditarPodcast');
        const modalEditarPodcastBS = new bootstrap.Modal(modalEditarPodcastEl);
        const formEditarPodcast = document.getElementById('formEditarPodcast');
        const btnSubmitEditPodcast = document.getElementById('btn-submit-edit-podcast');
        const editModalTitle = document.getElementById('editModalTitle');
        const editModalFeedback = document.getElementById('editModalFeedback');

        const editCategoriaSelect = document.getElementById('edit_id_categoria');
        const editAssuntoSelect = document.getElementById('edit_id_assunto');
        const editTipoMaterialSelect = document.getElementById('edit_tipo_material_apoio');
        const editCampoUploadPdf = document.getElementById('edit_campo_upload_pdf');
        const editInputPdfFile = document.getElementById('edit_pdf_file');
        const editCampoLinkExterno = document.getElementById('edit_campo_link_externo');
        const editInputLinkMaterialUrl = document.getElementById('edit_link_material_apoio_url');
        const editVisibilidadeSelect = document.getElementById('edit_visibilidade');
        const editCampoPlanoMinimo = document.getElementById('edit_campo_plano_minimo');
        const editInputPlanoMinimo = document.getElementById('edit_id_plano_minimo');
        const editAudioExistenteInfo = document.getElementById('edit_audio_existente_info');
        const editMaterialExistenteInfo = document.getElementById('edit_material_existente_info');
        const editSlugCategoriaAtualInput = document.getElementById('edit_slug_categoria_atual_para_pasta');
        const editSlugAssuntoAtualInput = document.getElementById('edit_slug_assunto_atual_para_pasta');

        function setButtonLoadingState(button, isLoading) {
            const textEl = button.querySelector('.btn-text');
            const spinnerEl = button.querySelector('.btn-spinner');
            if (isLoading) {
                button.disabled = true;
                if (textEl) textEl.classList.add('d-none'); // Bootstrap usa d-none
                if (spinnerEl) spinnerEl.classList.remove('d-none');
            } else {
                button.disabled = false;
                if (textEl) textEl.classList.remove('d-none');
                if (spinnerEl) spinnerEl.classList.add('d-none');
            }
        }

        function exibirFeedback(el, msg, isSuccess) {
            if (!el) el = feedbackMessageDiv;
            const alertClass = isSuccess ? 'alert-success' : 'alert-danger';
            el.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show py-2" role="alert">
                              ${escapeHTML(msg)}
                              <button type="button" class="btn-close btn-sm py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>`;
            el.classList.remove('d-none');
            if (el === feedbackMessageDiv) {
                setTimeout(() => {
                    const alertNode = el.querySelector('.alert');
                    if(alertNode) {
                        const alertInstance = bootstrap.Alert.getInstance(alertNode);
                        if (alertInstance) alertInstance.close();
                        else el.classList.add('d-none');
                    } else {
                         el.classList.add('d-none');
                    }
                }, 7000);
            }
        }
        function limparFeedback(el) { if(el) { el.innerHTML = ''; el.classList.add('d-none'); }}

        function popularFiltroAssuntos(idCategoriaSelecionada) {
            filtroAssuntoSelect.innerHTML = '<option value="todos">Todos Assuntos</option>';
            if (!idCategoriaSelecionada || idCategoriaSelecionada === 'todos') {
                filtroAssuntoSelect.disabled = true; return;
            }
            const assuntosFiltrados = todosAssuntosParaFiltro.filter(assunto => String(assunto.id_categoria) === String(idCategoriaSelecionada));
            if (assuntosFiltrados.length > 0) {
                assuntosFiltrados.forEach(assunto => {
                    const option = document.createElement('option');
                    option.value = assunto.id_assunto;
                    option.textContent = escapeHTML(assunto.nome_assunto);
                    filtroAssuntoSelect.appendChild(option);
                });
                filtroAssuntoSelect.disabled = false;
            } else {
                filtroAssuntoSelect.disabled = true;
            }
        }

        function popularEditAssuntos(idCategoriaSelecionada, idAssuntoPreSelecionado = null) {
            editAssuntoSelect.innerHTML = '<option value="">Carregando...</option>';
            editAssuntoSelect.disabled = true;
            const assuntosFiltrados = todosAssuntosParaModal.filter(assunto => String(assunto.id_categoria) === String(idCategoriaSelecionada));
            editAssuntoSelect.innerHTML = '<option value="">Selecione um Assunto...</option>';
            if (assuntosFiltrados.length > 0) {
                assuntosFiltrados.forEach(assunto => {
                    const option = document.createElement('option');
                    option.value = assunto.id_assunto;
                    option.textContent = escapeHTML(assunto.nome_assunto);
                    option.dataset.slugAssunto = assunto.slug_assunto;
                    if (idAssuntoPreSelecionado && String(assunto.id_assunto) === String(idAssuntoPreSelecionado)) {
                        option.selected = true;
                        if(editSlugAssuntoAtualInput) editSlugAssuntoAtualInput.value = assunto.slug_assunto;
                    }
                    editAssuntoSelect.appendChild(option);
                });
                editAssuntoSelect.disabled = false;
            } else {
                editAssuntoSelect.innerHTML = '<option value="">Nenhum assunto</option>';
            }
        }
        function atualizarEditSlugsOcultos() {
            const selectedCategoriaOption = editCategoriaSelect.options[editCategoriaSelect.selectedIndex];
            if (selectedCategoriaOption && editSlugCategoriaAtualInput) {
                editSlugCategoriaAtualInput.value = selectedCategoriaOption.dataset.slugCategoria || '';
            }
            const selectedAssuntoOption = editAssuntoSelect.options[editAssuntoSelect.selectedIndex];
            if (selectedAssuntoOption && editSlugAssuntoAtualInput) {
                editSlugAssuntoAtualInput.value = selectedAssuntoOption.dataset.slugAssunto || '';
            }
        }

        filtroCategoriaSelect.addEventListener('change', function() { popularFiltroAssuntos(this.value); fetchPodcasts(); });
        filtroAssuntoSelect.addEventListener('change', fetchPodcasts);
        filtroTituloInput.addEventListener('input', debounce(fetchPodcasts, 400));
        if(btnLimparFiltros) {
            btnLimparFiltros.addEventListener('click', () => {
                document.getElementById('formFiltros').reset();
                popularFiltroAssuntos('todos'); // Reseta e desabilita filtro de assunto
                fetchPodcasts();
            });
        }


        editCategoriaSelect.addEventListener('change', function() { popularEditAssuntos(this.value); atualizarEditSlugsOcultos(); });
        editAssuntoSelect.addEventListener('change', atualizarEditSlugsOcultos);

        editTipoMaterialSelect.addEventListener('change', function() {
            editCampoUploadPdf.classList.add('d-none');
            editInputPdfFile.required = false; editInputPdfFile.value = ''; // Limpa o campo
            editCampoLinkExterno.classList.add('d-none');
            editInputLinkMaterialUrl.required = false; editInputLinkMaterialUrl.value = ''; // Limpa o campo

            if (this.value === 'upload_pdf') {
                editCampoUploadPdf.classList.remove('d-none');
            } else if (this.value === 'link_externo') {
                editCampoLinkExterno.classList.remove('d-none');
                editInputLinkMaterialUrl.required = true;
            }
        });
        editVisibilidadeSelect.addEventListener('change', function() {
            if (this.value === 'restrito_assinantes') {
                editCampoPlanoMinimo.classList.remove('d-none');
            } else {
                editCampoPlanoMinimo.classList.add('d-none');
                editInputPlanoMinimo.value = '';
            }
        });

        function fetchPodcasts() {
            loadingPodcastsRow.style.display = 'table-row';
            noPodcastsMessageDiv.classList.add('d-none');
            podcastListTableBody.innerHTML = '';
            podcastListTableBody.appendChild(loadingPodcastsRow);
            const formData = new FormData();
            formData.append('action', 'list_podcasts');
            formData.append('filtro_categoria', filtroCategoriaSelect.value);
            formData.append('filtro_assunto', filtroAssuntoSelect.value);
            formData.append('busca_titulo', filtroTituloInput.value);
            if (csrfToken) formData.append('csrf_token', csrfToken);

            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => { if (!response.ok) throw new Error(`HTTP error ${response.status}`); return response.json(); })
            .then(data => {
                loadingPodcastsRow.style.display = 'none';
                podcastListTableBody.innerHTML = '';
                if (data.ok && data.podcasts) {
                    if (data.podcasts.length === 0) {
                        noPodcastsMessageDiv.classList.remove('d-none');
                    } else {
                        data.podcasts.forEach(p => {
                            const dataPub = new Date(p.data_publicacao.replace(' ', 'T') + 'Z');
                            const dataFormatada = dataPub.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
                            const visibilidadeTexto = p.visibilidade === 'publico' ? 'Público' : 'Assinantes';
                            const visibilidadeBadge = p.visibilidade === 'publico' ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis';
                            let audioDisplay = '<i class="fas fa-volume-mute text-muted fa-fw" title="Sem áudio"></i>';
                            if (p.url_audio) {
                                const audioFileName = p.url_audio.split('/').pop();
                                audioDisplay = `<i class="fas fa-volume-up text-primary fa-fw" title="${escapeHTML(audioFileName)}"></i>`;
                            }
                            const rowHTML = `
                                <tr class="align-middle">
                                    <td class="px-3"><div class="fw-semibold">${p.titulo_podcast}</div><small class="text-muted">${p.slug_podcast}</small></td>
                                    <td>${p.nome_categoria}</td>
                                    <td class="d-none d-sm-table-cell">${p.nome_assunto}</td>
                                    <td class="d-none d-md-table-cell">${dataFormatada}</td>
                                    <td class="text-center">${audioDisplay}</td>
                                    <td><span class="badge rounded-pill ${visibilidadeBadge} py-1 px-2">${escapeHTML(visibilidadeTexto)}</span></td>
                                    <td class="text-end px-3">
                                        <button class="btn btn-outline-primary btn-sm edit-podcast-btn me-1 py-0 px-1" data-id="${p.id_podcast}" title="Editar"><i class="fas fa-pencil-alt fa-fw"></i></button>
                                        <button class="btn btn-outline-danger btn-sm delete-podcast-btn py-0 px-1" data-id="${p.id_podcast}" data-titulo="${p.titulo_podcast}" title="Apagar"><i class="fas fa-trash-alt fa-fw"></i></button>
                                    </td></tr>`;
                            podcastListTableBody.insertAdjacentHTML('beforeend', rowHTML);
                        });
                        addTableActionListeners();
                    }
                } else { exibirFeedback(feedbackMessageDiv, data.msg || 'Erro ao carregar.', false); noPodcastsMessageDiv.classList.remove('d-none'); }
            })
            .catch(error => { loadingPodcastsRow.style.display = 'none'; podcastListTableBody.innerHTML = ''; noPodcastsMessageDiv.classList.remove('d-none'); console.error('Fetch error:', error); exibirFeedback(feedbackMessageDiv,'Erro de comunicação.', false); });
        }

        function addTableActionListeners() {
            document.querySelectorAll('.edit-podcast-btn').forEach(b => { b.removeEventListener('click', handleEditClick); b.addEventListener('click', handleEditClick); });
            document.querySelectorAll('.delete-podcast-btn').forEach(b => { b.removeEventListener('click', handleDeleteClick); b.addEventListener('click', handleDeleteClick); });
        }
        function handleEditClick() { abrirModalParaEditarPodcast(this.dataset.id); }
        function handleDeleteClick() {
            const podcastId = this.dataset.id; const podcastTitulo = this.dataset.titulo;
            if (confirm(`Tem certeza que deseja apagar "${podcastTitulo}"? Arquivos associados serão removidos.`)) {
                const formData = new FormData();
                formData.append('action', 'delete_podcast');
                formData.append('id_podcast', podcastId);
                if (csrfToken) formData.append('csrf_token', csrfToken);
                fetch(window.location.pathname, { method: 'POST', body: formData })
                .then(r => r.json()).then(d => { exibirFeedback(feedbackMessageDiv, d.msg, d.ok); if (d.ok) fetchPodcasts(); })
                .catch(e => { console.error("Delete error:", e); exibirFeedback(feedbackMessageDiv, "Erro ao apagar.", false); });
            }
        }

        function abrirModalParaEditarPodcast(idPodcast) {
            const formData = new FormData();
            formData.append('action', 'get_podcast_details');
            formData.append('id_podcast', idPodcast);
            if (csrfToken) formData.append('csrf_token', csrfToken);
            editModalTitle.textContent = 'Carregando...'; formEditarPodcast.reset(); formEditarPodcast.classList.remove('was-validated'); limparFeedback(editModalFeedback);
            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(data => {
                if (data.ok && data.podcast) {
                    const p = data.podcast;
                    document.getElementById('edit_id_podcast').value = p.id_podcast;
                    editModalTitle.textContent = `Editar: ${escapeHTML(p.titulo_podcast)}`;
                    document.getElementById('edit_titulo_podcast').value = p.titulo_podcast;
                    editCategoriaSelect.value = p.id_categoria;
                    popularEditAssuntos(p.id_categoria, p.id_assunto);
                    const catOption = editCategoriaSelect.querySelector(`option[value="${p.id_categoria}"]`);
                    if(catOption && editSlugCategoriaAtualInput) editSlugCategoriaAtualInput.value = catOption.dataset.slugCategoria || '';
                    document.getElementById('edit_descricao_podcast').value = p.descricao_podcast;
                    document.getElementById('edit_url_audio_atual').value = p.url_audio || '';
                    editAudioExistenteInfo.innerHTML = p.url_audio ? `Áudio: <strong>${escapeHTML(p.url_audio.split('/').pop())}</strong>` : 'Nenhum áudio.';
                    editAudioExistenteInfo.classList.toggle('d-none', !p.url_audio);
                    document.getElementById('edit_audio_file').required = !p.url_audio;
                    document.getElementById('edit_link_material_apoio_atual').value = p.link_material_apoio || '';
                    let tipoMaterialAtual = 'nenhum'; let urlMaterialAtual = '';
                    if (p.link_material_apoio) {
                        if (p.link_material_apoio.startsWith('http')) { tipoMaterialAtual = 'link_externo'; urlMaterialAtual = p.link_material_apoio; editMaterialExistenteInfo.innerHTML = `Link: <a href="${escapeHTML(p.link_material_apoio)}" target="_blank">${escapeHTML(p.link_material_apoio)}</a>`; }
                        else { tipoMaterialAtual = 'upload_pdf'; editMaterialExistenteInfo.innerHTML = `PDF: <strong>${escapeHTML(p.link_material_apoio.split('/').pop())}</strong>`; }
                        editMaterialExistenteInfo.classList.remove('d-none');
                    } else { editMaterialExistenteInfo.innerHTML = 'Nenhum material.'; editMaterialExistenteInfo.classList.remove('d-none');}
                    editTipoMaterialSelect.value = tipoMaterialAtual;
                    editTipoMaterialSelect.dispatchEvent(new Event('change'));
                    if (tipoMaterialAtual === 'link_externo') editInputLinkMaterialUrl.value = urlMaterialAtual; else editInputLinkMaterialUrl.value = '';
                    editInputPdfFile.value = '';
                    editVisibilidadeSelect.value = p.visibilidade; editVisibilidadeSelect.dispatchEvent(new Event('change'));
                    editInputPlanoMinimo.value = (p.visibilidade === 'restrito_assinantes' && p.id_plano_minimo) ? p.id_plano_minimo : "";
                    modalEditarPodcastBS.show();
                } else { exibirFeedback(feedbackMessageDiv, data.msg || 'Erro ao carregar dados.', false); }
            })
            .catch(e => { console.error("Get details error:", e); exibirFeedback(feedbackMessageDiv,'Erro de comunicação.', false); editModalTitle.textContent = 'Editar Podcast'; });
        }

        formEditarPodcast.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!this.checkValidity()) { e.stopPropagation(); this.classList.add('was-validated'); exibirFeedback(editModalFeedback, "Preencha os campos obrigatórios.", false); return; }
            this.classList.remove('was-validated'); setButtonLoadingState(btnSubmitEditPodcast, true); limparFeedback(editModalFeedback);
            const formData = new FormData(this);
            if (csrfToken && !formData.has('csrf_token')) formData.append('csrf_token', csrfToken);
            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(data => {
                if (data.ok) { exibirFeedback(feedbackMessageDiv, data.msg, true); modalEditarPodcastBS.hide(); fetchPodcasts(); }
                else { exibirFeedback(editModalFeedback, data.msg || 'Erro ao editar.', false); }
            })
            .catch(e => { console.error("Submit error:", e); exibirFeedback(editModalFeedback, 'Erro de comunicação.', false); })
            .finally(() => { setButtonLoadingState(btnSubmitEditPodcast, false); });
        });

        function debounce(func, delay) { let timeout; return (...args) => { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), delay); };}
        fetchPodcasts(); popularFiltroAssuntos(filtroCategoriaSelect.value);
    });
</script>
</body>
</html>