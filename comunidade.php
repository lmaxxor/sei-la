<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Garante que o utilizador está logado
require_once __DIR__ . '/db/db_connect.php'; // Conexão com o banco de dados

// Variáveis específicas da página
$pageTitle = "Comunidade - AudioTO";
$activePage = 'comunidade'; // Para destacar na sidebar

// Dados do utilizador para o header
$userId = $_SESSION['user_id'] ?? null;
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
$userAvatarForPost = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 48); // Avatar um pouco maior para posts

// Breadcrumbs
$breadcrumbs = [['nome' => 'Comunidade', 'link' => '#']];

// Placeholder para publicações da comunidade (futuramente viria do banco de dados)
// Para tornar dinâmico, você precisaria de uma tabela como 'comunidade_publicacoes'
// e buscar dados de utilizadores da tabela 'utilizadores'.
$community_posts = [
    [
        'post_id' => 1,
        'user_id' => 6, // ID de um utilizador existente para exemplo
        'user_name' => 'Mariana Costa', // Nome do utilizador
        'user_avatar' => get_user_avatar_placeholder('Mariana Costa', null, 48), // Avatar do utilizador
        'timestamp' => '2 horas atrás',
        'title' => 'Dicas para Terapia Ocupacional em Casa com Crianças Autistas',
        'content_preview' => 'Olá comunidade! Gostaria de partilhar algumas estratégias que têm funcionado muito bem nas minhas sessões remotas com crianças no espectro autista, focando em atividades que os pais podem facilmente replicar em casa. Alguém tem mais sugestões?',
        'likes' => 15,
        'comments' => 4,
        'tags' => ['Terapia Ocupacional', 'Autismo', 'Crianças', 'Dicas Práticas']
    ],
    [
        'post_id' => 2,
        'user_id' => 7,
        'user_name' => 'Rafael Gomes',
        'user_avatar' => get_user_avatar_placeholder('Rafael Gomes', null, 48),
        'timestamp' => '5 horas atrás',
        'title' => 'Webinar Imperdível sobre Novas Tecnologias Assistivas',
        'content_preview' => 'Acabei de participar num webinar incrível sobre as últimas novidades em tecnologia assistiva para reabilitação motora. Alguém mais viu? Gostaria de discutir os pontos altos e como podemos aplicar essas ferramentas no nosso dia a dia.',
        'likes' => 22,
        'comments' => 8,
        'tags' => ['Tecnologia Assistiva', 'Reabilitação', 'Webinar']
    ],
    [
        'post_id' => 3,
        'user_id' => 8,
        'user_name' => 'Patrícia Albuquerque',
        'user_avatar' => get_user_avatar_placeholder('Patrícia Albuquerque', null, 48),
        'timestamp' => '1 dia atrás',
        'title' => 'Desafios da Fisioterapia Respiratória em Pacientes Pós-COVID',
        'content_preview' => 'Tenho enfrentado alguns desafios específicos com pacientes na reabilitação respiratória pós-COVID, especialmente em relação à fadiga persistente. Quais abordagens vocês têm utilizado com sucesso? Vamos trocar experiências!',
        'likes' => 30,
        'comments' => 12,
        'tags' => ['Fisioterapia', 'Pós-COVID', 'Reabilitação Respiratória']
    ]
];

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
                        'light-bg': '#f7fafc', // Consistent with other pages
                        'card-bg': '#ffffff',   // Consistent
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
        .content-item-animated { animation: fadeInUp 0.5s ease-out forwards; opacity:0; }
        .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .tag-chip {
            background-color: theme('colors.primary-blue-light');
            color: theme('colors.primary-blue-dark');
            padding: 0.25rem 0.75rem;
            border-radius: 9999px; /* full */
            font-size: 0.75rem; /* text-xs */
            font-weight: 500; /* medium */
            transition: background-color 0.2s;
        }
        .tag-chip:hover {
            background-color: theme('colors.primary-blue');
            color: theme('colors.white', '#ffffff');
        }
    </style>
