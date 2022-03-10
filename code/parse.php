<?php
ini_set('display_errors', 'stderr');

/* Analyzátor kódu */
// připojení pomocných souborů ke skriptu
include_once("./assets/constants.php");
include_once("./assets/arguments.php");
include_once("./assets/analyzator.php");

// Kontrola argumentů
$args = new Arguments();
$args->add_valid_arg("help", true);
$args->test();

// Načtení vstupu
$analyzator = new Analyzator();
$analyzator->read_to_array();

// Lexikální analýza
$analyzator->lex_check();

// Syntaktická analýza
$analyzator->syn_check();

// Výpis XML
$analyzator->xml_print();

// Vrací návratový kód
exit(SUCCESS);
