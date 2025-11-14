<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2023 Eric Seigne <eric.seigne@cap-rel.fr>
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
 * \file    facturx/admin/support.php
 * \ingroup facturx
 * \brief   Support page of module facturx.
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

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/facturx.lib.php';

dol_include_once('/facturx/core/modules/modFacturx.class.php');
$tmpmodule = new modFacturx($db);


// Translations
$langs->loadLangs(array("errors", "admin", "facturx@facturx"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$error = 0;
// $setupnotempty = 0;

$useFormSetup = 1;

require_once __DIR__.'/../backport/v16/core/class/html.formsetup.class.php';

$phparray=phpinfo_array();

$formSetup = new custom\facturx\FormSetup($db);
$form = new Form($db);

$item = $formSetup->newItem('facturx_SOC_NAME');
$item->fieldValue = $mysoc->name;

$item = $formSetup->newItem('facturx_SOC_MAIL');
$item->fieldValue = $mysoc->email;

$item = $formSetup->newItem('facturx_MOD_NAME');
$item->fieldValue = $tmpmodule->name;

$item = $formSetup->newItem('facturx_MOD_VERSION');
$item->fieldValue = $tmpmodule->version;

$item = $formSetup->newItem('facturx_DOL_VERSION');
$item->fieldValue = DOL_VERSION;

$item = $formSetup->newItem('facturx_PHP_VERSION');
$item->fieldValue = phpversion();

$item = $formSetup->newItem('facturx_SERVER_OS');
$item->fieldValue = $phparray['General']['Server API'];

$item = $formSetup->newItem('facturx_DATABASE');
$item->fieldValue = $db::LABEL.' '.$db->getVersion();

$item = $formSetup->newItem('facturx_USER_AGENT');
$item->fieldValue = dol_escape_htmltag($_SERVER['HTTP_USER_AGENT']);


$item = $formSetup->newItem('facturx_DESCRIPTION')->setAsTextarea();
// $item->

/*
 * Actions
 */
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * View
 */

$help_url = '';
$page_name = "facturxSupport";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'object_facturx@facturx');

// Configuration header
$head = \custom\facturx\AdminPrepareHead();
print dol_get_fiche_head($head, 'support', '', -1, "facturx@facturx");

print "<h1>" . $langs->trans("facturxSupportCenter") . "</h1>";

// $facturx->setDebug(true);

print "<p>" . $langs->transnoentitiesnoconv("facturxSupportPresentation") . "</p>";

print "<p><a href='https://cap-rel.fr/sav-module-dolibarr/'>https://cap-rel.fr/sav-module-dolibarr/</a></p>";

// TODO END STUFF
// print $formSetup->generateOutput(true,true);


// $del = $customerRead->delete();
// print "<p>Delete = " . $del . "</p>";

// $list = facturx\Payment::list(['created' => '1660338149', 'limit' => 2]);

// foreach ($list as $payment) {
//   // Do some stuff with $payment
//   print "<p>" . json_encode($payment) . "</p>";
// }

// print "<p>Apres le list</p>";

// $card = new facturx\Card();
// $card->setNumber('5555555555554444');
// $card->setCvc('123');
// $card->setExpirationMonth('02');
// $card->setExpirationYear('2023');

// $customer = new facturx\Customer();
// $customer->setEmail('david@example.net');
// $customer->setMobile('+33639980102');
// $customer->setName('David Coaster');

// $payment = new facturx\Payment();
// $payment->setAmount(100);
// $payment->setCard($card);
// $payment->setCurrency('eur');
// $payment->setCustomer($customer);
// $payment->setDescription('Test Payment Company');

// $res = $payment->send();
// //paym_QClw6D2VqTxNIOTySbExArZd
// print json_encode($res);

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
