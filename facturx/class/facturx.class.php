<?php

/**
 * facturx.class.php
 *
 * Copyright (c) 2023 Eric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
* \file    facturx/class/facturx.class.php
* \ingroup facturx
* \brief   main facturx stuff is here
*
* All FacturX job
*/

namespace custom\facturx;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdPdfWriter;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use JMS\Serializer\Handler\HandlerRegistry;
use JMS\Serializer\EventDispatcher\EventDispatcher;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\entities\en16931\ram\DocumentContextParameterType;
use horstoeko\zugferd\entities\en16931\udt\IDType;

function debug_string_backtrace()
{
    ob_start();
    debug_print_backtrace();
    $trace = ob_get_contents();
    ob_end_clean();
    return $trace;
}

$matches  = preg_grep('/Restler\/AutoLoader.php/i', get_included_files());
if (file_exists(__DIR__ . '/../vendor/scoper-autoload.php')) {
    require_once __DIR__ . '/../vendor/scoper-autoload.php';
} elseif (count($matches) == 0) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    //autoloader compatible with specific dolibarr restler settings...
    spl_autoload_register(function ($class) {
        dol_syslog("use spl_autoload A from FacturX for $class");
        $list = include __DIR__ . '/../vendor/composer/autoload_classmap.php';
        $fileToLoad = $list[$class];
        // if($class == "custom\\facturx\\setasign\\FpdiPdfParser\\PdfParser\\PdfParser") {
        // 	dol_syslog("FacturX spl_autoload for " . $class . " -> " . $fileToLoad);
        // 	dol_syslog(debug_string_backtrace());
        // 	exit;
        // }
        if ($fileToLoad != "" && file_exists($fileToLoad)) {
            require_once $fileToLoad;
        }
    }, true, true);
}

require_once __DIR__ . "/../vendor/setasign/fpdf/fpdf.php";
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/discount.class.php';

dol_include_once('/facturx/lib/facturx.lib.php');

/**
 * Class FacturX
 */
class FacturX
{
    public $error = '';
    public $errors = [];
    public $db;
    private \Facture $_invoice;
    private $_outputlang;

    /**
     * Constructor
     */
    public function __construct($db, \Facture $invoice, $outputlang = "")
    {
        $this->db = $db;
        if (!is_object($invoice->thirdparty)) {
            $invoice->fetch_thirdparty();
        }
        $this->_invoice = $invoice;
        $this->_outputlang = $outputlang;
		// dol_syslog("facturx debug invoice input is " . json_encode($invoice));
    }

    /**
     * build PDF with Facturx XML embedded
     *
     * @param   string  $orig_pdf  [$orig_pdf description]
     *
     * @return  int             code < 0 on error
     */
    public function makePDF($orig_pdf)
    {
        global $conf, $user, $langs, $mysoc, $db;

        $precheck = false;
        if (file_exists($orig_pdf) && is_readable($orig_pdf)) {
            $finfo	= finfo_open(FILEINFO_MIME_TYPE);
            if (finfo_file($finfo, $orig_pdf) == 'application/pdf') {
                $precheck = true;
            }
        }
        if ($precheck == false) {
            dol_syslog(get_class($this) . "::executeHooks orig pdf file does not exists, can't add facturX XML inside");
            return -1;
        }

        $outputlangs = $langs;
        $langs->load("facturx@facturx");

        $ret = $prepaidAmount = 0;
		$billing_period = [];
        $deltemp = array();

        $object = $this->_invoice; //migrating code from dolibar

        dol_syslog('facturx::makePDF');

        clearstatcache(true);

        if (strpos($orig_pdf, 'mini.pdf') || strpos($orig_pdf, 'eticket.pdf')) {
            dol_syslog(get_class($this) . '::executeHooks eticket invoice, facturx minimum profile apply');
            $this->makeMinimalPDF($orig_pdf);
            return 0;
        }

        // if (!is_subclass_of($pdfhandler, 'ModelePDFFactures') && !is_subclass_of($pdfhandler, 'ModeleUltimatePDFFactures')) {
        // 	dol_syslog(get_class($this) . '::executeHooks not a ModelePDFFactures or ModeleUltimatePDFFactures ... but ' . get_class($pdfhandler) . ', parents classes=' . json_encode(class_parents($pdfhandler)). ' then return');
        // 	return 0;
        // }

        //New option to concat other files at the end of orig_pdf file (before is "before making facturx xml")
        if (!empty($this->_getDolGlobalString('FACTURX_CONCAT_BEFORE', ''))) {
            dol_syslog(get_class($this) . '::executeHooks concat pdf before create XML');
            $formatarray = pdf_getFormat();
            $format = array($formatarray['width'], $formatarray['height']);
            // Create empty PDF
            /** @phpstan-ignore-next-line */
            $pdf = $this->_pdf_getInstance($format);
            if (class_exists('TCPDF')) {
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
            }
            $pdf->SetFont(pdf_getPDFFont($outputlangs));

            //base file
            try {
                $pagecounttmp = $pdf->setSourceFile($orig_pdf);
                for ($i = 1; $i <= $pagecounttmp; $i++) {
                    $tplidx = $pdf->ImportPage($i, '/CropBox', true, true);
                    if ($tplidx !== false) {
                        $s = $pdf->getTemplatesize($tplidx);
                        $pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
                        $pdf->useTemplate($tplidx);
                    }
                }
            } catch (\Exception $e) {
                dol_syslog("Error when manipulating some PDF by concatpdf: ".$e->getMessage(), LOG_ERR);
                $this->error = $e->getMessage();
                $this->errors[] = $e->getMessage();
                dol_print_error($this->db, $this->error);  // Remove this when dolibarr is able to report on screen errors reported by this hook.
                return -1;
            }


            if (!empty($this->_getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION', ''))) {
                $pdf->SetCompression(false);
            }
            $dir = $conf->facture->dir_output.'/'.get_exdir($object->id, 2, 0, 0, $object, 'invoice');

            foreach (glob($dir."/".$object->ref."-*.pdf") as $file) {
                dol_syslog(get_class($this) . '::executeHooks concat pdf add ' . $file);
                // print json_encode($file);
                if (dol_is_file($file)) {    // We ignore file if not found so if ile has been removed we can still generate the PDF.
                    try {
                        $pagecounttmp = $pdf->setSourceFile($file);
                        for ($i = 1; $i <= $pagecounttmp; $i++) {
                            $tplidx = $pdf->ImportPage($i, '/CropBox', true, true);
                            if ($tplidx !== false) {
                                $s = $pdf->getTemplatesize($tplidx);
                                $pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
                                $pdf->useTemplate($tplidx);
                            }
                        }
                    } catch (\Exception $e) {
                        dol_syslog("Error when manipulating some PDF by concatpdf: ".$e->getMessage(), LOG_ERR);
                        $this->error = $e->getMessage();
                        $this->errors[] = $e->getMessage();
                        dol_print_error($this->db, $this->error);  // Remove this when dolibarr is able to report on screen errors reported by this hook.
                        return -1;
                    }
                } else {
                    dol_syslog("Error: Can't find PDF file, for file ".$file, LOG_WARNING);
                }
            }

            //save pdf
            $pdf->Close();
            $pdf->Output($orig_pdf, 'F');
        }

        $facture_number = $object->ref;
        $note_pub = $object->note_public ? $object->note_public : "";
        $ladate = new \DateTime(dol_print_date($object->date, 'dayrfc'));//, 'gmt'));
        $ladatepaiement = new \DateTime(dol_print_date($object->date_lim_reglement, 'dayrfc'));//, 'gmt'));

        //details about payment mode
        $account = new \Account($this->db);
        if ($object->fk_account > 0) {
            $bankid = $object->fk_account; // For backward compatibility when object->fk_account is forced with object->fk_bank
            $account->fetch($bankid);
        } else {
            $account->fetch($this->_getDolGlobalString('FACTURX_DEFAULT_BANK_ACCOUNT'));
        }

		$account_proprio = trim($account->proprio);
		if($account_proprio == '') {
			dol_syslog('Bank account holder name is empty, please correct it, use socname instead but it could be inccorrect for BT-85 field)', LOG_WARNING);
			$account_proprio = $mysoc->name;
		}

        //customer account linked
        $contact = $object->thirdparty;
        if (isset($object->contact)) {
            $contact = $object->contact;
        }

        // print "OBJECT : " . json_encode($object->array_options->d4d_promise_code);
        // exit;
        dol_syslog(get_class($this) . '::executeHooks create new XML document based on PROFILE_EN16931');
        $profile = (int) $this->_getDolGlobalString('FACTURX_PROFILE');
        if (!empty($profile)) {
            $facturxpdf = ZugferdDocumentBuilder::createNew($profile);
        } else {
            $facturxpdf = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);
        }
        dol_syslog(get_class($this) . '::executeHooks create new XML document based on PROFILE_EN16931 (2)');

        ///Specific for Chorus : if a chorus extrafield is present we have to check if all others are ok
        //and display warning in case of trouble
        //as chorus is french only solution, there is no translations for that keys
        $chorus = false;
        $chorusErrors = [];
        $promise_code = $object->array_options['options_d4d_promise_code'] ?? '';

        $customerOrderReferenceList = [];
        $deliveryDateList = [];

        // init hook
        include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
        $hookmanager = new \HookManager($this->db);
        $hookmanager->initHooks(array('facturx'));

        // trigger hook
        $parameters = array('invoice' => $object);
        $reshook = $hookmanager->executeHooks('calculateCustomerOrderReferencesAndDeliveryDates', $parameters); // Note that $action and $object may have been
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        } elseif ($reshook == 0) {
			/** @phpstan-ignore-next-line */
            $this->_determineDeliveryDatesAndCustomerOrderNumbers($customerOrderReferenceList, $deliveryDateList, $object);
        } else {
            $customerOrderReferenceList = $hookmanager->resArray['customerOrderReferenceList'];
            $deliveryDateList = $hookmanager->resArray['deliveryDateList'];
        }

