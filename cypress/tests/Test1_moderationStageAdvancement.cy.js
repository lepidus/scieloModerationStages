import '../support/commands.js';

function checkSendNextStageOptionIsPresent() {
    cy.contains("This submission is in the Format Pre-Moderation stage, do you want to send it to the Manuscript Type Pre-Moderation stage? ");
    cy.get('#checkboxSendNextStageAssignYes').parent().contains("Yes");
    cy.get('#checkboxSendNextStageAssignNo').parent().contains("No");
    cy.get('#checkboxSendNextStageAssignYes').should('not.be.checked');
    cy.get('#checkboxSendNextStageAssignNo').should('not.be.checked');
}

describe("SciELO Moderation Stages - Moderation stage advancement", function() {
    let submissionData;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        submissionData = {
            title: "Night of the Living Dead",
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
    
    it("Author creates submission", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.createSubmission(submissionData);
    });
    it("Checks submission is set to first moderation stage", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.contains('strong', 'Moderation stage:');
        cy.contains('span', 'Format Pre-Moderation');
    });
    it("Checks sending of submission to next moderation stage", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.contains('strong', 'Moderation stage:');
        cy.contains('span', 'Format Pre-Moderation');

        cy.contains('a', 'Assign').click();
        checkSendNextStageOptionIsPresent();
        cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
        cy.get('#checkboxSendNextStageAssignYes').click();
        cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
        cy.waitJQuery();
        cy.reload();

        cy.contains('span', 'Manuscript Type Pre-Moderation');
        cy.contains('button', 'Activity Log').click();
        cy.contains('The submission has been sent to the Manuscript Type Pre-Moderation stage');
    });
    it("Checks stage advancing not present in last stage", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.contains('a', 'Assign').click();
        cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
        cy.get('#checkboxSendNextStageAssignYes').click();
        cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
        cy.waitJQuery();
        cy.reload();

        cy.contains('span', 'Area Moderation');
        cy.contains('a', 'Assign').click();
        cy.get('#checkboxSendNextStageAssignYes').should('not.exist');
        cy.get('#checkboxSendNextStageAssignNo').should('not.exist');
    });
});
