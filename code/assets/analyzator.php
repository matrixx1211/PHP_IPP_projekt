<?php

/**
 * Analyzátor kódu
 */
class Analyzator
{
	public array $data;
	public bool $lex_error;
	public bool $syn_error;

	/**
	 * konstruktor pro analyzátor
	 *
	 * Konstroktor pro analyzátor s volitelným parametrem data.
	 *
	 * @param Array $data Pole řádků vstupu
	 **/
	public function __construct(array $data = array())
	{
		$this->data = $data;
		$this->lex_error = false;
		$this->syn_error = false;
	}

	/**
	 * funkce čte ze souboru/stdin a ukládá do pole
	 *
	 * Funkce čte soubor nebo stdin, který se má zanalyzovat, 
	 * vkládá ho do pole a odstraňuje komentáře a několikanásobné mezery.
	 *
	 * @param String $filename Jméno souboru
	 **/
	public function read_to_array(String $filename = "stdin")
	{
		// otevře soubor podle jména
		if ($filename == "stdin") {
			$filename = "php://" . $filename;
		}
		$file = fopen("$filename", "r");
		if ($file == false) {
			fprintf(STDERR, "File error: file not found or not enough rights.");
			exit(INFDEX_ERROR);
		}
		// načítám řádky
		while (($line = fgets($file)) != false) {
			// pokud obsahuje komentář -> odstraním ho
			if (str_contains($line, "#")) {
				$hash_pos = strpos($line, "#");
				$line = substr($line, 0, $hash_pos);
			}
			// všechny několikanásobné mezery nahradím za jednu
			$line = preg_replace('/\s+/', ' ', $line);

			if (!empty($line)) {
				// rozdění podle mezer
				array_push($this->data, explode(" ", $line));
			}
		}
		//print_r($this->data);
	}

	/**
	 * funkce pro lexikální analýzu
	 *
	 * Funkce kontroluje lexikální správnost zadaného vstupu.
	 **/
	public function lex_check()
	{
		// kontrola hlavičky
		if ($this->data[0][0] != ".IPPcode22") {
			fprintf(STDERR, "Header error: header not found on line 1!");
			exit(HEADER_ERROR);
		}

		// procházím pole a kontroluji lexikální chyby
		for ($i = 0; $i < count($this->data); $i++) {
			$line_length = count($this->data[$i]);
			for ($j = 0; $j < $line_length; $j++) {
				if ($i == 0) $j++;
				$upper = strtoupper($this->data[$i][$j]);
				switch ($upper) {
						// příkazy
					case 'MOVE':
					case 'CREATEFRAME':
					case 'PUSHFRAME':
					case 'POPFRAME':
					case 'DEFVAR':
					case 'CALL':
					case 'RETURN':
					case 'PUSHS':
					case 'POPS':
					case 'ADD':
					case 'SUB':
					case 'MUL':
					case 'DIV':
					case 'IDIV':
					case 'LT':
					case 'GT':
					case 'EQ':
					case 'AND':
					case 'OR':
					case 'NOT':
					case 'INT2CHAR':
					case 'STRI2INT':
					case 'READ':
					case 'WRITE':
					case 'CONCAT':
					case 'STRLEN':
					case 'GETCHAR':
					case 'SETCHAR':
					case 'TYPE':
					case 'LABEL':
					case 'JUMP':
					case 'JUMPIFEQ':
					case 'JUMPIFNEQ':
					case 'EXIT':
					case 'DPRINT':
					case 'BREAK':
						// datové typy
					case 'int':
					case 'bool':
					case 'string':
					case 'nil':
						break;


					default:
						// pokud je prázdný pole -> odstranit
						if (empty($this->data[$i][$j]) && $this->data[$i][$j] != '0' && $this->data[$i][$j] != 0) {
							array_splice($this->data[$i], $j, 1);
							$line_length = count($this->data[$i]);
						} else {
							if (str_contains($this->data[$i][$j], "@") == true) {
								$at_pos = strpos($this->data[$i][$j], "@");

								$len = strlen($this->data[$i][$j]);
								$before_at = substr($this->data[$i][$j], 0, $at_pos)."\n";
								$after_at = substr($this->data[$i][$j], $at_pos+1)."\n";


								echo "len ".$len." before ".$before_at." after ".$after_at;


								$at_array = explode("@", $this->data[$i][$j]);
								$this->data[$i][$j] = $at_array;
							} else {
								// neplatný znak 
								fprintf(STDERR, "Lexical error: in word \"%s\"", $this->data[$i][$j]);
								exit(LEXSEM_ERROR);
							}
						}
						break;
				}
			}
		}
		//print_r($this->data); //!debug
	}

	/**
	 * funkce pro syntaktickou analýzu
	 *
	 * Funkce kontroluje syntaktickou správnost zadaného vstupu.
	 **/
	public function syn_check()
	{
		
	}

	private function xml_header() {

	}
}
