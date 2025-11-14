# CHANGELOG FACTURX FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.6.78 -- 2025-07-07

add unit price and fix discount negative lines

## 1.6.76 -- 2025-06-30

add more actions (confirm_paiement and setnote_) where dolibarr rebuild pdf files

## 1.6.74 -- 2025-05-16

chorus : do not put AdditionalReferencedDocument data into xml

## 1.6.72 -- 2025-04-24

new setup option to force facturx call even if object type is not a ModelePDFFactures
add a test agains hidden conf PDF_SECURITY_ENCRYPTION

## 1.6.70 -- 2025-04-18

add a mapping system dolibarr <-> facturx for:
  * NORMAL invoice like that module makes from the first time
  * CORRECTION (to become for future "updates" of invoices ?)
  * CREDITNOTE (please make some checks with "negative" invoices)
  * PREPAYMENTINVOICE ("acompte")
add a check for customer ref length (chorus limit is 50 chars)
add document language to be the same as pdf part
add billing periods lines
fix a warninng message (undef array entry)

note in case of invoices and prepayment invoice linked dolibarr add a negative line
bug facturx point of view is to not have a negative line but a "partial paid" info
at the end of the document and a "remain to pay" affected by that payment ... so
facturx XML could be "different" than pdf part please have twice look at that and
make me detailled bug reports in case of problem !


## 1.6.64 -- 2025-03-05

fix file suffix stuck to _tmp.pdf instead of _facturx.pdf (in some race conditions)
add a hidden config key : FACTURX_SUFFIX_CUSTOM then you could put what you want as file suffix name
update payment terms translations (do not htmlencode)
fix PayeePartyCreditorFinancialAccount/AccoutName (BT-85) should be OwnerName instead of Bank name
fix negative lines as global discount and write some documentation about it

## 1.6.56 -- 2025-01-31

fix code thanks to grandoc (undef array index)

## 1.6.54 -- 2025-01-31

new add all paymentMean possibilities from Jan Bekemeier
fix a french error on alert messages: siren is the only needed information thanks to Vincent Maury

## 1.6.52 -- 2025-01-21

new profile PROFILE_XRECHNUNG_3 thanks to Sven again !
new option in setup to choose the profile you want
fix "FacturXDisabled" keyword displayed on invoices card, now that keyword is translated

## 1.6.50 -- 2025-01-15

Internationalization of PaymentMean field (remove the
french "Virement Bancaire") thanks to Jan Bekemeier

Change source of informations for company phone / fax and email
in case of contact linked to the invoice is not set, thanks to Sven Plohmer

Use customer language or document language for xml products lines


## 1.6.46 -- 2024-12-12

FIX discount on line less than 100%
Remove Chorus warning when chorus is disabled
Update translations

## 1.6.44 -- 2024-12-09

NEW contribution from Sven Plohmer :
    Adding functionality to fetch customer order references, corresponding...
    Remove "note" in case of empty note field
NEW contribution from Jan Bekemeier :
    New option in module setup "disables generation of DefinedTradeContact"

Code cleanup, migrate from conf->global to getDolGlobalString function

## 1.6.42 -- 2024-10-22

Fix langs files embedded into zip package

## 1.6.41 -- 2024-09-16

Thanks to Jan Bekemeier (Weblinx.IT GbR) German ID Prof is now correct

## 1.6.38 -- 2024-09-13

Fix a call to a backported function

## 1.6.36 -- 2024-09-05

Update German translations thanks to Jan Bekemeier (Weblinx.IT GbR)

## 1.6.34 -- 2024-07-25

New option to export XML file standalone

## 1.6.32 -- 2024-07-19

New option for german people : "GLOBAL_IDENTIFIER_DISABLE" thanks to
Sven Plohmer (from digitalcentric) becaus of government institution :
The ISO 6523 code for germany does not exists and is not accepted by
the goverment systems.

## 1.6.30 -- 2024-07-08

New option to disable FacturX ponctually on a file if needed

## 1.6.28 -- 2024-06-25

Update libs
Massive update for keeping html links into pdf

## 1.6.26 -- 2024-05-15

Add A1/A2 in BusinessProcessSpecifiedDocumentContextParameter
Update composer and depends

## 1.6.22 -- 2024-04-30

Update SIRET/SIREN case
Change isset/empty test thanks to (thanks to sylvain@infras)
Update API handle race situation for embedding FacturX XML file

## 1.6.20 -- 2024-04-05

Better log. Remove trigger stuff

## 1.6.19 -- 2024-03-25

FIX: full rewrite for trigger support, tests ok, next is customer checks

## 1.6.18 -- 2024-03-24

FIX: handle special race conditions of invoices made from sellyoursaas
     (action is empty)

## 1.6.16 -- 2024-03-11

NEW: handle pdf invoices from custom format using odt as transition
     (MUST use MAIN_ODT_AS_PDF setup to auto convert odt to pdf server side)

## 1.6.14 -- 2024-02-23

NEW: full german translation thanks to our greman official partner
     Weblinx.IT GbR


