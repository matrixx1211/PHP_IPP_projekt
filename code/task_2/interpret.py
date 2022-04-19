from copy import deepcopy
import errno
import getopt
from glob import glob
from operator import index
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
    "order": None,
    "command": None,
    "arg1": None,
    "arg1_value": None,
    "arg2": None,
    "arg2_value": None,
    "arg3": None,
    "arg3_value": None
}
# globální proměnná pro příkazy
commands = []
# globální proměnné pro GF, LF a TF
global_frame_vars = []
local_frame_vars = None
temp_frame_vars = None
# globální proměnná pro čítač instrukcí
instruction_counter = 0
# globální proměnná zásobník návratovových indexů
call_index_stack = []
# globální proměnná pro 
labels = []


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
                        data.append(line.replace("\n", "").strip())
            return data
        except FileNotFoundError:
            print("Error: File not exists or is not available right now.",file=sys.stderr)
            exit(CONST.FILE_INPUT)
    else:
        file = sys.stdin
        data = file.read().split("\n")
        # zahodím poslední hodnotu, protože podle unixu končí soubor
        # prázdným řádkem a split mi udělá jeden záznam, který je prázdný
        data.pop() 
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


def end_element_handler(name):
    global command
    global commands
    # Funkce, která zpracovává koncové elementy z xml dat
    # vlastně nic nezpracovává jen dává vědet, který element byl ukončen
    if (name == "instruction"):
        commands.append(command.copy())

        # vyčistit command
        command["order"] = None
        command["arg1"] = None
        command["arg2"] = None
        command["arg3"] = None
        command["command"] = None
        command["arg1_value"] = None
        command["arg2_value"] = None
        command["arg3_value"] = None


def find_variable_values(type, val):
    if (type == "var"):
        if ("GF" in val):
            for var in global_frame_vars:
                if (var["name"] == val):
                    return var
        elif ("TF" in val):
            if temp_frame_vars != None:
                for var in temp_frame_vars:
                    if (var["name"] == val):
                        return var
            else: 
                print("Error: Temporary frame doesn't exists.", file=sys.stderr)
                exit(CONST.RUNTIME_NOT_EXIST_FRAME)
        elif ("LF" in val):
            if local_frame_vars != None:
                last_local_frame = local_frame_vars.pop()
                for var in last_local_frame:
                    if (var["name"] == val):
                        local_frame_vars.append(last_local_frame)
                        return var
            else:
                print("Error: Local frame doesn't exists.", file=sys.stderr)
                exit(CONST.RUNTIME_NOT_EXIST_FRAME)
        print(f"Error: Variable not exists => \"{val}\".", file=sys.stderr)
        exit(CONST.RUNTIME_NOT_EXIST_VAR)
        # nenalezeno v žádném z rámců
    elif (type == "int" or type == "string" or type == "bool" or type == "nil"):
        value = {}
        value["name"] = None
        value["value"] = val
        value["type"] = type
        return value


def change_variable_value(old, new):
    old["value"] = new["value"]
    old["type"] = new["type"]

def check_variable_value(var):
    # kontrola jestli má zadaná proměnná zadanou hodnotu
    if var["type"] == None or var["value"] == None:
        print(f"Error: Variable doesn't have value => \"{var['name']}\".", file=sys.stderr)
        exit(CONST.RUNTIME_MISSING_VALUE)
    else:
        return var

