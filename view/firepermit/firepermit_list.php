<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 *   	\file       view/firepermit/firepermit_list.php
 *		\ingroup    digiriskdolibarr
 *		\brief      List page for fire permit
 */

// Load DigiriskDolibarr environment.
if (file_exists('../../digiriskdolibarr.main.inc.php')) {
	require_once __DIR__ . '/../../digiriskdolibarr.main.inc.php';
} elseif (file_exists('../../../digiriskdolibarr.main.inc.php')) {
	require_once __DIR__ . '/../../../digiriskdolibarr.main.inc.php';
} else {
	die('Include of digiriskdolibarr main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// load Saturne libraries.
require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

// load DigiriskDolibarr libraries.
require_once __DIR__ . '/../../class/firepermit.class.php';
require_once __DIR__ . '/../../class/preventionplan.class.php';
require_once __DIR__ . '/../../class/digiriskresources.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page.
saturne_load_langs(['projects', 'companies', 'commercial']);

// Get parameters.
$id         = GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$action     = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$confirm    = GETPOST('confirm', 'alpha');
$cancel     = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page

// Get list parameters.
$massaction  = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files  = GETPOST('show_files', 'int');   // Show files area generated by bulk actions ?
$toselect    = GETPOST('toselect', 'array');   // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetlist'; // To manage different context of search
$optioncss   = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$mode        = GETPOST('mode', 'aZ');

// Get pagination parameters.
$limit     = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST("sortfield", "alpha");
$sortorder = GETPOST("sortorder", 'alpha');
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
$page      = is_numeric($page) ? $page : 0;
$page      = $page == -1 ? 0 : $page;

$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical objects
$object            = new FirePermit($db);
$preventionplan    = new PreventionPlan($db);
$societe           = new Societe($db);
$contact           = new Contact($db);
$usertmp           = new User($db);
$digiriskresources = new DigiriskResources($db);
$signatory         = new SaturneSignature($db, $moduleNameLowerCase, $object->element);

// Initialize view objects.
$form      = new Form($db);
$formother = new FormOther($db);

$hookmanager->initHooks(['firepermitlist']); // Note that conf->hooks_modules contains array.

if (!isset($socid)) {
    $socid = $user->socid != null ? $user->socid : 0;
}

// Default sort order (if not yet defined by previous GETPOST).
if ( ! $sortfield) $sortfield = "t.ref";
if ( ! $sortorder) $sortorder = "ASC";

// Initialize array of search criterias
$search_all = GETPOST('search_all', 'alphanohtml') ? trim(GETPOST('search_all', 'alphanohtml')) : trim(GETPOST('sall', 'alphanohtml'));
$search     = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_' . $key, 'alpha') !== '') $search[$key] = GETPOST('search_' . $key, 'alpha');
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['searchall'])) $fieldstosearchall['t.' . $key] = $val['label'];
}

// Definition of fields for list
$arrayfields = array();

foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if ( ! empty($val['visible'])) $arrayfields['t.' . $key] = array('label' => $val['label'], 'checked' => (($val['visible'] < 0) ? 0 : 1), 'enabled' => ($val['enabled'] && ($val['visible'] != 3)), 'position' => $val['position']);
}

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

// Load FirePermit object
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be include, not include_once.


// Security check (enable the most restrictive one) - Protection if external user
$permissiontoread   = $user->rights->digiriskdolibarr->firepermit->read;
$permissiontoadd    = $user->rights->digiriskdolibarr->firepermit->write;
$permissiontodelete = $user->rights->digiriskdolibarr->firepermit->delete;

saturne_check_access($permissiontoread);

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if ( ! GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend' && $massaction != 'confirm_createbills') { $massaction = ''; }

$parameters = array('socid' => $socid);
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	$backtopage = dol_buildpath('/digiriskdolibarr/view/firepermit/firepermit_list.php', 1);

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
		}

		$toselect             = '';
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	$error = 0;
	if ( ! $error && ($massaction == 'delete' || ($action == 'delete' && $confirm == 'yes')) && $permissiontodelete) {
		if ( ! empty($toselect)) {
			foreach ($toselect as $toselectedid) {
				$objecttodelete = $object;
				$objecttodelete->fetch($toselectedid);

				$objecttodelete->status = 0;
				$result                     = $objecttodelete->delete($user);

				if ($result < 0) {
					// Delete firepermit KO
					if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
					else setEventMessages($object->error, null, 'errors');
				}
			}

			// Delete firepermit OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $_SERVER["PHP_SELF"]);
			exit;
		}
	}
}

