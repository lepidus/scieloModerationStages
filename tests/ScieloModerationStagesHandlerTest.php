<?php

use PHPUnit\Framework\TestCase;
use APP\submission\Submission;
use PKP\core\JSONMessage;
use PKP\security\Role;

require_once __DIR__ . '/../controllers/ScieloModerationStagesHandler.php';

/**
 * The accessible workflow stages below are those returned by the core
 * getAccessibleWorkflowStages: MANAGER/SITE_ADMIN on every stage only when the user
 * has no assignment in the submission, otherwise the role of each assignment.
 */
class ScieloModerationStagesHandlerTest extends TestCase
{
    private const MANAGER_ONLY_AUTHOR = [5 => [Role::ROLE_ID_AUTHOR]];
    private const MANAGER_AUTHOR_AND_MANAGER = [5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_MANAGER]];
    private const MANAGER_AUTHOR_AND_MODERATOR = [5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR]];
    private const MANAGER_UNASSIGNED = [1 => [Role::ROLE_ID_MANAGER], 5 => [Role::ROLE_ID_MANAGER]];
    private const ADMIN_UNASSIGNED = [1 => [Role::ROLE_ID_SITE_ADMIN], 5 => [Role::ROLE_ID_SITE_ADMIN]];
    private const SUB_EDITOR_ONLY_AUTHOR = [5 => [Role::ROLE_ID_AUTHOR]];
    private const SUB_EDITOR_ASSIGNED = [5 => [Role::ROLE_ID_SUB_EDITOR]];

    private function createHandler(array $userRoles, array $accessibleWorkflowStages): ScieloModerationStagesHandler
    {
        return new class ($userRoles, $accessibleWorkflowStages) extends ScieloModerationStagesHandler {
            public function __construct(private array $userRoles, private array $accessibleWorkflowStages)
            {
                parent::__construct();
            }

            public function getSubmission()
            {
                return new Submission();
            }

            protected function currentUserRoles(): array
            {
                return $this->userRoles;
            }

            protected function getAccessibleWorkflowStages($submission): array
            {
                return $this->accessibleWorkflowStages;
            }
        };
    }

    private function createRequest(): object
    {
        return new class () {
            public function checkCSRF(): bool
            {
                return true;
            }
        };
    }

    public function testEditorialAccessFollowsAccessibleWorkflowStages(): void
    {
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::MANAGER_ONLY_AUTHOR));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::MANAGER_AUTHOR_AND_MANAGER));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::MANAGER_AUTHOR_AND_MODERATOR));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::MANAGER_UNASSIGNED));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::ADMIN_UNASSIGNED));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::SUB_EDITOR_ONLY_AUTHOR));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess(self::SUB_EDITOR_ASSIGNED));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_ASSISTANT]]));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantEditorialAccess([]));
    }

    public function testStageDataUpdateFollowsAccessibleWorkflowStages(): void
    {
        $roles = ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;

        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole(self::MANAGER_ONLY_AUTHOR, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole(self::MANAGER_AUTHOR_AND_MANAGER, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole(self::MANAGER_AUTHOR_AND_MODERATOR, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole(self::MANAGER_UNASSIGNED, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole(self::ADMIN_UNASSIGNED, $roles));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole(self::SUB_EDITOR_ONLY_AUTHOR, $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole(self::SUB_EDITOR_ASSIGNED, $roles));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_ASSISTANT]], $roles));
    }

    public function testUpdateSubmissionStageDataRejectsManagerAssignedOnlyAsAuthor(): void
    {
        $handler = $this->createHandler([Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR], self::MANAGER_ONLY_AUTHOR);

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            $this->createRequest()
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testUpdateSubmissionStageDataRejectsSubEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = $this->createHandler([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR], self::SUB_EDITOR_ONLY_AUTHOR);

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            $this->createRequest()
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }
}