def calculate(c, op):
    var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
    left = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
    right = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
    lv = int(left["value"])
    rv = int(right["value"])
    if (left["type"] == "int" and right["type"] == "int"):
        if (op == "+"):
            var["value"] = str(lv + rv)
        elif (op == "-"):
            var["value"] = str(lv - rv)
        elif (op == "*"):
            var["value"] = str(lv * rv)
        elif (op == "/"):
            if (rv == 0):
                print(f"Error: Division by zero.", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_OP_VALUE)
            else:
                var["value"] = str(lv // rv)
        var["type"] = "int"
    else: 
        print(f"Error: Unexpected types, get \"{left['type']}\" and \"{right['type']}\" expected \"int\" and \"int\".", file=sys.stderr)
        exit(CONST.RUNTIME_BAD_OP_TYPES)
    
def evaluate_bool(c, op):
    var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
    left = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
    if (op != "not"):
        right = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (left["type"] != right["type"]):
            print(f"Error: Unexpected types, \"{left['type']}\" and \"{right['type']}\" expected same.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    if (left["type"] == "int" or left["type"] == "string" or left["type"] == "bool" or left["type"] == "nil"):
        if (op == "<" or op == ">"):
            if(left["type"] == "nil"):
                print(f"Error: Unexpected types, expected \"(int,string,bool)\".", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_OP_TYPES)
            else:
                if (op == "<"):
                    if (left["value"] < right["value"]):
                        var["value"] = "true"
                    else:
                        var["value"] = "false"
                elif (op == ">"):
                    if (left["value"] > right["value"]):
                        var["value"] = "true"
                    else:
                        var["value"] = "false"
        elif (op == "="):
            if (left["value"] == right["value"]):
                var["value"] = "true"
            else:
                var["value"] = "false"
        elif (op == "and" or op == "or" or op == "not"):
            if (left["type"] == "bool"):
                if (op == "and"):    
                    if ((left["value"] == right["value"]) and left["value"] == "true"):
                        var["value"] = "true"
                    else:
                        var["value"] = "false"
                elif (op == "or"):
                    if (left["value"] == "true" or right["value"] == "true"):
                        var["value"] = "true"
                    else:
                        var["value"] = "false"
                elif (op == "not"):
                    if (left["value"] == "false"):
                        var["value"] = "true"
                    else:
                        var["value"] = "false"
            else:
                print(f"Error: Unexpected type, get \"{left['type']}\" expected \"bool\".", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_OP_TYPES)
    else:
        print(f"Error: Unexpected types, expected \"(int,string,bool,nil)\".", file=sys.stderr)
        exit(CONST.RUNTIME_BAD_OP_TYPES)
            
    var["type"] = "bool"
    
def print_error(c, msg, code):
    print(f"Error:\t{msg} Located in:", file=sys.stderr)
    print(f"\tCommand:\t{c['command']}", file=sys.stderr)
    print(f"\tOrder:\t\t{c['order']}", file=sys.stderr)
    print(f"\tArg1:\t\t{c['arg1']}", file=sys.stderr)
    print(f"\tArg2:\t\t{c['arg2']}", file=sys.stderr)
    print(f"\tArg3:\t\t{c['arg3']}", file=sys.stderr)
    print(f"\tArg1 value:\t{c['arg1_value']}", file=sys.stderr)
    print(f"\tArg2 value:\t{c['arg2_value']}", file=sys.stderr)
    print(f"\tArg3 value:\t{c['arg3_value']}", file=sys.stderr)
    exit(code)
    
def interpret(c, in_data):
    # funkce bere jako parametr příkaz c a zpracováva ho
    global global_frame_vars
    global temp_frame_vars
    global local_frame_vars
    global stack
    global labels
    global instruction_counter
    global call_index_stack
    # funkce interpretuje příkazy a jako argument získává jeden přikaz
    if (c["command"] == "CREATEFRAME"):
        temp_frame_vars = []
    elif (c["command"] == "PUSHFRAME"):
        # pokud je prázdný dočasný rámec dojde k chybě
        if (temp_frame_vars == None):
            print_error(c, f"Temporary frame doesn't exists.", CONST.RUNTIME_NOT_EXIST_FRAME)
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
            #print_error(c, )
            print("Error: No local frame to pop.", file=sys.stderr)
            exit(CONST.RUNTIME_NOT_EXIST_FRAME)
    elif (c["command"] == "BREAK"):
        print(f"---------------------------------------------------------------------", file=sys.stderr)
        print(f"State: \tcurrent command is \"{c['command']}\" with order \"{c['order']}\" on index \"{instruction_counter}\"!", file=sys.stderr)
        
        print(f"\tcontent of stack:", file=sys.stderr)
        if stack:
            for i in stack:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"\tcontent of global frame:", file=sys.stderr)
        if global_frame_vars:
            for i in global_frame_vars:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"\tcontent of local frame:", file=sys.stderr)
        if local_frame_vars != None:
            for i in local_frame_vars:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"\tcontent of temp frame:", file=sys.stderr)
        if temp_frame_vars != None:
            for i in temp_frame_vars:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"\tcontent of labels:", file=sys.stderr)
        if labels:
            for i in labels:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"\tcontent of call index stack:", file=sys.stderr)
        if call_index_stack:
            for i in call_index_stack:
                print(f"\t\t{i}", file=sys.stderr)
        else:
            print(f"\t\tEmpty", file=sys.stderr)
            
        print(f"---------------------------------------------------------------------", file=sys.stderr)
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
                    gf_dict["value"] = None
                    gf_dict["type"] = None
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
                        tf_dict["value"] = None
                        tf_dict["type"] = None
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
                        lf_dict["value"] = None
                        lf_dict["type"] = None
                        top.append(lf_dict.copy())
                        local_frame_vars.append(top.copy())
        else: 
            print(f"Error: Unexpected type, get \"{c['arg1']['type']}\" expected \"var\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "PUSHS"):
        stack.append(c)
    elif (c["command"] == "POPS"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        if (not stack):
            print(f"Error: Cannot perform pop, stack is empty.", file=sys.stderr)
            exit(CONST.RUNTIME_MISSING_VALUE)
        else:
            pop = stack.pop()
            var["value"] = pop["arg1_value"]
            var["type"] = pop["arg1"]["type"]
    elif (c["command"] == "WRITE"):
        var = check_variable_value(find_variable_values(c["arg1"]["type"], c["arg1_value"]))
        string = var["value"]
        for escape in re.findall("\\\\[0-9]{3}", string):
            string = string.replace(escape, chr(int(escape.replace("\\", ""))))
        print(string, end="")
    elif (c["command"] == "EXIT"):
        # otestuju, jestli lze převést
        var = check_variable_value(find_variable_values(c["arg1"]["type"], c["arg1_value"]))
        exit_value = var["value"]
        if (var["type"] != "int"):    
            print("Error: Unexpected exit code type.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_VALUE)
        exit_value = int(exit_value)
        if (exit_value >= 0 and exit_value <= 49):
            exit(exit_value)
        else:
            print("Error: Unexpected exit code number.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_VALUE)
    elif (c["command"] == "DPRINT"):
        print(check_variable_value(find_variable_values(c["arg1"]["type"], c["arg1_value"]))["value"], end="", file=sys.stderr)
    elif (c["command"] == "MOVE"):
        change_variable_value(find_variable_values(c["arg1"]["type"], c["arg1_value"]), find_variable_values(c["arg2"]["type"], c["arg2_value"]))
    elif (c["command"] == "INT2CHAR"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        char = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        if (char["type"] != "int"):    
            print(f"Error: Unexpected type, get \"{char['type']}\" expected \"int\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
        val = int(char["value"])
        if (val >= 0 and val <= 1114111):
            var["value"] = chr(val)
            var["type"] = "string"
        else:
            print("Error: Value is outside of range <0, 1 114 111> for command \"INT2CHAR\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_STRING)
    elif (c["command"] == "READ"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        type = c["arg2_value"]
        if (not in_data):
            val = "nil@nil"
            type = "nil"
        elif (type == "int" or type == "string" or type == "bool"):
            val = in_data.pop()
            if (type == "bool"):
                if (val == "true"):
                    val = "true"
                else:
                    val = "false"
        else: 
            print(f"Error: Unexpected type, get \"{type}\" expected \"(int, string, bool)\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
        var["value"] = val
        var["type"] = type
    elif (c["command"] == "STRLEN"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        len_var = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        if (len_var["type"] == "string"):
            var["value"] = str(len(len_var["value"]))
            var["type"] = "int"
        else:
            print(f"Error: Unexpected type, get \"{len_var['type']}\" expected \"string\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "TYPE"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        type_var = find_variable_values(c["arg2"]["type"], c["arg2_value"])
        if (type_var["type"] != None):
            var["value"] = type_var["type"]
        else:
            var["value"] = ""
        var["type"] = "string"
    elif (c["command"] == "ADD"):
        calculate(c, "+")
    elif (c["command"] == "SUB"):
        calculate(c, "-")
    elif (c["command"] == "MUL"):
        calculate(c, "*")
    elif (c["command"] == "IDIV"):
        calculate(c, "/")
    elif (c["command"] == "LT"):
        evaluate_bool(c, "<")
    elif (c["command"] == "GT"):
        evaluate_bool(c, ">")
    elif (c["command"] == "EQ"):
        evaluate_bool(c, "=")
    elif (c["command"] == "AND"):
        evaluate_bool(c, "and")
    elif (c["command"] == "OR"):
        evaluate_bool(c, "or")
    elif (c["command"] == "NOT"):
        evaluate_bool(c, "not")
    elif (c["command"] == "STRI2INT"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        string_var = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        pos_var = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (string_var["type"] == "string" and pos_var["type"] == "int"):
            if (len(string_var["value"]) > int(pos_var["value"]) and int(pos_var["value"]) >= 0):
                val = ord(str(string_var["value"][int(pos_var["value"])]))
                if (val >= 0 and val <= 1114111):
                    var["value"] = str(val)
                    var["type"] = "int"
                else:
                    print(f"Error: Value is outside of range <0, 1 114 111> for command \"INT2CHAR\".", file=sys.stderr)
                    exit(CONST.RUNTIME_BAD_STRING)
            else:
                print(f"Error: Index out of range.", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_STRING)
        else:
            print(f"Error: Unexpected types, \"{string_var['type']}\" and \"{pos_var['type']}\" expected \"string\" and \"int\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "CONCAT"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        string_a_var = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        string_b_var = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (string_a_var["type"] == string_b_var["type"] and string_a_var["type"] == "string"):
            var["value"] = string_a_var["value"] + string_b_var["value"]
            var["type"] = "string"
        else:
            print(f"Error: Unexpected types, \"{string_a_var['type']}\" and \"{string_b_var['type']}\" expected same.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "GETCHAR"):
        var = find_variable_values(c["arg1"]["type"], c["arg1_value"])
        string_var = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        pos_var = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (string_var["type"] == "string" and pos_var["type"] == "int"):
            if (len(string_var["value"]) > int(pos_var["value"]) and int(pos_var["value"]) >= 0):
                var["value"] = string_var["value"][int(pos_var["value"])]
                var["type"] = "string"
            else:
                print(f"Error: Index out of range.", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_STRING)
        else:
            print(f"Error: Unexpected types, \"{string_var['type']}\" and \"{pos_var['type']}\" expected \"string\" and \"int\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "SETCHAR"):
        var = check_variable_value(find_variable_values(c["arg1"]["type"], c["arg1_value"]))
        pos_var = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        string_var = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (string_var["type"] == "string" and pos_var["type"] == "int" and var["type"] == "string"):
            if (len(var["value"]) > int(pos_var["value"]) and int(pos_var["value"]) >= 0):
                if (len(string_var["value"]) > 0):
                    var["value"]= var["value"][:int(pos_var["value"])] + string_var["value"][0] + var["value"][int(pos_var["value"])+1:]
                    var["type"] = "string"
                else:
                    print(f"Error: Index out of range.", file=sys.stderr)
                    exit(CONST.RUNTIME_BAD_STRING)
            else:
                print(f"Error: Index out of range.", file=sys.stderr)
                exit(CONST.RUNTIME_BAD_STRING)
        else:
            print(f"Error: Unexpected types, \"{var['type']}\", \"{pos_var['type']}\" and \"{string_var['type']}\" expected \"string\", \"int\" and \"string\".", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    elif (c["command"] == "CALL"):
        label = list(filter(lambda var: var['name'] == c["arg1_value"], labels))
        if (label):
            call_index_stack.append({"return_pos": label[0]["index"], "call_pos": instruction_counter, "call_on_label": label[0]["name"]})
            return label[0]["index"]
        else: 
            print(f"Error: Can't perform \"CALL\", label \"{c['arg1_value']}\" doesn't exist.", file=sys.stderr)
            exit(CONST.SEM_CHECK)
    elif (c["command"] == "RETURN"):
        if (call_index_stack):
            pop = call_index_stack.pop()
            return pop["return_pos"]
        else: 
            print(f"Error: Can't perform \"RETURN\", the \"CALL\" wasn't called and call stack is empty.", file=sys.stderr)
            exit(CONST.RUNTIME_MISSING_VALUE)
    elif (c["command"] == "JUMP"):
        label = list(filter(lambda var: var['name'] == c["arg1_value"], labels))
        if (label):
            return label[0]["index"]
        else: 
            print(f"Error: Label \"{c['arg1_value']}\" doesn't exist.", file=sys.stderr)
            exit(CONST.SEM_CHECK)
    elif (c["command"] == "JUMPIFEQ" or c["command"] == "JUMPIFNEQ"):
        label = list(filter(lambda var: var['name'] == c["arg1_value"], labels))
        left = check_variable_value(find_variable_values(c["arg2"]["type"], c["arg2_value"]))
        right = check_variable_value(find_variable_values(c["arg3"]["type"], c["arg3_value"]))
        if (left["type"] == right["type"] or left["type"] == "nil" or right["type"]):
            if (not label):
                print(f"Error: Label \"{c['arg1_value']}\" doesn't exist.", file=sys.stderr)
                exit(CONST.SEM_CHECK)
                
            if (c["command"] == "JUMPIFEQ"):
                if (left["value"] == right["value"]):
                    return label[0]["index"]
                
            elif (c["command"] == "JUMPIFNEQ"):
                if (left["value"] != right["value"]):
                    return label[0]["index"]
            
        else: 
            print(f"Error: Unexpected types.", file=sys.stderr)
            exit(CONST.RUNTIME_BAD_OP_TYPES)
    
    return instruction_counter+1


def xml_encoding_set(version, encoding, standalone):
    sys.stdout.reconfigure(encoding=encoding)


def main():
    #! try -> pro chybu 99 možná zkusit
    # test argumentů
    source_filename, input_filename = args_test()

    # otevření souborů a načtení dat do proměnných
    source_data = file_handler(source_filename)
    input_data = file_handler(input_filename)
    input_data.reverse()

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

    # kontrola orderu + přidání si labelů
    global labels
    last_ord = 0
    for c in commands:
        if (last_ord != c["order"] and c["order"] > 0):
            last_ord = c["order"]
            if (c["command"] == "LABEL"):
                if (list(filter(lambda var: var['name'] == c["arg1_value"], labels))):
                    print(f"Error: Label redefinition => \"{c['arg1_value']}\".", file=sys.stderr)
                    exit(CONST.SEM_CHECK)
                else:
                    label = {}
                    label["index"] = commands.index(c)
                    label["name"] = c["arg1_value"]
                    labels.append(label)
        else:
            print("Error: Unexpected order number.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)

    # interpretace příkazů
    global instruction_counter
    i = 0
    while i < len(commands):
        instruction_counter = interpret(commands[instruction_counter], input_data)
        i = instruction_counter
    
    # ukončení
    exit(CONST.SUCCESS)


if __name__ == '__main__':
    try:
        main()
    except MemoryError:
        print(f"Error: Internal error.", file=sys.stderr)
        exit(CONST.INTERNAL)
