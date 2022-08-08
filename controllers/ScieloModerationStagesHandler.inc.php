<?php

import('classes.handler.Handler');
import ('plugins.reports.scieloModerationStagesReport.classes.ModerationStageDAO');

class ScieloModerationStagesHandler extends Handler {

    protected const SUBMISSION_STAGE_ID = 5;

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

    public function getSubmissionExhibitData($args, $request) {
        $submissionId = $args['submissionId'];
        $exhibitData = array_merge(
            $this->getSubmissionModerationStage($submissionId),
            $this->getLastAssignedModerator($submissionId),
            $this->getAreaModerators($submissionId)
        );

        return json_encode($exhibitData);
    }

    private function getSubmissionModerationStage($submissionId) {
        $moderationStageDAO = new ModerationStageDAO();

        $moderationStage = $moderationStageDAO->getSubmissionModerationStage($submissionId);
        if(!is_null($moderationStage)) {
            $stageMap = [
                SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
                SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
                SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
            ];

            return ['submissionId' => $submissionId, 'moderationStageName' => __($stageMap[$moderationStage])];
        }

        return ['submissionId' => $submissionId, 'moderationStageName' => ''];
    }

    private function getLastAssignedModerator($submissionId) {
        
        return [];
    }

    private function getAreaModerators($submissionId) {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        
        $areaModeratorUsers =  array();
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            $userGroupName = strtolower($userGroup->getName('en_US'));

            if ($userGroupName == 'area moderator') {
                $user = $userDao->getById($stageAssignment->getUserId(), false);
                $areaModeratorUsers[] = $this->getUserFirstAndLastName($user);
            }
        }
        
        $areaModerators = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);

        return ['areaModerators' => $areaModerators];
    }

    private function getUserFirstAndLastName($user): string {
        $fullName = $user->getFullName();
        $explodedName = explode(" ", $fullName);

        return $explodedName[0] . ' ' . $explodedName[count($explodedName)-1];
    }
}