<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Garante que o utilizador está logado
require_once __DIR__ . '/db/db_connect.php'; // Conexão com o banco de dados

// Variáveis específicas da página
$pageTitle = "Notícias Semanais - AudioTO";
$activePage = 'noticias'; // Para destacar na sidebar

// Dados do utilizador para o header
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

// Função de avatar consistente
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}
$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);

// Breadcrumbs
$breadcrumbs = [['nome' => 'Notícias Semanais', 'link' => 'noticias.php']];

// Função para formatar datas de notícias
function format_news_display_date($date_string) {
    try {
        $date = new DateTime($date_string);
        $formatter = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Sao_Paulo', IntlDateFormatter::GREGORIAN);
        return $formatter->format($date);
    } catch (Exception $e) {
        return date("d/m/Y", strtotime($date_string));
    }
}

// Buscar notícias do banco de dados
$weekly_news_from_db = [];
$erro_noticias = null;

try {
    $stmt = $pdo->prepare(
        "SELECT n.*,
            (SELECT COUNT(*) FROM noticia_votos v WHERE v.id_noticia=n.id_noticia AND v.valor='up') AS upvotes,
            (SELECT COUNT(*) FROM noticia_votos v WHERE v.id_noticia=n.id_noticia AND v.valor='down') AS downvotes,
            (SELECT valor FROM noticia_votos v WHERE v.id_noticia=n.id_noticia AND v.id_utilizador=:uid LIMIT 1) AS user_vote
         FROM noticias n
         WHERE n.ativo = TRUE AND n.visibilidade = 'publico'
         ORDER BY n.data_publicacao DESC
         LIMIT 12"
    );
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $weekly_news_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao buscar notícias: " . $e->getMessage());
    $erro_noticias = "Não foi possível carregar as notícias no momento. Por favor, tente novamente mais tarde.";
}

