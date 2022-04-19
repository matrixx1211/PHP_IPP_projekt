<?php
ini_set('display_errors', 'stderr');

/* Analyzátor kódu */
// připojení pomocných souborů ke skriptu
include_once("./parse_assets/constants.php");
include_once("./parse_assets/arguments.php");
include_once("./parse_assets/analyzator.php");

// Kontrola argumentů
$args = new Arguments();
$args->add_valid_arg("help", true);
$args->test();

// Načtení vstupu
$analyzator = new Analyzator();
$analyzator->read_to_array();

// Lexikální a syntaktická analýza
$analyzator->lex_syn_check();

// Výpis XML
$analyzator->xml_print();

// Vrací návratový kód
exit(SUCCESS);
