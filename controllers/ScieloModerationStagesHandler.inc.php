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
            $this->getLastAssignedResponsible($submissionId),
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

    private function getLastAssignedResponsible($submissionId) {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);
        $stageAssignmentsResults = $stageAssignmentsResults->toArray();

        usort($stageAssignmentsResults, function ($a, $b) {
            $a = new DateTime($a->getData('dateAssigned'));
            $b = new DateTime($b->getData('dateAssigned'));
            if ($a == $b) return 0;
            
            return ($a > $b) ? -1 : 1;
        });

        foreach ($stageAssignmentsResults as $stageAssignment) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en_US'));

            if ($userGroupAbbrev == 'resp') {
                $user = $userDao->getById($stageAssignment->getUserId(), false);
                $responsibleText = __('plugins.generic.scieloModerationStages.responsible', ['responsible' => $user->getFullName()]);
                return ['responsible' => $responsibleText];
            }
        }
        
        return ['responsible' => ""];
    }

    private function getAreaModerators($submissionId) {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        
        $areaModeratorUsers =  array();
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en_US'));

            if ($userGroupAbbrev == 'am') {
                $user = $userDao->getById($stageAssignment->getUserId(), false);
                $areaModeratorUsers[] = $user->getFullName();
            }
        }
        
        if(!empty($areaModeratorUsers))
            $areaModerators = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);
        else
            $areaModerators = "";

        return ['areaModerators' => $areaModerators];
    }
}