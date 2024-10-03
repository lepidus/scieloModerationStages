<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.stageAssignment.StageAssignment');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class ModerationReminderHelperTest extends TestCase
{
    private $moderationReminderHelper;

    public function setUp(): void
    {
        $this->moderationReminderHelper = new ModerationReminderHelper();
    }

    private function createTestAssignments(): array
    {
        $firstAssignment = new StageAssignment();
        $firstAssignment->setData('submissionId', 256);
        $firstAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        $secondAssignment = new StageAssignment();
        $secondAssignment->setData('submissionId', 257);
        $secondAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        $thirdAssignment = new StageAssignment();
        $thirdAssignment->setData('submissionId', 258);
        $thirdAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        return [$firstAssignment, $secondAssignment, $thirdAssignment];
    }

    public function testFilterPreModerationAssignments(): void
    {
        $assignments = $this->createTestAssignments();
        $mockedModerationStageDao = null;
        $this->moderationReminderHelper->setModerationStageDao($mockedModerationStageDao);

        $expectedFilteredAssignments = [];
        $filteredAssignments = $this->moderationReminderHelper->filterPreModerationAssignments($assignments);

        $this->assertEquals($expectedFilteredAssignments, $filteredAssignments);
    }
}
