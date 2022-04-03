import errno
import getopt
import sys
#import xml.dom.minidom as xml
import xml.parsers.expat as xml
import re

from attr import attr
# konstanty
import assets.constants as CONST

# globalní proměnná pro order
order = 1
# globalní proměnná pro zásobníkové příkazy
stack = []
# globální proměnná pro kontolu jestli se objevil element program
program = False
# globální proměnné pro příkaz
command = {
    "command": "",
    "arg1": "",
    "arg1_value": "",
    "arg2": "",
    "arg2_value": "",
    "arg3": "",
    "arg3_value": ""
}


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
        print(err)
        exit(0)


def file_handler(filename):
    ''' Otevře zadaný soubor nebo vráti False pro stdin '''
    if (filename):
        try:
            file = open(filename, "r")
            data = []
            if (file):
                with file:
                    for line in file:
                        data.append(line)
            return data
        except FileNotFoundError:
            print("Error: File not exists or is not available right now.",
                  file=sys.stderr)
            exit(CONST.FILE_INPUT)
    '''  else:
        for line in iter(input, ''):
            print(line) '''
    return False


def start_element_handler(name, attrs):
    # Funkce, která zpracovává počáteční elementy z xml dat
    if name == "program":
        global program
        program = True
        if (attrs['language'].upper() != "IPPCODE22"):
            print("Error: Header doesnt exist.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)
    if name == "instruction":
        global order
        if (str(order) == attrs['order']):
            order = order + 1
        else:
            print("Error: Unexpected order number.", file=sys.stderr)
            exit(CONST.XML_UNEXPECTED_STRUCT)
    global command
    if name == "arg1":
        command["arg1"] = attrs
    if name == "arg2":
        command["arg2"] = attrs
    if name == "arg3":
        command["arg3"] = attrs
    print('Start element:', name, attrs)


def end_element_handler(name):
    # Funkce, která zpracovává koncové elementy z xml dat
    # vlastně nic nezpracovává jen dává vědet, který element byl ukončen
    if (name == "instruction"):
        print(command)
    # pass
    # print('End element:', name) #!debug


def char_data_handler(data):
    # Funkce, která zpracovává hodnoty z elementů z xml dat
    if (re.match("[0-9a-zA-Z]+", data) != None):
        global command
        if not command["arg1_value"]:
            command["arg1_value"] = data
        elif command["arg1_value"] and not command["arg2_value"]:
            command["arg2_value"] = data
        elif command["arg2_value"] and not command["arg3_value"]:
            command["arg3_value"] = data
        else: 
            print("Error: ") #TODO
        print('Character data:', repr(data))


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

    # procházím vstupní data
    for i in source_data:
        parser.Parse(i)

    # pokud se v programu vůbec nevyskytuje program
    if (not program):
        print("Error: Program element doesnt exist in this file.", file=sys.stderr)
        exit(CONST.XML_UNEXPECTED_STRUCT)

    # ukončení
    exit(CONST.SUCCESS)


if __name__ == '__main__':
    main()
