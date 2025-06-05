<?php
// admin/admin_sidebar_component.php
// Este ficheiro contém o HTML e a lógica da sidebar da área administrativa (Bootstrap Version).
// A verificação de sessão e função de admin já deve ter sido feita
// na página que inclui este componente (ex: admin/index.php).

// Determinar a página atual para o link ativo
$current_page_admin = basename($_SERVER['PHP_SELF']);

// Define an array of navigation items for easier management
$nav_items = [
    [
        'href' => 'index.php',
        'icon' => 'fas fa-chart-line',
        'text' => 'Painel',
        'page_id' => 'index.php'
    ],
    [
        'href' => 'gerir_categorias.php',
        'icon' => 'fas fa-tags',
        'text' => 'Categorias',
        'page_id' => 'gerir_categorias.php'
    ],
    [
        'href' => 'gerir_assuntos.php',
        'icon' => 'fas fa-bookmark',
        'text' => 'Assuntos',
        'page_id' => 'gerir_assuntos.php'
    ],
    [
        'href' => 'adicionar_podcast.php',
        'icon' => 'fas fa-plus-circle',
        'text' => 'Adicionar Podcast',
        'page_id' => 'adicionar_podcast.php'
    ],
    [
        'href' => 'gerir_podcasts.php',
        'icon' => 'fas fa-podcast',
        'text' => 'Gerir Podcasts',
        'page_id' => 'gerir_podcasts.php'
    ],
    [
        'href' => 'gerir_noticias.php',
        'icon' => 'fas fa-newspaper',
        'text' => 'Notícias',
        'page_id' => 'gerir_noticias.php'
    ],
    [
        'href' => 'gerir_oportunidades.php',
        'icon' => 'fas fa-bullhorn',
        'text' => 'Oportunidades',
        'page_id' => 'gerir_oportunidades.php'
    ],
    [
        'href' => 'gerir_utilizadores.php',
        'icon' => 'fas fa-users-cog',
        'text' => 'Utilizadores',
        'page_id' => 'gerir_utilizadores.php'
    ]
];

// Note: The main page including this sidebar should have Bootstrap CSS and JS linked.
// Font Awesome is also assumed for icons.
// This sidebar is designed to be toggled on mobile via a button in the header.
// The ID 'adminSidebar' is used for this purpose.
?>
<div class="d-flex flex-column flex-shrink-0 p-3 text-white bg-dark shadow-lg" id="adminSidebar" style="width: 280px; min-height: 100vh;">
    <a href="index.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none p-2">
        <i class="fas fa-headphones-alt fa-2x me-2"></i>
        <span class="fs-4 fw-bold">AudioTO <small class="fw-light fs-6">Admin</small></span>
    </a>
    <hr class="border-secondary">
    <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($nav_items as $item): ?>
        <li class="nav-item">
            <a href="<?php echo htmlspecialchars($item['href']); ?>" 
               class="nav-link <?php echo ($current_page_admin == $item['page_id']) ? 'active bg-primary' : 'text-white'; ?> py-2 ps-3 pe-2"
               aria-current="<?php echo ($current_page_admin == $item['page_id']) ? 'page' : 'false'; ?>">
                <i class="<?php echo htmlspecialchars($item['icon']); ?> fa-fw me-2"></i>
                <?php echo htmlspecialchars($item['text']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <hr class="border-secondary">
    <div class="px-2 pb-2">
        <a href="../inicio.php" class="d-flex align-items-center text-white text-decoration-none py-2 ps-3 pe-2 rounded hover-bg-secondary">
            <i class="fas fa-external-link-alt fa-fw me-2"></i>
            Ver Site Principal
        </a>
        <a href="../logout_handler.php" class="d-flex align-items-center text-warning text-decoration-none py-2 ps-3 pe-2 rounded hover-bg-secondary mt-1">
            <i class="fas fa-sign-out-alt fa-fw me-2"></i>
            Sair
        </a>
    </div>
</div>

<style>
    #adminSidebar .nav-link.active,
    #adminSidebar .nav-link:hover {
        background-color: #0d6efd; /* Bootstrap primary blue */
        color: white !important;
    }
    #adminSidebar .nav-link:not(.active):hover {
        background-color: rgba(255, 255, 255, 0.1); /* Subtle hover for non-active links */
    }
    #adminSidebar .hover-bg-secondary:hover {
         background-color: rgba(255, 255, 255, 0.1);
    }
    #adminSidebar hr.border-secondary {
        border-top: 1px solid rgba(255, 255, 255, 0.15);
    }
</style>

<?php
// This sidebar component does not include the mobile-specific duplicate structure.
// It's assumed the main page layout will handle making this sidebar responsive
// (e.g., using Bootstrap's Offcanvas component or custom CSS to hide/show it
// based on the toggle button in the admin_header_component.php).
// The header component's JavaScript `adminMobileSidebarToggle` button should target the `id="adminSidebar"`.
?>
