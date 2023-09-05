<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/preventionplan.class.php
 * \ingroup     digiriskdolibarr
 * \brief       This file is a class file for PreventionPlan
 */

require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

require_once __DIR__ . '/openinghours.class.php';
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for PreventionPlan
 */
class PreventionPlan extends SaturneObject
{
	/**
	 * @var string Module name.
	 */
	public $module = 'digiriskdolibarr';

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error string
	 * @see        $errors
	 */
	public $error;

	/**
	 * @var string[] Array of error strings
	 */
	public $errors = array();

	/**
	 * @var array Result array.
	 */
	public $result = array();

	/**
	 * @var int The object identifier
	 */
	public $id;

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'preventionplan';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element = 'digiriskdolibarr_preventionplan';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element_line = 'digiriskdolibarr_preventionplandet';

	/**
	 * @var int  Does this object support multicompany module ?
	 * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public int $isextrafieldmanaged = 1;

	/**
	 * @var string String with name of icon for digiriskelement. Must be the part after the 'object_' into object_digiriskelement.png
	 */
	public $picto = 'preventionplandocument@digiriskdolibarr';

	/**
	 * @var string Label status of const.
	 */
	public $labelStatus;

	/**
	 * @var string Label status short of const.
	 */
	public $labelStatusShort;

	/**
	 * @var array Context element object
	 */
	public $context = array();

	/**
	 * @var PreventionPlanLine[]     Array of subtable lines
	 */
	public $lines = array();

