<?php

import('classes.log.SubmissionEventLogEntry');
import('lib.pkp.classes.log.SubmissionLog');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');

class ModerationStageRegister {
    public function registerModerationStage($moderationStage) {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submissionDao->updateObject($moderationStage->submission);

        $moderationStageName = $moderationStage->getModerationStageName($stage);
        $request = Application::get()->getRequest();
        SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_METADATA_UPDATE, 'plugins.generic.scieloModerationStages.log.submissionSentToModerationStage', ['moderationStageName' => $moderationStageName]);
    }
}