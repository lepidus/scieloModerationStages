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
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\UserGroup;
use APP\submission\Submission;
use APP\decision\Decision;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;

class ScieloModerationStagesHandler extends Handler
{
    private const SUBMISSION_STAGE_ID = 5;
    private const THRESHOLD_TIME_EXHIBITORS = 2;
    private const SUBMISSION_SCOPED_OPERATIONS = ['getModerationTabData', 'updateSubmissionStageData', 'getSubmissionExhibitData'];

    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['getModerationTabData', 'getSubmissionExhibitData']
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
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    public function getModerationTabData($args, $request)
    {
        $submission = $this->getSubmission();
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
        $context = $request->getContext();

        if (is_null($context)) {
            return http_response_code(400);
        }

        $role = $args['role'] ?? null;
        $locale = Locale::getLocale();
        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $moderationReminderHelper = new ModerationReminderHelper();

        if ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_CONTENT;
            $expectedUserGroup = $moderationReminderHelper->getResponsiblesUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'preModerationTimeLimit');
        } elseif ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_AREA;
            $expectedUserGroup = $moderationReminderHelper->getAreaModeratorsUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'areaModerationTimeLimit');
        } else {
            return http_response_code(400);
        }

        $userGroupId = (int) ($args['userGroup'] ?? 0);

        if (is_null($expectedUserGroup) || (int) $expectedUserGroup->id !== $userGroupId) {
            return http_response_code(400);
        }

        $userToRemind = Repo::user()->get((int) ($args['user'] ?? 0));

        if (is_null($userToRemind)) {
            return http_response_code(400);
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

        $submission = $this->getSubmission();
        $moderationStage = new ModerationStage($submission);

        foreach (['formatStageEntryDate', 'contentStageEntryDate', 'areaStageEntryDate'] as $dateField) {
            if (isset($args[$dateField]) && is_string($args[$dateField]) && $this->isValidEntryDate($args[$dateField])) {
                $submission->setData($dateField, $args[$dateField]);
            }
        }

        $stageChangeAction = $args['stageChangeAction'] ?? null;

        if ($stageChangeAction === 'advance' && $moderationStage->canAdvanceStage()) {
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
        } elseif ($stageChangeAction === 'regress' && $moderationStage->canRegressStage()) {
            $moderationStage->sendPreviousStage();
            $this->registerStageChange(
                $moderationStage,
                'plugins.generic.scieloModerationStages.log.submissionReturnedToModerationStage'
            );
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
        $submissionId = $this->getSubmission()->getId();
        $exhibitData = ['submissionId' => $submissionId];

        if (!$this->currentUserIsAuthor()) {
            $exhibitData = array_merge(
                $exhibitData,
                $this->getAreaModerators($submissionId),
                $this->getTimeResponsible($submissionId),
                $this->getTimeAreaModerator($submissionId)
            );
        }

        return json_encode($exhibitData);
    }

    private function currentUserIsAuthor(): bool
    {
        $userRoles = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $editorialRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        return count(array_intersect($userRoles, $editorialRoles)) === 0;
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
