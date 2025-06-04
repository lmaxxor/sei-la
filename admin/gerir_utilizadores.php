<?php
// admin/gerir_utilizadores.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../login.php');
require_once __DIR__ . '/../db/db_connect.php';

// --- PROCESSAMENTO DE AÇÕES AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=UTF-8');

    function resposta_json($ok, $msg, $extra = []) {
        echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra)); exit;
    }

    $action = $_POST['action'];

    // --- LISTAR UTILIZADORES ---
    if ($action === 'list_utilizadores') {
        $filtro_funcao = $_POST['filtro_funcao'] ?? 'todos';
        $filtro_status_conta = $_POST['filtro_status_conta'] ?? 'todos';
        $busca_nome_email = $_POST['busca_nome_email'] ?? null;

        $sql = "SELECT 
                    u.id_utilizador, u.nome_completo, u.email, u.funcao, u.data_registo, u.status_sistema AS status_conta_sistema,
                    p.nome_plano as nome_plano_ativo,
                    (SELECT estado_assinatura FROM assinaturas_utilizador WHERE id_utilizador = u.id_utilizador ORDER BY data_inicio DESC LIMIT 1) as status_assinatura
                FROM utilizadores u
                LEFT JOIN planos_assinatura p ON u.id_plano_assinatura_ativo = p.id_plano
                WHERE 1=1";

        $params = [];
        if (!empty($filtro_funcao) && $filtro_funcao !== 'todos') {
            $sql .= " AND u.funcao = :funcao";
            $params[':funcao'] = $filtro_funcao;
        }
        if ($filtro_status_conta === 'ativo') {
            $sql .= " AND u.status_sistema = 'ativo'";
        } elseif ($filtro_status_conta === 'inativo') {
            $sql .= " AND u.status_sistema = 'inativo'";
        }
        if (!empty($busca_nome_email)) {
            $sql .= " AND (u.nome_completo LIKE :busca OR u.email LIKE :busca)";
            $params[':busca'] = '%' . $busca_nome_email . '%';
        }
        $sql .= " ORDER BY u.data_registo DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $utilizadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            resposta_json(true, 'Utilizadores listados.', ['utilizadores' => $utilizadores]);
        } catch (PDOException $e) {
            error_log("Erro ao listar utilizadores: " . $e->getMessage());
            resposta_json(false, 'Erro ao buscar utilizadores: ' . $e->getMessage());
        }
    }

    // --- OBTER DETALHES DO UTILIZADOR PARA EDIÇÃO ---
    if ($action === 'get_utilizador_details') {
        $id_utilizador = filter_input(INPUT_POST, 'id_utilizador', FILTER_VALIDATE_INT);
        if (!$id_utilizador) {
            resposta_json(false, 'ID do utilizador inválido.');
        }
        try {
            $stmt = $pdo->prepare("SELECT id_utilizador, nome_completo, email, funcao, profissao, crefito, data_registo, status_sistema, id_plano_assinatura_ativo 
                                     FROM utilizadores WHERE id_utilizador = :id_utilizador");
            $stmt->bindParam(':id_utilizador', $id_utilizador, PDO::PARAM_INT);
            $stmt->execute();
            $utilizador_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($utilizador_data) {
                $utilizador_data['status_conta_sistema'] = $utilizador_data['status_sistema'];
                resposta_json(true, 'Detalhes do utilizador obtidos.', ['utilizador' => $utilizador_data]);
            } else {
                resposta_json(false, 'Utilizador não encontrado.');
            }
        } catch (PDOException $e) {
            error_log("Erro ao buscar detalhes do utilizador: " . $e->getMessage());
            resposta_json(false, 'Erro ao carregar dados do utilizador.');
        }
    }

    // --- ATUALIZAR DADOS DO UTILIZADOR ---
    if ($action === 'update_utilizador') {
        $id_utilizador_update = filter_input(INPUT_POST, 'id_utilizador', FILTER_VALIDATE_INT);
        $nome_completo_update = trim($_POST['nome_completo'] ?? '');
        $email_update = trim(strtolower($_POST['email'] ?? ''));
        $profissao_update = trim($_POST['profissao'] ?? null);
        $crefito_update = trim($_POST['crefito'] ?? null);
        $funcao_update = $_POST['funcao'] ?? 'utilizador';
        $status_conta_update = $_POST['status_conta_sistema'] ?? 'ativo';
        $id_plano_update = filter_input(INPUT_POST, 'id_plano_assinatura_ativo', FILTER_VALIDATE_INT);
        if ($id_plano_update === false || $id_plano_update === 0) { // Treat 0 or empty string from select as NULL
            $id_plano_update = null;
        }


        if (!$id_utilizador_update || empty($nome_completo_update) || !filter_var($email_update, FILTER_VALIDATE_EMAIL) || !in_array($funcao_update, ['utilizador', 'administrador']) || !in_array($status_conta_update, ['ativo', 'inativo'])) {
            resposta_json(false, 'Dados inválidos para atualização. Verifique nome, email, função e status.');
        }

        // Prevent admin from demoting/deactivating self if they are the only active admin
        if ($id_utilizador_update == $_SESSION['user_id']) {
            if ($funcao_update !== 'administrador') {
                 resposta_json(false, 'Não pode remover a sua própria função de administrador.');
            }
            if ($status_conta_update === 'inativo') {
                $stmt_check_admin = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE funcao = 'administrador' AND status_sistema = 'ativo' AND id_utilizador != :current_user_id");
                $stmt_check_admin->execute([':current_user_id' => $id_utilizador_update]);
                if ($stmt_check_admin->fetchColumn() == 0) {
                    resposta_json(false, 'Não pode desativar a sua própria conta pois é o único administrador ativo.');
                }
            }
        }
        
        try {
            // Check if email is being changed and if the new one is unique
            $stmt_current_email = $pdo->prepare("SELECT email FROM utilizadores WHERE id_utilizador = :id");
            $stmt_current_email->execute([':id' => $id_utilizador_update]);
            $current_email = $stmt_current_email->fetchColumn();

            if (strtolower($email_update) !== strtolower($current_email)) {
                $stmt_email_exists = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE email = :email AND id_utilizador != :id");
                $stmt_email_exists->execute([':email' => $email_update, ':id' => $id_utilizador_update]);
                if ($stmt_email_exists->fetchColumn() > 0) {
                    resposta_json(false, 'O novo email fornecido já está em uso por outro utilizador.');
                }
            }

            $sql_update = "UPDATE utilizadores SET 
                            nome_completo = :nome_completo, 
                            email = :email, 
                            profissao = :profissao, 
                            crefito = :crefito, 
                            funcao = :funcao, 
                            status_sistema = :status_conta, 
                            id_plano_assinatura_ativo = :id_plano 
                           WHERE id_utilizador = :id";
            $params_update = [
                ':nome_completo' => $nome_completo_update,
                ':email' => $email_update,
                ':profissao' => $profissao_update ?: null,
                ':crefito' => $crefito_update ?: null,
                ':funcao' => $funcao_update,
                ':status_conta' => $status_conta_update,
                ':id_plano' => $id_plano_update,
                ':id' => $id_utilizador_update
            ];

            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute($params_update);

            if ($stmt_update->rowCount() > 0) {
                resposta_json(true, 'Utilizador atualizado com sucesso!');
            } else {
                 $stmt_exists = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE id_utilizador = :id");
                 $stmt_exists->execute([':id' => $id_utilizador_update]);
                 if ($stmt_exists->fetchColumn() > 0) {
                    resposta_json(true, 'Nenhuma alteração detectada.');
                 } else {
                    resposta_json(false, 'Utilizador não encontrado.');
                 }
            }
        } catch (PDOException $e) {
            error_log("Erro ao atualizar utilizador: " . $e->getMessage());
            resposta_json(false, 'Erro ao atualizar utilizador: ' . $e->getMessage());
        }
    }

    // --- EXCLUIR UTILIZADOR ---
    if ($action === 'delete_utilizador') {
        $id_utilizador_delete = filter_input(INPUT_POST, 'id_utilizador', FILTER_VALIDATE_INT);

        if (!$id_utilizador_delete) {
            resposta_json(false, 'ID do utilizador inválido para exclusão.');
        }

        if ($id_utilizador_delete == $_SESSION['user_id']) {
            resposta_json(false, 'Não pode excluir a sua própria conta de administrador.');
        }

        // Adicional: Verificar se é o único administrador antes de excluir?
        // Esta verificação já existe para desativação, para exclusão pode ser ainda mais crítico.
        // $user_to_delete_stmt = $pdo->prepare("SELECT funcao, status_sistema FROM utilizadores WHERE id_utilizador = :id");
        // $user_to_delete_stmt->execute([':id' => $id_utilizador_delete]);
        // $user_to_delete = $user_to_delete_stmt->fetch(PDO::FETCH_ASSOC);

        // if ($user_to_delete && $user_to_delete['funcao'] === 'administrador' && $user_to_delete['status_sistema'] === 'ativo') {
        //     $stmt_check_admin = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE funcao = 'administrador' AND status_sistema = 'ativo' AND id_utilizador != :id_to_delete");
        //     $stmt_check_admin->execute([':id_to_delete' => $id_utilizador_delete]);
        //     if ($stmt_check_admin->fetchColumn() == 0) {
        //         resposta_json(false, 'Não pode excluir este utilizador pois é o único administrador ativo.');
        //     }
        // }


        try {
            $stmt_delete = $pdo->prepare("DELETE FROM utilizadores WHERE id_utilizador = :id");
            $stmt_delete->bindParam(':id', $id_utilizador_delete, PDO::PARAM_INT);
            $stmt_delete->execute();

            if ($stmt_delete->rowCount() > 0) {
                resposta_json(true, 'Utilizador excluído com sucesso!');
            } else {
                resposta_json(false, 'Utilizador não encontrado ou já excluído.');
            }
        } catch (PDOException $e) {
            error_log("Erro ao excluir utilizador: " . $e->getMessage());
            // Se houver restrições de chave estrangeira não configuradas com ON DELETE CASCADE, pode dar erro.
            resposta_json(false, 'Erro ao excluir utilizador. Verifique se há dados associados que impedem a exclusão: ' . $e->getMessage());
        }
    }

    resposta_json(false, 'Ação desconhecida.');
}

