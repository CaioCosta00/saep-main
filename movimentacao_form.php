<?php
session_start();
require_once __DIR__ . '/conexao.php';


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

$erro = "";

$id_produto = (int) ($_GET["id_produto"] ?? 0);
$tipo       = "ENTRADA";
$quantidade = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_produto = (int) ($_POST["id_produto"] ?? 0);
    $tipo       = $_POST["tipo"] ?? "";
    $quantidade = (int) ($_POST["quantidade"] ?? 0);
    $id_user    = (int) $_SESSION["id_user"];    

    if (!$id_produto || !$quantidade || $tipo === "") {
        $erro = "Preencha todos os campos!";
    } elseif ($tipo !== "ENTRADA" && $tipo !== "SAIDA") {
        $erro = "Tipo não aceito!";
    } elseif ($quantidade <= 0) {
        $erro = "Quantidade inválida!";
    } else {
        $pdo->beginTransaction();
        try {
            
            $st = $pdo->prepare("SELECT quantidade_estoque FROM produto WHERE id_produto = ? FOR UPDATE");
            $st->execute([$id_produto]);
            $produto = $st->fetch();

            if (!$produto) {
                $erro = "Não foi possível encontrar o produto!";
            } elseif ($tipo === "SAIDA" && $produto["quantidade_estoque"] < $quantidade) {
                $erro = "Saída não permitida: estoque insuficiente. Disponível: " . (int) $produto["quantidade_estoque"] . ". Solicitado: " . $quantidade . ".";
            }

            if ($erro === "") {
                $st = $pdo->prepare("INSERT INTO movimentacao (tipo, quantidade, id_produto, datetime_movimentacao, id_user) VALUES (?, ?, ?, NOW(), ?)");
                $st->execute([$tipo, $quantidade, $id_produto, $id_user]);

                
                $delta = $tipo === "ENTRADA" ? $quantidade : -$quantidade;
                $st = $pdo->prepare("UPDATE produto SET quantidade_estoque = quantidade_estoque + ? WHERE id_produto = ?");
                $st->execute([$delta, $id_produto]);

                $pdo->commit();
                header("Location: movimentacoes.php?salvo=" . strtolower($tipo));
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = "Erro ao registrar a movimentação. Nenhuma alteração foi feita.";
        }
    }
}


$produtos = $pdo->query("SELECT id_produto, nome, quantidade_estoque FROM produto ORDER BY nome")->fetchAll();

require_once __DIR__ . '/includes/menu.php';
?>
    <main class="flex-1 flex flex-col">
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                <a href="movimentacoes.php" class="hover:text-senai-blue">Movimentações</a> ›
                <span class="text-gray-700 font-semibold">Nova Movimentação</span>
            </div>
            <h1 class="text-xl font-extrabold text-gray-800">Alterar Estoque</h1>
        </div>
        <div class="p-6 flex-1 max-w-xl">

            <?php if (!empty($erro)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="post">
                    <div class="mb-4">
                        <label class="form-label">Produto *</label>
                        <select name="id_produto" class="form-input" required>
                            <option value="">Selecione o produto</option>
                            <?php foreach ($produtos as $p): ?>
                                <option value="<?= $p["id_produto"] ?>" <?= $p["id_produto"] == $id_produto ? "selected" : "" ?>><?= htmlspecialchars($p["nome"]) ?> (estoque: <?= $p["quantidade_estoque"] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Tipo *</label>
                        <select name="tipo" class="form-input" required>
                            <option value="ENTRADA" <?= $tipo == "ENTRADA" ? "selected" : "" ?>>Entrada</option>
                            <option value="SAIDA" <?= $tipo == "SAIDA" ? "selected" : "" ?>>Saída</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Quantidade *</label>
                        <input type="number" name="quantidade" class="form-input" required min="1" step="1" placeholder="Ex: 10" value="<?= htmlspecialchars($quantidade) ?>">
                    </div>
                    <div class="mb-6">
                        <label class="form-label">Usuário responsável</label>
                        <input type="text" class="form-input bg-gray-100" disabled value="<?= htmlspecialchars($_SESSION["nome"]) ?>">
                        <p class="text-xs text-gray-400 mt-1">Registrado automaticamente com o usuário autenticado.</p>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-senai-blue text-white font-bold px-5 py-2.5 rounded-lg text-sm hover:bg-senai-blue-dark transition">💾 Salvar</button>
                        <a href="movimentacoes.php" class="bg-gray-100 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-sm hover:bg-gray-200 transition">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>