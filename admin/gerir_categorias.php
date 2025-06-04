<?php
// admin/gerir_categorias.php
require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php');
require_once __DIR__ . '/../db/db_connect.php'; 

$pageTitle = "Gerir Categorias de Podcasts";

// Feedback variables for non-AJAX operations (e.g., initial load error)
$feedback_message_global = '';
$feedback_type_global = '';

// Funções auxiliares
function limparTexto($texto) { return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8'); }

function resposta_json($ok, $msg, $extra = []) {
    // Garantir que a conexão PDO não seja serializada no JSON em caso de erro com $extra
    if (isset($extra['pdo'])) unset($extra['pdo']);
    // Limpar qualquer output buffer antes de enviar JSON
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra)); 
    exit;
}

// CRUD via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ADICIONAR
    if ($action === 'add') {
        $nome = limparTexto($_POST['nome'] ?? '');
        $slug = limparTexto($_POST['slug'] ?? '');
        $desc = limparTexto($_POST['desc'] ?? '');
        $icone = trim($_POST['icone'] ?? ''); 
        $cor = limparTexto($_POST['cor'] ?? '#0D6EFD'); // Bootstrap primary blue as default
        if (empty($nome)) resposta_json(false, 'O nome da categoria é obrigatório.');
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
            $slug = trim($slug, '-');
            if (empty($slug)) $slug = 'categoria-' . time(); // Fallback slug
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO categorias_podcast (nome_categoria, slug_categoria, descricao_categoria, icone_categoria, cor_icone, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nome, $slug, $desc, $icone, $cor]);
            resposta_json(true, 'Categoria adicionada com sucesso!');
        } catch(PDOException $e) {
            // Check for duplicate entry (slug or name if they are unique in DB)
            if ($e->getCode() == '23000') { // SQLSTATE for integrity constraint violation
                 resposta_json(false, 'Erro: Já existe uma categoria com este nome ou slug.');
            }
            error_log("Erro ao adicionar categoria: " . $e->getMessage());
            resposta_json(false, 'Erro ao adicionar categoria. Verifique os logs do servidor.');
        }
    }

    // EDITAR
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $nome = limparTexto($_POST['nome'] ?? '');
        $slug = limparTexto($_POST['slug'] ?? '');
        $desc = limparTexto($_POST['desc'] ?? '');
        $icone = trim($_POST['icone'] ?? ''); 
        $cor = limparTexto($_POST['cor'] ?? '#0D6EFD');
        if (empty($nome) || !$id) resposta_json(false, 'Nome e ID da categoria são obrigatórios.');
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nome)));
            $slug = trim($slug, '-');
             if (empty($slug)) $slug = 'categoria-' . $id . '-' . time(); // Fallback slug
        }
        try {
            $stmt = $pdo->prepare("UPDATE categorias_podcast SET nome_categoria=?, slug_categoria=?, descricao_categoria=?, icone_categoria=?, cor_icone=? WHERE id_categoria=?");
            $stmt->execute([$nome, $slug, $desc, $icone, $cor, $id]);
            resposta_json(true, 'Categoria atualizada com sucesso!');
        } catch(PDOException $e) {
             if ($e->getCode() == '23000') {
                 resposta_json(false, 'Erro: Já existe uma categoria com este nome ou slug.');
            }
            error_log("Erro ao editar categoria: " . $e->getMessage());
            resposta_json(false, 'Erro ao editar categoria. Verifique os logs do servidor.');
        }
    }

    // EXCLUIR
    if ($action === 'del') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) resposta_json(false, 'ID da categoria é obrigatório.');
        try {
            // Verificar se a categoria está em uso por algum assunto_podcast
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM assuntos_podcast WHERE id_categoria = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetchColumn() > 0) {
                resposta_json(false, 'Erro: Esta categoria está associada a um ou mais assuntos e não pode ser excluída.');
            }

            $stmt = $pdo->prepare("DELETE FROM categorias_podcast WHERE id_categoria=?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                resposta_json(true, 'Categoria excluída com sucesso.');
            } else {
                resposta_json(false, 'Nenhuma categoria encontrada com este ID ou já foi excluída.');
            }
        } catch(PDOException $e) {
            error_log("Erro ao excluir categoria: " . $e->getMessage());
            resposta_json(false, 'Erro ao excluir categoria. Pode estar em uso ou ocorreu um erro no servidor.');
        }
    }

    // CARREGAR CATEGORIA PARA EDIÇÃO
    if ($action === 'get') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) resposta_json(false, 'ID da categoria é obrigatório.');
        $stmt = $pdo->prepare("SELECT id_categoria, nome_categoria, slug_categoria, descricao_categoria, icone_categoria, cor_icone FROM categorias_podcast WHERE id_categoria=?");
        $stmt->execute([$id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dados) {
            resposta_json(true, 'Dados carregados.', ['dados' => $dados]);
        } else {
            resposta_json(false, 'Categoria não encontrada.');
        }
    }

    // Listar sempre para recarregar via AJAX
    if ($action === 'list') {
        try {
            $stmt = $pdo->query("SELECT id_categoria, nome_categoria, slug_categoria, descricao_categoria, icone_categoria, cor_icone, data_criacao FROM categorias_podcast ORDER BY nome_categoria ASC");
            $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            resposta_json(true, 'Lista carregada.', ['cats' => $cats]);
        } catch (PDOException $e) {
            error_log("Erro ao listar categorias: " . $e->getMessage());
            resposta_json(false, 'Erro ao carregar a lista de categorias.');
        }
    }
    resposta_json(false, 'Ação desconhecida ou não implementada.');
}

