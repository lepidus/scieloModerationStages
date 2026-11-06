<?php

use PHPUnit\Framework\TestCase;
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
}
