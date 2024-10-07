<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.db.DAOResultFactory');
import('lib.pkp.classes.security.UserGroup');
import('plugins.generic.scieloModerationStages.classes.ModerationReminderHelper');

class ModerationReminderHelperTest extends TestCase
{
    private $moderationReminderHelper;
    private $userGroups;
    private $locale = 'en_US';

    public function setUp(): void
    {
        $this->moderationReminderHelper = new ModerationReminderHelper();
        $this->userGroups = $this->createTestUserGroups();
    }

    protected function getMockedDAOs()
    {
        return ['UserGroupDAO', 'SubmissionDAO'];
    }

    private function createTestUserGroups(): array
    {
        $firstUserGroup = new UserGroup();
        $firstUserGroup->setData('id', 12);
        $firstUserGroup->setData('abbrev', 'SciELO', $this->locale);

        $secondUserGroup = new UserGroup();
        $secondUserGroup->setData('id', 13);
        $secondUserGroup->setData('abbrev', 'RESP', $this->locale);

        return [$firstUserGroup, $secondUserGroup];
    }

    private function registerUserGroupDaoMock()
    {
        $mockDaoResultFactory = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['toArray'])
            ->getMock();
        $mockDaoResultFactory->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue($this->userGroups));

        $mockedUserGroupDao = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getByContextId', 'getSetting'])
            ->getMock();
        $mockedUserGroupDao->expects($this->any())
            ->method('getByContextId')
            ->will($this->returnValue($mockDaoResultFactory));
        $mockedUserGroupDao->expects($this->any())
            ->method('getSetting')
            ->will($this->onConsecutiveCalls(
                $this->userGroups[0]->getData('abbrev', $this->locale),
                $this->userGroups[1]->getData('abbrev', $this->locale)
            ));

        DAORegistry::registerDAO('UserGroupDAO', $mockedUserGroupDao);
    }

    public function testGetResponsiblesUserGroup(): void
    {
        $this->registerUserGroupDaoMock();
        $contextId = 1;
        $responsiblesUserGroup = $this->moderationReminderHelper->getResponsiblesUserGroup($contextId);

        $this->assertEquals($this->userGroups[1]->getId(), $responsiblesUserGroup->getId());
    }
}
