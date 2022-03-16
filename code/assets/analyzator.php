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
			$line = preg_replace('/\s+/', ' ', $line);
			if (!empty(trim($line))) {
				// rozdění podle mezer
				array_push($this->data, explode(" ", $line));
			}
		}
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
			fprintf(STDERR, "Header error: header not found on line 1!\n");
			exit(HEADER_ERROR);
		}

		// procházím pole a kontroluji lexikální chyby
		for ($i = 0; $i < count($this->data); $i++) {
			$line_length = count($this->data[$i]);
			for ($j = 0; $j < $line_length; $j++) {
				if ($i == 0) $j++;
				switch (strtoupper($this->data[$i][$j])) {
						// příkazy
					case 'MOVE':
					case 'CREATEFRAME':
					case 'PUSHFRAME':
					case 'POPFRAME':
					case 'DEFVAR':

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

					case 'EXIT':
					case 'DPRINT':
					case 'BREAK':
						break;
						// příkazy, kde může být label
					case 'CALL':
					case 'LABEL':
					case 'JUMP':
					case 'JUMPIFEQ':
					case 'JUMPIFNEQ':
						$j++;
						break;
						// datové typy
					case 'INT':
					case 'BOOL':
					case 'STRING':
					case 'NIL':
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
								$before_at = substr($this->data[$i][$j], 0, $at_pos);
								$after_at = substr($this->data[$i][$j], $at_pos + 1);

								if ((strlen($this->data[$i][$j]) - 1) == $at_pos) {
									fprintf(STDERR, "Lexical error: after \"@\" is nothing!\n");
									exit(LEXSYN_ERROR);
								}

								switch (strtoupper($before_at)) {
									case 'INT':
									case 'BOOL':
									case 'STRING':
									case 'NIL':
										break;
									case 'TF':
									case 'LF':
									case 'GF':
										for ($l = 0; $l < strlen($after_at); $l++) {
											if (!($after_at[$l] == "_" ||
												$after_at[$l] == "-" ||
												$after_at[$l] == "$" ||
												$after_at[$l] == "&" ||
												$after_at[$l] == "%" ||
												$after_at[$l] == "*" ||
												$after_at[$l] == "!" ||
												$after_at[$l] == "?" ||
												ctype_alnum($after_at[$l]))) {
												fprintf(STDERR, "Lexical error: variable contains invalid character: \"%s\"!\n", $after_at[$l]);
												exit(LEXSYN_ERROR);
											}
										}
										break;

									default:
										// neplatný znak 
										fprintf(STDERR, "Lexical error: in word \"%s\" probably before \"@\"!\n", $this->data[$i][$j]);
										exit(LEXSYN_ERROR);
										break;
								}
								$at_array = explode("@", $this->data[$i][$j]);
								$this->data[$i][$j] = $at_array;
							} else {
								// neplatný znak 
								fprintf(STDERR, "Lexical error: in OPCODE \"%s\"!\n", $this->data[$i][$j]);
								exit(LEXSYN_ERROR);
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
		// generování hlavičky
		$this->xml_header();
		// procházím pole a kontroluji syntaktické chyby
		for ($i = 1; $i < count($this->data); $i++) {
			switch ($this->data[$i][0]) {
					// 0 argumentů
				case 'CREATEFRAME':
				case 'PUSHFRAME':
				case 'POPFRAME':
				case 'RETURN':
				case 'BREAK':
					$this->xml_instruction($this->data[$i][0]);
					break;

					// 1 argument -- var / symbol
				case 'DEFVAR': // var
				case 'PUSHS': // symbol
				case 'POPS': // var 
				case 'WRITE': // symb
				case 'EXIT': // symb
				case 'DPRINT': // symb
					if (is_array($this->data[$i][1])) {
						$type1 = strtoupper($this->data[$i][1][0]);
						$arg1 = $this->data[$i][1][0] . "@" . $this->data[$i][1][1];
						$arg1_type = "";
						if ($this->data[$i][0] == "DEFVAR" || $this->data[$i][0] == "POPS") {
							if ($type1 == "LF" || $type1 == "GF" || $type1 == "TF") {
								$arg1_type = "var";
								$this->xml_instruction($this->data[$i][0], $arg1, $arg1_type);
							} else {
								fprintf(STDERR, "Syntax error: expected <var> but get \"%s\"!\n", $this->data[$i][1]);
								exit(LEXSYN_ERROR);
							}
						} else {
							if ($type1 == "LF" || $type1 == "GF" || $type1 == "TF") {
								fprintf(STDERR, "Syntax error: expected <var> but get \"%s\"!\n", $this->data[$i][1]);
								exit(LEXSYN_ERROR);
							} else {
								$arg1_type = $this->data[$i][1][0];
								$this->xml_instruction($this->data[$i][0],  $this->data[$i][1][1] . "\0", $arg1_type);
							}
						}
					} else {
						fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					break;

					// 1 argument -- label 
				case 'CALL':
				case 'LABEL':
				case 'JUMP':
					$this->xml_instruction($this->data[$i][0], $this->data[$i][1], "label");
					break;

					// 2 argumenty
				case 'MOVE': // var symb
				case 'INT2CHAR': // var symb
				case 'READ': // var type
				case 'STRLEN': // var symb
				case 'TYPE': // var symb
					if (is_array($this->data[$i][1])) {
						$arg1 = $this->data[$i][1][0] . "@" . $this->data[$i][1][1];
						$type1 = strtoupper($this->data[$i][1][0]);
						$arg1_type = "";
						if ($type1 == "LF" || $type1 == "GF" || $type1 == "TF")
							$arg1_type = "var";
						else {
							fprintf(STDERR, "Syntax error: expected <var> but get \"%s\"!\n", $arg1);
							exit(LEXSYN_ERROR);
						}
						if (is_array($this->data[$i][2])) {
							$type2 = strtoupper($this->data[$i][2][0]);
							$arg2 = $this->data[$i][2][0] . "@" . $this->data[$i][2][1];
							$arg2_type = "";
							if ($type2 == "LF" || $type2 == "GF" || $type2 == "TF") {
								fprintf(STDERR, "Syntax error: <symb> but get \"%s\"!\n", $this->data[$i][1]);
								exit(LEXSYN_ERROR);
							} else {
								$arg2_type = $this->data[$i][2][0];
								$this->xml_instruction($this->data[$i][0], $arg1, $arg1_type, $this->data[$i][2][1]."\0", $arg2_type);
							}
						} else if ($this->data[$i][0] == "READ") {
							if ($type1 == "int" || $type1 == "bool" || $type1 == "string") {
								fprintf(STDERR, "Syntax error: expected <type> but get \"%s\"!\n", $this->data[$i][2]);
								exit(LEXSYN_ERROR);
							} else
								$this->xml_instruction($this->data[$i][0], $arg1, $arg1_type, $this->data[$i][2], "type");
						} else {
							fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
							exit(LEXSYN_ERROR);
						}
					} else {
						fprintf(STDERR, "Syntax error: expected <var> or <symb> but get %s!\n", $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					break;

					// 3 argumenty -- var / symbol
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
					if (is_array($this->data[$i][1])) {
						$arg1 = $this->data[$i][1][0] . "@" . $this->data[$i][1][1];
						$type1 = strtoupper($this->data[$i][1][0]);
						$arg1_type = "";
						if ($type1 == "LF" || $type1 == "GF" || $type1 == "TF")
							$arg1_type = "var";
						else {
							fprintf(STDERR, "Syntax error: expected <var> but get \"%s\"!\n", $arg1);
							exit(LEXSYN_ERROR);
						}
						if (is_array($this->data[$i][2])) {
							$type2 = strtoupper($this->data[$i][2][0]);
							$arg2 = $this->data[$i][2][0] . "@" . $this->data[$i][2][1];
							$arg2_type = "";
							if ($type2 == "LF" || $type2 == "GF" || $type2 == "TF") {
								fprintf(STDERR, "Syntax error: expected <symb> but get \"%s\"!\n", $arg2);
								exit(LEXSYN_ERROR);
							} else 
							{
								$arg2_type = $this->data[$i][2][0];
								$arg2 = $this->data[$i][2][1]."\0";
							}
							if (is_array($this->data[$i][3])) {
								$type3 = strtoupper($this->data[$i][3][0]);
								$arg3 = $this->data[$i][3][0] . "@" . $this->data[$i][3][1];
								$arg3_type = "";
								if ($type3 == "LF" || $type3 == "GF" || $type3 == "TF") {
									fprintf(STDERR, "Syntax error: expected <symb> but get \"%s\"!\n", $arg3);
									exit(LEXSYN_ERROR);
								} else {
									$arg3_type = $this->data[$i][3][0];
									$this->xml_instruction($this->data[$i][0], $arg1, $arg1_type, $arg2, $arg2_type, $this->data[$i][3][1]."\0", $arg3_type);
								}
							} else {
								fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
								exit(LEXSYN_ERROR);
							}
						} else {
							fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
							exit(LEXSYN_ERROR);
						}
					} else {
						fprintf(STDERR, "Syntax error: expected <var> or <symb> but get %s!\n", $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					break;


					// 3 argumenty -- label
				case 'JUMPIFEQ':
				case 'JUMPIFNEQ':
					if (ctype_alnum($this->data[$i][1])) {
						$arg1 = $this->data[$i][1];
						$arg1_type = "label";
						if (is_array($this->data[$i][2])) {
							$type2 = strtoupper($this->data[$i][2][0]);
							$arg2 = $this->data[$i][2][0] . "@" . $this->data[$i][2][1];
							$arg2_type = "";
							if ($type2 == "LF" || $type2 == "GF" || $type2 == "TF") {
								fprintf(STDERR, "Syntax error: expected <symb> but get \"%s\"!\n", $arg2);
								exit(LEXSYN_ERROR);
							} else $arg2_type = $this->data[$i][2][0];

							if (is_array($this->data[$i][3])) {
								$type3 = strtoupper($this->data[$i][3][0]);
								$arg3 = $this->data[$i][3][0] . "@" . $this->data[$i][3][1];
								$arg3_type = "";
								if ($type3 == "LF" || $type3 == "GF" || $type3 == "TF") {
									fprintf(STDERR, "Syntax error: expected <symb> but get \"%s\"!\n", $arg3);
									exit(LEXSYN_ERROR);
								} else $arg3_type = $this->data[$i][3][0];
								$this->xml_instruction($this->data[$i][0], $arg1, $arg1_type, $arg2, $arg2_type, $arg3, $arg3_type);
							} else {
								fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
								exit(LEXSYN_ERROR);
							}
						} else {
							fprintf(STDERR, "Syntax error: expected <var> or <symb> but get \"%s\"!\n", $this->data[$i][1]);
							exit(LEXSYN_ERROR);
						}
					} else {
						fprintf(STDERR, "Syntax error: expected <var> or <symb> but get %s!\n", $this->data[$i][1]);
						exit(LEXSYN_ERROR);
					}
					break;

				default:
					echo "error ";
					print_r($this->data[$i]);
					break;
			}
		}
	}

	private function xml_header()
	{
		$this->xml = xmlwriter_open_memory();
		xmlwriter_set_indent($this->xml, 1);
		$res = xmlwriter_set_indent_string($this->xml, "\t");

		xmlwriter_start_document($this->xml, '1.0', 'UTF-8');

		// program element
		xmlwriter_start_element($this->xml, 'program');

		// Atribut language pro program
		xmlwriter_start_attribute($this->xml, "language");
		xmlwriter_text($this->xml, "IPPcode22");
		xmlwriter_end_attribute($this->xml);
	}

	private function xml_instruction(
		String $instruction,
		?String $arg1 = "",
		?String $arg1_type = "",
		?String $arg2 = "",
		?String $arg2_type = "",
		?String $arg3 = "",
		?String $arg3_type = ""
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
		xmlwriter_end_element($this->xml);
	}

	/**
	 * funkce vypíše xml na výstup
	 *
	 * Funkce vypíše na výstup zpracovaný IPPcode22 ve formátu XML.
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
