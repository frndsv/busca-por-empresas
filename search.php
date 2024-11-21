
<?php 
include("database.php");


if (isset($_GET['input'])){
    $input = $_GET['input'];   // Recebimento do valor digitado pelo usuario
    $input = trim($input);    // Remove espaço branco no inicio e no final
    $filtered_input = preg_replace('/\s+/', ' ', $input); // Se houver mais de dois espaços, passa a ser só um
    $filtered_input = Normalizer::normalize($filtered_input, Normalizer::NFD); // Remoção de acentos
    $filtered_input = preg_replace('/[\x{0300}-\x{036F}]/u', '', $filtered_input); // Remoção de acentos
    $filtered_input = htmlspecialchars($filtered_input, ENT_QUOTES, 'UTF-8'); // Sanitização para evitar a inserção de html 
    $original_input = $filtered_input; // Salva o valor original para usar depois
    $size = mb_strlen($filtered_input);

    $rows_per_page = 15;
    $page = isset($_GET['page-nr']) ? intval($_GET['page-nr']) : 1;
    $start = ($page - 1) * $rows_per_page;
 
    // Guarda em um variavel uma filtragem para buscar por um cnpj 
    $cnpj_input = str_replace('.', '', $filtered_input); 
    $cnpj_input = str_replace('-', '', $cnpj_input);
    $cnpj_input = str_replace('/', '', $cnpj_input);
    $cnpj_input = str_replace(' ', '', $cnpj_input);

    // Testa se for pequeno demais
    if($size < 2) {
        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Seja mais especifico ou tente novamente! </h6> </div> </section>";
    } else {
        // Se depois de remover caracteres que poderiam existir em um cnpj, ele testa sem sem eles, o valor é um numero puro. Se for, então é um cnpj e fará conferencia com os campos relacionados a cnpj
        if(ctype_digit($cnpj_input)) {
            $size = mb_strlen($cnpj_input);
            // Teste se o usuario digitou uma parte do cnpj
            if ($size <= 8) {
                $stmt = $conn->prepare("SELECT COUNT(*) AS total 
                FROM rf_empresas a
                LEFT JOIN rf_estabelecimentos b ON b.cnpj = a.cnpj
                LEFT JOIN rf_municipio c ON c.cod_municipio = b.municipio
                WHERE a.cnpj = ?");
                $stmt->bind_param("s", $cnpj_input);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row_count = $result->fetch_assoc();
                    $num_rows = $row_count['total'];
                    $pages = ceil($num_rows / $rows_per_page);
            
                    if ($num_rows > 1000) {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                    } elseif ($num_rows > 0) {
                        $stmt = $conn->prepare("SELECT DISTINCT b.cnpj, b.cnpj_ordem, b.cnpj_dv, a.razao_social, b.nome_fantasia, c.nome_municipio, b.uf, b.situacao_cadastral
                            FROM rf_empresas a
                            LEFT JOIN rf_estabelecimentos b ON b.cnpj = a.cnpj
                            LEFT JOIN rf_municipio c ON c.cod_municipio = b.municipio
                            WHERE a.cnpj = ? ORDER BY b.situacao_cadastral, c.nome_municipio, a.cnpj  LIMIT ?, ?");
                        $stmt->bind_param("sii", $cnpj_input, $start, $rows_per_page);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            echo "<div class='results-count'> <h1> Encontramos $num_rows " . (($num_rows > 1) ? "resultados" : "resultado") . " para essa pesquisa... </h1></div>";
                            echo "<table class='tabela'>
                                    <thead>
                                        <tr>
                                            <th> CNPJ</th>
                                            <th>Razão Social</th>
                                            <th>Nome Fantasia</th>
                                            <th>Localidade</th>
                                            <th> Status </th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                            while ($row = $result->fetch_assoc()) {
                                $cnpj = $row['cnpj'] . $row['cnpj_ordem'] . $row['cnpj_dv'];
                                $cnpj = substr($cnpj, 0, 2) . '.' .
                                        substr($cnpj, 2, 3) . '.' .
                                        substr($cnpj, 5, 3) . '/' .
                                        substr($cnpj, 8, 4) . '-' .
                                        substr($cnpj, 12, 2);
                                
                                $razao = $row['razao_social'];
                                $nome = $row['nome_fantasia'];
                                $localidade = $row['nome_municipio'] . ' - ' . $row['uf'];

                                $situacao = match ($row['situacao_cadastral']) {
                                    '01' => "NULA",
                                    '02' => "ATIVA",
                                    '03' => "SUSPENSA",
                                    '04' => "INAPTA",
                                    '08' => "BAIXADA",
                                    default => "BAIXADA"
                                };
                                
                                $details_url = "details.php?cnpj=" . urlencode($cnpj);
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                        <td>$cnpj</td>
                                        <td>$razao</td>
                                        <td>$nome</td>
                                        <td>$localidade</td>
                                        <td>$situacao</td>
                                      </tr>";
                            }
                            echo "</tbody></table>";
                            
                            include("pagination.php");
                            $variable = '1';
                            showPagination($variable, $pages, $original_input, $cnpj_input, $page);

                            
                        } else {
                            echo "<section class='result-alert'><div class='alert-container'><h6 class='alert'> Ocorreu um erro ao buscar os resultados! </h6></div></section>";
                        }
                    } else {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                    }
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                }            
                $stmt->close(); 
            // Teste se o usuario digitou uma parte do cnpj (o inicio e a ordem)
            } else if ($size <= 12) {
                $base_cnpj = substr($cnpj_input, 0, 8); 
                $cnpj_ordem = substr($cnpj_input, 8);
                $stmt = $conn->prepare("SELECT COUNT(*) AS total 
                FROM rf_estabelecimentos a
                LEFT JOIN rf_empresas b ON b.cnpj = a.cnpj
                LEFT JOIN rf_municipio c ON c.cod_municipio = a.municipio
                WHERE a.cnpj = ? AND a.cnpj_ordem = ?");
                $stmt->bind_param("ss", $base_cnpj, $cnpj_ordem);

                if ($stmt->execute())  {
                    $result = $stmt->get_result();
                    $row_count = $result->fetch_assoc();
                    $num_rows = $row_count['total'];
                    $pages = ceil($num_rows / $rows_per_page);
                    
                    if($num_rows > 1000) {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                    } else if ($num_rows > 0) {
                        $stmt = $conn->prepare("SELECT DISTINCT a.cnpj, a.cnpj_ordem, a.cnpj_dv, b.razao_social, a.nome_fantasia, c.nome_municipio, a.uf, a.situacao_cadastral
                            FROM rf_estabelecimentos a
                            LEFT JOIN rf_empresas b ON b.cnpj = a.cnpj
                            LEFT JOIN rf_municipio c ON c.cod_municipio = a.municipio
                            WHERE a.cnpj = ? AND a.cnpj_ordem = ? ORDER BY a.situacao_cadastral, c.nome_municipio, a.cnpj, a.cnpj_ordem, a.cnpj_dv  LIMIT ?, ?");
                        $stmt->bind_param("ssii", $base_cnpj, $cnpj_ordem, $start, $rows_per_page);

                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            echo "<table class='tabela'>
                                    <thead>
                                        <tr>
                                            <th> CNPJ</th>
                                            <th>Razão Social</th>
                                            <th>Nome Fantasia</th>
                                            <th>Localidade</th>
                                            <th> Status </th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                            while ($row = $result->fetch_assoc()) {
                                $cnpj = $row['cnpj'] . $row['cnpj_ordem'] . $row['cnpj_dv'];
                                $cnpj = substr($cnpj, 0, 2) . '.' .
                                        substr($cnpj, 2, 3) . '.' .
                                        substr($cnpj, 5, 3) . '/' .
                                        substr($cnpj, 8, 4) . '-' .
                                        substr($cnpj, 12, 2);
                                
                                $razao = $row['razao_social'];
                                $nome = $row['nome_fantasia'];
                                $localidade = $row['nome_municipio'] . ' - ' . $row['uf'];

                                $situacao = match ($row['situacao_cadastral']) {
                                    '01' => "NULA",
                                    '02' => "ATIVA",
                                    '03' => "SUSPENSA",
                                    '04' => "INAPTA",
                                    '08' => "BAIXADA",
                                    default => "BAIXADA"
                                };
                                
                                $details_url = "details.php?cnpj=" . urlencode($cnpj);
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                        <td>$cnpj</td>
                                        <td>$razao</td>
                                        <td>$nome</td>
                                        <td>$localidade</td>
                                        <td>$situacao</td>
                                    </tr>";
                            }
                            echo "</tbody></table>";
                            
                            include("pagination.php");
                            $variable = '1';
                            showPagination($variable, $pages, $original_input, $cnpj_input, $page);
                            
                        } else {
                            echo "<section class='result-alert'><div class='alert-container'><h6 class='alert'> Ocorreu um erro ao buscar os resultados! </h6></div></section>";
                        }
                    } else {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                    }
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                }
            // Se o usuario digitou o cnpj completo ou algo numerico diferente 
            } else {
                $base_cnpj = substr($cnpj_input, 0, 8);   // A partir do 0, quero os próximos 8  
                $cnpj_ordem = substr($cnpj_input, 8, 4);  // A paritr do 8, quero os próximos 4   
                $cnpj_dv = substr($cnpj_input, 12, 2); // A partir do 12, queros os próximos 2

                $stmt = $conn->prepare("SELECT COUNT(*) AS total 
                FROM rf_estabelecimentos    a
                LEFT JOIN rf_empresas     b ON b.cnpj = a.cnpj
                LEFT JOIN rf_municipio        c ON c.cod_municipio = a.municipio
                WHERE a.cnpj = ? AND a.cnpj_ordem = ? AND a.cnpj_dv = ?");
                $stmt->bind_param("sss", $base_cnpj, $cnpj_ordem, $cnpj_dv);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row_count = $result->fetch_assoc();
                    $num_rows = $row_count['total'];
                    $pages = ceil($num_rows / $rows_per_page);
                    if ($num_rows > 1000)  {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                    } else if ($num_rows > 0) {
                        $stmt = $conn->prepare("SELECT DISTINCT a.cnpj, a.cnpj_ordem, a.cnpj_dv, b.razao_social, a.nome_fantasia, c.nome_municipio, a.uf, a.situacao_cadastral
                        FROM rf_estabelecimentos a
                        LEFT JOIN rf_empresas b ON b.cnpj = a.cnpj
                        LEFT JOIN rf_municipio c ON c.cod_municipio = a.municipio
                        WHERE a.cnpj = ? AND a.cnpj_ordem = ? AND a.cnpj_dv = ? ORDER BY a.situacao_cadastral, c.nome_municipio, a.cnpj, a.cnpj_ordem, a.cnpj_dv LIMIT ?, ?");
                        $stmt->bind_param("sssii", $base_cnpj, $cnpj_ordem, $cnpj_dv, $start, $rows_per_page);
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            echo "<table class='tabela'>
                                    <thead>
                                        <tr>
                                            <th> CNPJ</th>
                                            <th>Razão Social</th>
                                            <th>Nome Fantasia</th>
                                            <th>Localidade</th>
                                            <th> Status </th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                            while ($row = $result->fetch_assoc()) {
                                $cnpj = $row['cnpj'] . $row['cnpj_ordem'] . $row['cnpj_dv'];
                                $cnpj = substr($cnpj, 0, 2) . '.' .
                                        substr($cnpj, 2, 3) . '.' .
                                        substr($cnpj, 5, 3) . '/' .
                                        substr($cnpj, 8, 4) . '-' .
                                        substr($cnpj, 12, 2);
                                
                                $razao = $row['razao_social'];
                                $nome = $row['nome_fantasia'];
                                $localidade = $row['nome_municipio'] . ' - ' . $row['uf'];

                                $situacao = match ($row['situacao_cadastral']) {
                                    '01' => "NULA",
                                    '02' => "ATIVA",
                                    '03' => "SUSPENSA",
                                    '04' => "INAPTA",
                                    '08' => "BAIXADA",
                                    default => "BAIXADA"
                                };
                                
                                $details_url = "details.php?cnpj=" . urlencode($cnpj);
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                        <td>$cnpj</td>
                                        <td>$razao</td>
                                        <td>$nome</td>
                                        <td>$localidade</td>
                                        <td>$situacao</td>
                                      </tr>";
                            }
                            echo "</tbody></table>";
                            include("pagination.php");
                            $variable = '1';
                            showPagination($variable, $pages, $original_input, $cnpj_input, $page);
                        } else {
                            echo "<section class='result-alert'><div class='alert-container'><h6 class='alert'> Ocorreu um erro ao buscar os resultados! </h6></div></section>";
                        }
                    } else {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                    }
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                }
            }

        // Se não for só númerico, então vai ser uma razao social ou nome fantasia
        } else {
            // Conferencia 1 - Razao Social 
            $percent_input = str_replace(' ', '%', $filtered_input);
            $percent_input = $percent_input . '%';
            $filtered_input = $filtered_input . '%';

            $stmt = $conn->prepare("SELECT COUNT(*) as total
                    FROM  rf_empresas             a
                    LEFT JOIN rf_estabelecimentos  b ON b.cnpj = a.cnpj
                    LEFT JOIN rf_municipio        c ON c.cod_municipio = b.municipio
                    WHERE a.razao_social = ? OR a.razao_social LIKE ? OR a.razao_social LIKE ? 
            ");
            $stmt->bind_param("sss", $original_input, $filtered_input, $percent_input);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row_count = $result->fetch_assoc();
                $num_rows = $row_count['total'];
                $pages = ceil($num_rows / $rows_per_page);

                if ($num_rows > 10000) {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                } elseif($num_rows > 0) {
                    $stmt = $conn->prepare("SELECT DISTINCT b.cnpj, b.cnpj_ordem, b.cnpj_dv, a.razao_social, b.nome_fantasia, c.nome_municipio, b.uf, b.situacao_cadastral
                    FROM  rf_empresas             a
                    LEFT JOIN rf_estabelecimentos  b ON b.cnpj = a.cnpj
                    LEFT JOIN rf_municipio        c ON c.cod_municipio = b.municipio
                    WHERE a.razao_social = ? OR a.razao_social LIKE ? OR a.razao_social LIKE ? ORDER BY  b.situacao_cadastral, c.nome_municipio, b.cnpj, b.cnpj_ordem, b.cnpj_dv LIMIT ?, ?");

                    $stmt->bind_param("sssii", $original_input, $filtered_input, $percent_input, $start, $rows_per_page);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        echo "<table class='tabela'>
                                        <thead>
                                            <tr>
                                                <th> CNPJ</th>
                                                <th>Razão Social</th>
                                                <th>Nome Fantasia</th>
                                                <th>Localidade</th>
                                                <th> Status </th>
                                            </tr>
                        </thead>
                        <tbody>";

                        while ($row = $result->fetch_assoc()) {
                            $cnpj = $row['cnpj'] . $row['cnpj_ordem'] . $row['cnpj_dv'];
                            $cnpj = substr($cnpj, 0, 2) . '.' .
                                    substr($cnpj, 2, 3) . '.' .
                                    substr($cnpj, 5, 3) . '/' .
                                    substr($cnpj, 8, 4) . '-' .
                                    substr($cnpj, 12, 2);
                            $razao = $row['razao_social'];
                            $nome = $row['nome_fantasia'];
                            $localidade = $row['nome_municipio'] . ' - ' . $row['uf'];

                            $situacao = match ($row['situacao_cadastral']) {
                                '01' => "NULA",
                                '02' => "ATIVA",
                                '03' => "SUSPENSA",
                                '04' => "INAPTA",
                                '08' => "BAIXADA",
                                default => "BAIXADA"
                            };
                            
                            $details_url = "details.php?cnpj=" . urlencode($cnpj);
                                    echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                            <td>$cnpj</td>
                                            <td>$razao</td>
                                            <td>$nome</td>
                                            <td>$localidade</td>
                                            <td>$situacao</td>
                            </tr>";
                        }
                        echo "</tbody>
                        </table>";
                        include("pagination.php");
                        $variable = '2';
                        showPagination($variable, $pages, $original_input, $cnpj_input, $page);
                        // Da a opção ao usuario de ver outros resultados, caso os fornecidos não sejam suficientes
                        include("more.php");
                    } else {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                    }
                } else {

                    // Conferencia 2 - Nome fantasia
                    $percent_input = str_replace(' ', '%', $filtered_input);
                    $percent_input = $percent_input . '%';
                    $filtered_input = $filtered_input . '%';
                    $stmt = $conn->prepare("SELECT COUNT(*) as total
                    FROM  rf_estabelecimentos     a
                    LEFT JOIN rf_empresas         b ON b.cnpj = a.cnpj
                    LEFT JOIN rf_municipio        c ON c.cod_municipio = a.municipio
                    WHERE a.nome_fantasia = ? OR a.nome_fantasia LIKE ? OR a.nome_fantasia LIKE ? ");
                    $stmt->bind_param("sss", $original_input, $filtered_input, $percent_input);

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        $row_count = $result->fetch_assoc();
                        $num_rows = $row_count['total'];
                        $pages = ceil($num_rows / $rows_per_page);

                        if ($num_rows > 1000) {
                            echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                        } elseif($num_rows > 0) {

                            $stmt = $conn->prepare("SELECT DISTINCT a.cnpj, a.cnpj_ordem, a.cnpj_dv, b.razao_social, a.nome_fantasia, c.nome_municipio, a.uf, a.situacao_cadastral
                            FROM  rf_estabelecimentos     a
                            LEFT JOIN rf_empresas         b ON b.cnpj = a.cnpj
                            LEFT JOIN rf_municipio        c ON c.cod_municipio = a.municipio
                            WHERE a.nome_fantasia = ? OR a.nome_fantasia LIKE ? OR a.nome_fantasia LIKE ? ORDER BY a.situacao_cadastral, c.nome_municipio, a.cnpj, a.cnpj_ordem, a.cnpj_dv LIMIT ?, ?");

                            $stmt->bind_param("sssii", $original_input, $filtered_input, $percent_input, $start, $rows_per_page);

                            if ($stmt->execute()) {
                                $result = $stmt->get_result();

                                echo "<table class='tabela'>
                                        <thead>
                                            <tr>
                                                <th> CNPJ</th>
                                                <th>Razão Social</th>
                                                <th>Nome Fantasia</th>
                                                <th>Localidade</th>
                                                <th> Status </th>
                                            </tr>
                                </thead>
                                <tbody>";

                                while ($row = $result->fetch_assoc()) {
                                    $cnpj = $row['cnpj'] . $row['cnpj_ordem'] . $row['cnpj_dv'];
                                    $cnpj = substr($cnpj, 0, 2) . '.' .
                                            substr($cnpj, 2, 3) . '.' .
                                            substr($cnpj, 5, 3) . '/' .
                                            substr($cnpj, 8, 4) . '-' .
                                            substr($cnpj, 12, 2);
                                    
                                    $razao = $row['razao_social'];
                                    $nome = $row['nome_fantasia'];
                                    $localidade = $row['nome_municipio'] . ' - ' . $row['uf'];

                                    $situacao = match ($row['situacao_cadastral']) {
                                        '01' => "NULA",
                                        '02' => "ATIVA",
                                        '03' => "SUSPENSA",
                                        '04' => "INAPTA",
                                        '08' => "BAIXADA",
                                        default => "BAIXADA"
                                    };

                                    $details_url = "details.php?cnpj=" . urlencode($cnpj);
                                    echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                            <td>$cnpj</td>
                                            <td>$razao</td>
                                            <td>$nome</td>
                                            <td>$localidade</td>
                                            <td>$situacao</td>
                                    </tr>";
                                }
                                echo "</tbody></table>";
                                include("pagination.php");
                                $variable = '2';
                                showPagination($variable, $pages, $original_input, $cnpj_input, $page);
                                // Da a opção ao usuario de ver outros resultados, caso os fornecidos não sejam suficientes
                                include("more.php");
                            } else {
                                echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                            }
                        } else {
                            echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                        }
                    } else {
                        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                    }
                }
            }
        }
    }
}
