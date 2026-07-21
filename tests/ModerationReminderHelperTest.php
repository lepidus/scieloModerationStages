<?php

use PKP\tests\DatabaseTestCase;
use PKP\userGroup\UserGroup;
use PKP\security\Role;
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
        $this->responsiblesUserGroup->delete();
        parent::tearDown();
    }

    private function createResponsiblesUserGroup()
    {
        $responsiblesUserGroup = new UserGroup([
            'contextId' => $this->contextId,
            'roleId' => Role::ROLE_ID_SUB_EDITOR,
            'isDefault' => true,
            'showTitle' => false,
            'permitSelfRegistration' => false,
            'permitMetadataEdit' => true,
        ]);
        $responsiblesUserGroup->abbrev = [$this->locale => 'RESP'];
        $responsiblesUserGroup->save();

        return $responsiblesUserGroup;
    }

    public function testGetResponsiblesUserGroup(): void
    {
        $retrievedUserGroup = $this->moderationReminderHelper->getResponsiblesUserGroup($this->contextId);

        $this->assertNotNull($retrievedUserGroup);
        $this->assertEquals(
            'resp',
            strtolower($retrievedUserGroup->getLocalizedData('abbrev', 'en', UserGroup::LOCALE_MATCH_STRICT))
        );
    }
}
