<?php
// Inicializar a sessão
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Conectar ao banco de dados
$database = 'localhost:C:/Windel/Dados/nutribackup.fdb';
$user = 'SYSDBA';
$password = 'masterkey';

try {
    $dbh = new PDO("firebird:dbname=$database;charset=UTF8", $user, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo '<div class="error-message">';
    echo "Erro de conexão: " . $e->getMessage();
    echo '</div>';
    exit;
}

// Consulta SQL
$sql_query = "
SELECT 
    P.IDPRODUTO, 
    P.DESCRICAO, 
    P.UN, 
    C.CLASSIFICACAO AS NCM, 
    S.IDSITTRIB, 
    S.CODIGO AS CST, 
    S.CSOSN, 
    P.ALIQICMS,  
    N.CFOPDENTRO,
    P.CST_PIS_ENTRADA, 
    P.PER_PIS, 
    P.CST_COFINS_ENTRADA, 
    P.PER_COFINS,  
    P.CST_PIS_SAIDA, 
    P.PER_PIS_SAI, 
    P.CST_COFINS_SAIDA, 
    P.PER_COFINS_SAI,
    P.FORADELINHA
FROM PRODUTOS P
LEFT JOIN CLASSFISCAL C ON C.IDCLASSFISCAL = P.CLASSFISCAL
LEFT JOIN SITTRIB S ON S.IDSITTRIB = P.SITTRIB
LEFT JOIN NATOPER N ON N.IDNATOPER = P.NATOPERPADRAOVENDAS
WHERE FORADELINHA = 'N'
ORDER BY C.CLASSIFICACAO
";

// Consultar informações dos produtos
$produtos = [];
try {
    $stmt = $dbh->query($sql_query);
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="error-message">';
    echo "Erro ao consultar produtos: " . $e->getMessage();
    echo '</div>';
}

// Função para formatar percentuais
function format_percentual($value) {
    return number_format($value, 2, ',', '');
}

// Função para exportar dados para CSV
function export_to_csv($data, $filename = 'produtos.csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Cabeçalhos do CSV
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]), ';');
    }

    // Dados do CSV
    foreach ($data as $row) {
        foreach ($row as $key => $value) {
            if (strpos($key, 'PER_') !== false || $key === 'ALIQICMS') {
                $row[$key] = format_percentual($value);
            }
        }
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;
}

if (isset($_POST['export_csv'])) {
    export_to_csv($produtos);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar e Atualizar Produtos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Importar e Atualizar Produtos</h1>
        </header>
        
        <main>
            <!-- Formulário de upload -->
            <section class="form-container">
                <h2>Upload de Arquivo CSV</h2>
                <form action="importar_atualizar_produtos.php" method="post" enctype="multipart/form-data">
                    <label for="csv_file">Selecione o arquivo CSV:</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    <button type="submit">Importar</button>
                </form>
            </section>

            <!-- Resultados da importação -->
            <?php if (isset($_GET['status'])): ?>
                <section class="message-container">
                    <?php if ($_GET['status'] == 'success'): ?>
                        <div class="success-message">
                            <p>Importação realizada com sucesso!</p>
                        </div>
                    <?php elseif ($_GET['status'] == 'error'): ?>
                        <div class="error-message">
                            <p>Erro na importação! Verifique os detalhes e tente novamente.</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Exibir SQL de consulta -->
            <section class="sql-container">
                <h2>SQL de Consulta</h2>
                <pre><?php echo htmlspecialchars($sql_query); ?></pre>
            </section>

            <!-- Exibir dados da consulta -->
            <section class="data-container">
                <h2>Dados da Consulta</h2>
                <form method="post">
                    <button type="submit" name="export_csv" class="export-button">Exportar para CSV</button>
                </form>
                <table>
                    <thead>
                        <tr>
                            <?php if (!empty($produtos)): ?>
                                <?php foreach (array_keys($produtos[0]) as $header): ?>
                                    <th><?php echo htmlspecialchars($header); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <?php foreach ($produto as $value): ?>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>
