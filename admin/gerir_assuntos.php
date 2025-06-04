<?php
// admin/gerir_categorias.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php'); // Garante que apenas administradores logados acessem
require_once __DIR__ . '/../db/db_connect.php'; // Conexão com o banco de dados

// Função para gerar slugs únicos (adaptada para categorias)
function gerarSlugUnico($texto, $pdo, $tabela, $coluna_slug, $id_atual = null) {
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        // Adapta o prefixo para 'categoria-'
        $slug = 'categoria-' . uniqid();
    }

    $i = 1;
    $original_slug = $slug;
    // Adapta a query para a tabela e coluna de ID corretas
    $sql_check = "SELECT $coluna_slug FROM $tabela WHERE $coluna_slug = :slug";
    $coluna_id = 'id_categoria'; // Coluna ID para categorias_podcast

    if ($id_atual !== null) {
        $sql_check .= " AND $coluna_id != :id_atual";
    }
    $stmt = $pdo->prepare($sql_check);
    
    do {
        $params = [':slug' => $slug];
        if ($id_atual !== null) {
            $params[':id_atual'] = $id_atual;
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

// --- PROCESSAMENTO DE AÇÕES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Limpar qualquer output buffer antes de enviar JSON
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');

    // Função de resposta JSON (nome genérico para evitar conflitos se incluída em outros lugares)
    function enviar_resposta_json($ok, $msg, $extra = []) {
        echo json_encode(array_merge(['ok'=>$ok, 'msg'=>$msg], $extra)); exit;
    }

    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $nome = trim($_POST['nome_categoria'] ?? '');
        $slug_input = trim($_POST['slug_categoria'] ?? '');
        $descricao = trim($_POST['descricao_categoria'] ?? '');
        $icone = trim($_POST['icone_categoria'] ?? '');
        $cor_icone = trim($_POST['cor_icone_categoria'] ?? '#6c757d'); // Default color
        $id_categoria_edit = ($action === 'edit') ? intval($_POST['id'] ?? 0) : null;

        if (!$nome) enviar_resposta_json(false, 'Nome da categoria é obrigatório.');
        if ($action === 'edit' && !$id_categoria_edit) enviar_resposta_json(false, 'ID da categoria é obrigatório para edição.');

        // Gera o slug se não foi fornecido ou se o fornecido é inválido
        $slug_final = !empty($slug_input) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug_input) ? $slug_input : gerarSlugUnico($nome, $pdo, 'categorias_podcast', 'slug_categoria', $id_categoria_edit);
        
        // Verificar unicidade do nome e do slug
        $sql_check_unicidade = "SELECT id_categoria FROM categorias_podcast WHERE (nome_categoria = :nome OR slug_categoria = :slug)";
        $params_check_unicidade = [':nome' => $nome, ':slug' => $slug_final];
        if ($id_categoria_edit) {
            $sql_check_unicidade .= " AND id_categoria != :id_categoria_edit";
            $params_check_unicidade[':id_categoria_edit'] = $id_categoria_edit;
        }
        $stmt_check = $pdo->prepare($sql_check_unicidade);
        $stmt_check->execute($params_check_unicidade);
        if ($stmt_check->fetch()) enviar_resposta_json(false, 'Já existe uma categoria com este nome ou slug.');

        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO categorias_podcast (nome_categoria, slug_categoria, descricao_categoria, icone_categoria, cor_icone, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$nome, $slug_final, $descricao, $icone, $cor_icone]);
                enviar_resposta_json(true, 'Categoria adicionada com sucesso!');
            } elseif ($action === 'edit' && $id_categoria_edit) {
                $stmt = $pdo->prepare("UPDATE categorias_podcast SET nome_categoria=?, slug_categoria=?, descricao_categoria=?, icone_categoria=?, cor_icone=? WHERE id_categoria=?");
                $stmt->execute([$nome, $slug_final, $descricao, $icone, $cor_icone, $id_categoria_edit]);
                enviar_resposta_json(true, 'Categoria atualizada com sucesso!');
            }
        } catch(PDOException $e) { 
            error_log("Erro DB $action categoria: " . $e->getMessage());
            enviar_resposta_json(false, 'Erro ao guardar a categoria no banco de dados: ' . $e->getMessage()); 
        }
    }
    
    if ($action === 'del') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) enviar_resposta_json(false, 'ID da categoria é obrigatório.');
        
        try {
            // Verificar se existem assuntos vinculados a esta categoria
            $stmt_check_assuntos = $pdo->prepare("SELECT COUNT(*) FROM assuntos_podcast WHERE id_categoria = ?");
            $stmt_check_assuntos->execute([$id]);
            if ($stmt_check_assuntos->fetchColumn() > 0) {
                enviar_resposta_json(false, 'Não é possível excluir: esta categoria tem assuntos de podcast vinculados.');
            }

            $stmt = $pdo->prepare("DELETE FROM categorias_podcast WHERE id_categoria=?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                enviar_resposta_json(true, 'Categoria apagada com sucesso.');
            } else {
                enviar_resposta_json(false, 'Categoria não encontrada ou erro ao apagar.');
            }
        } catch(PDOException $e) { 
            error_log("Erro ao apagar categoria: " . $e->getMessage());
            enviar_resposta_json(false, 'Erro ao apagar a categoria: ' . $e->getMessage()); 
        }
    }

    if ($action === 'get') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) enviar_resposta_json(false, 'ID da categoria é obrigatório.');
        $stmt = $pdo->prepare("SELECT * FROM categorias_podcast WHERE id_categoria=?");
        $stmt->execute([$id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dados) {
            enviar_resposta_json(true, 'Dados da categoria obtidos.', ['dados'=>$dados]);
        } else {
            enviar_resposta_json(false, 'Categoria não encontrada.');
        }
    }

    if ($action === 'list') {
        try {
            // Query para listar categorias e contar quantos assuntos cada uma possui
            $stmt = $pdo->query("SELECT cp.*, 
                                (SELECT COUNT(*) FROM assuntos_podcast ap WHERE ap.id_categoria = cp.id_categoria) as total_assuntos
                                FROM categorias_podcast cp
                                ORDER BY cp.nome_categoria ASC");
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            enviar_resposta_json(true, 'Lista de categorias.', ['categorias'=>$categorias]);
        } catch (PDOException $e) {
            error_log("Erro ao listar categorias: " . $e->getMessage());
            enviar_resposta_json(false, 'Erro ao carregar a lista de categorias.');
        }
    }
    enviar_resposta_json(false, 'Ação desconhecida ou não implementada.');
}


