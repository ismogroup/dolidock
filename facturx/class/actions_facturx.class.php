<?php
/* Copyright (C) 2021 Éric Seigne <eric.seigne@cap-rel.fr>
 * Copyright (C) 2021 Maximilian Stein <ms@alarm-dispatcher.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    facturx/class/actions_facturx.class.php
 * \ingroup facturx
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

require_once __DIR__ . "/../vendor/setasign/fpdf/fpdf.php";
require_once DOL_DOCUMENT_ROOT . '/compta/bank/class/account.class.php';
dol_include_once('/facturx/lib/facturx.lib.php');
dol_include_once('/facturx/class/facturx.class.php');

$matches  = preg_grep('/Restler\/AutoLoader.php/i', get_included_files());
if (file_exists(__DIR__ . '/../vendor/scoper-autoload.php')) {
	require_once __DIR__ . '/../vendor/scoper-autoload.php';
} elseif (count($matches) == 0) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdPdfWriter;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;

use custom\facturx\FacturX;

// $included_files = get_included_files();
// echo '<pre>';
// foreach ($included_files as $filename) {
//     echo "$filename\n";
// }
// echo '</pre>';

/**
 * Class ActionsFacturX
 */
class ActionsFacturX
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int        Priority of hook (50 is used if value is not defined)
	 */
	public $priority;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		//try cf eldy mail 16/06/22
		$this->priority = 99;
	}


	/**
	 * Execute action
	 *
	 * @param  array        $parameters Array of parameters
	 * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param  string       $action     'add', 'update', 'view'
	 * @return int                             <0 if KO,
	 *                                           =0 if OK but we want to process standard actions too,
	 *                                            >0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Execute action
	 *
	 * @param  array  $parameters Array of parameters
	 * @param  Object $pdfhandler PDF builder handler
	 * @param  string $action     'add', 'update', 'view', 'builddoc'
	 * @return int                     <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;
		global $mysoc;


		$errors = 0;
		$deltemp = array();

		//dol_syslog('facturx::afterPDFCreation::executeHooks params=' . json_encode($outputlangs));

		dol_syslog('facturx::afterPDFCreation::executeHooks action=' . $action);

		// if (!empty(getDolGlobalString('FACTURX_USE_TRIGGER',''))) {
		// 	dol_syslog("FacturX hook is disabled due to module setup who is connected to Trigger events");
		// 	return 0;
		// }
		$facturx_disable = GETPOST('facturx_disable', 'int');
		if (!empty($facturx_disable)) {
			dol_syslog(get_class($this) . '::executeHooks facturx is explicit disabled, return');
			return 0;
		}

		if ($parameters['object']->element != "facture") {
			dol_syslog(get_class($this) . '::executeHooks not a customer invoice but a ' . $parameters['object']->element . ', return');
			return 0;
		}

		//race condition API call action is empty, see https://github.com/Dolibarr/dolibarr/issues/27202
		$requestPath = $_SERVER['REQUEST_URI'];
		if (empty($action) && (strpos($requestPath, '/api/') > 0) && (strpos($requestPath, 'builddoc') > 0)) {
			$action = 'builddoc';
		}

		$massaction = GETPOST('massaction');
		//ES: rq, not only builddoc: in some other cases dolibarr update pdf file then users thinks that XML will be embedded !
		if (empty($action) && isModEnabled("sellyoursaas")) {
			dol_syslog(get_class($this) . '::executeHooks action is empty but sellyoursaas race condition... continue');
		} elseif (!in_array($action, ["builddoc", "updateline", "confirm_valid", "confirm_validate", "confirm_modif", "addline", "confirm_deleteline", "setnote_public", "setnote_private", "confirm_paiement"]) && !in_array($massaction, ["confirm_createbills", "generate_doc"])) {
			dol_syslog(get_class($this) . '::executeHooks action is not in our action list but *' . $action . '*, return');
			return 0;
		}

		//get document language for facturx export
		$outputlang = $parameters['outputlangs']->defaultlang;

		clearstatcache(true);
		$orig_pdf = $parameters['file'];
		$invoice = new \Facture($this->db);
		$invoice->fetch($parameters['object']->id);
		$fx = new FacturX($this->db, $invoice, $outputlang);

		if (strpos($orig_pdf, 'mini.pdf') || strpos($orig_pdf, 'eticket.pdf')) {
			dol_syslog(get_class($this) . '::executeHooks eticket invoice, facturx minimum profile apply');
			$errors = $fx->makeMinimalPDF($orig_pdf);
			return 0;
		}

		if (!is_subclass_of($pdfhandler, 'ModelePDFFactures') && !is_subclass_of($pdfhandler, 'ModeleUltimatePDFFactures')) {
			dol_syslog(get_class($this) . '::executeHooks not a ModelePDFFactures or ModeleUltimatePDFFactures ... but ' . get_class($pdfhandler) . ', parents classes=' . json_encode(class_parents($pdfhandler)) . ' then return');
			if (getDolGlobalString('FACTURX_FORCE_ALL_INVOICE_TYPE')) {
				dol_syslog(get_class($this) . '::executeHooks FACTURX_FORCE_ALL_INVOICE_TYPE is set then continue, cross finger ...');
			} else {
				return 0;
			}
		}

		$errors = $fx->makePDF($orig_pdf);

		dol_syslog(get_class($this) . '::executeHooks end action=' . $action);
		return 0;
	}

	public function afterODTCreation($parameters, &$odthandler, &$action)
	{
		$orig_pdf = preg_replace('/\.od(x|t)/i', '.pdf', $parameters['file']);
		if (file_exists($orig_pdf)) {
			$new_parameters = $parameters;
			$new_parameters['file'] = $orig_pdf;
			dol_syslog(get_class($this) . '::executeHooks action=' . $action . " orig pdf file = " . $orig_pdf);
			return $this->afterPDFCreation($new_parameters, $odthandler, $action);
		}
		return -1;
	}


	/* Add here any other hooked methods... */


	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param  string       $action      Current action (if set). Generally create or edit or null
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs, $db;

		$error = 0; // Error counter
		dol_syslog(get_class($this) . '::doMassActions facturx' . json_encode($parameters));

		// print_r($parameters); print_r($object); echo "action: " . $action;
		if (in_array($parameters['currentcontext'], array('invoicelist'))) {        // do something only for the context 'somecontext1' or 'somecontext2'
			if ($parameters['massaction'] == "facturxZip") {
				$obj = new \Facture($db);

				$destdir = stripslashes($parameters['diroutputmassaction']);
				if (!is_dir($destdir)) {
					dol_syslog(get_class($this) . '::doMassActions make directory ' . $destdir);
					mkdir($destdir, 0700, true);
				}
				$zipname = $destdir . '/archive-facturx.zip';
				$zip = new ZipArchive();
				if ($zip->open($zipname, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
					dol_syslog(get_class($this) . '::doMassActions zip destination is ' . $zipname);
					foreach ($parameters['toselect'] as $objectid) {
						// Do action on each object id
						if ($obj->fetch($objectid) > 0) {
							$fic = $conf->facture->dir_output . '/' . $obj->ref . "/" . $obj->ref . ".pdf";
							if ($this->getDolGlobalString('FACTURX_SUFFIX_ENABLE', '') != '') {
								$suffix = $this->getDolGlobalString('FACTURX_SUFFIX_CUSTOM', '_facturx');
								$newfic = str_replace('.pdf', $suffix . '.pdf', $fic);
								$fic = $newfic;
							}
							if (file_exists($fic)) {
								// print "<p> on ajoute $fic </p>";
								$zip->addFile($fic, basename($fic));
							}
						}
					}
					$zip->close();

					///Then download the zipped file.
					header('Content-Type: application/zip');
					header('Content-disposition: attachment; filename=' . basename($zipname));
					header('Content-Length: ' . filesize($zipname));
					readfile($zipname);
					exit;
				}
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param  array        $parameters  Hook metadatas (context, etc...)
	 * @param  CommonObject $object      The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param  string       $action      Current action (if set). Generally create or edit or null
	 * @param  HookManager  $hookmanager Hook manager propagated to allow calling another hook
	 * @return int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$langs->load("facturx@facturx");
		dol_syslog(get_class($this) . '::addMoreMassActions facturx' . json_encode($parameters));

		$error = 0; // Error counter
		$disabled = 0;

		// print_r($parameters); print_r($object); echo "action: " . $action;
		//method=addMoreMassActions action= context=searchform:leftblock:toprightmenu:main:invoicelist
		if (in_array($parameters['currentcontext'], array('invoicelist'))) {        // do something only for the context 'somecontext1' or 'somecontext2'
			dol_syslog(get_class($this) . '::addMoreMassActions facturx 2');
			$this->resprints = '<option value="facturxZip"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("facturxMassActionZip") . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 *  Add options on builddoc hook
	 *
	 *  @param		array<string,mixed>		$parameters		Array of parameters
	 *  @param		object	$object			Object to use hooks on
	 */
	function formBuilddocOptions($parameters, &$object)
	{
		global $conf, $langs, $bc;

		$action = GETPOST('action', 'none');
		$contextArray = explode(':', $parameters['context']);
		if (in_array('invoicecard', $contextArray)) {
			$langs->load("facturx@facturx");
			$facturx_disable	= isset($_SESSION['facturx_disable'][$object->id]) ?  $_SESSION['facturx_disable'][$object->id] : 0;
			$var = false;
			$out = '<tr class="oddeven">
							<td colspan="5" align="right">
								<label for="facturx_disable">' . $langs->trans('FacturXDisabled') . '</label>
								<input type="checkbox" id="facturx_disable" name="facturx_disable" value="1" ' . (($facturx_disable) ? 'checked="checked"' : '') . ' />
							</td>
							</tr>';
			$this->resprints = $out;
		}
		return 1;
	}


	/**
	 * due to dolibarr 14 support and php scopper whe have to duplicate that code
	 * Return a Dolibarr global constant string value
	 *
	 * @param 	string 				$key 		Key to return value, return $default if not set
	 * @param 	string|int|float 	$default 	Value to return if not defined
	 * @return 	string							Value returned
	 */
	private function getDolGlobalString($key, $default = '')
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
}
