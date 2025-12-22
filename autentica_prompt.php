<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: autentica_login.php?pag=autentica_prompt.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? "default";

/**
 * =========================
 * CONFIG
 * =========================
 */
function env(string $key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
$API_BASE = env('API_BASE', 'https://autentica-dqcbd5brdthhbeb2.swedencentral-01.azurewebsites.net');

/* ===========================
   UTILS
=========================== */
function clean($v) {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function backend_get($url) {
    $res = @file_get_contents($url);
    return $res ? json_decode($res, true) : null;
}

function backend_post($url, $payload) {
    $ctx = stream_context_create([
        "http" => [
            "method"  => "POST",
            "header"  => "Content-Type: application/json",
            "content" => json_encode($payload),
            "timeout" => 10
        ]
    ]);
    return @file_get_contents($url, false, $ctx);
}

$message = "";

/* ===========================
   POST ACTIONS
=========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    switch ($_POST['action'] ?? '') {

        case 'new_version':
            backend_post("$API_BASE/prompt/save", [
                "user_id" => $user_id,
                "prompt_name" => $_POST['prompt_name'],
                "content" => $_POST['content'],
                "comment" => $_POST['comment'] ?? "",
                "feedback" => $_POST['feedback'] ?? ""
            ]);
            $message = "Nuova versione creata e attivata.";
            break;

        case 'activate_version':
            backend_post("$API_BASE/prompt/activate", [
                "user_id" => $user_id,
                "prompt_name" => $_POST['prompt_name'],
                "version" => intval($_POST['version'])
            ]);
            $message = "Versione attivata.";
            break;

        case 'update_feedback':
            backend_post("$API_BASE/prompt/feedback", [
                "user_id" => $user_id,
                "id" => $_POST['id'],
                "feedback" => $_POST['feedback']
            ]);
            $message = "Feedback aggiornato.";
            break;

        case 'delete_version':
            backend_post("$API_BASE/prompt/delete", [
                "user_id" => $user_id,
                "id" => $_POST['id']
            ]);
            $message = "Versione eliminata.";
            break;
    }
}

/* ===========================
   DATA FETCH
=========================== */

$prompts = backend_get(
    "$API_BASE/prompt/list?user_id=" . urlencode($user_id)
) ?? [];


$prompt_name = $_GET['prompt'] ?? null;
$active_version = null;
$versions = [];

if ($prompt_name) {
    $active = backend_get(
        "$API_BASE/prompt/get/" . urlencode($prompt_name) . "?user_id=" . urlencode($user_id)
    );
    $active_version = $active['version'] ?? null;

    $versions = backend_get(
        "$API_BASE/prompt/history/" . urlencode($prompt_name) . "?user_id=" . urlencode($user_id)
    ) ?? [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Prompt Manager</title>
    <link rel="icon" type="image/png" href="images/autentica.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --adm-blue: #003b80;
            --adm-blue-light: #e8f0fb;
            --adm-grey: #f2f4f7;
        }

        body { background: var(--adm-grey); }

        .adm-card { border-left: 4px solid var(--adm-blue); }

        .adm-header {
            background: var(--adm-blue);
            color: white;
            padding: 14px 22px;
            border-radius: 6px;
        }

        .btn-adm-primary {
            background: var(--adm-blue);
            border-color: var(--adm-blue);
            color: #fff;
            font-weight: 600;
        }

        .btn-adm-primary:hover {
            background: #002e63;
            border-color: #002e63;
            color: white;
        }

        .list-group-item.active {
            background: var(--adm-blue);
            border-color: var(--adm-blue);
        }

        .prompt-preview {
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            background: #fff;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .editor-area { height: 300px; }

        summary {
            cursor: pointer;
            font-weight: bold;
            color: var(--adm-blue);
        }
        .btn-adm-secondary { background:#e6ecf7;border-color:#003b80;color:#003b80;font-weight:600; }
    </style>
</head>

<body class="p-4">
<div class="container">

    <div class="adm-header mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="m-0">⚙️ Prompt Manager</h2>
            <small>User: <?= htmlentities($user_id) ?></small>
        </div>
        <a href="autentica.php?reset=1" class="btn btn-adm-secondary">⬅ Torna all'app</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success shadow-sm"><?= $message ?></div>
    <?php endif; ?>

    <div class="row">

        <!-- LISTA PROMPT -->
        <div class="col-md-3">
            <div class="list-group shadow-sm">
                <div class="list-group-item active fw-bold">Prompt disponibili</div>

                <?php foreach ($prompts as $p): ?>
                <a href="?prompt=<?= urlencode($p['prompt_name']) ?>"
                   class="list-group-item list-group-item-action
                   <?= ($prompt_name === $p['prompt_name']) ? 'active' : '' ?>">
                    <?= $p['prompt_name'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AREA DETTAGLIO -->
        <div class="col-md-9">

<?php if ($prompt_name): ?>
            <div class="card adm-card shadow-sm">

                <div class="card-header bg-white">
                    <h4 class="m-0 text-primary">✏️ Prompt: <strong><?= $prompt_name ?></strong></h4>
                </div>

                <div class="card-body">

                    <h5 class="text-primary mb-3">📚 Versioni disponibili</h5>

<?php foreach ($versions as $v): ?>
                    <div class="border rounded p-3 mb-3 bg-light">

                        <div class="d-flex justify-content-between">
                            <strong>Versione v<?= $v['version'] ?></strong>
                            <span class="text-muted"><?= $v['created_at'] ?></span>
                        </div>

                        <?php if (!empty($v['comment'])): ?>
                            <p class="text-muted mt-2"><?= nl2br(clean($v['comment'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($v['feedback'])): ?>
                            <p><strong>Feedback:</strong> <?= nl2br(clean($v['feedback'])) ?></p>
                        <?php endif; ?>

                        <details class="mt-2">
                            <summary>Mostra Prompt</summary>
                            <pre class="prompt-preview mt-2"><?= htmlentities($v['content']) ?></pre>
                        </details>

                        <?php if ($v['version'] != $active_version): ?>
                        <form method="POST" class="mt-2 d-flex gap-2">
                            <input type="hidden" name="action" value="activate_version">
                            <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">
                            <input type="hidden" name="version" value="<?= $v['version'] ?>">
                            <button class="btn btn-sm btn-adm-primary">Attiva versione</button>
                        </form>

                        <form method="POST" class="mt-2">
                            <input type="hidden" name="action" value="delete_version">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Eliminare questa versione?');">
                                🗑️ Elimina versione
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="badge bg-success mt-2">Attiva</span>
                        <?php endif; ?>

                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="update_feedback">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                            <label class="form-label">Modifica Feedback</label>
                            <textarea name="feedback" class="form-control" rows="2"><?= clean($v['feedback'] ?? "") ?></textarea>
                            <button class="btn btn-adm-primary btn-sm mt-2">Salva feedback</button>
                        </form>

                    </div>
<?php endforeach; ?>

                    <hr>

                    <h5 class="text-primary">➕ Crea nuova versione</h5>

                    <form method="POST">
                        <input type="hidden" name="action" value="new_version">
                        <input type="hidden" name="prompt_name" value="<?= $prompt_name ?>">

                        <label class="form-label">Commento (facoltativo)</label>
                        <input type="text" name="comment" class="form-control mb-3">

                        <label class="form-label">Feedback iniziale</label>
                        <input type="text" name="feedback" class="form-control mb-3">

                        <label class="form-label">Contenuto Prompt</label>
                        <textarea name="content" class="form-control editor-area mb-3" required></textarea>

                        <button class="btn btn-adm-primary">Salva nuova versione</button>
                    </form>

                </div>
            </div>
<?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>

