<?php 
    include("database.php");
    $input = $_GET['input'];
    $input = trim($input);    // Remove espaço branco no inicio e no final
    $filtered_input = preg_replace('/\s+/', ' ', $input); // Se houver mais de dois espaços, passa a ser só um
    $filtered_input = Normalizer::normalize($filtered_input, Normalizer::NFD); // Remoção de acentos
    $filtered_input = preg_replace('/[\x{0300}-\x{036F}]/u', '', $filtered_input); // Remoção de acentos
    $filtered_input = htmlspecialchars($filtered_input, ENT_QUOTES, 'UTF-8'); // Sanitização para evitar a inserção de html 
    $original_input = $filtered_input; // Salva o valor original para usar depois
    $size = mb_strlen($filtered_input);

    $rows_per_page = 10;
    $page = isset($_GET['page-nr']) ? intval($_GET['page-nr']) : 1;
    $start = ($page - 1) * $rows_per_page;

    if($size < 2) {
        echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Seja mais especifico ou tente novamente utilizando mais informações ou o CNPJ! </h6> </div> </section>";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM rf_estabelecimentos WHERE MATCH (razao_social, nome_fantasia) AGAINST (? IN NATURAL LANGUAGE MODE)");

        $stmt->bind_param("s", $original_input);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row_count = $result->fetch_assoc();
            $num_rows = $row_count['total'];
            $pages = ceil($num_rows / $rows_per_page);
            if ($num_rows > 100000) {
                echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> 5 Essa busca retorna mais de 10000 resultados, seja mais especifico ou tente por um cnpj </h6> </div> </section>";
            } elseif($num_rows > 0) {
                $stmt = $conn->prepare("SELECT DISTINCT a.cnpj, a.cnpj_ordem, a.cnpj_dv, a.razao_social, a.nome_fantasia, b.nome_municipio, a.uf, a.situacao_cadastral
                FROM rf_estabelecimentos a  
                LEFT JOIN rf_municipio   b ON b.cod_municipio = a.municipio
                WHERE MATCH (a.razao_social, a.nome_fantasia) AGAINST (? IN NATURAL LANGUAGE MODE)
                ORDER BY a.situacao_cadastral, b.nome_municipio, a.cnpj, a.cnpj_ordem, a.cnpj_dv LIMIT ?, ?
                ");

                $stmt->bind_param("sii", $original_input, $start, $rows_per_page);

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
                            <td> $situacao </td>
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
                } else {
                    echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
                }
            } else {
                echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
            }
        } else {
            echo "<section class='result-alert'> <div class='alert-container'>  <h6 class='alert'> Ocorreu um erro, tente novamente! </h6> </div> </section>";
        }
    }
