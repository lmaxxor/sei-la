<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 
ob_start(); 

require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); 
require_once __DIR__ . '/db/db_connect.php'; 

$pageTitle = "Oportunidades - Audio TO";

$userId = $_SESSION['user_id'] ?? 0; 
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

function get_user_avatar_placeholder_blue($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=007AFF&color=fff&size={$size}&rounded=true&bold=true";
}

$avatarUrl = get_user_avatar_placeholder_blue($userName, $userAvatarUrlSession, 40);

$oportunidades_data = [];
$erro_oportunidades = null;
try {
    $sql = "SELECT o.*, CASE WHEN f.id_favorito IS NOT NULL THEN TRUE ELSE FALSE END AS is_favorited
            FROM oportunidades o
            LEFT JOIN favoritos_oportunidade f ON o.id_oportunidade = f.id_oportunidade AND f.id_utilizador = :user_id
            WHERE o.ativo = TRUE
            ORDER BY o.destaque DESC, o.data_publicacao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $raw_oportunidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_oportunidades as $op) {
        $processed_op = [
            'id' => $op['id_oportunidade'],
            'type' => $op['tipo_oportunidade'],
            'title' => $op['titulo_oportunidade'],
            'description' => $op['descricao_oportunidade'],
            'short_description' => mb_strimwidth($op['descricao_oportunidade'] ?? '', 0, 140, "..."),
            'date' => '',
            'source' => $op['fonte_oportunidade'] ?? 'Não informado',
            'tags' => [], 
            'actionText' => 'Ver Detalhes',
            'actionUrl' => $op['link_oportunidade'] ?? '#',
            'themeColorName' => 'tag-outro', 
            'iconSvg' => '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>',
            'is_favorited' => (bool)($op['is_favorited'] ?? false),
            'is_featured' => (bool)($op['destaque'] ?? false)
        ];
        
        try {
            $stmt_tags = $pdo->prepare("
                SELECT t.nome_tag 
                FROM tags t
                JOIN tags_oportunidade to_tag ON t.id_tag = to_tag.id_tag
                WHERE to_tag.id_oportunidade = :id_oportunidade
            ");
            $stmt_tags->execute([':id_oportunidade' => $op['id_oportunidade']]);
            $tags_result = $stmt_tags->fetchAll(PDO::FETCH_COLUMN);
            if ($tags_result) {
                $processed_op['tags'] = $tags_result;
            }
        } catch (PDOException $e_tags) {
            error_log("Erro ao buscar tags para oportunidade ID " . $op['id_oportunidade'] . ": " . $e_tags->getMessage());
        }

        if (!empty($op['data_evento_inicio'])) {
            $processed_op['date'] = 'Evento em: ' . date('d/m/Y', strtotime($op['data_evento_inicio']));
            if (!empty($op['data_evento_fim']) && $op['data_evento_fim'] !== $op['data_evento_inicio']) {
                $processed_op['date'] .= ' a ' . date('d/m/Y', strtotime($op['data_evento_fim']));
            }
            if (!empty($op['local_evento'])) {
                $processed_op['date'] .= ' - Local: ' . htmlspecialchars($op['local_evento']);
            }
        } elseif (!empty($op['data_publicacao'])) {
            $processed_op['date'] = 'Publicado em: ' . date('d/m/Y', strtotime($op['data_publicacao']));
        }
        
        switch ($op['tipo_oportunidade']) {
            case 'curso':
                $processed_op['themeColorName'] = 'tag-curso'; 
                $processed_op['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" /></svg>';
                $processed_op['actionText'] = 'Ver Curso';
                break;
            case 'webinar':
                $processed_op['themeColorName'] = 'tag-webinar'; 
                $processed_op['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12A2.25 2.25 0 0020.25 14.25V3M3.75 21h16.5M11.25 3v11.25m0 0h1.5m-1.5 0l-3.75 3.75M11.25 3l3.75 3.75m0 0l3.75-3.75M3 12h18" /></svg>';
                $processed_op['actionText'] = 'Inscreva-se';
                break;
            case 'artigo':
                $processed_op['themeColorName'] = 'tag-artigo';
                $processed_op['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>';
                $processed_op['actionText'] = 'Ler Artigo';
                break;
            case 'vaga':
                $processed_op['themeColorName'] = 'tag-vaga'; 
                $processed_op['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.623a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" /></svg>';
                $processed_op['actionText'] = 'Ver Vaga';
                break;
            case 'evento':
                $processed_op['themeColorName'] = 'tag-evento'; 
                $processed_op['iconSvg'] = '<svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12v-.008z" /></svg>';
                $processed_op['actionText'] = 'Ver Evento';
                break;
             default:
                $processed_op['themeColorName'] = 'tag-outro'; 
                break;
        }
        // Atualiza a cor do SVG do ícone com base no themeColorName
        $processed_op['iconSvg'] = preg_replace('/(class="w-7 h-7) text-[^"]*"/', '$1 text-'. $processed_op['themeColorName'] .'"', $processed_op['iconSvg']);

        $oportunidades_data[] = $processed_op;
    }

} catch (PDOException $e) {
    error_log("Erro ao buscar oportunidades: " . $e->getMessage());
    $erro_oportunidades = "Não foi possível carregar as oportunidades no momento. Tente novamente mais tarde.";
}
// var_dump($oportunidades_data); // Para depuração, remover depois
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        'primary-blue': '#007AFF', 
                        'primary-blue-light': 'rgba(0, 122, 255, 0.1)', 
                        'primary-blue-dark': '#0056b3',
                        'light-bg': '#F9FAFB', 
                        'card-bg': '#FFFFFF',
                        'dark-text': '#1F2937',
                        'medium-text': '#4B5563',
                        'light-text': '#6B7280',
                        'success': '#10B981', 'danger': '#EF4444', 'warning': '#F59E0B', 'info': '#3B82F6',
                        
                        // Blue palette for tags - As chaves aqui devem corresponder ao 'themeColorName' do PHP
                        'tag-curso': '#2563EB',        // Azul Escuro (Curso)
                        'tag-webinar': '#3B82F6',      // Azul Médio (Webinar)
                        'tag-artigo': '#60A5FA',       // Azul Claro (Artigo)
                        'tag-vaga': '#38BDF8',         // Azul Céu (Vaga)
                        'tag-evento': '#0EA5E9',       // Azul Ciano (Evento)
                        'tag-outro': '#64748B',        // Cinza Azulado (Outro)

                        // Para hover dos botões de ação, um pouco mais escuro
                        'tag-curso-darker': '#1D4ED8',
                        'tag-webinar-darker': '#2563EB',
                        'tag-artigo-darker': '#3B82F6',
                        'tag-vaga-darker': '#0E7490',
                        'tag-evento-darker': '#0369A1',
                        'tag-outro-darker': '#475569',

                        'featured-border': '#007AFF', 
                        'featured-bg-start': 'rgba(0, 122, 255, 0.05)', 
                        'featured-bg-end': 'rgba(59, 130, 246, 0.08)', 
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        'raleway': ['Raleway', 'sans-serif'], 
                    },
                    animation: { 
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                        'pulse-heart': 'pulseHeart 1s ease-in-out',
                        'pulse-featured': 'pulseFeatured 2.5s infinite ease-in-out'
                    },
                    keyframes: { 
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        pulseHeart: { 
                            '0%': { transform: 'scale(1)' },
                            '50%': { transform: 'scale(1.2)' },
                            '100%': { transform: 'scale(1)' },
                        },
                        pulseFeatured: { 
                            '0%, 100%': { boxShadow: '0 0 15px 0px rgba(0, 122, 255, 0.2)' },
                            '50%': { boxShadow: '0 0 25px 8px rgba(0, 122, 255, 0.35)' }
                        }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Raleway:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0f0f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        html { height: 100%; } 
        body { display: flex; flex-direction: column; background-color: theme('colors.light-bg'); min-height: 100vh; }
        .main-container { flex-grow: 1; display: flex; overflow: hidden;  } 
        #main-content-area { flex-grow: 1; overflow-y: auto;  }

        .active-nav-link { background-color: theme('colors.primary-blue-light'); color: theme('colors.primary-blue'); border-right: 3px solid theme('colors.primary-blue'); font-weight: 600; }
        .active-nav-link i, .active-nav-link svg { color: theme('colors.primary-blue'); }
        .filter-tag { transition: all 0.2s ease-in-out; }
        .filter-tag.active { background-color: theme('colors.primary-blue'); color: white !important; border-color: theme('colors.primary-blue') !important; box-shadow: 0 2px 4px theme('colors.primary-blue / 30%'); }
        
        .filter-tag[data-filter="curso"]:hover:not(.active) { border-color: theme('colors.tag-curso'); color: theme('colors.tag-curso');}
        .filter-tag[data-filter="webinar"]:hover:not(.active) { border-color: theme('colors.tag-webinar'); color: theme('colors.tag-webinar');}
        .filter-tag[data-filter="artigo"]:hover:not(.active) { border-color: theme('colors.tag-artigo'); color: theme('colors.tag-artigo');}
        .filter-tag[data-filter="vaga"]:hover:not(.active) { border-color: theme('colors.tag-vaga'); color: theme('colors.tag-vaga');}
        .filter-tag[data-filter="evento"]:hover:not(.active) { border-color: theme('colors.tag-evento'); color: theme('colors.tag-evento');}
        .filter-tag[data-filter="outro"]:hover:not(.active) { border-color: theme('colors.tag-outro'); color: theme('colors.tag-outro');}
        .filter-tag[data-filter="todos"]:hover:not(.active) { border-color: theme('colors.primary-blue'); color: theme('colors.primary-blue');}


        .content-item-animated { opacity: 0; animation-fill-mode: forwards;}
        .modal-overlay { transition: opacity 0.3s ease-in-out; opacity: 0; }
        .modal-content { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; transform: scale(0.95) translateY(10px); opacity: 0; }
        #opportunityModal.flex .modal-overlay { opacity: 0.75; } 
        #opportunityModal.flex .modal-content { transform: scale(1) translateY(0); opacity: 1; }
        .favorite-button { padding: 0.6rem; /* Aumentado */ }
        .favorite-button svg { transition: transform 0.2s ease-in-out, color 0.2s ease-in-out; }
        .favorite-button:hover svg { transform: scale(1.15); }
        .favorite-button.favorited svg { fill: theme('colors.red.500'); color: theme('colors.red.500'); }
        .card-featured { 
            border-left-width: 4px; 
            border-left-color: theme('colors.featured-border'); 
            position: relative; 
            background-image: linear-gradient(135deg, var(--tw-gradient-from), var(--tw-gradient-to));
            --tw-gradient-from: theme('colors.featured-bg-start');
            --tw-gradient-to: theme('colors.featured-bg-end');
            animation: pulseFeatured 2.5s infinite ease-in-out; 
        }
        .featured-badge { position: absolute; top: -10px; right: 12px; background-color: lightseagreen; color: white; padding: 2px 8px; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .sidebar { transition: left 0.3s ease-in-out; }
        .sidebar.open { left: 0; }
        .sidebar-icon { width: 20px; height: 20px; }
    </style>
</head>
<body class="bg-light-bg text-dark-text">

    <div class="flex main-container">         <aside id="sidebar" class="sidebar fixed lg:static inset-y-0 left-[-256px] lg:left-0 z-50 w-64 bg-card-bg p-5 space-y-5 border-r border-gray-200 overflow-y-auto">
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

        <div class="flex-1 flex flex-col">             <header class="bg-card-bg p-4 shadow-sm flex justify-between items-center border-b border-gray-200 sticky top-0 z-30">                 <div class="flex items-center">
                    <button id="mobileMenuButton" class="lg:hidden text-gray-600 hover:text-primary-blue mr-3 p-2" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative w-full max-w-xs hidden sm:block">
                        <input id="searchInput" type="text" placeholder="Buscar Oportunidades..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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

            <main id="main-content-area" class="flex-1 bg-light-bg p-6 md:p-8 space-y-8">                 <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <h1 class="text-3xl font-bold text-dark-text tracking-tight">Oportunidades</h1>
                    <div id="filterTags" class="flex flex-wrap gap-2 items-center">
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-primary-blue text-primary-blue transition-colors active" data-filter="todos" data-type-color="primary-blue">Todos</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-curso text-tag-curso transition-colors" data-filter="curso" data-type-color="tag-curso">Cursos</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-webinar text-tag-webinar transition-colors" data-filter="webinar" data-type-color="tag-webinar">Webinars</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-artigo text-tag-artigo transition-colors" data-filter="artigo" data-type-color="tag-artigo">Artigos</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-vaga text-tag-vaga transition-colors" data-filter="vaga" data-type-color="tag-vaga">Vagas</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-evento text-tag-evento transition-colors" data-filter="evento" data-type-color="tag-evento">Eventos</button>
                        <button class="filter-tag text-sm font-medium py-2 px-4 rounded-full border border-gray-300 hover:border-tag-outro text-tag-outro transition-colors" data-filter="outro" data-type-color="tag-outro">Outros</button>
                    </div>
                </div>
                
                <?php if ($erro_oportunidades): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md shadow-md" role="alert">
                        <div class="flex">
                            <div class="py-1"><svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zM9 5v6h2V5H9zm0 8v2h2v-2H9z"/></svg></div>
                            <div>
                                <p class="font-bold">Erro ao Carregar Oportunidades</p>
                                <p class="text-sm"><?= htmlspecialchars($erro_oportunidades); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <section id="opportunitiesContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                    </section>
                <div id="noResultsMessage" class="hidden text-center text-medium-text py-16">
                    <svg class="w-20 h-20 mx-auto text-gray-400 mb-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM13.5 10.5h-6" />
                    </svg>
                    <p class="text-2xl font-semibold text-dark-text mb-2">Nenhuma oportunidade encontrada.</p>
                    <p class="text-md text-light-text">Tente ajustar seus filtros ou volte mais tarde para novas atualizações.</p>
                </div>
            </main>
        </div>
    </div>

    <div id="opportunityModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full hidden items-center justify-center z-50 modal-overlay p-4">
        <div class="relative mx-auto p-6 sm:p-8 border-0 w-full max-w-2xl shadow-2xl rounded-xl bg-white modal-content">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-200">
                <h3 id="modalTitle" class="text-2xl leading-7 font-bold text-dark-text">Detalhes da Oportunidade</h3>
                <button id="closeModalButton" type="button" class="p-2 -m-2 text-gray-400 hover:text-gray-600 rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-blue transition-colors">
                    <span class="sr-only">Fechar modal</span>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="modalBody" class="mt-2 text-sm space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                <p><strong class="font-semibold text-medium-text block mb-0.5">Tipo:</strong> <span id="modalType" class="text-dark-text capitalize bg-gray-100 px-2 py-0.5 rounded-md text-xs font-medium"></span></p>
                <div class="space-y-1">
                    <strong class="font-semibold text-medium-text block mb-0.5">Descrição Completa:</strong> 
                    <div id="modalDescription" class="text-dark-text leading-relaxed whitespace-pre-wrap prose prose-sm max-w-none"></div>
                </div>
                <p><strong class="font-semibold text-medium-text block mb-0.5">Data/Prazo:</strong> <span id="modalDate" class="text-dark-text"></span></p>
                <p><strong class="font-semibold text-medium-text block mb-0.5">Fonte:</strong> <span id="modalSource" class="text-dark-text"></span></p>
                <div>
                    <strong class="font-semibold text-medium-text block mb-1">Tags:</strong> 
                    <div id="modalTagsContainer" class="flex flex-wrap gap-2"></div>
                </div>
            </div>
            <div class="mt-6 pt-5 border-t border-gray-200 flex flex-col sm:flex-row justify-end gap-3">
                 <button id="closeModalButtonSecondary" type="button" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg shadow-sm hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 transition-colors">
                    Fechar
                </button>
                <a id="modalActionLink" href="#" target="_blank" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 bg-primary-blue text-white text-sm font-semibold rounded-lg shadow-md hover:bg-primary-blue-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-blue transition-colors">
                    Acessar Oportunidade
                </a>
            </div>
        </div>
    </div>

    <script>
        const opportunitiesData = <?php echo json_encode($oportunidades_data); ?>;
        const currentUserId = <?php echo json_encode($userId); ?>;
        
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuButton = document.getElementById('userDropdownButton'); 
            const userMenuDropdown = document.getElementById('userDropdownMenu'); 
            const mobileMenuButton = document.getElementById('mobileMenuButton'); 
            const sidebar = document.getElementById('sidebar'); 
            const sidebarOverlay = document.getElementById('sidebar-overlay'); 
            
            const opportunitiesContainer = document.getElementById('opportunitiesContainer');
            const filterTagsContainer = document.getElementById('filterTags');
            const searchInput = document.getElementById('searchInput'); 
            const noResultsMessage = document.getElementById('noResultsMessage');

            const modal = document.getElementById('opportunityModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalType = document.getElementById('modalType');
            const modalDescription = document.getElementById('modalDescription');
            const modalDate = document.getElementById('modalDate');
            const modalSource = document.getElementById('modalSource');
            const modalTagsContainer = document.getElementById('modalTagsContainer');
            const modalActionLink = document.getElementById('modalActionLink');
            const closeModalButton = document.getElementById('closeModalButton');
            const closeModalButtonSecondary = document.getElementById('closeModalButtonSecondary');

            let currentFilter = 'todos';
            let searchTerm = '';

            function openOpportunityModal(opportunity) {
                if (!opportunity || !modal) { return; }
                modalTitle.textContent = opportunity.title;
                modalType.textContent = opportunity.type.replace('-', ' ');
                
                const typeColorName = opportunity.themeColorName || 'tag-outro'; // Fallback
                modalType.className = `text-xs font-semibold uppercase text-${typeColorName} bg-${typeColorName}/10 px-2 py-0.5 rounded-full inline-block`;
                
                modalDescription.innerHTML = opportunity.description ? opportunity.description.replace(/\n/g, '<br>') : 'Nenhuma descrição detalhada disponível.';
                modalDate.textContent = opportunity.date || 'Não informada';
                modalSource.textContent = opportunity.source || 'Não informada';
                
                modalTagsContainer.innerHTML = ''; 
                if (opportunity.tags && opportunity.tags.length > 0) {
                    opportunity.tags.forEach(tag => {
                        const tagElement = document.createElement('span');
                        tagElement.className = 'text-xs bg-gray-100 text-gray-800 px-2.5 py-1 rounded-full font-medium';
                        tagElement.textContent = tag;
                        modalTagsContainer.appendChild(tagElement);
                    });
                } else {
                    modalTagsContainer.innerHTML = '<span class="text-xs text-light-text italic">Nenhuma tag associada.</span>';
                }

                modalActionLink.href = opportunity.actionUrl || '#';
                modalActionLink.textContent = opportunity.actionText || 'Ver Detalhes';
                
                // Aplicar cor do tema ao botão de ação do modal
                const actionButtonColor = `bg-${typeColorName}`;
                const actionButtonHoverColor = `hover:bg-${typeColorName.split('-')[0]}-${parseInt(typeColorName.split('-')[1] || '600') + 100}`; // Heurística para hover
                const actionButtonRingColor = `focus:ring-${typeColorName}`;

                modalActionLink.className = `w-full sm:w-auto inline-flex justify-center items-center px-5 py-2.5 ${actionButtonColor} text-white text-sm font-semibold rounded-lg shadow-md ${actionButtonHoverColor} focus:outline-none focus:ring-2 focus:ring-offset-2 ${actionButtonRingColor} transition-all`;


                modal.classList.remove('hidden');
                modal.classList.add('flex'); 
                void modal.offsetWidth; 
                modal.querySelector('.modal-content').style.transform = 'scale(1) translateY(0)';
                modal.querySelector('.modal-content').style.opacity = '1';
// A própria variável "modal" é a overlay!
modal.style.opacity = '1';
                document.body.style.overflow = 'hidden'; 
            }

            function closeOpportunityModal() {
                if (!modal) return;
                modal.querySelector('.modal-content').style.transform = 'scale(0.95) translateY(10px)';
                modal.querySelector('.modal-content').style.opacity = '0';
modal.style.opacity = '0';
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = ''; 
                }, 300); 
            }

            if (closeModalButton) closeModalButton.addEventListener('click', closeOpportunityModal);
            if (closeModalButtonSecondary) closeModalButtonSecondary.addEventListener('click', closeOpportunityModal);
            if (modal) {
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) { closeOpportunityModal(); }
                });
            }
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeOpportunityModal();
                }
            });

            async function toggleFavorite(opportunityId, buttonElement) {
                const opportunity = opportunitiesData.find(op => op.id.toString() === opportunityId.toString());
                if (!opportunity) return;
                const action = opportunity.is_favorited ? 'remove' : 'add';
                
                console.log(`Simulando: ${action} favorito para ID ${opportunityId} por user ${currentUserId}`);
                // try {
                //     const response = await fetch('api/toggle_favorite_oportunidade.php', { 
                //         method: 'POST',
                //         headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
                //         body: `oportunidade_id=${opportunityId}&action=${action}`
                //     });
                //     const result = await response.json();
                //     if (result.success) {
                        opportunity.is_favorited = !opportunity.is_favorited; 
                        buttonElement.classList.toggle('favorited', opportunity.is_favorited);
                        buttonElement.classList.toggle('animate-pulse-heart', opportunity.is_favorited);
                        setTimeout(() => buttonElement.classList.remove('animate-pulse-heart'), 800);
                        const heartIconPath = opportunity.is_favorited 
                            ? "M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" 
                            : "M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"; 
                        buttonElement.querySelector('svg path').setAttribute('d', heartIconPath);
                        buttonElement.querySelector('svg').setAttribute('fill', opportunity.is_favorited ? 'currentColor' : 'none');
                        buttonElement.querySelector('svg').setAttribute('stroke-width', opportunity.is_favorited ? '0' : '1.5');
                //     } else { console.error("Falha:", result.message); }
                // } catch (error) { console.error("Erro AJAX favorito:", error); }
            }

            function renderOpportunities() {
                if (!opportunitiesContainer) return;
                opportunitiesContainer.innerHTML = ''; 
                let filteredOpportunities = opportunitiesData;

                if (currentFilter !== 'todos') {
                    filteredOpportunities = filteredOpportunities.filter(op => op.type === currentFilter);
                }
                if (searchTerm) {
                    const lowerSearchTerm = searchTerm.toLowerCase();
                    filteredOpportunities = filteredOpportunities.filter(op => 
                        op.title.toLowerCase().includes(lowerSearchTerm) ||
                        op.description.toLowerCase().includes(lowerSearchTerm) ||
                        (op.source && op.source.toLowerCase().includes(lowerSearchTerm)) ||
                        (op.tags && op.tags.some(tag => tag.toLowerCase().includes(lowerSearchTerm)))
                    );
                }
                
                if (filteredOpportunities.length === 0) {
                    if(noResultsMessage) noResultsMessage.classList.remove('hidden');
                    if(opportunitiesContainer) opportunitiesContainer.classList.remove('grid'); 
                } else {
                    if(noResultsMessage) noResultsMessage.classList.add('hidden');
                    if(opportunitiesContainer) opportunitiesContainer.classList.add('grid'); 
                }

                filteredOpportunities.forEach((op, index) => {
                    const cardClasses = `bg-card-bg p-5 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 flex flex-col gap-3 content-item-animated relative ${op.is_featured ? 'card-featured' : ''}`;
                    const heartIconPath = op.is_favorited 
                        ? "M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" 
                        : "M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"; 
                    
                    const iconBgClass = `bg-${op.themeColorName}/10`; 
                    const iconTextClass = `text-${op.themeColorName}`;
                    const actionButtonBgClass = `bg-${op.themeColorName}`;
                    const actionButtonHoverBgClass = `hover:bg-${op.themeColorName.split('-')[0]}-${parseInt(op.themeColorName.split('-')[1] || '600') + 100}`; 
                    const actionButtonRingClass = `focus:ring-${op.themeColorName}`;


                    const card = `
                        <div class="${cardClasses}" style="animation-name: fadeInUp; animation-delay: ${index * 0.07}s;">
                            ${op.is_featured ? '<span class="featured-badge">Destaque</span>' : ''}
                            <div class="flex items-start justify-between">
                                <div class="p-3 ${iconBgClass} rounded-lg flex-shrink-0 mr-4">
                                    ${op.iconSvg.replace('class="w-7 h-7"', `class="w-7 h-7 ${iconTextClass}"`)} 
                                </div>
                                <button 
                                    type="button" 
                                    data-opportunity-id="${op.id}" 
                                    class="favorite-button text-gray-400 hover:text-red-500 p-1.5 rounded-full ${op.is_favorited ? 'favorited' : ''}"
                                    aria-label="Marcar como favorito"
                                >
                                    <svg class="w-6 h-6" fill="${op.is_favorited ? 'currentColor' : 'none'}" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="${op.is_favorited ? 0 : 1.5}">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="${heartIconPath}"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex-grow">
                                <span class="text-xs font-bold uppercase ${iconTextClass} tracking-wider">${op.type.replace('-', ' ')}</span>
                                <h3 class="text-lg font-semibold text-dark-text mt-1 mb-1.5 group-hover:text-primary-blue transition-colors">${op.title}</h3>
                                <p class="text-sm text-medium-text mb-3 leading-relaxed line-clamp-3">${op.short_description}</p>
                                <div class="text-xs text-light-text mb-3 space-y-1">
                                    ${op.date ? `<div class="flex items-center"><svg class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> <strong>Data/Prazo:</strong>&nbsp;${op.date}</div>` : ''}
                                    ${op.source ? `<div class="flex items-center"><svg class="w-3.5 h-3.5 mr-1.5 text-gray-400" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> <strong>Fonte:</strong>&nbsp;${op.source}</div>` : ''}
                                </div>
                                ${op.tags && op.tags.length > 0 ? `
                                <div class="flex flex-wrap gap-1.5 mb-4">
                                    ${op.tags.map(tag => `<span class="text-xs bg-gray-100 text-medium-text px-2.5 py-1 rounded-full font-medium">${tag}</span>`).join('')}
                                </div>
                                ` : ''}
                            </div>
                            <button type="button" data-opportunity-id="${op.id}" class="opportunity-details-button w-full mt-auto ${actionButtonBgClass} text-white font-medium py-2.5 px-5 rounded-lg ${actionButtonHoverBgClass} transition-all text-sm whitespace-nowrap shadow-md hover:shadow-lg focus:ring-2 ${actionButtonRingClass} focus:ring-opacity-50">
                                ${op.actionText}
                            </button>
                        </div>
                    `;
                    opportunitiesContainer.insertAdjacentHTML('beforeend', card);
                });

                document.querySelectorAll('.opportunity-details-button').forEach(button => {
                    button.addEventListener('click', function() {
                        const opportunityId = this.dataset.opportunityId;
                        const selectedOpportunity = opportunitiesData.find(op => op.id.toString() === opportunityId);
                        if (selectedOpportunity) openOpportunityModal(selectedOpportunity);
                    });
                });
                document.querySelectorAll('.favorite-button').forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation(); 
                        const opportunityId = this.dataset.opportunityId;
                        toggleFavorite(opportunityId, this);
                    });
                });
                
                const animatedElements = opportunitiesContainer.querySelectorAll('.content-item-animated:not(.animated)');
                animatedElements.forEach(el => {
                    el.style.opacity = '0'; 
                    setTimeout(() => { 
                        el.style.opacity = '1';
                        el.style.animationPlayState = 'running';
                        el.classList.add('animated'); 
                    }, 10); 
                });
            }

            if (filterTagsContainer) {
                filterTagsContainer.querySelectorAll('.filter-tag').forEach(tag => {
                     // Set initial hover/text colors based on data-type-color
                    const typeColor = tag.dataset.typeColor || 'primary-blue';
                    if (!tag.classList.contains('active')) {
                        tag.classList.add(`hover:border-${typeColor}`, `text-${typeColor}`);
                    }

                    tag.addEventListener('click', function(event) {
                        filterTagsContainer.querySelectorAll('.filter-tag').forEach(innerTag => {
                            innerTag.classList.remove('active', 'bg-primary-blue', 'text-white', 'border-primary-blue');
                            const innerTypeColor = innerTag.dataset.typeColor || 'primary-blue';
                            // Re-apply specific hover/text if not active
                            innerTag.classList.add(`hover:border-${innerTypeColor}`, `text-${innerTypeColor}`);
                        });
                        
                        event.target.classList.add('active', 'bg-primary-blue', 'text-white', 'border-primary-blue');
                        // Remove specific type hover when active
                        event.target.classList.remove(`hover:border-${typeColor}`, `text-${typeColor}`);
                        
                        currentFilter = event.target.dataset.filter;
                        renderOpportunities();
                    });
                });
            }


            if (searchInput) {
                let searchDebounceTimer;
                searchInput.addEventListener('input', function(event) {
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(() => {
                        searchTerm = event.target.value;
                        renderOpportunities();
                    }, 300); 
                });
            }

            if (userMenuButton && userMenuDropdown) {
                userMenuButton.addEventListener('click', (e) => {
                    e.stopPropagation(); 
                    userMenuDropdown.classList.toggle('hidden');
                });
            }
            document.addEventListener('click', (event) => {
                if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden') &&
                    userMenuButton && !userMenuButton.contains(event.target) && 
                    !userMenuDropdown.contains(event.target)) {
                    userMenuDropdown.classList.add('hidden');
                }
            });
            
            if (mobileMenuButton && sidebar && sidebarOverlay) {
                 mobileMenuButton.addEventListener('click', () => {
                    sidebar.classList.add('open');
                    sidebar.classList.remove('left-[-256px]');
                    sidebar.classList.add('left-0');
                    sidebarOverlay.classList.remove('hidden');
                });
            }
            function closeMobileNav() {
                if (sidebar && sidebarOverlay) {
                    sidebar.classList.remove('open');
                    sidebar.classList.add('left-[-256px]');
                    sidebar.classList.remove('left-0');
                    sidebarOverlay.classList.add('hidden');
                }
            }
            
            const closeMobileMenuBtnInternal = document.getElementById('closeMobileMenuButton'); 
            if (closeMobileMenuBtnInternal) { 
                closeMobileMenuBtnInternal.addEventListener('click', closeMobileNav);
            }
             if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileNav);
            }

             const navLinks = document.querySelectorAll('#mainNav a'); 
             navLinks.forEach(link => {
                const spanElement = link.querySelector('span');
                if (spanElement && spanElement.textContent.trim().toLowerCase() === 'oportunidades') {
                    link.classList.add('active-nav-link');
                } else {
                    link.classList.remove('active-nav-link');
                }
            });

            if (opportunitiesData && opportunitiesData.length > 0) {
                 renderOpportunities();
            } else if (!<?php echo json_encode($erro_oportunidades ? true : false); ?>) { 
                 if(noResultsMessage) noResultsMessage.classList.remove('hidden');
                 if(opportunitiesContainer) opportunitiesContainer.classList.remove('grid');
            }
        });

        function toggleMobileSidebar() { 
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('open');
                sidebar.classList.toggle('left-[-256px]');
                sidebar.classList.toggle('left-0');
                overlay.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>
<?php
ob_end_flush(); // Enviar o buffer de saída
?>
