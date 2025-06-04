<?php
// player_podcast.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Gestor de Sessões e Conexão BD
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/db/db_connect.php';

// 2. Informações do Utilizador
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_nome_completo'] ?? 'Utilizador';
$userEmail = $_SESSION['user_email'] ?? 'utilizador@email.com';
$userAvatarUrlSession = $_SESSION['user_avatar_url'] ?? null;
$userFirstName = explode(' ', $userName)[0];
$userFuncao = $_SESSION['user_funcao'] ?? 'utilizador';
// Assegurar que userPlanoId seja um inteiro ou null. Se for '0' de uma string, converter.
$userPlanoId = isset($_SESSION['user_plano_id']) ? (int)$_SESSION['user_plano_id'] : 0;


function get_user_avatar_placeholder($user_name, $avatar_url_from_session, $size = 40) {
    if ($avatar_url_from_session && strlen(trim($avatar_url_from_session)) > 5 && filter_var($avatar_url_from_session, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($avatar_url_from_session);
    }
    $name_encoded = urlencode($user_name);
    return "https://ui-avatars.com/api/?name={$name_encoded}&background=2760f3&color=fff&size={$size}&rounded=true&bold=true";
}
$avatarUrl = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 40);
$currentUserAvatarSmall = get_user_avatar_placeholder($userName, $userAvatarUrlSession, 36);

