<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); // Garante que o usuário está logado
require_once __DIR__ . '/db/db_connect.php'; // Conexão com o banco de dados

// Variáveis específicas da página
$pageTitle = "Nossos Planos - AudioTO";
$activePage = 'planos'; // Para destacar na sidebar

// Dados do usuário para o header
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;
// Assumindo que user_id está na sessão após o login
$userId = $_SESSION['user_id'] ?? 0; // Importante para registrar a assinatura

// Função de avatar consistente
function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen($avatar_url_from_session) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}
$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);

// Buscar planos do banco de dados
$planos_para_exibir = []; // Array final para usar no HTML
$erro_planos = null;
$preco_mensal_plano_pro_para_calculo = null;

try {
    $stmt = $pdo->query("SELECT id_plano, nome_plano, descricao_plano, preco_mensal, preco_anual, funcionalidades
                         FROM planos_assinatura
                         WHERE ativo = TRUE
                         ORDER BY id_plano ASC");
    $planos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($planos_db) {
        foreach ($planos_db as $p_temp) {
            if ($p_temp['id_plano'] == 2) { // Considera o plano Pro (ID 2) como base para cálculo de economia
                $preco_mensal_plano_pro_para_calculo = $p_temp['preco_mensal'];
                break;
            }
        }

        foreach ($planos_db as $plano_db_item) {
            $processed_plano = $plano_db_item;

            if (!empty($processed_plano['funcionalidades'])) {
                $features_array = json_decode($processed_plano['funcionalidades'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $processed_plano['features_list'] = $features_array;
                } else {
                    $processed_plano['features_list'] = array_map('trim', explode(';', $processed_plano['funcionalidades']));
                }
            } else {
                $processed_plano['features_list'] = [];
            }

            $processed_plano['recomendado'] = ($processed_plano['id_plano'] == 2); // Plano Pro é o recomendado

            // Adiciona o valor real do plano para o JavaScript (necessário para o data-attribute)
            if ($processed_plano['id_plano'] == 3 && !empty($processed_plano['preco_anual'])) { // Anual Pro
                $processed_plano['valor_real_para_pagamento'] = $processed_plano['preco_anual'];
            } elseif (!empty($processed_plano['preco_mensal'])) { // Essencial e Pro Mensal
                $processed_plano['valor_real_para_pagamento'] = $processed_plano['preco_mensal'];
            } else { // Grátis ou Consulte
                $processed_plano['valor_real_para_pagamento'] = 0;
            }

            $planos_para_exibir[] = $processed_plano;
        }
    }

} catch (PDOException $e) {
    error_log("Erro ao buscar planos: " . $e->getMessage());
    $erro_planos = "Não foi possível carregar os planos no momento. Tente novamente mais tarde.";
}
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
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
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
        .plan-card { animation: fadeInUp 0.5s ease-out forwards; opacity:0; } /* opacity:0 for animation */
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        /* Estilos para o modal (Tailwind já ajuda, mas podemos adicionar mais) */
        .animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }
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
                        <input type="text" placeholder="Buscar..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
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

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-light-bg p-6 md:p-8 space-y-10">
                <div class="text-center">
                    <h1 class="text-3xl md:text-4xl font-bold text-dark-text mb-3">Escolha o Plano Ideal para Você</h1>
                    <p class="text-lg text-medium-text max-w-2xl mx-auto">
                        Tenha acesso a podcasts exclusivos, materiais em PDF, e uma comunidade de terapeutas.
                    </p>
                </div>

                <?php if ($erro_planos): ?>
                    <div class="bg-red-100 border-l-4 border-danger text-red-700 p-4 rounded-md shadow max-w-3xl mx-auto" role="alert">
                        <p class="font-bold">Erro ao carregar planos:</p>
                        <p><?= htmlspecialchars($erro_planos); ?></p>
                    </div>
                <?php endif; ?>

                <section id="plansContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-6xl mx-auto">
                    <?php if (!empty($planos_para_exibir)): ?>
                        <?php foreach ($planos_para_exibir as $index => $plano):
                              $isRecommended = $plano['recomendado'];
                              $isGratisOuConsulte = ((is_null($plano['preco_mensal']) || $plano['preco_mensal'] == 0) && (is_null($plano['preco_anual']) || $plano['preco_anual'] == 0));
                        ?>
                            <div class="plan-card bg-card-bg rounded-xl shadow-lg p-8 flex flex-col hover:shadow-2xl transition-shadow duration-300
                                <?= $isRecommended ? 'border-2 border-warning ring-4 ring-warning/20 relative transform md:scale-105' : 'border border-gray-200' ?>"
                                style="animation-delay: <?= $index * 0.1 ?>s;">

                                <?php if ($isRecommended): ?>
                                    <div class="absolute top-0 -translate-y-1/2 left-1/2 -translate-x-1/2 bg-warning text-white text-xs font-semibold px-3 py-1 rounded-full shadow-md uppercase tracking-wider">Mais Popular</div>
                                <?php endif; ?>

                                <h3 class="text-2xl font-semibold <?= $isRecommended ? 'text-warning' : 'text-dark-text' ?> mb-2"><?= htmlspecialchars($plano['nome_plano']); ?></h3>
                                <p class="text-medium-text mb-6 text-sm min-h-[40px] line-clamp-2"><?= htmlspecialchars($plano['descricao_plano'] ?? 'Benefícios incríveis esperam por você.'); ?></p>

                                <div class="mb-6">
                                    <?php if ($plano['id_plano'] == 3 && !empty($plano['preco_anual'])): // "Anual Pro" (id 3) ?>
                                        <span class="text-4xl font-bold <?= $isRecommended ? 'text-warning' : 'text-primary-blue' ?>">R$ <?= number_format($plano['preco_anual'], 2, ',', '.'); ?></span>
                                        <span class="text-medium-text">/ano</span>
                                        <?php
                                        if ($preco_mensal_plano_pro_para_calculo > 0):
                                            $economia = 1 - ($plano['preco_anual'] / ($preco_mensal_plano_pro_para_calculo * 12));
                                            if ($economia > 0.01):
                                        ?>
                                        <p class="text-sm text-success font-medium">(Economize ~<?= round($economia * 100) ?>% em relação ao Pro mensal - equivale a R$ <?= number_format($plano['preco_anual'] / 12, 2, ',', '.') ?>/mês)</p>
                                        <?php endif; endif; ?>
                                    <?php elseif (!empty($plano['preco_mensal'])): // "Essencial" (id 1) e "Pro" (id 2) ?>
                                        <span class="text-4xl font-bold <?= $isRecommended ? 'text-warning' : 'text-primary-blue' ?>">R$ <?= number_format($plano['preco_mensal'], 2, ',', '.'); ?></span>
                                        <span class="text-medium-text">/mês</span>
                                    <?php else: // Caso de plano gratuito ou preço não definido (ex: "Consulte") ?>
                                        <span class="text-4xl font-bold <?= $isRecommended ? 'text-warning' : 'text-primary-blue' ?>">
                                            <?= ($isGratisOuConsulte) ? 'Grátis' : 'Consulte' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <ul class="space-y-3 text-medium-text mb-8 flex-grow text-sm">
                                    <?php if (!empty($plano['features_list'])): ?>
                                        <?php foreach ($plano['features_list'] as $feature): if(empty(trim($feature))) continue; ?>
                                            <li class="flex items-start">
                                                <svg class="w-5 h-5 text-success mr-2 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                                <span><?= htmlspecialchars($feature); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="flex items-center text-light-text">Benefícios principais incluídos.</li>
                                    <?php endif; ?>
                                </ul>

                                <?php if (!$isGratisOuConsulte): // Só mostra botão de pagamento se não for Grátis ou Consulte ?>
                                <button data-plano-id="<?= $plano['id_plano']; ?>"
                                        data-plano-nome="<?= htmlspecialchars($plano['nome_plano']); ?>"
                                        data-plano-amount="<?= htmlspecialchars($plano['valor_real_para_pagamento']); ?>"
                                        class="w-full <?= $isRecommended ? 'bg-warning text-white hover:bg-yellow-600' : 'bg-primary-blue text-white hover:bg-primary-blue-dark' ?>
                                               font-semibold py-3 px-6 rounded-lg transition-colors duration-300 mt-auto choose-plan-button">
                                    Escolher Plano
                                </button>
                                <?php elseif ($isGratisOuConsulte && strtolower($plano['nome_plano']) !== 'grátis' && $plano['valor_real_para_pagamento'] == 0): // Botão para "Consulte" ?>
                                    <a href="contato.php?plano=<?= urlencode($plano['nome_plano']) ?>"
                                       class="w-full text-center <?= $isRecommended ? 'bg-warning text-white hover:bg-yellow-600' : 'bg-primary-blue text-white hover:bg-primary-blue-dark' ?>
                                              font-semibold py-3 px-6 rounded-lg transition-colors duration-300 mt-auto">
                                        Consultar
                                    </a>
                                <?php else: // Para o plano "Grátis", pode não ter botão ou ter um botão de "Começar" diferente ?>
                                    <a href="registro.php?plano_id=<?= $plano['id_plano']; ?>"
                                       class="w-full text-center <?= $isRecommended ? 'bg-success text-white hover:bg-green-700' : 'bg-primary-blue text-white hover:bg-primary-blue-dark' ?>
                                              font-semibold py-3 px-6 rounded-lg transition-colors duration-300 mt-auto">
                                        Começar Agora (Grátis)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif(!$erro_planos): ?>
                        <p class="col-span-full text-center text-medium-text py-10 text-xl">Nenhum plano disponível no momento. Volte em breve!</p>
                    <?php endif; ?>
                </section>

                <section class="max-w-3xl mx-auto mt-12 py-8">
                    <h3 class="text-2xl font-semibold text-dark-text text-center mb-8">Perguntas Frequentes</h3>
                    <div class="space-y-4">
                        <details class="bg-card-bg p-4 rounded-lg shadow hover:shadow-md transition-shadow group">
                            <summary class="font-medium text-dark-text cursor-pointer list-none flex justify-between items-center">
                                Posso cancelar meu plano a qualquer momento?
                                <svg class="w-5 h-5 text-medium-text group-open:rotate-180 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <p class="text-medium-text mt-3 text-sm leading-relaxed">
                                Sim, você pode cancelar seu plano mensal a qualquer momento. Para planos anuais, o cancelamento se aplicará ao final do período de 12 meses já pago. Não oferecemos reembolsos proporcionais para períodos não utilizados.
                            </p>
                        </details>
                        <details class="bg-card-bg p-4 rounded-lg shadow hover:shadow-md transition-shadow group">
                            <summary class="font-medium text-dark-text cursor-pointer list-none flex justify-between items-center">
                                Quais são as formas de pagamento aceitas?
                                <svg class="w-5 h-5 text-medium-text group-open:rotate-180 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <p class="text-medium-text mt-3 text-sm leading-relaxed">
                                Aceitamos pagamentos via Pix para todos os planos. Para alguns planos e promoções futuras, poderemos adicionar cartões de crédito.
                            </p>
                        </details>
                         <details class="bg-card-bg p-4 rounded-lg shadow hover:shadow-md transition-shadow group">
                            <summary class="font-medium text-dark-text cursor-pointer list-none flex justify-between items-center">
                                Terei acesso aos podcasts e PDFs antigos se assinar hoje?
                                <svg class="w-5 h-5 text-medium-text group-open:rotate-180 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                            </summary>
                            <p class="text-medium-text mt-3 text-sm leading-relaxed">
                                Sim! Com os planos Pro e Anual Pro, você tem acesso a todo o nosso catálogo de podcasts e materiais em PDF, incluindo os conteúdos já publicados anteriormente. O Plano Essencial dá acesso aos podcasts do mês corrente e alguns selecionados do acervo.
                            </p>
                        </details>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div id="pixModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-[100] hidden animate-fade-in-up">
        <div class="bg-white p-6 md:p-8 rounded-lg shadow-xl w-11/12 md:w-1/2 lg:w-1/3 max-w-lg transform transition-all">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold text-primary-blue">Pagar com Pix - <span id="modalPlanName"></span></h2>
                <button id="closePixModal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>

            <div id="pixFormStep">
                <p class="mb-2">Plano: <strong id="modalPlanPrice" class="text-lg"></strong></p>
                <p class="text-sm text-medium-text mb-4">Preencha seus dados para gerar o código Pix.</p>
                <div class="mb-4">
                    <label for="pixCpf" class="block text-sm font-medium text-gray-700 mb-1">CPF do Pagador:</label>
                    <input type="text" id="pixCpf" name="pixCpf" class="w-full p-2 border border-gray-300 rounded-md focus:ring-primary-blue focus:border-primary-blue" placeholder="000.000.000-00" maxlength="14">
                </div>
                <div class="mb-6">
                    <label for="pixNome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo do Pagador:</label>
                    <input type="text" id="pixNome" name="pixNome" value="<?= htmlspecialchars($_SESSION['user_nome_completo'] ?? '') ?>" class="w-full p-2 border border-gray-300 rounded-md focus:ring-primary-blue focus:border-primary-blue" placeholder="Seu nome completo">
                </div>
                <input type="hidden" id="modalPlanIdInput" value="">
                <input type="hidden" id="modalPlanAmountInput" value="">

                <button id="generatePixButton" class="w-full bg-primary-blue hover:bg-primary-blue-dark text-white font-semibold py-3 px-4 rounded-lg transition duration-150 ease-in-out flex items-center justify-center">
                    <span id="generatePixButtonText">Gerar QR Code Pix</span>
                    <i id="generatePixButtonSpinner" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                </button>
                <div id="pixFormError" class="text-danger text-sm mt-2"></div>
            </div>

            <div id="pixQrCodeStep" class="hidden text-center">
                <p class="text-medium-text mb-3">Escaneie o QR Code abaixo com o app do seu banco:</p>
                <div class="flex justify-center mb-3">
                    <img id="pixQrCodeImage" src="" alt="Pix QR Code" class="w-48 h-48 md:w-56 md:h-56 border rounded-md p-1 bg-white">
                </div>
                <p class="text-medium-text mb-2 text-sm">Ou copie o código Pix:</p>
                <div class="relative mb-4">
                    <textarea id="pixCopiaECola" readonly rows="3" class="w-full p-3 pr-12 border border-gray-300 rounded-md bg-gray-50 text-xs resize-none"></textarea>
                    <button id="copyPixCodeButton" class="absolute top-2 right-2 p-1 text-gray-500 hover:text-primary-blue" title="Copiar Código">
                        <i class="fas fa-copy text-lg"></i>
                    </button>
                </div>
                <div id="pixStatusMessage" class="mt-4 p-3 rounded-md text-sm"></div>
                <p class="text-xs text-light-text mt-4">Após o pagamento, aguarde alguns instantes para a confirmação automática aqui ou verifique o status da sua assinatura na área "Meu Perfil".</p>
                <input type="hidden" id="currentTxid" value="">
            </div>
             <div id="pixLoading" class="hidden text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-primary-blue"></i>
                <p class="mt-2 text-medium-text">Aguarde...</p>
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

        const planCards = document.querySelectorAll('.plan-card');
        planCards.forEach((card, index) => {
             setTimeout(() => {
                card.style.opacity = '1';
            }, index * 100 + 500);
        });

        // --- Lógica do Modal PIX ---
        const pixModal = document.getElementById('pixModal');
        const closePixModalButton = document.getElementById('closePixModal');
        const choosePlanButtons = document.querySelectorAll('.choose-plan-button');

        const pixFormStep = document.getElementById('pixFormStep');
        const pixQrCodeStep = document.getElementById('pixQrCodeStep');
        const pixLoading = document.getElementById('pixLoading');

        const modalPlanName = document.getElementById('modalPlanName');
        const modalPlanPrice = document.getElementById('modalPlanPrice');
        const modalPlanIdInput = document.getElementById('modalPlanIdInput');
        const modalPlanAmountInput = document.getElementById('modalPlanAmountInput');

        const pixCpfInput = document.getElementById('pixCpf');
        const pixNomeInput = document.getElementById('pixNome');
        const generatePixButton = document.getElementById('generatePixButton');
        const generatePixButtonText = document.getElementById('generatePixButtonText');
        const generatePixButtonSpinner = document.getElementById('generatePixButtonSpinner');
        const pixFormError = document.getElementById('pixFormError');

        const pixQrCodeImage = document.getElementById('pixQrCodeImage');
        const pixCopiaEColaInput = document.getElementById('pixCopiaECola');
        const copyPixCodeButton = document.getElementById('copyPixCodeButton');
        const pixStatusMessage = document.getElementById('pixStatusMessage');
        const currentTxidInput = document.getElementById('currentTxid');

        let checkPixInterval = null;
        let userId = <?= json_encode($userId) ?>; // Pega o ID do usuário do PHP

        function openPixModal(planId, planName, planAmountFormatted, planAmountRaw) {
            if (parseFloat(planAmountRaw) <= 0) {
                // Não abrir modal para planos gratuitos ou de valor zero/consulte aqui
                // Se for "Consulte", o botão já é um link para contato.php
                // Se for "Grátis", o botão já é um link para registro.php
                console.log("Plano sem valor de pagamento, modal Pix não será aberto por este botão.");
                return;
            }

            modalPlanName.textContent = planName;
            modalPlanPrice.textContent = planAmountFormatted; // Ex: "R$ 34,90" ou "R$ 397,00"
            modalPlanIdInput.value = planId;
            modalPlanAmountInput.value = planAmountRaw;

            pixCpfInput.value = '';
            pixNomeInput.value = "<?= htmlspecialchars($userName ?? '') ?>"; // Preenche com nome da sessão
            pixFormError.textContent = '';
            pixFormStep.classList.remove('hidden');
            pixQrCodeStep.classList.add('hidden');
            pixLoading.classList.add('hidden');
            pixStatusMessage.textContent = '';
            pixStatusMessage.className = 'mt-4 p-3 rounded-md text-sm';
            generatePixButton.disabled = false;
            generatePixButtonText.textContent = 'Gerar QR Code Pix';
            generatePixButtonSpinner.classList.add('hidden');


            pixModal.classList.remove('hidden');
            if(checkPixInterval) clearInterval(checkPixInterval);
        }

        function closePixModal() {
            pixModal.classList.add('hidden');
            if(checkPixInterval) clearInterval(checkPixInterval);
        }

        if (closePixModalButton) {
            closePixModalButton.addEventListener('click', closePixModal);
        }
        if (pixModal) {
            pixModal.addEventListener('click', function(event) {
                if (event.target === pixModal) {
                    closePixModal();
                }
            });
        }


        choosePlanButtons.forEach(button => {
            button.addEventListener('click', function() {
                const planId = this.dataset.planoId;
                const planName = this.dataset.planoNome;
                const planAmountRaw = parseFloat(this.dataset.planoAmount);

                if (isNaN(planAmountRaw) || planAmountRaw <= 0) {
                    // Este botão não deveria levar ao modal de pagamento Pix se o valor for 0 ou inválido.
                    // A lógica de exibição do botão no PHP já deve tratar isso.
                    // Se um plano "Consulte" ou "Grátis" tiver este botão por engano, ele não fará nada aqui.
                    console.log("Plano selecionado não tem valor para pagamento Pix ou é inválido.");
                    return;
                }

                // Formata o preço para exibição (ex: R$ 34,90 /mês ou R$ 397,00 /ano)
                // Esta informação já está no card, mas podemos reconstruir ou pegar de data-attributes mais detalhados se necessário.
                // Por simplicidade, vamos apenas usar o valor raw para o modal de pagamento.
                let planPriceFormatted = `R$ ${planAmountRaw.toFixed(2).replace('.', ',')}`;
                // Você pode querer adicionar /mês ou /ano aqui se tiver essa info nos data-attributes

                openPixModal(planId, planName, planPriceFormatted, planAmountRaw);
            });
        });

        if (generatePixButton) {
            generatePixButton.addEventListener('click', async function() {
                const amount = modalPlanAmountInput.value;
                const cpf = pixCpfInput.value;
                const nome = pixNomeInput.value;
                const planId = modalPlanIdInput.value;

                pixFormError.textContent = '';
                if (!cpf.trim() || !nome.trim()) {
                    pixFormError.textContent = 'Por favor, preencha CPF e Nome.';
                    return;
                }
                // Validação simples de CPF (11 dígitos)
                if (cpf.replace(/\D/g, '').length !== 11) {
                    pixFormError.textContent = 'CPF inválido. Deve conter 11 dígitos.';
                    return;
                }


                generatePixButton.disabled = true;
                generatePixButtonText.textContent = 'Gerando...';
                generatePixButtonSpinner.classList.remove('hidden');

                pixFormStep.classList.add('hidden');
                pixLoading.classList.remove('hidden');

                const formData = new FormData();
                formData.append('amount', amount);
                formData.append('cpf', cpf);
                formData.append('nome', nome);
                formData.append('planId', planId);
                formData.append('userId', userId); // Enviar userId para o backend

                try {
                    const response = await fetch('payments/gerar_pix_efi.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    pixLoading.classList.add('hidden');
                    generatePixButton.disabled = false;
                    generatePixButtonText.textContent = 'Gerar QR Code Pix';
                    generatePixButtonSpinner.classList.add('hidden');


                    if (data.success) {
                        pixQrCodeImage.src = data.qrCodeImageUrl;
                        pixCopiaEColaInput.value = data.pixCopiaECola;
                        currentTxidInput.value = data.txid;
                        pixQrCodeStep.classList.remove('hidden');
                        pixStatusMessage.textContent = 'Aguardando pagamento...';
                        pixStatusMessage.className = 'mt-4 p-3 rounded-md text-sm bg-blue-100 text-blue-700 border border-blue-300';
                        startPixStatusCheck(data.txid);
                    } else {
                        pixFormError.textContent = data.message || 'Erro ao gerar Pix.';
                        pixFormStep.classList.remove('hidden');
                    }
                } catch (error) {
                    console.error('Erro na requisição AJAX:', error);
                    pixLoading.classList.add('hidden');
                    generatePixButton.disabled = false;
                    generatePixButtonText.textContent = 'Gerar QR Code Pix';
                    generatePixButtonSpinner.classList.add('hidden');
                    pixFormError.textContent = 'Erro de comunicação. Tente novamente.';
                    pixFormStep.classList.remove('hidden');
                }
            });
        }

        if (copyPixCodeButton) {
            copyPixCodeButton.addEventListener('click', function() {
                pixCopiaEColaInput.select();
                pixCopiaEColaInput.setSelectionRange(0, 999999); // Para mobile
                try {
                    navigator.clipboard.writeText(pixCopiaEColaInput.value).then(() => {
                        this.innerHTML = '<i class="fas fa-check text-success"></i>';
                        setTimeout(() => { this.innerHTML = '<i class="fas fa-copy text-lg"></i>'; }, 2000);
                    }).catch(err => {
                        console.warn('Falha ao copiar com navigator.clipboard:', err);
                        // Fallback para document.execCommand
                        if (document.execCommand('copy')) {
                             this.innerHTML = '<i class="fas fa-check text-success"></i>';
                             setTimeout(() => { this.innerHTML = '<i class="fas fa-copy text-lg"></i>'; }, 2000);
                        } else {
                            alert('Não foi possível copiar o código. Tente manualmente.');
                        }
                    });
                } catch (err) {
                     console.warn('Erro geral ao tentar copiar:', err);
                     alert('Não foi possível copiar o código. Tente manualmente.');
                }
            });
        }

        function startPixStatusCheck(txid) {
            if (checkPixInterval) clearInterval(checkPixInterval);

            let attempts = 0;
            const maxAttempts = 60; // Tentar por 5 minutos (60 tentativas * 5 segundos)

            checkPixInterval = setInterval(async () => {
                attempts++;
                if (attempts > maxAttempts && pixModal.classList.contains('hidden') === false) { // Só mostra timeout se modal estiver aberto
                    clearInterval(checkPixInterval);
                    pixStatusMessage.textContent = 'Tempo limite para verificação. Se o pagamento foi feito, seu plano será ativado em breve.';
                    pixStatusMessage.className = 'mt-4 p-3 rounded-md text-sm bg-orange-100 text-orange-700 border border-orange-300';
                    return;
                }
                 if (attempts > maxAttempts && pixModal.classList.contains('hidden') === true) {
                     clearInterval(checkPixInterval); // Para de verificar se o modal for fechado e o tempo esgotar
                     return;
                 }


                try {
                    const response = await fetch(`payments/verificar_pix_efi.php?txid=${txid}`);
                    const data = await response.json();

                    if (data.success) {
                        if (data.isPaid) {
                            clearInterval(checkPixInterval);
                            pixStatusMessage.textContent = 'Pagamento Confirmado! Seu plano está ativo.';
                            pixStatusMessage.className = 'mt-4 p-3 rounded-md text-sm bg-success text-white border border-green-600';
                            setTimeout(() => {
                                // Opcional: Redirecionar ou atualizar a página
                                // window.location.href = 'meu_perfil.php'; // Exemplo
                                 window.location.reload(); // Recarrega a página para refletir o novo status
                            }, 3000);
                        } else if (data.status && data.status.toUpperCase() === 'ATIVA') {
                            pixStatusMessage.textContent = 'Aguardando pagamento... (Status: ' + data.status + ')';
                        } else if (data.status) {
                            clearInterval(checkPixInterval);
                            pixStatusMessage.textContent = 'Cobrança não pôde ser concluída. (Status: ' + data.status + ')';
                            pixStatusMessage.className = 'mt-4 p-3 rounded-md text-sm bg-danger text-white border border-red-600';
                        }
                    } else {
                        // Não muda a mensagem se a verificação falhar, apenas loga ou mantém "Aguardando..."
                        // A mensagem de erro do data.message já seria mostrada pelo gerar_pix_efi.php
                         console.warn("Falha ao verificar status: ", data.message);
                    }
                } catch (error) {
                    console.error('Erro ao verificar status do Pix (AJAX):', error);
                }
            }, 5000); // Verifica a cada 5 segundos
        }

        if (pixCpfInput) {
            pixCpfInput.addEventListener('input', function (e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.substring(0, 11); // Limita a 11 dígitos
                let formattedValue = '';
                if (value.length > 9) {
                    formattedValue = `${value.substring(0, 3)}.${value.substring(3, 6)}.${value.substring(6, 9)}-${value.substring(9)}`;
                } else if (value.length > 6) {
                    formattedValue = `${value.substring(0, 3)}.${value.substring(3, 6)}.${value.substring(6)}`;
                } else if (value.length > 3) {
                    formattedValue = `${value.substring(0, 3)}.${value.substring(3)}`;
                } else {
                    formattedValue = value;
                }
                e.target.value = formattedValue;
            });
        }
    });
    </script>
</body>
</html>