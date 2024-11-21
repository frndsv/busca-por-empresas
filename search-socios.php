<?php 
include("database.php");


if (isset($_GET['input'])) {
  
    $input = $_GET['input'];   // Recebimento do valor digitado pelo usuario
    $input = trim($input);    // Remove espaço branco no inicio e no final
    $filtered_input = preg_replace('/\s+/', ' ', $input); // Se houver mais de dois espaços, passa a ser só um
    $filtered_input = Normalizer::normalize($filtered_input, Normalizer::NFD); // Remoção de acentos
    $filtered_input = preg_replace('/[\x{0300}-\x{036F}]/u', '', $filtered_input); // Remoção de acentos
    $filtered_input = htmlspecialchars($filtered_input, ENT_QUOTES, 'UTF-8'); // Sanitização para evitar a inserção de html 
    $original_input = $filtered_input; // Salva o valor original para usar depois
    $size = mb_strlen($original_input);
    $rows_per_page = 15;
    $page = isset($_GET['page-nr']) ? intval($_GET['page-nr']) : 1;
    $start = ($page - 1) * $rows_per_page;
 
    // Guarda em um variavel uma filtragem para buscar por um cnpj 
    $cnpj_input = str_replace('.', '', $filtered_input); 
    $cnpj_input = str_replace('-', '', $cnpj_input);
    $cnpj_input = str_replace('/', '', $cnpj_input);
    $cnpj_input = str_replace(' ', '', $cnpj_input);

    if($size < 2) {
        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Seja mais especifico ou tente novamente! </h6> </div> </section>";
    } else {
        if(ctype_digit($cnpj_input)) {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM 
            rf_socios a
            LEFT JOIN rf_qualificacao_socio b ON a.qualificacao_socio = b.cod_qualificacao
            LEFT JOIN rf_empresas c ON a.cnpj = c.cnpj
            WHERE cpf_cnpj_socio = ?");
            $stmt->bind_param("s", $cnpj_input);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row_count = $result->fetch_assoc();
                $num_rows = $row_count['total'];
                $pages = ceil($num_rows / $rows_per_page);
                if ($num_rows > 1000) {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                } else if ($num_rows > 0) {
                    $stmt = $conn->prepare("SELECT a.cnpj, c.razao_social, a.cpf_cnpj_socio, a.nome_socio_razao, b.desc_qualificacao  FROM 
                    rf_socios a
                    LEFT JOIN rf_qualificacao_socio b ON a.qualificacao_socio = b.cod_qualificacao
                    LEFT JOIN rf_empresas c ON a.cnpj = c.cnpj
                    WHERE cpf_cnpj_socio = ? ORDER BY a.nome_socio_razao, a.cpf_cnpj_socio LIMIT ?, ?");
                    $stmt->bind_param("sii", $cnpj_input,$start, $rows_per_page);
                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        echo "<table class='tabela'>
                                    <thead>
                                        <tr>
                                            <th> CNPJ </th>
                                            <th> Razão social </th>
                                            <th> Nome - Sócio </th>
                                            <th> Qualificação sócio </th>
                                            <th> CPF ou CNPJ - Sócio </th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                        while ($row = $result->fetch_assoc()) {
                            $cnpj = $row['cnpj'];
                            $razao = $row['razao_social'];
                            $identificador = $row['cpf_cnpj_socio'];
                            $identificador = substr($identificador, 0, 2) . '.' .
                                        substr($identificador, 2, 3) . '.' .
                                        substr($identificador, 5, 3) . '/' .
                                        substr($identificador, 8, 4) . '-' .
                                        substr($identificador, 12, 2);
                            $nome = $row['nome_socio_razao'];
                            $qualificacao = $row['desc_qualificacao'];
                            
                            $details_url = "details.php?cnpj=" . urlencode($identificador);
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                        <td>$cnpj</td>
                                        <td>$razao</td>
                                        <td>$nome</td>
                                        <td>$qualificacao</td>
                                        <td>$identificador</td>
                                      </tr>";
                        }
                        echo "</tbody></table>";
                        if ($pages > 1) {
                            echo "<div class='pagination'>
                                    <div class='page-info'>
                                        <p>Mostrando $page de $pages páginas</p>
                                    </div>
                                    <div class='pagination-nav'>
                                        <a href='?input=" . urlencode($cnpj_input) . "&page-nr=1'> Primeira </a>";
                            if ($page > 1) {
                                echo "<a href='?input=" . urlencode($cnpj_input) . "&page-nr=" . ($page - 1) . "'> < </a>";
                            } else {
                                echo "<span> < </span>";
                            }
                            if ($page < $pages) {
                                echo "<a href='?input=" . urlencode($cnpj_input) . "&page-nr=" . ($page + 1) . "'> > </a>";
                            } else {
                                echo "<span> > </span>";
                            }
                            echo "<a href='?input=" . urlencode($cnpj_input) . "&page-nr=$pages'>Última</a>
                                  </div>
                                </div>";
                        }
                    }
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                }
            }
        } else { 
            $name_to_check = $filtered_input;
            $checked = preg_replace('/\b(DA|DE|DOS)\b/i', '%', $name_to_check);
            $checked = preg_replace('/\b(da|de|dos)\b/i', '%', $name_to_check);
            $checked = preg_replace('/\s+/', '%', $checked); // Múltiplos espaços
            $checked = preg_replace('/%+/', '%', $checked); // Múltiplos % 
            $checked = trim($checked, '%'); // Remove % no início e no fim
            
            $percent_input = str_replace(' ', '%', $filtered_input);
            $percent_input = $percent_input . '%';
            $filtered_input = $filtered_input . '%';

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM 
            rf_socios a
            LEFT JOIN rf_qualificacao_socio b ON a.qualificacao_socio = b.cod_qualificacao
            LEFT JOIN rf_empresas c ON a.cnpj = c.cnpj
            WHERE nome_socio_razao LIKE ? OR nome_socio_razao LIKE ? OR nome_socio_razao LIKE ?");
            $stmt->bind_param("sss", $filtered_input, $percent_input, $checked);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row_count = $result->fetch_assoc();
                $num_rows = $row_count['total'];
                $pages = ceil($num_rows / $rows_per_page);
                if ($num_rows > 10000)  {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Essa busca retorna mais de 1000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
                } else if ($num_rows > 0) {
                    $stmt = $conn->prepare("SELECT a.cnpj, c.razao_social, a.cpf_cnpj_socio, a.nome_socio_razao, b.desc_qualificacao FROM
                    rf_socios a
                    LEFT JOIN rf_qualificacao_socio b ON a.qualificacao_socio = b.cod_qualificacao
                    LEFT JOIN rf_empresas c ON a.cnpj = c.cnpj
                    WHERE nome_socio_razao LIKE ?  OR nome_socio_razao LIKE ? OR nome_socio_razao LIKE ?  ORDER BY a.nome_socio_razao, a.cpf_cnpj_socio LIMIT ?, ?                  
                    ");
                    $stmt->bind_param("sssii", $filtered_input, $percent_input, $checked, $start, $rows_per_page);

                    if ($stmt->execute()) {
                        $result = $stmt->get_result();
                        echo "<table class='tabela'>
                                    <thead>
                                        <tr>
                                            <th> CNPJ </th>
                                            <th> Razão social </th>
                                            <th> Nome - Sócio </th>
                                            <th> Qualificação sócio </th>
                                            <th> CPF ou CNPJ - Sócio </th>
                                        </tr>
                                    </thead>
                                    <tbody>";
                        while ($row = $result->fetch_assoc()) {
                            $cnpj = $row['cnpj'];
                            $razao = $row['razao_social'];
                            $identificador = $row['cpf_cnpj_socio'];
                            $size = mb_strlen($identificador);
                            if($identificador > 12) {
                                $identificador = substr($identificador, 0, 2) . '.' .
                                                substr($identificador, 2, 3) . '.' .
                                                substr($identificador, 5, 3) . '/' .
                                                substr($identificador, 8, 4) . '-' .
                                                substr($identificador, 12, 2);
                            } else if(!$identificador) {
                            } else {
                                $identificador = substr($identificador, 0, 3) . '.' .
                                                substr($identificador, 3, 3) . '.' .
                                                substr($identificador, 6, 3) . '-' .
                                                substr($identificador, 9, 2);
                            }
                            
                            $nome = $row['nome_socio_razao'];
                            $qualificacao = $row['desc_qualificacao'];
                            
                            $details_url = "socio.php?identificador=" . ($identificador ? "&identificador=" . urlencode($identificador) : "" ) . ($nome ? "&nome=" . urlencode($nome) : "" ) . "&cnpj=" . urlencode($cnpj);
    
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                        <td>$cnpj</td>
                                        <td>$razao</td>
                                        <td>$nome</td>
                                        <td>$qualificacao</td>
                                        <td>$identificador</td>
                                      </tr>";
                        }
                        echo "</tbody></table>";
                        if ($pages > 1) {
                            echo "<div class='pagination'>
                                    <div class='page-info'>
                                        <p>Mostrando $page de $pages páginas</p>
                                    </div>
                                    <div class='pagination-nav'>
                                        <a href='?input=" . urlencode($original_input) . "&page-nr=1'> Primeira </a>";
                            if ($page > 1) {
                                echo "<a href='?input=" . urlencode($original_input) . "&page-nr=" . ($page - 1) . "'> < </a>";
                            } else {
                                echo "<span> < </span>";
                            }
                            if ($page < $pages) {
                                echo "<a href='?input=" . urlencode($original_input) . "&page-nr=" . ($page + 1) . "'> > </a>";
                            } else {
                                echo "<span> > </span>";
                            }
                            echo "<a href='?input=" . urlencode($original_input) . "&page-nr=$pages'>Última</a>
                                  </div>
                                </div>";
                        }
                    }
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Não encontramos nenhum dado com essas credenciais, tente novamente! </h6> </div> </section>";
                }
            }
        }
    }
}