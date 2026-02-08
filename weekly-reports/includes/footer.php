            </div><!-- نهاية container-fluid -->
        </div><!-- نهاية page-content -->
    </div><!-- نهاية wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- سكريبت مخصص -->
    <script src="<?= SITE_URL ?>/assets/js/script.js"></script>

    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