	const STATUS_DRAFT = 1;
	const STATUS_VALIDATED = 2;
	const STATUS_LOCKED = 3;
	const STATUS_ARCHIVED = 4;

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid'                => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => "Id"),
		'ref'                  => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'noteditable' => '1', 'index' => 1, 'searchall' => 1, 'showoncombobox' => '1', 'comment' => "Reference of object"),
		'ref_ext'              => array('type' => 'varchar(128)', 'label' => 'RefExt', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 0,),
		'entity'               => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 0,),
		'date_creation'        => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 0,),
		'tms'                  => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 0,),
		'status'               => array('type' => 'smallint', 'label' => 'Status', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 0,),
		'label'                => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth200', 'help' => "Help text", 'showoncombobox' => '1',),
		'date_start'           => array('type' => 'datetime', 'label' => 'StartDate', 'enabled' => '1', 'position' => 100, 'notnull' => -1, 'visible' => 1,),
		'date_end'             => array('type' => 'datetime', 'label' => 'EndDate', 'enabled' => '1', 'position' => 130, 'notnull' => -1, 'visible' => 1,),
		'prior_visit_bool'     => array('type' => 'boolean', 'label' => 'PriorVisit', 'enabled' => '1', 'position' => 140, 'notnull' => -1, 'visible' => -1,),
		'prior_visit_text'     => array('type' => 'text', 'label' => 'PriorVisitText', 'enabled' => '1', 'position' => 150, 'notnull' => -1, 'visible' => -1,),
		'prior_visit_date'     => array('type' => 'datetime', 'label' => 'PriorVisitDate', 'enabled' => '1', 'position' => 200, 'notnull' => -1, 'visible' => -1,),
		'cssct_intervention'   => array('type' => 'boolean', 'label' => 'CSSCTIntervention', 'enabled' => '1', 'position' => 160, 'notnull' => -1, 'visible' => -1,),
		'fk_project'           => array('type' => 'integer:Project:projet/class/project.class.php', 'label' => 'Project', 'enabled' => '1', 'position' => 170, 'notnull' => 1, 'visible' => 1,),
		'fk_user_creat'        => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 180, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid',),
		'fk_user_modif'        => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => '1', 'position' => 190, 'notnull' => -1, 'visible' => 0,),
		'last_email_sent_date' => array('type' => 'datetime', 'label' => 'LastEmailSentDate', 'enabled' => '1', 'position' => 200, 'notnull' => -1, 'visible' => -2,),
	);

	public $rowid;
	public $ref;
	public $ref_ext;
	public $entity;
	public $date_creation;
	public $tms;
	public $status;
	public $label;
	public $date_start;
	public $date_end;
	public $prior_visit_bool;
	public $prior_visit_text;
	public $prior_visit_date;
	public $cssct_intervention;
	public $fk_project;
	public $fk_user_creat;
	public $fk_user_modif;
	public $last_email_sent_date;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		parent::__construct($db, $this->module, $this->element);
	}


	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             <0 if KO, Id of created object if OK
	 */
	public function create(User $user, bool $notrigger = false): int
	{
		global $conf;

		$this->element = $this->element . '@digiriskdolibarr';

		return $this->createCommon($user, $notrigger || !$conf->global->DIGIRISKDOLIBARR_MAIN_AGENDA_ACTIONAUTO_PREVENTIONPLAN_CREATE);
	}

	/**
	 * Clone an object into another one
	 *
	 * @param User $user User that creates
	 * @param int $fromid Id of object to clone
	 * @param $options
	 * @return    mixed                New object created, <0 if KO
	 * @throws Exception
	 */
	public function createFromClone(User $user, $fromid, $options)
	{
		global $conf, $langs;
		$error = 0;

		$signatory         = new PreventionPlanSignature($this->db);
		$digiriskresources = new DigiriskResources($this->db);
		$openinghours      = new Openinghours($this->db);
		$preventionplandet = new PreventionPlanLine($this->db);

		$numberingModules = [
			$this->element              => $conf->global->DIGIRISKDOLIBARR_PREVENTIONPLAN_ADDON,
			$preventionplandet->element => $conf->global->DIGIRISKDOLIBARR_PREVENTIONPLANDET_ADDON
		];

		list($refPreventionPlanDetMod, $refPreventionPlanMod) = saturne_require_objects_mod($numberingModules, $moduleNameLowerCase);

		dol_syslog(__METHOD__, LOG_DEBUG);

		$object = new self($this->db);

		$this->db->begin();

		// Load source object
		$result = $object->fetchCommon($fromid);
		if ($result > 0 && ! empty($object->table_element_line)) {
			$object->fetchLines();
		}

		// Load openinghours form source object
		$morewhere  = ' AND element_id = ' . $object->id;
		$morewhere .= ' AND element_type = ' . "'" . $object->element . "'";
		$morewhere .= ' AND status = 1';

		$openinghours->fetch(0, '', $morewhere);

		// Load signatory and ressources form source object
		$signatories = $signatory->fetchSignatory("", $fromid, 'preventionplan');
		$resources   = $digiriskresources->fetchResourcesFromObject('', $object);

		if ( ! empty($signatories) && $signatories > 0) {
			foreach ($signatories as $arrayRole) {
				foreach ($arrayRole as $signatoryRole) {
					$signatoriesID[$signatoryRole->role] = $signatoryRole->id;
					if ($signatoryRole->role == 'PP_EXT_SOCIETY_INTERVENANTS') {
						$extintervenant_ids[] = $signatoryRole->id;
					}
				}
			}
		}

		// Reset some properties
		unset($object->id);
		unset($object->fk_user_creat);
		unset($object->import_key);

		// Clear fields
		if (property_exists($object, 'ref')) {
			$object->ref = $refPreventionPlanMod->getNextValue($object);
		}
		if (property_exists($object, 'label')) {
			$object->label = $options['clone_label'];
		}
		if (property_exists($object, 'ref_ext')) {
			$object->ref_ext = 'digirisk_' . $object->ref;
		}
		if (property_exists($object, 'date_creation')) {
			$object->date_creation = dol_now();
		}
		if (property_exists($object, 'status')) {
			$object->status = 1;
		}

		// Create clone
		$object->context['createfromclone'] = 'createfromclone';
		$preventionplanid                   = $object->create($user);

		if ($preventionplanid > 0) {
			$digiriskresources->digirisk_dolibarr_set_resources($this->db, $user->id, 'PP_EXT_SOCIETY', 'societe', array(array_shift($resources['PP_EXT_SOCIETY'])->id), $conf->entity, 'preventionplan', $preventionplanid, 1);
			$digiriskresources->digirisk_dolibarr_set_resources($this->db, $user->id, 'PP_LABOUR_INSPECTOR', 'societe', array(array_shift($resources['PP_LABOUR_INSPECTOR'])->id), $conf->entity, 'preventionplan', $preventionplanid, 1);
			$digiriskresources->digirisk_dolibarr_set_resources($this->db, $user->id, 'PP_LABOUR_INSPECTOR_ASSIGNED', 'socpeople', array(array_shift($resources['PP_LABOUR_INSPECTOR_ASSIGNED'])->id), $conf->entity, 'preventionplan', $preventionplanid, 1);
			if (!empty($signatoriesID)) {
				$signatory->createFromClone($user, $signatoriesID['PP_MAITRE_OEUVRE'], $preventionplanid);
				$signatory->createFromClone($user, $signatoriesID['PP_EXT_SOCIETY_RESPONSIBLE'], $preventionplanid);
			}

			if ( ! empty($options['schedule'])) {
				if ( ! empty($openinghours)) {
					$openinghours->element_id = $preventionplanid;
					$openinghours->create($user);
				}
			}

			if ( ! empty($options['attendants'])) {
				if ( ! empty($extintervenant_ids) && $extintervenant_ids > 0) {
					foreach ($extintervenant_ids as $extintervenant_id) {
						$signatory->createFromClone($user, $extintervenant_id, $preventionplanid);
					}
				}
			}

			if ( ! empty($options['preventionplan_risk'])) {
				$num = (!empty($object->lines) ? count($object->lines) : 0);
				for ($i = 0; $i < $num; $i++) {
					$line                    = $object->lines[$i];
					if (property_exists($line, 'ref')) {
						$line->ref = $refPreventionPlanDetMod->getNextValue($line);
					}
					$line->category          = empty($line->category) ? 0 : $line->category;
					$line->fk_preventionplan = $preventionplanid;

					$result = $line->insert($user, 1);
					if ($result < 0) {
						$this->error = $this->db->lasterror();
						$this->db->rollback();
						return -1;
					}
				}
			}
		} else {
			$error++;
			$this->error  = $object->error;
			$this->errors = $object->errors;
		}

		unset($object->context['createfromclone']);

		// End
		if ( ! $error) {
			$this->db->commit();
			return $preventionplanid;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * 	Set in progress status
	 *
	 * 	@param User $user Object user that modify
	 * 	@param int $notrigger 1=Does not execute triggers, 0=Execute triggers
	 * 	@return    int                        <0 if KO, >0 if OK
	 * 	@throws Exception
	 */
	public function setInProgress($user, $notrigger = 0)
	{
		global $conf;

		$signatory = new PreventionPlanSignature($this->db);
		$signatory->deleteSignatoriesSignatures($this->id, 'preventionplan');
		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'PREVENTIONPLAN_INPROGRESS');
	}
	/**
	 * 	Set pending signature status
	 *
	 *	@param	User	$user			Object user that modify
	 *  @param	int		$notrigger		1=Does not execute triggers, 0=Execute triggers
	 *	@return	int						<0 if KO, >0 if OK
	 */
	public function setPendingSignature($user, $notrigger = 0)
	{
		return $this->setStatusCommon($user, self::STATUS_VALIDATED, $notrigger, 'PREVENTIONPLAN_PENDINGSIGNATURE');
	}

	/**
	 *  Return the status
	 *
	 *  @param	int		$status        Id status
	 *  @param  int		$mode          0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 			       Label of status
	 */
	public function LibStatut($status, $mode = 0)
	{
		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			$langs->load("digiriskdolibarr@digiriskdolibarr");

			$this->labelStatus[self::STATUS_IN_PROGRESS]       = $langs->trans('InProgress');
			$this->labelStatus[self::STATUS_PENDING_SIGNATURE] = $langs->trans('ValidatePendingSignature');
			$this->labelStatus[self::STATUS_LOCKED]            = $langs->trans('Locked');
			$this->labelStatus[self::STATUS_ARCHIVED]          = $langs->trans('Archived');
		}

		$statusType                                                = 'status' . $status;
		if ($status == self::STATUS_VALIDATED) $statusType = 'status3';
		if ($status == self::STATUS_LOCKED) $statusType            = 'status8';
		if ($status == self::STATUS_ARCHIVED) $statusType          = 'status8';

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
	}

	/**
	 *  Output html form to select a third party.
	 *  Note, you must use the select_company to get the component to select a third party. This function must only be called by select_company.
	 *
	 * @param string|int $selected Preselected type
	 * @param string $htmlname Name of field in form
	 * @param string $filter Optional filters criteras (example: 's.rowid <> x', 's.client in (1,3)')
	 * @param string $showempty Add an empty field (Can be '1' or text to use on empty line like 'SelectThirdParty')
	 * @param int $forcecombo Force to use standard HTML select component without beautification
	 * @param array $events Event options. Example: array(array('method'=>'getContacts', 'url'=>dol_buildpath('/core/ajax/contacts.php',1), 'htmlname'=>'contactid', 'params'=>array('add-customer-contact'=>'disabled')))
	 * @param int $outputmode 0=HTML select string, 1=Array
	 * @param int $limit Limit number of answers
	 * @param string $morecss Add more css styles to the SELECT component
	 * @param string $moreparam Add more parameters onto the select tag. For example 'style="width: 95%"' to avoid select2 component to go over parent container
	 * @param bool $multiple add [] in the name of element and add 'multiple' attribut
	 * @return array|string
	 * @throws Exception
	 */
	public function select_preventionplan_list($selected = '', $htmlname = 'fk_preventionplan', $filter = '', $showempty = '1', $forcecombo = 0, $events = array(), $outputmode = 0, $limit = 0, $morecss = 'minwidth100', $moreparam = '', $multiple = false)
	{
		global $langs;

		$out      = '';
		$num      = 0;
		$outarray = array();

		$selected = array($selected);

		// Clean $filter that may contains sql conditions so sql code
		if (function_exists('testSqlAndScriptInject')) {
			if (testSqlAndScriptInject($filter, 3) > 0) {
				$filter = '';
			}
		}
		// On recherche les societes
		$sql  = 'SELECT ';
		$sql .= $this->getFieldList();
		$sql .= " FROM " . MAIN_DB_PREFIX . "digiriskdolibarr_preventionplan as s";

		$sql              .= " WHERE s.entity IN (" . getEntity($this->table_element) . ")";
		if ($filter) $sql .= " AND (" . $filter . ")";
		$sql              .= " AND status != 0";
		$sql              .= $this->db->order("rowid", "ASC");
		$sql              .= $this->db->plimit($limit, 0);

		// Build output string
		dol_syslog(get_class($this) . "::select_preventionplan_list", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ( ! $forcecombo) {
				include_once DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php';
				$out .= ajax_combobox($htmlname, $events, 0);
			}

			// Construct $out and $outarray
			$out .= '<select id="' . $htmlname . '" class="flat' . ($morecss ? ' ' . $morecss : '') . '"' . ($moreparam ? ' ' . $moreparam : '') . ' name="' . $htmlname . ($multiple ? '[]' : '') . '" ' . ($multiple ? 'multiple' : '') . '>' . "\n";

			$textifempty          = (($showempty && ! is_numeric($showempty)) ? $langs->trans($showempty) : '');
			if ($showempty) $out .= '<option value="-1">' . $textifempty . '</option>' . "\n";

			$num = $this->db->num_rows($resql);
			$i   = 0;
			if ($num) {
				while ($i < $num) {
					$obj   = $this->db->fetch_object($resql);
					$label = $obj->ref;

					if (empty($outputmode)) {
						if (in_array($obj->rowid, $selected)) {
							$out .= '<option value="' . $obj->rowid . '" selected>' . $label . '</option>';
						} else {
							$out .= '<option value="' . $obj->rowid . '">' . $label . '</option>';
						}
					} else {
						array_push($outarray, array('key' => $obj->rowid, 'value' => $label, 'label' => $label));
					}

					$i++;
					if (($i % 10) == 0) $out .= "\n";
				}
			}
			$out .= '</select>' . "\n";
		} else {
			dol_print_error($this->db);
		}

		$this->result = array('nbofpreventionplan' => $num);

		if ($outputmode) return $outarray;
		return $out;
	}
}