// Listar na primeira carga
$categorias = [];
try {
    $stmt = $pdo->query("SELECT id_categoria, nome_categoria, slug_categoria, descricao_categoria, icone_categoria, cor_icone, data_criacao FROM categorias_podcast ORDER BY nome_categoria ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_message_global = 'Erro ao carregar categorias: ' . $e->getMessage();
    $feedback_type_global = 'danger'; // Bootstrap alert type
    error_log("Erro PDO ao carregar categorias inicialmente: " . $e->getMessage());
}

// Informações do utilizador para o header (serão usadas por header.php)
$userName_for_header = $_SESSION['user_nome_completo'] ?? 'Admin';
$avatarUrl_for_header = $_SESSION['user_avatar_url'] ?? '';
if (!$avatarUrl_for_header) {
    $initials_for_header = ''; $nameParts_for_header = explode(' ', trim($userName_for_header));
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(substr($nameParts_for_header[0], 0, 1)) : 'A';
    if (count($nameParts_for_header) > 1) $initials_for_header .= strtoupper(substr(end($nameParts_for_header), 0, 1));
    elseif (strlen($nameParts_for_header[0]) > 1 && $initials_for_header === strtoupper(substr($nameParts_for_header[0], 0, 1))) $initials_for_header .= strtoupper(substr($nameParts_for_header[0], 1, 1));
    if(empty($initials_for_header) || strlen($initials_for_header) > 2) $initials_for_header = "AD";
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" xintegrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #f0f2f5; }
        .main-wrapper { display: flex; min-height: 100vh; }
        #adminSidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        #adminSidebar .nav-link { color: #bdc3c7; padding: 0.8rem 1.25rem; font-size: 0.9rem; border-left: 3px solid transparent; }
        #adminSidebar .nav-link:hover { background-color: #34495e; color: #ffffff; border-left-color: #3498db; }
        #adminSidebar .nav-link.active { background-color: #3498db; color: #ffffff; font-weight: 600; border-left-color: #2980b9; }
        #adminSidebar .nav-link .fas, #adminSidebar .nav-link .far { margin-right: 0.8rem; width: 20px; text-align: center; }
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; background-color: #f0f2f5; overflow-x: hidden; }
        .admin-main-content { padding: 2rem; flex-grow: 1; overflow-y: auto; }
        .admin-header { background-color: #ffffff; border-bottom: 1px solid #e0e0e0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card { border: none; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #495057; font-size: 0.875rem; }
        .form-control, .form-select { border-radius: 0.375rem; font-size: 0.9rem; }
        .form-control-sm, .form-select-sm { font-size: 0.875rem; padding: 0.25rem 0.5rem; }
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { color: #2c3e50; }
        .modal-footer { background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
        .cat-icon-display {
            width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 0.375rem; background-color: #e9ecef; color: #495057; overflow: hidden;
            vertical-align: middle;
        }
        .cat-icon-display svg, .cat-icon-display i { font-size: 1.25rem; }
        .cat-icon-display img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .table td { vertical-align: middle; font-size: 0.9rem; }
        .btn-spinner { display: inline-block; width: 1rem; height: 1rem; vertical-align: text-bottom; border: .2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: .75s linear infinite spinner-border; }
        @media (max-width: 991.98px) {
            #adminSidebar { position: fixed; top: 0; bottom: 0; left: -260px; z-index: 1045; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
            #adminSidebar.active { left: 0; }
            .content-wrapper.sidebar-active-overlay::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.4); z-index: 1040; }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <?php 
        if (file_exists(__DIR__ . '/sidebar.php')) {
            require __DIR__ . '/sidebar.php'; 
        } else {
            echo '<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow-lg" id="adminSidebar" style="width: 260px; min-height: 100vh;">';
            echo '<a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none p-2"><i class="fas fa-headphones-alt fa-2x me-2"></i><span class="fs-4 fw-bold">AudioTO <small class="fw-light fs-6">Admin</small></span></a><hr>';
            echo '<ul class="nav nav-pills flex-column mb-auto"><li class="nav-item"><a href="gerir_categorias.php" class="nav-link active bg-primary"><i class="fas fa-tags fa-fw me-2"></i>Gerir Categorias</a></li></ul><hr></div>';
        }
    ?>

    <div class="content-wrapper" id="contentWrapper">
        <?php 
            if (file_exists(__DIR__ . '/header.php')) {
                require __DIR__ . '/header.php';
            } else {
                echo '<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top admin-header px-3 py-2"><div class="container-fluid">';
                echo '<button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="adminMobileSidebarToggleFallback"><i class="fas fa-bars"></i></button>';
                echo '<a class="navbar-brand fw-bold text-primary" href="index.php">Audio TO Admin</a>';
                echo '<ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="#">'.htmlspecialchars($userName_for_header).'</a></li></ul>';
                echo '</div></nav>';
            }
        ?>

        <main class="admin-main-content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <h1 class="h2 mb-0 text-dark fw-bold"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <button type="button" class="btn btn-primary" id="btn-nova-categoria">
                        <i class="fas fa-plus me-2"></i>Nova Categoria
                    </button>
                </div>
                
                <div id="feedbackAreaGlobal" class="mb-3">
                    <?php if ($feedback_message_global): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($feedback_type_global); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($feedback_message_global); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header bg-light py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Lista de Categorias</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" class="ps-3">Ícone</th>
                                        <th scope="col">Nome</th>
                                        <th scope="col">Slug</th>
                                        <th scope="col">Descrição</th>
                                        <th scope="col">Criada em</th>
                                        <th scope="col" class="text-end pe-3">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="lista-categorias-body">
                                    <?php if (empty($categorias)): ?>
                                        <tr id="placeholder-sem-categorias">
                                            <td colspan="6" class="text-center text-muted p-5">
                                                <i class="fas fa-folder-open fa-3x mb-3"></i><br>
                                                Nenhuma categoria cadastrada ainda.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php foreach($categorias as $cat): ?>
                                    <tr data-id="<?php echo $cat['id_categoria']; ?>">
                                        <td class="ps-3">
                                            <span class="cat-icon-display" style="color: <?php echo htmlspecialchars($cat['cor_icone'] ?: '#6c757d'); ?>;">
                                                <?php
                                                    $iconeVal = $cat['icone_categoria'];
                                                    if (strpos($iconeVal, '<svg') !== false) { echo $iconeVal; }
                                                    elseif (preg_match('/^fa[sbrlkdu]? fa-[a-z0-9-]+/i', $iconeVal)) { echo "<i class='{$iconeVal}'></i>"; }
                                                    elseif (filter_var($iconeVal, FILTER_VALIDATE_URL)) { echo "<img src='" . htmlspecialchars($iconeVal) . "' alt=''>"; }
                                                    elseif (!empty($iconeVal)) { echo htmlspecialchars($iconeVal); }
                                                    else { echo '?'; }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="fw-medium"><?php echo htmlspecialchars($cat['nome_categoria']); ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?php echo htmlspecialchars($cat['slug_categoria']); ?></span></td>
                                        <td class="text-muted small" title="<?php echo htmlspecialchars($cat['descricao_categoria']); ?>">
                                            <?php echo htmlspecialchars(mb_strimwidth($cat['descricao_categoria'], 0, 70, "...")); ?>
                                        </td>
                                        <td class="text-muted small"><?php echo date("d/m/Y H:i", strtotime($cat['data_criacao'])); ?></td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-outline-primary btn-edit-categoria me-1" title="Editar" data-id="<?php echo $cat['id_categoria']; ?>"><i class="fas fa-pencil-alt"></i></button>
                                            <button class="btn btn-sm btn-outline-danger btn-delete-categoria" title="Excluir" data-id="<?php echo $cat['id_categoria']; ?>"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
         <footer class="py-4 mt-auto bg-light border-top">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; Audio TO Admin <?php echo date("Y"); ?></div>
                    <div><a href="#">Política de Privacidade</a> &middot; <a href="#">Termos &amp; Condições</a></div>
                </div>
            </div>
        </footer>
    </div> 
</div> 

    <div class="modal fade" id="modal-categoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="form-categoria">
                    <input type="hidden" name="id" id="cat_id">
                    <input type="hidden" name="action" id="cat_action" value="add">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalCategoriaLabel">Nova Categoria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-feedback-area" class="mb-3"></div>
                        <div class="mb-3">
                            <label for="cat_nome" class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cat_nome" name="nome" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="cat_slug" class="form-label">Slug</label>
                            <input type="text" class="form-control" id="cat_slug" name="slug" maxlength="150" placeholder="Gerado automaticamente se vazio">
                            <div class="form-text small">Use apenas letras minúsculas, números e hífens.</div>
                        </div>
                        <div class="mb-3">
                            <label for="cat_desc" class="form-label">Descrição</label>
                            <textarea class="form-control" id="cat_desc" name="desc" rows="3" maxlength="255"></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label for="cat_icone" class="form-label">Ícone</label>
                                <input type="text" class="form-control" id="cat_icone" name="icone" placeholder="Ex: fas fa-podcast, <svg...>, URL de imagem, ou emoji">
                                <div class="form-text small">Pode ser uma classe Font Awesome (ex: <code>fas fa-tag</code>), código SVG, URL de uma imagem (PNG, JPG, SVG) ou um emoji.</div>
                            </div>
                            <div class="col-md-3">
                                <label for="cat_cor" class="form-label">Cor do Ícone</label>
                                <input type="color" class="form-control form-control-color" id="cat_cor" name="cor" value="#0D6EFD" title="Escolha uma cor para o ícone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btn-salvar-categoria">
                            <span id="btn-salvar-text">Salvar Categoria</span>
                            <span id="btn-salvar-spinner" class="btn-spinner d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalCategoriaEl = document.getElementById('modal-categoria');
        const modalCategoria = new bootstrap.Modal(modalCategoriaEl);
        const formCategoria = document.getElementById('form-categoria');
        const feedbackGlobalEl = document.getElementById('feedbackAreaGlobal');
        const modalFeedbackEl = document.getElementById('modal-feedback-area');
        const listaCategoriasBody = document.getElementById('lista-categorias-body');
        const placeholderSemCategorias = document.getElementById('placeholder-sem-categorias');
        const btnSalvarCategoria = document.getElementById('btn-salvar-categoria');
        const btnSalvarText = document.getElementById('btn-salvar-text');
        const btnSalvarSpinner = document.getElementById('btn-salvar-spinner');

        function exibirIconePreview(iconeVal, corIcone = '#6c757d') {
            let iconHTML = `<span class="cat-icon-display" style="color: ${corIcone}; background-color: #f0f0f0;">?</span>`; // Default fallback
            if (!iconeVal) return iconHTML;
            iconeVal = String(iconeVal).trim();

            if (iconeVal.startsWith('<svg') && iconeVal.endsWith('</svg>')) {
                iconHTML = `<span class="cat-icon-display" style="color: ${corIcone};">${iconeVal}</span>`;
            } else if (/^fa[sbrlkdu]? fa-[a-z0-9-]+/i.test(iconeVal)) {
                iconHTML = `<span class="cat-icon-display" style="color: ${corIcone};"><i class="${iconeVal}"></i></span>`;
            } else if (/^https?:\/\//i.test(iconeVal) && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(iconeVal)) {
                iconHTML = `<span class="cat-icon-display"><img src="${CSS.escape(iconeVal)}" alt="Ícone"></span>`;
            } else if (iconeVal.length > 0 && iconeVal.length <= 5) { // Emojis or short chars
                iconHTML = `<span class="cat-icon-display" style="color:${corIcone}; font-size: 1.25rem;">${CSS.escape(iconeVal)}</span>`;
            } else if (iconeVal.length > 0) {
                iconHTML = `<span class='cat-icon-display text-xs text-muted p-1' title='${CSS.escape(iconeVal)}'>TXT</span>`;
            }
            return iconHTML;
        }
        
        function mostrarFeedback(el, mensagem, tipo = 'danger') {
            el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show small py-2 px-3" role="alert">${mensagem}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            // Auto-dismiss global feedback
            if (el === feedbackGlobalEl) {
                setTimeout(() => {
                    const alertInstance = bootstrap.Alert.getInstance(el.querySelector('.alert'));
                    if (alertInstance) alertInstance.close();
                }, 5000);
            }
        }

        function limparFeedback(el) {
            el.innerHTML = '';
        }

        function atualizarListaCategorias() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                body: 'action=list'
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.cats) {
                    listaCategoriasBody.innerHTML = ''; // Limpa a lista atual
                    if (data.cats.length === 0) {
                        listaCategoriasBody.innerHTML = `<tr id="placeholder-sem-categorias"><td colspan="6" class="text-center text-muted p-5"><i class="fas fa-folder-open fa-3x mb-3"></i><br>Nenhuma categoria cadastrada ainda.</td></tr>`;
                    } else {
                         if(placeholderSemCategorias) placeholderSemCategorias.remove(); // Remove placeholder if it exists as a direct child
                        data.cats.forEach(cat => {
                            const iconeHtml = exibirIconePreview(cat.icone_categoria, cat.cor_icone);
                            const descricaoCurta = cat.descricao_categoria ? (cat.descricao_categoria.length > 70 ? cat.descricao_categoria.substring(0, 70) + '...' : cat.descricao_categoria) : '';
                            const dataFormatada = new Date(cat.data_criacao).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

                            const row = `
                                <tr data-id="${cat.id_categoria}">
                                    <td class="ps-3">${iconeHtml}</td>
                                    <td class="fw-medium">${cat.nome_categoria}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">${cat.slug_categoria}</span></td>
                                    <td class="text-muted small" title="${cat.descricao_categoria || ''}">${descricaoCurta}</td>
                                    <td class="text-muted small">${dataFormatada}</td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-categoria me-1" title="Editar" data-id="${cat.id_categoria}"><i class="fas fa-pencil-alt"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-categoria" title="Excluir" data-id="${cat.id_categoria}"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>`;
                            listaCategoriasBody.insertAdjacentHTML('beforeend', row);
                        });
                    }
                } else {
                    mostrarFeedback(feedbackGlobalEl, data.msg || 'Erro ao atualizar lista de categorias.', 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                mostrarFeedback(feedbackGlobalEl, 'Erro de comunicação ao atualizar lista.', 'danger');
            });
        }

        document.getElementById('btn-nova-categoria').addEventListener('click', function() {
            formCategoria.reset();
            document.getElementById('cat_action').value = 'add';
            document.getElementById('modalCategoriaLabel').textContent = 'Nova Categoria';
            document.getElementById('cat_id').value = '';
            document.getElementById('cat_cor').value = '#0D6EFD'; // Reset color picker
            limparFeedback(modalFeedbackEl);
            modalCategoria.show();
        });

        listaCategoriasBody.addEventListener('click', function(e) {
            if (e.target.closest('.btn-edit-categoria')) {
                const btn = e.target.closest('.btn-edit-categoria');
                const catId = btn.dataset.id;
                limparFeedback(modalFeedbackEl);
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                    body: `action=get&id=${catId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok && data.dados) {
                        document.getElementById('cat_action').value = 'edit';
                        document.getElementById('modalCategoriaLabel').textContent = 'Editar Categoria';
                        document.getElementById('cat_id').value = data.dados.id_categoria;
                        document.getElementById('cat_nome').value = data.dados.nome_categoria;
                        document.getElementById('cat_slug').value = data.dados.slug_categoria;
                        document.getElementById('cat_desc').value = data.dados.descricao_categoria || '';
                        document.getElementById('cat_icone').value = data.dados.icone_categoria || '';
                        document.getElementById('cat_cor').value = data.dados.cor_icone || '#0D6EFD';
                        modalCategoria.show();
                    } else {
                        mostrarFeedback(feedbackGlobalEl, data.msg || 'Erro ao carregar dados da categoria.', 'danger');
                    }
                });
            }

            if (e.target.closest('.btn-delete-categoria')) {
                const btn = e.target.closest('.btn-delete-categoria');
                const catId = btn.dataset.id;
                const catNome = btn.closest('tr').querySelector('td:nth-child(2)').textContent;
                if (confirm(`Tem a certeza que deseja excluir a categoria "${catNome}"? Esta ação não pode ser desfeita.`)) {
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                        body: `action=del&id=${catId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        mostrarFeedback(feedbackGlobalEl, data.msg, data.ok ? 'success' : 'danger');
                        if (data.ok) {
                            atualizarListaCategorias();
                        }
                    });
                }
            }
        });

        formCategoria.addEventListener('submit', function(e) {
            e.preventDefault();
            btnSalvarText.classList.add('d-none');
            btnSalvarSpinner.classList.remove('d-none');
            btnSalvarCategoria.disabled = true;
            limparFeedback(modalFeedbackEl);

            const formData = new FormData(this);
            const slugInput = formData.get('slug');
            if (slugInput !== null && slugInput.trim() === '') { // Only delete if it exists and is empty
                 formData.delete('slug');
            }
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: new URLSearchParams(formData) // FormData will be URL-encoded
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    modalCategoria.hide();
                    mostrarFeedback(feedbackGlobalEl, data.msg, 'success');
                    atualizarListaCategorias();
                } else {
                    mostrarFeedback(modalFeedbackEl, data.msg, 'danger');
                }
            })
            .catch(error => {
                 console.error('Fetch error on form submit:', error);
                 mostrarFeedback(modalFeedbackEl, 'Erro de comunicação ao salvar. Tente novamente.', 'danger');
            })
            .finally(() => {
                btnSalvarText.classList.remove('d-none');
                btnSalvarSpinner.classList.add('d-none');
                btnSalvarCategoria.disabled = false;
            });
        });
        
        // Sidebar Toggle Logic (if header.php doesn't handle it globally)
        const mobileSidebarToggleButton = document.getElementById('adminMobileSidebarToggle'); 
        const adminSidebar = document.getElementById('adminSidebar');
        const contentWrapper = document.getElementById('contentWrapper'); 

        if (mobileSidebarToggleButton && adminSidebar && contentWrapper) {
            mobileSidebarToggleButton.addEventListener('click', function() {
                adminSidebar.classList.toggle('active');
                contentWrapper.classList.toggle('sidebar-active-overlay'); 
            });
        }
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
