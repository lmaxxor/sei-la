<?php
// inicio.php (Dashboard Principal do Utilizador)

// 1. Incluir o gestor de sessões
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Redireciona para login se não estiver logado

// 3. Incluir a conexão com o banco de dados
require_once __DIR__ . '/db/db_connect.php';

$pageTitle = "Início - AudioTO";

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userFirstName = explode(' ', $userName)[0];
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;

// --- DETERMINAR STATUS DE ASSINANTE/ADMIN ---
$is_admin = (isset($_SESSION['user_funcao']) && $_SESSION['user_funcao'] === 'administrador');
$user_active_plan_id = $_SESSION['user_plano_id'] ?? null; // ID do plano ativo do utilizador

// Assume-se que planos pagos têm user_plano_id > 0. 
// Se '0' ou um ID específico representa um plano gratuito, ajuste a lógica.
$is_subscriber = ($user_active_plan_id !== null && $user_active_plan_id > 0); 

$is_subscriber_or_admin = $is_admin || $is_subscriber;


// Function to get user avatar
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL) && strlen(trim($avatar_url_from_session)) > 10) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2760f3&color=fff&size={$size}&rounded=true&bold=true";
}

$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);
$avatarUrlSmall = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 36);

// --- LÓGICA PARA BUSCAR DADOS DO DASHBOARD ---

// 5.1. Continuar Ouvindo
$continue_listening_db = [];
$erro_continue_listening = null;
if ($userId) {
    try {
        $stmt_cl = $pdo->prepare(
            "SELECT
                p.id_podcast, p.titulo_podcast, p.slug_podcast,
                ap.nome_assunto as episodio_nome,
                p.imagem_capa_url, p.duracao_total_segundos,
                pr.posicao_segundos
            FROM posicao_reproducao_utilizador pr
            JOIN podcasts p ON pr.id_podcast = p.id_podcast
            JOIN assuntos_podcast ap ON p.id_assunto = ap.id_assunto
            WHERE pr.id_utilizador = :userId
              AND pr.posicao_segundos > 0
              AND (p.duracao_total_segundos IS NULL OR p.duracao_total_segundos = 0 OR pr.posicao_segundos < p.duracao_total_segundos)
            ORDER BY pr.data_atualizacao DESC
            LIMIT 3"
        );
        $stmt_cl->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_cl->execute();
        $results_cl = $stmt_cl->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results_cl as $row) {
            $progress = 0;
            if (!empty($row['duracao_total_segundos']) && $row['duracao_total_segundos'] > 0) {
                $progress = round(($row['posicao_segundos'] / $row['duracao_total_segundos']) * 100);
            } else {
                $progress = $row['posicao_segundos'] > 0 ? 5 : 0;
            }
            $imageUrlCl = $row['imagem_capa_url'] ?? "https://placehold.co/150x150/BFDBFE/60A5FA?text=" . urlencode(substr($row['titulo_podcast'],0,3));
            $continue_listening_db[] = [
                'title' => htmlspecialchars($row['titulo_podcast']),
                'image' => $imageUrlCl,
                'progress' => $progress,
                'episode' => htmlspecialchars($row['episodio_nome']),
                'slug' => htmlspecialchars($row['slug_podcast'])
            ];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar 'Continuar Ouvindo': " . $e->getMessage());
        $erro_continue_listening = "Não foi possível carregar os podcasts para continuar ouvindo.";
    }
}

// 5.2. Categorias de Podcast
$categorias_podcast_dashboard = [];
$erro_categorias = null;
try {
    $stmt_cat = $pdo->query(
        "SELECT id_categoria, nome_categoria, slug_categoria, icone_categoria, cor_icone,
            (SELECT COUNT(*) FROM assuntos_podcast WHERE id_categoria = cp.id_categoria) as num_assuntos
         FROM categorias_podcast cp
         ORDER BY nome_categoria ASC
         LIMIT 4"
    );
    $categorias_podcast_dashboard = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar categorias para dashboard: " . $e->getMessage());
    $erro_categorias = "Não foi possível carregar as categorias de podcast.";
}

// 5.3. Últimas Oportunidades
$ultimas_oportunidades = [];
$erro_oportunidades = null;
try {
    $stmt_op = $pdo->query(
        "SELECT id_oportunidade, tipo_oportunidade, titulo_oportunidade,
            SUBSTRING(descricao_oportunidade, 1, 100) as descricao_curta,
            link_oportunidade, data_publicacao
         FROM oportunidades
         WHERE ativo = TRUE
         ORDER BY data_publicacao DESC
         LIMIT 3"
    );
    $ultimas_oportunidades = $stmt_op->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar oportunidades para dashboard: " . $e->getMessage());
    $erro_oportunidades = "Não foi possível carregar as últimas oportunidades.";
}

