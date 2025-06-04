<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 
ob_start(); 

// 1. Incluir o gestor de sessões
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php'); 

// 2. Incluir a conexão com o banco de dados
require_once __DIR__ . '/db/db_connect.php'; // Garanta que este caminho está correto e $pdo é inicializado

$pageTitle = "Meu Perfil - AudioTO";

// Dados do utilizador da sessão
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@exemplo.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;
$userProfession = ''; // Será carregado do DB se existir
$userCrefito = '';    // Será carregado do DB se existir

if ($userId && isset($pdo)) {
    try {
        $stmt_user_details = $pdo->prepare("SELECT profissao, crefito FROM utilizadores WHERE id_utilizador = :userId");
        $stmt_user_details->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_user_details->execute();
        $userDetails = $stmt_user_details->fetch(PDO::FETCH_ASSOC);
        if ($userDetails) {
            $userProfession = $userDetails['profissao'] ?? '';
            $userCrefito = $userDetails['crefito'] ?? '';
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar detalhes do utilizador (profissão/crefito): " . $e->getMessage());
    }
}

// Definição de cores do Tailwind para uso no PHP dentro das tags <style>
// Renamed from get_user_avatar_profile_placeholder to match the model's naming convention
$tailwindColors = [
    'primary' => '#007AFF',
    'primary-light' => '#EBF4FF', // primary/10
    'primary-dark' => '#0056b3',
    'secondary' => '#4F46E5',
    'success' => '#34D399',
    'info' => '#FBBF24',
    'danger' => '#EF4444',
    'light-bg' => '#F9FAFB',
    'dark-text' => '#1F2937',
    'medium-text' => '#4B5563',
    'light-text' => '#6B7280',
];

// Função para obter avatar, renomeada conforme o modelo
function get_user_avatar_placeholder_blue($user_name, $avatar_url_from_session, $size = 128) {
    if ($avatar_url_from_session && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL) && strlen(trim($avatar_url_from_session)) > 10) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    // Usando a cor primária definida no Tailwind config do perfil (007AFF)
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=007AFF&color=fff&size={$size}&rounded=true&bold=true";
}

$profileAvatar = get_user_avatar_placeholder_blue($userName, $userAvatarUrlSession, 128);
$headerAvatar = get_user_avatar_placeholder_blue($userName, $userAvatarUrlSession, 40); // Para o header

