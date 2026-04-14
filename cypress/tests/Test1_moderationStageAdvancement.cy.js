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
        Cypress.config('defaultCommandTimeout', 10000);
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
    
    it("Author creates submission. Asserts submission goes to Format Pre-Moderation stage", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.createSubmission(submissionData);
        cy.contains('The moderation of your submission has been initiated and it has been forwarded to the Format Pre-Moderation stage, where it will undergo a screening process');
        cy.contains('Please wait for a response from the editorial team or an update on the status of your submission');
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
        cy.wait(3000);
        cy.reload();

        cy.contains('span', 'Manuscript Type Pre-Moderation');
        cy.contains('button', 'Activity Log').click();
        cy.contains('The submission has been sent to the Manuscript Type Pre-Moderation stage');
    });
    it("Checks sending of email notification after sending to next moderation stage", function() {
        cy.visit('localhost:8025');
        cy.get('b:contains("Advancement in Submission Moderation")').should('have.length', 1);
        cy.contains('b', 'Advancement in Submission Moderation')
            .parent().parent().parent()
            .within((node) => {
                cy.contains('fpaglieri@mailinator.com');
            });
        cy.get('b:contains("Advancement in Submission Moderation")').click();
        cy.get('#nav-tab button:contains("Text")').click();

        cy.contains('Your submission has been forwarded to the Manuscript Type Pre-Moderation stage');
        cy.contains('To facilitate the moderation process, please provide an up-to-date ORCID that includes the most recent scholarly work for at least one of the authors listed in the submission');
        cy.contains('Optionally, you may also provide an endorsement for the preprint, if you have one');
        cy.contains('For more information, we recommend reading our FAQs #10 and #19');
    });
    it("Checks stage advancing not present in last stage", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.contains('a', 'Assign').click();
        cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
        cy.get('#checkboxSendNextStageAssignYes').click();
        cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
        cy.wait(3000);
        cy.reload();

        cy.contains('span', 'Area Moderation');
        cy.contains('a', 'Assign').click();
        cy.get('#checkboxSendNextStageAssignYes').should('not.exist');
        cy.get('#checkboxSendNextStageAssignNo').should('not.exist');
    });
});
