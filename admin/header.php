<?php
// admin/admin_header_component.php
// Este ficheiro contém o HTML e a lógica do cabeçalho da área administrativa (Bootstrap Version).

// Fallbacks caso as variáveis não estejam definidas (devem ser definidas pela página que inclui este header)
if (!isset($userName_for_header)) { // Using a distinct variable name to avoid conflicts if included page also has $userName
    $userName_for_header = $_SESSION['user_nome_completo'] ?? 'Admin';
}
if (!isset($userEmail_for_header)) { // Using a distinct variable name
    $userEmail_for_header = $_SESSION['user_email'] ?? 'admin@audioto.com';
}
if (!isset($avatarUrl_for_header)) { // Using a distinct variable name
    // Gera um avatar com as iniciais do $userName_for_header
    $initials_for_header = '';
    $nameParts_for_header = explode(' ', $userName_for_header);
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(substr($nameParts_for_header[0], 0, 1)) : 'A';
    if (count($nameParts_for_header) > 1) {
        $initials_for_header .= strtoupper(substr(end($nameParts_for_header), 0, 1));
    } elseif (strlen($nameParts_for_header[0]) > 1 && $initials_for_header === strtoupper(substr($nameParts_for_header[0], 0, 1))) {
        $initials_for_header .= strtoupper(substr($nameParts_for_header[0], 1, 1));
    }
    if(empty($initials_for_header) || strlen($initials_for_header) > 2) $initials_for_header = "AD"; // Admin Default

    // Using Bootstrap primary blue for avatar background as an example
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}

$userFirstName_for_header = explode(' ', $userName_for_header)[0];

// Note: The main page including this header should have Bootstrap CSS and JS linked.
// Font Awesome is also assumed for icons.
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top admin-header px-3 py-2">
    <div class="container-fluid">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="adminMobileSidebarToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>

        <a class="navbar-brand fw-bold text-primary d-none d-lg-block" href="index.php">
            <i class="fas fa-headphones-alt me-1"></i> Audio TO Admin
        </a>
         <a class="navbar-brand fw-bold text-primary d-lg-none" href="index.php">
            <i class="fas fa-headphones-alt"></i>
        </a>


        <ul class="navbar-nav ms-auto d-flex flex-row align-items-center">
            <li class="nav-item dropdown me-2 me-lg-3">
                <a class="nav-link" href="#" id="navbarDropdownNotifications" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações">
                    <i class="fas fa-bell fa-lg text-secondary"></i>
                    <span class="badge rounded-pill bg-danger position-absolute top-0 start-50 translate-middle-x" style="font-size: 0.6em; padding: 0.25em 0.4em;">3</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg mt-2" aria-labelledby="navbarDropdownNotifications" style="width: 320px;">
                    <li class="dropdown-header text-center bg-light py-2">3 Novas Notificações</li>
                    <li><a class="dropdown-item d-flex align-items-start py-2" href="#">
                        <i class="fas fa-podcast text-info me-2 mt-1"></i> 
                        <div>
                            <small class="fw-bold">Novo Podcast Adicionado</small><br>
                            <small class="text-muted">"Explorando o Universo" foi publicado.</small>
                        </div>
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-start py-2" href="#">
                        <i class="fas fa-user-plus text-success me-2 mt-1"></i>
                         <div>
                            <small class="fw-bold">Novo Assinante</small><br>
                            <small class="text-muted">Maria Silva subscreveu o plano Premium.</small>
                        </div>
                    </a></li>
                    <li><a class="dropdown-item d-flex align-items-start py-2" href="#">
                        <i class="fas fa-comment-dots text-warning me-2 mt-1"></i>
                        <div>
                            <small class="fw-bold">Novo Comentário</small><br>
                            <small class="text-muted">João comentou no episódio "Tecnologia".</small>
                        </div>
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center text-primary small py-2" href="#">Ver Todas as Notificações</a></li>
                </ul>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center p-1" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($avatarUrl_for_header); ?>" alt="Foto do Administrador" class="rounded-circle border me-2" style="width: 38px; height: 38px; object-fit: cover;">
                    <span class="d-none d-lg-inline-block text-dark fw-medium small">Olá, <?php echo htmlspecialchars($userFirstName_for_header); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg mt-2" aria-labelledby="navbarDropdownUser">
                    <li>
                        <div class="px-3 py-2 border-bottom">
                            <p class="mb-0 fw-bold small text-dark"><?php echo htmlspecialchars($userName_for_header); ?></p>
                            <p class="mb-0 text-muted small text-truncate"><?php echo htmlspecialchars($userEmail_for_header); ?></p>
                        </div>
                    </li>
                    <li><a class="dropdown-item py-2 d-flex align-items-center" href="../perfil_page_v1.html"> {/* Assuming perfil_page_v1.html exists */}
                        <i class="fas fa-user-circle fa-fw me-2 text-secondary"></i> Meu Perfil
                    </a></li>
                    <li><a class="dropdown-item py-2 d-flex align-items-center" href="../inicio.php">
                        <i class="fas fa-external-link-alt fa-fw me-2 text-secondary"></i> Ver Site Principal
                    </a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2 d-flex align-items-center text-danger" href="../logout_handler.php"> {/* Assuming logout_handler.php exists */}
                        <i class="fas fa-sign-out-alt fa-fw me-2"></i> Sair
                    </a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<?php
// JavaScript for sidebar toggle (example, if your sidebar needs it)
// This script assumes your sidebar has an ID 'adminSidebar' (from the main page)
// and the toggle button has ID 'adminMobileSidebarToggle'.
// You might need to adjust this based on your actual sidebar implementation.
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileSidebarToggleButton = document.getElementById('adminMobileSidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar'); // Assuming this ID is on your sidebar element

    if (mobileSidebarToggleButton && adminSidebar) {
        mobileSidebarToggleButton.addEventListener('click', function() {
            adminSidebar.classList.toggle('active'); 
            // 'active' class would control visibility or margin-left for mobile view, defined in your CSS
            // For example, in your main page CSS:
            // #adminSidebar { margin-left: -260px; /* hidden by default on mobile */ }
            // #adminSidebar.active { margin-left: 0; /* shown */ }
        });
    }
});
</script>
