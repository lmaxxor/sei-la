<?php
// admin/processa_adicionar_podcast_em_massa.php

require_once __DIR__ . '/../sessao/session_handler.php';
requireAdmin('../logout.php');
require_once __DIR__ . '/../db/db_connect.php';

// Função para converter WAV em M4A usando FFmpeg
function converterWavParaM4a($wavPath, $m4aPath) {
    $cmd = "ffmpeg -y -i " . escapeshellarg($wavPath) . " -c:a aac -b:a 128k " . escapeshellarg($m4aPath) . " 2>&1";
    exec($cmd, $output, $return_var);
    return ($return_var === 0 && file_exists($m4aPath));
}

// Descrição automática se o assunto for "Papel do TO na Neonatal"
function descricaoPadraoNeonatal() {
    return "O terapeuta ocupacional na neonatologia atua de forma fundamental na Unidade de Terapia Intensiva Neonatal (UTIN) e nas enfermarias, promovendo o desenvolvimento neuropsicomotor, a prevenção de complicações e a humanização do cuidado ao recém-nascido. O profissional avalia e intervém nas áreas sensório-motora, alimentação, posicionamento, autorregulação e vinculação família-bebê, sempre respeitando as particularidades do período neonatal. Seu trabalho visa potencializar a qualidade de vida do bebê e sua família, favorecer a alta hospitalar precoce e apoiar a equipe multidisciplinar na construção de um ambiente adequado ao desenvolvimento dos pequenos pacientes.";
}

// Diretório de upload
$uploadDir = __DIR__ . '/../uploads/podcasts/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// Campos do formulário
$id_categoria         = $_POST['id_categoria'] ?? null;
$id_assunto           = $_POST['id_assunto'] ?? null;
$visibilidade         = $_POST['visibilidade'] ?? 'publico';
$id_plano_minimo      = $_POST['id_plano_minimo'] ?? null;
$descricao_podcast    = $_POST['descricao_podcast'] ?? '';
$tipo_material_apoio  = $_POST['tipo_material_apoio'] ?? 'nenhum';
$link_material_apoio_url = $_POST['link_material_apoio_url'] ?? '';
$usuario_id           = $_SESSION['user_id'] ?? 1;

// Upload múltiplo
$audios = $_FILES['audios'] ?? null;
$pdfs   = $_FILES['pdf_files'] ?? null; // pode ser null se não foi enviado

$feedbacks = [];

// Checagem se arquivos vieram
if (!$audios || !is_array($audios['name']) || count(array_filter($audios['name'])) === 0) {
    die("<h2 style='color:red;'>Nenhum arquivo de áudio enviado.</h2>");
}

// Carrega nome do assunto do banco para regra da descrição automática
$nome_assunto = null;
if ($id_assunto) {
    $stmt = $pdo->prepare("SELECT nome_assunto FROM assuntos_podcast WHERE id_assunto = ?");
    $stmt->execute([$id_assunto]);
    $nome_assunto = strtolower(trim($stmt->fetchColumn()));
}

foreach ($audios['name'] as $i => $audioName) {
    if (!$audioName) continue;
    $ext = strtolower(pathinfo($audioName, PATHINFO_EXTENSION));
    $novoNome = uniqid('audio_') . '.' . $ext;
    $destino = $uploadDir . $novoNome;

    // Move o áudio para pasta
    if (!move_uploaded_file($audios['tmp_name'][$i], $destino)) {
        $feedbacks[] = "Falha ao enviar arquivo: $audioName";
        continue;
    }

    // Se for wav, converte para m4a
    if ($ext == 'wav') {
        $m4aPath = $uploadDir . uniqid('audio_') . '.m4a';
        if (converterWavParaM4a($destino, $m4aPath)) {
            unlink($destino); // remove .wav
            $destino = $m4aPath;
            $ext = 'm4a';
        } else {
            $feedbacks[] = "Não foi possível converter $audioName para M4A. O arquivo .wav será usado.";
        }
    }

    // Procura PDF correspondente, se solicitado (baseado no nome do áudio)
    $pdf_url = null;
    if ($tipo_material_apoio == 'upload_pdf' && $pdfs && !empty($pdfs['name'])) {
        // Busca por PDF com o mesmo nome base do áudio (ignorando extensão)
        $baseAudio = pathinfo($audioName, PATHINFO_FILENAME);
        $found = false;
        foreach ($pdfs['name'] as $j => $pdfName) {
            $basePdf = pathinfo($pdfName, PATHINFO_FILENAME);
            if (strcasecmp($baseAudio, $basePdf) == 0 && $pdfs['tmp_name'][$j]) {
                $pdfExt = strtolower(pathinfo($pdfName, PATHINFO_EXTENSION));
                $pdfNome = uniqid('pdf_') . '.' . $pdfExt;
                $pdfDestino = $uploadDir . $pdfNome;
                if (move_uploaded_file($pdfs['tmp_name'][$j], $pdfDestino)) {
                    $pdf_url = 'uploads/podcasts/' . $pdfNome;
                    $found = true;
                }
                break;
            }
        }
        if (!$found) {
            // Se não achou correspondente, ignora PDF (ou poderia pegar um genérico, se quiser)
            $pdf_url = null;
        }
    } elseif ($tipo_material_apoio == 'link_externo') {
        $pdf_url = $link_material_apoio_url;
    }

    // Descrição automática se não enviada e assunto for "papel do to na neonatal"
    $desc = $descricao_podcast;
    if (empty($desc) && $nome_assunto && $nome_assunto == 'papel do to na neonatal') {
        $desc = descricaoPadraoNeonatal();
    }

    // Salva no banco
    $sql = "INSERT INTO podcasts 
        (titulo_podcast, id_categoria, id_assunto, descricao_podcast, arquivo_audio, tipo_arquivo_audio, visibilidade, id_plano_minimo, url_material_apoio, id_utilizador, data_upload) 
        VALUES (:titulo, :id_categoria, :id_assunto, :descricao, :arquivo_audio, :tipo_arquivo_audio, :visibilidade, :id_plano_minimo, :url_material_apoio, :id_utilizador, NOW())";
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute([
        ':titulo' => pathinfo($audioName, PATHINFO_FILENAME),
        ':id_categoria' => $id_categoria,
        ':id_assunto' => $id_assunto,
        ':descricao' => $desc,
        ':arquivo_audio' => 'uploads/podcasts/' . basename($destino),
        ':tipo_arquivo_audio' => $ext,
        ':visibilidade' => $visibilidade,
        ':id_plano_minimo' => $id_plano_minimo ?: null,
        ':url_material_apoio' => $pdf_url,
        ':id_utilizador' => $usuario_id,
    ]);
    if ($ok) {
        $feedbacks[] = "Podcast <strong>'".htmlspecialchars($audioName)."'</strong> adicionado com sucesso!";
    } else {
        $feedbacks[] = "<span style='color:red'>Erro ao salvar podcast '".htmlspecialchars($audioName)."'.</span>";
    }
}

// Exibe o resultado
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Resultado do Upload</title>
<link href='https://cdn.tailwindcss.com' rel='stylesheet'></head><body class='bg-gray-50 p-8'>";
echo "<div class='max-w-lg mx-auto bg-white rounded-xl shadow p-8'>";
echo "<h2 class='text-2xl font-bold mb-6'>Resultado do Upload em Massa</h2><ul class='space-y-2'>";
foreach ($feedbacks as $msg) echo "<li class='text-sm text-gray-700'>$msg</li>";
echo "</ul><a href='adicionar_podcast_em_massa.php' class='inline-block mt-8 px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark'>Voltar</a>";
echo "</div></body></html>";
?>
