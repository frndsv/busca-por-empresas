<?php

function showPagination($variable, $pages, $original_input, $cnpj_input, $page) {
    if ($variable == '1') {
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
    } else {
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
}

?>