/*
 * View
 */

$title    = $langs->trans("FirePermitList");
$helpUrl = 'FR:Module_Digirisk#DigiRisk_-_Permis_de_feu';

saturne_header(1, "", $title, $helpUrl);

// Add $param from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions                                                           = array();
if ($permissiontodelete) $arrayofmassactions['predelete']                     = '<span class="fa fa-trash paddingrightonly"></span>' . $langs->trans("Delete");
if (in_array($massaction, array('presend', 'predelete'))) $arrayofmassactions = array();

$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$newcardbutton = '';
if ($permissiontoadd) {
	$newcardbutton .= dolGetButtonTitle($langs->trans('NewFirePermit'), '', 'fa fa-plus-circle', DOL_URL_ROOT . '/custom/digiriskdolibarr/view/firepermit/firepermit_card.php?action=create');
}

print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="type" value="' . ($type ?? '') . '">';
print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';

include DOL_DOCUMENT_ROOT . '/core/tpl/massactions_pre.tpl.php';

// Build and execute select
// --------------------------------------------------------------------

$sql = 'SELECT ';
foreach ($object->fields as $key => $val) {
	$sql .= 't.' . $key . ', ';
}
// Add fields from extrafields
if ( ! empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) $sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef." . $key . ' as options_' . $key . ', ' : '');
}
// Add fields from hooks
$parameters = array();
$reshook    = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object); // Note that $action and $objectdocument may have been modified by hook
$sql       .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql        = preg_replace('/,\s*$/', '', $sql);
$sql       .= " FROM " . MAIN_DB_PREFIX . $object->table_element . " as t";