        if ($promise_code == '') {
            $promise_code = $object->ref_client ?? '';
        }
        if ($promise_code == '' && !empty($customerOrderReferenceList)) {
            $promise_code = $customerOrderReferenceList[0];
        } else {
            if (empty($this->_getDolGlobalString('FACTURX_DISABLE_CHORUS_EXTRAFIELDS', ''))) {
                $chorus = true;
            }
        }

        if ($promise_code == '') {
            $chorusErrors[] = "N° d'engagement absent";
        } elseif(strlen($promise_code) > 50 && $promise_code == $object->ref_client) {
			$chorusErrors[] = "Ref client trop longue pour chorus (max 50 caractères)";
		}

        // print "<p>PromiseCode = $promise_code</p>";
        if ($object->array_options['options_d4d_contract_number'] == '') {
            $chorusErrors[] = "N° de marché absent";
        } else {
            $chorus = true;
        }
        if ($object->array_options['options_d4d_service_code'] == '') {
            $chorusErrors[] = "Code service absent";
        } else {
            $chorus = true;
        }
        if (isset($object->thirdparty->idprof2) && trim($object->thirdparty->idprof2) == '') {
            $chorusErrors[] = "Numéro SIRET du client manquant";
        }
        if ($chorus) {
            if (count($chorusErrors) > 0) {
                setEventMessages("Alerte conformité Chorus:", $chorusErrors, 'warnings');
                dol_syslog(get_class($this) . '::executeHooks error chorus : ' . json_encode($chorusErrors), LOG_ERR);
            } else {
                dol_syslog(get_class($this) . '::executeHooks chorus enabled, no errors detected');
            }
        } else {
            dol_syslog(get_class($this) . '::executeHooks no chorus data');
        }

        $baseErrors = [];
        if (empty($mysoc->tva_intra)) {
            $baseErrors[] = $langs->trans("FxCheckErrorVATnumber");
        }
        if (empty($mysoc->address)) {
            $baseErrors[] = $langs->trans("FxCheckErrorAddress");
        }
        if (empty($mysoc->zip)) {
            $baseErrors[] = $langs->trans("FxCheckErrorZIP");
        }
        if (empty($mysoc->town)) {
            $baseErrors[] = $langs->trans("FxCheckErrorTown");
        }
        if (empty($mysoc->country_code)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCountry");
        }

        if (empty($object->thirdparty->name)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerName");
        }
        if ($mysoc->country_code != 'FR' && empty($object->thirdparty->idprof1)) { // in France seul le SIRET (idprof2) est utilisé
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerIDPROF1");
        }
        if (empty($object->thirdparty->idprof2)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerIDPROF2");
        }
        if (empty($object->thirdparty->address)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerAddress");
        }
        if (empty($object->thirdparty->zip)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerZIP");
        }
        if (empty($object->thirdparty->town)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerTown");
        }
        if (empty($object->thirdparty->country_code)) {
            $baseErrors[] = $langs->trans("FxCheckErrorCustomerCountry");
        }
        if ($object->thirdparty->tva_assuj) { // test vat code que si tiers assujetti
            $this->_thirdpartyCalcTva_intra($object);
            if (empty($object->thirdparty->tva_intra)) {
                $baseErrors[] = $langs->trans("FxCheckErrorCustomerVAT");
            }
        }
        if (count($baseErrors) > 0) {
            dol_syslog(get_class($this) . '::executeHooks baseErrors count > 0');
            //SPECIMEN
            if (strpos($orig_pdf, 'SPECIMEN') > 0) {
                dol_syslog(get_class($this) . '::executeHooks baseErrors count > 0 but specimen mode do not take care');
                //no message
            } else {
                // if (empty($this->_getDolGlobalString('FACTURX_USE_TRIGGER',''))) {
                setEventMessages($langs->trans("FxCheckError"), $baseErrors, 'warnings');
                dol_syslog(get_class($this) . '::executeHooks baseErrors count > 0, error = ' . json_encode($baseErrors));
                // } else {
                // 	$this->errors[] = $baseErrors;
                // }
            }
        }

