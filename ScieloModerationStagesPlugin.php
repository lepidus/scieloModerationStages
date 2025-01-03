<?php

/**
 * @file plugins/reports/scieloModerationStages/ScieloModerationStagesPlugin.inc.php
 *
 * Copyright (c) 2022 - 2024 Lepidus Tecnologia
 * Copyright (c) 2022 - 2024 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class ScieloModerationStagesPlugin
 * @ingroup plugins_generic_scieloModerationStages
 *
 * @brief SciELO Moderation Stages Plugin
 */

namespace APP\plugins\generic\scieloModerationStages;

use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\db\DAORegistry;
use Illuminate\Support\Facades\Event;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\core\JSONMessage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\observers\listeners\AssignFirstModerationStage;

class ScieloModerationStagesPlugin extends GenericPlugin
{
    private const SCIELO_BRASIL_EMAIL = 'scielo.submission@scielo.org';

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (Application::isUnderMaintenance()) {
            return $success;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            Event::subscribe(new AssignFirstModerationStage());

            Hook::add('Schema::get::submission', [$this, 'addOurFieldsToSubmissionSchema']);
            Hook::add('addparticipantform::display', [$this, 'addStageAdvanceToAssignForm']);
            Hook::add('addparticipantform::execute', [$this, 'sendSubmissionToNextModerationStage']);
            Hook::add('queryform::display', [$this, 'hideParticipantsOnDiscussionOpening']);

            Hook::add('Template::Workflow::Publication', [$this, 'addToWorkflowTabs']);
            Hook::add('Template::Workflow', [$this, 'addCurrentStageStatusToWorkflow']);
            Hook::add('LoadComponentHandler', [$this, 'setupScieloModerationStagesHandler']);

            Hook::add('TemplateManager::display', [$this, 'addJavaScriptAndStylesheet']);

            Hook::add('AcronPlugin::parseCronTab', [$this, 'addTasksToCrontab']);

            $this->addHandlerURLToJavaScript();
        }
        return $success;
    }

    public function addHandlerURLToJavaScript()
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $handlerUrl = $request->getDispatcher()->url($request, Application::ROUTE_COMPONENT, null, 'plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler');
        $data = ['moderationStagesHandlerUrl' => $handlerUrl];

        $templateMgr->addJavaScript('ModerationStagesHandler', 'app = ' . json_encode($data) . ';', ['contexts' => 'backend', 'inline' => true]);
    }

    public function addJavaScriptAndStylesheet($hookName, $params)
    {
        if ($params[1] == 'dashboard/index.tpl') {
            $templateMgr = $params[0];
            $request = Application::get()->getRequest();

            $jsUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/load.js';
            $styleUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/stageExhibitor.css';

            $templateMgr->addJavascript('ModerationStagesPlugin', $jsUrl, ['contexts' => 'backend']);
            $templateMgr->addStyleSheet('ModerationStagesExhibitor', $styleUrl, ['contexts' => 'backend']);
        }
        return false;
    }

    public function addTasksToCrontab($hookName, $params)
    {
        $taskFilesPath = &$params[0];
        $taskFilesPath[] = $this->getPluginPath() . DIRECTORY_SEPARATOR . 'scheduledTasks.xml';
        return false;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.scieloModerationStages.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.scieloModerationStages.description');
    }

    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        return array_merge(
            [
                new LinkAction(
                    'sendModerationReminder',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'sendModerationReminder', 'plugin' => $this->getName(), 'category' => 'generic']),
                        __('plugins.generic.scieloModerationStages.sendModerationReminder')
                    ),
                    __('plugins.generic.scieloModerationStages.sendModerationReminder')
                ),
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        __('plugins.generic.scieloModerationStages.settings.title')
                    ),
                    __('manager.plugins.settings')
                )
            ],
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request)
    {
        $context = $request->getContext();
        $contextId = ($context == null) ? 0 : $context->getId();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                return $this->handlePluginForm($request, $contextId, 'ScieloModerationStagesSettingsForm');
            case 'sendModerationReminder':
                return $this->handlePluginForm($request, $contextId, 'SendModerationReminderForm');
        }
        return parent::manage($args, $request);
    }

    private function handlePluginForm($request, $contextId, $formClass)
    {
        $formClass = 'APP\plugins\generic\scieloModerationStages\form\\' . $formClass;
        $form = new $formClass($this, $contextId);
        if ($request->getUserVar('save')) {
            $form->readInputData();
            if ($form->validate()) {
                $form->execute();
                return new JSONMessage(true);
            }
        } else {
            $form->initData();
        }
        return new JSONMessage(true, $form->fetch($request));
    }

    public function setupScieloModerationStagesHandler($hookName, $params)
    {
        $component = &$params[0];
        if ($component == 'plugins.generic.scieloModerationStages.controllers.ScieloModerationStagesHandler') {
            return true;
        }
        return false;
    }

    public function addOurFieldsToSubmissionSchema($hookName, $params)
    {
        $schema = &$params[0];

        $schema->properties->{'currentModerationStage'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'lastModerationStageChange'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'formatStageEntryDate'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'contentStageEntryDate'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];
        $schema->properties->{'areaStageEntryDate'} = (object) [
            'type' => 'string',
            'apiSummary' => true,
            'validation' => ['nullable'],
        ];

        return false;
    }

    public function addStageAdvanceToAssignForm($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        $submission = $params[0]->getSubmission();
        $moderationStage = new ModerationStage($submission);

        if ($moderationStage->canAdvanceStage()) {
            $currentStageName = $moderationStage->getCurrentStageName();
            $nextStageName = $moderationStage->getNextStageName();

            $templateMgr->assign('currentStage', $currentStageName);
            $templateMgr->assign('nextStage', $nextStageName);

            $templateMgr->registerFilter("output", array($this, 'addCheckboxesToAssignForm'));
        }

        return false;
    }

    public function addCheckboxesToAssignForm($output, $templateMgr)
    {
        if (preg_match('/<div[^>]+class="section formButtons/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $sentNextStageOutput = $templateMgr->fetch($this->getTemplateResource('sentNextStage.tpl'));

            $output = substr_replace($output, $sentNextStageOutput, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCheckboxesToAssignForm'));
        }
        return $output;
    }

    public function addToWorkflowTabs($hookName, $params)
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        $submission = $templateMgr->getTemplateVars('submission');

        $moderationStage = new ModerationStage($submission);
        if ($moderationStage->submissionStageExists()) {
            $stageDates = $moderationStage->getStageEntryDates();

            $templateMgr->assign($stageDates);
            $templateMgr->assign('submissionId', $submission->getId());
            $templateMgr->assign('userIsAuthor', $this->userIsAuthor($submission));
            $templateMgr->assign('canAdvanceStage', $moderationStage->canAdvanceStage());

            if ($moderationStage->canAdvanceStage()) {
                $currentStageName = $moderationStage->getCurrentStageName();
                $nextStageName = $moderationStage->getNextStageName();

                $templateMgr->assign('currentStage', $currentStageName);
                $templateMgr->assign('nextStage', $nextStageName);
            }

            $output .= sprintf(
                '<tab id="scieloModerationStages" label="%s">%s</tab>',
                __('plugins.generic.scieloModerationStages.displayNameWorkflow'),
                $templateMgr->fetch($this->getTemplateResource('moderationStageMenu.tpl'))
            );
        }
    }

    public function addCurrentStageStatusToWorkflow($hookName, $params)
    {
        $templateMgr = &$params[1];
        $submission = $templateMgr->getTemplateVars('submission');

        if (!is_null($submission->getData('currentModerationStage'))) {
            $moderationStage = new ModerationStage($submission);

            $templateMgr->assign('currentStageName', $moderationStage->getCurrentStageName());
            $templateMgr->registerFilter("output", [$this, 'addCurrentStageStatusToWorkflowFilter']);
        }

        return false;
    }

    public function addCurrentStageStatusToWorkflowFilter($output, $templateMgr)
    {
        if (preg_match('/<span[^>]+v-if="publicationList.length/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $currentStageStatus = $templateMgr->fetch($this->getTemplateResource('currentStageStatus.tpl'));

            $output = substr_replace($output, $currentStageStatus, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCurrentStageStatusToWorkflowFilter'));
        }
        return $output;
    }

    public function getStyleSheet()
    {
        return $this->getPluginPath() . '/styles/moderationStageStyleSheet.css';
    }

    private function userIsAuthor($submission)
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $submission->getData('stageId'));

            while ($stageAssignment = $stageAssignmentsResult->next()) {
                $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId(), $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
            }
        }

        return $currentUserAssignedRoles[0] == Role::ROLE_ID_AUTHOR;
    }

    public function sendSubmissionToNextModerationStage($hookName, $params)
    {
        $request = Application::get()->getRequest();
        $form = $params[0];
        $requestVars = $request->getUserVars();

        if ($requestVars['sendNextStage']) {
            $submission = $form->getSubmission();
            $moderationStage = new ModerationStage($submission);

            if ($moderationStage->canAdvanceStage()) {
                $moderationStage->sendNextStage();
                $moderationStageRegister = new ModerationStageRegister();
                $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
                $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
            }
        }
    }

    public function hideParticipantsOnDiscussionOpening($hookName, $params)
    {
        $form = $params[0];
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $allParticipants = $templateMgr->getTemplateVars('allParticipants');

        $query = $form->getQuery();
        $submission = Repo::submission()->get($query->getData('assocId'));

        if ($this->userIsAuthor($submission)) {
            $author = $request->getUser();
            $newParticipantsList = [];
            $allowedUsersEmails = [
                $author->getEmail(),
                self::SCIELO_BRASIL_EMAIL
            ];

            foreach ($allParticipants as $participantId => $participantData) {
                $participant = Repo::user()->get($participantId);

                if (in_array($participant->getEmail(), $allowedUsersEmails)) {
                    $newParticipantsList[$participantId] = $participantData;
                }
            }

            $templateMgr->assign('allParticipants', $newParticipantsList);
        }

        return false;
    }
}
