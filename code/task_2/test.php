<?php
/**
 * IPP 2022 projekt 2 - testovací soubor pro parser a interpret
 * 
 * @author Marek Bitomský
 */


include 'test_assets/html.php';

# Global constants and vars
# Return codes
const badParameter = 10;
const inFileError = 11;
const outFileError = 12;
const internalError = 99;

# Html vars
$dom = null;
$table = null;

# Main control structure
# Parameter parsing
$shortOpts = "hd:rp:i:v";
$longOpts = array("help", "directory:", "recursive", "parse-script:", "int-script:", "verbose");
$getOpts = getopt($shortOpts, $longOpts);

if (array_key_exists("help", $getOpts) || array_key_exists("h", $getOpts)) {    # Prints help
    if ($argc == 2 && $argv[1] == "-h" || $argv[1] == "--help") {
        echo "test.php help:\n";
        echo "-h, --help                    Prints this help.\n";
        echo "-v, --verbose                 Prints debug information.\n";
        echo "-d PATH, --directory PATH     Set path to dir with tests, default is cwd.\n";
        echo "-r, --recursive               Recursive processing tests from dir.\n";
        echo "-p PATH, --parse-script PATH  Set path to parse.php script, default is ./parse.php.\n";
        echo "-i PATH, --int-script PATH    Set path to interpret.py script, default is ./interpret.py.\n";
        exit;
    }
    else exit(badParameter);
}

$dir = "./";
$rFlag = false;
$parseScript = "./parse.php";
$intScript = "./interpret.py";

if (array_key_exists("directory", $getOpts)) $dir = $getOpts["directory"];
elseif (array_key_exists("d", $getOpts)) $dir = $getOpts["d"];

if (array_key_exists("recursive", $getOpts) || array_key_exists("r", $getOpts)) $rFlag = true;

if (array_key_exists("parse-script", $getOpts)) $parseScript = $getOpts["parse-script"];
elseif (array_key_exists("p", $getOpts)) $parseScript = $getOpts["p"];

if (array_key_exists("int-script", $getOpts)) $intScript = $getOpts["int-script"];
elseif (array_key_exists("i", $getOpts)) $intScript = $getOpts["i"];

if (!file_exists($dir) || !file_exists($parseScript) || !file_exists($intScript)) exit(inFileError);

$verbose = false;   # Own debug parameter
if (array_key_exists("v", $getOpts) || array_key_exists("verbose", $getOpts)) $verbose = true;

printLog("Verbose mode is on\n");

// Load test paths
if ($rFlag) exec("find " . $dir . " -regex '.*\.src$'", $testPaths);
else exec("find " . $dir . " -maxdepth 1 -regex '.*\.src$'", $testPaths);

// Test processing
$parseOutput = tempnam("/tmp", "");
$intOutput= tempnam("/tmp", "");
htmlInit();

foreach ($testPaths as $src) {
    $pathParts = explode('/', $src);
    $testName = explode('.', end($pathParts))[0];
    $testPath = "";

    foreach (array_slice($pathParts, 0, -1) as $dir) {
        $testPath = $testPath . $dir . '/';
    }

    $in = $testPath . $testName . ".in";
    $out = $testPath . $testName . ".out";
    $rcFile = $testPath . $testName . ".rc";

    if (!file_exists($in)) {
        $file = fopen($in, "w");
        fclose($file);
    }

    if (!file_exists($out)) {
        $file = fopen($out, "w");
        fclose($file);
    }

    if (!file_exists($rcFile)) {
        $rc = 0;
        $file = fopen($rcFile, "w");
        fwrite($file, "0");
        fclose($file);
    }
    else {
        $file = fopen($rcFile, "r");
        $rc = intval(fread($file, filesize($rcFile)));
        fclose($file);
    }

    $htmlData = array();
    $htmlData['name'] = $testName;
    $htmlData['level'] = 1;

    printLog("Test " . $testName . "\n");
    printLog("Expected rc: " . $rc . "\n");

    // Run parse.php
    exec("php " . $parseScript . " < " . $src, $parseOut, $parseRc);
    $parseOut = shell_exec("php " . $parseScript . " < " . $src);

    $outputFile = fopen($parseOutput, "w");
    fwrite($outputFile, $parseOut);
    fclose($outputFile);

    printLog("parse.php rc: " . $parseRc . "\n");

    // Run interpret.py
    if ($parseRc == 0) {
        $htmlData['level'] = 2;
        $htmlData['parseExcRC'] = 0;
        $htmlData['parseRealRC'] = 0;

        exec("python3.8 " . $intScript . " --source=" . $parseOutput . " < " . $in, $intOut,
            $intRc);
        $intOut = shell_exec("python3.8 " . $intScript . " --source=" . $parseOutput . " < " . $in);

        $outputFile = fopen($intOutput, "w");
        fwrite($outputFile, $intOut);
        fclose($outputFile);

        $htmlData['intExcRC'] = $rc;
        $htmlData['intRealRC'] = $intRc;
        $htmlData['intOut'] = $intOut;

        if (!$intRc) {
            $diff = shell_exec("diff " . $out . " " . $intOutput);
            $htmlData['diff'] = $diff;
        }

        printLog("interpret.py rc: " . $intRc . "\n");
    }
    else {
        $htmlData['parseExcRC'] = $rc;
        $htmlData['parseRealRC'] = $parseRc;
    }

    addTest($htmlData);
}

unlink($parseOutput);
unlink($intOutput);

echo "<!DOCTYPE html>\n";
echo $dom->saveHTML();