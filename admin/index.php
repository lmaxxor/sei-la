<?php
// admin/index.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php'); // Garante que apenas administradores acedam
require_once __DIR__ . '/../db/db_connect.php'; // Conexão com o banco de dados

$pageTitle = "Painel de Administração";

// Informações do utilizador para o header (serão usadas por header.php)
$userName_for_header = $_SESSION['user_nome_completo'] ?? 'Admin';
$userEmail_for_header = $_SESSION['user_email'] ?? 'admin@audioto.com'; // Para o header, se necessário
$avatarUrl_for_header = $_SESSION['user_avatar_url'] ?? '';

if (!$avatarUrl_for_header) {
    $initials_for_header = '';
    $nameParts_for_header = explode(' ', trim($userName_for_header));
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(substr($nameParts_for_header[0], 0, 1)) : 'A';
    if (count($nameParts_for_header) > 1) {
        $initials_for_header .= strtoupper(substr(end($nameParts_for_header), 0, 1));
    } elseif (strlen($nameParts_for_header[0]) > 1 && $initials_for_header === strtoupper(substr($nameParts_for_header[0], 0, 1))) {
        $initials_for_header .= strtoupper(substr($nameParts_for_header[0], 1, 1));
    }
    if (empty($initials_for_header) || strlen($initials_for_header) > 2) {
        $initials_for_header = "AD";
    }
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}

// --- Buscando Métricas Reais do Banco de Dados ---
$totalUtilizadores = 0;
$novosUtilizadoresUltimos30Dias = 0;
$totalPodcasts = 0;
$totalAssinaturasAtivas = 0;
$erro_metricas = null;
$labelsMeses = [];
$dataNovosUtilizadoresMes = [];
$dataFaturacaoMes = []; // Inicializa como array vazio; não haverá dados fictícios

try {
    // Total de Utilizadores (excluindo administradores)
    $stmt = $pdo->query("SELECT COUNT(id_utilizador) FROM utilizadores WHERE funcao = 'utilizador'");
    $totalUtilizadores = $stmt->fetchColumn();

    // Novos Utilizadores nos últimos 30 dias
    $stmt = $pdo->prepare("SELECT COUNT(id_utilizador) FROM utilizadores WHERE funcao = 'utilizador' AND data_registo >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $novosUtilizadoresUltimos30Dias = $stmt->fetchColumn();

    // Total de Podcasts
    $stmt = $pdo->query("SELECT COUNT(id_podcast) FROM podcasts");
    $totalPodcasts = $stmt->fetchColumn();
    
    // Total de Assinaturas Ativas
    $stmt = $pdo->query("SELECT COUNT(id_assinatura) FROM assinaturas_utilizador WHERE estado_assinatura = 'ativa'");
    $totalAssinaturasAtivas = $stmt->fetchColumn();

    // Dados para o gráfico de Novos Utilizadores (últimos 6 meses)
    for ($i = 5; $i >= 0; $i--) {
        $mesReferencia = date('Y-m-01', strtotime("-$i month"));
        $proximoMesReferencia = date('Y-m-01', strtotime("-" . ($i-1) . " month"));
        $labelsMeses[] = date('M/y', strtotime($mesReferencia));
        
        $stmt = $pdo->prepare("SELECT COUNT(id_utilizador) FROM utilizadores WHERE funcao = 'utilizador' AND data_registo >= :mes_inicio AND data_registo < :mes_fim");
        $stmt->bindParam(':mes_inicio', $mesReferencia);
        $stmt->bindParam(':mes_fim', $proximoMesReferencia);
        $stmt->execute();
        $dataNovosUtilizadoresMes[] = $stmt->fetchColumn();
    }
    
    // NOTA: Lógica para $dataFaturacaoMes foi removida pois não há fonte de dados real
    // Se você tivesse uma tabela de transações, a lógica seria semelhante à de novos utilizadores.
    // Por agora, $dataFaturacaoMes permanecerá um array vazio.

} catch (PDOException $e) {
    $erro_metricas = "Erro ao carregar métricas: " . $e->getMessage();
    error_log("Erro PDO no Dashboard: " . $e->getMessage());
    $totalUtilizadores = $novosUtilizadoresUltimos30Dias = $totalPodcasts = $totalAssinaturasAtivas = 0; // Default to 0 on error
    $labelsMeses = []; // Default to empty on error
    $dataNovosUtilizadoresMes = []; // Default to empty on error
    $dataFaturacaoMes = []; // Default to empty on error
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Audio TO Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" xintegrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f0f2f5;
        }
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }
        /* Estilos do sidebar.php e header.php devem ser consistentes com estes */
        #adminSidebar {
            width: 260px;
            background-color: #2c3e50; /* Azul escuro moderno */
            color: #ecf0f1;
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        #adminSidebar .nav-link {
            color: #bdc3c7;
            padding: 0.8rem 1.25rem;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }
        #adminSidebar .nav-link:hover {
            background-color: #34495e;
            color: #ffffff;
            border-left-color: #3498db; /* Azul primário Bootstrap */
        }
        #adminSidebar .nav-link.active {
            background-color: #3498db; /* Azul primário Bootstrap */
            color: #ffffff;
            font-weight: 600;
            border-left-color: #2980b9; /* Tom mais escuro do azul primário */
        }
        #adminSidebar .nav-link .fas, #adminSidebar .nav-link .far {
            margin-right: 0.8rem;
            width: 20px;
            text-align: center;
        }
         #adminSidebar .sidebar-brand-text {
            font-size: 1.1rem; /* Slightly smaller brand text */
        }

        .content-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background-color: #f0f2f5;
            overflow-x: hidden; /* Prevent horizontal scroll on content wrapper */
        }
        .admin-main-content {
            padding: 2rem;
            flex-grow: 1;
            overflow-y: auto;
        }
        .admin-header { /* Para header.php */
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        .metric-card {
            background-color: #fff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        .metric-card .metric-icon {
            font-size: 1.75rem;
            padding: 0.75rem;
            border-radius: 50%;
            margin-bottom: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
        }
        .metric-card .metric-value {
            font-size: 1.75rem;
            font-weight: 700;
        }
        .metric-card .metric-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .chart-card {
            background-color: #fff;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .quick-action-card {
            background-color: #fff;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            text-decoration: none;
            color: inherit;
        }
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            color: #0d6efd; /* Bootstrap primary on hover */
        }
        .quick-action-card .quick-action-icon {
            font-size: 1.5rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background-color: #e9ecef;
            color: #0d6efd;
        }

        @media (max-width: 991.98px) {
            #adminSidebar {
                position: fixed; top: 0; bottom: 0; left: -260px; z-index: 1045;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            #adminSidebar.active { left: 0; }
            .content-wrapper.sidebar-active-overlay::before {
                content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background-color: rgba(0,0,0,0.4); z-index: 1040;
            }
        }
    </style>
