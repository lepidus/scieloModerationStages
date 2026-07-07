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
use PKP\db\DAORegistry;
use PKP\core\JSONMessage;
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
    private const SUBMISSION_SCOPED_OPERATIONS = ['updateSubmissionStageData', 'getSubmissionExhibitData'];

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['getReminderBody', 'updateSubmissionStageData', 'getSubmissionExhibitData', 'getUserIsAuthor']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR],
            ['getSubmissionExhibitData', 'getUserIsAuthor']
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

    public function getReminderBody($args, $request)
    {
        $context = $request->getContext();
        if (is_null($context)) {
            return new JSONMessage(false);
        }

        $role = $args['role'] ?? null;
        $locale = Locale::getLocale();
        $plugin = PluginRegistry::getPlugin('generic', 'scielomoderationstagesplugin');
        $reminderHelper = new ModerationReminderHelper();

        if ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_PRE_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_CONTENT;
            $expectedUserGroup = $reminderHelper->getResponsiblesUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'preModerationTimeLimit');
        } elseif ($role == ModerationReminderEmailBuilder::REMINDER_TYPE_AREA_MODERATION) {
            $moderationStage = ModerationStage::SCIELO_MODERATION_STAGE_AREA;
            $expectedUserGroup = $reminderHelper->getAreaModeratorsUserGroup($context->getId());
            $moderationTimeLimit = $plugin->getSetting($context->getId(), 'areaModerationTimeLimit');
        } else {
            return new JSONMessage(false);
        }

        $userGroupId = (int) $args['userGroup'];
        if (is_null($expectedUserGroup) || (int) $expectedUserGroup->getId() !== $userGroupId) {
            return new JSONMessage(false);
        }

        $userToRemind = Repo::user()->get((int) $args['user']);
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

        return json_encode(['reminderBody' => $reminderEmail->view]);
    }

    public function updateSubmissionStageData($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $submission = $this->getSubmission();
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

        $stageChangeAction = $args['stageChangeAction'] ?? null;

        if ($stageChangeAction === 'advance' and $moderationStage->canAdvanceStage()) {
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
        } elseif ($stageChangeAction === 'regress' and $moderationStage->canRegressStage()) {
            $moderationStage->sendPreviousStage();
            $this->registerStageChange(
                $moderationStage,
                'plugins.generic.scieloModerationStages.log.submissionReturnedToModerationStage'
            );
        }

        Repo::submission()->edit($submission, []);
        return new JSONMessage(true);
    }

    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    private function registerStageChange(ModerationStage $moderationStage, string $logMessageKey): void
    {
        $moderationStageRegister = new ModerationStageRegister();
        $moderationStageRegister->registerModerationStageOnDatabase($moderationStage);
        $moderationStageRegister->registerModerationStageOnSubmissionLog($moderationStage, $logMessageKey);
    }

    public function getSubmissionExhibitData($args, $request)
    {
        $submissionId = $this->getSubmission()->getId();
        $exhibitData = $this->getSubmissionModerationStage($submissionId);

        if (!$this->currentUserIsAuthor()) {
            $exhibitData = array_merge($exhibitData, $this->getEditorialExhibitData($submissionId));
        }

        return json_encode($exhibitData);
    }

    public function getUserIsAuthor($args, $request)
    {
        return json_encode($this->currentUserIsAuthor() ? 1 : 0);
    }

    protected function currentUserIsAuthor(): bool
    {
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $editorialRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        return count(array_intersect($userRoles, $editorialRoles)) === 0;
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

    protected function getSubmissionModerationStage($submissionId)
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
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, Role::ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);
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
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');

        $stageAssignmentsResults = $stageAssignmentDao->getBySubmissionAndRoleId($submissionId, Role::ROLE_ID_SUB_EDITOR, self::SUBMISSION_STAGE_ID);
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
