<?php
include("database.php");
include_once("header.php");

if (!isset($_GET['cnpj'])) {
    header("Location: index.php");
    exit(); 
} else {
    $cnpj = htmlspecialchars($_GET['cnpj'], ENT_QUOTES, 'UTF-8');
    $cnpj = preg_replace('/[.\-\/ ]/', '', $cnpj); 
    $size = mb_strlen($cnpj); 
    
    if ($size != 14) {
        header("Location: index.php");
        exit(); 
    } else {
        $formatted_cnpj = substr($cnpj, 0, 2) . '.' .
                substr($cnpj, 2, 3) . '.' .
                substr($cnpj, 5, 3) . '/' .
                substr($cnpj, 8, 4) . '-' .
                substr($cnpj, 12, 2);

        $base_cnpj = substr($cnpj, 0, 8);
        $cnpj_ordem = substr($cnpj, 8, 4);
        $cnpj_dv = substr($cnpj, 12, 2); 

        $rows_per_page = 10;

        $page_socios = isset($_GET['page-nr-socios']) ? intval($_GET['page-nr-socios']) : 1;
        $start_socios = ($page_socios - 1) * $rows_per_page;

        $page_filiais = isset($_GET['page-nr-filiais']) ? intval($_GET['page-nr-filiais']) : 1;
        $start_filiais = ($page_filiais - 1) * $rows_per_page;

    

        $stmt = $conn->prepare(" SELECT COUNT(*) AS total
        FROM rf_empresas a
            LEFT JOIN rf_estabelecimentos b ON b.cnpj = a.cnpj
            LEFT JOIN rf_natureza_juridica c ON c.cod_natureza = a.natureza_juridica
            LEFT JOIN rf_cnae d ON d.cod_cnae = b.cnae_fiscal_principal
            LEFT JOIN rf_municipio g ON g.cod_municipio = b.municipio
            WHERE b.cnpj = ? AND b.cnpj_ordem = ? AND b.cnpj_dv = ?");
        $stmt->bind_param("sss", $base_cnpj, $cnpj_ordem, $cnpj_dv);
        
        $stmt = $conn->prepare("SELECT a.cnpj, b.cnpj_ordem, b.cnpj_dv, a.razao_social, b.cnae_fiscal_principal, 
            d.desc_cnae, c.descricao_natureza, g.nome_municipio, b.uf, b.ddd_fax, b.fax,
            b.tipo_logradouro, b.logradouro, b.numero, b.complemento, 
            b.bairro, b.cep, b.ident_matriz_filial, b.nome_fantasia, 
            b.situacao_cadastral, b.data_situacao_cadastral, 
            b.data_inicio_atividade, b.correio_eletronico, 
            b.ddd1, b.telefone1, b.ddd2, b.telefone2
          FROM rf_empresas a
          LEFT JOIN rf_estabelecimentos b ON b.cnpj = a.cnpj
          LEFT JOIN rf_natureza_juridica c ON c.cod_natureza = a.natureza_juridica
          LEFT JOIN rf_cnae d ON d.cod_cnae = b.cnae_fiscal_principal
          LEFT JOIN rf_municipio g ON g.cod_municipio = b.municipio
          WHERE b.cnpj = ? AND b.cnpj_ordem = ? AND b.cnpj_dv = ?");
        $stmt->bind_param("sss", $base_cnpj, $cnpj_ordem, $cnpj_dv);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $empresa = $result->fetch_assoc();
            $cep =  substr($empresa['cep'], 0, 5) . '-' . 
                    substr($empresa['cep'], 5, 3);
    
            $logradouro = $empresa['tipo_logradouro'] . " " . $empresa['logradouro'] . ", " . $empresa['numero'] .
            ", " . $empresa["complemento"];
            $bairro = $empresa['bairro'];
            $data_inicio = (new DateTime($empresa['data_inicio_atividade']))->format('d/m/y');
            $data_situacao = (new DateTime($empresa['data_situacao_cadastral']))->format('d/m/y');
    
            if($empresa['ddd1']) {
                $telefone = '(' . $empresa['ddd1'] .  ')' . ' ' .$empresa['telefone1'];
                $telefone = substr($telefone, 0, 9) . '-' . 
                            substr($telefone, 9, 4);
            } else {
                if($empresa['ddd2']) {
                    $telefone = '(' . $empresa['ddd2'] .  ')' . ' ' .$empresa['telefone2'];
                    $telefone = substr($telefone, 0, 9) . '-' . 
                                substr($telefone, 9, 4);
                } else {
                    $telefone = "";
                }
            }
    
            $fax = $empresa['ddd_fax'] && $empresa['fax'] ? '(' . $empresa['ddd_fax'] .  ')' . ' ' .$empresa['fax'] : "";
            $correio_eletronico = $empresa['correio_eletronico'] ? $empresa['correio_eletronico']  : "";
    
            $situacao = match ($empresa['situacao_cadastral']) {
                '01' => "NULA",
                '02' => "ATIVA",
                '03' => "SUSPENSA",
                '04' => "INAPTA",
                '08' => "BAIXADA",
                default => "BAIXADA"
            };
    
            $identidade = $empresa['ident_matriz_filial'] == 01 ? "MATRIZ" : "FILIAL";
            $descricao = $empresa['desc_cnae'] ?: "Ocorreu um erro";
    
            $localidade = ($empresa['nome_municipio'] && $empresa['uf']) ? "{$empresa['nome_municipio']} - {$empresa['uf']}" : "Ocorreu um erro";
    
            $stmt_socios = $conn->prepare("SELECT COUNT(*) as total FROM rf_empresas a
                     LEFT JOIN rf_socios b ON b.cnpj = a.cnpj
                     LEFT JOIN rf_qualificacao_socio c ON c.cod_qualificacao = b.qualificacao_socio
                     LEFT JOIN rf_estabelecimentos d ON d.cnpj = a.cnpj
                     WHERE d.cnpj = ? AND d.cnpj_ordem = ? AND d.cnpj_dv = ?
            ");
            $stmt_socios->bind_param("sss", $base_cnpj, $cnpj_ordem, $cnpj_dv);
    
            if($stmt_socios->execute()) {
                $result_socios = $stmt_socios->get_result();
                $row_count_socios = $result_socios->fetch_assoc();
                $num_rows_socios = $row_count_socios['total'];
                $pages_socios = ceil($num_rows_socios / $rows_per_page);
    
                
                $stmt_socios = $conn->prepare("SELECT b.nome_socio_razao, b.cpf_cnpj_socio, 
                b.qualificacao_socio, c.desc_qualificacao 
               FROM rf_empresas a
               LEFT JOIN rf_socios b ON b.cnpj = a.cnpj
               LEFT JOIN rf_qualificacao_socio c ON c.cod_qualificacao = b.qualificacao_socio
               LEFT JOIN rf_estabelecimentos d ON d.cnpj = a.cnpj
               WHERE d.cnpj = ? AND d.cnpj_ordem = ? AND d.cnpj_dv = ?
               ORDER BY b.nome_socio_razao LIMIT ?, ?");
               $stmt_socios->bind_param("sssii", $base_cnpj, $cnpj_ordem, $cnpj_dv, $start_socios, $rows_per_page);
                if ($stmt_socios->execute()) {
                    $result_socios = $stmt_socios->get_result();
                    $socios = $result_socios->fetch_assoc();
    
                    $stmt_filiais = $conn->prepare("SELECT COUNT(*) as total
                            FROM rf_estabelecimentos a
                            LEFT JOIN rf_empresas b ON b.cnpj = a.cnpj
                            WHERE a.cnpj = ? AND a.cnpj_ordem != ? 
                            ORDER BY a.cnpj_ordem");
                    $stmt_filiais->bind_param("ss", $base_cnpj, $cnpj_ordem);
                    if($stmt_filiais->execute()){
                        $result_filiais = $stmt_filiais->get_result();
                        $row_count_filiais = $result_filiais->fetch_assoc();
                        $num_rows_filiais = $row_count_filiais['total'];
                        $pages_filiais = ceil($num_rows_filiais / $rows_per_page);
    
                        $stmt_filiais = $conn->prepare("SELECT a.cnpj, a.cnpj_ordem, a.cnpj_dv, b.razao_social, a.nome_fantasia
                        FROM rf_estabelecimentos a
                        LEFT JOIN rf_empresas b ON b.cnpj = a.cnpj
                        WHERE a.cnpj = ? AND a.cnpj_ordem != ? 
                        ORDER BY a.cnpj_ordem LIMIT ?, ?");
                        $stmt_filiais->bind_param("ssii", $base_cnpj, $cnpj_ordem, $start_filiais, $rows_per_page);
                        if($stmt_filiais->execute()) {
                            $result_filiais = $stmt_filiais->get_result();
                            $filiais = $result_filiais->fetch_assoc();
                            $num_filiais = mysqli_num_rows($result_filiais);
                        }
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
    

}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Empresa</title>
    <link rel="stylesheet" href="./styles/details.css"> 

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body>    
    <section class="details-section">
        <div class='main-details'>
            <div class='details-title'>
                <h1><?php echo $empresa['nome_fantasia'] ?: $empresa['razao_social']; ?></h1>
                <h4><strong>Razão Social:</strong> <?php echo $empresa['razao_social']; ?></h4>
                <h6><i>Essa empresa se encontra <b><?php echo $situacao; ?></b> desde <?php echo $data_situacao; ?></i></h6>
                <h4><strong>CNPJ:</strong> <?php echo $formatted_cnpj; ?></h4>
                <div class='status-matriz'>
                    <span class='status <?php echo strtolower($situacao); ?>'><?php echo $situacao; ?></span>
                    <span class='status <?php echo strtolower($identidade); ?>'><?php echo $identidade; ?></span>
                </div>
            </div>
            <div class='more-details'>
                <div class='details-table'>
                    <div class='sub-details'><p><b>Código CNAE:</b></p></div>
                    <div class='sub-details'><p><?php echo $empresa['cnae_fiscal_principal'] ?: "Erro"; ?></p></div>
                </div>
                <div class='details-table'>
                    <div class='sub-details'><p><b>Descrição código CNAE:</b></p></div>
                    <div class='sub-details'><p><?php echo $descricao; ?></p></div>
                </div>
                <div class='details-table'>
                    <div class='sub-details'><p><b>Data de início das atividades:</b></p></div>
                    <div class='sub-details'><p><?php echo $data_inicio; ?></p></div>
                </div>
                <div class='details-table'><p><?php echo $localidade; ?></p></div>                
            </div>
        </div>        
    </section>

    <section class='info-section'>
        <div class='info info-socios'>
            <div class='info-header'> <h1> Sócios </h1></div>
            <div class='socio'>
                <?php
 
                    if ($socios) {
                        if($socios['cpf_cnpj_socio'] != '' ||  $socios['nome_socio_razao'] != '') {
                            mysqli_data_seek($result_socios, 0);
                            echo "<table class='tabela'>
                            <thead>
                            <tr>
                                <th>Nome</th>   
                                <th>Qualificação</th>
                                <th> CPF ou CNPJ </th>
                            </tr>
                            </thead>
                            <tbody>";
                            while ($socios = mysqli_fetch_assoc($result_socios)) {
                                $nome = $socios['nome_socio_razao'];
                                $identificador = $socios['cpf_cnpj_socio'];
                                $qualificacao_socio = $socios['desc_qualificacao'];
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
    
                                $details_url = "socio.php?identificador=" . ($identificador ? "&identificador=" . urlencode($identificador) : "" ) . ($nome ? "&nome=" . urlencode($nome) : "" ) . "&cnpj=" . urlencode($cnpj);
    
                                echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                    <td>$nome</td>
                                    <td>$qualificacao_socio</td>
                                    <td>$identificador</td>
                                </tr>";
                            }
                            echo "</tbody></table>";
    
                            if ($pages_socios > 1) {
                                echo "<div class='pagination'>
                                        <div class='page-info'>
                                            <p>Mostrando $page_socios de $pages_socios páginas</p>
                                        </div>
                                        <div class='pagination-nav'>
                                            <a href='?cnpj=" . urlencode($cnpj) . "&page-nr-socios=1&page-nr-filiais=$page_filiais'> Primeira </a>
                                        ";
                                        
                                    if ($page_socios > 1) {
                                        echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-socios=" . ($page_socios - 1) . "&page-nr-filiais=$page_filiais'> < </a>";
                                    } else {
                                        echo "<span> < </span>";
                                    }
                                    if ($page_socios < $pages_socios) {
                                        echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-socios=" . ($page_socios + 1) . "&page-nr-filiais=$page_filiais'> > </a>";
                                    } else {
                                        echo "<span> > </span>";
                                    }
                                    echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-socios=$pages_socios&page-nr-filiais=$page_filiais'>Última</a>
                                        </div>
                                        </div>";
                            }  
                        } else {
                            echo " <p class='alert'> Essa empresa não possui um sócio... </p>";
                        }
                                              
                    } else {
                        echo " <p class='alert'> Essa empresa não possui um sócio... </p>";
                    }
                ?>
            </div>
        </div>

        <div class='info-secondary'>
            <div class='info info-endereco'>
                <div class='info-header'>
                    <h1> Endereço </h1>
                </div>
                <div class='endereco'>
                    <?php
                        echo("<p class='info-details'> <b> Logradouro: </b>$logradouro </p>"); 
                        echo("<p class='info-details'> <b> Bairro: </b> $bairro </p>");  
                        echo("<p class='info-details'> <b> Município e UF: </b> $localidade </p>");           
                    ?>

                </div>
            </div>

            <div class='info info-contato'>
                <div class='info-header'>
                    <h1> Contato </h1>
                </div>
                <div class='contato'>
                    <?php
                        if($telefone) {
                            echo("<p class='info-details'> <b> Contato: </b> $telefone </p>");
                        } else {
                            echo("<p class='info-details'>Telefone não cadastrado </p>");
                        }

                        if($correio_eletronico) {
                            echo("<p class='info-details'> <b> Correio eletrônico: </b> $correio_eletronico </p>");
                        } else {
                            echo("<p class='info-details'>  Correio eletrônico não cadastrado </p>");
                        }

                        if($fax) {
                            echo("<p class='info-details'>  <b> FAX: </b> $fax </p>");
                        } 

                        
             
                    ?>
        
                </div>
            </div>
        </div>

        <div class='info info-filial'>
            <div class='info-header'><h1> Filiais e Matriz </h1></div>

            <div class='filial-matriz'>
                <?php

                    if($num_filiais > 0) {
                        $filiais = mysqli_fetch_assoc($result_filiais);
                        mysqli_data_seek($result_filiais, 0);
                        echo "<table class='tabela'>
                        <thead>
                            <tr>
                                <th> CNPJ </th>   
                                <th> Razão Social</th>
                                <th> Nome Fantasia </th>
                                <th> Ordem </th> 
                            </tr>
                        </thead>
                        <tbody>";

                        while ($filiais = mysqli_fetch_assoc($result_filiais)) {
                            $filial_cnpj = $filiais['cnpj'] . $filiais['cnpj_ordem'] . $filiais['cnpj_dv'];

                            $filial_cnpj = substr($filial_cnpj, 0, 2) . '.' .
                                            substr($filial_cnpj, 2, 3) . '.' .
                                            substr($filial_cnpj, 5, 3) . '/' .
                                            substr($filial_cnpj, 8, 4) . '-' .
                                            substr($filial_cnpj, 12, 2);
                            $razao = $filiais['razao_social'];
                            $nome = $filiais['nome_fantasia'];
                            $details_url = "details.php?cnpj=" . urlencode($filial_cnpj);
                            if($filiais['cnpj_ordem'] == '0001') {
                                $ordem = 'Matriz';
                            } else {
                                $ordem = 'Filial';
                            }
                            echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                <td>$filial_cnpj</td>
                                <td>$razao</td>
                                <td>$nome</td>
                                <td>$ordem</td>
                            </tr>";
                        }
                        echo "</tbody></table>";

                        if ($pages_filiais > 1) {
                            echo "<div class='pagination'>
                                    <div class='page-info'>
                                        <p>Mostrando $page_filiais de $pages_filiais páginas</p>
                                    </div>
                                    <div class='pagination-nav'>
                                        <a href='?cnpj=" . urlencode($cnpj) . "&page-nr-filiais=1&page-nr-socios=$page_socios'> Primeira </a>";
                            if ($page_filiais > 1) {
                                echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-filiais=" . ($page_filiais - 1) . "&page-nr-socios=$page_socios'> < </a>";
                            } else {
                                echo "<span> < </span>";
                            }
                            if ($page_filiais < $pages_filiais) {
                                echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-filiais=" . ($page_filiais + 1) . "&page-nr-socios=$page_socios'> > </a>";
                            } else {
                                echo "<span> > </span>";
                            }
                            echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr-filiais=$pages_filiais&page-nr-socios=$page_socios'>Última</a>
                                  </div>
                                </div>";
                        }
                        
                    } else {
                        echo " <p class='alert'> Essa empresa não possui filiais e você esta visulizando sua matriz! </p>";
                    }


                
                    
                ?>      
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var rows = document.querySelectorAll('.clickable-row');
            rows.forEach(function(row) {
                row.addEventListener('click', function() {
                    var url = row.getAttribute('data-href');
                    window.location.href = url;
                });
            });
        });
    </script>
</body>
</html>
