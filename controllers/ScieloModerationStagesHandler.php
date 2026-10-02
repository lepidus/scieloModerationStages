<?php

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
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageRegister;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStageDAO;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderEmailBuilder;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;
use APP\plugins\generic\scieloModerationStages\classes\DashboardExhibitorsHelper;
use APP\plugins\generic\scieloModerationStages\classes\mail\builders\StageAdvancementEmailBuilder;

class ScieloModerationStagesHandler extends Handler
{
    private const SUBMISSION_STAGE_ID = 5;
    private const SUBMISSION_SCOPED_OPERATIONS = ['updateSubmissionStageData', 'getSubmissionExhibitData'];

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['getReminderBody', 'updateSubmissionStageData', 'getSubmissionExhibitData']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR],
            ['getSubmissionExhibitData']
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
        $submission = $this->getSubmission();
        $request = Application::get()->getRequest();
        $userId = $request->getUser()->getId();
        $contextId = $request->getContext()->getId();

        $exhibitorsHelper = new DashboardExhibitorsHelper();
        $exhibitData = $exhibitorsHelper->getExhibitorsData($submission, $userId, $contextId);

        return json_encode($exhibitData);
    }
}
