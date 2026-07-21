import '../support/commands.js';

function openAssignParticipantForm() {
    cy.get('[data-cy="participant-manager"] button:contains("Assign")').first().click();
    cy.waitJQuery();
    cy.get('#addParticipantForm', { timeout: 20000 }).should('be.visible');
}

function checkSendNextStageOptionIsNotPresent() {
    cy.get('#addParticipantForm').within(() => {
        cy.contains("This submission is in the Format Pre-Moderation stage").should('not.exist');
        cy.get('#checkboxSendNextStageAssignYes').should('not.exist');
        cy.get('#checkboxSendNextStageAssignNo').should('not.exist');
    });
}

function closeAssignParticipantForm() {
    cy.get('.DialogClose:visible').last().click({ force: true });
    cy.get('#addParticipantForm').should('not.exist');
}

describe("SciELO Moderation Stages - Stage advancement hidden scenarios", function() {
    let submissionData;

    before(function() {
        Cypress.config('defaultCommandTimeout', 10000);
        submissionData = {
            title: "Candyman",
            abstract: 'A ghost appears when you speak his name 5 times in front of a mirror',
            keywords: ['plugin', 'testing'],
            contributors: [
                {
                    'given': 'Clive',
                    'family': 'Barker',
                    'email': 'clive.barker@stab.com',
                    'country': 'United Kingdom'
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
    it("Sending of submission to next moderation stage does not appear when posted/declined", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        // Post the preprint
        cy.get('button:contains("Post the preprint")').click();
        cy.get('button:contains("Post"):visible').click();
        cy.get('[id^="publish"] button:contains("Post")').click();
        cy.waitJQuery();

        // While posted, the advance-stage option must be hidden in the assign form
        cy.openWorkflowMenu('Production');
        openAssignParticipantForm();
        checkSendNextStageOptionIsNotPresent();
        closeAssignParticipantForm();

        // Unpost the preprint
        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button').contains('Unpost').click();
        cy.get('[data-cy=dialog] button').contains('Unpost').click();
        cy.waitJQuery();

        // Decline the submission
        cy.openWorkflowMenu('Production');
        cy.clickDecision('Decline Submission');
        cy.get('button:contains("Record Decision")').click();
        cy.get('a.pkpButton:contains("View Submission")').click();
        cy.waitJQuery();

        // While declined, the advance-stage option must be hidden in the assign form
        cy.openWorkflowMenu('Production');
        openAssignParticipantForm();
        checkSendNextStageOptionIsNotPresent();
    });
});
