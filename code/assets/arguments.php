<?php
class Arguments
{
	public $arguments;
	public $short;
	public $long;

	public function __construct(String $shortopts = "", Array $longopts = array())
	{
		$this->short = $shortopts;
		$this->long = $longopts;
	}

	/**
	 * funkce přidá další možnost argumentu
	 *
	 * Funkce podle parametrů přidá další validní možnost argumentů
	 * na příkazové řádce př. add_valid_arg(c). c: očekává hodnotu
	 * c:: může být hodnota, ale nemusí
	 *
	 * @param String $arg Nový argument
	 * @param Bool $short Krátká verze
	 **/
	public function add_valid_arg(String $arg, Bool $long = false)
	{
		if ($long == false) {
			$this->short .= $arg;
		} else {
			array_push($this->long, $arg);
		}
	}

	/**
	 * funkce pro test parametrů
	 *
	 * Funkce kontroluje parametry zadané na přikazové řádce.
	 **/
	public function test()
	{
		$this->arguments = getopt($this->short, $this->long);
		$count = count($this->arguments);
		if ($count != 0) {
			if (array_search("help", array_keys($this->arguments)) != "" || array_search("h", array_keys($this->arguments)) != "") {
				print("Skript typu filtr nacte ze standardniho vstupu zdrojovy kod v IPPcode22,\n");
				print("zkontroluje lexikalni a syntaktickou spravnost kodu a vypise na standardni\n");
				print("vystup XML reprezentaci programu dle specifikace.\n");
				print("Doporucena verze PHP: 8.1\n");
				if ($count == 1)
					exit(0);
				else
					exit(PARAMS_ERROR);
			}
		}
	}
}
