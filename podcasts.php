<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/db/db_connect.php';

// Dados do usuário da sessão
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;
$userPlanoId = (int) ($_SESSION['user_plano_id'] ?? 0); // Plano atual do usuário
$userIsAdmin = ($_SESSION['user_funcao'] ?? '') === 'administrador'; // Se o usuário é admin

/**
 * Gera a URL do avatar do usuário ou um placeholder.
 */
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}

$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);

// Parâmetros GET para navegação
$slug_categoria_selecionada = trim(filter_input(INPUT_GET, 'categoria', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$slug_assunto_selecionado = trim(filter_input(INPUT_GET, 'assunto', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

// Variáveis de controle da página
$pageTitle = "Nossos Podcasts";
$breadcrumbs = [['nome' => 'Podcasts', 'link' => 'podcasts.php']];
$categorias_para_exibir = [];
$assuntos_da_categoria = [];
$podcasts_do_assunto = [];
$categoria_atual = null;
$assunto_atual = null;
$erro_pagina = null;

/**
 * Determina o acesso do usuário a um podcast específico.
 *
 * @param array $podcast Dados do podcast (deve incluir 'visibilidade', 'id_plano_minimo', 'url_audio').
 * @param int $currentUserPlanoId ID do plano do usuário atual.
 * @param bool $isCurrentUserAdmin Se o usuário atual é administrador.
 * @return array Contendo 'canPlay' (bool) e 'showPadlock' (bool).
 */
function determinarAcessoPodcast(array $podcast, int $currentUserPlanoId, bool $isCurrentUserAdmin): array {
    $canPlay = false;
    $showPadlock = false;
    $visibilidade = $podcast['visibilidade'] ?? 'privado'; // Default para privado se não definido

    if ($visibilidade === 'publico') {
        $canPlay = true;
    } elseif ($visibilidade === 'restrito_assinantes') {
        if ($isCurrentUserAdmin || $currentUserPlanoId == 2 || $currentUserPlanoId == 3) {
            // Admin ou Planos Premium (2 ou 3) podem acessar conteúdo restrito.
            // Para uma lógica mais granular no futuro, onde 'id_plano_minimo' do podcast
            // pudesse restringir acesso mesmo entre planos premium (ex: só plano 3 acessa alguns):
            // $minPlanRequired = (int)($podcast['id_plano_minimo'] ?? 2); // Default para o menor plano premium
            // if ($minPlanRequired < 2) $minPlanRequired = 2; // Garante que seja no mínimo o plano premium base
            // if ($isCurrentUserAdmin || ($currentUserPlanoId >= $minPlanRequired && $currentUserPlanoId > 1) ) {
            //     $canPlay = true;
            // } else {
            //     $showPadlock = true; // Usuário não tem o plano mínimo requerido por este podcast específico
            // }
            $canPlay = true;
        } else {
            // Usuário não é admin e não tem plano premium (ex: gratuito)
            $showPadlock = true;
        }
    } else {
        // Outras visibilidades (ex: 'rascunho', 'privado')
        // Apenas admins podem ter acesso especial se houver áudio
        if ($isCurrentUserAdmin && !empty($podcast['url_audio'])) {
            $canPlay = true; // Será renderizado como botão "Ouvir (Admin)"
        }
        // Para outros usuários, $canPlay permanece false e $showPadlock permanece false (será "Indisponível")
    }

    return ['canPlay' => $canPlay, 'showPadlock' => $showPadlock];
}


// BUSCA DE DADOS DO BANCO
try {
    if (!empty($slug_categoria_selecionada)) {
        $stmt_cat_atual = $pdo->prepare("SELECT id_categoria, nome_categoria, slug_categoria, icone_categoria, cor_icone, descricao_categoria FROM categorias_podcast WHERE slug_categoria = :slug");
        $stmt_cat_atual->execute([':slug' => $slug_categoria_selecionada]);
        $categoria_atual = $stmt_cat_atual->fetch(PDO::FETCH_ASSOC);

        if (!$categoria_atual) {
            $_SESSION['feedback_message'] = "Ops! A categoria que você procurou não foi encontrada.";
            $_SESSION['feedback_type'] = "error";
            header('Location: podcasts.php'); exit;
        }
        $pageTitle = htmlspecialchars($categoria_atual['nome_categoria']) . " - AudioTO";
        $breadcrumbs[] = ['nome' => htmlspecialchars($categoria_atual['nome_categoria']), 'link' => 'podcasts.php?categoria=' . $slug_categoria_selecionada];

        if (!empty($slug_assunto_selecionado)) {
            $stmt_ass_atual = $pdo->prepare("SELECT id_assunto, nome_assunto, slug_assunto, descricao_assunto FROM assuntos_podcast WHERE slug_assunto = :slug_ass AND id_categoria = :id_cat");
            $stmt_ass_atual->execute([':slug_ass' => $slug_assunto_selecionado, ':id_cat' => $categoria_atual['id_categoria']]);
            $assunto_atual = $stmt_ass_atual->fetch(PDO::FETCH_ASSOC);

            if (!$assunto_atual) {
                $_SESSION['feedback_message'] = "Assunto não encontrado nesta categoria.";
                $_SESSION['feedback_type'] = "error";
                header('Location: podcasts.php?categoria=' . $slug_categoria_selecionada); exit;
            }
            $pageTitle = htmlspecialchars($assunto_atual['nome_assunto']) . " - " . htmlspecialchars($categoria_atual['nome_categoria']);
            $breadcrumbs[] = ['nome' => htmlspecialchars($assunto_atual['nome_assunto']), 'link' => '#'];
            
            $sql_podcasts = "SELECT id_podcast, titulo_podcast, descricao_podcast, link_material_apoio, slug_podcast, url_audio, visibilidade, id_plano_minimo, data_publicacao
                             FROM podcasts 
                             WHERE id_assunto = :id_assunto ";
            // Se não for admin, pode-se optar por listar apenas podcasts com visibilidade 'publico' ou 'restrito_assinantes'.
            // A regra "Usuários gratuitos (plano 1) veem todos os episódios" sugere que todos devem ser listados.
            // if (!$userIsAdmin) {
            //     $sql_podcasts .= "AND visibilidade IN ('publico', 'restrito_assinantes') ";
            // }
            $sql_podcasts .= "ORDER BY data_publicacao DESC";
            
            $stmt_podcasts = $pdo->prepare($sql_podcasts);
            $stmt_podcasts->execute([':id_assunto' => $assunto_atual['id_assunto']]);
            $podcasts_do_assunto = $stmt_podcasts->fetchAll(PDO::FETCH_ASSOC);

        } else {
            // Apenas categoria selecionada, busca assuntos
            $stmt_assuntos = $pdo->prepare(
                "SELECT id_assunto, nome_assunto, slug_assunto, descricao_assunto, icone_assunto, cor_icone_assunto,
                        (SELECT COUNT(*) FROM podcasts WHERE id_assunto = ap.id_assunto AND visibilidade IN ('publico', 'restrito_assinantes')) as num_podcasts 
                 FROM assuntos_podcast ap 
                 WHERE id_categoria = :id_categoria 
                 ORDER BY nome_assunto ASC"
            );
            $stmt_assuntos->execute([':id_categoria' => $categoria_atual['id_categoria']]);
            $assuntos_da_categoria = $stmt_assuntos->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Nenhuma categoria selecionada, busca todas as categorias
        $stmt_todas_categorias = $pdo->query(
            "SELECT id_categoria, nome_categoria, slug_categoria, icone_categoria, cor_icone, descricao_categoria,
                    (SELECT COUNT(*) FROM assuntos_podcast WHERE id_categoria = cp.id_categoria) as num_assuntos 
             FROM categorias_podcast cp 
             ORDER BY nome_categoria ASC"
        );
        $categorias_para_exibir = $stmt_todas_categorias->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erro na página de podcasts: " . $e->getMessage());
    $erro_pagina = "Ocorreu um erro ao carregar os conteúdos. Por favor, tente novamente mais tarde.";
}

// Feedback para o usuário
$feedback_message = $_SESSION['feedback_message'] ?? null;
$feedback_type = $_SESSION['feedback_type'] ?? null;
unset($_SESSION['feedback_message'], $_SESSION['feedback_type']);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - AudioTO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#2563eb', 'primary-blue-light': '#dbeafe', 'primary-blue-dark': '#1e40af',
                        'light-bg': '#f7fafc', 'card-bg': '#ffffff', 'dark-text': '#1f2937', 'medium-text': '#4b5563',
                        'light-text': '#6b7280', 'success': '#10b981', 'danger': '#ef4444', 'warning': '#f59e0b',
                        'info': '#3b82f6', 'brand-banner-start': '#6ee7b7', 'brand-banner-end': '#3b82f6',
                    },
                    fontFamily: { 'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'], },
                    animation: { 'fade-in-up': 'fadeInUp 0.5s ease-out forwards', },
                    keyframes: { fadeInUp: { '0%': { opacity: '0', transform: 'translateY(10px)' }, '100%': { opacity: '1', transform: 'translateY(0)' }, } }
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
        .content-item-animated { opacity: 0; animation-fill-mode: forwards; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .podcast-cover-image { aspect-ratio: 1 / 1; object-fit: cover; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400'); }
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
                    <input type="text" placeholder="Buscar Podcasts..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <li class="inline-flex items-center">
                        <?php if ($index < count($breadcrumbs) - 1): ?>
                            <a href="<?php echo htmlspecialchars($crumb['link']); ?>" class="hover:text-primary-blue transition-colors"><?php echo htmlspecialchars($crumb['nome']); ?></a>
                            <i class="fas fa-chevron-right w-2.5 h-2.5 text-gray-400 mx-1"></i>
                        <?php else: ?>
                            <span class="text-dark-text font-semibold"><?php echo htmlspecialchars($crumb['nome']); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <?php if ($feedback_message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg <?php echo $feedback_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>
            <?php if ($erro_pagina): ?>
                <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <?php echo htmlspecialchars($erro_pagina); ?>
                </div>
            <?php endif; ?>

            <?php if (!$erro_pagina): ?>
                <?php // SEÇÃO: Exibir todas as categorias (página inicial de podcasts) ?>
                <?php if (empty($slug_categoria_selecionada) && empty($slug_assunto_selecionado)): ?>
                    <section class="content-item-animated" style="animation-name: fadeInUp;">
                        <h1 class="text-2xl sm:text-3xl font-bold mb-6 p-6 rounded-xl shadow-lg bg-gradient-to-r from-primary-blue to-blue-400 text-white">Explorar Todas as Categorias</h1>
                        <?php if (!empty($categorias_para_exibir)): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                                <?php foreach ($categorias_para_exibir as $index => $cat): ?>
                                <a href="podcasts.php?categoria=<?php echo htmlspecialchars($cat['slug_categoria']); ?>"
                                   class="bg-card-bg p-5 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 flex flex-col items-center text-center transform hover:-translate-y-1 content-item-animated"
                                   style="animation-name: fadeInUp; animation-delay: <?php echo $index * 0.05; ?>s;">
                                    <div class="w-16 h-16 flex items-center justify-center rounded-full mb-3" style="background-color: <?php echo htmlspecialchars(!empty($cat['cor_icone']) ? $cat['cor_icone'] . '22' : '#dbeafe;'); ?>;">
                                        <?php 
                                        if (!empty($cat['icone_categoria']) && (strpos($cat['icone_categoria'], '<svg') !== false)): 
                                            $svg_mod = preg_replace('/width="[^"]*"/i', 'width="28"', $cat['icone_categoria']);
                                            $svg_mod = preg_replace('/height="[^"]*"/i', 'height="28"', $svg_mod);
                                            echo $svg_mod; // NOSONAR
                                        elseif (!empty($cat['icone_categoria'])): ?>
                                            <i class="<?php echo htmlspecialchars($cat['icone_categoria']); ?> text-2xl" style="color: <?php echo htmlspecialchars(!empty($cat['cor_icone']) ? $cat['cor_icone'] : '#2563eb'); ?>;"></i>
                                        <?php else: ?>
                                            <i class="fas fa-grip-horizontal text-2xl" style="color: <?php echo htmlspecialchars(!empty($cat['cor_icone']) ? $cat['cor_icone'] : '#2563eb'); ?>;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <h2 class="text-md font-semibold text-dark-text mb-1"><?php echo htmlspecialchars($cat['nome_categoria']); ?></h2>
                                    <p class="text-xs text-medium-text line-clamp-2 mb-2"><?php echo htmlspecialchars($cat['descricao_categoria'] ?? ($cat['num_assuntos'] . " assuntos disponíveis")); ?></p>
                                    <span class="text-xs font-medium text-primary-blue mt-auto"><?php echo $cat['num_assuntos']; ?> Assunto<?php echo $cat['num_assuntos'] != 1 ? 's' : '' ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-medium-text py-8">Nenhuma categoria de podcast disponível no momento.</p>
                        <?php endif; ?>
                    </section>

                <?php // SEÇÃO: Exibir assuntos de uma categoria selecionada ?>
                <?php elseif (!empty($slug_categoria_selecionada) && empty($slug_assunto_selecionado) && $categoria_atual): ?>
                    <section class="content-item-animated" style="animation-name: fadeInUp;">
                        <div class="mb-6 p-6 rounded-xl shadow-lg bg-gradient-to-r from-primary-blue to-blue-400 text-white">
                            <h1 class="text-2xl sm:text-3xl font-bold"><?php echo htmlspecialchars($categoria_atual['nome_categoria']); ?></h1>
                            <?php if(!empty($categoria_atual['descricao_categoria'])): ?>
                                <p class="text-sm mt-1 line-clamp-2 text-white/90"><?php echo htmlspecialchars($categoria_atual['descricao_categoria']); ?></p>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-semibold text-dark-text mb-4">Assuntos em <?php echo htmlspecialchars($categoria_atual['nome_categoria']); ?>:</h2>
                        <?php if (!empty($assuntos_da_categoria)): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                                <?php foreach ($assuntos_da_categoria as $index => $ass): ?>
                                <a href="podcasts.php?categoria=<?php echo htmlspecialchars($slug_categoria_selecionada); ?>&assunto=<?php echo htmlspecialchars($ass['slug_assunto']); ?>"
                                   class="bg-card-bg p-4 rounded-lg shadow hover:shadow-md transition-shadow flex flex-col content-item-animated transform hover:-translate-y-0.5"
                                   style="animation-name: fadeInUp; animation-delay: <?php echo $index * 0.05; ?>s;">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <div class="p-2.5 rounded-full" style="background-color: <?php echo htmlspecialchars(!empty($ass['cor_icone_assunto']) ? $ass['cor_icone_assunto'] . '22' : '#dbeafe;'); ?>">
                                            <?php 
                                                $assunto_icon_color = htmlspecialchars(!empty($ass['cor_icone_assunto']) ? $ass['cor_icone_assunto'] : '#2563eb');
                                                if (!empty($ass['icone_assunto']) && strpos($ass['icone_assunto'], '<svg') !== false): echo $ass['icone_assunto']; // NOSONAR
                                                elseif(!empty($ass['icone_assunto'])): ?>
                                                    <i class="<?php echo htmlspecialchars($ass['icone_assunto']); ?> text-lg" style="color: <?php echo $assunto_icon_color; ?>;"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-stream text-lg" style="color: <?php echo $assunto_icon_color; ?>;"></i>
                                                <?php endif; ?>
                                        </div>
                                        <h3 class="text-md font-semibold text-dark-text truncate"><?php echo htmlspecialchars($ass['nome_assunto']); ?></h3>
                                    </div>
                                    <p class="text-xs text-medium-text line-clamp-2 mb-2 flex-grow"><?php echo htmlspecialchars($ass['descricao_assunto'] ?? 'Explore os podcasts deste assunto.'); ?></p>
                                    <span class="text-xs font-medium text-primary-blue mt-auto"><?php echo $ass['num_podcasts']; ?> Podcast<?php echo ($ass['num_podcasts'] != 1) ? 's' : '' ?></span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-medium-text py-8">Nenhum assunto encontrado para esta categoria.</p>
                        <?php endif; ?>
                    </section>

                <?php // SEÇÃO: Exibir podcasts de um assunto selecionado ?>
                <?php elseif (!empty($slug_assunto_selecionado) && $assunto_atual && $categoria_atual): ?>
                    <section class="content-item-animated" style="animation-name: fadeInUp;">
                         <div class="mb-6 p-6 rounded-xl shadow-lg bg-gradient-to-r from-primary-blue to-blue-400 text-white">
                            <h1 class="text-2xl sm:text-3xl font-bold"><?php echo htmlspecialchars($assunto_atual['nome_assunto']); ?></h1>
                            <p class="text-sm text-white/90 mt-1">Categoria: <?php echo htmlspecialchars($categoria_atual['nome_categoria']); ?></p>
                            <?php if(!empty($assunto_atual['descricao_assunto'])): ?>
                                <p class="text-sm text-white/80 mt-2 line-clamp-3"><?php echo htmlspecialchars($assunto_atual['descricao_assunto']); ?></p>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-semibold text-dark-text mb-4">Episódios Disponíveis:</h2>
                        <?php if (!empty($podcasts_do_assunto)): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                                <?php foreach ($podcasts_do_assunto as $index => $pod): 
                                    // Determina o acesso usando a função auxiliar
                                    $acesso = determinarAcessoPodcast($pod, $userPlanoId, $userIsAdmin);
                                    $podeOuvir = $acesso['canPlay'];
                                    $mostrarCadeado = $acesso['showPadlock'];
                                    $isRestritoVisibilidade = $pod['visibilidade'] === 'restrito_assinantes'; // Para o emblema "Premium"
                                    
                                    $imagePath = 'https://placehold.co/300x300/2760f3/FFFFFF?text=' . urlencode(substr($pod['titulo_podcast'],0,1));
                                    $descricao_curta_podcast = mb_strimwidth($pod['descricao_podcast'] ?? 'Ouça este episódio incrível.', 0, 100, "...");
                                ?>
                                <div class="bg-card-bg rounded-lg shadow-lg hover:shadow-xl transition-shadow flex flex-col content-item-animated overflow-hidden"
                                     style="animation-name: fadeInUp; animation-delay: <?php echo $index * 0.05; ?>s;">
                                    <a href="player_podcast.php?slug=<?php echo htmlspecialchars($pod['slug_podcast']); ?>" class="block group">
                                        <div class="relative">
                                            <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                                                 alt="Capa de <?php echo htmlspecialchars($pod['titulo_podcast']); ?>" 
                                                 class="w-full h-40 podcast-cover-image group-hover:scale-105 transition-transform duration-300">
                                            <?php if ($isRestritoVisibilidade): // Mostra o emblema se for da visibilidade 'restrito_assinantes' ?>
                                            <span class="absolute top-2 right-2 bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full shadow">
                                                <i class="fas fa-crown mr-1 text-xs"></i>Premium
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="p-4 flex flex-col flex-grow">
                                            <h3 class="text-sm font-semibold text-dark-text mb-1 line-clamp-2 group-hover:text-primary-blue transition-colors"><?php echo htmlspecialchars($pod['titulo_podcast']); ?></h3>
                                            <p class="text-xs text-medium-text line-clamp-2 mb-2 flex-grow"><?php echo htmlspecialchars($descricao_curta_podcast); ?></p>
                                        </div>
                                    </a>
                                    <div class="p-4 border-t border-gray-200 mt-auto">
                                        <?php if ($mostrarCadeado): ?>
                                            <a href="planos.php" class="w-full flex items-center justify-center text-xs font-semibold py-2 px-3 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 transition-colors">
                                                <i class="fas fa-lock mr-2"></i> Ver Planos
                                            </a>
                                        <?php elseif ($podeOuvir): ?>
                                            <?php // Verifica se é um admin ouvindo conteúdo não padrão (ex: rascunho com áudio)
                                            $isConteudoNaoPadraoParaAdmin = $userIsAdmin && 
                                                                         $pod['visibilidade'] !== 'publico' && 
                                                                         $pod['visibilidade'] !== 'restrito_assinantes' &&
                                                                         !empty($pod['url_audio']);
                                            ?>
                                            <?php if ($isConteudoNaoPadraoParaAdmin): ?>
                                                <a href="player_podcast.php?slug=<?php echo htmlspecialchars($pod['slug_podcast']); ?>" class="w-full flex items-center justify-center text-xs font-semibold py-2 px-3 rounded-md bg-yellow-500 text-white hover:bg-yellow-600 transition-colors" title="Admin: Acesso especial para ouvir este conteúdo.">
                                                    <i class="fas fa-user-shield mr-2"></i> Ouvir (Admin)
                                                </a>
                                            <?php else: ?>
                                                <a href="player_podcast.php?slug=<?php echo htmlspecialchars($pod['slug_podcast']); ?>" class="w-full flex items-center justify-center text-xs font-semibold py-2 px-3 rounded-md bg-primary-blue text-white hover:bg-primary-blue-dark transition-colors">
                                                    <i class="fas fa-play mr-2"></i> Ouvir Agora
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="w-full flex items-center justify-center text-xs font-semibold py-2 px-3 rounded-md bg-gray-100 text-gray-400 cursor-default" title="Este conteúdo não está disponível para audição no momento.">
                                                <i class="fas fa-ban mr-2"></i> Indisponível
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-medium-text py-8">Nenhum podcast encontrado para este assunto.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; // Fim das condicionais de exibição de conteúdo ?>
            <?php endif; // Fim do if (!$erro_pagina) ?>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userDropdownButton && userDropdownMenu) {
            userDropdownButton.addEventListener('click', function (event) {
                event.stopPropagation();
                userDropdownMenu.classList.toggle('hidden');
            });
            document.addEventListener('click', function (event) {
                if (userDropdownMenu && !userDropdownMenu.classList.contains('hidden') && 
                    userDropdownButton && !userDropdownButton.contains(event.target) && 
                    !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.add('hidden');
                }
            });
        }

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
