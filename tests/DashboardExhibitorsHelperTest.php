<?php

use PHPUnit\Framework\TestCase;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableDashboardExhibitorsHelper;
use APP\plugins\generic\scieloModerationStages\ScieloModerationStagesPlugin;

class DashboardExhibitorsHelperTest extends TestCase
{
    private TestableDashboardExhibitorsHelper $helper;
    private int $submissionId = 1;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializePluginLocaleData();
        $this->helper = new TestableDashboardExhibitorsHelper();
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
}
