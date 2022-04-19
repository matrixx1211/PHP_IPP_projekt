<?php

/**
 * IPP 2022 projekt 2 - testovací soubor pro parser a interpret
 * 
 * @author Marek Bitomský
 */

ini_set('display_errors', 'stderr');

// Návratový kódy
const PARAM_MISS_OR_COMBINATION = 10;
const FILE_INPUT = 11;
const FILE_OUTPUT = 12;
const INTERNAL = 99;

# HTML proměnné
$dom;
$table;

// pomocný soubor pro generování
include 'test_assets/html.php';

// kontrola parametrů
$short_opts = "hd:rp:i:PIj:nD";
$long_opts = array("help", "directory:", "recursive", "parse-script:", "int-script:", "parse-only", "int-only", "jexampath:", "noclean", "debug");
$getopt = getopt($short_opts, $long_opts);

// pokud je v poli getopt help vypisuje se nápověda
if (array_key_exists("help", $getopt) || array_key_exists("h", $getopt)) {
    if ($argc == 2 && $argv[1] == "-h" || $argv[1] == "--help") {
        echo "test.php help:\n";
        echo "-h, --help                    Prints this help.\n";
        echo "-d PATH, --directory PATH     Set path to dir with tests, default is cwd.\n";
        echo "-r, --recursive               Recursive processing tests from dir.\n";
        echo "-p PATH, --parse-script PATH  Set path to parse.php script, default is ./parse.php.\n";
        echo "-i PATH, --int-script PATH    Set path to interpret.py script, default is ./interpret.py.\n";
        echo "-P, --parse-only              Do only parse.php tests.\n";
        echo "-I, --int-only                Do only interpret.py tests.\n";
        echo "-j PATH, --jexampath PATH     Do only interpret.py tests.\n";
        echo "-n, --noclean                 Don't clean temporary files.\n";
        echo "-D, --debug                   Prints debug information.\n";
        exit;
    } else exit(PARAM_MISS_OR_COMBINATION);
}

$dir = "./";
$recursive = false;
$parse_path = "./parse.php";
$interpret_path = "./interpret.py";
$parse_only = false;
$int_only = false;
$jexam_path = "/pub/courses/ipp/jexamxml/";
$noclean = false;
$debug = false;

// nastavení directory
if (array_key_exists("directory", $getopt))
    $dir = $getopt["directory"];
elseif (array_key_exists("d", $getopt))
    $dir = $getopt["d"];

// rekurzivní průchod
if (array_key_exists("recursive", $getopt) || array_key_exists("r", $getopt))
    $recursive = true;

// nastavuje cestu pro parse skript
if (array_key_exists("parse-script", $getopt))
    $parse_path = $getopt["parse-script"];
elseif (array_key_exists("p", $getopt))
    $parse_path = $getopt["p"];

// nastavuje cestu pro int skript
if (array_key_exists("int-script", $getopt))
    $interpret_path = $getopt["int-script"];
elseif (array_key_exists("i", $getopt))
    $interpret_path = $getopt["i"];
// kontroluje jen parse.php testy
if (array_key_exists("parse-only", $getopt) || array_key_exists("P", $getopt))
    $parse_only = true;

// kontroluje jen interpret.py testy
if (array_key_exists("int-only", $getopt) || array_key_exists("I", $getopt))
    $int_only = true;

// kontrola kombinace parametrů
if ($parse_only == true && $int_only == true)
    exit(PARAM_MISS_OR_COMBINATION);
if ($parse_only == true && (array_key_exists("int-script", $getopt) || array_key_exists("i", $getopt)))
    exit(PARAM_MISS_OR_COMBINATION);
if ($int_only == true && (array_key_exists("parse-script", $getopt) || array_key_exists("p", $getopt)))
    exit(PARAM_MISS_OR_COMBINATION);

// nastavení cesty pro jexam 
if (array_key_exists("jexampath", $getopt))
    $jexam_path = $getopt["jexampath"];
elseif (array_key_exists("j", $getopt))
    $jexam_path = $getopt["j"];

// kontrola a přidáni / na konec jexam_path
if ($jexam_path[strlen($jexam_path) - 1] != "/") {
    $jexam_path .= "/";
}

// nemaže dočasné soubory
if (array_key_exists("noclean", $getopt) || array_key_exists("n", $getopt))
    $noclean = true;

// nejsou soubory
if (!file_exists($dir) || !file_exists($parse_path) || !file_exists($interpret_path))
    exit(FILE_INPUT);

// vlastní debug parametr
if (array_key_exists("D", $getopt) || array_key_exists("debug", $getopt))
    $debug = true;
logger("Debug mode on\n");

// Načtení cest všech testovacích souboru včetně rekurzivního projití
if ($recursive)
    exec("find " . $dir . " -regex '.*\.src$'", $test_files_paths);
else
    exec("find " . $dir . " -maxdepth 1 -regex '.*\.src$'", $test_files_paths);

