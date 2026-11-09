<?php

use PHPUnit\Framework\TestCase;

import('lib.pkp.classes.form.Form');
import('plugins.generic.scieloModerationStages.form.ScieloModerationStagesSettingsForm');
import('plugins.generic.scieloModerationStages.form.SendModerationReminderForm');

class ModerationStagesFormsCsrfTest extends TestCase
{
    private function createPluginStub()
    {
        return new class () {
            public function getTemplateResource($template)
            {
                return $template;
            }
        };
    }

    private function formHasCheck(Form $form, string $checkClass): bool
    {
        foreach ($form->_checks as $check) {
            if ($check instanceof $checkClass) {
                return true;
            }
        }

        return false;
    }

    public function testSettingsFormValidatesCsrfToken(): void
    {
        $form = new ScieloModerationStagesSettingsForm($this->createPluginStub(), 1);

        $this->assertTrue($this->formHasCheck($form, FormValidatorCSRF::class));
        $this->assertTrue($this->formHasCheck($form, FormValidatorPost::class));
    }

    public function testSendModerationReminderFormValidatesCsrfToken(): void
    {
        $form = new SendModerationReminderForm($this->createPluginStub(), 1);

        $this->assertTrue($this->formHasCheck($form, FormValidatorCSRF::class));
        $this->assertTrue($this->formHasCheck($form, FormValidatorPost::class));
    }
}