        $facturxpdf
            ->setDocumentInformation($facture_number, $this->_getTypeOfInvoice(), $ladate, $conf->currency, $object->ref_customer, $this->_outputlang)
            ->addDocumentNote($note_pub)
            // ->setDocumentSupplyChainEvent(\DateTime::createFromFormat('Ymd', $ladate))
            ->setDocumentSeller($mysoc->name, $this->_idprof($mysoc))
            ->addDocumentSellerTaxRegistration("VA", $mysoc->tva_intra ?? 'FRSPECIMEN')
            ->setDocumentSellerLegalOrganisation($this->_idprof($mysoc), null, $mysoc->name ?? 'SPECIMEN')
            ->setDocumentSellerAddress($mysoc->address ?? 'ADRESS EMPTY', "", "", $mysoc->zip ?? 'ZIP EMPTY', $mysoc->town ?? 'NO TOWN', $mysoc->country_code ?? 'COUNTRY NOT SET')
            ->setDocumentBuyer($object->thirdparty->name ?? 'CUSTOMER', $this->_remove_spaces($this->_thirdpartyidprof() ?? 'IDPROF2'))
            ->setDocumentBuyerAddress($object->thirdparty->address ?? 'ADDRESS', "", "", $object->thirdparty->zip ?? 'ZIP', $object->thirdparty->town ?? 'TOWN', $object->thirdparty->country_code ?? 'COUNTRY')
            ->addDocumentBuyerTaxRegistration("VA", $object->thirdparty->tva_intra ?? '')
            ->setDocumentBuyerLegalOrganisation($this->_remove_spaces($this->_thirdpartyidprof() ?? ''), null, $contact->name ?? $contact->lastname)
            ->setDocumentBuyerCommunication('EM', $this->_extractBuyerMail($contact, $object->thirdparty));

