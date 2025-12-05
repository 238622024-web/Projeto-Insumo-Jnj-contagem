    </main>
    <footer class="text-center py-4 mt-5 small text-muted">
        &copy; <?= date('Y') ?> Manserv
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
                $('table.table').each(function(){
                    var $table = $(this);
                    var headerCount = $table.find('thead th').length;
                    var firstBodyRow = $table.find('tbody tr:first');
                    var firstRowCount = firstBodyRow.length ? firstBodyRow.find('td').length : 0;

                    // If table has no thead or no header cells, skip initialization
                    if (headerCount === 0) return;

                    // If the first row is an "empty message" with a colspan equal to header count, allow init
                    if (firstRowCount > 0 && firstRowCount !== headerCount) {
                        // Try to detect a single placeholder row using colspan
                        var $firstTd = firstBodyRow.find('td').first();
                        var colspan = $firstTd.attr('colspan');
                        if (!colspan) {
                            console.warn('DataTables skipped due to column count mismatch (thead:', headerCount, 'td:', firstRowCount, ') for table', $table);
                            return;
                        }

                        // If colspan exists but is incorrect, fix it to match headerCount
                        if (parseInt(colspan,10) !== headerCount) {
                            console.info('Adjusting colspan from', colspan, 'to', headerCount, 'for table', $table);
                            $firstTd.attr('colspan', headerCount);
                        }
                    }

                    // Build a neutral columns definition to avoid column-count issues
                    var columnsDef = [];
                    for (var i=0;i<headerCount;i++){ columnsDef.push({}); }

                    $table.DataTable({
                        pageLength: <?= (int)$itensPag ?>,
                        lengthMenu: [10,25,50,100],
                        columns: columnsDef,
                        columnDefs: [{ orderable: false, targets: -1 }],
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
                            searchPlaceholder: 'Pesquisar...'
                        }
                    });
                });
            });
        </script>
</body>
</html>