// 3. Slug do Podcast
$slug_podcast = trim(filter_input(INPUT_GET, 'slug', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

// 4. AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id_podcast_ajax = intval($_POST['id_podcast'] ?? 0);

    // --- INÍCIO DOS AJAX HANDLERS ---
    if ($_POST['acao'] === 'curtir' && $userId && $id_podcast_ajax > 0) {
        $ja_curtiu_stmt = $pdo->prepare("SELECT 1 FROM curtidas_conteudo WHERE id_utilizador=? AND tipo_conteudo='podcast' AND id_conteudo=?");
        $ja_curtiu_stmt->execute([$userId, $id_podcast_ajax]);
        if ($ja_curtiu_stmt->fetch()) {
            $pdo->prepare("DELETE FROM curtidas_conteudo WHERE id_utilizador=? AND tipo_conteudo='podcast' AND id_conteudo=?")->execute([$userId, $id_podcast_ajax]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO curtidas_conteudo (id_utilizador, tipo_conteudo, id_conteudo, data_curtida) VALUES (?, 'podcast', ?, NOW())")->execute([$userId, $id_podcast_ajax]);
            $liked = true;
        }
        $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM curtidas_conteudo WHERE tipo_conteudo='podcast' AND id_conteudo=?");
        $total_stmt->execute([$id_podcast_ajax]);
        $total = $total_stmt->fetchColumn();
        echo json_encode(['success' => true, 'liked' => $liked, 'total' => $total]);
        exit;
    }

    if ($_POST['acao'] === 'listar_comentarios' && $id_podcast_ajax > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.nome_completo, u.avatar_url
            FROM comentarios_conteudo c
            JOIN utilizadores u ON c.id_utilizador=u.id_utilizador
            WHERE c.tipo_conteudo_principal='podcast' AND c.id_conteudo_principal=? AND c.ativo=1
            ORDER BY c.data_comentario ASC
        ");
        $stmt->execute([$id_podcast_ajax]);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tree = [];
        foreach ($all as &$row) {
            $row['avatar_url_processed'] = get_user_avatar_placeholder($row['nome_completo'], $row['avatar_url'], 36);
            $row['respostas'] = [];
            $tree[$row['id_comentario']] = $row;
        }
        unset($row);
        $raiz = [];
        foreach ($tree as &$n) {
            if ($n['id_comentario_pai'] && isset($tree[$n['id_comentario_pai']])) {
                $tree[$n['id_comentario_pai']]['respostas'][] = &$n;
            } else {
                $raiz[] = &$n;
            }
        }
        unset($n);
        echo json_encode(['success' => true, 'comentarios' => $raiz]);
        exit;
    }

    if ($_POST['acao'] === 'comentar' && !empty($_POST['texto_comentario']) && $userId && $id_podcast_ajax > 0) {
        $texto = trim(htmlspecialchars($_POST['texto_comentario']));
        $id_pai = intval($_POST['id_comentario_pai'] ?? 0);
        if (!empty($texto)) {
            $stmt = $pdo->prepare("INSERT INTO comentarios_conteudo (id_utilizador, tipo_conteudo_principal, id_conteudo_principal, id_comentario_pai, texto_comentario, data_comentario, ativo) VALUES (?, 'podcast', ?, ?, ?, NOW(), 1)");
            $stmt->execute([$userId, $id_podcast_ajax, $id_pai ?: null, $texto]);
            echo json_encode(['success' => true, 'id_comentario' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'O comentário não pode estar vazio.']);
        }
        exit;
    }

    if ($_POST['acao'] === 'editar' && !empty($_POST['id_comentario']) && !empty($_POST['texto_comentario']) && $userId) {
        $id_comentario = intval($_POST['id_comentario']);
        $texto = trim(htmlspecialchars($_POST['texto_comentario']));
        if (!empty($texto)) {
            // Assegurar que o comentário pertence ao podcast correto e ao utilizador
            $check_stmt = $pdo->prepare("SELECT 1 FROM comentarios_conteudo WHERE id_comentario=? AND id_utilizador=? AND id_conteudo_principal=? AND tipo_conteudo_principal='podcast'");
            $check_stmt->execute([$id_comentario, $userId, $id_podcast_ajax]);
            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE comentarios_conteudo SET texto_comentario=?, editado=1, data_ultima_edicao=NOW() WHERE id_comentario=? AND id_utilizador=?");
                $stmt->execute([$texto, $id_comentario, $userId]);
                echo json_encode(['success' => $stmt->rowCount() > 0]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Permissão negada ou comentário não encontrado.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'O comentário não pode estar vazio.']);
        }
        exit;
    }

    if ($_POST['acao'] === 'apagar' && !empty($_POST['id_comentario']) && $userId) {
        $id_comentario = intval($_POST['id_comentario']);
        $stmt_check_podcast = $pdo->prepare("SELECT 1 FROM comentarios_conteudo WHERE id_comentario = ? AND id_utilizador = ? AND id_conteudo_principal = ? AND tipo_conteudo_principal = 'podcast'");
        $stmt_check_podcast->execute([$id_comentario, $userId, $id_podcast_ajax]);
        if ($stmt_check_podcast->fetch()) {
            $stmt = $pdo->prepare("UPDATE comentarios_conteudo SET ativo=0 WHERE id_comentario=? AND id_utilizador=?");
            $stmt->execute([$id_comentario, $userId]);
            echo json_encode(['success' => $stmt->rowCount() > 0]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Permissão negada ou comentário não encontrado.']);
        }
        exit;
    }

    if ($_POST['acao'] === 'salvar_posicao' && $userId && $id_podcast_ajax > 0 && isset($_POST['tempo_atual'])) {
        $tempo_atual = floatval($_POST['tempo_atual']);
        if ($tempo_atual >= 0) {
            try {
                $stmt_save_pos = $pdo->prepare("
                    INSERT INTO posicao_reproducao_utilizador (id_utilizador, id_podcast, posicao_segundos)
                    VALUES (:id_utilizador, :id_podcast, :posicao_segundos)
                    ON DUPLICATE KEY UPDATE posicao_segundos = :posicao_segundos_update, data_atualizacao = NOW()
                ");
                $stmt_save_pos->execute([
                    ':id_utilizador' => $userId,
                    ':id_podcast' => $id_podcast_ajax,
                    ':posicao_segundos' => $tempo_atual,
                    ':posicao_segundos_update' => $tempo_atual 
                ]);
                echo json_encode(['success' => true, 'message' => 'Posição guardada.']);
            } catch (PDOException $e) {
                error_log("Erro PDO ao salvar posição: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erro de base de dados ao guardar posição.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tempo inválido.']);
        }
        exit;
    }

    if ($_POST['acao'] === 'avaliar_podcast' && $userId && $id_podcast_ajax > 0 && isset($_POST['nota'])) {
        $nota = intval($_POST['nota']);
        if ($nota >= 1 && $nota <= 5) {
            try {
                $stmt_rate = $pdo->prepare("
                    INSERT INTO avaliacoes_podcast (id_utilizador, id_podcast, nota)
                    VALUES (:id_utilizador, :id_podcast, :nota_insert) 
                    ON DUPLICATE KEY UPDATE nota = :nota_update
                ");
                $stmt_rate->execute([
                    ':id_utilizador' => $userId,
                    ':id_podcast' => $id_podcast_ajax,
                    ':nota_insert' => $nota,
                    ':nota_update' => $nota
                ]);

                $stmt_avg_rating = $pdo->prepare("SELECT AVG(nota) as media, COUNT(id_avaliacao) as total FROM avaliacoes_podcast WHERE id_podcast = :id_podcast");
                $stmt_avg_rating->execute([':id_podcast' => $id_podcast_ajax]);
                $res_avg = $stmt_avg_rating->fetch(PDO::FETCH_ASSOC);
                $novaMedia = $res_avg['media'] ? round($res_avg['media'], 1) : 0;
                $novoTotal = intval($res_avg['total']);

                echo json_encode(['success' => true, 'message' => 'Avaliação guardada!', 'novaMedia' => $novaMedia, 'novoTotal' => $novoTotal]);
            } catch (PDOException $e) {
                error_log("Erro ao avaliar podcast: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erro de base de dados ao guardar avaliação.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nota inválida.']);
        }
        exit;
    }
    // --- FIM DOS AJAX HANDLERS ---

    echo json_encode(['success' => false, 'message' => 'Ação inválida ou dados insuficientes.']);
    exit;
}

// 5. Carregar Dados do Podcast
if (empty($slug_podcast)) {
    $_SESSION['feedback_message'] = "Nenhum podcast especificado.";
    $_SESSION['feedback_type'] = "error";
    header('Location: podcasts.php');
    exit;
}

$stmt_podcast = $pdo->prepare("
    SELECT p.id_podcast, p.titulo_podcast, p.descricao_podcast, p.url_audio, p.link_material_apoio, 
           p.visibilidade, p.id_plano_minimo, p.slug_podcast, p.imagem_capa_url,
           a.id_assunto, a.nome_assunto, a.slug_assunto, 
           c.id_categoria, c.nome_categoria, c.slug_categoria
    FROM podcasts p
    JOIN assuntos_podcast a ON p.id_assunto=a.id_assunto
    JOIN categorias_podcast c ON a.id_categoria=c.id_categoria
    WHERE p.slug_podcast=?
");
$stmt_podcast->execute([$slug_podcast]);
$podcast = $stmt_podcast->fetch(PDO::FETCH_ASSOC);

if (!$podcast || $podcast['visibilidade'] === 'privado') {
    $_SESSION['feedback_message'] = "Podcast não encontrado ou não acessível.";
    $_SESSION['feedback_type'] = "error";
    header('Location: podcasts.php');
    exit;
}

$pageTitle = htmlspecialchars($podcast['titulo_podcast']);

// 6. Controlo de Acesso
$is_admin = ($userFuncao === 'administrador');
$pode_aceder = false;

if ($podcast['visibilidade'] === 'publico') {
    $pode_aceder = true;
} elseif ($podcast['visibilidade'] === 'restrito_assinantes') {
    if ($is_admin) {
        $pode_aceder = true;
    } elseif ($userId && $userPlanoId > 0) { // Utilizador logado e tem um plano (ID > 0 indica plano pago)
        if ($podcast['id_plano_minimo'] === null || $userPlanoId >= (int)$podcast['id_plano_minimo']) {
            $pode_aceder = true;
        }
    }
}

if (!$pode_aceder) {
    if ($podcast['visibilidade'] === 'restrito_assinantes' && !$is_admin) {
        $redirect_reason = $userId ? "upgrade_required" : "login_then_upgrade";
        $mensagem_prompt = $userId ? 
            "O podcast \"" . htmlspecialchars($podcast['titulo_podcast']) . "\" é conteúdo premium. O seu plano atual não permite acesso. Considere fazer um upgrade!" :
            "O podcast \"" . htmlspecialchars($podcast['titulo_podcast']) . "\" é premium. Faça login ou assine um dos nossos planos para ter acesso!";
        
        $_SESSION['upgrade_prompt_message'] = $mensagem_prompt; // Para ser usada na página de planos
        header("Location: planos.php?from_content=" . urlencode($slug_podcast) . "&reason=" . $redirect_reason);
        exit;
    } else {
        $_SESSION['feedback_message'] = "Você não tem permissão para acessar este podcast (Visibilidade: " . htmlspecialchars($podcast['visibilidade']) . ").";
        $_SESSION['feedback_type'] = "warning";
    }
}

// 7. Carregar Dados Adicionais (só se o acesso for permitido)
$posicao_guardada = 0;
$avaliacao_media = 0;
$total_avaliacoes = 0;
$avaliacao_utilizador = 0;
$esta_na_fila = false;
$tags_episodio = [];
$usuario_ja_curtiu = false;
$total_curtidas_inicial = 0;
$total_comentarios_inicial = 0;
$episodios_relacionados = [];

if ($pode_aceder) {
    if ($userId && $podcast['id_podcast']) {
        $stmt_pos = $pdo->prepare("SELECT posicao_segundos FROM posicao_reproducao_utilizador WHERE id_utilizador = :id_utilizador AND id_podcast = :id_podcast");
        $stmt_pos->execute([':id_utilizador' => $userId, ':id_podcast' => $podcast['id_podcast']]);
        $result_pos = $stmt_pos->fetch(PDO::FETCH_ASSOC);
        if ($result_pos) $posicao_guardada = floatval($result_pos['posicao_segundos']);

        $stmt_user_rating = $pdo->prepare("SELECT nota FROM avaliacoes_podcast WHERE id_podcast = :id_podcast AND id_utilizador = :id_utilizador");
        $stmt_user_rating->execute([':id_podcast' => $podcast['id_podcast'], ':id_utilizador' => $userId]);
        if($res_user_rating = $stmt_user_rating->fetch(PDO::FETCH_ASSOC)) $avaliacao_utilizador = intval($res_user_rating['nota']);

        $stmt_fila_check = $pdo->prepare("SELECT 1 FROM fila_reproducao_utilizador WHERE id_utilizador = :id_utilizador AND id_podcast = :id_podcast");
        $stmt_fila_check->execute([':id_utilizador' => $userId, ':id_podcast' => $podcast['id_podcast']]);
        if ($stmt_fila_check->fetch()) $esta_na_fila = true;

        $stmt_curtida_check = $pdo->prepare("SELECT 1 FROM curtidas_conteudo WHERE id_utilizador=? AND tipo_conteudo='podcast' AND id_conteudo=?");
        $stmt_curtida_check->execute([$userId, $podcast['id_podcast']]);
        if ($stmt_curtida_check->fetch()) $usuario_ja_curtiu = true;
    }

    $stmt_avg_rating = $pdo->prepare("SELECT AVG(nota) as media, COUNT(id_avaliacao) as total FROM avaliacoes_podcast WHERE id_podcast = :id_podcast");
    $stmt_avg_rating->execute([':id_podcast' => $podcast['id_podcast']]);
    if($res_avg = $stmt_avg_rating->fetch(PDO::FETCH_ASSOC)){
        $avaliacao_media = $res_avg['media'] ? round($res_avg['media'], 1) : 0;
        $total_avaliacoes = intval($res_avg['total']);
    }
    
    $stmt_tags = $pdo->prepare("SELECT t.nome_tag, t.slug_tag FROM tags t JOIN podcast_tags pt ON t.id_tag = pt.id_tag WHERE pt.id_podcast = :id_podcast LIMIT 5");
    $stmt_tags->execute([':id_podcast' => $podcast['id_podcast']]);
    $tags_from_db = $stmt_tags->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($tags_from_db)) {
        foreach($tags_from_db as $tag_db) {
            $tags_episodio[] = ['nome' => htmlspecialchars($tag_db['nome_tag']), 'slug' => htmlspecialchars($tag_db['slug_tag'])];
        }
    } else { 
        $tags_episodio[] = ['nome' => htmlspecialchars($podcast['nome_assunto']), 'slug' => htmlspecialchars($podcast['slug_assunto'])];
    }

    $stmt_total_curtidas = $pdo->prepare("SELECT COUNT(*) FROM curtidas_conteudo WHERE tipo_conteudo='podcast' AND id_conteudo=?");
    $stmt_total_curtidas->execute([$podcast['id_podcast']]);
    $total_curtidas_inicial = $stmt_total_curtidas->fetchColumn();

    $stmt_total_comentarios = $pdo->prepare("SELECT COUNT(*) FROM comentarios_conteudo WHERE tipo_conteudo_principal='podcast' AND id_conteudo_principal=? AND ativo=1");
    $stmt_total_comentarios->execute([$podcast['id_podcast']]);
    $total_comentarios_inicial = $stmt_total_comentarios->fetchColumn();

    // Episódios Relacionados - CORRIGIDO
    $sql_related = "SELECT p.id_podcast as related_id_podcast, p.titulo_podcast, p.slug_podcast, p.imagem_capa_url, p.visibilidade, p.id_plano_minimo as related_id_plano_minimo
                    FROM podcasts p
                    WHERE p.id_assunto = :id_assunto 
                      AND p.id_podcast <> :id_podcast_current
                      AND p.visibilidade <> 'privado'
                    ORDER BY RAND() 
                    LIMIT 5"; // Buscar até 5 para filtrar e ficar com 3
    $rel_stmt = $pdo->prepare($sql_related);
    $rel_stmt->execute([
        ':id_assunto' => $podcast['id_assunto'], 
        ':id_podcast_current' => $podcast['id_podcast']
    ]);
    $episodios_relacionados_raw = $rel_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($episodios_relacionados_raw as $ep_raw){
        if (count($episodios_relacionados) >= 3) break; // Limitar a 3 após verificação de acesso

        $ep_is_premium = ($ep_raw['visibilidade'] === 'restrito_assinantes');
        $ep_pode_aceder_relacionado = false;

        if ($ep_raw['visibilidade'] === 'publico') {
            $ep_pode_aceder_relacionado = true;
        } elseif ($ep_raw['visibilidade'] === 'restrito_assinantes') {
            if ($is_admin) {
                $ep_pode_aceder_relacionado = true;
            } elseif ($userId && $userPlanoId > 0) {
                if ($ep_raw['related_id_plano_minimo'] === null || $userPlanoId >= (int)$ep_raw['related_id_plano_minimo']) {
                    $ep_pode_aceder_relacionado = true;
                }
            }
        }

        // Só adiciona se o utilizador puder aceder OU se for premium (para mostrar com cadeado/prompt no futuro, se desejado)
        // Para este caso, só adicionamos se puder aceder, simplificando
        // if ($ep_pode_aceder_relacionado) { 
            $ep_link = "player_podcast.php?slug=" . htmlspecialchars($ep_raw['slug_podcast']);
            $ep_data_attrs = "";
            if ($ep_is_premium && !$ep_pode_aceder_relacionado) {
                $ep_data_attrs = " data-premium='true' data-requires-upgrade='true' data-title=\"" . htmlspecialchars($ep_raw['titulo_podcast']) . "\" ";
            }

            $episodios_relacionados[] = [
                'titulo_podcast' => htmlspecialchars($ep_raw['titulo_podcast']),
                'slug_podcast' => htmlspecialchars($ep_raw['slug_podcast']),
                'imagem_capa_url' => $ep_raw['imagem_capa_url'] ?? 'https://placehold.co/80x80/2760f3/FFFFFF?text=' . urlencode(mb_substr($ep_raw['titulo_podcast'],0,1)) . '&font=Inter',
                'is_premium' => $ep_is_premium,
                'link' => $ep_link,
                'data_attributes' => $ep_data_attrs,
                'visibilidade' => $ep_raw['visibilidade'], // Para JS do modal
                'id_plano_minimo' => $ep_raw['related_id_plano_minimo']
            ];
        // }
    }
}


$breadcrumbs = [
    ['nome' => 'Podcasts', 'link' => 'podcasts.php'],
    ['nome' => htmlspecialchars($podcast['nome_categoria']), 'link' => 'podcasts.php?categoria=' . urlencode($podcast['slug_categoria'])],
    ['nome' => htmlspecialchars($podcast['nome_assunto']), 'link' => 'podcasts.php?categoria=' . urlencode($podcast['slug_categoria']) . '&assunto=' . urlencode($podcast['slug_assunto'])],
    ['nome' => $pageTitle, 'link' => '#']
];

$podcast_cover_image = $podcast['imagem_capa_url'] ?? 'https://placehold.co/600x400/2760f3/FFFFFF?text=' . urlencode(substr($podcast['titulo_podcast'],0,3)) . '&font=Inter';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?> - AudioTO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-blue': '#2563eb',
                        'primary-blue-light': '#e0eaff',
                        'primary-blue-dark': '#1e40af',
                        'brand-banner-start': '#6ee7b7', // Da inicio.php
                        'brand-banner-end': '#3b82f6',   // Da inicio.php
                        'light-bg': '#f7fafc', 
                        'card-bg': '#ffffff',
                        'dark-text': '#1f2937',
                        'medium-text': '#4b5563',
                        'light-text': '#6b7280',
                        'border-color': '#E5E7EB',
                        'border-color-strong': '#D1D5DB',
                        'success': '#10B981',
                        'danger': '#EF4444',
                        'warning': '#F59E0B', 
                        'info': '#3B82F6',
                        'yellow': { '400': '#FACC15', '500': '#EAB308' },
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'heartBeat': 'heartBeat 0.8s cubic-bezier(0.215, 0.610, 0.355, 1.000) both',
                        'pulse-comment': 'pulseComment 1.8s ease-out',
                        'slide-in-right': 'slideInRight 0.5s ease-out forwards',
                        'slide-out-right': 'slideOutRight 0.5s ease-in forwards',
                        'modal-scale-in': 'modalScaleIn 0.3s ease-out forwards',
                        'modal-scale-out': 'modalScaleOut 0.3s ease-in forwards',
                    },
                    keyframes: {
                        fadeInUp: { '0%': { opacity: '0', transform: 'translateY(15px)' }, '100%': { opacity: '1', transform: 'translateY(0)' }, },
                        heartBeat: { '0%': { transform: 'scale(1)' }, '14%': { transform: 'scale(1.3)' }, '28%': { transform: 'scale(1)' }, '42%': { transform: 'scale(1.2)' }, '70%': { transform: 'scale(1)' } },
                        pulseComment: { '0%': { boxShadow: '0 0 0 0 rgba(37, 99, 235, 0.4)' }, '70%': { boxShadow: '0 0 0 12px rgba(37, 99, 235, 0)' }, '100%': { boxShadow: '0 0 0 0 rgba(37, 99, 235, 0)' } },
                        slideInRight: { '0%': { transform: 'translateX(100%)', opacity: '0' }, '100%': { transform: 'translateX(0)', opacity: '1' } },
                        slideOutRight: { '0%': { transform: 'translateX(0)', opacity: '1' }, '100%': { transform: 'translateX(100%)', opacity: '0' } },
                        modalScaleIn: { '0%': { opacity: '0', transform: 'scale(0.95)' }, '100%': { opacity: '1', transform: 'scale(1)' }, },
                        modalScaleOut: { '0%': { opacity: '1', transform: 'scale(1)' }, '100%': { opacity: '0', transform: 'scale(0.95)' }, }
                    },
                     boxShadow: { // Sombras da inicio.php
                        'lg': '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)',
                        'xl': '0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04)',
                        'interactive': '0 4px 14px 0 rgba(0, 0, 0, 0.05), 0 0px 4px 0 rgba(0, 0, 0, 0.03)',
                        'interactive-lg': '0 10px 20px rgba(0,0,0,0.07), 0 3px 6px rgba(0,0,0,0.05)',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: theme('colors.light-bg'); color: theme('colors.dark-text'); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        :root { 
            --plyr-color-main: theme('colors.primary-blue'); 
            --plyr-font-family: 'Inter', sans-serif; 
            --plyr-control-radius: 0.5rem;
            --plyr-tooltip-background: theme('colors.dark-text');
            --plyr-tooltip-color: theme('colors.card-bg');
        }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: theme('colors.gray.100'); border-radius: 10px; } /* Usando gray.100 como inicio.php */
        ::-webkit-scrollbar-thumb { background: theme('colors.gray.300'); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: theme('colors.gray.400'); }

        /* Estilos da Sidebar da inicio.php */
        .sidebar { transition: left 0.3s ease-in-out; } /* Mudado de transform para left */
        .sidebar.open { left: 0; }
        .sidebar-icon { width: 20px; height: 20px; }
        nav a:hover .sidebar-icon { /* Removido transform: scale(1.1); para consistência com inicio.php */ }
        .active-nav-link { background-color: theme('colors.primary-blue-light'); color: theme('colors.primary-blue'); border-right: 3px solid theme('colors.primary-blue'); }
        .active-nav-link i { color: theme('colors.primary-blue'); }
        
        /* Estilos de Toast da inicio.php (ajustados) */
        .toast { position: fixed; top: 20px; right: 20px; z-index: 1050; padding: 0.9rem 1.5rem; border-radius: 0.5rem; color: #fff; box-shadow: theme('boxShadow.lg'); opacity: 0; transform: translateX(100%); transition: opacity .3s ease, transform .3s ease; font-size: 0.875rem; font-weight: 500; }
        .toast.show { opacity: 1; transform: translateX(0); animation: none; } /* Simplificado */
        .toast.hide { opacity: 0; transform: translateX(100%); animation: none; }
        .toast-success { background-color: theme('colors.success'); } 
        .toast-error { background-color: theme('colors.danger'); }
        .toast-info { background-color: theme('colors.info'); } 
        .toast-warning { background-color: theme('colors.warning'); color: theme('colors.dark-text');}
        
        .comment-card { transition: box-shadow 0.2s ease-in-out; border: 1px solid theme('colors.border-color'); }
        .comment-card:hover { box-shadow: theme('boxShadow.lg');  }
        .comment-card.new-comment-highlight { animation: pulseComment 1.8s ease-out; border-color: var(--plyr-color-main); }
        .reply-indent { margin-left: 2.5rem; } @media (min-width: 640px) { .reply-indent { margin-left: 3.5rem; } }
        
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; padding: 0.5rem; border-radius: 9999px; transition: background-color 0.2s ease, color 0.2s ease; }
        .btn-icon:hover { background-color: theme('colors.primary-blue-light'); }
        .btn-icon svg, .btn-icon i { width: 1rem; height: 1rem; }
        
        .liked-animation .like-icon-path { animation: heartBeat 0.8s cubic-bezier(0.215, 0.610, 0.355, 1.000) both; }
        
        .prose { max-width: none; } .prose h1, .prose h2, .prose h3 { color: theme('colors.dark-text'); font-weight: 700; }
        .prose p { color: theme('colors.medium-text'); line-height: 1.75; margin-bottom: 1em; font-size: 0.95rem; }
        .prose a { color: var(--plyr-color-main); text-decoration: none; font-weight: 500; }
        .prose a:hover { text-decoration: underline;}
        
        .form-textarea { appearance: none; background-color: theme('colors.card-bg'); border-color: theme('colors.border-color-strong'); border-width: 1px; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 0.875rem; line-height: 1.5; width: 100%; transition: border-color .2s ease, box-shadow .2s ease; }
        .form-textarea:focus { outline: 0; border-color: var(--plyr-color-main); box-shadow: 0 0 0 3px rgba(37, 99, 235,0.25); }
        
        .plyr--audio .plyr__controls { border-radius: 0.75rem; box-shadow: theme('boxShadow.lg'); background: #fff; padding: 10px; border: 1px solid theme('colors.border-color'); }
        .plyr__progress input[type=range]::-webkit-slider-thumb { box-shadow: 0 1px 1px rgba(var(--plyr-color-main-rgb),.15),0 0 0 1px rgba(var(--plyr-color-main-rgb),.2); }
        .plyr__control--overlaid { background: rgba(var(--plyr-color-main-rgb), 0.85); border-radius: 0.75rem;}
        .plyr__control.plyr__tab-focus, .plyr__control:hover, .plyr__control[aria-expanded=true] { background: theme('colors.primary-blue-light'); color: var(--plyr-color-main); }

        /* Botões baseados na inicio.php */
        .btn-base { font-weight: 500; padding: 0.625rem 1.25rem; border-radius: 0.5rem; transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; line-height: 1.25rem; }
        .btn-base:hover { transform: translateY(-1px); } 
        .btn-base:active { transform: translateY(0px); }
        .btn-primary { background-color: theme('colors.primary-blue'); color: white; }
        .btn-primary:hover { background-color: theme('colors.primary-blue-dark'); }
        .btn-outline { border: 1px solid theme('colors.primary-blue'); color: theme('colors.primary-blue'); background-color: white; } 
        .btn-outline:hover { background-color: theme('colors.primary-blue-light'); }
        .btn-gray { background-color: theme('colors.border-color'); color: theme('colors.medium-text'); } 
        .btn-gray:hover { background-color: theme('colors.border-color-strong'); }
        .btn-base[disabled] { background-color: theme('colors.border-color-strong'); color: theme('colors.light-text'); opacity:0.7; cursor: not-allowed; }
        .btn-base[disabled]:hover { transform: none; }
        
        .speed-controls button { padding: 0.35rem 0.7rem; font-size: 0.75rem; margin: 0 0.15rem; border: 1px solid theme('colors.primary-blue-light'); color: theme('colors.primary-blue'); border-radius: 0.375rem; transition: all 0.2s ease; }
        .speed-controls button:hover { background-color: theme('colors.primary-blue-light'); }
        .speed-controls button.active-speed { background-color: theme('colors.primary-blue'); color: white; font-weight: 600; }
        
        .rating-stars .fa-star { cursor: pointer; color: theme('colors.border-color-strong'); transition: color 0.2s ease, transform 0.2s ease; font-size: 1.125rem; margin: 0 0.05rem; } /* Ajustado tamanho */
        .rating-stars .fa-star.rated, .rating-stars .fa-star.hovered { color: theme('colors.yellow.400'); transform: scale(1.1); }
        .rating-stars:hover .fa-star { color: theme('colors.yellow.400');}
        .rating-stars .fa-star:hover ~ .fa-star { color: theme('colors.border-color-strong'); }

        .tag-link { transition: all 0.2s ease-in-out; }
        .tag-link:hover { transform: translateY(-1px); box-shadow: theme('boxShadow.md'); } /* Usando shadow.md da Tailwind */

        .related-episode-link:hover .related-episode-title { color: theme('colors.primary-blue-dark'); }
        .related-episode-link:hover .related-episode-play-icon { opacity: 1; transform: scale(1.1); }

        .form-input-search {
            background-color: theme('colors.gray.100'); /* Consistente com inicio.php */
            border: 1px solid theme('colors.gray.100');
            transition: all 0.2s ease;
        }
        .form-input-search:focus {
            background-color: white;
            border-color: theme('colors.primary-blue');
            box-shadow: 0 0 0 3px rgba(37, 99, 235,0.2);
        }
        .alert-box { animation: fadeInUp 0.5s ease-out; }

        .content-type-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem; /* Ajustado */
            font-size: 0.65rem; /* Mais pequeno */
            font-weight: 600;
            border-radius: 0.25rem; /* Menos arredondado */
            text-transform: uppercase;
            letter-spacing: 0.05em;
            vertical-align: middle;
            margin-left: 0.75rem;
        }
        .badge-premium {
            background-color: theme('colors.warning');
            color: theme('colors.gray.800'); /* Melhor contraste */
        }
        .badge-gratuito {
            background-color: theme('colors.success');
            color: white;
        }
         /* Modal genérico (usado para partilha e upgrade) */
        .modal-overlay {
            background-color: rgba(31, 41, 55, 0.6); /* gray-800 com opacidade */
            transition: opacity 0.3s ease-in-out;
        }
        .modal-dialog {
            max-width: 450px;
            width: calc(100% - 2rem);
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
            animation: modalScaleIn 0.3s ease-out forwards;
        }
        .modal-overlay.hidden {
            opacity: 0;
            pointer-events: none; /* Importante */
        }
        .modal-overlay.hidden .modal-dialog {
            animation: modalScaleOut 0.3s ease-in forwards;
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
        <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-card-bg p-4 shadow-sm flex justify-between items-center border-b border-gray-200 sticky top-0 z-30">
                <div class="flex items-center">
                    <button id="mobileMenuButton" aria-label="Abrir menu lateral" class="lg:hidden text-gray-600 hover:text-primary-blue mr-3 p-2" onclick="toggleMobileSidebar()">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="relative w-full max-w-xs hidden sm:block">
                        <label for="search-audioto-player" class="sr-only">Buscar em AudioTO</label>
                        <input type="text" id="search-audioto-player" placeholder="Buscar em AudioTO..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light">
                        <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button aria-label="Notificações" class="text-gray-500 hover:text-primary-blue relative p-2">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if(true): // Simular notificação ?>
                        <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-primary-blue ring-1 ring-white">
                            <span class="sr-only">Novas notificações</span>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="relative">
                        <button id="userDropdownButton" aria-label="Menu do usuário" aria-haspopup="true" aria-expanded="false" class="flex items-center space-x-2 focus:outline-none">
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar de <?php echo htmlspecialchars($userName); ?>" class="w-9 h-9 rounded-full border-2 border-primary-blue-light">
                            <div class="hidden md:block text-left">
                                <p class="text-xs font-medium text-dark-text"><?php echo htmlspecialchars($userFirstName); ?></p>
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
                <nav class="mb-4 text-xs font-medium text-light-text" aria-label="Breadcrumb"> <ol class="inline-flex items-center space-x-1 md:space-x-1.5">
                        <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <li class="inline-flex items-center">
                            <?php if ($i < count($breadcrumbs) - 1): ?>
                                <a href="<?php echo htmlspecialchars($crumb['link']); ?>" class="hover:text-primary-blue transition-colors duration-150"><?php echo $crumb['nome']; ?></a>
                                <i class="fas fa-chevron-right w-2.5 h-2.5 text-gray-400 mx-1.5"></i>
                            <?php else: ?>
                                <span class="text-dark-text font-semibold"><?php echo $crumb['nome']; ?></span>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>

                <?php if (!$pode_aceder && isset($_SESSION['feedback_message'])): ?>
                <div class="alert-box bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md shadow mb-6" role="alert">
                    <div class="flex">
                        <div class="py-1"><i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i></div>
                        <div>
                            <p class="font-bold">Acesso Restrito</p>
                            <p class="text-sm"><?php echo htmlspecialchars($_SESSION['feedback_message']); ?></p>
                            <?php unset($_SESSION['feedback_message']); unset($_SESSION['feedback_type']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($pode_aceder || (!$pode_aceder && isset($_SESSION['feedback_message']))): // Só renderiza o resto se puder aceder OU se o feedback já foi mostrado (e não houve redirect) ?>

                <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
                    <div class="flex-1 lg:w-[calc(100%-26rem)] space-y-6 animate-fade-in-up" style="animation-delay: 0.1s;"> <article class="bg-card-bg rounded-xl shadow-lg overflow-hidden border border-border-color">
                            <div class="p-5 sm:p-6 md:p-8">
                                <div class="flex items-center mb-2 flex-wrap">
                                    <h1 class="text-2xl md:text-3xl font-bold text-dark-text tracking-tight leading-tight mr-3"><?php echo htmlspecialchars($podcast['titulo_podcast']); ?></h1>
                                    <?php if ($podcast['visibilidade'] === 'restrito_assinantes'): ?>
                                        <span class="content-type-badge badge-premium whitespace-nowrap">Premium</span>
                                    <?php else: ?>
                                        <span class="content-type-badge badge-gratuito whitespace-nowrap">Gratuito</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-4 text-xs text-medium-text space-x-2">
                                    <span>Em: <a href="podcasts.php?categoria=<?php echo urlencode($podcast['slug_categoria']); ?>&assunto=<?php echo urlencode($podcast['slug_assunto']); ?>" class="text-primary-blue hover:underline font-medium"><?php echo htmlspecialchars($podcast['nome_assunto']); ?></a></span>
                                    <span class="text-slate-300">|</span>
                                    <span>Categoria: <a href="podcasts.php?categoria=<?php echo urlencode($podcast['slug_categoria']); ?>" class="text-primary-blue hover:underline font-medium"><?php echo htmlspecialchars($podcast['nome_categoria']); ?></a></span>
                                </div>

                                <?php if ($pode_aceder): ?>
                                <div class="mb-5 rounded-lg overflow-hidden shadow-inner border border-border-color">
                                    <audio id="podcastPlayer" controls controlsList="nodownload" data-posicao-inicial="<?php echo $posicao_guardada; ?>">
                                        <source src="<?php echo htmlspecialchars($podcast['url_audio']); ?>" type="audio/mpeg">
                                        Seu navegador não suporta o elemento de áudio.
                                    </audio>
                                </div>
                                <div class="speed-controls mb-6 text-center">
                                    <span class="text-xs font-medium text-slate-600 mr-2 align-middle">Velocidade:</span>
                                    <button data-speed="0.75" class="btn-base !px-2 !py-1 !text-xs !shadow-sm">0.75x</button>
                                    <button data-speed="1" class="btn-base !px-2 !py-1 !text-xs !shadow-sm active-speed">1x</button>
                                    <button data-speed="1.25" class="btn-base !px-2 !py-1 !text-xs !shadow-sm">1.25x</button>
                                    <button data-speed="1.5" class="btn-base !px-2 !py-1 !text-xs !shadow-sm">1.5x</button>
                                    <button data-speed="1.75" class="btn-base !px-2 !py-1 !text-xs !shadow-sm">1.75x</button>
                                    <button data-speed="2" class="btn-base !px-2 !py-1 !text-xs !shadow-sm">2x</button>
                                </div>
                                <?php elseif (isset($_SESSION['feedback_message'])): /* Player desabilitado mas mensagem já mostrada */ ?>
                                    <div class="text-center p-5 bg-slate-100 rounded-lg text-medium-text border border-border-color my-6">
                                        <i class="fas fa-lock mr-2 text-light-text"></i>O player está desabilitado para este conteúdo.
                                        <?php if ($podcast['visibilidade'] === 'restrito_assinantes' && !$is_admin): ?>
                                            <br><a href="planos.php?from_content=<?php echo urlencode($slug_podcast); ?>" class="text-primary-blue hover:underline font-semibold mt-2 inline-block">Ver planos de assinatura</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($podcast['descricao_podcast'])): ?>
                                <div class="prose prose-sm sm:prose-base text-medium-text mb-5">
                                    <?php echo nl2br(htmlspecialchars($podcast['descricao_podcast'])); ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($tags_episodio)): ?>
                                <div class="mb-6">
                                    <h4 class="text-sm font-semibold text-dark-text mb-2">Tags:</h4>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($tags_episodio as $tag): ?>
                                            <a href="podcasts.php?tag=<?php echo htmlspecialchars($tag['slug']); ?>" class="tag-link text-xs bg-primary-blue-light text-primary-blue hover:bg-primary-blue hover:text-white px-3 py-1.5 rounded-full font-medium">
                                                <?php echo htmlspecialchars($tag['nome']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-3 mt-6 pt-6 border-t border-border-color">
                                    <?php if (!empty($podcast['link_material_apoio']) && $pode_aceder): ?>
                                        <a href="<?php echo htmlspecialchars($podcast['link_material_apoio']); ?>" target="_blank" rel="noopener noreferrer" class="btn-base btn-outline text-sm">
                                            <i class="fas fa-paperclip w-3.5 h-3.5 mr-2"></i> Material de Apoio
                                        </a>
                                    <?php endif; ?>
                                    <button id="btnShare" class="btn-base btn-gray text-sm">
                                        <i class="fas fa-share-alt w-3.5 h-3.5 mr-2"></i> Compartilhar
                                    </button>
                                  
                                </div>
                            </div>
                            
                            <div class="bg-slate-50 px-5 sm:px-6 md:px-8 py-5 border-t border-border-color flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="flex items-center gap-x-5 gap-y-3 flex-wrap">
                                    <button id="btnCurtirPodcast" <?php if (!$pode_aceder || !$userId) echo 'disabled'; ?>
                                            class="btn-base text-sm group <?php echo $usuario_ja_curtiu ? 'btn-primary liked-animation' : 'btn-gray'; ?> <?php if (!$pode_aceder || !$userId) echo 'opacity-50 cursor-not-allowed'; ?>">
                                        <i class="fas fa-heart w-4 h-4 mr-2 like-icon-path transition-colors duration-300 <?php echo $usuario_ja_curtiu ? 'text-white' : 'text-slate-400 group-hover:text-primary-blue'; ?>"></i>
                                        <span id="curtirTexto"><?php echo $usuario_ja_curtiu ? 'Curtido!' : 'Curtir'; ?></span>
                                    </button>
                                    <div class="rating-stars flex items-center" data-id-podcast="<?php echo $podcast['id_podcast']; ?>">
                                        <span class="text-xs text-medium-text mr-2.5">Sua Avaliação:</span>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo ($pode_aceder && $userId && $i <= $avaliacao_utilizador) ? 'rated' : ''; ?>" data-value="<?php echo $i; ?>" title="<?php echo $i; ?> estrela<?php echo $i > 1 ? 's' : ''; ?>" <?php if (!$pode_aceder || !$userId) echo 'style="cursor:not-allowed;"'; ?>></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="text-xs text-medium-text flex items-center space-x-4">
                                    <span class="flex items-center" title="Total de curtidas"><i class="fas fa-heart text-red-400 mr-1.5"></i><span id="curtidasTotal" class="font-medium"><?php echo $total_curtidas_inicial; ?></span></span>
                                    <span class="flex items-center" title="Total de comentários"><i class="fas fa-comments text-primary-blue mr-1.5"></i><span id="comentariosTotalHeader" class="font-medium"><?php echo $total_comentarios_inicial; ?></span></span>
                                    <span class="flex items-center" title="Avaliação média"><i class="fas fa-star text-yellow-400 mr-1.5"></i><span id="mediaAvaliacao" class="font-medium"><?php echo $avaliacao_media; ?></span>/5 (<span id="totalAvaliacoes"><?php echo $total_avaliacoes; ?></span>)</span>
                                </div>
                            </div>
                        </article>

                        <section class="bg-card-bg rounded-xl shadow-lg p-5 sm:p-6 md:p-8 border border-border-color">
                            <h2 class="text-xl md:text-2xl font-bold text-dark-text mb-6">Comentários (<span id="comentariosCountDisplay"><?php echo $total_comentarios_inicial; ?></span>)</h2>
                            <?php if($userId && $pode_aceder): ?>
                            <form id="formComentarioPodcast" class="mb-8">
                                <div class="flex items-start space-x-3.5">
                                    <img src="<?php echo $currentUserAvatarSmall; ?>" alt="Seu avatar" class="w-10 h-10 rounded-full flex-shrink-0 mt-0.5 border-2 border-primary-blue-light shadow-sm">
                                    <div class="flex-1">
                                        <div class="mb-2 hidden bg-sky-50 border border-sky-200 p-2.5 rounded-md text-xs shadow-sm" id="replyingToContainer">
                                            <span class="text-light-text">Respondendo a <strong id="replyingToName" class="text-medium-text"></strong></span>
                                            <button type="button" id="cancelReplyBtn" class="ml-2 text-xs text-red-500 hover:underline font-semibold">&times; Cancelar</button>
                                        </div>
                                        <textarea name="texto_comentario" rows="3" placeholder="Partilhe a sua opinião sobre este episódio..." class="form-textarea mb-3 text-sm" required></textarea>
                                        <input type="hidden" name="id_comentario_pai" value="">
                                        <div class="flex items-center justify-between mt-2">
                                            <button type="submit" class="btn-base btn-primary text-sm">
                                                <span id="commentSubmitButtonText">Comentar</span>
                                                <i id="commentSpinner" class="fas fa-spinner fa-spin ml-2 hidden"></i>
                                            </button>
                                            <button type="button" id="btnCancelarEdicao" class="hidden text-xs text-medium-text hover:text-primary-blue hover:underline font-medium">Cancelar Edição</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <?php elseif (!$userId && $pode_aceder): ?>
                            <div class="mb-8 p-4 bg-slate-100 rounded-lg text-center border border-border-color">
                                <p class="text-medium-text text-sm">Você precisa <a href="login.php?redirect=<?php echo urlencode("player_podcast.php?slug=".$slug_podcast); ?>" class="text-primary-blue font-semibold hover:underline">fazer login</a> para comentar.</p>
                            </div>
                            <?php elseif (!$pode_aceder): ?>
                                 <div class="mb-8 p-4 bg-slate-100 rounded-lg text-center border border-border-color">
                                <p class="text-medium-text text-sm"><i class="fas fa-lock mr-1"></i> Comentários desabilitados para este conteúdo.</p>
                            </div>
                            <?php endif; ?>
                            <div id="comentariosPodcastContainer">
                                <div id="comentariosPodcast" class="space-y-5">
                                    <div id="noCommentsMessage" class="text-medium-text py-10 text-center border-2 border-dashed border-border-color-strong rounded-lg <?php echo $total_comentarios_inicial > 0 ? 'hidden' : ''; ?>">
                                        <i class="fas fa-comment-slash fa-3x text-slate-400 mb-3"></i>
                                        <p class="font-medium text-sm">Ainda não há comentários.</p>
                                        <?php if($pode_aceder && $userId): ?>
                                        <p class="text-xs mt-1">Seja o primeiro a compartilhar sua opinião!</p>
                                        <?php endif; ?>
                                    </div>
                                   </div>
                            </div>
                        </section>
                    </div>

                    <aside class="lg:w-1/3 xl:w-[24rem] bg-card-bg p-6 border border-border-color rounded-xl shadow-lg lg:sticky lg:top-[calc(4rem+1.75rem)] h-fit space-y-6 animate-fade-in-up" style="animation-delay: 0.2s;">
                        <div class="busca-rapida">
                            <h4 class="text-base font-semibold text-dark-text mb-3">Busca Rápida</h4>
                            <div class="relative">
                                <input type="search" placeholder="Buscar podcasts..." class="w-full py-2 px-4 pr-10 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-blue text-sm border border-transparent focus:border-primary-blue-light placeholder-light-text">
                                <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-dark-text mb-4 border-b border-border-color pb-3">Episódios Relacionados</h3>
                            <?php if (!empty($episodios_relacionados)): ?>
                            <ul class="space-y-3.5">
                                <?php foreach ($episodios_relacionados as $ep): ?>
                                <li>
                                    <a href="<?php echo $ep['link']; ?>" <?php echo $ep['data_attributes']; ?> 
                                       data-visibilidade="<?php echo htmlspecialchars($ep['visibilidade']); ?>" 
                                       data-plano-minimo="<?php echo htmlspecialchars($ep['id_plano_minimo'] ?? ''); ?>"
                                       class="related-episode-link flex items-center gap-3.5 p-3 rounded-lg hover:bg-primary-blue-light group transition-all duration-150 ease-in-out">
                                        <img src="<?php echo $ep['imagem_capa_url']; ?>" alt="Capa de <?php echo $ep['titulo_podcast']; ?>" class="w-16 h-16 rounded-md object-cover flex-shrink-0 shadow-sm border border-border-color">
                                        <div class="flex-1 min-w-0">
                                            <span class="related-episode-title font-medium text-dark-text group-hover:text-primary-blue transition-colors text-sm leading-snug line-clamp-2"><?php echo $ep['titulo_podcast']; ?></span>
                                             <?php if ($ep['is_premium']): ?>
                                                <span class="text-xs font-semibold text-warning block mt-0.5">Premium</span>
                                            <?php endif; ?>
                                        </div>
                                        <i class="related-episode-play-icon fas fa-play-circle text-primary-blue text-xl ml-auto opacity-0 group-hover:opacity-100 transition-all duration-200 ease-in-out"></i>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else: ?>
                            <div class="text-center text-medium-text py-6">
                                <i class="fas fa-headphones-alt fa-2x text-slate-400 mb-2"></i>
                                <p class="text-sm">Nenhum episódio relacionado.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </aside>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <div class="toast" id="toast"></div>

    <div id="shareModal" class="modal-overlay fixed inset-0 flex items-center justify-center z-[100] hidden p-4">
        <div class="modal-dialog bg-white p-6 sm:p-8 rounded-lg shadow-xl text-center">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-dark-text">Compartilhar Podcast</h3>
                <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <p class="text-medium-text mb-1">Compartilhe "<span id="sharePodcastTitle" class="font-semibold"></span>"</p>
            <input type="text" id="shareLinkInput" readonly class="w-full p-2 border border-border-color rounded-md mb-3 text-sm bg-slate-100 focus:ring-primary-blue focus:border-primary-blue">
            <div class="flex flex-col sm:flex-row gap-3 mt-4">
                <button id="copyShareLink" class="btn-base btn-outline w-full text-sm !py-2">
                    <i class="fas fa-copy mr-2"></i>Copiar Link
                </button>
                <a id="whatsappShareLink" href="#" target="_blank" class="btn-base bg-green-500 hover:bg-green-600 text-white w-full text-sm !py-2 flex items-center justify-center">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </a>
            </div>
        </div>
    </div>
    
    <div id="upgradeModal" class="modal-overlay fixed inset-0 flex items-center justify-center z-[100] hidden p-4">
        <div class="modal-dialog bg-white p-6 sm:p-8 rounded-lg shadow-xl text-center" id="upgradeModalDialog">
            <i class="fas fa-crown text-4xl text-warning mb-4"></i>
            <h3 class="text-xl font-semibold text-primary-blue mb-3">Conteúdo Premium!</h3>
            <p class="text-gray-700 mb-2">O conteúdo "<span id="modalPodcastTitle" class="font-semibold"></span>" é exclusivo para assinantes.</p>
            <p class="text-gray-600 mb-6">Faça um upgrade no seu plano para ter acesso completo.</p>
            <div class="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-3">
                <a id="modalUpgradeLink" href="planos.php" class="btn-base btn-primary w-full sm:w-auto text-sm">Ver Planos</a>
                <button onclick="closeUpgradeModal()" class="btn-base btn-gray w-full sm:w-auto text-sm">Agora Não</button>
            </div>
        </div>
    </div>


<script src="https://cdn.plyr.io/3.7.8/plyr.polyfilled.js"></script>
<script>
    // As variáveis PHP globais são definidas no início do script tag para clareza
    const idPodcastGlobal = <?php echo json_encode($podcast['id_podcast'] ?? null); ?>;
    const podcastTitleGlobal = <?php echo json_encode($pageTitle ?? 'este podcast'); ?>;
    const podcastSlugGlobal = <?php echo json_encode($podcast['slug_podcast'] ?? ''); ?>;
    const podcastVisibilidadeGlobal = <?php echo json_encode($podcast['visibilidade'] ?? 'publico'); ?>;
    const usuarioIdGlobal = <?php echo json_encode($userId); ?>;
    const podeAcederGlobal = <?php echo json_encode($pode_aceder); ?>;
    const isUserAdminGlobal = <?php echo json_encode($is_admin); ?>;
    const userPlanoIdGlobal = <?php echo json_encode($userPlanoId); ?>;

    let player;
    const podcastPlayerElement = document.getElementById('podcastPlayer');
    const posicaoInicial = parseFloat(podcastPlayerElement?.dataset.posicaoInicial || 0);

    if (podeAcederGlobal && podcastPlayerElement) {
        try {
            player = new Plyr('#podcastPlayer', {
                tooltips: { controls: true, seek: true },
                hideControls: false,
                listeners: {
                    ready: event => {
                        if (posicaoInicial > 0 && event.detail.plyr.duration > 0 && posicaoInicial < event.detail.plyr.duration) {
                            event.detail.plyr.currentTime = posicaoInicial;
                        }
                    }
                }
            });

            if (player && posicaoInicial > 0 && player.source && !player.playing && player.duration === 0) {
                 // Fallback se a duração não estiver disponível no 'ready'
                player.once('canplay', () => {
                    if (player.duration > 0 && posicaoInicial < player.duration && player.currentTime !== posicaoInicial) {
                         player.currentTime = posicaoInicial;
                    }
                });
            } else if (player && posicaoInicial > 0 && player.source && !player.playing && player.duration > 0){
                 if (posicaoInicial < player.duration && player.currentTime !== posicaoInicial) {
                    player.currentTime = posicaoInicial;
                 }
            }

        } catch(e) {
            console.error("Plyr initialization error:", e);
            if(podcastPlayerElement.parentElement) podcastPlayerElement.parentElement.innerHTML = "<p class='text-center text-danger p-4 bg-red-100 rounded-md'>Erro ao carregar o player de áudio.</p>";
        }
    }

    // Restante do JavaScript (toast, comentários, interações, modais, etc.)
    let emEdicao = false; let editandoId = null; let respondendoId = null;
    let savePositionInterval; const SAVE_INTERVAL_MS = 15000; 

    const toastElement = document.getElementById('toast');
    const formComentario = document.getElementById('formComentarioPodcast');
    const comentarioTextarea = formComentario ? formComentario.querySelector('textarea[name="texto_comentario"]') : null;
    const comentarioPaiInput = formComentario ? formComentario.querySelector('input[name="id_comentario_pai"]') : null;
    const btnCancelarEdicao = document.getElementById('btnCancelarEdicao');
    const comentariosPodcastDiv = document.getElementById('comentariosPodcast');
    const noCommentsMessage = document.getElementById('noCommentsMessage');
    const comentariosCountDisplay = document.getElementById('comentariosCountDisplay');
    const comentariosTotalHeader = document.getElementById('comentariosTotalHeader');
    const commentSubmitButtonText = formComentario ? document.getElementById('commentSubmitButtonText') : null;
    const commentSpinner = formComentario ? document.getElementById('commentSpinner') : null;
    const replyingToContainer = document.getElementById('replyingToContainer');
    const replyingToName = document.getElementById('replyingToName');
    const cancelReplyBtn = document.getElementById('cancelReplyBtn');
    const speedControlsContainer = document.querySelector('.speed-controls');
    const ratingStarsContainer = document.querySelector('.rating-stars');
    const btnAdicionarFila = document.getElementById('btnAdicionarFila');
    const filaTexto = document.getElementById('filaTexto');
    const btnShare = document.getElementById('btnShare');
    
    // Modal de Partilha Elements
    const shareModal = document.getElementById('shareModal');
    const sharePodcastTitleSpan = document.getElementById('sharePodcastTitle');
    const shareLinkInput = document.getElementById('shareLinkInput');
    const copyShareLinkButton = document.getElementById('copyShareLink');
    const whatsappShareLinkAnchor = document.getElementById('whatsappShareLink');

    // Modal de Upgrade Elements (para episódios relacionados)
    const upgradeModal = document.getElementById('upgradeModal');
    const upgradeModalDialog = document.getElementById('upgradeModalDialog');
    const modalPodcastTitleSpan = document.getElementById('modalPodcastTitle'); // No modal de upgrade
    const modalUpgradeLinkAnchor = document.getElementById('modalUpgradeLink'); // No modal de upgrade


    function showToast(message, type = 'info') { 
        if (!toastElement) return;
        toastElement.textContent = message;
        toastElement.className = 'toast show'; 
        if (type === 'success') toastElement.classList.add('toast-success');
        else if (type === 'error') toastElement.classList.add('toast-error');
        else if (type === 'warning') toastElement.classList.add('toast-warning');
        else toastElement.classList.add('toast-info'); 
        
        setTimeout(() => { 
            toastElement.classList.remove('show');
            toastElement.classList.add('hide'); 
            setTimeout(() => { toastElement.classList.remove('hide'); toastElement.className = 'toast'; }, 500); 
        }, 3500);
    }

    function setFormLoading(isLoading) {
        if (!formComentario) return;
        const submitButton = formComentario.querySelector('button[type="submit"]');
        if (isLoading) {
            if(commentSubmitButtonText) commentSubmitButtonText.textContent = emEdicao ? 'Salvando...' : 'Enviando...';
            if(commentSpinner) commentSpinner.classList.remove('hidden');
            if(comentarioTextarea) comentarioTextarea.disabled = true;
            if(submitButton) submitButton.disabled = true;
        } else {
            if(commentSubmitButtonText) commentSubmitButtonText.textContent = emEdicao ? 'Salvar Alterações' : 'Comentar';
            if(commentSpinner) commentSpinner.classList.add('hidden');
            if(comentarioTextarea) comentarioTextarea.disabled = false;
            if(submitButton) submitButton.disabled = false;
        }
    }
    function formatDateTime(dateString) {
        if (!dateString) return 'Data inválida';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Data inválida';
        const options = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false };
        try { 
            let formatted = date.toLocaleDateString('pt-BR', options).replace('de ', '').replace('.', '');
            formatted = formatted.replace(' às ', ' ');
            return formatted;
        } 
        catch (e) { return date.toLocaleString(undefined, options); } 
    }

    function renderComentarios() {
        if (!idPodcastGlobal || !comentariosPodcastDiv) return;
        const params = new URLSearchParams({ acao: 'listar_comentarios', id_podcast: idPodcastGlobal });
        fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                comentariosPodcastDiv.innerHTML = ''; 
                let totalComentarios = 0;
                if (data.comentarios) {
                     data.comentarios.forEach(comment => {
                        totalComentarios++;
                        if (comment.respostas) { 
                            comment.respostas.forEach(reply => totalComentarios++); 
                        }
                    });
                }
                if(comentariosCountDisplay) comentariosCountDisplay.textContent = totalComentarios;
                if(comentariosTotalHeader) comentariosTotalHeader.textContent = `${totalComentarios}`;
                
                if (data.comentarios && data.comentarios.length > 0) {
                    if(noCommentsMessage) noCommentsMessage.classList.add('hidden');
                    data.comentarios.forEach(comment => {
                        const commentElement = createCommentElement(comment, 0);
                        comentariosPodcastDiv.appendChild(commentElement);
                    });
                } else {
                    if(noCommentsMessage) noCommentsMessage.classList.remove('hidden');
                }
            } else {
                showToast(data.message || 'Erro ao carregar comentários.', 'error');
                if(noCommentsMessage) { noCommentsMessage.classList.remove('hidden'); noCommentsMessage.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x text-yellow-400 mb-2"></i><p class="font-medium text-sm">Não foi possível carregar os comentários.</p>';}
            }
        })
        .catch(error => {
            console.error('Error fetching comments:', error);
            showToast('Erro de rede ao carregar comentários.', 'error');
            if(noCommentsMessage) { noCommentsMessage.classList.remove('hidden'); noCommentsMessage.innerHTML = '<i class="fas fa-wifi fa-2x text-red-400 mb-2"></i><p class="font-medium text-sm">Erro de conexão ao buscar comentários.</p>';}
        });
    }

    function createCommentElement(comment, recursionLevel, isNew = false) {
        const div = document.createElement('div');
        div.className = `comment-card p-4 rounded-lg shadow-sm ${recursionLevel > 0 ? 'reply-indent mt-4' : 'mt-0'}`;
        div.classList.add('animate-fade-in-up'); // Adicionar animação
        div.style.animationDelay = `${recursionLevel * 0.05 + (isNew ? 0 : 0.1)}s`; // Delay para animação
        if (recursionLevel > 0) { div.classList.add('bg-slate-50', 'border-slate-200'); } 
        else { div.classList.add('bg-white', 'border-border-color'); }
        div.dataset.commentId = comment.id_comentario;
        if (isNew) { div.classList.add('new-comment-highlight'); setTimeout(() => div.classList.remove('new-comment-highlight'), 2500); }
        
        const sanitizedText = comment.texto_comentario.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        const displayText = sanitizedText.replace(/\n/g, '<br>');
        
        let userActions = '';
        if (usuarioIdGlobal && usuarioIdGlobal === parseInt(comment.id_utilizador)) {
            userActions = `
                <button title="Editar Comentário" onclick="iniciarEdicao(${comment.id_comentario}, \`${comment.texto_comentario.replace(/'/g, "\\'").replace(/\n/g, "\\n")}\`)" class="btn-icon text-blue-500 hover:text-blue-700"> <i class="fas fa-edit"></i> </button>
                <button title="Apagar Comentário" onclick="apagarComentario(${comment.id_comentario})" class="btn-icon text-red-500 hover:text-red-700"> <i class="fas fa-trash-alt"></i> </button>`;
        }
        
        div.innerHTML = `
            <div class="flex items-start space-x-3.5">
                <img src="${comment.avatar_url_processed || get_user_avatar_placeholder(comment.nome_completo, null, 36)}" alt="${comment.nome_completo}" class="w-9 h-9 rounded-full border-2 border-primary-blue-light shadow-sm flex-shrink-0">
                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-1.5">
                        <div>
                            <span class="font-semibold text-dark-text text-sm">${comment.nome_completo}</span>
                            <span class="text-xs text-light-text ml-0 sm:ml-2 block sm:inline">${formatDateTime(comment.data_comentario)}</span>
                            ${comment.editado == 1 ? '<span class="text-xs italic text-light-text ml-0 sm:ml-2 block sm:inline">(editado)</span>' : ''}
                        </div>
                        <div class="flex items-center space-x-1 mt-1 sm:mt-0">
                            ${userActions}
                            ${(usuarioIdGlobal && podeAcederGlobal) ? `<button title="Responder" onclick="prepararResposta(${comment.id_comentario}, '${comment.nome_completo.replace(/'/g, "\\'")}')" class="btn-icon text-green-500 hover:text-green-600"> <i class="fas fa-reply"></i> </button>` : ''}
                        </div>
                    </div>
                    <div class="text-medium-text text-sm leading-relaxed prose prose-sm max-w-none">${displayText}</div>
                </div>
            </div>`;
        
        if (comment.respostas && comment.respostas.length > 0) {
            const repliesContainer = document.createElement('div');
            repliesContainer.className = 'mt-4 space-y-4'; 
            comment.respostas.forEach(reply => { repliesContainer.appendChild(createCommentElement(reply, recursionLevel + 1)); });
            div.appendChild(repliesContainer);
        }
        return div;
    }
    
    if (formComentario) { 
        formComentario.onsubmit = function(e) { 
            e.preventDefault();
            if (!usuarioIdGlobal) { showToast('Você precisa estar logado para comentar.', 'warning'); return; }
            if (!podeAcederGlobal) { showToast('Você não pode comentar neste podcast.', 'warning'); return; }
            const texto = comentarioTextarea.value.trim();
            if (!texto) { showToast('Por favor, escreva algo no seu comentário.', 'warning'); comentarioTextarea.focus(); return; }
            
            setFormLoading(true);
            const acao = emEdicao ? 'editar' : 'comentar';
            const params = new URLSearchParams({ acao: acao, id_podcast: idPodcastGlobal, texto_comentario: texto, });
            if (emEdicao && editandoId) { params.append('id_comentario', editandoId); } 
            else if (respondendoId) { params.append('id_comentario_pai', respondendoId); } 
            else { params.append('id_comentario_pai', comentarioPaiInput.value || '0'); }
            
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(emEdicao ? 'Comentário atualizado com sucesso!' : 'Comentário enviado!', 'success');
                    formComentario.reset();
                    if(comentarioPaiInput) comentarioPaiInput.value = '';
                    cancelarModoResposta();
                    if (emEdicao) finalizarEdicao();
                    renderComentarios(); 
                    const targetId = data.id_comentario || editandoId;
                    if (targetId) {
                        setTimeout(() => {
                            const targetEl = document.querySelector(`.comment-card[data-comment-id="${targetId}"]`);
                            if (targetEl) {
                                targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                targetEl.classList.add('new-comment-highlight');
                                setTimeout(() => targetEl.classList.remove('new-comment-highlight'), 2500);
                            }
                        }, 300); 
                    }
                } else { showToast(data.message || 'Ocorreu um erro ao processar seu comentário.', 'error'); }
            })
            .catch(error => { console.error('Error submitting comment:', error); showToast('Erro de rede ao enviar comentário.', 'error'); })
            .finally(() => { setFormLoading(false); });
        };
    }

    function prepararResposta(idComentarioPai, nomeUsuario) { 
        if (!usuarioIdGlobal || !formComentario || !podeAcederGlobal) { showToast('Você precisa estar logado e ter acesso para responder.', 'warning'); return; }
        respondendoId = idComentarioPai;
        if(comentarioPaiInput) comentarioPaiInput.value = idComentarioPai;
        if(replyingToName) replyingToName.textContent = nomeUsuario;
        if(replyingToContainer) replyingToContainer.classList.remove('hidden');
        if(comentarioTextarea) { comentarioTextarea.value = ''; comentarioTextarea.placeholder = `Respondendo a ${nomeUsuario}...`; comentarioTextarea.focus(); }
        if(formComentario) formComentario.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (emEdicao) finalizarEdicao(false); 
    }

    if (cancelReplyBtn) { cancelReplyBtn.onclick = function() { cancelarModoResposta(); }; }
    function cancelarModoResposta() { 
        respondendoId = null;
        if (comentarioPaiInput) comentarioPaiInput.value = '';
        if (replyingToContainer) replyingToContainer.classList.add('hidden');
        if (replyingToName) replyingToName.textContent = '';
        if (comentarioTextarea) comentarioTextarea.placeholder = 'Partilhe a sua opinião sobre este episódio...';
        if (!emEdicao && formComentario) formComentario.reset(); 
    }

    function iniciarEdicao(id, textoAtual) { 
        if (!usuarioIdGlobal || !formComentario || !podeAcederGlobal) return;
        emEdicao = true; editandoId = id;
        if(comentarioTextarea) comentarioTextarea.value = textoAtual.replace(/\\n/g, '\n'); // Assegurar que quebras de linha sejam texto
        if(btnCancelarEdicao) btnCancelarEdicao.classList.remove('hidden');
        if(commentSubmitButtonText) commentSubmitButtonText.textContent = 'Salvar Alterações';
        if(comentarioTextarea) comentarioTextarea.focus();
        showToast('Modo de edição ativado.', 'info');
        if(formComentario) formComentario.scrollIntoView({ behavior: 'smooth', block: 'center' });
        cancelarModoResposta(); 
    }
    function finalizarEdicao(resetForm = true) { 
        emEdicao = false; editandoId = null;
        if(btnCancelarEdicao) btnCancelarEdicao.classList.add('hidden');
        if(commentSubmitButtonText) commentSubmitButtonText.textContent = 'Comentar';
        if(resetForm && formComentario) formComentario.reset();
        cancelarModoResposta(); 
    }
    if (btnCancelarEdicao) { btnCancelarEdicao.onclick = function() { finalizarEdicao(); }; }

    function apagarComentario(idComentario) { 
        if (!usuarioIdGlobal || !podeAcederGlobal) return;
        if (!confirm('Tem certeza que deseja apagar este comentário? Esta ação não pode ser desfeita.')) return;
        
        const params = new URLSearchParams({ acao: 'apagar', id_podcast: idPodcastGlobal, id_comentario: idComentario });
        const commentElement = document.querySelector(`.comment-card[data-comment-id="${idComentario}"]`);
        if (commentElement) { commentElement.style.opacity = '0.5'; commentElement.style.pointerEvents = 'none'; }
        
        fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Comentário apagado com sucesso!', 'success');
                if(commentElement) { 
                    commentElement.style.transition = 'opacity 0.3s ease, transform 0.3s ease, max-height 0.5s ease';
                    commentElement.style.transform = 'scale(0.95)'; 
                    commentElement.style.opacity = '0'; 
                    commentElement.style.maxHeight = '0px';
                    commentElement.style.paddingTop = '0px';
                    commentElement.style.paddingBottom = '0px';
                    commentElement.style.marginTop = '0px';
                    commentElement.style.marginBottom = '0px';
                    commentElement.style.borderWidth = '0px';
                    setTimeout(() => { 
                        commentElement.remove(); 
                        renderComentarios();
                    }, 500); 
                } 
                else { renderComentarios(); }
            } else { showToast(data.message || 'Não foi possível apagar o comentário.', 'error'); if (commentElement) {commentElement.style.opacity = '1'; commentElement.style.pointerEvents = 'auto';} }
        })
        .catch(error => { console.error('Error deleting comment:', error); showToast('Erro de rede ao apagar comentário.', 'error'); if (commentElement) {commentElement.style.opacity = '1'; commentElement.style.pointerEvents = 'auto';}});
    }

    const btnCurtirPodcast = document.getElementById('btnCurtirPodcast');
    if (btnCurtirPodcast) {
        btnCurtirPodcast.onclick = function() {
            if (!usuarioIdGlobal) { showToast('Você precisa estar logado para curtir.', 'warning'); return; }
            if (!podeAcederGlobal) { showToast('Você não pode curtir este podcast.', 'warning'); return; }
            const params = new URLSearchParams({ acao: 'curtir', id_podcast: idPodcastGlobal });
            this.disabled = true;
            const curtirTextoSpan = document.getElementById('curtirTexto');
            const originalText = curtirTextoSpan ? curtirTextoSpan.textContent : 'Curtir';
            const curtirIcon = this.querySelector('.like-icon-path');
            
            fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const curtidasTotalSpan = document.getElementById('curtidasTotal');
                    this.classList.toggle('btn-primary', data.liked); 
                    this.classList.toggle('liked-animation', data.liked);
                    this.classList.toggle('btn-gray', !data.liked); 
                    
                    if(curtirIcon){ curtirIcon.classList.toggle('text-white', data.liked); curtirIcon.classList.toggle('text-slate-400', !data.liked); curtirIcon.classList.toggle('group-hover:text-primary-blue', !data.liked); }
                    if(curtirTextoSpan) curtirTextoSpan.textContent = data.liked ? 'Curtido!' : 'Curtir';
                    if(curtidasTotalSpan) curtidasTotalSpan.textContent = `${data.total}`;
                    showToast(data.liked ? 'Podcast curtido com sucesso!' : 'Curtida removida.', data.liked ? 'success' : 'info');
                } else { showToast(data.message || 'Erro ao processar curtida.', 'error'); if(curtirTextoSpan) curtirTextoSpan.textContent = originalText; }
            })
            .catch(error => { console.error('Error liking podcast:', error); showToast('Erro de rede ao curtir.', 'error'); if(curtirTextoSpan) curtirTextoSpan.textContent = originalText; })
            .finally(() => { this.disabled = false; });
        };
    }
    
    // Lógica de Partilha
    function openShareModal() {
        if (!shareModal || !sharePodcastTitleSpan || !shareLinkInput || !copyShareLinkButton || !whatsappShareLinkAnchor) {
            console.error("Elementos do modal de partilha não encontrados.");
            return;
        }

        sharePodcastTitleSpan.textContent = podcastTitleGlobal;
        let shareUrl = '';
        // Assume que a URL base termina com '/'
        const baseUrl = window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);

        if (podcastVisibilidadeGlobal === 'restrito_assinantes') {
            shareUrl = `${baseUrl}player_gratuito.php?slug=${encodeURIComponent(podcastSlugGlobal)}`;
        } else { 
            shareUrl = `${baseUrl}player_podcast.php?slug=${encodeURIComponent(podcastSlugGlobal)}`;
        }
        shareLinkInput.value = shareUrl;

        const marketingMessage = `🎧 Confira este episódio incrível na AudioTO: "${podcastTitleGlobal}"! Ideal para profissionais e estudantes da área da saúde. Ouça agora:`;
        const whatsappUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(marketingMessage + ' ' + shareUrl)}`;
        whatsappShareLinkAnchor.href = whatsappUrl;

        shareModal.classList.remove('hidden');
        // A animação é controlada por CSS na classe .share-modal-overlay:not(.hidden) .share-modal-dialog
        document.body.style.overflow = 'hidden';
    }

    function closeShareModal() {
        if (!shareModal) return;
        shareModal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    if (btnShare) {
        btnShare.onclick = openShareModal;
    }
    if(shareModal) {
        shareModal.addEventListener('click', function(event) {
            if (event.target === shareModal) { 
                closeShareModal();
            }
        });
    }

    if (copyShareLinkButton) {
        copyShareLinkButton.onclick = function() {
            if (!shareLinkInput) return;
            shareLinkInput.select();
            shareLinkInput.setSelectionRange(0, 99999); 

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shareLinkInput.value)
                    .then(() => { showToast('Link copiado para a área de transferência!', 'success'); })
                    .catch(err => { showToast('Não foi possível copiar o link.', 'error'); console.error('Falha ao copiar (clipboard API): ', err); });
                } else { // Fallback para document.execCommand
                    const successful = document.execCommand('copy');
                    if (successful) {
                        showToast('Link copiado para a área de transferência!', 'success');
                    } else {
                        showToast('Não foi possível copiar o link automaticamente. Por favor, copie manualmente.', 'warning');
                    }
                }
            } catch (err) {
                showToast('Falha ao copiar o link.', 'error');
                console.error('Falha ao copiar: ', err);
            }
            // closeShareModal(); // Opcional: fechar modal após copiar
        };
    }
    
    if (player && speedControlsContainer && podeAcederGlobal) {
        const speedButtons = speedControlsContainer.querySelectorAll('button[data-speed]');
        speedButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (player) {
                    const speed = parseFloat(this.dataset.speed);
                    player.speed = speed;
                    speedButtons.forEach(btn => {
                        btn.classList.remove('active-speed', 'bg-primary-blue', 'text-white', 'font-semibold');
                        // btn.classList.add('border-primary-blue-light', 'text-primary-blue'); // Já é o default
                    });
                    this.classList.add('active-speed', 'bg-primary-blue', 'text-white', 'font-semibold');
                    // this.classList.remove('border-primary-blue-light', 'text-primary-blue');
                }
            });
        });
    }

    if (ratingStarsContainer && usuarioIdGlobal && podeAcederGlobal) { 
        const stars = ratingStarsContainer.querySelectorAll('.fa-star');
        const mediaAvaliacaoSpan = document.getElementById('mediaAvaliacao');
        const totalAvaliacoesSpan = document.getElementById('totalAvaliacoes');

        function setRatingVisual(ratingValue, permanent = false) {
            stars.forEach(star => {
                const starValue = parseInt(star.dataset.value);
                star.classList.toggle('rated', starValue <= ratingValue);
                star.classList.toggle('text-yellow-400', starValue <= ratingValue);
                star.classList.toggle('text-border-color-strong', starValue > ratingValue);
                if (permanent) {
                    star.classList.remove('hovered'); 
                }
            });
        }
        
        stars.forEach(star => {
            star.addEventListener('mouseover', function() {
                if (!podeAcederGlobal || !usuarioIdGlobal) return;
                const hoverValue = parseInt(this.dataset.value);
                stars.forEach((s, index) => {
                    s.classList.toggle('hovered', index < hoverValue);
                    s.classList.toggle('text-yellow-500', index < hoverValue); 
                    if (index >= hoverValue && !s.classList.contains('rated')) {
                         s.classList.remove('text-yellow-500');
                         s.classList.add('text-border-color-strong');
                    }
                });
            });

            star.addEventListener('mouseout', function() {
                 if (!podeAcederGlobal || !usuarioIdGlobal) return;
                stars.forEach(s => s.classList.remove('hovered', 'text-yellow-500'));
                setRatingVisual(parseInt(ratingStarsContainer.dataset.userRating || 0)); 
            });

            star.addEventListener('click', function() {
                if (!podeAcederGlobal || !usuarioIdGlobal) {
                    showToast('Você precisa estar logado e ter acesso para avaliar.', 'warning');
                    return;
                }
                const nota = parseInt(this.dataset.value);
                ratingStarsContainer.dataset.userRating = nota; 
                setRatingVisual(nota, true); 

                const params = new URLSearchParams({
                    acao: 'avaliar_podcast',
                    id_podcast: idPodcastGlobal,
                    nota: nota
                });
                fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        if (data.novaMedia !== undefined && mediaAvaliacaoSpan) mediaAvaliacaoSpan.textContent = parseFloat(data.novaMedia).toFixed(1);
                        if (data.novoTotal !== undefined && totalAvaliacoesSpan) totalAvaliacoesSpan.textContent = data.novoTotal;
                        ratingStarsContainer.dataset.previousRating = nota;
                    } else {
                        showToast(data.message || 'Erro ao enviar avaliação.', 'error');
                        const previousRating = parseInt(ratingStarsContainer.dataset.previousRating || 0);
                        ratingStarsContainer.dataset.userRating = previousRating; 
                        setRatingVisual(previousRating, true); 
                    }
                })
                .catch(error => {
                    console.error("Erro ao avaliar:", error);
                    showToast('Erro de rede ao avaliar.', 'error');
                    const previousRating = parseInt(ratingStarsContainer.dataset.previousRating || 0);
                    ratingStarsContainer.dataset.userRating = previousRating; 
                    setRatingVisual(previousRating, true);
                });
            });
        });
        const initialUserRating = <?php echo $avaliacao_utilizador; ?>;
        ratingStarsContainer.dataset.userRating = initialUserRating;
        ratingStarsContainer.dataset.previousRating = initialUserRating;
        setRatingVisual(initialUserRating, true);
    } else if (ratingStarsContainer) { 
         ratingStarsContainer.style.opacity = '0.6';
         ratingStarsContainer.style.pointerEvents = 'none';
    }


    
    
    if (podeAcederGlobal && player) {
        const saveCurrentPositionRegular = () => {
            if (player && player.currentTime > 0 && idPodcastGlobal && usuarioIdGlobal) {
                const params = new URLSearchParams({
                    acao: 'salvar_posicao',
                    id_podcast: idPodcastGlobal,
                    tempo_atual: player.currentTime
                });
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params,
                    keepalive: true 
                })
                .then(response => response.json())
                .then(data => { /* console.log('Posição guardada (intervalo/evento):', data); */ })
                .catch(error => console.error('Erro ao guardar posição (intervalo/evento):', error));
            }
        };

        savePositionInterval = setInterval(saveCurrentPositionRegular, SAVE_INTERVAL_MS);
        player.on('pause', saveCurrentPositionRegular);
        player.on('seeked', saveCurrentPositionRegular);

        const handlePageExit = () => {
            if (player && player.currentTime > 0 && idPodcastGlobal && usuarioIdGlobal && podeAcederGlobal) {
                clearInterval(savePositionInterval); 
                const formData = new FormData();
                formData.append('acao', 'salvar_posicao');
                formData.append('id_podcast', idPodcastGlobal);
                formData.append('tempo_atual', player.currentTime);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(window.location.pathname, formData);
                } else { 
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', window.location.pathname, false); 
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send(new URLSearchParams(formData).toString());
                }
            }
        };
        window.addEventListener('unload', handlePageExit);
        window.addEventListener('pagehide', handlePageExit); // For mobile browser backgrounding
    }
    
    if (podeAcederGlobal && comentariosPodcastDiv) { // Só renderiza se puder aceder e o div existir
        renderComentarios();
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('sidebar'); 
        const overlay = document.getElementById('sidebar-overlay');
        if (sidebar) {
            sidebar.classList.toggle('left-[-256px]'); // Usando a classe da inicio.php
            sidebar.classList.toggle('left-0');
            sidebar.classList.toggle('open'); // Manter open para lógica JS, se houver
        }
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
         const mobileMenuButton = document.getElementById('mobileMenuButton');
         if (mobileMenuButton) {
            const isSidebarOpen = sidebar ? sidebar.classList.contains('left-0') : false;
            mobileMenuButton.setAttribute('aria-expanded', isSidebarOpen.toString());
         }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const userDropdownButton = document.getElementById('userDropdownButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        if (userDropdownButton && userDropdownMenu) {
            userDropdownButton.addEventListener('click', function (event) {
                event.stopPropagation();
                const isExpanded = userDropdownMenu.classList.toggle('hidden');
                userDropdownButton.setAttribute('aria-expanded', (!isExpanded).toString());
                if (!isExpanded) { // Se não estava escondido (agora está visível)
                    userDropdownMenu.style.animation = 'fadeInUp 0.3s ease-out forwards';
                }
            });
            document.addEventListener('click', function (event) {
                if (userDropdownMenu && !userDropdownMenu.classList.contains('hidden') && 
                    userDropdownButton && !userDropdownButton.contains(event.target) && 
                    !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.add('hidden');
                    userDropdownButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
        const initialActiveSpeedButton = speedControlsContainer?.querySelector('button.active-speed');
        if(initialActiveSpeedButton){
            initialActiveSpeedButton.classList.add('bg-primary-blue', 'text-white', 'font-semibold');
            initialActiveSpeedButton.classList.remove('border-primary-blue-light', 'text-primary-blue');
        }

        // Lógica para Modal de Upgrade para episódios relacionados
        // A constante isUserSubscriberOrAdminJS já foi definida com dados do PHP
        document.querySelectorAll('a.related-episode-link[data-premium="true"][data-requires-upgrade="true"]').forEach(link => {
            link.addEventListener('click', function(event) {
                const isPremium = this.dataset.premium === 'true';
                const visibilidadeEp = this.dataset.visibilidade;
                const planoMinimoEp = this.dataset.planoMinimo ? parseInt(this.dataset.planoMinimo) : null;
                
                let podeAcederEpRelacionado = false;
                if (visibilidadeEp === 'publico') {
                    podeAcederEpRelacionado = true;
                } else if (visibilidadeEp === 'restrito_assinantes') {
                    if (isUserAdminGlobal) {
                        podeAcederEpRelacionado = true;
                    } else if (usuarioIdGlobal && userPlanoIdGlobal > 0) {
                        if (planoMinimoEp === null || userPlanoIdGlobal >= planoMinimoEp) {
                            podeAcederEpRelacionado = true;
                        }
                    }
                }

                if (isPremium && !podeAcederEpRelacionado) {
                    event.preventDefault();
                    const relatedPodcastTitle = this.dataset.title || 'este episódio';
                    const relatedSlug = this.href.split('slug=')[1] ? this.href.split('slug=')[1].split('&')[0] : '';
                    showUpgradeModal(relatedPodcastTitle, `planos.php?from_content=${relatedSlug ? encodeURIComponent(relatedSlug) : ''}&type=podcast&reason=related_promo`);
                }
            });
        });

        // Adicionar listener ao modal de upgrade (se ele for injetado dinamicamente)
        const upgradeModalEl = document.getElementById('upgradeModal');
        if (upgradeModalEl) {
            upgradeModalEl.addEventListener('click', function(event) {
                if (event.target === upgradeModalEl) {
                    closeUpgradeModal();
                }
            });
        }
    });

     function showUpgradeModal(podcastTitle, upgradePageUrlWithSlug) {
        const modal = document.getElementById('upgradeModal');
        const modalDialog = document.getElementById('upgradeModalDialog'); // Certifique-se que o ID do dialog é este
        const modalPodcastTitleSpan = document.getElementById('modalPodcastTitle');
        const modalUpgradeLinkAnchor = document.getElementById('modalUpgradeLink');

        if (!modal || !modalDialog || !modalPodcastTitleSpan || !modalUpgradeLinkAnchor) {
            console.warn("Elementos do modal de upgrade não encontrados. A redirecionar diretamente.");
            window.location.href = upgradePageUrlWithSlug || 'planos.php';
            return;
        }

        modalPodcastTitleSpan.textContent = podcastTitle;
        modalUpgradeLinkAnchor.href = upgradePageUrlWithSlug || 'planos.php';
        
        modal.classList.remove('hidden');
        modal.style.opacity = '1'; // Forçar visibilidade para animação
        modalDialog.classList.remove('animate-modal-scale-out');
        modalDialog.classList.add('animate-modal-scale-in');
        document.body.style.overflow = 'hidden';
    }

    function closeUpgradeModal() {
        const modal = document.getElementById('upgradeModal');
        const modalDialog = document.getElementById('upgradeModalDialog');
        if (!modal || !modalDialog) return;

        modalDialog.classList.remove('animate-modal-scale-in');
        modalDialog.classList.add('animate-modal-scale-out');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.style.opacity = '0';
            document.body.style.overflow = '';
        }, 300); 
    }

</script>
</body>
</html>