        if (empty($this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_DISABLE', ''))) {
            if (empty($this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_CUSTOM', ''))) {
                $facturxpdf->addDocumentSellerGlobalId($this->_idprof($mysoc), $this->_IEC_6523_code($mysoc->country_code));
            } else {
                list($idtype, $id) = explode(':', $this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_CUSTOM'));
                $facturxpdf->addDocumentSellerGlobalId($id, $idtype);
            }
        }

		//add buyer ID scheme
		if(!empty($this->_thirdpartyidprof())) {
			$facturxpdf->addDocumentBuyerGlobalId($this->_thirdpartyidprof(), $this->_IEC_6523_code($object->thirdparty->country_code));
		}

        if (!empty($deliveryDateList)) {
            $facturxpdf->setDocumentSupplyChainEvent(new \DateTime($deliveryDateList[0]));
        }

		//Not for chorus : a été rejetée pour le(s) motif(s) suivants, identifié(s) dans le flux cycle de vie : L'element (AttachmentBinaryObject.value) est obligatoire si l'element (FichierXml.SupplyChainTradeTransaction.ApplicableHeaderTradeAgreement.AdditionalReferencedDocument) est renseigne.
		if(!$chorus) {
			foreach ($customerOrderReferenceList as $customerOrderRef) {
				if ($customerOrderRef != $promise_code) {
					$facturxpdf->addDocumentAdditionalReferencedDocument($customerOrderRef, "130");
				}
			}
		}

        $contacts = $object->getIdContact('internal', 'SALESREPFOLL');
        $object->user = null;
        if (empty($this->_getDolGlobalString('FACTURX_GLOBAL_TRADECONTACT_DISABLE', ''))) {
            if (!empty($contacts) && $object->fetch_user($contacts[0]) > 0) {
                $name = $object->user->getFullName($outputlangs);
                $office_phone = $object->user->office_phone;
                $office_fax = $object->user->office_fax;
                $email = $object->user->email;
            } else {
                $name = $user->getFullName($outputlangs);
                $office_phone = $user->office_phone;
                $office_fax = $user->office_fax;
                $email = $user->email;
            }

            if (empty($office_phone)) {
                $office_phone = $mysoc->phone;
            }
            if (empty($office_fax)) {
                $office_fax = $mysoc->fax;
            }
            if (empty($email)) {
                $email = $mysoc->email;
            }
            $facturxpdf->setDocumentSellerContact($name, "", $office_phone, $office_fax, $email);
            $facturxpdf->setDocumentSellerCommunication("EM", $email);
        }
        // trigger hook for buyer reference
        $parameters = array('invoice' => $object);
        $reshook = $hookmanager->executeHooks('calculateBuyerReference', $parameters); // Note that $action and $object may have been
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        } elseif ($reshook > 0 && !empty($hookmanager->resPrint)) {
            $facturxpdf->setDocumentBuyerReference($hookmanager->resPrint);
        } else {
            if (!empty($object->array_options['options_d4d_service_code'])) {
                //CHORUS Débiteur. Code service $parameters['object']
                $facturxpdf->setDocumentBuyerReference($object->array_options['options_d4d_service_code']);
            }
        }

        if (!empty($object->array_options['options_d4d_contract_number'])) {
            //CHORUS Engagement. Numéro de marché
            $facturxpdf->setDocumentContractReferencedDocument($object->array_options['options_d4d_contract_number']);
        }
        if (!empty($promise_code)) {
            //CHORUS Engagement. Numéro d’engagement
            $facturxpdf->setDocumentBuyerOrderReferencedDocument($promise_code);
        }

		//moved after lines to get prepaid amount data
        // $facturxpdf->setDocumentSummation($object->total_ttc, $object->total_ttc, $object->total_ht, 0.0, 0.0, $object->total_ht, $object->total_tva, null, $prepaidAmount)
        //     ->addDocumentPaymentTerm($langs->transnoentitiesnoconv("PaymentConditions").": ".$langs->transnoentitiesnoconv("PaymentCondition".$object->cond_reglement_code), $ladatepaiement)
        //     ->addDocumentPaymentMean($this->get_paymentMean_number($object), $langs->transnoentitiesnoconv("PaymentType".$object->mode_reglement_code), null, null, null, null, $this->_remove_spaces($account->iban), $account_proprio, $this->_remove_spaces($account->number), $this->_remove_spaces($account->bic));


        //FACTURX_DISABLE_CHORUS_EXTRAFIELDS
        if (empty($this->_getDolGlobalString('FACTURX_DISABLE_CHORUS_EXTRAFIELDS', ''))) {
            dol_syslog("FacturX::Chorus add DocumentBusinessProcess data");
            if ($object->paye) {
                $facturxpdf->setDocumentBusinessProcess("A2");
            } else {
                $facturxpdf->setDocumentBusinessProcess("A1");
            }
        } else {
            dol_syslog("FacturX::Chorus disabled, then DocumentBusinessProcess disabled too");
        }

        //is there multi VAT informatins ? in case we need to collect all data to be able to join it at the end
        $tabTVA = [];
		//in case of prepaid invoice we have to forget dolibarr point of view with negative line
		$grand_total_ht = $grand_total_tva = $grand_total_ttc = 0;

        //use customer language
        // Define lang of customer
        $outputlangs = $langs;
        $newlang = '';

        if (isset($object->thirdparty->default_lang)) {
            $newlang = $object->thirdparty->default_lang; // for proposal, order, invoice, ...
        }
        // @phan-suppress-next-line PhanUndeclaredProperty
        if (isset($object->default_lang)) {
            $newlang = $object->default_lang; // for thirdparty @phan-suppress-current-line PhanUndeclaredProperty
        }
        if (GETPOST('lang_id', 'alphanohtml') != "") {
            $newlang = GETPOST('lang_id', 'alphanohtml');
        }
        if (!empty($newlang)) {
            $outputlangs = new \Translate("", $conf);
            $outputlangs->setDefaultLang($newlang);
        }

        //add invoice lines
        $numligne = 1;
        foreach ($object->lines as $line) {
            $isSubTotalLine = $this->_isLineFromExternalModule($line, $object->element, 'modSubtotal');
            if ($isSubTotalLine) {
                continue;
            }

			if($line->desc == '(DEPOSIT)') {
				$origFactRef = "";
				$origFactDate = new \DateTime();
				$discount = new \DiscountAbsolute($this->db);
				$resdiscount = $discount->fetch($line->fk_remise_except);
				print "<p>Fetch discount " . $line->fk_remise_except . ", res=$resdiscount</p>";
				if($resdiscount > 0) {
					$origFact = new \Facture($this->db);
					$resOrigFact = $origFact->fetch($discount->fk_facture_source);
					print "<p>Fetch origFact " . $discount->fk_facture_source . ", res=$resOrigFact</p>";
					if($resOrigFact > 0) {
						$origFactRef = $origFact->ref;
						$origFactDate = new \DateTime(dol_print_date($origFact->date, 'dayrfc'));
					}
				}

				$prepaidAmount += abs($line->total_ttc);
				$facturxpdf->addDocumentAllowanceCharge(abs($line->total_ttc), false, "S", "VAT", $line->tva_tx, null, null, null, null, null, "Prepayment invoice (386)", $origFactRef);
				print "<p>Set setDocumentBuyerOrderReferencedDocument : " . json_encode($origFactRef)  . " :: " . json_encode($origFactDate) . "</p>";
				$facturxpdf->setDocumentInvoiceReferencedDocument($origFactRef, $origFactDate);
                continue;
			}

            $libelle = $description = "";
            //use customer lang ?
            if ($newlang != "") {
                if (!isset($line->multilangs)) {
                    $tmpproduct = new \Product($db);
                    $resproduct = $tmpproduct->fetch($line->fk_product);
                    if ($resproduct > 0) {
                        $getm = $tmpproduct->getMultiLangs();
                        if ($getm < 0) {
                            dol_syslog("facturx error fetching multilang for product error is " . $tmpproduct->error, LOG_DEBUG);
                        }
                        $line->multilangs = $tmpproduct->multilangs;
                    } else {
                        dol_syslog("facturx error fetching product", LOG_DEBUG);
                    }
                }
                if (isset($line->multilangs)) {
                    $libelle = $line->multilangs[$newlang]["label"];
                    $description = $line->multilangs[$newlang]["description"];
                }
            }
            if (empty($libelle)) {
                $libelle = $line->product_label ? $line->libelle : "libre";
            }
            if (empty($description)) {
                $description = $line->desc ? $line->desc : "";
            }
            $lineref = $line->ref ? $line->ref : "0000";
            $lineproductref = $line->product_ref ? $line->product_ref : "0000";

			$facturxpdf
				->addNewPosition($numligne)
				->setDocumentPositionProductDetails($libelle, $description, $lineproductref)
				->setDocumentPositionGrossPrice($line->subprice)
				->setDocumentPositionNetPrice($line->subprice)
				->setDocumentPositionQuantity($line->qty, "H87")
				->setDocumentPositionLineSummation($line->total_ht);

			if(!empty($line->date_start)) {
				$billing_period["start"][$numligne] = $line->date_start;
			}
			if(!empty($line->date_end)) {
				$billing_period["end"][$numligne] = $line->date_end;
			}
			if(isset($billing_period["start"][$numligne]) && isset($billing_period["end"][$numligne])) {
				$facturxpdf->setDocumentPositionBillingPeriod($this->_tsToDateTime($billing_period["start"][$numligne]), $this->_tsToDateTime($billing_period["end"][$numligne]));
			}

            //a line with negative amount as "line discount"
			//please read 3 types of discount available
			//https://github.com/horstoeko/zugferd/wiki/Creating-XML-Documents#working-with-discounts-and-charges
            if ($line->subprice < 0) {
                dol_syslog("facturx : there is negative line, convert as a global discount", LOG_INFO);
                // setEventMessages($langs->transnoentitiesnoconv('FxNegativeLine'), [], 'warnings');

				// print json_encode($line);exit;
				$facturxpdf->addDocumentPositionGrossPriceAllowanceCharge(abs($line->subprice) * $line->qty, false, null, null,  "Discount");

				//other ideas
                // $facturxpdf->addDocumentPositionAllowanceCharge(abs($remise_amount), true, null, null, null, "Discount");
				// $facturxpdf->addDocumentAllowanceCharge(abs($line->subprice) * $line->qty, false, "S","VAT", $line->tva_tx,null, null, null, null, null, null, "Discount");
            }

			//VAT informations
			if ($line->tva_tx > 0) {
				$facturxpdf->addDocumentPositionTax('S', 'VAT', $line->tva_tx);
			} else {
				$facturxpdf->addDocumentPositionTax('K', 'VAT', '0.00');
			}

			//discount % on a line
			if (isset($line->remise_percent) && ($line->remise_percent > 0)) {
				$remise_amount = $line->total_ht - ($line->subprice * $line->qty);
				dol_syslog("facturx : there is a discount on that line : " . $line->remise_percent . ", amount is " . $remise_amount);
				$facturxpdf->addDocumentPositionAllowanceCharge(abs($remise_amount), false, $line->remise_percent, ($line->subprice * $line->qty), null, "Discount");
			}

			if (!isset($tabTVA[$line->tva_tx])) {
                $tabTVA[$line->tva_tx] = [];
            }
			if(!isset($tabTVA[$line->tva_tx]['totalHT'])) {
				$tabTVA[$line->tva_tx]['totalHT'] = 0;
			}
			if(!isset($tabTVA[$line->tva_tx]['totalTVA'])) {
				$tabTVA[$line->tva_tx]['totalTVA'] = 0;
			}

            $tabTVA[$line->tva_tx]['totalHT']  += $line->total_ht;
            $tabTVA[$line->tva_tx]['totalTVA'] += $line->total_tva;
			$grand_total_ht  += $line->total_ht;
			$grand_total_ttc += $line->total_ttc;
			$grand_total_tva += $line->total_tva;
            $numligne++;
        }

        //Multi VAT
        foreach ($tabTVA as $k => $v) {
            $code = "S";
            if ($k == 0) {
                $code = 'K';
            }
            $facturxpdf->addDocumentTax($code, "VAT", $v['totalHT'], $v['totalTVA'], $k);
        }

		$facturxpdf->setDocumentSummation($grand_total_ttc, $grand_total_ttc - $prepaidAmount, $grand_total_ht, 0.0, 0.0, $grand_total_ht, $grand_total_tva, null, $prepaidAmount)
			->addDocumentPaymentTerm($langs->transnoentitiesnoconv("PaymentConditions").": ".$langs->transnoentitiesnoconv("PaymentCondition".$object->cond_reglement_code), $ladatepaiement)
			->addDocumentPaymentMean($this->_get_paymentMean_number($object), $langs->transnoentitiesnoconv("PaymentType".$object->mode_reglement_code), null, null, null, null, $this->_remove_spaces($account->iban), $account_proprio, $this->_remove_spaces($account->number), $this->_remove_spaces($account->bic));

		// is there a billing period for that invoice ?
		//setDocumentBillingPeriod

        //Creation du xml (debug)
        if ($this->_getDolGlobalString('FACTURX_XML_STANDALONE')) {
            $xmlfile = str_replace('.pdf', '_facturx.xml', $orig_pdf);
            $facturxpdf->writeFile($xmlfile);
        }

        dol_syslog(get_class($this) . '::executeHooks try to validate XML');
        $pdfCheck = new ZugferdDocumentValidator($facturxpdf);
        $res = $pdfCheck->validateDocument();
        if (count($res) > 0) {
            $allErrors = $this->_getAllMessages($res);
            // if (empty($this->_getDolGlobalString('FACTURX_USE_TRIGGER',''))) {
            setEventMessages($allErrors, [], 'errors');
            // }
            // $this->errors[] = json_encode($res);
            dol_syslog(get_class($this) . '::executeHooks  (1) : ' . $allErrors, LOG_ERR);
        }

        $pdfBuilder = new ZugferdDocumentPdfBuilder($facturxpdf, $orig_pdf);

        $pdfBuilder->generateDocument();

        $new_pdf = $orig_pdf;
        if ($this->_getDolGlobalString('FACTURX_SUFFIX_ENABLE', '') != '') {
			$suffix = $this->_getDolGlobalString('FACTURX_SUFFIX_CUSTOM','_facturx');
			$new_pdf = str_replace('.pdf', $suffix . '.pdf', $orig_pdf);
        }

        $pdfBuilder->saveDocument($new_pdf);
        dol_syslog(get_class($this) . '::executeHooks save facturx document to : ' . $new_pdf . ', checksum : ' . sha1_file($new_pdf));
        if (empty($this->_getDolGlobalString('FACTURX_SUFFIX_ENABLE', '')) && file_exists($new_pdf)) {
            rename($new_pdf, $orig_pdf);
        }
        clearstatcache(true);
        // dol_syslog(get_class($this) . '::executeHooks end action=' . $action . ', file saved as ' . $new_pdf);
        return $ret;
    }


    /**
     * build pdf with minimal Facturx profile
     *
     * @param   string  $orig_pdf  [$orig_pdf description]
     *
     */
    public function makeMinimalPDF($orig_pdf)
    {
        global $conf, $user, $langs, $mysoc;

        $outputlangs = $langs;
        $object = $this->_invoice; //migrating code from dolibar

        dol_syslog('facturx::makeMinimalPDF, orig_pdf is ' . $orig_pdf);
        dol_syslog('facturx::makeMinimalPDF, object ' . json_encode($object));

		$prepaidAmount = 0;
        $facture_number = $object->ref;
        $note_pub = $object->note_public ? $object->note_public : "note";
        $ladate = new \DateTime(dol_print_date($object->date, 'dayrfc'));//, 'gmt'));
        $ladatepaiement = new \DateTime(dol_print_date($object->date_lim_reglement, 'dayrfc'));//, 'gmt'));

        //payment mode
        $account = new \Account($this->db);
        if ($object->fk_bank > 0) {
            $bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
            $account->fetch($bankid);
        }

		$account_proprio = trim($account->proprio);
		if($account_proprio == '') {
			dol_syslog('Bank account holder name is empty, please correct it, use socname instead but it could be inccorrect for BT-85 field)', LOG_WARNING);
			$account_proprio = $mysoc->name;
		}

        //linked cusstomer account
        $contact = $object->thirdparty;
        if (isset($object->contact)) {
            $contact = $object->contact;
        }

        // print "OBJECT : " . json_encode($object->array_options->d4d_promise_code);
        // exit;

        $facturxpdf = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_MINIMUM);

        $facturxpdf
            ->setDocumentInformation($facture_number, $this->_getTypeOfInvoice(), $ladate, $conf->currency)
            ->addDocumentNote($note_pub)
                // ->setDocumentSupplyChainEvent(\DateTime::createFromFormat('Ymd', $ladate))
            ->setDocumentSeller($mysoc->name, $this->_idprof($mysoc))
            ->addDocumentSellerTaxRegistration("VA", $mysoc->tva_intra)
            ->setDocumentSellerLegalOrganisation($this->_idprof($mysoc), null, $mysoc->name)
            ->setDocumentSellerAddress($mysoc->address, "", "", $mysoc->zip, $mysoc->town, $mysoc->country_code);
        // ->setDocumentBuyer($object->thirdparty->name, _remove_spaces($object->thirdparty->idprof2));

        if (empty($this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_DISABLE', ''))) {
            if (empty($this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_CUSTOM', ''))) {
                $facturxpdf->addDocumentSellerGlobalId($this->_idprof($mysoc), $this->_IEC_6523_code($mysoc->country_code));
            } else {
                list($idtype, $id) = explode(':', $this->_getDolGlobalString('FACTURX_GLOBAL_IDENTIFIER_CUSTOM'));
                $facturxpdf->addDocumentSellerGlobalId($id, $idtype);
            }
        }

        $facturxpdf->setDocumentSummation($object->total_ttc, $object->total_ttc, $object->total_ht, 0.0, 0.0, $object->total_ht, $object->total_tva, null, $prepaidAmount)
            ->addDocumentPaymentTerm($langs->trans("PaymentConditions").": ".$langs->trans("PaymentCondition".$object->cond_reglement_code), $ladatepaiement)
            ->addDocumentPaymentMean($this->_get_paymentMean_number($object), $langs->trans("PaymentType".$object->mode_reglement_code), null, null, null, null, $this->_remove_spaces($account->iban), $account_proprio, $this->_remove_spaces($account->number), $this->_remove_spaces($account->bic));

        //multi taux de tva ou pas il faut collecter les infos pour faire le recap global a la fin
        $tabTVA = [];

        //Ajout des lignes de la facture
        $numligne = 1;
        foreach ($object->lines as $line) {
            if (!isset($tabTVA[$line->tva_tx])) {
                $tabTVA[$line->tva_tx] = [];
            }
            $tabTVA[$line->tva_tx]['totalHT']  += $line->total_ht;
            $tabTVA[$line->tva_tx]['totalTVA'] += $line->total_tva;
            $numligne++;
        }

        //Multi taux de tva eventuel
        foreach ($tabTVA as $k => $v) {
            $code = "S";
            if ($k == 0) {
                $code = 'K';
            }
            $facturxpdf->addDocumentTax($code, "VAT", $v['totalHT'], $v['totalTVA'], $k);
        }

        //Creation du xml (debug)
        // $facturxpdf->writeFile("/var/www/dolibarr-data/facturx/temp/test.xml");

        $pdfCheck = new ZugferdDocumentValidator($facturxpdf);
        $res = $pdfCheck->validateDocument();
        if (count($res) > 0) {
            // if (empty($this->_getDolGlobalString('FACTURX_USE_TRIGGER',''))) {
            $allErrors = $this->_getAllMessages($res);
            setEventMessages($allErrors, [], 'errors');
            dol_syslog(get_class($this) . '::executeHooks XML validation error (2) : ' . $allErrors, LOG_ERR);
            // } else {
            // 	$this->errors[] = json_encode($res);
            // }
        }

        $pdfBuilder = new ZugferdDocumentPdfBuilder($facturxpdf, $orig_pdf);

        $pdfBuilder->generateDocument();

        $new_pdf = $orig_pdf;
		if ($this->_getDolGlobalString('FACTURX_SUFFIX_ENABLE', '') != '') {
			$suffix = $this->_getDolGlobalString('FACTURX_SUFFIX_CUSTOM','_facturx');
            $new_pdf = str_replace('.pdf', $suffix . '.pdf', $orig_pdf);
        }
        $pdfBuilder->saveDocument($new_pdf);
        if (!empty($this->_getDolGlobalString('FACTURX_SUFFIX_ENABLE', '')) && file_exists($new_pdf)) {
            rename($new_pdf, $orig_pdf);
        }
        clearstatcache(true);
        dol_syslog('build_minimal_pdf::end, file saved as ' . $orig_pdf);
    }


	// ****************************************************************** PRIVATES FUNCTIONS BELLOW ****************************************************************** //
	// note: please prefix all private function with "_" char


	/**
	 * get a timestamp and return a php DateTime object
	 *
	 * @param   $ts  timestamp
	 *
	 * @return \DateTime
	 */
	private function _tsToDateTime($ts) {
		dol_syslog("facturx call _tsToDateTime for $ts ...");
		if(empty($ts)) {
			return null;
		}
		$dt = new \DateTime();
		$dt->setTimestamp($ts);
		return $dt;
	}

    /**
     * determines the delivery dates and the corresponding order numbers within two arrays
     *
     * @param Array   $customerOrderReferenceList  array to store the corresponding order ids as strings
     * @param Array   $deliveryDateList            array to store the corresponding delivery dates as string in format YYYY-MM-DD
     * @param Facture $object invoice              object
     */
    private function _determineDeliveryDatesAndCustomerOrderNumbers(&$customerOrderReferenceList, &$deliveryDateList, $object)
    {
        $object->fetchObjectLinked();

        // check for delivery notes and correponding real delivery dates
        if (isset($object->linkedObjectsIds['shipping']) && is_array($object->linkedObjectsIds['shipping'])) {
            foreach ($object->linkedObjectsIds['shipping'] as $expeditionId) {
                $expedition = new \Expedition($this->db);
                $expeditionFetchResult = $expedition->fetch($expeditionId);
                if ($expeditionFetchResult > 0) {
                    if (!empty($expedition->origin) && $expedition->origin == "commande" && !empty($expedition->origin_id)) {
                        $commande = new \Commande($this->db);
                        $commandeFetchResult = $commande->fetch($expedition->origin_id);
                        if ($commandeFetchResult > 0 && !empty($commande->ref_client)) {
                            $customerOrderReferenceList[] = $commande->ref_client;
                        }
                    }
                    if (!empty($expedition->date_delivery)) {
                        $deliveryDateList[] = date('Y-m-d', $expedition->date_delivery);
                    }
                }
            }
        }

        // if delivery notes are linked and take the real delivery date from there. if no delivery notes are available,
        // take delivery date from order.
        if (isset($object->linkedObjectsIds['commande']) && is_array($object->linkedObjectsIds['commande'])) {
            foreach ($object->linkedObjectsIds['commande'] as $commandeId) {
                $commande = new \Commande($this->db);
                $commandeFetchResult = $commande->fetch($commandeId);
                if ($commandeFetchResult > 0) {
                    if (!empty($commande->ref_client)) {
                        $customerOrderReferenceList[] = $commande->ref_client;
                    }

                    $commande->fetchObjectLinked();

                    $found = 0;
                    if (!empty($commande->linkedObjectsIds) && !empty($commande->linkedObjectsIds['shipping']) && count($commande->linkedObjectsIds['shipping']) > 0) {
                        foreach ($commande->linkedObjectsIds['shipping'] as $expeditionId) {
                            $expedition = new \Expedition($this->db);
                            $expeditionFetchResult = $expedition->fetch($expeditionId);
                            if ($expeditionFetchResult > 0) {
                                if (!empty($expedition->date_delivery)) {
                                    $found++;
                                    $deliveryDateList[] = date('Y-m-d', $expedition->date_delivery);
                                }
                            }
                        }
                    }
                    if ($found == 0) {
                        if (!empty($commande->delivery_date)) {
                            $deliveryDateList[] = date('Y-m-d', $commande->delivery_date);
                        }
                    }
                }
            }
        }

        $customerOrderReferenceList = array_unique($customerOrderReferenceList);
        sort($customerOrderReferenceList);
        $deliveryDateList = array_unique($deliveryDateList);
        rsort($deliveryDateList);
    }


    /**
     * return IEC_6523 code (https://docs.peppol.eu/poacc/billing/3.0/codelist/ICD/)
     *
     * TODO: add other countries, at least europeans countries ...
     *
     * @return string code
     */
    private function _IEC_6523_code($country_code)
    {
        $retour = "";
        switch ($country_code) {
            case 'BE':
                $retour = "0008";
                break;
            case 'DE':
                $retour = "0000";
                break;
            case 'FR':
                $retour = "0009";
                break;
            default:
        }
        return $retour;
    }

	/**
	 * extract id prof : it depends on country ...
	 *
	 * @param   $thirdpart  dolibarr thirdpart
	 *
	 * @return  string return siret siren or locale prod if
	 */
    private function _idprof($thirdpart)
    {
        $retour = "";
        switch ($thirdpart->country_code) {
            case 'BE':
                $retour = $thirdpart->idprof1;
                break;
            case 'DE':
                if (!empty($thirdpart->idprof6)) {
                    $retour = $thirdpart->idprof6;
                    break;
                } elseif (!empty($thirdpart->idprof2) && !empty($thirdpart->idprof3)) {
                    $retour = $thirdpart->idprof2 . $thirdpart->idprof3;
                } else {
                    $retour = $thirdpart->idprof1;
                }
                break;
                //SIRET
            case 'FR':
                $retour = $thirdpart->idprof2;
                break;
            default:
                $retour = $thirdpart->idprof2;
        }
        return $this->_remove_spaces($retour);
    }

	/**
	 * buyer id prof depends on country
	 *
	 * @return  string idprof
	 */
    private function _thirdpartyidprof()
    {
        return $this->_idprof($this->_invoice->thirdparty);
    }

    /** VMA calcul n°tva intracomm si absent
    * in France only
    *
    */
    private function _thirdpartyCalcTva_intra(\Facture &$object)
    {
        if ($object->thirdparty->country_code == 'FR' && empty($object->thirdparty->tva_intra) && !empty($object->thirdparty->tva_assuj)) {
            $siren = trim($object->thirdparty->idprof1);
            if (empty($siren)) {
                $siren = (int) substr(str_replace(' ', '', $object->thirdparty->idprof2), 0, 9);
            }
            if (!empty($siren)) {
                // [FR + code clé  + numéro SIREN ]
                //Clé TVA = [12 + 3 × (SIREN modulo 97)] modulo 97
                $cle = (12 + (3 * $siren % 97)) % 97;
                $object->thirdparty->tva_intra = 'FR'.$cle.$siren;
            }
        }
    }

    /************************************************
     *    Check line type from external module ?
     *
     * @param  object $line       line we work on
     * @param  string $element    line object element (for special case like shipping)
     * @param  string $searchName module name we look for
     * @return boolean                        true if the line is a special one and was created by the module we ask for
     ************************************************/
    private function _isLineFromExternalModule($line, $element, $searchName)
    {
        global $db;

        if ($element == 'shipping' || $element == 'delivery') {
            $fk_origin_line    = $line->fk_origin_line;
            $line            = new \OrderLine($db);
            $line->fetch($fk_origin_line);
        }
        if ($line->product_type == 9 && $line->special_code == $this->_get_mod_number($searchName)) {
            return true;
        } else {
            return false;
        }
    }

    /************************************************
     *    Find module number
     *
     * @param  string $searchName module name we look for
     * @return integer                        -1 if KO, 0 not found or module number if Ok
     ************************************************/
    private function _get_mod_number($modName)
    {
        global $db;

        if (class_exists($modName)) {
            $objMod    = new $modName($db);
            return $objMod->numero;
        }
        return 0;
    }

	/**
	 * remove spaces from string for example french people add spaces into long numbers like
	 * SIRET: 844 431 239 00020
	 *
	 * @param   string  $str  string to cleanup
	 *
	 * @return  string  cleaned up string
	 */
    private function _remove_spaces($str)
    {
        return preg_replace('/\s+/', '', $str);
    }

    /************************************************
     *    Find paymentMean number
     *
     * @param  object $invoice object name we look for
     * @return integer                        paymentMeanId for HorstOeko libs
     ************************************************/
    private function _get_paymentMean_number($invoice)
    {
        $paymentMeanId = 97; //"Must be defined between trading parties" for empty values
        switch ($invoice->mode_reglement_code) {
            case 'CB': $paymentMeanId = 54;
                break; //Credit Card
            case 'CHQ': $paymentMeanId = 20;
                break; //Check
            case 'FAC': $paymentMeanId = 1;
                break; //Local payment method
            case 'LIQ': $paymentMeanId = 10;
                break; //Cash
            case 'PRE': $paymentMeanId = 59;
                break; //SEPA direct debit
            case 'TIP': $paymentMeanId = 45;
                break; //Bank Transfer with document
            case 'TRA': $paymentMeanId = 23;
                break; //Check
            case 'VAD': $paymentMeanId = 68;
                break; //Online Payment
            case 'VIR': $paymentMeanId = 30;
                break; //Bank Transfer
        }
        return $paymentMeanId;
    }

    private function _getAllMessages(ConstraintViolationListInterface $errObj)
    {
        $ret = "";
        foreach ($errObj as $r) {
            $ret .= $r->getPropertyPath() . " : " . $r->getMessage() . "\n";
        }
        return $ret;
    }

    /**
     *      Return a PDF instance object. We create a FPDI instance that instantiate TCPDF.
     *
     *      @param	string		$format         Array(width,height). Keep empty to use default setup.
     *      @param	string		$metric         Unit of format ('mm')
     *      @param  string		$pagetype       'P' or 'l'
     *      @return TCPDF|TCPDI					PDF object
     */
    private function _pdf_getInstance($format = '', $metric = 'mm', $pagetype = 'P')
    {
        global $conf;

        // Define constant for TCPDF
        if (!defined('K_TCPDF_EXTERNAL_CONFIG')) {
            define('K_TCPDF_EXTERNAL_CONFIG', 1); // this avoid using tcpdf_config file
            define('K_PATH_CACHE', DOL_DATA_ROOT.'/admin/temp/');
            define('K_PATH_URL_CACHE', DOL_DATA_ROOT.'/admin/temp/');
            dol_mkdir(K_PATH_CACHE);
            define('K_BLANK_IMAGE', '_blank.png');
            define('PDF_PAGE_FORMAT', 'A4');
            define('PDF_PAGE_ORIENTATION', $pagetype);
            define('PDF_CREATOR', 'TCPDF');
            define('PDF_AUTHOR', 'TCPDF');
            define('PDF_HEADER_TITLE', 'TCPDF Example');
            define('PDF_HEADER_STRING', "by Dolibarr ERP CRM");
            define('PDF_UNIT', $metric);
            define('PDF_MARGIN_HEADER', 5);
            define('PDF_MARGIN_FOOTER', 10);
            define('PDF_MARGIN_TOP', 27);
            define('PDF_MARGIN_BOTTOM', 25);
            define('PDF_MARGIN_LEFT', 15);
            define('PDF_MARGIN_RIGHT', 15);
            define('PDF_FONT_NAME_MAIN', 'helvetica');
            define('PDF_FONT_SIZE_MAIN', 10);
            define('PDF_FONT_NAME_DATA', 'helvetica');
            define('PDF_FONT_SIZE_DATA', 8);
            define('PDF_FONT_MONOSPACED', 'courier');
            define('PDF_IMAGE_SCALE_RATIO', 1.25);
            define('HEAD_MAGNIFICATION', 1.1);
            define('K_CELL_HEIGHT_RATIO', 1.25);
            define('K_TITLE_MAGNIFICATION', 1.3);
            define('K_SMALL_RATIO', 2 / 3);
            define('K_THAI_TOPCHARS', true);
            define('K_TCPDF_CALLS_IN_HTML', true);
            if ($this->_getDolGlobalString('TCPDF_THROW_ERRORS_INSTEAD_OF_DIE')) {
                define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
            } else {
                define('K_TCPDF_THROW_EXCEPTION_ERROR', false);
            }
        }

        // Load TCPDF
        require_once TCPDF_PATH.'tcpdf.php';

        // We need to instantiate tcpdi object (instead of tcpdf) to use merging features. But we can disable it (this will break all merge features).
        if (!$this->_getDolGlobalString('MAIN_DISABLE_TCPDI')) {
            require_once TCPDI_PATH.'tcpdi.php';
        }

        //$arrayformat=pdf_getFormat();
        //$format=array($arrayformat['width'],$arrayformat['height']);
        //$metric=$arrayformat['unit'];

        $pdfa = false; // PDF-1.3
        if ($this->_getDolGlobalString('PDF_USE_A')) {
            $pdfa = $this->_getDolGlobalString('PDF_USE_A'); 	// PDF/A-1 ou PDF/A-3
        }

        if (!$this->_getDolGlobalString('MAIN_DISABLE_TCPDI') && class_exists('TCPDI')) {
            $pdf = new \TCPDI($pagetype, $metric, $format, true, 'UTF-8', false, $pdfa);
        } else {
            $pdf = new \TCPDF($pagetype, $metric, $format, true, 'UTF-8', false, $pdfa);
        }

        // Protection and encryption of pdf
        if ($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION')) {
            /* Permission supported by TCPDF
            - print : Print the document;
            - modify : Modify the contents of the document by operations other than those controlled by 'fill-forms', 'extract' and 'assemble';
            - copy : Copy or otherwise extract text and graphics from the document;
            - annot-forms : Add or modify text annotations, fill in interactive form fields, and, if 'modify' is also set, create or modify interactive form fields (including signature fields);
            - fill-forms : Fill in existing interactive form fields (including signature fields), even if 'annot-forms' is not specified;
            - extract : Extract text and graphics (in support of accessibility to users with disabilities or for other purposes);
            - assemble : Assemble the document (insert, rotate, or delete pages and create bookmarks or thumbnail images), even if 'modify' is not set;
            - print-high : Print the document to a representation from which a faithful digital copy of the PDF content could be generated. When this is not set, printing is limited to a low-level representation of the appearance, possibly of degraded quality.
            - owner : (inverted logic - only for public-key) when set permits change of encryption and enables all other permissions.
            */

            // For TCPDF, we specify permission we want to block
            $pdfrights = ($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_RIGHTS') ? json_decode($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_RIGHTS'), true) : array('modify', 'copy')); // Json format in llx_const

            // Password for the end user
            $pdfuserpass = $this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_USERPASS');

            // Password of the owner, created randomly if not defined
            $pdfownerpass = ($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_OWNERPASS') ? $this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_OWNERPASS') : null);

            // For encryption strength: 0 = RC4 40 bit; 1 = RC4 128 bit; 2 = AES 128 bit; 3 = AES 256 bit
            $encstrength = (int) $this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_STRENGTH', 0);

            // Array of recipients containing public-key certificates ('c') and permissions ('p').
            // For example: array(array('c' => 'file://../examples/data/cert/tcpdf.crt', 'p' => array('print')))
            $pubkeys = ($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_PUBKEYS') ? json_decode($this->_getDolGlobalString('PDF_SECURITY_ENCRYPTION_PUBKEYS'), true) : null); // Json format in llx_const

            $pdf->SetProtection($pdfrights, $pdfuserpass, $pdfownerpass, $encstrength, $pubkeys);
        }

        return $pdf;
    }


