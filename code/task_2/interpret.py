import errno
import getopt
import sys
#import xml.dom.minidom as xml
import xml.parsers.expat as xml
import re
# konstanty
import assets.constants as CONST

# globalní hodnota pro order
order = 0


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
    else:
        for line in iter(input, ''):
            print(line)
        return False


def start_element_handler(name, attrs):
    # Funkce, která zpracovává počáteční elementy z xml dat
    print(order)
    print('Start element:', name, attrs)


def end_element_handler(name):
    # Funkce, která zpracovává koncové elementy z xml dat
    print('End element:', name)


def char_data_handler(data):
    # Funkce, která zpracovává hodnoty z elementů z xml dat
    if (re.match("[0-9a-zA-Z]+", data) != None):
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
        print("----------------")

    # ukončení
    exit(CONST.SUCCESS)


if __name__ == '__main__':
    main()
