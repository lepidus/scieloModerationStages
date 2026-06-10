<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\handler\Handler;
use PKP\facades\Locale;
use APP\facades\Repo;
use PKP\security\Role;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageRegressionEmailBuilder;

class ScieloModerationStagesHandler extends Handler
{
    private const SUBMISSION_STAGE_ID = 5;
    private const THRESHOLD_TIME_EXHIBITORS = 2;

    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['getModerationTabData', 'getUserIsAuthor', 'getSubmissionExhibitData']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            ['updateSubmissionStageData']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['getModerationStageCounts']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
            ['getReminderBody']
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getModerationTabData($args, $request)
    {
        $submission = Repo::submission()->get((int) ($args['submissionId'] ?? 0));
        if (!$submission) {
            http_response_code(404);
            return json_encode(['stageExists' => false]);
        }

        $moderationStage = new ModerationStage($submission);

        if (!$moderationStage->submissionStageExists()) {
            return json_encode(['stageExists' => false]);
        }

        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $context = $request->getContext();
        $canAdvanceStage = $moderationStage->canAdvanceStage();
        $canRegressStage = $moderationStage->canRegressStage();

        $data = [
            'stageExists' => true,
            'submissionId' => $submission->getId(),
            'userIsAuthor' => $plugin->userIsAuthor($submission),
            'currentStageKey' => $moderationStage->getCurrentStageName(false),
            'currentStageName' => $moderationStage->getCurrentStageName(),
            'canAdvanceStage' => $canAdvanceStage,
            'canRegressStage' => $canRegressStage,
            'stageEntryDates' => $moderationStage->getStageEntryDates(),
            'faqUrl' => $request->url($context->getPath()) . '/faq',
            'csrfToken' => $request->getSession()->token(),
        ];

        if ($canAdvanceStage) {
            $data['nextStageName'] = $moderationStage->getNextStageName();
        }

        if ($canRegressStage) {
            $data['previousStageName'] = $moderationStage->getPreviousStageName();
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
        if (!$request->checkCSRF()) {
            return http_response_code(403);
        }

        $submission = Repo::submission()->get((int) ($args['submissionId'] ?? 0));
        if (!$submission) {
            return http_response_code(404);
        }

        $moderationStage = new ModerationStage($submission);

        foreach (['formatStageEntryDate', 'contentStageEntryDate', 'areaStageEntryDate'] as $dateField) {
            if (isset($args[$dateField]) && is_string($args[$dateField]) && $this->isValidEntryDate($args[$dateField])) {
                $submission->setData($dateField, $args[$dateField]);
            }
        }

        $userSelectedAdvanceStage = (($args['sendNextStage'] ?? 0) == 1);
        $userSelectedRegressStage = (($args['sendPreviousStage'] ?? 0) == 1);

        if ($userSelectedAdvanceStage && $moderationStage->canAdvanceStage()) {
            $moderationStage->sendNextStage();
            $this->registerStageChange(
                $moderationStage,
                'plugins.generic.scieloModerationStages.log.submissionSentToModerationStage'
            );

            $email = (new StageAdvancementEmailBuilder())
                ->setSubmission($submission)
                ->buildEmailParams()
                ->build();
            Mail::send($email);
        } elseif ($userSelectedRegressStage && $moderationStage->canRegressStage()) {
            $moderationStage->sendPreviousStage();
            $this->registerStageChange(
                $moderationStage,
                'plugins.generic.scieloModerationStages.log.submissionReturnedToModerationStage'
            );

            $email = (new StageRegressionEmailBuilder())
                ->setSubmission($submission)
                ->buildEmailParams()
                ->build();
            Mail::send($email);
        }

        Repo::submission()->edit($submission, []);
        return http_response_code(200);
    }

    private function registerStageChange(ModerationStage $moderationStage, string $logMessageKey): void
    {
        $moderationStageRegister = new ModerationStageRegister();
        $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
        $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage, $logMessageKey);
    }

    private function isValidEntryDate(string $date): bool
    {
        $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }

    public function getSubmissionExhibitData($args, $request)
    {
        $submissionId = (int) $args['submissionId'];
        $exhibitData = ['submissionId' => $submissionId];

        if ($args['userIsAuthor'] == 0) {
            $exhibitData = array_merge(
                $exhibitData,
                $this->getAreaModerators($submissionId),
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

    /**
     * Returns the active submissions count per moderation stage keyed by the
     * dashboard view id, so it can be merged into the native _submissions/viewsCount
     * response and rendered with the same badge as the core dashboard views.
     */
    public function getModerationStageCounts($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();

        $editorialRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        if (!$context || !$user || empty(array_intersect($editorialRoles, $userRoles))) {
            return json_encode([]);
        }

        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));

        $moderationStageDao = new ModerationStageDAO();
        $counts = $moderationStageDao->countSubmissionsByModerationStage(
            $context->getId(),
            $canAccessUnassignedSubmission ? null : $user->getId()
        );

        $stages = [
            ModerationStage::SCIELO_MODERATION_STAGE_FORMAT,
            ModerationStage::SCIELO_MODERATION_STAGE_CONTENT,
            ModerationStage::SCIELO_MODERATION_STAGE_AREA,
        ];

        $result = [];
        foreach ($stages as $stage) {
            $result['moderation-stage-' . $stage] = $counts[$stage] ?? 0;
        }

        return json_encode($result);
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
