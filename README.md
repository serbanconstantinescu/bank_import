bank_import
===========

FA bank import

The aim of this module is to be able import and process bank statements into FrontAccounting.


INSTALLATION
------------
1. Extract the archive or copy all files into /modules/bank_import folder.
2. Install/activate module from FrontAccount as you do with other modules
3. after installation, 4 new menu links will appear into "Banking and General Ledger" section:
- Transaction/Process Bank Statements
- Inquiry/Bank Statemens Inquiry
- Maintenance/Import Bank Statements
- Maintenance/Manage Partners Bank Accounts

USAGE
-----
1. import one or more statements using the Import Bank Statements link
- select the correct format for your file
- check the output for any errors

2. process each transaction with Process Bank Statements link
- you will be presented a list of transactions with all the transaction details
- you have the option to process each transaction as a Customer Deposit, a Supplier payment, Manual settlement  or a Quick Entry (you will have to define Quick Entries as needed)
- after pressing "process", the transaction will be recorded into FA and the bank transaction will be marked as "settled"
- if some human error occurs, by voiding the FA transaction, the corresponding bank transaction is "unsettled" as well and becomes "processable" again


FOR DEVELOPERS
--------------
The module has two parts:
- a bank statement parser and importer
- the required frontend screens for transaction processing

The module uses MT940 bank statement format for keeping transactions - all the fields from MT940 format are mapped in 2 database tables.
The parser/importer sub-functions parse either a MT940 .STA file, either a .CSV file, mapping all parsable fields from .CSV into MT940 fields.

Apparently, each bank implements MT940 with some variations from standard. As such, a base MT940 parser has been developed, plus an additional parser that extends and modifies the base parser
This is adapted for Romanian BRD bank (named "RO-BRD-MT940")

As with the CSV files, so far I implemented for two banks (RO-BCR and RO-ING). Obviously, their format is different, as well as with the data contained.
There is no recipe here, just map the CSV fields onto MT940 fields as you can.

WARNING: normally, each transaction has been assigned an unique transaction id. In some CSV files, the transaction identfier is missing.
So you have to be creative enough to create a transaction id string (eg. date + amount = transaction id string)


FILES and CLASSES
-----------------
banking.inc - contains base MT940 transaction and statement classes
parser.inc - contains base class for a parser
mt940_parser.php - contains the base MT940 parser class
ro_brd_mt940_parser.php - contains the specific mt940 parser for BRD bank
parsers.inc - contains the "getParsers" function, used by UI to control what is presented to the user. self explanatory


test_parser.php - a CLI executable that allows testing and development