$pageTitle = "Gerir Categorias dos Podcasts"; // Título da página alterado
$userName_for_header = $_SESSION['user_nome_completo'] ?? 'Admin';
$avatarUrl_for_header = $_SESSION['user_avatar_url'] ?? '';
// Lógica para gerar avatar de fallback (mantida)
if (!$avatarUrl_for_header) { 
    $initials_for_header = ''; $nameParts_for_header = explode(' ', $userName_for_header);
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(substr($nameParts_for_header[0], 0, 1)) : 'A';
    if (count($nameParts_for_header) > 1) $initials_for_header .= strtoupper(substr(end($nameParts_for_header), 0, 1));
    elseif (strlen($nameParts_for_header[0]) > 1 && $initials_for_header === strtoupper(substr($nameParts_for_header[0], 0, 1))) $initials_for_header .= strtoupper(substr($nameParts_for_header[0], 1, 1));
    if(empty($initials_for_header) || strlen($initials_for_header) > 2) $initials_for_header = "AD";
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}

// Não é necessário buscar categorias para um select aqui, pois estamos na página de gerir categorias.

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Painel Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" xintegrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Estilos CSS (mantidos do original para consistência visual) */
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
        .form-control-sm, .form-select-sm { font-size: 0.8rem; padding-top: 0.2rem; padding-bottom: 0.2rem; }
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { color: #2c3e50; }
        .modal-footer { background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
        .cat-icon-display-sm { /* Estilo para exibir ícones */
            width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 0.25rem; background-color: #e9ecef; color: #495057; overflow: hidden;
            vertical-align: middle;
        }
        .cat-icon-display-sm svg, .cat-icon-display-sm i { font-size: 1rem; }
        .cat-icon-display-sm img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap;}
        .table td { vertical-align: middle; font-size: 0.85rem; }
        .table-sm td, .table-sm th { padding: .4rem .5rem; }
        .btn-spinner { display: inline-block; width: 1rem; height: 1rem; vertical-align: text-bottom; border: .2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: .75s linear infinite spinner-border; }
        .truncate-multiline { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .color-picker-input { padding: 0.1rem 0.2rem; height: calc(1.5em + .5rem + 2px); width: 60px; } /* Para o input de cor */

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
        // Inclui o sidebar.php, ajustando o link ativo se necessário dentro do sidebar.php
        // Para este exemplo, o sidebar.php precisaria de uma lógica para marcar 'Gerir Categorias' como ativo.
        // Ou, um sidebar específico para categorias poderia ser criado.
        // Por simplicidade, vou manter o fallback do arquivo original, mas o ideal é ter o sidebar.php adaptável.
        if (file_exists(__DIR__ . '/sidebar.php')) {
            // Supondo que sidebar.php pode receber $currentPage para definir o link ativo
            $currentPage_sidebar = 'gerir_categorias'; 
            require __DIR__ . '/sidebar.php'; 
        } else {
            // Fallback se sidebar.php não existir (adaptado para 'Gerir Categorias' como ativo)
            echo '<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow-lg" id="adminSidebar" style="width: 260px; min-height: 100vh;">';
            echo '<a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none p-2"><i class="fas fa-headphones-alt fa-2x me-2"></i><span class="fs-4 fw-bold">AudioTO <small class="fw-light fs-6">Admin</small></span></a><hr>';
            echo '<ul class="nav nav-pills flex-column mb-auto">';
            // Exemplo de como o sidebar.php poderia lidar com o link ativo:
            echo '<li class="nav-item"><a href="index.php" class="nav-link text-white"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a></li>';
            echo '<li class="nav-item"><a href="gerir_categorias.php" class="nav-link active bg-primary"><i class="fas fa-tags fa-fw me-2"></i>Gerir Categorias</a></li>';
            echo '<li class="nav-item"><a href="gerir_assuntos.php" class="nav-link text-white"><i class="fas fa-bookmark fa-fw me-2"></i>Gerir Assuntos</a></li>';
            // Adicionar outros links conforme necessário
            echo '</ul><hr></div>';
        }
    ?>

    <div class="content-wrapper" id="contentWrapper">
        <?php 
            // Inclui o header.php
            if (file_exists(__DIR__ . '/header.php')) {
                require __DIR__ . '/header.php';
            } else {
                // Fallback se header.php não existir
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
                
                <div id="feedbackAreaGlobal" class="mb-3"></div>

                <div class="card mb-4">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-10">
                                <label for="filtroNomeCategoria" class="form-label small mb-1">Buscar por Nome da Categoria</label>
                                <input type="text" id="filtroNomeCategoria" class="form-control form-control-sm" placeholder="Digite para buscar...">
                            </div>
                            <div class="col-md-2 d-grid">
                               <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimparFiltrosCategoria"><i class="fas fa-times me-1"></i>Limpar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Lista de Categorias</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 5%;">Ícone</th>
                                        <th>Nome da Categoria</th>
                                        <th class="d-none d-md-table-cell">Slug</th>
                                        <th class="d-none d-lg-table-cell">Descrição</th>
                                        <th class="text-center">Assuntos</th>
                                        <th class="d-none d-lg-table-cell">Criado em</th>
                                        <th class="text-end pe-3">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="categoria-list-body">
                                    </tbody>
                            </table>
                        </div>
                         <div id="placeholderVazioCategorias" class="text-center p-5 text-muted" style="display: none;">
                            <i class="fas fa-tags fa-3x mb-3"></i><br>
                            Nenhuma categoria encontrada com os filtros atuais ou nenhuma cadastrada.
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
                    <input type="hidden" name="id" id="categoria_id">
                    <input type="hidden" name="action" id="categoria_action" value="add">

                    <div class="modal-header">
                        <h5 class="modal-title" id="modalCategoriaLabel">Nova Categoria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="modal-feedback-area-categoria" class="mb-3"></div>
                        <div class="row g-3">
                            <div class="col-md-12 mb-3">
                                <label for="categoria_nome" class="form-label">Nome da Categoria <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="categoria_nome" name="nome_categoria" required maxlength="100">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="categoria_slug" class="form-label">Slug (URL)</label>
                            <input type="text" class="form-control form-control-sm" id="categoria_slug" name="slug_categoria" maxlength="120" placeholder="Gerado automaticamente se vazio">
                             <div class="form-text small">Use apenas letras minúsculas, números e hífens. Ex: minha-nova-categoria</div>
                        </div>
                        <div class="mb-3">
                            <label for="categoria_descricao" class="form-label">Descrição <small class="text-muted">(Opcional)</small></label>
                            <textarea name="descricao_categoria" id="categoria_descricao" class="form-control form-control-sm" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="row g-3">
                             <div class="col-md-8 mb-3">
                                <label for="categoria_icone" class="form-label">Ícone <small class="text-muted">(Ex: fas fa-book, URL de imagem, Emoji)</small></label>
                                <input type="text" class="form-control form-control-sm" id="categoria_icone" name="icone_categoria" maxlength="100" placeholder="fas fa-podcast">
                                <div class="form-text small">Pode ser uma classe FontAwesome (ex: <code>fas fa-star</code>), URL de uma imagem pequena (<code>.png</code>, <code>.svg</code>) ou um emoji.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="categoria_cor_icone" class="form-label">Cor do Ícone</label>
                                <input type="color" class="form-control form-control-sm form-control-color color-picker-input" id="categoria_cor_icone" name="cor_icone_categoria" value="#6c757d" title="Escolha uma cor para o ícone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="btn-salvar-categoria">
                            <span id="btn-salvar-categoria-text">Salvar Categoria</span>
                            <span id="btn-salvar-categoria-spinner" class="btn-spinner d-none ms-1" role="status" aria-hidden="true"></span>
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
        const feedbackGlobalElCategoria = document.getElementById('feedbackAreaGlobal'); // Renomeado para evitar conflito se ambos os scripts estiverem na mesma página (improvável aqui)
        const modalFeedbackElCategoria = document.getElementById('modal-feedback-area-categoria');
        const categoriaListTbody = document.getElementById('categoria-list-body');
        const placeholderVazioCategorias = document.getElementById('placeholderVazioCategorias');
        
        const btnNovaCategoria = document.getElementById('btn-nova-categoria');
        const btnSalvarCategoria = document.getElementById('btn-salvar-categoria');
        const btnSalvarCategoriaText = document.getElementById('btn-salvar-categoria-text');
        const btnSalvarCategoriaSpinner = document.getElementById('btn-salvar-categoria-spinner');

        // Função para renderizar ícones (mantida do original, é genérica)
        function renderIcon(icone, cor = '#6c757d', sizeClass = '') {
            let iconHTML = `<span class="cat-icon-display-sm ${sizeClass}" style="color: ${cor}; background-color: ${cor}20;"><i class="fas fa-tag"></i></span>`; // Default
            if (!icone) return iconHTML;
            icone = String(icone).trim();

            if (icone.startsWith('<svg') && icone.endsWith('</svg>')) {
                iconHTML = `<span class="cat-icon-display-sm ${sizeClass}" style="color: ${cor};">${icone}</span>`;
            } else if (/^fa[sbrlkdu]? fa-[a-z0-9-]+/i.test(icone)) {
                iconHTML = `<span class="cat-icon-display-sm ${sizeClass}" style="color: ${cor};"><i class="${icone}"></i></span>`;
            } else if (/^https?:\/\//i.test(icone) && /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(icone)) {
                iconHTML = `<span class="cat-icon-display-sm ${sizeClass}"><img src="${CSS.escape(icone)}" alt="Ícone"></span>`;
            } else if (icone.length > 0 && icone.length <= 5) { // Emojis or short chars
                iconHTML = `<span class="cat-icon-display-sm ${sizeClass}" style="color:${cor}; font-size: 1rem;">${CSS.escape(icone)}</span>`;
            }
            return iconHTML;
        }
        
        function mostrarFeedback(el, mensagem, tipo = 'danger') {
            el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show small py-2 px-3" role="alert">${mensagem}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            if (el === feedbackGlobalElCategoria) {
                setTimeout(() => {
                    const alertInstance = bootstrap.Alert.getInstance(el.querySelector('.alert'));
                    if (alertInstance) alertInstance.close();
                }, 5000);
            }
        }

        function limparFeedback(el) { el.innerHTML = ''; }

        function atualizarListaCategorias() {
            fetch(window.location.pathname, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                body: 'action=list'
            })
            .then(response => response.json())
            .then(data => {
                if (data.ok && data.categorias) {
                    categoriaListTbody.innerHTML = ''; 
                    if (data.categorias.length === 0) {
                        placeholderVazioCategorias.style.display = 'block';
                        if (categoriaListTbody.querySelector('tr')) categoriaListTbody.innerHTML = '';
                    } else {
                        placeholderVazioCategorias.style.display = 'none';
                        data.categorias.forEach(cat => {
                            const dataFormatada = new Date(cat.data_criacao).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            const descricaoCurta = cat.descricao_categoria ? (cat.descricao_categoria.length > 70 ? cat.descricao_categoria.substring(0, 70) + '...' : cat.descricao_categoria) : '<span class="text-muted small">-</span>';
                            
                            const row = `
                                <tr data-id="${cat.id_categoria}" data-nome-categoria="${cat.nome_categoria.toLowerCase()}">
                                    <td class="ps-3">${renderIcon(cat.icone_categoria, cat.cor_icone)}</td>
                                    <td>
                                        <strong class="text-dark">${cat.nome_categoria}</strong>
                                    </td>
                                    <td class="d-none d-md-table-cell"><span class="badge bg-light text-dark border">${cat.slug_categoria}</span></td>
                                    <td class="d-none d-lg-table-cell small">${descricaoCurta}</td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill ${cat.total_assuntos > 0 ? 'bg-primary-subtle text-primary-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'}">
                                            ${cat.total_assuntos}
                                        </span>
                                    </td>
                                    <td class="d-none d-lg-table-cell text-muted small">${dataFormatada}</td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-categoria me-1 py-1 px-2" title="Editar" data-id="${cat.id_categoria}"><i class="fas fa-pencil-alt"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-categoria py-1 px-2" title="Excluir" data-id="${cat.id_categoria}"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>`;
                            categoriaListTbody.insertAdjacentHTML('beforeend', row);
                        });
                    }
                     filtrarTabelaCategorias(); // Reaplicar filtros após carregar
                } else {
                    mostrarFeedback(feedbackGlobalElCategoria, data.msg || 'Erro ao atualizar lista de categorias.', 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                mostrarFeedback(feedbackGlobalElCategoria, 'Erro de comunicação ao atualizar lista.', 'danger');
            });
        }

        btnNovaCategoria.addEventListener('click', function() {
            formCategoria.reset();
            document.getElementById('categoria_action').value = 'add';
            document.getElementById('modalCategoriaLabel').textContent = 'Adicionar Nova Categoria';
            document.getElementById('categoria_id').value = '';
            document.getElementById('categoria_cor_icone').value = '#6c757d'; // Reset color
            limparFeedback(modalFeedbackElCategoria);
            modalCategoria.show();
        });

        categoriaListTbody.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit-categoria');
            const deleteBtn = e.target.closest('.btn-delete-categoria');

            if (editBtn) {
                const categoriaId = editBtn.dataset.id;
                limparFeedback(modalFeedbackElCategoria);
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                    body: `action=get&id=${categoriaId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.ok && data.dados) {
                        formCategoria.reset();
                        document.getElementById('categoria_action').value = 'edit';
                        document.getElementById('modalCategoriaLabel').textContent = 'Editar Categoria';
                        document.getElementById('categoria_id').value = data.dados.id_categoria;
                        document.getElementById('categoria_nome').value = data.dados.nome_categoria;
                        document.getElementById('categoria_slug').value = data.dados.slug_categoria;
                        document.getElementById('categoria_descricao').value = data.dados.descricao_categoria || '';
                        document.getElementById('categoria_icone').value = data.dados.icone_categoria || '';
                        document.getElementById('categoria_cor_icone').value = data.dados.cor_icone || '#6c757d';
                        modalCategoria.show();
                    } else {
                        mostrarFeedback(feedbackGlobalElCategoria, data.msg || 'Erro ao carregar dados da categoria.', 'danger');
                    }
                });
            }

            if (deleteBtn) {
                const categoriaId = deleteBtn.dataset.id;
                const categoriaNome = deleteBtn.closest('tr').querySelector('td:nth-child(2) strong').textContent;
                // Usar um modal de confirmação customizado em vez de confirm()
                // Por simplicidade, vou manter o confirm() aqui, mas o ideal seria um modal Bootstrap
                if (confirm(`Tem a certeza que deseja excluir a categoria "${categoriaNome}"? Esta ação não poderá ser desfeita. Se houver assuntos vinculados, a exclusão será impedida.`)) {
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
                        body: `action=del&id=${categoriaId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        mostrarFeedback(feedbackGlobalElCategoria, data.msg, data.ok ? 'success' : 'danger');
                        if (data.ok) {
                            atualizarListaCategorias();
                        }
                    });
                }
            }
        });

        formCategoria.addEventListener('submit', function(e) {
            e.preventDefault();
            btnSalvarCategoriaText.classList.add('d-none');
            btnSalvarCategoriaSpinner.classList.remove('d-none');
            btnSalvarCategoria.disabled = true;
            limparFeedback(modalFeedbackElCategoria);

            const formData = new FormData(this);
             if (formData.get('slug_categoria') && formData.get('slug_categoria').trim() === '') {
                formData.delete('slug_categoria'); // Deixa o backend gerar se estiver vazio
            }
            
            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    modalCategoria.hide();
                    mostrarFeedback(feedbackGlobalElCategoria, data.msg, 'success');
                    atualizarListaCategorias();
                } else {
                    mostrarFeedback(modalFeedbackElCategoria, data.msg, 'danger');
                }
            })
            .catch(error => {
                 console.error('Fetch error on form submit:', error);
                 mostrarFeedback(modalFeedbackElCategoria, 'Erro de comunicação ao salvar. Tente novamente.', 'danger');
            })
            .finally(() => {
                btnSalvarCategoriaText.classList.remove('d-none');
                btnSalvarCategoriaSpinner.classList.add('d-none');
                btnSalvarCategoria.disabled = false;
            });
        });
        
        // Filtros para categorias
        const filtroNomeCategoriaInput = document.getElementById('filtroNomeCategoria');
        const btnLimparFiltrosCategoria = document.getElementById('btnLimparFiltrosCategoria');

        function filtrarTabelaCategorias() {
            const termoBusca = filtroNomeCategoriaInput.value.toLowerCase();
            let visibleRows = 0;

            document.querySelectorAll('#categoria-list-body tr').forEach(row => {
                if (row.id === 'placeholder-sem-categorias-filtro') return; 
                
                const nomeCategoria = row.dataset.nomeCategoria || row.querySelector('td:nth-child(2) strong').textContent.toLowerCase();
                const matchNome = !termoBusca || nomeCategoria.includes(termoBusca);

                if (matchNome) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Toggle placeholder de filtro
            const temItensParaFiltrar = categoriaListTbody.querySelectorAll('tr[data-id]').length > 0;
            const filterPlaceholder = document.getElementById('placeholder-sem-categorias-filtro');

            if (temItensParaFiltrar) {
                if (visibleRows === 0) {
                    if (!filterPlaceholder) {
                        categoriaListTbody.insertAdjacentHTML('beforeend', '<tr id="placeholder-sem-categorias-filtro"><td colspan="7" class="text-center text-muted p-5"><i class="fas fa-search fa-2x mb-2"></i><br>Nenhuma categoria encontrada com os filtros aplicados.</td></tr>');
                    }
                    placeholderVazioCategorias.style.display = 'none';
                } else {
                    if (filterPlaceholder) filterPlaceholder.remove();
                }
            } else if (!temItensParaFiltrar) { // Se não há itens nenhuns
                 placeholderVazioCategorias.style.display = 'block';
                 if (filterPlaceholder) filterPlaceholder.remove();
            }
        }

        filtroNomeCategoriaInput.addEventListener('input', filtrarTabelaCategorias);
        btnLimparFiltrosCategoria.addEventListener('click', () => {
            filtroNomeCategoriaInput.value = '';
            filtrarTabelaCategorias();
        });

        // Sidebar Toggle Logic (mantido do original)
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
        
        atualizarListaCategorias(); // Carga inicial da lista de categorias
    });
    </script>
</body>
</html>
