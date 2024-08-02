<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Atualize os detalhes da conexão com o banco de dados
$database = 'localhost:C:/Windel/Dados/nutribackup.fdb'; // Ajuste o caminho conforme necessário
$user = 'SYSDBA';
$password = 'masterkey';

try {
    $dbh = new PDO("firebird:dbname=$database;charset=UTF8", $user, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo '<div style="background-color: #f8d7da; padding: 1rem; border-radius: 5px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-top: 10px; color: #721c24; border-color: #f5c6cb;">';
    echo "Erro de conexão: " . $e->getMessage();
    echo '</div>';
    exit;
}

// Verificar se o arquivo foi enviado
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    echo '<div style="background-color: #f8d7da; padding: 1rem; border-radius: 5px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-top: 10px; color: #721c24; border-color: #f5c6cb;">';
    echo "Erro ao enviar o arquivo. Verifique se você selecionou um arquivo CSV.";
    echo '</div>';
    exit;
}

$arquivo = $_FILES['csv_file']['tmp_name'];
$linhas_processadas = 0;
$linhas_erro = [];
$erros_campos = []; // Inicializar a variável

if (($handle = fopen($arquivo, 'r')) !== FALSE) {
    $linha_numero = 0; // Contador de linha para relatórios de erro
    while (($linha = fgetcsv($handle, 1000, ';')) !== FALSE) { // Usando ';' como delimitador
        $linha_numero++;
        
        // Ignorar a primeira linha (cabeçalho)
        if ($linha_numero == 1) {
            continue;
        }

        // Verificar se a linha está vazia
        if (empty(array_filter($linha))) {
            continue; // Ignorar linha vazia
        }
        
        // Verificar se a linha tem pelo menos 16 colunas e se não está vazia
        if (count($linha) >= 16 && array_filter($linha)) {
            
            $idProduto = trim($linha[0]);
            $descricao = trim($linha[1]);
            $ncm = trim($linha[3]);
            $sitTrib = trim($linha[4]);
            $aliqIcms = trim($linha[7]);
            $cstPisEntrada = trim($linha[9]);
            $perPis = trim($linha[10]);
            $cstCofinsEntrada = trim($linha[11]);
            $perCofins = trim($linha[12]);
            $cstPisSaida = trim($linha[13]);
            $perPisSai = trim($linha[14]);
            $cstCofinsSaida = trim($linha[15]);
            $perCofinsSai = trim($linha[16]);
            
            
            
            // Substituir vírgulas por pontos
            $perPis = str_replace(',', '.', $perPis);
            $perCofins = str_replace(',', '.', $perCofins);
            $perPisSai = str_replace(',', '.', $perPisSai);
            $perCofinsSai = str_replace(',', '.', $perCofinsSai);
            
            // Verificar se os campos principais não estão vazios
            if (!empty($idProduto) && !empty($descricao) && !empty($ncm) && !empty($sitTrib)) {
                try {
                    // Buscar IDCLASSFISCAL pelo CLASSIFICACAO
                    $stmt = $dbh->prepare("SELECT IDCLASSFISCAL FROM CLASSFISCAL WHERE CLASSIFICACAO = ?");
                    $stmt->execute([$ncm]); // Corrigir a variável aqui
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result) {
                        $idClassFiscal = $result['IDCLASSFISCAL'];
                        
                        // Atualizar a tabela PRODUTOS
                        $stmt = $dbh->prepare("
                            UPDATE PRODUTOS 
                            SET CLASSFISCAL = ?, 
                                SITTRIB = ?, 
                                ALIQICMS = ?, 
                                CST_PIS_SAIDA = ?, 
                                PER_PIS_SAI = ?, 
                                CST_COFINS_SAIDA = ?, 
                                PER_COFINS_SAI = ?
                            WHERE IDPRODUTO = ?;

                        ");
                        $stmt->execute([
                            $idClassFiscal, $sitTrib, $aliqIcms, 
                            $cstPisSaida, $perPisSai, $cstCofinsSaida, $perCofinsSai, 
                            $idProduto
                        ]);

                        $linhas_processadas++;
                    } else {
                        $linhas_erro[] = "Linha $linha_numero: NCM não encontrado.";
                    }
                } catch (PDOException $e) {
                    $linhas_erro[] = "Linha $linha_numero: Erro ao atualizar produto. " . $e->getMessage();
                }
            } else {
                $erros_campos[] = "Linha $linha_numero: Campos principais faltando (IDProduto, Descrição, NCM, SitTrib).";
            }
        } else {
            $erros_campos[] = "Linha $linha_numero: Linha com menos de 16 colunas ou vazia.";
        }
    }
    fclose($handle);
}

// Exibir resumo
echo '<div style="background-color: #d4edda; padding: 1rem; border-radius: 5px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-top: 10px; color: #155724; border-color: #c3e6cb;">';
echo "<p>Total de linhas processadas: $linhas_processadas</p>";
if (!empty($linhas_erro)) {
    echo "<p>Erros:</p><ul>";
    foreach ($linhas_erro as $erro) {
        echo "<li>$erro</li>";
    }
    echo "</ul>";
}
if (!empty($erros_campos)) {
    echo "<p>Erros de Campos:</p><ul>";
    foreach ($erros_campos as $erro_campo) {
        echo "<li>$erro_campo</li>";
    }
    echo "</ul>";
}
echo '</div>';
?>
