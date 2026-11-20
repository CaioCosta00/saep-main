<?php
session_start();
require_once __DIR__ . '/conexao.php';

// ---------- 1. Autenticação (rota privada) ----------
$logado = false;
if (!empty($_SESSION["id_user"]) && time() - ($_SESSION["ultimo_acesso"] ?? 0) <= 1800) {
    $st = $pdo->prepare("SELECT id_user, nome, tipo_usuario FROM `user` WHERE id_user = ?");
    $st->execute([$_SESSION["id_user"]]);
    $logado = $st->fetch();
}
if (!$logado || !in_array($logado["tipo_usuario"], ["operador", "administrador"], true)) {
    session_unset();
    session_destroy();
    header("Location: login.php?expirou=1");
    exit;
}
$_SESSION["nome"]          = $logado["nome"];
$_SESSION["tipo_usuario"]  = $logado["tipo_usuario"];
$_SESSION["ultimo_acesso"] = time();
$ehAdmin = $logado["tipo_usuario"] === "administrador";

// ---------- 2. Autorização: página exclusiva do administrador (GET e POST) ----------
if (!$ehAdmin) {
    header("Location: index.php?negado=1");
    exit;
}

$sucesso   = "";
$erro      = "";
$tipos     = ["operador" => "Operador", "administrador" => "Administrador"];
$idLogado  = (int) $_SESSION["id_user"];

// ---------- 3. Processamento: exclusão ----------
if (isset($_GET["excluir"])) {
    $idExcluir = (int) $_GET["excluir"];

    if ($idExcluir === $idLogado) {
        $erro = "Você não pode excluir a própria conta enquanto estiver autenticado!";
    } else {
        // preserva o histórico: usuário com movimentações não pode ser removido
        $st = $pdo->prepare("SELECT COUNT(*) FROM movimentacao WHERE id_user = ?");
        $st->execute([$idExcluir]);

        if ($st->fetchColumn() > 0) {
            $erro = "Este usuário possui movimentações registradas e não pode ser removido.";
        } else {
            try {
                $st = $pdo->prepare("DELETE FROM `user` WHERE id_user = ?");
                $st->execute([$idExcluir]);

                if ($st->rowCount() > 0) {
                    $sucesso = "Usuário removido com sucesso!";
                } else {
                    $erro = "Não foi possível encontrar o usuário!";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1451) {       // registro possui dependentes (FK)
                    $erro = "Este usuário possui registros vinculados e não pode ser removido.";
                } else {
                    throw $e;
                }
            }
        }
    }
}
if (isset($_GET["salvo"])) {
    $sucesso = $_GET["salvo"] === "editado" ? "Usuário atualizado com sucesso!" : "Usuário cadastrado com sucesso!";
}

// ---------- 4. Cadastro / edição ----------
$acao     = $_GET["acao"] ?? "";
$idEdicao = (int) ($_GET["id"] ?? 0);
$nome     = "";
$tipo     = "operador";
$alvo     = false;

if ($acao === "editar") {
    $st = $pdo->prepare("SELECT id_user, nome, tipo_usuario FROM `user` WHERE id_user = ?");
    $st->execute([$idEdicao]);
    $alvo = $st->fetch();

    if (!$alvo) {
        $erro = "Usuário não encontrado!";
        $acao = "";
    } else {
        $nome = $alvo["nome"];
        $tipo = $alvo["tipo_usuario"];
    }
}
$formAberto = $acao === "novo" || $acao === "editar";
$proprio    = $acao === "editar" && $idEdicao === $idLogado;    // editando a própria conta

if ($_SERVER["REQUEST_METHOD"] === "POST" && $formAberto) {

    $nome     = trim($_POST["nome"] ?? "");
    $senha    = $_POST["senha"] ?? "";
    $confirma = $_POST["confirma"] ?? "";
    // o administrador nunca altera o próprio perfil: o valor enviado é ignorado
    $tipo     = $proprio ? $alvo["tipo_usuario"] : ($_POST["tipo_usuario"] ?? "");

    if ($nome === "" || $tipo === "" || ($acao === "novo" && $senha === "")) {
        $erro = "Preencha todos os campos!";
    } elseif (mb_strlen($nome) < 3 || mb_strlen($nome) > 50) {
        $erro = "O nome deve ter entre 3 e 50 caracteres.";
    } elseif (!isset($tipos[$tipo])) {
        $erro = "Tipo de usuário não aceito!";
    } elseif ($senha !== "" && strlen($senha) < 6) {
        $erro = "A senha deve ter pelo menos 6 caracteres.";
    } elseif ($senha !== $confirma) {
        $erro = "A confirmação de senha não confere.";
    } else {
        $st = $pdo->prepare("SELECT id_user FROM `user` WHERE nome = ? AND id_user <> ?");
        $st->execute([$nome, $acao === "editar" ? $idEdicao : 0]);
        if ($st->fetch()) {
            $erro = "Já existe um usuário com este nome!";
        }
    }

    if ($erro === "") {
        if ($acao === "novo") {
            $st = $pdo->prepare("INSERT INTO `user` (nome, senha, tipo_usuario) VALUES (?, ?, ?)");
            $st->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $tipo]);
            header("Location: usuarios.php?salvo=novo");
        } else {
            if ($senha !== "") {
                $st = $pdo->prepare("UPDATE `user` SET nome = ?, senha = ?, tipo_usuario = ? WHERE id_user = ?");
                $st->execute([$nome, password_hash($senha, PASSWORD_DEFAULT), $tipo, $idEdicao]);
            } else {
                $st = $pdo->prepare("UPDATE `user` SET nome = ?, tipo_usuario = ? WHERE id_user = ?");
                $st->execute([$nome, $tipo, $idEdicao]);
            }
            header("Location: usuarios.php?salvo=editado");
        }
        exit;
    }
}

