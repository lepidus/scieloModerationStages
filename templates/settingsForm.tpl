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
            {fbvFormSection id="timeLimitsSection" label="plugins.generic.scieloModerationStages.settings.timeLimitsModerationReminder.label"}
                <p style="margin-top: 0;">
                    {translate key="plugins.generic.scieloModerationStages.settings.timeLimitsModerationReminder.description"}
                </p>

                {fbvElement type="text" id="preModerationTimeLimit" class="preModerationTimeLimit" value=$preModerationTimeLimit required="true" label="plugins.generic.scieloModerationStages.settings.preModerationTimeLimit" size=$fbvStyles.size.MEDIUM}
            {/fbvFormSection}
        {/fbvFormArea}
        {fbvFormButtons}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    </div>
</form>

