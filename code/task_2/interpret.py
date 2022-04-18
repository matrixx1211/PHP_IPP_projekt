from copy import deepcopy
import errno
import getopt
from glob import glob
from pickle import GLOBAL
import sys
from threading import local
# import xml.dom.minidom as xml
import xml.parsers.expat as xml
import re

from attr import attr
# konstanty
import int_assets.constants as CONST

# globalní proměnná pro order
order = 1
# globalní proměnná pro zásobníkové příkazy
stack = []
# globální proměnná pro kontolu jestli se objevil element program
program = False
# globální proměnné pro příkaz
command = {
    "order": -1,
    "command": "",
    "arg1": "",
    "arg1_value": "",
    "arg2": "",
    "arg2_value": "",
    "arg3": "",
    "arg3_value": ""
}
# globální proměnná pro příkazy
commands = []
# globální proměnné pro GF, LF a TF
global_frame_vars = []
local_frame_vars = None
temp_frame_vars = None


def print_help():
    ''' Funkce pro výpis nápovědy '''
    print("Interpret usage:")
    print("->This program accepts 3 parameters, but always you must enter one of them,")
    print("which means you must enter either --source or --input.")
    print("If you enter only one the other one will be take from stdin.")
    print("\t-h or --help <- prints usage")
    print("\t-s or --source <- XML file with source code for instance -s example.xml or --source=example.xml")
    print("\t-i or --input <- file with inputs for instance -i example.txt or --input=example.txt")


def args_test():
    ''' Testování argumentů a jejich počet '''
    try:
        opts, args = getopt.getopt(
            sys.argv[1:], "hs:i:", ("help", "source=", "input="))
        # print(sys.argv[0:]) #!DEBUG
        args_count = len(opts)
        source_filename = ""
        input_filename = ""
        help_entered = False
        source_entered = False
        input_entered = False
        if (args_count >= 1):
            for opt, val in opts:
                if (opt == "-h" or opt == "--help"):
                    help_entered = True
                elif (opt == "-s" or opt == "--source"):
                    source_entered = True
                    source_filename = val
                elif (opt == "-i" or opt == "--input"):
                    input_entered = True
                    input_filename = val
            if (help_entered or ((not source_entered) and (not input_entered))):
                print_help()
        else:
            print_help()

        return source_filename, input_filename
    except getopt.GetoptError as err:
        print("Error:", err, file=sys.stderr)
        exit(CONST.PARAM_MISS_OR_COMBINATION)


def file_handler(filename):
    ''' Otevře zadaný soubor nebo vráti False pro stdin '''
    data = []
    if (filename):
        try:
            file = open(filename, "r")
            if (file):
                with file:
                    for line in file:
                        data.append(line)
            return data
        except FileNotFoundError:
            print("Error: File not exists or is not available right now.",
                  file=sys.stderr)
            exit(CONST.FILE_INPUT)
    else:
        ''' for line in iter(input, ''):
            print(line) '''
        ''' file = sys.stdin
        input_line = file.read()
        if input_line:
            data.append() '''
    return data


