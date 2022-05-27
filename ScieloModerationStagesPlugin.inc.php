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

class ScieloModerationStagesPlugin extends GenericPlugin {

	public function register($category, $path, $mainContextId = NULL) {
		$success = parent::register($category, $path, $mainContextId);
        
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE'))
            return true;
        
        if ($success && $this->getEnabled($mainContextId)) {
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

	public function setSubmissionFirstModerationStage($hookName, $params) {
		//Chamar as duas funcoes abaixo
	}
	
	//function colocar submissao num estagio

	//function 	registrar no histórico de atividades que a submissao foi pro estagio tal
}