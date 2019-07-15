<?php

require __DIR__ . '/eczane.class.php';

// eskişehirdeki nöbetçi eczaneler
$eskisehir = NobetciEczane::Find('eskişehir');
print_r($eskisehir);

// izmirdeki nöbetçi eczaneler
$izmir = NobetciEczane::Find('izmir');
print_r($izmir);