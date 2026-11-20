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

$id_produto   = (int) ($_GET["id"] ?? 0);     
$editando     = $id_produto > 0;
$produtoAtual = false;

$nome       = "";
$quantidade = "";
$valor      = "";
$data       = date("Y-m-d");


if ($editando) {
    $st = $pdo->prepare("SELECT * FROM produto WHERE id_produto = ?");
    $st->execute([$id_produto]);
    $produtoAtual = $st->fetch();

    if ($produtoAtual) {
        $nome       = $produtoAtual["nome"];
        $quantidade = $produtoAtual["quantidade_estoque"];
        $valor      = $produtoAtual["valor_unitario"];
        $data       = date("Y-m-d", strtotime($produtoAtual["data_cadastro"]));
    }
}
$naoEncontrado = $editando && !$produtoAtual;


if ($_SERVER["REQUEST_METHOD"] === "POST" && !$naoEncontrado) {

    $nome  = trim($_POST["nome"] ?? "");        
    $valor = trim($_POST["valor"] ?? "");
    $data  = trim($_POST["data"] ?? "");
    if (!$editando) {
        $quantidade = trim($_POST["quantidade"] ?? "");   
    }

    $dataOk = DateTime::createFromFormat("Y-m-d", $data);

    if ($nome === "" || $valor === "" || $data === "" || (!$editando && $quantidade === "")) {
        $erro = "Preencha todos os campos!";
    } elseif (mb_strlen($nome) > 100) {
        $erro = "O nome deve ter no máximo 100 caracteres.";
    } elseif (!is_numeric($valor) || (!$editando && filter_var($quantidade, FILTER_VALIDATE_INT) === false)) {
        $erro = "Quantidade e valor devem ser numéricos.";
    } elseif ((float) $valor < 0 || (!$editando && (int) $quantidade < 0)) {
        $erro = "Quantidade e valor não podem ser negativos.";
    } elseif (!$dataOk || $dataOk->format("Y-m-d") !== $data) {
        $erro = "Data de cadastro inválida!";
    } else {
        $st = $pdo->prepare("SELECT id_produto FROM produto WHERE nome = ? AND id_produto <> ?");
        $st->execute([$nome, $id_produto]);
        if ($st->fetch()) {
            $erro = "Produto já cadastrado!";
        }
    }

    if ($erro === "") {
        if ($editando) {
            $st = $pdo->prepare("UPDATE produto SET nome = ?, valor_unitario = ?, data_cadastro = ? WHERE id_produto = ?");
            $st->execute([$nome, (float) $valor, $data, $id_produto]);
            header("Location: produtos.php?salvo=editado");
        } else {
            $st = $pdo->prepare("INSERT INTO produto (nome, quantidade_estoque, valor_unitario, data_cadastro) VALUES (?, ?, ?, ?)");
            $st->execute([$nome, (int) $quantidade, (float) $valor, $data]);
            header("Location: produtos.php?salvo=novo");   
        }
        exit;
    }
}

require_once __DIR__ . '/includes/menu.php';
?>
    <main class="flex-1 flex flex-col">
        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                <a href="produtos.php" class="hover:text-senai-blue">Produtos</a> ›
                <span class="text-gray-700 font-semibold"><?= $editando ? "Editar Produto" : "Novo Produto" ?></span>
            </div>
            <h1 class="text-xl font-extrabold text-gray-800"><?= $editando ? "Editar Produto" : "Novo Produto" ?></h1>
        </div>
        <div class="p-6 flex-1 max-w-xl">

            <?php if ($naoEncontrado): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    Nenhum produto encontrado!
                </div>
                <a href="produtos.php" class="bg-gray-100 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-sm hover:bg-gray-200 transition">Voltar</a>
            <?php else: ?>

            <?php if (!empty($erro)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form method="post">
                    <div class="mb-4">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-input" required maxlength="100" placeholder="Ex: Detergente" value="<?= htmlspecialchars($nome) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Quantidade em estoque <?= $editando ? "" : "*" ?></label>
                        <?php if ($editando): ?>
                            <input type="number" class="form-input bg-gray-100" disabled value="<?= htmlspecialchars($quantidade) ?>">
                            <p class="text-xs text-gray-400 mt-1">O saldo só é alterado por Movimentações (entradas e saídas).</p>
                        <?php else: ?>
                            <input type="number" name="quantidade" class="form-input" required min="0" step="1" placeholder="Ex: 25" value="<?= htmlspecialchars($quantidade) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Valor unitário (R$) *</label>
                        <input type="number" name="valor" class="form-input" required min="0" step="0.01" placeholder="Ex: 18.90" value="<?= htmlspecialchars($valor) ?>">
                    </div>
                    <div class="mb-6">
                        <label class="form-label">Data de cadastro *</label>
                        <input type="date" name="data" class="form-input" required value="<?= htmlspecialchars($data) ?>">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-senai-blue text-white font-bold px-5 py-2.5 rounded-lg text-sm hover:bg-senai-blue-dark transition">💾 Salvar</button>
                        <a href="produtos.php" class="bg-gray-100 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-sm hover:bg-gray-200 transition">Cancelar</a>
                    </div>
                </form>
            </div>

            <?php endif; ?>
        </div>
    </main>
</body>
</html>