    </main>
    <footer class="text-center py-4 mt-5 small text-muted">
        &copy; <?= date('Y') ?> JNJ
    </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <!-- jQuery + DataTables -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <?php
          require_once __DIR__ . '/../settings.php';
          $itensPag = (int) getSetting('itens_pagina', 25);
          if ($itensPag <= 0) { $itensPag = 25; }
        ?>
        <script>
            $(document).ready(function(){
                if ($('table.table').length){
                    $('table.table').DataTable({
                        pageLength: <?= (int)$itensPag ?>,
                        lengthMenu: [10,25,50,100],
                        columnDefs: [{ orderable: false, targets: -1 }],
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
                            searchPlaceholder: 'Pesquisar...'
                        }
                    });
                }
            });
        </script>
</body>
</html>