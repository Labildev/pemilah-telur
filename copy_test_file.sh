#!/bin/bash
# Script ini hanya dijalankan sekali untuk menyalin test.php dari folder docker
# Jalankan dari dalam folder pemilah-telur-xampp/:
#   bash copy_test_file.sh

cp ../pemilah-telur/public/test.php ./public/test.php
echo "test.php berhasil disalin!"
