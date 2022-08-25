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
            $this->getResponsibles($submissionId),
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

    private function getResponsibles($submissionId) {
        $responsibleUsers = $this->getAssignedUsers($submissionId, 'resp');
        
        $responsiblesText = "";
        
        if(count($responsibleUsers) > 1)
            unset($responsibleUsers['scielo-brasil']);

        if (count($responsibleUsers) == 1)
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsible', ['responsible' =>  array_pop($responsibleUsers)]);
        else if (count($responsibleUsers) > 1)
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsibles', ['responsibles' => implode(", ", $responsibleUsers)]);
        
        return ['responsibles' => $responsiblesText];
    }

    private function getAreaModerators($submissionId) {
        $areaModeratorUsers = $this->getAssignedUsers($submissionId, 'am');

        $areaModeratorsText = "";
        if (count($areaModeratorUsers) == 1)
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerator', ['areaModerator' => array_pop($areaModeratorUsers)]);
        else if(count($areaModeratorUsers) > 1)
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);
        
        return ['areaModerators' => $areaModeratorsText];
    }

    private function getAssignedUsers($submissionId, $abbrev): array {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userDao = DAORegistry::getDAO('UserDAO');
        
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);
        $assignedUsers = [];

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en_US'));

            if ($userGroupAbbrev == $abbrev) {
                $user = $userDao->getById($stageAssignment->getUserId(), false);
                $assignedUsers[$user->getData('username')] = $user->getFullName();
            }
        }

        return $assignedUsers;
    }
}