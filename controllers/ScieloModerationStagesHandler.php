<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\plugins\PluginRegistry;
use APP\core\Application;
use APP\handler\Handler;
use PKP\facades\Locale;
use APP\facades\Repo;
use PKP\core\JSONMessage;
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
    private const ENTRY_DATE_FIELDS = ['formatStageEntryDate', 'contentStageEntryDate', 'areaStageEntryDate'];

    public const EDITORIAL_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
        Role::ROLE_ID_ASSISTANT,
    ];

    public const STAGE_DATA_UPDATE_ROLES = [
        Role::ROLE_ID_SITE_ADMIN,
        Role::ROLE_ID_MANAGER,
        Role::ROLE_ID_SUB_EDITOR,
    ];

    private array $stageAssignments = [];
    private array $userGroupAbbrevs = [];

    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [...self::EDITORIAL_ROLES, Role::ROLE_ID_AUTHOR],
            ['getModerationTabData', 'getSubmissionExhibitData']
        );
        $this->addRoleAssignment(
            self::STAGE_DATA_UPDATE_ROLES,
            ['updateSubmissionStageData']
        );
        $this->addRoleAssignment(
            self::EDITORIAL_ROLES,
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
            return new JSONMessage(true, ['stageExists' => false]);
        }

        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $context = $request->getContext();
        $canUpdate = $this->currentUserCanUpdateStageData();
        $canAdvanceStage = $canUpdate && $moderationStage->canAdvanceStage();
        $canRegressStage = $canUpdate && $moderationStage->canRegressStage();

        $data = [
            'stageExists' => true,
            'submissionId' => $submission->getId(),
            'userIsAuthor' => $plugin->userIsAuthor($submission),
            'canUpdate' => $canUpdate,
            'currentStageKey' => $moderationStage->getCurrentStageName(false),
            'currentStageName' => $moderationStage->getCurrentStageName(),
            'canAdvanceStage' => $canAdvanceStage,
            'canRegressStage' => $canRegressStage,
            'stageEntryDates' => $moderationStage->getStageEntryDatesUpToCurrentStage(),
            'faqUrl' => $request->url($context->getPath()) . '/faq',
            'csrfToken' => $request->getSession()->token(),
        ];

        if ($canAdvanceStage) {
            $data['nextStageName'] = $moderationStage->getNextStageName();
        }

        if ($canRegressStage) {
            $data['previousStageName'] = $moderationStage->getPreviousStageName();
        }

        return new JSONMessage(true, $data);
    }

    public function getReminderBody($args, $request)
    {
        $context = $request->getContext();

        if (is_null($context)) {
            return new JSONMessage(false);
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
            return new JSONMessage(false);
        }

        $userGroupId = (int) ($args['userGroup'] ?? 0);

        if (is_null($expectedUserGroup) || (int) $expectedUserGroup->id !== $userGroupId) {
            return new JSONMessage(false);
        }

        $userToRemind = Repo::user()->get((int) ($args['user'] ?? 0));

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

        return new JSONMessage(true, ['reminderBody' => $reminderEmail->view]);
    }

    public function updateSubmissionStageData($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submission = $this->getSubmission();
        $moderationStage = new ModerationStage($submission);

        $entryDates = $this->readEntryDates($args);
        if (is_null($entryDates)) {
            return new JSONMessage(false);
        }

        foreach ($entryDates as $dateField => $date) {
            $submission->setData($dateField, $date);
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
        return new JSONMessage(true);
    }

    private function registerStageChange(ModerationStage $moderationStage, string $logMessageKey): void
    {
        $moderationStageRegister = new ModerationStageRegister();
        $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
        $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage, $logMessageKey);
    }

    private function readEntryDates(array $args): ?array
    {
        $entryDates = [];

        foreach (self::ENTRY_DATE_FIELDS as $dateField) {
            if (!array_key_exists($dateField, $args)) {
                continue;
            }

            $date = $args[$dateField];
            if (!is_string($date)) {
                return null;
            }

            $date = trim($date);
            if ($date === '') {
                $entryDates[$dateField] = null;
                continue;
            }

            if (!$this->isValidEntryDate($date)) {
                return null;
            }

            $entryDates[$dateField] = $date;
        }

        return $entryDates;
    }

    private function isValidEntryDate(string $date): bool
    {
        $parsedDate = DateTime::createFromFormat('Y-m-d', $date);
        return $parsedDate && $parsedDate->format('Y-m-d') === $date;
    }

    public function getSubmissionExhibitData($args, $request)
    {
        $submission = $this->getSubmission();
        $exhibitData = ['submissionId' => $submission->getId()];

        if ($this->currentUserIsEditorial()) {
            $exhibitData = array_merge(
                $exhibitData,
                $this->getAreaModerators($submission->getId()),
                $this->getTimeResponsible($submission),
                $this->getTimeAreaModerator($submission)
            );
        }

        return new JSONMessage(true, $exhibitData);
    }

    private function currentUserIsEditorial(): bool
    {
        return !empty(array_intersect($this->currentUserRoles(), self::EDITORIAL_ROLES));
    }

    private function currentUserCanUpdateStageData(): bool
    {
        return !empty(array_intersect($this->currentUserRoles(), self::STAGE_DATA_UPDATE_ROLES));
    }

    private function currentUserRoles(): array
    {
        return (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
    }

    public function getModerationStageCounts($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();

        $userRoles = $this->currentUserRoles();

        if (!$context || !$user || !$this->currentUserIsEditorial()) {
            return new JSONMessage(true, []);
        }

        $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));

        $moderationStageDao = new ModerationStageDAO();
        $counts = $moderationStageDao->countSubmissionsByModerationStage(
            $context->getId(),
            $canAccessUnassignedSubmission ? null : $user->getId(),
            self::EDITORIAL_ROLES
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

        return new JSONMessage(true, $result);
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
        $assignedUsers = [];

        foreach ($this->getModerationStageAssignments($submissionId) as $stageAssignment) {
            if ($this->getUserGroupAbbrev($stageAssignment->userGroupId) == $abbrev) {
                $user = Repo::user()->get($stageAssignment->userId, false);
                $assignedUsers[$user->getData('username')] = $user->getFullName();
            }
        }

        return $assignedUsers;
    }

    private function getModerationStageAssignments($submissionId)
    {
        return $this->stageAssignments[$submissionId] ??= StageAssignment::withSubmissionIds([$submissionId])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([self::SUBMISSION_STAGE_ID])
            ->get();
    }

    private function getUserGroupAbbrev($userGroupId): string
    {
        if (!isset($this->userGroupAbbrevs[$userGroupId])) {
            $userGroup = Repo::userGroup()->get($userGroupId);
            $this->userGroupAbbrevs[$userGroupId] = is_null($userGroup)
                ? ''
                : strtolower($userGroup->getLocalizedData('abbrev', 'en', UserGroup::LOCALE_MATCH_STRICT));
        }

        return $this->userGroupAbbrevs[$userGroupId];
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
        $lastAssignmentDate = "";

        foreach ($this->getModerationStageAssignments($submissionId) as $stageAssignment) {
            if ($this->getUserGroupAbbrev($stageAssignment->userGroupId) == $abbrev) {
                if (empty($lastAssignmentDate) or ($stageAssignment->dateAssigned > $lastAssignmentDate)) {
                    $lastAssignmentDate = $stageAssignment->dateAssigned;
                }
            }
        }

        return $lastAssignmentDate;
    }

    private function getTimeResponsible($submission)
    {
        $lastAssignmentDate = $this->getLastAssignmentDate($submission->getId(), 'resp');

        if (empty($lastAssignmentDate)) {
            return ['TimeResponsible' => ''];
        }
        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeResponsible");
    }

    private function getTimeAreaModerator($submission)
    {
        $lastAssignmentDate = $this->getLastAssignmentDate($submission->getId(), 'am');

        if (empty($lastAssignmentDate)) {
            return ['TimeAreaModerator' => ''];
        }

        return $this->getDataForTimeExhibitors($submission, $lastAssignmentDate, "TimeAreaModerator");
    }
}
