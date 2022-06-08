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

class ScieloModerationStagesPlugin extends GenericPlugin {
	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);

		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE'))
			return true;

		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('Schema::get::submission', array($this, 'addOurFieldsToSubmissionSchema'));
			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'setSubmissionFirstModerationStage'));
			HookRegistry::register('addparticipantform::display', array($this, 'addFieldsAssignForm'));
			HookRegistry::register('addparticipantform::execute', array($this, 'sendSubmissionToNextModerationStage'));
		}
				
		return $success;
	}

	public function getDisplayName() {
		return __('plugins.generic.scieloModerationStages.displayName');
	}

	public function getDescription() {
		return __('plugins.generic.scieloModerationStages.description');
	}

	public function addOurFieldsToSubmissionSchema($hookName, $params) {
		$schema =& $params[0];

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

	public function setSubmissionFirstModerationStage($hookName, $params) {
		$submission = $params[0]->submission;
		$moderationStage = new ModerationStage($submission);
		$moderationStage->setToFirstStage();
		$moderationStageRegister = new ModerationStageRegister();
		$moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
	}
	
	public function addFieldsAssignForm($hookName, $params) {
        $request = PKPApplication::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

		$submission = $params[0]->getSubmission();
		$moderationStage = new ModerationStage($submission);

		if($moderationStage->canAdvanceStage()) {
			$currentStageName = $moderationStage->getCurrentStageName();
			$nextStageName = $moderationStage->getNextStageName();

			$templateMgr->assign('currentStage', $currentStageName);
			$templateMgr->assign('nextStage', $nextStageName);
			
			$templateMgr->registerFilter("output", array($this, 'addCheckboxesToAssignForm'));
		}

        return false;
    }

	public function addCheckboxesToAssignForm($output, $templateMgr) {
		if (preg_match('/<div[^>]+class="section formButtons/', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posMatch = $matches[0][1];
            
			$sentNextStageOutput = $templateMgr->fetch($this->getTemplateResource('sentNextStage.tpl'));

            $output = substr_replace($output, $sentNextStageOutput, $posMatch, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCheckboxesToAssignForm'));
        }
        return $output;
	}

	public function sendSubmissionToNextModerationStage($hookName, $params) {
		$request = PKPApplication::get()->getRequest();
		$form = $params[0];
		$requestVars = $request->getUserVars();
		
		if($requestVars['sendNextStage']) {
			$submission = $form->getSubmission();
			$moderationStage = new ModerationStage($submission);
			$moderationStage->sendNextStage();
			$moderationStageRegister = new ModerationStageRegister();
			$moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
			$moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
		}
	}

}
