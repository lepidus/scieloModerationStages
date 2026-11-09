<?php

use PHPUnit\Framework\TestCase;
use PKP\core\JSONMessage;
use PKP\security\Role;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableModerationStagesHandler;
use APP\plugins\generic\scieloModerationStages\tests\helpers\FakeCsrfRequest;

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

    public function testUpdateSubmissionStageDataRejectsRequestWithoutValidCsrfToken(): void
    {
        $handler = new TestableModerationStagesHandler();

        $response = $handler->updateSubmissionStageData(['submissionId' => 1], new FakeCsrfRequest(false));

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testUpdateSubmissionStageDataRejectsInvalidEntryDate(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach (['2024-02-30', '2024-01-01T00:00', '<b>x</b>', ['2024-01-01']] as $invalidDate) {
            $response = $handler->updateSubmissionStageData(
                ['submissionId' => 1, 'formatStageEntryDate' => $invalidDate],
                new FakeCsrfRequest(true)
            );

            $this->assertInstanceOf(JSONMessage::class, $response);
            $this->assertFalse($response->getStatus());
        }
    }

    public function testStageDataUpdateIsRestrictedToEditorialManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR] as $roleId) {
            $this->assertContains('updateSubmissionStageData', $handler->getRoleAssignment($roleId));
        }
        foreach ([Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('updateSubmissionStageData', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testReminderBodyIsRestrictedToManagers(): void
    {
        $handler = new TestableModerationStagesHandler();

        foreach ([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER] as $roleId) {
            $this->assertContains('getReminderBody', $handler->getRoleAssignment($roleId));
        }
        foreach ([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER] as $roleId) {
            $this->assertNotContains('getReminderBody', (array) $handler->getRoleAssignment($roleId));
        }
    }

    public function testAssistantKeepsReadAccessToExhibitData(): void
    {
        $handler = new TestableModerationStagesHandler();

        $this->assertSame(['getSubmissionExhibitData'], $handler->getRoleAssignment(Role::ROLE_ID_ASSISTANT));
        $this->assertSame(['getSubmissionExhibitData'], $handler->getRoleAssignment(Role::ROLE_ID_AUTHOR));
    }

    public function testEditorialAccessFollowsAccessibleWorkflowStages(): void
    {
        $handler = TestableModerationStagesHandler::class;

        $this->assertFalse($handler::stagesGrantEditorialAccess(self::MANAGER_ONLY_AUTHOR));
        $this->assertTrue($handler::stagesGrantEditorialAccess(self::MANAGER_AUTHOR_AND_MANAGER));
        $this->assertTrue($handler::stagesGrantEditorialAccess(self::MANAGER_AUTHOR_AND_MODERATOR));
        $this->assertTrue($handler::stagesGrantEditorialAccess(self::MANAGER_UNASSIGNED));
        $this->assertTrue($handler::stagesGrantEditorialAccess(self::ADMIN_UNASSIGNED));
        $this->assertFalse($handler::stagesGrantEditorialAccess(self::SUB_EDITOR_ONLY_AUTHOR));
        $this->assertTrue($handler::stagesGrantEditorialAccess(self::SUB_EDITOR_ASSIGNED));
        $this->assertTrue($handler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_ASSISTANT]]));
        $this->assertFalse($handler::stagesGrantEditorialAccess([]));
    }

    public function testUpdateSubmissionStageDataRejectsManagerAssignedOnlyAsAuthor(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = self::MANAGER_ONLY_AUTHOR;

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            new FakeCsrfRequest(true)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testUpdateSubmissionStageDataRejectsServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new TestableModerationStagesHandler();
        $handler->userRoles = [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];
        $handler->accessibleWorkflowStages = self::SUB_EDITOR_ONLY_AUTHOR;

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            new FakeCsrfRequest(true)
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testStageDataUpdateRequiresUpdateRoleOnTheSubmission(): void
    {
        $roles = TestableModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;

        $handler = TestableModerationStagesHandler::class;

        $this->assertFalse($handler::stagesGrantAnyRole(self::MANAGER_ONLY_AUTHOR, $roles));
        $this->assertTrue($handler::stagesGrantAnyRole(self::MANAGER_AUTHOR_AND_MANAGER, $roles));
        $this->assertTrue($handler::stagesGrantAnyRole(self::MANAGER_AUTHOR_AND_MODERATOR, $roles));
        $this->assertTrue($handler::stagesGrantAnyRole(self::MANAGER_UNASSIGNED, $roles));
        $this->assertTrue($handler::stagesGrantAnyRole(self::ADMIN_UNASSIGNED, $roles));
        $this->assertFalse($handler::stagesGrantAnyRole(self::SUB_EDITOR_ONLY_AUTHOR, $roles));
        $this->assertTrue($handler::stagesGrantAnyRole(self::SUB_EDITOR_ASSIGNED, $roles));
        $this->assertFalse($handler::stagesGrantAnyRole([5 => [Role::ROLE_ID_ASSISTANT]], $roles));
    }
}
