<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    view/ticket/ticket_card.php
 * \ingroup digiriskdolibarr
 * \brief   Ticket card — Issue #4443.
 */

// Load Dolibarr environment — from custom/digiriskdolibarr/view/ticket/ we need 4 levels up
$res = 0;
if (!$res && file_exists('../../../../main.inc.php')) {
    $res = require '../../../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = require '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['ticket', 'companies']);

$id      = GETPOSTINT('id');
$trackId = GETPOST('track_id', 'alpha');
$action  = GETPOST('action', 'aZ09');

$object = new Ticket($db);
if ($id > 0 || !empty($trackId)) {
    $object->fetch($id, '', $trackId);
    if ($object->id > 0) {
        $object->fetch_optionals();
    }
}

// Security
if (!$user->hasRight('ticket', 'read')) {
    accessforbidden();
}

$permissionToWrite = $user->hasRight('ticket', 'write') && !$user->socid;
$url_page_current  = DOL_URL_ROOT . '/custom/digiriskdolibarr/view/ticket/ticket_card.php';

/*
 * Actions
 */

require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

// AJAX: inline save subject
if ($action === 'setsubject_ajax' && $permissionToWrite) {
    $newSubject = GETPOST('subject', 'alphanohtml');
    $object->fetch($id);
    $oldSubject = $object->subject;
    $object->subject = $newSubject;
    if ($object->update($user) > 0 && trim($newSubject) !== trim($oldSubject)) {
        // Log the change in ActionComm
        $actioncomm = new ActionComm($db);
        $actioncomm->type_code    = 'AC_OTH_AUTO';
        $actioncomm->code         = 'AC_TICKET_MODIFY';
        $actioncomm->label        = 'Sujet modifié : ' . $oldSubject . ' → ' . $newSubject;
        $actioncomm->fk_element   = $object->id;
        $actioncomm->elementtype  = 'ticket';
        $actioncomm->datep        = dol_now();
        $actioncomm->userownerid  = $user->id;
        $actioncomm->percentage   = -1;
        $actioncomm->create($user);
    }
    header('Content-Type: application/json');
    print json_encode(['success' => 1]);
    exit;
}

// AJAX: inline save project
if ($action === 'setproject_ajax' && $permissionToWrite) {
    $newProjectId = GETPOSTINT('fk_project');
    $object->fetch($id);
    $oldProjectId = (int) $object->fk_project;
    $object->fk_project = $newProjectId;
    if ($object->update($user) > 0) {
        // Log the change in ActionComm
        $actioncomm = new ActionComm($db);
        $actioncomm->type_code   = 'AC_OTH_AUTO';
        $actioncomm->code        = 'AC_TICKET_MODIFY';
        $oldLabel                = ($oldProjectId > 0) ? 'projet #' . $oldProjectId : 'aucun';
        $newLabel                = ($newProjectId > 0) ? 'projet #' . $newProjectId : 'aucun';
        $actioncomm->label       = 'Projet modifié : ' . $oldLabel . ' → ' . $newLabel;
        $actioncomm->fk_element  = $object->id;
        $actioncomm->elementtype = 'ticket';
        $actioncomm->datep       = dol_now();
        $actioncomm->userownerid = $user->id;
        $actioncomm->percentage  = -1;
        $actioncomm->create($user);
        // Fetch new project name for response
        $projectLabel = '';
        if ($newProjectId > 0) {
            $proj = new Project($db);
            $proj->fetch($newProjectId);
            $projectLabel = $proj->ref . ' - ' . $proj->title;
        }
    }
    header('Content-Type: application/json');
    print json_encode(['success' => 1, 'label' => $projectLabel ?? '']);
    exit;
}

// Action: set subject (native form)
if ($action === 'setsubject' && $permissionToWrite) {
    if (GETPOST('cancel', 'alpha')) {
        $action = '';
    } else {
        $object->fetch($id);
        $oldSubject = $object->subject;
        $object->subject = GETPOST('subject', 'alphanohtml');
        if ($object->update($user) > 0 && trim($object->subject) !== trim($oldSubject)) {
            $actioncomm = new ActionComm($db);
            $actioncomm->type_code   = 'AC_OTH_AUTO';
            $actioncomm->code        = 'AC_TICKET_MODIFY';
            $actioncomm->label       = 'Sujet modifié : ' . $oldSubject . ' → ' . $object->subject;
            $actioncomm->fk_element  = $object->id;
            $actioncomm->elementtype = 'ticket';
            $actioncomm->datep       = dol_now();
            $actioncomm->userownerid = $user->id;
            $actioncomm->percentage  = -1;
            $actioncomm->create($user);
        }
        header('Location: ' . $url_page_current . '?id=' . $object->id);
        exit;
    }
}

// Action: classify (set project — native form)
if ($action === 'classin' && $permissionToWrite) {
    $object->fetch($id);
    $object->setProject(GETPOSTINT('projectid'));
    header('Location: ' . $url_page_current . '?id=' . $object->id);
    exit;
}

if ($action === 'setmessage' && $permissionToWrite) {
    $object->fetch($id);
    $object->message = GETPOST('message', 'restricthtml');
    $object->update($user);
    header('Location: ' . $url_page_current . '?id=' . $object->id);
    exit;
}

/*
 * View
 */

if ($object->id <= 0) {
    llxHeader('', 'Ticket', '', '', 0, 0, '', '', '', 'mod-ticket page-card');
    print '<div class="error">Ticket introuvable.</div>';
    llxFooter();
    $db->close();
    exit;
}

// Load thirdparty if linked
if ($object->socid > 0) {
    $object->fetch_thirdparty();
}

