<?php
session_start();
require_once __DIR__ . '/conexao.php';

// ---------- Logout ----------
if (isset($_GET["sair"])) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $c = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $c["path"], $c["domain"], $c["secure"], $c["httponly"]);
    }
    session_destroy();
    header("Location: login.php?saiu=1");
    exit;
}

// Já autenticado: vai direto para o sistema
if (!empty($_SESSION["id_user"])) {
    header("Location: index.php");
    exit;
}

$erro  = "";
$aviso = "";
$nome  = "";

if (isset($_GET["expirou"])) {
    $aviso = "Sessão expirada ou inválida. Faça login novamente.";
}
if (isset($_GET["saiu"])) {
    $aviso = "Você saiu do sistema.";
}

// ---------- Autenticação ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome  = trim($_POST["nome"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($nome === "" || $senha === "") {
        $erro = "Informe o nome e a senha!";
    } else {
        $st = $pdo->prepare("SELECT id_user, nome, senha, tipo_usuario FROM `user` WHERE nome = ?");
        $st->execute([$nome]);
        $user = $st->fetch();

        if (!$user
            || !password_verify($senha, $user["senha"])
            || !in_array($user["tipo_usuario"], ["operador", "administrador"], true)) {
            $erro = "Nome ou senha inválidos!";
        } else {
            session_regenerate_id(true);                      // evita fixação de sessão
            $_SESSION["id_user"]       = (int) $user["id_user"];
            $_SESSION["nome"]          = $user["nome"];
            $_SESSION["tipo_usuario"]  = $user["tipo_usuario"];
            $_SESSION["ultimo_acesso"] = time();

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Controle de Estoque SAEP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { senai: { red:'#C0392B', blue:'#34679A', 'blue-dark':'#2C5A85', orange:'#E67E22', green:'#27AE60' } } } }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .form-input { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; outline:none; transition:border .15s; }
        .form-input:focus { border-color:#34679A; box-shadow:0 0 0 3px rgba(52,103,154,.15); }
        .form-label { display:block; font-size:12px; font-weight:600; color:#6b7280; margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">

        <div class="text-center mb-6">
            <p class="text-2xl font-extrabold text-gray-800">📦 Estoque SAEP</p>
            <p class="text-sm text-gray-500">Acesse com seu nome e senha</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($aviso)): ?>
            <div class="bg-blue-50 border border-blue-300 text-blue-700 px-4 py-3 rounded mb-4 text-sm">
                <?= htmlspecialchars($aviso) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <form method="post">
                <div class="mb-4">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="nome" class="form-input" required maxlength="100" autofocus value="<?= htmlspecialchars($nome) ?>">
                </div>
                <div class="mb-6">
                    <label class="form-label">Senha *</label>
                    <input type="password" name="senha" class="form-input" required>
                </div>
                <button type="submit" class="w-full bg-senai-blue text-white font-bold px-5 py-2.5 rounded-lg text-sm hover:bg-senai-blue-dark transition">Entrar</button>
            </form>
        </div>

    </div>
</body>
</html>