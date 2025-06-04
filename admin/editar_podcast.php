<?php
// admin/editar_podcast.php

require_once __DIR__ . '/../sessao/session_handler.php'; // Corrigido o caminho
requireAdmin('../login.php'); 
require_once __DIR__ . '/../db/db_connect.php'; // Corrigido o caminho

$pageTitle = "Editar Podcast";
$podcast = null;
$id_podcast_editar = null;

// Obter informações do utilizador da sessão para o header
$userName = $_SESSION['user_nome_completo'] ?? 'Admin';
$userEmail = $_SESSION['user_email'] ?? 'admin@audioto.com';
$avatarUrl = $_SESSION['user_avatar_url'] ?? null; 
// A lógica de fallback do avatarUrl está no admin_header_component.php

// Verificar se um ID de podcast foi passado via GET
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $id_podcast_editar = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, a.id_categoria 
             FROM podcasts p 
             JOIN assuntos_podcast a ON p.id_assunto = a.id_assunto
             WHERE p.id_podcast = :id_podcast"
        );
        $stmt->bindParam(':id_podcast', $id_podcast_editar, PDO::PARAM_INT);
        $stmt->execute();
        $podcast = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$podcast) {
            $_SESSION['feedback_message'] = "Podcast não encontrado para edição.";
            $_SESSION['feedback_type'] = "error";
            header('Location: gerir_podcasts.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar podcast para edição: " . $e->getMessage());
        $_SESSION['feedback_message'] = "Erro ao carregar dados do podcast.";
        $_SESSION['feedback_type'] = "error";
        header('Location: gerir_podcasts.php');
        exit;
    }
} else {
    $_SESSION['feedback_message'] = "ID do podcast inválido ou não fornecido.";
    $_SESSION['feedback_type'] = "error";
    header('Location: gerir_podcasts.php');
    exit;
}

// Buscar categorias e todos os assuntos para os dropdowns
$categorias = [];
$todos_assuntos_json = '[]'; // Para JavaScript
try {
    $stmt_categorias = $pdo->query("SELECT id_categoria, nome_categoria FROM categorias_podcast ORDER BY nome_categoria ASC");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

    $stmt_assuntos = $pdo->query("SELECT id_assunto, id_categoria, nome_assunto FROM assuntos_podcast ORDER BY nome_assunto ASC");
    $todos_assuntos_php_array = $stmt_assuntos->fetchAll(PDO::FETCH_ASSOC);
    $todos_assuntos_json = json_encode($todos_assuntos_php_array); // Passar para o JS
} catch (PDOException $e) {
    error_log("Erro ao buscar dados para formulário de edição de podcast: " . $e->getMessage());
}

// Buscar planos de assinatura
$planos_assinatura = [];
try {
    $stmt_planos = $pdo->query("SELECT id_plano, nome_plano FROM planos_assinatura WHERE ativo = TRUE ORDER BY nome_plano ASC");
    $planos_assinatura = $stmt_planos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar planos de assinatura: " . $e->getMessage());
}

// Mensagens de feedback da sessão e dados do formulário (se houver erro na submissão anterior)
$form_message = $_SESSION['form_message'] ?? null;
$form_message_type = $_SESSION['form_message_type'] ?? null;
$form_data_podcast = $_SESSION['form_data_podcast'] ?? $podcast; 

unset($_SESSION['form_message']);
unset($_SESSION['form_message_type']);
unset($_SESSION['form_data_podcast']);

// Determinar o tipo de material de apoio existente para pré-selecionar o dropdown
$tipo_material_existente = 'nenhum';
$url_material_existente_para_campo_texto = ''; // Para o campo de URL externa

