<?php
/* Úspěch */
define('SUCCESS', 0); // vše proběhne správně

/* Chyby pro všechny skripty */
define('PARAMS_ERROR', 10); // chybějící parametr skriptu nebo zakázaná kombinace parametrů
define('INFDEX_ERROR', 11); // chyba při neexistenci nebo nedostatečném oprávnení otevírání souboru
define('OUTFWR_ERROR', 12); // chyba při nedostatečném oprávnění nebo při zápisu

/* Chyby pro specifické skripty */
define('HEADER_ERROR', 21); // chybná hlavička
define('OPCODE_ERROR', 22); // chyba v operačním kódů v IPPcode22
define('LEXSEM_ERROR', 23); // chyba lexikální nebo syntaktická v IPPcode22

/* Interní chyba */
define('INTERN_ERROR', 99); // chyba při alokaci paměti apod.
