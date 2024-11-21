<?php 
include("database.php");

if (isset($_GET['cnpj']) && isset($_GET['identificador']) && isset($_GET['nome'])) {  
    $identificador_filtrado = null; 
    if(isset($_GET['identificador'])) {
        $identificador_socio = htmlspecialchars($_GET['identificador'], ENT_QUOTES, 'UTF-8');
        $identificador_socio = preg_replace('/[.\-\/ ]/', '', $identificador_socio); 

        if($identificador_socio == '') {
            $identificador_socio = NULL;
        } else {
            $identificador_filtrado = $identificador_socio;
            $identificador_filtrado = str_replace('*', '', $identificador_socio); 
        }
    }

    if(isset($_GET['nome'])) {
        $nome = htmlspecialchars($_GET['nome'], ENT_QUOTES, 'UTF-8');
        $nome = preg_replace('/[.\-\/ ]/', ' ', $nome);

        if($nome == '') {
            $nome = NULL;
        }
    } else {
        $nome = NULL;
    }

    $cnpj = htmlspecialchars($_GET['cnpj'], ENT_QUOTES, 'UTF-8');
    $cnpj = preg_replace('/[.\-\/ ]/', '', $cnpj);

    $size = mb_strlen($cnpj);

    $rows_per_page = 10;
    $page = isset($_GET['page-nr']) ? intval($_GET['page-nr']) : 1;
    $start = ($page - 1) * $rows_per_page;

    if($size > 8){
        $base_cnpj = substr($cnpj, 0, 8);
        $cnpj_ordem = substr($cnpj, 8, 4);
        $cnpj_dv = substr($cnpj, 12, 2); 

        $stmt_empresa = $conn->prepare("SELECT a.nome_fantasia, b.razao_social 
        FROM rf_estabelecimentos a
        LEFT JOIN rf_empresas b ON a.cnpj = b.cnpj
        WHERE a.cnpj = ? AND a.cnpj_ordem = ? AND a.cnpj_dv = ?");
        $stmt_empresa->bind_param("sss", $base_cnpj, $cnpj_ordem, $cnpj_dv);
             
        if ($stmt_empresa->execute()) {
            $result = $stmt_empresa->get_result();

            $num_rows = $result->num_rows;

            if ($num_rows > 0) {
                $empresa = $result->fetch_assoc();

                $razao = $empresa['razao_social'];
                if($empresa['nome_fantasia']) {
                    $nome_fantasia = $empresa['nome_fantasia'];
                } else {
                    $nome_fantasia = NULL;
                } 
            }
        } else {
            echo "Erro na execução da consulta.";
        }

        if($identificador_filtrado == '000000' || $nome == NULL || $identificador_socio == NULL || $num_rows == 0) {
            echo "
                <section class='details-section'> 
                    <div class='main-details'>
                        <div class='details-title'>
                            <h1> Não temos informações suficientes sobre este socio!  </h1>
                        </div>
                    </div>
    
                    <div class='more-details'>
                        <div class= 'sub-details'>
                            <p> Você está buscando por um sócio que a Receita Federal não nos fornece seu nome ou cnpj ou cpf. Infelizmente não podemos informar com certeza as informações sobre esse sócio! </p>
                        </div>
                    </div>
                </section>";
        } else {
            if($nome && $identificador_socio) {
                $stmt = $conn->prepare("WITH socio_selecionado AS (
                    SELECT *
                    FROM rf_socios
                    WHERE nome_socio_razao = ?
                    AND cpf_cnpj_socio LIKE ?
                    AND cnpj = ?
                    LIMIT 1
                    )
                    SELECT COUNT(*) AS total
                    FROM rf_socios s
                    JOIN rf_empresas em ON s.cnpj = em.cnpj
                    JOIN rf_estabelecimentos e ON s.cnpj = e.cnpj
                    LEFT JOIN rf_municipio m ON m.cod_municipio = e.municipio
                    WHERE s.nome_socio_razao = (SELECT nome_socio_razao FROM socio_selecionado)
                    AND s.cpf_cnpj_socio = (SELECT cpf_cnpj_socio FROM socio_selecionado);
                    ");
    
                $stmt->bind_param("sss", $nome, $identificador_socio, $base_cnpj);
    
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row_count = $result->fetch_assoc();
                    $num_rows = $row_count['total'];
                    $pages = ceil($num_rows / $rows_per_page);
                    if($num_rows > 0) {
                        $stmt = $conn->prepare("WITH socio_selecionado AS (
                            SELECT *
                            FROM rf_socios
                            WHERE nome_socio_razao = ?
                            AND cpf_cnpj_socio LIKE ?
                            AND cnpj = ?
                            LIMIT 1
                        )
                        SELECT CONCAT(e.cnpj, e.cnpj_ordem, e.cnpj_dv) AS cnpj, e.situacao_cadastral, em.razao_social, e.nome_fantasia, CONCAT(m.nome_municipio, ' - ', e.uf) AS localidade
                        FROM rf_socios s
                        JOIN rf_empresas em ON s.cnpj = em.cnpj
                        JOIN rf_estabelecimentos e ON s.cnpj = e.cnpj
                        LEFT JOIN rf_municipio m ON m.cod_municipio = e.municipio
                        WHERE s.nome_socio_razao = (SELECT nome_socio_razao FROM socio_selecionado)
                        AND s.cpf_cnpj_socio = (SELECT cpf_cnpj_socio FROM socio_selecionado) ORDER BY e.situacao_cadastral LIMIT ?,  ? ");
        
                        $stmt->bind_param("sssii", $nome, $identificador_socio, $base_cnpj, $start, $rows_per_page);
    
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            $empresas = $result->fetch_assoc();
                            $num_empresas = mysqli_num_rows($result);
    
                            echo "
                            <section class='details-section'> 
                                <div class='main-details'>
                                    <div class='details-title'>
                                        <h1> $nome </h1>
                                    </div>
                                </div> ";
    
                                if($nome_fantasia != NULL) {
                                    echo" 
                                        <div class='more-details'>
                                            <div class= 'sub-details'>
                                                <p> Você estava visualizando este sócio por meio da empresa $nome_fantasia de razão social $razao, e abaixo terá acesso as demais 
                                                empresas que ele esta filiado. </p>
                                            </div>
                                        </div>
                                        </section>";
                                } else {
                                    echo" 
                                        <div class='more-details'>
                                            <div class= 'sub-details'>
                                                <p> Você estava visualizando este sócio por meio da empresa $razao, e abaixo terá acesso as demais empresas que ele esta filiado. </p>
                                            </div>
                                        </div>
                                        </section>";
                                }
    
    
                            echo "
                            <section class='info-section'> 
                                <div class='info info-empresas'>
                                    <div class='info-header'>
                                        <h1> $nome é socio de $num_rows empresas... </h1>
                                    </div>
                                
    
                                    <div class='empresas'>";
                                    if($num_empresas > 0) {
                                        $empresas = mysqli_fetch_assoc($result);
                                        mysqli_data_seek($result, 0);
                                        echo "<table class='tabela'>
                                        <thead>
                                            <tr>
                                                <th> CNPJ </th>   
                                                <th> Razão Social</th>
                                                <th> Nome Fantasia </th>
                                                <th> Ordem </th> 
                                                <th> Status </th>
                                            </tr>
                                        </thead>
                                        <tbody>";
    
                                        while ($empresas = mysqli_fetch_assoc($result)) {
                                            $empresa_cnpj = $empresas['cnpj'];
                                            $empresa_cnpj = substr($empresa_cnpj, 0, 2) . '.' .
                                                            substr($empresa_cnpj, 2, 3) . '.' .
                                                            substr($empresa_cnpj, 5, 3) . '/' .
                                                            substr($empresa_cnpj, 8, 4) . '-' .
                                                            substr($empresa_cnpj, 12, 2);
                                            $empresas_razao = $empresas['razao_social'];
                                            $empresas_nome = $empresas['nome_fantasia'];
                                            $empresas_localidade = $empresas['localidade'];

                                            $situacao = match ($empresas['situacao_cadastral']) {
                                                '01' => "NULA",
                                                '02' => "ATIVA",
                                                '03' => "SUSPENSA",
                                                '04' => "INAPTA",
                                                '08' => "BAIXADA",
                                                default => "BAIXADA"
                                            };
    
                                            $details_url = "details.php?cnpj=" . urlencode($empresa_cnpj);  
                                            echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                                <td>$empresa_cnpj</td>
                                                <td>$empresas_razao</td>
                                                <td>$empresas_nome</td>
                                                <td>$empresas_localidade</td>
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
                                                        <a href='?cnpj=" . urlencode($cnpj) . "&page-nr=1" . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> Primeira </a>";
                                        
                                            if ($page > 1) {
                                                echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=" . ($page - 1) . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> < </a>";
                                            } else {
                                                echo "<span> < </span>";
                                            }
                                        
                                            if ($page < $pages) {
                                                echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=" . ($page + 1) . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> > </a>";
                                            } else {
                                                echo "<span> > </span>";
                                            }
                                        
                                            echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=$pages" . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'>Última</a>
                                                </div>
                                                </div>";
                                        }
                                    }               
                               echo"
                                </div>
                                    </div>     
                            </section>";
    
                        }
                    }
                }
            }
        } 

    } else {
        $stmt_empresa = $conn->prepare("SELECT * FROM rf_empresas WHERE cnpj = ?");
        $stmt_empresa->bind_param("s", $cnpj);
        
        if ($stmt_empresa->execute())  {
            $result = $stmt_empresa->get_result();
            $num_rows = $result->num_rows;

            if($num_rows > 0) {
                $empresa = $result->fetch_assoc();

                $razao = $empresa['razao_social'];
            }
        } else {
            echo "Erro na execução da consulta.";
        }

        if($identificador_filtrado == '000000' || $nome == NULL || $identificador_socio == NULL || $num_rows == 0) {
            echo "
                <section class='details-section'> 
                    <div class='main-details'>
                        <div class='details-title'>
                            <h1> Não temos informações suficientes sobre este socio!  </h1>
                        </div>
                    </div>
    
                    <div class='more-details'>
                        <div class= 'sub-details'>
                            <p> Você está buscando por um sócio que a Receita Federal não nos fornece seu nome ou cnpj ou cnpj. Infelizmente essa bsuca não poderá ser executada! </p>
                        </div>
                    </div>
                </section>";
        } else {
            if($nome && $identificador_socio) {
                $stmt = $conn->prepare("WITH socio_selecionado AS (
                    SELECT *
                    FROM rf_socios
                    WHERE nome_socio_razao = ?
                    AND cpf_cnpj_socio LIKE ?
                    AND cnpj = ?
                    LIMIT 1
                    )
                    SELECT COUNT(*) AS total
                    FROM rf_socios s
                    JOIN rf_empresas em ON s.cnpj = em.cnpj
                    JOIN rf_estabelecimentos e ON s.cnpj = e.cnpj
                    LEFT JOIN rf_municipio m ON m.cod_municipio = e.municipio
                    WHERE s.nome_socio_razao = (SELECT nome_socio_razao FROM socio_selecionado)
                    AND s.cpf_cnpj_socio = (SELECT cpf_cnpj_socio FROM socio_selecionado);
                    ");
        
                $stmt->bind_param("sss", $nome, $identificador_socio, $cnpj);
            
                if ($stmt->execute())  {
                    $result = $stmt->get_result();
                    $row_count = $result->fetch_assoc();
                    $num_rows = $row_count['total'];
                    $pages = ceil($num_rows / $rows_per_page);

                    if($num_rows > 0) {
                        $stmt = $conn->prepare("WITH socio_selecionado AS (
                            SELECT *
                            FROM rf_socios
                            WHERE nome_socio_razao = ?
                            AND cpf_cnpj_socio LIKE ?
                            AND cnpj = ?
                            LIMIT 1
                        )
                        SELECT CONCAT(e.cnpj, e.cnpj_ordem, e.cnpj_dv) AS cnpj, e.situacao_cadastral, em.razao_social, e.nome_fantasia, CONCAT(m.nome_municipio, ' - ', e.uf) AS localidade
                        FROM rf_socios s
                        JOIN rf_empresas em ON s.cnpj = em.cnpj
                        JOIN rf_estabelecimentos e ON s.cnpj = e.cnpj
                        LEFT JOIN rf_municipio m ON m.cod_municipio = e.municipio
                        WHERE s.nome_socio_razao = (SELECT nome_socio_razao FROM socio_selecionado)
                        AND s.cpf_cnpj_socio = (SELECT cpf_cnpj_socio FROM socio_selecionado) ORDER BY e.situacao_cadastral LIMIT ?,  ? ");
                        $stmt->bind_param("sssii", $nome, $identificador_socio, $cnpj, $start, $rows_per_page);
    
                        if ($stmt->execute()) {
                            $result = $stmt->get_result();
                            $empresas = $result->fetch_assoc();
                            $num_empresas = mysqli_num_rows($result);
    
                            echo "
                            <section class='details-section'> 
                                <div class='main-details'>
                                    <div class='details-title'>
                                        <h1> $nome </h1>
                                    </div>
                                </div> ";
                            
                            
                                echo" 
                                        <div class='more-details'>
                                            <div class= 'sub-details'>
                                                <p> Você estava visualizando este sócio por meio da empresa $razao, e abaixo terá acesso as demais empresas que ele esta filiado. </p>
                                            </div>
                                        </div>
                                        </section>";
                            

                            echo "
                            <section class='info-section'> 
                                <div class='info info-empresas'>
                                    <div class='info-header'>
                                        <h1> $nome é socio de $num_rows empresas... </h1>
                                    </div>
                                 <div class='empresas'>";
                            
                            if($num_empresas > 0) {
                                $empresas = mysqli_fetch_assoc($result);
                                mysqli_data_seek($result, 0);
                                echo "<table class='tabela'>
                                <thead>
                                    <tr>
                                        <th> CNPJ </th>   
                                        <th> Razão Social</th>
                                        <th> Nome Fantasia </th>
                                        <th> Ordem </th> 
                                        <th> Status </th>
                                    </tr>
                                </thead>
                                <tbody>";

                                while ($empresas = mysqli_fetch_assoc($result)) {
                                    $empresa_cnpj = $empresas['cnpj'];
                                    $empresa_cnpj = substr($empresa_cnpj, 0, 2) . '.' .
                                                    substr($empresa_cnpj, 2, 3) . '.' .
                                                    substr($empresa_cnpj, 5, 3) . '/' .
                                                    substr($empresa_cnpj, 8, 4) . '-' .
                                                    substr($empresa_cnpj, 12, 2);
                                    $empresas_razao = $empresas['razao_social'];
                                    $empresas_nome = $empresas['nome_fantasia'];
                                    $empresas_localidade = $empresas['localidade'];

                                    $situacao = match ($empresas['situacao_cadastral']) {
                                        '01' => "NULA",
                                        '02' => "ATIVA",
                                        '03' => "SUSPENSA",
                                        '04' => "INAPTA",
                                        '08' => "BAIXADA",
                                        default => "BAIXADA"
                                    };
    
                                    $details_url = "details.php?cnpj=" . urlencode($empresa_cnpj);  
                                    echo "<tr class='clickable-row tabela-row' data-href='$details_url'>
                                            <td>$empresa_cnpj</td>
                                            <td>$empresas_razao</td>
                                            <td>$empresas_nome</td>
                                            <td>$empresas_localidade</td>
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
                                            <a href='?cnpj=" . urlencode($cnpj) . "&page-nr=1" . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> Primeira </a>";
                                        
                                        if ($page > 1) {
                                            echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=" . ($page - 1) . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> < </a>";
                                        } else {
                                            echo "<span> < </span>";
                                        }
                                        
                                        if ($page < $pages) {
                                            echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=" . ($page + 1) . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'> > </a>";
                                        } else {
                                            echo "<span> > </span>";
                                        }
                                        
                                        echo "<a href='?cnpj=" . urlencode($cnpj) . "&page-nr=$pages" . ($identificador_socio ? "&identificador=" . urlencode($identificador_socio) : "") . ($nome ? "&nome=" . urlencode($nome) : "") . "'>Última</a>
                                                </div>
                                                </div>";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
} else {
    echo "
                <section class='details-section'> 
                    <div class='main-details'>
                        <div class='details-title'>
                            <h1> Não temos informações suficientes sobre este socio!  </h1>
                        </div>
                    </div>
    
                    <div class='more-details'>
                        <div class= 'sub-details'>
                            <p> Você está buscando por um sócio que a Receita Federal não nos fornece seu nome ou cnpj ou cpf. Infelizmente não podemos informar com certeza as informações sobre esse sócio! </p>
                        </div>
                    </div>
                </section>";
}