if (isset($extrafields->attributes[$object->table_element]['label']) &&
    is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . $object->table_element . "_extrafields as ef on (t.rowid = ef.fk_object)";
if ($object->ismultientitymanaged == 1) $sql                                                                                                      .= " WHERE t.entity IN (" . getEntity($object->element) . ")";
else $sql                                                                                                                                         .= " WHERE 1 = 1";
$sql                                                                                                                                              .= ' AND status != -1';


foreach ($search as $key => $val) {
		if ($key == 'status' && $search[$key] == -1) continue;
		$mode_search = (($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
	if (strpos($object->fields[$key]['type'], 'integer:') === 0) {
		if ($search[$key] == '-1') $search[$key] = '';
		$mode_search                             = 2;
	}
		if ($search[$key] != '') $sql .= natural_search($key, $search[$key], (($key == 'status') ? 2 : $mode_search));
}
	if ($search_all) $sql .= natural_search(array_keys($fieldstosearchall), $search_all);
	// Add where from extra fields
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_sql.tpl.php';
	// Add where from hooks
	$parameters = array();
	$reshook    = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object); // Note that $action and $objectdocument may have been modified by hook
	$sql       .= $hookmanager->resPrint;

	$sql .= $db->order($sortfield, $sortorder);

	// Count total nb of records
	$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);

	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total of record found is smaller than page * limit, goto and load page 0
		$page   = 0;
		$offset = 0;
	}
}
	// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
	$num = $nbtotalofrecords;
} else {
	if ($limit) $sql .= $db->plimit($limit + 1, $offset);

	$resql = $db->query($sql);
	if ( ! $resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}

	// Direct jump if only one record found
if ($num == 1 && ! empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $search_all && ! $page) {
	$obj = $db->fetch_object($resql);
	$id  = $obj->rowid;
	header("Location: " . dol_buildpath('/digiriskdolibarr/view/firepermit/firepermit_card.php', 1) . '?id=' . $id);
	exit;
}

if ($search_all) {
	foreach ($fieldstosearchall as $key => $val) $fieldstosearchall[$key] = $langs->trans($val);
	print '<div class="divsearchfieldfilter">' . $langs->trans("FilterOnInto", $search_all) . join(', ', $fieldstosearchall) . '</div>';
}

$moreforfilter = '';

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;

$arrayfields['MasterWorker']           = array('label' => 'MasterWorker', 'checked' => 1);
$arrayfields['ExtSociety']             = array('label' => 'ExtSociety', 'checked' => 1);
$arrayfields['ExtSocietyResponsible']  = array('label' => 'ExtSocietyResponsible', 'checked' => 1);
$arrayfields['ExtSocietyAttendant']    = array('label' => 'ExtSocietyAttendant', 'checked' => 1);

print_barre_liste($form->textwithpicto($title, $texthelp ?? ''), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

$selectedfields                         = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
if ($massactionbutton) $selectedfields .= $form->showCheckAddButtons('checkforselect', 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";
print '<tr class="liste_titre">';

$object->fields['Custom']['MasterWorker']            = $arrayfields['MasterWorker'] ;
$object->fields['Custom']['ExtSociety']              = $arrayfields['ExtSociety'];
$object->fields['Custom']['ExtSocietyResponsible']   = $arrayfields['ExtSocietyResponsible'];
$object->fields['Custom']['ExtSocietyAttendant']     = $arrayfields['ExtSocietyAttendant'];

foreach ($object->fields as $key => $val) {
	$cssforfield                        = (empty($val['css']) ? '' : $val['css']);
	if ($key == 'status') $cssforfield .= ($cssforfield ? ' ' : '') . 'center';
	if ( ! empty($arrayfields['t.' . $key]['checked'])) {
		print '<td class="liste_titre' . ($cssforfield ? ' ' . $cssforfield : '') . '">';

		if (isset($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) print $form->selectarray('search_' . $key, $val['arrayofkeyval'], $search[$key] ?? '', $val['notnull'], 0, 0, '', 1, 0, 0, '', 'maxwidth75');
		elseif (strpos($val['type'], 'integer:') === 0) {
			print $object->showInputField($val, $key, $search[$key] ?? '', '', '', 'search_', 'maxwidth150', 1);
		} elseif ( ! preg_match('/^(date|timestamp)/', $val['type'])) print '<input type="text" class="flat maxwidth75" name="search_' . $key . '" value="' . dol_escape_htmltag($search[$key] ?? '') . '">';
		print '</td>';
	}
	if ($key == 'Custom') {
		foreach ($val as $resource) {
			if ($resource['checked']) {
				print '<td>';
				print '</td>';
			}
		}
	}
}

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields' => $arrayfields);
$reshook    = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $objectdocument may have been modified by hook
print $hookmanager->resPrint;

// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>' . "\n";

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';

foreach ($object->fields as $key => $val) {
	$cssforfield                        = (empty($val['css']) ? '' : $val['css']);
	if ($key == 'status') $cssforfield .= ($cssforfield ? ' ' : '') . 'center';
	if ( ! empty($arrayfields['t.' . $key]['checked'])) {
		if (preg_match('/MasterWorker/', $arrayfields['t.' . $key]['label']) || preg_match('/StartDate/', $arrayfields['t.' . $key]['label']) || preg_match('/EndDate/', $arrayfields['t.' . $key]['label']) || preg_match('/ExtSociety/', $arrayfields['t.' . $key]['label']) || preg_match('/NbIntervenants/', $arrayfields['t.' . $key]['label']) || preg_match('/NbInterventions/', $arrayfields['t.' . $key]['label']) || preg_match('/Location/', $arrayfields['t.' . $key]['label'])) {
			$disablesort = 1;
		} else {
			$disablesort = 0;
		}
		print getTitleFieldOfList($arrayfields['t.' . $key]['label'], 0, $_SERVER['PHP_SELF'], 't.' . $key, '', $param, ($cssforfield ? 'class="' . $cssforfield . '"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield . ' ' : ''), $disablesort) . "\n";
	}
	if ($key == 'Custom') {
		foreach ($val as $resource) {
			if ($resource['checked']) {
				print '<td>';
				print $langs->trans($resource['label']);
				print '</td>';
			}
		}
	}
}

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_title.tpl.php';

// Hook fields
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
$reshook    = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $objectdocument may have been modified by hook
print $hookmanager->resPrint;

// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ') . "\n";
print '</tr>' . "\n";

$arrayofselected = is_array($toselect) ? $toselect : [];

// Loop on record
// --------------------------------------------------------------------

// contenu
$i          = 0;
$totalarray = ['nbfield' => 0];

while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);

	if (empty($obj)) break; // Should not happen


	// Store properties in $objectdocument
	$object->setVarsFromFetchObj($obj);

    if (isset($object->json)) {
        $json = json_decode($object->json, false, 512, JSON_UNESCAPED_UNICODE)->FirePermit;
    } else {
        $json = [];
    }

	// Show here line of result
	print '<tr class="oddeven firepermitdocument-row firepermit_row_' . $object->id . ' firepermitdocument-row-content-' . $object->id . '" id="firepermit_row_' . $object->id . '">';
	foreach ($object->fields as $key => $val) {
		$cssforfield                                 = (empty($val['css']) ? '' : $val['css']);
		if ($key == 'status') $cssforfield          .= ($cssforfield ? ' ' : '') . 'center';
		elseif ($key == 'ref') $cssforfield         .= ($cssforfield ? ' ' : '') . 'nowrap';
		elseif ($key == 'category') $cssforfield    .= ($cssforfield ? ' ' : '') . 'firepermitdocument-category';
		elseif ($key == 'description') $cssforfield .= ($cssforfield ? ' ' : '') . 'firepermitdocument-description';
		if ( ! empty($arrayfields['t.' . $key]['checked'])) {
			print '<td' . ($cssforfield ? ' class="' . $cssforfield . '"' : '') . ' style="width:2%">';
			if ($key == 'status') print $object->getLibStatut(5);
			elseif ($key == 'fk_preventionplan') {
				if ($obj->fk_preventionplan > 0) {
					$preventionplan->fetch($obj->fk_preventionplan);
					print $preventionplan->getNomUrl(1);
				}
			} elseif ($key == 'ref') {
				print '<i class="fas fa-fire-alt"></i>  ' . $object->getNomUrl();
			} elseif ($key == 'date_start') {
				print dol_print_date($object->date_start, 'dayhour', 'tzserver');	// We suppose dates without time are always gmt (storage of course + output)
			} elseif ($key == 'date_end') {
				print dol_print_date($object->date_end, 'dayhour', 'tzserver');	// We suppose dates without time are always gmt (storage of course + output)
			} else print $object->showOutputField($val, $key, $object->$key, '');
			print '</td>';
			if ( ! $i) $totalarray['nbfield']++;
			if ( ! empty($val['isameasure'])) {
				if ( ! $i) $totalarray['pos'][$totalarray['nbfield']] = 't.' . $key;
				$totalarray['val']['t.' . $key]                      += $object->$key;
			}
		}
		if ($key == 'Custom') {
			foreach ($val as $name => $resource) {
				if ($resource['checked']) {
					print '<td>';
					if ($resource['label'] == 'MasterWorker') {
						$element = $signatory->fetchSignatory('MasterWorker', $object->id, 'firepermit');
						if (is_array($element)) {
							$element = array_shift($element);
							$usertmp->fetch($element->element_id);
							print $usertmp->getNomUrl(1);
						}
					} elseif ($resource['label'] == 'ExtSociety') {
						$extSociety = $digiriskresources->fetchResourcesFromObject('ExtSociety', $object);
						if ($extSociety > 0) {
							print $extSociety->getNomUrl(1);
						}
					}
					if ($resource['label'] == 'ExtSocietyResponsible') {
						$element = $signatory->fetchSignatory('ExtSocietyResponsible', $object->id, 'firepermit');
						if (is_array($element)) {
							$element = array_shift($element);
							$contact->fetch($element->element_id);
							print $contact->getNomUrl(1);
						}
					}
					if ($resource['label'] == 'ExtSocietyAttendant') {
						$extSociety_intervenants = $signatory->fetchSignatory('ExtSocietyAttendant', $object->id, 'firepermit');
						if (is_array($extSociety_intervenants) && ! empty($extSociety_intervenants) && $extSociety_intervenants > 0) {
							foreach ($extSociety_intervenants as $element) {
								if ($element > 0) {
									$contact->fetch($element->element_id);
									print $contact->getNomUrl(1);
									print '<br>';
								}
							}
						}
					}
					print '</td>';
				}
			}
		}
	}
	// Action column
	print '<td class="nowrap center">';
	if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		$selected                                                  = 0;
		if (in_array($object->id, $arrayofselected)) $selected = 1;
		print '<input id="cb' . $object->id . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $object->id . '"' . ($selected ? ' checked="checked"' : '') . '>';
	}

	print '</td>';
	if ( ! $i) $totalarray['nbfield']++;
	print '</tr>' . "\n";
	$i++;
}
// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) { if ( ! empty($val['checked'])) $colspan++; }
	print '<tr><td colspan="' . $colspan . '" class="opacitymedium">' . $langs->trans("NoRecordFound") . '</td></tr>';
}
$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook    = $hookmanager->executeHooks('printFieldListFooter', $parameters, $risk); // Note that $action and $risk may have been modified by hook
print $hookmanager->resPrint;

print "</table>\n";
print '</div>';
print "</form>\n";

// End of page
llxFooter();
$db->close();
