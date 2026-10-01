<?php

use PHPUnit\Framework\TestCase;
use APP\submission\Submission;
use PKP\security\Role;
use APP\plugins\generic\scieloModerationStages\classes\ModerationStage;
use APP\plugins\generic\scieloModerationStages\tests\helpers\TestableDashboardExhibitorsHelper;
use APP\plugins\generic\scieloModerationStages\ScieloModerationStagesPlugin;

class DashboardExhibitorsHelperTest extends TestCase
{
    private TestableDashboardExhibitorsHelper $helper;
    private int $userId = 33;
    private int $contextId = 2;
    private int $submissionId = 1;
    private Submission $submission;

    public function setUp(): void
    {
        parent::setUp();
        $this->initializePluginLocaleData();
        $this->helper = new TestableDashboardExhibitorsHelper();
        $this->submission = new Submission();
        $this->submission->setData('id', $this->submissionId);
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

        $expectedModerationStageData = ['ModerationStage' => 'Moderation stage: Format Pre-Moderation'];
        $moderationStageData = $this->helper->getSubmissionModerationStageData($this->submissionId);

        $this->assertEquals($expectedModerationStageData, $moderationStageData);
    }

    public function testGetsResponsiblesNames(): void
    {
        $this->helper->usersByGroup['resp'] = [
            'jorgeamado' => 'Jorge Amado'
        ];

        $expectedResponsiblesData = ['Responsibles' => 'Responsible: Jorge Amado'];
        $responsiblesData = $this->helper->getResponsiblesData($this->submissionId);
        $this->assertEquals($expectedResponsiblesData, $responsiblesData);

        $this->helper->usersByGroup['resp'] = [
            'jorgeamado' => 'Jorge Amado',
            'cchagas' => 'Carlos Chagas'
        ];

        $expectedResponsiblesData = ['Responsibles' => 'Responsibles: Jorge Amado, Carlos Chagas'];
        $responsiblesData = $this->helper->getResponsiblesData($this->submissionId);
        $this->assertEquals($expectedResponsiblesData, $responsiblesData);
    }

    public function testHelperIgnoresScieloUserWhenGettingResponsiblesNames(): void
    {
        $this->helper->usersByGroup['resp'] = [
            'stagima' => 'Jorge Amado',
            'cchagas' => 'Carlos Chagas',
            'scielo-brasil' => 'SciELO Brasil'
        ];

        $expectedResponsiblesData = ['Responsibles' => 'Responsibles: Jorge Amado, Carlos Chagas'];
        $this->assertEquals($expectedResponsiblesData, $this->helper->getResponsiblesData($this->submissionId));
    }

    public function testGetsEmptyTextWhenThereIsNoResponsibles(): void
    {
        $this->helper->usersByGroup['resp'] = [];

        $this->assertEquals([], $this->helper->getResponsiblesData($this->submissionId));
    }

    public function testGetsAreaModeratorsNames(): void
    {
        $this->helper->usersByGroup['am'] = [
            'vmoraes' => 'Vinicius de Moraes'
        ];

        $expectedModeratorsData = ['AreaModerators' => 'Area moderator: Vinicius de Moraes'];
        $this->assertEquals($expectedModeratorsData, $this->helper->getAreaModeratorsData($this->submissionId));

        $this->helper->usersByGroup['am'] = [
            'vmoraes' => 'Vinicius de Moraes',
            'cbuarque' => 'Chico Buarque'
        ];

        $expectedModeratorsData = ['AreaModerators' => 'Area moderators: Vinicius de Moraes, Chico Buarque'];
        $this->assertEquals($expectedModeratorsData, $this->helper->getAreaModeratorsData($this->submissionId));
    }

