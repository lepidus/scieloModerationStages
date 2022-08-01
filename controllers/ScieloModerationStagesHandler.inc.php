<?php

import('classes.handler.Handler');
import ('plugins.reports.scieloModerationStagesReport.classes.ModerationStageDAO');

class ScieloModerationStagesHandler extends Handler {

    public function updateSubmissionStageData($args, $request){
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($args['submissionId']);
        
        if(isset($args['formatStageEntryDate']))
            $submission->setData('formatStageEntryDate', $args['formatStageEntryDate']);

        if(isset($args['contentStageEntryDate']))
            $submission->setData('contentStageEntryDate', $args['contentStageEntryDate']);

        if(isset($args['areaStageEntryDate']))
            $submission->setData('areaStageEntryDate', $args['areaStageEntryDate']);

        if(isset($args['sendNextStage']) && $args['sendNextStage'] == 1) {
            $moderationStage = new ModerationStage($submission);
			$moderationStage->sendNextStage();
			$moderationStageRegister = new ModerationStageRegister();
			$moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
			$moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
        }

        $submissionDao->updateObject($submission);
        return http_response_code(200);
    }

    public function getSubmissionModerationStage($args, $request) {
        $submissionId = $args['submissionId'];
        $moderationStageDAO = new ModerationStageDAO();

        $moderationStage = $moderationStageDAO->getSubmissionModerationStage($submissionId);
        if(!is_null($moderationStage)) {
            $stageMap = [
                SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
                SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
                SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
            ];

            return json_encode(['submissionId' => $submissionId, 'moderationStageName' => __($stageMap[$moderationStage])]);
        }

        return json_encode(['submissionId' => $submissionId, 'moderationStageName' => '']);
    }

}