<?php
// admin/adicionar_podcast.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php');
require_once __DIR__ . '/../db/db_connect.php';

// Utility function for upload errors
function uploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return "O ficheiro carregado excede a diretiva upload_max_filesize no php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "O ficheiro carregado excede a diretiva MAX_FILE_SIZE especificada no formulário HTML.";
        case UPLOAD_ERR_PARTIAL:
            return "O ficheiro foi apenas parcialmente carregado.";
        case UPLOAD_ERR_NO_FILE:
            return "Nenhum ficheiro foi carregado."; // Can be normal for optional files
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Falta uma pasta temporária.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Falha ao escrever ficheiro em disco.";
        case UPLOAD_ERR_EXTENSION:
            return "Uma extensão PHP interrompeu o carregamento do ficheiro.";
        default:
            return "Erro de carregamento desconhecido.";
    }
}

// Function to generate a basic slug
function generateSlug($text) {
    // Normalize a entrada para minúsculas
    $text = strtolower($text);
    // Remove acentos e caracteres especiais
    $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Substitui não-letras/dígitos por hífen
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Translitera para ASCII
    $text = preg_replace('~[^-\w]+~', '', $text); // Remove caracteres restantes não alfanuméricos (exceto hífen)
    $text = trim($text, '-'); // Remove hífens do início/fim
    $text = preg_replace('~-+~', '-', $text); // Substitui múltiplos hífens por um único
    if (empty($text)) {
        return 'n-a-' . uniqid(); // Retorna um slug padrão se o resultado for vazio
    }
    return $text;
}


