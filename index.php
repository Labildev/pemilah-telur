<?php
// Redirect otomatis ke folder public jika tidak menggunakan apache atau .htaccess tidak bekerja
header("Location: public/");
exit;
