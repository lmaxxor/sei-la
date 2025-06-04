<?php
// historico.php

// 1. Incluir o gestor de sessões e verificar login
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Redireciona para login se não estiver logado

// 2. Incluir a conexão com o banco de dados
require_once __DIR__ . '/db/db_connect.php';

$pageTitle = "Histórico de Reprodução - AudioTO";
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

// Função de avatar (consistente com outros arquivos)
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}

$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);

// Breadcrumbs
$breadcrumbs = [['nome' => 'Histórico de Reprodução', 'link' => '#']];

// 3. Lógica para buscar histórico de reprodução
$historico_reproducao = [];
$erro_pagina = null;

if ($userId) {
    try {
        $stmt_hist = $pdo->prepare(
            "SELECT
                pr.id_posicao,
                pr.id_podcast,
                pr.posicao_segundos,
                pr.data_atualizacao,
                p.titulo_podcast,
                p.slug_podcast,
                p.imagem_capa_url,      -- Assumindo que este campo existe
                p.duracao_total_segundos, -- Assumindo que este campo existe
                ap.nome_assunto,
                cp.nome_categoria
            FROM posicao_reproducao_utilizador pr
            JOIN podcasts p ON pr.id_podcast = p.id_podcast
            LEFT JOIN assuntos_podcast ap ON p.id_assunto = ap.id_assunto
            LEFT JOIN categorias_podcast cp ON ap.id_categoria = cp.id_categoria
            WHERE pr.id_utilizador = :userId
            ORDER BY pr.data_atualizacao DESC
            LIMIT 50" // Limitar para evitar sobrecarga, pode adicionar paginação depois
        );
        $stmt_hist->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_hist->execute();
        $historico_reproducao = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

        // Formatar dados para exibição
        foreach ($historico_reproducao as &$item) {
            // Imagem
            $item['imagem_exibir'] = $item['imagem_capa_url']
                ? htmlspecialchars($item['imagem_capa_url'])
                : "https://placehold.co/100x100/2563eb/FFFFFF?text=" . urlencode(substr($item['titulo_podcast'], 0, 1));

            // Progresso
            $item['progresso_percentual'] = 0;
            if (!empty($item['duracao_total_segundos']) && $item['duracao_total_segundos'] > 0) {
                $item['progresso_percentual'] = round(($item['posicao_segundos'] / $item['duracao_total_segundos']) * 100);
            } elseif ($item['posicao_segundos'] > 0) {
                $item['progresso_percentual'] = 5; // Progresso mínimo se iniciado mas sem duração total
            }
             $item['progresso_percentual'] = min(100, max(0, $item['progresso_percentual'])); // Garante entre 0 e 100

            // Data formatada
            try {
                $data = new DateTime($item['data_atualizacao'], new DateTimeZone('UTC')); // Assumindo que está em UTC no BD
                $data->setTimezone(new DateTimeZone('America/Sao_Paulo')); // Ajustar para o fuso horário local
                $item['data_formatada'] = $data->format('d/m/Y H:i');
            } catch (Exception $e) {
                $item['data_formatada'] = 'Data inválida';
            }
        }
        unset($item); // Limpar referência do loop

    } catch (PDOException $e) {
        error_log("Erro ao buscar histórico: " . $e->getMessage());
        $erro_pagina = "Não foi possível carregar seu histórico. Por favor, tente novamente mais tarde.";
    }
} else {
    $erro_pagina = "Faça login para ver seu histórico de reprodução.";
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
                        'danger': '#ef4444',
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
        .content-item-animated { opacity: 0; animation-fill-mode: forwards; }
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400'); }
        .progress-bar-bg { background-color: theme('colors.gray.200'); border-radius: theme('borderRadius.full'); overflow: hidden; height: 6px; }
        .progress-bar-fill { background-color: theme('colors.primary-blue'); height: 100%; border-radius: theme('borderRadius.full'); transition: width 0.3s ease; }
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
                    <input type="text" placeholder="Buscar no Histórico..." id="searchInputHist" class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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
                        <span class="text-dark-text font-semibold">Histórico de Reprodução</span>
                    </li>
                </ol>
            </nav>

            <?php if ($feedback_message): ?>
                <div class="p-4 mb-4 text-sm rounded-lg <?php echo $feedback_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-card-bg p-5 rounded-xl shadow-lg">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-dark-text mb-3 sm:mb-0">Seu Histórico</h1>
                    </div>

                <?php if ($erro_pagina): ?>
                    <div class="text-center text-danger py-12">
                        <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
                        <p class="text-xl font-semibold mt-2"><?php echo htmlspecialchars($erro_pagina); ?></p>
                    </div>
                <?php elseif (empty($historico_reproducao)): ?>
                    <div id="noHistoryMessage" class="text-center text-medium-text py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h7.5M3.75 6.75h.007v.008H3.75V6.75zm.375 0a3.001 3.001 0 00-3 0M3.75 12h.007v.008H3.75V12zm.375 0a3.001 3.001 0 00-3 0M3.75 17.25h.007v.008H3.75v-.008zm.375 0a3.001 3.001 0 00-3 0M21 6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75m0 10.5A2.25 2.25 0 005.25 19.5h13.5A2.25 2.25 0 0021 17.25M3 12h18M3 17.25h18" />
                        </svg>
                        <p class="text-xl font-semibold mt-2 text-dark-text">Seu histórico está vazio.</p>
                        <p class="text-sm text-light-text">Comece a ouvir alguns podcasts para criar seu histórico.</p>
                        <a href="podcasts.php" class="mt-4 inline-block bg-primary-blue text-white text-sm font-medium py-2 px-4 rounded-lg hover:bg-primary-blue-dark transition-colors">Explorar Podcasts</a>
                    </div>
                <?php else: ?>
                    <div id="historyItemsContainer" class="space-y-4">
                        <?php foreach ($historico_reproducao as $index => $item): ?>
                        <div class="history-item bg-card-bg p-4 rounded-lg shadow-md hover:shadow-lg transition-shadow flex flex-col sm:flex-row items-start gap-4 content-item-animated"
                             style="animation-name: fadeInUp; animation-delay: <?php echo $index * 0.05; ?>s;"
                             data-title="<?php echo htmlspecialchars(strtolower($item['titulo_podcast'])); ?>"
                             data-category="<?php echo htmlspecialchars(strtolower($item['nome_categoria'] ?? '')); ?>"
                             data-subject="<?php echo htmlspecialchars(strtolower($item['nome_assunto'] ?? '')); ?>">
                            <a href="player_podcast.php?slug=<?php echo htmlspecialchars($item['slug_podcast']); ?>" class="flex-shrink-0">
                                <img src="<?php echo $item['imagem_exibir']; ?>" alt="Capa de <?php echo htmlspecialchars($item['titulo_podcast']); ?>" class="w-20 h-20 sm:w-24 sm:h-24 rounded-md object-cover">
                            </a>
                            <div class="flex-grow">
                                <a href="player_podcast.php?slug=<?php echo htmlspecialchars($item['slug_podcast']); ?>" class="hover:text-primary-blue transition-colors">
                                    <h3 class="text-md sm:text-lg font-semibold text-dark-text line-clamp-1"><?php echo htmlspecialchars($item['titulo_podcast']); ?></h3>
                                </a>
                                <?php if (!empty($item['nome_assunto'])): ?>
                                <p class="text-xs text-medium-text line-clamp-1">
                                    <?php echo htmlspecialchars($item['nome_assunto']); ?>
                                    <?php if (!empty($item['nome_categoria'])): ?>
                                        <span class="text-light-text">&bull; <?php echo htmlspecialchars($item['nome_categoria']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php endif; ?>
                                <p class="text-xs text-light-text mt-1">Última vez ouvido: <?php echo $item['data_formatada']; ?></p>
                                <?php if ($item['duracao_total_segundos'] > 0 || $item['posicao_segundos'] > 0 ): // Mostra barra apenas se houver progresso ou duração ?>
                                <div class="mt-2">
                                    <div class="progress-bar-bg w-full">
                                        <div class="progress-bar-fill" style="width: <?php echo $item['progresso_percentual']; ?>%;"></div>
                                    </div>
                                    <p class="text-xs text-light-text text-right mt-0.5"><?php echo $item['progresso_percentual']; ?>% concluído</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="player_podcast.php?slug=<?php echo htmlspecialchars($item['slug_podcast']); ?>" class="mt-2 sm:mt-0 sm:ml-auto flex-shrink-0 bg-primary-blue text-white text-xs font-semibold py-2 px-3 rounded-md hover:bg-primary-blue-dark transition-colors self-start sm:self-center">
                                <i class="fas fa-play mr-1.5"></i> Continuar
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Dropdown do usuário
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

        // Animação de entrada
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

        // Busca no Histórico
        const searchInputHist = document.getElementById('searchInputHist');
        const historyItemsContainer = document.getElementById('historyItemsContainer');
        const noHistoryMessage = document.getElementById('noHistoryMessage'); // Assumindo que você tenha uma mensagem para "nenhum resultado"

        if (searchInputHist && historyItemsContainer) {
            searchInputHist.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const items = historyItemsContainer.getElementsByClassName('history-item');
                let visibleItems = 0;

                Array.from(items).forEach(item => {
                    const title = item.dataset.title || '';
                    const category = item.dataset.category || '';
                    const subject = item.dataset.subject || '';

                    if (title.includes(searchTerm) || category.includes(searchTerm) || subject.includes(searchTerm)) {
                        item.style.display = '';
                        visibleItems++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                // Se você tiver uma mensagem específica para "nenhum resultado de busca", pode mostrá-la aqui
                // if (visibleItems === 0 && searchTerm !== '') {
                //     // noSearchResultsMessage.classList.remove('hidden');
                // } else {
                //     // noSearchResultsMessage.classList.add('hidden');
                // }
                 if (visibleItems === 0 && allHistoryItems.length > 0 && searchTerm !== '') { // Se há itens no histórico, mas a busca não retornou nada
                    if (!document.getElementById('noSearchResultsHist')) {
                        const noResP = document.createElement('p');
                        noResP.id = 'noSearchResultsHist';
                        noResP.className = 'text-center text-medium-text py-10 col-span-full';
                        noResP.textContent = 'Nenhum item no histórico corresponde à sua busca.';
                        historyItemsContainer.appendChild(noResP);
                    }
                } else {
                    const noResP = document.getElementById('noSearchResultsHist');
                    if (noResP) noResP.remove();
                }


            });
        }
         const allHistoryItems = document.querySelectorAll('#historyItemsContainer .history-item');
         if(allHistoryItems.length === 0 && !document.getElementById('noHistoryMessage') && !<?php echo json_encode($erro_pagina ? true : false); ?>){
            // Se o PHP não encontrou itens e não houve erro, a mensagem já deve estar lá via PHP.
            // Mas se o container estiver vazio por JS, podemos forçar a mensagem.
            // Este caso é mais para quando a lista é manipulada totalmente por JS.
         }


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