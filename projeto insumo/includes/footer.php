    </main>
    <footer class="text-center py-4 mt-auto small text-muted footer-spacer">
        <strong>Manserv</strong>
        <?php if (!currentUser()): ?>
            <div class="footer-links mt-2">
                <a href="login.php" class="footer-link">Login</a>
                <span class="footer-separator">|</span>
                <a href="create-account.php" class="footer-link">Criar Conta</a>
            </div>
        <?php endif; ?>
        <div class="copyright">&copy; <?= date('Y') ?> Todos os direitos reservados</div>
    </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <!-- jQuery + DataTables -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="assets/js/header-footer.js"></script>
</body>
</html>