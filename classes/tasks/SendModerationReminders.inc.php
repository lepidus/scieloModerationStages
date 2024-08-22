<?php

import('lib.pkp.classes.scheduledTask.ScheduledTask');

class SendReadyEndorsements extends ScheduledTask
{
    public function executeActions()
    {
        $context = Application::get()->getRequest()->getContext();
        $responsibleUsers = $this->getResponsibleUsers($context->getId());

        foreach ($responsibleUsers as $responsible) {
            //obter as submissões ao qual está designado como tal
            //para cada submissão, verificar se estourou o tempo limite
            //dadas as submissões que estouraram o tempo, montar um e-mail lembrete
            //enviar o e-mail
        }

        return true;
    }

    private function getResponsibleUsers(int $contextId)
    {
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $contextUserGroups = $userGroupDao->getByContextId($contextId);

        foreach ($contextUserGroups as $userGroup) {
            $userGroupAbbrev = $userGroupDao->getSetting($userGroup->getId(), 'abbrev', 'en_US');

            if ($userGroupAbbrev === 'resp') {
                $responsiblesUserGroup = $userGroup;
                break;
            }
        }

        if (!$responsiblesUserGroup) {
            return [];
        }

        return $userGroupDao->getUsersById($responsiblesUserGroup->getId());
    }
}
