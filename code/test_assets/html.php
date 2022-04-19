<?php

/**
 * IPP 2022 projekt 2 - testovací soubor pro parser a interpret
 * 
 * @author Marek Bitomský
 */

function htmlInit()
{
    global $dom;
    global $table;

    $dom = new DOMDocument("1.0", "UTF-8");
    $dom->formatOutput = true;

    $html = $dom->createElement("html");
    $html->setAttribute("lang", "en");
    $dom->appendChild($html);

    $head = $dom->createElement("head");
    $html->appendChild($head);
    $meta = $dom->createElement("meta");
    $meta->setAttribute("charset", "UTF-8");
    $head->appendChild($meta);
    $title = $dom->createElement("title", "IPP tests results");
    $head->appendChild($title);
    $style = $dom->createElement("style", '
        body {
            font-family: "Arial", sans-serif;
            color: #000;
            background: #ecf0f1;
        }

        table, td {
            border: 1px solid #000;
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
            background-color: #61e89b;
        }

        .red {
            background-color: #ff6657;
        }
        
        .not-test {
            background-color: #777;
        }

        .new-line {
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
    $h1 = $dom->createElement("h1", "IPP tests results");
    $body->appendChild($h1);
    $table = $dom->createElement("table");
    $body->appendChild($table);

    $tr = $dom->createElement("tr");
    $table->appendChild($tr);
    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", "Název testu");
    $td->appendChild($h3);

    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", "Parse.php");
    $td->appendChild($h3);

    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", "Interpret.py");
    $td->appendChild($h3);

    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", "Result");
    $td->appendChild($h3);
}

function addTest($data)
{
    global $dom;
    global $table;

    $error = 0;
    $diff = 0;
    $out = 0;


    // vytvoření buňky s názvem testu
    $tr = $dom->createElement("tr");
    $table->appendChild($tr);
    $td = $dom->createElement("td");
    $tr->appendChild($td);
    $h3 = $dom->createElement("h3", $data['name']);
    $td->appendChild($h3);

    // vytvoření buňky se stavem testu skriptu parse.php
    $first_text_row = $dom->createTextNode("parse.php");
    $br = $dom->createElement("br");
    $second_text_row = $dom->createTextNode("Return code expected: " . $data["parseExcRC"] . " got: " .
        $data["parseRealRC"]);

    $td = $dom->createElement("td");

    if ($data["parseExcRC"] == $data["parseRealRC"]) $td->setAttribute("class", "green");
    else {
        $td->setAttribute("class", "red");
        $error = 1;
    }
    if ($data["parseExcRC"] == -1) {
        $td->setAttribute("class", "not-test");
    }

    $td->appendChild($first_text_row);
    $td->appendChild($br);
    $td->appendChild($second_text_row);
    $tr->appendChild($td);

    // vytvoření buňky se stavem testu skriptu interpret.py
    $td = $dom->createElement("td");
    $br = $dom->createElement("br");
    $first_text_row = $dom->createTextNode("interpret.py");

    if (!$error) {
        $second_text_row = $dom->createTextNode("Return code expected: " . $data["intExcRC"] . " got: "
            . $data["intRealRC"]);
        if ($data["intExcRC"] == $data["intRealRC"])
            $td->setAttribute("class", "green");
        else {
            $td->setAttribute("class", "red");
            $error = 1;
        }
        if ($data["intExcRC"] == -1)
            $td->setAttribute("class", "not-test");
    } else $second_text_row = $dom->createTextNode("Return code expected: - got: -");

    $td->appendChild($first_text_row);
    $td->appendChild($br);
    $td->appendChild($second_text_row);
    $tr->appendChild($td);

    // vytvoření buňky se stavem testů
    if (!$error && !$data["intRealRC"]) {
        if ($data['diff'] != "") {
            $diff = 1;
            $out = 1;
            $error = 1;
        }
    }

    if ($error) {
        $td = $dom->createElement("td", "status fail");
        $td->setAttribute("class", "red");
    } else {
        $td = $dom->createElement("td", "status ok");
        $td->setAttribute("class", "green");
    }
    $tr->appendChild($td);

    // řádek s výstupy
    if ($out) {
        $tr = $dom->createElement("tr");
        $table->appendChild($tr);
        $td = $dom->createElement("td", "output");
        $tr->appendChild($td);
        $td = $dom->createElement("td");
        $td->setAttribute("colspan", "3");
        $div = $dom->createElement("div");
        $div->setAttribute("class", "new-line toggle");
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

    // řádek s diffem
    if ($diff) {
        $tr = $dom->createElement("tr");
        $table->appendChild($tr);
        $td = $dom->createElement("td", "diff");
        $tr->appendChild($td);
        $td = $dom->createElement("td");
        $td->setAttribute("colspan", "3");
        $div = $dom->createElement("div");
        $div->setAttribute("class", "new-line toggle");
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

// vypisuje debug log
function logger($message)
{
    global $debug;
    if ($debug) fwrite(STDERR, $message);
}
