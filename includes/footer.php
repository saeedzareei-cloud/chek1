    </div> <!-- بسته شدن main-content -->
    
    <!-- jQuery - محلی -->
    <script src="<?php echo $base_url; ?>/assets/js/jquery.min.js"></script>
    
    <!-- Bootstrap JS - محلی -->
    <script src="<?php echo $base_url; ?>/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Persian Datepicker - محلی -->
    <script src="<?php echo $base_url; ?>/assets/js/persian-date.min.js"></script>
    <script src="<?php echo $base_url; ?>/assets/js/persian-datepicker.min.js"></script>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/persian-datepicker.min.css">
    
    <!-- Custom JS -->
    <script src="<?php echo $base_url; ?>/assets/js/script.js"></script>
    
    <?php if (isset($extra_js)) echo $extra_js; ?>
    
    <script>
    $(document).ready(function() {
        $('.persian-date').persianDatepicker({
            format: 'YYYY/MM/DD',
            autoClose: true,
            observer: true
        });
    });
    </script>
</body>
</html>