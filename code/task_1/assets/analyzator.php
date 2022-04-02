<?php

/**
 * Analyzátor kódu
 */
class Analyzator
{
	public array $data;
	public bool $lex_error;
	public bool $syn_error;
	private XMLWriter $xml;
	private int $order;

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
		$this->order = 1;
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
			fprintf(STDERR, "File error: file not found or not enough rights!\n");
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
			$line = trim(preg_replace('/\s+/', ' ', $line));
			if (!empty($line)) {
				$exploded_array = explode(" ", $line);
				array_push($this->data, $exploded_array);
			}
		}
	}

	/**
	 * funkce pro lexikální a syntaktickou analýzu
	 *
	 * Funkce kontroluje lexikální a syntaktickou správnost zadaného vstupu.
	 **/
	public function lex_syn_check()
	{
		// kontrola hlavičky
		if (strtoupper($this->data[0][0]) != ".IPPCODE22") {
			fprintf(STDERR, "Header error: header not found on line 1!\n");
			exit(HEADER_ERROR);
		}
		// generování hlavičky
		$this->xml_header();

		// procházím pole a kontroluji lexikální a syntaktické chyby
		for ($i = 1; $i < count($this->data); $i++) {
			switch (strtoupper($this->data[$i][0])) {
					// 0 argumentů
				case 'CREATEFRAME':
				case 'PUSHFRAME':
				case 'POPFRAME':
				case 'RETURN':
				case 'BREAK':
					$this->arg_count_check($this->data[$i], 0);
					// výpis
					$this->xml_instruction($this->data[$i][0]);
					break;

					// 1 argument -- var / symb
				case 'DEFVAR': // var
				case 'PUSHS': // symb
				case 'POPS': // var 
				case 'WRITE': // symb
				case 'EXIT': // symb
				case 'DPRINT': // symb
					$this->arg_count_check($this->data[$i], 1);
					// argument 1
					$arg1_array = $this->is_var_or_symb($this->data[$i][1]);
					if ($this->data[$i][0] == "DEFVAR" || $this->data[$i][0] == "POPS") {
						if ($arg1_array[0] != "var") {
							fprintf(STDERR, "Syntax error: expected \"<var>\", but get \"%s\" in \"%s\".\n", $arg1_array[0], $this->data[$i][1]);
							exit(LEXSYN_ERROR);
						}
					}
					// výpis
					$this->xml_instruction($this->data[$i][0], $arg1_array[0], $arg1_array[1]);
					break;

					// 1 argument -- label 
				case 'CALL':
				case 'LABEL':
				case 'JUMP':
					$this->arg_count_check($this->data[$i], 1);
					$this->is_label($this->data[$i][1]);
					// výpis
					$this->xml_instruction($this->data[$i][0], "label", $this->data[$i][1]);
					break;

					// 2 argumenty
				case 'MOVE': // var symb
				case 'INT2CHAR': // var symb
				case 'READ': // var type
				case 'STRLEN': // var symb
				case 'TYPE': // var symb
					$this->arg_count_check($this->data[$i], 2);
					// argument 1
					$arg1_array = $this->is_var_or_symb($this->data[$i][1]);
					if ($arg1_array[0] != "var") {
						fprintf(STDERR, "Syntax error: expected \"<var>\", but get \"%s\" in \"%s\".\n", $arg1_array[0], $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					// argument 2
					if ($this->data[$i][0] == "READ") {
						$this->is_type($this->data[$i][2]);
						// výpis
						$this->xml_instruction($this->data[$i][0], $arg1_array[0], $arg1_array[1], "type", $this->data[$i][2]);
					} else {
						$arg2_array = $this->is_var_or_symb($this->data[$i][2]);
						// výpis
						$this->xml_instruction($this->data[$i][0], $arg1_array[0], $arg1_array[1], $arg2_array[0], $arg2_array[1]);
					}
					break;

					// 3 argumenty -- var symb symb
				case 'ADD':
				case 'SUB':
				case 'MUL':
				case 'IDIV':
				case 'LT':
				case 'GT':
				case 'EQ':
				case 'AND':
				case 'OR':
				case 'NOT':
				case 'STRI2INT':
				case 'CONCAT':
				case 'GETCHAR':
				case 'SETCHAR':
					$this->arg_count_check($this->data[$i], 3);
					// argument 1
					$arg1_array = $this->is_var_or_symb($this->data[$i][1]);
					if ($arg1_array[0] != "var") {
						fprintf(STDERR, "Syntax error: expected \"<var>\", but get \"%s\" in \"%s\".\n", $arg1_array[0], $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					$arg2_array = $this->is_var_or_symb($this->data[$i][2]);
					$arg3_array = $this->is_var_or_symb($this->data[$i][3]);
					// výpis
					$this->xml_instruction($this->data[$i][0], $arg1_array[0], $arg1_array[1], $arg2_array[0], $arg2_array[1], $arg3_array[0], $arg3_array[1]);
					break;


					// 3 argumenty -- label symb symb
				case 'JUMPIFEQ':
				case 'JUMPIFNEQ':
					$this->arg_count_check($this->data[$i], 3);
					// argument 1
					$this->is_label($this->data[$i][1]);
					// argument 2
					$arg2_array = $this->is_var_or_symb($this->data[$i][2]);
					// argument 3
					$arg3_array = $this->is_var_or_symb($this->data[$i][3]);
					// výpis
					$this->xml_instruction($this->data[$i][0], "label", $this->data[$i][1], $arg2_array[0], $arg2_array[1], $arg3_array[0], $arg3_array[1]);
					break;
					// není to legální OPCODE
				default:
					fprintf(STDERR, "Lexical error: invalid OPCODE.");
					exit(LEXSYN_ERROR);
					break;
			}
		}
		//print_r($this->data); //!debug
	}

	/** 
	 * funkce kontroluje jestli se jedná o var nebo symb
	 * 
	 * Funkce kontroluje, jestli se jedná o proměnnou nebo symbol.
	 * Vrací pole s typem na indexu 0 a jeho argumentem na indexu 1.
	 * 
	 * @param String $var_or_symb Potenciální var nebo symb
	 * @return Array
	 **/
	private function is_var_or_symb(string $var_or_symb)
	{
		if (str_contains($var_or_symb, "@")) {
			$at_pos = strpos($var_or_symb, "@");
			$len = strlen($var_or_symb);
			// před @ nic
			if ($at_pos == 0) {
				fprintf(STDERR, "Lexical error: expected something before \"@\", but get nothing.\n");
				exit(LEXSYN_ERROR);
			}
			// za @ nic
			if (($len - 1) == $at_pos) {
				fprintf(STDERR, "Lexical error: expected something after \"@\", but get nothing.\n");
				exit(LEXSYN_ERROR);
			}
			$before_at = substr($var_or_symb, 0, $at_pos);
			$after_at = substr($var_or_symb, $at_pos + 1);
			$return = array();
			switch ($before_at) {
				case 'TF':
				case 'LF':
				case 'GF':
					if (preg_match("/^([_\-$&%*!?A-Za-z]([_\-$&%*!?A-Za-z0-9])*)/", $after_at) != 1) {
						fprintf(STDERR, "Lexical error: variable \"%s\" contains invalid character/s.\n", $after_at);
						exit(LEXSYN_ERROR);
					}
					array_push($return, "var");
					array_push($return, $var_or_symb);
					break;

				case 'string':
				case 'bool':
				case 'int':
				case 'nil':
					// pokud se jedná o boolean
					if ($before_at == "bool") {
						if (!($after_at == "false" || $after_at == "true")) {
							fprintf(STDERR, "Lexical error: expected \"true\" or \"false\", but get \"%s\".\n", $after_at);
							exit(LEXSYN_ERROR);
						}
					}
					// pokud se jedná o integer
					if ($before_at == "int") {
						if (preg_match("/^(?:[-|\+]?(0[xX])[0-9a-fA-F]+|[-|\+]?\d+(?:\d+)?|[-|\+]?(0[oO])[0-7]+)$/", $after_at) != 1) {
							fprintf(STDERR, "Lexical error: expected octal, decimal or hexadecimal number, but get \"%s\".\n", $after_at);
							exit(LEXSYN_ERROR);
						}
						if ($after_at == 0 || $after_at == '0')
							$after_at .= "\0";
					}
					// pokud se jedná o nil
					if ($before_at == "nil") {
						if ($after_at != "nil") {
							fprintf(STDERR, "Lexical error: expected \"nil\", but get \"%s\".\n", $after_at);
							exit(LEXSYN_ERROR);
						}
					}
					// pokud se jedná o string
					if ($before_at == "string") {
						if ($after_at == 0 || $after_at == '0')
							$after_at .= "\0";
					}
					array_push($return, $before_at);
					array_push($return, $after_at);
					break;

				default:
					fprintf(STDERR, "Lexical error: expected frame or data type, but get \"%s\".\n", $before_at);
					exit(LEXSYN_ERROR);
					break;
			}
			return $return;
		} else {
			fprintf(STDERR, "Lexical error: expected <var> or <symb>, but get \"%s\".\n", $var_or_symb);
			exit(LEXSYN_ERROR);
		}
	}

	/**
	 * funkce kontroluje jestli se jedná o label
	 *
	 * Funkce kontroluje, jestli se jedná o label a bere.
	 * Jako parametr bere potenciální label.
	 *
	 * @param String $label Potenciální label
	 **/
	private function is_label(string $label)
	{
		if (preg_match("/^([_\-$&%*!?A-Za-z]([_\-$&%*!?A-Za-z0-9])*)/", $label) != 1) {
			fprintf(STDERR, "Lexical error: label \"%s\" contains invalid character/s.\n", $label);
			exit(LEXSYN_ERROR);
		}
	}

	/**
	 * funkce kontroluje jestli se jedná o typ
	 *
	 * Funkce kontroluje, jestli se jedná o typ.
	 * Jako parametr bere potenciální typ.
	 *
	 * @param String $type Potenciální typ
	 **/
	private function is_type(string $type)
	{
		switch ($type) {
			case 'int':
			case 'string':
			case 'bool':
			case 'nil':
				break;

			default:
				fprintf(STDERR, "Lexical error: invalid type \"%s\".\n", $type);
				exit(LEXSYN_ERROR);
				break;
		}
	}

	/**
	 * funkce kontroluje počet argumentů
	 *
	 * Funkce kontroluje, jestli zadaný počet argumentů je stejný jako počet očekávaných.
	 * Jako parametry bere pole dat a očekávaný počet argumentů.
	 *
	 * @param Array $data Pole dat
	 * @param Int $expected_count Očekávaný počet argumentů
	 **/
	private function arg_count_check(array $data, int $expected_count)
	{
		$count = count($data) - 1; // bez instrukce
		if ($count != $expected_count) {
			fprintf(STDERR, "Syntax error: expected \"%d\" argument/s for instruction \"%s\", but get \"%d\".\n", $expected_count, $data[0], $count);
			exit(LEXSYN_ERROR);
		}
	}

	/**
	 * funkce generuje xml hlavičku
	 *
	 * Funkce generuje hlavičku pro xml soubor a počáteční tag program
	 **/
	private function xml_header()
	{
		$this->xml = xmlwriter_open_memory();
		xmlwriter_set_indent($this->xml, 1);
		xmlwriter_set_indent_string($this->xml, "\t");
		xmlwriter_set_indent($this->xml, true);

		xmlwriter_start_document($this->xml, '1.0', 'UTF-8');

		// program element
		xmlwriter_start_element($this->xml, 'program');

		// Atribut language pro program
		xmlwriter_start_attribute($this->xml, "language");
		xmlwriter_text($this->xml, "IPPcode22");
		xmlwriter_end_attribute($this->xml);
	}

	/**
	 * funkce generuje xml pro instrukci
	 *
	 * Funkce generuje xml pro konkrétní instrukci.
	 * Jako parametry bere instrukci a následně nula až tři 
	 * argumenty včetně jejich typů.
	 *
	 * @param String $instruction Instrukce
	 * @param ?String $arg1_type Typ argument 1
	 * @param ?String $arg1 Argument 1
	 * @param ?String $arg2_type Typ argument 2
	 * @param ?String $arg2 Argument 2
	 * @param ?String $arg3_type Typ argument 3
	 * @param ?String $arg3 Argument 3
	 **/
	private function xml_instruction(
		String $instruction,
		?String $arg1_type = "",
		?String $arg1 = "",
		?String $arg2_type = "",
		?String $arg2 = "",
		?String $arg3_type = "",
		?String $arg3 = ""
	) {
		// Instrukce
		xmlwriter_start_element($this->xml, "instruction");

		// Atribut order pro instrukci
		xmlwriter_start_attribute($this->xml, "order");
		xmlwriter_text($this->xml, $this->order);
		xmlwriter_end_attribute($this->xml);
		$this->order++;

		// Atribut opcode pro instrukci
		xmlwriter_start_attribute($this->xml, "opcode");
		xmlwriter_text($this->xml, $instruction);
		xmlwriter_end_attribute($this->xml);

		// Argument 1
		if (!empty($arg1)) {
			xmlwriter_start_element($this->xml, "arg1"); 	// <arg1
			xmlwriter_start_attribute($this->xml, "type");  //	 type="
			xmlwriter_text($this->xml, $arg1_type); 		// 	 $arg1_type
			xmlwriter_end_attribute($this->xml); 			//	 "
			xmlwriter_text($this->xml, $arg1); 				// >$arg1
			xmlwriter_end_element($this->xml); 				// </arg1>
		}

		// Argument 2
		if (!empty($arg2)) {
			xmlwriter_start_element($this->xml, "arg2"); 	// <arg2
			xmlwriter_start_attribute($this->xml, "type");  //	 type="
			xmlwriter_text($this->xml, $arg2_type); 		// 	 $arg2_type
			xmlwriter_end_attribute($this->xml); 			//	 "
			xmlwriter_text($this->xml, $arg2); 				// >$arg2
			xmlwriter_end_element($this->xml); 				// </arg2>
		}

		// Argument 2
		if (!empty($arg3)) {
			xmlwriter_start_element($this->xml, "arg3"); 	// <arg3
			xmlwriter_start_attribute($this->xml, "type");  //	 type="
			xmlwriter_text($this->xml, $arg3_type); 		// 	 $arg3_type
			xmlwriter_end_attribute($this->xml); 			//	 "
			xmlwriter_text($this->xml, $arg3); 				// >$arg3
			xmlwriter_end_element($this->xml); 				// </arg3>
		}
		
		// Konec instrukce
		//xmlwriter_full_end_element($this->xml);
		xmlwriter_end_element($this->xml);
	}

	/**
	 * funkce vypíše xml na výstup
	 *
	 * Funkce vypíše na výstup zpracovaný IPPcode22 ve formátu XML.
	 * 
	 * @param String $output Výstupní soubor
	 **/
	public function xml_print(String $output_filename = "stdout")
	{
		// ukončení program tagu
		xmlwriter_end_element($this->xml);

		// ukončení dokumentu
		xmlwriter_end_document($this->xml);

		// vypsání xml do souboru
		if ($output_filename == "stdout") {
			$output_filename = "php://" . $output_filename;
		}
		$output_file = fopen($output_filename, "w");
		fprintf($output_file, "%s", xmlwriter_output_memory($this->xml));
	}
}
