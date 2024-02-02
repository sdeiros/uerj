<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pebit";

require 'vendor/autoload.php'; // Certifique-se de carregar o autoload do dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

// Configurações da API do Google Sheets
$spreadsheetId = '1WAxnZPqgbTAvqtTampi34xDkL-3-W13mnbIo2ACcIm4';
$apiKey = 'AIzaSyDq1_3tOQdeM3besaeg1-O4coztRsL3FZY';

// Função para obter os dados da planilha
function getSheetData($spreadsheetId, $apiKey)
{
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/A2:H?key={$apiKey}";
    $data = file_get_contents($url);
    return json_decode($data, true);
}

// Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar a conexão
if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados: " . $conn->connect_error);
}

// Verificar se o botão de atualização foi acionado
if (isset($_POST['atualizar'])) {
    // Obter dados da planilha
    $sheetData = getSheetData($spreadsheetId, $apiKey);

    if ($sheetData === null) {
        echo "Erro ao obter dados da planilha.";
    } else {
        // Limpar a tabela antes de inserir dados atualizados
        $conn->query("TRUNCATE TABLE estagiarios");

        // Inserir dados no banco de dados
        foreach ($sheetData['values'] as $row) {
            $nome = $row[0];
            $email = $row[1];
            $convenio = $row[2];

            // Convertendo a data para o formato desejado
            $dataInicio = DateTime::createFromFormat('d/m/Y', $row[3])->format('Y-m-d');
            $dataTermino = DateTime::createFromFormat('d/m/Y', $row[4])->format('Y-m-d');

            // Obtendo o horário da coluna G
            $horarioOriginal = $row[6];

            // Construindo o formato desejado (HH:mm)
            $horarioFormatado = $horarioOriginal . 'hs';

            $cpf = $row[5];
            $area = $row[7]; // Alterando para pegar a coluna "ATUAÇÃO"

            $sql = "INSERT INTO estagiarios (Nome, Email, Convenio, DataInicio, DataTermino, Horario, CPF, Area, DiferencaHoras) 
            VALUES ('$nome', '$email', '$convenio', '$dataInicio', '$dataTermino', '$horarioFormatado', '$cpf', '$area', '$diferencaHoras')";

            if ($conn->query($sql) === FALSE) {
                echo "Erro ao inserir dados: " . $conn->error;
            }
        }

        echo "Dados atualizados com sucesso!";
    }
}

