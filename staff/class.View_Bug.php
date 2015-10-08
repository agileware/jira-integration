<?php
/**
 * ###############################################
 *
 * SWIFT Framework
 * _______________________________________________
 *
 * @author         Varun Shoor
 *
 * @package        SWIFT
 * @copyright      Copyright (c) 2001-2015, Kayako
 * @license        http://www.kayako.com/license
 * @link           http://www.kayako.com
 *
 * ###############################################
 */

/**
 * The Bug View
 * Handles all the bug(s) logged per ticet
 *
 * @author Abhinav Kumar
 */
class View_Bug extends SWIFT_View
{
	/**
	 * The constructor
	 *
	 * @return \View_Bug 'TRUE' on success and 'FALSE' otherwise
	 */
	public function __construct()
	{
		parent::__construct();

		$_SWIFT = SWIFT::GetInstance();

		$_SWIFT->Language->Load('staff_ticketsmanage');
		$_SWIFT->Language->Load('tickets_auditlogs');

		return true;
	}

	/**
	 * The destructor
	 *
	 * @return bool 'TRUE' on success and 'FALSE' otherwise
	 */
	public function __destruct()
	{
		return parent::__destruct();
	}

	/**
	 * Renders the 'Export to JIRA' form
	 *
	 * @param int $_ticketID The current ticket id
	 *
	 * @return boolean 'TRUE' on success and 'FALSE' otherwise
	 * @throws SWIFT_Exception if class is not loaded or $_ticketID is not provided
	 */
	public function RenderExportForm($_ticketID)
	{
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);
		}

		if (empty($_ticketID)) {
			throw new SWIFT_Exception('Ticket ID' . $this->Language->Get('jira_noempty'));
		}

		$_ticketID                   = (int) $_ticketID;
		$_projectsOptionsContainer   = array();
		$_issueTypesOptionsContainer = array();

		$_SWIFT_TicketObject = SWIFT_Ticket::GetObjectOnID($_ticketID);

		$_ticketPostContainer = $_SWIFT_TicketObject->GetTicketPosts();

		$_ticketPosts        = array();
		$_issueTypeContainer = array();

		foreach ($_ticketPostContainer as $_TicketPost) {
			$_creater = $_TicketPost->GetProperty('fullname');

			if ($_TicketPost->GetProperty('creator') == SWIFT_Ticket::CREATOR_STAFF) {
				$_staffDataStore = $_SWIFT->Staff->GetDataStore();
				$_creater        = $_staffDataStore['grouptitle'];
			} else if ($_TicketPost->GetProperty('creator') == SWIFT_Ticket::CREATOR_USER) {
				$_creater .= ' (' . $this->Language->Get('jira_user') . ')';
			}

			$_postedOn = SWIFT_Date::Get(SWIFT_Date::TYPE_DATETIME, $_TicketPost->GetProperty('dateline'));

			$_ticketPosts[] = $_creater . ' - ' . $_postedOn . PHP_EOL . strip_tags(trim($_TicketPost->GetDisplayContents())) . PHP_EOL;
		}

		$_ticketPosts = implode(PHP_EOL, $_ticketPosts);

		$_ticketPosts = '[' . $_SWIFT_TicketObject->GetProperty('ticketmaskid') . ']: ' . $_SWIFT_TicketObject->GetProperty('subject')
			. PHP_EOL
			. '=================================================='
			. PHP_EOL
			. PHP_EOL
			. $_ticketPosts;

		$this->UserInterface->Start(get_class($this), '/JIRA/Bug/ProcessIssueForm', SWIFT_UserInterface::MODE_INSERT, true);

		$_buttonText = '<input type="button" name="submitbutton" id="%formid%_submit" class="rebuttonblue" onclick="javascript: $(\'#%formid%\').submit();PreventDoubleClicking(this);" value="' . $this->Language->Get('jira_save') . '"/>
					<input type="button" name="submitbutton" id="%formid%_cancel" class="rebuttonred" onclick="javascript: $(\'.ui-icon-closethick\').click();" value="' . $this->Language->Get('jira_cancel') . '" onfocus="blur();" />';

		$this->UserInterface->OverrideButtonText($_buttonText);

		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabgeneral'), SWIFT::Get('swiftpath') . SWIFT_APPSDIRECTORY . '/jira/resources/postbugtojira_b.gif', 'general', true, false);

		$this->Load->Library('JIRA:JIRABridge', false, false, 'jira');

		$_JIRABridge = SWIFT_JIRABridge::GetInstance();

		if (!$_JIRABridge || !$_JIRABridge instanceof SWIFT_JIRABridge || !$_JIRABridge->GetIsClassLoaded()) {
			throw new SWIFT_Exception('JIRABridge ' . SWIFT_CLASSNOTLOADED);
		}

		$_defaultReporter = $_SWIFT->Settings->Get('bj_username');

		$_projectsContainer = $_JIRABridge->GetProjects();

		if ($_projectsContainer == false) {
			SWIFT::Notify(SWIFT::NOTIFICATION_ERROR, $_JIRABridge->GetErrorMessage());
		} else if (_is_array($_projectsContainer)) {
			foreach ($_projectsContainer as $_project) {
				$_projectsOptionsContainer[] = array(
					'title' => $_project['title'],
					'value' => $_project['value'],
				);
			}
		}

		$_prioritiesContainer = $_JIRABridge->GetPriorities();

		$_projects = $_JIRABridge->GetProjects();

		if (!is_array($_projects)) {
			echo 'Project' . $this->Language->Get('jira_noempty');

			return false;
		} else if (count($_projects)) {
		}

		foreach ($_projects as $_project) {
			$_issueTypeContainer = $_JIRABridge->GetIssueTypesByProject($_project['value']);
		}

		if (_is_array($_issueTypeContainer)) {
			foreach ($_issueTypeContainer as $_IssueType) {
				// Ignore sub-task issue type as we currently dont support it
				if (strtolower($_IssueType->name) == 'sub-task') {
					continue;
				}

				$_issueTypesOption = array(
					'title' => $_IssueType->name,
					'value' => $_IssueType->id
				);

				$_issueTypesOptionsContainer[] = $_issueTypesOption;
			}
		} else {
			if ($_JIRABridge && $_JIRABridge instanceof SWIFT_JIRABridge && $_JIRABridge->GetIsClassLoaded()) {
				$_JIRABridge->SetErrorMessage($this->Language->Get('jira_issuetypenotfound'));
			}
		}

		//Add form fields
		$_GeneralTabObject->Hidden('ticketId', $_ticketID);
		$_GeneralTabObject->Text('summary', $this->Language->Get('jira_summary'), $this->Language->Get('jira_summary_desc'), $_SWIFT_TicketObject->GetProperty('subject'), 'text', 60);
		$_GeneralTabObject->Select('project', $this->Language->Get('jira_project'), $this->Language->Get('jira_project_desc'), $_projectsContainer);
		$_GeneralTabObject->Select('issueType', $this->Language->Get('jira_issuetype'), $this->Language->Get('jira_issuetype_desc'), $_issueTypesOptionsContainer);
		$_GeneralTabObject->Select('priority', $this->Language->Get('jira_priority'), $this->Language->Get('jira_priority_desc'), $_prioritiesContainer);
		$_GeneralTabObject->Hidden('reporter', $_defaultReporter);
		$_GeneralTabObject->Title('<label for="description">' . $this->Language->Get('jira_description') . '</label><br/><span class="tabledescription">' . $this->Language->Get('jira_sensitive') . '</span>');
		$_GeneralTabObject->TextArea('description', '', $this->Language->Get('jira_description_desc'), $_ticketPosts, 50, 16);

		//Add Hidden fields - proves handy for Loading the view after update
		$this->UserInterface->Hidden('jira_ticketid', $_SWIFT_TicketObject->GetTicketID());
		$this->UserInterface->Hidden('jira_listtype', 'inbox');
		$this->UserInterface->Hidden('jira_departmentid', $_SWIFT_TicketObject->GetProperty('departmentid'));
		$this->UserInterface->Hidden('jira_ticketstatusid', $_SWIFT_TicketObject->GetProperty('ticketstatusid'));
		$this->UserInterface->Hidden('jira_tickettypeid', $_SWIFT_TicketObject->GetProperty('tickettypeid'));

		$this->UserInterface->End();

		return true;
	}

	/**
	 * Renders Link Issue Form
	 *
	 * @param int $_ticketID The current ticket id
	 *
	 * @return boolean 'TRUE' on success and 'FALSE' otherwise
	 * @throws SWIFT_Exception if class is not loaded or $_ticketID is not provided
	 */
	public function RenderLinkIssueForm($_ticketID)
	{
		$_SWIFT = SWIFT::GetInstance();

		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(SWIFT_CLASSNOTLOADED);
		}

		if (empty($_ticketID)) {
			throw new SWIFT_Exception('Ticket ID' . $this->Language->Get('jira_noempty'));
		}

		$_ticketID = (int) $_ticketID;

		$this->Load->Library('Ticket:Ticket', false, false, APP_TICKETS);

		$_SWIFT_TicketObject = SWIFT_Ticket::GetObjectOnID($_ticketID);

		$_ticketPostContainer = $_SWIFT_TicketObject->GetTicketPosts();

		$_ticketPosts = array();

		foreach ($_ticketPostContainer as $_TicketPost) {
			$_creater = $_TicketPost->GetProperty('fullname');

			if ($_TicketPost->GetProperty('creator') == SWIFT_Ticket::CREATOR_STAFF) {
				$_staffDataStore = $_SWIFT->Staff->GetDataStore();
				$_creater        = $_staffDataStore['grouptitle'];
			} else if ($_TicketPost->GetProperty('creator') == SWIFT_Ticket::CREATOR_USER) {
				$_creater .= ' (' . $this->Language->Get('jira_user') . ')';
			}

			$_postedOn = SWIFT_Date::Get(SWIFT_Date::TYPE_DATETIME, $_TicketPost->GetProperty('dateline'));

			$_ticketPosts[] = $_creater . ' - ' . $_postedOn . PHP_EOL . strip_tags(trim($_TicketPost->GetDisplayContents())) . PHP_EOL;
		}

		$_ticketPosts = implode(PHP_EOL, $_ticketPosts);

		$_ticketPosts = '[' . $_SWIFT_TicketObject->GetProperty('ticketmaskid') . ']: ' . $_SWIFT_TicketObject->GetProperty('subject')
			. PHP_EOL
			. '=================================================='
			. PHP_EOL
			. PHP_EOL
			. $_ticketPosts;

		$this->UserInterface->Start(get_class($this), '/JIRA/Bug/ProcessLinkIssueForm', SWIFT_UserInterface::MODE_INSERT, true);

		$_buttonText = '<input type="button" name="submitbutton" id="%formid%_submit" class="rebuttonblue" onclick="processIssueLinking(\'%formid%\');PreventDoubleClicking(this);" value="' . $this->Language->Get('jira_save') . '" />
					<input type="button" name="submitbutton" id="%formid%_cancel" class="rebuttonred" onclick="javascript: $(\'.ui-icon-closethick\').click();" value="' . $this->Language->Get('jira_cancel') . '"/>';

		$this->UserInterface->OverrideButtonText($_buttonText);

		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabgeneral'), SWIFT::Get('swiftpath') . '__modules/jira/resources/postbugtojira_b.gif', 'general', true, false);

		$this->Load->Library('JIRA:JIRABridge', false, false, 'jira');

		$_JIRABridge = SWIFT_JIRABridge::GetInstance();

		if (!$_JIRABridge) {
			echo $this->Language->Get('jira_error');
		}

		//Add form fields
		$_GeneralTabObject->Hidden('ticketId', $_ticketID);
		$_GeneralTabObject->Hidden('ticketkey', $_SWIFT_TicketObject->GetTicketDisplayID());
		$_GeneralTabObject->Text('jira_issue_id', $this->Language->Get('jira_issue_id'), $this->Language->Get('jira_issue_id_desc'), '', 'text', 60);
		$_GeneralTabObject->Title('<label for="description">' . $this->Language->Get('jira_description') . '</label><br/><span class="tabledescription">' . $this->Language->Get('jira_sensitive') . '</span>');
		$_GeneralTabObject->TextArea('description', '', $this->Language->Get('jira_description_desc'), $_ticketPosts, 3, 14);

		//Add Hidden fields - proves handy for Loading the view after update
		$this->UserInterface->Hidden('jira_ticketid', $_SWIFT_TicketObject->GetTicketID());
		$this->UserInterface->Hidden('jira_listtype', 'inbox');
		$this->UserInterface->Hidden('jira_departmentid', $_SWIFT_TicketObject->GetProperty('departmentid'));
		$this->UserInterface->Hidden('jira_ticketstatusid', $_SWIFT_TicketObject->GetProperty('ticketstatusid'));
		$this->UserInterface->Hidden('jira_tickettypeid', $_SWIFT_TicketObject->GetProperty('tickettypeid'));

		$this->UserInterface->End();

		return true;
	}

	/**
	 * Renders the 'Add Comment to JIRA' form
	 *
	 * @param string $_issueKey The JIRA issue key
	 *
	 * @return boolean 'TRUE' on success and 'FALSE' otherwise
	 * @throws SWIFT_Exception if class is not loaded or $_issueKey is empty
	 */
	public function RenderCommentForm($_issueKey)
	{
		if (!$this->GetIsClassLoaded()) {
			throw new SWIFT_Exception(__CLASS__ . ' ' . SWIFT_CLASSNOTLOADED);
		}

		if (empty($_issueKey)) {
			throw new SWIFT_Exception('Issue Key' . $this->Language->Get('jira_noempty'));
		}

		$_buttonText = '<input type="button" name="submitbutton" id="%formid%_submit" class="rebuttonblue" onclick="javascript: $(\'#%formid%\').submit();PreventDoubleClicking(this);" value="' . $this->Language->Get('jira_post') . '"/>
					 <input type="button" name="submitbutton" id="%formid%_cancel" class="rebuttonred" onclick="javascript: $(\'.ui-icon-closethick\').click();" value="' . $this->Language->Get('jira_cancel') . '" onfocus="blur();" />';

		$this->UserInterface->OverrideButtonText($_buttonText);

		$_GeneralTabObject = $this->UserInterface->AddTab($this->Language->Get('tabgeneral'), SWIFT::Get('swiftpath') . '__modules/jira/resources/postbugtojira_b.gif', 'general', true, false);
		$this->Load->Library('JIRA:JIRABridge', false, false, 'jira');

		$_JIRABridge = SWIFT_JIRABridge::GetInstance();

		if (!$_JIRABridge || !$_JIRABridge instanceof SWIFT_JIRABridge || !$_JIRABridge->GetIsClassLoaded()) {
			SWIFT::Notify(SWIFT::NOTIFICATION_ERROR, $this->Language->Get('jira_error'));

			return false;
		}

		$_JIRAIssue = $_JIRABridge->GetIssueBy('issuekey', $_issueKey);

		if ($_JIRAIssue && $_JIRAIssue instanceof SWIFT_JIRAIssueManager && $_JIRAIssue->GetIsClassLoaded()) {

			$this->Load->Library('Ticket:Ticket', false, false, APP_TICKETS);

			$_project = $_JIRAIssue->GetProject();

			$_RolesContainer = $_JIRABridge->GetProjectRoles($_project);

			$_RolesOptionContainer = array();

			if ($_RolesContainer && _is_array($_RolesContainer)) {
				foreach ($_RolesContainer as $_Role => $_RoleURL) {
					$_RolesOptionContainer[] = array(
						'title' => $_Role,
						'value' => $_Role
					);
				}
			}

			$_SWIFT_TicketObject = SWIFT_Ticket::GetObjectOnID($_JIRAIssue->GetKayakoTicketID());

			$_GeneralTabObject->Title('<label for="comment">' . $this->Language->Get('jira_comment') . '</label><br/><span class="tabledescription">' . $this->Language->Get('jira_sensitive') . '</span>');
			$_GeneralTabObject->TextArea('comment', '', '', '', 50, 6);
			$_GeneralTabObject->Select('visibility', $this->Language->Get('jira_comment_visibility'), '', $_RolesOptionContainer);
			$_GeneralTabObject->Hidden('issueKey', $_issueKey);

			$this->UserInterface->Hidden('jira_ticketid', $_SWIFT_TicketObject->GetTicketID());
			$this->UserInterface->Hidden('jira_listtype', 'inbox');
			$this->UserInterface->Hidden('jira_departmentid', $_SWIFT_TicketObject->GetProperty('departmentid'));
			$this->UserInterface->Hidden('jira_ticketstatusid', $_SWIFT_TicketObject->GetProperty('ticketstatusid'));
			$this->UserInterface->Hidden('jira_tickettypeid', $_SWIFT_TicketObject->GetProperty('tickettypeid'));
		} else {
			SWIFT::Notify(SWIFT::NOTIFICATION_ERROR, $this->Language->Get('jira_noissuefound') . $_issueKey);
		}

		return true;
	}
}
