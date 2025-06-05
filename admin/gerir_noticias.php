<?php
require_once __DIR__ . '/../sessao/session_handler.php';
// Para a página de admin, vamos usar uma função de verificação de admin específica
// que pode estar em session_handler.php ou definida aqui.
// Exemplo: requireAdmin('login.php');
// Por agora, manteremos a verificação de função de sessão.
requireLogin('login.php'); 

require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['user_funcao']) || $_SESSION['user_funcao'] !== 'administrador') {
    $_SESSION['feedback_message'] = "Acesso negado. Apenas administradores podem aceder a esta página.";
    $_SESSION['feedback_type'] = "error";
    header('Location: inicio.php');
    exit;
}

$pageTitle = "Gerir Notícias"; // Título mais direto para admin
$activeAdminPage = 'noticias'; // Para destacar na sidebar de admin
$userId = $_SESSION['user_id'] ?? null; 
$userName = $_SESSION['user_nome_completo'] ?? 'Admin';
$userEmail = $_SESSION['user_email'] ?? 'admin@audioto.com'; // Não exibido no header de admin do exemplo
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

function get_admin_avatar_placeholder($user_name, $avatar_url_from_session, $size = 32) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_parts = explode(' ', trim($user_name));
    $initials = '';
    if (count($name_parts) > 0 && !empty($name_parts[0])) {
        $initials .= strtoupper(mb_substr($name_parts[0], 0, 1));
    }
    if (count($name_parts) > 1 && !empty(end($name_parts))) {
        $initials .= strtoupper(mb_substr(end($name_parts), 0, 1));
    } elseif (empty($initials) && !empty($user_name)) {
        $initials = strtoupper(mb_substr($user_name, 0, 2));
    } elseif (empty($initials)) {
        $initials = "AD";
    }
    $name_encoded = urlencode($initials);
    // Cor de fundo da sidebar de admin do exemplo gerir_categorias.php (aproximada com Tailwind)
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=34495e&color=ecf0f1&size={$size}&rounded=true&bold=true";
}
$adminAvatarUrl = get_admin_avatar_placeholder($userName, $userAvatarUrlSession, 32);


$feedback_message = $_SESSION['feedback_message'] ?? null;
$feedback_type = $_SESSION['feedback_type'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);

$edit_mode = false;
$noticia_para_editar = null;

