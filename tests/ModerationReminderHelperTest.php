<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.user.User');
import('lib.pkp.classes.user.UserDAO');
import('lib.pkp.classes.stageAssignment.StageAssignment');
import('plugins.generic.scieloModerationStages.classes.ModerationStage');
import('plugins.generic.scieloModerationStages.classes.ModerationStageDAO');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class ModerationReminderHelperTest extends TestCase
{
    private $moderationReminderHelper;
    private $moderatorUsers;
    private $assignments;
    private $locale = 'en_US';

    public function setUp(): void
    {
        $this->moderationReminderHelper = new ModerationReminderHelper();
        $this->moderatorUsers = $this->createTestModeratorUsers();
        $this->assignments = $this->createTestAssignments();
    }

    protected function getMockedDAOs()
    {
        return ['UserDAO'];
    }

    private function createTestModeratorUsers(): array
    {
        $firstModerator = new User();
        $firstModerator->setData('id', 312);
        $firstModerator->setGivenName('Edgar', $this->locale);
        $firstModerator->setFamilyName('Linton', $this->locale);

        $secondModerator = new User();
        $secondModerator->setData('id', 313);
        $secondModerator->setGivenName('Catherine', $this->locale);
        $secondModerator->setFamilyName('Earnshaw', $this->locale);

        return [$firstModerator, $secondModerator];
    }

    private function createTestAssignments(): array
    {
        $firstAssignment = new StageAssignment();
        $firstAssignment->setData('submissionId', 256);
        $firstAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $firstAssignment->setData('userId', $this->moderatorUsers[0]->getId());

        $secondAssignment = new StageAssignment();
        $secondAssignment->setData('submissionId', 257);
        $secondAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $secondAssignment->setData('userId', $this->moderatorUsers[0]->getId());

        $thirdAssignment = new StageAssignment();
        $thirdAssignment->setData('submissionId', 258);
        $thirdAssignment->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $thirdAssignment->setData('userId', $this->moderatorUsers[1]->getId());

        return [$firstAssignment, $secondAssignment, $thirdAssignment];
    }

    private function createModerationStageDaoMock()
    {
        $mockedDAO = $this->createMock(ModerationStageDAO::class);
        $mockedDAO->method('getSubmissionModerationStage')->willReturnMap([
            [256, SCIELO_MODERATION_STAGE_CONTENT],
            [257, SCIELO_MODERATION_STAGE_FORMAT],
            [258, SCIELO_MODERATION_STAGE_CONTENT]
        ]);

        return $mockedDAO;
    }

    private function registerUserDaoMock()
    {
        $mockedUserDao = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById'])
            ->getMock();
        $mockedUserDao->expects($this->any())
            ->method('getById')
            ->will($this->onConsecutiveCalls($this->moderatorUsers[0], $this->moderatorUsers[1]));

        DAORegistry::registerDAO('UserDAO', $mockedUserDao);
    }

    public function testFilterAssignmentsOfSubmissionsOnPreModeration(): void
    {
        $mockedModerationStageDao = $this->createModerationStageDaoMock();
        $this->moderationReminderHelper->setModerationStageDao($mockedModerationStageDao);

        $expectedFilteredAssignments = [$this->assignments[0], $this->assignments[2]];
        $filteredAssignments = $this->moderationReminderHelper->filterAssignmentsOfSubmissionsOnPreModeration($this->assignments);

        $this->assertEquals($expectedFilteredAssignments, $filteredAssignments);
    }

    public function testGetUsersFromAssignments(): void
    {
        $this->registerUserDaoMock();

        $expectedAssignedUsers = [
            $this->moderatorUsers[1]->getId() => $this->moderatorUsers[1],
            $this->moderatorUsers[0]->getId() => $this->moderatorUsers[0],
        ];
        $usersFromAssignments = $this->moderationReminderHelper->getUsersFromAssignments($this->assignments);

        $this->assertEquals($expectedAssignedUsers, $usersFromAssignments);
    }
}
