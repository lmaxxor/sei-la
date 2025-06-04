<?php
// admin/gerir_oportunidades.php

// --- DEBUGGING: Show all PHP errors. Remove or comment out for production. ---
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// --- END DEBUGGING ---

// Gestor de Sessão e Autenticação (presumido seguro e funcional)
require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php'); // Garante que apenas administradores acessem

// Conexão com Banco de Dados (presumido seguro e funcional)
require_once __DIR__ . '/../db/db_connect.php'; // $pdo deve estar disponível aqui

// Função para sanitizar texto para exibição segura em HTML
function sanitizarParaHTML(string $texto = null): string {
    return htmlspecialchars(trim($texto ?? ''), ENT_QUOTES, 'UTF-8');
}

// Função para sanitizar texto removendo tags HTML (útil para dados que não devem conter HTML)
function removerTagsHTML(string $texto = null): string {
    return strip_tags(trim($texto ?? ''));
}


/**
 * Gera um slug único para um texto.
 * NOTA: Esta função está configurada para 'podcasts'.
 * Para usar com 'oportunidades', a tabela e colunas (slug_oportunidade, id_oportunidade) precisam ser ajustadas.
 * Atualmente NÃO É UTILIZADA neste script para 'oportunidades'.
 */
function gerarSlugUnicoOportunidade(string $texto, PDO $pdo, int $id_oportunidade_atual = null): string {
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = strtolower($slug);
    if (empty($slug)) {
        $slug = 'oportunidade-' . bin2hex(random_bytes(4)); // Gera um ID aleatório mais curto
    }

    $i = 1;
    $original_slug = $slug;
    // ADAPTAR: Esta consulta deve ser para a tabela 'oportunidades' e coluna 'slug_oportunidade'
    $sql_check = "SELECT id_oportunidade FROM oportunidades WHERE slug_oportunidade = :slug";
    if ($id_oportunidade_atual !== null) {
        $sql_check .= " AND id_oportunidade != :id_atual";
    }
    $stmt = $pdo->prepare($sql_check);

    do {
        $params = [':slug' => $slug];
        if ($id_oportunidade_atual !== null) {
            $params[':id_atual'] = $id_oportunidade_atual;
        }
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $slug = $original_slug . '-' . $i; $i++;
        } else {
            break;
        }
    } while (true);
    return $slug;
}

/**
 * Apaga uma pasta e todo o seu conteúdo recursivamente.
 * NOTA: Não utilizada diretamente neste script de gerenciamento de oportunidades.
 * Usar com cautela.
 */
function apagarPastaRecursivamente(string $dir): bool {
    if (!is_dir($dir)) {
        return false;
    }
    $objects = scandir($dir);
    if ($objects === false) return false; // Falha ao ler o diretório

    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path) && !is_link($path)) {
                apagarPastaRecursivamente($path);
            } else {
                @unlink($path); // Suprimir erros se o arquivo não puder ser excluído
            }
        }
    }
    return @rmdir($dir); // Suprimir erros
}