// Zpracovávání samotných testů
$temp_parse_output = tempnam("/tmp", "xbitom00");
$temp_interpret_output = tempnam("/tmp", "xbitom00");
htmlInit();

// pro všechny testovací cesty postupně provádím testy
foreach ($test_files_paths as $src) {
    $path_exploded = explode('/', $src);
    $test_name = explode('.', end($path_exploded))[0];
    $test_path = "";

    // vytvoření testovací cesty
    foreach (array_slice($path_exploded, 0, -1) as $dir) {
        $test_path = $test_path . $dir . '/';
    }

    $test_input_file = $test_path . $test_name . ".in";
    $test_output_file = $test_path . $test_name . ".out";
    $return_code_file = $test_path . $test_name . ".rc";

    if (!file_exists($test_input_file)) {
        $file = fopen($test_input_file, "w");
        fclose($file);
    }

    if (!file_exists($test_output_file)) {
        $file = fopen($test_output_file, "w");
        fclose($file);
    }

    if (!file_exists($return_code_file)) {
        $return_code = 0;
        $file = fopen($return_code_file, "w");
        fwrite($file, "0");
        fclose($file);
    } else {
        $file = fopen($return_code_file, "r");
        $return_code = intval(fread($file, filesize($return_code_file)));
        fclose($file);
    }

    $html_data = array();
    $html_data['name'] = $test_name;
    $html_data['level'] = 1;

    logger("Test " . $test_name . "\n");
    logger("Expected return code: " . $return_code . "\n");

    if (($parse_only == $int_only) or ($parse_only == true)) {
        // spustí parse.php v php8.1
        exec("php8.1 " . $parse_path . " < " . $src, $parse_output, $parse_return_code);
        $parse_output = shell_exec("php8.1 " . $parse_path . " < " . $src);

        $output_file = fopen($temp_parse_output, "w");
        fwrite($output_file, $parse_output);
        fclose($output_file);

        $html_data['parseExcRC'] = $return_code;
        $html_data['parseRealRC'] = $parse_return_code;
        $html_data['intExcRC'] = -1;
        $html_data['intRealRC'] = -1;

        if ($parse_only == true) {
            $diff = shell_exec("java -jar " . $jexam_path . "jexamxml.jar " . $test_output_file . " " . $temp_parse_output . " /D" . " " . $jexam_path . "options");
            $html_data['diff'] = $diff;
        }

        logger("parse.php return code: " . $parse_return_code . "\n");
    }

    if (($parse_only == $int_only) or ($int_only == true)) {
        // spustí interpret.py v python3.8
        if ($int_only == true) {
            $html_data['level'] = 2;
            $html_data['parseExcRC'] = -1;
            $html_data['parseRealRC'] = -1;

            exec("python3.8 " . $interpret_path . " --source=" . $src . " < " . $test_input_file, $interpret_output, $interpret_return_code);
            $interpret_output = shell_exec("python3.8 " . $interpret_path . " --source=" . $src . " < " . $test_input_file);

            $output_file = fopen($temp_interpret_output, "w");
            fwrite($output_file, $interpret_output);
            fclose($output_file);

            $html_data['intExcRC'] = $return_code;
            $html_data['intRealRC'] = $interpret_return_code;
            $html_data['intOut'] = $interpret_output;

            if (!$interpret_return_code) {
                $diff = shell_exec("diff " . $test_output_file . " " . $temp_interpret_output);
                $html_data['diff'] = $diff;
            }

            logger("interpret.py return code: " . $interpret_return_code . "\n");
        } else {
            if ($parse_return_code == 0) {
                $html_data['level'] = 2;
                $html_data['parseExcRC'] = 0;
                $html_data['parseRealRC'] = 0;

                exec("python3.8 " . $interpret_path . " --source=" . $temp_parse_output . " < " . $test_input_file, $interpret_output, $interpret_return_code);
                $interpret_output = shell_exec("python3.8 " . $interpret_path . " --source=" . $temp_parse_output . " < " . $test_input_file);

                $output_file = fopen($temp_interpret_output, "w");
                fwrite($output_file, $interpret_output);
                fclose($output_file);

                $html_data['intExcRC'] = $return_code;
                $html_data['intRealRC'] = $interpret_return_code;
                $html_data['intOut'] = $interpret_output;

                if (!$interpret_return_code) {
                    $diff = shell_exec("diff " . $test_output_file . " " . $temp_interpret_output);
                    $html_data['diff'] = $diff;
                }

                logger("interpret.py return code: " . $interpret_return_code . "\n");
            } else {
                $html_data['parseExcRC'] = $return_code;
                $html_data['parseRealRC'] = $parse_return_code;
            }
        }
    }

    addTest($html_data);
}

if ($noclean == false) {
    unlink($temp_parse_output);
    unlink($temp_interpret_output);
}

echo "<!DOCTYPE html>\n";
echo $dom->saveHTML();