// 5.4. Recomendados para Você (MODIFICADO)
$recomendados_db = [];
$erro_recomendados = null;
try {
    $sql_rec_base = "SELECT
                        p.titulo_podcast, p.slug_podcast, p.imagem_capa_url,
                        p.visibilidade, -- Importante para saber se é premium
                        cp.nome_categoria as autor_podcast
                    FROM podcasts p
                    JOIN assuntos_podcast ap ON p.id_assunto = ap.id_assunto
                    JOIN categorias_podcast cp ON ap.id_categoria = cp.id_categoria";

    // Mostrar públicos E restritos (premium) para todos na listagem.
    // O controlo de acesso real ocorre no clique (JS opcional) / na página do player (mandatório).
    $sql_rec_where_conditions = "p.visibilidade IN ('publico', 'restrito_assinantes')";
    
    // Filtrar para não mostrar podcasts privados, caso existam e não sejam tratados em outro lugar
    $sql_rec_where_conditions .= " AND p.visibilidade <> 'privado'";


    $sql_rec = $sql_rec_base . " WHERE " . $sql_rec_where_conditions;
    $sql_rec .= " ORDER BY p.data_publicacao DESC LIMIT 5"; // Ou outra ordem relevante

    $stmt_rec = $pdo->prepare($sql_rec);
    $stmt_rec->execute();
    $results_rec = $stmt_rec->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results_rec as $rec) {
        $imageUrlRec = $rec['imagem_capa_url'] ?? "https://placehold.co/200x280/FEF3C7/FBBF24?text=" . urlencode(substr($rec['titulo_podcast'],0,3));
        
        // Determina se o podcast é premium (restrito a assinantes)
        $is_premium_podcast = ($rec['visibilidade'] === 'restrito_assinantes');

        $recomendados_db[] = [
            'title' => htmlspecialchars($rec['titulo_podcast']),
            'image' => $imageUrlRec,
            'author' => htmlspecialchars($rec['autor_podcast']),
            'slug' => htmlspecialchars($rec['slug_podcast']),
            'is_premium' => $is_premium_podcast, // Adiciona flag para uso no HTML
            'visibilidade' => $rec['visibilidade'] 
        ];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar recomendados: " . $e->getMessage());
    $erro_recomendados = "Não foi possível carregar as recomendações.";
}


// 5.5. Em Alta na AudioTO
$trending_podcasts_db = [];
$erro_trending = null;
try {
    $stmt_trend = $pdo->query(
        "SELECT
            p.titulo_podcast, p.slug_podcast, p.imagem_capa_url,
            cp.nome_categoria, p.total_curtidas
        FROM podcasts p
        JOIN assuntos_podcast ap ON p.id_assunto = ap.id_assunto
        JOIN categorias_podcast cp ON ap.id_categoria = cp.id_categoria
        WHERE p.visibilidade <> 'privado' -- Adicionado para não mostrar privados
        ORDER BY p.total_curtidas DESC, p.data_publicacao DESC
        LIMIT 4"
    );
    $results_trend = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results_trend as $trend) {
        $imageUrlTrend = $trend['imagem_capa_url'] ?? "https://placehold.co/150x150/8B5CF6/FFFFFF?text=" . urlencode(substr($trend['titulo_podcast'],0,2));
        $listeners = $trend['total_curtidas'] ?? 0;
        $listeners_formatted = $listeners >= 1000 ? round($listeners / 1000, 1) . 'k' : (string)$listeners;

        $trending_podcasts_db[] = [
            'title' => htmlspecialchars($trend['titulo_podcast']),
            'image' => $imageUrlTrend,
            'category' => htmlspecialchars($trend['nome_categoria']),
            'listeners' => $listeners_formatted,
            'slug' => htmlspecialchars($trend['slug_podcast'])
        ];
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar 'Em Alta': " . $e->getMessage());
    $erro_trending = "Não foi possível carregar os podcasts em alta.";
}

// 5.6. Seu Resumo Semanal
$resumo_semanal = [
    'tempo_ouvindo' => '0h',
    'novos_episodios_ouvidos' => 0,
    'podcasts_concluidos' => 0,
    'novos_favoritos' => 0
];
$erro_resumo = null;

