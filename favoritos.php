<?php
// favoritos.php

// 1. Incluir o gestor de sessões e verificar login
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Redireciona para login se não estiver logado

// 2. Incluir a conexão com o banco de dados
require_once __DIR__ . '/db/db_connect.php';

$pageTitle = "Meus Favoritos - AudioTO";
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com'; // Needed for header if displayed
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

// Consistent avatar function from your layout
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    // Using primary-blue from the target layout's Tailwind config
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}

$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40); // For header

// Breadcrumbs for favorites page
$breadcrumbs = [['nome' => 'Meus Favoritos', 'link' => '#']]; // '#' because it's the current page

// 3. Lógica para buscar favoritos do usuário
$all_favorites_data = [];
$erro_pagina = null; // Use $erro_pagina for consistency with the layout

if ($userId) {
    try {
        $stmt_fav = $pdo->prepare(
            "SELECT f.id_favorito, f.id_conteudo, f.tipo_conteudo, f.data_favoritado
             FROM favoritos f
             WHERE f.id_utilizador = :userId
             ORDER BY f.data_favoritado DESC"
        );
        $stmt_fav->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_fav->execute();
        $favoritos = $stmt_fav->fetchAll(PDO::FETCH_ASSOC);

        foreach ($favoritos as $fav) {
            $item_data = [
                'id' => 'fav-' . $fav['tipo_conteudo'] . '-' . $fav['id_conteudo'],
                'originalId' => $fav['id_conteudo'],
                'dbFavoriteId' => $fav['id_favorito'],
                'type' => $fav['tipo_conteudo'],
                'title' => 'Título não encontrado',
                'description' => 'Descrição não disponível.',
                'actionText' => 'Ver Detalhes',
                'actionUrl' => '#',
                'iconColorClass' => 'text-primary-blue', // Default icon color class
                'iconBgClass' => 'bg-primary-blue-light', // Default icon background class
                'iconSvg' => '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>'
            ];

            if ($fav['tipo_conteudo'] == 'podcast') {
                $stmt_item = $pdo->prepare(
                    "SELECT p.titulo_podcast, p.slug_podcast, p.descricao_podcast, p.imagem_capa_url, c.nome_categoria, ap.nome_assunto
                     FROM podcasts p
                     LEFT JOIN assuntos_podcast ap ON p.id_assunto = ap.id_assunto
                     LEFT JOIN categorias_podcast c ON ap.id_categoria = c.id_categoria
                     WHERE p.id_podcast = :id_conteudo"
                );
                $stmt_item->bindParam(':id_conteudo', $fav['id_conteudo'], PDO::PARAM_INT);
                $stmt_item->execute();
                $podcast_details = $stmt_item->fetch(PDO::FETCH_ASSOC);

                if ($podcast_details) {
                    $item_data['title'] = htmlspecialchars($podcast_details['titulo_podcast']);
                    $desc_parts = [];
                    if (!empty($podcast_details['nome_categoria'])) $desc_parts[] = "Categoria: " . htmlspecialchars($podcast_details['nome_categoria']);
                    if (!empty($podcast_details['nome_assunto'])) $desc_parts[] = "Assunto: " . htmlspecialchars($podcast_details['nome_assunto']);
                    $item_data['description'] = !empty($desc_parts) ? implode(' / ', $desc_parts) : (htmlspecialchars(mb_strimwidth($podcast_details['descricao_podcast'] ?? '', 0, 100, "...")));
                    $item_data['actionText'] = 'Ouvir Podcast';
                    $item_data['actionUrl'] = 'player_podcast.php?slug=' . urlencode($podcast_details['slug_podcast']);
                    // Podcast specific icon styling (example)
                    $item_data['iconColorClass'] = 'text-indigo-600'; // Example color
                    $item_data['iconBgClass'] = 'bg-indigo-100';
                    $item_data['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z" /></svg>';
                }
            } elseif ($fav['tipo_conteudo'] == 'oportunidade') {
                $stmt_item = $pdo->prepare(
                    "SELECT titulo_oportunidade, descricao_oportunidade, link_oportunidade, tipo_oportunidade, fonte_oportunidade
                     FROM oportunidades
                     WHERE id_oportunidade = :id_conteudo"
                );
                $stmt_item->bindParam(':id_conteudo', $fav['id_conteudo'], PDO::PARAM_INT);
                $stmt_item->execute();
                $oportunidade_details = $stmt_item->fetch(PDO::FETCH_ASSOC);

                if ($oportunidade_details) {
                    $item_data['title'] = htmlspecialchars($oportunidade_details['titulo_oportunidade']);
                    $item_data['description'] = "Tipo: " . htmlspecialchars(ucfirst($oportunidade_details['tipo_oportunidade'])) .
                                               (!empty($oportunidade_details['fonte_oportunidade']) ? " / Fonte: " . htmlspecialchars($oportunidade_details['fonte_oportunidade']) : '');
                    $item_data['actionText'] = 'Ver Detalhes';
                    $item_data['actionUrl'] = htmlspecialchars($oportunidade_details['link_oportunidade'] ?? '#');
                    
                    switch ($oportunidade_details['tipo_oportunidade']) {
                        case 'curso':
                            $item_data['iconColorClass'] = 'text-green-600';
                            $item_data['iconBgClass'] = 'bg-green-100';
                            $item_data['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>';
                            break;
                        case 'artigo':
                            $item_data['iconColorClass'] = 'text-yellow-600';
                            $item_data['iconBgClass'] = 'bg-yellow-100';
                            $item_data['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>';
                            break;
                        case 'vaga':
                             $item_data['iconColorClass'] = 'text-sky-600';
                             $item_data['iconBgClass'] = 'bg-sky-100';
                             $item_data['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.247 2.247H5.997A2.25 2.25 0 013.75 18.223V14.15M16.5 14.85V18a2.25 2.25 0 002.25 2.25h1.5A2.25 2.25 0 0022.5 18v-3.15m-9.75-7.875c1.036 0 1.875.84 1.875 1.875 0 .359-.105.688-.285.975H18a2.25 2.25 0 012.25 2.25v.562c0 .3-.038.584-.108.853l-1.22 4.882A2.25 2.25 0 0116.743 21H7.257a2.25 2.25 0 01-2.18-2.083l-1.22-4.882A3.064 3.064 0 013.75 13.312V12.75A2.25 2.25 0 016 10.5h2.465c.18-.287.285-.616.285-.975 0-1.036.84-1.875 1.875-1.875z" /></svg>';
                            break;
                        default: // 'evento', 'outro'
                            $item_data['iconColorClass'] = 'text-gray-600';
                            $item_data['iconBgClass'] = 'bg-gray-100';
                            $item_data['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.355a7.5 7.5 0 01-4.5 0m4.5 0v.75A2.25 2.25 0 0113.5 21h-3a2.25 2.25 0 01-2.25-2.25V18m7.5-7.5h-4.5m0 0a9 9 0 100 13.5h1.5a12.025 12.025 0 011.412-3.37A48.56 48.56 0 0112 10.5zm-3.75 0a9 9 0 110-13.5H9a12.025 12.025 0 00-1.412 3.37A48.56 48.56 0 0012 10.5z" /></svg>';
                    }
                }
            }
            $all_favorites_data[] = $item_data;
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar favoritos: " . $e->getMessage());
        $erro_pagina = "Não foi possível carregar seus favoritos. Por favor, tente novamente mais tarde.";
    }
} elseif(!$userId) {
    $erro_pagina = "Faça login para ver seus favoritos.";
}

$feedback_message = $_SESSION['feedback_message'] ?? null;
$feedback_type = $_SESSION['feedback_type'] ?? null;
unset($_SESSION['feedback_message']);
unset($_SESSION['feedback_type']);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { // Using colors from podcasts.php layout
                        'primary-blue': '#2563eb',
                        'primary-blue-light': '#dbeafe', // Adjusted from #e0eaff
                        'primary-blue-dark': '#1e40af',
                        'light-bg': '#f7fafc',
                        'card-bg': '#ffffff',
                        'dark-text': '#1f2937',
                        'medium-text': '#4b5563',
                        'light-text': '#6b7280',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b',
                        'info': '#3b82f6',
                        // Additional colors from favoritos.html for icon backgrounds (can be mapped from above)
                        'secondary': '#4F46E5', // Example, can map to a primary-blue variant
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: theme('colors.light-bg'); color: theme('colors.dark-text'); }
        .sidebar { transition: left 0.3s ease-in-out; }
        .sidebar.open { left: 0; }
        .sidebar-icon { width: 20px; height: 20px; }
        .active-nav-link { background-color: theme('colors.primary-blue-light'); color: theme('colors.primary-blue'); border-right: 3px solid theme('colors.primary-blue'); }
        .active-nav-link i { color: theme('colors.primary-blue'); }
        
        .content-item-animated { opacity: 0; animation-fill-mode: forwards; } /* From podcasts.php */
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400'); }

        /* Styles for filter tabs from favoritos.html */
        .filter-tab.active {
            border-bottom-color: theme('colors.primary-blue'); /* Adjusted to primary-blue */
            color: theme('colors.primary-blue');
            font-weight: 600;
        }
    </style>
</head>
<body class="text-gray-800">

<div class="flex h-screen overflow-hidden">
   <aside id="sidebar" class="sidebar fixed lg:static inset-y-0 left-[-256px] lg:left-0 z-50 w-64 bg-card-bg p-5 space-y-5 border-r border-gray-200 overflow-y-auto">
            <div class="text-2xl font-bold text-primary-blue mb-6">AudioTO</div>
            <nav class="space-y-1.5" id="mainNav">
                <a href="inicio.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue active-nav-link">
                    <i class="fas fa-home sidebar-icon"></i>
                    <span class="text-sm font-medium">Início</span>
                </a>
                <a href="podcasts.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-podcast sidebar-icon"></i>
                    <span class="text-sm font-medium">Podcasts</span>
                </a>
                <a href="oportunidades.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-lightbulb sidebar-icon"></i>
                    <span class="text-sm font-medium">Oportunidades</span>
                </a>
                <a href="favoritos.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-heart sidebar-icon"></i>
                    <span class="text-sm font-medium">Meus Favoritos</span>
                </a>
                <a href="historico.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-history sidebar-icon"></i>
                    <span class="text-sm font-medium">Histórico</span>
                </a>
                <a href="planos.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-crown sidebar-icon"></i>
                    <span class="text-sm font-medium">Planos</span>
                </a>
                <a href="comunidade.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-users sidebar-icon"></i>
                    <span class="text-sm font-medium">Comunidade</span>
                </a>
            </nav>
            <div class="pt-5 border-t border-gray-200 space-y-1.5">
                <a href="perfil.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-user-circle sidebar-icon"></i>
                    <span class="text-sm font-medium">Meu Perfil</span>
                </a>
                <a href="configuracoes.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-cog sidebar-icon"></i>
                    <span class="text-sm font-medium">Configurações</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-sign-out-alt sidebar-icon"></i>
                    <span class="text-sm font-medium">Sair</span>
                </a>
            </div>
        </aside>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black opacity-50 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-card-bg p-4 shadow-sm flex justify-between items-center border-b border-gray-200">
            <div class="flex items-center">
                <button id="mobileMenuButton" class="lg:hidden text-gray-600 hover:text-primary-blue mr-3 p-2" onclick="toggleMobileSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="relative w-full max-w-xs hidden sm:block">
                    <input type="text" placeholder="Buscar em Favoritos..." id="searchInputFav" class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
                    <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-500 hover:text-primary-blue relative p-2">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-primary-blue ring-1 ring-white"></span>
                </button>
                <div class="relative">
                    <button id="userDropdownButton" class="flex items-center space-x-2 focus:outline-none">
                        <img src="<?php echo $avatarUrl; ?>" alt="Avatar de <?php echo htmlspecialchars($userName); ?>" class="w-9 h-9 rounded-full border-2 border-primary-blue-light">
                        <div class="hidden md:block">
                            <p class="text-xs font-medium text-dark-text"><?php echo htmlspecialchars($userName); ?></p>
                            <p class="text-xs text-light-text"><?php echo htmlspecialchars($userEmail); ?></p>
                        </div>
                        <i class="fas fa-chevron-down text-xs text-gray-500 hidden md:block"></i>
                    </button>
                    <div id="userDropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl z-20 py-1 border border-gray-200">
                        <a href="perfil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Meu Perfil</a>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Sair</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-light-bg p-5 md:p-7 space-y-6">
            <nav class="mb-4 text-xs font-medium text-light-text" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-1.5">
                     <li class="inline-flex items-center">
                        <a href="inicio.php" class="hover:text-primary-blue transition-colors">Início</a>
                        <i class="fas fa-chevron-right w-2.5 h-2.5 text-gray-400 mx-1"></i>
                    </li>
                    <li>
                        <span class="text-dark-text font-semibold">Meus Favoritos</span>
                    </li>
                </ol>
            </nav>

            <?php if ($feedback_message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg <?php echo $feedback_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($erro_pagina): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg text-center" role="alert">
                     <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($erro_pagina); ?>
                </div>
            <?php else: ?>
                <div class="bg-card-bg p-5 rounded-xl shadow-lg">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                        <h1 class="text-2xl sm:text-3xl font-bold text-dark-text mb-3 sm:mb-0">Meus Favoritos</h1>
                        <div id="filterTabs" class="border-b sm:border-b-0 border-gray-200 w-full sm:w-auto">
                            <nav class="-mb-px flex space-x-4 sm:space-x-6" aria-label="Tabs">
                                <button class="filter-tab py-3 px-1 border-b-2 border-transparent text-medium-text hover:text-primary-blue hover:border-primary-blue/50 transition-colors text-sm md:text-base active" data-filter="todos">
                                    Todos
                                </button>
                                <button class="filter-tab py-3 px-1 border-b-2 border-transparent text-medium-text hover:text-primary-blue hover:border-primary-blue/50 transition-colors text-sm md:text-base" data-filter="podcast">
                                    Podcasts
                                </button>
                                <button class="filter-tab py-3 px-1 border-b-2 border-transparent text-medium-text hover:text-primary-blue hover:border-primary-blue/50 transition-colors text-sm md:text-base" data-filter="oportunidade">
                                    Oportunidades
                                </button>
                            </nav>
                        </div>
                    </div>

                    <section id="favoritesContainer" class="space-y-5">
                        </section>
                    <div id="noFavoritesMessage" class="hidden text-center text-medium-text py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.82.61l-4.725-2.885a.563.563 0 00-.652 0l-4.725 2.885a.562.562 0 01-.82-.61l1.285-5.385a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                        </svg>
                        <p class="text-xl font-semibold mt-2 text-dark-text">Você ainda não tem favoritos.</p>
                        <p class="text-sm text-light-text">Explore podcasts e oportunidades e clique no ícone <i class="fas fa-star text-yellow-400"></i> para adicioná-los aqui!</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script>
    let allFavoritesData = <?php echo json_encode($all_favorites_data); ?>;
    let currentSearchTerm = ''; // For search functionality

    function renderFavorites() {
        const favoritesContainer = document.getElementById('favoritesContainer');
        const noFavoritesMessage = document.getElementById('noFavoritesMessage');
        const filterTabsContainer = document.getElementById('filterTabs');
        if (!favoritesContainer || !noFavoritesMessage || !filterTabsContainer) return;

        favoritesContainer.innerHTML = ''; 
        let viewData = allFavoritesData;
        const currentFilter = document.querySelector('#filterTabs .filter-tab.active')?.dataset.filter || 'todos';

        if (currentFilter !== 'todos') {
            viewData = viewData.filter(fav => fav.type === currentFilter);
        }

        if (currentSearchTerm) {
            viewData = viewData.filter(fav => 
                fav.title.toLowerCase().includes(currentSearchTerm) ||
                fav.description.toLowerCase().includes(currentSearchTerm)
            );
        }
        
        if (viewData.length === 0) {
            if (allFavoritesData.length === 0) { // No favorites at all
                noFavoritesMessage.classList.remove('hidden');
                favoritesContainer.classList.add('hidden');
                filterTabsContainer.classList.add('hidden');
                 document.getElementById('searchInputFav')?.parentElement.classList.add('hidden'); // Hide search if no favs
            } else { // Favorites exist, but filter/search yielded no results
                favoritesContainer.innerHTML = `<p class="text-center text-medium-text py-10 col-span-full">Nenhum favorito encontrado para "${currentSearchTerm ? currentSearchTerm + '" e filtro "' + currentFilter : 'este filtro'}".</p>`;
                noFavoritesMessage.classList.add('hidden');
                favoritesContainer.classList.remove('hidden');
                filterTabsContainer.classList.remove('hidden');
                document.getElementById('searchInputFav')?.parentElement.classList.remove('hidden');
            }
        } else {
            noFavoritesMessage.classList.add('hidden');
            favoritesContainer.classList.remove('hidden');
            filterTabsContainer.classList.remove('hidden');
             document.getElementById('searchInputFav')?.parentElement.classList.remove('hidden');
        }

        viewData.forEach((fav, index) => {
            const typeLabel = fav.type === 'podcast' ? 'Podcast' : 'Oportunidade';
            const card = `
                <div class="bg-card-bg p-5 rounded-xl shadow-md hover:shadow-lg transition-shadow duration-300 flex flex-col sm:flex-row items-start gap-x-5 gap-y-3 content-item-animated" 
                     style="animation-name: fadeInUp; animation-delay: ${index * 0.05}s;"
                     id="favorite-${fav.id}">
                    <div class="p-3 ${fav.iconBgClass} rounded-lg flex-shrink-0">
                        <span class="${fav.iconColorClass}">${fav.iconSvg}</span>
                    </div>
                    <div class="flex-grow">
                        <span class="text-xs font-bold uppercase ${fav.iconColorClass} tracking-wider">${typeLabel}</span>
                        <h3 class="text-lg font-semibold text-dark-text mt-1 mb-1 hover:text-primary-blue transition-colors">
                            <a href="${fav.actionUrl}" ${fav.type === 'podcast' ? '' : 'target="_blank" rel="noopener noreferrer"'}>${fav.title}</a>
                        </h3>
                        <p class="text-sm text-medium-text mb-3 line-clamp-2">${fav.description}</p>
                        <a href="${fav.actionUrl}" ${fav.type === 'podcast' ? '' : 'target="_blank" rel="noopener noreferrer"'} 
                           class="inline-flex items-center text-sm text-primary-blue hover:text-primary-blue-dark font-medium transition-colors group">
                            ${fav.actionText} 
                            <i class="fas fa-arrow-right ml-1.5 text-xs opacity-0 group-hover:opacity-100 transition-opacity transform group-hover:translate-x-1 duration-200"></i>
                        </a>
                    </div>
                    <button class="remove-favorite-btn text-danger hover:text-red-700 p-2 rounded-full hover:bg-red-500/10 transition-colors flex-shrink-0 ml-auto sm:ml-0 mt-2 sm:mt-0 self-start sm:self-center" 
                            data-dbid="${fav.dbFavoriteId}" title="Remover dos Favoritos">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12.56 0c.342.052.682.107 1.022.166m0 0A48.097 48.097 0 0112 5.25c5.186 0 9.794.655 13.228 1.738M5.25 5.25h13.5" />
                        </svg>
                    </button>
                </div>
            `;
            favoritesContainer.insertAdjacentHTML('beforeend', card);
        });

        document.querySelectorAll('.remove-favorite-btn').forEach(button => {
            button.addEventListener('click', function() {
                const favoriteDbIdToRemove = this.dataset.dbid;
                if (confirm('Tem certeza que deseja remover este item dos favoritos?')) {
                    // AJAX call to remover_favorito.php
                    fetch(`remover_favorito.php?id_favorito=${favoriteDbIdToRemove}`, { method: 'GET' }) // Or POST
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            allFavoritesData = allFavoritesData.filter(fav => fav.dbFavoriteId.toString() !== favoriteDbIdToRemove.toString());
                            renderFavorites();
                            // Optional: Show success toast/message
                        } else {
                            alert('Erro ao remover favorito: ' + (data.message || 'Tente novamente.'));
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição:', error);
                        alert('Ocorreu um erro na comunicação com o servidor.');
                    });
                }
            });
        });
         // Intersection Observer for animations
        const animatedElements = document.querySelectorAll('.content-item-animated');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        animatedElements.forEach(el => { observer.observe(el); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userDropdownButton && userDropdownMenu) {
            userDropdownButton.addEventListener('click', function (event) {
                event.stopPropagation();
                userDropdownMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function (event) {
                if (!userDropdownMenu.classList.contains('hidden') && 
                    !userDropdownButton.contains(event.target) && 
                    !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.add('hidden');
                }
            });
        }

        const filterTabsContainer = document.getElementById('filterTabs');
        if (filterTabsContainer) {
            filterTabsContainer.addEventListener('click', function(event) {
                const targetButton = event.target.closest('.filter-tab');
                if (targetButton) {
                    document.querySelectorAll('#filterTabs .filter-tab').forEach(tab => tab.classList.remove('active'));
                    targetButton.classList.add('active');
                    renderFavorites();
                }
            });
        }
        
        const searchInputFav = document.getElementById('searchInputFav');
        if (searchInputFav) {
            searchInputFav.addEventListener('input', function(e) {
                currentSearchTerm = e.target.value.toLowerCase();
                renderFavorites();
            });
        }

        renderFavorites(); // Initial render
    });

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('open');
        sidebar.classList.toggle('left-[-256px]');
        sidebar.classList.toggle('left-0');
        overlay.classList.toggle('hidden');
    }
</script>
</body>
</html>