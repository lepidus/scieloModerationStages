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
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantEditorialAccess([5 => [Role::ROLE_ID_AUTHOR]]));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantEditorialAccess([]));
    }

    public function testEditorialAccessIsGrantedByEditorialRolesOnSubmission(): void
    {
        foreach (ScieloModerationStagesHandler::EDITORIAL_ROLES as $roleId) {
            $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess([5 => [$roleId]]));
        }
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantEditorialAccess([
            5 => [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR],
        ]));
    }

    public function testUpdateSubmissionStageDataRejectsServerEditorWhoIsOnlyAuthorOfSubmission(): void
    {
        $handler = new class () extends ScieloModerationStagesHandler {
            public function getSubmission()
            {
                return new Submission();
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

        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_AUTHOR]], $roles));
        $this->assertFalse(ScieloModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_ASSISTANT]], $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_SUB_EDITOR]], $roles));
        $this->assertTrue(ScieloModerationStagesHandler::stagesGrantAnyRole([5 => [Role::ROLE_ID_MANAGER]], $roles));
    }
}