</head>
<body class="text-dark-text">

    <div class="flex h-screen main-container">
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
                        <input type="text" placeholder="Buscar na comunidade..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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
                        <h1 class="text-3xl font-bold text-dark-text">Comunidade AudioTO</h1>
                        <p class="text-medium-text mt-1">Conecte-se, partilhe e aprenda com outros profissionais.</p>
                    </div>
                    <button id="openNewPostModalButton" class="mt-4 sm:mt-0 bg-primary-blue hover:bg-primary-blue-dark text-white font-semibold py-2.5 px-5 rounded-lg shadow-md hover:shadow-lg transition-all duration-150 flex items-center">
                        <i class="fas fa-plus mr-2 text-sm"></i> Criar Publicação
                    </button>
                </div>

                <div class="space-y-6">
                    <?php if (empty($community_posts)): ?>
                        <div class="text-center text-medium-text py-12 bg-card-bg rounded-lg shadow">
                            <i class="fas fa-comments text-4xl text-gray-300 mb-3"></i>
                            <p class="text-xl font-semibold text-dark-text">Ainda não há publicações.</p>
                            <p class="text-sm">Seja o primeiro a iniciar uma discussão!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($community_posts as $index => $post): ?>
                            <article class="bg-card-bg p-5 sm:p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 content-item-animated" style="animation-delay: <?= $index * 0.1 ?>s;">
                                <div class="flex items-start space-x-4">
                                    <img src="<?= htmlspecialchars($post['user_avatar']); ?>" alt="Avatar de <?= htmlspecialchars($post['user_name']); ?>" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full flex-shrink-0">
                                    <div class="flex-grow">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h3 class="text-base sm:text-lg font-semibold text-dark-text hover:text-primary-blue transition-colors">
                                                    <a href="publicacao_detalhe.php?id=<?= $post['post_id']; ?>"><?= htmlspecialchars($post['title']); ?></a>
                                                </h3>
                                                <p class="text-xs text-light-text">
                                                    Publicado por <span class="font-medium text-medium-text"><?= htmlspecialchars($post['user_name']); ?></span> - <?= htmlspecialchars($post['timestamp']); ?>
                                                </p>
                                            </div>
                                            <button class="text-gray-400 hover:text-primary-blue p-1 -mr-1 rounded-full">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                        </div>
                                        <p class="text-sm text-medium-text mt-2 line-clamp-3">
                                            <?= htmlspecialchars($post['content_preview']); ?>
                                        </p>
                                        <?php if (!empty($post['tags'])): ?>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <?php foreach($post['tags'] as $tag): ?>
                                                <a href="comunidade.php?tag=<?= urlencode($tag); ?>" class="tag-chip">
                                                    <?= htmlspecialchars($tag); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mt-4 pt-3 border-t border-gray-200 flex items-center space-x-6 text-sm text-light-text">
                                            <button class="hover:text-primary-blue flex items-center group">
                                                <i class="far fa-thumbs-up mr-1.5 group-hover:text-primary-blue transition-colors"></i> <?= $post['likes']; ?> Curtidas
                                            </button>
                                            <a href="publicacao_detalhe.php?id=<?= $post['post_id']; ?>#comments" class="hover:text-primary-blue flex items-center group">
                                                <i class="far fa-comment mr-1.5 group-hover:text-primary-blue transition-colors"></i> <?= $post['comments']; ?> Comentários
                                            </a>
                                            <button class="hover:text-primary-blue flex items-center group ml-auto">
                                                <i class="fas fa-share-alt mr-1.5 group-hover:text-primary-blue transition-colors"></i> Partilhar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                 <nav class="mt-8 flex justify-center" aria-label="Paginação">
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

    <div id="newPostModal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-[60] hidden p-4">
        <div class="bg-card-bg p-6 sm:p-8 rounded-xl shadow-2xl w-full max-w-lg transform transition-all animate-modal-pop">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-dark-text">Criar Nova Publicação</h2>
                <button id="closeNewPostModalButton" class="text-gray-400 hover:text-danger transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="newPostForm">
                <div class="mb-4">
                    <label for="postTitle" class="block text-sm font-medium text-medium-text mb-1">Título</label>
                    <input type="text" id="postTitle" name="postTitle" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow" placeholder="Um título conciso e informativo">
                </div>
                <div class="mb-4">
                    <label for="postContent" class="block text-sm font-medium text-medium-text mb-1">Conteúdo</label>
                    <textarea id="postContent" name="postContent" rows="6" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow" placeholder="Escreva aqui a sua mensagem para a comunidade..."></textarea>
                </div>
                 <div class="mb-6">
                    <label for="postTags" class="block text-sm font-medium text-medium-text mb-1">Tags (separadas por vírgula)</label>
                    <input type="text" id="postTags" name="postTags" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-blue focus:border-primary-blue transition-shadow" placeholder="Ex: Terapia Ocupacional, Dicas, Crianças">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancelNewPostButton" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">Cancelar</button>
                    <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-primary-blue hover:bg-primary-blue-dark rounded-md shadow-sm hover:shadow-md transition-all">Publicar</button>
                </div>
            </form>
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

            // Modal Nova Publicação
            const openNewPostModalButton = document.getElementById('openNewPostModalButton');
            const newPostModal = document.getElementById('newPostModal');
            const closeNewPostModalButton = document.getElementById('closeNewPostModalButton');
            const cancelNewPostButton = document.getElementById('cancelNewPostButton');
            const newPostForm = document.getElementById('newPostForm');

            if (openNewPostModalButton && newPostModal) {
                openNewPostModalButton.addEventListener('click', () => {
                    newPostModal.classList.remove('hidden');
                });
            }
            if (closeNewPostModalButton && newPostModal) {
                closeNewPostModalButton.addEventListener('click', () => {
                    newPostModal.classList.add('hidden');
                });
            }
            if (cancelNewPostButton && newPostModal) {
                cancelNewPostButton.addEventListener('click', () => {
                    newPostModal.classList.add('hidden');
                });
            }
            // Fechar modal ao clicar fora (no overlay)
            if (newPostModal) {
                newPostModal.addEventListener('click', function(event) {
                    if (event.target === newPostModal) { // Clicou no fundo do modal
                        newPostModal.classList.add('hidden');
                    }
                });
            }


            if (newPostForm) {
                newPostForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    // Aqui você enviaria os dados do formulário para o backend (ex: via fetch/AJAX)
                    // para criar a nova publicação no banco de dados.
                    const title = document.getElementById('postTitle').value;
                    const content = document.getElementById('postContent').value;
                    const tags = document.getElementById('postTags').value;
                    console.log('Nova Publicação:', { title, content, tags });
                    alert('Publicação enviada (simulação)! Veja o console para os dados.');
                    newPostModal.classList.add('hidden');
                    this.reset(); // Limpa o formulário
                    // Após sucesso no backend, você poderia recarregar as publicações ou adicionar a nova dinamicamente.
                });
            }
            
            // Animação de entrada para os cards
            const animatedElements = document.querySelectorAll('.content-item-animated');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.animationPlayState = 'running';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05 }); // Ajustado threshold para melhor acionamento
            animatedElements.forEach(el => { observer.observe(el); });
        });
    </script>
</body>
</html>
