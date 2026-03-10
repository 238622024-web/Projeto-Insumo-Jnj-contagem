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
        <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="assets/vendor/jquery/jquery-3.7.0.min.js"></script>
        <script src="assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
        <script src="assets/js/header-footer.js"></script>
</body>
</html>