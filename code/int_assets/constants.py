''' Návratové kódy '''
# úspěšně bez chyby
SUCCESS = 0
# problém s parametry
PARAM_MISS_OR_COMBINATION = 10
# problémy se soubory
FILE_INPUT = 11
FILE_OUTPUT = 12
# vstupní problémy s xml
XML_FORMAT = 31
XML_UNEXPECTED_STRUCT = 32
# problémy s sémantickými kontrolami
SEM_CHECK = 52
# problémy při běhu
RUNTIME_BAD_OP_TYPES = 53
RUNTIME_NOT_EXIST_VAR = 54
RUNTIME_NOT_EXIST_FRAME = 55
RUNTIME_MISSING_VALUE = 56
RUNTIME_BAD_OP_VALUE = 57
RUNTIME_BAD_STRING = 58
# problémy interního charakteru (málo paměti apod.)
INTERNAL = 99