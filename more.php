<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
    <link rel="stylesheet" href="./styles/more-results.css">
    
</head>
<body>
    
    <?php 
    $more_results = "more-results.php?input=" . urlencode($original_input);
        echo "<section class='more-results'> 

            <div class='container'>
                <div class='more-container'> 
                    <h6> Ainda não encontrou a empresa que buscava? Você escolher ver uma quantidade maior de dados! </h6> 
                </div>

                <div class='more-container'>   
                    <a href='$more_results'> Ver mais possíveis resultados </a> 
                </div>
            </div>

        </section>";
    ?>
</body>
</html>