/**
 *	Class to manage invoice lines.
 *  Saved into database table llx_preventionplandet
 */
class PreventionPlanLine extends SaturneObject
{
	/**
	 * @var string Module name.
	 */
	public $module = 'digiriskdolibarr';

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error string
	 */
	public $error;

	/**
	 * @var int The object identifier
	 */
	public $id;

	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'preventionplandet';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'digiriskdolibarr_preventionplandet';

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid'             => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => "Id"),
		'ref'               => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'noteditable' => '1', 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => '1', 'comment' => "Reference of object"),
		'ref_ext'           => array('type' => 'varchar(128)', 'label' => 'RefExt', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 0,),
		'entity'            => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 0,),
		'date_creation'     => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 40, 'notnull' => 1, 'visible' => 0,),
		'tms'               => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => 0,),
		'category'          => array('type' => 'integer', 'label' => 'INRSRisk', 'enabled' => '1', 'position' => 60, 'notnull' => -1, 'visible' => -1,),
		'description'       => array('type' => 'text', 'label' => 'Description', 'enabled' => '1', 'position' => 70, 'notnull' => -1, 'visible' => -1,),
		'prevention_method' => array('type' => 'text', 'label' => 'PreventionMethod', 'enabled' => '1', 'position' => 80, 'notnull' => -1, 'visible' => -1,),
		'fk_preventionplan' => array('type' => 'integer', 'label' => 'FkPreventionPlan', 'enabled' => '1', 'position' => 90, 'notnull' => 1, 'visible' => 0,),
		'fk_element'        => array('type' => 'integer', 'label' => 'FkElement', 'enabled' => '1', 'position' => 100, 'notnull' => 1, 'visible' => 0,),
	);

	public $rowid;
	public $ref;
	public $ref_ext;
	public $date_creation;
	public $tms;
	public $category;
	public $description;
	public $prevention_method;
	public $fk_preventionplan;
	public $fk_element;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		parent::__construct($db, $this->module, $this->element);
	}

	/**
	 *    Insert line into database
	 *
	 * @param User $user
	 * @param bool $notrigger 1 no triggers
	 * @return        int                                         <0 if KO, >0 if OK
	 * @throws Exception
	 */
	public function insert(User $user, $notrigger = false)
	{
		global $db, $user, $conf;

		// Clean parameters
		$this->description = trim($this->description);

		$db->begin();
		$now = dol_now();

		// Insertion dans base de la ligne
		$sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'digiriskdolibarr_preventionplandet';
		$sql .= ' (ref, entity, date_creation, description, category, prevention_method, fk_preventionplan, fk_element';
		$sql .= ')';
		$sql .= " VALUES (";
		$sql .= "'" . $db->escape($this->ref) . "'" . ", ";
		$sql .= $this->entity . ", ";
		$sql .= "'" . $db->escape($db->idate($now)) . "'" . ", ";
		$sql .= "'" . $db->escape($this->description) . "'" . ", ";
		$sql .= $this->category . ", ";
		$sql .= "'" . $db->escape($this->prevention_method) . "'" . ", ";
		$sql .= $this->fk_preventionplan . ", ";
		$sql .= $this->fk_element ;

		$sql .= ')';

		dol_syslog(get_class($this) . "::insert", LOG_DEBUG);
		$resql = $db->query($sql);

		if ($resql) {
			$this->id    = $db->last_insert_id(MAIN_DB_PREFIX . 'preventionplandet');
			$this->rowid = $this->id; // For backward compatibility

			$db->commit();
			// Triggers
			if ( ! $notrigger) {
				// Call triggers
				if (!empty($conf->global->DIGIRISKDOLIBARR_MAIN_AGENDA_ACTIONAUTO_PREVENTIONPLANLINE_CREATE)) $this->call_trigger(strtoupper(get_class($this)) . '_CREATE', $user);
				// End call triggers
			}
			return $this->id;
		} else {
			$this->error = $db->lasterror();
			$db->rollback();
			return -2;
		}
	}
}