## 1.6.12 -- 2024-02-16

FIX: add facturx into PDF not only on builddoc hook but in 3 others
     situations (and maybe more in future ?)

## 1.6.10 -- 2024-02-10

NEW: mode debug log message to get more precise origin of bugs
FIX: switch from composer autoload to scoper autoload


## 1.6.8 -- 2024-02-01

NEW: switch to a module\facturx namespace for most of code
NEW: option to use trigger instead of hooks -> facturx will be
     embedded into PDF even if you makes your invoices via api
FIX: namespace for fpdf
FIX: validate error message returns object -> cast to text (json)
FIX: race condition for API call where action is empty
UPD: composer & external libs upgrade + patches
UPD: chorus fields displayed on PDF if non empty
NEW: contains namespace using php-scoper
NEW: Chorus fields could be disabled (hidden) via admin setup option

## 1.6.4 -- 2023-11-28

NEW: Chorus options on order to be able to put that informations
     as soon as possible, then invoice made from order will keep
     that informations


## 1.6.2 -- 2023-11-22

Fix a bug on specimen PDF creation (thanks to sylvain@infras)
New : lot of messages on document integrity check
Upgrade : on depends libs
 - Upgrading horstoeko/zugferd 1.0.31

## 1.6.0

Remove spaces from IBAN, BIC and Bank account number
Remove spaces from VAT & SIREN/SIRET
Upgrade depends libs:
 - Upgrading doctrine/annotations (1.13.2 => 2.0.1)
 - Upgrading doctrine/instantiator (1.4.1 => 1.5.0)
 - Upgrading doctrine/lexer (1.2.3 => 2.1.0)
 - Upgrading horstoeko/stringmanagement (v1.0.8 => v1.0.11)
 - Upgrading horstoeko/zugferd (v1.0.9 => v1.0.26)
 - Upgrading jms/metadata (2.6.1 => 2.8.0)
 - Upgrading jms/serializer (3.17.1 => 3.27.0)
 - Upgrading phpstan/phpdoc-parser (1.4.5 => 1.23.1)
 - Upgrading setasign/fpdf (1.8.4 => 1.8.6)
 - Upgrading setasign/fpdi (v2.3.6 => v2.4.1)
 - Upgrading symfony/deprecation-contracts (v2.5.1 => v2.5.2)
 - Upgrading symfony/polyfill-ctype (v1.25.0 => v1.27.0)
 - Upgrading symfony/polyfill-mbstring (v1.25.0 => v1.27.0)
 - Upgrading symfony/polyfill-php73 (v1.25.0 => v1.27.0)
 - Upgrading symfony/polyfill-php80 (v1.25.0 => v1.27.0)
 - Upgrading symfony/polyfill-php81 (v1.25.0 => v1.27.0)
 - Upgrading symfony/translation-contracts (v2.5.1 => v2.5.2)
 - Upgrading symfony/validator (v5.4.8 => v5.4.26)
 - Upgrading symfony/yaml (v5.4.3 => v5.4.23)

## 1.5.18

Add mini profile + handle of eTickets
Fix compatibility with UltimatePDF (ModeleUltimatePDFFactures)

## 1.5.16

Fix Belgium code + idprof1/idprof2

## 1.5.14

Fix Date +1/-1 GMT / Local hours (summer/winter) switch error

## 1.5.12

Fix CHORUS support if customer does not have some fields then
XML do not have empty fields (remove fields if value empty)

## 1.5.11

Fix parser indentation error (phpcs)

## 1.5.10

Compatible with ATM SubTotal module thanks to InfraS (Sylvain Legrand)

## 1.5.9

Compatible with dolibarr 17/18

## 1.5.8

CHORUS : new field to store tracking number from chorus when PDF is sent was not editable !
New option on setup : concat all other PDF files linked to the invoice BEFORE facturx (for example
you can join some other files to the invoice like order)

## 1.5.6

CHORUS : new field to store tracking number from chorus when PDF is sent
## 1.5.4

CHORUS options are now groupped masked by default (invoices)

## 1.5.2

New CHORUS support (3 custom fields on invoices) - confirmed !
Note for CHORUS : don't forget to add "flux habilitations" on your account.

## 1.4.4

New mass action on invoice list : export multiple files in a zip archive

## 1.4.3

Fix SIRET/SIREN code
Fix order to make pdf file

## 1.4.2

Suffix is now an option in setup module

## 1.4.1

Use a temp file because on some race conditions original file will be rewrite

## 1.4.0

File name suffix could be choosed on admin panel now, some pleople wan't a distinct PDF File with factur_x XML, some others don't

## 1.3.0

Change PDF export file name as original name + _facturx.pdf as suffix
Update depends libs
Fix FPDF collision

## 1.2.0

Fix export discount rates (was not present before that version !)
## 1.1.0

Thanks to  Maximilian Stein this version apply FacturX hook only on PDF customer invoices
and ODT document creation with automatic PDF conversion

## 1.0.0

Initial version
