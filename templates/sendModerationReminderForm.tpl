<script>
    $(function() {ldelim}
        $('#sendModerationReminderForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="sendModerationReminderForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="sendModerationReminder" save=true}">
    <div id="sendModerationReminder">
        {csrf}
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="sendModerationReminderFormNotification"}

        {fbvFormSection id="responsibleSection" label="plugins.generic.scieloModerationStages.sendModerationReminder.responsible.title"}
            {fbvElement type="select" id="responsible" name="responsible" from=$responsibles required="true" label="plugins.generic.scieloModerationStages.sendModerationReminder.responsible.description" size=$fbvStyles.size.MEDIUM}
        {/fbvFormSection}

        {fbvFormSection id="reminderBodySection" label="plugins.generic.scieloModerationStages.sendModerationReminder.reminderBody.title"}
			{fbvElement type="textarea" id="reminderBody" name="reminderBody" label="plugins.generic.scieloModerationStages.sendModerationReminder.reminderBody.description" rich=true value=$reminderBody}
		{/fbvFormSection}

        {fbvFormButtons submitText="plugins.generic.scieloModerationStages.send"}
        <p><span class="formRequired">{translate key="common.requiredField"}</span></p>
    </div>
</form>

