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


$filtroNome = trim($_GET["nome"] ?? "");
$filtroData = trim($_GET["data"] ?? "");
if ($filtroData !== "") {
    $d = DateTime::createFromFormat("Y-m-d", $filtroData);
    if (!$d || $d->format("Y-m-d") !== $filtroData) {
        $filtroData = "";
    }
}

$where  = [];
$params = [];
if ($filtroNome !== "") {
    $where[]  = "nome LIKE ?";
    $params[] = "%" . addcslashes($filtroNome, "%_\\") . "%";
}
if ($filtroData !== "") {
    $where[]  = "DATE(data_cadastro) = ?";
    $params[] = $filtroData;
}
$sqlWhere = $where ? " WHERE " . implode(" AND ", $where) : "";

$porPagina = 10;
$st = $pdo->prepare("SELECT COUNT(*) FROM produto" . $sqlWhere);
$st->execute($params);
$totalRegistros = (int) $st->fetchColumn();
$totalPaginas   = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaAtual    = min(max(1, (int) ($_GET["pagina"] ?? 1)), $totalPaginas);
$offset         = ($paginaAtual - 1) * $porPagina;

$st = $pdo->prepare("SELECT * FROM produto" . $sqlWhere . " ORDER BY quantidade_estoque, nome, id_produto LIMIT $porPagina OFFSET $offset");
$st->execute($params);
$produtos = $st->fetchAll();

$qs = function ($p) use ($filtroNome, $filtroData) {
    return "?" . http_build_query(array_filter(
        ["nome" => $filtroNome, "data" => $filtroData, "pagina" => $p],
        function ($v) { return $v !== ""; }
    ));
};

require_once __DIR__ . '/includes/menu.php';
?>
    <main class="flex-1 flex flex-col">

        <div class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-extrabold text-gray-800">Verificar Estoque</h1>
                <p class="text-sm text-gray-500">Quantidade disponível de cada produto</p>
            </div>
            <a href="movimentacao_form.php" class="bg-senai-green text-white font-bold px-4 py-2.5 rounded-lg text-sm hover:bg-green-600 transition flex items-center gap-2">
                + Nova Movimentação
            </a>
        </div>

        <div class="p-6 flex-1">

            <form method="get" class="bg-white rounded-xl shadow-sm p-4 mb-5 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-input" placeholder="Buscar por nome" value="<?= htmlspecialchars($filtroNome) ?>">
                </div>
                <div>
                    <label class="form-label">Data de cadastro</label>
                    <input type="date" name="data" class="form-input" value="<?= htmlspecialchars($filtroData) ?>">
                </div>
                <button type="submit" class="bg-senai-blue text-white font-bold px-5 py-2.5 rounded-lg text-sm hover:bg-senai-blue-dark transition">🔍 Filtrar</button>
                <a href="estoque.php" class="bg-gray-100 text-gray-600 font-semibold px-5 py-2.5 rounded-lg text-sm hover:bg-gray-200 transition">Limpar</a>
            </form>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-senai-blue text-white">
                        <tr>
                            <th class="px-4 py-3 text-left">Produto</th>
                            <th class="px-4 py-3 text-center">Valor unitário</th>
                            <th class="px-4 py-3 text-center">Quantidade</th>
                            <th class="px-4 py-3 text-center">Situação</th>
                            <th class="px-4 py-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">

                    <?php if (!$produtos): ?>
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">Nenhum produto encontrado!</td></tr>
                    <?php endif; ?>

                    <?php foreach ($produtos as $p): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-700 font-semibold"><?= htmlspecialchars($p["nome"]) ?></td>
                            <td class="px-4 py-3 text-center text-gray-600">R$ <?= number_format($p["valor_unitario"], 2, ",", ".") ?></td>
                            <td class="px-4 py-3 text-center text-gray-800 font-bold"><?= $p["quantidade_estoque"] ?></td>
                            <td class="px-4 py-3 text-center">
                                <?php if ($p["quantidade_estoque"] > 0): ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded">Em estoque</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded">Sem estoque</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a href="movimentacao_form.php?id_produto=<?= $p["id_produto"] ?>" class="bg-yellow-500 text-white text-xs px-2.5 py-1.5 rounded-md hover:bg-yellow-600 transition" title="Movimentar estoque">🔄 Movimentar</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>

                <div class="border-t border-gray-100 px-4 py-3 bg-gray-50 flex items-center justify-between">
                    <p class="text-xs text-gray-400">Exibindo <?= count($produtos) ?> de <?= $totalRegistros ?> produtos</p>
                    <?php if ($totalPaginas > 1): ?>
                        <div class="flex items-center gap-1">
                            <?php if ($paginaAtual > 1): ?>
                                <a href="<?= htmlspecialchars($qs($paginaAtual - 1)) ?>" class="px-3 py-1 rounded border border-gray-200 text-xs text-gray-600 hover:bg-gray-100">‹ Anterior</a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $paginaAtual - 2); $i <= min($totalPaginas, $paginaAtual + 2); $i++): ?>
                                <a href="<?= htmlspecialchars($qs($i)) ?>" class="px-3 py-1 rounded text-xs <?= $i === $paginaAtual ? 'bg-senai-blue text-white font-bold' : 'border border-gray-200 text-gray-600 hover:bg-gray-100' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <?php if ($paginaAtual < $totalPaginas): ?>
                                <a href="<?= htmlspecialchars($qs($paginaAtual + 1)) ?>" class="px-3 py-1 rounded border border-gray-200 text-xs text-gray-600 hover:bg-gray-100">Próxima ›</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

</body>
</html>