# Implementační dokumentace k 2. úloze do IPP 2021/2022
Jméno a příjmení: Marek Bitomský
Login: xbitom00 
## 1. interpret pro IPPcode22
Veškerá implementace interpretu se nachází v souboru `interpret.py` a konstanty v `int_assets/constants.py`.
Kód je rozdělen do několika hlavních částí
### 1.1. extrakce dat z XML vstupního souboru
Pro zpracování vstupního XML jsem použil knihovnu `xml.parsers.expat`, která pomocí
metody `Parse` převede XML reprezentaci ze vstupního souboru do datové struktury `commands`, se kterou se
dále pracuje a lze v ní instrukce seřadit podle attributu order. Rozdělení jsem docílil handlery, pro které 
jsem si dopsal vlastní funkčnost.
### 1.2. kontrola některých požadovaných vlastností XML reprezentace
V další části jsou implementovány testy na unikátnost a správnost hodnoty order jednotlivých
instrukcí. Zároveň zde plním strukturu `labels`, kde uchovávám návěští a informace o něm, když se narazí na instrukci `LABEL`
a také se kontroluje, jestli se nějaké návěstí nevyskytuje v XML kódu vícekrát.
### 1.3. zpracování instrukcí
Pro interpretaci slouží funkce `interpret`, která postupně prochází strukturu `commands`, která se dříve naplnila
a nyní se provádí dané operace dle zadané instrukce. Tato funkce vrací instrukční čitač.
#### 1.3.1 skoky, volání a návraty
Při procházení nové instrukce se zvyšuje instrukční čitač, který je dále používán při skocích,
voláních a návratech. Jestliže se narazí na některou z skokových funkcí, tak se prohledá struktura `labels`,
případně se do struktury `call_index_stack`, obsahující indexy pro návrat při volání `RETURN`, uloží daný index.
Při volání instrukce `CALL` se počítadlo uloží na zásobník a vrací se hodnota indexu, která je uložena v `labels`
na kterou se skočí a zároveň se kontroluje, jestli bylo návěští definováno.
Při volání instrukce `RETRUN` se naopak vytáhne hodnota z vrcholu zásobníku a vrátí se.

## 2. testovací skript test.php pro parse.php a interpret.py
Tento skript se skládá ze dvou částí hlavních částí `test.php` a `html.php`. 
### 2.1. test.php
V tomto souboru se řeší samotná část testování skriptů. 
#### 2.1.1. parametry
Kontrolují se zde parametry, jestli byli zadány správně a jestli se nejedná o špatnou kombinaci.
Dále se hodnoty z paremtrů ukládají do proměnných, aby se s němi dalo následně pracovat.
Nastavují se zde hodnoty jako je `--directory` pro určení adresáře, který se má prohledat.
Dále `--recursive`, jestli se má prohledávat i do podadresářu.
#### 2.1.2. testování
Provádí se zde testování, otevírají se všechny soubory `.in`, `.out`, `.src` a kontroluje se zde,
jestli vše sedí s referenčními hodnotami.
### 2.2. html.php
Generuje HTML výstupy na stadartní výstup jako tabulku. 

