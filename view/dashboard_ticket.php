<?php
/* Copyright (C) 2022 EVARISK <dev@evarisk.com>
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
 *	\file       dashboard_ticket.php
 *	\ingroup    digiriskdolibarr
 *	\brief      Dashboard page of Ticket
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res          = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if ( ! $res && file_exists("../../main.inc.php")) $res    = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if ( ! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

require_once './../lib/digiriskdolibarr_function.lib.php';

global $conf, $db, $user, $langs;

// Load translation files required by the page
$langs->loadLangs(array("digiriskdolibarr@digiriskdolibarr"));

// Security check
if ( ! $user->rights->digiriskdolibarr->lire && $user->rights->ticket->read) accessforbidden();

/*
 * View
 */

$help_url = 'FR:Module_DigiriskDolibarr';
$morejs   = array("/digiriskdolibarr/js/digiriskdolibarr.js.php");
$morecss  = array("/digiriskdolibarr/css/digiriskdolibarr.css");

llxHeader("", $langs->trans("DashBoardTicket"), $help_url, '', '', '', $morejs, $morecss);

print load_fiche_titre($langs->trans("DashBoardTicket"), '', 'digiriskdolibarr32px.png@digiriskdolibarr');

/*
 * Dashboard Ticket
 */

if (empty($conf->global->MAIN_DISABLE_WORKBOARD)) {
	//Array that contains all WorkboardResponse classes to process them
	$dashboardlines = array();

	// Do not include sections without management permission
	require_once DOL_DOCUMENT_ROOT.'/core/class/workboardresponse.class.php';

	$arrayService = array(
		'admin' => array(
			'name' => $langs->trans('Administration'),
			'picto' => '<i class="fas fa-hammer"></i>',
		),
		'RH' => array(
			'name' => $langs->trans('RH'),
			'picto' => '<i class="fas fa-hammer"></i>',
		),
	);

	$arrayCats = array(
		'Register' => array(
			'name' => $langs->trans('Register'),
			'label' => $langs->trans('TotalRegisterByService'),
		),
		'AccidentWithDIAT' => array(
			'name' => $langs->trans('AccidentWithDIAT'),
			'label' => $langs->trans('TotalAccidentWithDIATByService'),
		),
		'AccidentWithoutDIAT' => array(
			'name' => $langs->trans('AccidentWithoutDIAT'),
			'label' => $langs->trans('TotalAccidentWithoutDIATByService'),
		),
		'MaterialProblem' => array(
			'name' => $langs->trans('MaterialProblem'),
			'label' => $langs->trans('TotalMaterialProblemByService'),
		),
		'HumanProblem' => array(
			'name' => $langs->trans('HumanProblem'),
			'label' => $langs->trans('TotalHumanProblemByService'),
		),
//		'RPS' => array(
//			'name' => $langs->trans('RPS'),
//			'label' => $langs->trans('Test'),
//		),
		'DGI' => array(
			'name' => $langs->trans('DGI'),
			'label' => $langs->trans('TotalDGIByService'),
		),
	);

	print '<div class="fichecenter">';

	foreach ($arrayService as $service) {
		foreach ($arrayCats as $key => $cat) {
			if (!empty($conf->ticket->enabled) && $user->rights->ticket->read) {
				$dashboardlines['ticket'][$service['name']][$key] = load_board($cat['name'], $cat['label'], $service['name']);
			}
		}

		print '<div class="test">';
		print load_fiche_titre($service['name'], '', 'building');
		print '</div>';

		// Show dashboard
		if (!empty($dashboardlines)) {
			$openedDashBoard = '';
			foreach ($dashboardlines['ticket'][$service['name']] as $key => $board) {
				$openedDashBoard .= '<div class="box-flex-item"><div class="box-flex-item-with-margin">';
				$openedDashBoard .= '<div class="info-box info-box-sm">';
				$openedDashBoard .= '<span class="info-box-icon bg-infobox-ticket">';
				$openedDashBoard .= '<i class="fa fa-dol-ticket"></i>';
				$openedDashBoard .= '</span>';
				$openedDashBoard .= '<div class="info-box-content">';
				$openedDashBoard .= '<div class="info-box-title" title="' . strip_tags($key) . '">' . $langs->trans($key) . '</div>';
				$openedDashBoard .= '<div class="info-box-lines">';
				$openedDashBoard .= '<div class="info-box-line">';
				$openedDashBoard .= '<span class="marginrightonly">' . $board->label . '</span>';
				$openedDashBoard .= '</span>';
				$openedDashBoard .= '</div>';
				$openedDashBoard .= '</div><!-- /.info-box-lines --></div><!-- /.info-box-content -->';
				$openedDashBoard .= '</div><!-- /.info-box -->';
				$openedDashBoard .= '</div><!-- /.box-flex-item-with-margin -->';
				$openedDashBoard .= '</div><!-- /.box-flex-item -->';
			}

			print '<div class="opened-dash-board-wrap"><div class="box-flex-container">'.$openedDashBoard.'</div></div>';
		}
	}

	print '</div>';
}

// End of page
llxFooter();
$db->close();