function gerarSlug($titulo) {
    $slug = mb_strtolower($titulo, 'UTF-8');
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'noticia-' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $slug_noticia = !empty(trim($_POST['slug_noticia'] ?? '')) ? trim($_POST['slug_noticia']) : gerarSlug($titulo);
    $excerto = trim($_POST['excerto'] ?? '');
    $conteudo_completo_html = trim($_POST['conteudo_completo_html'] ?? '');
    $url_imagem_destaque = trim($_POST['url_imagem_destaque'] ?? '');
    $categoria_noticia = trim($_POST['categoria_noticia'] ?? '');
    $autor_noticia = 'Equipe AudioTO'; // <<<<--- AQUI, sempre fixo
    $data_publicacao = trim($_POST['data_publicacao'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $visibilidade = $_POST['visibilidade'] ?? 'publico';
    $id_utilizador_autor = $userId;

    if (empty($titulo) || empty($data_publicacao) || empty($conteudo_completo_html)) {
        $feedback_message = "Título, Data de Publicação e Conteúdo Completo são obrigatórios.";
        $feedback_type = "error";
        if ($_POST['action'] === 'edit_noticia' && isset($_POST['id_noticia'])) {
            $noticia_para_editar = $_POST; 
            $edit_mode = true;
        } else {
            $noticia_para_editar = $_POST;
        }
    } else {
        if ($_POST['action'] === 'add_noticia') {
            try {
                $stmt = $pdo->prepare("INSERT INTO noticias (titulo, slug_noticia, excerto, conteudo_completo_html, url_imagem_destaque, categoria_noticia, autor_noticia, data_publicacao, tags, ativo, visibilidade, id_utilizador_autor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$titulo, $slug_noticia, $excerto, $conteudo_completo_html, $url_imagem_destaque, $categoria_noticia, $autor_noticia, $data_publicacao, $tags, $ativo, $visibilidade, $id_utilizador_autor]);
                $_SESSION['feedback_message'] = "Notícia adicionada com sucesso!";
                $_SESSION['feedback_type'] = "success";
                $link = SITE_URL . '/noticias.php';
                $subject = 'Nova notícia publicada';
                $msg = 'Confira em: <a href="' . $link . '">' . htmlspecialchars($link) . '</a>';
                notifyUsers($pdo, 'notificar_noticias_plataforma', $subject, $msg);
            } catch (PDOException $e) {
                $_SESSION['feedback_message'] = "Erro ao adicionar notícia: " . ($e->getCode() == '23000' ? 'Já existe uma notícia com este slug.' : $e->getMessage());
                $_SESSION['feedback_type'] = "error";
            }
        } elseif ($_POST['action'] === 'edit_noticia' && isset($_POST['id_noticia'])) {
            $id_noticia = filter_var($_POST['id_noticia'], FILTER_VALIDATE_INT);
            $autor_noticia = 'Equipe AudioTO'; // Sempre fixo aqui também
            if ($id_noticia) {
                try {
                    $stmt = $pdo->prepare("UPDATE noticias SET titulo = ?, slug_noticia = ?, excerto = ?, conteudo_completo_html = ?, url_imagem_destaque = ?, categoria_noticia = ?, autor_noticia = ?, data_publicacao = ?, tags = ?, ativo = ?, visibilidade = ? WHERE id_noticia = ?");
                    $stmt->execute([$titulo, $slug_noticia, $excerto, $conteudo_completo_html, $url_imagem_destaque, $categoria_noticia, $autor_noticia, $data_publicacao, $tags, $ativo, $visibilidade, $id_noticia]);
                    $_SESSION['feedback_message'] = "Notícia atualizada com sucesso!";
                    $_SESSION['feedback_type'] = "success";
                } catch (PDOException $e) {
                    $_SESSION['feedback_message'] = "Erro ao atualizar notícia: " . ($e->getCode() == '23000' ? 'Já existe outra notícia com este slug.' : $e->getMessage());
                    $_SESSION['feedback_type'] = "error";
                }
            } else {
                $_SESSION['feedback_message'] = "ID da notícia inválido para edição.";
                $_SESSION['feedback_type'] = "error";
            }
        }
        if (empty($feedback_message) || $feedback_type === 'success') {
            header('Location: gerir_noticias.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_noticia' && isset($_POST['id_noticia_delete'])) {
    $id_noticia_delete = filter_var($_POST['id_noticia_delete'], FILTER_VALIDATE_INT);
    if ($id_noticia_delete) {
        try {
            $stmt = $pdo->prepare("DELETE FROM noticias WHERE id_noticia = ?");
            $stmt->execute([$id_noticia_delete]);
            $_SESSION['feedback_message'] = "Notícia excluída com sucesso!";
            $_SESSION['feedback_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['feedback_message'] = "Erro ao excluir notícia: " . $e->getMessage();
            $_SESSION['feedback_type'] = "error";
        }
        header('Location: gerir_noticias.php');
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id_noticia_get = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_noticia_get) {
        $stmt_edit = $pdo->prepare("SELECT * FROM noticias WHERE id_noticia = ?");
        $stmt_edit->execute([$id_noticia_get]);
        $noticia_para_editar_db = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if ($noticia_para_editar_db) {
            $noticia_para_editar = $noticia_para_editar_db;
            $edit_mode = true;
        } else {
            if (empty($feedback_message)) { 
                $feedback_message = "Notícia não encontrada para edição.";
                $feedback_type = "error";
            }
        }
    }
}

$lista_noticias = [];
try {
    $stmt_lista = $pdo->query("SELECT id_noticia, titulo, slug_noticia, categoria_noticia, autor_noticia, data_publicacao, ativo, visibilidade, excerto, conteudo_completo_html, url_imagem_destaque, tags FROM noticias ORDER BY data_publicacao DESC");
    $lista_noticias = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($feedback_message)) {
        $feedback_message = "Erro ao carregar lista de notícias: " . $e->getMessage();
        $feedback_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> - AudioTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#2563eb', // Cor principal do tema AudioTO
                        'primary-blue-light': '#dbeafe',
                        'primary-blue-dark': '#1e40af',
                        'admin-sidebar-bg': '#2c3e50', // Cor da sidebar do admin (exemplo gerir_categorias)
                        'admin-sidebar-text': '#bdc3c7',
                        'admin-sidebar-hover-bg': '#34495e',
                        'admin-sidebar-hover-text': '#ffffff',
                        'admin-sidebar-active-bg': '#3498db', // Cor de link ativo na sidebar admin
                        'admin-header-bg': '#ffffff',
                        'light-bg': '#f0f2f5', // Fundo geral do conteúdo admin
                        'card-bg': '#ffffff',
                        'dark-text': '#1f2937', // Cor de texto principal
                        'medium-text': '#4b5563',
                        'light-text': '#6b7280',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b',
                        'info': '#3b82f6',
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                     animation: {
                        'modal-pop': 'modalPop 0.3s ease-out forwards',
                    },
                    keyframes: {
                        modalPop: {
                            '0%': { opacity: '0', transform: 'scale(0.95) translateY(-20px)' },
                            '100%': { opacity: '1', transform: 'scale(1) translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Estilos para a sidebar de admin, adaptados do gerir_categorias.php */
       body { font-family: 'Nunito', sans-serif; background-color: #f0f2f5; }
        .main-wrapper { display: flex; min-height: 100vh; }
        #adminSidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        #adminSidebar .nav-link { color: #bdc3c7; padding: 0.8rem 1.25rem; font-size: 0.9rem; border-left: 3px solid transparent; }
        #adminSidebar .nav-link:hover { background-color: #34495e; color: #ffffff; border-left-color: #3498db; }
        #adminSidebar .nav-link.active { background-color: #3498db; color: #ffffff; font-weight: 600; border-left-color: #2980b9; }
        #adminSidebar .nav-link .fas, #adminSidebar .nav-link .far { margin-right: 0.8rem; width: 20px; text-align: center; }
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.5); }
        .modal-content-tw { max-height: 90vh; }
        .table-tw { width: 100%; border-collapse: collapse; }
        .table-tw th, .table-tw td { border: 1px solid theme('colors.gray.300'); padding: theme('spacing.3'); text-align: left; font-size: 0.875rem; /* text-sm */ }
        .table-tw thead th { background-color: theme('colors.gray.100'); font-weight: 600; color: theme('colors.gray.600'); text-transform: uppercase; letter-spacing: 0.05em;}
        .table-tw tbody tr:nth-child(even) { background-color: theme('colors.gray.50'); }
        .table-tw tbody tr:hover { background-color: theme('colors.primary-blue-light / 50%'); }
        .form-input-tw {
            display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; line-height: 1.25rem;
            color: theme('colors.dark-text'); background-color: theme('colors.white');
            border: 1px solid theme('colors.gray.300'); border-radius: 0.375rem; /* rounded-md */
            box-shadow: theme('boxShadow.sm');
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-input-tw:focus {
            border-color: theme('colors.primary-blue');
            outline: 0;
            box-shadow: 0 0 0 0.2rem theme('colors.primary-blue / 25%');
        }
        .form-select-tw { /* Estilo similar para select */
            display: block; width: 100%; padding: 0.5rem 2.5rem 0.5rem 0.75rem; font-size: 0.875rem;
            font-weight: 400; line-height: 1.5; color: theme('colors.dark-text'); background-color: theme('colors.white');
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 16px 12px;
            border: 1px solid theme('colors.gray.300'); border-radius: 0.375rem; box-shadow: theme('boxShadow.sm');
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
        }
        .form-select-tw:focus {
            border-color: theme('colors.primary-blue'); outline: 0;
            box-shadow: 0 0 0 0.2rem theme('colors.primary-blue / 25%');
        }
        .form-checkbox-tw {
            height: 1rem; width: 1rem; color: theme('colors.primary-blue');
            border-color: theme('colors.gray.300'); border-radius: 0.25rem; /* rounded */
            focus:ring-primary-blue;
        }

    </style>
</head>
<body class="bg-light-bg">

    <div class="main-wrapper flex min-h-screen">
      <?php 
        if (file_exists(__DIR__ . '/sidebar.php')) {
            require __DIR__ . '/sidebar.php'; 
        } else {
            echo '<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow-lg" id="adminSidebar" style="width: 260px; min-height: 100vh;">';
            echo '<a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none p-2"><i class="fas fa-headphones-alt fa-2x me-2"></i><span class="fs-4 fw-bold">AudioTO <small class="fw-light fs-6">Admin</small></span></a><hr>';
            echo '<ul class="nav nav-pills flex-column mb-auto"><li class="nav-item"><a href="gerir_categorias.php" class="nav-link active bg-primary"><i class="fas fa-tags fa-fw me-2"></i>Gerir Categorias</a></li></ul><hr></div>';
        }
    ?>

    
        <div id="adminSidebarOverlay" class="fixed inset-0 bg-black opacity-50 z-30 hidden lg:hidden"></div>


        <div class="content-wrapper flex-1 flex flex-col overflow-hidden">
            <header class="admin-header bg-admin-header-bg shadow-sm p-4 border-b border-gray-200">
                <div class="container-fluid mx-auto flex items-center justify-between">
                    <button id="adminMobileSidebarToggle" class="lg:hidden text-gray-600 hover:text-primary-blue p-2 -ml-2">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-dark-text hidden lg:block"><?= htmlspecialchars($pageTitle); ?></h1>
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-medium-text hidden sm:inline">Olá, <?= htmlspecialchars($userName); ?></span>
                        <img src="<?= htmlspecialchars($adminAvatarUrl); ?>" alt="Avatar" class="w-8 h-8 rounded-full">
                        <a href="logout.php" class="text-sm text-primary-blue hover:underline" title="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </header>

            <main class="admin-main-content p-6 flex-1 overflow-y-auto">
                <div class="container-fluid mx-auto">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-dark-text">Gerenciar Notícias</h2>
                        <button type="button" id="openAddModalBtn" class="bg-primary-blue hover:bg-primary-blue-dark text-white font-semibold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-colors flex items-center text-sm">
                            <i class="fas fa-plus mr-2"></i> Adicionar Nova
                        </button>
                    </div>

                    <?php if ($feedback_message): ?>
                    <div class="p-4 mb-4 rounded-md <?= $feedback_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                        <?= htmlspecialchars($feedback_message); ?>
                        <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-current p-1.5 rounded-lg focus:ring-2 focus:ring-offset-1 <?= $feedback_type === 'success' ? 'hover:bg-green-200 focus:ring-green-400' : 'hover:bg-red-200 focus:ring-red-400'; ?>" onclick="this.parentElement.style.display='none'" aria-label="Close">
                            <span class="sr-only">Fechar</span>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="bg-card-bg shadow-lg rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-dark-text">Lista de Notícias</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if (empty($lista_noticias) && empty($feedback_message)): ?>
                                <p class="text-center text-medium-text p-6">Nenhuma notícia encontrada.</p>
                            <?php elseif (!empty($lista_noticias)): ?>
                            <table class="table-tw min-w-full">
                                <thead>
                                    <tr>
                                        <th class="w-12 px-4 py-3">ID</th>
                                        <th class="px-4 py-3">Título</th>
                                        <th class="px-4 py-3">Categoria</th>
                                        <th class="px-4 py-3">Data Publicação</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Visibilidade</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($lista_noticias as $noticia): ?>
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500"><?= htmlspecialchars($noticia['id_noticia']); ?></td>
                                        <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($noticia['titulo']); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500"><?= htmlspecialchars($noticia['categoria_noticia'] ?? 'N/A'); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500"><?= htmlspecialchars(date("d/m/Y H:i", strtotime($noticia['data_publicacao']))); ?></td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $noticia['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                <?= $noticia['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars(ucfirst($noticia['visibilidade'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button type="button" class="text-yellow-600 hover:text-yellow-800 edit-btn"
                                                    data-id="<?= $noticia['id_noticia']; ?>"
                                                    data-titulo="<?= htmlspecialchars($noticia['titulo']); ?>"
                                                    data-slug="<?= htmlspecialchars($noticia['slug_noticia'] ?? ''); ?>"
                                                    data-excerto="<?= htmlspecialchars($noticia['excerto'] ?? ''); ?>"
                                                    data-conteudo="<?= htmlspecialchars($noticia['conteudo_completo_html'] ?? ''); ?>"
                                                    data-imagem="<?= htmlspecialchars($noticia['url_imagem_destaque'] ?? ''); ?>"
                                                    data-categoria="<?= htmlspecialchars($noticia['categoria_noticia'] ?? ''); ?>"
                                                    data-autor="<?= htmlspecialchars($noticia['autor_noticia'] ?? ''); ?>"
                                                    data-publicacao="<?= htmlspecialchars(date("Y-m-d\TH:i", strtotime($noticia['data_publicacao']))); ?>"
                                                    data-tags="<?= htmlspecialchars($noticia['tags'] ?? ''); ?>"
                                                    data-ativo="<?= $noticia['ativo']; ?>"
                                                    data-visibilidade="<?= htmlspecialchars($noticia['visibilidade']); ?>">
                                                <i class="fas fa-edit"></i> <span class="hidden sm:inline">Editar</span>
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-800 delete-btn"
                                                    data-id="<?= $noticia['id_noticia']; ?>"
                                                    data-titulo="<?= htmlspecialchars($noticia['titulo']); ?>">
                                                <i class="fas fa-trash"></i> <span class="hidden sm:inline">Excluir</span>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div id="noticiaModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop">
        <div class="bg-card-bg rounded-lg shadow-xl w-full max-w-2xl modal-content-tw transform transition-all animate-modal-pop">
            <form id="noticiaForm" action="gerir_noticias.php" method="POST">
                <input type="hidden" name="action" id="form_action">
                <input type="hidden" name="id_noticia" id="form_id_noticia">
                <div class="flex justify-between items-center p-5 border-b border-gray-200">
                    <h5 class="text-xl font-semibold text-dark-text" id="modalTitle">Adicionar Nova Notícia</h5>
                    <button type="button" class="text-gray-400 hover:text-gray-600 closeModalBtn">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4 overflow-y-auto max-h-[calc(90vh-140px)]">
                    <?php 
                        // Define variáveis para o formulário, usando $noticia_para_editar se estiver em modo de edição
                        // ou os dados do $_POST se houver um erro de validação, ou valores padrão.
                        $titulo_form = htmlspecialchars($noticia_para_editar['titulo'] ?? '');
                        $slug_form = htmlspecialchars($noticia_para_editar['slug_noticia'] ?? '');
                        $excerto_form = htmlspecialchars($noticia_para_editar['excerto'] ?? '');
                        $conteudo_form = htmlspecialchars($noticia_para_editar['conteudo_completo_html'] ?? '');
                        $imagem_form = htmlspecialchars($noticia_para_editar['url_imagem_destaque'] ?? '');
                        $categoria_form = htmlspecialchars($noticia_para_editar['categoria_noticia'] ?? '');
                        $autor_form = htmlspecialchars($noticia_para_editar['autor_noticia'] ?? $userName);
                        $publicacao_form = htmlspecialchars(isset($noticia_para_editar['data_publicacao']) ? date("Y-m-d\TH:i", strtotime($noticia_para_editar['data_publicacao'])) : date("Y-m-d\TH:i"));
                        $tags_form = htmlspecialchars($noticia_para_editar['tags'] ?? '');
                        $ativo_form = isset($noticia_para_editar['ativo']) ? (bool)$noticia_para_editar['ativo'] : true;
                        $visibilidade_form = $noticia_para_editar['visibilidade'] ?? 'publico';
                    ?>
                    <div>
                        <label for="form_titulo" class="block text-sm font-medium text-medium-text">Título da Notícia <span class="text-red-500">*</span></label>
                        <input type="text" id="form_titulo" name="titulo" value="<?= $titulo_form; ?>" required class="form-input-tw mt-1">
                    </div>
                    <div>
                        <label for="form_slug_noticia" class="block text-sm font-medium text-medium-text">Slug (URL amigável)</label>
                        <input type="text" id="form_slug_noticia" name="slug_noticia" value="<?= $slug_form; ?>" placeholder="Deixe em branco para gerar automaticamente" class="form-input-tw mt-1">
                        <p class="mt-1 text-xs text-light-text">Ex: minha-nova-noticia. Usar apenas letras minúsculas, números e hífens.</p>
                    </div>
                    <div>
                        <label for="form_excerto" class="block text-sm font-medium text-medium-text">Excerto / Resumo Curto</label>
                        <textarea id="form_excerto" name="excerto" rows="2" class="form-input-tw mt-1"><?= $excerto_form; ?></textarea>
                    </div>
                    <div>
                        <label for="form_conteudo_completo_html" class="block text-sm font-medium text-medium-text">Conteúdo Completo (HTML permitido) <span class="text-red-500">*</span></label>
                        <textarea id="form_conteudo_completo_html" name="conteudo_completo_html" rows="8" required class="form-input-tw mt-1"><?= $conteudo_form; ?></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="form_url_imagem_destaque" class="block text-sm font-medium text-medium-text">URL da Imagem de Destaque</label>
                            <input type="url" id="form_url_imagem_destaque" name="url_imagem_destaque" value="<?= $imagem_form; ?>" placeholder="https://exemplo.com/imagem.jpg" class="form-input-tw mt-1">
                        </div>
                        <div>
                            <label for="form_categoria_noticia" class="block text-sm font-medium text-medium-text">Categoria</label>
                            <input type="text" id="form_categoria_noticia" name="categoria_noticia" value="<?= $categoria_form; ?>" placeholder="Ex: Lançamentos, Eventos" class="form-input-tw mt-1">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="form_autor_noticia" class="block text-sm font-medium text-medium-text">Autor</label>
                            <input type="text" id="form_autor_noticia" name="autor_noticia" value="<?= $autor_form; ?>" class="form-input-tw mt-1">
                        </div>
                        <div>
                            <label for="form_data_publicacao" class="block text-sm font-medium text-medium-text">Data de Publicação <span class="text-red-500">*</span></label>
                            <input type="datetime-local" id="form_data_publicacao" name="data_publicacao" value="<?= $publicacao_form; ?>" required class="form-input-tw mt-1">
                        </div>
                    </div>
                    <div>
                        <label for="form_tags" class="block text-sm font-medium text-medium-text">Tags (separadas por vírgula)</label>
                        <input type="text" id="form_tags" name="tags" value="<?= $tags_form; ?>" placeholder="Ex: audio, terapia, novidade" class="form-input-tw mt-1">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                        <div>
                            <label for="form_visibilidade" class="block text-sm font-medium text-medium-text">Visibilidade</label>
                            <select id="form_visibilidade" name="visibilidade" class="form-select-tw mt-1">
                                <option value="publico" <?= ($visibilidade_form === 'publico') ? 'selected' : ''; ?>>Público</option>
                                <option value="restrito_assinantes" <?= ($visibilidade_form === 'restrito_assinantes') ? 'selected' : ''; ?>>Restrito a Assinantes</option>
                                <option value="rascunho" <?= ($visibilidade_form === 'rascunho') ? 'selected' : ''; ?>>Rascunho</option>
                            </select>
                        </div>
                        <div class="mt-4 md:mt-6">
                            <label class="flex items-center">
                                <input type="checkbox" id="form_ativo" name="ativo" value="1" class="form-checkbox-tw h-4 w-4 text-primary-blue border-gray-300 rounded focus:ring-primary-blue" <?= $ativo_form ? 'checked' : ''; ?>>
                                <span class="ml-2 text-sm text-medium-text">Notícia Ativa (visível no site)</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end items-center p-5 border-t border-gray-200 space-x-3">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md closeModalBtn">Cancelar</button>
                    <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-primary-blue hover:bg-primary-blue-dark rounded-md shadow-sm hover:shadow-md transition-all">Salvar Notícia</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop">
        <div class="bg-card-bg rounded-lg shadow-xl w-full max-w-md transform transition-all animate-modal-pop">
            <form id="deleteForm" action="gerir_noticias.php" method="POST">
                <input type="hidden" name="action" value="delete_noticia">
                <input type="hidden" name="id_noticia_delete" id="delete_id_noticia_input_tw">
                <div class="p-6">
                    <div class="flex items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-dark-text" id="deleteModalTitle">Confirmar Exclusão</h3>
                            <div class="mt-2">
                                <p class="text-sm text-medium-text">Tem certeza que deseja excluir a notícia "<strong id="delete_noticia_titulo_tw"></strong>"?</p>
                                <p class="text-sm text-danger mt-1">Esta ação não poderá ser desfeita.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse rounded-b-lg">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-danger text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Excluir
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-blue sm:mt-0 sm:w-auto sm:text-sm closeModalBtn">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const adminSidebar = document.getElementById('adminSidebar');
        const adminSidebarOverlay = document.getElementById('adminSidebarOverlay');
        const adminMobileSidebarToggle = document.getElementById('adminMobileSidebarToggle'); // Botão no header de admin
        const mainContentWrapper = document.querySelector('.content-wrapper'); // Para o overlay

        function toggleAdminMobileSidebar() {
            if (adminSidebar && adminSidebarOverlay && mainContentWrapper) {
                adminSidebar.classList.toggle('open'); // Assume que 'open' faz left: 0
                adminSidebar.classList.toggle('-translate-x-full'); // Para Tailwind
                adminSidebar.classList.toggle('translate-x-0');
                adminSidebarOverlay.classList.toggle('hidden');
                // mainContentWrapper.classList.toggle('sidebar-active-overlay'); // Se quiser um overlay no conteúdo
            }
        }
        if (adminMobileSidebarToggle) adminMobileSidebarToggle.addEventListener('click', toggleAdminMobileSidebar);
        if (adminSidebarOverlay) adminSidebarOverlay.addEventListener('click', toggleAdminMobileSidebar);


        // Modais Tailwind
        const noticiaModalEl = document.getElementById('noticiaModal');
        const deleteModalEl = document.getElementById('deleteModal');
        const openAddModalBtn = document.getElementById('openAddModalBtn');
        const noticiaForm = document.getElementById('noticiaForm');
        const modalTitleEl = document.getElementById('modalTitle');

        function openModal(modalElement) {
            if (modalElement) {
                modalElement.classList.remove('hidden');
                modalElement.classList.add('flex'); // Para centralizar com items-center justify-center
            }
        }
        function closeModal(modalElement) {
            if (modalElement) {
                modalElement.classList.add('hidden');
                modalElement.classList.remove('flex');
            }
        }

        document.querySelectorAll('.closeModalBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                closeModal(this.closest('.fixed.inset-0'));
            });
        });
        
        if (openAddModalBtn) {
            openAddModalBtn.addEventListener('click', function() {
                noticiaForm.reset();
                noticiaForm.querySelector('#form_action').value = 'add_noticia';
                noticiaForm.querySelector('#form_id_noticia').value = '';
                modalTitleEl.textContent = 'Adicionar Nova Notícia';
                noticiaForm.querySelector('#form_autor_noticia').value = '<?= htmlspecialchars($userName); ?>';
                noticiaForm.querySelector('#form_data_publicacao').value = new Date().toISOString().slice(0, 16);
                noticiaForm.querySelector('#form_ativo').checked = true;
                noticiaForm.querySelector('#form_visibilidade').value = 'publico';
                openModal(noticiaModalEl);
            });
        }

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                noticiaForm.reset();
                noticiaForm.querySelector('#form_action').value = 'edit_noticia';
                noticiaForm.querySelector('#form_id_noticia').value = this.dataset.id;
                modalTitleEl.textContent = 'Editar Notícia';

                noticiaForm.querySelector('#form_titulo').value = this.dataset.titulo;
                noticiaForm.querySelector('#form_slug_noticia').value = this.dataset.slug;
                noticiaForm.querySelector('#form_excerto').value = this.dataset.excerto;
                noticiaForm.querySelector('#form_conteudo_completo_html').value = this.dataset.conteudo;
                noticiaForm.querySelector('#form_url_imagem_destaque').value = this.dataset.imagem;
                noticiaForm.querySelector('#form_categoria_noticia').value = this.dataset.categoria;
                noticiaForm.querySelector('#form_autor_noticia').value = this.dataset.autor;
                noticiaForm.querySelector('#form_data_publicacao').value = this.dataset.publicacao;
                noticiaForm.querySelector('#form_tags').value = this.dataset.tags;
                noticiaForm.querySelector('#form_visibilidade').value = this.dataset.visibilidade;
                noticiaForm.querySelector('#form_ativo').checked = (this.dataset.ativo == '1');
                openModal(noticiaModalEl);
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('delete_id_noticia_input_tw').value = this.dataset.id;
                document.getElementById('delete_noticia_titulo_tw').textContent = this.dataset.titulo;
                openModal(deleteModalEl);
            });
        });
        
        const formTituloInput = document.getElementById('form_titulo');
        const formSlugInput = document.getElementById('form_slug_noticia');
        if (formTituloInput && formSlugInput) {
            formTituloInput.addEventListener('blur', function() {
                if (formSlugInput.value === '' || formSlugInput.dataset.autoGenerated === 'true') {
                    let slug = this.value.toLowerCase()
                        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") 
                        .replace(/[^\w\s-]/g, '')
                        .replace(/[\s_-]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                    formSlugInput.value = slug;
                    formSlugInput.dataset.autoGenerated = 'true';
                }
            });
            formSlugInput.addEventListener('input', function() {
                formSlugInput.dataset.autoGenerated = 'false';
            });
        }

        <?php if (!empty($feedback_message) && $feedback_type === 'error'): ?>
            <?php if (isset($_POST['action']) && ($_POST['action'] === 'add_noticia' || ($_POST['action'] === 'edit_noticia' && isset($_POST['id_noticia'])))): ?>
                modalTitleEl.textContent = '<?= $_POST['action'] === 'add_noticia' ? 'Adicionar Nova Notícia (Corrigir Erros)' : 'Editar Notícia (Corrigir Erros)' ?>';
                noticiaForm.querySelector('#form_action').value = '<?= $_POST['action'] ?>';
                if ('<?= $_POST['action'] ?>' === 'edit_noticia') {
                    noticiaForm.querySelector('#form_id_noticia').value = '<?= htmlspecialchars($_POST['id_noticia'] ?? '') ?>';
                }
                // Os valores dos campos já são preenchidos pelo PHP no HTML em caso de erro
                openModal(noticiaModalEl);
            <?php endif; ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
