<?php
    include_once("header.php");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Conecta </title>

    
    <link rel="stylesheet" href="./styles/main.css">
    

    <!-- font montserrat font-family: "Montserrat", sans-serif;  -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <!-- font lato font-family: "Lato", sans-serif; -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>
<body>
    <section class='search-section'>
        <div class="search-title">
            <h1 class='title'> BUSCAR SÓCIO </h1>
        </div>
        <div class='search-main'>
            <form class='form-search' action="socios.php" method="get">
                <input class='input' id='busca' name="input" placeholder="Digite o nome do sócio ou o cnpj caso seja um empresa..." type="text" value="<?php echo isset($_GET['input']) ? htmlspecialchars($_GET['input']) : ''; ?>">
                <button type="submit">PESQUISAR</button>
            </form>
        </div>
    </section>

    <section class='result-section'>
        <div id='search-result'>
            <?php
                if (isset($_GET['input'])) {
                    include('search-socios.php');
                }
            ?>
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