// ---------- 5. Consultas ----------
$usuarios = $pdo->query("SELECT id_user, nome, tipo_usuario FROM `user` ORDER BY nome")->fetchAll();

require_once __DIR__ . '/includes/menu.php';
?>
    <main class="flex-1 flex flex-col">

        <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <div>
                <?php if ($formAberto): ?>
                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                        <a href="usuarios.php" class="hover:text-senai-blue">Usuários</a> ›
                        <span class="text-gray-700 font-semibold"><?= $acao === "editar" ? "Editar Usuário" : "Novo Usuário" ?></span>
                    </div>
                    <h1 class="text-xl font-extrabold text-gray-800"><?= $acao === "editar" ? "Editar Usuário" : "Novo Usuário" ?></h1>
                <?php else: ?>
                    <h1 class="text-xl font-extrabold text-gray-800">Gerenciar Usuários</h1>
                    <p class="text-sm text-gray-500">Cadastre, edite e remova usuários e seus perfis de acesso</p>
                <?php endif; ?>
            </div>
            <?php if (!$formAberto): ?>
                <a href="?acao=novo" class="bg-senai-green text-white font-bold px-4 py-2.5 rounded-lg text-sm hover:bg-green-600 transition flex items-center gap-2">
                    + Novo Usuário
                </a>
            <?php endif; ?>
        </div>

        <div class="p-6 flex-1">

            <?php if (!empty($sucesso)): ?>
                <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-5 flex items-center gap-2 text-sm">
                    <span class="font-bold text-base">✓</span>
                    <span><?= $sucesso ?></span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-green-400 hover:text-green-700 text-lg leading-none">×</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-5 flex items-center gap-2 text-sm">
                    <span class="font-bold text-base">!</span>
                    <span><?= htmlspecialchars($erro) ?></span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-700 text-lg leading-none">×</button>
                </div>
            <?php endif; ?>

            <?php if ($formAberto): ?>

                <div class="bg-white rounded-xl shadow-sm p-6 max-w-xl">
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-input" required minlength="3" maxlength="50" value="<?= htmlspecialchars($nome) ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Perfil *</label>
                            <select name="tipo_usuario" class="form-input" <?= $proprio ? "disabled" : "required" ?>>
                                <?php foreach ($tipos as $valor => $rotulo): ?>
                                    <option value="<?= $valor ?>" <?= $tipo === $valor ? "selected" : "" ?>><?= $rotulo ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($proprio): ?>
                                <p class="text-xs text-gray-400 mt-1">Você não pode alterar o próprio perfil.</p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Senha <?= $acao === "novo" ? "*" : "" ?></label>
                            <input type="password" name="senha" class="form-input" minlength="6" autocomplete="new-password" <?= $acao === "novo" ? "required" : "" ?> placeholder="<?= $acao === "novo" ? "Mínimo de 6 caracteres" : "Deixe em branco para manter a senha atual" ?>">
                        </div>
                        <div class="mb-6">
                            <label class="form-label">Confirmar senha <?= $acao === "novo" ? "*" : "" ?></label>
                            <input type="password" name="confirma" class="form-input" autocomplete="new-password" <?= $acao === "novo" ? "required" : "" ?>>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit" class="bg-senai-blue text-white font-bold px-5 py-2.5 rounded-lg text-sm hover:bg-senai-blue-dark transition">💾 Salvar</button>
                            <a href="usuarios.php" class="bg-gray-100 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-sm hover:bg-gray-200 transition">Cancelar</a>
                        </div>
                    </form>
                </div>

            <?php else: ?>

                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-senai-blue text-white">
                            <tr>
                                <th class="px-4 py-3 text-left">Nome</th>
                                <th class="px-4 py-3 text-center">Perfil</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                        <?php if (!$usuarios): ?>
                            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Nenhum usuário encontrado!</td></tr>
                        <?php endif; ?>

                        <?php foreach ($usuarios as $u): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 text-gray-700 font-semibold">
                                    <?= htmlspecialchars($u["nome"]) ?>
                                    <?php if ((int) $u["id_user"] === $idLogado): ?>
                                        <span class="text-xs text-gray-400 font-normal">(você)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($u["tipo_usuario"] === "administrador"): ?>
                                        <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-2 py-1 rounded">Administrador</span>
                                    <?php else: ?>
                                        <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-1 rounded">Operador</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="?acao=editar&id=<?= $u["id_user"] ?>" class="bg-senai-orange text-white text-xs px-2.5 py-1.5 rounded-md hover:bg-orange-600 transition">✏️ Editar</a>
                                        <?php if ((int) $u["id_user"] !== $idLogado): ?>
                                            <a onclick="return confirm('Tem certeza disso?')" class="bg-senai-red text-white text-xs px-2.5 py-1.5 rounded-md hover:bg-red-700 transition" href="?excluir=<?= $u["id_user"] ?>">Excluir</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>

                    <div class="border-t border-gray-100 px-4 py-3 bg-gray-50">
                        <p class="text-xs text-gray-400">Exibindo <?= count($usuarios) ?> usuários</p>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>