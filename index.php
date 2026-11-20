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


require_once __DIR__ . '/includes/menu.php';

$sql = "SELECT
    (SELECT COUNT(*) FROM produto) AS produtos,
    (SELECT COALESCE(SUM(quantidade_estoque), 0) FROM produto) AS itens,
    (SELECT COUNT(*) FROM movimentacao) AS movimentacoes,
    (SELECT COUNT(*) FROM movimentacao WHERE tipo = 'SAIDA') AS saidas";
$totais = $pdo->query($sql)->fetch();

$cards = [
    ["🧴", "Produtos", $totais["produtos"], "produtos.php", "bg-senai-blue"],
    ["📋", "Itens em estoque", $totais["itens"], "estoque.php", "bg-senai-green"],
    ["🔄", "Movimentações", $totais["movimentacoes"], "movimentacoes.php", "bg-senai-orange"],
    ["📤", "Saídas", $totais["saidas"], "saidas.php", "bg-senai-red"],
];
?>
    <main class="flex-1 flex flex-col">

        <div class="bg-white border-b border-gray-200 px-6 py-4">
            <h1 class="text-xl font-extrabold text-gray-800">Dashboard</h1>
            <p class="text-sm text-gray-500">Visão geral do controle de estoque</p>
        </div>

        <div class="p-6 flex-1">

            <?php if (isset($_GET["negado"])): ?>
                <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-5 flex items-center gap-2 text-sm">
                    <span class="font-bold text-base">!</span>
                    <span>Acesso negado: esta área é exclusiva de administradores.</span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-700 text-lg leading-none">×</button>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <?php foreach ($cards as $c): ?>
                    <a href="<?= $c[3] ?>" class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4 hover:shadow-md transition">
                        <div class="<?= $c[4] ?> text-white text-2xl w-12 h-12 rounded-lg flex items-center justify-center"><?= $c[0] ?></div>
                        <div>
                            <p class="text-2xl font-extrabold text-gray-800"><?= $c[2] ?></p>
                            <p class="text-xs text-gray-500 uppercase font-semibold"><?= $c[1] ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-bold text-gray-800">Últimas movimentações</h2>
                    <a href="movimentacoes.php" class="text-xs text-senai-blue hover:underline">Ver todas →</a>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-senai-blue text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Produto</th>
                            <th class="px-4 py-3 text-center">Tipo</th>
                            <th class="px-4 py-3 text-center">Quantidade</th>
                            <th class="px-4 py-3 text-center">Data</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php
                        $sql = "SELECT m.tipo, m.quantidade, m.datetime_movimentacao, p.nome
                                FROM movimentacao m
                                JOIN produto p ON p.id_produto = m.id_produto
                                ORDER BY m.id_movimentacao DESC LIMIT 5";
                        $movimentacoes = $pdo->query($sql)->fetchAll();
                        foreach ($movimentacoes as $m):
                    ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-700 font-semibold"><?= htmlspecialchars($m["nome"]) ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($m["tipo"] == "ENTRADA"): ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded">Entrada</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded">Saída</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-600"><?= $m["quantidade"] ?></td>
                            <td class="px-4 py-3 text-center text-gray-600"><?= date("d/m/Y H:i", strtotime($m["datetime_movimentacao"])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

</body>
</html>