	/**
	 * local version of dolibarr getDolGlobalString to be
	 * compatible with dolibarr < 14 where second arg was
	 * was not present
	 *
	 * @param   $key     dolibarr conf key
	 * @param   $default default value as string
	 *
	 * @return  string dolibarr setup value
	 */
    private function _getDolGlobalString($key, $default = '')
    {
        if (function_exists('getDolGlobalString')) {
            if (((int) DOL_VERSION) < 15) {
                $res = getDolGlobalString($key);
                if (empty($res)) {
                    $res = $default;
                }
                return $res;
            } else {
                /** @phpstan-ignore-next-line */
                return getDolGlobalString($key, $default);
            }
        }
        global $conf;
        // return $conf->global->$key ?? $default;
        return (string) (empty($conf->global->$key) ? $default : $conf->global->$key);
    }

	/**
	 * map type of invoices dolibarr <-> facturx
	 *
	 * @return  string|null code of invoice type
	 */
	private function _getTypeOfInvoice() {
		$map = [
			\CommonInvoice::TYPE_STANDARD => ZugferdInvoiceType::INVOICE,
			\CommonInvoice::TYPE_REPLACEMENT => ZugferdInvoiceType::CORRECTION,
			\CommonInvoice::TYPE_CREDIT_NOTE => ZugferdInvoiceType::CREDITNOTE, //avoir
			\CommonInvoice::TYPE_DEPOSIT => ZugferdInvoiceType::PREPAYMENTINVOICE, //acompte
			// CommonInvoice::TYPE_PROFORMA =>
			// CommonInvoice::TYPE_SITUATION =>
		];
		return $map[$this->_invoice->type] ?? null;
	}

	/**
	 * extract mail from contact or thirdparty
	 *
	 * @param   $contact dolibarr contact
	 * @param   $thirdpart  dolibarr thirdpart/societe
	 *
	 * @return  string email of buyer
	 */
	private function _extractBuyerMail($contact, $thirdpart) {
		dol_syslog(("facturx _extractBuyerMail : contact=" . $contact->email . " | soc=" . $thirdpart->email));
		if(!empty($contact->email)) {
			return $contact->email;
		}
		return $thirdpart->email;
	}

}
