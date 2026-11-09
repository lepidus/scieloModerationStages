<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.security.Role');
import('lib.pkp.classes.services.interfaces.EntityWriteInterface');
import('classes.core.Services');
import('plugins.generic.scieloModerationStages.ScieloModerationStagesPlugin');

class ScieloModerationStagesPluginTest extends TestCase
{
    protected function tearDown(): void
    {
        HookRegistry::clear('Submission::validate');
        parent::tearDown();
    }

    public function testRejectsModerationPropsSubmittedForValidation(): void
    {
        $errors = [];
        $props = ['title' => ['en_US' => 'Title']];
        foreach (ScieloModerationStagesPlugin::API_WRITE_DISABLED_SUBMISSION_PROPS as $propName) {
            $props[$propName] = '2020-01-01';
        }
        $params = [&$errors, VALIDATE_ACTION_EDIT, $props, ['en_US'], 'en_US'];

        (new ScieloModerationStagesPlugin())->rejectApiWritesToModerationProps('Submission::validate', $params);

        $this->assertSame(ScieloModerationStagesPlugin::API_WRITE_DISABLED_SUBMISSION_PROPS, array_keys($errors));
    }

    public function testAcceptsSubmissionValidationWithoutModerationProps(): void
    {
        $errors = [];
        $params = [&$errors, VALIDATE_ACTION_EDIT, ['locale' => 'en_US'], ['en_US'], 'en_US'];

        (new ScieloModerationStagesPlugin())->rejectApiWritesToModerationProps('Submission::validate', $params);

        $this->assertSame([], $errors);
    }

    public function testCoreSubmissionValidationRejectsModerationPropsThroughTheHook(): void
    {
        HookRegistry::register('Submission::validate', [new ScieloModerationStagesPlugin(), 'rejectApiWritesToModerationProps']);

        $errors = Services::get('submission')->validate(
            VALIDATE_ACTION_EDIT,
            ['currentModerationStage' => '3', 'areaStageEntryDate' => '2020-01-01'],
            ['en_US'],
            'en_US'
        );

        $this->assertArrayHasKey('currentModerationStage', $errors);
        $this->assertArrayHasKey('areaStageEntryDate', $errors);
    }

    public function testWorkflowTabAllowsStageDataUpdateToManagerWhoIsAlsoAuthor(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_MANAGER, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
    }

    public function testWorkflowTabAllowsStageDataUpdateToSiteAdmin(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate([ROLE_ID_SITE_ADMIN], []));
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SITE_ADMIN, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
    }

    public function testWorkflowTabDeniesStageDataUpdateToSubEditorWhoIsOnlyAuthor(): void
    {
        $this->assertFalse(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_AUTHOR]]
        ));
        $this->assertFalse(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_ASSISTANT],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_ASSISTANT]]
        ));
    }

    public function testWorkflowTabAllowsStageDataUpdateToAssignedSubEditor(): void
    {
        $this->assertTrue(ScieloModerationStagesPlugin::rolesGrantStageDataUpdate(
            [ROLE_ID_SUB_EDITOR],
            [WORKFLOW_STAGE_ID_PRODUCTION => [ROLE_ID_SUB_EDITOR]]
        ));
    }
}