// --- BEGIN BACKEND BATCH PROCESSING LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'process_batch') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Ocorreu um erro desconhecido ao processar o lote.', 'errors' => []];

    $default_id_categoria = filter_input(INPUT_POST, 'default_id_categoria', FILTER_VALIDATE_INT); 
    $default_id_assunto = filter_input(INPUT_POST, 'default_id_assunto', FILTER_VALIDATE_INT);

    if (!$default_id_categoria || !$default_id_assunto) { 
        $response['message'] = 'Categoria ou Assunto padrão não fornecidos ou inválidos para o lote.';
        echo json_encode($response);
        exit;
    }

    $audio_upload_dir = __DIR__ . '/../uploads/podcasts/audio/';
    $pdf_upload_dir = __DIR__ . '/../uploads/podcasts/pdf/';
    if (!is_dir($audio_upload_dir) && !mkdir($audio_upload_dir, 0775, true)) {
        $response['message'] = 'Falha ao criar diretório de upload de áudio.';
        echo json_encode($response);
        exit;
    }
    if (!is_dir($pdf_upload_dir) && !mkdir($pdf_upload_dir, 0775, true)) {
        $response['message'] = 'Falha ao criar diretório de upload de PDF.';
        echo json_encode($response);
        exit;
    }

    $allowed_audio_types = ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/x-m4a'];
    $allowed_pdf_types = ['application/pdf'];
    $max_audio_size = 50 * 1024 * 1024; // 50MB
    $max_pdf_size = 10 * 1024 * 1024; // 10MB

    $titulos = $_POST['titulos'] ?? [];
    $descricoes = $_POST['descricoes'] ?? [];
    $links_apoio_form = $_POST['links_apoio'] ?? []; // Renamed to avoid confusion with DB column
    $visibilidades = $_POST['visibilidades'] ?? [];
    $planos_minimos = $_POST['planos_minimos'] ?? [];
    
    $total_audio_files = isset($_FILES['audio_files']['name']) ? count($_FILES['audio_files']['name']) : 0;
    $processed_count = 0;
    $error_details = [];

    if ($total_audio_files === 0) {
        $response['message'] = 'Nenhum ficheiro de áudio foi enviado.';
        echo json_encode($response);
        exit;
    }
    if (count($titulos) !== $total_audio_files || count($visibilidades) !== $total_audio_files) {
        $response['message'] = 'Inconsistência nos dados do formulário. O número de itens não corresponde ao número de áudios.';
        error_log("Audio files: $total_audio_files, Titles: " . count($titulos) . ", Visibilities: " . count($visibilidades));
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        for ($i = 0; $i < $total_audio_files; $i++) {
            $audio_original_name = $_FILES['audio_files']['name'][$i];
            $audio_tmp_name = $_FILES['audio_files']['tmp_name'][$i];
            $audio_type = $_FILES['audio_files']['type'][$i];
            $audio_error = $_FILES['audio_files']['error'][$i];
            $audio_size = $_FILES['audio_files']['size'][$i];

            $titulo = trim(htmlspecialchars($titulos[$i] ?? ''));
            $descricao = isset($descricoes[$i]) ? trim(htmlspecialchars($descricoes[$i])) : null;
            $link_material_externo = isset($links_apoio_form[$i]) && filter_var(trim($links_apoio_form[$i]), FILTER_VALIDATE_URL) ? trim($links_apoio_form[$i]) : null;
            $visibilidade = htmlspecialchars($visibilidades[$i] ?? 'restrito_assinantes');
            $id_plano_minimo = isset($planos_minimos[$i]) && !empty($planos_minimos[$i]) ? filter_var($planos_minimos[$i], FILTER_VALIDATE_INT) : null;

            if ($visibilidade === 'publico') {
                $id_plano_minimo = null;
            }

            if ($audio_error !== UPLOAD_ERR_OK) {
                $error_details[] = "Erro no upload do áudio '{$audio_original_name}': " . uploadErrorMessage($audio_error);
                continue;
            }
            if (!in_array(strtolower($audio_type), $allowed_audio_types)) {
                $error_details[] = "Tipo de ficheiro de áudio inválido para '{$audio_original_name}' (tipo: {$audio_type}). Permitidos: " . implode(', ', $allowed_audio_types);
                continue;
            }
            if ($audio_size > $max_audio_size) {
                $error_details[] = "Ficheiro de áudio '{$audio_original_name}' excede o tamanho máximo de " . ($max_audio_size / 1024 / 1024) . "MB.";
                continue;
            }
            if (empty($titulo)) {
                $error_details[] = "Título em falta para o ficheiro de áudio '{$audio_original_name}'.";
                continue;
            }

            $audio_file_extension = strtolower(pathinfo($audio_original_name, PATHINFO_EXTENSION));
            $unique_audio_filename = 'podcast_audio_' . uniqid() . '_' . time() . '.' . $audio_file_extension;
            $audio_destination = $audio_upload_dir . $unique_audio_filename;

            if (!move_uploaded_file($audio_tmp_name, $audio_destination)) {
                $error_details[] = "Falha ao mover o ficheiro de áudio '{$audio_original_name}'.";
                continue;
            }
            $audio_path_db = 'uploads/podcasts/audio/' . $unique_audio_filename; // This is for url_audio column

            $link_material_apoio_db = null; // This will go into link_material_apoio column

            if (isset($_FILES['pdf_files']['name'][$i]) && $_FILES['pdf_files']['error'][$i] === UPLOAD_ERR_OK) {
                $pdf_original_name = $_FILES['pdf_files']['name'][$i];
                $pdf_tmp_name = $_FILES['pdf_files']['tmp_name'][$i];
                $pdf_type = $_FILES['pdf_files']['type'][$i];
                $pdf_size = $_FILES['pdf_files']['size'][$i];

                if (strpos($pdf_original_name, 'empty_pdf_placeholder_') === 0 && $pdf_size === 0) {
                    // This was a placeholder, ignore
                } else {
                    if (!in_array(strtolower($pdf_type), $allowed_pdf_types)) {
                        $error_details[] = "Tipo de ficheiro PDF inválido para '{$pdf_original_name}' (associado a '{$audio_original_name}').";
                        continue; 
                    }
                    if ($pdf_size > $max_pdf_size) {
                        $error_details[] = "Ficheiro PDF '{$pdf_original_name}' (associado a '{$audio_original_name}') excede o tamanho máximo.";
                        continue;
                    }

                    $pdf_file_extension = strtolower(pathinfo($pdf_original_name, PATHINFO_EXTENSION));
                    $unique_pdf_filename = 'podcast_pdf_' . uniqid() . '_' . time() . '.' . $pdf_file_extension;
                    $pdf_destination = $pdf_upload_dir . $unique_pdf_filename;

                    if (move_uploaded_file($pdf_tmp_name, $pdf_destination)) {
                        $link_material_apoio_db = 'uploads/podcasts/pdf/' . $unique_pdf_filename; // PDF path for DB
                    } else {
                        $error_details[] = "Falha ao mover o ficheiro PDF '{$pdf_original_name}' (associado a '{$audio_original_name}').";
                        continue;
                    }
                }
            }
            
            // If PDF was not uploaded but an external link was provided, use that.
            if (empty($link_material_apoio_db) && !empty($link_material_externo)) { 
                 $link_material_apoio_db = $link_material_externo;
            }
            
            $slug_podcast = generateSlug($titulo);
            // Ensure slug uniqueness if necessary (e.g., by appending a count or random string if it already exists)
            // For simplicity, this example doesn't check for slug uniqueness in the DB before insert.

            $sql = "INSERT INTO podcasts (id_assunto, titulo_podcast, descricao_podcast, url_audio, duracao_total_segundos, link_material_apoio, visibilidade, id_plano_minimo, data_publicacao, slug_podcast) 
                    VALUES (:id_assunto, :titulo_podcast, :descricao_podcast, :url_audio, :duracao_total_segundos, :link_material_apoio, :visibilidade, :id_plano_minimo, NOW(), :slug_podcast)";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                ':id_assunto' => $default_id_assunto,
                ':titulo_podcast' => $titulo,
                ':descricao_podcast' => $descricao,
                ':url_audio' => $audio_path_db, // Corrected column name based on schema
                ':duracao_total_segundos' => 0, // Corrected column name, default to 0
                ':link_material_apoio' => $link_material_apoio_db, // Corrected column name
                ':visibilidade' => $visibilidade,
                ':id_plano_minimo' => $id_plano_minimo,
                ':slug_podcast' => $slug_podcast
            ];
            $stmt->execute($params);
            $processed_count++;
        }

        if (empty($error_details)) {
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = $processed_count . " podcast(s) adicionado(s) com sucesso!";
        } else {
            $pdo->rollBack();
            $response['message'] = "Alguns erros ocorreram. " . $processed_count . " podcast(s) processado(s) antes dos erros. Verifique os detalhes.";
            $response['errors'] = $error_details;
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro de PDO no batch upload: " . $e->getMessage());
        $response['message'] = "Erro de base de dados ao adicionar podcasts.";
        $response['errors'][] = "Detalhe do erro de base de dados: " . $e->getMessage(); 
    } catch (Exception $e) {
         if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro geral no batch upload: " . $e->getMessage());
        $response['message'] = "Ocorreu um erro inesperado durante o processamento.";
        $response['errors'][] = "Detalhe do erro geral: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
// --- END BACKEND BATCH PROCESSING LOGIC ---

$pageTitle = "Adicionar Novos Podcasts em Lote";
$userName_for_header = $_SESSION['user_nome_completo'] ?? 'Admin';
$avatarUrl_for_header = $_SESSION['user_avatar_url'] ?? '';
if (!$avatarUrl_for_header) {
    $initials_for_header = ''; $nameParts_for_header = explode(' ', $userName_for_header);
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(substr($nameParts_for_header[0], 0, 1)) : 'A';
    if (count($nameParts_for_header) > 1) $initials_for_header .= strtoupper(substr(end($nameParts_for_header), 0, 1));
    elseif (strlen($nameParts_for_header[0]) > 1 && $initials_for_header === strtoupper(substr($nameParts_for_header[0], 0, 1))) $initials_for_header .= strtoupper(substr($nameParts_for_header[0], 1, 1));
    if(empty($initials_for_header) || strlen($initials_for_header) > 2) $initials_for_header = "AD";
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}

$categorias = [];
$todos_assuntos_php_array = [];
try {
    $stmt_categorias = $pdo->query("SELECT id_categoria, nome_categoria FROM categorias_podcast ORDER BY nome_categoria ASC");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    $stmt_assuntos = $pdo->query("SELECT id_assunto, id_categoria, nome_assunto FROM assuntos_podcast ORDER BY nome_assunto ASC");
    $todos_assuntos_php_array = $stmt_assuntos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao buscar dados para formulário de podcast: " . $e->getMessage());
    $_SESSION['feedback_message'] = "Erro ao carregar dados necessários para o formulário.";
    $_SESSION['feedback_type'] = "error";
}
$todos_assuntos_json = json_encode($todos_assuntos_php_array);

$planos_assinatura = [];
try {
    $stmt_planos = $pdo->query("SELECT id_plano, nome_plano FROM planos_assinatura WHERE ativo = TRUE ORDER BY nome_plano ASC");
    $planos_assinatura = $stmt_planos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar planos de assinatura: " . $e->getMessage());
}
$planos_assinatura_json = json_encode($planos_assinatura);

$feedback_message_global = $_SESSION['feedback_message'] ?? null;
$feedback_type_global = $_SESSION['feedback_type'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" xintegrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f0f2f5; 
        }
        .main-wrapper {
            display: flex;
            min-height: 100vh; 
        }
        #adminSidebar { /* Styles for sidebar.php if it's included */
            width: 260px; /* Default width */
            background-color: #2c3e50; 
            color: #ecf0f1;
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }
        #adminSidebar .nav-link {
            color: #bdc3c7; 
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #adminSidebar .nav-link:hover, #adminSidebar .nav-link.active {
            background-color: #34495e; 
            color: #ffffff;
            border-left: 3px solid #3498db; 
        }
        #adminSidebar .nav-link .fas, #adminSidebar .nav-link .far { /* Support for different FA styles */
            margin-right: 0.75rem;
            width: 20px; /* Ensure icon alignment */
            text-align: center;
        }
         #adminSidebar .sidebar-brand-text {
            font-size: 1.1rem; /* Slightly smaller brand text */
        }

        .content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: #f0f2f5;
            overflow-x: hidden; /* Prevent horizontal scroll on content wrapper */
        }
        .admin-main-content {
            padding: 1.5rem 2rem; 
            flex-grow: 1;
            overflow-y: auto;
        }
        .admin-header { /* Styles for header.php if it's included */
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card {
            border: none;
            border-radius: 0.5rem; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
        }
        .form-label {
            font-weight: 600; 
            color: #495057;
            font-size: 0.875rem; /* Slightly smaller labels */
        }
        .form-control, .form-select {
            border-radius: 0.375rem; 
        }
        .form-control-sm, .form-select-sm { /* Ensure sm controls are indeed smaller */
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .modal-title {
            color: #2c3e50;
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .file-item-card .card-header {
            background-color: #e9ecef; 
            font-size: 0.9rem; /* Smaller header in item card */
        }
        .file-item-card .card-body {
            padding: 1rem; /* Slightly less padding in item card body */
        }
        .btn-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            vertical-align: text-bottom;
            border: .2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: .75s linear infinite spinner-border;
        }
        @media (max-width: 991.98px) { 
            #adminSidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: -260px; /* Hidden by default */
                z-index: 1045; /* Above backdrop */
                box-shadow: 0 0 15px rgba(0,0,0,0.2);
            }
            #adminSidebar.active {
                left: 0; /* Shown */
            }
            .content-wrapper.sidebar-active-overlay::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 1040; /* Below sidebar, above content */
            }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <?php 
        // This div is a placeholder for where your sidebar.php content will be injected.
        // Ensure sidebar.php itself uses Bootstrap classes and has the ID 'adminSidebar'.
        if (file_exists(__DIR__ . '/sidebar.php')) {
            require __DIR__ . '/sidebar.php'; // This should output the <div id="adminSidebar" ...> structure
        } else {
            // Fallback sidebar for testing if sidebar.php is not found
            echo '<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow-lg" id="adminSidebar" style="width: 260px; min-height: 100vh;">';
            echo '<a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none p-2">';
            echo '<i class="fas fa-headphones-alt fa-2x me-2"></i><span class="fs-4 fw-bold">AudioTO <small class="fw-light fs-6">Admin</small></span></a><hr>';
            echo '<ul class="nav nav-pills flex-column mb-auto">';
            echo '<li class="nav-item"><a href="#" class="nav-link text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Painel</a></li>';
            echo '<li class="nav-item"><a href="#" class="nav-link active bg-primary"><i class="fas fa-upload fa-fw me-2"></i>Adicionar Podcasts</a></li>';
            echo '</ul><hr></div>';
        }
    ?>

    <div class="content-wrapper" id="contentWrapper">
        <?php 
            if (file_exists(__DIR__ . '/header.php')) {
                require __DIR__ . '/header.php'; // This should output the <nav class="navbar admin-header ...">
            } else {
                // Fallback header for testing
                echo '<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top admin-header px-3 py-2">';
                echo '<div class="container-fluid">';
                echo '<button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="adminMobileSidebarToggleFallback"><i class="fas fa-bars"></i></button>';
                echo '<a class="navbar-brand fw-bold text-primary" href="index.php">Audio TO Admin</a>';
                echo '<ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="#">'.htmlspecialchars($userName_for_header).'</a></li></ul>';
                echo '</div></nav>';
            }
        ?>

        <main class="admin-main-content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2 mb-0 text-dark"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <a href="gerir_podcasts.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Voltar para Gestão
                    </a>
                </div>
                
                <div class="card">
                    <div class="card-body p-lg-4">
                        <div id="feedbackAreaGlobal" class="mb-4">
                            <?php if ($feedback_message_global): ?>
                                <div class="alert alert-<?php echo $feedback_type_global === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($feedback_message_global); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="mainUploadFormContainer">
                            <p class="text-muted mb-4 small">
                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                Defina a categoria e assunto padrão para todos os podcasts neste lote. Depois, selecione os ficheiros M4A.
                            </p>
                            <div class="row g-3 mb-4 pb-4 border-bottom">
                                <div class="col-md-6">
                                    <label for="default_id_categoria" class="form-label">Categoria Padrão <span class="text-danger">*</span></label>
                                    <select id="default_id_categoria" name="default_id_categoria" class="form-select" required>
                                        <option value="" disabled selected>Escolha uma categoria...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id_categoria']; ?>">
                                                <?php echo htmlspecialchars($cat['nome_categoria']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="default_id_assunto" class="form-label">Assunto Padrão <span class="text-danger">*</span></label>
                                    <select id="default_id_assunto" name="default_id_assunto" class="form-select" required disabled>
                                        <option value="" disabled selected>Escolha uma categoria primeiro...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="audio_files_batch" class="form-label">Ficheiros de Áudio (M4A) <span class="text-danger">*</span></label>
                                <input type="file" id="audio_files_batch" name="audio_files_batch[]" class="form-control" accept="audio/mp4,audio/x-m4a" multiple required>
                                <div class="form-text mt-2 small">Pode selecionar múltiplos ficheiros. Tamanho máximo por ficheiro: 50MB.</div>
                            </div>
                            
                            <div class="d-flex justify-content-end pt-3">
                                <button type="button" id="processFilesButton" class="btn btn-primary">
                                    <i class="fas fa-cogs me-2"></i>
                                    Processar Ficheiros
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
         <footer class="py-4 mt-auto bg-light border-top">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; Audio TO Admin <?php echo date("Y"); ?></div>
                    <div>
                        <a href="#">Política de Privacidade</a>
                        &middot;
                        <a href="#">Termos &amp; Condições</a>
                    </div>
                </div>
            </div>
        </footer>
    </div> 
</div> 

    <div class="modal fade" id="batchEditModal" tabindex="-1" aria-labelledby="batchEditModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"> 
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchEditModalLabel">
                        <i class="fas fa-edit me-2"></i>Editar Detalhes dos Podcasts
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeModalButtonHeader"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="batchEditForm"> 
                        <div id="fileItemsContainer" class="list-group">
                            {/* File items will be injected here by JavaScript */}
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <div id="modalFeedbackArea" class="text-sm me-auto w-100 mb-2 mb-md-0"></div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="cancelModalButtonFooter">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" id="saveBatchButton" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>
                        <span id="saveBatchButtonText">Guardar Todos</span>
                        <span id="saveBatchButtonSpinner" class="btn-spinner ms-2 d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        const todosAssuntos = <?php echo $todos_assuntos_json; ?>;
        const planosAssinatura = <?php echo $planos_assinatura_json; ?>;
        
        const defaultCategoriaSelect = document.getElementById('default_id_categoria');
        const defaultAssuntoSelect = document.getElementById('default_id_assunto');
        const audioFilesInput = document.getElementById('audio_files_batch');
        const processFilesButton = document.getElementById('processFilesButton');
        
        const batchEditModalEl = document.getElementById('batchEditModal');
        const batchEditBsModal = new bootstrap.Modal(batchEditModalEl); 
        
        const fileItemsContainer = document.getElementById('fileItemsContainer');
        const batchEditForm = document.getElementById('batchEditForm');
        const saveBatchButton = document.getElementById('saveBatchButton');
        const saveBatchButtonText = document.getElementById('saveBatchButtonText');
        const saveBatchButtonSpinner = document.getElementById('saveBatchButtonSpinner');
        
        const feedbackAreaGlobal = document.getElementById('feedbackAreaGlobal');
        const modalFeedbackArea = document.getElementById('modalFeedbackArea');

        let selectedAudioFilesStore = []; 

        function showAlert(areaElement, message, type = 'error') {
            let alertClass = 'alert-info'; 
            if (type === 'success') alertClass = 'alert-success';
            if (type === 'error') alertClass = 'alert-danger';
            areaElement.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show small py-2 px-3" role="alert">${message}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
        }
        function clearAlert(areaElement) {
            areaElement.innerHTML = '';
        }

        function popularDefaultAssuntos(idCategoriaSelecionada) {
            defaultAssuntoSelect.innerHTML = '<option value="" disabled selected>A carregar...</option>';
            defaultAssuntoSelect.disabled = true;
            const assuntosFiltrados = todosAssuntos.filter(assunto => String(assunto.id_categoria) === String(idCategoriaSelecionada));
            defaultAssuntoSelect.innerHTML = '<option value="" disabled selected>Escolha um assunto...</option>';
            if (assuntosFiltrados.length > 0) {
                assuntosFiltrados.forEach(assunto => {
                    const option = document.createElement('option');
                    option.value = assunto.id_assunto;
                    option.textContent = assunto.nome_assunto;
                    defaultAssuntoSelect.appendChild(option);
                });
                defaultAssuntoSelect.disabled = false;
            } else {
                defaultAssuntoSelect.innerHTML = '<option value="" disabled>Nenhum assunto para esta categoria</option>';
            }
        }

        defaultCategoriaSelect.addEventListener('change', function() {
            if (this.value) {
                popularDefaultAssuntos(this.value);
            } else {
                defaultAssuntoSelect.innerHTML = '<option value="" disabled selected>Escolha uma categoria primeiro...</option>';
                defaultAssuntoSelect.disabled = true;
            }
        });

        processFilesButton.addEventListener('click', () => {
            selectedAudioFilesStore = Array.from(audioFilesInput.files);
            if (selectedAudioFilesStore.length === 0) {
                showAlert(feedbackAreaGlobal, 'Por favor, selecione pelo menos um ficheiro M4A.', 'error');
                return;
            }
            if (!defaultCategoriaSelect.value) {
                showAlert(feedbackAreaGlobal, 'Por favor, selecione uma Categoria padrão para o lote.', 'error');
                return;
            }
             if (!defaultAssuntoSelect.value) {
                showAlert(feedbackAreaGlobal, 'Por favor, selecione um Assunto padrão para o lote.', 'error');
                return;
            }
            clearAlert(feedbackAreaGlobal);
            renderFileItemsInModal();
            batchEditBsModal.show();
        });
        
        function renderFileItemsInModal() {
            fileItemsContainer.innerHTML = ''; 
            selectedAudioFilesStore.forEach((file, index) => {
                const cleanFileName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                const fileItemHTML = `
                    <div class="card mb-3 file-item-card" data-index="${index}">
                        <div class="card-header d-flex align-items-center bg-light py-2 px-3">
                            <i class="fas fa-file-audio fa-fw text-primary me-2"></i>
                            <strong class="text-truncate small" title="${file.name}">${file.name}</strong>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-2">
                                <label for="titulo_podcast_${index}" class="form-label mb-1">Título <span class="text-danger">*</span></label>
                                <input type="text" id="titulo_podcast_${index}" name="titulos[]" class="form-control form-control-sm" value="${cleanFileName.replace(/_/g, ' ').replace(/-/g, ' ')}" required>
                            </div>
                            <div class="mb-2">
                                <label for="descricao_podcast_${index}" class="form-label mb-1">Descrição</label>
                                <textarea id="descricao_podcast_${index}" name="descricoes[]" rows="2" class="form-control form-control-sm" placeholder="Breve descrição..."></textarea>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label for="pdf_file_${index}" class="form-label mb-1">Ficheiro PDF</label>
                                    <input type="file" id="pdf_file_${index}" name="pdf_files[${index}]" class="form-control form-control-sm" accept=".pdf">
                                    <div class="form-text small">Opcional. Max 10MB.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="link_material_apoio_${index}" class="form-label mb-1">Link Externo</label>
                                    <input type="url" id="link_material_apoio_${index}" name="links_apoio[]" class="form-control form-control-sm" placeholder="https://...">
                                    <div class="form-text small">Opcional. URL completo.</div>
                                </div>
                            </div>
                             <div class="row g-2 pt-2 mt-2 border-top">
                                <div class="col-md-6 mt-2">
                                    <label for="visibilidade_${index}" class="form-label mb-1">Visibilidade <span class="text-danger">*</span></label>
                                    <select id="visibilidade_${index}" name="visibilidades[]" class="form-select form-select-sm visibilidade-select" data-index="${index}" required>
                                        <option value="restrito_assinantes" selected>Restrito a Assinantes</option>
                                        <option value="publico">Público (Gratuito)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mt-2" id="campo_plano_minimo_container_${index}">
                                    <label for="id_plano_minimo_${index}" class="form-label mb-1">Plano Mínimo</label>
                                    <select id="id_plano_minimo_${index}" name="planos_minimos[]" class="form-select form-select-sm plano-minimo-select">
                                        <option value="">Qualquer Plano Ativo</option>
                                        ${planosAssinatura.map(plano => `<option value="${plano.id_plano}">${plano.nome_plano}</option>`).join('')}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                fileItemsContainer.insertAdjacentHTML('beforeend', fileItemHTML);
                const visSelect = document.getElementById(`visibilidade_${index}`);
                if(visSelect) updatePlanoMinimoVisibility(visSelect);
            });
        }
        
        function updatePlanoMinimoVisibility(visibilidadeSelectElement) {
            const index = visibilidadeSelectElement.dataset.index;
            const planoMinimoContainer = document.getElementById(`campo_plano_minimo_container_${index}`);
            const planoMinimoInput = document.getElementById(`id_plano_minimo_${index}`);
            if (visibilidadeSelectElement.value === 'publico') {
                planoMinimoContainer.style.display = 'none';
                if(planoMinimoInput) planoMinimoInput.value = ''; 
            } else {
                planoMinimoContainer.style.display = 'block';
            }
        }

        fileItemsContainer.addEventListener('change', function(event) {
            if (event.target.classList.contains('visibilidade-select')) {
                updatePlanoMinimoVisibility(event.target);
            }
        });

        saveBatchButton.addEventListener('click', async function(event) { 
            event.preventDefault();
            saveBatchButton.disabled = true;
            saveBatchButtonText.classList.add('d-none'); 
            saveBatchButtonSpinner.classList.remove('d-none'); 
            clearAlert(modalFeedbackArea);

            const formData = new FormData(batchEditForm); 
            formData.append('default_id_categoria', defaultCategoriaSelect.value);
            formData.append('default_id_assunto', defaultAssuntoSelect.value);
            
            selectedAudioFilesStore.forEach((audioFile, index) => {
                 formData.append(`audio_files[${index}]`, audioFile, audioFile.name);
            });

             selectedAudioFilesStore.forEach((_, index) => {
                const pdfInput = document.getElementById(`pdf_file_${index}`);
                if (pdfInput && !pdfInput.files[0]) { 
                    formData.set(`pdf_files[${index}]`, new Blob(), `empty_pdf_placeholder_${index}.txt`);
                }
            });

            let formIsValid = true; 
            for(let i=0; i<selectedAudioFilesStore.length; i++) {
                const tituloInput = document.getElementById(`titulo_podcast_${i}`);
                if (!tituloInput || !tituloInput.value.trim()) {
                    showAlert(modalFeedbackArea, `Título é obrigatório para o ficheiro: ${selectedAudioFilesStore[i].name}.`, 'error');
                    if(tituloInput) tituloInput.focus();
                    formIsValid = false;
                    break; 
                }
            }

            if (!formIsValid) {
                saveBatchButton.disabled = false;
                saveBatchButtonText.classList.remove('d-none');
                saveBatchButtonSpinner.classList.add('d-none');
                return;
            }

            try {
                const response = await fetch('adicionar_podcast.php?action=process_batch', {
                    method: 'POST',
                    body: formData
                });

                const resultText = await response.text();
                let result;
                try {
                    result = JSON.parse(resultText);
                } catch (e) {
                    console.error("Falha ao analisar JSON:", resultText);
                    showAlert(modalFeedbackArea, 'Erro de comunicação: Resposta inválida do servidor. Verifique a consola (F12).', 'error');
                    throw new Error('Invalid JSON response');
                }

                if (result.success) {
                    showAlert(feedbackAreaGlobal, result.message, 'success');
                    batchEditBsModal.hide();
                    audioFilesInput.value = ''; 
                    selectedAudioFilesStore = [];
                    fileItemsContainer.innerHTML = '';
                    clearAlert(modalFeedbackArea);
                } else {
                    let errorMsg = result.message || "Ocorreu um erro.";
                    if (result.errors && result.errors.length > 0) {
                        errorMsg += '<br/><ul class="list-unstyled ps-3">';
                        result.errors.forEach(err => { errorMsg += `<li><small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>${err}</small></li>`; });
                        errorMsg += '</ul>';
                    }
                    showAlert(modalFeedbackArea, errorMsg, 'error');
                }

            } catch (error) {
                console.error('Erro no Fetch:', error);
                showAlert(modalFeedbackArea, `Erro ao submeter: ${error.message}. Verifique a consola (F12).`, 'error');
            } finally {
                saveBatchButton.disabled = false;
                saveBatchButtonText.classList.remove('d-none');
                saveBatchButtonSpinner.classList.add('d-none');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (defaultCategoriaSelect.value) { 
                popularDefaultAssuntos(defaultCategoriaSelect.value);
            }
            // Script for sidebar toggle if header.php doesn't handle it globally
            const mobileSidebarToggleButton = document.getElementById('adminMobileSidebarToggle'); // ID from your header
            const adminSidebar = document.getElementById('adminSidebar');
            const contentWrapper = document.getElementById('contentWrapper');

            if (mobileSidebarToggleButton && adminSidebar && contentWrapper) {
                mobileSidebarToggleButton.addEventListener('click', function() {
                    adminSidebar.classList.toggle('active');
                    contentWrapper.classList.toggle('sidebar-active-overlay'); // For backdrop on body/content
                });
            }
             // Fallback for the test header if your actual header.php is not loaded
            const fallbackToggler = document.getElementById('adminMobileSidebarToggleFallback');
            if(fallbackToggler && adminSidebar && contentWrapper){
                 fallbackToggler.addEventListener('click', function() {
                    adminSidebar.classList.toggle('active');
                    contentWrapper.classList.toggle('sidebar-active-overlay');
                });
            }
        });
    </script>
</body>
</html>