// Dados da assinatura
$subscriptionData = [
    'currentPlan' => 'Nenhum plano ativo',
    'planStatus' => '-',
    'renewalDate' => '-'
];
if ($userId && isset($pdo)) {
    try {
        $stmt_sub = $pdo->prepare(
            "SELECT p.nome_plano, su.estado_assinatura, DATE_FORMAT(su.data_proxima_cobranca, '%d de %M de %Y') as data_renovacao
             FROM assinaturas_utilizador su
             JOIN planos_assinatura p ON su.id_plano = p.id_plano
             WHERE su.id_utilizador = :userId AND su.estado_assinatura = 'ativa'
             ORDER BY su.data_inicio DESC LIMIT 1"
        );
        $stmt_sub->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_sub->execute();
        $userSubscription = $stmt_sub->fetch(PDO::FETCH_ASSOC);

        if ($userSubscription) {
            $subscriptionData['currentPlan'] = htmlspecialchars($userSubscription['nome_plano']);
            $subscriptionData['planStatus'] = htmlspecialchars(ucfirst($userSubscription['estado_assinatura']));
            $subscriptionData['renewalDate'] = htmlspecialchars($userSubscription['data_renovacao'] ? str_replace(
                ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                $userSubscription['data_renovacao']
            ) : 'N/D');
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar dados da assinatura: " . $e->getMessage());
    }
}

// Preferências de notificação
$notificationPreferences = [
    'notifyNewPodcasts' => true,
    'notifyNewOpportunities' => true,
    'notifyPlatformNews' => false
];
if ($userId && isset($pdo)) {
    try {
        $stmt_notif = $pdo->prepare("SELECT notificar_novos_podcasts, notificar_novas_oportunidades, notificar_noticias_plataforma FROM preferencias_notificacao WHERE id_utilizador = :userId");
        $stmt_notif->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt_notif->execute();
        $userPrefs = $stmt_notif->fetch(PDO::FETCH_ASSOC);
        if ($userPrefs) {
            $notificationPreferences['notifyNewPodcasts'] = (bool)$userPrefs['notificar_novos_podcasts'];
            $notificationPreferences['notifyNewOpportunities'] = (bool)$userPrefs['notificar_novas_oportunidades'];
            $notificationPreferences['notifyPlatformNews'] = (bool)$userPrefs['notificar_noticias_plataforma'];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar preferências de notificação: " . $e->getMessage());
    }
}

// Lógica de atualização do perfil
$updateMessage = '';
$updateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId && isset($pdo)) {
    if (isset($_POST['saveProfile'])) {
        $newFullName = trim($_POST['fullName'] ?? '');
        $newProfession = trim($_POST['profession'] ?? '');
        $newCrefito = trim($_POST['crefito'] ?? '');
        $avatarFile = $_FILES['avatar'] ?? null;

        if (empty($newFullName)) {
            $updateError = "O nome completo não pode estar vazio.";
        } else {
            $avatarPathToSave = $userAvatarUrlSession; 

            if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                         $updateError = "Falha ao criar diretório de uploads.";
                    }
                }
                if (empty($updateError)) { // Proceed only if directory creation was successful or dir exists
                    $fileName = $userId . '_' . time() . '_' . preg_replace("/[^a-zA-Z0-9\-\._]/", "", basename($avatarFile['name']));
                    $targetFilePath = $uploadDir . $fileName;
                    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

                    if (in_array($fileType, $allowedTypes) && $avatarFile['size'] < 2000000) { // Max 2MB
                        if (move_uploaded_file($avatarFile['tmp_name'], $targetFilePath)) {
                            $baseUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/');
                            $avatarPathToSave = $baseUrl . '/uploads/avatars/' . $fileName;
                            
                            if ($userAvatarUrlSession && strpos($userAvatarUrlSession, 'ui-avatars.com') === false) {
                                $oldAvatarLocalPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($userAvatarUrlSession, PHP_URL_PATH);
                                if (file_exists($oldAvatarLocalPath) && is_writable($oldAvatarLocalPath)) {
                                    unlink($oldAvatarLocalPath);
                                }
                            }
                        } else {
                            $updateError = "Erro ao fazer upload do novo avatar.";
                        }
                    } else {
                        $updateError = "Avatar inválido. Use JPG, PNG, GIF (máx 2MB).";
                    }
                }
            }

            if (empty($updateError)) {
                try {
                    $stmt_update = $pdo->prepare("UPDATE utilizadores SET nome_completo = :nome, profissao = :profissao, crefito = :crefito, avatar_url = :avatar WHERE id_utilizador = :userId");
                    $stmt_update->bindParam(':nome', $newFullName);
                    $stmt_update->bindParam(':profissao', $newProfession);
                    $stmt_update->bindParam(':crefito', $newCrefito);
                    $stmt_update->bindParam(':avatar', $avatarPathToSave);
                    $stmt_update->bindParam(':userId', $userId, PDO::PARAM_INT);
                    
                    if ($stmt_update->execute()) {
                        $_SESSION['user_nome_completo'] = $newFullName;
                        $_SESSION['user_avatar_url'] = $avatarPathToSave;
                        $_SESSION['user_profissao'] = $newProfession; // Atualizar sessão
                        $_SESSION['user_crefito'] = $newCrefito;     // Atualizar sessão
                        
                        $userName = $newFullName;
                        $userProfession = $newProfession;
                        $userCrefito = $newCrefito;
                        $userAvatarUrlSession = $avatarPathToSave;
                        $profileAvatar = get_user_avatar_placeholder_blue($userName, $userAvatarUrlSession, 128);
                        $headerAvatar = get_user_avatar_placeholder_blue($userName, $userAvatarUrlSession, 40);
                        $updateMessage = "Perfil atualizado com sucesso!";
                    } else {
                        $updateError = "Erro ao atualizar perfil no banco de dados.";
                    }
                } catch (PDOException $e) {
                    $updateError = "Erro no banco de dados: " . $e->getMessage();
                    error_log("Erro DB ao atualizar perfil: " . $e->getMessage());
                }
            }
        }
    } elseif (isset($_POST['changePassword'])) {
        $currentPassword = $_POST['currentPassword'] ?? '';
        $newPassword = $_POST['newPassword'] ?? '';
        $confirmNewPassword = $_POST['confirmNewPassword'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
            $updateError = "Todos os campos de palavra-passe são obrigatórios.";
        } elseif ($newPassword !== $confirmNewPassword) {
            $updateError = "A nova palavra-passe e a confirmação não coincidem.";
        } elseif (strlen($newPassword) < 8) {
            $updateError = "A nova palavra-passe deve ter pelo menos 8 caracteres.";
        } else {
            try {
                $stmt_pass = $pdo->prepare("SELECT palavra_passe FROM utilizadores WHERE id_utilizador = :userId");
                $stmt_pass->bindParam(':userId', $userId, PDO::PARAM_INT);
                $stmt_pass->execute();
                $user_db = $stmt_pass->fetch(PDO::FETCH_ASSOC);

                if ($user_db && password_verify($currentPassword, $user_db['palavra_passe'])) {
                    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt_update_pass = $pdo->prepare("UPDATE utilizadores SET palavra_passe = :new_password WHERE id_utilizador = :userId");
                    $stmt_update_pass->bindParam(':new_password', $hashedNewPassword);
                    $stmt_update_pass->bindParam(':userId', $userId, PDO::PARAM_INT);
                    if ($stmt_update_pass->execute()) {
                        $updateMessage = "Palavra-passe alterada com sucesso!";
                    } else {
                        $updateError = "Erro ao atualizar a palavra-passe.";
                    }
                } else {
                    $updateError = "Palavra-passe atual incorreta.";
                }
            } catch (PDOException $e) {
                $updateError = "Erro no banco de dados ao alterar palavra-passe: " . $e->getMessage();
                 error_log("Erro DB ao alterar password: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['saveNotifications'])) {
        $notifyPodcasts = isset($_POST['notifyNewPodcasts']) ? 1 : 0;
        $notifyOpportunities = isset($_POST['notifyNewOpportunities']) ? 1 : 0;
        $notifyPlatformNews = isset($_POST['notifyPlatformNews']) ? 1 : 0;
        try {
            $stmt_check_notif = $pdo->prepare("SELECT id_preferencia FROM preferencias_notificacao WHERE id_utilizador = :userId");
            $stmt_check_notif->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt_check_notif->execute();

            if ($stmt_check_notif->fetch()) {
                $stmt_update_notif = $pdo->prepare("UPDATE preferencias_notificacao SET notificar_novos_podcasts = :pod, notificar_novas_oportunidades = :opp, notificar_noticias_plataforma = :news WHERE id_utilizador = :userId");
            } else {
                $stmt_update_notif = $pdo->prepare("INSERT INTO preferencias_notificacao (id_utilizador, notificar_novos_podcasts, notificar_novas_oportunidades, notificar_noticias_plataforma) VALUES (:userId, :pod, :opp, :news)");
            }
            $stmt_update_notif->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt_update_notif->bindParam(':pod', $notifyPodcasts, PDO::PARAM_INT);
            $stmt_update_notif->bindParam(':opp', $notifyOpportunities, PDO::PARAM_INT);
            $stmt_update_notif->bindParam(':news', $notifyPlatformNews, PDO::PARAM_INT);
            
            if ($stmt_update_notif->execute()) {
                $updateMessage = "Preferências de notificação guardadas com sucesso!";
                $notificationPreferences['notifyNewPodcasts'] = (bool)$notifyPodcasts;
                $notificationPreferences['notifyNewOpportunities'] = (bool)$notifyOpportunities;
                $notificationPreferences['notifyPlatformNews'] = (bool)$notifyPlatformNews;
            } else {
                $updateError = "Erro ao guardar preferências de notificação.";
            }
        } catch (PDOException $e) {
            $updateError = "Erro no banco de dados ao guardar preferências: " . $e->getMessage();
            error_log("Erro DB ao guardar notificacoes: " . $e->getMessage());
        }
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '<?php echo $tailwindColors['primary']; ?>', 
                        'primary-dark': '<?php echo $tailwindColors['primary-dark']; ?>',
                        'primary-light': '<?php echo $tailwindColors['primary-light']; ?>',
                        'secondary': '<?php echo $tailwindColors['secondary']; ?>', 
                        'success': '<?php echo $tailwindColors['success']; ?>', 
                        'info': '<?php echo $tailwindColors['info']; ?>',   
                        'danger': '<?php echo $tailwindColors['danger']; ?>',   
                        'light-bg': '<?php echo $tailwindColors['light-bg']; ?>', 
                        'dark-text': '<?php echo $tailwindColors['dark-text']; ?>', 
                        'medium-text': '<?php echo $tailwindColors['medium-text']; ?>',
                        'light-text': '<?php echo $tailwindColors['light-text']; ?>', 
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'Arial', '"Noto Sans"', 'sans-serif', '"Apple Color Emoji"', '"Segoe UI Emoji"', '"Segoe UI Symbol"', '"Noto Color Emoji"'],
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
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #a1a1a1; }
        html, body { height: 100%; font-family: 'Inter', sans-serif; }
        body { display: flex; flex-direction: column; }
        .main-container { flex-grow: 1; }
        
        .active-nav-item { 
            background-color: <?php echo $tailwindColors['primary-light']; ?>;
            color: <?php echo $tailwindColors['primary']; ?>;
        }
        .active-nav-item svg { color: <?php echo $tailwindColors['primary']; ?> !important; }
        
        .form-input {
            @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-colors duration-200 text-sm placeholder-gray-400;
        }
        .form-label {
            @apply block text-sm font-medium text-medium-text mb-1;
        }
        .btn {
            @apply font-semibold py-2.5 px-6 rounded-lg transition-colors duration-300 disabled:opacity-50 inline-block text-center;
        }
        .btn-primary {
            @apply bg-primary text-white hover:bg-primary-dark;
        }
        .btn-secondary {
            @apply bg-gray-200 text-dark-text hover:bg-gray-300;
        }
        .form-checkbox {
             @apply h-5 w-5 text-primary rounded border-gray-300 focus:ring-primary/50;
        }

        .floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.5rem;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            min-width: 250px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .floating-alert.show {
            opacity: 1;
            transform: translateY(0);
        }
        .floating-alert.success { background-color: <?php echo $tailwindColors['success']; ?>; }
        .floating-alert.error { background-color: <?php echo $tailwindColors['danger']; ?>; }
    </style>
</head>
<body class="bg-light-bg text-dark-text">

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

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="h-20 bg-white shadow-sm flex items-center justify-between px-6 md:px-8 border-b border-gray-200">
                <button id="mobileMenuButton" class="md:hidden text-gray-600 hover:text-primary focus:outline-none" aria-label="Abrir menu">
                    <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                <div class="text-xl font-semibold text-dark-text hidden sm:block">Meu Perfil</div>
                <div class="flex items-center space-x-3">
                    <button class="p-2 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary/50" aria-label="Notificações">
                        <svg class="w-6 h-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                    </button>
                    <div class="relative">
                        <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none" aria-label="Menu do utilizador" aria-haspopup="true" aria-expanded="false">
                            <img id="userAvatarSmall" src="<?php echo htmlspecialchars($headerAvatar); ?>" alt="Foto do Utilizador" class="w-10 h-10 rounded-full border-2 border-transparent hover:border-primary transition-colors">
                            <span id="userNameSmall" class="hidden lg:inline font-medium text-dark-text">Olá, <?php echo htmlspecialchars(explode(' ', $userName)[0]); ?></span>
                            <svg class="hidden lg:inline w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div id="userMenuDropdown" class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-xl z-20 hidden py-1 border border-gray-100">
                            <div class="px-4 py-3">
                                <p class="text-sm font-semibold text-dark-text"><?php echo htmlspecialchars($userName); ?></p>
                                <p class="text-xs text-light-text truncate"><?php echo htmlspecialchars($userEmail); ?></p>
                            </div>
                            <div class="border-t border-gray-100"></div>
                            <a href="perfil.php" class="block px-4 py-2 text-sm text-medium-text hover:bg-primary-light hover:text-primary">Meu Perfil</a>
                            <a href="configuracoes.php" class="block px-4 py-2 text-sm text-medium-text hover:bg-primary-light hover:text-primary">Configurações</a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-danger hover:bg-red-500/10">Sair</a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-light-bg p-6 md:p-8 space-y-8">
                
                <h2 class="text-3xl font-semibold text-dark-text sm:hidden">Meu Perfil</h2> 
                <?php if ($updateMessage): ?>
                    <div id="alertMessage" class="floating-alert success" role="alert" style="opacity:0; transform: translateY(-20px);">
                        <?php echo htmlspecialchars($updateMessage); ?>
                    </div>
                <?php endif; ?>
                <?php if ($updateError): ?>
                     <div id="alertMessage" class="floating-alert error" role="alert" style="opacity:0; transform: translateY(-20px);">
                        <?php echo htmlspecialchars($updateError); ?>
                    </div>
                <?php endif; ?>

                <section id="profileInfoSection" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-dark-text mb-6 border-b pb-3">Informações Pessoais</h3>
                    <form id="profileForm" method="POST" action="perfil.php" class="space-y-6" enctype="multipart/form-data">
                        <div class="flex flex-col items-center sm:flex-row sm:items-start gap-6">
                            <div class="relative group">
                                <img id="profileAvatar" src="<?php echo htmlspecialchars($profileAvatar); ?>" alt="Avatar do Utilizador" class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 group-hover:border-primary/50 transition-colors">
                                <label for="avatarUpload" class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-0 group-hover:bg-opacity-50 text-white opacity-0 group-hover:opacity-100 rounded-full cursor-pointer transition-opacity duration-300">
                                    <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" /><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" /></svg>
                                </label>
                                <input type="file" name="avatar" id="avatarUpload" class="hidden" accept="image/jpeg,image/png,image/gif">
                            </div>
                            <div class="flex-grow w-full">
                                <div>
                                    <label for="fullName" class="form-label">Nome Completo</label>
                                    <input type="text" id="fullName" name="fullName" class="form-input" value="<?php echo htmlspecialchars($userName); ?>" placeholder="O seu nome completo">
                                </div>
                                <div class="mt-4">
                                    <label for="email" class="form-label">Endereço de Email</label>
                                    <input type="email" id="email" name="email" class="form-input bg-gray-100 cursor-not-allowed" value="<?php echo htmlspecialchars($userEmail); ?>" placeholder="O seu email" readonly>
                                    <p class="text-xs text-light-text mt-1">O email não pode ser alterado.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="profession" class="form-label">Profissão</label>
                                <input type="text" id="profession" name="profession" class="form-input" value="<?php echo htmlspecialchars($userProfession); ?>" placeholder="Ex: Terapeuta Ocupacional">
                            </div>
                            <div>
                                <label for="crefito" class="form-label">CREFITO / Registo Profissional</label>
                                <input type="text" id="crefito" name="crefito" class="form-input" value="<?php echo htmlspecialchars($userCrefito); ?>" placeholder="O seu número de registo">
                            </div>
                        </div>
                        <div class="pt-4 text-right">
                            <button type="submit" name="saveProfile" class="btn btn-primary">Guardar Alterações</button>
                        </div>
                    </form>
                </section>

                <section id="subscriptionSection" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-dark-text mb-6 border-b pb-3">Minha Assinatura</h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-sm text-medium-text">Plano Atual:</p>
                            <p id="currentPlan" class="text-lg font-semibold text-primary"><?php echo htmlspecialchars($subscriptionData['currentPlan']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-medium-text">Estado:</p>
                            <p id="planStatus" class="text-lg font-medium <?php echo $subscriptionData['planStatus'] === 'Ativa' ? 'text-success' : ($subscriptionData['planStatus'] === '-' ? 'text-medium-text':'text-danger'); ?>"><?php echo htmlspecialchars($subscriptionData['planStatus']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-medium-text">Data de Renovação:</p>
                            <p id="renewalDate" class="text-lg font-medium text-dark-text"><?php echo htmlspecialchars($subscriptionData['renewalDate']); ?></p>
                        </div>
                        <div class="pt-4">
                            <a href="planos.php" class="btn btn-secondary">Gerir Assinatura</a>
                        </div>
                    </div>
                </section>

                <section id="passwordSection" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-dark-text mb-6 border-b pb-3">Alterar Palavra-passe</h3>
                    <form id="passwordForm" method="POST" action="perfil.php" class="space-y-6">
                        <div>
                            <label for="currentPassword" class="form-label">Palavra-passe Atual</label>
                            <input type="password" id="currentPassword" name="currentPassword" class="form-input" placeholder="A sua palavra-passe atual" required>
                        </div>
                        <div>
                            <label for="newPassword" class="form-label">Nova Palavra-passe</label>
                            <input type="password" id="newPassword" name="newPassword" class="form-input" placeholder="Mínimo 8 caracteres" required>
                        </div>
                        <div>
                            <label for="confirmNewPassword" class="form-label">Confirmar Nova Palavra-passe</label>
                            <input type="password" id="confirmNewPassword" name="confirmNewPassword" class="form-input" placeholder="Repita a nova palavra-passe" required>
                        </div>
                        <div class="pt-4 text-right">
                            <button type="submit" name="changePassword" class="btn btn-primary">Alterar Palavra-passe</button>
                        </div>
                    </form>
                </section>

                <section id="notificationSection" class="bg-white p-6 md:p-8 rounded-xl shadow-lg">
                    <h3 class="text-xl font-semibold text-dark-text mb-6 border-b pb-3">Preferências de Notificação</h3>
                    <form id="notificationForm" method="POST" action="perfil.php" class="space-y-4">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="notifyNewPodcasts" class="form-checkbox" <?php if($notificationPreferences['notifyNewPodcasts']) echo 'checked'; ?>>
                            <span class="text-medium-text">Notificar sobre novos podcasts</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="notifyNewOpportunities" class="form-checkbox" <?php if($notificationPreferences['notifyNewOpportunities']) echo 'checked'; ?>>
                            <span class="text-medium-text">Notificar sobre novas oportunidades (cursos, vagas)</span>
                        </label>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input type="checkbox" name="notifyPlatformNews" class="form-checkbox" <?php if($notificationPreferences['notifyPlatformNews']) echo 'checked'; ?>>
                            <span class="text-medium-text">Receber notícias e atualizações da plataforma</span>
                        </label>
                        <div class="pt-4 text-right">
                            <button type="submit" name="saveNotifications" class="btn btn-primary">Guardar Preferências</button>
                        </div>
                    </form>
                </section>

            </main>
        </div>
    </div>

    <div id="mobileSidebar" class="fixed inset-0 flex z-40 md:hidden hidden">
        <div id="mobileSidebarOverlay" class="fixed inset-0 bg-black/30" aria-hidden="true"></div>
        <aside class="relative flex-1 flex flex-col max-w-xs w-full bg-white shadow-xl">
             <div class="h-20 flex items-center justify-between px-4 border-b border-gray-200">
                <a href="inicio.php" class="text-3xl font-bold text-primary">audio to</a>
                <button id="closeMobileMenuButton" class="text-gray-500 hover:text-primary" aria-label="Fechar menu">
                    <svg class="w-7 h-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="inicio.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h7.5" /></svg><span class="font-medium">Início</span></a>
                <a href="podcasts.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z" /></svg><span class="font-medium">Podcasts</span></a>
                <a href="oportunidades.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.355a7.5 7.5 0 01-4.5 0m4.5 0v.75A2.25 2.25 0 0113.5 21h-3a2.25 2.25 0 01-2.25-2.25V18m7.5-7.5h-4.5m0 0a9 9 0 100 13.5h1.5a12.025 12.025 0 011.412-3.37A48.56 48.56 0 0112 10.5zm-3.75 0a9 9 0 110-13.5H9a12.025 12.025 0 00-1.412 3.37A48.56 48.56 0 0012 10.5z" /></svg><span class="font-medium">Oportunidades</span></a>
                <a href="favoritos.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.82.61l-4.725-2.885a.563.563 0 00-.652 0l-4.725 2.885a.562.562 0 01-.82-.61l1.285-5.385a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg><span class="font-medium">Meus Favoritos</span></a>
                <a href="planos.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.537m0 0L12 18.75l2.121-1.03m0 0L15 15.182M12 6l3 4.5M12 6l-3 4.5M12 18.75V21m-3.75-3H4.5M20.25 18H18M9 3.75h6M9 3.75H3.75m16.5 0H15m-6 12.75h6m-6 0H3.75m16.5 0H15" /></svg><span class="font-medium">Planos</span></a>
                <a href="perfil.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-primary-light hover:text-primary rounded-lg transition-colors duration-200 group active-nav-item"><svg class="w-6 h-6 mr-3 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg><span class="font-medium">Meu Perfil</span></a>
            </nav>
            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" class="flex items-center px-4 py-3 text-medium-text hover:bg-red-500/10 hover:text-red-600 rounded-lg transition-colors duration-200 group"><svg class="w-6 h-6 mr-3 text-gray-500 group-hover:text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg><span class="font-medium">Sair</span></a>
            </div>
        </aside>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const userMenuButton = document.getElementById('userMenuButton');
        const userMenuDropdown = document.getElementById('userMenuDropdown');
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const closeMobileMenuButton = document.getElementById('closeMobileMenuButton');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const mobileSidebarOverlay = document.getElementById('mobileSidebarOverlay');

        const avatarUploadInput = document.getElementById('avatarUpload');
        const profileAvatarImg = document.getElementById('profileAvatar');
        const userAvatarSmallImg = document.getElementById('userAvatarSmall');
        
        if (userMenuButton && userMenuDropdown) {
            userMenuButton.addEventListener('click', (event) => {
                event.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
                userMenuButton.setAttribute('aria-expanded', String(!userMenuDropdown.classList.contains('hidden')));
            });
            document.addEventListener('click', (event) => {
                if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden') && 
                    userMenuButton && !userMenuButton.contains(event.target) && 
                    !userMenuDropdown.contains(event.target)) {
                    userMenuDropdown.classList.add('hidden');
                    userMenuButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
        if (mobileMenuButton && mobileSidebar) mobileMenuButton.addEventListener('click', () => mobileSidebar.classList.remove('hidden'));
        if (closeMobileMenuButton && mobileSidebar) closeMobileMenuButton.addEventListener('click', () => mobileSidebar.classList.add('hidden'));
        if (mobileSidebarOverlay && mobileSidebar) mobileSidebarOverlay.addEventListener('click', () => mobileSidebar.classList.add('hidden'));
        
        function showFloatingAlert(message, type = 'success') {
            const existingAlert = document.querySelector('.floating-alert');
            if(existingAlert) existingAlert.remove();

            const alertDiv = document.createElement('div');
            alertDiv.className = `floating-alert ${type}`;
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            void alertDiv.offsetWidth; 
            alertDiv.classList.add('show');

            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => { alertDiv.remove(); }, 300);
            }, 3000);
        }
        
        const initialAlert = document.getElementById('alertMessage');
        if (initialAlert && initialAlert.textContent.trim() !== '') {
             setTimeout(() => {
                initialAlert.classList.add('show');
                 setTimeout(() => {
                    initialAlert.classList.remove('show');
                    // Optionally remove the element from DOM after fade out if it's dynamically added for each message
                    // initialAlert.remove(); 
                }, 3000 + 300); // Keep it for 3s then fade
            }, 100); // Pequeno delay para garantir que a transição funcione
        } else if (initialAlert) {
            // If the alert message is empty (e.g. on initial load without a message), remove the placeholder
            initialAlert.remove(); 
        }

        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(event) {
                // Client-side validation can be added here before allowing PHP to process
                // For example, checking if fullName is not empty:
                const fullNameInput = document.getElementById('fullName');
                if (fullNameInput && fullNameInput.value.trim() === '') {
                    event.preventDefault();
                    showFloatingAlert('O nome completo não pode estar vazio.', 'error');
                }
                // The actual submission and backend processing is handled by PHP on page reload
            });
        }

        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(event) {
                const newPassword = document.getElementById('newPassword').value;
                const confirmNewPassword = document.getElementById('confirmNewPassword').value;
                if (newPassword !== confirmNewPassword) {
                    event.preventDefault(); 
                    showFloatingAlert('A nova palavra-passe e a confirmação não coincidem.', 'error');
                    return;
                }
                if (newPassword.length < 8) {
                    event.preventDefault();
                    showFloatingAlert('A nova palavra-passe deve ter pelo menos 8 caracteres.', 'error');
                    return;
                }
            });
        }

        if (avatarUploadInput && profileAvatarImg) {
            avatarUploadInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profileAvatarImg.src = e.target.result;
                        if(userAvatarSmallImg) userAvatarSmallImg.src = e.target.result; 
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    });
    </script>
</body>
</html>
<?php
ob_end_flush(); // Enviar o buffer de saída e desativar o buffer
?>
