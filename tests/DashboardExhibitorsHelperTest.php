<?php

use PHPUnit\Framework\TestCase;
use APP\submission\Submission;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableDashboardExhibitorsHelper;
use APP\plugins\generic\scieloModerationStages\ScieloModerationStagesPlugin;

class DashboardExhibitorsHelperTest extends TestCase
{
    private TestableDashboardExhibitorsHelper $helper;
    private int $submissionId = 1;
    private Submission $submission;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializePluginLocaleData();
        $this->helper = new TestableDashboardExhibitorsHelper();
        $this->submission = new Submission();
    }

    private function initializePluginLocaleData(): void
    {
        $plugin = new ScieloModerationStagesPlugin();
        $plugin->pluginPath = 'plugins/generic/scieloModerationStages';
        $plugin->addLocaleData();
    }

    public function testGetSubmissionModerationStage(): void
    {
        $mockModerationStageDao = new class () {
            public function getSubmissionModerationStage(int $submissionId): int
            {
                return ModerationStage::SCIELO_MODERATION_STAGE_FORMAT;
            }
        };
        $this->helper->moderationStageDao = $mockModerationStageDao;

        $expectedModerationStageText = 'Moderation stage: Format Pre-Moderation';
        $moderationStageText = $this->helper->getSubmissionModerationStageText($this->submissionId);

        $this->assertEquals($expectedModerationStageText, $moderationStageText);
    }

    public function testGetsResponsiblesNames(): void
    {
        $this->helper->usersByGroup['resp'] = [
            'jorgeamado' => 'Jorge Amado'
        ];

        $expectedResponsiblesText = 'Responsible: Jorge Amado';
        $this->assertEquals($expectedResponsiblesText, $this->helper->getResponsiblesText($this->submissionId));

        $this->helper->usersByGroup['resp'] = [
            'jorgeamado' => 'Jorge Amado',
            'cchagas' => 'Carlos Chagas'
        ];

        $expectedResponsiblesText = 'Responsibles: Jorge Amado, Carlos Chagas';
        $this->assertEquals($expectedResponsiblesText, $this->helper->getResponsiblesText($this->submissionId));
    }

    public function testHelperIgnoresScieloUserWhenGettingResponsiblesNames(): void
    {
        $this->helper->usersByGroup['resp'] = [
            'stagima' => 'Jorge Amado',
            'cchagas' => 'Carlos Chagas',
            'scielo-brasil' => 'SciELO Brasil'
        ];

        $expectedResponsiblesText = 'Responsibles: Jorge Amado, Carlos Chagas';
        $this->assertEquals($expectedResponsiblesText, $this->helper->getResponsiblesText($this->submissionId));
    }

    public function testGetsEmptyTextWhenThereIsNoResponsibles(): void
    {
        $this->helper->usersByGroup['resp'] = [];

        $this->assertEquals('', $this->helper->getResponsiblesText($this->submissionId));
    }

    public function testGetsAreaModeratorsNames(): void
    {
        $this->helper->usersByGroup['am'] = [
            'vmoraes' => 'Vinicius de Moraes'
        ];

        $expectedModeratorsText = 'Area moderator: Vinicius de Moraes';
        $this->assertEquals($expectedModeratorsText, $this->helper->getAreaModeratorsText($this->submissionId));

        $this->helper->usersByGroup['am'] = [
            'vmoraes' => 'Vinicius de Moraes',
            'cbuarque' => 'Chico Buarque'
        ];

        $expectedModeratorsText = 'Area moderators: Vinicius de Moraes, Chico Buarque';
        $this->assertEquals($expectedModeratorsText, $this->helper->getAreaModeratorsText($this->submissionId));
    }

    public function testGetsEmptyTextWhenThereIsNoAreaModerators(): void
    {
        $this->helper->usersByGroup['am'] = [];

        $this->assertEquals('', $this->helper->getAreaModeratorsText($this->submissionId));
    }

    public function testTimeExhibitorsDataAdaptsToDaysPassed(): void
    {
        $this->helper->submissionFinalDate = ['currentDate', '2026-09-28'];

        $expectedExhibitorData = ['TimeSubmitted' => 'Submission made less than a day ago'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-28', 'TimeSubmitted');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);

        $expectedExhibitorData = ['TimeSubmitted' => 'Submission made 2 days ago'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-26', 'TimeSubmitted');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);

        $expectedExhibitorData = ['TimeSubmitted' => 'Submission made 8 days ago', 'TimeSubmittedRedFlag' => true];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-20', 'TimeSubmitted');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);
    }

    public function testTimeExhibitorsDataAdaptsToDifferentFinalDates(): void
    {
        $this->helper->submissionFinalDate = ['dateDeclined', '2026-09-28'];
        $expectedExhibitorData = ['TimeSubmitted' => 'Submission made 2 days before rejection'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-26', 'TimeSubmitted');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);

        $this->helper->submissionFinalDate = ['datePublished', '2026-09-28'];
        $expectedExhibitorData = ['TimeSubmitted' => 'Submission made 2 days before posting'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-26', 'TimeSubmitted');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);
    }

    public function testTimeExhibitorsDataAdaptsToDifferentExhibitors(): void
    {
        $this->helper->submissionFinalDate = ['datePublished', '2026-09-28'];

        $expectedExhibitorData = ['TimeResponsible' => 'Responsible assigned 2 days before posting'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-26', 'TimeResponsible');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);

        $expectedExhibitorData = ['TimeAreaModerator' => 'Area moderator assigned 2 days before posting'];
        $exhibitorData = $this->helper->getDataForTimeExhibitor($this->submission, '2026-09-26', 'TimeAreaModerator');
        $this->assertEquals($expectedExhibitorData, $exhibitorData);
    }
}