    public function testGetsEmptyTextWhenThereIsNoAreaModerators(): void
    {
        $this->helper->usersByGroup['am'] = [];

        $this->assertEquals([], $this->helper->getAreaModeratorsData($this->submissionId));
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

    public function testGetsDateSubmittedData(): void
    {
        $this->submission->setData('dateSubmitted', '2026-09-26');
        $this->helper->submissionFinalDate = ['datePublished', '2026-09-28'];

        $expectedDateSubmittedData = ['TimeSubmitted' => 'Submission made 2 days before posting'];
        $dateSubmittedData = $this->helper->getTimeSubmittedData($this->submission);

        $this->assertEquals($expectedDateSubmittedData, $dateSubmittedData);
    }

    public function testGetsEmptyTextWhenThereIsNoDateSubmitted(): void
    {
        $this->helper->submissionFinalDate = ['currentDate', '2026-09-28'];

        $expectedDateSubmittedData = ['TimeSubmitted' => ''];
        $dateSubmittedData = $this->helper->getTimeSubmittedData($this->submission);

        $this->assertEquals($expectedDateSubmittedData, $dateSubmittedData);
    }

    public function testGetsTimeResponsibleData(): void
    {
        $this->helper->lastAssignmentDateByGroup['resp'] = '2026-09-26';
        $this->helper->submissionFinalDate = ['datePublished', '2026-09-28'];

        $expectedTimeResponsibleData = ['TimeResponsible' => 'Responsible assigned 2 days before posting'];
        $timeResponsibleData = $this->helper->getTimeResponsibleData($this->submission);

        $this->assertEquals($expectedTimeResponsibleData, $timeResponsibleData);
    }

    public function testGetsEmptyTextWhenThereIsNoResponsible(): void
    {
        $this->helper->submissionFinalDate = ['currentDate', '2026-09-28'];

        $expectedTimeResponsibleData = ['TimeResponsible' => ''];
        $timeResponsibleData = $this->helper->getTimeResponsibleData($this->submission);

        $this->assertEquals($expectedTimeResponsibleData, $timeResponsibleData);
    }

    public function testGetsTimeAreaModeratorData(): void
    {
        $this->helper->lastAssignmentDateByGroup['am'] = '2026-09-26';
        $this->helper->submissionFinalDate = ['datePublished', '2026-09-28'];

        $expectedTimeModeratorData = ['TimeAreaModerator' => 'Area moderator assigned 2 days before posting'];
        $timeModeratorData = $this->helper->getTimeAreaModeratorData($this->submission);

        $this->assertEquals($expectedTimeModeratorData, $timeModeratorData);
    }

    public function testGetsEmptyTextWhenThereIsNoAreaModerator(): void
    {
        $this->helper->submissionFinalDate = ['currentDate', '2026-09-28'];

        $expectedTimeModeratorData = ['TimeAreaModerator' => ''];
        $timeModeratorData = $this->helper->getTimeAreaModeratorData($this->submission);

        $this->assertEquals($expectedTimeModeratorData, $timeModeratorData);
    }

    public function testGetsUserMainUserGroupForManagers()
    {
        $this->helper->userUserGroups = [
            Role::ROLE_ID_MANAGER => [
                1 => 'psm',
                2 => 'je'
            ],
            Role::ROLE_ID_AUTHOR => [
                5 => 'au'
            ]
        ];

        $expectedUserMainGroup = ['role' => Role::ROLE_ID_MANAGER, 'abbrev' => 'psm'];
        $userMainGroup = $this->helper->getUserMainUserGroup($this->userId, $this->contextId);
        $this->assertEquals($expectedUserMainGroup, $userMainGroup);

        unset($this->helper->userUserGroups[Role::ROLE_ID_MANAGER]);

        $expectedUserMainGroup = ['role' => Role::ROLE_ID_AUTHOR, 'abbrev' => 'au'];
        $userMainGroup = $this->helper->getUserMainUserGroup($this->userId, $this->contextId);
        $this->assertEquals($expectedUserMainGroup, $userMainGroup);
    }

    public function testGetsUserMainUserGroupForEditors()
    {
        $this->helper->userUserGroups = [
            Role::ROLE_ID_SUB_EDITOR => [
                2 => 'ed',
                3 => 'resp',
                4 => 'am',
            ],
            Role::ROLE_ID_AUTHOR => [
                5 => 'au',
            ]
        ];

        $expectedUserMainGroup = ['role' => Role::ROLE_ID_SUB_EDITOR, 'abbrev' => 'resp'];
        $userMainGroup = $this->helper->getUserMainUserGroup($this->userId, $this->contextId);
        $this->assertEquals($expectedUserMainGroup, $userMainGroup);

        unset($this->helper->userUserGroups[Role::ROLE_ID_SUB_EDITOR][3]);
        $expectedUserMainGroup = ['role' => Role::ROLE_ID_SUB_EDITOR, 'abbrev' => 'am'];
        $userMainGroup = $this->helper->getUserMainUserGroup($this->userId, $this->contextId);
        $this->assertEquals($expectedUserMainGroup, $userMainGroup);

        unset($this->helper->userUserGroups[Role::ROLE_ID_SUB_EDITOR][4]);
        $expectedUserMainGroup = ['role' => Role::ROLE_ID_SUB_EDITOR, 'abbrev' => 'ed'];
        $userMainGroup = $this->helper->getUserMainUserGroup($this->userId, $this->contextId);
        $this->assertEquals($expectedUserMainGroup, $userMainGroup);
    }

    private function setBaseDataForExhibitorsTests()
    {
        $mockModerationStageDao = new class () {
            public function getSubmissionModerationStage(int $submissionId): int
            {
                return ModerationStage::SCIELO_MODERATION_STAGE_FORMAT;
            }
        };
        $this->helper->moderationStageDao = $mockModerationStageDao;
        $this->submission->setData('dateSubmitted', '2026-09-26');
        $this->helper->usersByGroup = [
            'resp' => ['cchagas' => 'Carlos Chagas'],
            'am' => ['vmoraes' => 'Vinicius de Moraes'],
        ];
        $this->helper->lastAssignmentDateByGroup = ['resp' => '2026-09-25', 'am' => '2026-09-26'];
        $this->helper->submissionFinalDate = ['currentDate', '2026-09-28'];
    }

    public function testGetsExhibitorsDataForManagers()
    {
        $this->setBaseDataForExhibitorsTests();
        $this->helper->userUserGroups = [
            Role::ROLE_ID_MANAGER => [
                1 => 'psm',
            ]
        ];

        $expectedExhibitorsData = [
            'submissionId' => $this->submissionId,
            'ModerationStage' => 'Moderation stage: Format Pre-Moderation',
            'TimeSubmitted' => 'Submission made 2 days ago',
            'ExhibitorsSeparator0' => '--',
            'Responsibles' => 'Responsible: Carlos Chagas',
            'TimeResponsible' => 'Responsible assigned 3 days ago',
            'TimeResponsibleRedFlag' => true,
            'ExhibitorsSeparator1' => '--',
            'AreaModerators' => 'Area moderator: Vinicius de Moraes',
            'TimeAreaModerator' => 'Area moderator assigned 2 days ago'
        ];
        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEquals($expectedExhibitorsData, $exhibitorsData);
    }

    public function testGetsExhibitorsDataForResponsibles()
    {
        $this->setBaseDataForExhibitorsTests();
        $this->helper->userUserGroups = [
            Role::ROLE_ID_SUB_EDITOR => [
                1 => 'resp',
            ]
        ];

        $expectedExhibitorsData = [
            'submissionId' => $this->submissionId,
            'ModerationStage' => 'Moderation stage: Format Pre-Moderation',
            'ExhibitorsSeparator1' => '--',
            'AreaModerators' => 'Area moderator: Vinicius de Moraes',
            'TimeAreaModerator' => 'Area moderator assigned 2 days ago'
        ];
        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEquals($expectedExhibitorsData, $exhibitorsData);
    }

    public function testGetsExhibitorsDataForAreaModerators()
    {
        $this->setBaseDataForExhibitorsTests();
        $this->helper->userUserGroups = [
            Role::ROLE_ID_SUB_EDITOR => [
                1 => 'am',
            ]
        ];

        $expectedExhibitorsData = [
            'submissionId' => $this->submissionId,
            'ModerationStage' => 'Moderation stage: Format Pre-Moderation'
        ];
        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEquals($expectedExhibitorsData, $exhibitorsData);
    }

    public function testGetsExhibitorsDataForAuthors()
    {
        $this->setBaseDataForExhibitorsTests();
        $this->helper->userUserGroups = [
            Role::ROLE_ID_AUTHOR => [
                1 => 'au',
            ]
        ];

        $expectedExhibitorsData = [
            'submissionId' => $this->submissionId,
            'ModerationStage' => 'Moderation stage: Format Pre-Moderation'
        ];
        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEquals($expectedExhibitorsData, $exhibitorsData);
    }

    public function testGetsExhibitorsDataForReadersOrUsersOutsideOfContext()
    {
        $this->setBaseDataForExhibitorsTests();
        $this->helper->userUserGroups = [
            Role::ROLE_ID_READER => [
                1 => 'read',
            ]
        ];

        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEmpty($exhibitorsData);

        $this->helper->userUserGroups = [];
        $exhibitorsData = $this->helper->getExhibitorsData($this->submission, $this->userId, $this->contextId);
        $this->assertEmpty($exhibitorsData);
    }
}