def start_element_handler(name, attrs):
    global program
    global order
    global command
    # Funkce, která zpracovává počáteční elementy z xml dat
    if name == "program":
        program = True
        if (attrs['language'].upper() != "IPPCODE22"):
            print("Error: Header doesnt exist.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)
    if name == "instruction":
        command["command"] = attrs["opcode"].upper()
        command["order"] = int(attrs["order"])
        if (int(attrs["order"]) <= 0):
            print("Error: Unexpected order number.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)
    if name == "arg1":
        command["arg1"] = attrs
    if name == "arg2":
        command["arg2"] = attrs
    if name == "arg3":
        command["arg3"] = attrs
    # print('Start element:', name, attrs) #!DEBUG


def char_data_handler(data):
    # Funkce, která zpracovává hodnoty z elementů z xml dat
    if (data.strip()):
        global command
        if not command["arg1_value"]:
            command["arg1_value"] = data
        elif command["arg1_value"] and not command["arg2_value"]:
            command["arg2_value"] = data
        elif command["arg2_value"] and not command["arg3_value"]:
            command["arg3_value"] = data
        else:
            print("Error: Unexpected arg value.", file=sys.stderr)
            exit(CONST.SEM_CHECK)
    # print('Character data:', repr(data)) #!DEBUG


def end_element_handler(name):
    global command
    global commands
    # Funkce, která zpracovává koncové elementy z xml dat
    # vlastně nic nezpracovává jen dává vědet, který element byl ukončen
    if (name == "instruction"):
        commands.append(command.copy())

        # vyčistit command
        command["order"] = 0
        command["arg1"] = ""
        command["arg2"] = ""
        command["arg3"] = ""
        command["command"] = ""
        command["arg1_value"] = ""
        command["arg2_value"] = ""
        command["arg3_value"] = ""

    # pass
    # print('End element:', name) #!debug


def find_variable_value(c, where):
    if (c[where]['type'] == "var"):
        if ("GF" in c[where+"_value"]):
            for var in global_frame_vars:  # TODO -> hledat ve framech+
                if (var["name"] == c[where+"_value"]):
                    return var["value"]
        elif ("TF" in c[where+"_value"]):
            print("TF")
            return "TF"
        elif ("LF" in c[where+"_value"]):
            print("LF")
            return "LF"
    elif (c[where]['type'] == "int"):
        return int(c[where+"_value"])


def change_variable_value(c):
    if (c["command"] == "INT2CHAR"):
        if (c["arg2"]["type"] == "var"):
            new = find_variable_value(c, "arg2")
        for var in global_frame_vars:
            if (var["name"] == c["arg1_value"]):
                var["value"] = chr(int(new))


def interpret(c):
    # funkce bere jako parametr příkaz c a zpracováva ho
    global global_frame_vars
    global temp_frame_vars
    global local_frame_vars
    global stack
    # funkce interpretuje příkazy a jako argument získává jeden přikaz
    if (c["command"] == "CREATEFRAME"):
        temp_frame_vars = []
    elif (c["command"] == "PUSHFRAME"):
        # pokud je prázdný dočasný rámec dojde k chybě
        if (temp_frame_vars == None):
            print("Error: Frame doesn't exists.", file=sys.stderr)
            exit(CONST.RUNTIME_NOT_EXIST_FRAME)
        # pokud je neinicializováný lokální rámec inicializuju
        if (local_frame_vars == None):
            local_frame_vars = []  
        # přejmenování TF -> LF, protože se referuju k proměnným jako k LF@name a ne name
        for var in temp_frame_vars:
            var["name"] = var["name"].replace("TF@", "LF@")             
        # vložím dočasný rámec do lokálního
        local_frame_vars.append(temp_frame_vars)
        # nastavím dočasný rámec na neinicializovaný
        temp_frame_vars = None
    elif (c["command"] == "POPFRAME"):
        # pokud existuje nějaký lokální rámec
        if (local_frame_vars != None):
            # do dočasného rámce dám lokální
            temp_frame_vars = local_frame_vars.pop()
            # kontrola jestli už náhodou není zásobník lokalních rámců prázdný
            if (not local_frame_vars):
                local_frame_vars = None
            # přejmenování LF -> TF, protože se referuju k proměnným jako k TF@name a ne name
            for var in temp_frame_vars:
                var["name"] = var["name"].replace("LF@", "TF@")      
        # jestliže je prázdný zásobník lokálních rámců
        else:
            print("Error: No local frame to pop.", file=sys.stderr)
            exit(CONST.RUNTIME_NOT_EXIST_FRAME)
    elif (c["command"] == "RETURN"):
        pass
    elif (c["command"] == "BREAK"):
        pass
    elif (c["command"] == "DEFVAR"):
        # vkládání globálních proměnných do pole glob. prom. #! možná lepší místo "GF@" in re.match()... 
        if (c["arg1"]["type"] == "var"):
            if ("GF@" in c["arg1_value"]):
                gf_dict = {}
                # pokud se vyskytuje proměnná stejného názvu dojde k chybě
                if (list(filter(lambda var: var['name'] == c["arg1_value"], global_frame_vars))):
                    print(
                        f"Error: Variable redefinition => \"{c['arg1_value']}\".", file=sys.stderr)
                    exit(CONST.SEM_CHECK)
                else:
                    gf_dict["name"] = c["arg1_value"]
                    gf_dict["value"] = ""
                    global_frame_vars.append(gf_dict.copy())
            elif ("TF@" in c["arg1_value"]):
                # pokud ještě nebyl vytvořený dočasný rámec dojde k chybě
                if (temp_frame_vars == None):
                    print("Error: Temporary frame doesn't exists.", file=sys.stderr)
                    exit(CONST.RUNTIME_NOT_EXIST_FRAME)
                # pokud existuje přidá se proměnná do pole dočasných proměnných
                else:
                    tf_dict = {}
                    # pokud se vyskytuje proměnná stejného názvu dojde k chybě
                    if (list(filter(lambda var: var['name'] == c["arg1_value"], temp_frame_vars))):
                        print(
                            f"Error: Variable redefinition => \"{c['arg1_value']}\".", file=sys.stderr)
                        exit(CONST.SEM_CHECK)
                    # jinak se vloží
                    else:
                        tf_dict["name"] = c["arg1_value"]
                        tf_dict["value"] = ""
                        temp_frame_vars.append(tf_dict.copy())
            elif ("LF@" in c["arg1_value"]):
                # pokud ještě nebyl vytvořený žádný lokální rámec dojde k chybě
                if (local_frame_vars == None):
                    print("Error: Local frame doesn't exists.", file=sys.stderr)
                    exit(CONST.RUNTIME_NOT_EXIST_FRAME)
                # pokud existuje přidá se proměnná do pole dočasných proměnných
                else:
                    lf_dict = {}
                    # pokud se vyskytuje proměnná stejného názvu dojde k chybě
                    top = local_frame_vars.pop()
                    if (list(filter(lambda var: var['name'] == c["arg1_value"], top))):
                        print(f"Error: Variable redefinition => \"{c['arg1_value']}\".", file=sys.stderr)
                        exit(CONST.SEM_CHECK)
                    # jinak se vloží
                    else:
                        lf_dict["name"] = c["arg1_value"]
                        lf_dict["value"] = ""
                        top.append(lf_dict.copy())
                        local_frame_vars.append(top.copy())
        else: 
            print(f"Error: Unexpected type, get \"{c['arg1']['type']}\" expected \"var\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "PUSHS"):
        stack.append(c)
    elif (c["command"] == "POPS"):
        if (c["arg1"]["type"] == "var"):
            for var in global_frame_vars:
                if (var["name"] == c["arg1_value"]):
                    var["value"] = stack.pop()["arg1_value"]
        # print(gf_vars) #!smazat
        pass
    elif (c["command"] == "WRITE"):
        print(find_variable_value(c, "arg1"), end="")
    elif (c["command"] == "EXIT"):  # !ošetřit když je to str nebo něco jiného než int
        exit_value = int(find_variable_value(c, "arg1"))
        if (exit_value >= 0 and exit_value <= 49):
            exit(exit_value)
        else:
            print("Error: Unexpected exit code number.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_VALUE)
    elif (c["command"] == "DPRINT"):
        pass
    elif (c["command"] == "CALL"):
        pass
    elif (c["command"] == "LABEL"):
        pass
    elif (c["command"] == "JUMP"):
        pass
    elif (c["command"] == "MOVE"):
        pass
    elif (c["command"] == "INT2CHAR"):
        change_variable_value(c)
    elif (c["command"] == "READ"):
        pass
    elif (c["command"] == "STRLEN"):
        pass
    elif (c["command"] == "TYPE"):
        pass
    elif (c["command"] == "ADD"):
        pass
    elif (c["command"] == "SUB"):
        pass
    elif (c["command"] == "MUL"):
        pass
    elif (c["command"] == "IDIV"):
        pass
    elif (c["command"] == "LT"):
        pass
    elif (c["command"] == "GT"):
        pass
    elif (c["command"] == "EQ"):
        pass
    elif (c["command"] == "AND"):
        pass
    elif (c["command"] == "OR"):
        pass
    elif (c["command"] == "NOT"):
        pass
    elif (c["command"] == "STRI2INT"):
        pass
    elif (c["command"] == "CONCAT"):
        pass
    elif (c["command"] == "GETCHAR"):
        pass
    elif (c["command"] == "SETCHAR"):
        pass
    elif (c["command"] == "JUMPIFEQ"):
        pass
    elif (c["command"] == "JUMPIFNEQ"):
        pass


def xml_encoding_set(version, encoding, standalone):
    sys.stdout.reconfigure(encoding=encoding)


def main():
    # test argumentů
    source_filename, input_filename = args_test()

    # otevření souborů a načtení dat do proměnných
    source_data = file_handler(source_filename)
    input_data = file_handler(input_filename)

    # vytvoření a nastavení parseru pro xml
    parser = xml.ParserCreate()
    parser.StartElementHandler = start_element_handler
    parser.CharacterDataHandler = char_data_handler
    parser.EndElementHandler = end_element_handler
    parser.XmlDeclHandler = xml_encoding_set

    # procházím vstupní data a ukládám si je do datové struktury
    for tag in source_data:
        parser.Parse(tag)

    # pokud se v programu vůbec nevyskytuje tag program
    if (not program):
        print("Error: Program element doesnt exist in this file.", file=sys.stderr)
        exit(CONST.XML_UNEXPECTED_STRUCT)

    # seřazení příkazů podle orderu
    global commands
    commands = sorted(commands, key=lambda dict: dict['order'])

    # kontrola orderu
    last_ord = 0
    for c in commands:
        if (last_ord != c["order"] and c["order"] > 0):
            last_ord = c["order"]
        else:
            print("Error: Unexpected order number.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)

    # interpretace příkazů
    for c in commands: #! vrátí index odkud má pokračovat interpretace LABEL CALL RETURN řešení asi -> (:_:)
        interpret(c)
        
    print(temp_frame_vars)
    print(local_frame_vars)
    
    # ukončení
    exit(CONST.SUCCESS)


if __name__ == '__main__':
    main()