$form = new Form($db);

llxHeader('', 'Ticket ' . $object->ref, '', '', 0, 0, '', '', '', 'mod-ticket page-card');

// ---- Build morehtmlref (native Dolibarr style) ----
$morehtmlref = '<div class="refidno">';

// Subject (editable inline, native style)
$morehtmlref .= '<a class="editfielda" href="' . $url_page_current . '?action=editsubject&token=' . newToken() . '&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetTitle'), 0) . '</a> ';
if ($action != 'editsubject') {
    $morehtmlref .= dolPrintLabel($object->subject);
} else {
    $morehtmlref .= '<form method="post" action="' . dol_escape_htmltag($url_page_current) . '">';
    $morehtmlref .= '<input type="hidden" name="action" value="setsubject">';
    $morehtmlref .= '<input type="hidden" name="token" value="' . newToken() . '">';
    $morehtmlref .= '<input type="hidden" name="id" value="' . $object->id . '">';
    $morehtmlref .= '<input type="text" class="minwidth300" name="subject" value="' . dol_escape_htmltag($object->subject) . '" autofocus>';
    $morehtmlref .= '<input type="submit" class="smallpaddingimp button valignmiddle" name="modify" value="' . $langs->trans("Modify") . '">';
    $morehtmlref .= '<input type="submit" class="smallpaddingimp button button-cancel valignmiddle" name="cancel" value="' . $langs->trans("Cancel") . '">';
    $morehtmlref .= '</form>';
}

// Author
if ($object->fk_user_create > 0) {
    $morehtmlref .= '<br>';
    $fuser = new User($db);
    $fuser->fetch($object->fk_user_create);
    $morehtmlref .= $fuser->getNomUrl(-1);
}

// Thirdparty
if (isModEnabled("societe")) {
    $morehtmlref .= '<br>';
    $morehtmlref .= img_picto($langs->trans("ThirdParty"), 'company', 'class="pictofixedwidth"');
    if ($object->socid > 0 && is_object($object->thirdparty)) {
        $morehtmlref .= $object->thirdparty->getNomUrl(1);
    } else {
        $morehtmlref .= '<span class="opacitymedium">' . $langs->trans('NoThirdParty') . '</span>';
    }
}

// Project
if (isModEnabled('project')) {
    $langs->load("projects");
    $morehtmlref .= '<br>';
    $object->fetchProject();
    $morehtmlref .= img_picto($langs->trans("Project"), 'project' . ((is_object($object->project) && $object->project->public) ? 'pub' : ''), 'class="pictofixedwidth"');
    if ($permissionToWrite) {
        if ($action != 'classify') {
            $morehtmlref .= '<a class="editfielda" href="' . $url_page_current . '?action=classify&token=' . newToken() . '&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> ';
        }
        $morehtmlref .= $form->form_project($url_page_current . '?id=' . $object->id, $object->socid, (string) $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
    } else {
        if (!empty($object->fk_project) && is_object($object->project)) {
            $morehtmlref .= $object->project->getNomUrl(1);
            if ($object->project->title) {
                $morehtmlref .= '<span class="opacitymedium"> - ' . dol_escape_htmltag($object->project->title) . '</span>';
            }
        }
    }
}

$morehtmlref .= '</div>';

$linkback = '<a href="' . DOL_URL_ROOT . '/ticket/list.php?restore_lastsearch_values=1"><strong>' . $langs->trans("BackToList") . '</strong></a> ';

dol_banner_tab($object, 'ref', $linkback, ($user->socid ? 0 : 1), 'ref', 'ref', $morehtmlref);
?>


<div class="fichecenter">
<div class="fichehalfleft">

<?php
// ---- Message initial (native Dolibarr table layout) ----
print '<form method="POST" action="' . dol_escape_htmltag($url_page_current) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="setmessage">';
print '<input type="hidden" name="id" value="' . (int) $object->id . '">';

print '<table class="border centpercent tableforfield">';
print '<tbody>';

// Header row
print '<tr class="liste_titre trforfield">';
print '<td class="nowrap titlefield">' . $langs->trans('TicketInitialMessage') . '</td>';
print '<td>';
if ($permissionToWrite && $action === 'editmessage') {
    print '<input type="submit" class="button button-edit smallpaddingimp" value="' . $langs->trans('Modify') . '"> ';
    print '<input type="submit" class="button button-cancel smallpaddingimp" name="cancel" value="' . $langs->trans('Cancel') . '">';
} elseif ($permissionToWrite) {
    print '<a href="' . dol_escape_htmltag($url_page_current . '?id=' . $object->id . '&action=editmessage&token=' . newToken()) . '">';
    print img_edit($langs->trans('Modify'), 0);
    print '</a>';
}
print '</td>';
print '</tr>';

// Content row
print '<tr><td colspan="2">';
if ($action === 'editmessage' && $permissionToWrite) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
    $doleditor = new DolEditor(
        'message',
        $object->message,
        '',
        120,
        'dolibarr_details',
        'In',
        false,
        true,
        getDolGlobalString('FCKEDITOR_ENABLE_TICKET'),
        ROWS_9,
        '95%'
    );
    $doleditor->Create();
} else {
    if (!empty($object->message)) {
        print dol_htmlentitiesbr($object->message);
    } else {
        print '<span class="opacitymedium">' . $langs->trans('None') . '</span>';
    }
}
print '</td></tr>';

print '</tbody>';
print '</table>';
print '</form>';
?>

</div><!-- fichehalfleft -->
</div><!-- fichecenter -->

<?php
llxFooter();
$db->close();
