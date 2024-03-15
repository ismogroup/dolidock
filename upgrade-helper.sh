#!/bin/bash
DOLLY_HOST=/var/www/dolidock
rm $DOLLY_HOST/documents/install.lock
echo "Exécutez $DOLI_URL_ROOT/install puis appuyez sur une touche pour vérouiller l'installation"
read STOP
echo "" > $DOLLY_HOST/documents/install.lock && chown root:root $DOLLY_HOST/html/conf/conf.php && chmod 444 $DOLLY_HOST/html/conf/conf.php