if (!empty($form_data_podcast['link_material_apoio'])) {
    // Verifica se é um URL completo (http ou https)
    if (filter_var($form_data_podcast['link_material_apoio'], FILTER_VALIDATE_URL) && 
        (strpos($form_data_podcast['link_material_apoio'], 'http://') === 0 || strpos($form_data_podcast['link_material_apoio'], 'https://') === 0)) {
        $tipo_material_existente = 'link_externo';
        $url_material_existente_para_campo_texto = $form_data_podcast['link_material_apoio'];
    } 
    // Verifica se é um caminho relativo para um PDF (contém .pdf e não é URL completo)
    else if (strpos(strtolower($form_data_podcast['link_material_apoio']), '.pdf') !== false) {
        $tipo_material_existente = 'upload_pdf';
        // Não preenchemos $url_material_existente_para_campo_texto aqui, pois é um upload
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?>: <?php echo htmlspecialchars($podcast['titulo_podcast']); ?> - Audio TO Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { 
            theme: {
                extend: {
                    colors: { 'primary': '#007AFF', 'primary-dark': '#0056b3', 'secondary': '#5856D6', 'light-bg': '#F9FAFB', 'dark-text': '#1F2937', 'medium-text': '#4B5563', 'light-text': '#6B7280', 'admin-bg': '#EDF2F7', 'danger-bg': '#FEE2E2', 'danger-text': '#B91C1C', 'success-bg': '#D1FAE5', 'success-text': '#065F46', },
                    fontFamily: { 'sans': ['Inter', 'ui-sans-serif', 'system-ui'], 'raleway': ['Raleway', 'sans-serif'], }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Raleway:wght@700;800&display=swap" rel="stylesheet">
    <style>
        html, body { height: 100%; font-family: 'Inter', sans-serif; }
        body { display: flex; flex-direction: column; }
        .main-container { flex-grow: 1; }
        #adminMainNav a.bg-primary svg, #adminMobileMainNav a.bg-primary svg { color: white !important; }
        .form-input, .form-textarea, .form-select {
            @apply w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary/50 focus:border-primary outline-none transition-colors duration-200 text-sm placeholder-gray-400 bg-white;
        }
        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-1.5;
        }
        .btn { @apply font-semibold py-2.5 px-6 rounded-lg transition-colors duration-150 text-sm shadow-sm hover:shadow; }
        .btn-primary { @apply bg-primary text-white hover:bg-primary-dark; }
        .btn-secondary { @apply bg-gray-200 text-gray-700 hover:bg-gray-300; }
        .message-box { 
            @apply text-sm p-3 rounded-md border mb-6; /* Aumentei a margem inferior */
        }
        .error-message {
            @apply bg-danger-bg text-danger-text border-danger-text/50;
        }
        .success-message {
            @apply bg-success-bg text-success-text border-success-text/50;
        }
    </style>
</head>
<body class="bg-admin-bg text-dark-text">

    <div class="flex h-screen main-container">
        
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            
            <?php require __DIR__ . '/header.php'; ?>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-admin-bg p-6 md:p-8 space-y-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h2 class="text-2xl md:text-3xl font-semibold text-dark-text">
                        Editar Podcast: <span class="text-primary font-bold"><?php echo htmlspecialchars($podcast['titulo_podcast']); ?></span>
                    </h2>
                    <a href="gerir_podcasts.php" class="text-sm text-primary hover:underline font-medium">&larr; Voltar para Gerir Podcasts</a>
                </div>
                
                <div class="bg-white p-6 md:p-8 rounded-xl shadow-xl max-w-3xl mx-auto">
                    <?php if ($form_message): ?>
                        <div class="message-box <?php echo $form_message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                            <?php echo htmlspecialchars($form_message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="processa_editar_podcast.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="id_podcast" value="<?php echo htmlspecialchars($id_podcast_editar); ?>">
                        <input type="hidden" name="url_audio_atual" value="<?php echo htmlspecialchars($podcast['url_audio'] ?? ''); ?>">
                        <input type="hidden" name="link_material_apoio_atual" value="<?php echo htmlspecialchars($podcast['link_material_apoio'] ?? ''); ?>">
                        <input type="hidden" name="slug_categoria_atual_para_pasta" value=""> <input type="hidden" name="slug_assunto_atual_para_pasta" value=""> <div>
                            <label for="titulo_podcast" class="form-label">Título do Podcast <span class="text-red-500">*</span></label>
                            <input type="text" id="titulo_podcast" name="titulo_podcast" class="form-input" value="<?php echo htmlspecialchars($form_data_podcast['titulo_podcast'] ?? ''); ?>" required>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="id_categoria" class="form-label">Categoria <span class="text-red-500">*</span></label>
                                <select id="id_categoria" name="id_categoria" class="form-select" required>
                                    <option value="">Selecione uma Categoria...</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id_categoria']; ?>" data-slug-categoria="<?php echo htmlspecialchars($cat['slug_categoria']); ?>" <?php echo (isset($form_data_podcast['id_categoria']) && $form_data_podcast['id_categoria'] == $cat['id_categoria']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nome_categoria']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="id_assunto" class="form-label">Assunto <span class="text-red-500">*</span></label>
                                <select id="id_assunto" name="id_assunto" class="form-select" required disabled>
                                    <option value="">Primeiro selecione uma categoria...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label for="descricao_podcast" class="form-label">Descrição do Podcast</label>
                            <textarea id="descricao_podcast" name="descricao_podcast" rows="4" class="form-textarea"><?php echo htmlspecialchars($form_data_podcast['descricao_podcast'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr class="my-2">
                        <p class="text-md font-semibold text-gray-700">Ficheiro de Áudio</p>
                        <div>
                            <label for="audio_file" class="form-label">Substituir Áudio (MP3, OGG, WAV, M4A)</label>
                            <?php if (!empty($podcast['url_audio'])): ?>
                                <p class="text-xs text-gray-600 mb-2">Áudio atual: 
                                    <a href="../<?php echo htmlspecialchars($podcast['url_audio']); ?>" target="_blank" class="text-primary underline hover:text-primary-dark">
                                        <?php echo basename($podcast['url_audio']); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                 <p class="text-xs text-red-500 mb-2">Nenhum áudio associado. Por favor, carregue um.</p>
                            <?php endif; ?>
                            <input type="file" id="audio_file" name="audio_file" class="form-input p-2 text-sm" accept="audio/mpeg,audio/ogg,audio/wav,audio/mp4,audio/x-m4a" <?php echo empty($podcast['url_audio']) ? 'required' : ''; ?>>
                            <p class="text-xs text-gray-500 mt-1">Selecione um novo ficheiro apenas se desejar substituir o atual. Máx: 50MB.</p>
                        </div>
                        
                        <hr class="my-2">
                        <p class="text-md font-semibold text-gray-700">Material de Apoio (Opcional)</p>

                        <div>
                            <label for="tipo_material_apoio" class="form-label">Tipo de Material de Apoio</label>
                            <select id="tipo_material_apoio" name="tipo_material_apoio" class="form-select">
                                <option value="nenhum" <?php echo ($tipo_material_existente == 'nenhum') ? 'selected' : ''; ?>>Nenhum</option>
                                <option value="upload_pdf" <?php echo ($tipo_material_existente == 'upload_pdf') ? 'selected' : ''; ?>>Substituir/Adicionar PDF</option>
                                <option value="link_externo" <?php echo ($tipo_material_existente == 'link_externo') ? 'selected' : ''; ?>>Usar/Alterar Link Externo</option>
                            </select>
                        </div>

                        <div id="campo_upload_pdf" class="<?php echo ($tipo_material_existente !== 'upload_pdf') ? 'hidden' : ''; ?>">
                            <label for="pdf_file" class="form-label">Ficheiro PDF de Apoio</label>
                            <?php if ($tipo_material_existente === 'upload_pdf' && !empty($podcast['link_material_apoio'])): ?>
                                <p class="text-xs text-gray-600 mb-2">PDF atual: 
                                    <a href="../<?php echo htmlspecialchars($podcast['link_material_apoio']); ?>" target="_blank" class="text-primary underline hover:text-primary-dark">
                                        <?php echo basename($podcast['link_material_apoio']); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <input type="file" id="pdf_file" name="pdf_file" class="form-input p-2 text-sm" accept=".pdf">
                            <p class="text-xs text-gray-500 mt-1">Envie um novo PDF para adicionar ou substituir o atual. Máx: 10MB.</p>
                        </div>
                        
                        <div id="campo_link_externo" class="<?php echo ($tipo_material_existente !== 'link_externo') ? 'hidden' : ''; ?>">
                            <label for="link_material_apoio_url" class="form-label">URL do Material de Apoio Externo</label>
                            <input type="url" id="link_material_apoio_url" name="link_material_apoio_url" class="form-input" placeholder="https://exemplo.com/artigo" value="<?php echo ($tipo_material_existente === 'link_externo') ? htmlspecialchars($url_material_existente_para_campo_texto) : ''; ?>">
                        </div>
                        
                        <hr class="my-2">
                        <p class="text-md font-semibold text-gray-700">Configurações de Acesso</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="visibilidade" class="form-label">Visibilidade <span class="text-red-500">*</span></label>
                                <select id="visibilidade" name="visibilidade" class="form-select" required>
                                    <option value="restrito_assinantes" <?php echo (isset($form_data_podcast['visibilidade']) && $form_data_podcast['visibilidade'] == 'restrito_assinantes') ? 'selected' : ''; ?>>Restrito a Assinantes</option>
                                    <option value="publico" <?php echo (isset($form_data_podcast['visibilidade']) && $form_data_podcast['visibilidade'] == 'publico') ? 'selected' : ''; ?>>Público</option>
                                </select>
                            </div>

                            <div id="campo_plano_minimo" style="<?php echo (isset($form_data_podcast['visibilidade']) && $form_data_podcast['visibilidade'] == 'publico') ? 'display:none;' : 'display:block;'; ?>">
                                <label for="id_plano_minimo" class="form-label">Plano Mínimo para Acesso (se restrito)</label>
                                <select id="id_plano_minimo" name="id_plano_minimo" class="form-select">
                                    <option value="">Todos os Assinantes com Plano Ativo</option>
                                    <?php foreach ($planos_assinatura as $plano): ?>
                                        <option value="<?php echo $plano['id_plano']; ?>" <?php echo (isset($form_data_podcast['id_plano_minimo']) && $form_data_podcast['id_plano_minimo'] == $plano['id_plano']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($plano['nome_plano']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between items-center">
                             <a href="gerir_podcasts.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" name="editar_podcast_submit" class="btn btn-primary">
                                Guardar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        const todosAssuntos = <?php echo $todos_assuntos_json; ?>;
        const categoriaSelect = document.getElementById('id_categoria');
        const assuntoSelect = document.getElementById('id_assunto');
        const tipoMaterialSelect = document.getElementById('tipo_material_apoio');
        const campoUploadPdf = document.getElementById('campo_upload_pdf');
        const inputPdfFile = document.getElementById('pdf_file');
        const campoLinkExterno = document.getElementById('campo_link_externo');
        const inputLinkMaterialUrl = document.getElementById('link_material_apoio_url');
        const visibilidadeSelect = document.getElementById('visibilidade');
        const campoPlanoMinimo = document.getElementById('campo_plano_minimo');
        const inputPlanoMinimo = document.getElementById('id_plano_minimo');

        const idAssuntoAtualPHP = <?php echo json_encode($form_data_podcast['id_assunto'] ?? null); ?>;
        const idCategoriaAtualPHP = <?php echo json_encode($form_data_podcast['id_categoria'] ?? null); ?>;
        
        // Campos ocultos para slugs atuais (para renomear pastas se slugs mudarem)
        const slugCategoriaAtualInput = document.querySelector('input[name="slug_categoria_atual_para_pasta"]');
        const slugAssuntoAtualInput = document.querySelector('input[name="slug_assunto_atual_para_pasta"]');


        function popularAssuntos(idCategoriaSelecionada, idAssuntoPreSelecionado = null) {
            assuntoSelect.innerHTML = '<option value="">Carregando...</option>';
            assuntoSelect.disabled = true;

            const assuntosFiltrados = todosAssuntos.filter(assunto => assunto.id_categoria == idCategoriaSelecionada);
            
            assuntoSelect.innerHTML = '<option value="">Selecione um Assunto...</option>';
            if (assuntosFiltrados.length > 0) {
                assuntosFiltrados.forEach(assunto => {
                    const option = document.createElement('option');
                    option.value = assunto.id_assunto;
                    option.textContent = assunto.nome_assunto;
                    // Adiciona data-slug-assunto ao option
                    option.dataset.slugAssunto = assunto.slug_assunto; 
                    if (idAssuntoPreSelecionado && assunto.id_assunto == idAssuntoPreSelecionado) {
                        option.selected = true;
                         if(slugAssuntoAtualInput) slugAssuntoAtualInput.value = assunto.slug_assunto; // Preenche slug do assunto atual
                    }
                    assuntoSelect.appendChild(option);
                });
                assuntoSelect.disabled = false;
            } else {
                assuntoSelect.innerHTML = '<option value="">Nenhum assunto para esta categoria</option>';
            }
        }
        
        function atualizarSlugsOcultos() {
            const selectedCategoriaOption = categoriaSelect.options[categoriaSelect.selectedIndex];
            if (selectedCategoriaOption && slugCategoriaAtualInput) {
                slugCategoriaAtualInput.value = selectedCategoriaOption.dataset.slugCategoria || '';
            }

            const selectedAssuntoOption = assuntoSelect.options[assuntoSelect.selectedIndex];
            if (selectedAssuntoOption && slugAssuntoAtualInput) {
                slugAssuntoAtualInput.value = selectedAssuntoOption.dataset.slugAssunto || '';
            }
        }


        categoriaSelect.addEventListener('change', function() {
            popularAssuntos(this.value); 
            atualizarSlugsOcultos(); // Atualiza slugs quando a categoria muda
        });
        
        assuntoSelect.addEventListener('change', function() {
            atualizarSlugsOcultos(); // Atualiza slug do assunto quando ele muda
        });


        tipoMaterialSelect.addEventListener('change', function() {
            campoUploadPdf.classList.add('hidden');
            inputPdfFile.required = false; 
            campoLinkExterno.classList.add('hidden');
            inputLinkMaterialUrl.required = false; 

            if (this.value === 'upload_pdf') {
                campoUploadPdf.classList.remove('hidden');
                // inputPdfFile.required = true; // Não tornar obrigatório ao editar, pois pode já existir
            } else if (this.value === 'link_externo') {
                campoLinkExterno.classList.remove('hidden');
                // inputLinkMaterialUrl.required = true; // Não tornar obrigatório ao editar
            }
        });

        visibilidadeSelect.addEventListener('change', function() {
            if (this.value === 'restrito_assinantes') {
                campoPlanoMinimo.style.display = 'block';
            } else {
                campoPlanoMinimo.style.display = 'none';
                inputPlanoMinimo.value = ''; 
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const adminUserMenuButton = document.getElementById('adminUserMenuButton');
            if (adminUserMenuButton) { /* ... (lógica do menu dropdown admin) ... */ }
            const adminMobileMenuButton = document.getElementById('adminMobileMenuButton');
            if (adminMobileMenuButton) { /* ... (lógica da sidebar mobile admin) ... */ }

            if (idCategoriaAtualPHP) {
                categoriaSelect.value = idCategoriaAtualPHP; 
                popularAssuntos(idCategoriaAtualPHP, idAssuntoAtualPHP);
            } else if (categoriaSelect.options.length > 1 && categoriaSelect.value) { 
                 popularAssuntos(categoriaSelect.value, idAssuntoAtualPHP);
            }
            atualizarSlugsOcultos(); // Chamar para definir os slugs iniciais
            
            tipoMaterialSelect.dispatchEvent(new Event('change'));
            visibilidadeSelect.dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>
