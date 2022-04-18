<?php
/**
 * IPP 2018 project 2 - test lib file for checking parse.php and interpret.py
 * @file html.php
 * @author Jiri Jurica
 */

function htmlInit() {
    global $dom;
    global $table;

    $dom = new DOMDocument("1.0", "UTF-8");
    $dom->formatOutput = true;  # Prettify output
    $implementation = new DOMImplementation();

    $html = $dom->createElement("html");
    $html->setAttribute("lang", "en");
    $dom->appendChild($html);

    $head = $dom->createElement("head");
    $html->appendChild($head);
    $meta = $dom->createElement("meta");
    $meta->setAttribute("charset", "UTF-8");
    $head->appendChild($meta);
    $title = $dom->createElement("title", "Test results");
    $head->appendChild($title);
    $style = $dom->createElement("style", '
        body {
            font-family: "Arial", sans-serif;
            color: #2c3e50;
            background: #ecf0f1;
        }

        table, td {
            border: 1px solid #2c3e50;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 10px;
        }

        td {
            padding-left: 20px;
        }

        a {
            float: right;
            margin-right: 20px;
            text-decoration: underline;
            cursor: pointer;
        }

        .green {
            background-color: #2ecc71;
        }

        .red {
            background-color: #e74c3c;
        }

        .newLine {
            white-space: pre-line;
        }

        .toggle {
            display: block;
            height: 80px;
            overflow: hidden;
        }
    ');
    $head->appendChild($style);
    $script = $dom->createElement("script", '
        function toggle(a) {
            var div = a.parentElement;
            
            if (div.style.height == "auto") {
                div.style.height = "80px";
                a.innerHTML = "SHOW MORE +"
            } else {
                div.style.height = "auto";
                a.innerHTML = "SHOW LESS -" 
            }
        }
    ');
    $head->appendChild($script);

    $body = $dom->createElement("body");
    $html->appendChild($body);
    $h1 = $dom->createElement("h1", "Test results");
    $body->appendChild($h1);
    $table = $dom->createElement("table");
    $body->appendChild($table);
}

function addTest($data) {
    global $dom;
    global $table;
    $error = 0;
    $diff = 0;
    $out = 0;

    #$dom = new DOMDocument("1.0", "UTF-8"); #!!!!!!!!!!!!!!
    #$table = $dom->createElement("table");

    # Create cell with name of test
    $tr = $dom->createElement("tr");
    $table->appendChild($tr);
    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", $data['name']);
    $td->appendChild($h3);

    # Cell with parse.php status
    $firstTextRow = $dom->createTextNode("parse.php");
    $br = $dom->createElement("br");
    $secondTextRow = $dom->createTextNode("rc expected: " . $data["parseExcRC"] . " real: " .
        $data["parseRealRC"]);

    $td = $dom->createElement("td");

    if ($data["parseExcRC"] == $data["parseRealRC"]) $td->setAttribute("class", "green");
    else {
        $td->setAttribute("class", "red");
        $error = 1;
    }

    $td->appendChild($firstTextRow);
    $td->appendChild($br);
    $td->appendChild($secondTextRow);
    $tr->appendChild($td);

    # Cell with interpret.py status
    $td = $dom->createElement("td");
    $br = $dom->createElement("br");
    $firstTextRow = $dom->createTextNode("interpret.py");

    if (!$error && !$data["parseRealRC"]) {
        $secondTextRow = $dom->createTextNode("rc expected: " . $data["intExcRC"] . " real: "
            . $data["intRealRC"]);
        if ($data["intExcRC"] == $data["intRealRC"]) $td->setAttribute("class", "green");
        else {
            $td->setAttribute("class", "red");
            $error = 1;
        }
    }
    else $secondTextRow = $dom->createTextNode("rc expected: - real: -");

    $td->appendChild($firstTextRow);
    $td->appendChild($br);
    $td->appendChild($secondTextRow);
    $tr->appendChild($td);

    # Cell with complete test status
    if (!$error && !$data["parseRealRC"] && !$data["intRealRC"]) {
        if ($data['diff'] != "") {
            $diff = 1;
            $out = 1;
            $error = 1;
        }
    }

    if ($error) {
        $td = $dom->createElement("td", "status fail");
        $td->setAttribute("class", "red");
    }
    else {
        $td = $dom->createElement("td", "status ok");
        $td->setAttribute("class", "green");
    }
    $tr->appendChild($td);

    # Row with output
    if ($out) {
        $tr = $dom->createElement("tr");
        $table->appendChild($tr);
        $td = $dom->createElement("td", "output");
        $tr->appendChild($td);
        $td = $dom->createElement("td");
        $td->setAttribute("colspan", "3");
        $div = $dom->createElement("div");
        $div->setAttribute("class", "newLine toggle");
        $td->appendChild($div);
        $a = $dom->createElement("a", "SHOW MORE +");
        $a->setAttribute("link", "#");
        $a->setAttribute("onclick", "toggle(this)");
        $text = $dom->createTextNode($data['intOut']);

        if (substr_count($data['intOut'], "\n") > 2) {
            $div->appendChild($a);
        }
        $div->appendChild($text);
        $tr->appendChild($td);
    }

    # Row with diff
    if ($diff) {
        $tr = $dom->createElement("tr");
        $table->appendChild($tr);
        $td = $dom->createElement("td", "diff");
        $tr->appendChild($td);
        $td = $dom->createElement("td");
        $td->setAttribute("colspan", "3");
        $div = $dom->createElement("div");
        $div->setAttribute("class", "newLine toggle");
        $td->appendChild($div);
        $a = $dom->createElement("a", "SHOW MORE +");
        $a->setAttribute("link", "#");
        $a->setAttribute("onclick", "toggle(this)");
        $text = $dom->createTextNode($data['diff']);

        if (substr_count($data['diff'], "\n") > 2) {
            $div->appendChild($a);
        }
        $div->appendChild($text);
        $tr->appendChild($td);
    }
}

/**Prints message on stderr if global var is set to true
 * @param $message
 */
function printLog($message) {
    global $verbose;
    if ($verbose) fwrite(STDERR, $message);
}
