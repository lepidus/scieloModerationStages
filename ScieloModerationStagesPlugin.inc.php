<?php

/**
 * @file plugins/reports/scieloModerationStages/ScieloModerationStagesPlugin.inc.php
 *
 * Copyright (c) 2022 Lepidus Tecnologia
 * Copyright (c) 2022 SciELO
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class ScieloModerationStagesPlugin
 * @ingroup plugins_generic_scieloModerationStages
 *
 * @brief SciELO Moderation Stages Plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');
import('plugins.generic.scieloModerationStages.classes.ModerationStageRegister');

define('SCIELO_BRASIL_EMAIL', 'scielo.submission@scielo.org');

class ScieloModerationStagesPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Schema::get::submission', array($this, 'addOurFieldsToSubmissionSchema'));
            HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'setSubmissionFirstModerationStage'));
            HookRegistry::register('addparticipantform::display', array($this, 'addFieldsAssignForm'));
            HookRegistry::register('addparticipantform::execute', array($this, 'sendSubmissionToNextModerationStage'));
            HookRegistry::register('queryform::display', array($this, 'hideParticipantsOnDiscussionOpening'));

            HookRegistry::register('Template::Workflow::Publication', array($this, 'addToWorkflowTabs'));
            HookRegistry::register('Template::Workflow', array($this, 'addCurrentStageStatus'));
            HookRegistry::register('LoadComponentHandler', array($this, 'setupScieloModerationStagesHandler'));

            HookRegistry::register('TemplateManager::display', array($this, 'addJavaScriptAndStylesheet'));

            HookRegistry::register('AcronPlugin::parseCronTab', array($this, 'addTasksToCrontab'));
            $this->addHandlerURLToJavaScript();
        }

        return $success;
    }

    public function addHandlerURLToJavaScript()
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $handlerUrl = $request->getDispatcher()->url($request, ROUTE_COMPONENT) . 'plugins/generic/scielo-moderation-stages/controllers/scielo-moderation-stages/';
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
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge(
            [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        __('plugins.generic.scieloModerationStages.settings.title')
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
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
                $this->import('ScieloModerationStagesSettingsForm');
                $form = new ScieloModerationStagesSettingsForm($this, $contextId);
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
        return parent::manage($args, $request);
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

    public function setSubmissionFirstModerationStage($hookName, $params)
    {
        $submission = $params[0]->submission;
        $moderationStage = new ModerationStage($submission);
        $moderationStage->setToFirstStage();
        $moderationStageRegister = new ModerationStageRegister();
        $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
    }

    public function addFieldsAssignForm($hookName, $params)
    {
        $request = PKPApplication::get()->getRequest();
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
        $submission = $templateMgr->get_template_vars('submission');

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

    public function addCurrentStageStatus($hookName, $params)
    {
        $templateMgr = &$params[1];
        $submission = $templateMgr->get_template_vars('submission');

        if (!is_null($submission->getData('currentModerationStage'))) {
            $moderationStage = new ModerationStage($submission);

            $templateMgr->assign('currentStageName', $moderationStage->getCurrentStageName());
            $templateMgr->registerFilter("output", array($this, 'addCurrentStageStatusFilter'));
        }

        return false;
    }

    public function addCurrentStageStatusFilter($output, $templateMgr)
    {
        if (preg_match('/<span[^>]+v-if="publicationList.length/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];

            $currentStageStatus = $templateMgr->fetch($this->getTemplateResource('currentStageStatus.tpl'));

            $output = substr_replace($output, $currentStageStatus, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCurrentStageStatusFilter'));
        }
        return $output;
    }

    public function getStyleSheet()
    {
        return $this->getPluginPath() . '/styles/moderationStageStyleSheet.css';
    }

    private function userIsAuthor($submission)
    {
        $currentUser = \Application::get()->getRequest()->getUser();
        $currentUserAssignedRoles = array();
        if ($currentUser) {
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $submission->getData('stageId'));
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
            while ($stageAssignment = $stageAssignmentsResult->next()) {
                $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $submission->getData('contextId'));
                $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
            }
        }

        return $currentUserAssignedRoles[0] == ROLE_ID_AUTHOR;
    }

    public function sendSubmissionToNextModerationStage($hookName, $params)
    {
        $request = PKPApplication::get()->getRequest();
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
        $submission = Services::get('submission')->get($query->getData('assocId'));

        if ($this->userIsAuthor($submission)) {
            $author = $request->getUser();
            $newParticipantsList = [];
            $allowedUsersEmails = [
                $author->getEmail(),
                SCIELO_BRASIL_EMAIL
            ];

            foreach ($allParticipants as $participantId => $participantData) {
                $userDao = DAORegistry::getDAO('UserDAO');
                $participant = $userDao->getById($participantId);

                if (in_array($participant->getEmail(), $allowedUsersEmails)) {
                    $newParticipantsList[$participantId] = $participantData;
                }
            }

            $templateMgr->assign('allParticipants', $newParticipantsList);
        }

        return false;
    }
}
