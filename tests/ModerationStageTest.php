<?php

use PHPUnit\Framework\TestCase;
use APP\submission\Submission;
use PKP\core\Core;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;

class ModerationStageTest extends TestCase
{
    private $submission;
    private $moderationStage;

    public function setUp(): void
    {
        $this->submission = $this->createSubmission();
        $this->moderationStage = new ModerationStage($this->submission);
    }

    private function createSubmission(): Submission
    {
        $submission = new Submission();
        $submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_FORMAT);
        return $submission;
    }

    public function testGetCurrentStageName(): void
    {
        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.formatStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getCurrentStageName());

        $expectedStageLocaleKey = 'plugins.generic.scieloModerationStages.stages.formatStage';
        $this->assertEquals($expectedStageLocaleKey, $this->moderationStage->getCurrentStageName(false));
    }

    public function testGetNextStageName(): void
    {
        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.contentStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getNextStageName());
    }

    public function testSendNextStage(): void
    {
        $this->moderationStage->sendNextStage();

        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.contentStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getCurrentStageName());
    }

    public function testSubmissionDataNextStage(): void
    {
        $this->moderationStage->sendNextStage();

        $this->assertEquals(ModerationStage::SCIELO_MODERATION_STAGE_CONTENT, $this->submission->getData('currentModerationStage'));
        $this->assertEquals(Core::getCurrentDate(), $this->submission->getData('lastModerationStageChange'));
        $this->assertEquals(Core::getCurrentDate(), $this->submission->getData('contentStageEntryDate'));
    }

    public function testPutOnFirstStage(): void
    {
        $submission = new Submission();
        $moderationStage = new ModerationStage($submission);
        $moderationStage->setToFirstStage();

        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.formatStage');
        $this->assertEquals($expectedStageName, $moderationStage->getCurrentStageName());
    }

    public function testRejectedSubmissionCantAdvanceStage(): void
    {
        $this->submission->setData('status', Submission::STATUS_DECLINED);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testPostedSubmissionCantAdvanceStage(): void
    {
        $this->submission->setData('status', Submission::STATUS_PUBLISHED);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testSubmissionOnLastStageCantAdvance(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_AREA);
        $moderationStage = new ModerationStage($this->submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testSubmissionWithoutStageCantAdvance(): void
    {
        $submission = new Submission();
        $moderationStage = new ModerationStage($submission);
        $this->assertFalse($moderationStage->canAdvanceStage());
    }

    public function testGetPreviousStageName(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);

        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.formatStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getPreviousStageName());
    }

    public function testSendPreviousStage(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_AREA);
        $this->moderationStage->sendPreviousStage();

        $expectedStageName = __('plugins.generic.scieloModerationStages.stages.contentStage');
        $this->assertEquals($expectedStageName, $this->moderationStage->getCurrentStageName());
    }

    public function testSubmissionDataPreviousStage(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);
        $this->moderationStage->sendPreviousStage();

        $this->assertEquals(ModerationStage::SCIELO_MODERATION_STAGE_FORMAT, $this->submission->getData('currentModerationStage'));
        $this->assertEquals(Core::getCurrentDate(), $this->submission->getData('lastModerationStageChange'));
        $this->assertEquals(Core::getCurrentDate(), $this->submission->getData('formatStageEntryDate'));
    }

    public function testSubmissionOnIntermediateStageCanRegress(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);
        $this->assertTrue($this->moderationStage->canRegressStage());

        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_AREA);
        $this->assertTrue($this->moderationStage->canRegressStage());
    }

    public function testSubmissionOnFirstStageCantRegress(): void
    {
        $this->assertFalse($this->moderationStage->canRegressStage());
    }

    public function testSubmissionWithoutStageCantRegress(): void
    {
        $submission = new Submission();
        $moderationStage = new ModerationStage($submission);
        $this->assertFalse($moderationStage->canRegressStage());
    }

    public function testRejectedSubmissionCantRegressStage(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);
        $this->submission->setData('status', Submission::STATUS_DECLINED);
        $this->assertFalse($this->moderationStage->canRegressStage());
    }

    public function testPostedSubmissionCantRegressStage(): void
    {
        $this->submission->setData('currentModerationStage', ModerationStage::SCIELO_MODERATION_STAGE_CONTENT);
        $this->submission->setData('status', Submission::STATUS_PUBLISHED);
        $this->assertFalse($this->moderationStage->canRegressStage());
    }
}