// Para depuração: Descomente as linhas abaixo para ver o que está a ser retornado.
/*
echo "<pre>Erro Notícias: "; var_dump($erro_noticias); echo "</pre>";
echo "<pre>Notícias do DB: "; var_dump($weekly_news_from_db); echo "</pre>";
*/
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#2563eb',
                        'primary-blue-light': '#dbeafe',
                        'primary-blue-dark': '#1e40af',
                        'light-bg': '#f7fafc', 
                        'card-bg': '#ffffff',   
                        'dark-text': '#1f2937',
                        'medium-text': '#4b5563',
                        'light-text': '#6b7280',
                        'success': '#10b981',
                        'info': '#3b82f6',    
                        'danger': '#ef4444',
                        'warning': '#f59e0b', 
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'modal-pop': 'modalPop 0.3s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        modalPop: {
                            '0%': { opacity: '0', transform: 'scale(0.95) translateY(10px)' },
                            '100%': { opacity: '1', transform: 'scale(1) translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300', '#c1c1c1'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400', '#a1a1a1'); }
        html, body { height: 100%; font-family: 'Inter', sans-serif; }
        body { display: flex; flex-direction: column; background-color: theme('colors.light-bg'); }
        .main-container { flex-grow: 1; }
        
        .sidebar { transition: left 0.3s ease-in-out; }
        .sidebar.open { left: 0; }
        .sidebar-icon { width: 20px; height: 20px; }
        .active-nav-link { 
            background-color: theme('colors.primary-blue-light'); 
            color: theme('colors.primary-blue');
            border-right: 3px solid theme('colors.primary-blue');
        }
        .active-nav-link i, .active-nav-link svg { 
            color: theme('colors.primary-blue') !important; 
        }
        .news-card-animated { 
            opacity:0; /* Começa invisível */
        }
        .news-card-animated.is-visible { 
            animation: fadeInUp 0.6s ease-out forwards; /* Aplica animação quando visível */
        }

        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .news-modal-content h4 { font-size: 1.125rem; line-height: 1.75rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.5rem; color: theme('colors.dark-text');}
        .news-modal-content ul { list-style-type: disc; list-style-position: inside; margin-left: 0.5rem; space-y: 0.25rem; color: theme('colors.medium-text');}
        .news-modal-content p { margin-top: 0.75rem; color: theme('colors.medium-text'); line-height: 1.625;}
        .news-modal-content a { color: theme('colors.primary-blue'); text-decoration: underline; }
        .news-modal-content a:hover { color: theme('colors.primary-blue-dark'); }
    </style>
</head>
<body class="text-dark-text">

    <div class="flex h-screen main-container">
        <aside id="sidebar" class="sidebar fixed lg:static inset-y-0 left-[-256px] lg:left-0 z-50 w-64 bg-card-bg p-5 space-y-5 border-r border-gray-200 overflow-y-auto">
            <div class="text-2xl font-bold text-primary-blue mb-6">AudioTO</div>
            <nav class="space-y-1.5" id="mainNav">
                <a href="inicio.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'inicio') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-home sidebar-icon"></i> <span class="text-sm font-medium">Início</span>
                </a>
                <a href="podcasts.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'podcasts') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-podcast sidebar-icon"></i> <span class="text-sm font-medium">Podcasts</span>
                </a>
                <a href="oportunidades.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'oportunidades') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-lightbulb sidebar-icon"></i> <span class="text-sm font-medium">Oportunidades</span>
                </a>
                <a href="favoritos.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'favoritos') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-heart sidebar-icon"></i> <span class="text-sm font-medium">Meus Favoritos</span>
                </a>
                <a href="historico.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'historico') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-history sidebar-icon"></i> <span class="text-sm font-medium">Histórico</span>
                </a>
                <a href="planos.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'planos') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-crown sidebar-icon"></i> <span class="text-sm font-medium">Planos</span>
                </a>
                <a href="comunidade.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'comunidade') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-users sidebar-icon"></i> <span class="text-sm font-medium">Comunidade</span>
                </a>
                <a href="noticias.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'noticias') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-newspaper sidebar-icon"></i> <span class="text-sm font-medium">Notícias</span>
                </a>
            </nav>
            <div class="pt-5 border-t border-gray-200">
                <a href="perfil.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'perfil') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-user-circle sidebar-icon"></i> <span class="text-sm font-medium">Meu Perfil</span>
                </a>
                 <a href="configuracoes.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'configuracoes') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-cog sidebar-icon"></i> <span class="text-sm font-medium">Configurações</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue">
                    <i class="fas fa-sign-out-alt sidebar-icon"></i> <span class="text-sm font-medium">Sair</span>
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
                        <input type="text" placeholder="Buscar notícias..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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
                            <img src="<?= htmlspecialchars($avatarUrl); ?>" alt="Avatar de <?= htmlspecialchars($userName); ?>" class="w-9 h-9 rounded-full border-2 border-primary-blue-light">
                            <div class="hidden md:block">
                                <p class="text-xs font-medium text-dark-text"><?= htmlspecialchars($userName); ?></p>
                                <p class="text-xs text-light-text"><?= htmlspecialchars($userEmail); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-gray-500 hidden md:block"></i>
                        </button>
                        <div id="userDropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl z-20 py-1 border border-gray-200">
                            <a href="perfil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Meu Perfil</a>
                            <a href="configuracoes.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Configurações</a>
                            <hr class="my-1 border-gray-200">
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Sair</a>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-light-bg p-6 md:p-8 space-y-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-dark-text">Notícias Semanais AudioTO</h1>
                        <p class="text-medium-text mt-1">Mantenha-se atualizado com as últimas novidades e insights.</p>
                    </div>
                </div>

                <div class="mb-6 flex space-x-2">
                    <button class="px-4 py-2 text-sm font-medium bg-primary-blue text-white rounded-lg shadow-sm hover:bg-primary-blue-dark transition-colors">Todas</button>
                    <button class="px-4 py-2 text-sm font-medium bg-card-bg text-medium-text border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Última Semana</button>
                    <button class="px-4 py-2 text-sm font-medium bg-card-bg text-medium-text border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Último Mês</button>
                </div>

                <?php if ($erro_noticias): ?>
                    <div class="bg-red-100 border-l-4 border-danger text-red-700 p-4 rounded-md shadow" role="alert">
                        <p class="font-bold">Erro:</p>
                        <p><?= htmlspecialchars($erro_noticias); ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 xl:gap-8">
                    <?php if (empty($weekly_news_from_db) && !$erro_noticias): ?>
                        <div class="md:col-span-2 lg:col-span-3 text-center text-medium-text py-12 bg-card-bg rounded-lg shadow">
                            <i class="far fa-newspaper text-4xl text-gray-300 mb-3"></i>
                            <p class="text-xl font-semibold text-dark-text">Nenhuma notícia publicada ainda.</p>
                            <p class="text-sm">Volte em breve para as últimas atualizações!</p>
                        </div>
                    <?php elseif (!empty($weekly_news_from_db)): ?>
                        <?php foreach ($weekly_news_from_db as $index => $news_item): ?>
                            <article class="news-card-animated bg-card-bg rounded-xl shadow-lg overflow-hidden flex flex-col hover:shadow-xl transition-shadow duration-300 group" 
                                     data-news-id="<?= $news_item['id_noticia']; ?>"
                                     data-title="<?= htmlspecialchars($news_item['titulo']); ?>"
                                     data-date="<?= htmlspecialchars(format_news_display_date($news_item['data_publicacao'])); ?>"
                                     data-author="<?= htmlspecialchars($news_item['autor_noticia'] ?? 'Equipa AudioTO'); ?>"
                                     data-category="<?= htmlspecialchars($news_item['categoria_noticia'] ?? 'Geral'); ?>"
                                     data-image="<?= htmlspecialchars($news_item['url_imagem_destaque'] ?? 'https://placehold.co/720x400/cccccc/FFFFFF?text=Sem+Imagem'); ?>">
                                <div class="h-48 w-full overflow-hidden">
                                     <img src="<?= htmlspecialchars($news_item['url_imagem_destaque'] ?? 'https://placehold.co/720x400/cccccc/FFFFFF?text=Sem+Imagem'); ?>" 
                                          alt="Imagem para <?= htmlspecialchars($news_item['titulo']); ?>" 
                                          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </div>
                                <div class="p-5 sm:p-6 flex flex-col flex-grow">
                                    <span class="text-xs font-semibold uppercase text-primary-blue tracking-wider mb-1"><?= htmlspecialchars($news_item['categoria_noticia'] ?? 'Destaque'); ?></span>
                                    <h2 class="text-lg sm:text-xl font-semibold text-dark-text mb-2 line-clamp-2 group-hover:text-primary-blue transition-colors">
                                        <a href="#" class="open-news-modal-button"><?= htmlspecialchars($news_item['titulo']); ?></a>
                                    </h2>
                                    <p class="text-xs text-light-text mb-3">
                                        <i class="far fa-calendar-alt mr-1"></i> <?= htmlspecialchars(format_news_display_date($news_item['data_publicacao'])); ?> 
                                        <span class="mx-1">&bull;</span> 
                                        <i class="fas fa-user-edit mr-1"></i> <?= htmlspecialchars($news_item['autor_noticia'] ?? 'Equipa AudioTO'); ?>
                                    </p>
                                    <p class="text-sm text-medium-text line-clamp-3 mb-4 flex-grow">
                                        <?= htmlspecialchars($news_item['excerto'] ?? 'Leia mais para ver o conteúdo completo.'); ?>
                                    </p>
                                    <div class="flex items-center justify-between mt-auto">
                                        <div class="flex items-center gap-3 text-sm">
                                            <button class="vote-button" data-id="<?= $news_item['id_noticia']; ?>" data-action="up">
                                                <i class="fas fa-arrow-up <?php if(($news_item['user_vote'] ?? '') === 'up') echo 'text-primary-blue'; ?>"></i>
                                                <span class="up-count ml-0.5"><?= (int)($news_item['upvotes'] ?? 0); ?></span>
                                            </button>
                                            <button class="vote-button" data-id="<?= $news_item['id_noticia']; ?>" data-action="down">
                                                <i class="fas fa-arrow-down <?php if(($news_item['user_vote'] ?? '') === 'down') echo 'text-primary-blue'; ?>"></i>
                                                <span class="down-count ml-0.5"><?= (int)($news_item['downvotes'] ?? 0); ?></span>
                                            </button>
                                        </div>
                                        <button class="open-news-modal-button text-sm bg-primary-blue-light text-primary-blue-dark font-semibold py-2 px-4 rounded-md hover:bg-primary-blue hover:text-white transition-all duration-200 self-start">
                                            Ler Mais <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                        </button>
                                    </div>
                                    <div class="hidden news-full-content"><?= $news_item['conteudo_completo_html']; // Atenção: Confie na fonte do HTML ou sanitize antes de exibir ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                 <nav class="mt-10 flex justify-center" aria-label="Paginação de Notícias">
                    <ul class="inline-flex items-center -space-x-px">
                        <li>
                            <a href="#" class="py-2 px-3 ml-0 leading-tight text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Anterior</a>
                        </li>
                        <li>
                            <a href="#" aria-current="page" class="z-10 py-2 px-3 leading-tight text-primary-blue bg-primary-blue-light border border-primary-blue hover:bg-blue-100 hover:text-blue-700">1</a>
                        </li>
                        <li>
                            <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700">2</a>
                        </li>
                        <li>
                            <a href="#" class="py-2 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700">Próxima</a>
                        </li>
                    </ul>
                </nav>
            </main>
        </div>
    </div>

    <div id="newsModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-[70] hidden p-4 animate-modal-pop">
        <div class="bg-card-bg rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-5 sm:p-6 border-b border-gray-200">
                <h2 id="newsModalTitle" class="text-xl sm:text-2xl font-semibold text-dark-text"></h2>
                <button id="closeNewsModalButton" class="text-gray-400 hover:text-danger transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="p-5 sm:p-6 overflow-y-auto news-modal-content flex-grow">
                <div class="mb-3 text-xs text-light-text">
                    <span id="newsModalDate"></span> <span class="mx-1">&bull;</span> Por: <span id="newsModalAuthor" class="font-medium"></span>
                </div>
                <img id="newsModalImage" src="" alt="Imagem da Notícia" class="w-full h-auto max-h-80 object-cover rounded-lg mb-4 hidden">
                <div id="newsModalFullContent" class="prose prose-sm sm:prose-base max-w-none">
                    </div>
            </div>
            <div class="p-5 sm:p-6 border-t border-gray-200 text-right">
                 <button id="closeNewsModalButtonFooter" class="px-6 py-2 text-sm font-medium text-white bg-primary-blue hover:bg-primary-blue-dark rounded-md transition-colors">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userDropdownButton = document.getElementById('userDropdownButton');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            
            if (userDropdownButton && userDropdownMenu) {
                userDropdownButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userDropdownMenu.classList.toggle('hidden');
                });
                document.addEventListener('click', (event) => {
                    if (userDropdownMenu && !userDropdownMenu.classList.contains('hidden') &&
                        userDropdownButton && !userDropdownButton.contains(event.target) && 
                        !userDropdownMenu.contains(event.target)) {
                        userDropdownMenu.classList.add('hidden');
                    }
                });
            }

            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            function toggleMobileSidebar() {
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.toggle('open'); 
                    sidebar.classList.toggle('left-[-256px]');
                    sidebar.classList.toggle('left-0');
                    sidebarOverlay.classList.toggle('hidden');
                }
            }

            if (mobileMenuButton) {
                mobileMenuButton.addEventListener('click', toggleMobileSidebar);
            }
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleMobileSidebar);
            }
            
            // Modal de Notícia
            const newsModal = document.getElementById('newsModal');
            const newsModalTitle = document.getElementById('newsModalTitle');
            const newsModalDate = document.getElementById('newsModalDate');
            const newsModalAuthor = document.getElementById('newsModalAuthor');
            const newsModalImage = document.getElementById('newsModalImage');
            const newsModalFullContent = document.getElementById('newsModalFullContent');
            const closeNewsModalButton = document.getElementById('closeNewsModalButton');
            const closeNewsModalButtonFooter = document.getElementById('closeNewsModalButtonFooter');

            document.querySelectorAll('.open-news-modal-button').forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const card = this.closest('article.news-card-animated');
                    
                    if (!card || !newsModal || !newsModalTitle || !newsModalDate || !newsModalAuthor || !newsModalImage || !newsModalFullContent) {
                        console.error('Um ou mais elementos do modal não foram encontrados.');
                        return;
                    }
                    
                    newsModalTitle.textContent = card.dataset.title || 'Título não disponível';
                    newsModalDate.textContent = card.dataset.date || '';
                    newsModalAuthor.textContent = card.dataset.author || '';
                    
                    const imageUrl = card.dataset.image;
                    if (imageUrl && imageUrl !== 'https://placehold.co/1x1/0000/0000?text=.' && imageUrl !== 'https://placehold.co/720x400/cccccc/FFFFFF?text=Sem+Imagem' ) {
                        newsModalImage.src = imageUrl;
                        newsModalImage.alt = "Imagem para " + card.dataset.title;
                        newsModalImage.classList.remove('hidden');
                    } else {
                        newsModalImage.classList.add('hidden');
                        newsModalImage.src = ''; 
                    }
                    
                    const fullContentDiv = card.querySelector('.news-full-content');
                    if (fullContentDiv) {
                        newsModalFullContent.innerHTML = fullContentDiv.innerHTML; 
                    } else {
                        newsModalFullContent.innerHTML = '<p>Conteúdo não disponível.</p>';
                    }
                    
                    newsModal.classList.remove('hidden');
                });
            });

            function closeNewsModal() {
                if(newsModal) newsModal.classList.add('hidden');
            }

            if(closeNewsModalButton) closeNewsModalButton.addEventListener('click', closeNewsModal);
            if(closeNewsModalButtonFooter) closeNewsModalButtonFooter.addEventListener('click', closeNewsModal);
            
            if(newsModal) {
                newsModal.addEventListener('click', function(event) {
                    if (event.target === newsModal) { 
                        closeNewsModal();
                    }
                });
            }

            // Animação de entrada para os cards
            const animatedElements = document.querySelectorAll('.news-card-animated');
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        console.log('Card de notícia intersetando:', entry.target); // Log para depuração
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target); 
                    }
                });
            }, { threshold: 0.1 });

            if (animatedElements.length > 0) {
                animatedElements.forEach(el => {
                    if(el) observer.observe(el);
                });
            } else {
                console.log('Nenhum elemento .news-card-animated encontrado para observar.');
            }

            document.querySelectorAll('.vote-button').forEach(btn => {
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    const id = this.dataset.id;
                    const action = this.dataset.action;
                    const params = new URLSearchParams({ id_noticia:id, acao:action });
                    fetch('noticia_votar.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:params})
                    .then(r=>r.json()).then(data=>{
                        if(data.success){
                            const card = this.closest('article');
                            if(card){
                                const upSpan = card.querySelector('.up-count');
                                const downSpan = card.querySelector('.down-count');
                                const upIcon = card.querySelector('.vote-button[data-action="up"] i');
                                const downIcon = card.querySelector('.vote-button[data-action="down"] i');
                                if(upSpan) upSpan.textContent = data.up;
                                if(downSpan) downSpan.textContent = data.down;
                                if(upIcon) upIcon.classList.toggle('text-primary-blue', data.vote==='up');
                                if(downIcon) downIcon.classList.toggle('text-primary-blue', data.vote==='down');
                            }
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
