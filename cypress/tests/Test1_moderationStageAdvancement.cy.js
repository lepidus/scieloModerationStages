import '../support/commands.js';

function openSubmissionAsEditor(title) {
    cy.visit('index.php/publicknowledge/dashboard/editorial');
    cy.findSubmission('active', title);
}

function openAssignParticipantForm() {
    cy.get('[data-cy="participant-manager"] button:contains("Assign")').first().click();
    cy.waitJQuery();
    cy.get('#addParticipantForm', { timeout: 20000 }).should('be.visible');
}

function checkSendNextStageOptionIsPresent(currentStage, nextStage) {
    cy.get('#addParticipantForm').within(() => {
        cy.contains(`This submission is in the ${currentStage} stage, do you want to send it to the ${nextStage} stage?`);
        cy.get('#checkboxSendNextStageAssignYes').parent().contains('Yes');
        cy.get('#checkboxSendNextStageAssignNo').parent().contains('No');
        cy.get('#checkboxSendNextStageAssignYes').should('not.be.checked');
        cy.get('#checkboxSendNextStageAssignNo').should('not.be.checked');
    });
}

function assignParticipantAndAdvanceStage() {
    cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
    cy.get('#checkboxSendNextStageAssignYes').click();
    cy.get('#addParticipantForm > .formButtons > .submitFormButton').click();
    cy.waitJQuery();
}

describe('SciELO Moderation Stages - Moderation stage advancement', function () {
    let submissionData;

    before(function () {
        Cypress.config('defaultCommandTimeout', 10000);
        submissionData = {
            title: 'Night of the Living Dead',
            abstract: 'Some people get stuck in a house when the dead arise from their graves',
            keywords: ['plugin', 'testing'],
            contributors: [
                {
                    'given': 'George',
                    'family': 'Romero',
                    'email': 'george.romero@stab.com',
                    'country': 'United States'
                }
            ],
            files: [
                {
                    'file': 'dummy.pdf',
                    'fileName': 'dummy.pdf',
                    'mimeType': 'application/pdf',
                    'genre': 'Preprint Text'
                }
            ]
        };
    });

    it('Author creates submission. Asserts submission goes to Format Pre-Moderation stage', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.createSubmission(submissionData);
        cy.contains('The moderation of your submission has been initiated and it has been forwarded to the Format Pre-Moderation stage, where it will undergo a screening process');
        cy.contains('Please wait for a response from the editorial team or an update on the status of your submission');
    });
    it('Checks submission is set to first moderation stage', function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.get('[data-cy="active-modal"]').within(() => {
            cy.contains('strong', 'Moderation stage:');
            cy.contains('Format Pre-Moderation');
        });
    });
    it('Checks sending of submission to next moderation stage', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.get('[data-cy="active-modal"]').within(() => {
            cy.contains('strong', 'Moderation stage:');
            cy.contains('Format Pre-Moderation');
        });

        openAssignParticipantForm();
        checkSendNextStageOptionIsPresent('Format Pre-Moderation', 'Manuscript Type Pre-Moderation');
        assignParticipantAndAdvanceStage();

        openSubmissionAsEditor(submissionData.title);
        cy.get('[data-cy="active-modal"]').contains('Manuscript Type Pre-Moderation');

        cy.get('[data-cy="active-modal"]').contains('button', 'Activity Log').click();
        cy.contains('The submission has been sent to the Manuscript Type Pre-Moderation stage');
    });
    it('Checks sending of email notification after advancing moderation stage', function () {
        cy.visit('http://127.0.0.1:8025');
        cy.contains('b', 'Advancement in Submission Moderation')
            .first()
            .parent().parent().parent()
            .within(() => {
                cy.contains('fpaglieri@mailinator.com');
            });
        cy.contains('b', 'Advancement in Submission Moderation').first().click();
        cy.get('#nav-tab button:contains("Text")').click();

        cy.contains('Your submission has been forwarded to the Manuscript Type Pre-Moderation stage');
        cy.contains('To facilitate moderation, please provide an updated ORCID with the most recent scientific output for at least one of the authors registered in the submission');
        cy.contains('Optionally, you may also provide an endorsement for the preprint, if you have one');
        cy.contains('For more information, we recommend reading our FAQs #10 and #19');
    });
    it('Checks stage advancing not present in last stage', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        openSubmissionAsEditor(submissionData.title);

        openAssignParticipantForm();
        assignParticipantAndAdvanceStage();

        openSubmissionAsEditor(submissionData.title);
        cy.get('[data-cy="active-modal"]').contains('Area Moderation');

        openAssignParticipantForm();
        cy.get('#addParticipantForm').within(() => {
            cy.get('#checkboxSendNextStageAssignYes').should('not.exist');
            cy.get('#checkboxSendNextStageAssignNo').should('not.exist');
        });
    });
});