// Função para obter os estagiários no sistema
function getEstagiarios()
{
    global $conn;
    $result = $conn->query("SELECT * FROM estagiarios");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Função para gerar o arquivo PDF
function gerarDeclaracao($dados)
{
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);

    $html = '<html>';
    $html .= '<head>';
    $html .= '<style>';
    $html .= 'body {margin-left: 40px; margin-right: 30px, margin-top: 40px; margin-bottom: 30px;}';
    $html .= 'header { text-align: center; font-family: "Times New Roman", serif; font-size: 13px; }'; // Estilo do cabeçalho
    $html .= 'header img { max-width: 14%; height: auto;  margin-bottom: 10px; }'; // Estilo para garantir que a imagem não ultrapasse a largura do cabeçalho
    $html .= 'h1, p { margin-bottom: 10px; }'; // Adicionando um espaçamento inferior de 10px a títulos e parágrafos
    $html .= '</style>';
    $html .= '<link rel="preconnect" href="https://fonts.googleapis.com">';
    $html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    $html .= '<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500&display=swap" rel="stylesheet">';
    $html .= '</head>';
    $html .= '<body>';

    // Cabeçalho
    $html .= '<header>';
    $html .= '<img src="data:image/jpeg;base64,' . base64_encode(file_get_contents('https://upload.wikimedia.org/wikipedia/commons/thumb/e/e7/Brasao_RJ.jpg/605px-Brasao_RJ.jpg')) . '" alt="Brasão do Estado do Rio de Janeiro">';
    $html .= '<p>Governo do Estado do Rio de Janeiro<br>';
    $html .= 'Secretaria de Ciências e Tecnologia<br>';
    $html .= 'Universidade do Estado do Rio de Janeiro</p>';
    $html .= '</header>';

    // Conteúdo da declaração
    $html .= '<h1 style="font-family: \'EB Garamond\', serif; font-size: 20px; text-align: center; letter-spacing: 2px;"><BR><BR><BR><BR><BR><BR><BR>DECLARAÇÃO<BR><BR></h1>';
    
    foreach ($dados as $estagiario) {
        // Formatar as datas aqui
        $dataInicioFormatada = DateTime::createFromFormat('Y-m-d', $estagiario['DataInicio'])->format('d/m/Y');
        $dataTerminoFormatada = DateTime::createFromFormat('Y-m-d', $estagiario['DataTermino'])->format('d/m/Y');
        $dataHoje = date('d/m/Y');
    
        // Calcular a diferença de horas considerando o horário de início e término
        $dataInicioObj = new DateTime($estagiario['DataInicio'] . ' ' . $estagiario['Horario']);
        $dataTerminoObj = new DateTime($estagiario['DataTermino'] . ' ' . $estagiario['Horario']);
    
        // Calcular a diferença de horas em termos absolutos (considerando horário de término posterior ao de início)
        $diferencaHoras = $dataTerminoObj->diff($dataInicioObj)->h;
    
        $html .= '<p style="font-family: \'EB Garamond\', serif; font-size: 16px; font-weight: 400;">Declaramos que ' . $estagiario['Nome'] . ' CPF: ' . $estagiario['CPF'] . ' iniciou em ' . $dataInicioFormatada . ' com término em ' . $dataTerminoFormatada . ' no estágio na área ' . $estagiario['Area'] . ', em função do Convênio ' . $estagiario['Convenio'] . ' com carga horária diária de ' . $diferencaHoras . ' horas, de segunda a sexta-feira, no horário de ' . $estagiario['Horario'] . '.</p><BR><BR>';
    }
    
    
    $html .= '<p style="font-family: \'EB Garamond\', serif; font-size: 14px; font-weight: 400;>Rio de Janeiro,' . $dataHoje . '</p>';
    $html .= '<BR><BR><BR><BR><BR><BR><BR><hr style="border-color: #000; max-width: 50%">';

    // Informações adicionais
    $html .= '<p style="font-size: 16px; font-weight: 400; text-align: center; margin-bottom: -10px;"><b>_____________________________<b></p>';
    $html .= '<p style="font-family: \'EB Garamond\', serif; font-size: 14px; font-weight: 400; text-align: center;">';
    $html .= 'Patrícia Gomes Ferreira da Costa<br>';
    $html .= 'Diretora do CETREINA/UERJ<br>';
    $html .= 'MATR. 41.962-2 / ID 5142617-0<br>';
    $html .= '</p>';

    $html .= '</body>';
    $html .= '</html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Salvar o PDF no servidor
    $output = $dompdf->output();
    file_put_contents('declaracao_estagio.pdf', $output);

    // Redirecionar para o arquivo PDF salvo
    header('Location: declaracao_estagio.pdf');
    exit();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Dados e Gerar Declaração</title>
</head>

<body>
    <form method="post">
        <button type="submit" name="atualizar">Atualizar Dados</button>
    </form>

    <hr>

    <h2>Estagiários no Sistema</h2>

    <?php
    $estagiarios = getEstagiarios();

    if ($estagiarios) {
        foreach ($estagiarios as $estagiario) {
            echo '<p>' . $estagiario['Nome'] . ' - <a href="gerar_declaracao.php?id=' . $estagiario['id'] . '">Gerar Declaração</a></p>';
        }
    } else {
        echo 'Nenhum estagiário no sistema.';
    }
    ?>

</body>

</html>