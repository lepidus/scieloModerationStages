<script>
    $(function() {ldelim}
        $('#scieloModerationStagesSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="scieloModerationStagesSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
    <div id="scieloModerationStagesSettings">
        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="scieloModerationStagesSettingsFormNotification"}
        {fbvFormArea id="orcidApiSettings"}
            {fbvFormSection id="timeLimitsSection" label="plugins.generic.scieloModerationStages.settings.preModerationReminder.label"}
                <p style="margin-top: 0;">
                    {translate key="plugins.generic.scieloModerationStages.settings.preModerationReminder.description"}
                </p>

                {fbvElement type="text" id="preModerationTimeLimit" class="preModerationTimeLimit" value=$preModerationTimeLimit required="true" label="plugins.generic.scieloModerationStages.settings.preModerationReminder.hint" size=$fbvStyles.size.SMALL}
            {/fbvFormSection}

            <p style="margin-bottom: 0;">
                {translate key="plugins.generic.scieloModerationStages.settings.remindersDayOfWeek"}
            </p>
        {/fbvFormArea}
        {fbvFormButtons}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    </div>
</form>