if ($userId) {
    try {
        $umaSemanaAtras = date('Y-m-d H:i:s', strtotime('-7 days'));

        $stmt_fav = $pdo->prepare("SELECT COUNT(*) as total FROM favoritos WHERE id_utilizador = :userId AND data_favoritado >= :umaSemanaAtras");
        $stmt_fav->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_fav->bindParam(':umaSemanaAtras', $umaSemanaAtras, PDO::PARAM_STR);
        $stmt_fav->execute();
        $resumo_semanal['novos_favoritos'] = $stmt_fav->fetchColumn() ?: 0;

        $stmt_conc = $pdo->prepare(
            "SELECT COUNT(DISTINCT pr.id_podcast) as total
            FROM posicao_reproducao_utilizador pr
            JOIN podcasts p ON pr.id_podcast = p.id_podcast
            WHERE pr.id_utilizador = :userId
              AND p.duracao_total_segundos > 0 AND pr.posicao_segundos >= p.duracao_total_segundos
              AND pr.data_atualizacao >= :umaSemanaAtras"
        );
        $stmt_conc->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_conc->bindParam(':umaSemanaAtras', $umaSemanaAtras, PDO::PARAM_STR);
        $stmt_conc->execute();
        $resumo_semanal['podcasts_concluidos'] = $stmt_conc->fetchColumn() ?: 0;

        $stmt_prog = $pdo->prepare(
            "SELECT COUNT(DISTINCT id_podcast) as total
            FROM posicao_reproducao_utilizador
            WHERE id_utilizador = :userId AND data_atualizacao >= :umaSemanaAtras AND posicao_segundos > 0"
        );
        $stmt_prog->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_prog->bindParam(':umaSemanaAtras', $umaSemanaAtras, PDO::PARAM_STR);
        $stmt_prog->execute();
        $resumo_semanal['novos_episodios_ouvidos'] = $stmt_prog->fetchColumn() ?: 0;

        $stmt_tempo = $pdo->prepare(
            "SELECT SUM(pr.posicao_segundos) as total_segundos_ouvidos_recentes
             FROM posicao_reproducao_utilizador pr
             WHERE pr.id_utilizador = :userId AND pr.data_atualizacao >= :umaSemanaAtras"
        );
        $stmt_tempo->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_tempo->bindParam(':umaSemanaAtras', $umaSemanaAtras, PDO::PARAM_STR);
        $stmt_tempo->execute();
        $total_segundos_ouvidos = $stmt_tempo->fetchColumn() ?: 0;
        
        if ($total_segundos_ouvidos > 0) {
            $horas = floor($total_segundos_ouvidos / 3600);
            $minutos = floor(($total_segundos_ouvidos % 3600) / 60);
            if ($horas > 0) {
                $resumo_semanal['tempo_ouvindo'] = $horas . 'h';
                if ($minutos > 0) {
                    $resumo_semanal['tempo_ouvindo'] .= ' ' . $minutos . 'm';    
                }
            } elseif ($minutos > 0) {
                $resumo_semanal['tempo_ouvindo'] = $minutos . 'm';
            } else if ($total_segundos_ouvidos > 0 && $total_segundos_ouvidos < 60) {
                $resumo_semanal['tempo_ouvindo'] = round($total_segundos_ouvidos) . 's';
            } else { 
                $resumo_semanal['tempo_ouvindo'] = '0m';
            }
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar resumo semanal: " . $e->getMessage());
        $erro_resumo = "Não foi possível carregar o resumo semanal.";
    }
}
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
                        'primary-blue-light': '#e0eaff',
                        'primary-blue-dark': '#1e40af',
                        'brand-banner-start': '#6ee7b7',
                        'brand-banner-end': '#3b82f6',
                        'light-bg': '#f7fafc',
                        'card-bg': '#ffffff',
                        'dark-text': '#1f2937',
                        'medium-text': '#4b5563',
                        'light-text': '#6b7280',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b', // Usado para badge premium
                        'info': '#3b82f6',
                        'tag-curso': '#34D399',
                        'tag-webinar': '#2563eb',
                        'tag-artigo': '#FBBF24',
                        'tag-vaga': '#F87171',
                        'tag-evento': '#3b82f6',
                        'tag-outro': '#60a5fa',
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'modal-scale-in': 'modalScaleIn 0.3s ease-out forwards',
                        'modal-scale-out': 'modalScaleOut 0.3s ease-in forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(15px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        modalScaleIn: {
                            '0%': { opacity: '0', transform: 'scale(0.95)' },
                            '100%': { opacity: '1', transform: 'scale(1)' },
                        },
                        modalScaleOut: {
                            '0%': { opacity: '1', transform: 'scale(1)' },
                            '100%': { opacity: '0', transform: 'scale(0.95)' },
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
        .form-element-animated { opacity: 0; animation-fill-mode: forwards; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400'); }
        .category-card-icon-bg { width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 9999px; margin-bottom: 1rem; }
        .category-card-icon-bg i, .category-card-icon-bg svg { width: 28px; height: 28px; }
        .podcast-card-sm img { aspect-ratio: 1 / 1; }
        .progress-bar-container { background-color: theme('colors.gray.200'); border-radius: 9999px; overflow: hidden; height: 6px;}
        .progress-bar { background-color: theme('colors.primary-blue'); height: 100%; border-radius: 9999px; transition: width 0.3s ease-in-out; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .premium-badge {
            position: absolute;
            top: 0.5rem; /* 8px */
            right: 0.5rem; /* 8px */
           background-color: goldenrod;
    color: cornsilk; 
            font-size: 0.65rem; /* 10px */
            font-weight: 700;
            padding: 0.2rem 0.5rem; /* Ajuste de padding */
            border-radius: 9999px; /* pill shape */
            z-index: 10;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
         /* Estilos para o Modal */
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.6);
            transition: opacity 0.3s ease-in-out;
        }
        .modal-dialog {
            max-width: 450px; /* Largura máxima do modal */
            width: calc(100% - 2rem); /* Responsivo com margens laterais */
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }
        .modal-overlay.hidden .modal-dialog {
            transform: scale(0.95);
            opacity: 0;
        }
        .modal-overlay:not(.hidden) .modal-dialog {
            transform: scale(1);
            opacity: 1;
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
                 <a href="noticias.php" class="flex items-center space-x-3 px-3 py-2.5 text-gray-700 rounded-lg hover:bg-primary-blue-light hover:text-primary-blue <?= ($activePage === 'noticias') ? 'active-nav-link' : '' ?>">
                    <i class="fas fa-newspaper sidebar-icon"></i> <span class="text-sm font-medium">Notícias</span>
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
                    <button id="mobileMenuButton" aria-label="Abrir menu lateral" class="lg:hidden text-gray-600 hover:text-primary-blue mr-3 p-2" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative w-full max-w-xs hidden sm:block">
                        <label for="search-audioto" class="sr-only">Buscar em AudioTO</label>
                        <input type="text" id="search-audioto" placeholder="Buscar em AudioTO..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
                        <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button aria-label="Notificações" class="text-gray-500 hover:text-primary-blue relative p-2">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if(true): // Lógica de notificação real aqui ?>
                        <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-primary-blue ring-1 ring-white">
                            <span class="sr-only">Novas notificações</span>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="relative">
                        <button id="userDropdownButton" aria-label="Menu do usuário" aria-haspopup="true" aria-expanded="false" class="flex items-center space-x-2 focus:outline-none">
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar de <?php echo htmlspecialchars($userName); ?>" class="w-9 h-9 rounded-full border-2 border-primary-blue-light">
                            <div class="hidden md:block text-left">
                                <p class="text-xs font-medium text-dark-text"><?php echo htmlspecialchars($userName); ?></p>
                                <p class="text-xs text-light-text"><?php echo htmlspecialchars($userEmail); ?></p>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-gray-500 hidden md:block ml-1"></i>
                        </button>
                        <div id="userDropdownMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl z-20 py-1 border border-gray-200" role="menu" aria-orientation="vertical" aria-labelledby="userDropdownButton">
                            <a href="perfil.php" role="menuitem" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Meu Perfil</a>
                            <a href="configuracoes.php" role="menuitem" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Configurações</a>
                            <hr class="my-1 border-gray-200">
                            <a href="logout.php" role="menuitem" class="block px-4 py-2 text-sm text-gray-700 hover:bg-primary-blue-light hover:text-primary-blue">Sair</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-light-bg p-5 md:p-7 space-y-7">
                <section class="bg-gradient-to-r from-brand-banner-end to-brand-banner-start text-white p-6 sm:p-8 rounded-xl shadow-lg form-element-animated" style="animation-name: fadeInUp;">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl sm:text-3xl font-semibold">👋 Bem-vindo(a) de volta, <?php echo htmlspecialchars($userFirstName); ?>!</h2>
                            <p class="mt-1 text-sm sm:text-base opacity-90">Pronto(a) para expandir os seus conhecimentos hoje?</p>
                        </div>
                        <i class="fas fa-headphones-alt text-4xl sm:text-5xl opacity-75 hidden sm:block p-3 bg-white/20 rounded-full"></i>
                    </div>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.1s;">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-dark-text">Continuar Ouvindo</h3>
                        <?php if (!empty($continue_listening_db)): ?>
                            <a href="historico.php" class="text-xs font-medium text-primary-blue hover:underline">Ver Histórico &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php if ($erro_continue_listening): ?>
                            <p class="col-span-full text-center text-danger py-6 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_continue_listening); ?></p>
                        <?php elseif (!empty($continue_listening_db)): ?>
                            <?php foreach($continue_listening_db as $item): ?>
                            <a href="player_podcast.php?slug=<?php echo $item['slug']; ?>" class="bg-card-bg p-4 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 flex space-x-3 items-center group">
                                <img src="<?php echo $item['image']; ?>" alt="Capa de <?php echo htmlspecialchars($item['title']); ?>" class="w-16 h-16 rounded-md object-cover podcast-card-sm flex-shrink-0">
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-dark-text truncate group-hover:text-primary-blue"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p class="text-xs text-light-text truncate"><?php echo htmlspecialchars($item['episode']); ?></p>
                                    <div class="progress-bar-container mt-2">
                                        <div class="progress-bar" style="width: <?php echo $item['progress']; ?>%;" aria-valuenow="<?php echo $item['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="col-span-full text-center text-medium-text py-6 bg-gray-50 p-4 rounded-md">Você ainda não começou a ouvir nenhum podcast.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.2s;">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-dark-text">Navegar por Categorias</h3>
                        <?php if (!empty($categorias_podcast_dashboard)): ?>
                            <a href="podcasts.php#categorias" class="text-xs font-medium text-primary-blue hover:underline">Todas as Categorias &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($erro_categorias): ?>
                        <p class="text-center text-danger py-6 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_categorias); ?></p>
                    <?php elseif (!empty($categorias_podcast_dashboard)): ?>
                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach ($categorias_podcast_dashboard as $cat):
                                $bgColorHex = $cat['cor_icone'] ?? '#db2777';
                                $iconColorHex = $cat['cor_icone'] ?? '#db2777';
                                if (strpos($bgColorHex, '#') === 0 && strlen($bgColorHex) === 7) {
                                    $bgColorWithOpacity = $bgColorHex . '22'; // Adiciona opacidade (ex: #RRGGBB22)
                                } else {
                                    $bgColorWithOpacity = 'rgba(219, 39, 119, 0.13)'; // Cor rosa padrão com opacidade se não for hex válido
                                }
                            ?>
                            <a href="podcasts.php?categoria=<?php echo htmlspecialchars($cat['slug_categoria']); ?>" class="category-card bg-card-bg p-4 rounded-lg shadow hover:shadow-lg transition-all duration-200 flex flex-col items-center text-center transform hover:-translate-y-1 group">
                                <div class="category-card-icon-bg mb-3" style="background-color: <?php echo htmlspecialchars($bgColorWithOpacity); ?>;">
                                    <?php if (!empty($cat['icone_categoria']) && strpos($cat['icone_categoria'], '<svg') !== false): echo $cat['icone_categoria']; // Renderiza SVG diretamente
                                        elseif (!empty($cat['icone_categoria'])): ?>
                                        <i class="<?php echo htmlspecialchars($cat['icone_categoria']); ?>" style="color: <?php echo htmlspecialchars($iconColorHex); ?>;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-podcast" style="color: <?php echo htmlspecialchars($iconColorHex); ?>;"></i>
                                    <?php endif; ?>
                                </div>
                                <h4 class="text-sm font-semibold text-dark-text group-hover:text-primary-blue"><?php echo htmlspecialchars($cat['nome_categoria']); ?></h4>
                                <p class="text-xs text-light-text">(<?php echo $cat['num_assuntos']; ?> Assuntos)</p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-medium-text py-6 bg-gray-50 p-4 rounded-md">Nenhuma categoria de podcast disponível.</p>
                    <?php endif; ?>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.3s;">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-dark-text">Recomendados para Você</h3>
                        <?php if (!empty($recomendados_db)): ?>
                            <a href="recomendacoes.php" class="text-xs font-medium text-primary-blue hover:underline">Ver Mais &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <div class="flex space-x-4 overflow-x-auto pb-3 -mb-3 scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                        <?php if ($erro_recomendados): ?>
                            <p class="w-full text-center text-danger py-6 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_recomendados); ?></p>
                        <?php elseif (!empty($recomendados_db)): ?>
                            <?php foreach ($recomendados_db as $rec): ?>
                                <?php
                                    $podcast_slug_url = urlencode($rec['slug']);
                                    $podcast_title_attr = htmlspecialchars($rec['title']);
                                    $is_item_premium = $rec['is_premium'];

                                    $link_href = "player_podcast.php?slug={$podcast_slug_url}";
                                    $link_onclick_js = ""; // Será preenchido pelo JS se necessário
                                    $premium_badge_html = "";
                                    $data_attributes = "";

                                    if ($is_item_premium) {
                                        $premium_badge_html = '<span class="premium-badge"><i class="fas fa-crown mr-1 text-xs"></i>Premium</span>';
                                        if (!$is_subscriber_or_admin) {
                                            // Adiciona atributos para o JS identificar e mostrar o modal
                                            $data_attributes = " data-premium='true' data-requires-upgrade='true' ";
                                        }
                                    }
                                ?>
                                <a href="<?php echo $link_href; ?>"
                                   <?php echo $data_attributes; ?>
                                   class="flex-shrink-0 w-36 sm:w-40 group relative">
                                    <div class="bg-card-bg rounded-lg shadow hover:shadow-lg transition-shadow duration-200 overflow-hidden">
                                        <?php echo $premium_badge_html; ?>
                                        <img src="<?php echo $rec['image']; ?>" alt="Capa de <?php echo $podcast_title_attr; ?>" class="w-full h-48 sm:h-52 object-cover">
                                        <div class="p-3">
                                            <h4 class="text-xs font-semibold text-dark-text truncate group-hover:text-primary-blue"><?php echo htmlspecialchars($rec['title']); ?></h4>
                                            <p class="text-xs text-light-text truncate"><?php echo htmlspecialchars($rec['author']); ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="w-full text-center text-medium-text py-6 bg-gray-50 p-4 rounded-md">Nenhuma recomendação disponível no momento.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.4s;">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold text-dark-text">Fique por Dentro: Oportunidades</h3>
                        <?php if (!empty($ultimas_oportunidades)): ?>
                            <a href="oportunidades.php" class="text-xs font-medium text-primary-blue hover:underline">Todas as Oportunidades &rarr;</a>
                        <?php endif; ?>
                    </div>
                    <?php if ($erro_oportunidades): ?>
                        <p class="text-center text-danger py-6 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_oportunidades); ?></p>
                    <?php elseif (!empty($ultimas_oportunidades)): ?>
                        <div class="space-y-4">
                            <?php
                            $tipo_cores_bg_op = ['curso' => 'bg-tag-curso/20', 'webinar' => 'bg-tag-webinar/20', 'artigo' => 'bg-tag-artigo/20', 'vaga' => 'bg-tag-vaga/20', 'evento' => 'bg-tag-evento/20', 'outro' => 'bg-tag-outro/20'];
                            $tipo_cores_text_op = ['curso' => 'text-tag-curso', 'webinar' => 'text-tag-webinar', 'artigo' => 'text-tag-artigo', 'vaga' => 'text-tag-vaga', 'evento' => 'text-tag-evento', 'outro' => 'text-tag-outro'];
                            $tipo_icones_fa = ['curso' => 'fas fa-graduation-cap', 'webinar' => 'fas fa-chalkboard-teacher', 'artigo' => 'far fa-newspaper', 'vaga' => 'fas fa-briefcase', 'evento' => 'far fa-calendar-alt', 'outro' => 'fas fa-info-circle'];
                            foreach ($ultimas_oportunidades as $op):
                                $tipo_slug = strtolower($op['tipo_oportunidade'] ?? 'outro');
                                $cor_bg = $tipo_cores_bg_op[$tipo_slug] ?? $tipo_cores_bg_op['outro'];
                                $cor_text = $tipo_cores_text_op[$tipo_slug] ?? $tipo_cores_text_op['outro'];
                                $icone_fa = $tipo_icones_fa[$tipo_slug] ?? $tipo_icones_fa['outro'];
                            ?>
                            <a href="<?php echo htmlspecialchars($op['link_oportunidade'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer" class="block bg-card-bg p-4 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 group">
                                <div class="flex items-start space-x-3">
                                    <div class="p-2.5 <?php echo $cor_bg; ?> <?php echo $cor_text; ?> rounded-full mt-0.5 flex-shrink-0">
                                        <i class="<?php echo $icone_fa; ?> text-base w-5 h-5 text-center"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-start">
                                            <h4 class="text-sm font-semibold text-dark-text group-hover:text-primary-blue flex-grow pr-2"><?php echo htmlspecialchars($op['titulo_oportunidade']); ?></h4>
                                            <span class="text-xs font-medium uppercase <?php echo $cor_text; ?> <?php echo $cor_bg; ?> px-1.5 py-0.5 rounded-full ml-2 flex-shrink-0 whitespace-nowrap">
                                                <?php echo htmlspecialchars(ucfirst($op['tipo_oportunidade'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-xs text-medium-text mt-0.5 line-clamp-2"><?php echo htmlspecialchars($op['descricao_curta']); ?></p>
                                        <p class="text-xs text-light-text mt-1.5"><?php echo date("d M, Y", strtotime($op['data_publicacao'])); ?></p>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-medium-text py-6 bg-gray-50 p-4 rounded-md">Nenhuma oportunidade recente disponível.</p>
                    <?php endif; ?>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.5s;">
                    <h3 class="text-xl font-semibold text-dark-text mb-4">Em Alta na AudioTO</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <?php if ($erro_trending): ?>
                            <p class="col-span-full text-center text-danger py-6 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_trending); ?></p>
                        <?php elseif(!empty($trending_podcasts_db)): ?>
                            <?php foreach($trending_podcasts_db as $trend): ?>
                            <a href="player_podcast.php?slug=<?php echo $trend['slug']; ?>" class="bg-card-bg p-3 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 group">
                                <img src="<?php echo $trend['image']; ?>" alt="Capa de <?php echo htmlspecialchars($trend['title']); ?>" class="w-full h-32 rounded-md object-cover mb-2 podcast-card-sm">
                                <h4 class="text-sm font-semibold text-dark-text truncate group-hover:text-primary-blue"><?php echo htmlspecialchars($trend['title']); ?></h4>
                                <p class="text-xs text-light-text truncate"><?php echo htmlspecialchars($trend['category']); ?></p>
                                <p class="text-xs text-primary-blue font-medium mt-1"><i class="fas fa-headphones-alt mr-1 opacity-75"></i> <?php echo htmlspecialchars($trend['listeners']); ?> Ouvintes</p>
                            </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="col-span-full text-center text-medium-text py-6 bg-gray-50 p-4 rounded-md">Nenhum podcast em alta no momento.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="form-element-animated" style="animation-name: fadeInUp; animation-delay: 0.6s;">
                    <div class="bg-card-bg p-5 rounded-xl shadow-lg">
                        <h3 class="text-lg font-semibold text-dark-text mb-4">Seu Resumo Semanal</h3>
                        <?php if ($erro_resumo): ?>
                            <p class="text-center text-danger py-3 bg-red-50 p-4 rounded-md"><?php echo htmlspecialchars($erro_resumo); ?></p>
                        <?php elseif($userId): ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-x-3 gap-y-5 text-center">
                            <div>
                                <p class="text-2xl font-bold text-primary-blue"><?php echo htmlspecialchars($resumo_semanal['tempo_ouvindo']); ?></p>
                                <p class="text-xs text-medium-text mt-0.5">Tempo Ouvindo</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-primary-blue"><?php echo htmlspecialchars($resumo_semanal['novos_episodios_ouvidos']); ?></p>
                                <p class="text-xs text-medium-text mt-0.5">Episódios Ouvidos</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-primary-blue"><?php echo htmlspecialchars($resumo_semanal['podcasts_concluidos']); ?></p>
                                <p class="text-xs text-medium-text mt-0.5">Podcasts Concluídos</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-primary-blue"><?php echo htmlspecialchars($resumo_semanal['novos_favoritos']); ?></p>
                                <p class="text-xs text-medium-text mt-0.5">Novos Favoritos</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <p class="text-center text-medium-text py-3 bg-gray-50 p-4 rounded-md">Faça login para ver seu resumo semanal.</p>
                        <?php endif; ?>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <div id="upgradeModal" class="modal-overlay fixed inset-0 flex items-center justify-center z-[100] hidden p-4">
        <div class="modal-dialog bg-white p-6 sm:p-8 rounded-lg shadow-xl text-center" id="upgradeModalDialog">
            <i class="fas fa-crown text-4xl text-warning mb-4"></i>
            <h3 class="text-xl font-semibold text-primary-blue mb-3">Conteúdo Premium!</h3>
            <p class="text-gray-700 mb-2">O podcast "<span id="modalPodcastTitle" class="font-semibold"></span>" é exclusivo para assinantes.</p>
            <p class="text-gray-600 mb-6">Faça um upgrade no seu plano para ter acesso completo a este e outros conteúdos premium.</p>
            <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-3">
                <a id="modalUpgradeLink" href="planos.php" class="w-full sm:w-auto bg-primary-blue hover:bg-primary-blue-dark text-white font-medium py-2.5 px-6 rounded-lg transition-colors">Ver Planos</a>
                <button onclick="closeUpgradeModal()" class="w-full sm:w-auto bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2.5 px-6 rounded-lg transition-colors">Agora Não</button>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userDropdownButton = document.getElementById('userDropdownButton');
            const userDropdownMenu = document.getElementById('userDropdownMenu');
            if (userDropdownButton && userDropdownMenu) {
                userDropdownButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const isExpanded = userDropdownMenu.classList.toggle('hidden');
                    userDropdownButton.setAttribute('aria-expanded', !isExpanded);
                });
                document.addEventListener('click', function (event) {
                    if (!userDropdownMenu.classList.contains('hidden') && !userDropdownButton.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                        userDropdownMenu.classList.add('hidden');
                        userDropdownButton.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            const animatedElements = document.querySelectorAll('.form-element-animated');
            if ("IntersectionObserver" in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.opacity = '1';
                            entry.target.style.animationPlayState = 'running';
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.1 });
                animatedElements.forEach(el => {
                    el.style.animationPlayState = 'paused'; // Start paused
                    observer.observe(el);
                });
            } else { 
                animatedElements.forEach(el => {
                    el.style.opacity = '1';
                    el.style.animationPlayState = 'running';
                });
            }

            // Lógica para Modal de Upgrade
            const isUserSubscriberOrAdmin = <?php echo json_encode($is_subscriber_or_admin); ?>;
            document.querySelectorAll('a[data-premium="true"][data-requires-upgrade="true"]').forEach(link => {
                link.addEventListener('click', function(event) {
                    if (!isUserSubscriberOrAdmin) { // Verifica novamente, embora o atributo já indique
                        event.preventDefault(); // Impede a navegação padrão
                        const podcastTitle = this.querySelector('h4') ? this.querySelector('h4').textContent : 'este conteúdo';
                        const slug = this.href.split('slug=')[1] ? this.href.split('slug=')[1].split('&')[0] : '';
                        showUpgradeModal(podcastTitle, `planos.php?from_content=${slug ? encodeURIComponent(slug) : ''}&type=podcast&reason=dashboard_promo`);
                    }
                    // Se for assinante/admin, o link funcionará normalmente
                });
            });
            
            const upgradeModalElement = document.getElementById('upgradeModal');
            if (upgradeModalElement) {
                upgradeModalElement.addEventListener('click', function(event) {
                    if (event.target === upgradeModalElement) { // Clicou no fundo escuro (overlay)
                        closeUpgradeModal();
                    }
                });
            }

        });

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            
            sidebar.classList.toggle('open');
            sidebar.classList.toggle('left-[-256px]');
            sidebar.classList.toggle('left-0');
            overlay.classList.toggle('hidden');

            const isSidebarOpen = sidebar.classList.contains('left-0');
            mobileMenuButton.setAttribute('aria-expanded', isSidebarOpen);
        }

        function showUpgradeModal(podcastTitle, upgradePageUrlWithSlug) {
            const modal = document.getElementById('upgradeModal');
            const modalDialog = document.getElementById('upgradeModalDialog');
            document.getElementById('modalPodcastTitle').textContent = podcastTitle;
            if (upgradePageUrlWithSlug) {
                document.getElementById('modalUpgradeLink').href = upgradePageUrlWithSlug;
            } else {
                document.getElementById('modalUpgradeLink').href = 'planos.php';
            }
            modal.classList.remove('hidden');
            modalDialog.style.animation = "modalScaleIn 0.3s ease-out forwards";
            document.body.style.overflow = 'hidden';
        }

        function closeUpgradeModal() {
            const modal = document.getElementById('upgradeModal');
            const modalDialog = document.getElementById('upgradeModalDialog');
            modalDialog.style.animation = "modalScaleOut 0.3s ease-in forwards";
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300); // Tempo da animação de saída
        }
    </script>
</body>
</html>