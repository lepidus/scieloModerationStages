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
import('classes.log.SubmissionEventLogEntry');
import('lib.pkp.classes.log.SubmissionLog');

define('SCIELO_MODERATION_STAGE_FORMAT', 1);
define('SCIELO_MODERATION_STAGE_CONTENT', 2);
define('SCIELO_MODERATION_STAGE_AREA', 3);

class ScieloModerationStagesPlugin extends GenericPlugin {

	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
        
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE'))
            return true;
        
        if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('Schema::get::submission', array($this, 'addOurFieldsToSubmissionSchema'));
			HookRegistry::register('submissionsubmitstep4form::execute', array($this, 'setSubmissionFirstModerationStage'));
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

        return false;
	}

	public function getModerationStageName($stage) {
		$stageMap = [
			'SCIELO_MODERATION_STAGE_FORMAT' => 'plugins.generic.scieloModerationStages.stages.formatStage',
			'SCIELO_MODERATION_STAGE_CONTENT' => 'plugins.generic.scieloModerationStages.stages.contentStage',
			'SCIELO_MODERATION_STAGE_AREA' => 'plugins.generic.scieloModerationStages.stages.areaStage',
		];

		return __($stageMap[$stage]);
	}

	public function setSubmissionFirstModerationStage($hookName, $params) {
		$submission = $params[0]->submission;
		$submission->setData('currentModerationStage', SCIELO_MODERATION_STAGE_FORMAT);
		$submission->setData('lastModerationStageChange', Core::getCurrentDate());

		$request = Application::get()->getRequest();
		$moderationStageName = $this->getModerationStageName(SCIELO_MODERATION_STAGE_FORMAT);
		SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, 'plugins.generic.scieloModerationStages.log.submissionSentToModerationStage', ['moderationStageName' => $moderationStageName]);
	}
	
}