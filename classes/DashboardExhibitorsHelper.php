<?php

namespace APP\plugins\generic\scieloModerationStages\classes;

use APP\facades\Repo;
use PKP\db\DAORegistry;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class DashboardExhibitorsHelper
{
    public const RESPONSIBLES_GROUP_ABBREV = 'resp';
    public const AREA_MODERATORS_GROUP_ABBREV = 'am';
    private const THRESHOLD_TIME_EXHIBITORS = 2;

    public $moderationStageDao;

    public function __construct()
    {
        $this->moderationStageDao = new ModerationStageDAO();
    }

    protected function getUserUserGroups(int $userId, int $contextId): array
    {
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByUserIds([$userId])
            ->getMany();

        $userUserGroups = [];
        foreach ($userGroups as $userGroup) {
            $role = $userGroup->getRoleId();
            if (!isset($userUserGroups[$role])) {
                $userUserGroups[$role] = [];
            }

            $userUserGroups[$role][$userGroup->getId()] = $userGroup->getData('abbrev');
        }

        return $userUserGroups;
    }

    public function getSubmissionModerationStageText(int $submissionId): string
    {
        $moderationStage = $this->moderationStageDao->getSubmissionModerationStage($submissionId);

        if (!is_null($moderationStage)) {
            $stageMap = [
                ModerationStage::SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
                ModerationStage::SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
                ModerationStage::SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
            ];

            return __('plugins.generic.scieloModerationStages.currentStageStatusLabel') . ' ' . __($stageMap[$moderationStage]);
        }

        return '';
    }

    public function getResponsiblesText(int $submissionId): string
    {
        $responsibleUsers = $this->getAssignedUsersByGroupAbbrev($submissionId, self::RESPONSIBLES_GROUP_ABBREV);

        $responsiblesText = "";

        if (count($responsibleUsers) > 1) {
            unset($responsibleUsers['scielo-brasil']);
        }

        if (count($responsibleUsers) == 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsible', ['responsible' =>  array_pop($responsibleUsers)]);
        } elseif (count($responsibleUsers) > 1) {
            $responsiblesText = __('plugins.generic.scieloModerationStages.responsibles', ['responsibles' => implode(", ", $responsibleUsers)]);
        }

        return $responsiblesText;
    }

    public function getAreaModeratorsText(int $submissionId)
    {
        $areaModeratorUsers = $this->getAssignedUsersByGroupAbbrev($submissionId, self::AREA_MODERATORS_GROUP_ABBREV);

        $areaModeratorsText = "";
        if (count($areaModeratorUsers) == 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerator', ['areaModerator' => array_pop($areaModeratorUsers)]);
        } elseif (count($areaModeratorUsers) > 1) {
            $areaModeratorsText = __('plugins.generic.scieloModerationStages.areaModerators', ['areaModerators' => implode(", ", $areaModeratorUsers)]);
        }

        return $areaModeratorsText;
    }

    protected function getAssignedUsersByGroupAbbrev(int $submissionId, string $abbrev): array
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
        $assignedUsers = [];

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());
            $userGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en'));

            if ($userGroupAbbrev == $abbrev) {
                $user = Repo::user()->get($stageAssignment->getUserId(), false);
                $assignedUsers[$user->getData('username')] = $user->getFullName();
            }
        }

        return $assignedUsers;
    }

    public function getTimeSubmittedData(Submission $submission)
    {
        $dateSubmitted = $submission->getData('dateSubmitted');

        if (empty($dateSubmitted)) {
            return ['TimeSubmitted' => ''];
        }

        return $this->getDataForTimeExhibitor($submission, $dateSubmitted, "TimeSubmitted");
    }

    public function getTimeResponsibleData(Submission $submission)
    {
        $lastAssignmentDate = $this->getLastAssignmentDateByGroupAbbrev($submission->getId(), self::RESPONSIBLES_GROUP_ABBREV);

        if (empty($lastAssignmentDate)) {
            return ['TimeResponsible' => ''];
        }
        return $this->getDataForTimeExhibitor($submission, $lastAssignmentDate, "TimeResponsible");
    }

    public function getTimeAreaModeratorData(Submission $submission)
    {
        $lastAssignmentDate = $this->getLastAssignmentDateByGroupAbbrev($submission->getId(), self::AREA_MODERATORS_GROUP_ABBREV);

        if (empty($lastAssignmentDate)) {
            return ['TimeAreaModerator' => ''];
        }

        return $this->getDataForTimeExhibitor($submission, $lastAssignmentDate, "TimeAreaModerator");
    }

    public function getDataForTimeExhibitor(Submission $submission, string $firstDate, string $exhibitor): array
    {
        list($dateType, $secondDate) = $this->getSubmissionFinalDateParams($submission);
        $firstDate = new \DateTime($firstDate);
        $secondDate = new \DateTime($secondDate);

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

    protected function getSubmissionFinalDateParams(Submission $submission): array
    {
        if ($submission->getData('status') == Submission::STATUS_PUBLISHED) {
            $publication = $submission->getCurrentPublication();
            return ['datePublished', $publication->getData('datePublished')];
        }

        if ($submission->getData('status') == Submission::STATUS_DECLINED) {
            $result = DB::table('edit_decisions')
                ->where('submission_id', $submission->getId())
                ->whereIn('decision', [Decision::DECLINE, Decision::INITIAL_DECLINE])
                ->orderBy('date_decided', 'asc')
                ->first();

            return ['dateDeclined', get_object_vars($result)['date_decided']];
        }

        return ['currentDate', Core::getCurrentDate()];
    }

    protected function getLastAssignmentDateByGroupAbbrev(int $submissionId, string $abbrev): string
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndStageId($submissionId);
        $lastAssignmentDate = "";

        while ($stageAssignment = $stageAssignmentsResults->next()) {
            $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());
            $currentUserGroupAbbrev = strtolower($userGroup->getData('abbrev', 'en'));

            if ($currentUserGroupAbbrev == $abbrev) {
                if (empty($lastAssignmentDate) or ($stageAssignment->getData('dateAssigned') > $lastAssignmentDate)) {
                    $lastAssignmentDate = $stageAssignment->getData('dateAssigned');
                }
            }
        }

        return $lastAssignmentDate;
    }
}