// --- Lógica para exibição da página HTML ---
$pageTitle = "Gerir Utilizadores";
$userName = $_SESSION['user_nome_completo'] ?? 'Admin';
// ... (resto da lógica de avatar e variáveis PHP) ...
if (!$avatarUrl) {
    $initials = ''; $nameParts = explode(' ', $userName);
    $initials .= !empty($nameParts[0]) ? strtoupper(substr($nameParts[0], 0, 1)) : 'A';
    if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
    elseif (strlen($nameParts[0]) > 1 && $initials === strtoupper(substr($nameParts[0], 0, 1))) $initials .= strtoupper(substr($nameParts[0], 1, 1));
    if (empty($initials) || strlen($initials) > 2) $initials = "AD";
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($initials) . "&background=0D6EFD&color=fff&size=40&rounded=true&bold=true";
}

$funcoes_select = ['utilizador' => 'Utilizador', 'administrador' => 'Administrador'];
$status_conta_select = ['todos' => 'Todos Status', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];

// Fetch available plans for the dropdown
try {
    $stmt_planos = $pdo->query("SELECT id_plano, nome_plano FROM planos_assinatura WHERE ativo = 1 ORDER BY nome_plano");
    $planos_disponiveis = $stmt_planos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar planos: " . $e->getMessage());
    $planos_disponiveis = []; // Evita erro na página se a query falhar
}


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Audio TO Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Raleway:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; display: flex; flex-direction: column; min-height: 100vh; }
        .main-container-wrapper { display: flex; flex-grow: 1; }
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow-x: hidden; }
        main { flex-grow: 1; overflow-y: auto; }
        .form-label-custom { font-size: 0.875em; margin-bottom: 0.25rem; display: block; }
        .info-field-like-input { /* Style paragraph to look like a disabled input if needed */
             display: block; width: 100%; padding: .375rem .75rem; font-size: 1rem; font-weight: 400;
             line-height: 1.5; color: #6c757d; background-color: #e9ecef; border: 1px solid #ced4da;
             border-radius: .375rem; -webkit-appearance: none; -moz-appearance: none; appearance: none;
        }
        .modal-body-scrollable { max-height: calc(100vh - 250px); overflow-y: auto; padding-right: 0.5rem; }
    </style>
