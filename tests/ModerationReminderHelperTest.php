<?php

use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;
use PKP\security\Role;
use APP\facades\Repo;
use APP\plugins\generic\scieloModerationStages\classes\ModerationReminderHelper;

class ModerationReminderHelperTest extends DatabaseTestCase
{
    private $moderationReminderHelper;
    private $responsiblesUserGroup;
    private $contextId = 1;
    private $locale = 'en';

    public function setUp(): void
    {
        $this->moderationReminderHelper = new ModerationReminderHelper();
        $this->responsiblesUserGroup = $this->createResponsiblesUserGroup();
    }

    protected function tearDown(): void
    {
        Repo::userGroup()->delete($this->responsiblesUserGroup);
        parent::tearDown();
    }

    private function createResponsiblesUserGroup()
    {
        $responsiblesUserGroup = new UserGroup();
        $responsiblesUserGroup->setAllData([
            'contextId' => $this->contextId,
            'roleId' => Role::ROLE_ID_SUB_EDITOR,
            'isDefault' => true,
            'showTitle' => false,
            'permitSelfRegistration' => false,
            'permitMetadataEdit' => true,
            'abbrev' => [
                $this->locale => 'RESP'
            ]
        ]);

        $responsiblesUserGroupId = Repo::userGroup()->add($responsiblesUserGroup);
        $responsiblesUserGroup->setId($responsiblesUserGroupId);

        return $responsiblesUserGroup;
    }

    public function testGetResponsiblesUserGroup(): void
    {
        $retrievedUserGroup = $this->moderationReminderHelper->getResponsiblesUserGroup($this->contextId);

        $this->assertEquals($this->responsiblesUserGroup->getId(), $retrievedUserGroup->getId());
    }
}
