<?php

use Illuminate\Database\Capsule\Manager as Capsule;

import('classes.handler.Handler');
import('classes.workflow.EditorDecisionActionsManager');
import('lib.pkp.classes.core.JSONMessage');
import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderEmailBuilder');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class ScieloModerationStagesHandler extends Handler
{
    private const SUBMISSION_STAGE_ID = 5;
    private const THRESHOLD_TIME_EXHIBITORS = 2;
    private const SUBMISSION_SCOPED_OPERATIONS = ['updateSubmissionStageData', 'getSubmissionExhibitData'];

    public const EXHIBITOR_EDITORIAL_ROLES = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR];
    public const STAGE_DATA_UPDATE_ROLES = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR];
    public const REMINDER_ROLES = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER];

    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR],
            ['getSubmissionExhibitData']
        );
        $this->addRoleAssignment(
            [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER],
            ['getUserIsAuthor']
        );
        $this->addRoleAssignment(self::STAGE_DATA_UPDATE_ROLES, ['updateSubmissionStageData']);
        $this->addRoleAssignment(self::REMINDER_ROLES, ['getReminderBody']);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $operation = $request->getRouter()->getRequestedOp($request);

        if (in_array($operation, self::SUBMISSION_SCOPED_OPERATIONS)) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    public function getReminderBody($args, $request)
    {
        $context = $request->getContext();
        if (is_null($context)) {
            return new JSONMessage(false);
        }

        $role = $args['role'] ?? null;
        $locale = AppLocale::getLocale();
        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $moderationReminderHelper = new ModerationReminderHelper();

        if ($role == REMINDER_TYPE_PRE_MODERATION) {
            $moderationStage = SCIELO_MODERATION_STAGE_CONTENT;
            $expectedUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'preModerationTimeLimit');
        } elseif ($role == REMINDER_TYPE_AREA_MODERATION) {
            $moderationStage = SCIELO_MODERATION_STAGE_AREA;
            $expectedUserGroup = $moderationReminderHelper->getAreaModeratorsUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'areaModerationTimeLimit');
        } else {
            return new JSONMessage(false);
        }

        $userGroupId = (int) ($args['userGroup'] ?? 0);
        if (is_null($expectedUserGroup) || (int) $expectedUserGroup->getId() !== $userGroupId) {
            return new JSONMessage(false);
        }

        $userToRemind = DAORegistry::getDAO('UserDAO')->getById((int) ($args['user'] ?? 0));
        if (is_null($userToRemind)) {
            return new JSONMessage(false);
        }

        $moderationStageDao = new ModerationStageDAO();
        $assignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $userGroupId,
            $moderationStage,
            $userToRemind->getId()
        );

        $submissions = [];
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        foreach ($assignments as $assignment) {
            $submission = $submissionDao->getById($assignment['submissionId']);

            if ($submission) {
                $submissions[] = $submission;
            }
        }

        $moderationReminderEmailBuilder = new ModerationReminderEmailBuilder(
            $context,
            $userToRemind,
            $submissions,
            $locale,
            $role,
            $moderationTimeLimit
        );
        $reminderEmail = $moderationReminderEmailBuilder->buildEmail();

        return json_encode(['reminderBody' => $reminderEmail->getBody()]);
    }

    public function updateSubmissionStageData($args, $request)
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submission = $this->getSubmission();
        if (!$this->currentUserCanUpdateSubmissionStageData($submission)) {
            return new JSONMessage(false);
        }

        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $moderationStage = new ModerationStage($submission);

        if (isset($args['formatStageEntryDate'])) {
            $submission->setData('formatStageEntryDate', $args['formatStageEntryDate']);
        }

        if (isset($args['contentStageEntryDate'])) {
            $submission->setData('contentStageEntryDate', $args['contentStageEntryDate']);
        }

        if (isset($args['areaStageEntryDate'])) {
            $submission->setData('areaStageEntryDate', $args['areaStageEntryDate']);
        }

        $userSelectedAdvanceStage = (($args['sendNextStage'] ?? null) == 1);
        if ($userSelectedAdvanceStage and $moderationStage->canAdvanceStage()) {
            $moderationStage->sendNextStage();
            $moderationStageRegister = new ModerationStageRegister();
            $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
            $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);
        }

        $submissionDao->updateObject($submission);
        return new JSONMessage(true);
    }

    public function getSubmissionExhibitData($args, $request)
    {
        $submission = $this->getSubmission();
        $submissionId = $submission->getId();
        $exhibitData = $this->getSubmissionModerationStage($submissionId);

        if ($this->currentUserHasEditorialAccessToSubmission($submission)) {
            $exhibitData = array_merge($exhibitData, $this->getEditorialExhibitData($submissionId));
        }

        return json_encode($exhibitData);
    }

    protected function getEditorialExhibitData($submissionId): array
    {
        return array_merge(
            $this->getResponsibles($submissionId),
            $this->getAreaModerators($submissionId),
            $this->getTimeSubmitted($submissionId),
            $this->getTimeResponsible($submissionId),
            $this->getTimeAreaModerator($submissionId)
        );
    }

    public function getUserIsAuthor($args, $request)
    {
        return json_encode($this->currentUserIsEditorial() ? 0 : 1);
    }

    protected function currentUserIsEditorial(): bool
    {
        $userRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        return count(array_intersect($userRoles, self::EXHIBITOR_EDITORIAL_ROLES)) > 0;
    }

    protected function currentUserHasEditorialAccessToSubmission($submission): bool
    {
        return self::stagesGrantAnyRole($this->getAccessibleWorkflowStages($submission), self::EXHIBITOR_EDITORIAL_ROLES);
    }

    protected function currentUserCanUpdateSubmissionStageData($submission): bool
    {
        return self::stagesGrantAnyRole($this->getAccessibleWorkflowStages($submission), self::STAGE_DATA_UPDATE_ROLES);
    }

    public static function stagesGrantAnyRole(array $accessibleWorkflowStages, array $roleIds): bool
    {
        foreach ($accessibleWorkflowStages as $stageRoles) {
            if (count(array_intersect((array) $stageRoles, $roleIds)) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function getAccessibleWorkflowStages($submission): array
    {
        $request = Application::get()->getRequest();

        return (array) Services::get('user')->getAccessibleWorkflowStages(
            $request->getUser()->getId(),
            $request->getContext()->getId(),
            $submission,
            $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
        );
    }

    protected function getSubmissionModerationStage($submissionId)
    {
        $moderationStageDAO = new ModerationStageDAO();

        $moderationStage = $moderationStageDAO->getSubmissionModerationStage($submissionId);
        if (!is_null($moderationStage)) {
            $stageMap = [
                SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
                SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
                SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
            ];

            $moderationStageText = __('plugins.generic.scieloModerationStages.currentStageStatusLabel') . ' ' . __($stageMap[$moderationStage]);

            return ['submissionId' => $submissionId, 'ModerationStage' => $moderationStageText];
        }

        return ['submissionId' => $submissionId, 'ModerationStage' => ''];
    }

    private function getResponsibles($submissionId)
    {
        $responsibleUsers = $this->getAssignedUsers($submissionId, 'resp');

        $responsiblesText = "";

        if (count($responsibleUsers) > 1) {
            unset($responsibleUsers['scielo-brasil']);
        }

        if (count($responsibleUsers) == 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsible', ['responsible' =>  array_pop($responsibleUsers)]);
        } elseif (count($responsibleUsers) > 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsibles', ['responsibles' => implode(", ", $responsibleUsers)]);
        }

        return ['Responsibles' => $responsiblesText];
    }

    private function getAreaModerators($submissionId)
    {
        $areaModeratorUsers = $this->getAssignedUsers($submissionId, 'am');

        $areaModeratorsText = "";
        if (count($areaModeratorUsers) == 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerator', ['areaModerator' => array_pop($areaModeratorUsers)]);
        } elseif (count($areaModeratorUsers) > 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);
        }

        return ['AreaModerators' => $areaModeratorsText];
    }

    private function getAssignedUsers($submissionId, $abbrev): array
    {
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

    private function getSecondDateParamsForTimeExhibitors($submission): array
    {
        if ($submission->getData('status') == STATUS_PUBLISHED) {
            $publication = $submission->getCurrentPublication();
            return ['datePublished', $publication->getData('datePublished')];
        }

        if ($submission->getData('status') == STATUS_DECLINED) {
            $result = Capsule::table('edit_decisions')
                ->where('submission_id', $submission->getId())
                ->whereIn('decision', [SUBMISSION_EDITOR_DECISION_DECLINE, SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE])
                ->orderBy('date_decided', 'asc')
                ->first();

            return ['dateDeclined', get_object_vars($result)['date_decided']];
        }

        return ['currentDate', Core::getCurrentDate()];
    }

    private function getDataForTimeExhibitors($submission, $firstDate, $exhibitor): array
    {
        list($dateType, $secondDate) = $this->getSecondDateParamsForTimeExhibitors($submission);
        $firstDate = new DateTime($firstDate);
        $secondDate = new DateTime($secondDate);

        $daysPassed = $secondDate->diff($firstDate)->format('%a');

        if ($daysPassed == 0) {
            return [$exhibitor => __("plugins.generic.scieloModerationStages.$exhibitor.$dateType.lessThanOneDay")];
        } elseif ($daysPassed > self::THRESHOLD_TIME_EXHIBITORS) {
            return [
                $exhibitor => __("plugins.generic.scieloModerationStages.$exhibitor.$dateType", ['daysPassed' => $daysPassed]),
                "{$exhibitor}RedFlag" => true
            ];
        }

        return [$exhibitor => __("plugins.generic.scieloModerationStages.$exhibitor.$dateType", ['daysPassed' => $daysPassed])];
    }

    private function getTimeSubmitted($submissionId)
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $dateSubmitted = $submission->getData('dateSubmitted');

        if (empty($dateSubmitted)) {
            return ['TimeSubmitted' => ''];
        }

        return $this->getDataForTimeExhibitors($submission, $dateSubmitted, "TimeSubmitted");
    }

    private function getLastAssignmentDate($submissionId, $abbrev): string
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userDao = DAORegistry::getDAO('UserDAO');

        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);
        $lastAssignmentDate = "";

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
            $currentUserGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en_US'));

            if ($currentUserGroupAbbrev == $abbrev) {
                if (empty($lastAssignmentDate) or ($stageAssignment->getData('dateAssigned') > $lastAssignmentDate)) {
                    $lastAssignmentDate = $stageAssignment->getData('dateAssigned');
                }
            }
        }

        return $lastAssignmentDate;
    }

    private function getTimeResponsible($submissionId)
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $lastAssignmentDate = $this->getLastAssignmentDate($submissionId, 'resp');

        if (empty($lastAssignmentDate)) {
            return ['TimeResponsible' => ''];
        }
        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeResponsible");
    }

    private function getTimeAreaModerator($submissionId)
    {
        $submission = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId);
        $lastAssignmentDate = $this->getLastAssignmentDate($submissionId, 'am');

        if (empty($lastAssignmentDate)) {
            return ['TimeAreaModerator' => ''];
        }

        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeAreaModerator");
    }
}
