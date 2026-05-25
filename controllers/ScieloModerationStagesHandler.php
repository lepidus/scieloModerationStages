<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\handler\Handler;
use PKP\facades\Locale;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;

class ScieloModerationStagesHandler extends Handler
{
    private const SUBMISSION_STAGE_ID = 5;
    private const THRESHOLD_TIME_EXHIBITORS = 2;

    public function getModerationTabData($args, $request)
    {
        $submission = Repo::submission()->get((int) $args['submissionId']);
        $moderationStage = new ModerationStage($submission);

        if (!$moderationStage->submissionStageExists()) {
            return json_encode(['stageExists' => false]);
        }

        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $context = $request->getContext();

        $data = [
            'stageExists' => true,
            'submissionId' => $submission->getId(),
            'userIsAuthor' => $plugin->userIsAuthor($submission),
            'currentStageKey' => $moderationStage->getCurrentStageName(false),
            'currentStageName' => $moderationStage->getCurrentStageName(),
            'canAdvanceStage' => $moderationStage->canAdvanceStage(),
            'stageEntryDates' => $moderationStage->getStageEntryDates(),
            'faqUrl' => $request->url($context->getPath()) . '/faq',
            'csrfToken' => $request->getSession()->token(),
        ];

        if ($moderationStage->canAdvanceStage()) {
            $data['nextStageName'] = $moderationStage->getNextStageName();
        }

        return json_encode($data);
    }

    public function getReminderBody($args, $request)
    {
        $userGroupId = (int) $args['userGroup'];
        $role = $args['role'];
        $userToRemind = Repo::user()->get((int) $args['user']);

        $context = $request->getContext();
        $locale = Locale::getLocale();
        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');

        if ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_CONTENT;
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'preModerationTimeLimit');
        } elseif ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_AREA;
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'areaModerationTimeLimit');
        }

        $moderationStageDao = new ModerationStageDAO();
        $assignments = $moderationStageDao->getAssignmentsByUserGroupAndModerationStage(
            $userGroupId,
            $moderationStage,
            $userToRemind->getId()
        );

        $submissions = [];
        foreach ($assignments as $assignment) {
            $submission = Repo::submission()->get($assignment['submissionId']);

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

        return json_encode(['reminderBody' => $reminderEmail->view]);
    }

    public function updateSubmissionStageData($args, $request)
    {
        $submission = Repo::submission()->get($args['submissionId']);
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

        $userSelectedAdvanceStage = (($args['sendNextStage'] ?? 0) == 1);
        if ($userSelectedAdvanceStage and $moderationStage->canAdvanceStage()) {
            $moderationStage->sendNextStage();
            $moderationStageRegister = new ModerationStageRegister();
            $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
            $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage);

            $emailBuilder = new StageAdvancementEmailBuilder();
            $email = $emailBuilder->setSubmission($submission)
                ->buildEmailParams()
                ->build();
            Mail::send($email);
        }

        Repo::submission()->edit($submission, []);
        return http_response_code(200);
    }

    public function getSubmissionExhibitData($args, $request)
    {
        $submissionId = (int) $args['submissionId'];
        $exhibitData = $this->getSubmissionModerationStage($submissionId);

        if ($args['userIsAuthor'] == 0) {
            $exhibitData = array_merge(
                $exhibitData,
                $this->getResponsibles($submissionId),
                $this->getAreaModerators($submissionId),
                $this->getTimeSubmitted($submissionId),
                $this->getTimeResponsible($submissionId),
                $this->getTimeAreaModerator($submissionId)
            );
        }

        return json_encode($exhibitData);
    }

    public function getUserIsAuthor($args, $request)
    {
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $adminRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        if (count(array_intersect($userRoles, $adminRoles)) > 0) {
            return json_encode(0);
        }

        return json_encode(1);
    }

    private function getSubmissionModerationStage($submissionId)
    {
        $moderationStageDAO = new ModerationStageDAO();

        $moderationStage = $moderationStageDAO->getSubmissionModerationStage($submissionId);
        if (!is_null($moderationStage)) {
            $stageMap = [
                ModerationStage::SCIELO_MODERATION_STAGE_FORMAT => 'plugins.generic.scieloModerationStages.stages.formatStage',
                ModerationStage::SCIELO_MODERATION_STAGE_CONTENT => 'plugins.generic.scieloModerationStages.stages.contentStage',
                ModerationStage::SCIELO_MODERATION_STAGE_AREA => 'plugins.generic.scieloModerationStages.stages.areaStage',
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
        $stageAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([self::SUBMISSION_STAGE_ID])
            ->get();
        $assignedUsers = [];

        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
            $userGroupAbbrev = strtolower($userGroup->getLocalizedData('abbrev', 'en', UserGroup::LOCALE_MATCH_STRICT));

            if ($userGroupAbbrev == $abbrev) {
                $user = Repo::user()->get($stageAssignment->userId, false);
                $assignedUsers[$user->getData('username')] = $user->getFullName();
            }
        }

        return $assignedUsers;
    }

    private function getSecondDateParamsForTimeExhibitors($submission): array
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
        $submission = Repo::submission()->get($submissionId);
        $dateSubmitted = $submission->getData('dateSubmitted');

        if (empty($dateSubmitted)) {
            return ['TimeSubmitted' => ''];
        }

        return $this->getDataForTimeExhibitors($submission, $dateSubmitted, "TimeSubmitted");
    }

    private function getLastAssignmentDate($submissionId, $abbrev): string
    {
        $stageAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([self::SUBMISSION_STAGE_ID])
            ->get();
        $lastAssignmentDate = "";

        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
            $currentUserGroupAbbrev = strtolower($userGroup->getLocalizedData('abbrev', 'en', UserGroup::LOCALE_MATCH_STRICT));

            if ($currentUserGroupAbbrev == $abbrev) {
                if (empty($lastAssignmentDate) or ($stageAssignment->dateAssigned > $lastAssignmentDate)) {
                    $lastAssignmentDate = $stageAssignment->dateAssigned;
                }
            }
        }

        return $lastAssignmentDate;
    }

    private function getTimeResponsible($submissionId)
    {
        $submission = Repo::submission()->get($submissionId);
        $lastAssignmentDate = $this->getLastAssignmentDate($submissionId, 'resp');

        if (empty($lastAssignmentDate)) {
            return ['TimeResponsible' => ''];
        }
        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeResponsible");
    }

    private function getTimeAreaModerator($submissionId)
    {
        $submission = Repo::submission()->get($submissionId);
        $lastAssignmentDate = $this->getLastAssignmentDate($submissionId, 'am');

        if (empty($lastAssignmentDate)) {
            return ['TimeAreaModerator' => ''];
        }

        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeAreaModerator");
    }
}
