<?php

use PHPUnit\Framework\TestCase;
use APP\submission\Submission;
use PKP\core\JSONMessage;
use PKP\security\Role;

require_once __DIR__ . '/../controllers/ScieloModerationStagesHandler.php';

class ScieloModerationStagesHandlerTest extends TestCase
{
    public function testEditorialAccessIsDeniedToUserWhoIsOnlyAuthorOfSubmission(): void
    {
        $roles = ScieloModerationStagesHandler::EDITORIAL_ROLES;

        $this->assertFalse(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [Role::ROLE_ID_SUB_EDITOR],
            [5 => [Role::ROLE_ID_AUTHOR]],
            $roles
        ));
        $this->assertFalse(ScieloModerationStagesHandler::rolesGrantSubmissionAccess([Role::ROLE_ID_SUB_EDITOR], [], $roles));
    }

    public function testEditorialAccessIsGrantedByEditorialRolesOnSubmission(): void
    {
        foreach (ScieloModerationStagesHandler::EDITORIAL_ROLES as $roleId) {
            $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
                [$roleId],
                [5 => [$roleId]],
                ScieloModerationStagesHandler::EDITORIAL_ROLES
            ));
        }
        $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR],
            [5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR]],
            ScieloModerationStagesHandler::EDITORIAL_ROLES
        ));
    }

    public function testManagerWhoIsAlsoAuthorOfSubmissionKeepsEditorialAccess(): void
    {
        foreach ([ScieloModerationStagesHandler::EDITORIAL_ROLES, ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES] as $roles) {
            $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
                [Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR],
                [5 => [Role::ROLE_ID_AUTHOR]],
                $roles
            ));
        }
    }

    public function testSiteAdminKeepsEditorialAccess(): void
    {
        foreach ([ScieloModerationStagesHandler::EDITORIAL_ROLES, ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES] as $roles) {
            $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess([Role::ROLE_ID_SITE_ADMIN], [], $roles));
            $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
                [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR],
                [5 => [Role::ROLE_ID_AUTHOR]],
                $roles
            ));
        }
    }

    public function testUpdateSubmissionStageDataRejectsServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new class () extends ScieloModerationStagesHandler {
            public function getSubmission()
            {
                return new Submission();
            }

            protected function currentUserRoles(): array
            {
                return [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR];
            }

            protected function getAccessibleWorkflowStages($submission): array
            {
                return [5 => [Role::ROLE_ID_AUTHOR]];
            }
        };
        $request = new class () {
            public function checkCSRF(): bool
            {
                return true;
            }
        };

        $response = $handler->updateSubmissionStageData(
            ['submissionId' => 1, 'stageChangeAction' => 'advance', 'formatStageEntryDate' => '2020-01-01'],
            $request
        );

        $this->assertInstanceOf(JSONMessage::class, $response);
        $this->assertFalse($response->getStatus());
    }

    public function testStageDataUpdateRequiresUpdateRoleOnTheSubmission(): void
    {
        $roles = ScieloModerationStagesHandler::STAGE_DATA_UPDATE_ROLES;

        $this->assertFalse(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [Role::ROLE_ID_SUB_EDITOR],
            [5 => [Role::ROLE_ID_AUTHOR]],
            $roles
        ));
        $this->assertFalse(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [Role::ROLE_ID_ASSISTANT],
            [5 => [Role::ROLE_ID_ASSISTANT]],
            $roles
        ));
        $this->assertTrue(ScieloModerationStagesHandler::rolesGrantSubmissionAccess(
            [Role::ROLE_ID_SUB_EDITOR],
            [5 => [Role::ROLE_ID_SUB_EDITOR]],
            $roles
        ));
    }
}