</head>
<body>

<div class="main-wrapper">

    <div class="content-wrapper" id="contentWrapper">
        <?php 
            if (file_exists(__DIR__ . '/header.php')) {
                require __DIR__ . '/header.php';
            } else {
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
                    <div>
                        <h1 class="h2 mb-1 text-dark fw-bold"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <p class="text-muted small">Visão geral do seu sistema Audio TO.</p>
                    </div>
                     <div class="d-none d-sm-block">
                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2">
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo date("d M, Y"); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($erro_metricas): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($erro_metricas); ?>
                    </div>
                <?php endif; ?>

                <section class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card text-center text-primary">
                            <div class="metric-icon bg-primary-subtle text-primary mx-auto">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="metric-value"><?php echo htmlspecialchars($totalUtilizadores); ?></div>
                            <div class="metric-label">Total de Utilizadores</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card text-center text-success">
                             <div class="metric-icon bg-success-subtle text-success mx-auto">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="metric-value"><?php echo htmlspecialchars($novosUtilizadoresUltimos30Dias); ?></div>
                            <div class="metric-label">Novos nos Últimos 30 Dias</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card text-center text-info">
                            <div class="metric-icon bg-info-subtle text-info mx-auto">
                                <i class="fas fa-podcast"></i>
                            </div>
                            <div class="metric-value"><?php echo htmlspecialchars($totalPodcasts); ?></div>
                            <div class="metric-label">Total de Podcasts</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="metric-card text-center text-warning">
                            <div class="metric-icon bg-warning-subtle text-warning mx-auto">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="metric-value"><?php echo htmlspecialchars($totalAssinaturasAtivas); ?></div>
                            <div class="metric-label">Assinaturas Ativas</div>
                        </div>
                    </div>
                </section>

                <section class="row g-4 mb-4">
                    <div class="col-lg-7">
                        <div class="chart-card">
                            <h5 class="card-title mb-3 fw-semibold"><i class="fas fa-chart-line me-2 text-primary"></i>Novos Utilizadores (Últimos 6 Meses)</h5>
                            <?php if (!empty($labelsMeses) && !empty($dataNovosUtilizadoresMes) && $labelsMeses[0] !== 'N/D'): ?>
                                <canvas id="usersChart" style="max-height: 300px;"></canvas>
                            <?php else: ?>
                                <p class="text-center text-muted p-5">Dados não disponíveis para o gráfico de novos utilizadores.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="chart-card">
                             <h5 class="card-title mb-3 fw-semibold"><i class="fas fa-dollar-sign me-2 text-success"></i>Faturação (Exemplo)</h5>
                             <?php if (!empty($labelsMeses) && !empty($dataFaturacaoMes) && $labelsMeses[0] !== 'N/D'): ?>
                                <canvas id="revenueChart" style="max-height: 300px;"></canvas>
                             <?php else: ?>
                                <p class="text-center text-muted p-5">Dados de faturação não implementados.</p>
                             <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section>
                    <h4 class="mb-3 fw-semibold text-dark">Ações Rápidas</h4>
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-4">
                            <a href="adicionar_podcast.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-upload"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Adicionar Podcast</h6>
                                    <small class="text-muted">Carregar novos áudios e materiais.</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                             <a href="gerir_podcasts.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-list-alt"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Gerir Podcasts</h6>
                                    <small class="text-muted">Editar ou remover existentes.</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                             <a href="gerir_utilizadores.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Gerir Utilizadores</h6>
                                    <small class="text-muted">Ver e administrar contas.</small>
                                </div>
                            </a>
                        </div>
                         <div class="col-sm-6 col-lg-4">
                             <a href="gerir_categorias.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Gerir Categorias</h6>
                                    <small class="text-muted">Criar e editar categorias.</small>
                                </div>
                            </a>
                        </div>
                        <div class="col-sm-6 col-lg-4">
                             <a href="gerir_assuntos.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-bookmark"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Gerir Assuntos</h6>
                                    <small class="text-muted">Organizar os temas dos podcasts.</small>
                                </div>
                            </a>
                        </div>
                         <div class="col-sm-6 col-lg-4">
                             <a href="gerir_oportunidades.php" class="quick-action-card d-flex align-items-center">
                                <div class="quick-action-icon me-3">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Gerir Oportunidades</h6>
                                    <small class="text-muted">Cursos, vagas e eventos.</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </section>

            </div>
        </main>
         <footer class="py-4 mt-auto bg-light border-top">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; Audio TO Admin <?php echo date("Y"); ?></div>
                    <div>
                        <a href="#">Política de Privacidade</a>
                        &middot;
                        <a href="#">Termos &amp; Condições</a>
                    </div>
                </div>
            </div>
        </footer>
    </div> 
</div> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Chart.js: Gráficos
        const userChartCtx = document.getElementById('usersChart')?.getContext('2d');
        const labelsMesesPHP = <?php echo json_encode($labelsMeses); ?>;
        const dataNovosUtilizadoresPHP = <?php echo json_encode($dataNovosUtilizadoresMes); ?>;
        const dataFaturacaoPHP = <?php echo json_encode($dataFaturacaoMes); ?>; // Será vazio

        if (userChartCtx && labelsMesesPHP.length > 0 && labelsMesesPHP[0] !== 'N/D') {
            new Chart(userChartCtx, {
                type: 'bar',
                data: {
                    labels: labelsMesesPHP,
                    datasets: [{
                        label: 'Novos Utilizadores',
                        data: dataNovosUtilizadoresPHP,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)', 
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                        barThickness: 'flex',
                        maxBarThickness: 30
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)'} },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        const revenueChartCtx = document.getElementById('revenueChart')?.getContext('2d');
        // Só renderiza o gráfico de faturação se houver dados (atualmente não há dados reais)
        // Se você implementar dados reais de faturação, esta condição pode ser ajustada.
        if (revenueChartCtx && labelsMesesPHP.length > 0 && dataFaturacaoPHP.length > 0 && labelsMesesPHP[0] !== 'N/D') {
            new Chart(revenueChartCtx, {
                type: 'line',
                data: {
                    labels: labelsMesesPHP,
                    datasets: [{
                        label: 'Faturação Estimada (R$)',
                        data: dataFaturacaoPHP,
                        borderColor: 'rgba(25, 135, 84, 1)', 
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: 'rgba(25, 135, 84, 1)',
                        pointBorderColor: '#fff',
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: 'rgba(25, 135, 84, 1)',

                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)'}, ticks: { callback: function(value) { return 'R$ ' + value; } } },
                        x: { grid: { display: false } }
                    }
                }
            });
        } else if (revenueChartCtx) {
             // Se não houver dados de faturação, você pode exibir uma mensagem ou deixar em branco
            // O HTML já tem uma mensagem para "Dados de faturação não implementados."
        }

        // Sidebar Toggle Logic
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
    });
    </script>
</body>
</html>

