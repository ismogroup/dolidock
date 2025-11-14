<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2021-2024 Éric Seigne <eric.seigne@cap-rel.fr>
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
 * \file    facturx/admin/setup.php
 * \ingroup facturx
 * \brief   Facturx setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/facturx.lib.php';
require_once '../vendor/horstoeko/zugferd/src/ZugferdProfiles.php';
//require_once "../class/myclass.class.php";

// Translations
$langs->loadLangs(array("admin", "facturx@facturx"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$arrayofparameters = array(
	''=>array('css'=>'', 'enabled'=>1),
);

$error = 0;
$setupnotempty = 0;

$useFormSetup = 1;

require_once __DIR__.'/../backport/v16/core/class/html.formsetup.class.php';

$formSetup = new custom\facturx\FormSetup($db);
$form = new Form($db);

$formSetup->newItem('FACTURX_SUFFIX_ENABLE')->setAsYesNo();
$formSetup->newItem('FACTURX_CONCAT_BEFORE')->setAsYesNo();
$formSetup->newItem('FACTURX_DISABLE_CHORUS_EXTRAFIELDS')->setAsYesNo();
$formSetup->newItem('FACTURX_GLOBAL_IDENTIFIER_DISABLE')->setAsYesNo();
$formSetup->newItem('FACTURX_GLOBAL_IDENTIFIER_CUSTOM')->setAsString();
$formSetup->newItem('FACTURX_GLOBAL_TRADECONTACT_DISABLE')->setAsYesNo();
$formSetup->newItem('FACTURX_XML_STANDALONE')->setAsYesNo();
$profiles = [
	\horstoeko\zugferd\ZugferdProfiles::PROFILE_EN16931 => 'PROFILE_EN16931',
	\horstoeko\zugferd\ZugferdProfiles::PROFILE_XRECHNUNG_3 => 'PROFILE_XRECHNUNG_3 (experimental)'
];
$item = $formSetup->newItem('FACTURX_PROFILE')->setAsSelect($profiles);
$item->defaultFieldValue = \horstoeko\zugferd\ZugferdProfiles::PROFILE_EN16931;

//TODO ? need to make hack of pdf templates and maybe others unpredictable files
//or make a full pdf page with only logo and merge it with first page of main pdf ?
// $formSetup->newItem('FACTURX_XML_ADD_LOGO')->setAsYesNo();

$formSetup->newItem('FACTURX_EXPERIMENTAL')->setAsTitle();
$formSetup->newItem('FACTURX_USE_TRIGGER')->setAsYesNo();
$formSetup->newItem('FACTURX_FORCE_ALL_INVOICE_TYPE')->setAsYesNo();

// $formSetup->newItem('SMARTDLC_DELAYS')->setAsTitle();
// $formSetup->newItem('SMARTDLC_OPEN_MAX_DELAY');

// if (isModEnabled('uptosign')) {
// 	$formSetup->newItem('SMARTDLC_AUTO_UPTOSIGN_ON_UPLOAD')->setAsYesNo();
// } else {
// 	$formSetup->newItem('SMARTDLC_AUTO_PSEUDOSIGN_ON_UPLOAD')->setAsYesNo();
// }

// $formSetup->newItem('SMARTDLC_AUTO_ADD_EVENT_ON_AGENDA')->setAsYesNo();
// $formSetup->newItem('SMARTDLC_AUTO_REMOVE_EVENT_ON_AGENDA')->setAsYesNo();

// $item = $formSetup->newItem('SMARTDLC_ADD_EXTRAFIELDS');

// if (!isset($extrafields) || !is_object($extrafields)) {
// 	require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
// 	$extrafields = new ExtraFields($db);
// }
// $allOptions = $extrafields->fetch_name_optionals_label('fichinter');
// $options = [];
// foreach ($allOptions as $key => $val) {
// 	if (preg_match('/smartdlc/', $key)) {
// 		//nothing pass next
// 	} else {
// 		$options[$key] = $val;
// 	}
// }
// $item->setAsMultiSelect($options);
// $item->helpText = $langs->transnoentities('SMARTDLC_ADD_EXTRAFIELDS');


// $compteid = null;
// // $liste = $form->select_comptes($compteid, 'SMARTDLC_BANK_ACCOUNT_FOR_PAYMENTS', 0, "courant=1", 2,'',0,'',1);
// $sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_account";
// $result = $db->query($sql);
// $options = array();
// if ($result) {
// 	while ($obj = $db->fetch_object($result)) {
// 		$options[$obj->rowid] = $obj->label;
// 	}
// }
// //note il faut utiliser des noms particuliers pour bénéficier du masquage de données:
// //if (!preg_match('/^MAIN_LOGEVENTS/', $name) && (preg_match('/(_KEY|_EXPORTKEY|_SECUREKEY|_SERVERKEY|_PASS|_PASSWORD|_PW|_PW_TICKET|_PW_EMAILING|_SECRET|_SECURITY_TOKEN|_WEB_TOKEN)$/', $name))) {


// $formSetup->newItem('SMARTDLC_BANK')->setAsTitle();
// $formSetup->newItem('SMARTDLC_BANK_ACCOUNT_FOR_PAYMENTS')->setAsSelect($options);
// $formSetup->newItem('SMARTDLC_BANK_MAIN_ACCOUNT_FOR_PAYOUTS')->setAsSelect($options);
// // $formSetup->newItem('SMARTDLC_ADD_FEES_ON_BANK_FOR_EACH_PAYMENT')->setAsYesNo();
// // $formSetup->newItem('SMARTDLC_ADD_FEES_ON_BANK_FOR_EACH_PAYOUT')->setAsYesNo();

// $item = $formSetup->newItem('SMARTDLC_ADD_FEES');
// $TField = array(
// 	'NONE' => $langs->trans('SMARTDLC_ADD_FEES_ON_BANK_NONE'),
// 	'PAYOUT' => $langs->trans('SMARTDLC_ADD_FEES_ON_BANK_FOR_EACH_PAYOUT'),
// );
// //a verifier par rapport a la factuation ça semble trop complexe
// //	'PAYMENT' => $langs->trans('SMARTDLC_ADD_FEES_ON_BANK_FOR_EACH_PAYMENT'),

// $item->setAsSelect($TField);
// $item->helpText = $langs->transnoentities('SMARTDLC_ADD_FEESTooltip');

// $formSetup->newItem('SMARTDLC_IS_PROD')->setAsYesNo();

// $formSetup->newItem('SMARTDLC_TEST_PARAMS')->setAsTitle();
// // Setup conf SMARTDLC_TEST_PUBLIC_KEY as a simple string input
// $item = $formSetup->newItem('SMARTDLC_TEST_PUBLIC_KEY');
// // Setup conf SMARTDLC_TEST_PRIVATE_KEY
// $item = $formSetup->newItem('SMARTDLC_TEST_PRIVATE_KEY');


// $formSetup->newItem('SMARTDLC_PROD_PARAMS')->setAsTitle();
// // Setup conf SMARTDLC_PROD_PUBLIC_KEY as a simple string input
// $item = $formSetup->newItem('SMARTDLC_PROD_PUBLIC_KEY');
// // Setup conf SMARTDLC_PROD_PRIVATE_KEY
// $item = $formSetup->newItem('SMARTDLC_PROD_PRIVATE_KEY');

// $item = $formSetup->newItem('SMARTDLC_NUMBER_OF_ITEMS_TO_SYNC');
// $TField = array(
// 	10 => '10',
// 	20 => '20',
// 	30 => '30',
// 	40 => '40',
// 	50 => '50',
// 	60 => '60',
// 	70 => '70',
// 	80 => '80',
// 	90 => '90',
// 	100 => '100',
// );
// $item->setAsSelect($TField);
// $item->helpText = $langs->transnoentities('SMARTDLC_NUMBER_OF_ITEMS_TO_SYNCTooltip');

// $item = $formSetup->newItem('SMARTDLC_NB_DAYS_TO_SYNC');
// $TField = array(
// 	10 => '10',
// 	20 => '20',
// 	30 => '30',
// 	40 => '40',
// 	50 => '50',
// 	60 => '60'
// );
// $item->setAsSelect($TField);
// $item->helpText = $langs->transnoentities('SMARTDLC_NB_DAYS_TO_SYNCTooltip');


// $sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE status='1' AND client='1' AND entity = '".$conf->entity."'";
// $result = $db->query($sql);
// $options = array();
// if ($result) {
// 	while ($obj = $db->fetch_object($result)) {
// 		$options[$obj->rowid] = $obj->nom;
// 	}
// }
// $formSetup->newItem('SMARTDLC_DEFAULT_CUSTOMER_IF_NULL')->setAsSelect($options);

// $formSetup->newItem('SMARTDLC_PUBLIC_CB_PAGE')->setAsYesNo();


// Setup conf SMARTDLC_MYPARAM2 as a simple textarea input but we replace the text of field title
// $item = $formSetup->newItem('SMARTDLC_MYPARAM2');
// $item->nameText = $item->getNameText().' more html text ';

// // Setup conf SMARTDLC_MYPARAM3
// $item = $formSetup->newItem('SMARTDLC_MYPARAM3');
// $item->setAsThirdpartyType();

// // Setup conf SMARTDLC_MYPARAM5
// $formSetup->newItem('SMARTDLC_MYPARAM5')->setAsEmailTemplate('thirdparty');

// // Setup conf SMARTDLC_MYPARAM6
// $formSetup->newItem('SMARTDLC_MYPARAM6')->setAsSecureKey()->enabled = 1; // disabled

// // Setup conf SMARTDLC_MYPARAM7
// $formSetup->newItem('SMARTDLC_MYPARAM7')->setAsProduct();

// $formSetup->newItem('Title')->setAsTitle();

// // Setup conf SMARTDLC_MYPARAM8
// $item = $formSetup->newItem('SMARTDLC_MYPARAM8');
// $TField = array(
// 	'test01' => $langs->trans('test01'),
// 	'test02' => $langs->trans('test02'),
// 	'test03' => $langs->trans('test03'),
// 	'test04' => $langs->trans('test04'),
// 	'test05' => $langs->trans('test05'),
// 	'test06' => $langs->trans('test06'),
// );
// $item->setAsMultiSelect($TField);
// $item->helpText = $langs->transnoentities('SMARTDLC_MYPARAM8');


// // Setup conf SMARTDLC_MYPARAM9
// $formSetup->newItem('SMARTDLC_MYPARAM9')->setAsSelect($TField);


// // Setup conf SMARTDLC_MYPARAM10
// $item = $formSetup->newItem('SMARTDLC_MYPARAM10');
// $item->setAsColor();
// $item->defaultFieldValue = '#FF0000';
// $item->nameText = $item->getNameText().' more html text ';
// $item->fieldInputOverride = '';
// $item->helpText = $langs->transnoentities('AnHelpMessage');
//$item->fieldValue = '';
//$item->fieldAttr = array() ; // fields attribute only for compatible fields like input text
//$item->fieldOverride = false; // set this var to override field output will override $fieldInputOverride and $fieldOutputOverride too
//$item->fieldInputOverride = false; // set this var to override field input
//$item->fieldOutputOverride = false; // set this var to override field output


$setupnotempty =+ count($formSetup->items);


$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if ( versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessage($langs->trans("SetupSaved"), 'mesgs');
	} else {
		setEventMessage($langs->trans("Error"), 'errors');
	}
} elseif ($action == 'specimen') {
	$modele = GETPOST('module', 'alpha');
	$tmpobjectkey = GETPOST('object');

	$tmpobject = new $tmpobjectkey($db);
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = ''; $classname = ''; $filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/facturx/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
		if (file_exists($file)) {
			$filefound = 1;
			$classname = "pdf_".$modele."_".strtolower($tmpobjectkey);
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($tmpobject, $langs) > 0) {
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=smartdlc-".strtolower($tmpobjectkey)."&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessage($module->error, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessage($langs->trans("ErrorModuleNotFound"), 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'FACTURX_'.strtoupper($tmpobjectkey)."_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$tmpobjectkey = GETPOST('object');
		if (!empty($tmpobjectkey)) {
			$constforval = 'FACTURX_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
			if ($conf->global->$constforval == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'FACTURX_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->$constforval = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'FACTURX_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}



/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "FacturxSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_facturx@facturx');

// Configuration header
$head = \custom\facturx\AdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), 0, "facturx@facturx");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("FacturxSetupPage").'</span><br><br>';

$miss = [];
if (!extension_loaded('gd')) {
	$miss[] = "PHP extension GD is missing";
}
if (!extension_loaded('zlib')) {
	$miss[] = "PHP extension ZLIB is missing";
}

if (count($miss)>0) {
	setEventMessages($langs->trans("Error"), $miss, 'errors');
}

if ($action == 'edit') {
	print $formSetup->generateOutput(true);
	print '<br>';
} elseif (!empty($formSetup->items)) {
	print $formSetup->generateOutput();
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '</div>';
}

if (getDolGlobalString('PDF_SECURITY_ENCRYPTION')) {
	print '<div class="warning">';
	print 'The not supported and hidden option PDF_SECURITY_ENCRYPTION has been enabled. This means a lof of feature related to PDF will be broken, like mass PDF generation or online signature of PDF.'."\n";
	print 'You should disable this option.';
	print '</div>';
}

if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