</head>
<body>

    <div class="main-container-wrapper">
        <?php require __DIR__ . '/sidebar.php'; ?>
        
        <div class="content-wrapper">
            <?php require __DIR__ . '/header.php'; ?>

            <main class="p-3 p-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-2">
                    <h1 class="h2 fw-bold text-dark">Gerir Utilizadores</h1>
                </div>

                <div id="feedbackMessage" class="d-none"></div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="busca_nome_email" class="form-label small">Buscar por Nome/Email</label>
                                <input type="text" id="busca_nome_email" class="form-control form-control-sm" placeholder="Digite nome ou email...">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_funcao" class="form-label small">Filtrar por Função</label>
                                <select id="filtro_funcao" class="form-select form-select-sm">
                                    <option value="todos">Todas Funções</option>
                                    <?php foreach ($funcoes_select as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_status_conta" class="form-label small">Filtrar por Status da Conta</label>
                                <select id="filtro_status_conta" class="form-select form-select-sm">
                                    <?php foreach ($status_conta_select as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3 py-2 text-uppercase small text-muted">Nome Completo</th>
                                    <th class="px-3 py-2 text-uppercase small text-muted">Email</th>
                                    <th class="px-3 py-2 text-uppercase small text-muted d-none d-sm-table-cell">Função</th>
                                    <th class="px-3 py-2 text-uppercase small text-muted d-none d-md-table-cell">Plano</th>
                                    <th class="px-3 py-2 text-uppercase small text-muted d-none d-lg-table-cell">Status Conta</th>
                                    <th class="px-3 py-2 text-uppercase small text-muted text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="utilizadorListTableBody">
                                <tr id="loadingUtilizadores"><td colspan="6" class="p-4 text-center text-muted">Carregando utilizadores...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="noUtilizadoresMessage" class="p-5 text-center text-muted d-none">
                         <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-people-fill mb-2 mx-auto text-secondary" viewBox="0 0 16 16">
                            <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/>
                        </svg>
                        <p>Nenhum utilizador encontrado com os filtros atuais.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="modalEditarUtilizador" tabindex="-1" aria-labelledby="editUserModalTitleLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form id="formEditarUtilizador">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalTitleLabel">Editar Utilizador</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-scrollable">
                        <input type="hidden" name="id_utilizador" id="edit_id_utilizador">
                        <input type="hidden" name="action" value="update_utilizador">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_nome_completo" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_nome_completo" name="nome_completo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_profissao" class="form-label">Profissão</label>
                                <input type="text" class="form-control" id="edit_profissao" name="profissao">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_crefito" class="form-label">CREFITO/Registo</label>
                                <input type="text" class="form-control" id="edit_crefito" name="crefito">
                            </div>
                        </div>
                        
                        <p class="form-label-custom mt-2">Data de Registo: <span id="info_data_registo" class="fw-normal text-muted"></span></p>
                        <hr class="my-3">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit_funcao" class="form-label">Função <span class="text-danger">*</span></label>
                                <select id="edit_funcao" name="funcao" class="form-select" required>
                                    <?php foreach ($funcoes_select as $value => $label): ?>
                                        <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_status_conta" class="form-label">Status da Conta <span class="text-danger">*</span></label>
                                <select id="edit_status_conta" name="status_conta_sistema" class="form-select" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_id_plano_assinatura_ativo" class="form-label">Plano de Assinatura</label>
                                <select id="edit_id_plano_assinatura_ativo" name="id_plano_assinatura_ativo" class="form-select">
                                    <option value="">Sem Plano Ativo</option>
                                    <?php foreach ($planos_disponiveis as $plano): ?>
                                        <option value="<?php echo htmlspecialchars($plano['id_plano']); ?>"><?php echo htmlspecialchars($plano['nome_plano']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                         <p class="form-text small mt-1">Alterar o status para 'Inativo' impedirá o login do utilizador.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroFuncaoSelect = document.getElementById('filtro_funcao');
    const filtroStatusContaSelect = document.getElementById('filtro_status_conta');
    const buscaNomeEmailInput = document.getElementById('busca_nome_email');
    const utilizadorListTableBody = document.getElementById('utilizadorListTableBody');
    const loadingUtilizadoresRow = document.getElementById('loadingUtilizadores');
    const noUtilizadoresMessageDiv = document.getElementById('noUtilizadoresMessage');
    const feedbackMessageDivUser = document.getElementById('feedbackMessage');

    const modalEditarUtilizadorEl = document.getElementById('modalEditarUtilizador');
    const bsEditModal = new bootstrap.Modal(modalEditarUtilizadorEl);
    
    const formEditarUtilizador = document.getElementById('formEditarUtilizador');
    const editUserModalTitle = document.getElementById('editUserModalTitleLabel');

    function exibirFeedbackUser(ok, msg) {
        feedbackMessageDivUser.innerHTML = `<div class="alert ${ok ? 'alert-success' : 'alert-danger'} alert-dismissible fade show" role="alert">
                                                ${msg}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                           </div>`;
        feedbackMessageDivUser.classList.remove('d-none');
    }

    function fetchUtilizadores() {
        loadingUtilizadoresRow.style.display = 'table-row';
        noUtilizadoresMessageDiv.classList.add('d-none');
        utilizadorListTableBody.innerHTML = ''; 
        utilizadorListTableBody.appendChild(loadingUtilizadoresRow);

        const formData = new FormData();
        formData.append('action', 'list_utilizadores');
        formData.append('filtro_funcao', filtroFuncaoSelect.value);
        formData.append('filtro_status_conta', filtroStatusContaSelect.value);
        formData.append('busca_nome_email', buscaNomeEmailInput.value);

        fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            loadingUtilizadoresRow.style.display = 'none';
            if (data.ok && data.utilizadores) {
                if (data.utilizadores.length === 0) {
                    noUtilizadoresMessageDiv.classList.remove('d-none');
                } else {
                    data.utilizadores.forEach(u => {
                        const dataReg = new Date(u.data_registo + " UTC");
                        const dataFormatada = dataReg.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit', year: 'numeric'});
                        const funcaoFormatada = u.funcao.charAt(0).toUpperCase() + u.funcao.slice(1);
                        
                        const statusContaTexto = u.status_conta_sistema === 'ativo' ? 'Ativo' : 'Inativo';
                        const statusContaClasse = u.status_conta_sistema === 'ativo' ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis';
                        const nomePlano = u.nome_plano_ativo || '<span class="text-muted small">N/A</span>';

                        const row = `
                            <tr>
                                <td class="px-3 py-2">
                                    <div>${u.nome_completo}</div>
                                    <small class="text-muted d-md-none">${u.email}</small>
                                </td>
                                <td class="px-3 py-2 text-muted d-none d-md-table-cell">${u.email}</td>
                                <td class="px-3 py-2 text-muted d-none d-sm-table-cell">${funcaoFormatada}</td>
                                <td class="px-3 py-2 text-muted d-none d-md-table-cell">${nomePlano}</td>
                                <td class="px-3 py-2 d-none d-lg-table-cell">
                                    <span class="badge rounded-pill ${statusContaClasse}">
                                        ${statusContaTexto}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-utilizador-btn" data-id="${u.id_utilizador}" title="Editar Detalhes">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-utilizador-btn ms-1" data-id="${u.id_utilizador}" data-nome="${u.nome_completo}" title="Excluir Utilizador">
                                        <i class="fas fa-trash-alt"></i> Excluir
                                    </button>
                                </td>
                            </tr>`;
                        utilizadorListTableBody.insertAdjacentHTML('beforeend', row);
                    });
                    addUtilizadorActionListeners();
                }
            } else {
                exibirFeedbackUser(false, data.msg || 'Erro ao carregar utilizadores.');
                noUtilizadoresMessageDiv.classList.remove('d-none');
            }
        })
        .catch(error => {
            loadingUtilizadoresRow.style.display = 'none';
            noUtilizadoresMessageDiv.classList.remove('d-none');
            console.error('Fetch error:', error);
            exibirFeedbackUser(false, 'Erro de comunicação ao carregar utilizadores.');
        });
    }

    function addUtilizadorActionListeners() {
        document.querySelectorAll('.edit-utilizador-btn').forEach(button => {
            button.addEventListener('click', function() {
                abrirModalParaEditarUtilizador(this.dataset.id);
            });
        });
        document.querySelectorAll('.delete-utilizador-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.id;
                const userName = this.dataset.nome;
                if (confirm(`Tem a certeza que deseja excluir o utilizador "${userName}" (ID: ${userId})?\nEsta ação não pode ser desfeita.`)) {
                    excluirUtilizador(userId);
                }
            });
        });
    }
    
    function abrirModalParaEditarUtilizador(idUtilizador) {
        const formData = new FormData();
        formData.append('action', 'get_utilizador_details');
        formData.append('id_utilizador', idUtilizador);

        fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.ok && data.utilizador) {
                const u = data.utilizador;
                formEditarUtilizador.reset(); // Reset form before populating
                document.getElementById('edit_id_utilizador').value = u.id_utilizador;
                editUserModalTitle.textContent = `Editando: ${u.nome_completo}`;
                
                document.getElementById('edit_nome_completo').value = u.nome_completo;
                document.getElementById('edit_email').value = u.email;
                document.getElementById('edit_profissao').value = u.profissao || '';
                document.getElementById('edit_crefito').value = u.crefito || '';
                
                const dataRegModal = new Date(u.data_registo + " UTC");
                document.getElementById('info_data_registo').textContent = dataRegModal.toLocaleString('pt-BR', {dateStyle:'long', timeStyle:'short'});

                document.getElementById('edit_funcao').value = u.funcao;
                document.getElementById('edit_status_conta').value = u.status_conta_sistema || 'ativo';
                document.getElementById('edit_id_plano_assinatura_ativo').value = u.id_plano_assinatura_ativo || '';


                bsEditModal.show();
            } else {
                exibirFeedbackUser(false, data.msg || 'Erro ao carregar dados do utilizador.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            exibirFeedbackUser(false, 'Erro de comunicação ao carregar dados do utilizador.');
        });
    }

    formEditarUtilizador.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            exibirFeedbackUser(data.ok, data.msg);
            if (data.ok) {
                bsEditModal.hide();
                fetchUtilizadores(); 
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            exibirFeedbackUser(false, 'Erro de comunicação ao atualizar utilizador.');
        });
    });

    function excluirUtilizador(idUtilizador) {
        const formData = new FormData();
        formData.append('action', 'delete_utilizador');
        formData.append('id_utilizador', idUtilizador);

        fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            exibirFeedbackUser(data.ok, data.msg);
            if (data.ok) {
                fetchUtilizadores(); // Atualizar a lista após exclusão
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            exibirFeedbackUser(false, 'Erro de comunicação ao excluir utilizador.');
        });
    }
    
    filtroFuncaoSelect.addEventListener('change', fetchUtilizadores);
    filtroStatusContaSelect.addEventListener('change', fetchUtilizadores);
    
    let debounceTimeout;
    buscaNomeEmailInput.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(fetchUtilizadores, 500);
    });
    
    fetchUtilizadores(); // Initial load
});
</script>
</body>
</html>