<?php

//
// VUT FIT - IPP 1. uloha
// Patrik Cerbak - xcerba00
// 2022/2023
// 

ini_set('display_errors', 'stderr');

// zpracovavani argumentu skriptu
if($argc == 2) {
    if($argv[1] == '--help') {
        echo("Skript typu filtr (parse.php v jazyce PHP 8.1) nacte ze standardniho vstupu zdrojovy kod
v IPPcode23, zkontroluje lexikalni a syntaktickou spravnost kodu a vypise na standardni
vystup XML reprezentaci programu dle specifikace v zadani.\n");
    exit(0);
    } else {
        error_log('Neznamy argument!');
        exit(10);
    }
} else if($argc > 2) {
    error_log('Nepovoleny pocet argumentu!');
    exit(10);
}

// nastaveni xmlwriteru
$xml = xmlwriter_open_memory();
xmlwriter_set_indent($xml, 2);
xmlwriter_set_indent_string($xml, ' ');

// funkce, ktera vygeneruje zacatek instrukce $opcode
function startInstruction($xml, $order, $opcode) {
    xmlwriter_start_element($xml, 'instruction');
    xmlwriter_start_attribute($xml, 'order');
    xmlwriter_text($xml, (string)$order);
    xmlwriter_end_attribute($xml);
    xmlwriter_start_attribute($xml, 'opcode');
    xmlwriter_text($xml, strtoupper($opcode));
    xmlwriter_end_attribute($xml);
}

// funkce na ukonceni instrukce
function endInstruction($xml) {
    xmlwriter_end_element($xml);
}

// funkce na vygenerovani $num-teho argumentu instrukce s typem $type a vnitrnim samotnym textem $text
function writeArgument($xml, $num, $type, $text) {
    xmlwriter_start_element($xml, 'arg' . (string)$num);
    xmlwriter_start_attribute($xml, 'type');
    xmlwriter_text($xml, $type);
    xmlwriter_end_attribute($xml);
    xmlwriter_text($xml, $text);
    xmlwriter_end_element($xml);
}

// funkce na vygenerovani symbolu (specialni pripad generovani argumentu)
function writeSymbol($xml, $num, $string) {
    $symbolSplit = explode('@', $string);
    // rozhodovani, jestli jde o promennou nebo literal
    if($symbolSplit[0] == 'LF' || $symbolSplit[0] == 'TF' || $symbolSplit[0] == 'GF') {
        writeArgument($xml, $num, 'var', $string);
    } else {
        writeArgument($xml, $num, $symbolSplit[0], $symbolSplit[1]);
    }
}

// funkce na kontrolu, jestli je $checkString label (kontroluje pomoci regexu)
function checkLabel($checkString) {
    if(preg_match('/^[_\-$&%*!?a-zA-Z][_\-$&%*!?a-zA-Z0-9]*$/', $checkString)) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// funkce na kontrolu, jestli je $checkString promenna
function checkVar($checkString) {
    if(preg_match('/^(LF|TF|GF)@[_\-$&%*!?a-zA-Z][_\-$&%*!?a-zA-Z0-9]*$/', $checkString)) {
        return TRUE;
    } else {
        return FALSE;
    }
}

// funkce na kontrolu, jestli je $checkString symbol
function checkSymbol($checkString) {
    // nejdriv kontroluje, jestli jde o promennou
    if(preg_match('/^(LF|TF|GF)@[_\-$&%*!?a-zA-Z][_\-$&%*!?a-zA-Z0-9]*$/', $checkString)) {
        return TRUE;
    } else {
        // pak kontroluje jestli neni int, bool, string nebo nil
        if(preg_match('/^int@[\+|-]?([0-9]+|0[oO][0-7]+|0[xX][0-9a-fA-F]+)$/', $checkString) ||
           preg_match('/^bool@(true|false)$/', $checkString) ||
           preg_match('/^string@([^\\#]|(\\[0-9][0-9][0-9]))+$/', $checkString) ||
           preg_match('/^nil@nil$/', $checkString)) {
            return TRUE;
        }
    }
    return FALSE;
}

// kontrola, jestli je $checkString nejaky typ (konkretne int, string, bool nebo nil)
function checkType($checkString) {
    if($checkString == 'int' || $checkString == 'string' ||
       $checkString == 'bool' || $checkString == 'nil') {
        return TRUE;
    } else {
        return FALSE;
    }
}

$order = 1; // promenna na ulozeni cisla aktualni instrukce
$headerOk = FALSE; // promenna na ulozeni, zda jiz byla nactena hlavicka programu

// while cyklus na nacitani vstupu
while($line = fgets(STDIN)) {
    // odstraneni komentaru a nahrada n mezer a jinych bilych znaku za jednu mezeru
    $line = explode('#', trim($line))[0];
    $line = preg_replace('/\s+/', ' ', $line);

    // kontrola hlavicky programu
    if(trim($line) == '.IPPcode23') {
        if($headerOk) {
            error_log('Vice nez jedna hlavicka!');
            exit(21);
        }
        // vygeneruje zakladni XML hlavicku
        xmlwriter_start_document($xml, '1.0', 'UTF-8');
        xmlwriter_start_element($xml, 'program');
        xmlwriter_start_attribute($xml, 'language');
        xmlwriter_text($xml, 'IPPcode23');
        xmlwriter_end_attribute($xml);
        $headerOk = TRUE;
        continue;
    }

    // kdyz narazi na prazdny radek (orezany komentar) nebo na odradkovani, tak ho preskoci
    if($line == '' || substr($line, 0, 1) == "\n") {
        continue;
    }

    // kdyz se program dostal zde, tak uz musela byt hlavicka pritomna a zkontrolovana
    if(!$headerOk) {
        error_log('Chybejici nebo spatna hlavicka!');
        exit(21);
    }

    // rozdeli nacteny radek podle mezery
    $splitted = explode(' ', trim($line));
    // switch statement porovnava prvni prvek z pole s instrukcemi
    switch(strtoupper($splitted[0])) {
        // ... <var> <symb>
        case 'MOVE':
        case 'NOT':
        case 'INT2CHAR':
        case 'STRLEN':
        case 'TYPE':
            // zkontroluje spravny pocet argumentu instrukce a pomoci predem definovanych funkci
            // zkontroluje spravnost argumentu (zde funkce checkVar a checkSymbol)
            if(count($splitted) == 3 && checkVar($splitted[1]) && checkSymbol($splitted[2])) {
                // pomoci funkci vygeneruje instrukci a jeji argumenty
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'var', $splitted[1]);
                writeSymbol($xml, 2, $splitted[2]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ...
        case 'CREATEFRAME':
        case 'PUSHFRAME':
        case 'POPFRAME':
        case 'RETURN':
        case 'BREAK':
            if(count($splitted) == 1) {
                startInstruction($xml, $order++, $splitted[0]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <var>
        case 'DEFVAR':
        case 'POPS':
            if(count($splitted) == 2 && checkVar($splitted[1])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'var', $splitted[1]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <label>
        case 'CALL':
            if(count($splitted) == 2 && checkLabel($splitted[1])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'label', $splitted[1]);
                endInstruction($xml);
            } else {
                exit(23);
            }
            break;
        // ... <symb>
        case 'PUSHS':
        case 'WRITE':
        case 'EXIT':
        case 'DPRINT':
            if(count($splitted) == 2 && checkSymbol($splitted[1])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeSymbol($xml, 1, $splitted[1]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <var> <symb1> <symb2>
        case 'ADD':
        case 'SUB':
        case 'MUL':
        case 'IDIV':
        case 'LT':
        case 'GT':
        case 'EQ':
        case 'AND':
        case 'OR':
        case 'STRI2INT':
        case 'CONCAT':
        case 'GETCHAR':
        case 'SETCHAR':
            if(count($splitted) == 4 && checkVar($splitted[1]) && checkSymbol($splitted[2]) && checkSymbol($splitted[3])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'var', $splitted[1]);
                writeSymbol($xml, 2, $splitted[2]);
                writeSymbol($xml, 3, $splitted[3]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <var> <type>
        case 'READ':
            if(count($splitted) == 3 && checkVar($splitted[1]) && checkType($splitted[2])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'var', $splitted[1]);
                writeArgument($xml, 2, 'type', $splitted[2]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <label>
        case 'LABEL':
        case 'JUMP':
            if(count($splitted) == 2 && checkLabel($splitted[1])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'label', $splitted[1]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        // ... <label> <symb1> <symb2>
        case 'JUMPIFEQ':
        case 'JUMPIFNEQ':
            if(count($splitted) == 4 && checkLabel($splitted[1]) && checkSymbol($splitted[2]) && checkSymbol($splitted[3])) {
                startInstruction($xml, $order++, $splitted[0]);
                writeArgument($xml, 1, 'label', $splitted[1]);
                writeSymbol($xml, 2, $splitted[2]);
                writeSymbol($xml, 3, $splitted[3]);
                endInstruction($xml);
            } else {
                error_log('Lexikalni nebo syntakticka chyba!');
                exit(23);
            }
            break;
        default:
            error_log('Neznamy nebo spatny operacni kod!');
            exit(22);
    }
}

xmlwriter_end_element($xml); // </program>
xmlwriter_end_document($xml);

// vypise vygenerovane xml na vystup
echo xmlwriter_output_memory($xml);
?>