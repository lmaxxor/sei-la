<?php
require_once __DIR__ . '/sessao/session_handler.php';
requireLogin('login.php');
require_once __DIR__ . '/sessao/csrf.php';
require_once __DIR__ . '/db/db_connect.php';

$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$postId) {
    header('Location: comunidade.php');
    exit;
}

$csrfToken = getCsrfToken();

try {
    $stmt = $pdo->prepare('SELECT p.*, u.nome_completo, u.avatar_url,
        IF(c.id_like IS NULL,0,1) AS user_liked
        FROM comunidade_posts p
        JOIN utilizadores u ON p.id_utilizador = u.id_utilizador
        LEFT JOIN comunidade_curtidas c ON c.id_post = p.id_post AND c.id_utilizador = :uid
        WHERE p.id_post = :id');
    $stmt->bindParam(':id', $postId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $_SESSION["user_id"], PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        header('Location: comunidade.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT c.*, u.nome_completo, u.avatar_url FROM comunidade_comentarios c JOIN utilizadores u ON c.id_utilizador = u.id_utilizador WHERE c.id_post = :id ORDER BY c.data_criacao ASC');
    $stmt->bindParam(':id', $postId, PDO::PARAM_INT);
    $stmt->execute();
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Erro ao carregar post: ' . $e->getMessage());
    $post = null; $comentarios = [];
}

function avatar($name, $url, $size=40) {
    if ($url && filter_var($url, FILTER_VALIDATE_URL)) return $url;
    $enc = urlencode($name);
    return "https://ui-avatars.com/api/?name={$enc}&background=2563eb&color=fff&size={$size}&rounded=true&bold=true";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?php echo $csrfToken; ?>">
<title>Publicação - AudioTO</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
</head>
<body class="bg-gray-50 p-6">
<a href="comunidade.php" class="text-blue-600 hover:underline">&larr; Voltar</a>
<?php if ($post): ?>
    <article class="bg-white p-6 rounded shadow mt-4">
        <h1 class="text-2xl font-bold mb-2"><?php echo htmlspecialchars($post['titulo']); ?></h1>
        <p class="text-sm text-gray-500 mb-4">por <?php echo htmlspecialchars($post['nome_completo']); ?> em <?php echo date('d/m/Y H:i', strtotime($post['data_criacao'])); ?></p>
        <div class="prose max-w-none mb-4"><?php echo nl2br(htmlspecialchars($post['texto'])); ?></div>
        <button class="curtir-post-button mt-2 flex items-center text-sm text-gray-600" data-post-id="<?php echo $postId; ?>">
            <i class="far fa-thumbs-up mr-1 <?php if($post['user_liked']) echo 'text-blue-600'; ?>"></i>
            <span class="likes-count mr-1"><?php echo (int)$post['total_curtidas']; ?></span>Curtidas
        </button>
    </article>
    <section class="mt-6">
        <h2 class="text-xl font-semibold mb-4">Comentários (<?php echo count($comentarios); ?>)</h2>
        <?php foreach ($comentarios as $c): ?>
            <div class="bg-white p-4 rounded shadow mb-3">
                <div class="flex items-center mb-2">
                    <img src="<?php echo avatar($c['nome_completo'], $c['avatar_url'],32); ?>" class="w-8 h-8 rounded-full mr-2" alt="avatar">
                    <span class="font-semibold mr-2"><?php echo htmlspecialchars($c['nome_completo']); ?></span>
                    <span class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($c['data_criacao'])); ?></span>
                    <?php if ($c['id_utilizador'] == $_SESSION['user_id'] || isAdmin()): ?>
                        <form method="post" action="comunidade_apagar_comentario.php" class="ml-auto">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="comment_id" value="<?php echo $c['id_comentario']; ?>">
                            <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                            <button type="submit" class="text-red-600 text-sm">Apagar</button>
                        </form>
                    <?php endif; ?>
                </div>
                <p><?php echo nl2br(htmlspecialchars($c['texto'])); ?></p>
            </div>
        <?php endforeach; ?>
        <form method="post" action="comunidade_comentar.php" class="mt-4 bg-white p-4 rounded shadow">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
            <textarea name="texto" rows="3" class="w-full border rounded p-2" required placeholder="Escreva um comentário..."></textarea>
            <button type="submit" class="mt-2 bg-blue-600 text-white px-4 py-2 rounded">Comentar</button>
        </form>
    </section>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const btn = document.querySelector('.curtir-post-button');
  if(btn){
    btn.addEventListener('click', function(){
        const id = this.dataset.postId;
        const params = new URLSearchParams({ id_post: id });
        fetch('comunidade_curtir.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
        .then(r=>r.json()).then(data=>{
            if(data.success){
               const icon = this.querySelector('i');
               const count = this.querySelector('.likes-count');
               if(icon) icon.classList.toggle('text-blue-600', data.liked);
               if(count) count.textContent = data.total;
            }
        });
    });
  }
});
</script>
</body>
</html>