// Função de resposta JSON para AJAX
function enviarRespostaJSON(bool $ok, string $msg, array $extra = []): void {
    if (ob_get_level() > 0) { // Limpa qualquer buffer de saída acidental
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');
    // NOTA: Para maior segurança, considere adicionar cabeçalhos de segurança aqui:
    // header("X-Content-Type-Options: nosniff");
    // header("X-Frame-Options: DENY");
    // header("Content-Security-Policy: default-src 'self'"); // Ajustar conforme necessário
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

// --- PROCESSAMENTO DE AÇÕES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // TODO: Implementar verificação de Token CSRF para todas as ações POST.
    // Ex: if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    //     enviarRespostaJSON(false, 'Erro de segurança. Ação bloqueada.');
    // }

    $action = $_POST['action'];
    // Caminhos de upload (não usados neste CRUD específico, mas definidos)
    // $projectRootPath = dirname(__DIR__); // __DIR__ é 'admin', dirname(__DIR__) é a raiz do projeto
    // $baseUploadPathOnServer = $projectRootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
    // $baseUploadPathForDB = 'uploads/';

    if ($action === 'list_oportunidades') {
        $filtro_tipo = $_POST['filtro_tipo'] ?? 'todos';
        $busca_titulo_raw = $_POST['busca_titulo'] ?? null;
        $busca_titulo = !empty($busca_titulo_raw) ? trim($busca_titulo_raw) : null;

        $sql = "SELECT
                    id_oportunidade, tipo_oportunidade, titulo_oportunidade,
                    SUBSTRING(descricao_oportunidade, 1, 100) as descricao_curta,
                    link_oportunidade, data_publicacao, ativo, fonte_oportunidade, tags
                FROM oportunidades
                WHERE 1=1";

        $params = [];
        $tipos_validos = ['curso', 'webinar', 'artigo', 'vaga', 'evento', 'outro']; // Para validação
        if (!empty($filtro_tipo) && $filtro_tipo !== 'todos') {
            if (in_array($filtro_tipo, $tipos_validos, true)) {
                $sql .= " AND tipo_oportunidade = :tipo_oportunidade";
                $params[':tipo_oportunidade'] = $filtro_tipo;
            } else {
                enviarRespostaJSON(false, 'Tipo de filtro inválido.');
            }
        }
        if ($busca_titulo !== null) {
            $sql .= " AND titulo_oportunidade LIKE :titulo";
            $params[':titulo'] = '%' . $busca_titulo . '%';
        }
        $sql .= " ORDER BY data_publicacao DESC, id_oportunidade DESC"; // Adiciona desempate

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $oportunidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Sanitizar dados para exibição no JS, especialmente se puderem conter HTML e o JS não tratar 100%
            // O JS já está usando escapeHTML, então aqui focamos em manter os dados limpos para a lógica.
            // No entanto, é uma boa prática garantir que os dados enviados estejam na forma esperada.
            foreach ($oportunidades as &$op) {
                $op['titulo_oportunidade'] = sanitizarParaHTML($op['titulo_oportunidade']);
                $op['descricao_curta'] = sanitizarParaHTML($op['descricao_curta']);
                $op['fonte_oportunidade'] = sanitizarParaHTML($op['fonte_oportunidade']);
                // link_oportunidade é uma URL, não precisa de htmlspecialchars aqui se o JS tratar
                // tipo_oportunidade será usado para lógica, manter como está.
            }
            unset($op);

            enviarRespostaJSON(true, 'Oportunidades listadas.', ['oportunidades' => $oportunidades]);
        } catch (PDOException $e) {
            error_log("Erro PDO ao listar oportunidades: " . $e->getMessage());
            enviarRespostaJSON(false, 'Erro ao buscar oportunidades. Tente novamente mais tarde.');
        }
    }

    elseif ($action === 'get_oportunidade_details') {
        $id_oportunidade = filter_input(INPUT_POST, 'id_oportunidade', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if (!$id_oportunidade) {
            enviarRespostaJSON(false, 'ID da oportunidade inválido.');
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM oportunidades WHERE id_oportunidade = :id_oportunidade");
            $stmt->bindParam(':id_oportunidade', $id_oportunidade, PDO::PARAM_INT);
            $stmt->execute();
            $oportunidade_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oportunidade_data) {
                // Os dados serão preenchidos em campos de formulário, que por padrão tratam como texto.
                // htmlspecialchars não é estritamente necessário aqui, mas não prejudica se os valores
                // fossem acidentalmente renderizados como HTML em outro lugar antes de chegar ao JS.
                // A sanitização principal ocorrerá na SAÍDA (seja no PHP para renderização de página ou no JS).
                // Para os inputs datetime-local, o formato específico é necessário.
                $oportunidade_data['data_evento_inicio_formato'] = '';
                if (!empty($oportunidade_data['data_evento_inicio'])) {
                    try {
                        $dt_inicio = new DateTime($oportunidade_data['data_evento_inicio']);
                        $oportunidade_data['data_evento_inicio_formato'] = $dt_inicio->format('Y-m-d\TH:i');
                    } catch (Exception $ex) {/* Ignorar data inválida */}
                }
                $oportunidade_data['data_evento_fim_formato'] = '';
                if (!empty($oportunidade_data['data_evento_fim'])) {
                     try {
                        $dt_fim = new DateTime($oportunidade_data['data_evento_fim']);
                        $oportunidade_data['data_evento_fim_formato'] = $dt_fim->format('Y-m-d\TH:i');
                    } catch (Exception $ex) {/* Ignorar data inválida */}
                }
                enviarRespostaJSON(true, 'Detalhes da oportunidade obtidos.', ['oportunidade' => $oportunidade_data]);
            } else {
                enviarRespostaJSON(false, 'Oportunidade não encontrada.');
            }
        } catch (PDOException $e) {
            error_log("Erro PDO ao buscar detalhes da oportunidade (ID: $id_oportunidade): " . $e->getMessage());
            enviarRespostaJSON(false, 'Erro ao carregar dados da oportunidade.');
        }
    }

    elseif ($action === 'add_edit_oportunidade') {
        $id_oportunidade = filter_input(INPUT_POST, 'id_oportunidade', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

        // Sanitizar entradas de string. Usar removerTagsHTML se HTML não for permitido, ou uma biblioteca
        // como HTMLPurifier se HTML seguro for necessário. Para este exemplo, sanitizarParaHTML é um bom padrão.
        $tipo_oportunidade_raw = $_POST['tipo_oportunidade'] ?? '';
        $titulo_oportunidade = sanitizarParaHTML($_POST['titulo_oportunidade'] ?? ''); // Era filter_input com SANITIZE_STRING
        $descricao_oportunidade = sanitizarParaHTML($_POST['descricao_oportunidade'] ?? ''); // Era trim puro
        $link_oportunidade = trim(filter_input(INPUT_POST, 'link_oportunidade', FILTER_SANITIZE_URL) ?: ''); // Mantido, é bom

        $data_evento_inicio_str = trim($_POST['data_evento_inicio'] ?? '');
        $data_evento_fim_str = trim($_POST['data_evento_fim'] ?? '');

        $data_evento_inicio = null;
        if (!empty($data_evento_inicio_str)) {
            try { $data_evento_inicio = (new DateTime($data_evento_inicio_str))->format('Y-m-d H:i:s'); }
            catch (Exception $e) { enviarRespostaJSON(false, 'Data de início do evento inválida.'); }
        }
        $data_evento_fim = null;
        if (!empty($data_evento_fim_str)) {
            try { $data_evento_fim = (new DateTime($data_evento_fim_str))->format('Y-m-d H:i:s'); }
            catch (Exception $e) { enviarRespostaJSON(false, 'Data de fim do evento inválida.'); }
        }

        $local_evento = sanitizarParaHTML($_POST['local_evento'] ?? ''); // Era filter_input com SANITIZE_STRING
        $fonte_oportunidade = sanitizarParaHTML($_POST['fonte_oportunidade'] ?? ''); // Era filter_input com SANITIZE_STRING
        $tags = sanitizarParaHTML($_POST['tags'] ?? ''); // Era filter_input com SANITIZE_STRING
        $ativo = (isset($_POST['ativo']) && $_POST['ativo'] === '1') ? 1 : 0; // Correto

        // Validações
        if (empty($tipo_oportunidade_raw) || empty($titulo_oportunidade) || empty($descricao_oportunidade)) {
            enviarRespostaJSON(false, 'Tipo, título e descrição são obrigatórios.');
        }
        $tipos_validos = ['curso', 'webinar', 'artigo', 'vaga', 'evento', 'outro'];
        if (!in_array($tipo_oportunidade_raw, $tipos_validos, true)) {
            enviarRespostaJSON(false, 'Tipo de oportunidade inválido.');
        }
        $tipo_oportunidade = $tipo_oportunidade_raw; // Tipo é válido

        if (mb_strlen($titulo_oportunidade) > 250) {
            enviarRespostaJSON(false, 'O título não pode exceder 250 caracteres.');
        }
        if (!empty($link_oportunidade) && !filter_var($link_oportunidade, FILTER_VALIDATE_URL)) {
            enviarRespostaJSON(false, 'O link fornecido não é uma URL válida.');
        }
        if ($data_evento_inicio && $data_evento_fim && strtotime($data_evento_fim) < strtotime($data_evento_inicio)) {
            enviarRespostaJSON(false, 'A data de fim do evento não pode ser anterior à data de início.');
        }

        try {
            $pdo->beginTransaction(); // Iniciar transação

            // Se fosse usar slugs:
            // $slug_oportunidade = gerarSlugUnicoOportunidade($titulo_oportunidade, $pdo, $id_oportunidade);

            if ($id_oportunidade) { // Edição
                $sql = "UPDATE oportunidades SET
                            tipo_oportunidade = :tipo, titulo_oportunidade = :titulo, descricao_oportunidade = :descricao,
                            link_oportunidade = :link, data_evento_inicio = :data_inicio, data_evento_fim = :data_fim,
                            local_evento = :local, fonte_oportunidade = :fonte, tags = :tags, ativo = :ativo
                            -- , slug_oportunidade = :slug -- Se usar slug
                        WHERE id_oportunidade = :id";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':tipo' => $tipo_oportunidade, ':titulo' => $titulo_oportunidade, ':descricao' => $descricao_oportunidade,
                    ':link' => !empty($link_oportunidade) ? $link_oportunidade : null,
                    ':data_inicio' => $data_evento_inicio, ':data_fim' => $data_evento_fim,
                    ':local' => !empty($local_evento) ? $local_evento : null,
                    ':fonte' => !empty($fonte_oportunidade) ? $fonte_oportunidade : null,
                    ':tags' => !empty($tags) ? $tags : null,
                    ':ativo' => $ativo, ':id' => $id_oportunidade
                    // , ':slug' => $slug_oportunidade -- Se usar slug
                ];
                $stmt->execute($params);
                $message = 'Oportunidade atualizada com sucesso!';
            } else { // Adição
                $sql = "INSERT INTO oportunidades
                            (tipo_oportunidade, titulo_oportunidade, descricao_oportunidade, link_oportunidade,
                             data_evento_inicio, data_evento_fim, local_evento, fonte_oportunidade, tags, ativo, data_publicacao, id_usuario_criador
                             -- , slug_oportunidade -- Se usar slug
                            )
                        VALUES
                            (:tipo, :titulo, :descricao, :link, :data_inicio, :data_fim, :local, :fonte, :tags, :ativo, NOW(), :id_usuario_criador
                             -- , :slug -- Se usar slug
                            )";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':tipo' => $tipo_oportunidade, ':titulo' => $titulo_oportunidade, ':descricao' => $descricao_oportunidade,
                    ':link' => !empty($link_oportunidade) ? $link_oportunidade : null,
                    ':data_inicio' => $data_evento_inicio, ':data_fim' => $data_evento_fim,
                    ':local' => !empty($local_evento) ? $local_evento : null,
                    ':fonte' => !empty($fonte_oportunidade) ? $fonte_oportunidade : null,
                    ':tags' => !empty($tags) ? $tags : null,
                    ':ativo' => $ativo,
                    ':id_usuario_criador' => $_SESSION['user_id'] ?? null // Grava quem criou
                    // , ':slug' => $slug_oportunidade -- Se usar slug
                ];
                $stmt->execute($params);
                $id_oportunidade = (int)$pdo->lastInsertId();
                $message = 'Oportunidade adicionada com sucesso!';
            }
            $pdo->commit(); // Confirmar transação
            enviarRespostaJSON(true, $message, ['id' => $id_oportunidade]);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro PDO ao salvar oportunidade (ID: $id_oportunidade): " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A'));
            if ($e->getCode() == 23000) { // Constraint violation (ex: UNIQUE)
                 enviarRespostaJSON(false, 'Erro ao salvar: Já existe uma oportunidade com dados semelhantes (ex: título ou slug).');
            } else {
                 enviarRespostaJSON(false, 'Erro ao salvar oportunidade. Verifique os dados e tente novamente.');
            }
        } catch (Exception $e) { // Captura exceções de DateTime
             if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro geral ao salvar oportunidade (ID: $id_oportunidade): " . $e->getMessage());
            enviarRespostaJSON(false, $e->getMessage()); // Envia a mensagem específica da exceção (ex: data inválida)
        }
    }

    elseif ($action === 'delete_oportunidade') {
        $id_oportunidade = filter_input(INPUT_POST, 'id_oportunidade', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        if (!$id_oportunidade) {
            enviarRespostaJSON(false, 'ID da oportunidade inválido.');
        }

        // Adicional: verificar se o usuário tem permissão para apagar esta oportunidade.

        try {
            $pdo->beginTransaction();
            // Se houvesse arquivos associados (ex: imagem da oportunidade), apagar aqui
            // Ex: $stmt_select_imagem = $pdo->prepare("SELECT caminho_imagem FROM oportunidades WHERE id_oportunidade = :id"); ... unlink($caminho_imagem_servidor);

            $stmt_delete = $pdo->prepare("DELETE FROM oportunidades WHERE id_oportunidade = :id_oportunidade");
            $stmt_delete->bindParam(':id_oportunidade', $id_oportunidade, PDO::PARAM_INT);
            $stmt_delete->execute();

            if ($stmt_delete->rowCount() > 0) {
                $pdo->commit();
                enviarRespostaJSON(true, 'Oportunidade apagada com sucesso!');
            } else {
                $pdo->rollBack();
                enviarRespostaJSON(false, 'Oportunidade não encontrada ou já havia sido apagada.');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro PDO de BD ao apagar oportunidade (ID: $id_oportunidade): " . $e->getMessage());
            if ($e->getCode() == '23000') { // Foreign key constraint
                enviarRespostaJSON(false, 'Erro: Esta oportunidade não pode ser excluída pois existem dados relacionados a ela em outras partes do sistema.');
            } else {
                enviarRespostaJSON(false, 'Erro ao processar a exclusão da oportunidade.');
            }
        }
    }
    else {
        enviarRespostaJSON(false, 'Ação desconhecida ou não permitida.');
    }
}

// --- Preparação de dados para a página HTML ---
$pageTitle = "Gerir Oportunidades";
$userName_for_header = sanitizarParaHTML($_SESSION['user_nome_completo'] ?? 'Admin');
$avatarUrl_for_header = sanitizarParaHTML($_SESSION['user_avatar_url'] ?? '');

if (empty($avatarUrl_for_header)) {
    $initials_for_header = ''; $nameParts_for_header = explode(' ', trim($userName_for_header));
    $initials_for_header .= !empty($nameParts_for_header[0]) ? strtoupper(mb_substr($nameParts_for_header[0], 0, 1, 'UTF-8')) : 'A';
    if (count($nameParts_for_header) > 1) {
        $initials_for_header .= strtoupper(mb_substr(end($nameParts_for_header), 0, 1, 'UTF-8'));
    } elseif (mb_strlen($nameParts_for_header[0], 'UTF-8') > 1 && $initials_for_header === strtoupper(mb_substr($nameParts_for_header[0], 0, 1, 'UTF-8'))) {
        $initials_for_header .= strtoupper(mb_substr($nameParts_for_header[0], 1, 1, 'UTF-8'));
    }
    if (empty($initials_for_header) || mb_strlen($initials_for_header, 'UTF-8') > 2) {
        $initials_for_header = "AD";
    }
    $avatarUrl_for_header = "https://ui-avatars.com/api/?name=" . urlencode($initials_for_header) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}
$tipos_oportunidade_select = ['curso' => 'Curso', 'webinar' => 'Webinar', 'artigo' => 'Artigo', 'vaga' => 'Vaga', 'evento' => 'Evento', 'outro' => 'Outro'];


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitizarParaHTML($pageTitle); ?> - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Estilos CSS (idealmente em arquivo externo) */
        body { font-family: 'Nunito', sans-serif; background-color: #f0f2f5; }
        .main-wrapper { display: flex; min-height: 100vh; }
        #adminSidebar { width: 260px; background-color: #2c3e50; color: #ecf0f1; transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        #adminSidebar .nav-link { color: #bdc3c7; padding: 0.8rem 1.25rem; font-size: 0.9rem; border-left: 3px solid transparent; }
        #adminSidebar .nav-link:hover { background-color: #34495e; color: #ffffff; border-left-color: #3498db; }
        #adminSidebar .nav-link.active { background-color: #3498db; color: #ffffff; font-weight: 600; border-left-color: #2980b9; }
        #adminSidebar .nav-link .fas, #adminSidebar .nav-link .fa-solid { margin-right: 0.8rem; width: 20px; text-align: center; } /* Adicionado fa-solid */
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; background-color: #f0f2f5; overflow-x: hidden; }
        .admin-main-content { padding: 2rem; flex-grow: 1; overflow-y: auto; }
        .admin-header { background-color: #ffffff; border-bottom: 1px solid #e0e0e0; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
        .card { border: none; border-radius: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .form-label { font-weight: 600; color: #495057; font-size: 0.875rem; }
        .form-control, .form-select { border-radius: 0.375rem; font-size: 0.9rem; }
        .form-control-sm, .form-select-sm { font-size: 0.8rem; padding-top: 0.25rem; padding-bottom: 0.25rem; } /* Ajuste padding */
        .modal-header { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .modal-title { color: #2c3e50; }
        .modal-body { max-height: calc(100vh - 210px); overflow-y: auto; }
        .modal-footer { background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
        .table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; color: #495057;}
        .table td { vertical-align: middle; font-size: 0.85rem; color: #343a40; }
        .table-sm td, .table-sm th { padding: .5rem .6rem; }
        .btn-spinner { display: inline-block; width: 1rem; height: 1rem; vertical-align: text-bottom; border: .2em solid currentColor; border-right-color: transparent; border-radius: 50%; animation: .75s linear infinite spinner-border; }
        .badge.bg-status-ativo { background-color: #d1e7dd !important; color: #0f5132 !important; border: 1px solid #badbcc;}
        .badge.bg-status-inativo { background-color: #f8d7da !important; color: #842029 !important; border: 1px solid #f5c2c7;}

        @media (max-width: 991.98px) {
            #adminSidebar { position: fixed; top: 0; bottom: 0; left: -260px; z-index: 1045; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
            #adminSidebar.active { left: 0; }
            .content-wrapper.sidebar-active-overlay::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.4); z-index: 1040; }
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <?php
        // Incluir Sidebar (supõe-se que admin_sidebar_component.php define $activePage para destacar o link correto)
        $activePage = 'oportunidades'; // Definir qual página está ativa para a sidebar
        if (file_exists(__DIR__ . '/sidebar.php')) {
            require __DIR__ . '/sidebar.php';
        } else {
            echo '';
        }
    ?>

    <div class="content-wrapper" id="contentWrapper">
        <?php
            // Incluir Header
            if (file_exists(__DIR__ . '/header.php')) {
                require __DIR__ . '/header.php';
            } else {
                 echo '';
            }
        ?>

        <main class="admin-main-content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h1 class="h2 mb-0 text-dark fw-bold"><?php echo sanitizarParaHTML($pageTitle); ?></h1>
                    <button type="button" class="btn btn-primary btn-sm" id="btnNovaOportunidade">
                        <i class="fas fa-plus me-2"></i>Nova Oportunidade
                    </button>
                </div>

                <div id="feedbackMessageGlobal" class="mb-3"></div>

                <div class="card mb-4">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-5 col-md-6">
                                <label for="filtro_titulo_op" class="form-label small mb-1">Buscar por Título</label>
                                <input type="text" id="filtro_titulo_op" class="form-control form-control-sm" placeholder="Digite o título...">
                            </div>
                            <div class="col-lg-5 col-md-6">
                                <label for="filtro_tipo_op" class="form-label small mb-1">Filtrar por Tipo</label>
                                <select id="filtro_tipo_op" class="form-select form-select-sm">
                                    <option value="todos">Todos os Tipos</option>
                                    <?php foreach ($tipos_oportunidade_select as $value => $label): ?>
                                        <option value="<?php echo sanitizarParaHTML($value); ?>"><?php echo sanitizarParaHTML($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-12 d-grid">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-auto" id="btnLimparFiltrosOportunidade"><i class="fas fa-times me-1"></i>Limpar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-light py-3">
                        <h5 class="mb-0 fw-semibold text-dark">Lista de Oportunidades</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 30%;">Título</th>
                                        <th style="width: 15%;">Tipo</th>
                                        <th class="d-none d-sm-table-cell" style="width: 20%;">Fonte</th>
                                        <th class="d-none d-md-table-cell" style="width: 15%;">Publicado em</th>
                                        <th style="width: 10%;">Status</th>
                                        <th class="text-end pe-3" style="width: 10%;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="oportunidadeListTableBody">
                                    <tr id="loadingOportunidadesRow"><td colspan="6" class="p-5 text-center text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Carregando oportunidades...</td></tr>
                                </tbody>
                            </table>
                        </div>
                         <div id="noOportunidadesMessage" class="text-center p-5 text-muted d-none">
                            <i class="fas fa-calendar-times fa-3x mb-3 text-secondary"></i><br>
                            Nenhuma oportunidade encontrada com os filtros atuais.
                        </div>
                    </div>
                </div>
            </div>
        </main>
         <footer class="py-4 mt-auto bg-light border-top">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; Audio TO Admin <?php echo date("Y"); ?></div>
                    <div><a href="#">Política de Privacidade</a> &middot; <a href="#">Termos &amp; Condições</a></div>
                </div>
            </div>
        </footer>
    </div>
</div>

    <div class="modal fade" id="modalOportunidade" tabindex="-1" aria-labelledby="modalOportunidadeLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form id="formOportunidade" novalidate>
                    <input type="hidden" name="id_oportunidade" id="op_id_oportunidade">
                    <input type="hidden" name="action" id="op_action_type" value="add_edit_oportunidade">
                    <div class="modal-header">
                        <h5 class="modal-title" id="opModalTitle">Nova Oportunidade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="modalOpFeedbackArea" class="mb-3"></div>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2">
                                <label for="op_tipo_oportunidade" class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select id="op_tipo_oportunidade" name="tipo_oportunidade" class="form-select form-select-sm" required>
                                    <option value="">Selecione o tipo...</option>
                                    <?php foreach ($tipos_oportunidade_select as $value => $label): ?>
                                        <option value="<?php echo sanitizarParaHTML($value); ?>"><?php echo sanitizarParaHTML($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Por favor, selecione um tipo.</div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label for="op_titulo_oportunidade" class="form-label">Título <span class="text-danger">*</span></label>
                                <input type="text" id="op_titulo_oportunidade" name="titulo_oportunidade" class="form-control form-control-sm" required maxlength="250">
                                 <div class="invalid-feedback">Por favor, informe o título (máx. 250 caracteres).</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="op_descricao_oportunidade" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <textarea id="op_descricao_oportunidade" name="descricao_oportunidade" rows="4" class="form-control form-control-sm" required></textarea>
                            <div class="invalid-feedback">Por favor, informe a descrição.</div>
                        </div>
                        <div class="mb-2">
                            <label for="op_link_oportunidade" class="form-label">Link (URL)</label>
                            <input type="url" id="op_link_oportunidade" name="link_oportunidade" class="form-control form-control-sm" placeholder="https://...">
                            <div class="invalid-feedback">Por favor, insira uma URL válida.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2">
                                <label for="op_data_evento_inicio" class="form-label">Data Início Evento (Opcional)</label>
                                <input type="datetime-local" id="op_data_evento_inicio" name="data_evento_inicio" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label for="op_data_evento_fim" class="form-label">Data Fim Evento (Opcional)</label>
                                <input type="datetime-local" id="op_data_evento_fim" name="data_evento_fim" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="op_local_evento" class="form-label">Local do Evento (Opcional)</label>
                            <input type="text" id="op_local_evento" name="local_evento" class="form-control form-control-sm" placeholder="Ex: Online, São Paulo - SP" maxlength="150">
                        </div>
                        <div class="mb-2">
                            <label for="op_fonte_oportunidade" class="form-label">Fonte (Opcional)</label>
                            <input type="text" id="op_fonte_oportunidade" name="fonte_oportunidade" class="form-control form-control-sm" placeholder="Ex: Nome da Empresa, Revista Científica" maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="op_tags" class="form-label">Tags (separadas por vírgula, opcional)</label>
                            <input type="text" id="op_tags" name="tags" class="form-control form-control-sm" placeholder="Ex: pediatria, online, gratuito" maxlength="255">
                        </div>
                         <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="op_ativo" name="ativo" value="1" checked>
                            <label class="form-check-label small" for="op_ativo">Ativo (visível para utilizadores)</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-primary" id="btn-salvar-oportunidade">
                             <span id="btn-salvar-op-text">Salvar Oportunidade</span>
                             <span id="btn-salvar-op-spinner" class="btn-spinner d-none ms-1" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmOpModal" tabindex="-1" aria-labelledby="deleteConfirmOpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmOpModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteConfirmOpMessage">Tem certeza que deseja apagar esta oportunidade?</p>
                    <p class="fw-bold" id="deleteConfirmOpItemName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-danger" id="btnConfirmDeleteOp">
                        <span class="btn-text">Apagar</span>
                        <span class="btn-spinner d-none ms-1" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
    // Helper function to safely escape HTML for attribute values or direct insertion
    function escapeHTML(str) {
        if (str === null || typeof str === 'undefined') return '';
        return String(str).replace(/[&<>"']/g, function (match) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const filtroTipoOpSelect = document.getElementById('filtro_tipo_op');
        const filtroTituloOpInput = document.getElementById('filtro_titulo_op');
        const oportunidadeListTableBody = document.getElementById('oportunidadeListTableBody');
        const loadingOportunidadesRow = document.getElementById('loadingOportunidadesRow');
        const noOportunidadesMessageDiv = document.getElementById('noOportunidadesMessage');
        const feedbackGlobalElOp = document.getElementById('feedbackMessageGlobal');
        // Corrigido: O ID no HTML é btnLimparFiltrosOportunidade
        const btnLimparFiltros = document.getElementById('btnLimparFiltrosOportunidade');


        const modalOportunidadeEl = document.getElementById('modalOportunidade');
        const modalOportunidade = new bootstrap.Modal(modalOportunidadeEl);
        const formOportunidade = document.getElementById('formOportunidade');
        const btnNovaOportunidade = document.getElementById('btnNovaOportunidade');
        const opModalTitle = document.getElementById('opModalTitle');
        // Corrigido: O ID no HTML é modalOpFeedbackArea
        const modalOpFeedbackEl = document.getElementById('modalOpFeedbackArea');
        const btnSalvarOportunidade = document.getElementById('btn-salvar-oportunidade');
        const btnSalvarOpText = document.getElementById('btn-salvar-op-text');
        const btnSalvarOpSpinner = document.getElementById('btn-salvar-op-spinner');

        const deleteConfirmOpModalEl = document.getElementById('deleteConfirmOpModal');
        const deleteConfirmOpModal = new bootstrap.Modal(deleteConfirmOpModalEl);
        const deleteConfirmOpItemName = document.getElementById('deleteConfirmOpItemName');
        const btnConfirmDeleteOp = document.getElementById('btnConfirmDeleteOp');
        let currentDeleteOpId = null;
        // const csrfToken = document.getElementById('op_csrf_token')?.value; // Exemplo de como pegar o token

        function mostrarFeedback(el, mensagem, tipo = 'danger') {
            if (!el) return;
            el.innerHTML = `<div class="alert alert-${tipo} alert-dismissible fade show small py-2 px-3" role="alert">${escapeHTML(mensagem)}<button type="button" class="btn-close btn-sm py-2" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            el.classList.remove('d-none'); // Garante que está visível se foi escondido antes

            if (el === feedbackGlobalElOp) { // Auto-hide para feedback global
                setTimeout(() => {
                    const alertInstance = bootstrap.Alert.getInstance(el.querySelector('.alert'));
                    if (alertInstance) alertInstance.close();
                }, 7000);
            }
        }
        function limparFeedback(el) { if(el) { el.innerHTML = ''; el.classList.add('d-none'); }}

        function setButtonLoadingState(button, isLoading, defaultText = "Salvar") {
            const textSpan = button.querySelector('.btn-text') || button.querySelector('span:not(.btn-spinner)');
            const spinnerSpan = button.querySelector('.btn-spinner');
            if (isLoading) {
                if(textSpan) textSpan.classList.add('d-none');
                if(spinnerSpan) spinnerSpan.classList.remove('d-none');
                button.disabled = true;
            } else {
                if(textSpan) {
                    textSpan.classList.remove('d-none');
                    if(defaultText && textSpan.id === 'btn-salvar-op-text') textSpan.textContent = defaultText;
                    else if (defaultText && button.id === 'btnConfirmDeleteOp') textSpan.textContent = "Apagar";
                 }
                if(spinnerSpan) spinnerSpan.classList.add('d-none');
                button.disabled = false;
            }
        }


        function fetchOportunidades() {
            loadingOportunidadesRow.style.display = 'table-row';
            noOportunidadesMessageDiv.classList.add('d-none');
            oportunidadeListTableBody.innerHTML = '';
            oportunidadeListTableBody.appendChild(loadingOportunidadesRow);

            const formData = new FormData();
            formData.append('action', 'list_oportunidades');
            formData.append('filtro_tipo', filtroTipoOpSelect.value);
            formData.append('busca_titulo', filtroTituloOpInput.value);
            // if (csrfToken) formData.append('csrf_token', csrfToken);

            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json(); // Assumindo que o PHP sempre retorna JSON válido
            })
            .then(data => {
                loadingOportunidadesRow.style.display = 'none';
                oportunidadeListTableBody.innerHTML = ''; // Limpar linha de carregamento

                if (data.ok && data.oportunidades) {
                    if (data.oportunidades.length === 0) {
                        noOportunidadesMessageDiv.classList.remove('d-none');
                    } else {
                        noOportunidadesMessageDiv.classList.add('d-none');
                        data.oportunidades.forEach(op => {
                            // Os dados como op.titulo_oportunidade já vêm sanitizados do PHP (sanitizarParaHTML)
                            const dataPub = new Date(op.data_publicacao.replace(' ', 'T') + 'Z'); // Adicionar Z para UTC se a data do BD for UTC
                            const dataFormatada = dataPub.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
                            const tipoFormatado = op.tipo_oportunidade.charAt(0).toUpperCase() + op.tipo_oportunidade.slice(1);
                            const statusClasse = op.ativo == 1 ? 'bg-status-ativo' : 'bg-status-inativo';
                            const statusTexto = op.ativo == 1 ? 'Ativo' : 'Inativo';

                            const rowHTML = `
                                <tr data-id="${op.id_oportunidade}">
                                    <td class="ps-3">
                                        <strong class="text-dark d-block text-truncate" style="max-width: 280px;" title="${op.titulo_oportunidade}">${op.titulo_oportunidade}</strong>
                                        <small class="text-muted d-block text-truncate" style="max-width: 280px;" title="${op.descricao_curta}">${op.descricao_curta ? op.descricao_curta + '...' : ''}</small>
                                    </td>
                                    <td><span class="badge bg-info-subtle text-info-emphasis rounded-pill py-1 px-2">${escapeHTML(tipoFormatado)}</span></td>
                                    <td class="d-none d-sm-table-cell">${op.fonte_oportunidade || '-'}</td>
                                    <td class="d-none d-md-table-cell text-muted small">${dataFormatada}</td>
                                    <td><span class="badge rounded-pill ${statusClasse} py-1 px-2">${statusTexto}</span></td>
                                    <td class="text-end pe-3">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-oportunidade me-1 py-1 px-2" title="Editar" data-id="${op.id_oportunidade}"><i class="fas fa-pencil-alt"></i></button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-oportunidade py-1 px-2" title="Apagar" data-id="${op.id_oportunidade}" data-titulo="${op.titulo_oportunidade}"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>`;
                            oportunidadeListTableBody.insertAdjacentHTML('beforeend', rowHTML);
                        });
                        addOportunidadeActionListeners();
                    }
                } else {
                    mostrarFeedback(feedbackGlobalElOp, data.msg || 'Erro ao carregar oportunidades.', 'danger');
                    noOportunidadesMessageDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                loadingOportunidadesRow.style.display = 'none';
                oportunidadeListTableBody.innerHTML = ''; // Limpa
                noOportunidadesMessageDiv.classList.remove('d-none');
                console.error('Fetch error (list_oportunidades):', error);
                mostrarFeedback(feedbackGlobalElOp, 'Erro de comunicação ao carregar oportunidades. Verifique sua conexão.', 'danger');
            });
        }

        function addOportunidadeActionListeners() {
            document.querySelectorAll('.btn-edit-oportunidade').forEach(button => {
                button.removeEventListener('click', handleEditClick); // Previne múltiplos listeners
                button.addEventListener('click', handleEditClick);
            });
            document.querySelectorAll('.btn-delete-oportunidade').forEach(button => {
                button.removeEventListener('click', handleDeleteClick); // Previne múltiplos listeners
                button.addEventListener('click', handleDeleteClick);
            });
        }

        function handleEditClick() { abrirModalParaEditarOportunidade(this.dataset.id); }
        function handleDeleteClick() {
            const opId = this.dataset.id;
            const opTitulo = this.dataset.titulo; // Já escapado pelo PHP e/ou escapeHTML ao criar o botão
            abrirModalConfirmacaoExclusaoOp(opId, opTitulo);
        }

        function abrirModalOportunidade(paraEdicao = false, idOportunidade = null) {
            formOportunidade.reset();
            formOportunidade.classList.remove('was-validated');
            document.getElementById('op_id_oportunidade').value = paraEdicao ? idOportunidade : '';
            opModalTitle.textContent = paraEdicao ? 'Editar Oportunidade' : 'Nova Oportunidade';
            document.getElementById('op_ativo').checked = true;
            limparFeedback(modalOpFeedbackEl);

            if (paraEdicao && idOportunidade) {
                opModalTitle.textContent = 'Carregando dados...';
                const formData = new FormData();
                formData.append('action', 'get_oportunidade_details');
                formData.append('id_oportunidade', idOportunidade);
                // if (csrfToken) formData.append('csrf_token', csrfToken);

                fetch(window.location.pathname, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok && data.oportunidade) {
                        const op = data.oportunidade;
                        // Valores já são strings, ou formatados (data_evento_*_formato)
                        document.getElementById('op_tipo_oportunidade').value = op.tipo_oportunidade;
                        document.getElementById('op_titulo_oportunidade').value = op.titulo_oportunidade;
                        document.getElementById('op_descricao_oportunidade').value = op.descricao_oportunidade;
                        document.getElementById('op_link_oportunidade').value = op.link_oportunidade || '';
                        document.getElementById('op_data_evento_inicio').value = op.data_evento_inicio_formato || '';
                        document.getElementById('op_data_evento_fim').value = op.data_evento_fim_formato || '';
                        document.getElementById('op_local_evento').value = op.local_evento || '';
                        document.getElementById('op_fonte_oportunidade').value = op.fonte_oportunidade || '';
                        document.getElementById('op_tags').value = op.tags || '';
                        document.getElementById('op_ativo').checked = parseInt(op.ativo) === 1;
                        opModalTitle.textContent = 'Editar Oportunidade';
                        modalOportunidade.show();
                    } else {
                        mostrarFeedback(feedbackGlobalElOp, data.msg || 'Erro ao carregar dados para edição.', 'danger');
                    }
                })
                .catch(error => {
                    console.error("Fetch error get_oportunidade_details:", error);
                    mostrarFeedback(feedbackGlobalElOp, 'Erro de comunicação ao carregar dados para edição.', 'danger');
                    opModalTitle.textContent = 'Editar Oportunidade'; // Reset title
                });
            } else {
                modalOportunidade.show();
            }
        }

        function abrirModalParaEditarOportunidade(idOportunidade) {
            abrirModalOportunidade(true, idOportunidade);
        }

        function abrirModalConfirmacaoExclusaoOp(id, titulo) {
            currentDeleteOpId = id;
            deleteConfirmOpItemName.textContent = `"${titulo}"`; // Título já está escapado
            deleteConfirmOpModal.show();
        }

        btnConfirmDeleteOp.addEventListener('click', function() {
            if (currentDeleteOpId) {
                setButtonLoadingState(this, true);
                const formData = new FormData();
                formData.append('action', 'delete_oportunidade');
                formData.append('id_oportunidade', currentDeleteOpId);
                // if (csrfToken) formData.append('csrf_token', csrfToken);

                fetch(window.location.pathname, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    mostrarFeedback(feedbackGlobalElOp, data.msg, data.ok ? 'success' : 'danger');
                    if (data.ok) fetchOportunidades();
                })
                .catch(error => {
                    console.error("Fetch error delete_oportunidade:", error);
                    mostrarFeedback(feedbackGlobalElOp, "Erro de comunicação ao apagar oportunidade.", "danger");
                })
                .finally(() => {
                    setButtonLoadingState(this, false, "Apagar");
                    deleteConfirmOpModal.hide();
                    currentDeleteOpId = null;
                });
            }
        });

        formOportunidade.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            this.classList.remove('was-validated');

            setButtonLoadingState(btnSalvarOportunidade, true, "Salvar Oportunidade");
            limparFeedback(modalOpFeedbackEl);

            const formData = new FormData(this);
            if (!formData.has('ativo')) {
                formData.append('ativo', '0');
            } else {
                formData.set('ativo', '1');
            }
            // if (csrfToken && !formData.has('csrf_token')) formData.append('csrf_token', csrfToken);

            fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.ok) {
                    modalOportunidade.hide();
                    mostrarFeedback(feedbackGlobalElOp, data.msg, 'success');
                    fetchOportunidades();
                } else {
                    mostrarFeedback(modalOpFeedbackEl, data.msg || "Erro desconhecido ao salvar.", 'danger');
                }
            })
            .catch(error => {
                console.error('Fetch error on form submit:', error);
                mostrarFeedback(modalOpFeedbackEl, 'Erro de comunicação ao salvar. Tente novamente.', 'danger');
            })
            .finally(() => {
                setButtonLoadingState(btnSalvarOportunidade, false, "Salvar Oportunidade");
            });
        });

        if(btnNovaOportunidade) btnNovaOportunidade.addEventListener('click', () => abrirModalOportunidade(false));
        if(filtroTipoOpSelect) filtroTipoOpSelect.addEventListener('change', fetchOportunidades);
        if(filtroTituloOpInput) filtroTituloOpInput.addEventListener('input', debounce(fetchOportunidades, 400));
        if(btnLimparFiltros) {
            btnLimparFiltros.addEventListener('click', () => {
                if(filtroTituloOpInput) filtroTituloOpInput.value = '';
                if(filtroTipoOpSelect) filtroTipoOpSelect.value = 'todos';
                fetchOportunidades();
            });
        }

        function debounce(func, delay) {
            let timeout;
            return function(...args) { clearTimeout(timeout); timeout = setTimeout(() => func.apply(this, args), delay); };
        }

        fetchOportunidades();

        // Sidebar Toggle Logic (se os componentes não cuidarem disso)
        const mobileSidebarToggleButton = document.getElementById('adminMobileSidebarToggle') || document.getElementById('adminMobileSidebarToggleFallback');
        const adminSidebar = document.getElementById('adminSidebar');
        const contentWrapper = document.getElementById('contentWrapper');

        if (mobileSidebarToggleButton && adminSidebar && contentWrapper) {
            mobileSidebarToggleButton.addEventListener('click', function() {
                adminSidebar.classList.toggle('active');
                contentWrapper.classList.toggle('sidebar-active-overlay');
            });
            // Fechar sidebar ao clicar no overlay (se existir e for desejado)
            contentWrapper.addEventListener('click', function(e) {
                if (contentWrapper.classList.contains('sidebar-active-overlay') && e.target === contentWrapper) {
                    adminSidebar.classList.remove('active');
                    contentWrapper.classList.remove('sidebar-active-overlay');
                }
            });
        }
    });
    </script>
</body>
</html>