# Dokumentace
**Jméno:** Marek Bitomský
**Login:** xbitom00 
**Email:** <xbitom00@stud.fit.vutbr.cz>

## 1. Analyzátor v php
Moje řešení je členěno do 4 php souborů, konkrétně *analyzator.php*, *arguments.php*, *constants.php* a *parse.php* pro lepší přehlednost v kódu.

### 1.1. Soubor constants.php
Tento soubor obsahuje pouze konstanty pro funkci *exit()*.

### 1.2. Soubor arguments.php
Zde se nachází třída **Arguments**, která má 2 public metody *add_valid_arg()* a *test()*.
1. Metoda *add_valid_arg()* bere jako parametry arg typu string a long typu boolean. Touto metodou lze přidat parametry pro příkazovou řádku, které bude analyzátor akceptovat. Příklad použití `add_valid_arg("help", true);`
2. Metoda *test()* nebere žádné parametry. Pomocí této metody lze zkontrolovat argumenty příkazové řádky a následně podle nich upravit chování analyzátoru.

### 1.3. Soubor analyzator.php
Zde se nachází třída **Analyzator**, která má 3 public metody a 6 private metod pro zjednodušení kódu.
**Public metody** jsou *read_to_array()*, *lex_syn_check()* a *xml_print()*.
1. Metoda *read_to_array()* načítá standardní vstup do pole.
2. Metoda *lex_syn_check()* provádí lexikální a syntaktickou analýzu.
3. Metoda *xml_print()* vypisuje xml na standardní výstup.

**Private metody** jsou *is_var_or_symb()*, *is_label()*, *is_type()*, *arg_count_check()*, *xml_header()* a *xml_instruction()*.
1. Metoda *is_var_or_symb()* kontroluje, jestli je zadaný parametr instrukce typu \<var\> nebo typu \<symb\> a vrací pole, které v sobě uchovává typ na indexu 0 a hodnotu na indexu 1 pro výpis. 
2. Metoda *is_label()* kontroluje, jestli je zadaný parametr instrukce validní label.
3. Metoda *is_type()* kontroluje, jestli je zadaný parametr instrukce typu \<type\>.
4. Metoda *arg_count_check()* kontroluje, jestli právě zpracovávaná instrukce má správný počet argumentů.
5. Metoda *xml_header()* vytváří hlavičku xml výpisu s počátečním nastavením a elementem \<program\>.
6. Metoda *xml_instruction()* generuje xml pro zadanou instrukci a její parametry. Její parametry jsou typu string a vždy je potřeba zadat minimálně první z nich a tím je instrukce, následuje volitelný typ a hodnota pro první až třetí argument.

### 1.4. Soubor parse.php
Zde se využívají veškeré metody z ostatních souborů a tvoří samotnou funkcionalitu.